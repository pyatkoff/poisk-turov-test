'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const {execFileSync} = require('node:child_process');

const source = fs.readFileSync('v2/prototype-search/app.js', 'utf8');
const start = source.indexOf("const selectedTourKey='anytour.real.selected-tour.v1'");
const end = source.indexOf('\nlet compareView=', start);
assert.ok(start >= 0 && end > start, 'selected-tour lifecycle block is present');

const storage = new Map();
let now = Date.parse('2026-09-22T12:00:00Z');
let timer = 0;
const context = {
  Date: class extends Date { static now() { return now; } },
  JSON,
  Number,
  String,
  Array,
  Object,
  RegExp,
  structuredClone,
  hotels: [],
  data: { text: value => String(value ?? '') },
  getStored: (key, fallback) => storage.has(key) ? JSON.parse(storage.get(key)) : fallback,
  saveStored: (key, value) => storage.set(key, JSON.stringify(value)),
  clearStored: key => storage.delete(key),
  addDays: (day, count) => new Date(Date.parse(day + 'T12:00:00Z') + count * 86400000).toISOString().slice(0, 10),
  savedFlightTextPlain: offer => offer.savedFlightText || 'TK 3025 10:00 · TK 3024 18:00',
  setTimeout: () => ++timer,
  clearTimeout: () => {},
  updateNav: () => {},
  showModal: () => {},
  renderRealOffer: () => {},
  editSearch: () => {},
  closeModal: () => {},
  openLeadPreview: () => {},
};
vm.createContext(context);
vm.runInContext(source.slice(start, end) + `\nthis.selectedTourTest={
  snapshot:selectedTourOfferSnapshot, hotelSnapshot:selectedTourHotelSnapshot,
  store:storeSelectedTour, restore:restoreSelectedTour, read:readSelectedTour,
  demote:demoteSavedTour, remove:removeSelectedTour, undo:undoSelectedTour,
  same:sameSelectedTourConditions, ttl:selectedTourTTL
};`, context);

const api = context.selectedTourTest;
const hotel = {id: 77, name: 'Exact Hotel', resort: 'Side', country: '4', stars: 5, rating: 4.8, photos: ['hotel.jpg'], legacyIds: [701], raw: {arrival: 'AYT'}};
const offer = {
  key: 'tourvisor:123', hotelId: 77, day: '2026-10-05', nights: 7,
  total: 1500000, room: 'Deluxe Sea View', placement: '2 ADL', adults: 2, ages: [],
  origin: 'Москва', meal: 'Всё включено', operator: 'FUN&SUN', flight: 'charter',
  provider: 'tourvisor', raw: {id: 123, selectionEnabled: true},
  search: {origin: 'Москва', country: '4', from: '2026-10-05', to: '2026-10-11', minNights: 7, maxNights: 7, adults: 2, ages: []},
  tour: {id: 123, price: 1500000, secretSupplierContext: 'must-not-persist'},
  variants: [{forward: [{company: 'TK', number: '3025'}]}], flightChoiceId: '0'
};

context.hotels.push(hotel);
api.store(offer, hotel);
const record = JSON.parse(storage.get('anytour.real.selected-tour.v1'));
assert.equal(record.observedAt, now, '24h starts at the real selection observation');
assert.equal(record.offer.total, 1500000, 'high prices retain their exact meaning');
assert.equal(record.offer.cached, true, 'persisted selection is an observation, never a live quote');
assert.equal(record.offer.tour, null, 'live quote authorization is not persisted');
assert.deepEqual(record.offer.variants, [], 'supplier flight response is not persisted');
assert.equal(record.offer.room, offer.room);
assert.equal(record.offer.meal, offer.meal);
assert.equal(record.offer.operator, offer.operator);
assert.equal(record.offer.flight, offer.flight);

api.demote();
assert.equal(api.read().cached, true, 'a new search demotes the in-memory quote');
assert.equal(api.read().tour, null, 'a changed search cannot reuse the previous quote');
assert.equal(JSON.parse(storage.get('anytour.real.selected-tour.v1')).observedAt, now, 'demotion does not extend the 24h window');

