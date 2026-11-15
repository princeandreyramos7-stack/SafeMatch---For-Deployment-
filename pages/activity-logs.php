<?php
/**
 * SafeMatch - Activity logs (admins see all users; others see their own).
 */
$pageTitle = 'Activity Logs';
$activePage = 'activity';
$pageScripts = ['activity-logs.js'];
require __DIR__ . '/../templates/header.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
    <p class="text-sm text-slate-500"><?= is_admin() ? 'All system activity, including sign-ins, record changes and verifications.' : 'Your own activity in SafeMatch.' ?></p>
    <a href="#" id="btn-export" class="btn btn-secondary shrink-0"><?= icon('download', 'w-4 h-4') ?> Export CSV</a>
</div>

<div class="card">
    <form id="log-filters" class="p-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6 border-b border-slate-100" role="search">
        <div class="relative sm:col-span-2">
            <label for="f-q" class="sr-only">Search logs</label>
            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none"><?= icon('search', 'w-4 h-4') ?></span>
            <input id="f-q" name="q" type="search" class="form-input pl-9" placeholder="Search description or IP…" autocomplete="off">
        </div>
        <?php if (is_admin()): ?>
            <select name="user_id" id="f-user" class="form-input" aria-label="User"><option value="">All users</option></select>
        <?php endif; ?>
        <select name="action" id="f-action" class="form-input" aria-label="Action">
            <option value="">All actions</option>
            <option value="LOGIN">Logins</option>
            <option value="MOTHER_">Mother records</option>
            <option value="NEWBORN_">Newborn records</option>
            <option value="QR_">QR codes &amp; verification</option>
            <option value="QR_VERIFY_MISMATCH">Verification mismatches</option>
            <option value="SCREENING">Screenings</option>
            <option value="ASSESSMENT">Assessments</option>
            <option value="DISCHARGE">Discharge</option>
            <option value="USER_">User management</option>
        </select>
        <input name="date_from" type="date" class="form-input" aria-label="From date" title="From">
        <div class="flex gap-2">
            <input name="date_to" type="date" class="form-input" aria-label="To date" title="To">
            <button type="reset" class="btn btn-ghost shrink-0" aria-label="Clear filters" title="Clear filters"><?= icon('x', 'w-4 h-4') ?></button>
        </div>
    </form>
    <div class="table-wrap">
        <table class="data-table" id="logs-table">
            <thead>
            <tr>
                <th data-sort="created_at" data-default-dir="desc">Date / time</th>
                <th data-sort="user">User</th>
                <th data-sort="action">Action</th>
                <th>Description</th>
                <th>IP address</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div id="logs-pagination"></div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
