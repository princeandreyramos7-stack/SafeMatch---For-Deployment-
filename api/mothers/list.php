<?php
/**
 * GET /api/mothers/list.php
 * Params: q, date_from, date_to (admission), status (admitted|discharged),
 *         sort, dir, page, per_page
 *         options=1  -> minimal list for dropdowns (id, mother_id, name)
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('GET');

$pdo = db();

// Subquery: a mother is "discharged" once she has babies and none remain un-discharged.
$dischargedExpr = "(EXISTS (SELECT 1 FROM newborns n WHERE n.mother_id = m.id)
    AND NOT EXISTS (
        SELECT 1 FROM newborns n
        LEFT JOIN discharge_records dr ON dr.newborn_id = n.id AND dr.discharge_status = 'discharged'
        WHERE n.mother_id = m.id AND dr.id IS NULL))";

$where = [];
$params = [];

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $where[] = '(m.mother_id LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ?
                 OR CONCAT_WS(\' \', m.first_name, m.last_name) LIKE ? OR m.contact_number LIKE ? OR m.room_number LIKE ?)';
    array_push($params, ...array_fill(0, 6, like($q)));
}
if (!empty($_GET['date_from']) && validate(['d' => $_GET['date_from']], ['d' => 'date']) === []) {
    $where[] = 'm.admission_date >= ?';
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to']) && validate(['d' => $_GET['date_to']], ['d' => 'date']) === []) {
    $where[] = 'm.admission_date <= ?';
    $params[] = $_GET['date_to'] . ' 23:59:59';
}
$status = $_GET['status'] ?? '';
if ($status === 'discharged') {
    $where[] = $dischargedExpr;
} elseif ($status === 'admitted') {
    $where[] = "NOT $dischargedExpr";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Minimal list for <select> pickers
if (!empty($_GET['options'])) {
    $stmt = $pdo->prepare(
        "SELECT m.id, m.mother_id, m.first_name, m.middle_name, m.last_name, m.room_number
         FROM mothers m $whereSql ORDER BY m.admission_date DESC LIMIT 1000"
    );
    $stmt->execute($params);
    json_success($stmt->fetchAll());
}

[$page, $perPage, $offset] = pagination_params();
$order = sort_clause([
    'mother_id'      => 'm.mother_id',
    'name'           => 'm.last_name',
    'admission_date' => 'm.admission_date',
    'room_number'    => 'm.room_number',
    'created_at'     => 'm.created_at',
], 'admission_date');

$count = $pdo->prepare("SELECT COUNT(*) FROM mothers m $whereSql");
$count->execute($params);
$total = (int) $count->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT m.id, m.mother_id, m.first_name, m.middle_name, m.last_name, m.date_of_birth,
            m.contact_number, m.blood_type, m.admission_date, m.room_number, m.attending_physician,
            (SELECT COUNT(*) FROM newborns n WHERE n.mother_id = m.id) AS newborn_count,
            $dischargedExpr AS is_discharged
     FROM mothers m
     $whereSql
     ORDER BY $order, m.id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);

json_paginated($stmt->fetchAll(), $total, $page, $perPage);
