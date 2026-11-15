<?php
/**
 * SafeMatch - TEMPORARY server diagnostics. DELETE THIS FILE after use.
 * Open: /check.php?key=check
 */
if (($_GET['key'] ?? '') !== 'check') {
    http_response_code(404);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

function line(string $label, $ok, string $detail = ''): void
{
    echo str_pad($label, 34) . ($ok ? '[ OK ]  ' : '[FAIL]  ') . $detail . "\n";
}

echo "SafeMatch diagnostics\n=====================\n";
line('PHP version >= 8.0', PHP_VERSION_ID >= 80000, PHP_VERSION . ' (needs 8.0+; choose it in hPanel > PHP Configuration)');
foreach (['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'gd', 'json'] as $ext) {
    line("Extension $ext", extension_loaded($ext));
}

try {
    require __DIR__ . '/config/database.php';
    line('Config loads', true, 'DB_NAME=' . DB_NAME . ' DB_USER=' . DB_USER . ' DB_HOST=' . DB_HOST);
} catch (Throwable $e) {
    line('Config loads', false, $e->getMessage());
    exit;
}

try {
    $pdo = db();
    line('Database connection', true);
} catch (Throwable $e) {
    line('Database connection', false, $e->getMessage());
    exit;
}

$expected = ['users', 'mothers', 'newborns', 'qr_codes', 'qr_verifications', 'newborn_screening_types',
    'newborn_screenings', 'maternal_assessment_types', 'maternal_assessments', 'discharge_requirements',
    'discharge_records', 'discharge_decisions', 'activity_log', 'notifications'];
$have = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$missing = array_diff($expected, $have);
line('All 14 tables exist', !$missing, $missing ? 'MISSING: ' . implode(', ', $missing) . ' -> import database.sql' : count($have) . ' tables');

if (!$missing) {
    $n = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    line('Seed users present', $n >= 1, "$n user(s)");
    $hash = $pdo->query("SELECT password FROM users WHERE email='admin@safematch.com'")->fetchColumn();
    line('Admin password check', $hash && password_verify('admin123', $hash), $hash ? '' : 'admin user not found');
}

line('uploads/ writable', is_writable(__DIR__ . '/uploads/profile_pictures'), 'needs permission 755');
line('Session save path writable', is_writable(session_save_path() ?: sys_get_temp_dir()), session_save_path() ?: sys_get_temp_dir());
echo "\nDelete check.php when finished.\n";
