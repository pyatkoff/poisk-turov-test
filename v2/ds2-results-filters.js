(function(){'use strict';
if(window.DS2ResultsFilters)return;
const rail=document.querySelector('.results-filter-rail');
const results=document.getElementById('results');
const summary=document.getElementById('resultSummary');
const mq=window.matchMedia?window.matchMedia('(min-width:1000px)'):{matches:true};
if(!rail||!results)return;
let sourceItems=[];
let applying=false;
let state={priceMax:0,stars:0,rating:0,meal:''};
function renderer(){return window.V2Results||null;}
function price(h){const p=Number(h&&h.price||0);if(p>0)return p;const tours=Array.isArray(h&&h.tours)?h.tours:[];const values=tours.map(t=>Number(t&&t.price||0)).filter(v=>v>0);return values.length?Math.min.apply(null,values):0;}
function mealText(t){return [t&&t.meal&&t.meal.russianName,t&&t.meal&&t.meal.fullRussianName,t&&t.meal&&t.meal.name].filter(Boolean).join(' ').toLowerCase();}
function mealMatches(h,key){if(!key)return true;const tours=Array.isArray(h&&h.tours)?h.tours:[];return tours.some(t=>{const m=mealText(t);if(key==='ai')return /вс[её]\s+включено|all\s+inclusive|(^|\s)ai($|\s)/i.test(m);if(key==='hb')return /полупансион|half\s*board|(^|\s)hb($|\s)/i.test(m);return true;});}
function matches(h){if(state.priceMax&&price(h)&&price(h)>state.priceMax)return false;if(state.stars&&Number(h&&h.category||0)<state.stars)return false;if(state.rating&&Number(h&&h.rating||0)<state.rating)return false;if(!mealMatches(h,state.meal))return false;return true;}
function money(v){return new Intl.NumberFormat('ru-RU').format(Number(v||0));}
function updateSummary(count){if(!summary)return;summary.textContent=count===sourceItems.length?'Показано отелей: '+count:'Подходит '+count+' из '+sourceItems.length+' отелей';}
function apply(){if(!mq.matches||!sourceItems.length)return;const r=renderer();if(!r||typeof r.render!=='function')return;const filtered=sourceItems.filter(matches);applying=true;try{r.render(filtered);}finally{applying=false;}updateSummary(filtered.length);const count=rail.querySelector('[data-ds2-filter-count]');if(count)count.textContent=String(filtered.length);}
function reset(){state={priceMax:0,stars:0,rating:0,meal:''};const range=rail.querySelector('[data-ds2-price]');if(range){range.value=range.max;state.priceMax=Number(range.max||0);}rail.querySelectorAll('input[type=radio]').forEach(i=>{i.checked=i.value==='0'||i.value==='';});syncPrice();apply();}
function syncPrice(){const range=rail.querySelector('[data-ds2-price]');const out=rail.querySelector('[data-ds2-price-label]');if(!range||!out)return;state.priceMax=Number(range.value||0);out.textContent='до '+money(state.priceMax)+' ₽';}
function build(){rail.innerHTML='<div class="filter-rail-title">Фильтры</div><label class="filter-range"><span>Бюджет на тур</span><input type="range" data-ds2-price min="40000" max="250000" step="5000" value="250000"><small data-ds2-price-label>до 250 000 ₽</small></label><fieldset><legend>Питание</legend><label><input type="radio" name="ds2-meal" value="" checked> Любое</label><label><input type="radio" name="ds2-meal" value="ai"> Всё включено</label><label><input type="radio" name="ds2-meal" value="hb"> Полупансион</label></fieldset><fieldset><legend>Категория отеля</legend><label><input type="radio" name="ds2-stars" value="0" checked> Любая</label><label><input type="radio" name="ds2-stars" value="5"> 5★</label><label><input type="radio" name="ds2-stars" value="4"> 4★ и выше</label><label><input type="radio" name="ds2-stars" value="3"> 3★ и выше</label></fieldset><fieldset><legend>Рейтинг</legend><label><input type="radio" name="ds2-rating" value="0" checked> Любой</label><label><input type="radio" name="ds2-rating" value="4.5"> 4.5+</label><label><input type="radio" name="ds2-rating" value="4"> 4.0+</label></fieldset><div class="filter-rail-actions"><button type="button" data-ds2-reset>Сбросить</button><span>Отелей: <b data-ds2-filter-count>0</b></span></div>';
rail.addEventListener('input',e=>{const t=e.target;if(t.matches('[data-ds2-price]')){syncPrice();apply();}});
rail.addEventListener('change',e=>{const t=e.target;if(t.name==='ds2-meal')state.meal=t.value;if(t.name==='ds2-stars')state.stars=Number(t.value||0);if(t.name==='ds2-rating')state.rating=Number(t.value||0);apply();});
rail.addEventListener('click',e=>{if(e.target.closest('[data-ds2-reset]'))reset();});
}
build();
window.addEventListener('v2:results-rendered',e=>{if(applying)return;const items=e&&e.detail&&Array.isArray(e.detail.items)?e.detail.items:[];sourceItems=items.slice();if(!sourceItems.length)return;const prices=sourceItems.map(price).filter(v=>v>0);const range=rail.querySelector('[data-ds2-price]');if(range&&prices.length){const max=Math.ceil(Math.max.apply(null,prices)/5000)*5000;const min=Math.floor(Math.min.apply(null,prices)/5000)*5000;range.min=String(Math.max(0,min));range.max=String(Math.max(min+5000,max));range.value=range.max;state.priceMax=Number(range.max);syncPrice();}updateSummary(sourceItems.length);const count=rail.querySelector('[data-ds2-filter-count]');if(count)count.textContent=String(sourceItems.length);});
window.addEventListener('v2:search-reset',()=>{sourceItems=[];reset();});
window.DS2ResultsFilters={apply,reset,get state(){return Object.assign({},state);},version:1};
})();