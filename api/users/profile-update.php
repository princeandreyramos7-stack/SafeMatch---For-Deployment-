<?php
/**
 * POST /api/users/profile-update.php   (any logged-in user, own profile)
 * multipart/form-data
 *   action=profile   name, contact_number, position, [profile_picture file], [remove_picture=1]
 *   action=password  current_password, password, password_confirmation
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('POST');

$data = request_data();
$pdo = db();
$user = current_user();
$id = (int) $user['id'];

$stmt = $pdo->prepare('SELECT password, profile_picture FROM users WHERE id = ?');
$stmt->execute([$id]);
$stored = $stmt->fetch();

if (($data['action'] ?? 'profile') === 'password') {
    $errors = [];
    if (!password_verify((string) ($data['current_password'] ?? ''), $stored['password'])) {
        $errors['current_password'][] = 'Current password is incorrect.';
    }
    $password = (string) ($data['password'] ?? '');
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'][] = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    } elseif ($password !== (string) ($data['password_confirmation'] ?? '')) {
        $errors['password_confirmation'][] = 'Passwords do not match.';
    }
    if ($errors) {
        json_error('Please correct the highlighted fields.', 422, $errors);
    }
    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    session_regenerate_id(true);
    log_activity('PASSWORD_CHANGE', 'Changed own password');
    json_success(null, 'Password changed successfully.');
}

$errors = validate($data, [
    'name'           => 'required|maxlen:255',
    'contact_number' => 'required|phone',
    'position'       => 'required|maxlen:255',
]);
if ($errors) {
    json_error('Please correct the highlighted fields.', 422, $errors);
}

$picture = $stored['profile_picture'];
if (!empty($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
    try {
        $picture = store_profile_picture($_FILES['profile_picture']);
    } catch (InvalidArgumentException $e) {
        json_error($e->getMessage(), 422, ['profile_picture' => [$e->getMessage()]]);
    }
} elseif (!empty($data['remove_picture'])) {
    $picture = null;
}

$pdo->prepare('UPDATE users SET name = ?, contact_number = ?, position = ?, profile_picture = ? WHERE id = ?')
    ->execute([trim($data['name']), $data['contact_number'], trim($data['position']), $picture, $id]);

if ($picture !== $stored['profile_picture']) {
    delete_profile_picture($stored['profile_picture']);
}

log_activity('PROFILE_UPDATE', 'Updated own profile' . ($picture !== $stored['profile_picture'] ? ' (profile picture changed)' : ''));

json_success([
    'name'                => trim($data['name']),
    'profile_picture_url' => profile_picture_url($picture),
], 'Profile updated.');
