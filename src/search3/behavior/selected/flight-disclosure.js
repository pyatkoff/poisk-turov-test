  function disclosureButton(flights) {
    var action = flights.querySelector('.search3-flight-show-all');
    if (action) return action;
    action = document.createElement('button');
    action.type = 'button';
    action.className = 'search3-flight-show-all';
    var variants = flights.querySelector('.flight-variants');
    if (variants && variants.nextSibling) flights.insertBefore(action, variants.nextSibling);
    else flights.appendChild(action);
    return action;
  }

  function syncFlightDisclosure(flights) {
    flights = flights || selected.querySelector('.tour-flights');
    var variantsBox = flights && flights.querySelector('.flight-variants');
    var variants = variantsBox ? Array.from(variantsBox.querySelectorAll(':scope > .flight-variant')) : [];
    if (!variantsBox || variants.length <= INITIAL_FLIGHT_LIMIT) {
      var existing = flights && flights.querySelector('.search3-flight-show-all');
      if (variantsBox) {
        removeData(variantsBox, 'search3FlightDisclosure');
        variants.forEach(function (variant) { if (variant.hidden) variant.hidden = false; });
      }
      if (existing) existing.remove();
      return;
    }

    setData(variantsBox, 'search3FlightDisclosure', '1');
    var expanded = variantsBox.dataset.search3FlightsExpanded === '1';
    var selectedIndex = variants.findIndex(function (variant) {
      return variant.classList.contains('is-selected') || !!variant.querySelector('input[name="v2flight"]:checked');
    });
    var visible = new Set(visibleFlightIndexes(variants.length, selectedIndex, expanded, INITIAL_FLIGHT_LIMIT));
    variants.forEach(function (variant, index) {
      var hide = !visible.has(index);
      if (variant.hidden !== hide) variant.hidden = hide;
    });

    var action = disclosureButton(flights);
    setHidden(action, selected.classList.contains('search3-final-review'));
    setAttribute(action, 'aria-expanded', expanded ? 'true' : 'false');
    if (variantsBox.id) setAttribute(action, 'aria-controls', variantsBox.id);
    else removeAttribute(action, 'aria-controls');
    setText(action, expanded ? 'Скрыть дополнительные варианты' : 'Показать все ' + variants.length + ' вариантов');
  }

  function toggleFlightDisclosure(button) {
    var flights = button && button.closest('.tour-flights');
    var variants = flights && flights.querySelector('.flight-variants');
    if (!variants) return;
    if (variants.dataset.search3FlightsExpanded === '1') delete variants.dataset.search3FlightsExpanded;
    else variants.dataset.search3FlightsExpanded = '1';
    syncFlightDisclosure(flights);
    try { button.focus({ preventScroll: true }); } catch (_error) { button.focus(); }
  }
