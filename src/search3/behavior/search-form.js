/* Native Search3 entry and the remaining result-shell state. */
(function(){'use strict';
var form=document.getElementById('tourSearch'),results=document.getElementById('results'),edit=document.getElementById('resultsSearchEdit'),trip=document.getElementById('resultsTripContext');if(!form||!results)return;
form.dataset.search3Ready='1';
const busy=value=>results.setAttribute('aria-busy',value),on=(name,handler)=>window.addEventListener(name,handler);
let editing=false,hasTrip=false;
function optionLabel(name,id){const field=form.elements[name],option=field&&Array.from(field.options||[]).find(item=>String(item.value)===String(id||''));return option&&option.value?String(option.textContent||'').trim():''}
function dateLabel(value){const m=/^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value||''));return m&&Number(m[2])>=1&&Number(m[2])<=12&&Number(m[3])>=1&&Number(m[3])<=31?m[3]+'.'+m[2]+'.'+m[1]:''}
function count(value,min,max){if(typeof value!=='number'&&(typeof value!=='string'||!value.trim()))return null;const n=Number(value);return Number.isInteger(n)&&n>=min&&n<=max?n:null}
function word(n,one,few,many){return n%100>=11&&n%100<=14?many:n%10===1?one:n%10>=2&&n%10<=4?few:many}
function captureTrip(){
if(!trip)return;const p=window.V2SearchLifecycle&&window.V2SearchLifecycle.snapshot||{};
const from=optionLabel('from',p.departureId),country=optionLabel('country',p.countryId),route=from&&country?from+' → '+country:country||from&&'Вылет: '+from||'',facts=[],start=dateLabel(p.dateFrom),end=dateLabel(p.dateTo),low=count(p.nightsFrom,1,28),high=count(p.nightsTo,1,28),adults=count(p.adults,1,6),children=Array.isArray(p.childs)&&p.childs.length<=3&&p.childs.every(age=>count(age,0,17)!==null)?p.childs.length:null;
if(start&&end)facts.push('Вылет '+start+(start===end?'':' — '+end));
if(low!==null&&high!==null&&high>=low)facts.push((low===high?low:low+'–'+high)+' '+word(high,'ночь','ночи','ночей'));
if(adults!==null)facts.push(adults+' '+word(adults,'взрослый','взрослых','взрослых'));
if(children)facts.push(children+' '+word(children,'ребёнок','ребёнка','детей'));
trip.querySelector('[data-search3-trip-route]').textContent=route;trip.querySelector('[data-search3-trip-details]').textContent=facts.join(' · ');hasTrip=!!(route||facts.length);
}
function setEditor(open,focus){form.dataset.search3View=open?'editor':'summary';if(trip)trip.hidden=open||!hasTrip;if(edit){edit.textContent=open?'Свернуть параметры':'Изменить поиск';edit.setAttribute('aria-controls','tourSearch');edit.setAttribute('aria-expanded',open)}if(open&&focus){form.scrollIntoView({block:'start'});form.elements.from.focus({preventScroll:true})}}
function hasHotels(){return!!results.querySelector('.hotel-card')}
on('v2:search-started',()=>{captureTrip();editing=false;form.querySelectorAll('details[open]').forEach(node=>node.open=false);if(hasHotels())setEditor(false);busy('true')});
on('v2:search-reset',()=>{editing=true;setEditor(true);busy('true')});
on('v2:search-error',()=>{editing=true;setEditor(true);busy('false')});
on('v2:results-rendered',()=>{if(hasHotels()&&!editing)setEditor(false);else if(!hasHotels())setEditor(true);busy('false')});
document.addEventListener('click',event=>{const toggle=event.target.closest('#resultsSearchEdit');if(toggle||event.target.closest('.empty-edit-search')){const open=!toggle||form.dataset.search3View!=='editor';editing|=open;setEditor(open,open)}});
const lifecycle=window.V2SearchLifecycle;if(lifecycle){editing=!!lifecycle.dirty;if(Number(lifecycle.searchId)>0&&!editing)captureTrip()}
setEditor(editing||!hasHotels());
})();
