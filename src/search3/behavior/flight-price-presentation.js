

/* Search3-only display guard for localized decimal flight prices.
   The authoritative tour/flight price values and selection events remain untouched. */
(function () {
  'use strict';

  if (window.Search3CandidateFlightTradeoffV1) return;
  var selected = document.getElementById('selectedTour');
  if (!selected || !document.body.classList.contains('search3-candidate')) return;
  var queued = false;

  function localizedMoneyNumber(value) {
    var compact = String(value == null ? '' : value)
      .replace(/[\s\u00a0\u202f]/g, '')
      .replace(/[^0-9,.-]/g, '');
    if (!compact) return 0;
    var comma = compact.lastIndexOf(',');
    var dot = compact.lastIndexOf('.');
    var separator = Math.max(comma, dot);
    if (separator >= 0 && compact.length - separator - 1 > 0 && compact.length - separator - 1 <= 2) {
      compact = compact.slice(0, separator).replace(/[.,]/g, '') + '.' + compact.slice(separator + 1).replace(/[.,]/g, '');
    } else {
      compact = compact.replace(/[.,]/g, '');
    }
    var parsed = Number(compact);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
  }

  function displayedPrice(variant) {
    var value = variant && variant.querySelector('.flight-choice>b');
    return localizedMoneyNumber(value && value.textContent);
  }

  function money(value) {
    return new Intl.NumberFormat('ru-RU').format(value);
  }

  function correctTradeoffs() {
    var variants = Array.from(selected.querySelectorAll('.flight-variant'));
    if (variants.length < 2) return false;
    var prices = variants.map(displayedPrice);
    if (prices.some(function (price) { return !price; })) return false;
    var minimum = Math.min.apply(null, prices);
    var changed = false;
    variants.forEach(function (variant, index) {
      var labels = Array.from(variant.querySelectorAll('.flight-choice-tradeoffs span'));
      var label = labels.find(function (node) { return /минимальн/i.test(String(node.textContent || '')); });
      if (!label) return;
      var delta = prices[index] - minimum;
      var next = delta === 0 ? 'Самая низкая цена' : '+' + money(delta) + ' ₽ к минимальной';
      if (label.textContent !== next) {
        label.textContent = next;
        label.classList.toggle('is-best-price', delta === 0);
        changed = true;
      }
    });
    return changed;
  }

  function schedule() {
    if (queued) return;
    queued = true;
    window.setTimeout(function () {
      queued = false;
      correctTradeoffs();
    }, 0);
  }

  new MutationObserver(schedule).observe(selected, { childList: true, subtree: true, characterData: true });
  window.addEventListener('v2:flight-selected', schedule);
  schedule();
  window.Search3CandidateFlightTradeoffV1 = Object.freeze({
    version: 1,
    localizedMoneyNumber: localizedMoneyNumber,
    displayedPrice: displayedPrice,
    correctTradeoffs: correctTradeoffs
  });
})();
