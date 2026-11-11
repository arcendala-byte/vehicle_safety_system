<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_queries.php';

require_role('admin');

$pdo = Database::getConnection();
$blackspots = $pdo->query(
    "SELECT blackspot_id, road_name, description, latitude, longitude,
            risk_level, risk_score, accident_count, road_condition,
            traffic_density, weather_typical, last_updated
     FROM accident_blackspots
     WHERE status = 'active'
     ORDER BY risk_score DESC"
)->fetchAll();

$totalAccidents = (int) $pdo->query(
    "SELECT COALESCE(SUM(accident_count),0) FROM accident_blackspots WHERE status='active'"
)->fetchColumn();

$user      = current_user();
$pageTitle = 'Black Spots';
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
                    <h1 class="page-title">Black Spots</h1>
                    <p class="page-subtitle">
                        <?= count($blackspots) ?> active locations &middot;
                        <?= (int) $totalAccidents ?> recorded accidents
                    </p>
                </div>
                <a href="<?= url('admin/blackspots/predict.php') ?>" class="btn btn--primary">
                    <i data-lucide="cpu"></i> Predict Risk for Coordinates
                </a>
            </div>

            <div class="grid grid--kpis">
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Active Black Spots</div>
                        <div class="kpi__value"><?= count($blackspots) ?></div>
                        <div class="kpi__meta">Monitored by the system</div>
                    </div>
                    <div class="kpi__icon kpi__icon--danger"><i data-lucide="map-pin"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Accidents</div>
                        <div class="kpi__value"><?= (int) $totalAccidents ?></div>
                        <div class="kpi__meta">Across all black spots</div>
                    </div>
                    <div class="kpi__icon kpi__icon--warning"><i data-lucide="alert-triangle"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">ML Model</div>
                        <div class="kpi__value" style="font-size: var(--fs-lg);">
                            Random Forest
                        </div>
                        <div class="kpi__meta">R² = 0.96</div>
                    </div>
                    <div class="kpi__icon kpi__icon--success"><i data-lucide="cpu"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Predictions</div>
                        <div class="kpi__value" style="font-size: var(--fs-lg);">
                            <a href="<?= url('admin/blackspots/predict.php') ?>" class="link-plain">
                                Test Now →
                            </a>
                        </div>
                        <div class="kpi__meta">Use the ML endpoint</div>
                    </div>
                    <div class="kpi__icon kpi__icon--accent"><i data-lucide="sparkles"></i></div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-card__body section-card__body--flush">
                    <?php if (empty($blackspots)): ?>
                        <div class="empty-state">
                            <i data-lucide="map-pin"></i>
                            <div class="empty-state__title">No black spots recorded</div>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Road</th>
                                    <th>Risk</th>
                                    <th>Score</th>
                                    <th>Accidents</th>
                                    <th>Conditions</th>
                                    <th>Location</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blackspots as $bs): ?>
                                    <tr>
                                        <td>
                                            <div class="cell-primary"><?= e($bs['road_name']) ?></div>
                                            <div class="cell-secondary"><?= e($bs['description'] ?? '') ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?= e(badge_class($bs['risk_level'])) ?>">
                                                <?= e($bs['risk_level']) ?>
                                            </span>
                                        </td>
                                        <td><?= number_format((float) $bs['risk_score'], 2) ?></td>
                                        <td><?= (int) $bs['accident_count'] ?></td>
                                        <td>
                                            <div class="cell-secondary">
                                                <?= e(ucfirst($bs['road_condition'])) ?> road,
                                                <?= e(ucfirst($bs['weather_typical'])) ?>,
                                                <?= e(ucfirst($bs['traffic_density'])) ?> traffic
                                            </div>
                                        </td>
                                        <td>
                                            <code><?= number_format((float) $bs['latitude'], 4) ?>,
                                                  <?= number_format((float) $bs['longitude'], 4) ?></code>
                                        </td>
                                        <td class="text-right">
                                            <div class="row-actions">
                                                <a href="<?= url('admin/blackspots/predict.php?lat=' . urlencode($bs['latitude']) . '&lng=' . urlencode($bs['longitude'])) ?>"
                                                   class="row-action" title="Predict risk">
                                                    <i data-lucide="cpu"></i>
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

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
