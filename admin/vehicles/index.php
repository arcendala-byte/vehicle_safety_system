<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/pagination.php';
require_once __DIR__ . '/../../includes/db_queries.php';

require_role('admin');

$q      = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$type   = (string) ($_GET['type']   ?? '');
$sort   = (string) ($_GET['sort']   ?? 'created');
$dir    = (string) ($_GET['dir']    ?? 'desc');
$page   = max(1, (int) ($_GET['page'] ?? 1));

$result = list_vehicles([
    'q'        => $q,
    'status'   => $status,
    'type'     => $type,
    'sort'     => $sort,
    'dir'      => $dir,
    'page'     => $page,
    'per_page' => 10,
]);

$vehicles = $result['rows'];
$meta     = $result['meta'];

$user      = current_user();
$pageTitle = 'Vehicles';

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
                    <h1 class="page-title">Vehicles</h1>
                    <p class="page-subtitle">
                        <?= (int) $meta['total'] ?> vehicle<?= $meta['total'] === 1 ? '' : 's' ?> registered
                    </p>
                </div>
                <a href="<?= url('admin/vehicles/create.php') ?>" class="btn btn--primary">
                    <i data-lucide="plus"></i> Add Vehicle
                </a>
            </div>

            <form class="filter-bar" method="GET" action="<?= url('admin/vehicles/index.php') ?>">
                <div class="filter-bar__search">
                    <i data-lucide="search" class="filter-bar__search-icon"></i>
                    <input
                        type="text"
                        name="q"
                        class="form-input"
                        placeholder="Search reg no, make, modelâ€¦"
                        value="<?= e($q) ?>"
                        autocomplete="off"
                    >
                </div>

                <select name="type" class="form-input filter-bar__select">
                    <option value="">All types</option>
                    <?php foreach (['private','commercial','psv','truck','bus','other'] as $t): ?>
                        <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>>
                            <?= ucfirst($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="form-input filter-bar__select">
                    <option value="">All statuses</option>
                    <option value="active"      <?= $status === 'active'      ? 'selected' : '' ?>>Active</option>
                    <option value="inactive"    <?= $status === 'inactive'    ? 'selected' : '' ?>>Inactive</option>
                    <option value="maintenance" <?= $status === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                </select>

                <select name="sort" class="form-input filter-bar__select">
                    <option value="created" <?= $sort === 'created' ? 'selected' : '' ?>>Newest first</option>
                    <option value="reg"     <?= $sort === 'reg'     ? 'selected' : '' ?>>Reg No</option>
                    <option value="make"    <?= $sort === 'make'    ? 'selected' : '' ?>>Make</option>
                    <option value="type"    <?= $sort === 'type'    ? 'selected' : '' ?>>Type</option>
                </select>

                <button type="submit" class="btn">
                    <i data-lucide="filter"></i> Apply
                </button>

                <?php if ($q !== '' || $status !== '' || $type !== '' || $sort !== 'created'): ?>
                    <a href="<?= url('admin/vehicles/index.php') ?>" class="btn btn--ghost">
                        <i data-lucide="x"></i> Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="section-card">
                <div class="section-card__body section-card__body--flush">
                    <?php if (empty($vehicles)): ?>
                        <div class="empty-state">
                            <i data-lucide="car"></i>
                            <div class="empty-state__title">No vehicles found</div>
                            <div class="empty-state__text">
                                <?= $q !== '' || $status !== '' || $type !== ''
                                    ? 'Try adjusting your search or filters.'
                                    : 'Add your first vehicle to get started.' ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Reg No</th>
                                    <th>Vehicle</th>
                                    <th>Type</th>
                                    <th>Assigned Driver</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vehicles as $v): ?>
                                    <tr>
                                        <td>
                                            <div class="cell-primary"><?= e($v['registration_number']) ?></div>
                                            <div class="cell-secondary">
                                                <?= $v['year'] ? (int) $v['year'] : 'â€”' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?= e(trim(($v['make'] ?? '') . ' ' . ($v['model'] ?? ''))) ?: 'â€”' ?></div>
                                        </td>
                                        <td><?= e(ucfirst($v['vehicle_type'])) ?></td>
                                        <td>
                                            <?php if ($v['driver_name']): ?>
                                                <a href="<?= url('admin/drivers/view.php?id=' . (int) $v['driver_id']) ?>"
                                                   class="link-plain">
                                                    <?= e($v['driver_name']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-subtle">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= e(badge_class($v['status'])) ?>">
                                                <?= e(format_label($v['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <div class="row-actions">
                                                <a href="<?= url('admin/vehicles/view.php?id=' . (int) $v['vehicle_id']) ?>"
                                                   class="row-action" title="View">
                                                    <i data-lucide="eye"></i>
                                                </a>
                                                <a href="<?= url('admin/vehicles/edit.php?id=' . (int) $v['vehicle_id']) ?>"
                                                   class="row-action" title="Edit">
                                                    <i data-lucide="pencil"></i>
                                                </a>
                                                <a href="<?= url('admin/vehicles/delete.php?id=' . (int) $v['vehicle_id']) ?>"
                                                   class="row-action row-action--danger"  title="Delete" data-confirm="Are you sure you want to delete this item?">
                                                    <i data-lucide="trash-2"></i>
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

            <?php
                echo render_pagination($meta, 'admin/vehicles/index.php', [
                    'q'      => $q,
                    'status' => $status,
                    'type'   => $type,
                    'sort'   => $sort,
                    'dir'    => $dir,
                ]);
            ?>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>