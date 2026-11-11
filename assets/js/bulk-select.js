/* ============================================================
   VEHISAFE — BULK SELECT
   Wires a checkbox column + bulk action bar.
   ============================================================ */

(function () {
    'use strict';

    var table      = document.querySelector('[data-bulk-table]');
    var bulkBar    = document.querySelector('[data-bulk-bar]');
    var countEl    = document.querySelector('[data-bulk-count]');
    var selectAll  = document.querySelector('[data-bulk-select-all]');
    var form       = document.querySelector('[data-bulk-form]');
    var actionSel  = form ? form.querySelector('select[name="new_status"]') : null;
    var actionBtn  = form ? form.querySelector('button[type="submit"]') : null;

    if (!table || !bulkBar || !form) return;

    function getCheckboxes() {
        return Array.prototype.slice.call(
            table.querySelectorAll('input[type="checkbox"][data-row-check]')
        );
    }

    function updateBar() {
        var boxes = getCheckboxes();
        var checked = boxes.filter(function (b) { return b.checked; });
        if (countEl) countEl.textContent = checked.length;
        bulkBar.style.display = checked.length > 0 ? '' : 'none';

        if (selectAll) {
            selectAll.checked = checked.length > 0 && checked.length === boxes.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < boxes.length;
        }
    }

    // Select-all toggle
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            getCheckboxes().forEach(function (b) { b.checked = selectAll.checked; });
            updateBar();
        });
    }

    // Per-row toggle
    table.addEventListener('change', function (e) {
        if (e.target && e.target.matches('input[type="checkbox"][data-row-check]')) {
            updateBar();
        }
    });

    // Before submit, inject checked IDs into the form
    form.addEventListener('submit', function (e) {
        var checked = getCheckboxes().filter(function (b) { return b.checked; });
        if (checked.length === 0) {
            e.preventDefault();
            return;
        }
        if (!actionSel || !actionSel.value) {
            e.preventDefault();
            alert('Please select an action.');
            return;
        }
        // Clear any previously injected inputs
        form.querySelectorAll('input[name="violation_ids[]"]').forEach(function (n) { n.remove(); });
        // Inject hidden inputs for each checked row
        checked.forEach(function (b) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'violation_ids[]';
            inp.value = b.value;
            form.appendChild(inp);
        });
    });

    updateBar();
})();