(function () {
  'use strict';
  function capture(snapshot, generation, labels) {
    if (!snapshot || !Number.isInteger(generation) || generation < 1) return null;
    const params = {};
    Object.keys(snapshot).forEach(key => { params[key] = Array.isArray(snapshot[key]) ? snapshot[key].slice() : snapshot[key]; });
    return { generation, params, labels: Object.assign({}, labels || {}) };
  }
  function isCurrent(run, lifecycle) {
    return !!run && !!lifecycle && run.generation === lifecycle.generation && !lifecycle.dirty;
  }
  function errorMessage(code) {
    const messages = {
      search_not_supported: 'ANEX пока не поддерживает эти условия поиска.',
      invalid_request: 'Проверьте даты, количество ночей и состав туристов для поиска ANEX.',
      supplier_conditions_rejected: 'ANEX не принял выбранные условия поиска. Измените параметры и повторите поиск.',
      rate_limited: 'Достигнут лимит запросов ANEX. Подождите минуту перед следующим поиском.',
      supplier_timeout: 'Расчёт ANEX занял слишком много времени. Повторите поиск позже.'
    };
    return typeof messages[code] === 'string' ? messages[code] : 'Не удалось получить предложения ANEX. Повторите поиск позже.';
  }
  function dateRangeLabel(range) {
    if (!range || ![range.from, range.to].every(value => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value))) return '';
    const begin = new Date(range.from + 'T00:00:00Z'), end = new Date(range.to + 'T00:00:00Z');
    if (!Number.isFinite(+begin) || !Number.isFinite(+end) || begin.toISOString().slice(0, 10) !== range.from
      || end.toISOString().slice(0, 10) !== range.to || end < begin || end - begin > 6 * 86400000) return '';
    const format = value => value.split('-').reverse().join('.');
    return 'Вылеты ANEX: ' + format(range.from) + (range.to === range.from ? '' : ' — ' + format(range.to));
  }
  function validHotel(hotel) {
    return !!hotel && Number.isSafeInteger(hotel.local_id) && hotel.local_id > 0
      && typeof hotel.name === 'string' && hotel.name.length > 0 && hotel.name.length <= 300
      && Array.isArray(hotel.tours) && hotel.tours.length > 0 && hotel.tours.length <= 300
      && hotel.tours.every(tour => tour && tour.price && tour.price.currency === 'RUB'
        && typeof tour.price.amount === 'string' && /^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/.test(tour.price.amount)
        && Number(tour.price.amount) > 0 && typeof tour.checkin === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(tour.checkin)
        && Number.isInteger(tour.nights) && tour.nights > 0 && tour.nights <= 60);
  }
  function priceRank(value) {
    const number = Number(value);
    return Number.isFinite(number) && number > 0 ? number : Infinity;
  }
  function mealLabel(value) {
    const text = typeof value === 'string' ? value.trim() : '';
    const labels = { RO: 'Без питания', BB: 'Завтраки', HB: 'Полупансион', FB: 'Полный пансион',
      AI: 'Всё включено', ALL: 'Всё включено', 'ALL INCLUSIVE': 'Всё включено',
      UAI: 'Ультра всё включено', 'ULTRA ALL INCLUSIVE': 'Ультра всё включено',
      'AI-WITHOUT ALCOHOL': 'Всё включено без алкоголя', 'AI WITHOUT ALCOHOL': 'Всё включено без алкоголя' };
    return labels[text.toUpperCase()] || text;
  }
  function filterItem(hotel) {
    return { id: hotel.local_id, category: hotel.category, rating: hotel.rating, seaDistance: null,
      price: Number(hotel.tours[0].price.amount), tours: hotel.tours.map(tour => ({
        price: Number(tour.price.amount), meal: { name: mealLabel(tour.meal) },
        anex: tour
      })) };
  }
  function compareCards(a, b, mode) {
    if (mode === 'rating' || mode === 'stars') {
      const key = mode === 'stars' ? 'category' : 'rating';
      const difference = Number(b[key] || 0) - Number(a[key] || 0);
      if (difference) return difference;
    }
    if (mode === 'sea') {
      const seaRank = value => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value)) && Number(value) >= 0 ? Number(value) : Infinity;
      const distance = seaRank(a.seaDistance) - seaRank(b.seaDistance);
      if (distance) return distance;
    }
    return priceRank(a.price) - priceRank(b.price) || String(a.id).localeCompare(String(b.id));
  }
  window.AnyTourAnexSearch3 = { capture, isCurrent, validHotel, errorMessage, dateRangeLabel, compareCards, filterItem, mealLabel, version: 1 };
  if (!/^\/_preview\/search3-anex-candidate\//.test(window.location.pathname)) return;
  const script = document.currentScript;
  if (!script || !script.src) return;
  const endpoint = new URL('api-anex-search3-preview.php', script.src);
  if (endpoint.origin !== window.location.origin || !/^\/_preview\/search3-anex-candidate\//.test(endpoint.pathname)) return;
  const results = document.getElementById('results'), form = document.getElementById('tourSearch');
  if (!results || !form || !window.fetch) return;
  const panelAnchor = (typeof results.closest === 'function' && results.closest('.results-layout')) || results;
  let active = null, controller = null, lastGeneration = 0, hotels = [], message = '', dates = '', panel = null;
  let tvItems = [], tvCards = [], openHotels = new Set(), ownPresentation = null, renderQueued = false;
  let calendarBox = null, calendarObserver = null;
  let sourceMode = 'all';
  const sourceChoices = [
    ['all', 'Все отели'], ['anex', 'С предложениями ANEX API'],
    ['tourvisor', 'С предложениями Tourvisor'], ['both', 'В обоих источниках']
  ];
  const replacedText = new Map(), hiddenEmpty = new Map(), sourceDisplay = new Map();
  const money = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 });
  function node(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = String(text);
    return element;
  }
  const style = node('style');
  style.textContent = 'body.search3-candidate #anexSearch3Results.anex-search3-panel{display:block!important;grid-column:1/-1;min-width:0}.anex-search3-panel{margin:20px 0;min-width:0}.anex-search3-panel h2{font:inherit;font-weight:700;font-size:20px;margin:0 0 12px}.anex-search3-status{color:#566176;font-size:14px;line-height:1.5}.anex-search3-hotel{border:1px solid #dbe2ed;border-radius:16px;background:#fff;padding:16px;margin:12px 0;overflow-wrap:anywhere}.anex-search3-hotel h3{font:inherit;font-size:18px;font-weight:700;margin:0 0 6px}.anex-search3-place{color:#566176;font-size:14px;margin:0 0 12px}.anex-search3-offers{margin:12px 16px;border-top:1px solid #dbe2ed;padding-top:12px;min-width:0;overflow-wrap:anywhere}.anex-search3-hotel .anex-search3-offers{margin:0}.anex-search3-offers summary{cursor:pointer;min-height:44px;display:list-item;align-content:center;color:#2743cb;font-weight:700;line-height:1.5;padding:8px 0}.anex-search3-offer{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px 20px;padding:12px 0;border-top:1px solid #edf0f5;line-height:1.5;font-size:14px}.anex-search3-offer p{margin:0;flex:1 1 230px}.anex-search3-offer strong{white-space:nowrap}.anex-search3-note{color:#566176;font-size:12px;line-height:1.5;margin:8px 0}';
  document.head.appendChild(style);
  style.textContent += '\n.anex-search3-source-filter{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;margin-top:12px;font-size:14px;font-weight:600}.anex-search3-source-filter select{box-sizing:border-box;max-width:100%;min-width:0;min-height:44px;padding:10px 32px 10px 12px;border:1px solid #dbe2ed;border-radius:8px;background:#fff;color:#18243b;font:inherit}.anex-search3-source-filter select:focus-visible{outline:2px solid #2743cb;outline-offset:2px}body.search3-candidate #results .hotel-card.anex-search3-source-hidden{display:none!important}@media(max-width:600px){.anex-search3-source-filter select{width:100%}}';
  const sourceFilter = node('label', 'anex-search3-source-filter', 'Отели в списке');
  const sourceSelect = node('select');
  sourceSelect.id = 'anexSearch3SourceFilter';
  sourceSelect.setAttribute('aria-controls', 'results');
  sourceChoices.forEach(([value, label]) => {
    const option = node('option', '', label); option.value = value; sourceSelect.appendChild(option);
  });
  sourceSelect.value = sourceMode;
  sourceFilter.appendChild(sourceSelect);
  sourceSelect.addEventListener('change', () => {
    sourceMode = sourceChoices.some(([value]) => value === sourceSelect.value) ? sourceSelect.value : 'all';
    queueRender();
  });
  style.textContent += '\n.anex-search3-tv-price-label{display:block!important;font-size:11px;font-weight:500;line-height:1.4;color:#566176}';
  style.textContent += '\nbody.search3-candidate #results .anex-search3-hotel{display:block!important;padding:0!important;width:100%;min-width:0;grid-column:1/-1}.anex-search3-identity{padding:18px 18px 4px}.anex-search3-identity h3{margin:0 0 6px;font-size:18px;line-height:1.3}.anex-search3-source{display:inline-flex;flex-wrap:wrap;align-items:center;gap:5px 10px;padding:7px 10px;border-radius:8px;background:#edf2ff;color:#2743cb;font-size:13px;line-height:1.4;font-weight:700;margin:8px 0;max-width:100%;overflow-wrap:anywhere}.anex-search3-source strong{white-space:nowrap}.anex-search3-offers h4{margin:0 0 8px;font-size:15px;color:#2743cb}.anex-search3-tv-source{margin:12px 16px 0;font-size:14px;color:#566176}.anex-search3-hotel .anex-search3-offers{margin:8px 18px 12px}.anex-search3-panel{padding:10px 0}.anex-search3-panel h2{font-size:16px;margin-bottom:4px}.anex-search3-panel p{margin:4px 0}.anex-search3-offer .anex-search3-source{display:block;background:none;padding:0;margin:0 0 4px;font-size:12px}.anex-search3-hotel .anex-search3-place{margin-bottom:4px}@media(max-width:600px){.anex-search3-identity{padding:14px 14px 4px}.anex-search3-hotel .anex-search3-offers{margin:6px 14px 10px}.anex-search3-offer{gap:6px}.anex-search3-offer p{flex-basis:100%}}';
  function replaceText(element, value) {
    if (!element) return;
    const previous = replacedText.get(element);
    replacedText.set(element, { before: previous && element.textContent === previous.after ? previous.before : element.textContent, after: value });
    element.textContent = value;
  }
  function clear() {
    sourceDisplay.forEach((before, card) => {
      if (card.style.getPropertyValue('display') === 'none' && card.style.getPropertyPriority('display') === 'important') {
        if (before.value) card.style.setProperty('display', before.value, before.priority);
        else card.style.removeProperty('display');
      }
    });
    sourceDisplay.clear();
    results.querySelectorAll('.anex-search3-source-hidden').forEach(card => card.classList.remove('anex-search3-source-hidden'));
    results.querySelectorAll('.anex-search3-offers').forEach(details => {
      if (details.tagName !== 'DETAILS') return;
      const id = Number(details.getAttribute('data-anex-search3-row'));
      if (details.open) openHotels.add(id); else openHotels.delete(id);
    });
    results.querySelectorAll('[data-anex-search3-row]').forEach(row => row.remove());
    results.querySelectorAll('[data-anex-search3-card]').forEach(card => card.remove());
    replacedText.forEach((value, element) => { if (element.textContent === value.after) element.textContent = value.before; });
    replacedText.clear();
    hiddenEmpty.forEach((hidden, element) => { element.hidden = hidden; });
    hiddenEmpty.clear();
    if (ownPresentation && !results.querySelector('.hotel-card')) {
      ownPresentation.classes.forEach(name => document.body.classList.remove(name));
      if (ownPresentation.tools) ownPresentation.tools.hidden = ownPresentation.hidden;
    }
    ownPresentation = null;
    if (panel) panel.remove();
    panel = null;
  }
  function price(tour) { return (tour.kind === 'group_minimum' ? 'от ' : '') + money.format(Number(tour.price.amount)) + ' ₽'; }
  function sourceBadge(hotel) {
    const badge = node('div', 'anex-search3-source');
    badge.setAttribute('data-anex-search3-row', String(hotel.local_id));
    badge.appendChild(node('span', '', 'ANEX API'));
    badge.appendChild(node('strong', '', price(hotel.tours[0])));
    return badge;
  }
  function offers(hotel, embedded = false) {
    const details = node(embedded ? 'section' : 'details', 'anex-search3-offers');
    details.setAttribute('data-anex-search3-row', String(hotel.local_id));
    details.appendChild(node(embedded ? 'h4' : 'summary', '', embedded ? 'Предложения ANEX API' : 'Показать предложения ANEX API · ' + price(hotel.tours[0])));
    if (!embedded) {
      details.open = openHotels.has(hotel.local_id);
    }
    hotel.tours.forEach(tour => {
      const row = node('div', 'anex-search3-offer');
      const date = tour.checkin.split('-').reverse().join('.');
      row.appendChild(node('p', '', [date, tour.nights + ' ноч.', mealLabel(tour.meal), tour.room,
        tour.adults + ' взр.' + (tour.children ? ', ' + tour.children + ' дет.' : '')].filter(Boolean).join(' · ')));
      row.appendChild(node('strong', '', price(tour)));
      details.appendChild(row);
    });
    details.appendChild(node('p', 'anex-search3-note', 'Цена из поиска ANEX. Включение топливного сбора уточняется; итоговую стоимость подтвердит менеджер.'));
    return details;
  }
  function attach(card, hotel) {
    const body = card.querySelector('.hotel-body') || card;
    body.appendChild(sourceBadge(hotel));
    const box = card.querySelector('.hotel-tours');
    if (box) {
      const origin = node('h4', 'anex-search3-tv-source', 'Предложения через Tourvisor');
      origin.setAttribute('data-anex-search3-row', String(hotel.local_id));
      box.insertBefore(origin, box.firstElementChild || null);
      box.appendChild(offers(hotel, true));
      const label = card.querySelector('.hotel-best-offer');
      if (label) {
        const source = node('span', 'anex-search3-tv-price-label', 'Через Tourvisor');
        source.setAttribute('data-anex-search3-row', String(hotel.local_id));
        label.insertBefore(source, label.firstElementChild || null);
      }
      const copy = card.querySelector('.search3-hotel-action__copy');
      const tv = tvItems.find(item => String(item.id) === String(hotel.local_id));
      if (copy && tv && Array.isArray(tv.tours)) replaceText(copy.querySelector('strong'),
        'Предложений: ' + (tv.tours.length + hotel.tours.length) + ' ');
    } else card.appendChild(offers(hotel));
  }
  function standalone(hotel) {
    const card = node('article', 'hotel-card anex-search3-hotel');
    card.setAttribute('data-anex-search3-card', String(hotel.local_id));
    card.setAttribute('data-hotel-id', String(hotel.local_id));
    card.setAttribute('data-search3-results-v1', '1');
    const identity = node('div', 'anex-search3-identity');
    identity.appendChild(node('h3', '', hotel.name + (hotel.category ? ' ' + hotel.category + '★' : '')));
    identity.appendChild(node('p', 'anex-search3-place', [hotel.country, hotel.region].filter(Boolean).join(' · ')));
    identity.appendChild(sourceBadge(hotel));
    card.appendChild(identity);
    card.appendChild(offers(hotel));
    return card;
  }
  function localFilterNotice() {
    const lifecycle = window.V2SearchLifecycle;
    if (lifecycle && typeof lifecycle.params === 'function') {
      const current = lifecycle.params();
      if (Object.keys(active.params).some(key => JSON.stringify(active.params[key]) !== JSON.stringify(current[key]))) {
        return 'Обновите поиск, чтобы получить предложения ANEX по выбранным условиям.';
      }
    }
    const filter = window.DS2ResultsFilters, state = filter && filter.state;
    const range = document.querySelector('[data-ds2-price]');
    if ((!filter || typeof filter.filteredHotel !== 'function') && ((state && (state.stars || state.rating || state.meal || state.seaMax))
      || (range && Number(range.value) < Number(range.max)))) {
      return 'Для просмотра предложений ANEX сбросьте фильтры результатов.';
    }
    return '';
  }
  function updateSupplemental() {
    const filter = window.DS2ResultsFilters;
    if (filter && typeof filter.setSupplementalItems === 'function') filter.setSupplementalItems(hotels.map(filterItem));
  }
  function filteredHotel(hotel) {
    const filter = window.DS2ResultsFilters;
    if (!filter || typeof filter.filteredHotel !== 'function') return hotel;
    const kept = filter.filteredHotel(filterItem(hotel));
    return kept && kept.tours.length ? Object.assign({}, hotel, { tours: kept.tours.map(tour => tour.anex) }) : null;
  }
  function queueRender() {
    if (renderQueued) return;
    renderQueued = true;
    Promise.resolve().then(() => { renderQueued = false; render(); });
  }
  function labelTourvisorProgress() {
    const status = document.getElementById('status');
    if (!status) return;
    ['.search-progress-head', '.search-progress-error-copy', '.search-progress-empty-copy'].forEach(selector => {
      const box = status.querySelector(selector), title = box && box.querySelector('strong');
      if (title && (selector !== '.search-progress-empty-copy' || title.textContent === 'По этим условиям туров не нашли')) {
        replaceText(title, 'Tourvisor · ' + title.textContent);
      }
    });
  }
  function labelCalendar() {
    if (!isCurrent(active, window.V2SearchLifecycle) || !calendarBox) return;
    const title = calendarBox.querySelector('#search3PriceCalendarTitle') || calendarBox.querySelector('#currentPriceCalendarTitle');
    if (title && title.textContent !== 'Календарь цен Tourvisor') replaceText(title, 'Календарь цен Tourvisor');
  }
  function watchCalendar() {
    const box = document.getElementById('currentPriceCalendar');
    if (box !== calendarBox) {
      if (calendarObserver) calendarObserver.disconnect();
      calendarObserver = null; calendarBox = box;
      // The existing presentation owner creates its visible title after the search event.
      if (box && typeof MutationObserver === 'function') {
        calendarObserver = new MutationObserver(labelCalendar);
        calendarObserver.observe(box, { childList: true, subtree: true });
      }
    }
    labelCalendar();
  }
  function render() {
    const sourceFocused = document.activeElement === sourceSelect;
    clear();
    if (!isCurrent(active, window.V2SearchLifecycle)) return;
    labelTourvisorProgress();
    watchCalendar();
    const filterNotice = localFilterNotice();
    const cards = new Map();
    results.querySelectorAll('.hotel-card[data-hotel-id]').forEach(card => {
      const id = String(card.dataset.hotelId || '');
      // Duplicate local cards are ambiguous; keep those supplier rows separate.
      cards.set(id, cards.has(id) ? null : card);
    });
    const tv = new Map(tvItems.map(item => [String(item.id), item]));
    const ranked = [];
    results.querySelectorAll('.hotel-card[data-hotel-id]').forEach((card, index) => {
      const id = String(card.dataset.hotelId), item = tv.get(id) || {};
      ranked.push({ id, card, tourvisor: true, anex: false, price: item.price, category: item.category, rating: item.rating, seaDistance: item.seaDistance, index });
    });
    panel = node('section', 'anex-search3-panel');
    panel.id = 'anexSearch3Results';
    // Inline priority beats the existing layered Search3 section whitelist.
    panel.style.setProperty('display', 'block', 'important');
    panel.style.setProperty('grid-column', '1 / -1');
    panel.setAttribute('aria-label', 'Источники предложений');
    panel.appendChild(node('h2', '', 'Tourvisor и ANEX API'));
    if (dates) panel.appendChild(node('p', 'anex-search3-status', dates));
    const status = node('p', 'anex-search3-status', filterNotice || message);
    status.setAttribute('role', 'status');
    panel.appendChild(status);
    let added = 0, merged = 0, ambiguous = 0;
    (filterNotice ? [] : hotels).filter(validHotel).map(filteredHotel).filter(Boolean).forEach(hotel => {
      const existing = cards.get(String(hotel.local_id));
      if (cards.has(String(hotel.local_id)) && !existing) { ambiguous++; return; }
      if (existing) {
        attach(existing, hotel);
        const item = ranked.find(row => row.card === existing);
        item.anex = true;
        item.price = Math.min(priceRank(item.price), priceRank(hotel.tours[0].price.amount));
        merged++; return;
      }
      const card = standalone(hotel);
      ranked.push({ id: String(hotel.local_id), card, tourvisor: false, anex: true, price: hotel.tours[0].price.amount,
        category: hotel.category, rating: hotel.rating, seaDistance: null });
      added++;
    });
    if (added || merged) {
      const mode = (document.getElementById('sortResults') || {}).value || 'price';
      ranked.sort((a, b) => compareCards(a, b, mode));
      ranked.forEach(item => results.appendChild(item.card));
      results.querySelectorAll('.empty').forEach(element => { hiddenEmpty.set(element, element.hidden); element.hidden = true; });
      const tools = document.getElementById('resultsTools');
      if (!cards.size) ownPresentation = {
        classes: ['search3-has-results', 'search3-results-active'].filter(name => !document.body.classList.contains(name)),
        tools, hidden: tools ? tools.hidden : false
      };
      document.body.classList.add('search3-has-results', 'search3-results-active');
      if (tools) tools.hidden = false;
      if (tools) replaceText(tools.querySelector('strong'), 'Найдено отелей: ' + ranked.length);
      replaceText(document.getElementById('resultSummary'), 'Tourvisor: ' + cards.size + ' · ANEX API: ' + (added + merged));
      replaceText(document.querySelector('[data-ds2-filter-count]'), String(ranked.length));
      replaceText(document.querySelector('[data-ds2-filter-word]'), 'в выдаче');
      status.textContent = 'Отелей в выдаче: ' + ranked.length + '. Через Tourvisor: ' + cards.size
        + ', через ANEX API: ' + (added + merged) + '. В обоих источниках: ' + merged + '.'
        + (ambiguous ? ' Часть предложений ожидает уточнения связи.' : '');
    } else {
      // Restore the original source order when ANEX is hidden by changed filters.
      tvCards.filter(card => card.parentNode === results).forEach(card => results.appendChild(card));
      if (!filterNotice && hotels.length && !ambiguous) status.textContent = 'По выбранным фильтрам предложений ANEX нет. Измените фильтры или сбросьте их.';
    }
    const counts = { all: ranked.length, anex: added + merged, tourvisor: ranked.filter(item => item.tourvisor).length, both: merged };
    sourceChoices.forEach(([value, label], index) => { sourceSelect.children[index].textContent = label + ' · ' + counts[value]; });
    sourceSelect.value = sourceMode;
    panel.appendChild(sourceFilter);
    let visible = 0;
    ranked.forEach(item => {
      const shown = sourceMode === 'all' || (sourceMode === 'both' ? item.anex && item.tourvisor : item[sourceMode]);
      if (shown) visible++; else {
        item.card.classList.add('anex-search3-source-hidden');
        sourceDisplay.set(item.card, { value: item.card.style.getPropertyValue('display'), priority: item.card.style.getPropertyPriority('display') });
        // Inline priority also wins over the existing layered !important card layout.
        item.card.style.setProperty('display', 'none', 'important');
      }
    });
    if (sourceMode !== 'all') {
      // Keep both offer sections on shared cards; this selects hotels, not a new supplier search.
      replaceText(document.querySelector('#resultsTools strong'), 'Найдено отелей: ' + visible);
      replaceText(document.querySelector('[data-ds2-filter-count]'), String(visible));
      replaceText(document.querySelector('[data-ds2-filter-word]'), 'в выдаче');
      const notice = filterNotice || (!hotels.length ? message : '');
      status.textContent = visible ? 'Показано отелей: ' + visible + ' из ' + ranked.length + '.'
        : 'Для выбранного источника отелей нет. Выберите «Все отели» или измените фильтры.';
      if (notice) status.textContent += ' ' + notice;
    }
    panelAnchor.parentNode.insertBefore(panel, panelAnchor);
    if (sourceFocused) sourceSelect.focus({ preventScroll: true });
  }
  function labels() {
    const out = {};
    ['from', 'country'].forEach(name => {
      const select = form.elements[name], option = select && select.options && select.options[select.selectedIndex];
      if (option) out[name] = String(option.textContent || '').slice(0,180);
    });
    return out;
  }
  async function start() {
    const lifecycle = window.V2SearchLifecycle;
    if (lifecycle && !lifecycle.dirty && lifecycle.snapshot && lifecycle.generation === lastGeneration) return;
    if (controller) controller.abort();
    controller = null;
    active = null; hotels = []; message = ''; dates = ''; sourceMode = 'all'; clear(); openHotels.clear();
    updateSupplemental();
    tvItems = []; tvCards = [];
    const existing = window.V2Results && window.V2Results.state;
    if (existing && Array.isArray(existing.items)) tvItems = existing.items.slice();
    tvCards = Array.from(results.querySelectorAll('.hotel-card[data-hotel-id]'));
    if (!lifecycle || lifecycle.dirty || !lifecycle.snapshot || lifecycle.generation === lastGeneration) return;
    const run = capture(lifecycle.snapshot, lifecycle.generation, labels());
    if (!run) return;
    active = run; lastGeneration = run.generation;
    controller = new AbortController();
    const signal = controller.signal;
    message = 'Ищем предложения ANEX…'; render();
    const timeout = setTimeout(() => { if (isCurrent(run, window.V2SearchLifecycle) && controller) controller.abort(); }, 90000);
    try {
      const response = await window.fetch(endpoint.href, { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(run), signal });
      const payload = await response.json();
      if (!isCurrent(run, window.V2SearchLifecycle) || active !== run) return;
      if (!response.ok || !payload.ok) {
        message = errorMessage(payload.error);
      } else if (payload.data && payload.data.generation === run.generation && payload.data.provider === 'anex' && Array.isArray(payload.data.hotels)) {
        dates = dateRangeLabel(payload.data.date_range);
        const seen = new Set();
        hotels = payload.data.hotels.slice(0, 300).filter(hotel => {
          if (!validHotel(hotel) || seen.has(hotel.local_id)) return false;
          seen.add(hotel.local_id); return true;
        });
        updateSupplemental();
        message = hotels.length ? 'Найдено отелей: ' + hotels.length
          : payload.data.external_search_pending ? 'ANEX продолжает расчёт. Повторите поиск позже.' : 'Подходящих предложений ANEX пока нет.';
      } else message = errorMessage(null);
      render();
    } catch (error) {
      if (isCurrent(run, window.V2SearchLifecycle) && active === run) {
        message = errorMessage(null); render();
      }
    } finally { clearTimeout(timeout); }
  }
  window.addEventListener('v2:search-reset', start);
  window.addEventListener('v2:results-rendered', event => {
    if (event && event.detail && Array.isArray(event.detail.items)) tvItems = event.detail.items.slice();
    tvCards = Array.from(results.querySelectorAll('.hotel-card[data-hotel-id]')).filter(card => !card.getAttribute('data-anex-search3-card'));
    queueRender();
  });
  const sort = document.getElementById('sortResults');
  if (sort) sort.addEventListener('change', queueRender);
  const rail = document.querySelector('.results-filter-rail');
  if (rail) ['input', 'change', 'click'].forEach(event => rail.addEventListener(event, action => {
    if (action.type === 'click' && action.target && action.target.closest('[data-ds2-reset]')) sourceMode = 'all';
    queueRender();
  }));
  ['v2:search-started', 'v2:search-progress', 'v2:search-complete', 'v2:search-continue-started',
    'v2:search-continue-requested', 'v2:search-continue-progress', 'v2:search-continued',
    'v2:search-continue-error'].forEach(event => window.addEventListener(event, queueRender));
  document.addEventListener('click', event => {
    if (event.target && event.target.closest && event.target.closest('.tour-more-toggle')) setTimeout(render, 0);
  }, true);
  window.addEventListener('v2:search-error', () => { if (!isCurrent(active, window.V2SearchLifecycle)) clear(); else queueRender(); });
  // The addon may be loaded after a URL-triggered initial search has started.
  if (window.V2SearchLifecycle && window.V2SearchLifecycle.snapshot) start();
}());
