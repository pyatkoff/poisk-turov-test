/* Candidate-only human-readable selected-tour presentation. */
(function () {
  'use strict';

  if (window.Search3CandidateSelectedPresentationV1) return;
  var selected = document.getElementById('selectedTour');
  var format = window.Search3CandidateResultsV1;
  if (!selected || !format || !document.body.classList.contains('search3-candidate')) return;
  var tour = null;
  var selectedTotal = 0;
  var queued = false;

  function text(value) {
    if (value == null) return '';
    if (typeof value === 'object') return text(value.russianName || value.fullRussianName || value.name || value.title || '');
    return String(value).trim();
  }

  function plural(count, one, few, many) {
    var n = Math.abs(Number(count) || 0);
    var mod10 = n % 10;
    var mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few;
    return many;
  }

  function setText(node, value) {
    value = String(value || '').trim();
    if (node && value && String(node.textContent || '').trim() !== value) node.textContent = value;
  }

  function labelValueRows(scope, rowSelector, labelSelector, valueSelector, values) {
    if (!scope) return;
    scope.querySelectorAll(rowSelector).forEach(function (row) {
      var label = row.querySelector(labelSelector);
      var value = row.querySelector(valueSelector);
      var key = String(label && label.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      if (value && values[key]) setText(value, values[key]);
    });
  }

  function partyLabel(value) {
    return format.partyLabel(Number(value && value.adults || 2), Number(value && value.childs || 0));
  }

  function normalizedTotal(detail) {
    var value = detail || {};
    var sourceTour = value.tour || tour || {};
    if (value.pricePending) return Number(value.basePrice || sourceTour.price || 0);
    return Number(value.price || 0) || Number(value.basePrice || sourceTour.price || 0);
  }

  function dateWithNights(value) {
    var date = format.formatDate(value && value.date);
    var nights = Number(value && value.nights || 0);
    var stay = nights ? nights + ' ' + plural(nights, 'ночь', 'ночи', 'ночей') : '';
    return [date, stay].filter(Boolean).join(' · ');
  }

  function displayValues(value) {
    return {
      'дата': format.formatDate(value && value.date),
      'питание': format.mealLabel(value && value.meal),
      'номер': format.roomLabel(value && value.roomType),
      'размещение': format.placementLabel(value && value.placement)
    };
  }

  function decoratePriceContext(value) {
    var party = partyLabel(value);
    var scope = 'За весь тур · ' + party;
    var amount = selectedTotal || Number(value && value.price || 0);
    var money = amount > 0 ? new Intl.NumberFormat('ru-RU').format(amount) + ' ₽' : '';
    var selectedPrice = selected.querySelector('.selected-price');
    var selectedPriceLabel = selectedPrice && selectedPrice.querySelector('small');
    setText(selectedPriceLabel, scope);
    if (selectedPrice && money) selectedPrice.setAttribute('aria-label', money + ', ' + scope.toLowerCase());

    selected.querySelectorAll('.search3-booking-summary__total,.search3-tour-detail-rail__price').forEach(function (price) {
      setText(price.querySelector(':scope > span'), scope);
      setText(price.querySelector(':scope > strong'), money);
      if (money) price.setAttribute('aria-label', money + ', ' + scope.toLowerCase());
    });

    var mobileBar = document.querySelector('.search3-selected-mobile-bar');
    if (mobileBar) {
      setText(mobileBar.querySelector('.search3-selected-mobile-bar__price small'), scope);
      var mobilePrice = mobileBar.querySelector('[data-s3-selected-price]');
      setText(mobilePrice, money);
      if (mobilePrice && money) mobilePrice.setAttribute('aria-label', money + ', ' + scope.toLowerCase());
      setText(mobileBar.querySelector('[data-s3-selected-lead]'), 'Далее: итог тура');
    }
  }

  function decorate() {
    if (!tour || selected.hidden) return;
    var values = displayValues(tour);
    labelValueRows(selected, '.facts > div', 'span', 'b', values);
    labelValueRows(selected, '.search3-booking-summary dl > div', 'dt', 'dd', values);
    labelValueRows(selected, '.search3-tour-detail-rail dl > div', 'dt', 'dd', values);
    labelValueRows(selected, '.search3-final-services > article', 'span', 'strong', values);

    selected.querySelectorAll('.search3-final-services > article').forEach(function (article) {
      var label = article.querySelector('span');
      if (String(label && label.textContent || '').trim().toLowerCase() === 'номер') {
        setText(article.querySelector('small'), values['размещение']);
      }
    });

    selected.querySelectorAll('.search3-tour-detail-rail dl > div').forEach(function (row) {
      if (String(row.querySelector('dt') && row.querySelector('dt').textContent || '').trim().toLowerCase() === 'дата') {
        setText(row.querySelector('dd'), dateWithNights(tour));
      }
    });

    decoratePriceContext(tour);
    var detailContinue = selected.querySelector('.search3-tour-detail-rail__continue');
    setText(detailContinue, 'Далее: итог тура');
    var flightContinue = selected.querySelector('.search3-flight-continue button');
    if (flightContinue && !selected.classList.contains('search3-final-review')) setText(flightContinue, 'Далее: итог тура');
    selected.dataset.search3SelectedPresentation = '1';
  }

  function schedule() {
    if (queued) return;
    queued = true;
    window.setTimeout(function () {
      queued = false;
      decorate();
    }, 0);
  }

  new MutationObserver(schedule).observe(selected, { childList: true, subtree: true });
  window.addEventListener('v2:tour-selected', function (event) {
    tour = event && event.detail && event.detail.tour || null;
    selectedTotal = Number(tour && tour.price || 0);
    schedule();
  });
  window.addEventListener('v2:flight-selected', schedule);
  window.addEventListener('v2:tour-price-updated', function (event) {
    selectedTotal = normalizedTotal(event && event.detail);
    schedule();
  });
  window.addEventListener('v2:booking-review', schedule);
  window.addEventListener('search3:lead-entry', schedule);

  window.Search3CandidateSelectedPresentationV1 = Object.freeze({
    version: 1,
    decorate: decorate,
    displayValues: displayValues,
    dateWithNights: dateWithNights,
    normalizedTotal: normalizedTotal
  });
})();


