'use strict';
// Pinned main public-form fixture -> current source NEXT restore, with the current legacy URL owner executed.
// Optional --public-root verifies the exact main files and compares both real legacy owners locally.
// No boot, network or supplier execution; DOM collaborators are intercepted.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),{createHash}=require('node:crypto');
function directoryOption(name){const i=process.argv.indexOf(name);if(i<0)return null;assert(process.argv[i+1]&&!process.argv[i+1].startsWith('--'),'directory required after '+name);return path.resolve(process.argv[i+1]);}
const root=path.join(directoryOption('--source-root')||path.resolve(__dirname,'..'),'v2'),read=relative=>fs.readFileSync(path.join(root,relative),'utf8');
const source=read('visual-search/app.js'),adapter=read('visual-search/public-url-v1.js'),canonical=read('prototype-search/data.js');
const old=read('search-lifecycle-v6.js');
// This small recorded contract belongs to main e68fe088, not the release homepage.
const publicContract={main:'e68fe088764521c7ff6b605403ca7f2ebb7e4dd6',action:'/poisk-turov/',
 homeFields:['from','country','dateFrom','dateTo','daysFrom','daysTill','count_people'],
 sha256:{'home-v1.php':'081389eb4980e41a5801943a752ccbc16fb1326682a199c0e29f31525d4f270d','country-page-v1.php':'7a1c91a899a3bac1daa285a07ad6b586604fb08008063b6263a91e6abd51017b','search-lifecycle-v6.js':'3cc27d84a13ed010daa0cdf850c73e47e58aa7b9edff6a0155b4456d1e914e61'}};
