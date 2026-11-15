<?php
/**
 * GET  /api/assessments/types.php          -> list assessment types (?all=1 includes inactive)
 * POST /api/assessments/types.php (admin)  -> create (name, description) or
 *                                             update (id, name, description, is_active)
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init(['GET', 'POST']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = 'SELECT id, name, description, is_active FROM maternal_assessment_types'
        . (empty($_GET['all']) ? ' WHERE is_active = 1' : '') . ' ORDER BY name';
    json_success($pdo->query($sql)->fetchAll());
}

api_require_admin();
$data = request_data();
$errors = validate($data, ['name' => 'required|maxlen:255', 'description' => 'maxlen:2000']);
if ($errors) {
    json_error('Please correct the highlighted fields.', 422, $errors);
}

$id = (int) ($data['id'] ?? 0);
$active = !isset($data['is_active']) || filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
if ($id > 0) {
    $pdo->prepare('UPDATE maternal_assessment_types SET name = ?, description = ?, is_active = ? WHERE id = ?')
        ->execute([trim($data['name']), nullable(trim((string) ($data['description'] ?? ''))), $active, $id]);
    log_activity('ASSESSMENT_TYPE_UPDATE', "Updated assessment type #$id ({$data['name']})");
    json_success(['id' => $id], 'Assessment type updated.');
}

$pdo->prepare('INSERT INTO maternal_assessment_types (name, description) VALUES (?, ?)')
    ->execute([trim($data['name']), nullable(trim((string) ($data['description'] ?? '')))]);
$id = (int) $pdo->lastInsertId();
log_activity('ASSESSMENT_TYPE_CREATE', "Added assessment type {$data['name']}");
json_success(['id' => $id], 'Assessment type added.', 201);
