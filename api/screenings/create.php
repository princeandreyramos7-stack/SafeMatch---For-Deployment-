<?php
/**
 * POST /api/screenings/create.php
 * Body: newborn_id, screening_type_id, screening_date, result, notes
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('POST');

$data = request_data();
$errors = validate($data, [
    'newborn_id'        => 'required|integer',
    'screening_type_id' => 'required|integer',
    'screening_date'    => 'required|datetime',
    'result'            => 'required|in:Normal,Abnormal,Pending',
    'notes'             => 'maxlen:5000',
], ['newborn_id' => 'Newborn', 'screening_type_id' => 'Screening type', 'screening_date' => 'Screening date']);

$pdo = db();
if (empty($errors['newborn_id'])) {
    $stmt = $pdo->prepare('SELECT id, newborn_id FROM newborns WHERE id = ?');
    $stmt->execute([(int) $data['newborn_id']]);
    $newborn = $stmt->fetch();
    if (!$newborn) {
        $errors['newborn_id'][] = 'Selected newborn does not exist.';
    }
}
if (empty($errors['screening_type_id'])) {
    $stmt = $pdo->prepare('SELECT name FROM newborn_screening_types WHERE id = ? AND is_active = 1');
    $stmt->execute([(int) $data['screening_type_id']]);
    $typeName = $stmt->fetchColumn();
    if (!$typeName) {
        $errors['screening_type_id'][] = 'Selected screening type is not available.';
    }
}
if (empty($errors['screening_date']) && new DateTime(parse_datetime($data['screening_date'])) > new DateTime('+5 minutes')) {
    $errors['screening_date'][] = 'Screening date cannot be in the future.';
}
if ($errors) {
    json_error('Please correct the highlighted fields.', 422, $errors);
}

$pdo->prepare(
    'INSERT INTO newborn_screenings (newborn_id, screening_type_id, screening_date, result, notes, performed_by)
     VALUES (?, ?, ?, ?, ?, ?)'
)->execute([
    (int) $data['newborn_id'],
    (int) $data['screening_type_id'],
    parse_datetime($data['screening_date']),
    $data['result'],
    nullable(trim((string) ($data['notes'] ?? ''))),
    current_user_id(),
]);
$id = (int) $pdo->lastInsertId();

log_activity('SCREENING_CREATE', "Recorded $typeName for newborn {$newborn['newborn_id']}: {$data['result']}");
if ($data['result'] === 'Abnormal') {
    notify_admins('Abnormal newborn screening', "$typeName for newborn {$newborn['newborn_id']} is ABNORMAL.", 'warning', 'newborn', (int) $newborn['id']);
}

json_success(['id' => $id], 'Screening recorded.', 201);
