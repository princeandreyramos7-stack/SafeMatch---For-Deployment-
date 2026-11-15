<?php
/**
 * SafeMatch - Public landing page.
 * Uses the session only (no database) so it always loads, even if the DB is down.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/templates/icons.php';

$loggedIn = !empty($_SESSION['user_id']);
$ctaHref = url($loggedIn ? 'pages/dashboard.php' : 'pages/login.php');
$ctaLabel = $loggedIn ? 'Open dashboard' : 'Sign in';

$features = [
    ['qr',       'Signed QR bands',          'Every mother and baby gets a matching QR band. Each code is digitally signed, so an edited or forged band is rejected.'],
    ['shield',   'Two-step verification',    'Scan the mother, then the baby. SafeMatch confirms both bands are genuine, current and registered to each other.'],
    ['badge',    'Screenings & assessments', 'Record hearing, blood, pulse oximetry and physical screenings, plus maternal vital signs, postpartum and mental health checks.'],
    ['exit',     'Discharge checklist',      'A configurable checklist with a red, yellow and green readiness light. Discharge unlocks only when everything required is done.'],
    ['bell',     'Alerts & audit trail',     'Mismatches and abnormal results notify administrators instantly. Every action is logged and exportable.'],
    ['key',      'Roles & security',         'Admin and healthcare roles, session timeouts, login lockout and protected records keep patient data safe.'],
];
$steps = [
    ['Register',  'Admit the mother and register her newborn. IDs are created automatically.'],
    ['Band',      'Print the QR bands and attach them in the delivery room.'],
    ['Verify',    'Scan mother, then baby. Get a clear MATCH or MISMATCH in seconds.'],
    ['Discharge', 'Complete the checklist and discharge the pair with a full record.'],
];
$faqs = [
    ['Do I need special equipment?', 'No. Any phone, tablet or laptop with a camera works. If a camera is not available, staff can scan from a photo or type the band ID.'],
    ['What happens if the bands do not match?', 'The result turns red, the baby must not be released, every administrator is notified, and the attempt is saved in the activity log.'],
    ['Can someone fake or alter a QR band?', 'Each band carries a cryptographic signature. If the details are changed, or the record is updated after printing, verification fails and asks for a reprint.'],
    ['Who can see patient records?', 'Only signed-in staff. Administrators manage users and settings, and every sign-in and change is recorded.'],
];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> · <?= e(APP_TAGLINE) ?></title>
    <meta name="description" content="SafeMatch helps maternity wards make sure every baby goes home with the right mother, using signed QR bands and a verified discharge checklist.">
    <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#14B8A6"><path d="' . SM_ICONS['heart'] . '"/></svg>') ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/custom.css')) ?>?v=<?= e(APP_VERSION) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/tailwind.css')) ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body class="bg-white text-slate-800 antialiased">

<!-- Navigation -->
<header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-slate-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
        <a href="<?= e(url('')) ?>" class="flex items-center gap-2.5" aria-label="<?= e(APP_NAME) ?> home">
            <span class="w-9 h-9 rounded-xl bg-primary-500 text-white flex items-center justify-center"><?= icon('shield', 'w-5 h-5') ?></span>
            <span class="font-bold text-slate-900 text-lg"><?= e(APP_NAME) ?></span>
        </a>
        <nav class="hidden md:flex items-center gap-7 text-sm font-medium text-slate-600" aria-label="Sections">
            <a href="#features" class="hover:text-primary-700">Features</a>
            <a href="#how" class="hover:text-primary-700">How it works</a>
            <a href="#discharge" class="hover:text-primary-700">Discharge</a>
            <a href="#faq" class="hover:text-primary-700">FAQ</a>
        </nav>
        <div class="flex items-center gap-2">
            <a href="<?= e($ctaHref) ?>" class="btn btn-primary"><?= e($ctaLabel) ?></a>
            <button type="button" id="nav-toggle" class="md:hidden touch-target rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Menu" aria-expanded="false" aria-controls="mobile-nav"><?= icon('menu', 'w-6 h-6') ?></button>
        </div>
    </div>
    <nav id="mobile-nav" class="hidden md:hidden border-t border-slate-100 bg-white px-4 py-2" aria-label="Sections">
        <?php foreach (['features' => 'Features', 'how' => 'How it works', 'discharge' => 'Discharge', 'faq' => 'FAQ'] as $id => $label): ?>
            <a href="#<?= $id ?>" class="block py-3 text-sm font-medium text-slate-700 border-b border-slate-50 last:border-0"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
</header>

<main>
    <!-- Hero -->
    <section class="relative overflow-hidden bg-gradient-to-b from-primary-50 via-white to-white">
        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-primary-100/60 blur-3xl" aria-hidden="true"></div>
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 py-14 sm:py-20 lg:py-24 grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-white border border-primary-100 px-3 py-1 text-xs font-semibold text-primary-700 shadow-sm">
                    <?= icon('heart', 'w-3.5 h-3.5') ?> Built for maternity wards
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold tracking-tight text-slate-900 leading-[1.1]">
                    Every baby goes home with the <span class="text-primary-600">right mother.</span>
                </h1>
                <p class="mt-5 text-lg text-slate-600 max-w-xl">
                    SafeMatch pairs each mother and newborn with signed QR bands and checks them before discharge, so a mix-up never leaves your ward.
                </p>
                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                    <a href="<?= e($ctaHref) ?>" class="btn btn-primary !min-h-[48px] !px-6 !text-base"><?= $loggedIn ? 'Open dashboard' : 'Sign in to SafeMatch' ?> <?= icon('exit', 'w-5 h-5') ?></a>
                    <a href="#how" class="btn btn-secondary !min-h-[48px] !px-6 !text-base">See how it works</a>
                </div>
                <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-600">
                    <li class="flex items-center gap-2"><span class="text-emerald-600"><?= icon('check', 'w-4 h-4') ?></span> Signed QR bands</li>
                    <li class="flex items-center gap-2"><span class="text-emerald-600"><?= icon('check', 'w-4 h-4') ?></span> Works on any phone</li>
                    <li class="flex items-center gap-2"><span class="text-emerald-600"><?= icon('check', 'w-4 h-4') ?></span> Full audit trail</li>
                </ul>
            </div>

            <!-- Product mockup -->
            <div class="relative mx-auto w-full max-w-md" aria-hidden="true">
                <div class="rounded-3xl bg-white border border-slate-200 shadow-2xl shadow-primary-900/10 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-sm font-semibold text-slate-900">Verify mother &amp; baby</p>
                        <span class="text-[11px] font-medium text-slate-400">Live</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-3">
                            <div class="flex items-center gap-2">
                                <span class="w-8 h-8 rounded-lg bg-emerald-500 text-white flex items-center justify-center"><?= icon('check', 'w-4 h-4') ?></span>
                                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Mother</p><p class="text-sm font-semibold truncate">Maria Santos</p></div>
                            </div>
                            <div class="mt-3 rounded-xl bg-white p-2 flex justify-center"><?= icon('qr', 'w-20 h-20 text-slate-800') ?></div>
                        </div>
                        <div class="rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-3">
                            <div class="flex items-center gap-2">
                                <span class="w-8 h-8 rounded-lg bg-emerald-500 text-white flex items-center justify-center"><?= icon('check', 'w-4 h-4') ?></span>
                                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Baby</p><p class="text-sm font-semibold truncate">Baby Girl Santos</p></div>
                            </div>
                            <div class="mt-3 rounded-xl bg-white p-2 flex justify-center"><?= icon('qr', 'w-20 h-20 text-slate-800') ?></div>
                        </div>
                    </div>
                    <div class="mt-4 rounded-2xl result-match border-2 p-4 flex items-center gap-4">
                        <span class="w-12 h-12 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0"><?= icon('check', 'w-7 h-7') ?></span>
                        <div><p class="text-2xl font-extrabold text-emerald-800 leading-none">MATCH</p><p class="text-xs text-emerald-900 mt-1">Mother and baby are correctly paired.</p></div>
                    </div>
                </div>
                <div class="absolute -bottom-5 -left-3 sm:-left-8 rounded-2xl bg-white border border-slate-200 shadow-xl px-4 py-3 flex items-center gap-3">
                    <span class="light-dot light-green !w-4 !h-4"></span>
                    <div><p class="text-xs text-slate-500 leading-tight">Discharge readiness</p><p class="text-sm font-bold text-slate-900 leading-tight">14 of 14 complete</p></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Problem strip -->
    <section class="bg-slate-900 text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 grid sm:grid-cols-3 gap-6 text-center sm:text-left">
            <div><p class="text-3xl font-extrabold text-primary-400">2 scans</p><p class="text-sm text-slate-300 mt-1">is all it takes to confirm a pair before discharge.</p></div>
            <div><p class="text-3xl font-extrabold text-primary-400">0 guesswork</p><p class="text-sm text-slate-300 mt-1">checks run automatically, with a clear pass or fail.</p></div>
            <div><p class="text-3xl font-extrabold text-primary-400">100% logged</p><p class="text-sm text-slate-300 mt-1">every verification and discharge is recorded.</p></div>
        </div>
    </section>

    <!-- Features -->
    <section id="features" class="scroll-mt-20 max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="max-w-2xl mx-auto text-center">
            <p class="text-sm font-semibold text-primary-600">Features</p>
            <h2 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">Everything the ward needs, in one place</h2>
            <p class="mt-3 text-slate-600">From admission to discharge, SafeMatch keeps mother and baby linked and every step recorded.</p>
        </div>
        <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($features as [$ic, $title, $text]): ?>
                <article class="rounded-2xl border border-slate-200 bg-white p-6 hover:border-primary-300 hover:shadow-lg hover:shadow-primary-900/5 transition">
                    <span class="w-11 h-11 rounded-xl bg-primary-50 text-primary-700 flex items-center justify-center"><?= icon($ic, 'w-6 h-6') ?></span>
                    <h3 class="mt-4 font-semibold text-slate-900"><?= e($title) ?></h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= e($text) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- How it works -->
    <section id="how" class="scroll-mt-20 bg-slate-50 border-y border-slate-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
            <div class="max-w-2xl mx-auto text-center">
                <p class="text-sm font-semibold text-primary-600">How it works</p>
                <h2 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">From delivery room to discharge in four steps</h2>
            </div>
            <ol class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <?php foreach ($steps as $i => [$title, $text]): ?>
                    <li class="relative rounded-2xl bg-white border border-slate-200 p-6">
                        <span class="w-10 h-10 rounded-full bg-primary-500 text-white font-bold flex items-center justify-center"><?= $i + 1 ?></span>
                        <h3 class="mt-4 font-semibold text-slate-900"><?= e($title) ?></h3>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= e($text) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <!-- Discharge traffic light -->
    <section id="discharge" class="scroll-mt-20 max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-20 grid lg:grid-cols-2 gap-12 items-center">
        <div>
            <p class="text-sm font-semibold text-primary-600">Safe discharge</p>
            <h2 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">Discharge only when it is truly safe</h2>
            <p class="mt-4 text-slate-600">SafeMatch combines the clinical checklist with the QR check. The discharge button stays locked until both are complete, and it tells staff exactly what is missing.</p>
            <ul class="mt-6 space-y-3 text-sm text-slate-700">
                <li class="flex gap-3"><span class="mt-0.5 text-emerald-600"><?= icon('check', 'w-5 h-5') ?></span> Separate requirements for mother, newborn and both</li>
                <li class="flex gap-3"><span class="mt-0.5 text-emerald-600"><?= icon('check', 'w-5 h-5') ?></span> Latest QR verification must be a match</li>
                <li class="flex gap-3"><span class="mt-0.5 text-emerald-600"><?= icon('check', 'w-5 h-5') ?></span> Completed discharges are locked for the record</li>
            </ul>
        </div>
        <div class="space-y-3">
            <?php foreach ([
                ['red',    'Not ready',   'Many requirements are still pending.', 'w-1/4',  'bg-red-500'],
                ['yellow', 'In progress', 'At least half done, mandatory items remain.', 'w-3/5', 'bg-amber-500'],
                ['green',  'Ready',       'All required items met and bands verified.', 'w-full', 'bg-emerald-500'],
            ] as [$light, $label, $desc, $width, $bar]): ?>
                <div class="rounded-2xl border border-slate-200 p-4 flex items-center gap-4">
                    <span class="light-dot light-<?= $light ?> !w-5 !h-5"></span>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-slate-900"><?= e($label) ?></p>
                        <p class="text-xs text-slate-500"><?= e($desc) ?></p>
                        <div class="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full <?= $bar ?> <?= $width ?>"></div></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="scroll-mt-20 bg-slate-50 border-y border-slate-100">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
            <h2 class="text-3xl font-bold tracking-tight text-slate-900 text-center">Frequently asked questions</h2>
            <div class="mt-10 space-y-3">
                <?php foreach ($faqs as [$q, $a]): ?>
                    <details class="group rounded-2xl bg-white border border-slate-200 px-5 py-4">
                        <summary class="flex items-center justify-between gap-4 cursor-pointer font-semibold text-slate-900 list-none">
                            <?= e($q) ?>
                            <span class="shrink-0 text-slate-400 group-open:rotate-180 transition-transform"><?= icon('chevron', 'w-5 h-5') ?></span>
                        </summary>
                        <p class="mt-3 text-sm text-slate-600 leading-relaxed"><?= e($a) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-16">
        <div class="rounded-3xl bg-gradient-to-br from-primary-600 to-primary-800 px-6 py-12 sm:px-12 text-center text-white">
            <h2 class="text-3xl font-bold tracking-tight">Ready to protect every discharge?</h2>
            <p class="mt-3 text-primary-50/90 max-w-xl mx-auto">Sign in with your hospital staff account to start verifying mothers and babies.</p>
            <a href="<?= e($ctaHref) ?>" class="mt-7 inline-flex items-center justify-center min-h-[48px] px-7 rounded-xl bg-white text-primary-800 font-semibold hover:bg-primary-50 transition"><?= e($loggedIn ? 'Open dashboard' : 'Sign in to SafeMatch') ?></a>
        </div>
    </section>
</main>

<footer class="border-t border-slate-100">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between text-sm text-slate-500">
        <div class="flex items-center gap-2.5">
            <span class="w-7 h-7 rounded-lg bg-primary-500 text-white flex items-center justify-center"><?= icon('shield', 'w-4 h-4') ?></span>
            <span class="font-semibold text-slate-700"><?= e(APP_NAME) ?></span>
            <span class="hidden sm:inline">· <?= e(APP_TAGLINE) ?></span>
        </div>
        <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Authorized personnel only.</p>
    </div>
</footer>

<script>
    (function () {
        var btn = document.getElementById('nav-toggle');
        var nav = document.getElementById('mobile-nav');
        btn.addEventListener('click', function () {
            var open = nav.classList.toggle('hidden') === false;
            btn.setAttribute('aria-expanded', String(open));
        });
        nav.addEventListener('click', function (e) {
            if (e.target.tagName === 'A') { nav.classList.add('hidden'); btn.setAttribute('aria-expanded', 'false'); }
        });
    })();
</script>
</body>
</html>
