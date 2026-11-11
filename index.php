<?php
/**
 * ============================================================
 * LANDING PAGE
 * ============================================================
 * Public home page with login and registration options.
 * ============================================================
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Go straight to dashboard.
if (is_logged_in()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$pageTitle = 'Welcome';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-layout">

    <!-- Left: Brand panel -->
    <aside class="auth-brand">
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

    <!-- Right: Options panel -->
    <main class="auth-form-panel">
        <div class="auth-card">
            <div class="auth-card__header">
                <h2 class="auth-card__title">Welcome to <?= e(APP_NAME) ?></h2>
                <p class="auth-card__subtitle">
                    Choose how you'd like to continue.
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

            <div style="display: flex; flex-direction: column; gap: var(--space-3); margin-top: var(--space-4);">
                <a href="<?= url('login.php') ?>" class="btn btn--primary btn--block" style="padding: 14px;">
                    <i data-lucide="log-in"></i>
                    <span>Sign In</span>
                </a>
                <a href="<?= url('register.php') ?>" class="btn btn--block" style="padding: 14px; border: 1px solid var(--color-border); background: var(--color-surface);">
                    <i data-lucide="user-plus"></i>
                    <span>Create a Driver Account</span>
                </a>
            </div>

            <div style="margin-top: var(--space-6); padding-top: var(--space-5); border-top: 1px solid var(--color-border);">
                <h3 style="font-size: var(--fs-sm); font-weight: 600; margin-bottom: var(--space-3); color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.06em;">
                    What's Inside
                </h3>
                <ul class="auth-brand__features" style="color: var(--color-text);">
                    <li style="color: var(--color-text);"><i data-lucide="video"></i> AI-powered driver monitoring</li>
                    <li style="color: var(--color-text);"><i data-lucide="cpu"></i> Random Forest black-spot prediction</li>
                    <li style="color: var(--color-text);"><i data-lucide="shield-check"></i> Real-time safety alerts</li>
                </ul>
            </div>

            <p class="form-hint form-hint--center" style="margin-top: var(--space-6);">
                Registration requires admin approval before activation.
            </p>
        </div>
    </main>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>