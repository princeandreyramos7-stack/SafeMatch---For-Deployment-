<?php
/**
 * SafeMatch - Application constants
 *
 * Values can be overridden with environment variables (useful in production)
 * so that secrets do not have to live in source control.
 */

if (defined('SAFEMATCH_LOADED')) {
    return;
}
define('SAFEMATCH_LOADED', true);

define('APP_NAME', 'SafeMatch');
define('APP_TAGLINE', 'Mother-Baby Identification System');
define('APP_VERSION', '1.0.0');

// Timezone used for PHP and for the MySQL session (keeps NOW() consistent)
define('APP_TIMEZONE', getenv('SAFEMATCH_TIMEZONE') ?: 'Asia/Manila');
date_default_timezone_set(APP_TIMEZONE);

// Secret used to sign QR payloads (HMAC). CHANGE THIS IN PRODUCTION.
// Changing it invalidates every QR band that has already been printed.
define('APP_SECRET', getenv('SAFEMATCH_SECRET') ?: '0a376ada7249e51d3881e124a55363f8735fa93bf23a7367d44c262b22f825fb');

// Absolute filesystem path to the project root
define('BASE_PATH', dirname(__DIR__));

/**
 * Work out the URL path of the project (e.g. "/Safematch") so the app runs
 * from any sub-folder without configuration. Override with SAFEMATCH_BASE_URL.
 */
function safematch_detect_base_url(): string
{
    $override = getenv('SAFEMATCH_BASE_URL');
    if ($override !== false) {
        return rtrim($override, '/');
    }
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if (!$docRoot) {
        return '';
    }
    $root = str_replace('\\', '/', $docRoot);
    $base = str_replace('\\', '/', BASE_PATH);
    if (stripos($base, $root) === 0) {
        $path = trim(substr($base, strlen($root)), '/');
        return $path === '' ? '' : '/' . $path;
    }
    return '';
}
define('BASE_URL', safematch_detect_base_url());

// Sessions
define('SESSION_NAME', 'SAFEMATCHSESSID');
define('SESSION_TIMEOUT', 30 * 60);          // 30 minutes of inactivity
define('SESSION_COOKIE_SECURE', (bool) (getenv('SAFEMATCH_HTTPS') ?: false)); // true in production (HTTPS)

// Login throttling
define('LOGIN_MAX_ATTEMPTS', 5);             // failed attempts...
define('LOGIN_LOCKOUT_MINUTES', 15);         // ...within this window locks the account/IP

// Passwords
define('PASSWORD_MIN_LENGTH', 8);

// Uploads
define('UPLOAD_DIR', BASE_PATH . '/uploads/profile_pictures');
define('UPLOAD_URL', BASE_URL . '/uploads/profile_pictures');
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // 2MB
define('UPLOAD_ALLOWED_MIME', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
]);

// Pagination
define('DEFAULT_PER_PAGE', 15);
define('MAX_PER_PAGE', 100);

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_HEALTHCARE', 'healthcare_personnel');

// Reference lists
define('BLOOD_TYPES', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']);

// Discharge readiness thresholds (percentage of all active requirements met)
define('READINESS_YELLOW_THRESHOLD', 50);
