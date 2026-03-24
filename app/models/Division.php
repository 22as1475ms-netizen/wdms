<?php
class Division {
  public static function all(PDO $pdo): array {
    return $pdo->query("
      SELECT d.*, u.name AS chief_name, u.email AS chief_email
      FROM divisions d
      LEFT JOIN users u ON u.id = d.chief_user_id
      ORDER BY d.name
    ")->fetchAll();
  }

  public static function find(PDO $pdo, int $id): ?array {
    $s = $pdo->prepare("
      SELECT d.*, u.name AS chief_name, u.email AS chief_email
      FROM divisions d
      LEFT JOIN users u ON u.id = d.chief_user_id
      WHERE d.id=?
      LIMIT 1
    ");
    $s->execute([$id]);
    $row = $s->fetch();
    return $row ?: null;
  }

  public static function create(PDO $pdo, string $name, ?int $chiefUserId = null): int {
    $pdo->prepare("INSERT INTO divisions(name, chief_user_id) VALUES(?, ?)")
      ->execute([$name, $chiefUserId]);
    return (int)$pdo->lastInsertId();
  }

  public static function updateChief(PDO $pdo, int $id, ?int $chiefUserId): void {
    $pdo->prepare("UPDATE divisions SET chief_user_id=? WHERE id=?")->execute([$chiefUserId, $id]);
  }
}
