'use strict';
// Presentation compatibility only: data, requests, identity and money belong to the canonical adapter.
(() => {
 const canonical=window.AnyTourPrototypeData,lead=window.AnyTourPrototypeLead;
 if(!canonical||!lead)throw new Error('Не удалось загрузить поиск. Обновите страницу.');
 const runtime=window.V2Runtime;
 if(runtime&&typeof runtime.on==='function')runtime.on('flights',(_data,response)=>{
  let value;
  try{value=response?.ok?response.headers?.get('X-AnyTour-Fuel-Monitoring'):null;}catch{return;}
  if(typeof value==='string'&&value.length<=2048&&/^[A-Za-z0-9_-]+$/.test(value))console.info('AnyTourFuelMonitoringV1',value);
 });
 const today=new Date(),first=new Date(Date.UTC(today.getFullYear(),today.getMonth(),today.getDate()+14)).toISOString().slice(0,10);
 const last=new Date(Date.parse(first+'T12:00:00Z')+6*86400000).toISOString().slice(0,10);
 const bridge=Object.create(canonical);
 Object.defineProperties(bridge,{
  live:{value:true},preview:{value:false},scenario:{value:'live'},
  initialSearch:{value:Object.freeze({origin:'Москва',country:'4',from:first,to:last,minNights:7,maxNights:7,adults:2,ages:[]})},
  describe:{value:()=> 'Живой поиск: Tourvisor и прямой ANEX. Календарь — ранее найденные цены. Чтобы передать выбранный тур менеджеру, перейдите в Telegram или MAX и запустите бота. Бронирование и оплата на сайте не выполняются.'}
 });
 window.AnyTourPrototypeData=Object.freeze(bridge);
 window.AnyTourPrototypeLead=Object.freeze({...lead,
  canApply:o=>!!o?.tour&&o.provider==='tourvisor'&&!o.cached&&!o.loading&&!o.quoteError&&!o.flightsLoading&&!o.pricePending&&Number.isFinite(Number(o.total))&&Number(o.total)>0,
  unavailableMarkup:()=>'<section class="tour-recording-limit"><h3>Проверьте предложение</h3><p>Цена и наличие тура должны быть актуализированы. Рейс можно оставить менеджеру.</p></section>'
 });
 for(const id of ['recording-file','recording-status']){
  const node=document.getElementById(id);if(node)(id==='recording-file'?node.closest('label'):node).hidden=true;
 }
})();
