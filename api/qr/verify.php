<?php
/**
 * POST /api/qr/verify.php
 * Body: mother_qr (scanned text), newborn_qr (scanned text), notes (optional)
 *
 * Step 1 scans the mother's band, step 2 the baby's band. The pair is a
 * MATCH only when every check passes:
 *   - each code is the right type (mother / newborn)
 *   - signatures are valid (the band was produced by SafeMatch, unmodified)
 *   - the scanned data equals the current record (band is not outdated)
 *   - the newborn belongs to this mother in the database
 * Every attempt is recorded in qr_verifications (when both records exist)
 * and in the activity log. Mismatches notify all administrators.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/qr-functions.php';

api_init('POST');

$motherRaw = (string) input('mother_qr', '');
$newbornRaw = (string) input('newborn_qr', '');
$notes = mb_substr((string) input('notes', ''), 0, 1000);

$errors = validate(['mother_qr' => $motherRaw, 'newborn_qr' => $newbornRaw], [
    'mother_qr'  => 'required|maxlen:2000',
    'newborn_qr' => 'required|maxlen:2000',
], ['mother_qr' => "Mother's QR code", 'newborn_qr' => "Newborn's QR code"]);
if ($errors) {
    json_error('Both QR codes are required.', 422, $errors);
}

$pdo = db();
$m = parse_scanned_qr($motherRaw);
$n = parse_scanned_qr($newbornRaw);

$checks = [];
$addCheck = function (string $label, bool $passed, string $detail = '') use (&$checks) {
    $checks[] = ['label' => $label, 'passed' => $passed, 'detail' => $detail];
};

// --- Identify both records -------------------------------------------------
if ($m['id'] === null || $n['id'] === null) {
    log_activity('QR_VERIFY_INVALID', 'Unreadable QR code presented during verification');
    json_error('One of the scanned codes is not a SafeMatch QR code.', 422);
}

$stmt = $pdo->prepare('SELECT * FROM mothers WHERE mother_id = ?');
$stmt->execute([$m['id']]);
$mother = $stmt->fetch();

$stmt = $pdo->prepare('SELECT * FROM newborns WHERE newborn_id = ?');
$stmt->execute([$n['id']]);
$newborn = $stmt->fetch();

$typeOkMother = $m['manual'] ? str_starts_with($m['id'], 'M') : (($m['payload']['type'] ?? '') === 'mother');
$typeOkNewborn = $n['manual'] ? str_starts_with($n['id'], 'N') : (($n['payload']['type'] ?? '') === 'newborn');

if (!$typeOkMother || !$typeOkNewborn) {
    log_activity('QR_VERIFY_INVALID', "Wrong code type scanned (step 1: {$m['id']}, step 2: {$n['id']})");
    json_error('Wrong code type: scan the MOTHER\'s band first, then the BABY\'s band.', 422);
}
if (!$mother || !$newborn) {
    $missing = !$mother ? "mother {$m['id']}" : "newborn {$n['id']}";
    log_activity('QR_VERIFY_INVALID', "Verification failed: $missing not found in records");
    notify_admins('Unknown QR code scanned', "A verification attempt used an unknown code ($missing).", 'warning');
    json_error("No record found for $missing. This band may be invalid or the record was deleted.", 404);
}

// --- Run the checks -------------------------------------------------------
$addCheck('Mother record found', true, "{$mother['mother_id']} - " . full_name($mother));
$addCheck('Newborn record found', true, "{$newborn['newborn_id']} - " . newborn_display_name($newborn));

$manual = $m['manual'] || $n['manual'];
if ($manual) {
    $addCheck('Codes entered manually', true, 'IDs were typed, not scanned. Confirm identity visually.');
} else {
    $addCheck('Mother band authentic', $m['signature_ok'], $m['signature_ok'] ? 'Signature valid' : 'Signature invalid - band may be forged or altered');
    $addCheck('Newborn band authentic', $n['signature_ok'], $n['signature_ok'] ? 'Signature valid' : 'Signature invalid - band may be forged or altered');

    // Compare scanned payloads with what the current records would produce.
    $stmt = $pdo->prepare('SELECT * FROM mothers WHERE id = ?');
    $stmt->execute([$newborn['mother_id']]);
    $actualMother = $stmt->fetch();

    $currentMother = mother_qr_payload($mother);
    $currentNewborn = newborn_qr_payload($newborn, $actualMother);
    $motherCurrent = $m['payload'] == $currentMother;
    $newbornCurrent = $n['payload'] == $currentNewborn;
    $addCheck('Mother band up to date', $motherCurrent, $motherCurrent ? 'Matches current record' : 'Band data differs from the record - reprint the band');
    $addCheck('Newborn band up to date', $newbornCurrent, $newbornCurrent ? 'Matches current record' : 'Band data differs from the record - reprint the band');

    $bandLink = ($n['payload']['mother_id'] ?? null) === $mother['mother_id'];
    $addCheck("Baby's band names this mother", $bandLink,
        $bandLink ? "Band lists mother {$mother['mother_id']}" : 'Band lists mother ' . ($n['payload']['mother_id'] ?? 'unknown'));
}

$pairOk = (int) $newborn['mother_id'] === (int) $mother['id'];
$addCheck('Records linked as mother and baby', $pairOk,
    $pairOk ? 'Newborn is registered to this mother' : 'Newborn is registered to a DIFFERENT mother');

$isMatch = !in_array(false, array_column($checks, 'passed'), true);
$result = $isMatch ? 'match' : 'mismatch';

// --- Record the attempt ---------------------------------------------------
$failed = array_column(array_filter($checks, fn ($c) => !$c['passed']), 'label');
$storedNotes = trim(implode("\n", array_filter([
    $notes,
    $manual ? '[Manual ID entry]' : '',
    $failed ? 'Failed checks: ' . implode('; ', $failed) : '',
])));

$pdo->prepare(
    'INSERT INTO qr_verifications (mother_id, newborn_id, verified_by, verification_result, verification_notes)
     VALUES (?, ?, ?, ?, ?)'
)->execute([$mother['id'], $newborn['id'], current_user_id(), $result, $storedNotes ?: null]);
$verificationId = (int) $pdo->lastInsertId();

$pairLabel = "mother {$mother['mother_id']} / newborn {$newborn['newborn_id']}";
if ($isMatch) {
    log_activity('QR_VERIFY_MATCH', "Verified MATCH: $pairLabel" . ($manual ? ' (manual entry)' : ''));
} else {
    log_activity('QR_VERIFY_MISMATCH', "MISMATCH detected: $pairLabel. Failed: " . implode('; ', $failed));
    notify_admins(
        'QR verification mismatch',
        "Mismatch for $pairLabel by " . current_user()['name'] . '. Failed: ' . implode('; ', $failed),
        'error', 'qr_verification', $verificationId
    );
}

json_success([
    'result'          => $result,
    'verification_id' => $verificationId,
    'manual'          => $manual,
    'checks'          => $checks,
    'mother'          => [
        'id' => (int) $mother['id'], 'mother_id' => $mother['mother_id'], 'name' => full_name($mother),
        'room_number' => $mother['room_number'],
    ],
    'newborn'         => [
        'id' => (int) $newborn['id'], 'newborn_id' => $newborn['newborn_id'],
        'name' => newborn_display_name($newborn), 'sex' => $newborn['sex'],
        'date_of_birth' => $newborn['date_of_birth'],
    ],
], $isMatch ? 'MATCH - mother and baby are correctly paired.' : 'MISMATCH - do not release this baby. Supervisor has been notified.');
