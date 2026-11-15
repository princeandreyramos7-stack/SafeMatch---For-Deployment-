<?php
/**
 * GET /api/dashboard/stats.php
 * Live statistics and chart data for the dashboard.
 * ?passive=1 (auto-refresh) does not extend the user's session.
 */
if (!empty($_GET['passive'])) {
    define('SM_PASSIVE_REQUEST', true);
}
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/discharge-functions.php';

api_init('GET');

$pdo = db();
$scalar = fn (string $sql) => (int) $pdo->query($sql)->fetchColumn();

$stats = [
    'total_mothers'        => $scalar('SELECT COUNT(*) FROM mothers'),
    'total_newborns'       => $scalar('SELECT COUNT(*) FROM newborns'),
    'todays_admissions'    => $scalar('SELECT COUNT(*) FROM mothers WHERE DATE(admission_date) = CURDATE()'),
    'todays_births'        => $scalar('SELECT COUNT(*) FROM newborns WHERE DATE(date_of_birth) = CURDATE()'),
    'pending_discharges'   => $scalar("SELECT COUNT(*) FROM newborns n WHERE NOT EXISTS (
                                SELECT 1 FROM discharge_records dr WHERE dr.newborn_id = n.id AND dr.discharge_status = 'discharged')"),
    'ready_for_discharge'  => $scalar("SELECT COUNT(*) FROM discharge_records WHERE discharge_status = 'ready'"),
    'discharged_total'     => $scalar("SELECT COUNT(*) FROM discharge_records WHERE discharge_status = 'discharged'"),
    'verifications_7d'     => $scalar('SELECT COUNT(*) FROM qr_verifications WHERE verified_at >= CURDATE() - INTERVAL 6 DAY'),
    'mismatches_7d'        => $scalar("SELECT COUNT(*) FROM qr_verifications WHERE verification_result = 'mismatch' AND verified_at >= CURDATE() - INTERVAL 6 DAY"),
    'abnormal_screenings'  => $scalar("SELECT COUNT(*) FROM newborn_screenings WHERE result = 'Abnormal'"),
    'pending_screenings'   => $scalar("SELECT COUNT(*) FROM newborn_screenings WHERE result = 'Pending'"),
    'assessments_attention'=> $scalar("SELECT COUNT(*) FROM maternal_assessments WHERE result <> 'Normal' AND assessment_date >= CURDATE() - INTERVAL 6 DAY"),
];

// Admissions & births trend - last 7 days (zero-filled)
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[$d] = ['date' => $d, 'label' => date('D, M j', strtotime($d)), 'admissions' => 0, 'births' => 0];
}
foreach ($pdo->query('SELECT DATE(admission_date) d, COUNT(*) c FROM mothers
                      WHERE admission_date >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(admission_date)') as $row) {
    if (isset($days[$row['d']])) {
        $days[$row['d']]['admissions'] = (int) $row['c'];
    }
}
foreach ($pdo->query('SELECT DATE(date_of_birth) d, COUNT(*) c FROM newborns
                      WHERE date_of_birth >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(date_of_birth)') as $row) {
    if (isset($days[$row['d']])) {
        $days[$row['d']]['births'] = (int) $row['c'];
    }
}

// Screenings by type, split by result
$screenings = $pdo->query(
    "SELECT t.name,
            SUM(s.result = 'Normal') AS normal,
            SUM(s.result = 'Abnormal') AS abnormal,
            SUM(s.result = 'Pending') AS pending
     FROM newborn_screening_types t
     LEFT JOIN newborn_screenings s ON s.screening_type_id = t.id
     WHERE t.is_active = 1
     GROUP BY t.id, t.name ORDER BY t.name"
)->fetchAll();
foreach ($screenings as &$s) {
    $s['normal'] = (int) $s['normal'];
    $s['abnormal'] = (int) $s['abnormal'];
    $s['pending'] = (int) $s['pending'];
}
unset($s);

// Discharge readiness distribution (traffic light)
$distribution = ['green' => 0, 'yellow' => 0, 'red' => 0, 'discharged' => 0];
foreach ($pdo->query(discharge_overview_sql())->fetchAll() as $row) {
    $distribution[decorate_overview_row($row)['light']]++;
}

// Recent verifications
$recent = $pdo->query(
    'SELECT v.id, v.verification_result, v.verified_at, m.mother_id, n.newborn_id,
            n.first_name, n.last_name, n.sex, m.first_name AS mf, m.last_name AS ml, u.name AS verified_by_name
     FROM qr_verifications v
     JOIN mothers m ON m.id = v.mother_id
     JOIN newborns n ON n.id = v.newborn_id
     LEFT JOIN users u ON u.id = v.verified_by
     ORDER BY v.verified_at DESC, v.id DESC LIMIT 6'
)->fetchAll();
foreach ($recent as &$r) {
    $r['newborn_name'] = newborn_display_name($r);
    $r['mother_name'] = trim($r['mf'] . ' ' . $r['ml']);
}
unset($r);

// Recent admissions
$admissions = $pdo->query(
    'SELECT m.id, m.mother_id, m.first_name, m.last_name, m.admission_date, m.room_number,
            (SELECT COUNT(*) FROM newborns n WHERE n.mother_id = m.id) AS newborn_count
     FROM mothers m ORDER BY m.admission_date DESC LIMIT 6'
)->fetchAll();

json_success([
    'stats'                  => $stats,
    'admissions_trend'       => array_values($days),
    'screenings_by_type'     => $screenings,
    'readiness_distribution' => $distribution,
    'recent_verifications'   => $recent,
    'recent_admissions'      => $admissions,
    'generated_at'           => date('c'),
]);
