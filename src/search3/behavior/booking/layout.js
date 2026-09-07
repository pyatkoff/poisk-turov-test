function syncLayout(){
  const root=document.getElementById('selectedTour'),form=root&&root.querySelector('.lead-form'),shell=form&&form.closest('.search3-lead-shell'),summary=shell&&shell.querySelector('.search3-booking-summary');
  if(!root||!form||!shell||!summary)return;
  const finalReview=root.classList.contains('search3-final-review'),leadEntry=root.classList.contains('search3-lead-entry');
  if(finalReview&&!leadEntry)root.dataset.search3FinalLayout='maket7';else delete root.dataset.search3FinalLayout;
  const title=summary.querySelector('.search3-booking-summary__title'),titleText=finalReview&&!leadEntry?'Итоговая стоимость':'Ваш тур';if(title&&title.textContent!==titleText)title.textContent=titleText;
  const flight=summary.querySelector('.search3-booking-summary__flight'),label=flightLabel(lastFlight);if(flight&&flight.textContent!==label)flight.textContent=label;
}
