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
        .wrap('<div id="wcsdm-field-group-wrap--' + fieldGroupId + '" class="wcsdm-field-group-wrap stuffbox wcsdm-field-group-wrap--' + fieldGroupId + '"></div>');

      $fieldGroupDescription
        .appendTo('#wcsdm-field-group-wrap--' + fieldGroupId);

      $fieldGroupTable
        .appendTo('#wcsdm-field-group-wrap--' + fieldGroupId);

      if ($fieldGroupTable && $fieldGroupTable.length) {
        if ($fieldGroup.hasClass('wcsdm-field-group-hidden')) {
          $('#wcsdm-field-group-wrap--' + fieldGroupId)
            .addClass('wcsdm-hidden');
        }
      } else {
        $('#wcsdm-field-group-wrap--' + fieldGroupId).remove();
      }
    });

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
  },
  maybeOpenModal: function () {
    // Try show settings modal on settings page.
    if (wcsdmBackendVars.showSettings) {
      setTimeout(function () {
        var isMethodAdded = false;
        var methods = $(document).find('.wc-shipping-zone-method-type');
        for (var i = 0; i < methods.length; i++) {
          var method = methods[i];
          if ($(method).text() === wcsdmBackendVars.methodTitle) {
            $(method).closest('tr').find('.row-actions .wc-shipping-zone-method-settings').trigger('click');
            isMethodAdded = true;
            return;
          }
        }

        // Show Add shipping method modal if the shipping is not added.
        if (!isMethodAdded) {
          $('.wc-shipping-zone-add-method').trigger('click');
          $('select[name="add_method_id"]').val(wcsdmBackendVars.methodId).trigger('change');
        }
      }, 500);
    }
  },
  submitForm: function (e) {
    e.preventDefault();

    if (wcsdmMapPicker.editingAPIKey || wcsdmMapPicker.editingAPIKeyPicker) {
      window.alert(wcsdmError('finish_editing_api'));
    } else {
      if (!wcsdmTableRates.validateRows()) {
        window.alert(wcsdmError('table_rates_invalid'));
      } else {
        $('#btn-ok').trigger('click');
      }
    }
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
