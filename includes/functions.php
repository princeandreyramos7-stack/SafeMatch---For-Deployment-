<?php
/**
 * SafeMatch - Shared helper functions
 *
 * Every page and API endpoint includes this file (directly or via auth.php).
 * It boots the session, sends security headers and provides helpers for
 * escaping, JSON responses, CSRF, validation, logging and notifications.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

/* -------------------------------------------------------------------------
 * Bootstrapping: security headers + secure session
 * ---------------------------------------------------------------------- */

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(self)');
}

start_secure_session();

/**
 * Starts the session with hardened cookie settings and enforces the
 * inactivity timeout. Sets $_SESSION['timed_out'] when the session expired.
 */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(SESSION_NAME);
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => SESSION_COOKIE_SECURE,
        'cookie_samesite' => 'Lax',
        'cookie_path'     => (BASE_URL === '' ? '/' : BASE_URL . '/'),
        'use_strict_mode' => true,
        'use_only_cookies' => true,
        'gc_maxlifetime'  => SESSION_TIMEOUT,
    ]);

    $now = time();
    if (isset($_SESSION['user_id'], $_SESSION['last_activity'])
        && ($now - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        // Inactive too long: wipe the session but remember why.
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['timed_out'] = true;
    }

    // Background polling (e.g. notifications) defines SM_PASSIVE_REQUEST so it
    // does not keep an idle session alive forever.
    if (!defined('SM_PASSIVE_REQUEST') || !isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = $now;
    }
}

/** Seconds left before the session times out for inactivity. */
function session_seconds_left(): int
{
    return max(0, SESSION_TIMEOUT - (time() - (int) ($_SESSION['last_activity'] ?? time())));
}

/* -------------------------------------------------------------------------
 * Output helpers
 * ---------------------------------------------------------------------- */

/** Escapes a value for safe HTML output. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Builds an absolute URL path inside the app, e.g. url('pages/mothers.php'). */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Formats a date/datetime string for display. */
function format_date(?string $value, string $format = 'M d, Y'): string
{
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '';
}

/** "Maria Santos" style name from a row containing first/middle/last name. */
function full_name(array $row, bool $withMiddle = false, string $prefix = ''): string
{
    $first  = trim((string) ($row[$prefix . 'first_name'] ?? ''));
    $middle = trim((string) ($row[$prefix . 'middle_name'] ?? ''));
    $last   = trim((string) ($row[$prefix . 'last_name'] ?? ''));
    $parts  = [$first];
    if ($withMiddle && $middle !== '') {
        $parts[] = $middle;
    }
    $parts[] = $last;
    return trim(implode(' ', array_filter($parts, fn ($p) => $p !== '')));
}

/** Display name for a newborn; unnamed babies become "Baby Boy/Girl <Last>". */
function newborn_display_name(array $row): string
{
    if (trim((string) ($row['first_name'] ?? '')) !== '') {
        return full_name($row);
    }
    $label = ($row['sex'] ?? '') === 'Female' ? 'Baby Girl' : 'Baby Boy';
    return trim($label . ' ' . ($row['last_name'] ?? ''));
}

/* -------------------------------------------------------------------------
 * JSON API helpers (standard response envelope)
 * ---------------------------------------------------------------------- */

function json_response(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success($data = null, string $message = 'Operation successful', int $status = 200): void
{
    json_response(['success' => true, 'message' => $message, 'data' => $data], $status);
}

function json_error(string $message, int $status = 400, array $errors = []): void
{
    $payload = ['success' => false, 'message' => $message];
    if ($errors) {
        $payload['errors'] = $errors;
    }
    json_response($payload, $status);
}

function json_paginated(array $rows, int $total, int $page, int $perPage, array $extra = []): void
{
    json_response(array_merge([
        'success'    => true,
        'data'       => $rows,
        'pagination' => [
            'current_page' => $page,
            'total_pages'  => max(1, (int) ceil($total / $perPage)),
            'per_page'     => $perPage,
            'total'        => $total,
        ],
    ], $extra));
}

/**
 * Common setup for every API endpoint:
 *  - JSON error handling for uncaught exceptions
 *  - allowed HTTP method(s)
 *  - authentication (+ optional admin role)
 *  - CSRF validation for state-changing requests
 *
 * @param string|array $methods Allowed method(s), e.g. 'POST' or ['GET','POST']
 */
function api_init($methods = 'GET', bool $requireAuth = true, bool $adminOnly = false): void
{
    set_exception_handler(function (Throwable $e) {
        error_log('[SafeMatch API] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        json_error('An unexpected server error occurred. Please try again.', 500);
    });

    $methods = (array) $methods;
    $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        json_error('Method not allowed.', 405);
    }

    if ($requireAuth) {
        api_require_login();
        if ($adminOnly) {
            api_require_admin();
        }
    }

    if ($method !== 'GET' && !verify_csrf()) {
        // 403 (not the non-standard 419, which Apache rewrites to 500); the
        // "code" lets the front end recognise it and reload the page.
        json_response([
            'success' => false,
            'code'    => 'csrf_invalid',
            'message' => 'Invalid or missing security token. Please refresh the page and try again.',
        ], 403);
    }
}

/**
 * Returns the request payload, merging JSON bodies and form posts.
 */
function request_data(): array
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }
    $data = $_POST;
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $json = json_decode(file_get_contents('php://input'), true);
        if (is_array($json)) {
            $data = array_merge($data, $json);
        }
    }
    return $data;
}

