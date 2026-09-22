'use strict';
// Pure read-state contract; all source identities and responses are fictional.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../v2/search3-canonical-profiles-v1.js'),'utf8');
const plain=value=>JSON.parse(JSON.stringify(value));
let checks=0;function check(value,message){assert.ok(value,message);checks++;}
function setup(route='/_preview/search3-local-candidate/poisk-turov/',clock={setTimeout,clearTimeout}){
 const events={},calls=[];let changes=0;const root={location:{pathname:route},addEventListener:(name,fn)=>events[name]=fn};
 root.fetch=(url,options)=>new Promise((resolve,reject)=>calls.push({url,options,resolve,reject}));
 vm.runInNewContext(source,{window:root,URLSearchParams,AbortController,setTimeout:clock.setTimeout,clearTimeout:clock.clearTimeout});
 const owner=root.Search3CanonicalProfilesV1.create(()=>changes++);
 return{owner,root,calls,events,get changes(){return changes;}};
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
 ctx=setup();let pending=ctx.owner.readProfile(901);
 check(ctx.calls.length===1,'A standalone profile uses one local read');
 const ownQuery=new URL(ctx.calls[0].url,'https://fixture.invalid').searchParams;
 check(ownQuery.toString()==='catalog=anytour&anytourHotelId=901','Own ID never enters the legacy namespace');
 check(ctx.calls[0].options.cache==='no-store'&&ctx.calls[0].options.credentials==='same-origin','Standalone read keeps local isolation');
 await reply(ctx,0,{ok:true,catalog:'anytour',source:'anytour-canonical-catalog',item:p(901)});await pending;
 check(ctx.owner.read([],{}).length===0,'A profile alone never becomes an ordinary search result');
 const stale={id:'old-exact-offer',provider:'tourvisor',price:125000};
 ctx.owner.upsertOffer(901,stale,{legacyHotelId:101,source:'tourvisor'});
 ctx.root.V2SearchLifecycle={hotelDetail:{hotelId:'901',profileOnly:true}};
 const standalone=ctx.owner.read([],{});
 check(standalone.length===1&&standalone[0].name==='Own 901'&&standalone[0].images[0]==='https://fixture.invalid/a.jpg','Own name and media survive without the search');
 check(standalone[0].tours.length===0&&standalone[0].price===0&&!standalone[0].canonicalOfferLinks.length&&!standalone[0].canonicalLegacyIds.length&&!standalone[0].providers.length,'Profile-only view has no historical price, offers or supplier authority');
 check(stale.price===125000&&ctx.calls.length===1,'Historical offer remains unchanged and no supplier lookup is launched');ctx.owner.reset();
 for(const alter of [r=>({...r,source:'tourvisor'}),r=>({...r,catalog:'tourvisor'}),r=>({...r,item:p(902)}),r=>({...r,item:p(901,0)}),r=>({...r,item:null})]){
  ctx=setup();const rejected=assert.rejects(ctx.owner.readProfile(901));
  await reply(ctx,0,alter({ok:true,catalog:'anytour',source:'anytour-canonical-catalog',item:p(901)}));await rejected;
  check(ctx.owner.details({anytourHotelId:901})===null&&ctx.calls.length===1,'Wrong identity/source cannot create a card or retry loop');ctx.owner.reset();
 }
 for(const status of [404,503]){ctx=setup();const rejected=assert.rejects(ctx.owner.readProfile(901));await reply(ctx,0,{ok:false},status);await rejected;check(ctx.owner.details({anytourHotelId:901})===null,'Unavailable local profile is not invented');ctx.owner.reset();}
 ctx=setup();pending=ctx.owner.readProfile(901);ctx.owner.reset();await reply(ctx,0,{ok:true,catalog:'anytour',source:'anytour-canonical-catalog',item:p(901)});
 check(await pending===null&&ctx.calls[0].options.signal.aborted&&ctx.owner.details({anytourHotelId:901})===null,'Late profile cannot survive a search reset');
 for(const invalid of ['0901','0','javascript:901',Number.MAX_SAFE_INTEGER+1])await assert.rejects(ctx.owner.readProfile(invalid));
 check(ctx.calls.length===1,'Invalid own IDs make no request');
 // Direct profile reads share the result-batch worker slots. A terminal search
 // must resume hydration when favourites finish, without another render/poll.
 for(const mode of ['success','http','invalid','network','timeout']){
  let nextTimer=0;const timers=new Map(),clock={setTimeout:fn=>{timers.set(++nextTimer,fn);return nextTimer;},clearTimeout:key=>timers.delete(key)};
  ctx=setup('/_preview/search3-local-candidate/prototype-search/',clock);
  const first=ctx.owner.readProfile(901),settled=mode==='success'?first:assert.rejects(first);
  const second=ctx.owner.readProfile(902),rows=Array.from({length:105},(_,i)=>h(2000+i)),saved=JSON.stringify(rows);
  try{
   check(ctx.owner.read(rows,{sort:'price'}).length===0,'Results await their own profiles during '+mode);await tick();
   check(ctx.calls.length===2&&ctx.changes===0,'Direct reads occupy both result-batch slots');
   if(mode==='network'){ctx.calls[0].reject(new Error('Fixture network error'));await tick();}
   else{
    if(mode==='timeout'){timers.get(1)();check(ctx.calls[0].options.signal.aborted,'Profile timeout aborts the request');}
    await reply(ctx,0,{ok:true,catalog:'anytour',source:'anytour-canonical-catalog',item:p(mode==='invalid'?999:901)},mode==='http'?503:200);
   }
   await settled;await tick();
   check(ctx.calls.length===3&&ids(ctx.calls[2]).length===100,'Freed slot resumes current result batch after '+mode+' without read/refresh');
   check(ctx.changes===0,'Standalone completion does not invent a result render');
   await reply(ctx,1,{ok:true,catalog:'anytour',source:'anytour-canonical-catalog',item:p(902)});await second;await tick();
   check(ctx.calls.length===4&&ids(ctx.calls[3]).length===5,'Second freed slot resumes only the remaining bounded batch');
   check(ids(ctx.calls[2]).concat(ids(ctx.calls[3])).join(',')===rows.map(row=>row.id).join(','),'Exact current legacy IDs once, no favourite ID or duplicate');
   check(ctx.calls.slice(2).every(call=>call.url.startsWith('/_preview/search3-local-candidate/data/hotel-details-read-v1.php?')&&call.options.cache==='no-store'&&call.options.credentials==='same-origin'),'Resumed batches retain the existing isolated catalogue contract');
   for(const index of [2,3])await reply(ctx,index,response(ids(ctx.calls[index]),Object.fromEntries(ids(ctx.calls[index]).map(key=>[key,key]))));
   const projected=ctx.owner.read(rows,{sort:'price'});
   check(projected.length===105&&projected.every((card,index)=>card.tours[0]===rows[index].tours[0]),'All eligible original offers project after hydration');
   check(JSON.stringify(rows)===saved&&ctx.calls.length===4&&ctx.changes===2,'No source mutation, extra lookup or refresh loop');
   check(timers.size===0,'All direct and batch timers are cleared');
  }finally{ctx.owner.reset();}
 }
 // A late old-generation direct read must neither hydrate nor wake new results.
 ctx=setup('/_preview/search3-local-candidate/prototype-search/');
 const oldProfile=ctx.owner.readProfile(901),oldError=assert.rejects(ctx.owner.readProfile(902));
 ctx.owner.read([h(102)],{});ctx.owner.reset();
 const currentA=ctx.owner.readProfile(903),currentB=ctx.owner.readProfile(904),currentRows=[h(106)];
 try{
  ctx.owner.read(currentRows,{});await tick();check(ctx.calls.length===4,'Current direct reads occupy their own generation slots');
  await reply(ctx,0,{ok:true,catalog:'anytour',source:'anytour-canonical-catalog',item:p(901)});ctx.calls[1].reject(new Error('Old request aborted'));await oldError;await tick();
  check(await oldProfile===null&&ctx.calls.length===4&&ctx.changes===0&&ctx.owner.details({anytourHotelId:901})===null,'Late success/error cannot resume or populate the new generation');
  await reply(ctx,2,{ok:true,catalog:'anytour',source:'anytour-canonical-catalog',item:p(903)});await currentA;await tick();
  check(ctx.calls.length===5&&ids(ctx.calls[4]).join(',')==='106','Only current result IDs resume when a current slot is released');
  await reply(ctx,3,{ok:true,catalog:'anytour',source:'anytour-canonical-catalog',item:p(904)});await currentB;
  await reply(ctx,4,response([106],{106:2}));
  check(ctx.owner.read(currentRows,{}).length===1&&ctx.calls.length===5&&ctx.changes===1,'Current generation completes once without stale offers');
 }finally{ctx.owner.reset();}
 console.log('SEARCH3_CANONICAL_PROFILES_OK checks='+checks+' supplier_calls=0 db_writes=0');
})().catch(e=>{console.error(e);process.exitCode=1;});
