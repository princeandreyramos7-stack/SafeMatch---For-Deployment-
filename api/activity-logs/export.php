<?php
/**
 * GET /api/activity-logs/export.php
 * Same filters as list.php; downloads a CSV (max 50,000 rows).
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/activity-filters.php';

api_init('GET');

[$whereSql, $params] = activity_log_filters($_GET);

$stmt = db()->prepare(
    "SELECT a.created_at, u.name AS user_name, u.email, a.action, a.description, a.ip_address, a.user_agent
     FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
     $whereSql ORDER BY a.created_at DESC, a.id DESC LIMIT 50000"
);
$stmt->execute($params);

log_activity('ACTIVITY_EXPORT', 'Exported activity log to CSV');

$filename = 'safematch-activity-log-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly
fputcsv($out, ['Date/Time', 'User', 'Email', 'Action', 'Description', 'IP Address', 'User Agent']);

/** Neutralise spreadsheet formula injection (=, +, -, @ at the start of a cell). */
$safe = fn ($v) => (is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;

while ($row = $stmt->fetch()) {
    fputcsv($out, array_map($safe, [
        $row['created_at'], $row['user_name'] ?? 'System', $row['email'], $row['action'],
        $row['description'], $row['ip_address'], $row['user_agent'],
    ]));
}
fclose($out);
