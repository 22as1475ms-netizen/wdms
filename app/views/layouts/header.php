<?php
require_once __DIR__ . "/../../config/app.php";
require_once __DIR__ . "/../../helpers/http.php";
require_once __DIR__ . "/../../models/Notification.php";

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isAuthPage = $currentPath === (BASE_URL . '/login') || str_ends_with($currentPath, '/login');
$isWorkspacePage = $currentPath === (BASE_URL . '/documents') || str_ends_with($currentPath, '/documents');
$user = $_SESSION['user'] ?? null;
$initials = $user ? avatar_initials((string)$user['name']) : '';
$avatarPhoto = $user ? avatar_photo_url($user) : null;
$avatarPreset = $user ? avatar_preset_key($user) : 'preset-ocean';
$onboardingVersionMap = [
  'ADMIN' => 'admin-v1',
  'DIVISION_CHIEF' => 'division-chief-v1',
  'EMPLOYEE' => 'employee-v1',
];
$currentGuideVersion = $user ? ($onboardingVersionMap[strtoupper((string)($user['role'] ?? 'EMPLOYEE'))] ?? 'employee-v1') : '';
$shouldAutoOpenGuide = $user && (($user['onboarding_guide_version'] ?? '') !== $currentGuideVersion);
$unreadCount = 0;
$unreadItems = [];
if ($user && isset($pdo)) {
  $unreadCount = Notification::unreadCount($pdo, (int)$user['id']);
  $unreadItems = Notification::recentAll($pdo, (int)$user['id'], 8);
}
$notificationTone = static function (array $n): array {
  $haystack = strtolower(trim(((string)($n['title'] ?? '')) . ' ' . ((string)($n['body'] ?? ''))));
  if ($haystack !== '' && (str_contains($haystack, 'reject') || str_contains($haystack, 'denied') || str_contains($haystack, 'failed') || str_contains($haystack, 'error'))) {
    return ['danger', 'bi-x-circle-fill'];
  }
  if ($haystack !== '' && (str_contains($haystack, 'approved') || str_contains($haystack, 'accepted') || str_contains($haystack, 'success'))) {
    return ['success', 'bi-check-circle-fill'];
  }
  if ($haystack !== '' && (str_contains($haystack, 'review') || str_contains($haystack, 'pending') || str_contains($haystack, 'request'))) {
    return ['warning', 'bi-exclamation-circle-fill'];
  }
  if ($haystack !== '' && (str_contains($haystack, 'message') || str_contains($haystack, 'chat'))) {
    return ['info', 'bi-chat-left-text-fill'];
  }
  return ['info', 'bi-bell-fill'];
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script>
    (function () {
      try {
        var storedMode = localStorage.getItem('wdms-color-mode');
        var storedChatTheme = localStorage.getItem('wdms-chat-theme');
        var storedPerformanceMode = localStorage.getItem('wdms-performance-mode');
        var validPerformanceModes = ['auto', 'lite', 'full'];
        var performanceMode = validPerformanceModes.indexOf(storedPerformanceMode) >= 0 ? storedPerformanceMode : 'auto';
        var prefersReducedMotion = false;
        var saveData = false;
        var deviceMemory = 8;
        var hardwareConcurrency = 8;
        try {
          prefersReducedMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
          saveData = !!(navigator.connection && navigator.connection.saveData);
          deviceMemory = Number(navigator.deviceMemory || 8);
          hardwareConcurrency = Number(navigator.hardwareConcurrency || 8);
        } catch (_perfErr) {}
        var computedPerformanceTier = performanceMode === 'lite'
          ? 'lite'
          : (performanceMode === 'full'
            ? 'full'
            : ((prefersReducedMotion || saveData || deviceMemory <= 4 || hardwareConcurrency <= 4) ? 'lite' : 'full'));
        document.documentElement.setAttribute('data-color-mode', storedMode === 'dark' ? 'dark' : 'light');
        document.documentElement.setAttribute('data-chat-theme', storedChatTheme || 'ocean');
        document.documentElement.setAttribute('data-performance-mode', performanceMode);
        document.documentElement.setAttribute('data-performance-tier', computedPerformanceTier);
        window.wdmsPerformance = {
          mode: performanceMode,
          tier: computedPerformanceTier,
          prefersReducedMotion: prefersReducedMotion,
          saveData: saveData,
          deviceMemory: deviceMemory,
          hardwareConcurrency: hardwareConcurrency
        };
      } catch (_err) {
        document.documentElement.setAttribute('data-color-mode', 'light');
        document.documentElement.setAttribute('data-chat-theme', 'ocean');
        document.documentElement.setAttribute('data-performance-mode', 'auto');
        document.documentElement.setAttribute('data-performance-tier', 'full');
      }
    })();
  </script>
  <link href="<?= BASE_URL ?>/assets/css/app.css" rel="stylesheet">
</head>
<body class="app-body <?= $isAuthPage ? 'app-body--auth' : '' ?>">
<?php if(!$isAuthPage): ?>
<nav class="navbar navbar-expand-lg app-nav">
  <div class="container-fluid px-4 px-lg-5">
    <div class="app-nav__inner w-100">
      <a class="app-brand" href="<?= BASE_URL . workspace_home_path() ?>">
        <span class="app-brand__logo">
          <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="<?= e(APP_NAME) ?> logo">
        </span>
        <span>
          <span class="d-block app-brand__name"><?= e(APP_NAME) ?></span>
          <span class="d-block app-brand__meta">Database Workspace</span>
        </span>
      </a>

      <?php if($user && $isWorkspacePage): ?>
        <?php
          $navTab = trim((string)($_GET['tab'] ?? 'private'));
          $navUserId = (int)($_GET['user_id'] ?? 0);
        ?>
        <form class="app-nav-search" method="GET" action="<?= BASE_URL ?>/documents">
          <input type="hidden" name="tab" value="<?= e($navTab !== '' ? $navTab : 'private') ?>">
          <?php if($navUserId > 0 && $navTab !== 'shared'): ?>
            <input type="hidden" name="user_id" value="<?= (int)$navUserId ?>">
          <?php endif; ?>
          <span class="app-nav-search__shell">
            <i class="bi bi-search app-nav-search__icon" aria-hidden="true"></i>
            <input class="form-control app-nav-search__input" type="search" name="q" placeholder="Search tabs, files, folders, settings" value="<?= e((string)($_GET['q'] ?? '')) ?>">
            <button class="btn btn-primary btn-sm app-nav-search__btn" type="submit">Search</button>
          </span>
        </form>
      <?php endif; ?>

      <div class="app-nav__cluster">
        <?php if($user): ?>
          <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm app-notification-btn" data-bs-toggle="dropdown" type="button" id="app-alert-toggle" aria-label="Notifications">
              <i class="bi bi-bell"></i>
              <span id="app-alert-dot" class="app-notification-dot <?= $unreadCount > 0 ? '' : 'd-none' ?>" aria-hidden="true"></span>
              <span id="app-alert-count" class="visually-hidden"><?= (int)$unreadCount ?></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-2 app-notification-menu" style="min-width: 340px;">
              <div id="app-alert-items">
                <?php if(empty($unreadItems)): ?>
                  <div class="text-muted small px-2 py-1">No unread notifications.</div>
                <?php else: ?>
                    <?php foreach($unreadItems as $n): ?>
                      <?php [$tone, $icon] = $notificationTone($n); ?>
                      <?php $rawLink = trim(Notification::resolveDestination($n)); ?>
                    <?php if($rawLink === 'chat://open'): ?>
                      <button type="button" class="dropdown-item app-notification-item app-notification-item--<?= e($tone) ?> <?= (int)($n['is_read'] ?? 0) === 1 ? 'is-read' : '' ?> js-open-chat-from-notification">
                        <span class="app-notification-item__icon"><i class="bi <?= e($icon) ?>"></i></span>
                        <span class="app-notification-item__content">
                          <strong><?= e((string)$n['title']) ?></strong>
                          <small><?= e((string)($n['body'] ?? '')) ?></small>
                        </span>
                      </button>
                    <?php elseif($rawLink !== ''): ?>
                      <?php $href = (str_starts_with($rawLink, 'http://') || str_starts_with($rawLink, 'https://')) ? $rawLink : (BASE_URL . (str_starts_with($rawLink, '/') ? $rawLink : ('/' . $rawLink))); ?>
                      <a class="dropdown-item app-notification-item app-notification-item--<?= e($tone) ?> <?= (int)($n['is_read'] ?? 0) === 1 ? 'is-read' : '' ?>" href="<?= e($href) ?>">
                        <span class="app-notification-item__icon"><i class="bi <?= e($icon) ?>"></i></span>
                        <span class="app-notification-item__content">
                          <strong><?= e((string)$n['title']) ?></strong>
                          <small><?= e((string)($n['body'] ?? '')) ?></small>
                        </span>
                      </a>
                    <?php else: ?>
                      <div class="dropdown-item app-notification-item app-notification-item--<?= e($tone) ?> <?= (int)($n['is_read'] ?? 0) === 1 ? 'is-read' : '' ?>">
                        <span class="app-notification-item__icon"><i class="bi <?= e($icon) ?>"></i></span>
                        <span class="app-notification-item__content">
                          <strong><?= e((string)$n['title']) ?></strong>
                          <small><?= e((string)($n['body'] ?? '')) ?></small>
                        </span>
                      </div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <div class="dropdown-divider"></div>
              <form method="POST" action="<?= BASE_URL ?>/notifications/read" class="mb-1" id="mark-all-read-form">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-light w-100">Mark all read</button>
              </form>
              <form method="POST" action="<?= BASE_URL ?>/notifications/clear" id="clear-all-notifications-form">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger w-100">Clear all notifications</button>
              </form>
            </div>
          </div>
          <div class="dropdown">
            <button class="app-user-pill app-user-pill--button" data-bs-toggle="dropdown" type="button" aria-expanded="false">
              <span class="app-user-pill__avatar <?= e($avatarPreset) ?>">
                <?php if($avatarPhoto): ?>
                  <img src="<?= e($avatarPhoto) ?>" alt="<?= e($user['name']) ?>">
                <?php else: ?>
                  <?= e($initials) ?>
                <?php endif; ?>
              </span>
              <span class="app-user-pill__meta">
                <strong><?= e($user['name']) ?></strong>
                <span><?= e(role_label((string)$user['role'])) ?></span>
              </span>
              <i class="bi bi-chevron-down ms-1"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-2">
              <a class="dropdown-item" href="<?= BASE_URL ?>/account/password"><i class="bi bi-person-gear me-2"></i>Profile</a>
              <a class="dropdown-item" href="<?= BASE_URL ?>/documents"><i class="bi bi-folder2-open me-2"></i>Workspace</a>
              <button class="dropdown-item app-theme-toggle" type="button" id="toggle-color-mode" role="switch" aria-checked="false">
                <span class="app-theme-toggle__copy">
                  <i class="bi bi-moon-stars" id="toggle-color-mode-icon"></i>
                  <span>
                    <strong>Dark mode</strong>
                    <small id="toggle-color-mode-label">Off</small>
                  </span>
                </span>
                <span class="app-theme-toggle__switch" aria-hidden="true"><span class="app-theme-toggle__thumb"></span></span>
              </button>
              <button class="dropdown-item app-theme-toggle" type="button" id="toggle-performance-mode">
                <span class="app-theme-toggle__copy">
                  <i class="bi bi-speedometer2" id="toggle-performance-icon"></i>
                  <span>
                    <strong>Performance mode</strong>
                    <small id="toggle-performance-label">Auto</small>
                  </span>
                </span>
                <span class="app-theme-toggle__value" id="toggle-performance-value">Auto</span>
              </button>
              <button class="dropdown-item" type="button" id="open-onboarding-guide"><i class="bi bi-signpost-split me-2"></i>How to use WDMS</button>
              <div class="dropdown-divider"></div>
              <form method="POST" action="<?= BASE_URL ?>/logout" class="js-confirm m-0" data-confirm-message="Log out now?">
                <?= csrf_field() ?>
                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
              </form>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<?php endif; ?>
<div class="container-fluid app-shell">
