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
