'use strict';
const assert=require('node:assert/strict'),fs=require('fs'),vm=require('vm'),crypto=require('crypto'),{JSDOM}=require('jsdom');
const source=fs.readFileSync(__dirname+'/../v2/visual-search/hotel-details-v1.js','utf8');
function verifyInfantChargeStatus(appSource,hotelSource){
 const start=appSource.indexOf('const cachedPriceNote='),end=appSource.indexOf('// Meal identities',start);
 assert(start>=0&&end>start);
 const q=vm.runInNewContext(appSource.slice(start,end)+';({priceNote,cardPriceNote});',{
  data:{scenario:'live',preview:false},needsRefresh:o=>o.provider==='anex'});
 const listing={key:'infant-base',hotelId:1,provider:'anex',raw:{infantCharge:Object.freeze({status:'unknown',amount:null,includedInSearchPrice:null})},
  total:100000,adults:2,ages:[1,8],room:'Standard',meal:'AI',day:'2026-10-10',returnDay:'2026-10-17',nights:7,operator:'ANEX'};
 assert.match(q.priceNote(listing),/доплата за младенца уточняется/i);
 const ordinary={...listing,raw:{},ages:[]};
 assert.equal(q.cardPriceNote(ordinary),'Цена из текущего поиска · требует подтверждения');
 const rowStart=hotelSource.indexOf('function roomOfferChoiceHTML('),rowEnd=hotelSource.indexOf('function renderHotelRooms(',rowStart);
 const helpers={context:{hotelOfferDraft:{hotelId:1,key:listing.key},selectedOffer:null},esc:v=>String(v),sameSelectedTourConditions:()=>false,
  selectedPriceStatus:()=>'',cardPriceNote:q.cardPriceNote,displayMealLabel:o=>o.meal,operatorBadge:o=>o,flightLabel:()=>'',
  dateText:v=>v,nightsText:v=>v+' ночей',guestsText:()=> '2 взрослых + дети 1, 8 лет',money:v=>v+' ₽'};
 const rowContext=vm.createContext(helpers);vm.runInContext(hotelSource.slice(rowStart,rowEnd),rowContext);
 const html=rowContext.roomOfferChoiceHTML(listing);
 assert.match(html,/room-offer-price-status[^>]*>[^<]*доплата за младенца уточняется/i);
 assert.match(html,/<strong>100000 ₽<\/strong>/);assert.equal(listing.raw.infantCharge.amount,null);assert.equal(listing.total,100000);
 const footerStart=appSource.indexOf('function syncHotelOfferSelection('),footerEnd=appSource.indexOf('function observeHotelRoomChoices(',footerStart);
 const nodes={'#hotel-detail-min':{},'#hotel-detail-price-status':{},'#hotel-detail-offers':{dataset:{}}};
 const f={...helpers,hotelOfferCheckActive:()=>false,hotels:[{id:1}],hotelOffers:()=>[listing],hotelOfferDraft:{hotelId:1,key:listing.key},selectedOffer:null,
  state:{search:{}},searchKey:()=>'',needsRefresh:()=>true,$:s=>nodes[s],$$:()=>[],mealLabel:o=>o.meal};
 vm.createContext(f);vm.runInContext(appSource.slice(footerStart,footerEnd),f);f.syncHotelOfferSelection(1);
 assert.match(nodes['#hotel-detail-price-status'].textContent,/доплата за младенца уточняется/i);
 assert.equal(nodes['#hotel-detail-min'].textContent,'100000 ₽');assert.equal(nodes['#hotel-detail-offers'].dataset.key,listing.key);
 f.hotelOfferCheckActive=()=>true;f.selectedOffer=listing;f.retainedProviderView=()=>({type:'anex-current'});f.syncHotelOfferSelection(1);
 assert.match(nodes['#hotel-detail-price-status'].textContent,/доплата за младенца уточняется/i,'current-context check does not erase the unresolved component');
 assert.equal(nodes['#hotel-detail-min'].textContent,'100000 ₽');
 console.log('PASS actual infant price qualifiers: card/room/selected footer retain unknown/null and exact whole listing amount; ordinary copy unchanged');
}
verifyInfantChargeStatus(fs.readFileSync(__dirname+'/../v2/visual-search/app.js','utf8'),source);
function records(code,baseline=false,requestPlaceholder=false){const output=[],checkInvocation=code===source&&!baseline&&!requestPlaceholder;
 for(const mode of ['empty','single','two','many','incomplete'])for(const long of [false,true])for(const meal of ['', 'BB']){
  const dom=new JSDOM('<div id="modal-body"></div><div id="modal-footer" hidden></div><dialog id="modal"></dialog>'),doc=dom.window.document;
  let offers=Array.from({length:mode==='empty'?0:mode==='single'?1:mode==='two'?2:5},(_,i)=>({key:'offer-'+i,day:'2026-10-01',returnDay:'2026-10-08',nights:7,total:119000+i*6500,room:requestPlaceholder&&mode==='incomplete'&&i===0?' on request ':i%2?'Family<&':'Standard<&',meal:requestPlaceholder&&mode==='incomplete'&&i===0?'On Request':i%3?'BB':'AI',operator:'Operator<&',flight:i%2?'regular':'charter'}));
  const h={id:1,name:'Hotel<&',resort:'Resort<&',stars:mode==='incomplete'?0:5,photos:mode==='incomplete'?[]:['photo1','photo2','photo3','photo4'],amenities:mode==='incomplete'?[]:Array.from({length:long?4:1},(_,i)=>({label:'Услуга<&'+i,group:'Группа<&'})),raw:mode==='incomplete'?{}:{description:long?'Длинное описание<& '.repeat(60):'Описание<&',hotelInformation:{services:{available:long?'Услуги<& '.repeat(45):'Услуги<&'},infrastructure:{beach:['Пляж<&','Пляж<&']}}}};
  let remembered=0,observed=0,synced=0,offerInventories=0;
  const ctx={window:dom.window,document:doc,queueMicrotask:fn=>fn(),$:s=>doc.querySelector(s),$$:s=>[...doc.querySelectorAll(s)],data:{text:v=>String(v??'')},hotels:[h],modalType:'hotel-details',
   plainHotelText:v=>String(v??'').replace(/<[^>]*>/g,' ').replace(/&nbsp;/gi,' ').replace(/\s+/g,' ').trim(),esc:v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),hotelOfferDraft:null,selectedOffer:null,sameSelectedTourConditions:()=>false,selectedPriceStatus:()=>'',needsRefresh:()=>false,operatorBadge:o=>'[operator:'+o+']',syncHotelOfferSelection:()=>{},hotelOffers:()=>{offerInventories++;return offers;},
   mealLabel:o=>o.meal,displayMealLabel:o=>String(o.meal??'').trim().toLowerCase()==='on request'?'Питание уточняется':o.meal||'Питание уточняется',roomLabel:o=>String(o.room??'').trim().toLowerCase()==='on request'?'Номер уточняется':o.room||'Номер уточняется',dateText:d=>d,nightsText:n=>n+' ночей',flightLabel:o=>o.flight,guestsText:()=> '2 взрослых',money:n=>n+' ₽',icon:n=>'['+n+']',offerCountText:n=>n+' туров',cardPriceNote:()=> 'Цена предложения',observeHotelRoomChoices:()=>observed++,syncHotelSectionNavigation:()=>synced++,rememberUIRoute:()=>remembered++,ratingValue:()=>mode==='incomplete'?null:4.5,ratingText:()=> '4,5',departureScopeText:()=> '1–7 октября',durationText:()=> '7 ночей',
   showModal:(type,title,kicker,body)=>{doc.querySelector('#modal-body').innerHTML=body;}
  };vm.createContext(ctx);vm.runInContext(code,ctx);let owner=baseline?ctx:ctx.window.AnyTourHotelDetails.create(ctx);const mountedOffers=owner.openHotelDetails(1);if(checkInvocation){assert.equal(offerInventories,1,'opening reuses one synchronous offer inventory');assert.strictEqual(mountedOffers,offers,'opening exposes the exact mounted offer array for same-turn restoration');}owner.renderHotelRooms(1,meal,['Standard<&']);if(checkInvocation)assert.equal(offerInventories,2,'later render computes a fresh offer inventory');
  output.push({mode,long,meal,body:doc.querySelector('#modal-body').innerHTML,...(requestPlaceholder?{text:doc.querySelector('#modal-body').textContent}:{}),footer:doc.querySelector('#modal-footer').innerHTML,footerHidden:doc.querySelector('#modal-footer').hidden,remembered,observed,synced});
  if(checkInvocation){offers=offers.map((offer,index)=>({...offer,total:offer.total+777+index}));owner.renderHotelRooms(1,meal,['Standard<&']);assert.equal(offerInventories,3,'updated-price render computes another fresh offer inventory');const updated=offers.find(offer=>!meal||offer.meal===meal);if(updated)assert.match(doc.querySelector('.hotel-room-cards').textContent,new RegExp(String(updated.total)),`later room price is rendered from the fresh inventory (${mode}/${meal})`);}dom.window.close();
 }return output;
}
const compare=process.argv.indexOf('--compare');if(compare>=0)assert.deepEqual(records(source),records(fs.readFileSync(process.argv[compare+1],'utf8'),true),'actual before/after hotel HTML and room DOM equivalence');
const actual=records(source),digest=crypto.createHash('sha256').update(JSON.stringify(actual)).digest('hex');
const placeholder=records(source,false,true).find(record=>record.mode==='incomplete'&&!record.long&&!record.meal);assert(placeholder);
assert.match(placeholder.text,/Номер уточняется/);assert.match(placeholder.text,/Питание уточняется/);assert.doesNotMatch(placeholder.text,/on request/i);
assert.match(placeholder.body,/data-room=" on request "/,'raw room identity stays on the existing grouping owner');assert.match(placeholder.body,/<option value="On Request">Питание уточняется<\/option>/,'raw meal filter value stays exact while its label is readable');
const historicalRoomOwner="function roomOfferChoiceHTML(o){\n return `<div class=\"room-offer-choice\" data-offer-key=\"${esc(o.key)}\"><div class=\"room-offer-conditions\"><strong class=\"room-offer-meal\">${esc(displayMealLabel(o))}</strong><dl class=\"room-choice-facts\"><div><dt class=\"sr-only\">Даты и отдых</dt><dd><time datetime=\"${esc(o.day)}\">${dateText(o.day)}</time> → <time datetime=\"${esc(o.returnDay)}\">${dateText(o.returnDay)}</time> · ${nightsText(o.nights)}</dd></div></dl><span class=\"room-offer-operator\">${esc(o.operator)}${flightLabel(o)?' · '+flightLabel(o):''}</span></div><div class=\"hotel-room-price\"><small>Весь тур · ${guestsText(o)}</small><strong>${money(o.total)}</strong><button class=\"primary\" data-action=\"offer\" data-key=\"${esc(o.key)}\" aria-label=\"Смотреть тур: ${esc(displayMealLabel(o))}, ${dateText(o.day)}, ${esc(o.operator)}, ${money(o.total)}\">Смотреть тур ${icon('arrow')}</button></div></div>`;\n}\nfunction renderHotelRooms(id,meal='',restoredRooms=null,initialOffers=null){\n const h=hotels.find(h=>h.id===id);if(!h||modalType!=='hotel-details')return;\n const allOffers=initialOffers||hotelOffers(h),meals=[...new Set(allOffers.map(o=>o.meal))];\n let mealControl=$('#hotel-room-meal');\n if(!mealControl&&meals.length>1&&allOffers.length>2){$('#hotel-room-count').insertAdjacentHTML('beforebegin',`<label class=\"hotel-room-meal-filter\">Питание в туре<select id=\"hotel-room-meal\" data-id=\"${h.id}\"></select></label>`);mealControl=$('#hotel-room-meal');}\n if(mealControl){const values=['',...meals];if(meal&&!values.includes(meal))values.push(meal);if(JSON.stringify([...mealControl.options].map(o=>o.value))!==JSON.stringify(values))mealControl.innerHTML=values.map(value=>`<option value=\"${esc(value)}\">${esc(value?displayMealLabel({meal:value}):'Любое питание')}</option>`).join('');}\n const offers=allOffers.filter(o=>!meal||o.meal===meal),rooms=[],byRoom=new Map();\n // Set merges signed zero; strict equality leaves the NaN group empty.\n offers.forEach(o=>{\n  const room=o.room;let group=byRoom.get(room);\n  if(!group){group={room:room===0?0:room,offers:[]};byRoom.set(room,group);rooms.push(group);}\n  if(room===room)group.offers.push(o);\n });\n $('#hotel-room-count').textContent=`Номера: ${rooms.length} · Туры: ${offers.length}`;\n const roomCards=$('.hotel-room-cards');roomCards.classList.toggle('single-direct-offer',offers.length===1);\n roomCards.innerHTML=rooms.map(({room,offers:rows})=>{\n  const choices=`<div class=\"room-offer-list\">${rows.slice(0,2).map(roomOfferChoiceHTML).join('')}${rows.length>2?`<details class=\"hotel-room-more\"><summary><span class=\"room-more-closed\">Ещё ${offerCountText(rows.length-2)}</span><span class=\"room-more-open\">Скрыть остальные туры</span></summary>${rows.slice(2).map(roomOfferChoiceHTML).join('')}</details>`:''}</div>`;\n  const title=`<h4>${esc(roomLabel({room}))}</h4>`;\n  if(rooms.length===1)return `<article class=\"hotel-room-card\" data-room=\"${esc(room)}\"><header class=\"room-choice-main\">${title}<span class=\"room-choice-label\">${offerCountText(rows.length)}</span></header>${choices}</article>`;\n  const meals=[...new Set(rows.map(displayMealLabel))].map(esc).join(' · '),min=Math.min(...rows.map(o=>o.total));\n  return `<details class=\"hotel-room-card room-overview\" data-room=\"${esc(room)}\" ${restoredRooms?.includes(room)?'open':''}><summary class=\"room-overview-toggle\"><span class=\"room-overview-name\">${title}<span class=\"room-overview-meals\">${meals}</span></span><span class=\"room-overview-bottom\"><strong class=\"room-overview-price\">от ${money(min)}</strong><span class=\"room-overview-action\"><span class=\"room-overview-closed\">${offerCountText(rows.length)}</span><span class=\"room-overview-open\">Свернуть</span><span class=\"rotate-arrow\" aria-hidden=\"true\">⌄</span></span></span></summary>${choices}</details>`;\n }).join('')||'<p class=\"tour-missing\">По текущим условиям предложений нет. Измените даты или фильтры поиска.</p>';\n const control=$('#hotel-room-meal');if(control){control.value=meal;[...control.options].forEach(option=>option.defaultSelected=option.value===meal);}\n const price=$('#hotel-detail-min'),button=$('#hotel-detail-offers');\n if(price)price.textContent=offers.length?(offers.length>1?'от ':'')+money(offers[0].total):'Нет предложений';\n const status=$('#hotel-detail-price-status');if(status)status.textContent=offers.length?cardPriceNote(offers[0]):'';\n if(button){button.dataset.action=offers.length===1?'offer':'hotel-section';button.dataset.target='hotel-rooms-heading';if(offers.length===1)button.dataset.key=offers[0].key;else delete button.dataset.key;button.innerHTML=(offers.length===1?'Смотреть тур':'Выбрать тур')+' '+icon('arrow');button.disabled=!offers.length;}\n queueMicrotask(()=>{observeHotelRoomChoices();syncHotelSectionNavigation();});\n rememberUIRoute();\n}\n";
const historicalSource=source.slice(0,source.indexOf('function roomOfferChoiceHTML('))+historicalRoomOwner+source.slice(source.indexOf('function openHotelDetails('));
const historical=records(historicalSource);assert.equal(crypto.createHash('sha256').update(JSON.stringify(historical)).digest('hex'),'d2debd3022a456ffcb1803db380fc87c57df6fe8f816cf6ce92e83fd910772e5','unchanged historical room/filter/footer oracle');
for(let n=0;n<actual.length;n++){const facts=r=>{const d=new JSDOM(r.body).window.document;return {roomKeys:[...d.querySelectorAll('[data-room]')].map(e=>e.dataset.room),mealOptions:[...d.querySelectorAll('#hotel-room-meal option')].map(e=>[e.value,e.textContent]),choices:[...d.querySelectorAll('.room-offer-choice')].map(e=>[e.dataset.offerKey,e.querySelector('.hotel-room-price strong').textContent])};};assert.deepEqual(facts(actual[n]),facts(historical[n]),'exact room group/meal/raw key/whole price inventory retained');}
assert.notDeepEqual(records(source.replace('rows.slice(0,2)','rows.slice(0,1)')),actual,'lost visible room choice detected');
assert.notDeepEqual(records(source.replace('o.meal===meal','o.meal!==meal')),actual,'wrong meal selection detected');
console.log('PASS hotel presentation '+actual.length+' observations: original HTML/footer/rooms/meal/price/escaping and two mutations; '+digest);