const publicRoot=directoryOption('--public-root');let mainOld=null;
if(publicRoot){
 const files={};for(const [file,expected] of Object.entries(publicContract.sha256)){files[file]=fs.readFileSync(path.join(publicRoot,'v2',file),'utf8');assert.equal(createHash('sha256').update(files[file]).digest('hex'),expected,'exact '+publicContract.main+' public owner: '+file);}
 const home=files['home-v1.php'],country=files['country-page-v1.php'];
 assert(home.includes('action="'+publicContract.action+'" method="get"'));
 for(const name of publicContract.homeFields)assert(home.includes('name="'+name+'"'),'pinned main public home field '+name);
 assert(country.includes("'?country=' . $countryId"),'pinned main country hero native query');mainOld=files['search-lifecycle-v6.js'];
}
assert(old.includes("dateFrom:['date_from','dateFrom'],dateTo:['date_till','dateTo'],daysFrom:['days_from','daysFrom'],daysTill:['days_till','daysTill']"),'actual public aliases');
assert(old.includes("sp.getAll('child_age[]').concat(sp.getAll('child_age'))"),'both public child-age forms');
const requests=[],blocked=()=>{requests.push('blocked');throw Error('network forbidden');};
const context={URLSearchParams,fetch:blocked,location:{assign:blocked},history:{replaceState:blocked}};
vm.createContext(context);vm.runInContext(adapter,context);
const convert=context.AnyTourPublicSearchURLV1.fromLegacy;
function oldHydration(query='',owner=old){
 const keys=new Set(),partyWrites=[],fields={},services=[],events=[];let children=[];
 class RecordedParams extends URLSearchParams{get(key){keys.add(key);return super.get(key);}getAll(key){keys.add(key);return super.getAll(key);}has(key){keys.add(key);return super.has(key);}}
 const body=name=>{const line=owner.match(new RegExp('^function '+name+'\\([^\\n]*$','m'))?.[0];assert(line&&line.endsWith('}'),'complete actual legacy owner: '+name);return line;};
 // The release adds an optional queryOverride and a preview-only hydrated event.
 // Execute its no-override production-body path, and compare it with main when supplied.
 const ctx={URLSearchParams:RecordedParams,window:{location:{search:query}},document:{body:{classList:{contains:()=>false}},createElement:()=>({})},emit:(...args)=>events.push(args),
  setField:(name,value)=>{fields[name]=value;},setHydratedChildCount:value=>{partyWrites.push(['count',value]);children=Array.from({length:Math.max(0,Math.min(3,Number(value)||0))},(_,index)=>({index}));},setHydratedChildAge:(node,value)=>partyWrites.push(['age',node.index,value]),
  catalogs:{renderChildAges:()=>partyWrites.push(['render']),updateServiceCount:()=>events.push(['services'])},form:{querySelectorAll:selector=>selector==='select[name="child_age[]"]'?children:services,appendChild:node=>services.push(node)}};
 vm.createContext(ctx);vm.runInContext(body('queryValue')+'\n'+body('hydrateUrlState')+'\nglobalThis.result=hydrateUrlState();',ctx);
 return {keys:[...keys],partyWrites,fields,services:services.map(node=>({value:node.value,checked:node.checked})),events,auto:ctx.result.auto};
}
function currentNextKeys(){
 const start=source.indexOf('function updateURL(){'),end=source.indexOf('const generatedRootBindings=',start);assert(start>=0&&end>start,'actual NEXT URL owner');
 let query='';
 const ctx={URLSearchParams,searchEditSession:null,urlStateHydrated:true,data:{scenario:'live'},location:{search:'',hash:''},history:{state:{},replaceState:(_state,_title,value)=>{query=value;}},destinationIds:f=>f.hotelIds,amenityLabel:()=> 'Пляж',
  state:{search:{origin:'Москва',country:'101',from:'2026-12-20',to:'2026-12-24',minNights:9,maxNights:11,adults:3,ages:[0,8]},selectedDate:'2026-12-21',hasSearched:true,onlyFavorites:true,sort:'price',
   filters:{stars:[5],meals:['AI'],resorts:['Курорт'],operators:['Оператор'],flight:['charter'],amenities:['1:1'],beach:true,rating:true,family:true,spa:true,hotelId:11,hotelIds:[11,12],q:'отель',min:100000,max:200000}}};
 vm.createContext(ctx);vm.runInContext(source.slice(start,end)+'\nupdateURL();',ctx);
 assert(query);return [...new URLSearchParams(query).keys()];
}
const helpers={};vm.createContext(helpers);
const helperCode=['text','date'].map(name=>canonical.match(new RegExp('^  const '+name+' = .+$','m'))?.[0]);
assert(helperCode.every(Boolean));vm.runInContext(helperCode.join('\n')+'\nglobalThis.api={text,date};',helpers);
const catalog={departures:[{id:1,name:'Москва'},{id:2,russianName:'Санкт-Петербург'}],countries:[{id:'101',name:'Египет',tourvisorIds:['1']},{id:'204',name:'Турция',tourvisorIds:['4']}],
 origin:'Санкт-Петербург',text:helpers.api.text,startDay:'2026-11-21',endDay:'2027-05-20'};
