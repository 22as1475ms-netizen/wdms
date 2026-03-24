<?php require __DIR__ . "/../layouts/header.php"; ?>
<?php require_once __DIR__ . "/../../helpers/csrf.php"; ?>
<?php require_once __DIR__ . "/../../helpers/http.php"; ?>
<?php
$pages = max(1, (int)ceil($total / max(1, $per)));
$filters = $filters ?? [];
$privateStorage = $storage['private'] ?? ['used' => 0, 'limit' => 1, 'percent' => 0];
$officialStorage = $storage['official'] ?? ['used' => 0, 'limit' => 1, 'percent' => 0];
$privateStoragePercent = max(0, min(100, (float)($privateStorage['percent'] ?? 0)));
$officialStoragePercent = max(0, min(100, (float)($officialStorage['percent'] ?? 0)));
$privateStorageVisualPercent = $privateStoragePercent;
$officialStorageVisualPercent = $officialStoragePercent;
$privateStorageState = $privateStoragePercent >= 95 ? 'critical' : ($privateStoragePercent >= 80 ? 'warning' : 'normal');
$officialStorageState = $officialStoragePercent >= 95 ? 'critical' : ($officialStoragePercent >= 80 ? 'warning' : 'normal');
$privateStorageAlert = $privateStorageState === 'critical'
  ? 'Storage almost full'
  : ($privateStorageState === 'warning' ? 'Storage getting full' : '');
$officialStorageAlert = $officialStorageState === 'critical'
  ? 'Storage almost full'
  : ($officialStorageState === 'warning' ? 'Storage getting full' : '');
$canUploadHere = ($isAdmin ? in_array($tab, ['private', 'official'], true) : $tab === 'official') && (!$isAdmin || (int)$targetUserId === (int)($_SESSION['user']['id'] ?? 0));
$canManageLibrary = $isAdmin ? in_array($tab, ['private', 'official'], true) : $tab === 'official';
$canManageTrash = $tab === 'trash';
$selectedOwnerLabel = $selectedUser['name'] ?? 'Workspace';
$trashedFolders = $trashedFolders ?? [];
$divisionEmployeeFolders = $divisionEmployeeFolders ?? [];
$selectedQueueEmployee = $selectedQueueEmployee ?? null;
$sort = $sort ?? 'modified_desc';
$workspaceQuery = trim((string)($workspaceQuery ?? ''));
$workspaceResults = $workspaceResults ?? [];
$hasSubmittableDocs = (bool)($hasSubmittableDocs ?? false);
$submittableFolderIds = $submittableFolderIds ?? [];
$isDivisionChiefWorkspace = $isDivisionChief && !$isAdmin;
$isSharedMyFilesWorkspace = !$isAdmin;
$personalTab = $isAdmin ? $tab : 'official';
$currentRoleLabel = role_label((string)($isAdmin ? ($selectedUser['role'] ?? 'EMPLOYEE') : ($_SESSION['user']['role'] ?? 'EMPLOYEE')));
$currentDivisionLabel = (string)($isAdmin ? ($selectedUser['division_name'] ?? 'No division') : ($_SESSION['user']['division_name'] ?? 'No division'));
$workspaceTitle = match ($tab) {
  'division_queue' => 'Employee Records',
  'official' => $isSharedMyFilesWorkspace ? 'My Files' : 'Official Records',
  'shared' => 'Shared Records',
  'trash' => 'Trash',
  default => $isSharedMyFilesWorkspace ? 'My Files' : 'Private Storage',
};
$workspaceEyebrow = $isDivisionChief ? 'Division Workflow' : 'Workspace';
$workspaceSubtitle = $isAdmin
  ? 'Oversight view for ' . $selectedOwnerLabel . '.'
  : ($isDivisionChief && $tab === 'division_queue'
    ? 'Review the official records uploaded by employees in your division.'
    : 'A cleaner records workspace for uploading, searching, routing, and opening official files.');
$workspaceCountLabel = $tab === 'trash'
  ? ((int)$total . ' items in view')
  : ((int)$total . ' records in view');
$canCreateFolder = $isAdmin && $tab === 'private' && $canUploadHere;
$hasActiveWorkspaceFilters =
  $search !== '' ||
  (string)($filters['status'] ?? '') !== '' ||
  (string)($filters['category'] ?? '') !== '' ||
  (string)($filters['tags'] ?? '') !== '' ||
  (string)($filters['date_from'] ?? '') !== '' ||
  (string)($filters['date_to'] ?? '') !== '' ||
  (string)($filters['document_code'] ?? '') !== '' ||
  (string)($filters['document_type'] ?? '') !== '' ||
  (string)($filters['routing_status'] ?? '') !== '' ||
  (string)($filters['priority_level'] ?? '') !== '' ||
  (string)($filters['current_location'] ?? '') !== '' ||
  (($employeeFilter ?? 0) > 0);
$existingNameMap = [];
foreach (($existingNames ?? []) as $n) {
  $key = strtolower(trim((string)$n));
  if ($key !== '') {
    $existingNameMap[$key] = true;
  }
}

function format_bytes_ui(int $bytes): string {
  $units = ['B', 'KB', 'MB', 'GB', 'TB'];
  $value = $bytes;
  $unit = 0;
  while ($value >= 1024 && $unit < count($units) - 1) {
    $value /= 1024;
    $unit++;
  }
  return number_format($value, $unit === 0 ? 0 : 2) . ' ' . $units[$unit];
}

function document_card_type(string $name): array {
  $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
  if (in_array($ext, ['doc', 'docx'], true)) return ['word', 'bi-file-earmark-word-fill', 'Word'];
  if (in_array($ext, ['xls', 'xlsx', 'csv'], true)) return ['excel', 'bi-file-earmark-excel-fill', 'Spreadsheet'];
  if ($ext === 'pdf') return ['pdf', 'bi-file-earmark-pdf-fill', 'PDF'];
  if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) return ['image', 'bi-file-earmark-image-fill', 'Image'];
  if (in_array($ext, ['zip', 'rar', '7z'], true)) return ['archive', 'bi-file-earmark-zip-fill', 'Archive'];
  if (in_array($ext, ['mp4', 'mov', 'avi'], true)) return ['video', 'bi-file-earmark-play-fill', 'Video'];
  if (in_array($ext, ['mp3', 'wav'], true)) return ['audio', 'bi-file-earmark-music-fill', 'Audio'];
  if (in_array($ext, ['txt', 'md'], true)) return ['text', 'bi-file-earmark-text-fill', 'Text'];
  if (in_array($ext, ['php', 'js', 'ts', 'html', 'css', 'json', 'xml'], true)) return ['code', 'bi-file-earmark-code-fill', 'Code'];
  return ['generic', 'bi-file-earmark-fill', strtoupper($ext ?: 'File')];
}

function document_card_preview(array $doc): array {
  $name = (string)($doc['name'] ?? '');
  $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
  $docId = (int)($doc['id'] ?? 0);
  $fileUrl = BASE_URL . '/documents/file?id=' . $docId . '&sig=' . DocumentService::signedDocumentToken($docId);
  $latestPath = (string)($doc['latest_file_path'] ?? '');

  if ($ext === 'pdf') {
    return [
      'kind' => 'pdf',
      'url' => $fileUrl,
    ];
  }

  if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
    return [
      'kind' => 'image',
      'url' => $fileUrl,
    ];
  }

  if ($latestPath !== '' && in_array($ext, ['txt', 'md', 'csv', 'json', 'xml', 'html', 'css', 'js', 'ts', 'php'], true)) {
    $abs = DocumentService::absolutePathFromVersion($latestPath);
    $text = function_exists('document_text_preview') ? document_text_preview($abs) : null;
    if ($text !== null && trim($text) !== '') {
      return [
        'kind' => 'text',
        'text' => mb_substr(trim($text), 0, 320),
      ];
    }
  }

  if ($latestPath !== '' && $ext === 'docx') {
    $abs = DocumentService::absolutePathFromVersion($latestPath);
    $text = function_exists('document_docx_text_preview') ? document_docx_text_preview($abs) : null;
    if ($text !== null && trim($text) !== '') {
      return [
        'kind' => 'docx-text',
        'text' => mb_substr(trim($text), 0, 320),
      ];
    }
  }

  return ['kind' => 'icon'];
}

function document_status_badge(string $status): string {
  $normalized = strtolower(trim($status));
  if ($normalized === 'approved') return 'badge-soft badge-soft--success';
  if ($normalized === 'rejected') return 'badge-soft badge-soft--warning';
  if ($normalized === 'to be reviewed') return 'badge-soft badge-soft--muted';
  return 'badge-soft badge-soft--info';
}

function document_category_label(?string $category): string {
  $value = trim((string)$category);
  return $value !== '' ? $value : 'No category';
}

function document_direction_label(?string $value): string {
  return strtoupper(trim((string)$value)) === 'OUTGOING' ? 'Outgoing' : 'Incoming';
}

function routing_status_label(?string $value): string {
  return match (strtoupper(trim((string)$value))) {
    'PENDING_SHARE_ACCEPTANCE' => 'Pending recipient acceptance',
    'SHARE_ACCEPTED' => 'Shared accepted',
    'SHARE_DECLINED' => 'Share not accepted yet',
    'PENDING_REVIEW_ACCEPTANCE' => 'Pending section chief acceptance',
    'IN_REVIEW' => 'In review',
    'REVIEW_ASSIGNMENT_DECLINED' => 'Review not accepted yet',
    'APPROVED' => 'Approved',
    'REJECTED' => 'Rejected',
    default => 'Available',
  };
}

function priority_level_label(?string $value): string {
  return match (strtoupper(trim((string)$value))) {
    'LOW' => 'Low',
    'HIGH' => 'High',
    'URGENT' => 'Urgent',
    default => 'Normal',
  };
}

function route_filter_query(array $filters, string $tab, int $folder, string $search, string $sort, bool $isAdmin, int $targetUserId): array {
  return [
    'tab' => $tab,
    'folder' => $folder > 0 ? $folder : null,
    'search' => $search !== '' ? $search : null,
    'status' => ($filters['status'] ?? '') !== '' ? (string)$filters['status'] : null,
    'category' => ($filters['category'] ?? '') !== '' ? (string)$filters['category'] : null,
    'tags' => ($filters['tags'] ?? '') !== '' ? (string)$filters['tags'] : null,
    'date_from' => ($filters['date_from'] ?? '') !== '' ? (string)$filters['date_from'] : null,
    'date_to' => ($filters['date_to'] ?? '') !== '' ? (string)$filters['date_to'] : null,
    'document_code' => ($filters['document_code'] ?? '') !== '' ? (string)$filters['document_code'] : null,
    'document_type' => ($filters['document_type'] ?? '') !== '' ? (string)$filters['document_type'] : null,
    'routing_status' => ($filters['routing_status'] ?? '') !== '' ? (string)$filters['routing_status'] : null,
    'priority_level' => ($filters['priority_level'] ?? '') !== '' ? (string)$filters['priority_level'] : null,
    'current_location' => ($filters['current_location'] ?? '') !== '' ? (string)$filters['current_location'] : null,
    'sort' => $sort !== '' ? $sort : null,
    'user_id' => $isAdmin ? $targetUserId : null,
  ];
}

function trash_timestamp_label(?string $value): string {
  $time = $value ? strtotime($value) : false;
  return $time ? date('M d, Y g:i A', $time) : 'Unknown';
}

function workspace_filter_href(array $params): string {
  return BASE_URL . '/documents?' . http_build_query(array_filter($params, static fn($value): bool => $value !== '' && $value !== null));
}

function workspace_sort_label(string $sort): string {
  return match ($sort) {
    'name_asc' => 'Name A-Z',
    'name_desc' => 'Name Z-A',
    'modified_asc' => 'Date modified: oldest',
    default => 'Date modified: newest',
  };
}

function workspace_activity_label(?string $value): string {
  $time = $value ? strtotime($value) : false;
  return $time ? 'Updated ' . date('M d, Y g:i A', $time) : 'Current file';
}
?>

