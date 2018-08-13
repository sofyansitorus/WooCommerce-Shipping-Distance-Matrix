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
			$('#wcsdm-error').remove();
			$('#wcsdm-table-map-picker td').removeClass('error');

			var errors = {};
			var $button = $(e.currentTarget).prop('disable', true);

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
					$('#' + key).closest('td').addClass('error');
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
