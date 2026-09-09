/* Native selected-tour handoff: the canonical controller's lead form stays visible. */
(function(){'use strict';
function root(){return document.getElementById('selectedTour')}
function selectedState(open){document.body.classList.toggle('search3-selected-open',!!open)}
function ensure(){const r=root(),flights=r&&r.querySelector('.tour-flights'),form=r&&r.querySelector('.lead-form');if(!flights||!form)return null;let action=flights.querySelector('.search3-flight-continue');if(!action){action=document.createElement('div');action.className='search3-flight-continue';action.innerHTML='<button type="button" class="primary">Продолжить к заявке</button>';flights.appendChild(action)}if(action.hidden)action.hidden=false;const button=action.querySelector('button');if(button&&button.textContent!=='Продолжить к заявке')button.textContent='Продолжить к заявке';return button}
/* Keep original radio nodes/order; only large lists need a local disclosure. */
function ensureFlightChoices(){
  const r=root(),list=r&&r.querySelector('.flight-variants');if(!list)return;
  const variants=list.querySelectorAll('.flight-variant');if(variants.length<=6)return;
  let toggle=r.querySelector('.search3-flight-toggle');
  if(!toggle){
    list.id='search3-flight-choices';
    list.classList.add('search3-flight-choices');
    list.dataset.collapsed='true';
    toggle=document.createElement('button');toggle.type='button';toggle.className='secondary search3-flight-toggle';
    toggle.setAttribute('aria-controls',list.id);
    list.before(toggle);
    toggle.addEventListener('click',()=>{
      list.dataset.collapsed=list.dataset.collapsed==='true'?'false':'true';
      update();
      if(list.dataset.collapsed==='false'){
        const active=list.querySelector('input[name="v2flight"]:checked');
        if(active)active.focus();
      }
    });
  }
  function update(){const collapsed=list.dataset.collapsed==='true';toggle.setAttribute('aria-expanded',String(!collapsed));toggle.textContent=collapsed?'Показать все рейсы ('+variants.length+')':'Оставить выбранный рейс';}
  update();
}
function localizedPrice(node){const value=String(node?.textContent||'').replace(/[\s\u00a0\u202f]/g,'').replace(/[^0-9,.-]/g,''),split=Math.max(value.lastIndexOf(','),value.lastIndexOf('.'));return Number(split>=0&&value.length-split-1<=2?value.slice(0,split).replace(/[.,]/g,'')+'.'+value.slice(split+1).replace(/[.,]/g,''):value.replace(/[.,]/g,''))||0}
function correctTradeoffs(){const variants=[...document.querySelectorAll('#selectedTour .flight-variant')],prices=variants.map(v=>localizedPrice(v.querySelector('.flight-choice>b')));if(variants.length<2||prices.some(v=>!v))return;const minimum=Math.min(...prices);variants.forEach((variant,index)=>{const label=[...variant.querySelectorAll('.flight-choice-tradeoffs span')].find(node=>/минимальн/i.test(node.textContent));if(!label)return;const delta=prices[index]-minimum;label.textContent=delta?'+'+new Intl.NumberFormat('ru-RU').format(delta)+' ₽ к минимальной':'Самая низкая цена';label.classList.toggle('is-best-price',!delta)})}
function directText(node){return [...node?.childNodes||[]].filter(child=>child.nodeType===3).map(child=>String(child.nodeValue||'')).join(' ').replace(/\s+/g,' ').trim()}
function syncLeadFlight(){const r=root(),choice=r&&r.querySelector('.flight-variant.is-selected .flight-choice span'),summary=r&&r.querySelector('.lead-selection-summary');if(!choice||!summary)return;const route=directText(choice.querySelector('.flight-choice-summary')),label=directText(choice),row=[...summary.querySelectorAll('span')].find(node=>/Рейс/i.test(String(node.querySelector('small')?.textContent||''))),value=row&&row.querySelector('b');if(value)value.textContent=[label,route].filter(Boolean).join(' · ')}
function enterLead(source){const r=root(),form=r&&r.querySelector('.lead-form');if(!r||!form)return false;r.classList.remove('search3-final-review');r.classList.add('search3-lead-entry');window.dispatchEvent(new CustomEvent('search3:lead-entry',{detail:{active:true,source:source||'native'}}));form.scrollIntoView({behavior:'smooth',block:'start'});const phone=form.querySelector('input[name="phone"]');if(phone)phone.focus({preventScroll:true});return true}
const selected=root();let queued=false;if(selected&&typeof MutationObserver!=='undefined')new MutationObserver(()=>{if(queued)return;queued=true;requestAnimationFrame(()=>{queued=false;if(selected.querySelector('.load-flights'))ensure()})}).observe(selected,{childList:true,subtree:true});
window.addEventListener('v2:tour-selected',()=>{const r=root();selectedState(true);if(r)r.classList.remove('search3-final-review','search3-lead-entry');setTimeout(ensure,0)});
window.addEventListener('v2:flight-selected',()=>setTimeout(()=>{correctTradeoffs();ensure();ensureFlightChoices();syncLeadFlight()},0));
window.addEventListener('v2:selected-tour-opened',()=>selectedState(true));
['v2:tour-returned','v2:selected-tour-closed','v2:search-reset'].forEach(name=>window.addEventListener(name,()=>selectedState(false)));
window.addEventListener('click',event=>{if(event.target&&event.target.closest&&event.target.closest('#selectedTour .back-results,#selectedTour .lead-success-back'))selectedState(false)},true);
document.addEventListener('click',event=>{if(event.target&&event.target.closest&&event.target.closest('#selectedTour .search3-flight-continue button')){event.preventDefault();enterLead('flight')}});
window.Search3SummaryCta={ensure,enterLead,correctTradeoffs,syncLeadFlight,version:13};
})();
