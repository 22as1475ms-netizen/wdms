<?php require __DIR__ . "/../layouts/header.php"; ?>
<?php require_once __DIR__ . "/../../helpers/http.php"; ?>
<?php
function format_user_dashboard_bytes(int $bytes): string {
  $units = ['B', 'KB', 'MB', 'GB', 'TB'];
  $value = $bytes;
  $unit = 0;
  while ($value >= 1024 && $unit < count($units) - 1) {
    $value /= 1024;
    $unit++;
  }
  return number_format($value, $unit === 0 ? 0 : 2) . ' ' . $units[$unit];
}

$user = $_SESSION['user'] ?? [];
$displayName = trim((string)($user['name'] ?? 'User'));
$firstName = $displayName !== '' ? (string)preg_replace('/\s+.*/', '', $displayName) : 'User';

$storage = $storage ?? ['used' => 0, 'limit' => 1, 'remaining' => 1, 'percent' => 0];
$storageUsed = (int)($storage['used'] ?? 0);
$storageLimit = max(1, (int)($storage['limit'] ?? 1));
$storageRemaining = max(0, (int)($storage['remaining'] ?? 0));
$storagePercentRaw = (float)($storage['percent'] ?? (($storageUsed / $storageLimit) * 100));
$storagePercent = number_format(min(100, max(0, $storagePercentRaw)), 1);

$folderCount = (int)($folderCount ?? 0);
$activeTotal = (int)($activeTotal ?? 0);
$sharedTotal = (int)($sharedTotal ?? 0);
$trashTotal = (int)($trashTotal ?? 0);
$unreadNotifications = (int)($unreadNotifications ?? 0);
$recentDocs = is_array($recentDocs ?? null) ? $recentDocs : [];
$recentActivity = is_array($recentActivity ?? null) ? $recentActivity : [];
$recentNotifications = is_array($recentNotifications ?? null) ? $recentNotifications : [];
?>

