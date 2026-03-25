<?php
$dbHost = wdms_env_string('DB_HOST', '127.0.0.1');
$dbPort = wdms_env_int('DB_PORT', 3306, 1);
$dbName = wdms_env_string('DB_NAME', 'wdms');
$dbUser = wdms_env_string('DB_USER', 'root');
$dbPass = wdms_env_string('DB_PASS', '');
$dbCharset = wdms_env_string('DB_CHARSET', 'utf8mb4');

$pdo = new PDO(
  "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}",
  $dbUser,
  $dbPass,
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]
);

if (wdms_env_bool('DB_AUTO_BOOTSTRAP_SCHEMA', true)) {
  wdms_bootstrap_schema($pdo);
}

function wdms_bootstrap_schema(PDO $pdo): void {
  wdms_add_column_if_missing($pdo, 'folders', 'deleted_at', "TIMESTAMP NULL");
  wdms_add_column_if_missing($pdo, 'folders', 'deleted_by', "INT NULL");
  wdms_add_column_if_missing($pdo, 'folders', 'storage_area', "VARCHAR(20) NOT NULL DEFAULT 'PRIVATE'");
  wdms_add_column_if_missing($pdo, 'documents', 'checked_out_by', "INT NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'checked_out_at', "TIMESTAMP NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'tags', "VARCHAR(255) NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'category', "VARCHAR(100) NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'status', "VARCHAR(20) NOT NULL DEFAULT 'Draft'");
  wdms_add_column_if_missing($pdo, 'documents', 'retention_until', "DATE NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'deleted_by', "INT NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'deleted_reason', "VARCHAR(255) NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'approval_locked', "TINYINT(1) NOT NULL DEFAULT 0");
  wdms_add_column_if_missing($pdo, 'documents', 'review_note', "VARCHAR(1000) NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'storage_area', "VARCHAR(20) NOT NULL DEFAULT 'PRIVATE'");
  wdms_add_column_if_missing($pdo, 'documents', 'division_id', "INT NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'submitted_at', "TIMESTAMP NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'reviewed_at', "TIMESTAMP NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'reviewed_by', "INT NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'document_code', "VARCHAR(80) NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'title', "VARCHAR(255) NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'document_type', "VARCHAR(20) NOT NULL DEFAULT 'INCOMING'");
  wdms_add_column_if_missing($pdo, 'documents', 'signatory', "VARCHAR(150) NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'current_location', "VARCHAR(180) NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'routing_status', "VARCHAR(20) NOT NULL DEFAULT 'NOT_ROUTED'");
  wdms_add_column_if_missing($pdo, 'documents', 'priority_level', "VARCHAR(20) NOT NULL DEFAULT 'NORMAL'");
  wdms_add_column_if_missing($pdo, 'documents', 'document_date', "DATE NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'review_acceptance_status', "VARCHAR(30) NOT NULL DEFAULT 'NOT_SENT'");
  wdms_add_column_if_missing($pdo, 'documents', 'review_accepted_at', "TIMESTAMP NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'review_declined_at', "TIMESTAMP NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'review_acceptance_note', "VARCHAR(1000) NULL");
  wdms_add_column_if_missing($pdo, 'users', 'avatar_photo', "VARCHAR(255) NULL");
  wdms_add_column_if_missing($pdo, 'users', 'avatar_preset', "VARCHAR(32) NULL");
  wdms_add_column_if_missing($pdo, 'users', 'division_id', "INT NULL");
  wdms_add_column_if_missing($pdo, 'users', 'onboarding_seen_at', "TIMESTAMP NULL");
  wdms_add_column_if_missing($pdo, 'users', 'onboarding_guide_version', "VARCHAR(40) NULL");

  wdms_normalize_legacy_roles($pdo);

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS divisions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(120) NOT NULL,
      chief_user_id INT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_division_name (name),
      INDEX(chief_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS document_reviews (
      id INT AUTO_INCREMENT PRIMARY KEY,
      document_id INT NOT NULL,
      reviewer_id INT NOT NULL,
      decision VARCHAR(20) NOT NULL,
      note VARCHAR(1000) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX(document_id),
      INDEX(reviewer_id),
      INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS notifications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      title VARCHAR(180) NOT NULL,
      body VARCHAR(255) NULL,
      link VARCHAR(255) NULL,
      is_read TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX(user_id),
      INDEX(is_read),
      INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS document_messages (
      id INT AUTO_INCREMENT PRIMARY KEY,
      document_id INT NOT NULL,
      sender_id INT NOT NULL,
      recipient_id INT NOT NULL,
      message TEXT NOT NULL,
      attachment_path VARCHAR(255) NULL,
      attachment_name VARCHAR(255) NULL,
      attachment_mime VARCHAR(100) NULL,
      is_read TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX(document_id),
      INDEX(sender_id),
      INDEX(recipient_id),
      INDEX(is_read),
      INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS document_routes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      document_id INT NOT NULL,
      from_location VARCHAR(180) NULL,
      to_location VARCHAR(180) NOT NULL,
      status_snapshot VARCHAR(20) NOT NULL DEFAULT 'NOT_ROUTED',
      note VARCHAR(1000) NULL,
      routed_by INT NOT NULL,
      routed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX(document_id),
      INDEX(routed_by),
      INDEX(routed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  wdms_add_column_if_missing($pdo, 'document_messages', 'is_read', "TINYINT(1) NOT NULL DEFAULT 0");
  wdms_add_column_if_missing($pdo, 'document_messages', 'attachment_path', "VARCHAR(255) NULL");
  wdms_add_column_if_missing($pdo, 'document_messages', 'attachment_name', "VARCHAR(255) NULL");
  wdms_add_column_if_missing($pdo, 'document_messages', 'attachment_mime', "VARCHAR(100) NULL");
  wdms_add_column_if_missing($pdo, 'document_messages', 'deleted_by_sender_at', "TIMESTAMP NULL");
  wdms_add_column_if_missing($pdo, 'document_messages', 'deleted_by_recipient_at', "TIMESTAMP NULL");
  wdms_add_column_if_missing($pdo, 'permissions', 'shared_by', "INT NULL");
  wdms_add_column_if_missing($pdo, 'permissions', 'accepted_at', "TIMESTAMP NULL");
  wdms_add_column_if_missing($pdo, 'permissions', 'declined_at', "TIMESTAMP NULL");
  wdms_add_column_if_missing($pdo, 'permissions', 'response_note', "VARCHAR(1000) NULL");

  wdms_unify_routed_storage($pdo);
}

function wdms_unify_routed_storage(PDO $pdo): void {
  // Transitional workflow: keep one routed storage area underneath the app.
  $pdo->exec("UPDATE folders SET storage_area='OFFICIAL' WHERE storage_area <> 'OFFICIAL'");
  $pdo->exec("UPDATE documents SET storage_area='OFFICIAL' WHERE storage_area <> 'OFFICIAL'");
}

function wdms_normalize_legacy_roles(PDO $pdo): void {
  $s = $pdo->query("SELECT id, role FROM users");
  foreach ($s->fetchAll() as $row) {
    $role = strtoupper((string)($row['role'] ?? ''));
    $normalized = match ($role) {
      'ADMIN', 'DIVISION_CHIEF', 'EMPLOYEE' => $role,
      'USER' => 'EMPLOYEE',
      default => 'EMPLOYEE',
    };
    if ($normalized !== $role) {
      $u = $pdo->prepare("UPDATE users SET role=? WHERE id=?");
      $u->execute([$normalized, (int)$row['id']]);
    }
  }
}

function wdms_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void {
  $sql = "
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND COLUMN_NAME = ?
  ";
  $s = $pdo->prepare($sql);
  $s->execute([$table, $column]);
  if ((int)$s->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
  }
}
