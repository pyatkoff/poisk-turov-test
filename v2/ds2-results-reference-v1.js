(function(){
  const tools=document.getElementById('resultsTools');
  const results=document.getElementById('results');
  const selected=document.getElementById('selectedTour');
  if(!tools||!results||!selected)return;
  function sync(){
    const selectedVisible=!selected.hidden;
    const hasResults=!tools.hidden||results.children.length>0;
    document.body.classList.toggle('ds2-results-active',hasResults&&!selectedVisible);
    document.body.classList.toggle('ds2-selected-active',selectedVisible);
  }
  new MutationObserver(sync).observe(tools,{attributes:true,attributeFilter:['hidden']});
  new MutationObserver(sync).observe(results,{childList:true});
  new MutationObserver(sync).observe(selected,{attributes:true,attributeFilter:['hidden']});
  window.addEventListener('pageshow',sync);
  sync();
})();
