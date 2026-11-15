/**
 * SafeMatch - Mothers page: list, register, edit, view and delete mothers.
 */
(function () {
    'use strict';
    const esc = SM.escape;
    const form = document.getElementById('mother-form');
    const submitBtn = document.getElementById('mother-submit');
    let list;
    let viewingId = null;

    function renderRow(m) {
        const name = SM.fullName(m.first_name, m.last_name, m.middle_name);
        const status = Number(m.is_discharged)
            ? '<span class="badge badge-blue">Discharged</span>'
            : '<span class="badge badge-teal">Admitted</span>';
        return `
            <tr>
                <td class="font-mono text-xs font-semibold text-primary-700 whitespace-nowrap">${esc(m.mother_id)}</td>
                <td>
                    <button type="button" class="font-medium text-slate-900 hover:text-primary-700 text-left" data-action="view" data-id="${m.id}">${esc(name)}</button>
                    <p class="text-xs text-slate-500">${esc(SM.fmt.age(m.date_of_birth))} yrs${m.blood_type ? ' · ' + esc(m.blood_type) : ''}</p>
                </td>
                <td class="whitespace-nowrap">${esc(m.contact_number)}</td>
                <td class="whitespace-nowrap">${esc(SM.fmt.datetime(m.admission_date))}</td>
                <td>${esc(m.room_number || '—')}</td>
                <td class="tabular-nums">${esc(m.newborn_count)}</td>
                <td>${status}</td>
                <td class="text-right whitespace-nowrap">
                    ${SM.actionButton('view', m.id, 'eye', 'View details')}
                    ${SM.actionButton('edit', m.id, 'pencil', 'Edit')}
                    ${SM.config.isAdmin ? SM.actionButton('delete', m.id, 'trash', 'Delete', 'danger') : ''}
                </td>
            </tr>`;
    }

    function openCreate() {
        SM.form.reset(form);
        document.getElementById('mother-modal-title').textContent = 'Register Mother';
        submitBtn.textContent = 'Register Mother';
        form.elements.admission_date.value = SM.fmt.nowLocal();
        SM.modal.open('mother-modal');
    }

    async function openEdit(id) {
        try {
            const { data } = await SM.api('api/mothers/view.php', { params: { id } });
            SM.form.reset(form);
            SM.form.fill(form, data.mother);
            document.getElementById('mother-modal-title').textContent = `Edit ${data.mother.mother_id}`;
            submitBtn.textContent = 'Save Changes';
            SM.modal.close('mother-view-modal');
            SM.modal.open('mother-modal');
        } catch (err) { SM.handleError(err); }
    }

    function detail(label, value) {
        return `<div><dt>${esc(label)}</dt><dd>${value ? esc(value) : '<span class="text-slate-400">—</span>'}</dd></div>`;
    }

    async function openView(id) {
        const body = document.getElementById('mother-view-body');
        body.innerHTML = '<div class="space-y-3"><div class="skeleton h-5 w-1/2"></div><div class="skeleton h-5 w-2/3"></div><div class="skeleton h-5 w-1/3"></div></div>';
        viewingId = id;
        SM.modal.open('mother-view-modal');
        try {
            const { data } = await SM.api('api/mothers/view.php', { params: { id } });
            const m = data.mother;
            document.getElementById('mother-view-title').textContent = SM.fullName(m.first_name, m.last_name, m.middle_name);
            document.getElementById('mother-view-sub').textContent = `${m.mother_id} · Registered ${SM.fmt.datetime(m.created_at)}${m.created_by_name ? ' by ' + m.created_by_name : ''}`;
            document.getElementById('mother-view-add-baby').href = SM.url('pages/newborns.php?new=1&mother=' + m.id);

            const babies = data.newborns.length ? data.newborns.map((n) => `
                <li class="flex items-center justify-between gap-3 py-2.5">
                    <div class="min-w-0">
                        <a class="font-medium text-slate-900 hover:text-primary-700" href="${esc(SM.url('pages/newborns.php?view=' + n.id))}">${esc(n.display_name)}</a>
                        <p class="text-xs text-slate-500">${esc(n.newborn_id)} · ${esc(n.sex)} · ${esc(SM.fmt.datetime(n.date_of_birth))} · ${esc(n.weight)} kg</p>
                    </div>
                    ${SM.badges.discharge(n.discharge_status)}
                </li>`).join('') : '<li class="py-4 text-sm text-slate-500">No newborn registered yet.</li>';

            const assessments = data.assessments.length ? data.assessments.map((a) => `
                <li class="flex items-center justify-between gap-3 py-2">
                    <div><p class="text-sm font-medium">${esc(a.type_name)}</p><p class="text-xs text-slate-500">${esc(SM.fmt.datetime(a.assessment_date))}${a.assessed_by_name ? ' · ' + esc(a.assessed_by_name) : ''}</p></div>
                    ${SM.badges.result(a.result)}
                </li>`).join('') : '<li class="py-3 text-sm text-slate-500">No assessments recorded.</li>';

            body.innerHTML = `
                <dl class="detail-grid">
                    ${detail('Date of birth', SM.fmt.date(m.date_of_birth) + ' (' + SM.fmt.age(m.date_of_birth) + ' yrs)')}
                    ${detail('Blood type', m.blood_type)}
                    ${detail('Contact number', m.contact_number)}
                    ${detail('Address', m.address)}
                    ${detail('Emergency contact', [m.emergency_contact, m.emergency_contact_number].filter(Boolean).join(' · '))}
                    ${detail('Admission', SM.fmt.datetime(m.admission_date))}
                    ${detail('Room', m.room_number)}
                    ${detail('Attending physician', m.attending_physician)}
                    ${detail('Medical history', m.medical_history)}
                    ${detail('Allergies', m.allergies)}
                </dl>
                <div class="mt-6 grid gap-6 sm:grid-cols-2">
                    <section><h3 class="text-sm font-semibold text-slate-900">Newborns</h3><ul class="divide-y divide-slate-100">${babies}</ul></section>
                    <section><h3 class="text-sm font-semibold text-slate-900">Recent assessments</h3><ul class="divide-y divide-slate-100">${assessments}</ul></section>
                </div>`;
        } catch (err) {
            body.innerHTML = `<p class="text-red-600 text-sm">${esc(err.message)}</p>`;
        }
    }

    async function remove(id, btn) {
        const row = btn.closest('tr');
        const name = row?.querySelector('[data-action="view"]')?.textContent || 'this mother';
        const ok = await SM.confirm({
            title: 'Delete mother record?',
            message: `This permanently deletes ${name}, her newborns, QR codes, screenings, assessments and verification history. This cannot be undone.`,
            confirmText: 'Delete',
        });
        if (!ok) return;
        try {
            const json = await SM.api('api/mothers/delete.php', { method: 'POST', data: { id } });
            SM.toast(json.message, 'success');
            list.reload();
        } catch (err) { SM.handleError(err); }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!SM.form.validate(form)) return;
        const data = SM.form.serialize(form);
        const isEdit = !!data.id;
        SM.setLoading(submitBtn, true, 'Saving…');
        try {
            const json = await SM.api(isEdit ? 'api/mothers/update.php' : 'api/mothers/create.php', { method: 'POST', data });
            SM.toast(json.message, 'success', json.data.qr_regenerated ? 5000 : 3000);
            SM.modal.close('mother-modal');
            list.reload();
        } catch (err) {
            SM.handleError(err, form);
        } finally {
            SM.setLoading(submitBtn, false);
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        // Deep links: ?new=1 opens the form, ?view=ID opens details.
        // Read before listPage() rewrites the URL with filter state.
        const params = new URLSearchParams(location.search);
        SM.form.enableLiveValidation(form);
        list = SM.listPage({
            endpoint: 'api/mothers/list.php',
            table: '#mothers-table',
            filtersForm: '#mother-filters',
            pagination: '#mothers-pagination',
            defaultSort: { sort: 'admission_date', dir: 'desc' },
            emptyText: 'No mothers match your filters.',
            renderRow,
            actions: { view: openView, edit: openEdit, delete: remove },
        });

        document.getElementById('btn-add-mother').addEventListener('click', openCreate);
        document.getElementById('mother-view-edit').addEventListener('click', () => viewingId && openEdit(viewingId));

        if (params.get('new')) openCreate();
        if (params.get('view')) openView(params.get('view'));
    });
})();
