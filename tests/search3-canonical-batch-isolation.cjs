'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(process.argv[2]||'v2/search3-canonical-profiles-v1.js','utf8');
const tick=()=>new Promise(resolve=>setImmediate(resolve));
function setup(refreshRows=null,pathname='/_preview/search3-local-candidate/prototype-search/'){
 const events={},calls=[];let changes=0;
 const root={location:{pathname},addEventListener:(name,fn)=>events[name]=fn};
 root.fetch=(url,options)=>new Promise((resolve,reject)=>calls.push({url,options,resolve,reject}));
 vm.runInNewContext(source,{window:root,document:{createElement:()=>({setAttribute(){},appendChild(){},addEventListener(){}})},URLSearchParams,AbortController,setTimeout,clearTimeout});
 const owner=root.Search3CanonicalProfilesV1.create(()=>{changes++;if(refreshRows)owner.read(refreshRows,{});});
 return {owner,root,calls,events,get changes(){return changes;}};
}
function h(id){return {id,provider:'tourvisor',mappingStatus:'resolved',tours:[{id:'tourvisor:'+id,provider:'tourvisor',price:100000+id}]};}
function p(id){return {id,catalog:'anytour',revision:1,name:'Hotel '+id,hotelInformation:{}};}
function ids(call){return new URL(call.url,'https://fixture.invalid').searchParams.getAll('legacyHotelIds[]').map(Number);}
function good(requested){
 return {ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:requested,items:requested.map(p),links:requested.map(id=>({legacyHotelId:id,anytourHotelId:id})),missingLegacyIds:[]};
}
function bad(requested){
 return {ok:true,source:'broken-catalogue',catalog:'anytour',requestedLegacyIds:requested,items:[],links:[],missingLegacyIds:requested};
}
async function reply(ctx,index,payload,status=200){ctx.calls[index].resolve({ok:status===200,status,json:async()=>payload});await tick();}
async function reject(ctx,index,error=new Error('fixture transport failure')){ctx.calls[index].reject(error);await tick();}

