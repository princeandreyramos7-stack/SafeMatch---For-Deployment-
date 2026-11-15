/**
 * SafeMatch - core front-end library (vanilla JS)
 *
 * Exposes a global `SM` namespace used by every page script:
 *   SM.api()        fetch wrapper (CSRF header, JSON envelope, 401 handling)
 *   SM.toast()      auto-dismissing toast notifications
 *   SM.modal        open/close modals; SM.confirm() promise-based dialog
 *   SM.form         serialize / fill / validate / show server errors
 *   SM.listPage()   searchable, filterable, sortable, paginated table controller
 *   SM.fmt          date formatting helpers;  SM.badges  status badges
 * plus layout behaviour (sidebar, dropdowns, notifications, session timeout).
 */
(function () {
    'use strict';

    const CONFIG = window.SM_CONFIG || { baseUrl: '', csrfToken: '', sessionTimeout: 1800, user: {} };
    const ICONS = window.SM_ICONS || {};

    const SM = {
        config: CONFIG,
        url: (path) => CONFIG.baseUrl + '/' + String(path).replace(/^\/+/, ''),
    };

    /* ------------------------------------------------------------------
     * Utilities
     * ---------------------------------------------------------------- */

    /** Escapes a value for safe insertion into HTML. */
    SM.escape = function (value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    };
    const esc = SM.escape;

    SM.icon = function (name, cls = 'w-5 h-5') {
        const d = ICONS[name] || ICONS.document || '';
        return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="${esc(cls)}" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="${d}"/></svg>`;
    };

    SM.debounce = function (fn, wait = 350) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    };

    SM.qs = (sel, root = document) => root.querySelector(sel);
    SM.qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    /** Full name helper for rows with first/last name fields. */
    SM.fullName = (first, last, middle) => [first, middle, last].filter(Boolean).join(' ');

    /* ------------------------------------------------------------------
     * Formatting
     * ---------------------------------------------------------------- */

    function parseDate(value) {
        if (!value) return null;
        // MySQL "YYYY-MM-DD HH:MM:SS" -> local time
        const d = new Date(String(value).replace(' ', 'T'));
        return isNaN(d) ? null : d;
    }

    SM.fmt = {
        date(value) {
            const d = parseDate(value);
            return d ? d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '';
        },
        datetime(value) {
            const d = parseDate(value);
            return d ? d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';
        },
        time(value) {
            if (!value) return '';
            const [h, m] = String(value).split(':');
            const d = new Date();
            d.setHours(+h, +m, 0, 0);
            return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        },
        relative(value) {
            const d = parseDate(value);
            if (!d) return '';
            const diff = (Date.now() - d.getTime()) / 1000;
            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
            if (diff < 604800) return Math.floor(diff / 86400) + ' day(s) ago';
            return SM.fmt.date(value);
        },
        age(dob) {
            const d = parseDate(dob);
            if (!d) return '';
            const now = new Date();
            let age = now.getFullYear() - d.getFullYear();
            if (now < new Date(now.getFullYear(), d.getMonth(), d.getDate())) age--;
            return age;
        },
        /** Value for <input type="datetime-local"> from a MySQL datetime. */
        inputDateTime(value) {
            return value ? String(value).replace(' ', 'T').slice(0, 16) : '';
        },
        nowLocal() {
            const d = new Date();
            d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
            return d.toISOString().slice(0, 16);
        },
        today() {
            return SM.fmt.nowLocal().slice(0, 10);
        },
    };

    /* ------------------------------------------------------------------
     * Badges (status always shown as text + colour, never colour alone)
     * ---------------------------------------------------------------- */

    SM.badges = {
        result(result) {
            const map = { Normal: 'green', Abnormal: 'red', Pending: 'yellow', 'Needs Attention': 'yellow' };
            return `<span class="badge badge-${map[result] || 'gray'}">${esc(result)}</span>`;
        },
        discharge(status) {
            const map = {
                discharged: ['blue', 'Discharged'],
                ready: ['green', 'Ready'],
                not_ready: ['gray', 'Not ready'],
            };
            const [color, label] = map[status] || ['gray', status];
            return `<span class="badge badge-${color}">${esc(label)}</span>`;
        },
        light(light, percent) {
            const labels = { green: 'Ready', yellow: 'In progress', red: 'Not ready', discharged: 'Discharged' };
            const pct = percent !== undefined && light !== 'discharged' ? ` · ${Math.round(percent)}%` : '';
            return `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700"><span class="light-dot light-${esc(light)}" aria-hidden="true"></span>${esc(labels[light] || light)}${pct}</span>`;
        },
        verification(result) {
            if (!result) return '<span class="badge badge-gray">Not verified</span>';
            return result === 'match'
                ? `<span class="badge badge-green">${SM.icon('check', 'w-3.5 h-3.5')} Match</span>`
                : `<span class="badge badge-red">${SM.icon('x', 'w-3.5 h-3.5')} Mismatch</span>`;
        },
        role(role) {
            return role === 'admin'
                ? '<span class="badge badge-teal">Administrator</span>'
                : '<span class="badge badge-gray">Healthcare Personnel</span>';
        },
    };

    /* ------------------------------------------------------------------
     * Session timeout tracking
     * ---------------------------------------------------------------- */

    const session = {
        lastActivity: Date.now(),
        warned: false,
        touch() {
            this.lastActivity = Date.now();
            this.warned = false;
            SM.qs('#session-warning')?.remove();
        },
        secondsLeft() {
            return CONFIG.sessionTimeout - Math.floor((Date.now() - this.lastActivity) / 1000);
        },
    };
    SM.session = session;

    function expireSession() {
        window.location.href = SM.url('pages/login.php?timeout=1');
    }

    function checkSession() {
        if (!CONFIG.user || !CONFIG.user.id) return;
        const left = session.secondsLeft();
        if (left <= 0) {
            expireSession();
        } else if (left <= 120 && !session.warned) {
            session.warned = true;
            showSessionWarning();
        }
    }

    function showSessionWarning() {
        const container = SM.qs('#toast-container');
        if (!container) return;
        const el = document.createElement('div');
        el.id = 'session-warning';
        el.className = 'toast toast-warning';
        el.setAttribute('role', 'alert');
        el.innerHTML = `${SM.icon('clock', 'w-5 h-5 shrink-0')}
            <div class="flex-1"><p class="font-semibold">Session expiring soon</p>
            <p class="opacity-90">You will be logged out in under 2 minutes due to inactivity.</p>
            <button type="button" class="mt-2 px-3 py-1.5 rounded-lg bg-white/20 hover:bg-white/30 font-semibold">Stay signed in</button></div>`;
        el.querySelector('button').addEventListener('click', async () => {
            try {
                await SM.api('api/auth/keepalive.php', { method: 'POST' });
                SM.toast('Session extended.', 'success');
            } catch (_) { /* handled by api() */ }
        });
        container.appendChild(el);
    }

    /* ------------------------------------------------------------------
     * API wrapper
     * ---------------------------------------------------------------- */

    class ApiError extends Error {
        constructor(message, status, errors, payload) {
            super(message);
            this.status = status;
            this.errors = errors || {};
            this.payload = payload || {};
        }
    }
    SM.ApiError = ApiError;

    /**
     * Calls an API endpoint and returns the parsed JSON envelope.
     * @param {string} path     e.g. 'api/mothers/list.php'
     * @param {object} options  { method, params, data, formData, passive }
     * @throws {ApiError} when success=false or the request fails
     */
    SM.api = async function (path, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        let url = SM.url(path);
        if (options.params) {
            const qs = new URLSearchParams();
            Object.entries(options.params).forEach(([k, v]) => {
                if (v !== undefined && v !== null && v !== '') qs.append(k, v);
            });
            const s = qs.toString();
            if (s) url += (url.includes('?') ? '&' : '?') + s;
        }

        const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        let body;
        if (method !== 'GET') {
            headers['X-CSRF-Token'] = CONFIG.csrfToken;
            if (options.formData) {
                body = options.formData; // browser sets multipart boundary
            } else {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify(options.data || {});
            }
        }

        let response;
        try {
            response = await fetch(url, { method, headers, body, credentials: 'same-origin' });
        } catch (networkError) {
            throw new ApiError('Network error. Check your connection and try again.', 0);
        }

        let json;
        try {
            json = await response.json();
        } catch (_) {
            throw new ApiError(`Unexpected server response (${response.status}).`, response.status);
        }

        if (!options.passive) session.touch();

        if (response.status === 401 && CONFIG.user && CONFIG.user.id) {
            SM.toast(json.message || 'Your session has expired.', 'error');
            setTimeout(expireSession, 1200);
            throw new ApiError(json.message, 401);
        }
        if (!response.ok || json.success === false) {
            throw new ApiError(json.message || 'Request failed.', response.status, json.errors, json);
        }
        return json;
    };

    /** Shows the error from a failed API call as a toast (and returns it). */
    SM.handleError = function (err, form) {
        if (err && err.status === 401) return err;
        if (form && err && err.errors && Object.keys(err.errors).length) {
            SM.form.showErrors(form, err.errors);
        }
        SM.toast((err && err.message) || 'Something went wrong.', 'error', 5000);
        return err;
    };

    /* ------------------------------------------------------------------
     * Toasts
     * ---------------------------------------------------------------- */

    SM.toast = function (message, type = 'info', duration = 3000) {
        const container = SM.qs('#toast-container');
        if (!container) { alert(message); return; }
        const icons = { success: 'check', error: 'warning', info: 'bell', warning: 'warning' };
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');
        el.innerHTML = `${SM.icon(icons[type] || 'bell', 'w-5 h-5 shrink-0 mt-0.5')}
            <p class="flex-1">${esc(message)}</p>
            <button type="button" class="shrink-0 -mr-1 -mt-1 p-1 rounded hover:bg-white/20" aria-label="Dismiss">${SM.icon('x', 'w-4 h-4')}</button>`;
        const remove = () => {
            el.classList.add('leaving');
            setTimeout(() => el.remove(), 200);
        };
        el.querySelector('button').addEventListener('click', remove);
        container.appendChild(el);
        setTimeout(remove, duration);
    };

    /* ------------------------------------------------------------------
     * Buttons
     * ---------------------------------------------------------------- */

    SM.setLoading = function (button, loading, text) {
        if (!button) return;
        if (loading) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = `<span class="spinner" aria-hidden="true"></span><span>${esc(text || 'Please wait…')}</span>`;
        } else {
            button.disabled = false;
            if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
        }
    };

    /* ------------------------------------------------------------------
     * Modals
     * ---------------------------------------------------------------- */

    let lastFocused = null;
    SM.modal = {
        open(id) {
            const modal = typeof id === 'string' ? document.getElementById(id) : id;
            if (!modal) return;
            lastFocused = document.activeElement;
            modal.classList.remove('hidden');
            document.body.classList.add('modal-open');
            const focusable = modal.querySelector('[autofocus], input:not([type=hidden]):not([disabled]), select, textarea, button:not([data-modal-close])');
            setTimeout(() => focusable && focusable.focus(), 50);
        },
        close(id) {
            const modal = typeof id === 'string' ? document.getElementById(id) : id;
            if (!modal) return;
            modal.classList.add('hidden');
            if (!SM.qs('.modal:not(.hidden)')) document.body.classList.remove('modal-open');
            modal.dispatchEvent(new CustomEvent('modal:closed'));
            if (lastFocused && lastFocused.focus) lastFocused.focus();
        },
    };

    document.addEventListener('click', (e) => {
        const closer = e.target.closest('[data-modal-close]');
        if (closer) SM.modal.close(closer.closest('.modal'));
        const opener = e.target.closest('[data-modal-open]');
        if (opener) SM.modal.open(opener.dataset.modalOpen);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const open = SM.qsa('.modal:not(.hidden)');
        if (open.length) SM.modal.close(open[open.length - 1]);
    });

    /**
     * Promise-based confirmation dialog.
     * @returns {Promise<boolean>}
     */
    SM.confirm = function ({ title = 'Are you sure?', message = '', confirmText = 'Confirm', danger = true } = {}) {
        const modal = SM.qs('#confirm-modal');
        if (!modal) return Promise.resolve(window.confirm(message || title));
        SM.qs('#confirm-title', modal).textContent = title;
        SM.qs('#confirm-message', modal).textContent = message;
        const ok = SM.qs('[data-confirm-ok]', modal);
        const cancel = SM.qs('[data-confirm-cancel]', modal);
        ok.textContent = confirmText;
        ok.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');
        const iconWrap = SM.qs('#confirm-icon', modal);
        iconWrap.className = 'shrink-0 w-11 h-11 rounded-full flex items-center justify-center ' + (danger ? 'bg-red-100 text-red-600' : 'bg-primary-100 text-primary-700');

        return new Promise((resolve) => {
            const done = (value) => {
                ok.removeEventListener('click', onOk);
                cancel.removeEventListener('click', onCancel);
                modal.removeEventListener('modal:closed', onClosed);
                if (!modal.classList.contains('hidden')) SM.modal.close(modal);
                resolve(value);
            };
            const onOk = () => done(true);
            const onCancel = () => done(false);
            const onClosed = () => done(false);
            ok.addEventListener('click', onOk);
            cancel.addEventListener('click', onCancel);
            modal.addEventListener('modal:closed', onClosed);
            SM.modal.open(modal);
            setTimeout(() => cancel.focus(), 60);
        });
    };

    /* ------------------------------------------------------------------
     * Forms: serialize, fill, validate, server errors
     * ---------------------------------------------------------------- */

    const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    SM.form = {
        serialize(form) {
            const data = {};
            new FormData(form).forEach((value, key) => {
                if (value instanceof File) return;
                data[key] = typeof value === 'string' ? value.trim() : value;
            });
            SM.qsa('input[type=checkbox][name]', form).forEach((cb) => { data[cb.name] = cb.checked; });
            return data;
        },

        fill(form, values) {
            Object.entries(values || {}).forEach(([key, value]) => {
                const field = form.elements.namedItem(key);
                if (!field) return;
                if (field.type === 'checkbox') field.checked = !!Number(value) || value === true;
                else if (field.type === 'datetime-local') field.value = SM.fmt.inputDateTime(value);
                else if (field.type === 'date') field.value = value ? String(value).slice(0, 10) : '';
                else if (field.type === 'time') field.value = value ? String(value).slice(0, 5) : '';
                else field.value = value ?? '';
            });
        },

        reset(form) {
            form.reset();
            SM.form.clearErrors(form);
            SM.qsa('input[type=hidden][data-reset]', form).forEach((i) => { i.value = ''; });
        },

        clearErrors(form) {
            SM.qsa('.is-invalid', form).forEach((el) => el.classList.remove('is-invalid'));
            SM.qsa('.form-error[data-generated]', form).forEach((el) => el.remove());
        },

        setFieldError(field, message) {
            const container = field.closest('[data-field]') || field.parentElement;
            let msg = container.querySelector('.form-error[data-generated]');
            if (!message) {
                field.classList.remove('is-invalid');
                field.removeAttribute('aria-invalid');
                if (msg) msg.remove();
                return;
            }
            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');
            if (!msg) {
                msg = document.createElement('p');
                msg.className = 'form-error';
                msg.dataset.generated = '1';
                msg.setAttribute('role', 'alert');
                container.appendChild(msg);
            }
            msg.textContent = message;
        },

        /**
         * Validates one field from its HTML attributes. Returns the error, ''
         * when valid, or null when the field should be skipped (non-first radio).
         */
        checkField(field) {
            if (field.disabled || field.type === 'hidden' || field.type === 'file') return '';
            if (field.type === 'radio') {
                // A radio group is validated once, through its first input.
                const group = SM.qsa(`input[type=radio][name="${field.name}"]`, field.form);
                if (group[0] !== field) return null;
                const required = group.some((r) => r.required);
                return required && !group.some((r) => r.checked) ? `${field.dataset.label || 'This field'} is required.` : '';
            }
            const value = (field.value || '').trim();
            const label = field.dataset.label
                || (field.id && SM.qs(`label[for="${field.id}"]`)?.textContent.replace('*', '').trim())
                || 'This field';

            if (field.required && !value) return `${label} is required.`;
            if (!value) return '';
            if (field.type === 'email' && !EMAIL_RE.test(value)) return 'Enter a valid email address.';
            if (field.dataset.phone !== undefined && !/^\d{11}$/.test(value)) return `${label} must be exactly 11 digits.`;
            if (field.type === 'number') {
                const n = Number(value);
                if (Number.isNaN(n)) return `${label} must be a number.`;
                if (field.min !== '' && n < Number(field.min)) return `${label} must be at least ${field.min}.`;
                if (field.max !== '' && n > Number(field.max)) return `${label} must not exceed ${field.max}.`;
            }
            if (field.maxLength > 0 && value.length > field.maxLength) return `${label} is too long.`;
            if (field.minLength > 0 && value.length < field.minLength) return `${label} must be at least ${field.minLength} characters.`;
            if (field.dataset.match) {
                const other = field.form.elements.namedItem(field.dataset.match);
                if (other && other.value !== field.value) return 'Values do not match.';
            }
            if (field.dataset.notFuture !== undefined) {
                const d = new Date(value.length === 10 ? value + 'T00:00' : value);
                if (d.getTime() > Date.now() + 5 * 60000) return `${label} cannot be in the future.`;
            }
            return '';
        },

        /** Validates the whole form; focuses the first invalid field. */
        validate(form) {
            let firstInvalid = null;
            SM.qsa('input, select, textarea', form).forEach((field) => {
                const error = SM.form.checkField(field);
                if (error === null) return;
                SM.form.setFieldError(field, error);
                if (error && !firstInvalid) firstInvalid = field;
            });
            if (firstInvalid) {
                firstInvalid.focus();
                return false;
            }
            return true;
        },

        /** Real-time feedback: validate on blur, re-validate on input once invalid. */
        enableLiveValidation(form) {
            const recheck = (f) => {
                if (f.type === 'radio') f = SM.qs(`input[type=radio][name="${f.name}"]`, form);
                const error = SM.form.checkField(f);
                if (error !== null) SM.form.setFieldError(f, error);
            };
            form.addEventListener('blur', (e) => {
                if (e.target.matches('input:not([type=radio]), select, textarea')) recheck(e.target);
            }, true);
            form.addEventListener('input', (e) => {
                const f = e.target;
                if (f.dataset.phone !== undefined) f.value = f.value.replace(/\D/g, '').slice(0, 11);
                if (f.classList.contains('is-invalid') || f.type === 'radio') recheck(f);
            });
            form.addEventListener('change', (e) => { if (e.target.type === 'radio') recheck(e.target); });
            form.setAttribute('novalidate', 'novalidate');
        },

        /** Displays server-side validation errors {field: [messages]}. */
        showErrors(form, errors) {
            let first = null;
            Object.entries(errors || {}).forEach(([name, messages]) => {
                const field = form.elements.namedItem(name);
                if (field && field.nodeType === 1) {
                    SM.form.setFieldError(field, [].concat(messages)[0]);
                    if (!first) first = field;
                }
            });
            if (first) first.focus();
        },
    };

    /* ------------------------------------------------------------------
     * URL state (filters survive refresh / can be bookmarked)
     * ---------------------------------------------------------------- */

    SM.urlState = {
        read() {
            return Object.fromEntries(new URLSearchParams(window.location.search));
        },
        write(state) {
            const qs = new URLSearchParams();
            Object.entries(state).forEach(([k, v]) => {
                if (v !== undefined && v !== null && v !== '' && !(k === 'page' && Number(v) === 1)) qs.set(k, v);
            });
            const s = qs.toString();
            history.replaceState(null, '', window.location.pathname + (s ? '?' + s : ''));
        },
    };

    /* ------------------------------------------------------------------
     * Pagination
     * ---------------------------------------------------------------- */

    SM.renderPagination = function (container, p, onChange) {
        if (!container) return;
        if (!p || p.total === 0) { container.innerHTML = ''; return; }
        const from = (p.current_page - 1) * p.per_page + 1;
        const to = Math.min(p.total, p.current_page * p.per_page);
        const pages = [];
        const add = (n) => { if (!pages.includes(n) && n >= 1 && n <= p.total_pages) pages.push(n); };
        add(1);
        for (let i = p.current_page - 1; i <= p.current_page + 1; i++) add(i);
        add(p.total_pages);
        pages.sort((a, b) => a - b);

        let buttons = '';
        let prev = 0;
        pages.forEach((n) => {
            if (n - prev > 1) buttons += '<span class="px-1 text-slate-400 hidden sm:inline">…</span>';
            const active = n === p.current_page;
            buttons += `<button type="button" data-page="${n}" class="hidden sm:inline-flex min-w-[40px] h-10 items-center justify-center rounded-lg text-sm font-medium ${active ? 'bg-primary-500 text-white' : 'text-slate-600 hover:bg-slate-100'}" ${active ? 'aria-current="page"' : ''}>${n}</button>`;
            prev = n;
        });

        container.innerHTML = `
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-slate-100">
                <p class="text-sm text-slate-500">Showing <span class="font-medium text-slate-700">${from}–${to}</span> of <span class="font-medium text-slate-700">${p.total}</span></p>
                <div class="flex items-center gap-1">
                    <button type="button" data-page="${p.current_page - 1}" class="btn btn-secondary btn-sm" ${p.current_page <= 1 ? 'disabled' : ''}>Prev</button>
                    ${buttons}
                    <span class="sm:hidden text-sm text-slate-600 px-2">Page ${p.current_page} / ${p.total_pages}</span>
                    <button type="button" data-page="${p.current_page + 1}" class="btn btn-secondary btn-sm" ${p.current_page >= p.total_pages ? 'disabled' : ''}>Next</button>
                </div>
            </div>`;
        SM.qsa('button[data-page]', container).forEach((b) => {
            b.addEventListener('click', () => {
                if (!b.disabled) onChange(Number(b.dataset.page));
            });
        });
    };

    /* ------------------------------------------------------------------
     * List page controller
     * ---------------------------------------------------------------- */

    /**
     * Wires a filter form + sortable table + pagination to a list endpoint.
     * Filter inputs are the named fields of `filtersForm`; text inputs are
     * debounced, selects/dates apply immediately. State is mirrored to the URL.
     */
    SM.listPage = function (opts) {
        const table = SM.qs(opts.table);
        const tbody = table.querySelector('tbody');
        const filtersForm = opts.filtersForm ? SM.qs(opts.filtersForm) : null;
        const paginationEl = opts.pagination ? SM.qs(opts.pagination) : null;
        const columns = table.querySelectorAll('thead th').length;
        const fromUrl = opts.syncUrl === false ? {} : SM.urlState.read();

        const state = {
            page: Number(fromUrl.page) || 1,
            sort: fromUrl.sort || opts.defaultSort?.sort || '',
            dir: fromUrl.dir || opts.defaultSort?.dir || 'desc',
            filters: {},
        };
        let requestId = 0;

        if (filtersForm) {
            SM.qsa('[name]', filtersForm).forEach((el) => {
                if (fromUrl[el.name] !== undefined) el.value = fromUrl[el.name];
                state.filters[el.name] = el.value;
            });
            const apply = () => {
                SM.qsa('[name]', filtersForm).forEach((el) => { state.filters[el.name] = el.value.trim(); });
                state.page = 1;
                load();
            };
            const debounced = SM.debounce(apply, 350);
            filtersForm.addEventListener('input', (e) => {
                if (e.target.matches('input[type=search], input[type=text]')) debounced();
            });
            filtersForm.addEventListener('change', (e) => {
                if (!e.target.matches('input[type=search], input[type=text]')) apply();
            });
            filtersForm.addEventListener('submit', (e) => { e.preventDefault(); apply(); });
            filtersForm.addEventListener('reset', () => setTimeout(apply, 0));
        }

        // Sortable headers
        const headers = SM.qsa('th[data-sort]', table);
        const paintSort = () => headers.forEach((th) => {
            th.classList.remove('sort-asc', 'sort-desc');
            th.removeAttribute('aria-sort');
            if (th.dataset.sort === state.sort) {
                th.classList.add(state.dir === 'asc' ? 'sort-asc' : 'sort-desc');
                th.setAttribute('aria-sort', state.dir === 'asc' ? 'ascending' : 'descending');
            }
        });
        headers.forEach((th) => {
            th.tabIndex = 0;
            const toggle = () => {
                if (state.sort === th.dataset.sort) state.dir = state.dir === 'asc' ? 'desc' : 'asc';
                else { state.sort = th.dataset.sort; state.dir = th.dataset.defaultDir || 'asc'; }
                state.page = 1;
                load();
            };
            th.addEventListener('click', toggle);
            th.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
        });

        function renderMessage(html) {
            tbody.innerHTML = `<tr><td colspan="${columns}" class="!py-12 text-center text-sm text-slate-500">${html}</td></tr>`;
        }

        function renderLoading() {
            const rows = Array.from({ length: 4 }, () =>
                `<tr>${Array.from({ length: columns }, () => '<td><div class="skeleton h-4 w-full max-w-[140px]"></div></td>').join('')}</tr>`
            ).join('');
            tbody.innerHTML = rows;
        }

        async function load() {
            const id = ++requestId;
            paintSort();
            renderLoading();
            const params = { ...(opts.baseParams || {}), ...state.filters, page: state.page, sort: state.sort, dir: state.dir, per_page: opts.perPage };
            if (opts.syncUrl !== false) {
                SM.urlState.write({ ...state.filters, page: state.page, sort: state.sort !== opts.defaultSort?.sort ? state.sort : '', dir: state.sort !== opts.defaultSort?.sort || state.dir !== opts.defaultSort?.dir ? state.dir : '' });
            }
            try {
                const json = await SM.api(opts.endpoint, { params });
                if (id !== requestId) return; // a newer request superseded this one
                if (!json.data.length) {
                    renderMessage(esc(opts.emptyText || 'No records found.'));
                } else {
                    tbody.innerHTML = json.data.map(opts.renderRow).join('');
                }
                SM.renderPagination(paginationEl, json.pagination, (page) => { state.page = page; load(); });
                if (opts.onLoaded) opts.onLoaded(json);
            } catch (err) {
                if (id !== requestId) return;
                renderMessage(`<span class="text-red-600">${esc(err.message)}</span> <button type="button" class="ml-2 text-primary-700 font-medium underline" data-retry>Retry</button>`);
                tbody.querySelector('[data-retry]')?.addEventListener('click', load);
            }
        }

        // Row action delegation: <button data-action="edit" data-id="1">
        if (opts.actions) {
            tbody.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-action]');
                if (!btn) return;
                const handler = opts.actions[btn.dataset.action];
                if (handler) handler(btn.dataset.id, btn, e);
            });
        }

        load();
        return { reload: load, state };
    };

    /** Small helper to build row action buttons. */
    SM.actionButton = function (action, id, icon, label, extraClass = '') {
        return `<button type="button" class="icon-btn ${extraClass}" data-action="${esc(action)}" data-id="${esc(id)}" title="${esc(label)}" aria-label="${esc(label)}">${SM.icon(icon, 'w-5 h-5')}</button>`;
    };

    /** Fills a <select> with options (keeps the first placeholder option). */
    SM.fillSelect = function (select, items, valueKey, labelFn, keepFirst = true) {
        const first = keepFirst && select.options[0] && select.options[0].value === '' ? select.options[0].outerHTML : '';
        select.innerHTML = first + items.map((it) => `<option value="${esc(it[valueKey])}">${esc(labelFn(it))}</option>`).join('');
    };

    /**
     * Adds a filter box above a long <select> so staff can find a patient quickly.
     */
    SM.searchableSelect = function (input, select) {
        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            let firstMatch = null;
            Array.from(select.options).forEach((opt) => {
                if (!opt.value) return;
                const show = !term || opt.textContent.toLowerCase().includes(term);
                opt.hidden = !show;
                if (show && !firstMatch) firstMatch = opt;
            });
            if (term && firstMatch && (select.selectedOptions[0]?.hidden || !select.value)) {
                select.value = firstMatch.value;
                select.dispatchEvent(new Event('change'));
            }
        });
    };

    /* ------------------------------------------------------------------
     * Layout: sidebar, dropdowns, logout, notifications
     * ---------------------------------------------------------------- */

    function initSidebar() {
        const sidebar = SM.qs('#sidebar');
        const overlay = SM.qs('#sidebar-overlay');
        if (!sidebar) return;
        const open = () => { sidebar.classList.remove('-translate-x-full'); overlay.classList.remove('hidden'); };
        const close = () => { sidebar.classList.add('-translate-x-full'); overlay.classList.add('hidden'); };
        SM.qs('#sidebar-open')?.addEventListener('click', open);
        SM.qs('#sidebar-close')?.addEventListener('click', close);
        overlay?.addEventListener('click', close);
        window.addEventListener('resize', () => { if (window.innerWidth >= 1024) overlay.classList.add('hidden'); });
    }

    function initDropdown(buttonSel, panelSel, onOpen) {
        const button = SM.qs(buttonSel);
        const panel = SM.qs(panelSel);
        if (!button || !panel) return;
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            const willOpen = panel.classList.contains('hidden');
            SM.qsa('#user-menu, #notif-panel').forEach((p) => p.classList.add('hidden'));
            panel.classList.toggle('hidden', !willOpen);
            button.setAttribute('aria-expanded', String(willOpen));
            if (willOpen && onOpen) onOpen();
        });
        document.addEventListener('click', (e) => {
            if (!panel.contains(e.target) && !button.contains(e.target)) {
                panel.classList.add('hidden');
                button.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function initLogout() {
        SM.qsa('[data-logout]').forEach((btn) => btn.addEventListener('click', async () => {
            try {
                const json = await SM.api('api/auth/logout.php', { method: 'POST' });
                window.location.href = json.data.redirect;
            } catch (_) {
                window.location.href = SM.url('pages/login.php');
            }
        }));
    }

    const notifColors = { success: 'bg-emerald-500', error: 'bg-red-500', warning: 'bg-amber-500', info: 'bg-blue-500' };

    async function loadNotifications() {
        try {
            const json = await SM.api('api/notifications/list.php', { passive: true });
            const { items, unread } = json.data;
            const badge = SM.qs('#notif-count');
            if (badge) {
                badge.textContent = unread > 99 ? '99+' : unread;
                badge.classList.toggle('hidden', unread === 0);
            }
            const list = SM.qs('#notif-list');
            if (!list) return;
            list.innerHTML = items.length ? items.map((n) => `
                <li>
                    <button type="button" data-notif-id="${n.id}" class="w-full text-left px-4 py-3 flex gap-3 hover:bg-slate-50 ${Number(n.is_read) ? 'opacity-70' : ''}">
                        <span class="mt-1.5 w-2 h-2 rounded-full shrink-0 ${notifColors[n.type] || 'bg-slate-400'}" aria-hidden="true"></span>
                        <span class="min-w-0">
                            <span class="block font-medium text-slate-800">${esc(n.title)} ${Number(n.is_read) ? '' : '<span class="sr-only">(unread)</span>'}</span>
                            <span class="block text-slate-600 text-xs mt-0.5 break-words">${esc(n.message)}</span>
                            <span class="block text-slate-400 text-[11px] mt-1">${esc(SM.fmt.relative(n.created_at))}</span>
                        </span>
                    </button>
                </li>`).join('') : '<li class="px-4 py-6 text-center text-slate-500">No notifications yet.</li>';
        } catch (_) { /* silent: polling */ }
    }

    function initNotifications() {
        if (!SM.qs('#notif-button')) return;
        initDropdown('#notif-button', '#notif-panel', loadNotifications);
        SM.qs('#notif-list').addEventListener('click', async (e) => {
            const item = e.target.closest('[data-notif-id]');
            if (!item) return;
            try {
                await SM.api('api/notifications/mark-read.php', { method: 'POST', data: { id: item.dataset.notifId } });
                loadNotifications();
            } catch (err) { SM.handleError(err); }
        });
        SM.qs('#notif-mark-all').addEventListener('click', async () => {
            try {
                await SM.api('api/notifications/mark-read.php', { method: 'POST', data: { all: 1 } });
                loadNotifications();
            } catch (err) { SM.handleError(err); }
        });
        loadNotifications();
        setInterval(loadNotifications, 60000);
    }

    document.addEventListener('DOMContentLoaded', () => {
        initSidebar();
        initDropdown('#user-menu-button', '#user-menu');
        initLogout();
        initNotifications();
        if (CONFIG.user && CONFIG.user.id) setInterval(checkSession, 15000);
    });

    window.SM = SM;
})();
