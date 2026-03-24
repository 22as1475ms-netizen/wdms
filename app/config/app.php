<?php

function wdms_env_bool(string $key, bool $default): bool {
  $value = getenv($key);
  if ($value === false) {
    return $default;
  }

  return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function wdms_env_int(string $key, int $default, int $min = PHP_INT_MIN): int {
  $value = getenv($key);
  $intValue = $value === false ? $default : (int)$value;
  return max($min, $intValue);
}

function wdms_app_secret(): string {
  $envSecret = trim((string)(getenv('APP_SECRET') ?: ''));
  if ($envSecret !== '') {
    return $envSecret;
  }

  $secretDir = __DIR__ . '/../../storage/secrets';
  $secretFile = $secretDir . '/app_secret';

  if (is_file($secretFile)) {
    $stored = trim((string)(file_get_contents($secretFile) ?: ''));
    if ($stored !== '') {
      return $stored;
    }
  }

  if (!is_dir($secretDir) && !mkdir($secretDir, 0700, true) && !is_dir($secretDir)) {
    throw new RuntimeException('Unable to create storage secrets directory.');
  }

  $generated = bin2hex(random_bytes(32));
  if (file_put_contents($secretFile, $generated, LOCK_EX) === false) {
    throw new RuntimeException('Unable to persist application secret.');
  }
  @chmod($secretFile, 0600);
  return $generated;
}

define('BASE_URL', '/wdms/public');
define('STORAGE_DIR', __DIR__ . '/../../storage/documents');
define('APP_NAME', 'WDMS');
define('APP_DEBUG', wdms_env_bool('APP_DEBUG', false));
define('APP_URL', rtrim((string)(getenv('APP_URL') ?: ''), '/'));
define('APP_SECRET', wdms_app_secret());
define('PRIVATE_STORAGE_LIMIT_BYTES', 5 * 1024 * 1024 * 1024);
define('OFFICIAL_STORAGE_LIMIT_BYTES', 5 * 1024 * 1024 * 1024);
define('SESSION_TIMEOUT_MINUTES', wdms_env_int('SESSION_TIMEOUT_MINUTES', 45, 5));
define('TRASH_RETENTION_DAYS', wdms_env_int('TRASH_RETENTION_DAYS', 0, 0));
define('MAX_UPLOAD_BYTES_USER', wdms_env_int('MAX_UPLOAD_BYTES_USER', 1024 * 1024 * 1024, 1));
define('MAX_UPLOAD_BYTES_ADMIN', wdms_env_int('MAX_UPLOAD_BYTES_ADMIN', 1024 * 1024 * 1024, 1));
