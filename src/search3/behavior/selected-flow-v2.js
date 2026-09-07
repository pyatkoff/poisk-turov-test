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

  var format = window.Search3CandidateResultsV1;
  var queued = false;
  var currentTour = null;
  var currentTotal = 0;
  var mobileBar = document.querySelector('.search3-selected-mobile-bar');
  if (!mobileBar) {
    mobileBar = document.createElement('div');
    mobileBar.className = 'search3-selected-mobile-bar';
    mobileBar.hidden = true;
    mobileBar.innerHTML = '<div class="search3-selected-mobile-bar__price"><small>Стоимость тура</small><strong data-s3-selected-price>—</strong></div><button type="button" data-s3-selected-lead>Далее: итог тура</button>';
    body.appendChild(mobileBar);
  }

  function text(node) {
    return String(node && node.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function ensureSelectedTrust() {
    if (selected.querySelector('.selected-confidence')) return;
    var form = selected.querySelector('.lead-form');
    if (!form) return;
    var flights = selected.querySelector('.tour-flights');
    var box = document.createElement('section');
    box.className = 'selected-confidence';
    box.setAttribute('aria-label', 'Покупка и сопровождение AnyTour');
    box.innerHTML = '<div><span>Покупка с сопровождением</span><strong>Без оплаты на этом шаге</strong><p>Менеджер проверит детали тура, оформит договор до оплаты и останется на связи до вылета и во время поездки.</p></div><div class="selected-confidence-steps"><b>Договор до оплаты</b><b>Уточним рейс и итоговую стоимость</b><b>Поддержка до и во время отдыха</b></div>';
    if (flights) flights.insertAdjacentElement('afterend', box);
    else form.insertAdjacentElement('beforebegin', box);
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

  function labelValueRows(scope, rowSelector, labelSelector, valueSelector, values) {
    if (!scope) return;
    scope.querySelectorAll(rowSelector).forEach(function (row) {
      var label = row.querySelector(labelSelector);
      var value = row.querySelector(valueSelector);
      var key = text(label).toLowerCase();
      if (value && values[key]) setText(value, values[key]);
    });
  }

  function displayValues(value) {
    if (!format) return {};
    return {
      'дата': format.formatDate(value && value.date),
      'питание': format.mealLabel(value && value.meal),
      'номер': format.roomLabel(value && value.roomType),
      'размещение': format.placementLabel(value && value.placement)
    };
  }

  function syncPresentation() {
    if (!currentTour || selected.hidden || !format) return;
    var values = displayValues(currentTour);
    labelValueRows(selected, '.facts > div', 'span', 'b', values);
    labelValueRows(selected, '.search3-booking-summary dl > div', 'dt', 'dd', values);
    labelValueRows(selected, '.search3-final-services > article', 'span', 'strong', values);
    selected.querySelectorAll('.search3-final-services > article').forEach(function (article) {
      if (text(article.querySelector('span')).toLowerCase() === 'номер') {
        setText(article.querySelector('small'), values['размещение']);
      }
    });
    var scope = 'За весь тур · ' + format.partyLabel(number(currentTour.adults) || 2, number(currentTour.childs));
    var selectedPrice = selected.querySelector('.selected-price');
    setText(selectedPrice && selectedPrice.querySelector('small'), scope);
    if (selectedPrice && currentTotal > 0) setAttribute(selectedPrice, 'aria-label', money(currentTotal) + ', ' + scope.toLowerCase());
    var flightContinue = selected.querySelector('.search3-flight-continue button');
    if (flightContinue && !selected.classList.contains('search3-final-review')) setText(flightContinue, flowLabel('flight'));
    setData(selected, 'search3SelectedPresentation', '1');
  }

  function selectedAmount(source) {
    if (currentTotal > 0) return money(currentTotal);
    if (!source) return '';
    return Array.from(source.childNodes || []).filter(function (node) {
      return node.nodeType === 3;
    }).map(function (node) {
      return text(node);
    }).filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
  }

  function normalizeLeadFields() {
    if (!window.matchMedia || !window.matchMedia('(max-width:640px)').matches
        || !selected.classList.contains('search3-lead-entry')) return;
    var form = selected.querySelector('.lead-form');
    var fields = form && form.querySelector('.lead-fields');
    var name = form && form.querySelector('input[name="name"]');
    var phone = form && form.querySelector('input[name="phone"]');
    if (!form || !fields || !name || !phone || form.dataset.search3MobileLeadNormalized === '1') return;
    form.dataset.search3MobileLeadNormalized = '1';
    var nameLabel = name.closest('label');
    var phoneLabel = phone.closest('label');
    if (nameLabel) {
      nameLabel.hidden = false;
      nameLabel.removeAttribute('hidden');
      nameLabel.style.setProperty('display', 'grid', 'important');
      name.hidden = false;
      name.removeAttribute('hidden');
      if (phoneLabel && nameLabel.nextElementSibling !== phoneLabel) fields.insertBefore(nameLabel, phoneLabel);
      else if (!phoneLabel && fields.firstElementChild !== nameLabel) fields.prepend(nameLabel);
    }
    if (phoneLabel) {
      phoneLabel.hidden = false;
      phoneLabel.removeAttribute('hidden');
      phoneLabel.style.setProperty('display', 'grid', 'important');
    }
    Array.from(form.querySelectorAll('button,summary')).forEach(function (node) {
      if (/^Дополнить заявку/i.test(text(node))) {
        node.hidden = true;
        if (node.style.display !== 'none') node.style.setProperty('display', 'none', 'important');
      }
    });
  }

  function continueFlow() {
    if (selected.classList.contains('search3-lead-entry')) return;
    if (selected.classList.contains('search3-final-review')) {
      if (window.Search3SummaryCta && typeof window.Search3SummaryCta.enterLead === 'function') {
        window.Search3SummaryCta.enterLead('mobile-bar');
        return;
      }
      var summary = selected.querySelector('.search3-summary-submit');
      if (summary) {
        summary.click();
        return;
      }
    }
    var next = selected.querySelector('.search3-flight-continue button');
    if (next) {
      next.click();
      return;
    }
    var flights = selected.querySelector('.tour-flights');
    if (flights) flights.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
    if (mobileBar) {
      setText(mobileBar.querySelector('.search3-selected-mobile-bar__price small'), scope);
      var mobileAmount = mobileBar.querySelector('[data-s3-selected-price]');
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
    var visible = !selected.hidden && getComputedStyle(selected).display !== 'none' && selected.children.length > 0;
    body.classList.toggle('search3-selected-open', visible);
    var leadEntry = selected.classList.contains('search3-lead-entry');
    var finalReview = selected.classList.contains('search3-final-review');
    setHidden(mobileBar, !visible || leadEntry || finalReview);
    if (visible && leadEntry) normalizeLeadFields();
    if (visible) {
      var selectedPrice = selected.querySelector('.selected-price');
      setText(mobileBar.querySelector('.search3-selected-mobile-bar__price small'), priceScope());
      setText(mobileBar.querySelector('[data-s3-selected-price]'), selectedAmount(selectedPrice) || '—');
      setText(mobileBar.querySelector('[data-s3-selected-lead]'), flowLabel('flight'));
    }
    var flights = selected.querySelector('.tour-flights');
    syncPresentation();
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
    var mobileAction = event.target && event.target.closest && event.target.closest('[data-s3-selected-lead]');
    if (mobileAction) {
      event.preventDefault();
      continueFlow();
      return;
    }
    var button = event.target && event.target.closest && event.target.closest('.search3-flight-show-all');
    if (!button || !selected.contains(button)) return;
    event.preventDefault();
    toggleFlightDisclosure(button);
  });

  window.addEventListener('v2:tour-selected', function (event) {
    ensureSelectedTrust();
    currentTour = event && event.detail && event.detail.tour || null;
    currentTotal = normalizedTotal({ tour: currentTour }, currentTour);
    schedule();
  });
  window.addEventListener('v2:tour-price-updated', function (event) {
    currentTotal = normalizedTotal(event && event.detail, currentTour);
    schedule();
  });
  ['v2:flight-selected', 'v2:selected-tour-opened', 'v2:selected-tour-closed', 'v2:results-rendered',
    'v2:booking-review', 'search3:lead-entry', 'v2:lead-started', 'v2:lead-success', 'v2:lead-error'].forEach(function (name) {
    window.addEventListener(name, schedule);
  });

  var observer = new MutationObserver(schedule);
  observer.observe(selected, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['hidden', 'class', 'style']
  });

  ensureSelectedTrust();
  schedule();
  window.Search3SelectedTourMobile = {
    version: 14,
    sync: sync,
    scheduleSync: schedule,
    continueFlow: continueFlow,
    normalizeLeadFields: normalizeLeadFields,
    normalizedTotal: normalizedTotal,
    selectedAmount: selectedAmount
  };
  window.Search3CandidateSelectedPresentationV1 = Object.freeze({
    version: 1,
    decorate: function () {
      if (!currentTour || selected.hidden) return;
      syncPresentation();
      syncDisplayedPrice();
    },
    displayValues: displayValues,
    normalizedTotal: function (detail) { return normalizedTotal(detail, currentTour); }
  });
  window.Search3SelectedFlowV2 = Object.freeze({
    version: 4,
    sync: sync,
    noFlightState: noFlightState,
    activateReview: activateReview,
    syncDisplayedPrice: syncDisplayedPrice,
    syncFlightDisclosure: syncFlightDisclosure,
    toggleFlightDisclosure: toggleFlightDisclosure,
    helpers: helpers
  });
})();
