/**
 * Map Picker
 */
var wcsdmMapPicker = {
  params: {},
  origin_lat: '',
  origin_lng: '',
  origin_address: '',
  zoomLevel: 16,
  apiKeyErrorCheckInterval: null,
  apiKeyError: '',
  editingAPIKey: false,
  init: function (params) {
    wcsdmMapPicker.params = params;
    wcsdmMapPicker.apiKeyError = '';
    wcsdmMapPicker.editingAPIKey = false;

    ConsoleListener.on('error', function (errorMessage) {
      if (errorMessage.toLowerCase().indexOf('google') !== -1) {
        wcsdmMapPicker.apiKeyError = errorMessage;
      }

      if ($('.gm-err-message').length) {
        $('.gm-err-message').replaceWith('<p style="text-align:center">' + wcsdmMapPicker.convertError(errorMessage) + '</p>');
      }
    });

    // Edit Api Key
    $(document).off('focus', '.wcsdm-field-type--api_key');
    $(document).on('focus', '.wcsdm-field-type--api_key', function () {
      if ($(this).prop('readonly') && !$(this).hasClass('loading')) {
        $(this).data('value', $(this).val()).prop('readonly', false);
      }
    });

    $(document).off('blur', '.wcsdm-field-type--api_key');
    $(document).on('blur', '.wcsdm-field-type--api_key', function () {
      if (!$(this).prop('readonly') && !$(this).hasClass('editing')) {
        $(this).data('value', undefined).prop('readonly', true);
      }
    });

    $(document).off('input', '.wcsdm-field-type--api_key', wcsdmMapPicker.handleApiKeyInput);
    $(document).on('input', '.wcsdm-field-type--api_key', wcsdmMapPicker.handleApiKeyInput);

    // Edit Api Key
    $(document).off('click', '.wcsdm-edit-api-key', wcsdmMapPicker.editApiKey);
    $(document).on('click', '.wcsdm-edit-api-key', wcsdmMapPicker.editApiKey);

    // Show Store Location Picker
    $(document).off('click', '.wcsdm-field--origin');
    $(document).on('click', '.wcsdm-field--origin', function () {
      if ($(this).prop('readonly')) {
        $('.wcsdm-edit-location-picker').trigger('click');
      }
    });

    // Show Store Location Picker
    $(document).off('focus', '[data-link="location_picker"]', wcsdmMapPicker.showLocationPicker);
    $(document).on('focus', '[data-link="location_picker"]', wcsdmMapPicker.showLocationPicker);

    // Hide Store Location Picker
    $(document).off('click', '#wcsdm-btn--map-cancel', wcsdmMapPicker.hideLocationPicker);
    $(document).on('click', '#wcsdm-btn--map-cancel', wcsdmMapPicker.hideLocationPicker);

    // Apply Store Location
    $(document).off('click', '#wcsdm-btn--map-apply', wcsdmMapPicker.applyLocationPicker);
    $(document).on('click', '#wcsdm-btn--map-apply', wcsdmMapPicker.applyLocationPicker);

    // Toggle Map Search Panel
    $(document).off('click', '#wcsdm-map-search-panel-toggle', wcsdmMapPicker.toggleMapSearch);
    $(document).on('click', '#wcsdm-map-search-panel-toggle', wcsdmMapPicker.toggleMapSearch);
  },
  validateAPIKeyBothSide: function ($input) {
    wcsdmMapPicker.validateAPIKeyServerSide($input, wcsdmMapPicker.validateAPIKeyBrowserSide);
  },
  validateAPIKeyBrowserSide: function ($input) {
    wcsdmMapPicker.apiKeyError = '';

    wcsdmMapPicker.initMap($input.val(), function () {
      var geocoderArgs = {
        latLng: new google.maps.LatLng(parseFloat(wcsdmMapPicker.params.defaultLat), parseFloat(wcsdmMapPicker.params.defaultLng)),
      };

      var geocoder = new google.maps.Geocoder();

      geocoder.geocode(geocoderArgs, function (results, status) {
        if (status.toLowerCase() === 'ok') {
          console.log('validateAPIKeyBrowserSide', results);

          $input.addClass('valid');

          setTimeout(function () {
            $input.removeClass('editing loading valid').data('value', undefined);
          }, 1000);
        }
      });

      clearInterval(wcsdmMapPicker.apiKeyErrorCheckInterval);

      wcsdmMapPicker.apiKeyErrorCheckInterval = setInterval(function () {
        if ($input.hasClass('valid') || wcsdmMapPicker.apiKeyError) {
          clearInterval(wcsdmMapPicker.apiKeyErrorCheckInterval);
        }

        if (wcsdmMapPicker.apiKeyError) {
          wcsdmMapPicker.showError($input, wcsdmMapPicker.apiKeyError);
          $input.prop('readonly', false).removeClass('loading');
        }
      }, 300);
    });
  },
  validateAPIKeyServerSide: function ($input, onSuccess) {
    $.ajax({
      method: 'POST',
      url: wcsdmMapPicker.params.ajax_url,
      data: {
        action: 'wcsdm_validate_api_key_server',
        nonce: wcsdmMapPicker.params.validate_api_key_nonce,
        key: $input.val(),
      }
    }).done(function (response) {
      console.log('validateAPIKeyServerSide', response);

      if (typeof onSuccess === 'function') {
        onSuccess($input);
      } else {
        $input.addClass('valid');

        setTimeout(function () {
          $input.removeClass('editing loading valid').data('value', undefined);
        }, 1000);
      }
    }).fail(function (error) {
      if (error.responseJSON && error.responseJSON.data) {
        wcsdmMapPicker.showError($input, error.responseJSON.data);
      } else if (error.statusText) {
        wcsdmMapPicker.showError($input, error.statusText);
      } else {
        wcsdmMapPicker.showError($input, wcsdmMapPicker.params.i18n.errors.unknown);
      }

      $input.prop('readonly', false).removeClass('loading');
    });
  },
  showError: function ($input, errorMessage) {
    $('<div class="error notice wcsdm-error-box"><p>' + wcsdmMapPicker.convertError(errorMessage) + '</p></div>')
      .hide()
      .appendTo($input.closest('td'))
      .slideDown();
  },
  removeError: function ($input) {
    $input.closest('td')
      .find('.wcsdm-error-box')
      .slideUp('fast');
  },
  convertError: function (text) {
    var exp = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
    return text.replace(exp, "<a href='$1' target='_blank'>$1</a>");
  },
  handleApiKeyInput: function (e) {
    var $input = $(e.currentTarget);

    if ($input.val() === $input.data('value')) {
      $input.removeClass('editing');
    } else {
      $input.addClass('editing');
    }

    wcsdmMapPicker.removeError($input);
  },
  editApiKey: function (e) {
    e.preventDefault();

    var $input = $(this).blur().prev('input');

    if (!$input.hasClass('editing') || $input.hasClass('loading')) {
      return;
    }

    $input.prop('readonly', true).addClass('loading');

    if ($input.attr('data-key') === 'api_key') {
      wcsdmMapPicker.validateAPIKeyServerSide($input);
    } else {
      wcsdmMapPicker.validateAPIKeyBrowserSide($input);
    }

    wcsdmMapPicker.removeError($input);
  },
  showLocationPicker: function (event) {
    event.preventDefault();

    $(this).blur();

    wcsdmMapPicker.apiKeyError = '';

    var api_key_picker = $('#woocommerce_wcsdm_api_key_picker').val();

    if (wcsdmMapPicker.isEditingAPIKey()) {
      return window.alert(wcsdmError('finish_editing_api'));
    } else if (!api_key_picker.length) {
      return window.alert(wcsdmError('api_key_picker_empty'));
    }

    $('.modal-close-link').hide();

    wcsdmToggleButtons({
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

    var $subTitle = $('#wcsdm-field-group-wrap--location_picker').find('.wc-settings-sub-title').first().addClass('wcsdm-hidden');

    $('.wc-backbone-modal-header').find('h1').append('<span>' + $subTitle.text() + '</span>');

    wcsdmMapPicker.initMap(api_key_picker, wcsdmMapPicker.renderMap);
  },
  hideLocationPicker: function (e) {
    e.preventDefault();

    wcsdmMapPicker.destroyMap();

    $('.modal-close-link').show();

    wcsdmToggleButtons();

    $('#wcsdm-field-group-wrap--location_picker').find('.wc-settings-sub-title').first().removeClass('wcsdm-hidden');

    $('.wc-backbone-modal-header').find('h1 span').remove();

    $('#wcsdm-field-group-wrap--location_picker').hide().siblings().not('.wcsdm-hidden').fadeIn();
  },
  applyLocationPicker: function (e) {
    e.preventDefault();

    if (!wcsdmMapPicker.apiKeyError) {
      $('#woocommerce_wcsdm_origin_lat').val(wcsdmMapPicker.origin_lat);
      $('#woocommerce_wcsdm_origin_lng').val(wcsdmMapPicker.origin_lng);
      $('#woocommerce_wcsdm_origin_address').val(wcsdmMapPicker.origin_address);
    }

    wcsdmMapPicker.hideLocationPicker(e);
  },
  toggleMapSearch: function (e) {
    e.preventDefault();

    $('#wcsdm-map-search-panel').toggleClass('expanded');
  },
  initMap: function (apiKey, callback) {
    wcsdmMapPicker.destroyMap();

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
  },
  isEditingAPIKey: function () {
    return $('.wcsdm-field-type--api_key.editing').length > 0;
  },
};