// Execute the inventory inside the actual renderer, independently of the DOM.
// The original Set + strict-equality filters remain the test-only reference.
const inventoryStart=source.indexOf(' const offers=allOffers.filter('),inventoryEnd=source.indexOf(" $('#hotel-room-count').textContent",inventoryStart);
assert(inventoryStart>=0&&inventoryEnd>inventoryStart,'actual room inventory boundaries');
const inventory=vm.runInNewContext('(allOffers,meal)=>{'+source.slice(inventoryStart,inventoryEnd)+'return {offers,rooms};}',{Map,Set,id:1,context:{hotelOfferDraft:null}});
const originalInventory=(allOffers,meal)=>{const offers=allOffers.filter(o=>!meal||o.meal===meal);return {offers,rooms:[...new Set(offers.map(o=>o.room))].map(room=>({room,offers:offers.filter(o=>o.room===room)}))};};
let inventoryCases=0;
function checkInventory(allOffers,meal){
 const expected=originalInventory(allOffers,meal),actual=inventory(allOffers,meal);
 assert.equal(actual.offers.length,expected.offers.length);actual.offers.forEach((offer,index)=>assert.strictEqual(offer,expected.offers[index],'filtered raw offer identity/order'));
 assert.equal(actual.rooms.length,expected.rooms.length,'first-seen group count');
 actual.rooms.forEach((group,index)=>{
  const old=expected.rooms[index];assert(Object.is(group.room,old.room),'Set room order, zero normalization and NaN identity');assert.equal(group.offers.length,old.offers.length,'strict-equality group membership');
  group.offers.forEach((offer,i)=>assert.strictEqual(offer,old.offers[i],'raw grouped offer identity/order'));
 });inventoryCases++;
}
const objectRoom={},otherObjectRoom={},symbolRoom=Symbol('room'),roomValues=['R<&','Family','',undefined,null,NaN,-0,0,false,objectRoom,otherObjectRoom,symbolRoom];let seed=57913;
const random=()=>((seed=(seed*1664525+1013904223)>>>0)/4294967296);
for(let round=0;round<500;round++){
 const rows=Array.from({length:Math.floor(random()*65)},(_,i)=>({key:round+':'+i,room:roomValues[Math.floor(random()*roomValues.length)],meal:['AI','BB',''][Math.floor(random()*3)]}));
 if(round%7===0&&rows.length>2){delete rows[1];const prototype=Object.create(Array.prototype);prototype[1]={key:'inherited',room:'Inherited',meal:'BB'};Object.setPrototypeOf(rows,prototype);}
 if(round%11===0&&rows.length>4)delete rows[3];
 const before=rows.slice(),keys=Object.keys(rows);checkInventory(rows,['','AI','BB','missing',null][round%5]);assert.deepEqual(Object.keys(rows),keys,'native input slots retained');before.forEach((row,index)=>assert.strictEqual(rows[index],row,'raw input references retained'));
}
checkInventory([{room:-0,meal:'AI'},{room:0,meal:'BB'},{room:NaN,meal:'AI'},{room:NaN,meal:'BB'},{room:undefined,meal:'BB'}],'');
// The meal filter keeps native initial-length semantics before grouping.
function growingInput(){const rows=[{room:'First',get meal(){rows.push({room:'Late',meal:'AI'});return 'AI';}},{room:'Second',meal:'AI'}];return rows;}
const grown=inventory(growingInput(),'AI'),oldGrown=originalInventory(growingInput(),'AI');assert.deepEqual(Array.from(grown.rooms,group=>group.room),oldGrown.rooms.map(group=>group.room));assert.equal(grown.offers.length,2);
let reads=0;const work=Array.from({length:1000},(_,index)=>({key:'work:'+index,meal:'AI',get room(){reads++;return 'Room '+index%50;}}));
originalInventory(work,'');const oldReads=reads;reads=0;const current=inventory(work,'');assert.equal(oldReads,51000);assert.equal(reads,1000);assert.equal(current.rooms.length,50);assert.equal(current.rooms.reduce((sum,group)=>sum+group.offers.length,0),1000);
console.log('PASS hotel room inventory: '+inventoryCases+' original grouping cases; native sparse/inherited/initial-length, zero/NaN and raw identity/order; room reads '+oldReads+'→'+reads+'; supplier/lead HTTP 0');

