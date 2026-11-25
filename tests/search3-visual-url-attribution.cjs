'use strict';
// Execute the shipped URL/edit/history owners and unchanged lead payload reader.
// DOM collaborators are intercepted: this is an offline source regression, not a browser/supplier result.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const override=process.argv.indexOf('--app');
const appPath=override<0?path.resolve(__dirname,'../v2/visual-search/app.js'):path.resolve(process.argv[override+1]);
const source=fs.readFileSync(appPath,'utf8');
const lead=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/lead-runtime-v1.js'),'utf8');
const fields=['yclid','utm_source','utm_medium','utm_campaign','utm_content','utm_term'];
const plain=value=>JSON.parse(JSON.stringify(value));
function owner(first,last){const a=source.indexOf(first),b=source.indexOf(last,a);assert(a>=0&&b>a,first+' owner found');return source.slice(a,b);}
const homeMode=source.match(/^const homeFormOnly=[^\n]+/m);assert(homeMode,'actual guarded HOME mode owner found');
const code=[homeMode[0],owner('function searchURLParameters(','const generatedRootBindings='),
 owner('function editSearch(){','const hotelPlaces='),
 owner('function commitSearchDraft(){','const searchLifecycle='),
 owner('function restoreHistoryView(route){','function updateModalBack(){')].join('\n');
function setup(href,{home=false}={}){
 let url=new URL(href);const writes=[],nodes=new Map(),listeners=new Map(),requests=[];
 const blocked=()=>{requests.push('blocked');throw Error('network forbidden');};
 const noop=()=>{};
 function node(selector){if(!nodes.has(selector))nodes.set(selector,{hidden:false,open:false,value:'Москва',classList:{add:noop,remove:noop,contains:()=>false},scrollIntoView:noop,focus:noop});return nodes.get(selector);}
 const location={get href(){return url.href;},get search(){return url.search;},get hash(){return url.hash;}};
 const history={state:{unchanged:'retained'},replaceState(state,title,href){this.state=state;url=new URL(href,url);writes.push(['replace',url.href]);},pushState(state,title,href){this.state=state;url=new URL(href,url);writes.push(['push',url.href]);}};
 const state={search:{origin:'Москва',country:'turkey',from:'2026-12-01',to:'2026-12-07',minNights:7,maxNights:7,adults:2,ages:[0,8]},
  filters:{stars:[4,5],meals:['AI'],resorts:[],operators:[],flight:[],amenities:[],beach:false,rating:false,family:false,spa:false,hotelId:0,hotelIds:[],q:'',min:0,max:null},selectedDate:null,hasSearched:true,onlyFavorites:false,sort:'recommended'};
 const context={URL,URLSearchParams,structuredClone,location,history,state,searchEditSession:null,urlStateHydrated:true,data:{scenario:'live'},
  destinationIds:f=>f.hotelIds,amenityLabel:()=>'',searchResponse:{pending:false},innerWidth:430,scrollY:37,actionTrigger:null,draft:structuredClone(state.search),draftDestination:null,
  $:node,focusReference:()=>({id:'fixture-focus'}),scrollBehavior:()=> 'instant',requestAnimationFrame:fn=>fn(),
  agesNeedReview:false,filterBudgetEdit:null,urlPartyIssues:[],currentFilterBudgetEdit:()=>false,draftSelectedDate:()=>state.selectedDate,
  uiHistoryOpen:false,uiHistoryClosing:false,pendingPageReturn:null,uiHistoryKey:'search3-ui',modalHistory:[],handlingUIBack:false,restoringUIHistory:false,inlineSearchPicker:null,
  reopenUIRoute:()=>true,uiRoute:()=>({type:'selected-tour'}),addEventListener:(type,fn)=>listeners.set(type,fn),fetch:blocked,
  document:{cookie:'_ym_uid=fixture-client',activeElement:null,querySelector:()=>null,body:{classList:{add:noop}}},
  window:{AnyTourPublicConfigV1:{valid:true},V2Runtime:{state:{}},V2_CONFIG:{leadApi:'/lead-adapter-v2.php',search3FormOnly:home},fetch:blocked,scrollTo:noop}};
 for(const name of ['stopSearch','closeFilters','updateSearchUI','collapseSearch','renderFilters','loadCountries','restoreFocus','rememberDestination','finishPageReturn','modalBack','setFilterSectionOpen','closeModal','closeInlineSearchPicker'])context[name]=noop;
 vm.createContext(context);vm.runInContext(code+'\n'+lead,context,{filename:appPath});
 const session=context.window.V2TourController.createProviderLeadSession({provider:'anex',offerRef:'offline-only',current:noop,payload:()=>({provider:'anex',providerOfferRef:'offline-only'})});
 const payload=()=>plain(session.payload({get:()=>null}));
 const attribution=()=>{const value=payload();return Object.fromEntries([...fields,'yaclient'].map(key=>[key,value[key]]));};
 return{context,location,history,writes,requests,node,listeners,payload,attribution};
}

