<?php
/**
 * GET /api/activity-logs/list.php
 * Params: q, user_id (admin), action (prefix), date_from, date_to, page, per_page
 * Admins see everyone's activity; other users see only their own.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/activity-filters.php';

api_init('GET');

$pdo = db();
[$whereSql, $params] = activity_log_filters($_GET);
[$page, $perPage, $offset] = pagination_params();
$order = sort_clause(['created_at' => 'a.created_at', 'action' => 'a.action', 'user' => 'u.name'], 'created_at');

$count = $pdo->prepare("SELECT COUNT(*) FROM activity_log a LEFT JOIN users u ON u.id = a.user_id $whereSql");
$count->execute($params);
$total = (int) $count->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT a.id, a.action, a.description, a.ip_address, a.user_agent, a.created_at,
            u.id AS user_id, u.name AS user_name, u.role AS user_role
     FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
     $whereSql ORDER BY $order, a.id DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Distinct action names for the filter dropdown
$actions = $pdo->query('SELECT DISTINCT action FROM activity_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

json_paginated($rows, $total, $page, $perPage, ['actions' => $actions]);
