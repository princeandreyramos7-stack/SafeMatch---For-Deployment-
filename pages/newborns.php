<?php
/**
 * SafeMatch - Newborn management (list, register, edit, view, QR codes, delete).
 */
$pageTitle = 'Newborns';
$activePage = 'newborns';
$pageScripts = ['newborns.js'];
require __DIR__ . '/../templates/header.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
    <p class="text-sm text-slate-500">Each newborn is linked to the mother, and matching QR bands are generated automatically.</p>
    <button type="button" id="btn-add-newborn" class="btn btn-primary shrink-0"><?= icon('plus', 'w-4 h-4') ?> Register Newborn</button>
</div>

<div class="card">
    <form id="newborn-filters" class="p-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6 border-b border-slate-100" role="search">
        <div class="relative sm:col-span-2">
            <label for="f-q" class="sr-only">Search newborns</label>
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none"><?= icon('search', 'w-4 h-4') ?></span>
            <input id="f-q" name="q" type="search" class="form-input pl-9" placeholder="Search baby, mother or ID…" autocomplete="off">
        </div>
        <select name="sex" class="form-input" aria-label="Sex">
            <option value="">All sexes</option><option>Male</option><option>Female</option>
        </select>
        <select name="delivery_type" class="form-input" aria-label="Delivery type">
            <option value="">All deliveries</option><option>Normal</option><option>Cesarean</option>
        </select>
        <select name="status" class="form-input" aria-label="Discharge status">
            <option value="">Any discharge status</option>
            <option value="not_ready">Not ready</option>
            <option value="ready">Ready</option>
            <option value="discharged">Discharged</option>
        </select>
        <div class="flex gap-2">
            <input name="date_from" type="date" class="form-input" aria-label="Born from" title="Born from">
            <button type="reset" class="btn btn-ghost shrink-0" aria-label="Clear filters" title="Clear filters"><?= icon('x', 'w-4 h-4') ?></button>
        </div>
        <input type="hidden" name="mother_id">
    </form>

    <div class="table-wrap">
        <table class="data-table" id="newborns-table">
            <thead>
            <tr>
                <th data-sort="newborn_id">Newborn ID</th>
                <th data-sort="name">Name</th>
                <th data-sort="mother">Mother</th>
                <th data-sort="date_of_birth" data-default-dir="desc">Born</th>
                <th data-sort="weight">Weight</th>
                <th>Delivery</th>
                <th>Discharge</th>
                <th class="text-right">Actions</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div id="newborns-pagination"></div>
</div>

