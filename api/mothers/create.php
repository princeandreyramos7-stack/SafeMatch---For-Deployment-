<?php
/**
 * POST /api/mothers/create.php
 * Registers a mother. mother_id (e.g. M2026001) is generated automatically.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation-rules.php';

api_init('POST');

[$errors, $values] = validate_mother(request_data());
if ($errors) {
    json_error('Please correct the highlighted fields.', 422, $errors);
}

$pdo = db();
$userId = current_user_id();

$code = insert_with_code('mothers', 'mother_id', 'M', function (string $code) use ($pdo, $values, $userId) {
    $columns = array_keys($values);
    $sql = 'INSERT INTO mothers (mother_id, ' . implode(', ', $columns) . ', created_by)
            VALUES (?, ' . implode(', ', array_fill(0, count($columns), '?')) . ', ?)';
    $pdo->prepare($sql)->execute(array_merge([$code], array_values($values), [$userId]));
});

$id = (int) $pdo->lastInsertId();
$name = trim($values['first_name'] . ' ' . $values['last_name']);
log_activity('MOTHER_CREATE', "Registered mother $code ($name)");

json_success(['id' => $id, 'mother_id' => $code], "Mother $name registered as $code.", 201);
