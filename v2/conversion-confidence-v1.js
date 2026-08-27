(function(){'use strict';
if(window.V2ConversionConfidenceV1)return;
const results=document.getElementById('results'),tools=document.getElementById('resultsTools');
function decorateTourButtons(root){(root||document).querySelectorAll('.direct-tour').forEach(btn=>{if(btn.dataset.v2Confidence==='1')return;btn.dataset.v2Confidence='1';btn.textContent='Проверить тур';const row=btn.closest('.tour-row'),date=row&&row.querySelector('.tour-meta>strong');const dateText=String(date&&date.textContent||'').trim();btn.setAttribute('aria-label',dateText?'Проверить тур на '+dateText:'Проверить выбранный тур');});}
function ensureResultsNote(){if(!tools||!results)return null;let note=document.getElementById('v2ResultsConfidence');if(note)return note;note=document.createElement('div');note.id='v2ResultsConfidence';note.className='results-confidence';note.hidden=true;note.setAttribute('role','note');note.innerHTML='<strong>Перед заявкой вы всё проверите</strong><span>Откройте конкретный тур — покажем рейс, багаж и итоговую стоимость. Отправка заявки не является оплатой.</span>';tools.insertAdjacentElement('afterend',note);return note;}
function syncResultsNote(items){const note=ensureResultsNote();if(!note)return;note.hidden=!(Array.isArray(items)&&items.length);}
function decorateResults(detail){const items=detail&&Array.isArray(detail.items)?detail.items:[];decorateTourButtons(results||document);syncResultsNote(items);}
window.addEventListener('v2:results-rendered',e=>decorateResults(e.detail||{}));
window.addEventListener('v2:search-started',()=>{const note=ensureResultsNote();if(note)note.hidden=true;});
window.addEventListener('v2:search-dirty',()=>{const note=ensureResultsNote();if(note)note.hidden=true;});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>{ensureResultsNote();decorateTourButtons(results||document);},{once:true});else{ensureResultsNote();decorateTourButtons(results||document);}
window.V2ConversionConfidenceV1={decorateTourButtons,ensureResultsNote,syncResultsNote,decorateResults,version:1};
})();
