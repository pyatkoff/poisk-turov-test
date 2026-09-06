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
    var comparable = next.replace(/\s+/g, ' ');
    if (node && next && text(node) !== comparable) node.textContent = next;
  }

  function setAttribute(node, name, value) {
    if (node && node.getAttribute(name) !== value) node.setAttribute(name, value);
  }

  function removeAttribute(node, name) {
    if (node && node.hasAttribute(name)) node.removeAttribute(name);
  }

  function setHidden(node, value) {
    if (node && node.hidden !== value) node.hidden = value;
  }

  function setData(node, name, value) {
    if (node && node.dataset[name] !== value) node.dataset[name] = value;
  }

  function removeData(node, name) {
    if (node && node.dataset[name] !== undefined) delete node.dataset[name];
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
    var ariaLabel = amount + ', ' + scope.toLowerCase();
    selected.querySelectorAll('.search3-booking-summary__total').forEach(function (box) {
      setText(box.querySelector(':scope > span'), scope);
      setText(box.querySelector(':scope > strong'), amount);
      setAttribute(box, 'aria-label', ariaLabel);
    });
    var mobile = document.querySelector('.search3-selected-mobile-bar');
    if (mobile) {
      setText(mobile.querySelector('.search3-selected-mobile-bar__price small'), scope);
      var mobileAmount = mobile.querySelector('[data-s3-selected-price]');
      setText(mobileAmount, amount);
      setAttribute(mobileAmount, 'aria-label', ariaLabel);
    }
  }

  /* @include behavior/selected/flight-fallback.js */

  /* @include behavior/selected/flight-disclosure.js */

  function clearFallback() {
    selected.classList.remove('search3-flight-fallback');
    removeData(selected, 'search3FlightFallback');
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
      setData(selected, 'search3FlightFallback', '1');
      ensureReviewAction(flights);
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
