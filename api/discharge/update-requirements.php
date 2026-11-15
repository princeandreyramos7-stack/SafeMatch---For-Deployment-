<?php
/**
 * POST /api/discharge/update-requirements.php
 * Body: record_id, requirement_id, is_met (bool), notes (optional)
 * Marks one discharge requirement as met / not met and recalculates status.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/discharge-functions.php';

api_init('POST');

$data = request_data();
$errors = validate($data, [
    'record_id'      => 'required|integer',
    'requirement_id' => 'required|integer',
    'notes'          => 'maxlen:2000',
]);
if ($errors) {
    json_error('Invalid request.', 422, $errors);
}

$pdo = db();
$recordId = (int) $data['record_id'];
$requirementId = (int) $data['requirement_id'];
$isMet = filter_var($data['is_met'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

$stmt = $pdo->prepare(
    'SELECT dr.*, n.newborn_id AS newborn_code FROM discharge_records dr
     JOIN newborns n ON n.id = dr.newborn_id WHERE dr.id = ?'
);
$stmt->execute([$recordId]);
$record = $stmt->fetch();
if (!$record) {
    json_error('Discharge record not found.', 404);
}
if ($record['discharge_status'] === 'discharged') {
    json_error('This pair has already been discharged; requirements are locked.', 409);
}

$stmt = $pdo->prepare('SELECT name FROM discharge_requirements WHERE id = ? AND is_active = 1');
$stmt->execute([$requirementId]);
$reqName = $stmt->fetchColumn();
if (!$reqName) {
    json_error('Requirement not found or inactive.', 404);
}

$notes = nullable(trim((string) ($data['notes'] ?? '')));
$pdo->prepare(
    'INSERT INTO discharge_decisions (discharge_record_id, requirement_id, is_met, checked_at, checked_by, notes)
     VALUES (?, ?, ?, NOW(), ?, ?)
     ON DUPLICATE KEY UPDATE is_met = VALUES(is_met), checked_at = VALUES(checked_at),
                             checked_by = VALUES(checked_by), notes = VALUES(notes)'
)->execute([$recordId, $requirementId, $isMet, current_user_id(), $notes]);

$status = refresh_discharge_status($recordId);
$readiness = discharge_readiness($recordId);

log_activity(
    'DISCHARGE_REQUIREMENT_UPDATE',
    sprintf('Newborn %s: "%s" marked %s', $record['newborn_code'], $reqName, $isMet ? 'MET' : 'NOT MET')
);

json_success([
    'status'            => $status,
    'percent'           => $readiness['percent'],
    'light'             => $readiness['light'],
    'met'               => $readiness['met'],
    'total'             => $readiness['total'],
    'mandatory_pending' => $readiness['mandatory_pending'],
    'pending'           => $readiness['pending'],
    'can_discharge'     => $readiness['can_discharge'],
    'blockers'          => $readiness['blockers'],
], $isMet ? 'Requirement marked as met.' : 'Requirement marked as not met.');
