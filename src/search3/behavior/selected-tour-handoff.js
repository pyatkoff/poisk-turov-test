/* Candidate-only selected-tour handoff. Production search, tour and lead contracts stay authoritative. */
(function () {
  'use strict';

  if (window.Search3CandidateSelectedHandoffV1) return;
  var selected = document.getElementById('selectedTour');
  var results = document.getElementById('results');
  if (!selected || !results || !document.body.classList.contains('search3-candidate')) return;
  var selectedFocusRun = 0;
  var returnFocusRun = 0;

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
    selected.setAttribute('aria-busy', isTourLoading() ? 'true' : 'false');
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

  function focusSelectedHeading() {
    if (selected.hidden) return;
    var heading = prepareSelectedContext();
    if (!heading) return;
    try { heading.focus({ preventScroll: true }); } catch (_error) { heading.focus(); }
  }

  function focusSelectedContext() {
    if (selected.hidden || !prepareSelectedContext()) return;
    try { selected.focus({ preventScroll: true }); } catch (_error) { selected.focus(); }
  }

  function scheduleSelectedContextFocus() {
    var run = ++selectedFocusRun;
    var attempts = 0;
    function settle() {
      if (run !== selectedFocusRun || selected.hidden) return;
      var heading = prepareSelectedContext();
      attempts += 1;
      if (!heading || !heading.isConnected) {
        if (attempts < 6) window.requestAnimationFrame(settle);
        return;
      }
      if (document.activeElement !== heading && document.activeElement !== selected) focusSelectedContext();
      if (attempts < 6) window.requestAnimationFrame(settle);
    }
    window.setTimeout(function () { window.requestAnimationFrame(settle); }, 0);
  }

  function isVisibleFocusTarget(target) {
    return !!(target && target.isConnected && !target.disabled && target.getClientRects().length > 0);
  }

  function focusReturnedContext(sourceHint) {
    var run = ++returnFocusRun;
    var attempts = 0;
    var source = sourceHint || (window.V2SelectedTourReturnV1 && window.V2SelectedTourReturnV1.sourceButton);
    var card = source && source.closest && source.closest('.hotel-card');

    function settle() {
      if (run !== returnFocusRun || !selected.hidden) return;
      var target = card && card.querySelector('.search3-show-tours');
      if (!isVisibleFocusTarget(target)) {
        target = results;
        if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
      }
      results.dataset.search3ReturnFocus = target === results ? 'results' : 'resume-tours';
      if (document.activeElement !== target) {
        try { target.focus({ preventScroll: true }); } catch (_error) { target.focus(); }
      }
      attempts += 1;
      if (attempts < 8) window.requestAnimationFrame(settle);
    }

    window.setTimeout(function () { window.requestAnimationFrame(settle); }, 0);
  }

  new MutationObserver(function () {
    syncBusy();
    restoreProductionLabels();
  }).observe(selected, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });

  new MutationObserver(restoreProductionLabels).observe(results, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['disabled']
  });

  function cancelPendingFocus(event) {
    if (event.target && selected.contains(event.target)) selectedFocusRun += 1;
  }
  function cancelReturnFocus() {
    if (selected.hidden) returnFocusRun += 1;
  }
  selected.addEventListener('pointerdown', cancelPendingFocus, true);
  selected.addEventListener('keydown', cancelPendingFocus, true);
  document.addEventListener('pointerdown', cancelReturnFocus, true);
  document.addEventListener('keydown', cancelReturnFocus, true);

  window.addEventListener('v2:tour-selected', function () {
    selected.setAttribute('aria-busy', 'false');
    restoreProductionLabels();
    scheduleSelectedContextFocus();
  });
  window.addEventListener('v2:tour-returned', function (event) {
    selectedFocusRun += 1;
    selected.setAttribute('aria-busy', 'false');
    restoreProductionLabels();
    focusReturnedContext(event && event.detail && event.detail.source);
  });
  window.addEventListener('v2:search-reset', function () {
    selectedFocusRun += 1;
    returnFocusRun += 1;
    selected.setAttribute('aria-busy', 'false');
  });

  syncBusy();
  restoreProductionLabels();
  window.Search3CandidateSelectedHandoffV1 = Object.freeze({
    version: 1,
    restoreProductionLabels: restoreProductionLabels,
    syncBusy: syncBusy,
    focusSelectedHeading: focusSelectedHeading,
    focusSelectedContext: focusSelectedContext,
    scheduleSelectedContextFocus: scheduleSelectedContextFocus,
    focusReturnedContext: focusReturnedContext
  });
})();
