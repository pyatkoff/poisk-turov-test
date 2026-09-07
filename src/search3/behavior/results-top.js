

/* donor:search3-results-top.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
/* Donor CSS is bundled into the isolated candidate asset. */
var form=document.getElementById('tourSearch'),tools=document.getElementById('resultsTools'),heading=tools&&tools.querySelector('strong'),summary=document.getElementById('resultSummary'),searchSummary=document.getElementById('resultsSearchSummary'),edit=document.getElementById('resultsSearchEdit'),results=document.getElementById('results');
if(!form||!tools||!heading||!summary)return;
function word(n,one,few,many){var x=Math.abs(Number(n)||0)%100,y=x%10;if(x>10&&x<20)return many;if(y===1)return one;if(y>=2&&y<=4)return few;return many;}
function toursCount(items){return (Array.isArray(items)?items:[]).reduce(function(sum,h){return sum+(Array.isArray(h&&h.tours)?h.tours.length:0);},0);}
function selectedText(name){var el=form.elements[name];if(!el)return'';if(el.tagName==='SELECT'){var o=el.options&&el.selectedIndex>=0?el.options[el.selectedIndex]:null;return o?String(o.textContent||'').trim():'';}return String(el.value||'').trim();}
function compactRoute(){var from=selectedText('from'),country=selectedText('country'),region=selectedText('region');var dest=[country,region].filter(Boolean).join(', ');return [from,dest].filter(Boolean).join(' → ');}
function syncRoute(){if(searchSummary){var route=searchSummary.querySelector('#resultsSearchRoute');if(route){var text=compactRoute();if(text)route.textContent=text;}}}
function emptyLocalResults(){return !!document.querySelector('.results-filter-rail[data-s3-empty-results="1"]');}
function hasResults(){return !!(results&&results.querySelector('.hotel-card'))||emptyLocalResults();}
function syncResultsState(){var has=hasResults();document.body.classList.toggle('search3-has-results',has);if(has){document.body.classList.remove('search3-editing-search');syncRoute();}}
function update(items){items=Array.isArray(items)?items:[];var hotels=items.length,tours=toursCount(items),has=hotels>0||emptyLocalResults();heading.textContent='Найдено '+tours+' '+word(tours,'тур','тура','туров');summary.textContent=hotels?hotels+' '+word(hotels,'отель','отеля','отелей')+' · актуальные варианты':'Актуальные варианты';document.body.classList.toggle('search3-has-results',has);document.body.classList.remove('search3-editing-search');if(has)syncRoute();}
window.addEventListener('v2:results-rendered',function(e){update(e&&e.detail&&Array.isArray(e.detail.items)?e.detail.items:[]);});
window.addEventListener('v2:search-reset',function(){document.body.classList.remove('search3-has-results','search3-editing-search');heading.textContent='Предложения';summary.textContent='Актуальные варианты';});
// Result mutations coalesce state updates; responsive geometry belongs to CSS.
var frameQueued=false;
function scheduleResultsSync(){
  if(frameQueued)return;
  frameQueued=true;
  requestAnimationFrame(function(){
    frameQueued=false;
    syncResultsState();
  });
}
if(results){new MutationObserver(scheduleResultsSync).observe(results,{childList:true});syncResultsState();}
if(edit)edit.addEventListener('click',function(){document.body.classList.add('search3-editing-search');form.scrollIntoView({behavior:'smooth',block:'start'});var focusTarget=form.querySelector('select,input:not([type="hidden"]),button');if(focusTarget)setTimeout(function(){try{focusTarget.focus({preventScroll:true});}catch(_e){focusTarget.focus();}},250);});
form.addEventListener('change',syncRoute);syncRoute();
})();
