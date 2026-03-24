<?php require __DIR__ . "/../layouts/header.php"; ?>
<?php require_once __DIR__ . "/../../helpers/csrf.php"; ?>
<?php require_once __DIR__ . "/../../helpers/http.php"; ?>
<?php
$groupsByUserId = [];
foreach ($workspaceGroups as $group) {
  $groupsByUserId[(int)$group['user']['id']] = $group;
}
$defaultUserId = !empty($workspaceGroups) ? (int)$workspaceGroups[0]['user']['id'] : 0;
$selectedUserId = req_int('user_id', $defaultUserId);
$selectedGroup = $groupsByUserId[$selectedUserId] ?? ($workspaceGroups[0] ?? null);
$selectedUser = $selectedGroup['user'] ?? null;
$selectedSummary = $selectedGroup['summary'] ?? [];
$selectedPanel = ($userPanels ?? [])[$selectedUserId] ?? ['recent_documents' => [], 'recent_logs' => [], 'activity_summary' => []];
$selectedActivity = $selectedPanel['activity_summary'] ?? [];
$selectedRecentDocuments = $selectedPanel['recent_documents'] ?? [];
$selectedRecentLogs = $selectedPanel['recent_logs'] ?? [];

if (!function_exists('admin_view_datetime')) {
  function admin_view_datetime(?string $value, string $fallback = 'No recent activity'): string {
    $raw = trim((string)$value);
    if ($raw === '') {
      return $fallback;
    }
    try {
      $dt = new DateTimeImmutable($raw, new DateTimeZone('Asia/Manila'));
    } catch (Throwable $_e) {
      return $fallback;
    }
    return $dt->setTimezone(new DateTimeZone('Asia/Manila'))->format('M d, Y g:i A');
  }
}

if (!function_exists('admin_badge_class')) {
  function admin_badge_class(string $value, string $type = 'status'): string {
    $normalized = strtoupper(trim($value));
    if ($type === 'area') {
      return $normalized === 'OFFICIAL' ? 'is-official' : 'is-private';
    }
    if ($type === 'priority') {
      return match ($normalized) {
        'URGENT' => 'is-danger',
        'HIGH' => 'is-warning',
        default => 'is-neutral',
      };
    }
    return match ($normalized) {
      'APPROVED', 'ACTIVE', 'SHARE_ACCEPTED', 'IN_REVIEW' => 'is-success',
      'REJECTED', 'DECLINED', 'DISABLED' => 'is-danger',
      'PENDING_REVIEW_ACCEPTANCE', 'PENDING_SHARE_ACCEPTANCE', 'NOT_SENT', 'DRAFT' => 'is-warning',
      default => 'is-neutral',
    };
  }
}

if ($selectedUser) {
  $selectedUserPhoto = avatar_photo_url($selectedUser);
  $selectedUserPreset = avatar_preset_key($selectedUser);
  $selectedUserInitials = avatar_initials((string)$selectedUser['name']);
  $selectedJoined = admin_view_datetime((string)($selectedUser['created_at'] ?? ''), 'Date unavailable');
}
?>

