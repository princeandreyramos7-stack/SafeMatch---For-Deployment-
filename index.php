<?php
/**
 * SafeMatch entry point: send users to the dashboard or the login page.
 */
require_once __DIR__ . '/includes/auth.php';

redirect(is_logged_in() ? 'pages/dashboard.php' : 'pages/login.php');
