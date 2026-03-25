<?php require __DIR__ . "/../layouts/header.php"; ?>
<?php require_once __DIR__ . "/../../helpers/csrf.php"; ?>
<?php require_once __DIR__ . "/../../helpers/http.php"; ?>
<?php
$pages = max(1, (int)ceil($total / max(1, $per)));
$filters = $filters ?? [];
$privateStorage = $storage['all'] ?? ['used' => 0, 'limit' => 1, 'percent' => 0];
$officialStorage = $storage['all'] ?? ['used' => 0, 'limit' => 1, 'percent' => 0];
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
$canUploadHere = ($tab === 'routed') && (!$isAdmin || (int)$targetUserId === (int)($_SESSION['user']['id'] ?? 0));
$canManageLibrary = $tab === 'routed';
$canManageTrash = $tab === 'trash';
$selectedOwnerLabel = (string)($selectedUser['name'] ?? ($_SESSION['user']['name'] ?? 'Workspace'));
$trashedFolders = $trashedFolders ?? [];
$divisionEmployeeFolders = $divisionEmployeeFolders ?? [];
$selectedQueueEmployee = $selectedQueueEmployee ?? null;
$sort = $sort ?? 'modified_desc';
$workspaceQuery = trim((string)($workspaceQuery ?? ''));
$workspaceResults = $workspaceResults ?? [];
$shareRecipients = $shareRecipients ?? [];
$hasSubmittableDocs = (bool)($hasSubmittableDocs ?? false);
$submittableFolderIds = $submittableFolderIds ?? [];
$isDivisionChiefWorkspace = $isDivisionChief && !$isAdmin;
$isSharedMyFilesWorkspace = !$isAdmin;
$personalTab = 'routed';
$currentRoleLabel = role_label((string)($isAdmin ? ($selectedUser['role'] ?? 'EMPLOYEE') : ($_SESSION['user']['role'] ?? 'EMPLOYEE')));
$currentDivisionLabel = (string)($isAdmin ? ($selectedUser['division_name'] ?? 'No division') : ($_SESSION['user']['division_name'] ?? 'No division'));
$workspaceTitle = match ($tab) {
  'division_queue' => 'Employee Records',
  'routed' => 'My Files',
  'shared' => 'Shared',
  'trash' => 'Trash',
  default => 'My Files',
};
$workspaceEyebrow = $isDivisionChief ? 'Division Workflow' : 'Workspace';
$workspaceSubtitle = $isAdmin
  ? 'Oversight view for ' . $selectedOwnerLabel . '.'
  : ($isDivisionChief && $tab === 'division_queue'
    ? 'Review the routed files uploaded by employees in your division.'
    : 'Your files and routed records.');
$workspaceCountLabel = $tab === 'trash'
  ? ((int)$total . ' items in view')
  : ((int)$total . ' records in view');
$canCreateFolder = false;
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
$shareRoleLabels = [
  'ADMIN' => 'Admin',
  'DIVISION_CHIEF' => 'Division Chief',
  'EMPLOYEE' => 'Employee',
];
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

function workflow_badge_label(?string $routingStatus, ?string $status = null): string {
  return match (strtoupper(trim((string)$routingStatus))) {
    'PENDING_SHARE_ACCEPTANCE' => 'Waiting',
    'SHARE_ACCEPTED' => 'Routed',
    'PENDING_REVIEW_ACCEPTANCE' => 'Routed',
    'IN_REVIEW' => 'Routed',
    'APPROVED' => 'Completed',
    'SHARE_DECLINED' => 'Returned',
    'REVIEW_ASSIGNMENT_DECLINED' => 'Returned',
    'REJECTED' => 'Returned',
    default => (strtolower(trim((string)$status)) === 'approved' ? 'Completed' : 'Waiting'),
  };
}

