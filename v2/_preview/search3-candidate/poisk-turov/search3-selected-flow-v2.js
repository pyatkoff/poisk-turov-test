/* Search3 no-flight CTA adapter. Preview presentation only. */
(function () {
  'use strict';

  if (window.Search3SelectedFlowV2) return;

  var body = document.body;
  var selected = document.getElementById('selectedTour');
  if (!body || !body.classList.contains('search3-candidate') || !selected) return;

  var queued = false;

  function text(node) {
    return String(node && node.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function noFlightState() {
    var flights = selected.querySelector('.tour-flights');
    if (!flights || flights.querySelector('.flight-variant,.flight-error')) return false;
    var message = text(flights.querySelector('.selected-loading'));
    return message.indexOf('Для тура варианты рейсов не найдены.') === 0
      || message.indexOf('Данные по рейсам пока не получены.') === 0;
  }

  function ensureReviewAction() {
    var flights = selected.querySelector('.tour-flights');
    if (!flights) return null;
    var action = flights.querySelector('.search3-flight-continue');
    if (!action) {
      action = document.createElement('div');
      action.className = 'search3-flight-continue search3-flight-continue--fallback';
      action.dataset.search3SelectedFlowOwned = '1';
      action.innerHTML = '<button type="button" class="primary">Проверить детали тура</button>';
      flights.appendChild(action);
    } else {
      action.classList.add('search3-flight-continue--fallback');
    }
    return action.querySelector('button');
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
      action.textContent = 'Перейти к итогу тура';
      rail.appendChild(action);
    }
    action.dataset.search3SelectedFlowAction = '1';
    action.setAttribute('aria-label', 'Перейти к итогу тура без выбранного рейса');
    bindAction(action);
  }

  function syncMobileAction(active) {
    var button = document.querySelector('.search3-selected-mobile-bar [data-s3-selected-lead]');
    if (!button) return;
    bindAction(button);
    if (active) {
      if (button.dataset.search3SelectedFlowAction !== '1') {
        button.dataset.search3SelectedFlowLabel = text(button);
        button.dataset.search3SelectedFlowAria = button.getAttribute('aria-label') || '';
      }
      button.dataset.search3SelectedFlowAction = '1';
      if (text(button) !== 'К итогу тура') button.textContent = 'К итогу тура';
      if (button.getAttribute('aria-label') !== 'Перейти к итогу тура без выбранного рейса') {
        button.setAttribute('aria-label', 'Перейти к итогу тура без выбранного рейса');
      }
      return;
    }
    if (button.dataset.search3SelectedFlowAction !== '1') return;
    var label = button.dataset.search3SelectedFlowLabel || 'Продолжить';
    if (text(button) !== label) button.textContent = label;
    var aria = button.dataset.search3SelectedFlowAria || '';
    if (aria) button.setAttribute('aria-label', aria);
    else button.removeAttribute('aria-label');
    delete button.dataset.search3SelectedFlowAction;
    delete button.dataset.search3SelectedFlowLabel;
    delete button.dataset.search3SelectedFlowAria;
  }

  function clear() {
    selected.classList.remove('search3-flight-fallback');
    delete selected.dataset.search3FlightFallback;
    selected.querySelectorAll('[data-search3-selected-flow-owned="1"]').forEach(function (node) {
      node.remove();
    });
    var retained = selected.querySelector('.search3-flight-continue--fallback');
    if (retained) retained.classList.remove('search3-flight-continue--fallback');
    syncMobileAction(false);
  }

  function sync() {
    if (!noFlightState()) {
      clear();
      return;
    }
    selected.classList.add('search3-flight-fallback');
    selected.dataset.search3FlightFallback = '1';
    ensureReviewAction();
    ensureRailAction();
    syncMobileAction(true);
  }

  function schedule() {
    if (queued) return;
    queued = true;
    window.requestAnimationFrame(function () {
      queued = false;
      sync();
    });
  }

  var observer = new MutationObserver(schedule);
  observer.observe(selected, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['hidden', 'class']
  });
  var mobileButton = document.querySelector('.search3-selected-mobile-bar [data-s3-selected-lead]');
  if (mobileButton) {
    observer.observe(mobileButton, {
      childList: true,
      subtree: true,
      characterData: true
    });
  }

  schedule();
  window.Search3SelectedFlowV2 = Object.freeze({
    version: 2,
    sync: sync,
    noFlightState: noFlightState,
    activateReview: activateReview
  });
})();
