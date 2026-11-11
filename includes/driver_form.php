<?php
/**
 * ============================================================
 * DRIVER FORM PARTIAL
 * ============================================================
 * Expects: $formValues, $formErrors, $formAction, $formSubmitLabel
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

$val = function (string $key, string $default = '') use ($formValues) {
    return htmlspecialchars((string) ($formValues[$key] ?? $default), ENT_QUOTES, 'UTF-8');
};
$err = function (string $key) use ($formErrors) {
    return isset($formErrors[$key])
        ? '<div class="form-error">' . htmlspecialchars($formErrors[$key], ENT_QUOTES, 'UTF-8') . '</div>'
        : '';
};
$hasError = fn(string $key) => isset($formErrors[$key]) ? ' form-input--error' : '';
?>

<form method="POST" action="<?= e($formAction) ?>" class="form" novalidate>
    <?= csrf_field() ?>

    <!-- Section: Personal -->
    <div class="form-section">
        <div class="form-section__header">
            <div class="form-section__title">Personal Information</div>
            <div class="form-section__subtitle">Basic identity and contact details</div>
        </div>

        <div class="form-grid">
            <div class="form-group form-group--full">
                <label for="full_name" class="form-label">
                    Full Name <span class="required">*</span>
                </label>
                <div class="input-wrap">
                    <i data-lucide="user" class="input-icon"></i>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        class="form-input<?= $hasError('full_name') ?>"
                        value="<?= $val('full_name') ?>"
                        placeholder="e.g. John Mwangi"
                        maxlength="100"
                        required
                    >
                </div>
                <?= $err('full_name') ?>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">Phone</label>
                <div class="input-wrap">
                    <i data-lucide="phone" class="input-icon"></i>
                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        class="form-input<?= $hasError('phone') ?>"
                        value="<?= $val('phone') ?>"
                        placeholder="+254712345678"
                        maxlength="20"
                    >
                </div>
                <?= $err('phone') ?>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <div class="input-wrap">
                    <i data-lucide="mail" class="input-icon"></i>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input<?= $hasError('email') ?>"
                        value="<?= $val('email') ?>"
                        placeholder="driver@vehisafe.local"
                        maxlength="120"
                    >
                </div>
                <?= $err('email') ?>
            </div>
        </div>
    </div>

    <!-- Section: License -->
    <div class="form-section">
        <div class="form-section__header">
            <div class="form-section__title">License Information</div>
            <div class="form-section__subtitle">Driving license and compliance</div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="license_number" class="form-label">
                    License Number <span class="required">*</span>
                </label>
                <div class="input-wrap">
                    <i data-lucide="credit-card" class="input-icon"></i>
                    <input
                        type="text"
                        id="license_number"
                        name="license_number"
                        class="form-input<?= $hasError('license_number') ?>"
                        value="<?= $val('license_number') ?>"
                        placeholder="DL-KE-2024-001"
                        maxlength="50"
                        required
                    >
                </div>
                <?= $err('license_number') ?>
            </div>

            <div class="form-group">
                <label for="license_expiry" class="form-label">License Expiry</label>
                <div class="input-wrap">
                    <i data-lucide="calendar" class="input-icon"></i>
                    <input
                        type="date"
                        id="license_expiry"
                        name="license_expiry"
                        class="form-input<?= $hasError('license_expiry') ?>"
                        value="<?= $val('license_expiry') ?>"
                    >
                </div>
                <?= $err('license_expiry') ?>
            </div>
        </div>
    </div>

    <!-- Section: Account -->
    <div class="form-section">
        <div class="form-section__header">
            <div class="form-section__title">Account Status</div>
            <div class="form-section__subtitle">Driver availability in the system</div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="status" class="form-label">
                    Status <span class="required">*</span>
                </label>
                <div class="input-wrap">
                    <i data-lucide="activity" class="input-icon"></i>
                    <select id="status" name="status" class="form-input<?= $hasError('status') ?>">
                        <option value="active"    <?= $val('status') === 'active'    ? 'selected' : '' ?>>Active</option>
                        <option value="inactive"  <?= $val('status') === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $val('status') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <?= $err('status') ?>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="form-actions">
        <a href="<?= url('admin/drivers/index.php') ?>" class="btn btn--ghost">
            <i data-lucide="arrow-left"></i> Cancel
        </a>
        <button type="submit" class="btn btn--primary">
            <i data-lucide="save"></i> <?= e($formSubmitLabel) ?>
        </button>
    </div>
</form>