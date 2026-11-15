<?php
/**
 * POST /api/qr/generate.php
 * Body: newborn_id (internal id)
 * (Re)generates the mother + newborn QR pair. Bands printed from a previous
 * generation keep working only if the encoded data did not change.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/qr-functions.php';

api_init('POST');

$newbornId = (int) input('newborn_id', 0);
if ($newbornId <= 0) {
    json_error('Newborn is required.', 422, ['newborn_id' => ['Newborn is required.']]);
}

try {
    $qr = generate_qr_pair($newbornId, current_user_id());
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 404);
}

$stmt = db()->prepare('SELECT newborn_id FROM newborns WHERE id = ?');
$stmt->execute([$newbornId]);
$code = $stmt->fetchColumn();
log_activity('QR_GENERATE', "Generated QR codes for newborn $code and mother");

json_success($qr, 'QR codes generated successfully.');
