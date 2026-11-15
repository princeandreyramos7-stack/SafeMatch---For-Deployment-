<?php
/**
 * GET /api/users/list.php   (admin only)
 * Params: q, role, status (active|inactive), sort, dir, page, per_page
 *         options=1 -> minimal list (id, name) for filters
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('GET', true, true);

$pdo = db();

if (!empty($_GET['options'])) {
    json_success($pdo->query('SELECT id, name, role FROM users ORDER BY name')->fetchAll());
}

$where = [];
$params = [];
$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR position LIKE ? OR contact_number LIKE ?)';
    array_push($params, like($q), like($q), like($q), like($q));
}
if (in_array($_GET['role'] ?? '', [ROLE_ADMIN, ROLE_HEALTHCARE], true)) {
    $where[] = 'role = ?';
    $params[] = $_GET['role'];
}
if (($_GET['status'] ?? '') === 'active') {
    $where[] = 'is_active = 1';
} elseif (($_GET['status'] ?? '') === 'inactive') {
    $where[] = 'is_active = 0';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

[$page, $perPage, $offset] = pagination_params();
$order = sort_clause([
    'name'       => 'name',
    'email'      => 'email',
    'role'       => 'role',
    'position'   => 'position',
    'created_at' => 'created_at',
], 'name', 'asc');

$count = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql");
$count->execute($params);
$total = (int) $count->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT id, name, email, role, contact_number, position, profile_picture, is_active, created_at,
            (SELECT MAX(created_at) FROM activity_log a WHERE a.user_id = users.id AND a.action = 'LOGIN') AS last_login
     FROM users $whereSql ORDER BY $order, id LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['profile_picture_url'] = profile_picture_url($r['profile_picture']);
    $r['is_self'] = (int) $r['id'] === current_user_id();
}
unset($r);

json_paginated($rows, $total, $page, $perPage);
