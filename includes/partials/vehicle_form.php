<?php
/**
 * ============================================================
 * VEHICLE FORM PARTIAL
 * ============================================================
 * Expects: $formValues, $formErrors, $formAction, $formSubmitLabel
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

require_once __DIR__ . '/../vehicle_helpers.php';

$val = function (string $key, string $default = '') use ($formValues) {
    return htmlspecialchars((string) ($formValues[$key] ?? $default), ENT_QUOTES, 'UTF-8');
};
$err = function (string $key) use ($formErrors) {
    return isset($formErrors[$key])
        ? '<div class="form-error">' . htmlspecialchars($formErrors[$key], ENT_QUOTES, 'UTF-8') . '</div>'
        : '';
};
$hasError = fn(string $key) => isset($formErrors[$key]) ? ' form-input--error' : '';

// Fetch active drivers for the assign dropdown
$driverOptions = list_drivers_for_select();
$currentDriverId = (int) ($formValues['assigned_driver_id'] ?? 0);
?>

<form method="POST" action="<?= e($formAction) ?>" class="form" novalidate>
    <?= csrf_field() ?>

    <!-- Section: Vehicle identity -->
    <div class="form-section">
        <div class="form-section__header">
            <div class="form-section__title">Vehicle Identity</div>
            <div class="form-section__subtitle">Registration and manufacturer details</div>
        </div>

        <div class="form-grid">
            <div class="form-group form-group--full">
                <label for="registration_number" class="form-label">
                    Registration Number <span class="required">*</span>
                </label>
                <div class="input-wrap">
                    <i data-lucide="hash" class="input-icon"></i>
                    <input
                        type="text"
                        id="registration_number"
                        name="registration_number"
                        class="form-input<?= $hasError('registration_number') ?>"
                        value="<?= $val('registration_number') ?>"
                        placeholder="KDA 123A"
                        maxlength="20"
                        required
                        style="text-transform: uppercase;"
                    >
                </div>
                <?= $err('registration_number') ?>
            </div>

            <div class="form-group">
                <label for="make" class="form-label">Make</label>
                <div class="input-wrap">
                    <i data-lucide="factory" class="input-icon"></i>
                    <input
                        type="text"
                        id="make"
                        name="make"
                        class="form-input<?= $hasError('make') ?>"
                        value="<?= $val('make') ?>"
                        placeholder="Toyota"
                        maxlength="50"
                    >
                </div>
                <?= $err('make') ?>
            </div>

            <div class="form-group">
                <label for="model" class="form-label">Model</label>
                <div class="input-wrap">
                    <i data-lucide="car" class="input-icon"></i>
                    <input
                        type="text"
                        id="model"
                        name="model"
                        class="form-input<?= $hasError('model') ?>"
                        value="<?= $val('model') ?>"
                        placeholder="Corolla"
                        maxlength="50"
                    >
                </div>
                <?= $err('model') ?>
            </div>

            <div class="form-group">
                <label for="year" class="form-label">Year</label>
                <div class="input-wrap">
                    <i data-lucide="calendar" class="input-icon"></i>
                    <input
                        type="number"
                        id="year"
                        name="year"
                        class="form-input<?= $hasError('year') ?>"
                        value="<?= $val('year') ?>"
                        placeholder="<?= date('Y') ?>"
                        min="1980"
                        max="<?= (int) date('Y') + 1 ?>"
                    >
                </div>
                <?= $err('year') ?>
            </div>

            <div class="form-group">
                <label for="vehicle_type" class="form-label">
                    Vehicle Type <span class="required">*</span>
                </label>
                <div class="input-wrap">
                    <i data-lucide="truck" class="input-icon"></i>
                    <select id="vehicle_type" name="vehicle_type" class="form-input<?= $hasError('vehicle_type') ?>">
                        <option value="private"    <?= $val('vehicle_type') === 'private'    ? 'selected' : '' ?>>Private</option>
                        <option value="commercial" <?= $val('vehicle_type') === 'commercial' ? 'selected' : '' ?>>Commercial</option>
                        <option value="psv"        <?= $val('vehicle_type') === 'psv'        ? 'selected' : '' ?>>PSV (Public Service)</option>
                        <option value="truck"      <?= $val('vehicle_type') === 'truck'      ? 'selected' : '' ?>>Truck</option>
                        <option value="bus"        <?= $val('vehicle_type') === 'bus'        ? 'selected' : '' ?>>Bus</option>
                        <option value="other"      <?= $val('vehicle_type') === 'other'      ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <?= $err('vehicle_type') ?>
            </div>
        </div>
    </div>

    <!-- Section: Assignment -->
    <div class="form-section">
        <div class="form-section__header">
            <div class="form-section__title">Assignment & Status</div>
            <div class="form-section__subtitle">Assign a driver and set the vehicle's operational state</div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="assigned_driver_id" class="form-label">Assigned Driver</label>
                <div class="input-wrap">
                    <i data-lucide="user" class="input-icon"></i>
                    <select id="assigned_driver_id" name="assigned_driver_id" class="form-input<?= $hasError('assigned_driver_id') ?>">
                        <option value="0">— Unassigned —</option>
                        <?php foreach ($driverOptions as $d): ?>
                            <option value="<?= (int) $d['driver_id'] ?>"
                                <?= $currentDriverId === (int) $d['driver_id'] ? 'selected' : '' ?>>
                                <?= e($d['full_name']) ?> (<?= e($d['license_number']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?= $err('assigned_driver_id') ?>
            </div>

            <div class="form-group">
                <label for="status" class="form-label">
                    Status <span class="required">*</span>
                </label>
                <div class="input-wrap">
                    <i data-lucide="activity" class="input-icon"></i>
                    <select id="status" name="status" class="form-input<?= $hasError('status') ?>">
                        <option value="active"      <?= $val('status') === 'active'      ? 'selected' : '' ?>>Active</option>
                        <option value="inactive"    <?= $val('status') === 'inactive'    ? 'selected' : '' ?>>Inactive</option>
                        <option value="maintenance" <?= $val('status') === 'maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                    </select>
                </div>
                <?= $err('status') ?>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="form-actions">
        <a href="<?= url('admin/vehicles/index.php') ?>" class="btn btn--ghost">
            <i data-lucide="arrow-left"></i> Cancel
        </a>
        <button type="submit" class="btn btn--primary">
            <i data-lucide="save"></i> <?= e($formSubmitLabel) ?>
        </button>
    </div>
</form>