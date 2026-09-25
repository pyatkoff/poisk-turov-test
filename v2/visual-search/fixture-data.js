'use strict';
// Offline UI fixtures. No supplier, pricing or lead API is called from this Site.
(() => {
  const clone = value => structuredClone(value);
  const text = value => typeof value === 'string' ? value : value?.name || value?.russianName || '';
  const amount = value => Number.isFinite(Number(value)) && Number(value) > 0 ? Number(value) : null;
  const rating = value => {const n=Number(String(value??'').replace(',','.'));return Number.isFinite(n)&&n>0&&n<=5?n:null;};
  const scenarios = new Set(['snapshot', 'flights', 'flight-error', 'empty', 'mixed', 'family', 'incomplete', 'price-change', 'unavailable', 'expired']);
  let scenario = new URLSearchParams(location.search).get('scenario') || 'snapshot';
  if (!scenarios.has(scenario)) scenario = 'snapshot';
  let snapshot, rows = [], generation = 0;
  const scenarioSearch = () => ({...clone(search), ages: scenario === 'family' ? [0, 8] : []});
  const catalog = {countries: [{id: '4', name: 'Турция'}], departures: [{id: '1', name: 'Москва'}], meals: [], regions: {'4': []}};
  const search = {origin: 'Москва', country: '4', from: '2026-10-01', to: '2026-10-07', minNights: 7, maxNights: 7, adults: 2, ages: []};
  const iso = day => '2026-10-' + String(day).padStart(2, '0');
  const addDays = (day, count) => new Date(Date.parse(day + 'T12:00:00Z') + count * 86400000).toISOString().slice(0, 10);
  function normalize(raw) {
    return raw.hotels.map(h => ({
      id: h.id, name: h.name, country: '4', resort: h.location.replace(/, Турция$/, ''), region: '', subRegion: '',
      stars: h.stars, rating: rating(h.rating),
      photos: h.photos, amenities: [], legacyIds: [], beach: null, family: false, spa: false,
      raw: {description: h.facts, arrival: h.location.replace(/, Турция$/, '')},
      offers: h.offers.map((o, i) => ({
        key: o.key, hotelId: h.id, variant: i, provider: decodeURIComponent(o.key).split(':')[0],
        cached: true, raw: {selectionEnabled: false, listingPriceState: 'snapshot'}, origin: 'Москва', search: clone(search),
        day: iso(Number(o.dates.match(/^\d+/)?.[0])), returnDay: iso(Number(o.dates.split('→')[1]?.match(/\d+/)?.[0])),
        nights: 7, adults: 2, ages: [], meal: o.meal, room: o.room, operator: o.operator, flight: o.flight,
        total: Number(o.price.replace(/\D/g, '')), flightChoiceId: null
      }))
    }));
  }
  function demoRows(s) {
    if (scenario === 'empty') return [];
    const offer = {key: 'demo-flight-24', hotelId: 900001, variant: 0, provider: 'fixture', cached: false,
      raw: {selectionEnabled: true}, origin: s.origin, search: clone(s), day: s.from, returnDay: addDays(s.from, s.minNights),
      nights: s.minNights, adults: s.adults, ages: [...s.ages], meal: 'Всё включено', room: 'Standard · демонстрационный номер',
      operator: 'Демо-оператор', flight: 'regular', total: 186400, flightChoiceId: null};
    const hotels = [{id: 900001, name: 'Azure Bay Resort · демо', country: s.country, resort: 'Белек', region: '', subRegion: '',
      stars: 5, rating: null, beach: null, family: false, spa: false, photos: ['./assets/photo-1.jpg', './assets/photo-2.jpg'],
      amenities: [], legacyIds: [], raw: {description: 'Вымышленный отель и расписание для проверки выбора рейсов.', arrival: 'Анталья'}, offers: [offer]}];
    if (scenario === 'mixed') {
      // Explicitly synthetic service descriptions exercise the hotel overview.
      hotels[0].raw.hotelInformation = {services: {free: 'Wi-Fi в общественных зонах · учебный пример.', child: 'Детский бассейн · учебный пример.'}, infrastructure: {territory: 'Бассейн и зона отдыха · учебный пример.'}};
      for (let i=1;i<5;i++) {
        const h=clone(hotels[0]);h.id+=i;h.name=['Palm Garden · демо','City Rooms · демо','Mountain Spa · демо','Coast Club · демо'][i-1];h.stars=i+1;h.resort=i%2?'Кемер':'Белек';
        h.offers=Array.from({length:i+1},(_,j)=>({...clone(offer),hotelId:h.id,key:`demo-mixed-${i}-${j}`,variant:j,room:j%2?'Family · демонстрационный номер':'Standard · демонстрационный номер',meal:j%2?'Завтраки · демо':'Всё включено · демо',flight:j%2?'regular':'charter',operator:'Демо-оператор '+(j%2+1),total:95000+i*24000+j*6500}));hotels.push(h);
      }
    }
    if (scenario === 'incomplete') {hotels[0].photos=[];hotels[0].stars=0;hotels[0].raw.description='';}
    return hotels;
  }
  function selectedRows(s) { return scenario === 'snapshot' ? rows : demoRows(s); }
  function snapshotScopeMatches(s, hotelIds = []) {
    return !hotelIds.length
      && (s.origin === 'Москва' || String(s.origin) === '1')
      && String(s.country) === search.country
      && Number(s.adults) === search.adults
      && Array.isArray(s.ages) && s.ages.length === 0
      && Number(s.minNights) <= 7 && Number(s.maxNights) >= 7
      && String(s.from) <= search.to && String(s.to) >= search.from;
  }
  async function snapshotFallback(s, hotelIds = []) {
    await ready;
    if (!snapshotScopeMatches(s, hotelIds)) return null;
    const hotels = rows.map(h => ({
      ...h,
      offers: h.offers
        .filter(o => o.day >= s.from && o.day <= s.to && o.nights >= s.minNights && o.nights <= s.maxNights)
        .map(o => ({...o, origin: s.origin, search: clone(s), adults: s.adults, ages: [...s.ages]}))
    })).filter(h => h.offers.length);
    if (!hotels.length) return null;
    return {
      hotels: clone(hotels),
      capturedAt: snapshot.capturedAt,
      offers: hotels.reduce((total, h) => total + h.offers.length, 0)
    };
  }
  function updateCatalog() {
    const source = selectedRows(search);
    catalog.meals = [...new Set(source.flatMap(h => h.offers.map(o => o.meal)))].map(name => ({name}));
    catalog.regions['4'] = [...new Set(source.map(h => h.resort))].map((name, i) => ({id: String(i + 1), name, country: '4'}));
  }
  function describe() {
    const labels={mixed:'5 вымышленных отелей: разные категории, номера, питание, чартеры и регулярные рейсы.',family:'Демо: двое взрослых, младенец 0 лет и ребёнок 8 лет. Проверка состава туристов до заявки.',incomplete:'Демо: нет фото, категории и сведений о багаже. Неизвестное остаётся неизвестным.', 'price-change':'Демо: цена при выборе меняется с 186 400 до 195 400 ₽. В заявку должна попасть новая цена.',unavailable:'Демо: выбранный тур исчез при проверке. Другой вариант автоматически не подставляется.',expired:'Демо: срок предложения истёк. Этот сценарий включается вручную; остальные не зависят от времени.'};
    if(labels[scenario])return labels[scenario]+' Нет запросов поставщикам и отправки заявки.';
    return scenario === 'snapshot' ? `Снимок 23.09.2026: 1 047 отелей, 1 453 видимых предложения. Москва → Турция, 1–7 октября, 7 ночей, 2 взрослых.`
      : scenario === 'flights' ? '24 вымышленные пары рейсов: прямые, с пересадкой, без цены и с неизвестным багажом.'
      : scenario === 'flight-error' ? 'Демонстрация ошибки рейсов. Условия и цена — примеры.' : 'Демонстрация пустой выдачи.';
  }
  const ready = fetch('./fixtures/live-search-2026-09-23.json').then(response => {
    if (!response.ok) throw new Error('Не удалось открыть сохранённый пример. Обновите страницу.');
    return response.json();
  }).then(raw => {snapshot = raw; rows = normalize(raw); updateCatalog();});
  const time = minutes => `${String(Math.floor(minutes / 60) % 24).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
  function segments(tour, i, back = false) {
    const day = back ? tour.returnDay : tour.day, start = (back ? 420 : 300) + (i % 12) * 65;
    const from = back ? 'Анталья · AYT' : i % 2 ? 'Москва · SVO' : 'Москва · VKO';
    const to = back ? i % 2 ? 'Москва · SVO' : 'Москва · VKO' : 'Анталья · AYT';
    const company = i % 3 ? 'Авиакомпания A · демо' : 'Авиакомпания B · демо';
    const leg = (a, b, dep, arr, suffix) => ({company, number: `DEMO ${i + 101}${suffix}`, plane: 'Воздушное судно · пример',
      departure: {port: a, date: day, time: time(dep)}, arrival: {port: b, date: addDays(day, Math.floor(arr / 1440)), time: time(arr)},
      baggage: i === 6 ? null : i % 5 === 0 ? 0 : 20, carryOn: i === 6 ? '' : '5 кг'});
    return i >= 20 ? [leg(from, 'Стамбул · IST', start, start + 210, 'A'), leg('Стамбул · IST', to, start + 325, start + 415, 'B')]
      : [leg(from, to, start, start + 275, '')];
  }
  window.AnyTourPrototypeData = Object.freeze({
    preview: true, catalog, text, amount, meal: text, date: value => String(value || ''),
    get initialSearch() {return scenarioSearch();}, clockStart: '2026-09-24', describe, get scenario() {return scenario;},
    async init() {await ready; return {origin: 'Москва', ...catalog};},
    async countries() {await ready; return {origin: 'Москва', ...catalog};},
    async regions(country) {await ready; return catalog.regions[country] || [];},
    params: s => clone(s),
    snapshotFallback,
    async search(s, emit, hotelIds, filters = {}) {
      const run = ++generation; emit({type: 'loading'}); await ready;
      if (run !== generation) return;
      emit({type: 'results', hotels: clone(selectedRows(s))});
      emit({type: 'complete', canContinue: false});
    },
    stop() {generation++;}, continueSearch() {}, observationScopeSupported() {return false;},
    async calendarPrices(s, from, to, signal) {await ready; return {hotels: signal?.aborted ? [] : clone(selectedRows(s)), observations: [], partial: false};},
    async savedHotels(ids, s) {await ready; return clone(selectedRows(s).filter(h => ids.includes(h.id)));},
    async restoreHotel(id) {await ready; const h = selectedRows(search).find(h => h.id === id); if (!h) throw new Error('Отеля нет в этом примере'); return clone(h);},
    async lookupHotels(q) {await ready; return clone(selectedRows(search).filter(h => (h.name + ' ' + h.resort).toLowerCase().includes(q.toLowerCase())).slice(0, 30));},
    async quote(o) {
      if (scenario === 'unavailable') {const error=new Error('Демо: выбранный тур больше недоступен. Вернитесь к результатам.');error.code='offer_unavailable';throw error;}
      if (scenario === 'expired') {const error=new Error('Демо: срок предложения истёк. Вернитесь к результатам.');error.code='offer_expired';throw error;}
      if (scenario === 'snapshot') throw new Error('Это сохранённая выдача от 23 сентября. Актуальность проверяется в рабочем поиске.');
      return {id: o.key, price: scenario === 'price-change' ? o.total + 9000 : o.total, roomType: o.room, meal: o.meal, day: o.day, returnDay: o.returnDay};
    },
    async flights(tour) {
      if (scenario === 'flight-error') throw new Error('Демонстрационная ошибка загрузки рейсов');
      const variants = Array.from({length: scenario === 'family' ? 3 : 24}, (_, i) => ({isDefault: i === 0, price: i === 23 ? null : tour.price + i * 1300,
        fuelCharge: i === 6 ? null : 0, forward: segments(tour, i), backward: segments(tour, i, true)}));
      if (scenario === 'incomplete') for(const v of variants)for(const leg of [...v.forward,...v.backward]){leg.baggage=null;leg.carryOn=null;}
      return variants;
    },
    variantPrice: (tour, v) => amount(v?.price), fuel: (tour, v) => v?.fuelCharge ?? null,
    async setScenario(value) {if (!scenarios.has(value)) return; generation++; scenario = value; await ready; updateCatalog();},
    get metadata() {return snapshot ? {capturedAt: snapshot.capturedAt, hotels: rows.length, offers: snapshot.capturedOfferCount, sourceOffers: snapshot.visibleTotalOffers} : null;}
  });
})();
