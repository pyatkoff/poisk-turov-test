'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/production-query-v1.js'),'utf8');
const io=[],window={};
vm.runInNewContext(source,{window,URLSearchParams,fetch(){io.push('fetch');throw Error('unexpected network');},
 document:new Proxy({},{get(){io.push('DOM');throw Error('unexpected DOM');}}),localStorage:new Proxy({},{get(){io.push('storage');throw Error('unexpected storage');}})});
const api=window.AnyTourSearch3ProductionQueryV1;
const catalog={departures:[{id:1,name:'Moscow',russianName:'Москва'},{id:2,name:'Казань'}],
 countries:[{id:78,name:'Египет',tourvisorIds:['4']},{id:4,name:'Другой LOCAL id',tourvisorIds:['78']}],
 meals:[{id:7,russianName:'Всё включено',name:'AI'},{id:8,fullRussianName:'Ультра всё включено',name:'UAI'}]};
const original=JSON.stringify(catalog),tests=[];const test=(name,run)=>tests.push({name,run});
const ready=search=>api.normalize(search,catalog,{identitiesReady:true});
const throws=(search,code,override=catalog)=>assert.throws(()=>api.normalize(search,override,{identitiesReady:true}),error=>error.code===code);
const home='from=1&country=4&dateFrom=2027-01-24&dateTo=2027-01-30&daysFrom=7&daysTill=10&count_people=2&child_age%5B%5D=0&child_age%5B%5D=0&child_age%5B%5D=12';

