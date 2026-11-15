/**
 * SafeMatch - Activity log viewer with filters and CSV export.
 */
(function () {
    'use strict';
    const esc = SM.escape;

    function actionBadge(action) {
        let tone = 'gray';
        if (/MISMATCH|FAILED|BLOCKED|DENIED|DELETE|DEACTIVATE|INVALID/.test(action)) tone = 'red';
        else if (/MATCH|DISCHARGE$|ACTIVATE/.test(action)) tone = 'green';
        else if (/CREATE|LOGIN$/.test(action)) tone = 'teal';
        else if (/UPDATE|RESET|CHANGE|EDIT/.test(action)) tone = 'yellow';
        return `<span class="badge badge-${tone} font-mono !text-[11px]">${esc(action)}</span>`;
    }

    function renderRow(l) {
        return `
            <tr>
                <td class="whitespace-nowrap text-sm">${esc(SM.fmt.datetime(l.created_at))}</td>
                <td class="whitespace-nowrap">${l.user_name ? esc(l.user_name) : '<span class="text-slate-400">System / unknown</span>'}</td>
                <td>${actionBadge(l.action)}</td>
                <td class="text-sm text-slate-700 min-w-[240px]">${esc(l.description || '')}</td>
                <td class="font-mono text-xs text-slate-500 whitespace-nowrap" title="${esc(l.user_agent || '')}">${esc(l.ip_address || '')}</td>
            </tr>`;
    }

    function updateExportLink() {
        const params = new URLSearchParams();
        new FormData(document.getElementById('log-filters')).forEach((v, k) => { if (v) params.set(k, v); });
        document.getElementById('btn-export').href = SM.url('api/activity-logs/export.php' + (params.toString() ? '?' + params : ''));
    }

    document.addEventListener('DOMContentLoaded', async () => {
        const userSelect = document.getElementById('f-user');
        if (userSelect) {
            try {
                const { data } = await SM.api('api/users/list.php', { params: { options: 1 } });
                SM.fillSelect(userSelect, data, 'id', (u) => u.name);
            } catch (err) { SM.handleError(err); }
        }

        SM.listPage({
            endpoint: 'api/activity-logs/list.php',
            table: '#logs-table',
            filtersForm: '#log-filters',
            pagination: '#logs-pagination',
            perPage: 25,
            defaultSort: { sort: 'created_at', dir: 'desc' },
            emptyText: 'No activity matches your filters.',
            renderRow,
            onLoaded: updateExportLink,
        });
    });
})();
