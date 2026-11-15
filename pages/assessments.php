<?php
/**
 * SafeMatch - Maternal assessments.
 */
$pageTitle = 'Maternal Assessments';
$activePage = 'assessments';
$pageScripts = ['records.js', 'assessments.js'];
require __DIR__ . '/../templates/header.php';

$records = [
    'noun'               => 'Assessment',
    'intro'              => 'Record vital signs, postpartum, breastfeeding and mental health assessments for mothers.',
    'subjectLabel'       => 'Mother',
    'subjectPlaceholder' => 'Search mother name or ID…',
    'subjectField'       => 'mother_id',
    'subjectParam'       => 'mother_id',
    'subjectSort'        => 'mother',
    'typeField'          => 'assessment_type_id',
    'dateField'          => 'assessment_date',
    'dateSort'           => 'assessment_date',
    'dateLabel'          => 'Assessment date',
    'results'            => ['Normal', 'Abnormal', 'Needs Attention'],
];
require __DIR__ . '/../templates/records-page.php';
require __DIR__ . '/../templates/footer.php';
