

/* Candidate-only selected-tour handoff. Production search, tour and lead contracts stay authoritative. */
(function () {
  'use strict';

  if (window.Search3CandidateSelectedHandoffV1) return;
  var selected = document.getElementById('selectedTour');
  var results = document.getElementById('results');
  if (!selected || !results || !document.body.classList.contains('search3-candidate')) return;

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
    var heading = selected.querySelector('.selected-head h2') || selected.querySelector('.search3-review-heading h2');
    if (!heading) return null;
    if (!heading.id) heading.id = 'search3-selected-tour-heading';
    heading.setAttribute('tabindex', '-1');
    selected.setAttribute('tabindex', '-1');
    selected.setAttribute('aria-labelledby', heading.id);
    return heading;
  }

  function focusSelectedContext() {
    if (selected.hidden || !prepareSelectedContext()) return;
    try { selected.focus({ preventScroll: true }); } catch (_error) { selected.focus(); }
  }

  function scheduleSelectedContextFocus() {
    window.requestAnimationFrame(focusSelectedContext);
  }

  // Result button labels have their own observer below. Tour mutations only
  // change busy state; do not rescan every hotel button for each flight update.
  new MutationObserver(syncBusy).observe(selected, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });

  new MutationObserver(restoreProductionLabels).observe(results, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['disabled']
  });

  window.addEventListener('v2:tour-selected', function () {
    selected.setAttribute('aria-busy', 'false');
    restoreProductionLabels();
    scheduleSelectedContextFocus();
  });
  window.addEventListener('v2:search-reset', function () {
    selected.setAttribute('aria-busy', 'false');
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
})();
