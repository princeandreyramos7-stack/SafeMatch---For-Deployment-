<?php
/**
 * GET /api/assessments/list.php
 * Params: q, mother_id, type_id, result, date_from, date_to, sort, dir, page, per_page
 * Response includes summary counts by result for the current filter.
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('GET');

$pdo = db();
$where = [];
$params = [];

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $where[] = "(m.mother_id LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ? OR CONCAT_WS(' ', m.first_name, m.last_name) LIKE ?)";
    array_push($params, like($q), like($q), like($q), like($q));
}
if (!empty($_GET['mother_id'])) {
    $where[] = 'a.mother_id = ?';
    $params[] = (int) $_GET['mother_id'];
}
if (!empty($_GET['type_id'])) {
    $where[] = 'a.assessment_type_id = ?';
    $params[] = (int) $_GET['type_id'];
}
if (in_array($_GET['result'] ?? '', ['Normal', 'Abnormal', 'Needs Attention'], true)) {
    $where[] = 'a.result = ?';
    $params[] = $_GET['result'];
}
if (!empty($_GET['date_from']) && validate(['d' => $_GET['date_from']], ['d' => 'date']) === []) {
    $where[] = 'a.assessment_date >= ?';
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to']) && validate(['d' => $_GET['date_to']], ['d' => 'date']) === []) {
    $where[] = 'a.assessment_date <= ?';
    $params[] = $_GET['date_to'] . ' 23:59:59';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = "FROM maternal_assessments a
         JOIN mothers m ON m.id = a.mother_id
         JOIN maternal_assessment_types t ON t.id = a.assessment_type_id
         LEFT JOIN users u ON u.id = a.assessed_by
         $whereSql";

[$page, $perPage, $offset] = pagination_params();
$order = sort_clause([
    'assessment_date' => 'a.assessment_date',
    'type'            => 't.name',
    'result'          => 'a.result',
    'mother'          => 'm.mother_id',
], 'assessment_date');

$stmt = $pdo->prepare("SELECT a.result, COUNT(*) AS total $from GROUP BY a.result");
$stmt->execute($params);
$summary = ['Normal' => 0, 'Abnormal' => 0, 'Needs Attention' => 0];
foreach ($stmt->fetchAll() as $row) {
    $summary[$row['result']] = (int) $row['total'];
}
$total = array_sum($summary);

$stmt = $pdo->prepare(
    "SELECT a.id, a.assessment_date, a.result, a.notes, a.created_at,
            t.id AS type_id, t.name AS type_name,
            m.id AS mother_pk, m.mother_id, m.first_name, m.last_name, m.room_number,
            u.name AS assessed_by_name
     $from ORDER BY $order, a.id DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['mother_name'] = trim($r['first_name'] . ' ' . $r['last_name']);
}
unset($r);

json_paginated($rows, $total, $page, $perPage, ['summary' => $summary]);