<!-- Add / edit modal -->
<div id="newborn-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="newborn-modal-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel max-w-3xl">
        <form id="newborn-form">
            <div class="modal-header">
                <h2 id="newborn-modal-title" class="text-lg font-semibold">Register Newborn</h2>
                <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button>
            </div>
            <div class="modal-body space-y-6">
                <input type="hidden" name="id" data-reset>

                <fieldset>
                    <legend class="text-sm font-semibold text-slate-900 mb-3">Mother</legend>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label for="nb-mother-search" class="form-label">Find mother</label>
                            <input id="nb-mother-search" type="search" class="form-input" placeholder="Type name or ID…" autocomplete="off">
                        </div>
                        <div data-field class="sm:col-span-2">
                            <label for="nb-mother" class="form-label">Mother <span class="req">*</span></label>
                            <select id="nb-mother" name="mother_id" class="form-input" required data-label="Mother">
                                <option value="">Select mother…</option>
                            </select>
                            <p class="form-hint">Only mothers who are still admitted are listed.</p>
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="text-sm font-semibold text-slate-900 mb-3">Baby</legend>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div data-field><label for="nb-first" class="form-label">First name</label><input id="nb-first" name="first_name" class="form-input" maxlength="255" placeholder="Optional"></div>
                        <div data-field><label for="nb-middle" class="form-label">Middle name</label><input id="nb-middle" name="middle_name" class="form-input" maxlength="255"></div>
                        <div data-field><label for="nb-last" class="form-label">Last name <span class="req">*</span></label><input id="nb-last" name="last_name" class="form-input" required maxlength="255"></div>
                        <div data-field><label for="nb-dob" class="form-label">Date of birth <span class="req">*</span></label><input id="nb-dob" name="date_of_birth" type="date" class="form-input" required data-not-future></div>
                        <div data-field><label for="nb-tob" class="form-label">Time of birth <span class="req">*</span></label><input id="nb-tob" name="time_of_birth" type="time" class="form-input" required></div>
                        <div data-field>
                            <label for="nb-sex" class="form-label">Sex <span class="req">*</span></label>
                            <select id="nb-sex" name="sex" class="form-input" required><option value="">Select…</option><option>Male</option><option>Female</option></select>
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="text-sm font-semibold text-slate-900 mb-3">Birth measurements</legend>
                    <div class="grid gap-4 grid-cols-2 sm:grid-cols-5">
                        <div data-field><label for="nb-weight" class="form-label">Weight (kg) <span class="req">*</span></label><input id="nb-weight" name="weight" type="number" step="0.01" min="0.3" max="7" inputmode="decimal" class="form-input" required></div>
                        <div data-field><label for="nb-length" class="form-label">Length (cm) <span class="req">*</span></label><input id="nb-length" name="length" type="number" step="0.1" min="20" max="70" inputmode="decimal" class="form-input" required></div>
                        <div data-field><label for="nb-head" class="form-label">Head circ. (cm)</label><input id="nb-head" name="head_circumference" type="number" step="0.1" min="20" max="50" inputmode="decimal" class="form-input" data-label="Head circumference"></div>
                        <div data-field><label for="nb-ap1" class="form-label">APGAR 1 min</label><input id="nb-ap1" name="apgar_score_1min" type="number" min="0" max="10" step="1" inputmode="numeric" class="form-input" data-label="APGAR (1 min)"></div>
                        <div data-field><label for="nb-ap5" class="form-label">APGAR 5 min</label><input id="nb-ap5" name="apgar_score_5min" type="number" min="0" max="10" step="1" inputmode="numeric" class="form-input" data-label="APGAR (5 min)"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="text-sm font-semibold text-slate-900 mb-3">Delivery</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div data-field>
                            <label for="nb-delivery" class="form-label">Delivery type <span class="req">*</span></label>
                            <select id="nb-delivery" name="delivery_type" class="form-input" required><option value="">Select…</option><option>Normal</option><option>Cesarean</option></select>
                        </div>
                        <div data-field><label for="nb-ped" class="form-label">Pediatrician</label><input id="nb-ped" name="pediatrician" class="form-input" maxlength="255"></div>
                        <div data-field class="sm:col-span-2"><label for="nb-comp" class="form-label">Birth complications</label><textarea id="nb-comp" name="birth_complications" class="form-input" rows="2" maxlength="5000" placeholder="None"></textarea></div>
                    </div>
                </fieldset>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="newborn-submit">Register Newborn</button>
            </div>
        </form>
    </div>
</div>

<!-- View modal -->
<div id="newborn-view-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="newborn-view-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel max-w-3xl">
        <div class="modal-header">
            <div>
                <h2 id="newborn-view-title" class="text-lg font-semibold">Newborn details</h2>
                <p id="newborn-view-sub" class="text-xs text-slate-500"></p>
            </div>
            <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button>
        </div>
        <div class="modal-body" id="newborn-view-body"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
            <button type="button" class="btn btn-secondary" id="newborn-view-qr"><?= icon('qr', 'w-4 h-4') ?> QR Codes</button>
            <button type="button" class="btn btn-primary" id="newborn-view-edit"><?= icon('pencil', 'w-4 h-4') ?> Edit</button>
        </div>
    </div>
</div>

<!-- QR modal -->
<div id="qr-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="qr-modal-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel max-w-2xl">
        <div class="modal-header">
            <div>
                <h2 id="qr-modal-title" class="text-lg font-semibold">QR Identification Bands</h2>
                <p id="qr-modal-sub" class="text-xs text-slate-500"></p>
            </div>
            <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button>
        </div>
        <div class="modal-body" id="qr-modal-body"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
            <button type="button" class="btn btn-secondary" id="qr-regenerate"><?= icon('refresh', 'w-4 h-4') ?> Regenerate</button>
            <a href="#" target="_blank" rel="noopener" class="btn btn-primary" id="qr-print"><?= icon('printer', 'w-4 h-4') ?> Print Bands</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
