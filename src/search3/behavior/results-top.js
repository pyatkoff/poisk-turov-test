
/* Minimal result-state bridge. Rendering, sorting and tour selection stay in V2ResultsV5. */
(function(){
  'use strict';
  var body=document.body,results=document.getElementById('results'),tools=document.getElementById('resultsTools');
  if(!body||!body.classList.contains('search3-candidate')||!results)return;
  function active(value){
    body.classList.toggle('search3-has-results',value);
    body.classList.toggle('search3-results-active',value);
    if(tools)tools.hidden=!value;
  }
  window.addEventListener('v2:results-rendered',function(){active(true);});
  window.addEventListener('v2:tour-selected',function(){active(false);});
  window.addEventListener('v2:selected-tour-closed',function(){active(results.children.length>0);});
  window.addEventListener('v2:search-reset',function(){active(false);});
}());
