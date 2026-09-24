'use strict';
(() => {
  if (window.AnyTourPrototypeCalendarExactHotelV1) return;
  const source = window.AnyTourPrototypeData;
  const localParser = window.AnyTourLocalDbProviderV1;
  if (!source || typeof source.search !== 'function' || typeof source.resumeCached !== 'function'
    || typeof source.calendarPrices !== 'function' || typeof source.params !== 'function'
    || typeof source.sameScope !== 'function' || typeof source.project !== 'function'
    || !localParser || typeof localParser.parse !== 'function') return;

  const clone = value => typeof structuredClone === 'function'
    ? structuredClone(value)
    : JSON.parse(JSON.stringify(value));
  const plus = (day, count) => new Date(new Date(day + 'T12:00:00Z').getTime() + count * 86400000).toISOString().slice(0, 10);
  const hotelLegacyIds = new Map();

  function cleanLegacyIds(value) {
    if (!Array.isArray(value)) return [];
    const ids = [...new Set(value.map(id => String(id)).filter(id => /^[1-9][0-9]*$/.test(id)))];
    ids.sort((a, b) => Number(a) - Number(b));
    return ids;
  }

  function rememberHotels(rows) {
    if (!Array.isArray(rows)) return;
    for (const row of rows) {
      const id = Number(row?.id), legacyIds = cleanLegacyIds(row?.legacyIds);
      if (Number.isSafeInteger(id) && id > 0 && legacyIds.length) hotelLegacyIds.set(id, legacyIds);
    }
  }

  function wrapReceive(callback) {
    return event => {
      if (event?.type === 'results') rememberHotels(event.hotels);
      return callback(event);
    };
  }

  async function exactWindow(search, from, to, signal, filters, legacyIds, hotelId) {
    if (signal?.aborted) throw new DOMException('Aborted', 'AbortError');
    const scope = {...clone(search), from, to};
    const request = source.params(scope, legacyIds, filters);
    if (!Array.isArray(request.hotelIds) || request.hotelIds.map(String).join(',') !== legacyIds.join(',')) {
      throw new Error('Exact hotel calendar identity unavailable');
    }
    const response = await fetch('/_preview/search3-local-candidate/data/search3-local-results-read-v1.php', {
      method: 'POST', credentials: 'same-origin', cache: 'no-store', signal,
      headers: {'Content-Type': 'application/json', 'X-Requested-With': 'AnyTourSearch3'},
      body: JSON.stringify({params: request})
    });
    if (!response.ok) throw new Error('Цены выбранного отеля из базы временно недоступны.');
    const payload = await response.json(), data = payload?.data;
    if (payload?.ok !== true || !data || !source.sameScope(request, data.scope)) {
      throw new Error('Ответ базы не соответствует выбранному отелю.');
    }
    const parsed = localParser.parse(data);
    if (!parsed || !Array.isArray(parsed.hotels)) throw new Error('Некорректный ответ базы выбранного отеля.');
    const list = parsed.hotels.map(group => {
      if (!group || Number(group.anytourHotelId) !== hotelId || !group.hotel || !Array.isArray(group.offers)) {
        throw new Error('База вернула другой отель.');
      }
      const returnedLegacyIds = cleanLegacyIds(group.offers.map(offer => offer?.legacyHotelId));
      if (returnedLegacyIds.some(id => !legacyIds.includes(id))) throw new Error('База вернула другой отель.');
      return {
        ...group.hotel,
        anytourHotelId: group.anytourHotelId,
        canonicalLegacyIds: returnedLegacyIds,
        tours: group.offers.map(offer => offer.tour)
      };
    });
    const rows = source.project(list, scope);
    if (!Array.isArray(rows) || rows.some(row => Number(row?.id) !== hotelId)) throw new Error('База вернула другой отель.');
    rememberHotels(rows);
    return rows;
  }

  async function exactHotelCalendarPrices(search, from, to, signal, filters, onUpdate, legacyIds, hotelId) {
    const snapshot = {hotels: [], observations: []};
    const show = () => {
      if (!signal?.aborted && typeof onUpdate === 'function') onUpdate(clone(snapshot));
    };
    try {
      for (let start = from; start <= to; start = plus(start, 22)) {
        const end = plus(start, 21) < to ? plus(start, 21) : to;
        const rows = await exactWindow(search, start, end, signal, filters, legacyIds, hotelId);
        snapshot.hotels.push(...rows);
        show();
      }
      return {...snapshot, partial: false};
    } catch (error) {
      if (signal?.aborted || error?.name === 'AbortError') throw error;
      return {...snapshot, partial: true};
    }
  }

  const descriptors = Object.getOwnPropertyDescriptors(source);
  descriptors.search = {...descriptors.search, value(search, callback, ...rest) {
    return source.search.call(source, search, typeof callback === 'function' ? wrapReceive(callback) : callback, ...rest);
  }};
  descriptors.resumeCached = {...descriptors.resumeCached, value(search, callback, ...rest) {
    return source.resumeCached.call(source, search, typeof callback === 'function' ? wrapReceive(callback) : callback, ...rest);
  }};
  if (typeof source.lookupHotels === 'function') descriptors.lookupHotels = {...descriptors.lookupHotels, value: async function (...args) {
    const rows = await source.lookupHotels.apply(source, args); rememberHotels(rows); return rows;
  }};
  if (typeof source.restoreHotel === 'function') descriptors.restoreHotel = {...descriptors.restoreHotel, value: async function (...args) {
    const row = await source.restoreHotel.apply(source, args); rememberHotels([row]); return row;
  }};
  if (typeof source.savedHotels === 'function') descriptors.savedHotels = {...descriptors.savedHotels, value: async function (...args) {
    const rows = await source.savedHotels.apply(source, args); rememberHotels(rows); return rows;
  }};
  descriptors.calendarPrices = {...descriptors.calendarPrices, value(search, from, to, signal, filters = {}, onUpdate) {
    const hotelId = Number(filters?.hotelId);
    if (!Number.isSafeInteger(hotelId) || hotelId < 1) {
      return source.calendarPrices.call(source, search, from, to, signal, filters, onUpdate);
    }
    const legacyIds = hotelLegacyIds.get(hotelId) || [];
    if (!legacyIds.length) {
      if (signal?.aborted) return Promise.reject(new DOMException('Aborted', 'AbortError'));
      const snapshot = {hotels: [], observations: [], partial: true};
      if (typeof onUpdate === 'function') onUpdate(clone(snapshot));
      return Promise.resolve(snapshot);
    }
    return exactHotelCalendarPrices(search, from, to, signal, clone(filters), onUpdate, [...legacyIds], hotelId);
  }};
  descriptors.__calendarExactHotelV1 = {value: true, enumerable: false, writable: false, configurable: false};

  window.AnyTourPrototypeData = Object.freeze(Object.defineProperties({}, descriptors));
  window.AnyTourPrototypeCalendarExactHotelV1 = Object.freeze({version: 1});
})();
