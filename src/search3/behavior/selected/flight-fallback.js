  function noFlightState(flights) {
    flights = flights || selected.querySelector('.tour-flights');
    if (!flights || flights.querySelector('.flight-variant,.flight-error')) return false;
    return noFlightMessage(text(flights.querySelector('.selected-loading')));
  }

  function ensureReviewAction(flights) {
    flights = flights || selected.querySelector('.tour-flights');
    if (!flights) return null;
    var action = flights.querySelector('.search3-flight-continue');
    if (!action) {
      action = document.createElement('div');
      action.className = 'search3-flight-continue search3-flight-continue--fallback';
      setData(action, 'search3SelectedFlowOwned', '1');
      action.innerHTML = '<button type="button" class="primary">' + flowLabel('flight') + '</button>';
      flights.appendChild(action);
    } else {
      action.classList.add('search3-flight-continue--fallback');
    }
    var button = action.querySelector('button');
    setText(button, flowLabel('flight'));
    return button;
  }

  function activateReview(event) {
    var flights = selected.querySelector('.tour-flights');
    if (!noFlightState(flights) || selected.classList.contains('search3-final-review')) return false;
    var review = ensureReviewAction(flights);
    if (!review) return false;
    if (event) {
      event.preventDefault();
      event.stopPropagation();
      if (event.stopImmediatePropagation) event.stopImmediatePropagation();
    }
    review.click();
    return true;
  }

  function bindAction(button) {
    if (!button || button.dataset.search3SelectedFlowBound === '1') return;
    setData(button, 'search3SelectedFlowBound', '1');
    button.addEventListener('click', function (event) {
      if (button.dataset.search3SelectedFlowAction !== '1') return;
      activateReview(event);
    }, true);
  }

  function syncMobileAction(noFlight) {
    var button = document.querySelector('.search3-selected-mobile-bar [data-s3-selected-lead]');
    if (!button) return;
    setText(button, flowLabel('flight'));
    bindAction(button);
    if (noFlight) {
      setData(button, 'search3SelectedFlowAction', '1');
      setAttribute(button, 'aria-label', 'Перейти к итогу тура без выбранного рейса');
    } else {
      removeData(button, 'search3SelectedFlowAction');
      removeAttribute(button, 'aria-label');
    }
  }
