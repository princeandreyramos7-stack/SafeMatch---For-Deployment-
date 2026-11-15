<?php
/**
 * GET /api/newborns/view.php?id=
 * Newborn details with mother, screenings, QR codes and discharge summary.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/discharge-functions.php';

api_init('GET');

$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare(
    'SELECT n.*, u.name AS created_by_name FROM newborns n
     LEFT JOIN users u ON u.id = n.created_by WHERE n.id = ?'
);
$stmt->execute([$id]);
$newborn = $stmt->fetch();
if (!$newborn) {
    json_error('Newborn not found.', 404);
}
$newborn['display_name'] = newborn_display_name($newborn);

$stmt = $pdo->prepare(
    'SELECT id, mother_id, first_name, middle_name, last_name, date_of_birth, contact_number,
            room_number, admission_date, blood_type
     FROM mothers WHERE id = ?'
);
$stmt->execute([$newborn['mother_id']]);
$mother = $stmt->fetch();

$stmt = $pdo->prepare(
    'SELECT s.id, s.screening_date, s.result, s.notes, t.name AS type_name, u.name AS performed_by_name
     FROM newborn_screenings s
     JOIN newborn_screening_types t ON t.id = s.screening_type_id
     LEFT JOIN users u ON u.id = s.performed_by
     WHERE s.newborn_id = ? ORDER BY s.screening_date DESC'
);
$stmt->execute([$id]);
$screenings = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT q.id, q.mother_qr_code, q.newborn_qr_code, q.mother_qr_data, q.newborn_qr_data,
            q.generated_at, u.name AS generated_by_name
     FROM qr_codes q LEFT JOIN users u ON u.id = q.generated_by
     WHERE q.newborn_id = ? ORDER BY q.id DESC LIMIT 1'
);
$stmt->execute([$id]);
$qr = $stmt->fetch() ?: null;

$record = ensure_discharge_record($id);
$readiness = discharge_readiness((int) $record['id']);

json_success([
    'newborn'    => $newborn,
    'mother'     => $mother,
    'screenings' => $screenings,
    'qr'         => $qr,
    'discharge'  => [
        'record_id'    => (int) $record['id'],
        'status'       => $readiness['record']['discharge_status'],
        'percent'      => $readiness['percent'],
        'light'        => $readiness['light'],
        'verified'     => $readiness['verified'],
        'verification' => $readiness['verification'],
    ],
]);
