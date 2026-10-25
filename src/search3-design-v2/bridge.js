'use strict';
// Headless seam only: canonical adapter owns HTTP, IDs, supplier generations,
// price arithmetic, quote receipts and lead authority. This file owns no layout.
(function (root) {
  function create(options = {}) {
    const data = options.data || root.AnyTourPrototypeData;
    if (!data) throw new Error('Канонический адаптер поиска не загружен.');
    const address = options.location || root.location;
    const initialParams = new URLSearchParams(address?.search || '');
    const requestedSearch = initialParams.get('searched') === '1';
    let initialization = null, hotels = [], searchGeneration = 0, selectionGeneration = 0, dictionaryGeneration = 0, flightGeneration = 0;
    let currentSearch = null, selected = null, selectionPromise = null, followupPromise = null, latest = {hotels, status: 'idle', searchGeneration: 0};
    let dictionarySnapshot = null;
    const subscribers = new Set();
    function publish(snapshot) {
      latest = {...snapshot, searchGeneration};
      for (const listener of subscribers) { try { listener(latest); } catch (error) { root.console?.error?.(error); } }
      return latest;
    }
    function subscribe(listener, {replay = true} = {}) {
      if (typeof listener !== 'function') throw new TypeError('Snapshot listener is required');
      subscribers.add(listener); if (replay) listener(latest);
      return () => subscribers.delete(listener);
    }
    const countries = () => (data.catalog?.countries || []).map(row => ({id: String(row.id), name: data.text(row)}));
    const positive = (name, fallback, max) => {
      const raw = initialParams.get(name);
      if (raw === null) return fallback;
      if (!/^(?:[1-9][0-9]*)$/.test(raw) || Number(raw) > max) throw new Error('Некорректный параметр поездки: ' + name);
      return Number(raw);
    };
    const date = (name, fallback) => {
      const value = initialParams.get(name);
      if (value === null) return fallback;
      if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) throw new Error('Некорректная дата поездки: ' + name);
      const parsed = new Date(value + 'T12:00:00Z');
      if (!Number.isFinite(parsed.getTime()) || parsed.toISOString().slice(0, 10) !== value) throw new Error('Некорректная дата поездки: ' + name);
      return value;
    };
    const filters = () => ({hotelId: 0, hotelIds: [], q: '', stars: [], meals: [], resorts: [],
      operators: [], flight: [], amenities: [], min: 0, max: null, rating: false,
      beach: false, family: false, spa: false});
    const dictionaries = () => ({origins: (data.catalog?.departures || []).map(row => data.text(row)),
      mealGroups: root.AnyTourLocalDbProviderV1?.mealFilterGroups?.(data.catalog?.mealPlans || []) || [],
      countries: countries(), meals: (data.catalog?.mealPlans || []).map(plan => ({
        id: plan.id, nameRu: plan.nameRu, code: plan.code, nativeIds: plan.nativeIds
      }))});
    function restoreFilters() {
      const restored = filters();
      const list = name => {
        const raw = initialParams.get(name);
        return raw === null || raw === '' ? [] : raw.split('|');
      };
      const stars = list('stars');
      if (stars.some(value => !/^[1-5]$/.test(value))) throw new Error('Проверьте категории отелей в ссылке.');
      restored.stars = [...new Set(stars.map(Number))];
      restored.meals = [...new Set(list('meals'))];
      if (restored.meals.some(value => dictionaries().meals.filter(plan => plan.nameRu === value).length !== 1)) throw new Error('Питание из ссылки не подтверждено каталогом.');
      restored.resorts = [...new Set(list('resorts'))];
      if (restored.resorts.some(value => !value.trim() || value.length > 300)) throw new Error('Проверьте курорты в ссылке.');
      const ownIds = list('hotelIds');
      if (ownIds.some(value => !/^[1-9][0-9]*$/.test(value) || !Number.isSafeInteger(Number(value)))) throw new Error('Проверьте выбранные отели в ссылке.');
      restored.hotelIds = [...new Set(ownIds.map(Number))];
      if (initialParams.has('hotelId')) {
        const raw = initialParams.get('hotelId');
        if (!/^[0-9]+$/.test(raw) || !Number.isSafeInteger(Number(raw))) throw new Error('Проверьте выбранный отель в ссылке.');
        restored.hotelId = Number(raw);
        if (restored.hotelId && !restored.hotelIds.includes(restored.hotelId)) restored.hotelIds.push(restored.hotelId);
      }
      for (const name of ['min', 'max']) {
        const raw = initialParams.get(name);
        if (raw === null || raw === '') continue;
        if (!/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/.test(raw) || !Number.isFinite(Number(raw))) throw new Error('Проверьте бюджет в ссылке.');
        restored[name] = Number(raw);
      }
      if (restored.max !== null && restored.min > restored.max) throw new Error('Минимальный бюджет больше максимального.');
      return restored;
    }
    function present(rows) {
      const names = new Map(countries().map(row => [row.id, row.name]));
      return rows.map(hotel => ({...hotel,
        location: [names.get(String(hotel.country)), hotel.subRegion || hotel.region || hotel.resort].filter(Boolean).join(' · '),
        // Offer objects and receipt-bearing raw objects retain their identity.
        offers: hotel.offers || []}));
    }
    function validateSearch(search) {
      if (!search || !countries().some(row => row.id === String(search.country))
        || !(data.catalog?.departures || []).some(row => data.text(row) === search.origin)
        || !/^\d{4}-\d{2}-\d{2}$/.test(search.from || '') || !/^\d{4}-\d{2}-\d{2}$/.test(search.to || '')
        || search.from > search.to || !Number.isSafeInteger(search.adults) || search.adults < 1
        || !Number.isSafeInteger(search.minNights) || !Number.isSafeInteger(search.maxNights)
        || search.minNights < 1 || search.maxNights < search.minNights
        || !Array.isArray(search.ages) || search.ages.some(age => !Number.isSafeInteger(age) || age < 0 || age > 17)) {
        throw new Error('Проверьте параметры поездки.');
      }
      // This calls the established intent validator only; it sends no request.
      data.params(search, [], filters());
    }
    async function init() {
      if (initialization) return initialization;
      initialization = (async () => {
        const catalog = await data.init(initialParams.get('origin') || 'Москва');
        if (initialParams.has('origin') && (!(data.catalog?.departures || []).some(row => data.text(row) === initialParams.get('origin')) || catalog.origin !== initialParams.get('origin'))) throw new Error('Город вылета из ссылки не подтверждён каталогом.');
        const today = new Date(), pad = part => String(part).padStart(2, '0');
        const localToday = today.getFullYear() + '-' + pad(today.getMonth() + 1) + '-' + pad(today.getDate());
        const fromDefault = new Date(Date.parse(localToday + 'T12:00:00Z') + 14 * 86400000).toISOString().slice(0, 10);
        const toDefault = new Date(Date.parse(fromDefault + 'T12:00:00Z') + 6 * 86400000).toISOString().slice(0, 10);
        const requestedCountry = initialParams.get('country'), available = countries();
        if (requestedCountry && !available.some(row => row.id === requestedCountry)) throw new Error('Страна из ссылки не подтверждена каталогом.');
        const country = available.find(row => row.id === requestedCountry)?.id
          || available.find(row => row.name === 'Турция')?.id || available[0]?.id;
        const agesRaw = initialParams.get('ages') || '';
        if (agesRaw && !/^(?:0|[1-9]|1[0-7])(?:,(?:0|[1-9]|1[0-7]))*$/.test(agesRaw)) throw new Error('Проверьте возраст детей в ссылке.');
        const ages = agesRaw ? agesRaw.split(',').map(Number) : [];
        const search = {origin: catalog.origin, country, from: date('from', fromDefault), to: date('to', toDefault),
          minNights: positive('minNights', 7, 28), maxNights: positive('maxNights', 7, 28),
          adults: positive('adults', 2, 6), ages};
        const restored = restoreFilters();
        validateSearch(search);
        currentSearch = search;
        dictionarySnapshot = {type: 'complete', origin: search.origin, generation: dictionaryGeneration, dictionaries: dictionaries()};
        return {search, dictionaries: dictionaries(), filters: restored, hotels, status: 'idle', requestedSearch};
      })();
      try { return await initialization; }
      catch (error) { initialization = null; throw error; }
    }
    async function changeOrigin(origin) {
      await init();
      if (!(data.catalog?.departures || []).some(row => data.text(row) === origin)) throw new Error('Город вылета не подтверждён каталогом.');
      const epoch = ++dictionaryGeneration;
      const previous = dictionarySnapshot;
      dictionarySnapshot = {type: 'pending', origin, generation: epoch, dictionaries: dictionaries()};
      publish({...latest, dictionaryEvent: dictionarySnapshot});
      try {
        const result = await data.countries(origin);
        if (!result || epoch !== dictionaryGeneration || result.origin && result.origin !== origin) throw new Error('Список стран изменился. Повторите выбор.');
        dictionarySnapshot = {type: 'complete', origin, generation: epoch, dictionaries: dictionaries()};
        publish({...latest, dictionaryEvent: dictionarySnapshot});
        return dictionarySnapshot.dictionaries;
      } catch (error) {
        if (epoch === dictionaryGeneration) {
          dictionarySnapshot = {type: 'error', origin: previous?.origin || currentSearch.origin, generation: epoch, dictionaries: dictionaries(), message: error.message};
          publish({...latest, dictionaryEvent: dictionarySnapshot});
        }
        throw error;
      }
    }
    async function search(search, selectedFilters, onEvent) {
      await init(); validateSearch(search);
      const epoch = ++searchGeneration;
      ++selectionGeneration; ++flightGeneration; selected = null; selectionPromise = null; followupPromise = null; currentSearch = {...search, ages: [...search.ages]};
      const intent = currentSearch;
      const constraints = {...filters(), ...selectedFilters};
      const retainedHotels = hotels;
      // A new intent starts with an empty presentation. Retained profiles can
      // prove hotel identity, but their previous offers are never new results.
      hotels = [];
      publish({hotels, status: 'loading', message: '', sources: null, search: intent, filters: constraints});
      const ownIds = constraints.hotelIds?.length ? constraints.hotelIds : constraints.hotelId ? [constraints.hotelId] : [];
      const supplierIds = [];
      try {
        for (const id of ownIds) {
          const hotel = retainedHotels.find(row => row.id === id) || await data.restoreHotel(id, search.country);
          if (epoch !== searchGeneration) throw new Error('Поиск заменён новым запросом.');
          if (!hotel || String(hotel.country) !== String(search.country) || !hotel.legacyIds?.length) {
            throw new Error('Не удалось подтвердить поисковую связь выбранного отеля.');
          }
          supplierIds.push(...hotel.legacyIds);
        }
      } catch (error) {
        if (epoch === searchGeneration) publish({hotels, status: 'error', message: error.message, error: error.message, search: intent, filters: constraints});
        throw error;
      }
      let terminal = null;
      const emit = event => {
        if (epoch !== searchGeneration) return;
        if (event.type === 'results') hotels = present(event.hotels || []);
        // Canonical profile enrichment can publish results after completion.
        // Only a new search generation or an explicit terminal event changes
        // that generation's terminal status; metadata is not another search.
        if (event.type === 'error' || event.type === 'complete') {
          terminal = {status: event.type === 'error' ? 'error' : event.partial ? 'partial' : 'complete',
            message: event.message || '', sources: event.sources && typeof event.sources === 'object' ? event.sources : null};
        }
        const status = terminal?.status || 'loading', message = terminal ? terminal.message : event.message || '';
        publish({hotels, status, message, sources: terminal?.sources || null, event, search: intent, filters: constraints,
          error: status === 'error' ? message || 'Не удалось завершить поиск.' : null});
        if (typeof onEvent === 'function') onEvent(latest);
      };
      if (epoch !== searchGeneration) throw new Error('Поиск заменён новым запросом.');
      try { await data.search(intent, emit, [...new Set(supplierIds)], constraints); }
      catch (error) {
        if (epoch === searchGeneration) emit({type: 'error', message: error.message});
        throw error;
      }
      return latest;
    }
    function stayLabels(offer, tour) {
      if (!tour) return {room: offer.room, meal: offer.meal};
      if (typeof data.stayLabel !== 'function') return null;
      const quoted = {...offer, tour, room: data.text(tour.roomType) || offer.room,
        meal: data.meal(tour.meal) || offer.meal};
      // Explicit absence preserves native raw qualifiers even when meal aliases match.
      return {room: data.stayLabel(quoted, 'room', null), meal: data.stayLabel(quoted, 'meal', null)};
    }
    function readOffers(id) {
      const hotel = hotels.find(row => row.id === Number(id));
      return {offers: hotel?.offers || [], status: latest.status};
    }
    async function readHotel(id) {
      const hotel = hotels.find(row => row.id === Number(id));
      if (!hotel) throw new Error('Отель отсутствует в текущей выдаче.');
      return {hotel, description: hotel.raw?.description || hotel.note || '', photos: hotel.photos || [],
        amenities: hotel.amenities || []};
    }
    function findOffer(key) {
      for (const hotel of hotels) {
        const offer = hotel.offers.find(row => row.key === key);
        if (offer) return offer;
      }
      return null;
    }
    const amount = value => data.amount ? data.amount(value) : Number(typeof value === 'object' ? value?.value ?? value?.amount : value);
    function currentSelection() {
      if (!selected || selected.selection !== selectionGeneration || selected.searchGeneration !== searchGeneration) return null;
      return selected;
    }
    // Classification comes from the canonical owner, never from operator names.
    const failureInfo = error => Object.freeze({retryable: error?.retryable !== false});
    function unavailable(value) { return value?.quoteFailure?.retryable === false || value?.flightsFailure?.retryable === false; }
    function unavailableError(value) {
      return Object.assign(new Error(value.quoteError || value.flightsError || 'Онлайн-проверка этого предложения недоступна.'), {retryable: false});
    }
    function requireAvailable(value) { if (unavailable(value)) throw unavailableError(value); }
    function quotedSelection(fields) {
      const next = {...selected, ...fields};
      next.priceAccepted = next.total === next.offer.total;
      selected = next; return next;
    }
    function quoteOffer(key, {force = false, expectedOffer = null} = {}) {
      const offer = findOffer(key);
      if (!offer || expectedOffer && expectedOffer !== offer || offer.cached || !['tourvisor', 'anex'].includes(offer.provider)) {
        return Promise.reject(new Error('Этот источник требует отдельной актуализации точного предложения.'));
      }
      const retained = currentSelection();
      if (retained?.offer === offer && unavailable(retained)) return Promise.reject(unavailableError(retained));
      if (!force && currentSelection()?.offer === offer) return selectionPromise || Promise.resolve(selected);
      const epoch = searchGeneration, selection = ++selectionGeneration;
      ++flightGeneration; followupPromise = null;
      selected = {...offer, offer, selection, searchGeneration: epoch, loading: true, pricePending: true, priceAccepted: false,
        flightsLoading: false, flightsError: '', quoteFailure: null, flightsFailure: null, flightChoiceId: null, variants: []};
      const current = () => epoch === searchGeneration && selection === selectionGeneration && selected?.offer === offer;
      const assertCurrent = () => { if (!current()) throw new Error('Выбранный тур изменился.'); };
      selectionPromise = (async () => {
        try {
          if (offer.provider === 'anex') {
            if (offer.raw?.anexKind !== 'concrete') throw new Error('Сначала откройте конкретные варианты ANEX.');
            const context = await data.verifyAnexConcrete(offer); assertCurrent();
            const quoteResult = await data.verifyAnexPackage(offer); assertCurrent();
            selected = {...selected, loading: false, context, quoteResult, variants: quoteResult.choices || [], quoteError: ''};
            return selected;
          }
          const tour = await data.quote(offer); assertCurrent();
          const total = amount(tour.price);
          if (!Number.isFinite(total) || total <= 0) throw new Error('Итоговая стоимость тура не подтверждена.');
          selected = {...selected, tour, total, quoteListingTotal: offer.total, flightsLoading: true, loading: false, quoteError: ''};
          try {
            const variants = await data.flights(tour); assertCurrent();
            selected = {...selected, variants, flightsLoading: false, flightsLoaded: true, flightsError: '', pricePending: true};
          } catch (error) {
            assertCurrent(); selected = {...selected, flightsLoading: false, flightsLoaded: false, flightsError: error.message, flightsFailure: failureInfo(error), pricePending: true};
          }
          return selected;
        } catch (error) {
          if (current()) selected = {...selected, loading: false, flightsLoading: false, quoteError: error.message, quoteFailure: failureInfo(error)};
          throw error;
        } finally { if (current()) selectionPromise = null; }
      })();
      return selectionPromise;
    }
    function selectFlight(originalIndex) {
      requireAvailable(currentSelection());
      if (!currentSelection()?.tour || selected.provider !== 'tourvisor' || selected.loading || selected.flightsLoading || !Number.isSafeInteger(originalIndex)
        || !selected.variants?.[originalIndex]) throw new Error('Выберите перелёт из текущего предложения.');
      const total = data.variantPrice(selected.tour, selected.variants[originalIndex]);
      if (!Number.isFinite(total) || total <= 0) throw new Error('Итоговая стоимость перелёта не подтверждена.');
      ++flightGeneration;
      return quotedSelection({flightChoiceId: String(originalIndex), total, pricePending: false, flightsError: ''});
    }
    function deferFlight() {
      const value = currentSelection(), total = amount(value?.tour?.price);
      requireAvailable(value);
      if (!value || value.provider !== 'tourvisor' || value.loading || value.flightsLoading || !Number.isFinite(total) || total <= 0) throw new Error('Сначала подтвердите актуальную цену тура.');
      ++flightGeneration;
      return quotedSelection({flightChoiceId: null, total, variants: [], pricePending: false, flightsError: '', flightDeferred: true});
    }
    function trackFollowup(operation) {
      const promise = operation.finally(() => { if (followupPromise === promise) followupPromise = null; });
      followupPromise = promise; return promise;
    }
    async function loadFlights() {
      const value = currentSelection();
      requireAvailable(value);
      if (!value?.tour || value.provider !== 'tourvisor' || value.loading || value.flightsLoading) throw new Error('Сначала подтвердите актуальное предложение.');
      const epoch = searchGeneration, selection = selectionGeneration, flight = ++flightGeneration;
      selected = {...value, flightsLoading: true, flightsError: '', pricePending: true, priceAccepted: false, flightChoiceId: null};
      const current = () => epoch === searchGeneration && selection === selectionGeneration && flight === flightGeneration;
      try {
        const variants = await data.flights(value.tour);
        if (!current()) throw new Error('Выбранный тур изменился.');
        selected = {...selected, variants, flightsLoading: false, flightsLoaded: true, flightsFailure: null}; return selected;
      } catch (error) {
        if (current()) selected = {...selected, flightsLoading: false, flightsError: error.message, flightsFailure: failureInfo(error)};
        throw error;
      }
    }
    function retryFlights() { return trackFollowup(loadFlights()); }
    async function expandOfferGroup(key, {expectedOffer = null} = {}) {
      const offer = findOffer(key), epoch = searchGeneration;
      if (!offer || expectedOffer && offer !== expectedOffer || offer.provider !== 'anex' || offer.raw?.anexKind !== 'group_minimum') throw new Error('Откройте групповой вариант ANEX из текущей выдачи.');
      const result = await data.expandAnexGroup(offer);
      if (epoch !== searchGeneration) throw new Error('Условия поиска изменились.');
      const hotel = hotels.find(row => row.id === offer.hotelId);
      if (!hotel || result.hotelId !== hotel.id || !Array.isArray(result.offers)) throw new Error('Не удалось подтвердить варианты отеля.');
      const existing = new Set(hotel.offers.map(row => row.key));
      hotel.offers.push(...result.offers.filter(row => !existing.has(row.key)));
      publish({...latest, hotels}); return result.offers;
    }
    async function calculateProviderFlight(choiceRef) {
      const value = currentSelection();
      requireAvailable(value);
      if (!value || value.provider !== 'anex' || !value.quoteResult?.choices?.some(choice => choice.choiceRef === choiceRef)) throw new Error('Выберите перелёт из текущего ответа ANEX.');
      const epoch = searchGeneration, selection = selectionGeneration, flight = ++flightGeneration;
      selected = {...value, pricePending: true, priceAccepted: false, flightsLoading: true, flightsError: '', receipt: null};
      const current = () => epoch === searchGeneration && selection === selectionGeneration && flight === flightGeneration;
      try {
        const result = await data.verifyAnexPackage(value.offer, choiceRef);
        if (!current()) throw new Error('Выбранный тур изменился.');
        const total = Number(result?.finalPrice?.amount);
        if (result?.state !== 'quote_verified' || result.finalPriceVerified !== true || result.choice?.choiceRef !== choiceRef || result.finalPrice?.currency !== 'RUB' || !Number.isFinite(total) || total <= 0 || result.expiresAt * 1000 <= Date.now()) throw new Error('Цена выбранного перелёта не подтверждена.');
        const offer = value.offer, raw = offer.raw, hotel = hotels.find(row => row.id === offer.hotelId);
        const receipt = Object.freeze({provider: 'anex', offerRef: raw.offerRef, searchRef: raw.searchRef, generation: raw.anexGeneration,
          departure: String(offer.origin || currentSearch.origin || ''), localHotelId: Number(raw.anexLocalHotelId), priceKind: 'verified', finalPriceVerified: true,
          choiceRef, expiresAt: result.expiresAt, hotel: String(hotel?.name || ''), country: countries().find(row => row.id === String(hotel?.country))?.name || '',
          resort: String(hotel?.resort || hotel?.region || ''), day: offer.day, nights: offer.nights, adults: offer.adults,
          ages: Object.freeze([...offer.ages]), room: String(offer.room || ''), meal: String(offer.meal || ''), operator: 'ANEX', price: total, currency: 'RUB',
          flights: Object.freeze(result.choice.legs.map((leg, index) => Object.freeze({direction: String(index), name: leg.label})))});
        return quotedSelection({quoteResult: result, receipt, flightChoiceId: choiceRef, total, pricePending: false, flightsLoading: false, flightsError: ''});
      } catch (error) {
        if (current()) selected = {...selected, flightsLoading: false, flightsError: error.message, flightsFailure: failureInfo(error)};
        throw error;
      }
    }
    function selectProviderFlight(choiceRef) { return trackFollowup(calculateProviderFlight(choiceRef)); }
    function acceptPrice() {
      const value = currentSelection();
      requireAvailable(value);
      if (!value || value.loading || value.pricePending || value.flightsLoading || value.flightsError || value.quoteError || !Number.isFinite(value.total) || value.total <= 0) throw new Error('Сначала подтвердите стоимость выбранного тура.');
      selected = {...value, priceAccepted: true}; return selected;
    }
    function previewContact(formData) {
      const value = currentSelection();
      requireAvailable(value);
      if (!value || value.loading || value.pricePending || value.flightsLoading || value.flightsError || value.quoteError) throw new Error('Сначала проверьте выбранный тур.');
      if (!value.priceAccepted) throw new Error('Подтвердите изменившуюся стоимость тура.');
      if (value.provider === 'anex') {
        if (!value.receipt || value.receipt.expiresAt * 1000 <= Date.now()) throw new Error('Срок подтверждённой цены истёк.');
        if (!data.anexPackagePrices(value.offer).some(row => row.choiceRef === value.receipt.choiceRef && Number(row.finalPrice.amount) === value.total)) throw new Error('Выбранный перелёт больше не подтверждён.');
        if (!root.AnyTourPrototypeLead?.providerPreviewPayload) throw new Error('Канонический владелец заявки не загружен.');
        return root.AnyTourPrototypeLead.providerPreviewPayload(value.receipt, formData);
      }
      // Payload validation delegates to canonical receipt/expiry guards. Never
      // call session.submit: lead delivery is disabled in this preview.
      return data.leadSession(value).payload(formData);
    }
    return Object.freeze({init, changeOrigin, search, readHotel, readOffers, stayLabels, quoteOffer, selectFlight, selectProviderFlight,
      deferFlight, retryFlights, expandOfferGroup, acceptPrice, previewContact, subscribe, getSelection: currentSelection,
      waitSelection: () => selectionPromise || followupPromise || Promise.resolve(currentSelection()),
      getDictionarySnapshot: () => dictionarySnapshot,
      regions: country => data.regions(country),
      lookupHotels: (query, country, signal) => data.lookupHotels(query, country, signal),
      calendarPrices: (search, from, to, signal, constraints, onUpdate) => data.calendarPrices(search, from, to, signal, constraints, onUpdate),
      stop() { ++searchGeneration; ++selectionGeneration; ++flightGeneration; selected = null; selectionPromise = null; followupPromise = null; data.stop(); publish({...latest, status: 'stopped'}); },
      getSnapshot: () => latest, leadDelivery: false});
  }
  root.AnyTourDesignV2Bridge = Object.freeze({create});
})(typeof window === 'undefined' ? globalThis : window);
