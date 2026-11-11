<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pagination.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/violation_helpers.php';

require_role('driver');

$user     = current_user();
$driverId = (int) ($user['driver_id'] ?? 0);

if ($driverId <= 0) {
    set_flash('error', 'Your account is not linked to a driver profile.');
    header('Location: ' . url('logout.php'));
    exit;
}

// Filters (subset of admin — no driver_id because it's fixed)
$eventType = (string) ($_GET['event_type'] ?? '');
$severity  = (string) ($_GET['severity']   ?? '');
$status    = (string) ($_GET['status']     ?? '');
$q         = trim((string) ($_GET['q']     ?? ''));
$sort      = (string) ($_GET['sort']       ?? 'occurred');
$dir       = (string) ($_GET['dir']        ?? 'desc');
$page      = max(1, (int) ($_GET['page']   ?? 1));

$result = list_violations([
    'driver_id'  => $driverId,
    'event_type' => $eventType,
    'severity'   => $severity,
    'status'     => $status,
    'q'          => $q,
    'sort'       => $sort,
    'dir'        => $dir,
    'page'       => $page,
    'per_page'   => 10,
]);

$violations = $result['rows'];
$meta       = $result['meta'];

$byStatus   = count_violations_by_status($driverId);
$bySeverity = count_violations_by_severity($driverId);

$pageTitle = 'My Violations';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../../includes/partials/sidebar_driver.php'; ?>

    <div class="app-main">
        <header class="app-topbar">
            <div class="app-topbar__left">
                <button class="app-topbar__toggle" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
                    <i data-lucide="menu"></i>
                </button>
                <span class="app-topbar__title"><?= e($pageTitle) ?></span>
            </div>
            <div class="app-topbar__right">
                <span class="app-badge app-badge--info">DEMO MODE</span>
                <button class="app-topbar__icon-btn" type="button" data-theme-toggle aria-label="Toggle theme">
                    <i data-lucide="moon"></i>
                </button>
                <span class="app-user">
                    <i data-lucide="user-circle"></i>
                    <span class="app-user__name"><?= e($user['full_name'] ?? 'User') ?></span>
                    <span class="app-user__role"><?= e($user['role'] ?? '') ?></span>
                </span>
                <a href="<?= url('logout.php') ?>" class="btn btn--sm">
                    <i data-lucide="log-out"></i> Sign out
                </a>
            </div>
        </header>

        <main class="app-content">
            <?php require_once __DIR__ . '/../../includes/flash.php'; ?>

            <div class="page-header">
                <h1 class="page-title">My Violations</h1>
                <p class="page-subtitle">
                    <?= (int) $meta['total'] ?> event<?= $meta['total'] === 1 ? '' : 's' ?> on record
                </p>
            </div>

            <!-- KPI strip -->
            <div class="grid grid--kpis">
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Open</div>
                        <div class="kpi__value"><?= (int) $byStatus['open'] ?></div>
                        <div class="kpi__meta">Awaiting review</div>
                    </div>
                    <div class="kpi__icon kpi__icon--warning"><i data-lucide="alert-circle"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Critical</div>
                        <div class="kpi__value"><?= (int) $bySeverity['critical'] ?></div>
                        <div class="kpi__meta">Highest severity</div>
                    </div>
                    <div class="kpi__icon kpi__icon--danger"><i data-lucide="alert-triangle"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Resolved</div>
                        <div class="kpi__value"><?= (int) $byStatus['resolved'] ?></div>
                        <div class="kpi__meta">Closed events</div>
                    </div>
                    <div class="kpi__icon kpi__icon--success"><i data-lucide="check-circle-2"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Events</div>
                        <div class="kpi__value"><?= (int) $meta['total'] ?></div>
                        <div class="kpi__meta">All-time</div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="list"></i></div>
                </div>
            </div>

            <!-- Filters -->
            <form class="filter-bar" method="GET" action="<?= url('driver/violations/index.php') ?>">
                <div class="filter-bar__search">
                    <i data-lucide="search" class="filter-bar__search-icon"></i>
                    <input type="text" name="q" class="form-input"
                           placeholder="Search description…"
                           value="<?= e($q) ?>" autocomplete="off">
                </div>

                <select name="event_type" class="form-input filter-bar__select">
                    <option value="">All types</option>
                    <?php foreach (violation_event_types() as $t): ?>
                        <option value="<?= e($t) ?>" <?= $eventType === $t ? 'selected' : '' ?>>
                            <?= e(format_event_type($t)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="severity" class="form-input filter-bar__select">
                    <option value="">All severities</option>
                    <?php foreach (violation_severities() as $s): ?>
                        <option value="<?= e($s) ?>" <?= $severity === $s ? 'selected' : '' ?>>
                            <?= e(ucfirst($s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="form-input filter-bar__select">
                    <option value="">All statuses</option>
                    <?php foreach (violation_statuses() as $s): ?>
                        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                            <?= e(ucfirst($s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn"><i data-lucide="filter"></i> Apply</button>

                <?php if ($q || $eventType || $severity || $status): ?>
                    <a href="<?= url('driver/violations/index.php') ?>" class="btn btn--ghost">
                        <i data-lucide="x"></i> Clear
                    </a>
                <?php endif; ?>
            </form>

            <!-- Table -->
            <div class="section-card">
                <div class="section-card__body section-card__body--flush">
                    <?php if (empty($violations)): ?>
                        <div class="empty-state">
                            <i data-lucide="shield-check"></i>
                            <div class="empty-state__title">Clean record</div>
                            <div class="empty-state__text">
                                <?= ($q || $eventType || $severity || $status)
                                    ? 'No violations match your filters.'
                                    : 'You have no recorded violations. Keep driving safely!' ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Vehicle</th>
                                    <th>Severity</th>
                                    <th>Confidence</th>
                                    <th>Status</th>
                                    <th>Occurred</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($violations as $v): ?>
                                    <tr class="violation-row--<?= e($v['severity']) ?>">
                                        <td><?= e(format_event_type($v['event_type'])) ?></td>
                                        <td><?= e($v['registration_number'] ?? '—') ?></td>
                                        <td>
                                            <span class="badge <?= e(badge_class($v['severity'])) ?>">
                                                <?= e(format_label($v['severity'])) ?>
                                            </span>
                                        </td>
                                        <td><?= $v['confidence'] !== null ? number_format((float) $v['confidence'] * 100, 0) . '%' : '—' ?></td>
                                        <td>
                                            <span class="badge <?= e(badge_class($v['status'])) ?>">
                                                <?= e(format_label($v['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= e(date('d M, H:i', strtotime($v['occurred_at']))) ?></td>
                                        <td class="text-right">
                                            <div class="row-actions">
                                                <a href="<?= url('driver/violations/view.php?id=' . (int) $v['violation_id']) ?>"
                                                   class="row-action" title="View">
                                                    <i data-lucide="eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <?= render_pagination($meta, 'driver/violations/index.php', [
                'q'          => $q,
                'event_type' => $eventType,
                'severity'   => $severity,
                'status'     => $status,
                'sort'       => $sort,
                'dir'        => $dir,
            ]) ?>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>