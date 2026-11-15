<?php
/**
 * POST /api/newborns/update.php
 * Body: id + newborn fields. Regenerates the QR pair when encoded data or
 * the linked mother changes.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation-rules.php';
require_once __DIR__ . '/../../includes/qr-functions.php';
require_once __DIR__ . '/../../includes/discharge-functions.php';

api_init('POST');

$id = (int) input('id', 0);
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM newborns WHERE id = ?');
$stmt->execute([$id]);
$existing = $stmt->fetch();
if (!$existing) {
    json_error('Newborn not found.', 404);
}

[$errors, $values] = validate_newborn(request_data());
if ($errors) {
    json_error('Please correct the highlighted fields.', 422, $errors);
}

$record = ensure_discharge_record($id);
$motherChanged = (int) $values['mother_id'] !== (int) $existing['mother_id'];
if ($motherChanged && $record['discharge_status'] === 'discharged') {
    json_error('This newborn has already been discharged; the linked mother cannot be changed.', 409, [
        'mother_id' => ['Cannot change the mother after discharge.'],
    ]);
}

$pdo->beginTransaction();
try {
    $set = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($values)));
    $pdo->prepare("UPDATE newborns SET $set WHERE id = ?")
        ->execute(array_merge(array_values($values), [$id]));

    if ($motherChanged) {
        // Keep the open discharge record pointing at the right mother.
        $pdo->prepare('UPDATE discharge_records SET mother_id = ? WHERE id = ?')
            ->execute([$values['mother_id'], $record['id']]);
    }

    $stmt = $pdo->prepare('SELECT newborn_qr_data FROM qr_codes WHERE newborn_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$id]);
    $oldQr = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT * FROM mothers WHERE id = ?');
    $stmt->execute([$values['mother_id']]);
    $mother = $stmt->fetch();
    $newQrData = qr_json(newborn_qr_payload(array_merge($existing, $values), $mother));

    $qrChanged = $motherChanged || $oldQr !== $newQrData;
    if ($qrChanged) {
        generate_qr_pair($id, current_user_id());
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

$details = [];
if ($motherChanged) {
    $details[] = "re-linked to mother {$mother['mother_id']}";
}
if ($qrChanged) {
    $details[] = 'QR codes regenerated';
}
log_activity('NEWBORN_UPDATE', "Updated newborn {$existing['newborn_id']}" . ($details ? ' (' . implode(', ', $details) . ')' : ''));

$message = 'Newborn record updated.';
if ($qrChanged) {
    $message .= ' QR codes were regenerated - please reprint the bands.';
}
json_success(['id' => $id, 'qr_regenerated' => $qrChanged], $message);
