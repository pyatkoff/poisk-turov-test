(function(){'use strict';
const cfg=window.V2_CONFIG||{};
function qs(sel,root){return (root||document).querySelector(sel)}
function safeUrl(v){const s=String(v||'').trim();return /^https:\/\//i.test(s)?s:''}
function messengerLinks(){return{max:safeUrl(cfg.maxUrl||cfg.maxBotUrl||cfg.maxLink),telegram:safeUrl(cfg.telegramUrl||cfg.telegramBotUrl||cfg.telegramLink)}}
function ensureStatus(form){let box=form.querySelector('.search3-lead-status');if(!box){box=document.createElement('div');box.className='search3-lead-status';box.hidden=true;form.prepend(box)}return box}
function setState(state,detail){const form=qs('#selectedTour .lead-form');if(!form)return;form.dataset.search3LeadState=state;const box=ensureStatus(form);box.hidden=false;const links=messengerLinks();if(state==='sending'){
box.innerHTML='<div class="search3-lead-status__icon search3-lead-status__icon--sending">✈</div><div><h3>Отправляем заявку…</h3><p>Пожалуйста, подождите. Это займёт несколько секунд.</p><ol><li>Сохраняем ваши данные</li><li>Отправляем заявку менеджеру</li><li>Подтверждаем получение</li></ol></div>';
}else if(state==='success'){
const leadId=detail&&detail.leadId?'<p class="search3-lead-id">Заявка № '+String(detail.leadId)+'</p>':'';
const max=links.max?'<a class="search3-msg-btn search3-msg-btn--max" href="'+links.max+'" target="_blank" rel="noopener noreferrer">Продолжить в MAX</a>':'<span class="search3-msg-btn search3-msg-btn--disabled" aria-disabled="true">Продолжить в MAX</span>';
const tg=links.telegram?'<a class="search3-msg-btn search3-msg-btn--tg" href="'+links.telegram+'" target="_blank" rel="noopener noreferrer">Продолжить в Telegram</a>':'<span class="search3-msg-btn search3-msg-btn--disabled" aria-disabled="true">Продолжить в Telegram</span>';
box.innerHTML='<div class="search3-lead-status__icon search3-lead-status__icon--success">✓</div><div><h3>Заявка отправлена!</h3><p>Менеджер уже получил информацию о поездке и свяжется с вами в ближайшее время.</p>'+leadId+'<strong>Хотите продолжить общение прямо сейчас?</strong><div class="search3-messenger-actions">'+max+tg+'<button type="button" class="search3-stay-site">Остаться на сайте</button></div></div>';
}else if(state==='error'){
box.innerHTML='<div class="search3-lead-status__icon search3-lead-status__icon--error">!</div><div><h3>Не удалось отправить заявку</h3><p>Проверьте данные и попробуйте ещё раз.</p><div class="search3-error-actions"><button type="button" class="search3-retry-lead">Повторить отправку</button><button type="button" class="search3-edit-lead">Изменить данные</button></div></div>';
}
}
function clearState(){const form=qs('#selectedTour .lead-form');if(!form)return;delete form.dataset.search3LeadState;const box=form.querySelector('.search3-lead-status');if(box)box.hidden=true}
window.addEventListener('v2:lead-started',e=>setState('sending',e.detail||{}));
window.addEventListener('v2:lead-success',e=>setState('success',e.detail||{}));
window.addEventListener('v2:lead-error',e=>setState('error',e.detail||{}));
document.addEventListener('click',e=>{const form=qs('#selectedTour .lead-form');if(!form)return;if(e.target.closest('.search3-stay-site')){clearState();form.scrollIntoView({behavior:'smooth',block:'start'})}if(e.target.closest('.search3-edit-lead')){clearState();const first=form.querySelector('input,textarea,select');if(first)first.focus()}if(e.target.closest('.search3-retry-lead')){clearState();const btn=form.querySelector('button[type="submit"]');if(btn)btn.click()}});
})();
