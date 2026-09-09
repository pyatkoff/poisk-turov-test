/* Native Search3 entry and the remaining result-shell state. */
(function(){'use strict';
var form=document.getElementById('tourSearch'),body=document.body,results=document.getElementById('results');if(!form||!results)return;
form.dataset.search3Ready='1';
const busy=value=>results.setAttribute('aria-busy',value),on=(name,handler)=>window.addEventListener(name,handler);
on('v2:search-started',()=>{form.querySelectorAll('details[open]').forEach(node=>node.open=false);busy('true')});
on('v2:search-reset',()=>busy('true'));
on('v2:search-error',()=>{busy('false');body.classList.add('search3-editing-search')});
on('v2:results-rendered',()=>{busy('false');body.classList.remove('search3-editing-search')});
document.addEventListener('click',event=>{if(event.target.matches('#resultsSearchEdit,.empty-edit-search')){body.classList.add('search3-editing-search');form.elements.from.focus()}});
})();
