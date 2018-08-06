;(function($) {
"use strict";

// Returns a function, that, as long as it continues to be invoked, will not
// be triggered. The function will be called after it stops being called for
// N milliseconds. If `immediate` is passed, trigger the function on the
// leading edge, instead of the trailing.
function debounce(func, wait, immediate) {
	var timeout;
	return function () {
		var context = this, args = arguments;
		var later = function () {
			timeout = null;
			if (!immediate) {
				func.apply(context, args);
			}
		};
		var callNow = immediate && !timeout;
		clearTimeout(timeout);
		timeout = setTimeout(later, wait);
		if (callNow) {
			func.apply(context, args);
		}
	};
}

// Attrs
$.fn.attrs = function (attrs) {
	var t = $(this);
	var results = {};
	if (attrs) {
		// Set attributes
		t.each(function (i, e) {
			var j = $(e);
			for (var attr in attrs) {
				j.attr(attr, attrs[attr]);
			}
		});
		result = t;
	} else {
		// Get attributes
		var a = {},
			r = t.get(0);
		if (r) {
			r = r.attributes;
			for (var i in r) {
				var p = r[i];
				if (typeof p.nodeValue !== 'undefined') a[p.nodeName] = p.nodeValue;
			}
		}
		results = a;
	}

	if (!Object.keys(results).length) {
		return results;
	}

	var data = {};
	Object.keys(results).forEach(function (key) {
		if (key.indexOf('data-') !== 0) {
			data[key] = results[key];
		}
	});

	return data;
};

// Taking Over window.console.error
var isMapError = false, timerDistanceMatrix;
var windowConsoleError = window.console.error;
window.console.error = function () {
	if (!isMapError && arguments[0].toLowerCase().indexOf('google') !== 1) {
		isMapError = true;
		wcsdmSetting._showMapError(arguments[0]);
	}
	windowConsoleError.apply(windowConsoleError, arguments);
};

var rowIndex;
var rowScrollTop = 0;

var wcsdmSetting = {
	_zoomLevel: 16,
	_defaultLat: -6.175392,
	_defaultLng: 106.827153,
	init: function (params) {
		wcsdmSetting.params = params;

		// Try show settings modal on settings page.
		if (wcsdmSetting.params.showSettings) {
			setTimeout(function () {
				var isMethodAdded = false;
				var methods = $(document).find('.wc-shipping-zone-method-type');
				for (var i = 0; i < methods.length; i++) {
					var method = methods[i];
					if ($(method).text() === wcsdmSetting.params.methodTitle) {
						$(method).closest('tr').find('.row-actions .wc-shipping-zone-method-settings').trigger('click');
						isMethodAdded = true;
						return;
					}
				}
				// Show Add shipping method modal if the shipping is not added.
				if (!isMethodAdded) {
					$('.wc-shipping-zone-add-method').trigger('click');
					$('select[name="add_method_id"]').val(wcsdmSetting.params.methodId).trigger('change');
				}
			}, 200);
		}

		// Handle setting link clicked.
		$(document).on('click', '.wc-shipping-zone-method-settings', function () {
			var methodTitle = $(this).closest('tr').find('.wc-shipping-zone-method-type').text();
			if (methodTitle !== wcsdmSetting.params.methodTitle) {
				return false;
			}
			setTimeout(function () {
				$('.wcsdm-rate-field--distance').trigger('change');
				$('#woocommerce_wcsdm_gmaps_api_units').trigger('change');
				$('#woocommerce_wcsdm_gmaps_api_key').trigger('input');
				$('#btn-ok').hide().after(wp.template('btn-save-changes'));
				wcsdmSetting._toggleNoRatesRow();
			}, 100);
		});

		// Handle on API Key field setting changed.
		$(document).on('input', '#woocommerce_wcsdm_gmaps_api_key', debounce(function () {
			wcsdmSetting._initGoogleMaps();
		}, 500));

		// Handle on distance unit field setting changed.
		$(document).on('change', '#woocommerce_wcsdm_gmaps_api_units', function () {
			$('option[value="per_unit"]').text(wcsdmSetting.params.i18n.distance[$(this).val()].perUnit);
		});

		// Handle on distance field changed.
		$(document).on('change input', '.wcsdm-rate-field--distance', function (e) {
			var $inputTarget = $(e.currentTarget);
			var inputVal = parseInt($inputTarget.val(), 10);
			if (inputVal < 10) {
				$inputTarget.attr('step', '1');
			} else if (inputVal >= 10 && inputVal <= 50) {
				$inputTarget.attr('step', '5');
			} else {
				$inputTarget.attr('step', '10');
			}
			var dataChange = $inputTarget.data('change');
			if (typeof dataChange === 'undefined') {
				$inputTarget.attr('data-change', $inputTarget.val());
			} else if (dataChange !== $inputTarget.val()) {
				$inputTarget.attr('data-change', $inputTarget.val());
				$inputTarget.addClass('changed').closest('tr').addClass('changed');
			}
		});

		// Sort rows based distance field on blur.
		$(document).on('blur', '.wcsdm-rate-field--dummy--distance.changed', function (e) {
			$('#btn-save-changes').prop('disabled', true);
			var rows = $('#wcsdm-table-rates > tbody > tr').addClass('sorting').get().sort(function (a, b) {
				var valueADistance = parseInt($(a).find('.wcsdm-rate-field--dummy--distance').val(), 10);
				var valueBDistance = parseInt($(b).find('.wcsdm-rate-field--dummy--distance').val(), 10);

				if (isNaN(valueADistance)) {
					return 2;
				}

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
				$('#btn-save-changes').prop('disabled', false);
				$('#wcsdm-error').remove();
				$('#wcsdm-table-rates > tbody > tr').removeClass('changed sorting error').find('td,th').removeClass('error').find('.wcsdm-rate-field--dummy--distance').removeClass('changed');
			}, 800);
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
		$(document).on('change input', '.wcsdm-rate-field--dummy', function (e) {
			var $input = $(e.currentTarget);
			var inputVal = $input.val();
			var inputKey = $input.attr('data-key');
			$input.closest('tr').find('.wcsdm-rate-field--hidden--' + inputKey).val(inputVal);
		});

		// Handle on advanced rate settings link clicked.
		$(document).on('click', '.advanced-rate-settings', function (e) {
			e.preventDefault();
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
			$section.find('.wcsdm-table-row-advanced').show().siblings().hide();
			$('#btn-save-changes').hide().after(wp.template('btn-apply-changes'));
		});

		// Handle on Apply Changes button clicked.
		$(document).on('click', '#btn-apply-changes', function (e) {
			e.preventDefault();
			$('#wcsdm-error').remove();
			$('#wcsdm-table-advanced tbody').find('tr, td').removeClass('error');

			var errors = wcsdmSetting._validateForm($('.wcsdm-rate-field--advanced'));

			if (errors.length) {
				var errorMessages = {};

				for (let index = 0; index < errors.length; index++) {
					errorMessages[errors[index].key] = errors[index].message;
				}

				var errorMessage = '';
				Object.keys(errorMessages).forEach(function (key) {
					$('.wcsdm-rate-field--advanced--' + key).closest('tr').addClass('error');
					errorMessage += '<p id="wcsdm-rate-field--error--' + key + '">' + errorMessages[key] + '</p>';
				});

				$('#wcsdm-table-advanced').before(wp.template('wcsdm-error')({
					title: wcsdm_params.i18n.errors.error_title,
					content: errorMessage,
				}));
				return;
			}

			var formData = wcsdmSetting._populateFormData($('.wcsdm-rate-field--advanced'));

			if (Object.keys(formData).length) {
				Object.keys(formData).forEach(function (key) {
					$('#wcsdm-table-rates tbody tr:eq(' + rowIndex + ')').removeClass('error').addClass('applied').find('.wcsdm-rate-field--' + key).val(formData[key]).trigger('change');
				});

				$(e.currentTarget).closest('section').find('.wcsdm-table-row-advanced').hide().siblings().show();
				$(e.currentTarget).closest('div').remove();
				$('#btn-save-changes').show();

				$('.wc-modal-shipping-method-settings').animate({
					scrollTop: rowScrollTop,
				}, 500);

				$('#wcsdm-table-rates tbody tr:eq(' + rowIndex + ')').removeClass('applied').find('.wcsdm-rate-field--dummy--distance').focus().trigger('change').blur();
			}
		});

		// Handle on Cancel Changes button clicked.
		$(document).on('click', '#btn-cancel-changes', function (e) {
			$(e.currentTarget).closest('section').find('.wcsdm-table-row-advanced').hide().siblings().show();
			$(e.currentTarget).closest('div').remove();
			$('#btn-save-changes').show();

			$('.wc-modal-shipping-method-settings').animate({
				scrollTop: rowScrollTop,
			}, 500);
		});

		// Handle on Save Changes button clicked.
		$(document).on('click', '#btn-save-changes', function (e) {
			e.preventDefault();
			$('#wcsdm-error').remove();
			$('#wcsdm-table-rates tbody').find('tr, td, th').removeClass('error');

			var errors = wcsdmSetting._validateForm($('.wcsdm-rate-field--hidden'));

			if (errors.length) {
				var errorMessages = {};

				for (let index = 0; index < errors.length; index++) {
					$('#wcsdm-table-rates tbody tr:eq(' + errors[index].rowIndex + ')').find('.col-' + errors[index].key).addClass('error');
					errorMessages[errors[index].key] = errors[index].message;
				}

				var errorMessage = '';
				Object.keys(errorMessages).forEach(function (key) {
					errorMessage += '<p id="wcsdm-rate-field--error--' + key + '">' + errorMessages[key] + '</p>';
				});

				$('#wcsdm-table-rates').before(wp.template('wcsdm-error')({
					title: wcsdm_params.i18n.errors.error_title,
					content: errorMessage,
				}));
				return;
			}

			$('#btn-ok').trigger('click');
		});

		// Handle toggle rate rows in bulk.
		$(document).on('change', '#wcsdm-table-rates thead .select-item', wcsdmSetting._toggleRateRowsBulk);

		// Handle toggle rate rows individually.
		$(document).on('change', '#wcsdm-table-rates tbody .select-item', wcsdmSetting._toggleRateRows);

		// Handle add rate rows.
		$(document).on('click', '#wcsdm-table-rates .button.add_row', wcsdmSetting._addRateRows);

		// Handle remove rate rows.
		$(document).on('click', '#wcsdm-table-rates .button.remove_rows', wcsdmSetting._removeRateRows);
	},
	_validateForm: function ($fields) {
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
				rowIndex: $(field).closest('tr').index(),
			}));
		});

		Object.keys(fields).forEach(function (key) {
			var dataRows = fields[key];
			for (let index = 0; index < dataRows.length; index++) {
				var dataRow = dataRows[index];
				var ignoreField = false;
				var showIf = dataRow.show_if || false;
				var hideIf = dataRow.hide_if || false;

				if (showIf) {
					Object.keys(showIf).forEach(function (showIfKey) {
						var showIfTarget = fields[showIfKey][index].value;
						if (showIf[showIfKey].indexOf(showIfTarget) === -1) {
							ignoreField = true;
						}
					});
				}

				if (hideIf) {
					Object.keys(hideIf).forEach(function (hideIfKey) {
						var hideIfTarget = fields[hideIfKey][index].value;
						if (hideIf[hideIfKey].indexOf(hideIfTarget) !== -1) {
							ignoreField = true;
						}
					});
				}

				if (!ignoreField) {
					try {
						var rowValue = dataRow.value || '';
						var rowRequired = dataRow.required || false;

						if (!rowValue.length && rowRequired) {
							throw new Error(wcsdm_params.i18n.errors.field_required.replace('%s', dataRow.title));
						}

						if (rowValue.length) {
							if (dataRow.type === 'number') {
								var costType = fields['cost_type'][index].value;
								var costField = dataRow.cost_field || false;
								if (costType === 'formula' && costField) {
									var matches = rowValue.match(/([0-9]|[\*\+\-\/\(\)]|\{d\}|\{w\}|\{a\}|\{q\})+/gs);
									if (!matches.length || matches[0] !== rowValue) {
										throw new Error(wcsdm_params.i18n.errors.field_invalid.replace('%s', dataRow.title));
									}

									if (rowValue.indexOf('(') !== -1 || rowValue.indexOf(')') !== -1) {
										var opening = rowValue.replace(/[^\(]+/g, '');
										var closing = rowValue.replace(/[^\)]+/g, '');
										if (opening.length !== closing.length) {
											throw new Error(wcsdm_params.i18n.errors.field_invalid.replace('%s', dataRow.title));
										}

										var cleaned = rowValue.replace(/\((?:[^()]|\([^()]*\))*\)/g, '');
										if (cleaned && (cleaned.indexOf('(') !== -1 || cleaned.indexOf(')'))) {
											throw new Error(wcsdm_params.i18n.errors.field_invalid.replace('%s', dataRow.title));
										}
									}
								} else {
									if (isNaN(rowValue)) {
										throw new Error(wcsdm_params.i18n.errors.field_invalid.replace('%s', dataRow.title));
									}

									if (!isNaN(dataRow.min) && parseFloat(rowValue) < parseFloat(dataRow.min)) {
										throw new Error(wcsdm_params.i18n.errors.field_min_value.replace('%$1s', dataRow.title).replace('%$2d', dataRow.min));
									}

									if (!isNaN(dataRow.max) && parseFloat(rowValue) < parseFloat(dataRow.max)) {
										throw new Error(wcsdm_params.i18n.errors.field_max_value.replace('%$1s', dataRow.title).replace('%$2d', dataRow.max));
									}
								}
							} else if (dataRow.type === 'object') {
								if (typeof dataRow.options[rowValue] === 'undefined') {
									throw new Error(wcsdm_params.i18n.errors.field_invalid.replace('%s', dataRow.title));
								}
							}
						}
					} catch (error) {
						errors.push({
							key: key,
							message: error.message,
							rowIndex: dataRow.rowIndex,
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
	_initGoogleMaps: function () {
		$('#wcsdm-error').remove();
		$('#wcsdm-map-spinner').show();
		$('#wcsdm-map-wrapper').hide().empty();
		$('#wcsdm-lat-lng-wrap').hide();

		var apiKey = $('#woocommerce_wcsdm_gmaps_api_key').val();
		if (!apiKey.length) {
			$('#wcsdm-map-wrapper').closest('tr').hide();
			return;
		}

		$('#wcsdm-map-wrapper').closest('tr').show();

		isMapError = false;

		window.google = undefined;

		var mapScriptUrl = 'https://maps.googleapis.com/maps/api/js?libraries=geometry,places&key=' + apiKey + '&language=' + wcsdmSetting.params.language;
		$.getScript(mapScriptUrl, function () {
			wcsdmSetting._buildGoogleMaps();
		});
	},
	_buildGoogleMaps: function () {
		var regExp = /^[-+]?[0-9]{1,7}(\.[0-9]+)?$/;
		var curLat = $('#woocommerce_wcsdm_origin_lat').val();
		var curLng = $('#woocommerce_wcsdm_origin_lng').val();
		curLat = curLat.length && regExp.exec(curLat) ? parseFloat(curLat) : wcsdmSetting._defaultLat;
		curLng = curLng.length && regExp.exec(curLng) ? parseFloat(curLng) : wcsdmSetting._defaultLng;
		var curLatLng = { lat: curLat, lng: curLng };
		var tmplMapCanvas = wp.template('wcsdm-map-canvas');
		var tmplMapSearch = wp.template('wcsdm-map-search');
		if (!$('#wcsdm-map-canvas').length) {
			$('#wcsdm-map-wrapper').append(tmplMapCanvas());
		}
		var markers = [];
		var map = new google.maps.Map(
			document.getElementById('wcsdm-map-canvas'),
			{
				center: curLatLng,
				zoom: wcsdmSetting._zoomLevel,
				mapTypeId: 'roadmap'
			}
		);
		var marker = new google.maps.Marker({
			map: map,
			position: curLatLng,
			draggable: true,
			icon: wcsdmSetting.params.marker
		});

		var infowindow = new google.maps.InfoWindow({ maxWidth: 350 });

		if (curLat === wcsdmSetting._defaultLat && curLng === wcsdmSetting._defaultLng) {
			infowindow.setContent(wcsdmSetting.params.i18n.dragMarker);
			infowindow.open(map, marker);
		} else {
			wcsdmSetting._setLatLng(marker.position, marker, map, infowindow);
		}

		google.maps.event.addListener(marker, 'dragstart', function () {
			infowindow.close();
		});

		google.maps.event.addListener(marker, 'dragend', function (event) {
			wcsdmSetting._setLatLng(event.latLng, marker, map, infowindow);
		});

		markers.push(marker);

		if (!$('#wcsdm-map-search').length) {
			$('#wcsdm-map-wrapper').append(tmplMapSearch());
		}
		// Create the search box and link it to the UI element.
		var inputAddress = document.getElementById('wcsdm-map-search');
		var searchBox = new google.maps.places.SearchBox(inputAddress);
		map.controls[google.maps.ControlPosition.TOP_LEFT].push(inputAddress);
		// Bias the SearchBox results towards current map's viewport.
		map.addListener('bounds_changed', function () {
			searchBox.setBounds(map.getBounds());
		});
		// Listen for the event fired when the user selects a prediction and retrieve more details for that place.
		searchBox.addListener('places_changed', function () {
			var places = searchBox.getPlaces();
			if (places.length === 0) {
				return;
			}
			// Clear out the old markers.
			markers.forEach(function (marker) {
				marker.setMap(null);
			});
			markers = [];
			// For each place, get the icon, name and location.
			var bounds = new google.maps.LatLngBounds();
			places.forEach(function (place) {
				if (!place.geometry) {
					console.log('Returned place contains no geometry');
					return;
				}
				marker = new google.maps.Marker({
					map: map,
					position: place.geometry.location,
					draggable: true,
					icon: wcsdmSetting.params.marker
				});
				wcsdmSetting._setLatLng(place.geometry.location, marker, map, infowindow);
				google.maps.event.addListener(marker, 'dragstart', function () {
					infowindow.close();
				});
				google.maps.event.addListener(marker, 'dragend', function (event) {
					wcsdmSetting._setLatLng(event.latLng, marker, map, infowindow);
				});
				// Create a marker for each place.
				markers.push(marker);
				if (place.geometry.viewport) {
					// Only geocodes have viewport.
					bounds.union(place.geometry.viewport);
				} else {
					bounds.extend(place.geometry.location);
				}
			});
			map.fitBounds(bounds);
		});

		// Test API Key for Google Distance Matrix Service API
		clearTimeout(timerDistanceMatrix);
		timerDistanceMatrix = setTimeout(function () {
			if (!isMapError) {
				var origin = new google.maps.LatLng(-6.1762256, 106.82295120000003);
				var destination = new google.maps.LatLng(-6.194891755155254, 106.81979692219852);
				var service = new google.maps.DistanceMatrixService();
				service.getDistanceMatrix(
					{
						origins: [origin],
						destinations: [destination],
						travelMode: 'DRIVING',
						unitSystem: google.maps.UnitSystem.METRIC
					}, function (response, status) {
						if (status === 'OK') {
							$('#wcsdm-map-spinner').hide();
							$('#wcsdm-map-wrapper').show();
							$('#wcsdm-lat-lng-wrap').show();
						} else {
							console.log('DistanceMatrixRequestError', { status: status, response: response });
						}
					});
			}
		}, 800);
	},
	_showMapError: function (errorMsg) {
		$('#wcsdm-map-spinner').hide();
		$('#wcsdm-map-wrapper').empty().hide();
		$('#wcsdm-lat-lng-wrap').hide().find('.origin-coordinates').val('');
		var regExpTitle = /^([A-Za-z0-9\s]+):/;
		var regExpLink = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;

		$('#wcsdm-map-wrapper').before(wp.template('wcsdm-error')({
			title: errorMsg.match(regExpTitle)[0] || 'Error',
			content: errorMsg.replace(regExpTitle, '').replace(regExpLink, '<a href="$1" target="_blank">$1</a>'),
		}));
	},
	_setLatLng: function (location, marker, map, infowindow) {
		var geocoder = new google.maps.Geocoder();
		geocoder.geocode(
			{
				latLng: location
			},
			function (results, status) {
				if (status === google.maps.GeocoderStatus.OK && results[0]) {
					infowindow.setContent(results[0].formatted_address);
					infowindow.open(map, marker);
					marker.addListener('click', function () {
						infowindow.open(map, marker);
					});
				}
			}
		);
		map.setCenter(location);
		$('#woocommerce_wcsdm_origin_lat').val(location.lat());
		$('#woocommerce_wcsdm_origin_lng').val(location.lng());
	},
	_toggleRateRowsBulk: function (e) {
		var $this = $(e.currentTarget);
		var $checkbox = $this.closest('table').find('tbody input[type=checkbox]');
		if (!$checkbox.length) {
			$this.prop('checked', !$this.is(':checked'));
			return;
		}
		$checkbox.prop('checked', $this.is(':checked')).trigger('change');
		if ($this.is(':checked')) {
			$('.wc-modal-shipping-method-settings').animate({
				scrollTop: $('.wc-modal-shipping-method-settings').find('form').outerHeight()
			}, 500);
		}
	},
	_toggleRateRows: function (e) {
		var $this = $(e.currentTarget);
		var $table = $this.closest('table');
		var $thead = $table.find('thead');
		var $tbody = $table.find('tbody');
		var $tfoot = $table.find('tfoot');

		$this.closest('tr').removeClass('applied').siblings().removeClass('applied');

		if ($this.is(':checked')) {
			$this.closest('tr').addClass('selected');
		} else {
			$this.closest('tr').removeClass('selected');
		}

		if ($tbody.find('[type=checkbox]:checked').length) {
			$tfoot.find('.add_row').hide('fast', function () {
				$(this).siblings('.remove_rows').show();
			});
		} else {
			$tfoot.find('.remove_rows').hide('fast', function () {
				$(this).siblings('.add_row').show();
			});
		}

		if ($tbody.find('[type=checkbox]:checked').length === $tbody.find('[type=checkbox]').length) {
			$thead.find('input[type=checkbox]').prop('checked', true);
		} else {
			$thead.find('input[type=checkbox]').prop('checked', false);
		}
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
		wcsdmSetting._toggleNoRatesRow();
	},
	_removeRateRows: function (e) {
		e.preventDefault();
		var $this = $(e.currentTarget);
		var $table = $this.closest('table');
		$this.hide('fast', function () {
			$(this).siblings('.add_row').show();
		});
		$table.find('thead [type=checkbox]').prop('checked', false);
		$table.find('tbody [type=checkbox]:checked').each(function (index, checkbox) {
			$(checkbox).closest('tr').remove();
		});
		wcsdmSetting._toggleNoRatesRow();
	},
	_toggleNoRatesRow: function () {
		if ($('#wcsdm-table-rates tbody tr').length) {
			$('#row-heading-bottom').hide().closest('table').find('thead, tbody, tfoot .row-heading-both').show();
			$('#btn-save-changes').show();
		} else {
			$('#row-heading-bottom').show().find('.add_row').show().closest('table').find('thead, tbody, tfoot .row-heading-both').hide();
			$('#btn-save-changes').hide();
		}
	}
};

$(document).ready(function () {
	wcsdmSetting.init(wcsdm_params);
});
}(jQuery));