now += api.ttl - 1;
api.restore();
assert.ok(api.read(), 'selection remains visible until the full 24 hours elapse');
now += 1;
assert.equal(api.read(), null, 'selection expires exactly at 24 hours');
assert.equal(storage.has('anytour.real.selected-tour.v1'), false, 'expired observation is removed');

now = Date.parse('2026-09-22T12:00:00Z');
api.store(offer, hotel);
api.remove();
assert.equal(api.read(), null, 'explicit removal clears the selection');
now += 1000;
api.undo();
assert.ok(api.read(), 'undo restores a non-expired selection');
assert.equal(JSON.parse(storage.get('anytour.real.selected-tour.v1')).observedAt, now - 1000, 'undo does not renew observation time');

const target = api.snapshot(offer);
const candidate = {...target, cached: false, raw: {selectionEnabled: true}};
assert.equal(api.same(candidate, target), true, 'all canonical tour conditions match');
for (const [field, value] of [['hotelId', 78], ['day', '2026-10-06'], ['nights', 8], ['room', 'Standard'], ['meal', 'Завтрак'], ['operator', 'ANEX'], ['flight', 'regular']]) {
  assert.equal(api.same({...candidate, [field]: value}, target), false, `${field} cannot be silently substituted`);
}
assert.equal(api.same({...candidate, adults: 3}, target), false, 'party size cannot be silently substituted');
assert.equal(api.same({...candidate, ages: [7]}, target), false, 'child composition cannot be silently substituted');

const priceCopyStart = source.indexOf('const needsRefresh=');
const priceCopyEnd = source.indexOf('const mealLabel=', priceCopyStart);
assert.ok(priceCopyStart >= 0 && priceCopyEnd > priceCopyStart, 'price provenance helpers are present');
const priceCopyContext = {nightsText:n=>n+' ночей'};
vm.createContext(priceCopyContext);
vm.runInContext(source.slice(priceCopyStart, priceCopyEnd) + '\nthis.priceCopyTest={needsRefresh,offerActionLabel,refreshOfferActionLabel,refreshOfferNotice,cachedPriceNote,priceNote,offerMetaNote};', priceCopyContext);
const priceCopy = priceCopyContext.priceCopyTest;
const cachedLocal = {cached:true,provider:'local',nights:7,raw:{selectionEnabled:false,listingPriceState:'search_price_confirmation_required'}};
const cachedEstimate = {cached:true,provider:'local',raw:{selectionEnabled:false,listingPriceState:'final_ready_estimate'}};
const cachedVerified = {cached:true,provider:'local',raw:{selectionEnabled:false,listingPriceState:'final_verified'}};
const directAnex = {cached:false,provider:'anex',raw:{selectionEnabled:false}};
const directAndromeda = {cached:false,provider:'andromeda',raw:{selectionEnabled:false}};
const freshTourvisor = {cached:false,provider:'tourvisor',raw:{selectionEnabled:true}};
assert.equal(priceCopy.priceNote(cachedLocal),'Сохранённая цена · требует проверки','cached LOCAL price keeps saved provenance');
assert.equal(priceCopy.priceNote(cachedEstimate),'Расчётная цена · подтвердим при выборе','ready estimate stays visibly estimated');
assert.equal(priceCopy.priceNote(cachedVerified),'Ранее подтверждённая цена · наличие проверим','verified historical price remains distinct without becoming selectable');
assert.equal(priceCopy.offerActionLabel(cachedEstimate),'Смотреть условия','ready estimate remains refresh-required');
assert.equal(priceCopy.offerActionLabel(cachedVerified),'Смотреть условия','verified cached price still requires availability refresh');
assert.equal(priceCopy.priceNote(directAnex),'Цена из текущего поиска · требует подтверждения','fresh direct ANEX is not mislabeled as saved');
assert.equal(priceCopy.priceNote(directAndromeda),'Цена из текущего поиска · требует подтверждения','fresh direct Andromeda is not mislabeled as saved');
assert.equal(priceCopy.priceNote(freshTourvisor),'Цена предложения · сборы уточняются','fresh selectable Tourvisor keeps current offer copy');
assert.equal(priceCopy.offerMetaNote(directAnex),'Цена из текущего поиска · требует подтверждения','compact direct-provider row uses truthful provenance');
assert.equal(priceCopy.offerMetaNote(freshTourvisor),'Рейсы и багаж — при выборе','fresh Tourvisor compact row keeps selection detail hint');
assert.equal(priceCopy.offerActionLabel(directAndromeda),'Смотреть условия','direct-provider selection authority remains unchanged');
assert.equal(priceCopy.offerActionLabel(freshTourvisor),'Выбрать тур','Tourvisor selection CTA remains unchanged');

