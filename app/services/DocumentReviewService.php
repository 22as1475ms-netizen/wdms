<?php
require_once __DIR__ . "/../models/Document.php";
require_once __DIR__ . "/../models/Division.php";
require_once __DIR__ . "/../models/DocumentRoute.php";
require_once __DIR__ . "/../models/DocumentReview.php";
require_once __DIR__ . "/../models/Permission.php";
require_once __DIR__ . "/../models/Notification.php";
require_once __DIR__ . "/../models/AuditLog.php";

class DocumentReviewService {
  public static function submitForReview(PDO $pdo, array $doc, int $actorId): array {
    $docId = (int)($doc['id'] ?? 0);
    if ($docId <= 0) {
      throw new RuntimeException('not_found');
    }
    if ((int)($doc['owner_id'] ?? 0) !== $actorId && strtoupper((string)($_SESSION['user']['role'] ?? '')) !== 'ADMIN') {
      throw new RuntimeException('forbidden');
    }
    if (!self::canSubmit($doc)) {
      throw new RuntimeException(((int)($doc['approval_locked'] ?? 0) === 1) ? 'approval_locked' : 'decision_already_final');
    }

    $divisionId = (int)($doc['division_id'] ?? 0);
    if ($divisionId <= 0) {
      throw new RuntimeException('division_required');
    }

    $division = Division::find($pdo, $divisionId);
    if (!$division || (int)($division['chief_user_id'] ?? 0) <= 0) {
      throw new RuntimeException('division_chief_required');
    }

    Document::submitForReview($pdo, $docId, $divisionId);
    Document::updateTrackingState($pdo, $docId, 'Section Chief Review Queue', 'PENDING_REVIEW_ACCEPTANCE');
    Document::markRouteActive($pdo, $docId);
    DocumentRoute::add(
      $pdo,
      $docId,
      (string)($doc['current_location'] ?? ''),
      'Section Chief Review Queue',
      'PENDING_REVIEW_ACCEPTANCE',
      self::documentRouteNote('submit', $doc),
      $actorId
    );
    Notification::add($pdo, (int)$division['chief_user_id'], 'Routed file awaiting review', (string)($doc['name'] ?? ''), '/documents/view?id=' . $docId);
    AuditLog::add($pdo, $actorId, 'Submitted routed file for review', $docId, 'division_id=' . $divisionId);

    return ['document_id' => $docId, 'division_id' => $divisionId, 'chief_user_id' => (int)$division['chief_user_id']];
  }

  public static function finalizeDecision(PDO $pdo, array $doc, int $actorId, string $decision, ?string $note = null): array {
    $docId = (int)($doc['id'] ?? 0);
    if ($docId <= 0) {
      throw new RuntimeException('not_found');
    }

    $decision = strtoupper(trim($decision));
    if (!in_array($decision, ['APPROVED', 'REJECTED'], true)) {
      throw new RuntimeException('decision_invalid');
    }

    $cleanNote = trim((string)$note);
    if ($decision === 'REJECTED' && $cleanNote === '') {
      throw new RuntimeException('reject_note_required');
    }

    $storedNote = $cleanNote !== '' ? mb_substr($cleanNote, 0, 1000) : null;
    Document::finalizeReview($pdo, $docId, $decision, $storedNote, $actorId);
    $ownerFinalLocation = trim((string)($doc['owner_name'] ?? 'Original uploader'));
    $nextLocation = $decision === 'APPROVED' ? $ownerFinalLocation : 'Returned to Owner';
    $nextRouteStatus = $decision === 'APPROVED' ? 'APPROVED' : 'REJECTED';
    Document::updateTrackingState($pdo, $docId, $nextLocation, $nextRouteStatus);
    Document::closeRoute($pdo, $docId, $decision === 'APPROVED' ? 'APPROVED' : 'REJECTED');

    foreach (Permission::listForDoc($pdo, $docId) as $member) {
      $memberUserId = (int)($member['user_id'] ?? 0);
      if ($memberUserId > 0) {
        Permission::revoke($pdo, $docId, $memberUserId);
      }
    }

    DocumentRoute::add(
      $pdo,
      $docId,
      (string)($doc['current_location'] ?? 'Section Chief Review Queue'),
      $nextLocation,
      $nextRouteStatus,
      $storedNote ?: ($decision === 'APPROVED'
        ? 'Section chief approved the routed file and it was automatically returned to the original uploader as the final holder.'
        : self::documentRouteNote('reject', $doc)),
      $actorId
    );
    DocumentReview::add($pdo, $docId, $actorId, $decision, $storedNote);
    Notification::add(
      $pdo,
      (int)($doc['owner_id'] ?? 0),
      $decision === 'APPROVED' ? 'Routed file approved' : 'Routed file rejected',
      $storedNote ?: ($decision === 'APPROVED'
        ? 'Approved by the section chief and automatically returned to you.'
        : (string)($doc['name'] ?? '')),
      '/documents/view?id=' . $docId
    );
    AuditLog::add($pdo, $actorId, $decision === 'APPROVED' ? 'Approved routed file' : 'Rejected routed file', $docId, $storedNote);

    return ['document_id' => $docId, 'decision' => $decision];
  }

  private static function canSubmit(array $doc): bool {
    $status = strtolower(trim((string)($doc['status'] ?? 'Draft')));
    if (in_array($status, ['approved', 'to be reviewed'], true)) {
      return false;
    }

    return (int)($doc['approval_locked'] ?? 0) !== 1;
  }

  private static function documentRouteNote(string $action, array $tracking): string {
    $title = trim((string)($tracking['title'] ?? ''));
    $label = $title !== '' ? $title : (string)($tracking['name'] ?? '');
    return match ($action) {
      'submit' => 'Document submitted for section chief review.',
      'reject' => 'Document review rejected.',
      default => 'Document route updated: ' . $label,
    };
  }
}
