<?php
/**
 * POST /api/newborns/create.php
 * Registers a newborn linked to a mother. Automatically:
 *  - generates newborn_id (e.g. N2026001)
 *  - generates the mother/newborn QR pair
 *  - opens a discharge record with all active requirements
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validation-rules.php';
require_once __DIR__ . '/../../includes/qr-functions.php';
require_once __DIR__ . '/../../includes/discharge-functions.php';

api_init('POST');

[$errors, $values] = validate_newborn(request_data());
if ($errors) {
    json_error('Please correct the highlighted fields.', 422, $errors);
}

$pdo = db();
$userId = current_user_id();

$pdo->beginTransaction();
try {
    $code = insert_with_code('newborns', 'newborn_id', 'N', function (string $code) use ($pdo, $values, $userId) {
        $columns = array_keys($values);
        $sql = 'INSERT INTO newborns (newborn_id, ' . implode(', ', $columns) . ', created_by)
                VALUES (?, ' . implode(', ', array_fill(0, count($columns), '?')) . ', ?)';
        $pdo->prepare($sql)->execute(array_merge([$code], array_values($values), [$userId]));
    });
    $id = (int) $pdo->lastInsertId();

    generate_qr_pair($id, $userId);
    ensure_discharge_record($id);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

$name = newborn_display_name($values);
log_activity('NEWBORN_CREATE', "Registered newborn $code ($name) for mother #{$values['mother_id']}; QR codes generated");

json_success(['id' => $id, 'newborn_id' => $code], "Newborn $name registered as $code. QR codes generated.", 201);
