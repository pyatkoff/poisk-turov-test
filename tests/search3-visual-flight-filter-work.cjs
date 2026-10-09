// Characterize the actual exported bind owner. No renderer substitute or I/O.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),crypto=require('node:crypto');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/flight-picker-v18.js'),'utf8')+'\n'+fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/flight-picker-ui-v1.js'),'utf8');
function model(code,{width=1280,count=24,selected=0,sort='default',uniform=false}={}){
 const trace=[],controls=new Map(),work={flightIndex:0,flightPrice:0,appends:0};
 function node(key){return {key,value:'',checked:false,hidden:false,textContent:'',children:[],handlers:{},setAttribute(k,v){this[k]=v;},addEventListener(k,fn){this.handlers[k]=fn;},focus(options){trace.push(['focus',key,options]);},scrollIntoView(options){trace.push(['scroll',key,options]);},append(...nodes){this.children.push(...nodes);},replaceChildren(...nodes){this.children=[...nodes];}};}
 const rows=Array.from({length:count},(_,i)=>{
  const row=node('row'+i),input=node('input'+i),difference=node('difference'+i);input.name='flight-pair';input.value=String(i);input.checked=i===selected;input.closest=()=>row;
  row.dataset=new Proxy({flightIndex:String(i),flightPrice:i%5?String(100000+(count-i)*100):'',flightSearch:uniform?'ЁЖ AIR AAA RETURN':(i%2?'EXAMPLE AIR BBB':'ЁЖ AIR AAA')+' RETURN '+i,flightDirect:String(i%3===0),flightBaggage:String(i%4!==0),flightForwardTime:i%2?'morning':'night',flightBackwardTime:i%3?'evening':'morning'},{get(target,key){if(key==='flightIndex'||key==='flightPrice')work[key]++;return target[key];}});
  row.querySelector=s=>s==='input:checked'?(input.checked?input:null):s==='input'?input:s==='[data-flight-difference]'?difference:null;row.input=input;row.difference=difference;return row;
 });
 const list=node('list');list.children=[...rows];Object.defineProperty(list,'childNodes',{get:()=>list.children});list.append=row=>{work.appends++;list.children=list.children.filter(x=>x!==row);list.children.push(row);};
 list.querySelector=s=>s==='input:checked'?rows.find(r=>r.input.checked)?.input:null;
 list.querySelectorAll=s=>list.children.filter(r=>rows.includes(r)&&(s==='.flight-option'||s==='.flight-option:not([hidden])'&&!r.hidden||s==='.flight-option[data-flight-match="true"]'&&r.dataset.flightMatch==='true'));
 const keys=['.flight-filter-panel','.flight-filter-state','[data-flight-query]','[data-flight-selected]','[data-flight-load-more]','[data-flight-filter="direct"]','[data-flight-filter="baggage"]','[data-flight-sort]','[data-flight-time=forward]','[data-flight-time=backward]','[data-flight-time-count]','[data-flight-filter-count]','[data-flight-show-results]'];
 for(const key of keys)controls.set(key,node(key));controls.get('[data-flight-sort]').value=sort;controls.get('.flight-filter-panel').querySelector=()=>node('summary');
 const container=node('container');container.querySelector=s=>s==='.flight-options'?list:controls.get(s);
 container.querySelectorAll=s=>s==='[data-flight-filter]'?[controls.get('[data-flight-filter="direct"]'),controls.get('[data-flight-filter="baggage"]')]:s==='[data-flight-time]'?[controls.get('[data-flight-time=forward]'),controls.get('[data-flight-time=backward]')]:s==='[data-flight-filter],[data-flight-sort],[data-flight-time]'?[...container.querySelectorAll('[data-flight-filter]'),controls.get('[data-flight-sort]'),...container.querySelectorAll('[data-flight-time]')]:[];
 const context={window:{},matchMedia:q=>({matches:q.includes('max-width')?width<=760:true}),document:{createTextNode:text=>({text}),createElement:tag=>node(tag)}};vm.createContext(context);
 vm.runInContext('globalThis.normalizations=0;globalThis.sorts=0;globalThis.comparisons=0;const lower=String.prototype.toLocaleLowerCase;String.prototype.toLocaleLowerCase=function(...args){normalizations++;return lower.apply(this,args)};const originalSort=Array.prototype.sort;Array.prototype.sort=function(compare){sorts++;return originalSort.call(this,(a,b)=>{comparisons++;return compare(a,b)})};',context);
 vm.runInContext(code,context);context.window.AnyTourFlightPickerV18.bind(container,n=>n+' ₽');
 const fire=(key,event='change')=>controls.get(key).handlers[event]?.({target:controls.get(key)});
 const snapshot=()=>JSON.parse(JSON.stringify({order:list.children.map(r=>r.key),rows:rows.map(r=>[r.key,r.hidden,r.dataset.flightMatch,r.input.checked,r.difference.textContent]),controls:[...controls].map(([k,v])=>[k,v.value,v.checked,v.hidden,v.textContent,v['aria-label'],v.children.map(c=>c.text??c.textContent)]),trace}));
 return {context,rows,controls,container,list,work,fire,snapshot};
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
// These inputs characterize the original owner, including cache invalidation,
// stable ties from the bind inventory and repair of all actual child nodes.
function changedRecords(code){
 const out=[];for(const width of [390,1280])for(const count of [0,1,24])for(const sort of ['default','price']){
  const m=model(code,{width,count,sort,selected:count-1}),take=()=>out.push(m.snapshot()),refresh=()=>{m.fire('[data-flight-query]','input');take();};
  take();m.list.children.unshift({key:'legend'});refresh();
  m.rows.forEach((r,j)=>r.dataset.flightPrice=String(j+1));refresh();
  m.rows.forEach((r,j)=>r.dataset.flightIndex=String(count-j));refresh();
  m.rows.forEach((r,j)=>{r.dataset.flightPrice=String((j%4)*100);r.dataset.flightIndex=String(j%3);});refresh();
  // Equal keys must revert to the original bind order, not the last sorted one.
  m.rows.forEach(r=>{r.dataset.flightPrice='42';r.dataset.flightIndex='1';});refresh();
  const numbers=['0','','NaN','Infinity','-Infinity','-1','1e2','01','+0',' 9','3.5'];
  m.rows.forEach((r,j)=>{r.dataset.flightPrice=numbers[j%numbers.length];r.dataset.flightIndex=numbers[(j+3)%numbers.length];});refresh();
  if(count){delete m.rows[0].dataset.flightPrice;delete m.rows[0].dataset.flightIndex;}refresh();
  for(const value of ['price','default','price']){m.controls.get('[data-flight-sort]').value=value;m.fire('[data-flight-sort]');take();}
  m.list.children.reverse();refresh();
  if(count)m.list.children=m.list.children.filter(r=>r!==m.rows[0]);refresh();
  m.list.children.push({key:'trailing text'});refresh();
  m.list.children.splice(1,0,{key:'unowned element'});refresh();
  if(count)m.rows[0].dataset.flightSearch='НОВЫЙ РЕЙС';m.controls.get('[data-flight-query]').value='новый рейс';refresh();
  m.fire('[data-flight-selected]','click');take();
  m.controls.get('[data-flight-filter="direct"]').checked=true;m.fire('[data-flight-filter="direct"]');take();
  m.controls.get('.flight-filter-state').children.find(c=>c.onclick)?.onclick();take();
  m.fire('[data-flight-load-more]','click');take();
  if(count){m.rows.forEach(r=>r.input.checked=false);m.rows.at(-1).input.checked=true;m.container.handlers.change({target:m.rows.at(-1).input});take();}
  m.fire('[data-flight-selected]','click');take();m.fire('[data-flight-show-results]','click');take();
 }
 return out;
}
function domRecords(code,{legacyCopy=false,legacyResetFocus=false}={}){
 const {JSDOM}=require('jsdom'),out=[];
 const copyLabels=[
  ['.flight-options>legend','Пары рейсов туда и обратно. Цена всего тура за всех туристов.','Пары рейсов туда и обратно. Цена за весь тур.'],
  ['.flight-price>small','весь тур за всех','за весь тур']
 ];
 for(const width of [390,1280]){
  const dom=new JSDOM('<main></main>',{runScripts:'outside-only'}),w=dom.window,trace=[];
  w.matchMedia=q=>({matches:q.includes('max-width')?width<=760:true});
  w.HTMLElement.prototype.scrollIntoView=function(options){trace.push([this.dataset.flightIndex,options]);};
  w.eval(code);const host=w.document.querySelector('main'),api=w.AnyTourFlightPickerV18;
  const segment=j=>({company:'ЁЖ AIR',number:String(j),departure:{port:'AAA',time:'10:00'},arrival:{port:'BBB',time:'12:00'},baggage:20});
  const variants=Array.from({length:24},(_,j)=>({forward:[segment(j)],backward:[segment(j)],total:100000+(24-j)*100}));
  const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  host.innerHTML=api.render({tour:{id:'fixture'},variants},'23',{esc,text:String,money:n=>n+' ₽',price:(_,v)=>v.total,legHTML:()=>'',fuelText:()=>''});api.bind(host,n=>n+' ₽');
  const list=host.querySelector('.flight-options'),rows=[...list.querySelectorAll('.flight-option')],query=host.querySelector('[data-flight-query]'),sort=host.querySelector('[data-flight-sort]');
  // Assert the declared presentation change on the actual DOM, then normalize
  // only those two labels on clones. State, attributes and unknown nodes remain
  // in the original pinned oracle, including BODY when it initially has focus.
  const snapshotNode=node=>{
   const copy=node.cloneNode(true);
   for(const [selector,current,original] of copyLabels){
    const labels=[...(copy.matches?.(selector)?[copy]:[]),...copy.querySelectorAll(selector)];
    for(const label of labels){assert.equal(label.innerHTML,legacyCopy?original:current,'only declared plain flight-price copy may differ');label.textContent=original;}
   }
   return copy;
  };
  let resetSnapshot=false;
  const take=()=>{
   assert.equal(host.querySelectorAll(copyLabels[0][0]).length,1,'one actual pair legend');
   assert.equal(host.querySelectorAll(copyLabels[1][0]).length,host.querySelectorAll('.flight-option').length,'one actual whole-tour caption per option');
   for(const [selector,current,original] of copyLabels)for(const label of host.querySelectorAll(selector))assert.equal(label.innerHTML,legacyCopy?original:current,'actual flight-price copy must remain plain text');
   const html=host.innerHTML,focus=w.document.activeElement.outerHTML;
   if(resetSnapshot)assert.strictEqual(w.document.activeElement,legacyResetFocus?w.document.body:host.querySelector('.flight-filter-panel>summary'),'Reset keeps keyboard focus on the visible filter summary');
   // Reset's old BODY focus is the one declared behavior repair. Normalize
   // only this detached focus snapshot; all DOM/order/selection pins remain.
   out.push({html:snapshotNode(host).innerHTML,focus:snapshotNode(resetSnapshot?w.document.body:w.document.activeElement).outerHTML,scroll:JSON.parse(JSON.stringify(trace)),scrollTop:host.scrollTop});resetSnapshot=false;
   assert.equal(host.innerHTML,html,'snapshot normalization must not mutate the actual host');
   assert.equal(w.document.activeElement.outerHTML,focus,'snapshot normalization must not mutate the focused DOM');
  },fire=(node,event='change')=>{node.focus();node.dispatchEvent(new w.Event(event,{bubbles:true}));},click=selector=>{const node=host.querySelector(selector);node.focus();node.click();},refresh=()=>{fire(query,'input');take();};
  take();query.value='еж air';refresh();sort.value='price';fire(sort);take();refresh();
  rows[0].dataset.flightPrice='1';rows[1].dataset.flightIndex='-1';refresh();
  rows.forEach(r=>{r.dataset.flightPrice='42';r.dataset.flightIndex='1';});refresh();
  list.append(w.document.createTextNode('tail'));refresh();list.insertBefore(rows.at(-1),rows[0]);refresh();rows[0].remove();refresh();
  const extra=w.document.createElement('span');extra.textContent='unowned';list.append(extra);refresh();
  // A newly inserted option stays outside the original per-bind inventory.
  const added=rows[0].cloneNode(true);added.dataset.flightIndex='999';added.querySelector('input').value='999';added.querySelector('input').checked=false;list.append(added);refresh();
  const chosen=rows.at(-1).querySelector('input');chosen.checked=true;fire(chosen);take();
  click('[data-flight-selected]');take();click('[data-flight-load-more]');take();
  query.value='absent';refresh();click('[data-flight-selected]');take();
  host.querySelector('[data-flight-filter="baggage"]').checked=true;fire(host.querySelector('[data-flight-filter="baggage"]'));take();
  click('.flight-filter-state button');resetSnapshot=true;take();click('[data-flight-show-results]');take();
  dom.window.close();
 }
 return out;
}
const hash=value=>crypto.createHash('sha256').update(JSON.stringify(value)).digest('hex');
const actual=records(source),digest=hash(actual),i=process.argv.indexOf('--compare'),baseline=i>=0?fs.readFileSync(process.argv[i+1],'utf8'):null;
assert.equal(digest,'cce0ebb624018ad312c58418e380350e967630801ae91454c82eb0ba68de718c','pinned original bind behavior');
if(baseline)assert.deepEqual(actual,records(baseline),'actual bind visible state, order, selection, price differences and focus');
assert(JSON.stringify(actual)!==JSON.stringify(records(source.replace("(!baggage||row.dataset.flightBaggage==='true')",'true'))),'baggage mutation detected');
const work=model(source,{count:1000,uniform:true});work.context.normalizations=0;work.controls.get('[data-flight-query]').value='еж air aaa return';work.fire('[data-flight-query]','input');assert.equal(work.context.normalizations,1001,'one query and one normalization per row');
const changed=changedRecords(source),dom=domRecords(source);
if(process.argv.includes('--capture'))console.log('CAPTURE original changed/DOM digests',hash(changed),hash(dom));
else{assert.equal(hash(changed),'6d255be2935c046bb0e3b069ab0bbf94cfc8544aad2211f7c7717b6774fdaf4a','original dynamic-data and child-node repair states');assert.equal(hash(dom),'94583c42c8b29ded66f9113f54053786978378bd7e62ecf1fb694c339e23b152','original real DOM, selection, focus and scroll states');}
if(baseline){assert.deepEqual(changed,changedRecords(baseline));assert.deepEqual(dom,domRecords(baseline,{legacyCopy:true,legacyResetFocus:true}));}
for(const [before,after] of [
 ['Пары рейсов туда и обратно. Цена всего тура за всех туристов.','Пары рейсов туда и обратно. Цена билета.'],
 ['<small>весь тур за всех</small>','<small>цена за одного</small>'],
 ['Пары рейсов туда и обратно. Цена всего тура за всех туристов.','<span>Пары рейсов туда и обратно. Цена всего тура за всех туристов.</span>'],
 ['<small>весь тур за всех</small>','<small><span>весь тур за всех</span></small>']
]){
 const mutated=source.replace(before,after);assert.notEqual(mutated,source,'declared copy mutation reaches the actual renderer');
 assert.throws(()=>domRecords(mutated),/actual flight-price copy/,'unrecognized copy cannot be normalized away');
}
for(const condition of ['index!==key.index','price!==key.price'])assert.notEqual(hash(changedRecords(source.replace('index!==key.index||price!==key.price',condition))),hash(changed),'each dynamic sort-key mutation detected');
assert.notEqual(hash(changedRecords(source.replace('nodes=list.childNodes','nodes=[...list.querySelectorAll(".flight-option")]'))),hash(changed),'non-row child-node order mutation detected');
function refreshWork(code,sort){
 const m=model(code,{count:1000,uniform:true,sort});Object.keys(m.work).forEach(k=>m.work[k]=0);m.context.normalizations=m.context.sorts=m.context.comparisons=0;
 m.controls.get('[data-flight-query]').value='еж air aaa return';m.fire('[data-flight-query]','input');
 const counts={...m.work,sorts:m.context.sorts,comparisons:m.context.comparisons,normalizations:m.context.normalizations};return {counts,state:m.snapshot()};
}
for(const sort of ['default','price']){
 const after=refreshWork(source,sort);assert.equal(after.counts.sorts,0);assert.equal(after.counts.comparisons,0);assert.equal(after.counts.appends,0);assert.equal(after.counts.flightIndex,1000);assert.equal(after.counts.flightPrice,sort==='price'?2001:1001);assert.equal(after.counts.normalizations,1001);
 if(baseline){const before=refreshWork(baseline,sort);assert.deepEqual(after.state,before.state);console.log('WORK second query /1000 rows',sort,JSON.stringify({before:before.counts,after:after.counts}));}
}
console.log(`PASS flight filter: ${actual.length} pinned bind states (${digest}), ${changed.length} dynamic/repair states, ${dom.length} real DOM states; unchanged normalization1001; no unchanged-order sorts/appends; provider/lead HTTP0`);

require('./search3-visual-flight-picker-lazy.cjs');

