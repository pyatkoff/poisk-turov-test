'use strict';
(() => {
  if (window.AnyTourPrototypeRehydrationRetentionV1) return;
  const source = window.AnyTourPrototypeData;
  if (!source || typeof source.search !== 'function' || typeof source.resumeCached !== 'function'
    || typeof source.rehydrateCached !== 'function' || typeof source.stop !== 'function') return;

  const clone = value => typeof structuredClone === 'function'
    ? structuredClone(value)
    : JSON.parse(JSON.stringify(value));
  const text = value => String(value || '').toLocaleLowerCase('ru-RU').replace(/\s+/g, ' ').trim();
  const known = value => {
    const normalized = text(value);
    return normalized && !/уточняется/.test(normalized) ? normalized : '';
  };
  const ageMultiset = value => Array.isArray(value)
    ? value.map(Number).filter(Number.isFinite).sort((a, b) => a - b)
    : [];

  let epoch = 0, receive = null, snapshot = null, pending = null;

  function sameCurrentOffer(candidate, cached) {
    if (!candidate || !cached || candidate.cached === true || candidate.provider !== cached.provider
      || Number(candidate.hotelId) !== Number(cached.hotelId) || candidate.day !== cached.day
      || Number(candidate.nights) !== Number(cached.nights) || Number(candidate.adults) !== Number(cached.adults)
      || JSON.stringify(ageMultiset(candidate.ages)) !== JSON.stringify(ageMultiset(cached.ages))) return false;
    if (text(candidate.operator) !== text(cached.operator)) return false;
    const room = known(cached.room), meal = known(cached.mealRaw || cached.meal), placement = known(cached.placement);
    if (!room || !meal || !placement) return false;
    if (text(candidate.room) !== room) return false;
    const liveMeals = [candidate.mealRaw, candidate.meal].map(text).filter(Boolean);
    if (!liveMeals.includes(meal)) return false;
    if (text(candidate.placement) !== placement) return false;
    if (['regular', 'charter'].includes(cached.flight) && candidate.flight !== cached.flight) return false;
    return true;
  }

  function publishReplacement(cached, current, token) {
    if (token !== epoch || !receive || !Array.isArray(snapshot) || !cached || !current
      || current.cached === true || current.provider !== cached.provider
      || Number(current.hotelId) !== Number(cached.hotelId)) return false;
    const next = clone(snapshot), hotel = next.find(row => Number(row?.id) === Number(cached.hotelId));
    if (!hotel || !Array.isArray(hotel.offers) || !hotel.offers.some(row => row?.key === cached.key)) return false;
    hotel.offers = [...hotel.offers.filter(row => row?.key !== cached.key && row?.key !== current.key), clone(current)];
    snapshot = next;
    receive({type: 'results', hotels: clone(next), rehydrationRetention: true});
    return true;
  }

  function wrapReceive(callback, token) {
    return event => {
      if (token === epoch && event?.type === 'results' && Array.isArray(event.hotels)) snapshot = clone(event.hotels);
      return callback(event);
    };
  }

  function start(method, search, callback, ...rest) {
    const token = ++epoch;
    snapshot = null;
    pending = null;
    receive = typeof callback === 'function' ? wrapReceive(callback, token) : null;
    return source[method].call(source, search, receive || callback, ...rest);
  }

  const descriptors = Object.getOwnPropertyDescriptors(source);
  descriptors.search = {...descriptors.search, value(search, callback, ...rest) { return start('search', search, callback, ...rest); }};
  descriptors.resumeCached = {...descriptors.resumeCached, value(search, callback, ...rest) { return start('resumeCached', search, callback, ...rest); }};
  descriptors.stop = {...descriptors.stop, value(...args) {
    epoch++;
    snapshot = null;
    pending = null;
    receive = null;
    return source.stop.apply(source, args);
  }};
  descriptors.rehydrateCached = {...descriptors.rehydrateCached, value: async function (cached, ...args) {
    const token = epoch, result = await source.rehydrateCached.call(source, cached, ...args);
    if (token !== epoch) return result;
    const offers = Array.isArray(result?.offers) ? result.offers : [];
    const exactMatches = result?.state === 'current' ? offers.filter(item => sameCurrentOffer(item, cached)) : [];
    const exact = exactMatches.length === 1 ? exactMatches[0] : null;
    if (exact) {
      pending = null;
      publishReplacement(cached, exact, token);
    } else {
      pending = result?.state === 'current' && offers.length
        ? {token, cached, offers: offers.map(clone)}
        : null;
    }
    return result;
  }};
  descriptors.__rehydrationRetentionV1 = {value: true, enumerable: false, writable: false, configurable: false};

  document.addEventListener('click', event => {
    const button = event?.target?.closest?.('[data-action="rehydrated-offer"]');
    const draft = pending;
    if (!button || !draft || draft.token !== epoch) return;
    const offer = draft.offers.find(item => item?.key === button.dataset.key);
    if (!offer) return;
    if (publishReplacement(draft.cached, offer, draft.token)) pending = null;
  }, true);

  window.AnyTourPrototypeData = Object.freeze(Object.defineProperties({}, descriptors));
  window.AnyTourPrototypeRehydrationRetentionV1 = Object.freeze({version: 1});
})();
