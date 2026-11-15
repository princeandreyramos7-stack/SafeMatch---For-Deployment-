<?php
/**
 * GET /api/discharge/check-readiness.php?newborn_id=   (or ?record_id=)
 * Returns the full readiness breakdown: requirements grouped by category,
 * percentage, traffic light, pending items, QR verification status and
 * whether final discharge is allowed.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/discharge-functions.php';

api_init('GET');

$pdo = db();
$recordId = (int) ($_GET['record_id'] ?? 0);
$newbornId = (int) ($_GET['newborn_id'] ?? 0);

if ($recordId > 0) {
    $stmt = $pdo->prepare('SELECT newborn_id FROM discharge_records WHERE id = ?');
    $stmt->execute([$recordId]);
    $newbornId = (int) $stmt->fetchColumn();
}
if ($newbornId <= 0) {
    json_error('Discharge record not found.', 404);
}

try {
    $record = ensure_discharge_record($newbornId);
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 404);
}
refresh_discharge_status((int) $record['id']);
$readiness = discharge_readiness((int) $record['id']);

$stmt = $pdo->prepare(
    'SELECT n.id, n.newborn_id, n.first_name, n.last_name, n.sex, n.date_of_birth,
            m.id AS mother_pk, m.mother_id, m.first_name AS mother_first_name, m.last_name AS mother_last_name,
            m.room_number, m.admission_date, u.name AS discharged_by_name
     FROM newborns n
     JOIN mothers m ON m.id = n.mother_id
     LEFT JOIN users u ON u.id = ?
     WHERE n.id = ?'
);
$stmt->execute([$readiness['record']['discharged_by'], $newbornId]);
$info = $stmt->fetch();
$info['display_name'] = newborn_display_name($info);
$info['mother_name'] = trim($info['mother_first_name'] . ' ' . $info['mother_last_name']);

// Helpful context: screenings recorded for the baby.
$stmt = $pdo->prepare(
    'SELECT t.name, s.result, s.screening_date FROM newborn_screenings s
     JOIN newborn_screening_types t ON t.id = s.screening_type_id
     WHERE s.newborn_id = ? ORDER BY s.screening_date DESC'
);
$stmt->execute([$newbornId]);
$readiness['screenings'] = $stmt->fetchAll();

$grouped = ['mother' => [], 'newborn' => [], 'both' => []];
foreach ($readiness['requirements'] as $req) {
    $grouped[$req['category']][] = $req;
}
$readiness['grouped'] = $grouped;
$readiness['info'] = $info;
unset($readiness['requirements']);

json_success($readiness);
