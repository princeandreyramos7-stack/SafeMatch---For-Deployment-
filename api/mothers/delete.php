<?php
/**
 * POST /api/mothers/delete.php   (admin only)
 * Body: id
 * Deletes a mother and her babies' records. Records with a completed
 * discharge are kept for the audit trail and cannot be deleted.
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('POST', true, true);

$id = (int) input('id', 0);
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM mothers WHERE id = ?');
$stmt->execute([$id]);
$mother = $stmt->fetch();
if (!$mother) {
    json_error('Mother not found.', 404);
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM discharge_records WHERE mother_id = ? AND discharge_status = 'discharged'");
$stmt->execute([$id]);
if ((int) $stmt->fetchColumn() > 0) {
    json_error('This mother has a completed discharge on record and cannot be deleted.', 409);
}

$pdo->beginTransaction();
try {
    // Tables without ON DELETE CASCADE are cleared explicitly.
    $pdo->prepare('DELETE FROM qr_verifications WHERE mother_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM discharge_records WHERE mother_id = ?')->execute([$id]); // decisions cascade
    $pdo->prepare('DELETE FROM mothers WHERE id = ?')->execute([$id]);                  // newborns, QR, screenings, assessments cascade
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

$name = full_name($mother);
log_activity('MOTHER_DELETE', "Deleted mother {$mother['mother_id']} ($name) and related records");

json_success(null, "Mother {$mother['mother_id']} deleted.");
