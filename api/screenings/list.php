<?php
/**
 * GET /api/screenings/list.php
 * Params: q, newborn_id, type_id, result, date_from, date_to, sort, dir, page, per_page
 * Response includes summary counts by result for the current filter.
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('GET');

$pdo = db();
$where = [];
$params = [];

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $where[] = "(n.newborn_id LIKE ? OR n.first_name LIKE ? OR n.last_name LIKE ? OR m.mother_id LIKE ?)";
    array_push($params, like($q), like($q), like($q), like($q));
}
if (!empty($_GET['newborn_id'])) {
    $where[] = 's.newborn_id = ?';
    $params[] = (int) $_GET['newborn_id'];
}
if (!empty($_GET['type_id'])) {
    $where[] = 's.screening_type_id = ?';
    $params[] = (int) $_GET['type_id'];
}
if (in_array($_GET['result'] ?? '', ['Normal', 'Abnormal', 'Pending'], true)) {
    $where[] = 's.result = ?';
    $params[] = $_GET['result'];
}
if (!empty($_GET['date_from']) && validate(['d' => $_GET['date_from']], ['d' => 'date']) === []) {
    $where[] = 's.screening_date >= ?';
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to']) && validate(['d' => $_GET['date_to']], ['d' => 'date']) === []) {
    $where[] = 's.screening_date <= ?';
    $params[] = $_GET['date_to'] . ' 23:59:59';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = "FROM newborn_screenings s
         JOIN newborns n ON n.id = s.newborn_id
         JOIN mothers m ON m.id = n.mother_id
         JOIN newborn_screening_types t ON t.id = s.screening_type_id
         LEFT JOIN users u ON u.id = s.performed_by
         $whereSql";

[$page, $perPage, $offset] = pagination_params();
$order = sort_clause([
    'screening_date' => 's.screening_date',
    'type'           => 't.name',
    'result'         => 's.result',
    'newborn'        => 'n.newborn_id',
], 'screening_date');

$stmt = $pdo->prepare("SELECT s.result, COUNT(*) AS total $from GROUP BY s.result");
$stmt->execute($params);
$summary = ['Normal' => 0, 'Abnormal' => 0, 'Pending' => 0];
foreach ($stmt->fetchAll() as $row) {
    $summary[$row['result']] = (int) $row['total'];
}
$total = array_sum($summary);

$stmt = $pdo->prepare(
    "SELECT s.id, s.screening_date, s.result, s.notes, s.created_at,
            t.id AS type_id, t.name AS type_name,
            n.id AS newborn_pk, n.newborn_id, n.first_name, n.last_name, n.sex,
            m.mother_id, u.name AS performed_by_name
     $from ORDER BY $order, s.id DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['newborn_name'] = newborn_display_name($r);
}
unset($r);

json_paginated($rows, $total, $page, $perPage, ['summary' => $summary]);
