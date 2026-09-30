'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),{JSDOM}=require('jsdom');
const app=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/app.js'),'utf8');
const start=app.indexOf('// Source HTML stays inert.'),end=app.indexOf('function hotelHighlights',start);
assert(start>=0&&end>start,'visual app exposes one canonical inert hotel-text owner');
const dom=new JSDOM('<div id="modal-body"></div><div id="modal-footer" hidden></div><dialog id="modal"></dialog>'),document=dom.window.document;
const helper={document};vm.createContext(helper);vm.runInContext(app.slice(start,end)+';globalThis.hotelText={hotelContentText,plainHotelText};',helper);
const {hotelContentText,plainHotelText}=helper.hotelText;
const encoded='&#8203;<p>Отель состоит из <strong>двух</strong> корпусов &amp; SPA&nbsp;— &#x41;.</p>&ZeroWidthSpace;Конец.';
assert.equal(plainHotelText(encoded),'Отель состоит из двух корпусов & SPA — A. Конец.','decimal/hex/named entities and zero-width import artifacts become plain text');
assert.equal(hotelContentText('<script>danger()</script><style>bad{}</style><p>Первая строка</p><div>Вторая<br>третья</div>&lt;script&gt;текст&lt;/script&gt;'),'Первая строка\nВторая\nтретья\n<script>текст</script>','active supplier nodes are removed while encoded markup remains inert text');
const hotelOwner=fs.readFileSync(path.resolve(__dirname,'../v2/visual-search/hotel-details-v1.js'),'utf8');
const hotel={id:1,name:'VIKING EXPRESS',resort:'Кемер',stars:4,photos:[],amenities:[],raw:{description:encoded+'&lt;script&gt;alert(1)&lt;/script&gt;'}};
const ctx={window:dom.window,document,queueMicrotask:fn=>fn(),$:s=>document.querySelector(s),$$:s=>[...document.querySelectorAll(s)],data:{text:v=>String(v??'')},hotels:[hotel],modalType:'hotel-details',plainHotelText,
 esc:v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),hotelOffers:()=>[],mealLabel:()=>'',dateText:v=>v,nightsText:v=>String(v),flightLabel:()=>'',guestsText:()=>'',money:v=>String(v),icon:()=>'',offerCountText:v=>String(v),cardPriceNote:()=>'',observeHotelRoomChoices:()=>{},syncHotelSectionNavigation:()=>{},rememberUIRoute:()=>{},ratingValue:()=>null,ratingText:()=>'',departureScopeText:()=>'',durationText:()=>'',
 showModal:(type,title,kicker,body)=>{document.querySelector('#modal-body').innerHTML=body;}};
vm.createContext(ctx);vm.runInContext(hotelOwner,ctx);ctx.window.AnyTourHotelDetails.create(ctx).openHotelDetails(1);
const body=document.querySelector('#modal-body'),visible=body.textContent;
assert.match(visible,/Отель состоит из двух корпусов & SPA — A\. Конец\.<script>текст<\/script>|Отель состоит из двух корпусов & SPA — A\. Конец\.<script>alert\(1\)<\/script>/,'hotel detail receives decoded readable profile text');
assert.doesNotMatch(visible,/&#8203;|&ZeroWidthSpace;|\u200B|\uFEFF/,'entity and invisible artifacts are absent from rendered detail');
assert.equal(body.querySelectorAll('script,style,iframe,object,embed,svg,math,template').length,0,'decoded supplier markup never becomes active DOM');
dom.window.close();
console.log('PASS visual hotel text: entity decoding, invisible cleanup, inert markup, escaped detail output');
