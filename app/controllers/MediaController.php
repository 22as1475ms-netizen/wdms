<?php
require_once __DIR__ . "/../services/StorageService.php";

function serve_media_file(): void {
  global $pdo;

  if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    exit;
  }

  $key = trim((string)($_GET['k'] ?? ''));
  if ($key === '') {
    http_response_code(404);
    exit;
  }

  if (!StorageService::exists($pdo, $key)) {
    http_response_code(404);
    exit;
  }

  $download = req_str('download', '') === '1';
  $name = basename($key);
  $mime = 'application/octet-stream';
  StorageService::withReadablePath($pdo, $key, static function (string $path) use (&$mime): void {
    $detected = @mime_content_type($path);
    if (is_string($detected) && $detected !== '') {
      $mime = $detected;
    }
  });

  header('Content-Type: ' . $mime);
  header('Content-Length: ' . StorageService::size($pdo, $key));
  header('X-Content-Type-Options: nosniff');
  header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');

  if (!StorageService::output($pdo, $key)) {
    http_response_code(500);
  }
  exit;
}
