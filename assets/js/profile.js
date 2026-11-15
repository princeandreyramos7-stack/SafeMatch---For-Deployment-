/**
 * SafeMatch - Profile page: update details & picture, change password.
 */
(function () {
    'use strict';
    const esc = SM.escape;
    const MAX_SIZE = 2 * 1024 * 1024;
    const TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    const profileForm = document.getElementById('profile-form');
    const passwordForm = document.getElementById('password-form');
    const fileInput = document.getElementById('profile-picture');
    const preview = document.getElementById('avatar-preview');
    const removeBtn = document.getElementById('remove-picture');
    let objectUrl = null;

    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        SM.form.setFieldError(fileInput, '');
        if (!file) return;
        if (!TYPES.includes(file.type)) {
            SM.form.setFieldError(fileInput, 'Only JPG, PNG, GIF or WEBP images are allowed.');
            fileInput.value = '';
            return;
        }
        if (file.size > MAX_SIZE) {
            SM.form.setFieldError(fileInput, 'Image must be 2MB or smaller.');
            fileInput.value = '';
            return;
        }
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        preview.innerHTML = `<img src="${esc(objectUrl)}" alt="New profile picture preview" class="w-full h-full object-cover">`;
        profileForm.elements.remove_picture.value = '';
        removeBtn.classList.remove('hidden');
    });

    removeBtn.addEventListener('click', () => {
        fileInput.value = '';
        profileForm.elements.remove_picture.value = '1';
        const initials = SM.config.user.name.split(/\s+/).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
        preview.textContent = initials;
        removeBtn.classList.add('hidden');
    });

    profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!SM.form.validate(profileForm)) return;
        const btn = document.getElementById('profile-submit');
        SM.setLoading(btn, true, 'Saving…');
        try {
            const json = await SM.api('api/users/profile-update.php', { method: 'POST', formData: new FormData(profileForm) });
            SM.toast(json.message, 'success');
            // Reflect changes in the header without reloading.
            document.querySelectorAll('[data-user-name]').forEach((el) => { el.textContent = json.data.name; });
            SM.config.user.name = json.data.name;
            const headerAvatar = document.querySelector('[data-user-avatar]');
            if (headerAvatar) {
                const replacement = json.data.profile_picture_url
                    ? Object.assign(document.createElement('img'), { src: json.data.profile_picture_url, alt: '', className: 'w-9 h-9 rounded-full object-cover' })
                    : Object.assign(document.createElement('span'), { textContent: json.data.name.split(/\s+/).slice(0, 2).map((p) => p[0]).join('').toUpperCase(), className: 'w-9 h-9 rounded-full bg-primary-100 text-primary-700 font-semibold text-sm flex items-center justify-center' });
                replacement.setAttribute('data-user-avatar', '');
                headerAvatar.replaceWith(replacement);
            }
            fileInput.value = '';
            profileForm.elements.remove_picture.value = '';
        } catch (err) { SM.handleError(err, profileForm); } finally { SM.setLoading(btn, false); }
    });

    passwordForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!SM.form.validate(passwordForm)) return;
        const btn = document.getElementById('password-submit');
        SM.setLoading(btn, true, 'Updating…');
        try {
            const json = await SM.api('api/users/profile-update.php', { method: 'POST', data: SM.form.serialize(passwordForm) });
            SM.toast(json.message, 'success');
            passwordForm.reset();
        } catch (err) { SM.handleError(err, passwordForm); } finally { SM.setLoading(btn, false); }
    });

    document.addEventListener('DOMContentLoaded', () => {
        SM.form.enableLiveValidation(profileForm);
        SM.form.enableLiveValidation(passwordForm);
    });
})();
