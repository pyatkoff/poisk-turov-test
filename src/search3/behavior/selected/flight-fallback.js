  function noFlightState(flights) {
    flights = flights || selected.querySelector('.tour-flights');
    if (!flights || flights.querySelector('.flight-variant,.flight-error')) return false;
    return noFlightMessage(text(flights.querySelector('.selected-loading')));
  }

  function ensureEmptyFlightRecovery(flights) {
    flights = flights || selected.querySelector('.tour-flights');
    if (!noFlightState(flights)) return null;
    var message = flights.querySelector('.selected-loading');
    setText(message, 'Данные по рейсам пока не получены. Можно проверить ещё раз; если данные не появятся, менеджер уточнит перелёт по заявке.');
    var tourId = String(currentTour && currentTour.id || '');
    var retry = flights.querySelector('.load-flights');
    if (!retry && tourId) {
      retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'load-flights secondary';
      setData(retry, 'search3SelectedFlowOwned', '1');
      message.insertAdjacentElement('afterend', retry);
    }
    if (retry && tourId) {
      setAttribute(retry, 'data-tid', tourId);
      setText(retry, 'Проверить рейсы ещё раз');
    }
    return retry;
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
    } else if (!action.classList.contains('search3-flight-continue--fallback')) {
      action.classList.add('search3-flight-continue--fallback');
    }
    var button = action.querySelector('button');
    setText(button, selected.classList.contains('search3-final-review') ? 'Изменить рейс' : flowLabel());
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
