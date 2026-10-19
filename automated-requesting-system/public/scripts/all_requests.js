/**
 * all_requests.js for all_requests.php
 */

const inProgress = [ 'submitted', 'supervisor_reviewed', 'department_checked', 'checker_approved', 'final_approved' ];
function filterByDept(sel) {
    const val = sel.value.toLowerCase();
    document.querySelectorAll('table[data-filterable] tbody tr').forEach(function(row) {
        if (!val) { row.classList.remove('d-none'); return; }
        row.classList.toggle('d-none', (row.dataset.dept || '').toLowerCase() !== val);
    });
}
function filterByStatusAll(sel) {
    const val = sel.value;
    document.querySelectorAll('table[data-filterable] tbody tr').forEach(function(row) {
        const s = row.dataset.status || '';
        if (!val) { row.classList.remove('d-none'); return; }
        const show = val === 'in_progress' ? inProgress.includes(s) : s === val;
        row.classList.toggle('d-none', !show);
    });
}

(function () {
    var inApproval = [ 'submitted', 'checker_approved', 'process_approved', 'department_reviewed', 'finance_reviewed', 'final_approved'];

    function refilter() {
        var deptVal = (document.getElementById('deptFilter')?.value || '').toLowerCase();
        var statusVal = (document.getElementById('statusFilterAll')?.value || '').toLowerCase();
        document.querySelectorAll('table[data-filterable] tbody tr').forEach(function (row) {
            var dept = (row.dataset.dept || '').toLowerCase();
            var status = (row.dataset.status || '');
            var deptOk = !deptVal || dept === deptVal;
            var statOk = !statusVal;
            if (!statOk) {
                if (statusVal === 'in_approval') statOk = inApproval.includes(status);
                else if (statusVal === 'completed') statOk = (status === 'completed' || status === 'final_approved');
                else statOk = status === statusVal;
            }
            row.classList.toggle('d-none', !(deptOk && statOk));
        });
    }

    document.getElementById('deptFilter')?.addEventListener('change', refilter);
    document.getElementById('statusFilterAll')?.addEventListener('change', refilter);
})();
