/**
 * Backend Scripts
 */

function toggleBottons(args) {
    "use strict";

    var data = getButton(args);
    $('#wcsdm-buttons').remove();
    $('#btn-ok').hide().after(wp.template('wcsdm-buttons')(data));
}

function getButton(args) {
    "use strict";

    var leftButtonDefaultId = 'add-rate';
    var leftButtonDefaultIcon = 'plus';
    var leftButtonDefaultLabel = 'add';

    var leftButtonDefault = {
        id: leftButtonDefaultId,
        icon: leftButtonDefaultIcon,
        label: leftButtonDefaultLabel,
    };

    var rightButtonDefaultIcon = 'yes';
    var rightButtonDefaultId = 'save-settings';
    var rightButtonDefaultLabel = 'save';

    var rightButtonDefault = {
        id: rightButtonDefaultId,
        icon: rightButtonDefaultIcon,
        label: rightButtonDefaultLabel,
    };

    var selected = {};

    if (_.has(args, 'left')) {
        var leftButton = _.defaults(args.left, leftButtonDefault);

        if (_.has(wcsdm_params.i18n, leftButton.label)) {
            leftButton.label = wcsdm_params.i18n[leftButton.label];
        }

        selected.btn_left = leftButton;
    }

    if (_.has(args, 'right')) {
        var rightButton = _.defaults(args.right, rightButtonDefault);

        if (_.has(wcsdm_params.i18n, rightButton.label)) {
            rightButton.label = wcsdm_params.i18n[rightButton.label];
        }

        selected.btn_right = rightButton;
    }

    if (_.isEmpty(selected)) {
        var leftButton = _.defaults({}, leftButtonDefault);

        if (_.has(wcsdm_params.i18n, leftButton.label)) {
            leftButton.label = wcsdm_params.i18n[leftButton.label];
        }

        selected.btn_left = leftButton;

        var rightButton = _.defaults({}, rightButtonDefault);

        if (_.has(wcsdm_params.i18n, rightButton.label)) {
            rightButton.label = wcsdm_params.i18n[rightButton.label];
        }

        selected.btn_right = rightButton;
    }

    return selected;
}

$(document).ready(function () {
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
        }, 400);
    }

    $(document).on('click', '.wc-shipping-zone-method-settings', function () {
        var params = _.mapObject(wcsdm_params, function (val, key) {
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