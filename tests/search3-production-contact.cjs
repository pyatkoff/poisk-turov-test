'use strict';
// Execute the actual phone/contact owners. No supplier, sender or browser IO.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=path.resolve(__dirname,'..');
const lead=fs.readFileSync(path.join(root,'v2/prototype-search/lead.js'),'utf8');
const app=fs.readFileSync(path.join(root,'v2/visual-search/app.js'),'utf8');
const tests=[];const test=(name,fn)=>tests.push({name,fn});
function owner(enabled){
 const io=[];
 const window={V2_CONFIG:{leadApi:'../preview-lead-disabled.php',...(enabled===undefined?{}:{search3PhoneLeadEnabled:enabled})},location:{href:'https://anytoour.ru/poisk-turov/'}};
 const deny=name=>new Proxy({},{get(){io.push(name);throw Error('Unexpected '+name);}});
 vm.runInNewContext(lead,{window,URL,document:deny('DOM'),fetch:deny('fetch'),localStorage:deny('storage')});
 return {api:window.AnyTourPrototypeLead,io};
}
test('Production fallback collects no phone and offers the real contacts route',()=>{
 const {api,io}=owner(false),html=api.markup();
 assert.match(html,/href="\/contacts\/"/);
 assert.doesNotMatch(html,/<form|<input|<textarea|type="submit"|prototype-lead-form|data-action="messenger-phone"/);
 assert.equal(api.action(),'');
 for(const method of ['bind','bindProviderApplication','bindProviderPreview'])assert.doesNotThrow(()=>api[method]({}));
 assert.deepEqual(io,[],'disabled contact owner must not even bind a DOM or sender');
});
test('Existing preview phone markup and submit action keep their default behavior',()=>{
 for(const flag of [undefined,true]){
  const {api,io}=owner(flag),html=api.markup();
  assert.match(html,/<form id="prototype-lead-form">/);
  assert.match(html,/name="phone"/);assert.match(html,/name="consent"/);
  assert.match(api.action(),/type="submit" form="prototype-lead-form"/);
  assert.deepEqual(io,[]);
 }
});
function contact(phone,available=true){
 const start=app.indexOf('function messengerContactHTML('),end=app.indexOf('\nfunction sameMessengerReview(',start);
 assert(start>=0&&end>start);
 const {api}=owner(phone);
 const ctx={window:{V2_CONFIG:{search3PhoneLeadEnabled:phone},AnyTourPrototypeLead:api},messengerAvailable:()=>available};
 vm.runInNewContext(app.slice(start,end)+'\nresult=messengerContactHTML({});',ctx);
 return ctx.result;
}
test('Production messenger screen keeps TG/MAX and its pending status without a dead phone form',()=>{
 const html=contact(false);
 for(const value of ['data-platform="telegram"','data-platform="max"','id="messenger-status"','id="messenger-check"','href="/contacts/"'])assert(html.includes(value),value);
 assert.equal((html.match(/data-action="messenger-open"/g)||[]).length,2);
 assert.doesNotMatch(html,/data-action="messenger-phone"|id="messenger-phone"|prototype-lead-form|type="submit"/);
});
test('Unavailable retained handoff offers contacts, without pretending a phone submission is active',()=>{
 const html=contact(false,false);assert.match(html,/href="\/contacts\/"/);
 assert.doesNotMatch(html,/messenger-open|prototype-lead-form|type="submit"/);
});
test('Preview messenger screen retains its existing phone choice and canonical form',()=>{
 const html=contact(true);
 for(const value of ['data-action="messenger-phone"','id="messenger-phone"','id="prototype-lead-form"'])assert(html.includes(value),value);
});
test('Optional contact-flight editing remains active when messenger replaces the phone form',()=>{
 const code=app.match(/function contactFlightActive\(key\)\{[^\n]+\}/)?.[0];assert(code);
 const selectedOffer={key:'offer-1',contactFlightEditing:true},ctx={selectedOffer,modalType:'selected-tour',$:selector=>selector==='#messenger-entry'?{}:null};
 vm.runInNewContext(code+'\ncheck=contactFlightActive;',ctx);
 assert.equal(ctx.check('offer-1'),true);assert.equal(ctx.check('other'),false);
 ctx.modalType='all-offers';assert.equal(ctx.check('offer-1'),false);
});
test('Production startup does not require the removed prototype controls',()=>{
 const end=app.indexOf('\nconst paths =');assert(end>0);
 const fragment=app.slice(app.indexOf('const $ ='),end),ctx={window:{V2_CONFIG:{search3ProductionEnabled:true}},document:{querySelector(){throw Error('Prototype DOM accessed');},querySelectorAll(){throw Error('Prototype DOM accessed');}}};
 assert.doesNotThrow(()=>vm.runInNewContext(fragment,ctx));
});
test('Manual invocation cannot enter a phone form in the production messenger policy',()=>{
 const start=app.indexOf('function messengerPhone('),end=app.indexOf('\nasync function messengerOpen(',start);assert(start>=0&&end>start);
 const ctx={window:{V2_CONFIG:{search3PhoneLeadEnabled:false}},$:()=>{throw Error('Phone DOM accessed');}};
 assert.doesNotThrow(()=>vm.runInNewContext(app.slice(start,end)+'\nmessengerPhone(true);',ctx));
});
test('Expired or out-of-window production links cannot auto-search substituted default dates',()=>{
 const query=fs.readFileSync(path.join(root,'v2/visual-search/production-query-v1.js'),'utf8');
 const start=app.indexOf('function restoreURL('),end=app.indexOf('\nlet compactSearchFrame=',start);assert(start>=0&&end>start);
 const make=(search,productionMode=true)=>{
  const window={},ctx={window,URLSearchParams,productionMode,location:{search},startDay:'2026-10-28',endDay:'2027-10-28',
   state:{search:{from:'2026-11-10',to:'2026-11-16',minNights:7,maxNights:7,adults:2,ages:[]}},
   data:{date:value=>value,catalog:{}},dateObj:value=>new Date(value+'T12:00:00Z'),structuredClone,
   parseURLParty:(p,s)=>({adults:s.adults,ages:s.ages,issues:[]})};
  vm.runInNewContext(query,ctx);
  ctx.entrySearchParams=()=>productionMode?window.AnyTourSearch3ProductionQueryV1.normalize(search,{},{}):new URLSearchParams(search);
  vm.runInNewContext(app.slice(start,end)+'\nrestore=restoreURL;',ctx);return ctx;
 };
 for(const [from,to]of [['2026-10-01','2026-10-07'],['2027-11-01','2027-11-07']]){
  const ctx=make('?from=1&country=4&dateFrom='+from+'&dateTo='+to+'&daysFrom=7&daysTill=7&count_people=2');
  assert.throws(()=>ctx.restore(true),error=>error.code==='search3_query_dates_invalid');
  assert.equal(ctx.state.search.from,'2026-11-10','do not silently replace the requested trip then search defaults');
 }
 const current=make('?from=1&country=4&dateFrom=2027-01-24&dateTo=2027-01-30&daysFrom=7&daysTill=7&count_people=2');
 current.restore(true);assert.equal(current.state.search.from,'2027-01-24');assert.equal(current.state.search.to,'2027-01-30');
 const preview=make('?from=2026-10-01&to=2026-10-07',false);
 preview.restore(true);assert.equal(preview.state.search.from,'2026-11-10','existing preview hydration is unchanged');
});
test('Production URL updates preserve the existing attribution fields and omit demo routing',()=>{
 const code=app.match(/function updateURL\(\)\{[^\n]+\}/)?.[0];assert(code);
 for(const productionMode of [true,false]){
  const replacements=[],ctx={productionMode,URLSearchParams,searchEditSession:null,urlStateHydrated:true,
   data:{scenario:'live'},destinationIds:()=>[],location:{search:'?utm_source=example&utm_content=a%26b&utm_content=second&yclid=test-click&arbitrary=ignored',hash:''},
   state:{search:{origin:'Москва',country:'1',from:'2027-01-24',to:'2027-01-30',minNights:7,maxNights:7,adults:2,ages:[]},selectedDate:null,hasSearched:false,onlyFavorites:false,sort:'recommended',filters:{stars:[],meals:[],resorts:[],operators:[],flight:[],amenities:[],max:null}},
   history:{state:null,replaceState:(state,title,url)=>replacements.push(url)}};
  vm.runInNewContext(code+'\nupdateURL();',ctx);assert.equal(replacements.length,1);
  const p=new URLSearchParams(replacements[0]);
  if(productionMode){assert.equal(p.get('s3'),'1');assert.equal(p.has('scenario'),false);assert.equal(p.get('utm_source'),'example');assert.deepEqual(p.getAll('utm_content'),['a&b','second']);assert.equal(p.get('yclid'),'test-click');}
  else{assert.equal(p.get('scenario'),'live');assert.equal(p.has('s3'),false);assert.equal(p.has('utm_source'),false,'preview URL behavior is unchanged');}
  assert.equal(p.has('arbitrary'),false);
 }
});
for(const {name,fn}of tests){try{fn();console.log('PASS '+name);}catch(error){console.error('FAIL '+name);throw error;}}
console.log('SEARCH3_PRODUCTION_CONTACT_OK '+tests.length);
