;(function($) {
"use strict";

// Taking Over window.console.error
var windowConsoleError = window.console.error;
window.console.error = function () {
    if (arguments[0].toLowerCase().indexOf('google.com') !== 1) {
        wcsdmMapPicker.showMapError(arguments[0]);
        return;
    }

    windowConsoleError.apply(windowConsoleError, arguments);
};

var isMapError = false;

var wcsdmMapPicker = {
    init: function (params) {
        wcsdmMapPicker.params = params;

        $(document).on('click', '#wcsdm-btn-map-picker', function (e) {
            e.preventDefault();

            wcsdmMapPicker.setFooterButtons();
            $('#wcsdm-map-picker-canvas').empty();
            $('#woocommerce_wcsdm_gmaps_api_key_dummy').val($('#woocommerce_wcsdm_gmaps_api_key').val()).trigger('input');
            $('#wcsdm-row-api-key').show().siblings().hide();
        });

        $(document).on('click', '#wcsdm-btn-map-cancel', wcsdmMapPicker.cancelChanges);

        $(document).on('click', '#wcsdm-btn-map-apply', wcsdmMapPicker.applyChanges);

        $(document).on('input', '#woocommerce_wcsdm_gmaps_api_key_dummy', debounce(wcsdmMapPicker.initGoogleMap, 500));
    },
    addFooterButtons: function (buttons) {
        $('#wcsdm-footer-buttons').remove();
        $('#btn-ok').after(wp.template('wcsdm-footer-buttons')(buttons));
    },
    setFooterButtons: function () {
        $('#wcsdm-footer-buttons').remove();
        $('#btn-ok').after(wp.template('wcsdm-footer-buttons')({
            btn_left: {
                label: wcsdmMapPicker.params.i18n.cancel,
                id: 'wcsdm-btn-map-cancel',
                icon: 'undo'
            },
            btn_right: {
                label: wcsdmMapPicker.params.i18n.apply_changes,
                id: 'wcsdm-btn-map-apply',
                icon: 'yes'
            }
        }));
    },
    restoreFooterButtons: function () {
        $('#wcsdm-footer-buttons').remove();
        $('#btn-ok').after(wp.template('wcsdm-footer-buttons')({
            btn_left: {
                label: wcsdmMapPicker.params.i18n.add_rate,
                id: 'wcsdm-btn-add-rate',
                icon: 'plus'
            },
            btn_right: {
                label: wcsdmMapPicker.params.i18n.save_changes,
                id: 'wcsdm-btn-save',
                icon: 'yes'
            }
        }));
    },
    cancelChanges: function (e) {
        e.preventDefault();

        hideError();

        $('#wcsdm-map-picker-canvas').empty();

        wcsdmMapPicker.restoreFooterButtons();

        $('#woocommerce_wcsdm_gmaps_api_key_dummy').val($('#woocommerce_wcsdm_gmaps_api_key').val());
        $('#woocommerce_wcsdm_origin_lat_dummy').val($('#woocommerce_wcsdm_origin_lat').val());
        $('#woocommerce_wcsdm_origin_lng_dummy').val($('#woocommerce_wcsdm_origin_lng').val());

        $('#wcsdm-row-api-key').hide().siblings().not('.wcsdm-row--hidden').show();
    },
    applyChanges: function (e) {
        e.preventDefault();
        if (isMapError) {
            return;
        }

        hideError();

        var errors = {};
        var $button = $(e.currentTarget).prop('disabled', true);

        var requiredFields = [
            'woocommerce_wcsdm_gmaps_api_key_dummy',
            'woocommerce_wcsdm_origin_lat_dummy',
            'woocommerce_wcsdm_origin_lng_dummy'
        ];

        for (var i = 0; i < requiredFields.length; i++) {
            var requiredFieldKey = requiredFields[i];
            var $requiredField = $('#' + requiredFieldKey);
            if (!$requiredField.val().length) {
                errors[requiredFieldKey] = wcsdmMapPicker.params.i18n.errors.field_required.replace('%s', $requiredField.data('title'));
            }
        }

        if (_.keys(errors).length) {
            var errorMessage = '';
            _.keys(errors).forEach(function (key) {
                errorMessage += '<p id="wcsdm-rate-field--error--' + key + '">' + errors[key] + '</p>';
                $('#' + key).closest('td').addClass('error');
            });

            showError({
                selector: '#wcsdm-table-map-picker',
                content: errorMessage
            });

            $button.prop('disabled', false);
            return;
        }

        var testService = new google.maps.DistanceMatrixService();
        testService.getDistanceMatrix(
            {
                origins: [new google.maps.LatLng(wcsdmMapPicker.params.default_lat, wcsdmMapPicker.params.default_lng)],
                destinations: [new google.maps.LatLng(wcsdmMapPicker.params.test_destination_lat, wcsdmMapPicker.params.test_destination_lng)],
                travelMode: 'DRIVING',
                unitSystem: google.maps.UnitSystem.METRIC
            }, function (response, status) {
                console.log('DistanceMatrixTestRequest', { status: status, response: response });
                if (status === 'OK') {

                    wcsdmMapPicker.restoreFooterButtons();

                    var newApiKey = $('#woocommerce_wcsdm_gmaps_api_key_dummy').val();
                    var newLat = $('#woocommerce_wcsdm_origin_lat_dummy').val();
                    var newLng = $('#woocommerce_wcsdm_origin_lng_dummy').val();

                    $('#wcsdm-map-picker-canvas').empty();

                    $('#woocommerce_wcsdm_gmaps_api_key').val(newApiKey);
                    $('#woocommerce_wcsdm_origin_lat').val(newLat);
                    $('#woocommerce_wcsdm_origin_lng').val(newLng);

                    $('#wcsdm-row-api-key').hide().siblings().not('.wcsdm-row--hidden').show();
                }
            });
    },
    initGoogleMap: function () {
        hideError();

        $('#wcsdm-map-picker-canvas').removeClass('has-error empty').empty();

        var apiKey = $('#woocommerce_wcsdm_gmaps_api_key_dummy').val();
        if (!apiKey.length) {
            $('#wcsdm-map-picker-canvas').addClass('empty').append($('#wcsdm-map-picker-instruction').html());
            $('#wcsdm-btn-map-apply').prop('disabled', true);
            return;
        }

        isMapError = false;

        window.google = undefined;

        var mapScriptUrl = 'https://maps.googleapis.com/maps/api/js?libraries=geometry,places&key=' + apiKey + '&language=' + wcsdmMapPicker.params.language;
        $.getScript(mapScriptUrl, function () {
            wcsdmMapPicker.buildGoogleMaps();
        });
    },
    buildGoogleMaps: function () {
        var pattern = /^[-]?[0-9]{1,7}(\.[0-9]+)?$/;

        var curLat = $('#woocommerce_wcsdm_origin_lat').val();
        var curLng = $('#woocommerce_wcsdm_origin_lng').val();

        var curLatLng = {
            lat: curLat.length && pattern.exec(curLat) ? parseFloat(curLat) : wcsdmMapPicker.params.default_lat,
            lng: curLng.length && pattern.exec(curLng) ? parseFloat(curLng) : wcsdmMapPicker.params.default_lng,
        };

        var markers = [];

        // Initiate map
        var map = new google.maps.Map(
            document.getElementById('wcsdm-map-picker-canvas'),
            {
                center: curLatLng,
                zoom: 16,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
                mapTypeId: 'roadmap'
            }
        );

        var marker = new google.maps.Marker({
            map: map,
            position: curLatLng,
            draggable: true,
            icon: wcsdmMapPicker.params.marker
        });

        var infowindow = new google.maps.InfoWindow({ maxWidth: 350 });

        if (!curLat.length || !pattern.exec(curLat) || !curLng.length || !pattern.exec(curLng)) {
            infowindow.setContent(wcsdmMapPicker.params.i18n.dragMarker);
            infowindow.open(map, marker);
        } else {
            wcsdmMapPicker.setLatLng(marker.position, marker, map, infowindow);
        }

        google.maps.event.addListener(marker, 'dragstart', function () {
            infowindow.close();
        });

        google.maps.event.addListener(marker, 'dragend', function (event) {
            wcsdmMapPicker.setLatLng(event.latLng, marker, map, infowindow);
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
                    icon: wcsdmMapPicker.params.marker
                });

                wcsdmMapPicker.setLatLng(place.geometry.location, marker, map, infowindow);

                google.maps.event.addListener(marker, 'dragstart', function () {
                    infowindow.close();
                });

                google.maps.event.addListener(marker, 'dragend', function (event) {
                    wcsdmMapPicker.setLatLng(event.latLng, marker, map, infowindow);
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
                    var infowindowContent = results[0].formatted_address +
                        '<hr /><span class="lat-lng-label"><strong>' + wcsdmMapPicker.params.i18n.latitude + '</strong>: ' + location.lat() + '</span>' +
                        '<br /><span class="lat-lng-label"><strong>' + wcsdmMapPicker.params.i18n.longitude + '</strong>: ' + location.lng() + '</span>';
                    infowindow.setContent(infowindowContent);
                    infowindow.open(map, marker);
                    marker.addListener('click', function () {
                        infowindow.open(map, marker);
                    });
                }
            }
        );
        map.setCenter(location);

        if (!isMapError) {
            $('#wcsdm-btn-map-apply').prop('disabled', false);
            $('#woocommerce_wcsdm_origin_lat_dummy').val(location.lat());
            $('#woocommerce_wcsdm_origin_lng_dummy').val(location.lng());
        }
    },
    showMapError: function (errorMsg) {
        isMapError = true;
        var patternLink = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
        if ($('.gm-err-message').length) {
            $('.gm-err-message').empty().append(errorMsg.replace(patternLink, '<a href="$1" target="_blank">$1</a>'));
        } else {
            setTimeout(function () {
                $('#wcsdm-map-picker-canvas').addClass('empty has-error').empty();
                showError({
                    selector: '#wcsdm-map-picker-canvas',
                    method: 'append',
                    content: errorMsg.replace(patternLink, '<a href="$1" target="_blank">$1</a>')
                });
            }, 0);
        }
        $('#wcsdm-btn-map-apply').prop('disabled', true);
    }
};
}(jQuery));