// Price-note regression: comparisons never silently promote listing money.
function verifyRoomPriceStatus(appSource,hotelSource){
 const rowStart=hotelSource.indexOf('function roomOfferChoiceHTML('),rowEnd=hotelSource.indexOf('function renderHotelRooms(',rowStart);
 const footerStart=appSource.indexOf('function syncHotelOfferSelection('),footerEnd=appSource.indexOf('function observeHotelRoomChoices(',footerStart);
 assert(rowStart>=0&&rowEnd>rowStart&&footerStart>=0&&footerEnd>footerStart);
 const raw={},search={origin:'Москва',country:'4'},listing={key:'one',hotelId:1,raw,search,total:99938,room:'Standard',meal:'BB',day:'2026-10-05',returnDay:'2026-10-12',nights:7,operator:'FUN&SUN',provider:'tourvisor',cached:true};
 const snapshot='Снимок · цена требует проверки',priced='Цена с выбранными рейсами · сбор включён';
 const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const retained={...listing,cached:false,tour:{price:99938},quoteListingTotal:99938,flightChoiceId:'pair-2'};
 const context={hotelOfferDraft:{hotelId:1,key:'one'},selectedOffer:null};
 const same=(a,b)=>a.room===b.room&&a.meal===b.meal&&a.day===b.day;
 const helpers={context,esc,sameSelectedTourConditions:same,selectedPriceStatus:o=>o.tour?priced:'Цена из выдачи · демо',cardPriceNote:()=>snapshot,displayMealLabel:o=>o.meal,operatorBadge:o=>o,flightLabel:()=>'',dateText:v=>v,nightsText:v=>v+' ночей',guestsText:()=> '2 взр.',money:v=>v+' ₽'};
 const rowContext=vm.createContext(helpers);vm.runInContext(hotelSource.slice(rowStart,rowEnd),rowContext);
 const row=o=>rowContext.roomOfferChoiceHTML(o);
 const rowNote=html=>html.match(/<small class="room-offer-price-status">([^<]+)<\/small>/)?.[1];
 assert.equal(rowNote(row(listing)),snapshot,'room comparison retains the listing source/status');
 context.selectedOffer=retained;
 assert.equal(rowNote(row(listing)),priced,'a matched retained choice keeps selected pair/fuel status');
 context.selectedOffer={...retained,key:'another'};
 assert.equal(rowNote(row(listing)),snapshot,'another offer cannot lend its verified status');
 context.selectedOffer={...retained,pricePending:true};
 assert.equal(rowNote(row(listing)),snapshot,'pending pair cannot lend its verified status');
 context.selectedOffer=null;helpers.cardPriceNote=()=>'<unverified>';
 assert.equal(rowNote(row(listing)),'&lt;unverified&gt;','price-note text stays escaped');
 const nodes={'#hotel-detail-min':{},'#hotel-detail-price-status':{},'#hotel-detail-offers':{dataset:{}}};
 const f={hotelOfferCheckActive:()=>false,hotels:[{id:1}],hotelOffers:()=>[listing],hotelOfferDraft:null,selectedOffer:null,state:{search},searchKey:s=>JSON.stringify(s),sameSelectedTourConditions:same,selectedPriceStatus:helpers.selectedPriceStatus,cardPriceNote:()=>snapshot,money:helpers.money,mealLabel:helpers.displayMealLabel,dateText:helpers.dateText,needsRefresh:()=>true,$:s=>nodes[s],$$:()=>[]};
 vm.createContext(f);vm.runInContext(appSource.slice(footerStart,footerEnd),f);
 f.syncHotelOfferSelection(1);
 assert.match(nodes['#hotel-detail-price-status'].textContent,/Снимок · цена требует проверки/,'minimum footer identifies listing price');
 f.hotelOfferDraft={hotelId:1,key:'one'};f.syncHotelOfferSelection(1);
 assert.match(nodes['#hotel-detail-price-status'].textContent,/Снимок · цена требует проверки/,'selected listing footer retains uncertainty');
 assert.equal(nodes['#hotel-detail-min'].textContent,'99938 ₽');
 f.selectedOffer=retained;f.syncHotelOfferSelection(1);
 assert(nodes['#hotel-detail-price-status'].textContent.endsWith(priced),'retained footer preserves the current pair/fuel status');
 assert.equal(nodes['#hotel-detail-min'].textContent,'99938 ₽','annotation cannot add fuel to the amount');
 f.selectedOffer={...retained,search:{...search,origin:'Другой город'}};f.syncHotelOfferSelection(1);
 assert(nodes['#hotel-detail-price-status'].textContent.endsWith(snapshot),'another search cannot lend its verified status');
 assert.equal(nodes['#hotel-detail-offers'].dataset.key,'one','exact offer action stays unchanged');
}
verifyRoomPriceStatus(fs.readFileSync(__dirname+'/../v2/visual-search/app.js','utf8'),source);
console.log('PASS room price status: listing/minimum/selected/current pair/pending/foreign identity/escaping; amounts unchanged');
