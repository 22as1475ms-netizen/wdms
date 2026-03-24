<?php
if (!isset($_SESSION['user'])) {
  header("Location: ".BASE_URL."/login");
  exit;
}
if (($_SESSION['user']['status'] ?? 'ACTIVE') !== 'ACTIVE') {
  session_destroy();
  die("Account disabled.");
}

$now = time();
$timeout = SESSION_TIMEOUT_MINUTES * 60;
$lastActivity = (int)($_SESSION['_last_activity'] ?? $now);
if ($timeout > 0 && ($now - $lastActivity) > $timeout) {
  session_destroy();
  header("Location: ".BASE_URL."/login?err=session_expired");
  exit;
}
$_SESSION['_last_activity'] = $now;
