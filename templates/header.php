<?php
/**
 * SafeMatch - Page header template
 *
 * Variables a page may set before including this file:
 *   $pageTitle    (string)  title shown in the header and <title>
 *   $activePage   (string)  sidebar key to highlight
 *   $adminOnly    (bool)    restrict the page to administrators
 *   $pageScripts  (array)   JS files in assets/js to load after main.js
 *   $extraHead    (string)  raw HTML added to <head> (e.g. CDN scripts)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/icons.php';

if (!empty($adminOnly)) {
    require_admin();
} else {
    require_login();
}

$user = current_user();
$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? '';
$avatarUrl = profile_picture_url($user['profile_picture']);
$initials = strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice(preg_split('/\s+/', trim($user['name'])), 0, 2))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#14B8A6"><path d="' . SM_ICONS['heart'] . '"/></svg>') ?>">
    
    <link rel="stylesheet" href="<?= e(asset('assets/css/custom.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/tailwind.css')) ?>">
    <?= $extraHead ?? '' ?>
    <script>
        window.SM_CONFIG = <?= json_encode([
            'baseUrl'        => BASE_URL,
            'csrfToken'      => csrf_token(),
            'sessionTimeout' => SESSION_TIMEOUT,
            'user'           => ['id' => (int) $user['id'], 'name' => $user['name'], 'role' => $user['role']],
            'isAdmin'        => is_admin(),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        window.SM_ICONS = <?= json_encode(SM_ICONS, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
    </script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 bg-white px-3 py-2 rounded shadow">Skip to content</a>

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="lg:pl-64 min-h-screen flex flex-col">
    <!-- Top header -->
    <header class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-slate-200">
        <div class="h-16 flex items-center gap-3 px-4 sm:px-6">
            <button type="button" id="sidebar-open" class="lg:hidden touch-target -ml-2 rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Open navigation">
                <?= icon('menu', 'w-6 h-6') ?>
            </button>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-primary-600 lg:hidden"><?= e(APP_NAME) ?></p>
                <h1 class="text-base sm:text-lg font-semibold text-slate-900 truncate"><?= e($pageTitle) ?></h1>
            </div>

            <!-- Notifications -->
            <div class="relative" id="notif-wrapper">
                <button type="button" id="notif-button" class="touch-target relative rounded-full text-slate-600 hover:bg-slate-100" aria-label="Notifications" aria-expanded="false" aria-haspopup="true">
                    <?= icon('bell', 'w-6 h-6') ?>
                    <span id="notif-count" class="hidden absolute top-1.5 right-1.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-[18px] text-center"></span>
                </button>
                <div id="notif-panel" class="hidden absolute right-0 mt-2 w-[min(22rem,calc(100vw-2rem))] bg-white rounded-xl shadow-xl border border-slate-200 overflow-hidden z-30">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                        <p class="font-semibold text-sm">Notifications</p>
                        <button type="button" id="notif-mark-all" class="text-xs font-medium text-primary-700 hover:underline">Mark all as read</button>
                    </div>
                    <ul id="notif-list" class="max-h-96 overflow-y-auto divide-y divide-slate-100 text-sm">
                        <li class="px-4 py-6 text-center text-slate-500">Loading…</li>
                    </ul>
                </div>
            </div>

            <!-- User dropdown -->
            <div class="relative" id="user-menu-wrapper">
                <button type="button" id="user-menu-button" class="flex items-center gap-2 rounded-full sm:rounded-lg p-1 sm:pr-2 hover:bg-slate-100 min-h-[44px]" aria-expanded="false" aria-haspopup="true">
                    <?php if ($avatarUrl): ?>
                        <img src="<?= e($avatarUrl) ?>" alt="" class="w-9 h-9 rounded-full object-cover" data-user-avatar>
                    <?php else: ?>
                        <span class="w-9 h-9 rounded-full bg-primary-100 text-primary-700 font-semibold text-sm flex items-center justify-center" data-user-avatar><?= e($initials) ?></span>
                    <?php endif; ?>
                    <span class="hidden sm:block text-left">
                        <span class="block text-sm font-medium text-slate-800 leading-tight" data-user-name><?= e($user['name']) ?></span>
                        <span class="block text-xs text-slate-500 leading-tight"><?= $user['role'] === ROLE_ADMIN ? 'Administrator' : 'Healthcare Personnel' ?></span>
                    </span>
                    <?= icon('chevron', 'hidden sm:block w-4 h-4 text-slate-400') ?>
                </button>
                <div id="user-menu" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-200 py-1 z-30" role="menu">
                    <div class="px-4 py-3 border-b border-slate-100">
                        <p class="text-sm font-medium truncate"><?= e($user['name']) ?></p>
                        <p class="text-xs text-slate-500 truncate"><?= e($user['email']) ?></p>
                    </div>
                    <a href="<?= e(url('pages/profile.php')) ?>" class="flex items-center gap-2 px-4 py-3 text-sm hover:bg-slate-50" role="menuitem"><?= icon('user', 'w-4 h-4') ?> My Profile</a>
                    <button type="button" data-logout class="w-full flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50" role="menuitem"><?= icon('logout', 'w-4 h-4') ?> Logout</button>
                </div>
            </div>
        </div>
    </header>

    <main id="main-content" class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 py-5 sm:py-6">
        <?php if (!empty($_GET['denied'])): ?>
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">You do not have permission to access that page.</div>
        <?php endif; ?>
