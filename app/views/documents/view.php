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
$canForwardFile = in_array($level, ['admin', 'owner', 'editor', 'viewer', 'division_chief'], true);
$backTab = 'routed';
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
  'SHARE_ACCEPTED' => 'In recipient custody',
  'SHARE_DECLINED' => 'Returned to owner',
  'PENDING_REVIEW_ACCEPTANCE' => 'Pending section chief acceptance',
  'IN_REVIEW' => 'In section chief review',
  'REVIEW_ASSIGNMENT_DECLINED' => 'Returned to owner',
  'APPROVED' => 'Approved',
  'REJECTED' => 'Rejected',
  default => 'Available with owner',
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
$roleLabels = [
  'ADMIN' => 'Admin',
  'DIVISION_CHIEF' => 'Division Chief',
  'EMPLOYEE' => 'Employee',
];
$currentShareRow = null;
foreach (($shared ?? []) as $sharedRow) {
  if ((int)($sharedRow['user_id'] ?? 0) === $currentUserId) {
    $currentShareRow = $sharedRow;
    break;
  }
}
$shareDeclineNote = trim((string)($currentShareRow['response_note'] ?? ''));
$reviewDeclineNote = trim((string)($doc['review_acceptance_note'] ?? ''));
$routingStatus = strtoupper((string)($doc['routing_status'] ?? 'AVAILABLE'));
$isAcceptedSharedChiefReviewer = $role === 'DIVISION_CHIEF'
  && !empty($currentShareRow['accepted_at'])
  && $routingStatus === 'SHARE_ACCEPTED';
$routeOutcomeLabel = match (strtoupper(trim((string)($doc['route_outcome'] ?? 'ACTIVE')))) {
  'APPROVED' => 'Approved',
  'RETURNED' => 'Returned',
  'REJECTED' => 'Rejected',
  'ARCHIVED' => 'Archived',
  default => 'Active',
};
$routeClosedAt = trim((string)($doc['route_closed_at'] ?? ''));
$isClosedRoute = strtoupper(trim((string)($doc['route_outcome'] ?? 'ACTIVE'))) !== 'ACTIVE' || in_array($routingStatus, ['APPROVED', 'REJECTED'], true);
$isShareLocked = match ($routingStatus) {
  'APPROVED', 'REJECTED' => true,
  'PENDING_SHARE_ACCEPTANCE', 'PENDING_REVIEW_ACCEPTANCE' => true,
  'SHARE_ACCEPTED' => !in_array($level, ['admin', 'editor', 'viewer'], true),
  'IN_REVIEW' => !in_array($level, ['admin', 'division_chief'], true),
  default => false,
} || $isClosedRoute;
?>

