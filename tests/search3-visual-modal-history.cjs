// Site100 modal route snapshots retain nested form drafts; returning to passive offer/room lists preserves the newly chosen flight instead of restoring the old listing. Original identity/focus/scroll and stale-provider mutations stay required.
// Execute the actual modal owner with DOM/collaborator boundaries intercepted.
// Baseline is P5's unchanged modal code, originally app blob db413a55.
// The retained pin was recomputed from release 7a4e93b before removal,
// excluding only the two comparison Back scenarios.
// O51 additionally projects the empty cancellation callback and checks the
// unchanged active observations against fresh release 6f6d4b9585.
// The receiving return fix intentionally changes hotel-details restoration;
// its applied-pair regression retains exact identity and the whole-tour total.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),crypto=require('node:crypto');
const source=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
const clone=x=>JSON.parse(JSON.stringify(x));
function owner(source){
 const a=source.indexOf('function updateModalBack(){'),b=source.indexOf("$('#modal').addEventListener('cancel'",a),guard=source.indexOf('function terminalOfferQuote('),guardEnd=source.indexOf('function offerQuoteFailure(',guard);assert(a>=0&&b>a&&guard>=0&&guardEnd>guard);
 // The modal corpus intercepts the provider-view collaborator separately.
 // Load the actual guard with a read-only empty view for these original cases.
 return source.slice(source.indexOf('function hotelOfferCheckActive('),source.indexOf('function renderOfferProgress('))+'\nconst terminalOfferQuote=(()=>{const retainedProviderView=()=>null;'+source.slice(guard,guardEnd)+'return terminalOfferQuote;})();\n'+source.slice(a,b);
}
function observe(source,s){
 const trace=[],tasks=[],expiry=[],nodes=new Map(),savedOffer={key:'offer-7',hotelId:7,raw:{native:'same'}},savedGallery={id:7,index:2};let ctx;
 const checkpoint=()=>({type:ctx.modalType,restoring:ctx.restoringModal,history:ctx.modalHistory.map(x=>x.type),selected:ctx.selectedOffer?.key,open:node('#modal').open,footer:node('#modal-footer').innerHTML});
 const record=(name,...args)=>trace.push([name,...args,checkpoint()]);
 function node(key){if(nodes.has(key))return nodes.get(key);const n={key,id:key,value:'AI',dataset:{hotelId:'7',room:'standard'},textContent:'old '+key,innerHTML:'<old>'+key+'</old>',hidden:false,scrollTop:37,className:'wide-dialog',open:key==='#modal'?!!s.open:false,top:key==='#modal-body'?100:150,
  getBoundingClientRect:()=>({top:n.top}),contains:x=>x?.key==='focus-target',matches:selector=>selector.split(',').includes('[data-action="offer"]')&&!!s.returningOffer,
  focus:options=>{ctx.document.activeElement=n;record('focus',key,options);},
  setAttribute:(k,v)=>{n[k]=v;record('attribute',key,k,v);},
  closest:selector=>node(selector),querySelector:selector=>node(selector),
  showModal:()=>{n.open=true;record('showModal');},close:()=>{n.open=false;record('close');},
  classList:{contains:()=>!!s.filterOpen,toggle:()=>{}},style:{overflow:'initial'}
 };nodes.set(key,n);return n;}
 const collaborators=['cancelVerification','enterUIHistory','hydrate','syncDestinationViewport','rememberUIRoute','leaveUIHistory','cancelDestinationLookup','restorePageReturn','renderResultCards','renderRealOffer','restoreProviderView','openAndromedaApplicationPreview','openAnexApplicationPreview','renderHotelRooms','renderOfferList','renderFavorites','refreshSavedTourControls','syncHotelSectionNavigation'];
 ctx={$:node,$$:selector=>[node(selector+'[0]'),node(selector+'[1]')],Math,Number,String,Array,Set,JSON,
  renderedResultItems:[],hotelDetailsView:null,hotelOfferDraft:null,modalType:s.current||'offer',modalHistory:[],restoringModal:!!s.restoring,selectedOffer:savedOffer,gallery:savedGallery,actionTrigger:node('trigger'),selectionGeneration:10,offerView:s.noOfferView?null:{id:7},
  calendarRequest:{abort:()=>record('calendarAbort')},calendarObserver:{disconnect:()=>record('calendarDisconnect')},hotelRoomObserver:{disconnect:()=>record('roomDisconnect')},
  hotels:[{id:7}],hotelOffers:()=>[{meal:'AI'}],
  document:{activeElement:node('active'),body:{style:{overflow:'initial'}}},
  focusReference:(element,root)=>{record('focusReference',element.key,root.key);return {element,selector:'#offer-button',top:18};},
  restoreFocus:(reference,fallback,root)=>{record('restoreFocus',reference?.selector||null,fallback.key,root.key);ctx.document.activeElement=reference?.element||fallback;},
  providerQuoteExpiryTimer:777,refreshProviderQuoteExpiry:()=>{},clearTimeout:id=>{assert.equal(id,777);expiry.push('clear');},
  queueMicrotask:fn=>{if(fn===ctx.refreshProviderQuoteExpiry){expiry.push('schedule');return;}record('queueMicrotask');tasks.push(fn);},
  window:{AnyTourPrototypeLead:{bind:offer=>record('bindLead',offer.key)}}
 };
 collaborators.forEach(name=>ctx[name]=(...args)=>{record(name,...args.map(x=>x&&typeof x==='object'?x.key||clone(x):x));return true;});
 ctx.retainedProviderView=offer=>{record('retainedProviderView',offer?.key);return s.stale?.includes(offer?.key)?null:{type:offer?.viewType||s.previous};};
 function step(type,index=0){const offer={...savedOffer,key:'step-'+index,viewType:type};return {type,title:'title '+type,kicker:'kicker',body:'<section>'+type+'</section>',footer:'footer '+type,footerHidden:!!s.footerHidden,className:type==='gallery'?'gallery-dialog':s.className||'wide-dialog',scroll:83,gallery:{id:7,index:3},offer,focus:{element:node('focus-target'),selector:'#offer-button',top:s.noFocusTop?undefined:18}};}
 if(s.kind==='back'){ctx.modalHistory=[step(s.previous)];if(s.staleAbove)ctx.modalHistory.push(step('andromeda-flights',1),step('anex-current',2));if(s.empty)ctx.modalHistory=[];if(s.onlyStale)ctx.modalHistory=[step('anex-current',2)];}
 if(s.setup)s.setup(ctx,node);
 ctx.formFiltersDraft=null;ctx.destinationPending=null;ctx.uiRoute=()=>({type:ctx.modalType,key:ctx.selectedOffer?.key});
 vm.createContext(ctx);vm.runInContext(owner(source),ctx);
 if(s.kind==='show')ctx.showModal(s.next,'new title','new kicker','<new>body</new>',!!s.wide);
 else if(s.kind==='close')ctx.closeModal({fromHistory:!!s.fromHistory});
 else ctx.modalBack();
 // Snapshot identity is part of the contract, not just equal serialized values.
 const last=ctx.modalHistory.at(-1),identities={snapshotOfferIsCurrent:last?.offer===savedOffer,snapshotGalleryIsOriginal:last?.gallery===savedGallery,selectedIsOriginal:ctx.selectedOffer===savedOffer};
 tasks.forEach(fn=>fn());
 assert.deepEqual(expiry,s.kind==='show'?['schedule']:s.kind==='close'?s.open?['clear']:[]:trace.some(item=>item[0]==='queueMicrotask')?['schedule']:[],'dialog schedules receipt reflection or clears its timer without changing retained modal traces');
 const history=ctx.modalHistory.map(x=>({...x,focus:x.focus?{selector:x.focus.selector,top:x.focus.top,element:x.focus.element?.key}:null}));
 const dom=[...nodes].map(([key,n])=>[key,{value:n.value,textContent:n.textContent,innerHTML:n.innerHTML,hidden:n.hidden,scrollTop:n.scrollTop,className:n.className,open:n.open,title:n.title,ariaLabel:n['aria-label']}]);
 return clone({trace,history,identities,dom,selectedOffer:ctx.selectedOffer,gallery:ctx.gallery,modalType:ctx.modalType,restoringModal:ctx.restoringModal,selectionGeneration:ctx.selectionGeneration,observerIsNull:ctx.hotelRoomObserver===null,overflow:ctx.document.body.style.overflow,activeElement:ctx.document.activeElement?.key});
}
const scenarios=[];const add=(name,s)=>scenarios.push({name,...s});
for(const open of [false,true])for(const next of ['offer','gallery','dates','all-offers','andromeda-flights'])for(const wide of [false,true])add(`show:${open}:${next}:${wide}`,{kind:'show',open,next,wide});
add('show while restoring',{kind:'show',open:true,current:'hotel-details',next:'offer',restoring:true});
add('trigger fallback',{kind:'show',open:true,next:'dates',setup:c=>c.actionTrigger=null});
for(const previous of ['offer','all-offers','hotel-details','gallery','andromeda-flights','andromeda-verified','anex-current','anex-additional','anex-quote','provider-application','anex-application','favorites','selected-tour','dates','about']){
 add('back:'+previous,{kind:'back',open:true,current:'offer',previous});
 add('back hidden footer:'+previous,{kind:'back',open:true,current:'dates',previous,footerHidden:true,noFocusTop:true,className:''});
}
add('empty stack',{kind:'back',open:true,empty:true});
add('stale provider stack skipped',{kind:'back',open:true,previous:'hotel-details',staleAbove:true,stale:['step-1','step-2']});
add('only stale step',{kind:'back',open:true,onlyStale:true,stale:['step-2']});
add('offer list absent',{kind:'back',open:true,previous:'all-offers',noOfferView:true});
add('hotel meal absent',{kind:'back',open:true,previous:'hotel-details',setup:(c,n)=>n('#hotel-room-meal').value='BB'});
add('hotel returning offer expands',{kind:'back',open:true,previous:'hotel-details',returningOffer:true});
add('hotel Back retains applied pair',{kind:'back',open:true,current:'offer',previous:'hotel-details',setup:c=>{
 Object.assign(c.selectedOffer,{flightChoiceId:'1',total:133500.5});
 c.modalHistory[0].offer={...c.selectedOffer,flightChoiceId:null,total:120000};
}});
add('focus outside body',{kind:'back',open:true,previous:'gallery',setup:(c,n)=>n('#modal-body').contains=()=>false});
for(const open of [false,true])for(const filterOpen of [false,true])for(const fromHistory of [false,true])add(`close:${open}:${filterOpen}:${fromHistory}`,{kind:'close',open,filterOpen,fromHistory});
// The retired My tour painter and empty verification cancellation had no active
// DOM effect. Ignore only their intercepted callbacks, retaining every active
// modal observation and original before/after comparison.
function records(source){return scenarios.map(s=>{const result=observe(source,s);result.trace=result.trace.filter(record=>!['refreshSavedTourControls','cancelVerification'].includes(record[0]));return {name:s.name,result};});}
// Only the three explicit viewport-sync call sites may differ from the retained pin.
// Prove their exact position/count and preserve every original observation.
let retainedSource=source;
for(const [current,retained] of [
 ['m.close();syncDestinationViewport();document.body.style.overflow=','m.close();document.body.style.overflow='],
 ['restoreModalStepFocus(previous);syncDestinationViewport();','restoreModalStepFocus(previous);'],
 ['updateModalBack();syncDestinationViewport();\n}','updateModalBack();\n}']
]){assert.equal(retainedSource.split(current).length,2,'one intentional viewport-sync call site');retainedSource=retainedSource.replace(current,retained);}
retainedSource=retainedSource.replace('renderResultCards(renderedResultItems,{selectionOnly:true});','');
const actual=records(source),projected=clone(actual),retained=records(retainedSource);
for(const row of projected)row.result.trace=row.result.trace.filter(c=>c[0]!=='renderResultCards');let viewportSyncObservations=0;
for(let index=0;index<actual.length;index++){
 const scenario=scenarios[index],trace=actual[index].result.trace,extra=scenario.kind==='close'&&scenario.open||scenario.kind==='back'&&trace.some(record=>record[0]==='restoreFocus');
 const syncs=trace.flatMap((record,index)=>record[0]==='syncDestinationViewport'?[index]:[]),originalCount=retained[index].result.trace.filter(record=>record[0]==='syncDestinationViewport').length;
 const snapshotSync=extra&&scenario.kind==='back';
 assert.equal(syncs.length,originalCount+(extra?1:0)+(snapshotSync?1:0),scenario.name+' exact viewport synchronization count');
 if(extra){const at=syncs.at(-1);assert.equal(trace[at-1][0],scenario.kind==='close'?'close':'restoreFocus',scenario.name+' syncs after close or restored focus');assert.equal(trace[at+1][0],scenario.kind==='close'?'renderResultCards':'syncHotelSectionNavigation',scenario.name+' syncs before passive return bookkeeping');projected[index].result.trace.splice(at,1);viewportSyncObservations++;}
 if(snapshotSync){
  const at=syncs[1],preceding=trace[at-1];
  assert.deepEqual(preceding.slice(0,3),['attribute','#modal-back','aria-label'],scenario.name+' snapshot sync follows restored Back state');
  assert.equal(trace[at][1].restoring,true,scenario.name+' snapshot sync precedes focus restoration');
  assert.equal(trace[at][1].footer,actual[index].result.dom.find(([key])=>key==='#modal-footer')[1].innerHTML,scenario.name+' snapshot footer is already restored');
  assert.deepEqual(trace[at][1],preceding.at(-1),scenario.name+' synchronization cannot change snapshot identity or DOM');
  assert(at<syncs.at(-1),scenario.name+' snapshot geometry is available before the focus pass');
  projected[index].result.trace.splice(at,1);viewportSyncObservations++;
 }
}
assert.deepEqual(projected,retained,'all original modal observations remain unchanged after only the explicit viewport synchronization');
const digest=crypto.createHash('sha256').update(JSON.stringify(projected)).digest('hex'),i=process.argv.indexOf('--compare');
if(i>=0)assert.deepEqual(projected,records(fs.readFileSync(process.argv[i+1],'utf8')),'retained modal before/after observable traces');
assert.equal(actual.length,68,'retained 67 modal cases plus applied-pair hotel Back regression');
if(!process.argv.includes('--capture'))assert.equal(digest,'b63e1cb92a5126b1fcd7d1507c2eb49108fd3b69eafad6d868a7c6be80ca0e3f','retained modal observations plus intentional passive hotel-room restoration fix');
const result=name=>actual.find(r=>r.name===name).result;
assert.equal(result('show:true:dates:false').identities.snapshotOfferIsCurrent,true);
assert.equal(result('show:true:dates:false').identities.snapshotGalleryIsOriginal,false);
assert.equal(result('back:offer').restoringModal,false);
assert.equal(result('back:all-offers').identities.selectedIsOriginal,true,'return to passive offers preserves current selected flight');
assert.equal(result('back:hotel-details').identities.selectedIsOriginal,true,'return to passive hotel rooms preserves current selection identity');
const appliedPair=result('hotel Back retains applied pair');
assert.equal(appliedPair.modalType,'hotel-details');
assert.equal(appliedPair.identities.selectedIsOriginal,true,'hotel Back keeps the original applied selection object');
assert.equal(appliedPair.selectedOffer.key,'offer-7','hotel Back retains the exact offer');
assert.equal(appliedPair.selectedOffer.flightChoiceId,'1','hotel Back cannot restore the unselected listing pair');
assert.equal(appliedPair.selectedOffer.total,133500.5,'hotel Back cannot restore the former whole-tour total');
assert.deepEqual(result('show:true:dates:false').history.at(-1).route,{type:'offer',key:'offer-7'},'nested picker snapshot retains route context');
assert.deepEqual(result('only stale step').history,[]);
assert.equal(result('close:true:true:false').overflow,'hidden');
assert.equal(result('close:true:false:false').overflow,'');
assert.notDeepEqual(records(source.replace('restoringModal=false;if(previous.type', 'restoringModal=true;if(previous.type')),actual,'restoration flag mutation detected');
assert.notDeepEqual(records(source.replace('modalBody.scrollTop+=top-previous.focus.top','modalBody.scrollTop+=top')),actual,'focus offset mutation detected');
assert.notDeepEqual(records(source.replace('gallery:{...gallery},offer:selectedOffer','gallery:gallery,offer:selectedOffer')),actual,'snapshot alias mutation detected');
const roomSnapshotMutation=source.replace("!['all-offers','hotel-details'].includes(previous.type)","previous.type!=='all-offers'");
assert.notEqual(roomSnapshotMutation,source,'one existing passive-room snapshot guard is exercised');
assert.notDeepEqual(records(roomSnapshotMutation),actual,'restoring stale hotel-room selection/pair/total is detected');
console.log(`PASS modal history: ${actual.length} cases; snapshot identity, restore/render/focus/scroll/guards retained digest ${digest} plus ${viewportSyncObservations} exact viewport sync observations; supplier and lead HTTP 0`);

