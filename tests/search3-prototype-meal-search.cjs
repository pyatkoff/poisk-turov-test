'use strict';
// Actual adapter, parser, app predicates and URL bindings; fictional HTTP only.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const read=p=>fs.readFileSync(path.join(__dirname,'../v2',p),'utf8');
const plans=[{id:501,nameRu:'Всё включено',nativeIds:['7']},{id:502,nameRu:'Ультра всё включено',nativeIds:['9']},{id:503,nameRu:'Завтрак',nativeIds:['2']}];
let snapshot={source:'anytour-search-meal-v1',provider:'tourvisor',scopeKey:'global',available:true,revision:'a'.repeat(64),plans},failCatalogue=false;
const calls=[],dbCalls=[],catCalls=[];
const window={location:{href:'https://anytoour.ru/_preview/search3-local-candidate/prototype-search/'},Search3CanonicalProfilesV1:{create:()=>({reset(){}})},V2Runtime:{setSearchId(){},async api(action,params){calls.push({action,params});if(action==='search_start')return{searchId:123};throw Error('Unexpected supplier action '+action);}}};
const c=vm.createContext({window,structuredClone,URL,URLSearchParams,DOMException,setTimeout:()=>1,clearTimeout(){},fetch:async(url,options)=>{
 const input=JSON.parse(options.body);assert.match(url,/search3-local-results-read-v1.php$/);
 if(input.action==='meal_catalog'){catCalls.push(input);assert.deepEqual(input,{action:'meal_catalog',provider:'tourvisor',scopeKey:'global'});return{ok:!failCatalogue,json:async()=>({ok:!failCatalogue,data:structuredClone(snapshot)})};}
 dbCalls.push(input.params);return{ok:true,json:async()=>({ok:true,data:{...storedReply(),scope:{scopeVersion:1,...input.params}}})};
}});
for(const file of ['search3-local-db-provider-v1.js','prototype-search/data.js'])vm.runInContext(read(file),c,{filename:file});
const data=window.AnyTourPrototypeData,parser=window.AnyTourLocalDbProviderV1,trip={origin:'Москва',country:'4',from:'2026-10-01',to:'2026-10-02',minNights:7,maxNights:7,adults:2,ages:[]};
data.catalog.departures.push({id:1,name:'Москва'});data.catalog.countries.push({id:4,name:'Турция'});
const profile={id:4234,anytourHotelId:4234,catalog:'anytour',revision:1,name:'Наш отель',category:5,region:{name:'Курорт'}};
const raw=[{id:'ai',price:110000,meal:{id:7,name:'Arbitrary vendor AI text'}},{id:'uai',price:130000,meal:{id:9,name:'ULTRA (different label)'}},{id:'bb',price:50000,meal:{id:2,name:'Breakfast'}},{id:'unknown',price:1,meal:{name:'Всё включено'}}].map(t=>({...t,provider:'tourvisor',date:trip.from,nights:7}));
function storedReply(){
 return{source:'anytour-db-first-results-v1',scopeVersion:1,scopeDigest:'e'.repeat(64),selectionAuthority:false,hotels:['tourvisor','anex','andromeda'].map((provider,i)=>{
  const hotelId=4234+i,concept={kind:'meal',id:801+i,hotelId,localKey:'exact-'+i,nameRu:'Подробные условия отеля',revision:2};
  return{anytourHotelId:hotelId,hotel:{...profile,id:hotelId},offers:[{provider,legacyHotelId:101+i,price:120000,currency:'RUB',
   listing:{schema_version:1,provider,currency:'RUB',selection_state:'refresh_required',booking_enabled:false,listingPriceReady:true,listingPrice:{amount:'120000',currency:'RUB'},identity:{offer_ref_digest:String(i+1).repeat(64),search_ref_digest:'b'.repeat(64),provider_hotel_ref_digest:'c'.repeat(64)},operator:{raw:'Operator'},tour:{checkin:trip.from,nights:7,party:{adults:2,children:0,child_ages:[]},meal:{raw:'Vendor text'},room:{raw:'Room'},placement:{raw:'DBL'}}},
   stayMatch:{source:'anytour-hotel-stay-v2',exactScope:true,meal:{status:'accepted',canonical:concept}},
   searchMeal:{source:'anytour-search-meal-v1',hotelId,conceptId:concept.id,conceptRevision:2,plan:{id:501,code:'all-inclusive',nameRu:'Всё включено'}}}]};
 })};
}
const app=read('prototype-search/app.js');
const pure=app.slice(app.indexOf('function hotelMatch('),app.indexOf('function updateSearchUI('));
const defaultFilters=()=>({hotelId:0,q:'',stars:[],meals:[],resorts:[],operators:[],flight:[],min:0,max:600000,rating:false,beach:false,family:false,spa:false});
const model={state:{search:trip,filters:defaultFilters(),selectedDate:null,favorites:[],onlyFavorites:false},hotels:[],ratingValue:()=>0};vm.createContext(model);vm.runInContext(pure,model);
async function browserCheck(){
 const http=require('node:http'),{chromium}=require(process.env.PLAYWRIGHT_NODE_PATH||'playwright'),root=path.resolve(__dirname,'../v2'),base='/_preview/search3-local-candidate/';
 const evidence=process.env.SEARCH3_MEAL_EVIDENCE||'/tmp/search3-meal-evidence';fs.mkdirSync(evidence,{recursive:true});
 const server=http.createServer((req,res)=>{
  const pathname=new URL(req.url,'http://localhost').pathname;
  let file=path.resolve(root,pathname.slice(base.length));if(pathname.endsWith('/'))file=path.join(file,'index.html');
  if(!pathname.startsWith(base)||!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);return res.end();}
  res.setHeader('Content-Type',({'.html':'text/html','.js':'text/javascript','.css':'text/css','.svg':'image/svg+xml','.woff2':'font/woff2','.png':'image/png'})[path.extname(file)]||'application/octet-stream');res.end(fs.readFileSync(file));
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 let browser;
 try{
  browser=await chromium.launch({headless:true,...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH?{executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH}:{})});
  for(const width of [390,1440]){
   const ctx=await browser.newContext({viewport:{width,height:900}}),page=await ctx.newPage(),started=[],errors=[];
   let missing=false;
   page.on('pageerror',e=>errors.push(e.message));
   const origin='http://127.0.0.1:'+server.address().port;
   await ctx.route('**/*',async route=>{
    const u=new URL(route.request().url()),json=data=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify(data)});
    if(u.pathname==='/data/departures-v1.php')return json({ok:true,items:[{id:1,name:'Москва'}]});
    if(u.pathname.endsWith('/search3-local-results-read-v1.php')){
     const input=route.request().postDataJSON();
     if(input.action==='meal_catalog')return json({ok:true,data:{source:'anytour-search-meal-v1',provider:'tourvisor',scopeKey:'global',available:true,revision:'a'.repeat(64),plans:[{id:501,nameRu:'Всё включено',nativeIds:['7']},{id:502,nameRu:'Ультра всё включено',nativeIds:missing?[]:['9']},{id:503,nameRu:'Завтрак',nativeIds:['2']}]}});
     return json({ok:true,data:{source:'anytour-db-first-results-v1',scopeVersion:1,scopeDigest:'e'.repeat(64),scope:{scopeVersion:1,...input.params},selectionAuthority:false,hotels:[]}});
    }
    if(u.pathname.endsWith('/hotel-details-read-v1.php')){const ids=u.searchParams.getAll('legacyHotelIds[]');return json({ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:ids,missingLegacyIds:[],items:[profile],links:[{legacyHotelId:101,anytourHotelId:4234}]});}
    if(u.pathname==='/api-v2.php'){
     const action=u.searchParams.get('action');
     if(action==='countries')return json([{id:4,name:'Турция'}]);
     if(action==='search_start'){started.push(Object.fromEntries(u.searchParams));return json({searchId:123});}
     if(action==='search_status')return json({status:'complete',progress:100});
     if(action==='search_results')return json([{id:101,provider:'tourvisor',tours:raw}]);
     throw Error('Unexpected provider action '+action);
    }
    if(u.origin!==origin)throw Error('Unexpected external request '+u.href);
    return route.continue();
   });
   const query=new URLSearchParams({...trip,ages:'',meals:'501|502'});
   await page.goto(origin+base+'prototype-search/?'+query);
   await page.locator('.search-submit:not([disabled])').waitFor();
   await page.locator('#quick-meal').click();
   assert.equal(await page.locator('[data-meal-choice]:checked').evaluateAll(xs=>xs.map(x=>x.value).sort().join('|')),'501|502');
   assert.match(await page.locator('.meal-options').textContent(),/Ультра всё включено/);
   await page.screenshot({path:path.join(evidence,`meal-catalogue-${width}.png`)});
   await page.locator('[data-action="apply-meals"]').click();await page.waitForTimeout(100);
   await page.locator('.search-submit').click();
   await page.waitForFunction(()=>document.querySelector('.hotel-offer-count')?.textContent.startsWith('2 ')).catch(async error=>{
    console.error(JSON.stringify({width,started,errors,body:await page.locator('body').innerText()}));
    await page.screenshot({path:path.join(evidence,`meal-failure-${width}.png`),fullPage:true});throw error;
   });
   assert.equal(started.length,1);assert.equal(started[0].meal,'7');
   await page.screenshot({path:path.join(evidence,`meal-results-${width}.png`),fullPage:true});
   for(const [ids,count,price] of [[['501'],1,110000],[['502'],1,130000],[['501','502'],2,110000],[[],4,1]]){
    if(!await page.locator('#quick-meal').isVisible())await page.locator('[data-action="edit-search"]').first().click();
    await page.locator('#quick-meal').click();
    await page.locator('[data-meal-choice][value=""]').check();
    for(const id of ids)await page.locator(`[data-meal-choice][value="${id}"]`).check();
    await page.locator('[data-action="apply-meals"]').click();await page.waitForTimeout(100);
    assert.equal(await page.locator('.hotel-offer-count').textContent(),`${count} вариантов тура`);
    const digits=(await page.locator('.starting-price strong').textContent()).replace(/\D/g,'');assert.equal(Number(digits),price);
    assert.equal(Number((await page.locator('[data-action="select-date"]').first().locator('strong').textContent()).replace(/\D/g,'')),price,'Calendar minimum follows the same selected local IDs');
    assert.equal(started.length,1,'Local filter edits never start another supplier search');
   }
   await page.locator('#quick-meal').click();await page.locator('[data-meal-choice][value="501"]').check();await page.locator('[data-meal-choice][value="502"]').check();await page.locator('[data-action="apply-meals"]').click();await page.waitForTimeout(100);
   assert.equal(new URL(page.url()).searchParams.get('meals'),'501|502');
   await page.reload();await page.locator('.search-submit:not([disabled])').waitFor();
   await page.locator('#quick-meal').click();assert.equal(await page.locator('[data-meal-choice]:checked').evaluateAll(xs=>xs.map(x=>x.value).sort().join('|')),'501|502');await page.locator('[data-action="apply-meals"]').click();await page.waitForTimeout(100);
   missing=true;await page.locator('.search-submit').click();await page.getByText('Не настроено соответствие выбранного питания для Tourvisor.',{exact:false}).waitFor();
   assert.equal(started.length,1,'A newly missing mapping blocks the next provider request');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
   assert.deepEqual(errors,[]);await page.screenshot({path:path.join(evidence,`meal-mapping-missing-${width}.png`),fullPage:true});
   console.log(`SEARCH_MEAL_BROWSER_OK width=${width} local_IDs=1 AI_UAI_union=1 request_minimum=7 reload=1 mapping_error_blocks=1`);
   await ctx.close();
  }
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
}

