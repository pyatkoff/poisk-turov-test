'use strict';
const assert=require('node:assert/strict'),fs=require('fs'),vm=require('vm'),crypto=require('crypto'),{JSDOM}=require('jsdom');
const source=fs.readFileSync(__dirname+'/../v2/visual-search/hotel-details-v1.js','utf8');
function records(code,baseline=false){const output=[];
 for(const mode of ['empty','single','two','many','incomplete'])for(const long of [false,true])for(const meal of ['', 'BB']){
  const dom=new JSDOM('<div id="modal-body"></div><div id="modal-footer" hidden></div><dialog id="modal"></dialog>'),doc=dom.window.document;
  const offers=Array.from({length:mode==='empty'?0:mode==='single'?1:mode==='two'?2:5},(_,i)=>({key:'offer-'+i,day:'2026-10-01',returnDay:'2026-10-08',nights:7,total:119000+i*6500,room:i%2?'Family<&':'Standard<&',meal:i%3?'BB':'AI',operator:'Operator<&',flight:i%2?'regular':'charter'}));
  const h={id:1,name:'Hotel<&',resort:'Resort<&',stars:mode==='incomplete'?0:5,photos:mode==='incomplete'?[]:['photo1','photo2','photo3','photo4'],amenities:mode==='incomplete'?[]:Array.from({length:long?4:1},(_,i)=>({label:'Услуга<&'+i,group:'Группа<&'})),raw:mode==='incomplete'?{}:{description:long?'Длинное описание<& '.repeat(60):'Описание<&',hotelInformation:{services:{available:long?'Услуги<& '.repeat(45):'Услуги<&'},infrastructure:{beach:['Пляж<&','Пляж<&']}}}};
  let remembered=0,observed=0,synced=0;
  const ctx={window:dom.window,document:doc,queueMicrotask:fn=>fn(),$:s=>doc.querySelector(s),$$:s=>[...doc.querySelectorAll(s)],data:{text:v=>String(v??'')},hotels:[h],modalType:'hotel-details',
   plainHotelText:v=>String(v??'').replace(/<[^>]*>/g,' ').replace(/&nbsp;/gi,' ').replace(/\s+/g,' ').trim(),esc:v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),hotelOffers:()=>offers,
   mealLabel:o=>o.meal,dateText:d=>d,nightsText:n=>n+' ночей',flightLabel:o=>o.flight,guestsText:()=> '2 взрослых',money:n=>n+' ₽',icon:n=>'['+n+']',offerCountText:n=>n+' туров',cardPriceNote:()=> 'Цена предложения',observeHotelRoomChoices:()=>observed++,syncHotelSectionNavigation:()=>synced++,rememberUIRoute:()=>remembered++,ratingValue:()=>mode==='incomplete'?null:4.5,ratingText:()=> '4,5',departureScopeText:()=> '1–7 октября',durationText:()=> '7 ночей',
   showModal:(type,title,kicker,body)=>{doc.querySelector('#modal-body').innerHTML=body;}
  };vm.createContext(ctx);vm.runInContext(code,ctx);let owner=baseline?ctx:ctx.window.AnyTourHotelDetails.create(ctx);owner.openHotelDetails(1);owner.renderHotelRooms(1,meal,['Standard<&']);
  output.push({mode,long,meal,body:doc.querySelector('#modal-body').innerHTML,footer:doc.querySelector('#modal-footer').innerHTML,footerHidden:doc.querySelector('#modal-footer').hidden,remembered,observed,synced});dom.window.close();
 }return output;
}
const compare=process.argv.indexOf('--compare');if(compare>=0)assert.deepEqual(records(source),records(fs.readFileSync(process.argv[compare+1],'utf8'),true),'actual before/after hotel HTML and room DOM equivalence');
const actual=records(source),digest=crypto.createHash('sha256').update(JSON.stringify(actual)).digest('hex');
if(!process.argv.includes('--capture'))assert.equal(digest,'d2debd3022a456ffcb1803db380fc87c57df6fe8f816cf6ce92e83fd910772e5','original hotel presentation observations');
assert.notDeepEqual(records(source.replace('rows.slice(0,2)','rows.slice(0,1)')),actual,'lost visible room choice detected');
assert.notDeepEqual(records(source.replace('o.meal===meal','o.meal!==meal')),actual,'wrong meal selection detected');
console.log('PASS hotel presentation '+actual.length+' observations: original HTML/footer/rooms/meal/price/escaping and two mutations; '+digest);
