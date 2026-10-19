/**
 * table-filter.js
 *
 * Client-side search + filter for all data tables.
 *
 * Usage: add data attributes to the <table>:
 *   data-filterable — enables this script
 *   data-search-col="0,1,2"  — comma-separated column indices to search
 *   data-filter-col="2" — column index for the type/status dropdown
 *
 */

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('table[data-filterable]').forEach(function (table) {
        var wrap = table.closest('.table-wrap');
        if (!wrap) return;

        var searchInput = wrap.querySelector('[data-search-input]');
        var filterSelect = wrap.querySelector('[data-filter-select]');
        var countEl = wrap.querySelector('[data-filter-count]');
        var rows = Array.from(table.querySelectorAll('tbody tr'));
        var searchCols = (table.dataset.searchCol || '0').split(',').map(Number);
        var filterCol = table.dataset.filterCol !== undefined ? parseInt(table.dataset.filterCol) : null;
        var debounceTimer;

        function getCell(row, colIndex) {
            return (row.cells[colIndex]?.textContent || '').toLowerCase().trim();
        }

        function applyFilter() {
            var query = (searchInput?.value || '').toLowerCase().trim();
            var filterVal = (filterSelect?.value || '').toLowerCase().trim();
            var visible = 0;

            rows.forEach(function (row) {
                var matchesSearch = !query || searchCols.some(function (col) {
                    return getCell(row, col).includes(query);
                });
                var matchesFilter = !filterVal || filterCol === null ||
                    getCell(row, filterCol).includes(filterVal);

                var show = matchesSearch && matchesFilter;
                // Fade instead of snap
                row.classList.toggle('table-row-faded', !show);
                row.classList.toggle('d-none', !show);
                if (show) visible++;
            });

            if (countEl) {
                countEl.textContent = visible + ' of ' + rows.length + ' result' + (rows.length !== 1 ? 's' : '');
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(applyFilter, 200);
            });
        }
        if (filterSelect) filterSelect.addEventListener('change', applyFilter);

        applyFilter();
    });
});
