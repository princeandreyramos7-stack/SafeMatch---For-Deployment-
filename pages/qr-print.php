<?php
/**
 * SafeMatch - Printable QR identification bands for a mother-newborn pair.
 * Opens in a new tab; use the browser's print dialog.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/qr-functions.php';
require_once __DIR__ . '/../templates/icons.php';

require_login();

$newbornId = (int) ($_GET['newborn_id'] ?? 0);
$stmt = db()->prepare(
    'SELECT n.*, m.mother_id AS mother_code, m.first_name AS m_first, m.last_name AS m_last,
            m.room_number, m.date_of_birth AS m_dob, m.admission_date
     FROM newborns n JOIN mothers m ON m.id = n.mother_id WHERE n.id = ?'
);
$stmt->execute([$newbornId]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    exit('Newborn not found.');
}

$stmt = db()->prepare('SELECT * FROM qr_codes WHERE newborn_id = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$newbornId]);
$qr = $stmt->fetch() ?: generate_qr_pair($newbornId, current_user_id());

log_activity('QR_PRINT', "Opened printable QR bands for newborn {$row['newborn_id']}");

$babyName = newborn_display_name($row);
$motherName = trim($row['m_first'] . ' ' . $row['m_last']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR Bands · <?= e($row['newborn_id']) ?> · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url("assets/css/tailwind.css")) ?>?v=<?= e(APP_VERSION) ?>">
    <style>
        @page { margin: 12mm; }
        .band { break-inside: avoid; page-break-inside: avoid; }
        .band img { image-rendering: pixelated; }
        @media print { .no-print { display: none !important; } body { background: #fff !important; } }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 p-4 sm:p-8">
<div class="max-w-3xl mx-auto">
    <div class="no-print flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold">Identification bands</h1>
            <p class="text-sm text-slate-500">Print and attach to the mother's and baby's wrists before leaving the delivery room.</p>
        </div>
        <button onclick="window.print()" class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 rounded-lg bg-teal-500 hover:bg-teal-600 text-white font-semibold">
            <?= icon('printer', 'w-5 h-5') ?> Print
        </button>
    </div>

    <?php
    $bands = [
        ['label' => 'MOTHER', 'name' => $motherName, 'code' => $row['mother_code'], 'img' => $qr['mother_qr_code'],
         'lines' => ['DOB ' . format_date($row['m_dob']), 'Admitted ' . format_date($row['admission_date']), $row['room_number'] ? 'Room ' . $row['room_number'] : '']],
        ['label' => 'BABY', 'name' => $babyName, 'code' => $row['newborn_id'], 'img' => $qr['newborn_qr_code'],
         'lines' => [$row['sex'], 'Born ' . format_date($row['date_of_birth']) . ' ' . substr($row['time_of_birth'], 0, 5), 'Mother: ' . $row['mother_code'] . ' ' . $motherName]],
    ];
    foreach ($bands as $band): ?>
        <section class="band bg-white border-2 border-dashed border-slate-300 rounded-2xl p-5 mb-6 flex items-center gap-5">
            <img src="<?= e($band['img']) ?>" alt="QR code <?= e($band['code']) ?>" class="w-36 h-36 sm:w-44 sm:h-44 shrink-0">
            <div class="min-w-0">
                <p class="text-xs font-bold tracking-[0.2em] text-teal-700"><?= e(APP_NAME) ?> · <?= e($band['label']) ?></p>
                <p class="text-2xl font-bold leading-tight mt-1 break-words"><?= e($band['name']) ?></p>
                <p class="font-mono text-lg font-semibold mt-1"><?= e($band['code']) ?></p>
                <?php foreach (array_filter($band['lines']) as $line): ?>
                    <p class="text-sm text-slate-600"><?= e($line) ?></p>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <p class="text-xs text-slate-400">Generated <?= e(format_date($qr['generated_at'], 'M d, Y g:i A')) ?> · Codes are signed; altered bands will fail verification.</p>
</div>
</body>
</html>
