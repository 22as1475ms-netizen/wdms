<?php
require_once __DIR__ . "/../helpers/http.php";
require_once __DIR__ . "/../models/User.php";
require_once __DIR__ . "/../models/Document.php";
require_once __DIR__ . "/../models/Permission.php";
require_once __DIR__ . "/../models/Version.php";
require_once __DIR__ . "/../models/AuditLog.php";
require_once __DIR__ . "/../models/Notification.php";
require_once __DIR__ . "/../models/ChatMessage.php";
require_once __DIR__ . "/../services/AuthService.php";
require_once __DIR__ . "/../services/AccessService.php";
require_once __DIR__ . "/../services/DocumentService.php";

function api_selected_owner_id(PDO $pdo, int $sessionUserId): int {
  $isAdmin = (($_SESSION['user']['role'] ?? '') === 'ADMIN');
  if (!$isAdmin) {
    return $sessionUserId;
  }

  $targetUserId = max(1, req_int('user_id', req_int('target_user_id', $sessionUserId)));
  return User::findById($pdo, $targetUserId) ? $targetUserId : $sessionUserId;
}

function api_can_manage_document(array $doc, int $uid): bool {
  return (($_SESSION['user']['role'] ?? '') === 'ADMIN') || (int)$doc['owner_id'] === $uid;
}

function api_chat_allowed(): bool {
  return strtoupper((string)($_SESSION['user']['role'] ?? '')) !== 'ADMIN';
}

