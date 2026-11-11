<?php
/**
 * ============================================================
 * API: CSV Report Export
 * ============================================================
 * GET /api/reports/export.php?type=<type>&from=YYYY-MM-DD&to=YYYY-MM-DD
 * Types: driver-safety, violations, trips, blackspots
 * ============================================================
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/reports_helpers.php';

require_role('admin');

$type     = (string) ($_GET['type']   ?? '');
$dateFrom = (string) ($_GET['from']   ?? '');
$dateTo   = (string) ($_GET['to']     ?? '');

$rows = [];
$filename = 'report_' . date('Ymd_His') . '.csv';

switch ($type) {
    case 'driver-safety':
        $rows = report_driver_safety($dateFrom ?: null, $dateTo ?: null);
        $filename = 'driver_safety_' . date('Ymd') . '.csv';
        break;
    case 'violations':
        $rows = report_violations([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ]);
        $filename = 'violations_' . date('Ymd') . '.csv';
        break;
    case 'trips':
        $rows = report_trips($dateFrom ?: null, $dateTo ?: null);
        $filename = 'trips_' . date('Ymd') . '.csv';
        break;
    case 'blackspots':
        $rows = report_blackspots();
        $filename = 'blackspots_' . date('Ymd') . '.csv';
        break;
    default:
        http_response_code(400);
        exit('Unknown report type.');
}

// Force CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

if (!empty($rows)) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
} else {
    fputcsv($out, ['No data']);
}

fclose($out);
exit;
