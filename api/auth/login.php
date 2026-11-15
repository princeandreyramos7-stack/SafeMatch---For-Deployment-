<?php
/**
 * POST /api/auth/login.php
 * Body: email, password, csrf_token
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('POST', false);

$data = request_data();
$errors = validate($data, [
    'email'    => 'required|email',
    'password' => 'required',
]);
if ($errors) {
    json_error('Please correct the highlighted fields.', 422, $errors);
}

[$ok, $message] = attempt_login((string) $data['email'], (string) $data['password']);
if (!$ok) {
    json_error($message, 401);
}

json_success([
    'redirect'   => url('pages/dashboard.php'),
    'csrf_token' => csrf_token(),
], $message);
