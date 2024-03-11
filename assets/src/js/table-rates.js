/**
 * Table Rates
 */
var wcsdmTableRates = {
  params: {},
  errorId: 'wcsdm-errors-rate-fields',
  sortableTimer: null,
  init: function (params) {
    wcsdmTableRates.params = params;

    wcsdmTableRates.hideAdvancedRate();

    // Show advanced rate form
    $(document).off('click', '.wcsdm-action-link--show_advanced_rate', wcsdmTableRates.showAdvancedRate);
    $(document).on('click', '.wcsdm-action-link--show_advanced_rate', wcsdmTableRates.showAdvancedRate);

    // Close advanced rate form
    $(document).off('click', '#wcsdm-btn--cancel-advanced', wcsdmTableRates.closeAdvancedRate);
    $(document).on('click', '#wcsdm-btn--cancel-advanced', wcsdmTableRates.closeAdvancedRate);

    // Apply advanced rate
    $(document).off('click', '#wcsdm-btn--apply-advanced', wcsdmTableRates.applyAdvanced);
    $(document).on('click', '#wcsdm-btn--apply-advanced', wcsdmTableRates.applyAdvanced);

    // Add rate row
    $(document).off('click', '#wcsdm-btn--add-rate', wcsdmTableRates.addRate);
    $(document).on('click', '#wcsdm-btn--add-rate', wcsdmTableRates.addRate);

    // Delete rate row
    $(document).off('click', '#wcsdm-btn--delete-rate-select', wcsdmTableRates.showDeleteRateRowsForm);
    $(document).on('click', '#wcsdm-btn--delete-rate-select', wcsdmTableRates.showDeleteRateRowsForm);

    // Cancel delete rate row
    $(document).off('click', '#wcsdm-btn--delete-rate-cancel', wcsdmTableRates.deleteRateCancel);
    $(document).on('click', '#wcsdm-btn--delete-rate-cancel', wcsdmTableRates.deleteRateCancel);

    // Confirm delete rate row
    $(document).off('click', '#wcsdm-btn--delete-rate-confirm', wcsdmTableRates.deleteRateConfirm);
    $(document).on('click', '#wcsdm-btn--delete-rate-confirm', wcsdmTableRates.deleteRateConfirm);

    // Toggle selected rows
    $(document).off('change', '#wcsdm-table--table_rates--dummy thead .select-item', wcsdmTableRates.selectItemHead);
    $(document).on('change', '#wcsdm-table--table_rates--dummy thead .select-item', wcsdmTableRates.selectItemHead);

    // Toggle selected row
    $(document).off('change', '#wcsdm-table--table_rates--dummy tbody .select-item', wcsdmTableRates.selectItemBody);
    $(document).on('change', '#wcsdm-table--table_rates--dummy tbody .select-item', wcsdmTableRates.selectItemBody);

    // Handle change event dummy rate field
    $(document).off('focus', '.wcsdm-field--context--dummy.wcsdm-field--context--dummy--max_distance');
    $(document).on('focus', '.wcsdm-field--context--dummy.wcsdm-field--context--dummy--max_distance', function () {
      $(this).data('value', $(this).val());
    });

    $(document).off('blur', '.wcsdm-field--context--dummy.wcsdm-field--context--dummy--max_distance');
    $(document).on('blur', '.wcsdm-field--context--dummy.wcsdm-field--context--dummy--max_distance', function () {
      $(this).data('value', undefined);
    });

    $(document).off('input', '.wcsdm-field--context--dummy:not(a)');
    $(document).on('input', '.wcsdm-field--context--dummy:not(a)', wcsdmDebounce(function (e) {
      wcsdmTableRates.handleRateFieldDummy(e);
    }, 800));

    var validateDummyFieldsTimer;

    $(document.body).on('wc_add_error_tip', function (event, $input) {
      if (event.type !== 'wc_add_error_tip' || !$input.is('.wcsdm-field--context--dummy')) {
        return;
      }

      clearTimeout(validateDummyFieldsTimer);

      validateDummyFieldsTimer = setTimeout(function () {
        $input.trigger('input');

        if ($input.val().length) {
          wcsdmTableRates.sortRateRows();
        }
      }, 800);
    });

    // Toggle selected row
    $(document).off('change', '#woocommerce_wcsdm_distance_unit', wcsdmTableRates.initForm);
    $(document).on('change', '#woocommerce_wcsdm_distance_unit', wcsdmTableRates.initForm);

    wcsdmTableRates.initForm();

    if (!$('#wcsdm-table--table_rates--dummy tbody tr').length) {
      wcsdmTableRates.addRateRow();
    }

    wcsdmTableRates.sortRateRows();
  },
  initForm: function () {
    var distanceUnit = $('#woocommerce_wcsdm_distance_unit').val();
    var $distanceUnitFields = $('#woocommerce_wcsdm_distance_unit').data('fields');

    if (_.has($distanceUnitFields, 'label')) {
      var targets = $distanceUnitFields.label.targets;
      var value = $distanceUnitFields.label.value;
      _.each(targets, function (target) {
        $(target).text(value[distanceUnit]);
      });
    }

    if (_.has($distanceUnitFields, 'attribute')) {
      var targets = $distanceUnitFields.attribute.targets;
      var value = $distanceUnitFields.attribute.value;
      _.each(targets, function (target) {
        $(target).attr('data-unit', value[distanceUnit]);
      });
    }
  },
  addRate: function (e) {
    e.preventDefault();
    $(e.currentTarget).prop('disabled', true);

    wcsdmTableRates.addRateRow();
    wcsdmTableRates.sortRateRows();

    $(e.currentTarget).prop('disabled', false);
  },
  handleRateFieldDummy: function (e) {
    e.preventDefault();

    var $field = $(e.target);
    var $row = $field.closest('tr');
    $row.find('.wcsdm-field--context--hidden[data-id=' + $field.data('id') + ']').val(e.target.value);

    if ($field.hasClass('wcsdm-field--context--dummy--max_distance')) {
      $row.addClass('editing');

      if ($field.val() !== $field.data('value')) {
        wcsdmTableRates.sortRateRows($field);
      }
    }

    wcsdmTableRates.validateRows();
  },
  showAdvancedRate: function (e) {
    e.preventDefault();

    var $advancedRateFieldGroup = $('#woocommerce_wcsdm_field_group_advanced_rate');
    $advancedRateFieldGroup.nextAll('h3, p, .wc-shipping-zone-method-fields, table.form-table').show();
    $advancedRateFieldGroup.prevAll('h3, p, .wc-shipping-zone-method-fields, table.form-table, .wcsdm-notice').hide();

    var $row = $(e.currentTarget).closest('tr').addClass('editing');

    $row.find('.wcsdm-field--context--hidden').each(function () {
      $('.wcsdm-field--context--advanced[data-id=' + $(this).data('id') + ']').val($(this).val());
    });

    wcsdmToggleButtons({
      left: {
        id: 'cancel-advanced',
        label: wcsdmI18n('Cancel'),
      },
      right: {
        id: 'apply-advanced',
        label: wcsdmI18n('Apply Changes'),
      }
    });

    $('.modal-close-link').hide();

    $('.wc-backbone-modal-header').find('h1').append('<span>' + $advancedRateFieldGroup.text() + '</span>');
  },
  applyAdvanced: function (e) {
    e.preventDefault();

    $('.wcsdm-field--context--advanced').each(function () {
      var fieldId = $(this).data('id');
      var fieldValue = $(this).val();

      $('#wcsdm-table--table_rates--dummy tbody tr.editing .wcsdm-field--context--dummy[data-id=' + fieldId + ']:not(a)').val(fieldValue);
      $('#wcsdm-table--table_rates--dummy tbody tr.editing .wcsdm-field--context--hidden[data-id=' + fieldId + ']:not(a)').val(fieldValue);
    });

    wcsdmTableRates.closeAdvancedRate();
  },
  closeAdvancedRate: function () {
    wcsdmTableRates.hideAdvancedRate();

    wcsdmToggleButtons();

    $('.wc-backbone-modal-header').find('h1 span').remove();

    $('.modal-close-link').show();

    $('#wcsdm-table--table_rates--dummy tbody tr.selected').each(function () {
      $(this).find('.select-item').trigger('change');
    });

    wcsdmTableRates.scrollToTableRate();
    wcsdmTableRates.sortRateRows();
    wcsdmTableRates.validateRows();
  },
  hideAdvancedRate: function () {
    var $advancedRateFieldGroup = $('#woocommerce_wcsdm_field_group_advanced_rate');
    $advancedRateFieldGroup.hide().nextAll('h3, p, .wc-shipping-zone-method-fields, table.form-table').hide();
    $advancedRateFieldGroup.prevAll('h3, p, .wc-shipping-zone-method-fields, table.form-table, .wcsdm-notice').show();
  },
  highlightRow: function () {
    var $row = $('#wcsdm-table--table_rates--dummy tbody tr.editing').removeClass('editing');

    if ($row.length) {
      $row.addClass('highlighted');

      setTimeout(function () {
        $row.removeClass('highlighted');
      }, 1500);
    }
  },
  addRateRow: function () {
    var $lastRow = $('#wcsdm-table--table_rates--dummy tbody tr:last-child');

    $('#wcsdm-table--table_rates--dummy tbody').append(wp.template('wcsdm-dummy-row'));

    if ($lastRow) {
      $lastRow.find('.wcsdm-field--context--hidden:not(a)').each(function () {
        var $field = $(this);
        var fieldId = $field.data('id');
        var fieldValue = fieldId === 'woocommerce_wcsdm_max_distance' ? Math.ceil((parseInt($field.val(), 10) * 1.8)) : $field.val();
        $('#wcsdm-table--table_rates--dummy tbody tr:last-child .wcsdm-field[data-id=' + fieldId + ']').val(fieldValue);
      });
    }

    wcsdmTableRates.setRowNumber();
    wcsdmTableRates.scrollToTableRate();

    wcsdmTableRates.initForm();
  },
  showDeleteRateRowsForm: function (e) {
    e.preventDefault();

    var $heading = $('#woocommerce_wcsdm_field_group_table_rates');

    $heading.hide().prevAll().hide();
    $heading.next('p').hide();

    $('.wc-backbone-modal-header').find('h1').append('<span>' + $heading.text() + '</span><span>Delete</span>');
    $('#wcsdm-table--table_rates--dummy tbody tr:not(.selected)').hide();
    $('#wcsdm-table--table_rates--dummy').find('.select-item').prop('disabled', true);

    wcsdmToggleButtons({
      left: {
        id: 'delete-rate-cancel',
        label: wcsdmI18n('Cancel'),
      },
      right: {
        id: 'delete-rate-confirm',
        label: wcsdmI18n('Confirm Delete'),
      }
    });

    wcsdmTableRates.hideError();
  },
  deleteRateCancel: function (e) {
    e.preventDefault();

    var $heading = $('#woocommerce_wcsdm_field_group_table_rates');

    $heading.show().prevAll().show();
    $heading.next('p').show();

    $('.wc-backbone-modal-header').find('h1 span').remove();
    $('#wcsdm-table--table_rates--dummy tbody tr').show();
    $('#wcsdm-table--table_rates--dummy').find('.select-item').prop('disabled', false);

    $('#wcsdm-table--table_rates--dummy tbody tr.selected').each(function () {
      $(this).find('.select-item:checked').trigger('change');
    });

    wcsdmTableRates.sortRateRows();
    wcsdmTableRates.scrollToTableRate();
    wcsdmTableRates.validateRows();
  },
  deleteRateConfirm: function (e) {
    e.preventDefault();

    $('#wcsdm-table--table_rates--dummy tbody .select-item:checked').closest('tr').remove();

    if (!$('#wcsdm-table--table_rates--dummy tbody tr').length) {
      $('#wcsdm-table--table_rates--dummy thead .select-item:checked').prop('checked', false).trigger('change');
      wcsdmTableRates.addRateRow();
    }

    wcsdmToggleButtons();

    wcsdmTableRates.setRowNumber();

    wcsdmTableRates.deleteRateCancel(e);
  },
  selectItemHead: function (e) {
    e.preventDefault();

    var isChecked = $(e.target).is(':checked');

    $('#wcsdm-table--table_rates--dummy tbody tr').each(function () {
      wcsdmTableRates.toggleRowSelected($(this), isChecked);
    });

    if (isChecked) {
      wcsdmToggleButtons({
        left: {
          id: 'delete-rate-select',
          label: wcsdmI18n('Delete Selected Rates'),
        }
      });
    } else {
      wcsdmToggleButtons();
    }
  },
  selectItemBody: function (e) {
    e.preventDefault();

    var $field = $(e.target);
    var $row = $(e.target).closest('tr');

    wcsdmTableRates.toggleRowSelected($row, $field.is(':checked'));

    if ($('#wcsdm-table--table_rates--dummy tbody .select-item:checked').length) {
      wcsdmToggleButtons({
        left: {
          id: 'delete-rate-select',
          label: wcsdmI18n('Delete Selected Rates'),
        }
      });
    } else {
      wcsdmToggleButtons();
    }

    var isBulkChecked = $('#wcsdm-table--table_rates--dummy tbody .select-item').length === $('#wcsdm-table--table_rates--dummy tbody .select-item:checked').length;

    $('#wcsdm-table--table_rates--dummy thead .select-item').prop('checked', isBulkChecked);
  },
  toggleRowSelected: function ($row, isChecked) {
    $row.find('.wcsdm-field--context--dummy').prop('disabled', isChecked);

    if (isChecked) {
      $row.addClass('selected').find('.select-item').prop('checked', isChecked);
      $row.find('.wcsdm-action-link').addClass('wcsdm-disabled');
    } else {
      $row.removeClass('selected').find('.select-item').prop('checked', isChecked);
      $row.find('.wcsdm-action-link').removeClass('wcsdm-disabled');
    }
  },
  sortRateRows: function ($fieldFocus) {

    var rows = $('#wcsdm-table--table_rates--dummy > tbody > tr').get().sort(function (a, b) {

      var aMaxDistance = $(a).find('.wcsdm-field--context--dummy--max_distance').val();
      var bMaxDistance = $(b).find('.wcsdm-field--context--dummy--max_distance').val();

      var aIndex = $(a).find('.wcsdm-field--context--dummy--max_distance').index();
      var bIndex = $(b).find('.wcsdm-field--context--dummy--max_distance').index();

      if (isNaN(aMaxDistance) || !aMaxDistance.length) {
        return 2;
      }

      aMaxDistance = parseFloat(aMaxDistance);
      bMaxDistance = parseFloat(bMaxDistance);

      if (aMaxDistance < bMaxDistance) {
        return -1;
      }

      if (aMaxDistance > bMaxDistance) {
        return 1;
      }

      if (aIndex < bIndex) {
        return -1;
      }

      if (aIndex > bIndex) {
        return 1;
      }

      return 0;
    });

    var maxDistances = {};

    $.each(rows, function (index, row) {
      var maxDistance = $(row).find('.wcsdm-field--context--dummy--max_distance').val();

      if (!maxDistances[maxDistance]) {
        maxDistances[maxDistance] = [];
      }

      maxDistances[maxDistance].push($(row));

      $(row).addClass('wcsdm-rate-row-index--' + index).attr('data-max-distance', maxDistance).appendTo($('#wcsdm-table--table_rates--dummy').children('tbody')).fadeIn('slow');
    });

    _.each(maxDistances, function (rows) {
      _.each(rows, function (row) {
        if (rows.length > 1) {
          $(row).addClass('wcsdm-sort-enabled');
          $(row).find('.wcsdm-action-link--sort_rate').removeClass('wcsdm-action-link--sort_rate--disabled');
        } else {
          $(row).removeClass('wcsdm-sort-enabled');
          $(row).find('.wcsdm-action-link--sort_rate').addClass('wcsdm-action-link--sort_rate--disabled');
        }
      });
    });

    clearTimeout(wcsdmTableRates.sortableTimer);

    wcsdmTableRates.sortableTimer = setTimeout(function () {
      wcsdmTableRates.setRowNumber();
      wcsdmTableRates.highlightRow();

      if ($('#wcsdm-table--table_rates--dummy > tbody').sortable('instance')) {
        $('#wcsdm-table--table_rates--dummy > tbody').sortable('destroy');
      }

      $('#wcsdm-table--table_rates--dummy tbody').sortable({
        scroll: false,
        cursor: 'move',
        axis: 'y',
        placeholder: 'ui-state-highlight',
        items: 'tr.wcsdm-sort-enabled',
        start: function (event, ui) {
          if (ui.item.hasClass('wcsdm-sort-enabled')) {
            $(event.currentTarget).find('tr').each(function () {
              if (ui.item.attr('data-max-distance') === $(this).attr('data-max-distance')) {
                $(this).addClass('sorting');
              } else {
                $(this).removeClass('sorting');
              }
            });

            $('#wcsdm-table--table_rates--dummy > tbody').sortable('option', 'items', 'tr.wcsdm-sort-enabled.sorting').sortable('refresh');
          } else {
            $('#wcsdm-table--table_rates--dummy > tbody').sortable('cancel');
          }
        },
        stop: function () {
          $('#wcsdm-table--table_rates--dummy > tbody').sortable('option', 'items', 'tr.wcsdm-sort-enabled').sortable('refresh').find('tr').removeClass('sorting');
          wcsdmTableRates.setRowNumber();
        },
      }).disableSelection();

      if ($fieldFocus) {
        $fieldFocus.focus();
      }
    }, 100);
  },
  scrollToTableRate: function () {
    $('.wc-modal-shipping-method-settings').scrollTop($('.wc-modal-shipping-method-settings').find('form').outerHeight());
  },
  validateRows: function () {
    wcsdmTableRates.hideError();

    var uniqueKeys = {};
    var ratesData = [];

    $('#wcsdm-table--table_rates--dummy > tbody > tr').each(function () {
      var $row = $(this);
      var rowIndex = $row.index();
      var rowData = {
        index: rowIndex,
        error: false,
        fields: {},
      };

      var uniqueKey = [];

      $row.find('input.wcsdm-field--context--hidden').each(function () {
        var $field = $(this);
        var fieldTitle = $field.data('title');
        var fieldKey = $field.data('key');
        var fieldId = $field.data('id');
        var fieldValue = $field.val().trim();

        var fieldData = {
          title: fieldTitle,
          value: fieldValue,
          key: fieldKey,
          id: fieldId,
        };

        if ($field.hasClass('wcsdm-field--is-required') && fieldValue.length < 1) {
          fieldData.error = wcsdmTableRates.rateRowError(rowIndex, wcsdmSprintf(wcsdmI18n('%s field is required'), fieldTitle));
        }

        if (!fieldData.error && fieldValue.length) {
          if ($field.data('type') === 'number' && isNaN(fieldValue)) {
            fieldData.error = wcsdmTableRates.rateRowError(rowIndex, wcsdmSprintf(wcsdmI18n('%s field value must be numeric'), fieldTitle));
          }

          var fieldValueInt = parseInt(fieldValue, 10);

          if (typeof $field.attr('min') !== 'undefined' && fieldValueInt < parseInt($field.attr('min'), 10)) {
            fieldData.error = wcsdmTableRates.rateRowError(rowIndex, wcsdmSprintf(wcsdmI18n('%1$s field value cannot be lower than %2$d'), fieldTitle, $field.attr('min')));
          }

          if (typeof $field.attr('max') !== 'undefined' && fieldValueInt > parseInt($field.attr('max'), 10)) {
            fieldData.error = wcsdmTableRates.rateRowError(rowIndex, wcsdmSprintf(wcsdmI18n('%1$s field value cannot be greater than %2$d'), fieldTitle, $field.attr('max')));
          }
        }

        if ($field.data('is_rule') && fieldValue.length) {
          uniqueKey.push(wcsdmSprintf('%s__%s', fieldKey, fieldValue));
        }

        rowData.fields[fieldKey] = fieldData;
      });

      if (uniqueKey.length) {
        var uniqueKeyString = uniqueKey.join('___');

        if (_.has(uniqueKeys, uniqueKeyString)) {
          var duplicateKeys = [];

          for (var i = 0; i < uniqueKey.length; i++) {
            var keySplit = uniqueKey[i].split('__');
            var title = $row.find('input.wcsdm-field--context--hidden[data-key="' + keySplit[0] + '"]').data('title');

            duplicateKeys.push(title);
          }

          rowData.error = wcsdmTableRates.rateRowError(rowIndex, wcsdmSprintf(wcsdmI18n('Shipping rules combination duplicate with rate row #%1$d: %2$s'), wcsdmTableRates.indexToNumber(uniqueKeys[uniqueKeyString]), duplicateKeys.join(', ')));
        } else {
          uniqueKeys[uniqueKeyString] = rowIndex;
        }
      }

      ratesData.push(rowData);
    });

    var errorText = '';

    _.each(ratesData, function (rowData) {
      if (rowData.error) {
        errorText += wcsdmSprintf('<p>%s</p>', rowData.error.message);
      }

      _.each(rowData.fields, function (field) {
        if (field.error) {
          errorText += wcsdmSprintf('<p>%s</p>', field.error.message);
        }
      });
    });

    if (!errorText) {
      return true;
    }

    $('#woocommerce_wcsdm_field_group_table_rates').next('p').after('<div class="error notice wcsdm-notice">' + errorText + '</div>');
  },
  rateRowError: function (rowIndex, errorMessage) {
    return new Error(wcsdmSprintf(wcsdmI18n('Table rate row #%1$d: %2$s'), wcsdmTableRates.indexToNumber(rowIndex), errorMessage));
  },
  hideError: function () {
    $('#woocommerce_wcsdm_field_group_table_rates').next('p').next('.wcsdm-notice').remove();
  },
  setRowNumber: function () {
    $('#wcsdm-table--table_rates--dummy > tbody > tr').each(function () {
      $(this).find('.wcsdm-col--type--row_number').text(($(this).index() + 1));
    });
  },
  indexToNumber: function (rowIndex) {
    return (rowIndex + 1);
  },
};
