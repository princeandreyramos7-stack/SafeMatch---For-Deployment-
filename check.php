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

/* ---- Page runner: renders an app page and prints the real PHP error ----
 * Sign in first (same browser), then open /check.php?key=check&run=dashboard */
if (isset($_GET['run'])) {
    require_once __DIR__ . '/includes/functions.php';
    $public = ['login', 'index']; // no sign-in needed
    $pages = array_merge($public, ['dashboard', 'mothers', 'newborns', 'qr-verification', 'screenings', 'assessments', 'discharge', 'users', 'activity-logs', 'profile']);
    if (!in_array($_GET['run'], $pages, true)) {
        exit("Unknown page.\n");
    }
    if (!in_array($_GET['run'], $public, true) && empty($_SESSION['user_id'])) {
        exit("You are not signed in. Sign in at /pages/login.php, then reload this URL.\n");
    }
    $show = function (string $what): void {
        while (ob_get_level()) {
            ob_end_clean();
        }
        echo "\n$what\n";
    };
    register_shutdown_function(function () use ($show) {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            $show("FATAL ERROR: {$e['message']}\n  in {$e['file']} on line {$e['line']}");
        }
    });
    set_exception_handler(function (Throwable $t) use ($show) {
        $show(get_class($t) . ': ' . $t->getMessage() . "\n  in " . $t->getFile() . ' on line ' . $t->getLine());
    });
    ob_start();
    require $_GET['run'] === 'index' ? __DIR__ . '/index.php' : __DIR__ . '/pages/' . $_GET['run'] . '.php';
    $size = strlen(ob_get_clean());
    echo "Page '{$_GET['run']}' rendered OK ($size bytes).\n";
    exit;
}

echo "SafeMatch diagnostics\n=====================\n";
line('PHP version >= 8.0', PHP_VERSION_ID >= 80000, PHP_VERSION . ' (needs 8.0+; choose it in hPanel > PHP Configuration)');
foreach (['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'gd', 'json'] as $ext) {
    line("Extension $ext", extension_loaded($ext));
}

try {
    require __DIR__ . '/config/database.php';
    line('Config loads', true, 'DB_NAME=' . DB_NAME . ' DB_HOST=' . DB_HOST . (is_file(__DIR__ . '/config/database.local.php') ? ' (using database.local.php)' : ' (no database.local.php)'));
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
