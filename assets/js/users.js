/**
 * SafeMatch - User management (admin): create, edit, activate/deactivate, reset password.
 */
(function () {
    'use strict';
    const esc = SM.escape;
    const form = document.getElementById('user-form');
    const resetForm = document.getElementById('reset-form');
    let list;
    let rows = [];

    function avatar(u) {
        if (u.profile_picture_url) return `<img src="${esc(u.profile_picture_url)}" alt="" class="w-9 h-9 rounded-full object-cover shrink-0">`;
        const initials = u.name.split(/\s+/).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
        return `<span class="w-9 h-9 rounded-full bg-primary-100 text-primary-700 font-semibold text-xs flex items-center justify-center shrink-0">${esc(initials)}</span>`;
    }

    function renderRow(u) {
        const active = Number(u.is_active);
        return `
            <tr class="${active ? '' : 'opacity-60'}">
                <td><div class="flex items-center gap-3">${avatar(u)}<div class="min-w-0"><p class="font-medium">${esc(u.name)} ${u.is_self ? '<span class="text-xs text-slate-400">(you)</span>' : ''}</p><p class="text-xs text-slate-500 truncate">${esc(u.email)}</p></div></div></td>
                <td>${SM.badges.role(u.role)}</td>
                <td>${esc(u.position)}</td>
                <td class="whitespace-nowrap">${esc(u.contact_number)}</td>
                <td class="whitespace-nowrap text-sm">${u.last_login ? esc(SM.fmt.relative(u.last_login)) : '<span class="text-slate-400">Never</span>'}</td>
                <td>${active ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-gray">Inactive</span>'}</td>
                <td class="text-right whitespace-nowrap">
                    ${SM.actionButton('edit', u.id, 'pencil', 'Edit')}
                    ${SM.actionButton('reset', u.id, 'key', 'Reset password')}
                    ${u.is_self ? '' : SM.actionButton('toggle', u.id, 'power', active ? 'Deactivate' : 'Activate', active ? 'danger' : '')}
                    <a href="${esc(SM.url('pages/activity-logs.php?user_id=' + u.id))}" class="icon-btn" title="View activity" aria-label="View activity">${SM.icon('list', 'w-5 h-5')}</a>
                </td>
            </tr>`;
    }

    const findRow = (id) => rows.find((r) => String(r.id) === String(id));

    function setCreateMode(isCreate) {
        form.querySelectorAll('[data-create-only]').forEach((el) => {
            el.classList.toggle('hidden', !isCreate);
            el.querySelectorAll('input').forEach((i) => { i.disabled = !isCreate; });
        });
    }

    function openCreate() {
        SM.form.reset(form);
        setCreateMode(true);
        document.getElementById('user-modal-title').textContent = 'Add User';
        SM.modal.open('user-modal');
    }

    function openEdit(id) {
        const u = findRow(id);
        if (!u) return;
        SM.form.reset(form);
        setCreateMode(false);
        SM.form.fill(form, u);
        form.elements.action.value = 'update';
        document.getElementById('user-modal-title').textContent = `Edit ${u.name}`;
        SM.modal.open('user-modal');
    }

    function openReset(id) {
        const u = findRow(id);
        if (!u) return;
        SM.form.reset(resetForm);
        resetForm.elements.id.value = u.id;
        document.getElementById('reset-sub').textContent = `${u.name} · ${u.email}`;
        SM.modal.open('reset-modal');
    }

    async function toggle(id) {
        const u = findRow(id);
        if (!u) return;
        const activate = !Number(u.is_active);
        const ok = await SM.confirm({
            title: activate ? 'Activate user?' : 'Deactivate user?',
            message: activate ? `${u.name} will be able to sign in again.` : `${u.name} will be signed out and unable to sign in. Their records and history are kept.`,
            confirmText: activate ? 'Activate' : 'Deactivate',
            danger: !activate,
        });
        if (!ok) return;
        try {
            const json = await SM.api('api/users/update.php', { method: 'POST', data: { id, action: 'toggle_active', is_active: activate } });
            SM.toast(json.message, 'success');
            list.reload();
        } catch (err) { SM.handleError(err); }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!SM.form.validate(form)) return;
        const data = SM.form.serialize(form);
        const isEdit = !!data.id;
        const btn = document.getElementById('user-submit');
        SM.setLoading(btn, true, 'Saving…');
        try {
            const json = await SM.api(isEdit ? 'api/users/update.php' : 'api/users/create.php', { method: 'POST', data });
            SM.toast(json.message, 'success');
            SM.modal.close('user-modal');
            list.reload();
        } catch (err) { SM.handleError(err, form); } finally { SM.setLoading(btn, false); }
    });

    resetForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!SM.form.validate(resetForm)) return;
        const btn = document.getElementById('reset-submit');
        SM.setLoading(btn, true, 'Saving…');
        try {
            const json = await SM.api('api/users/update.php', { method: 'POST', data: SM.form.serialize(resetForm) });
            SM.toast(json.message, 'success');
            SM.modal.close('reset-modal');
        } catch (err) { SM.handleError(err, resetForm); } finally { SM.setLoading(btn, false); }
    });

    document.addEventListener('DOMContentLoaded', () => {
        SM.form.enableLiveValidation(form);
        SM.form.enableLiveValidation(resetForm);
        list = SM.listPage({
            endpoint: 'api/users/list.php',
            table: '#users-table',
            filtersForm: '#user-filters',
            pagination: '#users-pagination',
            defaultSort: { sort: 'name', dir: 'asc' },
            emptyText: 'No users found.',
            renderRow,
            onLoaded: (json) => { rows = json.data; },
            actions: { edit: openEdit, reset: openReset, toggle },
        });
        document.getElementById('btn-add-user').addEventListener('click', openCreate);
    });
})();