<div class="drive-shell" id="workspace-shell">
  <aside class="drive-sidebar" id="workspace-sidebar">
    <a class="drive-logo" href="<?= BASE_URL . workspace_home_path() ?>">
      <img class="drive-logo__image" src="<?= BASE_URL ?>/assets/images/logo.png" alt="WDMS logo">
      <span>WDMS Database Workspace</span>
    </a>

    <div class="drive-compose">
      <div class="drive-sidebar__section">
        <span class="drive-label">Current area</span>
        <div class="drive-note">
          <?php if($tab === 'division_queue'): ?>
            Employee Official Records
          <?php elseif($tab === 'official'): ?>
            <?= $isSharedMyFilesWorkspace ? 'My Files' : 'Official Records' ?>
          <?php elseif($tab === 'shared'): ?>
            Shared Official Records
          <?php elseif($tab === 'trash'): ?>
            Trash
          <?php else: ?>
            <?= $isSharedMyFilesWorkspace ? 'My Files' : 'Private Storage' ?>
          <?php endif; ?>
          <br>
          <small><?= $isAdmin ? 'Oversight for ' . e($selectedOwnerLabel) : 'Role: ' . e(role_label((string)($_SESSION['user']['role'] ?? 'EMPLOYEE'))) ?></small>
        </div>
      </div>

      <?php if($canUploadHere || $canCreateFolder): ?>
        <div class="drive-sidebar__section drive-sidebar__section--helper">
          <span class="drive-label">Create & Upload</span>
          <div class="drive-note drive-note--soft">
            Use the <strong>+ New</strong> button in the workspace header for file upload, folder upload, and folder creation.
          </div>
        </div>
      <?php endif; ?>

      <?php if($tab === 'division_queue'): ?>
        <form method="GET" class="drive-form-stack drive-block">
          <input type="hidden" name="tab" value="division_queue">
          <label class="drive-label">Employees</label>
          <select class="form-select drive-input" name="employee_id">
            <option value="0">All employees</option>
            <?php foreach(($divisionEmployees ?? []) as $employee): ?>
              <option value="<?= (int)$employee['id'] ?>" <?= (int)$employeeFilter === (int)$employee['id'] ? 'selected' : '' ?>>
                <?= e((string)$employee['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select class="form-select drive-input" name="status">
            <option value="">All statuses</option>
            <option value="To be reviewed" <?= ($filters['status'] ?? '') === 'To be reviewed' ? 'selected' : '' ?>>To be reviewed</option>
            <option value="Approved" <?= ($filters['status'] ?? '') === 'Approved' ? 'selected' : '' ?>>Approved</option>
            <option value="Rejected" <?= ($filters['status'] ?? '') === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
          </select>
          <input class="form-control drive-input" name="search" value="<?= e((string)$search) ?>" placeholder="Search official records">
          <button class="btn btn-primary" type="submit">Apply filters</button>
        </form>
      <?php endif; ?>

      <?php if($isAdmin): ?>
        <div class="drive-storage">
          <div class="drive-storage__top">
            <span>Private</span>
            <strong><?= e(format_bytes_ui((int)$privateStorage['used'])) ?></strong>
          </div>
          <div class="drive-storage__bar">
            <span style="width: <?= e(number_format((float)$privateStorage['percent'], 1)) ?>%"></span>
          </div>
          <div class="drive-storage__meta">
            <?= e(format_bytes_ui((int)$privateStorage['used'])) ?> of <?= e(format_bytes_ui((int)$privateStorage['limit'])) ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="drive-storage">
        <div class="drive-storage__top">
          <span><?= $isSharedMyFilesWorkspace ? 'My Files' : 'Official' ?></span>
          <strong><?= e(format_bytes_ui((int)$officialStorage['used'])) ?></strong>
        </div>
        <div class="drive-storage__bar">
          <span style="width: <?= e(number_format((float)$officialStorage['percent'], 1)) ?>%"></span>
        </div>
        <div class="drive-storage__meta">
          <?= e(format_bytes_ui((int)$officialStorage['used'])) ?> of <?= e(format_bytes_ui((int)$officialStorage['limit'])) ?>
        </div>
      </div>
    </div>
  </aside>

  <section class="drive-content">
    <?php if($canUploadHere): ?>
      <div class="drive-drop-surface d-none" id="workspace-drop-surface" aria-hidden="true">
        <div class="drive-drop-surface__panel">
          <div class="drive-drop-surface__icon"><i class="bi bi-cloud-arrow-up"></i></div>
          <strong>Drop files to upload them here</strong>
            <span><?= $tab === 'official' ? ($isSharedMyFilesWorkspace ? 'Files will go to My Files.' : 'Files will go to official records.') : 'Files will go to private storage.' ?></span>
        </div>
      </div>
      <aside class="drive-upload-panel d-none" id="workspace-upload-panel" aria-live="polite">
        <div class="drive-upload-panel__head">
          <div>
            <div class="drive-upload-panel__eyebrow">Upload queue</div>
            <strong class="drive-upload-panel__title" id="workspace-upload-title">Preparing upload</strong>
          </div>
          <button class="drive-upload-panel__close" id="workspace-upload-close" type="button" aria-label="Hide upload queue">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="drive-upload-panel__summary">
          <span id="workspace-upload-summary">Waiting for files</span>
          <span id="workspace-upload-percent">0%</span>
        </div>
        <div class="drive-upload-progress">
          <span id="workspace-upload-progress-bar" style="width: 0%"></span>
        </div>
        <div class="drive-upload-list" id="workspace-upload-list"></div>
      </aside>
    <?php endif; ?>

    <div class="drive-toolbar">
      <div class="drive-toolbar__content">
        <button type="button" class="btn btn-light btn-sm workspace-sidebar-toggle mb-2" id="workspace-sidebar-toggle" aria-expanded="true">
          <i class="bi bi-layout-sidebar-inset me-1"></i>Hide panel
        </button>
        <div class="section-eyebrow"><?= e($workspaceEyebrow) ?></div>
        <div class="drive-title"><?= e($workspaceTitle) ?></div>
        <div class="drive-subtitle"><?= e($workspaceSubtitle) ?></div>
        <div class="drive-toolbar__meta">
          <span class="drive-toolbar__pill"><?= e($currentRoleLabel) ?></span>
          <span class="drive-toolbar__pill"><?= e($currentDivisionLabel) ?></span>
          <span class="drive-toolbar__pill"><?= e($workspaceCountLabel) ?></span>
        </div>
      </div>
      <div class="drive-actions">
        <?php if($canUploadHere || $canCreateFolder): ?>
          <div class="dropdown">
            <button class="btn btn-primary btn-sm drive-new-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-plus-lg me-1"></i>New
            </button>
            <div class="dropdown-menu dropdown-menu-end drive-new-menu">
              <?php if($canCreateFolder): ?>
                <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#workspaceNewFolderModal">
                  <i class="bi bi-folder-plus me-2"></i>New folder
                </button>
              <?php endif; ?>
              <?php if($canUploadHere): ?>
                <button class="dropdown-item" type="button" data-workspace-new-action="file-upload">
                  <i class="bi bi-file-earmark-arrow-up me-2"></i>File upload
                </button>
                <button class="dropdown-item" type="button" data-workspace-new-action="folder-upload">
                  <i class="bi bi-folder-symlink me-2"></i>Folder upload
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
        <?php if($isAdmin): ?>
          <a class="btn btn-light btn-sm" href="<?= BASE_URL ?>/admin/users"><i class="bi bi-arrow-left me-1"></i>Back to admin</a>
        <?php endif; ?>
        <?php if($canManageTrash): ?>
          <form method="POST" action="<?= BASE_URL ?>/documents/trash/empty?user_id=<?= (int)$targetUserId ?>" class="js-confirm" data-confirm-message="Permanently delete all eligible trash items? This action cannot be undone." data-confirm-password="true" data-confirm-password-label="Confirm your password to permanently delete all trash items">
            <?= csrf_field() ?>
            <input type="hidden" name="confirm_password" value="">
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash3 me-1"></i>Delete all</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if(req_str('msg') !== ''): ?>
      <div class="alert alert-success"><?= e(ui_message(req_str('msg'))) ?></div>
    <?php endif; ?>
    <?php if(req_str('err') !== ''): ?>
      <div class="alert alert-danger"><?= e(ui_message(req_str('err'))) ?></div>
    <?php endif; ?>

    <?php if($workspaceQuery !== ''): ?>
      <section class="table-card workspace-search-card">
        <div class="table-card__header">
          <div class="workspace-contents-header">
            <h2><i class="bi bi-search me-1"></i>Search results</h2>
            <p class="workspace-contents-header__summary">Results for “<?= e($workspaceQuery) ?>” across workspace pages, folders, files, shared records, and notifications.</p>
          </div>
        </div>
        <div class="table-card__body">
          <?php if(empty($workspaceResults)): ?>
            <div class="table-empty">No workspace results matched that search.</div>
          <?php else: ?>
            <div class="workspace-search-groups">
              <?php foreach($workspaceResults as $sectionLabel => $items): ?>
                <div class="workspace-search-group">
                  <div class="workspace-search-group__head">
                    <h3><?= e((string)$sectionLabel) ?></h3>
                    <span><?= count($items) ?></span>
                  </div>
                  <div class="workspace-search-grid">
                    <?php foreach($items as $item): ?>
                      <?php
                        $itemHref = (string)($item['href'] ?? '');
                        $resolvedHref = str_starts_with($itemHref, 'http://') || str_starts_with($itemHref, 'https://')
                          ? $itemHref
                          : (BASE_URL . (str_starts_with($itemHref, '/') ? $itemHref : '/' . $itemHref));
                      ?>
                      <?php if($itemHref === 'chat://open'): ?>
                        <button class="workspace-search-hit js-open-chat-from-notification" type="button">
                          <span class="workspace-search-hit__icon"><i class="bi <?= e((string)($item['icon'] ?? 'bi-search')) ?>"></i></span>
                          <span class="workspace-search-hit__copy">
                            <strong><?= e((string)($item['title'] ?? 'Result')) ?></strong>
                            <small><?= e((string)($item['meta'] ?? '')) ?></small>
                          </span>
                        </button>
                      <?php else: ?>
                        <a class="workspace-search-hit" href="<?= e($resolvedHref) ?>">
                          <span class="workspace-search-hit__icon"><i class="bi <?= e((string)($item['icon'] ?? 'bi-search')) ?>"></i></span>
                          <span class="workspace-search-hit__copy">
                            <strong><?= e((string)($item['title'] ?? 'Result')) ?></strong>
                            <small><?= e((string)($item['meta'] ?? '')) ?></small>
                          </span>
                        </a>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="metric-grid">
      <?php if($isAdmin): ?>
        <article class="metric-card metric-card--storage metric-card--storage-private metric-card--storage-<?= e($privateStorageState) ?>" style="--storage-level: <?= e(number_format($privateStorageVisualPercent, 2, '.', '')) ?>%;">
        <div class="metric-card__storage-water" aria-hidden="true">
          <span class="metric-card__storage-wave metric-card__storage-wave--back"></span>
          <span class="metric-card__storage-wave metric-card__storage-wave--front"></span>
        </div>
        <div class="metric-card__storage-content">
          <div class="metric-card__label">Private storage</div>
          <div class="metric-card__value"><?= e(format_bytes_ui((int)$privateStorage['used'])) ?></div>
          <div class="metric-card__meta"><?= e(format_bytes_ui((int)$privateStorage['limit'])) ?> total</div>
          <?php if($privateStorageAlert !== ''): ?>
            <div class="metric-card__storage-alert metric-card__storage-alert--<?= e($privateStorageState) ?>">
              <i class="bi <?= $privateStorageState === 'critical' ? 'bi-exclamation-octagon-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
              <span><?= e($privateStorageAlert) ?> · <?= e(number_format($privateStoragePercent, 1)) ?>%</span>
            </div>
          <?php endif; ?>
        </div>
      </article>
      <?php endif; ?>
      <article class="metric-card metric-card--storage metric-card--storage-official metric-card--storage-<?= e($officialStorageState) ?>" style="--storage-level: <?= e(number_format($officialStorageVisualPercent, 2, '.', '')) ?>%;">
        <div class="metric-card__storage-water" aria-hidden="true">
          <span class="metric-card__storage-wave metric-card__storage-wave--back"></span>
          <span class="metric-card__storage-wave metric-card__storage-wave--front"></span>
        </div>
        <div class="metric-card__storage-content">
          <div class="metric-card__label"><?= $isSharedMyFilesWorkspace ? 'My Files' : 'Official storage' ?></div>
          <div class="metric-card__value"><?= e(format_bytes_ui((int)$officialStorage['used'])) ?></div>
          <div class="metric-card__meta"><?= e(format_bytes_ui((int)$officialStorage['limit'])) ?> total</div>
          <?php if($officialStorageAlert !== ''): ?>
            <div class="metric-card__storage-alert metric-card__storage-alert--<?= e($officialStorageState) ?>">
              <i class="bi <?= $officialStorageState === 'critical' ? 'bi-exclamation-octagon-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
              <span><?= e($officialStorageAlert) ?> · <?= e(number_format($officialStoragePercent, 1)) ?>%</span>
            </div>
          <?php endif; ?>
        </div>
      </article>
      <?php if($tab === 'division_queue'): ?>
        <article class="metric-card">
          <div class="metric-card__label">Pending</div>
          <div class="metric-card__value"><?= (int)($queueCounts['pending'] ?? 0) ?></div>
          <div class="metric-card__meta">Waiting for review</div>
        </article>
        <article class="metric-card">
          <div class="metric-card__label">Resolved</div>
          <div class="metric-card__value"><?= (int)(($queueCounts['approved'] ?? 0) + ($queueCounts['rejected'] ?? 0)) ?></div>
          <div class="metric-card__meta">Approved or rejected</div>
        </article>
      <?php else: ?>
        <article class="metric-card">
          <div class="metric-card__label"><?= $tab === 'trash' ? 'Items in trash' : 'Files in view' ?></div>
          <div class="metric-card__value"><?= (int)$total ?></div>
          <div class="metric-card__meta"><?= e($selectedOwnerLabel) ?></div>
        </article>
        <article class="metric-card">
          <div class="metric-card__label">Role</div>
          <div class="metric-card__value"><?= e(role_label((string)($isAdmin ? ($selectedUser['role'] ?? 'EMPLOYEE') : ($_SESSION['user']['role'] ?? 'EMPLOYEE')))) ?></div>
          <div class="metric-card__meta"><?= e((string)($isAdmin ? ($selectedUser['division_name'] ?? 'No division') : ($_SESSION['user']['division_name'] ?? 'No division'))) ?></div>
        </article>
      <?php endif; ?>
    </section>

    <div class="drive-tabs mb-3">
      <?php if(!$isAdmin): ?>
        <a class="drive-tab <?= $tab === 'official' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/documents?tab=official">My Files</a>
        <?php if($isDivisionChief): ?>
          <a class="drive-tab <?= $tab === 'division_queue' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/documents?tab=division_queue">Employee Records</a>
        <?php endif; ?>
        <a class="drive-tab <?= $tab === 'shared' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/documents?tab=shared">Shared</a>
        <a class="drive-tab <?= $tab === 'trash' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/documents?tab=trash">Trash</a>
      <?php else: ?>
        <a class="drive-tab <?= $tab === 'private' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/documents?tab=private&user_id=<?= (int)$targetUserId ?>">Private</a>
        <a class="drive-tab <?= $tab === 'official' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/documents?tab=official&user_id=<?= (int)$targetUserId ?>">Official</a>
        <a class="drive-tab <?= $tab === 'trash' ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/documents?tab=trash&user_id=<?= (int)$targetUserId ?>">Trash</a>
      <?php endif; ?>
    </div>

    <div class="admin-drive-main">
      <?php if($canManageLibrary): ?>
        <form id="library-bulk-form" method="POST" action="<?= BASE_URL ?>/documents/manage-selected<?= $isAdmin ? '?user_id='.(int)$targetUserId : '' ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="">
          <input type="hidden" name="source_storage_area" value="<?= e(strtoupper($tab)) ?>">
        </form>
      <?php endif; ?>
      <?php if(($isAdmin ? in_array($tab, ['private', 'official'], true) : $tab === 'official') && !empty($folderBreadcrumbs)): ?>
        <nav class="drive-breadcrumbs" aria-label="Folder path">
          <a href="<?= BASE_URL ?>/documents?tab=<?= e($tab) ?><?= $isAdmin ? '&user_id='.(int)$targetUserId : '' ?>"><?= $personalTab === 'official' ? ($isSharedMyFilesWorkspace ? 'My Files root' : 'Official root') : 'Private root' ?></a>
          <?php foreach($folderBreadcrumbs as $crumb): ?>
            <?php $crumbFolderId = (int)($folderPathMap[$crumb['path']] ?? 0); ?>
            <span>/</span>
            <a href="<?= BASE_URL ?>/documents?tab=<?= e($tab) ?>&folder=<?= $crumbFolderId ?><?= $isAdmin ? '&user_id='.(int)$targetUserId : '' ?>"><?= e((string)$crumb['label']) ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
      <?php if($tab === 'division_queue' && !empty($divisionEmployeeFolders)): ?>
        <div class="table-card">
          <div class="table-card__header">
            <div>
              <h2><i class="bi bi-people me-1"></i>Employees and uploaded official records</h2>
              <p>Official records are grouped by employee so section chiefs can quickly see who uploaded what and when activity last happened.</p>
            </div>
          </div>
          <div class="table-card__body">
            <div class="table-responsive">
              <table class="table workspace-table align-middle mb-0">
                <thead>
                  <tr>
                    <th>Employee</th>
                    <th>Official files</th>
                    <th>Pending</th>
                    <th>Resolved</th>
                    <th>Latest file</th>
                    <th>Last activity</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($divisionEmployeeFolders as $entry): ?>
                    <?php
                      $employee = $entry['employee'] ?? [];
                      $employeeId = (int)($employee['id'] ?? 0);
                      $employeeHref = BASE_URL . '/documents?tab=division_queue&employee_id=' . $employeeId;
                      if (($filters['status'] ?? '') !== '') {
                        $employeeHref .= '&status=' . urlencode((string)$filters['status']);
                      }
                      if ($search !== '') {
                        $employeeHref .= '&search=' . urlencode((string)$search);
                      }
                    ?>
                    <tr class="<?= $selectedQueueEmployee && (int)($selectedQueueEmployee['id'] ?? 0) === $employeeId ? 'table-active' : '' ?>">
                      <td>
                        <a href="<?= e($employeeHref) ?>" class="fw-semibold text-decoration-none"><?= e((string)($employee['name'] ?? 'Employee')) ?></a>
                        <div class="text-muted small"><?= e((string)($employee['email'] ?? '')) ?></div>
                      </td>
                      <td><?= (int)($entry['documents'] ?? 0) ?></td>
                      <td><?= (int)($entry['pending'] ?? 0) ?></td>
                      <td><?= (int)($entry['resolved'] ?? 0) ?></td>
                      <td>
                        <?= e((string)($entry['latest_document_title'] ?? 'No uploaded file yet')) ?>
                        <?php if(trim((string)($entry['latest_document_code'] ?? '')) !== ''): ?>
                          <div class="text-muted small"><?= e((string)($entry['latest_document_code'] ?? '')) ?></div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?= e(workspace_activity_label((string)($entry['latest_activity_at'] ?? ''))) ?>
                        <?php if(trim((string)($entry['latest_location'] ?? '')) !== ''): ?>
                          <div class="text-muted small"><?= e((string)($entry['latest_location'] ?? '')) ?></div>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="table-card">
        <div class="table-card__header">
          <div class="workspace-contents-header">
            <h2><i class="bi bi-folder2-open me-1"></i><?= ($isAdmin ? in_array($tab, ['private', 'official'], true) : $tab === 'official') ? 'Contents' : 'Records' ?></h2>
            <p class="workspace-contents-header__summary">
              <?php if($tab === 'official'): ?>
                <?= $isSharedMyFilesWorkspace
                  ? 'Your official records live here under My Files.'
                  : 'Official folders and records can be shared with other employees and monitored through routing.' ?>
              <?php elseif($tab === 'division_queue'): ?>
                <?= $selectedQueueEmployee
                  ? 'Viewing official records for ' . e((string)($selectedQueueEmployee['name'] ?? 'selected employee')) . '.'
                  : 'Choose an employee above to focus on one person, or review all official employee records below.' ?>
              <?php elseif($tab === 'trash'): ?>
                Select folders or files to permanently delete them. Deleted folders appear once and hide their contents.
              <?php else: ?>
                Current file listing.
              <?php endif; ?>
            </p>
            <?php if($tab === 'official' && $isAdmin): ?>
              <div class="drive-tabs workspace-status-tabs">
                <?php
                  $officialFilterBase = route_filter_query($filters, 'official', (int)$folder, (string)$search, (string)$sort, $isAdmin, (int)$targetUserId);
                ?>
                <a class="drive-tab <?= ($filters['status'] ?? '') === '' ? 'is-active' : '' ?>" href="<?= workspace_filter_href($officialFilterBase) ?>">All</a>
                <a class="drive-tab <?= ($filters['status'] ?? '') === 'Draft' ? 'is-active' : '' ?>" href="<?= workspace_filter_href($officialFilterBase + ['status' => 'Draft']) ?>">Draft</a>
                <a class="drive-tab <?= ($filters['status'] ?? '') === 'Approved' ? 'is-active' : '' ?>" href="<?= workspace_filter_href($officialFilterBase + ['status' => 'Approved']) ?>">Approved</a>
                <a class="drive-tab <?= ($filters['status'] ?? '') === 'Rejected' ? 'is-active' : '' ?>" href="<?= workspace_filter_href($officialFilterBase + ['status' => 'Rejected']) ?>">Rejected</a>
              </div>
            <?php endif; ?>
          </div>
          <?php if($canManageTrash && (!empty($trashedFolders) || !empty($docs))): ?>
            <div class="workspace-controls">
              <div class="workspace-controls__actions">
                <button class="btn btn-outline-secondary btn-sm" type="button" id="trash-select-all">
                  <i class="bi bi-check2-square me-1"></i>Select all
                </button>
                <button class="btn btn-outline-danger btn-sm" type="submit" form="trash-bulk-delete-form">
                  <i class="bi bi-trash3 me-1"></i>Delete selected
                </button>
              </div>
            </div>
          <?php elseif($canManageLibrary): ?>
            <div class="workspace-controls">
              <form method="GET" class="workspace-quick-tools">
                <input type="hidden" name="tab" value="<?= e($tab) ?>">
                <?php if($folder > 0): ?><input type="hidden" name="folder" value="<?= (int)$folder ?>"><?php endif; ?>
                <?php if($isAdmin): ?><input type="hidden" name="user_id" value="<?= (int)$targetUserId ?>"><?php endif; ?>
                <?php if(($filters['status'] ?? '') !== ''): ?><input type="hidden" name="status" value="<?= e((string)$filters['status']) ?>"><?php endif; ?>
                <?php if(($filters['category'] ?? '') !== ''): ?><input type="hidden" name="category" value="<?= e((string)$filters['category']) ?>"><?php endif; ?>
                <?php if(($filters['tags'] ?? '') !== ''): ?><input type="hidden" name="tags" value="<?= e((string)$filters['tags']) ?>"><?php endif; ?>
                <?php if(($filters['date_from'] ?? '') !== ''): ?><input type="hidden" name="date_from" value="<?= e((string)$filters['date_from']) ?>"><?php endif; ?>
                <?php if(($filters['date_to'] ?? '') !== ''): ?><input type="hidden" name="date_to" value="<?= e((string)$filters['date_to']) ?>"><?php endif; ?>
                <?php if(($filters['document_code'] ?? '') !== ''): ?><input type="hidden" name="document_code" value="<?= e((string)$filters['document_code']) ?>"><?php endif; ?>
                <?php if(($filters['document_type'] ?? '') !== ''): ?><input type="hidden" name="document_type" value="<?= e((string)$filters['document_type']) ?>"><?php endif; ?>
                <?php if(($filters['routing_status'] ?? '') !== ''): ?><input type="hidden" name="routing_status" value="<?= e((string)$filters['routing_status']) ?>"><?php endif; ?>
                <?php if(($filters['priority_level'] ?? '') !== ''): ?><input type="hidden" name="priority_level" value="<?= e((string)$filters['priority_level']) ?>"><?php endif; ?>
                <?php if(($filters['current_location'] ?? '') !== ''): ?><input type="hidden" name="current_location" value="<?= e((string)$filters['current_location']) ?>"><?php endif; ?>
                <?php if(($employeeFilter ?? 0) > 0): ?><input type="hidden" name="employee_id" value="<?= (int)$employeeFilter ?>"><?php endif; ?>
                <input class="form-control form-control-sm workspace-quick-tools__search" name="search" value="<?= e((string)$search) ?>" placeholder="Search name, title, signatory, or tags">
                <div class="workspace-sort-form">
                  <label class="workspace-sort-form__label" for="workspace-sort">Sort</label>
                  <select class="form-select form-select-sm" id="workspace-sort" name="sort" onchange="this.form.submit()">
                    <option value="modified_desc" <?= $sort === 'modified_desc' ? 'selected' : '' ?>>Date modified: newest</option>
                    <option value="modified_asc" <?= $sort === 'modified_asc' ? 'selected' : '' ?>>Date modified: oldest</option>
                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                    <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
                  </select>
                </div>
                <div class="workspace-quick-tools__buttons">
                  <button class="btn btn-sm btn-primary workspace-quick-tools__submit" type="submit">Search</button>
                  <button
                    class="btn btn-sm <?= $hasActiveWorkspaceFilters ? 'btn-primary workspace-quick-tools__filters--active' : 'btn-outline-secondary' ?> workspace-quick-tools__filters"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#workspaceAdvancedFilterModal"
                  >
                    <i class="bi bi-sliders me-1"></i>Advanced Filters<?= $hasActiveWorkspaceFilters ? ' On' : '' ?>
                  </button>
                </div>
              </form>
              <div class="workspace-controls__actions">
                <button class="btn btn-outline-secondary btn-sm" type="button" id="library-select-all-records">
                  <i class="bi bi-check2-square me-1"></i>Select all
                </button>
                <?php if($isAdmin): ?>
                  <button class="btn btn-outline-primary btn-sm" type="button" data-library-action="<?= $tab === 'private' ? 'move_to_official' : 'move_to_private' ?>">
                    <i class="bi bi-arrow-left-right me-1"></i><?= $tab === 'private' ? 'Move to Official' : 'Move to Private' ?>
                  </button>
                <?php endif; ?>
                <button class="btn btn-outline-danger btn-sm" type="button" data-library-action="delete">
                  <i class="bi bi-trash3 me-1"></i>Delete selected
                </button>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <div class="table-card__body">
          <?php if($canManageTrash): ?>
            <form
              id="trash-bulk-delete-form"
              method="POST"
              action="<?= BASE_URL ?>/documents/trash/delete-selected?user_id=<?= (int)$targetUserId ?>"
              class="js-confirm"
              data-confirm-message="Permanently delete the selected trash items? This action cannot be undone."
              data-confirm-password="true"
              data-confirm-password-label="Confirm your password to permanently delete the selected trash items"
            >
              <?= csrf_field() ?>
              <input type="hidden" name="confirm_password" value="">
            </form>
          <?php endif; ?>

          <?php if(empty($docs) && empty($trashedFolders) && empty($folders)): ?>
            <div class="table-empty">No records found in this view.</div>
          <?php else: ?>
            <?php if(($isAdmin ? in_array($tab, ['private', 'official'], true) : $tab === 'official') && !empty($folders)): ?>
              <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                  <h3 class="h6 mb-0"><?= $currentFolder ? 'Folders in ' . e((string)($currentFolder['name'] ?? '')) : 'Folders' ?></h3>
                  <span class="text-muted small"><?= count($folders) ?> folder<?= count($folders) === 1 ? '' : 's' ?></span>
                </div>
                <div class="admin-folder-grid">
                  <?php foreach($folders as $f): ?>
                    <?php $folderHref = BASE_URL . '/documents?tab=' . $tab . '&folder=' . (int)$f['id'] . ($isAdmin ? '&user_id='.(int)$targetUserId : ''); ?>
                    <article class="admin-folder-tile admin-folder-tile--with-menu">
                      <div class="admin-folder-tile__menu d-flex align-items-center gap-2">
                        <?php if($canManageLibrary): ?>
                          <input class="form-check-input library-select-item" type="checkbox" name="folder_ids[]" value="<?= (int)$f['id'] ?>" form="library-bulk-form" aria-label="Select folder <?= e((string)$f['display_name']) ?>">
                        <?php endif; ?>
                        <div class="dropdown">
                          <button class="btn btn-sm btn-light folder-menu-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Folder options">
                            <i class="bi bi-three-dots-vertical"></i>
                          </button>
                          <div class="dropdown-menu dropdown-menu-end folder-menu">
                            <a class="dropdown-item" href="<?= $folderHref ?>"><i class="bi bi-folder2-open me-2"></i>Open</a>
                            <?php if($tab === 'private'): ?>
                              <form method="POST" action="<?= BASE_URL ?>/folders/move-to-official<?= $isAdmin ? '?user_id='.(int)$targetUserId : '' ?>" class="js-confirm" data-confirm-message="Move this folder and all nested files to official records?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                <button class="dropdown-item" type="submit"><i class="bi bi-arrow-right-circle me-2"></i>Move to Official</button>
                              </form>
                              <button class="dropdown-item js-folder-rename" type="button" data-folder-id="<?= (int)$f['id'] ?>" data-folder-name="<?= e((string)$f['display_name']) ?>"><i class="bi bi-pencil me-2"></i>Rename</button>
                            <?php else: ?>
                              <?php if($isAdmin): ?>
                                <form method="POST" action="<?= BASE_URL ?>/folders/move-to-private<?= $isAdmin ? '?user_id='.(int)$targetUserId : '' ?>" class="js-confirm" data-confirm-message="Move this folder and all nested files to private files?">
                                  <?= csrf_field() ?>
                                  <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                  <button class="dropdown-item" type="submit"><i class="bi bi-arrow-left-circle me-2"></i>Move to Private</button>
                                </form>
                              <?php endif; ?>
                            <?php endif; ?>
                            <form method="POST" action="<?= BASE_URL ?>/folders/delete<?= $isAdmin ? '?user_id='.(int)$targetUserId : '' ?>" class="js-confirm" data-confirm-message="Move this folder and all nested files to trash?">
                              <?= csrf_field() ?>
                              <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                              <input type="hidden" name="storage_area" value="<?= e(strtoupper($tab)) ?>">
                              <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash3 me-2"></i>Move to trash</button>
                            </form>
                          </div>
                        </div>
                      </div>
                      <a class="admin-folder-tile__link" href="<?= $folderHref ?>">
                        <div class="admin-folder-tile__icon"><i class="bi bi-folder-fill"></i></div>
                        <div class="admin-folder-tile__title"><?= e((string)$f['display_name']) ?></div>
                        <div class="admin-folder-tile__meta"><?= $tab === 'official' ? ($isSharedMyFilesWorkspace ? 'My Files folder' : 'Official folder') : 'Private folder' ?></div>
                        <div class="admin-folder-tile__cta">Open folder <i class="bi bi-arrow-up-right"></i></div>
                      </a>
                    </article>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if($canManageTrash && !empty($trashedFolders)): ?>
              <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                  <h3 class="h6 mb-0">Deleted folders</h3>
                  <span class="text-muted small"><?= count($trashedFolders) ?> folder<?= count($trashedFolders) === 1 ? '' : 's' ?></span>
                </div>
                <div class="admin-folder-grid">
                  <?php foreach($trashedFolders as $f): ?>
                    <article class="admin-folder-tile admin-folder-tile--with-menu">
                      <div class="admin-folder-tile__menu d-flex align-items-center gap-2">
                        <input class="form-check-input trash-select-item" type="checkbox" name="folder_ids[]" value="<?= (int)$f['id'] ?>" form="trash-bulk-delete-form" aria-label="Select folder <?= e((string)$f['display_name']) ?>">
                        <form
                          method="POST"
                          action="<?= BASE_URL ?>/documents/trash/delete-selected?user_id=<?= (int)$targetUserId ?>"
                          class="js-confirm"
                          data-confirm-message="Permanently delete this folder and everything inside it? This action cannot be undone."
                          data-confirm-password="true"
                          data-confirm-password-label="Confirm your password to permanently delete this folder"
                        >
                          <?= csrf_field() ?>
                          <input type="hidden" name="confirm_password" value="">
                          <input type="hidden" name="folder_ids[]" value="<?= (int)$f['id'] ?>">
                          <button class="btn btn-sm btn-light text-danger" type="submit" aria-label="Delete folder permanently">
                            <i class="bi bi-trash3"></i>
                          </button>
                        </form>
                      </div>
                      <div class="admin-folder-tile__link" role="presentation">
                        <div class="admin-folder-tile__icon"><i class="bi bi-folder-x"></i></div>
                        <div class="admin-folder-tile__title"><?= e((string)$f['display_name']) ?></div>
                        <div class="admin-folder-tile__meta">Deleted <?= e(trash_timestamp_label((string)($f['deleted_at'] ?? null))) ?></div>
                        <div class="admin-folder-tile__cta">Contents stay hidden until the folder is permanently deleted.</div>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if(!empty($docs)): ?>
              <?php if($canManageTrash): ?>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                  <h3 class="h6 mb-0">Deleted files</h3>
                  <span class="text-muted small"><?= count($docs) ?> file<?= count($docs) === 1 ? '' : 's' ?></span>
                </div>
              <?php endif; ?>
            <div class="drive-file-grid drive-file-list">
              <?php foreach($docs as $d): ?>
                <?php [$typeClass, $iconClass, $typeLabel] = document_card_type((string)$d['name']); ?>
                <?php $cardPreview = document_card_preview($d); ?>
                <?php $sharedAccepted = $tab !== 'shared' || !empty($d['accepted_at']); ?>
                <?php $cardPreview = ['kind' => 'icon']; ?>
                <article class="drive-file-card drive-file-card--list">
                  <div class="drive-file-card__head">
                    <div class="drive-card-actions">
                      <?php if($canManageTrash): ?>
                        <input class="form-check-input trash-select-item" type="checkbox" name="document_ids[]" value="<?= (int)$d['id'] ?>" form="trash-bulk-delete-form" aria-label="Select file <?= e((string)$d['name']) ?>">
                      <?php elseif($canManageLibrary): ?>
                        <input class="form-check-input library-select-item" type="checkbox" name="document_ids[]" value="<?= (int)$d['id'] ?>" form="library-bulk-form" aria-label="Select file <?= e((string)$d['name']) ?>">
                      <?php endif; ?>
                      <?php if(!in_array($tab, ['private', 'trash'], true)): ?>
                        <span class="<?= e(document_status_badge((string)($d['status'] ?? 'Draft'))) ?>"><?= e((string)($d['status'] ?? 'Draft')) ?></span>
                      <?php endif; ?>
                      <?php if($tab !== 'division_queue'): ?>
                        <div class="dropdown">
                          <button class="btn btn-sm btn-light folder-menu-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="File options">
                            <i class="bi bi-three-dots-vertical"></i>
                          </button>
                          <div class="dropdown-menu dropdown-menu-end folder-menu">
                            <a class="dropdown-item" href="<?= BASE_URL ?>/documents/view?id=<?= (int)$d['id'] ?><?= $isAdmin ? '&user_id='.(int)$targetUserId : '' ?>"><i class="bi bi-box-arrow-up-right me-2"></i>Open</a>
                            <button
                              class="dropdown-item"
                              type="button"
                              data-bs-toggle="modal"
                              data-bs-target="#workspaceFileDetailsModal"
                              data-file-title="<?= e((string)($d['title'] ?? $d['name'])) ?>"
                              data-file-code="<?= e((string)($d['document_code'] ?? 'Uncoded')) ?>"
                              data-file-direction="<?= e(document_direction_label((string)($d['document_type'] ?? 'INCOMING'))) ?>"
                              data-file-location="<?= e((string)($d['current_location'] ?? 'Unspecified location')) ?>"
                              data-file-routing="<?= e(routing_status_label((string)($d['routing_status'] ?? 'NOT_ROUTED'))) ?>"
                              data-file-priority="<?= e(priority_level_label((string)($d['priority_level'] ?? 'NORMAL'))) ?>"
                              data-file-category="<?= e(document_category_label((string)($d['category'] ?? ''))) ?>"
                              data-file-owner="<?= e((string)($d['owner_name'] ?? $selectedOwnerLabel)) ?>"
                              data-file-storage="<?= e($tab === 'official' && $isSharedMyFilesWorkspace ? 'My Files' : ucfirst(strtolower((string)($d['storage_area'] ?? 'PRIVATE')))) ?>"
                              data-file-signatory="<?= e((string)($d['signatory'] ?? 'Not set')) ?>"
                              data-file-activity="<?= $canManageTrash ? e('Deleted ' . trash_timestamp_label((string)($d['deleted_at'] ?? null))) : e(workspace_activity_label((string)($d['last_activity_at'] ?? $d['created_at'] ?? ''))) ?>"
                              data-file-tags="<?= e(trim((string)($d['tags'] ?? '')) !== '' ? (string)$d['tags'] : 'No tags') ?>"
                            ><i class="bi bi-info-circle me-2"></i>Details</button>
                            <a class="dropdown-item" href="<?= BASE_URL ?>/documents/download?id=<?= (int)$d['id'] ?>"><i class="bi bi-download me-2"></i>Download</a>
                            <?php if($tab === 'private'): ?>
                              <form method="POST" action="<?= BASE_URL ?>/documents/move-to-official<?= $isAdmin ? '?user_id='.(int)$targetUserId : '' ?>" class="js-confirm" data-confirm-message="Move this file to official records?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button class="dropdown-item" type="submit"><i class="bi bi-arrow-right-circle me-2"></i>Move to Official</button>
                              </form>
                            <?php elseif($tab === 'official'): ?>
                              <?php if($isAdmin): ?>
                                <form method="POST" action="<?= BASE_URL ?>/documents/move-to-private<?= $isAdmin ? '?user_id='.(int)$targetUserId : '' ?>" class="js-confirm" data-confirm-message="Move this file to private files?">
                                  <?= csrf_field() ?>
                                  <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                  <button class="dropdown-item" type="submit"><i class="bi bi-arrow-left-circle me-2"></i>Move to Private</button>
                                </form>
                              <?php endif; ?>
                            <?php endif; ?>
                            <?php if($canManageTrash): ?>
                              <form
                                method="POST"
                                action="<?= BASE_URL ?>/documents/trash/delete-selected?user_id=<?= (int)$targetUserId ?>"
                                class="js-confirm"
                                data-confirm-message="Permanently delete this file? This action cannot be undone."
                                data-confirm-password="true"
                                data-confirm-password-label="Confirm your password to permanently delete this file"
                              >
                                <?= csrf_field() ?>
                                <input type="hidden" name="confirm_password" value="">
                                <input type="hidden" name="document_ids[]" value="<?= (int)$d['id'] ?>">
                                <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash3 me-2"></i>Delete permanently</button>
                              </form>
                            <?php else: ?>
                              <form method="POST" action="<?= BASE_URL ?>/documents/delete<?= $isAdmin ? '?user_id='.(int)$targetUserId : '' ?>" class="js-confirm" data-confirm-message="Move this file to trash?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash3 me-2"></i>Move to trash</button>
                              </form>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="drive-file-card__preview">
                    <?php if(($cardPreview['kind'] ?? '') === 'image'): ?>
                      <img
                        class="drive-file-card__preview-image"
                        src="<?= e((string)($cardPreview['url'] ?? '')) ?>"
                        alt="<?= e((string)$d['name']) ?>"
                        loading="lazy"
                      >
                    <?php elseif(($cardPreview['kind'] ?? '') === 'pdf'): ?>
                      <iframe
                        class="drive-file-card__preview-frame"
                        src="<?= e((string)($cardPreview['url'] ?? '')) ?>"
                        title="PDF preview for <?= e((string)$d['name']) ?>"
                        loading="lazy"
                      ></iframe>
                    <?php elseif(in_array((string)($cardPreview['kind'] ?? ''), ['text', 'docx-text'], true)): ?>
                      <div class="drive-file-card__preview-text">
                        <pre><?= e((string)($cardPreview['text'] ?? '')) ?></pre>
                      </div>
                    <?php else: ?>
                      <div class="drive-file-card__preview-icon d-flex align-items-center justify-content-center">
                        <i class="bi <?= e($iconClass) ?>" style="font-size: 3.5rem;"></i>
                      </div>
                    <?php endif; ?>
                    <a class="drive-file-card__preview-link" href="<?= BASE_URL ?>/documents/view?id=<?= (int)$d['id'] ?><?= $isAdmin ? '&user_id='.(int)$targetUserId : '' ?>" aria-label="Open <?= e((string)$d['name']) ?>"></a>
                  </div>
                  <div class="drive-file-card__body">
                    <span class="drive-file-card__type is-<?= e($typeClass) ?>">
                      <i class="bi <?= e($iconClass) ?>"></i>
                      <?= e($typeLabel) ?>
                    </span>
                    <a class="drive-file__name drive-file__name--link drive-file-card__name" href="<?= BASE_URL ?>/documents/view?id=<?= (int)$d['id'] ?><?= $isAdmin ? '&user_id='.(int)$targetUserId : '' ?>">
                      <?= e((string)($d['title'] ?? $d['name'])) ?>
                    </a>
                    <div class="drive-file__meta">
                      <?= e((string)($d['document_code'] ?? 'Uncoded')) ?> · <?= e(document_direction_label((string)($d['document_type'] ?? 'INCOMING'))) ?>
                    </div>
                    <div class="drive-file-card__meta-row">
                      <span><?= $canManageTrash ? 'Deleted ' . e(trash_timestamp_label((string)($d['deleted_at'] ?? null))) : e(workspace_activity_label((string)($d['last_activity_at'] ?? $d['created_at'] ?? ''))) ?></span>
                      <a class="btn btn-sm btn-light" href="<?= BASE_URL ?>/documents/view?id=<?= (int)$d['id'] ?><?= $isAdmin ? '&user_id='.(int)$targetUserId : '' ?>"><?= !$sharedAccepted ? 'Respond' : ($canManageTrash || $tab === 'division_queue' ? 'Review' : 'Open') ?></a>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      </div>
    </div>
    <?php if($pages > 1): ?>
      <div class="drive-pager">
        <?php $prev = max(1, $page - 1); $next = min($pages, $page + 1); ?>
        <?php
          $pagerBase = [
            'tab' => $tab,
            'folder' => $folder > 0 ? (int)$folder : null,
            'search' => $search !== '' ? $search : null,
            'status' => ($filters['status'] ?? '') !== '' ? (string)$filters['status'] : null,
            'sort' => $sort !== '' ? $sort : null,
            'employee_id' => ($employeeFilter ?? 0) > 0 ? (int)$employeeFilter : null,
            'user_id' => $isAdmin ? (int)$targetUserId : null,
          ];
        ?>
        <a class="btn btn-light btn-sm <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= workspace_filter_href($pagerBase + ['page' => $prev]) ?>">Previous</a>
        <span class="drive-pager__label">Page <?= (int)$page ?> of <?= (int)$pages ?></span>
        <a class="btn btn-light btn-sm <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= workspace_filter_href($pagerBase + ['page' => $next]) ?>">Next</a>
      </div>
    <?php endif; ?>
  </section>
</div>

<script>
  (function () {
    const librarySelectTriggers = Array.from(document.querySelectorAll('#library-select-all-records'));
    librarySelectTriggers.forEach(function (trigger) {
      trigger.addEventListener('click', function () {
        const items = Array.from(document.querySelectorAll('.library-select-item'));
        const shouldCheck = items.some(function (input) { return !input.checked; });
        items.forEach(function (input) {
          input.checked = shouldCheck;
        });
      });
    });

    const libraryForm = document.getElementById('library-bulk-form');
    const libraryActionInput = libraryForm ? libraryForm.querySelector('input[name="action"]') : null;
    document.querySelectorAll('[data-library-action]').forEach(function (button) {
      button.addEventListener('click', async function () {
        if (!libraryForm || !libraryActionInput) return;
        const action = button.getAttribute('data-library-action') || '';
        const selected = document.querySelectorAll('.library-select-item:checked');
        if (!selected.length) return;

        const message = action === 'delete'
          ? 'Move the selected items to trash?'
          : (action === 'move_to_official'
            ? 'Move the selected items to official records?'
            : 'Move the selected items to private files?');

        let ok = true;
        if (typeof window.wdmsConfirmModal === 'function') {
          const result = await window.wdmsConfirmModal({
            title: 'Confirm action',
            message: message,
            confirmText: 'Continue'
          });
          ok = !!result.ok;
        } else {
          ok = window.confirm(message);
        }
        if (!ok) return;

        libraryActionInput.value = action;
        libraryForm.submit();
      });
    });

    const selectAllTrigger = document.getElementById('trash-select-all');
    if (selectAllTrigger) {
      selectAllTrigger.addEventListener('click', function () {
        const items = Array.from(document.querySelectorAll('.trash-select-item'));
        const shouldCheck = items.some(function (input) { return !input.checked; });
        items.forEach(function (input) {
          input.checked = shouldCheck;
        });
      });
    }

    document.querySelectorAll('.js-folder-rename').forEach(function (button) {
      button.addEventListener('click', async function () {
        const folderId = button.getAttribute('data-folder-id') || '';
        const currentName = button.getAttribute('data-folder-name') || '';
        const nextName = window.prompt('Rename folder', currentName);
        if (!nextName || nextName.trim() === '' || nextName.trim() === currentName) {
          return;
        }
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= BASE_URL ?>/folders/rename<?= $isAdmin ? '?user_id='.(int)$targetUserId : '' ?>';

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_csrf';
        csrf.value = '<?= e(csrf_token()) ?>';
        form.appendChild(csrf);

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = folderId;
        form.appendChild(idInput);

        const nameInput = document.createElement('input');
        nameInput.type = 'hidden';
        nameInput.name = 'new_name';
        nameInput.value = nextName.trim();
        form.appendChild(nameInput);

        document.body.appendChild(form);
        form.submit();
      });
    });
  })();
</script>
<script>
  (function () {
    const form = document.querySelector('form[action="<?= BASE_URL ?>/documents/upload"]');
    if (!form) return;

    const dropzone = document.getElementById('upload-dropzone');
    const fileInput = document.getElementById('upload-file-input');
    const folderInput = document.getElementById('upload-folder-input');
    const fileTrigger = document.getElementById('workspace-upload-file-trigger');
    const folderTrigger = document.getElementById('workspace-upload-folder-trigger');
    const selectionLabel = document.getElementById('workspace-upload-selection');
    const workspaceShell = document.getElementById('workspace-shell');
    const dropSurface = document.getElementById('workspace-drop-surface');
    const uploadPanel = document.getElementById('workspace-upload-panel');
    const uploadTitle = document.getElementById('workspace-upload-title');
    const uploadSummary = document.getElementById('workspace-upload-summary');
    const uploadPercent = document.getElementById('workspace-upload-percent');
    const uploadProgressBar = document.getElementById('workspace-upload-progress-bar');
    const uploadList = document.getElementById('workspace-upload-list');
    const uploadClose = document.getElementById('workspace-upload-close');
    const conflictInput = form.querySelector('input[name="on_conflict"]');
    const uploadButton = form.querySelector('button[type="submit"]');
    const existing = <?= json_encode(array_keys($existingNameMap), JSON_UNESCAPED_SLASHES) ?>;
    const existingSet = new Set(existing);
    const uploadQueueStorageKey = 'wdms_upload_queue_state';
    let dragDepth = 0;
    let activeRequest = null;
    let uploadQueueHidden = false;

    function formatBytes(bytes) {
      const value = Math.max(0, Number(bytes) || 0);
      if (value < 1024) return value.toFixed(0) + ' B';
      if (value < 1024 * 1024) return (value / 1024).toFixed(1) + ' KB';
      if (value < 1024 * 1024 * 1024) return (value / (1024 * 1024)).toFixed(2) + ' MB';
      return (value / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
    }

    function escapeHtml(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function updateSelectionLabel() {
      if (!selectionLabel) return;
      const files = fileInput && fileInput.files ? Array.from(fileInput.files) : [];
      const folderFiles = folderInput && folderInput.files ? Array.from(folderInput.files) : [];
      if (folderFiles.length) {
        const rootPath = String(folderFiles[0].webkitRelativePath || '').split('/')[0] || 'Selected folder';
        selectionLabel.textContent = rootPath + ' (' + folderFiles.length + ' files)';
        return;
      }
      if (files.length === 1) {
        selectionLabel.textContent = files[0].name;
        return;
      }
      if (files.length > 1) {
        selectionLabel.textContent = files.length + ' files selected';
        return;
      }
      selectionLabel.textContent = 'No file selected yet.';
    }

    function setUploadBusy(isBusy) {
      form.dataset.uploadBusy = isBusy ? '1' : '0';
      if (uploadButton) {
        uploadButton.disabled = isBusy;
        uploadButton.innerHTML = isBusy
          ? '<i class="bi bi-arrow-repeat me-1"></i>Uploading...'
          : '<i class="bi bi-upload me-1"></i>Upload';
      }
    }

    function showUploadPanel(forceOpen) {
      if (!uploadPanel) return;
      if (forceOpen) {
        uploadQueueHidden = false;
      }
      if (!uploadQueueHidden) {
        uploadPanel.classList.remove('d-none');
      }
    }

    function hideUploadPanel() {
      if (!uploadPanel) return;
      uploadQueueHidden = true;
      uploadPanel.classList.add('d-none');
      try {
        window.sessionStorage.removeItem(uploadQueueStorageKey);
      } catch (_err) {}
    }

    function clearUploadPanel() {
      activeRequest = null;
      uploadQueueHidden = false;
      setUploadBusy(false);
      if (uploadPanel) {
        uploadPanel.classList.add('d-none');
      }
      if (uploadTitle) uploadTitle.textContent = 'Preparing upload';
      if (uploadSummary) uploadSummary.textContent = 'Waiting for files';
      if (uploadPercent) uploadPercent.textContent = '0%';
      if (uploadProgressBar) uploadProgressBar.style.width = '0%';
      if (uploadList) uploadList.innerHTML = '';
      try {
        window.sessionStorage.removeItem(uploadQueueStorageKey);
      } catch (_err) {}
    }

    function persistQueue(state) {
      try {
        window.sessionStorage.setItem(uploadQueueStorageKey, JSON.stringify(state));
      } catch (_err) {}
    }

    function restoreQueue() {
      if (!uploadPanel) return;
      try {
        const raw = window.sessionStorage.getItem(uploadQueueStorageKey);
        if (!raw) return;
        const state = JSON.parse(raw);
        if (!state || !Array.isArray(state.items)) return;
        renderQueue(state);
        showUploadPanel(true);
      } catch (_err) {}
    }

    function createQueueState(entries) {
      const items = entries.map(function (entry, index) {
        const file = entry.file || {};
        const size = Math.max(0, Number(file.size) || 0);
        const relativePath = String(entry.relativePath || '').trim();
        const location = relativePath ? relativePath.replace(/\//g, ' > ') : 'Top level';
        return {
          id: 'upload-item-' + index + '-' + Date.now(),
          name: String(file.name || 'File'),
          location: location,
          size: size,
          loaded: 0,
          progress: 0,
          status: 'Queued'
        };
      });

      return {
        items: items,
        totalBytes: items.reduce(function (sum, item) { return sum + item.size; }, 0),
        totalLoaded: 0,
        overallProgress: 0,
        statusText: 'Preparing upload...',
        title: entries.length === 1 ? 'Uploading 1 item' : 'Uploading ' + entries.length + ' items'
      };
    }

    function renderQueue(state) {
      if (!uploadPanel || !uploadList || !uploadTitle || !uploadSummary || !uploadPercent || !uploadProgressBar) {
        return;
      }
      showUploadPanel();
      uploadTitle.textContent = state.title;
      uploadSummary.textContent = state.statusText;
      uploadPercent.textContent = Math.round(state.overallProgress) + '%';
      uploadProgressBar.style.width = Math.max(0, Math.min(100, state.overallProgress)) + '%';
      uploadList.innerHTML = state.items.map(function (item) {
        const progress = Math.max(0, Math.min(100, item.progress));
        const classes = ['drive-upload-item'];
        if (item.status === 'Done') classes.push('is-complete');
        if (item.status === 'Failed') classes.push('is-error');
        return ''
          + '<article class="' + classes.join(' ') + '">'
          + '  <div class="drive-upload-item__top">'
          + '    <strong class="drive-upload-item__name" title="' + escapeHtml(item.name) + '">' + escapeHtml(item.name) + '</strong>'
          + '    <span class="drive-upload-item__status">' + escapeHtml(item.status) + '</span>'
          + '  </div>'
          + '  <div class="drive-upload-item__track"><span style="width: ' + progress + '%"></span></div>'
          + '  <div class="drive-upload-item__meta">'
          + '    <span title="' + escapeHtml(item.location) + '">' + escapeHtml(item.location) + '</span>'
          + '    <span>' + escapeHtml(formatBytes(item.loaded)) + ' / ' + escapeHtml(formatBytes(item.size)) + '</span>'
          + '  </div>'
          + '</article>';
      }).join('');
      persistQueue(state);
    }

    function updateQueueProgress(state, loadedBytes) {
      const totalBytes = Math.max(0, state.totalBytes);
      const clampedLoaded = Math.max(0, Math.min(totalBytes, Number(loadedBytes) || 0));
      state.totalLoaded = clampedLoaded;
      state.overallProgress = totalBytes > 0 ? (clampedLoaded / totalBytes) * 100 : 100;
      state.statusText = totalBytes > 0
        ? formatBytes(clampedLoaded) + ' of ' + formatBytes(totalBytes) + ' uploaded'
        : 'Uploading files...';

      let remaining = clampedLoaded;
      state.items.forEach(function (item) {
        const itemLoaded = Math.max(0, Math.min(item.size, remaining));
        remaining = Math.max(0, remaining - itemLoaded);
        item.loaded = itemLoaded;
        item.progress = item.size > 0 ? (itemLoaded / item.size) * 100 : (state.overallProgress > 0 ? 100 : 0);
        item.status = item.progress >= 100 ? 'Processing' : 'Uploading';
      });
    }

    function finalizeQueue(state, status) {
      if (status === 'done') {
        state.overallProgress = 100;
        state.statusText = 'Upload complete.';
        state.items.forEach(function (item) {
          item.loaded = item.size;
          item.progress = 100;
          item.status = 'Done';
        });
      } else {
        state.statusText = 'Upload failed. Please try again.';
        state.items.forEach(function (item) {
          if (item.progress <= 0) {
            item.status = 'Failed';
          }
        });
      }
      renderQueue(state);
    }

    function clearRelativePathInputs() {
      form.querySelectorAll('input[data-relative-path="1"]').forEach(function (input) {
        input.remove();
      });
    }

    function appendRelativePath(fieldName, relativePath) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = fieldName;
      input.value = relativePath || '';
      input.setAttribute('data-relative-path', '1');
      form.appendChild(input);
    }

    function relativePathFromFile(file) {
      const fullPath = String(file && file.webkitRelativePath ? file.webkitRelativePath : '').replace(/\\/g, '/');
      if (!fullPath) return '';
      const parts = fullPath.split('/').filter(Boolean);
      if (parts.length <= 1) return '';
      parts.pop();
      return parts.join('/');
    }

    function syncRelativePathInputs() {
      clearRelativePathInputs();
      const files = fileInput && fileInput.files ? Array.from(fileInput.files) : [];
      const folderFiles = folderInput && folderInput.files ? Array.from(folderInput.files) : [];
      files.forEach(function () {
        appendRelativePath('file_relative_paths[]', '');
      });
      folderFiles.forEach(function (file) {
        appendRelativePath('folder_relative_paths[]', relativePathFromFile(file));
      });
    }

    async function confirmOverride() {
      if (typeof window.wdmsConfirmModal === 'function') {
        const result = await window.wdmsConfirmModal({
          title: 'Name conflict',
          message: 'One or more files with the same name already exist here. Upload and override them as new versions?',
          confirmText: 'Upload as version',
          cancelText: 'Choose another file'
        });
        return !!result.ok;
      }
      return window.confirm('One or more files with the same name already exist here. Upload and override them as new versions?');
    }

    async function resolveConflict(files) {
      conflictInput.value = '';
      if (!files.length) return true;
      const hasDuplicate = files.some(function (entry) {
        const name = String(entry && entry.name ? entry.name : '').trim().toLowerCase();
        return !!name && existingSet.has(name);
      });
      if (!hasDuplicate) return true;
      const ok = await confirmOverride();
      if (ok) {
        conflictInput.value = 'override';
        return true;
      }
      return false;
    }

    async function handleSelection() {
      const files = fileInput && fileInput.files ? Array.from(fileInput.files) : [];
      const folderFiles = folderInput && folderInput.files ? Array.from(folderInput.files) : [];
      const combined = files.concat(folderFiles);
      const ok = await resolveConflict(combined);
      if (!ok) {
        if (fileInput) fileInput.value = '';
        if (folderInput) folderInput.value = '';
        clearRelativePathInputs();
        conflictInput.value = '';
        updateSelectionLabel();
        return false;
      }
      syncRelativePathInputs();
      updateSelectionLabel();
      return ok;
    }

    function selectedEntriesFromInputs() {
      const entries = [];
      const files = fileInput && fileInput.files ? Array.from(fileInput.files) : [];
      const folderFiles = folderInput && folderInput.files ? Array.from(folderInput.files) : [];
      files.forEach(function (file) {
        entries.push({ file: file, relativePath: '' });
      });
      folderFiles.forEach(function (file) {
        entries.push({ file: file, relativePath: relativePathFromFile(file) });
      });
      return entries;
    }

    function droppedFileEntries(fileList) {
      return Array.from(fileList || []).map(function (file) {
        return {
          file: file,
          relativePath: relativePathFromFile(file)
        };
      });
    }

    function collectFileEntries(items) {
      const list = Array.from(items || []);
      const entryReaders = list.map(function (item) {
        const entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
        if (entry) {
          return readEntry(entry, '');
        }
        if (item.getAsFileSystemHandle) {
          return item.getAsFileSystemHandle().then(function (handle) {
            return readFileSystemHandle(handle, '');
          }).catch(function () {
            const file = item.getAsFile ? item.getAsFile() : null;
            return file ? [{ file: file, relativePath: '' }] : [];
          });
        }
        const file = item.getAsFile ? item.getAsFile() : null;
        return Promise.resolve(file ? [{ file: file, relativePath: '' }] : []);
      });
      return Promise.all(entryReaders).then(function (groups) {
        return groups.flat();
      });
    }

    function readFileSystemHandle(handle, basePath) {
      if (!handle) {
        return Promise.resolve([]);
      }
      if (handle.kind === 'file') {
        return handle.getFile().then(function (file) {
          return [{ file: file, relativePath: basePath }];
        }).catch(function () {
          return [];
        });
      }
      if (handle.kind !== 'directory') {
        return Promise.resolve([]);
      }
      const nextBase = [basePath, handle.name].filter(Boolean).join('/');
      return (async function () {
        const groups = [];
        for await (const childHandle of handle.values()) {
          groups.push(await readFileSystemHandle(childHandle, nextBase));
        }
        return groups.flat();
      })().catch(function () {
        return [];
      });
    }

    function readEntry(entry, basePath) {
      if (entry.isFile) {
        return new Promise(function (resolve) {
          entry.file(function (file) {
            resolve([{ file: file, relativePath: basePath }]);
          }, function () {
            resolve([]);
          });
        });
      }
      if (!entry.isDirectory) {
        return Promise.resolve([]);
      }
      return readDirectory(entry).then(function (children) {
        const nextBase = [basePath, entry.name].filter(Boolean).join('/');
        return Promise.all(children.map(function (child) {
          return readEntry(child, nextBase);
        })).then(function (groups) {
          return groups.flat();
        });
      });
    }

    function readDirectory(directoryEntry) {
      return new Promise(function (resolve) {
        const reader = directoryEntry.createReader();
        const entries = [];
        const readBatch = function () {
          reader.readEntries(function (batch) {
            if (!batch.length) {
              resolve(entries);
              return;
            }
            entries.push.apply(entries, batch);
            readBatch();
          }, function () {
            resolve(entries);
          });
        };
        readBatch();
      });
    }

    function queueDroppedFileList(fileList) {
      const entries = droppedFileEntries(fileList);
      if (!entries.length) return;
      if (fileInput) fileInput.value = '';
      if (folderInput) folderInput.value = '';
      clearRelativePathInputs();
      uploadDroppedEntries(entries);
    }

    function notifyFolderDropUnsupported() {
      if (typeof window.wdmsConfirmModal === 'function') {
        window.wdmsConfirmModal({
          title: 'Folder drag-and-drop unavailable',
          message: 'This browser did not expose the folder contents during drag-and-drop. Use "Upload a folder instead" so WDMS can keep the folder structure.',
          confirmText: 'Open folder upload',
          cancelText: 'Close'
        }).then(function (result) {
          if (result && result.ok && folderTrigger) {
            folderTrigger.focus();
            folderTrigger.click();
          }
        });
        return;
      }
      window.alert('This browser did not expose the folder contents during drag-and-drop. Use "Upload a folder instead" so WDMS can keep the folder structure.');
      if (folderTrigger) {
        folderTrigger.focus();
      }
    }

    function buildUploadFormData(entries) {
      const formData = new FormData();
      const csrf = form.querySelector('input[name="_csrf"]');
      const folderIdInput = form.querySelector('input[name="folder_id"]');
      const storageAreaInput = form.querySelector('input[name="storage_area"]');
      if (csrf) formData.append('_csrf', csrf.value);
      if (folderIdInput) formData.append('folder_id', folderIdInput.value);
      if (storageAreaInput) formData.append('storage_area', storageAreaInput.value);
      formData.append('on_conflict', conflictInput.value || '');
      form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (field) {
        const name = field.getAttribute('name');
        if (!name || ['file[]', 'folder_upload[]', 'file_relative_paths[]', '_csrf', 'folder_id', 'storage_area', 'on_conflict'].indexOf(name) !== -1) {
          return;
        }
        if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
          return;
        }
        formData.append(name, field.value || '');
      });

      entries.forEach(function (entry) {
        formData.append('file[]', entry.file, entry.file.name);
        formData.append('file_relative_paths[]', entry.relativePath || '');
      });

      return formData;
    }

    function sendUpload(entries) {
      if (!entries.length || activeRequest) return;
      const queueState = createQueueState(entries);
      updateQueueProgress(queueState, 0);
      renderQueue(queueState);
      showUploadPanel(true);
      setUploadBusy(true);

      const xhr = new XMLHttpRequest();
      activeRequest = xhr;
      xhr.open('POST', form.action, true);
      xhr.withCredentials = true;

      xhr.upload.addEventListener('progress', function (event) {
        if (!event.lengthComputable) return;
        updateQueueProgress(queueState, event.loaded);
        renderQueue(queueState);
      });

      xhr.addEventListener('load', function () {
        activeRequest = null;
        setUploadBusy(false);
        if (xhr.status >= 200 && xhr.status < 400) {
          finalizeQueue(queueState, 'done');
          window.setTimeout(function () {
            window.location.href = xhr.responseURL || window.location.href;
          }, 400);
          return;
        }
        finalizeQueue(queueState, 'error');
      });

      xhr.addEventListener('error', function () {
        activeRequest = null;
        setUploadBusy(false);
        finalizeQueue(queueState, 'error');
      });

      xhr.send(buildUploadFormData(entries));
    }

    async function uploadDroppedEntries(entries) {
      if (!entries.length) return;
      const files = entries.map(function (entry) { return entry.file; });
      const ok = await resolveConflict(files);
      if (!ok) return;
      sendUpload(entries);
    }

    if (fileInput) {
      fileInput.addEventListener('change', function () {
        if (folderInput) folderInput.value = '';
        conflictInput.value = '';
        syncRelativePathInputs();
        updateSelectionLabel();
      });
    }
    if (folderInput) {
      folderInput.addEventListener('change', function () {
        if (fileInput) fileInput.value = '';
        handleSelection().then(function (ok) {
          if (ok) {
            form.requestSubmit();
          }
        });
      });
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (form.dataset.uploadBusy === '1') return;
      handleSelection().then(function (ok) {
        if (!ok) return;
        const entries = selectedEntriesFromInputs();
        if (!entries.length) return;
        sendUpload(entries);
      });
    });
    if (uploadClose) {
      uploadClose.addEventListener('click', function () {
        if (activeRequest) return;
        hideUploadPanel();
      });
    }
    restoreQueue();
    updateSelectionLabel();
    if (fileTrigger && fileInput) {
      fileTrigger.addEventListener('click', function () {
        fileInput.value = '';
        if (folderInput) folderInput.value = '';
        updateSelectionLabel();
        fileInput.click();
      });
    }
    if (folderTrigger && folderInput) {
      folderTrigger.addEventListener('click', async function () {
        if (typeof window.wdmsConfirmModal === 'function') {
          const result = await window.wdmsConfirmModal({
            title: 'Upload a folder',
            message: 'Your browser may ask for confirmation before sending all files in the selected folder. Continue?',
            confirmText: 'Choose folder',
            cancelText: 'Cancel'
          });
          if (!result.ok) return;
        }
        if (fileInput) fileInput.value = '';
        folderInput.value = '';
        updateSelectionLabel();
        folderInput.click();
      });
    }
    if (dropzone && fileInput) {
      dropzone.addEventListener('click', function () { fileInput.click(); });
      dropzone.addEventListener('dragenter', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
        dropzone.style.outline = '2px dashed #7b9bcf';
      });
      dropzone.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
        dropzone.style.outline = '2px dashed #7b9bcf';
      });
      dropzone.addEventListener('dragleave', function (e) {
        e.stopPropagation();
        dropzone.style.outline = 'none';
      });
      dropzone.addEventListener('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.style.outline = 'none';
        if (!e.dataTransfer) return;

        if (e.dataTransfer.items && e.dataTransfer.items.length) {
          collectFileEntries(e.dataTransfer.items).then(function (entries) {
            if (entries.length) {
              uploadDroppedEntries(entries);
              return;
            }

            if (!e.dataTransfer.files || !e.dataTransfer.files.length) {
              notifyFolderDropUnsupported();
              return;
            }
            queueDroppedFileList(e.dataTransfer.files);
          });
          return;
        }

        if (!e.dataTransfer.files || !e.dataTransfer.files.length) {
          notifyFolderDropUnsupported();
          return;
        }
        queueDroppedFileList(e.dataTransfer.files);
      });
    }

    if (workspaceShell && dropSurface) {
      ['dragenter', 'dragover'].forEach(function (eventName) {
        workspaceShell.addEventListener(eventName, function (e) {
          if (!e.dataTransfer || !Array.from(e.dataTransfer.types || []).includes('Files')) return;
          e.preventDefault();
          e.dataTransfer.dropEffect = 'copy';
          dragDepth += 1;
          dropSurface.classList.remove('d-none');
        });
      });
      workspaceShell.addEventListener('dragleave', function (e) {
        if (!e.dataTransfer || !Array.from(e.dataTransfer.types || []).includes('Files')) return;
        dragDepth = Math.max(0, dragDepth - 1);
        if (dragDepth === 0) {
          dropSurface.classList.add('d-none');
        }
      });
      workspaceShell.addEventListener('drop', function (e) {
        if (!e.dataTransfer) return;
        e.preventDefault();
        dragDepth = 0;
        dropSurface.classList.add('d-none');
        if (e.dataTransfer.items && e.dataTransfer.items.length) {
          collectFileEntries(e.dataTransfer.items).then(function (entries) {
            if (entries.length) {
              uploadDroppedEntries(entries);
              return;
            }
            if (e.dataTransfer.files && e.dataTransfer.files.length) {
              queueDroppedFileList(e.dataTransfer.files);
              return;
            }
            notifyFolderDropUnsupported();
          });
          return;
        }
        if (e.dataTransfer.files && e.dataTransfer.files.length) {
          queueDroppedFileList(e.dataTransfer.files);
          return;
        }
        notifyFolderDropUnsupported();
      });
    }
  })();