// Execute the connected viewport owner with explicit rectangles: pinch changes
// must not impersonate a keyboard or scroll the focused filter input.
const viewportStart=source.indexOf('function syncDestinationViewport(){'),viewportEnd=source.indexOf('window.visualViewport?.addEventListener',viewportStart);
assert(viewportStart>0&&viewportEnd>viewportStart);
for(const width of [390,768,1280])for(const modalOpen of [false,true]){
 const input={value:'12345',selectionStart:1,selectionEnd:3,matches:selector=>selector==='input,textarea'},viewport={height:420,offsetTop:25,scale:1};
 const node=classes=>{const names=new Set(classes),values=new Map();return{open:false,scrollTop:0,classList:{contains:name=>names.has(name),toggle:(name,on)=>on?names.add(name):names.delete(name),remove:name=>names.delete(name)},style:{setProperty:(name,value)=>values.set(name,value),removeProperty:name=>values.delete(name),getPropertyValue:name=>values.get(name)||''},querySelector:()=>null,contains:()=>false};};
 const panel=node(modalOpen?[]:['open']),modal=node([]),body=node([]),footer=node([]),header={getBoundingClientRect:()=>({bottom:100})},drawerFooter={getBoundingClientRect:()=>({top:330})};modal.open=modalOpen;
 panel.querySelector=selector=>selector==='.filter-top'?header:drawerFooter;panel.contains=field=>field===input;panel.getBoundingClientRect=()=>({top:33,bottom:437});input.getBoundingClientRect=()=>({top:392-panel.scrollTop,bottom:440-panel.scrollTop});
 const nodes={'#modal':modal,'#modal-body':body,'#modal-footer':footer,'#filter-panel':panel},context={$:selector=>nodes[selector],window:{visualViewport:viewport},document:{activeElement:input},innerWidth:width,innerHeight:900,modalType:'offer',requestAnimationFrame:callback=>callback()};
 vm.createContext(context);vm.runInContext(source.slice(viewportStart,viewportEnd),context);const sync=()=>context.syncDestinationViewport(),bounded=()=>modalOpen?modal.classList.contains('viewport-keyboard'):panel.classList.contains('viewport-keyboard'),responsive=width<=(modalOpen?760:1100),properties=modalOpen?['--modal-viewport-top','--modal-viewport-height']:['--filter-viewport-top','--filter-viewport-bottom','--filter-viewport-height'],owner=modalOpen?modal:panel;
 sync();assert.equal(bounded(),responsive,'scale1 keeps the existing modal/drawer keyboard boundary');assert.equal(panel.scrollTop,!modalOpen&&responsive?110:0);const scroll=panel.scrollTop;
 for(const scale of [1.25,2]){viewport.scale=scale;sync();assert.equal(bounded(),false,'pinch is not a keyboard at '+width);for(const property of properties)assert.equal(owner.style.getPropertyValue(property),'','pinch clears keyboard positioning, including the fixed drawer footer');assert.equal(panel.scrollTop,scroll,'pinch never scrolls the active filter field');assert.strictEqual(context.document.activeElement,input);assert.deepEqual([input.value,input.selectionStart,input.selectionEnd],['12345',1,3]);}
 viewport.scale=1;sync();assert.equal(bounded(),responsive,'scale1 keyboard behavior resumes after pinch');assert.equal(panel.scrollTop,scroll);viewport.height=900;sync();assert.equal(bounded(),false,'keyboard dismissal clears the retained bound');
}
console.log('PASS actual viewport owner: scale1 keyboard, pinch1.25/2, restored scale1 and dismissal; unchanged modal boundary; drawer focus/caret/draft/scroll retained; no browser or physical pinch claim');

