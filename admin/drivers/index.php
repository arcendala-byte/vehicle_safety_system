<?php
/**
 * ============================================================
 * ADMIN — DRIVER LIST
 * ============================================================
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/pagination.php';
require_once __DIR__ . '/../../includes/db_queries.php';

require_role('admin');

// Handle quick approve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_approve_id'])) {
    if (verify_csrf()) {
        $approveId = (int) $_POST['quick_approve_id'];
        $pdo = Database::getConnection();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE drivers SET status = 'active' WHERE driver_id = :id");
            $stmt->execute([':id' => $approveId]);
            $stmt = $pdo->prepare("UPDATE users u JOIN drivers d ON d.user_id = u.user_id SET u.status = 'active' WHERE d.driver_id = :id");
            $stmt->execute([':id' => $approveId]);
            $pdo->commit();
            set_flash('success', 'Driver approved successfully.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Approval failed.');
        }
        header('Location: ' . url('admin/drivers/index.php'));
        exit;
    }
}

// Filters
$q       = trim((string) ($_GET['q'] ?? ''));
$status  = (string) ($_GET['status'] ?? '');
$sort    = (string) ($_GET['sort'] ?? 'created');
$dir     = (string) ($_GET['dir']  ?? 'desc');
$page    = max(1, (int) ($_GET['page'] ?? 1));

$result = list_drivers([
    'q'        => $q,
    'status'   => $status,
    'sort'     => $sort,
    'dir'      => $dir,
    'page'     => $page,
    'per_page' => 10,
]);

$drivers = $result['rows'];
$meta    = $result['meta'];

$user      = current_user();
$pageTitle = 'Drivers';

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
                    <h1 class="page-title">Drivers</h1>
                    <p class="page-subtitle"><?= (int) $meta['total'] ?> driver<?= $meta['total'] === 1 ? '' : 's' ?> registered</p>
                </div>
                <a href="<?= url('admin/drivers/create.php') ?>" class="btn btn--primary">
                    <i data-lucide="plus"></i> Add Driver
                </a>
            </div>

            <form class="filter-bar" method="GET" action="<?= url('admin/drivers/index.php') ?>">
                <div class="filter-bar__search">
                    <i data-lucide="search" class="filter-bar__search-icon"></i>
                    <input type="text" name="q" class="form-input" placeholder="Search name, phone, license, email…" value="<?= e($q) ?>" autocomplete="off">
                </div>
                <select name="status" class="form-input filter-bar__select">
                    <option value="">All statuses</option>
                    <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
                    <option value="inactive"  <?= $status === 'inactive'  ? 'selected' : '' ?>>Inactive / Pending</option>
                    <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
                <select name="sort" class="form-input filter-bar__select">
                    <option value="created" <?= $sort === 'created' ? 'selected' : '' ?>>Newest first</option>
                    <option value="name"    <?= $sort === 'name'    ? 'selected' : '' ?>>Name A–Z</option>
                    <option value="score"   <?= $sort === 'score'   ? 'selected' : '' ?>>Safety score</option>
                </select>
                <button type="submit" class="btn"><i data-lucide="filter"></i> Apply</button>
                <?php if ($q !== '' || $status !== ''): ?>
                    <a href="<?= url('admin/drivers/index.php') ?>" class="btn btn--ghost"><i data-lucide="x"></i> Clear</a>
                <?php endif; ?>
            </form>

            <div class="section-card">
                <div class="section-card__body section-card__body--flush">
                    <?php if (empty($drivers)): ?>
                        <div class="empty-state">
                            <i data-lucide="users"></i>
                            <div class="empty-state__title">No drivers found</div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Driver</th>
                                    <th>Contact</th>
                                    <th>License</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($drivers as $d): ?>
                                    <tr>
                                        <td>
                                            <div class="cell-primary"><?= e($d['full_name']) ?></div>
                                            <div class="cell-secondary">ID #<?= (int) $d['driver_id'] ?></div>
                                        </td>
                                        <td>
                                            <div><?= e($d['phone'] ?: '—') ?></div>
                                            <div class="cell-secondary"><?= e($d['email'] ?: '—') ?></div>
                                        </td>
                                        <td>
                                            <div><?= e($d['license_number']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?= (int) $d['current_score'] >= 85 ? 'badge--success' : 'badge--warning' ?>">
                                                <?= (int) $d['current_score'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= e(badge_class($d['status'])) ?>">
                                                <?= e(format_label($d['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <div class="row-actions">
                                                <?php if ($d['status'] === 'inactive'): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="quick_approve_id" value="<?= (int) $d['driver_id'] ?>">
                                                        <button type="submit" class="row-action" style="color: var(--color-success);" title="Approve Driver">
                                                            <i data-lucide="check-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <a href="<?= url('admin/drivers/view.php?id=' . (int) $d['driver_id']) ?>" class="row-action" title="View">
                                                    <i data-lucide="eye"></i>
                                                </a>
                                                <a href="<?= url('admin/drivers/edit.php?id=' . (int) $d['driver_id']) ?>" class="row-action" title="Edit">
                                                    <i data-lucide="pencil"></i>
                                                </a>
                                                <a href="<?= url('admin/drivers/delete.php?id=' . (int) $d['driver_id']) ?>" class="row-action row-action--danger" title="Delete">
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
            
            <?= render_pagination($meta, 'admin/drivers/index.php', ['q' => $q, 'status' => $status, 'sort' => $sort]) ?>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>