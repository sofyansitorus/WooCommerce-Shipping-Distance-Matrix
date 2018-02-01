(function($) {
    "use strict";
    var wcsdmSetting = {
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
                    ).is(":checked") ?
                    $("#woocommerce_wcsdm_gmaps_api_units").val() : ""
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
        }
    };
    $(document).ready(function() {
        wcsdmSetting.init();
    });
})(jQuery);