const compareLabelStart = source.indexOf('const comparisonVariantLabel=');
const compareLabelEnd = source.indexOf('\nfunction normalizeComparison', compareLabelStart);
assert.ok(compareLabelStart >= 0 && compareLabelEnd > compareLabelStart, 'comparison variant label helper is present');
const compareLabelContext = {};
vm.createContext(compareLabelContext);
vm.runInContext(source.slice(compareLabelStart, compareLabelEnd) + '\nthis.comparisonVariantLabelTest=comparisonVariantLabel;', compareLabelContext);
const compareLabel = compareLabelContext.comparisonVariantLabelTest;
const compareAI = {room:'Standard Sea View',meal:'Всё включено',operator:'ANEX'};
const compareBB = {...compareAI,meal:'Завтраки'};
assert.equal(compareLabel(compareAI),'Standard Sea View · Всё включено · ANEX','compare selector includes exact room, meal and operator');
assert.equal(compareLabel(compareBB),'Standard Sea View · Завтраки · ANEX','meal differentiates otherwise identical compare variants');
assert.notEqual(compareLabel(compareAI),compareLabel(compareBB),'different meal offers never have identical compare labels');
const favoritesStart = source.indexOf('function renderFavorites()');
const favoritesEnd = source.indexOf('\nfunction openFilters()', favoritesStart);
assert.ok(favoritesStart >= 0 && favoritesEnd > favoritesStart, 'favorites renderer is present');
const favoritesSource = source.slice(favoritesStart, favoritesEnd);
assert.match(favoritesSource,/priceNote\(o\)/,'Favorites must preserve the representative offer price provenance/readiness');
assert.ok(!favoritesSource.includes('За ${guestsText()} · сборы уточняются'),'Favorites must not replace offer price state with generic surcharge copy');

assert.equal(priceCopy.refreshOfferActionLabel(cachedLocal),'Найти актуальные туры','cached offer keeps saved-offer refresh CTA');
assert.equal(priceCopy.refreshOfferActionLabel(directAnex),'Проверить этот тур','fresh direct offer asks to verify this tour');
assert.match(priceCopy.refreshOfferNotice(cachedLocal),/^Сохранённое предложение доступно 24 часа/,'cached offer keeps 24h saved notice');
assert.doesNotMatch(priceCopy.refreshOfferNotice(directAndromeda),/Сохранённое предложение/,'fresh direct offer is not described as saved in details');
assert.match(priceCopy.refreshOfferNotice(directAndromeda),/найдено в текущем поиске/,'fresh direct offer describes current-search provenance');

const mealFilterSource = source.slice(source.indexOf('function hotelOffers(h,options={})'), source.indexOf('\nfunction recommendedHotelScore'));
assert.match(mealFilterSource,/selectedMealPlanIds/,'top-level meal filter resolves canonical plan IDs');
assert.match(mealFilterSource,/selectedMealPlanIds\.includes\(o\.mealPlanId\)/,'offer filtering compares canonical plan IDs');
assert.doesNotMatch(mealFilterSource,/f\.meals\.includes\(o\.meal\)/,'raw/display meal strings never own the top-level filter');
const mergeMealsSource = source.slice(source.indexOf('function mergeSearchResults(event)'), source.indexOf('\nfunction commitSearchDraft'));
assert.match(mergeMealsSource,/o\.mealPlanId/,'result facets require canonical meal plan identity');
assert.match(mergeMealsSource,/o\.mealFacet/,'canonical Russian plan label is the facet display');
assert.doesNotMatch(mergeMealsSource,/mealNames\[o\.meal\]/,'raw meal wording cannot register a top-level facet');
const applyCatalogSource = source.slice(source.indexOf('function applyCatalog(c)'), source.indexOf('\nasync function loadCountries'));
assert.match(applyCatalogSource,/data\.catalog\.mealPlans/,'initial meal choices come from reviewed canonical catalogue');
assert.doesNotMatch(applyCatalogSource,/data\.catalog\.meals\.forEach/,'supplier raw meal catalogue cannot seed top-level choices');