// The approved room draft continues through the footer. Its passive return
// target is the exact selected row, including when a late cheaper row moves it.
const continuedRoom={kind:'show',open:true,current:'hotel-details',next:'offer',setup:(c,n)=>{
 c.actionTrigger.dataset={action:'continue-hotel-offer',key:'exact-room'};
 n('#modal-body [data-action="select-hotel-offer"][0]').dataset.key='exact-room';
}};
const continued=observe(source,continuedRoom),continuedFocus=continued.trace.find(row=>row[0]==='focusReference');
assert.equal(continuedFocus[1],'#modal-body [data-action="select-hotel-offer"][0]','footer continuation captures the exact selected room row');
const oldCapture=source.replace(/modalType==='hotel-details'&&\['continue-hotel-offer','offer'\]\.includes\(actionTrigger\?\.dataset.action\)\?\$\$\('#modal-body \[data-action="select-hotel-offer"\]'\)\.find\(row=>row.dataset.key===actionTrigger.dataset.key\)\|\|actionTrigger:actionTrigger\|\|document.activeElement/,'actionTrigger||document.activeElement');
assert.notDeepEqual(observe(oldCapture,continuedRoom),continued,'footer-only return target regression detected');

// Execute the actual passive route owner: browser history is a locator, never
// enough to rebuild absent/expired/foreign inventory or authorize a request.
const routeStart=source.indexOf('function reopenUIRoute(route){'),routeEnd=source.indexOf('function restoreHistoryView(route){',routeStart);assert(routeStart>=0&&routeEnd>routeStart);
for(const mode of ['current','retained','missing','foreign','loading','flights-loading','expired','empty','other']){
 const raw={},offer={key:'exact-tv',raw},saved={...offer,variants:[{}]},route={type:'flights',key:'exact-tv',flightChoiceId:'0',view:{query:'AAA'},scroll:83},opened=[];
 if(mode==='foreign')saved.raw={};if(mode==='loading')saved.loading=true;if(mode==='flights-loading')saved.flightsLoading=true;if(mode==='empty')saved.variants=[];
 const ctx={hotels:[],selectedOffer:mode==='retained'?null:mode==='other'?{key:'other',raw:{},variants:[{}]}:saved,offerFromKey:key=>mode==='missing'?null:key===offer.key?offer:null,retainedProviderView:()=>mode==='retained'?{offer:saved}:null,needsRefresh:o=>{assert.strictEqual(o,saved);return mode==='expired';},openFlightPicker:value=>opened.push(value)};
 vm.createContext(ctx);vm.runInContext(source.slice(source.indexOf('function terminalOfferQuote('),source.indexOf('function offerQuoteFailure('))+source.slice(routeStart,routeEnd),ctx);const valid=['current','retained'].includes(mode);assert.equal(ctx.reopenUIRoute(route),valid,mode+' history guard');assert.equal(opened.length,valid?1:0);if(valid){assert.strictEqual(opened[0],route);assert.strictEqual(ctx.selectedOffer,saved);}
}
console.log('PASS passive flight history: exact retained offer/raw inventory only; missing/foreign/loading/expired/empty/other rejected; supplier and lead HTTP0');

