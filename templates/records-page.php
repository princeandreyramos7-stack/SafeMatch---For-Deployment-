<?php
/**
 * SafeMatch - Shared page body for clinical records (newborn screenings and
 * maternal assessments). The including page sets $records:
 *   noun, nounPlural, subjectLabel, subjectPlaceholder, results (array),
 *   intro, dateLabel
 */
$r = $records;
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
    <p class="text-sm text-slate-500"><?= e($r['intro']) ?></p>
    <div class="flex gap-2 shrink-0">
        <?php if (is_admin()): ?>
            <button type="button" id="btn-manage-types" class="btn btn-secondary"><?= icon('settings', 'w-4 h-4') ?> Types</button>
        <?php endif; ?>
        <button type="button" id="btn-add-record" class="btn btn-primary"><?= icon('plus', 'w-4 h-4') ?> Record <?= e($r['noun']) ?></button>
    </div>
</div>

<!-- Summary by result (for current filters) -->
<section class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4" aria-label="Summary">
    <div class="card p-4"><p class="text-xs text-slate-500">Total</p><p class="text-2xl font-bold tabular-nums" data-summary="total">–</p></div>
    <?php
    $tones = ['Normal' => 'text-emerald-700', 'Abnormal' => 'text-red-700', 'Pending' => 'text-amber-700', 'Needs Attention' => 'text-amber-700'];
    foreach ($r['results'] as $res): ?>
        <button type="button" class="card p-4 text-left hover:border-primary-300" data-result-filter="<?= e($res) ?>">
            <p class="text-xs text-slate-500"><?= e($res) ?></p>
            <p class="text-2xl font-bold tabular-nums <?= $tones[$res] ?? '' ?>" data-summary="<?= e($res) ?>">–</p>
        </button>
    <?php endforeach; ?>
</section>

<div class="card">
    <form id="record-filters" class="p-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6 border-b border-slate-100" role="search">
        <div class="relative sm:col-span-2">
            <label for="f-q" class="sr-only">Search</label>
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none"><?= icon('search', 'w-4 h-4') ?></span>
            <input id="f-q" name="q" type="search" class="form-input pl-9" placeholder="<?= e($r['subjectPlaceholder']) ?>" autocomplete="off">
        </div>
        <select name="type_id" id="f-type" class="form-input" aria-label="Type"><option value="">All types</option></select>
        <select name="result" id="f-result" class="form-input" aria-label="Result">
            <option value="">All results</option>
            <?php foreach ($r['results'] as $res): ?><option><?= e($res) ?></option><?php endforeach; ?>
        </select>
        <input name="date_from" type="date" class="form-input" aria-label="From date" title="From">
        <div class="flex gap-2">
            <input name="date_to" type="date" class="form-input" aria-label="To date" title="To">
            <button type="reset" class="btn btn-ghost shrink-0" aria-label="Clear filters" title="Clear filters"><?= icon('x', 'w-4 h-4') ?></button>
        </div>
        <input type="hidden" name="<?= e($r['subjectParam']) ?>">
    </form>

    <div class="table-wrap">
        <table class="data-table" id="records-table">
            <thead>
            <tr>
                <th data-sort="<?= e($r['dateSort']) ?>" data-default-dir="desc">Date / time</th>
                <th data-sort="<?= e($r['subjectSort']) ?>"><?= e($r['subjectLabel']) ?></th>
                <th data-sort="type">Type</th>
                <th data-sort="result">Result</th>
                <th>Notes</th>
                <th>Recorded by</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div id="records-pagination"></div>
</div>

<!-- Create modal -->
<div id="record-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="record-modal-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel max-w-xl">
        <form id="record-form">
            <div class="modal-header">
                <h2 id="record-modal-title" class="text-lg font-semibold">Record <?= e($r['noun']) ?></h2>
                <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button>
            </div>
            <div class="modal-body space-y-4">
                <div>
                    <label for="rec-subject-search" class="form-label">Find <?= e(strtolower($r['subjectLabel'])) ?></label>
                    <input id="rec-subject-search" type="search" class="form-input" placeholder="Type name or ID…" autocomplete="off">
                </div>
                <div data-field>
                    <label for="rec-subject" class="form-label"><?= e($r['subjectLabel']) ?> <span class="req">*</span></label>
                    <select id="rec-subject" name="<?= e($r['subjectField']) ?>" class="form-input" required data-label="<?= e($r['subjectLabel']) ?>"><option value="">Select…</option></select>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div data-field>
                        <label for="rec-type" class="form-label"><?= e($r['noun']) ?> type <span class="req">*</span></label>
                        <select id="rec-type" name="<?= e($r['typeField']) ?>" class="form-input" required data-label="Type"><option value="">Select…</option></select>
                    </div>
                    <div data-field>
                        <label for="rec-date" class="form-label"><?= e($r['dateLabel']) ?> <span class="req">*</span></label>
                        <input id="rec-date" name="<?= e($r['dateField']) ?>" type="datetime-local" class="form-input" required data-not-future data-label="Date">
                    </div>
                </div>
                <fieldset data-field>
                    <legend class="form-label">Result <span class="req">*</span></legend>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <?php foreach ($r['results'] as $i => $res): ?>
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 min-h-[44px] cursor-pointer has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                                <input type="radio" name="result" value="<?= e($res) ?>" class="accent-teal-600 w-4 h-4" <?= $i === 0 ? 'required' : '' ?> data-label="Result">
                                <span class="text-sm font-medium"><?= e($res) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <div data-field>
                    <label for="rec-notes" class="form-label">Notes</label>
                    <textarea id="rec-notes" name="notes" class="form-input" rows="3" maxlength="5000" placeholder="Findings, follow-up actions…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="record-submit">Save <?= e($r['noun']) ?></button>
            </div>
        </form>
    </div>
</div>

<?php if (is_admin()): ?>
<!-- Manage types modal (admin) -->
<div id="types-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="types-modal-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel max-w-xl">
        <div class="modal-header">
            <h2 id="types-modal-title" class="text-lg font-semibold"><?= e($r['noun']) ?> types</h2>
            <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button>
        </div>
        <div class="modal-body">
            <ul id="types-list" class="divide-y divide-slate-100 mb-5"></ul>
            <form id="type-form" class="rounded-xl border border-slate-200 p-4 space-y-3">
                <input type="hidden" name="id" data-reset>
                <p class="text-sm font-semibold" id="type-form-title">Add a type</p>
                <div data-field><label for="type-name" class="form-label">Name <span class="req">*</span></label><input id="type-name" name="name" class="form-input" required maxlength="255"></div>
                <div data-field><label for="type-desc" class="form-label">Description</label><input id="type-desc" name="description" class="form-input" maxlength="2000"></div>
                <div class="flex gap-2 justify-end">
                    <button type="reset" class="btn btn-secondary btn-sm" id="type-cancel">Clear</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="type-submit">Save type</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