function api_dispatch(string $method, string $path): bool {
  global $pdo;

  if ($path === '/api/auth/login' && $method === 'POST') {
    $data = api_input();
    $ok = AuthService::login($pdo, trim((string)($data['email'] ?? '')), (string)($data['password'] ?? ''));
    api_json($ok ? 200 : 401, $ok ? ['ok' => true, 'user' => api_user()] : ['ok' => false, 'message' => 'Invalid credentials']);
  }

  if ($path === '/api/auth/logout' && $method === 'POST') {
    if (!empty($_SESSION['user']['id'])) {
      AuditLog::add($pdo, (int)$_SESSION['user']['id'], "Logged out", null, null);
    }
    session_destroy();
    api_json(200, ['ok' => true]);
  }

  if ($path === '/api/auth/me' && $method === 'GET') {
    api_require_login();
    api_json(200, ['user' => api_user()]);
  }

  if ($path === '/api/notifications/unread' && $method === 'GET') {
    api_require_login();
    $uid = (int)$_SESSION['user']['id'];
    $items = Notification::recentAll($pdo, $uid, 8);
    $payloadItems = array_map(static function (array $row): array {
      return [
        'title' => (string)($row['title'] ?? ''),
        'body' => (string)($row['body'] ?? ''),
        'link' => Notification::resolveDestination($row),
        'is_read' => (int)($row['is_read'] ?? 0) === 1,
      ];
    }, $items);

    api_json(200, [
      'count' => Notification::unreadCount($pdo, $uid),
      'items' => $payloadItems,
    ]);
  }

  if ($path === '/api/chat/conversations' && $method === 'GET') {
    api_require_login();
    if (!api_chat_allowed()) {
      api_json(403, ['message' => 'Chat unavailable']);
    }
    $uid = (int)$_SESSION['user']['id'];
    $rows = ChatMessage::conversations($pdo, $uid);
    $items = array_map(static function (array $row): array {
      return [
        'peer_id' => (int)$row['peer_id'],
        'peer_name' => (string)($row['peer_name'] ?? ''),
        'peer_email' => (string)($row['peer_email'] ?? ''),
        'peer_avatar_photo' => (string)($row['peer_avatar_photo'] ?? ''),
        'peer_avatar_preset' => (string)($row['peer_avatar_preset'] ?? ''),
        'last_message' => (string)($row['last_message'] ?? ''),
        'last_created_at' => (string)($row['last_created_at'] ?? ''),
        'unread_count' => (int)($row['unread_count'] ?? 0),
      ];
    }, $rows);
    api_json(200, ['items' => $items, 'unread_total' => ChatMessage::unreadTotal($pdo, $uid)]);
  }

  if (preg_match('#^/api/chat/thread/(\d+)$#', $path, $m) && $method === 'GET') {
    api_require_login();
    if (!api_chat_allowed()) {
      api_json(403, ['message' => 'Chat unavailable']);
    }
    $uid = (int)$_SESSION['user']['id'];
    $peerId = (int)$m[1];
    if ($peerId <= 0 || $peerId === $uid) {
      api_json(422, ['message' => 'Invalid chat recipient']);
    }
    ChatMessage::markThreadRead($pdo, $uid, $peerId);
    $rows = ChatMessage::thread($pdo, $uid, $peerId, 100);
    $items = array_map(static function (array $row) use ($uid): array {
      $attachmentPath = trim((string)($row['attachment_path'] ?? ''));
      $attachmentMime = trim((string)($row['attachment_mime'] ?? ''));
      return [
        'id' => (int)$row['id'],
        'sender_id' => (int)$row['sender_id'],
        'recipient_id' => (int)$row['recipient_id'],
        'document_id' => (int)($row['document_id'] ?? 0),
        'message' => (string)($row['message'] ?? ''),
        'attachment_url' => $attachmentPath !== '' ? api_public_file_url($attachmentPath) : '',
        'attachment_name' => (string)($row['attachment_name'] ?? ''),
        'attachment_mime' => $attachmentMime,
        'attachment_is_image' => str_starts_with(strtolower($attachmentMime), 'image/'),
        'created_at' => (string)($row['created_at'] ?? ''),
        'is_mine' => (int)$row['sender_id'] === $uid,
      ];
    }, $rows);
    api_json(200, ['items' => $items]);
  }

  if (preg_match('#^/api/chat/thread/(\d+)$#', $path, $m) && $method === 'DELETE') {
    api_require_login();
    if (!api_chat_allowed()) {
      api_json(403, ['message' => 'Chat unavailable']);
    }
    $uid = (int)$_SESSION['user']['id'];
    $peerId = (int)$m[1];
    if ($peerId <= 0 || $peerId === $uid) {
      api_json(422, ['message' => 'Invalid chat recipient']);
    }
    $deleted = ChatMessage::deleteThreadForUser($pdo, $uid, $peerId);
    api_json(200, ['ok' => true, 'deleted' => $deleted, 'unread_total' => ChatMessage::unreadTotal($pdo, $uid)]);
  }

  if ($path === '/api/chat/send' && $method === 'POST') {
    api_require_login();
    if (!api_chat_allowed()) {
      api_json(403, ['message' => 'Chat unavailable']);
    }
    $uid = (int)$_SESSION['user']['id'];
    $data = api_input();
    $peerId = (int)($data['peer_id'] ?? 0);
    $peerEmail = trim((string)($data['peer_email'] ?? ''));
    $message = trim((string)($data['message'] ?? ''));
    $docId = (int)($data['document_id'] ?? 0);
    $attachment = api_handle_chat_attachment($_FILES['attachment'] ?? null);

    if ($peerId <= 0 && $peerEmail !== '') {
      $peer = User::findByEmail($pdo, $peerEmail);
      $peerId = $peer ? (int)$peer['id'] : 0;
    }
    if ($peerId <= 0 || $peerId === $uid) {
      api_json(422, ['message' => 'Invalid recipient']);
    }
    if ($message === '' && empty($attachment)) {
      api_json(422, ['message' => 'Message or attachment is required']);
    }

    $id = ChatMessage::send($pdo, $uid, $peerId, $message, max(0, $docId), $attachment);
    Notification::add($pdo, $peerId, "New chat message", (string)($_SESSION['user']['email'] ?? 'A user'), "chat://open");
    api_json(201, ['ok' => true, 'id' => $id]);
  }

  if ($path === '/api/documents' && $method === 'GET') {
    api_require_login();
    $uid = (int)$_SESSION['user']['id'];
    $tab = req_str('tab', 'my');
    $search = req_str('search', '');
    $folder = req_int('folder', 0);
    $page = max(1, req_int('page', 1));
    $per = max(1, min(50, req_int('per', 10)));
    $targetUserId = api_selected_owner_id($pdo, $uid);

    if ($tab === 'shared') {
      [$docs, $total] = Document::listShared($pdo, $uid, $search, $page, $per);
    } elseif ($tab === 'trash') {
      [$docs, $total] = Document::listMy($pdo, $targetUserId, $search, null, $page, $per, true);
    } else {
      [$docs, $total] = Document::listMy($pdo, $targetUserId, $search, $folder ?: null, $page, $per, false);
    }

    api_json(200, ['items' => $docs, 'total' => $total, 'page' => $page, 'per' => $per]);
  }

  if ($path === '/api/documents' && $method === 'POST') {
    api_require_login();
    $uid = (int)$_SESSION['user']['id'];
    $folderId = req_int('folder_id', 0) ?: null;
    $ownerId = api_selected_owner_id($pdo, $uid);

    if (!empty($_FILES['file'])) {
      $docId = DocumentService::upload($pdo, $_FILES['file'], $ownerId, $folderId, $uid);
      api_json(201, ['id' => $docId]);
    }

    api_json(422, ['message' => 'In-app file creation is disabled. Upload files instead.']);
  }

  if (preg_match('#^/api/documents/(\d+)$#', $path, $m)) {
    api_require_login();
    $docId = (int)$m[1];
    $uid = (int)$_SESSION['user']['id'];

    if ($method === 'GET') {
      api_require_view($pdo, $docId, $uid);
      $doc = Document::get($pdo, $docId);
      api_json($doc ? 200 : 404, $doc ? ['document' => $doc] : ['message' => 'Not found']);
    }

    if ($method === 'PUT') {
      api_require_edit($pdo, $docId, $uid);
      $data = api_input();
      $name = trim((string)($data['name'] ?? ''));
      if ($name === '') {
        api_json(422, ['message' => 'Document name is required']);
      }
      Document::rename($pdo, $docId, $name);
      AuditLog::add($pdo, $uid, "Renamed document", $docId, $name);
      api_json(200, ['ok' => true]);
    }

    if ($method === 'DELETE') {
      $doc = Document::get($pdo, $docId);
      if (!$doc || !api_can_manage_document($doc, $uid)) {
        api_json(403, ['message' => 'Owner access required']);
      }
      Document::softDelete($pdo, $docId);
      AuditLog::add($pdo, $uid, "Soft-deleted document", $docId, null);
      api_json(200, ['ok' => true]);
    }
  }

  if (preg_match('#^/api/documents/(\d+)/share$#', $path, $m) && $method === 'POST') {
    api_require_login();
    $docId = (int)$m[1];
    $uid = (int)$_SESSION['user']['id'];
    $doc = Document::get($pdo, $docId);
    if (!$doc || !api_can_manage_document($doc, $uid)) {
      api_json(403, ['message' => 'Owner access required']);
    }

    $data = api_input();
    $email = trim((string)($data['email'] ?? ''));
    $permission = trim((string)($data['permission'] ?? 'viewer'));
    if (!in_array($permission, ['viewer', 'editor'], true)) {
      $permission = 'viewer';
    }

    $target = User::findByEmail($pdo, $email);
    if (!$target) {
      api_json(404, ['message' => 'User not found']);
    }
    if (strtoupper((string)($doc['storage_area'] ?? 'PRIVATE')) !== 'OFFICIAL') {
      api_json(422, ['message' => 'Only official records can be shared']);
    }

    Permission::upsert($pdo, $docId, (int)$target['id'], $permission);
    AuditLog::add($pdo, $uid, "Shared document", $docId, "to=".$email.", perm=".$permission);
    api_json(200, ['ok' => true]);
  }

  if (preg_match('#^/api/documents/(\d+)/permissions$#', $path, $m) && $method === 'GET') {
    api_require_login();
    $docId = (int)$m[1];
    $uid = (int)$_SESSION['user']['id'];
    api_require_view($pdo, $docId, $uid);
    api_json(200, ['items' => Permission::listForDoc($pdo, $docId)]);
  }

  if (preg_match('#^/api/documents/(\d+)/versions$#', $path, $m) && $method === 'GET') {
    api_require_login();
    $docId = (int)$m[1];
    $uid = (int)$_SESSION['user']['id'];
    api_require_view($pdo, $docId, $uid);
    api_json(200, ['items' => Version::list($pdo, $docId)]);
  }

  api_json(404, ['message' => 'API route not found']);
}

