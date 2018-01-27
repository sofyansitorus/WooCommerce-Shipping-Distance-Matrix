(function($) {
	"use strict";

	var wcsdmSetting = {
		inputApiKeyId: "woocommerce_wcsdm_gmaps_api_key",
		inputLatId: "woocommerce_wcsdm_origin_lat",
		inputLngId: "woocommerce_wcsdm_origin_lng",
		mapWrapperId: "wcsdm-map-wrapper",
		mapSearchId: "wcsdm-map-search",
		mapCanvasId: "wcsdm-map-canvas",
		zoomLevel: 16,

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
					$("#" + self.mapCanvasId).remove();
					$("#" + self.mapSearchId).remove();
					$("#" + self.inputApiKeyId).trigger("change");
					$("#woocommerce_wcsdm_gmaps_api_units").trigger("change");
				}
			});

			// Handle setting API key changed.
			$(document).on("change", "#" + self.inputApiKeyId, function() {
				self._loadScript($(this).val());
			});

			// Handle setting API key changed.
			$(document).on(
				"change",
				"#woocommerce_wcsdm_gmaps_api_units",
				function() {
					$(".input-group-distance")
						.removeClass("metric imperial")
						.addClass($(this).val());
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

		_loadScript: function(apiKey) {
			var self = this;

			if (typeof google !== "undefined" && typeof google.maps !== "undefined") {
				window.google.maps = {};
			}

			try {
				var scriptSrc =
					"https://maps.googleapis.com/maps/api/js?key=" +
					apiKey +
					"&libraries=geometry,places";
				$.getScript(scriptSrc, function() {
					self._buildMap();
				});
			} catch (error) {
				console.log("error_buildMap", error);
			}
		},

		_buildMap: function() {
			var self = this;

			var curLat = $("#" + self.inputLatId).val();
			var curLng = $("#" + self.inputLngId).val();

			curLat = curLat.length ? parseFloat(curLat) : -6.175392;
			curLng = curLng.length ? parseFloat(curLng) : 106.827153;

			var curLatLng = {
				lat: curLat,
				lng: curLng
			};

			var tmplMapCanvas = wp.template(self.mapCanvasId);
			var tmplMapSearch = wp.template(self.mapSearchId);

			if (!$("#" + self.mapCanvasId).length) {
				$("#" + self.mapWrapperId).append(
					tmplMapCanvas({
						map_canvas_id: self.mapCanvasId
					})
				);
			}

			var markers = [];

			var map = new google.maps.Map(document.getElementById(self.mapCanvasId), {
				center: curLatLng,
				zoom: self.zoomLevel,
				mapTypeId: "roadmap"
			});

			var marker = new google.maps.Marker({
				map: map,
				position: curLatLng,
				draggable: true,
				icon: wcsdm_params.marker
			});

			var infowindow = new google.maps.InfoWindow({
				maxWidth: 350,
				content: wcsdm_params.txt.drag_marker
			});

			infowindow.open(map, marker);

			google.maps.event.addListener(marker, "dragstart", function(event) {
				infowindow.close();
			});

			google.maps.event.addListener(marker, "dragend", function(event) {
				self._setLatLng(event.latLng, marker, map, infowindow);
			});

			markers.push(marker);

			if (!$("#" + self.mapSearchId).length) {
				$("#" + self.mapWrapperId).append(
					tmplMapSearch({
						map_search_id: self.mapSearchId
					})
				);
			}

			// Create the search box and link it to the UI element.
			var inputAddress = document.getElementById(self.mapSearchId);
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
				map.setZoom(self.zoomLevel);
				map.fitBounds(bounds);
			});
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
							$("#" + self.inputLatId).val(location.lat());
							$("#" + self.inputLngId).val(location.lng());
						} else {
							$("#" + self.inputLatId).val("");
							$("#" + self.inputLngId).val("");
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
					.find("tr")
					.removeClass("selected")
					.find("input[type=checkbox]")
					.prop("checked", false);
				elem
					.closest("table")
					.find(".button.remove_rows")
					.hide();
				elem
					.closest("table")
					.find(".button.add")
					.show();
			}
		},

		_addRateRows: function(e) {
			e.preventDefault();

			var elem = $(e.currentTarget);

			var template = wp.template("rates-list-input-table-row"); // uses script tag ID minus "tmpl-"

			// Let's fake some data (maybe this is data from an API request?)
			var tmplData = {
				field_key: elem.data("key"),
				distance_unit: $("#woocommerce_wcsdm_gmaps_api_units").val()
			};

			var row = template(tmplData);

			$("#rates-list-table tbody").append(row);
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

			var checkboxes = elem.closest("table").find("tbody input[type=checkbox]");

			checkboxes.each(function(index, checkbox) {
				if ($(checkbox).is(":checked")) {
					$(checkbox)
						.closest("tr")
						.remove();
				}
			});
		}
	};

	$(document).ready(function() {
		wcsdmSetting.init();
	});
})(jQuery);
