'use strict';
// Remote-CI rendered evidence. Fictional fixtures only: no supplier, DB, CRM or
// public page is contacted. The donor's original renderer is a reference only;
// its mock runtime is never added to the product build.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const crypto = require('node:crypto');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const builder = require('../scripts/build/search3_design_v2_native.cjs');

const sourceRoot = path.resolve(__dirname, '..');
const assetRoot = path.resolve(process.env.SEARCH3_NATIVE_ASSET_ROOT || '');
const evidence = path.resolve(process.env.SEARCH3_NATIVE_EVIDENCE || '');
const sourceSHA = process.env.SOURCE_SHA;
assert(process.env.SEARCH3_NATIVE_ASSET_ROOT && process.env.SEARCH3_NATIVE_EVIDENCE,
  'Explicit compiled asset root and evidence directory required');
assert(/^[0-9a-f]{40}$/.test(sourceSHA || ''), 'Exact source SHA required');
assert.equal(execFileSync('git', ['rev-parse', 'HEAD'], {cwd:sourceRoot,encoding:'utf8'}).trim(), sourceSHA);
assert(!assetRoot.startsWith(path.join(sourceRoot, 'v2') + path.sep), 'Use an isolated compiled payload');
const base = '/_preview/search3-design-v2-candidate';
const manifest = JSON.parse(fs.readFileSync(path.join(assetRoot, 'visual-search/native-v2/source-manifest.json')));
assert.equal(manifest.donorSource, '00fe6734c08acf234756bb59e5b61dbfecf41941');
assert.equal(manifest.legacyPresentationLoaded, false);
assert.equal(manifest.mockSimulatorLoaded, false);
const hash = value => crypto.createHash('sha256').update(value).digest('hex');
for (const [name, sha] of Object.entries(manifest.files)) {
  assert.equal(hash(fs.readFileSync(path.join(assetRoot,'visual-search/native-v2',name))),sha,'Compiled native manifest: '+name);
}
fs.mkdirSync(evidence,{recursive:true});

const fixture = {
  label:'DETERMINISTIC FICTIONAL BROWSER FIXTURE; not availability or a live price',
  verifiedSelection:{total:112000,flightChoiceId:0,forwardTimes:['09:20','13:20'],backwardTimes:['13:00','17:00']},
  trip:{origin:'Москва',country:'4',from:'2026-10-12',to:'2026-10-25',minNights:7,maxNights:7,adults:2,ages:[]},
  hotels:[
    {id:1,donorId:'laguna',name:'FIXTURE Laguna Beach Resort',stars:5,room:'Стандартный номер',meal:'Ультра всё включено',food:'uai',total:156400},
    {id:2,donorId:'marina',name:'FIXTURE Marina Bay Hotel',stars:4,room:'Стандартный номер',meal:'Завтраки и ужины',food:'hb',total:149900},
    {id:3,donorId:'garden',name:'FIXTURE Seaside Garden Hotel & Residence',stars:3,room:'Стандартный номер',meal:'Завтраки',food:'bb',total:98700},
    {id:4,donorId:'villas',name:'FIXTURE Laguna Collection Premium Resort & Private Villas',stars:5,room:'Вилла с двумя спальнями · свой бассейн',meal:'Ультра всё включено',food:'uai',total:1059000}
  ]
};
const fixtureNow = new Date('2026-10-12T08:00:00Z');
fs.writeFileSync(path.join(evidence,'fixture.json'),JSON.stringify(fixture,null,2)+'\n');

function fixtureBridge(fixture) {
  const trip=fixture.trip, calls={init:0,search:0,quote:0,expand:0,flight:[],preview:0,submit:0};
  const rawHotels=fixture.hotels.map(row=>({id:row.id,name:row.name,country:'4',location:'Турция · Кемер',region:'Кемер',stars:row.stars,photos:[],amenities:[],raw:{description:'FICTIONAL fixture hotel description'},offers:[{
    key:'fixture:offer:'+row.id,hotelId:row.id,day:trip.from,returnDay:'2026-10-19',nights:7,adults:2,ages:[],room:row.room,meal:row.meal,mealFacet:row.meal,operator:'FIXTURE TV',provider:'tourvisor',total:row.total,search:trip,raw:{fixture:true}
  },...(row.id===1?[{key:'fixture:later:1',hotelId:row.id,day:trip.to,returnDay:'2026-11-01',nights:7,adults:2,ages:[],room:row.room,meal:row.meal,mealFacet:row.meal,operator:'FIXTURE TV',provider:'tourvisor',total:166400,search:trip,raw:{fixture:true}}]:[])]}));
  const emptyFilters=()=>({hotelId:0,hotelIds:[],q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],amenities:[],min:0,max:null,rating:false,beach:false,family:false,spa:false});
  const dictionaries={origins:['Москва','Калининград'],countries:[{id:'4',name:'Турция'},{id:'1',name:'Египет'}],meals:fixture.hotels.map((row,i)=>({id:i+1,nameRu:row.meal,code:row.food,nativeIds:[]}))};
  let selection=null,latest={hotels:[],status:'idle'},generation=0;
  const listeners=new Set();
  const publish=value=>{latest=value;for(const listener of listeners)listener(value);return value;};
  const leg=(back=false)=>({company:{name:'FIXTURE AIR'},number:back?'FX 102':'FX 101',departure:{date:back?'2026-10-19':trip.from,time:back?'13:00':'09:20',port:{name:back?'FIXTURE Antalya':'FIXTURE Moscow'}},arrival:{date:back?'2026-10-19':trip.from,time:back?'17:00':'13:20',port:{name:back?'FIXTURE Moscow':'FIXTURE Antalya'}},baggage:20,carryOn:'5 кг'});
  const control=window.__nativeFixture={calls,hotels:rawHotels,failNextSearch:false,failNextQuote:false,getSelection:()=>selection,getSnapshot:()=>latest};
  control.installManyFlights=()=>{
    selection=null;control.manyFlightCount=40;
    const hotel=rawHotels[0];
    hotel.offers=[{...hotel.offers[0],key:'fixture:many-flight:1',raw:{fixture:true,manyFlightCase:true}},...hotel.offers.slice(1)];
    control.deliverHotels(rawHotels,{status:'complete',message:''});
  };
  control.deliverHotels=(hotels,values={})=>publish({...latest,...values,hotels,search:{...trip,ages:[]}});
  // Installed only after all existing captures and authority proofs. This
  // separate in-memory ANEX group never changes the paired donor corpus.
  control.installGroup=()=>{
    const group={...rawHotels[0].offers[0],key:'fixture:group:901',hotelId:901,provider:'anex',operator:'FIXTURE ANEX',room:'Номер уточняется',raw:{fixture:true,anexKind:'group_minimum'}};
    const offers=[0,1].map(index=>({...group,key:'fixture:concrete:901:'+index,room:index?'Семейный номер':'Точный стандартный номер',total:group.total+5000+index*9000,raw:{fixture:true,anexKind:'concrete'}}));
    const hotel={...rawHotels[0],id:901,name:'FIXTURE Explicit ANEX Group Hotel',offers:[group]};
    control.group={hotel,offer:group,offers,expectedReferenceRetained:false};rawHotels.push(hotel);
    control.deliverHotels(rawHotels,{status:'complete',message:''});
  };
  control.installAI=()=>{
    const seed=rawHotels[0].offers[0],sea={...seed,key:'fixture:ai:sea',hotelId:902,provider:'tourvisor',operator:'FIXTURE TV',room:'standard room sea view',meal:'Всё включено',mealFacet:'Всё включено',total:138989,raw:{fixture:true,aiJourney:true}};
    const land={...sea,key:'fixture:ai:land',room:'standard room land view',total:137083,raw:{fixture:true,aiJourney:true}};
    const hotel={...rawHotels[0],id:902,name:'FIXTURE GRAND BARHAN HOTEL (EX. AS BLUE COAST)',offers:[land,sea]};
    rawHotels.push(hotel);control.ai={hotel,offer:sea};control.deliverHotels(rawHotels,{status:'complete',message:''});
  };
  let initResolve,initReject,initialization;
  control.releaseInit=()=>{window.__nativeHoldInit=false;initResolve?.();};control.rejectInit=()=>initReject?.(new Error('FICTIONAL controlled dictionary failure'));
  const api={
    init(){
      calls.init++;
      // Match the canonical bridge's shared catalogue initialization. Responsive
      // mounts join this promise instead of creating another held request.
      if(!initialization)initialization=(async()=>{
        if(window.__nativeHoldInit)await new Promise((resolve,reject)=>{initResolve=resolve;initReject=reject;});
        return{search:{...trip,ages:[]},filters:emptyFilters(),dictionaries,hotels:[],status:'idle',requestedSearch:new URLSearchParams(location.search).get('searched')==='1'};
      })().catch(error=>{initialization=null;throw error;});
      return initialization;
    },
    subscribe(listener,{replay=true}={}){listeners.add(listener);if(replay)listener(latest);return()=>listeners.delete(listener);},
    getSnapshot(){return latest;},
    async changeOrigin(){return dictionaries;},
    async regions(){return[{id:'kemer',name:'Кемер'}];},
    async lookupHotels(){return{rows:[],hasMore:false};},
    async calendarPrices(){return{rows:[],items:[]};},
    async search(search,filters,callback){
      calls.search++;generation++;selection=null;
      const event={hotels:rawHotels,status:'partial',message:'FICTIONAL controlled progressive result',searchGeneration:generation};
      publish(event);callback?.(event);
      if(control.failNextSearch){control.failNextSearch=false;throw new Error('FICTIONAL controlled search failure');}
      const complete={hotels:rawHotels,status:'complete',searchGeneration:generation};publish(complete);callback?.(complete);return complete;
    },
    getSelection(){return selection;},
    async expandOfferGroup(key,{expectedOffer=null}={}){
      const group=control.group;
      if(!group||key!==group.offer.key||expectedOffer!==group.offer||!rawHotels.includes(group.hotel)||group.hotel.offers[0]!==group.offer)throw new Error('Fixture group must be the exact current canonical offer');
      calls.expand++;group.expectedReferenceRetained=true;
      await new Promise(resolve=>{control.releaseGroupExpansion=resolve;});
      group.hotel.offers.push(...group.offers);control.deliverHotels(rawHotels);
      return group.offers;
    },
    async quoteOffer(key,{expectedOffer=null}={}){
      calls.quote++;
      const offer=rawHotels.flatMap(row=>row.offers).find(row=>row.key===key);
      if(!offer||expectedOffer&&offer!==expectedOffer)throw new Error('Unknown or replaced fixture offer');
      if(control.failNextQuote){control.failNextQuote=false;throw new Error('FICTIONAL controlled quote failure');}
      if(offer.provider==='anex'){
        if(offer.raw?.anexKind!=='concrete')throw new Error('Only a concrete fixture may be quoted');
        const expiresAt=Math.floor(Date.now()/1000)+600;
        selection={...offer,offer,total:offer.total,variants:[],flightChoiceId:null,pricePending:false,priceAccepted:true,expiresAt,quoteResult:{state:'quote_verified',expiresAt,choice:{choiceRef:'fixture:choice:901',legs:[{label:'FICTIONAL outbound'},{label:'FICTIONAL inbound'}]}}};
        return selection;
      }
      const variants=[{price:112000,forward:[leg()],backward:[leg(true)]},{price:123000,forward:[{...leg(),departure:{...leg().departure,time:'10:20'}}],backward:[leg(true)]}];
      for(let index=variants.length;index<(control.manyFlightCount||2);index++)variants.push({price:112000+index*1000,forward:[{...leg(),number:'FX '+(200+index)}],backward:[{...leg(true),number:'FX '+(300+index)}]});
      if(offer.raw?.aiJourney){variants[0]={...variants[0],price:163056};variants.splice(1);}
      selection={...offer,offer,tour:{id:'fixture-tour:'+offer.hotelId,price:offer.raw?.aiJourney?138989:110000,roomType:offer.room,meal:{name:offer.raw?.aiJourney?'AI':offer.meal}},total:offer.raw?.aiJourney?138989:110000,variants,flightChoiceId:null,pricePending:true,priceAccepted:false,expiresAt:Math.floor(Date.now()/1000)+600};
      return selection;
    },
    selectFlight(index){if(!selection||!selection.variants[index])throw new Error('Invalid fixture variant');calls.flight.push(index);selection={...selection,total:selection.variants[index].price,flightChoiceId:index,pricePending:false,priceAccepted:false};return selection;},
    deferFlight(){selection={...selection,total:selection.tour.price,flightChoiceId:null,pricePending:false,priceAccepted:false};return selection;},
    acceptPrice(){if(!selection||selection.pricePending)throw new Error('Fixture price pending');selection={...selection,priceAccepted:true};return selection;},
    previewContact(fields){
      if(!selection||!selection.priceAccepted||selection.pricePending)throw new Error('Fixture selection has no authority');
      if(!fields.get('consent'))throw new Error('Fixture consent required');
      calls.preview++;const value={fixture:true,price:selection.total,flightChoiceId:selection.flightChoiceId,tourId:selection.tour?.id||selection.key,name:fields.get('name'),phone:fields.get('phone')};control.payload=value;return value;
    }
  };
  window.AnyTourDesignV2Bridge={create:()=>api};
}

// This separate journey loads the compiled native bridge, not fixtureBridge.
// Only its canonical data dependency is replaced; all transport is forbidden.
function terminalDataFixture(fixture) {
  const calls={search:0,other:0},control=window.__nativeTerminalFixture={calls};
  let emit,hotels=[];
  const forbidden=()=>{calls.other++;throw new Error('Unexpected transport/quote/lead in terminal metadata fixture');};
  window.AnyTourPrototypeData={catalog:{departures:[{name:'Москва'}],countries:[{id:4,name:'Турция'}],mealPlans:[]},
    text:row=>row.name,params(){return{};},async init(){return{origin:'Москва'};},stop(){},
    async search(search,callback){
      calls.search++;emit=callback;
      hotels=fixture.hotels.map(row=>({id:row.id,donorId:row.donorId,name:row.name,country:'4',region:'Кемер',
        stars:row.stars,photos:[],amenities:[],raw:{description:'FICTIONAL before local enrichment'},
        offers:[{key:'fixture:terminal:'+row.id,hotelId:row.id,day:search.from,returnDay:'2026-10-19',nights:7,
          adults:search.adults,ages:[...search.ages],room:row.room,meal:row.meal,mealFacet:row.meal,
          operator:'FIXTURE ANEX',provider:'anex',total:row.total,search,raw:{fixture:true}}]}));
      callback({type:'results',hotels});callback({type:'complete'});
    },quote:forbidden,flights:forbidden,leadSession:forbidden,regions:forbidden,lookupHotels:forbidden,calendarPrices:forbidden};
  control.terminal=(kind)=>emit(kind==='complete'?{type:'complete'}:kind==='partial'
    ?{type:'complete',partial:true,message:'API HTTP 403',sources:{tourvisor:{status:'error',hotels:0,offers:0},anex:{status:'complete',hotels:4,offers:4},andromeda:{status:'error',hotels:0,offers:0},database:{status:'skipped',hotels:0,offers:0}}}
    :{type:'error',message:'Не удалось завершить поиск. Полученные предложения сохранены.'});
  control.refresh=()=>{
    const before=control.bridge.getSnapshot(),references=before.hotels.map(hotel=>hotel.offers[0]);
    emit({type:'results',hotels:hotels.map(hotel=>({...hotel,raw:{description:'FICTIONAL after local enrichment'}}))});
    emit({type:'provider',status:'complete'});emit({type:'progress',progress:100});
    const after=control.bridge.getSnapshot();
    return{before:before.status,after:after.status,messageBefore:before.message,messageAfter:after.message,
      generationRetained:before.searchGeneration===after.searchGeneration,
      exactOffersRetained:after.hotels.every((hotel,index)=>hotel.offers[0]===references[index]),
      ids:after.hotels.map(hotel=>hotel.id),totals:after.hotels.map(hotel=>hotel.offers[0].total),searches:calls.search,other:calls.other};
  };
}
function recordTerminalBridge() {
  const create=window.AnyTourDesignV2Bridge.create;
  window.AnyTourDesignV2Bridge=Object.freeze({create(options){
    const bridge=create(options),control=window.__nativeTerminalFixture;
    control.bridge=bridge;control.getSnapshot=()=>bridge.getSnapshot();window.__nativeFixture=control;
    return bridge;
  }});
}

