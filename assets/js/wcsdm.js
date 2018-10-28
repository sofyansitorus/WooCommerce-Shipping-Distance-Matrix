;(function($) {
"use strict";

// Returns a function, that, as long as it continues to be invoked, will not
// be triggered. The function will be called after it stops being called for
// N milliseconds.
function debounce(func, wait) {
    var timeout;
    return function () {
        var context = this;
        var args = arguments;
        var later = function () {
            timeout = null;
            func.apply(context, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Replace the percent (%) sign by a variable passed as an argument
 * similar to sprintf function in PHP
 *
 * @see: http://locutus.io/php/strings/sprintf/
 */
function sprintf() {
    var regex = /%%|%(?:(\d+)\$)?((?:[-+#0 ]|'[\s\S])*)(\d+)?(?:\.(\d*))?([\s\S])/g
    var args = arguments
    var i = 0
    var format = args[i++]

    var _pad = function (str, len, chr, leftJustify) {
        if (!chr) {
            chr = ' '
        }
        var padding = (str.length >= len) ? '' : new Array(1 + len - str.length >>> 0).join(chr)
        return leftJustify ? str + padding : padding + str
    }

    var justify = function (value, prefix, leftJustify, minWidth, padChar) {
        var diff = minWidth - value.length
        if (diff > 0) {
            // when padding with zeros
            // on the left side
            // keep sign (+ or -) in front
            if (!leftJustify && padChar === '0') {
                value = [
                    value.slice(0, prefix.length),
                    _pad('', diff, '0', true),
                    value.slice(prefix.length)
                ].join('')
            } else {
                value = _pad(value, minWidth, padChar, leftJustify)
            }
        }
        return value
    }

    var _formatBaseX = function (value, base, leftJustify, minWidth, precision, padChar) {
        // Note: casts negative numbers to positive ones
        var number = value >>> 0
        value = _pad(number.toString(base), precision || 0, '0', false)
        return justify(value, '', leftJustify, minWidth, padChar)
    }

    // _formatString()
    var _formatString = function (value, leftJustify, minWidth, precision, customPadChar) {
        if (precision !== null && precision !== undefined) {
            value = value.slice(0, precision)
        }
        return justify(value, '', leftJustify, minWidth, customPadChar)
    }

    // doFormat()
    var doFormat = function (substring, argIndex, modifiers, minWidth, precision, specifier) {
        var number, prefix, method, textTransform, value

        if (substring === '%%') {
            return '%'
        }

        // parse modifiers
        var padChar = ' ' // pad with spaces by default
        var leftJustify = false
        var positiveNumberPrefix = ''
        var j, l

        for (j = 0, l = modifiers.length; j < l; j++) {
            switch (modifiers.charAt(j)) {
                case ' ':
                case '0':
                    padChar = modifiers.charAt(j)
                    break
                case '+':
                    positiveNumberPrefix = '+'
                    break
                case '-':
                    leftJustify = true
                    break
                case "'":
                    if (j + 1 < l) {
                        padChar = modifiers.charAt(j + 1)
                        j++
                    }
                    break
            }
        }

        if (!minWidth) {
            minWidth = 0
        } else {
            minWidth = +minWidth
        }

        if (!isFinite(minWidth)) {
            throw new Error('Width must be finite')
        }

        if (!precision) {
            precision = (specifier === 'd') ? 0 : 'fFeE'.indexOf(specifier) > -1 ? 6 : undefined
        } else {
            precision = +precision
        }

        if (argIndex && +argIndex === 0) {
            throw new Error('Argument number must be greater than zero')
        }

        if (argIndex && +argIndex >= args.length) {
            throw new Error('Too few arguments')
        }

        value = argIndex ? args[+argIndex] : args[i++]

        switch (specifier) {
            case '%':
                return '%'
            case 's':
                return _formatString(value + '', leftJustify, minWidth, precision, padChar)
            case 'c':
                return _formatString(String.fromCharCode(+value), leftJustify, minWidth, precision, padChar)
            case 'b':
                return _formatBaseX(value, 2, leftJustify, minWidth, precision, padChar)
            case 'o':
                return _formatBaseX(value, 8, leftJustify, minWidth, precision, padChar)
            case 'x':
                return _formatBaseX(value, 16, leftJustify, minWidth, precision, padChar)
            case 'X':
                return _formatBaseX(value, 16, leftJustify, minWidth, precision, padChar)
                    .toUpperCase()
            case 'u':
                return _formatBaseX(value, 10, leftJustify, minWidth, precision, padChar)
            case 'i':
            case 'd':
                number = +value || 0
                // Plain Math.round doesn't just truncate
                number = Math.round(number - number % 1)
                prefix = number < 0 ? '-' : positiveNumberPrefix
                value = prefix + _pad(String(Math.abs(number)), precision, '0', false)

                if (leftJustify && padChar === '0') {
                    // can't right-pad 0s on integers
                    padChar = ' '
                }
                return justify(value, prefix, leftJustify, minWidth, padChar)
            case 'e':
            case 'E':
            case 'f': // @todo: Should handle locales (as per setlocale)
            case 'F':
            case 'g':
            case 'G':
                number = +value
                prefix = number < 0 ? '-' : positiveNumberPrefix
                method = ['toExponential', 'toFixed', 'toPrecision']['efg'.indexOf(specifier.toLowerCase())]
                textTransform = ['toString', 'toUpperCase']['eEfFgG'.indexOf(specifier) % 2]
                value = prefix + Math.abs(number)[method](precision)
                return justify(value, prefix, leftJustify, minWidth, padChar)[textTransform]()
            default:
                // unknown specifier, consume that char and return empty
                return ''
        }
    }

    try {
        return format.replace(regex, doFormat)
    } catch (err) {
        return false
    }
}

/**
 * Table Rates
 */
var wcsdmTableRates = {
    params: {},
    errorId: 'wcsdm-errors-rate-fields',
    init: function (params) {
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
        e.preventDefault();
        $(e.currentTarget).prop('disabled', true);

        if (wcsdmTableRates.validateRates()) {
            wcsdmTableRates.addRateRow();
        }

        $(e.currentTarget).prop('disabled', false);
    },
    handleRateFieldDummy: function (e) {
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
        e.preventDefault();
        $('#wcsdm-row-advanced').hide().siblings().show();
        $('.modal-close-link').show();
        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons());
        wcsdmTableRates.resetRateErrors();
        wcsdmTableRates.sortRateRows();
    },
    highlightRow() {
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
        $('#' + wcsdmTableRates.errorId).remove();
        $('.wcsdm-field--rate').removeClass('wcsdm-field--rate--error').removeAttr('data-error');
    },
    validateRates: function (isAdvanced) {
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
            $tabel.before(wp.template('wcsdm-errors')({ id: wcsdmTableRates.errorId, errors }));

            return false;
        }

        return true;
    },
    addRateRow: function () {
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
        e.preventDefault();

        $('#wcsdm-table-dummy tbody .select-item:not(:checked)').closest('tr').addClass('hidden');
        $('#wcsdm-table-dummy tbody .wcsdm-field--rate--dummy').prop('disabled', true);
        $('#wcsdm-table-dummy tbody .select-item:checked').closest('tr').addClass('deleted');
        $('.wcsdm-col--select-item, .wcsdm-col--link_advanced').hide();
        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons('confirm_delete_rate'));
    },
    deleteRateRowCancel: function (e) {
        e.preventDefault();

        $('#wcsdm-table-dummy tbody tr').removeClass('hidden deleted');
        $('#wcsdm-table-dummy tbody .wcsdm-field--rate--dummy').prop('disabled', false);
        $('.wcsdm-col--select-item, .wcsdm-col--link_advanced').show();
        wcsdmTableRates.toggleBottons(wcsdmTableRates.getButtons('delete_rate'));
    },
    deleteRateRowConfirm: function (e) {
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
    sortRateRows: function (resolve) {
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
    });
});
}(jQuery));