/** Fetches a trimmed string value from the request payload. */
function input(string $key, $default = null)
{
    $data = request_data();
    if (!array_key_exists($key, $data)) {
        return $default;
    }
    $value = $data[$key];
    return is_string($value) ? trim($value) : $value;
}

/** Converts empty strings to null (for optional DB columns). */
function nullable($value)
{
    return ($value === '' || $value === null) ? null : $value;
}

/* -------------------------------------------------------------------------
 * CSRF protection
 * ---------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Validates the token sent in the X-CSRF-Token header or csrf_token field. */
function verify_csrf(): bool
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (request_data()['csrf_token'] ?? '');
    return is_string($sent) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/* -------------------------------------------------------------------------
 * Pagination & sorting
 * ---------------------------------------------------------------------- */

/** @return array{0:int,1:int,2:int} [page, perPage, offset] */
function pagination_params(): array
{
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = (int) ($_GET['per_page'] ?? DEFAULT_PER_PAGE);
    $perPage = min(MAX_PER_PAGE, max(1, $perPage));
    return [$page, $perPage, ($page - 1) * $perPage];
}

/**
 * Returns a safe "ORDER BY" fragment using a whitelist of sortable columns.
 *
 * @param array  $allowed map of request sort key => SQL expression
 */
function sort_clause(array $allowed, string $defaultKey, string $defaultDir = 'desc'): string
{
    $key = $_GET['sort'] ?? $defaultKey;
    $dir = strtolower($_GET['dir'] ?? $defaultDir) === 'asc' ? 'ASC' : 'DESC';
    if (!isset($allowed[$key])) {
        $key = $defaultKey;
    }
    return $allowed[$key] . ' ' . $dir;
}

/** Escapes LIKE wildcards in a search term. */
function like(string $term): string
{
    return '%' . addcslashes($term, '%_\\') . '%';
}

/* -------------------------------------------------------------------------
 * Validation
 * ---------------------------------------------------------------------- */

/**
 * Minimal rule-based validator.
 *
 * Rules (pipe separated): required, email, phone, date, datetime, time,
 * numeric, integer, min:N, max:N (numeric range), maxlen:N, in:a,b,c
 *
 * @return array field => [messages]
 */
function validate(array $data, array $rules, array $labels = []): array
{
    $errors = [];
    foreach ($rules as $field => $ruleString) {
        $label = $labels[$field] ?? ucwords(str_replace('_', ' ', $field));
        $value = $data[$field] ?? null;
        $value = is_string($value) ? trim($value) : $value;
        $empty = ($value === null || $value === '' || $value === []);

        foreach (explode('|', $ruleString) as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);

            if ($name === 'required') {
                if ($empty) {
                    $errors[$field][] = "$label is required.";
                    break;
                }
                continue;
            }
            if ($empty) {
                continue; // optional and not provided
            }

            $fail = null;
            switch ($name) {
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $fail = "$label must be a valid email address.";
                    }
                    break;
                case 'phone':
                    if (!preg_match('/^\d{11}$/', (string) $value)) {
                        $fail = "$label must be exactly 11 digits.";
                    }
                    break;
                case 'date':
                    $d = DateTime::createFromFormat('Y-m-d', (string) $value);
                    if (!$d || $d->format('Y-m-d') !== $value) {
                        $fail = "$label must be a valid date.";
                    }
                    break;
                case 'datetime':
                    if (!parse_datetime((string) $value)) {
                        $fail = "$label must be a valid date and time.";
                    }
                    break;
                case 'time':
                    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $value)) {
                        $fail = "$label must be a valid time.";
                    }
                    break;
                case 'numeric':
                    if (!is_numeric($value)) {
                        $fail = "$label must be a number.";
                    }
                    break;
                case 'integer':
                    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                        $fail = "$label must be a whole number.";
                    }
                    break;
                case 'min':
                    if (is_numeric($value) && $value < (float) $arg) {
                        $fail = "$label must be at least $arg.";
                    }
                    break;
                case 'max':
                    if (is_numeric($value) && $value > (float) $arg) {
                        $fail = "$label must not exceed $arg.";
                    }
                    break;
                case 'maxlen':
                    if (mb_strlen((string) $value) > (int) $arg) {
                        $fail = "$label must not exceed $arg characters.";
                    }
                    break;
                case 'in':
                    if (!in_array((string) $value, explode(',', (string) $arg), true)) {
                        $fail = "$label has an invalid value.";
                    }
                    break;
            }
            if ($fail) {
                $errors[$field][] = $fail;
            }
        }
    }
    return $errors;
}