function api_input(): array {
  $type = (string)($_SERVER['CONTENT_TYPE'] ?? '');
  if (str_contains($type, 'application/json')) {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
  }

  if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $raw = file_get_contents('php://input');
    parse_str($raw ?: '', $data);
    return is_array($data) ? $data : [];
  }

  return $_POST;
}

function api_require_login(): void {
  if (empty($_SESSION['user'])) {
    api_json(401, ['message' => 'Authentication required']);
  }

  if (($_SESSION['user']['status'] ?? 'ACTIVE') !== 'ACTIVE') {
    session_destroy();
    api_json(403, ['message' => 'Account disabled']);
  }
}

function api_require_view(PDO $pdo, int $docId, int $userId): void {
  if (!AccessService::level($pdo, $docId, $userId)) {
    api_json(403, ['message' => 'No access']);
  }
}

function api_require_edit(PDO $pdo, int $docId, int $userId): void {
  $level = AccessService::level($pdo, $docId, $userId);
  if (!in_array($level, ['owner', 'editor'], true)) {
    api_json(403, ['message' => 'No edit access']);
  }
}

function api_user(): array {
  if (empty($_SESSION['user'])) {
    return [];
  }

  $user = $_SESSION['user'];
  unset($user['password']);
  return $user;
}

function api_json(int $status, array $payload): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_SLASHES);
  exit;
}

