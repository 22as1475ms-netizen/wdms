<?php
require_once __DIR__ . '/TestCase.php';

class DocumentReviewWorkflowTest extends TestCase {
  public function testSubmitForReviewUpdatesDocumentAndCreatesQueueArtifacts(): void {
    $owner = $this->actingAs(3);
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Policy Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
      'status' => 'Draft',
    ]);
    $doc = Document::get($this->pdo, $docId);

    $result = DocumentReviewService::submitForReview($this->pdo, $doc, (int)$owner['id']);

    $updatedDoc = Document::get($this->pdo, $docId);
    $routes = DocumentRoute::listForDocument($this->pdo, $docId);
    $notifications = Notification::recentAll($this->pdo, 2, 5);
    $auditLogs = AuditLog::recentForUser($this->pdo, (int)$owner['id'], 5);

    $this->assertSame($docId, (int)$result['document_id']);
    $this->assertSame('To be reviewed', (string)$updatedDoc['status']);
    $this->assertSame('PENDING_REVIEW_ACCEPTANCE', (string)$updatedDoc['routing_status']);
    $this->assertSame('PENDING', (string)$updatedDoc['review_acceptance_status']);
    $this->assertCount(1, $routes);
    $this->assertSame('Routed file awaiting review', (string)$notifications[0]['title']);
    $this->assertSame('Submitted routed file for review', (string)$auditLogs[0]['action']);
  }

  public function testSubmitForReviewRequiresAssignedDivisionChief(): void {
    $owner = $this->actingAs(3);
    $this->pdo->exec('UPDATE divisions SET chief_user_id = NULL WHERE id = 1');
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Policy Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
      'status' => 'Draft',
    ]);

    $this->expectExceptionMessage('division_chief_required', function () use ($docId, $owner): void {
      DocumentReviewService::submitForReview($this->pdo, Document::get($this->pdo, $docId), (int)$owner['id']);
    });
  }

  public function testSubmitForReviewRejectsNonOwnerWithoutAdminRole(): void {
    $owner = $this->actingAs(3);
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Policy Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
      'status' => 'Draft',
    ]);
    $this->actingAs(2);

    $this->expectExceptionMessage('forbidden', function () use ($docId): void {
      DocumentReviewService::submitForReview($this->pdo, Document::get($this->pdo, $docId), 2);
    });
  }

  public function testSubmitForReviewRejectsLockedDocument(): void {
    $owner = $this->actingAs(3);
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Policy Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
      'status' => 'Draft',
    ]);
    $this->pdo->prepare('UPDATE documents SET approval_locked = 1 WHERE id = ?')->execute([$docId]);

    $this->expectExceptionMessage('approval_locked', function () use ($docId, $owner): void {
      DocumentReviewService::submitForReview($this->pdo, Document::get($this->pdo, $docId), (int)$owner['id']);
    });
  }

  public function testFinalizeReviewApprovalLocksDocumentAndLogsDecision(): void {
    $owner = $this->actingAs(3);
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Policy Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
      'status' => 'Draft',
    ]);
    DocumentReviewService::submitForReview($this->pdo, Document::get($this->pdo, $docId), (int)$owner['id']);
    Document::acceptReviewAssignment($this->pdo, $docId);
    $this->actingAs(2);

    $result = DocumentReviewService::finalizeDecision($this->pdo, Document::get($this->pdo, $docId), 2, 'APPROVED');

    $updatedDoc = Document::get($this->pdo, $docId);
    $reviews = DocumentReview::listForDocument($this->pdo, $docId);
    $notifications = Notification::recentAll($this->pdo, 3, 5);

    $this->assertSame('APPROVED', (string)$result['decision']);
    $this->assertSame('Approved', (string)$updatedDoc['status']);
    $this->assertSame('APPROVED', (string)$updatedDoc['routing_status']);
    $this->assertSame(1, (int)$updatedDoc['approval_locked']);
    $this->assertCount(1, $reviews);
    $this->assertSame('Routed file approved', (string)$notifications[0]['title']);
  }

  public function testFinalizeReviewRejectRequiresReason(): void {
    $owner = $this->actingAs(3);
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Policy Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
      'status' => 'Draft',
    ]);
    DocumentReviewService::submitForReview($this->pdo, Document::get($this->pdo, $docId), (int)$owner['id']);
    Document::acceptReviewAssignment($this->pdo, $docId);
    $this->actingAs(2);

    $this->expectExceptionMessage('reject_note_required', function () use ($docId): void {
      DocumentReviewService::finalizeDecision($this->pdo, Document::get($this->pdo, $docId), 2, 'REJECTED', '');
    });
  }

  public function testFinalizeReviewRejectsInvalidDecision(): void {
    $owner = $this->actingAs(3);
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Policy Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
      'status' => 'Draft',
    ]);
    DocumentReviewService::submitForReview($this->pdo, Document::get($this->pdo, $docId), (int)$owner['id']);
    Document::acceptReviewAssignment($this->pdo, $docId);
    $this->actingAs(2);

    $this->expectExceptionMessage('decision_invalid', function () use ($docId): void {
      DocumentReviewService::finalizeDecision($this->pdo, Document::get($this->pdo, $docId), 2, 'MAYBE');
    });
  }
}
