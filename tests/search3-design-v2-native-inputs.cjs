'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {JSDOM,VirtualConsole}=require('jsdom');
const builder=require('../scripts/build/search3_design_v2_native.cjs');
const source=path.resolve(__dirname,'../src/search3-design-v2');
const trip={origin:'Москва',country:'4',from:'2026-10-25',to:'2026-10-31',minNights:7,maxNights:7,adults:2,ages:[]};
const countries=[{id:'4',name:'Турция'},{id:'8',name:'Египет'}],dictionaries={origins:['Москва','Калининград'],countries,meals:[{nameRu:'Всё включено'}]};
const filters={hotelId:0,hotelIds:[],q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],amenities:[],min:0,max:null,rating:false,beach:false,family:false,spa:false};
const offer={key:'tourvisor:exact',hotelId:7,meal:'Всё включено',total:112000,day:'2026-10-25',nights:7,room:'Standard',operator:'Fixture operator',provider:'tourvisor',search:trip,adults:2,ages:[],raw:{receipt:'identity-only'}};
const hotel={id:7,name:'Verified hotel',country:'4',location:'Турция · Кемер',region:'Кемер',stars:5,photos:[],offers:[offer],raw:{},legacyIds:['701']};
let starts=0,lastIntent=null,lastFilters=null,lookups=0,regions=0;const originRequests=[];
const bridge={async init(){return{search:{...trip,ages:[]},filters,dictionaries,status:'idle',requestedSearch:false};},async search(s,f,cb){starts++;lastIntent=s;lastFilters=f;const result={search:s,filters:f,hotels:[hotel],status:'complete',searchGeneration:starts};cb(result);return result;},async regions(country){regions++;return country==='4'?[{id:'41',name:'Кемер',country:'4',kind:'region',tourvisorIds:['19']},{id:'42',name:'Сиде',country:'4',kind:'region',tourvisorIds:['20']}]:[];},async lookupHotels(query,country,signal){lookups++;assert.equal(country,'4');assert(!signal.aborted);return query.toLowerCase().includes('verified')?[hotel]:[];},changeOrigin(origin){return new Promise(resolve=>originRequests.push({origin,resolve}));}};
const dom=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:'https://anytoour.ru/_preview/search3-design-v2-candidate/visual-search/',runScripts:'outside-only',virtualConsole:new VirtualConsole()});
const w=dom.window,root=w.document.querySelector('[data-product]');w.eval(fs.readFileSync(path.join(source,'tour.js'),'utf8'));w.eval(fs.readFileSync(path.join(source,'frontend.js'),'utf8'));const mounted=w.AnyTourDesignV2Native.mount({root,bridge});
const node=selector=>{const found=root.querySelector(selector);assert(found,selector);return found;};
const click=selector=>node(selector).click(),tick=()=>new Promise(resolve=>w.setTimeout(resolve,0));
function input(selector,value,type='input'){const target=node(selector);target.value=value;target.dispatchEvent(new w.Event(type,{bubbles:true}));return target;}
function submit(){node('[data-search-form]').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));}
const pop=direction=>new Promise((resolve,reject)=>{const timeout=w.setTimeout(()=>reject(new Error('history '+direction+' did not complete')),1000);w.addEventListener('popstate',()=>{w.clearTimeout(timeout);resolve();},{once:true});w.history[direction]();});
async function selectedJourneyChecks(){
 for(const variant of ['st-mobile','st-desktop']){
  const doc=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:w.location.origin+w.location.pathname,runScripts:'outside-only',virtualConsole:new VirtualConsole()}),win=doc.window;
  let product=win.document.querySelector('[data-product]');product.classList.remove('st-mobile');product.classList.add(variant);
  const dearer={...offer,key:'tourvisor:dearer',total:161000,room:'Family Suite',meal:'Завтраки',raw:{receipt:'exact-dearer'}};
  const loaded={...hotel,offers:[offer,dearer]},state={screen:'results',search:trip,filters:{...filters},hotels:[loaded],status:'complete',searchGeneration:1,hasSearched:true,favorites:[]};
  let calls=0,top=0;Object.defineProperty(win,'scrollY',{get:()=>top});Object.defineProperty(win.document,'scrollingElement',{value:win.document.documentElement});
  win.document.documentElement.scrollTo=options=>{top=options.top;};win.HTMLElement.prototype.scrollIntoView=function(){top=0;};
  win.AnyTourDesignV2Tour={...w.AnyTourDesignV2Tour,mount(){return{render({offer:chosen}){calls++;assert.strictEqual(chosen,dearer);},getState(){return{};},destroy(){}};}};
  if(!win.AnyTourDesignV2Tour)win.eval(fs.readFileSync(path.join(source,'tour.js'),'utf8'));win.eval(fs.readFileSync(path.join(source,'frontend.js'),'utf8'));
  const api={async init(){return{dictionaries,requestedSearch:false};}};let owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api,initialState:state});
  const el=s=>{const n=product.querySelector(s);assert(n,s);return n;},tap=s=>{const n=el(s);n.focus();n.click();};
  try{
   await owner.ready;const sort=el('.st-sort');sort.value='name';sort.dispatchEvent(new win.Event('change',{bubbles:true}));top=1320;tap('[data-hotel-open="7"]');assert.equal(top,0,'A hotel opened from a deep result starts at its heading');assert.strictEqual(win.document.activeElement,el('[data-hotel-name]'));
   top=430;tap('[data-hotel-offers] [data-offer="tourvisor:dearer"]');assert.equal(top,0,'The chosen tour starts at the top');assert.strictEqual(owner.getState().selected,dearer);
   tap('[data-action="hotel"]');assert.equal(top,430,'Return restores the exact offer comparison position');assert.strictEqual(win.document.activeElement,el('[data-hotel-offers] [data-offer="tourvisor:dearer"]'));
   assert.equal(el('[data-hotel-min-price]').textContent,'161 000 ₽','The hotel summary follows the selected exact offer, not the minimum');assert.match(el('[data-action="best-tour"]').textContent,/К выбранному туру/);assert.match(el('.st-aside-summary').textContent,/Family Suite/);
   tap('[data-hotel-section="about"]');tap('[data-action="best-tour"]');assert.strictEqual(owner.getState().selected,dearer,'Continue cannot silently replace a more expensive chosen offer');tap('[data-action="hotel"]');assert(!el('[data-about-hotel]').hidden,'Return preserves the hotel section');
   const retained=owner.getState();owner.destroy();const container=win.document.createElement('div');container.innerHTML=builder.extract().markup;win.document.body.replaceChildren(container);product=container.querySelector('[data-product]');owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api,initialState:retained});await owner.ready;assert(!el('[data-about-hotel]').hidden,'Responsive remount preserves the hotel section');
   tap('[data-action="results"]');assert.equal(top,1320,'Return restores the initiating result position');assert.strictEqual(win.document.activeElement,el('[data-hotel-open="7"]'));
   tap('[data-action="filters"]');const cap=el('[data-filter-form] input[type="number"][data-native-budget="max"]');cap.value='150000';cap.dispatchEvent(new win.Event('input',{bubbles:true}));tap('[data-action="apply-filters"]');assert.equal(top,0,'New filter results start at the top rather than restoring a previous list offset');assert.strictEqual(win.document.activeElement,el('[data-result-count]'),'New mobile results focus their visible count, not a hidden empty-state heading');
   assert.equal(calls,2,'Only the two explicit visits render the retained exact tour');
  }finally{owner.destroy();win.close();}
 }
}
async function returnedPriceStateChecks(){
 for(const variant of ['st-mobile','st-desktop']){
  const doc=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:w.location.origin+w.location.pathname,runScripts:'outside-only',virtualConsole:new VirtualConsole()}),win=doc.window,product=win.document.querySelector('[data-product]');
  product.classList.remove('st-mobile');product.classList.add(variant);
  win.eval(fs.readFileSync(path.join(source,'tour.js'),'utf8'));win.eval(fs.readFileSync(path.join(source,'frontend.js'),'utf8'));
  let receipt=null,resolveQuote,expiry,quotes=0,clock=Date.now(),top=840;
  win.Date.now=()=>clock;const nativeTimeout=win.setTimeout.bind(win);
  win.setTimeout=(fn,ms,...args)=>ms>1000?(expiry=fn,41):nativeTimeout(fn,ms,...args);
  Object.defineProperty(win,'scrollY',{get:()=>top});Object.defineProperty(win.document,'scrollingElement',{value:win.document.documentElement});
  win.document.documentElement.scrollTo=options=>{top=options.top;};win.HTMLElement.prototype.scrollIntoView=function(){top=0;};
  const api={async init(){return{dictionaries,requestedSearch:false};},getSelection:()=>receipt,quoteOffer(key,{expectedOffer}){quotes++;assert.strictEqual(expectedOffer,offer);return new Promise(resolve=>{resolveQuote=()=>{receipt={offer,tour:{roomType:offer.room,meal:{},price:179000},variants:[{forward:[],backward:[]}],flightChoiceId:0,total:179000,pricePending:false,priceAccepted:true,expiresAt:clock/1000+10};resolve(receipt);};});}};
  const state={screen:'hotel',search:trip,filters:{...filters},hotels:[hotel],hotelId:hotel.id,status:'complete',searchGeneration:1,hasSearched:true,favorites:[]};
  const owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api,initialState:state}),el=s=>{const n=product.querySelector(s);assert(n,s);return n;},tap=s=>{const n=el(s);n.focus();n.click();};
  try{
   await owner.ready;tap('[data-hotel-offers] [data-offer="tourvisor:exact"]');tap('[data-action="hotel"]');tap('[data-hotel-section="about"]');top=840;
   resolveQuote();await new Promise(resolve=>nativeTimeout(resolve,0));
   assert.equal(state.screen,'hotel');assert.equal(el('[data-about-hotel]').hidden,false);assert.strictEqual(win.document.activeElement,el('[data-hotel-section="about"]'));assert.equal(top,840);
   assert.equal(el('[data-hotel-min-price]').textContent,'179 000 ₽','A quote settling after Back refreshes the selected total');
   assert.match(el('[data-hotel-price-caption]').textContent,/Проверенная цена/);assert.match(el('.st-offer--selected .st-offer-price-caption').textContent,/С выбранным перелётом/);
   assert.match(el('.st-offer--selected .st-offer-delta').textContent,/112 000 ₽/,'The selected row retains its original search baseline');
   assert.match(el('[data-lead-choice]').textContent,/Всё включено/,'An empty checked meal object retains the factual offer meal');
   clock+=11000;assert(expiry);expiry();
   assert.match(el('[data-hotel-price-caption]').textContent,/Последняя цена.*нужна проверка/,'Expiry updates the visible hotel, not only hidden tour/contact screens');
   assert.match(el('.st-offer--selected .st-offer-price-caption').textContent,/нужна проверка/);assert.equal(el('[data-about-hotel]').hidden,false);assert.strictEqual(win.document.activeElement,el('[data-hotel-section="about"]'));assert.equal(top,840);assert.equal(quotes,1);
  }finally{owner.destroy();win.close();}
 }
}
async function responsiveTourFocusChecks(){
 const parts=builder.extract(),doc=new JSDOM('<!doctype html><head><style>'+parts.css+'\n'+parts.desktopCSS+'</style></head><body>'+parts.markup+'</body>',{url:w.location.origin+w.location.pathname,runScripts:'outside-only',virtualConsole:new VirtualConsole()}),win=doc.window;
 let product=win.document.querySelector('[data-product]'),owner;
 const state={screen:'tour',search:trip,filters:{...filters},hotels:[hotel],selected:offer,hotelId:hotel.id,status:'complete',hasSearched:true,favorites:[]};
 const api={async init(){return{dictionaries,requestedSearch:false};}};
 win.AnyTourDesignV2Tour={...w.AnyTourDesignV2Tour,mount(){return{render({offer:chosen}){assert.strictEqual(chosen,offer);},getState(){return{};},destroy(){}};}};
 if(!win.AnyTourDesignV2Tour)win.eval(fs.readFileSync(path.join(source,'tour.js'),'utf8'));win.eval(fs.readFileSync(path.join(source,'frontend.js'),'utf8'));
 try{
  owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api,initialState:state});await owner.ready;
  const quick=product.querySelector('.st-quick-tour [data-action="lead"]');quick.focus();assert.strictEqual(win.document.activeElement,quick);
  const retained=owner.getState();owner.destroy();const wrap=win.document.createElement('div');wrap.innerHTML=parts.markup;win.document.body.replaceChildren(wrap);product=wrap.querySelector('[data-product]');product.classList.replace('st-mobile','st-desktop');
  owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api,initialState:retained});await owner.ready;
  assert.equal(win.getComputedStyle(product.querySelector('.st-quick-tour')).display,'none','The existing desktop CSS hides the mobile quick action');
  assert.strictEqual(win.document.activeElement,product.querySelector('[data-screen="tour"] h1'),'A CSS-hidden initiating action falls back to the visible tour heading after responsive remount');
  assert.strictEqual(owner.getState().selected,offer,'Responsive focus recovery retains the canonical offer');
 }finally{owner?.destroy();win.close();}
}
async function groupMinimumChecks(){
 for(const outcome of ['complete','error','stale']){
  const doc=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:w.location.origin+w.location.pathname,runScripts:'outside-only',virtualConsole:new VirtualConsole()}),win=doc.window;
  const group={...offer,key:'anex:group:'+outcome,provider:'anex',raw:{anexKind:'group_minimum'}},concrete={...offer,key:'anex:concrete:'+outcome,provider:'anex',total:114000,raw:{anexKind:'concrete'}},loaded={...hotel,offers:[group]};
  let product=win.document.querySelector('[data-product]'),owner,searches=0,expansions=0,quotes=0,resolve,reject;
  const api={async init(){return{search:trip,filters:{...filters,hotelIds:[],stars:[],meals:[],resorts:[]},dictionaries,status:'idle',requestedSearch:false};},async search(s,f,cb){searches++;const result={hotels:[loaded],status:'complete',searchGeneration:searches};cb(result);return result;},expandOfferGroup(key,{expectedOffer}){expansions++;assert.equal(key,group.key);assert.strictEqual(expectedOffer,group,'expansion binds the exact loaded minimum');return new Promise((yes,no)=>{resolve=()=>{loaded.offers.push(concrete);yes([concrete]);};reject=no;});}};
  win.AnyTourDesignV2Tour={...w.AnyTourDesignV2Tour,mount(){return{render(context){quotes++;assert.strictEqual(context.offer,concrete,'only a returned exact concrete offer reaches the tour owner');},getState(){return{};},destroy(){}};}};
  if(!win.AnyTourDesignV2Tour)win.eval(fs.readFileSync(path.join(source,'tour.js'),'utf8'));win.eval(fs.readFileSync(path.join(source,'frontend.js'),'utf8'));
  const el=s=>{const n=product.querySelector(s);assert(n,s);return n;},tap=s=>el(s).click(),settle=async()=>{await new Promise(r=>win.setTimeout(r,0));await new Promise(r=>win.setTimeout(r,0));};
  const remount=async()=>{const retained=owner.getState();owner.destroy();const wrap=win.document.createElement('div');wrap.innerHTML=builder.extract().markup;win.document.body.replaceChildren(wrap);product=wrap.querySelector('[data-product]');owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api,initialState:retained});await owner.ready;};
  owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api});
  try{
   await owner.ready;assert.equal(expansions+quotes+searches,0,'passive mount has no search, expansion or tour verification');
   el('[data-search-form]').dispatchEvent(new win.Event('submit',{bubbles:true,cancelable:true}));await settle();
   const card='[data-screen="results"] [data-offer="'+group.key+'"]',expand='[data-native-expand-group="'+group.key+'"]';
   assert.match(el(card).textContent,/Выбрать вариант/);tap(card);assert.equal(owner.getState().screen,'hotel');assert.equal(owner.getState().selected,null,'minimum is not selected as a tour');
   assert.match(el('[data-hotel-min-price]').textContent,/^от /);assert.equal(expansions+quotes,0,'card opens room variants without paid work');
   tap('[data-action="results"]');tap('[data-hotel-about="7"]');tap('[data-action="best-tour"]');assert.equal(owner.getState().screen,'hotel');assert(!el('[data-hotel-offers-panel]').hidden);assert.equal(expansions+quotes,0,'summary CTA opens variants without a preliminary tour');
   owner.render();tap(expand);el(expand).dispatchEvent(new win.MouseEvent('click',{bubbles:true}));await settle();
   assert.equal(expansions,1,'one explicit expansion is reserved before awaiting');assert(el(expand).disabled);assert.equal(quotes,0);assert.strictEqual(win.document.activeElement,el('[data-native-group-status]'),'pending focus remains on a readable status instead of body');
   if(outcome==='complete'){
    await remount();assert(el(expand).disabled,'pending expansion survives viewport remount');assert.strictEqual(win.document.activeElement,el('[data-native-group-status]'),'pending status focus survives remount');assert.equal(expansions,1);
    const retainedCard=win.document.createElement('button');retainedCard.dataset.offer=concrete.key;retainedCard.textContent='Retained result action';el('[data-card-list]').prepend(retainedCard);resolve();await settle();
    const choice='[data-hotel-offers] [data-offer="'+concrete.key+'"]';assert(!product.querySelector(expand),'only the resolved exact group is replaced by concrete offers');assert.strictEqual(win.document.activeElement,el(choice),'completion focuses the returned concrete offer');
    assert.strictEqual(owner.getState().hotels[0].offers[1],concrete);assert.equal(quotes,0,'completed expansion does not verify a tour passively');
    assert.equal(el('[data-hotel-min-price]').textContent,'114 000 ₽','hotel minimum follows the visible exact offers rather than a hidden earlier group price');assert.equal(el('[data-hotel-offers] .st-offer-delta').textContent,'Минимальная цена');assert(el('[data-offer-comparison]').hidden,'a single exact offer has no comparison against the hidden group minimum');
    tap(choice);assert.strictEqual(owner.getState().selected,concrete);assert.equal(owner.getState().screen,'tour');assert.equal(quotes,1);
    tap('[data-action="hotel"]');tap('[data-action="results"]');assert.match(el('[data-hotel-card="7"] .st-card-all').textContent,/Все варианты \(1\)/,'result card counts the same visible exact offer rows as the hotel panel');tap(card);owner.render();await remount();
    assert.equal(expansions,1,'Back, cached return, render and remount do not repeat expansion');assert.equal(quotes,1,'return to variants does not repeat tour verification');assert.strictEqual(owner.getState().selected,concrete);
   }else if(outcome==='error'){
    reject(new Error('Fixture expansion unavailable'));await settle();assert.match(el('[data-hotel-offers] [role="alert"]').textContent,/Не удалось получить точные варианты/);assert(!product.textContent.includes('Fixture expansion unavailable'),'raw provider errors are not shown');assert(el(expand).disabled);assert.strictEqual(win.document.activeElement,el('[data-native-group-status]'),'error completion retains readable focus');
    tap('[data-action="results"]');tap(card);await remount();assert.strictEqual(win.document.activeElement,el('[data-native-group-status]'),'error focus survives remount');tap(expand);await settle();assert.equal(expansions,1,'an error/unknown operation cannot be replayed by return or remount');assert.equal(quotes,0);
   }else{
    const replacement={...group,raw:{anexKind:'group_minimum'}},current={...loaded,offers:[replacement]};owner.getState().hotels=[current];owner.getState().searchGeneration++;
    resolve();await settle();assert.strictEqual(owner.getState().hotels[0],current,'obsolete completion cannot replace a newer canonical result');assert(!product.querySelector('[data-offer="'+concrete.key+'"]'));assert.equal(owner.getState().selected,null);assert.equal(expansions,1);assert.equal(quotes,0);
   }
   assert.equal(searches,1,'all navigation retains the single explicit fixture search');
  }finally{owner.destroy();win.close();}
 }
}
async function loadedBudgetChecks(){
 const doc=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:w.location.origin+w.location.pathname,runScripts:'outside-only',virtualConsole:new VirtualConsole()});
 const win=doc.window,product=win.document.querySelector('[data-product]');product.classList.add('st-mobile');
 const cheap={...offer,key:'tourvisor:cheap',hotelId:8,total:99000},loaded=[hotel,{...hotel,id:8,name:'Cheaper fixture hotel',offers:[cheap]}];let searches=0;
 const api={async init(){return{search:trip,filters:{...filters,min:0,max:null,hotelIds:[],resorts:[]},dictionaries,status:'idle',requestedSearch:false};},async search(s,f,cb){searches++;const result={hotels:loaded,status:'complete'};cb(result);return result;}};
 if(!win.AnyTourDesignV2Tour)win.eval(fs.readFileSync(path.join(source,'tour.js'),'utf8'));win.eval(fs.readFileSync(path.join(source,'frontend.js'),'utf8'));let owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api});await owner.ready;
 const el=s=>{const n=product.querySelector(s);assert(n,s);return n;},tap=s=>el(s).click();
 const edit=(s,value,type='input')=>{const n=el(s);n.value=value;n.dispatchEvent(new win.Event(type,{bubbles:true}));};
 el('[data-search-form]').dispatchEvent(new win.Event('submit',{bubbles:true,cancelable:true}));await new Promise(r=>win.setTimeout(r,0));
 tap('[data-action="filters"]');assert.equal(el('[data-filter-apply]').textContent,'Показать 2 отеля');
 edit('[data-filter-form] input[type="number"][data-native-budget="max"]','100000');
 assert.equal(el('[data-filter-apply]').textContent,'Показать 1 отель','mobile budget updates the actual loaded result count before Apply');
 assert.equal(el('[data-filter-form] input[type="range"]').value,'100000','number and slider agree');
 edit('[data-filter-form] input[type="number"][data-native-budget="min"]','150000');
 assert(el('[data-filter-apply]').disabled,'invalid range cannot silently apply prior valid values');assert(!el('[data-filter-form] [data-native-budget-error]').hidden);
 assert.equal(owner.getState().filters.max,null,'unapplied budget remains a local draft');
 tap('[data-action="close-filters"]');tap('[data-action="filters"]');assert.equal(el('[data-filter-form] input[type="number"][data-native-budget="max"]').value,'','Cancel restores the applied budget');
 edit('[data-filter-form] input[type="number"][data-native-budget="max"]','100000');
 const retained=owner.getState();owner.destroy();owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api,initialState:retained});await owner.ready;
 assert.equal(el('[data-filter-form] input[type="number"][data-native-budget="max"]').value,'100000','viewport remount retains the open filter draft');assert.equal(el('[data-filter-apply]').textContent,'Показать 1 отель');
 tap('[data-action="apply-filters"]');assert.equal(product.querySelectorAll('.st-hotel-card').length,1);assert.strictEqual(owner.getState().hotels[1].offers[0],cheap,'local filtering preserves exact canonical offer reference');
 tap('[data-action="filters"]');tap('[data-screen="filters"] [data-action="reset-draft"]');assert.equal(el('[data-filter-apply]').textContent,'Показать 2 отеля');assert.equal(owner.getState().filters.max,100000,'drawer Reset only changes its draft');tap('[data-action="close-filters"]');assert.equal(product.querySelectorAll('.st-hotel-card').length,1,'Cancel also cancels a draft Reset');
 tap('[data-action="filters"]');edit('[data-filter-form] input[type="range"]','112000');assert.equal(el('[data-filter-form] input[type="number"][data-native-budget="max"]').value,'112000');assert.equal(el('[data-filter-apply]').textContent,'Показать 2 отеля');
 edit('[data-filter-form] input[type="number"][data-native-budget="max"]','');assert.equal(el('[data-filter-form] input[type="range"]').value,'112000');tap('[data-action="apply-filters"]');assert.equal(owner.getState().filters.max,null,'clearing the maximum really removes the cap');
 assert.equal(searches,1,'budget edits, Cancel, Apply and viewport remount start no supplier search');owner.destroy();win.close();
}
async function progressiveBudgetChecks(){
 const doc=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:w.location.origin+w.location.pathname,runScripts:'outside-only',virtualConsole:new VirtualConsole()});
 const win=doc.window,product=win.document.querySelector('[data-product]');product.classList.add('st-mobile');
 const cheap={...offer,key:'tourvisor:progress-cheap',hotelId:8,total:99000},expensive={...offer,key:'tourvisor:progress-expensive',hotelId:9,total:250000};let starts=0,receive;
 const api={async init(){return{search:trip,filters:{...filters,min:0,max:null,hotelId:0,hotelIds:[],stars:[],meals:[],resorts:[]},dictionaries,status:'idle'};},search(s,f,cb){starts++;receive=cb;cb({hotels:[hotel],status:'loading',searchGeneration:1});return new Promise(()=>{});}};
 if(!win.AnyTourDesignV2Tour)win.eval(fs.readFileSync(path.join(source,'tour.js'),'utf8'));win.eval(fs.readFileSync(path.join(source,'frontend.js'),'utf8'));const owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api});await owner.ready;
 const el=s=>{const n=product.querySelector(s);assert(n,s);return n;};el('[data-search-form]').dispatchEvent(new win.Event('submit',{bubbles:true,cancelable:true}));await new Promise(r=>win.setTimeout(r,0));el('[data-action="filters"]').click();
 const cap=el('[data-filter-form] input[type="number"][data-native-budget="max"]'),min=el('[data-filter-form] input[type="number"][data-native-budget="min"]');
 cap.value='100000';cap.dispatchEvent(new win.Event('input',{bubbles:true}));cap.focus();assert.equal(el('[data-filter-apply]').textContent,'Показать 0 отелей');
 receive({hotels:[hotel,{...hotel,id:8,offers:[cheap]}],status:'loading',searchGeneration:1});
 assert.equal(el('[data-filter-apply]').textContent,'Показать 1 отель','same-generation progressive results refresh the active drawer count');assert.strictEqual(el('[data-filter-form] input[type="number"][data-native-budget="max"]'),cap,'progress does not replace a typed budget node');assert.strictEqual(win.document.activeElement,cap);assert.equal(cap.value,'100000');
 min.value='150000';min.dispatchEvent(new win.Event('input',{bubbles:true}));min.focus();assert(el('[data-filter-apply]').disabled);
 receive({hotels:[hotel,{...hotel,id:8,offers:[cheap]},{...hotel,id:9,offers:[expensive]}],status:'complete',searchGeneration:1});
 assert.strictEqual(win.document.activeElement,min);assert.equal(min.value,'150000','progress retains an invalid raw draft');assert(el('[data-filter-apply]').disabled);assert.equal(el('[data-filter-form] input[type="range"]').max,'250000','slider extent includes the newly loaded full price');
 min.value='';min.dispatchEvent(new win.Event('input',{bubbles:true}));assert.equal(el('[data-filter-apply]').textContent,'Показать 1 отель');el('[data-action="apply-filters"]').click();assert.equal(product.querySelectorAll('.st-hotel-card').length,1);assert.equal(owner.getState().filters.max,100000);assert.equal(starts,1);owner.destroy();win.close();
}
async function unstartedNavigationChecks(){
 for(const mobile of [false,true]){
  const doc=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:w.location.origin+w.location.pathname,runScripts:'outside-only',virtualConsole:new VirtualConsole()});
  const win=doc.window,product=win.document.querySelector('[data-product]');product.classList.toggle('st-mobile',mobile);let searches=0;
  win.localStorage.setItem('anytour.prototype.v18.favorites.v1','[7]');
  const api={async init(){return{search:{...trip,ages:[]},filters:{...filters,hotelIds:[],stars:[],meals:[],resorts:[]},dictionaries,status:'idle',requestedSearch:false};},async search(s,f,cb){searches++;const result={hotels:[],status:'complete',searchGeneration:searches};cb(result);return result;}};
  if(!win.AnyTourDesignV2Tour)win.eval(fs.readFileSync(path.join(source,'tour.js'),'utf8'));win.eval(fs.readFileSync(path.join(source,'frontend.js'),'utf8'));const owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api});
  const el=s=>{const n=product.querySelector(s);assert(n,s);return n;};
  try{
   await owner.ready;const back=el('[data-screen="search"] [data-action="results"]');
   assert(back.hidden,'No saved-results return is offered before any search');
   back.click();assert.equal(owner.getState().screen,'search','A stale results action cannot fabricate a completed search');
   el('[data-action="favorites"]').click();assert.equal(owner.getState().screen,'results');
   assert.equal(el('[data-result-count]').textContent,'Поиск ещё не запускали');
   assert.equal(el('[data-empty-title]').textContent,'Начните с поиска');
   assert.equal(el('[data-empty-action]').textContent,'К параметрам поиска');
   assert(!el('[data-empty]').textContent.includes('Нет подходящих туров'));
   assert.equal(product.querySelectorAll('.st-hotel-card').length,0);assert.equal(searches,0);
   assert.equal(win.localStorage.getItem('anytour.prototype.v18.favorites.v1'),'[7]','Presearch navigation retains saved favorites');
   el('[data-empty-action]').click();assert.equal(owner.getState().screen,'search');assert(back.hidden);
   el('[data-search-form]').dispatchEvent(new win.Event('submit',{bubbles:true,cancelable:true}));await new Promise(r=>win.setTimeout(r,0));
   assert.equal(searches,1);assert.equal(el('[data-result-count]').textContent,'0 отелей');
   assert.equal(el('[data-empty-title]').textContent,'Нет подходящих туров','An actually completed empty search retains its factual empty state');
   el('[data-action="search"]').click();assert(!back.hidden,'A real completed search enables its saved-results return');back.click();
   assert.equal(owner.getState().screen,'results');assert.equal(searches,1,'Returning to the actual empty result starts no new search');
  }finally{owner.destroy();win.close();}
 }
}
async function destinationFocusChecks(){
 for(const mobile of [false,true]){
  const doc=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:w.location.href,runScripts:'outside-only',virtualConsole:new VirtualConsole()});
  const win=doc.window,product=win.document.querySelector('[data-product]');product.classList.toggle('st-mobile',mobile);let searches=0;const pending=[];
  const api={async init(){return{search:{...trip,ages:[]},filters:{...filters,hotelIds:[],stars:[],meals:[],resorts:[]},dictionaries,status:'idle',requestedSearch:false};},async search(){searches++;throw Error('Destination edits must not search');},async regions(country){return country==='4'?[{id:'41',name:'Кемер',country:'4'},{id:'42',name:'Сиде',country:'4'}]:[];},lookupHotels(query,country,signal){return new Promise(resolve=>pending.push({resolve,query,country,signal}));}};
  if(!win.AnyTourDesignV2Tour)win.eval(fs.readFileSync(path.join(source,'tour.js'),'utf8'));win.eval(fs.readFileSync(path.join(source,'frontend.js'),'utf8'));const owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api});
  const el=s=>{const n=product.querySelector(s);assert(n,s);return n;},tap=s=>{const n=el(s);n.focus();n.click();},settle=()=>new Promise(r=>win.setTimeout(r,0));
  const country=id=>'[data-native-destination="country"][data-native-destination-id="'+id+'"]',resort=id=>'[data-native-destination="resort"][data-native-destination-id="'+id+'"]';
  const escape=()=>win.document.activeElement.dispatchEvent(new win.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
  try{
   await owner.ready;tap('[data-search-destination-toggle]');await settle();tap(country('4'));
   assert.strictEqual(win.document.activeElement,el(country('4')),'Same-country selection retains a keyboard target');await settle();assert.strictEqual(win.document.activeElement,el(country('4')),'Dictionary completion retains the chosen country');
   escape();assert(el('[data-destination-editor]').hidden,'Escape closes the direction editor after choosing');assert.strictEqual(win.document.activeElement,el('[data-search-destination-toggle]'));
   tap('[data-search-destination-toggle]');await settle();tap(resort('41'));assert.strictEqual(win.document.activeElement,el(resort('41')));tap(resort('42'));
   tap('[data-native-destination-remove="resort"][data-native-destination-value="Кемер"]');assert.strictEqual(win.document.activeElement,el('[data-native-destination-remove="resort"][data-native-destination-value="Сиде"]'),'Removing a chip focuses the surviving adjacent chip');
   tap('[data-native-destination-remove="resort"][data-native-destination-value="Сиде"]');assert.strictEqual(win.document.activeElement,el('[data-destination-all]'),'Removing the final chip focuses whole country');
   tap(country('8'));assert(!el('[data-destination-confirm]').hidden);assert.strictEqual(win.document.activeElement,el('[data-destination-keep]'),'Replacement confirmation focuses the visible Keep action');assert.equal(el('[data-destination-country]').textContent,'Турция','A pending country does not mutate the draft');
   tap('[data-destination-keep]');assert(el('[data-destination-confirm]').hidden);assert.strictEqual(win.document.activeElement,el(country('8')),'Keep returns to the rejected choice');assert.equal(el('[data-destination-country]').textContent,'Турция');
   tap(country('8'));tap('[data-destination-replace]');assert.strictEqual(win.document.activeElement,el(country('8')),'Replacement returns focus to the accepted country');await settle();assert.strictEqual(win.document.activeElement,el(country('8')));assert.equal(el('[data-destination-country]').textContent,'Египет');
   escape();assert.equal(el('[data-destination-country]').textContent,'Турция','Escape restores the original country after replacement');assert.equal(el('[data-destination-summary]').textContent,'Вся страна');
   tap('[data-search-destination-toggle]');await settle();const query=el('[data-destination-query]');query.focus();query.value='Ту';query.dispatchEvent(new win.Event('input',{bubbles:true}));await settle();assert.equal(pending.length,1);
   tap(country('4'));await settle();assert.equal(pending.length,2);assert(pending[0].signal.aborted,'Previous lookup is invalidated');assert.strictEqual(win.document.activeElement,el(country('4')));
   pending[0].resolve([{id:999,name:'Obsolete country hotel',country:'4'}]);await settle();assert.strictEqual(win.document.activeElement,el(country('4')));assert(!product.querySelector('[data-native-destination-id="999"]'),'Obsolete lookup cannot reinsert an identity');
   pending[1].resolve([]);await settle();assert.strictEqual(win.document.activeElement,el(country('4')),'Latest asynchronous lookup completion preserves country focus');escape();assert(el('[data-destination-editor]').hidden);assert.equal(searches,0);
  }finally{owner.destroy();win.close();}
 }
}
async function formFocusChecks(){
 for(const mobile of [false,true]){
  const doc=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:w.location.origin+w.location.pathname,runScripts:'outside-only',virtualConsole:new VirtualConsole()});
  const win=doc.window,product=win.document.querySelector('[data-product]');product.classList.toggle('st-mobile',mobile);let searches=0;
  const api={async init(){return{search:{...trip,ages:[]},filters:{...filters,hotelIds:[],stars:[],meals:[],resorts:[]},dictionaries,status:'idle',requestedSearch:false};},async search(){searches++;throw new Error('Input edits must not search');}};
  if(!win.AnyTourDesignV2Tour)win.eval(fs.readFileSync(path.join(source,'tour.js'),'utf8'));win.eval(fs.readFileSync(path.join(source,'frontend.js'),'utf8'));const owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api});
  const el=s=>{const n=product.querySelector(s);assert(n,s);return n;},tap=s=>{const n=el(s);n.focus();n.click();};
  try{
   await owner.ready;
   tap('[data-search-nights-toggle]');const night=el('[data-native-night="10"]');tap('[data-native-night="10"]');
   assert.strictEqual(win.document.activeElement,night,'Selected night retains focus and its DOM identity');
   tap('[data-native-night="14"]');assert.strictEqual(win.document.activeElement,el('[data-native-night="14"]'),'Second range endpoint retains focus');
   tap('[data-native-night="28"]');tap('[data-native-night="1"]');assert(!el('[data-search-night-error]').hidden);
   assert.strictEqual(win.document.activeElement,el('[data-native-night="1"]'),'An invalid range leaves focus on its rejected endpoint');
   tap('[data-search-editor-cancel="nights"]');assert.strictEqual(win.document.activeElement,el('[data-search-nights-toggle]'));
   assert.equal(el('[data-search-night-value]').textContent,'7 ночей','Cancel restores the untouched duration');
   tap('[data-search-nights-toggle]');tap('[data-native-night="10"]');
   win.document.activeElement.dispatchEvent(new win.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
   assert.strictEqual(win.document.activeElement,el('[data-search-nights-toggle]'),'Escape restores the duration opener after a keyboard edit');
   assert(el('[data-search-nights-editor]').hidden);assert.equal(el('[data-search-night-value]').textContent,'7 ночей','Escape discards the duration draft');
   tap('[data-search-travelers-toggle]');tap('[data-search-add-child]');
   assert.strictEqual(win.document.activeElement,el('[data-native-age="0"]'),'Adding a child focuses the required age');
   const age=el('[data-native-age="0"]');age.value='0';age.dispatchEvent(new win.Event('change',{bubbles:true}));
   assert.strictEqual(el('[data-native-age="0"]'),age,'Changing age preserves the native select');assert.strictEqual(win.document.activeElement,age);
   tap('[data-search-add-child]');const second=el('[data-native-age="1"]');assert.strictEqual(win.document.activeElement,second);
   second.value='7';second.dispatchEvent(new win.Event('change',{bubbles:true}));assert.strictEqual(win.document.activeElement,second);
   tap('[data-native-remove-child="0"]');assert.equal(el('[data-native-age="0"]').value,'7','Removing a child preserves the remaining age');
   assert.strictEqual(win.document.activeElement,el('[data-native-age="0"]'),'Removed row moves focus to the next remaining age');
   tap('[data-native-remove-child="0"]');assert.strictEqual(win.document.activeElement,el('[data-search-add-child]'),'Last removed child returns focus to Add');
   tap('[data-search-editor-cancel="travelers"]');assert.strictEqual(win.document.activeElement,el('[data-search-travelers-toggle]'));
   tap('[data-search-date-toggle]');
   const next=()=>product.querySelector('[data-calendar-month="1"]:not(.st-calendar-mobile-next)');
   next().focus();next().click();assert.strictEqual(win.document.activeElement,next(),'Paging retains focus on the visible next-month action');
   tap('[data-calendar-month="-1"]');assert(el('[data-calendar-month="-1"]').disabled);
   assert.strictEqual(win.document.activeElement,next(),'At the earliest month the enabled next action receives focus');
   win.document.activeElement.dispatchEvent(new win.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
   assert.strictEqual(win.document.activeElement,el('[data-search-date-toggle]'),'Escape restores the date opener after paging');assert(el('[data-search-calendar]').hidden);
   assert.equal(el('[data-search-date-value]').textContent,'25–31 окт');assert.equal(searches,0);
  }finally{owner.destroy();win.close();}
 }
}
async function popupSubmitChecks(){
 for(const mobile of [false,true]){
  const doc=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:w.location.origin+w.location.pathname,runScripts:'outside-only',virtualConsole:new VirtualConsole()});
  const win=doc.window,product=win.document.querySelector('[data-product]');product.classList.toggle('st-mobile',mobile);const intents=[];
  const api={async init(){return{search:{...trip,ages:[]},filters:{...filters,hotelIds:[],stars:[],meals:[],resorts:[]},dictionaries,status:'idle'};},async search(s,f,cb){intents.push(s);const result={hotels:[],status:'complete',searchGeneration:intents.length};cb(result);return result;},async regions(){return[];}};
  if(!win.AnyTourDesignV2Tour)win.eval(fs.readFileSync(path.join(source,'tour.js'),'utf8'));win.eval(fs.readFileSync(path.join(source,'frontend.js'),'utf8'));const owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api});
  const el=s=>{const n=product.querySelector(s);assert(n,s);return n;},tap=s=>el(s).click(),send=()=>el('[data-search-form]').dispatchEvent(new win.Event('submit',{bubbles:true,cancelable:true})),settle=()=>new Promise(r=>win.setTimeout(r,0));
  try{
   await owner.ready;const submit=el('[type="submit"]'),home=submit.parentElement,popup=el('[data-native-form-popup]');
   tap('[data-search-nights-toggle]');assert(popup.open);assert.strictEqual(submit.closest('dialog'),popup);assert.equal(el('[data-search-form]').querySelectorAll('[type="submit"]').length,1,'Popup reuses the one canonical Find control');
   tap('[data-native-night="10"]');popup.dispatchEvent(new win.Event('cancel',{cancelable:true}));assert(!popup.open);assert.equal(el('[data-search-night-value]').textContent,'7 ночей');assert.strictEqual(submit.parentElement,home);assert.strictEqual(win.document.activeElement,el('[data-search-nights-toggle]'));
   tap('[data-search-date-toggle]');tap('[data-calendar-boundary="to"]');tap('[data-native-date="2026-10-30"]');send();await settle();
   assert.equal(intents.length,1,'Find applies a valid open calendar and starts exactly once');assert.equal(intents[0].from,trip.from);assert.equal(intents[0].to,'2026-10-30');assert(!popup.open);assert.strictEqual(submit.parentElement,home);
   tap('[data-action="search"]');tap('[data-search-travelers-toggle]');tap('[data-search-add-child]');send();await settle();assert.equal(intents.length,1,'Incomplete child age blocks Find locally');assert(popup.open);assert.strictEqual(win.document.activeElement,el('[data-native-age="0"]'));assert.match(el('[data-search-family-status]').textContent,/Укажите возраст ребёнка 1/);
   const age=el('[data-native-age="0"]');age.value='7';age.dispatchEvent(new win.Event('change',{bubbles:true}));send();await settle();assert.equal(intents.length,2);assert.deepEqual(Array.from(intents[1].ages),[7]);assert(!popup.open);
   tap('[data-action="search"]');tap('[data-search-date-toggle]');tap('[data-calendar-boundary="to"]');tap('[data-native-date="2026-10-31"]');send();await settle();assert.equal(intents.length,2,'Date change still requires age review before a supplier start');assert(popup.open);assert.equal(popup.dataset.editor,'travelers');assert.equal(el('[data-native-age="0"]').value,'7');assert.match(el('[data-search-family-status]').textContent,/Подтвердите возраста/);
   send();await settle();assert.equal(intents.length,3,'Find from the age-review popup confirms retained ages and starts once');assert.equal(intents[2].to,trip.to);assert.deepEqual(Array.from(intents[2].ages),[7]);
   tap('[data-action="search"]');tap('[data-conditions-open="budget"]');const cap=el('[data-search-budget="max"]');cap.value='abc';cap.dispatchEvent(new win.Event('input',{bubbles:true}));send();await settle();assert.equal(intents.length,3);assert(popup.open);assert.equal(cap.value,'abc');assert.strictEqual(win.document.activeElement,cap);assert(!el('[data-search-budget-error]').hidden);
   cap.value='100000';cap.dispatchEvent(new win.Event('input',{bubbles:true}));const min=el('[data-search-budget="min"]');min.value='200000';min.dispatchEvent(new win.Event('input',{bubbles:true}));submit.focus();send();await settle();assert.equal(intents.length,3);assert.strictEqual(win.document.activeElement,cap,'Reversed numeric budget focuses its upper bound');assert.equal(min.value,'200000');assert.equal(cap.value,'100000');tap('[data-conditions-cancel="budget"]');
   tap('[data-search-destination-toggle]');await settle();tap('[data-native-destination="country"][data-native-destination-id="8"]');send();await settle();assert.equal(intents.length,3,'Find does not silently accept a pending destination replacement');assert(!el('[data-destination-confirm]').hidden);assert.strictEqual(win.document.activeElement,el('[data-destination-keep]'));tap('[data-destination-cancel]');
   owner.destroy();assert.strictEqual(submit.parentElement,home);assert.equal(product.querySelector('dialog'),null,'Responsive teardown removes the modal and returns the original fields');
  }finally{owner.destroy();win.close();}
 }
}
async function familyCopyChecks(){
 // Literal language expectations keep nominative summaries separate from prices after «за».
 const adultSummary=['','1 взрослый','2 взрослых','3 взрослых','4 взрослых','5 взрослых','6 взрослых'];
 const adultPrice=['','1 взрослого','2 взрослых','3 взрослых','4 взрослых','5 взрослых','6 взрослых'];
 const childSummary=['',' + 1 ребёнок',' + 2 ребёнка',' + 3 ребёнка'];
 const childPrice=['',' + 1 ребёнка',' + 2 детей',' + 3 детей'];
 const doc=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:w.location.origin+w.location.pathname,runScripts:'outside-only',virtualConsole:new VirtualConsole()});
 const win=doc.window,product=win.document.querySelector('[data-product]');let searches=0,loadedOffer=null;
 const api={async init(){return{search:{...trip,ages:[]},filters:{...filters,min:0,max:null,hotelId:0,hotelIds:[],stars:[],meals:[],resorts:[]},dictionaries,status:'idle',requestedSearch:false};},async search(s,f,cb){
  searches++;loadedOffer={...offer,adults:s.adults,ages:s.ages,search:s};
  const result={search:s,filters:f,hotels:[{...hotel,offers:[loadedOffer]}],status:'complete',searchGeneration:searches};cb(result);return result;
 }};
 if(!win.AnyTourDesignV2Tour)win.eval(fs.readFileSync(path.join(source,'tour.js'),'utf8'));win.eval(fs.readFileSync(path.join(source,'frontend.js'),'utf8'));const owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api});
 const el=s=>{const n=product.querySelector(s);assert(n,s);return n;};
 const tap=s=>{const n=el(s);assert(!n.disabled,s+' must be available');n.click();};
 try{
  await owner.ready;let checked=0;
  for(let adults=1;adults<=6;adults++)for(let children=0;children<=3;children++){
   const summary=adultSummary[adults]+childSummary[children],price=adultPrice[adults]+childPrice[children],ages=[0,7,17].slice(0,children);
   tap('[data-search-travelers-toggle]');
   while(Number(el('[data-search-adult-value]').textContent)>adults)tap('[data-search-adult="-1"]');
   while(Number(el('[data-search-adult-value]').textContent)<adults)tap('[data-search-adult="1"]');
   while(product.querySelector('[data-native-remove-child]'))tap('[data-native-remove-child="0"]');
   for(let index=0;index<children;index++){
    tap('[data-search-add-child]');const age=el('[data-native-age="'+index+'"]');age.value=String(ages[index]);age.dispatchEvent(new win.Event('change',{bubbles:true}));
   }
   assert.equal(el('[data-search-travelers-value]').textContent,summary,'form summary: '+summary);
   assert.equal(el('[data-search-family-status]').textContent,summary,'family editor summary: '+summary);
   tap('[data-search-editor-done="travelers"]');
   assert.equal(el('[data-search-travelers-value]').textContent,summary,'applied family summary');
   tap('[data-search-date-toggle]');
   assert(el('[data-calendar-price-context]').textContent.endsWith(' · '+summary),'calendar context uses a free summary');
   tap('[data-search-calendar-close]');
   assert.equal(searches,checked,'editing family and calendar copy never starts a search');
   el('[data-search-form]').dispatchEvent(new win.Event('submit',{bubbles:true,cancelable:true}));await new Promise(resolve=>win.setTimeout(resolve,0));checked++;
   assert.equal(searches,checked,'one existing explicit submit per composition');
   assert.equal(owner.getState().search.adults,adults);assert.deepEqual(Array.from(owner.getState().search.ages),ages);
   assert.strictEqual(owner.getState().hotels[0].offers[0],loadedOffer,'copy retains exact loaded offer identity');
   assert.equal(loadedOffer.total,offer.total);assert.equal(loadedOffer.adults,adults);assert.deepEqual(Array.from(loadedOffer.ages),ages);
   assert.equal(el('[data-party]').textContent,summary,'desktop results summary');
   assert.equal(el('[data-party-copy]').textContent,summary,'mobile results summary');
   assert.equal(el('.st-hotel-card .st-price-caption').textContent,'за '+price,'result price caption uses accusative');
   tap('[data-hotel-open="'+hotel.id+'"]');
   assert.equal(el('[data-hotel-price-caption]').textContent,'Цена из поиска за '+price+' · 7 ночей','hotel summary price caption');
   assert.equal(el('[data-offer-context]').textContent,'Все цены за '+price,'hotel offer price context');
   tap('[data-action="search"]');
  }
  assert.equal(checked,24,'all adult 1–6 × child 0–3 compositions reach form/results/hotel owners');
 }finally{owner.destroy();win.close();}
}
async function localCalendarDayChecks(){
 const doc=new JSDOM('<!doctype html><body>'+builder.extract().markup+'</body>',{url:w.location.origin+w.location.pathname,runScripts:'outside-only',virtualConsole:new VirtualConsole()});
 const win=doc.window,NativeDate=win.Date,fixed=NativeDate.parse('2026-10-15T22:30:00Z');
 class LocalRolloverDate extends NativeDate{
  constructor(...args){super(...(args.length?args:[fixed]));}
  getFullYear(){return this.getTime()===fixed?2026:super.getFullYear();}
  getMonth(){return this.getTime()===fixed?9:super.getMonth();}
  getDate(){return this.getTime()===fixed?16:super.getDate();}
  static now(){return fixed;}
 }
 win.Date=LocalRolloverDate;
 const product=win.document.querySelector('[data-product]'),expired={...trip,from:'2026-10-15',to:'2026-10-21'};let searches=0;
 const api={async init(){return{search:expired,filters:{...filters},dictionaries,status:'idle',requestedSearch:false};},async search(){searches++;throw new Error('A locally expired period must not start suppliers');}};
 if(!win.AnyTourDesignV2Tour)win.eval(fs.readFileSync(path.join(source,'tour.js'),'utf8'));win.eval(fs.readFileSync(path.join(source,'frontend.js'),'utf8'));const owner=win.AnyTourDesignV2Native.mount({root:product,bridge:api});
 try{
  await owner.ready;product.querySelector('[data-search-form]').dispatchEvent(new win.Event('submit',{bubbles:true,cancelable:true}));
  const yesterday=product.querySelector('[data-native-date="2026-10-15"]');assert(yesterday?.disabled,'The calendar disables yesterday after the local midnight/UTC rollover');
  assert.match(product.querySelector('[data-calendar-error]').textContent,/истёк/);assert.equal(searches,0,'Local calendar validation blocks a past-date supplier start');
  win.eval(fs.readFileSync(path.join(source,'bridge.js'),'utf8'));
  const data={catalog:{departures:[{name:'Москва'}],countries,mealPlans:dictionaries.meals},text:row=>row.name,async init(){return{origin:'Москва'};},params(){}};
  const initial=await win.AnyTourDesignV2Bridge.create({data,location:{search:''}}).init();
  assert.equal(initial.search.from,'2026-10-30','Default departure starts 14 days after the browser local date');
  assert.equal(initial.search.to,'2026-11-05','Default period retains the exact seven-day range');
 }finally{owner.destroy();win.close();}
}
(async()=>{
 await selectedJourneyChecks();
 await returnedPriceStateChecks();
 await responsiveTourFocusChecks();
 await mounted.ready;await localCalendarDayChecks();await unstartedNavigationChecks();await destinationFocusChecks();await popupSubmitChecks();assert.equal(starts,0,'passive hydration does not search suppliers');
 click('[data-search-nights-toggle]');assert.equal(node('[data-search-nights-toggle]').getAttribute('aria-expanded'),'true');
 click('[data-native-night="10"]');assert.equal(node('[name="minNights"]').value,'10');assert.equal(node('[name="maxNights"]').value,'10','first tap replaces the previous exact duration');
 click('[data-native-night="14"]');assert.equal(node('[name="minNights"]').value,'10');assert.equal(node('[name="maxNights"]').value,'14','second tap creates range from first tap');
 click('[data-native-night="7"]');assert.equal(node('[name="minNights"]').value,'7');assert.equal(node('[name="maxNights"]').value,'7','third tap starts a new exact duration');
 click('[data-native-night="28"]');assert.equal(node('[name="maxNights"]').value,'7','invalid range retains the chosen duration');assert(!node('[data-search-night-error]').hidden);
 click('[data-search-night="14"]');assert.equal(node('[name="minNights"]').value,'14');assert.equal(node('[name="maxNights"]').value,'14');
 click('[data-search-editor-cancel="nights"]');assert.equal(node('[data-search-night-value]').textContent,'7 ночей');assert.equal(node('[data-search-nights-toggle]').getAttribute('aria-expanded'),'false');
 click('[data-search-date-toggle]');assert.equal(root.querySelectorAll('.st-calendar-month').length,2,'native two-month calendar is retained');
 const firstDays=node('.st-calendar-days');assert.equal(firstDays.children[3].dataset.nativeDate,'2026-10-01','Thursday aligns to fourth column after Monday/Tuesday/Wednesday gaps');
 click('[data-calendar-month="1"]');assert.equal(node('.st-calendar-days').firstElementChild.dataset.nativeDate,undefined);assert.equal(node('.st-calendar-days').children[6].dataset.nativeDate,'2026-11-01','Sunday aligns to seventh column');
 click('[data-native-date="2026-11-02"]');click('[data-native-date="2026-11-10"]');click('[data-calendar-done]');assert(node('[data-search-date-value]').textContent.includes('2–10 ноя'));assert.equal(mounted.getState().search.from,trip.from,'date edits remain draft until explicit submit');
 click('[data-search-date-toggle]');click('[data-native-date="2026-11-12"]');click('[data-search-calendar-close]');assert(node('[data-search-date-value]').textContent.includes('2–10 ноя'),'date cancel restores applied draft');
 click('[data-search-date-toggle]');click('[data-calendar-month="-1"]');click('[data-native-date="2026-10-25"]');click('[data-native-date="2026-10-31"]');click('[data-calendar-done]');assert.equal(node('[data-search-date-value]').textContent,'25–31 окт','same-month date range keeps the donor compact presentation');assert.equal(starts,0,'calendar navigation/selections never starts suppliers');
 click('[data-conditions-open="budget"]');input('[data-search-budget="min"]','150 000');input('[data-search-budget="max"]','100000');assert(node('[data-conditions-apply="budget"]').disabled);assert(!node('[data-search-budget-error]').hidden);input('[data-search-budget="max"]','abc');assert.equal(node('[data-search-budget="max"]').getAttribute('aria-invalid'),'true');input('[data-search-budget="min"]','100000');input('[data-search-budget="max"]','150000');click('[data-conditions-apply="budget"]');assert(node('[data-search-budget-editor]').hidden);
 click('[data-search-travelers-toggle]');click('[data-search-add-child]');click('[data-search-add-child]');assert(node('[data-search-editor-done="travelers"]').disabled);input('[data-native-age="0"]','0','change');input('[data-native-age="1"]','17','change');click('[data-search-editor-done="travelers"]');assert(node('[data-search-travelers-value]').textContent.includes('2 ребёнка'));
 click('[data-search-destination-toggle]');await tick();assert.equal(regions,1);click('[data-native-destination="resort"][data-native-destination-id="41"]');click('[data-destination-apply]');assert.equal(node('[data-destination-summary]').textContent,'Кемер');
 click('[data-search-destination-toggle]');input('[data-destination-query]','Verified');await tick();assert.equal(lookups,1);click('[data-native-destination="hotel"][data-native-destination-id="7"]');assert(!node('[data-destination-confirm]').hidden,'hotel replacement requires visible confirmation');click('[data-destination-keep]');assert.equal(node('[data-destination-footer-summary]').textContent,'Турция · Кемер');click('[data-native-destination="hotel"][data-native-destination-id="7"]');click('[data-destination-replace]');click('[data-destination-apply]');assert.equal(node('[data-destination-summary]').textContent,'Verified hotel');
 click('[data-search-destination-toggle]');click('[data-destination-clear]');await tick();click('[data-native-destination="country"][data-native-destination-id="8"]');click('[data-destination-replace]');await tick();click('[data-destination-cancel]');assert.equal(node('[data-destination-country]').textContent,'Турция');assert.equal(node('[data-destination-summary]').textContent,'Verified hotel','destination cancel restores own exact hotel constraint');assert.equal(starts,0);
 input('[name="origin"]','Калининград','change');input('[name="origin"]','Москва','change');originRequests[1].resolve(dictionaries);await tick();originRequests[0].resolve({...dictionaries,countries:[countries[1]]});await tick();assert.equal(node('[name="origin"]').value,'Москва');assert.equal(node('[data-destination-country]').textContent,'Турция','superseded origin responses cannot overwrite current country');
 submit();await tick();assert.equal(starts,1);assert.deepEqual(Array.from(lastIntent.ages),[0,17]);assert.equal(lastFilters.min,100000);assert.equal(lastFilters.max,150000);assert.deepEqual(Array.from(lastFilters.hotelIds),[7]);const url=new URL(w.location.href);assert.equal(url.searchParams.get('hotelIds'),'7');assert.equal(url.searchParams.get('min'),'100000');
 const legacyURL=new URL(w.location.href);legacyURL.searchParams.set('hotelId','7');w.history.replaceState(w.history.state,'',legacyURL.href);
 mounted.getState().filters.hotelIds=[7,9];input('.st-sort','price','change');
 const roundTrip=new URL(w.location.href);assert.equal(roundTrip.searchParams.get('hotelIds'),'7|9');assert(!roundTrip.searchParams.has('hotelId'),'canonical URL removes the legacy single-hotel constraint');
 w.eval(fs.readFileSync(path.join(source,'bridge.js'),'utf8'));
 const urlData={catalog:{departures:dictionaries.origins.map(name=>({name})),countries,mealPlans:dictionaries.meals},text:row=>row.name,async init(){return{origin:'Москва'};},params(){}};
 const restored=await w.AnyTourDesignV2Bridge.create({data:urlData,location:{search:roundTrip.search}}).init();
 assert.deepEqual(Array.from(restored.filters.hotelIds),[7,9],'serialized multiple hotels restore through the actual bridge');
 mounted.getState().filters.hotelIds=[7];input('.st-sort','price','change');
 click('[data-hotel-open="7"]');click('[data-offer="tourvisor:exact"]');assert.strictEqual(mounted.getState().selected,offer);await pop('back');assert.equal(mounted.getState().screen,'hotel');assert.strictEqual(mounted.getState().selected,offer);assert.equal(root.querySelectorAll('[data-hotel-offers] .st-offer--selected [data-offer="tourvisor:exact"]').length,1,'Back marks the actual chosen canonical offer');await pop('back');assert.equal(mounted.getState().screen,'results');assert.equal(mounted.getState().filters.min,100000);await pop('forward');assert.equal(mounted.getState().screen,'hotel');await pop('forward');assert.equal(mounted.getState().screen,'tour');assert.strictEqual(mounted.getState().selected,offer,'Back/Forward retains canonical offer identity');assert.equal(starts,1,'history and navigation do not resubmit suppliers');
 mounted.getState().selected=null;click('[data-hotel-about="7"]');click('[data-action="best-tour"]');await pop('back');assert.equal(mounted.getState().screen,'hotel');assert.strictEqual(mounted.getState().selected,offer,'Hotel summary CTA remembers the actual chosen offer too');assert.equal(root.querySelectorAll('[data-hotel-offers] .st-offer--selected [data-offer="tourvisor:exact"]').length,1);assert.equal(starts,1);
 click('[data-action="search"]');click('[data-search-nights-toggle]');click('[data-native-night="10"]');const retained=mounted.getState();mounted.destroy();
 bridge.getDictionarySnapshot=()=>({type:'pending',origin:'Калининград',generation:10,dictionaries});
 const container=w.document.createElement('div');container.innerHTML=builder.extract().markup;w.document.body.replaceChildren(container);const newRoot=container.querySelector('[data-product]');const remounted=w.AnyTourDesignV2Native.mount({root:newRoot,bridge,initialState:retained});await remounted.ready;
 assert(newRoot.querySelector('[data-search-form] [type="submit"]').disabled,'durable pending origin disables submit after remount');remounted.applyDictionarySnapshot({type:'complete',origin:'Калининград',generation:10,dictionaries});assert(!newRoot.querySelector('[data-search-form] [type="submit"]').disabled);assert.equal(newRoot.querySelector('[name="origin"]').value,'Калининград');remounted.applyDictionarySnapshot({type:'complete',origin:'Москва',generation:9,dictionaries});assert.equal(newRoot.querySelector('[name="origin"]').value,'Калининград','superseded durable dictionary receipt is ignored');remounted.applyDictionarySnapshot({type:'error',origin:'Калининград',generation:11,dictionaries,message:'Fixture dictionary unavailable'});assert(!newRoot.querySelector('[data-search-error]').hidden);
 assert(!newRoot.querySelector('[data-search-nights-editor]').hidden,'open input editor survives viewport remount');assert.equal(newRoot.querySelector('[name="minNights"]').value,'10');newRoot.querySelector('[data-native-night="14"]').click();assert.equal(newRoot.querySelector('[name="maxNights"]').value,'14','night range anchor survives remount');newRoot.querySelector('[data-search-editor-cancel="nights"]').click();assert.equal(newRoot.querySelector('[data-search-night-value]').textContent,'7 ночей','original editor cancel snapshot survives remount');assert.equal(starts,1);
 remounted.destroy();await groupMinimumChecks();await loadedBudgetChecks();await progressiveBudgetChecks();await familyCopyChecks();await formFocusChecks();w.close();console.log('PASS native inputs: group minima open hotel variants without preliminary tour; explicit exact-object expansion runs once across pending/remount/error/stale/cached return and only concrete selection reaches tour; dates/nights/party/canonical destination identity, compact date ranges, budget validation/Reset/Cancel/remount and progressive count/range updates retaining typed invalid draft/focus; 24 family compositions use correct summary/price cases; form focus survives night/range, child-age/add/remove and calendar paging; destination choice/confirmation/chip removal/async lookup focus and Escape cancellation; supplier starts only explicit submit');
})().catch(error=>{console.error(error);mounted.destroy();w.close();process.exitCode=1;});
