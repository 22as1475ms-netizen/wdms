<?php require __DIR__ . "/../layouts/header.php"; ?>
<?php require_once __DIR__ . "/../../helpers/http.php"; ?>
<?php
$users = $users ?? [];
$selectedUser = $selectedUser ?? null;
$selectedUserId = (int)($selectedUserId ?? 0);
$logs = $logs ?? [];
$days = $days ?? [];
$summary = $summary ?? ['total' => 0, 'document_events' => 0, 'sharing_events' => 0];
$selectedMonth = (string)($selectedMonth ?? date('Y-m'));
$selectedMonthLabel = (string)($selectedMonthLabel ?? '');
?>

<div class="workspace-page">
  <section class="workspace-toolbar">
    <div>
      <div class="section-eyebrow">Administration</div>
      <h1 class="drive-title">Audit Logs</h1>
      <p class="muted-copy">Drive-style per-user logs with day-based collapsible history and per-user exports.</p>
    </div>
    <div class="drive-actions">
      <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/admin/users"><i class="bi bi-people me-1"></i>User workspaces</a>
    </div>
  </section>

  <?php if(req_str('err') !== ''): ?>
    <div class="alert alert-danger"><?= e(ui_message(req_str('err'))) ?></div>
  <?php endif; ?>

  <section class="admin-drive-layout">
    <aside class="admin-drive-sidebar">
      <div class="table-card">
        <div class="table-card__header">
          <div>
            <h2><i class="bi bi-people me-1"></i>Users</h2>
            <p>Select one user to inspect logs.</p>
          </div>
        </div>
        <div class="table-card__body admin-user-list">
          <?php foreach($users as $u): ?>
            <?php $uid = (int)$u['id']; ?>
            <a class="admin-user-chip <?= $uid === $selectedUserId ? 'is-active' : '' ?>" href="<?= BASE_URL ?>/admin/logs?user_id=<?= $uid ?>">
              <span class="admin-user-chip__avatar"><?= e(strtoupper(substr((string)$u['name'], 0, 1))) ?></span>
              <span class="admin-user-chip__meta">
                <strong><?= e($u['name']) ?></strong>
                <span><?= e($u['email']) ?></span>
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
              <h2><i class="bi bi-journal-text me-1"></i><?= e((string)$selectedUser['name']) ?> activity</h2>
              <p><?= e((string)$selectedUser['email']) ?> | <?= e((string)$selectedUser['role']) ?> | <?= e((string)$selectedUser['status']) ?></p>
            </div>
            <div class="drive-actions">
              <span class="badge-soft <?= ((string)$selectedUser['role'] === 'ADMIN') ? 'badge-soft--warning' : 'badge-soft--info' ?>"><?= e((string)$selectedUser['role']) ?></span>
              <span class="status-pill <?= ((string)$selectedUser['status'] === 'ACTIVE') ? 'status-pill--active' : 'status-pill--disabled' ?>"><?= e((string)$selectedUser['status']) ?></span>
            </div>
          </div>
          <div class="table-card__body">
            <div class="detail-stat-grid">
              <article class="share-stat">
                <div class="share-stat__label">Loaded events</div>
                <div class="share-stat__value"><?= (int)$summary['total'] ?></div>
              </article>
              <article class="share-stat">
                <div class="share-stat__label">Document events</div>
                <div class="share-stat__value"><?= (int)$summary['document_events'] ?></div>
              </article>
              <article class="share-stat">
                <div class="share-stat__label">Sharing events</div>
                <div class="share-stat__value"><?= (int)$summary['sharing_events'] ?></div>
              </article>
            </div>
            <p class="muted-copy mt-3 mb-0">Showing logs for <strong><?= e($selectedMonthLabel) ?></strong> (Philippine Time).</p>

            <form method="GET" action="<?= BASE_URL ?>/admin/logs" class="drive-search mt-3">
              <input type="hidden" name="user_id" value="<?= (int)$selectedUserId ?>">
              <input class="form-control drive-input" type="month" name="month" value="<?= e($selectedMonth) ?>">
              <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-funnel me-1"></i>Apply month filter</button>
            </form>

            <div class="drive-actions mt-3">
              <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/admin/logs/export?user_id=<?= (int)$selectedUserId ?>&month=<?= e($selectedMonth) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Download Activity Report PDF</a>
            </div>
          </div>
        </div>

        <div class="table-card">
          <div class="table-card__header">
            <div>
              <h2><i class="bi bi-calendar3 me-1"></i>Day-by-day timeline</h2>
              <p>Each date is collapsible with complete action history and exact time.</p>
            </div>
          </div>

          <?php if(empty($logs)): ?>
            <div class="table-empty">No logs found for this user on the selected date range.</div>
          <?php else: ?>
            <div class="table-card__body admin-folder-sections">
              <?php foreach($days as $day => $dayLogs): ?>
                <details class="folder-section" <?= $day === array_key_first($days) ? 'open' : '' ?>>
                  <summary class="folder-section__header" style="cursor:pointer; list-style:none;">
                    <div>
                      <h3 class="folder-section__title"><span class="drive-folder-glyph"><i class="bi bi-calendar-event"></i></span><?= e($day) ?></h3>
                      <p><?= count($dayLogs) ?> event(s)</p>
                    </div>
                  </summary>
                  <div class="table-responsive">
                    <table class="table workspace-table align-middle mb-0">
                      <thead>
                        <tr>
                          <th>Date</th>
                          <th>Hour</th>
                          <th>Action</th>
                          <th>Document</th>
                          <th>Details</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach($dayLogs as $log): ?>
                          <?php $dt = admin_datetime_pht((string)($log['created_at'] ?? '')); ?>
                          <tr>
                            <td class="text-muted"><?= e($dt ? $dt->format('Y-m-d') : '-') ?></td>
                            <td class="text-muted"><?= e($dt ? $dt->format('h:i A') : '-') ?></td>
                            <td><?= e((string)$log['action']) ?></td>
                            <td><?= e((string)($log['document_id'] ?? '-')) ?></td>
                            <td>
                              <?php $metaItems = parse_meta_details((string)($log['meta'] ?? '')); ?>
                              <?php if(empty($metaItems)): ?>
                                <span class="meta-empty">No details</span>
                              <?php else: ?>
                                <div class="meta-list">
                                  <?php foreach($metaItems as $item): ?>
                                    <span class="meta-item">
                                      <span class="meta-item__label"><?= e((string)$item['label']) ?>:</span>
                                      <span class="meta-item__value"><?= e((string)$item['value']) ?></span>
                                    </span>
                                  <?php endforeach; ?>
                                </div>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </details>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="table-card">
          <div class="table-empty">No users found.</div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php require __DIR__ . "/../layouts/footer.php"; ?>
