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

// Taking Over window.console.error
var isMapError = false, timerDistanceMatrix;
var windowConsoleError = window.console.error;
window.console.error = function () {
	if (arguments[0].indexOf('google.com') !== 1) {
		isMapError = true;
		wcsdmSetting._showMapError(arguments[0]);
	}
	windowConsoleError.apply(windowConsoleError, arguments);
};

var rowIndex;
var rowScrollTop = 0;

var wcsdmSetting = {
	_zoomLevel: 16,
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
			$('.field-distance').trigger('change');
			$('#woocommerce_wcsdm_gmaps_api_units').trigger('change');
			$('#woocommerce_wcsdm_gmaps_api_key').trigger('input');
			wcsdmSetting._toggleNoRatesRow();
		});

		// Handle on API Key field setting changed.
		$(document).on('input', '#woocommerce_wcsdm_gmaps_api_key', debounce(function () {
			wcsdmSetting._initGoogleMaps();
		}, 250));

		// Handle on Latitude and Longitude field setting changed.
		$(document).on('change', '#woocommerce_wcsdm_origin_lat, #woocommerce_wcsdm_origin_lng', debounce(function () {
			if (!$('#woocommerce_wcsdm_origin_lat').val().length || !$('#woocommerce_wcsdm_origin_lng').val().length) {
				return;
			}
			wcsdmSetting._initGoogleMaps();
		}, 250));

		// Handle on distance unit field setting changed.
		$(document).on('change', '#woocommerce_wcsdm_gmaps_api_units', function () {
			$('.field-groups.distance .field-group-item-units').text(wcsdmSetting.params.i18n.distance[$(this).val()].unit);
			$('option[value="per_unit"]').text(wcsdmSetting.params.i18n.distance[$(this).val()].perUnit);
		});

		// Handle on distance unit field setting changed.
		$(document).on('change', '#woocommerce_wcsdm_gmaps_api_units', function () {
			$('.field-groups.distance .field-group-item-units').text(wcsdmSetting.params.i18n.distance[$(this).val()].unit);
			$('option[value="per_unit"]').text(wcsdmSetting.params.i18n.distance[$(this).val()].perUnit);
		});

		// Handle on distance field changed.
		$(document).on('change input', '.field-distance', function (e) {
			var $inputTarget = $(e.currentTarget);
			var inputVal = parseInt($inputTarget.val());
			if (inputVal < 10) {
				$inputTarget.attr('step', '1');
			} else if (inputVal >= 10 && inputVal <= 50) {
				$inputTarget.attr('step', '5');
			} else {
				$inputTarget.attr('step', '10');
			}
		});

		// Handle on dummy field value changed.
		$(document).on('change', '#wcsdm-table-rates tbody .wcsdm-input-dummy', function (e) {
			$(e.currentTarget).closest('td').find('.wcsdm-input').val($(e.currentTarget).val());
		});

		// Handle on free shipping option field value changed.
		$(document).on('change', '#woocommerce_wcsdm_free', function (e) {
			$('.free-shipping-yes_alt').closest('tr').hide();
			if ($(e.currentTarget).val() === 'yes_alt') {
				$('.free-shipping-yes_alt').closest('tr').show();
			}
		});

		// Handle on advanced rate settings link clicked.
		$(document).on('click', '.advanced-rate-settings', function (e) {
			e.preventDefault();
			var $row = $(e.currentTarget).closest('tr').removeClass('applied');
			$row.siblings().removeClass('applied');
			$row.find('.wcsdm-input').each(function () {
				$('#' + $(this).data('id')).val($(this).val()).attr('name', '').trigger('change');
			});
			rowIndex = $row.index();
			rowScrollTop = Math.abs($row.closest('form').position().top);
			var $section = $(e.currentTarget).closest('section');
			$section.find('.wcsdm-table-row-advanced').show().siblings().hide();
			$('#btn-ok').hide().after(wp.template('btn-advanced'));
			$('#woocommerce_wcsdm_free').trigger('change');
		});

		// Handle on Apply Change button clicked.
		$(document).on('click', '#btn-dummy', function (e) {
			e.preventDefault();
			var $inputTarget;
			$('#wcsdm-table-advanced .wcsdm-input').each(function () {
				var $input = $(this);
				var inputVal = $input.val();
				var inputId = $input.attr('id');
				$inputTarget = $('#wcsdm-table-rates tbody tr:eq(' + rowIndex + ')').addClass('applied').find('.wcsdm-input.' + inputId).val(inputVal);
				if ($inputTarget.hasClass('wcsdm-input-free')) {
					$inputTarget.closest('td').find('.dashicons').removeClass().addClass('dashicons').addClass('free-shipping-' + inputVal);
				} else {
					$inputTarget.closest('td').find('.wcsdm-input-dummy').val(inputVal);
				}
				$input.val('');
			});
			$(e.currentTarget).closest('section').find('.wcsdm-table-row-advanced').hide().siblings().show();
			$(e.currentTarget).remove();
			$('#btn-ok').show();

			$('.wc-modal-shipping-method-settings').animate({
				scrollTop: rowScrollTop
			}, 500);

			setTimeout(function () {
				if ($inputTarget) {
					$inputTarget.closest('tr').removeClass('applied');
				}
			}, 1000);
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
	_initGoogleMaps: function () {
		$('#wcsdm-map-error').hide().empty();
		$('#wcsdm-map-wrapper').hide().empty();
		$('#wcsdm-lat-lng-wrap').hide();

		var apiKey = $('#woocommerce_wcsdm_gmaps_api_key').val();
		if (!apiKey.length) {
			$('#wcsdm-map-wrapper').closest('tr').hide();
			return;
		}

		$('#wcsdm-map-wrapper').closest('tr').show();

		isMapError = false;

		if (typeof window.google !== 'undefined') {
			window.google = undefined;
		}

		var mapScriptUrl = 'https://maps.googleapis.com/maps/api/js?libraries=geometry,places&key=' + apiKey + '&language=' + wcsdmSetting.params.language;
		$.getScript(mapScriptUrl, function () {
			wcsdmSetting._buildGoogleMaps();
		});
	},
	_buildGoogleMaps: function () {
		var defaultLat = -6.175392;
		var defaultLng = 106.827153;
		var curLat = $('#woocommerce_wcsdm_origin_lat').val();
		var curLng = $('#woocommerce_wcsdm_origin_lng').val();
		curLat = curLat.length ? parseFloat(curLat) : defaultLat;
		curLng = curLng.length ? parseFloat(curLng) : defaultLng;
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

		if (curLat === defaultLat && curLng === defaultLng) {
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
		$('#wcsdm-map-wrapper').show();
		$('#wcsdm-lat-lng-wrap').show();

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
						console.log('DistanceMatrix.testRequest', { status: status, response: response });
					});
			}
		}, 800);
	},
	_showMapError: function (errorMsg) {
		$('#wcsdm-map-wrapper').empty().hide();
		$('#wcsdm-lat-lng-wrap').hide().find('.origin-coordinates').val('');
		var regExp = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
		$('#wcsdm-map-error').html(errorMsg.replace(regExp, '<a href="$1" target="_blank">$1</a>')).show();
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
		$('#wcsdm-table-rates tbody').append(wp.template('rates-list-input-table-row')).find('tr:last-child .field-distance').focus();
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
			$('#btn-ok').show();
		} else {
			$('#row-heading-bottom').show().find('.add_row').show().closest('table').find('thead, tbody, tfoot .row-heading-both').hide();
			$('#btn-ok').hide();
		}
	}
};

$(document).ready(function () {
	wcsdmSetting.init(wcsdm_params);
});
