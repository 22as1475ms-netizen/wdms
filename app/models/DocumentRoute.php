<?php
class DocumentRoute {
  public static function add(
    PDO $pdo,
    int $documentId,
    ?string $fromLocation,
    string $toLocation,
    string $statusSnapshot,
    ?string $note,
    int $routedBy
  ): int {
    $pdo->prepare("
      INSERT INTO document_routes(document_id, from_location, to_location, status_snapshot, note, routed_by)
      VALUES(?,?,?,?,?,?)
    ")->execute([
      $documentId,
      self::cleanLocation($fromLocation),
      self::cleanLocation($toLocation) ?? 'Unspecified',
      self::normalizeStatus($statusSnapshot),
      self::cleanNote($note),
      $routedBy,
    ]);

    return (int)$pdo->lastInsertId();
  }

  public static function listForDocument(PDO $pdo, int $documentId): array {
    $s = $pdo->prepare("
      SELECT r.*, u.name AS routed_by_name, u.email AS routed_by_email
      FROM document_routes r
      JOIN users u ON u.id = r.routed_by
      WHERE r.document_id = ?
      ORDER BY r.routed_at DESC, r.id DESC
    ");
    $s->execute([$documentId]);
    return $s->fetchAll();
  }

  public static function normalizeStatus(string $value): string {
    return strtoupper(trim($value)) === 'ROUTED' ? 'ROUTED' : 'NOT_ROUTED';
  }

  private static function cleanLocation(?string $value): ?string {
    $clean = trim((string)$value);
    if ($clean === '') {
      return null;
    }
    return mb_substr($clean, 0, 180);
  }

  private static function cleanNote(?string $value): ?string {
    $clean = trim((string)$value);
    if ($clean === '') {
      return null;
    }
    return mb_substr($clean, 0, 1000);
  }
}
