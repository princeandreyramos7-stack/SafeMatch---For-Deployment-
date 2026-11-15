/**
 * SafeMatch - Maternal assessments page (uses the shared records controller).
 */
(function () {
    'use strict';
    const esc = SM.escape;

    SM.recordsPage({
        noun: 'Assessment',
        listEndpoint: 'api/assessments/list.php',
        createEndpoint: 'api/assessments/create.php',
        typesEndpoint: 'api/assessments/types.php',
        subjectEndpoint: 'api/mothers/list.php',
        subjectParams: { options: 1, status: 'admitted' },
        subjectQueryKey: 'mother',
        subjectLabel: (m) => `${m.mother_id} — ${SM.fullName(m.first_name, m.last_name)}${m.room_number ? ' (Room ' + m.room_number + ')' : ''}`,
        dateField: 'assessment_date',
        byField: 'assessed_by_name',
        renderSubject: (r) => `
            <a class="font-medium text-slate-900 hover:text-primary-700" href="${esc(SM.url('pages/mothers.php?view=' + r.mother_pk))}">${esc(r.mother_name)}</a>
            <p class="font-mono text-xs text-slate-500">${esc(r.mother_id)}${r.room_number ? ' · Room ' + esc(r.room_number) : ''}</p>`,
    });
})();
