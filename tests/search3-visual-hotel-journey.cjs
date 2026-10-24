'use strict';
// Execute the connected owners with deferred responses, without DOM emulation
// or supplier access. Browser geometry is covered by the existing compiled gate.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const app=fs.readFileSync(path.join(__dirname,'../v2/visual-search/app.js'),'utf8');
function slice(from,to){const a=app.indexOf(from),b=app.indexOf(to,a+from.length);assert(a>=0&&b>a,from);return app.slice(a,b);}
const owners=slice('function hotelOfferCheckActive(','async function openOffer(')
 +slice('const unknownInfantCharge=','const priceNote=')
 +slice('function restoreProviderView(','const flightPairFor=')
 +slice('function leadReadyOffer(','function quotePriceChangeHTML(')
 +slice('function anexApplicationReceipt(','function openAnexApplicationPreview(')
 +slice('function openAnexConcreteCurrent(','function refreshHotel(');
function deferred(){let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};}
function setup(provider='tourvisor',kind='concrete'){
 const search={origin:'Москва',country:'4',adults:2,ages:[0,8]},raw={anexKind:kind,anexSessionCurrent:true,offerRef:'anex_online:'+'a'.repeat(64),searchRef:'b'.repeat(32),anexGeneration:1,anexLocalHotelId:1};
 const listing={key:'one',hotelId:1,provider,raw,search,total:198600,room:'Family',meal:'AI',day:'2027-04-18',nights:7,adults:2,ages:[0,8]};
 const hotel={id:1,offers:[listing]},calls={quote:0,flights:0,expand:0,verify:0,additional:0,contacts:0,offerScreens:0,lists:0,rooms:0},views=new Map();
 const nodes={'#modal':{open:true},'.hotel-section-nav':{dataset:{hotelId:'1'}},'#modal-footer':{hidden:false,innerHTML:''},'#hotel-detail-min':{textContent:''}};
 const c={console,Number,Set,Map,Promise,selectedOffer:{...listing},hotelOfferDraft:{hotelId:1,key:'one'},hotelDetailsView:{id:1,checkKey:'one'},modalType:'hotel-details',selectionGeneration:0,state:{search},anexCurrentDraft:null,anexApplicationDraft:null,anexFlightViews:new WeakMap(),hotels:[hotel],
  $:selector=>nodes[selector],data:{live:true,amount:v=>Number.isFinite(Number(v))&&Number(v)>0?Number(v):null,text:v=>String(v||''),meal:v=>v,quote:async()=>{calls.quote++;return {price:198600};},flights:async()=>{calls.flights++;throw Error('flight requests are optional');},verifyAnexConcrete:async()=>{calls.verify++;return {state:'current'};},verifyAnexAdditional:async()=>{calls.additional++;return {state:'additional_prices',finalPriceVerified:false,arithmeticApplied:true,searchPrice:{amount:198600},partySurcharge:{amount:700},calculatedTotal:{amount:199300,currency:'RUB'}};},expandAnexGroup:async()=>{calls.expand++;return {offers:[{...listing,key:'room',raw:{...raw,anexKind:'concrete'}}]};}},
  offerFromKey:key=>hotel.offers.find(o=>o.key===key),selectedTourHotel:()=>hotel,searchKey:s=>JSON.stringify(s),sameSelectedTourConditions:(a,b)=>a.room===b.room&&a.meal===b.meal&&a.day===b.day&&a.adults===b.adults&&JSON.stringify(a.ages)===JSON.stringify(b.ages),needsRefresh:o=>o.provider==='anex',flightPairFor:o=>o.variants?.[Number(o.flightChoiceId)]||null,
  retainedProviderView:o=>views.get(o?.key),rememberProviderView:(o,type,result,error='',pending=false)=>views.set(o.key,{offer:o,type,result,error,pending}),
  restoreProviderView:o=>{const v=views.get(o.key);if(!v)return false;c.selectedOffer=v.offer;if(v.type==='anex-current')c.openAnexConcreteCurrent(v.offer,v.result);if(v.type==='anex-additional')c.openAnexAdditionalEstimate(v.offer,v.result);return true;},
  renderRealOffer:()=>{calls.offerScreens++;c.modalType='offer';},syncHotelOfferSelection:()=>{},openLeadPreview:o=>{calls.contacts++;calls.contactOffer=o;c.modalType='selected-tour';},openOffer:()=>{calls.offerScreens++;},loadRealFlights:async()=>{calls.flights++;},openFlightPicker:()=>{calls.flights++;},
  terminalizeSearchForVerification:()=>{},renderResults:()=>{},refreshOpenHotelRooms:()=>calls.rooms++,positionHotelRooms:()=>{},openAllOffers:()=>calls.lists++,toast:()=>{},refreshHotel:()=>{},openAnexPackageQuote:()=>{},loadAnexPackageQuote:async()=>{},renderAnexFlightInventory:()=>{},rememberUIRoute:()=>{},
  anexApplicationReceipt:(o,result)=>({price:Number(result.calculatedTotal.amount),finalPriceVerified:false,priceKind:'estimate'}),countryNames:{'4':'Турция'},mealLabel:o=>o.meal,esc:v=>v,money:v=>String(v),roomLabel:o=>o.room,displayMealLabel:o=>o.meal,dateText:v=>v,nightsText:v=>v,guestsText:()=> '2 взрослых и 2 ребёнка',showModal:()=>{calls.offerScreens++;}
 };
 vm.createContext(c);vm.runInContext(owners,c);return {c,calls,listing,hotel,views,nodes};
}
function verifyResultCardEntry(){
 const dispatcher=slice("case 'offer':{const key=b.dataset.key", "case 'start-lead':");
 for(const provider of ['tourvisor','anex','andromeda','fixture'])for(const cardEntry of [false,true]){
  const clicked={key:'clicked-second',hotelId:7,provider,raw:{},total:90000},first={...clicked,key:'other-first',raw:{}},previous={key:'old',hotelId:9,raw:{}};
  const calls=[],c={b:{dataset:{key:clicked.key,...(cardEntry?{cardEntry:'true'}:{})}},hotelOfferDraft:{hotelId:9,key:'old'},selectionGeneration:3,selectedOffer:previous,state:{search:{}},
   offerFromKey:key=>[first,clicked].find(o=>o.key===key),cancelHotelOfferCheck:()=>calls.push('cancel'),
   openHotelDetails:(id,route)=>calls.push({type:'rooms',id,route,draft:c.hotelOfferDraft}),openOffer:(key,retained)=>calls.push({type:'offer',key,retained}),sameSelectedTourConditions:()=>false,searchKey:()=>''};
  vm.createContext(c);vm.runInContext("function dispatch(){switch('offer'){"+dispatcher+"}}",c);c.dispatch();
  if(cardEntry&&['tourvisor','anex'].includes(provider)){
   const entry=calls.find(x=>x?.type==='rooms');assert(entry,provider+' result card enters existing hotel rooms');assert.equal(entry.id,7);assert.equal(entry.route.entryOfferKey,clicked.key);
   if(provider==='tourvisor'){assert.equal(entry.draft.key,clicked.key,'clicked exact row, never first offer');assert.equal(entry.draft.hotelId,7);assert.equal(c.selectionGeneration,4);assert.equal(calls.filter(x=>x==='cancel').length,1);}
   else assert.equal(c.hotelOfferDraft.key,'old','ANEX group refresh remains its existing owner');
   assert.equal(calls.some(x=>x?.type==='offer'),false);
  }else assert.equal(calls.at(-1).type,'offer','explicit details and unsupported sources retain their old route');
  assert.strictEqual(c.selectedOffer,previous,'entry does not mint or overwrite a quote, flight choice or browser price');
 }
 const room={open:false},other={open:false},more={setAttribute:(name,value)=>{more[name]=value;}},key='clicked-second';
 const row={dataset:{offerKey:key},closest:selector=>selector==='.room-overview'?room:selector==='.hotel-room-more'?more:null};
 const c={window:{V2_CONFIG:{},AnyTourHotelDetails:{create(){}}},data:{},hotelDetailsRequest:0,hotelDetailsView:{id:7,restored:{entryOfferKey:key}},hotels:[{id:7}],modalType:'hotel-details',
  $:selector=>selector==='#modal'?{open:true}:null,$$:selector=>selector==='.room-offer-choice'?[{dataset:{offerKey:'first'},closest:()=>other},row]:[],
  hotelDetailsOwner:()=>({openHotelDetails:()=>[{key:'first',provider:'tourvisor'},{key,provider:'tourvisor'}]}),renderHotelRooms(){},rememberUIRoute(){},positionHotelRooms(){}};
 vm.createContext(c);vm.runInContext(slice('function renderHotelDetails(){','function positionHotelRooms(){'),c);c.renderHotelDetails();
 assert.equal(room.open,true,'the clicked room is expanded after the lazy hotel owner renders');assert.equal(other.open,false,'other rooms are not expanded');assert.equal(more.open,'','clicked tail offer is not hidden in More');
 console.log('PASS exact TV result-card rooms entry; ANEX/direct/unsupported routes preserved; supplier calls0');
}
async function verifyChoiceComposition(){
 const {c,listing,nodes}=setup();
 const panel={hidden:false},label={hidden:true},classes=new Set(),choice={textContent:'',classList:{toggle(){}},setAttribute(){}};
 const row={dataset:{offerKey:listing.key},classList:{toggle:(name,on)=>on?classes.add(name):classes.delete(name)},setAttribute(){},removeAttribute(){},querySelector:selector=>({'.hotel-room-price':panel,'.room-choice-selected':label,'[data-action="select-hotel-offer"]':choice})[selector]};
 nodes['#hotel-detail-price-status']={textContent:''};nodes['#hotel-detail-offers']={dataset:{},disabled:false,textContent:''};
 Object.assign(c,{$$:()=>[row],hotelOffers:()=>[listing],cardPriceNote:()=> 'Цена из выдачи · требует проверки',syncHotelFlightSelection(){},fuelAmount:()=>null,fuelIncludedInPrice:()=>false,offerQuoteErrorText:()=> 'Предложение недоступно'});
 vm.runInContext(slice('function selectedPriceStatus(','function fuelDisclosureHTML(')+slice('function syncHotelOfferSelection(','function observeHotelRoomChoices('),c);
 const sync=()=>c.syncHotelOfferSelection(1,[listing]),button=nodes['#hotel-detail-offers'],status=nodes['#hotel-detail-price-status'];
 sync();assert.equal(panel.hidden,true);assert.equal(label.hidden,false);assert.equal(button.textContent,'Проверить тур');assert.equal(status.textContent,'Цена из выдачи · требует проверки');assert.equal(status.textContent.includes(listing.room),false,'row facts are not duplicated in the price status');
 c.selectedOffer={...listing,tour:{price:198600},quoteListingTotal:198600};sync();assert.equal(button.textContent,'Продолжить');assert.equal(status.textContent,'Цена предложения · перелёт уточняется');
 c.selectedOffer={...c.selectedOffer,hotelFlightPreview:true,pricePending:true};sync();assert.equal(button.disabled,true);assert.equal(nodes['#hotel-detail-min'].textContent,'Цена уточняется');assert.match(status.textContent,/Цена выбранной пары не передана/);
 c.selectedOffer={...listing,quoteError:'expired',quoteErrorTerminal:true};sync();assert.equal(button.disabled,true);assert.equal(button.textContent,'Тур недоступен');
 c.hotelOfferDraft=null;sync();assert.equal(panel.hidden,false,'alternatives recover their own comparable price/action');assert.equal(label.hidden,true);
 for(const outcome of ['healthy','empty','error']){
  const {c,calls,listing}=setup();c.selectedOffer={...listing,tour:{price:198600},quoteListingTotal:198600,flightsLoaded:true,variants:outcome==='empty'?[]:[{}],flightsError:outcome==='error'?'temporary':''};
  await c.openHotelFlights('one');assert.equal(calls.quote,0);assert.equal(calls.flights,Number(outcome!=='healthy'),outcome+' uses the existing explicit retry owner');assert.equal(c.selectedOffer.flightChoiceId,undefined,'inventory reopening never chooses a default');
 }
 console.log('PASS approved Site220 receiver: single selected total, current check/continue, unknown/terminal guards, alternative prices and healthy-only cached optional flights');
}
async function verifySite215Journey(){
 const {JSDOM,VirtualConsole}=require('jsdom');
const root=path.resolve(__dirname,'../v2/visual-search');
const pause=()=>new Promise(resolve=>setTimeout(resolve,20));
async function run(width,lazy=false){
 const faults=[],reads=[],counts={quote:0,flights:0},vc=new VirtualConsole();vc.on('jsdomError',e=>faults.push(e.message));
 const dom=new JSDOM(fs.readFileSync(root+'/index.html','utf8'),{url:'https://fixture.test/?scenario=flights&searched=1',runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:vc}),w=dom.window,d=w.document;
 try{
  w.CSS={escape:v=>String(v).replace(/[^a-zA-Z0-9_-]/g,x=>'\\'+x)};w.structuredClone=structuredClone;w.innerWidth=width;w.innerHeight=900;w.scrollTo=()=>{};w.HTMLElement.prototype.scrollIntoView=function(){};
  w.IntersectionObserver=class{observe(){}disconnect(){}unobserve(){}};w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};w.HTMLDialogElement.prototype.close=function(){this.open=false;};
  w.matchMedia=q=>({matches:/max-width/.test(q)?width<=Number(q.match(/\d+/)?.[0]||0):/min-width/.test(q)?width>=Number(q.match(/\d+/)?.[0]||0):false,addEventListener(){},removeEventListener(){}});
  w.fetch=async input=>{const u=new URL(input,w.location.href);assert.equal(u.origin,'https://fixture.test');assert.equal(u.pathname,'/fixtures/live-search-2026-09-23.json');reads.push(u.pathname);return {ok:true,json:async()=>JSON.parse(fs.readFileSync(root+u.pathname,'utf8'))};};
  for(const f of ['fixture-data.js','local-db-parser.js','recorded-data.js','search-lifecycle-v1.js','flight-picker-v18.js','preview-lead.js','filter-panel-v1.js','offer-list-v1.js','hotel-details-v1.js','flight-picker-ui-v1.js'])w.eval(fs.readFileSync(root+'/'+f,'utf8'));
  const data=w.AnyTourPrototypeData,emptyRetry=width===430&&!lazy;w.AnyTourPrototypeData={...data,quote:async(...args)=>{counts.quote++;return data.quote(...args);},flights:async(...args)=>{counts.flights++;if(emptyRetry&&counts.flights===1)return [];return data.flights(...args);}};
  w.eval(fs.readFileSync(root+'/app.js','utf8'));await pause();await pause();
  const get=s=>{const n=d.querySelector(s);assert(n,s);return n;},click=s=>get(s).click(),settle=async predicate=>{for(let i=0;i<100&&!predicate();i++)await pause();assert(predicate(),'asynchronous selection settled');};
  click('.hotel-card [data-action="hotel-details"]');await pause();
  click('[data-action="select-hotel-offer"]');
  const key=get('.room-offer-choice.selected').dataset.offerKey;
  if(lazy){click('#hotel-detail-offers');await settle(()=>d.querySelector('#prototype-lead-form'));assert.equal(counts.flights,0,'ordinary manager path never fetches flights');}
  else{
   click('.room-offer-choice.selected [data-action="hotel-flights"]');if(emptyRetry&&counts.flights===0){await settle(()=>counts.flights===1&&d.querySelector('.room-flight-preview [data-action="hotel-flights"]'));click('.room-flight-preview [data-action="hotel-flights"]');}await settle(()=>d.querySelector('.hotel-flight-inline [name="flight-pair"]'));
   assert.equal(get('#modal-title').textContent.includes('Ваш тур'),false,'inline flights do not insert another offer screen');
   assert.equal(d.querySelectorAll('.hotel-flight-inline [name="flight-pair"]:checked').length,0,'loading inventory never commits a default pair');
   click('.hotel-flight-inline [name="flight-pair"][value="23"]');assert.equal(get('#hotel-detail-offers').disabled,true,'unknown pair price cannot continue');assert.equal(get('#hotel-detail-min').textContent,'Цена уточняется');
   click('[data-action="defer-hotel-flight"]');assert.equal(get('.room-offer-choice.selected .hotel-room-price>strong').textContent,get('#hotel-detail-min').textContent,'manager fallback restores the same checked amount in row and footer');
   click('.room-offer-choice.selected [data-action="hotel-flights"]');await settle(()=>d.querySelector('.hotel-flight-inline [name="flight-pair"]'));
   click('.hotel-flight-inline [name="flight-pair"][value="1"]');const value=get('#hotel-detail-min').textContent;
   assert.equal(get('.room-offer-choice.selected').dataset.offerKey,key);click('#hotel-detail-offers');await settle(()=>d.querySelector('#prototype-lead-form'));
   assert(get('#modal-footer').textContent.includes(value),'same whole-tour pair amount reaches contacts');
  }
  const form=get('#prototype-lead-form'),phone=get('[name="phone"]');phone.value='70000001234';phone.dispatchEvent(new w.Event('input',{bubbles:true}));get('[name="consent"]').checked=true;get('[name="consent"]').dispatchEvent(new w.Event('input',{bubbles:true}));
  const previous=get('#modal-footer').textContent,requests={...counts};
  if(lazy==='terminal'){w.AnyTourPrototypeData.flights=async()=>{counts.flights++;throw Object.assign(Error('expired'),{code:'offer_expired',retryable:false});};click('[data-action="contact-flights"]');await settle(()=>!d.querySelector('#prototype-lead-form')&&d.querySelector('#modal-body .error-text[role="alert"]'));assert.match(get('#modal-body .error-text[role="alert"]').textContent,/Срок предложения истёк/);assert.equal(d.querySelectorAll('[data-action="confirm-tour"],#modal [type="submit"]').length,0,'expired quote has no eligible application');assert.deepEqual(faults,[]);return {width,lazy,terminal_price_revoked:true,supplier_requests:0,lead_requests:0};}
  if(typeof lazy==='string'){
   let resolve;const gate=new Promise(r=>resolve=r);w.AnyTourPrototypeData.flights=async(...args)=>{counts.flights++;await gate;return data.flights(...args);};
   click('[data-action="contact-flights"]');assert.strictEqual(get('#prototype-lead-form'),form,'lazy loading preserves the same form nodes');assert.equal(get('[name="phone"]').value,'70000001234');assert.equal(get('#modal-footer').textContent,previous);
   click('[data-action="'+(lazy==='late-back'?'modal-back':'close-modal')+'"]');resolve();await pause();await pause();assert.equal(d.querySelectorAll('#tour-flight-options').length,0,'late inventory cannot reopen the editor');
   if(lazy==='late-back'){click('#hotel-detail-offers');await settle(()=>d.querySelector('#prototype-lead-form'));assert.equal(get('[name="phone"]').value,'70000001234');}
   assert.equal(counts.quote,1);assert.equal(counts.flights,1);assert.deepEqual(faults,[]);return {width,lazy,late_reply_rejected:true,form_node_preserved:true,supplier_requests:0,lead_requests:0};
  }
  click('[data-action="contact-flights"]');await settle(()=>d.querySelector('#tour-flight-options [name="flight-pair"]'));
  assert.equal(counts.quote,requests.quote,'contact editing reuses current quote');assert.equal(counts.flights,requests.flights+Number(lazy),'known inventory is reused; lazy inventory loaded once');
  const chosen=get('[name="flight-pair"][value="2"]');chosen.click();click('[data-action="modal-back"]');
  assert(get('#prototype-lead-form'),'Cancel returns straight to contacts');assert.equal(get('#modal-footer').textContent,previous);assert.equal(get('[name="phone"]').value,'70000001234');assert.equal(get('[name="consent"]').checked,true,'Cancel retains the original consent identity');
  click('[data-action="contact-flights"]');await settle(()=>d.querySelector('#tour-flight-options [name="flight-pair"]'));click('[name="flight-pair"][value="2"]');const changed=get('#flight-total').textContent;click('[data-action="apply-flight"]');
  assert(get('#prototype-lead-form'),'Apply returns straight to contacts without room confirmation');assert(get('#modal-footer').textContent.includes(changed));assert.equal(get('[name="phone"]').value,'70000001234');assert.equal(get('[name="consent"]').checked,false,'changed pair resets separate consent');
  assert.equal(counts.quote,1);assert.equal(counts.flights,1+Number(emptyRetry));assert.deepEqual(faults,[]);assert.deepEqual(reads,['/fixtures/live-search-2026-09-23.json']);
  return {width,lazy,key,counts,contact_apply_cancel:true,phone_retained:true,consent_reset:true,supplier_requests:0,lead_requests:0};
 }catch(error){console.error('JOURNEY FAILURE',width,lazy,error,faults);throw error;}finally{await pause();dom.window.close();}
}
 const records=[];for(const width of [430,1280])for(const lazy of [false,true])records.push(await run(width,lazy));for(const lazy of ['late-back','late-close','terminal'])records.push(await run(430,lazy));
 console.log('PASS actual Site215 UI boundary: '+JSON.stringify(records));
}

