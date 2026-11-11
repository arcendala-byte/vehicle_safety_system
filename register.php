<?php
/**
 * ============================================================
 * DRIVER SELF-REGISTRATION
 * ============================================================
 * Flow: Driver signs up → account created inactive →
 *       Admin approves → driver can log in.
 * ============================================================
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/validation.php';

// Already logged in? Send to dashboard.
if (is_logged_in()) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$errors   = [];
$success  = false;
$formVals = [
    'full_name'      => '',
    'email'          => '',
    'phone'          => '',
    'license_number' => '',
    'license_expiry' => '',
    'username'       => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!verify_csrf()) {
        $errors['_general'] = 'Security check failed. Please try again.';
    } else {
        // Collect
        $formVals = [
            'full_name'      => clean((string) ($_POST['full_name']      ?? '')),
            'email'          => clean((string) ($_POST['email']          ?? '')),
            'phone'          => clean((string) ($_POST['phone']          ?? '')),
            'license_number' => clean((string) ($_POST['license_number'] ?? '')),
            'license_expiry' => clean((string) ($_POST['license_expiry'] ?? '')),
            'username'       => clean((string) ($_POST['username']       ?? '')),
        ];
        $password = (string) ($_POST['password']         ?? '');
        $confirm  = (string) ($_POST['confirm_password'] ?? '');

        // Validate
        $v = new Validator($_POST);
        $v->required('full_name', 'Full name')->max('full_name', 100)
          ->required('username', 'Username')->min('username', 3)->max('username', 50)
          ->required('email', 'Email')->email('email')
          ->required('license_number', 'License number')->max('license_number', 50)
          ->phone('phone')
          ->date('license_expiry', 'License expiry')
          ->required('password', 'Password')->min('password', 8);

        if ($password !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        $errors = array_merge($errors, $v->errors());

        // Uniqueness checks
        if (empty($errors)) {
            $pdo = Database::getConnection();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
            $stmt->execute([':u' => $formVals['username']]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errors['username'] = 'That username is already taken.';
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :e");
            $stmt->execute([':e' => $formVals['email']]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errors['email'] = 'That email is already registered.';
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM drivers WHERE license_number = :l");
            $stmt->execute([':l' => $formVals['license_number']]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errors['license_number'] = 'That license number is already registered.';
            }
        }

        // Insert if clean
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1. Create user account (inactive — pending admin approval)
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users
                        (username, email, password_hash, role, full_name, status)
                     VALUES
                        (:u, :e, :h, 'driver', :fn, 'inactive')"
                );
                $stmt->execute([
                    ':u'  => $formVals['username'],
                    ':e'  => $formVals['email'],
                    ':h'  => $hash,
                    ':fn' => $formVals['full_name'],
                ]);
                $userId = (int) $pdo->lastInsertId();

                // 2. Create linked driver record (also inactive)
                $stmt = $pdo->prepare(
                    "INSERT INTO drivers
                        (user_id, full_name, phone, email, license_number, license_expiry, status, current_score)
                     VALUES
                        (:uid, :fn, :ph, :em, :lic, :exp, 'inactive', 100)"
                );
                $stmt->execute([
                    ':uid' => $userId,
                    ':fn'  => $formVals['full_name'],
                    ':ph'  => $formVals['phone'] ?: null,
                    ':em'  => $formVals['email'],
                    ':lic' => $formVals['license_number'],
                    ':exp' => $formVals['license_expiry'] ?: null,
                ]);

                $pdo->commit();

                // Log the registration
                log_event('DRIVER_REGISTERED', 'username=' . $formVals['username']);

                $success = true;
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('[register] ' . $e->getMessage());
                $errors['_general'] = 'Registration failed. Please try again.';
            }
        }
    }
}

$pageTitle = 'Register';
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
                Join the movement<br>
                for safer roads.
            </h1>

            <p class="auth-brand__description">
                Register as a driver to access real-time safety monitoring,
                AI-powered alerts, and road safety intelligence.
            </p>

            <ul class="auth-brand__features">
                <li><i data-lucide="shield-check"></i> Personal safety score tracking</li>
                <li><i data-lucide="bell"></i> Real-time driving alerts</li>
                <li><i data-lucide="map-pin"></i> Black-spot warnings on your route</li>
                <li><i data-lucide="bar-chart-3"></i> Trip history &amp; analytics</li>
            </ul>

            <p class="auth-brand__footer">
                &copy; <?= date('Y') ?> <?= e(APP_ORGANIZATION) ?> · Prototype v<?= e(APP_VERSION) ?>
            </p>
        </div>
    </aside>

    <!-- Right: Form panel -->
    <main class="auth-form-panel">
        <div class="auth-card" style="max-width: 520px;">
            <?php if ($success): ?>
                <div class="auth-card__header" style="text-align: center;">
                    <div style="width: 72px; height: 72px; border-radius: 50%; background: var(--color-success-bg); color: var(--color-success); display: grid; place-items: center; margin: 0 auto var(--space-4);">
                        <i data-lucide="check-circle-2" style="width: 36px; height: 36px;"></i>
                    </div>
                    <h2 class="auth-card__title">Registration Submitted</h2>
                    <p class="auth-card__subtitle">
                        Your account is pending admin approval. You will be able to log in once it's reviewed.
                    </p>
                </div>
                <div class="alert alert--info">
                    <i data-lucide="info"></i>
                    <span>You'll receive an email once your account is approved (or check with your administrator).</span>
                </div>
                <a href="<?= url('login.php') ?>" class="btn btn--primary btn--block">
                    <i data-lucide="log-in"></i> Back to Sign In
                </a>
            <?php else: ?>
                <div class="auth-card__header">
                    <h2 class="auth-card__title">Create your driver account</h2>
                    <p class="auth-card__subtitle">
                        All registrations require admin approval before activation.
                    </p>
                </div>

                <?php if (!empty($errors['_general'])): ?>
                    <div class="alert alert--danger">
                        <i data-lucide="alert-circle"></i>
                        <span><?= e($errors['_general']) ?></span>
                    </div>
                <?php endif; ?>

                <form class="form" method="POST" action="<?= url('register.php') ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="form-grid">
                        <div class="form-group form-group--full">
                            <label class="form-label">Full Name <span class="required">*</span></label>
                            <div class="input-wrap">
                                <i data-lucide="user" class="input-icon"></i>
                                <input type="text" name="full_name" class="form-input"
                                       placeholder="e.g. John Mwangi"
                                       value="<?= e($formVals['full_name']) ?>" required>
                            </div>
                            <?php if (!empty($errors['full_name'])): ?>
                                <div class="form-error"><?= e($errors['full_name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Username <span class="required">*</span></label>
                            <div class="input-wrap">
                                <i data-lucide="at-sign" class="input-icon"></i>
                                <input type="text" name="username" class="form-input"
                                       placeholder="e.g. jmwangi"
                                       value="<?= e($formVals['username']) ?>" required minlength="3">
                            </div>
                            <?php if (!empty($errors['username'])): ?>
                                <div class="form-error"><?= e($errors['username']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <div class="input-wrap">
                                <i data-lucide="mail" class="input-icon"></i>
                                <input type="email" name="email" class="form-input"
                                       placeholder="you@example.com"
                                       value="<?= e($formVals['email']) ?>" required>
                            </div>
                            <?php if (!empty($errors['email'])): ?>
                                <div class="form-error"><?= e($errors['email']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <div class="input-wrap">
                                <i data-lucide="phone" class="input-icon"></i>
                                <input type="text" name="phone" class="form-input"
                                       placeholder="+254712345678"
                                       value="<?= e($formVals['phone']) ?>">
                            </div>
                            <?php if (!empty($errors['phone'])): ?>
                                <div class="form-error"><?= e($errors['phone']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label">License Number <span class="required">*</span></label>
                            <div class="input-wrap">
                                <i data-lucide="credit-card" class="input-icon"></i>
                                <input type="text" name="license_number" class="form-input"
                                       placeholder="DL-KE-2024-001"
                                       value="<?= e($formVals['license_number']) ?>" required>
                            </div>
                            <?php if (!empty($errors['license_number'])): ?>
                                <div class="form-error"><?= e($errors['license_number']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label">License Expiry</label>
                            <div class="input-wrap">
                                <i data-lucide="calendar" class="input-icon"></i>
                                <input type="date" name="license_expiry" class="form-input"
                                       value="<?= e($formVals['license_expiry']) ?>">
                            </div>
                            <?php if (!empty($errors['license_expiry'])): ?>
                                <div class="form-error"><?= e($errors['license_expiry']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Password <span class="required">*</span></label>
                            <div class="input-wrap">
                                <i data-lucide="lock" class="input-icon"></i>
                                <input type="password" name="password" class="form-input"
                                       placeholder="At least 8 characters" required minlength="8">
                            </div>
                            <?php if (!empty($errors['password'])): ?>
                                <div class="form-error"><?= e($errors['password']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirm Password <span class="required">*</span></label>
                            <div class="input-wrap">
                                <i data-lucide="lock" class="input-icon"></i>
                                <input type="password" name="confirm_password" class="form-input"
                                       placeholder="Re-enter password" required minlength="8">
                            </div>
                            <?php if (!empty($errors['confirm_password'])): ?>
                                <div class="form-error"><?= e($errors['confirm_password']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primary btn--block" style="margin-top: var(--space-4);">
                        <i data-lucide="user-plus"></i> Create Account
                    </button>

                    <p class="form-hint form-hint--center" style="margin-top: var(--space-4);">
                        Already have an account?
                        <a href="<?= url('login.php') ?>" class="link-plain">Sign in here</a>
                    </p>
                </form>
            <?php endif; ?>
        </div>
    </main>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>