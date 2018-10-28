/**
 * Table Rates
 */
var wcsdmTableRates = {
    params: {},
    errorId: 'wcsdm-errors-rate-fields',
    init: function (params) {
        "use strict";
        wcsdmTableRates.params = params;

        // Show advanced row
        $(document).off('click', '.wcsdm-field--rate--dummy--link_advanced');
        $(document).on('click', '.wcsdm-field--rate--dummy--link_advanced', wcsdmTableRates.showAdvancedForm);

        // Hide advanced row
        $(document).off('click', '#wcsdm-btn-advanced-cancel');
        $(document).on('click', '#wcsdm-btn-advanced-cancel', wcsdmTableRates.hideAdvancedForm);

        // Apply advanced row
        $(document).off('click', '#wcsdm-btn-advanced-apply');
        $(document).on('click', '#wcsdm-btn-advanced-apply', wcsdmTableRates.applyAdvancedForm);

        // Add rate row
        $(document).off('click', '#wcsdm-btn-add-rate');
        $(document).on('click', '#wcsdm-btn-add-rate', wcsdmTableRates.handleAddRateButton);

        // Delete rate row
        $(document).off('click', '#wcsdm-btn-delete-rate');
        $(document).on('click', '#wcsdm-btn-delete-rate', wcsdmTableRates.deleteRateRow);

        // Cancel delete rate row
        $(document).off('click', '#wcsdm-btn-delete-rate-cancel');
        $(document).on('click', '#wcsdm-btn-delete-rate-cancel', wcsdmTableRates.deleteRateRowCancel);

        // Confirm delete rate row
        $(document).off('click', '#wcsdm-btn-delete-rate-confirm');
        $(document).on('click', '#wcsdm-btn-delete-rate-confirm', wcsdmTableRates.deleteRateRowConfirm);

        // Toggle selected rows
        $(document).off('change', '#wcsdm-table-dummy thead .select-item');
        $(document).on('change', '#wcsdm-table-dummy thead .select-item', wcsdmTableRates.toggleSelectedRows);

        // Toggle selected row
        $(document).off('change', '#wcsdm-table-dummy tbody .select-item');
        $(document).on('change', '#wcsdm-table-dummy tbody .select-item', wcsdmTableRates.toggleSelectedRow);

        // Handle change event dummy rate field
        $(document).off('input', '.wcsdm-field--rate--dummy:not(a)');
        $(document).on('input', '.wcsdm-field--rate--dummy:not(a)', debounce(function (e) {
            wcsdmTableRates.handleRateFieldDummy(e);
        }, 500));

        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons());

        if (!$('#wcsdm-table-dummy tbody tr').length) {
            wcsdmTableRates.addRateRow();
        }
    },
    handleAddRateButton: function (e) {
        "use strict";
        e.preventDefault();
        $(e.currentTarget).prop('disabled', true);

        if (wcsdmTableRates.validateRates()) {
            wcsdmTableRates.addRateRow();
        }

        $(e.currentTarget).prop('disabled', false);
    },
    handleRateFieldDummy: function (e) {
        "use strict";
        e.preventDefault();

        var $field = $(e.target);
        var $row = $field.closest('tr');
        $row.find('.wcsdm-field--rate--hidden[data-id=' + $field.data('id') + ']').val(e.target.value);

        if (wcsdmTableRates.validateRates()) {
            $row.addClass('editing');
            wcsdmTableRates.sortRateRows();
        }
    },
    showAdvancedForm: function (e) {
        "use strict";
        e.preventDefault();
        wcsdmTableRates.resetRateErrors();

        $('#wcsdm-table-dummy .select-item').prop('checked', false);
        $('#wcsdm-table-dummy tbody tr').removeClass();

        var $row = $(e.currentTarget).closest('tr').addClass('editing');

        $row.find('.wcsdm-field--rate--hidden').each(function (index, field) {
            var $field = $(field);
            $('#' + $field.data('id')).val($field.val());
        });

        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons('advanced'));

        $('#wcsdm-row-advanced').show().siblings().hide('fast', function () {
            $('.modal-close-link').hide();
        });
    },
    applyAdvancedForm: function (e) {
        "use strict";
        e.preventDefault();

        if (!wcsdmTableRates.validateRates(true)) {
            return;
        }

        $('.wcsdm-field--rate--advanced').each(function (index, field) {
            var fieldId = $(field).attr('id');
            $('#wcsdm-table-dummy tbody tr.editing .wcsdm-field--rate--dummy[data-id=' + fieldId + ']:not(a)').val($(field).val());
            $('#wcsdm-table-dummy tbody tr.editing .wcsdm-field--rate--hidden[data-id=' + fieldId + ']:not(a)').val($(field).val());
        });

        wcsdmTableRates.hideAdvancedForm(e);
    },
    hideAdvancedForm: function (e) {
        "use strict";
        e.preventDefault();
        $('#wcsdm-row-advanced').hide().siblings().show();
        $('.modal-close-link').show();
        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons());
        wcsdmTableRates.resetRateErrors();
        wcsdmTableRates.sortRateRows();
    },
    highlightRow: function () {
        "use strict";
        var $row = $('#wcsdm-table-dummy tbody tr.editing');
        if ($row.length) {
            $row.addClass('highlighted');
            var $modal = $('.wc-modal-shipping-method-settings');
            var $table = $('#wcsdm-row-dummy');
            var scrollToTop = ($row.offset().top - $table.offset().top) + $modal.offset().top;
            $modal.scrollTop(scrollToTop);
            setTimeout(function () {
                $row.removeClass('highlighted');
            }, 600);
        }
        $('#wcsdm-table-dummy tbody tr').removeClass('editing');
    },
    resetRateErrors: function () {
        "use strict";
        $('#' + wcsdmTableRates.errorId).remove();
        $('.wcsdm-field--rate').removeClass('wcsdm-field--rate--error').removeAttr('data-error');
    },
    validateRates: function (isAdvanced) {
        "use strict";
        wcsdmTableRates.resetRateErrors();

        var errors = {};
        var $tabel = isAdvanced ? $('#wcsdm-table-advanced') : $('#wcsdm-table-dummy');
        $tabel.find('tbody tr').each(function (i, row) {
            $(row).find('.wcsdm-field--rate:not(a)').each(function (i, field) {
                var $field = $(field);

                var fieldId = $field.data('id');
                var fieldValue = $field.val();

                var errorMessage = '';

                if ($field.data('required') && !fieldValue.length) {
                    errorMessage = sprintf(wcsdmTableRates.params.i18n.errors.field_required, $field.data('title'));
                }

                if (fieldValue.length) {
                    // Validate select field
                    if ($field.data('type') === 'select' && !_.has($field.data('options'), fieldValue)) {
                        errorMessage = sprintf(wcsdmTableRates.params.i18n.errors.field_select, $field.data('title'));
                    }

                    // Validate numeric field
                    if ($field.data('type') === 'number') {
                        var regex = $field.attr('step') === 'any' ? /^[0-9]\d*(\.\d+)?$/ : /^\d+$/;
                        if (!regex.test(fieldValue)) {
                            var errorStrkey = $field.attr('step') === 'any' ? 'field_numeric_decimal' : 'field_numeric';
                            errorMessage = sprintf(wcsdmTableRates.params.i18n.errors[errorStrkey], $field.data('title'));
                        }
                    }

                    // Validate field minimum value
                    if ($field.attr('min') && fieldValue < $field.attr('min')) {
                        errorMessage = sprintf(wcsdmTableRates.params.i18n.errors.field_min_value, $field.data('title'), $field.attr('min'));
                    }

                    // Validate field minimum value
                    if ($field.attr('max') && fieldValue < $field.attr('max')) {
                        errorMessage = sprintf(wcsdmTableRates.params.i18n.errors.field_min_value, $field.data('title'), $field.attr('min'));
                    }
                }

                if (errorMessage.length) {
                    errors[fieldId] = errorMessage;
                    $field.addClass('wcsdm-field--rate--error');
                }
            });
        });

        if (!_.isEmpty(errors)) {
            var templateData = {
                id: wcsdmTableRates.errorId,
                errors: errors,
            };

            $tabel.before(wp.template('wcsdm-errors')(templateData));

            return false;
        }

        return true;
    },
    addRateRow: function () {
        "use strict";
        var $lastRow = $('#wcsdm-table-dummy tbody tr:last-child');

        $('#wcsdm-table-dummy tbody').append(wp.template('wcsdm-dummy-row')).find('tr:last-child').find('.wcsdm-field--rate--dummy--max_distance').focus();

        if ($lastRow) {
            $lastRow.find('.wcsdm-field--rate--dummy:not(a)').each(function (index, field) {
                $('#wcsdm-table-dummy tbody tr:last-child .wcsdm-field--rate--dummy[data-id=' + $(field).data('id') + ']').val($(field).val());
            });

            $lastRow.find('.wcsdm-field--rate--hidden:not(a)').each(function (index, field) {
                $('#wcsdm-table-dummy tbody tr:last-child .wcsdm-field--rate--hidden[data-id=' + $(field).data('id') + ']').val($(field).val());
            });
        }

        $('.wc-modal-shipping-method-settings').scrollTop($('.wc-modal-shipping-method-settings').find('form').outerHeight());
    },
    deleteRateRow: function (e) {
        "use strict";
        e.preventDefault();

        $('#wcsdm-table-dummy tbody .select-item:not(:checked)').closest('tr').addClass('hidden');
        $('#wcsdm-table-dummy tbody .wcsdm-field--rate--dummy').prop('disabled', true);
        $('#wcsdm-table-dummy tbody .select-item:checked').closest('tr').addClass('deleted');
        $('.wcsdm-col--select-item, .wcsdm-col--link_advanced').hide();
        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons('confirm_delete_rate'));
    },
    deleteRateRowCancel: function (e) {
        "use strict";
        e.preventDefault();

        $('#wcsdm-table-dummy tbody tr').removeClass('hidden deleted');
        $('#wcsdm-table-dummy tbody .wcsdm-field--rate--dummy').prop('disabled', false);
        $('.wcsdm-col--select-item, .wcsdm-col--link_advanced').show();
        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons('delete_rate'));
    },
    deleteRateRowConfirm: function (e) {
        "use strict";
        e.preventDefault();

        $('#wcsdm-table-dummy tbody .select-item:checked').closest('tr').remove();
        $('#wcsdm-table-dummy tbody tr').removeClass('hidden');
        $('#wcsdm-table-dummy thead .select-item').prop('checked', false);
        $('#wcsdm-table-dummy tbody .wcsdm-field--rate--dummy').prop('disabled', false);
        $('.wcsdm-col--select-item, .wcsdm-col--link_advanced').show();
        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons());

        if (!$('#wcsdm-table-dummy tbody tr').length) {
            wcsdmTableRates.addRateRow();
        }

        wcsdmTableRates.resetRateErrors();
    },
    toggleSelectedRows: function (e) {
        "use strict";
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
        "use strict";
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
    sortRateRows: function () {
        "use strict";
        var rows = $('#wcsdm-table-dummy > tbody > tr').get().sort(function (a, b) {
            var valueADistance = $(a).find('.wcsdm-field--rate--dummy--max_distance').val();
            var valueBDistance = $(b).find('.wcsdm-field--rate--dummy--max_distance').val();

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
            $('#wcsdm-table-dummy').children('tbody').append(row);
        });

        setTimeout(function () {
            wcsdmTableRates.highlightRow();
        }, 100);
    },
    toggleBottons: function (data) {
        "use strict";
        $('#wcsdm-buttons').remove();
        $('#btn-ok').hide().after(wp.template('wcsdm-buttons')(data));
    },
    getButtons: function (context) {
        "use strict";
        var btnAddRate = {
            label: wcsdmTableRates.params.i18n.add_rate,
            id: 'wcsdm-btn-add-rate',
            dashicon: 'plus'
        };

        var btnDeleteRate = {
            label: wcsdmTableRates.params.i18n.delete_rate,
            id: 'wcsdm-btn-delete-rate',
            dashicon: 'plus'
        };

        var btnDeleteRateCancel = {
            label: wcsdmTableRates.params.i18n.cancel,
            id: 'wcsdm-btn-delete-rate-cancel',
            dashicon: 'undo'
        };

        var btnDeleteRateConfirm = {
            label: wcsdmTableRates.params.i18n.delete_rate_confirm,
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
