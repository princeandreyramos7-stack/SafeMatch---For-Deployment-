/**
 * SafeMatch - Newborns page: list, register, edit, view, QR bands, delete.
 */
(function () {
    'use strict';
    const esc = SM.escape;
    const form = document.getElementById('newborn-form');
    const submitBtn = document.getElementById('newborn-submit');
    const motherSelect = document.getElementById('nb-mother');
    let list;
    let viewingId = null;
    let qrNewbornId = null;
    let mothersCache = [];

    /* ---------------- Mother picker ---------------- */

    async function loadMothers(includeId) {
        try {
            const { data } = await SM.api('api/mothers/list.php', { params: { options: 1, status: 'admitted' } });
            mothersCache = data;
            // When editing, keep the current mother selectable even if discharged.
            if (includeId && !data.some((m) => String(m.id) === String(includeId))) {
                const { data: v } = await SM.api('api/mothers/view.php', { params: { id: includeId } });
                mothersCache = [v.mother, ...data];
            }
            SM.fillSelect(motherSelect, mothersCache, 'id',
                (m) => `${m.mother_id} — ${SM.fullName(m.first_name, m.last_name)}${m.room_number ? ' (Room ' + m.room_number + ')' : ''}`);
        } catch (err) { SM.handleError(err); }
    }

    // Default the baby's last name to the mother's.
    motherSelect.addEventListener('change', () => {
        const m = mothersCache.find((x) => String(x.id) === motherSelect.value);
        if (m && !form.elements.last_name.value) form.elements.last_name.value = m.last_name;
    });

    /* ---------------- Table ---------------- */

    function renderRow(n) {
        return `
            <tr>
                <td class="font-mono text-xs font-semibold text-primary-700 whitespace-nowrap">${esc(n.newborn_id)}</td>
                <td>
                    <button type="button" class="font-medium text-slate-900 hover:text-primary-700 text-left" data-action="view" data-id="${n.id}">${esc(n.display_name)}</button>
                    <p class="text-xs text-slate-500">${esc(n.sex)}</p>
                </td>
                <td><p class="text-sm">${esc(SM.fullName(n.mother_first_name, n.mother_last_name))}</p><p class="text-xs font-mono text-slate-500">${esc(n.mother_id)}</p></td>
                <td class="whitespace-nowrap">${esc(SM.fmt.date(n.date_of_birth))}<p class="text-xs text-slate-500">${esc(SM.fmt.time(n.time_of_birth))}</p></td>
                <td class="tabular-nums whitespace-nowrap">${esc(n.weight)} kg</td>
                <td>${esc(n.delivery_type)}</td>
                <td>${SM.badges.discharge(n.discharge_status)}</td>
                <td class="text-right whitespace-nowrap">
                    ${SM.actionButton('qr', n.id, 'qr', 'QR codes')}
                    ${SM.actionButton('view', n.id, 'eye', 'View details')}
                    ${SM.actionButton('edit', n.id, 'pencil', 'Edit')}
                    ${SM.config.isAdmin ? SM.actionButton('delete', n.id, 'trash', 'Delete', 'danger') : ''}
                </td>
            </tr>`;
    }

    /* ---------------- Create / edit ---------------- */

    async function openCreate(motherId) {
        SM.form.reset(form);
        document.getElementById('newborn-modal-title').textContent = 'Register Newborn';
        submitBtn.textContent = 'Register Newborn';
        document.getElementById('nb-mother-search').value = '';
        form.elements.date_of_birth.value = SM.fmt.today();
        form.elements.time_of_birth.value = SM.fmt.nowLocal().slice(11, 16);
        SM.modal.open('newborn-modal');
        await loadMothers();
        if (motherId) {
            motherSelect.value = motherId;
            motherSelect.dispatchEvent(new Event('change'));
        }
    }

    async function openEdit(id) {
        try {
            const { data } = await SM.api('api/newborns/view.php', { params: { id } });
            SM.form.reset(form);
            await loadMothers(data.newborn.mother_id);
            SM.form.fill(form, data.newborn);
            const locked = data.discharge.status === 'discharged';
            motherSelect.disabled = locked;
            document.getElementById('newborn-modal-title').textContent = `Edit ${data.newborn.newborn_id}`;
            submitBtn.textContent = 'Save Changes';
            SM.modal.close('newborn-view-modal');
            SM.modal.open('newborn-modal');
        } catch (err) { SM.handleError(err); }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!SM.form.validate(form)) return;
        motherSelect.disabled = false; // include the value even when locked
        const data = SM.form.serialize(form);
        const isEdit = !!data.id;
        SM.setLoading(submitBtn, true, 'Saving…');
        try {
            const json = await SM.api(isEdit ? 'api/newborns/update.php' : 'api/newborns/create.php', { method: 'POST', data });
            SM.toast(json.message, 'success', 4500);
            SM.modal.close('newborn-modal');
            list.reload();
            if (!isEdit) openQr(json.data.id); // show the bands right away for printing
        } catch (err) {
            SM.handleError(err, form);
        } finally {
            SM.setLoading(submitBtn, false);
        }
    });

    /* ---------------- View ---------------- */

    function detail(label, value) {
        return `<div><dt>${esc(label)}</dt><dd>${value !== null && value !== undefined && value !== '' ? esc(value) : '<span class="text-slate-400">—</span>'}</dd></div>`;
    }

    async function openView(id) {
        const body = document.getElementById('newborn-view-body');
        body.innerHTML = '<div class="space-y-3"><div class="skeleton h-5 w-1/2"></div><div class="skeleton h-5 w-2/3"></div></div>';
        viewingId = id;
        SM.modal.open('newborn-view-modal');
        try {
            const { data } = await SM.api('api/newborns/view.php', { params: { id } });
            const n = data.newborn;
            const m = data.mother;
            const d = data.discharge;
            document.getElementById('newborn-view-title').textContent = n.display_name;
            document.getElementById('newborn-view-sub').textContent = `${n.newborn_id} · Registered ${SM.fmt.datetime(n.created_at)}${n.created_by_name ? ' by ' + n.created_by_name : ''}`;

            const screenings = data.screenings.length ? data.screenings.map((s) => `
                <li class="flex items-center justify-between gap-3 py-2">
                    <div><p class="text-sm font-medium">${esc(s.type_name)}</p><p class="text-xs text-slate-500">${esc(SM.fmt.datetime(s.screening_date))}${s.performed_by_name ? ' · ' + esc(s.performed_by_name) : ''}</p></div>
                    ${SM.badges.result(s.result)}
                </li>`).join('') : '<li class="py-3 text-sm text-slate-500">No screenings recorded yet.</li>';

            body.innerHTML = `
                <div class="rounded-xl border border-slate-200 p-4 mb-5 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
                    <div>
                        <p class="text-xs text-slate-500">Mother</p>
                        <a class="font-semibold text-slate-900 hover:text-primary-700" href="${esc(SM.url('pages/mothers.php?view=' + m.id))}">${esc(SM.fullName(m.first_name, m.last_name))}</a>
                        <p class="text-xs text-slate-500">${esc(m.mother_id)}${m.room_number ? ' · Room ' + esc(m.room_number) : ''}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        ${SM.badges.light(d.light, d.percent)}
                        ${SM.badges.verification(d.verification && d.verification.verification_result)}
                        <a href="${esc(SM.url('pages/discharge.php?newborn=' + n.id))}" class="btn btn-secondary btn-sm">Discharge checklist</a>
                    </div>
                </div>
                <dl class="detail-grid">
                    ${detail('Sex', n.sex)}
                    ${detail('Born', SM.fmt.date(n.date_of_birth) + ' at ' + SM.fmt.time(n.time_of_birth))}
                    ${detail('Weight', n.weight + ' kg')}
                    ${detail('Length', n.length + ' cm')}
                    ${detail('Head circumference', n.head_circumference ? n.head_circumference + ' cm' : '')}
                    ${detail('APGAR (1 / 5 min)', [n.apgar_score_1min ?? '—', n.apgar_score_5min ?? '—'].join(' / '))}
                    ${detail('Delivery type', n.delivery_type)}
                    ${detail('Pediatrician', n.pediatrician)}
                    ${detail('Birth complications', n.birth_complications)}
                </dl>
                <section class="mt-6">
                    <div class="flex items-center justify-between"><h3 class="text-sm font-semibold text-slate-900">Screenings</h3>
                    <a href="${esc(SM.url('pages/screenings.php?new=1&newborn=' + n.id))}" class="text-xs font-medium text-primary-700 hover:underline">+ Record screening</a></div>
                    <ul class="divide-y divide-slate-100">${screenings}</ul>
                </section>`;
        } catch (err) {
            body.innerHTML = `<p class="text-red-600 text-sm">${esc(err.message)}</p>`;
        }
    }

    /* ---------------- QR codes ---------------- */

    function qrCard(title, subtitle, id, img, kind) {
        return `
            <figure class="rounded-xl border border-slate-200 p-4 text-center">
                <figcaption class="mb-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">${esc(title)}</p>
                    <p class="font-semibold text-slate-900">${esc(subtitle)}</p>
                    <p class="font-mono text-xs text-primary-700">${esc(id)}</p>
                </figcaption>
                <img src="${esc(img)}" alt="QR code for ${esc(subtitle)}" class="mx-auto w-48 h-48 sm:w-56 sm:h-56" style="image-rendering: pixelated">
                <a href="${esc(img)}" download="${esc(id)}-${esc(kind)}-qr.png" class="btn btn-secondary btn-sm mt-3">${SM.icon("download", "w-4 h-4")} Download QR</a>
            </figure>`;
    }

    async function openQr(id) {
        qrNewbornId = id;
        const body = document.getElementById('qr-modal-body');
        body.innerHTML = '<div class="grid sm:grid-cols-2 gap-4"><div class="skeleton h-64"></div><div class="skeleton h-64"></div></div>';
        document.getElementById('qr-print').href = SM.url('pages/qr-print.php?newborn_id=' + encodeURIComponent(id));
        SM.modal.open('qr-modal');
        try {
            const { data } = await SM.api('api/qr/view.php', { params: { newborn_id: id } });
            renderQr(data);
        } catch (err) {
            body.innerHTML = `<p class="text-red-600 text-sm">${esc(err.message)}</p>`;
        }
    }

    function renderQr({ info, qr }) {
        document.getElementById('qr-modal-sub').textContent = `Generated ${SM.fmt.datetime(qr.generated_at)}${qr.generated_by_name ? ' by ' + qr.generated_by_name : ''}`;
        document.getElementById('qr-modal-body').innerHTML = `
            <div class="grid sm:grid-cols-2 gap-4">
                ${qrCard("Mother's band", info.mother_name, info.mother_id, qr.mother_qr_code, "mother")}
                ${qrCard("Baby's band", info.display_name, info.newborn_id, qr.newborn_qr_code, "baby")}
            </div>
            <p class="mt-4 text-xs text-slate-500">Each code is digitally signed. Scan the mother's band first, then the baby's, on the QR Verification page before discharge.</p>`;
    }

    document.getElementById('qr-regenerate').addEventListener('click', async (e) => {
        const ok = await SM.confirm({
            title: 'Regenerate QR codes?',
            message: 'New images will be created from the current records. Bands already printed stay valid only if the encoded details did not change.',
            confirmText: 'Regenerate', danger: false,
        });
        if (!ok) return;
        const btn = e.currentTarget;
        SM.setLoading(btn, true, 'Generating…');
        try {
            await SM.api('api/qr/generate.php', { method: 'POST', data: { newborn_id: qrNewbornId } });
            const { data } = await SM.api('api/qr/view.php', { params: { newborn_id: qrNewbornId } });
            renderQr(data);
            SM.toast('QR codes regenerated.', 'success');
        } catch (err) { SM.handleError(err); } finally { SM.setLoading(btn, false); }
    });

    /* ---------------- Delete ---------------- */

    async function remove(id, btn) {
        const name = btn.closest('tr')?.querySelector('[data-action="view"]')?.textContent || 'this newborn';
        const ok = await SM.confirm({
            title: 'Delete newborn record?',
            message: `This permanently deletes ${name} with QR codes, screenings and verification history.`,
            confirmText: 'Delete',
        });
        if (!ok) return;
        try {
            const json = await SM.api('api/newborns/delete.php', { method: 'POST', data: { id } });
            SM.toast(json.message, 'success');
            list.reload();
        } catch (err) { SM.handleError(err); }
    }

    /* ---------------- Init ---------------- */

    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(location.search); // read before listPage rewrites the URL
        SM.form.enableLiveValidation(form);
        SM.searchableSelect(document.getElementById('nb-mother-search'), motherSelect);
        document.getElementById('newborn-modal').addEventListener('modal:closed', () => { motherSelect.disabled = false; });

        list = SM.listPage({
            endpoint: 'api/newborns/list.php',
            table: '#newborns-table',
            filtersForm: '#newborn-filters',
            pagination: '#newborns-pagination',
            defaultSort: { sort: 'date_of_birth', dir: 'desc' },
            emptyText: 'No newborns match your filters.',
            renderRow,
            actions: { view: openView, edit: openEdit, delete: remove, qr: openQr },
        });

        document.getElementById('btn-add-newborn').addEventListener('click', () => openCreate());
        document.getElementById('newborn-view-edit').addEventListener('click', () => viewingId && openEdit(viewingId));
        document.getElementById('newborn-view-qr').addEventListener('click', () => {
            if (!viewingId) return;
            SM.modal.close('newborn-view-modal');
            openQr(viewingId);
        });

        if (params.get('new')) openCreate(params.get('mother'));
        if (params.get('view')) openView(params.get('view'));
        if (params.get('qr')) openQr(params.get('qr'));
    });
})();
