<?php
if (!isset($_SESSION['user'])) {
  redirect('/login');
}
if (($_SESSION['user']['status'] ?? 'ACTIVE') !== 'ACTIVE') {
  session_destroy();
  redirect('/login?err=account_disabled');
}

$now = time();
$timeout = SESSION_TIMEOUT_MINUTES * 60;
$lastActivity = (int)($_SESSION['_last_activity'] ?? $now);
if ($timeout > 0 && ($now - $lastActivity) > $timeout) {
  session_destroy();
  redirect('/login?err=session_expired');
}
$_SESSION['_last_activity'] = $now;
