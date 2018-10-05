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

    if (!_.keys(results).length) {
        return results;
    }

    var data = {};
    _.keys(results).forEach(function (key) {
        if (key.indexOf('data-') !== 0) {
            data[key] = results[key];
        }
    });

    return data;
};

function showError(args) {
    var params = _.extend({
        selector: '',
        method: 'before',
        title: wcsdm_params.i18n.errors.error_title,
        content: ''
    }, args);

    if (params.selector.length) {
        var $selector = $(params.selector);
        if (!$selector.length) {
            return;
        }

        switch (params.method) {
            case 'before':
                $selector.before(wp.template('wcsdm-error')(params));
                break;

            case 'after':
                $selector.after(wp.template('wcsdm-error')(params));
                break;

            default:
                $selector.append(wp.template('wcsdm-error')(params));
                break;
        }

        $('.wc-modal-shipping-method-settings').animate({
            scrollTop: $('#wcsdm-error').position().top
        }, 500);

        return $selector;
    }
}

function hideError() {
    $('#wcsdm-error').remove();
    $('.wc-modal-shipping-method-settings').children().removeClass('error');
}

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

var rowIndex;
var rowScrollTop = 0;

var wcsdmTableRates = {
    init: function (params) {
        wcsdmTableRates.params = params;

        $('.wcsdm-rate-field--hidden').each(function (i, input) {
            var $input = $(input);
            $input.closest('tr').find('.wcsdm-rate-field--dummy--' + $input.data('key')).val($input.val());
        });

        $('#woocommerce_wcsdm_gmaps_api_units').trigger('change');

        setTimeout(function () {
            wcsdmTableRates.toggleTableRates();
        }, 100);

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
        $(document).on('change', '#woocommerce_wcsdm_gmaps_api_units.changed', function (e) {
            $('option[value="per_unit"]').text(wcsdmTableRates.params.i18n.distance[$(e.currentTarget).val()].perUnit);
        });

        // Handle on Rates per Shipping CLass option field value changed.
        $(document).on('change', '.wcsdm-rate-field--advanced', function () {
            $('.wcsdm-rate-field--advanced').each(function (i, field) {
                $(field).closest('tr').show();
                var showField = true;
                var fieldShowIf = $(field).data('show_if');
                if (fieldShowIf) {
                    _.keys(fieldShowIf).forEach(function (key) {
                        var fieldShowIfTarget = $('.wcsdm-rate-field--advanced--' + key).val();
                        if (fieldShowIf[key].indexOf(fieldShowIfTarget) === -1) {
                            showField = false;
                        }
                    });
                }

                if (showField) {
                    var fieldHideIf = $(field).data('hide_if');
                    if (fieldHideIf) {
                        _.keys(fieldHideIf).forEach(function (key) {
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

            wcsdmTableRates.validateRatesList();
        }, 500));

        // // Sort rows based distance field on blur.
        $(document).on('blur', '.wcsdm-rate-field--dummy--distance', function (e) {
            if ($(e.currentTarget).val().length) {
                wcsdmTableRates.sortRates();
            }
        });

        // Handle on advanced rate settings link clicked.
        $(document).on('click', '.wcsdm-btn-advanced-rate', function (e) {
            e.preventDefault();

            hideError();

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

            wcsdmTableRates.setFooterButtons();

            $('#wcsdm-row-advanced').show().siblings().hide();

        });

        // Handle on Cancel Changes button clicked.
        $(document).on('click', '#wcsdm-btn-advanced-rate-cancel', function (e) {
            e.preventDefault();

            hideError();

            wcsdmTableRates.restoreFooterButtons();

            $('#wcsdm-row-advanced').hide().siblings().not('.wcsdm-row--hidden').show();

            $('#wcsdm-table-advanced tr').removeClass('error');

            $('#wcsdm-table-rates tbody tr:eq(' + rowIndex + ')').addClass('applied');

            wcsdmTableRates.validateRatesList();

            $('.wc-modal-shipping-method-settings').animate({
                scrollTop: rowScrollTop
            }, 500, function () {
                setTimeout(function () {
                    $('#wcsdm-table-rates tbody tr').removeClass('applied');
                }, 800);
            });
        });

        // Handle on Apply Changes button clicked.
        $(document).on('click', '#wcsdm-btn-advanced-rate-apply', function (e) {
            e.preventDefault();
            hideError();

            var errors = wcsdmTableRates.getRateFormErrors($('.wcsdm-rate-field--advanced'));

            if (errors.length) {
                var errorMessages = {};

                for (var index = 0; index < errors.length; index++) {
                    errorMessages[errors[index].key] = errors[index].message;
                }

                var errorMessage = '';
                _.keys(errorMessages).forEach(function (key) {
                    $('.wcsdm-rate-field--advanced--' + key).closest('tr').addClass('error');
                    errorMessage += '<p id="wcsdm-rate-field--error--' + key + '">' + errorMessages[key] + '</p>';
                });

                showError({
                    selector: '#wcsdm-table-advanced',
                    content: errorMessage
                });
                return;
            }

            var formData = wcsdmTableRates._populateFormData($('.wcsdm-rate-field--advanced'));

            if (_.keys(formData).length) {
                _.keys(formData).forEach(function (key) {
                    $('#wcsdm-table-rates tbody tr:eq(' + rowIndex + ')').removeClass('error').addClass('applied').find('.wcsdm-rate-field--dummy--' + key + ', .wcsdm-rate-field--hidden--' + key).val(formData[key]);
                });

                wcsdmTableRates.restoreFooterButtons();

                $('#wcsdm-row-advanced').hide().siblings().not('.wcsdm-row--hidden').show();

                $('.wc-modal-shipping-method-settings').animate({
                    scrollTop: rowScrollTop
                }, 500, function () {
                    setTimeout(function () {
                        wcsdmTableRates.sortRates();
                        wcsdmTableRates.validateRatesList();
                    }, 100);
                });
            }
        });

        // // Handle on Save Changes button clicked.
        // $(document).on('click', '#wcsdm-btn-primary-save-changes', function (e) {
        //     e.preventDefault();
        //     hideError();

        //     var locationErrorMessage = '';
        //     var locationFields = ['woocommerce_wcsdm_origin_lat', 'woocommerce_wcsdm_origin_lng'];
        //     for (var i = 0; i < locationFields.length; i++) {
        //         var locationFieldKey = locationFields[i];
        //         var $locationField = $('#' + locationFieldKey);
        //         if (!$locationField.val().length) {
        //             locationErrorMessage += '<p id="wcsdm-rate-field--error--' + locationFieldKey + '">' + wcsdmTableRates.params.i18n.errors.field_required.replace('%s', $locationField.data('title')) + '</p>';
        //             $('#' + locationFieldKey + '_dummy').closest('td').addClass('error');
        //         }
        //     }

        //     if (locationErrorMessage.length) {
        //         showError({
        //             selector: '#wcsdm-col-store-location',
        //             content: locationErrorMessage
        //         });
        //         return;
        //     }

        //     if (!$('#wcsdm-table-rates tbody tr').length) {
        //         showError({
        //             selector: '#wcsdm-table-rates',
        //             content: wcsdmTableRates.params.i18n.errors.rates_empty
        //         });
        //         return;
        //     }

        //     var errors = wcsdmTableRates.validateRatesList();
        //     if (errors.length) {
        //         var errorMessages = {};

        //         for (var index = 0; index < errors.length; index++) {
        //             errorMessages[errors[index].key] = errors[index].message;
        //         }

        //         var errorMessage = '';
        //         _.keys(errorMessages).forEach(function (key) {
        //             $('.wcsdm-rate-field--advanced--' + key).closest('tr').addClass('error');
        //             errorMessage += '<p id="wcsdm-rate-field--error--' + key + '">' + errorMessages[key] + '</p>';
        //         });

        //         showError({
        //             selector: '#wcsdm-table-rates',
        //             content: errorMessage
        //         });
        //         return;
        //     }

        //     $('#btn-ok').trigger('click');
        // });

        // Handle add rate rows.
        $(document).on('click', '#wcsdm-btn-add-rate', wcsdmTableRates.addRateRows);

        // Handle remove rate rows.
        $(document).on('click', '.btn-delete-rate', wcsdmTableRates.removeRateRows);
    },
    setFooterButtons: function () {
        $('#wcsdm-footer-buttons').remove();
        $('#btn-ok').after(wp.template('wcsdm-footer-buttons')({
            btn_left: {
                label: wcsdmMapPicker.params.i18n.cancel,
                id: 'wcsdm-btn-advanced-rate-cancel',
                icon: 'undo'
            },
            btn_right: {
                label: wcsdmMapPicker.params.i18n.apply_changes,
                id: 'wcsdm-btn-advanced-rate-apply',
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
    sortRates: function () {
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
    validateRatesList: function () {
        hideError();
        var errors = wcsdmTableRates.getRateFormErrors($('.wcsdm-rate-field--hidden'));
        if (errors.length) {
            for (var index = 0; index < errors.length; index++) {
                $('#wcsdm-table-rates tbody tr:eq(' + errors[index].rowIndex + ')').find('.col-' + errors[index].key).addClass('error');
            }
        }
        return errors;
    },
    getRateFormErrors: function ($fields) {
        var errors = [];
        var fields = {};

        // Populate form fields
        $fields.each(function (i, field) {
            var fieldKey = $(field).attr('data-key');
            if (typeof fields[fieldKey] === 'undefined') {
                fields[fieldKey] = [];
            }
            fields[fieldKey].push(_.extend({}, $(field).attrs(), $(field).data(), {
                value: $(field).val(),
                rowIndex: $(field).closest('tr').index()
            }));
        });

        _.keys(fields).forEach(function (key) {
            var dataRows = fields[key];
            for (var index = 0; index < dataRows.length; index++) {
                var dataRow = dataRows[index];
                var ignoreField = false;
                var showIf = dataRow.show_if || false;
                var hideIf = dataRow.hide_if || false;

                if (showIf) {
                    _.keys(showIf).forEach(function (showIfKey) {
                        var showIfTarget = fields[showIfKey][index].value;
                        var showIfField = showIf[showIfKey];
                        if (showIfField.indexOf(showIfTarget) === -1) {
                            ignoreField = true;
                        }
                    });
                }

                if (hideIf) {
                    _.keys(hideIf).forEach(function (hideIfKey) {
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
    addRateRows: function (e) {
        e.preventDefault();
        $('#wcsdm-table-rates tbody').append(wp.template('rates-list-input-table-row')).find('tr:last-child .wcsdm-rate-field').each(function (i, input) {
            $(input).trigger('change');
            if ($(input).hasClass('wcsdm-rate-field--distance')) {
                $(input).focus();
            }
        });
        $('#woocommerce_wcsdm_gmaps_api_units').trigger('change');
        $('.wc-modal-shipping-method-settings').scrollTop($('.wc-modal-shipping-method-settings').find('form').outerHeight());
        wcsdmTableRates.toggleTableRates();
    },
    removeRateRows: function (e) {
        e.preventDefault();
        $(e.currentTarget).closest('tr').remove();
        wcsdmTableRates.toggleTableRates();
    },
    toggleTableRates: function (e) {
        $('#wcsdm-table-rates').find('thead, tfoot').show();

        if (!$('#wcsdm-table-rates tbody tr').length) {
            $('#wcsdm-table-rates').find('thead, tfoot').hide();
        }
    }
};

$(document).ready(function () {
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
        }, 400);
    }

    $(document).on('click', '.wc-shipping-zone-method-settings', function () {
        var params = _.mapObject(wcsdm_params, function (val, key) {
            switch (key) {
                case 'default_lat':
                case 'default_lng':
                case 'test_destination_lat':
                case 'test_destination_lng':
                    return parseFloat(val);

                default:
                    return val;
            }
        });

        // Bail early if the link clicked others shipping method
        var methodTitle = $(this).closest('tr').find('.wc-shipping-zone-method-type').text();
        if (methodTitle !== params.methodTitle) {
            return;
        }

        $('#btn-ok').hide().after(wp.template('wcsdm-footer-buttons')({
            btn_left: {
                label: params.i18n.add_rate,
                id: 'wcsdm-btn-add-rate',
                icon: 'plus'
            },
            btn_right: {
                label: params.i18n.save_changes,
                id: 'wcsdm-btn-save',
                icon: 'yes'
            }
        }));

        wcsdmTableRates.init(params);
        wcsdmMapPicker.init(params);
    });
});
}(jQuery));
