'use strict';
(() => {
  const source = window.AnyTourPrototypeData;
  if (!source || typeof source.search !== 'function' || source.__searchReceiptV1 === true) return;

  const countFields = new Set([
    'hotels','offers','receivedHotels','receivedOffers','mappedHotels','mappedOffers',
    'visibleHotels','visibleOffers','scopeFilteredOffers','storedOffers','projectedOffers'
  ]);
  const statuses = new Set(['loading','complete','partial','error','skipped']);
  const safeKey = value => typeof value === 'string' && /^[A-Za-z0-9_.:+-]{1,80}$/.test(value);
  const cleanCounts = value => {
    const out = {};
    if (!value || typeof value !== 'object' || Array.isArray(value)) return out;
    for (const [key, raw] of Object.entries(value)) {
      if (!safeKey(key)) continue;
      const n = raw;
      if (typeof n === 'number' && Number.isFinite(n) && n >= 0) out[key] = n;
    }
    return out;
  };
  const cleanSource = value => {
    const out = {};
    if (!value || typeof value !== 'object' || Array.isArray(value)) return out;
    if (statuses.has(value.status)) out.status = value.status;
    for (const field of countFields) {
      const n = value[field];
      if (typeof n === 'number' && Number.isFinite(n) && n >= 0) out[field] = n;
    }
    if (value.providerOfferCounts && typeof value.providerOfferCounts === 'object') {
      out.providerOfferCounts = cleanCounts(value.providerOfferCounts);
    }
    for (const field of ['dateFrom','dateTo']) {
      if (typeof value[field] === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value[field])) out[field] = value[field];
    }
    return out;
  };
  const cleanSources = value => {
    const out = {};
    if (!value || typeof value !== 'object' || Array.isArray(value)) return out;
    for (const [provider, row] of Object.entries(value)) {
      if (!safeKey(provider)) continue;
      out[provider] = cleanSource(row);
    }
    return out;
  };
  const cleanUnion = value => {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
    const hotels = value.hotels, offers = value.offers;
    if (typeof hotels !== 'number' || !Number.isFinite(hotels) || hotels < 0
      || typeof offers !== 'number' || !Number.isFinite(offers) || offers < 0) return null;
    return {
      hotels,
      offers,
      hotelsByProvider: cleanCounts(value.hotelsByProvider),
      offersByProvider: cleanCounts(value.offersByProvider),
      providerSets: cleanCounts(value.providerSets)
    };
  };
  const resultSnapshot = event => {
    const rows = Array.isArray(event?.hotels) ? event.hotels : [];
    const hotelsByProvider = {}, offersByProvider = {}, providerSets = {};
    let offers = 0;
    for (const row of rows) {
      const rowOffers = Array.isArray(row?.offers) ? row.offers : [];
      offers += rowOffers.length;
      const providers = [...new Set(rowOffers.map(offer => offer?.provider).filter(safeKey))].sort();
      for (const provider of providers) hotelsByProvider[provider] = (hotelsByProvider[provider] || 0) + 1;
      for (const offer of rowOffers) if (safeKey(offer?.provider)) {
        offersByProvider[offer.provider] = (offersByProvider[offer.provider] || 0) + 1;
      }
      if (providers.length) {
        const set = providers.join('+');
        providerSets[set] = (providerSets[set] || 0) + 1;
      }
    }
    return {
      projection: {hotels: rows.length, offers},
      union: {hotels: rows.length, offers, hotelsByProvider, offersByProvider, providerSets}
    };
  };
  const write = receipt => {
    const host = document.getElementById('results');
    if (host) host.dataset.searchReceipt = JSON.stringify(receipt);
  };
  const originalSearch = source.search;
  const wrappedSearch = function(search, callback, ...args) {
    const receipt = {
      schemaVersion: 1,
      phase: 'loading',
      providers: {},
      sources: {},
      union: null,
      projection: {hotels: 0, offers: 0}
    };
    let projectedUnion = null;
    write(receipt);
    return originalSearch.call(this, search, event => {
      if (event && typeof event === 'object') {
        if (event.type === 'loading') receipt.phase = 'loading';
        if (event.type === 'results') {
          const snapshot = resultSnapshot(event);
          receipt.projection = snapshot.projection;
          projectedUnion = snapshot.union;
          receipt.union = snapshot.union;
        }
        if (event.type === 'provider' && safeKey(event.provider)) {
          const provider = cleanSource(event);
          receipt.providers[event.provider] = provider.status || 'loading';
        }
        if (event.type === 'complete') {
          receipt.phase = event.partial === true ? 'partial' : 'complete';
          receipt.sources = cleanSources(event.sources);
          receipt.union = projectedUnion || cleanUnion(event.union);
        }
        if (event.type === 'error') receipt.phase = 'error';
      }
      write(receipt);
      return callback(event);
    }, ...args);
  };

  const descriptors = Object.getOwnPropertyDescriptors(source);
  descriptors.search = {...descriptors.search, value: wrappedSearch};
  descriptors.__searchReceiptV1 = {value:true, enumerable:false, writable:false, configurable:false};
  window.AnyTourPrototypeData = Object.freeze(Object.defineProperties({}, descriptors));
})();