(async()=>{
 // One bad catalogue row must not poison valid siblings from the first union.
 let ctx=setup(),rows=[h(101),h(102),h(103),h(104)];
 assert.equal(ctx.owner.read(rows,{}).length,0);await tick();
 assert.deepEqual(ids(ctx.calls[0]),[101,102,103,104]);
 await reply(ctx,0,bad([101,102,103,104]));
 assert.equal(ctx.calls.length,3,'validation failure bisects once under the two-worker cap');
 assert.deepEqual(ids(ctx.calls[1]),[101,102]);
 assert.deepEqual(ids(ctx.calls[2]),[103,104]);
 await reply(ctx,1,good([101,102]));
 await reply(ctx,2,bad([103,104]));
 assert.equal(ctx.calls.length,5,'only the invalid half is bisected again');
 assert.deepEqual(ids(ctx.calls[3]),[103]);
 assert.deepEqual(ids(ctx.calls[4]),[104]);
 await reply(ctx,3,bad([103]));
 await reply(ctx,4,good([104]));
 const cards=ctx.owner.read(rows,{});
 assert.equal(JSON.stringify(Array.from(cards,card=>Number(card.anytourHotelId)).sort((a,b)=>a-b)),JSON.stringify([101,102,104]));
 assert.equal(ctx.calls.length,5,'bad singleton is terminal and does not loop');
 ctx.owner.reset();

 // Transport failures remain fail-closed for the whole requested subset and do not fan out.
 ctx=setup();rows=[h(201),h(202),h(203)];
 ctx.owner.read(rows,{});await tick();await reject(ctx,0);
 assert.equal(ctx.calls.length,1,'network error never starts validation-isolation retries');
 assert.equal(ctx.owner.read(rows,{}).length,0);
 ctx.owner.reset();

 // The validation retry budget is globally bounded per search generation.
 ctx=setup();rows=Array.from({length:100},(_,i)=>h(1001+i));
 ctx.owner.read(rows,{});await tick();
 let index=0;
 while(index<ctx.calls.length&&index<40){await reply(ctx,index,bad(ids(ctx.calls[index])));index++;}
 assert.ok(ctx.calls.length<=33,'one initial batch plus at most 32 isolation children');
 assert.equal(index,ctx.calls.length,'malformed catalogue eventually reaches terminal failed subsets');
 assert.equal(ctx.owner.read(rows,{}).length,0);
 ctx.owner.reset();

 // Reset aborts in-flight split children and prevents old-generation retry work.
 ctx=setup();rows=[h(301),h(302),h(303),h(304)];
 ctx.owner.read(rows,{});await tick();await reply(ctx,0,bad([301,302,303,304]));
 assert.equal(ctx.calls.length,3);
 const splitA=ctx.calls[1],splitB=ctx.calls[2];
 ctx.owner.reset();
 assert.equal(splitA.options.signal.aborted,true);
 assert.equal(splitB.options.signal.aborted,true);
 await reply(ctx,1,good([301,302]));
 await reply(ctx,2,good([303,304]));
 assert.equal(ctx.calls.length,3,'old split completion cannot enqueue more work');
 assert.equal(ctx.owner.read([],{}).length,0,'old split completion cannot publish profiles after reset');

 // Saturated workers must retain every split while refresh/read re-enters pump.
 // IDs and catalogue responses below are fictional; own and legacy IDs differ.
 for(const pathname of ['/_preview/search3-local-candidate/prototype-search/','/_preview/search3-next-candidate/visual-search/']){
  rows=Array.from({length:200},(_,i)=>h(i+1));ctx=setup(rows,pathname);
  const invalid=new Set([1,101]);
  const payloadFor=requested=>({
   ok:true,source:'anytour-canonical-catalog',catalog:'anytour',requestedLegacyIds:requested,
   items:requested.map(legacy=>({...p(10000+legacy),name:invalid.has(legacy)?'':'Hotel '+legacy,
    description:'Saved description '+legacy,primaryImage:'https://fixture.invalid/'+legacy+'.jpg',
    images:['https://fixture.invalid/'+legacy+'.jpg']})),
   links:requested.map(legacy=>({legacyHotelId:legacy,anytourHotelId:10000+legacy})),missingLegacyIds:[]
  });
  try{
   ctx.owner.read(rows,{});await tick();assert.equal(ctx.calls.length,2);
   let settled=0;
   while(settled<ctx.calls.length&&settled<40){
    const wave=ctx.calls.slice(settled);settled+=wave.length;
    assert.ok(wave.length<=2,'catalogue isolation retains the shared two-worker limit');
    // Additional renders while workers are occupied must not dequeue waiting halves.
    for(let i=0;i<3;i++)ctx.owner.read(rows,{});
    await tick();assert.equal(ctx.calls.length,settled,'no request while both slots are occupied');
    for(const call of wave){
     const requested=ids(call);assert.ok(requested.length>0&&requested.length<=100);
     const payload=payloadFor(requested);call.resolve({ok:true,status:200,json:async()=>payload});
    }
    await tick();
   }
   const visible=ctx.owner.read(rows,{});
   assert.equal(visible.length,198,'two invalid rows must not suppress the other 198 canonical cards');
   assert.equal(settled,ctx.calls.length,'all queued subsets finish, with no repeated invalid union');
   assert.ok(ctx.calls.length<=34,'two initial batches share the existing 32-child split budget');
   assert.equal(new Set(ctx.calls.map(call=>ids(call).join(','))).size,ctx.calls.length,'no lost split is regrouped and requested again');
   for(const card of visible){
    const legacy=Number(card.id);assert.ok(!invalid.has(legacy));
    assert.equal(card.anytourHotelId,10000+legacy);
    assert.equal(card.description,'Saved description '+legacy);
    assert.equal(card.primaryImage,'https://fixture.invalid/'+legacy+'.jpg');
    assert.equal(card.images[0],card.primaryImage);
    assert.equal(card.tours[0],rows[legacy-1].tours[0],'original supplier offer facts are unchanged');
   }
   console.log('parallel catalogue isolation: '+pathname+' 198/200; invalid=2; requests='+ctx.calls.length);
  }finally{ctx.owner.reset();}
 }

 console.log('search3 canonical batch isolation: ok');
})().catch(error=>{console.error(error);process.exit(1);});