function donorReferenceHTML() {
  const donor=builder.reference()['anytour-fragment.html'];
  let runtime=donor.match(/<script[^>]*>([\s\S]*?)<\/script>/)[1];
  const replaceOnce=(old,next)=>{assert.equal(runtime.split(old).length,2,'One exact donor adapter anchor');runtime=runtime.replace(old,next);};
  // Test-only adapters replace mock data and its fabricated date-price curve;
  // original CSS, DOM, screen renderer and card/offer renderer remain intact.
  const start=runtime.indexOf('function b(){'),end=runtime.indexOf('function v(){',start);
  assert(start>0&&end>start,'Original donor calendar owners');
  runtime=runtime.slice(0,start)+`function b(){const items=[];for(let day=r.query.from;day<=r.query.to&&items.length<7;day=d(day,1)){const prices=g.flatMap(h=>x(h).filter(o=>o.date===day).map(o=>o.price));items.push({date:day,factor:1,price:prices.length?Math.min(...prices):null});}return {total:items.length,offset:0,maxPage:0,items};}`+runtime.slice(end);
  replaceOnce('p(),A(),B("search")',`p(),A(),B("search");(globalThis.__designV2DonorControllers||(globalThis.__designV2DonorControllers=[])).push({root:t,setFixture(fixture,screen){r.query={...fixture.trip,country:"Турция"};r.priceDate=fixture.trip.from;r.dateFactor=1;r.filters=new Set;r.min=0;r.max=Infinity;r.onlyFavorites=false;r.favorites=new Set;g=fixture.hotels.map(hotel=>({...hotel,id:hotel.donorId,location:"Турция · Кемер",image:"missing",description:"FICTIONAL fixture hotel description",beach:false,private:false,sand:false,offers:[{id:"fixture:offer:"+hotel.id,hotelId:hotel.donorId,date:fixture.trip.from,nights:7,room:hotel.room,meal:hotel.meal,food:hotel.food,operator:"FIXTURE TV",price:hotel.total}]}));r.hotelId=g[0].id;r.selected={...g[0].offers[0],price:fixture.verifiedSelection.total};r.screen="results";A();B(screen);const flights=a('[data-screen="tour"] .st-flight-row');for(let index=0;index<flights.length;index++){const times=index?fixture.verifiedSelection.backwardTimes:fixture.verifiedSelection.forwardTimes;[...flights[index].querySelectorAll('b')].forEach((node,i)=>node.textContent=times[i]);}a('[data-flight-origin],[data-flight-return-destination]').forEach(node=>node.textContent='FIXTURE Moscow');a('[data-flight-destination],[data-flight-return-origin]').forEach(node=>node.textContent='FIXTURE Antalya');}})`);
  const originalMarkup=donor.replace(/^<style>[\s\S]*?<\/style>/,'').replace(/<script[^>]*>[\s\S]*?<\/script>/g,'');
  const parts=builder.extract();
  // The donor gets only the outer viewport adapter, never native UI repairs.
  const viewport=fs.readFileSync(path.join(sourceRoot,'src/search3-design-v2/viewport.css'),'utf8');
  assert.equal(viewport.split('/* Keep full totals').length,2,'One outer viewport adapter boundary');
  const donorHostCSS=viewport.split('/* Keep full totals')[0];
  return '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'+parts.css+'</style><style>'+parts.desktopCSS+'</style><style>'+donorHostCSS+'</style><script src="'+base+'/visual-search/native-v2/lucide.min.js"></script></head><body><main id="design-v2-native-root">'+originalMarkup+'</main><script>'+runtime+'</script></body></html>';
}

const referenceHTML=donorReferenceHTML();
const mime={'.html':'text/html;charset=utf-8','.js':'application/javascript;charset=utf-8','.css':'text/css;charset=utf-8','.json':'application/json','.png':'image/png','.woff2':'font/woff2','.woff':'font/woff','.svg':'image/svg+xml'};
const server=http.createServer((req,res)=>{
  const pathname=new URL(req.url,'http://127.0.0.1').pathname;
  if(pathname===base+'/__donor-reference/'){res.writeHead(200,{'Content-Type':'text/html;charset=utf-8'});res.end(referenceHTML);return;}
  if(req.method!=='GET'||!pathname.startsWith(base+'/')){res.writeHead(403);res.end('fixture boundary');return;}
  let rel=decodeURIComponent(pathname.slice(base.length+1));
  if(rel==='visual-search/'||rel==='visual-search/index.php')rel='visual-search/index.html';
  const target=path.resolve(assetRoot,rel),ext=path.extname(target);
  if(!target.startsWith(assetRoot+path.sep)||!mime[ext]||!fs.existsSync(target)||!fs.statSync(target).isFile()){res.writeHead(404);res.end('fixture static asset only');return;}
  res.writeHead(200,{'Content-Type':mime[ext]});fs.createReadStream(target).pipe(res);
});

const report={schema:1,sourceSHA,donorSource:manifest.donorSource,fixture:fixture.label,
  requiredWidths:[360,390,430,768,1280],requiredTextPercents:[100],expectedPairedScreens:['search','results','hotel','tour','lead'],
  compiledNativeManifest:manifest,scope:'controlled compiled UI, not live supplier/physical/publication acceptance',
  donorAdapter:'exact original renderer and CSS; test-only controlled hotels, loaded-date-price and selected-price/flight-fact adapters',
  textMethod:'ordinary computed text size; layout dimensions and browser zoom stay unchanged',
  knownSemanticDifferences:['Native package inclusions remain unknown until verified; donor demo inclusion copy is reference only.','Native quote/flight/contact authority comes from controlled bridge fixtures; donor demo authority is not transferred.'],
  cases:[],failures:[]};

// Bound fixture-state polling independently of animation frames, so a stalled
// background-tab RAF cannot consume the CI job without exposing the state.
const waitForFixture=(page,predicate,arg)=>page.waitForFunction(predicate,arg,{timeout:6000,polling:50});

async function setTextSize(page,percent) {
  await page.evaluate(percent=>{
    document.getElementById('native-text-scale-evidence')?.remove();
    const key=node=>{const parts=[];for(let item=node;item&&item.id!=='b4';item=item.parentElement){let part=item.localName+[...item.classList].sort().map(name=>'.'+CSS.escape(name)).join('');if(item.dataset.screen)part+='[data-screen='+JSON.stringify(item.dataset.screen)+']';parts.unshift(part);}return'#b4 > '+parts.join(' > ');};
    window.__nativeTextScaleKey=key;window.__nativeTextScaleBaseline=new Map();
    if(percent===200){
      // Persistent selector rules also resize DOM recreated by the live renderer.
      // Per-node inline styles disappear when that renderer replaces a row.
      for(const node of document.querySelectorAll('#b4 *')){const size=parseFloat(getComputedStyle(node).fontSize);if(Number.isFinite(size))window.__nativeTextScaleBaseline.set(key(node),size);}
      const sheet=document.createElement('style');sheet.id='native-text-scale-evidence';
      sheet.textContent=[...window.__nativeTextScaleBaseline].map(([selector,size])=>selector+'{font-size:'+(size*2)+'px!important}').join('\n');document.head.append(sheet);
    }
  },percent);
}

