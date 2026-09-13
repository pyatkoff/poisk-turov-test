/* Native Search3 entry and the remaining result-shell state. */
(function(){'use strict';
var form=document.getElementById('tourSearch'),results=document.getElementById('results');if(!form||!results)return;
form.dataset.search3Ready='1';
const busy=value=>results.setAttribute('aria-busy',value),on=(name,handler)=>window.addEventListener(name,handler);
on('v2:search-started',()=>{form.querySelectorAll('details[open]').forEach(node=>node.open=false);busy('true')});
on('v2:search-reset',()=>busy('true'));
on('v2:search-error',()=>busy('false'));
on('v2:results-rendered',()=>busy('false'));
document.addEventListener('click',event=>{if(event.target.closest('#resultsSearchEdit,.empty-edit-search'))form.elements.from.focus()});
})();
