<?php
/**
 * GET  /api/discharge/requirements.php          -> all requirements (incl. inactive)
 * POST /api/discharge/requirements.php (admin)  -> create or update a requirement
 *      Body: [id], name, description, category, is_mandatory, is_active
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/discharge-functions.php';

api_init(['GET', 'POST']);

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query(
        "SELECT id, name, description, category, is_mandatory, is_active, created_at
         FROM discharge_requirements ORDER BY FIELD(category, 'mother', 'newborn', 'both'), id"
    )->fetchAll();
    json_success($rows);
}

api_require_admin();
$data = request_data();
$errors = validate($data, [
    'name'        => 'required|maxlen:255',
    'description' => 'maxlen:2000',
    'category'    => 'required|in:mother,newborn,both',
]);
if ($errors) {
    json_error('Please correct the highlighted fields.', 422, $errors);
}

$id = (int) ($data['id'] ?? 0);
$mandatory = filter_var($data['is_mandatory'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
$active = filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
$values = [trim($data['name']), nullable(trim((string) ($data['description'] ?? ''))), $data['category'], $mandatory, $active];

if ($id > 0) {
    $stmt = $pdo->prepare('UPDATE discharge_requirements SET name = ?, description = ?, category = ?, is_mandatory = ?, is_active = ? WHERE id = ?');
    $stmt->execute(array_merge($values, [$id]));
    $action = 'DISCHARGE_REQUIREMENT_EDIT';
    $message = 'Requirement updated.';
} else {
    $pdo->prepare('INSERT INTO discharge_requirements (name, description, category, is_mandatory, is_active) VALUES (?, ?, ?, ?, ?)')
        ->execute($values);
    $id = (int) $pdo->lastInsertId();
    $action = 'DISCHARGE_REQUIREMENT_CREATE';
    $message = 'Requirement added.';
}

// New/changed requirements affect every open discharge record.
if ($active) {
    $pdo->prepare(
        "INSERT IGNORE INTO discharge_decisions (discharge_record_id, requirement_id, is_met)
         SELECT id, ?, 0 FROM discharge_records WHERE discharge_status <> 'discharged'"
    )->execute([$id]);
}
refresh_all_discharge_statuses();

log_activity($action, "Discharge requirement #$id: {$data['name']} ({$data['category']}, " . ($mandatory ? 'mandatory' : 'optional') . ', ' . ($active ? 'active' : 'inactive') . ')');

json_success(['id' => $id], $message);