function api_public_file_url(string $path): string {
  $clean = '/' . ltrim(str_replace('\\', '/', $path), '/');
  return BASE_URL . $clean;
}

function api_handle_chat_attachment(?array $file): array {
  if (!$file || empty($file['name'])) {
    return [];
  }

  $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($error === UPLOAD_ERR_NO_FILE) {
    return [];
  }
  if ($error !== UPLOAD_ERR_OK) {
    api_json(422, ['message' => 'Attachment upload failed']);
  }

  $tmpPath = (string)($file['tmp_name'] ?? '');
  if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    api_json(422, ['message' => 'Invalid attachment upload']);
  }

  $size = (int)($file['size'] ?? 0);
  if ($size <= 0 || $size > 10 * 1024 * 1024) {
    api_json(422, ['message' => 'Attachment must be less than 10MB']);
  }

  $originalName = trim((string)($file['name'] ?? ''));
  $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
  $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip', 'rar'];
  if (!in_array($ext, $allowedExt, true)) {
    api_json(422, ['message' => 'Unsupported attachment type']);
  }

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = $finfo ? (string)finfo_file($finfo, $tmpPath) : '';
  if ($finfo) {
    finfo_close($finfo);
  }
  if ($mime === '') {
    $mime = (string)($file['type'] ?? 'application/octet-stream');
  }

  $dir = __DIR__ . '/../../public/uploads/chat';
  if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    api_json(500, ['message' => 'Unable to store attachment']);
  }

  $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)pathinfo($originalName, PATHINFO_FILENAME));
  $safeBase = trim((string)$safeBase, '._-');
  if ($safeBase === '') {
    $safeBase = 'file';
  }
  $filename = $safeBase . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
  $targetPath = $dir . '/' . $filename;
  if (!move_uploaded_file($tmpPath, $targetPath)) {
    api_json(500, ['message' => 'Unable to save attachment']);
  }

  return [
    'path' => '/uploads/chat/' . $filename,
    'name' => $originalName,
    'mime' => $mime,
  ];
}
