<?php
/**
 * ============================================================
 * ADMIN — PENDING DRIVER REGISTRATIONS
 * ============================================================
 * Shows all inactive driver accounts awaiting approval.
 * ============================================================
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_queries.php';

require_role('admin');

$pdo = Database::getConnection();

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action   = (string) ($_POST['action'] ?? '');
    $driverId = (int) ($_POST['driver_id'] ?? 0);

    if ($driverId > 0 && in_array($action, ['approve', 'reject'], true)) {
        // Fetch driver + linked user
        $stmt = $pdo->prepare("SELECT driver_id, user_id, full_name FROM drivers WHERE driver_id = :id");
        $stmt->execute([':id' => $driverId]);
        $driver = $stmt->fetch();

        if ($driver) {
            try {
                $pdo->beginTransaction();

                if ($action === 'approve') {
                    $stmt = $pdo->prepare("UPDATE drivers SET status = 'active' WHERE driver_id = :id");
                    $stmt->execute([':id' => $driverId]);
                    if ($driver['user_id']) {
                        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = :uid");
                        $stmt->execute([':uid' => $driver['user_id']]);
                    }
                    set_flash('success', 'Driver "' . $driver['full_name'] . '" approved.');
                } else {
                    // Reject = delete the driver and user records
                    if ($driver['user_id']) {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :uid");
                        $stmt->execute([':uid' => $driver['user_id']]);
                    }
                    $stmt = $pdo->prepare("DELETE FROM drivers WHERE driver_id = :id");
                    $stmt->execute([':id' => $driverId]);
                    set_flash('success', 'Driver "' . $driver['full_name'] . '" rejected and removed.');
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('[pending] ' . $e->getMessage());
                set_flash('error', 'Action failed. Please try again.');
            }
        }
        header('Location: ' . url('admin/drivers/pending.php'));
        exit;
    }
}

// Fetch pending drivers (inactive, with a linked user account)
$stmt = $pdo->query(
    "SELECT d.driver_id, d.full_name, d.phone, d.email, d.license_number,
            d.license_expiry, d.created_at, u.username, u.status AS user_status
     FROM drivers d
     JOIN users u ON u.user_id = d.user_id
     WHERE d.status = 'inactive'
     ORDER BY d.created_at DESC"
);
$pending = $stmt->fetchAll();

$user      = current_user();
$pageTitle = 'Pending Registrations';
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

            <div class="page-header">
                <h1 class="page-title">Pending Driver Registrations</h1>
                <p class="page-subtitle">
                    <?= count($pending) ?> registration<?= count($pending) === 1 ? '' : 's' ?> awaiting approval
                </p>
            </div>

            <?php if (empty($pending)): ?>
                <div class="section-card">
                    <div class="section-card__body">
                        <div class="empty-state">
                            <i data-lucide="check-circle-2"></i>
                            <div class="empty-state__title">All caught up</div>
                            <div class="empty-state__text">No pending registrations at this time.</div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="section-card">
                    <div class="section-card__body section-card__body--flush">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Applicant</th>
                                    <th>Username</th>
                                    <th>Contact</th>
                                    <th>License</th>
                                    <th>Registered</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending as $p): ?>
                                    <tr>
                                        <td>
                                            <div class="cell-primary"><?= e($p['full_name']) ?></div>
                                        </td>
                                        <td><code><?= e($p['username']) ?></code></td>
                                        <td>
                                            <div><?= e($p['email']) ?></div>
                                            <div class="cell-secondary"><?= e($p['phone'] ?: '—') ?></div>
                                        </td>
                                        <td>
                                            <div><?= e($p['license_number']) ?></div>
                                            <div class="cell-secondary">
                                                <?= $p['license_expiry'] ? 'Expires ' . e(date('d M Y', strtotime($p['license_expiry']))) : '—' ?>
                                            </div>
                                        </td>
                                        <td><?= e(date('d M Y, H:i', strtotime($p['created_at']))) ?></td>
                                        <td class="text-right">
                                            <form method="POST" action="<?= url('admin/drivers/pending.php') ?>" style="display: inline-flex; gap: 6px; margin: 0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="driver_id" value="<?= (int) $p['driver_id'] ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn--sm" style="background: var(--color-success); color: white; border-color: var(--color-success);">
                                                    <i data-lucide="check"></i> Approve
                                                </button>
                                                <button type="submit" name="action" value="reject" class="btn btn--sm" style="background: var(--color-danger); color: white; border-color: var(--color-danger);"
                                                        onclick="return confirm('Reject and remove this registration permanently?');">
                                                    <i data-lucide="x"></i> Reject
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>