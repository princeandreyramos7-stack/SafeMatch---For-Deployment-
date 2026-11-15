<?php
/**
 * POST /api/users/create.php   (admin only)
 * Body: name, email, password, password_confirmation, role, contact_number, position
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('POST', true, true);

$data = request_data();
$errors = validate($data, [
    'name'           => 'required|maxlen:255',
    'email'          => 'required|email|maxlen:255',
    'password'       => 'required',
    'role'           => 'required|in:' . ROLE_ADMIN . ',' . ROLE_HEALTHCARE,
    'contact_number' => 'required|phone',
    'position'       => 'required|maxlen:255',
]);

$password = (string) ($data['password'] ?? '');
if (empty($errors['password'])) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'][] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    } elseif ($password !== (string) ($data['password_confirmation'] ?? '')) {
        $errors['password_confirmation'][] = 'Passwords do not match.';
    }
}

$pdo = db();
$email = strtolower(trim((string) ($data['email'] ?? '')));
if (empty($errors['email'])) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ((int) $stmt->fetchColumn() > 0) {
        $errors['email'][] = 'This email is already registered.';
    }
}
if ($errors) {
    json_error('Please correct the highlighted fields.', 422, $errors);
}

$pdo->prepare(
    'INSERT INTO users (name, email, password, role, contact_number, position, is_active)
     VALUES (?, ?, ?, ?, ?, ?, 1)'
)->execute([
    trim($data['name']), $email, password_hash($password, PASSWORD_DEFAULT),
    $data['role'], $data['contact_number'], trim($data['position']),
]);
$id = (int) $pdo->lastInsertId();

log_activity('USER_CREATE', "Created user $email ({$data['role']})");
notify_user($id, 'Welcome to SafeMatch', 'Your account was created. Please change your password after your first login.', 'info');

json_success(['id' => $id], 'User created.', 201);
