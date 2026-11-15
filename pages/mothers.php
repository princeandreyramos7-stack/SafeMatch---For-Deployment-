<?php
/**
 * SafeMatch - Mother management (list, register, edit, view, delete).
 */
$pageTitle = 'Mothers';
$activePage = 'mothers';
$pageScripts = ['mothers.js'];
require __DIR__ . '/../templates/header.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
    <p class="text-sm text-slate-500">Register and manage admitted mothers. A Mother ID (e.g. M<?= date('Y') ?>001) is assigned automatically.</p>
    <button type="button" id="btn-add-mother" class="btn btn-primary shrink-0"><?= icon('plus', 'w-4 h-4') ?> Register Mother</button>
</div>

<div class="card">
    <form id="mother-filters" class="p-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5 border-b border-slate-100" role="search">
        <div class="relative sm:col-span-2">
            <label for="f-q" class="sr-only">Search mothers</label>
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none"><?= icon('search', 'w-4 h-4') ?></span>
            <input id="f-q" name="q" type="search" class="form-input pl-9" placeholder="Search name, ID, contact or room…" autocomplete="off">
        </div>
        <div>
            <label for="f-status" class="sr-only">Status</label>
            <select id="f-status" name="status" class="form-input">
                <option value="">All statuses</option>
                <option value="admitted">Currently admitted</option>
                <option value="discharged">Discharged</option>
            </select>
        </div>
        <div>
            <label for="f-from" class="sr-only">Admitted from</label>
            <input id="f-from" name="date_from" type="date" class="form-input" title="Admitted from">
        </div>
        <div class="flex gap-2">
            <label for="f-to" class="sr-only">Admitted to</label>
            <input id="f-to" name="date_to" type="date" class="form-input" title="Admitted to">
            <button type="reset" class="btn btn-ghost shrink-0" title="Clear filters" aria-label="Clear filters"><?= icon('x', 'w-4 h-4') ?></button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table" id="mothers-table">
            <thead>
            <tr>
                <th data-sort="mother_id">Mother ID</th>
                <th data-sort="name">Name</th>
                <th>Contact</th>
                <th data-sort="admission_date" data-default-dir="desc">Admitted</th>
                <th data-sort="room_number">Room</th>
                <th>Babies</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div id="mothers-pagination"></div>
</div>

<!-- Add / edit modal -->
<div id="mother-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="mother-modal-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel max-w-3xl">
        <form id="mother-form">
            <div class="modal-header">
                <h2 id="mother-modal-title" class="text-lg font-semibold">Register Mother</h2>
                <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button>
            </div>
            <div class="modal-body space-y-6">
                <input type="hidden" name="id" data-reset>

                <fieldset>
                    <legend class="text-sm font-semibold text-slate-900 mb-3">Personal information</legend>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div data-field><label for="m-first" class="form-label">First name <span class="req">*</span></label><input id="m-first" name="first_name" class="form-input" required maxlength="255"></div>
                        <div data-field><label for="m-middle" class="form-label">Middle name</label><input id="m-middle" name="middle_name" class="form-input" maxlength="255"></div>
                        <div data-field><label for="m-last" class="form-label">Last name <span class="req">*</span></label><input id="m-last" name="last_name" class="form-input" required maxlength="255"></div>
                        <div data-field><label for="m-dob" class="form-label">Date of birth <span class="req">*</span></label><input id="m-dob" name="date_of_birth" type="date" class="form-input" required data-not-future></div>
                        <div data-field><label for="m-contact" class="form-label">Contact number <span class="req">*</span></label><input id="m-contact" name="contact_number" type="tel" inputmode="numeric" class="form-input" required data-phone placeholder="09XXXXXXXXX"></div>
                        <div data-field>
                            <label for="m-blood" class="form-label">Blood type</label>
                            <select id="m-blood" name="blood_type" class="form-input">
                                <option value="">Unknown</option>
                                <?php foreach (BLOOD_TYPES as $bt): ?><option value="<?= e($bt) ?>"><?= e($bt) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div data-field class="sm:col-span-3"><label for="m-address" class="form-label">Address <span class="req">*</span></label><textarea id="m-address" name="address" class="form-input !min-h-[64px]" rows="2" required maxlength="1000"></textarea></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="text-sm font-semibold text-slate-900 mb-3">Emergency contact</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div data-field><label for="m-ec" class="form-label">Contact person</label><input id="m-ec" name="emergency_contact" class="form-input" maxlength="255"></div>
                        <div data-field><label for="m-ecn" class="form-label">Contact number</label><input id="m-ecn" name="emergency_contact_number" type="tel" inputmode="numeric" class="form-input" data-phone data-label="Emergency contact number" placeholder="09XXXXXXXXX"></div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="text-sm font-semibold text-slate-900 mb-3">Admission</legend>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div data-field><label for="m-adm" class="form-label">Admission date &amp; time <span class="req">*</span></label><input id="m-adm" name="admission_date" type="datetime-local" class="form-input" required data-not-future></div>
                        <div data-field><label for="m-room" class="form-label">Room number</label><input id="m-room" name="room_number" class="form-input" maxlength="50"></div>
                        <div data-field><label for="m-phys" class="form-label">Attending physician</label><input id="m-phys" name="attending_physician" class="form-input" maxlength="255"></div>
                        <div data-field class="sm:col-span-3 grid gap-4 sm:grid-cols-2">
                            <div data-field><label for="m-hist" class="form-label">Medical history</label><textarea id="m-hist" name="medical_history" class="form-input" rows="3" maxlength="5000"></textarea></div>
                            <div data-field><label for="m-allergy" class="form-label">Allergies</label><textarea id="m-allergy" name="allergies" class="form-input" rows="3" maxlength="2000" placeholder="None known"></textarea></div>
                        </div>
                    </div>
                </fieldset>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="mother-submit">Save Mother</button>
            </div>
        </form>
    </div>
</div>

<!-- View modal -->
<div id="mother-view-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="mother-view-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel max-w-3xl">
        <div class="modal-header">
            <div>
                <h2 id="mother-view-title" class="text-lg font-semibold">Mother details</h2>
                <p id="mother-view-sub" class="text-xs text-slate-500"></p>
            </div>
            <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button>
        </div>
        <div class="modal-body" id="mother-view-body"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
            <a href="#" class="btn btn-secondary" id="mother-view-add-baby"><?= icon('plus', 'w-4 h-4') ?> Add Newborn</a>
            <button type="button" class="btn btn-primary" id="mother-view-edit"><?= icon('pencil', 'w-4 h-4') ?> Edit</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
