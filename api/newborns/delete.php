<?php
/**
 * POST /api/newborns/delete.php   (admin only)
 * Body: id
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('POST', true, true);

$id = (int) input('id', 0);
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM newborns WHERE id = ?');
$stmt->execute([$id]);
$newborn = $stmt->fetch();
if (!$newborn) {
    json_error('Newborn not found.', 404);
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM discharge_records WHERE newborn_id = ? AND discharge_status = 'discharged'");
$stmt->execute([$id]);
if ((int) $stmt->fetchColumn() > 0) {
    json_error('This newborn has a completed discharge on record and cannot be deleted.', 409);
}

$pdo->beginTransaction();
try {
    $pdo->prepare('DELETE FROM qr_verifications WHERE newborn_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM discharge_records WHERE newborn_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM newborns WHERE id = ?')->execute([$id]); // QR codes & screenings cascade
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

log_activity('NEWBORN_DELETE', "Deleted newborn {$newborn['newborn_id']} (" . newborn_display_name($newborn) . ')');

json_success(null, "Newborn {$newborn['newborn_id']} deleted.");
