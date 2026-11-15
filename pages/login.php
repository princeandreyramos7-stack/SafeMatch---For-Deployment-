<?php
/**
 * SafeMatch - Login page (the only app page that does not require a session).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../templates/icons.php';

if (is_logged_in()) {
    redirect('pages/dashboard.php');
}

$timedOut = !empty($_GET['timeout']) || !empty($_SESSION['timed_out']);
unset($_SESSION['timed_out']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · <?= e(APP_NAME) ?></title>
    <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#14B8A6"><path d="' . SM_ICONS['heart'] . '"/></svg>') ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/custom.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/tailwind.css')) ?>">
</head>
<body class="min-h-screen bg-white text-slate-800 antialiased">
<div class="min-h-screen grid lg:grid-cols-[1.05fr_1fr]">

    <!-- Brand panel -->
    <section class="relative hidden lg:flex flex-col justify-between overflow-hidden p-12 xl:p-16 bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 text-white">
        <div class="absolute -top-32 -right-32 w-[28rem] h-[28rem] rounded-full bg-white/10 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -bottom-40 -left-24 w-[26rem] h-[26rem] rounded-full bg-primary-400/20 blur-3xl" aria-hidden="true"></div>

        <a href="<?= e(url('')) ?>" class="relative flex items-center gap-3 w-fit">
            <span class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center"><?= icon('shield', 'w-6 h-6') ?></span>
            <span class="text-xl font-bold"><?= e(APP_NAME) ?></span>
        </a>

        <div class="relative max-w-md">
            <h2 class="text-4xl font-extrabold leading-tight tracking-tight">Every baby goes home with the right mother.</h2>
            <p class="mt-4 text-primary-50/90 text-lg">Verify every pair with signed QR bands before discharge.</p>

            <div class="mt-10 rounded-2xl bg-white/10 border border-white/20 backdrop-blur p-4 flex items-center gap-4" aria-hidden="true">
                <span class="w-12 h-12 rounded-full bg-emerald-400 text-emerald-950 flex items-center justify-center shrink-0"><?= icon('check', 'w-7 h-7') ?></span>
                <div>
                    <p class="font-extrabold text-xl leading-none">MATCH</p>
                    <p class="text-sm text-primary-50/90 mt-1">Mother and baby are correctly paired.</p>
                </div>
            </div>

            <ul class="mt-8 space-y-3 text-sm text-primary-50">
                <li class="flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center"><?= icon('qr', 'w-4 h-4') ?></span> Signed QR bands for mother and baby</li>
                <li class="flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center"><?= icon('badge', 'w-4 h-4') ?></span> Screenings and maternal assessments</li>
                <li class="flex items-center gap-3"><span class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center"><?= icon('exit', 'w-4 h-4') ?></span> Discharge checklist with a traffic light</li>
            </ul>
        </div>

        <p class="relative text-xs text-primary-100/70">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> · <?= e(APP_TAGLINE) ?></p>
    </section>

    <!-- Form panel -->
    <main class="flex flex-col px-5 sm:px-10 py-6 bg-gradient-to-b from-primary-50/60 to-white lg:bg-none">
        <div class="flex items-center justify-between">
            <a href="<?= e(url('')) ?>" class="lg:hidden flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-primary-500 text-white flex items-center justify-center"><?= icon('shield', 'w-5 h-5') ?></span>
                <span class="font-bold text-slate-900"><?= e(APP_NAME) ?></span>
            </a>
            <a href="<?= e(url('')) ?>" class="ml-auto inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-primary-700 min-h-[44px]">
                <span class="rotate-90"><?= icon('chevron', 'w-4 h-4') ?></span> Back to home
            </a>
        </div>

        <div class="flex-1 flex items-center justify-center py-8">
            <div class="w-full max-w-sm">
                <span class="hidden lg:flex w-12 h-12 rounded-2xl bg-primary-50 text-primary-600 items-center justify-center mb-5"><?= icon('key', 'w-6 h-6') ?></span>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Welcome back</h1>
                <p class="mt-2 text-slate-500">Sign in with your hospital staff account.</p>

                <?php if ($timedOut): ?>
                    <div class="mt-6 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                        <?= icon('clock', 'w-5 h-5 shrink-0 mt-0.5') ?>
                        <span>You were signed out after <?= (int) (SESSION_TIMEOUT / 60) ?> minutes of inactivity. Please sign in again.</span>
                    </div>
                <?php endif; ?>

                <div id="login-alert" class="hidden mt-6 flex gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    <?= icon('warning', 'w-5 h-5 shrink-0 mt-0.5') ?><span id="login-alert-text"></span>
                </div>

                <form id="login-form" class="mt-7 space-y-5" novalidate>
                    <?= csrf_field() ?>
                    <div data-field>
                        <label for="email" class="form-label">Email address</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3.5 flex items-center text-slate-400 pointer-events-none"><?= icon('user', 'w-5 h-5') ?></span>
                            <input id="email" name="email" type="email" class="form-input !pl-11 !min-h-[48px]" placeholder="you@hospital.com" autocomplete="username" required autofocus data-label="Email" inputmode="email" autocapitalize="none" spellcheck="false">
                        </div>
                    </div>
                    <div data-field>
                        <label for="password" class="form-label">Password</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3.5 flex items-center text-slate-400 pointer-events-none"><?= icon('key', 'w-5 h-5') ?></span>
                            <input id="password" name="password" type="password" class="form-input !pl-11 !pr-12 !min-h-[48px]" placeholder="Enter your password" autocomplete="current-password" required data-label="Password">
                            <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 touch-target text-slate-400 hover:text-slate-600" aria-label="Show password" aria-pressed="false">
                                <?= icon('eye', 'w-5 h-5') ?>
                            </button>
                        </div>
                        <p id="caps-warning" class="hidden mt-1.5 text-xs font-medium text-amber-700 flex items-center gap-1.5"><?= icon('warning', 'w-4 h-4') ?> Caps Lock is on</p>
                    </div>
                    <button type="submit" id="login-submit" class="btn btn-primary w-full !min-h-[48px] !text-base">Sign in</button>
                </form>

                <p class="mt-8 flex items-center justify-center gap-2 text-xs text-slate-400">
                    <?= icon('shield', 'w-4 h-4') ?> Authorized personnel only. All access is logged.
                </p>
            </div>
        </div>
    </main>
</div>

<div id="toast-container" class="fixed z-[60] top-4 right-4 left-4 sm:left-auto sm:w-96 flex flex-col gap-2 pointer-events-none" aria-live="polite"></div>

<script>
    window.SM_CONFIG = <?= json_encode(['baseUrl' => BASE_URL, 'csrfToken' => csrf_token(), 'sessionTimeout' => SESSION_TIMEOUT, 'user' => null], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.SM_ICONS = <?= json_encode(SM_ICONS, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= e(asset('assets/js/main.js')) ?>"></script>
<script>
    (function () {
        const form = document.getElementById('login-form');
        const alertBox = document.getElementById('login-alert');
        const alertText = document.getElementById('login-alert-text');
        const submit = document.getElementById('login-submit');
        const pwd = document.getElementById('password');
        const toggle = document.getElementById('toggle-password');

        SM.form.enableLiveValidation(form);

        toggle.addEventListener('click', () => {
            const show = pwd.type === 'password';
            pwd.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            toggle.setAttribute('aria-pressed', String(show));
            pwd.focus();
        });

        // Caps Lock hint while typing the password
        const caps = document.getElementById('caps-warning');
        const checkCaps = (e) => caps.classList.toggle('hidden', !(e.getModifierState && e.getModifierState('CapsLock')));
        pwd.addEventListener('keydown', checkCaps);
        pwd.addEventListener('keyup', checkCaps);
        pwd.addEventListener('blur', () => caps.classList.add('hidden'));

        function showAlert(message) {
            alertText.textContent = message;
            alertBox.classList.remove('hidden');
            alertBox.classList.remove('shake');
            void alertBox.offsetWidth; // restart the animation
            alertBox.classList.add('shake');
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            alertBox.classList.add('hidden');
            if (!SM.form.validate(form)) return;

            SM.setLoading(submit, true, 'Signing in…');
            try {
                const json = await SM.api('api/auth/login.php', { method: 'POST', data: SM.form.serialize(form) });
                submit.innerHTML = 'Signed in. Redirecting…';
                window.location.href = json.data.redirect;
            } catch (err) {
                SM.setLoading(submit, false);
                if (err.errors && Object.keys(err.errors).length) SM.form.showErrors(form, err.errors);
                showAlert(err.message);
                if (err.payload && err.payload.code === 'csrf_invalid') setTimeout(() => window.location.reload(), 1500);
                pwd.value = '';
                pwd.focus();
            }
        });
    })();
</script>
</body>
</html>
