/**
 * Map Picker
 */

function createSearchInput() {
  const searchInput = document.createElement("input");

  // Set CSS for the control.
  searchInput.type = "text";
  searchInput.id = "wcsdm-map-search-input";

  return searchInput;
}


var wcsdmMapPicker = {
  params: {},
  origin_lat: '',
  origin_lng: '',
  origin_address: '',
  zoomLevel: 16,
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

    // Show Store Location Picker
    $(document).off('click', '.wcsdm-btn--map-show', wcsdmMapPicker.showLocationPicker);
    $(document).on('click', '.wcsdm-btn--map-show', wcsdmMapPicker.showLocationPicker);

    // Hide Store Location Picker
    $(document).off('click', '#wcsdm-btn--map-cancel', wcsdmMapPicker.hideLocationPicker);
    $(document).on('click', '#wcsdm-btn--map-cancel', wcsdmMapPicker.hideLocationPicker);

    // Apply Store Location
    $(document).off('click', '#wcsdm-btn--map-apply', wcsdmMapPicker.applyLocationPicker);
    $(document).on('click', '#wcsdm-btn--map-apply', wcsdmMapPicker.applyLocationPicker);
  },
  showLocationPicker: function (event) {
    event.preventDefault();

    $(this).blur();

    wcsdmMapPicker.destroyMap();

    var modalHeaderHeight = $('.wc-backbone-modal-header').height();
    var modalMaxHeight = parseFloat($('.wc-modal-shipping-method-settings').css('max-height'));

    $('.wc-modal-shipping-method-settings').find('form').hide().after(wp.template('wcsdm-map-wrap')({
      height: (modalMaxHeight - modalHeaderHeight) + 'px'
    }));

    wcsdmMapPicker.apiKeyError = '';

    var api_key_picker = $('#woocommerce_wcsdm_api_key_picker').val();

    wcsdmToggleButtons({
      left: {
        id: 'map-cancel',
        label: wcsdmI18n('Cancel'),
      },
      right: {
        id: 'map-apply',
        label: wcsdmI18n('Apply Changes'),
      }
    });

    $('.modal-close-link').hide();

    $('.wc-backbone-modal-header').find('h1').append('<span>' + wcsdmI18n('Store Location Picker') + '</span>');

    wcsdmMapPicker.initMap(api_key_picker);
  },
  hideLocationPicker: function (e) {
    e.preventDefault();

    wcsdmMapPicker.destroyMap();

    wcsdmToggleButtons();

    $('.modal-close-link').show();

    $('.wc-backbone-modal-header').find('h1 span').remove();

    $('.wc-modal-shipping-method-settings').find('form').show();
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
  initMap: function (apiKey) {
    if (_.isEmpty(apiKey)) {
      apiKey = 'InvalidKey';
    }

    var script = document.createElement('script');
    script.id = 'wcsdm-google-maps-api';
    script.src = 'https://maps.googleapis.com/maps/api/js?key=' + apiKey + '&callback=initMap&loading=async&libraries=geometry,places';
    script.async = true;

    window.initMap = wcsdmMapPicker.renderMap;

    document.head.appendChild(script);
  },
  renderMap: function () {
    wcsdmMapPicker.origin_lat = $('#woocommerce_wcsdm_origin_lat').val();
    wcsdmMapPicker.origin_lng = $('#woocommerce_wcsdm_origin_lng').val();

    var currentLatLng = {
      lat: _.isEmpty(wcsdmMapPicker.origin_lat) ? parseFloat(wcsdmMapPicker.params.defaultLat) : parseFloat(wcsdmMapPicker.origin_lat),
      lng: _.isEmpty(wcsdmMapPicker.origin_lng) ? parseFloat(wcsdmMapPicker.params.defaultLng) : parseFloat(wcsdmMapPicker.origin_lng)
    };

    var map = new window.google.maps.Map(
      document.getElementById('wcsdm-map-canvas'),
      {
        mapTypeId: 'roadmap',
        center: currentLatLng,
        zoom: wcsdmMapPicker.zoomLevel,
        streetViewControl: false,
        mapTypeControl: false,
        fullscreenControl: false,
      }
    );

    var marker = new window.google.maps.Marker({
      map: map,
      position: currentLatLng,
      draggable: true,
      icon: wcsdmMapPicker.params.marker
    });

    var infoWindow = new window.google.maps.InfoWindow({ maxWidth: 350 });

    if (_.isEmpty(wcsdmMapPicker.origin_lat) || _.isEmpty(wcsdmMapPicker.origin_lng)) {
      infoWindow.setContent(wcsdmI18n('Drag this marker or search your address at the input above.'));
      infoWindow.open(map, marker);
    } else {
      wcsdmMapPicker.setLatLng(marker.position, marker, map, infoWindow);
    }

    window.google.maps.event.addListener(marker, 'dragstart', function () {
      infoWindow.close();
    });

    window.google.maps.event.addListener(marker, 'dragend', function (event) {
      wcsdmMapPicker.setLatLng(event.latLng, marker, map, infoWindow);
    });

    const searchInputWrapper = document.createElement("div");

    // Create the control.
    const searchInput = createSearchInput(map);

    // Append the control to the DIV.
    searchInputWrapper.appendChild(searchInput);

    map.controls[google.maps.ControlPosition.TOP_CENTER].push(searchInputWrapper);

    var mapSearchBox = new window.google.maps.places.SearchBox(searchInput);

    // Bias the SearchBox results towards current map's viewport.
    map.addListener('bounds_changed', function () {
      mapSearchBox.setBounds(map.getBounds());
    });

    // Listen for the event fired when the user selects a prediction and retrieve more details for that place.
    mapSearchBox.addListener('places_changed', function () {

      var places = mapSearchBox.getPlaces();

      if (places.length === 0) {
        return;
      }

      // For each place, get the icon, name and location.
      var bounds = new window.google.maps.LatLngBounds();

      places.forEach(function (place) {
        if (!place.geometry) {
          console.log('Returned place contains no geometry');
          return;
        }

        if (marker && marker.setMap) {
          marker.setMap(null);
        }

        marker = new window.google.maps.Marker({
          map: map,
          position: place.geometry.location,
          draggable: true,
          icon: wcsdmMapPicker.params.marker
        });

        window.google.maps.event.addListener(marker, 'dragstart', function () {
          infoWindow.close();
        });

        window.google.maps.event.addListener(marker, 'dragend', function (event) {
          wcsdmMapPicker.setLatLng(event.latLng, marker, map, infoWindow);
        });

        wcsdmMapPicker.setLatLng(place.geometry.location, marker, map, infoWindow, place.formatted_address);

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
    if (window.google && window.google.maps) {
      window.google.maps = null;
    }

    $('wcsdm-google-maps-api').remove();
    $('#wcsdm-map-wrap').remove();
  },
  setLatLng: function (location, marker, map, infoWindow, formatted_address) {
    var geocoder = new window.google.maps.Geocoder();

    if (formatted_address) {
      wcsdmMapPicker.setInfoWindow(infoWindow, map, marker, location.lat().toString(), location.lng().toString(), formatted_address);

      wcsdmMapPicker.origin_lat = location.lat();
      wcsdmMapPicker.origin_lng = location.lng();
      wcsdmMapPicker.origin_address = formatted_address;
    } else {
      geocoder.geocode(
        {
          latLng: location
        },
        function (results, status) {
          if (status === window.google.maps.GeocoderStatus.OK && results[0]) {
            wcsdmMapPicker.setInfoWindow(infoWindow, map, marker, location.lat().toString(), location.lng().toString(), results[0].formatted_address);

            wcsdmMapPicker.origin_lat = location.lat();
            wcsdmMapPicker.origin_lng = location.lng();
            wcsdmMapPicker.origin_address = results[0].formatted_address;
          }
        }
      );
    }

    $('#wcsdm-map-search-input').val('');

    map.setCenter(location);
  },
  setInfoWindow: function (infoWindow, map, marker, latitude, longitude, formatted_address) {
    var infoWindowContents = [
      '<div class="wcsdm-info-window-container">',
      '<div class="wcsdm-info-window-item">' + wcsdmI18n('Latitude') + '</div>',
      '<div class="wcsdm-info-window-item">' + latitude + '</div>',
      '<div class="wcsdm-info-window-item">' + wcsdmI18n('Longitude') + '</div>',
      '<div class="wcsdm-info-window-item">' + longitude + '</div>',
      '<div class="wcsdm-info-window-item">' + wcsdmI18n('Address') + '</div>',
      '<div class="wcsdm-info-window-item">' + formatted_address + '</div>',
      '</div>'
    ];

    infoWindow.setContent(infoWindowContents.join(''));
    infoWindow.open(map, marker);

    marker.addListener('click', function () {
      infoWindow.open(map, marker);
    });
  },
  convertError: function (text) {
    var exp = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
    return text.replace(exp, "<a href='$1' target='_blank'>$1</a>");
  },
};
