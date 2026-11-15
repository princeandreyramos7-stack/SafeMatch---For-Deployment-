<?php
/**
 * POST /api/mothers/update.php
 * Body: id + mother fields. QR codes of her babies are regenerated so the
 * printed bands always reflect the current record.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation-rules.php';
require_once __DIR__ . '/../../includes/qr-functions.php';

api_init('POST');

$id = (int) input('id', 0);
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM mothers WHERE id = ?');
$stmt->execute([$id]);
$existing = $stmt->fetch();
if (!$existing) {
    json_error('Mother not found.', 404);
}

[$errors, $values] = validate_mother(request_data());
if ($errors) {
    json_error('Please correct the highlighted fields.', 422, $errors);
}

$pdo->beginTransaction();
try {
    $set = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($values)));
    $pdo->prepare("UPDATE mothers SET $set WHERE id = ?")
        ->execute(array_merge(array_values($values), [$id]));

    // Only fields encoded in the QR matter; regenerate when they changed.
    $before = mother_qr_payload($existing);
    $after = mother_qr_payload(array_merge($existing, $values));
    $qrChanged = $before !== $after;
    if ($qrChanged) {
        regenerate_mother_qrs($id, current_user_id());
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

log_activity('MOTHER_UPDATE', "Updated mother {$existing['mother_id']}" . ($qrChanged ? ' (QR codes regenerated)' : ''));

$message = 'Mother record updated.';
if ($qrChanged) {
    $message .= ' QR codes were regenerated - please reprint the bands.';
}
json_success(['id' => $id, 'qr_regenerated' => $qrChanged], $message);
