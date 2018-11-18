
// Taking Over window.console.error
var isMapError = false;

var windowConsoleError = window.console.error;
window.console.error = function () {
    if (arguments[0].toLowerCase().indexOf('google') !== 1) {
        isMapError = arguments[0];
        window.alert(isMapError);
        $('#api_key_browser').trigger('click');
        $('#woocommerce_wcsdm_api_key_browser').val(wcsdmMapPicker.apiKeyBrowser);
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

        // Cancel Edit Api Key
        $(document).off('click', '.wcsdm-edit-api-key-cancel', wcsdmMapPicker.editApiKeyCancel);
        $(document).on('click', '.wcsdm-edit-api-key-cancel', wcsdmMapPicker.editApiKeyCancel);

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
    editApiKey: function (e) {
        'use strict';

        e.preventDefault();

        var $link = $(e.currentTarget);
        var $linkCancel = $link.next('a');
        var $input = $link.closest('tr').find('input[type=hidden]');
        var $inputDummy = $link.closest('tr').find('input[type=text]');
        var $spinner = $link.closest('tr').find('.spinner');
        var $icon = $link.find('.dashicons');

        var apiKey = $input.val();
        var apiKeyDummy = $inputDummy.val();

        if ($link.hasClass('editing')) {
            if (!apiKeyDummy.length) {
                return;
            }

            $linkCancel.addClass('wcsdm-hidden');
            $link.removeClass('editing');
            $inputDummy.prop('readonly', true);
            $icon.toggleClass('dashicons-edit').toggleClass('dashicons-yes');

            var toggleControls = function (timeout) {
                var $d = $.Deferred();
                $link.hide();
                $spinner.css('visibility', 'visible');
                $('.wcsdm-buttons-item,.wcsdm-link').prop('disabled', true).css('opacity', 0.5);

                setTimeout(function () {
                    $link.show();
                    $spinner.css('visibility', 'hidden');
                    $('.wcsdm-buttons-item,.wcsdm-link').prop('disabled', false).css('opacity', 1);
                    $d.resolve(true);
                }, timeout);

                return $d.promise();
            };

            switch ($link.attr('id')) {
                case 'api_key_browser': {
                    wcsdmMapPicker.apiKeyBrowser = $input.val();

                    // Unset current google instance
                    window.google = undefined;
                    isMapError = false;

                    $.getScript('https://maps.googleapis.com/maps/api/js?libraries=geometry,places&key=' + apiKeyDummy, function () {
                        toggleControls(5000).then(function () {
                            if (!isMapError) {
                                $input.val(apiKeyDummy);
                            }
                        });
                    });
                    break;
                }

                default:
                    toggleControls(500).then(function () {
                        $input.val(apiKeyDummy);
                    });
                    break;
            }
        } else {
            $link.show().addClass('editing');
            $linkCancel.removeClass('wcsdm-hidden');
            $icon.toggleClass('dashicons-edit').toggleClass('dashicons-yes');
            $inputDummy.prop('readonly', false).val(apiKey);
            $spinner.css('visibility', 'hidden');
        }
    },
    editApiKeyCancel: function (e) {
        'use strict';

        e.preventDefault();

        var $link = $(e.currentTarget);
        var $linkEdit = $link.prev('a');
        var $iconEdit = $linkEdit.find('.dashicons');
        var $input = $link.closest('tr').find('input[type=hidden]');
        var $inputDummy = $link.closest('tr').find('input[type=text]');

        $link.addClass('wcsdm-hidden');
        $linkEdit.removeClass('editing');
        $iconEdit.toggleClass('dashicons-yes').toggleClass('dashicons-edit');
        $inputDummy.prop('readonly', true).val($input.val());
        if ($input.val().length) {
            isMapError = false;
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

        if (isMapError) {
            window.alert(isMapError);
            return;
        }

        $('.modal-close-link').hide();

        toggleBottons({
            left: {
                id: 'map-cancel',
                label: 'cancel',
                icon: 'undo'
            },
            right: {
                id: 'map-apply',
                label: 'apply',
                icon: 'editor-spellcheck'
            }
        });

        $('#wcsdm-field-group-wrap--location_picker').fadeIn().siblings().hide();

        wcsdmMapPicker.initMap();
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

        var apiKey = $('#woocommerce_wcsdm_api_key_browser').val();
        if (_.isEmpty(apiKey)) {
            return;
        }

        var testDistanceMatrix = function () {
            var dfd = jQuery.Deferred();

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
                        dfd.resolve(status, response);
                    } else {
                        dfd.reject(status, response);
                    }
                });

            return dfd.promise();
        };

        $.when(testDistanceMatrix())
            .then(function (status) {
                if (status.toLowerCase() === 'ok' && !isMapError) {
                    $('#woocommerce_wcsdm_origin_lat').val(wcsdmMapPicker.origin_lat);
                    $('#woocommerce_wcsdm_origin_lng').val(wcsdmMapPicker.origin_lng);
                    $('#woocommerce_wcsdm_origin_address').val(wcsdmMapPicker.origin_address);
                    wcsdmMapPicker.hideStoreLocationPicker(e);

                    return;
                }

                if (isMapError) {
                    window.alert(isMapError);

                    return;
                }

                window.alert(status);
            });
    },
    toggleMapSearch: function (e) {
        'use strict';

        e.preventDefault();

        $(e.currentTarget).find('span').toggleClass('dashicons-search').toggleClass('dashicons-dismiss');

        $('#wcsdm-map-search-panel').toggleClass('hide-main');
        $('#wcsdm-map-search-panel-main').toggleClass('wcsdm-hidden');
    },
    destroyMap: function () {
        window.google = undefined;
        $('#wcsdm-map-canvas').empty();
        $('#wcsdm-map-search-panel').remove();
    },
    initMap: function () {
        wcsdmMapPicker.destroyMap();

        isMapError = false;

        var apiKey = $('#woocommerce_wcsdm_api_key_browser').val();

        if (_.isEmpty(apiKey)) {
            apiKey = 'InvalidKey';
        }

        var scriptUrl = 'https://maps.googleapis.com/maps/api/js?libraries=geometry,places&key=' + apiKey;
        $.getScript(scriptUrl, function () {
            wcsdmMapPicker.renderMap();
        });
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
