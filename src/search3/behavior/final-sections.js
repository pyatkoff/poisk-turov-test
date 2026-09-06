

/* donor:search3-final-sections.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
const classPrefix='search3-final-';
let tour=null,flight=null;
const {esc,text}=window.Search3PresentationText;
function number(v){if(v&&typeof v==='object'&&v.value!==undefined)v=v.value;const n=Number(v||0);return Number.isFinite(n)?n:0;}
function money(v){const n=number(v);return n>0?new Intl.NumberFormat('ru-RU').format(n)+' ₽':'—';}
function baggage(v){return window.Search3FlightPresentation.baggage(v);}
function people(t){const a=Number(t&&t.adults||0),c=Number(t&&t.childs||0),format=window.Search3CandidateResultsV1;if(format&&typeof format.partyLabel==='function')return format.partyLabel(a,c);const p=[];if(a)p.push(a+' взрослых');if(c)p.push(c+' '+(c===1?'ребёнок':c>=2&&c<=4?'ребёнка':'детей'));return p.join(' · ')||'—';}
function serviceRow(label,value,detail){return '<article><span>'+label+'</span><strong>'+value+'</strong>'+(detail||'')+'</article>';}
function render(){const root=document.getElementById('selectedTour');if(!root||!tour)return;let box=root.querySelector('.'+classPrefix+'sections');if(box)box.remove();box=document.createElement('div');box.className=classPrefix+'sections';const meal=text(tour.meal)||'—',room=text(tour.roomType)||'—',placement=text(tour.placement)||'—',operator=text(tour.operator)||'—',fuel=number(tour.fuelCharge),flightFuel=number(flight&&flight.fuelCharge),bag=baggage(flight);const service=[];service.push(serviceRow('Питание',esc(meal)));service.push(serviceRow('Номер',esc(room),'<small>'+esc(placement)+'</small>'));if(fuel>0)service.push(serviceRow('Топливный сбор тура',money(fuel)));if(flightFuel>0)service.push(serviceRow('Топливный сбор рейса',money(flightFuel)));if(bag)service.push(serviceRow('Багаж',esc(bag)));service.push(serviceRow('Туроператор',esc(operator)));box.innerHTML=('<section class="'+classPrefix+'section"><div class="'+classPrefix+'section__heading"><strong>Услуги и условия</strong><span>Только данные выбранного тура</span></div><div class="'+classPrefix+'services">')+service.join('')+('</div></section><section class="'+classPrefix+'section"><div class="'+classPrefix+'section__heading"><strong>Туристы</strong><span>Состав размещения у туроператора</span></div><div class="'+classPrefix+'tourists"><span>Для выбранного варианта</span><strong>')+esc(people(tour))+'</strong></div></section>';const lead=root.querySelector('.search3-lead-shell,.lead-form');if(lead&&lead.parentNode)lead.parentNode.insertBefore(box,lead);else root.appendChild(box);}
window.addEventListener('v2:tour-selected',e=>{tour=e.detail&&e.detail.tour||null;flight=null;setTimeout(render,0)});
window.addEventListener('v2:flight-selected',e=>{flight=e.detail&&e.detail.flight||null;setTimeout(render,0)});
})();
