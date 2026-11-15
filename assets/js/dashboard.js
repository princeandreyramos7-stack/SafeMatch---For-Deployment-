/**
 * SafeMatch - Dashboard: loads live stats and renders charts (Chart.js).
 *
 * Colour roles:
 *  - Admissions trend: categorical series slots 1 & 2 (blue, orange) -
 *    identity only, with legend + tooltip so colour never stands alone.
 *  - Screening results and discharge readiness are *states*, so they use
 *    the reserved status palette (good / warning / critical) and are always
 *    labelled with text.
 */
(function () {
    'use strict';
    const esc = SM.escape;

    const COLORS = {
        series1: '#2a78d6',  // admissions
        series2: '#eb6834',  // births
        good: '#0ca30c',
        warning: '#fab219',
        critical: '#d03b3b',
        neutral: '#94a3b8',
        surface: '#ffffff',
        grid: '#eef2f6',
        text: '#52514e',
    };

    const charts = {};

    function baseOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 400 },
            plugins: {
                legend: { position: 'top', align: 'start', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, color: COLORS.text, font: { size: 12 } } },
                tooltip: { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8, titleFont: { weight: '600' } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: COLORS.text, font: { size: 11 } }, border: { color: '#e2e8f0' } },
                y: { beginAtZero: true, grid: { color: COLORS.grid }, ticks: { color: COLORS.text, precision: 0, font: { size: 11 } }, border: { display: false } },
            },
        };
    }

    function draw(id, config) {
        if (!window.Chart) return;
        if (charts[id]) charts[id].destroy();
        charts[id] = new Chart(document.getElementById(id), config);
    }

    function renderStats(stats) {
        Object.entries(stats).forEach(([key, value]) => {
            const el = document.querySelector(`[data-stat="${key}"]`);
            if (el) el.textContent = Number(value).toLocaleString();
        });
        const mism = document.querySelector('[data-stat-note="mismatches"]');
        if (mism) {
            mism.innerHTML = stats.mismatches_7d > 0
                ? `<span class="font-semibold text-red-600">${stats.mismatches_7d} mismatch${stats.mismatches_7d > 1 ? 'es' : ''}</span>`
                : '<span class="text-emerald-700">No mismatches</span>';
        }
        const ready = document.querySelector('[data-stat-note="ready"]');
        if (ready) ready.textContent = `${stats.ready_for_discharge} ready to go`;
        const births = document.querySelector('[data-stat-note="births"]');
        if (births) births.textContent = `${stats.todays_births} birth${stats.todays_births === 1 ? '' : 's'} today`;

        const alerts = [];
        if (stats.mismatches_7d > 0) alerts.push(['red', 'warning', `${stats.mismatches_7d} QR mismatch${stats.mismatches_7d > 1 ? 'es' : ''} in the last 7 days`, 'pages/activity-logs.php?action=QR_VERIFY_MISMATCH']);
        if (stats.abnormal_screenings > 0) alerts.push(['amber', 'badge', `${stats.abnormal_screenings} abnormal newborn screening${stats.abnormal_screenings > 1 ? 's' : ''}`, 'pages/screenings.php?result=Abnormal']);
        if (stats.assessments_attention > 0) alerts.push(['amber', 'document', `${stats.assessments_attention} maternal assessment${stats.assessments_attention > 1 ? 's' : ''} need attention (7 days)`, 'pages/assessments.php']);
        document.getElementById('alerts').innerHTML = alerts.map(([tone, icon, text, href]) => `
            <a href="${esc(SM.url(href))}" class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium ${tone === 'red' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-900'}">
                ${SM.icon(icon, 'w-5 h-5 shrink-0')}<span>${esc(text)}</span>
            </a>`).join('');
    }

    function renderAdmissions(trend) {
        const opts = baseOptions();
        opts.interaction = { mode: 'index', intersect: false }; // crosshair-style tooltip for all series
        draw('chart-admissions', {
            type: 'line',
            data: {
                labels: trend.map((d) => d.label),
                datasets: [
                    { label: 'Admissions', data: trend.map((d) => d.admissions), borderColor: COLORS.series1, backgroundColor: COLORS.series1, borderWidth: 2, pointRadius: 4, pointHoverRadius: 6, pointBorderColor: COLORS.surface, pointBorderWidth: 2, cubicInterpolationMode: 'monotone' },
                    { label: 'Births', data: trend.map((d) => d.births), borderColor: COLORS.series2, backgroundColor: COLORS.series2, borderWidth: 2, pointRadius: 4, pointHoverRadius: 6, pointBorderColor: COLORS.surface, pointBorderWidth: 2, cubicInterpolationMode: 'monotone' },
                ],
            },
            options: opts,
        });
        document.getElementById('table-admissions').innerHTML = `
            <table class="data-table"><thead><tr><th>Day</th><th>Admissions</th><th>Births</th></tr></thead>
            <tbody>${trend.map((d) => `<tr><td>${esc(d.label)}</td><td class="tabular-nums">${d.admissions}</td><td class="tabular-nums">${d.births}</td></tr>`).join('')}</tbody></table>`;
    }

    function renderReadiness(dist) {
        const items = [
            { key: 'green', label: 'Ready', color: COLORS.good },
            { key: 'yellow', label: 'In progress', color: COLORS.warning },
            { key: 'red', label: 'Not ready', color: COLORS.critical },
            { key: 'discharged', label: 'Discharged', color: COLORS.neutral },
        ];
        const opts = baseOptions();
        opts.indexAxis = 'y';
        opts.plugins.legend.display = false; // single measure: categories are labelled on the axis
        opts.scales.x = { beginAtZero: true, grid: { color: COLORS.grid }, ticks: { color: COLORS.text, precision: 0 }, border: { display: false } };
        opts.scales.y = { grid: { display: false }, ticks: { color: COLORS.text }, border: { color: '#e2e8f0' } };
        draw('chart-readiness', {
            type: 'bar',
            data: {
                labels: items.map((i) => i.label),
                datasets: [{
                    label: 'Pairs',
                    data: items.map((i) => dist[i.key] || 0),
                    backgroundColor: items.map((i) => i.color),
                    borderRadius: 4,
                    borderSkipped: 'start',
                    barThickness: 22,
                }],
            },
            options: opts,
        });
        document.getElementById('readiness-legend').innerHTML = items.map((i) => `
            <li class="flex items-center gap-2"><span class="light-dot" style="background:${i.color}" aria-hidden="true"></span>
            <span class="text-slate-600">${esc(i.label)}</span><span class="ml-auto font-semibold tabular-nums text-slate-800">${dist[i.key] || 0}</span></li>`).join('');
    }

    function renderScreenings(rows) {
        const opts = baseOptions();
        opts.scales.x.stacked = true;
        opts.scales.y.stacked = true;
        opts.interaction = { mode: 'index', intersect: false };
        const ds = (label, key, color) => ({
            label, data: rows.map((r) => r[key]), backgroundColor: color,
            borderColor: COLORS.surface, borderWidth: { top: 2 }, borderRadius: 4, borderSkipped: 'start', maxBarThickness: 48,
        });
        draw('chart-screenings', {
            type: 'bar',
            data: {
                labels: rows.map((r) => r.name),
                datasets: [ds('Normal', 'normal', COLORS.good), ds('Pending', 'pending', COLORS.warning), ds('Abnormal', 'abnormal', COLORS.critical)],
            },
            options: opts,
        });
        document.getElementById('table-screenings').innerHTML = `
            <table class="data-table"><thead><tr><th>Screening</th><th>Normal</th><th>Pending</th><th>Abnormal</th></tr></thead>
            <tbody>${rows.map((r) => `<tr><td>${esc(r.name)}</td><td class="tabular-nums">${r.normal}</td><td class="tabular-nums">${r.pending}</td><td class="tabular-nums">${r.abnormal}</td></tr>`).join('')}</tbody></table>`;
    }

    function renderRecentVerifications(items) {
        const list = document.getElementById('recent-verifications');
        list.innerHTML = items.length ? items.map((v) => `
            <li class="py-2.5 flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-slate-800 truncate">${esc(v.newborn_name)} <span class="text-slate-400 font-normal">→</span> ${esc(v.mother_name)}</p>
                    <p class="text-xs text-slate-500">${esc(v.newborn_id)} · ${esc(v.mother_id)} · ${esc(SM.fmt.relative(v.verified_at))}</p>
                </div>
                ${SM.badges.verification(v.verification_result)}
            </li>`).join('') : '<li class="py-6 text-center text-slate-500">No verifications yet.</li>';
    }

    function renderRecentAdmissions(items) {
        const tbody = document.querySelector('#recent-admissions tbody');
        tbody.innerHTML = items.length ? items.map((m) => `
            <tr>
                <td class="font-mono text-xs font-semibold text-primary-700">${esc(m.mother_id)}</td>
                <td class="font-medium">${esc(SM.fullName(m.first_name, m.last_name))}</td>
                <td class="whitespace-nowrap">${esc(SM.fmt.datetime(m.admission_date))}</td>
                <td>${esc(m.room_number || '—')}</td>
                <td class="tabular-nums">${esc(m.newborn_count)}</td>
            </tr>`).join('') : '<tr><td colspan="5" class="!py-8 text-center text-slate-500">No admissions yet. <a class="text-primary-700 font-medium underline" href="' + esc(SM.url('pages/mothers.php?new=1')) + '">Register the first mother</a>.</td></tr>';
    }

    async function load(passive = false) {
        try {
            const { data } = await SM.api('api/dashboard/stats.php', passive ? { params: { passive: 1 }, passive: true } : {});
            renderStats(data.stats);
            renderAdmissions(data.admissions_trend);
            renderReadiness(data.readiness_distribution);
            renderScreenings(data.screenings_by_type);
            renderRecentVerifications(data.recent_verifications);
            renderRecentAdmissions(data.recent_admissions);
        } catch (err) {
            SM.handleError(err);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        load();
        // Keep the numbers live while the dashboard is open.
        setInterval(() => { if (!document.hidden) load(true); }, 120000);
    });
})();
