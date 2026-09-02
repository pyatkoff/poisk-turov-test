(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function ensure(){const r=root(),box=r&&r.querySelector('.tour-flights');if(!box)return;let action=box.querySelector('.search3-flight-continue');if(!action){action=document.createElement('div');action.className='search3-flight-continue';action.innerHTML='<button type="button" class="primary">Продолжить</button>';box.appendChild(action)}action.hidden=false;action.querySelector('button').textContent=r.classList.contains('search3-final-review')?'Изменить рейсы':'Продолжить';}
function enterReview(){const r=root();if(!r)return;r.classList.add('search3-final-review');ensure();window.dispatchEvent(new CustomEvent('v2:booking-review',{detail:{}}));const target=r.querySelector('.search3-final-sections,.search3-lead-shell,.lead-form');if(target)target.scrollIntoView({behavior:'smooth',block:'start'});}
function exitReview(){const r=root();if(!r)return;r.classList.remove('search3-final-review');ensure();const flights=r.querySelector('.tour-flights');if(flights)flights.scrollIntoView({behavior:'smooth',block:'start'});}
window.addEventListener('v2:tour-selected',()=>{const r=root();if(r)r.classList.remove('search3-final-review')});
window.addEventListener('v2:flight-selected',()=>setTimeout(ensure,0));
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-flight-continue button');if(!b)return;e.preventDefault();const r=root();if(r&&r.classList.contains('search3-final-review'))exitReview();else enterReview();});
})();
