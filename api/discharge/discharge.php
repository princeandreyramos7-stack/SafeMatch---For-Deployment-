<?php
/**
 * POST /api/discharge/discharge.php
 * Body: record_id, notes
 * Final discharge. Allowed only when every mandatory requirement is met and
 * the latest QR verification for the pair is a match.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/discharge-functions.php';

api_init('POST');

$data = request_data();
$errors = validate($data, [
    'record_id' => 'required|integer',
    'notes'     => 'maxlen:5000',
]);
if ($errors) {
    json_error('Invalid request.', 422, $errors);
}

$pdo = db();
$recordId = (int) $data['record_id'];

$pdo->beginTransaction();
try {
    // Lock the record so two staff cannot discharge simultaneously.
    $stmt = $pdo->prepare('SELECT * FROM discharge_records WHERE id = ? FOR UPDATE');
    $stmt->execute([$recordId]);
    $record = $stmt->fetch();
    if (!$record) {
        $pdo->rollBack();
        json_error('Discharge record not found.', 404);
    }
    if ($record['discharge_status'] === 'discharged') {
        $pdo->rollBack();
        json_error('This pair has already been discharged.', 409);
    }

    $readiness = discharge_readiness($recordId);
    if (!$readiness['can_discharge']) {
        $pdo->rollBack();
        json_error('Discharge is not allowed yet: ' . implode(' ', $readiness['blockers']), 409, [
            'blockers' => $readiness['blockers'],
        ]);
    }

    $pdo->prepare(
        "UPDATE discharge_records
         SET discharge_status = 'discharged', discharge_date = NOW(), discharge_notes = ?, discharged_by = ?
         WHERE id = ?"
    )->execute([nullable(trim((string) ($data['notes'] ?? ''))), current_user_id(), $recordId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$stmt = $pdo->prepare(
    'SELECT n.newborn_id, n.first_name, n.last_name, n.sex, m.mother_id, m.first_name AS mf, m.last_name AS ml
     FROM newborns n JOIN mothers m ON m.id = n.mother_id WHERE n.id = ?'
);
$stmt->execute([$record['newborn_id']]);
$p = $stmt->fetch();
$label = "newborn {$p['newborn_id']} (" . newborn_display_name($p) . ") with mother {$p['mother_id']} ({$p['mf']} {$p['ml']})";

log_activity('DISCHARGE', "Discharged $label");
notify_admins('Discharge completed', ucfirst($label) . ' discharged by ' . current_user()['name'] . '.', 'success', 'discharge', $recordId);

json_success(['record_id' => $recordId], 'Discharge completed successfully.');
