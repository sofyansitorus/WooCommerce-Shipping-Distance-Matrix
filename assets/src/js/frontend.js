
var wcsdmFrontendForm = {
  bindEvents: function () {
    $(document.body).off('updated_wc_div updated_shipping_method', wcsdmFrontendForm.loadForm);
    $(document.body).on('updated_wc_div updated_shipping_method', wcsdmFrontendForm.loadForm);
  },
  loadForm: function () {
    $.each(wcsdmFrontendVars.forms, function (prefix, form) {
      var $wrapper = $(form.wrapper);

      if (!$wrapper || !$wrapper.length) {
        return;
      }

      // Add address_1 & address_2 fields to calc_shipping form
      if (prefix === 'calc_shipping') {
        $fieldInsertAfter = null;

        $.each(form.fields, function (fieldKey, field) {
          var $field = $wrapper.find('#calc_shipping_' + fieldKey + '_field').first();

          if ($field && $field.length) {
            $fieldInsertAfter = $field;
            return;
          }

          if (!$fieldInsertAfter) {
            $fieldInsertAfter = $wrapper.find('#calc_shipping_country_field').first();
          }

          if (!$fieldInsertAfter || !$fieldInsertAfter.length) {
            return;
          }

          var fieldTemplate = wp.template('wcsdm-calc-shipping-custom-field--' + fieldKey);

          if (typeof fieldTemplate !== 'function') {
            return;
          }

          $fieldInsertAfter.after(fieldTemplate($.extend({}, field, {
            fieldKey: fieldKey,
            value: $('#wcsdm-calc-shipping-value-field--' + fieldKey).val(),
          })));
        });
      }

      $(document.body).trigger('wcsdm_form_loaded_' + prefix, form);
      $(document.body).trigger('wcsdm_form_loaded', form, prefix);
    });
  },
  init: function () {
    wcsdmFrontendForm.bindEvents();
    wcsdmFrontendForm.loadForm();
  }
};

$(document).ready(wcsdmFrontendForm.init);
