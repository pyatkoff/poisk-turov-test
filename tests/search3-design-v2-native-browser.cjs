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
  trip:{origin:'Москва',country:'4',from:'2026-10-12',to:'2026-10-18',minNights:7,maxNights:7,adults:2,ages:[]},
  hotels:[
    {id:1,donorId:'laguna',name:'FIXTURE Laguna Beach Resort',stars:5,room:'Стандартный номер',meal:'Ультра всё включено',food:'uai',total:156400},
    {id:2,donorId:'marina',name:'FIXTURE Marina Bay Hotel',stars:4,room:'Стандартный номер',meal:'Завтраки и ужины',food:'hb',total:149900},
    {id:3,donorId:'garden',name:'FIXTURE Seaside Garden Hotel & Residence',stars:3,room:'Стандартный номер',meal:'Завтраки',food:'bb',total:98700},
    {id:4,donorId:'villas',name:'FIXTURE Laguna Collection Premium Resort & Private Villas',stars:5,room:'Вилла с двумя спальнями · свой бассейн',meal:'Ультра всё включено',food:'uai',total:1059000}
  ]
};
fs.writeFileSync(path.join(evidence,'fixture.json'),JSON.stringify(fixture,null,2)+'\n');

function fixtureBridge(fixture) {
  const trip=fixture.trip, calls={init:0,search:0,quote:0,flight:[],preview:0,submit:0};
  const rawHotels=fixture.hotels.map(row=>({id:row.id,name:row.name,country:'4',location:'Турция · Кемер',region:'Кемер',stars:row.stars,photos:[],amenities:[],raw:{description:'FICTIONAL fixture hotel description'},offers:[{
    key:'fixture:offer:'+row.id,hotelId:row.id,day:trip.from,returnDay:'2026-10-19',nights:7,adults:2,ages:[],room:row.room,meal:row.meal,mealFacet:row.meal,operator:'FIXTURE TV',provider:'tourvisor',total:row.total,search:trip,raw:{fixture:true}
  }]}));
  const emptyFilters=()=>({hotelId:0,hotelIds:[],q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],amenities:[],min:0,max:null,rating:false,beach:false,family:false,spa:false});
  const dictionaries={origins:['Москва','Калининград'],countries:[{id:'4',name:'Турция'},{id:'1',name:'Египет'}],meals:fixture.hotels.map((row,i)=>({id:i+1,nameRu:row.meal,code:row.food,nativeIds:[]}))};
  let selection=null,latest={hotels:[],status:'idle'},generation=0;
  const listeners=new Set();
  const publish=value=>{latest=value;for(const listener of listeners)listener(value);return value;};
  const leg=(back=false)=>({company:{name:'FIXTURE AIR'},number:back?'FX 102':'FX 101',departure:{date:back?'2026-10-19':trip.from,time:back?'13:00':'09:20',port:{name:back?'FIXTURE Antalya':'FIXTURE Moscow'}},arrival:{date:back?'2026-10-19':trip.from,time:back?'17:00':'13:20',port:{name:back?'FIXTURE Moscow':'FIXTURE Antalya'}},baggage:20,carryOn:'5 кг'});
  const control=window.__nativeFixture={calls,hotels:rawHotels,failNextSearch:false,failNextQuote:false,getSelection:()=>selection};
  control.deliverHotels=hotels=>publish({...latest,hotels,search:{...trip,ages:[]}});
  const api={
    async init(){calls.init++;return{search:{...trip,ages:[]},filters:emptyFilters(),dictionaries,hotels:[],status:'idle',requestedSearch:new URLSearchParams(location.search).get('searched')==='1'};},
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
    async quoteOffer(key){
      calls.quote++;
      const offer=rawHotels.flatMap(row=>row.offers).find(row=>row.key===key);
      if(!offer)throw new Error('Unknown fixture offer');
      if(control.failNextQuote){control.failNextQuote=false;throw new Error('FICTIONAL controlled quote failure');}
      selection={...offer,offer,tour:{id:'fixture-tour:'+offer.hotelId,price:110000,roomType:offer.room,meal:{name:offer.meal}},total:110000,variants:[{price:112000,forward:[leg()],backward:[leg(true)]},{price:123000,forward:[{...leg(),departure:{...leg().departure,time:'10:20'}}],backward:[leg(true)]}],flightChoiceId:null,pricePending:true,priceAccepted:false,expiresAt:Math.floor(Date.now()/1000)+600};
      return selection;
    },
    selectFlight(index){if(!selection||!selection.variants[index])throw new Error('Invalid fixture variant');calls.flight.push(index);selection={...selection,total:selection.variants[index].price,flightChoiceId:index,pricePending:false,priceAccepted:false};return selection;},
    deferFlight(){selection={...selection,total:selection.tour.price,flightChoiceId:null,pricePending:false,priceAccepted:false};return selection;},
    acceptPrice(){if(!selection||selection.pricePending)throw new Error('Fixture price pending');selection={...selection,priceAccepted:true};return selection;},
    previewContact(fields){
      if(!selection||!selection.priceAccepted||selection.pricePending)throw new Error('Fixture selection has no authority');
      if(!fields.get('consent'))throw new Error('Fixture consent required');
      calls.preview++;const value={fixture:true,price:selection.total,flightChoiceId:selection.flightChoiceId,tourId:selection.tour.id,name:fields.get('name'),phone:fields.get('phone')};control.payload=value;return value;
    }
  };
  window.AnyTourDesignV2Bridge={create:()=>api};
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
  requiredWidths:[360,390,430,768,1280],requiredTextPercents:[100,200],expectedPairedScreens:['search','results','hotel','tour','lead'],
  compiledNativeManifest:manifest,scope:'controlled compiled UI, not live supplier/physical/publication acceptance',
  donorAdapter:'exact original renderer and CSS; test-only controlled hotels, loaded-date-price and selected-price/flight-fact adapters',
  text200Method:'each original computed text font size is doubled; layout dimensions and browser zoom stay unchanged',
  knownSemanticDifferences:['Native package inclusions remain unknown until verified; donor demo inclusion copy is reference only.','Native quote/flight/contact authority comes from controlled bridge fixtures; donor demo authority is not transferred.'],
  cases:[],failures:[]};

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