<div class="workspace-page">
  <?php if(req_str('msg') !== ''): ?>
    <div class="alert alert-success mb-0"><?= e(ui_message(req_str('msg'))) ?></div>
  <?php endif; ?>
  <?php if(req_str('err') !== ''): ?>
    <div class="alert alert-danger mb-0"><?= e(ui_message(req_str('err'))) ?></div>
  <?php endif; ?>

  <section class="workspace-hero">
    <div class="workspace-hero__content">
      <div class="workspace-hero__eyebrow">Simple Home</div>
      <h1 class="workspace-hero__title">Hello <?= e($firstName) ?>, what do you want to do today?</h1>
      <p class="workspace-hero__copy">
        Start with one button below. You can always come back here.
      </p>
      <div class="dashboard-status-chips">
        <span class="dashboard-status-chip"><i class="bi bi-files me-1"></i><?= $activeTotal ?> file(s)</span>
        <span class="dashboard-status-chip"><i class="bi bi-bell me-1"></i><?= $unreadNotifications ?> unread alert(s)</span>
      </div>
    </div>
  </section>

  <section class="row g-3">
    <div class="col-12 col-lg-4">
      <a href="<?= BASE_URL ?>/documents?tab=my" class="card h-100 text-decoration-none text-dark border-0 shadow-sm">
        <div class="card-body">
          <div class="text-uppercase fw-bold small text-muted">Step 1</div>
          <h2 class="h5 mt-2 mb-2">My Files</h2>
          <p class="text-muted mb-0">Open your files, upload a new file, or create a folder.</p>
        </div>
      </a>
    </div>
    <div class="col-12 col-lg-4">
      <a href="<?= BASE_URL ?>/documents?tab=shared" class="card h-100 text-decoration-none text-dark border-0 shadow-sm">
        <div class="card-body">
          <div class="text-uppercase fw-bold small text-muted">Step 2</div>
          <h2 class="h5 mt-2 mb-2">Shared With Me</h2>
          <p class="text-muted mb-0">See files that other people shared with you.</p>
        </div>
      </a>
    </div>
    <div class="col-12 col-lg-4">
      <a href="<?= BASE_URL ?>/documents?tab=trash" class="card h-100 text-decoration-none text-dark border-0 shadow-sm">
        <div class="card-body">
          <div class="text-uppercase fw-bold small text-muted">Step 3</div>
          <h2 class="h5 mt-2 mb-2">Trash</h2>
          <p class="text-muted mb-0">Restore files you removed by mistake.</p>
        </div>
      </a>
    </div>
  </section>

  <section class="workspace-dual-grid">
    <article class="surface-card dashboard-panel">
      <div class="table-card__meta">
        <div>
          <h2 class="surface-card__title mb-1">Storage usage</h2>
          <p class="surface-card__copy mb-0">Track your quota before large uploads or new versions.</p>
        </div>
        <span class="badge-soft badge-soft--info"><?= e($storagePercent) ?>%</span>
      </div>
      <div class="dashboard-storage-block">
        <div class="dashboard-storage-block__row">
          <span>Used space</span>
          <strong><?= e(format_user_dashboard_bytes($storageUsed)) ?></strong>
        </div>
        <div class="dashboard-storage-block__rail">
          <span style="width: <?= e($storagePercent) ?>%"></span>
        </div>
        <div class="dashboard-storage-block__meta">
          <?= e(format_user_dashboard_bytes($storageRemaining)) ?> remaining of <?= e(format_user_dashboard_bytes($storageLimit)) ?>
        </div>
      </div>
      <a class="btn btn-outline-secondary mt-3" href="<?= BASE_URL ?>/documents?tab=my"><i class="bi bi-upload me-1"></i>Open My Files</a>
    </article>
  </section>

  <section class="surface-card dashboard-panel">
    <div class="table-card__meta">
      <div>
        <h2 class="surface-card__title mb-1">Recent files</h2>
        <p class="surface-card__copy mb-0">Open one of your recent files quickly.</p>
      </div>
      <a class="btn btn-light" href="<?= BASE_URL ?>/documents?tab=my">See all files</a>
    </div>

    <?php if(empty($recentDocs)): ?>
      <div class="table-empty px-0 pb-0">No files found yet. Upload your first document to get started.</div>
    <?php else: ?>
      <div class="dashboard-file-list">
        <?php foreach($recentDocs as $doc): ?>
          <?php $docId = (int)($doc['id'] ?? 0); ?>
          <a class="dashboard-file-row" href="<?= BASE_URL ?>/documents/view?id=<?= $docId ?>">
            <span class="dashboard-file-row__icon"><i class="bi bi-file-earmark-text"></i></span>
            <span class="dashboard-file-row__main">
              <span class="dashboard-file-row__name"><?= e((string)($doc['name'] ?? 'Untitled file')) ?></span>
              <span class="dashboard-file-row__meta">
                Created <?= e((string)($doc['created_at'] ?? '')) ?> | Updated <?= e((string)($doc['last_activity_at'] ?? $doc['created_at'] ?? '')) ?>
              </span>
            </span>
            <span class="dashboard-file-row__open"><i class="bi bi-chevron-right"></i></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="surface-card dashboard-panel">
    <div class="table-card__meta">
      <div>
        <h2 class="surface-card__title mb-1">Alerts</h2>
        <p class="surface-card__copy mb-0">New messages about file sharing and updates.</p>
      </div>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/notifications/read"><i class="bi bi-check2-all me-1"></i>Mark all as read</a>
    </div>

    <?php if(empty($recentNotifications)): ?>
      <div class="table-empty px-0 pb-0">You are all caught up.</div>
    <?php else: ?>
      <div class="dashboard-notice-list">
        <?php foreach($recentNotifications as $notice): ?>
          <a class="dashboard-notice-row" href="<?= e(BASE_URL . (string)($notice['link'] ?? '/documents?tab=shared')) ?>">
            <span class="dashboard-notice-row__icon"><i class="bi bi-bell"></i></span>
            <span class="dashboard-notice-row__main">
              <span class="dashboard-notice-row__title"><?= e((string)($notice['title'] ?? 'Notification')) ?></span>
              <span class="dashboard-notice-row__meta"><?= e((string)($notice['body'] ?? '')) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . "/../layouts/footer.php"; ?>
