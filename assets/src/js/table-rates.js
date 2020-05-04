/**
 * Table Rates
 */
var wcsdmTableRates = {
  params: {},
  errorId: 'wcsdm-errors-rate-fields',
  sortableTimer: null,
  init: function (params) {
    wcsdmTableRates.params = params;

    // Show advanced rate form
    $(document).off('click', '.wcsdm-action-link--show_advanced_rate', wcsdmTableRates.showAdvancedRateForm);
    $(document).on('click', '.wcsdm-action-link--show_advanced_rate', wcsdmTableRates.showAdvancedRateForm);

    // Close advanced rate form
    $(document).off('click', '#wcsdm-btn--cancel-advanced', wcsdmTableRates.closeAdvancedRateForm);
    $(document).on('click', '#wcsdm-btn--cancel-advanced', wcsdmTableRates.closeAdvancedRateForm);

    // Apply advanced rate
    $(document).off('click', '#wcsdm-btn--apply-advanced', wcsdmTableRates.applyAdvancedForm);
    $(document).on('click', '#wcsdm-btn--apply-advanced', wcsdmTableRates.applyAdvancedForm);

    // Add rate row
    $(document).off('click', '#wcsdm-btn--add-rate', wcsdmTableRates.handleAddRateButton);
    $(document).on('click', '#wcsdm-btn--add-rate', wcsdmTableRates.handleAddRateButton);

    // Delete rate row
    $(document).off('click', '#wcsdm-btn--delete-rate-select', wcsdmTableRates.showDeleteRateRowsForm);
    $(document).on('click', '#wcsdm-btn--delete-rate-select', wcsdmTableRates.showDeleteRateRowsForm);

    // Cancel delete rate row
    $(document).off('click', '#wcsdm-btn--delete-rate-cancel', wcsdmTableRates.closeDeleteRateRowsForm);
    $(document).on('click', '#wcsdm-btn--delete-rate-cancel', wcsdmTableRates.closeDeleteRateRowsForm);

    // Confirm delete rate row
    $(document).off('click', '#wcsdm-btn--delete-rate-confirm', wcsdmTableRates.deleteRateRows);
    $(document).on('click', '#wcsdm-btn--delete-rate-confirm', wcsdmTableRates.deleteRateRows);

    // Toggle selected rows
    $(document).off('change', '#wcsdm-table--table_rates--dummy thead .select-item', wcsdmTableRates.toggleRows);
    $(document).on('change', '#wcsdm-table--table_rates--dummy thead .select-item', wcsdmTableRates.toggleRows);

    // Toggle selected row
    $(document).off('change', '#wcsdm-table--table_rates--dummy tbody .select-item', wcsdmTableRates.toggleRow);
    $(document).on('change', '#wcsdm-table--table_rates--dummy tbody .select-item', wcsdmTableRates.toggleRow);

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
    var distanceUnitShort = distanceUnit === 'metric' ? 'km' : 'mi';
    var $distanceUnitFields = $('#woocommerce_wcsdm_distance_unit').data('fields');

    $('.wcsdm-field--context--dummy--max_distance').next('span').remove();
    $('.wcsdm-field--context--dummy--max_distance').addClass('has-unit').after('<span>' + distanceUnitShort + '</span>');

    var label = $distanceUnitFields && _.has($distanceUnitFields.label, distanceUnit) ? $distanceUnitFields.label[distanceUnit] : '';

    if (label && label.length) {
      $.each($distanceUnitFields.targets, function (index, target) {
        $(target).data('index', index).text(label);
      });
    }
  },
  handleAddRateButton: function (e) {
    e.preventDefault();
    $(e.currentTarget).prop('disabled', true);

    wcsdmTableRates.addRateRow();

    $(e.currentTarget).prop('disabled', false);
  },
  handleRateFieldDummy: function (e) {
    e.preventDefault();

    var $field = $(e.target);
    var $row = $field.closest('tr');
    $row.find('.wcsdm-field--context--hidden[data-id=' + $field.data('id') + ']').val(e.target.value);

    if ($field.hasClass('wcsdm-field--context--dummy--max_distance')) {
      $row.addClass('editing');

      if (parseInt($field.val()) !== parseInt($field.data('value'))) {
        wcsdmTableRates.sortRateRows($field);
      }
    }

    wcsdmTableRates.validateRows();
  },
  showAdvancedRateForm: function (e) {
    e.preventDefault();

    var $row = $(e.currentTarget).closest('tr').addClass('editing');

    $row.find('.wcsdm-field--context--hidden').each(function () {
      $('.wcsdm-field--context--advanced[data-id=' + $(this).data('id') + ']').val($(this).val());
    });

    wcsdmToggleButtons({
      left: {
        id: 'cancel-advanced',
        label: 'Cancel',
        icon: 'undo'
      },
      right: {
        id: 'apply-advanced',
        label: 'Apply Changes',
        icon: 'editor-spellcheck'
      }
    });

    $('.modal-close-link').hide();

    $('#wcsdm-field-group-wrap--advanced_rate').fadeIn().siblings('.wcsdm-field-group-wrap').hide();

    var $subTitle = $('#wcsdm-field-group-wrap--advanced_rate').find('.wc-settings-sub-title').first().addClass('wcsdm-hidden');

    $('.wc-backbone-modal-header').find('h1').append('<span>' + $subTitle.text() + '</span>');
  },
  applyAdvancedForm: function (e) {
    e.preventDefault();

    $('.wcsdm-field--context--advanced').each(function () {
      var fieldId = $(this).data('id');
      var fieldValue = $(this).val();

      $('#wcsdm-table--table_rates--dummy tbody tr.editing .wcsdm-field--context--dummy[data-id=' + fieldId + ']:not(a)').val(fieldValue);
      $('#wcsdm-table--table_rates--dummy tbody tr.editing .wcsdm-field--context--hidden[data-id=' + fieldId + ']:not(a)').val(fieldValue);
    });

    wcsdmTableRates.closeAdvancedRateForm(e);
  },
  closeAdvancedRateForm: function (e) {
    e.preventDefault();

    wcsdmToggleButtons();

    $('#wcsdm-field-group-wrap--advanced_rate').hide().siblings('.wcsdm-field-group-wrap').not('.wcsdm-hidden').fadeIn();

    $('#wcsdm-field-group-wrap--advanced_rate').find('.wc-settings-sub-title').first().removeClass('wcsdm-hidden');

    $('.wc-backbone-modal-header').find('h1 span').remove();

    $('.modal-close-link').show();

    $('#wcsdm-table--table_rates--dummy tbody tr.selected').each(function () {
      $(this).find('.select-item').trigger('change');
    });

    wcsdmTableRates.scrollToTableRate();
    wcsdmTableRates.sortRateRows();
    wcsdmTableRates.validateRows();
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

    $('#wcsdm-table--table_rates--dummy tbody tr:not(.selected)').hide();
    $('#wcsdm-table--table_rates--dummy').find('.wcsdm-col--type--select_item, .wcsdm-col--type--action_link').hide();
    $('#wcsdm-field-group-wrap--table_rates').siblings().hide();

    $('#wcsdm-field-group-wrap--table_rates').find('p').first().addClass('wcsdm-hidden');

    var $subTitle = $('#wcsdm-field-group-wrap--table_rates').find('.wc-settings-sub-title').first().addClass('wcsdm-hidden');

    $('.wc-backbone-modal-header').find('h1').append('<span>' + $subTitle.text() + '</span>');

    wcsdmToggleButtons({
      left: {
        id: 'delete-rate-cancel',
        label: 'Cancel',
        icon: 'undo'
      },
      right: {
        id: 'delete-rate-confirm',
        label: 'Confirm Delete',
        icon: 'trash'
      }
    });

    wcsdmTableRates.hideError();
  },
  closeDeleteRateRowsForm: function (e) {
    e.preventDefault();

    $('#wcsdm-table--table_rates--dummy tbody tr').show();
    $('#wcsdm-table--table_rates--dummy').find('.wcsdm-col--type--select_item, .wcsdm-col--type--action_link').show();
    $('#wcsdm-field-group-wrap--table_rates').siblings().not('.wcsdm-hidden').fadeIn();

    $('#wcsdm-field-group-wrap--table_rates').find('p').first().removeClass('wcsdm-hidden');
    $('#wcsdm-field-group-wrap--table_rates').find('.wc-settings-sub-title').first().removeClass('wcsdm-hidden');

    $('.wc-backbone-modal-header').find('h1 span').remove();

    $('#wcsdm-table--table_rates--dummy tbody tr.selected').each(function () {
      $(this).find('.select-item').trigger('change');
    });

    wcsdmTableRates.scrollToTableRate();
    wcsdmTableRates.validateRows();
  },
  deleteRateRows: function (e) {
    e.preventDefault();

    $('#wcsdm-table--table_rates--dummy tbody .select-item:checked').closest('tr').remove();

    if (!$('#wcsdm-table--table_rates--dummy tbody tr').length) {
      if ($('#wcsdm-table--table_rates--dummy thead .select-item').is(':checked')) {
        $('#wcsdm-table--table_rates--dummy thead .select-item').prop('checked', false).trigger('change');
      }

      wcsdmTableRates.addRateRow();
    } else {
      wcsdmToggleButtons();
    }

    wcsdmTableRates.setRowNumber();

    wcsdmTableRates.closeDeleteRateRowsForm(e);
  },
  toggleRows: function (e) {
    e.preventDefault();

    var isChecked = $(e.target).is(':checked');

    $('#wcsdm-table--table_rates--dummy tbody tr').each(function () {
      wcsdmTableRates.toggleRowSelected($(this), isChecked);
    });

    if (isChecked) {
      wcsdmToggleButtons({
        left: {
          id: 'delete-rate-select',
          label: 'Delete Selected Rates',
          icon: 'trash'
        }
      });
    } else {
      wcsdmToggleButtons();
    }
  },
  toggleRow: function (e) {
    e.preventDefault();

    var $field = $(e.target);
    var $row = $(e.target).closest('tr');

    wcsdmTableRates.toggleRowSelected($row, $field.is(':checked'));

    if ($('#wcsdm-table--table_rates--dummy tbody .select-item:checked').length) {
      wcsdmToggleButtons({
        left: {
          id: 'delete-rate-select',
          label: 'Delete Selected Rates',
          icon: 'trash'
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
    } else {
      $row.removeClass('selected').find('.select-item').prop('checked', isChecked);
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

      aMaxDistance = parseInt(aMaxDistance, 10);
      bMaxDistance = parseInt(bMaxDistance, 10);

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
          $(row).addClass('wcsdm-sort-enabled').find('.wcsdm-action-link--sort_rate').prop('enable', true);
        } else {
          $(row).removeClass('wcsdm-sort-enabled').find('.wcsdm-action-link--sort_rate').prop('enable', false);
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
          fieldData.error = wcsdmTableRates.rateRowError(rowIndex, wcsdmSprintf(wcsdmError('field_required'), fieldTitle));
        }

        if (!fieldData.error && fieldValue.length) {
          if ($field.data('validate') === 'number' && isNaN(fieldValue)) {
            fieldData.error = wcsdmTableRates.rateRowError(rowIndex, wcsdmSprintf(wcsdmError('field_numeric'), fieldTitle));
          }

          var fieldValueInt = parseInt(fieldValue, 10);

          if (typeof $field.attr('min') !== 'undefined' && fieldValueInt < parseInt($field.attr('min'), 10)) {
            fieldData.error = wcsdmTableRates.rateRowError(rowIndex, wcsdmSprintf(wcsdmError('field_min_value'), fieldTitle, $field.attr('min')));
          }

          if (typeof $field.attr('max') !== 'undefined' && fieldValueInt > parseInt($field.attr('max'), 10)) {
            fieldData.error = wcsdmTableRates.rateRowError(rowIndex, wcsdmSprintf(wcsdmError('field_max_value'), fieldTitle, $field.attr('max')));
          }
        }

        if ($field.data('is_rule') && fieldValue.length) {
          uniqueKey.push(sprintf('%s__%s', fieldKey, fieldValue));
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

            duplicateKeys.push(wcsdmSprintf('%s: %s', title, keySplit[1]));
          }

          rowData.error = wcsdmTableRates.rateRowError(rowIndex, wcsdmSprintf(wcsdmError('duplicate_rate_row'), wcsdmTableRates.indexToNumber(uniqueKeys[uniqueKeyString]), duplicateKeys.join(', ')));
        } else {
          uniqueKeys[uniqueKeyString] = rowIndex;
        }
      }

      ratesData.push(rowData);
    });

    var errorText = '';

    _.each(ratesData, function (rowData) {
      if (rowData.error) {
        errorText += wcsdmSprintf('<p>%s</p>', rowData.error.toString());
      }

      _.each(rowData.fields, function (field) {
        if (field.error) {
          errorText += wcsdmSprintf('<p>%s</p>', field.error.toString());
        }
      });
    });

    if (!errorText) {
      return true;
    }

    $('#woocommerce_wcsdm_field_group_table_rates').next('p').after('<div class="error notice wcsdm-notice has-margin">' + errorText + '</div>');
  },
  rateRowError: function (rowIndex, errorMessage) {
    return new Error(wcsdmSprintf(wcsdmError('table_rate_row'), wcsdmTableRates.indexToNumber(rowIndex), errorMessage));
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
