;(function($) {
"use strict";

var wcsdmSetting = {
	_inputLatId: '',
	_inputLngId: '',
	_mapWrapperId: '',
	_mapSearchId: '',
	_mapCanvasId: '',
	_zoomLevel: 16,
	_keyStr: 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=',
	init: function (params) {
		var self = this;
		self._params = params;
		self._inputLatSel = 'woocommerce_wcsdm_origin_lat';
		self._inputLngSel = 'woocommerce_wcsdm_origin_lng';
		self._mapWrapperSel = 'wcsdm-map-wrapper';
		self._mapSearchSel = 'wcsdm-map-search';
		self._mapCanvasSel = 'wcsdm-map-canvas';

		// Try show settings modal on settings page.
		if (self._params.show_settings) {
			setTimeout(function () {
				var isMethodAdded = false;
				var methods = $(document).find('.wc-shipping-zone-method-type');
				for (var i = 0; i < methods.length; i++) {
					var method = methods[i];
					if ($(method).text() === self._params.method_title) {
						$(method)
							.closest('tr')
							.find('.row-actions .wc-shipping-zone-method-settings')
							.trigger('click');
						isMethodAdded = true;
						return;
					}
				}
				// Show Add shipping method modal if the shipping is not added.
				if (!isMethodAdded) {
					$('.wc-shipping-zone-add-method').trigger('click');
					$('select[name="add_method_id"]')
						.val(self._params.method_id)
						.trigger('change');
				}
			}, 200);
		}

		// Handle setting link clicked.
		$(document).on('click', '.wc-shipping-zone-method-settings', function () {
			var method_title = $(this).closest('tr').find('.wc-shipping-zone-method-type').text();
			if (method_title !== self._params.method_title) {
				return false;
			}
			$('#woocommerce_wcsdm_gmaps_api_units').trigger('change');
			self._initGoogleMaps();
		});

		// Handle on distnace unit field setting changed.
		$(document).on('change', '#woocommerce_wcsdm_gmaps_api_units', function () {
			switch ($(this).val()) {
				case 'metric':
					$('.field-group.distance .field-group-icon').text('KM');
					$('option[value="per_unit"]').text(self._params.txt.per_unit_km);
					break;

				default:
					$('.field-group.distance .field-group-icon').text('MI');
					$('option[value="per_unit"]').text(self._params.txt.per_unit_mi);
					break;
			}
		});

		// Handle on latitude and longitude field setting changed.
		$(document).on('change', '#' + self._inputLatSel + ', #' + self._inputLngSel, function () {
			if ($('.gm-err-content').length) {
				return false;
			}
			self._initGoogleMaps();
		});

		// Handle toggle rate rows in bulk.
		$(document).on('change', '#rates-list-table thead .select-item', self._toggleRateRowsBulk);

		// Handle toggle rate rows in individually.
		$(document).on('change', '#rates-list-table tbody .select-item', self._toggleRateRows);

		// Handle add rate rows.
		$(document).on('click', '#rates-list-table .button.add_row', self._addRateRows);

		// Handle remove rate rows.
		$(document).on('click', '#rates-list-table .button.remove_rows', self._removeRateRows);
	},
	_initGoogleMaps: function () {
		var self = this;

		$('#' + self._mapWrapperSel).show().siblings('.description').hide();

		try {
			if (
				typeof google === 'undefined' ||
				typeof google.maps === 'undefined'
			) {
				throw 'google is not defined';
			}
			self._buildGoogleMaps();
		} catch (error) {
			var mapScriptUrl = 'https://maps.googleapis.com/maps/api/js?key=' + self._decode($('#map-secret-key').val()) + '&libraries=geometry,places&&language=' + self._params.language;
			$.getScript(mapScriptUrl, function () {
				self._buildGoogleMaps();
			});
		}
	},
	_buildGoogleMaps: function () {
		var self = this;
		var defaultLat = -6.175392;
		var defaultLng = 106.827153;
		var curLat = $('#' + self._inputLatSel).val();
		var curLng = $('#' + self._inputLngSel).val();
		curLat = curLat.length ? parseFloat(curLat) : defaultLat;
		curLng = curLng.length ? parseFloat(curLng) : defaultLng;
		var curLatLng = { lat: curLat, lng: curLng };
		var tmplMapCanvas = wp.template(self._mapCanvasSel);
		var tmplMapSearch = wp.template(self._mapSearchSel);
		if (!$('#' + self._mapCanvasSel).length) {
			$('#' + self._mapWrapperSel).append(
				tmplMapCanvas({
					map_canvas_id: self._mapCanvasSel
				})
			);
		}
		var markers = [];
		var map = new google.maps.Map(
			document.getElementById(self._mapCanvasSel),
			{
				center: curLatLng,
				zoom: self._zoomLevel,
				mapTypeId: 'roadmap'
			}
		);
		var marker = new google.maps.Marker({
			map: map,
			position: curLatLng,
			draggable: true,
			icon: self._params.marker
		});

		var infowindow = new google.maps.InfoWindow({ maxWidth: 350 });

		if (curLat === defaultLat && curLng === defaultLng) {
			infowindow.setContent(self._params.txt.drag_marker);
			infowindow.open(map, marker);
		} else {
			self._setLatLng(marker.position, marker, map, infowindow);
		}

		google.maps.event.addListener(marker, 'dragstart', function () {
			infowindow.close();
		});

		google.maps.event.addListener(marker, 'dragend', function (event) {
			self._setLatLng(event.latLng, marker, map, infowindow);
		});

		markers.push(marker);

		if (!$('#' + self._mapSearchSel).length) {
			$('#' + self._mapWrapperSel).append(
				tmplMapSearch({
					map_search_id: self._mapSearchSel
				})
			);
		}
		// Create the search box and link it to the UI element.
		var inputAddress = document.getElementById(self._mapSearchSel);
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
					icon: self._params.marker
				});
				self._setLatLng(place.geometry.location, marker, map, infowindow);
				google.maps.event.addListener(marker, 'dragstart', function () {
					infowindow.close();
				});
				google.maps.event.addListener(marker, 'dragend', function (event) {
					self._setLatLng(event.latLng, marker, map, infowindow);
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

		setInterval(function () {
			if ($('.gm-err-content').length) {
				$('#' + self._mapWrapperSel)
					.hide()
					.siblings('.description')
					.show();
				$('#' + self._mapSearchSel).remove();
				google = undefined;
			}
		}, 1000);
	},
	_setLatLng: function (location, marker, map, infowindow) {
		var self = this;
		var geocoder = new google.maps.Geocoder();
		geocoder.geocode(
			{
				latLng: location
			},
			function (results, status) {
				if (status === google.maps.GeocoderStatus.OK && results[0]) {
					infowindow.setContent(results[0].formatted_address);
					infowindow.open(map, marker);
					marker.addListener('click', function () {
						infowindow.open(map, marker);
					});
				}
			}
		);
		map.setCenter(location);
		$('#' + self._inputLatSel).val(location.lat());
		$('#' + self._inputLngSel).val(location.lng());
	},
	_toggleRateRowsBulk: function (e) {
		var $this = $(e.currentTarget);
		var $checkbox = $this.closest('table').find('tbody input[type=checkbox]');
		if (!$checkbox.length) {
			$this.prop('checked', !$this.is(':checked'));
			return;
		}
		$checkbox.prop('checked', $this.is(':checked')).trigger('change');
	},
	_toggleRateRows: function (e) {
		var $this = $(e.currentTarget);
		var $table = $this.closest('table');
		var $thead = $table.find('thead');
		var $tbody = $table.find('tbody');
		var $tfoot = $table.find('tfoot');

		if ($tbody.find('[type=checkbox]:checked').length) {
			$tfoot.find('.add_row').hide('fast', function () {
				$(this).siblings('.remove_rows').show();
			});
		} else {
			$tfoot.find('.remove_rows').hide('fast', function () {
				$(this).siblings('.add_row').show();
			});
		}

		if ($tbody.find('[type=checkbox]:checked').length === $tbody.find('[type=checkbox]').length) {
			$thead.find('input[type=checkbox]').prop('checked', true);
		} else {
			$thead.find('input[type=checkbox]').prop('checked', false);
		}
	},
	_addRateRows: function (e) {
		e.preventDefault();
		$('#rates-list-table tbody').append(wp.template('rates-list-input-table-row')).find('tr:last-child .field-distance').focus();
		$('#woocommerce_wcsdm_gmaps_api_units').trigger('change');
	},
	_removeRateRows: function (e) {
		e.preventDefault();
		var $this = $(e.currentTarget);
		var $table = $this.closest('table');
		$this.hide('fast', function () {
			$(this).siblings('.add_row').show();
		});
		$table.find('thead [type=checkbox]').prop('checked', false);
		$table.find('tbody [type=checkbox]:checked').each(function (index, checkbox) {
			$(checkbox).closest('tr').remove();
		});
	},
	_encode: function (e) {
		var self = this;
		var t = '';
		var n, r, i, s, o, u, a;
		var f = 0;
		e = self._utf8_encode(e);
		while (f < e.length) {
			n = e.charCodeAt(f++);
			r = e.charCodeAt(f++);
			i = e.charCodeAt(f++);
			s = n >> 2;
			o = ((n & 3) << 4) | (r >> 4);
			u = ((r & 15) << 2) | (i >> 6);
			a = i & 63;
			if (isNaN(r)) {
				u = a = 64;
			} else if (isNaN(i)) {
				a = 64;
			}
			t =
				t +
				this._keyStr.charAt(s) +
				this._keyStr.charAt(o) +
				this._keyStr.charAt(u) +
				this._keyStr.charAt(a);
		}
		return t;
	},
	_decode: function (e) {
		var self = this;
		var t = '';
		var n, r, i;
		var s, o, u, a;
		var f = 0;
		e = e.replace(/[^A-Za-z0-9+/=]/g, '');
		while (f < e.length) {
			s = this._keyStr.indexOf(e.charAt(f++));
			o = this._keyStr.indexOf(e.charAt(f++));
			u = this._keyStr.indexOf(e.charAt(f++));
			a = this._keyStr.indexOf(e.charAt(f++));
			n = (s << 2) | (o >> 4);
			r = ((o & 15) << 4) | (u >> 2);
			i = ((u & 3) << 6) | a;
			t = t + String.fromCharCode(n);
			if (u !== 64) {
				t = t + String.fromCharCode(r);
			}
			if (a !== 64) {
				t = t + String.fromCharCode(i);
			}
		}
		t = self._utf8_decode(t);
		return t;
	},
	_utf8_encode: function (e) {
		e = e.replace(/rn/g, 'n');
		var t = '';
		for (var n = 0; n < e.length; n++) {
			var r = e.charCodeAt(n);
			if (r < 128) {
				t += String.fromCharCode(r);
			} else if (r > 127 && r < 2048) {
				t += String.fromCharCode((r >> 6) | 192);
				t += String.fromCharCode((r & 63) | 128);
			} else {
				t += String.fromCharCode((r >> 12) | 224);
				t += String.fromCharCode(((r >> 6) & 63) | 128);
				t += String.fromCharCode((r & 63) | 128);
			}
		}
		return t;
	},
	_utf8_decode: function (e) {
		var t = '';
		var n = 0;
		var r = 0;
		var c2 = 0;
		var c3 = 0;
		while (n < e.length) {
			r = e.charCodeAt(n);
			if (r < 128) {
				t += String.fromCharCode(r);
				n++;
			} else if (r > 191 && r < 224) {
				c2 = e.charCodeAt(n + 1);
				t += String.fromCharCode(((r & 31) << 6) | (c2 & 63));
				n += 2;
			} else {
				c2 = e.charCodeAt(n + 1);
				c3 = e.charCodeAt(n + 2);
				t += String.fromCharCode(
					((r & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63)
				);
				n += 3;
			}
		}
		return t;
	}
};
$(document).ready(function () {
	wcsdmSetting.init(wcsdm_params);
});
}(jQuery));