// Run the actual lazy renderer, selection dispatch and in-place sync together.
// Toggle names identify the exact offer; aria-pressed alone carries selection.
const hotelSource=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/hotel-details-v1.js'),'utf8');
const choiceStart=hotelSource.indexOf('function roomOfferChoiceHTML('),choiceEnd=hotelSource.indexOf('function renderHotelRooms(',choiceStart);
const selectionStart=source.indexOf('function syncHotelOfferSelection('),selectionEnd=source.indexOf('function observeHotelRoomChoices(',selectionStart);
const dispatchStart=source.indexOf("case 'select-hotel-offer':"),dispatchEnd=source.indexOf("case 'continue-hotel-offer':",dispatchStart);
assert(choiceStart>=0&&choiceEnd>choiceStart&&selectionStart>=0&&selectionEnd>selectionStart&&dispatchStart>=0&&dispatchEnd>dispatchStart);
const exactRows=[0,1].map(i=>({key:'exact-room-'+i,hotelId:7,raw:{id:i},room:'Family '+i,meal:i?'BB':'AI',day:'2026-10-01',returnDay:'2026-10-08',nights:7,operator:'Operator '+i,total:125000+i*1500}));
for(const initial of [null,exactRows[0],exactRows[1]]){
 const makeNode=()=>{const attributes=new Map(),classes=new Set();return{dataset:{},textContent:'',classList:{toggle:(key,on)=>on?classes.add(key):classes.delete(key),contains:key=>classes.has(key)},setAttribute:(key,value)=>attributes.set(key,String(value)),getAttribute:key=>attributes.get(key),removeAttribute:key=>attributes.delete(key)};};
 const footer=Object.fromEntries(['#hotel-detail-min','#hotel-detail-price-status','#hotel-detail-offers'].map(key=>[key,makeNode()]));let rows=[],remembered=0;
 const ctx={modalType:'hotel-details',hotelDetailsView:null,selectionGeneration:0,context:{selectedOffer:null,hotelOfferDraft:initial?{hotelId:7,key:initial.key}:null},selectedOffer:null,hotelOfferDraft:initial?{hotelId:7,key:initial.key}:null,hotels:[{id:7}],state:{search:{}},
  esc:String,displayMealLabel:o=>o.meal,mealLabel:o=>o.meal,dateText:String,nightsText:String,flightLabel:()=>'',guestsText:()=> '2 взрослых',money:value=>value+' ₽',operatorBadge:String,sameSelectedTourConditions:()=>false,needsRefresh:()=>false,selectedPriceStatus:()=>'',cardPriceNote:()=>'Цена из выдачи · требует проверки',
  hotelOffers:()=>exactRows,offerFromKey:key=>exactRows.find(o=>o.key===key),rememberUIRoute:()=>remembered++,$:key=>key==='.hotel-section-nav'?{dataset:{hotelId:'7'}}:footer[key],$$:()=>rows,document:{activeElement:null}};
 vm.createContext(ctx);vm.runInContext(source.slice(source.indexOf('function hotelOfferCheckActive('),source.indexOf('function cancelHotelOfferCheck('))+hotelSource.slice(choiceStart,choiceEnd)+source.slice(selectionStart,selectionEnd)+"function dispatchChoice(b){const action='select-hotel-offer';switch(action){"+source.slice(dispatchStart,dispatchEnd)+'}}',ctx);
 rows=exactRows.map(offer=>{const html=ctx.roomOfferChoiceHTML(offer),row=makeNode(),button=makeNode(),tag=html.match(/<button\b[^>]*data-action="select-hotel-offer"[^>]*>/)[0];row.dataset.offerKey=offer.key;row.querySelector=()=>button;button.dataset.key=offer.key;for(const name of ['aria-label','aria-pressed'])button.setAttribute(name,tag.match(new RegExp(name+'="([^"]*)"'))[1]);button.textContent=initial?.key===offer.key?'Выбрано':'Выбрать';return row;});
 const names=rows.map(row=>row.querySelector().getAttribute('aria-label'));
 names.forEach((name,i)=>{const o=exactRows[i];assert.equal(name,`Выбрать: ${o.room}, ${o.meal}, ${o.day}, ${o.operator}, ${o.total} ₽`,'stable accessible name includes exact conditions even when initially selected');});
 assert.notEqual(names[0],names[1],'different exact offers remain distinguishable');
 for(const offer of [exactRows[0],exactRows[1],exactRows[0]]){
  const button=rows.find(row=>row.dataset.offerKey===offer.key).querySelector();ctx.document.activeElement=button;ctx.dispatchChoice(button);
  assert.strictEqual(ctx.document.activeElement,button,'in-place selection preserves focused button');assert.equal(ctx.hotelOfferDraft.key,offer.key);assert.equal(ctx.hotelOfferDraft.hotelId,7);
  rows.forEach((row,i)=>{const active=row.dataset.offerKey===offer.key,b=row.querySelector();assert.equal(b.getAttribute('aria-label'),names[i],'toggle name never changes on selection');assert.equal(b.getAttribute('aria-pressed'),String(active));assert.equal(b.textContent,active?'Выбрано':'Выбрать');assert.equal(row.classList.contains('selected'),active);assert.equal(row.getAttribute('aria-current'),active?'true':undefined);});
  assert.equal(footer['#hotel-detail-min'].textContent,offer.total+' ₽');assert.equal(footer['#hotel-detail-offers'].dataset.key,offer.key);
 }
 assert.equal(remembered,3,'each explicit choice records history once');
}
console.log('PASS exact offer toggle: actual renderer/dispatch/sync, unselected/A/B initial states, A→B→A stable names/pressed state/focus/exact footer; supplier and lead HTTP0');
