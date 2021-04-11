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

    var infoWindow = new google.maps.InfoWindow({
      maxWidth: 350,
      content: wcsdmMapPicker.params.i18n.drag_marker,
    });

    infoWindow.open(map, marker);

    google.maps.event.addListener(marker, 'dragstart', function () {
      infoWindow.close();
    });

    google.maps.event.addListener(marker, 'dragend', function (event) {
      wcsdmMapPicker.setLatLng(map, marker, infoWindow, event.latLng.lat(), event.latLng.lng());
    });

    marker.addListener('click', function () {
      infoWindow.open(map, marker);
    });

    $('#wcsdm-map-wrap').prepend(wp.template('wcsdm-map-search-panel')());

    map.controls[google.maps.ControlPosition.TOP_LEFT].push(document.getElementById('wcsdm-map-search-panel'));

    var mapSearchBox = new google.maps.places.SearchBox(document.getElementById('wcsdm-map-search-input'));

    mapSearchBox.addListener('places_changed', function () {
      var places = mapSearchBox.getPlaces();

      if (places.length === 0) {
        return;
      }

      var place = _.find(places, function (found) {
        if (!found.geometry) {
          return false;
        }

        return true;
      });

      wcsdmMapPicker.setLatLng(map, marker, infoWindow, place.geometry.location.lat(), place.geometry.location.lng(), true);
    });

    google.maps.event.addListenerOnce(map, 'idle', function () {
      $('#wcsdm-map-search-panel').removeClass('wcsdm-hidden');

      if (!_.isEmpty(wcsdmMapPicker.origin_lat) && !_.isEmpty(wcsdmMapPicker.origin_lng)) {
        wcsdmMapPicker.setLatLng(map, marker, infoWindow, currentLatLng.lat, currentLatLng.lng);
      } else {
        $('#wcsdm-map-search-panel').addClass('expanded');
      }
    });
  },
  destroyMap: function () {
    if (window.google) {
      window.google = undefined;
    }

    $('#wcsdm-map-canvas').empty();
    $('#wcsdm-map-search-panel').remove();
  },
  setLatLng: function (map, marker, infoWindow, lat, lng, setCenter) {
    var latLng = new google.maps.LatLng(lat, lng);

    var geocoder = new google.maps.Geocoder();

    geocoder.geocode({ location: latLng }, function (results, status) {
      if ('OK' !== status) {
        return;
      }

      var result = _.find(results, function (found) {
        return found.formatted_address && found.formatted_address.length > 0;
      });

      if (!result) {
        return;
      }

      var contents = [
        result.formatted_address,
        '<span class="wcsdm-coordinate-label">' + wcsdmMapPicker.params.i18n.latitude + '</span>: ' + lat,
        '<span class="wcsdm-coordinate-label">' + wcsdmMapPicker.params.i18n.longitude + '</span>: ' + lng,
      ];

      infoWindow.setContent(contents.join('<hr />'));

      infoWindow.open(map, marker);

      if (setCenter) {
        marker.setPosition(latLng);
        map.setCenter(latLng);
      }

      $('#wcsdm-map-search-input').val(result.formatted_address);

      wcsdmMapPicker.origin_lat = lat;
      wcsdmMapPicker.origin_lng = lng;
      wcsdmMapPicker.origin_address = result.formatted_address;
    });
  },
  isEditingAPIKey: function () {
    return $('.wcsdm-field-type--api_key.editing').length > 0;
  },
};
