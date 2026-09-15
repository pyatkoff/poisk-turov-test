(() => {
  'use strict';

  const root = window;
  if (root.Search3AndromedaQuoteUi && root.Search3AndromedaQuoteUi.version === 3) return;

  const quoteStates = new Map();
  const moneyFormatter = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 });

  function safeText(value, max = 180) {
    return typeof value === 'string' ? value.trim().slice(0, max) : '';
  }

  function formatMoney(value) {
    if (!value || value.currency !== 'RUB') return '';
    const amount = Number(value.amount);
    return Number.isFinite(amount) && amount > 0 ? moneyFormatter.format(amount) + '\u00a0₽' : '';
  }

  function pointLabel(point) {
    if (!point) return '';
    return [safeText(point.town, 100), safeText(point.port, 100)].filter(Boolean).join(' · ');
  }

  function flightLabel(flight) {
    const direction = flight.direction === '0' ? 'Туда' : 'Обратно';
    const route = [pointLabel(flight.departure), pointLabel(flight.arrival)].filter(Boolean).join(' → ');
    return [direction, safeText(flight.datebeg, 40), safeText(flight.name, 160), route, safeText(flight.class, 80)].filter(Boolean).join(' · ');
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
      panel.setAttribute('role', 'alert');
      appendText(panel, 'p', state.message, 'tour-selection-note');
      return;
    }
    panel.removeAttribute('role');
    const { selection, quote } = state;
    if (quote.state === 'quote_verified') {
      const finalPrice = formatMoney(quote.finalPrice);
      const changed = Number(quote.finalPrice.amount) !== Number(selection.tour.price);
      appendText(panel, 'strong', (changed ? 'Цена изменилась и подтверждена: ' : 'Подтверждённая цена: ') + finalPrice);
      quote.flights.forEach(flight => appendText(panel, 'p', flightLabel(flight), 'tour-selection-note'));
      appendText(panel, 'small', 'Цена и рейсы подтверждены поставщиком для этого предложения.');
      const button = appendText(panel, 'button', 'Выбрать подтверждённый тур', 'primary');
      button.type = 'button';
      button.dataset.andromedaSelect = ref;
      button.dataset.tid = selection.tour.id;
      return;
    }
    appendText(panel, 'strong', 'Нужно выбрать рейс');
    quote.flights.forEach(flight => appendText(panel, 'p', flightLabel(flight), 'tour-selection-note'));
    appendText(panel, 'small', 'Поставщик ещё не подтвердил итоговую цену. Этот вариант пока нельзя выбрать.');
  }

  function decorate() {
    if (!document || typeof document.querySelectorAll !== 'function') return;
    document.querySelectorAll('[data-andromeda-detail][aria-expanded="true"]').forEach(button => {
      const ref = String(button.dataset && button.dataset.andromedaDetail || '');
      if (!/^offer_[a-f0-9]{64}$/.test(ref)) return;
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

  async function verifyQuote(ref) {
    ref = String(ref || '');
    const provider = root.AnyTourAndromedaProvider;
    if (!/^offer_[a-f0-9]{64}$/.test(ref) || !provider || typeof provider.verifyQuote !== 'function') {
      quoteStates.set(ref, { status: 'error', message: 'Предложение изменилось. Обновите поиск.' });
      scheduleDecorate();
      return null;
    }
    const previous = quoteStates.get(ref);
    if (previous && previous.status === 'loading') return null;
    if (previous && previous.status === 'complete') return previous;
    quoteStates.set(ref, { status: 'loading' });
    scheduleDecorate();
    try {
      const receipt = await provider.verifyQuote(ref);
      if (!receipt || !receipt.selection || !receipt.quote) throw new Error('andromeda_quote_invalid');
      const complete = { status: 'complete', selection: receipt.selection, quote: receipt.quote };
      quoteStates.set(ref, complete);
      return complete;
    } catch (_) {
      quoteStates.set(ref, { status: 'error', message: 'Не удалось подтвердить предложение. Обновите поиск и выберите актуальный вариант.' });
      return null;
    } finally {
      scheduleDecorate();
    }
  }

  function selectVerified(ref, button) {
    const state = quoteStates.get(String(ref || ''));
    const controller = root.V2TourController;
    if (!state || state.status !== 'complete' || state.quote.state !== 'quote_verified' || !controller || typeof controller.selectProviderQuote !== 'function') return false;
    controller.selectProviderQuote(state.selection, state.quote, button);
    return true;
  }

  function reset() {
    quoteStates.clear();
    scheduleDecorate();
  }

  if (document && typeof document.addEventListener === 'function') {
    document.addEventListener('click', event => {
      const select = event.target && event.target.closest && event.target.closest('[data-andromeda-select]');
      if (select) {
        event.preventDefault();
        event.stopPropagation();
        selectVerified(select.dataset.andromedaSelect, select);
        return;
      }
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

  const api = { verifyQuote, selectVerified, decorate, states: quoteStates, version: 3 };
  root.Search3AndromedaQuoteUi = api;
})();
