/**
 * SafeMatch - Discharge page: readiness list (traffic light), requirement
 * checklist, final discharge, and requirement configuration (admin).
 */
(function () {
    'use strict';
    const esc = SM.escape;
    let list;
    let current = null; // readiness payload of the open checklist

    const CATEGORY_LABELS = { mother: 'Mother', newborn: 'Newborn', both: 'Mother & baby' };
    const LIGHT_BAR = { green: 'bg-emerald-500', yellow: 'bg-amber-500', red: 'bg-red-500', discharged: 'bg-slate-400' };

    /* ---------------- List ---------------- */

    function progressBar(percent, light) {
        return `<div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden" role="progressbar" aria-valuenow="${Math.round(percent)}" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full rounded-full ${LIGHT_BAR[light] || 'bg-slate-400'}" style="width:${Math.max(0, Math.min(100, percent))}%"></div>
                </div>`;
    }

    function renderRow(r) {
        return `
            <tr>
                <td><button type="button" class="font-medium text-slate-900 hover:text-primary-700 text-left" data-action="open" data-id="${r.newborn_pk}">${esc(r.newborn_name)}</button>
                    <p class="font-mono text-xs text-slate-500">${esc(r.newborn_id)}</p></td>
                <td><p class="text-sm">${esc(r.mother_name)}</p><p class="font-mono text-xs text-slate-500">${esc(r.mother_id)}${r.room_number ? ' · Room ' + esc(r.room_number) : ''}</p></td>
                <td class="min-w-[160px]">
                    <div class="flex items-center justify-between gap-2 mb-1">${SM.badges.light(r.light)}<span class="text-xs tabular-nums text-slate-600">${r.met_requirements}/${r.total_requirements}</span></div>
                    ${progressBar(r.percent, r.light)}
                </td>
                <td>${SM.badges.verification(r.last_verification)}</td>
                <td>${SM.badges.discharge(r.discharge_status)}${r.discharge_date ? `<p class="text-xs text-slate-500 mt-1">${esc(SM.fmt.datetime(r.discharge_date))}</p>` : ''}</td>
                <td class="whitespace-nowrap">${esc(SM.fmt.date(r.nb_dob))}</td>
                <td class="text-right"><button type="button" class="btn btn-secondary btn-sm" data-action="open" data-id="${r.newborn_pk}">${r.discharge_status === 'discharged' ? 'View' : 'Checklist'}</button></td>
            </tr>`;
    }

    function paintChips() {
        const light = document.getElementById('f-light').value;
        const status = document.getElementById('f-status').value;
        document.querySelectorAll('[data-light-chip]').forEach((b) => {
            const active = status !== 'discharged' && b.dataset.lightChip === light;
            b.classList.toggle('!border-primary-500', active);
            b.classList.toggle('!bg-primary-50', active);
            b.setAttribute('aria-pressed', String(active));
        });
        const dis = document.querySelector('[data-status-chip]');
        dis.classList.toggle('!border-primary-500', status === 'discharged');
        dis.classList.toggle('!bg-primary-50', status === 'discharged');
        dis.setAttribute('aria-pressed', String(status === 'discharged'));
    }

    /* ---------------- Checklist ---------------- */

    async function openChecklist(newbornId) {
        const body = document.getElementById('checklist-body');
        body.innerHTML = '<div class="space-y-3"><div class="skeleton h-6 w-1/3"></div><div class="skeleton h-24"></div><div class="skeleton h-24"></div></div>';
        document.getElementById('checklist-footer').innerHTML = '';
        SM.modal.open('checklist-modal');
        try {
            const { data } = await SM.api('api/discharge/check-readiness.php', { params: { newborn_id: newbornId } });
            current = data;
            renderChecklist();
        } catch (err) {
            body.innerHTML = `<p class="text-sm text-red-600">${esc(err.message)}</p>`;
        }
    }

    function summaryBlock(d) {
        const lightText = {
            green: 'All mandatory requirements met',
            yellow: 'Making progress - some mandatory items pending',
            red: 'Many requirements still pending',
            discharged: 'Discharge completed',
        }[d.light];
        return `
            <div class="rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="light-dot light-${esc(d.light)} !w-5 !h-5" aria-hidden="true"></span>
                        <div><p class="text-2xl font-bold tabular-nums" id="cl-percent">${Math.round(d.percent)}%</p>
                        <p class="text-xs text-slate-500" id="cl-light-text">${esc(lightText)} · ${d.met}/${d.total} met</p></div>
                    </div>
                    <div class="flex flex-wrap gap-2 items-center">
                        ${SM.badges.verification(d.verification && d.verification.verification_result)}
                        ${d.verification ? `<span class="text-xs text-slate-500">${esc(SM.fmt.relative(d.verification.verified_at))}</span>` : ''}
                        ${d.record.discharge_status !== 'discharged' && !d.verified ? `<a href="${esc(SM.url('pages/qr-verification.php'))}" class="btn btn-secondary btn-sm">${SM.icon('qr', 'w-4 h-4')} Verify bands</a>` : ''}
                    </div>
                </div>
                <div class="mt-3" id="cl-bar">${progressBar(d.percent, d.light)}</div>
                <div id="cl-blockers">${blockersHtml(d.blockers)}</div>
            </div>`;
    }

    function blockersHtml(blockers) {
        if (!blockers || !blockers.length) return '';
        return `<ul class="mt-3 space-y-1 text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-lg p-3">
            ${blockers.map((b) => `<li class="flex gap-2">${SM.icon('warning', 'w-4 h-4 shrink-0 mt-0.5')}<span>${esc(b)}</span></li>`).join('')}</ul>`;
    }

    function requirementItem(req, locked) {
        const id = `req-${req.requirement_id}`;
        return `
            <li class="py-3 flex gap-3" data-req="${req.requirement_id}">
                <input type="checkbox" id="${id}" class="mt-0.5 w-6 h-6 shrink-0 accent-emerald-600 cursor-pointer disabled:cursor-not-allowed" ${req.is_met ? 'checked' : ''} ${locked ? 'disabled' : ''} data-req-toggle>
                <div class="min-w-0 flex-1">
                    <label for="${id}" class="font-medium text-slate-900 cursor-pointer">${esc(req.name)}</label>
                    ${req.is_mandatory ? '<span class="ml-1 badge badge-red !text-[10px]">Required</span>' : '<span class="ml-1 badge badge-gray !text-[10px]">Optional</span>'}
                    ${req.description ? `<p class="text-xs text-slate-500">${esc(req.description)}</p>` : ''}
                    <p class="text-[11px] text-slate-400 mt-0.5" data-req-meta>${req.checked_at ? `Updated ${esc(SM.fmt.datetime(req.checked_at))} by ${esc(req.checked_by_name || '—')}` : 'Not yet checked'}</p>
                    ${locked ? (req.notes ? `<p class="text-xs text-slate-600 mt-1">Note: ${esc(req.notes)}</p>` : '') : `
                    <input type="text" class="form-input !min-h-[36px] mt-2 text-sm" placeholder="Note (optional)" maxlength="2000" value="${esc(req.notes || '')}" data-req-note aria-label="Note for ${esc(req.name)}">`}
                </div>
            </li>`;
    }

    function renderChecklist() {
        const d = current;
        const info = d.info;
        const locked = d.record.discharge_status === 'discharged';
        document.getElementById('checklist-title').textContent = `${info.display_name} & ${info.mother_name}`;
        document.getElementById('checklist-sub').textContent = `${info.newborn_id} · ${info.mother_id}${info.room_number ? ' · Room ' + info.room_number : ''}`;

        const sections = ['mother', 'newborn', 'both'].map((cat) => {
            const items = d.grouped[cat] || [];
            if (!items.length) return '';
            const met = items.filter((i) => i.is_met).length;
            return `
                <section>
                    <div class="flex items-center justify-between"><h3 class="text-sm font-semibold text-slate-900">${esc(CATEGORY_LABELS[cat])}</h3>
                    <span class="text-xs text-slate-500 tabular-nums" data-cat-count="${cat}">${met}/${items.length}</span></div>
                    <ul class="divide-y divide-slate-100">${items.map((r) => requirementItem(r, locked)).join('')}</ul>
                </section>`;
        }).join('');

        const screenings = d.screenings.length
            ? d.screenings.map((s) => `<li class="flex justify-between gap-2 py-1.5"><span>${esc(s.name)}</span>${SM.badges.result(s.result)}</li>`).join('')
            : '<li class="py-2 text-slate-500">No screenings recorded yet.</li>';

        const dischargedBlock = locked ? `
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 mb-5">
                <p class="font-semibold">Discharged ${esc(SM.fmt.datetime(d.record.discharge_date))}${info.discharged_by_name ? ' by ' + esc(info.discharged_by_name) : ''}</p>
                ${d.record.discharge_notes ? `<p class="mt-1 whitespace-pre-line">${esc(d.record.discharge_notes)}</p>` : ''}
            </div>` : '';

        document.getElementById('checklist-body').innerHTML = `
            ${dischargedBlock}
            ${summaryBlock(d)}
            <div class="mt-5 grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-5">${sections}</div>
                <aside class="space-y-4">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <h3 class="text-sm font-semibold text-slate-900 mb-1">Newborn screenings</h3>
                        <ul class="text-sm">${screenings}</ul>
                        ${locked ? '' : `<a class="mt-2 inline-block text-xs font-medium text-primary-700 hover:underline" href="${esc(SM.url('pages/screenings.php?new=1&newborn=' + info.id))}">+ Record screening</a>`}
                    </div>
                    ${locked ? '' : `
                    <div class="rounded-xl border border-slate-200 p-4">
                        <label for="discharge-notes" class="form-label">Discharge notes</label>
                        <textarea id="discharge-notes" class="form-input" rows="4" maxlength="5000" placeholder="Instructions given, follow-up dates, who accompanied the mother…"></textarea>
                    </div>`}
                </aside>
            </div>`;

        renderFooter();
        bindChecklist(locked);
    }

    function renderFooter() {
        const d = current;
        const footer = document.getElementById('checklist-footer');
        if (d.record.discharge_status === 'discharged') {
            footer.innerHTML = '<button type="button" class="btn btn-secondary" data-modal-close>Close</button>';
            return;
        }
        footer.innerHTML = `
            <button type="button" class="btn btn-secondary" data-modal-close>Close</button>
            <button type="button" class="btn btn-success" id="btn-discharge" ${d.can_discharge ? '' : 'disabled aria-disabled="true"'} title="${d.can_discharge ? '' : esc(d.blockers.join(' '))}">
                ${SM.icon('exit', 'w-4 h-4')} Discharge mother &amp; baby
            </button>`;
        document.getElementById('btn-discharge').addEventListener('click', doDischarge);
    }

    function bindChecklist(locked) {
        if (locked) return;
        const body = document.getElementById('checklist-body');
        body.querySelectorAll('[data-req-toggle]').forEach((cb) => {
            cb.addEventListener('change', () => saveRequirement(cb.closest('[data-req]')));
        });
        body.querySelectorAll('[data-req-note]').forEach((input) => {
            input.addEventListener('change', () => saveRequirement(input.closest('[data-req]')));
        });
    }

    async function saveRequirement(li) {
        const cb = li.querySelector('[data-req-toggle]');
        const note = li.querySelector('[data-req-note]');
        cb.disabled = true;
        try {
            const { data, message } = await SM.api('api/discharge/update-requirements.php', {
                method: 'POST',
                data: { record_id: current.record.id, requirement_id: li.dataset.req, is_met: cb.checked, notes: note ? note.value : '' },
            });
            // Update local state and the summary without re-rendering inputs.
            Object.values(current.grouped).flat().forEach((r) => {
                if (String(r.requirement_id) === li.dataset.req) r.is_met = cb.checked;
            });
            Object.assign(current, {
                percent: data.percent, light: data.light, met: data.met, total: data.total,
                mandatory_pending: data.mandatory_pending, can_discharge: data.can_discharge, blockers: data.blockers,
            });
            current.record.discharge_status = data.status;
            li.querySelector('[data-req-meta]').textContent = `Updated just now by ${SM.config.user.name}`;
            Object.entries(current.grouped).forEach(([cat, items]) => {
                const el = document.querySelector(`[data-cat-count="${cat}"]`);
                if (el) el.textContent = `${items.filter((i) => i.is_met).length}/${items.length}`;
            });
            document.getElementById('cl-percent').textContent = Math.round(data.percent) + '%';
            document.getElementById('cl-bar').innerHTML = progressBar(data.percent, data.light);
            document.getElementById('cl-blockers').innerHTML = blockersHtml(data.blockers);
            const dot = document.querySelector('#checklist-body .light-dot');
            if (dot) dot.className = `light-dot light-${data.light} !w-5 !h-5`;
            document.getElementById('cl-light-text').textContent = `${data.met}/${data.total} met`;
            renderFooter();
            SM.toast(message, 'success', 1800);
            list.reload();
        } catch (err) {
            cb.checked = !cb.checked; // revert
            SM.handleError(err);
        } finally {
            cb.disabled = false;
        }
    }

    async function doDischarge() {
        const info = current.info;
        const ok = await SM.confirm({
            title: 'Confirm discharge',
            message: `Discharge ${info.display_name} (${info.newborn_id}) with ${info.mother_name} (${info.mother_id})? The checklist will be locked.`,
            confirmText: 'Discharge', danger: false,
        });
        if (!ok) return;
        const btn = document.getElementById('btn-discharge');
        SM.setLoading(btn, true, 'Discharging…');
        try {
            const json = await SM.api('api/discharge/discharge.php', {
                method: 'POST',
                data: { record_id: current.record.id, notes: document.getElementById('discharge-notes')?.value || '' },
            });
            SM.toast(json.message, 'success', 4000);
            list.reload();
            openChecklist(info.id); // reload as locked record
        } catch (err) {
            SM.handleError(err);
            SM.setLoading(btn, false);
        }
    }

    /* ---------------- Requirements admin ---------------- */

    async function renderRequirements() {
        const wrap = document.getElementById('requirements-list');
        try {
            const { data } = await SM.api('api/discharge/requirements.php');
            wrap.innerHTML = ['mother', 'newborn', 'both'].map((cat) => {
                const items = data.filter((r) => r.category === cat);
                return `
                    <section>
                        <h3 class="text-sm font-semibold text-slate-900">${esc(CATEGORY_LABELS[cat])} <span class="text-slate-400 font-normal">(${items.length})</span></h3>
                        <ul class="divide-y divide-slate-100">${items.map((r) => `
                            <li class="py-2.5 flex items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium ${Number(r.is_active) ? '' : 'line-through text-slate-400'}">${esc(r.name)}
                                    ${Number(r.is_mandatory) ? '<span class="badge badge-red !text-[10px]">Required</span>' : '<span class="badge badge-gray !text-[10px]">Optional</span>'}
                                    ${Number(r.is_active) ? '' : '<span class="badge badge-gray !text-[10px]">Inactive</span>'}</p>
                                    ${r.description ? `<p class="text-xs text-slate-500">${esc(r.description)}</p>` : ''}
                                </div>
                                <button type="button" class="icon-btn" data-req-edit="${r.id}" aria-label="Edit ${esc(r.name)}">${SM.icon('pencil', 'w-4 h-4')}</button>
                            </li>`).join('') || '<li class="py-2 text-sm text-slate-500">None</li>'}</ul>
                    </section>`;
            }).join('');
            wrap.querySelectorAll('[data-req-edit]').forEach((b) => b.addEventListener('click', () => {
                const r = data.find((x) => String(x.id) === b.dataset.reqEdit);
                const f = document.getElementById('requirement-form');
                SM.form.fill(f, r);
                document.getElementById('req-form-title').textContent = `Edit "${r.name}"`;
                f.elements.name.focus();
            }));
        } catch (err) { SM.handleError(err); }
    }

    function initRequirementsAdmin() {
        const btn = document.getElementById('btn-requirements');
        if (!btn) return;
        const f = document.getElementById('requirement-form');
        SM.form.enableLiveValidation(f);
        btn.addEventListener('click', () => {
            SM.form.reset(f);
            document.getElementById('req-form-title').textContent = 'Add a requirement';
            SM.modal.open('requirements-modal');
            renderRequirements();
        });
        f.addEventListener('reset', () => {
            setTimeout(() => { f.elements.id.value = ''; }, 0);
            document.getElementById('req-form-title').textContent = 'Add a requirement';
        });
        f.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!SM.form.validate(f)) return;
            const submit = document.getElementById('req-submit');
            SM.setLoading(submit, true, 'Saving…');
            try {
                const json = await SM.api('api/discharge/requirements.php', { method: 'POST', data: SM.form.serialize(f) });
                SM.toast(json.message, 'success');
                SM.form.reset(f);
                renderRequirements();
                list.reload();
            } catch (err) { SM.handleError(err, f); } finally { SM.setLoading(submit, false); }
        });
    }

    /* ---------------- Init ---------------- */

    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(location.search);
        const filters = document.getElementById('discharge-filters');
        if (!params.has('status')) document.getElementById('f-status').value = 'open';

        list = SM.listPage({
            endpoint: 'api/discharge/list.php',
            table: '#discharge-table',
            filtersForm: '#discharge-filters',
            pagination: '#discharge-pagination',
            defaultSort: { sort: 'birth', dir: 'desc' },
            emptyText: 'No mother-baby pairs match your filters.',
            renderRow,
            actions: { open: openChecklist },
            onLoaded: paintChips,
        });

        const setFilter = (status, light) => {
            document.getElementById('f-status').value = status;
            document.getElementById('f-light').value = light;
            filters.dispatchEvent(new Event('change'));
        };
        document.querySelectorAll('[data-light-chip]').forEach((b) => b.addEventListener('click', () => setFilter('open', b.dataset.lightChip)));
        document.querySelector('[data-status-chip]').addEventListener('click', () => setFilter('discharged', ''));
        document.getElementById('f-status').addEventListener('change', () => { document.getElementById('f-light').value = ''; });

        initRequirementsAdmin();
        if (params.get('newborn')) openChecklist(params.get('newborn'));
    });
})();
