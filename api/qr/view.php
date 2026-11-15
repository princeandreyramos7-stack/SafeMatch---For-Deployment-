<?php
/**
 * GET /api/qr/view.php?newborn_id=
 * Returns the stored QR pair for a newborn (generating it if missing),
 * plus the names needed to label printed bands.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/qr-functions.php';

api_init('GET');

$newbornId = (int) ($_GET['newborn_id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare(
    'SELECT n.id, n.newborn_id, n.first_name, n.last_name, n.sex, n.date_of_birth, n.time_of_birth,
            m.id AS mother_pk, m.mother_id, m.first_name AS mother_first_name, m.last_name AS mother_last_name,
            m.room_number
     FROM newborns n JOIN mothers m ON m.id = n.mother_id WHERE n.id = ?'
);
$stmt->execute([$newbornId]);
$info = $stmt->fetch();
if (!$info) {
    json_error('Newborn not found.', 404);
}

$stmt = $pdo->prepare(
    'SELECT q.*, u.name AS generated_by_name FROM qr_codes q
     LEFT JOIN users u ON u.id = q.generated_by
     WHERE q.newborn_id = ? ORDER BY q.id DESC LIMIT 1'
);
$stmt->execute([$newbornId]);
$qr = $stmt->fetch();

if (!$qr) {
    generate_qr_pair($newbornId, current_user_id());
    log_activity('QR_GENERATE', "Generated missing QR codes for newborn {$info['newborn_id']}");
    $stmt->execute([$newbornId]);
    $qr = $stmt->fetch();
}

$info['display_name'] = newborn_display_name($info);
$info['mother_name'] = trim($info['mother_first_name'] . ' ' . $info['mother_last_name']);

json_success(['info' => $info, 'qr' => $qr]);
