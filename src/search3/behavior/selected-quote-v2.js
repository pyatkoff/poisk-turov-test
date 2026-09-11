(() => {
  'use strict';

  const root = window;
  const entry = typeof root.__ANYTOOUR_SEARCH3_ENTRY__ === 'string' ? root.__ANYTOOUR_SEARCH3_ENTRY__ : '';
  if (!entry) return;
  const ns = root[entry] = root[entry] || {};
  ns.modules = ns.modules || {};
  if (ns.modules['search3-selected-flow-v2'] && ns.modules['search3-selected-flow-v2'].version === 2) return;

  const quoteStates = new Map();
  const activeControllers = new Map();
  const moneyFormatter = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 });

  function safeText(value, max = 180) {
    return typeof value === 'string' ? value.trim().slice(0, max) : '';
  }

  function quoteEndpoint(value) {
    const raw = value || (root.V2_CONFIG && root.V2_CONFIG.andromedaApi);
    if (!raw || !root.location) return null;
    try {
      const url = new URL(raw, root.location.href);
      if (url.origin !== root.location.origin || url.search || url.hash) return null;
      if (!/^\/(?:_preview\/[A-Za-z0-9._-]+\/)?api-andromeda-search3-preview\.php$/.test(url.pathname)) return null;
      url.pathname = url.pathname.replace(/api-andromeda-search3-preview\.php$/, 'api-andromeda-quote-preview.php');
      return url;
    } catch (_) {
      return null;
    }
  }

  function normalizeContext(value) {
    const provider = root.AnyTourAndromedaProvider;
    return provider && typeof provider.context === 'function' ? provider.context(value) : null;
  }

  function findTour(ref) {
    ref = String(ref || '');
    if (!/^offer_[a-f0-9]{64}$/.test(ref)) return null;
    const renderer = root.V2Results;
    const items = renderer && renderer.state && Array.isArray(renderer.state.items) ? renderer.state.items : [];
    const matches = new Map();
    items.forEach(hotel => {
      (Array.isArray(hotel && hotel.tours) ? hotel.tours : []).forEach(tour => {
        if (!tour || tour.provider !== 'andromeda' || tour.offerRef !== ref) return;
        const context = normalizeContext(tour.offerContext);
        if (!context || context.offer_ref !== ref) return;
        matches.set(JSON.stringify(context), { tour, context });
      });
    });
    return matches.size === 1 ? Array.from(matches.values())[0] : null;
  }

  function normalizeMoney(value) {
    if (!value || typeof value !== 'object') return null;
    const amount = Number(value.amount);
    const currency = safeText(value.currency, 8);
    if (!Number.isFinite(amount) || amount <= 0 || amount > 1e12 || !/^[A-Z0-9_]{2,8}$/.test(currency)) return null;
    return { amount, currency };
  }

  function normalizePoint(value) {
    if (!value || typeof value !== 'object') return null;
    return {
      state: safeText(value.state, 100),
      town: safeText(value.town, 100),
      port: safeText(value.port, 100),
    };
  }

  function normalizeFlight(value) {
    if (!value || typeof value !== 'object') return null;
    const direction = String(value.direction == null ? '' : value.direction);
    if (direction !== '0' && direction !== '1') return null;
    return {
      direction,
      name: safeText(value.name, 160),
      datebeg: safeText(value.datebeg, 40),
      dateend: safeText(value.dateend, 40),
      className: safeText(value.class, 80),
      departure: normalizePoint(value.departure),
      arrival: normalizePoint(value.arrival),
    };
  }

  function normalizeQuote(data) {
    if (!data || data.provider !== 'andromeda' || data.booking_enabled !== false) return null;
    const state = data.state;
    if (state !== 'quote_verified' && state !== 'flight_selection_required') return null;
    const flights = Array.isArray(data.flights) ? data.flights.slice(0, 40).map(normalizeFlight).filter(Boolean) : [];
    const normalized = {
      state,
      searchPrice: normalizeMoney(data.search_price),
      packagePrice: normalizeMoney(data.package_price),
      finalPrice: normalizeMoney(data.final_price),
      finalPriceVerified: data.final_price_verified === true,
      flightSelectionRequired: data.flight_selection_required === true,
      flights,
      bookingEnabled: false,
    };
    if (state === 'quote_verified' && (!normalized.finalPriceVerified || !normalized.finalPrice || normalized.flightSelectionRequired)) return null;
    if (state === 'flight_selection_required' && (normalized.finalPriceVerified || normalized.finalPrice || !normalized.flightSelectionRequired)) return null;
    return normalized;
  }

  function formatMoney(value) {
    if (!value) return '';
    const suffix = value.currency === 'RUB' ? ' ₽' : ' ' + value.currency;
    return moneyFormatter.format(value.amount) + suffix;
  }

  function pointLabel(point) {
    if (!point) return '';
    return [point.town, point.port].filter(Boolean).join(' · ');
  }

  function flightLabel(flight) {
    const direction = flight.direction === '0' ? 'Туда' : 'Обратно';
    const route = [pointLabel(flight.departure), pointLabel(flight.arrival)].filter(Boolean).join(' → ');
    return [direction, flight.datebeg, flight.name, route, flight.className].filter(Boolean).join(' · ');
  }

  function clearNode(node) {
    while (node.firstChild) node.removeChild(node.firstChild);
  }

  function appendText(parent, tag, text, className) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = text;
    parent.appendChild(node);
    return node;
  }

  function retryMessage(code) {
    if (code === 'quote_not_available') return 'Предложение изменилось. Обновите поиск, чтобы получить свежий вариант.';
    if (code === 'monthly_quota_exhausted') return 'Проверка цены временно недоступна. Попробуйте позже.';
    return 'Не удалось проверить актуальную цену и рейсы.';
  }

  function renderPanel(panel, ref) {
    clearNode(panel);
    const state = quoteStates.get(ref);
    if (!state) {
      appendText(panel, 'p', 'Перед выбором проверим актуальную цену и рейсы у поставщика.', 'tour-selection-note');
      const button = appendText(panel, 'button', 'Проверить цену и рейсы', 'secondary');
      button.type = 'button';
      button.dataset.andromedaQuote = ref;
      return;
    }
    if (state.status === 'loading') {
      panel.setAttribute('role', 'status');
      appendText(panel, 'p', 'Проверяем актуальную цену и рейсы…', 'tour-selection-note');
      return;
    }
    panel.removeAttribute('role');
    if (state.status === 'error') {
      appendText(panel, 'p', state.message || 'Не удалось проверить актуальную цену и рейсы.', 'tour-selection-note');
      if (state.retry !== false) {
        const button = appendText(panel, 'button', 'Повторить проверку', 'secondary');
        button.type = 'button';
        button.dataset.andromedaQuote = ref;
      }
      return;
    }
    const data = state.data;
    if (!data) return;
    if (data.state === 'quote_verified') {
      appendText(panel, 'strong', 'Подтверждённая цена: ' + formatMoney(data.finalPrice));
      if (data.packagePrice && data.packagePrice.amount !== data.finalPrice.amount) {
        appendText(panel, 'p', 'До выбора рейсов: ' + formatMoney(data.packagePrice), 'tour-selection-note');
      }
      data.flights.forEach(flight => appendText(panel, 'p', flightLabel(flight), 'tour-selection-note'));
      appendText(panel, 'small', 'Цена пересчитана поставщиком после выбора рейсов. Бронирование пока отключено.');
      return;
    }
    appendText(panel, 'strong', 'Нужно выбрать рейс');
    data.flights.forEach(flight => appendText(panel, 'p', flightLabel(flight), 'tour-selection-note'));
    appendText(panel, 'small', 'Итоговую цену покажем только после выбора рейса. Автоматически вариант не выбираем.');
  }

  function decorate() {
    if (!document || typeof document.querySelectorAll !== 'function') return;
    document.querySelectorAll('[data-andromeda-detail][aria-expanded="true"]').forEach(button => {
      const ref = String(button.dataset && button.dataset.andromedaDetail || '');
      const found = findTour(ref);
      if (!found || !found.tour.providerDetail || found.tour.providerDetail.status !== 'complete') return;
      const targetId = button.getAttribute('aria-controls');
      if (!targetId) return;
      const detail = document.getElementById(targetId);
      if (!detail || detail.classList.contains('provider-detail--error')) return;
      let panel = detail.querySelector('[data-andromeda-quote-panel]');
      if (!panel) {
        panel = document.createElement('div');
        panel.dataset.andromedaQuotePanel = ref;
        detail.appendChild(panel);
      }
      renderPanel(panel, ref);
    });
  }

  let scheduled = false;
  function scheduleDecorate() {
    if (scheduled) return;
    scheduled = true;
    const run = root.requestAnimationFrame || (callback => root.setTimeout(callback, 0));
    run(() => { scheduled = false; decorate(); });
  }

  function requestBody(found) {
    const lifecycle = root.V2SearchLifecycle;
    const params = lifecycle && lifecycle.snapshot;
    if (!params || lifecycle.dirty || lifecycle.generation !== found.context.generation) return null;
    const body = {
      action: 'quote',
      generation: found.context.generation,
      page: found.context.page,
      params,
      offer_context: found.context,
    };
    if (found.context.hotel_scope) body.hotel_scope = found.context.hotel_scope;
    return body;
  }

  async function verifyQuote(ref) {
    ref = String(ref || '');
    const endpoint = quoteEndpoint();
    const found = findTour(ref);
    const body = found && requestBody(found);
    if (!endpoint || !found || !body || typeof root.fetch !== 'function') {
      quoteStates.set(ref, { status: 'error', retry: false, message: 'Предложение изменилось. Обновите поиск.' });
      scheduleDecorate();
      return null;
    }
    const previous = quoteStates.get(ref);
    if (previous && previous.status === 'loading') return null;
    if (previous && previous.status === 'complete') return previous.data;

    const controller = new AbortController();
    activeControllers.set(ref, controller);
    quoteStates.set(ref, { status: 'loading' });
    scheduleDecorate();
    let timedOut = false;
    const timer = root.setTimeout(() => { timedOut = true; controller.abort(); }, 45000);
    try {
      const response = await root.fetch(endpoint.href, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'AnyTourSearch3' },
        body: JSON.stringify(body),
        signal: controller.signal,
      });
      const payload = await response.json().catch(() => null);
      if (!payload || payload.ok !== true || !response.ok) {
        const code = safeText(payload && payload.error, 80) || 'supplier_unavailable';
        const retry = response.status >= 500 || response.status === 429;
        quoteStates.set(ref, { status: 'error', retry, message: retryMessage(code) });
        return null;
      }
      const data = normalizeQuote(payload.data);
      if (!data) {
        quoteStates.set(ref, { status: 'error', retry: false, message: 'Поставщик вернул неполные данные. Обновите поиск.' });
        return null;
      }
      quoteStates.set(ref, { status: 'complete', data });
      return data;
    } catch (error) {
      if (controller.signal.aborted && !timedOut) return null;
      quoteStates.set(ref, { status: 'error', retry: true, message: timedOut ? 'Проверка цены заняла слишком много времени.' : 'Не удалось проверить актуальную цену и рейсы.' });
      return null;
    } finally {
      root.clearTimeout(timer);
      if (activeControllers.get(ref) === controller) activeControllers.delete(ref);
      scheduleDecorate();
    }
  }

  function reset() {
    activeControllers.forEach(controller => controller.abort());
    activeControllers.clear();
    quoteStates.clear();
    scheduleDecorate();
  }

  if (document && typeof document.addEventListener === 'function') {
    document.addEventListener('click', event => {
      const button = event.target && event.target.closest && event.target.closest('[data-andromeda-quote]');
      if (!button) return;
      event.preventDefault();
      verifyQuote(button.dataset.andromedaQuote);
    });
  }
  if (typeof root.addEventListener === 'function') {
    root.addEventListener('v2:results-rendered', scheduleDecorate);
    root.addEventListener('v2:search-reset', reset);
  }
  if (document && document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scheduleDecorate, { once: true });
  else scheduleDecorate();

  const api = { quoteEndpoint, normalizeQuote, findTour, requestBody, verifyQuote, decorate, states: quoteStates, version: 2 };
  ns.modules['search3-selected-flow-v2'] = api;
})();