async function run(){
 {const {c,listing,views}=setup();vm.runInContext(slice('function selectHotelFlight(','function syncHotelOfferSelection('),c);c.syncHotelOfferSelection=()=>{};c.withFlightPair=(o,id)=>({...o,flightChoiceId:id,total:201000,pricePending:false});c.selectedOffer={...listing,tour:{price:198600},quoteListingTotal:198600,variants:[{}]};c.selectHotelFlight('one','0');assert.equal(views.get('one').type,'tourvisor-selection');assert.equal(views.get('one').offer.total,201000,'explicit inline pair reaches the existing retained view used on reopen');c.deferHotelFlight('one');assert.equal(views.get('one').offer.total,198600);assert.equal(views.get('one').offer.flightChoiceId,null,'explicit manager choice replaces the retained priced pair without new quote');}
 {const {c,calls}=setup();await c.openLeadWithQuote('one');assert.equal(calls.quote,1);assert.equal(calls.flights,0);assert.equal(calls.offerScreens,0);assert.equal(calls.contacts,1);assert.equal(calls.contactOffer.total,198600);}
 {const {c,calls,listing}=setup();const pair={price:201000};c.selectedOffer={...listing,tour:{price:198600},quoteListingTotal:198600,total:201000,variants:[pair],flightChoiceId:'0'};await c.openLeadWithQuote('one');assert.equal(calls.quote,0);assert.equal(calls.contacts,1);assert.equal(calls.contactOffer.total,201000,'reuse the exact selected pair total');}
 for(const mismatch of ['raw','search','room','party','price']){
  const {c,calls,listing}=setup();c.selectedOffer={...listing,tour:{price:1},quoteListingTotal:198600};
  if(mismatch==='raw')c.selectedOffer.raw={};if(mismatch==='search')c.selectedOffer.search={...listing.search,origin:'Казань'};if(mismatch==='room')c.selectedOffer.room='Standard';if(mismatch==='party')c.selectedOffer.ages=[8];if(mismatch==='price')c.selectedOffer.quoteListingTotal=111;
  await c.openLeadWithQuote('one');assert.equal(calls.quote,1,mismatch+' cannot borrow an earlier price');assert.equal(calls.contactOffer.total,198600);
 }
 for(const interrupt of ['close','new-selection','new-generation']){
  const {c,calls,nodes}=setup(),gate=deferred();c.data.quote=()=>{calls.quote++;return gate.promise;};
  const pending=c.openLeadWithQuote('one');await c.openLeadWithQuote('one');assert.equal(calls.quote,1,'double action shares the current pending intent');
  if(interrupt==='close'){c.cancelHotelOfferCheck();nodes['#modal'].open=false;}if(interrupt==='new-selection')c.hotelOfferDraft={hotelId:1,key:'other'};if(interrupt==='new-generation')c.selectionGeneration++;
  gate.resolve({price:199000});await pending;assert.equal(calls.contacts,0,'late quote after '+interrupt+' cannot navigate');if(interrupt==='close'){assert.equal(c.selectedOffer.loading,false);nodes['#modal'].open=true;c.data.quote=async()=>({price:199000});await c.openLeadWithQuote('one');assert.equal(calls.contacts,1,'explicit re-entry is usable after close');}
 }
 {const {c,calls}=setup();c.data.quote=async()=>{calls.quote++;throw Object.assign(Error('expired'),{code:'offer_expired',retryable:false});};await c.openLeadWithQuote('one');await c.openLeadWithQuote('one');assert.equal(calls.quote,1,'terminal quote is not replayed');assert.equal(calls.contacts,0);assert.equal(calls.offerScreens,0);assert.equal(c.selectedOffer.quoteErrorTerminal,true);}
 for(const outcome of ['success','expired','unknown']){
  const {c,calls,listing,hotel}=setup(),gate=deferred(),quoted={price:199000};let wire=0;
  hotel.offers.push({...listing,key:'two',room:'Other',raw:{...listing.raw}});
  if(outcome==='expired'){
   const canonical=fs.readFileSync(path.join(__dirname,'../v2/prototype-search/data.js'),'utf8');
   const take=(from,to)=>{const a=canonical.indexOf(from),b=canonical.indexOf(to,a+from.length);assert(a>=0&&b>a);return canonical.slice(a,b);};
   Object.assign(listing.raw,{id:'123',operator:'Biblio-Globus',selectionEnabled:true});
   Object.assign(c,{URL,URLSearchParams,AbortController,setTimeout,clearTimeout,location:{href:'https://example.invalid/preview/'},generation:1,searchId:123,quoteReceipts:new WeakMap(),amount:c.data.amount});
   c.window={V2_CONFIG:{api:'https://example.invalid/api.php'},fetch:()=>{wire++;return gate.promise;}};
   vm.runInContext(fs.readFileSync(path.join(__dirname,'../v2/runtime-v3.js'),'utf8'),c);c.rt=c.window.V2Runtime;
   vm.runInContext(take('function assertTourvisorRecalculationSupported(','const image =')+take('function tourvisorQuoteFailure(','async function flights('),c);
   c.data.quote=o=>{calls.quote++;return c.quote(o);};
  }else c.data.quote=()=>{calls.quote++;wire++;return calls.quote===1?gate.promise:Promise.resolve(quoted);};
  vm.runInContext("function selectRoom(key){const b={dataset:{key}};switch('select-hotel-offer'){"+slice("case 'select-hotel-offer':","case 'continue-hotel-offer':")+'}}',c);
  const pending=c.openLeadWithQuote('one');assert.equal(c.selectedOffer.loading,true);
  c.selectRoom('two');assert.equal(c.selectedOffer.loading,false,'changing room cancels only abandoned presentation loading');assert.equal(calls.quote,1,'room selection does not automatically quote');
  if(outcome==='success')gate.resolve(quoted);
  else if(outcome==='expired')gate.resolve({ok:false,status:410,json:async()=>({error:'expired'}),clone(){return this;}});
  else gate.reject(Object.assign(Error('uncertain result'),{code:'quote_unknown',retryable:false}));
  await pending;assert.equal(calls.contacts,0,'old A completion cannot navigate from B');
  c.selectRoom('one');assert.equal(c.hotelOfferCheckActive('one'),true);assert.equal(c.selectedOffer.loading,false,'reselected A is not permanently disabled');assert.equal(calls.quote,1);
  c.continueHotelOffer('one');await new Promise(resolve=>setImmediate(resolve));
  assert.strictEqual(c.selectedOffer.raw,listing.raw);
  if(outcome==='success'){assert.equal(wire,2,'explicit known-success recheck is not a retained-cache or HTTP saving claim');assert.equal(calls.quote,2);assert.equal(calls.contacts,1);assert.strictEqual(calls.contactOffer.tour,quoted);assert.equal(calls.contactOffer.total,199000);}
  else{assert.equal(wire,1,'inactive terminal result cannot replay');assert.equal(calls.quote,1);assert.equal(calls.contacts,0);assert.equal(c.selectedOffer.quoteErrorTerminal,true);if(outcome==='expired')assert.equal(c.selectedOffer.quoteErrorCode,'offer_expired');c.continueHotelOffer('one');await new Promise(resolve=>setImmediate(resolve));assert.equal(calls.quote,1,'terminal/UNKNOWN remains sealed without replay');}
 }
 for(const changed of ['raw','search','price','room']){
  const {c,calls,listing,hotel,views}=setup(),gate=deferred();c.data.quote=()=>{calls.quote++;return gate.promise;};
  const pending=c.openLeadWithQuote('one');c.selectionGeneration++;
  if(changed==='raw')hotel.offers[0]={...listing,raw:{...listing.raw}};
  if(changed==='search')c.state.search={...listing.search,origin:'Казань'};
  if(changed==='price')listing.total++;
  if(changed==='room')listing.room='Other';
  gate.reject(Object.assign(Error('expired old identity'),{code:'offer_expired',retryable:false}));await pending;
  assert.equal(views.size,0,'late terminal result cannot seal changed '+changed);assert.equal(calls.contacts,0);
 }
 {const {c,calls,listing,hotel}=setup('anex'),gate=deferred();let wire=0,request;
  hotel.offers.push({...listing,key:'two',room:'Other',raw:{...listing.raw}});
  c.data.verifyAnexConcrete=()=>{calls.verify++;if(!request){wire++;request=gate.promise;}return request;};
  vm.runInContext("function selectRoom(key){const b={dataset:{key}};switch('select-hotel-offer'){"+slice("case 'select-hotel-offer':","case 'continue-hotel-offer':")+'}}',c);
  const pending=c.refreshLiveHotel(1);c.selectRoom('two');assert.equal(c.selectedOffer.loading,false);
  gate.resolve({state:'current'});await pending;assert.equal(calls.additional,0,'abandoned ANEX context cannot start additional pricing');
  c.selectRoom('one');c.continueHotelOffer('one');await new Promise(resolve=>setImmediate(resolve));
  assert.equal(wire,1);assert.equal(calls.verify,1,'the retained ANEX context is restored without another current check');assert.equal(calls.additional,0);assert.equal(c.selectedOffer.loading,false);
  await c.applyAnexAdditionalPrices();assert.equal(calls.additional,1);assert.equal(c.anexApplicationDraft.price,199300);assert.equal(c.anexApplicationDraft.finalPriceVerified,false);assert.equal(calls.offerScreens,0);
 }
 {const {c,calls}=setup();c.data.quote=async()=>{calls.quote++;if(calls.quote===1)throw Error('temporary');return {price:199000};};await c.openLeadWithQuote('one');assert.equal(calls.contacts,0);await c.openLeadWithQuote('one');assert.equal(calls.contacts,1,'explicit retry recovers');assert.equal(calls.quote,2);}
 {const {c,calls,hotel}=setup('anex','group_minimum');await c.refreshLiveHotel(1);assert.equal(calls.expand,1);assert.equal(hotel.offers.length,1);assert.equal(hotel.offers[0].raw.anexKind,'concrete');assert.equal(calls.rooms,1);assert.equal(calls.lists,0,'expansion stays in the same hotel');assert.equal(calls.offerScreens,0);assert.equal(c.modalType,'hotel-details');}
 {const {c,calls,hotel,nodes}=setup('anex','group_minimum'),gate=deferred();c.data.expandAnexGroup=()=>{calls.expand++;return gate.promise;};const pending=c.refreshLiveHotel(1);nodes['#modal'].open=false;gate.resolve({offers:[]});await pending;assert.equal(hotel.offers.length,1);assert.equal(calls.rooms,0,'closed hotel is not replaced by a late expansion');}
 {const {c,calls}=setup('anex');await c.refreshLiveHotel(1);assert.equal(calls.verify,1);assert.equal(calls.additional,1);assert.equal(calls.offerScreens,0,'current/additional checks stay inline');assert.equal(calls.flights,0);assert.equal(c.anexApplicationDraft.price,199300);assert.equal(c.anexApplicationDraft.finalPriceVerified,false,'calculated estimate never becomes verified');assert.equal(calls.contacts,0,'changed estimate needs a deliberate continue');await c.refreshLiveHotel(1);assert.equal(calls.verify,1);assert.equal(calls.additional,1,'return reuses the retained result');}
 {const {c,calls,views,listing}=setup('anex');views.set('one',{offer:listing,type:'anex-current',result:{state:'current'},error:'UNKNOWN',pending:false});await c.refreshLiveHotel(1);assert.equal(calls.verify,0);assert.equal(calls.additional,0,'an UNKNOWN current result is never retried automatically');}
 verifyResultCardEntry();
 await verifyChoiceComposition();
 await verifySite215Journey();
 console.log('PASS hotel journey: exact price/pair reuse, foreign raw/search/room/party/price rejection, double intent, late/closed selection, A-B-A canonical TV/runtime deferred410 noReplay, explicit success recheck, terminal/UNKNOWN and retained ANEX recovery, inline group/current/estimate and no mandatory flights; supplier/lead calls 0');
}
module.exports=run;module.exports.verifyResultCardEntry=verifyResultCardEntry;module.exports.verifyChoiceComposition=verifyChoiceComposition;if(require.main===module)run().catch(error=>{console.error(error);process.exitCode=1;});