</script>
<script>
  (function () {
    const uploadModalElement = document.getElementById('workspaceUploadModal');
    const uploadModalLabel = document.getElementById('workspaceUploadModalLabel');
    document.querySelectorAll('[data-workspace-new-action]').forEach(function (button) {
      button.addEventListener('click', function () {
        const action = button.getAttribute('data-workspace-new-action');
        if (!uploadModalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
          return;
        }
        const modal = bootstrap.Modal.getOrCreateInstance(uploadModalElement);
        uploadModalElement.setAttribute('data-upload-intent', action || '');
        if (uploadModalLabel) {
          uploadModalLabel.textContent = action === 'folder-upload' ? 'Upload folder to <?= e($workspaceTitle) ?>' : 'Upload to <?= e($workspaceTitle) ?>';
        }
        modal.show();
      });
    });
  })();
</script>
<script>
  (function () {
    const shell = document.getElementById('workspace-shell');
    const toggle = document.getElementById('workspace-sidebar-toggle');
    if (!shell || !toggle) return;
    const key = 'wdms_workspace_sidebar_collapsed';
    const applyState = function (collapsed) {
      shell.classList.toggle('drive-shell--collapsed', collapsed);
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      toggle.innerHTML = collapsed
        ? '<i class="bi bi-layout-sidebar me-1"></i>Show panel'
        : '<i class="bi bi-layout-sidebar-inset me-1"></i>Hide panel';
    };
    applyState(window.localStorage.getItem(key) === '1');
    toggle.addEventListener('click', function () {
      const collapsed = !shell.classList.contains('drive-shell--collapsed');
      applyState(collapsed);
      window.localStorage.setItem(key, collapsed ? '1' : '0');
    });
  })();
