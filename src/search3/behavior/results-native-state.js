/* Minimal result visibility bridge; rendering, sorting and selection stay canonical. */
(function () {
  'use strict';
  var body = document.body;
  var results = document.getElementById('results');
  var tools = document.getElementById('resultsTools');
  if (!body || !body.classList.contains('search3-candidate') || !results) return;

  function show(value) {
    body.classList.toggle('search3-has-results', value);
    if (tools) tools.hidden = !value;
  }
  function busy(value) { results.setAttribute('aria-busy', value ? 'true' : 'false'); }

  window.addEventListener('v2:search-started', function () { busy(true); });
  ['v2:search-complete', 'v2:search-error'].forEach(function (name) {
    window.addEventListener(name, function () { busy(false); });
  });
  window.addEventListener('v2:results-rendered', function () { busy(false); show(true); });
  window.addEventListener('v2:tour-selected', function () { show(false); });
  window.addEventListener('v2:selected-tour-closed', function () { show(results.children.length > 0); });
  window.addEventListener('v2:search-reset', function () { busy(true); show(false); });
  document.addEventListener('click', function (event) {
    var target = event.target && event.target.closest;
    var back = target && event.target.closest('#selectedTour .back-results,#selectedTour .lead-success-back');
    if (back && !back.disabled) show(results.children.length > 0);
    var edit = target && event.target.closest('#resultsSearchEdit,.empty-edit-search');
    var form = document.getElementById('tourSearch');
    if (!edit || !form) return;
    event.preventDefault();
    show(false);
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    var field = form.querySelector('select:not([disabled]),input:not([type="hidden"]):not([disabled])');
    if (field) field.focus({ preventScroll: true });
  }, true);
})();
