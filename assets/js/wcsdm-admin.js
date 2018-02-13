(function($) {
	"use strict";
	var wcsdmSetting = {
		_inputLatId: "woocommerce_wcsdm_origin_lat",
		_inputLngId: "woocommerce_wcsdm_origin_lng",
		_mapWrapperId: "wcsdm-map-wrapper",
		_mapSearchId: "wcsdm-map-search",
		_mapCanvasId: "wcsdm-map-canvas",
		_zoomLevel: 16,
		_keyStr:
			"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",
		init: function() {
			var self = this;

			// Try show settings modal on settings page.
			if (wcsdm_params.show_settings) {
				setTimeout(function() {
					var isMethodAdded = false;
					var methods = $(document).find(".wc-shipping-zone-method-type");
					for (var i = 0; i < methods.length; i++) {
						var method = methods[i];
						if ($(method).text() == wcsdm_params.method_title) {
							$(method)
								.closest("tr")
								.find(".row-actions .wc-shipping-zone-method-settings")
								.trigger("click");
							isMethodAdded = true;
							return;
						}
					}
					// Show Add shipping method modal if the shipping is not added.
					if (!isMethodAdded) {
						$(".wc-shipping-zone-add-method").trigger("click");
						$("select[name='add_method_id']")
							.val(wcsdm_params.method_id)
							.trigger("change");
					}
				}, 200);
			}
			// Handle setting link clicked.
			$(document).on("click", ".wc-shipping-zone-method-settings", function() {
				if (
					$(this)
						.closest("tr")
						.find(".wc-shipping-zone-method-type")
						.text() === wcsdm_params.method_title
				) {
					self._initGoogleMaps();
					$("#woocommerce_wcsdm_gmaps_api_units").trigger("change");
				}
			});
			// Handle setting distance units changed.
			$(document).on(
				"change",
				"#woocommerce_wcsdm_gmaps_api_units",
				function() {
					$(".input-group-distance")
						.removeClass("metric imperial")
						.addClass($(this).val());
					$("#per_distance_unit_selected").text(
						$(this)
							.find("option:selected")
							.text()
					);
					$(".input-group-price").removeClass("metric imperial");
					if ($("#woocommerce_wcsdm_charge_per_distance_unit").is(":checked")) {
						$(".input-group-price").addClass($(this).val());
					}
				}
			);
			// Handle setting charge_per_distance_unit changed.
			$(document).on(
				"change",
				"#woocommerce_wcsdm_charge_per_distance_unit",
				function() {
					$(".input-group-price").removeClass("metric imperial");
					if ($(this).is(":checked")) {
						$(".input-group-price").addClass(
							$("#woocommerce_wcsdm_gmaps_api_units").val()
						);
					}
				}
			);
			// Handle select rate row.
			$(document).on(
				"click",
				"#rates-list-table tbody .select-item",
				self._selectRateRows
			);
			// Handle toggle rate row.
			$(document).on(
				"click",
				"#rates-list-table thead .select-item",
				self._toggleRateRows
			);
			// Handle add rate rows.
			$(document).on("click", "#rates-list-table a.add", self._addRateRows);
			// Handle remove rate rows.
			$(document).on(
				"click",
				"#rates-list-table a.remove_rows",
				self._removeRateRows
			);
		},
		_initGoogleMaps: function(e) {
			var self = this;
			$("#" + self._inputLatId).closest("tr").hide();
			$("#" + self._inputLngId).closest("tr").hide();
			try {
				if (
					typeof google === "undefined" ||
					typeof google.maps === "undefined"
				) {
					throw "google is not defined";
				}
				self._buildGoogleMaps();
			} catch (error) {
				$.getScript(
					"https://maps.googleapis.com/maps/api/js?key=" +
						self._decode($("#map-secret-key").val()) +
						"&libraries=geometry,places&language=" +
						wcsdm_params.language,
					function() {
						self._buildGoogleMaps();
					}
				);
			}
		},
		_buildGoogleMaps: function() {
			var self = this;
			var defaultLat = -6.175392;
			var defaultLng = 106.827153;
			var curLat = $("#" + self._inputLatId).val();
			var curLng = $("#" + self._inputLngId).val();
			curLat = curLat.length ? parseFloat(curLat) : defaultLat;
			curLng = curLng.length ? parseFloat(curLng) : defaultLng;
			var curLatLng = {
				lat: curLat,
				lng: curLng
			};
			var tmplMapCanvas = wp.template(self._mapCanvasId);
			var tmplMapSearch = wp.template(self._mapSearchId);
			if (!$("#" + self._mapCanvasId).length) {
				$("#" + self._mapWrapperId).append(
					tmplMapCanvas({
						map_canvas_id: self._mapCanvasId
					})
				);
			}
			var markers = [];
			var map = new google.maps.Map(
				document.getElementById(self._mapCanvasId),
				{
					center: curLatLng,
					zoom: self._zoomLevel,
					mapTypeId: "roadmap"
				}
			);
			var marker = new google.maps.Marker({
				map: map,
				position: curLatLng,
				draggable: true,
				icon: wcsdm_params.marker
			});

			var infowindow = new google.maps.InfoWindow({
				maxWidth: 350
			});

			if (curLat == defaultLat && curLng == defaultLng) {
				infowindow.setContent(wcsdm_params.txt.drag_marker);
				infowindow.open(map, marker);
			} else {
				self._setLatLng(marker.position, marker, map, infowindow);
			}

			google.maps.event.addListener(marker, "dragstart", function(event) {
				infowindow.close();
			});

			google.maps.event.addListener(marker, "dragend", function(event) {
				self._setLatLng(event.latLng, marker, map, infowindow);
			});

			markers.push(marker);

			if (!$("#" + self._mapSearchId).length) {
				$("#" + self._mapWrapperId).append(
					tmplMapSearch({
						map_search_id: self._mapSearchId
					})
				);
			}
			// Create the search box and link it to the UI element.
			var inputAddress = document.getElementById(self._mapSearchId);
			var searchBox = new google.maps.places.SearchBox(inputAddress);
			map.controls[google.maps.ControlPosition.TOP_LEFT].push(inputAddress);
			// Bias the SearchBox results towards current map's viewport.
			map.addListener("bounds_changed", function() {
				searchBox.setBounds(map.getBounds());
			});
			// Listen for the event fired when the user selects a prediction and retrieve more details for that place.
			searchBox.addListener("places_changed", function() {
				var places = searchBox.getPlaces();
				if (places.length === 0) {
					return;
				}
				// Clear out the old markers.
				markers.forEach(function(marker) {
					marker.setMap(null);
				});
				markers = [];
				// For each place, get the icon, name and location.
				var bounds = new google.maps.LatLngBounds();
				places.forEach(function(place) {
					if (!place.geometry) {
						console.log("Returned place contains no geometry");
						return;
					}
					marker = new google.maps.Marker({
						map: map,
						position: place.geometry.location,
						draggable: true,
						icon: wcsdm_params.marker
					});
					self._setLatLng(place.geometry.location, marker, map, infowindow);
					google.maps.event.addListener(marker, "dragstart", function(event) {
						infowindow.close();
					});
					google.maps.event.addListener(marker, "dragend", function(event) {
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
				map.setZoom(self._zoomLevel);
				map.fitBounds(bounds);
			});

			setInterval(function() {
				if ($(".gm-err-content").length) {
					$("#" + self._mapCanvasId)
						.closest("tr")
						.hide();
					$("#" + self._inputLatId)
						.closest("tr")
						.show();
					$("#" + self._inputLngId)
						.closest("tr")
						.show();
					google = undefined;
				}
			}, 1000);
		},
		_setLatLng: function(location, marker, map, infowindow) {
			var self = this;
			var geocoder = new google.maps.Geocoder();
			geocoder.geocode(
				{
					latLng: location
				},
				function(results, status) {
					if (status == google.maps.GeocoderStatus.OK) {
						if (results[0]) {
							infowindow.setContent(results[0].formatted_address);
							infowindow.open(map, marker);
							$("#" + self._inputLatId).val(location.lat());
							$("#" + self._inputLngId).val(location.lng());
						} else {
							$("#" + self._inputLatId).val("");
							$("#" + self._inputLngId).val("");
						}
					}
				}
			);
			map.setCenter(location);
		},
		_selectRateRows: function(e) {
			var elem = $(e.currentTarget);
			var checkboxes_all = elem.closest("tbody").find("input[type=checkbox]");
			var checkboxes_checked = elem
				.closest("tbody")
				.find("input[type=checkbox]:checked");
			if (
				checkboxes_checked.length &&
				checkboxes_checked.length === checkboxes_all.length
			) {
				elem
					.closest("table")
					.find("thead input[type=checkbox]")
					.prop("checked", true);
			} else {
				elem
					.closest("table")
					.find("thead input[type=checkbox]")
					.prop("checked", false);
			}
			if (checkboxes_checked.length) {
				elem
					.closest("table")
					.find(".button.remove_rows")
					.show();
				elem
					.closest("table")
					.find(".button.add")
					.hide();
			} else {
				elem
					.closest("table")
					.find(".button.remove_rows")
					.hide();
				elem
					.closest("table")
					.find(".button.add")
					.show();
			}
			checkboxes_all.each(function(index, checkbox) {
				if ($(checkbox).is(":checked")) {
					$(checkbox)
						.closest("tr")
						.addClass("selected");
				} else {
					$(checkbox)
						.closest("tr")
						.removeClass("selected");
				}
			});
		},
		_toggleRateRows: function(e) {
			var elem = $(e.currentTarget);
			if (elem.is(":checked")) {
				elem
					.closest("table")
					.find("tr")
					.addClass("selected")
					.find("input[type=checkbox]")
					.prop("checked", true);
				if (elem.closest("table").find("tbody input[type=checkbox]").length) {
					elem
						.closest("table")
						.find(".button.remove_rows")
						.show();
					elem
						.closest("table")
						.find(".button.add")
						.hide();
				}
			} else {
				elem
					.closest("table")
					.find("tr")
					.removeClass("selected")
					.find("input[type=checkbox]")
					.prop("checked", false);
				if (elem.closest("table").find("tbody input[type=checkbox]").length) {
					elem
						.closest("table")
						.find(".button.remove_rows")
						.hide();
					elem
						.closest("table")
						.find(".button.add")
						.show();
				}
			}
		},
		_addRateRows: function(e) {
			e.preventDefault();
			var template = wp.template("rates-list-input-table-row");
			// Set the template data vars.
			var tmplData = {
				field_key: $(e.currentTarget).data("key"),
				distance_unit: $("#woocommerce_wcsdm_gmaps_api_units").val(),
				charge_per_distance_unit: $(
					"#woocommerce_wcsdm_charge_per_distance_unit"
				).is(":checked")
					? $("#woocommerce_wcsdm_gmaps_api_units").val()
					: ""
			};
			$("#rates-list-table tbody").append(template(tmplData));
		},
		_removeRateRows: function(e) {
			e.preventDefault();
			var elem = $(e.currentTarget);
			elem.hide();
			elem
				.closest("table")
				.find(".button.add")
				.show();
			elem
				.closest("table")
				.find("thead input[type=checkbox]")
				.prop("checked", false);
			elem
				.closest("table")
				.find("tbody input[type=checkbox]")
				.each(function(index, checkbox) {
					if ($(checkbox).is(":checked")) {
						$(checkbox)
							.closest("tr")
							.remove();
					}
				});
		},
		_encode: function(e) {
			var self = this;
			var t = "";
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
		_decode: function(e) {
			var self = this;
			var t = "";
			var n, r, i;
			var s, o, u, a;
			var f = 0;
			e = e.replace(/[^A-Za-z0-9+/=]/g, "");
			while (f < e.length) {
				s = this._keyStr.indexOf(e.charAt(f++));
				o = this._keyStr.indexOf(e.charAt(f++));
				u = this._keyStr.indexOf(e.charAt(f++));
				a = this._keyStr.indexOf(e.charAt(f++));
				n = (s << 2) | (o >> 4);
				r = ((o & 15) << 4) | (u >> 2);
				i = ((u & 3) << 6) | a;
				t = t + String.fromCharCode(n);
				if (u != 64) {
					t = t + String.fromCharCode(r);
				}
				if (a != 64) {
					t = t + String.fromCharCode(i);
				}
			}
			t = self._utf8_decode(t);
			return t;
		},
		_utf8_encode: function(e) {
			e = e.replace(/rn/g, "n");
			var t = "";
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
		_utf8_decode: function(e) {
			var t = "";
			var n = 0;
			var r = 0;
			var c1 = 0;
			var c2 = 0;
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
	$(document).ready(function() {
		wcsdmSetting.init();
	});
})(jQuery);