const refreshStart=source.indexOf('async function refreshHotel(id)');
const refreshEnd=source.indexOf('\nfunction openLeadPreview',refreshStart);
assert.ok(refreshStart>=0&&refreshEnd>refreshStart,'refresh owner exists');
const refreshSource=source.slice(refreshStart,refreshEnd);
assert.match(refreshSource,/o\.provider==='anex'.*o\.raw\?\.anexKind==='group_minimum'/s,'ANEX group minimum owns a same-provider refresh branch');
assert.match(refreshSource,/await data\.expandAnexGroup\(o\)/,'ANEX group refresh calls the dedicated direct-provider expansion');
assert.match(refreshSource,/terminalizeSearchForVerification\(\);renderResults\(\{keepFilters:true\}\)/,'ANEX verification terminalizes the old provider UI before awaiting the exact provider');
assert.ok(refreshSource.indexOf('expandAnexGroup(o)')<refreshSource.lastIndexOf('genericExactRefresh(o,h)'),'ANEX same-provider expansion runs before the final generic cross-provider refresh fallback');
assert.match(refreshSource,/h\.offers=\[\.\.\.h\.offers\.filter.*\.\.\.expanded\.offers\]/s,'expanded concrete ANEX variants replace the selected group minimum in the existing hotel offer list');
assert.match(refreshSource,/o\.provider==='anex'.*o\.raw\?\.anexKind==='concrete'.*o\.raw\?\.anexSessionCurrent===true/s,'expanded concrete ANEX owns a same-provider current-offer branch');
assert.match(refreshSource,/await data\.verifyAnexConcrete\(o\)/,'expanded concrete ANEX verifies through the retained provider session');
assert.ok(refreshSource.indexOf('verifyAnexConcrete(o)')<refreshSource.lastIndexOf('genericExactRefresh(o,h)'),'concrete ANEX provider follow-up runs before final generic cross-provider fallback');
assert.match(refreshSource,/openAnexConcreteCurrent\(o,current\)/,'current concrete ANEX result uses provider-current UI');

