<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('driver');

$user = current_user();
$pdo  = Database::getConnection();

// Fetch full user + driver record
$stmt = $pdo->prepare(
    "SELECT u.user_id, u.username, u.email, u.full_name, u.role, u.last_login, u.created_at,
            d.driver_id, d.phone, d.license_number, d.license_expiry, d.current_score, d.status AS driver_status
     FROM users u
     LEFT JOIN drivers d ON d.user_id = u.user_id
     WHERE u.user_id = :id LIMIT 1"
);
$stmt->execute([':id' => $user['user_id']]);
$profile = $stmt->fetch();

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../includes/partials/sidebar_driver.php'; ?>

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
            <?php require_once __DIR__ . '/../includes/flash.php'; ?>

            <div class="page-header">
                <h1 class="page-title">My Profile</h1>
                <p class="page-subtitle">Update your account information and password</p>
            </div>

            <div id="profilePage"
                 data-api-update="<?= url('api/profile/update.php') ?>"
                 data-api-password="<?= url('api/profile/password.php') ?>">

                <!-- Profile info card -->
                <div class="profile-card">
                    <div class="profile-card__avatar">
                        <i data-lucide="user"></i>
                    </div>
                    <div class="profile-card__info">
                        <h2><?= e($profile['full_name']) ?></h2>
                        <div class="profile-card__meta">
                            <span class="badge badge--info"><?= e(ucfirst($profile['role'])) ?></span>
                            <span><?= e($profile['username']) ?></span>
                        </div>
                        <div class="profile-card__meta" style="margin-top: 8px;">
                            <span class="text-subtle" style="font-size: 12px;">
                                Member since <?= e(date('d M Y', strtotime($profile['created_at']))) ?>
                            </span>
                            <?php if ($profile['last_login']): ?>
                                <span class="text-subtle" style="font-size: 12px;">
                                    · Last login <?= e(date('d M Y H:i', strtotime($profile['last_login']))) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Update profile form -->
                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Personal Information</div>
                    </div>
                    <div class="section-card__body">
                        <form id="updateProfileForm" class="form">
                            <div class="form-grid">
                                <div class="form-group form-group--full">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="full_name" class="form-input"
                                           value="<?= e($profile['full_name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-input"
                                           value="<?= e($profile['email']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-input"
                                           value="<?= e($profile['phone'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-input" value="<?= e($profile['username']) ?>" disabled>
                                    <div class="form-hint">Username cannot be changed</div>
                                </div>
                                <?php if ($profile['license_number']): ?>
                                    <div class="form-group">
                                        <label class="form-label">License Number</label>
                                        <input type="text" class="form-input" value="<?= e($profile['license_number']) ?>" disabled>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn--primary">
                                    <i data-lucide="save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Change password form -->
                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Change Password</div>
                    </div>
                    <div class="section-card__body">
                        <form id="changePasswordForm" class="form">
                            <div class="form-grid">
                                <div class="form-group form-group--full">
                                    <label class="form-label">Current Password</label>
                                    <div class="input-wrap">
                                        <i data-lucide="lock" class="input-icon"></i>
                                        <input type="password" name="current_password" class="form-input" required autocomplete="current-password">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">New Password</label>
                                    <div class="input-wrap">
                                        <i data-lucide="key" class="input-icon"></i>
                                        <input type="password" name="new_password" class="form-input" required minlength="8" autocomplete="new-password">
                                    </div>
                                    <div class="form-hint">At least 8 characters</div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Confirm New Password</label>
                                    <div class="input-wrap">
                                        <i data-lucide="key" class="input-icon"></i>
                                        <input type="password" name="confirm_password" class="form-input" required minlength="8" autocomplete="new-password">
                                    </div>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn--primary">
                                    <i data-lucide="shield-check"></i> Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<script>
    window.__VEHISAFE_CSRF__ = '<?= csrf_token() ?>';
</script>
<script src="<?= asset('js/profile.js') ?>?v=<?= e(APP_VERSION) ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
