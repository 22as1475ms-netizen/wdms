<?php
require_once __DIR__ . "/../models/Document.php";
require_once __DIR__ . "/../models/Version.php";
require_once __DIR__ . "/../models/AuditLog.php";

class DocumentService {
  private const EDITABLE_EXTENSIONS = ['docx', 'xlsx', 'xls'];

  public static function upload(
    PDO $pdo,
    array $file,
    int $ownerId,
    ?int $folderId,
    ?int $actorId = null,
    string $storageArea = 'PRIVATE',
    ?int $divisionId = null,
    array $metadata = []
  ): int {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
      throw new RuntimeException("Invalid upload");
    }

    $original = self::sanitizeFilename((string)($file['name'] ?? 'file'));
    self::assertUploadConstraints($file, $actorId ?? $ownerId);

    $actorId = $actorId ?? $ownerId;
    $storageArea = Document::normalizeStorageArea($storageArea);
    $docId = Document::create($pdo, $ownerId, $folderId, $original, $storageArea, $divisionId, $metadata);
    self::storeUploadedVersion($pdo, $docId, $file, $actorId);

    AuditLog::add($pdo, $actorId, "Uploaded document", $docId, $original);

    return $docId;
  }

  public static function uploadNewVersion(PDO $pdo, int $docId, array $file, int $userId): int {
    $doc = Document::get($pdo, $docId);
    if (!$doc) {
      throw new RuntimeException("Document not found");
    }

    $version = self::storeUploadedVersion($pdo, $docId, $file, $userId);
    AuditLog::add($pdo, $userId, "Uploaded new version", $docId, "version=".$version);

    return $version;
  }

  public static function restoreVersion(PDO $pdo, int $docId, int $versionId, int $userId): int {
    $doc = Document::get($pdo, $docId);
    $source = Version::get($pdo, $versionId);
    if (!$doc || !$source || (int)$source['document_id'] !== $docId) {
      throw new RuntimeException("Version not found");
    }

    $sourcePath = self::absolutePathFromVersion($source['file_path']);
    if (!is_file($sourcePath)) {
      throw new RuntimeException("Source file missing");
    }

    $next = Version::nextNumber($pdo, $docId);
    $safeName = self::buildStoredFilename($docId, $next, $doc['name']);
    $storageArea = (string)($doc['storage_area'] ?? 'PRIVATE');
    $targetPath = self::absolutePathForOwner((int)$doc['owner_id'], $safeName, $storageArea);
    self::assertWithinQuota((int)$doc['owner_id'], filesize($sourcePath) ?: 0, $storageArea);

    if (!copy($sourcePath, $targetPath)) {
      throw new RuntimeException("Failed to restore version");
    }

    Version::add($pdo, $docId, $userId, self::relativePath((int)$doc['owner_id'], $safeName, $storageArea), $next);
    self::archiveNonLatestVersions($pdo, $docId, (int)$doc['owner_id'], $next, $storageArea);
    AuditLog::add($pdo, $userId, "Restored version", $docId, "from=".$source['version_number'].",to=".$next);

    return $next;
  }

  public static function storageStats(PDO $pdo): array {
    $totalBytes = 0;
    $files = 0;

    if (is_dir(STORAGE_DIR)) {
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(STORAGE_DIR, FilesystemIterator::SKIP_DOTS)
      );
      foreach ($iterator as $item) {
        if ($item->isFile()) {
          $files++;
          $totalBytes += $item->getSize();
        }
      }
    }

    return [
      'documents' => Document::countActive($pdo),
      'documents_total' => Document::countAll($pdo),
      'documents_trashed' => Document::countTrashed($pdo),
      'versions' => Version::countAll($pdo),
      'files' => $files,
      'bytes' => $totalBytes,
    ];
  }

  public static function ownerStorageSummary(int $ownerId, string $storageArea = 'ALL'): array {
    $storageArea = strtoupper(trim($storageArea));
    $used = self::ownerStorageBytes($ownerId, $storageArea);
    $limit = match ($storageArea) {
      'PRIVATE' => PRIVATE_STORAGE_LIMIT_BYTES,
      'OFFICIAL' => OFFICIAL_STORAGE_LIMIT_BYTES,
      default => PRIVATE_STORAGE_LIMIT_BYTES + OFFICIAL_STORAGE_LIMIT_BYTES,
    };
    return [
      'used' => $used,
      'limit' => $limit,
      'remaining' => max(0, $limit - $used),
      'percent' => $limit > 0 ? min(100, ($used / $limit) * 100) : 0,
    ];
  }

  public static function ownerStorageBreakdown(int $ownerId): array {
    return [
      'private' => self::ownerStorageSummary($ownerId, 'PRIVATE'),
      'official' => self::ownerStorageSummary($ownerId, 'OFFICIAL'),
      'all' => self::ownerStorageSummary($ownerId, 'ALL'),
    ];
  }

  public static function absolutePathFromVersion(string $filePath): string {
    $normalized = str_replace(['\\', '..'], ['/', ''], $filePath);
    $prefix = '/storage/documents/';
    $pos = strpos($normalized, $prefix);
    if ($pos !== false) {
      $normalized = substr($normalized, $pos + strlen($prefix));
    }

    $normalized = ltrim($normalized, '/');
    return rtrim(STORAGE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
  }

  public static function signedDocumentToken(int $docId): string {
    return hash_hmac('sha256', (string)$docId, APP_SECRET);
  }

  public static function verifyDocumentToken(int $docId, string $token): bool {
    return hash_equals(self::signedDocumentToken($docId), $token);
  }

  private static function storeUploadedVersion(PDO $pdo, int $docId, array $file, int $userId): int {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
      throw new RuntimeException("Invalid upload");
    }

    $doc = Document::get($pdo, $docId);
    if (!$doc) {
      throw new RuntimeException("Document not found");
    }

    $ver = Version::nextNumber($pdo, $docId);
    $sourceName = self::sanitizeFilename((string)($file['name'] ?? $doc['name']));
    $ext = strtolower((string)pathinfo($sourceName, PATHINFO_EXTENSION));
    $docExt = strtolower((string)pathinfo((string)$doc['name'], PATHINFO_EXTENSION));
    self::assertUploadConstraints($file, $userId);

    if ($docExt !== $ext) {
      throw new RuntimeException("Uploaded version must match the document format");
    }
    $storageArea = (string)($doc['storage_area'] ?? 'PRIVATE');
    self::assertWithinQuota((int)$doc['owner_id'], (int)($file['size'] ?? 0), $storageArea);

    $safeName = self::buildStoredFilename($docId, $ver, $sourceName);
    $absPath = self::absolutePathForOwner((int)$doc['owner_id'], $safeName, $storageArea);

    if (!move_uploaded_file($file['tmp_name'], $absPath)) {
      throw new RuntimeException("Failed to store file");
    }

    Version::add($pdo, $docId, $userId, self::relativePath((int)$doc['owner_id'], $safeName, $storageArea), $ver);
    self::archiveNonLatestVersions($pdo, $docId, (int)$doc['owner_id'], $ver, $storageArea);
    return $ver;
  }

  private static function archiveNonLatestVersions(PDO $pdo, int $docId, int $ownerId, int $latestVersionNumber, string $storageArea = 'PRIVATE'): void {
    if ($latestVersionNumber <= 1) {
      return;
    }

    $s = $pdo->prepare("
      SELECT id, file_path, version_number
      FROM document_versions
      WHERE document_id = ? AND version_number < ?
      ORDER BY version_number ASC
    ");
    $s->execute([$docId, $latestVersionNumber]);
    $rows = $s->fetchAll();

    foreach ($rows as $row) {
      $currentPath = self::absolutePathFromVersion((string)$row['file_path']);
      if (!is_file($currentPath)) {
        continue;
      }

      $base = basename($currentPath);
      $targetPath = self::absolutePathForArchivedVersion($ownerId, $docId, $base, $storageArea);
      if (strtolower($currentPath) === strtolower($targetPath)) {
        continue;
      }

      $targetPath = self::resolveArchiveCollision($targetPath);
      if (!@rename($currentPath, $targetPath)) {
        if (!@copy($currentPath, $targetPath) || !@unlink($currentPath)) {
          continue;
        }
      }

      $rel = self::relativePathForArchivedVersion($ownerId, $docId, basename($targetPath), $storageArea);
      $u = $pdo->prepare("UPDATE document_versions SET file_path=? WHERE id=?");
      $u->execute([$rel, (int)$row['id']]);
    }
  }

  private static function absolutePathForArchivedVersion(int $ownerId, int $docId, string $basename, string $storageArea = 'PRIVATE'): string {
    $dir = self::storageRootForArea($storageArea)
      . DIRECTORY_SEPARATOR . $ownerId
      . DIRECTORY_SEPARATOR . 'previous_versions'
      . DIRECTORY_SEPARATOR . $docId;
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
      throw new RuntimeException("Failed to create archived versions directory");
    }
    return $dir . DIRECTORY_SEPARATOR . $basename;
  }

  private static function relativePathForArchivedVersion(int $ownerId, int $docId, string $basename, string $storageArea = 'PRIVATE'): string {
    $segment = strtolower(Document::normalizeStorageArea($storageArea));
    return "../storage/documents/" . $segment . "/" . $ownerId . "/previous_versions/" . $docId . "/" . $basename;
  }

  private static function resolveArchiveCollision(string $targetPath): string {
    if (!file_exists($targetPath)) {
      return $targetPath;
    }

    $dir = dirname($targetPath);
    $name = pathinfo($targetPath, PATHINFO_FILENAME);
    $ext = pathinfo($targetPath, PATHINFO_EXTENSION);
    $suffix = '_' . time();
    return $dir . DIRECTORY_SEPARATOR . $name . $suffix . ($ext !== '' ? '.' . $ext : '');
  }

  private static function normalizeEditableExtension(string $extension): ?string {
    $ext = strtolower(trim($extension));
    return in_array($ext, self::EDITABLE_EXTENSIONS, true) ? $ext : null;
  }

  private static function assertUploadConstraints(array $file, int $actorId): void {
    $size = (int)($file['size'] ?? 0);
    $userRole = strtoupper((string)($_SESSION['user']['role'] ?? 'USER'));
    $max = $userRole === 'ADMIN' ? MAX_UPLOAD_BYTES_ADMIN : MAX_UPLOAD_BYTES_USER;

    if ($size <= 0 || $size > $max) {
      throw new RuntimeException("File exceeds upload size policy");
    }
  }

  private static function ownerStorageBytes(int $ownerId, string $storageArea = 'ALL'): int {
    $storageArea = strtoupper(trim($storageArea));
    $roots = [];
    if ($storageArea === 'ALL' || $storageArea === 'PRIVATE') {
      $roots[] = self::storageRootForArea('PRIVATE') . DIRECTORY_SEPARATOR . $ownerId;
    }
    if ($storageArea === 'ALL' || $storageArea === 'OFFICIAL') {
      $roots[] = self::storageRootForArea('OFFICIAL') . DIRECTORY_SEPARATOR . $ownerId;
    }

    $bytes = 0;
    foreach ($roots as $dir) {
      if (!is_dir($dir)) {
        continue;
      }
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
      );
      foreach ($iterator as $item) {
        if ($item->isFile()) {
          $bytes += $item->getSize();
        }
      }
    }
    return $bytes;
  }

  private static function assertWithinQuota(int $ownerId, int $incomingBytes, string $storageArea = 'PRIVATE'): void {
    if ($incomingBytes <= 0) {
      return;
    }

    $summary = self::ownerStorageSummary($ownerId, $storageArea);
    if (($summary['used'] + $incomingBytes) > $summary['limit']) {
      throw new RuntimeException("User storage limit exceeded");
    }
  }

  private static function sanitizeFilename(string $name): string {
    $clean = preg_replace('/[^\w\-. ]+/', '_', $name);
    return trim((string)$clean) ?: 'file';
  }

  private static function buildStoredFilename(int $docId, int $version, string $originalName): string {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    return "doc{$docId}_v{$version}_" . time() . ($ext ? ".{$ext}" : "");
  }

  private static function absolutePathForOwner(int $ownerId, string $basename, string $storageArea = 'PRIVATE'): string {
    $dir = self::storageRootForArea($storageArea) . DIRECTORY_SEPARATOR . $ownerId;
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
      throw new RuntimeException("Failed to create storage directory");
    }

    return $dir . DIRECTORY_SEPARATOR . $basename;
  }

  private static function relativePath(int $ownerId, string $basename, string $storageArea = 'PRIVATE'): string {
    $segment = strtolower(Document::normalizeStorageArea($storageArea));
    return "../storage/documents/" . $segment . "/" . $ownerId . "/" . $basename;
  }

  private static function storageRootForArea(string $storageArea): string {
    $segment = strtolower(Document::normalizeStorageArea($storageArea));
    return rtrim(STORAGE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $segment;
  }
}
