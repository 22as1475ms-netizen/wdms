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
  wdms_ensure_base_schema($pdo);

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
  wdms_add_column_if_missing($pdo, 'documents', 'routing_status', "VARCHAR(40) NOT NULL DEFAULT 'AVAILABLE'");
  wdms_add_column_if_missing($pdo, 'documents', 'priority_level', "VARCHAR(20) NOT NULL DEFAULT 'NORMAL'");
  wdms_add_column_if_missing($pdo, 'documents', 'document_date', "DATE NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'review_acceptance_status', "VARCHAR(30) NOT NULL DEFAULT 'NOT_SENT'");
  wdms_add_column_if_missing($pdo, 'documents', 'review_accepted_at', "TIMESTAMP NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'review_declined_at', "TIMESTAMP NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'review_acceptance_note', "VARCHAR(1000) NULL");
  wdms_add_column_if_missing($pdo, 'documents', 'route_outcome', "VARCHAR(20) NOT NULL DEFAULT 'ACTIVE'");
  wdms_add_column_if_missing($pdo, 'documents', 'route_closed_at', "TIMESTAMP NULL");
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
    CREATE TABLE IF NOT EXISTS sessions (
      id VARCHAR(128) PRIMARY KEY,
      payload MEDIUMTEXT NOT NULL,
      last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      expires_at DATETIME NOT NULL,
      INDEX(expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS stored_files (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      storage_key VARCHAR(255) NOT NULL,
      kind VARCHAR(40) NOT NULL DEFAULT 'generic',
      visibility VARCHAR(20) NOT NULL DEFAULT 'private',
      original_name VARCHAR(255) NULL,
      mime_type VARCHAR(120) NULL,
      size_bytes BIGINT NOT NULL DEFAULT 0,
      content LONGBLOB NOT NULL,
      created_by INT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_storage_key (storage_key),
      INDEX(kind),
      INDEX(visibility),
      INDEX(created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS document_routes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      document_id INT NOT NULL,
      from_location VARCHAR(180) NULL,
      to_location VARCHAR(180) NOT NULL,
      status_snapshot VARCHAR(40) NOT NULL DEFAULT 'AVAILABLE',
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

  wdms_ensure_varchar_length($pdo, 'documents', 'routing_status', 40, false, 'AVAILABLE');
  wdms_ensure_varchar_length($pdo, 'document_routes', 'status_snapshot', 40, false, 'AVAILABLE');

  if (wdms_env_bool('DB_AUTO_UNIFY_ROUTED_STORAGE', false)) {
    wdms_unify_routed_storage($pdo);
  }
}

function wdms_ensure_base_schema(PDO $pdo): void {
  if (wdms_table_exists($pdo, 'users')
    && wdms_table_exists($pdo, 'folders')
    && wdms_table_exists($pdo, 'documents')
    && wdms_table_exists($pdo, 'document_versions')
    && wdms_table_exists($pdo, 'permissions')
    && wdms_table_exists($pdo, 'audit_logs')) {
    return;
  }

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS users(
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(100) NOT NULL,
      email VARCHAR(120) UNIQUE NOT NULL,
      password VARCHAR(255) NOT NULL,
      role ENUM('ADMIN','DIVISION_CHIEF','EMPLOYEE') DEFAULT 'EMPLOYEE',
      status ENUM('ACTIVE','DISABLED') DEFAULT 'ACTIVE',
      division_id INT NULL,
      onboarding_seen_at TIMESTAMP NULL,
      onboarding_guide_version VARCHAR(40) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS divisions(
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(120) NOT NULL,
      chief_user_id INT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS folders(
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(150) NOT NULL,
      owner_id INT NOT NULL,
      storage_area ENUM('PRIVATE','OFFICIAL') NOT NULL DEFAULT 'PRIVATE',
      deleted_at TIMESTAMP NULL,
      deleted_by INT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX(owner_id),
      INDEX(deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS documents(
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(255) NOT NULL,
      owner_id INT NOT NULL,
      folder_id INT NULL,
      storage_area ENUM('PRIVATE','OFFICIAL') DEFAULT 'PRIVATE',
      division_id INT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'Draft',
      review_note VARCHAR(1000) NULL,
      approval_locked TINYINT(1) NOT NULL DEFAULT 0,
      submitted_at TIMESTAMP NULL,
      reviewed_at TIMESTAMP NULL,
      reviewed_by INT NULL,
      deleted_at TIMESTAMP NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX(owner_id),
      INDEX(folder_id),
      INDEX(deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS document_reviews(
      id INT AUTO_INCREMENT PRIMARY KEY,
      document_id INT NOT NULL,
      reviewer_id INT NOT NULL,
      decision VARCHAR(20) NOT NULL,
      note VARCHAR(1000) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS document_versions(
      id INT AUTO_INCREMENT PRIMARY KEY,
      document_id INT NOT NULL,
      file_path TEXT NOT NULL,
      version_number INT NOT NULL,
      created_by INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX(document_id),
      INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS permissions(
      id INT AUTO_INCREMENT PRIMARY KEY,
      document_id INT NOT NULL,
      user_id INT NOT NULL,
      permission ENUM('viewer','editor') NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_doc_user (document_id, user_id),
      INDEX(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS audit_logs(
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      action VARCHAR(255) NOT NULL,
      document_id INT NULL,
      meta TEXT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX(user_id),
      INDEX(created_at),
      INDEX(document_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");

  wdms_seed_base_data($pdo);
}

function wdms_seed_base_data(PDO $pdo): void {
  $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
  if ($userCount === 0) {
    $stmt = $pdo->prepare("
      INSERT INTO users(name, email, password, role, status)
      VALUES(?, ?, ?, 'ADMIN', 'ACTIVE')
    ");
    $stmt->execute([
      'Administrator',
      'admin@wdms.com',
      '$2y$10$go0YfIGnYoPXyzNG0okjQe1frdg8Y./hkVa0Dl6EO8pNEDmkvsFRK',
    ]);
  }

  $divisionCount = (int)$pdo->query("SELECT COUNT(*) FROM divisions")->fetchColumn();
  if ($divisionCount === 0) {
    $stmt = $pdo->prepare("INSERT INTO divisions(name, chief_user_id) VALUES(?, NULL)");
    $stmt->execute(['Records Division']);
  }
}

function wdms_table_exists(PDO $pdo, string $table): bool {
  $s = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
  ");
  $s->execute([$table]);
  return (int)$s->fetchColumn() > 0;
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
  if (!wdms_table_exists($pdo, $table)) {
    return;
  }

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

function wdms_ensure_varchar_length(PDO $pdo, string $table, string $column, int $minLength, bool $nullable, ?string $default = null): void {
  if (!wdms_table_exists($pdo, $table)) {
    return;
  }

  $sql = "
    SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND COLUMN_NAME = ?
    LIMIT 1
  ";
  $s = $pdo->prepare($sql);
  $s->execute([$table, $column]);
  $columnInfo = $s->fetch();
  if (!$columnInfo) {
    return;
  }

  $dataType = strtolower((string)($columnInfo['DATA_TYPE'] ?? ''));
  $currentLength = (int)($columnInfo['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
  if ($dataType === 'varchar' && $currentLength >= $minLength) {
    return;
  }

  $nullSql = $nullable ? 'NULL' : 'NOT NULL';
  $defaultSql = $default !== null ? " DEFAULT '" . str_replace("'", "''", $default) . "'" : '';
  $pdo->exec("ALTER TABLE {$table} MODIFY COLUMN {$column} VARCHAR({$minLength}) {$nullSql}{$defaultSql}");
}
