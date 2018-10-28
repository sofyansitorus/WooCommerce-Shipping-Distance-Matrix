/**
 * Backend Scripts
 */
$(document).ready(function () {
    // Try show settings modal on settings page.
    if (wcsdm_backend_params.showSettings) {
        setTimeout(function () {
            var isMethodAdded = false;
            var methods = $(document).find('.wc-shipping-zone-method-type');
            for (var i = 0; i < methods.length; i++) {
                var method = methods[i];
                if ($(method).text() === wcsdm_backend_params.methodTitle) {
                    $(method).closest('tr').find('.row-actions .wc-shipping-zone-method-settings').trigger('click');
                    isMethodAdded = true;
                    return;
                }
            }
            // Show Add shipping method modal if the shipping is not added.
            if (!isMethodAdded) {
                $('.wc-shipping-zone-add-method').trigger('click');
                $('select[name="add_method_id"]').val(wcsdm_backend_params.methodId).trigger('change');
            }
        }, 400);
    }

    $(document).on('click', '.wc-shipping-zone-method-settings', function () {
        var params = _.mapObject(wcsdm_backend_params, function (val, key) {
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

        wcsdmTableRates.init(params);
        wcsdmMapPicker.init(params);
    });
});