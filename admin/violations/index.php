<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pagination.php';
require_once __DIR__ . '/../../includes/db_queries.php';
require_once __DIR__ . '/../../includes/violation_helpers.php';

require_role('admin');

// Filters
$driverId  = (int)    ($_GET['driver_id']  ?? 0);
$vehicleId = (int)    ($_GET['vehicle_id'] ?? 0);
$eventType = (string) ($_GET['event_type'] ?? '');
$severity  = (string) ($_GET['severity']   ?? '');
$status    = (string) ($_GET['status']     ?? '');
$dateFrom  = (string) ($_GET['date_from']  ?? '');
$dateTo    = (string) ($_GET['date_to']    ?? '');
$q         = trim((string) ($_GET['q']     ?? ''));
$sort      = (string) ($_GET['sort']       ?? 'occurred');
$dir       = (string) ($_GET['dir']        ?? 'desc');
$page      = max(1, (int) ($_GET['page']   ?? 1));

$result = list_violations([
    'driver_id'  => $driverId,
    'vehicle_id' => $vehicleId,
    'event_type' => $eventType,
    'severity'   => $severity,
    'status'     => $status,
    'date_from'  => $dateFrom,
    'date_to'    => $dateTo,
    'q'          => $q,
    'sort'       => $sort,
    'dir'        => $dir,
    'page'       => $page,
    'per_page'   => 15,
]);

$violations = $result['rows'];
$meta       = $result['meta'];

$byStatus   = count_violations_by_status();
$bySeverity = count_violations_by_severity();

$drivers  = list_drivers_minimal();
$vehicles = list_vehicles_minimal();

$user      = current_user();
$pageTitle = 'Violations';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../../includes/partials/sidebar_admin.php'; ?>

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

            <div class="page-header page-header--row">
                <div>
                    <h1 class="page-title">Violations</h1>
                    <p class="page-subtitle"><?= (int) $meta['total'] ?> event<?= $meta['total'] === 1 ? '' : 's' ?> match your filters</p>
                </div>
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
                        <div class="kpi__label">Acknowledged</div>
                        <div class="kpi__value"><?= (int) $byStatus['acknowledged'] ?></div>
                        <div class="kpi__meta">In progress</div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="eye"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Critical severity</div>
                        <div class="kpi__value"><?= (int) $bySeverity['critical'] ?></div>
                        <div class="kpi__meta">Highest priority</div>
                    </div>
                    <div class="kpi__icon kpi__icon--danger"><i data-lucide="alert-triangle"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Resolved</div>
                        <div class="kpi__value"><?= (int) $byStatus['resolved'] ?></div>
                        <div class="kpi__meta">Closed</div>
                    </div>
                    <div class="kpi__icon kpi__icon--success"><i data-lucide="check-circle-2"></i></div>
                </div>
            </div>

            <!-- Filter bar -->
            <form class="filter-bar" method="GET" action="<?= url('admin/violations/index.php') ?>">
                <div class="filter-bar__search">
                    <i data-lucide="search" class="filter-bar__search-icon"></i>
                    <input type="text" name="q" class="form-input"
                           placeholder="Search driver, reg no, description…"
                           value="<?= e($q) ?>" autocomplete="off">
                </div>

                <select name="driver_id" class="form-input filter-bar__select">
                    <option value="">All drivers</option>
                    <?php foreach ($drivers as $d): ?>
                        <option value="<?= (int) $d['driver_id'] ?>" <?= $driverId === (int) $d['driver_id'] ? 'selected' : '' ?>>
                            <?= e($d['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="vehicle_id" class="form-input filter-bar__select">
                    <option value="">All vehicles</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= (int) $v['vehicle_id'] ?>" <?= $vehicleId === (int) $v['vehicle_id'] ? 'selected' : '' ?>>
                            <?= e($v['registration_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

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

                <input type="date" name="date_from" class="form-input filter-bar__select" value="<?= e($dateFrom) ?>">
                <input type="date" name="date_to"   class="form-input filter-bar__select" value="<?= e($dateTo) ?>">

                <button type="submit" class="btn"><i data-lucide="filter"></i> Apply</button>
                <?php if ($q || $driverId || $vehicleId || $eventType || $severity || $status || $dateFrom || $dateTo): ?>
                    <a href="<?= url('admin/violations/index.php') ?>" class="btn btn--ghost">
                        <i data-lucide="x"></i> Clear
                    </a>
                <?php endif; ?>
            </form>

            <!-- Bulk form -->
            <form method="POST" action="<?= url('admin/violations/bulk.php') ?>" data-bulk-form>
                <?= csrf_field() ?>

                <!-- Bulk bar (hidden until checkboxes selected) -->
                <div class="bulk-bar" data-bulk-bar style="display: none;">
                    <span class="bulk-bar__count"><span data-bulk-count>0</span> selected</span>
                    <select name="new_status" class="form-input" style="max-width: 200px;">
                        <option value="">— Choose action —</option>
                        <?php foreach (violation_statuses() as $s): ?>
                            <option value="<?= e($s) ?>">Mark as <?= e(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn--primary btn--sm">
                        <i data-lucide="check"></i> Apply
                    </button>
                    <span class="bulk-bar__spacer"></span>
                </div>

                <div class="section-card">
                    <div class="section-card__body section-card__body--flush">
                        <?php if (empty($violations)): ?>
                            <div class="empty-state">
                                <i data-lucide="shield-check"></i>
                                <div class="empty-state__title">No violations found</div>
                                <div class="empty-state__text">
                                    <?= ($q || $driverId || $vehicleId || $eventType || $severity || $status || $dateFrom || $dateTo)
                                        ? 'Try adjusting your filters.'
                                        : 'The system has no recorded safety events yet.' ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <table class="data-table" data-bulk-table>
                                <thead>
                                    <tr>
                                        <th class="col-check">
                                            <input type="checkbox" data-bulk-select-all aria-label="Select all">
                                        </th>
                                        <th>Type</th>
                                        <th>Driver</th>
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
                                            <td class="col-check">
                                                <input type="checkbox" data-row-check value="<?= (int) $v['violation_id'] ?>" aria-label="Select violation">
                                            </td>
                                            <td><?= e(format_event_type($v['event_type'])) ?></td>
                                            <td><?= e($v['driver_name'] ?? '—') ?></td>
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
                                                    <a href="<?= url('admin/violations/view.php?id=' . (int) $v['violation_id']) ?>"
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
            </form>

            <?= render_pagination($meta, 'admin/violations/index.php', [
                'q'          => $q,
                'driver_id'  => $driverId,
                'vehicle_id' => $vehicleId,
                'event_type' => $eventType,
                'severity'   => $severity,
                'status'     => $status,
                'date_from'  => $dateFrom,
                'date_to'    => $dateTo,
                'sort'       => $sort,
                'dir'        => $dir,
            ]) ?>

        </main>
    </div>
</div>

<script src="<?= asset('js/bulk-select.js') ?>?v=<?= e(APP_VERSION) ?>"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>