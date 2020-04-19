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
  editingAPIKeyPicker: false,
  init: function (params) {
    wcsdmMapPicker.params = params;
    wcsdmMapPicker.apiKeyError = '';
    wcsdmMapPicker.editingAPIKey = false;
    wcsdmMapPicker.editingAPIKeyPicker = false;

    ConsoleListener.on('error', function (errorMessage) {
      if (errorMessage.toLowerCase().indexOf('google') !== -1) {
        wcsdmMapPicker.apiKeyError = errorMessage;
      }
    });

    // Edit Api Key
    $(document).off('click', '.wcsdm-edit-api-key', wcsdmMapPicker.editApiKey);
    $(document).on('click', '.wcsdm-edit-api-key', wcsdmMapPicker.editApiKey);

    // Show Store Location Picker
    $(document).off('click', '.wcsdm-edit-location-picker', wcsdmMapPicker.showLocationPicker);
    $(document).on('click', '.wcsdm-edit-location-picker', wcsdmMapPicker.showLocationPicker);

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
  validateAPIKeyBothSide: function (apiKey, $input, $link) {
    wcsdmMapPicker.validateAPIKeyServerSide(apiKey, $input, $link, wcsdmMapPicker.validateAPIKeyBrowserSide);
  },
  validateAPIKeyBrowserSide: function (apiKey, $input, $link) {
    wcsdmMapPicker.apiKeyError = '';

    wcsdmMapPicker.initMap(apiKey, function () {
      var geocoderArgs = {
        latLng: new google.maps.LatLng(parseFloat(wcsdmMapPicker.params.defaultLat), parseFloat(wcsdmMapPicker.params.defaultLng)),
      };

      var geocoder = new google.maps.Geocoder();

      geocoder.geocode(geocoderArgs, function (results, status) {
        if (status.toLowerCase() === 'ok') {
          console.log('validateAPIKeyBrowserSide', results);

          $link.removeClass('editing').data('value', '');
          $input.prop('readonly', true);

          wcsdmMapPicker.apiKeyError = '';
          wcsdmMapPicker.editingAPIKeyPicker = false;
        }
      });

      clearInterval(wcsdmMapPicker.apiKeyErrorCheckInterval);

      wcsdmMapPicker.apiKeyErrorCheckInterval = setInterval(function () {
        if (!$link.hasClass('editing') || wcsdmMapPicker.apiKeyError) {
          clearInterval(wcsdmMapPicker.apiKeyErrorCheckInterval);

          $link.removeClass('loading').attr('disabled', false);

          if (wcsdmMapPicker.apiKeyError) {
            alert(wcsdmMapPicker.apiKeyError);
          }
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
      console.log('validateAPIKeyServerSide', response);
      wcsdmMapPicker.editingAPIKey = false;

      if (typeof onSuccess === 'function') {
        onSuccess(apiKey, $input, $link);
      } else {
        $link.removeClass('editing').data('value', '');
        $input.prop('readonly', true);
      }
    }).fail(function (error) {
      if (error.responseJSON && error.responseJSON.data) {
        alert(error.responseJSON.data);
      } else if (error.statusText) {
        alert(error.statusText);
      } else {
        alert('Google API Response Error: Uknown');
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
      if (apiKey && apiKey !== $link.data('value')) {
        $link.addClass('loading').attr('disabled', true);

        if ($link.attr('id') === 'api_key') {
          wcsdmMapPicker.validateAPIKeyServerSide(apiKey, $input, $link);
        } else {
          wcsdmMapPicker.validateAPIKeyBrowserSide(apiKey, $input, $link);
        }
      } else {
        $link.removeClass('editing').data('value', '');
        $input.prop('readonly', true);

        if ($link.attr('id') === 'api_key') {
          wcsdmMapPicker.editingAPIKey = false;
        } else {
          wcsdmMapPicker.listenConsole = false;
          wcsdmMapPicker.editingAPIKeyPicker = false;
        }
      }
    } else {
      $link.addClass('editing').data('value', apiKey);
      $input.prop('readonly', false);

      if ($link.attr('id') === 'api_key') {
        wcsdmMapPicker.editingAPIKey = true;
      } else {
        wcsdmMapPicker.editingAPIKeyPicker = true;
      }
    }
  },
  showLocationPicker: function (e) {
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

    var $subTitle = $('#wcsdm-field-group-wrap--location_picker').find('.wc-settings-sub-title').first().addClass('wcsdm-hidden');

    $('.wc-backbone-modal-header').find('h1').append('<span>' + $subTitle.text() + '</span>');

    wcsdmMapPicker.initMap($('#woocommerce_wcsdm_api_key_picker').val(), wcsdmMapPicker.renderMap);
  },
  hideLocationPicker: function (e) {
    e.preventDefault();

    wcsdmMapPicker.destroyMap();

    $('.modal-close-link').show();

    toggleBottons();

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
  }
};
