
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
                // Add address_1 & address_2 fields to calc_shipping form
                if (form.prefix === 'calc_shipping') {
                    var $cloneFieldWrap = $wrapper.find('#calc_shipping_postcode_field');

                    if (!$cloneFieldWrap || !$cloneFieldWrap.length) {
                        $cloneFieldWrap = $wrapper.find('#calc_shipping_city_field');
                    }

                    if ($cloneFieldWrap && $cloneFieldWrap.length) {
                        _.each(['address_1', 'address_2'], function (addressField) {
                            var $addresFieldWrap = $cloneFieldWrap.clone().attr({
                                id: 'calc_shipping_' + addressField + '_field'
                            });

                            $addresFieldWrap.find('input').attr({
                                id: 'calc_shipping_' + addressField,
                                name: 'calc_shipping_' + addressField,
                                placeholder: wcsdmFrontendForm.fields['default'][addressField].placeholder,
                                value: $('#wcsdm-calc-shipping-field-value-' + addressField).text()
                            }).trigger('change');

                            $cloneFieldWrap.before($addresFieldWrap);

                            $('#wcsdm-calc-shipping-field-value-' + addressField).remove();
                        });
                    }
                }

                $(document.body).trigger('wcsdm_form_loaded_' + form.prefix, form);
                $(document.body).trigger('wcsdm_form_loaded', form);
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