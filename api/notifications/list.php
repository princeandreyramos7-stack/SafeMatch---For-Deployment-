<?php
/**
 * GET /api/notifications/list.php
 * Latest notifications for the current user plus the unread count.
 * Polled in the background, so it does not extend the session.
 */
define('SM_PASSIVE_REQUEST', true);
require_once __DIR__ . '/../../includes/auth.php';

api_init('GET');

$pdo = db();
$userId = current_user_id();

$stmt = $pdo->prepare(
    'SELECT id, title, message, type, is_read, related_type, related_id, created_at
     FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 20'
);
$stmt->execute([$userId]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$userId]);

json_success([
    'items'        => $items,
    'unread'       => (int) $stmt->fetchColumn(),
    'session_left' => session_seconds_left(),
]);
