<?php
/**
 * SafeMatch - Page footer template. Closes the layout opened in header.php
 * and loads main.js plus the page's own scripts ($pageScripts).
 */
?>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 text-xs text-slate-500 flex flex-col sm:flex-row gap-1 sm:justify-between">
            <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> · <?= e(APP_TAGLINE) ?></span>
            <span>Every discharge verified. Every baby home with the right mother.</span>
        </div>
    </footer>
</div>

<!-- Toasts -->
<div id="toast-container" class="fixed z-[60] top-4 right-4 left-4 sm:left-auto sm:w-96 flex flex-col gap-2 pointer-events-none" aria-live="polite" aria-atomic="false"></div>

<!-- Generic confirmation dialog (SM.confirm) -->
<div id="confirm-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel max-w-md">
        <div class="p-6">
            <div class="flex gap-4">
                <span id="confirm-icon" class="shrink-0 w-11 h-11 rounded-full bg-red-100 text-red-600 flex items-center justify-center"><?= icon('warning', 'w-6 h-6') ?></span>
                <div>
                    <h2 id="confirm-title" class="text-lg font-semibold text-slate-900">Are you sure?</h2>
                    <p id="confirm-message" class="mt-1 text-sm text-slate-600"></p>
                </div>
            </div>
        </div>
        <div class="px-6 py-4 bg-slate-50 rounded-b-2xl flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <button type="button" class="btn btn-secondary" data-confirm-cancel>Cancel</button>
            <button type="button" class="btn btn-danger" data-confirm-ok>Confirm</button>
        </div>
    </div>
</div>

<script src="<?= e(asset('assets/js/main.js')) ?>"></script>
<?php foreach (($pageScripts ?? []) as $script): ?>
<script src="<?= e(asset('assets/js/' . $script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
