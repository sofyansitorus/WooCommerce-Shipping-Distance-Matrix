/**
 * Backend Scripts
 */

function toggleBottons(args) {
    'use strict';

    var data = getButton(args);
    $('#wcsdm-buttons').remove();
    $('#btn-ok').hide().after(wp.template('wcsdm-buttons')(data));
}

function getButton(args) {
    'use strict';

    var leftButtonDefaultId = 'add-rate';
    var leftButtonDefaultIcon = 'plus';
    var leftButtonDefaultLabel = 'add';

    var leftButtonDefault = {
        id: leftButtonDefaultId,
        icon: leftButtonDefaultIcon,
        label: leftButtonDefaultLabel
    };

    var rightButtonDefaultIcon = 'yes';
    var rightButtonDefaultId = 'save-settings';
    var rightButtonDefaultLabel = 'save';

    var rightButtonDefault = {
        id: rightButtonDefaultId,
        icon: rightButtonDefaultIcon,
        label: rightButtonDefaultLabel
    };

    var selected = {};
    var leftButton;
    var rightButton;

    if (_.has(args, 'left')) {
        leftButton = _.defaults(args.left, leftButtonDefault);

        if (_.has(wcsdm_backend.i18n, leftButton.label)) {
            leftButton.label = wcsdm_backend.i18n[leftButton.label];
        }

        selected.btn_left = leftButton;
    }

    if (_.has(args, 'right')) {
        rightButton = _.defaults(args.right, rightButtonDefault);

        if (_.has(wcsdm_backend.i18n, rightButton.label)) {
            rightButton.label = wcsdm_backend.i18n[rightButton.label];
        }

        selected.btn_right = rightButton;
    }

    if (_.isEmpty(selected)) {
        leftButton = _.defaults({}, leftButtonDefault);

        if (_.has(wcsdm_backend.i18n, leftButton.label)) {
            leftButton.label = wcsdm_backend.i18n[leftButton.label];
        }

        selected.btn_left = leftButton;

        rightButton = _.defaults({}, rightButtonDefault);

        if (_.has(wcsdm_backend.i18n, rightButton.label)) {
            rightButton.label = wcsdm_backend.i18n[rightButton.label];
        }

        selected.btn_right = rightButton;
    }

    return selected;
}

$(document).ready(function () {
    // Try show settings modal on settings page.
    if (wcsdm_backend.showSettings) {
        setTimeout(function () {
            var isMethodAdded = false;
            var methods = $(document).find('.wc-shipping-zone-method-type');
            for (var i = 0; i < methods.length; i++) {
                var method = methods[i];
                if ($(method).text() === wcsdm_backend.methodTitle) {
                    $(method).closest('tr').find('.row-actions .wc-shipping-zone-method-settings').trigger('click');
                    isMethodAdded = true;
                    return;
                }
            }
            // Show Add shipping method modal if the shipping is not added.
            if (!isMethodAdded) {
                $('.wc-shipping-zone-add-method').trigger('click');
                $('select[name="add_method_id"]').val(wcsdm_backend.methodId).trigger('change');
            }
        }, 400);
    }

    $(document).on('click', '.wc-shipping-zone-method-settings', function () {
        var params = _.mapObject(wcsdm_backend, function (val, key) {
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