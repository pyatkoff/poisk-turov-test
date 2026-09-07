

/* Candidate-owned result and responsive safety layer. */
(function () {
  'use strict';

  if (window.Search3CandidateResultsV1) return;

  var body = document.body;
  var results = document.getElementById('results');
  var tools = document.getElementById('resultsTools');
  var sort = document.getElementById('sortResults');
  var selected = document.getElementById('selectedTour');
  if (!body || !body.classList.contains('search3-candidate') || !results || !tools) return;

  var form = document.getElementById('tourSearch');
  var heading = tools.querySelector('strong');
  var summary = document.getElementById('resultSummary');
  var searchSummary = document.getElementById('resultsSearchSummary');
  var edit = document.getElementById('resultsSearchEdit');
  var topReady = !!(form && heading && summary);
  var hotelsById = new Map();
  var mobileToolbarTimer = null;
  var frameQueued = false;

  function restoreProductionLabels() {
    results.querySelectorAll('button[data-search3-production-label]').forEach(function (button) {
      var original = String(button.dataset.search3ProductionLabel || '').trim();
      var current = String(button.textContent || '').replace(/\s+/g, ' ').trim();
      if (!button.disabled && original && current !== original) button.textContent = original;
    });
  }

  function isTourLoading() {
    if (selected.hidden || selected.children.length !== 1) return false;
    var onlyChild = selected.firstElementChild;
    return !!(onlyChild && onlyChild.classList.contains('selected-loading') && !onlyChild.querySelector('button,a,input,select,textarea'));
  }

  function syncBusy() {
    var next = isTourLoading() ? 'true' : 'false';
    if (selected.getAttribute('aria-busy') !== next) selected.setAttribute('aria-busy', next);
  }

  function prepareSelectedContext() {
    var selectedHeading = selected.querySelector('.selected-head h2');
    if (!selectedHeading) return null;
    if (!selectedHeading.id) selectedHeading.id = 'search3-selected-tour-heading';
    selectedHeading.setAttribute('tabindex', '-1');
    selected.setAttribute('tabindex', '-1');
    selected.setAttribute('aria-labelledby', selectedHeading.id);
    return selectedHeading;
  }

  function focusSelectedContext() {
    if (selected.hidden || !prepareSelectedContext()) return;
    try { selected.focus({ preventScroll: true }); } catch (_error) { selected.focus(); }
  }

  function scheduleSelectedContextFocus() {
    window.requestAnimationFrame(focusSelectedContext);
  }

  function word(number, one, few, many) {
    var value = Math.abs(Number(number) || 0) % 100;
    var last = value % 10;
    if (value > 10 && value < 20) return many;
    if (last === 1) return one;
    if (last >= 2 && last <= 4) return few;
    return many;
  }

  function toursCount(items) {
    return items.reduce(function (count, hotel) {
      return count + (Array.isArray(hotel && hotel.tours) ? hotel.tours.length : 0);
    }, 0);
  }

  function selectedText(name) {
    var element = form.elements[name];
    if (!element) return '';
    if (element.tagName === 'SELECT') {
      var option = element.options && element.selectedIndex >= 0 ? element.options[element.selectedIndex] : null;
      return option ? String(option.textContent || '').trim() : '';
    }
    return String(element.value || '').trim();
  }

  function syncRoute() {
    if (!searchSummary) return;
    var route = searchSummary.querySelector('#resultsSearchRoute');
    if (!route) return;
    var destination = [selectedText('country'), selectedText('region')].filter(Boolean).join(', ');
    var text = [selectedText('from'), destination].filter(Boolean).join(' → ');
    if (text) route.textContent = text;
  }

  function emptyLocalResults() {
    return !!document.querySelector('.results-filter-rail[data-s3-empty-results="1"]');
  }

  function hasResults() {
    return !!results.querySelector('.hotel-card') || emptyLocalResults();
  }

  function syncResultsState() {
    var has = hasResults();
    body.classList.toggle('search3-has-results', has);
    if (has) {
      body.classList.remove('search3-editing-search');
      if (topReady) syncRoute();
    }
  }

  function updateTop(items) {
    var hotels = items.length;
    var tours = toursCount(items);
    var has = hotels > 0 || emptyLocalResults();
    heading.textContent = 'Найдено ' + tours + ' ' + word(tours, 'тур', 'тура', 'туров');
    summary.textContent = hotels
      ? hotels + ' ' + word(hotels, 'отель', 'отеля', 'отелей') + ' · актуальные варианты'
      : 'Актуальные варианты';
    body.classList.toggle('search3-has-results', has);
    body.classList.remove('search3-editing-search');
    if (has) syncRoute();
  }

  function scheduleResultsSync() {
    if (frameQueued) return;
    frameQueued = true;
    requestAnimationFrame(function () {
      frameQueued = false;
      syncResultsState();
    });
  }

  /* @include behavior/results/labels.js */

  /* @include behavior/results/cards.js */

  /* @include behavior/results/toolbar.js */

  window.addEventListener('v2:results-rendered', function (event) {
    var items = event && event.detail && Array.isArray(event.detail.items) ? event.detail.items : [];
    if (topReady) updateTop(items);
    collapseAll();
    decorate(items);
  });

  window.addEventListener('v2:search-reset', function () {
    body.classList.remove('search3-has-results', 'search3-editing-search');
    if (topReady) {
      heading.textContent = 'Предложения';
      summary.textContent = 'Актуальные варианты';
    }
    cancelMobileToolbar();
    hotelsById.clear();
    collapseAll();
    body.classList.remove('search3-results-active');
    if (selected) selected.setAttribute('aria-busy', 'false');
  });

  window.addEventListener('v2:tour-selected', function () {
    collapseAll();
    if (!selected) return;
    selected.setAttribute('aria-busy', 'false');
    restoreProductionLabels();
    scheduleSelectedContextFocus();
  });

  document.addEventListener('click', function (event) {
    var more = event.target && event.target.closest && event.target.closest('.tour-more-toggle');
    if (more && results.contains(more)) {
      window.setTimeout(function () {
        var card = more.closest('.hotel-card');
        var hotel = card && hotelsById.get(String(card.dataset.hotelId || ''));
        decorateTourRows(card && card.querySelector('.hotel-tours'), hotel);
      }, 0);
      return;
    }
    var button = event.target && event.target.closest && event.target.closest('.search3-show-tours');
    if (!button || !results.contains(button)) return;
    var card = button.closest('.hotel-card');
    var tours = card && card.querySelector('.hotel-tours');
    if (!card || !tours) return;
    var open = button.getAttribute('aria-expanded') !== 'true';
    collapseAll(open ? card : null);
    card.classList.toggle('search3-tours-open', open);
    tours.hidden = !open;
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    button.textContent = open ? 'Скрыть туры' : 'Показать туры';
  }, true);

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Tab') return;
    var sheet = document.querySelector('.mrf-sheet.is-open');
    var panel = sheet && sheet.querySelector('.mrf-panel[role="dialog"]');
    if (!panel) return;
    var focusable = Array.prototype.filter.call(
      panel.querySelectorAll('button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'),
      function (node) {
        var box = node.getBoundingClientRect();
        var style = window.getComputedStyle(node);
        return box.width > 0 && box.height > 0 && style.display !== 'none' && style.visibility !== 'hidden';
      }
    );
    if (!focusable.length) return;
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && (document.activeElement === last || !panel.contains(document.activeElement))) {
      event.preventDefault();
      first.focus();
    }
  });

  if (window.matchMedia) {
    var compactResults = window.matchMedia('(max-width:999px)');
    if (compactResults.addEventListener) {
      compactResults.addEventListener('change', function (event) {
        if (event.matches) scheduleMobileToolbar();
      });
    }
  }

  new MutationObserver(scheduleResultsSync).observe(results, { childList: true });
  syncResultsState();
  if (selected) {
    // Tour mutations only own busy state; result mutations own button labels.
    new MutationObserver(syncBusy).observe(selected, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });
    new MutationObserver(restoreProductionLabels).observe(results, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['disabled']
    });
    syncBusy();
    restoreProductionLabels();
    window.Search3CandidateSelectedHandoffV1 = Object.freeze({
      version: 1,
      restoreProductionLabels: restoreProductionLabels,
      syncBusy: syncBusy,
      focusSelectedContext: focusSelectedContext,
      scheduleSelectedContextFocus: scheduleSelectedContextFocus
    });
  }
  if (topReady) {
    if (edit) edit.addEventListener('click', function () {
      body.classList.add('search3-editing-search');
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      var focusTarget = form.querySelector('select,input:not([type="hidden"]),button');
      if (focusTarget) setTimeout(function () {
        try { focusTarget.focus({ preventScroll: true }); } catch (_error) { focusTarget.focus(); }
      }, 250);
    });
    form.addEventListener('change', syncRoute);
    syncRoute();
  }

  window.Search3CandidateResultsV1 = Object.freeze({
    version: 3,
    status: 'REFERENCE_IMPLEMENTATION_IN_PROGRESS',
    approvedPixelsCompared: false,
    partyLabel: guestCountLabel,
    plural: plural,
    formatDate: formatTourDate,
    mealLabel: mealLabel,
    roomLabel: roomLabel,
    placementLabel: placementLabel,
    decorate: decorate,
    collapseAll: collapseAll
  });
})();
