<?php
/**
 * SafeMatch - My profile: details, profile picture and password.
 */
$pageTitle = 'My Profile';
$activePage = '';
$pageScripts = ['profile.js'];
require __DIR__ . '/../templates/header.php';
?>
<div class="grid gap-5 lg:grid-cols-3 max-w-5xl">
    <!-- Profile -->
    <section class="card lg:col-span-2" aria-labelledby="profile-heading">
        <form id="profile-form" enctype="multipart/form-data">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 id="profile-heading" class="font-semibold text-slate-900">Profile information</h2>
                <p class="text-xs text-slate-500">Signed in as <?= e($user['email']) ?> · <?= $user['role'] === ROLE_ADMIN ? 'Administrator' : 'Healthcare Personnel' ?></p>
            </div>
            <div class="p-5 space-y-5">
                <input type="hidden" name="action" value="profile">
                <input type="hidden" name="remove_picture" value="">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <div id="avatar-preview" class="w-20 h-20 rounded-full overflow-hidden bg-primary-100 text-primary-700 font-bold text-2xl flex items-center justify-center shrink-0">
                        <?php if ($avatarUrl): ?>
                            <img src="<?= e($avatarUrl) ?>" alt="Profile picture" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?= e($initials) ?>
                        <?php endif; ?>
                    </div>
                    <div data-field>
                        <div class="flex flex-wrap gap-2">
                            <label class="btn btn-secondary btn-sm cursor-pointer">
                                <?= icon('upload', 'w-4 h-4') ?> Upload photo
                                <input type="file" name="profile_picture" id="profile-picture" accept="image/jpeg,image/png,image/gif,image/webp" class="sr-only">
                            </label>
                            <button type="button" id="remove-picture" class="btn btn-ghost btn-sm <?= $avatarUrl ? '' : 'hidden' ?>">Remove</button>
                        </div>
                        <p class="form-hint">JPG, PNG, GIF or WEBP, up to 2MB.</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div data-field class="sm:col-span-2"><label for="p-name" class="form-label">Full name <span class="req">*</span></label><input id="p-name" name="name" class="form-input" required maxlength="255" value="<?= e($user['name']) ?>"></div>
                    <div data-field><label for="p-position" class="form-label">Position <span class="req">*</span></label><input id="p-position" name="position" class="form-input" required maxlength="255" value="<?= e($user['position']) ?>"></div>
                    <div data-field><label for="p-contact" class="form-label">Contact number <span class="req">*</span></label><input id="p-contact" name="contact_number" type="tel" inputmode="numeric" class="form-input" required data-phone value="<?= e($user['contact_number']) ?>"></div>
                    <div class="sm:col-span-2"><label class="form-label" for="p-email">Email</label><input id="p-email" class="form-input" value="<?= e($user['email']) ?>" disabled><p class="form-hint">Contact an administrator to change your email or role.</p></div>
                </div>
            </div>
            <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 rounded-b-2xl flex justify-end">
                <button type="submit" class="btn btn-primary" id="profile-submit">Save Profile</button>
            </div>
        </form>
    </section>

    <!-- Password -->
    <section class="card" aria-labelledby="password-heading">
        <form id="password-form">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 id="password-heading" class="font-semibold text-slate-900">Change password</h2>
                <p class="text-xs text-slate-500">At least <?= PASSWORD_MIN_LENGTH ?> characters.</p>
            </div>
            <div class="p-5 space-y-4">
                <input type="hidden" name="action" value="password">
                <div data-field><label for="pw-current" class="form-label">Current password <span class="req">*</span></label><input id="pw-current" name="current_password" type="password" class="form-input" required autocomplete="current-password"></div>
                <div data-field><label for="pw-new" class="form-label">New password <span class="req">*</span></label><input id="pw-new" name="password" type="password" class="form-input" required minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password"></div>
                <div data-field><label for="pw-confirm" class="form-label">Confirm new password <span class="req">*</span></label><input id="pw-confirm" name="password_confirmation" type="password" class="form-input" required data-match="password" autocomplete="new-password" data-label="Password confirmation"></div>
            </div>
            <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 rounded-b-2xl flex justify-end">
                <button type="submit" class="btn btn-primary" id="password-submit">Update Password</button>
            </div>
        </form>
    </section>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
