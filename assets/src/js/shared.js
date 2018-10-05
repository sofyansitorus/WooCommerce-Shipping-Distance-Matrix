// Returns a function, that, as long as it continues to be invoked, will not
// be triggered. The function will be called after it stops being called for
// N milliseconds. If `immediate` is passed, trigger the function on the
// leading edge, instead of the trailing.
function debounce(func, wait, immediate) {
    var timeout;
    return function () {
        var context = this, args = arguments;
        var later = function () {
            timeout = null;
            if (!immediate) {
                func.apply(context, args);
            }
        };
        var callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) {
            func.apply(context, args);
        }
    };
}

// jQuery Function to get element attributes
$.fn.attrs = function (attrs) {
    var t = $(this);
    var results = {};
    if (attrs) {
        // Set attributes
        t.each(function (i, e) {
            var j = $(e);
            for (var attr in attrs) {
                j.attr(attr, attrs[attr]);
            }
        });
        result = t;
    } else {
        // Get attributes
        var a = {},
            r = t.get(0);
        if (r) {
            r = r.attributes;
            for (var i in r) {
                var p = r[i];
                if (typeof p.nodeValue !== 'undefined') a[p.nodeName] = p.nodeValue;
            }
        }
        results = a;
    }

    if (!_.keys(results).length) {
        return results;
    }

    var data = {};
    _.keys(results).forEach(function (key) {
        if (key.indexOf('data-') !== 0) {
            data[key] = results[key];
        }
    });

    return data;
};

function showError(args) {
    var params = _.extend({
        selector: '',
        method: 'before',
        title: wcsdm_params.i18n.errors.error_title,
        content: ''
    }, args);

    if (params.selector.length) {
        var $selector = $(params.selector);
        if (!$selector.length) {
            return;
        }

        switch (params.method) {
            case 'before':
                $selector.before(wp.template('wcsdm-error')(params));
                break;

            case 'after':
                $selector.after(wp.template('wcsdm-error')(params));
                break;

            default:
                $selector.append(wp.template('wcsdm-error')(params));
                break;
        }

        $('.wc-modal-shipping-method-settings').animate({
            scrollTop: $('#wcsdm-error').position().top
        }, 500);

        return $selector;
    }
}

function hideError() {
    $('#wcsdm-error').remove();
    $('.wc-modal-shipping-method-settings').children().removeClass('error');
}