</script>
<script>
  (function () {
    const modal = document.getElementById('workspaceFileDetailsModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
      const trigger = event.relatedTarget;
      if (!trigger) return;
      const fields = ['title', 'code', 'direction', 'location', 'routing', 'priority', 'category', 'owner', 'storage', 'signatory', 'activity', 'tags'];
      fields.forEach(function (field) {
        const target = modal.querySelector('[data-detail-field="' + field + '"]');
        if (!target) return;
        target.textContent = trigger.getAttribute('data-file-' + field) || 'Not set';
      });
    });
  })();
</script>
<?php if($canManageLibrary): ?>
<div class="modal fade workspace-filter-modal" id="workspaceAdvancedFilterModal" tabindex="-1" aria-labelledby="workspaceAdvancedFilterModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content workspace-filter-modal__content">
      <div class="modal-header border-0 pb-0">
        <div>
          <h5 class="modal-title" id="workspaceAdvancedFilterModalLabel">Advanced Filters</h5>
          <p class="workspace-filter-modal__intro mb-0">Refine your file list without moving the workspace layout around.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3">
        <form method="GET" class="workspace-filter-grid">
          <input type="hidden" name="tab" value="<?= e($tab) ?>">
          <?php if($folder > 0): ?><input type="hidden" name="folder" value="<?= (int)$folder ?>"><?php endif; ?>
          <?php if($isAdmin): ?><input type="hidden" name="user_id" value="<?= (int)$targetUserId ?>"><?php endif; ?>
          <?php if(($employeeFilter ?? 0) > 0): ?><input type="hidden" name="employee_id" value="<?= (int)$employeeFilter ?>"><?php endif; ?>
          <input type="hidden" name="sort" value="<?= e((string)$sort) ?>">
          <div class="workspace-filter-grid__search">
            <input class="form-control form-control-sm" name="search" value="<?= e((string)$search) ?>" placeholder="Search name, title, signatory, or tags">
          </div>
          <input class="form-control form-control-sm" name="document_code" value="<?= e((string)($filters['document_code'] ?? '')) ?>" placeholder="Doc. ID">
          <input class="form-control form-control-sm" name="current_location" value="<?= e((string)($filters['current_location'] ?? '')) ?>" placeholder="Current location">
          <select class="form-select form-select-sm" name="document_type">
            <option value="">All directions</option>
            <option value="INCOMING" <?= ($filters['document_type'] ?? '') === 'INCOMING' ? 'selected' : '' ?>>Incoming</option>
            <option value="OUTGOING" <?= ($filters['document_type'] ?? '') === 'OUTGOING' ? 'selected' : '' ?>>Outgoing</option>
          </select>
          <select class="form-select form-select-sm" name="routing_status">
            <option value="">Any route status</option>
            <option value="AVAILABLE" <?= ($filters['routing_status'] ?? '') === 'AVAILABLE' ? 'selected' : '' ?>>Available</option>
            <option value="PENDING_SHARE_ACCEPTANCE" <?= ($filters['routing_status'] ?? '') === 'PENDING_SHARE_ACCEPTANCE' ? 'selected' : '' ?>>Pending recipient acceptance</option>
            <option value="SHARE_ACCEPTED" <?= ($filters['routing_status'] ?? '') === 'SHARE_ACCEPTED' ? 'selected' : '' ?>>Shared accepted</option>
            <option value="SHARE_DECLINED" <?= ($filters['routing_status'] ?? '') === 'SHARE_DECLINED' ? 'selected' : '' ?>>Share not accepted yet</option>
            <option value="PENDING_REVIEW_ACCEPTANCE" <?= ($filters['routing_status'] ?? '') === 'PENDING_REVIEW_ACCEPTANCE' ? 'selected' : '' ?>>Pending section chief acceptance</option>
            <option value="IN_REVIEW" <?= ($filters['routing_status'] ?? '') === 'IN_REVIEW' ? 'selected' : '' ?>>In review</option>
            <option value="REVIEW_ASSIGNMENT_DECLINED" <?= ($filters['routing_status'] ?? '') === 'REVIEW_ASSIGNMENT_DECLINED' ? 'selected' : '' ?>>Review not accepted yet</option>
            <option value="APPROVED" <?= ($filters['routing_status'] ?? '') === 'APPROVED' ? 'selected' : '' ?>>Approved</option>
            <option value="REJECTED" <?= ($filters['routing_status'] ?? '') === 'REJECTED' ? 'selected' : '' ?>>Rejected</option>
          </select>
          <select class="form-select form-select-sm" name="priority_level">
            <option value="">Any priority</option>
            <option value="LOW" <?= ($filters['priority_level'] ?? '') === 'LOW' ? 'selected' : '' ?>>Low</option>
            <option value="NORMAL" <?= ($filters['priority_level'] ?? '') === 'NORMAL' ? 'selected' : '' ?>>Normal</option>
            <option value="HIGH" <?= ($filters['priority_level'] ?? '') === 'HIGH' ? 'selected' : '' ?>>High</option>
            <option value="URGENT" <?= ($filters['priority_level'] ?? '') === 'URGENT' ? 'selected' : '' ?>>Urgent</option>
          </select>
          <select class="form-select form-select-sm" name="status">
            <option value="">Any status</option>
            <option value="Draft" <?= ($filters['status'] ?? '') === 'Draft' ? 'selected' : '' ?>>Draft</option>
            <option value="To be reviewed" <?= ($filters['status'] ?? '') === 'To be reviewed' ? 'selected' : '' ?>>To be reviewed</option>
            <option value="Approved" <?= ($filters['status'] ?? '') === 'Approved' ? 'selected' : '' ?>>Approved</option>
            <option value="Rejected" <?= ($filters['status'] ?? '') === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
          </select>
          <input class="form-control form-control-sm" name="category" value="<?= e((string)($filters['category'] ?? '')) ?>" placeholder="Category">
          <input class="form-control form-control-sm" name="tags" value="<?= e((string)($filters['tags'] ?? '')) ?>" placeholder="Tags">
          <input class="form-control form-control-sm" type="date" name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>">
          <input class="form-control form-control-sm" type="date" name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>">
          <div class="workspace-filter-modal__actions">
            <a class="btn btn-sm btn-light" href="<?= workspace_filter_href(route_filter_query([], $tab, (int)$folder, '', $sort, $isAdmin, (int)$targetUserId)) ?>">Reset</a>
            <button class="btn btn-sm btn-primary" type="submit">Apply filters</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if($canCreateFolder): ?>
