(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function ensure(){const r=root(),summary=r&&r.querySelector('.search3-booking-summary'),form=r&&r.querySelector('.lead-form');if(!summary||!form)return;let actions=summary.querySelector('.search3-summary-actions');if(!actions){actions=document.createElement('div');actions.className='search3-summary-actions';actions.innerHTML='<button type="button" class="search3-summary-submit">Отправить заявку</button><p>Перед отправкой проверьте выбранный тур, рейс и контактные данные.</p>';summary.appendChild(actions)}const active=r.classList.contains('search3-final-review');form.classList.toggle('search3-has-summary-submit',active);const sent=form.dataset.sent==='1';const sending=form.dataset.search3LeadState==='sending';const button=actions.querySelector('.search3-summary-submit');if(button){button.disabled=sent||sending;button.textContent=sent?'Заявка отправлена':sending?'Отправляем…':'Отправить заявку'}actions.hidden=!active;}
window.addEventListener('v2:booking-review',()=>setTimeout(ensure,0));
window.addEventListener('v2:lead-started',()=>setTimeout(ensure,0));
window.addEventListener('v2:lead-success',()=>setTimeout(ensure,0));
window.addEventListener('v2:lead-error',()=>setTimeout(ensure,0));
window.addEventListener('v2:tour-selected',()=>setTimeout(ensure,0));
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-summary-submit');if(!b)return;const r=root(),form=r&&r.querySelector('.lead-form');if(!form)return;form.scrollIntoView({behavior:'smooth',block:'center'});if(typeof form.requestSubmit==='function')form.requestSubmit();else{const submit=form.querySelector('button[type="submit"]');if(submit)submit.click()}});
})();
