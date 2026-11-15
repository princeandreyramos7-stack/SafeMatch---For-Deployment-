<?php
/**
 * POST /api/auth/logout.php
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('POST', false);

if (is_logged_in()) {
    log_activity('LOGOUT', current_user()['name'] . ' logged out');
}
logout_user();

json_success(['redirect' => url('pages/login.php')], 'You have been logged out.');
