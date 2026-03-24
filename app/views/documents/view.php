<?php require __DIR__ . "/../layouts/header.php"; ?>
<?php require_once __DIR__ . "/../../helpers/csrf.php"; ?>
<?php require_once __DIR__ . "/../../helpers/http.php"; ?>
<?php
$currentUserId = (int)($_SESSION['user']['id'] ?? 0);
$role = strtoupper((string)($_SESSION['user']['role'] ?? 'EMPLOYEE'));
$isOwner = (int)$doc['owner_id'] === $currentUserId;
$isReviewer = in_array($level, ['admin', 'division_chief'], true);
$canEditFile = in_array($level, ['admin', 'owner', 'editor'], true) && !(int)($doc['approval_locked'] ?? 0);
$isPendingSharedRecipient = in_array($level, ['viewer_pending', 'editor_pending'], true);
$isDeclinedSharedRecipient = in_array($level, ['viewer_declined', 'editor_declined'], true);
$isPendingReviewer = $level === 'division_chief_pending';
$isDeclinedReviewer = $level === 'division_chief_declined';
$isOfficial = strtoupper((string)($doc['storage_area'] ?? 'PRIVATE')) === 'OFFICIAL';
$backTab = $isOfficial ? 'official' : 'private';
$categoryLabel = trim((string)($doc['category'] ?? '')) !== '' ? (string)$doc['category'] : 'No category';
$preview = $preview ?? ['kind' => 'none', 'message' => 'No preview available.'];
$statusLabel = trim((string)($doc['status'] ?? 'Draft'));
$docExt = strtolower((string)pathinfo((string)($doc['name'] ?? ''), PATHINFO_EXTENSION));
$googleDocsEligible = in_array($docExt, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true);
$publicFileUrl = app_url('/documents/file?id=' . (int)$doc['id'] . '&sig=' . DocumentService::signedDocumentToken((int)$doc['id']));
$googleDocsAvailable = $googleDocsEligible && app_url_is_publicly_reachable($publicFileUrl);
$googleDocsUrl = $googleDocsAvailable ? google_docs_open_url($publicFileUrl) : '';
$docTitle = trim((string)($doc['title'] ?? '')) !== '' ? (string)$doc['title'] : (string)$doc['name'];
$docCode = trim((string)($doc['document_code'] ?? '')) !== '' ? (string)$doc['document_code'] : 'Uncoded';
$directionLabel = strtoupper((string)($doc['document_type'] ?? 'INCOMING')) === 'OUTGOING' ? 'Outgoing' : 'Incoming';
$routingLabel = strtoupper((string)($doc['routing_status'] ?? 'NOT_ROUTED')) === 'ROUTED' ? 'Routed' : 'Not routed';
$routingLabel = match (strtoupper((string)($doc['routing_status'] ?? 'AVAILABLE'))) {
  'PENDING_SHARE_ACCEPTANCE' => 'Pending recipient acceptance',
  'SHARE_ACCEPTED' => 'Shared and accepted',
  'SHARE_DECLINED' => 'Share declined',
  'PENDING_REVIEW_ACCEPTANCE' => 'Pending section chief acceptance',
  'IN_REVIEW' => 'In review',
  'REVIEW_ASSIGNMENT_DECLINED' => 'Review assignment declined',
  'APPROVED' => 'Approved',
  'REJECTED' => 'Rejected',
  default => 'Available',
};
$priorityLabel = match (strtoupper((string)($doc['priority_level'] ?? 'NORMAL'))) {
  'LOW' => 'Low',
  'HIGH' => 'High',
  'URGENT' => 'Urgent',
  default => 'Normal',
};
$documentDateLabel = trim((string)($doc['document_date'] ?? '')) !== '' ? (string)$doc['document_date'] : 'Not set';
$shareRecipients = $shareRecipients ?? [];
$shareRecipientGroups = [];
foreach ($shareRecipients as $recipient) {
  $groupLabel = trim((string)($recipient['division_name'] ?? '')) !== '' ? (string)$recipient['division_name'] : 'No division';
  $chiefLabel = trim((string)($recipient['chief_name'] ?? '')) !== '' ? (string)$recipient['chief_name'] : 'No division chief assigned';
  $groupKey = $groupLabel . '||' . $chiefLabel;
  if (!isset($shareRecipientGroups[$groupKey])) {
    $shareRecipientGroups[$groupKey] = [
      'division_name' => $groupLabel,
      'chief_name' => $chiefLabel,
      'items' => [],
    ];
  }
  $shareRecipientGroups[$groupKey]['items'][] = $recipient;
}
?>

