(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function relayout(){if(window.Search3BookingSummary&&typeof window.Search3BookingSummary.syncLayout==='function')window.Search3BookingSummary.syncLayout()}
function syncLeadVisibility(r,form){const review=r&&r.classList.contains('search3-final-review'),entry=r&&r.classList.contains('search3-lead-entry');if(review&&!entry)form.style.setProperty('display','none','important');else{form.style.removeProperty('display');relayout();}}
function ensure(){const r=root(),summary=r&&r.querySelector('.search3-booking-summary'),form=r&&r.querySelector('.lead-form');if(!summary||!form)return;let actions=summary.querySelector('.search3-summary-actions');if(!actions){actions=document.createElement('div');actions.className='search3-summary-actions';actions.innerHTML='<button type="button" class="search3-summary-submit">Отправить заявку</button><p>Перед отправкой проверьте выбранный тур и рейс.</p>';summary.appendChild(actions)}const active=r.classList.contains('search3-final-review'),entry=r.classList.contains('search3-lead-entry');form.classList.toggle('search3-has-summary-submit',active&&!entry);syncLeadVisibility(r,form);const sent=form.dataset.sent==='1';const sending=form.dataset.search3LeadState==='sending';const button=actions.querySelector('.search3-summary-submit');if(button){button.disabled=sent||sending;button.textContent=sent?'Заявка отправлена':sending?'Отправляем…':'Отправить заявку'}actions.hidden=!active||entry;}
function enterLead(){const r=root(),form=r&&r.querySelector('.lead-form');if(!r||!form)return;r.classList.add('search3-lead-entry');ensure();window.dispatchEvent(new CustomEvent('search3:lead-entry',{detail:{active:true,source:'summary'}}));form.scrollIntoView({behavior:'smooth',block:'start'});const phone=form.querySelector('input[name="phone"]');if(phone){try{phone.focus({preventScroll:true});}catch(e){phone.focus();}}}
window.addEventListener('v2:booking-review',()=>setTimeout(ensure,0));
window.addEventListener('v2:lead-started',()=>setTimeout(ensure,0));
window.addEventListener('v2:lead-success',()=>setTimeout(ensure,0));
window.addEventListener('v2:lead-error',()=>setTimeout(ensure,0));
window.addEventListener('search3:lead-entry',()=>setTimeout(ensure,0));
window.addEventListener('v2:tour-selected',()=>{const r=root();if(r)r.classList.remove('search3-lead-entry');setTimeout(ensure,0)});
document.addEventListener('click',e=>{const b=e.target&&e.target.closest&&e.target.closest('#selectedTour .search3-summary-submit');if(!b)return;e.preventDefault();enterLead();});
window.Search3SummaryCta={ensure,enterLead,version:3};
})();
