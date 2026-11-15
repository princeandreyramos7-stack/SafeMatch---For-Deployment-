<?php
/**
 * POST /api/notifications/mark-read.php
 * Body: id (single notification) or all=1
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('POST');

$userId = current_user_id();
if (!empty(input('all'))) {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$userId]);
} else {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
        ->execute([(int) input('id', 0), $userId]);
}

json_success(null, 'Notifications updated.');
