
var wcsdmFrontendForm = {
    setFields: function () {
        wcsdmFrontendForm.fields = $.parseJSON(wc_address_i18n_params.locale.replace(/&quot;/g, '"'));
    },
    bindEvents: function () {
        $(document.body).on('updated_wc_div updated_shipping_method', wcsdmFrontendForm.loadForm);
    },
    loadForm: function () {
        _.each(wcsdmFrontendForm.getForms(), function (form) {
            var $wrapper = $(form.wrapper);

            if ($wrapper && $wrapper.length) {
                $wrapper.addClass('wcsdm-form-loaded');

                // Add address_1 & address_2 fields to calc_shipping form
                if (form.prefix === 'calc_shipping') {
                    var $postCodeFieldWrap = $wrapper.find('#calc_shipping_postcode_field');

                    if ($postCodeFieldWrap.length) {
                        var getAddressFieldValues = function () {
                            var destinationText = $('.woocommerce-shipping-destination strong').text();
                            if (_.isEmpty(destinationText)) {
                                return [];
                            }

                            var countryCode = $('#calc_shipping_country').val();

                            var destinationParts = _.map(destinationText.split(', '), function (part) {
                                return part.trim();
                            });

                            var destinationFiltered = _.filter(destinationParts, function (part) {
                                var included = true;
                                _.each(_.keys(wcsdmFrontendForm.fields['default']), function (fieldKey) {
                                    var $field = $('#calc_shipping_' + fieldKey);
                                    if ($field && $field.length) {
                                        var fieldValue = $field.val();
                                        switch (fieldKey) {
                                            case 'country':
                                                var countryText = $field.find('option[value=' + fieldValue + ']').text();
                                                if (countryText.trim() === part) {
                                                    included = false;
                                                }
                                                break;
                                            case 'state':
                                                if (fieldValue.trim() === part) {
                                                    included = false;
                                                } else {
                                                    var states = $.parseJSON(wc_country_select_params.countries)[countryCode];
                                                    if (!_.isEmpty(states)) {
                                                        var stateText = $field.find('option[value=' + fieldValue + ']').text();
                                                        if (stateText.trim() === part) {
                                                            included = false;
                                                        }
                                                    }
                                                }
                                                break;

                                            default:
                                                if (fieldValue.trim() === part) {
                                                    included = false;
                                                }
                                                break;
                                        }
                                    }
                                });

                                return included;
                            });

                            return destinationFiltered;
                        };

                        var addressFieldValues = getAddressFieldValues();

                        var addAddressField = function (fieldId) {
                            var $addresFieldWrap = $postCodeFieldWrap.clone().attr({
                                id: 'calc_shipping_' + fieldId + '_field'
                            });

                            var value = '';

                            if (_.size(addressFieldValues)) {
                                switch (fieldId) {
                                    case 'address_1':
                                        value = _.first(addressFieldValues);
                                        break;

                                    default:
                                        value = _.rest(addressFieldValues, 1).join(', ');
                                        break;
                                }
                            }

                            $addresFieldWrap.find('input').attr({
                                id: 'calc_shipping_' + fieldId,
                                name: 'calc_shipping_' + fieldId,
                                placeholder: wcsdmFrontendForm.fields['default'][fieldId].placeholder,
                                value: value
                            }).trigger('change');

                            $postCodeFieldWrap.before($addresFieldWrap);
                        };

                        // Inject "address_1" field
                        addAddressField('address_1');

                        // Inject "address_2" field
                        addAddressField('address_2');
                    }
                }

                $(document.body).trigger('wcsdm_form_loaded_' + form.prefix, form, wcsdmFrontendForm.fields);
                $(document.body).trigger('wcsdm_form_loaded', form, wcsdmFrontendForm.fields);
            }
        });
    },
    getForms: function () {
        return [{
            wrapper: '.woocommerce-billing-fields__field-wrapper',
            prefix: 'billing'
        }, {
            wrapper: '.woocommerce-shipping-fields__field-wrapper',
            prefix: 'shipping'
        }, {
            wrapper: '.shipping-calculator-form',
            prefix: 'calc_shipping'
        }];
    },
    init: function () {
        // wc_address_i18n_params is required to continue, ensure the object exists
        if (typeof wc_address_i18n_params === 'undefined') {
            return false;
        }

        // wc_country_select_params is required to continue, ensure the object exists
        if (typeof wc_country_select_params === 'undefined') {
            return false;
        }

        wcsdmFrontendForm.setFields();
        wcsdmFrontendForm.bindEvents();
        wcsdmFrontendForm.loadForm();
    }
};

$(document).ready(wcsdmFrontendForm.init);