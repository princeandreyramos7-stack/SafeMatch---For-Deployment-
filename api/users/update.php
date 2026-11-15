<?php
/**
 * POST /api/users/update.php   (admin only)
 * Body: id, action
 *   action=update          name, email, role, contact_number, position
 *   action=toggle_active   is_active (bool)
 *   action=reset_password  password, password_confirmation
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('POST', true, true);

$data = request_data();
$pdo = db();
$id = (int) ($data['id'] ?? 0);
$action = $data['action'] ?? 'update';
$isSelf = $id === current_user_id();

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) {
    json_error('User not found.', 404);
}

/** Prevents removing the last active administrator. */
$isLastAdmin = function () use ($pdo, $user): bool {
    if ($user['role'] !== ROLE_ADMIN || !$user['is_active']) {
        return false;
    }
    return (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn() <= 1;
};

switch ($action) {
    case 'toggle_active':
        $active = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        if (!$active && $isSelf) {
            json_error('You cannot deactivate your own account.', 409);
        }
        if (!$active && $isLastAdmin()) {
            json_error('At least one active administrator is required.', 409);
        }
        $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$active, $id]);
        log_activity($active ? 'USER_ACTIVATE' : 'USER_DEACTIVATE', ($active ? 'Activated' : 'Deactivated') . " user {$user['email']}");
        json_success(['id' => $id, 'is_active' => (bool) $active], $active ? 'User activated.' : 'User deactivated.');

    case 'reset_password':
        $password = (string) ($data['password'] ?? '');
        $errors = [];
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors['password'][] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } elseif ($password !== (string) ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'][] = 'Passwords do not match.';
        }
        if ($errors) {
            json_error('Please correct the highlighted fields.', 422, $errors);
        }
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        log_activity('USER_PASSWORD_RESET', "Reset password for {$user['email']}");
        notify_user($id, 'Password reset', 'An administrator reset your password.', 'warning');
        json_success(['id' => $id], 'Password reset successfully.');

    case 'update':
        $errors = validate($data, [
            'name'           => 'required|maxlen:255',
            'email'          => 'required|email|maxlen:255',
            'role'           => 'required|in:' . ROLE_ADMIN . ',' . ROLE_HEALTHCARE,
            'contact_number' => 'required|phone',
            'position'       => 'required|maxlen:255',
        ]);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if (empty($errors['email'])) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
            $stmt->execute([$email, $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errors['email'][] = 'This email is already registered.';
            }
        }
        if (empty($errors['role']) && $data['role'] !== ROLE_ADMIN && $isLastAdmin()) {
            $errors['role'][] = 'At least one active administrator is required.';
        }
        if ($errors) {
            json_error('Please correct the highlighted fields.', 422, $errors);
        }
        $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ?, contact_number = ?, position = ? WHERE id = ?')
            ->execute([trim($data['name']), $email, $data['role'], $data['contact_number'], trim($data['position']), $id]);
        log_activity('USER_UPDATE', "Updated user {$user['email']}" . ($user['role'] !== $data['role'] ? " (role: {$user['role']} -> {$data['role']})" : ''));
        json_success(['id' => $id], 'User updated.');

    default:
        json_error('Unknown action.', 400);
}
