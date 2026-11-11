<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db_queries.php';

require_role('admin');

$prefill = [
    'latitude'        => $_GET['lat']  ?? '-1.2921',
    'longitude'       => $_GET['lng']  ?? '36.8219',
    'road_condition'  => 'fair',
    'weather'         => 'clear',
    'traffic_density' => 'medium',
    'accident_count'  => '0',
];

$prediction = null;
$error      = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prefill = [
        'latitude'        => $_POST['latitude']        ?? '',
        'longitude'       => $_POST['longitude']       ?? '',
        'road_condition'  => $_POST['road_condition']  ?? 'fair',
        'weather'         => $_POST['weather']         ?? 'clear',
        'traffic_density' => $_POST['traffic_density'] ?? 'medium',
        'accident_count'  => $_POST['accident_count']  ?? '0',
    ];

    $lat = (float) $prefill['latitude'];
    $lng = (float) $prefill['longitude'];

    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        $error = 'Coordinates out of range.';
    } else {
        $payload = [
            'latitude'        => $lat,
            'longitude'       => $lng,
            'road_condition'  => $prefill['road_condition'],
            'weather'         => $prefill['weather'],
            'traffic_density' => $prefill['traffic_density'],
            'accident_count'  => (int) $prefill['accident_count'],
        ];

        $ch = curl_init(AI_SERVICE_URL . '/api/predict/blackspot');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-API-Key: ' . AI_API_KEY,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if ($httpCode === 200 && !empty($decoded['success'])) {
            $prediction = $decoded['prediction'];
        } else {
            $error = $decoded['message'] ?? 'Prediction failed';
        }
    }
}

$user      = current_user();
$pageTitle = 'Predict Black-Spot Risk';
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
                <nav class="breadcrumb">
                    <a href="<?= url('admin/dashboard.php') ?>">Dashboard</a>
                    <span class="breadcrumb__sep">/</span>
                    <a href="<?= url('admin/blackspots/index.php') ?>">Black Spots</a>
                    <span class="breadcrumb__sep">/</span>
                    <span>Predict</span>
                </nav>
                <h1 class="page-title">Predict Risk for Any Coordinates</h1>
                <p class="page-subtitle">ML model trained on 170 accident records — R² = 0.96</p>
            </div>

            <div class="split-grid">

                <div class="section-card" style="margin-top: 0;">
                    <div class="section-card__header">
                        <div class="section-card__title">Input Features</div>
                    </div>
                    <div class="section-card__body">
                        <form method="POST" action="<?= url('admin/blackspots/predict.php') ?>" class="form">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Latitude</label>
                                    <input type="text" name="latitude" class="form-input"
                                           value="<?= e($prefill['latitude']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Longitude</label>
                                    <input type="text" name="longitude" class="form-input"
                                           value="<?= e($prefill['longitude']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Road Condition</label>
                                    <select name="road_condition" class="form-input">
                                        <?php foreach (['good','fair','poor'] as $v): ?>
                                            <option value="<?= $v ?>" <?= $prefill['road_condition'] === $v ? 'selected' : '' ?>>
                                                <?= ucfirst($v) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Weather</label>
                                    <select name="weather" class="form-input">
                                        <?php foreach (['clear','rain','fog','mixed'] as $v): ?>
                                            <option value="<?= $v ?>" <?= $prefill['weather'] === $v ? 'selected' : '' ?>>
                                                <?= ucfirst($v) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Traffic Density</label>
                                    <select name="traffic_density" class="form-input">
                                        <?php foreach (['low','medium','high'] as $v): ?>
                                            <option value="<?= $v ?>" <?= $prefill['traffic_density'] === $v ? 'selected' : '' ?>>
                                                <?= ucfirst($v) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Historical Accidents</label>
                                    <input type="number" name="accident_count" class="form-input"
                                           value="<?= e($prefill['accident_count']) ?>" min="0" max="100">
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn--primary">
                                    <i data-lucide="cpu"></i> Predict Risk
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="section-card" style="margin-top: 0;">
                    <div class="section-card__header">
                        <div class="section-card__title">Prediction</div>
                    </div>
                    <div class="section-card__body">
                        <?php if ($error): ?>
                            <div class="alert alert--danger">
                                <i data-lucide="alert-circle"></i>
                                <span><?= e($error) ?></span>
                            </div>
                        <?php elseif ($prediction): ?>
                            <?php
                                $score = (float) $prediction['risk_score'];
                                $level = $prediction['risk_level'];
                                $pct   = (int) round($score * 100);
                            ?>
                            <div style="text-align: center; margin-bottom: 24px;">
                                <div style="
                                    width: 140px;
                                    height: 140px;
                                    border-radius: 50%;
                                    margin: 0 auto;
                                    display: grid;
                                    place-items: center;
                                    background: conic-gradient(
                                        <?= $level === 'CRITICAL' ? '#7f1d1d' :
                                            ($level === 'HIGH' ? '#dc2626' :
                                            ($level === 'MEDIUM' ? '#d97706' : '#16a34a')) ?>
                                        <?= $pct ?>%,
                                        var(--color-border) 0
                                    );
                                    position: relative;
                                ">
                                    <div style="
                                        position: absolute;
                                        inset: 12px;
                                        background: var(--color-surface);
                                        border-radius: 50%;
                                        display: grid;
                                        place-items: center;
                                        flex-direction: column;
                                    ">
                                        <div style="font-size: 1.75rem; font-weight: 600;">
                                            <?= $pct ?>
                                        </div>
                                        <div style="font-size: 10px; color: var(--color-text-muted);">RISK</div>
                                    </div>
                                </div>
                                <div style="margin-top: 12px;">
                                    <span class="badge <?= e(badge_class($level)) ?>" style="font-size: 14px; padding: 6px 14px;">
                                        <?= e($level) ?>
                                    </span>
                                </div>
                            </div>

                            <dl class="detail-list">
                                <div><dt>Latitude</dt><dd><?= number_format((float) $prediction['latitude'], 6) ?></dd></div>
                                <div><dt>Longitude</dt><dd><?= number_format((float) $prediction['longitude'], 6) ?></dd></div>
                                <div><dt>Risk Score</dt><dd><?= number_format($score, 3) ?></dd></div>
                                <div><dt>Risk Level</dt><dd>
                                    <span class="badge <?= e(badge_class($level)) ?>"><?= e($level) ?></span>
                                </dd></div>
                                <div><dt>Road</dt><dd><?= e(ucfirst($prediction['features_used']['road_condition'])) ?></dd></div>
                                <div><dt>Weather</dt><dd><?= e(ucfirst($prediction['features_used']['weather'])) ?></dd></div>
                                <div><dt>Traffic</dt><dd><?= e(ucfirst($prediction['features_used']['traffic_density'])) ?></dd></div>
                                <div><dt>Accidents</dt><dd><?= (int) $prediction['features_used']['accident_count'] ?></dd></div>
                            </dl>
                        <?php else: ?>
                            <div class="empty-state">
                                <i data-lucide="cpu"></i>
                                <div class="empty-state__title">No prediction yet</div>
                                <div class="empty-state__text">
                                    Fill in coordinates and conditions on the left, then click Predict Risk.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
