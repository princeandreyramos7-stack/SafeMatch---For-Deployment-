<?php
/**
 * GET /api/mothers/view.php?id=
 * Returns the mother, her newborns (with discharge status) and recent assessments.
 */
require_once __DIR__ . '/../../includes/auth.php';

api_init('GET');

$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare(
    'SELECT m.*, u.name AS created_by_name
     FROM mothers m LEFT JOIN users u ON u.id = m.created_by
     WHERE m.id = ?'
);
$stmt->execute([$id]);
$mother = $stmt->fetch();
if (!$mother) {
    json_error('Mother not found.', 404);
}

$stmt = $pdo->prepare(
    "SELECT n.id, n.newborn_id, n.first_name, n.middle_name, n.last_name, n.sex, n.date_of_birth,
            n.weight, n.length, n.delivery_type,
            COALESCE((SELECT dr.discharge_status FROM discharge_records dr
                      WHERE dr.newborn_id = n.id ORDER BY dr.id DESC LIMIT 1), 'not_ready') AS discharge_status
     FROM newborns n WHERE n.mother_id = ? ORDER BY n.date_of_birth"
);
$stmt->execute([$id]);
$newborns = $stmt->fetchAll();
foreach ($newborns as &$nb) {
    $nb['display_name'] = newborn_display_name($nb);
}
unset($nb);

$stmt = $pdo->prepare(
    'SELECT a.id, a.assessment_date, a.result, a.notes, t.name AS type_name, u.name AS assessed_by_name
     FROM maternal_assessments a
     JOIN maternal_assessment_types t ON t.id = a.assessment_type_id
     LEFT JOIN users u ON u.id = a.assessed_by
     WHERE a.mother_id = ? ORDER BY a.assessment_date DESC LIMIT 20'
);
$stmt->execute([$id]);

json_success([
    'mother'      => $mother,
    'newborns'    => $newborns,
    'assessments' => $stmt->fetchAll(),
]);
