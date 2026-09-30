// Characterize the actual exported bind owner. No renderer substitute or I/O.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),crypto=require('node:crypto');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/flight-picker-v18.js'),'utf8');
function model(code,{width=1280,count=24,selected=0,sort='default',uniform=false}={}){
 const trace=[],controls=new Map();
 function node(key){return {key,value:'',checked:false,hidden:false,textContent:'',children:[],handlers:{},setAttribute(k,v){this[k]=v;},addEventListener(k,fn){this.handlers[k]=fn;},focus(options){trace.push(['focus',key,options]);},scrollIntoView(options){trace.push(['scroll',key,options]);},append(...nodes){this.children.push(...nodes);},replaceChildren(...nodes){this.children=[...nodes];}};}
 const rows=Array.from({length:count},(_,i)=>{
  const row=node('row'+i),input=node('input'+i),difference=node('difference'+i);input.name='flight-pair';input.value=String(i);input.checked=i===selected;input.closest=()=>row;
  row.dataset={flightIndex:String(i),flightPrice:i%5?String(100000+(count-i)*100):'',flightSearch:uniform?'ЁЖ AIR AAA RETURN':(i%2?'EXAMPLE AIR BBB':'ЁЖ AIR AAA')+' RETURN '+i,flightDirect:String(i%3===0),flightBaggage:String(i%4!==0),flightForwardTime:i%2?'morning':'night',flightBackwardTime:i%3?'evening':'morning'};
  row.querySelector=s=>s==='input:checked'?(input.checked?input:null):s==='input'?input:s==='[data-flight-difference]'?difference:null;row.input=input;row.difference=difference;return row;
 });
 const list=node('list');list.children=[...rows];list.append=row=>{list.children=list.children.filter(x=>x!==row);list.children.push(row);};
 list.querySelector=s=>s==='input:checked'?rows.find(r=>r.input.checked)?.input:null;
 list.querySelectorAll=s=>list.children.filter(r=>s==='.flight-option'||s==='.flight-option:not([hidden])'&&!r.hidden||s==='.flight-option[data-flight-match="true"]'&&r.dataset.flightMatch==='true');
 const keys=['.flight-filter-panel','.flight-filter-state','[data-flight-query]','[data-flight-selected]','[data-flight-load-more]','[data-flight-filter="direct"]','[data-flight-filter="baggage"]','[data-flight-sort]','[data-flight-time=forward]','[data-flight-time=backward]','[data-flight-time-count]','[data-flight-filter-count]','[data-flight-show-results]'];
 for(const key of keys)controls.set(key,node(key));controls.get('[data-flight-sort]').value=sort;controls.get('.flight-filter-panel').querySelector=()=>node('summary');
 const container=node('container');container.querySelector=s=>s==='.flight-options'?list:controls.get(s);
 container.querySelectorAll=s=>s==='[data-flight-filter]'?[controls.get('[data-flight-filter="direct"]'),controls.get('[data-flight-filter="baggage"]')]:s==='[data-flight-time]'?[controls.get('[data-flight-time=forward]'),controls.get('[data-flight-time=backward]')]:s==='[data-flight-filter],[data-flight-sort],[data-flight-time]'?[...container.querySelectorAll('[data-flight-filter]'),controls.get('[data-flight-sort]'),...container.querySelectorAll('[data-flight-time]')]:[];
 const context={window:{},matchMedia:q=>({matches:q.includes('max-width')?width<=760:true}),document:{createTextNode:text=>({text}),createElement:tag=>node(tag)}};vm.createContext(context);
 vm.runInContext('globalThis.normalizations=0;const lower=String.prototype.toLocaleLowerCase;String.prototype.toLocaleLowerCase=function(...args){normalizations++;return lower.apply(this,args)};',context);
 vm.runInContext(code,context);context.window.AnyTourFlightPickerV18.bind(container,n=>n+' ₽');
 const fire=(key,event='change')=>controls.get(key).handlers[event]?.({target:controls.get(key)});
 const snapshot=()=>JSON.parse(JSON.stringify({order:list.children.map(r=>r.key),rows:rows.map(r=>[r.key,r.hidden,r.dataset.flightMatch,r.input.checked,r.difference.textContent]),controls:[...controls].map(([k,v])=>[k,v.value,v.checked,v.hidden,v.textContent,v['aria-label'],v.children.map(c=>c.text??c.textContent)]),trace}));
 return {context,rows,controls,container,fire,snapshot};
}
function records(code){
 const out=[];for(const width of [390,1280])for(const count of [0,1,24])for(const sort of ['default','price'])for(const query of ['', '  ЕЖ  air  ', 'AIR RETURN','еж nope','BBB 23']){
  const m=model(code,{width,count,sort,selected:count-1}),take=()=>out.push(m.snapshot());take();
  m.controls.get('[data-flight-query]').value=query;m.fire('[data-flight-query]','input');take();
  m.controls.get('[data-flight-filter="baggage"]').checked=true;m.fire('[data-flight-filter="baggage"]');take();
  m.controls.get('[data-flight-filter="direct"]').checked=true;m.controls.get('[data-flight-filter="baggage"]').checked=true;m.controls.get('[data-flight-time=forward]').value='morning';m.fire('[data-flight-time=forward]');take();
  m.fire('[data-flight-load-more]','click');take();
  if(count){m.rows.forEach(r=>r.input.checked=false);m.rows[0].input.checked=true;m.container.handlers.change({target:m.rows[0].input});take();m.fire('[data-flight-selected]','click');take();}
  // A later input must read changed DOM data rather than a stale memoized value.
  if(count)m.rows[0].dataset.flightSearch='НОВЫЙ РЕЙС';m.controls.get('[data-flight-query]').value='новый рейс';m.fire('[data-flight-query]','input');take();
 }
 return out;
}
const actual=records(source),digest=crypto.createHash('sha256').update(JSON.stringify(actual)).digest('hex'),i=process.argv.indexOf('--compare');
assert.equal(digest,'cce0ebb624018ad312c58418e380350e967630801ae91454c82eb0ba68de718c','pinned original bind behavior');
if(i>=0)assert.deepEqual(actual,records(fs.readFileSync(process.argv[i+1],'utf8')),'actual bind visible state, order, selection, price differences and focus');
assert(JSON.stringify(actual)!==JSON.stringify(records(source.replace("(!baggage||row.dataset.flightBaggage==='true')",'true'))),'baggage mutation detected');
const work=model(source,{count:1000,uniform:true});work.context.normalizations=0;work.controls.get('[data-flight-query]').value='еж air aaa return';work.fire('[data-flight-query]','input');assert.equal(work.context.normalizations,1001,'one query and one normalization per row');
if(i>=0){const before=model(fs.readFileSync(process.argv[i+1],'utf8'),{count:1000,uniform:true});before.context.normalizations=0;before.controls.get('[data-flight-query]').value='еж air aaa return';before.fire('[data-flight-query]','input');assert.equal(before.context.normalizations,4001,'exact baseline repeats each query term');assert.deepEqual(work.snapshot(),before.snapshot());}
console.log(`PASS flight filter: ${actual.length} actual-bind state records; digest ${digest}; 4001->1001 normalizations for1000 rows/four terms; provider/lead HTTP0`);
