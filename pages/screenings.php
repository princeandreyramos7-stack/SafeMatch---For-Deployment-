<?php
/**
 * SafeMatch - Newborn screenings.
 */
$pageTitle = 'Newborn Screenings';
$activePage = 'screenings';
$pageScripts = ['records.js', 'screenings.js'];
require __DIR__ . '/../templates/header.php';

$records = [
    'noun'               => 'Screening',
    'intro'              => 'Track hearing, blood, pulse oximetry and physical screenings for every newborn.',
    'subjectLabel'       => 'Newborn',
    'subjectPlaceholder' => 'Search baby name, newborn or mother ID…',
    'subjectField'       => 'newborn_id',
    'subjectParam'       => 'newborn_id',
    'subjectSort'        => 'newborn',
    'typeField'          => 'screening_type_id',
    'dateField'          => 'screening_date',
    'dateSort'           => 'screening_date',
    'dateLabel'          => 'Screening date',
    'results'            => ['Normal', 'Abnormal', 'Pending'],
];
require __DIR__ . '/../templates/records-page.php';
require __DIR__ . '/../templates/footer.php';