function workflow_badge_class(?string $routingStatus, ?string $status = null): string {
  return match (workflow_badge_label($routingStatus, $status)) {
    'Completed' => 'badge-soft badge-soft--success',
    'Returned' => 'badge-soft badge-soft--warning',
    'Routed' => 'badge-soft badge-soft--info',
    default => 'badge-soft badge-soft--muted',
  };
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
    'SHARE_ACCEPTED' => 'In recipient custody',
    'SHARE_DECLINED' => 'Returned to owner',
    'PENDING_REVIEW_ACCEPTANCE' => 'Pending section chief acceptance',
    'IN_REVIEW' => 'In section chief review',
    'REVIEW_ASSIGNMENT_DECLINED' => 'Returned to owner',
    'APPROVED' => 'Approved',
    'REJECTED' => 'Rejected',
    default => 'Available with owner',
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

function sidebar_tab_href(string $targetTab, bool $isAdmin, int $targetUserId): string {
  return BASE_URL . '/documents?tab=' . $targetTab . ($isAdmin ? '&user_id=' . $targetUserId : '');
}

$navSearchSuggestions = [
  [
    'label' => 'My Files',
    'meta' => 'Workspace tab',
    'href' => sidebar_tab_href('routed', $isAdmin, (int)$targetUserId),
    'keywords' => 'my files workspace routed files records documents',
  ],
  [
    'label' => 'Shared',
    'meta' => 'Workspace tab',
    'href' => sidebar_tab_href('shared', false, (int)$targetUserId),
    'keywords' => 'shared routed files received sent route monitor',
  ],
  [
    'label' => 'Trash',
    'meta' => 'Workspace tab',
    'href' => sidebar_tab_href('trash', $isAdmin, (int)$targetUserId),
    'keywords' => 'trash deleted files restore',
  ],
  [
    'label' => 'Profile',
    'meta' => 'Account settings',
    'href' => BASE_URL . '/account/password',
    'keywords' => 'profile settings account password avatar user preferences',
  ],
  [
    'label' => 'Dark mode',
    'meta' => 'Appearance setting',
    'action' => 'toggle-dark-mode',
    'keywords' => 'dark mode theme appearance light mode display',
  ],
  [
    'label' => 'Log out',
    'meta' => 'Session action',
    'action' => 'logout',
    'keywords' => 'log out logout sign out leave session account',
  ],
];

if ($isDivisionChief && !$isAdmin) {
  $navSearchSuggestions[] = [
    'label' => 'Employee Records',
    'meta' => 'Division workflow',
    'href' => sidebar_tab_href('division_queue', false, (int)$targetUserId),
    'keywords' => 'employee records division queue review pending approved rejected',
  ];
}

$navSearchSeen = [];
foreach ($navSearchSuggestions as $seedItem) {
  $seedKey = strtolower(trim((string)($seedItem['label'] ?? ''))) . '|' . trim((string)($seedItem['href'] ?? ''));
  $navSearchSeen[$seedKey] = true;
}

foreach (($docs ?? []) as $navDoc) {
  $docId = (int)($navDoc['id'] ?? 0);
  if ($docId <= 0) {
    continue;
  }
  $docHref = BASE_URL . '/documents/view?id=' . $docId . ($isAdmin ? '&user_id=' . (int)$targetUserId : '');
  $docLabel = trim((string)($navDoc['title'] ?? '')) !== '' ? (string)$navDoc['title'] : (string)($navDoc['name'] ?? ('Document #' . $docId));
  $docMetaBits = array_filter([
    (string)($navDoc['name'] ?? ''),
    document_category_label($navDoc['category'] ?? null),
    routing_status_label($navDoc['routing_status'] ?? null),
  ], static fn($value): bool => trim((string)$value) !== '');
  $docKey = strtolower($docLabel) . '|' . $docHref;
  if (isset($navSearchSeen[$docKey])) {
    continue;
  }
  $navSearchSeen[$docKey] = true;
  $navSearchSuggestions[] = [
    'label' => $docLabel,
    'meta' => implode(' • ', array_slice($docMetaBits, 0, 3)),
    'href' => $docHref,
    'keywords' => implode(' ', array_filter([
      $docLabel,
      (string)($navDoc['name'] ?? ''),
      (string)($navDoc['document_code'] ?? ''),
      (string)($navDoc['category'] ?? ''),
      (string)($navDoc['tags'] ?? ''),
      (string)($navDoc['signatory'] ?? ''),
      (string)($navDoc['current_location'] ?? ''),
      routing_status_label($navDoc['routing_status'] ?? null),
      $workspaceTitle,
    ], static fn($value): bool => trim((string)$value) !== '')),
  ];
}
?>

<div class="drive-shell" id="workspace-shell">
  <script>
    window.wdmsNavSearchDataset = <?= json_encode(array_values($navSearchSuggestions), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.dispatchEvent(new Event('wdms-nav-search-data-ready'));
  </script>
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
            Employee Routed Files
          <?php elseif($tab === 'routed'): ?>
            My Files
          <?php elseif($tab === 'shared'): ?>
            Shared
          <?php elseif($tab === 'trash'): ?>
            Trash
          <?php else: ?>
            My Files
          <?php endif; ?>
          <br>
          <small><?= $isAdmin ? 'Oversight for ' . e($selectedOwnerLabel) : 'Role: ' . e(role_label((string)($_SESSION['user']['role'] ?? 'EMPLOYEE'))) ?></small>
        </div>
      </div>

      <div class="drive-sidebar__section">
        <span class="drive-label">Browse</span>
        <nav class="drive-sidebar-nav">
          <a class="drive-sidebar-nav__item <?= $tab === 'routed' ? 'is-active' : '' ?>" href="<?= e(sidebar_tab_href('routed', $isAdmin, (int)$targetUserId)) ?>">
            <i class="bi bi-folder2-open"></i>
            <span>My Files</span>
          </a>
          <?php if(!$isAdmin): ?>
            <?php if($isDivisionChief): ?>
              <a class="drive-sidebar-nav__item <?= $tab === 'division_queue' ? 'is-active' : '' ?>" href="<?= e(sidebar_tab_href('division_queue', false, (int)$targetUserId)) ?>">
                <i class="bi bi-people"></i>
                <span>Employee Records</span>
              </a>
            <?php endif; ?>
            <a class="drive-sidebar-nav__item <?= $tab === 'shared' ? 'is-active' : '' ?>" href="<?= e(sidebar_tab_href('shared', false, (int)$targetUserId)) ?>">
              <i class="bi bi-send"></i>
              <span>Shared</span>
            </a>
          <?php endif; ?>
          <a class="drive-sidebar-nav__item <?= $tab === 'trash' ? 'is-active' : '' ?>" href="<?= e(sidebar_tab_href('trash', $isAdmin, (int)$targetUserId)) ?>">
            <i class="bi bi-trash3"></i>
            <span>Trash</span>
          </a>
        </nav>
      </div>

      <?php if($canUploadHere || $canCreateFolder): ?>
        <div class="drive-sidebar__section drive-sidebar__section--helper">
          <span class="drive-label">Create & Upload</span>
          <div class="drive-note drive-note--soft">
            Use the <strong>+ New</strong> button in the workspace header for file upload.
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
          <input class="form-control drive-input" name="search" value="<?= e((string)$search) ?>" placeholder="Search routed files">
          <button class="btn btn-primary" type="submit">Apply filters</button>
        </form>
      <?php endif; ?>
    </div>
  </aside>

  <section class="drive-content">
    <?php if($canUploadHere): ?>
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
        <button type="button" class="btn btn-light btn-sm workspace-sidebar-toggle" id="workspace-sidebar-toggle" aria-expanded="true">
          <i class="bi bi-layout-sidebar-inset me-1"></i><span>Hide panel</span>
        </button>
        <div class="section-eyebrow"><?= e($workspaceEyebrow) ?></div>
        <div class="drive-title"><?= e($workspaceTitle) ?></div>
        <div class="drive-subtitle"><?= e($workspaceSubtitle) ?></div>
      </div>
      <div class="drive-actions">
        <?php if($canUploadHere): ?>
          <button class="btn btn-primary btn-sm drive-new-button" type="button" data-bs-toggle="modal" data-bs-target="#workspaceUploadModal">
            <i class="bi bi-upload me-1"></i>Upload
          </button>
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
            <p class="workspace-contents-header__summary">Results for "<?= e($workspaceQuery) ?>" across workspace pages, files, shared records, and notifications.</p>
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

    <div class="admin-drive-main">
      <?php if($canManageLibrary): ?>
        <form id="library-bulk-form" method="POST" action="<?= BASE_URL ?>/documents/manage-selected<?= $isAdmin ? '?user_id='.(int)$targetUserId : '' ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="">
          <input type="hidden" name="source_storage_area" value="OFFICIAL">
        </form>
      <?php endif; ?>
      <?php if($tab === 'division_queue' && !empty($divisionEmployeeFolders)): ?>
        <div class="table-card">
          <div class="table-card__header">
            <div>
              <h2><i class="bi bi-people me-1"></i>Employees and routed files</h2>
              <p>Routed files are grouped by employee so section chiefs can quickly see who uploaded what, where it is, and when activity last happened.</p>
            </div>
          </div>
          <div class="table-card__body">
            <div class="table-responsive">
              <table class="table workspace-table align-middle mb-0">
                <thead>
                  <tr>
                    <th>Employee</th>
                    <th>Routed files</th>
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

      <div class="table-card workspace-file-surface">
        <div class="workspace-file-browser-bar">
          <form class="workspace-file-browser-bar__sort" method="GET" action="<?= BASE_URL ?>/documents">
            <input type="hidden" name="tab" value="<?= e((string)$tab) ?>">
            <?php if($isAdmin): ?>
              <input type="hidden" name="user_id" value="<?= (int)$targetUserId ?>">
            <?php endif; ?>
            <?php if($search !== ''): ?>
              <input type="hidden" name="search" value="<?= e((string)$search) ?>">
            <?php endif; ?>
            <label for="workspace-sort-select">Sort</label>
            <select class="form-select form-select-sm" id="workspace-sort-select" name="sort" onchange="this.form.submit()">
              <option value="modified_desc" <?= $sort === 'modified_desc' ? 'selected' : '' ?>>Date modified: newest</option>
              <option value="modified_asc" <?= $sort === 'modified_asc' ? 'selected' : '' ?>>Date modified: oldest</option>
              <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
              <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
            </select>
          </form>
          <div class="workspace-file-browser-bar__view" role="group" aria-label="View mode">
            <button class="btn btn-light btn-sm js-file-view-toggle" type="button" data-view="grid" aria-pressed="true" title="Grid view">
              <i class="bi bi-grid-3x3-gap"></i>
            </button>
            <button class="btn btn-light btn-sm js-file-view-toggle" type="button" data-view="list" aria-pressed="false" title="List view">
              <i class="bi bi-list-ul"></i>
            </button>
          </div>
        </div>
        <?php if(($canManageTrash && (!empty($trashedFolders) || !empty($docs))) || $canManageLibrary): ?>
        <div class="table-card__header table-card__header--compact">
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
              <div class="workspace-controls__actions">
                <button class="btn btn-outline-secondary btn-sm" type="button" id="library-select-all-records">
                  <i class="bi bi-check2-square me-1"></i>Select all
                </button>
                <button class="btn btn-outline-danger btn-sm" type="button" data-library-action="delete">
                  <i class="bi bi-trash3 me-1"></i>Delete selected
                </button>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
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

          <?php if(empty($docs) && empty($trashedFolders)): ?>
            <div class="table-empty">No records found in this view.</div>
          <?php else: ?>
            <?php if(!empty($docs)): ?>
              <?php if($canManageTrash): ?>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                  <h3 class="h6 mb-0">Deleted files</h3>
                  <span class="text-muted small"><?= count($docs) ?> file<?= count($docs) === 1 ? '' : 's' ?></span>
                </div>
              <?php endif; ?>
            <div class="drive-file-grid drive-file-list" id="workspace-file-grid">
              <?php foreach($docs as $d): ?>
                <?php [$typeClass, $iconClass, $typeLabel] = document_card_type((string)$d['name']); ?>
                <?php $cardPreview = document_card_preview($d); ?>
                <?php $sharedAccepted = $tab !== 'shared' || !empty($d['accepted_at']) || (int)($d['owner_id'] ?? 0) === (int)($_SESSION['user']['id'] ?? 0); ?>
                <?php $canOwnFileActions = $isAdmin || (int)($d['owner_id'] ?? 0) === (int)($_SESSION['user']['id'] ?? 0); ?>
                <?php $hasOutgoingSharedLink = $tab === 'shared' && (string)($d['shared_scope'] ?? '') === 'outgoing'; ?>
                <?php $isCardShareLocked = in_array(strtoupper((string)($d['routing_status'] ?? 'AVAILABLE')), ['PENDING_SHARE_ACCEPTANCE', 'SHARE_ACCEPTED', 'PENDING_REVIEW_ACCEPTANCE', 'IN_REVIEW'], true) || $hasOutgoingSharedLink; ?>
                <?php $canCancelShare = !$canManageTrash && ($canOwnFileActions || $hasOutgoingSharedLink) && $hasOutgoingSharedLink; ?>
                <?php $cardPreview = ['kind' => 'icon']; ?>
                <article class="drive-file-card drive-file-card--list js-selectable-card" data-selectable-id="<?= (int)$d['id'] ?>">
                  <div class="drive-file-card__head">
                    <div class="drive-card-actions">
                      <?php if($canManageTrash): ?>
                        <input class="form-check-input trash-select-item" type="checkbox" name="document_ids[]" value="<?= (int)$d['id'] ?>" form="trash-bulk-delete-form" aria-label="Select file <?= e((string)$d['name']) ?>">
                      <?php elseif($canManageLibrary): ?>
                        <input class="form-check-input library-select-item" type="checkbox" name="document_ids[]" value="<?= (int)$d['id'] ?>" form="library-bulk-form" aria-label="Select file <?= e((string)$d['name']) ?>">
                      <?php endif; ?>
                      <?php if($tab !== 'trash'): ?>
                        <span class="<?= e(workflow_badge_class((string)($d['routing_status'] ?? 'AVAILABLE'), (string)($d['status'] ?? 'Draft'))) ?>">
                          <?= e(workflow_badge_label((string)($d['routing_status'] ?? 'AVAILABLE'), (string)($d['status'] ?? 'Draft'))) ?>
                        </span>
                      <?php endif; ?>
                      <?php if($tab !== 'division_queue'): ?>
                        <div class="dropdown">
                          <button class="btn btn-sm btn-light folder-menu-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="File options">
                            <i class="bi bi-three-dots-vertical"></i>
                          </button>
                          <div class="dropdown-menu dropdown-menu-end folder-menu">
                            <a class="dropdown-item" href="<?= BASE_URL ?>/documents/view?id=<?= (int)$d['id'] ?><?= $isAdmin ? '&user_id='.(int)$targetUserId : '' ?>"><i class="bi bi-box-arrow-up-right me-2"></i>Open</a>
                            <?php if(!$canManageTrash && $canOwnFileActions && !$isCardShareLocked): ?>
                              <button
                                class="dropdown-item"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#workspaceShareFileModal"
                                data-share-id="<?= (int)$d['id'] ?>"
                                data-share-title="<?= e((string)($d['title'] ?? $d['name'])) ?>"
                                data-share-routing="<?= e(routing_status_label((string)($d['routing_status'] ?? 'AVAILABLE'))) ?>"
                                data-share-locked="<?= $isCardShareLocked ? '1' : '0' ?>"
                              ><i class="bi bi-send me-2"></i>Share</button>
                            <?php endif; ?>
                            <?php if($canCancelShare): ?>
                              <button
                                class="dropdown-item text-warning js-cancel-share"
                                type="button"
                                data-document-id="<?= (int)$d['id'] ?>"
                                data-action="<?= BASE_URL ?>/documents/revoke"
                                data-confirm-message="Cancel this current share and return the file to the owner?"
                              ><i class="bi bi-arrow-counterclockwise me-2"></i>Cancel share</button>
                            <?php endif; ?>
                            <?php if(!$canManageTrash && $canOwnFileActions): ?>
                              <button
                                class="dropdown-item"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#workspaceReplaceFileModal"
                              data-replace-id="<?= (int)$d['id'] ?>"
                              data-replace-title="<?= e((string)($d['title'] ?? $d['name'])) ?>"
                              data-replace-name="<?= e((string)$d['name']) ?>"
                              data-replace-code="<?= e((string)($d['document_code'] ?? '')) ?>"
                              data-replace-signatory="<?= e((string)($d['signatory'] ?? '')) ?>"
                              data-replace-priority="<?= e((string)($d['priority_level'] ?? 'NORMAL')) ?>"
                              data-replace-date="<?= e((string)($d['document_date'] ?? '')) ?>"
                              data-replace-category="<?= e((string)($d['category'] ?? '')) ?>"
                              data-replace-tags="<?= e((string)($d['tags'] ?? '')) ?>"
                              data-replace-location="<?= e((string)($d['current_location'] ?? '')) ?>"
                              ><i class="bi bi-arrow-repeat me-2"></i>Replace file</button>
                            <?php endif; ?>
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
                              data-file-storage="Routed"
                              data-file-signatory="<?= e((string)($d['signatory'] ?? 'Not set')) ?>"
                              data-file-activity="<?= $canManageTrash ? e('Deleted ' . trash_timestamp_label((string)($d['deleted_at'] ?? null))) : e(workspace_activity_label((string)($d['last_activity_at'] ?? $d['created_at'] ?? ''))) ?>"
                              data-file-tags="<?= e(trim((string)($d['tags'] ?? '')) !== '' ? (string)$d['tags'] : 'No tags') ?>"
                            ><i class="bi bi-info-circle me-2"></i>Details</button>
                            <a class="dropdown-item" href="<?= BASE_URL ?>/documents/download?id=<?= (int)$d['id'] ?>"><i class="bi bi-download me-2"></i>Download</a>
                            <?php if($canManageTrash): ?>
                              <form method="POST" action="<?= BASE_URL ?>/documents/restore<?= $isAdmin ? '?user_id='.(int)$targetUserId : '' ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button class="dropdown-item" type="submit"><i class="bi bi-arrow-counterclockwise me-2"></i>Restore</button>
                              </form>
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
                            <?php elseif($canOwnFileActions): ?>
                              <button
                                class="dropdown-item text-danger js-delete-document"
                                type="button"
                                data-document-id="<?= (int)$d['id'] ?>"
                                data-action="<?= BASE_URL ?>/documents/delete<?= $isAdmin ? '?user_id='.(int)$targetUserId : '' ?>"
                                data-confirm-message="Move this file to trash?"
                              ><i class="bi bi-trash3 me-2"></i>Move to trash</button>
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
                      <?= e((string)($d['name'] ?? 'Untitled file')) ?>
                    </a>
                    <?php $documentTitle = trim((string)($d['title'] ?? '')); ?>
                    <?php if($documentTitle !== '' && $documentTitle !== (string)($d['name'] ?? '')): ?>
                      <div class="drive-file__meta"><?= e($documentTitle) ?></div>
                    <?php endif; ?>
                    <?php if($tab !== 'shared'): ?>
                      <div class="drive-file__meta">
                        <?= e((string)($d['document_code'] ?? 'Uncoded')) ?> · <?= e(document_direction_label((string)($d['document_type'] ?? 'INCOMING'))) ?>
                      </div>
                    <?php else: ?>
                      <div class="drive-file__meta">
                        Route tracked below
                      </div>
                    <?php endif; ?>
                    <div class="drive-file-card__meta-row">
                      <span><?= $canManageTrash ? 'Deleted ' . e(trash_timestamp_label((string)($d['deleted_at'] ?? null))) : e(workspace_activity_label((string)($d['last_activity_at'] ?? $d['created_at'] ?? ''))) ?></span>
                      <a class="btn btn-sm btn-light" href="<?= BASE_URL ?>/documents/view?id=<?= (int)$d['id'] ?><?= $isAdmin ? '&user_id='.(int)$targetUserId : '' ?>"><?= !$sharedAccepted ? 'Respond' : ($canManageTrash || $tab === 'division_queue' ? 'Review' : 'Open') ?></a>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
            <?php if($tab === 'shared'): ?>
              <section class="shared-route-monitor">
                <div class="shared-route-monitor__header">
                  <div>
                    <h3 class="shared-route-monitor__title">Route monitor</h3>
                    <p class="shared-route-monitor__copy">Check where each shared file went without stretching the shared cards.</p>
                  </div>
                </div>
                <div class="shared-route-monitor__list">
                  <?php foreach($docs as $d): ?>
                    <?php
                      $routeFrom = trim((string)($d['last_route_from'] ?? '')) !== '' ? (string)$d['last_route_from'] : 'Start';
                      $routeTo = trim((string)($d['last_route_to'] ?? '')) !== '' ? (string)$d['last_route_to'] : (string)($d['current_location'] ?? 'Unknown');
                      $routeNote = trim((string)($d['last_route_note'] ?? ''));
                      $routeUpdatedBy = trim((string)($d['last_route_by_name'] ?? '')) !== '' ? (string)$d['last_route_by_name'] : (string)($d['last_touched_by_name'] ?? 'Unknown');
                      $routeUpdatedAt = trim((string)($d['last_route_at'] ?? ''));
                      $showRouteMonitorCancel = (string)($d['shared_scope'] ?? '') === 'outgoing'
                        && in_array(strtoupper((string)($d['routing_status'] ?? 'AVAILABLE')), ['PENDING_SHARE_ACCEPTANCE', 'SHARE_ACCEPTED'], true);
                    ?>
                    <details class="shared-route-item" id="shared-route-item-<?= (int)$d['id'] ?>">
                      <summary class="shared-route-item__summary">
                        <span class="shared-route-item__summary-main">
                          <span class="shared-route-item__file"><?= e((string)($d['name'] ?? 'Untitled file')) ?></span>
                          <span class="shared-route-item__status"><?= e(routing_status_label((string)($d['routing_status'] ?? 'AVAILABLE'))) ?></span>
                        </span>
                        <span class="shared-route-item__summary-route"><?= e($routeFrom) ?> → <?= e($routeTo) ?></span>
                      </summary>
                      <div class="shared-route-item__body">
                        <div class="shared-route-item__grid">
                          <div class="shared-route-item__field">
                            <span class="shared-route-item__label">From</span>
                            <span class="shared-route-item__value"><?= e($routeFrom) ?></span>
                          </div>
                          <div class="shared-route-item__field">
                            <span class="shared-route-item__label">To</span>
                            <span class="shared-route-item__value"><?= e($routeTo) ?></span>
                          </div>
                          <div class="shared-route-item__field">
                            <span class="shared-route-item__label">Routing status</span>
                            <span class="shared-route-item__value"><?= e(routing_status_label((string)($d['routing_status'] ?? 'AVAILABLE'))) ?></span>
                          </div>
                          <div class="shared-route-item__field">
                            <span class="shared-route-item__label">Updated by</span>
                            <span class="shared-route-item__value">
                              <?= e($routeUpdatedBy) ?>
                              <?php if($routeUpdatedAt !== ''): ?>
                                · <?= e(workspace_activity_label($routeUpdatedAt)) ?>
                              <?php endif; ?>
                            </span>
                          </div>
                          <?php if($routeNote !== ''): ?>
                            <div class="shared-route-item__field shared-route-item__field--full">
                              <span class="shared-route-item__label">Route note</span>
                              <span class="shared-route-item__value"><?= e($routeNote) ?></span>
                            </div>
                          <?php endif; ?>
                          <?php if($showRouteMonitorCancel): ?>
                            <div class="shared-route-item__field shared-route-item__field--full">
                              <span class="shared-route-item__label">Owner action</span>
                              <div>
                                <button
                                  class="btn btn-sm btn-outline-warning js-cancel-share"
                                  type="button"
                                  data-document-id="<?= (int)$d['id'] ?>"
                                  data-action="<?= BASE_URL ?>/documents/revoke"
                                  data-confirm-message="Cancel this current share and return the file to the owner?"
                                ><i class="bi bi-arrow-counterclockwise me-2"></i>Cancel share</button>
                              </div>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </details>
                  <?php endforeach; ?>
                </div>
              </section>
            <?php endif; ?>
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
    const fileGrid = document.getElementById('workspace-file-grid');
    const viewButtons = Array.from(document.querySelectorAll('.js-file-view-toggle'));
    const viewStorageKey = 'wdms_file_view_mode';
    const applyViewMode = function (mode) {
      if (!fileGrid) return;
      const nextMode = mode === 'list' ? 'list' : 'grid';
      fileGrid.classList.toggle('is-list-view', nextMode === 'list');
      viewButtons.forEach(function (button) {
        const active = button.getAttribute('data-view') === nextMode;
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      window.localStorage.setItem(viewStorageKey, nextMode);
    };
    if (fileGrid && viewButtons.length) {
      applyViewMode(window.localStorage.getItem(viewStorageKey) || 'grid');
      viewButtons.forEach(function (button) {
        button.addEventListener('click', function () {
          applyViewMode(button.getAttribute('data-view') || 'grid');
        });
      });
    }

    if (fileGrid) {
      const cards = Array.from(fileGrid.querySelectorAll('.js-selectable-card'));
      let marquee = null;
      let startX = 0;
      let startY = 0;
      let dragging = false;
      const setCardSelection = function (card, selected) {
        const checkbox = card.querySelector('.library-select-item, .trash-select-item');
        if (!checkbox) return;
        checkbox.checked = selected;
        card.classList.toggle('is-selected', selected);
      };
      const syncSelectionVisuals = function () {
        cards.forEach(function (card) {
          const checkbox = card.querySelector('.library-select-item, .trash-select-item');
          card.classList.toggle('is-selected', !!(checkbox && checkbox.checked));
        });
      };
      syncSelectionVisuals();
      fileGrid.addEventListener('change', function (event) {
        if (event.target && event.target.matches('.library-select-item, .trash-select-item')) {
          syncSelectionVisuals();
        }
      });
      fileGrid.addEventListener('mousedown', function (event) {
        if (event.button !== 0) return;
        const interactive = event.target.closest('a, button, input, label, .dropdown, .folder-menu, .drive-file-card__preview-link');
        if (interactive) return;
        const rect = fileGrid.getBoundingClientRect();
        startX = event.clientX - rect.left + fileGrid.scrollLeft;
        startY = event.clientY - rect.top + fileGrid.scrollTop;
        dragging = true;
        marquee = document.createElement('div');
        marquee.className = 'workspace-selection-marquee';
        marquee.style.left = startX + 'px';
        marquee.style.top = startY + 'px';
        marquee.style.width = '0px';
        marquee.style.height = '0px';
        fileGrid.appendChild(marquee);
        cards.forEach(function (card) { setCardSelection(card, false); });
      });
      window.addEventListener('mousemove', function (event) {
        if (!dragging || !marquee) return;
        const rect = fileGrid.getBoundingClientRect();
        const currentX = event.clientX - rect.left + fileGrid.scrollLeft;
        const currentY = event.clientY - rect.top + fileGrid.scrollTop;
        const left = Math.min(startX, currentX);
        const top = Math.min(startY, currentY);
        const width = Math.abs(currentX - startX);
        const height = Math.abs(currentY - startY);
        marquee.style.left = left + 'px';
        marquee.style.top = top + 'px';
        marquee.style.width = width + 'px';
        marquee.style.height = height + 'px';
        const selectionRect = { left: left, right: left + width, top: top, bottom: top + height };
        cards.forEach(function (card) {
          const cardRect = card.getBoundingClientRect();
          const gridRect = fileGrid.getBoundingClientRect();
          const relativeRect = {
            left: cardRect.left - gridRect.left + fileGrid.scrollLeft,
            right: cardRect.right - gridRect.left + fileGrid.scrollLeft,
            top: cardRect.top - gridRect.top + fileGrid.scrollTop,
            bottom: cardRect.bottom - gridRect.top + fileGrid.scrollTop
          };
          const intersects = !(
            selectionRect.right < relativeRect.left ||
            selectionRect.left > relativeRect.right ||
            selectionRect.bottom < relativeRect.top ||
            selectionRect.top > relativeRect.bottom
          );
          setCardSelection(card, intersects);
        });
      });
      window.addEventListener('mouseup', function () {
        if (!dragging) return;
        dragging = false;
        if (marquee && marquee.parentNode) {
          marquee.parentNode.removeChild(marquee);
        }
        marquee = null;
        syncSelectionVisuals();
      });
    }

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
          : 'This older storage move is no longer used.';

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

    const submitWorkspaceActionForm = function (action, values) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = action;

      const csrf = document.createElement('input');
      csrf.type = 'hidden';
      csrf.name = '_csrf';
      csrf.value = '<?= e(csrf_token()) ?>';
      form.appendChild(csrf);

      Object.keys(values).forEach(function (key) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = String(values[key] == null ? '' : values[key]);
        form.appendChild(input);
      });

      document.body.appendChild(form);
      form.submit();
    };

    document.querySelectorAll('.js-delete-document').forEach(function (button) {
      button.addEventListener('click', async function () {
        const docId = button.getAttribute('data-document-id') || '';
        const action = button.getAttribute('data-action') || '';
        const message = button.getAttribute('data-confirm-message') || 'Move this file to trash?';
        if (!docId || !action) return;

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

        submitWorkspaceActionForm(action, { id: docId });
      });
    });

    document.querySelectorAll('.js-delete-folder').forEach(function (button) {
      button.addEventListener('click', async function () {
        const folderId = button.getAttribute('data-folder-id') || '';
        const storageArea = button.getAttribute('data-storage-area') || '';
        const action = button.getAttribute('data-action') || '';
        const message = button.getAttribute('data-confirm-message') || 'Move this folder and all nested files to trash?';
        if (!folderId || !action) return;

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

        submitWorkspaceActionForm(action, {
          id: folderId,
          storage_area: storageArea
        });
      });
    });

    document.querySelectorAll('.js-cancel-share').forEach(function (button) {
      button.addEventListener('click', async function () {
        const docId = button.getAttribute('data-document-id') || '';
        const action = button.getAttribute('data-action') || '';
        const message = button.getAttribute('data-confirm-message') || 'Cancel this current share?';
        if (!docId || !action) return;

        let ok = true;
        if (typeof window.wdmsConfirmModal === 'function') {
          const result = await window.wdmsConfirmModal({
            title: 'Cancel share',
            message: message,
            confirmText: 'Cancel share'
          });
          ok = !!result.ok;
        } else {
          ok = window.confirm(message);
        }
        if (!ok) return;

        submitWorkspaceActionForm(action, { document_id: docId });
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
    const fileTrigger = document.getElementById('workspace-upload-file-trigger');
    const selectionLabel = document.getElementById('workspace-upload-selection');
    const selectionState = document.getElementById('workspace-upload-selection-state');
    const selectionBadge = document.getElementById('workspace-upload-selection-badge');
    const selectionList = document.getElementById('workspace-upload-selection-list');
    const uploadPanel = document.getElementById('workspace-upload-panel');
    const uploadTitle = document.getElementById('workspace-upload-title');
    const uploadSummary = document.getElementById('workspace-upload-summary');
    const uploadPercent = document.getElementById('workspace-upload-percent');
    const uploadProgressBar = document.getElementById('workspace-upload-progress-bar');
    const uploadList = document.getElementById('workspace-upload-list');
    const uploadClose = document.getElementById('workspace-upload-close');
    const uploadModalElement = document.getElementById('workspaceUploadModal');
    const conflictInput = form.querySelector('input[name="on_conflict"]');
    const uploadButton = form.querySelector('button[type="submit"]');
    const existing = <?= json_encode(array_keys($existingNameMap), JSON_UNESCAPED_SLASHES) ?>;
    const existingSet = new Set(existing);
    const uploadQueueStorageKey = 'wdms_upload_queue_state';
    let activeRequest = null;
    let uploadQueueHidden = false;
    let pendingEntries = [];

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

    function renderSelectionState(summary, items, isSelected) {
      if (selectionLabel) {
        selectionLabel.textContent = summary;
      }
      if (selectionBadge) {
        selectionBadge.textContent = isSelected ? 'Selected' : 'Waiting';
      }
      if (selectionState) {
        selectionState.dataset.empty = isSelected ? '0' : '1';
      }
      if (!selectionList) return;
      if (items && items.length) {
        selectionList.innerHTML = items.map(function (item) {
          return '<li>' + escapeHtml(item) + '</li>';
        }).join('');
        selectionList.classList.remove('d-none');
      } else {
        selectionList.innerHTML = '';
        selectionList.classList.add('d-none');
      }
    }

    function updateSelectionLabel() {
      if (!selectionLabel) return;
      if (pendingEntries.length) {
        if (pendingEntries.length === 1) {
          renderSelectionState(
            pendingEntries[0].file.name + ' ready to upload',
            [pendingEntries[0].file.name + ' • ' + formatBytes(pendingEntries[0].file.size || 0)],
            true
          );
          return;
        }
        const previewItems = pendingEntries.slice(0, 3).map(function (entry) {
          const displayName = String(entry.relativePath || '').trim() || entry.file.name;
          return displayName + ' • ' + formatBytes(entry.file.size || 0);
        });
        if (pendingEntries.length > 3) {
          previewItems.push('+' + (pendingEntries.length - 3) + ' more item(s)');
        }
        renderSelectionState(
          pendingEntries.length + ' dropped file(s) ready to upload',
          previewItems,
          true
        );
        return;
      }
      const files = fileInput && fileInput.files ? Array.from(fileInput.files) : [];
      if (files.length === 1) {
        renderSelectionState(
          files[0].name + ' ready to upload',
          [files[0].name + ' • ' + formatBytes(files[0].size || 0)],
          true
        );
        return;
      }
      if (files.length > 1) {
        const previewItems = files.slice(0, 3).map(function (file) {
          return file.name + ' • ' + formatBytes(file.size || 0);
        });
        if (files.length > 3) {
          previewItems.push('+' + (files.length - 3) + ' more item(s)');
        }
        renderSelectionState(files.length + ' files selected', previewItems, true);
        return;
      }
      renderSelectionState('No file selected yet.', [], false);
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

    function clearSelectedInputs() {
      if (fileInput) fileInput.value = '';
    }

    function setPendingEntries(entries) {
      pendingEntries = Array.isArray(entries) ? entries.slice() : [];
      clearSelectedInputs();
      clearRelativePathInputs();
      pendingEntries.forEach(function (entry) {
        appendRelativePath('file_relative_paths[]', entry.relativePath || '');
      });
      updateSelectionLabel();
    }

    function clearPendingEntries() {
      pendingEntries = [];
      clearRelativePathInputs();
      updateSelectionLabel();
    }

    function openUploadModal() {
      if (!uploadModalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return;
      }
      bootstrap.Modal.getOrCreateInstance(uploadModalElement).show();
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
      pendingEntries = [];
      const files = fileInput && fileInput.files ? Array.from(fileInput.files) : [];
      files.forEach(function () {
        appendRelativePath('file_relative_paths[]', '');
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
      const combined = selectedEntriesFromInputs().map(function (entry) { return entry.file; });
      const ok = await resolveConflict(combined);
      if (!ok) {
        clearSelectedInputs();
        clearPendingEntries();
        conflictInput.value = '';
        updateSelectionLabel();
        return false;
      }
      if (!pendingEntries.length) {
        syncRelativePathInputs();
      }
      updateSelectionLabel();
      return ok;
    }

    function selectedEntriesFromInputs() {
      if (pendingEntries.length) {
        return pendingEntries.slice();
      }
      const entries = [];
      const files = fileInput && fileInput.files ? Array.from(fileInput.files) : [];
      files.forEach(function (file) {
        entries.push({ file: file, relativePath: '' });
      });
      return entries;
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
        if (!name || ['file[]', 'file_relative_paths[]', '_csrf', 'folder_id', 'storage_area', 'on_conflict'].indexOf(name) !== -1) {
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

    if (fileInput) {
      fileInput.addEventListener('change', function () {
        pendingEntries = [];
        conflictInput.value = '';
        syncRelativePathInputs();
        window.setTimeout(updateSelectionLabel, 0);
      });
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (form.dataset.uploadBusy === '1') return;
      handleSelection().then(function (ok) {
        if (!ok) return;
        const entries = selectedEntriesFromInputs();
        if (!entries.length) return;
        if (pendingEntries.length) {
          sendUpload(entries);
          return;
        }
        setUploadBusy(true);
        form.submit();
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
      fileTrigger.addEventListener('click', function (event) {
        event.stopPropagation();
        clearPendingEntries();
        fileInput.value = '';
        updateSelectionLabel();
      });
    }
    if (dropzone && fileInput) {
      dropzone.addEventListener('click', function (event) {
        const interactiveTarget = event.target && event.target.closest
          ? event.target.closest('label, button, input, a, .workspace-upload-modal__selection-state')
          : null;
        if (interactiveTarget) return;
        fileInput.click();
      });
    }
  })();
</script>
<script>
  (function () {
    const uploadModalElement = document.getElementById('workspaceUploadModal');
    const uploadModalLabel = document.getElementById('workspaceUploadModalLabel');
    const uploadModalIntro = document.getElementById('workspaceUploadModalIntro');
    if (!uploadModalElement) {
      return;
    }
    uploadModalElement.addEventListener('show.bs.modal', function () {
      if (uploadModalLabel) {
        uploadModalLabel.textContent = 'Upload files';
      }
      const selectionLabel = document.getElementById('workspace-upload-selection');
      if (uploadModalIntro) {
        const selectionText = selectionLabel ? String(selectionLabel.textContent || '').trim() : '';
        uploadModalIntro.textContent = selectionText && selectionText !== 'No file selected yet.'
          ? 'These files are ready to upload. Review them below, then complete the tracking details.'
          : 'Choose one or more files with the file picker, then fill in the tracking details before upload.';
      }
      window.setTimeout(function () {
        const fileTrigger = document.getElementById('workspace-upload-file-trigger');
        if (fileTrigger) fileTrigger.focus();
      }, 120);
    });
  })();
</script>
<script>
  (function () {
    window.wdmsUploadPreviewSync = function () {
      const fileInput = document.getElementById('upload-file-input');
      const selectionState = document.getElementById('workspace-upload-selection-state');
      const selectionBadge = document.getElementById('workspace-upload-selection-badge');
      const selectionLabel = document.getElementById('workspace-upload-selection');
      const selectionList = document.getElementById('workspace-upload-selection-list');

      if (!fileInput || !selectionLabel) {
        return;
      }

      const formatBytes = function (bytes) {
        const value = Math.max(0, Number(bytes) || 0);
        if (value < 1024) return value.toFixed(0) + ' B';
        if (value < 1024 * 1024) return (value / 1024).toFixed(1) + ' KB';
        if (value < 1024 * 1024 * 1024) return (value / (1024 * 1024)).toFixed(2) + ' MB';
        return (value / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
      };

      const escapeHtml = function (value) {
        return String(value == null ? '' : value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      };

      const render = function (summary, items, isSelected) {
        selectionLabel.textContent = summary;
        if (selectionBadge) selectionBadge.textContent = isSelected ? 'Selected' : 'Waiting';
        if (selectionState) selectionState.dataset.empty = isSelected ? '0' : '1';
        if (!selectionList) return;
        if (items.length) {
          selectionList.innerHTML = items.map(function (item) {
            return '<li>' + escapeHtml(item) + '</li>';
          }).join('');
          selectionList.classList.remove('d-none');
        } else {
          selectionList.innerHTML = '';
          selectionList.classList.add('d-none');
        }
      };

      const files = fileInput.files ? Array.from(fileInput.files) : [];

      if (files.length === 1) {
        render(files[0].name + ' ready to upload', [files[0].name + ' • ' + formatBytes(files[0].size || 0)], true);
        return;
      }

      if (files.length > 1) {
        const items = files.slice(0, 3).map(function (file) {
          return file.name + ' • ' + formatBytes(file.size || 0);
        });
        if (files.length > 3) {
          items.push('+' + (files.length - 3) + ' more item(s)');
        }
        render(files.length + ' files selected', items, true);
        return;
      }

      render('No file selected yet.', [], false);
    };

    const fileInput = document.getElementById('upload-file-input');
    const selectionState = document.getElementById('workspace-upload-selection-state');
    const selectionBadge = document.getElementById('workspace-upload-selection-badge');
    const selectionLabel = document.getElementById('workspace-upload-selection');
    const selectionList = document.getElementById('workspace-upload-selection-list');
    const uploadModalElement = document.getElementById('workspaceUploadModal');

    if (!fileInput || !selectionLabel) {
      return;
    }

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

    function render(summary, items, isSelected) {
      selectionLabel.textContent = summary;
      if (selectionBadge) selectionBadge.textContent = isSelected ? 'Selected' : 'Waiting';
      if (selectionState) selectionState.dataset.empty = isSelected ? '0' : '1';
      if (!selectionList) return;
      if (items.length) {
        selectionList.innerHTML = items.map(function (item) {
          return '<li>' + escapeHtml(item) + '</li>';
        }).join('');
        selectionList.classList.remove('d-none');
      } else {
        selectionList.innerHTML = '';
        selectionList.classList.add('d-none');
      }
    }

    fileInput.addEventListener('change', function () {
      window.setTimeout(window.wdmsUploadPreviewSync, 0);
    });
    window.addEventListener('focus', function () {
      window.setTimeout(window.wdmsUploadPreviewSync, 0);
    });
    if (uploadModalElement) {
      uploadModalElement.addEventListener('shown.bs.modal', function () {
        window.setTimeout(window.wdmsUploadPreviewSync, 0);
      });
    }
    window.wdmsUploadPreviewSync();
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
    const bindFileDetailsModal = function () {
      const modal = document.getElementById('workspaceFileDetailsModal');
      if (!modal || modal.dataset.bound === '1') return;
      modal.dataset.bound = '1';
      const fields = ['title', 'code', 'direction', 'location', 'routing', 'priority', 'category', 'owner', 'storage', 'signatory', 'activity', 'tags'];
      const applyDetails = function (source) {
        if (!source) return;
        fields.forEach(function (field) {
          const target = modal.querySelector('[data-detail-field="' + field + '"]');
          if (!target) return;
          target.textContent = source.getAttribute('data-file-' + field) || 'Not set';
        });
      };
      document.querySelectorAll('[data-bs-target="#workspaceFileDetailsModal"]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
          applyDetails(trigger);
        });
      });
      modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        applyDetails(trigger);
      });
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', bindFileDetailsModal, { once: true });
    } else {
      bindFileDetailsModal();
    }
  })();
</script>
<script>
  (function () {
    const modal = document.getElementById('workspaceReplaceFileModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
      const trigger = event.relatedTarget;
      if (!trigger) return;
      const form = modal.querySelector('#workspace-replace-file-form');
      const idInput = form ? form.querySelector('input[name="id"]') : null;
      const locationInput = form ? form.querySelector('input[name="current_location"]') : null;
      const fileInput = form ? form.querySelector('input[name="file"]') : null;
      if (idInput) {
        idInput.value = trigger.getAttribute('data-replace-id') || '';
      }
      if (locationInput) {
        locationInput.value = trigger.getAttribute('data-replace-location') || '';
      }
      if (fileInput) {
        fileInput.value = '';
      }
      const fieldMap = {
        document_code: 'data-replace-code',
        title: 'data-replace-title',
        signatory: 'data-replace-signatory',
        priority_level: 'data-replace-priority',
        document_date: 'data-replace-date',
        category: 'data-replace-category',
        tags: 'data-replace-tags'
      };
      Object.keys(fieldMap).forEach(function (fieldName) {
        const input = form ? form.querySelector('[name="' + fieldName + '"]') : null;
        if (!input) return;
        input.value = trigger.getAttribute(fieldMap[fieldName]) || '';
      });
      const titleTarget = modal.querySelector('[data-replace-field="title"]');
      const nameTarget = modal.querySelector('[data-replace-field="name"]');
      if (titleTarget) {
        titleTarget.textContent = trigger.getAttribute('data-replace-title') || 'Selected file';
      }
      if (nameTarget) {
        const filename = trigger.getAttribute('data-replace-name') || 'Choose the correct replacement file.';
        nameTarget.textContent = 'Current file: ' + filename;
      }
    });
  })();
</script>
<script>
  (function () {
    const boot = function () {
    const createShareCombobox = function (config) {
      const searchInput = document.getElementById(config.searchInputId);
      const recipientSelect = document.getElementById(config.selectId);
      const panel = document.getElementById(config.panelId);
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
        panel.classList.remove('d-none');
      };
      panel.addEventListener('mousedown', function (event) {
        event.preventDefault();
      });
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
    const bindShareModal = function (config) {
      const modal = document.getElementById(config.modalId);
      const form = document.getElementById(config.formId);
      if (!modal || !form) return;

      const shareCombobox = createShareCombobox({
        searchInputId: config.searchInputId,
        selectId: config.selectId,
        panelId: config.panelId,
      });

      modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        shareCombobox.reset();
        if (typeof config.onShow === 'function') {
          config.onShow({
            modal: modal,
            form: form,
            trigger: trigger,
          });
        }
      });

      modal.addEventListener('shown.bs.modal', function () {
        const searchInput = document.getElementById(config.searchInputId);
        const submitButton = config.submitSelector ? modal.querySelector(config.submitSelector) : null;
        if (searchInput && !(submitButton && submitButton.disabled)) {
          searchInput.focus();
          shareCombobox.open();
        }
      });
    };

    bindShareModal({
      modalId: 'workspaceShareFileModal',
      formId: 'workspace-share-file-form',
      searchInputId: 'workspace-share-recipient-search',
      selectId: 'workspace-share-recipient-select',
      panelId: 'workspace-share-recipient-panel',
      submitSelector: '[data-share-field="submit"]',
      onShow: function (context) {
        const trigger = context.trigger;
        const modal = context.modal;
        const form = context.form;
        const titleTarget = modal.querySelector('[data-share-field="title"]');
        const routingTarget = modal.querySelector('[data-share-field="routing"]');
        const lockedNote = modal.querySelector('[data-share-field="locked-note"]');
        const fieldsWrap = modal.querySelector('[data-share-field="form-fields"]');
        const submitButton = modal.querySelector('[data-share-field="submit"]');
        const docIdInput = form.querySelector('input[name="document_id"]');
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
      },
    });

    bindShareModal({
      modalId: 'workspaceShareFolderModal',
      formId: 'workspace-share-folder-form',
      searchInputId: 'workspace-share-folder-recipient-search',
      selectId: 'workspace-share-folder-recipient-select',
      panelId: 'workspace-share-folder-recipient-panel',
      onShow: function (context) {
        const trigger = context.trigger;
        const modal = context.modal;
        const form = context.form;
        const titleTarget = modal.querySelector('[data-share-folder-field="title"]');
        const folderIdInput = form.querySelector('input[name="folder_id"]');
        const folderId = trigger ? (trigger.getAttribute('data-share-folder-id') || '') : '';
        const folderName = trigger ? (trigger.getAttribute('data-share-folder-name') || 'Selected folder') : 'Selected folder';
        if (folderIdInput) folderIdInput.value = folderId;
        if (titleTarget) titleTarget.textContent = folderName;
      },
    });
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
      boot();
    }
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
            <option value="AVAILABLE" <?= ($filters['routing_status'] ?? '') === 'AVAILABLE' ? 'selected' : '' ?>>Available with owner</option>
            <option value="PENDING_SHARE_ACCEPTANCE" <?= ($filters['routing_status'] ?? '') === 'PENDING_SHARE_ACCEPTANCE' ? 'selected' : '' ?>>Pending recipient acceptance</option>
            <option value="SHARE_ACCEPTED" <?= ($filters['routing_status'] ?? '') === 'SHARE_ACCEPTED' ? 'selected' : '' ?>>In recipient custody</option>
            <option value="SHARE_DECLINED" <?= ($filters['routing_status'] ?? '') === 'SHARE_DECLINED' ? 'selected' : '' ?>>Returned to owner</option>
            <option value="PENDING_REVIEW_ACCEPTANCE" <?= ($filters['routing_status'] ?? '') === 'PENDING_REVIEW_ACCEPTANCE' ? 'selected' : '' ?>>Pending section chief acceptance</option>
            <option value="IN_REVIEW" <?= ($filters['routing_status'] ?? '') === 'IN_REVIEW' ? 'selected' : '' ?>>In section chief review</option>
            <option value="REVIEW_ASSIGNMENT_DECLINED" <?= ($filters['routing_status'] ?? '') === 'REVIEW_ASSIGNMENT_DECLINED' ? 'selected' : '' ?>>Returned to owner</option>
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

<?php if($canUploadHere): ?>
<div class="modal fade workspace-upload-modal" id="workspaceUploadModal" tabindex="-1" aria-labelledby="workspaceUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl workspace-upload-modal__dialog">
    <div class="modal-content workspace-file-details-modal__content">
      <form id="workspace-upload-form" method="POST" action="<?= BASE_URL ?>/documents/upload" enctype="multipart/form-data" class="drive-form-stack drive-form-stack--upload workspace-upload-modal__form">
        <?= csrf_field() ?>
        <input type="hidden" name="folder_id" value="<?= (int)$folder ?>">
        <input type="hidden" name="storage_area" value="OFFICIAL">
        <input type="hidden" name="on_conflict" value="">
        <div class="modal-header border-0 pb-0">
          <div>
            <h5 class="modal-title" id="workspaceUploadModalLabel">Upload files</h5>
            <p class="workspace-filter-modal__intro mb-0" id="workspaceUploadModalIntro">Choose one or more files, then fill in the tracking details before upload.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-3">
          <section class="workspace-upload-modal__section">
            <div class="workspace-upload-modal__section-head">
              <span class="workspace-upload-modal__eyebrow">Source</span>
              <strong>Select files</strong>
              <small>Pick one or more files using the file picker below.</small>
            </div>
            <div class="drive-note drive-note--soft workspace-upload-modal__dropzone" id="upload-dropzone" style="cursor: pointer;">
              <div class="workspace-upload-modal__dropzone-copy">
                <i class="bi bi-file-earmark-arrow-up me-1"></i>Click here to choose files.
              </div>
              <div class="workspace-upload-modal__chooser">
                <label class="btn btn-outline-primary" for="upload-file-input" id="workspace-upload-file-trigger">
                  <i class="bi bi-file-earmark-arrow-up me-1"></i>Choose files
                </label>
              </div>
              <div class="workspace-upload-modal__selection-state" id="workspace-upload-selection-state" data-empty="1">
                <div class="workspace-upload-modal__selection-head">
                  <span class="workspace-upload-modal__selection-badge" id="workspace-upload-selection-badge">Waiting</span>
                  <strong class="workspace-upload-modal__selection" id="workspace-upload-selection">No file selected yet.</strong>
                </div>
                <ul class="workspace-upload-modal__selection-list d-none" id="workspace-upload-selection-list"></ul>
              </div>
            </div>
          </section>
          <input class="d-none" id="upload-file-input" type="file" name="file[]" multiple onchange="window.wdmsUploadPreviewSync && window.wdmsUploadPreviewSync()">
          <section class="workspace-upload-modal__section workspace-upload-modal__section--profile">
            <div class="workspace-upload-modal__section-head">
              <span class="workspace-upload-modal__eyebrow">Tracking</span>
              <strong>Document details</strong>
              <small>Single-file uploads keep the exact Doc. ID. Batch uploads reuse this profile and add numbered Doc. ID suffixes automatically.</small>
            </div>
            <div class="workspace-upload-modal__grid">
              <label class="workspace-upload-modal__field">
                <span>Doc. ID</span>
                <input class="form-control drive-input" name="document_code" placeholder="Enter document ID" required>
              </label>
              <label class="workspace-upload-modal__field">
                <span>Document title</span>
                <input class="form-control drive-input" name="title" placeholder="Enter document title">
              </label>
              <label class="workspace-upload-modal__field">
                <span>Signatory</span>
                <input class="form-control drive-input" name="signatory" placeholder="Enter signatory" required>
              </label>
              <label class="workspace-upload-modal__field">
                <span>Priority</span>
                <span class="workspace-upload-modal__control-shell workspace-upload-modal__control-shell--select">
                  <select class="form-select drive-input workspace-upload-modal__select" name="priority_level">
                    <option value="NORMAL">Normal</option>
                    <option value="LOW">Low</option>
                    <option value="HIGH">High</option>
                    <option value="URGENT">Urgent</option>
                  </select>
                  <i class="bi bi-chevron-down workspace-upload-modal__control-icon" aria-hidden="true"></i>
                </span>
              </label>
              <label class="workspace-upload-modal__field">
                <span>Document date</span>
                <span class="workspace-upload-modal__control-shell workspace-upload-modal__control-shell--date">
                  <input class="form-control drive-input workspace-upload-modal__date" type="date" name="document_date" value="<?= e(date('Y-m-d')) ?>" required>
                  <i class="bi bi-calendar3 workspace-upload-modal__control-icon workspace-upload-modal__control-icon--date" aria-hidden="true"></i>
                </span>
              </label>
              <label class="workspace-upload-modal__field">
                <span>Category</span>
                <input class="form-control drive-input" name="category" placeholder="Enter category" required>
              </label>
              <label class="workspace-upload-modal__field">
                <span>Tags</span>
                <input class="form-control drive-input" name="tags" placeholder="Add tags">
              </label>
            </div>
          </section>
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

<div class="modal fade workspace-file-details-modal" id="workspaceReplaceFileModal" tabindex="-1" aria-labelledby="workspaceReplaceFileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content workspace-file-details-modal__content">
      <form id="workspace-replace-file-form" method="POST" action="<?= BASE_URL ?>/documents/replace" enctype="multipart/form-data" class="drive-form-stack">
        <?= csrf_field() ?>
        <div class="modal-header border-0 pb-0">
          <div>
            <h5 class="modal-title" id="workspaceReplaceFileModalLabel">Replace file</h5>
            <p class="workspace-filter-modal__intro mb-0">Upload the correct file to replace the current one without losing its record history.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-3">
          <input type="hidden" name="id" value="">
          <input type="hidden" name="current_location" value="">
          <div class="workspace-upload-modal__section workspace-upload-modal__section--profile">
            <div class="workspace-upload-modal__section-head">
              <span class="workspace-upload-modal__eyebrow">Replace</span>
              <strong data-replace-field="title">Selected file</strong>
              <small data-replace-field="name">Choose the correct replacement file.</small>
            </div>
            <div class="workspace-upload-modal__grid">
              <label class="workspace-upload-modal__field">
                <span>Doc. ID</span>
                <input class="form-control drive-input" name="document_code" placeholder="Enter document ID" required>
              </label>
              <label class="workspace-upload-modal__field">
                <span>Document title</span>
                <input class="form-control drive-input" name="title" placeholder="Enter document title">
              </label>
              <label class="workspace-upload-modal__field">
                <span>Signatory</span>
                <input class="form-control drive-input" name="signatory" placeholder="Enter signatory" required>
              </label>
              <label class="workspace-upload-modal__field">
                <span>Priority</span>
                <span class="workspace-upload-modal__control-shell workspace-upload-modal__control-shell--select">
                  <select class="form-select drive-input workspace-upload-modal__select" name="priority_level">
                    <option value="NORMAL">Normal</option>
                    <option value="LOW">Low</option>
                    <option value="HIGH">High</option>
                    <option value="URGENT">Urgent</option>
                  </select>
                  <i class="bi bi-chevron-down workspace-upload-modal__control-icon" aria-hidden="true"></i>
                </span>
              </label>
              <label class="workspace-upload-modal__field">
                <span>Document date</span>
                <span class="workspace-upload-modal__control-shell workspace-upload-modal__control-shell--date">
                  <input class="form-control drive-input workspace-upload-modal__date" type="date" name="document_date" value="<?= e(date('Y-m-d')) ?>" required>
                  <i class="bi bi-calendar3 workspace-upload-modal__control-icon workspace-upload-modal__control-icon--date" aria-hidden="true"></i>
                </span>
              </label>
              <label class="workspace-upload-modal__field">
                <span>Category</span>
                <input class="form-control drive-input" name="category" placeholder="Enter category" required>
              </label>
              <label class="workspace-upload-modal__field">
                <span>Tags</span>
                <input class="form-control drive-input" name="tags" placeholder="Add tags">
              </label>
              <label class="workspace-upload-modal__field workspace-upload-modal__field--wide">
                <span>New file</span>
                <input class="form-control drive-input" type="file" name="file" required>
              </label>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button class="btn drive-secondary" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Replace file</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade workspace-file-details-modal" id="workspaceShareFileModal" tabindex="-1" aria-labelledby="workspaceShareFileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content workspace-file-details-modal__content">
      <form id="workspace-share-file-form" method="POST" action="<?= BASE_URL ?>/documents/share" class="drive-form-stack">
        <?= csrf_field() ?>
        <div class="modal-header border-0 pb-0">
          <div>
            <h5 class="modal-title" id="workspaceShareFileModalLabel">Route and share file</h5>
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
            <div class="share-combobox mb-2" id="workspace-share-recipient-combobox">
              <input class="form-control drive-input share-combobox__input" type="search" id="workspace-share-recipient-search" placeholder="Select user in this division" autocomplete="off">
              <div class="share-combobox__panel d-none" id="workspace-share-recipient-panel">
                <?php foreach($shareRecipientGroups as $group): ?>
                  <?php foreach($group['items'] as $recipient): ?>
                    <?php $recipientRole = $shareRoleLabels[strtoupper((string)($recipient['role'] ?? 'EMPLOYEE'))] ?? 'User'; ?>
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
            <select class="form-select drive-input mb-2 d-none" name="target_user_id" id="workspace-share-recipient-select" required>
              <option value="">Select user in this division</option>
              <?php foreach($shareRecipientGroups as $group): ?>
                <optgroup label="<?= e($group['division_name']) ?> | Chief: <?= e($group['chief_name']) ?>">
                  <?php foreach($group['items'] as $recipient): ?>
                    <?php $recipientRole = $shareRoleLabels[strtoupper((string)($recipient['role'] ?? 'EMPLOYEE'))] ?? 'User'; ?>
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

<div class="modal fade workspace-file-details-modal" id="workspaceShareFolderModal" tabindex="-1" aria-labelledby="workspaceShareFolderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content workspace-file-details-modal__content">
      <form id="workspace-share-folder-form" method="POST" action="<?= BASE_URL ?>/folders/share<?= $isAdmin ? '?user_id='.(int)$targetUserId : '' ?>" class="drive-form-stack">
        <?= csrf_field() ?>
        <div class="modal-header border-0 pb-0">
          <div>
            <h5 class="modal-title" id="workspaceShareFolderModalLabel">Route and share folder</h5>
            <p class="workspace-filter-modal__intro mb-0" data-share-folder-field="title">Choose one person in this division to receive the routed folder.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-3">
          <input type="hidden" name="folder_id" value="">
          <div class="drive-note drive-note--soft mb-3">
            All active files inside this folder will be routed together using the same one-to-one sharing rule.
          </div>
          <div class="share-combobox mb-2" id="workspace-share-folder-recipient-combobox">
            <input class="form-control drive-input share-combobox__input" type="search" id="workspace-share-folder-recipient-search" placeholder="Select user in this division" autocomplete="off">
            <div class="share-combobox__panel d-none" id="workspace-share-folder-recipient-panel">
              <?php foreach($shareRecipientGroups as $group): ?>
                <?php foreach($group['items'] as $recipient): ?>
                  <?php $recipientRole = $shareRoleLabels[strtoupper((string)($recipient['role'] ?? 'EMPLOYEE'))] ?? 'User'; ?>
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
          <select class="form-select drive-input mb-2 d-none" name="target_user_id" id="workspace-share-folder-recipient-select" required>
            <option value="">Select user in this division</option>
            <?php foreach($shareRecipientGroups as $group): ?>
              <optgroup label="<?= e($group['division_name']) ?> | Chief: <?= e($group['chief_name']) ?>">
                <?php foreach($group['items'] as $recipient): ?>
                  <?php $recipientRole = $shareRoleLabels[strtoupper((string)($recipient['role'] ?? 'EMPLOYEE'))] ?? 'User'; ?>
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
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-outline-primary" type="submit">Share</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . "/../layouts/footer.php"; ?>

