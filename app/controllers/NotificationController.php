<?php
require_once __DIR__ . "/../helpers/csrf.php";
require_once __DIR__ . "/../models/Notification.php";

function notifications_redirect_back(): void {
  $fallback = '/documents';
  $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
  if ($ref === '') {
    redirect($fallback);
  }

  $parts = parse_url($ref);
  $path = (string)($parts['path'] ?? '');
  if ($path === '' || !str_starts_with($path, BASE_URL)) {
    redirect($fallback);
  }

  $target = substr($path, strlen(BASE_URL));
  if ($target === '') {
    $target = '/';
  }
  $query = (string)($parts['query'] ?? '');
  if ($query !== '') {
    $target .= '?' . $query;
  }
  redirect($target);
}

function notifications_is_ajax(): bool {
  $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
  $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
  return str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest';
}

function notifications_json_ok(array $extra = []): void {
  http_response_code(200);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(array_merge(['ok' => true], $extra), JSON_UNESCAPED_SLASHES);
  exit;
}

function notifications_mark_read(): void {
  global $pdo;
  csrf_verify();
  $uid = (int)($_SESSION['user']['id'] ?? 0);
  if ($uid > 0) {
    Notification::markAllRead($pdo, $uid);
  }
  if (notifications_is_ajax()) {
    notifications_json_ok();
  }
  notifications_redirect_back();
}

function notifications_clear_all(): void {
  global $pdo;
  csrf_verify();
  $uid = (int)($_SESSION['user']['id'] ?? 0);
  if ($uid > 0) {
    Notification::clearAll($pdo, $uid);
  }
  if (notifications_is_ajax()) {
    notifications_json_ok();
  }
  notifications_redirect_back();
}
