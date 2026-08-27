(function(){'use strict';
if(window.V2TrustConversionV1)return;
function resultsTools(){return document.getElementById('resultsTools');}
function selected(){return document.getElementById('selectedTour');}
function ensureResultsTrust(){const tools=resultsTools();if(!tools||document.querySelector('.results-trust-strip'))return;const strip=document.createElement('div');strip.className='results-trust-strip';strip.setAttribute('aria-label','Что важно знать перед выбором тура');strip.innerHTML='<span><b>Цена из поиска</b><small>Актуальна на момент получения предложения</small></span><span><b>Рейс можно проверить</b><small>Перед заявкой покажем доступные варианты и стоимость</small></span><span><b>Без оплаты на этом шаге</b><small>Сначала выберите подходящий тур</small></span>';tools.insertAdjacentElement('afterend',strip);}
function ensureCheckoutTrust(root){const scope=root||selected();if(!scope||scope.querySelector('.checkout-trust-panel'))return;const form=scope.querySelector('.lead-form'),flights=scope.querySelector('.tour-flights');if(!form)return;const panel=document.createElement('section');panel.className='checkout-trust-panel';panel.setAttribute('aria-label','Что произойдёт после заявки');panel.innerHTML='<div><span class="checkout-trust-eyebrow">После заявки</span><strong>Вы ничего не оплачиваете прямо сейчас</strong><p>Менеджер получит выбранный тур и, если вы выбрали рейс, его параметры. После проверки деталей он свяжется с вами по указанному телефону.</p></div><div class="checkout-trust-points"><span><b>1</b>Проверим актуальность деталей</span><span><b>2</b>Уточним выбранный рейс и итоговую стоимость</span><span><b>3</b>Свяжемся с вами для подтверждения</span></div>';
if(flights)flights.insertAdjacentElement('afterend',panel);else form.insertAdjacentElement('beforebegin',panel);}
function refineSelectedCta(root){const scope=root||selected(),btn=scope&&scope.querySelector('.selected-lead-cta'),note=scope&&scope.querySelector('.selected-lead-cta-note');if(btn)btn.textContent='Запросить подтверждение тура';if(note)note.textContent='Без оплаты — менеджер проверит детали и свяжется с вами.';}
function decorateSelected(root){const scope=root||selected();if(!scope)return;ensureCheckoutTrust(scope);refineSelectedCta(scope);}
window.addEventListener('v2:tour-selected',()=>decorateSelected(selected()));
document.addEventListener('DOMContentLoaded',()=>{ensureResultsTrust();decorateSelected(selected());},{once:true});
window.V2TrustConversionV1={ensureResultsTrust,ensureCheckoutTrust,refineSelectedCta,decorateSelected,version:1};
})();
