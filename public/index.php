<?php
$ROOT = dirname(__DIR__); // C:\xampp\htdocs\wdms

require $ROOT . "/app/config/app.php";
require $ROOT . "/app/config/database.php";
wdms_bootstrap_session();

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
error_reporting(APP_DEBUG ? E_ALL : 0);

require $ROOT . "/app/helpers/view.php";
require $ROOT . "/app/helpers/redirect.php";
require $ROOT . "/app/helpers/csrf.php";
require $ROOT . "/app/helpers/http.php";

function wdms_normalized_request_path(): string {
  $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
  if (BASE_URL !== '' && str_starts_with($path, BASE_URL)) {
    $path = substr($path, strlen(BASE_URL));
  }
  return $path === '' ? '/' : $path;
}

function wdms_web_routes(): array {
  return [
    '/' => ['controller' => 'AuthController.php', 'handler' => 'login'],
    '/login' => ['controller' => 'AuthController.php', 'handler' => 'login'],
    '/notifications/read' => ['middleware' => ['require_login.php'], 'controller' => 'NotificationController.php', 'handler' => 'notifications_mark_read'],
    '/notifications/clear' => ['middleware' => ['require_login.php'], 'controller' => 'NotificationController.php', 'handler' => 'notifications_clear_all'],
    '/dashboard' => ['middleware' => ['require_login.php'], 'handler' => 'wdms_dashboard_redirect'],
    '/account/password' => ['middleware' => ['require_login.php'], 'controller' => 'AccountController.php', 'handler' => 'account_password'],
    '/account/onboarding/complete' => ['middleware' => ['require_login.php'], 'controller' => 'AccountController.php', 'handler' => 'account_complete_onboarding'],
    '/media/file' => ['middleware' => ['require_login.php'], 'controller' => 'MediaController.php', 'handler' => 'serve_media_file'],
    '/documents' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'documents'],
    '/documents/upload' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'upload'],
    '/documents/create' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'create_document'],
    '/documents/view' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'view_doc'],
    '/documents/replace' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'replace_file'],
    '/documents/download' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'download_doc'],
    '/documents/file' => ['controller' => 'DocumentController.php', 'handler' => 'serve_doc_file'],
    '/documents/version/upload' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'upload_version'],
    '/documents/checkout' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'checkout_doc'],
    '/documents/checkin' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'checkin_doc'],
    '/documents/metadata' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'update_metadata'],
    '/documents/route' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'route_document'],
    '/documents/message/send' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'send_document_message'],
    '/documents/review/decision' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'review_document_decision'],
    '/documents/review/accept' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'accept_review_assignment'],
    '/documents/review/decline' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'decline_review_assignment'],
    '/documents/submit' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'submit_document_for_review'],
    '/documents/bulk' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'bulk_action'],
    '/documents/delete' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'soft_delete'],
    '/documents/move-to-official' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'move_doc_to_official'],
    '/documents/move-to-private' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'move_doc_to_private'],
    '/documents/manage-selected' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'manage_selected_documents'],
    '/documents/restore' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'restore_doc'],
    '/folders/restore' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'restore_folder'],
    '/documents/trash/empty' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'empty_trash'],
    '/documents/trash/delete-selected' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'delete_selected_trash'],
    '/documents/share' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'share_doc'],
    '/documents/share/respond' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'respond_to_share'],
    '/documents/revoke' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'revoke_share'],
    '/folders/create' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'create_folder'],
    '/folders/delete' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'delete_folder'],
    '/folders/move-to-official' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'move_folder_to_official'],
    '/folders/move-to-private' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'move_folder_to_private'],
    '/folders/rename' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'rename_folder'],
    '/folders/share' => ['middleware' => ['require_login.php'], 'controller' => 'DocumentController.php', 'handler' => 'share_folder'],
    '/admin/users' => ['middleware' => ['require_login.php'], 'controller' => 'AdminController.php', 'handler' => 'admin_users'],
    '/admin/users/export' => ['middleware' => ['require_login.php'], 'controller' => 'AdminController.php', 'handler' => 'admin_export_users'],
    '/admin/users/toggle' => ['middleware' => ['require_login.php'], 'controller' => 'AdminController.php', 'handler' => 'admin_toggle_user'],
    '/admin/users/create' => ['middleware' => ['require_login.php'], 'controller' => 'AdminController.php', 'handler' => 'admin_create_user'],
    '/admin/divisions/create' => ['middleware' => ['require_login.php'], 'controller' => 'AdminController.php', 'handler' => 'admin_create_division'],
    '/admin/users/delete' => ['middleware' => ['require_login.php'], 'controller' => 'AdminController.php', 'handler' => 'admin_delete_user'],
    '/admin/users/role' => ['middleware' => ['require_login.php'], 'controller' => 'AdminController.php', 'handler' => 'admin_change_role'],
    '/admin/users/password' => ['middleware' => ['require_login.php'], 'controller' => 'AdminController.php', 'handler' => 'admin_change_user_password'],
    '/admin/logs' => ['middleware' => ['require_login.php'], 'controller' => 'AdminController.php', 'handler' => 'admin_logs'],
    '/admin/logs/export' => ['middleware' => ['require_login.php'], 'controller' => 'AdminController.php', 'handler' => 'admin_export_logs'],
  ];
}

function wdms_require_route_files(string $root, array $route): void {
  foreach ($route['middleware'] ?? [] as $middleware) {
    require $root . "/app/middleware/" . $middleware;
  }

  if (!empty($route['controller'])) {
    require $root . "/app/controllers/" . $route['controller'];
  }
}

function wdms_dashboard_redirect(): void {
  redirect(workspace_home_path());
}

function wdms_logout(): void {
  global $pdo;

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
  } else {
    redirect('/');
  }
  if (!empty($_SESSION['user']['id'])) {
    require __DIR__ . "/../app/models/AuditLog.php";
    AuditLog::add($pdo, (int)$_SESSION['user']['id'], "Logged out", null, null);
  }
  session_destroy();
  redirect('/login');
}

function wdms_dispatch_web_route(string $root, string $path): bool {
  if ($path === '/logout') {
    wdms_logout();
    return true;
  }

  $routes = wdms_web_routes();
  if (!isset($routes[$path])) {
    return false;
  }

  $route = $routes[$path];
  wdms_require_route_files($root, $route);

  $handler = $route['handler'] ?? '';
  if ($handler === '' || !function_exists($handler)) {
    throw new RuntimeException('Route handler is not available for ' . $path);
  }

  $handler();
  return true;
}

$path = wdms_normalized_request_path();

if (str_starts_with($path, '/api/')) {
  require $ROOT . "/app/controllers/ApiController.php";
  api_dispatch($_SERVER['REQUEST_METHOD'], $path);
  exit;
}

if (!wdms_dispatch_web_route($ROOT, $path)) {
  http_response_code(404);
  echo "404 Not Found";
}
