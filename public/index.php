<?php
session_start();

$ROOT = dirname(__DIR__); // C:\xampp\htdocs\wdms

require $ROOT . "/app/config/app.php";
require $ROOT . "/app/config/database.php";

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
error_reporting(APP_DEBUG ? E_ALL : 0);

require $ROOT . "/app/helpers/view.php";
require $ROOT . "/app/helpers/redirect.php";
require $ROOT . "/app/helpers/csrf.php";
require $ROOT . "/app/helpers/http.php";

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace(BASE_URL, '', $path);
if ($path === '') $path = '/';

if (str_starts_with($path, '/api/')) {
  require $ROOT . "/app/controllers/ApiController.php";
  api_dispatch($_SERVER['REQUEST_METHOD'], $path);
}

switch ($path) {

  // auth
  case '/':
  case '/login':
    require $ROOT . "/app/controllers/AuthController.php";
    login();
    break;

  case '/logout':
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_verify();
    } else {
      redirect('/');
    }
    if (!empty($_SESSION['user']['id'])) {
      require $ROOT . "/app/models/AuditLog.php";
      AuditLog::add($pdo, (int)$_SESSION['user']['id'], "Logged out", null, null);
    }
    session_destroy();
    redirect('/login');
    break;

  case '/notifications/read':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/NotificationController.php";
    notifications_mark_read();
    break;

  case '/notifications/clear':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/NotificationController.php";
    notifications_clear_all();
    break;

  // dashboard
  case '/dashboard':
    require $ROOT . "/app/middleware/require_login.php";
    redirect(workspace_home_path());
    break;

  case '/account/password':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/AccountController.php";
    account_password();
    break;

  case '/account/onboarding/complete':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/AccountController.php";
    account_complete_onboarding();
    break;

  // documents
  case '/documents':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    documents();
    break;

  case '/documents/upload':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    upload();
    break;

  case '/documents/create':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    create_document();
    break;

  case '/documents/view':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    view_doc();
    break;

  case '/documents/replace':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    replace_file();
    break;

  case '/documents/download':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    download_doc();
    break;

  case '/documents/file':
    require $ROOT . "/app/controllers/DocumentController.php";
    serve_doc_file();
    break;

  case '/documents/version/upload':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    upload_version();
    break;

  case '/documents/checkout':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    checkout_doc();
    break;

  case '/documents/checkin':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    checkin_doc();
    break;

  case '/documents/metadata':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    update_metadata();
    break;

  case '/documents/route':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    route_document();
    break;

  case '/documents/message/send':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    send_document_message();
    break;

  case '/documents/review/decision':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    review_document_decision();
    break;

  case '/documents/review/accept':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    accept_review_assignment();
    break;

  case '/documents/review/decline':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    decline_review_assignment();
    break;

  case '/documents/submit':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    submit_document_for_review();
    break;

  case '/documents/bulk':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    bulk_action();
    break;

  case '/documents/delete':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    soft_delete();
    break;

  case '/documents/move-to-official':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    move_doc_to_official();
    break;

  case '/documents/move-to-private':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    move_doc_to_private();
    break;

  case '/documents/manage-selected':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    manage_selected_documents();
    break;

  case '/documents/restore':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    restore_doc();
    break;

  case '/documents/trash/empty':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    empty_trash();
    break;

  case '/documents/trash/delete-selected':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    delete_selected_trash();
    break;

  case '/documents/share':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    share_doc();
    break;

  case '/documents/share/respond':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    respond_to_share();
    break;

  case '/documents/revoke':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    revoke_share();
    break;

  // folders
  case '/folders/create':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    create_folder();
    break;

  case '/folders/delete':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    delete_folder();
    break;

  case '/folders/move-to-official':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    move_folder_to_official();
    break;

  case '/folders/move-to-private':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    move_folder_to_private();
    break;

  case '/folders/rename':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/DocumentController.php";
    rename_folder();
    break;

  // admin
  case '/admin/users':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/AdminController.php";
    admin_users();
    break;

  case '/admin/users/export':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/AdminController.php";
    admin_export_users();
    break;

  case '/admin/users/toggle':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/AdminController.php";
    admin_toggle_user();
    break;

  case '/admin/users/create':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/AdminController.php";
    admin_create_user();
    break;

  case '/admin/divisions/create':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/AdminController.php";
    admin_create_division();
    break;

  case '/admin/users/delete':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/AdminController.php";
    admin_delete_user();
    break;

  case '/admin/users/role':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/AdminController.php";
    admin_change_role();
    break;

  case '/admin/users/password':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/AdminController.php";
    admin_change_user_password();
    break;

  case '/admin/logs':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/AdminController.php";
    admin_logs();
    break;

  case '/admin/logs/export':
    require $ROOT . "/app/middleware/require_login.php";
    require $ROOT . "/app/controllers/AdminController.php";
    admin_export_logs();
    break;

  default:
    http_response_code(404);
    echo "404 Not Found";
}
