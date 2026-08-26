(function(){'use strict';
const form=document.getElementById('tourSearch');
if(!form||typeof document.createElement!=='function')return;
const mq=window.matchMedia?window.matchMedia('(max-width: 560px)'):{matches:false};
let collapsed=false;
const summary=document.createElement('div');
summary.className='mobile-search-summary';
summary.hidden=true;
summary.innerHTML='<button type="button" class="mobile-search-summary-main" aria-label="Изменить параметры поиска"><span class="mobile-search-summary-route"></span><small class="mobile-search-summary-meta"></small></button><button type="button" class="mobile-search-summary-edit">Изменить</button>';
form.parentNode.insertBefore(summary,form.nextSibling);
function optionText(el){if(!el)return'';if(el.tagName==='SELECT'){const o=el.options&&el.options[el.selectedIndex];return o?String(o.textContent||'').trim():'';}return String(el.value||'').trim();}
function shortDate(v){if(!v)return'';const p=String(v).split('-').map(Number);if(p.length!==3)return String(v);try{return new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'short'}).format(new Date(p[0],p[1]-1,p[2])).replace('.','');}catch(e){return String(v);}}
function guestText(){const adults=Math.max(1,Number(form.elements.count_people&&form.elements.count_people.value||1));const children=Math.max(0,Number(form.elements.child_count&&form.elements.child_count.value||0));return adults+' '+(adults===1?'взрослый':'взрослых')+(children?' · '+children+' '+(children===1?'ребёнок':children<5?'ребёнка':'детей'):'');}
function refresh(){const from=optionText(form.elements.from)||'Вылет',country=optionText(form.elements.country)||'Направление';summary.querySelector('.mobile-search-summary-route').textContent=from+' → '+country;const bits=[];const df=form.elements.dateFrom&&form.elements.dateFrom.value,dt=form.elements.dateTo&&form.elements.dateTo.value;if(df||dt)bits.push((df?shortDate(df):'')+(df&&dt?' — ':'')+(dt?shortDate(dt):''));const nf=form.elements.daysFrom&&form.elements.daysFrom.value,nt=form.elements.daysTill&&form.elements.daysTill.value;if(nf||nt)bits.push(nf===nt?nf+' ночей':nf+'–'+nt+' ночей');bits.push(guestText());const stars=form.elements.stars&&form.elements.stars.value;if(stars)bits.push(stars==='5'?'5★':'от '+stars+'★');const food=optionText(form.elements.food);if(form.elements.food&&form.elements.food.value&&food)bits.push(food);summary.querySelector('.mobile-search-summary-meta').textContent=bits.filter(Boolean).join(' · ');}
function collapse(){if(!mq.matches)return;refresh();collapsed=true;form.classList.add('mobile-search-collapsed');summary.hidden=false;const details=form.querySelector('details.extras');if(details)details.open=false;setTimeout(()=>{const status=document.getElementById('status');if(status&&typeof status.scrollIntoView==='function')status.scrollIntoView({block:'start',behavior:'smooth'});},80);}
function expand(){collapsed=false;form.classList.remove('mobile-search-collapsed');summary.hidden=true;setTimeout(()=>{if(typeof form.scrollIntoView==='function')form.scrollIntoView({block:'start',behavior:'smooth'});},20);}
summary.addEventListener('click',expand);
form.addEventListener('input',()=>{if(collapsed)refresh();});
form.addEventListener('change',()=>{if(collapsed)refresh();});
window.addEventListener('v2:search-started',collapse);
window.addEventListener('v2:search-dirty',expand);
window.addEventListener('v2:search-error',e=>{if(e.detail&&e.detail.phase==='validation')expand();});
if(mq&&typeof mq.addEventListener==='function')mq.addEventListener('change',e=>{if(!e.matches)expand();});
const style=document.createElement('style');
style.id='v2-mobile-search-summary-style';
style.textContent='@media(max-width:560px){.mobile-search-summary{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:center;margin:8px 0 10px;padding:9px 10px;background:#fff;border:1px solid #dfe5ef;border-radius:14px;box-shadow:0 7px 20px rgba(38,53,89,.07);position:sticky;top:max(4px,env(safe-area-inset-top));z-index:7}.mobile-search-summary[hidden]{display:none!important}.mobile-search-summary-main{min-width:0;border:0;background:transparent;padding:0;text-align:left;cursor:pointer}.mobile-search-summary-route{display:block;color:#273248;font-size:14px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.mobile-search-summary-meta{display:block;margin-top:3px;color:#7a8598;font-size:10px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.mobile-search-summary-edit{border:1px solid #cfd8ee;background:#f6f8ff;color:#3154cf;border-radius:10px;min-height:42px;padding:0 11px;font-size:12px;font-weight:900;cursor:pointer}.mobile-search-collapsed{display:none!important}.mobile-search-collapsed+.mobile-search-summary{margin-top:0}}';
document.head&&document.head.appendChild(style);
})();