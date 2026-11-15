<?php
/**
 * SafeMatch - Sidebar navigation (off-canvas on mobile, fixed on desktop).
 * Expects $activePage from the including page.
 */
$navItems = [
    ['key' => 'dashboard',    'label' => 'Dashboard',            'icon' => 'home',     'href' => 'pages/dashboard.php'],
    ['key' => 'mothers',      'label' => 'Mothers',              'icon' => 'user',     'href' => 'pages/mothers.php'],
    ['key' => 'newborns',     'label' => 'Newborns',             'icon' => 'heart',    'href' => 'pages/newborns.php'],
    ['key' => 'qr',           'label' => 'QR Verification',      'icon' => 'qr',       'href' => 'pages/qr-verification.php'],
    ['key' => 'screenings',   'label' => 'Screenings',           'icon' => 'badge',    'href' => 'pages/screenings.php'],
    ['key' => 'assessments',  'label' => 'Maternal Assessments', 'icon' => 'document', 'href' => 'pages/assessments.php'],
    ['key' => 'discharge',    'label' => 'Discharge',            'icon' => 'exit',     'href' => 'pages/discharge.php'],
    ['key' => 'activity',     'label' => 'Activity Logs',        'icon' => 'list',     'href' => 'pages/activity-logs.php'],
    ['key' => 'users',        'label' => 'Users',                'icon' => 'users',    'href' => 'pages/users.php', 'admin' => true],
];
?>
<div id="sidebar-overlay" class="fixed inset-0 z-30 bg-slate-900/50 hidden lg:hidden" aria-hidden="true"></div>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-64 bg-white border-r border-slate-200 flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-200" aria-label="Main navigation">
    <div class="h-16 flex items-center justify-between px-5 border-b border-slate-100">
        <a href="<?= e(url('pages/dashboard.php')) ?>" class="flex items-center gap-2.5">
            <span class="w-9 h-9 rounded-xl bg-primary-500 text-white flex items-center justify-center"><?= icon('shield', 'w-5 h-5') ?></span>
            <span>
                <span class="block font-bold text-slate-900 leading-tight"><?= e(APP_NAME) ?></span>
                <span class="block text-[11px] text-slate-500 leading-tight">Mother-Baby ID System</span>
            </span>
        </a>
        <button type="button" id="sidebar-close" class="lg:hidden touch-target rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Close navigation">
            <?= icon('x', 'w-5 h-5') ?>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        <?php foreach ($navItems as $item):
            if (!empty($item['admin']) && !is_admin()) {
                continue;
            }
            $active = $activePage === $item['key'];
            ?>
            <a href="<?= e(url($item['href'])) ?>"
               class="flex items-center gap-3 px-3 min-h-[44px] rounded-lg text-sm font-medium transition-colors <?= $active ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>"
               <?= $active ? 'aria-current="page"' : '' ?>>
                <?= icon($item['icon'], 'w-5 h-5 shrink-0 ' . ($active ? 'text-primary-600' : 'text-slate-400')) ?>
                <span><?= e($item['label']) ?></span>
                <?php if (!empty($item['admin'])): ?>
                    <span class="ml-auto text-[10px] uppercase tracking-wide font-semibold text-slate-400">Admin</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="p-4 border-t border-slate-100 text-xs text-slate-400">
        <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?>
    </div>
</aside>
