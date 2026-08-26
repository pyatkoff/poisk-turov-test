(function(){'use strict';
if(window.V2PrimaryMealUXV1)return;
var form=document.getElementById('tourSearch');if(!form)return;
var select=form.elements.food,main=form.querySelector('.main-fields');if(!select||!main)return;
var field=select.closest('.field');if(!field)return;
field.classList.add('field-wide','main-meal','primary-step','primary-step-7');
var title=field.querySelector(':scope > span');if(title)title.textContent='Питание';
main.appendChild(field);
select.classList.add('meal-native-select');
var wrap=document.createElement('div');wrap.className='meal-quick';wrap.setAttribute('role','group');wrap.setAttribute('aria-label','Тип питания');field.appendChild(wrap);
function lower(v){return String(v||'').toLowerCase().replace('ё','е');}
function priority(label){var s=lower(label);if(s.indexOf('ультра')>=0||s.indexOf('uai')>=0)return 100;if(s.indexOf('все включ')>=0||s.indexOf('all inclusive')>=0)return 90;if(s.indexOf('завтрак')>=0||s.indexOf('breakfast')>=0)return 80;if(s.indexOf('без пит')>=0||s.indexOf('room only')>=0)return 70;return 10;}
function labelFor(label){var s=lower(label);if(s.indexOf('ультра')>=0||s.indexOf('uai')>=0)return'Ультра всё включено';if(s.indexOf('все включ')>=0||s.indexOf('all inclusive')>=0)return'Всё включено';if(s.indexOf('завтрак')>=0||s.indexOf('breakfast')>=0)return'Завтраки';if(s.indexOf('без пит')>=0||s.indexOf('room only')>=0)return'Без питания';return String(label||'').trim();}
function items(){var opts=Array.from(select.options||[]).filter(function(o){return String(o.value||'')!=='';});opts.sort(function(a,b){return priority(b.textContent)-priority(a.textContent);});var out=[],seen={};for(var i=0;i<opts.length&&out.length<4;i++){var label=labelFor(opts[i].textContent),key=lower(label);if(seen[key])continue;seen[key]=1;out.push({value:String(opts[i].value),label:label});}return out;}
function sync(){wrap.querySelectorAll('button').forEach(function(btn){var active=String(btn.dataset.value||'')===String(select.value||'');btn.classList.toggle('is-active',active);btn.setAttribute('aria-pressed',active?'true':'false');});}
function addButton(value,label){var btn=document.createElement('button');btn.type='button';btn.className='meal-choice';btn.dataset.value=value;btn.textContent=label;btn.addEventListener('click',function(){if(String(select.value||'')===value)return;select.value=value;sync();select.dispatchEvent(new Event('change',{bubbles:true}));});wrap.appendChild(btn);}
function render(){var current=String(select.value||'');wrap.innerHTML='';addButton('','Любое');items().forEach(function(item){addButton(item.value,item.label);});if(current&&Array.from(select.options||[]).some(function(o){return String(o.value)===current;}))select.value=current;sync();}
select.addEventListener('change',sync);
var timer=0,observer=new MutationObserver(function(){clearTimeout(timer);timer=setTimeout(render,0);});observer.observe(select,{childList:true});
var style=document.createElement('style');style.textContent='.main-meal{grid-column:1/-1!important}.meal-native-select{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}.meal-quick{display:flex;gap:7px;min-height:48px;overflow-x:auto;scrollbar-width:none;padding:1px 1px 3px}.meal-quick::-webkit-scrollbar{display:none}.meal-choice{flex:0 0 auto;min-height:46px;padding:0 15px;border:1px solid #d7dde8;border-radius:11px;background:#fff;color:#596579;font:800 13px/1 Inter,Arial,sans-serif;cursor:pointer}.meal-choice.is-active{border-color:#6079d8;background:#edf2ff;color:#2442ad}.meal-choice:focus-visible{outline:3px solid rgba(64,92,199,.18);outline-offset:1px}@media(max-width:560px){.meal-choice{min-height:44px;padding:0 13px;font-size:12px}}';document.head.appendChild(style);
render();window.V2PrimaryMealUXV1={render:render,version:1};
})();