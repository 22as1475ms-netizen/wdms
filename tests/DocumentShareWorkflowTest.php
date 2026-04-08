<?php
require_once __DIR__ . '/TestCase.php';

class DocumentShareWorkflowTest extends TestCase {
  public function testShareDocumentCreatesPermissionRouteNotificationAndAuditLog(): void {
    $owner = $this->actingAs(3);
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Test Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
    ]);
    $doc = Document::get($this->pdo, $docId);
    $target = User::findById($this->pdo, 2);

    $result = DocumentShareService::shareDocument($this->pdo, $doc, (int)$owner['id'], $target, 'editor');

    $permission = Permission::findRowForUser($this->pdo, $docId, 2);
    $updatedDoc = Document::get($this->pdo, $docId);
    $routes = DocumentRoute::listForDocument($this->pdo, $docId);
    $notifications = Notification::recentAll($this->pdo, 2, 5);
    $auditLogs = AuditLog::recentForUser($this->pdo, (int)$owner['id'], 5);

    $this->assertSame(2, (int)$result['target_id']);
    $this->assertSame('editor', (string)$permission['permission']);
    $this->assertSame('PENDING_SHARE_ACCEPTANCE', (string)$updatedDoc['routing_status']);
    $this->assertCount(1, $routes);
    $this->assertSame('A routed file was shared with you', (string)$notifications[0]['title']);
    $this->assertSame('Shared document', (string)$auditLogs[0]['action']);
  }

  public function testShareDocumentRejectsSelfTarget(): void {
    $owner = $this->actingAs(3);
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Test Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
    ]);

    $this->expectExceptionMessage('cannot_share_self', function () use ($docId, $owner): void {
      DocumentShareService::shareDocument($this->pdo, Document::get($this->pdo, $docId), (int)$owner['id'], User::findById($this->pdo, 3), 'viewer');
    });
  }

  public function testShareDocumentRejectsDifferentDivisionTarget(): void {
    $owner = $this->actingAs(3);
    $this->pdo->prepare("INSERT INTO divisions(name, chief_user_id) VALUES(?, ?)")->execute(['Other Division', null]);
    $otherDivisionId = (int)$this->pdo->lastInsertId();
    $otherUserId = User::create($this->pdo, 'Other Employee', 'other@wdms.test', 'EMPLOYEE', 'ACTIVE', password_hash('password', PASSWORD_BCRYPT), $otherDivisionId);
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Test Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
    ]);

    $this->expectExceptionMessage('user_not_found', function () use ($docId, $owner, $otherUserId): void {
      DocumentShareService::shareDocument($this->pdo, Document::get($this->pdo, $docId), (int)$owner['id'], User::findById($this->pdo, $otherUserId), 'viewer');
    });
  }

  public function testShareDocumentRejectsWhenShareAlreadyInProgress(): void {
    $owner = $this->actingAs(3);
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Test Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
    ]);
    $target = User::findById($this->pdo, 2);
    DocumentShareService::shareDocument($this->pdo, Document::get($this->pdo, $docId), (int)$owner['id'], $target, 'viewer');

    $this->expectExceptionMessage('share_in_progress', function () use ($docId, $owner, $target): void {
      DocumentShareService::shareDocument($this->pdo, Document::get($this->pdo, $docId), (int)$owner['id'], $target, 'viewer');
    });
  }

  public function testAcceptShareMarksPermissionAcceptedAndNotifiesOwner(): void {
    $owner = $this->actingAs(3);
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Test Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
    ]);
    $doc = Document::get($this->pdo, $docId);
    $target = User::findById($this->pdo, 2);
    DocumentShareService::shareDocument($this->pdo, $doc, (int)$owner['id'], $target, 'viewer');

    $this->actingAs(2);
    $permissionRow = Permission::findRowForUser($this->pdo, $docId, 2);
    $result = DocumentShareService::respondToShare($this->pdo, Document::get($this->pdo, $docId), $permissionRow, 2, 'ACCEPT');

    $updatedPermission = Permission::findRowForUser($this->pdo, $docId, 2);
    $updatedDoc = Document::get($this->pdo, $docId);
    $notifications = Notification::recentAll($this->pdo, 3, 5);

    $this->assertSame('accepted', (string)$result['status']);
    $this->assertNotNull($updatedPermission['accepted_at'] ?? null);
    $this->assertSame('SHARE_ACCEPTED', (string)$updatedDoc['routing_status']);
    $this->assertSame('Shared document accepted', (string)$notifications[0]['title']);
  }

  public function testDeclineShareRequiresNote(): void {
    $owner = $this->actingAs(3);
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Test Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
    ]);
    $doc = Document::get($this->pdo, $docId);
    $target = User::findById($this->pdo, 2);
    DocumentShareService::shareDocument($this->pdo, $doc, (int)$owner['id'], $target, 'viewer');

    $this->actingAs(2);
    $permissionRow = Permission::findRowForUser($this->pdo, $docId, 2);

    $this->expectExceptionMessage('response_note_required', function () use ($docId, $permissionRow): void {
      DocumentShareService::respondToShare($this->pdo, Document::get($this->pdo, $docId), $permissionRow, 2, 'DECLINE', '');
    });
  }

  public function testRevokeShareRemovesMembersAndReturnsDocumentToOwner(): void {
    $owner = $this->actingAs(3);
    $docId = Document::create($this->pdo, (int)$owner['id'], null, 'memo.docx', 'OFFICIAL', (int)$owner['division_id'], [
      'title' => 'Test Memo',
      'current_location' => 'Owner Desk',
      'routing_status' => 'AVAILABLE',
    ]);
    $doc = Document::get($this->pdo, $docId);
    $target = User::findById($this->pdo, 2);
    DocumentShareService::shareDocument($this->pdo, $doc, (int)$owner['id'], $target, 'viewer');

    $result = DocumentShareService::revokeShare($this->pdo, Document::get($this->pdo, $docId), (int)$owner['id'], (string)$owner['name']);

    $permission = Permission::findRowForUser($this->pdo, $docId, 2);
    $updatedDoc = Document::get($this->pdo, $docId);
    $routes = DocumentRoute::listForDocument($this->pdo, $docId);

    $this->assertSame(1, (int)$result['revoked_members']);
    $this->assertSame(null, $permission);
    $this->assertSame('AVAILABLE', (string)$updatedDoc['routing_status']);
    $this->assertCount(2, $routes);
    $this->assertStringContains('Share cancelled by owner', (string)$routes[0]['note']);
  }
}
