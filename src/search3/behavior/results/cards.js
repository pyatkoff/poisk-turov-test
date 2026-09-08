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

  function decorateSalesLeader(card, bodyNode, hotel) {
    var image = card.querySelector('.hotel-photo img');
    if (image) {
      var fallback = function () {
        var box = image.closest('.hotel-photo');
        if (!box || box.querySelector('.photo-placeholder')) return;
        var placeholder = document.createElement('div');
        placeholder.className = 'photo-placeholder';
        placeholder.textContent = 'ANYTOUR';
        image.replaceWith(placeholder);
      };
      image.addEventListener('error', fallback, { once: true });
      if (image.complete && image.naturalWidth === 0) fallback();
    }
    if (!hotel || hotel.salesLeader !== true) return;
    var title = bodyNode.querySelector('.hotel-title');
    if (!title) return;
    var badge = document.createElement('span');
    badge.className = 'sales-leader-badge';
    badge.textContent = 'Лидер продаж';
    badge.setAttribute('aria-label', 'Лидер продаж AnyTour');
    title.before(badge);
    card.classList.add('is-sales-leader');
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

  function decorateTourRows(toursNode, hotel) {
    if (!toursNode) return;
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
        productionChoice.textContent = 'Проверить тур';
        productionChoice.setAttribute('aria-label', date && date.textContent ? 'Проверить тур на ' + date.textContent : 'Проверить выбранный тур');
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
      button.textContent = button.dataset.search3ShowLabel || 'Показать туры';
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
    decorateSalesLeader(card, bodyNode, hotel);
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
    var showLabel = 'Показать ' + count + ' ' + tourWord(count);
    var action = document.createElement('div');
    action.className = 'search3-hotel-action';
    action.innerHTML = '<button type="button" class="search3-show-tours" aria-expanded="false" data-search3-show-label="'
      + safe(showLabel) + '">' + safe(showLabel) + '</button>';
    bodyNode.appendChild(action);

    if (!tours.id) tours.id = 'search3-hotel-tours-' + safe(String(card.dataset.hotelId || 'result'));
    action.querySelector('.search3-show-tours').setAttribute('aria-controls', tours.id);
    tours.hidden = true;
  }

  function decorate(items) {
    hotelsById = new Map((Array.isArray(items) ? items : []).map(function (hotel) {
      return [hotelId(hotel), hotel];
    }));
    body.classList.toggle('search3-results-active', hotelsById.size > 0 || !!document.querySelector('.results-filter-rail[data-s3-empty-results="1"]'));
    if (!hotelsById.size) { cancelMobileToolbar(); return; }
    results.querySelectorAll('.hotel-card').forEach(decorateCard);
    scheduleMobileToolbar();
  }
