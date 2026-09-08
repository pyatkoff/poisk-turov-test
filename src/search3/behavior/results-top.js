
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
  function busy(value){results.setAttribute('aria-busy',value?'true':'false');}
  window.addEventListener('v2:search-started',function(){busy(true);});
  window.addEventListener('v2:search-complete',function(){busy(false);});
  window.addEventListener('v2:search-error',function(){busy(false);});
  window.addEventListener('v2:results-rendered',function(){busy(false);body.classList.remove('search3-editing-search');active(true);});
  document.addEventListener('click',function(event){
    var edit=event.target&&event.target.closest&&event.target.closest('#resultsSearchEdit,.empty-edit-search');
    var form=document.getElementById('tourSearch');
    if(!edit||!form)return;
    event.preventDefault();
    body.classList.add('search3-editing-search');
    form.scrollIntoView({behavior:'smooth',block:'start'});
    var field=form.querySelector('select:not([disabled]),input:not([type="hidden"]):not([disabled])');
    if(field)field.focus({preventScroll:true});
  });
  window.addEventListener('v2:tour-selected',function(){active(false);});
  window.addEventListener('v2:selected-tour-closed',function(){active(results.children.length>0);});
  window.addEventListener('v2:search-reset',function(){busy(true);active(false);});
}());
