<?php
require_once __DIR__ . "/../models/AuditLog.php";
require_once __DIR__ . "/../models/Document.php";
require_once __DIR__ . "/../models/Folder.php";
require_once __DIR__ . "/../models/Notification.php";
require_once __DIR__ . "/../models/User.php";
require_once __DIR__ . "/../services/DocumentService.php";

function dashboard(): void {
  global $pdo;

  if (empty($_SESSION['user'])) {
    redirect('/login');
    return;
  }

  $user = $_SESSION['user'];

  if (($user['role'] ?? '') === 'ADMIN') {
    $users = User::all($pdo);
    $activityByUser = [];
    foreach ($users as $account) {
      $rows = AuditLog::recentForUser($pdo, (int)$account['id'], 6);
      if (!empty($rows)) {
        $activityByUser[] = [
          'user' => $account,
          'logs' => $rows,
        ];
      }
    }

    view('dashboard/admin', [
      'activityByUser' => $activityByUser,
      'activitySummary' => AuditLog::summaryLastDays($pdo, 14),
      'storage' => DocumentService::storageStats($pdo),
      'userCounts' => [
        'total' => User::countAll($pdo),
        'active' => User::countActive($pdo),
      ],
    ]);
    return;
  }

  $uid = (int)($user['id'] ?? 0);
  $folders = Folder::listForUser($pdo, $uid);
  [$recentDocs, $activeTotal] = Document::listMy($pdo, $uid, '', null, 1, 5, false);
  [, $sharedTotal] = Document::listShared($pdo, $uid, '', 1, 1);
  [, $trashTotal] = Document::listMy($pdo, $uid, '', null, 1, 1, true);

  view('dashboard/user', [
    'storage' => DocumentService::ownerStorageSummary($uid),
    'folderCount' => count($folders),
    'activeTotal' => $activeTotal,
    'sharedTotal' => $sharedTotal,
    'trashTotal' => $trashTotal,
    'recentDocs' => $recentDocs,
    'recentActivity' => AuditLog::recentForUser($pdo, $uid, 5),
    'unreadNotifications' => Notification::unreadCount($pdo, $uid),
    'recentNotifications' => Notification::recentUnread($pdo, $uid, 4),
  ]);
}
