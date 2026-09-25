'use strict';
// Presentation compatibility only: data, requests, identity and money belong to the canonical adapter.
(() => {
 const canonical=window.AnyTourPrototypeData,lead=window.AnyTourPrototypeLead;
 if(!canonical||!lead)throw new Error('Не удалось загрузить поиск. Обновите страницу.');
 const first=new Date(Date.now()+14*86400000).toISOString().slice(0,10);
 const last=new Date(Date.parse(first+'T12:00:00Z')+6*86400000).toISOString().slice(0,10);
 const bridge=Object.create(canonical);
 Object.defineProperties(bridge,{
  live:{value:true},preview:{value:false},scenario:{value:'live'},
  initialSearch:{value:Object.freeze({origin:'Москва',country:'4',from:first,to:last,minNights:7,maxNights:7,adults:2,ages:[]})},
  describe:{value:()=> 'Живой поиск: ТВ, САМО и ANEX. Календарь — ранее найденные цены. Заявки не отправляются.'}
 });
 window.AnyTourPrototypeData=Object.freeze(bridge);
 window.AnyTourPrototypeLead=Object.freeze({...lead,
  canApply:o=>!!o?.tour&&o.provider==='tourvisor'&&!o.cached&&!o.loading&&!o.quoteError&&!o.flightsLoading&&!o.flightsError&&!o.pricePending&&o.variants?.length>0,
  unavailableMarkup:()=>'<section class="tour-recording-limit"><h3>Проверьте предложение</h3><p>Цена, наличие и рейсы уточняются для выбранных условий.</p></section>'
 });
 for(const id of ['recording-file','recording-status']){
  const node=document.getElementById(id);if(node)(id==='recording-file'?node.closest('label'):node).hidden=true;
 }
})();
