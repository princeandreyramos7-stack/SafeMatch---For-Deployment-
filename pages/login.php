<?php
/**
 * SafeMatch - Login page (the only page that does not require a session).
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
    <title>Login · <?= e(APP_NAME) ?></title>
    <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#14B8A6"><path d="' . SM_ICONS['heart'] . '"/></svg>') ?>">
    
    <link rel="stylesheet" href="<?= e(url('assets/css/custom.css')) ?>?v=<?= e(APP_VERSION) ?>">
    <link rel="stylesheet" href="<?= e(url("assets/css/tailwind.css")) ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<div class="min-h-screen grid lg:grid-cols-2">
    <!-- Brand panel -->
    <section class="hidden lg:flex flex-col justify-between p-12 bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 text-white">
        <div class="flex items-center gap-3">
            <span class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center"><?= icon('shield', 'w-6 h-6') ?></span>
            <span class="text-xl font-bold"><?= e(APP_NAME) ?></span>
        </div>
        <div class="max-w-md">
            <h2 class="text-3xl font-bold leading-tight">Every baby goes home with the right mother.</h2>
            <p class="mt-4 text-primary-50/90">Register mothers and newborns, issue matching QR bands, and verify every pair before discharge.</p>
            <ul class="mt-8 space-y-3 text-sm text-primary-50">
                <li class="flex items-center gap-3"><?= icon('qr', 'w-5 h-5') ?> Signed QR bands for mother and baby</li>
                <li class="flex items-center gap-3"><?= icon('badge', 'w-5 h-5') ?> Newborn screenings and maternal assessments</li>
                <li class="flex items-center gap-3"><?= icon('exit', 'w-5 h-5') ?> Discharge readiness checklist with a traffic light</li>
            </ul>
        </div>
        <p class="text-xs text-primary-100/70">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> · <?= e(APP_TAGLINE) ?></p>
    </section>

    <!-- Login form -->
    <main class="flex items-center justify-center px-4 py-10 sm:px-8">
        <div class="w-full max-w-sm">
            <div class="lg:hidden flex items-center gap-3 mb-8">
                <span class="w-11 h-11 rounded-xl bg-primary-500 text-white flex items-center justify-center"><?= icon('shield', 'w-6 h-6') ?></span>
                <div>
                    <p class="text-xl font-bold text-slate-900"><?= e(APP_NAME) ?></p>
                    <p class="text-xs text-slate-500"><?= e(APP_TAGLINE) ?></p>
                </div>
            </div>

            <h1 class="text-2xl font-bold text-slate-900">Sign in</h1>
            <p class="mt-1 text-sm text-slate-500">Use your hospital staff account.</p>

            <?php if ($timedOut): ?>
                <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status">
                    Your session expired after <?= (int) (SESSION_TIMEOUT / 60) ?> minutes of inactivity. Please sign in again.
                </div>
            <?php endif; ?>

            <div id="login-alert" class="hidden mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert"></div>

            <form id="login-form" class="mt-6 space-y-5" novalidate>
                <?= csrf_field() ?>
                <div data-field>
                    <label for="email" class="form-label">Email address <span class="req">*</span></label>
                    <input id="email" name="email" type="email" class="form-input" autocomplete="username" required autofocus data-label="Email">
                </div>
                <div data-field>
                    <label for="password" class="form-label">Password <span class="req">*</span></label>
                    <div class="relative">
                        <input id="password" name="password" type="password" class="form-input pr-12" autocomplete="current-password" required data-label="Password">
                        <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 touch-target text-slate-400 hover:text-slate-600" aria-label="Show password">
                            <?= icon('eye', 'w-5 h-5') ?>
                        </button>
                    </div>
                </div>
                <button type="submit" id="login-submit" class="btn btn-primary w-full">Sign in</button>
            </form>

            <p class="mt-8 text-xs text-slate-400 text-center">Authorized personnel only. All access is logged.</p>
        </div>
    </main>
</div>

<div id="toast-container" class="fixed z-[60] top-4 right-4 left-4 sm:left-auto sm:w-96 flex flex-col gap-2 pointer-events-none" aria-live="polite"></div>

<script>
    window.SM_CONFIG = <?= json_encode(['baseUrl' => BASE_URL, 'csrfToken' => csrf_token(), 'sessionTimeout' => SESSION_TIMEOUT, 'user' => null], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.SM_ICONS = <?= json_encode(SM_ICONS, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= e(url('assets/js/main.js')) ?>?v=<?= e(APP_VERSION) ?>"></script>
<script>
    (function () {
        const form = document.getElementById('login-form');
        const alertBox = document.getElementById('login-alert');
        const submit = document.getElementById('login-submit');
        const pwd = document.getElementById('password');

        SM.form.enableLiveValidation(form);

        document.getElementById('toggle-password').addEventListener('click', function () {
            const show = pwd.type === 'password';
            pwd.type = show ? 'text' : 'password';
            this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            alertBox.classList.add('hidden');
            if (!SM.form.validate(form)) return;

            SM.setLoading(submit, true, 'Signing in…');
            try {
                const json = await SM.api('api/auth/login.php', { method: 'POST', data: SM.form.serialize(form) });
                SM.toast(json.message, 'success');
                window.location.href = json.data.redirect;
            } catch (err) {
                SM.setLoading(submit, false);
                if (err.errors && Object.keys(err.errors).length) SM.form.showErrors(form, err.errors);
                alertBox.textContent = err.message;
                alertBox.classList.remove('hidden');
                if (err.payload && err.payload.code === 'csrf_invalid') setTimeout(() => window.location.reload(), 1500);
                pwd.value = '';
                pwd.focus();
            }
        });
    })();
</script>
</body>
</html>
