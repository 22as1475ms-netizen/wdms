<?php
class Document {
  private static function sortOrderSql(array $filters = [], string $fallback = 'd.id DESC'): string {
    $sort = trim((string)($filters['sort'] ?? ''));
    $activityExpr = "COALESCE((SELECT MAX(dv.created_at) FROM document_versions dv WHERE dv.document_id=d.id), d.reviewed_at, d.submitted_at, d.created_at)";
    return match ($sort) {
      'name_asc' => 'd.name ASC, d.id ASC',
      'name_desc' => 'd.name DESC, d.id DESC',
      'modified_asc' => $activityExpr . ' ASC, d.id ASC',
      'modified_desc' => $activityExpr . ' DESC, d.id DESC',
      default => $fallback,
    };
  }

  public static function create(
    PDO $pdo,
    int $ownerId,
    ?int $folderId,
    string $name,
    string $storageArea = 'PRIVATE',
    ?int $divisionId = null,
    array $metadata = []
  ): int {
    $storageArea = self::normalizeStorageArea($storageArea);
    $pdo->prepare("
      INSERT INTO documents(
        name, owner_id, folder_id, storage_area, division_id, document_code, title,
        document_type, signatory, current_location, routing_status, priority_level, document_date
      )
      VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([
      $name,
      $ownerId,
      $folderId,
      $storageArea,
      $divisionId,
      self::cleanText($metadata['document_code'] ?? null, 80),
      self::cleanText($metadata['title'] ?? null, 255),
      self::normalizeDocumentType((string)($metadata['document_type'] ?? 'INCOMING')),
      self::cleanText($metadata['signatory'] ?? null, 150),
      self::cleanText($metadata['current_location'] ?? null, 180),
      self::normalizeRoutingStatus((string)($metadata['routing_status'] ?? 'NOT_ROUTED')),
      self::normalizePriorityLevel((string)($metadata['priority_level'] ?? 'NORMAL')),
      self::cleanDate($metadata['document_date'] ?? null),
    ]);
    return (int)$pdo->lastInsertId();
  }

  public static function normalizeStorageArea(string $storageArea): string {
    return strtoupper(trim($storageArea)) === 'OFFICIAL' ? 'OFFICIAL' : 'PRIVATE';
  }

  public static function rename(PDO $pdo, int $id, string $name): void {
    $pdo->prepare("UPDATE documents SET name=? WHERE id=?")->execute([$name, $id]);
  }

  public static function updateMetadata(PDO $pdo, int $id, array $data): void {
    $pdo->prepare("
      UPDATE documents
      SET document_code = ?, title = ?, document_type = ?, signatory = ?, current_location = ?,
          routing_status = ?, priority_level = ?, document_date = ?, tags = ?, category = ?,
          status = ?, retention_until = ?, storage_area = ?
      WHERE id = ?
    ")->execute([
      self::cleanText($data['document_code'] ?? null, 80),
      self::cleanText($data['title'] ?? null, 255),
      self::normalizeDocumentType((string)($data['document_type'] ?? 'INCOMING')),
      self::cleanText($data['signatory'] ?? null, 150),
      self::cleanText($data['current_location'] ?? null, 180),
      self::normalizeRoutingStatus((string)($data['routing_status'] ?? 'NOT_ROUTED')),
      self::normalizePriorityLevel((string)($data['priority_level'] ?? 'NORMAL')),
      self::cleanDate($data['document_date'] ?? null),
      $data['tags'] ?? null,
      $data['category'] ?? null,
      $data['status'] ?? 'Draft',
      $data['retention_until'] ?? null,
      self::normalizeStorageArea((string)($data['storage_area'] ?? 'PRIVATE')),
      $id,
    ]);
  }

