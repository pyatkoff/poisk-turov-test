'use strict';
// Behavioral regression for the canonical Andromeda page/coverage state.
// All provider HTTP is mocked. Empty price pages deliberately isolate coverage
// from money/offer normalization; existing inventory tests own offer retention.
const fs=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const path=require('node:path');
function readCanonicalFunctions(){
 const file=path.join(__dirname,'../v2/prototype-search/data.js');
 const text=fs.readFileSync(file,'utf8');
 const start=text.indexOf('  function andromedaBranch(');
 const end=text.indexOf('  async function enrichAndromeda(',start);
 assert(start>=0&&end>start,'canonical Andromeda coverage functions must be present');
 return text.slice(start,end);
}
function harness(code,handler){
 const events=[],calls=[];
 const run={generation:1,sourceCounts:{},controller:new AbortController(),active:true};
 const sandbox={Map,Set,Number,Array,Error,Object,JSON,AbortController,
  owner:{clearOffers(){},upsertLegacyOffer(){},refresh(){}},
  current:r=>r===run&&run.active,notify:e=>events.push(structuredClone(e)),clearCalendarWindows(){},
  // Empty price-page fixtures exercise only actual envelope/coverage/continuation code.
  directAndromedaOffer(){throw new Error('unexpected offer normalization');},
  fetch:async(url,options)=>{
    const body=JSON.parse(options.body);calls.push(body);
    const response=handler?handler(body):payload(body.params,body.page);
    if(response instanceof Error)throw response;
    return {ok:true,json:async()=>({ok:true,data:response})};
  }
 };
 vm.createContext(sandbox);
 vm.runInContext(code,sandbox);
 const seed=async(i,total,pages=3,status='complete')=>{
   const p={dateFrom:'2026-10-01',dateTo:'2026-10-07',branch:i,pages};
   await sandbox.applyDirectAndromeda(run,payload(p,1,status),p,i,total);
 };
 return {run,events,calls,seed,
  status:()=>sandbox.andromedaProviderStatus(run,false),
  more:()=>sandbox.andromedaContinuationAvailable(run),
  next:()=>sandbox.continueDirectAndromeda(run,'https://fixture.invalid/')};
}
function payload(p,page,status='complete'){
 return {provider:'andromeda',generation:1,hotels:[],date_range:{from:p.dateFrom,to:p.dateTo},
  search_ref:String(p.branch+1).repeat(64),page,pages_count:p.pages,status,selection_enabled:false,first_page_only:false};
}
const cases=[
 ['valid-first-page-ready-no-auto-request',async(code)=>{
  const h=harness(code);await h.seed(0,1,3);assert.equal(h.status(),'ready');assert.equal(h.calls.length,0);assert.equal(h.more(),true);
 }],
 ['fully-drained-success-complete',async(code)=>{
  const h=harness(code);await h.seed(0,1,1);assert.equal(h.status(),'complete');assert.equal(h.more(),false);
 }],
 ['partial-first-page-without-more-is-not-complete',async(code)=>{
  const h=harness(code);await h.seed(0,1,1,'partial');assert.equal(h.status(),'partial');
 }],
 ['partial-first-page-with-more-is-not-ready',async(code)=>{
  const h=harness(code);await h.seed(0,1,3,'partial');assert.equal(h.status(),'partial');assert.equal(h.more(),true);
 }],
 ['initial-failed-destination-remains-visible-after-sibling-page',async(code)=>{
  const h=harness(code);await h.seed(1,2,2);
  h.run.sourceCounts.andromeda.destinationBranchFailed=true;
  await h.next();assert.equal(h.events.at(-1).status,'partial');
  assert.equal(h.run.sourceCounts.andromeda.destinationBranchFailed,true);
  assert.equal(h.run.andromedaBranches.get(1).pages.size,2);
 }],
 ['failed-continuation-sticky-across-two-successful-sibling-pages',async(code)=>{
  const h=harness(code,body=>body.params.branch===0&&body.page===2?new Error('fixture-only'):payload(body.params,body.page));
  await h.seed(0,2,3);await h.seed(1,2,3);
  await h.next();assert.equal(h.events.at(-1).status,'partial');
  await h.next();assert.equal(h.events.at(-1).status,'partial');
  assert.equal(h.run.sourceCounts.andromeda.continuationFailed,true);
  assert.equal(h.run.andromedaBranches.get(0).pages.size,1);
  assert.equal(h.run.andromedaBranches.get(1).pages.size,3);
  assert.deepEqual(h.calls.map(b=>[b.params.branch,b.page]),[[0,2],[1,2],[1,3]]);
 }],
 ['one-next-page-per-branch-per-click',async(code)=>{
  const h=harness(code);await h.seed(0,2,3);await h.seed(1,2,3);
  await h.next();assert.deepEqual(h.calls.map(b=>[b.params.branch,b.page]),[[0,2],[1,2]]);
  assert.equal(h.more(),true);assert.equal(h.events.at(-1).status,'ready');
 }],
 ['invalid-page-count-retains-page1-and-seals-failed-branch',async(code)=>{
  const h=harness(code,body=>({...payload(body.params,body.page),pages_count:0}));
  await h.seed(0,1,3);await h.next();assert.equal(h.events.at(-1).status,'partial');
  assert.equal(h.run.andromedaBranches.get(0).pages.size,1);assert.equal(h.more(),false);
  await h.next();assert.equal(h.calls.length,1);assert.equal(h.events.at(-1).status,'partial');
 }],
 ['stopped-run-makes-no-continuation-request',async(code)=>{
  const h=harness(code);await h.seed(0,1,3);h.run.active=false;await h.next();assert.equal(h.calls.length,0);
 }]
];
async function runCoverageTests(){
 const code=readCanonicalFunctions(),failures=[];
 for(const [name,check] of cases){
  try{await check(code);console.log('PASS continuation coverage: '+name);}
  catch(error){failures.push({name,error});console.error('FAIL continuation coverage: '+name+'\n'+error.message);}
 }
 assert.equal(failures.length,0,failures.map(({name})=>name).join(', '));
 return {checks:cases.length,providerHttp:0,databaseWrites:0};
}
module.exports=runCoverageTests;
if(require.main===module)runCoverageTests().catch(error=>{console.error(error);process.exitCode=1;});
