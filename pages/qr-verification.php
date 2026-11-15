<?php
/**
 * SafeMatch - Two-step QR verification of a mother-baby pair (verification only).
 */
$pageTitle = 'QR Verification';
$activePage = 'qr';
$pageScripts = ['qr-scanner.js'];
$extraHead = '<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>';
require __DIR__ . '/../templates/header.php';
?>
<div class="max-w-2xl mx-auto">
    <div class="text-center mb-5">
        <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Verify mother &amp; baby</h2>
        <p class="text-sm text-slate-500 mt-1">Scan the mother's band, then the baby's. The result appears automatically.</p>
    </div>

    <!-- The two bands: doubles as the step indicator -->
    <div id="bands" class="grid grid-cols-2 gap-3 mb-4" aria-live="polite"></div>

    <!-- Scan panel -->
    <section id="scan-panel" class="card overflow-hidden" aria-labelledby="scan-title">
        <div class="px-4 sm:px-5 pt-4 pb-3 flex items-center gap-3">
            <span id="scan-icon" class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center"></span>
            <div class="min-w-0">
                <h3 id="scan-title" class="font-semibold text-slate-900"></h3>
                <p id="scan-help" class="text-sm text-slate-500"></p>
            </div>
        </div>

        <div class="relative bg-slate-900 aspect-square sm:aspect-[4/3]">
            <div id="qr-reader" class="absolute inset-0 w-full h-full [&_video]:w-full [&_video]:h-full [&_video]:object-cover"></div>
            <div id="camera-placeholder" class="absolute inset-0 flex flex-col items-center justify-center gap-4 p-6 text-center text-slate-300">
                <span class="w-16 h-16 rounded-full bg-white/10 flex items-center justify-center"><?= icon('qr', 'w-9 h-9 text-white/80') ?></span>
                <p class="text-sm">Point the camera at the QR code on the band</p>
                <button type="button" id="btn-start-camera" class="btn btn-primary"><?= icon('camera', 'w-4 h-4') ?> Start camera</button>
            </div>
            <div id="camera-live" class="hidden absolute bottom-3 inset-x-3 flex justify-between gap-2">
                <span class="inline-flex items-center gap-2 rounded-full bg-black/60 text-white text-xs font-medium px-3 py-1.5"><span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Scanning…</span>
                <button type="button" id="btn-stop-camera" class="rounded-full bg-black/60 text-white text-xs font-medium px-3 py-1.5 hover:bg-black/75">Stop</button>
            </div>
        </div>

        <div class="p-4 sm:p-5 space-y-3">
            <div class="grid grid-cols-2 gap-2">
                <label class="btn btn-secondary cursor-pointer">
                    <?= icon('upload', 'w-4 h-4') ?> Scan a photo
                    <input type="file" id="qr-file" accept="image/*" capture="environment" class="sr-only">
                </label>
                <button type="button" id="btn-manual-toggle" class="btn btn-secondary" aria-expanded="false" aria-controls="manual-form"><?= icon('pencil', 'w-4 h-4') ?> Type the ID</button>
            </div>
            <select id="camera-select" class="form-input hidden" aria-label="Choose camera"></select>

            <form id="manual-form" class="hidden rounded-xl bg-slate-50 border border-slate-200 p-3">
                <label for="manual-code" class="form-label">Band ID or pasted QR text</label>
                <div class="flex flex-col sm:flex-row gap-2">
                    <input id="manual-code" class="form-input" placeholder="e.g. M<?= date('Y') ?>001" autocomplete="off" autocapitalize="characters">
                    <button type="submit" class="btn btn-primary shrink-0">Use ID</button>
                </div>
                <p class="form-hint">Typed IDs skip the band's signature check and are flagged in the log. Confirm identity visually.</p>
            </form>
        </div>
    </section>

    <!-- Result panel -->
    <section id="result-panel" class="hidden" aria-live="assertive"></section>

    <div class="text-center mt-4" id="start-over-wrap">
        <button type="button" id="btn-reset" class="text-sm text-slate-500 hover:text-slate-800 underline underline-offset-2 hidden">Start over</button>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
