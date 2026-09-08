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
  function validHotel(hotel) {
    return !!hotel && Number.isSafeInteger(hotel.local_id) && hotel.local_id > 0
      && typeof hotel.name === 'string' && hotel.name.length > 0 && hotel.name.length <= 300
      && Array.isArray(hotel.tours) && hotel.tours.length > 0 && hotel.tours.length <= 5
      && hotel.tours.every(tour => tour && tour.price && tour.price.currency === 'RUB'
        && typeof tour.price.amount === 'string' && /^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/.test(tour.price.amount)
        && Number(tour.price.amount) > 0 && typeof tour.checkin === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(tour.checkin)
        && Number.isInteger(tour.nights) && tour.nights > 0 && tour.nights <= 60);
  }
  window.AnyTourAnexSearch3 = { capture, isCurrent, validHotel, version: 1 };
  if (!/^\/_preview\/search3-anex-candidate\//.test(window.location.pathname)) return;
  const script = document.currentScript;
  if (!script || !script.src) return;
  const endpoint = new URL('api-anex-search3-preview.php', script.src);
  if (endpoint.origin !== window.location.origin || !/^\/_preview\/search3-anex-candidate\//.test(endpoint.pathname)) return;
  const results = document.getElementById('results'), form = document.getElementById('tourSearch');
  if (!results || !form || !window.fetch) return;
  const panelAnchor = (typeof results.closest === 'function' && results.closest('.results-layout')) || results;
  let active = null, controller = null, lastGeneration = 0, hotels = [], message = '', panel = null;
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
  function clear() {
    results.querySelectorAll('[data-anex-search3-row]').forEach(row => row.remove());
    if (panel) panel.remove();
    panel = null;
  }
  function price(tour) { return (tour.kind === 'group_minimum' ? 'от ' : '') + money.format(Number(tour.price.amount)) + ' ₽'; }
  function offers(hotel) {
    const details = node('details', 'anex-search3-offers');
    details.setAttribute('data-anex-search3-row', String(hotel.local_id));
    details.appendChild(node('summary', '', 'ANEX · ' + price(hotel.tours[0])));
    hotel.tours.forEach(tour => {
      const row = node('div', 'anex-search3-offer');
      const date = tour.checkin.split('-').reverse().join('.');
      row.appendChild(node('p', '', [date, tour.nights + ' ноч.', tour.meal, tour.room,
        tour.adults + ' взр.' + (tour.children ? ', ' + tour.children + ' дет.' : '')].filter(Boolean).join(' · ')));
      row.appendChild(node('strong', '', price(tour)));
      details.appendChild(row);
    });
    details.appendChild(node('p', 'anex-search3-note', 'Стоимость по результатам поиска. Условия подтвердит менеджер.'));
    return details;
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
    if ((state && (state.stars || state.rating || state.meal || state.seaMax))
      || (range && Number(range.value) < Number(range.max))) {
      return 'Для просмотра предложений ANEX сбросьте фильтры результатов.';
    }
    return '';
  }
  function render() {
    clear();
    if (!isCurrent(active, window.V2SearchLifecycle)) return;
    const filterNotice = localFilterNotice();
    const cards = new Map();
    results.querySelectorAll('.hotel-card[data-hotel-id]').forEach(card => {
      const id = String(card.dataset.hotelId || '');
      // Duplicate local cards are ambiguous; keep those supplier rows separate.
      cards.set(id, cards.has(id) ? null : card);
    });
    panel = node('section', 'anex-search3-panel');
    panel.id = 'anexSearch3Results';
    panel.setAttribute('aria-label', 'Предложения ANEX');
    panel.appendChild(node('h2', '', 'Предложения ANEX'));
    const status = node('p', 'anex-search3-status', filterNotice || message);
    status.setAttribute('role', 'status');
    panel.appendChild(status);
    let separate = 0;
    (filterNotice ? [] : hotels).filter(validHotel).forEach(hotel => {
      const existing = cards.get(String(hotel.local_id));
      if (existing) { existing.appendChild(offers(hotel)); return; }
      const card = node('article', 'anex-search3-hotel');
      card.setAttribute('data-anytour-hotel-id', String(hotel.local_id));
      card.appendChild(node('h3', '', hotel.name + (hotel.category ? ' ' + hotel.category + '★' : '')));
      card.appendChild(node('p', 'anex-search3-place', [hotel.country, hotel.region].filter(Boolean).join(' · ')));
      card.appendChild(offers(hotel));
      panel.appendChild(card);
      separate++;
    });
    if (!filterNotice && !separate && hotels.length) status.textContent = 'Предложения ANEX добавлены к отелям в результатах.';
    panelAnchor.parentNode.insertBefore(panel, panelAnchor.nextSibling);
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
    active = null; hotels = []; message = ''; clear();
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
        message = payload.error === 'search_not_supported' ? 'ANEX пока не поддерживает эти условия поиска.' : 'ANEX сейчас не ответил. Повторите поиск позже.';
      } else if (payload.data && payload.data.generation === run.generation && payload.data.provider === 'anex' && Array.isArray(payload.data.hotels)) {
        const seen = new Set();
        hotels = payload.data.hotels.slice(0, 300).filter(hotel => {
          if (!validHotel(hotel) || seen.has(hotel.local_id)) return false;
          seen.add(hotel.local_id); return true;
        });
        message = hotels.length ? 'Найдено отелей: ' + hotels.length
          : payload.data.external_search_pending ? 'ANEX продолжает расчёт. Повторите поиск позже.' : 'Подходящих предложений ANEX пока нет.';
      } else message = 'ANEX сейчас не ответил. Повторите поиск позже.';
      render();
    } catch (error) {
      if (isCurrent(run, window.V2SearchLifecycle) && active === run) {
        message = 'ANEX сейчас не ответил. Повторите поиск позже.'; render();
      }
    } finally { clearTimeout(timeout); }
  }
  window.addEventListener('v2:search-reset', start);
  window.addEventListener('v2:results-rendered', render);
  window.addEventListener('v2:search-error', () => { if (!isCurrent(active, window.V2SearchLifecycle)) clear(); });
  // The addon may be loaded after a URL-triggered initial search has started.
  if (window.V2SearchLifecycle && window.V2SearchLifecycle.snapshot) start();
}());