async function capture(page,side,width,percent,screen,row,fullPage=true) {
  console.log('NATIVE_FRAME',side,width,percent,screen);
  if(side==='native')await waitForFixture(page,pending=>pending?window.__nativeFixture?.getSnapshot?.().status==='loading':window.__nativeFixture?.getSnapshot?.().status!=='loading',screen==='results-loading');
  await page.evaluate(()=>document.fonts.ready);
  await page.evaluate(()=>new Promise((resolve,reject)=>{
    const deadline=setTimeout(()=>reject(new Error('Two paint frames did not complete within 6 seconds')),6000);
    requestAnimationFrame(()=>requestAnimationFrame(()=>{clearTimeout(deadline);resolve();}));
  }));
  await setTextSize(page,percent);
  await page.evaluate(()=>window.scrollTo(0,0));
  const file=side+'-'+screen+'-'+width+'-text'+percent+'.png';
  await page.screenshot({path:path.join(evidence,file),fullPage,animations:'disabled'});
  const measurements=await page.evaluate(percent=>{
    const root=[...document.querySelectorAll('[data-product]')].find(node=>!node.closest('[hidden]'))||document.querySelector('[data-native-startup]:not([hidden])');
    const stage=root?.closest('.st-stage');
    const box=selector=>{const element=root?.querySelector(selector);if(!element||element.closest('[hidden]'))return null;const r=element.getBoundingClientRect(),style=getComputedStyle(element);return{x:r.x,y:r.y,width:r.width,height:r.height,fontSize:style.fontSize,visible:r.width>0&&r.height>0};};
    const doc=document.documentElement;
    const prices=[...root.querySelectorAll('.st-price,[data-tour-price],.st-offer-price')].filter(node=>!node.closest('[hidden]')&&node.getBoundingClientRect().width>0).map(node=>{
      let fragmented=false;const walker=document.createTreeWalker(node,NodeFilter.SHOW_TEXT);let text;
      while((text=walker.nextNode()))for(const match of text.textContent.matchAll(/\d+/g)){const range=document.createRange();range.setStart(text,match.index);range.setEnd(text,match.index+match[0].length);if([...range.getClientRects()].filter(rect=>rect.width>0&&rect.height>0).length>1)fragmented=true;}
      const fontSize=parseFloat(getComputedStyle(node).fontSize),baseline=window.__nativeTextScaleBaseline?.get(window.__nativeTextScaleKey(node));
      return{text:node.textContent,width:node.getBoundingClientRect().width,clientWidth:node.clientWidth,scrollWidth:node.scrollWidth,overflow:node.scrollWidth>node.clientWidth+1,fragmented,fontSize,baselineFontSize:percent===200?baseline:fontSize,textScaleVerified:percent!==200||Number.isFinite(baseline)&&Math.abs(fontSize-baseline*2)<0.5};
    });
    const buttons=[...root.querySelectorAll('button.q8c2,[data-destination-keep],[data-destination-cancel],[data-search-night]')].filter(node=>!node.closest('[hidden]')&&node.getBoundingClientRect().width>0).map(node=>{
      const box=node.getBoundingClientRect(),walker=document.createTreeWalker(node,NodeFilter.SHOW_TEXT);let text,clipped=false;
      while((text=walker.nextNode()))if(text.textContent.trim()&&!text.parentElement.closest('svg')){const range=document.createRange();range.selectNodeContents(text);for(const rect of range.getClientRects())if(rect.width>0&&rect.height>0&&(rect.left<box.left-1||rect.right>box.right+1||rect.top<box.top-1||rect.bottom>box.bottom+1))clipped=true;}
      return{text:node.textContent,clipped};
    });
    const budgetInputs=[...root.querySelectorAll('.st-budget input[type="number"]')].filter(node=>!node.closest('[hidden]')&&node.getBoundingClientRect().width>0).map(node=>{
      const style=getComputedStyle(node),canvas=document.createElement('canvas'),context=canvas.getContext('2d');context.font=style.fontWeight+' '+style.fontSize+' '+style.fontFamily;
      if(!context.font.includes(style.fontSize))throw new Error('Budget text measurement lost its actual font size');
      const text=node.value||node.placeholder,available=node.clientWidth-parseFloat(style.paddingLeft)-parseFloat(style.paddingRight),textWidth=context.measureText(text).width;
      return{text,available,textWidth,font:context.font,clipped:textWidth>available+1,appearance:style.appearance};
    });
    const budget=root.querySelector('[data-filter-sidebar] .st-budget');
    const budgetColumns=budget&&!budget.closest('[hidden]')?getComputedStyle(budget).gridTemplateColumns.split(' ').length:null;
    // Range rectangles include the font's em box, which is taller than the
    // painted Aeroport glyphs. Compare actual ink height with line spacing.
    window.__nativeTextLinesOverlap=node=>{
      const context=document.createElement('canvas').getContext('2d');
      const walker=document.createTreeWalker(node,NodeFilter.SHOW_TEXT);let text;
      while((text=walker.nextNode()))if(text.textContent.trim()&&!text.parentElement.closest('svg')){
        const style=getComputedStyle(text.parentElement);
        context.font=style.fontStyle+' '+style.fontWeight+' '+style.fontSize+' '+style.fontFamily;
        const metrics=context.measureText(text.textContent),inkHeight=metrics.actualBoundingBoxAscent+metrics.actualBoundingBoxDescent;
        if(!Number.isFinite(inkHeight))throw new Error('Painted text metrics unavailable');
        const range=document.createRange();range.selectNodeContents(text);
        const lines=[...range.getClientRects()].filter(rect=>rect.width>0&&rect.height>0).sort((a,b)=>a.top-b.top);
        if(lines.some((rect,index)=>lines.slice(index+1).some(next=>next.top>rect.top+1&&next.top-rect.top<inkHeight-1)))return true;
      }
      return false;
    };
    const visible=node=>{
      if(node.closest('[hidden]'))return false;
      // Chromium can retain layout rectangles for content skipped by a closed
      // details element. Its own summary remains painted and must be checked.
      for(let details=node.closest('details:not([open])');details;details=details.parentElement?.closest('details:not([open])')){
        if(!details.querySelector(':scope > summary')?.contains(node))return false;
      }
      const bounds=node.getBoundingClientRect();return bounds.width>0&&bounds.height>0;
    };
    const resultKinds=[['.st-name-button','result-title'],['.st-location','result-location'],
      ['.st-conditions-date','result-date'],['.st-conditions-room','result-room'],
      ['.st-conditions-meal','result-meal'],['.st-conditions-operator','result-operator'],
      ['.st-price-caption','result-party']];
    const readableText=[...root.querySelectorAll('.q8c4 > span,.st-destination-caption,.st-compact-caption,.st-search-part span,.st-search-part b,.st-condition-category .st-condition-label,.st-condition-category .q8c8,.st-flight-row b,.st-header-nav span,.st-footer button,.st-filter-heading h2,.st-filter-heading button,.st-name-button,.st-location,.st-conditions-date,.st-conditions-room,.st-conditions-meal,.st-conditions-operator,.st-price-caption,.st-offer-name,.st-offer-meal,.st-offer-meta,.st-results-note > span,.st-cost-list b,.st-cost-list > div > span,.st-native-flight-time b,.st-native-flight-route,.st-native-flight-alternatives > summary,.st-native-startup-card h1,.st-native-startup-card p,.st-pc-day,.st-pc-full,.st-pc-short,.st-pc-none,.st-pc-foot > span,[data-result-count],[data-empty-title],[data-active-chips] .st-chip span,[data-empty-text],[data-search-night],[data-search-travelers-value],[data-search-child-hint],[data-search-family-status],[data-destination-cancel],[data-destination-confirm-title],[data-destination-confirm-copy],[data-destination-keep],[data-destination-replace]')].filter(visible).map(node=>{
      const box=node.getBoundingClientRect(),walker=document.createTreeWalker(node,NodeFilter.SHOW_TEXT);let text,brokenWords=false,clipped=false;const overlappingLines=window.__nativeTextLinesOverlap(node);
      while((text=walker.nextNode()))if(text.textContent.trim()&&!text.parentElement.closest('svg')){
        const range=document.createRange();range.selectNodeContents(text);
        const lines=[...range.getClientRects()].filter(rect=>rect.width>0&&rect.height>0).sort((a,b)=>a.top-b.top);
        for(const rect of lines){
          if(rect.left<box.left-1||rect.right>box.right+1)clipped=true;
          // Glyph ink may extend vertically beyond its line box without clipping.
          // Vertical bounds matter only at an ancestor that actually clips paint.
          const modal=node.closest('dialog:modal');
          for(let ancestor=node;ancestor&&root.contains(ancestor);ancestor=ancestor.parentElement){
            const style=getComputedStyle(ancestor),bounds=ancestor.getBoundingClientRect();
            // Modal content can be reached by its own vertical scroll. The
            // browser's top layer is not clipped by the underlying form/stage.
            if(modal&&ancestor.matches('[data-native-popup-panel]')&&/^(auto|scroll)$/.test(style.overflowY)&&ancestor.scrollHeight>ancestor.clientHeight+1)break;
            if(/^(hidden|clip|scroll|auto)$/.test(style.overflowY)&&(rect.top<bounds.top-1||rect.bottom>bounds.bottom+1))clipped=true;
            if(ancestor===modal)break;
          }
        }
        for(const match of text.textContent.matchAll(/[\p{L}\p{N}:★]+/gu)){range.setStart(text,match.index);range.setEnd(text,match.index+match[0].length);if([...range.getClientRects()].filter(rect=>rect.width>0&&rect.height>0).length>1)brokenWords=true;}
      }
      const card=node.closest('.st-hotel-card');
      const kind=node.matches('[data-search-night]')?'night-preset':card?resultKinds.find(([selector])=>node.matches(selector))?.[1]||'other':node.matches('.st-results-note > span')?'result-notice':'other';
      const cardId=card?.hasAttribute('data-hotel-card')?Number(card.dataset.hotelCard):null;
      return{text:node.textContent,clipped,brokenWords,overlappingLines,kind,cardId};
    });
    const resultCardIds=[...root.querySelectorAll('.st-hotel-card')].filter(visible).map(node=>Number(node.dataset.hotelCard));
    const notices=[...root.querySelectorAll('.st-results-note')].filter(visible),resultList=root.querySelector('[data-card-list]'),resultMore=root.querySelector('[data-result-more]');
    const resultNotice={visibleCount:notices.length,
      afterBatch:notices.length===1&&Boolean((resultMore||resultList)?.compareDocumentPosition(notices[0])&Node.DOCUMENT_POSITION_FOLLOWING),
      cardNoticeCount:[...root.querySelectorAll('.st-hotel-card .st-card-bottom')].filter(visible).length,
      text:notices.map(node=>node.textContent.trim()).join('\n')};
    const header=root.querySelector('.st-header'),headerBounds=header.getBoundingClientRect(),headerItems=[...header.querySelectorAll('.st-logo,.st-header-nav span,.st-fav-top,.st-native-startup-title')].map(node=>node.getBoundingClientRect()).filter(rect=>rect.width>0&&rect.height>0);
    const headerFit={outside:headerItems.some(rect=>rect.left<headerBounds.left-1||rect.right>headerBounds.right+1||rect.top<headerBounds.top-1||rect.bottom>headerBounds.bottom+1),overlap:headerItems.some((a,i)=>headerItems.slice(i+1).some(b=>Math.min(a.right,b.right)>Math.max(a.left,b.left)+1&&Math.min(a.bottom,b.bottom)>Math.max(a.top,b.top)+1))};
    const count=root.querySelector('[data-result-count]'),toolbar=count?.closest('.st-toolbar');let countFit=null;
    if(count&&visible(count)&&toolbar){
      const bounds=toolbar.getBoundingClientRect(),range=document.createRange();range.selectNodeContents(count);
      const textRects=[...range.getClientRects()].filter(rect=>rect.width>0&&rect.height>0).map(rect=>({left:rect.left,right:rect.right,top:rect.top,bottom:rect.bottom}));
      const controls=[...toolbar.querySelectorAll('button,select')].filter(visible).map(node=>{const rect=node.getBoundingClientRect();return{text:node.innerText.trim(),left:rect.left,right:rect.right,top:rect.top,bottom:rect.bottom};});
      countFit={text:count.textContent,toolbar:{left:bounds.left,right:bounds.right},textRects,controls,
        outside:textRects.some(rect=>rect.left<bounds.left-1||rect.right>bounds.right+1),
        overlap:textRects.some(a=>controls.some(b=>Math.min(a.right,b.right)>Math.max(a.left,b.left)+1&&Math.min(a.bottom,b.bottom)>Math.max(a.top,b.top)+1))};
    }
    const sort=root.querySelector('.st-sort');let sortFit=null;
    if(sort&&!sort.closest('[hidden]')&&sort.getBoundingClientRect().width>0){const style=getComputedStyle(sort),context=document.createElement('canvas').getContext('2d');context.font=style.fontWeight+' '+style.fontSize+' '+style.fontFamily;const text=sort.selectedOptions[0]?.textContent||'',available=sort.clientWidth-parseFloat(style.paddingLeft)-parseFloat(style.paddingRight)-20;sortFit={text,available,textWidth:context.measureText(text).width};}
    const calendar=root.querySelector('.q8c9'),calendarPanel=calendar?.querySelector('.st-pc-panel');let calendarFit=null;
    if(calendar&&!calendar.closest('[hidden]')&&calendar.getBoundingClientRect().width>0&&calendarPanel&&!calendarPanel.hidden&&calendarPanel.getBoundingClientRect().width>0){
      const bounds=calendar.getBoundingClientRect(),foot=calendar.querySelector('.st-pc-foot'),pager=calendar.querySelector('.st-pc-pager');
      const buttons=[...calendar.querySelectorAll('.st-pc-page')].map(node=>{const rect=node.getBoundingClientRect();return{disabled:node.disabled,left:rect.left,right:rect.right,top:rect.top,bottom:rect.bottom,width:rect.width,height:rect.height};});
      const pagerBounds=pager?.getBoundingClientRect();
      calendarFit={left:bounds.left,right:bounds.right,footClientWidth:foot?.clientWidth||0,footScrollWidth:foot?.scrollWidth||0,
        pager:pagerBounds?{left:pagerBounds.left,right:pagerBounds.right,top:pagerBounds.top,bottom:pagerBounds.bottom}:null,buttons,
        clipped:Boolean(pagerBounds&&(pagerBounds.left<bounds.left-1||pagerBounds.right>bounds.right+1)||buttons.some(rect=>rect.left<bounds.left-1||rect.right>bounds.right+1)||foot&&foot.scrollWidth>foot.clientWidth+1)};
    }
    const decision=root.querySelector('[data-destination-confirm]');let destinationFit=null;
    if(decision&&visible(decision)){
      const bounds=decision.getBoundingClientRect(),style=getComputedStyle(decision),fontSize=parseFloat(style.fontSize),available=decision.clientWidth-parseFloat(style.paddingLeft)-parseFloat(style.paddingRight);
      const actions=[...decision.querySelectorAll('[data-destination-keep],[data-destination-replace]')].map(node=>{const rect=node.getBoundingClientRect();return{text:node.textContent.trim(),left:rect.left,right:rect.right,top:rect.top,bottom:rect.bottom,width:rect.width,height:rect.height,disabled:node.disabled};});
      destinationFit={available,fontSize,queryNarrow:available<=fontSize*18+1,actions,
        stacked:actions.length===2&&actions[1].top>=actions[0].bottom-1,
        outside:actions.some(rect=>rect.left<bounds.left-1||rect.right>bounds.right+1),
        overlap:actions.length===2&&Math.min(actions[0].right,actions[1].right)>Math.max(actions[0].left,actions[1].left)+1&&Math.min(actions[0].bottom,actions[1].bottom)>Math.max(actions[0].top,actions[1].top)+1};
    }
    const summaryFit=[...root.querySelectorAll('.st-tour-hotel,.st-quick-context')].filter(node=>!node.closest('[hidden]')&&node.getBoundingClientRect().width>0).map(node=>{
      const bounds=node.getBoundingClientRect(),image=node.firstElementChild?.getBoundingClientRect(),copy=node.lastElementChild?.getBoundingClientRect(),fontSize=parseFloat(getComputedStyle(node).fontSize);
      return{className:node.className,width:bounds.width,fontSize,imageWidth:image?.width||0,copyWidth:copy?.width||0,copyWidthRatio:copy?.width/bounds.width||0,
        stacked:Boolean(image&&copy&&copy.top>=image.bottom-1),queryNarrow:bounds.width<=fontSize*18+1};
    });
    const offerActionFit=[...root.querySelectorAll('.st-offer > .st-offer-select')].filter(node=>!node.closest('[hidden]')&&node.getBoundingClientRect().width>0).map(node=>{
      const row=node.closest('.st-offer'),container=row.closest('.st-offer-choice'),price=row.children[1].getBoundingClientRect(),bounds=row.getBoundingClientRect(),action=node.getBoundingClientRect(),containerBounds=container.getBoundingClientRect(),fontSize=parseFloat(getComputedStyle(container).fontSize),rowStyle=getComputedStyle(row),availableWidth=row.clientWidth-parseFloat(rowStyle.paddingLeft)-parseFloat(rowStyle.paddingRight),text=document.createRange();
      text.selectNodeContents(node);const textRects=[...text.getClientRects()].filter(rect=>rect.width>0&&rect.height>0);
      return{text:node.textContent.trim(),width:bounds.width,availableWidth,containerWidth:containerBounds.width,fontSize,actionWidth:action.width,actionWidthRatio:action.width/availableWidth,
        queryNarrow:containerBounds.width<=fontSize*22+1,actionOnOwnRow:action.top>=price.bottom-1,
        outside:action.left<bounds.left-1||action.right>bounds.right+1||textRects.some(rect=>rect.left<action.left-1||rect.right>action.right+1||rect.top<action.top-1||rect.bottom>action.bottom+1)};
    });
    const consentLabel=root.querySelector('[data-lead-form] > .st-check');let consentFit=null;
    if(consentLabel&&visible(consentLabel)){
      const bounds=consentLabel.getBoundingClientRect(),checkbox=consentLabel.querySelector('input[type="checkbox"]'),control=checkbox.getBoundingClientRect();
      checkbox.focus();const style=getComputedStyle(consentLabel);
      consentFit={label:{left:bounds.left,right:bounds.right,top:bounds.top,bottom:bounds.bottom,width:bounds.width,height:bounds.height},
        checkbox:{left:control.left,right:control.right,top:control.top,bottom:control.bottom,width:control.width,height:control.height},
        ownsCheckbox:checkbox.closest('label')===consentLabel,required:checkbox.required,
        contained:control.left>=bounds.left-1&&control.right<=bounds.right+1&&control.top>=bounds.top-1&&control.bottom<=bounds.bottom+1,
        focusOutline:style.outlineStyle!=='none'&&parseFloat(style.outlineWidth)>=2};
    }
    const popup=root.querySelector('[data-native-form-popup][open]');let popupFit=null;
    if(popup){const panel=popup.querySelector('[data-native-popup-panel]:not([hidden])'),bounds=popup.getBoundingClientRect(),find=popup.querySelector('[type="submit"]'),action=find.getBoundingClientRect();popupFit={editor:popup.dataset.editor,modal:popup.matches(':modal'),outside:bounds.left<-1||bounds.right>innerWidth+1||bounds.top<-1||bounds.bottom>innerHeight+1,panelOverflow:panel.scrollWidth>panel.clientWidth+1,findVisible:action.top>=0&&action.bottom<=innerHeight,findEnabled:!find.disabled,findHeight:action.height,submitCount:root.querySelector('[data-search-form]').querySelectorAll('[type="submit"]').length};}
    return{viewportWidth:innerWidth,viewportHeight:innerHeight,documentWidth:doc.scrollWidth,overflow:doc.scrollWidth>innerWidth+1,stageHidden:Boolean(stage?.hidden),screen:root?.querySelector('[data-screen]:not([hidden])')?.dataset.screen||(root?.matches('[data-native-startup]')?'startup':undefined),prices,buttons,budgetInputs,budgetColumns,readableText,headerFit,countFit,sortFit,
      popupFit,calendarFit,destinationFit,summaryFit,offerActionFit,consentFit,resultCardIds,resultNotice,boxes:{header:box('.st-header'),title:box('h1'),sidebar:box('[data-filter-sidebar]'),card:box('.st-hotel-card'),price:box('.st-price'),form:box('[data-search-form]'),tour:box('.st-tour-layout'),contact:box('[data-lead-form]')}};
  },percent);
  if(side==='native'&&screen.startsWith('terminal-')){
    measurements.terminalState=await page.evaluate(()=>{
      const control=window.__nativeTerminalFixture,snapshot=control.bridge.getSnapshot();
      const root=[...document.querySelectorAll('[data-product]')].find(node=>!node.closest('[hidden]'));
      const alert=root.querySelector('[data-source-alert]');
      return{status:snapshot.status,message:snapshot.message,searchGeneration:snapshot.searchGeneration,
        searchStarts:control.calls.search,otherCalls:control.calls.other,
        alertVisible:!alert.hidden,alertText:alert.textContent};
    });
    measurements.terminalState.ownerSHA256=manifest.files['bridge.js'];
  }
  row.frames.push({side,screen,textPercent:percent,file,sha256:hash(fs.readFileSync(path.join(evidence,file))),...measurements});
  const logicalScreen=screen.replace(/^short-desktop-/,'');
  assert.equal(measurements.screen,logicalScreen.startsWith('startup-')?'startup':logicalScreen.startsWith('family-')||logicalScreen.startsWith('destination-')||logicalScreen.startsWith('popup-')||logicalScreen==='initial-search-return'?'search':logicalScreen==='budget-draft'||logicalScreen==='budget-invalid'||logicalScreen==='budget-progress-invalid'?'filters':['terminal-complete','terminal-partial','terminal-error','initial-favorites','budget-results','results-loading','results-error','results-partial-empty','results-partial','results-bounded','filters-active','filter-empty','filter-empty-partial','calendar-later','error'].includes(logicalScreen)?'results':['hotel-return','hotel-selected-dearer','ai-hotel-checked'].includes(logicalScreen)?'hotel':['lead-manager','lead-selected-dearer','ai-contact'].includes(logicalScreen)?'lead':['tour-alternatives','tour-many-flights','ai-price-changed'].includes(logicalScreen)?'tour':logicalScreen,'Captured frame must show the requested usable screen, never a replacement startup shell');
  if(side==='native'){
    if(measurements.popupFit){const p=measurements.popupFit;assert.equal(p.modal,true);assert.equal(p.outside,false,'Popup stays inside the viewport');assert.equal(p.panelOverflow,false,'Popup content has no horizontal overflow');assert.equal(p.findVisible,true,'Find stays visible while popup content scrolls');assert.equal(p.findEnabled,true);assert(p.findHeight>=44);assert.equal(p.submitCount,1,'Popup reuses exactly one canonical search submit');}
    const cardIds=measurements.resultCardIds,notice=measurements.resultNotice;
    assert.ok(cardIds.every(id=>Number.isInteger(id)&&id>0),'Every measured result card retains its exact hotel ID');
    assert.equal(new Set(cardIds).size,cardIds.length,'Measured result card identities are unique');
    assert.equal(notice.cardNoticeCount,0,'Price explanation has no duplicate per-card owner');
    if(measurements.screen==='results'&&cardIds.length){
      assert.equal(notice.visibleCount,1,'Populated results have exactly one readable price explanation');
      assert.equal(notice.afterBatch,true,'The shared price explanation follows the current hotel batch and its progressive control');
      assert.equal(notice.text,'Цены из поиска — за весь тур и всех туристов. Перед заявкой проверим цену, наличие и состав пакета, включая обязательные доплаты.');
      const notes=measurements.readableText.filter(item=>item.kind==='result-notice');
      assert.equal(notes.length,1,'The visible shared notice has measured text');
      assert.equal(notes[0].cardId,null,'The shared notice belongs to the result list');
      for(const cardId of cardIds)for(const kind of ['result-title','result-location','result-date','result-room','result-meal','result-operator','result-party']){
        const facts=measurements.readableText.filter(item=>item.cardId===cardId&&item.kind===kind);
        assert.equal(facts.length,1,'Each result card measures one '+kind+' for hotel '+cardId);
        assert.ok(facts[0].text.trim(),'Measured result facts remain nonempty');
      }
    }else{
      assert.deepEqual(cardIds,[],'Non-results and empty frames show no result cards');
      assert.deepEqual(notice,{visibleCount:0,afterBatch:false,cardNoticeCount:0,text:''},'Empty and non-results frames show no price explanation');
      assert.equal(measurements.readableText.filter(item=>item.kind==='result-notice').length,0);
    }
    if(logicalScreen==='initial-favorites'){
      assert.equal(measurements.prices.length,0,'Unstarted results invent no prices');
      assert.equal(measurements.readableText.find(item=>item.text==='Поиск ещё не запускали')?.text,'Поиск ещё не запускали');
    }
    if(logicalScreen==='results'){
      assert.deepEqual(cardIds,fixture.hotels.map(hotel=>hotel.id),'Standard and short-desktop results retain all four factual hotel identities');
      for(const hotel of fixture.hotels){
        const expected={'result-title':hotel.name,'result-location':'Турция · Кемер','result-date':'12 окт · 7 ночей',
          'result-room':hotel.room,'result-meal':hotel.meal,'result-operator':'FIXTURE TV','result-party':'за 2 взрослых'};
        for(const [kind,text]of Object.entries(expected))assert.equal(measurements.readableText.find(item=>item.cardId===hotel.id&&item.kind===kind)?.text,text,'Standard and short-desktop results retain complete '+kind+' for hotel '+hotel.id);
      }
    }
  }
  if(side==='native'&&measurements.overflow)report.failures.push({width,percent,screen,error:'Native document horizontal overflow: '+measurements.documentWidth+' > '+measurements.viewportWidth});
  if(side==='native'&&measurements.prices.some(price=>price.overflow))report.failures.push({width,percent,screen,error:'Full price exceeds its rendered container',details:measurements.prices.filter(price=>price.overflow)});
  if(side==='native'&&measurements.prices.some(price=>price.fragmented||!price.textScaleVerified))report.failures.push({width,percent,screen,error:'Price digits fragment or dynamic text did not receive the requested scale',details:measurements.prices.filter(price=>price.fragmented||!price.textScaleVerified)});
  if(side==='native'&&measurements.buttons.some(button=>button.clipped))report.failures.push({width,percent,screen,error:'Primary action text exceeds its button',details:measurements.buttons.filter(button=>button.clipped)});
  if(side==='native'&&measurements.budgetInputs.some(input=>input.clipped))report.failures.push({width,percent,screen,error:'Budget number or unlimited placeholder is clipped',details:measurements.budgetInputs.filter(input=>input.clipped)});
  if(side==='native'&&width===1280&&percent===100&&measurements.screen==='results'&&measurements.budgetColumns!==2)report.failures.push({width,percent,screen,error:'Normal desktop budget must retain the donor two-column composition'});
  if(side==='native'&&measurements.readableText.some(node=>node.clipped||node.brokenWords||node.overlappingLines))report.failures.push({width,percent,screen,error:'Condition, navigation or flight text clips, overlaps lines or splits inside a word',details:measurements.readableText.filter(node=>node.clipped||node.brokenWords||node.overlappingLines)});
  if(side==='native'&&logicalScreen==='destination-confirm'){
    const fit=measurements.destinationFit;
    assert.ok(fit,'Country replacement frame must measure its visible decisions');
    assert.deepEqual(fit.actions.map(action=>action.text),['Оставить','Заменить']);
    assert.equal(measurements.readableText.filter(item=>['Оставить','Заменить'].includes(item.text.trim())).length,2,'Both complete decision labels receive readable text measurements');
    if(fit.outside||fit.overlap||fit.actions.some(action=>action.height<44||action.disabled)||fit.queryNarrow&&!fit.stacked)report.failures.push({width,percent,screen,error:'Country replacement decisions clip, overlap or fail accessible narrow reflow',details:fit});
    if(percent===100&&fit.stacked)report.failures.push({width,percent,screen,error:'Ordinary country replacement lost its donor side-by-side decisions',details:fit});
  }
  if(side==='native'&&measurements.countFit&&(measurements.countFit.outside||measurements.countFit.overlap))report.failures.push({width,percent,screen,error:'Result count/status text escapes its toolbar or overlaps a control',details:measurements.countFit});
  if(side==='native'&&measurements.calendarFit?.clipped)report.failures.push({width,percent,screen,error:'Calendar note or paging actions escape the calendar',details:measurements.calendarFit});
  if(side==='native'&&percent===200&&measurements.summaryFit.some(item=>item.queryNarrow&&(!item.stacked||item.copyWidthRatio<0.94)))report.failures.push({width,percent,screen,error:'Enlarged selected-hotel summary did not promote its factual copy to the full row',details:measurements.summaryFit.filter(item=>item.queryNarrow&&(!item.stacked||item.copyWidthRatio<0.94))});
  if(side==='native'&&percent===100&&width===390&&measurements.summaryFit.some(item=>item.queryNarrow||item.stacked))report.failures.push({width,percent,screen,error:'Ordinary mobile selected-hotel summary lost the donor side-by-side composition',details:measurements.summaryFit});
  if(side==='native'&&measurements.offerActionFit.some(item=>item.outside))report.failures.push({width,percent,screen,error:'Hotel offer action or its label escapes the offer row',details:measurements.offerActionFit.filter(item=>item.outside)});
  if(side==='native'&&logicalScreen==='lead'){
    const fit=measurements.consentFit;
    if(!fit||fit.label.height<44||fit.checkbox.width<20||fit.checkbox.height<20||!fit.ownsCheckbox||!fit.required||!fit.contained||!fit.focusOutline)report.failures.push({width,percent,screen,error:'Required consent is not a complete 44px touch target with visible keyboard focus',details:fit});
  }
  if(side==='native'&&percent===200&&measurements.offerActionFit.some(item=>item.queryNarrow&&(!item.actionOnOwnRow||item.actionWidthRatio<0.94)))report.failures.push({width,percent,screen,error:'Enlarged narrow hotel offer did not promote its action to a usable full row',details:measurements.offerActionFit.filter(item=>item.queryNarrow&&(!item.actionOnOwnRow||item.actionWidthRatio<0.94))});
  if(side==='native'&&percent===100&&width<=430&&measurements.offerActionFit.some(item=>item.queryNarrow||item.actionOnOwnRow||item.actionWidthRatio>0.6))report.failures.push({width,percent,screen,error:'Ordinary mobile hotel offer lost the donor price/action row',details:measurements.offerActionFit});
  if(side==='native'&&(measurements.headerFit.outside||measurements.headerFit.overlap))report.failures.push({width,percent,screen,error:'Header content overlaps or escapes the header',details:measurements.headerFit});
  if(side==='native'&&measurements.sortFit&&measurements.sortFit.textWidth>measurements.sortFit.available+1)report.failures.push({width,percent,screen,error:'Selected sort label is clipped',details:measurements.sortFit});
  if(side==='native'&&width===1280&&['search','results','hotel','tour','lead'].includes(screen)){
    const viewport=page.viewportSize();
    try{await page.setViewportSize({width,height:720});await capture(page,side,width,percent,'short-desktop-'+screen,row,false);}
    finally{await page.setViewportSize(viewport);}
  }
  return measurements;
}

