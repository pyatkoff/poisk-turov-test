'use strict';
const assert=require('node:assert/strict'),fs=require('fs'),vm=require('vm'),crypto=require('crypto'),{JSDOM}=require('jsdom');
const source=fs.readFileSync(__dirname+'/../v2/visual-search/hotel-details-v1.js','utf8');
function records(code,baseline=false){const output=[],checkInvocation=code===source&&!baseline;
 for(const mode of ['empty','single','two','many','incomplete'])for(const long of [false,true])for(const meal of ['', 'BB']){
  const dom=new JSDOM('<div id="modal-body"></div><div id="modal-footer" hidden></div><dialog id="modal"></dialog>'),doc=dom.window.document;
  let offers=Array.from({length:mode==='empty'?0:mode==='single'?1:mode==='two'?2:5},(_,i)=>({key:'offer-'+i,day:'2026-10-01',returnDay:'2026-10-08',nights:7,total:119000+i*6500,room:i%2?'Family<&':'Standard<&',meal:i%3?'BB':'AI',operator:'Operator<&',flight:i%2?'regular':'charter'}));
  const h={id:1,name:'Hotel<&',resort:'Resort<&',stars:mode==='incomplete'?0:5,photos:mode==='incomplete'?[]:['photo1','photo2','photo3','photo4'],amenities:mode==='incomplete'?[]:Array.from({length:long?4:1},(_,i)=>({label:'Услуга<&'+i,group:'Группа<&'})),raw:mode==='incomplete'?{}:{description:long?'Длинное описание<& '.repeat(60):'Описание<&',hotelInformation:{services:{available:long?'Услуги<& '.repeat(45):'Услуги<&'},infrastructure:{beach:['Пляж<&','Пляж<&']}}}};
  let remembered=0,observed=0,synced=0,offerInventories=0;
  const ctx={window:dom.window,document:doc,queueMicrotask:fn=>fn(),$:s=>doc.querySelector(s),$$:s=>[...doc.querySelectorAll(s)],data:{text:v=>String(v??'')},hotels:[h],modalType:'hotel-details',
   plainHotelText:v=>String(v??'').replace(/<[^>]*>/g,' ').replace(/&nbsp;/gi,' ').replace(/\s+/g,' ').trim(),esc:v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),hotelOffers:()=>{offerInventories++;return offers;},
   mealLabel:o=>o.meal,dateText:d=>d,nightsText:n=>n+' ночей',flightLabel:o=>o.flight,guestsText:()=> '2 взрослых',money:n=>n+' ₽',icon:n=>'['+n+']',offerCountText:n=>n+' туров',cardPriceNote:()=> 'Цена предложения',observeHotelRoomChoices:()=>observed++,syncHotelSectionNavigation:()=>synced++,rememberUIRoute:()=>remembered++,ratingValue:()=>mode==='incomplete'?null:4.5,ratingText:()=> '4,5',departureScopeText:()=> '1–7 октября',durationText:()=> '7 ночей',
   showModal:(type,title,kicker,body)=>{doc.querySelector('#modal-body').innerHTML=body;}
  };vm.createContext(ctx);vm.runInContext(code,ctx);let owner=baseline?ctx:ctx.window.AnyTourHotelDetails.create(ctx);owner.openHotelDetails(1);if(checkInvocation)assert.equal(offerInventories,1,'opening reuses one synchronous offer inventory');owner.renderHotelRooms(1,meal,['Standard<&']);if(checkInvocation)assert.equal(offerInventories,2,'later render computes a fresh offer inventory');
  output.push({mode,long,meal,body:doc.querySelector('#modal-body').innerHTML,footer:doc.querySelector('#modal-footer').innerHTML,footerHidden:doc.querySelector('#modal-footer').hidden,remembered,observed,synced});
  if(checkInvocation){offers=offers.map((offer,index)=>({...offer,total:offer.total+777+index}));owner.renderHotelRooms(1,meal,['Standard<&']);assert.equal(offerInventories,3,'updated-price render computes another fresh offer inventory');const updated=offers.find(offer=>!meal||offer.meal===meal);if(updated)assert.match(doc.querySelector('.hotel-room-cards').textContent,new RegExp(String(updated.total)),`later room price is rendered from the fresh inventory (${mode}/${meal})`);}dom.window.close();
 }return output;
}
const compare=process.argv.indexOf('--compare');if(compare>=0)assert.deepEqual(records(source),records(fs.readFileSync(process.argv[compare+1],'utf8'),true),'actual before/after hotel HTML and room DOM equivalence');
const actual=records(source),digest=crypto.createHash('sha256').update(JSON.stringify(actual)).digest('hex');
if(!process.argv.includes('--capture'))assert.equal(digest,'d2debd3022a456ffcb1803db380fc87c57df6fe8f816cf6ce92e83fd910772e5','original hotel presentation observations');
assert.notDeepEqual(records(source.replace('rows.slice(0,2)','rows.slice(0,1)')),actual,'lost visible room choice detected');
assert.notDeepEqual(records(source.replace('o.meal===meal','o.meal!==meal')),actual,'wrong meal selection detected');
console.log('PASS hotel presentation '+actual.length+' observations: original HTML/footer/rooms/meal/price/escaping and two mutations; '+digest);

// Execute the inventory inside the actual renderer, independently of the DOM.
// The original Set + strict-equality filters remain the test-only reference.
const inventoryStart=source.indexOf(' const offers=allOffers.filter('),inventoryEnd=source.indexOf(" $('#hotel-room-count').textContent",inventoryStart);
assert(inventoryStart>=0&&inventoryEnd>inventoryStart,'actual room inventory boundaries');
const inventory=vm.runInNewContext('(allOffers,meal)=>{'+source.slice(inventoryStart,inventoryEnd)+'return {offers,rooms};}',{Map,Set});
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
