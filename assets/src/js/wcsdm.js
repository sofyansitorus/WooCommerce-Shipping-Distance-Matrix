var rowIndex;
var rowScrollTop = 0;

var wcsdmTableRates = {
    init: function (params) {
        wcsdmTableRates.params = params;

        // Show advanced row
        $(document).on('click', '.wcsdm-rate-field--dummy--advanced_link', wcsdmTableRates.showAdvancedForm);

        // Hide advanced row
        $(document).on('click', '#wcsdm-btn-advanced-cancel', wcsdmTableRates.hideAdvancedForm);

        // Apply advanced row
        $(document).on('click', '#wcsdm-btn-advanced-apply', wcsdmTableRates.applyAdvancedForm);

        // Add rate row
        $(document).on('click', '#wcsdm-btn-add-rate', wcsdmTableRates.addRateRow);

        // Delete rate row
        $(document).on('click', '#wcsdm-btn-delete-rate', wcsdmTableRates.deleteRateRow);

        // Cancel delete rate row
        $(document).on('click', '#wcsdm-btn-delete-rate-cancel', wcsdmTableRates.deleteRateRowCancel);

        // Confirm delete rate row
        $(document).on('click', '#wcsdm-btn-delete-rate-confirm', wcsdmTableRates.deleteRateRowConfirm);

        // Toggle selected rows
        $(document).on('change', '#wcsdm-table-dummy thead .select-item', wcsdmTableRates.toggleSelectedRows);

        // Toggle selected row
        $(document).on('change', '#wcsdm-table-dummy tbody .select-item', wcsdmTableRates.toggleSelectedRow);

        // Handle change event dummy rate field
        $(document).on('input change', '.wcsdm-rate-field--dummy', wcsdmTableRates.handleRateFieldDummy);

        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons());

        if (!$('#wcsdm-table-dummy tbody tr').length) {
            $('#wcsdm-btn-add-rate').trigger('click');
        }
    },
    handleRateFieldDummy: function (e) {
        e.preventDefault();

        var $field = $(e.target);
        $field.closest('tr').find('.wcsdm-rate-field--hidden[data-id=' + $field.data('id') + ']').val(e.target.value);
    },
    showAdvancedForm: function (e) {
        e.preventDefault();

        var $row = $(e.currentTarget).closest('tr');

        rowIndex = $row.index();
        rowScrollTop = Math.abs($row.closest('form').position().top);

        $row.find('.wcsdm-rate-field--hidden').each(function (index, field) {
            var $field = $(field);
            $('#' + $field.data('id')).val($field.val());
        });

        $('#wcsdm-table-dummy .select-item').prop('checked', false);
        $('#wcsdm-table-dummy tbody tr').removeClass();
        $('#wcsdm-row-advanced').show().siblings().hide();
        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons('advanced'));
    },
    hideAdvancedForm: function (e) {
        e.preventDefault();

        $('#wcsdm-table-dummy tbody tr:eq(' + rowIndex + ')').addClass('applied');

        $('#wcsdm-row-advanced').hide().siblings().show();

        $('.wc-modal-shipping-method-settings').animate({
            scrollTop: rowScrollTop
        }, 500, function () {
            setTimeout(function () {
                $('#wcsdm-table-dummy tbody tr').removeClass('applied');
            }, 400);
        });
        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons());
    },
    applyAdvancedForm: function (e) {
        e.preventDefault();

        $('.wcsdm-rate-field--advanced').each(function (index, field) {
            var fieldId = $(field).attr('id');
            $('#wcsdm-table-dummy tbody tr:eq(' + rowIndex + ') .wcsdm-rate-field--dummy[data-id=' + fieldId + ']').val($(field).val());
            $('#wcsdm-table-dummy tbody tr:eq(' + rowIndex + ') .wcsdm-rate-field--hidden[data-id=' + fieldId + ']').val($(field).val());
        });

        $('#wcsdm-table-dummy tbody tr:eq(' + rowIndex + ')').addClass('applied');

        $('#wcsdm-row-advanced').hide().siblings().show();

        $('.wc-modal-shipping-method-settings').animate({
            scrollTop: rowScrollTop
        }, 500, function () {
            setTimeout(function () {
                $('#wcsdm-table-dummy tbody tr').removeClass('applied');
            }, 400);
        });
        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons());
    },
    addRateRow: function (e) {
        if (e) {
            e.preventDefault();
        }

        $('#wcsdm-table-dummy tbody').append(wp.template('wcsdm-dummy-row')).find('tr:last-child .wcsdm-rate-field--dummy--max_distance').focus();
        $('.wc-modal-shipping-method-settings').scrollTop($('.wc-modal-shipping-method-settings').find('form').outerHeight());
    },
    deleteRateRow: function (e) {
        e.preventDefault();

        $('#wcsdm-table-dummy tbody .select-item:not(:checked)').closest('tr').addClass('hidden');
        $('#wcsdm-table-dummy tbody .wcsdm-rate-field--dummy').prop('disabled', true);
        $('#wcsdm-table-dummy tbody .select-item:checked').closest('tr').addClass('deleted');
        $('.wcsdm-col--select-item, .wcsdm-col--advanced_link').hide();
        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons('confirm_delete_rate'));
    },
    deleteRateRowCancel: function (e) {
        e.preventDefault();

        $('#wcsdm-table-dummy tbody tr').removeClass('hidden deleted');
        $('#wcsdm-table-dummy tbody .wcsdm-rate-field--dummy').prop('disabled', false);
        $('.wcsdm-col--select-item, .wcsdm-col--advanced_link').show();
        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons('delete_rate'));
    },
    deleteRateRowConfirm: function (e) {
        e.preventDefault();

        $('#wcsdm-table-dummy tbody .select-item:checked').closest('tr').remove();
        $('#wcsdm-table-dummy tbody tr').removeClass('hidden');
        $('#wcsdm-table-dummy thead .select-item').prop('checked', false);
        $('#wcsdm-table-dummy tbody .wcsdm-rate-field--dummy').prop('disabled', false);
        $('.wcsdm-col--select-item, .wcsdm-col--advanced_link').show();
        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons());

        if (!$('#wcsdm-table-dummy tbody tr').length) {
            wcsdmTableRates.addRateRow();
        }
    },
    toggleSelectedRows: function (e) {
        e.preventDefault();

        if ($(e.target).is(':checked')) {
            $('#wcsdm-table-dummy tbody tr').removeClass('hidden deleted').addClass('selected').find('.select-item').prop('checked', true);
            wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons('delete_rate'));
        } else {
            $('#wcsdm-table-dummy tbody tr').removeClass('hidden deleted').removeClass('selected').find('.select-item').prop('checked', false);
            wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons());
        }
    },
    toggleSelectedRow: function (e) {
        e.preventDefault();

        var $field = $(e.target);
        var $row = $field.closest('tr');

        if ($field.is(':checked')) {
            $row.addClass('selected');
        } else {
            $row.removeClass('selected');
        }

        if ($('#wcsdm-table-dummy tbody .select-item:checked').length) {
            wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons('delete_rate'));
        } else {
            wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons());
        }

        var isBulkChecked = $('#wcsdm-table-dummy tbody .select-item').length === $('#wcsdm-table-dummy tbody .select-item:checked').length;

        $('#wcsdm-table-dummy thead .select-item').prop('checked', isBulkChecked);
    },
    toggleBottons: function (data) {
        $('#wcsdm-buttons').remove();
        $('#btn-ok').hide().after(wp.template('wcsdm-buttons')(data));
    },
    getButtons: function (context) {
        var btnAddRate = {
            label: wcsdmTableRates.params.i18n.add_rate,
            id: 'wcsdm-btn-add-rate',
            dashicon: 'plus'
        };

        var btnDeleteRate = {
            label: wcsdmTableRates.params.i18n.delete,
            id: 'wcsdm-btn-delete-rate',
            dashicon: 'plus'
        };

        var btnDeleteRateCancel = {
            label: wcsdmTableRates.params.i18n.cancel,
            id: 'wcsdm-btn-delete-rate-cancel',
            dashicon: 'undo'
        };

        var btnDeleteRateConfirm = {
            label: wcsdmTableRates.params.i18n.confirm_delete,
            id: 'wcsdm-btn-delete-rate-confirm',
            dashicon: 'trash'
        };

        var btnSave = {
            label: wcsdmTableRates.params.i18n.save_changes,
            id: 'wcsdm-btn-save',
            dashicon: 'yes'
        };

        var btnAdvancedCancel = {
            label: wcsdmTableRates.params.i18n.cancel,
            id: 'wcsdm-btn-advanced-cancel',
            dashicon: 'undo'
        };

        var btnAdvancedApply = {
            label: wcsdmTableRates.params.i18n.apply_changes,
            id: 'wcsdm-btn-advanced-apply',
            dashicon: 'editor-spellcheck'
        };

        if (context === 'advanced') {
            return {
                btn_left: btnAdvancedCancel,
                btn_right: btnAdvancedApply,
            };
        }

        if (context === 'delete_rate') {
            return {
                btn_left: btnDeleteRate,
            };
        }

        if (context === 'confirm_delete_rate') {
            return {
                btn_left: btnDeleteRateCancel,
                btn_right: btnDeleteRateConfirm,
            };
        }

        return {
            btn_left: btnAddRate,
            btn_right: btnSave,
        };
    },
};

