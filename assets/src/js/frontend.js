
var wcsdmFrontendForm = {
  bindEvents: function () {
    $(document.body).off('updated_wc_div updated_shipping_method', wcsdmFrontendForm.loadForm);
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
        var $fieldToCloneBefore = $wrapper.find('#calc_shipping_city_field, #calc_shipping_postcode_field').first();

        if ($fieldToCloneBefore.length) {
          try {
            var template = wp.template('wcsdm-calc-shipping-custom-field');

            _.each(['address_1', 'address_2'], function (fieldKey) {
              if ($wrapper.find('#calc_shipping_' + fieldKey + '_field').first().length) {
                return;
              }

              var $extraField = $('#wcsdm-calc-shipping-field-value-' + fieldKey);

              if (!$extraField || !$extraField.length) {
                return;
              }

              var fieldData = _.extend({}, $extraField.data('field'), {
                field: fieldKey,
                value: $extraField.val(),
              });

              $fieldToCloneBefore.before(template(fieldData));
            });
          } catch (error) {
            console.log('wcsdmError', error);
          }
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
