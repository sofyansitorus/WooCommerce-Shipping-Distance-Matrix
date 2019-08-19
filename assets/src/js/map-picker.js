
// Taking Over window.console.error
var isMapError = undefined, isMapErrorInterval;

var windowConsoleError = window.console.error;

window.console.error = function () {
    if (arguments[0].toLowerCase().indexOf('google') !== -1) {
        isMapError = arguments[0];
    }

    windowConsoleError.apply(windowConsoleError, arguments);
};

/**
 * Map Picker
 */
var wcsdmMapPicker = {
    params: {},
    origin_lat: '',
    origin_lng: '',
    origin_address: '',
    zoomLevel: 16,
    apiKeyBrowser: '',
    init: function (params) {
        'use strict';

        wcsdmMapPicker.params = params;

        // Edit Api Key
        $(document).off('click', '.wcsdm-edit-api-key', wcsdmMapPicker.editApiKey);
        $(document).on('click', '.wcsdm-edit-api-key', wcsdmMapPicker.editApiKey);

        // Get API Key
        $(document).off('click', '#wcsdm-btn--get-api-key', wcsdmMapPicker.getApiKey);
        $(document).on('click', '#wcsdm-btn--get-api-key', wcsdmMapPicker.getApiKey);

        // Show Store Location Picker
        $(document).off('click', '.wcsdm-edit-location', wcsdmMapPicker.showStoreLocationPicker);
        $(document).on('click', '.wcsdm-edit-location', wcsdmMapPicker.showStoreLocationPicker);

        // Hide Store Location Picker
        $(document).off('click', '#wcsdm-btn--map-cancel', wcsdmMapPicker.hideStoreLocationPicker);
        $(document).on('click', '#wcsdm-btn--map-cancel', wcsdmMapPicker.hideStoreLocationPicker);

        // Apply Store Location
        $(document).off('click', '#wcsdm-btn--map-apply', wcsdmMapPicker.applyStoreLocation);
        $(document).on('click', '#wcsdm-btn--map-apply', wcsdmMapPicker.applyStoreLocation);

        // Toggle Map Search Panel
        $(document).off('click', '#wcsdm-map-search-panel-toggle', wcsdmMapPicker.toggleMapSearch);
        $(document).on('click', '#wcsdm-map-search-panel-toggle', wcsdmMapPicker.toggleMapSearch);
    },
    testDistanceMatrix: function () {
        var origin = new google.maps.LatLng(parseFloat(wcsdmMapPicker.params.defaultLat), parseFloat(wcsdmMapPicker.params.defaultLng));
        var destination = new google.maps.LatLng(parseFloat(wcsdmMapPicker.params.testLat), parseFloat(wcsdmMapPicker.params.testLng));
        var service = new google.maps.DistanceMatrixService();

        service.getDistanceMatrix(
            {
                origins: [origin],
                destinations: [destination],
                travelMode: 'DRIVING',
                unitSystem: google.maps.UnitSystem.METRIC
            }, function (response, status) {
                if (status.toLowerCase() === 'ok') {
                    isMapError = false;
                } else {
                    if (response.error_message) {
                        isMapError = response.error_message;
                    } else {
                        isMapError = 'Error: ' + status;
                    }
                }
            });
    },
    editApiKey: function (e) {
        'use strict';

        e.preventDefault();

        var $link = $(e.currentTarget);
        var $input = $link.closest('tr').find('input[type=hidden]');
        var $inputDummy = $link.closest('tr').find('input[type=text]');
        var apiKey = $input.val();
        var apiKeyDummy = $inputDummy.val();

        if ($link.hasClass('editing')) {
            if (apiKey !== apiKeyDummy) {
                $link.addClass('loading').attr('disabled', true);

                switch ($link.attr('id')) {
                    case 'api_key_browser': {
                        wcsdmMapPicker.initMap(apiKeyDummy, wcsdmMapPicker.testDistanceMatrix);

                        clearInterval(isMapErrorInterval);

                        isMapErrorInterval = setInterval(function () {
                            if (typeof isMapError !== 'undefined') {
                                clearInterval(isMapErrorInterval);

                                if (isMapError) {
                                    $inputDummy.val(apiKey);
                                    window.alert(isMapError);
                                } else {
                                    $input.val(apiKeyDummy);
                                }

                                $link.removeClass('loading editing').attr('disabled', false);
                                $inputDummy.prop('readonly', true);
                            }
                        }, 100);
                        break;
                    }

                    default: {
                        $.ajax({
                            method: "POST",
                            url: wcsdmMapPicker.params.ajax_url,
                            data: {
                                action: "wcsdm_validate_api_key_server",
                                nonce: wcsdmMapPicker.params.validate_api_key_nonce,
                                key: apiKeyDummy,
                            }
                        }).done(function () {
                            // Set new API Key value
                            $input.val(apiKeyDummy);
                        }).fail(function (error) {
                            // Restore existing API Key value
                            $inputDummy.val(apiKey);

                            // Show error
                            if (error.responseJSON && error.responseJSON.data) {
                                return window.alert(error.responseJSON.data);
                            }

                            if (error.statusText) {
                                return window.alert(error.statusText);
                            }

                            window.alert('Error');
                        }).always(function () {
                            $link.removeClass('loading editing').attr('disabled', false);
                            $inputDummy.prop('readonly', true);
                        });
                    }
                }
            } else {
                $link.removeClass('editing');
                $inputDummy.prop('readonly', true);
            }
        } else {
            $link.addClass('editing');
            $inputDummy.prop('readonly', false);
        }
    },
    getApiKey: function (e) {
        'use strict';

        e.preventDefault();

        window.open('https://cloud.google.com/maps-platform/#get-started', '_blank').focus();
    },
    showStoreLocationPicker: function (e) {
        'use strict';

        e.preventDefault();

        $('.modal-close-link').hide();

        toggleBottons({
            left: {
                id: 'map-cancel',
                label: 'Cancel',
                icon: 'undo'
            },
            right: {
                id: 'map-apply',
                label: 'Apply Changes',
                icon: 'editor-spellcheck'
            }
        });

        $('#wcsdm-field-group-wrap--location_picker').fadeIn().siblings().hide();

        wcsdmMapPicker.initMap($('#woocommerce_wcsdm_api_key_browser').val(), wcsdmMapPicker.renderMap);
    },
    hideStoreLocationPicker: function (e) {
        'use strict';

        e.preventDefault();

        wcsdmMapPicker.destroyMap();

        $('.modal-close-link').show();

        toggleBottons();

        $('#wcsdm-field-group-wrap--location_picker').hide().siblings().not('.wcsdm-hidden').fadeIn();
    },
    applyStoreLocation: function (e) {
        'use strict';

        e.preventDefault();

        if (isMapError) {
            return;
        }

        wcsdmMapPicker.initMap($('#woocommerce_wcsdm_api_key_browser').val(), wcsdmMapPicker.testDistanceMatrix);

        clearInterval(isMapErrorInterval);

        isMapErrorInterval = setInterval(function () {
            if (typeof isMapError !== 'undefined') {
                clearInterval(isMapErrorInterval);

                if (isMapError) {
                    window.alert(isMapError);
                } else {
                    $('#woocommerce_wcsdm_origin_lat').val(wcsdmMapPicker.origin_lat);
                    $('#woocommerce_wcsdm_origin_lng').val(wcsdmMapPicker.origin_lng);
                    $('#woocommerce_wcsdm_origin_address').val(wcsdmMapPicker.origin_address);
                    wcsdmMapPicker.hideStoreLocationPicker(e);
                }
            }
        }, 100);
    },
    toggleMapSearch: function (e) {
        'use strict';

        e.preventDefault();

        $(e.currentTarget).find('span').toggleClass('dashicons-search').toggleClass('dashicons-dismiss');

        $('#wcsdm-map-search-panel').toggleClass('hide-main');
        $('#wcsdm-map-search-panel-main').toggleClass('wcsdm-hidden');
    },
    initMap: function (apiKey, callback) {
        wcsdmMapPicker.destroyMap();

        isMapError = undefined;

        if (_.isEmpty(apiKey)) {
            apiKey = 'InvalidKey';
        }

        $.getScript('https://maps.googleapis.com/maps/api/js?libraries=geometry,places&key=' + apiKey, callback);
    },
    renderMap: function () {
        wcsdmMapPicker.origin_lat = $('#woocommerce_wcsdm_origin_lat').val();
        wcsdmMapPicker.origin_lng = $('#woocommerce_wcsdm_origin_lng').val();

        var currentLatLng = {
            lat: _.isEmpty(wcsdmMapPicker.origin_lat) ? parseFloat(wcsdmMapPicker.params.defaultLat) : parseFloat(wcsdmMapPicker.origin_lat),
            lng: _.isEmpty(wcsdmMapPicker.origin_lng) ? parseFloat(wcsdmMapPicker.params.defaultLng) : parseFloat(wcsdmMapPicker.origin_lng)
        };

        var map = new google.maps.Map(
            document.getElementById('wcsdm-map-canvas'),
            {
                mapTypeId: 'roadmap',
                center: currentLatLng,
                zoom: wcsdmMapPicker.zoomLevel,
                streetViewControl: false,
                mapTypeControl: false
            }
        );

        var marker = new google.maps.Marker({
            map: map,
            position: currentLatLng,
            draggable: true,
            icon: wcsdmMapPicker.params.marker
        });

        var infowindow = new google.maps.InfoWindow({ maxWidth: 350 });

        if (_.isEmpty(wcsdmMapPicker.origin_lat) || _.isEmpty(wcsdmMapPicker.origin_lng)) {
            infowindow.setContent(wcsdmMapPicker.params.i18n.drag_marker);
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

        $('#wcsdm-map-wrap').prepend(wp.template('wcsdm-map-search-panel')());
        map.controls[google.maps.ControlPosition.TOP_LEFT].push(document.getElementById('wcsdm-map-search-panel'));

        var mapSearchBox = new google.maps.places.SearchBox(document.getElementById('wcsdm-map-search-input'));

        // Bias the SearchBox results towards current map's viewport.
        map.addListener('bounds_changed', function () {
            mapSearchBox.setBounds(map.getBounds());
        });

        var markers = [];

        // Listen for the event fired when the user selects a prediction and retrieve more details for that place.
        mapSearchBox.addListener('places_changed', function () {
            var places = mapSearchBox.getPlaces();
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
    destroyMap: function () {
        if (window.google) {
            window.google = undefined;
        }

        $('#wcsdm-map-canvas').empty();
        $('#wcsdm-map-search-panel').remove();
    },
    setLatLng: function (location, marker, map, infowindow) {
        var geocoder = new google.maps.Geocoder();

        geocoder.geocode(
            {
                latLng: location
            },
            function (results, status) {
                if (status === google.maps.GeocoderStatus.OK && results[0]) {
                    var infowindowContents = [
                        wcsdmMapPicker.params.i18n.latitude + ': ' + location.lat().toString(),
                        wcsdmMapPicker.params.i18n.longitude + ': ' + location.lng().toString()
                    ];

                    infowindow.setContent(infowindowContents.join('<br />'));
                    infowindow.open(map, marker);

                    marker.addListener('click', function () {
                        infowindow.open(map, marker);
                    });

                    $('#wcsdm-map-search-input').val(results[0].formatted_address);

                    wcsdmMapPicker.origin_lat = location.lat();
                    wcsdmMapPicker.origin_lng = location.lng();
                    wcsdmMapPicker.origin_address = results[0].formatted_address;
                }
            }
        );

        map.setCenter(location);
    }
};
