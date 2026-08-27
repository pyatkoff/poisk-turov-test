(function(){'use strict';
if(window.V2CheckoutExperienceV1)return;
const factGroups={
  'Вылет из':'trip','Дата':'trip','Ночей':'stay','Туристы':'stay','Питание':'stay',
  'Номер':'room','Размещение':'room','Оператор':'provider','Перелёт':'flight','Топливный сбор':'price'
};
function factLabel(node){const el=node&&node.querySelector('span');return String(el&&el.textContent||'').trim();}
function decorateFacts(scope){const facts=scope&&scope.querySelector('.facts');if(!facts)return;facts.classList.add('checkout-facts');facts.setAttribute('role','list');[...facts.children].forEach(item=>{item.setAttribute('role','listitem');const group=factGroups[factLabel(item)]||'other';item.dataset.factGroup=group;});if(!facts.previousElementSibling||!facts.previousElementSibling.classList.contains('checkout-facts-heading')){const heading=document.createElement('div');heading.className='checkout-facts-heading';heading.innerHTML='<strong>Параметры тура</strong><span>Проверьте основные условия перед выбором перелёта</span>';facts.insertAdjacentElement('beforebegin',heading);}}
function decorateJourney(scope){if(scope.querySelector('.checkout-journey'))return;const back=scope.querySelector('.back-results');if(!back)return;const journey=document.createElement('div');journey.className='checkout-journey';journey.setAttribute('aria-label','Этапы оформления');journey.innerHTML='<span><b>1</b>Проверьте тур</span><span><b>2</b>Выберите перелёт</span><span><b>3</b>Оставьте контакты</span>';back.insertAdjacentElement('afterend',journey);}
function setStage(stage,complete){const scope=document.getElementById('selectedTour'),steps=scope?[...scope.querySelectorAll('.checkout-journey span')]:[];steps.forEach((step,index)=>{const n=index+1;step.classList.toggle('is-active',n===stage&&!complete);step.classList.toggle('is-complete',n<stage||(complete&&n<=stage));step.setAttribute('aria-current',n===stage&&!complete?'step':'false');});}
function decorateLead(scope){const form=scope&&scope.querySelector('.lead-form');if(!form)return;form.classList.add('checkout-lead');if(!form.querySelector('.checkout-help')){const note=document.createElement('div');note.className='checkout-help';note.textContent='Выбранный тур, рейс и актуальная стоимость передаются менеджеру вместе с заявкой.';const submit=form.querySelector('button[type="submit"]');if(submit)submit.insertAdjacentElement('beforebegin',note);else form.appendChild(note);}}
function decorate(scope){scope=scope||document.getElementById('selectedTour');if(!scope)return;decorateJourney(scope);const head=scope.querySelector('.selected-head');if(head)head.classList.add('checkout-head');const picture=scope.querySelector('.selected-picture');if(picture)picture.classList.add('checkout-picture');const desc=scope.querySelector('.hotel-desc');if(desc)desc.classList.add('checkout-description');decorateFacts(scope);const flights=scope.querySelector('.tour-flights');if(flights){flights.classList.add('checkout-flights');flights.setAttribute('aria-label','Выбор перелёта');}decorateLead(scope);setStage(1,false);}
window.addEventListener('v2:tour-selected',()=>decorate(document.getElementById('selectedTour')));
window.addEventListener('v2:flight-selected',()=>setStage(2,false));
window.addEventListener('v2:lead-started',()=>setStage(3,false));
window.addEventListener('v2:lead-error',()=>setStage(3,false));
window.addEventListener('v2:lead-success',()=>setStage(3,true));
window.V2CheckoutExperienceV1={decorate,decorateFacts,decorateJourney,decorateLead,setStage,version:1};
})();