<div class="workspace-page">
  <section class="workspace-toolbar">
    <div>
      <div class="section-eyebrow">Administration</div>
      <h1 class="drive-title">Users, Divisions, and Oversight</h1>
      <p class="muted-copy">Admins manage accounts, divisions, storage oversight, and cross-division visibility. Chat is excluded from the admin surface.</p>
    </div>
    <div class="drive-actions">
      <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/admin/logs">Audit logs</a>
      <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/admin/users/export">Export inventory</a>
    </div>
  </section>

  <?php if(req_str('msg') !== ''): ?>
    <div class="alert alert-success"><?= e(ui_message(req_str('msg'))) ?></div>
  <?php endif; ?>
  <?php if(req_str('err') !== ''): ?>
    <div class="alert alert-danger"><?= e(ui_message(req_str('err'))) ?></div>
  <?php endif; ?>

  <div class="admin-drive-layout">
    <aside class="admin-drive-sidebar">
      <div class="table-card">
        <div class="table-card__header">
          <div>
            <h2><i class="bi bi-diagram-3 me-1"></i>Create division</h2>
            <p>Set the department structure before assigning employees.</p>
          </div>
        </div>
        <div class="table-card__body">
          <form method="POST" action="<?= BASE_URL ?>/admin/divisions/create" class="drive-form-stack admin-confirm-form" data-confirm-label="create this division">
            <?= csrf_field() ?>
            <input type="hidden" name="confirm_password" value="">
            <input class="form-control drive-input" name="name" placeholder="Division name" required>
            <select class="form-select drive-input" name="chief_user_id">
              <option value="0">Assign chief later</option>
              <?php foreach(($divisionChiefs ?? []) as $chief): ?>
                <option value="<?= (int)$chief['id'] ?>"><?= e((string)$chief['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Create division</button>
          </form>
        </div>
      </div>

      <div class="table-card">
        <div class="table-card__header">
          <div>
            <h2><i class="bi bi-person-plus me-1"></i>Create account</h2>
            <p>Default password: <code>password</code></p>
          </div>
        </div>
        <div class="table-card__body">
          <form method="POST" action="<?= BASE_URL ?>/admin/users/create" class="drive-form-stack admin-confirm-form" data-confirm-label="create this account">
            <?= csrf_field() ?>
            <input type="hidden" name="confirm_password" value="">
            <input class="form-control drive-input" name="name" placeholder="Full name" required>
            <input class="form-control drive-input" type="email" name="email" placeholder="Email address" required>
            <select class="form-select drive-input" name="role">
              <option value="EMPLOYEE">Employee</option>
              <option value="DIVISION_CHIEF">Section Chief</option>
              <option value="ADMIN">Admin</option>
            </select>
            <select class="form-select drive-input" name="division_id">
              <option value="0">No division</option>
              <?php foreach(($divisions ?? []) as $division): ?>
                <option value="<?= (int)$division['id'] ?>"><?= e((string)$division['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Create account</button>
          </form>
        </div>
      </div>

      <div class="table-card">
        <div class="table-card__header">
          <div>
            <h2><i class="bi bi-people me-1"></i>Accounts</h2>
            <p>Select an account to inspect private and official storage.</p>
          </div>
        </div>
        <div class="table-card__body admin-user-list">
          <?php foreach($users as $u): ?>
            <?php $userChipPhoto = avatar_photo_url($u); ?>
            <?php $userChipPreset = avatar_preset_key($u); ?>
            <?php $userChipInitials = avatar_initials((string)$u['name']); ?>
            <a class="admin-user-chip <?= $selectedUser && (int)$selectedUser['id'] === (int)$u['id'] ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/admin/users?user_id=<?= (int)$u['id'] ?>">
              <span class="admin-user-chip__avatar app-user-pill__avatar <?= e($userChipPreset) ?>">
                <?php if($userChipPhoto): ?>
                  <img src="<?= e($userChipPhoto) ?>" alt="<?= e((string)$u['name']) ?>">
                <?php else: ?>
                  <?= e($userChipInitials) ?>
                <?php endif; ?>
              </span>
              <span class="admin-user-chip__meta">
                <strong><?= e((string)$u['name']) ?></strong>
                <span><?= e(role_label((string)$u['role'])) ?> | <?= e((string)($u['division_name'] ?? 'No division')) ?></span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </aside>

    <div class="admin-drive-main">
      <?php if($selectedUser): ?>
        <div class="table-card">
          <div class="table-card__header">
            <div>
              <h2><?= e((string)$selectedUser['name']) ?></h2>
              <p><?= e((string)$selectedUser['email']) ?> | <?= e(role_label((string)$selectedUser['role'])) ?> | <?= e((string)($selectedUser['division_name'] ?? 'No division')) ?></p>
            </div>
            <div class="drive-actions">
              <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/documents?tab=private&user_id=<?= (int)$selectedUser['id'] ?>">Private</a>
              <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/documents?tab=official&user_id=<?= (int)$selectedUser['id'] ?>">Official</a>
            </div>
          </div>
          <div class="table-card__body">
            <section class="admin-employee-spotlight">
              <div class="admin-employee-spotlight__hero">
                <div class="admin-employee-spotlight__profile">
                  <span class="admin-employee-spotlight__avatar app-user-pill__avatar <?= e($selectedUserPreset) ?>">
                    <?php if($selectedUserPhoto): ?>
                      <img src="<?= e($selectedUserPhoto) ?>" alt="<?= e((string)$selectedUser['name']) ?>">
                    <?php else: ?>
                      <?= e($selectedUserInitials) ?>
                    <?php endif; ?>
                  </span>
                  <div class="admin-employee-spotlight__identity">
                    <p class="admin-employee-spotlight__eyebrow">Employee workspace overview</p>
                    <h3><?= e((string)$selectedUser['name']) ?></h3>
                    <p>
                      <?= e(role_label((string)$selectedUser['role'])) ?>
                      • <?= e((string)($selectedUser['division_name'] ?? 'No division')) ?>
                      • <?= e((string)$selectedUser['status']) ?>
                    </p>
                    <div class="admin-employee-spotlight__meta">
                      <span><i class="bi bi-envelope me-1"></i><?= e((string)$selectedUser['email']) ?></span>
                      <span><i class="bi bi-calendar2-week me-1"></i>Joined <?= e($selectedJoined) ?></span>
                      <span><i class="bi bi-clock-history me-1"></i><?= e(admin_view_datetime((string)($selectedActivity['last_seen_at'] ?? ''), 'No sign-in log yet')) ?></span>
                    </div>
                  </div>
                </div>
                <div class="admin-employee-spotlight__highlights">
                  <article class="admin-overview-stat">
                    <span class="admin-overview-stat__label">Documents</span>
                    <strong><?= (int)($selectedSummary['document_count'] ?? 0) ?></strong>
                    <small><?= (int)($selectedSummary['active_count'] ?? 0) ?> active, <?= (int)($selectedSummary['trashed_count'] ?? 0) ?> archived</small>
                  </article>
                  <article class="admin-overview-stat">
                    <span class="admin-overview-stat__label">Tracking</span>
                    <strong><?= (int)($selectedSummary['tracking_count'] ?? 0) ?></strong>
                    <small>routed or under workflow handling</small>
                  </article>
                  <article class="admin-overview-stat">
                    <span class="admin-overview-stat__label">Last document activity</span>
                    <strong><?= e(admin_view_datetime((string)($selectedSummary['latest_activity_at'] ?? ''), 'No document activity')) ?></strong>
                    <small>latest upload, submit, review, or version update</small>
                  </article>
                </div>
              </div>

              <div class="admin-overview-metrics">
                <article class="admin-overview-metric-card">
                  <span>Private storage</span>
                  <strong><?= (int)($selectedSummary['private_count'] ?? 0) ?></strong>
                  <small>employee-owned working files</small>
                </article>
                <article class="admin-overview-metric-card">
                  <span>Official storage</span>
                  <strong><?= (int)($selectedSummary['official_count'] ?? 0) ?></strong>
                  <small>published or released records</small>
                </article>
                <article class="admin-overview-metric-card">
                  <span>Incoming / outgoing</span>
                  <strong><?= (int)($selectedSummary['incoming_count'] ?? 0) ?> / <?= (int)($selectedSummary['outgoing_count'] ?? 0) ?></strong>
                  <small>tracked document movement mix</small>
                </article>
                <article class="admin-overview-metric-card">
                  <span>Versions / shared</span>
                  <strong><?= (int)($selectedSummary['version_count'] ?? 0) ?> / <?= (int)($selectedSummary['shared_docs_count'] ?? 0) ?></strong>
                  <small>revision history and collaboration load</small>
                </article>
              </div>

              <div class="admin-employee-panels">
                <article class="admin-employee-panel">
                  <div class="admin-employee-panel__header">
                    <div>
                      <h3>Tracked files snapshot</h3>
                      <p>Recent files that matter most for database, file, and document routing oversight.</p>
                    </div>
                  </div>
                  <div class="admin-file-snapshot-list">
                    <?php if($selectedRecentDocuments): ?>
                      <?php foreach($selectedRecentDocuments as $document): ?>
                        <?php $folderPathLabel = folder_location_label($document['folder_name'] ?? null); ?>
                        <article class="admin-file-snapshot">
                          <div class="admin-file-snapshot__main">
                            <div class="admin-file-snapshot__title-row">
                              <strong><?= e((string)$document['name']) ?></strong>
                              <span class="admin-pill <?= e(admin_badge_class((string)($document['storage_area'] ?? 'PRIVATE'), 'area')) ?>"><?= e(role_label((string)($document['storage_area'] ?? 'PRIVATE'))) ?></span>
                              <span class="admin-pill <?= e(admin_badge_class((string)($document['status'] ?? 'DRAFT'))) ?>"><?= e((string)($document['status'] ?? 'Draft')) ?></span>
                            </div>
                            <div class="admin-file-snapshot__meta">
                              <span><?= e((string)($document['document_code'] ?? 'Uncoded file')) ?></span>
                              <span><?= e((string)($document['document_type'] ?? 'INCOMING')) ?></span>
                              <span>Folder: <?= e(folder_display_name($document['folder_name'] ?? null)) ?></span>
                              <span>Location: <?= e((string)($document['current_location'] ?? 'Not set')) ?></span>
                            </div>
                          </div>
                          <div class="admin-file-snapshot__stats">
                            <span>v<?= (int)($document['latest_version'] ?? 0) ?></span>
                            <span><?= (int)($document['shared_count'] ?? 0) ?> shared</span>
                            <span class="admin-pill <?= e(admin_badge_class((string)($document['priority_level'] ?? 'NORMAL'), 'priority')) ?>"><?= e((string)($document['priority_level'] ?? 'NORMAL')) ?></span>
                            <span title="<?= e($folderPathLabel) ?>"><?= e(admin_view_datetime((string)($document['last_activity_at'] ?? ''), 'No activity')) ?></span>
                          </div>
                        </article>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <p class="admin-empty-state">No tracked files yet for this employee.</p>
                    <?php endif; ?>
                  </div>
                </article>

                <article class="admin-employee-panel">
                  <div class="admin-employee-panel__header">
                    <div>
                      <h3>Recent activity timeline</h3>
                      <p>Quick audit view of the employee's latest uploads, reviews, sharing, and access events.</p>
                    </div>
                  </div>
                  <div class="admin-activity-summary">
                    <div><strong><?= (int)($selectedActivity['total'] ?? 0) ?></strong><span>Recent events</span></div>
                    <div><strong><?= (int)($selectedActivity['uploads'] ?? 0) ?></strong><span>Uploads</span></div>
                    <div><strong><?= (int)($selectedActivity['reviews'] ?? 0) ?></strong><span>Review actions</span></div>
                    <div><strong><?= (int)($selectedActivity['shares'] ?? 0) ?></strong><span>Sharing events</span></div>
                  </div>
                  <div class="admin-activity-timeline">
                    <?php if($selectedRecentLogs): ?>
                      <?php foreach($selectedRecentLogs as $log): ?>
                        <article class="admin-activity-timeline__item">
                          <div class="admin-activity-timeline__dot" aria-hidden="true"></div>
                          <div class="admin-activity-timeline__body">
                            <strong><?= e((string)($log['action'] ?? 'Activity')) ?></strong>
                            <p><?= e(admin_view_datetime((string)($log['created_at'] ?? ''), 'Time unavailable')) ?></p>
                            <?php $metaItems = parse_meta_details((string)($log['meta'] ?? '')); ?>
                            <?php if($metaItems): ?>
                              <div class="admin-activity-timeline__meta">
                                <?php foreach(array_slice($metaItems, 0, 3) as $item): ?>
                                  <span><?= e($item['label']) ?>: <?= e($item['value']) ?></span>
                                <?php endforeach; ?>
                              </div>
                            <?php endif; ?>
                          </div>
                        </article>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <p class="admin-empty-state">No audit trail entries yet for this employee.</p>
                    <?php endif; ?>
                  </div>
                </article>
              </div>
            </section>

            <?php if((int)$selectedUser['id'] !== (int)($_SESSION['user']['id'] ?? 0)): ?>
              <section class="admin-user-controls">
                <article class="admin-action-card">
                  <div class="admin-action-card__header">
                    <div>
                      <h3>Role and division</h3>
                      <p>Adjust access level and department assignment for this account.</p>
                    </div>
                  </div>
                  <form method="POST" action="<?= BASE_URL ?>/admin/users/role?id=<?= (int)$selectedUser['id'] ?>" class="admin-user-form-grid admin-confirm-form" data-confirm-label="update this account role">
                    <?= csrf_field() ?>
                    <input type="hidden" name="confirm_password" value="">
                    <label class="admin-field">
                      <span>Role</span>
                      <select class="form-select form-select-sm" name="role">
                        <option value="EMPLOYEE" <?= (string)$selectedUser['role'] === 'EMPLOYEE' ? 'selected' : '' ?>>Employee</option>
                        <option value="DIVISION_CHIEF" <?= (string)$selectedUser['role'] === 'DIVISION_CHIEF' ? 'selected' : '' ?>>Section Chief</option>
                        <option value="ADMIN" <?= (string)$selectedUser['role'] === 'ADMIN' ? 'selected' : '' ?>>Admin</option>
                      </select>
                    </label>
                    <label class="admin-field">
                      <span>Division</span>
                      <select class="form-select form-select-sm" name="division_id">
                        <option value="0">No division</option>
                        <?php foreach(($divisions ?? []) as $division): ?>
                          <option value="<?= (int)$division['id'] ?>" <?= (int)($selectedUser['division_id'] ?? 0) === (int)$division['id'] ? 'selected' : '' ?>><?= e((string)$division['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <div class="admin-action-card__footer">
                      <button class="btn btn-sm btn-outline-dark" type="submit">Save role settings</button>
                    </div>
                  </form>
                </article>

                <article class="admin-action-card">
                  <div class="admin-action-card__header">
                    <div>
                      <h3>Password</h3>
                      <p>Set a new password for this account. The user will use it on the next sign-in.</p>
                    </div>
                  </div>
                  <form method="POST" action="<?= BASE_URL ?>/admin/users/password?id=<?= (int)$selectedUser['id'] ?>" class="admin-user-form-grid admin-confirm-form" data-confirm-label="change this account password">
                    <?= csrf_field() ?>
                    <input type="hidden" name="confirm_password" value="">
                    <label class="admin-field">
                      <span>New password</span>
                      <input class="form-control form-control-sm" type="password" name="new_password" minlength="8" placeholder="At least 8 characters" required>
                    </label>
                    <label class="admin-field">
                      <span>Confirm password</span>
                      <input class="form-control form-control-sm" type="password" name="new_password_confirm" minlength="8" placeholder="Repeat the new password" required>
                    </label>
                    <div class="admin-action-card__footer">
                      <button class="btn btn-sm btn-outline-secondary" type="submit">Update password</button>
                    </div>
                  </form>
                </article>

                <article class="admin-action-card admin-action-card--danger">
                  <div class="admin-action-card__header">
                    <div>
                      <h3>Account status</h3>
                      <p>Disable access temporarily or permanently remove the account and owned files.</p>
                    </div>
                  </div>
                  <div class="admin-user-form-grid">
                    <div class="admin-action-card__footer admin-action-card__footer--split">
                      <form method="POST" action="<?= BASE_URL ?>/admin/users/toggle?id=<?= (int)$selectedUser['id'] ?>" class="admin-confirm-form" data-confirm-label="change this account status">
                        <?= csrf_field() ?>
                        <input type="hidden" name="confirm_password" value="">
                        <input type="hidden" name="status" value="<?= (string)$selectedUser['status'] === 'ACTIVE' ? 'DISABLED' : 'ACTIVE' ?>">
                        <button class="btn btn-sm btn-outline-primary" type="submit"><?= (string)$selectedUser['status'] === 'ACTIVE' ? 'Disable account' : 'Enable account' ?></button>
                      </form>
                      <form method="POST" action="<?= BASE_URL ?>/admin/users/delete?id=<?= (int)$selectedUser['id'] ?>" class="admin-confirm-form" data-confirm-label="delete this account" data-confirm-message="Permanently delete this account and all owned files? This cannot be undone.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="confirm_password" value="">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete account</button>
                      </form>
                    </div>
                  </div>
                </article>
              </section>
            <?php endif; ?>

            <div class="table-responsive">
              <table class="table workspace-table align-middle mb-0">
                <thead>
                  <tr>
                    <th>File</th>
                    <th>Area</th>
                    <th>Status</th>
                    <th>Routing</th>
                    <th>Folder</th>
                    <th>Shared</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach(($selectedGroup['allDocuments'] ?? []) as $document): ?>
                    <tr>
                      <td><?= e((string)$document['name']) ?></td>
                      <td><?= e(ucfirst(strtolower((string)($document['storage_area'] ?? 'PRIVATE')))) ?></td>
                      <td><?= e((string)($document['status'] ?? 'Draft')) ?></td>
                      <td><?= e((string)($document['routing_status'] ?? 'NOT_ROUTED')) ?></td>
                      <?php $folderPathLabel = folder_location_label($document['folder_name'] ?? null); ?>
                      <td title="<?= e($folderPathLabel) ?>"><?= e(folder_display_name($document['folder_name'] ?? null)) ?></td>
                      <td><?= (int)($document['shared_count'] ?? 0) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . "/../layouts/footer.php"; ?>