const plain=value=>JSON.parse(JSON.stringify(value));
function actualRestore(query,ages=[]){
 const a=source.indexOf('function parseURLParty('),b=source.indexOf('let compactSearchFrame=',a);assert(a>=0&&b>a);
 const defaults=source.match(/^const defaultFilters=.+$/m)?.[0];assert(defaults);
 const ctx={URLSearchParams,structuredClone,location:{search:'?'+query},countryNames:{4:'Default',101:'Египет',204:'Турция'},startDay:catalog.startDay,endDay:catalog.endDay,
  data:{catalog:{departures:catalog.departures},text:helpers.api.text,date:helpers.api.date,meal:String},
  state:{search:{origin:'Москва',country:'4',from:'2026-12-05',to:'2026-12-11',minNights:7,maxNights:7,adults:2,ages:[...ages]},sort:'recommended'},
  dateObj:s=>new Date(s+'T12:00:00Z'),urlPartyIssues:[],linkedAmenityLabels:null,restoreAmenityLabels:()=>new Map(),validIds:ids=>ids.filter(n=>Number.isSafeInteger(n)&&n>0),destinationIds:f=>f.hotelIds||[],setDestinationIds:()=>{},$:()=>({value:''}),draft:null};
 vm.createContext(ctx);vm.runInContext(defaults+'\nstate.filters=defaultFilters();\n'+source.slice(a,b)+'\nrestoreURL();',ctx);
 return plain({search:ctx.state.search,issues:ctx.urlPartyIssues});
}
const landing=new URLSearchParams({from:'2',country:'1',dateFrom:'2026-12-20',dateTo:'2026-12-24',daysFrom:'9',daysTill:'11',count_people:'3',yclid:'audit-only',utm_campaign:'семья & море'});
assert.deepEqual([...landing.keys()].filter(key=>!['yclid','utm_campaign'].includes(key)),publicContract.homeFields,'landing fixture uses the pinned main form field contract');
const expected={origin:'Санкт-Петербург',country:'101',from:'2026-12-20',to:'2026-12-24',minNights:9,maxNights:11,adults:3,ages:[]};
const mapped=convert(landing,catalog);assert(mapped.ok);assert.equal(mapped.autoSearch,true,'retained old explicit-search intent, no request is made');
const observed=actualRestore(process.argv.includes('--without-adapter')?landing.toString():mapped.query);
assert.deepEqual(observed.search,expected,'pinned main home query reaches current NEXT with every selected condition');
assert.deepEqual(observed.issues,[]);
const mappedParams=new URLSearchParams(mapped.query);assert.equal(mappedParams.get('yclid'),'audit-only');assert.equal(mappedParams.get('utm_campaign'),'семья & море');
assert(!mapped.query.includes('семья & море'),'query values encoded');assert(!mappedParams.has('dateFrom'));assert(!mappedParams.has('count_people'));