/** Parses "Y-m-d H:i", "Y-m-d\TH:i" or "Y-m-d H:i:s" into "Y-m-d H:i:s", or null. */
function parse_datetime(string $value): ?string
{
    $value = str_replace('T', ' ', trim($value));
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
        $d = DateTime::createFromFormat($fmt, $value);
        if ($d && $d->format($fmt) === $value) {
            return $d->format('Y-m-d H:i:s');
        }
    }
    return null;
}

/* -------------------------------------------------------------------------
 * Activity log & notifications
 * ---------------------------------------------------------------------- */

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

/**
 * Records an action in the activity log. Never throws: logging failures must
 * not break the user's operation.
 */
function log_activity(string $action, string $description = '', ?int $userId = null): void
{
    try {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $stmt = db()->prepare(
            'INSERT INTO activity_log (user_id, action, description, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $action,
            $description,
            client_ip(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (Throwable $e) {
        error_log('[SafeMatch] Failed to write activity log: ' . $e->getMessage());
    }
}

function notify_user(int $userId, string $title, string $message, string $type = 'info', ?string $relatedType = null, ?int $relatedId = null): void
{
    try {
        db()->prepare(
            'INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $title, $message, $type, $relatedType, $relatedId]);
    } catch (Throwable $e) {
        error_log('[SafeMatch] Failed to create notification: ' . $e->getMessage());
    }
}

/** Sends a notification to every active administrator. */
function notify_admins(string $title, string $message, string $type = 'info', ?string $relatedType = null, ?int $relatedId = null): void
{
    try {
        $ids = db()->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            notify_user((int) $id, $title, $message, $type, $relatedType, $relatedId);
        }
    } catch (Throwable $e) {
        error_log('[SafeMatch] Failed to notify admins: ' . $e->getMessage());
    }
}

/* -------------------------------------------------------------------------
 * Record identifiers
 * ---------------------------------------------------------------------- */

/**
 * Generates the next human-readable identifier, e.g. M2026001 / N2026001.
 * The sequence restarts every year. Callers should retry on a duplicate-key
 * error (two simultaneous inserts) - see insert_with_code().
 *
 * @param string $table  'mothers' or 'newborns'
 * @param string $column 'mother_id' or 'newborn_id'
 * @param string $prefix 'M' or 'N'
 */
function generate_code(string $table, string $column, string $prefix): string
{
    $allowed = ['mothers' => 'mother_id', 'newborns' => 'newborn_id'];
    if (($allowed[$table] ?? null) !== $column) {
        throw new InvalidArgumentException('Invalid table for code generation.');
    }
    $base  = $prefix . date('Y');
    $start = strlen($base) + 1;
    $stmt  = db()->prepare(
        "SELECT MAX(CAST(SUBSTRING($column, $start) AS UNSIGNED)) FROM $table WHERE $column LIKE ?"
    );
    $stmt->execute([$base . '%']);
    $next = ((int) $stmt->fetchColumn()) + 1;
    return $base . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

/**
 * Runs $insert($code) with a freshly generated code, retrying if another
 * request grabbed the same code first (unique constraint violation).
 */
function insert_with_code(string $table, string $column, string $prefix, callable $insert): string
{
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $code = generate_code($table, $column, $prefix);
        try {
            $insert($code);
            return $code;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000' || stripos($e->getMessage(), $column) === false) {
                throw $e;
            }
        }
    }
    throw new RuntimeException('Could not generate a unique identifier. Please try again.');
}

/* -------------------------------------------------------------------------
 * File uploads
 * ---------------------------------------------------------------------- */

/**
 * Validates and stores an uploaded profile picture.
 *
 * @return string stored filename
 * @throws InvalidArgumentException with a user-friendly message
 */
function store_profile_picture(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE  => 'The image is larger than the server allows.',
            UPLOAD_ERR_FORM_SIZE => 'The image is too large.',
            UPLOAD_ERR_PARTIAL   => 'The upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE   => 'No file was uploaded.',
        ];
        throw new InvalidArgumentException($messages[$file['error']] ?? 'The upload failed.');
    }
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        throw new InvalidArgumentException('Profile picture must be 2MB or smaller.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Invalid upload.');
    }

    // Check the real content type, not the browser-supplied one.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(UPLOAD_ALLOWED_MIME[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new InvalidArgumentException('Only JPG, PNG, GIF or WEBP images are allowed.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . UPLOAD_ALLOWED_MIME[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $filename)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return $filename;
}

/** Deletes a previously stored profile picture (ignores unsafe names). */
function delete_profile_picture(?string $filename): void
{
    if ($filename && preg_match('/^[a-f0-9]{32}\.(jpg|png|gif|webp)$/', $filename)) {
        $path = UPLOAD_DIR . '/' . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function profile_picture_url(?string $filename): ?string
{
    return $filename ? UPLOAD_URL . '/' . rawurlencode($filename) : null;
}
