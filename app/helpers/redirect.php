<?php
function redirect(string $path): void {
  header("Location: " . wdms_base_url_path($path));
  exit;
}

function workspace_home_path(): string {
  $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));
  if ($role === 'ADMIN') {
    return '/admin/users';
  }
  if ($role === 'DIVISION_CHIEF') {
    return '/documents?tab=division_queue';
  }
  return '/documents?tab=routed';
}