<div class="modal fade workspace-file-details-modal" id="workspaceNewFolderModal" tabindex="-1" aria-labelledby="workspaceNewFolderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content workspace-file-details-modal__content">
      <form method="POST" action="<?= BASE_URL ?>/folders/create">
        <?= csrf_field() ?>
        <input type="hidden" name="folder_id" value="<?= (int)$folder ?>">
        <div class="modal-header border-0 pb-0">
          <div>
            <h5 class="modal-title" id="workspaceNewFolderModalLabel">New folder</h5>
            <p class="workspace-filter-modal__intro mb-0">Create a folder in the current workspace location.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-3">
          <label class="form-label small text-muted" for="workspace-new-folder-name">Folder name</label>
          <input class="form-control" id="workspace-new-folder-name" name="name" placeholder="Enter folder name" required>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create folder</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if($canUploadHere): ?>
<div class="modal fade workspace-upload-modal" id="workspaceUploadModal" tabindex="-1" aria-labelledby="workspaceUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content workspace-file-details-modal__content">
      <form id="workspace-upload-form" method="POST" action="<?= BASE_URL ?>/documents/upload" enctype="multipart/form-data" class="drive-form-stack drive-form-stack--upload workspace-upload-modal__form">
        <?= csrf_field() ?>
        <input type="hidden" name="folder_id" value="<?= (int)$folder ?>">
        <input type="hidden" name="storage_area" value="<?= $personalTab === 'official' ? 'OFFICIAL' : 'PRIVATE' ?>">
        <input type="hidden" name="on_conflict" value="">
        <div class="modal-header border-0 pb-0">
          <div>
            <h5 class="modal-title" id="workspaceUploadModalLabel">Upload to <?= e($workspaceTitle) ?></h5>
            <p class="workspace-filter-modal__intro mb-0">Add a file or folder, then fill in the tracking details before upload.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-3">
          <div class="workspace-upload-modal__chooser">
            <button class="btn btn-outline-primary" type="button" id="workspace-upload-file-trigger">
              <i class="bi bi-file-earmark-arrow-up me-1"></i>Choose file
            </button>
            <button class="btn btn-outline-secondary" type="button" id="workspace-upload-folder-trigger">
              <i class="bi bi-folder-symlink me-1"></i>Choose folder
            </button>
            <span class="workspace-upload-modal__selection" id="workspace-upload-selection">No file selected yet.</span>
          </div>
          <label class="drive-label">Add files</label>
          <div class="drive-note drive-note--soft workspace-upload-modal__dropzone" id="upload-dropzone" style="cursor: pointer;">
            <i class="bi bi-cloud-arrow-up me-1"></i>Click here or drag and drop files.
          </div>
          <input class="form-control drive-input" id="upload-file-input" type="file" name="file[]">
          <input class="d-none" id="upload-folder-input" type="file" name="folder_upload[]" webkitdirectory directory>
          <div class="drive-note drive-note--soft">
            Tracking profile
            <br>
            <small>Upload one document at a time so the company-issued Doc. ID clearly identifies which file is which.</small>
          </div>
          <div class="workspace-upload-modal__grid">
            <input class="form-control drive-input" name="document_code" placeholder="Doc. ID" required>
            <input class="form-control drive-input" name="title" placeholder="Document title">
            <input class="form-control drive-input" value="Incoming on upload; becomes outgoing after section chief approval" disabled>
            <input class="form-control drive-input" name="signatory" placeholder="Signatory" required>
            <input class="form-control drive-input workspace-upload-modal__field--wide" name="current_location" placeholder="Current location" value="<?= $personalTab === 'official' ? 'Official Records Receiving' : 'Private Workspace' ?>">
            <div class="row g-2 workspace-upload-modal__field--wide">
              <div class="col-6">
                <select class="form-select drive-input" name="priority_level">
                  <option value="NORMAL">Normal</option>
                  <option value="LOW">Low</option>
                  <option value="HIGH">High</option>
                  <option value="URGENT">Urgent</option>
                </select>
              </div>
              <div class="col-6">
                <input class="form-control drive-input" type="date" name="document_date" value="<?= e(date('Y-m-d')) ?>" required>
              </div>
            </div>
            <input class="form-control drive-input" name="category" placeholder="Category" required>
            <input class="form-control drive-input" name="tags" placeholder="Tags">
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button class="btn drive-secondary" type="submit"><i class="bi bi-upload me-1"></i>Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="modal fade workspace-file-details-modal" id="workspaceFileDetailsModal" tabindex="-1" aria-labelledby="workspaceFileDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content workspace-file-details-modal__content">
      <div class="modal-header border-0 pb-0">
        <div>
          <h5 class="modal-title" id="workspaceFileDetailsModalLabel">File details</h5>
          <p class="workspace-filter-modal__intro mb-0">Quick reference for the selected record.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-3">
        <div class="workspace-file-details">
          <div><span>Title</span><strong data-detail-field="title">-</strong></div>
          <div><span>Doc. ID</span><strong data-detail-field="code">-</strong></div>
          <div><span>Direction</span><strong data-detail-field="direction">-</strong></div>
          <div><span>Location</span><strong data-detail-field="location">-</strong></div>
          <div><span>Route status</span><strong data-detail-field="routing">-</strong></div>
          <div><span>Priority</span><strong data-detail-field="priority">-</strong></div>
          <div><span>Category</span><strong data-detail-field="category">-</strong></div>
          <div><span>Owner</span><strong data-detail-field="owner">-</strong></div>
          <div><span>Storage</span><strong data-detail-field="storage">-</strong></div>
          <div><span>Signatory</span><strong data-detail-field="signatory">-</strong></div>
          <div><span>Last activity</span><strong data-detail-field="activity">-</strong></div>
          <div><span>Tags</span><strong data-detail-field="tags">-</strong></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . "/../layouts/footer.php"; ?>
