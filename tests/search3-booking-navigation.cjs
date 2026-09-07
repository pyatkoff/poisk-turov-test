/* Baseline snapshots captured from release 7e51960a before flight/CTA owner consolidation. */
const fs = require('fs'), vm = require('vm'), assert = require('assert/strict'), crypto = require('crypto');
const path = require('path');
const iife = require('./search3-bundle-iife.cjs');
const bundlePath = process.argv[2] || path.join(__dirname, '../v2/search3-results-filters-v1.js');
function run(dir) {
  const events=new Map(), clicks=[], tasks=new Map(), frames=[], trace=[], nodes=[];
  let nextTask=1, rootPresent=true, formPresent=true;
  function node(name,classes='') {
    const members=new Set(classes.split(' ').filter(Boolean)), styles={};
    const n={name,dataset:{},hidden:false,textContent:'',innerHTML:'',children:[],
      classList:{contains:c=>members.has(c),add:(...cs)=>cs.forEach(c=>members.add(c)),remove:(...cs)=>cs.forEach(c=>members.delete(c)),toggle(c,on){if(on===undefined)on=!members.has(c);on?members.add(c):members.delete(c);return on}},
      style:{setProperty:(k,v,p)=>{styles[k]=[v,p]},removeProperty:k=>{delete styles[k]}},
      appendChild(child){this.children.push(child);return child},
      insertBefore(child,ref){const i=this.children.indexOf(ref);assert.ok(i>=0);this.children.splice(i,0,child)},
      focus(options){trace.push(['focus',name,options||null])},scrollIntoView(options){trace.push(['scroll',name,options||null])},
      snapshot(){return {classes:[...members].sort(),styles,dataset:n.dataset,hidden:n.hidden,text:n.textContent,html:n.innerHTML,disabled:n.disabled||false}}
    };
    n.querySelector=s=>{throw Error(name+' unexpected selector '+s)};
    Object.defineProperty(n,'className',{get:()=>[...members].join(' '),set:s=>{members.clear();s.split(' ').filter(Boolean).forEach(c=>members.add(c))}});
    nodes.push(n);return n;
  }
  const root=node('root'), shell=node('shell','search3-lead-shell'), summary=node('summary','search3-booking-summary');
  const form=node('form','lead-form'), flights=node('flights','tour-flights'), head=node('head','selected-head');
  const title=node('flight title'), hint=node('flight hint'), leadTitle=node('lead title'), leadHint=node('lead hint'), phone=node('phone');
  const commentLabel=node('comment label');
  root.children=[head,flights,shell]; shell.children=[form,summary];
  function find(parent,c){return parent.children.find(n=>n.classList.contains(c))||null}
  summary.querySelector=s=>{const a=find(summary,'search3-summary-actions');if(s==='.search3-summary-submit')return a&&a.querySelector(s);assert.equal(s,'.search3-summary-actions');return a};
  flights.querySelector=s=>{assert.equal(s,'.search3-flight-continue');return find(flights,'search3-flight-continue')};
  root.querySelector=s=>{
    if(s==='.search3-booking-summary')return summary;
    if(s==='.lead-form')return formPresent?form:null;
    if(s==='.search3-lead-back')return find(root,'search3-lead-back');
    if(s==='.search3-lead-shell')return shell;
    if(s==='.tour-flights')return flights;
    if(s==='.tour-flights .section-heading strong')return title;
    if(s==='.tour-flights .section-heading span')return hint;
    if(s==='.search3-final-sections,.search3-lead-shell,.lead-form')return shell;
    throw Error('root selector '+s);
  };
  form.querySelector=s=>{
    if(s==='.section-heading')return {querySelector:s=>s==='strong'?leadTitle:leadHint};
    if(s==='.search3-lead-protection')return find(form,'search3-lead-protection');
    if(s==='textarea[name="comment"]')return {closest:()=>commentLabel};
    if(s==='input[name="phone"]')return phone;
    throw Error('form selector '+s);
  };
  function emit(name,detail={}){for(const fn of events.get(name)||[])fn({type:name,detail})}
  const context={
    document:{getElementById:id=>{assert.equal(id,'selectedTour');return rootPresent?root:null},
      addEventListener:(name,fn)=>{assert.equal(name,'click');clicks.push(fn)},
      createElement(tag){const n=node('created '+nodes.length);if(tag==='div'){const button=node(n.name+' button');n.querySelector=s=>{assert.ok(s==='button'||s==='.search3-summary-submit');return button}}return n}},
    window:{addEventListener:(name,fn)=>{if(!events.has(name))events.set(name,[]);events.get(name).push(fn)},
      dispatchEvent(e){trace.push(['event',e.type,e.detail]);emit(e.type,e.detail)},Search3BookingSummary:{syncLayout(){}}},
    setTimeout(fn){const id=nextTask++;tasks.set(id,fn);return id},clearTimeout:id=>tasks.delete(id),
    requestAnimationFrame:fn=>frames.push(fn),CustomEvent:function(type,opts){this.type=type;this.detail=opts.detail}
  };
  const bundle=fs.readFileSync(dir,'utf8');
  const code=[...new Set([iife(bundle,{literal:'.tour-flights .section-heading strong'}),iife(bundle,{global:'Search3SummaryCta'})])].join('\n');
  vm.runInNewContext(code,context);
  function flush(){let n=0;while(tasks.size||frames.length){assert.ok(++n<100,'settles');while(tasks.size){const [id,fn]=tasks.entries().next().value;tasks.delete(id);fn()}if(frames.length)frames.shift()()}}
  function click(selector){const e={target:{closest:s=>s===selector?{}:null},preventDefault(){}};clicks.forEach(fn=>fn(e))}
  const snapshots=[], pending=[];
  function record(name){flush();snapshots.push({name,nodes:nodes.map(n=>[n.name,n.snapshot()]),trace:JSON.parse(JSON.stringify(trace))})}
  emit('v2:tour-selected');pending.push(tasks.size);record('tour');
  emit('v2:flight-selected');record('flight ready');
  click('#selectedTour .search3-flight-continue button');pending.push(tasks.size);record('review');
  assert.ok(root.classList.contains('search3-final-review'));
  click('#selectedTour .search3-summary-submit');record('lead entry');
  assert.ok(root.classList.contains('search3-lead-entry'));
  assert.equal(head.snapshot().styles.display[0],'none');
  form.dataset.search3LeadState='sending';emit('v2:lead-started');record('sending');
  form.dataset.search3LeadState='error';emit('v2:lead-error');record('retry available');
  click('#selectedTour .search3-lead-back');record('back to review');
  assert.ok(!root.classList.contains('search3-lead-entry'));
  assert.equal(head.snapshot().styles.display,undefined);
  click('#selectedTour .search3-flight-continue button');record('change flight');
  assert.ok(!root.classList.contains('search3-final-review'));
  click('#selectedTour .search3-flight-continue button');record('review again');
  form.dataset.sent='1';emit('v2:lead-success');record('success');
  assert.equal(find(summary,'search3-summary-actions').querySelector('button').disabled,true);
  emit('v2:tour-selected');record('reset phase');
  assert.ok(!root.classList.contains('search3-final-review'));
  formPresent=false;emit('v2:tour-selected');emit('v2:flight-selected');record('missing form');
  rootPresent=false;emit('v2:booking-review');record('missing root');
  return {snapshots:JSON.parse(JSON.stringify(snapshots)),pending,clickListeners:clicks.length};
}
const result=run(bundlePath);
const digest=crypto.createHash('sha256').update(JSON.stringify(result.snapshots)).digest('hex');
assert.equal(digest,'800e65e6d7658048cac39579fb279d2825987c25961995ee53e5902ed013232b',
  'thirteen complete navigation snapshots retain pre-consolidation DOM, events, scroll and focus');
console.log(JSON.stringify({snapshots:result.snapshots.length,digest,pending:result.pending,clickListeners:result.clickListeners}));
assert.deepEqual(result.pending,[1,1],'tour reset and review each share one navigation task');
assert.equal(result.clickListeners,1,'flight, review and lead use one click owner');
console.log('PASS: compiled booking navigation preserves thirteen complete transitions');