const countryOnly=convert('country=1',catalog);assert(countryOnly.ok);assert.equal(countryOnly.autoSearch,false);assert.equal(actualRestore(countryOnly.query).search.country,'101','native ID is resolved, never copied as a canonical ID');
const alias=new URLSearchParams(landing);alias.set('date_from','2026-12-21');alias.set('date_till','2026-12-25');alias.set('days_from','8');alias.set('days_till','10');
const aliasResult=actualRestore(convert(alias,catalog).query);assert.equal(aliasResult.search.from,'2026-12-21');assert.equal(aliasResult.search.to,'2026-12-25');assert.equal(aliasResult.search.minNights,8);assert.equal(aliasResult.search.maxNights,10);
for(const ages of [['0','8'],['8','8'],['0','17','17']]){
 const input=new URLSearchParams(landing);input.set('child_count',String(ages.length));ages.forEach(age=>input.append('child_age[]',age));
 const result=convert(input,catalog);assert(result.ok);assert.deepEqual(actualRestore(result.query).search.ages,ages.map(Number),'infant/repeated/upper age preserved');
}
const mixedAges=new URLSearchParams(landing);mixedAges.append('child_age[]','0');mixedAges.append('child_age','8');assert.deepEqual(actualRestore(convert(mixedAges,catalog).query).search.ages,[0,8]);
function rejected(query,code,loaded=catalog){const result=convert(query,loaded);assert.equal(result.ok,false,query);assert.equal(result.code,code,query);assert.equal(Object.hasOwn(result,'query'),false,'no usable partial query on unresolved terms');}
rejected('from=99&country=1','departure_unmapped');
rejected('from=2&country=1','departure_catalog_required',{...catalog,origin:'Москва'});
rejected('country=101','country_unmapped');
rejected('country=1','country_unmapped',{...catalog,countries:[...catalog.countries,{id:999,tourvisorIds:['1']}]});
rejected('country=1','catalog_required',null);
for(const query of ['child_count=2&child_age[]=8','child_count=4','child_count=0&child_age[]=8'])rejected(query,'party_incomplete');
for(const query of ['child_age[]=','child_age[]=18','child_age[]=oops','count_people=0'])rejected(query,'party_invalid');
rejected('dateFrom=2026-12-20','dates_invalid');rejected('dateFrom=2026-12-20&dateTo=2026-12-19','dates_invalid');
rejected('dateFrom=2027-02-29&dateTo=2027-03-01','dates_invalid');rejected('dateFrom=2026-12-01&dateTo=2026-12-23','dates_invalid');
rejected('dateFrom=2027-06-01&dateTo=2027-06-02','dates_outside_next_window');
rejected('daysFrom=9','nights_incomplete');rejected('daysFrom=9&daysTill=8','nights_invalid');rejected('daysFrom=1&daysTill=12','nights_invalid');
for(const key of ['hotel','region','subregion','food','stars','onlyDirect'])rejected('country=1&'+key+'=1','unresolved_filters');
rejected('origin=Москва&country=101&from=2026-12-20&to=2026-12-24','next_namespace');
const legacy=oldHydration();
for(const key of legacy.keys){const result=convert(new URLSearchParams([[key,'x']]),catalog);assert.notEqual(result.code,'unknown_parameters','actual old hydrate field is recognized: '+key);assert.notEqual(result.code,'next_namespace','shared legacy field is not reclassified as NEXT: '+key);}
const serialized=currentNextKeys();assert(serialized.includes('favorites')&&!serialized.includes('fav'),'actual NEXT favorite key');
for(const key of serialized.filter(key=>!['from','country'].includes(key)))rejected(new URLSearchParams([['from','2'],['country','1'],[key,'x']]),['hotel','stars','rating'].includes(key)?'unresolved_filters':'next_namespace');
for(const query of ['country=1&new_filter=beach','country=1&new_filter=&new_filter=beach','country=1&new_filter=%20','country=1&utm_id=unrecognized','country=1&fav=1','country=1&night_from=9','country=1&datefrom=2026-12-20','country=1&=unknown','country=1&%E0%A4%A=value'])rejected(query,'unknown_parameters');
for(const query of ['from=2&country=1&q=hotel','from=2&country=1&min=1','from=2&country=1&max=2','country=1&amenityLabels={}','country=1&favorites=1','country=1&sort=price','country=1&date=2026-12-20','country=1&q='])rejected(query,'next_namespace');
rejected('from=2026-12-20&country=1','departure_unmapped');
const emptyUnknown=convert('country=1&new_filter=',catalog);assert(emptyUnknown.ok);assert.equal(new URLSearchParams(emptyUnknown.query).has('new_filter'),false,'empty unknown parameter carries no requested condition');
assert.deepEqual(oldHydration('?child_count=').partyWrites,[],'actual old parser leaves the party untouched for an empty child_count without ages');
const emptyCount=convert('country=1&child_count=',catalog);assert(emptyCount.ok);assert.equal(new URLSearchParams(emptyCount.query).has('ages'),false,'empty child_count is not an explicit zero');assert.deepEqual(actualRestore(emptyCount.query,[0,8]).search.ages,[0,8],'empty child_count preserves the existing NEXT party');
const zeroCount=convert('country=1&child_count=0',catalog);assert(zeroCount.ok);assert.equal(new URLSearchParams(zeroCount.query).get('ages'),'','explicit zero still clears children');
assert.equal(landing.get('from'),'2','caller query untouched');assert.equal(catalog.countries[0].id,'101','canonical catalog untouched');
let comparedMainCases=0;
if(mainOld){
 const cases=['',landing.toString(),alias.toString(),mixedAges.toString(),'child_count=','child_count=0','child_count=2&child_age[]=8','child_count=3&child_age[]=8&child_age[]=8&child_age[]=17','count_people=0&child_age[]=18','country=1&from=2&hotel=123','country=1&region=5&subregion=6&food=3&only_direct=1&only_charter=true&hotel_service[]=8&hotel_service[]=8&hotel_service=10','yclid=audit-only&utm_campaign=fixture'];
 for(const query of cases){assert.deepEqual(oldHydration(query),oldHydration(query,mainOld),'current and pinned main legacy URL semantics: '+query);comparedMainCases++;}
}
assert.equal(requests.length,0,'no fetch/navigation/history writes');
console.log('PASS public URL mapping: pinned main '+publicContract.main.slice(0,8)+' form fixture -> current NEXT restore; '+legacy.keys.length+' current legacy inputs and '+serialized.length+' current NEXT output keys; '+(mainOld?'exact main files verified, '+comparedMainCases+' real legacy-owner comparisons':'main source comparison not requested')+'; unknown/mixed fail closed, party/crosswalk/attribution; no boot/network/history writes');
