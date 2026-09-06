function layoutStyle(node,name,value){node.style.setProperty(name,value,'important');}
function clearLayout(shell,form,summary){['display','grid-column','grid-template-columns','gap','align-items'].forEach(p=>shell.style.removeProperty(p));['grid-column','grid-row'].forEach(p=>form.style.removeProperty(p));['display','grid-column','grid-row'].forEach(p=>summary.style.removeProperty(p));}
function syncLayout(){
  const root=document.getElementById('selectedTour'),form=root&&root.querySelector('.lead-form'),shell=form&&form.closest('.search3-lead-shell'),summary=shell&&shell.querySelector('.search3-booking-summary');
  if(!root||!form||!shell||!summary)return;
  const desktop=window.matchMedia('(min-width:1000px)').matches,finalReview=root.classList.contains('search3-final-review'),leadEntry=root.classList.contains('search3-lead-entry');
  if(finalReview&&!leadEntry)root.dataset.search3FinalLayout='maket7';else delete root.dataset.search3FinalLayout;
  const title=summary.querySelector('.search3-booking-summary__title'),titleText=finalReview&&!leadEntry?'Итоговая стоимость':'Ваш тур';if(title&&title.textContent!==titleText)title.textContent=titleText;
  const flight=summary.querySelector('.search3-booking-summary__flight'),label=flightLabel(lastFlight);if(flight&&flight.textContent!==label)flight.textContent=label;
  clearLayout(shell,form,summary);
  if(desktop&&finalReview&&leadEntry){
    layoutStyle(shell,'display','grid');
    layoutStyle(shell,'grid-column','1 / -1');
    layoutStyle(shell,'grid-template-columns','minmax(0,1fr) 320px');
    layoutStyle(shell,'gap','18px');
    layoutStyle(shell,'align-items','start');
    layoutStyle(form,'grid-column','1');
    layoutStyle(form,'grid-row','1');
    layoutStyle(summary,'display','block');
    layoutStyle(summary,'grid-column','2');
    layoutStyle(summary,'grid-row','1');
  }else if(desktop&&finalReview){
    /* Maket7 final review uses a compact hotel card on the left and cost-only rail on the right. */
    layoutStyle(shell,'display','contents');
    layoutStyle(form,'grid-column','1 / 3');
    layoutStyle(summary,'display','block');
    layoutStyle(summary,'grid-column','3');
    layoutStyle(summary,'grid-row','4 / 12');
  }
}
