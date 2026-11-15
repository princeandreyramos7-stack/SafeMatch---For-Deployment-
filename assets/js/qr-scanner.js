/**
 * SafeMatch - QR verification (html5-qrcode)
 *
 * Scan the mother's band, then the baby's. As soon as both are read the
 * server compares them and returns MATCH / MISMATCH. Fallbacks: scan a
 * photo, or type the ID.
 */
(function () {
    'use strict';
    const esc = SM.escape;
    const el = (id) => document.getElementById(id);

    const state = { step: 1, mother: null, newborn: null };
    let scanner = null;
    let scanning = false;
    let busy = false;
    let lastReject = { text: '', at: 0 };

    /* ---------------- Parsing ---------------- */

    function parseCode(text) {
        const raw = String(text || '').trim();
        try {
            const obj = JSON.parse(raw);
            if (obj && obj.type && obj.id) {
                const details = obj.type === 'mother'
                    ? [obj.dob && `DOB ${obj.dob}`, obj.admission && `Admitted ${obj.admission}`]
                    : [obj.sex, obj.dob && `Born ${obj.dob}`, obj.mother_id && `Mother ${obj.mother_id}`];
                return { raw, type: obj.type, id: obj.id, name: obj.name, details: details.filter(Boolean), manual: false };
            }
        } catch (_) { /* not JSON */ }
        const m = raw.toUpperCase().match(/^([MN])\d{7,}$/);
        if (m) return { raw: raw.toUpperCase(), type: m[1] === 'M' ? 'mother' : 'newborn', id: raw.toUpperCase(), name: 'Typed ID', details: [], manual: true };
        return null;
    }

    /* ---------------- Rendering ---------------- */

    function bandCard(kind, code, active, done) {
        const isMother = kind === 'mother';
        const label = isMother ? 'Mother' : 'Baby';
        const tone = isMother ? 'primary' : 'pink';
        const icon = isMother ? 'user' : 'heart';
        let cls = 'border-slate-200 bg-white';
        let badge = `<span class="w-9 h-9 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center">${SM.icon(icon, 'w-5 h-5')}</span>`;
        let body = '<p class="text-sm text-slate-400">Waiting…</p>';
        if (active) {
            cls = isMother ? 'border-primary-400 ring-4 ring-primary-100 bg-white' : 'border-pink-400 ring-4 ring-pink-100 bg-white';
            badge = `<span class="w-9 h-9 rounded-xl ${isMother ? 'bg-primary-100 text-primary-700' : 'bg-pink-100 text-pink-700'} flex items-center justify-center animate-pulse">${SM.icon(icon, 'w-5 h-5')}</span>`;
            body = `<p class="text-sm font-medium ${isMother ? 'text-primary-700' : 'text-pink-700'}">Scan now</p>`;
        }
        if (done && code) {
            cls = 'border-emerald-300 bg-emerald-50';
            badge = `<span class="w-9 h-9 rounded-xl bg-emerald-500 text-white flex items-center justify-center">${SM.icon('check', 'w-5 h-5')}</span>`;
            body = `<p class="text-sm font-semibold text-slate-900 truncate">${esc(code.name)}</p>
                    <p class="font-mono text-xs text-slate-600">${esc(code.id)}</p>
                    ${code.manual ? '<p class="text-[11px] text-amber-700 mt-0.5">Typed manually</p>' : ''}`;
        }
        return `
            <div class="rounded-2xl border-2 p-3 sm:p-4 transition-all ${cls}">
                <div class="flex items-center gap-3">
                    ${badge}
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">${label} band</p>
                        ${body}
                    </div>
                </div>
            </div>`;
    }

    function paintBands() {
        el('bands').innerHTML =
            bandCard('mother', state.mother, state.step === 1, !!state.mother) +
            bandCard('newborn', state.newborn, state.step === 2, !!state.newborn);
    }

    function paintScan() {
        const isMother = state.step === 1;
        el('scan-title').textContent = isMother ? "Scan the mother's band" : "Now scan the baby's band";
        el('scan-help').textContent = isMother
            ? "It's on the mother's wristband."
            : "It's on the baby's ankle or wrist band.";
        const icon = el('scan-icon');
        icon.className = 'shrink-0 w-10 h-10 rounded-xl flex items-center justify-center ' + (isMother ? 'bg-primary-100 text-primary-700' : 'bg-pink-100 text-pink-700');
        icon.innerHTML = SM.icon(isMother ? 'user' : 'heart', 'w-5 h-5');
        el('manual-code').placeholder = isMother ? 'e.g. M' + new Date().getFullYear() + '001' : 'e.g. N' + new Date().getFullYear() + '001';
        el('manual-code').value = '';
    }

    function refresh() {
        const showResult = state.step === 3;
        el('scan-panel').classList.toggle('hidden', showResult);
        el('result-panel').classList.toggle('hidden', !showResult);
        el('btn-reset').classList.toggle('hidden', showResult || (!state.mother && !state.newborn));
        paintBands();
        if (!showResult) paintScan();
    }

    function feedback(ok) {
        try { if (navigator.vibrate) navigator.vibrate(ok ? 80 : [80, 60, 80]); } catch (_) { /* ignore */ }
    }

    /* ---------------- Handling a decoded code ---------------- */

    function reject(text, message, type = 'warning') {
        // The camera decodes many frames per second; show each problem once.
        const now = Date.now();
        if (lastReject.text === text && now - lastReject.at < 3000) return;
        lastReject = { text, at: now };
        feedback(false);
        SM.toast(message, type, 3500);
    }

    function handleCode(text) {
        if (busy || state.step > 2) return;
        const code = parseCode(text);
        if (!code) return reject(String(text), 'That is not a SafeMatch QR code.', 'error');

        const expected = state.step === 1 ? 'mother' : 'newborn';
        if (code.type !== expected) {
            return reject(String(text), expected === 'mother'
                ? "That is the baby's band. Scan the MOTHER's band first."
                : "That is the mother's band. Now scan the BABY's band.");
        }
        feedback(true);
        if (expected === 'mother') {
            state.mother = code;
            state.step = 2;
            refresh();
        } else {
            state.newborn = code;
            stopCamera();
            refresh();
            verify();
        }
    }

    /* ---------------- Camera ---------------- */

    async function startCamera(deviceId) {
        if (!window.Html5Qrcode) {
            SM.toast('The scanner failed to load. Use "Scan a photo" or type the ID.', 'error', 5000);
            return;
        }
        if (!window.isSecureContext) {
            SM.toast('The camera needs HTTPS (or localhost). Use "Scan a photo" or type the ID.', 'warning', 6000);
            return;
        }
        try {
            if (!scanner) scanner = new Html5Qrcode('qr-reader', { verbose: false });
            if (scanning) await scanner.stop();
            const camera = deviceId ? { deviceId: { exact: deviceId } } : { facingMode: 'environment' };
            await scanner.start(
                camera,
                { fps: 10, qrbox: (w, h) => { const s = Math.floor(Math.min(w, h) * 0.7); return { width: s, height: s }; } },
                handleCode,
                () => { /* "no code in this frame" */ }
            );
            scanning = true;
            el('camera-placeholder').classList.add('hidden');
            el('camera-live').classList.remove('hidden');
            populateCameras();
        } catch (err) {
            scanning = false;
            SM.toast(String(err && (err.name || err)).includes('NotAllowed')
                ? 'Camera permission was denied. Allow it in your browser, or use another option.'
                : 'Could not start the camera. Use "Scan a photo" or type the ID.', 'error', 5000);
        }
    }

    async function stopCamera() {
        if (scanner && scanning) {
            try { await scanner.stop(); } catch (_) { /* already stopped */ }
        }
        scanning = false;
        el('camera-placeholder').classList.remove('hidden');
        el('camera-live').classList.add('hidden');
    }

    async function populateCameras() {
        const select = el('camera-select');
        if (select.options.length) return;
        try {
            const cams = await Html5Qrcode.getCameras();
            if (cams.length > 1) {
                select.innerHTML = cams.map((c, i) => `<option value="${esc(c.id)}">${esc(c.label || 'Camera ' + (i + 1))}</option>`).join('');
                select.classList.remove('hidden');
            }
        } catch (_) { /* no permission to list cameras */ }
    }

    async function scanFile(file) {
        if (!file) return;
        if (!window.Html5Qrcode) { SM.toast('The scanner failed to load.', 'error'); return; }
        try {
            if (scanning) await stopCamera();
            if (!scanner) scanner = new Html5Qrcode('qr-reader', { verbose: false });
            handleCode(await scanner.scanFile(file, false));
        } catch (_) {
            SM.toast('No QR code found in that photo. Try again with the code centred and in focus.', 'error', 4500);
        } finally {
            el('qr-file').value = '';
        }
    }

    /* ---------------- Verification ---------------- */

    async function verify() {
        if (!(state.mother && state.newborn) || busy) return;
        busy = true;
        showChecking();
        try {
            const json = await SM.api('api/qr/verify.php', {
                method: 'POST',
                data: { mother_qr: state.mother.raw, newborn_qr: state.newborn.raw, notes: el('verify-notes').value },
            });
            showResult(json.data, json.message);
        } catch (err) {
            showError(err);
        } finally {
            busy = false;
        }
    }

    function showChecking() {
        state.step = 3;
        el('scan-panel').classList.add('hidden');
        el('result-panel').classList.remove('hidden');
        el('btn-reset').classList.add('hidden');
        el('result-panel').innerHTML = `
            <div class="card p-10 text-center">
                <span class="spinner !w-8 !h-8 text-primary-600" aria-hidden="true"></span>
                <p class="mt-3 font-semibold text-slate-800">Verifying pair…</p>
            </div>`;
    }

    function checksList(checks, open) {
        const failed = checks.filter((c) => !c.passed).length;
        return `
            <details class="mt-4 rounded-xl bg-white/70" ${open ? 'open' : ''}>
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-700">
                    ${failed ? `${failed} of ${checks.length} checks failed` : `All ${checks.length} checks passed`}
                </summary>
                <ul class="px-4 pb-3 space-y-2 text-sm">
                    ${checks.map((c) => `
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 shrink-0 ${c.passed ? 'text-emerald-600' : 'text-red-600'}">${SM.icon(c.passed ? 'check' : 'x', 'w-4 h-4')}</span>
                            <span><span class="font-medium text-slate-800">${esc(c.label)}</span>${c.detail ? `<span class="block text-xs ${c.passed ? 'text-slate-500' : 'text-red-700'}">${esc(c.detail)}</span>` : ''}<span class="sr-only">${c.passed ? ' (passed)' : ' (failed)'}</span></span>
                        </li>`).join('')}
                </ul>
            </details>`;
    }

    function showResult(r, message) {
        message = String(message).replace(/^(MATCH|MISMATCH) - /, '');
        state.step = 3;
        const match = r.result === 'match';
        feedback(match);
        el('result-panel').innerHTML = `
            <div class="rounded-2xl border-2 p-5 sm:p-7 text-center ${match ? 'result-match' : 'result-mismatch'}" role="alert">
                <span class="mx-auto w-20 h-20 rounded-full flex items-center justify-center text-white ${match ? 'bg-emerald-500' : 'bg-red-500'}">${SM.icon(match ? 'check' : 'x', 'w-11 h-11')}</span>
                <p class="mt-4 text-3xl sm:text-4xl font-extrabold tracking-tight ${match ? 'text-emerald-800' : 'text-red-800'}">${match ? 'MATCH' : 'MISMATCH'}</p>
                <p class="mt-1 text-sm sm:text-base ${match ? 'text-emerald-900' : 'text-red-900 font-semibold'}">${esc(message)}</p>

                <div class="mt-5 grid grid-cols-2 gap-3 text-left text-sm">
                    <div class="rounded-xl bg-white/80 p-3"><p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Mother</p><p class="font-semibold text-slate-900">${esc(r.mother.name)}</p><p class="font-mono text-xs text-slate-600">${esc(r.mother.mother_id)}${r.mother.room_number ? ' · Room ' + esc(r.mother.room_number) : ''}</p></div>
                    <div class="rounded-xl bg-white/80 p-3"><p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Baby</p><p class="font-semibold text-slate-900">${esc(r.newborn.name)}</p><p class="font-mono text-xs text-slate-600">${esc(r.newborn.newborn_id)} · ${esc(r.newborn.sex)}</p></div>
                </div>
                ${r.manual ? `<p class="mt-3 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-left">An ID was typed, not scanned, so band signatures were not checked. Confirm identity visually.</p>` : ''}
                <div class="text-left">${checksList(r.checks, !match)}</div>
            </div>
            <div class="mt-4 flex flex-col-reverse sm:flex-row gap-2 sm:justify-center">
                <button type="button" class="btn btn-secondary" data-restart>${SM.icon('refresh', 'w-4 h-4')} Verify another pair</button>
                ${match
                    ? `<a class="btn btn-primary" href="${esc(SM.url('pages/discharge.php?newborn=' + r.newborn.id))}">${SM.icon('exit', 'w-4 h-4')} Open discharge checklist</a>`
                    : `<button type="button" class="btn btn-danger" data-restart>${SM.icon('qr', 'w-4 h-4')} Re-scan the bands</button>`}
            </div>`;
        bindRestart();
        refresh();
    }

    function showError(err) {
        state.step = 3;
        feedback(false);
        el('result-panel').innerHTML = `
            <div class="rounded-2xl border-2 p-6 text-center result-mismatch" role="alert">
                <span class="mx-auto w-20 h-20 rounded-full flex items-center justify-center text-white bg-red-500">${SM.icon('warning', 'w-11 h-11')}</span>
                <p class="mt-4 text-2xl font-extrabold text-red-800">CANNOT VERIFY</p>
                <p class="mt-1 text-sm text-red-900">${esc(err.message)}</p>
                <p class="mt-3 text-sm font-semibold text-red-900">Do not release the baby until the pair is verified.</p>
            </div>
            <div class="mt-4 flex flex-col-reverse sm:flex-row gap-2 sm:justify-center">
                <button type="button" class="btn btn-secondary" data-restart>Start over</button>
                ${err.status === 0 || err.status >= 500 ? `<button type="button" class="btn btn-primary" id="btn-retry">${SM.icon('refresh', 'w-4 h-4')} Try again</button>` : ''}
            </div>`;
        bindRestart();
        el('btn-retry')?.addEventListener('click', verify);
        refresh();
    }

    function bindRestart() {
        el('result-panel').querySelectorAll('[data-restart]').forEach((b) => b.addEventListener('click', reset));
    }

    function reset() {
        state.step = 1;
        state.mother = null;
        state.newborn = null;
        lastReject = { text: '', at: 0 };
        el('verify-notes').value = '';
        el('result-panel').innerHTML = '';
        refresh();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /* ---------------- Init ---------------- */

    document.addEventListener('DOMContentLoaded', () => {
        el('btn-start-camera').addEventListener('click', () => startCamera());
        el('btn-stop-camera').addEventListener('click', stopCamera);
        el('camera-select').addEventListener('change', (e) => startCamera(e.target.value));
        el('qr-file').addEventListener('change', (e) => scanFile(e.target.files[0]));
        el('btn-reset').addEventListener('click', reset);

        const manualForm = el('manual-form');
        const toggle = el('btn-manual-toggle');
        toggle.addEventListener('click', () => {
            const open = manualForm.classList.toggle('hidden') === false;
            toggle.setAttribute('aria-expanded', String(open));
            if (open) el('manual-code').focus();
        });
        manualForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const value = el('manual-code').value.trim();
            if (value) handleCode(value);
        });

        window.addEventListener('beforeunload', () => { if (scanning) scanner.stop().catch(() => {}); });
        refresh();
    });
})();
