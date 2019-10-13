/**
 * Table Rates
 */
var wcsdmTableRates = {
    params: {},
    errorId: 'wcsdm-errors-rate-fields',
    init: function (params) {
        wcsdmTableRates.params = params;

        // Show advanced rate form
        $(document).off('click', '.wcsdm-link--advanced-rate', wcsdmTableRates.showAdvancedRateForm);
        $(document).on('click', '.wcsdm-link--advanced-rate', wcsdmTableRates.showAdvancedRateForm);

        // Close advanced rate form
        $(document).off('click', '#wcsdm-btn--cancel-advanced', wcsdmTableRates.closeAdvancedRateForm);
        $(document).on('click', '#wcsdm-btn--cancel-advanced', wcsdmTableRates.closeAdvancedRateForm);

        // Apply advanced rate
        $(document).off('click', '#wcsdm-btn--apply-advanced', wcsdmTableRates.applyAdvancedForm);
        $(document).on('click', '#wcsdm-btn--apply-advanced', wcsdmTableRates.applyAdvancedForm);

        // Add rate row
        $(document).off('click', '#wcsdm-btn--add-rate', wcsdmTableRates.handleAddRateButton);
        $(document).on('click', '#wcsdm-btn--add-rate', wcsdmTableRates.handleAddRateButton);

        // Delete rate row
        $(document).off('click', '#wcsdm-btn--delete-rate-select', wcsdmTableRates.deleteRateRow);
        $(document).on('click', '#wcsdm-btn--delete-rate-select', wcsdmTableRates.deleteRateRow);

        // Cancel delete rate row
        $(document).off('click', '#wcsdm-btn--delete-rate-cancel', wcsdmTableRates.deleteRateRowCancel);
        $(document).on('click', '#wcsdm-btn--delete-rate-cancel', wcsdmTableRates.deleteRateRowCancel);

        // Confirm delete rate row
        $(document).off('click', '#wcsdm-btn--delete-rate-confirm', wcsdmTableRates.deleteRateRowConfirm);
        $(document).on('click', '#wcsdm-btn--delete-rate-confirm', wcsdmTableRates.deleteRateRowConfirm);

        // Toggle selected rows
        $(document).off('change', '#wcsdm-table--table_rates--dummy thead .select-item', wcsdmTableRates.toggleRows);
        $(document).on('change', '#wcsdm-table--table_rates--dummy thead .select-item', wcsdmTableRates.toggleRows);

        // Toggle selected row
        $(document).off('change', '#wcsdm-table--table_rates--dummy tbody .select-item', wcsdmTableRates.toggleRow);
        $(document).on('change', '#wcsdm-table--table_rates--dummy tbody .select-item', wcsdmTableRates.toggleRow);

        // Handle change event dummy rate field
        $(document).off('input', '.wcsdm-field--context--dummy:not(a)');
        $(document).on('input', '.wcsdm-field--context--dummy:not(a)', debounce(function (e) {
            wcsdmTableRates.handleRateFieldDummy(e);
        }, 500));

        // Toggle selected row
        $(document).off('change', '#woocommerce_wcsdm_distance_unit', wcsdmTableRates.initForm);
        $(document).on('change', '#woocommerce_wcsdm_distance_unit', wcsdmTableRates.initForm);

        wcsdmTableRates.initForm();

        if (!$('#wcsdm-table--table_rates--dummy tbody tr').length) {
            wcsdmTableRates.addRateRow();
        }
    },
    initForm: function () {
        $('.wcsdm-field-key--total_cost_type > option[value="formula"]').prop('disabled', !wcsdmTableRates.params.isPro);

        var distanceUnitSelected = $('#woocommerce_wcsdm_distance_unit').val();
        var $distanceUnitFields = $('#woocommerce_wcsdm_distance_unit').data('fields');

        var label = $distanceUnitFields && _.has($distanceUnitFields.label, distanceUnitSelected) ? $distanceUnitFields.label[distanceUnitSelected] : '';

        if (label && label.length) {
            _.each($distanceUnitFields.targets, function (target) {
                $(target).text(label);
            });
        }
    },
    handleAddRateButton: function (e) {
        e.preventDefault();
        $(e.currentTarget).prop('disabled', true);

        wcsdmTableRates.addRateRow();

        $(e.currentTarget).prop('disabled', false);
    },
    handleRateFieldDummy: function (e) {
        e.preventDefault();

        var $field = $(e.target);
        var $row = $field.closest('tr');
        $row.find('.wcsdm-field--context--hidden[data-id=' + $field.data('id') + ']').val(e.target.value);

        if ($field.hasClass('wcsdm-field--context--dummy--max_distance')) {
            $row.addClass('editing');
            $field.off('blur', wcsdmTableRates.sortRateRows);
            $field.on('blur', wcsdmTableRates.sortRateRows);
        }
    },
    showAdvancedRateForm: function (e) {
        e.preventDefault();

        $('#wcsdm-table--table_rates--dummy .select-item').prop('checked', false);
        $('#wcsdm-table--table_rates--dummy tbody tr').removeClass();

        var $row = $(e.currentTarget).closest('tr').addClass('editing');

        $row.find('.wcsdm-field--context--hidden').each(function (index, field) {
            $('.wcsdm-field--context--advanced[data-id=' + $(field).data('id') + ']').val($(field).val());
        });

        toggleBottons({
            left: {
                id: 'cancel-advanced',
                label: 'Cancel',
                icon: 'undo'
            },
            right: {
                id: 'apply-advanced',
                label: 'Apply Changes',
                icon: 'editor-spellcheck'
            }
        });

        $('.modal-close-link').hide();

        $('#wcsdm-field-group-wrap--advanced_rate').fadeIn().siblings('.wcsdm-field-group-wrap').hide();
    },
    applyAdvancedForm: function (e) {
        e.preventDefault();

        $('.wcsdm-field--context--advanced').each(function (index, field) {
            var fieldId = $(field).data('id');
            $('#wcsdm-table--table_rates--dummy tbody tr.editing .wcsdm-field--context--dummy[data-id=' + fieldId + ']:not(a)').val($(field).val());
            $('#wcsdm-table--table_rates--dummy tbody tr.editing .wcsdm-field--context--hidden[data-id=' + fieldId + ']:not(a)').val($(field).val());
        });

        wcsdmTableRates.closeAdvancedRateForm(e);
    },
    closeAdvancedRateForm: function (e) {
        e.preventDefault();
        $('#wcsdm-field-group-wrap--advanced_rate').hide().siblings('.wcsdm-field-group-wrap').not('.wcsdm-hidden').fadeIn();
        toggleBottons();
        $('.modal-close-link').show();
        wcsdmTableRates.scrollToTableRate();
        wcsdmTableRates.sortRateRows();
    },
    highlightRow: function () {
        var $row = $('#wcsdm-table--table_rates--dummy tbody tr.editing');
        if ($row.length) {
            $row.addClass('highlighted');
            setTimeout(function () {
                $row.removeClass('highlighted');
            }, 1500);
        }
        $('#wcsdm-table--table_rates--dummy tbody tr').removeClass('editing');
    },
    addRateRow: function () {
        var $lastRow = $('#wcsdm-table--table_rates--dummy tbody tr:last-child');

        $('#wcsdm-table--table_rates--dummy tbody').append(wp.template('wcsdm-dummy-row'));

        if ($lastRow) {
            $lastRow.find('.wcsdm-field--context--hidden:not(a)').each(function (index, field) {
                var fieldId = $(field).data('id');
                var fieldValue = fieldId === 'woocommerce_wcsdm_max_distance' ? Math.ceil((parseInt($(field).val(), 10) * 1.8)) : $(field).val();
                $('#wcsdm-table--table_rates--dummy tbody tr:last-child .wcsdm-field[data-id=' + fieldId + ']').val(fieldValue);
            });
        }

        wcsdmTableRates.scrollToTableRate();

        wcsdmTableRates.initForm();
    },
    deleteRateRow: function (e) {
        e.preventDefault();

        $('#wcsdm-table--table_rates--dummy tbody .select-item:not(:checked)').closest('tr').hide();
        $('#wcsdm-table--table_rates--dummy').find('.wcsdm-col--select-item, .wcsdm-col--link_advanced').hide();
        $('#wcsdm-field-group-wrap--table_rates').siblings().hide();

        wcsdmTableRates.scrollToTableRate();

        toggleBottons({
            left: {
                id: 'delete-rate-cancel',
                label: 'Cancel',
                icon: 'undo'
            },
            right: {
                id: 'delete-rate-confirm',
                label: 'Confirm Delete',
                icon: 'trash'
            }
        });
    },
    deleteRateRowCancel: function (e) {
        e.preventDefault();

        $('#wcsdm-table--table_rates--dummy tbody tr').show();
        $('#wcsdm-table--table_rates--dummy').find('.wcsdm-col--select-item, .wcsdm-col--link_advanced').show();
        $('#wcsdm-field-group-wrap--table_rates').siblings().not('.wcsdm-hidden').fadeIn();

        wcsdmTableRates.scrollToTableRate();

        toggleBottons({
            left: {
                id: 'delete-rate-select',
                label: 'Delete Selected Rates',
                icon: 'trash'
            }
        });
    },
    deleteRateRowConfirm: function (e) {
        e.preventDefault();

        $('#wcsdm-table--table_rates--dummy tbody .select-item:checked').closest('tr').remove();
        $('#wcsdm-table--table_rates--dummy tbody tr').show();
        $('#wcsdm-table--table_rates--dummy').find('.wcsdm-col--select-item, .wcsdm-col--link_advanced').show();
        $('#wcsdm-table--table_rates--dummy .select-item').prop('checked', false);
        $('#wcsdm-field-group-wrap--table_rates').siblings().not('.wcsdm-hidden').fadeIn();

        toggleBottons();

        if (!$('#wcsdm-table--table_rates--dummy tbody tr').length) {
            wcsdmTableRates.addRateRow();
        }
    },
    toggleRows: function (e) {
        e.preventDefault();

        $.each($('#wcsdm-table--table_rates--dummy tbody tr'), function (index, row) {
            wcsdmTableRates.toggleRowSelected($(row), $(e.target).is(':checked'));
        });

        if ($(e.target).is(':checked')) {
            toggleBottons({
                left: {
                    id: 'delete-rate-select',
                    label: 'Delete Selected Rates',
                    icon: 'trash'
                }
            });
        } else {
            toggleBottons();
        }
    },
    toggleRow: function (e) {
        e.preventDefault();

        var $field = $(e.target);
        var $row = $(e.target).closest('tr');

        wcsdmTableRates.toggleRowSelected($row, $field.is(':checked'));

        if ($('#wcsdm-table--table_rates--dummy tbody .select-item:checked').length) {
            toggleBottons({
                left: {
                    id: 'delete-rate-select',
                    label: 'Delete Selected Rates',
                    icon: 'trash'
                }
            });
        } else {
            toggleBottons();
        }

        var isBulkChecked = $('#wcsdm-table--table_rates--dummy tbody .select-item').length === $('#wcsdm-table--table_rates--dummy tbody .select-item:checked').length;

        $('#wcsdm-table--table_rates--dummy thead .select-item').prop('checked', isBulkChecked);
    },
    toggleRowSelected: function ($row, isChecked) {
        $row.find('.wcsdm-field--context--dummy').prop('disabled', isChecked);

        if (isChecked) {
            $row.addClass('selected').find('.select-item').prop('checked', isChecked);
            $row.find('a').css('opacity', '0.4');
        } else {
            $row.removeClass('selected').find('.select-item').prop('checked', isChecked);
            $row.find('a').css('opacity', '1');
        }
    },
    sortRateRows: function () {
        var rows = $('#wcsdm-table--table_rates--dummy > tbody > tr').get().sort(function (a, b) {
            var valueADistance = $(a).find('.wcsdm-field--context--dummy--max_distance').val();
            var valueBDistance = $(b).find('.wcsdm-field--context--dummy--max_distance').val();

            if (isNaN(valueADistance) || !valueADistance.length) {
                return 2;
            }

            valueADistance = parseInt(valueADistance, 10);
            valueBDistance = parseInt(valueBDistance, 10);

            if (valueADistance < valueBDistance) {
                return -1;
            }

            if (valueADistance > valueBDistance) {
                return 1;
            }

            return 0;
        });

        $.each(rows, function (index, row) {
            var $row = $(row).hide();
            $('#wcsdm-table--table_rates--dummy').children('tbody').append(row);
            $row.fadeIn('slow');
        });

        setTimeout(function () {
            wcsdmTableRates.highlightRow();
        }, 100);
    },
    scrollToTableRate: function () {
        $('.wc-modal-shipping-method-settings').scrollTop($('.wc-modal-shipping-method-settings').find('form').outerHeight());
    }
};
