<?php
function csrf_token(): string {
  if (!isset($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['_csrf'];
}

function csrf_field(): string {
  $t = htmlspecialchars(csrf_token());
  return '<input type="hidden" name="_csrf" value="'.$t.'">';
}

function csrf_request_token(): string {
  if (isset($_POST['_csrf'])) {
    return trim((string)$_POST['_csrf']);
  }

  if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    return trim((string)$_SERVER['HTTP_X_CSRF_TOKEN']);
  }

  if (isset($_SERVER['HTTP_X_XSRF_TOKEN'])) {
    return trim((string)$_SERVER['HTTP_X_XSRF_TOKEN']);
  }

  return '';
}

function csrf_verify(?array $methods = null): void {
  $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
  $allowedMethods = $methods ?? ['POST'];
  $normalizedMethods = array_map(static fn(string $value): string => strtoupper(trim($value)), $allowedMethods);
  if (!in_array($method, $normalizedMethods, true)) {
    return;
  }

  $token = csrf_request_token();
  $ok = $token !== ''
    && isset($_SESSION['_csrf'])
    && hash_equals((string)$_SESSION['_csrf'], $token);
  if (!$ok) {
    http_response_code(419);
    die("419 CSRF token mismatch");
  }
}