(async()=>{
 await data.loadMealCatalogue();
 for(const [selected,minimum,count,min]of [[['501'],'7',1,110000],[['502'],'9',1,130000],[['501','502'],'7',2,110000],[[],'',4,1]]){
  model.hotels=data.project([{...profile,tours:raw}],trip);model.state.filters.meals=selected;
  assert.equal(data.params(trip,[],{meals:selected}).meal,minimum,'Map local IDs to supported native minimum');
  assert.equal(vm.runInContext('hotelOffers(hotels[0]).length',model),count,'Actual UI filter uses exact local-ID union');
  assert.equal(vm.runInContext(`minimumForDay('${trip.from}')`,model),min,'Calendar uses the identical same-offer predicate');
  const before=calls.length;await data.search(trip,()=>{},[],{meals:selected});
  assert.equal(calls.length,before+1);assert.equal(calls.at(-1).action,'search_start');assert.equal(calls.at(-1).params.meal,minimum);
  assert.equal(dbCalls.at(-1).meal,'','DB cohort lookup does not use a supplier meal ID');
 }
 model.state.filters={...defaultFilters(),meals:['502'],max:120000};assert.equal(vm.runInContext('hotelOffers(hotels[0]).length',model),0,'Meal+budget must match the SAME offer');
 const equal=structuredClone(snapshot);equal.plans[1].nameRu=equal.plans[0].nameRu;snapshot=equal;await data.loadMealCatalogue();
 model.hotels=data.project([{...profile,tours:raw}],trip);model.state.filters={...defaultFilters(),meals:['502']};
 assert.equal(vm.runInContext('hotelOffers(hotels[0])[0].raw.id',model),'uai','Equal labels never merge local IDs');
 const stored=parser.parse(storedReply());assert.equal(stored.offerCount,3);
 for(const hotel of stored.hotels){const o=data.project([{...hotel.hotel,anytourHotelId:hotel.anytourHotelId,tours:hotel.offers.map(x=>x.tour)}],trip)[0].offers[0];assert.equal(o.mealPlanId,501);assert.equal(o.mealId,801+Number(hotel.anytourHotelId)-4234);assert.equal(o.raw.selectionEnabled,false);assert.equal(o.total,120000);}
 for(const change of [r=>r.searchMeal.hotelId++,r=>r.searchMeal.conceptId++,r=>r.searchMeal.conceptRevision++,r=>r.searchMeal.source='bad',r=>r.stayMatch.meal.status='pending']){const response=storedReply();change(response.hotels[0].offers[0]);assert.equal(parser.parse(response).hotels[0].offers[0].tour.searchMealPlan,undefined,'Unproven/foreign/stale memberships never classify the offer');}
 const calendar=await data.calendar(trip,trip.from,trip.to,undefined,{meals:['501','502']});assert.deepEqual(Array.from(calendar,h=>h.offers[0].mealPlanId),[501,501,501]);
 // Unknown and stale links cannot silently turn into an unconstrained supplier query.
 for(const selected of [['AI'],['777'],['501','777']]){const before=calls.length;const events=[];await data.search(trip,e=>events.push(e),[],{meals:selected});assert.equal(calls.length,before);assert.equal(events.filter(e=>e.type==='error').length,1);}
 snapshot.plans[1].nativeIds=[];let before=calls.length,events=[];await data.search(trip,e=>events.push(e),[],{meals:['501','502']});assert.equal(calls.length,before);assert.match(events.find(e=>e.type==='error').message,/соответствие|отсутствует/);
 failCatalogue=true;before=calls.length;events=[];await data.search(trip,e=>events.push(e),[],{meals:['501']});assert.equal(calls.length,before);assert.equal(events.filter(e=>e.type==='error').length,1);
 // Actual URL writer/reader keep canonical IDs, not the text rendered beside them.
 const urlModel={state:{search:structuredClone(trip),filters:{...defaultFilters(),meals:['501','502']},selectedDate:null,hasSearched:false,onlyFavorites:false,sort:'recommended'},URLSearchParams,location:{search:'',hash:''},history:{state:null,replaceState(_s,_t,url){urlModel.location.search=url;}},structuredClone};
 vm.createContext(urlModel);vm.runInContext(app.slice(app.indexOf('function updateURL()'),app.indexOf('function renderSummary()')),urlModel);vm.runInContext('updateURL()',urlModel);
 assert.equal(new URLSearchParams(urlModel.location.search).get('meals'),'501|502');
 assert.match(app,/f\.meals\.includes\(String\(o\.mealPlanId\)\)/);assert.doesNotMatch(app,/mealNames\[o\.meal\]=o\.meal/);
 if(process.env.SEARCH3_MEAL_BROWSER==='1')await browserCheck();
 console.log('SEARCH MEALS PASS: own catalogue → local IDs → exact TV minimum request → canonical response → actual offer/calendar filters; AI/UAI/both/reset, same-offer budget, equal labels, all3 stored providers, stale/missing mappings and URL. Real supplier/DB writes/leads0.');
})().catch(e=>{console.error(e);process.exitCode=1;});
