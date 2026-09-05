

/* Candidate-owned result and responsive safety layer. */
(function () {
  'use strict';

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

  function plural(count, one, few, many) {
    var n = Math.abs(Number(count) || 0);
    var mod10 = n % 10;
    var mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return one;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few;
    return many;
  }

  function formatTourDate(value) {
    var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || '').trim());
    if (!match) return String(value || '').trim();
    var months = ['янв.', 'февр.', 'марта', 'апр.', 'мая', 'июня', 'июля', 'авг.', 'сент.', 'окт.', 'нояб.', 'дек.'];
    return String(Number(match[3])) + ' ' + months[Number(match[2]) - 1] + ' ' + match[1];
  }

  function mealLabel(value) {
    var raw = textValue(value);
    if (!raw) return '';
    var key = raw.toUpperCase().replace(/[._-]+/g, ' ').replace(/\s+/g, ' ').trim();
    var labels = {
      'RO': 'Без питания',
      'ROOM ONLY': 'Без питания',
      'BB': 'Завтраки',
      'BREAKFAST': 'Завтраки',
      'HB': 'Завтрак и ужин',
      'HALF BOARD': 'Завтрак и ужин',
      'FB': 'Трёхразовое питание',
      'FULL BOARD': 'Трёхразовое питание',
      'AI': 'Всё включено',
      'ALL INCLUSIVE': 'Всё включено',
      'UAI': 'Ультра всё включено',
      'ULTRA ALL INCLUSIVE': 'Ультра всё включено'
    };
    return labels[key] || raw;
  }

  function guestCountLabel(adults, children) {
    adults = Math.max(1, Number(adults) || 2);
    children = Math.max(0, Number(children) || 0);
    var label = adults + ' ' + plural(adults, 'взрослый', 'взрослых', 'взрослых');
    if (children > 0) label += ' и ' + children + ' ' + plural(children, 'ребёнок', 'ребёнка', 'детей');
    return label;
  }

  function roomLabel(value) {
    var raw = textValue(value);
    if (!raw) return '';
    var key = raw.toLowerCase().replace(/[._-]+/g, ' ').replace(/\s+/g, ' ').trim();
    var labels = {
      'standard': 'Стандартный номер',
      'standard room': 'Стандартный номер',
      'std': 'Стандартный номер',
      'std room': 'Стандартный номер',
      'std room without air conditioner': 'Стандартный номер без кондиционера'
    };
    return labels[key] || raw;
  }

  function placementLabel(value) {
    var raw = textValue(value);
    if (!raw) return '';
    var labels = {
      'SGL': 'Одноместное',
      'DBL': 'Двухместное',
      'TRPL': 'Трёхместное',
      'QUAD': 'Четырёхместное'
    };
    return labels[raw.toUpperCase()] || raw;
  }

  function guestLabel() {
    var form = document.getElementById('tourSearch');
    var adults = Number(form && form.elements && form.elements.count_people && form.elements.count_people.value || 2) || 2;
    var children = Number(form && form.elements && form.elements.child_count && form.elements.child_count.value || 0) || 0;
    return guestCountLabel(adults, children);
  }

  function cardFacts(hotel) {
    var tour = representativeTour(hotel);
    if (!tour) return [];
    var facts = [];
    if (tour.date) facts.push(['Вылет', formatTourDate(tour.date)]);
    if (tour.nights) facts.push(['Ночей', String(tour.nights)]);
    var meal = mealLabel(tour.meal);
    if (meal) facts.push(['Питание', meal]);
    if (tour.isCharter === true) facts.push(['Рейс', 'Чартер']);
    return facts.slice(0, 4);
  }

  function decorateDecisionCopy(card, hotel) {
    var rating = Number(hotel && hotel.rating || 0);
    var ratingNode = card.querySelector('.hotel-decision-rating');
    if (ratingNode && rating > 0) {
      ratingNode.textContent = '★ ' + rating.toLocaleString('ru-RU', { maximumFractionDigits: 1 }) + '/5';
      ratingNode.setAttribute('aria-label', 'Оценка отеля ' + rating.toLocaleString('ru-RU', { maximumFractionDigits: 1 }) + ' из 5');
    }
    var sea = Number(hotel && hotel.seaDistance || 0);
    var seaNode = card.querySelector('.hotel-decision-sea');
    if (seaNode && sea > 0) seaNode.textContent = 'До моря ' + new Intl.NumberFormat('ru-RU').format(sea) + ' м';
  }

  function decorateHeading(bodyNode, hotel) {
    var title = bodyNode.querySelector('.hotel-title');
    if (!title || title.parentElement.classList.contains('search3-hotel-heading')) return;
    var heading = document.createElement('div');
    heading.className = 'search3-hotel-heading';
    title.parentNode.insertBefore(heading, title);
    heading.appendChild(title);
    var category = Number(hotel && hotel.category || 0);
    if (category > 0) {
      var stars = document.createElement('span');
      stars.className = 'search3-hotel-category';
      stars.textContent = category + '★';
      stars.setAttribute('aria-label', 'Категория отеля ' + category + ' звёзд');
      heading.appendChild(stars);
    }
  }

  function decoratePriceContext(bodyNode, hotel) {
    var tour = representativeTour(hotel);
    var bestOffer = bodyNode.querySelector('.hotel-best-offer');
    var label = bestOffer && bestOffer.querySelector(':scope > small:not(.hotel-price-context)');
    var price = bestOffer && bestOffer.querySelector('.hotel-price');
    var context = bestOffer && bestOffer.querySelector('.hotel-price-context');
    if (label) label.textContent = 'За весь тур';
    if (price) price.setAttribute('aria-label', (price.textContent || '').replace(/\s+/g, ' ').trim() + ', за тур на ' + guestLabel());
    if (!tour || !context) return;
    context.innerHTML = '<span>' + safe(guestLabel()) + '</span>';
  }

  function ensureTourListHead(toursNode, hotel) {
    if (!toursNode || toursNode.querySelector('.search3-tour-list-head')) return;
    var count = Array.isArray(hotel && hotel.tours) ? hotel.tours.length : toursNode.querySelectorAll('.tour-row').length;
    var head = document.createElement('div');
    head.className = 'search3-tour-list-head';
    head.innerHTML = '<div><strong>Лучшее предложение</strong><span>Сравните дату, номер, питание и цену</span></div>'
      + '<b>' + count + ' ' + tourWord(count) + '</b>';
    toursNode.insertBefore(head, toursNode.firstChild);
  }

  function decorateTourRows(toursNode, hotel) {
    if (!toursNode) return;
    ensureTourListHead(toursNode, hotel);
    toursNode.querySelectorAll('.tour-row').forEach(function (row) {
      if (row.dataset.search3OfferV2 === '1') return;
      row.dataset.search3OfferV2 = '1';

      var date = row.querySelector('.tour-meta > strong');
      if (date) date.textContent = formatTourDate(date.textContent);

      row.querySelectorAll('.tour-fact').forEach(function (fact) {
        var label = fact.querySelector('small');
        var value = fact.querySelector('b');
        if (!label || !value) return;
        var name = textValue(label.textContent).toLowerCase();
        if (name === 'питание') value.textContent = mealLabel(value.textContent);
        if (name === 'номер') value.textContent = roomLabel(value.textContent);
        if (name === 'размещение') value.textContent = placementLabel(value.textContent);
      });

      var action = row.querySelector('.tour-action');
      var price = action && action.querySelector(':scope > b');
      var productionChoice = action && action.querySelector('button[data-tid]');
      if (productionChoice && !productionChoice.dataset.search3ProductionLabel) {
        productionChoice.dataset.search3ProductionLabel = (productionChoice.textContent || '').replace(/\s+/g, ' ').trim();
      }
      if (action && price) {
        var scope = document.createElement('small');
        scope.className = 'search3-tour-price-scope';
        scope.textContent = 'За весь тур';
        action.insertBefore(scope, price);
        price.setAttribute('aria-label', (price.textContent || '').replace(/\s+/g, ' ').trim() + ', за весь тур');
      }
    });
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
    decorateHeading(bodyNode, hotel);
    decorateDecisionCopy(card, hotel);
    decoratePriceContext(bodyNode, hotel);
    decorateTourRows(tours, hotel);
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
        if (event.matches) window.setTimeout(mountMobileToolbar, 0);
      });
    }
  }

  window.Search3CandidateResultsV1 = Object.freeze({
    version: 3,
    status: 'REFERENCE_IMPLEMENTATION_IN_PROGRESS',
    approvedPixelsCompared: false,
    partyLabel: guestCountLabel,
    formatDate: formatTourDate,
    mealLabel: mealLabel,
    roomLabel: roomLabel,
    placementLabel: placementLabel,
    decorate: decorate,
    collapseAll: collapseAll
  });
})();
