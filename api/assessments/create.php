<?php
/**
 * POST /api/assessments/create.php
 * Body: mother_id, assessment_type_id, assessment_date, result, notes
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('POST');

$data = request_data();
$errors = validate($data, [
    'mother_id'          => 'required|integer',
    'assessment_type_id' => 'required|integer',
    'assessment_date'    => 'required|datetime',
    'result'             => 'required|in:Normal,Abnormal,Needs Attention',
    'notes'              => 'maxlen:5000',
], ['mother_id' => 'Mother', 'assessment_type_id' => 'Assessment type', 'assessment_date' => 'Assessment date']);

$pdo = db();
if (empty($errors['mother_id'])) {
    $stmt = $pdo->prepare('SELECT id, mother_id FROM mothers WHERE id = ?');
    $stmt->execute([(int) $data['mother_id']]);
    $mother = $stmt->fetch();
    if (!$mother) {
        $errors['mother_id'][] = 'Selected mother does not exist.';
    }
}
if (empty($errors['assessment_type_id'])) {
    $stmt = $pdo->prepare('SELECT name FROM maternal_assessment_types WHERE id = ? AND is_active = 1');
    $stmt->execute([(int) $data['assessment_type_id']]);
    $typeName = $stmt->fetchColumn();
    if (!$typeName) {
        $errors['assessment_type_id'][] = 'Selected assessment type is not available.';
    }
}
if (empty($errors['assessment_date']) && new DateTime(parse_datetime($data['assessment_date'])) > new DateTime('+5 minutes')) {
    $errors['assessment_date'][] = 'Assessment date cannot be in the future.';
}
if ($errors) {
    json_error('Please correct the highlighted fields.', 422, $errors);
}

$pdo->prepare(
    'INSERT INTO maternal_assessments (mother_id, assessment_type_id, assessment_date, result, notes, assessed_by)
     VALUES (?, ?, ?, ?, ?, ?)'
)->execute([
    (int) $data['mother_id'],
    (int) $data['assessment_type_id'],
    parse_datetime($data['assessment_date']),
    $data['result'],
    nullable(trim((string) ($data['notes'] ?? ''))),
    current_user_id(),
]);
$id = (int) $pdo->lastInsertId();

log_activity('ASSESSMENT_CREATE', "Recorded $typeName for mother {$mother['mother_id']}: {$data['result']}");
if ($data['result'] !== 'Normal') {
    notify_admins('Maternal assessment needs review', "$typeName for mother {$mother['mother_id']}: {$data['result']}.", 'warning', 'mother', (int) $mother['id']);
}

json_success(['id' => $id], 'Assessment recorded.', 201);