  public static function updateTrackingState(PDO $pdo, int $id, string $currentLocation, string $routingStatus): void {
    $pdo->prepare("
      UPDATE documents
      SET current_location = ?, routing_status = ?
      WHERE id = ?
    ")->execute([
      self::cleanText($currentLocation, 180),
      self::normalizeRoutingStatus($routingStatus),
      $id,
    ]);
  }

  public static function moveToStorageArea(PDO $pdo, int $id, string $storageArea, ?int $folderId, ?int $divisionId = null): void {
    $pdo->prepare("
      UPDATE documents
      SET storage_area = ?, folder_id = ?, division_id = ?, status = 'Draft', review_note = NULL,
          approval_locked = 0, submitted_at = NULL, reviewed_at = NULL, reviewed_by = NULL
      WHERE id = ?
    ")->execute([
      self::normalizeStorageArea($storageArea),
      $folderId,
      $divisionId,
      $id,
    ]);
  }

  public static function moveFolderTreeToStorageArea(PDO $pdo, int $ownerId, array $folderIdMap, string $fromStorageArea, string $toStorageArea, ?int $divisionId = null): int {
    if (empty($folderIdMap)) {
      return 0;
    }

    $updated = 0;
    foreach ($folderIdMap as $sourceFolderId => $targetFolderId) {
      $s = $pdo->prepare("
        UPDATE documents
        SET storage_area=?, folder_id=?, division_id=?, status='Draft', review_note=NULL,
            approval_locked=0, submitted_at=NULL, reviewed_at=NULL, reviewed_by=NULL
        WHERE owner_id=? AND folder_id=? AND storage_area=? AND deleted_at IS NULL
      ");
      $s->execute([
        self::normalizeStorageArea($toStorageArea),
        (int)$targetFolderId,
        $divisionId,
        $ownerId,
        (int)$sourceFolderId,
        self::normalizeStorageArea($fromStorageArea),
      ]);
      $updated += $s->rowCount();
    }

    return $updated;
  }

  public static function listActiveForOwnerInStorage(PDO $pdo, int $ownerId, string $storageArea): array {
    $s = $pdo->prepare("
      SELECT d.*
      FROM documents d
      WHERE owner_id=? AND storage_area=? AND deleted_at IS NULL
    ");
    $s->execute([$ownerId, self::normalizeStorageArea($storageArea)]);
    return $s->fetchAll();
  }

  public static function get(PDO $pdo, int $id): ?array {
    $s = $pdo->prepare("
      SELECT d.*, u.name owner_name, u.email owner_email, u.division_id owner_division_id, dv.name division_name
      FROM documents d
      JOIN users u ON u.id=d.owner_id
      LEFT JOIN divisions dv ON dv.id = d.division_id
      WHERE d.id=? LIMIT 1
    ");
    $s->execute([$id]);
    $r = $s->fetch();
    return $r ?: null;
  }

  public static function findActiveByOwnerAndNameInFolder(PDO $pdo, int $ownerId, string $name, ?int $folderId, ?string $storageArea = null): ?array {
    $params = [$ownerId, $name];
    $storageSql = '';
    if ($storageArea !== null) {
      $storageSql = " AND d.storage_area = ? ";
      $params[] = self::normalizeStorageArea($storageArea);
    }

    if ($folderId === null) {
      $s = $pdo->prepare("
        SELECT d.*, u.name owner_name
        FROM documents d
        JOIN users u ON u.id=d.owner_id
        WHERE d.owner_id=? AND d.folder_id IS NULL AND d.deleted_at IS NULL AND d.name=? $storageSql
        LIMIT 1
      ");
    } else {
      $params = [$ownerId, $folderId, $name];
      if ($storageArea !== null) {
        $params[] = self::normalizeStorageArea($storageArea);
      }
      $s = $pdo->prepare("
        SELECT d.*, u.name owner_name
        FROM documents d
        JOIN users u ON u.id=d.owner_id
        WHERE d.owner_id=? AND d.folder_id=? AND d.deleted_at IS NULL AND d.name=? $storageSql
        LIMIT 1
      ");
    }

    $s->execute($params);
    $r = $s->fetch();
    return $r ?: null;
  }

  public static function listActiveNamesForOwner(PDO $pdo, int $ownerId, ?int $folderId, ?string $storageArea = null): array {
    $params = [$ownerId];
    $storageSql = '';
    if ($storageArea !== null) {
      $storageSql = " AND storage_area = ? ";
      $params[] = self::normalizeStorageArea($storageArea);
    }

    if ($folderId === null) {
      $s = $pdo->prepare("SELECT name FROM documents WHERE owner_id=? AND folder_id IS NULL AND deleted_at IS NULL $storageSql");
    } else {
      $params = [$ownerId, $folderId];
      if ($storageArea !== null) {
        $params[] = self::normalizeStorageArea($storageArea);
      }
      $s = $pdo->prepare("SELECT name FROM documents WHERE owner_id=? AND folder_id=? AND deleted_at IS NULL $storageSql");
    }

    $s->execute($params);
    return array_map(static fn(array $row): string => (string)$row['name'], $s->fetchAll());
  }

  public static function listMy(PDO $pdo, int $userId, string $search, ?int $folderId, int $page, int $per, bool $trash=false, array $filters = []): array {
    $where = $trash ? "d.deleted_at IS NOT NULL" : "d.deleted_at IS NULL";
    $params = [$userId];
    $searchSql = self::buildSearchSql($search, $params);

    $folderSql = "";
    if ($folderId !== null && $folderId > 0) {
      $folderSql = " AND d.folder_id = ? ";
      $params[] = $folderId;
    } elseif (!$trash) {
      $folderSql = " AND d.folder_id IS NULL ";
    }

    $metaSql = self::buildFilterSql($filters, $params);

    $off = ($page-1)*$per;
    $orderBy = self::sortOrderSql($filters, 'd.id DESC');
    $activityExpr = "COALESCE((SELECT MAX(dv.created_at) FROM document_versions dv WHERE dv.document_id=d.id), d.reviewed_at, d.submitted_at, d.created_at)";
    $sql = "
      SELECT d.*, u.name owner_name,
             (SELECT MAX(version_number) FROM document_versions dv WHERE dv.document_id=d.id) latest_version,
             (SELECT dv.file_path FROM document_versions dv WHERE dv.document_id=d.id ORDER BY dv.version_number DESC, dv.id DESC LIMIT 1) latest_file_path,
             $activityExpr AS last_activity_at
      FROM documents d
      JOIN users u ON u.id=d.owner_id
      WHERE d.owner_id=? AND $where $searchSql $folderSql $metaSql
      ORDER BY $orderBy
      LIMIT $per OFFSET $off
    ";
    $s = $pdo->prepare($sql);
    $s->execute($params);
    $rows = $s->fetchAll();

    $sqlc = "SELECT COUNT(*) FROM documents d WHERE d.owner_id=? AND $where $searchSql $folderSql $metaSql";
    $sc = $pdo->prepare($sqlc);
    $sc->execute($params);
    $total = (int)$sc->fetchColumn();

    return [$rows, $total];
  }

  public static function listTrashedForOwner(PDO $pdo, int $userId, string $search, array $filters = []): array {
    $params = [$userId];
    $searchSql = self::buildSearchSql($search, $params);
    $metaSql = self::buildFilterSql($filters, $params);
    $activityExpr = "COALESCE((SELECT MAX(dv.created_at) FROM document_versions dv WHERE dv.document_id=d.id), d.deleted_at, d.reviewed_at, d.submitted_at, d.created_at)";

    $s = $pdo->prepare("
      SELECT d.*, u.name owner_name, f.name AS folder_name,
             (SELECT MAX(version_number) FROM document_versions dv WHERE dv.document_id=d.id) latest_version,
             (SELECT dv.file_path FROM document_versions dv WHERE dv.document_id=d.id ORDER BY dv.version_number DESC, dv.id DESC LIMIT 1) latest_file_path,
             $activityExpr AS last_activity_at
      FROM documents d
      JOIN users u ON u.id=d.owner_id
      LEFT JOIN folders f ON f.id=d.folder_id
      WHERE d.owner_id=? AND d.deleted_at IS NOT NULL $searchSql $metaSql
      ORDER BY " . self::sortOrderSql($filters, 'd.deleted_at DESC, d.id DESC') . "
    ");
    $s->execute($params);
    return $s->fetchAll();
  }

  public static function listShared(PDO $pdo, int $userId, string $search, int $page, int $per, array $filters = []): array {
    $off = ($page-1)*$per;
    $params = [$userId];
    $searchSql = self::buildSearchSql($search, $params);
    $metaSql = self::buildFilterSql($filters, $params);
    $orderBy = self::sortOrderSql($filters, 'd.id DESC');
    $activityExpr = "COALESCE((SELECT MAX(dv.created_at) FROM document_versions dv WHERE dv.document_id=d.id), d.reviewed_at, d.submitted_at, d.created_at)";

    $s = $pdo->prepare("
      SELECT d.*, u.name owner_name, p.*,
             (SELECT MAX(version_number) FROM document_versions dv WHERE dv.document_id=d.id) latest_version,
             (SELECT dv.file_path FROM document_versions dv WHERE dv.document_id=d.id ORDER BY dv.version_number DESC, dv.id DESC LIMIT 1) latest_file_path,
             $activityExpr AS last_activity_at
      FROM permissions p
      JOIN documents d ON d.id=p.document_id
      JOIN users u ON u.id=d.owner_id
      WHERE p.user_id=? AND d.deleted_at IS NULL $searchSql $metaSql
      ORDER BY $orderBy
      LIMIT $per OFFSET $off
    ");
    $s->execute($params);
    $rows = $s->fetchAll();

    $sc = $pdo->prepare("
      SELECT COUNT(*)
      FROM permissions p
      JOIN documents d ON d.id=p.document_id
      WHERE p.user_id=? AND d.deleted_at IS NULL $searchSql $metaSql
    ");
    $sc->execute($params);
    $total = (int)$sc->fetchColumn();

    return [$rows, $total];
  }

  public static function listForDivisionChief(PDO $pdo, int $divisionId, array $filters = []): array {
    $params = [$divisionId];
    $where = "d.division_id=? AND d.storage_area='OFFICIAL' AND d.deleted_at IS NULL";
    $status = trim((string)($filters['status'] ?? ''));
    if ($status !== '') {
      $where .= " AND d.status = ? ";
      $params[] = $status;
    }
    $employeeId = (int)($filters['employee_id'] ?? 0);
    if ($employeeId > 0) {
      $where .= " AND d.owner_id = ? ";
      $params[] = $employeeId;
    }
    $search = trim((string)($filters['search'] ?? ''));
    $where .= self::buildSearchSql($search, $params);

    $s = $pdo->prepare("
      SELECT d.*, u.name owner_name, u.email owner_email,
             (SELECT MAX(version_number) FROM document_versions dv WHERE dv.document_id=d.id) latest_version,
             (SELECT dv.file_path FROM document_versions dv WHERE dv.document_id=d.id ORDER BY dv.version_number DESC, dv.id DESC LIMIT 1) latest_file_path,
             COALESCE((SELECT MAX(dv.created_at) FROM document_versions dv WHERE dv.document_id=d.id), d.reviewed_at, d.submitted_at, d.created_at) AS last_activity_at
      FROM documents d
      JOIN users u ON u.id = d.owner_id
      WHERE $where
      ORDER BY
        CASE d.status
          WHEN 'To be reviewed' THEN 0
          WHEN 'Rejected' THEN 1
          WHEN 'Approved' THEN 2
          ELSE 3
        END,
        COALESCE(d.submitted_at, d.created_at) DESC
    ");
    $s->execute($params);
    return $s->fetchAll();
  }

  public static function softDelete(PDO $pdo, int $id, ?int $deletedBy = null, ?string $reason = null): void {
    $pdo->prepare("UPDATE documents SET deleted_at=NOW(), deleted_by=?, deleted_reason=? WHERE id=?")
      ->execute([$deletedBy, $reason, $id]);
  }

  public static function softDeleteByFolder(PDO $pdo, int $ownerId, int $folderId, ?int $deletedBy = null, ?string $reason = null, ?string $storageArea = null): int {
    $params = [$deletedBy, $reason, $ownerId, $folderId];
    $areaSql = '';
    if ($storageArea !== null) {
      $areaSql = " AND storage_area = ? ";
      $params[] = self::normalizeStorageArea($storageArea);
    }
    $s = $pdo->prepare("UPDATE documents SET deleted_at=NOW(), deleted_by=?, deleted_reason=? WHERE owner_id=? AND folder_id=? AND deleted_at IS NULL $areaSql");
    $s->execute($params);
    return $s->rowCount();
  }

  public static function trashedIdsForOwner(PDO $pdo, int $ownerId): array {
    $s = $pdo->prepare("SELECT id FROM documents WHERE owner_id=? AND deleted_at IS NOT NULL");
    $s->execute([$ownerId]);
    return array_map(static fn(array $row): int => (int)$row['id'], $s->fetchAll());
  }

  public static function trashedIdsEligibleForPurge(PDO $pdo, int $ownerId, int $retentionDays): array {
    if ($retentionDays <= 0) {
      return self::trashedIdsForOwner($pdo, $ownerId);
    }
    $s = $pdo->prepare("
      SELECT id
      FROM documents
      WHERE owner_id=? AND deleted_at IS NOT NULL
        AND deleted_at <= (NOW() - INTERVAL ? DAY)
    ");
    $s->execute([$ownerId, $retentionDays]);
    return array_map(static fn(array $row): int => (int)$row['id'], $s->fetchAll());
  }

  public static function idsByFolderIds(PDO $pdo, array $folderIds, ?int $ownerId = null): array {
    $ids = array_values(array_unique(array_map('intval', $folderIds)));
    if (empty($ids)) {
      return [];
    }

    $ph = implode(',', array_fill(0, count($ids), '?'));
    $params = $ids;
    $ownerSql = '';
    if ($ownerId !== null) {
      $ownerSql = ' AND owner_id=?';
      $params[] = $ownerId;
    }

    $s = $pdo->prepare("SELECT id FROM documents WHERE folder_id IN ($ph) $ownerSql");
    $s->execute($params);
    return array_map(static fn(array $row): int => (int)$row['id'], $s->fetchAll());
  }

  public static function hardDeleteByIds(PDO $pdo, array $docIds): int {
    if (empty($docIds)) {
      return 0;
    }
    $ids = array_values(array_map('intval', $docIds));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $s = $pdo->prepare("DELETE FROM documents WHERE id IN ($ph)");
    $s->execute($ids);
    return $s->rowCount();
  }

  public static function restore(PDO $pdo, int $id): void {
    $pdo->prepare("UPDATE documents SET deleted_at=NULL, deleted_by=NULL, deleted_reason=NULL WHERE id=?")->execute([$id]);
  }

  public static function checkout(PDO $pdo, int $id, int $userId): void {
    $pdo->prepare("UPDATE documents SET checked_out_by=?, checked_out_at=NOW() WHERE id=?")
      ->execute([$userId, $id]);
  }

  public static function checkin(PDO $pdo, int $id): void {
    $pdo->prepare("UPDATE documents SET checked_out_by=NULL, checked_out_at=NULL WHERE id=?")
      ->execute([$id]);
  }

  public static function submitForReview(PDO $pdo, int $id, int $divisionId): void {
    $pdo->prepare("
      UPDATE documents
      SET storage_area='OFFICIAL', division_id=?, status='To be reviewed', submitted_at=NOW(),
          approval_locked=1, checked_out_by=NULL, checked_out_at=NULL, review_note=NULL,
          review_acceptance_status='PENDING', review_accepted_at=NULL, review_declined_at=NULL,
          review_acceptance_note=NULL
      WHERE id=?
    ")->execute([$divisionId, $id]);
  }

  public static function acceptReviewAssignment(PDO $pdo, int $id): void {
    $pdo->prepare("
      UPDATE documents
      SET review_acceptance_status='ACCEPTED', review_accepted_at=NOW(), review_declined_at=NULL, review_acceptance_note=NULL
      WHERE id=?
    ")->execute([$id]);
  }

  public static function declineReviewAssignment(PDO $pdo, int $id, ?string $note): void {
    $pdo->prepare("
      UPDATE documents
      SET review_acceptance_status='DECLINED', review_accepted_at=NULL, review_declined_at=NOW(), review_acceptance_note=?
      WHERE id=?
    ")->execute([self::cleanText($note, 1000), $id]);
  }

  public static function finalizeReview(PDO $pdo, int $id, string $decision, ?string $note, int $reviewerId): void {
    $status = strtoupper($decision) === 'APPROVED' ? 'Approved' : 'Rejected';
    $locked = $status === 'Approved' ? 1 : 0;
    $documentType = $status === 'Approved' ? 'OUTGOING' : 'INCOMING';
    $pdo->prepare("
      UPDATE documents
      SET status=?, review_note=?, reviewed_by=?, reviewed_at=NOW(), approval_locked=?,
          checked_out_by=NULL, checked_out_at=NULL, review_acceptance_status='ACCEPTED',
          document_type=?
      WHERE id=?
    ")->execute([$status, $note, $reviewerId, $locked, $documentType, $id]);
  }

  public static function countAll(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
  }

  public static function countActive(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL")->fetchColumn();
  }

  public static function countTrashed(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE deleted_at IS NOT NULL")->fetchColumn();
  }

  public static function listInventoryForOwner(PDO $pdo, int $ownerId): array {
    $s = $pdo->prepare("
      SELECT
        d.id,
        d.name,
        d.owner_id,
        d.folder_id,
        d.storage_area,
        d.status,
        d.document_code,
        d.title,
        d.document_type,
        d.routing_status,
        d.priority_level,
        d.current_location,
        d.deleted_at,
        d.created_at,
        d.submitted_at,
        d.reviewed_at,
        f.name AS folder_name,
        COALESCE((SELECT MAX(dv.version_number) FROM document_versions dv WHERE dv.document_id = d.id), 0) AS latest_version,
        COALESCE((SELECT COUNT(*) FROM document_versions dv WHERE dv.document_id = d.id), 0) AS version_count,
        COALESCE((SELECT COUNT(*) FROM permissions p WHERE p.document_id = d.id), 0) AS shared_count,
        COALESCE((SELECT MAX(dv.created_at) FROM document_versions dv WHERE dv.document_id = d.id), d.reviewed_at, d.submitted_at, d.created_at) AS last_activity_at
      FROM documents d
      LEFT JOIN folders f ON f.id = d.folder_id
      WHERE d.owner_id = ?
      ORDER BY
        d.storage_area DESC,
        CASE WHEN f.name IS NULL THEN 1 ELSE 0 END,
        f.name ASC,
        d.name ASC
    ");
    $s->execute([$ownerId]);
    return $s->fetchAll();
  }

  public static function sharedMembersPreview(PDO $pdo, array $documentIds, int $limit = 3): array {
    $ids = array_values(array_filter(array_map('intval', $documentIds), static fn(int $v): bool => $v > 0));
    if (empty($ids)) {
      return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));

    $totalsStmt = $pdo->prepare("
      SELECT document_id, COUNT(*) AS total
      FROM permissions
      WHERE document_id IN ($ph)
      GROUP BY document_id
    ");
    $totalsStmt->execute($ids);
    $totals = [];
    foreach ($totalsStmt->fetchAll() as $row) {
      $totals[(int)$row['document_id']] = (int)$row['total'];
    }

    $membersStmt = $pdo->prepare("
      SELECT p.document_id, u.id AS user_id, u.name, u.email, u.avatar_photo, u.avatar_preset
      FROM permissions p
      JOIN users u ON u.id = p.user_id
      WHERE p.document_id IN ($ph)
      ORDER BY p.document_id ASC, p.id ASC
    ");
    $membersStmt->execute($ids);
    $map = [];
    foreach ($membersStmt->fetchAll() as $row) {
      $docId = (int)$row['document_id'];
      if (!isset($map[$docId])) {
        $map[$docId] = [
          'total' => (int)($totals[$docId] ?? 0),
          'items' => [],
        ];
      }
      if (count($map[$docId]['items']) >= $limit) {
        continue;
      }
      $map[$docId]['items'][] = $row;
    }
    return $map;
  }

  private static function buildFilterSql(array $filters, array &$params): string {
    $metaSql = "";
    $status = trim((string)($filters['status'] ?? ''));
    $category = trim((string)($filters['category'] ?? ''));
    $tags = trim((string)($filters['tags'] ?? ''));
    $dateFrom = trim((string)($filters['date_from'] ?? ''));
    $dateTo = trim((string)($filters['date_to'] ?? ''));
    $storageArea = trim((string)($filters['storage_area'] ?? ''));
    $documentCode = trim((string)($filters['document_code'] ?? ''));
    $documentType = trim((string)($filters['document_type'] ?? ''));
    $routingStatus = trim((string)($filters['routing_status'] ?? ''));
    $priorityLevel = trim((string)($filters['priority_level'] ?? ''));
    $currentLocation = trim((string)($filters['current_location'] ?? ''));

    if ($status !== '') {
      $metaSql .= " AND d.status = ? ";
      $params[] = $status;
    }
    if ($category !== '') {
      $metaSql .= " AND d.category LIKE ? ";
      $params[] = "%" . $category . "%";
    }
    if ($tags !== '') {
      $metaSql .= " AND d.tags LIKE ? ";
      $params[] = "%" . $tags . "%";
    }
    if ($dateFrom !== '') {
      $metaSql .= " AND DATE(d.created_at) >= ? ";
      $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
      $metaSql .= " AND DATE(d.created_at) <= ? ";
      $params[] = $dateTo;
    }
    if ($storageArea !== '') {
      $metaSql .= " AND d.storage_area = ? ";
      $params[] = self::normalizeStorageArea($storageArea);
    }
    if ($documentCode !== '') {
      $metaSql .= " AND d.document_code LIKE ? ";
      $params[] = "%" . $documentCode . "%";
    }
    if ($documentType !== '') {
      $metaSql .= " AND d.document_type = ? ";
      $params[] = self::normalizeDocumentType($documentType);
    }
    if ($routingStatus !== '') {
      $metaSql .= " AND d.routing_status = ? ";
      $params[] = self::normalizeRoutingStatus($routingStatus);
    }
    if ($priorityLevel !== '') {
      $metaSql .= " AND d.priority_level = ? ";
      $params[] = self::normalizePriorityLevel($priorityLevel);
    }
    if ($currentLocation !== '') {
      $metaSql .= " AND d.current_location LIKE ? ";
      $params[] = "%" . $currentLocation . "%";
    }

    return $metaSql;
  }

  private static function buildSearchSql(string $search, array &$params): string {
    $term = trim($search);
    if ($term === '') {
      return '';
    }

    $like = '%' . $term . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

    return "
      AND (
        d.name LIKE ?
        OR COALESCE(d.title, '') LIKE ?
        OR COALESCE(d.document_code, '') LIKE ?
        OR COALESCE(d.current_location, '') LIKE ?
        OR COALESCE(d.signatory, '') LIKE ?
        OR COALESCE(d.tags, '') LIKE ?
      )
    ";
  }

  public static function normalizeDocumentType(string $value): string {
    return strtoupper(trim($value)) === 'OUTGOING' ? 'OUTGOING' : 'INCOMING';
  }

  public static function normalizeRoutingStatus(string $value): string {
    return match (strtoupper(trim($value))) {
      'PENDING_SHARE_ACCEPTANCE' => 'PENDING_SHARE_ACCEPTANCE',
      'SHARE_ACCEPTED' => 'SHARE_ACCEPTED',
      'SHARE_DECLINED' => 'SHARE_DECLINED',
      'PENDING_REVIEW_ACCEPTANCE' => 'PENDING_REVIEW_ACCEPTANCE',
      'IN_REVIEW' => 'IN_REVIEW',
      'REVIEW_ASSIGNMENT_DECLINED' => 'REVIEW_ASSIGNMENT_DECLINED',
      'APPROVED' => 'APPROVED',
      'REJECTED' => 'REJECTED',
      default => 'AVAILABLE',
    };
  }

  public static function normalizePriorityLevel(string $value): string {
    return match (strtoupper(trim($value))) {
      'LOW' => 'LOW',
      'HIGH' => 'HIGH',
      'URGENT' => 'URGENT',
      default => 'NORMAL',
    };
  }

  private static function cleanText(mixed $value, int $limit): ?string {
    $clean = trim((string)$value);
    if ($clean === '') {
      return null;
    }
    return mb_substr($clean, 0, $limit);
  }

  private static function cleanDate(mixed $value): ?string {
    $clean = trim((string)$value);
    if ($clean === '') {
      return null;
    }
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean) ? $clean : null;
  }
}
