'use strict';
// Pure read-state contract; all source identities and responses are fictional.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../v2/search3-canonical-profiles-v1.js'),'utf8');
const plain=value=>JSON.parse(JSON.stringify(value));
let checks=0;function check(value,message){assert.ok(value,message);checks++;}
function setup(route='/_preview/search3-local-candidate/poisk-turov/'){
 const events={},calls=[];let changes=0;const root={location:{pathname:route},addEventListener:(name,fn)=>events[name]=fn};
 root.fetch=(url,options)=>new Promise((resolve,reject)=>calls.push({url,options,resolve,reject}));
 vm.runInNewContext(source,{window:root,URLSearchParams,AbortController,setTimeout,clearTimeout});
 const owner=root.Search3CanonicalProfilesV1.create(()=>changes++);
 return{owner,calls,events,get changes(){return changes;}};
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
const h=(key,provider='tourvisor')=>({id:key,provider,mappingStatus:'resolved',name:'Supplier text',tours:[{id:provider+':offer:'+key,price:100000,provider}]});
const p=(key,revision=1)=>({id:key,catalog:'anytour',revision,name:'Own '+key,description:'Own description',images:['https://fixture.invalid/a.jpg'],hotelInformation:{services:['Kids club'],roomTypes:'Descriptive, NOT mapped'}});
function response(ids,links,missing=[],items){return {ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:ids,items:items||[...new Set(Object.values(links))].map(x=>p(x)),links:Object.entries(links).map(([a,b])=>({legacyHotelId:+a,anytourHotelId:b})),missingLegacyIds:missing};}
async function reply(ctx,index,value,status=200){ctx.calls[index].resolve({ok:status===200,status,json:async()=>value});await tick();}
(async()=>{
 for(const route of ['/poisk-turov/','/_preview/search3-site-candidate/','/_preview/search3-local-candidate-evil/','/nested/_preview/search3-local-candidate/']){const ctx=setup(route);check(ctx.owner===null&&!ctx.calls.length&&!Object.keys(ctx.events).length,'Off-route has no reader or listener');}
 let ctx=setup();const hotels=[h(102),h(106,'anex'),h(108,'andromeda')],before=JSON.stringify(hotels);
 check(ctx.owner.read(hotels,{}).length===0,'Withhold until own profiles resolve');await tick();
 check(ctx.calls.length===1,'One batch');check(new URL(ctx.calls[0].url,'https://fixture.invalid').searchParams.getAll('legacyHotelIds[]').join(',')==='102,106,108','Exact legacy input');
 check(ctx.calls[0].options.cache==='no-store'&&ctx.calls[0].options.credentials==='same-origin','Read isolation');
 await reply(ctx,0,response([102,106,108],{102:1,106:1,108:1}));const cards=ctx.owner.read(hotels,{});
 check(cards.length===1&&cards[0].anytourHotelId===1&&cards[0].id===102,'Own grouping and separate legacy anchor');
 check(cards[0].name==='Own 1'&&!('mappingStatus' in cards[0]),'Own profile, no supplier metadata');
 check(cards[0].tours.every((t,i)=>t===hotels[i].tours[0]),'Source objects retained');check(JSON.stringify(hotels)===before,'Inputs unchanged');
 check(plain(cards[0].canonicalLegacyIds).join(',')==='102,106,108','Expansion keeps every original scope');
 check(ctx.owner.details(cards[0]).services[0]==='Kids club','Own descriptive data projected');
 check(ctx.owner.read(hotels.slice().reverse(),{})[0].id===102,'Stable anchor after source order change');
 check(ctx.owner.read([hotels[1]],{})[0].tours[0]===hotels[1].tours[0],'Removed source does not leave stale offers');
 check(ctx.calls.length===1,'Renders do not refetch');ctx.owner.reset();
 check(ctx.owner.source().length===0&&ctx.owner.details(cards[0])===null,'Reset clears profiles and source');
 const bad=[{...h(1),mappingStatus:'pending'}, {...h(2,'andromeda'),mappingStatus:'conflict'}, {...h(3,'anex'),mappingStatus:undefined}, {...h(4),id:'04'}, {...h(5),id:0}, {...h(6),id:Number.MAX_SAFE_INTEGER+1}, {...h(7),provider:'unknown'}];
 check(ctx.owner.read(bad,{}).length===0,'Unconfirmed identities withheld');await tick();check(ctx.calls.length===1,'No requests for unresolved supplier IDs');
 ctx=setup();ctx.owner.read([h(102)],{});await tick();ctx.events['v2:search-reset']();ctx.owner.read([h(106)],{});await tick();
 await reply(ctx,0,response([102],{102:1}));check(ctx.changes===0&&ctx.calls[0].options.signal.aborted,'Stale reply cannot refresh');
 await reply(ctx,1,response([106],{106:2}));check(ctx.owner.read([h(106)],{})[0].anytourHotelId===2,'Current generation resolves');ctx.owner.reset();
 for(const alter of [r=>({...r,requestedLegacyIds:[999]}),r=>({...r,links:[{legacyHotelId:999,anytourHotelId:1}]}),r=>({...r,missingLegacyIds:[102]}),r=>({...r,items:[p(1),p(1)]}),r=>({...r,items:[p(1),p(2)]}),r=>({...r,catalog:'tourvisor'}),r=>({...r,items:[p(1,0)]}),r=>({...r,items:[{...p(1),name:''}]})]){
  ctx=setup();ctx.owner.read([h(102)],{});await tick();await reply(ctx,0,alter(response([102],{102:1})));
  check(ctx.owner.read([h(102)],{}).length===0,'Malformed response never partially published');check(ctx.calls.length===1,'Malformed response not retried in a loop');ctx.owner.reset();
 }
 ctx=setup();const many=Array.from({length:205},(_,i)=>h(1000+i));ctx.owner.read(many,{});await tick();check(ctx.calls.length===2,'Maximum two workers');
 const ids=call=>new URL(call.url,'https://fixture.invalid').searchParams.getAll('legacyHotelIds[]').map(Number);
 check(ctx.calls.every(call=>ids(call).length===100),'Maximum 100 IDs');
 await reply(ctx,0,response(ids(ctx.calls[0]),Object.fromEntries(ids(ctx.calls[0]).map(x=>[x,x]))));ctx.owner.read(many,{});await tick();check(ctx.calls.length===3&&ids(ctx.calls[2]).length===5,'Next bounded batch');
 await reply(ctx,1,response(ids(ctx.calls[1]),Object.fromEntries(ids(ctx.calls[1]).map(x=>[x,x]))));await reply(ctx,2,response(ids(ctx.calls[2]),Object.fromEntries(ids(ctx.calls[2]).map(x=>[x,x]))));
 check(ctx.owner.read(many,{}).length===205,'All profiles preserved');ctx.owner.reset();
 ctx=setup();ctx.owner.read([h(102)],{});await tick();await reply(ctx,0,response([102],{102:1}));ctx.owner.read([h(102),h(106)],{});await tick();
 await reply(ctx,1,response([106],{106:1},[],[{...p(1),name:'Conflicting same revision'}]));
 check(ctx.owner.read([h(102),h(106)],{}).length===1&&ctx.owner.read([h(102),h(106)],{})[0].tours.length===1,'Same-revision drift rejects new linkage');ctx.owner.reset();
 console.log('SEARCH3_CANONICAL_PROFILES_OK checks='+checks+' supplier_calls=0 db_writes=0');
})().catch(e=>{console.error(e);process.exitCode=1;});
