(function ($, wcsdmBackendVars) {
  /**
 * Backend Scripts
 */

  function submitForm(e) {
    e.preventDefault();

     if (!wcsdmTableRates.validateRows()) {
      window.alert(wcsdmI18n('Table rates data is incomplete or invalid!'));
    } else {
      $('#btn-ok').trigger('click');
    }
  }

  function toggleStoreOriginFields(e) {
    e.preventDefault();

    var selected = $(this).val();
    var fields = $(this).data('fields');
    _.each(fields, function (fieldIds, fieldValue) {
      _.each(fieldIds, function (fieldId) {
        if (fieldValue !== selected) {
          if ($('#' + fieldId).closest('tr').length) {
            $('#' + fieldId).closest('tr').hide();
          } else {
            $('label[for="' + fieldId + '"]').hide().next().hide();
          }
        } else {
          if ($('#' + fieldId).closest('tr').length) {
            $('#' + fieldId).closest('tr').show();
          } else {
            $('label[for="' + fieldId + '"]').show().next().show();
          }
        }
      });
    });
  }

  function removeEmptyRows() {
    $('.wc-modal-shipping-method-settings table.form-table:empty').remove();
    $('.wc-shipping-zone-method-fields:empty').remove();
  }

  function renderForm() {
    if (!$('#woocommerce_wcsdm_origin_type') || !$('#woocommerce_wcsdm_origin_type').length) {
      return;
    }

    removeEmptyRows();

    // Submit form
    $(document).off('click', '#wcsdm-btn--save-settings', submitForm);
    $(document).on('click', '#wcsdm-btn--save-settings', submitForm);

    // Toggle Store Origin Fields
    $(document).off('change', '#woocommerce_wcsdm_origin_type', toggleStoreOriginFields);
    $(document).on('change', '#woocommerce_wcsdm_origin_type', toggleStoreOriginFields);

    $('#woocommerce_wcsdm_origin_type').trigger('change');

    var params = _.mapObject(wcsdmBackendVars, function (val, key) {
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

    wcsdmToggleButtons();
  }

  function initForm() {
    // Init form
    $(document.body).off('wc_backbone_modal_loaded', renderForm);
    $(document.body).on('wc_backbone_modal_loaded', renderForm);
  }

  var initFormTimeout;

  function initFormDebounce() {
    if (initFormTimeout) {
      clearTimeout(initFormTimeout);
    }

    initFormTimeout = setTimeout(initForm, 100);
  }

  $(document).ready(initForm);

  $(window).on('resize orientationchange', initFormDebounce);
})(jQuery, window.wcsdmBackendVars);
