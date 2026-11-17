/* SITE1719 / SEO1720. Read-only price observation, not a bookable tour or final total. */
(function () {
  'use strict';

  function day(date) { return date.toISOString().slice(0, 10); }
  function windowRange() {
    const begin = new Date();
    begin.setUTCHours(0, 0, 0, 0);
    begin.setUTCDate(begin.getUTCDate() + 1);
    const end = new Date(begin);
    end.setUTCDate(end.getUTCDate() + 119);
    return { from: day(begin), to: day(end) };
  }
  function validAmount(n) { return typeof n === 'number' && Number.isFinite(n) && n >= 0 && n <= 100000000; }
  function formatRub(n) { return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(n); }
  function eligible(data, range) {
    if (!data || data.ok !== true || data.format !== 'anytour-hotel-monitor-prices-v1'
      || data.identityNamespace !== 'tourvisor' || data.source !== 'scheduled_monitor'
      || data.observationWindowHours !== 36 || data.supplierRequests !== 0
      || data.writes !== 0 || data.automaticDispatch !== false) return null;
    const s = data.scope;
    if (!s || !Array.isArray(s.tourvisorHotelIds) || s.tourvisorHotelIds.length !== 1
      || s.tourvisorHotelIds[0] !== 268 || s.departureId !== 1
      || s.dateFrom !== range.from || s.dateTo !== range.to || s.nightsFrom !== 5
      || s.nightsTo !== 14 || s.adults !== 2 || s.childrenCount !== 0 || s.currency !== 'RUB') return null;
    if (!Array.isArray(data.items) || data.items.length !== 1) return null;
    const item = data.items[0];
    if (!item || item.tourvisorHotelId !== 268 || item.state !== 'observed'
      || !Array.isArray(item.offers) || item.offers.length > 3) return null;
    const exported = Date.parse(data.generatedAt);
    if (!Number.isFinite(exported) || Math.abs(Date.now() - exported) > 300000) return null;
    const now = Date.now();
    const accepted = item.offers.filter(o => {
      if (!o || o.tourvisorHotelId !== 268 || o.departureId !== 1
        || o.adults !== 2 || o.childrenCount !== 0 || o.currency !== 'RUB'
        || o.source !== 'scheduled_monitor' || o.priceKind !== 'listing_observation'
        || o.currentPriceVerified !== false || o.fullPackagePriceVerified !== false
        || o.availabilityVerified !== false || o.fuelIncluded !== null) return false;
      if (!validAmount(o.price) || o.price === 0
        || (o.fuelCharge !== null && !validAmount(o.fuelCharge))
        || !Number.isInteger(o.nights) || o.nights < 5 || o.nights > 14
        || !Number.isInteger(o.ageSecondsAtExport) || o.ageSecondsAtExport < 0
        || o.ageSecondsAtExport >= 129600) return false;
      if (typeof o.departureDate !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(o.departureDate)
        || o.departureDate < range.from || o.departureDate > range.to) return false;
      if (!/^[0-9a-f]{64}$/.test(o.recordId || '') || !o.tourId || !o.searchId) return false;
      if (typeof o.roomType !== 'string' || o.roomType.length > 120) return false;
      const expiry = Date.parse(o.expiresAt);
      return Number.isFinite(expiry) && expiry > now && expiry <= exported + 901000;
    });
    if (!accepted.length) return null;
    return accepted.sort((a, b) => a.price - b.price)[0];
  }

  function start() {
    const root = document.querySelector('[data-jaz-price-example]');
    if (!root || typeof window.fetch !== 'function') return;
    const button = root.querySelector('[data-price-load]');
    const status = root.querySelector('[data-price-status]');
    const result = root.querySelector('[data-price-result]');
    const amount = root.querySelector('[data-price-amount]');
    const terms = root.querySelector('[data-price-terms]');
    const extra = root.querySelector('[data-price-extra]');
    const source = root.querySelector('[data-price-source]');
    const link = root.querySelector('[data-price-search-link]');
    if (!button || !status || !result || !amount || !terms || !extra || !source || !link) return;
    let timeout = null;
    function hide(state, message) {
      if (timeout !== null) clearTimeout(timeout);
      timeout = null;
      delete root.dataset.priceExpiry;
      result.hidden = true;
      link.hidden = true;
      root.dataset.priceState = state;
      status.textContent = message;
    }
    button.addEventListener('click', async function () {
      if (button.disabled) return;
      hide('loading', 'Читаем сохранённые наблюдения. Новый поиск у поставщика не запускается.');
      button.disabled = true;
      const range = windowRange();
      try {
        const endpoint = new URL(root.dataset.priceEndpoint || '', window.location.href);
        if (endpoint.origin !== window.location.origin || endpoint.pathname !== '/data/hotel-monitor-prices-read-v1.php'
          || endpoint.search || endpoint.hash) throw new Error('Unsafe price endpoint');
        endpoint.searchParams.append('tourvisorHotelIds[]', '268');
        endpoint.searchParams.set('departureId', '1');
        endpoint.searchParams.set('dateFrom', range.from);
        endpoint.searchParams.set('dateTo', range.to);
        const response = await fetch(endpoint.href, {
          method: 'GET', credentials: 'omit', cache: 'no-store',
          headers: { Accept: 'application/json' }
        });
        if (!response.ok) throw new Error('Price reader unavailable');
        const data = await response.json();
        const offer = eligible(data, range);
        if (!offer) {
          hide('empty', 'Свежего подходящего наблюдения нет. Это не означает, что туров нет: проверьте свои даты в поиске.');
          return;
        }
        const target = new URL(root.dataset.priceSearch || '', window.location.href);
        if (target.origin !== window.location.origin || !target.pathname.endsWith('/poisk-turov/')
          || target.searchParams.get('hotel') !== '268' || target.searchParams.get('country') !== '1'
          || target.searchParams.get('region') !== '5') throw new Error('Invalid site search context');
        target.searchParams.set('search3_restore', '1');
        target.searchParams.set('from', '1');
        target.searchParams.set('dateFrom', offer.departureDate);
        target.searchParams.set('dateTo', offer.departureDate);
        target.searchParams.set('daysFrom', String(offer.nights));
        target.searchParams.set('daysTill', String(offer.nights));
        target.searchParams.set('count_people', '2');
        target.searchParams.set('child_count', '0');
        target.searchParams.delete('child_age[]');
        target.searchParams.delete('price_from');
        target.searchParams.delete('price_till');
        if (offer.mealId === 7) target.searchParams.set('food', '7');
        else target.searchParams.delete('food');
        const observedDate = new Date(offer.departureDate + 'T12:00:00Z');
        const prettyDate = observedDate.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
        amount.textContent = formatRub(offer.price);
        terms.textContent = 'Москва · ' + prettyDate + ' · ' + offer.nights + ' ночей · 2 взрослых, без детей'
          + (offer.mealId === 7 ? ' · всё включено' : '')
          + ' · номер: ' + (offer.roomType.trim() || 'категория по условиям тура');
        extra.textContent = (offer.fuelCharge === null
          ? 'Возможные доплаты не подтверждены.'
          : 'В записи указан топливный сбор ' + formatRub(offer.fuelCharge) + '; включён ли он в сумму, неизвестно.')
          + ' Наличие не проверено. Категория номера может отличаться от семейных апартаментов.';
        source.textContent = 'Запись мониторинга: ' + String(offer.observedAtRaw || '').slice(0, 35)
          + ' (часовой пояс неизвестен).';
        link.href = target.href;
        link.hidden = false;
        result.hidden = false;
        root.dataset.priceState = 'observed';
        status.textContent = 'Найден ориентир по цене; предложение проверьте в поиске.';
        root.dataset.priceExpiry = String(Date.parse(offer.expiresAt));
        const expiresIn = Date.parse(offer.expiresAt) - Date.now();
        timeout = setTimeout(function () {
          hide('expired', 'Ценовое наблюдение устарело. Нажмите кнопку для обновления или откройте подбор.');
        }, Math.max(1, expiresIn));
      } catch (_) {
        hide('error', 'Не удалось получить ценовой ориентир. Подбор туров доступен по кнопке ниже.');
      } finally {
        button.disabled = false;
        button.textContent = 'Обновить ценовой ориентир';
      }
    });
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible' && timeout !== null) {
        const current = root.dataset.priceState;
        if (current === 'observed' && root.dataset.priceExpiry && Date.now() >= Number(root.dataset.priceExpiry))
          hide('expired', 'Ценовое наблюдение устарело. Обновите данные или откройте подбор.');
      }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else start();
}());
