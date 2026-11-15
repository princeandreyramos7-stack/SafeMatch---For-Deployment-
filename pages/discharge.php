<?php
/**
 * SafeMatch - Discharge management: readiness overview, checklist, final discharge.
 */
$pageTitle = 'Discharge';
$activePage = 'discharge';
$pageScripts = ['discharge.js'];
require __DIR__ . '/../templates/header.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
    <p class="text-sm text-slate-500">Discharge requires every mandatory requirement to be met <strong class="font-semibold text-slate-700">and</strong> a matching QR verification.</p>
    <?php if (is_admin()): ?>
        <button type="button" id="btn-requirements" class="btn btn-secondary shrink-0"><?= icon('settings', 'w-4 h-4') ?> Requirements</button>
    <?php endif; ?>
</div>

<!-- Traffic light filter chips -->
<div class="flex flex-wrap gap-2 mb-4" role="group" aria-label="Filter by readiness">
    <?php foreach (['' => 'All open', 'green' => 'Ready', 'yellow' => 'In progress', 'red' => 'Not ready'] as $k => $label): ?>
        <button type="button" class="btn btn-secondary btn-sm" data-light-chip="<?= e($k) ?>">
            <?php if ($k): ?><span class="light-dot light-<?= e($k) ?>" aria-hidden="true"></span><?php endif; ?><?= e($label) ?>
        </button>
    <?php endforeach; ?>
    <button type="button" class="btn btn-secondary btn-sm" data-status-chip="discharged"><span class="light-dot light-discharged" aria-hidden="true"></span>Discharged</button>
</div>

<div class="card">
    <form id="discharge-filters" class="p-4 grid gap-3 sm:grid-cols-3 border-b border-slate-100" role="search">
        <div class="relative sm:col-span-2">
            <label for="f-q" class="sr-only">Search</label>
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none"><?= icon('search', 'w-4 h-4') ?></span>
            <input id="f-q" name="q" type="search" class="form-input pl-9" placeholder="Search baby, mother, ID or room…" autocomplete="off">
        </div>
        <select name="status" id="f-status" class="form-input" aria-label="Status">
            <option value="open">Open (not discharged)</option>
            <option value="">All</option>
            <option value="not_ready">Not ready</option>
            <option value="ready">Ready</option>
            <option value="discharged">Discharged</option>
        </select>
        <input type="hidden" name="light" id="f-light">
    </form>

    <div class="table-wrap">
        <table class="data-table" id="discharge-table">
            <thead>
            <tr>
                <th data-sort="newborn">Baby</th>
                <th data-sort="mother">Mother</th>
                <th data-sort="percent">Readiness</th>
                <th>QR check</th>
                <th data-sort="status">Status</th>
                <th data-sort="birth" data-default-dir="desc">Born</th>
                <th class="text-right">Actions</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div id="discharge-pagination"></div>
</div>

<!-- Checklist modal -->
<div id="checklist-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="checklist-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel max-w-4xl">
        <div class="modal-header">
            <div class="min-w-0">
                <h2 id="checklist-title" class="text-lg font-semibold truncate">Discharge checklist</h2>
                <p id="checklist-sub" class="text-xs text-slate-500 truncate"></p>
            </div>
            <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button>
        </div>
        <div class="modal-body" id="checklist-body"></div>
        <div class="modal-footer" id="checklist-footer"></div>
    </div>
</div>

<?php if (is_admin()): ?>
<!-- Requirements admin modal -->
<div id="requirements-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="req-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel max-w-3xl">
        <div class="modal-header">
            <h2 id="req-title" class="text-lg font-semibold">Discharge requirements</h2>
            <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button>
        </div>
        <div class="modal-body">
            <div id="requirements-list" class="space-y-5 mb-6"></div>
            <form id="requirement-form" class="rounded-xl border border-slate-200 p-4 grid gap-3 sm:grid-cols-2">
                <input type="hidden" name="id" data-reset>
                <p class="sm:col-span-2 text-sm font-semibold" id="req-form-title">Add a requirement</p>
                <div data-field class="sm:col-span-2"><label for="req-name" class="form-label">Name <span class="req">*</span></label><input id="req-name" name="name" class="form-input" required maxlength="255"></div>
                <div data-field class="sm:col-span-2"><label for="req-desc" class="form-label">Description</label><input id="req-desc" name="description" class="form-input" maxlength="2000"></div>
                <div data-field>
                    <label for="req-cat" class="form-label">Applies to <span class="req">*</span></label>
                    <select id="req-cat" name="category" class="form-input" required>
                        <option value="mother">Mother</option><option value="newborn">Newborn</option><option value="both">Both</option>
                    </select>
                </div>
                <div class="flex items-end gap-4 pb-2">
                    <label class="inline-flex items-center gap-2 text-sm min-h-[44px]"><input type="checkbox" name="is_mandatory" class="w-5 h-5 accent-teal-600" checked> Mandatory</label>
                    <label class="inline-flex items-center gap-2 text-sm min-h-[44px]"><input type="checkbox" name="is_active" class="w-5 h-5 accent-teal-600" checked> Active</label>
                </div>
                <div class="sm:col-span-2 flex justify-end gap-2">
                    <button type="reset" class="btn btn-secondary btn-sm">Clear</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="req-submit">Save requirement</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