<div class="workspace-page">
  <section class="workspace-toolbar">
    <div>
      <div class="section-eyebrow">Preview</div>
      <h1 class="drive-title"><?= e($docTitle) ?></h1>
      <p class="muted-copy"><?= e($docCode !== 'Uncoded' ? $docCode : (string)$doc['name']) ?></p>
    </div>
    <div class="drive-actions">
      <a class="btn btn-light btn-sm" href="<?= BASE_URL ?>/documents?tab=<?= $isReviewer && $role === 'DIVISION_CHIEF' ? 'division_queue' : $backTab ?><?= $role === 'ADMIN' ? '&user_id='.(int)$returnUserId : '' ?>">Back</a>
      <?php if($googleDocsAvailable): ?>
        <a class="btn btn-outline-primary btn-sm" href="<?= e($googleDocsUrl) ?>" target="_blank" rel="noopener noreferrer">Open with Google Docs</a>
      <?php endif; ?>
      <?php if($canForwardFile && !$isShareLocked): ?>
        <button
          class="btn btn-outline-primary btn-sm"
          type="button"
          data-bs-toggle="modal"
          data-bs-target="#documentShareFileModal"
          data-share-id="<?= (int)$doc['id'] ?>"
          data-share-title="<?= e($docTitle) ?>"
          data-share-routing="<?= e($routingLabel) ?>"
          data-share-locked="<?= $isShareLocked ? '1' : '0' ?>"
        >Share</button>
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
  <div class="drive-note drive-note--soft mb-3">
    <strong>Route lifecycle:</strong>
    <?= $routeClosedAt !== '' ? e('Closed: ' . $routeOutcomeLabel) : e(($routingStatus === 'AVAILABLE' ? 'Available with owner' : 'Active route')) ?>
    <?php if($routeClosedAt !== ''): ?>
      <span class="text-muted"> · <?= e($routeClosedAt) ?></span>
    <?php endif; ?>
  </div>

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

  <?php if($isPendingSharedRecipient || $isDeclinedSharedRecipient || $isPendingReviewer || $isDeclinedReviewer): ?>
    <section class="details-card mt-3">
      <div class="details-card__title">Workflow Response</div>

      <?php if($isPendingSharedRecipient): ?>
        <div class="drive-note drive-note--soft mb-3">
          This routed file is waiting for your acceptance before you can open, review, or forward it.
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <form method="POST" action="<?= BASE_URL ?>/documents/share/respond">
            <?= csrf_field() ?>
            <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
            <input type="hidden" name="decision" value="ACCEPT">
            <button class="btn btn-primary" type="submit">Accept file</button>
          </form>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/documents/share/respond" class="drive-form-stack">
          <?= csrf_field() ?>
          <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
          <input type="hidden" name="decision" value="DECLINE">
          <label class="form-label" for="share-response-note">Reason if not accepting</label>
          <textarea class="form-control drive-input" id="share-response-note" name="response_note" rows="4" maxlength="1000" placeholder="Explain why you are declining this routed file."><?= e(req_str('err') === 'response_note_required' ? req_str('response_note', '') : '') ?></textarea>
          <div>
            <button class="btn btn-outline-danger" type="submit">Decline file</button>
          </div>
        </form>
      <?php elseif($isDeclinedSharedRecipient): ?>
        <div class="drive-note drive-note--soft mb-3">
          You previously declined this routed file. You can accept it now if you need to continue the workflow.
        </div>
        <?php if($shareDeclineNote !== ''): ?>
          <div class="alert alert-secondary mb-3">
            <strong>Your last decline note:</strong> <?= e($shareDeclineNote) ?>
          </div>
        <?php endif; ?>
        <form method="POST" action="<?= BASE_URL ?>/documents/share/respond">
          <?= csrf_field() ?>
          <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
          <input type="hidden" name="decision" value="ACCEPT">
          <button class="btn btn-primary" type="submit">Accept file now</button>
        </form>
      <?php endif; ?>

      <?php if($isPendingReviewer): ?>
        <div class="drive-note drive-note--soft mb-3">
          This routed file was submitted to you for section chief review. Accept the assignment to start review and forwarding actions.
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <form method="POST" action="<?= BASE_URL ?>/documents/review/accept">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
            <button class="btn btn-primary" type="submit">Accept review</button>
          </form>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/documents/review/decline" class="drive-form-stack">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
          <label class="form-label" for="review-response-note">Reason if not accepting</label>
          <textarea class="form-control drive-input" id="review-response-note" name="response_note" rows="4" maxlength="1000" placeholder="Explain why you are declining this review assignment."><?= e(req_str('err') === 'response_note_required' ? req_str('response_note', '') : '') ?></textarea>
          <div>
            <button class="btn btn-outline-danger" type="submit">Decline review</button>
          </div>
        </form>
      <?php elseif($isDeclinedReviewer): ?>
        <div class="drive-note drive-note--soft mb-3">
          You previously declined this review assignment. You can accept it now if you are ready to continue.
        </div>
        <?php if($reviewDeclineNote !== ''): ?>
          <div class="alert alert-secondary mb-3">
            <strong>Your last decline note:</strong> <?= e($reviewDeclineNote) ?>
          </div>
        <?php endif; ?>
        <form method="POST" action="<?= BASE_URL ?>/documents/review/accept">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
          <button class="btn btn-primary" type="submit">Accept review now</button>
        </form>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if($isAcceptedSharedChiefReviewer): ?>
    <section class="details-card mt-3">
      <div class="details-card__title">Section Chief Decision</div>
      <div class="drive-note drive-note--soft mb-3">
        You already accepted this shared file. You can now approve it as section chief or reject it with a note.
      </div>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <form method="POST" action="<?= BASE_URL ?>/documents/review/decision">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
          <input type="hidden" name="decision" value="APPROVED">
          <button class="btn btn-primary" type="submit">Approve file</button>
        </form>
      </div>
      <form method="POST" action="<?= BASE_URL ?>/documents/review/decision" class="drive-form-stack">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
        <input type="hidden" name="decision" value="REJECTED">
        <label class="form-label" for="shared-chief-reject-note">Reject note</label>
        <textarea class="form-control drive-input" id="shared-chief-reject-note" name="reject_note" rows="4" maxlength="1000" placeholder="Explain what needs to be corrected before routing again."><?= e(req_str('err') === 'reject_note_required' ? req_str('reject_note', '') : '') ?></textarea>
        <div>
          <button class="btn btn-outline-danger" type="submit">Reject file</button>
        </div>
      </form>
    </section>
  <?php endif; ?>

