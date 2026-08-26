(function(){'use strict';
const form=document.getElementById('tourSearch');if(!form)return;
const details=form.querySelector('details.extras');if(!details)return;
const summary=details.querySelector(':scope > summary');if(!summary)return;
const defaults={arrival:'',region:'',subregion:'',hotel:'',operator:'',hotel_type:'',stars:'',rating:'',food:'',price_from:'',price_till:'',onlyDirect:false,onlyCharter:false};
const names=Object.keys(defaults);
function selectedCount(){let count=0;names.forEach(name=>{const el=form.elements[name];if(!el)return;if(el instanceof RadioNodeList){if(String(el.value||'')!==String(defaults[name]))count++;return;}if(el.type==='checkbox'){if(!!el.checked!==!!defaults[name])count++;return;}if(String(el.value||'')!==String(defaults[name]))count++;});const services=form.querySelectorAll('input[name="hotel_service[]"]:checked').length;if(services)count+=services;return count;}
function render(){const count=selectedCount(),label=count?'Выбрано фильтров: '+count:'Курорт, отель, питание и перелёт';summary.innerHTML='<strong>Все фильтры</strong><span>'+label+'</span>';summary.setAttribute('aria-label',count?'Все фильтры, выбрано '+count:'Все фильтры, ничего не выбрано');return count;}
function init(){details.open=false;render();form.addEventListener('change',render);form.addEventListener('input',e=>{if(e.target&&e.target.matches('input[name="price_from"],input[name="price_till"]'))render();});}
init();
window.V2SearchFiltersUX={selectedCount,render,version:1};
})();