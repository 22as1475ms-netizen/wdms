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

function csrf_verify(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = isset($_POST['_csrf'], $_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $_POST['_csrf']);
    if (!$ok) {
      http_response_code(419);
      die("419 CSRF token mismatch");
    }
  }
}