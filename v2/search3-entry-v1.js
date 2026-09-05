/* Search3 entry behavior overlay. Preview only. */
(function () {
  'use strict';

  if (window.Search3CandidateEntryV1) return;

  var form = document.getElementById('tourSearch');
  var mobile = window.matchMedia && window.matchMedia('(max-width:760px)');
  var settleTimers = [];
  if (!form || !mobile || !document.body.classList.contains('search3-candidate')) return;

  function entryNodes() {
    var control = form.elements.region;
    var region = control && control.closest ? control.closest('.field') : null;
    return {
      main: form.querySelector('.search3-primary-grid'),
      advanced: form.querySelector('.search3-quality__grid'),
      region: region
    };
  }

  function sync() {
    syncPriceCalendar();
    var nodes = entryNodes();
    if (!nodes.main || !nodes.advanced || !nodes.region) return false;

    nodes.region.classList.add('search3-region');
    if (mobile.matches) {
      if (nodes.region.parentNode !== nodes.advanced) {
        nodes.advanced.insertBefore(nodes.region, nodes.advanced.firstElementChild);
      }
      form.dataset.search3EntryLayout = 'mobile-compact';
    } else {
      var dates = nodes.main.querySelector('.search3-dates');
      if (nodes.region.parentNode !== nodes.main || nodes.region.nextElementSibling !== dates) {
        nodes.main.insertBefore(nodes.region, dates || nodes.main.querySelector('.search-submit'));
      }
      form.dataset.search3EntryLayout = 'desktop';
    }
    syncResultSummary();
    return true;
  }

  function nodeText(id) {
    var node = document.getElementById(id);
    return node ? String(node.textContent || '').replace(/\s+/g, ' ').trim() : '';
  }

  function syncResultSummary() {
    var route = document.querySelector('#resultsSearchSummary .results-search-summary__route');
    if (!route) return;
    var detail = route.querySelector('.search3-entry-summary-detail');
    if (!detail) {
      detail = document.createElement('span');
      detail.className = 'search3-entry-summary-detail';
      route.appendChild(detail);
    }
    var values = ['resultsSearchDates', 'resultsSearchNights', 'resultsSearchGuests']
      .map(nodeText)
      .filter(function (value) { return value && value !== '—'; });
    var next = values.join(' · ');
    if (detail.textContent !== next) detail.textContent = next;
    var hidden = !values.length;
    if (detail.hidden !== hidden) detail.hidden = hidden;
  }

  // Keep the existing calendar's prices, date selection and search owner intact.
  // Only adapt its presentation: expanded on desktop, compact on a phone.
  function syncPriceCalendar() {
    var calendar = document.getElementById('currentPriceCalendar');
    if (!calendar || calendar.hidden || calendar.querySelector('.search3-price-calendar')) return;
    var days = calendar.querySelector('.current-price-calendar__days');
    var best = calendar.querySelector('.current-price-calendar__day.is-best strong');
    if (!days || !best) return;

    var details = document.createElement('details');
    details.className = 'search3-price-calendar';
    details.open = !mobile.matches;
    var summary = document.createElement('summary');
    summary.className = 'search3-price-calendar__summary';
    var title = document.createElement('strong');
    title.id = 'search3PriceCalendarTitle';
    title.textContent = 'Календарь цен';
    var price = document.createElement('span');
    price.textContent = 'от ' + best.textContent.trim();
    summary.appendChild(title);
    summary.appendChild(price);
    details.appendChild(summary);
    var content = document.createElement('div');
    content.className = 'search3-price-calendar__content';
    while (calendar.firstChild) content.appendChild(calendar.firstChild);
    details.appendChild(content);
    calendar.appendChild(details);
    calendar.setAttribute('aria-labelledby', title.id);
  }

  function settle() {
    settleTimers.forEach(function (timer) { window.clearTimeout(timer); });
    settleTimers = [0, 40, 160, 320].map(function (delay) {
      return window.setTimeout(sync, delay);
    });
  }

  if (typeof mobile.addEventListener === 'function') mobile.addEventListener('change', settle);
  else if (typeof mobile.addListener === 'function') mobile.addListener(settle);
  window.addEventListener('v2:search-reset', settle);
  window.addEventListener('v2:results-rendered', settle);
  window.addEventListener('v2:search-complete', settle);
  form.addEventListener('change', settle);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', settle, { once: true });
  } else {
    settle();
  }

  window.Search3CandidateEntryV1 = Object.freeze({
    version: 1,
    sync: sync
  });
})();
