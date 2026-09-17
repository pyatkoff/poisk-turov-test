(function(){'use strict';
if(window.Search3ResultsContinuityV1)return;
const openDetails=new Set();
let focusState=null,anchorState=null,anchorFrame=0;
function resultsNode(){return document.getElementById('results');}
function cardOf(node){return node&&typeof node.closest==='function'?node.closest('.hotel-card'):null;}
function hotelId(card){return String(card&&card.dataset&&card.dataset.hotelId||'');}
function cardById(results,id){if(!results||!id)return null;return Array.from(results.querySelectorAll('.hotel-card')).find(card=>hotelId(card)===String(id))||null;}
function focusIdentity(node){
 const card=cardOf(node),hotel=hotelId(card);if(!card||!hotel||!node||typeof node.closest!=='function')return null;
 let target=node.closest('.direct-tour');if(target)return{hotel,kind:'tour',value:String(target.dataset.tid||'')};
 target=node.closest('.provider-detail-toggle');if(target)return{hotel,kind:'provider',value:String(target.dataset.andromedaDetail||'')};
 target=node.closest('[data-detail-retry]');if(target)return{hotel,kind:'provider-retry',value:String(target.dataset.andromedaDetail||'')};
 target=node.closest('.tour-more-toggle');if(target)return{hotel,kind:'offers',value:''};
 target=node.closest('.tour-list-more');if(target)return{hotel,kind:'offers-more',value:''};
 target=node.closest('.hotel-gallery-thumb');if(target)return{hotel,kind:'gallery',value:String(target.dataset.galleryIndex||'')};
 target=node.closest('summary');if(target&&target.parentElement&&target.parentElement.classList.contains('hotel-details'))return{hotel,kind:'details',value:''};
 return null;
}
function focusTarget(results,state){
 const card=state&&cardById(results,state.hotel);if(!card)return null;
 let nodes=[];
 if(state.kind==='tour')nodes=card.querySelectorAll('.direct-tour');
 else if(state.kind==='provider')nodes=card.querySelectorAll('.provider-detail-toggle');
 else if(state.kind==='provider-retry')nodes=card.querySelectorAll('[data-detail-retry]');
 else if(state.kind==='offers')return card.querySelector('.tour-more-toggle');
 else if(state.kind==='offers-more')return card.querySelector('.tour-list-more');
 else if(state.kind==='gallery')nodes=card.querySelectorAll('.hotel-gallery-thumb');
 else if(state.kind==='details')return card.querySelector('.hotel-details > summary');
 return Array.from(nodes).find(node=>state.kind==='tour'?String(node.dataset.tid||'')===state.value:state.kind==='gallery'?String(node.dataset.galleryIndex||'')===state.value:String(node.dataset.andromedaDetail||'')===state.value)||null;
}
function sampleAnchor(results){
 results=results||resultsNode();if(!results)return;
 const cards=Array.from(results.querySelectorAll('.hotel-card'));if(!cards.length){anchorState=null;return;}
 const height=Math.max(1,Number(window.innerHeight)||1),visible=cards.find(card=>{const rect=card.getBoundingClientRect();return rect.bottom>0&&rect.top<height;}),card=visible||cards.find(item=>item.getBoundingClientRect().bottom>0);
 if(!card)return;const id=hotelId(card);if(!id)return;anchorState={hotel:id,top:card.getBoundingClientRect().top};
}
function scheduleAnchor(){if(anchorFrame)return;const run=()=>{anchorFrame=0;sampleAnchor();};anchorFrame=typeof window.requestAnimationFrame==='function'?window.requestAnimationFrame(run):(setTimeout(run,16),-1);}
function rememberDetails(details){const card=cardOf(details),id=hotelId(card);if(!id)return;if(details.open)openDetails.add(id);else openDetails.delete(id);sampleAnchor();}
function restoreDetails(results){openDetails.forEach(id=>{const card=cardById(results,id),details=card&&card.querySelector('.hotel-details');if(details)details.open=true;});}
function restoreFocus(results){if(!focusState)return;const target=focusTarget(results,focusState);if(!target){focusState=null;return;}try{target.focus({preventScroll:true});}catch(e){target.focus();}}
function restoreAnchor(results){if(!anchorState)return;const card=cardById(results,anchorState.hotel);if(!card){anchorState=null;return;}const delta=card.getBoundingClientRect().top-Number(anchorState.top||0);if(Math.abs(delta)>=1&&typeof window.scrollBy==='function')window.scrollBy(0,delta);anchorState={hotel:anchorState.hotel,top:card.getBoundingClientRect().top};}
function purge(results){const ids=new Set(Array.from(results.querySelectorAll('.hotel-card')).map(hotelId).filter(Boolean));Array.from(openDetails).forEach(id=>{if(!ids.has(id))openDetails.delete(id);});if(focusState&&!ids.has(focusState.hotel))focusState=null;if(anchorState&&!ids.has(anchorState.hotel))anchorState=null;}
function restore(results){if(!results)return;restoreDetails(results);restoreAnchor(results);restoreFocus(results);purge(results);scheduleAnchor();}
function reset(){openDetails.clear();focusState=null;anchorState=null;if(anchorFrame&&typeof window.cancelAnimationFrame==='function'&&anchorFrame!==-1)window.cancelAnimationFrame(anchorFrame);anchorFrame=0;}
document.addEventListener('focusin',event=>{const results=resultsNode();if(!results||!results.contains(event.target)){focusState=null;return;}focusState=focusIdentity(event.target);sampleAnchor(results);},true);
document.addEventListener('toggle',event=>{const details=event.target;if(details&&details.classList&&details.classList.contains('hotel-details'))rememberDetails(details);},true);
document.addEventListener('pointerdown',event=>{const results=resultsNode();if(results&&results.contains(event.target))sampleAnchor(results);},true);
window.addEventListener('scroll',scheduleAnchor,{passive:true});
window.addEventListener('resize',scheduleAnchor,{passive:true});
window.addEventListener('v2:search-progress',()=>sampleAnchor());
window.addEventListener('v2:search-continue-started',()=>sampleAnchor());
window.addEventListener('v2:search-continue-progress',()=>sampleAnchor());
window.addEventListener('v2:results-rendered',event=>restore(event.detail&&event.detail.results||resultsNode()));
window.addEventListener('v2:search-started',reset);
window.addEventListener('v2:search-reset',reset);
window.Search3ResultsContinuityV1={sampleAnchor,restore,reset,version:1};
})();
