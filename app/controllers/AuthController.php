<?php
require_once __DIR__ . "/../services/AuthService.php";
require_once __DIR__ . "/../helpers/csrf.php";

function login(): void {
  global $pdo;
  csrf_verify();
  $error = null;
  $errCode = req_str('err', '');
  if ($errCode !== '') {
    $error = ui_message($errCode);
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    if (AuthService::login($pdo, $email, $pass)) {
      redirect(workspace_home_path());
    }
    $error = "Invalid credentials or disabled account.";
  }

  view('auth/login', ['error' => $error]);
}
