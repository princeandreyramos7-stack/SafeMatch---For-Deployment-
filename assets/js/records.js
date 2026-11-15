/**
 * SafeMatch - Shared controller for clinical record pages
 * (newborn screenings and maternal assessments).
 *
 * SM.recordsPage({
 *   listEndpoint, createEndpoint, typesEndpoint,
 *   subjectEndpoint, subjectParams, subjectLabel(row), subjectQueryKey,
 *   renderSubject(row), noun
 * })
 */
(function () {
    'use strict';
    const esc = SM.escape;

    SM.recordsPage = function (cfg) {
        const form = document.getElementById('record-form');
        const submitBtn = document.getElementById('record-submit');
        const subjectSelect = document.getElementById('rec-subject');
        const typeSelect = document.getElementById('rec-type');
        const filterType = document.getElementById('f-type');
        let list;
        let types = [];

        async function loadTypes() {
            try {
                const { data } = await SM.api(cfg.typesEndpoint);
                types = data;
                SM.fillSelect(typeSelect, types, 'id', (t) => t.name);
                const current = filterType.value;
                SM.fillSelect(filterType, types, 'id', (t) => t.name);
                filterType.value = current;
            } catch (err) { SM.handleError(err); }
        }

        async function loadSubjects() {
            try {
                const { data } = await SM.api(cfg.subjectEndpoint, { params: cfg.subjectParams });
                SM.fillSelect(subjectSelect, data, 'id', cfg.subjectLabel);
            } catch (err) { SM.handleError(err); }
        }

        function renderRow(r) {
            return `
                <tr class="${r.result === 'Abnormal' ? 'bg-red-50/40' : ''}">
                    <td class="whitespace-nowrap">${esc(SM.fmt.datetime(r[cfg.dateField]))}</td>
                    <td>${cfg.renderSubject(r)}</td>
                    <td class="whitespace-nowrap">${esc(r.type_name)}</td>
                    <td>${SM.badges.result(r.result)}</td>
                    <td class="text-xs text-slate-600 max-w-xs">${esc(r.notes || '')}</td>
                    <td class="whitespace-nowrap text-sm">${esc(r[cfg.byField] || '—')}</td>
                </tr>`;
        }

        function renderSummary(json) {
            const s = json.summary || {};
            document.querySelector('[data-summary="total"]').textContent = json.pagination.total;
            Object.entries(s).forEach(([k, v]) => {
                const el = document.querySelector(`[data-summary="${k}"]`);
                if (el) el.textContent = v;
            });
        }

        async function openCreate(subjectId) {
            SM.form.reset(form);
            document.getElementById('rec-subject-search').value = '';
            form.elements[cfg.dateField].value = SM.fmt.nowLocal();
            SM.modal.open('record-modal');
            await loadSubjects();
            if (subjectId) subjectSelect.value = subjectId;
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!SM.form.validate(form)) return;
            SM.setLoading(submitBtn, true, 'Saving…');
            try {
                const json = await SM.api(cfg.createEndpoint, { method: 'POST', data: SM.form.serialize(form) });
                SM.toast(json.message, 'success');
                SM.modal.close('record-modal');
                list.reload();
            } catch (err) {
                SM.handleError(err, form);
            } finally {
                SM.setLoading(submitBtn, false);
            }
        });

        /* ---------- Type management (admin) ---------- */

        const typesModal = document.getElementById('types-modal');
        const typeForm = document.getElementById('type-form');

        async function renderTypes() {
            const listEl = document.getElementById('types-list');
            try {
                const { data } = await SM.api(cfg.typesEndpoint, { params: { all: 1 } });
                listEl.innerHTML = data.map((t) => `
                    <li class="py-3 flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium ${Number(t.is_active) ? '' : 'text-slate-400 line-through'}">${esc(t.name)}</p>
                            <p class="text-xs text-slate-500">${esc(t.description || '')}</p>
                        </div>
                        <button type="button" class="icon-btn" data-type-edit="${t.id}" aria-label="Edit ${esc(t.name)}">${SM.icon('pencil', 'w-4 h-4')}</button>
                        <button type="button" class="btn btn-sm ${Number(t.is_active) ? 'btn-secondary' : 'btn-success'}" data-type-toggle="${t.id}">${Number(t.is_active) ? 'Disable' : 'Enable'}</button>
                    </li>`).join('');
                listEl.querySelectorAll('[data-type-edit]').forEach((b) => b.addEventListener('click', () => {
                    const t = data.find((x) => String(x.id) === b.dataset.typeEdit);
                    SM.form.fill(typeForm, t);
                    document.getElementById('type-form-title').textContent = `Edit "${t.name}"`;
                    typeForm.elements.name.focus();
                }));
                listEl.querySelectorAll('[data-type-toggle]').forEach((b) => b.addEventListener('click', async () => {
                    const t = data.find((x) => String(x.id) === b.dataset.typeToggle);
                    try {
                        await SM.api(cfg.typesEndpoint, { method: 'POST', data: { id: t.id, name: t.name, description: t.description || '', is_active: !Number(t.is_active) } });
                        SM.toast(Number(t.is_active) ? 'Type disabled.' : 'Type enabled.', 'success');
                        renderTypes();
                        loadTypes();
                    } catch (err) { SM.handleError(err); }
                }));
            } catch (err) { SM.handleError(err); }
        }

        if (typesModal) {
            document.getElementById('btn-manage-types').addEventListener('click', () => {
                SM.form.reset(typeForm);
                document.getElementById('type-form-title').textContent = 'Add a type';
                SM.modal.open(typesModal);
                renderTypes();
            });
            typeForm.addEventListener('reset', () => {
                typeForm.elements.id.value = '';
                document.getElementById('type-form-title').textContent = 'Add a type';
            });
            typeForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!SM.form.validate(typeForm)) return;
                const btn = document.getElementById('type-submit');
                SM.setLoading(btn, true, 'Saving…');
                try {
                    const json = await SM.api(cfg.typesEndpoint, { method: 'POST', data: { ...SM.form.serialize(typeForm), is_active: true } });
                    SM.toast(json.message, 'success');
                    SM.form.reset(typeForm);
                    renderTypes();
                    loadTypes();
                } catch (err) { SM.handleError(err, typeForm); } finally { SM.setLoading(btn, false); }
            });
        }

        /* ---------- Init ---------- */

        document.addEventListener('DOMContentLoaded', async () => {
            const params = new URLSearchParams(location.search);
            SM.form.enableLiveValidation(form);
            SM.searchableSelect(document.getElementById('rec-subject-search'), subjectSelect);
            await loadTypes(); // so the type filter can be restored from the URL

            list = SM.listPage({
                endpoint: cfg.listEndpoint,
                table: '#records-table',
                filtersForm: '#record-filters',
                pagination: '#records-pagination',
                defaultSort: { sort: cfg.dateField, dir: 'desc' },
                emptyText: `No ${cfg.noun.toLowerCase()}s match your filters.`,
                renderRow,
                onLoaded: renderSummary,
            });

            document.getElementById('btn-add-record').addEventListener('click', () => openCreate());
            document.querySelectorAll('[data-result-filter]').forEach((b) => b.addEventListener('click', () => {
                const sel = document.getElementById('f-result');
                sel.value = sel.value === b.dataset.resultFilter ? '' : b.dataset.resultFilter;
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }));

            if (params.get('new')) openCreate(params.get(cfg.subjectQueryKey));
        });
    };
})();
