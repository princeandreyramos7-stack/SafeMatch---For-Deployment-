<?php
/**
 * GET /api/discharge/list.php
 * Params: q, status (not_ready|ready|discharged|open), light (green|yellow|red),
 *         sort, dir, page, per_page
 * Lists every mother-newborn pair with its readiness percentage and light.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/discharge-functions.php';

api_init('GET');

$pdo = db();
$where = [];
$params = [];

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $where[] = "(t.newborn_id LIKE ? OR t.mother_id LIKE ? OR t.nb_first_name LIKE ? OR t.nb_last_name LIKE ?
                 OR CONCAT_WS(' ', t.m_first_name, t.m_last_name) LIKE ? OR t.room_number LIKE ?)";
    array_push($params, ...array_fill(0, 6, like($q)));
}
$status = $_GET['status'] ?? '';
if (in_array($status, ['not_ready', 'ready', 'discharged'], true)) {
    $where[] = 't.discharge_status = ?';
    $params[] = $status;
} elseif ($status === 'open') {
    $where[] = "t.discharge_status <> 'discharged'";
}

// Light filter is expressed in SQL so pagination stays correct.
$light = $_GET['light'] ?? '';
$pctExpr = '(CASE WHEN t.total_requirements = 0 THEN 100 ELSE t.met_requirements * 100 / t.total_requirements END)';
if ($light === 'green') {
    $where[] = "t.discharge_status <> 'discharged' AND t.mandatory_pending = 0";
} elseif ($light === 'yellow') {
    $where[] = "t.discharge_status <> 'discharged' AND t.mandatory_pending > 0 AND $pctExpr >= " . (int) READINESS_YELLOW_THRESHOLD;
} elseif ($light === 'red') {
    $where[] = "t.discharge_status <> 'discharged' AND t.mandatory_pending > 0 AND $pctExpr < " . (int) READINESS_YELLOW_THRESHOLD;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

[$page, $perPage, $offset] = pagination_params();
$order = sort_clause([
    'newborn'   => 't.newborn_id',
    'mother'    => 't.m_last_name',
    'percent'   => $pctExpr,
    'status'    => 't.discharge_status',
    'birth'     => 't.nb_dob',
    'discharge' => 't.discharge_date',
], 'birth');

$base = '(' . discharge_overview_sql() . ') t';

$count = $pdo->prepare("SELECT COUNT(*) FROM $base $whereSql");
$count->execute($params);
$total = (int) $count->fetchColumn();

$stmt = $pdo->prepare("SELECT t.* FROM $base $whereSql ORDER BY $order, t.newborn_pk DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = array_map('decorate_overview_row', $stmt->fetchAll());

json_paginated($rows, $total, $page, $perPage);
