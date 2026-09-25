'use strict';
// Local display rehearsal only. Imported values never authorize supplier/lead requests.
(() => {
 const base=window.AnyTourPrototypeData,clone=structuredClone;
 let recording=null,active=false;
 const fail=message=>{throw new Error(message);};
 const str=(v,label)=>typeof v==='string'&&v.trim()&&v.length<=500?v:fail('Нет корректного поля: '+label);
 const num=(v,label,min=1,max=1e9)=>typeof v==='number'&&Number.isFinite(v)&&v>=min&&v<=max?v:fail('Некорректное число: '+label);
 const integer=(v,label,min,max)=>Number.isInteger(num(v,label,min,max))?v:fail('Нужно целое число: '+label);
 const date=(v,label)=>{str(v,label);const n=Date.parse(v+'T12:00:00Z');if(!/^\d{4}-\d{2}-\d{2}$/.test(v)||!Number.isFinite(n)||new Date(n).toISOString().slice(0,10)!==v)fail('Некорректная дата: '+label);return v;};
 const list=(v,label,max)=>Array.isArray(v)&&v.length&&v.length<=max?v:fail('Нет списка: '+label);
 const photos=value=>Array.isArray(value)?value.filter(url=>typeof url==='string'&&url.length<=2000&&(/^\.\/assets\/[a-zA-Z0-9._/-]+$/.test(url)||(()=>{try{const u=new URL(url);return u.protocol==='https:'&&!u.username&&!u.password;}catch{return false;}})())).slice(0,12):[];
 const optional=v=>v==null?null:typeof v==='number'?num(v,'багаж',0):str(v,'багаж');
 function segment(v){
  const endpoint=p=>({port:str(p?.port,'аэропорт'),date:date(p?.date,'дата рейса'),time:/^([01]\d|2[0-3]):[0-5]\d$/.test(p?.time)?p.time:fail('Нет времени рейса')});
  return {number:str(v.number,'номер рейса'),company:str(v.company,'авиакомпания'),plane:typeof v.plane==='string'?v.plane:'',departure:endpoint(v.departure),arrival:endpoint(v.arrival),baggage:optional(v.baggage),carryOn:optional(v.carryOn)};
 }
 function validate(raw){
  if(raw?.schemaVersion!==1||!['recorded','demo'].includes(raw.kind)||raw.currency!=='RUB')fail('Нужна запись AnyTour v1 в рублях. HAR и обычная выдача не подходят.');
  const capturedAt=str(raw.capturedAt,'время записи');if(!Number.isFinite(Date.parse(capturedAt)))fail('Нет даты записи');
  const source=str(raw.source,'источник'),s=raw.search||{};
  const search={origin:str(s.origin,'город вылета'),country:str(s.country,'страна'),from:date(s.from,'начало периода'),to:date(s.to,'конец периода'),minNights:integer(s.minNights,'ночи от',1,28),maxNights:integer(s.maxNights,'ночи до',1,28),adults:integer(s.adults,'взрослые',1,6),ages:Array.isArray(s.ages)&&s.ages.length<=3?s.ages.map(a=>integer(a,'возраст',0,17)):fail('Нет возраста детей')};
  if(search.from>search.to||search.minNights>search.maxNights)fail('Перевёрнут диапазон поездки');
  const entries=new Map(),quoteIds=new Set(),keys=new Set(),hotelIds=new Set();
  for(const e of list(raw.entries,'цены и рейсы',100)){
   const key=str(e.offerKey,'связь с предложением'),q=e.quote||{},id=str(q.id,'ID цены');if(entries.has(key)||quoteIds.has(id))fail('Повторяющаяся связь тура и цены');quoteIds.add(id);
   const quote={id,price:num(q.price,'цена тура'),roomType:str(q.roomType,'номер'),meal:str(q.meal,'питание'),day:date(q.day,'вылет'),returnDay:date(q.returnDay,'возвращение')};
   const flights=list(e.flights,'рейсы',100).map(v=>({isDefault:v.isDefault===true,price:v.price===null?null:num(v.price,'цена с рейсом'),fuelCharge:v.fuelCharge==null?null:num(v.fuelCharge,'топливо',0),forward:list(v.forward,'рейсы туда',4).map(segment),backward:list(v.backward,'рейсы обратно',4).map(segment)}));
   const defaults=flights.filter(v=>v.isDefault);if(defaults.length!==1||defaults[0].price!==quote.price)fail('Основной перелёт не совпадает с ценой тура');
   for(const f of flights){if(f.forward[0].departure.date!==quote.day||f.backward[0].departure.date!==quote.returnDay)fail('Даты перелёта не совпадают с туром');for(const legs of [f.forward,f.backward])for(let i=1;i<legs.length;i++)if(legs[i-1].arrival.port!==legs[i].departure.port)fail('Несвязанные сегменты перелёта');}
   entries.set(key,{quote,flights});
  }
  const hotels=list(raw.hotels,'отели',30).map(h=>{
   const id=integer(h.id,'ID отеля',1,1e12);if(hotelIds.has(id))fail('Повторяющийся отель');hotelIds.add(id);
   const offers=list(h.offers,'предложения',100).map((o,i)=>{
    const key=str(o.key,'ID предложения');if(keys.has(key))fail('Повторяющееся предложение');keys.add(key);const e=entries.get(key);if(!e)fail('У предложения нет сохранённой цены и рейсов');
    const day=date(o.day,'дата тура'),returnDay=date(o.returnDay,'возвращение'),nights=integer(o.nights,'ночи',1,28),room=str(o.room,'номер'),meal=str(o.meal,'питание');
    if(day!==e.quote.day||returnDay!==e.quote.returnDay||room!==e.quote.roomType||meal!==e.quote.meal)fail('Цена относится к другому туру, номеру или питанию');
    if(day<search.from||day>search.to||nights<search.minNights||nights>search.maxNights||returnDay<day)fail('Тур не совпадает с условиями поиска');
    if(o.adults!==search.adults||JSON.stringify(o.ages)!==JSON.stringify(search.ages))fail('Состав туристов не совпадает с записью');
    return {key,hotelId:id,variant:i,provider:'recorded',recordingKind:raw.kind,cached:false,raw:{selectionEnabled:true},origin:search.origin,search:clone(search),day,returnDay,nights,adults:search.adults,ages:[...search.ages],room,meal,operator:str(o.operator,'туроператор'),flight:['regular','charter','unknown'].includes(o.flight)?o.flight:'unknown',total:num(o.total,'цена в выдаче'),flightChoiceId:null};
   });
   return {id,name:str(h.name,'отель'),country:search.country,resort:typeof h.resort==='string'?h.resort:'',region:'',subRegion:'',stars:h.stars==null?0:integer(h.stars,'категория',0,5),rating:h.rating==null?null:num(h.rating,'рейтинг',0,5),photos:photos(h.photos),amenities:[],legacyIds:[],beach:null,family:false,spa:false,raw:{description:'',arrival:typeof h.resort==='string'?h.resort:''},offers};
  });
  if(keys.size!==entries.size)fail('В записи есть цена без предложения');
  const catalog={countries:[{id:search.country,name:str(raw.countryName,'название страны')}],departures:[{id:'1',name:search.origin}],meals:[...new Set(hotels.flatMap(h=>h.offers.map(o=>o.meal)))].map(name=>({name})),regions:{[search.country]:[]}};
  return {kind:raw.kind,capturedAt,source,search,hotels,entries,catalog};
 }
 function filtered(s){
  if(s.origin!==recording.search.origin||String(s.country)!==recording.search.country||s.adults!==recording.search.adults||JSON.stringify(s.ages)!==JSON.stringify(recording.search.ages))return [];
  return recording.hotels.map(h=>({...h,offers:h.offers.filter(o=>o.day>=s.from&&o.day<=s.to&&o.nights>=s.minNights&&o.nights<=s.maxNights)})).filter(h=>h.offers.length);
 }
 const api={...base,
  get scenario(){return active?'recorded':base.scenario;},get initialSearch(){return active?clone(recording.search):base.initialSearch;},get catalog(){return active?clone(recording.catalog):base.catalog;},get clockStart(){return active?recording.search.from:base.clockStart;},get metadata(){return active?{capturedAt:recording.capturedAt,hotels:recording.hotels.length,offers:recording.entries.size}:base.metadata;},
  describe:()=>active?`${recording.kind==='demo'?'Демонстрационная запись':'Загруженная запись'} от ${new Date(recording.capturedAt).toLocaleDateString('ru-RU')}: ${recording.hotels.length} отелей, ${recording.entries.size} туров. Без обновления наличия и отправки заявки. После перезагрузки загрузите файл снова.`:new URLSearchParams(location.search).get('scenario')==='recorded'?'Запись не сохраняется после перезагрузки. Загрузите файл снова. Пока открыт сохранённый пример без рейсов.':base.describe(),
  async setScenario(value){base.stop();if(value==='recorded'){if(!recording)fail('Сначала загрузите файл записи');active=true;}else{active=false;await base.setScenario(value);}},
  async init(...a){return active?clone(recording.catalog):base.init(...a);},async countries(...a){return active?clone(recording.catalog):base.countries(...a);},async regions(...a){return active?[]:base.regions(...a);},
  async search(s,emit,...a){if(!active)return base.search(s,emit,...a);emit({type:'results',hotels:clone(filtered(s))});emit({type:'complete',canContinue:false});},
  async calendarPrices(s,...a){return active?{hotels:clone(filtered(s)),observations:[],partial:false}:base.calendarPrices(s,...a);},observationScopeSupported:(...a)=>active?false:base.observationScopeSupported(...a),
  async lookupHotels(q,...a){return active?clone(recording.hotels.filter(h=>q.toLowerCase().split(/\s+/).every(word=>(h.name+' '+h.resort).toLowerCase().includes(word)))):base.lookupHotels(q,...a);},
  async savedHotels(ids,...a){return active?clone(recording.hotels.filter(h=>ids.includes(h.id))):base.savedHotels(ids,...a);},
  async restoreHotel(id,...a){if(!active)return base.restoreHotel(id,...a);const h=recording.hotels.find(h=>h.id===id);if(!h)fail('Отеля нет в записи');return clone(h);},
  async quote(o){if(!active)return base.quote(o);const entry=recording.entries.get(o.key);if(!entry)fail('Тура нет в записи');return clone(entry.quote);},
  async flights(t){if(!active)return base.flights(t);const entry=[...recording.entries.values()].find(e=>e.quote.id===t.id);if(!entry)fail('Рейсов нет в записи');return clone(entry.flights);}
 };
 window.AnyTourPrototypeData=Object.freeze(api);
 window.AnyTourRecording=Object.freeze({validate,load(raw){const next=validate(raw);base.stop();recording=next;return {hotels:next.hotels.length,offers:next.entries.size};}});
 document.addEventListener('DOMContentLoaded',()=>{
  const input=document.getElementById('recording-file'),status=document.getElementById('recording-status');
  input.addEventListener('change',async()=>{
   const file=input.files?.[0];if(!file)return;status.textContent='Проверяем запись…';
   try{if(file.size>5*1024*1024)fail('Запись должна быть не больше 5 МБ');const counts=window.AnyTourRecording.load(JSON.parse(await file.text()));const select=document.getElementById('fixture-scenario');if(!select.querySelector('[value="recorded"]'))select.add(new Option('Загруженная запись · без отправки','recorded'));select.value='recorded';select.dispatchEvent(new Event('change',{bubbles:true}));status.textContent=`Загружено ${counts.hotels} отелей и ${counts.offers} туров. Файл остаётся только в этой вкладке.`;}
   catch(error){status.textContent='Запись не загружена: '+(error instanceof SyntaxError?'файл не содержит корректный JSON.':error.message);}
   input.value='';
  });
 });
})();