async function referenceFrame(page,width,percent,screen,row) {
  await page.evaluate(({fixture,screen,width})=>{
    const controllers=window.__designV2DonorControllers;
    if(!controllers?.length)throw new Error('Original donor reference renderer missing');
    for(const controller of controllers){const mobile=controller.root.classList.contains('st-mobile');controller.root.closest('.st-stage').hidden=mobile!==(width<=760);controller.setFixture(fixture,screen);}
    document.querySelectorAll('.st-preview-nav,.st-caption,.st-browser-tabs').forEach(node=>node.hidden=true);
    window.lucide?.createIcons({attrs:{width:18,height:18}});
  },{fixture,screen,width});
  await capture(page,'donor',width,percent,screen,row);
}

(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const origin='http://127.0.0.1:'+server.address().port;
  const browser=await chromium.launch({headless:true});
  try {
    for(const width of [360,390,430,768,1280])for(const percent of [100]){
      const row={width,textPercent:percent,frames:[],checks:[],errors:[],unexpectedRequests:[],proof:{}};report.cases.push(row);
      const context=await browser.newContext({viewport:{width,height:900},locale:'ru-RU',timezoneId:'UTC',reducedMotion:'reduce'});
      const page=await context.newPage(),reference=await context.newPage();
      // Keep the controlled departure range current independently of the CI
      // runner's wall clock. The product must continue rejecting past dates;
      // only this fictional evidence clock is pinned to the fixture date.
      await Promise.all([page.clock.setFixedTime(fixtureNow),reference.clock.setFixedTime(fixtureNow)]);
      page.setDefaultTimeout(6000);reference.setDefaultTimeout(6000);
      page.setDefaultNavigationTimeout(10000);reference.setDefaultNavigationTimeout(10000);
      page.on('pageerror',error=>row.errors.push(error.message));reference.on('pageerror',error=>row.errors.push('donor: '+error.message));
      let terminalJourney=false;
      await context.route('**/*',async route=>{
        const request=route.request(),url=new URL(request.url());
        if(url.origin!==origin||request.method()!=='GET'||/\/(?:api[^/]*|lead-(?:adapter|bridge|receiver)[^/]*)\.(?:php|json)(?:$|\?)/.test(url.pathname)){
          row.unexpectedRequests.push({method:request.method(),url:request.url()});await route.abort();return;
        }
        if(url.pathname===base+'/visual-search/native-v2/bridge.js'){
          if(terminalJourney){const actual=fs.readFileSync(path.join(assetRoot,'visual-search/native-v2/bridge.js'),'utf8');assert.equal(hash(actual),manifest.files['bridge.js']);await route.fulfill({status:200,contentType:'application/javascript',body:actual+'\n('+recordTerminalBridge.toString()+')();'});return;}
          await route.fulfill({status:200,contentType:'application/javascript',body:'('+fixtureBridge.toString()+')('+JSON.stringify(fixture)+');'});return;
        }
        if(terminalJourney&&url.pathname===base+'/prototype-search/data.js'){await route.fulfill({status:200,contentType:'application/javascript',body:'('+terminalDataFixture.toString()+')('+JSON.stringify(fixture)+');'});return;}
        // Headless canonical contracts are exercised separately in the same CI
        // job. Browser presentation fixtures have no canonical network startup.
        if(url.pathname.endsWith('.js')&&!url.pathname.startsWith(base+'/visual-search/native-v2/')&&!url.pathname.endsWith('/lead-form-guard-v1.js')){
          await route.fulfill({status:200,contentType:'application/javascript',body:'/* controlled presentation fixture: canonical transport disabled */'});return;
        }
        await route.continue();
      });
      try {
        await page.addInitScript(()=>{window.__nativeHoldInit=true;window.__nativeMediaChanges=[];matchMedia('(max-width:760px)').addEventListener('change',event=>window.__nativeMediaChanges.push({mobile:event.matches,width:innerWidth}));});
        await Promise.all([page.goto(origin+base+'/visual-search/'),reference.goto(origin+base+'/__donor-reference/')]);
        await page.locator('[data-native-startup-status]').waitFor({state:'visible'});
        assert.equal(await page.locator('[data-product]:visible').count(),0,'Unbound donor forms stay hidden during initialization');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),0);
        await capture(page,'native',width,percent,'startup-pending',row);
        await page.evaluate(()=>window.__nativeFixture.rejectInit());
        await page.locator('[data-native-startup-status][role="alert"]').waitFor({state:'visible'});
        await capture(page,'native',width,percent,'startup-error',row);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),0,'Initialization failure does not search');
        await Promise.all([page.waitForNavigation(),page.locator('[data-native-startup-retry]').click()]);
        await page.locator('[data-native-startup-status]').waitFor({state:'visible'});
        await page.evaluate(()=>window.__nativeFixture.releaseInit());
        const product=page.locator('[data-product]:visible');
        await page.locator('[data-native-startup]').waitFor({state:'hidden'});
        row.proof.startupPendingFormHidden=true;row.proof.startupErrorSearches=0;row.proof.startupRetrySearches=0;

        const screen=name=>product.locator('[data-screen="'+name+'"]');
        await screen('search').waitFor({state:'visible'});
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),0,'Passive form hydration starts no supplier fixture');
        row.proof.passiveHydrationSearches=0;
        await capture(page,'native',width,percent,'search',row);await referenceFrame(reference,width,percent,'search',row);
        assert.equal(await product.locator('[data-screen="search"] [data-action="results"]').isVisible(),false,'There is no saved-result return before the first search');
        const originalFavorites=await page.evaluate(()=>localStorage.getItem('anytour.prototype.v18.favorites.v1'));
        await product.locator('[data-action="favorites"]').click();await screen('results').waitFor({state:'visible'});
        assert.equal(await product.locator('[data-result-count]').innerText(),'Поиск ещё не запускали');
        assert.equal(await product.locator('[data-empty-title]').innerText(),'Начните с поиска');
        assert.equal(await product.locator('[data-empty-action]').innerText(),'К параметрам поиска');
        assert.equal(await product.locator('.st-hotel-card').count(),0);
        await capture(page,'native',width,percent,'initial-favorites',row);
        await product.locator('[data-action="favorites"]').click();
        assert.equal(await product.locator('[data-action="favorites"]').getAttribute('aria-pressed'),'false');
        await product.locator('[data-empty-action]').click();await screen('search').waitFor({state:'visible'});
        assert.equal(await product.locator('[data-screen="search"] [data-action="results"]').isVisible(),false);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),0,'Presearch navigation never starts a supplier search');
        assert.equal(await page.evaluate(()=>localStorage.getItem('anytour.prototype.v18.favorites.v1')),originalFavorites,'Presearch navigation keeps saved favorite identities');
        await capture(page,'native',width,percent,'initial-search-return',row);
        row.proof.presearchNavigationTruthful=true;
        await product.locator('[data-search-destination-toggle]').click();
        const sameCountry=product.locator('[data-native-destination="country"][data-native-destination-id="4"]');
        await sameCountry.press('Enter');
        assert.equal(await sameCountry.evaluate(node=>node===document.activeElement),true,'Same-country choice survives its rendered dictionary');
        await capture(page,'native',width,percent,'destination-choice',row,false);
        assert.equal(await sameCountry.evaluate(node=>node===document.activeElement),true,'Direction choice capture retains keyboard focus');
        await page.keyboard.press('Escape');
        assert.equal(await product.locator('[data-search-destination-toggle]').evaluate(node=>node===document.activeElement),true,'Direction Escape restores its opener');
        await product.locator('[data-search-destination-toggle]').click();
        const otherCountry=product.locator('[data-native-destination="country"][data-native-destination-id="1"]');
        await otherCountry.press('Enter');
        assert.equal(await product.locator('[data-destination-keep]').evaluate(node=>node===document.activeElement),true,'Country replacement focuses its decision');
        assert.equal(await product.locator('[data-destination-country]').innerText(),'Турция','Unconfirmed choice leaves the original country');
        await capture(page,'native',width,percent,'destination-confirm',row,false);
        assert.equal(await product.locator('[data-destination-keep]').evaluate(node=>node===document.activeElement),true,'Country decision capture retains keyboard focus');
        await product.locator('[data-destination-keep]').press('Enter');
        assert.equal(await otherCountry.evaluate(node=>node===document.activeElement),true,'Keep restores the rejected country choice');
        await otherCountry.press('Enter');await product.locator('[data-destination-replace]').press('Enter');
        assert.equal(await otherCountry.evaluate(node=>node===document.activeElement),true,'Accepted country remains focused after dictionary refresh');
        assert.equal(await product.locator('[data-destination-country]').innerText(),'Египет');
        await page.keyboard.press('Escape');
        assert.equal(await product.locator('[data-search-destination-toggle]').evaluate(node=>node===document.activeElement),true);
        assert.equal(await product.locator('[data-destination-country]').innerText(),'Турция','Escape discards the replacement draft');
        assert.equal(await product.locator('[data-destination-summary]').innerText(),'Вся страна');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),0,'Direction choices never initiate a supplier search');
        row.proof.destinationKeyboardFocusRetained=true;
        if(percent===200&&(width===390||width===430)){
          const overlapProof=await page.evaluate(()=>{
            const caption=[...document.querySelectorAll('[data-product]')].find(node=>!node.closest('[hidden]')).querySelector('.st-origin-field > span');
            const repaired=window.__nativeTextLinesOverlap(caption),original=caption.style.lineHeight;
            caption.style.lineHeight='15px';const regression=window.__nativeTextLinesOverlap(caption);
            caption.style.lineHeight=original;const recovered=window.__nativeTextLinesOverlap(caption);
            return{repaired,regression,recovered};
          });
          assert.deepEqual(overlapProof,{repaired:false,regression:true,recovered:false},'Painted-line guard detects the original fixed-height caption and accepts its repaired reflow');
          row.proof.enlargedCaptionPaintOverlapRegressionChecked=true;
        }
        await product.locator('[data-search-nights-toggle]').click();
        await product.locator('[data-native-night="10"]').press('Enter');
        assert.equal(await product.locator('[data-native-night="10"]').evaluate(node=>node===document.activeElement),true,'Keyboard night choice retains focus');
        await page.keyboard.press('Tab');
        assert.equal(await product.locator('[data-native-night="11"]').evaluate(node=>node===document.activeElement),true,'Tab continues to the next night after choosing');
        await page.keyboard.press('Enter');
        assert.equal(await product.locator('[data-native-night="11"]').evaluate(node=>node===document.activeElement),true,'Keyboard range endpoint retains focus');
        await capture(page,'native',width,percent,'popup-nights',row,false);
        await page.keyboard.press('Escape');
        assert.equal(await product.locator('[data-search-nights-toggle]').evaluate(node=>node===document.activeElement),true,'Escape restores focus to the night opener');
        assert.equal(await product.locator('[data-search-night-value]').innerText(),'7 ночей','Keyboard Cancel retains the original duration');
        row.proof.nightKeyboardFocusRetained=true;
        await product.locator('[data-search-date-toggle]').click();
        await product.locator('[data-calendar-month="1"]:not(.st-calendar-mobile-next)').click();
        assert.equal(await product.locator('[data-calendar-month="1"]:not(.st-calendar-mobile-next)').evaluate(node=>node===document.activeElement),true,'Month paging retains its visible action');
        await product.locator('[data-calendar-month="-1"]').click();
        assert.equal(await product.locator('[data-calendar-month="-1"]').isEnabled(),false);
        assert.equal(await product.locator('[data-calendar-month="1"]:not(.st-calendar-mobile-next)').evaluate(node=>node===document.activeElement),true,'Earliest month focuses the enabled next-month action');
        await page.keyboard.press('Escape');
        row.proof.formCalendarPagingFocusRetained=true;
        await product.locator('[data-search-travelers-toggle]').click();
        await product.locator('[data-search-adult="-1"]').click();
        assert.equal(await product.locator('[data-search-travelers-value]').innerText(),'1 взрослый');
        await capture(page,'native',width,percent,'family-0',row);
        for(let index=0;index<3;index++){
          await product.locator('[data-search-add-child]').click();
          const age=product.locator('[data-native-age="'+index+'"]'),ageHandle=await age.elementHandle();
          assert.equal(await age.evaluate(node=>node===document.activeElement),true,'New child focuses its required age');
          assert.equal(await product.locator('[data-search-editor-done="travelers"]').isEnabled(),false,'Each child requires an explicit age');
          if(index===0)await age.press('ArrowDown');else await age.selectOption(String([0,7,17][index]));
          assert.equal(await age.inputValue(),String([0,7,17][index]));
          assert.equal(await ageHandle.evaluate(node=>node.isConnected&&node===document.activeElement),true,'Choosing age retains the same native select and focus');
          await ageHandle.dispose();
          assert.equal(await product.locator('[data-search-editor-done="travelers"]').isEnabled(),true);
          assert.equal(await product.locator('[data-search-travelers-value]').innerText(),['','1 взрослый + 1 ребёнок','1 взрослый + 2 ребёнка','1 взрослый + 3 ребёнка'][index+1]);
          await capture(page,'native',width,percent,'family-'+(index+1),row);
        }
        assert.equal(await product.locator('[data-search-add-child]').isEnabled(),false,'At most three children');
        await product.locator('[data-search-editor-done="travelers"]').click();
        await capture(page,'native',width,percent,'family-applied',row);
        await product.locator('[data-search-date-toggle]').click();
        await product.locator('[data-calendar-boundary="to"]').click();
        await product.locator('[data-native-date="2026-10-24"]').click();
        await product.locator('[data-calendar-done]').click();
        await product.locator('[data-search-travelers-toggle]').click();
        assert.match(await product.locator('[data-search-child-hint]').innerText(),/Проверьте возраста/);
        for(let index=0;index<3;index++)assert.equal(await product.locator('[data-native-age="'+index+'"]').inputValue(),String([0,7,17][index]));
        await capture(page,'native',width,percent,'family-review',row);
        await product.locator('[data-search-editor-done="travelers"]').click();
        await product.locator('[data-search-date-toggle]').click();
        await product.locator('[data-calendar-boundary="to"]').click();
        await product.locator('[data-native-date="2026-10-25"]').click();
        await product.locator('[data-calendar-done]').click();
        await product.locator('[data-search-travelers-toggle]').click();
        for(let index=2;index>=0;index--){
          await product.locator('[data-native-remove-child="'+index+'"]').click();
          const target=product.locator(index?'[data-native-age="'+(index-1)+'"]':'[data-search-add-child]');
          assert.equal(await target.evaluate(node=>node===document.activeElement),true,'Removing a child retains a usable adjacent input');
        }
        await product.locator('[data-search-adult="1"]').click();
        await product.locator('[data-search-editor-done="travelers"]').click();
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),0,'Family edits and age review start no search');
        row.proof.familyAges=[0,7,17];row.proof.familyExtraSearches=0;row.proof.familyAgeReviewRetained=true;
        row.proof.familyInputFocusRetained=true;

        const formBefore=await product.locator('[data-search-form]').boundingBox();
        await product.locator('[data-search-date-toggle]').click();
        const formAfter=await product.locator('[data-search-form]').boundingBox();
        assert(Math.abs(formAfter.height-formBefore.height)<1.1,'Opening a popup does not expand the form');
        await capture(page,'native',width,percent,'popup-calendar',row,false);
        await screen('search').locator('button[type="submit"]').click();
        await screen('results').waitFor({state:'visible'});
        assert.equal(await product.locator('[data-native-form-popup]').isVisible(),false,'Find applies the valid popup selection and closes it');
        row.proof.formPopupSingleSubmitRetained=true;
        await product.locator('.st-hotel-card').nth(3).waitFor();
        assert.match(await product.locator('[data-mobile-dates]').textContent(),/12–25 окт/,'Same-month range keeps the donor compact copy');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),1);
        row.proof.formSubmitSearches=1;
        assert.deepEqual(await product.locator('.st-name-button').allTextContents(),fixture.hotels.map(hotel=>hotel.name),'Complete hotel names survive title/arrow wrapping');
        assert.equal(await product.locator('.st-hotel-card .st-card-bottom').count(),0,'Provisional price explanation has one shared owner');
        const notice=product.locator('.st-results-note');
        assert.equal(await notice.isVisible(),true);
        assert.match(await notice.textContent(),/за весь тур и всех туристов.*проверим цену, наличие и состав пакета.*обязательные доплаты/);
        assert.equal(await notice.evaluate(node=>Boolean(node.parentElement.querySelector('[data-result-more]').compareDocumentPosition(node)&Node.DOCUMENT_POSITION_FOLLOWING)),true,'Price explanation follows the current result batch and its progressive control');
        await capture(page,'native',width,percent,'results',row);await referenceFrame(reference,width,percent,'results',row);
        const originalStarts=await page.evaluate(()=>window.__nativeFixture.calls.search);
        const firstFavorite=product.locator('[data-favorite="1"]');await firstFavorite.focus();await firstFavorite.press('Enter');
        assert.equal(await product.locator('[data-favorite="1"]').getAttribute('aria-pressed'),'true');
        assert.equal(await product.locator('[data-favorite="1"]').evaluate(node=>node===document.activeElement),true,'Favorite rerender restores the same card control');
        const secondFavorite=product.locator('[data-favorite="2"]');await secondFavorite.focus();await secondFavorite.press('Enter');
        assert.deepEqual(await page.evaluate(()=>JSON.parse(localStorage.getItem('anytour.prototype.v18.favorites.v1'))),[1,2]);
        const favoritesToggle=product.locator('[data-action="favorites"]:visible').first();await favoritesToggle.click();
        assert.equal(await favoritesToggle.getAttribute('aria-pressed'),'true');
        await product.locator('[data-favorite="1"]').focus();await product.locator('[data-favorite="1"]').press('Enter');
        assert.equal(await product.locator('[data-favorite="2"]').evaluate(node=>node===document.activeElement),true,'Removing a saved card focuses the adjacent saved card');
        await product.locator('[data-favorite="2"]').press('Enter');
        assert.equal(await product.locator('[data-empty-action]').evaluate(node=>node===document.activeElement),true,'Removing the final saved card focuses the recovery action');
        assert.deepEqual(await page.evaluate(()=>JSON.parse(localStorage.getItem('anytour.prototype.v18.favorites.v1'))),[]);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts,'Favorite-only local interactions start zero supplier requests');
        await product.locator('[data-action="favorites"]:visible').first().click();
        assert.equal(await product.locator('[data-action="favorites"]:visible').first().getAttribute('aria-pressed'),'false');
        row.proof.favoriteCardFocusRetained=true;row.proof.favoriteRemovalFocusRecovered=true;row.proof.favoriteLocalExtraSearches=0;
        await page.evaluate(()=>window.__nativeFixture.deliverHotels([],{status:'loading',message:''}));
        assert.equal(await product.locator('[data-result-count]').innerText(),'Ищем…');
        assert.equal(await product.locator('[data-empty-action]').isVisible(),false);
        assert.equal(await product.locator('[data-source-alert]').isVisible(),false);
        assert.equal(await product.locator('[data-empty]').getAttribute('aria-busy'),'true');
        await capture(page,'native',width,percent,'results-loading',row);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts);
        row.proof.loadingInitialCountNeutral=true;row.proof.loadingEmptyActionHidden=true;row.proof.loadingExtraSearches=0;
        await page.evaluate(()=>window.__nativeFixture.deliverHotels([],{status:'error',message:'API HTTP 500'}));
        assert.equal(await product.locator('[data-result-count]').innerText(),'Поиск прерван');
        assert.equal(await product.locator('[data-empty-title]').innerText(),'Не удалось загрузить туры');
        assert.equal(await product.locator('[data-empty-action]').innerText(),'Вернуться к поиску');
        assert.equal(await product.locator('[data-source-alert]').isVisible(),false);
        assert.equal(await product.locator('[data-empty]').getAttribute('aria-busy'),'false');
        await capture(page,'native',width,percent,'results-error',row);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts);
        row.proof.failedInitialCountNeutral=true;row.proof.failedInitialFriendlyCopy=true;row.proof.failedInitialExtraSearches=0;
        await page.evaluate(()=>window.__nativeFixture.deliverHotels([],{status:'partial',message:''}));
        assert.equal(await product.locator('[data-result-count]').innerText(),'Неполные данные');
        assert.equal(await product.locator('[data-empty-title]').innerText(),'Поиск завершён не полностью');
        assert.equal(await product.locator('[data-empty-action]').innerText(),'Вернуться к поиску');
        assert.match(await product.locator('[data-empty-text]').innerText(),/Предложения не получены/);
        assert.equal(await product.locator('[data-source-alert]').isVisible(),false,'No phantom received proposals in empty partial completion');
        assert.equal(await product.locator('[data-empty]').getAttribute('aria-busy'),'false');
        await capture(page,'native',width,percent,'results-partial-empty',row);
        await product.locator('[data-empty-action]').click();await screen('search').waitFor({state:'visible'});
        assert.equal(await screen('search').locator('[name="origin"]').inputValue(),fixture.trip.origin);
        assert.equal(await screen('search').locator('[name="country"]').inputValue(),fixture.trip.country);
        await page.goBack();await screen('results').waitFor({state:'visible'});
        assert.equal(await product.locator('[data-result-count]').innerText(),'Неполные данные');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.getSnapshot().status),'partial');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts,'Partial retry preparation and Back start no search');
        await page.evaluate(()=>window.__nativeFixture.deliverHotels(window.__nativeFixture.hotels,{status:'partial',message:''}));
        assert.equal(await product.locator('.st-hotel-card').count(),4,'Partial completion retains every received hotel');
        assert.equal(await product.locator('[data-result-count]').innerText(),'4 отеля');
        assert.match(await product.locator('[data-source-alert]').innerText(),/Поиск завершён не полностью/);
        await capture(page,'native',width,percent,'results-partial',row);
        row.proof.partialEmptyNeutralCount=true;row.proof.partialSavedSearchRetained=true;row.proof.partialLoadedHotels=4;row.proof.partialExtraSearches=0;
        await page.evaluate(()=>window.__nativeFixture.deliverHotels(window.__nativeFixture.hotels,{status:'complete',message:''}));
        await page.evaluate(()=>{
          const source=window.__nativeFixture.hotels;
          const bulk=Array.from({length:55},(_,index)=>{
            const template=source[index%source.length],id=1000+index;
            return{...template,id,name:'FICTIONAL loaded hotel '+String(index+1).padStart(2,'0'),offers:template.offers.slice(0,1).map(offer=>({...offer,key:'fixture:bulk:'+id,hotelId:id}))};
          });
          window.__nativeFixture.deliverHotels(bulk);
        });
        assert.equal(await product.locator('.st-hotel-card').count(),24,'Only the first bounded batch is rendered');
        assert.equal(await product.locator('[data-result-count]').innerText(),'55 отелей','Full factual loaded count remains visible');
        assert.equal(await product.locator('[data-native-result-more]').innerText(),'Показать ещё 24');
        await capture(page,'native',width,percent,'results-bounded',row);
        await product.locator('[data-native-result-more]').click();
        assert.equal(await product.locator('.st-hotel-card').count(),48);
        assert.equal(await product.locator('[data-native-result-more]').evaluate(node=>node===document.activeElement),true,'The remaining load-more control keeps keyboard focus');
        await product.locator('.st-hotel-card').first().locator('.st-name-button').click();await screen('hotel').waitFor({state:'visible'});await page.goBack();await screen('results').waitFor({state:'visible'});
        assert.equal(await product.locator('.st-hotel-card').count(),48,'Back retains the revealed result batch');
        await product.locator('[data-native-result-more]').click();
        assert.equal(await product.locator('.st-hotel-card').count(),55);
        assert.equal(await product.locator('[data-native-result-more]').count(),0,'The control disappears after every loaded row is visible');
        assert.equal(await product.locator('.st-hotel-card').nth(48).locator('.st-name-button').evaluate(node=>node===document.activeElement),true,'The first final-batch card receives focus when the control disappears');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts,'Revealing loaded rows starts no supplier search');
        row.proof.boundedInitialCards=24;row.proof.boundedLoadedHotels=55;row.proof.boundedBackCards=48;row.proof.boundedExtraSearches=0;
        await page.evaluate(()=>window.__nativeFixture.deliverHotels(window.__nativeFixture.hotels));
        const calendar=product.locator('.q8c9');
        if(width<=760){
          await calendar.locator('[data-native-calendar]').click();
          assert.equal(await calendar.locator('[data-native-calendar]').evaluate(node=>node===document.activeElement),true,'The mobile calendar toggle keeps keyboard focus after its panel rerenders');
        }
        assert.deepEqual(await calendar.locator('[data-native-day]').evaluateAll(nodes=>nodes.map(node=>node.dataset.nativeDay)),['2026-10-12','2026-10-13','2026-10-14','2026-10-15','2026-10-16','2026-10-17','2026-10-18']);
        const nextDates=calendar.locator('[data-native-calendar-page="next"]');await nextDates.focus();await nextDates.press('Enter');
        assert.deepEqual(await calendar.locator('[data-native-day]').evaluateAll(nodes=>nodes.map(node=>node.dataset.nativeDay)),['2026-10-19','2026-10-20','2026-10-21','2026-10-22','2026-10-23','2026-10-24','2026-10-25']);
        assert.equal(await calendar.locator('[data-native-calendar-page="prev"]').evaluate(node=>node===document.activeElement),true,'The enabled paging control keeps keyboard focus after the last page replaces its button');
        assert.equal(await calendar.locator('[data-native-day="2026-10-25"] .st-pc-full').innerText(),'166 400 ₽','Later page uses the exact loaded full-tour total');
        assert.match(await calendar.locator('[data-native-day="2026-10-24"]').getAttribute('aria-label'),/цена пока не получена/,'Unknown later-day price stays explicit without asserting availability');
        await capture(page,'native',width,percent,'calendar-later',row);
        await calendar.locator('[data-native-day="2026-10-25"]').click();
        assert.equal(await product.locator('.st-hotel-card').count(),1,'A later loaded date filters existing cards locally');
        assert.equal(await calendar.locator('[data-native-day="2026-10-25"]').getAttribute('aria-pressed'),'true','The selected later date remains visible on its page');
        assert.equal(await calendar.locator('[data-native-day="2026-10-25"]').evaluate(node=>node===document.activeElement),true,'The selected date keeps keyboard focus after local result rerender');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts,'Paging and later-date selection start no supplier search');
        await calendar.locator('[data-native-day="2026-10-25"]').click();assert.equal(await product.locator('.st-hotel-card').count(),4);
        assert.equal(await calendar.locator('[data-native-day="2026-10-25"]').evaluate(node=>node===document.activeElement),true,'The cleared date keeps keyboard focus after local result rerender');
        await product.locator('[data-hotel-open="1"]').first().click();await screen('hotel').waitFor({state:'visible'});
        const currentFavorite=product.locator('[data-favorite-current]');await currentFavorite.focus();await currentFavorite.press('Enter');
        assert.equal(await currentFavorite.getAttribute('aria-pressed'),'true');assert.equal(await currentFavorite.getAttribute('aria-label'),'Удалить из избранного');
        assert.equal(await currentFavorite.evaluate(node=>node===document.activeElement),true,'Hotel favorite keeps focus with truthful selected state');
        await currentFavorite.press('Enter');assert.equal(await currentFavorite.getAttribute('aria-pressed'),'false');assert.equal(await currentFavorite.getAttribute('aria-label'),'Сохранить отель');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts,'Hotel favorite starts zero supplier requests');
        await page.goBack();await screen('results').waitFor({state:'visible'});
        assert.equal(await calendar.locator('[data-native-day="2026-10-19"]').count(),1,'Back restores the same calendar page');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts,'Calendar history return starts no supplier search');
        row.proof.calendarPages=2;row.proof.calendarLaterLoadedTotal=166400;row.proof.calendarControlFocusRetained=true;row.proof.calendarPagingExtraSearches=0;row.proof.calendarHistoryPageRetained=true;row.proof.hotelFavoriteStateTruthful=true;
        if(width===390&&percent===100){
          await page.evaluate(()=>window.__nativeFixture.deliverHotels([window.__nativeFixture.hotels[0]]));
          assert.equal(await calendar.locator('[data-native-day="2026-10-19"]').count(),1,'Progressive results retain the visible calendar page');
          await product.locator('[data-action="filters"]').first().click();
          const drawer=product.locator('[data-filter-form]'),cap=drawer.locator('input[type="number"][data-native-budget="max"]'),min=drawer.locator('input[type="number"][data-native-budget="min"]');
          await cap.fill('200000');
          assert.equal(await product.locator('[data-filter-apply]').innerText(),'Показать 1 отель');
          await page.evaluate(()=>window.__nativeFixture.deliverHotels(window.__nativeFixture.hotels.slice(0,3)));
          assert.equal(await product.locator('[data-filter-apply]').innerText(),'Показать 3 отеля','New same-generation inventory refreshes the open drawer');
          assert.equal(await cap.inputValue(),'200000');assert.equal(await cap.evaluate(node=>node===document.activeElement),true,'Snapshot retains the typed input and focus');
          await min.fill('250000');
          await page.evaluate(()=>window.__nativeFixture.deliverHotels(window.__nativeFixture.hotels));
          assert.equal(await min.inputValue(),'250000');assert.equal(await min.evaluate(node=>node===document.activeElement),true);assert.equal(await product.locator('[data-filter-apply]').isEnabled(),false);
          assert.equal(await drawer.locator('input[type="range"]').getAttribute('max'),'1059000');
          await capture(page,'native',width,percent,'budget-progress-invalid',row);
          await product.locator('[data-action="close-filters"]').click();assert.equal(await product.locator('.st-hotel-card').count(),4);
          assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts);row.proof.progressiveDrawerRetainedDraftAndFocus=true;
        }
        if(width<=760)await product.locator('[data-action="filters"]').first().click();
        const meal=product.locator('[data-native-filter="meals"][value="Ультра всё включено"]:visible').first();
        await meal.check();
        if(width<=760)await product.locator('[data-action="apply-filters"]').click();
        else assert.equal(await product.locator('[data-filter-sidebar] [data-native-filter="meals"][value="Ультра всё включено"]').evaluate(node=>node===document.activeElement),true,'The desktop inline facet keeps keyboard focus after local result rerender');
        assert.equal(await product.locator('.st-hotel-card').count(),2,'Loaded meal filter matches factual fixtures');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts,'Local filter starts zero supplier requests');
        row.proof.localFilterExtraSearches=0;if(width>760)row.proof.desktopFacetFocusRetained=true;
        const activeMeal=product.locator('[data-active-chips] [data-native-remove-constraint="meals"]');
        assert.equal(await activeMeal.innerText(),'Ультра всё включено');
        assert((await activeMeal.boundingBox()).height>=44,'Condition removal has a usable touch target');
        await capture(page,'native',width,percent,'filters-active',row);
        if(width<=760)await product.locator('[data-action="filters"]').first().click();
        const recoveryBudget=width<=760?product.locator('[data-filter-form]'):product.locator('[data-filter-sidebar]');
        await recoveryBudget.locator('input[type="number"][data-native-budget="max"]').fill('100000');
        if(width<=760)await product.locator('[data-action="apply-filters"]').click();else await recoveryBudget.locator('input[type="number"][data-native-budget="max"]').press('Tab');
        assert.equal(await product.locator('.st-hotel-card').count(),0);
        assert.equal(await product.locator('[data-empty-action]').getAttribute('data-native-remove-constraint'),'budget');
        assert.match(await product.locator('[data-empty-text]').innerText(),/2 отеля из загруженных/);
        assert.match(await calendar.locator('.st-pc-foot').innerText(),/По выбранным условиям на этих датах предложений нет/);
        assert.match(await calendar.locator('[data-native-day="2026-10-25"]').getAttribute('aria-label'),/нет предложений по выбранным условиям/);
        row.proof.filteredCalendarTruthful=true;
        await capture(page,'native',width,percent,'filter-empty',row);
        await page.evaluate(()=>window.__nativeFixture.deliverHotels(window.__nativeFixture.hotels,{status:'partial',message:''}));
        assert.equal(await product.locator('[data-empty-title]').innerText(),'Нет подходящих туров среди полученных');
        assert.equal(await product.locator('[data-empty-action]').getAttribute('data-native-remove-constraint'),'budget');
        assert.match(await product.locator('[data-empty-text]').innerText(),/2 отеля из загруженных/);
        await capture(page,'native',width,percent,'filter-empty-partial',row);
        row.proof.partialFilteredRecoveryLoadedHotels=2;
        await product.locator('[data-empty-action]').click();
        assert.equal(await product.locator('.st-hotel-card').count(),2,'Single-condition recovery preserves the applied meal');
        assert.equal(await activeMeal.innerText(),'Ультра всё включено');
        assert.equal(new URL(page.url()).searchParams.has('max'),false);
        await activeMeal.click();
        assert.equal(await product.locator('[data-active-chips]').isVisible(),false);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts,'Chip removal and local recovery start no supplier search');
        row.proof.activeConstraintsVisible=true;row.proof.recoveryLoadedHotels=2;row.proof.recoveryPreservedMeal=true;row.proof.recoveryExtraSearches=0;
        assert.equal(await product.locator('.st-hotel-card').count(),4);
        await page.evaluate(()=>window.__nativeFixture.deliverHotels(window.__nativeFixture.hotels,{status:'complete',message:''}));
        const budgetPanel=width<=760?product.locator('[data-filter-form]'):product.locator('[data-filter-sidebar]');
        if(width<=760)await product.locator('[data-action="filters"]').first().click();
        const cap=budgetPanel.locator('input[type="number"][data-native-budget="max"]');
        await cap.fill('150000');
        if(width<=760){
          assert.equal(await product.locator('[data-filter-apply]').innerText(),'Показать 2 отеля','Budget draft counts exact loaded rows');
          assert.equal(await budgetPanel.locator('input[type="range"]').inputValue(),'150000');
          await budgetPanel.locator('input[type="number"][data-native-budget="min"]').fill('200000');
          assert.equal(await product.locator('[data-filter-apply]').isEnabled(),false,'Invalid budget cannot apply');
          await capture(page,'native',width,percent,'budget-invalid',row);
          await budgetPanel.locator('input[type="number"][data-native-budget="min"]').fill('0');
          if(width===390&&percent===100){
            await page.setViewportSize({width:768,height:900});await screen('filters').waitFor({state:'visible'});
            await page.setViewportSize({width,height:900});await screen('filters').waitFor({state:'visible'});
            assert.equal(await cap.inputValue(),'150000','Open budget draft survives real breakpoint remount');
            assert.equal(await product.locator('[data-filter-apply]').innerText(),'Показать 2 отеля');row.proof.budgetBreakpointDraftRetained=true;
          }
          await capture(page,'native',width,percent,'budget-draft',row);
          await product.locator('[data-action="apply-filters"]').click();
        }else{
          await cap.press('Tab');
          assert.equal(await budgetPanel.locator('input[type="number"][data-native-budget="max"]').evaluate(node=>node===document.activeElement),true,'The desktop inline budget keeps keyboard focus after local result rerender');
        }
        assert.equal(await product.locator('.st-hotel-card').count(),2,'Budget cap uses the full loaded price');
        await capture(page,'native',width,percent,'budget-results',row);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts,'Budget edits and viewport remount start no supplier search');
        row.proof.budgetLoadedCount=2;row.proof.budgetExtraSearches=0;if(width>760)row.proof.desktopBudgetFocusRetained=true;
        if(width<=760){
          await product.locator('[data-action="filters"]').first().click();
          await screen('filters').locator('[data-action="reset-draft"]').click();
          assert.equal(await product.locator('[data-filter-apply]').innerText(),'Показать 4 отеля');
          await product.locator('[data-action="apply-filters"]').click();
        }else await product.locator('[data-action="reset-filters"]').first().click();
        assert.equal(await product.locator('.st-hotel-card').count(),4);
        // Full-page screenshots cannot prove where a real screen transition
        // lands. Open a deep result and return before the paired hotel frame.
        const deepResult=product.locator('[data-hotel-open="4"]').first();
        await deepResult.scrollIntoViewIfNeeded();await deepResult.focus();
        const deepPosition=await deepResult.evaluate(node=>({scroll:scrollY,top:node.getBoundingClientRect().top}));
        await deepResult.click();await screen('hotel').waitFor({state:'visible'});
        const hotelLanding=await product.locator('[data-hotel-name]').evaluate(node=>({focused:node===document.activeElement,top:node.getBoundingClientRect().top,bottom:node.getBoundingClientRect().bottom,height:innerHeight}));
        assert(hotelLanding.focused&&hotelLanding.top>=0&&hotelLanding.bottom<=hotelLanding.height,'Opening a deep result lands at the visible hotel heading');
        await screen('hotel').locator('[data-action="results"]').click();await screen('results').waitFor({state:'visible'});
        const returnedPosition=await deepResult.evaluate(node=>({focused:node===document.activeElement,scroll:scrollY,top:node.getBoundingClientRect().top}));
        assert(returnedPosition.focused&&Math.abs(returnedPosition.top-deepPosition.top)<=2,'The saved-results return restores the initiating card and viewport position');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts);
        row.proof.screenNavigation={deepScroll:deepPosition.scroll,hotelHeadingVisible:true,resultFocusRestored:true,resultAnchorDelta:returnedPosition.top-deepPosition.top,extraSearches:0};
        await product.locator('[data-hotel-open="1"]').first().click();
        await screen('hotel').waitFor({state:'visible'});
        await capture(page,'native',width,percent,'hotel',row);await referenceFrame(reference,width,percent,'hotel',row);
        await product.locator('[data-hotel-offers] [data-offer="fixture:offer:1"]').click();
        await screen('tour').waitFor({state:'visible'});
        if(width<=760)assert.equal(await product.locator('.st-stay-details').isVisible(),false,'Original mobile quick summary replaces duplicate stay details');
        await product.locator('[data-native-tour-flight="0"]').waitFor({state:'visible'});
        await product.locator('[data-native-tour-flight="0"]').click();
        await waitForFixture(page,()=>document.activeElement?.matches('[data-native-flight-alternatives] summary'));
        await product.locator('[data-action="accept-price"]:visible').first().click();
        await product.locator('[data-action="lead"]:visible').first().waitFor({state:'visible'});
        assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().offer===window.__nativeFixture.hotels[0].offers[0]),true,'Exact loaded offer reference retained');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().total),112000,'Selected flight is the full canonical total');
        assert.deepEqual(await page.evaluate(()=>window.__nativeFixture.calls.flight),[0],'Original selected flight index retained');
        row.proof.exactLoadedOfferRetained=true;row.proof.flightChoiceIndex=0;row.proof.selectedFullTotal=112000;
        assert.match(await screen('tour').innerText(),/112\s*000/);
        assert.match(await screen('tour').innerText(),/09:20/);
        assert.doesNotMatch(await screen('tour').innerText(),/08:10/,'Donor mock flight does not become a product fact');
        await capture(page,'native',width,percent,'tour',row);await referenceFrame(reference,width,percent,'tour',row);
        const alternatives=product.locator('[data-native-flight-alternatives]'),disclosure=alternatives.locator('summary');
        assert.equal(await alternatives.getAttribute('open'),null,'Selected schedule stays visible with alternatives collapsed');
        assert.equal(await product.locator('[data-native-tour-flight="0"]').count(),0,'Selected pair/price has no repeated choice row');
        assert.equal(await product.locator('[data-native-tour-flight="1"]').isVisible(),false);
        assert.equal(await disclosure.evaluate(node=>node.getBoundingClientRect().height>=44),true);
        const closedFlightHeight=await product.locator('[data-native-flight-section]').evaluate(node=>node.getBoundingClientRect().height);
        await disclosure.focus();await page.keyboard.press('Enter');
        await product.locator('[data-native-tour-flight="1"]').waitFor({state:'visible'});
        const expandedFlightHeight=await product.locator('[data-native-flight-section]').evaluate(node=>node.getBoundingClientRect().height);
        assert(expandedFlightHeight>closedFlightHeight+44,'Disclosure saves a material amount of tour-page height');
        await capture(page,'native',width,percent,'tour-alternatives',row);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.quote),1,'Opening loaded flight choices starts no quote');
        assert.deepEqual(await page.evaluate(()=>window.__nativeFixture.calls.flight),[0]);
        await product.locator('[data-native-tour-flight="1"]').click();
        await waitForFixture(page,()=>window.__nativeFixture.getSelection().flightChoiceId===1);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().total),123000,'Alternative preserves its full canonical total');
        assert.equal(await alternatives.getAttribute('open'),null,'New selected pair collapses alternatives');
        await product.locator('[data-action="accept-price"]:visible').first().click();
        await disclosure.click();await product.locator('[data-native-tour-flight="0"]').click();
        await product.locator('[data-action="accept-price"]:visible').first().click();
        assert.deepEqual(await page.evaluate(()=>window.__nativeFixture.calls.flight),[0,1,0],'Original indices survive filtered alternative list');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts);
        row.proof.flightAlternativesClosedHeight=closedFlightHeight;row.proof.flightAlternativesExpandedHeight=expandedFlightHeight;
        row.proof.flightAlternativesSavedHeight=expandedFlightHeight-closedFlightHeight;row.proof.flightAlternativesExtraQuotes=0;row.proof.flightAlternativeOriginalIndices=[0,1,0];
        await disclosure.click();
        await product.locator('[data-action="lead"]:visible').first().click();
        await screen('lead').waitFor({state:'visible'});
        if(width<=760)await product.locator('[data-native-contact-facts] > summary').click();
        for(const fact of ['09:20','13:20','13:00','17:00','FX 101','FX 102','FIXTURE Moscow','FIXTURE Antalya','FIXTURE TV','Москва'])assert((await product.locator('[data-native-contact-facts]').innerText()).includes(fact),'Final contact review retains '+fact);
        assert.match(await product.locator('[data-lead-context]').innerText(),/12 окт — 19 окт/);
        assert.match(await product.locator('[data-native-contact-note]').innerText(),/Цена проверена с выбранным перелётом/);
        row.proof.contactExactItineraryRetained=true;
        await capture(page,'native',width,percent,'lead',row);await referenceFrame(reference,width,percent,'lead',row);
        const leadPriceFit=await screen('lead').evaluate(root=>{
          const card=root.querySelector('.st-lead'),price=card?.querySelector('[data-native-contact-summary] > .st-price'),caption=card?.querySelector('[data-native-price-caption]'),amount=card?.querySelector('[data-tour-price]');
          if(!price||!caption||!amount)return null;
          const bounds=price.getBoundingClientRect(),label=caption.getBoundingClientRect(),value=amount.getBoundingClientRect();
          const captionStyle=getComputedStyle(caption);
          const inside=box=>box.width>0&&box.height>0&&box.left>=bounds.left-1&&box.right<=bounds.right+1&&box.top>=bounds.top-1&&box.bottom<=bounds.bottom+1;
          return{captionCount:card.querySelectorAll('[data-native-price-caption]').length,amountCount:card.querySelectorAll('[data-tour-price]').length,
            readableCaptionWeight:Number(captionStyle.fontWeight)<=500,readableCaptionSpacing:captionStyle.letterSpacing==='normal'||parseFloat(captionStyle.letterSpacing)>=0,
            captionInsidePrice:inside(label),amountInsidePrice:inside(value),captionAboveAmount:label.bottom<=value.top+1,
            overflow:price.scrollWidth>price.clientWidth+1||caption.scrollWidth>caption.clientWidth+1||amount.scrollWidth>amount.clientWidth+1};
        });
        assert(leadPriceFit?.captionCount===1&&leadPriceFit.amountCount===1&&leadPriceFit.readableCaptionWeight&&leadPriceFit.readableCaptionSpacing&&leadPriceFit.captionInsidePrice&&leadPriceFit.amountInsidePrice&&leadPriceFit.captionAboveAmount&&!leadPriceFit.overflow,'A single readable contact price caption and amount must stay together inside the bordered price block');
        row.proof.contactPriceBlockFit=leadPriceFit;
        if(width<=760)await product.locator('[data-native-contact-facts] > summary').click();
        const form=product.locator('[data-lead-form]');
        await form.locator('[name="name"]').fill('FICTIONAL QA');await form.locator('[name="phone"]').fill('+7 999 000 00 00');await form.locator('[name="consent"]').check();
        await form.locator('button[type="submit"]').click();
        await waitForFixture(page,()=>[...document.querySelectorAll('[data-lead-form]')].some(form=>form.dataset.checked==='1'));
        assert.deepEqual(await page.evaluate(()=>({price:window.__nativeFixture.payload.price,flightChoiceId:window.__nativeFixture.payload.flightChoiceId,preview:window.__nativeFixture.calls.preview,submit:window.__nativeFixture.calls.submit})),{price:112000,flightChoiceId:0,preview:1,submit:0});
        row.proof.contactPayloadFullTotal=112000;row.proof.contactPayloadFlightChoiceIndex=0;row.proof.contactPreviewCount=1;row.proof.realLeadSubmissions=0;
        await page.goBack();await screen('tour').waitFor({state:'visible'});assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().total),112000);
        assert.equal(await alternatives.getAttribute('open'),'','Back retains loaded-alternatives disclosure');
        await page.goBack();await screen('hotel').waitFor({state:'visible'});
        assert.equal(await product.locator('[data-hotel-offers] .st-offer--selected [data-offer="fixture:offer:1"]').count(),1,'Back highlights only the exact previously selected offer');
        await capture(page,'native',width,percent,'hotel-return',row);
        await page.goForward();await screen('tour').waitFor({state:'visible'});
        await page.goForward();await screen('lead').waitFor({state:'visible'});assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().total),112000);
        assert.equal(await form.locator('[name="name"]').inputValue(),'FICTIONAL QA');assert.equal(await form.locator('[name="phone"]').inputValue(),'+7 999 000 00 00');assert.equal(await form.locator('[name="consent"]').isChecked(),true,'Unchanged selection retains the contact draft and consent on Back/Forward');
        row.proof.contactDraftAndConsentRetained=true;
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts,'Render/Back/Forward starts no supplier requests');
        row.proof.backForwardRetainedSelection=true;row.proof.backForwardExtraSearches=0;
        await product.locator('.st-footer [data-action="search"]').click();await screen('search').waitFor({state:'visible'});
        await page.evaluate(()=>{window.__nativeFixture.failNextSearch=true;});
        await screen('search').locator('button[type="submit"]').click();
        await waitForFixture(page,()=>[...document.querySelectorAll('[data-source-alert]')].some(node=>!node.hidden&&node.textContent.includes('Не удалось завершить поиск')));
        const failedAlert=product.locator('[data-source-alert]');
        assert.equal((await failedAlert.innerText()).includes('FICTIONAL'),false,'Raw bridge failures never become presentation copy');
        assert.equal(await failedAlert.locator('strong').innerText(),'Не удалось завершить поиск');
        assert.match(await failedAlert.innerText(),/Полученные предложения сохранены/);
        assert.equal(await failedAlert.locator('[data-action="search"]').innerText(),'Изменить и повторить');
        await capture(page,'native',width,percent,'error',row);
        assert.equal(await product.locator('.st-hotel-card').count(),4,'Search failure retains received rows');
        await failedAlert.locator('[data-action="search"]').click();await screen('search').waitFor({state:'visible'});
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts+1,'Opening retry parameters starts no supplier search');
        row.proof.errorRetainedRows=4;row.proof.errorRawMessageHidden=true;row.proof.errorRetryPreparationExtraSearches=0;
        await screen('search').locator('button[type="submit"]').click();
        await waitForFixture(page,()=>window.__nativeFixture.calls.search===3);
        await waitForFixture(page,()=>[...document.querySelectorAll('[data-source-alert]')].every(node=>node.hidden));
        assert.equal(await product.locator('.st-hotel-card').count(),4);
        row.proof.explicitSearchRetryCount=1;row.proof.retryRows=4;
        await product.locator('[data-favorite="1"]').click();
        assert.deepEqual(await page.evaluate(()=>JSON.parse(localStorage.getItem('anytour.prototype.v18.favorites.v1'))),[1],'Existing favorite key preserved');
        row.proof.favoriteStoragePreserved=true;

        // Retained forty-pair corpus: browsing and responsive return remain
        // local; only selecting the last exact pair invokes selectFlight.
        const manyBefore=await page.evaluate(()=>{const c=window.__nativeFixture;return{search:c.calls.search,quote:c.calls.quote,flights:c.calls.flight.length};});
        await page.evaluate(()=>window.__nativeFixture.installManyFlights());
        await product.locator('[data-hotel-open="1"]').first().click();await screen('hotel').waitFor({state:'visible'});
        await product.locator('[data-hotel-offers] [data-offer="fixture:many-flight:1"]').click();
        await screen('tour').waitFor({state:'visible'});
        await waitForFixture(page,()=>window.__nativeFixture.getSelection()?.variants.length===40);
        assert.equal(await product.locator('[data-native-tour-flight]:visible').count(),3);
        const manyDisclosure=product.locator('[data-native-flight-alternatives]');
        assert.equal(await manyDisclosure.locator('summary').innerText(),'Ещё перелёты · 37');
        const managerBeforeList=await product.locator('[data-native-defer-flight]').evaluate(node=>{
          const first=node.parentElement.querySelector('[data-native-tour-flight]'),a=node.getBoundingClientRect(),b=first.getBoundingClientRect();
          return{before:a.bottom<=b.top,visible:a.width>0&&a.height>=44,overflow:node.scrollWidth>node.clientWidth+1};
        });
        assert(managerBeforeList.before&&managerBeforeList.visible&&!managerBeforeList.overflow,'Manager action stays above all flight options with a readable44px target');
        const pairLayout=await product.locator('.st-native-flight-facts').first().evaluate(node=>{
          const [outbound,inbound]=[...node.children].map(leg=>leg.getBoundingClientRect());
          return{columns:getComputedStyle(node).gridTemplateColumns.split(' ').length,paired:Math.abs(outbound.top-inbound.top)<1&&outbound.right<=inbound.left,stacked:outbound.bottom<=inbound.top};
        });
        assert.equal(width===1280?pairLayout.paired:pairLayout.stacked,true,'Wide tour keeps both directions side by side; phone and narrow tablet keep their reading order');
        await capture(page,'native',width,percent,'tour-many-flights',row);
        await manyDisclosure.locator('summary').click();
        assert.equal(await product.locator('[data-native-tour-flight]:visible').count(),40);
        const manyPassive=await page.evaluate(()=>{const c=window.__nativeFixture;return{search:c.calls.search,quote:c.calls.quote,flights:c.calls.flight.length};});
        assert.deepEqual(manyPassive,{search:manyBefore.search,quote:manyBefore.quote+1,flights:manyBefore.flights},'Opening the remaining37 pairs creates no extra operation');
        await product.locator('[data-native-tour-flight="39"]').click();
        await waitForFixture(page,()=>window.__nativeFixture.getSelection()?.flightChoiceId===39);
        assert.equal(await manyDisclosure.locator('summary').innerText(),'Другие перелёты · 39');
        assert.equal(await product.locator('[data-native-tour-flight]:visible').count(),0);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().total),151000);
        await product.locator('[data-action="accept-price"]:visible').first().click();
        await product.locator('[data-action="lead"]:visible').first().click();await screen('lead').waitFor({state:'visible'});
        await page.goBack();await screen('tour').waitFor({state:'visible'});
        assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().flightChoiceId),39);
        assert.equal(await manyDisclosure.getAttribute('open'),null);
        assert.deepEqual(await page.evaluate(()=>{const c=window.__nativeFixture;return{search:c.calls.search,quote:c.calls.quote,flights:c.calls.flight.length};}),{search:manyBefore.search,quote:manyBefore.quote+1,flights:manyBefore.flights+1},'Contact and Back retain the exact last pair without a quote or flight replay');
        row.proof.manyFlightChoices={total:40,initiallyVisible:3,collapsed:37,selectedCanonicalIndex:39,selectedTotal:151000,managerBeforeList,pairLayout,passiveExtraOperations:0};
        await product.locator('[data-native-defer-flight]').click();
        await product.locator('[data-action="accept-price"]:visible').first().click();
        await product.locator('[data-action="lead"]:visible').first().click();await screen('lead').waitFor({state:'visible'});
        assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().flightChoiceId),null);assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().total),110000);
        if(width<=760)await product.locator('[data-native-contact-facts] > summary').click();
        assert.match(await product.locator('[data-native-contact-flight]').innerText(),/Рейс уточнит менеджер/);assert.doesNotMatch(await product.locator('[data-native-contact-flight]').innerText(),/FX /);
        assert.match(await product.locator('[data-native-contact-note]').innerText(),/Цена предложения проверена\. Рейс и доплаты уточнит менеджер/);
        assert.equal(await form.locator('[name="consent"]').isChecked(),false,'Changing the flight decision revokes prior consent');
        await capture(page,'native',width,percent,'lead-manager',row);
        if(width<=760)await product.locator('[data-native-contact-facts] > summary').click();
        row.proof.managerContact={total:110000,flightChoiceId:null,oldPairRemoved:true,consentRevoked:true};
        await page.goBack();await screen('tour').waitFor({state:'visible'});
        await page.goBack();await screen('hotel').waitFor({state:'visible'});
        await page.goBack();await screen('results').waitFor({state:'visible'});

        // Exercise the new group route after all paired captures. Its held
        // fixture proves that opening a hotel is passive, only the explicit
        // hotel CTA expands, and responsive mounts join that same operation.
        const groupBefore=await page.evaluate(()=>{const c=window.__nativeFixture;return{search:c.calls.search,quote:c.calls.quote,expand:c.calls.expand};});
        await page.evaluate(()=>window.__nativeFixture.installGroup());
        await product.locator('[data-hotel-card="901"] [data-offer="fixture:group:901"]').click();
        await screen('hotel').waitFor({state:'visible'});
        assert.equal(await screen('tour').isVisible(),false,'A group minimum never opens a preliminary tour screen');
        const groupCTA=product.locator('[data-native-expand-group="fixture:group:901"]');
        assert.equal(await groupCTA.count(),1,'Exactly one explicit group-expansion action');
        assert.equal(await groupCTA.innerText(),'Варианты');
        assert.equal(await groupCTA.getAttribute('aria-label'),'Показать точные варианты этого предложения');
        assert.equal(await groupCTA.evaluate(node=>node===document.activeElement),true,'The group card focuses its exact hotel action');
        assert.deepEqual(await page.evaluate(()=>{const c=window.__nativeFixture;return{search:c.calls.search,quote:c.calls.quote,expand:c.calls.expand};}),groupBefore,'Opening group hotel starts no search, expansion or quote');
        const groupActionFit=await groupCTA.evaluate(node=>{
          const box=node.getBoundingClientRect(),row=node.closest('.st-offer').getBoundingClientRect(),doc=document.documentElement;
          const range=document.createRange();range.selectNodeContents(node);
          return{height:box.height,contained:box.left>=row.left-1&&box.right<=row.right+1&&box.top>=row.top-1&&box.bottom<=row.bottom+1,
            textContained:[...range.getClientRects()].every(rect=>rect.left>=box.left-1&&rect.right<=box.right+1&&rect.top>=box.top-1&&rect.bottom<=box.bottom+1),
            documentOverflow:doc.scrollWidth>doc.clientWidth+1};
        });
        assert(groupActionFit.height>=43.5&&groupActionFit.contained&&groupActionFit.textContained&&!groupActionFit.documentOverflow,'The exact-variant CTA is readable and reachable at the current ordinary width');
        await groupCTA.press('Enter');
        await waitForFixture(page,()=>window.__nativeFixture.calls.expand===1&&typeof window.__nativeFixture.releaseGroupExpansion==='function');
        assert.equal(await groupCTA.isDisabled(),true);
        assert.match(await product.locator('[data-hotel-offers]').innerText(),/Получаем точные варианты/);
        const groupStatus=product.locator('[data-native-group-status="fixture:group:901"]');
        assert.equal(await groupStatus.getAttribute('tabindex'),'-1');assert.equal(await groupStatus.getAttribute('role'),'status');
        assert.equal(await groupStatus.evaluate(node=>node===document.activeElement),true,'The disabled expansion action hands keyboard focus to its pending status');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.quote),groupBefore.quote,'Pending group expansion never quotes a minimum');
        const oppositeWidth=width<=760?768:390;
        const resizeGroupHotel=async responsiveWidth=>{
          await page.setViewportSize({width:responsiveWidth,height:900});
          await waitForFixture(page,()=>{
            const root=[...document.querySelectorAll('[data-product]')].find(node=>!node.closest('[hidden]'));
            return root&&root.classList.contains('st-mobile')===(innerWidth<=760)&&!root.querySelector('[data-screen="hotel"]').hidden&&document.querySelector('[data-native-startup]').hidden;
          });
        };
        for(const responsiveWidth of [oppositeWidth,width]){
          await resizeGroupHotel(responsiveWidth);
          assert.equal(await groupCTA.isDisabled(),true,'Responsive remount retains the pending explicit operation');
          assert.equal(await groupStatus.evaluate(node=>node===document.activeElement),true,'Responsive remount restores pending-status focus');
          assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.expand),1,'Responsive remount never repeats expansion');
          assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.quote),groupBefore.quote);
        }
        await page.evaluate(()=>window.__nativeFixture.releaseGroupExpansion());
        const exactGroupOffer=product.locator('[data-hotel-offers] [data-offer="fixture:concrete:901:0"]');
        await exactGroupOffer.waitFor({state:'visible'});
        await waitForFixture(page,()=>document.activeElement?.getAttribute('data-offer')==='fixture:concrete:901:0');
        assert.equal(await groupCTA.count(),0,'A resolved minimum is replaced by its concrete choices');
        assert.equal(await product.locator('[data-hotel-offers] [data-offer]').count(),2);
        assert.equal(await product.locator('[data-hotel-min-price]').innerText(),'161 400 ₽','The hotel minimum describes its visible concrete choices, without the hidden lower group minimum');
        assert.deepEqual(await product.locator('[data-hotel-offers] .st-offer-price').allInnerTexts(),['161 400 ₽','170 400 ₽']);
        assert.deepEqual(await product.locator('[data-hotel-offers] .st-offer-delta').allInnerTexts(),['Минимальная цена','+9 000 ₽'],'Displayed deltas use the minimum of the visible concrete list');
        assert.equal(await page.evaluate(()=>{const g=window.__nativeFixture.group;return g.expectedReferenceRetained&&g.offers.every(offer=>g.hotel.offers.includes(offer));}),true,'Expansion binds the exact current minimum and returns exact canonical concrete objects');
        await exactGroupOffer.press('Enter');await screen('tour').waitFor({state:'visible'});
        await waitForFixture(page,()=>window.__nativeFixture.getSelection()?.offer===window.__nativeFixture.group.offers[0]);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.quote),groupBefore.quote+1,'Only choosing a concrete variant authorizes one quote');
        assert.match(await screen('tour').innerText(),/Точный стандартный номер/);
        await page.goBack();await screen('hotel').waitFor({state:'visible'});
        assert.equal(await groupCTA.count(),0,'Back retains resolved concrete variants');
        assert.equal(await product.locator('[data-hotel-offers] .st-offer--selected [data-offer="fixture:concrete:901:0"]').count(),1);
        for(const responsiveWidth of [oppositeWidth,width]){
          await resizeGroupHotel(responsiveWidth);
          assert.equal(await groupCTA.count(),0,'Responsive remount retains resolved concrete objects');
          assert.equal(await product.locator('[data-hotel-offers] .st-offer--selected [data-offer="fixture:concrete:901:0"]').count(),1);
          assert.equal(await exactGroupOffer.evaluate(node=>node===document.activeElement),true,'Responsive remount restores focus to the exact selected concrete choice');
        }
        // The approved mobile hotel has no visible desktop summary. Reopen its
        // concrete row there; the desktop summary must choose that same row.
        if(width>760)await product.locator('[data-action="best-tour"]:visible').first().click();
        else await exactGroupOffer.click();
        await screen('tour').waitFor({state:'visible'});
        assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection()?.offer===window.__nativeFixture.group.offers[0]),true,'The visible hotel action opens its displayed concrete minimum directly');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.quote),groupBefore.quote+1,'Reopening reuses the exact checked concrete offer');
        await page.goBack();await screen('hotel').waitFor({state:'visible'});
        await exactGroupOffer.click();await screen('tour').waitFor({state:'visible'});
        await page.goBack();await screen('hotel').waitFor({state:'visible'});
        await page.goForward();await screen('tour').waitFor({state:'visible'});
        const groupAfter=await page.evaluate(()=>{const c=window.__nativeFixture;return{search:c.calls.search,quote:c.calls.quote,expand:c.calls.expand,exactSelection:c.getSelection()?.offer===c.group.offers[0]};});
        assert.deepEqual(groupAfter,{search:groupBefore.search,quote:groupBefore.quote+1,expand:1,exactSelection:true},'Back, reopen and responsive mounts keep the exact loaded choice without another operation');
        row.proof.groupMinimumOpenedHotelWithoutQuote=true;row.proof.groupPassiveExtraSearches=0;row.proof.groupPassiveExpansions=0;
        row.proof.groupExplicitExpandCount=1;row.proof.groupExpansionExactReferences=true;row.proof.groupPendingRemountRetained=true;row.proof.groupPendingStatusFocusRetained=true;
        row.proof.groupConcreteQuoteCount=1;row.proof.groupConcreteFocusRetained=true;row.proof.groupBackRemountExtraOperations=0;
        row.proof.groupVisibleConcreteMinimum=161400;row.proof.groupVisibleConcreteDelta=9000;row.proof.groupDirectConcreteReopen=true;
        row.proof.groupReopenAction=width>760?'desktop-summary':'mobile-concrete-row';if(width>760)row.proof.groupSummaryOpensConcreteDirectly=true;
        row.proof.groupActionFit=groupActionFit;
        row.checks.push('group minimum -> hotel passively -> one explicit expansion -> exact concrete offer -> one quote; pending/resolved responsive remount, focus and Back/reopen retain the same objects with zero repeated operations');

        // Continue a deliberately non-minimum concrete offer through every
        // handoff. The existing summary must not replace it with the minimum.
        await page.goBack();await screen('hotel').waitFor({state:'visible'});
        const dearerOffer=product.locator('[data-hotel-offers] [data-offer="fixture:concrete:901:1"]');
        await dearerOffer.click();await screen('tour').waitFor({state:'visible'});
        await waitForFixture(page,()=>window.__nativeFixture.getSelection()?.offer===window.__nativeFixture.group.offers[1]);
        await screen('tour').locator('[data-action="hotel"]').click();await screen('hotel').waitFor({state:'visible'});
        assert.equal(await dearerOffer.evaluate(node=>node===document.activeElement),true,'Return focuses the actual more expensive exact offer');
        assert.equal(await product.locator('[data-hotel-min-price]').innerText(),'170 400 ₽');
        assert.equal(await product.locator('[data-action="best-tour"]').innerText(),'К выбранному туру');
        assert.match(await product.locator('.st-aside-summary .st-included-list').innerText(),/Семейный номер/);
        await capture(page,'native',width,percent,'hotel-selected-dearer',row);
        if(width>760)await product.locator('[data-action="best-tour"]:visible').click();else await dearerOffer.click();
        await screen('tour').waitFor({state:'visible'});
        assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().offer===window.__nativeFixture.group.offers[1]),true);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.quote),groupBefore.quote+2,'Continue reuses the selected dearer quote');
        await product.locator('[data-action="lead"]:visible').first().click();await screen('lead').waitFor({state:'visible'});
        if(width<=760)await product.locator('[data-native-contact-facts] > summary').click();
        assert.match(await product.locator('[data-lead-choice]').innerText(),/Семейный номер/);assert.match(await product.locator('[data-native-contact-flight]').innerText(),/FICTIONAL outbound/);
        await capture(page,'native',width,percent,'lead-selected-dearer',row);
        if(width<=760)await product.locator('[data-native-contact-facts] > summary').click();
        const selectedBefore=await page.evaluate(()=>({quote:window.__nativeFixture.calls.quote,search:window.__nativeFixture.calls.search}));
        await screen('lead').locator('[data-action="tour"]').click();await screen('tour').waitFor({state:'visible'});
        await product.locator('[data-action="lead"]:visible').first().click();await screen('lead').waitFor({state:'visible'});
        assert.deepEqual(await page.evaluate(()=>({quote:window.__nativeFixture.calls.quote,search:window.__nativeFixture.calls.search})),selectedBefore);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().total),170400);
        row.proof.nonMinimumJourney={exactOfferIndex:1,total:170400,summaryAndContinueRetained:true,contactItineraryRetained:true,backReopenExtraOperations:0};

        // The mobile quick action is absent from desktop layout. A responsive
        // remount must recover visible focus without another supplier action.
        await screen('lead').locator('[data-action="tour"]').click();await screen('tour').waitFor({state:'visible'});
        const focusOperations=await page.evaluate(()=>{const c=window.__nativeFixture.calls;return{search:c.search,expand:c.expand,quote:c.quote};});
        const resizeTour=async responsiveWidth=>{
          await page.setViewportSize({width:responsiveWidth,height:900});
          await waitForFixture(page,()=>{
            const root=[...document.querySelectorAll('[data-product]')].find(node=>!node.closest('[hidden]'));
            return root&&root.classList.contains('st-mobile')===(innerWidth<=760)&&!root.querySelector('[data-screen="tour"]').hidden&&document.querySelector('[data-native-startup]').hidden;
          });
        };
        await resizeTour(390);await product.locator('.st-quick-tour [data-action="lead"]').focus();
        await resizeTour(1280);
        assert.equal(await product.locator('.st-quick-tour [data-action="lead"]').isVisible(),false);
        assert.equal(await screen('tour').locator('h1').evaluate(node=>node===document.activeElement&&node.getBoundingClientRect().height>0),true,'A hidden mobile initiating control falls back to the visible desktop tour heading');
        await resizeTour(width);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection()?.offer===window.__nativeFixture.group.offers[1]),true);
        assert.deepEqual(await page.evaluate(()=>{const c=window.__nativeFixture.calls;return{search:c.search,expand:c.expand,quote:c.quote};}),focusOperations);
        row.proof.screenNavigation.responsiveHiddenFocusFallback=true;
        row.proof.screenNavigation.responsiveRemountExtraOperations=0;

        // Same retained facts as the installed AI audit, explicitly fictional
        // supplier responses. Exercise long identity, changed full total and
        // consistent room/tour/contact price provenance at every width.
        await screen('tour').locator('[data-action="hotel"]').click();await screen('hotel').waitFor({state:'visible'});
        await product.locator('[data-screen="hotel"] [data-action="results"]').click();await screen('results').waitFor({state:'visible'});
        await page.evaluate(()=>window.__nativeFixture.installAI());
        await product.locator('[data-hotel-open="902"]').first().click();await screen('hotel').waitFor({state:'visible'});
        await product.locator('[data-hotel-offers] [data-offer="fixture:ai:sea"]').click();await screen('tour').waitFor({state:'visible'});
        await product.locator('[data-native-tour-flight="0"]').click();
        await waitForFixture(page,()=>window.__nativeFixture.getSelection()?.total===163056);
        assert.equal(await product.locator('[data-action="lead"]:visible').first().isDisabled(),true);
        assert.equal(await product.locator('[data-tour-meal]:visible,[data-quick-meal]:visible').first().innerText(),'Всё включено');
        assert.match(await product.locator('[data-native-price-origin]:visible').first().innerText(),/138 989 ₽/);
        await capture(page,'native',width,percent,'ai-price-changed',row);
        await product.locator('[data-action="accept-price"]:visible').first().click();
        await screen('tour').locator('[data-action="hotel"]').click();await screen('hotel').waitFor({state:'visible'});
        const selectedAI=product.locator('.st-offer--selected');
        assert.equal(await selectedAI.locator('.st-offer-price').innerText(),'163 056 ₽');
        assert.equal(await selectedAI.locator('.st-offer-price-caption').innerText(),'С выбранным перелётом');
        assert.match(await selectedAI.locator('.st-offer-delta').innerText(),/138 989 ₽/);
        await capture(page,'native',width,percent,'ai-hotel-checked',row);
        if(width>760)await product.locator('[data-action="best-tour"]:visible').click();else await selectedAI.locator('[data-offer]').click();
        await screen('tour').waitFor({state:'visible'});await product.locator('[data-action="lead"]:visible').first().click();await screen('lead').waitFor({state:'visible'});
        assert.match(await product.locator('[data-lead-choice]').innerText(),/standard room sea view · Всё включено/);
        assert.equal(await screen('lead').locator('[data-tour-price]').innerText(),'163 056 ₽');
        const contactDetails=product.locator('[data-native-contact-facts]');
        assert.equal(await contactDetails.getAttribute('open'),width<=760?null:'');
        const detailCalls=await page.evaluate(()=>{const c=window.__nativeFixture.calls;return{search:c.search,quote:c.quote,flight:[...c.flight]};});
        if(width<=760)await contactDetails.locator('summary').click();
        assert.equal(await product.locator('[data-native-contact-flight]').isVisible(),true);
        if(width<=760)await contactDetails.locator('summary').click();
        assert.deepEqual(await page.evaluate(()=>{const c=window.__nativeFixture.calls;return{search:c.search,quote:c.quote,flight:[...c.flight]};}),detailCalls);
        row.proof.contactDisclosure={initiallyCollapsed:width<=760,expandedFactsVisible:true,passiveExtraOperations:0};
        const aiFit=await product.locator('[data-native-contact-summary]').evaluate(summary=>{
          const title=summary.querySelector('[data-lead-hotel]'),form=summary.parentElement.querySelector('[data-lead-form]'),box=summary.getBoundingClientRect(),titleBox=title.getBoundingClientRect(),formBox=form.getBoundingClientRect();
          return {width:box.width,titleWidth:titleBox.width,titleLines:Math.round(titleBox.height/parseFloat(getComputedStyle(title).lineHeight)),titleFits:title.scrollWidth<=title.clientWidth+1,summaryFits:summary.scrollWidth<=summary.clientWidth+1,columns:formBox.left>=box.right-1};
        });
        assert(aiFit.titleFits&&aiFit.summaryFits,'The long AI hotel identity and complete selected summary fit');
        if(width===1280)assert(aiFit.width>=450&&aiFit.titleWidth>=330&&aiFit.titleLines<=3&&aiFit.columns,'Desktop gives the chosen tour enough width beside the form');
        await capture(page,'native',width,percent,'ai-contact',row);
        const aiCalls=await page.evaluate(()=>{const c=window.__nativeFixture.calls;return{search:c.search,quote:c.quote,flight:[...c.flight]};});
        await page.goBack();await screen('tour').waitFor({state:'visible'});await page.goBack();await screen('hotel').waitFor({state:'visible'});
        await page.goForward();await screen('tour').waitFor({state:'visible'});await page.goForward();await screen('lead').waitFor({state:'visible'});
        assert.deepEqual(await page.evaluate(()=>{const c=window.__nativeFixture.calls;return{search:c.search,quote:c.quote,flight:[...c.flight]};}),aiCalls);
        assert.equal(await screen('lead').locator('[data-tour-price]').innerText(),'163 056 ₽');
        row.proof.aiPriceContact={searchPrice:138989,checkedFullTotal:163056,readableMeal:true,originalIndex:0,explicitAcceptance:true,selectedRowPriceRetained:true,backForwardExtraOperations:0,contactFit:aiFit};

        // Revisit the controlled native entry with its actual compiled bridge.
        // The previous 393 frames/proofs remain untouched by this extra journey.
        terminalJourney=true;
        const params=new URLSearchParams({...fixture.trip,ages:''});
        await page.goto(origin+base+'/visual-search/?'+params);
        await page.locator('[data-native-startup]').waitFor({state:'hidden'});
        const actualProduct=page.locator('[data-product]:visible');
        assert.equal(await page.evaluate(()=>window.__nativeTerminalFixture.calls.search),0);
        await actualProduct.locator('[data-screen="search"] button[type="submit"]').click();
        await actualProduct.locator('[data-screen="results"]').waitFor({state:'visible'});
        for(const kind of ['complete','partial','error']){
          const retained=await page.evaluate(kind=>{const control=window.__nativeTerminalFixture;control.terminal(kind);return control.refresh();},kind);
          assert.equal(retained.before,kind);assert.equal(retained.after,kind);
          assert.equal(retained.messageAfter,retained.messageBefore);
          assert.equal(retained.generationRetained,true);assert.equal(retained.exactOffersRetained,true);
          assert.deepEqual(retained.ids,[1,2,3,4]);assert.deepEqual(retained.totals,[156400,149900,98700,1059000]);
          assert.equal(retained.searches,1);assert.equal(retained.other,0);
          const alert=actualProduct.locator('[data-source-alert]');
          assert.equal(await alert.isVisible(),kind!=='complete');
          if(kind==='partial')assert.equal(await alert.innerText(),'Поиск завершён не полностью\nПоказаны предложения: ANEX. Не все источники завершили поиск: Tourvisor и Andromeda.');
          if(kind==='error'){
            assert.match(await alert.innerText(),/^Не удалось завершить поиск\nПолученные предложения сохранены/);
            assert.equal((await alert.innerText()).includes(retained.messageBefore),false,'Terminal bridge detail is not copied into UI');
          }
          assert.equal(await actualProduct.locator('[data-result-count]').innerText(),'4 отеля');
          await capture(page,'native',width,percent,'terminal-'+kind,row);
        }
        row.proof.latePresentationTerminalStatesRetained=true;row.proof.latePresentationExtraSearches=0;
        row.checks.push('passive init 0 searches','form submit exactly one search','favorite state and keyboard focus retained locally with 0 searches','loaded 14-day calendar pages locally with exact later total and retained history','local loaded meal filter 0 extra searches','hotel -> exact offer -> quote -> original flight index -> full price -> accepted contact preview','real leads 0','Back/Forward retained selection and 0 searches','controlled error retained rows and explicit retry','existing favorite storage retained');
      } catch(error) {
        row.mediaChanges=await page.evaluate(()=>window.__nativeMediaChanges).catch(()=>[]);row.error=error.stack||String(error);report.failures.push({width,percent,error:error.message});
        try{await page.screenshot({path:path.join(evidence,'failure-'+width+'-text'+percent+'.png'),fullPage:true});}catch{}
      } finally {
        if(row.errors.length)report.failures.push({width,percent,error:'Page errors',details:row.errors});
        if(row.unexpectedRequests.length)report.failures.push({width,percent,error:'Unexpected external/API/lead request',details:row.unexpectedRequests});
        await context.close();
      }
    }
    report.status=report.failures.length?'failed':'passed';
    fs.writeFileSync(path.join(evidence,'acceptance.json'),JSON.stringify(report,null,2)+'\n');
    assert.equal(report.failures.length,0,'Native rendered acceptance failures: '+JSON.stringify(report.failures));
    console.log('PASS exact-head native controlled journey and donor/native rendered pairs: 360/390/430/768/1280, ordinary text100%; suppliers real0, leads real0. Visual owner review and public readback remain separate.');
  } finally {
    await browser.close();await new Promise(resolve=>server.close(resolve));
  }
})().catch(error=>{report.status='failed';fs.writeFileSync(path.join(evidence,'acceptance.json'),JSON.stringify(report,null,2)+'\n');console.error(error);process.exitCode=1;});
