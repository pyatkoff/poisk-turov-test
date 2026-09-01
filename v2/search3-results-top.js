(function(){'use strict';
var form=document.getElementById('tourSearch'),tools=document.getElementById('resultsTools'),heading=tools&&tools.querySelector('strong'),summary=document.getElementById('resultSummary'),searchSummary=document.getElementById('resultsSearchSummary');
if(!form||!tools||!heading||!summary)return;
function word(n,one,few,many){var x=Math.abs(Number(n)||0)%100,y=x%10;if(x>10&&x<20)return many;if(y===1)return one;if(y>=2&&y<=4)return few;return many;}
function toursCount(items){return (Array.isArray(items)?items:[]).reduce(function(sum,h){return sum+(Array.isArray(h&&h.tours)?h.tours.length:0);},0);}
function selectedText(name){var el=form.elements[name];if(!el)return'';if(el.tagName==='SELECT'){var o=el.options&&el.selectedIndex>=0?el.options[el.selectedIndex]:null;return o?String(o.textContent||'').trim():'';}return String(el.value||'').trim();}
function compactRoute(){var from=selectedText('from'),country=selectedText('country'),region=selectedText('region');var dest=[country,region].filter(Boolean).join(' · ');return [from,dest].filter(Boolean).join(' → ');}
function syncRoute(){if(!searchSummary)return;var route=searchSummary.querySelector('#resultsSearchRoute');if(route){var text=compactRoute();if(text)route.textContent=text;}}
function ensureMeta(){var existing=tools.querySelector('.search3-results-meta');if(existing)return existing;var meta=document.createElement('div');meta.className='search3-results-meta';meta.innerHTML='<span data-s3-hotels>0 отелей</span><span aria-hidden="true">·</span><span data-s3-tours>0 туров</span>';var first=tools.firstElementChild;if(first)first.appendChild(meta);return meta;}
function update(items){items=Array.isArray(items)?items:[];var hotels=items.length,tours=toursCount(items);heading.textContent='Найдено '+tours+' '+word(tours,'тур','тура','туров');summary.textContent=hotels?hotels+' '+word(hotels,'отель','отеля','отелей')+' · актуальные варианты':'Актуальные варианты';var meta=ensureMeta(),h=meta.querySelector('[data-s3-hotels]'),t=meta.querySelector('[data-s3-tours]');if(h)h.textContent=hotels+' '+word(hotels,'отель','отеля','отелей');if(t)t.textContent=tours+' '+word(tours,'тур','тура','туров');syncRoute();document.body.classList.toggle('search3-has-results',hotels>0);}
window.addEventListener('v2:results-rendered',function(e){update(e&&e.detail&&Array.isArray(e.detail.items)?e.detail.items:[]);});
window.addEventListener('v2:search-reset',function(){document.body.classList.remove('search3-has-results');heading.textContent='Предложения';summary.textContent='Актуальные варианты';var meta=tools.querySelector('.search3-results-meta');if(meta)meta.remove();});
form.addEventListener('change',syncRoute);syncRoute();
})();
