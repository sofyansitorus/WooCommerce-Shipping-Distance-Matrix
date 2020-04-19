
var wcsdmFrontendForm = {
  bindEvents: function () {
    $(document.body).on('updated_wc_div updated_shipping_method', wcsdmFrontendForm.loadForm);
  },
  loadForm: function () {
    var forms = wcsdmFrontendForm.getForms();
    var defaultFields = wcsdmFrontendForm.getDefaultFields();

    _.each(forms, function (form) {
      var $wrapper = $(form.wrapper);

      if (!$wrapper || !$wrapper.length) {
        return;
      }

      // Add address_1 & address_2 fields to calc_shipping form
      if (form.prefix === 'calc_shipping') {
        var $fieldToClone = $wrapper.find('#calc_shipping_city_field, #calc_shipping_postcode_field').first();

        if ($fieldToClone && $fieldToClone.length) {
          _.each(defaultFields, function (fieldData, fieldKey) {
            if (!wcsdm_frontend['shipping_calculator_' + fieldKey]) {
              return;
            }

            if (!_.contains(['address_1', 'address_2'], fieldKey)) {
              return;
            }

            if ($wrapper.find('#calc_shipping_' + fieldKey + '_field').length) {
              return;
            }

            var $fieldCloned = $fieldToClone.clone().attr({
              id: 'calc_shipping_' + fieldKey + '_field',
            });

            var placeholder = fieldData.placeholder || '';

            $fieldCloned.find('input').attr({
              'id': 'calc_shipping_' + fieldKey,
              'name': 'calc_shipping_' + fieldKey,
              'value': $('#wcsdm-calc-shipping-field-value-' + fieldKey).val(),
              'placeholder': placeholder,
              'data-placeholder': placeholder,
            }).data({
              'placeholder': placeholder,
            }).trigger('change');

            $fieldToClone.before($fieldCloned);

            $('#wcsdm-calc-shipping-field-value-' + fieldKey).remove();
          });
        }
      }

      $(document.body).trigger('wcsdm_form_loaded_' + form.prefix, form);
      $(document.body).trigger('wcsdm_form_loaded', form);
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
  getDefaultFields: function () {
    try {
      return JSON.parse(wc_address_i18n_params.locale).default;
    } catch (error) {
      console.log('getDefaultFields', error);
    }
  },
  init: function () {
    wcsdmFrontendForm.bindEvents();
    wcsdmFrontendForm.loadForm();
  }
};

$(document).ready(wcsdmFrontendForm.init);
