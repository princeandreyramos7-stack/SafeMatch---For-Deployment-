<?php
/**
 * SafeMatch - User management (administrators only).
 */
$pageTitle = 'Users';
$activePage = 'users';
$adminOnly = true;
$pageScripts = ['users.js'];
require __DIR__ . '/../templates/header.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
    <p class="text-sm text-slate-500">Manage staff accounts. Deactivated users cannot sign in; their history is kept for audit.</p>
    <button type="button" id="btn-add-user" class="btn btn-primary shrink-0"><?= icon('plus', 'w-4 h-4') ?> Add User</button>
</div>

<div class="card">
    <form id="user-filters" class="p-4 grid gap-3 sm:grid-cols-4 border-b border-slate-100" role="search">
        <div class="relative sm:col-span-2">
            <label for="f-q" class="sr-only">Search users</label>
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none"><?= icon('search', 'w-4 h-4') ?></span>
            <input id="f-q" name="q" type="search" class="form-input pl-9" placeholder="Search name, email, position…" autocomplete="off">
        </div>
        <select name="role" class="form-input" aria-label="Role">
            <option value="">All roles</option>
            <option value="admin">Administrator</option>
            <option value="healthcare_personnel">Healthcare Personnel</option>
        </select>
        <select name="status" class="form-input" aria-label="Status">
            <option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option>
        </select>
    </form>
    <div class="table-wrap">
        <table class="data-table" id="users-table">
            <thead>
            <tr>
                <th data-sort="name">User</th>
                <th data-sort="role">Role</th>
                <th data-sort="position">Position</th>
                <th>Contact</th>
                <th>Last login</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div id="users-pagination"></div>
</div>

<!-- Add / edit user -->
<div id="user-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="user-modal-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel max-w-xl">
        <form id="user-form">
            <div class="modal-header">
                <h2 id="user-modal-title" class="text-lg font-semibold">Add User</h2>
                <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button>
            </div>
            <div class="modal-body grid gap-4 sm:grid-cols-2">
                <input type="hidden" name="id" data-reset>
                <input type="hidden" name="action" value="update">
                <div data-field class="sm:col-span-2"><label for="u-name" class="form-label">Full name <span class="req">*</span></label><input id="u-name" name="name" class="form-input" required maxlength="255"></div>
                <div data-field class="sm:col-span-2"><label for="u-email" class="form-label">Email <span class="req">*</span></label><input id="u-email" name="email" type="email" class="form-input" required maxlength="255" autocomplete="off"></div>
                <div data-field>
                    <label for="u-role" class="form-label">Role <span class="req">*</span></label>
                    <select id="u-role" name="role" class="form-input" required>
                        <option value="healthcare_personnel">Healthcare Personnel</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div data-field><label for="u-position" class="form-label">Position <span class="req">*</span></label><input id="u-position" name="position" class="form-input" required maxlength="255" placeholder="e.g. Midwife, Staff Nurse"></div>
                <div data-field class="sm:col-span-2"><label for="u-contact" class="form-label">Contact number <span class="req">*</span></label><input id="u-contact" name="contact_number" type="tel" inputmode="numeric" class="form-input" required data-phone placeholder="09XXXXXXXXX"></div>
                <div data-field data-create-only><label for="u-pass" class="form-label">Password <span class="req">*</span></label><input id="u-pass" name="password" type="password" class="form-input" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password" required></div>
                <div data-field data-create-only><label for="u-pass2" class="form-label">Confirm password <span class="req">*</span></label><input id="u-pass2" name="password_confirmation" type="password" class="form-input" data-match="password" autocomplete="new-password" required data-label="Password confirmation"></div>
                <p class="sm:col-span-2 form-hint" data-create-only>At least <?= PASSWORD_MIN_LENGTH ?> characters. Ask the user to change it after their first sign-in.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="user-submit">Save User</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset password -->
<div id="reset-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="reset-title">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel max-w-md">
        <form id="reset-form">
            <div class="modal-header">
                <div><h2 id="reset-title" class="text-lg font-semibold">Reset password</h2><p id="reset-sub" class="text-xs text-slate-500"></p></div>
                <button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x') ?></button>
            </div>
            <div class="modal-body space-y-4">
                <input type="hidden" name="id">
                <input type="hidden" name="action" value="reset_password">
                <div data-field><label for="r-pass" class="form-label">New password <span class="req">*</span></label><input id="r-pass" name="password" type="password" class="form-input" required minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password"></div>
                <div data-field><label for="r-pass2" class="form-label">Confirm new password <span class="req">*</span></label><input id="r-pass2" name="password_confirmation" type="password" class="form-input" required data-match="password" autocomplete="new-password" data-label="Password confirmation"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary" id="reset-submit">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