$(document).ready(function () {
    // Try show settings modal on settings page.
    if (wcsdm_params.showSettings) {
        setTimeout(function () {
            var isMethodAdded = false;
            var methods = $(document).find('.wc-shipping-zone-method-type');
            for (var i = 0; i < methods.length; i++) {
                var method = methods[i];
                if ($(method).text() === wcsdm_params.methodTitle) {
                    $(method).closest('tr').find('.row-actions .wc-shipping-zone-method-settings').trigger('click');
                    isMethodAdded = true;
                    return;
                }
            }
            // Show Add shipping method modal if the shipping is not added.
            if (!isMethodAdded) {
                $('.wc-shipping-zone-add-method').trigger('click');
                $('select[name="add_method_id"]').val(wcsdm_params.methodId).trigger('change');
            }
        }, 400);
    }

    $(document).on('click', '.wc-shipping-zone-method-settings', function () {
        var params = _.mapObject(wcsdm_params, function (val, key) {
            switch (key) {
                case 'default_lat':
                case 'default_lng':
                case 'test_destination_lat':
                case 'test_destination_lng':
                    return parseFloat(val);

                default:
                    return val;
            }
        });

        // Bail early if the link clicked others shipping method
        var methodTitle = $(this).closest('tr').find('.wc-shipping-zone-method-type').text();
        if (methodTitle !== params.methodTitle) {
            return;
        }

        wcsdmTableRates.init(params);
        // wcsdmMapPicker.init(params);
    });
});
