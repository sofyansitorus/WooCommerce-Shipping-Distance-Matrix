/**
 * Table Rates
 */
var wcsdmTableRates = {
  params: {},
  errorId: 'wcsdm-errors-rate-fields',
  init: function (params) {
    wcsdmTableRates.params = params;

    // Show advanced rate form
    $(document).off('click', '.wcsdm-link--advanced-rate', wcsdmTableRates.showAdvancedRateForm);
    $(document).on('click', '.wcsdm-link--advanced-rate', wcsdmTableRates.showAdvancedRateForm);

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

    wcsdmTableRates.scrollToTableRate();

    wcsdmTableRates.initForm();
  },
  showDeleteRateRowsForm: function (e) {
    e.preventDefault();

    $('#wcsdm-table--table_rates--dummy tbody .select-item:not(:checked)').closest('tr').hide();
    $('#wcsdm-table--table_rates--dummy').find('.wcsdm-col--select-item, .wcsdm-col--link_advanced').hide();
    $('#wcsdm-table--table_rates--dummy').find('.wcsdm-col--select-item, .wcsdm-col--link_sort').hide();
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
  },
  closeDeleteRateRowsForm: function (e) {
    e.preventDefault();

    $('#wcsdm-table--table_rates--dummy tbody tr').show();
    $('#wcsdm-table--table_rates--dummy').find('.wcsdm-col--select-item, .wcsdm-col--link_advanced').show();
    $('#wcsdm-table--table_rates--dummy').find('.wcsdm-col--select-item, .wcsdm-col--link_sort').show();
    $('#wcsdm-field-group-wrap--table_rates').siblings().not('.wcsdm-hidden').fadeIn();

    $('#wcsdm-field-group-wrap--table_rates').find('p').first().removeClass('wcsdm-hidden');
    $('#wcsdm-field-group-wrap--table_rates').find('.wc-settings-sub-title').first().removeClass('wcsdm-hidden');

    $('.wc-backbone-modal-header').find('h1 span').remove();

    $('#wcsdm-table--table_rates--dummy tbody tr.selected').each(function () {
      $(this).find('.select-item').trigger('change');
    });

    wcsdmTableRates.scrollToTableRate();
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

      $(row).addClass('wcsdm-rate-row-index--' + index).appendTo($('#wcsdm-table--table_rates--dummy').children('tbody')).fadeIn('slow');
    });

    _.each(maxDistances, function (rows) {
      _.each(rows, function (row) {
        if (rows.length > 1) {
          $(row).addClass('wcsdm-sort-enabled').find('a.wcsdm-col--link_sort').prop('enable', true);
        } else {
          $(row).removeClass('wcsdm-sort-enabled').find('a.wcsdm-col--link_sort').prop('enable', false);
        }
      });
    });

    setTimeout(function () {
      wcsdmTableRates.highlightRow();

      if (!$('#wcsdm-table--table_rates--dummy > tbody').sortable('instance')) {
        $(function () {
          var oldIndex = null;
          var maxDistance = null;

          $('#wcsdm-table--table_rates--dummy tbody').sortable({
            items: 'tr.wcsdm-sort-enabled:not(.selected)',
            cursor: 'move',
            classes: {
              "ui-sortable": "highlight"
            },
            placeholder: "ui-state-highlight",
            axis: "y",
            start: function (event, ui) {
              if ($(event.target).closest('tr').hasClass('selected')) {
                $(event.target).sortable('cancel');
              } else {
                oldIndex = ui.item.index();

                maxDistance = $('#wcsdm-table--table_rates--dummy tbody tr')
                  .eq(oldIndex)
                  .find('[data-id="woocommerce_wcsdm_max_distance"]')
                  .val();
              }
            },
            change: function (event, ui) {
              if (!maxDistance) {
                $(event.target).sortable('cancel');
              } else {
                var newIndex = ui.placeholder.index();
                var rowIndex = newIndex > oldIndex ? (newIndex - 1) : (newIndex + 1);

                var newMaxDistance = $('#wcsdm-table--table_rates--dummy tbody tr')
                  .eq(rowIndex)
                  .find('[data-id="woocommerce_wcsdm_max_distance"]')
                  .val();

                if (maxDistance !== newMaxDistance) {
                  $(event.target).sortable('cancel');
                }
              }
            },
          });
          $('#wcsdm-table--table_rates--dummy tbody').disableSelection();
        });
      }

      if ($fieldFocus) {
        $fieldFocus.focus();
      }
    }, 100);
  },
  scrollToTableRate: function () {
    $('.wc-modal-shipping-method-settings').scrollTop($('.wc-modal-shipping-method-settings').find('form').outerHeight());
  },
  hasError: function () {
    $('#woocommerce_wcsdm_field_group_table_rates').next('p').next('.wcsdm-notice').remove();

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
            if (uniqueKey[i].indexOf('max_distance') === -1) {
              var keySplit = uniqueKey[i].split('__');
              var title = $row.find('input.wcsdm-field--context--hidden[data-key="' + keySplit[0] + '"]').data('title');

              duplicateKeys.push(wcsdmSprintf('%s: %s', title, keySplit[1]));
            }
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

    if (errorText) {
      return $('#woocommerce_wcsdm_field_group_table_rates').next('p').after('<div class="error notice wcsdm-notice has-margin">' + errorText + '</div>');
    }

    return false;
  },
  rateRowError: function (rowIndex, errorMessage) {
    return new Error(wcsdmSprintf(wcsdmError('table_rate_row'), wcsdmTableRates.indexToNumber(rowIndex), errorMessage));
  },
  indexToNumber: function (rowIndex) {
    return (rowIndex + 1);
  },
};