const anexCurrentStart=source.indexOf('function openAnexConcreteCurrent(o,current)');
const anexCurrentEnd=source.indexOf('\nasync function refreshHotel',anexCurrentStart);
assert.ok(anexCurrentStart>=0&&anexCurrentEnd>anexCurrentStart,'ANEX provider-current result owner exists');
const anexCurrentSource=source.slice(anexCurrentStart,anexCurrentEnd);
assert.match(anexCurrentSource,/finalPriceReady/,'ANEX result distinguishes a retained calculated total from search price');
assert.match(anexCurrentSource,/не помечается как финально подтверждённая цена/,'ANEX calculated total remains explicitly non-final');
assert.match(anexCurrentSource,/обязательные доплаты и итоговая цена ещё требуют подтверждения/,'ANEX search-price fallback remains truthful');
assert.doesNotMatch(anexCurrentSource,/openLeadPreview|completeTour|AnyTourPrototypeLead|leadSession/,'ANEX provider-current result cannot enter Tourvisor lead path');
assert.match(anexCurrentSource,/data-action="anex-additional-prices"/,'provider-current ANEX exposes one explicit mandatory-additional action when retained total is absent');
const anexAdditionalStart=source.indexOf('function openAnexAdditionalEstimate(o,result)');
const anexAdditionalEnd=source.indexOf('\nasync function applyAnexAdditionalPrices()',anexAdditionalStart);
assert.ok(anexAdditionalStart>=0&&anexAdditionalEnd>anexAdditionalStart,'ANEX mandatory-additional result owner exists');
const anexAdditionalSource=source.slice(anexAdditionalStart,anexAdditionalEnd);
assert.match(anexAdditionalSource,/Цена из поиска ANEX/,'ANEX additional view keeps the original search-price component');
assert.match(anexAdditionalSource,/Обязательная доплата за состав туристов/,'ANEX additional view shows the server-calculated mandatory addition separately');
assert.match(anexAdditionalSource,/Расчётная сумма с доплатой/,'ANEX additional view shows the server-composed total');
assert.match(anexAdditionalSource,/не финально подтверждённая цена бронирования/,'ANEX additional total remains explicitly non-final');
assert.doesNotMatch(anexAdditionalSource,/openLeadPreview|completeTour|AnyTourPrototypeLead|leadSession/,'ANEX additional estimate cannot enter any lead path');
const anexAdditionalApplyStart=source.indexOf('async function applyAnexAdditionalPrices()');
const anexAdditionalApplyEnd=source.indexOf('\nasync function refreshHotel',anexAdditionalApplyStart);
assert.ok(anexAdditionalApplyStart>=0&&anexAdditionalApplyEnd>anexAdditionalApplyStart,'ANEX explicit AdditionalPrices action exists');
const anexAdditionalApplySource=source.slice(anexAdditionalApplyStart,anexAdditionalApplyEnd);
assert.match(anexAdditionalApplySource,/await data\.verifyAnexAdditional\(draft\.offer\)/,'ANEX UI delegates arithmetic/transport to data owner');
assert.doesNotMatch(anexAdditionalApplySource,/searchPrice|partySurcharge|calculatedTotal/,'ANEX UI does not inspect or recompute server money components');
assert.match(source,/case 'anex-additional-prices':applyAnexAdditionalPrices\(\)/,'ANEX mandatory additions have one explicit click action');
assert.match(refreshSource,/o\.provider==='andromeda'.*o\.raw\?\.quoteRequired===true/s,'Andromeda quote-required offer owns a same-provider verification branch');
assert.match(refreshSource,/await data\.verifyAndromeda\(o\)/,'Andromeda verification calls the dedicated same-provider quote owner');
assert.ok(refreshSource.indexOf('verifyAndromeda(o)')<refreshSource.lastIndexOf('genericExactRefresh(o,h)'),'Andromeda same-provider quote runs before final generic cross-provider fallback');
assert.match(refreshSource,/o\.cached&&\['anex','andromeda'\]\.includes\(o\.provider\)&&o\.raw\?\.rehydration/,'cached ANEX/Andromeda offers enter silent same-provider rehydration first');
assert.match(refreshSource,/await data\.rehydrateCached\(o\)/,'cached offer delegates fresh provider search to the data owner');
assert.match(refreshSource,/offers\.find\(item=>sameRehydratedTour\(item,o\)\)/,'fresh provider results require a strict semantic match before replacing the cached selection');
assert.ok(refreshSource.indexOf('rehydrateCached(o)')<refreshSource.indexOf('genericExactRefresh(o,h)'),'cached same-provider rehydration runs before the visible generic fallback');
assert.doesNotMatch(refreshSource.slice(0,refreshSource.indexOf('genericExactRefresh(o,h)')),/closeModal\(\)|runSearch\(/,'silent provider rehydration keeps the selected-tour UI in place');
const rehydratedVariantsStart=source.indexOf('function openRehydratedVariants(cached,offers)');
const rehydratedVariantsEnd=source.indexOf('\nfunction chooseRehydratedOffer',rehydratedVariantsStart);
assert.ok(rehydratedVariantsStart>=0&&rehydratedVariantsEnd>rehydratedVariantsStart,'same-provider current variants have an in-place UI owner');
const rehydratedVariantsSource=source.slice(rehydratedVariantsStart,rehydratedVariantsEnd);
assert.match(rehydratedVariantsSource,/только свежие предложения того же поставщика/,'changed cached offer shows same-provider variants rather than silently substituting another tour');
assert.match(source,/case 'rehydrated-offer':chooseRehydratedOffer\(b\.dataset\.key\)/,'same-provider variant selection has one explicit continuation action');
assert.match(refreshSource,/quote\.state==='flight_selection_required'.*openAndromedaFlightChoice/s,'ambiguous Andromeda flights stay in provider-specific flight selection');
assert.match(refreshSource,/openAndromedaVerified\(o,quote\)/,'verified Andromeda quote uses the provider-verified result view');

const andromedaVerifiedStart=source.indexOf('function openAndromedaVerified(o,quote)');
const andromedaVerifiedEnd=source.indexOf('\nasync function applyAndromedaFlightChoice()',andromedaVerifiedStart);
assert.ok(andromedaVerifiedStart>=0&&andromedaVerifiedEnd>andromedaVerifiedStart,'Andromeda verified result owner exists');
const andromedaVerifiedSource=source.slice(andromedaVerifiedStart,andromedaVerifiedEnd);
assert.match(andromedaVerifiedSource,/quote\.finalPrice/,'verified Andromeda result displays supplier-confirmed final price');
assert.match(andromedaVerifiedSource,/Оформление заявки из Andromeda.*пока не подключено/s,'Andromeda verified result states the current no-lead boundary');
assert.doesNotMatch(andromedaVerifiedSource,/openLeadPreview|completeTour|AnyTourPrototypeLead|leadSession/,'Andromeda verified result cannot enter the Tourvisor lead path');

const andromedaChoiceStart=source.indexOf('function openAndromedaFlightChoice(o,quote)');
const andromedaChoiceEnd=source.indexOf('\nfunction openAndromedaVerified',andromedaChoiceStart);
assert.ok(andromedaChoiceStart>=0&&andromedaChoiceEnd>andromedaChoiceStart,'Andromeda flight-choice UI owner exists');
const andromedaChoiceSource=source.slice(andromedaChoiceStart,andromedaChoiceEnd);
assert.match(andromedaChoiceSource,/name="andromeda-outbound"/,'Andromeda flight choice asks for outbound flight');
assert.match(andromedaChoiceSource,/name="andromeda-return"/,'Andromeda flight choice asks for return flight');
assert.match(source,/case 'apply-andromeda-flights':applyAndromedaFlightChoice\(\)/,'opaque Andromeda flight refs have one explicit apply action');
const terminalizeStart=source.indexOf('function terminalizeSearchForVerification()');
const terminalizeEnd=source.indexOf('\nfunction stopSearch()',terminalizeStart);
assert.ok(terminalizeStart>=0&&terminalizeEnd>terminalizeStart,'provider-status terminalizer exists');
const terminalizeSource=source.slice(terminalizeStart,terminalizeEnd);
assert.match(terminalizeSource,/==='loading'.*='cancelled'/s,'old loading provider states become terminal');
assert.match(terminalizeSource,/searchResponse\.pending=false/,'verification clears old pending state');
assert.match(terminalizeSource,/searchResponse\.phase='cancelled'/,'verification marks the old search lifecycle cancelled');

const prepareSearch = source.slice(source.indexOf('function prepareSearchRun(options={})'), source.indexOf('\nfunction mergeSearchResults'));
assert.match(prepareSearch, /demoteSavedTour\(\)/, 'new search retains a demoted observation before lifecycle orchestration');
assert.doesNotMatch(prepareSearch, /savedSelection=null/, 'new search no longer deletes the saved tour');
assert.match(source, /function runSearch\(options=\{\}\)\{return searchLifecycle\.run\(options\);\}/, 'legacy internal runSearch entrypoint delegates to the canonical lifecycle owner');
assert.match(source, /exactRefreshTarget:selectedTourOfferSnapshot\(o\)/, 'refresh carries the immutable canonical target');
assert.match(source, /sameSelectedTourConditions\(o,r\.exactRefreshTarget\)/, 'completion requires the same tour conditions');

const urlStateStart=source.indexOf('function restoreURL()');
const urlStateEnd=source.indexOf('\nfunction renderSummary',urlStateStart);
assert.ok(urlStateStart>=0&&urlStateEnd>urlStateStart,'URL state block is present');
const urlStateSource=source.slice(urlStateStart,urlStateEnd);
assert.match(urlStateSource,/p\.get\('searched'\)==='1'/,'searched=1 restores the searched state on reload');
assert.match(urlStateSource,/state\.filters\.flight=.*regular.*charter.*unknown/s,'flight filter round-trips through the URL');
for(const key of ['beach','rating','family','spa'])assert.match(urlStateSource,new RegExp("p\\.get\\(k\\)==='1'"),key+' boolean filters use the canonical URL flag parser');
assert.match(urlStateSource,/state\.onlyFavorites=p\.get\('favorites'\)==='1'/,'favorites-only mode round-trips through the URL');
assert.match(urlStateSource,/history\[push\?'pushState':'replaceState'\]/,'URL owner can distinguish fresh search history from local replacements');
const commitURLSource=source.slice(source.indexOf('function commitSearchDraft()'),source.indexOf('\nconst searchLifecycle=',source.indexOf('function commitSearchDraft()')));
assert.match(commitURLSource,/updateURL\(\{push:true\}\)/,'an explicit committed search creates a distinct browser history entry');
const popStateStart=source.indexOf("addEventListener('popstate'");
const popStateEnd=source.indexOf('\nfunction showModal',popStateStart);
assert.ok(popStateStart>=0&&popStateEnd>popStateStart,'browser popstate owner is present');
const popStateSource=source.slice(popStateStart,popStateEnd);
assert.match(popStateSource,/location\.search!==currentURLQuery\(\).*location\.reload\(\)/s,'Back/Forward across different search URLs reloads the browser-restored query');
assert.ok(popStateSource.indexOf('e.state?.[uiHistoryKey]')<popStateSource.indexOf('location.reload()'),'modal/drawer history is consumed before search-URL reload');
const bootSource=source.slice(source.indexOf('async function bootRealData()'),source.indexOf("\ndocument.addEventListener('click'",source.indexOf('async function bootRealData()')));
assert.match(bootSource,/const resumeURL=restoreURL\(\)/,'boot captures whether URL represents completed search state');
assert.match(bootSource,/if\(resumeURL\)runSearch\(\{resumeOnly:true\}\)/,'searched URL boots through supplier-free cached resume');
const prepareResumeSource=source.slice(source.indexOf('function prepareSearchRun(options={})'),source.indexOf('\nfunction mergeSearchResults'));
assert.match(prepareResumeSource,/const resumeOnly=options\.resumeOnly===true/,'search preparation recognizes cached resume explicitly');
assert.match(prepareResumeSource,/if\(!resumeOnly\)\{[^}]*demoteSavedTour\(\)/s,'cached resume never demotes the saved selected-tour observation');
assert.match(source,/Сохранённых предложений пока нет/,'empty cached resume explains that no saved rows were available');
assert.match(source,/Условия восстановлены из ссылки без нового запроса к туроператорам/,'cached empty state is honest about supplier-free restoration');

const editSearchSource=source.slice(source.indexOf('const providerSearchPending='),source.indexOf('\nconst hotelPlaces',source.indexOf('const providerSearchPending=')));
assert.match(editSearchSource,/Object\.values\(searchResponse\.providers\|\|\{\}\)\.includes\('loading'\)/,'provider background state is explicitly detectable');
assert.match(editSearchSource,/searchResponse\.pending\|\|providerSearchPending\(\).*stopSearch\(\)/s,'editing aborts both main and background provider work');
const stopSearchSource=source.slice(source.indexOf('function clearSearchTimers()'),source.indexOf('\nfunction renderSearchStatus'));
assert.match(stopSearchSource,/data\.stop\(\)/,'stop keeps the canonical data abort path');
assert.match(stopSearchSource,/searchResponse\.providers\[provider\]==='loading'.*='cancelled'/s,'stop marks active provider continuations cancelled');
assert.match(stopSearchSource,/searchResponse\.canContinue=false/,'stopped background work cannot expose a dead Continue action');
const searchStatusSource=source.slice(source.indexOf('function renderSearchStatus'),source.indexOf('\nfunction prepareSearchRun'));
assert.match(searchStatusSource,/more\.hidden=.*providerPending/s,'Tourvisor Continue stays hidden while direct providers are still loading');
assert.match(searchStatusSource,/r\.pending\|\|providerPending\?'<button class="secondary" data-action="stop-search">Остановить поиск<\/button>'/,'background provider loading exposes Stop');

execFileSync(process.execPath,['tests/search3-prototype-search-lifecycle-v1.cjs'],{stdio:'inherit'});
console.log('search3 prototype selected-tour retention: ok');
