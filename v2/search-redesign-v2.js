(function(){'use strict';
if(window.V2SearchRedesignV2)return;
var body=document.body,tools=document.getElementById('resultsTools'),selected=document.getElementById('selectedTour');
if(!body||!tools)return;
function sync(){
  var hasResults=!tools.hidden;
  var hasSelected=!!(selected&&!selected.hidden);
  body.classList.toggle('at-search2-results',hasResults);
  body.classList.toggle('at-search2-selected',hasSelected);
}
var observer=new MutationObserver(sync);
observer.observe(tools,{attributes:true,attributeFilter:['hidden','class']});
if(selected)observer.observe(selected,{attributes:true,attributeFilter:['hidden','class']});
['v2:search-started','v2:search-progress','v2:results-rendered','v2:search-complete','v2:search-dirty','v2:selected-tour-changed','v2:selected-tour-cleared'].forEach(function(name){window.addEventListener(name,sync);});
sync();
window.V2SearchRedesignV2={sync:sync,version:1};
})();
