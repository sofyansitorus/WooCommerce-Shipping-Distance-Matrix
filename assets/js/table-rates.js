;(function($) {
"use strict";

var rowIndex;
var rowScrollTop = 0;

var wcsdmTableRates = {
    init: function (params) {
        wcsdmTableRates.params = params;

        $('.wcsdm-rate-field--hidden').each(function (i, input) {
            var $input = $(input);
            $input.closest('tr').find('.wcsdm-rate-field--dummy--' + $input.data('key')).val($input.val());
        });

        $('#woocommerce_wcsdm_gmaps_api_units').trigger('change');

        setTimeout(function () {
            wcsdmTableRates.toggleTableRates();
        }, 100);

        // Handle on distance field changed.
        $(document).on('change input', '.wcsdm-rate-field--dummy--distance', function (e) {
            var $inputTarget = $(e.currentTarget);
            var dataChange = $inputTarget.data('change');
            if (typeof dataChange === 'undefined') {
                $inputTarget.attr('data-change', $inputTarget.val());
            } else if (dataChange !== $inputTarget.val()) {
                $inputTarget.attr('data-change', $inputTarget.val());
                $inputTarget.addClass('changed').closest('tr').addClass('changed');
            }
        });

        // Handle on distance unit field setting changed.
        $(document).on('change', '#woocommerce_wcsdm_gmaps_api_units.changed', function (e) {
            $('option[value="per_unit"]').text(wcsdmTableRates.params.i18n.distance[$(e.currentTarget).val()].perUnit);
        });

        // Handle on Rates per Shipping CLass option field value changed.
        $(document).on('change', '.wcsdm-rate-field--advanced', function () {
            $('.wcsdm-rate-field--advanced').each(function (i, field) {
                $(field).closest('tr').show();
                var showField = true;
                var fieldShowIf = $(field).data('show_if');
                if (fieldShowIf) {
                    _.keys(fieldShowIf).forEach(function (key) {
                        var fieldShowIfTarget = $('.wcsdm-rate-field--advanced--' + key).val();
                        if (fieldShowIf[key].indexOf(fieldShowIfTarget) === -1) {
                            showField = false;
                        }
                    });
                }

                if (showField) {
                    var fieldHideIf = $(field).data('hide_if');
                    if (fieldHideIf) {
                        _.keys(fieldHideIf).forEach(function (key) {
                            var fieldHideIfTarget = $('.wcsdm-rate-field--advanced--' + key).val();
                            if (fieldHideIf[key].indexOf(fieldHideIfTarget) !== -1) {
                                showField = false;
                            }
                        });
                    }
                }

                if (!showField) {
                    $(field).closest('tr').hide();
                } else {
                    $(field).closest('tr').show();
                }
            });
        });

        // Handle on Calculation Method option field value changed.
        $(document).on('change', '.wcsdm-rate-field--advanced--cost_type', function (e) {
            switch ($(e.currentTarget).val()) {
                case 'formula':
                    $(e.currentTarget).closest('table').find('.wcsdm-rate-field--advanced.wcsdm-cost-field').attr('type', 'text');
                    break;

                default:
                    $(e.currentTarget).closest('table').find('.wcsdm-rate-field--advanced.wcsdm-cost-field').attr('type', 'number');
                    break;
            }
        });

        // Handle on Calculation Method option field value changed.
        $(document).on('change', '.wcsdm-rate-field--dummy--cost_type', function (e) {
            switch ($(e.currentTarget).val()) {
                case 'formula':
                    $(e.currentTarget).closest('tr').find('.wcsdm-rate-field--dummy.wcsdm-cost-field').attr('type', 'text');
                    break;

                default:
                    $(e.currentTarget).closest('tr').find('.wcsdm-rate-field--dummy.wcsdm-cost-field').attr('type', 'number');
                    break;
            }
        });

        // Handle on dummy field value changed.
        $(document).on('change input', '.wcsdm-rate-field--dummy', debounce(function (e) {
            e.preventDefault();

            var $input = $(e.currentTarget);
            var inputVal = $input.val();
            var inputKey = $input.attr('data-key');
            $input.closest('tr').find('.wcsdm-rate-field--hidden--' + inputKey).val(inputVal);

            wcsdmTableRates.validateRatesList();
        }, 500));

        // // Sort rows based distance field on blur.
        $(document).on('blur', '.wcsdm-rate-field--dummy--distance', function (e) {
            if ($(e.currentTarget).val().length) {
                wcsdmTableRates.sortRates();
            }
        });

        // Handle on advanced rate settings link clicked.
        $(document).on('click', '.wcsdm-btn-advanced-rate', function (e) {
            e.preventDefault();

            hideError();

            var $row = $(e.currentTarget).closest('tr').removeClass('applied');
            $row.siblings().removeClass('applied');
            $row.find('.wcsdm-rate-field--hidden').each(function (i, input) {
                var $input = $(input);
                var inputVal = $input.val();
                var inputKey = $input.attr('data-key');
                var $inputTarget = $('.wcsdm-rate-field--advanced--' + inputKey).val(inputVal).trigger('change');
                if ($row.find('.col-' + inputKey).hasClass('error')) {
                    $inputTarget.closest('tr').addClass('error');
                }
            });

            rowIndex = $row.index();
            rowScrollTop = Math.abs($row.closest('form').position().top);

            wcsdmTableRates.setFooterButtons();

            $('#wcsdm-row-advanced').show().siblings().hide();

        });

        // Handle on Cancel Changes button clicked.
        $(document).on('click', '#wcsdm-btn-advanced-rate-cancel', function (e) {
            e.preventDefault();

            hideError();

            wcsdmTableRates.restoreFooterButtons();

            $('#wcsdm-row-advanced').hide().siblings().not('.wcsdm-row--hidden').show();

            $('#wcsdm-table-advanced tr').removeClass('error');

            $('#wcsdm-table-rates tbody tr:eq(' + rowIndex + ')').addClass('applied');

            wcsdmTableRates.validateRatesList();

            $('.wc-modal-shipping-method-settings').animate({
                scrollTop: rowScrollTop
            }, 500, function () {
                setTimeout(function () {
                    $('#wcsdm-table-rates tbody tr').removeClass('applied');
                }, 800);
            });
        });

        // Handle on Apply Changes button clicked.
        $(document).on('click', '#wcsdm-btn-advanced-rate-apply', function (e) {
            e.preventDefault();
            hideError();

            var errors = wcsdmTableRates.getRateFormErrors($('.wcsdm-rate-field--advanced'));

            if (errors.length) {
                var errorMessages = {};

                for (var index = 0; index < errors.length; index++) {
                    errorMessages[errors[index].key] = errors[index].message;
                }

                var errorMessage = '';
                _.keys(errorMessages).forEach(function (key) {
                    $('.wcsdm-rate-field--advanced--' + key).closest('tr').addClass('error');
                    errorMessage += '<p id="wcsdm-rate-field--error--' + key + '">' + errorMessages[key] + '</p>';
                });

                showError({
                    selector: '#wcsdm-table-advanced',
                    content: errorMessage
                });
                return;
            }

            var formData = wcsdmTableRates._populateFormData($('.wcsdm-rate-field--advanced'));

            if (_.keys(formData).length) {
                _.keys(formData).forEach(function (key) {
                    $('#wcsdm-table-rates tbody tr:eq(' + rowIndex + ')').removeClass('error').addClass('applied').find('.wcsdm-rate-field--dummy--' + key + ', .wcsdm-rate-field--hidden--' + key).val(formData[key]);
                });

                wcsdmTableRates.restoreFooterButtons();

                $('#wcsdm-row-advanced').hide().siblings().not('.wcsdm-row--hidden').show();

                $('.wc-modal-shipping-method-settings').animate({
                    scrollTop: rowScrollTop
                }, 500, function () {
                    setTimeout(function () {
                        wcsdmTableRates.sortRates();
                        wcsdmTableRates.validateRatesList();
                    }, 100);
                });
            }
        });

        // // Handle on Save Changes button clicked.
        // $(document).on('click', '#wcsdm-btn-primary-save-changes', function (e) {
        //     e.preventDefault();
        //     hideError();

        //     var locationErrorMessage = '';
        //     var locationFields = ['woocommerce_wcsdm_origin_lat', 'woocommerce_wcsdm_origin_lng'];
        //     for (var i = 0; i < locationFields.length; i++) {
        //         var locationFieldKey = locationFields[i];
        //         var $locationField = $('#' + locationFieldKey);
        //         if (!$locationField.val().length) {
        //             locationErrorMessage += '<p id="wcsdm-rate-field--error--' + locationFieldKey + '">' + wcsdmTableRates.params.i18n.errors.field_required.replace('%s', $locationField.data('title')) + '</p>';
        //             $('#' + locationFieldKey + '_dummy').closest('td').addClass('error');
        //         }
        //     }

        //     if (locationErrorMessage.length) {
        //         showError({
        //             selector: '#wcsdm-col-store-location',
        //             content: locationErrorMessage
        //         });
        //         return;
        //     }

        //     if (!$('#wcsdm-table-rates tbody tr').length) {
        //         showError({
        //             selector: '#wcsdm-table-rates',
        //             content: wcsdmTableRates.params.i18n.errors.rates_empty
        //         });
        //         return;
        //     }

        //     var errors = wcsdmTableRates.validateRatesList();
        //     if (errors.length) {
        //         var errorMessages = {};

        //         for (var index = 0; index < errors.length; index++) {
        //             errorMessages[errors[index].key] = errors[index].message;
        //         }

        //         var errorMessage = '';
        //         _.keys(errorMessages).forEach(function (key) {
        //             $('.wcsdm-rate-field--advanced--' + key).closest('tr').addClass('error');
        //             errorMessage += '<p id="wcsdm-rate-field--error--' + key + '">' + errorMessages[key] + '</p>';
        //         });

        //         showError({
        //             selector: '#wcsdm-table-rates',
        //             content: errorMessage
        //         });
        //         return;
        //     }

        //     $('#btn-ok').trigger('click');
        // });

        // Handle add rate rows.
        $(document).on('click', '#wcsdm-btn-add-rate', wcsdmTableRates.addRateRows);

        // Handle remove rate rows.
        $(document).on('click', '.btn-delete-rate', wcsdmTableRates.removeRateRows);
    },
    setFooterButtons: function () {
        $('#wcsdm-footer-buttons').remove();
        $('#btn-ok').after(wp.template('wcsdm-footer-buttons')({
            btn_left: {
                label: wcsdmMapPicker.params.i18n.cancel,
                id: 'wcsdm-btn-advanced-rate-cancel',
                icon: 'undo'
            },
            btn_right: {
                label: wcsdmMapPicker.params.i18n.apply_changes,
                id: 'wcsdm-btn-advanced-rate-apply',
                icon: 'yes'
            }
        }));
    },
    restoreFooterButtons: function () {
        $('#wcsdm-footer-buttons').remove();
        $('#btn-ok').after(wp.template('wcsdm-footer-buttons')({
            btn_left: {
                label: wcsdmMapPicker.params.i18n.add_rate,
                id: 'wcsdm-btn-add-rate',
                icon: 'plus'
            },
            btn_right: {
                label: wcsdmMapPicker.params.i18n.save_changes,
                id: 'wcsdm-btn-save',
                icon: 'yes'
            }
        }));
    },
    sortRates: function () {
        $('#wcsdm-btn-primary-save-changes').prop('disabled', true);
        var rows = $('#wcsdm-table-rates > tbody > tr').addClass('sorting').get().sort(function (a, b) {
            var valueADistance = $(a).find('.wcsdm-rate-field--dummy--distance').val();
            var valueBDistance = $(b).find('.wcsdm-rate-field--dummy--distance').val();

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
            $('#wcsdm-table-rates').children('tbody').append(row);
        });

        setTimeout(function () {
            $('#wcsdm-btn-primary-save-changes').prop('disabled', false);
            $('#wcsdm-table-rates > tbody > tr').removeClass('changed sorting applied');
            $('#wcsdm-table-rates .wcsdm-rate-field--dummy--distance').removeClass('changed');
        }, 800);
    },
    validateRatesList: function () {
        hideError();
        var errors = wcsdmTableRates.getRateFormErrors($('.wcsdm-rate-field--hidden'));
        if (errors.length) {
            for (var index = 0; index < errors.length; index++) {
                $('#wcsdm-table-rates tbody tr:eq(' + errors[index].rowIndex + ')').find('.col-' + errors[index].key).addClass('error');
            }
        }
        return errors;
    },
    getRateFormErrors: function ($fields) {
        var errors = [];
        var fields = {};

        // Populate form fields
        $fields.each(function (i, field) {
            var fieldKey = $(field).attr('data-key');
            if (typeof fields[fieldKey] === 'undefined') {
                fields[fieldKey] = [];
            }
            fields[fieldKey].push(_.extend({}, $(field).attrs(), $(field).data(), {
                value: $(field).val(),
                rowIndex: $(field).closest('tr').index()
            }));
        });

        _.keys(fields).forEach(function (key) {
            var dataRows = fields[key];
            for (var index = 0; index < dataRows.length; index++) {
                var dataRow = dataRows[index];
                var ignoreField = false;
                var showIf = dataRow.show_if || false;
                var hideIf = dataRow.hide_if || false;

                if (showIf) {
                    _.keys(showIf).forEach(function (showIfKey) {
                        var showIfTarget = fields[showIfKey][index].value;
                        var showIfField = showIf[showIfKey];
                        if (showIfField.indexOf(showIfTarget) === -1) {
                            ignoreField = true;
                        }
                    });
                }

                if (hideIf) {
                    _.keys(hideIf).forEach(function (hideIfKey) {
                        var hideIfTarget = fields[hideIfKey][index].value;
                        var hideIfField = hideIf[hideIfKey];
                        if (hideIfField.indexOf(hideIfTarget) !== -1) {
                            ignoreField = true;
                        }
                    });
                }

                if (!ignoreField) {
                    try {
                        var rowValue = dataRow.value || '';
                        var rowRequired = dataRow.required || false;

                        if (!rowValue.length && rowRequired) {
                            throw new Error(wcsdmTableRates.params.i18n.errors.field_required.replace('%s', dataRow.title));
                        }

                        if (rowValue.length) {
                            if (dataRow.type === 'number') {
                                var costType = fields.cost_type[index].value;
                                var costField = dataRow.cost_field || false;
                                if (costType === 'formula' && costField) {
                                    var matches = rowValue.match(/([0-9]|[\*\+\-\/\(\)]|\{d\}|\{w\}|\{a\}|\{q\})+/g);
                                    if (!matches.length || matches[0] !== rowValue) {
                                        throw new Error(wcsdmTableRates.params.i18n.errors.field_invalid.replace('%s', dataRow.title));
                                    }

                                    if (rowValue.indexOf('(') !== -1 || rowValue.indexOf(')') !== -1) {
                                        var opening = rowValue.replace(/[^\(]+/g, '');
                                        var closing = rowValue.replace(/[^\)]+/g, '');
                                        if (opening.length !== closing.length) {
                                            throw new Error(wcsdmTableRates.params.i18n.errors.field_invalid.replace('%s', dataRow.title));
                                        }

                                        var cleaned = rowValue.replace(/\((?:[^()]|\([^()]*\))*\)/g, '');
                                        if (cleaned && (cleaned.indexOf('(') !== -1 || cleaned.indexOf(')'))) {
                                            throw new Error(wcsdmTableRates.params.i18n.errors.field_invalid.replace('%s', dataRow.title));
                                        }
                                    }
                                } else {
                                    if (isNaN(rowValue)) {
                                        throw new Error(wcsdmTableRates.params.i18n.errors.field_invalid.replace('%s', dataRow.title));
                                    }

                                    if (!isNaN(dataRow.min) && parseFloat(rowValue) < parseFloat(dataRow.min)) {
                                        throw new Error(wcsdmTableRates.params.i18n.errors.field_min_value.replace('%$1s', dataRow.title).replace('%$2d', dataRow.min));
                                    }

                                    if (!isNaN(dataRow.max) && parseFloat(rowValue) < parseFloat(dataRow.max)) {
                                        throw new Error(wcsdmTableRates.params.i18n.errors.field_max_value.replace('%$1s', dataRow.title).replace('%$2d', dataRow.max));
                                    }
                                }
                            } else if (dataRow.type === 'object') {
                                if (typeof dataRow.options[rowValue] === 'undefined') {
                                    throw new Error(wcsdmTableRates.params.i18n.errors.field_invalid.replace('%s', dataRow.title));
                                }
                            }
                        }
                    } catch (error) {
                        errors.push({
                            key: key,
                            message: error.message,
                            rowIndex: dataRow.rowIndex
                        });
                    }
                }
            }
        });

        return errors;
    },
    _populateFormData: function ($fields) {
        var dataForm = {};

        // Populate form data
        $fields.each(function (i, input) {
            dataForm[$(input).attr('data-key')] = $(input).val();
        });

        return dataForm;
    },
    addRateRows: function (e) {
        e.preventDefault();
        $('#wcsdm-table-rates tbody').append(wp.template('rates-list-input-table-row')).find('tr:last-child .wcsdm-rate-field').each(function (i, input) {
            $(input).trigger('change');
            if ($(input).hasClass('wcsdm-rate-field--distance')) {
                $(input).focus();
            }
        });
        $('#woocommerce_wcsdm_gmaps_api_units').trigger('change');
        $('.wc-modal-shipping-method-settings').scrollTop($('.wc-modal-shipping-method-settings').find('form').outerHeight());
        wcsdmTableRates.toggleTableRates();
    },
    removeRateRows: function (e) {
        e.preventDefault();
        $(e.currentTarget).closest('tr').remove();
        wcsdmTableRates.toggleTableRates();
    },
    toggleTableRates: function (e) {
        $('#wcsdm-table-rates').find('thead, tfoot').show();

        if (!$('#wcsdm-table-rates tbody tr').length) {
            $('#wcsdm-table-rates').find('thead, tfoot').hide();
        }
    }
};
}(jQuery));