const expected={yclid:'audit-001',utm_source:'Яндекс',utm_medium:'cpc',utm_campaign:'семья & море',utm_content:'"<img src=x onerror=alert(1)> + # ? = %',utm_term:'отель / 2+2',yaclient:'fixture-client'};
const landing=new URL('https://example.invalid/poisk-turov/');
for(const key of fields)landing.searchParams.set(key,expected[key]);
landing.searchParams.append('yclid','second-click-must-not-replace-first');
landing.searchParams.set('utm_id','not-in-existing-contract');landing.searchParams.set('leadApi','https://outside.invalid/');landing.hash='old-anchor';
const f=setup(landing.href),ctx=f.context;
assert.deepEqual(f.attribution(),expected,'existing lead reader before URL update');
ctx.updateURL();
assert.deepEqual(f.attribution(),expected,'initial URL update retains the existing lead attribution');
assert.deepEqual(Object.keys(f.payload()).sort(),[...fields,'yaclient','provider','providerOfferRef','sessid','page'].sort(),'lead payload contract remains unchanged');
const first=new URL(f.location.href);
assert.equal(first.origin,landing.origin);assert.equal(first.pathname,landing.pathname);assert.equal(first.hash,'','existing hash normalization');
assert.equal(first.searchParams.getAll('yclid').length,1,'same first-value semantics as the lead reader');
assert.equal(first.searchParams.has('utm_id'),false);assert.equal(first.searchParams.has('leadApi'),false,'unrecognized parameters stay excluded');
assert.equal(first.searchParams.get('ages'),'0,8');assert.equal(first.searchParams.get('stars'),'4|5');
assert.equal(first.searchParams.get('searched'),'1','the receiving search keeps its applied-search flag');
assert(!f.location.href.includes('<img'),'untrusted attribution remains URL-encoded');
assert.strictEqual(f.history.state.unchanged,'retained','history state is preserved');
const firstWrites=f.writes.length;ctx.updateURL();assert.equal(f.writes.length,firstWrites,'unchanged URL does not replace history');

for(let round=0;round<3;round++){
 const beforeEdit=f.location.href;ctx.editSearch();assert(ctx.searchEditSession,'actual edit owner opened a draft');
 ctx.state.filters.min=150000+round;ctx.updateURL();assert.equal(f.location.href,beforeEdit,'draft cannot replace applied URL');
 ctx.cancelSearchEdit();assert.equal(ctx.searchEditSession,null);assert.equal(ctx.state.filters.min,0,'cancel restores applied filters');
 assert.deepEqual(f.attribution(),expected,'attribution after edit/cancel '+round);
 ctx.editSearch();ctx.draft.minNights=8+round;ctx.draft.maxNights=8+round;assert.deepEqual(plain(ctx.commitSearchDraft()),{});
 assert.equal(new URL(f.location.href).searchParams.get('minNights'),String(8+round));
 assert.deepEqual(f.attribution(),expected,'attribution after committed search edit '+round);
 ctx.uiHistoryOpen=true;ctx.modalHistory=[{type:'selected-tour'}];f.node('#modal').open=true;
 f.listeners.get('popstate')({state:{}});assert.deepEqual(f.attribution(),expected,'attribution after actual Back owner '+round);
 ctx.uiHistoryOpen=false;f.node('#modal').open=false;
 const route={type:'selected-tour'};f.history.state={...f.history.state,[ctx.uiHistoryKey]:route};
 f.listeners.get('popstate')({state:f.history.state});assert.deepEqual(f.attribution(),expected,'attribution after actual history resume owner '+round);
}
const reloaded=setup(f.location.href);reloaded.context.updateURL();assert.deepEqual(reloaded.attribution(),expected,'URL survives a new runtime without in-memory attribution storage');
const cold=setup(landing.href);cold.context.urlStateHydrated=false;cold.context.updateURL();assert.equal(cold.writes.length,0,'unhydrated state still cannot rewrite URL');
const organic=setup('https://example.invalid/poisk-turov/?utm_campaign=&other=discard');organic.context.updateURL();
assert.equal(new URL(organic.location.href).searchParams.get('utm_campaign'),'','present empty value stays empty');
for(const key of fields.filter(key=>key!=='utm_campaign'))assert.equal(new URL(organic.location.href).searchParams.has(key),false,'absent attribution is not fabricated');
const homeLanding=new URL(landing);homeLanding.pathname='/';homeLanding.searchParams.set('searched','1');
const home=setup(homeLanding.href,{home:true}),homeContext=home.context;
homeContext.updateURL();
assert.equal(new URL(home.location.href).pathname,'/','HOME keeps its current route');
assert.equal(new URL(home.location.href).searchParams.has('searched'),false,'HOME removes a stale searched flag');
assert.deepEqual(home.attribution(),expected,'HOME rewrite preserves existing attribution');
homeContext.editSearch();homeContext.draft.adults=3;homeContext.draft.ages=[0,12];homeContext.draft.minNights=8;homeContext.draft.maxNights=8;
assert.deepEqual(plain(homeContext.commitSearchDraft()),{});
assert.equal(homeContext.state.hasSearched,false,'committing HOME conditions does not mark a search as started');
const homeURL=new URL(home.location.href);
assert.equal(homeURL.searchParams.has('searched'),false,'committed HOME URL stays without searched');
assert.equal(homeURL.searchParams.get('adults'),'3');assert.equal(homeURL.searchParams.get('ages'),'0,12');assert.equal(homeURL.searchParams.get('minNights'),'8');
assert.deepEqual(home.attribution(),expected,'HOME commit preserves existing attribution');
const homeWrites=home.writes.length,receiver=homeContext.searchURLParameters(true);
assert.equal(receiver.get('searched'),'1','the same serializer can authorize the receiving search');
assert.equal(receiver.get('ages'),'0,12');for(const key of fields)assert.equal(receiver.get(key),expected[key]);
assert.equal(home.location.href,homeURL.href,'serializing the receiver leaves HOME history unchanged');
homeContext.updateURL();assert.equal(home.writes.length,homeWrites,'unchanged HOME URL does not replace history');
for(const fixture of [f,reloaded,cold,organic,home])assert.equal(fixture.requests.length,0,'supplier and lead HTTP 0');
console.log('PASS URL attribution: first rewrite, 3 edit/cancel/commit/Back/resume cycles, reload, encoding, duplicate/empty/absent values; HOME clean history and receiving serialization; unchanged lead payload contract; supplier/lead HTTP 0');
