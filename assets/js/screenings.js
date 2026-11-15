/**
 * SafeMatch - Newborn screenings page (uses the shared records controller).
 */
(function () {
    'use strict';
    const esc = SM.escape;

    SM.recordsPage({
        noun: 'Screening',
        listEndpoint: 'api/screenings/list.php',
        createEndpoint: 'api/screenings/create.php',
        typesEndpoint: 'api/screenings/types.php',
        subjectEndpoint: 'api/newborns/list.php',
        subjectParams: { options: 1, exclude_discharged: 1 },
        subjectQueryKey: 'newborn',
        subjectLabel: (n) => `${n.newborn_id} — ${n.display_name} (mother ${n.mother_id})`,
        dateField: 'screening_date',
        byField: 'performed_by_name',
        renderSubject: (r) => `
            <a class="font-medium text-slate-900 hover:text-primary-700" href="${esc(SM.url('pages/newborns.php?view=' + r.newborn_pk))}">${esc(r.newborn_name)}</a>
            <p class="font-mono text-xs text-slate-500">${esc(r.newborn_id)} · ${esc(r.mother_id)}</p>`,
    });
})();
