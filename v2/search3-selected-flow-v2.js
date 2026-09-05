/* Search3 selected price, flight disclosure and CTA adapter. Preview presentation only. */
(function () {
  'use strict';

  var INITIAL_FLIGHT_LIMIT = 6;

  function number(value) {
    if (value && typeof value === 'object' && value.value !== undefined) value = value.value;
    var result = Number(value || 0);
    return Number.isFinite(result) ? result : 0;
  }

  function normalizedTotal(detail, fallbackTour) {
    var value = detail || {};
    var tour = value.tour || fallbackTour || {};
    if (value.pricePending) return number(value.basePrice) || number(tour.price);
    return number(value.price) || number(value.basePrice) || number(tour.price);
  }

  function visibleFlightIndexes(count, selectedIndex, expanded, limit) {
    var total = Math.max(0, Number(count) || 0);
    var initial = Math.max(1, Number(limit) || INITIAL_FLIGHT_LIMIT);
    var selected = Number(selectedIndex);
    var indexes = [];
    for (var index = 0; index < total; index += 1) {
      if (expanded || index < initial || index === selected) indexes.push(index);
    }
    return indexes;
  }

  function noFlightMessage(value) {
    var message = String(value || '').replace(/\s+/g, ' ').trim();
    return message.indexOf('Для тура варианты рейсов не найдены.') === 0
      || message.indexOf('Данные по рейсам пока не получены.') === 0;
  }

  function flowLabel(stage) {
    if (stage === 'review') return 'Перейти к заявке';
    if (stage === 'submit') return 'Отправить заявку';
    return 'Далее: итог тура';
  }

  var helpers = Object.freeze({
    version: 3,
    initialFlightLimit: INITIAL_FLIGHT_LIMIT,
    normalizedTotal: normalizedTotal,
    visibleFlightIndexes: visibleFlightIndexes,
    noFlightMessage: noFlightMessage,
    flowLabel: flowLabel
  });
  window.Search3SelectedFlowV2Helpers = helpers;

  if (window.Search3SelectedFlowV2) return;
  var body = document.body;
  var selected = document.getElementById('selectedTour');
  if (!body || !body.classList.contains('search3-candidate') || !selected) return;

  var queued = false;
  var currentTour = null;
  var currentTotal = 0;

  function text(node) {
    return String(node && node.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function setText(node, value) {
    var next = String(value || '').trim();
    if (node && next && text(node) !== next) node.textContent = next;
  }

  function money(value) {
    var amount = number(value);
    return amount > 0 ? new Intl.NumberFormat('ru-RU').format(amount) + ' ₽' : '';
  }

  function priceScope() {
    return text(selected.querySelector('.selected-price > small')) || 'Стоимость тура';
  }

  function syncDisplayedPrice() {
    var amount = money(currentTotal);
    if (!amount) return;
    var scope = priceScope();
    selected.querySelectorAll('.search3-booking-summary__total,.search3-tour-detail-rail__price').forEach(function (box) {
      setText(box.querySelector(':scope > span'), scope);
      setText(box.querySelector(':scope > strong'), amount);
      box.setAttribute('aria-label', amount + ', ' + scope.toLowerCase());
    });
    var mobile = document.querySelector('.search3-selected-mobile-bar');
    if (mobile) {
      setText(mobile.querySelector('.search3-selected-mobile-bar__price small'), scope);
      var mobileAmount = mobile.querySelector('[data-s3-selected-price]');
      setText(mobileAmount, amount);
      if (mobileAmount) mobileAmount.setAttribute('aria-label', amount + ', ' + scope.toLowerCase());
    }
  }

  function noFlightState(flights) {
    flights = flights || selected.querySelector('.tour-flights');
    if (!flights || flights.querySelector('.flight-variant,.flight-error')) return false;
    return noFlightMessage(text(flights.querySelector('.selected-loading')));
  }

  function ensureReviewAction() {
    var flights = selected.querySelector('.tour-flights');
    if (!flights) return null;
    var action = flights.querySelector('.search3-flight-continue');
    if (!action) {
      action = document.createElement('div');
      action.className = 'search3-flight-continue search3-flight-continue--fallback';
      action.dataset.search3SelectedFlowOwned = '1';
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
    if (!noFlightState() || selected.classList.contains('search3-final-review')) return false;
    var review = ensureReviewAction();
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
    button.dataset.search3SelectedFlowBound = '1';
    button.addEventListener('click', function (event) {
      if (button.dataset.search3SelectedFlowAction !== '1') return;
      activateReview(event);
    }, true);
  }

  function ensureRailAction() {
    var rail = selected.querySelector('.search3-tour-detail-rail');
    if (!rail) return;
    var action = rail.querySelector('.search3-flight-fallback-rail-action');
    if (!action) {
      action = document.createElement('button');
      action.type = 'button';
      action.className = 'search3-flight-fallback-rail-action';
      action.dataset.search3SelectedFlowOwned = '1';
      rail.appendChild(action);
    }
    setText(action, flowLabel('flight'));
    action.dataset.search3SelectedFlowAction = '1';
    action.setAttribute('aria-label', 'Перейти к итогу тура без выбранного рейса');
    bindAction(action);
  }

  function syncMobileAction(noFlight) {
    var button = document.querySelector('.search3-selected-mobile-bar [data-s3-selected-lead]');
    if (!button) return;
    setText(button, flowLabel('flight'));
    bindAction(button);
    if (noFlight) {
      button.dataset.search3SelectedFlowAction = '1';
      button.setAttribute('aria-label', 'Перейти к итогу тура без выбранного рейса');
    } else {
      delete button.dataset.search3SelectedFlowAction;
      button.removeAttribute('aria-label');
    }
  }

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
    var existing = flights && flights.querySelector('.search3-flight-show-all');
    if (!variantsBox || variants.length <= INITIAL_FLIGHT_LIMIT) {
      if (variantsBox) {
        variantsBox.removeAttribute('data-search3-flight-disclosure');
        variants.forEach(function (variant) { if (variant.hidden) variant.hidden = false; });
      }
      if (existing) existing.remove();
      return;
    }

    variantsBox.dataset.search3FlightDisclosure = '1';
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
    action.hidden = selected.classList.contains('search3-final-review');
    action.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    if (variantsBox.id) action.setAttribute('aria-controls', variantsBox.id);
    else action.removeAttribute('aria-controls');
    setText(action, expanded ? 'Скрыть дополнительные варианты' : 'Показать все ' + variants.length + ' вариантов');
  }

  function toggleFlightDisclosure(button) {
    var flights = button && button.closest('.tour-flights');
    var variants = flights && flights.querySelector('.flight-variants');
    if (!variants) return;
    if (variants.dataset.search3FlightsExpanded === '1') delete variants.dataset.search3FlightsExpanded;
    else variants.dataset.search3FlightsExpanded = '1';
    syncFlightDisclosure();
    try { button.focus({ preventScroll: true }); } catch (_error) { button.focus(); }
  }

  function clearFallback() {
    selected.classList.remove('search3-flight-fallback');
    delete selected.dataset.search3FlightFallback;
    selected.querySelectorAll('[data-search3-selected-flow-owned="1"]').forEach(function (node) {
      node.remove();
    });
    var retained = selected.querySelector('.search3-flight-continue--fallback');
    if (retained) retained.classList.remove('search3-flight-continue--fallback');
  }

  function syncLeadCopy() {
    var box = selected.querySelector('.lead-selection-summary');
    if (!box) return;
    var price = box.querySelector(':scope > span:first-child b');
    setText(price, money(currentTotal));
    var choice = selected.querySelector('.flight-variant.is-selected .flight-choice > span');
    if (choice) {
      var pieces = Array.from(choice.childNodes).map(function (node) { return text(node); }).filter(Boolean);
      setText(box.querySelector(':scope > span:nth-child(2) b'), pieces.join(' · '));
    }
  }

  function sync() {
    var flights = selected.querySelector('.tour-flights');
    syncDisplayedPrice();
    syncLeadCopy();
    syncFlightDisclosure(flights);
    var noFlight = noFlightState(flights);
    if (noFlight) {
      selected.classList.add('search3-flight-fallback');
      selected.dataset.search3FlightFallback = '1';
      ensureReviewAction();
      ensureRailAction();
    } else {
      clearFallback();
    }
    syncMobileAction(noFlight);
  }

  function schedule() {
    if (queued) return;
    queued = true;
    window.requestAnimationFrame(function () {
      queued = false;
      sync();
    });
  }

  document.addEventListener('click', function (event) {
    var button = event.target && event.target.closest && event.target.closest('.search3-flight-show-all');
    if (!button || !selected.contains(button)) return;
    event.preventDefault();
    toggleFlightDisclosure(button);
  });

  window.addEventListener('v2:tour-selected', function (event) {
    currentTour = event && event.detail && event.detail.tour || null;
    currentTotal = normalizedTotal({ tour: currentTour }, currentTour);
    schedule();
  });
  window.addEventListener('v2:tour-price-updated', function (event) {
    currentTotal = normalizedTotal(event && event.detail, currentTour);
    schedule();
  });
  ['v2:flight-selected', 'v2:booking-review', 'search3:lead-entry'].forEach(function (name) {
    window.addEventListener(name, schedule);
  });

  var observer = new MutationObserver(schedule);
  observer.observe(selected, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['hidden', 'class']
  });
  var mobileBar = document.querySelector('.search3-selected-mobile-bar');
  if (mobileBar) observer.observe(mobileBar, { childList: true, subtree: true, characterData: true });

  schedule();
  window.Search3SelectedFlowV2 = Object.freeze({
    version: 3,
    sync: sync,
    noFlightState: noFlightState,
    activateReview: activateReview,
    syncDisplayedPrice: syncDisplayedPrice,
    syncFlightDisclosure: syncFlightDisclosure,
    toggleFlightDisclosure: toggleFlightDisclosure,
    helpers: helpers
  });
})();
