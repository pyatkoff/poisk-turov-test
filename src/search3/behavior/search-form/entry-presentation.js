function installEntryPresentation(form, main, grid, region) {
  if (window.Search3CandidateEntryV1) return;
  var mobile = window.matchMedia && window.matchMedia('(max-width:760px)');
  var settleTimers = [], adaptedPriceCalendar = null;
  if (!mobile) return;
  var collapseMobile = window.matchMedia('(max-width:700px)');

  function expandSearch() {
    form.classList.remove('mobile-search-collapsed');
    if (collapseMobile.matches) window.setTimeout(function () {
      form.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }, 20);
  }

  window.addEventListener('v2:search-started', function () {
    if (!collapseMobile.matches) return;
    form.classList.add('mobile-search-collapsed');
    var details = form.querySelector('details.extras');
    if (details) details.open = false;
    window.setTimeout(function () {
      var status = document.getElementById('status');
      if (status) status.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }, 80);
  });
  window.addEventListener('v2:search-dirty', expandSearch);
  window.addEventListener('v2:search-error', function (event) {
    if (event.detail && event.detail.phase === 'validation') expandSearch();
  });
  function expandAtDesktop() { if (!collapseMobile.matches) expandSearch(); }
  if (collapseMobile.addEventListener) collapseMobile.addEventListener('change', expandAtDesktop);
  else if (collapseMobile.addListener) collapseMobile.addListener(expandAtDesktop);

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
      .map(nodeText).filter(function (value) { return value && value !== '—'; });
    var next = values.join(' · ');
    if (detail.textContent !== next) detail.textContent = next;
    var hidden = !values.length;
    if (detail.hidden !== hidden) detail.hidden = hidden;
  }

  function syncPriceCalendar() {
    var calendar = document.getElementById('currentPriceCalendar');
    if (!calendar || calendar.hidden || calendar === adaptedPriceCalendar) return;
    if (calendar.querySelector('.search3-price-calendar')) {
      adaptedPriceCalendar = calendar;
      return;
    }
    var best = calendar.querySelector('.current-price-calendar__day.is-best strong');
    if (!calendar.querySelector('.current-price-calendar__days') || !best) return;
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
    adaptedPriceCalendar = calendar;
  }

  function sync() {
    syncPriceCalendar();
    if (!region) return false;
    if (mobile.matches) {
      if (region.parentNode !== grid) grid.insertBefore(region, grid.firstElementChild);
      if (form.dataset.search3EntryLayout !== 'mobile-compact') form.dataset.search3EntryLayout = 'mobile-compact';
    } else {
      var dates = main.querySelector('.search3-dates');
      if (region.parentNode !== main || region.nextElementSibling !== dates) main.insertBefore(region, dates || main.querySelector('.search-submit'));
      if (form.dataset.search3EntryLayout !== 'desktop') form.dataset.search3EntryLayout = 'desktop';
    }
    syncResultSummary();
    return true;
  }

  function settle() {
    settleTimers.forEach(function (timer) { window.clearTimeout(timer); });
    settleTimers = [0, 40, 160, 320].map(function (delay) { return window.setTimeout(sync, delay); });
  }

  if (typeof mobile.addEventListener === 'function') mobile.addEventListener('change', settle);
  else if (typeof mobile.addListener === 'function') mobile.addListener(settle);
  window.addEventListener('v2:search-reset', settle);
  window.addEventListener('v2:results-rendered', settle);
  window.addEventListener('v2:search-complete', settle);
  form.addEventListener('change', settle);
  settle();
  window.Search3CandidateEntryV1 = Object.freeze({ version: 1, sync: sync });
}