<div class="workspace-page">
  <section class="workspace-toolbar">
    <div>
      <div class="section-eyebrow"><?= $isOfficial ? 'Official Record' : 'Private File' ?></div>
      <h1 class="drive-title"><?= e($docTitle) ?></h1>
      <p class="muted-copy">
        <?= e($docCode) ?> |
        Owner: <?= e((string)$doc['owner_name']) ?> |
        Division: <?= e((string)($doc['division_name'] ?? 'Unassigned')) ?><?= $isOfficial ? ' | Status: ' . e((string)($doc['status'] ?? 'Draft')) : '' ?>
      </p>
    </div>
    <div class="drive-actions">
      <a class="btn btn-light btn-sm" href="<?= BASE_URL ?>/documents?tab=<?= $isReviewer && $role === 'DIVISION_CHIEF' ? 'division_queue' : $backTab ?><?= $role === 'ADMIN' ? '&user_id='.(int)$returnUserId : '' ?>">Back</a>
      <?php if($googleDocsAvailable): ?>
        <a class="btn btn-outline-primary btn-sm" href="<?= e($googleDocsUrl) ?>" target="_blank" rel="noopener noreferrer">Open with Google Docs</a>
      <?php endif; ?>
      <?php if($canViewFile): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/documents/download?id=<?= (int)$doc['id'] ?>">Download</a>
      <?php endif; ?>
    </div>
  </section>

  <?php if(req_str('msg') !== ''): ?>
    <div class="alert alert-success"><?= e(ui_message(req_str('msg'))) ?></div>
  <?php endif; ?>
  <?php if(req_str('err') !== ''): ?>
    <div class="alert alert-danger"><?= e(ui_message(req_str('err'))) ?></div>
  <?php endif; ?>
  <?php if($googleDocsEligible && !$googleDocsAvailable): ?>
    <div class="alert alert-info">Open with Google Docs is unavailable on this setup because the file URL is not publicly reachable yet. It will work after WDMS is deployed on a public domain or the file is made internet-accessible.</div>
  <?php endif; ?>

  <section class="metric-grid">
    <article class="metric-card">
      <div class="metric-card__label">Current location</div>
      <div class="metric-card__value"><?= e((string)($doc['current_location'] ?? 'Unspecified')) ?></div>
      <div class="metric-card__meta"><?= e($routingLabel) ?></div>
    </article>
    <article class="metric-card">
      <div class="metric-card__label">Document profile</div>
      <div class="metric-card__value"><?= e($directionLabel) ?></div>
      <div class="metric-card__meta"><?= e($priorityLabel) ?> priority</div>
    </article>
    <article class="metric-card">
      <div class="metric-card__label">Tracking date</div>
      <div class="metric-card__value"><?= e($documentDateLabel) ?></div>
      <div class="metric-card__meta"><?= e((string)($doc['signatory'] ?? 'No signatory')) ?></div>
    </article>
    <article class="metric-card">
      <div class="metric-card__label">Review history</div>
      <div class="metric-card__value"><?= count($reviews ?? []) ?></div>
      <div class="metric-card__meta"><?= count($routes ?? []) ?> route update(s)</div>
    </article>
  </section>

  <div class="document-detail-layout">
    <section class="details-card document-preview-card">
      <div class="details-card__title">Preview</div>
      <?php if(($preview['kind'] ?? '') === 'pdf'): ?>
        <iframe
          class="document-preview-frame"
          src="<?= e((string)($preview['url'] ?? '')) ?>"
          title="PDF preview"
        ></iframe>
      <?php elseif(($preview['kind'] ?? '') === 'video'): ?>
        <div class="document-preview-media text-center">
          <video
            class="document-preview-video"
            src="<?= e((string)($preview['url'] ?? '')) ?>"
            controls
            preload="metadata"
          >
            Your browser does not support inline video playback.
          </video>
        </div>
      <?php elseif(($preview['kind'] ?? '') === 'image'): ?>
        <div class="document-preview-media text-center">
          <img
            class="document-preview-image"
            src="<?= e((string)($preview['url'] ?? '')) ?>"
            alt="<?= e((string)$doc['name']) ?>"
          >
        </div>
      <?php elseif(($preview['kind'] ?? '') === 'docx-html'): ?>
        <div class="document-preview-text document-preview-docx">
          <?= (string)($preview['html'] ?? '') ?>
        </div>
        <div class="text-muted small mt-2">DOCX preview is shown as a lightweight browser rendering of the document, including embedded images when available.</div>
      <?php elseif(in_array((string)($preview['kind'] ?? ''), ['text', 'docx-text'], true)): ?>
        <div class="document-preview-text">
          <pre><?= e((string)($preview['text'] ?? '')) ?></pre>
        </div>
        <?php if(($preview['kind'] ?? '') === 'docx-text'): ?>
          <div class="text-muted small mt-2">DOCX preview is shown as extracted text for reading before download.</div>
        <?php endif; ?>
      <?php else: ?>
        <div class="drive-note drive-note--soft"><?= e((string)($preview['message'] ?? 'Preview unavailable.')) ?></div>
      <?php endif; ?>
    </section>

    <div class="details-grid document-detail-sidebar">
      <section class="details-card">
      <div class="details-card__title">Current file</div>
      <?php if($canEditFile && $canViewFile): ?>
        <form method="POST" action="<?= BASE_URL ?>/documents/replace?id=<?= (int)$doc['id'] ?>" enctype="multipart/form-data" class="drive-form-stack mb-3">
          <?= csrf_field() ?>
          <input class="form-control drive-input" type="file" name="file" required>
          <button class="btn btn-primary" type="submit">Replace file</button>
        </form>
      <?php endif; ?>

      <div class="drive-note drive-note--soft">
        This page keeps only the current file available for preview and download.
      </div>
      <?php if($canViewFile): ?>
        <div class="mt-3">
          <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/documents/download?id=<?= (int)$doc['id'] ?>">Download current file</a>
        </div>
      <?php endif; ?>
      </section>

      <aside class="details-card">
      <div class="details-card__title">Tracking details</div>
      <div class="drive-note drive-note--soft mb-3">
        <strong>File name:</strong> <?= e((string)$doc['name']) ?><br>
        <strong>Category:</strong> <?= e($categoryLabel) ?><br>
        <strong>Sharing:</strong> <?= count($shared) ?> collaborator(s)
      </div>

      <?php if($canEditFile): ?>
        <form method="POST" action="<?= BASE_URL ?>/documents/metadata?id=<?= (int)$doc['id'] ?>" class="drive-form-stack">
          <?= csrf_field() ?>
          <input class="form-control drive-input" name="document_code" value="<?= e((string)($doc['document_code'] ?? '')) ?>" placeholder="Doc. ID" required>
          <input class="form-control drive-input" name="title" value="<?= e((string)($doc['title'] ?? '')) ?>" placeholder="Title" required>
          <div class="row g-2">
            <div class="col-6">
              <input class="form-control drive-input" value="<?= e($directionLabel) ?>" disabled>
            </div>
            <div class="col-6">
              <select class="form-select drive-input" name="priority_level">
                <option value="NORMAL" <?= strtoupper((string)($doc['priority_level'] ?? 'NORMAL')) === 'NORMAL' ? 'selected' : '' ?>>Normal</option>
                <option value="LOW" <?= strtoupper((string)($doc['priority_level'] ?? 'NORMAL')) === 'LOW' ? 'selected' : '' ?>>Low</option>
                <option value="HIGH" <?= strtoupper((string)($doc['priority_level'] ?? 'NORMAL')) === 'HIGH' ? 'selected' : '' ?>>High</option>
                <option value="URGENT" <?= strtoupper((string)($doc['priority_level'] ?? 'NORMAL')) === 'URGENT' ? 'selected' : '' ?>>Urgent</option>
              </select>
            </div>
          </div>
          <input class="form-control drive-input" name="signatory" value="<?= e((string)($doc['signatory'] ?? '')) ?>" placeholder="Signatory" required>
          <input class="form-control drive-input" name="current_location" value="<?= e((string)($doc['current_location'] ?? '')) ?>" placeholder="Current location" required>
          <div class="row g-2">
            <div class="col-12">
              <input class="form-control drive-input" type="date" name="document_date" value="<?= e((string)($doc['document_date'] ?? '')) ?>" required>
            </div>
          </div>
          <input class="form-control drive-input" name="category" value="<?= e((string)($doc['category'] ?? '')) ?>" placeholder="Category" required>
          <input class="form-control drive-input" name="tags" value="<?= e((string)($doc['tags'] ?? '')) ?>" placeholder="Tags">
          <button class="btn btn-outline-primary" type="submit">Save tracking details</button>
        </form>
      <?php endif; ?>

      <?php if($isPendingSharedRecipient): ?>
        <div class="details-card__title mt-4">Shared route response</div>
        <div class="drive-note drive-note--soft mb-3">This document was routed to you and is waiting for your acceptance before the file can be viewed.</div>
        <form method="POST" action="<?= BASE_URL ?>/documents/share/respond" class="drive-form-stack">
          <?= csrf_field() ?>
          <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
          <input type="hidden" name="decision" value="ACCEPT">
          <button class="btn btn-outline-success" type="submit">Accept routed document</button>
        </form>
        <form method="POST" action="<?= BASE_URL ?>/documents/share/respond" class="drive-form-stack mt-2">
          <?= csrf_field() ?>
          <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
          <input type="hidden" name="decision" value="DECLINE">
          <textarea class="form-control drive-input" name="response_note" rows="3" placeholder="Reason for not accepting yet" required></textarea>
          <button class="btn btn-outline-danger" type="submit">Do not accept yet</button>
        </form>
      <?php elseif($isDeclinedSharedRecipient): ?>
        <div class="details-card__title mt-4">Shared route response</div>
        <div class="drive-note drive-note--soft">You previously marked this shared route as not accepted yet. The owner can review the reason in the route history.</div>
      <?php endif; ?>

      <?php if($isPendingReviewer): ?>
        <div class="details-card__title mt-4">Review route response</div>
        <div class="drive-note drive-note--soft mb-3">Accept this routed document first before reviewing its file contents and making an approval decision.</div>
        <form method="POST" action="<?= BASE_URL ?>/documents/review/accept?id=<?= (int)$doc['id'] ?>" class="drive-form-stack">
          <?= csrf_field() ?>
          <button class="btn btn-outline-success" type="submit">Accept for review</button>
        </form>
        <form method="POST" action="<?= BASE_URL ?>/documents/review/decline?id=<?= (int)$doc['id'] ?>" class="drive-form-stack mt-2">
          <?= csrf_field() ?>
          <textarea class="form-control drive-input" name="response_note" rows="3" placeholder="Reason for not accepting yet" required></textarea>
          <button class="btn btn-outline-danger" type="submit">Do not accept yet</button>
        </form>
      <?php elseif($isDeclinedReviewer): ?>
        <div class="details-card__title mt-4">Review route response</div>
        <div class="drive-note drive-note--soft">This review assignment was marked as not accepted yet. The owner can see the reason in the route history.</div>
      <?php endif; ?>

      <?php if($isOwner && $isOfficial && strcasecmp($statusLabel, 'Approved') === 0): ?>
        <div class="details-card__title">Review status</div>
        <div class="drive-note drive-note--soft" style="border-color:rgba(25, 135, 84, 0.28); background:rgba(25, 135, 84, 0.08); color:#146c43;">
          <span class="badge-soft badge-soft--success" style="display:inline-flex; align-items:center; gap:0.45rem; padding:0.45rem 0.7rem;">
            <input type="checkbox" checked disabled aria-label="Already approved" style="accent-color:#198754;">
            <span>Already approved</span>
          </span>
          <div class="mt-2">This official record was already approved by the section chief and can no longer be submitted again.</div>
        </div>
      <?php endif; ?>

      <?php if($isReviewer && !$isPendingReviewer && (string)($doc['status'] ?? '') === 'To be reviewed' && strtoupper((string)($doc['review_acceptance_status'] ?? 'NOT_SENT')) === 'ACCEPTED'): ?>
        <div class="details-card__title mt-4">Review decision</div>
        <form method="POST" action="<?= BASE_URL ?>/documents/review/decision?id=<?= (int)$doc['id'] ?>" class="drive-form-stack js-confirm" data-confirm-message="Approve this official record?">
          <?= csrf_field() ?>
          <input type="hidden" name="decision" value="APPROVED">
          <button class="btn btn-outline-success" type="submit">Approve</button>
        </form>
        <form method="POST" action="<?= BASE_URL ?>/documents/review/decision?id=<?= (int)$doc['id'] ?>" class="drive-form-stack js-confirm mt-2" data-confirm-message="Reject this official record?">
          <?= csrf_field() ?>
          <input type="hidden" name="decision" value="REJECTED">
          <textarea class="form-control drive-input" name="reject_note" rows="3" placeholder="Required rejection reason" required></textarea>
          <button class="btn btn-outline-danger" type="submit">Reject with note</button>
        </form>
      <?php endif; ?>

      <div class="details-card__title mt-4">Sharing</div>
      <?php if(!$isOfficial): ?>
        <div class="drive-note drive-note--soft">Private files cannot be shared. Move content to official records if it needs collaboration.</div>
      <?php elseif($isOwner || $role === 'ADMIN'): ?>
        <form method="POST" action="<?= BASE_URL ?>/documents/share" class="drive-form-stack">
          <?= csrf_field() ?>
          <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
          <select class="form-select drive-input" name="target_user_id" required>
            <option value="">Route to employee</option>
            <?php foreach($shareRecipientGroups as $group): ?>
              <optgroup label="<?= e($group['division_name']) ?> | Chief: <?= e($group['chief_name']) ?>">
                <?php foreach($group['items'] as $recipient): ?>
                  <option value="<?= (int)$recipient['id'] ?>">
                    <?= e((string)$recipient['name']) ?> | <?= e((string)$recipient['email']) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
          <select class="form-select drive-input" name="permission">
            <option value="viewer">Viewer</option>
            <option value="editor">Editor</option>
          </select>
          <div class="drive-note drive-note--soft document-route-helper">
            The employee list is grouped by division and shows the assigned division chief. Selecting an employee will share the file and mark it as routed for that employee's acceptance.
          </div>
          <button class="btn btn-outline-primary" type="submit">Route and share to employee</button>
        </form>
      <?php endif; ?>

      <?php if(!empty($shared)): ?>
        <div class="mt-3">
          <?php foreach($shared as $s): ?>
            <div class="details-share-row">
              <div>
                <div class="fw-semibold"><?= e((string)$s['name']) ?></div>
                <div class="text-muted small">
                  <?= e((string)$s['email']) ?> |
                  <?php if(!empty($s['accepted_at'])): ?>
                    Accepted
                  <?php elseif(!empty($s['declined_at'])): ?>
                    Not accepted yet
                  <?php else: ?>
                    Waiting for acceptance
                  <?php endif; ?>
                </div>
              </div>
              <?php if($isOwner || $role === 'ADMIN'): ?>
                <form method="POST" action="<?= BASE_URL ?>/documents/revoke" class="js-confirm" data-confirm-message="Revoke this access?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
                  <input type="hidden" name="member_user_id" value="<?= (int)$s['user_id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Revoke</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      </aside>
    </div>
  </div>

      <?php if(!empty($reviews)): ?>
    <section class="details-card mt-4">
      <div class="details-card__title">Review history</div>
      <div class="table-responsive">
        <table class="table workspace-table align-middle mb-0">
          <thead>
            <tr>
              <th>Decision</th>
              <th>Reviewer</th>
              <th>Note</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($reviews as $review): ?>
              <tr>
                <td><?= e((string)$review['decision']) ?></td>
                <td><?= e((string)$review['reviewer_name']) ?></td>
                <td><?= e((string)($review['note'] ?? '-')) ?></td>
                <td><?= e((string)$review['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

  <?php if(!empty($routes)): ?>
    <section class="details-card mt-4">
      <div class="details-card__title">Route history</div>
      <div class="table-responsive">
        <table class="table workspace-table align-middle mb-0">
          <thead>
            <tr>
              <th>From</th>
              <th>To</th>
              <th>Status</th>
              <th>Note</th>
              <th>Updated by</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($routes as $route): ?>
              <tr>
                <td><?= e((string)($route['from_location'] ?: 'Start')) ?></td>
                <td><?= e((string)$route['to_location']) ?></td>
                <td><?= e(strtoupper((string)($route['status_snapshot'] ?? 'NOT_ROUTED')) === 'ROUTED' ? 'Routed' : 'Not routed') ?></td>
                <td><?= e((string)($route['note'] ?? '-')) ?></td>
                <td><?= e((string)($route['routed_by_name'] ?? 'Unknown')) ?></td>
                <td><?= e((string)$route['routed_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php require __DIR__ . "/../layouts/footer.php"; ?>
