<?php
/**
 * GET /api/qr/history.php
 * Params: result (match|mismatch), date_from, date_to, q, page, per_page
 * Lists QR verification attempts.
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('GET');

$pdo = db();
$where = [];
$params = [];

if (in_array($_GET['result'] ?? '', ['match', 'mismatch'], true)) {
    $where[] = 'v.verification_result = ?';
    $params[] = $_GET['result'];
}
if (!empty($_GET['date_from']) && validate(['d' => $_GET['date_from']], ['d' => 'date']) === []) {
    $where[] = 'v.verified_at >= ?';
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to']) && validate(['d' => $_GET['date_to']], ['d' => 'date']) === []) {
    $where[] = 'v.verified_at <= ?';
    $params[] = $_GET['date_to'] . ' 23:59:59';
}
$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $where[] = "(m.mother_id LIKE ? OR n.newborn_id LIKE ? OR CONCAT_WS(' ', m.first_name, m.last_name) LIKE ?)";
    array_push($params, like($q), like($q), like($q));
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = "FROM qr_verifications v
         JOIN mothers m ON m.id = v.mother_id
         JOIN newborns n ON n.id = v.newborn_id
         LEFT JOIN users u ON u.id = v.verified_by
         $whereSql";

[$page, $perPage, $offset] = pagination_params();

$count = $pdo->prepare("SELECT COUNT(*) $from");
$count->execute($params);
$total = (int) $count->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT v.id, v.verification_result, v.verification_notes, v.verified_at,
            m.mother_id, m.first_name AS mother_first_name, m.last_name AS mother_last_name,
            n.id AS newborn_pk, n.newborn_id, n.first_name, n.last_name, n.sex,
            u.name AS verified_by_name
     $from ORDER BY v.verified_at DESC, v.id DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['newborn_name'] = newborn_display_name($r);
    $r['mother_name'] = trim($r['mother_first_name'] . ' ' . $r['mother_last_name']);
}
unset($r);

json_paginated($rows, $total, $page, $perPage);
