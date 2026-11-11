<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_queries.php';
require_once __DIR__ . '/../includes/violation_helpers.php';

require_role('driver');

$user     = current_user();
$driverId = (int) ($user['driver_id'] ?? 0);

if ($driverId <= 0) {
    set_flash('error', 'Your account is not linked to a driver profile.');
    header('Location: ' . url('logout.php'));
    exit;
}

$pdo = Database::getConnection();

// Driver profile
$stmt = $pdo->prepare("SELECT * FROM drivers WHERE driver_id = :id");
$stmt->execute([':id' => $driverId]);
$driver = $stmt->fetch();

// Score history (chronological)
$stmt = $pdo->prepare(
    "SELECT score_id, score, previous_score, violation_count, reason, calculated_at
     FROM driver_safety_scores
     WHERE driver_id = :id
     ORDER BY calculated_at ASC"
);
$stmt->execute([':id' => $driverId]);
$history = $stmt->fetchAll();

// Chart payload
$chart = [
    'labels' => array_map(fn($r) => date('d M H:i', strtotime($r['calculated_at'])), $history),
    'scores' => array_map(fn($r) => (int) $r['score'], $history),
];

// Violations breakdown
$bySeverity = count_violations_by_severity($driverId);
$byStatus   = count_violations_by_status($driverId);

$pageTitle = 'Safety Score';
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
                <h1 class="page-title">My Safety Score</h1>
                <p class="page-subtitle">Track your driving safety rating over time.</p>
            </div>

            <!-- Current score -->
            <div class="section-card" style="margin-top: 0;">
                <div class="section-card__body">
                    <div class="score-hero">
                        <div class="score-hero__ring" style="--pct: <?= (int) $driver['current_score'] ?>;">
                            <div class="score-hero__value"><?= (int) $driver['current_score'] ?></div>
                            <div class="score-hero__unit">/100</div>
                        </div>
                        <div class="score-hero__text">
                            <h2 class="score-hero__title">
                                <?php
                                    $s = (int) $driver['current_score'];
                                    if ($s >= 90)      echo 'Excellent';
                                    elseif ($s >= 80)  echo 'Good';
                                    elseif ($s >= 70)  echo 'Fair';
                                    elseif ($s >= 60)  echo 'Needs Improvement';
                                    else               echo 'At Risk';
                                ?>
                            </h2>
                            <p class="score-hero__desc">
                                Your safety score reflects your driving behaviour during monitored trips.
                                Higher scores mean safer driving.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Breakdown -->
            <div class="grid grid--kpis">
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Total Violations</div>
                        <div class="kpi__value"><?= array_sum($bySeverity) ?></div>
                        <div class="kpi__meta">All-time events</div>
                    </div>
                    <div class="kpi__icon kpi__icon--info"><i data-lucide="list"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Critical Severity</div>
                        <div class="kpi__value"><?= (int) $bySeverity['critical'] ?></div>
                        <div class="kpi__meta">Highest concern</div>
                    </div>
                    <div class="kpi__icon kpi__icon--danger"><i data-lucide="alert-triangle"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Resolved</div>
                        <div class="kpi__value"><?= (int) $byStatus['resolved'] ?></div>
                        <div class="kpi__meta">Closed events</div>
                    </div>
                    <div class="kpi__icon kpi__icon--success"><i data-lucide="check-circle-2"></i></div>
                </div>
                <div class="kpi">
                    <div class="kpi__text">
                        <div class="kpi__label">Open Events</div>
                        <div class="kpi__value"><?= (int) $byStatus['open'] ?></div>
                        <div class="kpi__meta">Awaiting review</div>
                    </div>
                    <div class="kpi__icon kpi__icon--warning"><i data-lucide="alert-circle"></i></div>
                </div>
            </div>

            <!-- Trend chart -->
            <div class="section-card">
                <div class="section-card__header">
                    <div>
                        <div class="section-card__title">Score History</div>
                        <div class="section-card__subtitle">Your safety score over time</div>
                    </div>
                </div>
                <div class="section-card__body">
                    <?php if (empty($history)): ?>
                        <div class="empty-state">
                            <i data-lucide="line-chart"></i>
                            <div class="empty-state__title">No score history yet</div>
                            <div class="empty-state__text">Complete a trip to start tracking your score.</div>
                        </div>
                    <?php else: ?>
                        <div style="height: 300px;">
                            <canvas id="safetyScoreChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- History table -->
            <?php if (!empty($history)): ?>
                <div class="section-card">
                    <div class="section-card__header">
                        <div class="section-card__title">Score Log</div>
                        <div class="section-card__subtitle">Every recorded change to your score</div>
                    </div>
                    <div class="section-card__body section-card__body--flush">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Score</th>
                                    <th>Previous</th>
                                    <th>Change</th>
                                    <th>Violations</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse($history) as $h): ?>
                                    <?php
                                        $curr = (int) $h['score'];
                                        $prev = $h['previous_score'] !== null ? (int) $h['previous_score'] : null;
                                        $delta = $prev !== null ? $curr - $prev : null;
                                    ?>
                                    <tr>
                                        <td><?= e(date('d M Y, H:i', strtotime($h['calculated_at']))) ?></td>
                                        <td>
                                            <span class="badge <?= $curr >= 85 ? 'badge--success' : ($curr >= 70 ? 'badge--warning' : 'badge--danger') ?>">
                                                <?= $curr ?>
                                            </span>
                                        </td>
                                        <td><?= $prev !== null ? $prev : 'â€”' ?></td>
                                        <td>
                                            <?php if ($delta === null): ?>
                                                <span class="text-subtle">â€”</span>
                                            <?php elseif ($delta > 0): ?>
                                                <span style="color: var(--color-success); font-weight: 600;">+<?= $delta ?></span>
                                            <?php elseif ($delta < 0): ?>
                                                <span style="color: var(--color-danger); font-weight: 600;"><?= $delta ?></span>
                                            <?php else: ?>
                                                <span class="text-subtle">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= (int) $h['violation_count'] ?></td>
                                        <td><?= e($h['reason'] ?? 'â€”') ?></td>
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

<?php if (!empty($history)): ?>
<script id="safety-score-data" type="application/json">
<?= json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
<script src="<?= asset('js/safety-score-chart.js') ?>?v=<?= e(APP_VERSION) ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>