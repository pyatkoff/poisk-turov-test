// Ordered cold-list inventory and same-render work, without supplier/lead/DOM I/O.
'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=path.resolve(__dirname,'../v2/visual-search');
const source=fs.readFileSync(path.join(root,'offer-list-v1.js'),'utf8');
const app=fs.readFileSync(path.join(root,'app.js'),'utf8');
const keySource=app.match(/const offerGroupKey=[^;]+;/)?.[0];
const noteSource=app.match(/const sharedOfferNote=[\s\S]*?};/)?.[0];
assert(keySource&&noteSource,'use the actual unchanged application collaborators');
const helpers=vm.runInNewContext(keySource+noteSource+'({offerGroupKey,sharedOfferNote})',{offerMetaNote:o=>o.note});
const key=helpers.offerGroupKey;
function referenceInventory(hotels,hotelOffers,offerView,offerGroupKey){
 const h=hotels.find(h=>h.id===offerView.id),all=hotelOffers(h);
 const filtered=all.filter(o=>(offerView.mode==='compare'||!offerView.departure||o.day===offerView.departure)&&(!offerView.flight||o.flight===offerView.flight)&&(!offerView.room||o.room===offerView.room)&&(!offerView.meal||o.meal===offerView.meal));
 const sorted=[...filtered].sort((a,b)=>offerView.sort==='date'?a.day.localeCompare(b.day)||a.total-b.total:a.total-b.total||a.day.localeCompare(b.day));
 const groups=[...new Set(sorted.map(offerGroupKey))].map(key=>({key,offers:sorted.filter(o=>offerGroupKey(o)===key)}));
 return {h,all,filtered,groups};
}
function inventory(code,rows,view){
 const start=code.indexOf('function offerListInventory(){'),end=code.indexOf('function renderOfferList(',start);
 assert(start>=0&&end>start);
 let keyCalls=0;const h={id:1,rows},ctx={hotels:[h],hotelOffers:hotel=>hotel?.rows||[],offerView:view,offerGroupKey:o=>{keyCalls++;return key(o);}};
 vm.createContext(ctx);vm.runInContext(code.slice(start,end),ctx);
 return {value:ctx.offerListInventory(),keyCalls,h};
}
function sameReferences(actual,expected){
 assert.strictEqual(actual.h,expected.h);assert.strictEqual(actual.all,expected.all);
 assert.equal(actual.filtered.length,expected.filtered.length);
 actual.filtered.forEach((o,i)=>assert.strictEqual(o,expected.filtered[i]));
 assert.equal(actual.groups.length,expected.groups.length);
 actual.groups.forEach((g,i)=>{assert.equal(g.key,expected.groups[i].key);assert.equal(g.offers.length,expected.groups[i].offers.length);g.offers.forEach((o,j)=>assert.strictEqual(o,expected.groups[i].offers[j]));});
}
const baseView=()=>({id:1,mode:'list',sort:'price',departure:'',flight:'',room:'',meal:'',open:[],limits:{},pair:[],activeVariant:null});
const offer=(i,group=i%50)=>Object.freeze({key:'offer-'+i,variant:i,room:group===0?'__proto__':group===1?'Номер <&"':'room-'+group,meal:group%2?'AI':'BB',day:'2026-10-'+(10+i%3),returnDay:'2026-10-'+(17+i%3),total:100000+i%11,nights:7,flight:i%2?'charter':'regular',operator:'fixture',placement:'2',note:'same',raw:Object.freeze({id:i})});
const rows=Object.freeze(Array.from({length:1000},(_,i)=>offer(i)));
let cases=0;
for(const mode of ['list','compare'])for(const sort of ['date','price'])for(const departure of ['','2026-10-11','missing'])for(const flight of ['','charter'])for(const room of ['','room-3'])for(const meal of ['','AI']){
 const view={...baseView(),mode,sort,departure,flight,room,meal};
 const actual=inventory(source,rows,view),expected=referenceInventory([actual.h],h=>h.rows,view,key);
 sameReferences(actual.value,expected);assert.equal(actual.keyCalls,expected.filtered.length,'exactly one key per retained offer');cases++;
}
const sparse=[];sparse.length=9;sparse[2]=offer(2);sparse[6]=sparse[2];
const proto=Object.create(Array.prototype);proto[4]=offer(4);Object.setPrototypeOf(sparse,proto);Object.freeze(sparse);
for(const special of [Object.freeze([]),Object.freeze([offer(0)]),sparse,Object.freeze([offer(1),offer(0),offer(1)]),Object.freeze([{...offer(0),room:'a|b',meal:'c'},{...offer(1),room:'a',meal:'b|c'}])]){
 for(const sort of ['price','date']){
  const view={...baseView(),sort},actual=inventory(source,special,view);
  sameReferences(actual.value,referenceInventory([actual.h],h=>h.rows,view,key));cases++;
 }
}
const measured=inventory(source,rows,baseView());let beforeCalls=0;
referenceInventory([measured.h],h=>h.rows,baseView(),o=>{beforeCalls++;return key(o);});
assert.equal(beforeCalls,51000);assert.equal(measured.keyCalls,1000);
// Render both actual owner variants against the same deterministic DOM boundary.
// The reference restores ONLY the old repeated note call; all markup stays actual.
const hoisted=" const commonNote=groups.length?sharedOfferNote(all):'';\n";
assert(source.includes(hoisted));
const legacyNotes=source.replace(hoisted,'').replace('  const rows=offers.slice','  const commonNote=sharedOfferNote(all);\n  const rows=offers.slice');
const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const unesc=v=>v.replace(/&quot;|&#39;|&lt;|&gt;|&amp;/g,c=>({'&amp;':'&','&lt;':'<','&gt;':'>','&quot;':'"','&#39;':"'"}[c]));
function render(code,all,view,shortlist=false,reset=false){
 const dom=new Map(),events=[];let noteCalls=0,noteVisits=0;
 const node=name=>{if(!dom.has(name)){
  let html='';const classes=new Set(),style={},label={hidden:false},n={hidden:false,open:false,textContent:'',value:'',options:[{value:'',textContent:'Any',dataset:{}}],classList:{contains:c=>classes.has(c),toggle:(c,on)=>on?classes.add(c):classes.delete(c)},style:{setProperty:(k,v)=>{style[k]=v;}},closest:()=>label};
  Object.defineProperty(n,'innerHTML',{get:()=>html,set:v=>{html=v;if(name.startsWith('#offer-'))n.options=[...v.matchAll(/<option value="([^"]*)"[^>]*>(.*?)<\/option>/g)].map(m=>({value:unesc(m[1]),textContent:unesc(m[2]),dataset:{}}));}});
  n.snapshot=()=>({html,hidden:n.hidden,open:n.open,text:n.textContent,value:n.value,options:n.options,classes:[...classes],style,label});dom.set(name,n);
 }return dom.get(name);};
 const context={$:node,$$:()=>[],hotels:[{id:1,resort:'Resort'}],hotelOffers:()=>all,offerGroupKey:key,offerView:structuredClone(view),offerRefinementFields:['departure','flight','room','meal'],innerWidth:1440,optionalShortlistEnabled:shortlist,state:{search:{origin:'Москва',from:'2026-10-10',minNights:7}},mealNames:{},esc,
  sharedOfferNote:input=>{assert.strictEqual(input,all,'shared note uses all, not filtered/group rows');noteCalls++;noteVisits+=input.length;return helpers.sharedOfferNote(input);},offerMetaNote:o=>o.note,
  mealLabel:o=>o.meal,flightLabel:o=>o.flight,needsRefresh:()=>false,cardPriceNote:()=>'',dateText:String,nightsText:String,offerCountText:String,money:String,rangeText:(a,b)=>a+'/'+b,durationText:()=>'',guestsText:()=>'',icon:()=>'',offerActionLabel:()=>'',offerSearchContext:()=>'',operatorBadge:String,selectionStepsHTML:()=>'',rememberUIRoute:()=>events.push('route'),renderComparisonFooter:()=>events.push('comparison'),setComparisonQuotes:values=>events.push(['quotes',values.map(o=>o.key)])};
 const sandbox={window:{}};vm.createContext(sandbox);vm.runInContext(code,sandbox);
 const api=sandbox.window.AnyTourOfferList.create(context);api.renderOfferList(reset);
 return {snapshot:JSON.parse(JSON.stringify({dom:[...dom].map(([k,n])=>[k,n.snapshot()]),events,view:context.offerView})),noteCalls,noteVisits};
}
let renders=0;
for(const all of [rows.slice(0,100),rows.slice(0,1),[],rows.slice(0,25).map((o,i)=>({...o,note:i%2?'different':'same'}))])for(const mode of ['list','compare'])for(const sort of ['price','date'])for(const departure of ['','missing'])for(const reset of [false,true]){
 const view={...baseView(),mode,sort,departure},before=render(legacyNotes,all,view,true,reset),after=render(source,all,view,true,reset);
 assert.deepEqual(after.snapshot,before.snapshot,'render output and view state unchanged');
 assert.equal(after.noteCalls,before.noteCalls?1:0,'one same-render note, zero for empty/comparison');renders++;
}
const before=render(legacyNotes,rows,baseView()),after=render(source,rows,baseView());
assert.equal(before.noteCalls,50);assert.equal(after.noteCalls,1);assert.equal(before.noteVisits,50000);assert.equal(after.noteVisits,1000);
assert.deepEqual(after.snapshot,before.snapshot);
const reversed=source.replace('groups.push(group)','groups.unshift(group)');assert.notEqual(reversed,source);
const reversedResult=inventory(reversed,rows,baseView());
assert.throws(()=>sameReferences(reversedResult.value,referenceInventory([reversedResult.h],h=>h.rows,baseView(),key)),'reversed group order mutation detected');
const copied=source.replace('group.offers.push(offer)','group.offers.push({...offer})');assert.notEqual(copied,source);
const copiedResult=inventory(copied,rows,baseView());
assert.throws(()=>sameReferences(copiedResult.value,referenceInventory([copiedResult.h],h=>h.rows,baseView(),key)),'raw identity mutation detected');
console.log(`PASS cold offer-list inventory: ${cases} reference cases; ${renders} render states; key calls ${beforeCalls}→${measured.keyCalls}; shared note calls ${before.noteCalls}→${after.noteCalls}, visits ${before.noteVisits}→${after.noteVisits}; supplier/lead HTTP 0`);
