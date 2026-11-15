<?php
/**
 * SafeMatch - Dashboard: live statistics and charts.
 */
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$pageScripts = ['dashboard.js'];
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
require __DIR__ . '/../templates/header.php';

$statCards = [
    ['key' => 'total_mothers',      'label' => 'Total Mothers',       'icon' => 'user',  'tone' => 'bg-primary-50 text-primary-700', 'href' => 'pages/mothers.php'],
    ['key' => 'total_newborns',     'label' => 'Total Newborns',      'icon' => 'heart', 'tone' => 'bg-pink-50 text-pink-700',       'href' => 'pages/newborns.php'],
    ['key' => 'todays_admissions',  'label' => "Today's Admissions",  'icon' => 'clock', 'tone' => 'bg-blue-50 text-blue-700',       'href' => 'pages/mothers.php?date_from=' . date('Y-m-d')],
    ['key' => 'pending_discharges', 'label' => 'Pending Discharges',  'icon' => 'exit',  'tone' => 'bg-amber-50 text-amber-700',     'href' => 'pages/discharge.php?status=open'],
    ['key' => 'verifications_7d',   'label' => 'Verifications (7 days)', 'icon' => 'qr', 'tone' => 'bg-emerald-50 text-emerald-700', 'href' => 'pages/qr-verification.php'],
];
?>
<div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-5">
    <div>
        <p class="text-sm text-slate-500"><?= e(date('l, F j, Y')) ?></p>
        <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Good <?= (int) date('G') < 12 ? 'morning' : ((int) date('G') < 18 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $user['name'])[0]) ?></h2>
    </div>
    <div class="grid grid-cols-1 sm:flex gap-2">
        <a href="<?= e(url('pages/mothers.php?new=1')) ?>" class="btn btn-secondary"><?= icon('plus', 'w-4 h-4') ?> Register Mother</a>
        <a href="<?= e(url('pages/newborns.php?new=1')) ?>" class="btn btn-secondary"><?= icon('plus', 'w-4 h-4') ?> Register Newborn</a>
        <a href="<?= e(url('pages/qr-verification.php')) ?>" class="btn btn-primary"><?= icon('qr', 'w-4 h-4') ?> Verify Pair</a>
    </div>
</div>

<!-- Stat cards -->
<section class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4" aria-label="Key statistics">
    <?php foreach ($statCards as $i => $card): ?>
        <a href="<?= e(url($card['href'])) ?>" class="card p-4 hover:border-primary-300 transition-colors <?= $i === 4 ? 'col-span-2 lg:col-span-1' : '' ?>">
            <div class="flex items-center justify-between">
                <span class="w-10 h-10 rounded-xl flex items-center justify-center <?= $card['tone'] ?>"><?= icon($card['icon'], 'w-5 h-5') ?></span>
            </div>
            <p class="mt-3 text-2xl sm:text-3xl font-bold text-slate-900 tabular-nums" data-stat="<?= e($card['key']) ?>"><span class="skeleton inline-block h-7 w-12"></span></p>
            <p class="text-sm text-slate-500"><?= e($card['label']) ?></p>
            <?php if ($card['key'] === 'verifications_7d'): ?>
                <p class="text-xs mt-1" data-stat-note="mismatches"></p>
            <?php elseif ($card['key'] === 'pending_discharges'): ?>
                <p class="text-xs mt-1 text-slate-500" data-stat-note="ready"></p>
            <?php elseif ($card['key'] === 'todays_admissions'): ?>
                <p class="text-xs mt-1 text-slate-500" data-stat-note="births"></p>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</section>

<!-- Attention items -->
<section id="alerts" class="mt-4 grid gap-3 sm:grid-cols-3 empty:hidden" aria-label="Items needing attention"></section>

<!-- Charts -->
<section class="mt-5 grid gap-4 lg:grid-cols-3">
    <div class="card p-4 sm:p-5 lg:col-span-2">
        <div class="flex items-center justify-between gap-2">
            <div>
                <h3 class="font-semibold text-slate-900">Admissions trend</h3>
                <p class="text-xs text-slate-500">Mothers admitted and babies born, last 7 days</p>
            </div>
        </div>
        <div class="chart-box mt-3"><canvas id="chart-admissions" role="img" aria-label="Line chart of admissions and births over the last 7 days"></canvas></div>
        <details class="mt-2 text-sm"><summary class="cursor-pointer text-slate-500 text-xs">View as table</summary><div id="table-admissions" class="table-wrap mt-2"></div></details>
    </div>

    <div class="card p-4 sm:p-5">
        <h3 class="font-semibold text-slate-900">Discharge readiness</h3>
        <p class="text-xs text-slate-500">All mother-baby pairs by status</p>
        <div class="chart-box mt-3"><canvas id="chart-readiness" role="img" aria-label="Bar chart of discharge readiness"></canvas></div>
        <ul id="readiness-legend" class="mt-2 grid grid-cols-2 gap-2 text-xs"></ul>
    </div>

    <div class="card p-4 sm:p-5 lg:col-span-2">
        <h3 class="font-semibold text-slate-900">Screenings by type</h3>
        <p class="text-xs text-slate-500">All newborn screenings recorded, by result</p>
        <div class="chart-box mt-3"><canvas id="chart-screenings" role="img" aria-label="Stacked bar chart of screenings by type and result"></canvas></div>
        <details class="mt-2 text-sm"><summary class="cursor-pointer text-slate-500 text-xs">View as table</summary><div id="table-screenings" class="table-wrap mt-2"></div></details>
    </div>

    <div class="card p-4 sm:p-5">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-slate-900">Recent verifications</h3>
            <a href="<?= e(url('pages/activity-logs.php?action=QR_VERIFY')) ?>" class="text-xs font-medium text-primary-700 hover:underline">View all</a>
        </div>
        <ul id="recent-verifications" class="mt-3 divide-y divide-slate-100 text-sm"></ul>
    </div>
</section>

<section class="mt-4 card">
    <div class="flex items-center justify-between px-4 sm:px-5 py-4 border-b border-slate-100">
        <h3 class="font-semibold text-slate-900">Recent admissions</h3>
        <a href="<?= e(url('pages/mothers.php')) ?>" class="text-xs font-medium text-primary-700 hover:underline">All mothers</a>
    </div>
    <div class="table-wrap">
        <table class="data-table" id="recent-admissions">
            <thead><tr><th>Mother ID</th><th>Name</th><th>Admitted</th><th>Room</th><th>Babies</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../templates/footer.php'; ?>
