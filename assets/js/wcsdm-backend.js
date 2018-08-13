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

// jQuery Function to get element attributes
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
var isMapError = false;
var windowConsoleError = window.console.error;
window.console.error = function () {
	if (!isMapError && arguments[0].toLowerCase().indexOf('google') !== 1) {
		isMapError = true;
		wcsdmMap.showMapError(arguments[0]);
	}
	windowConsoleError.apply(windowConsoleError, arguments);
};

var currentLat;
var currentLng;

var wcsdmMap = {
	zoomLevel: 16,
	defaultLat: -6.175392,
	defaultLng: 106.827153,
	init: function (params) {
		wcsdmMap.params = params;

		// Handle setting link clicked.
		$(document).on('click', '.wc-shipping-zone-method-settings', function () {
			// Bail early if the link clicked others shipping method
			var methodTitle = $(this).closest('tr').find('.wc-shipping-zone-method-type').text();
			if (methodTitle !== wcsdmMap.params.methodTitle) {
				return false;
			}

			currentLat = $('#woocommerce_wcsdm_origin_lat').val();
			currentLng = $('#woocommerce_wcsdm_origin_lng').val();

			$('#wcsdm-col-store-location').empty().append(wp.template('wcsdm-lat-lng-table')({
				origin_lat: currentLat,
				origin_lng: currentLng,
				hideButton: false
			}));
		});

		$(document).on('click', '#wcsdm-btn-map-picker', function (e) {
			e.preventDefault();
			$('#wcsdm-map-picker-canvas').empty();
			$('#wcsdm-row-api-key').show().siblings().hide();
			$('#wcsdm-col-store-location').empty();
			$('#map-picker-lat-lng').empty().append(wp.template('wcsdm-lat-lng-table')({
				origin_lat: currentLat,
				origin_lng: currentLng,
				hideButton: true
			}));
			$('#wcsdm-buttons-footer-primary').remove();
			$('#btn-ok').after(wp.template('wcsdm-buttons-footer-advanced')({
				id_cancel: 'wcsdm-btn-map-cancel',
				id_apply: 'wcsdm-btn-map-apply'
			}));
			$('#woocommerce_wcsdm_gmaps_api_key_dummy').val($('#woocommerce_wcsdm_gmaps_api_key').val()).trigger('input');
		});

		$(document).on('click', '#wcsdm-btn-map-cancel', function (e) {
			e.preventDefault();
			$('#wcsdm-error').remove();
			$(e.currentTarget).closest('div').remove();
			$('#wcsdm-col-store-location').empty().append(wp.template('wcsdm-lat-lng-table')({
				origin_lat: currentLat,
				origin_lng: currentLng,
				hideButton: false
			}));
			$('#wcsdm-map-picker-canvas').empty();
			$('#wcsdm-row-api-key').hide().siblings().not('.wcsdm-row--hidden').show();
			$('#btn-ok').after(wp.template('wcsdm-buttons-footer-primary'));
		});

		$(document).on('click', '#wcsdm-btn-map-apply', function (e) {
			e.preventDefault();
			var $button = $(e.currentTarget).prop('disable', true);
			$('#wcsdm-error').remove();
			var errors = {};
			var requiredFields = [
				'woocommerce_wcsdm_gmaps_api_key_dummy',
				'woocommerce_wcsdm_origin_lat_dummy',
				'woocommerce_wcsdm_origin_lng_dummy'
			];

			for (var i = 0; i < requiredFields.length; i++) {
				var requiredFieldKey = requiredFields[i];
				var $requiredField = $('#' + requiredFieldKey);
				if (!$requiredField.val().length) {
					errors[requiredFieldKey] = wcsdmMap.params.i18n.errors.field_required.replace('%s', $requiredField.data('title'));
				}
			}

			if (Object.keys(errors).length) {
				var errorMessage = '';
				Object.keys(errors).forEach(function (key) {
					$('.wcsdm-rate-field--advanced--' + key).closest('tr').addClass('error');
					errorMessage += '<p id="wcsdm-rate-field--error--' + key + '">' + errors[key] + '</p>';
				});

				$('#wcsdm-table-map-picker').before(wp.template('wcsdm-error')({
					title: wcsdmMap.params.i18n.errors.error_title,
					content: errorMessage
				}));
				$button.prop('disable', false);
				return;
			}

			var testService = new google.maps.DistanceMatrixService();
			testService.getDistanceMatrix(
				{
					origins: [new google.maps.LatLng(-6.1762256, 106.82295120000003)],
					destinations: [new google.maps.LatLng(-6.194891755155254, 106.81979692219852)],
					travelMode: 'DRIVING',
					unitSystem: google.maps.UnitSystem.METRIC
				}, function (response, status) {
					console.log('DistanceMatrixTestRequest', { status: status, response: response });
					if (status === 'OK') {
						$button.closest('div').remove();

						var newApiKey = $('#woocommerce_wcsdm_gmaps_api_key_dummy').val();
						var newLat = $('#woocommerce_wcsdm_origin_lat_dummy').val();
						var newLng = $('#woocommerce_wcsdm_origin_lng_dummy').val();

						$('#wcsdm-col-store-location').empty().append(wp.template('wcsdm-lat-lng-table')({
							origin_lat: newLat,
							origin_lng: newLng,
							hideButton: false
						}));

						$('#wcsdm-row-api-key').hide().siblings().not('.wcsdm-row--hidden').show();
						$('#btn-ok').after(wp.template('wcsdm-buttons-footer-primary'));
						$('#woocommerce_wcsdm_gmaps_api_key').val(newApiKey);
						$('#woocommerce_wcsdm_origin_lat').val(newLat);
						$('#woocommerce_wcsdm_origin_lng').val(newLng);
						$('#wcsdm-map-picker-canvas').empty();
					} else {
						$button.prop('disable', false);
					}
				});
		});

		// Handle on API Key field setting changed.
		$(document).on('input', '#woocommerce_wcsdm_gmaps_api_key_dummy', debounce(function () {
			wcsdmMap.initGoogleMaps();
		}, 500));
	},
	initGoogleMaps: function () {
		$('#wcsdm-error').remove();
		$('#wcsdm-map-picker-canvas').removeClass('empty').empty();

		var apiKey = $('#woocommerce_wcsdm_gmaps_api_key_dummy').val();
		if (!apiKey.length) {
			$('#wcsdm-map-picker-canvas').addClass('empty').append($('#wcsdm-map-picker-instruction').html());
			return;
		}

		isMapError = false;

		window.google = undefined;

		var mapScriptUrl = 'https://maps.googleapis.com/maps/api/js?libraries=geometry,places&key=' + apiKey + '&language=' + wcsdmMap.params.language;
		$.getScript(mapScriptUrl, function () {
			wcsdmMap.buildGoogleMaps();
		});
	},
	buildGoogleMaps: function () {
		var regExp = /^[-+]?[0-9]{1,7}(\.[0-9]+)?$/;
		var curLat = $('#woocommerce_wcsdm_origin_lat').val();
		var curLng = $('#woocommerce_wcsdm_origin_lng').val();
		curLat = curLat.length && regExp.exec(curLat) ? parseFloat(curLat) : wcsdmMap.defaultLat;
		curLng = curLng.length && regExp.exec(curLng) ? parseFloat(curLng) : wcsdmMap.defaultLng;
		var curLatLng = { lat: curLat, lng: curLng };
		var markers = [];
		var map = new google.maps.Map(
			document.getElementById('wcsdm-map-picker-canvas'),
			{
				center: curLatLng,
				zoom: wcsdmMap.zoomLevel,
				mapTypeId: 'roadmap'
			}
		);

		var marker = new google.maps.Marker({
			map: map,
			position: curLatLng,
			draggable: true,
			icon: wcsdmMap.params.marker
		});

		var infowindow = new google.maps.InfoWindow({ maxWidth: 350 });

		if (curLat === wcsdmMap.defaultLat && curLng === wcsdmMap.defaultLng) {
			infowindow.setContent(wcsdmMap.params.i18n.dragMarker);
			infowindow.open(map, marker);
		} else {
			wcsdmMap.setLatLng(marker.position, marker, map, infowindow);
		}

		google.maps.event.addListener(marker, 'dragstart', function () {
			infowindow.close();
		});

		google.maps.event.addListener(marker, 'dragend', function (event) {
			wcsdmMap.setLatLng(event.latLng, marker, map, infowindow);
		});

		markers.push(marker);

		$('#wcsdm-map-picker-canvas').append(wp.template('wcsdm-map-search'));

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
					icon: wcsdmMap.params.marker
				});
				wcsdmMap.setLatLng(place.geometry.location, marker, map, infowindow);
				google.maps.event.addListener(marker, 'dragstart', function () {
					infowindow.close();
				});
				google.maps.event.addListener(marker, 'dragend', function (event) {
					wcsdmMap.setLatLng(event.latLng, marker, map, infowindow);
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
	},
	setLatLng: function (location, marker, map, infowindow) {
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

		$('#woocommerce_wcsdm_origin_lat_dummy').val(location.lat());
		$('#woocommerce_wcsdm_origin_lng_dummy').val(location.lng());
	},
	showMapError: function (errorMsg) {
		var regExpLink = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
		$('.gm-err-message').empty().append(errorMsg.replace(regExpLink, '<a href="$1" target="_blank">$1</a>'));
		$('#woocommerce_wcsdm_origin_lat_dummy').val('');
		$('#woocommerce_wcsdm_origin_lng_dummy').val('');
	}
};

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

$(document).ready(function () {
	wcsdmMap.init(wcsdm_params);
	wcsdmTableRates.init(wcsdm_params);
	// Try show settings modal on settings page.
	if (wcsdm_params.showSettings) {
		setTimeout(function () {
			var isMethodAdded = false;
			var methods = $(document).find('.wc-shipping-zone-method-type');
			for (var i = 0; i < methods.length; i++) {
				var method = methods[i];
				if ($(method).text() === wcsdm_params.methodTitle) {
					$(method).closest('tr').find('.row-actions .wc-shipping-zone-method-settings').trigger('click');
					isMethodAdded = true;
					return;
				}
			}
			// Show Add shipping method modal if the shipping is not added.
			if (!isMethodAdded) {
				$('.wc-shipping-zone-add-method').trigger('click');
				$('select[name="add_method_id"]').val(wcsdm_params.methodId).trigger('change');
			}
		}, 200);
	}
});
}(jQuery));
