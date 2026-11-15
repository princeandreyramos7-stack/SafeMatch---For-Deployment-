<?php
/**
 * SafeMatch - Authentication & authorization helpers
 */

require_once __DIR__ . '/functions.php';

/**
 * Returns the logged-in user (fresh from the database, cached per request),
 * or null. Deactivated users are logged out immediately.
 */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, role, contact_number, position, profile_picture, is_active, created_at
         FROM users WHERE id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row || !$row['is_active']) {
        logout_user();
        return null;
    }
    $user = $row;
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === ROLE_ADMIN;
}

function current_user_id(): ?int
{
    $user = current_user();
    return $user ? (int) $user['id'] : null;
}

/* ---------- Page guards (redirect) ---------- */

function require_login(): void
{
    if (!is_logged_in()) {
        $reason = !empty($_SESSION['timed_out']) ? '?timeout=1' : '';
        redirect('pages/login.php' . $reason);
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        log_activity('ACCESS_DENIED', 'Attempted to open admin page ' . ($_SERVER['REQUEST_URI'] ?? ''));
        redirect('pages/dashboard.php?denied=1');
    }
}

/* ---------- API guards (JSON) ---------- */

function api_require_login(): void
{
    if (!is_logged_in()) {
        $message = !empty($_SESSION['timed_out'])
            ? 'Your session has expired. Please log in again.'
            : 'Authentication required.';
        json_error($message, 401);
    }
}

function api_require_admin(): void
{
    if (!is_admin()) {
        log_activity('ACCESS_DENIED', 'Attempted admin API call ' . ($_SERVER['REQUEST_URI'] ?? ''));
        json_error('You do not have permission to perform this action.', 403);
    }
}

/* ---------- Login / logout ---------- */

/**
 * True when too many failed logins came from this IP or for this email
 * within the lockout window (based on the activity log).
 */
function login_is_throttled(string $email): bool
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM activity_log
         WHERE action = 'LOGIN_FAILED'
           AND created_at > (NOW() - INTERVAL ? MINUTE)
           AND (ip_address = ? OR description LIKE ?)"
    );
    $stmt->execute([LOGIN_LOCKOUT_MINUTES, client_ip(), '%' . addcslashes($email, '%_\\') . '%']);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

/**
 * Attempts to authenticate. Returns [bool success, string message].
 */
function attempt_login(string $email, string $password): array
{
    $email = strtolower(trim($email));

    if (login_is_throttled($email)) {
        log_activity('LOGIN_BLOCKED', "Login blocked (too many attempts) for $email");
        return [false, 'Too many failed attempts. Please wait ' . LOGIN_LOCKOUT_MINUTES . ' minutes and try again.'];
    }

    $stmt = db()->prepare('SELECT id, name, password, role, is_active FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Always run password_verify (against a dummy bcrypt hash for unknown
    // emails) so response timing does not reveal which emails exist.
    $hash = $user['password'] ?? '$2y$10$28xMIe/5SPfMdOGHtCCdB.5h/9jkQFh9NBYgrZKEMlxGHtOgZ32C6';
    $valid = password_verify($password, $hash);

    if (!$user || !$valid) {
        log_activity('LOGIN_FAILED', "Failed login for $email", $user ? (int) $user['id'] : null);
        return [false, 'Invalid email or password.'];
    }
    if (!$user['is_active']) {
        log_activity('LOGIN_FAILED', "Login attempt on deactivated account $email", (int) $user['id']);
        return [false, 'Your account has been deactivated. Please contact an administrator.'];
    }

    // Upgrade hash if PHP's default algorithm changed.
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    // Prevent session fixation.
    session_regenerate_id(true);
    $_SESSION = [
        'user_id'       => (int) $user['id'],
        'role'          => $user['role'],
        'last_activity' => time(),
        'csrf_token'    => bin2hex(random_bytes(32)),
    ];

    log_activity('LOGIN', "{$user['name']} logged in", (int) $user['id']);
    return [true, 'Welcome back, ' . $user['name'] . '!'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
