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
$activeRoutingStatuses = ['PENDING_SHARE_ACCEPTANCE', 'SHARE_ACCEPTED', 'PENDING_REVIEW_ACCEPTANCE', 'IN_REVIEW'];
$isShareLocked = in_array(strtoupper((string)($doc['routing_status'] ?? 'AVAILABLE')), $activeRoutingStatuses, true);
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