async function capture(page,side,width,percent,screen,row) {
  if(side==='native')await page.waitForFunction(()=>window.__nativeFixture?.getSnapshot?.().status!=='loading');
  await page.evaluate(()=>document.fonts.ready);
  await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
  await setTextSize(page,percent);
  await page.evaluate(()=>window.scrollTo(0,0));
  const file=side+'-'+screen+'-'+width+'-text'+percent+'.png';
  await page.screenshot({path:path.join(evidence,file),fullPage:true,animations:'disabled'});
  const measurements=await page.evaluate(percent=>{
    const root=[...document.querySelectorAll('[data-product]')].find(node=>!node.closest('[hidden]'));
    const stage=root?.closest('.st-stage');
    const box=selector=>{const element=root?.querySelector(selector);if(!element||element.closest('[hidden]'))return null;const r=element.getBoundingClientRect(),style=getComputedStyle(element);return{x:r.x,y:r.y,width:r.width,height:r.height,fontSize:style.fontSize,visible:r.width>0&&r.height>0};};
    const doc=document.documentElement;
    const prices=[...root.querySelectorAll('.st-price,[data-tour-price],.st-offer-price')].filter(node=>!node.closest('[hidden]')&&node.getBoundingClientRect().width>0).map(node=>{
      let fragmented=false;const walker=document.createTreeWalker(node,NodeFilter.SHOW_TEXT);let text;
      while((text=walker.nextNode()))for(const match of text.textContent.matchAll(/\d+/g)){const range=document.createRange();range.setStart(text,match.index);range.setEnd(text,match.index+match[0].length);if([...range.getClientRects()].filter(rect=>rect.width>0&&rect.height>0).length>1)fragmented=true;}
      const fontSize=parseFloat(getComputedStyle(node).fontSize),baseline=window.__nativeTextScaleBaseline?.get(window.__nativeTextScaleKey(node));
      return{text:node.textContent,width:node.getBoundingClientRect().width,clientWidth:node.clientWidth,scrollWidth:node.scrollWidth,overflow:node.scrollWidth>node.clientWidth+1,fragmented,fontSize,baselineFontSize:percent===200?baseline:fontSize,textScaleVerified:percent!==200||Number.isFinite(baseline)&&Math.abs(fontSize-baseline*2)<0.5};
    });
    const buttons=[...root.querySelectorAll('button.q8c2')].filter(node=>!node.closest('[hidden]')&&node.getBoundingClientRect().width>0).map(node=>{
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
    const readableText=[...root.querySelectorAll('.st-search-part span,.st-search-part b,.st-condition-category .st-condition-label,.st-condition-category .q8c8,.st-flight-row b,.st-header-nav span,.st-footer button,.st-filter-heading h2,.st-filter-heading button,.st-conditions-meal,.st-conditions-operator,.st-cost-list b')].filter(node=>!node.closest('[hidden]')&&node.getBoundingClientRect().width>0).map(node=>{
      const box=node.getBoundingClientRect(),walker=document.createTreeWalker(node,NodeFilter.SHOW_TEXT);let text,brokenWords=false,clipped=false;
      while((text=walker.nextNode()))if(text.textContent.trim()&&!text.parentElement.closest('svg')){
        const range=document.createRange();range.selectNodeContents(text);for(const rect of range.getClientRects())if(rect.width>0&&rect.height>0){
          if(rect.left<box.left-1||rect.right>box.right+1)clipped=true;
          // Glyph ink may extend vertically beyond its line box without clipping.
          // Vertical bounds matter only at an ancestor that actually clips paint.
          for(let ancestor=node;ancestor&&root.contains(ancestor);ancestor=ancestor.parentElement){const style=getComputedStyle(ancestor),bounds=ancestor.getBoundingClientRect();if(/^(hidden|clip|scroll|auto)$/.test(style.overflowY)&&(rect.top<bounds.top-1||rect.bottom>bounds.bottom+1))clipped=true;}
        }
        for(const match of text.textContent.matchAll(/[\p{L}\p{N}:★]+/gu)){range.setStart(text,match.index);range.setEnd(text,match.index+match[0].length);if([...range.getClientRects()].filter(rect=>rect.width>0&&rect.height>0).length>1)brokenWords=true;}
      }
      return{text:node.textContent,clipped,brokenWords};
    });
    const header=root.querySelector('.st-header'),headerBounds=header.getBoundingClientRect(),headerItems=[...header.querySelectorAll('.st-logo,.st-header-nav span,.st-fav-top')].map(node=>node.getBoundingClientRect()).filter(rect=>rect.width>0&&rect.height>0);
    const headerFit={outside:headerItems.some(rect=>rect.left<headerBounds.left-1||rect.right>headerBounds.right+1||rect.top<headerBounds.top-1||rect.bottom>headerBounds.bottom+1),overlap:headerItems.some((a,i)=>headerItems.slice(i+1).some(b=>Math.min(a.right,b.right)>Math.max(a.left,b.left)+1&&Math.min(a.bottom,b.bottom)>Math.max(a.top,b.top)+1))};
    const sort=root.querySelector('.st-sort');let sortFit=null;
    if(sort&&!sort.closest('[hidden]')&&sort.getBoundingClientRect().width>0){const style=getComputedStyle(sort),context=document.createElement('canvas').getContext('2d');context.font=style.fontWeight+' '+style.fontSize+' '+style.fontFamily;const text=sort.selectedOptions[0]?.textContent||'',available=sort.clientWidth-parseFloat(style.paddingLeft)-parseFloat(style.paddingRight)-20;sortFit={text,available,textWidth:context.measureText(text).width};}
    return{viewportWidth:innerWidth,documentWidth:doc.scrollWidth,overflow:doc.scrollWidth>innerWidth+1,stageHidden:stage?.hidden,screen:root?.querySelector('[data-screen]:not([hidden])')?.dataset.screen,prices,buttons,budgetInputs,budgetColumns,readableText,headerFit,sortFit,
      boxes:{header:box('.st-header'),title:box('h1'),sidebar:box('[data-filter-sidebar]'),card:box('.st-hotel-card'),price:box('.st-price'),form:box('[data-search-form]'),tour:box('.st-tour-layout'),contact:box('[data-lead-form]')}};
  },percent);
  row.frames.push({side,screen,textPercent:percent,file,sha256:hash(fs.readFileSync(path.join(evidence,file))),...measurements});
  if(side==='native'&&measurements.overflow)report.failures.push({width,percent,screen,error:'Native document horizontal overflow: '+measurements.documentWidth+' > '+measurements.viewportWidth});
  if(side==='native'&&measurements.prices.some(price=>price.overflow))report.failures.push({width,percent,screen,error:'Full price exceeds its rendered container',details:measurements.prices.filter(price=>price.overflow)});
  if(side==='native'&&measurements.prices.some(price=>price.fragmented||!price.textScaleVerified))report.failures.push({width,percent,screen,error:'Price digits fragment or dynamic text did not receive the requested scale',details:measurements.prices.filter(price=>price.fragmented||!price.textScaleVerified)});
  if(side==='native'&&measurements.buttons.some(button=>button.clipped))report.failures.push({width,percent,screen,error:'Primary action text exceeds its button',details:measurements.buttons.filter(button=>button.clipped)});
  if(side==='native'&&measurements.budgetInputs.some(input=>input.clipped))report.failures.push({width,percent,screen,error:'Budget number or unlimited placeholder is clipped',details:measurements.budgetInputs.filter(input=>input.clipped)});
  if(side==='native'&&width===1280&&percent===100&&measurements.screen==='results'&&measurements.budgetColumns!==2)report.failures.push({width,percent,screen,error:'Normal desktop budget must retain the donor two-column composition'});
  if(side==='native'&&measurements.readableText.some(node=>node.clipped||node.brokenWords))report.failures.push({width,percent,screen,error:'Condition, navigation or flight text clips or splits inside a word',details:measurements.readableText.filter(node=>node.clipped||node.brokenWords)});
  if(side==='native'&&(measurements.headerFit.outside||measurements.headerFit.overlap))report.failures.push({width,percent,screen,error:'Header content overlaps or escapes the header',details:measurements.headerFit});
  if(side==='native'&&measurements.sortFit&&measurements.sortFit.textWidth>measurements.sortFit.available+1)report.failures.push({width,percent,screen,error:'Selected sort label is clipped',details:measurements.sortFit});
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
    for(const width of [360,390,430,768,1280])for(const percent of [100,200]){
      const row={width,textPercent:percent,frames:[],checks:[],errors:[],unexpectedRequests:[],proof:{}};report.cases.push(row);
      const context=await browser.newContext({viewport:{width,height:900},locale:'ru-RU',timezoneId:'UTC',reducedMotion:'reduce'});
      const page=await context.newPage(),reference=await context.newPage();
      page.setDefaultTimeout(6000);reference.setDefaultTimeout(6000);
      page.setDefaultNavigationTimeout(10000);reference.setDefaultNavigationTimeout(10000);
      page.on('pageerror',error=>row.errors.push(error.message));reference.on('pageerror',error=>row.errors.push('donor: '+error.message));
      await context.route('**/*',async route=>{
        const request=route.request(),url=new URL(request.url());
        if(url.origin!==origin||request.method()!=='GET'||/\/(?:api[^/]*|lead-(?:adapter|bridge|receiver)[^/]*)\.(?:php|json)(?:$|\?)/.test(url.pathname)){
          row.unexpectedRequests.push({method:request.method(),url:request.url()});await route.abort();return;
        }
        if(url.pathname===base+'/visual-search/native-v2/bridge.js'){
          await route.fulfill({status:200,contentType:'application/javascript',body:'('+fixtureBridge.toString()+')('+JSON.stringify(fixture)+');'});return;
        }
        // Headless canonical contracts are exercised separately in the same CI
        // job. Browser presentation fixtures have no canonical network startup.
        if(url.pathname.endsWith('.js')&&!url.pathname.startsWith(base+'/visual-search/native-v2/')&&!url.pathname.endsWith('/lead-form-guard-v1.js')){
          await route.fulfill({status:200,contentType:'application/javascript',body:'/* controlled presentation fixture: canonical transport disabled */'});return;
        }
        await route.continue();
      });
      try {
        await Promise.all([page.goto(origin+base+'/visual-search/'),reference.goto(origin+base+'/__donor-reference/')]);
        const product=page.locator('[data-product]:visible');
        const screen=name=>product.locator('[data-screen="'+name+'"]');
        await screen('search').waitFor({state:'visible'});
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),0,'Passive form hydration starts no supplier fixture');
        row.proof.passiveHydrationSearches=0;
        await capture(page,'native',width,percent,'search',row);await referenceFrame(reference,width,percent,'search',row);
        await screen('search').locator('button[type="submit"]').click();
        await screen('results').waitFor({state:'visible'});
        await product.locator('.st-hotel-card').nth(3).waitFor();
        assert.match(await product.locator('[data-mobile-dates]').textContent(),/12–18 окт/,'Same-month range keeps the donor compact copy');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),1);
        row.proof.formSubmitSearches=1;
        await capture(page,'native',width,percent,'results',row);await referenceFrame(reference,width,percent,'results',row);
        const originalStarts=await page.evaluate(()=>window.__nativeFixture.calls.search);
        if(width===390&&percent===100){
          await page.evaluate(()=>window.__nativeFixture.deliverHotels([window.__nativeFixture.hotels[0]]));
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
        assert.equal(await product.locator('.st-hotel-card').count(),2,'Loaded meal filter matches factual fixtures');
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts,'Local filter starts zero supplier requests');
        row.proof.localFilterExtraSearches=0;
        if(width<=760){
          await product.locator('[data-action="filters"]').first().click();
          await product.locator('[data-native-filter="meals"][value="Ультра всё включено"]:visible').first().uncheck();
          await product.locator('[data-action="apply-filters"]').click();
        }else await product.locator('[data-action="reset-filters"]').first().click();
        assert.equal(await product.locator('.st-hotel-card').count(),4);
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
        }else await cap.press('Tab');
        assert.equal(await product.locator('.st-hotel-card').count(),2,'Budget cap uses the full loaded price');
        await capture(page,'native',width,percent,'budget-results',row);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts,'Budget edits and viewport remount start no supplier search');
        row.proof.budgetLoadedCount=2;row.proof.budgetExtraSearches=0;
        if(width<=760){
          await product.locator('[data-action="filters"]').first().click();
          await screen('filters').locator('[data-action="reset-draft"]').click();
          assert.equal(await product.locator('[data-filter-apply]').innerText(),'Показать 4 отеля');
          await product.locator('[data-action="apply-filters"]').click();
        }else await product.locator('[data-action="reset-filters"]').first().click();
        assert.equal(await product.locator('.st-hotel-card').count(),4);
        await product.locator('[data-hotel-open="1"]').first().click();
        await screen('hotel').waitFor({state:'visible'});
        await capture(page,'native',width,percent,'hotel',row);await referenceFrame(reference,width,percent,'hotel',row);
        await product.locator('[data-hotel-offers] [data-offer="fixture:offer:1"]').click();
        await screen('tour').waitFor({state:'visible'});
        if(width<=760)assert.equal(await product.locator('.st-stay-details').isVisible(),false,'Original mobile quick summary replaces duplicate stay details');
        await product.locator('[data-native-tour-flight="0"]').waitFor({state:'visible'});
        await product.locator('[data-native-tour-flight="0"]').click();
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
        await product.locator('[data-action="lead"]:visible').first().click();
        await screen('lead').waitFor({state:'visible'});
        await capture(page,'native',width,percent,'lead',row);await referenceFrame(reference,width,percent,'lead',row);
        const form=product.locator('[data-lead-form]');
        await form.locator('[name="name"]').fill('FICTIONAL QA');await form.locator('[name="phone"]').fill('+7 999 000 00 00');await form.locator('[name="consent"]').check();
        await form.locator('button[type="submit"]').click();
        await page.waitForFunction(()=>[...document.querySelectorAll('[data-lead-form]')].some(form=>form.dataset.checked==='1'));
        assert.deepEqual(await page.evaluate(()=>({price:window.__nativeFixture.payload.price,flightChoiceId:window.__nativeFixture.payload.flightChoiceId,preview:window.__nativeFixture.calls.preview,submit:window.__nativeFixture.calls.submit})),{price:112000,flightChoiceId:0,preview:1,submit:0});
        row.proof.contactPayloadFullTotal=112000;row.proof.contactPayloadFlightChoiceIndex=0;row.proof.contactPreviewCount=1;row.proof.realLeadSubmissions=0;
        await page.goBack();await screen('tour').waitFor({state:'visible'});assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().total),112000);
        await page.goBack();await screen('hotel').waitFor({state:'visible'});
        assert.equal(await product.locator('[data-hotel-offers] .st-offer--selected [data-offer="fixture:offer:1"]').count(),1,'Back highlights only the exact previously selected offer');
        await capture(page,'native',width,percent,'hotel-return',row);
        await page.goForward();await screen('tour').waitFor({state:'visible'});
        await page.goForward();await screen('lead').waitFor({state:'visible'});assert.equal(await page.evaluate(()=>window.__nativeFixture.getSelection().total),112000);
        assert.equal(await page.evaluate(()=>window.__nativeFixture.calls.search),originalStarts,'Render/Back/Forward starts no supplier requests');
        row.proof.backForwardRetainedSelection=true;row.proof.backForwardExtraSearches=0;
        await product.locator('.st-footer [data-action="search"]').click();await screen('search').waitFor({state:'visible'});
        await page.evaluate(()=>{window.__nativeFixture.failNextSearch=true;});
        await screen('search').locator('button[type="submit"]').click();
        await page.waitForFunction(()=>[...document.querySelectorAll('[data-source-alert]')].some(node=>!node.hidden&&node.textContent.includes('FICTIONAL controlled search failure')));
        await capture(page,'native',width,percent,'error',row);
        assert.equal(await product.locator('.st-hotel-card').count(),4,'Search failure retains received rows');
        row.proof.errorRetainedRows=4;
        await product.locator('.st-footer [data-action="search"]').click();await screen('search').waitFor({state:'visible'});await screen('search').locator('button[type="submit"]').click();
        await page.waitForFunction(()=>window.__nativeFixture.calls.search===3);
        await page.waitForFunction(()=>[...document.querySelectorAll('[data-source-alert]')].every(node=>node.hidden||!node.textContent.includes('FICTIONAL controlled search failure')));
        assert.equal(await product.locator('.st-hotel-card').count(),4);
        row.proof.explicitSearchRetryCount=1;row.proof.retryRows=4;
        await product.locator('[data-favorite="1"]').click();
        assert.deepEqual(await page.evaluate(()=>JSON.parse(localStorage.getItem('anytour.prototype.v18.favorites.v1'))),[1],'Existing favorite key preserved');
        row.proof.favoriteStoragePreserved=true;
        row.checks.push('passive init 0 searches','form submit exactly one search','local loaded meal filter 0 extra searches','hotel -> exact offer -> quote -> original flight index -> full price -> accepted contact preview','real leads 0','Back/Forward retained selection and 0 searches','controlled error retained rows and explicit retry','existing favorite storage retained');
      } catch(error) {
        row.error=error.stack||String(error);report.failures.push({width,percent,error:error.message});
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
    console.log('PASS exact-head native controlled journey and donor/native rendered pairs: 360/390/430/768/1280, text 100/200%; suppliers real0, leads real0. Visual owner review and public readback remain separate.');
  } finally {
    await browser.close();await new Promise(resolve=>server.close(resolve));
  }
})().catch(error=>{report.status='failed';fs.writeFileSync(path.join(evidence,'acceptance.json'),JSON.stringify(report,null,2)+'\n');console.error(error);process.exitCode=1;});
