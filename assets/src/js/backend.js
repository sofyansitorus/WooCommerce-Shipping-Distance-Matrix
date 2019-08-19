/**
 * Backend Scripts
 */

var wcsdmBackend = {
    renderForm: function () {
        if (!$('#woocommerce_wcsdm_origin_type') || !$('#woocommerce_wcsdm_origin_type').length) {
            return;
        }

        // Submit form
        $(document).off('click', '#wcsdm-btn--save-settings', wcsdmBackend.submitForm);
        $(document).on('click', '#wcsdm-btn--save-settings', wcsdmBackend.submitForm);

        // Show API Key instruction
        $(document).off('click', '.wcsdm-show-instructions', wcsdmBackend.showApiKeyInstructions);
        $(document).on('click', '.wcsdm-show-instructions', wcsdmBackend.showApiKeyInstructions);

        // Close API Key instruction
        $(document).off('click', '#wcsdm-btn--close-instructions', wcsdmBackend.closeApiKeyInstructions);
        $(document).on('click', '#wcsdm-btn--close-instructions', wcsdmBackend.closeApiKeyInstructions);

        // Toggle Store Origin Fields
        $(document).off('change', '#woocommerce_wcsdm_origin_type', wcsdmBackend.toggleStoreOriginFields);
        $(document).on('change', '#woocommerce_wcsdm_origin_type', wcsdmBackend.toggleStoreOriginFields);

        $('#woocommerce_wcsdm_origin_type').trigger('change');

        $('.wc-modal-shipping-method-settings table.form-table').each(function () {
            var $table = $(this);
            var $rows = $table.find('tr');
            if (!$rows.length) {
                $table.remove();
            }
        });

        $('.wcsdm-field-group').each(function () {
            var $fieldGroup = $(this);

            var fieldGroupId = $fieldGroup
                .attr('id')
                .replace('woocommerce_wcsdm_field_group_', '');

            var $fieldGroupDescription = $fieldGroup
                .next('p')
                .detach();

            var $fieldGroupTable = $fieldGroup
                .nextAll('table.form-table')
                .first()
                .attr('id', 'wcsdm-table--' + fieldGroupId)
                .addClass('wcsdm-table wcsdm-table--' + fieldGroupId)
                .detach();

            $fieldGroup
                .wrap('<div id="wcsdm-field-group-wrap--' + fieldGroupId + '" class="wcsdm-field-group-wrap wcsdm-field-group-wrap--' + fieldGroupId + '"></div>');

            $fieldGroupDescription
                .appendTo('#wcsdm-field-group-wrap--' + fieldGroupId);

            $fieldGroupTable
                .appendTo('#wcsdm-field-group-wrap--' + fieldGroupId);

            if ($fieldGroup.hasClass('wcsdm-field-group-hidden')) {
                $('#wcsdm-field-group-wrap--' + fieldGroupId)
                    .addClass('wcsdm-hidden');
            }
        });

        var params = _.mapObject(wcsdm_backend, function (val, key) {
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

        wcsdmTableRates.init(params);
        wcsdmMapPicker.init(params);

        toggleBottons();
    },
    maybeOpenModal: function () {
        // Try show settings modal on settings page.
        if (wcsdm_backend.showSettings) {
            setTimeout(function () {
                var isMethodAdded = false;
                var methods = $(document).find('.wc-shipping-zone-method-type');
                for (var i = 0; i < methods.length; i++) {
                    var method = methods[i];
                    if ($(method).text() === wcsdm_backend.methodTitle) {
                        $(method).closest('tr').find('.row-actions .wc-shipping-zone-method-settings').trigger('click');
                        isMethodAdded = true;
                        return;
                    }
                }

                // Show Add shipping method modal if the shipping is not added.
                if (!isMethodAdded) {
                    $('.wc-shipping-zone-add-method').trigger('click');
                    $('select[name="add_method_id"]').val(wcsdm_backend.methodId).trigger('change');
                }
            }, 500);
        }
    },
    submitForm: function (e) {
        'use strict';
        e.preventDefault();

        $('#btn-ok').trigger('click');
    },
    showApiKeyInstructions: function (e) {
        'use strict';

        e.preventDefault();

        toggleBottons({
            left: {
                id: 'close-instructions',
                label: 'Back',
                icon: 'undo'
            },
            right: {
                id: 'get-api-key',
                label: 'Get API Key',
                icon: 'admin-links'
            }
        });

        $('#wcsdm-field-group-wrap--api_key_instruction').fadeIn().siblings().hide();

        $('.modal-close-link').hide();
    },
    closeApiKeyInstructions: function (e) {
        'use strict';

        e.preventDefault();

        $('#wcsdm-field-group-wrap--api_key_instruction').hide().siblings().not('.wcsdm-hidden').fadeIn();

        $('.modal-close-link').show();

        toggleBottons();
    },
    toggleStoreOriginFields: function (e) {
        e.preventDefault();
        var selected = $(this).val();
        var fields = $(this).data('fields');
        _.each(fields, function (fieldIds, fieldValue) {
            _.each(fieldIds, function (fieldId) {
                if (fieldValue !== selected) {
                    $('#' + fieldId).closest('tr').hide();
                } else {
                    $('#' + fieldId).closest('tr').show();
                }
            });
        });
    },
    initForm: function () {
        // Init form
        $(document.body).off('wc_backbone_modal_loaded', wcsdmBackend.renderForm);
        $(document.body).on('wc_backbone_modal_loaded', wcsdmBackend.renderForm);
    },
    init: function () {
        wcsdmBackend.initForm();
        wcsdmBackend.maybeOpenModal();
    }
};

$(document).ready(wcsdmBackend.init);