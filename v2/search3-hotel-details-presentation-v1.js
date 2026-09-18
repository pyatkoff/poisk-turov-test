(function(){'use strict';
if(window.Search3HotelDetailsPresentationV1)return;
const targetPath=/^\/_preview\/search3-local-candidate(?:\/|$)/;
const baseTitle=String(document.title||'Поиск туров онлайн — AnyTour');
function active(){return targetPath.test(String(window.location&&window.location.pathname||''));}
function text(node){return String(node&&node.textContent||'').replace(/\s+/g,' ').trim();}
function restoreTitle(){if(document.title!==baseTitle)document.title=baseTitle;}
function hotelUrlId(){
 if(!active())return'';
 try{return String(new URLSearchParams(String(window.location&&window.location.search||'')).get('search3_hotel')||'');}catch(error){return'';}
}
function syncHotelPageTitle(root){
 const id=hotelUrlId();
 if(!id||!root||typeof root.querySelectorAll!=='function'){restoreTitle();return false;}
 const cards=Array.from(root.querySelectorAll('.hotel-card[data-anytour-hotel-id]'));
 const card=cards.length===1&&String(cards[0].dataset&&cards[0].dataset.anytourHotelId||'')===id?cards[0]:null;
 const name=card?text(card.querySelector('.hotel-title')):'';
 if(!name){restoreTitle();return false;}
 const title=name+' — туры AnyTour',changed=document.title!==title;
 document.title=title;
 return changed;
}
function normalizeAmbiguousRating(card){
 if(!active()||!card||typeof card.querySelector!=='function')return false;
 if(!card.dataset||!String(card.dataset.anytourHotelId||''))return false;
 const rating=card.querySelector('.hotel-decision-rating'),match=text(rating).match(/^Рейтинг\s+([0-9]+(?:[.,][0-9]+)?)$/i);
 if(!rating||!match)return false;
 const value=match[1].replace('.',',');
 rating.textContent='Каталожная оценка '+value+' · шкала не указана';
 if(rating.classList){rating.classList.remove('hotel-decision-rating');rating.classList.add('hotel-decision-rating-unscaled');}
 rating.setAttribute('data-rating-semantics','unscaled');
 rating.setAttribute('aria-label','Каталожная оценка '+value+'. Источник, шкала и число отзывов не указаны.');
 return true;
}
function normalizeCard(card){
 if(!active()||!card||typeof card.querySelector!=='function')return false;
 let changed=normalizeAmbiguousRating(card);
 const summary=card.querySelector('.hotel-description-summary'),details=card.querySelector('.hotel-details'),content=details&&details.querySelector('.hotel-details-content'),duplicate=content&&content.querySelector('.hotel-description');
 if(!summary||!details||!content||!duplicate||text(summary)!==text(duplicate))return changed;
 if(typeof duplicate.remove==='function')duplicate.remove();else if(duplicate.parentNode)duplicate.parentNode.removeChild(duplicate);
 const hasExtra=content.children?content.children.length>0:!!text(content);
 if(!hasExtra){if(typeof details.remove==='function')details.remove();else if(details.parentNode)details.parentNode.removeChild(details);}
 return true;
}
function normalize(root){
 if(!active()||!root||typeof root.querySelectorAll!=='function')return 0;
 let changed=0;root.querySelectorAll('.hotel-card').forEach(card=>{if(normalizeCard(card))changed++;});syncHotelPageTitle(root);return changed;
}
window.addEventListener('v2:results-rendered',event=>normalize(event.detail&&event.detail.results||document.getElementById('results')));
window.addEventListener('v2:hotel-details-rendered',event=>{const root=document.getElementById('results'),id=String(event.detail&&event.detail.hotelId||'');if(!root||!id)return;const card=Array.from(root.querySelectorAll('.hotel-card')).find(node=>String(node.dataset&&node.dataset.hotelId||'')===id);if(card)normalizeCard(card);});
['v2:search-started','v2:search-reset'].forEach(name=>window.addEventListener(name,restoreTitle));
window.Search3HotelDetailsPresentationV1={active,syncHotelPageTitle,normalizeAmbiguousRating,normalizeCard,normalize,version:3};
})();
