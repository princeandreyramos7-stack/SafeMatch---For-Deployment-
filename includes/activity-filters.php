<?php
/**
 * SafeMatch - Shared WHERE-clause builder for the activity log list and export.
 * Non-admin users can only see their own activity.
 *
 * @return array{0:string,1:array} [whereSql, params]
 */

require_once __DIR__ . '/auth.php';

function activity_log_filters(array $query): array
{
    $where = [];
    $params = [];

    if (!is_admin()) {
        $where[] = 'a.user_id = ?';
        $params[] = current_user_id();
    } elseif (!empty($query['user_id'])) {
        $where[] = 'a.user_id = ?';
        $params[] = (int) $query['user_id'];
    }
    if (!empty($query['action'])) {
        // Prefix match so "QR_VERIFY" matches QR_VERIFY_MATCH/MISMATCH/INVALID
        $where[] = 'a.action LIKE ?';
        $params[] = addcslashes((string) $query['action'], '%_\\') . '%';
    }
    $q = trim((string) ($query['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(a.description LIKE ? OR a.action LIKE ? OR a.ip_address LIKE ?)';
        array_push($params, like($q), like($q), like($q));
    }
    if (!empty($query['date_from']) && validate(['d' => $query['date_from']], ['d' => 'date']) === []) {
        $where[] = 'a.created_at >= ?';
        $params[] = $query['date_from'] . ' 00:00:00';
    }
    if (!empty($query['date_to']) && validate(['d' => $query['date_to']], ['d' => 'date']) === []) {
        $where[] = 'a.created_at <= ?';
        $params[] = $query['date_to'] . ' 23:59:59';
    }

    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
}
