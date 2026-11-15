<?php
/**
 * GET  /api/screenings/types.php          -> list screening types (?all=1 includes inactive)
 * POST /api/screenings/types.php (admin)  -> create (name, description) or
 *                                            update (id, name, description, is_active)
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init(['GET', 'POST']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = 'SELECT id, name, description, is_active FROM newborn_screening_types'
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
    $pdo->prepare('UPDATE newborn_screening_types SET name = ?, description = ?, is_active = ? WHERE id = ?')
        ->execute([trim($data['name']), nullable(trim((string) ($data['description'] ?? ''))), $active, $id]);
    log_activity('SCREENING_TYPE_UPDATE', "Updated screening type #$id ({$data['name']})");
    json_success(['id' => $id], 'Screening type updated.');
}

$pdo->prepare('INSERT INTO newborn_screening_types (name, description) VALUES (?, ?)')
    ->execute([trim($data['name']), nullable(trim((string) ($data['description'] ?? '')))]);
$id = (int) $pdo->lastInsertId();
log_activity('SCREENING_TYPE_CREATE', "Added screening type {$data['name']}");
json_success(['id' => $id], 'Screening type added.', 201);
