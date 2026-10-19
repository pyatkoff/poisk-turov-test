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
    detail.textContent = values.join(' · ');
    detail.hidden = !values.length;
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
