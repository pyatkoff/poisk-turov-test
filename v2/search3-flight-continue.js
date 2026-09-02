(function(){'use strict';
function ensure(){const root=document.getElementById('selectedTour'),box=root&&root.querySelector('.tour-flights');if(!box)return;let action=box.querySelector('.search3-flight-continue');if(!action){action=document.createElement('div');action.className='search3-flight-continue';action.innerHTML='<button type="button" class="primary">Продолжить</button>';box.appendChild(action)}action.hidden=false;}
window.addEventListener('v2:flight-selected',()=>setTimeout(ensure,0));
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-flight-continue button');if(!b)return;const root=document.getElementById('selectedTour'),lead=root&&root.querySelector('.search3-lead-shell,.lead-form');if(lead)lead.scrollIntoView({behavior:'smooth',block:'start'});});
})();
