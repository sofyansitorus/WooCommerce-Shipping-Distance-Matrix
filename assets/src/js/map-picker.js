

// Taking Over window.console.error
var isMapError = false;

var windowConsoleError = window.console.error;
window.console.error = function () {
    if (arguments[0].toLowerCase().indexOf('google') !== 1) {
        isMapError = true;
        $('#wcsdm-map-search').hide();
        $('.gm-err-message').empty().html(
            arguments[0].replace(/(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig, '<a href="$1" target="_blank">$1</a>')
        );
    }

    windowConsoleError.apply(windowConsoleError, arguments);
};

/**
 * Map Picker
 */
var wcsdmMapPicker = {
    params: {},
    lat: '',
    lng: '',
    init: function (params) {
        "use strict";

        wcsdmMapPicker.params = params;

        wcsdmMapPicker.tweakShowMapFormLink();

        // Show form
        $(document).off('click', '.wcsdm-link--show-map');
        $(document).on('click', '.wcsdm-link--show-map', wcsdmMapPicker.showForm);

        // Hide form
        $(document).off('click', '#wcsdm-btn--map-cancel');
        $(document).on('click', '#wcsdm-btn--map-cancel', wcsdmMapPicker.hideForm);

        // Apply form
        $(document).off('click', '#wcsdm-btn--map-apply');
        $(document).on('click', '#wcsdm-btn--map-apply', wcsdmMapPicker.applyForm);

        // Togle instructions
        $(document).off('click', '#wcsdm-show-instructions');
        $(document).on('click', '#wcsdm-show-instructions', wcsdmMapPicker.showInstructions);

        $(document).off('click', '#wcsdm-btn--close-instructions');
        $(document).on('click', '#wcsdm-btn--close-instructions', wcsdmMapPicker.closeInstructions);

        // Handle on API Key field setting changed.
        $(document).off('input', '#woocommerce_wcsdm_api_key__dummy');
        $(document).on('input', '#woocommerce_wcsdm_api_key__dummy', debounce(function () {
            wcsdmMapPicker.initMap();
        }, 500));
    },
    tweakShowMapFormLink: function () {
        "use strict";

        $('.wcsdm-link--show-map').closest('p').css('display', 'inline-block');
    },
    showInstructions: function (e) {
        "use strict";

        e.preventDefault();

        toggleBottons({
            right: {
                id: 'close-instructions',
                label: 'back',
                icon: 'undo',
            }
        });

        $('#wcsdm-row-map-instructions').show().siblings().hide();
    },
    closeInstructions: function (e) {
        "use strict";

        e.preventDefault();

        wcsdmMapPicker.showForm(e);
    },
    showForm: function (e) {
        "use strict";

        e.preventDefault();

        $('.modal-close-link').hide();

        toggleBottons({
            left: {
                id: 'map-cancel',
                label: 'cancel',
                icon: 'undo',
            },
            right: {
                id: 'map-apply',
                label: 'apply',
                icon: 'editor-spellcheck',
            }
        });

        $('#woocommerce_wcsdm_api_key__dummy').val($('#woocommerce_wcsdm_api_key').val());

        $('#wcsdm-row-map-picker').show().siblings().hide();

        wcsdmMapPicker.initMap();
    },
    hideForm: function (e) {
        "use strict";

        e.preventDefault();

        wcsdmMapPicker.destroyMap();

        $('.modal-close-link').show();

        toggleBottons();

        $('#wcsdm-row-map-picker').hide().siblings().not('.wcsdm-hidden').show();
    },
    applyForm: function (e) {
        "use strict";

        e.preventDefault();

        if (isMapError) {
            return;
        }

        var apiKey = $('#woocommerce_wcsdm_api_key__dummy').val();
        if (_.isEmpty(apiKey)) {
            $('#woocommerce_wcsdm_api_key__dummy').val('InvalidKey').trigger('input');
            return;
        }

        var testDistanceMatrix = function () {
            var dfd = jQuery.Deferred();

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
                    console.log('testDistanceMatrix', { response: response, status: status });
                    dfd.resolve(status);
                });

            return dfd.promise();
        };

        $.when(testDistanceMatrix()).then(function (status) {
            if (status.toLowerCase() === 'ok' && !isMapError) {
                $('#woocommerce_wcsdm_lat').val(wcsdmMapPicker.lat);
                $('#woocommerce_wcsdm_lng').val(wcsdmMapPicker.lng);
                $('#woocommerce_wcsdm_api_key').val($('#woocommerce_wcsdm_api_key__dummy').val());
                wcsdmMapPicker.hideForm(e);
                return;
            }

            alert(status);
        });
    },
    destroyMap: function () {
        window.google = undefined;
        $('#wcsdm-map-canvas').hide().empty();
        $('#wcsdm-map-search').hide().val('');
    },
    initMap: function () {
        wcsdmMapPicker.destroyMap();

        isMapError = false;

        var apiKey = $('#woocommerce_wcsdm_api_key__dummy').val();

        if (_.isEmpty(apiKey)) {
            apiKey = 'InvalidKey';
        }

        $('#wcsdm-map-canvas').show();
        $('#wcsdm-map-search').show();

        var scriptUrl = 'https://maps.googleapis.com/maps/api/js?libraries=geometry,places&key=' + apiKey;
        $.getScript(scriptUrl, function () {
            wcsdmMapPicker.renderMap();
        });
    },
    renderMap: function () {
        wcsdmMapPicker.lat = $('#woocommerce_wcsdm_lat').val();
        wcsdmMapPicker.lng = $('#woocommerce_wcsdm_lng').val();

        var currentLatLng = {
            lat: _.isEmpty(wcsdmMapPicker.lat) ? parseFloat(wcsdmMapPicker.params.defaultLat) : parseFloat(wcsdmMapPicker.lat),
            lng: _.isEmpty(wcsdmMapPicker.lng) ? parseFloat(wcsdmMapPicker.params.defaultLng) : parseFloat(wcsdmMapPicker.lng),
        };

        var map = new google.maps.Map(
            document.getElementById('wcsdm-map-canvas'),
            {
                mapTypeId: 'roadmap',
                center: currentLatLng,
                zoom: 12,
                streetViewControl: false,
                mapTypeControl: false,
            }
        );

        var marker = new google.maps.Marker({
            map: map,
            position: currentLatLng,
            draggable: true,
            icon: wcsdmMapPicker.params.marker
        });

        var infowindow = new google.maps.InfoWindow({ maxWidth: 350 });

        if (_.isEmpty(wcsdmMapPicker.lat) || _.isEmpty(wcsdmMapPicker.lng)) {
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

        var mapSearchInput = document.getElementById('wcsdm-map-search');
        // map.controls[google.maps.ControlPosition.TOP_LEFT].push(mapSearchInput);

        var mapSearchBox = new google.maps.places.SearchBox(mapSearchInput);

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
                    var infowindowCOntent = [
                        wcsdmMapPicker.params.i18n.latitude + ': ' + location.lat().toString(),
                        wcsdmMapPicker.params.i18n.longitude + ': ' + location.lng().toString(),
                    ];
                    infowindow.setContent(infowindowCOntent.join('<br /><br />'));
                    infowindow.open(map, marker);

                    marker.addListener('click', function () {
                        infowindow.open(map, marker);
                    });

                    $('#wcsdm-map-search').val(results[0].formatted_address);
                }
            }
        );
        map.setCenter(location);
        wcsdmMapPicker.lat = location.lat();
        wcsdmMapPicker.lng = location.lng();
    }
};
