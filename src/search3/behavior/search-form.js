/* Native Search3 entry and the remaining result-shell state. */
(function(){'use strict';
var form=document.getElementById('tourSearch'),results=document.getElementById('results'),edit=document.getElementById('resultsSearchEdit'),trip=document.getElementById('resultsTripContext'),collapse=form&&form.querySelector('.search-editor-collapse');if(!form||!results)return;
form.dataset.search3Ready='1';
const busy=value=>results.setAttribute('aria-busy',value),on=(name,handler)=>window.addEventListener(name,handler);
const extras=form.querySelector('.extras'),moreFilters=form.querySelector('.search-more-filters');
if(extras&&moreFilters){
 moreFilters.hidden=false;extras.querySelector('summary').hidden=true;form.dataset.search3Extras='enhanced';
 const sync=()=>moreFilters.setAttribute('aria-expanded',String(extras.open));
 const focusExtra=name=>{extras.open=true;sync();const native=form.elements[name],target=name==='hotel'?extras.querySelector('[data-v2-hotel-query]'):native;requestAnimationFrame(()=>target?.focus({preventScroll:true}))};
 moreFilters.addEventListener('click',()=>{extras.open=!extras.open;sync();if(extras.open){extras.scrollIntoView({block:'nearest'});extras.querySelector('select:not([hidden]),input:not([hidden])')?.focus({preventScroll:true})}});
 extras.addEventListener('toggle',sync);
 on('v2:search-error',event=>{const name=event.detail?.error?.field;if(event.detail?.phase==='validation'&&name&&extras.contains(form.elements[name]))focusExtra(name)});
 form.addEventListener('invalid',event=>{if(extras.contains(event.target))focusExtra(event.target.name)},true);
 sync();
}
/* Mobile choices project the existing minimum-category field; no second value owner. */
const category=form.elements.stars,categoryField=category&&category.closest('.search-preference--stars');
if(categoryField&&window.matchMedia){
 const mobile=window.matchMedia('(max-width:700px)'),choices=document.createElement('div');
 choices.className='search-category-choices';choices.hidden=true;choices.setAttribute('role','radiogroup');choices.setAttribute('aria-labelledby','searchCategoryLabel');
 const buttons=Array.from(category.options).map(option=>{
  const button=document.createElement('button');button.type='button';button.setAttribute('role','radio');button.dataset.category=option.value;
  button.textContent=option.value?option.value+'★'+(option.value==='5'?'':'+'):'Любая';button.setAttribute('aria-label',option.textContent.trim());
  button.addEventListener('click',()=>choose(button));choices.appendChild(button);return button;
 });
 function syncCategory(){buttons.forEach(button=>{const option=Array.from(category.options).find(item=>item.value===button.dataset.category),checked=category.value===button.dataset.category;button.disabled=category.disabled||!option||option.disabled;button.setAttribute('aria-checked',String(checked));button.tabIndex=checked&&!button.disabled?0:-1})}
 function choose(button){if(button.disabled)return;category.value=button.dataset.category;category.dispatchEvent(new Event('change',{bubbles:true}));syncCategory()}
 choices.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End'].includes(event.key))return;const available=buttons.filter(button=>!button.disabled),index=available.indexOf(event.target);if(index<0||!available.length)return;event.preventDefault();const next=event.key==='Home'?0:event.key==='End'?available.length-1:(index+(['ArrowLeft','ArrowUp'].includes(event.key)?-1:1)+available.length)%available.length;choose(available[next]);available[next].focus()});
 function categoryLayout(){const focus=document.activeElement,restore=mobile.matches?focus===category:choices.contains(focus);category.hidden=mobile.matches;choices.hidden=!mobile.matches;syncCategory();if(restore)(mobile.matches?buttons.find(button=>button.tabIndex===0):category)?.focus({preventScroll:true})}
 categoryField.appendChild(choices);category.addEventListener('change',syncCategory);form.addEventListener('reset',()=>queueMicrotask(syncCategory));
 ['v2:search-hydrated','v2:search-started','v2:search-resumed','v2:search-reset','pageshow'].forEach(name=>on(name,syncCategory));
 new MutationObserver(syncCategory).observe(category,{attributes:true,childList:true,subtree:true});mobile.addEventListener('change',categoryLayout);categoryLayout();
}
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
function setEditor(open,focus){form.dataset.search3View=open?'editor':'summary';if(trip)trip.hidden=open||!hasTrip;if(collapse){collapse.hidden=!open||!hasHotels();collapse.textContent='К результатам'}if(edit){edit.textContent=open?'Свернуть параметры':'Изменить поиск';edit.setAttribute('aria-controls','tourSearch');edit.setAttribute('aria-expanded',open)}if(open&&focus){form.scrollIntoView({block:'start'});form.elements.from.focus({preventScroll:true})}}
function hasHotels(){return!window.V2SearchLifecycle?.hotelDetail?.profileOnly&&!!results.querySelector('.hotel-card')}
on('v2:search-started',()=>{captureTrip();editing=false;form.querySelectorAll('details[open]').forEach(node=>node.open=false);if(hasHotels())setEditor(false);busy('true')});
on('v2:search-resumed',()=>{captureTrip();editing=false;setEditor(!hasHotels());busy('false')});
on('v2:search-reset',()=>{editing=true;setEditor(true);busy('true')});
on('v2:search-error',()=>{editing=true;setEditor(true);busy('false')});
on('v2:results-rendered',()=>{if(hasHotels()&&!editing)setEditor(false);else if(!hasHotels())setEditor(true);busy('false')});
document.addEventListener('click',event=>{if(event.target.closest('.search-editor-collapse')){setEditor(false);edit?.focus({preventScroll:true});return}const toggle=event.target.closest('#resultsSearchEdit');if(toggle||event.target.closest('.empty-edit-search')){const open=!toggle||form.dataset.search3View!=='editor';editing|=open;setEditor(open,open)}});
const lifecycle=window.V2SearchLifecycle;if(lifecycle){editing=!!lifecycle.dirty;if(Number(lifecycle.searchId)>0&&!editing)captureTrip()}
setEditor(editing||!hasHotels());
})();
