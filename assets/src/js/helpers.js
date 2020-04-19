// Returns a function, that, as long as it continues to be invoked, will not
// be triggered. The function will be called after it stops being called for
// N milliseconds.
function debounce(func, wait) {
  var timeout;
  return function () {
    var context = this;
    var args = arguments;
    var later = function () {
      timeout = null;
      func.apply(context, args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

function toggleBottons(args) {
  var data = getButton(args);
  $('#wcsdm-buttons').remove();
  $('#btn-ok').hide().after(wp.template('wcsdm-buttons')(data));
}

function getButton(args) {
  var buttonLabels = wcsdm_backend.i18n.buttons;

  var leftButtonDefaultId = 'add-rate';
  var leftButtonDefaultIcon = 'plus';
  var leftButtonDefaultLabel = 'Add New Rate';

  var leftButtonDefault = {
    id: leftButtonDefaultId,
    icon: leftButtonDefaultIcon,
    label: leftButtonDefaultLabel
  };

  var rightButtonDefaultIcon = 'yes';
  var rightButtonDefaultId = 'save-settings';
  var rightButtonDefaultLabel = 'Save Changes';

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

    if (_.has(buttonLabels, leftButton.label)) {
      leftButton.label = buttonLabels[leftButton.label];
    }

    selected.btn_left = leftButton;
  }

  if (_.has(args, 'right')) {
    rightButton = _.defaults(args.right, rightButtonDefault);

    if (_.has(buttonLabels, rightButton.label)) {
      rightButton.label = buttonLabels[rightButton.label];
    }

    selected.btn_right = rightButton;
  }

  if (_.isEmpty(selected)) {
    leftButton = _.defaults({}, leftButtonDefault);

    if (_.has(buttonLabels, leftButton.label)) {
      leftButton.label = buttonLabels[leftButton.label];
    }

    selected.btn_left = leftButton;

    rightButton = _.defaults({}, rightButtonDefault);

    if (_.has(buttonLabels, rightButton.label)) {
      rightButton.label = buttonLabels[rightButton.label];
    }

    selected.btn_right = rightButton;
  }

  return selected;
}
