document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-tenant-plan-form').forEach(function (form) {
        form.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        form.addEventListener('submit', function (event) {
            event.stopPropagation();
        });
    });

    document.querySelectorAll('.js-confirm-action').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const message = form.getAttribute('data-confirm');

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const filterForm = document.querySelector('.js-dashboard-filters');

    if (filterForm) {
        let searchDebounceTimer = null;

        filterForm.querySelectorAll('input[type="date"], select').forEach(function (field) {
            field.addEventListener('change', function () {
                filterForm.submit();
            });
        });

        const searchField = filterForm.querySelector('input[type="search"]');

        if (searchField) {
            searchField.addEventListener('input', function () {
                window.clearTimeout(searchDebounceTimer);
                searchDebounceTimer = window.setTimeout(function () {
                    filterForm.submit();
                }, 350);
            });

            searchField.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    window.clearTimeout(searchDebounceTimer);
                    filterForm.submit();
                }
            });
        }
    }

    const bulkForm = document.getElementById('bulk-status-form');
    const tenantCheckboxes = Array.from(document.querySelectorAll('.js-tenant-select'));

    if (!bulkForm || tenantCheckboxes.length === 0) {
        return;
    }

    const selectAllCheckbox = document.querySelector('.js-select-all-tenants');
    const selectionCount = document.querySelector('.js-bulk-selection-count');
    const bulkStatusSelect = document.querySelector('.js-bulk-status-select');
    const bulkStatusHidden = document.querySelector('.js-bulk-status-hidden');
    const bulkSubmitButton = document.querySelector('.js-bulk-status-submit');

    const statusLabels = {
        active: 'odobriti',
        suspended: 'suspendirati',
        pending: 'vratiti na čekanje',
    };

    function selectedCheckboxes() {
        return tenantCheckboxes.filter(function (checkbox) {
            return checkbox.checked;
        });
    }

    function updateBulkControls() {
        const selected = selectedCheckboxes();
        const count = selected.length;
        const hasSelection = count > 0;

        if (selectionCount) {
            selectionCount.textContent = count + ' odabrano';
        }

        if (bulkStatusSelect) {
            bulkStatusSelect.disabled = !hasSelection;
        }

        if (bulkSubmitButton) {
            bulkSubmitButton.disabled = !hasSelection;
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.indeterminate = count > 0 && count < tenantCheckboxes.length;
            selectAllCheckbox.checked = count > 0 && count === tenantCheckboxes.length;
        }
    }

    tenantCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateBulkControls);
    });

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            tenantCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateBulkControls();
        });
    }

    if (bulkSubmitButton && bulkStatusSelect && bulkStatusHidden) {
        bulkSubmitButton.addEventListener('click', function () {
            const selected = selectedCheckboxes();

            if (selected.length === 0) {
                return;
            }

            const status = bulkStatusSelect.value;
            const actionLabel = statusLabels[status] || 'ažurirati';
            const message = 'Želiš ' + actionLabel + ' ' + selected.length + ' tenanata?';

            if (!window.confirm(message)) {
                return;
            }

            bulkStatusHidden.value = status;
            bulkForm.submit();
        });
    }

    updateBulkControls();
});
