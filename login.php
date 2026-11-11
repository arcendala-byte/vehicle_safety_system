<?php
/**
 * ============================================================
 * LOGIN PAGE — with real authentication
 * ============================================================
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Send to dashboard.
if (is_logged_in()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$errors   = [];
$loginVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $loginVal = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if ($loginVal === '' || $password === '') {
        $errors[] = 'Please enter both username and password.';
    } elseif (!verify_csrf()) {
        $errors[] = 'Security check failed. Please try again.';
    } else {
        $result = attempt_login($loginVal, $password);
        if ($result['ok']) {
            // Route based on role
            if (($result['user']['role'] ?? '') === 'admin') {
                header('Location: ' . url('admin/dashboard.php'));
            } else {
                header('Location: ' . url('driver/dashboard.php'));
            }
            exit;
        } else {
            $errors[] = $result['message'] ?? 'Invalid credentials.';
        }
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-layout">

    <!-- Left: Brand panel -->
    <aside class="auth-brand">
        <!-- Background video -->
        <video class="auth-brand__video" autoplay loop muted playsinline>
            <source src="<?= asset('images/hero.mp4') ?>" type="video/mp4">
        </video>
        <div class="auth-brand__overlay"></div>

        <div class="auth-brand__inner">
            <div class="brand-mark">
                <div class="brand-mark__icon">
                    <i data-lucide="shield-check"></i>
                </div>
                <div class="brand-mark__text">
                    <span class="brand-mark__name"><?= e(APP_NAME) ?></span>
                    <span class="brand-mark__tagline"><?= e(APP_TAGLINE) ?></span>
                </div>
            </div>

            <h1 class="auth-brand__title">
                Intelligent vehicle safety,<br>
                driven by AI.
            </h1>

            <p class="auth-brand__description">
                Real-time driver monitoring, predictive black-spot analysis,
                and integrated road-safety intelligence — all in one platform.
            </p>

            <ul class="auth-brand__features">
                <li><i data-lucide="eye"></i> Driver behaviour monitoring</li>
                <li><i data-lucide="alert-triangle"></i> Accident black-spot prediction</li>
                <li><i data-lucide="map-pin"></i> GPS-based hazard warnings</li>
                <li><i data-lucide="bar-chart-3"></i> Safety analytics &amp; reports</li>
            </ul>

            <p class="auth-brand__footer">
                &copy; <?= date('Y') ?> <?= e(APP_ORGANIZATION) ?> · Prototype v<?= e(APP_VERSION) ?>
            </p>
        </div>
    </aside>

    <!-- Right: Form panel -->
    <main class="auth-form-panel">
        <div class="auth-card">

            <div class="auth-card__header">
                <h2 class="auth-card__title">Sign in to your account</h2>
                <p class="auth-card__subtitle">
                    Enter your credentials to access the platform.
                </p>
            </div>

            <?php if (is_demo_mode()): ?>
                <div class="alert alert--info" role="status">
                    <i data-lucide="info"></i>
                    <span>
                        <strong>Demo mode.</strong>
                        Use <code>admin / Admin@123</code> or <code>driver1 / Driver@123</code>.
                    </span>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert--danger" role="alert">
                    <i data-lucide="alert-circle"></i>
                    <span><?= e(implode(' ', $errors)) ?></span>
                </div>
            <?php endif; ?>

            <form class="form" method="POST" action="<?= url('login.php') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="username" class="form-label">Username or Email</label>
                    <div class="input-wrap">
                        <i data-lucide="user" class="input-icon"></i>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-input"
                            placeholder="e.g. admin"
                            autocomplete="username"
                            value="<?= e($loginVal) ?>"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-wrap">
                        <i data-lucide="lock" class="input-icon"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="input-action" data-toggle-password aria-label="Show password">
                            <i data-lucide="eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-row">
                    <label class="checkbox">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="link-muted">Forgot password?</a>
                </div>

                                <button type="submit" class="btn btn--primary btn--block">
                    <i data-lucide="log-in"></i>
                    <span>Sign In</span>
                </button>

                <p class="form-hint form-hint--center" style="margin-top: var(--space-4);">
                    Don't have an account?
                    <a href="<?= url('register.php') ?>" class="link-plain">Register as a driver</a>
                </p>
            </form>

        </div>
    </main>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>