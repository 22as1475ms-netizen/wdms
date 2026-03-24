<?php
class User {
  public static function findByEmail(PDO $pdo, string $email): ?array {
    $s = $pdo->prepare("
      SELECT u.*, d.name AS division_name
      FROM users u
      LEFT JOIN divisions d ON d.id = u.division_id
      WHERE u.email=? LIMIT 1
    ");
    $s->execute([$email]);
    $u = $s->fetch();
    return $u ?: null;
  }

  public static function findById(PDO $pdo, int $id): ?array {
    $s = $pdo->prepare("
      SELECT u.*, d.name AS division_name
      FROM users u
      LEFT JOIN divisions d ON d.id = u.division_id
      WHERE u.id=? LIMIT 1
    ");
    $s->execute([$id]);
    $u = $s->fetch();
    return $u ?: null;
  }

  public static function all(PDO $pdo): array {
    return $pdo->query("
      SELECT u.id, u.name, u.email, u.role, u.status, u.division_id, u.created_at, u.avatar_photo, u.avatar_preset, d.name AS division_name
      FROM users u
      LEFT JOIN divisions d ON d.id = u.division_id
      ORDER BY u.id DESC
    ")->fetchAll();
  }

  public static function allNonAdmins(PDO $pdo): array {
    return $pdo->query("
      SELECT u.id, u.name, u.email, u.role, u.status, u.division_id, u.created_at, u.avatar_photo, u.avatar_preset, d.name AS division_name
      FROM users u
      LEFT JOIN divisions d ON d.id = u.division_id
      WHERE u.role <> 'ADMIN'
      ORDER BY u.id DESC
    ")->fetchAll();
  }

  public static function allEmployees(PDO $pdo): array {
    return $pdo->query("
      SELECT u.id, u.name, u.email, u.role, u.status, u.division_id, u.created_at, u.avatar_photo, u.avatar_preset, d.name AS division_name
      FROM users u
      LEFT JOIN divisions d ON d.id = u.division_id
      WHERE u.role='EMPLOYEE'
      ORDER BY u.name
    ")->fetchAll();
  }

  public static function listShareRecipients(PDO $pdo, ?int $excludeUserId = null): array {
    $sql = "
      SELECT
        u.id,
        u.name,
        u.email,
        u.role,
        u.status,
        u.division_id,
        u.created_at,
        u.avatar_photo,
        u.avatar_preset,
        d.name AS division_name,
        chief.id AS chief_user_id,
        chief.name AS chief_name,
        chief.email AS chief_email
      FROM users u
      LEFT JOIN divisions d ON d.id = u.division_id
      LEFT JOIN users chief ON chief.id = d.chief_user_id
      WHERE u.role = 'EMPLOYEE' AND u.status = 'ACTIVE'
    ";
    $params = [];
    if ($excludeUserId !== null && $excludeUserId > 0) {
      $sql .= " AND u.id <> ? ";
      $params[] = $excludeUserId;
    }
    $sql .= " ORDER BY COALESCE(d.name, 'ZZZ'), u.name ";

    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
  }

  public static function allDivisionChiefs(PDO $pdo): array {
    return $pdo->query("
      SELECT u.id, u.name, u.email, u.role, u.status, u.division_id, u.created_at, u.avatar_photo, u.avatar_preset, d.name AS division_name
      FROM users u
      LEFT JOIN divisions d ON d.id = u.division_id
      WHERE u.role='DIVISION_CHIEF'
      ORDER BY u.name
    ")->fetchAll();
  }

  public static function setStatus(PDO $pdo, int $id, string $status): void {
    $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([$status, $id]);
  }

  public static function setRole(PDO $pdo, int $id, string $role): void {
    $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $id]);
  }

  public static function setDivision(PDO $pdo, int $id, ?int $divisionId): void {
    $pdo->prepare("UPDATE users SET division_id=? WHERE id=?")->execute([$divisionId, $id]);
  }

  public static function search(PDO $pdo, string $q): array {
    $q = "%$q%";
    $s = $pdo->prepare("
      SELECT id, name, email, role, division_id
      FROM users
      WHERE status='ACTIVE' AND (name LIKE ? OR email LIKE ?)
      ORDER BY name
      LIMIT 20
    ");
    $s->execute([$q, $q]);
    return $s->fetchAll();
  }

  public static function countAll(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
  }

  public static function countActive(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='ACTIVE'")->fetchColumn();
  }

  public static function updatePassword(PDO $pdo, int $id, string $hash): void {
    $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $id]);
  }

  public static function updateName(PDO $pdo, int $id, string $name): void {
    $pdo->prepare("UPDATE users SET name=? WHERE id=?")->execute([$name, $id]);
  }

  public static function updateAvatar(PDO $pdo, int $id, ?string $photoPath, ?string $preset): void {
    $pdo->prepare("UPDATE users SET avatar_photo=?, avatar_preset=? WHERE id=?")
      ->execute([$photoPath, $preset, $id]);
  }

  public static function markOnboardingSeen(PDO $pdo, int $id, string $version): void {
    $pdo->prepare("UPDATE users SET onboarding_seen_at=NOW(), onboarding_guide_version=? WHERE id=?")
      ->execute([$version, $id]);
  }

  public static function emailExists(PDO $pdo, string $email): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email=?");
    $s->execute([$email]);
    return (int)$s->fetchColumn() > 0;
  }

  public static function create(PDO $pdo, string $name, string $email, string $role, string $status, string $passwordHash, ?int $divisionId = null): int {
    $pdo->prepare("INSERT INTO users(name,email,password,role,status,division_id) VALUES(?,?,?,?,?,?)")
      ->execute([$name, $email, $passwordHash, $role, $status, $divisionId]);
    return (int)$pdo->lastInsertId();
  }

  public static function countAdmins(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='ADMIN'")->fetchColumn();
  }

  public static function countActiveAdmins(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='ADMIN' AND status='ACTIVE'")->fetchColumn();
  }

  public static function deleteById(PDO $pdo, int $id): void {
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
  }

  public static function listEmployeesByDivision(PDO $pdo, int $divisionId): array {
    $s = $pdo->prepare("
      SELECT u.id, u.name, u.email, u.role, u.status, u.division_id, u.created_at, u.avatar_photo, u.avatar_preset, d.name AS division_name
      FROM users u
      LEFT JOIN divisions d ON d.id = u.division_id
      WHERE u.role='EMPLOYEE' AND u.division_id=?
      ORDER BY u.name
    ");
    $s->execute([$divisionId]);
    return $s->fetchAll();
  }
}
