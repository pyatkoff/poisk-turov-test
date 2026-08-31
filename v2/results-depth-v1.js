(function(){'use strict';
if(window.V2ResultsDepthV1)return;
const INITIAL_LIMIT=25,EXPANDED_LIMIT=100;
let requestSeq=0;
async function expand(event){
  const detail=event&&event.detail||{},searchId=Number(detail.searchId||0),items=Array.isArray(detail.items)?detail.items:[];
  if(!searchId||items.length<INITIAL_LIMIT)return;
  const lifecycle=window.V2SearchLifecycle,runtime=window.V2Runtime,renderer=window.V2Results;
  if(!lifecycle||!runtime||!renderer||typeof runtime.api!=='function'||typeof renderer.render!=='function')return;
  const seq=++requestSeq;
  try{
    const list=await runtime.api('search_results',{searchId,limit:EXPANDED_LIMIT});
    if(seq!==requestSeq)return;
    if(Number(lifecycle.searchId||0)!==searchId||lifecycle.dirty||lifecycle.pending)return;
    if(!Array.isArray(list)||list.length<=items.length)return;
    renderer.render(list);
    window.dispatchEvent(new CustomEvent('v2:results-depth-expanded',{detail:{searchId,from:items.length,to:list.length,limit:EXPANDED_LIMIT}}));
  }catch(error){
    console.warn('results depth expansion failed; keeping initial results',error);
  }
}
window.addEventListener('v2:search-complete',expand);
window.addEventListener('v2:search-reset',()=>{requestSeq++;});
window.V2ResultsDepthV1={expand,initialLimit:INITIAL_LIMIT,expandedLimit:EXPANDED_LIMIT,version:1};
})();