</div>

<div class="modal fade workspace-file-details-modal" id="documentShareFileModal" tabindex="-1" aria-labelledby="documentShareFileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content workspace-file-details-modal__content">
      <form id="document-share-file-form" method="POST" action="<?= BASE_URL ?>/documents/share" class="drive-form-stack">
        <?= csrf_field() ?>
        <div class="modal-header border-0 pb-0">
          <div>
            <h5 class="modal-title" id="documentShareFileModalLabel">Route and share file</h5>
            <p class="workspace-filter-modal__intro mb-0" data-share-field="title">Choose one person in this division to receive the routed file.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-3">
          <input type="hidden" name="document_id" value="">
          <div class="drive-note drive-note--soft mb-3">
            <strong>Current route status:</strong> <span data-share-field="routing">Available with owner</span>
          </div>
          <div class="drive-note drive-note--soft mb-3 d-none" data-share-field="locked-note">
            Routing is active right now, so this file cannot be shared again until it returns to the owner or reaches a final decision.
          </div>
          <div data-share-field="form-fields">
            <div class="share-combobox mb-2" id="document-share-recipient-combobox">
              <input class="form-control drive-input share-combobox__input" type="search" id="document-share-recipient-search" placeholder="Select user in this division" autocomplete="off">
              <div class="share-combobox__panel d-none" id="document-share-recipient-panel">
                <?php foreach($shareRecipientGroups as $group): ?>
                  <?php foreach($group['items'] as $recipient): ?>
                    <?php $recipientRole = $roleLabels[strtoupper((string)($recipient['role'] ?? 'EMPLOYEE'))] ?? 'User'; ?>
                    <?php $recipientLabel = (string)$recipient['name'] . ' | ' . $recipientRole . ' | ' . (string)$recipient['email']; ?>
                    <button
                      type="button"
                      class="share-combobox__option"
                      data-value="<?= (int)$recipient['id'] ?>"
                      data-search="<?= e(strtolower(trim((string)($recipient['name'] ?? '') . ' ' . (string)($recipient['email'] ?? '') . ' ' . $recipientRole . ' ' . (string)($recipient['division_name'] ?? '')))) ?>"
                      data-label="<?= e($recipientLabel) ?>"
                    ><?= e($recipientLabel) ?></button>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </div>
            </div>
            <select class="form-select drive-input mb-2 d-none" name="target_user_id" id="document-share-recipient-select" required>
              <option value="">Select user in this division</option>
              <?php foreach($shareRecipientGroups as $group): ?>
                <optgroup label="<?= e($group['division_name']) ?> | Chief: <?= e($group['chief_name']) ?>">
                  <?php foreach($group['items'] as $recipient): ?>
                    <?php $recipientRole = $roleLabels[strtoupper((string)($recipient['role'] ?? 'EMPLOYEE'))] ?? 'User'; ?>
                    <option
                      value="<?= (int)$recipient['id'] ?>"
                      data-search="<?= e(strtolower(trim((string)($recipient['name'] ?? '') . ' ' . (string)($recipient['email'] ?? '') . ' ' . $recipientRole . ' ' . (string)($recipient['division_name'] ?? '')))) ?>"
                    >
                      <?= e((string)$recipient['name']) ?> | <?= e($recipientRole) ?> | <?= e((string)$recipient['email']) ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
            <select class="form-select drive-input" name="permission">
              <option value="viewer">Viewer</option>
              <option value="editor">Editor</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-outline-primary" type="submit" data-share-field="submit">Share</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function () {
    const initShareCombobox = function () {
      const searchInput = document.getElementById('document-share-recipient-search');
      const recipientSelect = document.getElementById('document-share-recipient-select');
      const panel = document.getElementById('document-share-recipient-panel');
      if (!searchInput || !recipientSelect || !panel) {
        return { reset: function () {} };
      }
      const optionButtons = Array.from(panel.querySelectorAll('.share-combobox__option'));
      let emptyState = panel.querySelector('.share-combobox__empty');
      if (!emptyState) {
        emptyState = document.createElement('div');
        emptyState.className = 'share-combobox__empty d-none';
        emptyState.textContent = 'No matching user found in this division.';
        panel.appendChild(emptyState);
      }
      const closePanel = function () {
        panel.classList.add('d-none');
      };
      const openPanel = function () {
        panel.classList.remove('d-none');
      };
      panel.addEventListener('mousedown', function (event) {
        event.preventDefault();
      });
      const renderOptions = function () {
        const query = String(searchInput.value || '').trim().toLowerCase();
        let visibleCount = 0;
        optionButtons.forEach(function (button) {
          const haystack = String(button.getAttribute('data-search') || '').toLowerCase();
          const matched = query === '' || haystack.indexOf(query) !== -1;
          button.classList.toggle('d-none', !matched);
          if (matched) {
            visibleCount += 1;
          }
        });
        emptyState.classList.toggle('d-none', visibleCount !== 0);
        openPanel();
      };
      optionButtons.forEach(function (button) {
        button.addEventListener('click', function () {
          const value = this.getAttribute('data-value') || '';
          const label = this.getAttribute('data-label') || this.textContent || '';
          recipientSelect.value = value;
          searchInput.value = label.trim();
          closePanel();
        });
      });
      searchInput.addEventListener('focus', renderOptions);
      searchInput.addEventListener('click', renderOptions);
      searchInput.addEventListener('input', function () {
        recipientSelect.value = '';
        renderOptions();
      });
      searchInput.addEventListener('blur', function () {
        window.setTimeout(closePanel, 180);
      });
      return {
        open: function () {
          renderOptions();
        },
        reset: function () {
          recipientSelect.value = '';
          searchInput.value = '';
          closePanel();
        },
      };
    };

    const modal = document.getElementById('documentShareFileModal');
    const form = document.getElementById('document-share-file-form');
    if (!modal || !form) return;

    const titleTarget = modal.querySelector('[data-share-field="title"]');
    const routingTarget = modal.querySelector('[data-share-field="routing"]');
    const lockedNote = modal.querySelector('[data-share-field="locked-note"]');
    const fieldsWrap = modal.querySelector('[data-share-field="form-fields"]');
    const submitButton = modal.querySelector('[data-share-field="submit"]');
    const docIdInput = form.querySelector('input[name="document_id"]');
    const shareCombobox = initShareCombobox();
    modal.addEventListener('show.bs.modal', function (event) {
      const trigger = event.relatedTarget;
      const docId = trigger ? (trigger.getAttribute('data-share-id') || '') : '';
      const title = trigger ? (trigger.getAttribute('data-share-title') || 'Selected file') : 'Selected file';
      const routing = trigger ? (trigger.getAttribute('data-share-routing') || 'Available with owner') : 'Available with owner';
      const locked = trigger ? (trigger.getAttribute('data-share-locked') === '1') : false;
      if (docIdInput) docIdInput.value = docId;
      if (titleTarget) titleTarget.textContent = title;
      if (routingTarget) routingTarget.textContent = routing;
      if (lockedNote) lockedNote.classList.toggle('d-none', !locked);
      if (fieldsWrap) fieldsWrap.classList.toggle('d-none', locked);
      if (submitButton) submitButton.disabled = locked;
      shareCombobox.reset();
    });
    modal.addEventListener('shown.bs.modal', function () {
      const searchInput = document.getElementById('document-share-recipient-search');
      if (searchInput && !submitButton.disabled) {
        searchInput.focus();
        shareCombobox.open();
      }
    });
  })();
</script>

<?php require __DIR__ . "/../layouts/footer.php"; ?>
