(function(){'use strict';
if(window.Search3HotelDetailsPresentationV1)return;
const targetPath=/^\/_preview\/search3-local-candidate(?:\/|$)/;
function active(){return targetPath.test(String(window.location&&window.location.pathname||''));}
function text(node){return String(node&&node.textContent||'').replace(/\s+/g,' ').trim();}
function normalizeCard(card){
 if(!active()||!card||typeof card.querySelector!=='function')return false;
 const summary=card.querySelector('.hotel-description-summary'),details=card.querySelector('.hotel-details'),content=details&&details.querySelector('.hotel-details-content'),duplicate=content&&content.querySelector('.hotel-description');
 if(!summary||!details||!content||!duplicate||text(summary)!==text(duplicate))return false;
 if(typeof duplicate.remove==='function')duplicate.remove();else if(duplicate.parentNode)duplicate.parentNode.removeChild(duplicate);
 const hasExtra=content.children?content.children.length>0:!!text(content);
 if(!hasExtra){if(typeof details.remove==='function')details.remove();else if(details.parentNode)details.parentNode.removeChild(details);}
 return true;
}
function normalize(root){
 if(!active()||!root||typeof root.querySelectorAll!=='function')return 0;
 let changed=0;root.querySelectorAll('.hotel-card').forEach(card=>{if(normalizeCard(card))changed++;});return changed;
}
window.addEventListener('v2:results-rendered',event=>normalize(event.detail&&event.detail.results||document.getElementById('results')));
window.addEventListener('v2:hotel-details-rendered',event=>{const root=document.getElementById('results'),id=String(event.detail&&event.detail.hotelId||'');if(!root||!id)return;const card=Array.from(root.querySelectorAll('.hotel-card')).find(node=>String(node.dataset&&node.dataset.hotelId||'')===id);if(card)normalizeCard(card);});
window.Search3HotelDetailsPresentationV1={active,normalizeCard,normalize,version:1};
})();
