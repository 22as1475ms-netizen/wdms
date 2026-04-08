<?php

function share_doc(): void {
  global $pdo;
  csrf_verify();

  $uid = (int)$_SESSION['user']['id'];
  $docId = req_int('document_id', 0);
  $doc = Document::get($pdo, $docId);
  if (!$doc) { redirect('/documents?err=not_found'); }
  $targetUserId = req_int('target_user_id', 0);
  $targetEmail = req_str('target_email', '');
  $target = $targetUserId > 0 ? User::findById($pdo, $targetUserId) : User::findByEmail($pdo, $targetEmail);
  if (!$target) {
    redirect('/documents/view?id='.$docId.'&err=user_not_found');
  }

  try {
    DocumentShareService::shareDocument($pdo, $doc, $uid, $target, req_str('permission', 'viewer'));
  } catch (RuntimeException $e) {
    $error = $e->getMessage();
    if ($error === 'forbidden') {
      http_response_code(403);
      die("403 current holder only");
    }
    $separator = str_contains('/documents/view?id=' . $docId, '?') ? '&' : '?';
    redirect('/documents/view?id='.$docId . $separator . 'err=' . urlencode($error) . '&user_id='.(int)$doc['owner_id']);
  }

  redirect('/documents?tab=shared&msg=shared&user_id='.(int)$doc['owner_id']);
}

function share_folder(): void {
  global $pdo;
  csrf_verify();

  $uid = (int)($_SESSION['user']['id'] ?? 0);
  $folderId = req_int('folder_id', 0);
  $ownerId = selected_owner_id($pdo, $uid);
  $folder = Folder::getForUser($pdo, $folderId, $ownerId, 'OFFICIAL');
  if (!$folder) {
    redirect('/documents?tab=routed&err=folder_not_found' . documents_context_query_suffix(null, $ownerId));
  }

  $targetUserId = req_int('target_user_id', 0);
  $target = $targetUserId > 0 ? User::findById($pdo, $targetUserId) : null;
  if (!$target || !in_array(strtoupper((string)($target['role'] ?? '')), ['EMPLOYEE', 'DIVISION_CHIEF'], true)) {
    redirect('/documents?tab=routed&folder=' . $folderId . '&err=user_not_found' . documents_context_query_suffix($folderId, $ownerId));
  }
  if ((int)$target['id'] === $uid) {
    redirect('/documents?tab=routed&folder=' . $folderId . '&err=cannot_share_self' . documents_context_query_suffix($folderId, $ownerId));
  }

  $owner = User::findById($pdo, $ownerId);
  $divisionId = (int)($owner['division_id'] ?? 0);
  if ($divisionId > 0 && (int)($target['division_id'] ?? 0) !== $divisionId) {
    redirect('/documents?tab=routed&folder=' . $folderId . '&err=user_not_found' . documents_context_query_suffix($folderId, $ownerId));
  }

  $tree = Folder::listTreeForUser($pdo, $ownerId, (string)$folder['name'], 'OFFICIAL');
  $folderIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $tree)));
  if (empty($folderIds)) {
    $folderIds = [$folderId];
  }

  $docs = array_values(array_filter(Document::listActiveForOwnerInStorage($pdo, $ownerId, 'OFFICIAL'), static function (array $doc) use ($folderIds): bool {
    return in_array((int)($doc['folder_id'] ?? 0), $folderIds, true);
  }));
  if (empty($docs)) {
    redirect('/documents?tab=routed&folder=' . $folderId . '&err=not_found' . documents_context_query_suffix($folderId, $ownerId));
  }

  foreach ($docs as $doc) {
    if (!can_manage_document($doc, $uid)) {
      http_response_code(403);
      die("403 owner only");
    }
    if (in_array(strtoupper((string)($doc['routing_status'] ?? 'AVAILABLE')), ['PENDING_SHARE_ACCEPTANCE', 'SHARE_ACCEPTED', 'PENDING_REVIEW_ACCEPTANCE', 'IN_REVIEW'], true)) {
      redirect('/documents?tab=routed&folder=' . $folderId . '&err=share_in_progress' . documents_context_query_suffix($folderId, $ownerId));
    }
  }

  $perm = req_str('permission', 'viewer');
  if (!in_array($perm, ['viewer', 'editor'], true)) $perm = 'viewer';
  $division = (int)($target['division_id'] ?? 0) > 0 ? Division::find($pdo, (int)$target['division_id']) : null;

  foreach ($docs as $doc) {
    $docId = (int)($doc['id'] ?? 0);
    foreach (Permission::listForDoc($pdo, $docId) as $member) {
      Permission::revoke($pdo, $docId, (int)($member['user_id'] ?? 0));
    }
    Permission::upsert($pdo, $docId, (int)$target['id'], $perm, $uid);
    Document::updateTrackingState($pdo, $docId, 'Awaiting recipient acceptance', 'PENDING_SHARE_ACCEPTANCE');
    Document::markRouteActive($pdo, $docId);
    DocumentRoute::add(
      $pdo,
      $docId,
      (string)($doc['current_location'] ?? ''),
      'Awaiting recipient acceptance',
      'PENDING_SHARE_ACCEPTANCE',
      document_share_route_note($target, $division) . ' Folder: ' . Folder::basename((string)$folder['name']),
      $uid
    );
  }

  Notification::add($pdo, (int)$target['id'], "A routed folder was shared with you", Folder::basename((string)$folder['name']), "/documents?tab=shared");
  AuditLog::add($pdo, $uid, "Shared folder", null, "folder_id=" . $folderId . ", to=" . (string)($target['email'] ?? '') . ", docs=" . count($docs));
  redirect('/documents?tab=shared&msg=shared&user_id=' . $ownerId);
}

function respond_to_share(): void {
  global $pdo;
  csrf_verify();

  $uid = (int)($_SESSION['user']['id'] ?? 0);
  $docId = req_int('document_id', 0);
  $decision = strtoupper(req_str('decision', ''));
  $note = trim(req_str('response_note', ''));
  $doc = Document::get($pdo, $docId);
  if (!$doc) {
    redirect('/documents?err=not_found');
  }

  $permissionRow = Permission::findRowForUser($pdo, $docId, $uid);
  if (!$permissionRow) {
    http_response_code(403);
    die("403 share recipient only");
  }

  try {
    $result = DocumentShareService::respondToShare($pdo, $doc, $permissionRow, $uid, $decision, $note);
  } catch (RuntimeException $e) {
    $error = $e->getMessage();
    redirect('/documents/view?id='.$docId.'&err=' . urlencode($error));
  }

  if (($result['status'] ?? '') === 'accepted') {
    redirect('/documents/view?id='.$docId.'&msg=share_accepted');
  }
  redirect('/documents?tab=shared&msg=share_declined');
}

function revoke_share(): void {
  global $pdo;
  csrf_verify();

  $uid = (int)$_SESSION['user']['id'];
  $docId = req_int('document_id', 0);
  $doc = Document::get($pdo, $docId);
  if (!$doc) { redirect('/documents?err=not_found'); }
  if (!can_manage_document($doc, $uid)) { http_response_code(403); die("403 owner only"); }
  try {
    DocumentShareService::revokeShare($pdo, $doc, $uid, (string)($_SESSION['user']['name'] ?? ($doc['owner_name'] ?? 'Owner')));
  } catch (RuntimeException $e) {
    redirect('/documents?tab=shared&err=' . urlencode($e->getMessage()) . '&user_id='.(int)$doc['owner_id']);
  }
  redirect('/documents?tab=shared&msg=share_cancelled&user_id='.(int)$doc['owner_id']);
}
