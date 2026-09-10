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
  function hotelKey(hotel) { return Number.isSafeInteger(hotel.local_id) && hotel.local_id > 0 ? hotel.local_id : hotel.card_key; }
  function rowKey(value) { return /^andromeda:[A-Za-z0-9_-]+:[A-Za-z0-9_-]+$/.test(value || '') ? value : Number(value); }
  function validHotel(hotel) {
    return !!hotel && ((Number.isSafeInteger(hotel.local_id) && hotel.local_id > 0)
      || (hotel.local_id === null && hotel.provider === 'andromeda' && hotel.mapping_status === 'unresolved'
        && /^andromeda:[A-Za-z0-9_-]{1,128}:[A-Za-z0-9_-]{1,128}$/.test(hotel.card_key)))
      && typeof hotel.name === 'string' && hotel.name.length > 0 && hotel.name.length <= 4096
      && Array.isArray(hotel.tours) && hotel.tours.length > 0 && hotel.tours.length <= (hotel.tours.some(t => t && t.provider === 'andromeda') ? 10000 : 300)
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
  function supplierInfo(hotel) {
    const content = hotel && hotel.andromeda_content;
    if (!content || content.source !== 'andromeda') return null;
    const safe = value => {
      if (typeof value !== 'string' || value.length > 2048 || !/^https:\/\//.test(value)) return null;
      try { const u = new URL(value); return !u.username && !u.password && !u.port
        && /^[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}$/i.test(u.hostname)
        && !/\.(?:local|localhost|internal|lan)$/i.test(u.hostname) ? u.href : null; } catch (_) { return null; }
    };
    return { image_url: safe(content.image_url), hotel_url: safe(content.hotel_url) };
  }
  function catalogInfo(hotel) {
    const data = hotel && hotel.catalog;
    if (!data || data.hotel_id !== hotel.local_id || data.source !== 'tourvisor') return null;
    const text = (value, limit) => typeof value === 'string' ? value.trim().slice(0, limit) : '';
    let image = null;
    if (typeof data.image_url === 'string' && data.image_url.length <= 2048
      && /^https:\/\//i.test(data.image_url) && !/[\s\u0000-\u001f\u007f]/.test(data.image_url)) {
      try {
        const url = new URL(data.image_url);
        if (url.protocol === 'https:' && !url.username && !url.password && !url.port
          && /^[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}$/i.test(url.hostname)
          && !/\.(?:local|localhost|internal)$/i.test(url.hostname)) image = url.href;
      } catch (_) {}
    }
    return { image_url: image, description: text(data.description, 16000), address: text(data.address, 1000),
      subregion: text(data.subregion, 300), sea_distance: typeof data.sea_distance === 'number'
        && Number.isFinite(data.sea_distance) && data.sea_distance >= 0 && data.sea_distance <= 100000 ? data.sea_distance : null };
  }
  function filterItem(hotel) {
    const catalog = catalogInfo(hotel);
    return { id: hotelKey(hotel), category: hotel.category, rating: hotel.rating, seaDistance: catalog ? catalog.sea_distance : null,
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
  function pointSearchParams(run, id) {
    const p = run && run.params;
    if (!p || !Number.isSafeInteger(id) || id < 1 || p.currency !== 'RUB'
      || (p.hotelIds && p.hotelIds.length) || !p.dateFrom || !p.dateTo
      || !p.nightsFrom || !p.nightsTo || !p.departureId || !p.countryId) return null;
    const copy = capture(p, run.generation);
    return copy ? Object.assign(copy.params, { hotelIds: [id] }) : null;
  }
  function pointSearchHotel(list, id, params) {
    if (!Array.isArray(list) || list.length > 1) throw new Error('Unexpected hotel response');
    if (!list.length) return null;
    const h = list[0];
    if (!h || !['string', 'number'].includes(typeof h.id) || Number(h.id) !== id || !Array.isArray(h.tours) || h.tours.length > 1000
      || (h.country && h.country.id && Number(h.country.id) !== Number(params.countryId))) throw new Error('Unexpected hotel identity');
    const tours = h.tours.map(t => {
      const date = typeof t?.date === 'string' ? t.date.replace(/^(\d{2})\.(\d{2})\.(\d{4})$/, '$3-$2-$1') : '';
      const time = /^\d{4}-\d{2}-\d{2}$/.test(date) ? new Date(date + 'T00:00:00Z') : null;
      if (!time || !Number.isFinite(+time) || time.toISOString().slice(0, 10) !== date
        || date < params.dateFrom || date > params.dateTo || !Number.isInteger(Number(t.nights))
        || Number(t.nights) < Number(params.nightsFrom) || Number(t.nights) > Number(params.nightsTo)
        || !['string', 'number'].includes(typeof t.price) || !Number.isFinite(Number(t.price)) || Number(t.price) <= 0 || Number(t.price) > 1e12
        || (t.currency !== undefined && t.currency !== 'RUB')) throw new Error('Unexpected tour conditions');
      return Object.assign({}, t);
    }).sort((a, b) => Number(a.price) - Number(b.price));
    return tours.length ? Object.assign({}, h, { tours, price: Number(tours[0].price) }) : null;
  }
  function combineSources(pages) {
    const merged = new Map();
    Object.entries(pages).forEach(([provider, page]) => {
      if (!['anex', 'andromeda'].includes(provider)) return;
      page.filter(validHotel).forEach(hotel => {
        const key = hotelKey(hotel);
        const row = merged.get(key) || Object.assign({}, hotel, { tours: [] });
        if (provider === 'andromeda') {
          const incoming = supplierInfo(hotel), current = supplierInfo(row);
          if (incoming) row.andromeda_content = Object.assign({}, row.andromeda_content || hotel.andromeda_content, {
            source: 'andromeda', image_url: current?.image_url || incoming.image_url,
            hotel_url: current?.hotel_url || incoming.hotel_url
          });
        }
        const seen = new Set(row.tours.filter(t => t.offer_ref).map(t => t.provider + ':' + t.offer_ref));
        row.tours.push(...hotel.tours.filter(t => !t.offer_ref || !seen.has(provider + ':' + t.offer_ref)).map(t => Object.assign({}, t, { provider })));
        row.tours.sort((a,b) => Number(a.price.amount)-Number(b.price.amount));
        merged.set(key, row);
      });
    });
    return Array.from(merged.values());
  }
  function offerContext(tour) {
    const c = tour && tour.offer_context;
    return tour?.provider === 'andromeda' && c?.provider === 'andromeda'
      && /^[a-f0-9]{64}$/.test(c.search_ref) && /^offer_[a-f0-9]{64}$/.test(c.offer_ref)
      && c.offer_ref === tour.offer_ref && Number.isInteger(c.generation) && c.generation > 0
      && Number.isInteger(c.page) && c.page > 0 && c.page <= 1000 ? Object.assign({}, c) : null;
  }
  function sourceLabel(tour) { return tour.provider === 'andromeda' ? 'Андромеда' : 'ANEX API'; }
  window.AnyTourAnexSearch3 = { capture, isCurrent, validHotel, errorMessage, dateRangeLabel, compareCards, filterItem, mealLabel, pointSearchParams, pointSearchHotel, combineSources, hotelKey, offerContext, version: 2 };
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
  const openDescriptions = new Set(), failedImages = new Set();
  const pointChecks = new Map(), openPointOffers = new Set(), pointVisible = new Map();
  let pointPending = null, broadComplete = false, broadHotelIds = new Set();
  let calendarBox = null, calendarObserver = null;
  let sourceMode = 'all';
  const sourceChoices = [
    ['all', 'Все отели'], ['anex', 'С предложениями ANEX API'], ['andromeda', 'С предложениями Андромеды'],
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
  style.textContent = `
body.search3-candidate #anexSearch3Results.anex-search3-panel{display:block!important;grid-column:1/-1;min-width:0}
.anex-search3-detail{box-sizing:border-box;width:min(560px,calc(100% - 24px));max-height:85vh;overflow:auto;border:1px solid #dbe2ed;border-radius:16px;padding:24px;color:#344257}.anex-search3-detail::backdrop{background:rgba(20,32,50,.45)}
.anex-search3-panel{margin:20px 0;padding:10px 0;min-width:0}
.anex-search3-panel h2{font:inherit;font-weight:700;font-size:16px;margin:0 0 4px}
.anex-search3-panel p{margin:4px 0}
.anex-search3-status{color:#566176;font-size:14px;line-height:1.5}
body.search3-candidate #results .anex-search3-hotel{display:block!important;padding:0!important;width:100%;min-width:0;grid-column:1/-1;border:1px solid #dbe2ed;border-radius:16px;background:#fff;margin:12px 0;overflow:hidden;overflow-wrap:anywhere}
.anex-search3-header{display:grid;grid-template-columns:minmax(220px,34%) minmax(0,1fr);align-items:start}
.anex-search3-media{position:relative;aspect-ratio:4/3;width:100%;margin:0;background:#edf0f5;overflow:hidden}
.anex-search3-photo-empty{position:absolute;inset:0;display:grid;place-items:center;padding:16px;color:#69758a;font-size:14px}
.anex-search3-media img{position:absolute;inset:0;display:block;width:100%;height:100%;max-width:none;object-fit:cover}
.anex-search3-media figcaption{position:absolute;right:8px;bottom:8px;border-radius:4px;padding:3px 6px;background:rgba(20,32,50,.72);color:#fff;font-size:10px;line-height:1.4}
.anex-search3-identity{padding:18px;min-width:0}
.anex-search3-identity h3{font:inherit;margin:0 0 8px;font-size:18px;font-weight:700;line-height:1.3}
.anex-search3-place{color:#566176;font-size:14px;line-height:1.5;margin:0 0 8px}
.anex-search3-facts{color:#344257;font-size:13px;line-height:1.5;margin:8px 0}
.anex-search3-source{display:inline-flex;flex-wrap:wrap;align-items:center;gap:5px 10px;padding:7px 10px;border-radius:8px;background:#edf2ff;color:#2743cb;font-size:13px;line-height:1.4;font-weight:700;margin:8px 0;max-width:100%;overflow-wrap:anywhere}
.anex-search3-source strong{white-space:nowrap}
.anex-search3-about{font-size:14px;line-height:1.6;margin-top:4px}
.anex-search3-about p{margin:8px 0;white-space:pre-line}
.anex-search3-offers{margin:12px 16px;border-top:1px solid #dbe2ed;padding-top:12px;min-width:0;overflow-wrap:anywhere}
.anex-search3-hotel .anex-search3-offers{margin:0 18px 12px}
.anex-search3-offers summary,.anex-search3-about summary{cursor:pointer;min-height:44px;display:list-item;align-content:center;color:#2743cb;font-weight:700;line-height:1.5;padding:8px 0;box-sizing:border-box}
.anex-search3-offers h4{margin:0 0 8px;font-size:15px;color:#2743cb}
.anex-search3-offer{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px 20px;padding:12px 0;border-top:1px solid #edf0f5;line-height:1.5;font-size:14px}
.anex-search3-offer p{margin:0;flex:1 1 230px}
.anex-search3-offer strong{white-space:nowrap}
.anex-search3-offer .anex-search3-source{display:block;background:none;padding:0;margin:0 0 4px;font-size:12px}
.anex-search3-note{color:#566176;font-size:12px;line-height:1.5;margin:8px 0}
.anex-search3-tv-point{margin:0 18px 14px;min-width:0}
.anex-search3-tv-check{box-sizing:border-box;min-height:44px;max-width:100%;padding:10px 14px;border:1px solid #2743cb;border-radius:8px;background:#fff;color:#2743cb;font:inherit;font-size:14px;line-height:1.4;text-align:left;cursor:pointer}
.anex-search3-tv-check:disabled{opacity:.55;cursor:default}
.anex-search3-tv-check:focus-visible{outline:2px solid #2743cb;outline-offset:2px}
.anex-search3-tv-status{font-size:13px;line-height:1.5;color:#566176;margin:8px 0}
.anex-search3-hotel .anex-search3-tv-offers{margin:0}
.anex-search3-tv-price-label{display:block!important;font-size:11px;font-weight:500;line-height:1.4;color:#566176}
.anex-search3-tv-source{margin:12px 16px 0;font-size:14px;color:#566176}
.anex-search3-source-filter{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;margin-top:12px;font-size:14px;font-weight:600}
.anex-search3-source-filter select{box-sizing:border-box;max-width:100%;min-width:0;min-height:44px;padding:10px 32px 10px 12px;border:1px solid #dbe2ed;border-radius:8px;background:#fff;color:#18243b;font:inherit}
.anex-search3-source-filter select:focus-visible,.anex-search3-about summary:focus-visible,.anex-search3-offers summary:focus-visible{outline:2px solid #2743cb;outline-offset:2px}
body.search3-candidate #results .hotel-card.anex-search3-source-hidden{display:none!important}
@media(max-width:600px){.anex-search3-header{grid-template-columns:minmax(0,1fr)}.anex-search3-media{aspect-ratio:16/10}.anex-search3-identity{padding:14px}.anex-search3-hotel .anex-search3-offers{margin:0 14px 10px}.anex-search3-offer{gap:6px}.anex-search3-offer p{flex-basis:100%}.anex-search3-source-filter select{width:100%}}
`;
  document.head.appendChild(style);
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
      if (details.tagName !== 'DETAILS' || details.classList.contains('anex-search3-tv-offers')) return;
      const id = rowKey(details.getAttribute('data-anex-search3-row'));
      if (details.open) openHotels.add(id); else openHotels.delete(id);
    });
    results.querySelectorAll('.anex-search3-about').forEach(details => {
      const id = rowKey(details.getAttribute('data-anex-search3-row'));
      if (details.open) openDescriptions.add(id); else openDescriptions.delete(id);
    });
    results.querySelectorAll('.anex-search3-tv-offers').forEach(details => {
      const id = rowKey(details.getAttribute('data-anex-search3-row'));
      if (details.open) openPointOffers.add(id); else openPointOffers.delete(id);
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
    badge.setAttribute('data-anex-search3-row', String(hotelKey(hotel)));
    badge.appendChild(node('span', '', Array.from(new Set(hotel.tours.map(sourceLabel))).join(' · ')));
    badge.appendChild(node('strong', '', price(hotel.tours[0])));
    return badge;
  }
  let offerDialog = null, detailAbort = null;
  function closeOffer() {
    if (detailAbort) detailAbort.abort();
    detailAbort = null;
    if (offerDialog) { offerDialog.close(); offerDialog.remove(); }
    offerDialog = null;
  }
  async function openOffer(tour) {
    const context = offerContext(tour), run = active;
    if (!context || !isCurrent(run, window.V2SearchLifecycle)) return;
    closeOffer();
    const dialog = node('dialog', 'anex-search3-detail');
    offerDialog = dialog;
    const close = node('button', 'anex-search3-tv-check', 'Вернуться к предложениям');
    close.type = 'button'; close.addEventListener('click', closeOffer);
    const content = node('div', '', 'Загружаем условия тура…');
    dialog.appendChild(close); dialog.appendChild(content); document.body.appendChild(dialog);
    dialog.addEventListener('cancel', event => { event.preventDefault(); closeOffer(); });
    dialog.showModal();
    detailAbort = new AbortController(); const abort = detailAbort, signal = abort.signal;
    const timer = setTimeout(() => abort.abort(), 15000);
    try {
      const request = Object.assign({}, run, { action: 'offer_detail', page: context.page, offer_context: context });
      if (new URL(window.location.href).searchParams.get('andromeda_operator') === '5') request.andromeda_operator_ids = ['5'];
      const response = await window.fetch(new URL('api-andromeda-search3-preview.php', endpoint).href, {
        method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'AnyTourSearch3' },
        body: JSON.stringify(request), signal
      });
      const payload = await response.json(), data = payload.data;
      if (offerDialog !== dialog || active !== run || !isCurrent(run, window.V2SearchLifecycle)) { if (offerDialog === dialog) closeOffer(); return; }
      if (!response.ok || !payload.ok || data?.provider !== 'andromeda'
        || Object.keys(context).some(key => data.offer_context?.[key] !== context[key])) throw new Error('Offer unavailable');
      content.replaceChildren(node('h2', '', data.hotel));
      [data.operator, data.checkin.split('-').reverse().join('.') + ' · ' + data.nights + ' ноч.',
        data.adults + ' взр.' + (data.children ? ' · ' + data.children + ' дет.' : ''),
        mealLabel(data.meal), data.room, data.placement].filter(Boolean).forEach(value => content.appendChild(node('p', '', value)));
      content.appendChild(node('strong', '', price(data)));
      content.appendChild(node('p', 'anex-search3-note', 'Цена из поиска. Актуальность, рейсы и итоговую стоимость ещё нужно подтвердить. Бронирование пока недоступно.'));
    } catch (_) {
      if (offerDialog === dialog) content.textContent = 'Предложение недоступно или срок его хранения истёк. Повторите поиск.';
    } finally { clearTimeout(timer); }
  }
  ['input', 'change'].forEach(event => form.addEventListener(event, closeOffer));
  function offers(hotel, embedded = false) {
    const details = node(embedded ? 'section' : 'details', 'anex-search3-offers');
    details.setAttribute('data-anex-search3-row', String(hotelKey(hotel)));
    details.appendChild(node(embedded ? 'h4' : 'summary', '', embedded ? 'Предложения поставщиков' : 'Показать предложения · ' + price(hotel.tours[0])));
    if (!embedded) {
      details.open = openHotels.has(hotelKey(hotel));
    }
    hotel.tours.forEach(tour => {
      const row = node('div', 'anex-search3-offer');
      const date = tour.checkin.split('-').reverse().join('.');
      row.appendChild(node('p', '', [sourceLabel(tour) + (tour.operator ? ' · ' + tour.operator : ''), date, tour.nights + ' ноч.', mealLabel(tour.meal), tour.room,
        tour.adults + ' взр.' + (tour.children ? ', ' + tour.children + ' дет.' : '')].filter(Boolean).join(' · ')));
      row.appendChild(node('strong', '', price(tour)));
      if (offerContext(tour)) {
        const button = node('button', 'anex-search3-tv-check', 'Подробнее о туре');
        button.type = 'button'; button.addEventListener('click', () => openOffer(tour)); row.appendChild(button);
      }
      details.appendChild(row);
    });
    details.appendChild(node('p', 'anex-search3-note', 'Цена из поиска поставщика. Включение топливного сбора уточняется; итоговую стоимость подтвердит менеджер.'));
    return details;
  }
  function attach(card, hotel) {
    const body = card.querySelector('.hotel-body') || card;
    body.appendChild(sourceBadge(hotel));
    const box = card.querySelector('.hotel-tours');
    if (box) {
      const origin = node('h4', 'anex-search3-tv-source', 'Предложения через Tourvisor');
      origin.setAttribute('data-anex-search3-row', String(hotelKey(hotel)));
      box.insertBefore(origin, box.firstElementChild || null);
      box.appendChild(offers(hotel, true));
      const label = card.querySelector('.hotel-best-offer');
      if (label) {
        const source = node('span', 'anex-search3-tv-price-label', 'Через Tourvisor');
        source.setAttribute('data-anex-search3-row', String(hotelKey(hotel)));
        label.insertBefore(source, label.firstElementChild || null);
      }
      const copy = card.querySelector('.search3-hotel-action__copy');
      const tv = tvItems.find(item => String(item.id) === String(hotelKey(hotel)));
      if (copy && tv && Array.isArray(tv.tours)) replaceText(copy.querySelector('strong'),
        'Предложений: ' + (tv.tours.length + hotel.tours.length) + ' ');
    } else card.appendChild(offers(hotel));
  }
  function standalone(hotel, anexHotel = hotel, pointHotel = null) {
    const card = node('article', 'hotel-card anex-search3-hotel');
    card.setAttribute('data-anex-search3-card', String(hotelKey(hotel)));
    card.setAttribute('data-hotel-id', String(hotelKey(hotel)));
    card.setAttribute('data-search3-results-v1', '1');
    const catalog = catalogInfo(hotel), supplier = supplierInfo(hotel);
    const photos = [catalog && catalog.image_url && {url: catalog.image_url, source: 'Tourvisor'},
      supplier && supplier.image_url && {url: supplier.image_url, source: 'Андромеда'}].filter(Boolean);
    const header = node('div', 'anex-search3-header');
    const media = node('figure', 'anex-search3-media');
    const emptyPhoto = () => media.appendChild(node('span', 'anex-search3-photo-empty', 'Фото пока нет'));
    const showPhoto = () => {
      const photo = photos.find(candidate => !failedImages.has(candidate.url));
      if (!photo) { emptyPhoto(); return; }
      const image = node('img');
      image.alt = hotel.name;
      image.loading = 'lazy'; image.decoding = 'async'; image.referrerPolicy = 'no-referrer';
      const caption = node('figcaption', '', 'Фото: ' + photo.source);
      image.addEventListener('error', () => {
        failedImages.add(photo.url); image.remove(); caption.remove(); showPhoto();
      }, { once: true });
      image.src = photo.url;
      media.appendChild(image); media.appendChild(caption);
    };
    showPhoto();
    header.appendChild(media);
    const identity = node('div', 'anex-search3-identity');
    identity.appendChild(node('h3', '', hotel.name + (hotel.category ? ' ' + hotel.category + '★' : '')));
    identity.appendChild(node('p', 'anex-search3-place', Array.from(new Set([
      hotel.country, hotel.region, catalog && catalog.subregion
    ].filter(Boolean))).join(' · ')));
    const facts = [];
    if (typeof hotel.rating === 'number' && Number.isFinite(hotel.rating) && hotel.rating > 0) facts.push('Рейтинг ' + money.format(hotel.rating));
    if (catalog && catalog.sea_distance !== null) facts.push('До моря: ' + money.format(catalog.sea_distance) + ' м');
    if (facts.length) identity.appendChild(node('p', 'anex-search3-facts', facts.join(' · ')));
    if (anexHotel) identity.appendChild(sourceBadge(anexHotel));
    if (pointHotel) identity.appendChild(node('div', 'anex-search3-source', 'Tourvisor · от ' + money.format(pointHotel.price) + ' ₽'));
    if (catalog && (catalog.description || catalog.address)) {
      const about = node('details', 'anex-search3-about');
      about.setAttribute('data-anex-search3-row', String(hotelKey(hotel)));
      about.open = openDescriptions.has(hotelKey(hotel));
      about.appendChild(node('summary', '', 'Об отеле'));
      if (catalog.description) about.appendChild(node('p', '', catalog.description));
      if (catalog.address) about.appendChild(node('p', '', 'Адрес: ' + catalog.address));
      about.appendChild(node('p', 'anex-search3-note', 'Информация об отеле: Tourvisor'));
      identity.appendChild(about);
    }
    if (supplier && supplier.hotel_url) {
      const link = node('a', '', 'Описание отеля у поставщика');
      link.href = supplier.hotel_url; link.target = '_blank'; link.rel = 'noopener noreferrer'; identity.appendChild(link);
    }
    header.appendChild(identity);
    card.appendChild(header);
    if (anexHotel) card.appendChild(offers(anexHotel));
    const point = pointBlock(hotel, pointHotel);
    if (point) card.appendChild(point);
    return card;
  }
  function pointUrl(action, params) {
    const rt = window.V2Runtime;
    if (!rt || typeof rt.build !== 'function') return null;
    try {
      const url = new URL(rt.build(action, params), window.location.href);
      // Isolated previews intentionally retain the existing read-only Tourvisor gateway.
      return url.origin === window.location.origin && (url.pathname === '/api-v2.php'
        || /^\/_preview\/search3-anex-candidate\/(?:v2\/)?api-v2\.php$/.test(url.pathname)) ? url.href : null;
    } catch (_) { return null; }
  }
  function canPointCheck(hotel) {
    return broadComplete && !broadHotelIds.has(String(hotelKey(hotel))) && isCurrent(active, window.V2SearchLifecycle)
      && !localFilterNotice() && !!pointSearchParams(active, hotel.local_id) && !!pointUrl('search_start', {});
  }
  function pointHotelFor(hotel) {
    const check = pointChecks.get(hotel.local_id), value = check && check.hotel;
    if (!value) return null;
    // Use the same accepted hotel identity/facets; only the Tourvisor tours differ.
    const item = Object.assign({}, filterItem(hotel), { tours: value.tours, price: value.price });
    const filter = window.DS2ResultsFilters;
    const kept = filter && typeof filter.filteredHotel === 'function' ? filter.filteredHotel(item) : item;
    return kept && kept.tours.length ? kept : null;
  }
  function pointText(value) {
    return typeof value === 'string' || typeof value === 'number' ? String(value).slice(0, 500)
      : value && typeof value === 'object' ? pointText(value.russianName || value.name || value.title || '') : '';
  }
  function pointBlock(hotel, value) {
    const id = hotel.local_id, check = pointChecks.get(id);
    if (!check && !canPointCheck(hotel)) return null;
    const box = node('section', 'anex-search3-tv-point');
    box.id = 'anexSearch3TvCheck-' + id;
    box.tabIndex = -1;
    box.setAttribute('data-anex-search3-row', String(id));
    if (!check) {
      const button = node('button', 'anex-search3-tv-check', 'Проверить предложения Tourvisor');
      button.type = 'button'; button.disabled = !!pointPending || pointChecks.size >= 3;
      button.addEventListener('click', () => checkPoint(hotel));
      box.appendChild(button);
      if (pointChecks.size >= 3) box.appendChild(node('p', 'anex-search3-tv-status', 'Для новой проверки обновите поиск.'));
    } else if (value) {
      const details = node('details', 'anex-search3-offers anex-search3-tv-offers');
      details.setAttribute('data-anex-search3-row', String(id));
      details.open = openPointOffers.has(id);
      details.appendChild(node('summary', '', 'Предложения Tourvisor: ' + value.tours.length + ' · от ' + money.format(value.price) + ' ₽'));
      const offerRow = tour => {
        const row = node('div', 'anex-search3-offer');
        row.appendChild(node('p', '', [pointText(tour.date), tour.nights + ' ноч.', pointText(tour.meal),
          pointText(tour.roomType), pointText(tour.placement), pointText(tour.operator)].filter(Boolean).join(' · ')));
        row.appendChild(node('strong', '', money.format(Number(tour.price)) + ' ₽'));
        return row;
      };
      let shown = Math.min(pointVisible.get(id) || 20, value.tours.length);
      value.tours.slice(0, shown).forEach(tour => details.appendChild(offerRow(tour)));
      const count = node('p', 'anex-search3-tv-visible anex-search3-note');
      count.setAttribute('role', 'status');
      const updateCount = () => { count.textContent = 'Показано ' + shown + ' из ' + value.tours.length + ' предложений'; };
      updateCount();
      if (shown < value.tours.length) {
        const more = node('button', 'anex-search3-tv-check anex-search3-tv-more');
        more.type = 'button';
        const updateLabel = () => { more.textContent = 'Показать ещё ' + Math.min(20, value.tours.length - shown); };
        updateLabel();
        more.addEventListener('click', () => {
          if (!document.contains(box) || !isCurrent(active, window.V2SearchLifecycle)) return;
          const next = Math.min(shown + 20, value.tours.length);
          value.tours.slice(shown, next).forEach(tour => details.insertBefore(offerRow(tour), more));
          shown = next; pointVisible.set(id, shown); updateCount();
          if (shown < value.tours.length) updateLabel();
          else {
            const focused = document.activeElement === more;
            more.remove();
            if (focused) { count.tabIndex = -1; count.focus({ preventScroll: true }); }
          }
        });
        details.appendChild(more);
      }
      details.appendChild(count);
      details.appendChild(node('p', 'anex-search3-note', 'Цены из отдельной проверки Tourvisor по вашим условиям. Итоговую стоимость подтвердит менеджер.'));
      box.appendChild(details);
    } else {
      const status = node('p', 'anex-search3-tv-status', check.state === 'loading' ? 'Ищем предложения Tourvisor…'
        : check.state === 'empty' ? 'По этим условиям Tourvisor предложений не вернул.'
        : check.state === 'success' ? 'По выбранным фильтрам предложений Tourvisor нет.'
        : check.state === 'timeout' ? 'Tourvisor не завершил расчёт. Предложения ANEX сохранены.'
        : 'Не удалось завершить проверку Tourvisor. Предложения ANEX сохранены.');
      status.setAttribute('role', 'status'); box.appendChild(status);
    }
    return box;
  }
  async function checkPoint(hotel) {
    const id = hotel.local_id;
    if (!canPointCheck(hotel) || pointPending || pointChecks.has(id) || pointChecks.size >= 3) return;
    const run = active, params = pointSearchParams(run, id), abort = new AbortController();
    const check = { state: 'loading', abort, hotel: null, timer: null, wake: null };
    pointChecks.set(id, check); pointPending = check;
    const current = () => isCurrent(run, window.V2SearchLifecycle) && active === run && pointChecks.get(id) === check && !localFilterNotice();
    const request = async (action, values) => {
      if (!current() || abort.signal.aborted) throw new Error('Stopped');
      const url = pointUrl(action, values);
      if (!url) throw new Error('Unavailable preview API');
      // Bypass runtime observation: a point result must never replace broad search/lead context.
      const response = await window.fetch(url, { credentials: 'same-origin', signal: abort.signal });
      if (!response.ok) throw new Error('Point search request failed');
      const data = await response.json();
      if (!current() || abort.signal.aborted || data?.ok === false) throw new Error('Stopped or invalid response');
      return data;
    };
    const timeout = setTimeout(() => { check.state = 'timeout'; abort.abort(); if (check.wake) check.wake(); }, 60000);
    queueRender();
    try {
      const started = await request('search_start', params), searchId = Number(started.searchId);
      if (!Number.isSafeInteger(searchId) || searchId < 1) throw new Error('Missing search ID');
      let complete = false;
      for (let poll = 0; poll < 8; poll++) {
        // Spread the same eight reads across the one-minute deadline; do not exhaust them in 17.5 seconds.
        if (poll) await new Promise(resolve => { check.wake = resolve; check.timer = setTimeout(resolve, 7500); });
        check.wake = null;
        const status = await request('search_status', { searchId });
        if (Number(status.progress) >= 100 || status.status === 'complete') { complete = true; break; }
      }
      if (!complete) { check.state = 'timeout'; return; }
      const list = await request('search_results', { searchId, limit: 100 });
      check.hotel = pointSearchHotel(list, id, params);
      check.state = check.hotel ? 'success' : 'empty';
      if (check.hotel) openPointOffers.add(id);
      updateSupplemental();
    } catch (_) {
      if (check.state !== 'timeout') check.state = 'error';
    } finally {
      clearTimeout(timeout); clearTimeout(check.timer);
      if (pointPending === check) pointPending = null;
      if (current()) queueRender();
    }
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
    if (filter && typeof filter.setSupplementalItems === 'function') filter.setSupplementalItems(hotels.map(hotel => {
      const item = filterItem(hotel), check = pointChecks.get(hotel.local_id);
      return check && check.hotel ? Object.assign({}, item, { tours: item.tours.concat(check.hotel.tours),
        price: Math.min(item.price, check.hotel.price) }) : item;
    }));
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
    const pointFocused = document.activeElement && typeof document.activeElement.closest === 'function'
      && document.activeElement.closest('.anex-search3-tv-point');
    const pointFocusId = pointFocused && pointFocused.id;
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
    panel.appendChild(node('h2', '', 'Tourvisor · ANEX API · Андромеда'));
    if (dates) panel.appendChild(node('p', 'anex-search3-status', dates));
    const status = node('p', 'anex-search3-status', filterNotice || message);
    status.setAttribute('role', 'status');
    panel.appendChild(status);

    let added = 0, merged = 0, ambiguous = 0, supplementalCount = 0;
    (filterNotice ? [] : hotels).filter(validHotel).forEach(original => {
      const hotel = filteredHotel(original), point = pointHotelFor(original);
      if (!hotel && !point) return;
      const id = String(hotelKey(original));
      const existing = cards.get(id);
      if (cards.has(id) && !existing) { ambiguous++; return; }
      if (existing) {
        // A later normal Tourvisor card owns its native selection and search context.
        if (!hotel) return;
        attach(existing, hotel);
        const item = ranked.find(row => row.card === existing);
        item.anex = hotel.tours.some(t => t.provider === 'anex');
        item.andromeda = hotel.tours.some(t => t.provider === 'andromeda');
        item.price = Math.min(priceRank(item.price), priceRank(hotel.tours[0].price.amount));
        merged++; return;
      }
      const card = standalone(original, hotel, point);
      ranked.push({ id, card, tourvisor: !!point, anex: !!hotel && hotel.tours.some(t => t.provider === 'anex'),
        andromeda: !!hotel && hotel.tours.some(t => t.provider === 'andromeda'),
        price: Math.min(hotel ? priceRank(hotel.tours[0].price.amount) : Infinity, point ? priceRank(point.price) : Infinity),
        category: original.category, rating: original.rating, seaDistance: filterItem(original).seaDistance });
      if (hotel) { if (point) merged++; else added++; }
      supplementalCount++;
    });
    const tvCount = ranked.filter(item => item.tourvisor).length;
    if (added || merged || supplementalCount) {
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
      replaceText(document.getElementById('resultSummary'), 'Tourvisor: ' + tvCount + ' · Другие API: ' + (added + merged));
      replaceText(document.querySelector('[data-ds2-filter-count]'), String(ranked.length));
      replaceText(document.querySelector('[data-ds2-filter-word]'), 'в выдаче');
      status.textContent = 'Отелей в выдаче: ' + ranked.length + '. Через Tourvisor: ' + tvCount
        + ', через ANEX/Андромеду: ' + (added + merged) + '. В обоих источниках: ' + merged + '.'
        + (ambiguous ? ' Часть предложений ожидает уточнения связи.' : '') + ' ' + message;
    } else {
      // Restore the original source order when ANEX is hidden by changed filters.
      tvCards.filter(card => card.parentNode === results).forEach(card => results.appendChild(card));
      if (!filterNotice && hotels.length && !ambiguous) status.textContent = 'По выбранным фильтрам предложений других поставщиков нет. Измените фильтры или сбросьте их.';
    }
    const counts = { all: ranked.length, anex: ranked.filter(i => i.anex).length, andromeda: ranked.filter(i => i.andromeda).length, tourvisor: ranked.filter(item => item.tourvisor).length, both: merged };
    sourceChoices.forEach(([value, label], index) => { sourceSelect.children[index].textContent = label + ' · ' + counts[value]; });
    sourceSelect.value = sourceMode;
    panel.appendChild(sourceFilter);
    let visible = 0;
    ranked.forEach(item => {
      const shown = sourceMode === 'all' || (sourceMode === 'both' ? (item.anex || item.andromeda) && item.tourvisor : item[sourceMode]);
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
    if (pointFocusId) {
      const target = document.getElementById(pointFocusId);
      if (target) target.focus({ preventScroll: true });
    }
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
    closeOffer();
    if (controller) controller.abort();
    controller = null;
    active = null; hotels = []; message = ''; dates = ''; sourceMode = 'all'; clear(); openHotels.clear(); openDescriptions.clear(); failedImages.clear();
    pointChecks.forEach(check => { check.abort.abort(); clearTimeout(check.timer); if (check.wake) check.wake(); });
    pointChecks.clear(); pointPending = null; openPointOffers.clear(); pointVisible.clear(); broadComplete = false; broadHotelIds = new Set();
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
    const anexOnly = new URL(window.location.href || endpoint.href).searchParams.get('andromeda_operator') === '5';
    const pages = {}, statuses = { anex: 'ANEX: поиск…', andromeda: 'Андромеда: поиск…' };
    message = Object.values(statuses).join(' · '); render();
    const timeout = setTimeout(() => { if (isCurrent(run, window.V2SearchLifecycle) && controller) controller.abort(); }, 600000);
    await Promise.allSettled(['anex', 'andromeda'].map(async provider => {
      const label = provider === 'andromeda' ? 'Андромеда' + (anexOnly ? ' (ANEX)' : '') : 'ANEX';
      try {
        const url = new URL('api-' + provider + '-search3-preview.php', endpoint);
        let number = 1, total = 1;
        do {
          const request = provider === 'andromeda' ? Object.assign({}, run, { page: number }) : run;
          if (provider === 'andromeda' && anexOnly) request.andromeda_operator_ids = ['5'];
          const response = await window.fetch(url.href, { method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'AnyTourSearch3' }, body: JSON.stringify(request), signal });
          const payload = await response.json();
          if (!isCurrent(run, window.V2SearchLifecycle) || active !== run || signal.aborted) return;
          if (!response.ok || !payload.ok) {
            statuses[provider] = errorMessage(payload.error).replace(/ANEX/g, label) + (number > 1 ? ' Полученные страницы сохранены.' : '');
            break;
          }
          const data = payload.data;
          if (!data || data.generation !== run.generation || data.provider !== provider || !Array.isArray(data.hotels)
            || (provider === 'andromeda' && data.page !== undefined && data.page !== number)) throw new Error('Invalid provider page');
          pages[provider] = combineSources({ [provider]: [...(pages[provider] || []), ...data.hotels] });
          if (provider === 'andromeda') {
            total = Number.isInteger(data.pages_count) && data.pages_count > 0 ? data.pages_count : 1;
            statuses[provider] = label + ': ' + pages[provider].length + ' отелей · страниц ' + number + '/' + total
              + (number < total ? ' · загружаем…' : ' · готово');
          } else statuses[provider] = label + ': ' + pages[provider].length + ' отелей (первая страница)';
          hotels = combineSources(pages); message = Object.values(statuses).join(' · ');
          dates = 'ANEX: первая страница. Андромеда: последовательная загрузка предложений.';
          updateSupplemental(); render();
          number++;
        } while (provider === 'andromeda' && number <= total && number <= 1000 && !signal.aborted);

      } catch (error) {
        if (!isCurrent(run, window.V2SearchLifecycle) || active !== run) return;
        statuses[provider] = label + ': ответ недоступен';
      }
      if (!isCurrent(run, window.V2SearchLifecycle) || active !== run) return;
      hotels = combineSources(pages);
      message = Object.values(statuses).join(' · ');
      dates = 'ANEX: первая страница. Андромеда: последовательная загрузка предложений.';
      updateSupplemental(); render();
    }));
    clearTimeout(timeout);
  }

  window.addEventListener('v2:search-reset', start);
  function broadFinished(event) {
    const lifecycle = window.V2SearchLifecycle, detail = event && event.detail;
    if (!isCurrent(active, lifecycle) || !detail || !Array.isArray(detail.items)
      || !Number(lifecycle.searchId) || Number(detail.searchId) !== Number(lifecycle.searchId)) return;
    broadComplete = true;
    broadHotelIds = new Set(detail.items.map(item => String(item && item.id || '')));
    queueRender();
  }
  window.addEventListener('v2:search-complete', broadFinished);
  window.addEventListener('v2:search-continued', broadFinished);
  ['v2:search-continue-started', 'v2:search-continue-requested'].forEach(name => window.addEventListener(name, () => { broadComplete = false; }));
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
