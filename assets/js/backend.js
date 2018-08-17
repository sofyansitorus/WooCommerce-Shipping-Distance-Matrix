;(function($) {
"use strict";

$(document).ready(function () {
	wcsdmMap.init(wcsdm_params);
	wcsdmTableRates.init(wcsdm_params);
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
		}, 200);
	}
});
}(jQuery));