test('Homepage GET becomes modern fields with exact catalog identities and ordered repeated infant ages',()=>{
 const p=ready(home);
 for(const [key,value] of Object.entries({origin:'Москва',country:'78',from:'2027-01-24',to:'2027-01-30',minNights:'7',maxNights:'10',adults:'2',ages:'0,0,12',s3:'1'}))assert.equal(p.get(key),value,key);
 for(const key of ['dateFrom','dateTo','daysFrom','daysTill','count_people','child_age[]'])assert.equal(p.has(key),false,key);
 assert.equal(api.initialOrigin(home,'Казань'),'1');
});
test('Early hydration defers catalog mapping and may be safely normalized again after catalogue readiness',()=>{
 const early=api.normalize(home+'&stars=4',{},{});assert.equal(early.get('from'),'2027-01-24');assert.equal(early.get('origin'),'1');assert.equal(early.get('country'),'4');assert.equal(early.get('ages'),'0,0,12');assert.equal(early.has('s3'),false);
 const late=ready(early);assert.equal(late.get('origin'),'Москва');assert.equal(late.get('country'),'78');assert.equal(late.get('ages'),'0,0,12');
 assert.equal(late.get('stars'),'4|5');
 assert.equal(ready(late).toString(),late.toString(),'the LOCAL namespace is never remapped on a second hydration');
});
test('Marked modern country IDs never collide with legacy Tourvisor IDs',()=>{
 const marked=new URLSearchParams('s3=1&origin=Москва&country=4&from=2027-01-24&to=2027-01-30&ages=0,0,12&hotels=4|78&resorts=4');
 assert.equal(ready(marked).toString(),marked.toString());assert.equal(ready('from=1&country=4').get('country'),'78');
 assert.equal(ready('from=1&country=78').get('country'),'4');
});
test('Existing explicit modern shared links, empty requests and attribution survive unchanged',()=>{
 for(const value of ['', '?utm_source=test&utm_source=again&yclid=123', 'origin=Москва&country=78&from=2027-01-24&to=2027-01-30&ages=0,0', 'country=78&from=2027-01-24&to=2027-01-30']){
  const p=new URLSearchParams(value);assert.equal(ready(p).toString(),p.toString());
 }
 const input=new URLSearchParams(home+'&utm_source=Реклама&utm_source=again&yclid=123&gclid=abc&custom=kept'),before=input.toString();
 const p=ready(input);assert.deepEqual(p.getAll('utm_source'),['Реклама','again']);assert.equal(p.get('yclid'),'123');assert.equal(p.get('gclid'),'abc');assert.equal(p.get('custom'),'kept');
 assert.equal(input.toString(),before);assert.equal(JSON.stringify(catalog),original);assert.notEqual(p,input);
});
test('Only literal unique modern markers are accepted',()=>{
 for(const value of ['s3=','s3=0','s3=true','s3=2','s3=1&s3=1','s3=1&s3=0']){
  throws(value,'search3_query_marker_invalid');assert.throws(()=>api.initialOrigin(value,'Москва'),error=>error.code==='search3_query_marker_invalid');
 }
});
test('Unmapped and ambiguous country or departure identities fail closed without numeric substitution',()=>{
 throws('from=99&country=4','search3_query_departure_unmapped');throws('from=1&country=99','search3_query_country_unmapped');
 throws('country=4','search3_query_country_unmapped',{...catalog,countries:[{id:4,tourvisorIds:['78']}]});
 throws('from=1&country=4','search3_query_country_unmapped',{...catalog,countries:[...catalog.countries,{id:90,tourvisorIds:['4']}]});
 throws('from=1&country=4','search3_query_departure_unmapped',{...catalog,departures:[...catalog.departures,{id:1,name:'Другой город'}]});
 throws('from=1&country=4','search3_query_country_unmapped',{...catalog,countries:[{id:78,tourvisorIds:['4','99']}]});
 throws('country=04','search3_query_country_invalid');throws('from=1&country=bad','search3_query_country_invalid');
});
test('Meal and budget aliases map through current catalogue labels and preserve full-party ranges',()=>{
 const p=ready(home+'&food=7&price_from=120000&price_till=200000.50&stars=4');
 assert.equal(p.get('meals'),'Всё включено');assert.equal(p.get('min'),'120000');assert.equal(p.get('max'),'200000.50');assert.equal(p.get('stars'),'4|5');
 assert.equal(ready(home+'&food=8').get('meals'),'Ультра всё включено');assert.equal(ready(home+'&food=&price_from=&price_till=').has('meals'),false);
 throws(home+'&food=99','search3_query_meal_unmapped');throws(home+'&food=AI','search3_query_meal_invalid');
 throws(home+'&food=7','search3_query_meal_unmapped',{...catalog,meals:[...catalog.meals,{id:7,name:'Другой вариант'}]});
 throws(home+'&price_from=200000&price_till=100000','search3_query_budget_invalid');throws(home+'&price_from=-1','search3_query_budget_invalid');
});
test('Unsupported legacy location filters refuse rather than treating provider IDs as LOCAL IDs',()=>{
 for(const key of ['hotel','hotels','hotelId','hotel_ids','region','regions','regionId','subregion','subRegion','town','resort','resorts']){
  throws(home+'&'+key+'=4','search3_query_legacy_terms_unsupported');throws(key+'=4','search3_query_legacy_terms_unsupported');
 }
 const p=ready(home+'&hotel=&region=');assert.equal(p.has('hotel'),false);assert.equal(p.has('region'),false);
 assert.equal(ready('s3=1&origin=Москва&country=78&hotel=4').get('hotel'),'4');
});
test('Unmapped legacy operator, arrival, hotel, flight and rating conditions fail with a fixed readable error',()=>{
 const keys=['operator','operator[]','arrival','hotel_type','onlyDirect','only_direct','onlyCharter','only_charter','hotel_service[]','rating'];
 for(const key of keys){
  for(const value of ['0','1','PRIVATE_ID']){
   const query=home+'&'+encodeURIComponent(key)+'='+value;
   assert.throws(()=>ready(query),error=>{
    assert.equal(error.code,'search3_query_legacy_terms_unsupported');assert.match(error.message,/Откройте новый поиск/);
    assert.doesNotMatch(error.message,/search3_query_|PRIVATE_ID|operator|rating/);return true;
   });
  }
  const marked=new URLSearchParams('s3=1&origin=Москва&country=78&'+encodeURIComponent(key)+'=1');
  assert.equal(ready(marked).toString(),marked.toString(),'modern parameters remain in their own contract');
 }
 const empty=ready(home+'&operator=&arrival=&hotel_type=&onlyDirect=&only_charter=&hotel_service%5B%5D=&rating=');
 for(const key of keys)assert.equal(empty.has(key),false);
});
test('Incomplete or malformed party, dates, nights and conflicting aliases never become another trip',()=>{
 throws('from=1&country=4&dateFrom=2027-01-24','search3_query_dates_incomplete');
 throws('from=1&country=4&dateFrom=2027-02-29&dateTo=2027-03-01','search3_query_dates_invalid');
 throws('from=1&country=4&dateFrom=2027-02-02&dateTo=2027-02-01','search3_query_dates_invalid');
 throws('from=1&country=4&daysFrom=7','search3_query_nights_incomplete');throws('from=1&country=4&daysFrom=10&daysTill=7','search3_query_nights_invalid');
 throws('from=1&country=4&count_people=0','search3_query_adults_invalid');throws('from=1&country=4&child_age[]=18','search3_query_ages_invalid');
 throws('from=1&country=4&child_age[]=0&child_count=2','search3_query_ages_incomplete');throws('from=1&country=4&child_age[0]=0','search3_query_ages_invalid');
 throws('from=1&country=4&child_age[]=0&child_age=0','search3_query_ages_invalid');
 throws(home+'&adults=3','search3_query_conflicting_terms');throws(home+'&origin=Казань','search3_query_conflicting_terms');
 for(const key of ['from','country','dateFrom','count_people'])throws(home+'&'+key+'=1','search3_query_duplicate_field');
 throws(home+'&food=7&food=8','search3_query_duplicate_field');
 const empty=ready('from=2&country=4&child_count=0');assert.equal(empty.get('origin'),'Казань');assert.equal(empty.get('ages'),'');
});
test('Initial origin selects only explicit modern origin, numeric legacy departure or caller fallback without IO',()=>{
 assert.equal(api.initialOrigin('origin=Казань&from=2027-01-24','Москва'),'Казань');assert.equal(api.initialOrigin('from=2&country=4','Москва'),'2');
 assert.equal(api.initialOrigin('from=2027-01-24','Москва'),'Москва');assert.equal(api.initialOrigin('','Москва'),'Москва');
 assert.throws(()=>api.initialOrigin('from=bad','Москва'),error=>error.code==='search3_query_departure_invalid');
 assert.equal(JSON.stringify(catalog),original);assert.deepEqual(io,[]);assert.equal(Object.isFrozen(api),true);
});
test('Supported snake-case date/night aliases preserve the same trip and reject conflicting spellings',()=>{
 const p=ready('from=2&country=4&date_from=2027-01-24&date_till=2027-01-30&days_from=7&days_till=10&count_people=2');
 assert.equal(p.get('origin'),'Казань');assert.equal(p.get('from'),'2027-01-24');assert.equal(p.get('to'),'2027-01-30');assert.equal(p.get('minNights'),'7');assert.equal(p.get('maxNights'),'10');
 for(const key of ['date_from','date_till','days_from','days_till'])assert.equal(p.has(key),false);
 throws(home+'&date_from=2027-01-25','search3_query_conflicting_terms');
});
test('Only a validated numeric-ID homepage submission carries an initial search intent',()=>{
 assert.equal(api.isLegacySubmission(home),true);
 assert.equal(api.isLegacySubmission('from=2&country=4&date_from=2027-01-24&date_till=2027-01-30'),true);
 for(const value of ['', 'country=4','from=1&country=4','origin=Москва&country=78&from=2027-01-24&to=2027-01-30',
  'from=1&country=4&daysFrom=7&daysTill=7','from=1&country=4&days_from=7&days_till=7',
  's3=1&from=1&country=4&daysFrom=7&daysTill=7','from=2027-01-24&country=78&daysFrom=7&daysTill=7',
  home+'&adults=3',home+'&origin=Казань',home+'&date_from=2027-01-25',home+'&country=4',
  'from=1&country=4&dateFrom=2027-01-24',home+'&hotel=99'])assert.equal(api.isLegacySubmission(value),false,value);
 assert.equal(api.isLegacySubmission('from=1&country=4&dateFrom=2000-01-01&dateTo=2000-01-02'),true,
  'form intent is structural; the connected UI owner must reject dates outside its actual clock window before searching');
 assert.deepEqual(io,[]);
});
for(const {name,run} of tests){run();console.log('PASS '+name);}console.log('search3 production query: '+tests.length+' groups PASS; accepted catalogue namespaces, ordered party and zero IO');
