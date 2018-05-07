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

// Taking Over window.console.error
var isMapError = false;
var windowConsoleError = window.console.error;
window.console.error = function () {
	var errMsg = arguments[0];
	if (errMsg.indexOf('https://developers.google.com/maps/documentation/javascript/error-messages') !== 1) {
		wcsdmSetting._showMapError(errMsg);
	}
	windowConsoleError.apply(windowConsoleError, arguments);
};

var wcsdmSetting = {
	_zoomLevel: 16,
	init: function (params) {
		var self = this;
		self.params = params;

		// Try show settings modal on settings page.
		if (self.params.showSettings) {
			setTimeout(function () {
				var isMethodAdded = false;
				var methods = $(document).find('.wc-shipping-zone-method-type');
				for (var i = 0; i < methods.length; i++) {
					var method = methods[i];
					if ($(method).text() === self.params.methodTitle) {
						$(method).closest('tr').find('.row-actions .wc-shipping-zone-method-settings').trigger('click');
						isMethodAdded = true;
						return;
					}
				}
				// Show Add shipping method modal if the shipping is not added.
				if (!isMethodAdded) {
					$('.wc-shipping-zone-add-method').trigger('click');
					$('select[name="add_method_id"]').val(self.params.methodId).trigger('change');
				}
			}, 200);
		}

		// Handle setting link clicked.
		$(document).on('click', '.wc-shipping-zone-method-settings', function () {
			var methodTitle = $(this).closest('tr').find('.wc-shipping-zone-method-type').text();
			if (methodTitle !== self.params.methodTitle) {
				return false;
			}
			$('#woocommerce_wcsdm_gmaps_api_units').trigger('change');
			$('#woocommerce_wcsdm_gmaps_api_key').trigger('input');
		});

		// Handle on API Key field setting changed.
		$(document).on('input', '#woocommerce_wcsdm_gmaps_api_key', debounce(function () {
			self._initGoogleMaps();
		}, 250));

		// Handle on Latitude and Longitude field setting changed.
		$(document).on('change', '#woocommerce_wcsdm_origin_lat, #woocommerce_wcsdm_origin_lng', debounce(function () {
			if (!$('#woocommerce_wcsdm_origin_lat').val().length || !$('#woocommerce_wcsdm_origin_lng').val().length) {
				return;
			}
			self._initGoogleMaps();
		}, 250));

		// Handle on distnace unit field setting changed.
		$(document).on('change', '#woocommerce_wcsdm_gmaps_api_units', function () {
			$('.field-group.distance .field-group-icon').text(self.params.i18n.distance[$(this).val()].unit);
			$('option[value="per_unit"]').text(self.params.i18n.distance[$(this).val()].perUnit);
		});

		// Handle toggle rate rows in bulk.
		$(document).on('change', '#rates-list-table thead .select-item', self._toggleRateRowsBulk);

		// Handle toggle rate rows in individually.
		$(document).on('change', '#rates-list-table tbody .select-item', self._toggleRateRows);

		// Handle add rate rows.
		$(document).on('click', '#rates-list-table .button.add_row', self._addRateRows);

		// Handle remove rate rows.
		$(document).on('click', '#rates-list-table .button.remove_rows', self._removeRateRows);
	},
	_initGoogleMaps: function () {
		var self = this;

		$('#wcsdm-map-wrapper').hide().siblings('.description').show();

		var apiKey = $('#woocommerce_wcsdm_gmaps_api_key').val();
		if (!apiKey.length) {
			return;
		}

		isMapError = false;

		if (typeof window.google !== 'undefined') {
			window.google = undefined;
		}

		var mapScriptUrl = 'https://maps.googleapis.com/maps/api/js?libraries=geometry,places&key=' + apiKey + '&language=' + self.params.language;
		$.getScript(mapScriptUrl, function () {
			self._buildGoogleMaps();
		});
	},
	_buildGoogleMaps: function () {
		var self = this;
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
				zoom: self._zoomLevel,
				mapTypeId: 'roadmap'
			}
		);
		var marker = new google.maps.Marker({
			map: map,
			position: curLatLng,
			draggable: true,
			icon: self.params.marker
		});

		var infowindow = new google.maps.InfoWindow({ maxWidth: 350 });

		if (curLat === defaultLat && curLng === defaultLng) {
			infowindow.setContent(self.params.i18n.dragMarker);
			infowindow.open(map, marker);
		} else {
			self._setLatLng(marker.position, marker, map, infowindow);
		}

		google.maps.event.addListener(marker, 'dragstart', function () {
			infowindow.close();
		});

		google.maps.event.addListener(marker, 'dragend', function (event) {
			self._setLatLng(event.latLng, marker, map, infowindow);
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
					icon: self.params.marker
				});
				self._setLatLng(place.geometry.location, marker, map, infowindow);
				google.maps.event.addListener(marker, 'dragstart', function () {
					infowindow.close();
				});
				google.maps.event.addListener(marker, 'dragend', function (event) {
					self._setLatLng(event.latLng, marker, map, infowindow);
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
		setTimeout(function () {
			if (!isMapError) {
				$('#wcsdm-map-wrapper').show().siblings('.description').hide();
			}
		}, 500);
	},
	_showMapError: function (errorMsg) {
		$('#wcsdm-map-wrapper').empty().hide().siblings('.description').show('fast', function () {
			isMapError = true;
			window.alert(errorMsg);
		});
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
	},
	_toggleRateRows: function (e) {
		var $this = $(e.currentTarget);
		var $table = $this.closest('table');
		var $thead = $table.find('thead');
		var $tbody = $table.find('tbody');
		var $tfoot = $table.find('tfoot');

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
		$('#rates-list-table tbody').append(wp.template('rates-list-input-table-row')).find('tr:last-child .field-distance').focus();
		$('#woocommerce_wcsdm_gmaps_api_units').trigger('change');
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
	}
};

$(document).ready(function () {
	wcsdmSetting.init(wcsdm_params);
});
}(jQuery));
