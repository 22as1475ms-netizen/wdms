<?php
require_once __DIR__ . "/../models/Document.php";
require_once __DIR__ . "/../models/Permission.php";

class AccessService {
  // returns accepted or pending access states
  public static function level(PDO $pdo, int $docId, int $userId): ?string {
    $doc = Document::get($pdo, $docId);
    if (!$doc) return null;
    $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));
    if ($role === 'ADMIN') return 'admin';
    if ((int)$doc['owner_id'] === $userId) return 'owner';
    if (
      $role === 'DIVISION_CHIEF'
      && strtoupper((string)($doc['storage_area'] ?? 'PRIVATE')) === 'OFFICIAL'
      && (int)($doc['division_id'] ?? 0) > 0
      && (int)($doc['division_id'] ?? 0) === (int)($_SESSION['user']['division_id'] ?? 0)
    ) {
      $reviewAcceptance = strtoupper((string)($doc['review_acceptance_status'] ?? 'NOT_SENT'));
      if ($reviewAcceptance === 'ACCEPTED' || in_array((string)($doc['status'] ?? ''), ['Approved', 'Rejected'], true)) {
        return 'division_chief';
      }
      if ($reviewAcceptance === 'PENDING') {
        return 'division_chief_pending';
      }
      if ($reviewAcceptance === 'DECLINED') {
        return 'division_chief_declined';
      }
      return null;
    }

    $permission = Permission::findRowForUser($pdo, $docId, $userId);
    if (!$permission) {
      return null;
    }
    $perm = (string)($permission['permission'] ?? '');
    if ($perm === '') {
      return null;
    }
    if (!empty($permission['accepted_at'])) {
      return $perm;
    }
    if (!empty($permission['declined_at'])) {
      return $perm . '_declined';
    }
    return $perm . '_pending';
  }

  public static function requireView(PDO $pdo, int $docId, int $userId): void {
    $lvl = self::level($pdo, $docId, $userId);
    if (!in_array($lvl, ['admin','owner','editor','viewer','division_chief'], true)) { http_response_code(403); die("403 No access"); }
  }

  public static function requireEdit(PDO $pdo, int $docId, int $userId): void {
    $lvl = self::level($pdo, $docId, $userId);
    if (!in_array($lvl, ['admin','owner','editor'], true)) { http_response_code(403); die("403 No edit access"); }
  }
}
