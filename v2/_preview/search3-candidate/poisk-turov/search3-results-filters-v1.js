(function () {
  'use strict';

  var nativeMatchMedia = window.__search3CandidateNativeMatchMedia;
  if (typeof nativeMatchMedia === 'function') {
    window.matchMedia = nativeMatchMedia;
    document.documentElement.dataset.search3MatchMediaRestored = window.matchMedia === nativeMatchMedia ? '1' : '0';
    delete window.__search3CandidateNativeMatchMedia;
  }

  if (window.Search3CandidateResultsV1) return;

  var body = document.body;
  var results = document.getElementById('results');
  var tools = document.getElementById('resultsTools');
  var sort = document.getElementById('sortResults');
  if (!body || !body.classList.contains('search3-candidate') || !results || !tools) return;

  var hotelsById = new Map();

  function safe(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function textValue(value) {
    if (value == null) return '';
    if (typeof value === 'object') {
      return textValue(value.russianName || value.fullRussianName || value.name || value.title || '');
    }
    return String(value).trim();
  }

  function hotelId(hotel) {
    return String(hotel && hotel.id != null ? hotel.id : '');
  }

  function representativeTour(hotel) {
    var tours = hotel && Array.isArray(hotel.tours) ? hotel.tours : [];
    if (!tours.length) return null;
    return tours.slice().sort(function (a, b) {
      var left = Number(a && a.price || 0) || Number.MAX_SAFE_INTEGER;
      var right = Number(b && b.price || 0) || Number.MAX_SAFE_INTEGER;
      return left - right;
    })[0] || null;
  }

  function tourWord(count) {
    var n = Math.abs(Number(count) || 0);
    var mod10 = n % 10;
    var mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return 'тур';
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'тура';
    return 'туров';
  }

  function cardFacts(hotel) {
    var tour = representativeTour(hotel);
    if (!tour) return [];
    var facts = [];
    if (tour.date) facts.push(['Вылет', String(tour.date)]);
    if (tour.nights) facts.push(['Ночей', String(tour.nights)]);
    var meal = textValue(tour.meal);
    if (meal) facts.push(['Питание', meal]);
    if (tour.isCharter === true) facts.push(['Рейс', 'Чартер']);
    return facts.slice(0, 4);
  }

  function collapseCard(card) {
    var tours = card.querySelector('.hotel-tours');
    var button = card.querySelector('.search3-show-tours');
    card.classList.remove('search3-tours-open');
    if (tours) tours.hidden = true;
    if (button) {
      button.setAttribute('aria-expanded', 'false');
      button.textContent = 'Показать туры';
    }
  }

  function collapseAll(except) {
    results.querySelectorAll('.hotel-card.search3-tours-open').forEach(function (card) {
      if (card !== except) collapseCard(card);
    });
  }

  function decorateCard(card) {
    if (!card || card.dataset.search3ResultsV1 === '1') return;
    var hotel = hotelsById.get(String(card.dataset.hotelId || ''));
    var bodyNode = card.querySelector('.hotel-body');
    var tours = card.querySelector('.hotel-tours');
    if (!hotel || !bodyNode || !tours) return;

    card.dataset.search3ResultsV1 = '1';
    var facts = cardFacts(hotel);
    if (facts.length) {
      var factsNode = document.createElement('div');
      factsNode.className = 'search3-hotel-facts';
      factsNode.innerHTML = facts.map(function (fact) {
        return '<span><small>' + safe(fact[0]) + '</small><b>' + safe(fact[1]) + '</b></span>';
      }).join('');
      bodyNode.appendChild(factsNode);
    }

    var count = Array.isArray(hotel.tours) ? hotel.tours.length : tours.querySelectorAll('.tour-row').length;
    var action = document.createElement('div');
    action.className = 'search3-hotel-action';
    action.innerHTML = '<div class="search3-hotel-action__copy"><strong>' + count + ' ' + tourWord(count)
      + '</strong><span>доступно по выбранным датам</span></div>'
      + '<button type="button" class="search3-show-tours" aria-expanded="false">Показать туры</button>';
    bodyNode.appendChild(action);

    if (!tours.id) tours.id = 'search3-hotel-tours-' + safe(String(card.dataset.hotelId || 'result'));
    action.querySelector('.search3-show-tours').setAttribute('aria-controls', tours.id);
    tours.hidden = true;
  }

  function decorate(items) {
    hotelsById = new Map((Array.isArray(items) ? items : []).map(function (hotel) {
      return [hotelId(hotel), hotel];
    }));
    body.classList.toggle('search3-results-active', hotelsById.size > 0);
    if (!hotelsById.size) return;
    results.querySelectorAll('.hotel-card').forEach(decorateCard);
    window.setTimeout(mountMobileToolbar, 0);
  }

  function mountMobileToolbar() {
    if (!body.classList.contains('search3-results-active')) return;
    var filterBar = document.querySelector('.mrf-bar');
    if (!filterBar || !sort) return;
    var toolbar = document.querySelector('.search3-mobile-toolbar');
    if (!toolbar) {
      toolbar = document.createElement('div');
      toolbar.className = 'search3-mobile-toolbar';
      toolbar.innerHTML = '<div class="search3-mobile-filter-slot"></div>'
        + '<label class="search3-mobile-sort"><span>Сортировка</span><select aria-label="Сортировка результатов"></select></label>';
      tools.insertAdjacentElement('afterend', toolbar);
      var proxy = toolbar.querySelector('select');
      proxy.innerHTML = sort.innerHTML;
      proxy.value = sort.value;
      proxy.addEventListener('change', function () {
        sort.value = proxy.value;
        sort.dispatchEvent(new Event('change', { bubbles: true }));
      });
      sort.addEventListener('change', function () { proxy.value = sort.value; });
    }
    var slot = toolbar.querySelector('.search3-mobile-filter-slot');
    if (slot && filterBar.parentElement !== slot) slot.appendChild(filterBar);
  }

  window.addEventListener('v2:results-rendered', function (event) {
    collapseAll();
    decorate(event && event.detail && Array.isArray(event.detail.items) ? event.detail.items : []);
  });

  window.addEventListener('v2:search-reset', function () {
    hotelsById.clear();
    collapseAll();
    body.classList.remove('search3-results-active');
  });

  window.addEventListener('v2:tour-selected', function () { collapseAll(); });

  document.addEventListener('click', function (event) {
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
        if (event.matches) window.setTimeout(mountMobileToolbar, 0);
      });
    }
  }

  window.Search3CandidateResultsV1 = Object.freeze({
    version: 1,
    status: 'DONOR_RECONSTRUCTION',
    approvedPixelsCompared: false,
    decorate: decorate,
    collapseAll: collapseAll
  });
})();
