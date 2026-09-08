/* Native selected-tour handoff: the canonical controller's lead form stays visible. */
(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function ensure(){const r=root(),flights=r&&r.querySelector('.tour-flights'),form=r&&r.querySelector('.lead-form');if(!flights||!form)return null;let action=flights.querySelector('.search3-flight-continue');if(!action){action=document.createElement('div');action.className='search3-flight-continue';action.innerHTML='<button type="button" class="primary">Оставить заявку</button>';flights.appendChild(action)}action.hidden=false;const button=action.querySelector('button');if(button)button.textContent='Оставить заявку';return button}
function enterLead(source){const r=root(),form=r&&r.querySelector('.lead-form');if(!r||!form)return false;r.classList.remove('search3-final-review');r.classList.add('search3-lead-entry');window.dispatchEvent(new CustomEvent('search3:lead-entry',{detail:{active:true,source:source||'native'}}));form.scrollIntoView({behavior:'smooth',block:'start'});const phone=form.querySelector('input[name="phone"]');if(phone)phone.focus({preventScroll:true});return true}
window.addEventListener('v2:tour-selected',()=>{const r=root();if(r)r.classList.remove('search3-final-review','search3-lead-entry');setTimeout(ensure,0)});
window.addEventListener('v2:flight-selected',()=>setTimeout(ensure,0));
document.addEventListener('click',event=>{if(event.target&&event.target.closest&&event.target.closest('#selectedTour .search3-flight-continue button')){event.preventDefault();enterLead('flight')}});
window.Search3SummaryCta={ensure,enterLead,version:10};
})();
