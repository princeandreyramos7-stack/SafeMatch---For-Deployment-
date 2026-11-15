<?php
/**
 * GET /api/newborns/list.php
 * Params: q, sex, delivery_type, date_from, date_to (birth), mother_id,
 *         status (not_ready|ready|discharged), sort, dir, page, per_page
 *         options=1 [&exclude_discharged=1] -> minimal list for dropdowns
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('GET');

$pdo = db();
$statusExpr = "COALESCE((SELECT dr.discharge_status FROM discharge_records dr
                WHERE dr.newborn_id = n.id ORDER BY dr.id DESC LIMIT 1), 'not_ready')";

$where = [];
$params = [];

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $where[] = "(n.newborn_id LIKE ? OR n.first_name LIKE ? OR n.last_name LIKE ?
                 OR m.mother_id LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ?
                 OR CONCAT_WS(' ', m.first_name, m.last_name) LIKE ?)";
    array_push($params, ...array_fill(0, 7, like($q)));
}
if (in_array($_GET['sex'] ?? '', ['Male', 'Female'], true)) {
    $where[] = 'n.sex = ?';
    $params[] = $_GET['sex'];
}
if (in_array($_GET['delivery_type'] ?? '', ['Normal', 'Cesarean'], true)) {
    $where[] = 'n.delivery_type = ?';
    $params[] = $_GET['delivery_type'];
}
if (!empty($_GET['date_from']) && validate(['d' => $_GET['date_from']], ['d' => 'date']) === []) {
    $where[] = 'n.date_of_birth >= ?';
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to']) && validate(['d' => $_GET['date_to']], ['d' => 'date']) === []) {
    $where[] = 'n.date_of_birth <= ?';
    $params[] = $_GET['date_to'] . ' 23:59:59';
}
if (!empty($_GET['mother_id'])) {
    $where[] = 'n.mother_id = ?';
    $params[] = (int) $_GET['mother_id'];
}
if (in_array($_GET['status'] ?? '', ['not_ready', 'ready', 'discharged'], true)) {
    $where[] = "$statusExpr = ?";
    $params[] = $_GET['status'];
}
if (!empty($_GET['exclude_discharged'])) {
    $where[] = "$statusExpr <> 'discharged'";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = "FROM newborns n JOIN mothers m ON m.id = n.mother_id $whereSql";

if (!empty($_GET['options'])) {
    $stmt = $pdo->prepare(
        "SELECT n.id, n.newborn_id, n.first_name, n.last_name, n.sex,
                m.id AS mother_pk, m.mother_id, m.first_name AS mother_first_name, m.last_name AS mother_last_name
         $from ORDER BY n.date_of_birth DESC LIMIT 1000"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['display_name'] = newborn_display_name($r);
    }
    json_success($rows);
}

[$page, $perPage, $offset] = pagination_params();
$order = sort_clause([
    'newborn_id'    => 'n.newborn_id',
    'name'          => 'n.last_name',
    'date_of_birth' => 'n.date_of_birth',
    'sex'           => 'n.sex',
    'weight'        => 'n.weight',
    'mother'        => 'm.last_name',
], 'date_of_birth');

$count = $pdo->prepare("SELECT COUNT(*) $from");
$count->execute($params);
$total = (int) $count->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT n.id, n.newborn_id, n.first_name, n.middle_name, n.last_name, n.sex, n.date_of_birth,
            n.time_of_birth, n.weight, n.length, n.delivery_type, n.pediatrician,
            m.id AS mother_pk, m.mother_id, m.first_name AS mother_first_name, m.last_name AS mother_last_name,
            $statusExpr AS discharge_status,
            (SELECT COUNT(*) FROM qr_codes q WHERE q.newborn_id = n.id) AS has_qr
     $from
     ORDER BY $order, n.id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['display_name'] = newborn_display_name($r);
}
unset($r);

json_paginated($rows, $total, $page, $perPage);
