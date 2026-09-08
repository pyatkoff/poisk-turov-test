/* Search3 selected state, no-flight recovery and display-only price-label adapter. */
(function () {
  'use strict';

  if (window.Search3SelectedFlowV2) return;
  var body = document.body;
  var selected = document.getElementById('selectedTour');
  if (!body || !body.classList.contains('search3-candidate') || !selected) return;

  var queued = false;
  var currentTour = null;

  function text(node) {
    return String(node && node.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function setText(node, value) {
    var next = String(value || '').trim();
    if (node && next && text(node) !== next.replace(/\s+/g, ' ')) node.textContent = next;
  }

  function setAttribute(node, name, value) {
    if (node && node.getAttribute(name) !== value) node.setAttribute(name, value);
  }

  function setData(node, name, value) {
    if (node && node.dataset[name] !== value) node.dataset[name] = value;
  }

  function removeData(node, name) {
    if (node && node.dataset[name] !== undefined) delete node.dataset[name];
  }

  function noFlightMessage(value) {
    var message = String(value || '').replace(/\s+/g, ' ').trim();
    return message.indexOf('Для тура варианты рейсов не найдены.') === 0
      || message.indexOf('Данные по рейсам пока не получены.') === 0;
  }

  function flowLabel() {
    return 'Оставить заявку';
  }

  function localizedMoneyNumber(value) {
    var compact = String(value == null ? '' : value)
      .replace(/[\s\u00a0\u202f]/g, '').replace(/[^0-9,.-]/g, '');
    if (!compact) return 0;
    var separator = Math.max(compact.lastIndexOf(','), compact.lastIndexOf('.'));
    compact = separator >= 0 && compact.length - separator - 1 > 0 && compact.length - separator - 1 <= 2
      ? compact.slice(0, separator).replace(/[.,]/g, '') + '.' + compact.slice(separator + 1).replace(/[.,]/g, '')
      : compact.replace(/[.,]/g, '');
    var parsed = Number(compact);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
  }

  function correctFlightTradeoffs() {
    var variants = Array.from(selected.querySelectorAll('.flight-variant'));
    if (variants.length < 2) return false;
    var prices = variants.map(function (variant) {
      var value = variant.querySelector('.flight-choice>b');
      return localizedMoneyNumber(value && value.textContent);
    });
    if (prices.some(function (price) { return !price; })) return false;
    var minimum = Math.min.apply(null, prices);
    var changed = false;
    variants.forEach(function (variant, index) {
      var label = Array.from(variant.querySelectorAll('.flight-choice-tradeoffs span')).find(function (node) {
        return /минимальн/i.test(String(node.textContent || ''));
      });
      if (!label) return;
      var delta = prices[index] - minimum;
      var next = delta === 0 ? 'Самая низкая цена' : '+' + new Intl.NumberFormat('ru-RU').format(delta) + ' ₽ к минимальной';
      if (label.textContent !== next) {
        label.textContent = next;
        changed = true;
      }
      var best = delta === 0;
      if (label.classList.contains('is-best-price') !== best) {
        label.classList.toggle('is-best-price', best);
        changed = true;
      }
    });
    return changed;
  }

  /* @include behavior/selected/flight-fallback.js */

  function clearFallback() {
    if (selected.classList.contains('search3-flight-fallback')) {
      selected.classList.remove('search3-flight-fallback');
    }
    removeData(selected, 'search3FlightFallback');
    selected.querySelectorAll('[data-search3-selected-flow-owned="1"]').forEach(function (node) {
      node.remove();
    });
    var retained = selected.querySelector('.search3-flight-continue--fallback');
    if (retained && retained.classList.contains('search3-flight-continue--fallback')) {
      retained.classList.remove('search3-flight-continue--fallback');
    }
  }

  function sync() {
    var visible = !selected.hidden && getComputedStyle(selected).display !== 'none' && selected.children.length > 0;
    if (body.classList.contains('search3-selected-open') !== visible) {
      body.classList.toggle('search3-selected-open', visible);
    }
    var flights = selected.querySelector('.tour-flights');
    correctFlightTradeoffs();
    if (noFlightState(flights)) {
      if (!selected.classList.contains('search3-flight-fallback')) {
        selected.classList.add('search3-flight-fallback');
      }
      setData(selected, 'search3FlightFallback', '1');
      ensureEmptyFlightRecovery(flights);
      ensureReviewAction(flights);
    } else {
      clearFallback();
    }
  }

  function schedule() {
    if (queued) return;
    queued = true;
    window.requestAnimationFrame(function () {
      queued = false;
      sync();
    });
  }

  window.addEventListener('v2:tour-selected', function (event) {
    currentTour = event && event.detail && event.detail.tour || null;
    schedule();
  });
  ['v2:tour-price-updated', 'v2:flight-selected', 'v2:selected-tour-opened',
    'v2:selected-tour-closed', 'v2:results-rendered', 'v2:booking-review',
    'search3:lead-entry', 'v2:lead-started', 'v2:lead-success', 'v2:lead-error'].forEach(function (name) {
    window.addEventListener(name, schedule);
  });

  new MutationObserver(schedule).observe(selected, {
    childList: true,
    subtree: true,
    characterData: true,
    attributes: true,
    attributeFilter: ['hidden', 'class', 'style']
  });

  schedule();
  window.Search3SelectedFlowV2 = Object.freeze({
    version: 5,
    sync: sync,
    noFlightState: noFlightState,
    ensureEmptyFlightRecovery: ensureEmptyFlightRecovery,
    activateReview: activateReview,
    localizedMoneyNumber: localizedMoneyNumber,
    correctFlightTradeoffs: correctFlightTradeoffs
  });
})();
