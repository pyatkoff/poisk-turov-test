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
// Reuse the canonical action URL; presentation never reconstructs search authority.
function hotelTitleHref(card){
 if(!active()||hotelUrlId()||!card||typeof card.querySelector!=='function'||window.V2SearchLifecycle?.dirty)return'';
 const id=String(card.dataset&&card.dataset.anytourHotelId||''),action=card.querySelector('a.tour-more-toggle[target="_blank"]');
 if(!/^[1-9][0-9]*$/.test(id)||!action)return'';
 const href=String(action.getAttribute('href')||'');
 try{
  const current=new URL(window.location.href),url=new URL(href,current);
  if(!/^https?:$/.test(url.protocol)||url.origin!==current.origin||url.pathname!==current.pathname||url.username||url.password)return'';
  const hotels=url.searchParams.getAll('search3_hotel'),searches=url.searchParams.getAll('search3_search');
  if(hotels.length!==1||hotels[0]!==id||searches.length!==1||!/^[1-9][0-9]*$/.test(searches[0]))return'';
  return href;
 }catch(error){return'';}
}
function unwrapTitleLink(link){
 const parent=link&&link.parentNode;if(!parent)return false;
 while(link.firstChild)parent.insertBefore(link.firstChild,link);
 parent.removeChild(link);return true;
}
function normalizeHotelTitleLink(card){
 if(!active()||!card||typeof card.querySelector!=='function')return false;
 const title=card.querySelector('.hotel-title');if(!title||typeof title.querySelector!=='function')return false;
 let link=title.querySelector('a.search3-hotel-title-link');
 const href=hotelTitleHref(card);
 if(!href)return unwrapTitleLink(link);
 if(!link&&title.querySelector('a'))return false;
 const name=text(title);if(!name)return false;
 let changed=false;
 if(!link){
  link=document.createElement('a');link.className='search3-hotel-title-link';
  while(title.firstChild)link.appendChild(title.firstChild);
  title.appendChild(link);changed=true;
 }
 const attributes={href,target:'_blank',rel:'noopener','aria-label':name+' — откроется в новой вкладке'};
 Object.keys(attributes).forEach(key=>{if(link.getAttribute(key)!==attributes[key]){link.setAttribute(key,attributes[key]);changed=true;}});
 return changed;
}
// Link only the already displayed image; do not invent a larger supplier URL.
function normalizeHotelPhotoLink(card){
 if(!active()||!card||typeof card.querySelector!=='function'||!/^[1-9][0-9]*$/.test(String(card.dataset&&card.dataset.anytourHotelId||'')))return false;
 const gallery=card.querySelector('.hotel-gallery'),main=gallery&&gallery.querySelector('img.hotel-gallery-main');
 if(!gallery)return false;
 let link=gallery.querySelector('a.search3-hotel-photo-link');
 const href=String(main&&main.getAttribute('src')||'');
 let safe=false;
 try{const url=new URL(href);safe=/^https:\/\//i.test(href)&&!/[\s\u0000-\u001f\u007f]/.test(href)&&url.protocol==='https:'&&!url.username&&!url.password;}catch(error){}
 if(!safe){if(link){link.parentNode.removeChild(link);return true;}return false;}
 let changed=false;
 if(!link){
  link=document.createElement('a');link.className='search3-hotel-photo-link';
  // Cover the existing photo without moving its image or the z-index:2 thumbnails.
  link.setAttribute('style','position:absolute;inset:0;z-index:1;outline-offset:-3px');
  gallery.appendChild(link);changed=true;
 }
 const attributes={href,target:'_blank',rel:'noopener noreferrer','aria-label':String(main.getAttribute('alt')||'Фото отеля')+' — открыть в новой вкладке',title:'Открыть фото в новой вкладке'};
 Object.keys(attributes).forEach(key=>{if(link.getAttribute(key)!==attributes[key]){link.setAttribute(key,attributes[key]);changed=true;}});
 return changed;
}
function resetPresentation(){
 if(!active())return;
 restoreTitle();
 const root=document.getElementById('results');
 if(root&&typeof root.querySelectorAll==='function')root.querySelectorAll('.hotel-title a.search3-hotel-title-link').forEach(unwrapTitleLink);
}
function normalizeCard(card){
 if(!active()||!card||typeof card.querySelector!=='function')return false;
 let changed=normalizeAmbiguousRating(card);
 if(normalizeHotelTitleLink(card))changed=true;
 if(normalizeHotelPhotoLink(card))changed=true;
 const summary=card.querySelector('.hotel-description-summary'),details=card.querySelector('.hotel-details'),content=details&&details.querySelector('.hotel-details-content'),duplicate=content&&content.querySelector('.hotel-description');
 if(!summary||!details||!content||!duplicate||text(summary)!==text(duplicate))return changed;
 // A description-only disclosure is still needed: CSS swaps its teaser/full text
 // and reveals mobile gallery controls on open. Deduplicate only rich details.
 if(!content.children||!Array.from(content.children).some(node=>node!==duplicate))return changed;
 if(typeof duplicate.remove==='function')duplicate.remove();else if(duplicate.parentNode)duplicate.parentNode.removeChild(duplicate);
 return true;
}
function normalize(root){
 if(!active()||!root||typeof root.querySelectorAll!=='function')return 0;
 let changed=0;root.querySelectorAll('.hotel-card').forEach(card=>{if(normalizeCard(card))changed++;});syncHotelPageTitle(root);return changed;
}
window.addEventListener('v2:results-rendered',event=>normalize(event.detail&&event.detail.results||document.getElementById('results')));
window.addEventListener('v2:hotel-details-rendered',event=>{const root=document.getElementById('results'),id=String(event.detail&&event.detail.hotelId||'');if(!root||!id)return;const card=Array.from(root.querySelectorAll('.hotel-card')).find(node=>String(node.dataset&&node.dataset.hotelId||'')===id);if(card)normalizeCard(card);});
// Bubble after the renderer's capture-phase thumbnail swap, before native navigation.
['click','auxclick','contextmenu','focusin'].forEach(name=>window.addEventListener(name,event=>{
 const target=event.target,gallery=target&&typeof target.closest==='function'&&target.closest('#results .hotel-gallery');
 if(gallery)normalizeHotelPhotoLink(gallery.closest('.hotel-card'));
}));
['v2:search-started','v2:search-reset'].forEach(name=>window.addEventListener(name,resetPresentation));
window.Search3HotelDetailsPresentationV1={active,syncHotelPageTitle,normalizeAmbiguousRating,hotelTitleHref,normalizeHotelTitleLink,normalizeHotelPhotoLink,normalizeCard,normalize,version:6};
})();
