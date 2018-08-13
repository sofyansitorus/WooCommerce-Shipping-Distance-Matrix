var rowIndex;
var rowScrollTop = 0;

var wcsdmTableRates = {
	init: function (params) {
		wcsdmTableRates.params = params;

		// Handle setting link clicked.
		$(document).on('click', '.wc-shipping-zone-method-settings', function () {
			// Bail early if the link clicked others shipping method
			var methodTitle = $(this).closest('tr').find('.wc-shipping-zone-method-type').text();
			if (methodTitle !== wcsdmTableRates.params.methodTitle) {
				return false;
			}

			$('.wcsdm-rate-field--hidden--select').each(function (i, input) {
				var $input = $(input);
				var inputKey = $input.data('key');
				var inputValue = $input.val();
				$input.closest('tr').find('.wcsdm-rate-field--dummy--' + inputKey).val(inputValue);
			});
			$('#woocommerce_wcsdm_gmaps_api_units').trigger('change');
			$('#btn-ok').hide().after(wp.template('wcsdm-buttons-footer-primary'));

			setTimeout(function () {
				$('.wcsdm-rate-field--dummy--select').trigger('change');
			}, 100);
		});

		// Handle on distance field changed.
		$(document).on('change input', '.wcsdm-rate-field--dummy--distance', function (e) {
			var $inputTarget = $(e.currentTarget);
			var dataChange = $inputTarget.data('change');
			if (typeof dataChange === 'undefined') {
				$inputTarget.attr('data-change', $inputTarget.val());
			} else if (dataChange !== $inputTarget.val()) {
				$inputTarget.attr('data-change', $inputTarget.val());
				$inputTarget.addClass('changed').closest('tr').addClass('changed');
			}
		});

		// Handle on distance unit field setting changed.
		$(document).on('change', '#woocommerce_wcsdm_gmaps_api_units.changed', function () {
			$('option[value="per_unit"]').text(wcsdmTableRates.params.i18n.distance[$(this).val()].perUnit);
		});

		// Handle on Rates per Shipping CLass option field value changed.
		$(document).on('change', '.wcsdm-rate-field--advanced', function () {
			$('.wcsdm-rate-field--advanced').each(function (i, field) {
				$(field).closest('tr').show();
				var showField = true;
				var fieldShowIf = $(field).data('show_if');
				if (fieldShowIf) {
					Object.keys(fieldShowIf).forEach(function (key) {
						var fieldShowIfTarget = $('.wcsdm-rate-field--advanced--' + key).val();
						if (fieldShowIf[key].indexOf(fieldShowIfTarget) === -1) {
							showField = false;
						}
					});
				}

				if (showField) {
					var fieldHideIf = $(field).data('hide_if');
					if (fieldHideIf) {
						Object.keys(fieldHideIf).forEach(function (key) {
							var fieldHideIfTarget = $('.wcsdm-rate-field--advanced--' + key).val();
							if (fieldHideIf[key].indexOf(fieldHideIfTarget) !== -1) {
								showField = false;
							}
						});
					}
				}

				if (!showField) {
					$(field).closest('tr').hide();
				} else {
					$(field).closest('tr').show();
				}
			});
		});

		// Handle on Calculation Method option field value changed.
		$(document).on('change', '.wcsdm-rate-field--advanced--cost_type', function (e) {
			switch ($(e.currentTarget).val()) {
				case 'formula':
					$(e.currentTarget).closest('table').find('.wcsdm-rate-field--advanced.wcsdm-cost-field').attr('type', 'text');
					break;

				default:
					$(e.currentTarget).closest('table').find('.wcsdm-rate-field--advanced.wcsdm-cost-field').attr('type', 'number');
					break;
			}
		});

		// Handle on Calculation Method option field value changed.
		$(document).on('change', '.wcsdm-rate-field--dummy--cost_type', function (e) {
			switch ($(e.currentTarget).val()) {
				case 'formula':
					$(e.currentTarget).closest('tr').find('.wcsdm-rate-field--dummy.wcsdm-cost-field').attr('type', 'text');
					break;

				default:
					$(e.currentTarget).closest('tr').find('.wcsdm-rate-field--dummy.wcsdm-cost-field').attr('type', 'number');
					break;
			}
		});

		// Handle on dummy field value changed.
		$(document).on('change input', '.wcsdm-rate-field--dummy', debounce(function (e) {
			e.preventDefault();

			var $input = $(e.currentTarget);
			var inputVal = $input.val();
			var inputKey = $input.attr('data-key');
			$input.closest('tr').find('.wcsdm-rate-field--hidden--' + inputKey).val(inputVal);

			wcsdmTableRates._validateRatesList();
		}, 500));

		// Sort rows based distance field on blur.
		$(document).on('blur', '.wcsdm-rate-field--dummy--distance', function (e) {
			if ($(e.currentTarget).val().length) {
				wcsdmTableRates._sortRates();
			}
		});

		// Handle on advanced rate settings link clicked.
		$(document).on('click', '.wcsdm-btn-advanced-link', function (e) {
			e.preventDefault();
			$('#wcsdm-error').remove();
			var $row = $(e.currentTarget).closest('tr').removeClass('applied');
			$row.siblings().removeClass('applied');
			$row.find('.wcsdm-rate-field--hidden').each(function (i, input) {
				var $input = $(input);
				var inputVal = $input.val();
				var inputKey = $input.attr('data-key');
				var $inputTarget = $('.wcsdm-rate-field--advanced--' + inputKey).val(inputVal).trigger('change');
				if ($row.find('.col-' + inputKey).hasClass('error')) {
					$inputTarget.closest('tr').addClass('error');
				}
			});
			rowIndex = $row.index();
			rowScrollTop = Math.abs($row.closest('form').position().top);
			var $section = $(e.currentTarget).closest('section');
			$section.find('.wcsdm-row-advanced').show().siblings().hide();
			$('#wcsdm-buttons-footer-primary').remove();
			$('#btn-ok').hide().after(wp.template('wcsdm-buttons-footer-advanced')({
				id_cancel: 'wcsdm-btn-advanced-cancel',
				id_apply: 'wcsdm-btn-advanced-apply'
			}));
		});

		// Handle on Apply Changes button clicked.
		$(document).on('click', '#wcsdm-btn-advanced-apply', function (e) {
			e.preventDefault();
			$('#wcsdm-error').remove();
			$('#wcsdm-table-advanced tbody').find('tr, td').removeClass('error');

			var errors = wcsdmTableRates._getRateFormErrors($('.wcsdm-rate-field--advanced'));

			if (errors.length) {
				var errorMessages = {};

				for (var index = 0; index < errors.length; index++) {
					errorMessages[errors[index].key] = errors[index].message;
				}

				var errorMessage = '';
				Object.keys(errorMessages).forEach(function (key) {
					$('.wcsdm-rate-field--advanced--' + key).closest('tr').addClass('error');
					errorMessage += '<p id="wcsdm-rate-field--error--' + key + '">' + errorMessages[key] + '</p>';
				});

				$('#wcsdm-table-advanced').before(wp.template('wcsdm-error')({
					title: wcsdmTableRates.params.i18n.errors.error_title,
					content: errorMessage
				}));
				return;
			}

			var formData = wcsdmTableRates._populateFormData($('.wcsdm-rate-field--advanced'));

			if (Object.keys(formData).length) {
				Object.keys(formData).forEach(function (key) {
					$('#wcsdm-table-rates tbody tr:eq(' + rowIndex + ')').removeClass('error').addClass('applied').find('.wcsdm-rate-field--dummy--' + key).val(formData[key]);
					$('#wcsdm-table-rates tbody tr:eq(' + rowIndex + ')').removeClass('error').addClass('applied').find('.wcsdm-rate-field--hidden--' + key).val(formData[key]);
				});

				$(e.currentTarget).closest('div').remove();
				$('#wcsdm-row-advanced').hide().siblings().not('.wcsdm-row--hidden').show();
				$('#btn-ok').after(wp.template('wcsdm-buttons-footer-primary'));

				$('.wc-modal-shipping-method-settings').animate({
					scrollTop: rowScrollTop
				}, 500);

				setTimeout(function () {
					wcsdmTableRates._sortRates();
					wcsdmTableRates._validateRatesList();
				}, 100);
			}
		});

		// Handle on Cancel Changes button clicked.
		$(document).on('click', '#wcsdm-btn-advanced-cancel', function (e) {
			e.preventDefault();
			$(e.currentTarget).closest('div').remove();
			$('#wcsdm-row-advanced').hide().siblings().not('.wcsdm-row--hidden').show();
			$('#wcsdm-table-advanced tr').removeClass('error');
			$('#btn-ok').after(wp.template('wcsdm-buttons-footer-primary'));
			$('#wcsdm-table-rates tbody tr:eq(' + rowIndex + ')').addClass('applied');

			$('.wc-modal-shipping-method-settings').animate({
				scrollTop: rowScrollTop
			}, 500);

			wcsdmTableRates._validateRatesList();

			setTimeout(function () {
				$('#wcsdm-table-rates tbody tr:eq(' + rowIndex + ')').removeClass('applied');
			}, 800);
		});

		// Handle on Save Changes button clicked.
		$(document).on('click', '#wcsdm-btn-primary-save-changes', function (e) {
			e.preventDefault();
			$('#wcsdm-error').remove();
			var errors = wcsdmTableRates._validateRatesList();
			if (errors.length) {
				var errorMessages = {};

				for (var index = 0; index < errors.length; index++) {
					errorMessages[errors[index].key] = errors[index].message;
				}

				var errorMessage = '';
				Object.keys(errorMessages).forEach(function (key) {
					$('.wcsdm-rate-field--advanced--' + key).closest('tr').addClass('error');
					errorMessage += '<p id="wcsdm-rate-field--error--' + key + '">' + errorMessages[key] + '</p>';
				});

				$('#wcsdm-table-rates').before(wp.template('wcsdm-error')({
					title: wcsdmTableRates.params.i18n.errors.error_title,
					content: errorMessage
				}));
				return;
			}

			$('#btn-ok').trigger('click');
		});

		// Handle add rate rows.
		$(document).on('click', '#wcsdm-btn-primary-add-rate', wcsdmTableRates._addRateRows);

		// Handle remove rate rows.
		$(document).on('click', '.btn-delete-rate', wcsdmTableRates._removeRateRows);
	},
	_sortRates: function () {
		$('#wcsdm-btn-primary-save-changes').prop('disabled', true);
		var rows = $('#wcsdm-table-rates > tbody > tr').addClass('sorting').get().sort(function (a, b) {
			var valueADistance = $(a).find('.wcsdm-rate-field--dummy--distance').val();
			var valueBDistance = $(b).find('.wcsdm-rate-field--dummy--distance').val();

			if (isNaN(valueADistance) || !valueADistance.length) {
				return 2;
			}

			valueADistance = parseInt(valueADistance, 10);
			valueBDistance = parseInt(valueBDistance, 10);

			if (valueADistance < valueBDistance) {
				return -1;
			}

			if (valueADistance > valueBDistance) {
				return 1;
			}

			return 0;
		});

		$.each(rows, function (index, row) {
			$('#wcsdm-table-rates').children('tbody').append(row);
		});

		setTimeout(function () {
			$('#wcsdm-btn-primary-save-changes').prop('disabled', false);
			$('#wcsdm-table-rates > tbody > tr').removeClass('changed sorting applied');
			$('#wcsdm-table-rates .wcsdm-rate-field--dummy--distance').removeClass('changed');
		}, 800);
	},
	_validateRatesList: function () {
		// $('#wcsdm-error').remove();
		$('#wcsdm-table-rates tbody').find('tr, td').removeClass('error');
		var errors = wcsdmTableRates._getRateFormErrors($('.wcsdm-rate-field--hidden'));
		if (errors.length) {
			for (var index = 0; index < errors.length; index++) {
				$('#wcsdm-table-rates tbody tr:eq(' + errors[index].rowIndex + ')').find('.col-' + errors[index].key).addClass('error');
			}
		}
		return errors;
	},
	_getRateFormErrors: function ($fields) {
		var errors = [];
		var fields = {};

		// Populate form fields
		$fields.each(function (i, field) {
			var fieldKey = $(field).attr('data-key');
			if (typeof fields[fieldKey] === 'undefined') {
				fields[fieldKey] = [];
			}
			fields[fieldKey].push(Object.assign({}, $(field).attrs(), $(field).data(), {
				value: $(field).val(),
				rowIndex: $(field).closest('tr').index()
			}));
		});

		Object.keys(fields).forEach(function (key) {
			var dataRows = fields[key];
			for (var index = 0; index < dataRows.length; index++) {
				var dataRow = dataRows[index];
				var ignoreField = false;
				var showIf = dataRow.show_if || false;
				var hideIf = dataRow.hide_if || false;

				if (showIf) {
					Object.keys(showIf).forEach(function (showIfKey) {
						var showIfTarget = fields[showIfKey][index].value;
						var showIfField = showIf[showIfKey];
						if (showIfField.indexOf(showIfTarget) === -1) {
							ignoreField = true;
						}
					});
				}

				if (hideIf) {
					Object.keys(hideIf).forEach(function (hideIfKey) {
						var hideIfTarget = fields[hideIfKey][index].value;
						var hideIfField = hideIf[hideIfKey];
						if (hideIfField.indexOf(hideIfTarget) !== -1) {
							ignoreField = true;
						}
					});
				}

				if (!ignoreField) {
					try {
						var rowValue = dataRow.value || '';
						var rowRequired = dataRow.required || false;

						if (!rowValue.length && rowRequired) {
							throw new Error(wcsdmTableRates.params.i18n.errors.field_required.replace('%s', dataRow.title));
						}

						if (rowValue.length) {
							if (dataRow.type === 'number') {
								var costType = fields.cost_type[index].value;
								var costField = dataRow.cost_field || false;
								if (costType === 'formula' && costField) {
									var matches = rowValue.match(/([0-9]|[\*\+\-\/\(\)]|\{d\}|\{w\}|\{a\}|\{q\})+/g);
									if (!matches.length || matches[0] !== rowValue) {
										throw new Error(wcsdmTableRates.params.i18n.errors.field_invalid.replace('%s', dataRow.title));
									}

									if (rowValue.indexOf('(') !== -1 || rowValue.indexOf(')') !== -1) {
										var opening = rowValue.replace(/[^\(]+/g, '');
										var closing = rowValue.replace(/[^\)]+/g, '');
										if (opening.length !== closing.length) {
											throw new Error(wcsdmTableRates.params.i18n.errors.field_invalid.replace('%s', dataRow.title));
										}

										var cleaned = rowValue.replace(/\((?:[^()]|\([^()]*\))*\)/g, '');
										if (cleaned && (cleaned.indexOf('(') !== -1 || cleaned.indexOf(')'))) {
											throw new Error(wcsdmTableRates.params.i18n.errors.field_invalid.replace('%s', dataRow.title));
										}
									}
								} else {
									if (isNaN(rowValue)) {
										throw new Error(wcsdmTableRates.params.i18n.errors.field_invalid.replace('%s', dataRow.title));
									}

									if (!isNaN(dataRow.min) && parseFloat(rowValue) < parseFloat(dataRow.min)) {
										throw new Error(wcsdmTableRates.params.i18n.errors.field_min_value.replace('%$1s', dataRow.title).replace('%$2d', dataRow.min));
									}

									if (!isNaN(dataRow.max) && parseFloat(rowValue) < parseFloat(dataRow.max)) {
										throw new Error(wcsdmTableRates.params.i18n.errors.field_max_value.replace('%$1s', dataRow.title).replace('%$2d', dataRow.max));
									}
								}
							} else if (dataRow.type === 'object') {
								if (typeof dataRow.options[rowValue] === 'undefined') {
									throw new Error(wcsdmTableRates.params.i18n.errors.field_invalid.replace('%s', dataRow.title));
								}
							}
						}
					} catch (error) {
						errors.push({
							key: key,
							message: error.message,
							rowIndex: dataRow.rowIndex
						});
					}
				}
			}
		});

		return errors;
	},
	_populateFormData: function ($fields) {
		var dataForm = {};

		// Populate form data
		$fields.each(function (i, input) {
			dataForm[$(input).attr('data-key')] = $(input).val();
		});

		return dataForm;
	},
	_addRateRows: function (e) {
		e.preventDefault();
		$('#wcsdm-table-rates tbody').append(wp.template('rates-list-input-table-row')).find('tr:last-child .wcsdm-rate-field').each(function (i, input) {
			$(input).trigger('change');
			if ($(input).hasClass('wcsdm-rate-field--distance')) {
				$(input).focus();
			}
		});
		$('#woocommerce_wcsdm_gmaps_api_units').trigger('change');
		$('.wc-modal-shipping-method-settings').scrollTop($('.wc-modal-shipping-method-settings').find('form').outerHeight());
	},
	_removeRateRows: function (e) {
		e.preventDefault();
		$(e.currentTarget).closest('tr').remove();
	}
};
