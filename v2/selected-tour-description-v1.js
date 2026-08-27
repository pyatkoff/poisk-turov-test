(function(){'use strict';
function decorate(root){const scope=root||document.getElementById('selectedTour'),desc=scope&&scope.querySelector('.hotel-desc');if(!desc||desc.dataset.v2Disclosure==='1')return;const text=String(desc.textContent||'').trim();if(text.length<280)return;desc.dataset.v2Disclosure='1';desc.classList.add('is-collapsed');const btn=document.createElement('button');btn.type='button';btn.className='hotel-desc-toggle';btn.setAttribute('aria-expanded','false');btn.textContent='Подробнее об отеле';btn.addEventListener('click',()=>{const expanded=btn.getAttribute('aria-expanded')==='true';btn.setAttribute('aria-expanded',expanded?'false':'true');btn.textContent=expanded?'Подробнее об отеле':'Свернуть описание';desc.classList.toggle('is-collapsed',expanded);});desc.insertAdjacentElement('afterend',btn);}
window.addEventListener('v2:tour-selected',()=>decorate(document.getElementById('selectedTour')));
window.V2SelectedTourDescription={decorate,version:1};
})();
