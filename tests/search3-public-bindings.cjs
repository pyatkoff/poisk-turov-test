'use strict';
// Actual config/catalog/bootstrap/app/analytics owners with local fixture transports.
// All fetches are intercepted; these are not supplier or installed-page measurements.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const {JSDOM,VirtualConsole}=require('jsdom');
const root=path.resolve(__dirname,'../v2'),read=file=>fs.readFileSync(path.join(root,file),'utf8');
const app=read('visual-search/app.js'),plain=value=>JSON.parse(JSON.stringify(value));
const nativeApi='/_preview/search3-anex-candidate/api-anex-search3-preview.php',disabledLead='/_preview/search3-next-candidate/preview-lead-disabled.php';
function config(profile='live',bounded=false){const prefix=bounded?'/_preview/search3-site-candidate':'';return {schemaVersion:1,profile,scenario:'family',
 publicBase:prefix+'/poisk-turov/',resourceBase:prefix+'/visual-search/',api:'/api-v2.php',leadApi:disabledLead,
 anexApi:profile==='live'?nativeApi:null,metrikaCounter:0};}
function box(settings=config(),pathname=settings.publicBase,query=''){
 const calls=[],events=[],listeners=new Map(),location=new URL('https://public-binding.invalid'+pathname+(query?'?'+query:''));
 const document={currentScript:{dataset:{search3PublicConfig:JSON.stringify(settings)}},addEventListener(){},createElement(){throw Error('No external scripts permitted');}};
 const context={URL,URLSearchParams,structuredClone,TextEncoder,AbortController,Date,Math,Map,Set,WeakMap,WeakSet,Promise,setTimeout,clearTimeout,
  crypto:require('node:crypto').webcrypto,location,document,performance:{now:()=>100},CustomEvent:class{constructor(type,options){this.type=type;this.detail=options?.detail;}},
  addEventListener(type,fn){if(!listeners.has(type))listeners.set(type,[]);listeners.get(type).push(fn);},
  dispatchEvent(event){events.push(event.type);for(const fn of listeners.get(event.type)||[])fn(event);},dataLayer:[],
  fetch:async(...args)=>{calls.push(args);throw Error('Unmocked transport');}};
 context.window=context;vm.createContext(context);
 const load=file=>vm.runInContext(read(file),context,{filename:file});
 load('visual-search/public-config-v1.js');return {context,calls,events,load};
}
function catalogBox(query){
 const h=box(config(),'/poisk-turov/',query),w=h.context;
 // Synthetic catalog identities intentionally differ; only the actual crosswalk may map them.
 const departures=[{id:1,name:'Москва'},{id:33,russianName:'Калининград'}];
 w.fetch=async(input,options={})=>{
  const url=new URL(input,w.location.href);h.calls.push({path:url.pathname,query:url.search,body:options.body});
  let value;
  if(url.pathname==='/data/departures-v1.php')value={ok:true,items:departures};
  else if(url.pathname==='/_preview/search3-local-candidate/data/search3-local-results-read-v1.php'){
   assert.equal(JSON.parse(options.body).action,'meal_catalog');value={ok:false};
  }else if(url.pathname==='/_preview/search3-local-candidate/data/search3-destination-read-v1.php'){
   assert.equal(url.searchParams.get('action'),'countries');
   value={ok:true,source:'anytour-destination-identities-v1',provider:'tourvisor',items:[{id:'501',name:'Египет',tourvisorIds:['1']}]};
  }else throw Error('Forbidden transport '+url.pathname);
  return {ok:true,json:async()=>value};
 };
 w.V2Runtime={setSearchId(){},api:async action=>{h.calls.push({api:action});assert.equal(action,'meals','no supplier API operation');return [];}};
 for(const file of ['search3-canonical-profiles-v1.js','prototype-search/data.js','visual-search/public-url-v1.js','visual-search/public-bootstrap-v1.js'])h.load(file);
 return h;
}
function restore(query,catalog,data){
 const begin=app.indexOf('function parseURLParty('),end=app.indexOf('let compactSearchFrame=',begin);assert(begin>0&&end>begin);
 const ctx={URLSearchParams,structuredClone,location:{search:'?'+query},countryNames:Object.fromEntries(catalog.countries.map(row=>[row.id,data.text(row)])),
  startDay:'2026-11-21',endDay:'2027-05-20',data,state:{search:{origin:'Москва',country:'4',from:'2026-12-05',to:'2026-12-11',minNights:7,maxNights:7,adults:2,ages:[]},sort:'recommended'},
  dateObj:s=>new Date(s+'T12:00:00Z'),urlPartyIssues:[],linkedAmenityLabels:null,restoreAmenityLabels:()=>new Map(),validIds:ids=>ids.filter(n=>Number.isSafeInteger(n)&&n>0),destinationIds:f=>f.hotelIds||[],setDestinationIds(){},$:()=>({value:''}),draft:null};
 vm.createContext(ctx);vm.runInContext(app.match(/^const defaultFilters=.+$/m)[0]+'\nstate.filters=defaultFilters();\n'+app.slice(begin,end)+'\nrestoreURL();',ctx);
 return plain(ctx.state.search);
}
async function publicURLRegressions(){
 for(const [settings,pathname,expected] of [
  [config(),'/poisk-turov/',true],[config(),'/poisk-turov/index.php',true],[config(),'/poisk-turov/other',false],
  [config(),'/_preview/search3-next-candidate/visual-search/',false],[config('review',true),'/_preview/search3-site-candidate/poisk-turov/',true],
  [config('live',true),'/_preview/search3-site-candidate/poisk-turov/',false],[{...config(),leadApi:'https://external.invalid/lead'},'/poisk-turov/',false],
  [{...config(),leadApi:'/lead-adapter-v2.php'},'/poisk-turov/',false]
 ]){const h=box(settings,pathname);assert.equal(h.context.AnyTourPublicConfigV1.valid,expected);h.load('search3-canonical-profiles-v1.js');
  assert.equal(!!h.context.Search3CanonicalProfilesV1.create(()=>{}),expected&&settings.profile==='live'||pathname.startsWith('/_preview/search3-next-candidate/visual-search/'));
  assert.equal(h.context.V2_CONFIG.search3ContactMode,expected?'messenger-only':null);
  assert.equal(h.context.V2_CONFIG.leadApi,disabledLead,'public entry never enables the legacy phone receiver');
  assert.equal(h.calls.length,0,'config/admission never fetch');
 }
 const query='from=33&country=1&dateFrom=2026-12-20&dateTo=2026-12-24&daysFrom=9&daysTill=11&count_people=3&child_count=2&child_age%5B%5D=0&child_age%5B%5D=8&yclid=a%26b&utm_campaign=%D1%81%D0%B5%D0%BC%D1%8C%D1%8F';
 const h=catalogBox(query),w=h.context,bounds={query,origin:'Москва',startDay:'2026-11-21',endDay:'2027-05-20'};
 const result=await w.AnyTourPublicBootstrapV1.prepare(w.AnyTourPrototypeData,bounds);
 assert(w.Search3CanonicalProfilesV1.current(),'actual canonical owner admitted on public path');
 assert.deepEqual(h.calls.filter(c=>c.path?.endsWith('search3-destination-read-v1.php')).map(c=>new URLSearchParams(c.query).get('departureId')),['33'],'correct city before country catalog; no default-Moscow read');
 assert.deepEqual(restore(result.query,result.catalog,w.AnyTourPrototypeData),{origin:'Калининград',country:'501',from:'2026-12-20',to:'2026-12-24',minNights:9,maxNights:11,adults:3,ages:[0,8]});
 assert.equal(result.autoSearch,true);assert.equal(new URLSearchParams(result.query).get('yclid'),'a&b');
 const after=await w.AnyTourPublicBootstrapV1.prepare(w.AnyTourPrototypeData,{...bounds,query:result.query});
 assert.equal(after.namespace,'next');assert.deepEqual(restore(after.query,after.catalog,w.AnyTourPrototypeData),restore(result.query,result.catalog,w.AnyTourPrototypeData),'reload reuses the canonical namespace and party');
 const country=catalogBox('country=1'),countryResult=await country.context.AnyTourPublicBootstrapV1.prepare(country.context.AnyTourPrototypeData,{...bounds,query:'country=1'});
 assert.equal(countryResult.namespace,'legacy');assert.equal(new URLSearchParams(countryResult.query).get('country'),'501');assert.equal(countryResult.autoSearch,false);
 const reopenedCountry=await country.context.AnyTourPublicBootstrapV1.prepare(country.context.AnyTourPrototypeData,{...bounds,query:countryResult.query});
 assert.equal(reopenedCountry.namespace,'next','country-only output has an explicit canonical namespace on reload');assert.equal(new URLSearchParams(reopenedCountry.query).get('country'),'501');
 for(const query of ['new_filter=1','country=1&stars=5','from=33&country=1&q=sea','origin=Москва&country=501&dateFrom=2026-12-20','origin=Москва&min=1&new_filter=1','from=not-a-city&country=1']){
  const h=catalogBox(query),w=h.context,before=w.location.href;
  await assert.rejects(w.AnyTourPublicBootstrapV1.prepare(w.AnyTourPrototypeData,{...bounds,query}),error=>error.publicSearchURL===true&&!Object.hasOwn(error,'query')&&error.fallback.startsWith('/poisk-turov-old/?'));
  assert.equal(w.location.href,before);assert.equal(h.calls.length,0,'unsupported links rejected before catalog/transport');
 }
 // Public LOCAL content still uses its exact accepted endpoint and untouched DTO checks.
 const content=catalogBox(''),cw=content.context;cw.fetch=async input=>{content.calls.push(String(input));return {ok:true,json:async()=>({ok:true,source:'anytour-local-tv-catalog-v1',catalog:'local-tv',requestedIds:[15],items:[],links:[],missingIds:[15]})};};
 await cw.AnyTourPrototypeData.refreshHotelContent([15]);assert.equal(content.calls.length,1);assert(content.calls[0].startsWith('/_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php?'));
 console.log('PASS public config/catalog/bootstrap: exact public admission, legacy departure before country, real NEXT restore/reload/party, unknown/mixed fail closed; mocked catalog reads only');
}
async function reviewApp(query,bounded=false){
 const settings=config('review',bounded),errors=[],calls=[],virtual=new VirtualConsole();virtual.on('jsdomError',error=>errors.push(error));
 const dom=new JSDOM(read('visual-search/index.html'),{url:'https://public-binding.invalid'+settings.publicBase+query,runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:virtual});
 const w=dom.window;w.document.querySelector('.prototype-tools')?.remove();w.document.querySelector('.private-badge')?.remove();
 const script=w.document.createElement('script');script.dataset.search3PublicConfig=JSON.stringify(settings);Object.defineProperty(w.document,'currentScript',{configurable:true,get:()=>script});
 w.structuredClone=structuredClone;w.TextEncoder=TextEncoder;w.matchMedia=()=>({matches:false,addEventListener(){},removeEventListener(){}});
 w.CSS={escape:value=>String(value).replace(/[^a-zA-Z0-9_-]/g,char=>'\\'+char)};
 w.IntersectionObserver=class{observe(){}unobserve(){}disconnect(){}};w.ResizeObserver=class{observe(){}disconnect(){}};
 w.HTMLElement.prototype.scrollIntoView=function(){};w.HTMLElement.prototype.scrollTo=function(){};w.scrollTo=()=>{};
 w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};w.HTMLDialogElement.prototype.close=function(){this.open=false;};
 w.fetch=async input=>{calls.push(String(input));assert.equal(String(input),settings.resourceBase+'fixtures/live-search-2026-09-23.json','review only reads its local saved fixture');return {ok:true,json:async()=>JSON.parse(read('visual-search/fixtures/live-search-2026-09-23.json'))};};
 w.XMLHttpRequest=function(){throw Error('XHR forbidden');};w.navigator.sendBeacon=()=>{throw Error('Beacon forbidden');};
 const files=['visual-search/public-config-v1.js','visual-search/fixture-data.js','visual-search/local-db-parser.js','visual-search/recorded-data.js','prototype-search/search-lifecycle-v1.js','visual-search/flight-picker-v18.js','visual-search/preview-lead.js','visual-search/filter-panel-v1.js','visual-search/public-url-v1.js','visual-search/public-bootstrap-v1.js'];
 for(const file of files)w.eval(read(file));
 const marker='updateCompactSearch();\n})();';assert(app.includes(marker));
 w.eval(app.replace(marker,'window.__publicAudit={get state(){return state;},get ready(){return catalogReady;},get hydrated(){return urlStateHydrated;},get failure(){return publicUrlFailure;}};\n'+marker));
 for(let i=0;i<100&&!w.__publicAudit?.ready&&!w.__publicAudit?.failure;i++)await new Promise(resolve=>setTimeout(resolve,10));
 await new Promise(resolve=>setTimeout(resolve,20));assert.deepEqual(errors,[],'actual app tolerates removed lab controls');
 return {dom,w,calls,settings,errors};
}
async function reviewRegressions(){
 for(const bounded of [false,true]){
  const h=await reviewApp('?scenario=live&yclid=keep',bounded),w=h.w;
  assert(w.__publicAudit.ready);assert(w.__publicAudit.hydrated);assert.equal(w.AnyTourPrototypeData.scenario,'family');
  assert.deepEqual(plain(w.__publicAudit.state.search.ages),[0,8]);assert.equal(new URLSearchParams(w.location.search).get('yclid'),'keep');
  assert.equal(new URLSearchParams(w.location.search).get('scenario'),'family');assert.equal(h.calls.length,1);assert.equal(w.V2_CONFIG.leadApi,disabledLead);
  assert.equal(w.V2_CONFIG.search3ContactMode,'messenger-only');assert.equal(w.V2_CONFIG.search3MessengerEnabled,false);
  assert.notEqual(w.AnyTourPrototypeData.live,true);assert.equal(w.AnyTourPrototypeData.messengerHandoff,undefined);
  assert(Object.isFrozen(w.AnyTourPrototypeData.messengerContactPreview));
  assert.match(w.AnyTourPrototypeData.messengerContactPreview.ready,/^Демонстрация:.*Заявки не отправляются/);
  assert.match(w.AnyTourPrototypeData.messengerContactPreview.unavailable,/^Демонстрация:.*недоступен/);
  await w.AnyTourPrototypeData.setScenario('journey');assert.equal(w.AnyTourPrototypeData.scenario,'family','query and lab actions cannot select live/another review scenario');
  const photos=[...w.document.querySelectorAll('.hotel-image')].map(img=>img.getAttribute('src'));assert(photos.length);assert(photos.every(url=>url.startsWith(h.settings.resourceBase+'assets/')));
  assert.equal(w.V2Analytics,undefined,'production analytics absent from review graph');w.close();
 }
 const query='?from=33&country=1&dateFrom=2026-12-20&count_people=6&new_filter=%3Cscript%3E';
 const h=await reviewApp(query),w=h.w;assert(w.__publicAudit.failure);assert.equal(w.__publicAudit.hydrated,false);assert.equal(w.__publicAudit.state.hasSearched,false);
 assert.equal(w.location.search,query);assert.equal(w.__publicAudit.state.search.adults,2,'no partial party restore');assert.equal(w.document.querySelector('[data-action="retry-catalog"]'),null,'unsupported URL is not an automatic retry');
 const link=w.document.querySelector('#catalog-error a');assert(link);assert.equal(new URL(link.href).pathname,'/poisk-turov-old/');assert.equal(new URL(link.href).searchParams.get('new_filter'),'<script>');assert.equal(w.document.querySelector('#catalog-error script'),null);w.close();
 console.log('PASS public REVIEW actual app: root and bounded paths, fixed family despite scenario=live, absolute assets, removed lab controls, unchanged/recoverable rejected URL; one local fixture read and zero supplier/lead calls');
}
async function contactReviewRegressions(){
 const h=await reviewApp('?scenario=live'),w=h.w,d=w.document;
 const wait=async check=>{for(let i=0;i<100;i++){assert.deepEqual(h.errors,[]);if(check())return;await new Promise(resolve=>setTimeout(resolve,10));}assert.fail('Public contact fixture did not settle');};
 const click=selector=>{const target=d.querySelector(selector);assert(target,'actual action exists: '+selector);target.dispatchEvent(new w.MouseEvent('click',{bubbles:true}));};
 // The ordinary on-demand owner is executed locally, with no loader/network fallback.
 w.eval(read('visual-search/hotel-details-v1.js'));
 click('#hotel-900001 h3 [data-action="hotel-details"]');
 await wait(()=>d.querySelector('[data-action="select-hotel-offer"][data-key="demo-flight-24"]'));
 click('[data-action="select-hotel-offer"][data-key="demo-flight-24"]');
 await wait(()=>d.querySelector('#messenger-entry[data-search3-review-messenger="true"] [data-platform="telegram"]')?.disabled===false);
 const total=()=>d.querySelector('#modal-footer .footer-total>strong').textContent,recap=()=>d.querySelector('.application-recap').textContent;
 const expected={total:total(),recap:recap()};assert.match(expected.total,/186[\s\u00a0\u202f]*400/);
 const assertContact=()=>{
  assert.equal(d.querySelectorAll('#prototype-lead-form,[data-action="messenger-phone"],[data-messenger-phone-submit]').length,0);
  assert.equal(d.querySelectorAll('#messenger-entry [data-action="messenger-open"]').length,2);
  assert.equal(total(),expected.total);assert.equal(recap(),expected.recap);
  assert.equal(w.V2_CONFIG.search3MessengerEnabled,false);assert.notEqual(w.AnyTourPrototypeData.live,true);
  assert.equal(h.calls.length,1,'only the existing saved JSON fixture has been read');
 };
 assertContact();click('#messenger-entry [data-platform="telegram"]');
 assert.match(d.querySelector('#messenger-status').textContent,/^Демонстрация:.*недоступен.*не отправлялась/);
 assert([...d.querySelectorAll('#messenger-entry [data-action="messenger-open"]')].every(button=>button.disabled));assertContact();
 click('#messenger-entry [data-action="modal-back"]');await wait(()=>d.querySelector('#hotel-room-count'));
 assert.equal(d.querySelector('[data-action="select-hotel-offer"][data-key="demo-flight-24"]').getAttribute('aria-pressed'),'true');
 click('#modal .modal-header [data-action="close-modal"]');await wait(()=>d.querySelector('#hotel-900001 .current-card-selection [data-action="offer"]'));
 click('#hotel-900001 .current-card-selection [data-action="offer"]');await wait(()=>d.querySelector('[data-action="confirm-tour"]'));
 click('[data-action="confirm-tour"]');await wait(()=>d.querySelector('#messenger-entry [data-platform="max"]')?.disabled===false);
 assertContact();assert.match(d.querySelector('#messenger-status').textContent,/^Демонстрация:.*готов/);
 click('#messenger-entry [data-platform="max"]');assert.match(d.querySelector('#messenger-status').textContent,/^Демонстрация:.*недоступен/);assertContact();
 w.close();console.log('PASS public REVIEW contact: actual shared app + fixed fixture, Telegram/MAX demo errors, Back/selected card/reopen retain exact recap and total; phone/POST/handoff/navigation 0');
}
async function analyticsRegressions(){
 const h=box(),w=h.context;h.load('search3-canonical-profiles-v1.js');const owner=w.Search3CanonicalProfilesV1.create(()=>{});
 owner.upsertHotel({catalog:'anytour',id:501,revision:1,name:'Canonical hotel'});
 h.load('analytics-v4.js');h.load('visual-search/public-analytics-v1.js');h.load('prototype-search/search-lifecycle-v1.js');
 const api=w.AnyTourPublicAnalyticsV1,response={key:'test'},data={search(_search,emit){emit({type:'loading'});emit({type:'results',hotels:[{}]});emit({type:'complete'});}};
 let resultsCount=0;const lifecycle=w.AnyTourPrototypeSearchLifecycleV1.create({form:{addEventListener(){}},data,prepare:()=>({search:{},response}),currentKey:()=>response.key,
  onResults:event=>{resultsCount=event.hotels.length;},afterEvent:(event,r)=>api.event(event,r,{resultsCount}),afterStart:r=>api.start(r,{resultsCount})});
 lifecycle.run();assert.deepEqual(w.dataLayer.map(row=>row.v2_event),['search_started','search_complete'],'sync callback plus afterStart never duplicates a run');
 assert(!Object.hasOwn(w.dataLayer[0].v2,'searchId'),'unknown supplier ID omitted');
 api.tour({provider:'tourvisor',tour:{id:'actual-native-tour',hotel:{id:777}},total:123456}, {id:501,name:'Hotel',resort:'Resort'},'Country');
 api.tour({provider:'tourvisor',tour:{id:'actual-native-tour',hotel:{id:777}},total:123456}, {id:501,name:'Hotel'},'Country');
 const tour=w.dataLayer.find(row=>row.v2_event==='tour_selected');assert.equal(tour.v2.hotelId,'777','never the canonical hotel ID');
 assert.equal(w.dataLayer.filter(row=>row.v2_event==='tour_selected').length,1);
 api.hotel({id:501,legacyIds:[777,778]});assert.deepEqual(plain(w.dataLayer.at(-1).v2),{},'ambiguous native hotel ID omitted');
 const receipt={provider:'anex',offerRef:'anex_online:'+'a'.repeat(64),choiceRef:'anex_quote:'+'b'.repeat(64),localHotelId:501,price:234567.89,priceKind:'verified',finalPriceVerified:true,expiresAt:Math.floor(Date.now()/1000)+600,flights:[{name:'Туда'},{name:'Обратно'}]};
 api.provider(receipt);api.provider(receipt);const provider=w.dataLayer.filter(row=>row.v2_event==='tour_selected').at(-1);
 assert(provider.v2.tourId.startsWith('anex_online:'));assert.equal(provider.v2.price,234567.89);assert(!Object.hasOwn(provider.v2,'hotelId'));assert(!w.dataLayer.some(row=>row.v2_event.startsWith('lead_')),'handoff/selection is not a submitted lead');
 assert(owner.details({anytourHotelId:501}),'analytics never resets the actual canonical owner');assert(!h.events.includes('v2:search-started'));assert(!h.events.includes('v2:search-reset'));assert.equal(h.calls.length,0);
 const review=box(config('review'));review.load('analytics-v4.js');review.load('visual-search/public-analytics-v1.js');review.context.AnyTourPublicAnalyticsV1.hotel({legacyIds:[777]});assert.equal(review.context.dataLayer.length,0);
 console.log('PASS public analytics: actual lifecycle + unchanged analytics owner, original/namespaced identities, receipt price unchanged, no canonical reset/duplicate run/false lead conversion; zero transport');
}
(async()=>{await publicURLRegressions();await reviewRegressions();await contactReviewRegressions();await analyticsRegressions();})().catch(error=>{console.error(error);process.exitCode=1;});
