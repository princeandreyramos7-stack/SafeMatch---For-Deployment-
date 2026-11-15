<?php
/**
 * POST /api/auth/keepalive.php
 * Extends the session when the user chooses "Stay signed in".
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('POST');

json_success(['session_left' => session_seconds_left()], 'Session extended.');
