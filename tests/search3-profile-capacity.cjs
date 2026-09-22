'use strict';
// Run the actual client with fictional profiles/transport. No supplier or DB writes.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../v2/search3-canonical-profiles-v1.js'),'utf8');
let checks=0;
function check(condition,label){assert.ok(condition,label);checks++;}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
const profile=id=>({id,catalog:'anytour',revision:1,name:'Fictional hotel '+id});
const payload=id=>({ok:true,catalog:'anytour',source:'anytour-canonical-catalog',item:profile(id)});
const row=id=>({id,provider:'tourvisor',tours:[{id:'fictional-'+id,provider:'tourvisor',date:'2099-10-01',nights:7,price:123000+id}]});
const ids=call=>new URL(call.url,'https://fixture.invalid').searchParams.getAll('legacyHotelIds[]').map(Number);
function setup(){
 const calls=[],timers=new Map();let seq=0,active=0,peak=0,changes=0;
 const root={location:{pathname:'/_preview/search3-local-candidate/prototype-search/'},addEventListener(){}};
 root.fetch=(url,options)=>new Promise((resolve,reject)=>{
  const call={url,options,done:false};calls.push(call);active++;peak=Math.max(peak,active);
  function finish(fn,value){if(call.done)return;call.done=true;active--;fn(value);}
  call.resolve=response=>finish(resolve,response);call.reject=error=>finish(reject,error);
 });
 vm.runInNewContext(source,{window:root,document:{},URLSearchParams,AbortController,
  setTimeout:fn=>{timers.set(++seq,fn);return seq;},clearTimeout:key=>timers.delete(key)});
 const owner=root.Search3CanonicalProfilesV1.create(()=>changes++);
 return{root,owner,calls,timers,get active(){return active;},get peak(){return peak;},get changes(){return changes;}};
}
async function deliver(ctx,call,value,status=200){call.resolve({ok:status===200,status,json:async()=>value});await tick();}
async function direct(ctx,call){const key=Number(new URL(call.url,'https://fixture.invalid').searchParams.get('anytourHotelId'));await deliver(ctx,call,payload(key));}
async function batch(ctx,call){const requested=ids(call);await deliver(ctx,call,{ok:true,catalog:'anytour',source:'anytour-canonical-catalog',requestedLegacyIds:requested,items:requested.map(profile),links:requested.map(key=>({legacyHotelId:key,anytourHotelId:key})),missingLegacyIds:[]});}
async function cleanup(ctx){ctx.owner.reset();for(const call of ctx.calls)if(!call.done)call.resolve({ok:true,json:async()=>({})});await tick();check(ctx.timers.size===0,'reset/settlement clears active timers');}
(async()=>{
 {
  const ctx=setup(),rows=Array.from({length:105},(_,i)=>row(1001+i)),before=JSON.stringify(rows);
  const reads=Array.from({length:20},(_,i)=>ctx.owner.readProfile(901+i));
  const duplicate=ctx.owner.readProfile('920');
  try{
   check(ctx.calls.length===2,'20 distinct favourites start only two HTTP reads');
   check(ctx.timers.size===2,'queued favourites do not start network timers');
   ctx.owner.read(rows,{});await tick();check(ctx.calls.length===2,'current results respect the same active limit');
   await direct(ctx,ctx.calls[0]);await reads[0];
   check(ctx.calls.length===3&&ids(ctx.calls[2]).length===100,'first released slot starts current result batch before waiting favourites');
   await direct(ctx,ctx.calls[1]);await reads[1];
   check(ctx.calls.length===4&&ids(ctx.calls[3]).length===5,'second slot starts only remaining current result IDs');
   check(ids(ctx.calls[2]).concat(ids(ctx.calls[3])).join(',')===rows.map(x=>x.id).join(','),'no dropped or duplicate result IDs');
   await batch(ctx,ctx.calls[2]);
   check(ctx.calls.length===5&&ctx.calls[4].url.includes('anytourHotelId=903'),'favourites resume without another external read after a batch');
   await batch(ctx,ctx.calls[3]);
   for(let index=4;index<ctx.calls.length;index++)await direct(ctx,ctx.calls[index]);
   const resolved=await Promise.all(reads),same=await duplicate,projected=ctx.owner.read(rows,{});
   check(resolved.length===20&&resolved.every((p,i)=>p.id===901+i),'every queued favourite settles with the correct profile');
   check(same===resolved[19],'queued duplicate shares the exact same resolved profile');
   check(ctx.calls.length===22&&ctx.peak===2&&ctx.active===0,'20 profiles plus two batches, at most two active requests');
   check(projected.length===105&&projected.every((p,i)=>p.tours[0]===rows[i].tours[0]),'current cards keep exact original offers');
   check(JSON.stringify(rows)===before&&ctx.changes===2,'no mutation of prices/source rows or extra renders');
   check(ctx.timers.size===0,'all timers cleared after the queue drains');
   const fresh=ctx.owner.readProfile(920);check(ctx.calls.length===23,'later explicit read rechecks freshness');await direct(ctx,ctx.calls[22]);await fresh;
  }finally{await cleanup(ctx);}
 }
 for(const mode of ['network','http','json','identity','revision','timeout']){
  const ctx=setup(),first=ctx.owner.readProfile(901),rejected=assert.rejects(first),second=ctx.owner.readProfile(902),third=ctx.owner.readProfile(903),thirdTwin=ctx.owner.readProfile('903');
  try{
   check(ctx.calls.length===2,'capacity before '+mode);
   if(mode==='network')ctx.calls[0].reject(new Error('Fictional network error'));
   else if(mode==='timeout'){ctx.timers.get(1)();check(ctx.calls[0].options.signal.aborted,'timeout aborts only the active request');ctx.calls[0].reject(new Error('Aborted'));}
   else if(mode==='json')ctx.calls[0].resolve({ok:true,json:async()=>{throw new Error('Invalid JSON');}});
   else{const data=payload(mode==='identity'?999:901);if(mode==='revision')data.item.revision=0;await deliver(ctx,ctx.calls[0],data,mode==='http'?503:200);}
   await rejected;await tick();
   check(ctx.calls.length===3&&ctx.calls[2].url.includes('anytourHotelId=903'),'failed slot resumes exactly one queued profile after '+mode);
   check(ctx.owner.details({anytourHotelId:901})===null,'failure never invents a profile');
   const retry=ctx.owner.readProfile(901);check(ctx.calls.length===3,'explicit retry waits for capacity rather than bypassing it');
   await direct(ctx,ctx.calls[1]);await second;
   check(ctx.calls.length===4&&ctx.calls[3].url.includes('anytourHotelId=901'),'explicit retry uses the freed slot');
   await direct(ctx,ctx.calls[2]);check(await third===await thirdTwin,'queued single-flight survives sibling failure');
   await direct(ctx,ctx.calls[3]);await retry;
   check(ctx.calls.length===4&&ctx.peak===2&&ctx.timers.size===0,'no automatic retry loop or leaked timer after '+mode);
  }finally{await cleanup(ctx);}
 }
 {
  const ctx=setup(),old=Array.from({length:20},(_,i)=>ctx.owner.readProfile(901+i));
  try{
   ctx.owner.reset();
   check((await Promise.all(old.slice(2))).every(value=>value===null),'reset immediately settles all not-started readers');
   check(ctx.calls.length===2&&ctx.calls.every(call=>call.options.signal.aborted),'queued readers never reach transport during reset');
   const fresh=ctx.owner.readProfile(901),same=ctx.owner.readProfile('901');
   check(ctx.calls.length===3,'new epoch starts a fresh same-ID request');
   await direct(ctx,ctx.calls[0]);await direct(ctx,ctx.calls[1]);
   check((await Promise.all(old.slice(0,2))).every(value=>value===null),'old active success resolves as stale');
   const sameAgain=ctx.owner.readProfile(901);check(ctx.calls.length===3,'late old completion cannot delete the current pending entry');
   check(ctx.owner.details({anytourHotelId:901})===null&&ctx.changes===0,'old epoch does not hydrate current cards or wake stale work');
   await direct(ctx,ctx.calls[2]);check(await fresh===await same&&await same===await sameAgain,'current epoch dedupe remains correct');
   check(ctx.calls.length===3,'reset does not replay discarded favourites');
  }finally{await cleanup(ctx);}
 }
 {
  const ctx=setup(),rows=Array.from({length:205},(_,i)=>row(2001+i));
  try{
   ctx.owner.read(rows,{});const favourite=ctx.owner.readProfile(901);await tick();
   check(ctx.calls.length===2&&ctx.calls.every(call=>ids(call).length===100),'initial batches prevent direct profile from bypassing capacity');
   ctx.calls[0].reject(new Error('Fictional batch failure'));await tick();
   check(ctx.calls.length===3&&ids(ctx.calls[2]).length===5,'one failed batch does not block other current result IDs');
   await batch(ctx,ctx.calls[1]);check(ctx.calls.length===4&&ctx.calls[3].url.includes('anytourHotelId=901'),'favourite resumes after pending batches have started');
   await batch(ctx,ctx.calls[2]);await direct(ctx,ctx.calls[3]);await favourite;
   check(ctx.owner.read(rows,{}).length===105,'valid result batches survive a sibling failure');
   await tick();check(ctx.calls.length===4&&ctx.peak===2,'rejected result IDs are not retried automatically');
  }finally{await cleanup(ctx);}
 }
 {
  const ctx=setup();let attempts=0;
  ctx.root.V2Runtime={fetch(){attempts++;throw new Error('Synchronous fixture failure');}};
  await assert.rejects(ctx.owner.readProfile(901));await assert.rejects(ctx.owner.readProfile(901));
  check(attempts===2&&ctx.timers.size===0,'synchronous failure releases capacity and permits explicit retry');
  await assert.rejects(ctx.owner.readProfile('0901'));check(attempts===2,'strict invalid ID never reaches transport');
  await cleanup(ctx);
 }
 console.log('SEARCH3_PROFILE_CAPACITY_OK checks='+checks+' supplier_calls=0 db_writes=0');
})().catch(error=>{console.error(error);process.exitCode=1;});
