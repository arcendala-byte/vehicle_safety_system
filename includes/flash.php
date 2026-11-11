<?php
/**
 * ============================================================
 * FLASH MESSAGE RENDERER (FIXED)
 * ============================================================
 * Renders any pending flash messages stored in session.
 * Include this near the top of any page's content area.
 * ============================================================
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

require_once __DIR__ . '/auth.php';

start_secure_session();

$flashTypes = [
    'success' => 'check-circle-2',
    'error'   => 'alert-circle',
    'danger'  => 'alert-circle',
    'warning' => 'alert-triangle',
    'info'    => 'info',
];

// Read all pending flash messages in one pass — do NOT call get_flash()
// (which consumes) more than once per type.
$pending = [];
foreach ($flashTypes as $type => $icon) {
    if (!empty($_SESSION['flash'][$type])) {
        $pending[] = [
            'type' => $type,
            'icon' => $icon,
            'msg'  => (string) $_SESSION['flash'][$type],
        ];
        unset($_SESSION['flash'][$type]);
    }
}

if (!empty($pending)):
    foreach ($pending as $m):
        $alertClass = in_array($m['type'], ['error', 'danger'], true) ? 'danger' : $m['type'];
?>
    <div class="alert alert--<?= e($alertClass) ?>" role="alert">
        <i data-lucide="<?= e($m['icon']) ?>"></i>
        <span><?= e($m['msg']) ?></span>
    </div>
<?php
    endforeach;
endif;
?>