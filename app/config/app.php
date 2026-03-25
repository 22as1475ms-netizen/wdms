<?php

function wdms_env_string(string $key, string $default = ''): string {
  $value = getenv($key);
  return $value === false ? $default : trim((string)$value);
}

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

function wdms_normalize_base_url(string $path): string {
  $path = trim(str_replace('\\', '/', $path));
  if ($path === '' || $path === '/') {
    return '';
  }

  $path = '/' . trim($path, '/');
  return $path === '/.' ? '' : $path;
}

function wdms_detect_base_url(): string {
  $envBase = wdms_env_string('APP_BASE_PATH', '');
  if ($envBase !== '') {
    return wdms_normalize_base_url($envBase);
  }

  $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
  if ($scriptName === '') {
    return '';
  }

  $basePath = dirname($scriptName);
  if ($basePath === '.' || $basePath === DIRECTORY_SEPARATOR) {
    return '';
  }

  return wdms_normalize_base_url($basePath);
}

function wdms_base_url_path(string $path = ''): string {
  $path = trim($path);
  if ($path === '') {
    return BASE_URL !== '' ? BASE_URL : '/';
  }

  if ($path[0] !== '/') {
    $path = '/' . $path;
  }

  return (BASE_URL !== '' ? BASE_URL : '') . $path;
}

function wdms_storage_dir(): string {
  $configured = wdms_env_string('STORAGE_DIR', '');
  if ($configured !== '') {
    return rtrim($configured, "\\/");
  }

  return rtrim(__DIR__ . '/../../storage/documents', "\\/");
}

function wdms_public_path(string $path = ''): string {
  $root = realpath(__DIR__ . '/../../public') ?: (__DIR__ . '/../../public');
  if ($path === '') {
    return $root;
  }

  return rtrim($root, "\\/") . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function wdms_public_upload_dir(string $segment): string {
  return wdms_public_path('uploads/' . trim($segment, "\\/"));
}

function wdms_app_secret(): string {
  $envSecret = wdms_env_string('APP_SECRET', '');
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

  if (!is_dir($secretDir)) {
    if (!@mkdir($secretDir, 0700, true) && !is_dir($secretDir)) {
      throw new RuntimeException('APP_SECRET is not configured and local secret storage is unavailable.');
    }
  }

  $generated = bin2hex(random_bytes(32));
  if (file_put_contents($secretFile, $generated, LOCK_EX) === false) {
    throw new RuntimeException('APP_SECRET is not configured and could not be persisted locally.');
  }
  @chmod($secretFile, 0600);
  return $generated;
}

function wdms_bootstrap_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) {
    return;
  }
  
  $isSecure = false;
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $isSecure = strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
  } elseif (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
    $isSecure = true;
  }

  session_name(wdms_env_string('SESSION_NAME', 'WDMSSESSID'));
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => BASE_URL !== '' ? BASE_URL . '/' : '/',
    'domain' => wdms_env_string('SESSION_DOMAIN', ''),
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => wdms_env_string('SESSION_SAMESITE', 'Lax'),
  ]);

  session_start();
}

define('BASE_URL', wdms_detect_base_url());
define('STORAGE_DIR', wdms_storage_dir());
define('APP_NAME', 'WDMS');
define('APP_DEBUG', wdms_env_bool('APP_DEBUG', false));
define('APP_URL', rtrim(wdms_env_string('APP_URL', ''), '/'));
define('APP_SECRET', wdms_app_secret());
define('PRIVATE_STORAGE_LIMIT_BYTES', 5 * 1024 * 1024 * 1024);
define('OFFICIAL_STORAGE_LIMIT_BYTES', 5 * 1024 * 1024 * 1024);
define('SESSION_TIMEOUT_MINUTES', wdms_env_int('SESSION_TIMEOUT_MINUTES', 45, 5));
define('TRASH_RETENTION_DAYS', wdms_env_int('TRASH_RETENTION_DAYS', 0, 0));
define('MAX_UPLOAD_BYTES_USER', wdms_env_int('MAX_UPLOAD_BYTES_USER', 1024 * 1024 * 1024, 1));
define('MAX_UPLOAD_BYTES_ADMIN', wdms_env_int('MAX_UPLOAD_BYTES_ADMIN', 1024 * 1024 * 1024, 1));
