(function(){'use strict';
function digits(v){return String(v||'').replace(/\D+/g,'');}
function validatePhone(input){if(!input)return true;const count=digits(input.value).length;if(!input.value.trim()){input.setCustomValidity('');return false;}if(count<10||count>15){input.setCustomValidity('Укажите корректный телефон: от 10 до 15 цифр.');return false;}input.setCustomValidity('');return true;}
function leadHeadingHint(root){const scope=root||document,form=scope.querySelector&&scope.querySelector('.lead-form');return form&&form.querySelector('.section-heading span');}
function syncFlightContext(hasFlight,root){const hint=leadHeadingHint(root||document.getElementById('selectedTour'));if(!hint)return;hint.textContent=hasFlight?'Менеджер получит выбранный тур и выбранный рейс.':'Менеджер получит выбранный тур. Рейс можно выбрать выше.';}
function decorate(root){const scope=root||document,form=scope.querySelector&&scope.querySelector('.lead-form');if(!form||form.dataset.v2LeadGuard==='1')return;const phone=form.querySelector('input[name="phone"]');if(!phone)return;form.dataset.v2LeadGuard='1';phone.setAttribute('inputmode','tel');phone.setAttribute('aria-describedby','v2-phone-hint');let hint=form.querySelector('#v2-phone-hint');if(!hint){hint=document.createElement('small');hint.id='v2-phone-hint';hint.className='lead-phone-hint';hint.textContent='Введите номер телефона с кодом страны или города.';phone.insertAdjacentElement('afterend',hint);}const check=()=>validatePhone(phone);phone.addEventListener('input',check);phone.addEventListener('blur',check);form.addEventListener('reset',()=>{phone.setCustomValidity('');});}
window.addEventListener('v2:tour-selected',()=>{const root=document.getElementById('selectedTour');decorate(root);syncFlightContext(false,root);});
window.addEventListener('v2:flight-selected',()=>syncFlightContext(true,document.getElementById('selectedTour')));
document.addEventListener('DOMContentLoaded',()=>decorate(document.getElementById('selectedTour')),{once:true});
window.V2LeadFormGuard={digits,validatePhone,decorate,syncFlightContext,version:1};
})();
