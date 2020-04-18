/**
 * Map Picker
 */
var wcsdmMapPicker = {
    params: {},
    origin_lat: '',
    origin_lng: '',
    origin_address: '',
    zoomLevel: 16,
    listenConsole: false,
    apiKeyError: '',
    apiKeyErrorInterval: null,
    init: function (params) {
        wcsdmMapPicker.params = params;

        wcsdmMapPicker.apiKeyError = '';

        ConsoleListener.on('error', function (errorMessage) {
            if (!wcsdmMapPicker.listenConsole) {
                return;
            }

            if (errorMessage.toLowerCase().indexOf('google') !== -1) {
                wcsdmMapPicker.apiKeyError = errorMessage;
            }

            wcsdmMapPicker.listenConsole = false;

            if (wcsdmMapPicker.apiKeyError) {
                alert(wcsdmMapPicker.apiKeyError);
            }
        });

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
    validateAPIKeyBothSide: function (apiKey, $input, $link) {
        wcsdmMapPicker.validateAPIKeyServerSide(apiKey, $input, $link, wcsdmMapPicker.validateAPIKeyBrowserSide);
    },
    validateAPIKeyBrowserSide: function (apiKey, $input, $link) {
        wcsdmMapPicker.initMap(apiKey, function () {
            wcsdmMapPicker.apiKeyError = '';

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
                        $link.removeClass('editing').data('value', '');
                        $input.prop('readonly', true);
                        wcsdmMapPicker.listenConsole = false;
                        wcsdmMapPicker.apiKeyError = '';
                        console.log(response);
                    }
                });

            clearInterval(wcsdmMapPicker.apiKeyErrorInterval);

            wcsdmMapPicker.apiKeyErrorInterval = setInterval(function () {
                if (!$link.hasClass('editing') || wcsdmMapPicker.apiKeyError) {
                    clearInterval(wcsdmMapPicker.apiKeyErrorInterval);
                    $link.removeClass('loading').attr('disabled', false);
                }
            }, 300);
        });
    },
    validateAPIKeyServerSide: function (apiKey, $input, $link, onSuccess) {
        $.ajax({
            method: 'POST',
            url: wcsdmMapPicker.params.ajax_url,
            data: {
                action: 'wcsdm_validate_api_key_server',
                nonce: wcsdmMapPicker.params.validate_api_key_nonce,
                key: apiKey
            }
        }).done(function (response) {
            if (typeof onSuccess === 'function') {
                onSuccess(apiKey, $input, $link);
            } else {
                $link.removeClass('editing').data('value', '');
                $input.prop('readonly', true);
                wcsdmMapPicker.listenConsole = false;
                wcsdmMapPicker.apiKeyError = '';
                console.log(response);
            }
        }).fail(function (error) {
            if (error.responseJSON && error.responseJSON.data) {
                console.error(error.responseJSON.data);
            } else if (error.statusText) {
                console.error(error.statusText);
            } else {
                console.error('Google API Response Error: Uknown');
            }
        }).always(function () {
            $link.removeClass('loading').attr('disabled', false);
        });
    },
    editApiKey: function (e) {
        e.preventDefault();

        var $link = $(e.currentTarget);
        var $input = $link.closest('tr').find('input[type=text]');
        var apiKey = $input.val();

        if ($link.hasClass('editing')) {
            if (apiKey !== $link.data('value') || wcsdmMapPicker.apiKeyError) {
                $link.addClass('loading').attr('disabled', true);

                var validateBrowserSide = false;
                var validateServerSide = false;

                if ($link.attr('id') === 'api_key') {
                    validateBrowserSide = true;

                    if (!$('#woocommerce_wcsdm_api_key_split').is(':checked')) {
                        validateServerSide = true;
                    }
                } else {
                    validateServerSide = true;
                }

                wcsdmMapPicker.listenConsole = true;

                if (validateServerSide && validateBrowserSide) {
                    wcsdmMapPicker.validateAPIKeyBothSide(apiKey, $input, $link);
                } else if (validateServerSide) {
                    wcsdmMapPicker.validateAPIKeyServerSide(apiKey, $input, $link);
                } else if (validateBrowserSide) {
                    wcsdmMapPicker.validateAPIKeyBrowserSide(apiKey, $input, $link);
                }
            } else {
                wcsdmMapPicker.listenConsole = false;
                $link.removeClass('editing').data('value', '');
                $input.prop('readonly', true);
            }
        } else {
            $link.addClass('editing').data('value', apiKey);
            $input.prop('readonly', false);
        }
    },
    getApiKey: function (e) {
        e.preventDefault();

        window.open('https://cloud.google.com/maps-platform/#get-started', '_blank').focus();
    },
    showStoreLocationPicker: function (e) {
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

        wcsdmMapPicker.initMap($('#woocommerce_wcsdm_api_key').val(), wcsdmMapPicker.renderMap);
    },
    hideStoreLocationPicker: function (e) {
        e.preventDefault();

        wcsdmMapPicker.destroyMap();

        $('.modal-close-link').show();

        toggleBottons();

        $('#wcsdm-field-group-wrap--location_picker').hide().siblings().not('.wcsdm-hidden').fadeIn();
    },
    applyStoreLocation: function (e) {
        e.preventDefault();

        if (isMapError) {
            return;
        }

        $('#woocommerce_wcsdm_origin_lat').val(wcsdmMapPicker.origin_lat);
        $('#woocommerce_wcsdm_origin_lng').val(wcsdmMapPicker.origin_lng);
        $('#woocommerce_wcsdm_origin_address').val(wcsdmMapPicker.origin_address);
        wcsdmMapPicker.hideStoreLocationPicker(e);
    },
    toggleMapSearch: function (e) {
        e.preventDefault();

        $('#wcsdm-map-search-panel').toggleClass('expanded');
    },
    initMap: function (apiKey, callback) {
        wcsdmMapPicker.destroyMap();

        isMapError = null;

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

        setTimeout(function () {
            $('#wcsdm-map-search-panel').removeClass('wcsdm-hidden');
        }, 500);
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
