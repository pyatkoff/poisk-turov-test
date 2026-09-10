(function(){'use strict';
const results=document.getElementById('results'),tools=document.getElementById('resultsTools');
if(!results||!tools)return;
const storageKey='anytour.search3.shortlist.v1',source='tourvisor',limit=3,fields=['schemaVersion','savedAt','source','hotelId','hotelName','country','region','searchId','offerId','date','nights','meal','room','placement','operator','adults','childs','observedPrice','currency'];
let saved=[],currentItems=[],root=null,message='',persistence=true,returnFocus=null;
function text(value){const api=window.V2Results;return api&&typeof api.textValue==='function'?api.textValue(value).replace(/\s+/g,' ').trim():String(value||'').replace(/\s+/g,' ').trim();}
function meal(tour){const api=window.V2Results;return text(api&&typeof api.mealLabel==='function'?api.mealLabel(tour):tour&&tour.meal);}
function searchId(){return String(window.V2Runtime&&window.V2Runtime.state&&window.V2Runtime.state.searchId||'');}
function identity(item){return [item.source,item.searchId,item.offerId].join(':');}
function cleanRecord(value){
  if(!value||typeof value!=='object'||Array.isArray(value)||Object.keys(value).some(key=>!fields.includes(key)))return null;
  const record={schemaVersion:Number(value.schemaVersion),savedAt:String(value.savedAt||''),source:String(value.source||''),hotelId:String(value.hotelId||''),hotelName:text(value.hotelName),country:text(value.country),region:text(value.region),searchId:String(value.searchId||''),offerId:String(value.offerId||''),date:text(value.date),nights:Number(value.nights),meal:text(value.meal),room:text(value.room),placement:text(value.placement),operator:text(value.operator),adults:Number(value.adults||0),childs:Number(value.childs||0),observedPrice:Number(value.observedPrice),currency:String(value.currency||'')};
  if(record.schemaVersion!==1||record.source!==source||!record.hotelId||!record.hotelName||!/^\d+$/.test(record.searchId)||Number(record.searchId)<=0||!record.offerId||!record.date||!Number.isFinite(record.nights)||record.nights<=0||!record.meal||!record.room||!Number.isFinite(record.observedPrice)||record.observedPrice<=0||record.currency!=='RUB'||!Number.isFinite(Date.parse(record.savedAt)))return null;
  return record;
}
function load(){
  try{
    const raw=window.localStorage.getItem(storageKey);
    if(!raw)return;
    const parsed=JSON.parse(raw);
    if(!Array.isArray(parsed)||parsed.length>limit)throw new Error('invalid shortlist');
    const records=parsed.map(cleanRecord);
    if(records.some(item=>!item)||new Set(records.map(identity)).size!==records.length)throw new Error('invalid shortlist');
    saved=records;
  }catch(error){
    saved=[];message='Не удалось прочитать сохранённое сравнение. Начните новый список.';
    try{window.localStorage.removeItem(storageKey);}catch(storageError){persistence=false;message='Сравнение не сохранится после закрытия страницы.';}
  }
}
function persist(){
  if(!persistence)return;
  try{if(saved.length)window.localStorage.setItem(storageKey,JSON.stringify(saved));else window.localStorage.removeItem(storageKey);}
  catch(error){persistence=false;message='Сравнение не сохранится после закрытия страницы.';}
}
function exactOffer(offerId,hotelId){
  const id=String(offerId||''),hotel=String(hotelId||''),matches=[];
  currentItems.forEach(item=>(Array.isArray(item&&item.tours)?item.tours:[]).forEach(tour=>{if(String(tour&&tour.id||'')===id)matches.push({hotel:item,tour});}));
  return matches.length===1&&(!hotel||String(matches[0].hotel&&matches[0].hotel.id)===hotel)?matches[0]:null;
}
function snapshot(offerId){
  const currentSearch=searchId(),match=exactOffer(offerId);
  if(!currentSearch||!/^\d+$/.test(currentSearch)||Number(currentSearch)<=0||!match)return null;
  const hotel=match.hotel,tour=match.tour,record=cleanRecord({schemaVersion:1,savedAt:new Date().toISOString(),source,hotelId:String(hotel.id),hotelName:hotel.name,country:hotel.country,region:hotel.region,searchId:currentSearch,offerId:String(tour.id),date:tour.date,nights:tour.nights,meal:meal(tour),room:tour.roomType,placement:tour.placement,operator:tour.operator,adults:tour.adults,childs:tour.childs,observedPrice:tour.price,currency:'RUB'});
  return record;
}
function available(item){return item.source===source&&item.searchId===searchId()&&!!exactOffer(item.offerId,item.hotelId);}
function ensure(){
  if(root)return root;
  root=document.createElement('section');root.className='search3-shortlist';root.hidden=true;root.setAttribute('aria-labelledby','search3ShortlistTitle');tools.insertAdjacentElement('afterend',root);return root;
}
function node(tag,className,value){const element=document.createElement(tag);if(className)element.className=className;if(value!==undefined)element.textContent=value;return element;}
function price(value){return new Intl.NumberFormat('ru-RU',{maximumFractionDigits:0}).format(value)+' ₽';}
function savedTime(value){try{return new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}).format(new Date(value));}catch(error){return value;}}
function render(){
  const panel=ensure();panel.replaceChildren();panel.hidden=!saved.length&&!message;
  if(panel.hidden){decorate();return;}
  const head=node('div','search3-shortlist__head'),copy=node('div','search3-shortlist__copy'),title=node('h2','',saved.length?'Сравнение предложений · '+saved.length+' из '+limit:'Сравнение предложений');title.id='search3ShortlistTitle';copy.append(title,node('p','',saved.length?'Сохранённая цена остаётся исторической — перед выбором предложение проверяется в текущей выдаче.':'Сохраните предложение из выдачи, чтобы сравнить точные условия.'));head.append(copy);
  if(saved.length){const clear=node('button','search3-shortlist-clear','Очистить список');clear.type='button';head.append(clear);}panel.append(head);
  if(saved.length){const list=node('div','search3-shortlist__items');saved.forEach(item=>{
    const article=node('article','search3-shortlist-item');article.dataset.source=item.source;article.dataset.hotelId=item.hotelId;article.dataset.offerId=item.offerId;article.dataset.searchId=item.searchId;
    const heading=node('h3','',item.hotelName),place=node('p','search3-shortlist-item__place',[item.country,item.region].filter(Boolean).join(' · ')),facts=node('dl','search3-shortlist-item__facts');
    [['Вылет',item.date],['Ночей',String(item.nights)],['Питание',item.meal],['Номер',item.room],['Размещение',item.placement||'Уточняется'],['Оператор',item.operator||'Уточняется']].forEach(pair=>{const wrap=node('div');wrap.append(node('dt','',pair[0]),node('dd','',pair[1]));facts.append(wrap);});
    const savedPrice=node('div','search3-shortlist-item__price');savedPrice.append(node('small','', 'Цена при сохранении'),node('strong','',price(item.observedPrice)),node('time','',savedTime(item.savedAt)));savedPrice.querySelector('time').dateTime=item.savedAt;
    const actions=node('div','search3-shortlist-item__actions'),select=node('button','search3-shortlist-select',available(item)?'Проверить предложение':'Нет в текущей выдаче'),remove=node('button','search3-shortlist-remove','Удалить');select.type='button';select.dataset.offerId=item.offerId;select.disabled=!available(item);remove.type='button';actions.append(select,remove);article.append(heading,place,facts,savedPrice,actions);list.append(article);
  });panel.append(list);}
  const status=node('p','search3-shortlist-status',message);status.setAttribute('aria-live','polite');status.setAttribute('role','status');panel.append(status);decorate();
}
function isSaved(record){const key=identity(record);return saved.some(item=>identity(item)===key);}
function decorate(){
  results.querySelectorAll('.search3-shortlist-toggle').forEach(button=>button.remove());
  results.querySelectorAll('.direct-tour[data-tid]').forEach(select=>{const record=snapshot(select.dataset.tid);if(!record)return;const button=node('button','search3-shortlist-toggle',isSaved(record)?'Сохранено для сравнения':'Сравнить');button.type='button';button.dataset.offerId=record.offerId;button.setAttribute('aria-pressed',isSaved(record)?'true':'false');select.insertAdjacentElement('afterend',button);});
}
function focusToggle(offerId){requestAnimationFrame(()=>{const button=Array.from(results.querySelectorAll('.search3-shortlist-toggle')).find(node=>node.dataset.offerId===String(offerId));if(button)button.focus();});}
function focusResults(){const temporary=!results.hasAttribute('tabindex');if(temporary)results.setAttribute('tabindex','-1');try{results.focus({preventScroll:true});}catch(error){results.focus();}if(temporary)results.addEventListener('blur',()=>results.removeAttribute('tabindex'),{once:true});}
function focusAfterRemoval(index,item){requestAnimationFrame(()=>{const buttons=root.querySelectorAll('.search3-shortlist-remove');if(buttons.length){buttons[Math.min(index,buttons.length-1)].focus();return;}const sourceButton=Array.from(results.querySelectorAll('.search3-shortlist-toggle')).find(button=>button.dataset.offerId===item.offerId);if(sourceButton)sourceButton.focus();else focusResults();});}
function toggle(button){const record=snapshot(button.dataset.offerId);if(!record){message='Это предложение больше не доступно в текущей выдаче.';render();return;}const index=saved.findIndex(item=>identity(item)===identity(record));if(index>=0){const removed=saved.splice(index,1)[0];message='';persist();render();focusAfterRemoval(index,removed);return;}if(saved.length>=limit){message='Можно сравнить не больше трёх предложений.';render();focusToggle(record.offerId);return;}saved.push(record);message=persistence?'Предложение добавлено. Цена зафиксирована на момент сохранения.':'Сравнение не сохранится после закрытия страницы.';persist();render();focusToggle(record.offerId);}
function selectSaved(button){const article=button.closest('.search3-shortlist-item'),item=saved.find(entry=>entry.offerId===String(article&&article.dataset.offerId||'')&&entry.searchId===String(article&&article.dataset.searchId||''));if(!item||!available(item)){message='Предложение уже не входит в текущую выдачу. Обновите поиск или удалите снимок.';render();return;}let target=Array.from(results.querySelectorAll('.hotel-card')).find(card=>card.dataset.hotelId===item.hotelId);target=target&&Array.from(target.querySelectorAll('.direct-tour')).find(node=>String(node.dataset.tid)===item.offerId);if(!target&&window.V2Results&&typeof window.V2Results.revealOfferAlternatives==='function')target=window.V2Results.revealOfferAlternatives(item.offerId);if(!target){message='Не удалось открыть предложение из текущей выдачи.';render();return;}returnFocus={offerId:item.offerId,searchId:item.searchId};target.click();}
function remove(button){const article=button.closest('.search3-shortlist-item'),index=saved.findIndex(item=>item.offerId===String(article&&article.dataset.offerId||'')&&item.searchId===String(article&&article.dataset.searchId||''));if(index<0)return;const removed=saved.splice(index,1)[0];message='';persist();render();focusAfterRemoval(index,removed);}
document.addEventListener('click',event=>{const target=event.target.closest&&event.target.closest('.search3-shortlist-toggle,.search3-shortlist-select,.search3-shortlist-remove,.search3-shortlist-clear');if(!target)return;if(target.classList.contains('search3-shortlist-toggle'))toggle(target);else if(target.classList.contains('search3-shortlist-select'))selectSaved(target);else if(target.classList.contains('search3-shortlist-remove'))remove(target);else{const previous=saved.slice();saved=[];message='';persist();render();requestAnimationFrame(()=>{const first=previous[0]&&Array.from(results.querySelectorAll('.search3-shortlist-toggle')).find(button=>button.dataset.offerId===previous[0].offerId);if(first)first.focus();else focusResults();});}});
document.addEventListener('click',event=>{if(event.target.closest&&event.target.closest('.tour-more-toggle'))requestAnimationFrame(decorate);},true);
window.addEventListener('search3:local-results-filtered',event=>{currentItems=event.detail&&Array.isArray(event.detail.items)?event.detail.items.slice():[];render();});
window.addEventListener('v2:results-rendered',decorate);
window.addEventListener('v2:search-reset',()=>{currentItems=[];render();});
window.addEventListener('v2:search-started',()=>{currentItems=[];render();});
window.addEventListener('v2:tour-returned',()=>{const focus=returnFocus;returnFocus=null;if(!focus)return;requestAnimationFrame(()=>{const button=Array.from(root.querySelectorAll('.search3-shortlist-select')).find(node=>node.dataset.offerId===focus.offerId&&node.closest('.search3-shortlist-item').dataset.searchId===focus.searchId);if(button&&!button.disabled)button.focus({preventScroll:true});});});
load();render();
window.Search3Shortlist={storageKey,items:()=>saved.map(item=>Object.assign({},item)),get persistent(){return persistence;},version:1};
})();
