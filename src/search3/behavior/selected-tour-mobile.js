

/* donor:search3-selected-tour-mobile.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
var selected=document.getElementById('selectedTour');if(!selected)return;
var bar=document.createElement('div');bar.className='search3-selected-mobile-bar';bar.hidden=true;bar.innerHTML='<div class="search3-selected-mobile-bar__price"><small>Стоимость тура</small><strong data-s3-selected-price>—</strong></div><button type="button" data-s3-selected-lead>Далее: итог тура</button>';
document.body.appendChild(bar);
var syncQueued=false,selectedTotal=0;
function actionButton(){return bar.querySelector('[data-s3-selected-lead]')}
function normalizedTotal(detail){var d=detail||{},tour=d.tour||{};if(d.pricePending)return Number(d.basePrice||tour.price||0);var value=Number(d.price||0);return value>0?value:Number(d.basePrice||tour.price||0);}
function money(value){var amount=Number(value||0);return amount>0?new Intl.NumberFormat('ru-RU').format(amount)+' ₽':'';}
function selectedAmount(source){if(selectedTotal>0)return money(selectedTotal);if(!source)return'';return Array.from(source.childNodes||[]).filter(function(node){return node.nodeType===Node.TEXT_NODE;}).map(function(node){return String(node.textContent||'').trim();}).filter(Boolean).join(' ').replace(/\s+/g,' ').trim();}
function normalizeLeadFields(){if(!window.matchMedia||!window.matchMedia('(max-width:640px)').matches||!selected.classList.contains('search3-lead-entry'))return;var form=selected.querySelector('.lead-form'),fields=form&&form.querySelector('.lead-fields'),name=form&&form.querySelector('input[name="name"]'),phone=form&&form.querySelector('input[name="phone"]');if(!form||!fields||!name||!phone||form.dataset.search3MobileLeadNormalized==='1')return;form.dataset.search3MobileLeadNormalized='1';var nameLabel=name.closest('label'),phoneLabel=phone.closest('label');if(nameLabel){nameLabel.hidden=false;nameLabel.removeAttribute('hidden');nameLabel.style.setProperty('display','grid','important');name.hidden=false;name.removeAttribute('hidden');if(phoneLabel&&nameLabel.nextElementSibling!==phoneLabel)fields.insertBefore(nameLabel,phoneLabel);else if(!phoneLabel&&fields.firstElementChild!==nameLabel)fields.prepend(nameLabel)}if(phoneLabel){phoneLabel.hidden=false;phoneLabel.removeAttribute('hidden');phoneLabel.style.setProperty('display','grid','important')}Array.from(form.querySelectorAll('button,summary')).forEach(function(node){var text=String(node.textContent||'').replace(/\s+/g,' ').trim();if(/^Дополнить заявку/i.test(text)){node.hidden=true;if(node.style.display!=='none')node.style.setProperty('display','none','important')}})}
function sync(){var visible=!selected.hidden&&getComputedStyle(selected).display!=='none'&&selected.children.length>0;document.body.classList.toggle('search3-selected-open',visible);var leadEntry=selected.classList.contains('search3-lead-entry'),finalReview=selected.classList.contains('search3-final-review');bar.hidden=!visible||leadEntry||finalReview;if(!visible)return;if(leadEntry)normalizeLeadFields();var source=selected.querySelector('.selected-price'),label=bar.querySelector('.search3-selected-mobile-bar__price small'),sourceLabel=source&&source.querySelector(':scope > small'),scope=String(sourceLabel&&sourceLabel.textContent||'Стоимость тура').replace(/\s+/g,' ').trim();if(label)label.textContent=scope||'Стоимость тура';var target=bar.querySelector('[data-s3-selected-price]'),amount=selectedAmount(source);if(target)target.textContent=amount||'—';var btn=actionButton();if(!btn)return;btn.textContent='Далее: итог тура';}
function scheduleSync(){if(syncQueued)return;syncQueued=true;setTimeout(function(){syncQueued=false;sync()},0)}
function continueFlow(){
  if(selected.classList.contains('search3-lead-entry'))return;
  if(selected.classList.contains('search3-final-review')){
    if(window.Search3SummaryCta&&typeof window.Search3SummaryCta.enterLead==='function'){window.Search3SummaryCta.enterLead('mobile-bar');return;}
    var summary=selected.querySelector('.search3-summary-submit');if(summary){summary.click();return;}
  }
  var next=selected.querySelector('.search3-flight-continue button');
  if(next){next.click();return;}
  var flights=selected.querySelector('.tour-flights');if(flights)flights.scrollIntoView({behavior:'smooth',block:'start'});
}
document.addEventListener('click',function(e){var btn=e.target&&e.target.closest&&e.target.closest('[data-s3-selected-lead]');if(!btn)return;e.preventDefault();continueFlow();});
window.addEventListener('v2:tour-selected',function(event){selectedTotal=normalizedTotal({tour:event&&event.detail&&event.detail.tour});scheduleSync();});
window.addEventListener('v2:tour-price-updated',function(event){selectedTotal=normalizedTotal(event&&event.detail);scheduleSync();});
['v2:selected-tour-opened','v2:selected-tour-closed','v2:results-rendered','v2:booking-review','search3:lead-entry','v2:lead-started','v2:lead-success','v2:lead-error'].forEach(function(name){window.addEventListener(name,scheduleSync);});
new MutationObserver(scheduleSync).observe(selected,{childList:true,subtree:true,attributes:true,attributeFilter:['hidden','class','style']});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',scheduleSync,{once:true});else scheduleSync();
window.Search3SelectedTourMobile={sync,scheduleSync,continueFlow,normalizeLeadFields,normalizedTotal,selectedAmount,version:14};
})();
