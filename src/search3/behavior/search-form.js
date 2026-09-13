/* Native Search3 entry and the remaining result-shell state. */
(function(){'use strict';
var form=document.getElementById('tourSearch'),results=document.getElementById('results'),edit=document.getElementById('resultsSearchEdit');if(!form||!results)return;
form.dataset.search3Ready='1';
const busy=value=>results.setAttribute('aria-busy',value),on=(name,handler)=>window.addEventListener(name,handler);
let editing=false;
function setEditor(open,focus){form.dataset.search3View=open?'editor':'summary';if(edit){edit.setAttribute('aria-controls','tourSearch');edit.setAttribute('aria-expanded',String(open))}if(open&&focus){form.scrollIntoView({block:'start'});form.elements.from.focus({preventScroll:true})}}
function hasHotels(){return!!results.querySelector('.hotel-card')}
on('v2:search-started',()=>{editing=false;form.querySelectorAll('details[open]').forEach(node=>node.open=false);if(hasHotels())setEditor(false);busy('true')});
on('v2:search-reset',()=>{editing=true;setEditor(true);busy('true')});
on('v2:search-error',()=>{editing=true;setEditor(true);busy('false')});
on('v2:results-rendered',()=>{if(hasHotels()&&!editing)setEditor(false);else if(!hasHotels())setEditor(true);busy('false')});
document.addEventListener('click',event=>{if(event.target.closest('#resultsSearchEdit,.empty-edit-search')){editing=true;setEditor(true,true)}});
setEditor(!hasHotels());
})();
