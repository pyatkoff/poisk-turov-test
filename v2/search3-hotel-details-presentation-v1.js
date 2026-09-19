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
function normalizeHotelRating(card){
 if(!active()||!card||typeof card.querySelector!=='function')return false;
 if(!card.dataset||!String(card.dataset.anytourHotelId||''))return false;
 const rating=card.querySelector('.hotel-decision-rating'),match=text(rating).match(/^Рейтинг\s+([0-9]+(?:[.,][0-9]+)?)$/i);
 if(!rating||!match)return false;
 const value=match[1].replace('.',',');
 if(!(Number(match[1].replace(',','.'))>0&&Number(match[1].replace(',','.'))<=5)){rating.remove();return true;}
 rating.textContent='Рейтинг отеля '+value+' из 5';
 rating.setAttribute('data-rating-semantics','five-point');
 rating.setAttribute('aria-label',rating.textContent);
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
function safePhotoUrl(href){
 try{const url=new URL(href);return /^https:\/\//i.test(href)&&!/[\s\u0000-\u001f\u007f]/.test(href)&&url.protocol==='https:'&&!url.username&&!url.password;}catch(error){return false;}
}
function supportsPhotoDialog(){return typeof window.HTMLDialogElement==='function'&&typeof window.HTMLDialogElement.prototype.showModal==='function';}
let photoViewer=null,photoSession=null;
function finishPhotoViewer(){
 const session=photoSession;photoSession=null;
 document.documentElement.classList.remove('hotel-photo-viewer-open');
 const image=photoViewer.querySelector('img');image.onload=null;image.onerror=null;image.removeAttribute('src');
 if(session&&session.link.isConnected)session.link.focus({preventScroll:true});
}
function closePhotoViewer(){if(photoViewer&&photoViewer.open){photoViewer.close();finishPhotoViewer();}}
function showPhoto(index){
 if(!photoSession)return;
 const session=photoSession;
 session.index=(index+session.urls.length)%session.urls.length;
 const url=session.urls[session.index],image=photoViewer.querySelector('img'),status=photoViewer.querySelector('.hotel-photo-viewer__status');
 status.hidden=false;status.textContent='Загружаем фото…';image.hidden=true;
 image.onload=()=>{if(photoSession!==session||image.getAttribute('src')!==url)return;status.hidden=true;image.hidden=false;};
 image.onerror=()=>{if(photoSession!==session||image.getAttribute('src')!==url)return;status.hidden=false;status.textContent='Не удалось загрузить фото. Попробуйте другой снимок или откройте оригинал.';image.hidden=true;};
 image.alt='Фото отеля '+session.name;image.src=url;
 photoViewer.querySelector('.hotel-photo-viewer__counter').textContent='Фото '+(session.index+1)+' из '+session.urls.length;
 photoViewer.querySelector('.hotel-photo-viewer__original').href=url;
}
function createPhotoViewer(){
 const dialog=document.createElement('dialog');dialog.className='hotel-photo-viewer';
 dialog.setAttribute('aria-labelledby','hotel-photo-viewer-title');
 dialog.innerHTML='<header class="hotel-photo-viewer__header"><h2 id="hotel-photo-viewer-title"></h2><button type="button" class="hotel-photo-viewer__close" aria-label="Закрыть фотографии" autofocus>×</button></header><div class="hotel-photo-viewer__stage"><img alt="" hidden><p class="hotel-photo-viewer__status" role="status"></p></div><footer class="hotel-photo-viewer__footer"><div class="hotel-photo-viewer__navigation"><button type="button" data-photo-step="-1" aria-label="Предыдущее фото">←</button><span class="hotel-photo-viewer__counter" role="status" aria-live="polite"></span><button type="button" data-photo-step="1" aria-label="Следующее фото">→</button></div><a class="hotel-photo-viewer__original" target="_blank" rel="noopener noreferrer">Открыть оригинал <span aria-hidden="true">↗</span></a></footer>';
 dialog.querySelector('.hotel-photo-viewer__close').addEventListener('click',closePhotoViewer);
 dialog.querySelectorAll('[data-photo-step]').forEach(button=>button.addEventListener('click',()=>{if(photoSession)showPhoto(photoSession.index+Number(button.dataset.photoStep));}));
 dialog.addEventListener('keydown',event=>{
  if(event.key==='Tab'&&photoSession){
   const actions=Array.from(dialog.querySelectorAll('button:not([hidden]),a[href]')),first=actions[0],last=actions[actions.length-1];
   if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
   else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
   return;
  }
  if(!photoSession||event.altKey||event.ctrlKey||event.metaKey||!['ArrowLeft','ArrowRight'].includes(event.key))return;
  event.preventDefault();showPhoto(photoSession.index+(event.key==='ArrowRight'?1:-1));
 });
 const stage=dialog.querySelector('.hotel-photo-viewer__stage');let gesture=null;
 stage.addEventListener('pointerdown',event=>{gesture=event.isPrimary&&event.pointerType==='touch'?{id:event.pointerId,x:event.clientX,y:event.clientY}:null;});
 stage.addEventListener('pointercancel',()=>{gesture=null;});
 stage.addEventListener('pointerup',event=>{
  const start=gesture;gesture=null;if(!start||start.id!==event.pointerId||!photoSession)return;
  const dx=event.clientX-start.x,dy=event.clientY-start.y;
  if(Math.abs(dx)>=50&&Math.abs(dx)>Math.abs(dy)*1.5)showPhoto(photoSession.index+(dx<0?1:-1));
 });
 dialog.addEventListener('click',event=>{if(event.target===dialog){const box=dialog.getBoundingClientRect();if(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom)closePhotoViewer();}});
 dialog.addEventListener('cancel',event=>{event.preventDefault();closePhotoViewer();});
 // Native close is queued. Do not let an older close event clear a reopened viewer.
 dialog.addEventListener('close',()=>{if(!dialog.open){gesture=null;finishPhotoViewer();}});
 document.body.appendChild(dialog);return dialog;
}
function openPhotoViewer(link,gallery){
 if(!active()||!supportsPhotoDialog())return false;
 // Snapshot only photos already rendered for this hotel. Navigation never mutates
 // the renderer's gallery, trip parameters or result state and makes no API calls.
 const urls=Array.from(gallery.querySelectorAll('img.hotel-gallery-main, .hotel-gallery-thumb img')).map(image=>String(image.getAttribute('src')||'')).filter(safePhotoUrl);
 const unique=Array.from(new Set(urls));if(!unique.length||unique[0]!==link.getAttribute('href'))return false;
 if(!photoViewer)photoViewer=createPhotoViewer();
 const card=gallery.closest('.hotel-card');
 photoSession={link,urls:unique,index:0,name:text(card.querySelector('.hotel-title'))||'Отель'};
 photoViewer.querySelector('h2').textContent=photoSession.name;
 photoViewer.querySelectorAll('[data-photo-step]').forEach(button=>{button.hidden=unique.length<2;});
 try{photoViewer.showModal();}catch(error){photoSession=null;return false;}
 document.documentElement.classList.add('hotel-photo-viewer-open');showPhoto(0);return true;
}
// Keep the exact displayed image as a native fallback; do not invent a larger URL.
function normalizeHotelPhotoLink(card){
 if(!active()||!card||typeof card.querySelector!=='function'||!/^[1-9][0-9]*$/.test(String(card.dataset&&card.dataset.anytourHotelId||'')))return false;
 const gallery=card.querySelector('.hotel-gallery'),main=gallery&&gallery.querySelector('img.hotel-gallery-main');
 if(!gallery)return false;
 let link=gallery.querySelector('a.search3-hotel-photo-link');
 const href=String(main&&main.getAttribute('src')||'');
 if(!safePhotoUrl(href)){if(link){link.parentNode.removeChild(link);return true;}return false;}
 let changed=false;
 if(!link){
  link=document.createElement('a');link.className='search3-hotel-photo-link';
  // Cover the existing photo without moving its image or the z-index:2 thumbnails.
  link.setAttribute('style','position:absolute;inset:0;z-index:1;outline-offset:-3px');
  gallery.appendChild(link);changed=true;
 }
 const enhanced=supportsPhotoDialog();
 const attributes={href,target:'_blank',rel:'noopener noreferrer','aria-label':String(main.getAttribute('alt')||'Фото отеля')+(enhanced?' — смотреть фотографии':' — открыть в новой вкладке'),title:enhanced?'Смотреть фотографии':'Открыть фото в новой вкладке'};
 if(enhanced)attributes['aria-haspopup']='dialog';
 Object.keys(attributes).forEach(key=>{if(link.getAttribute(key)!==attributes[key]){link.setAttribute(key,attributes[key]);changed=true;}});
 return changed;
}
function resetPresentation(){
 if(!active())return;
 closePhotoViewer();
 restoreTitle();
 const root=document.getElementById('results');
 if(root&&typeof root.querySelectorAll==='function')root.querySelectorAll('.hotel-title a.search3-hotel-title-link').forEach(unwrapTitleLink);
}
function normalizeCard(card){
 if(!active()||!card||typeof card.querySelector!=='function')return false;
 let changed=normalizeHotelRating(card);
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
 if(photoSession&&!photoSession.link.isConnected)closePhotoViewer();
 let changed=0;root.querySelectorAll('.hotel-card').forEach(card=>{if(normalizeCard(card))changed++;});syncHotelPageTitle(root);return changed;
}
window.addEventListener('v2:results-rendered',event=>normalize(event.detail&&event.detail.results||document.getElementById('results')));
window.addEventListener('v2:hotel-details-rendered',event=>{const root=document.getElementById('results'),id=String(event.detail&&event.detail.hotelId||'');if(!root||!id)return;const card=Array.from(root.querySelectorAll('.hotel-card')).find(node=>String(node.dataset&&node.dataset.hotelId||'')===id);if(card)normalizeCard(card);});
// Bubble after the renderer's capture-phase thumbnail swap, before native navigation.
['click','auxclick','contextmenu','focusin'].forEach(name=>window.addEventListener(name,event=>{
 const target=event.target,gallery=target&&typeof target.closest==='function'&&target.closest('#results .hotel-gallery');
 if(!gallery)return;
 normalizeHotelPhotoLink(gallery.closest('.hotel-card'));
 const link=target.closest('a.search3-hotel-photo-link');
 if(name==='click'&&link&&!event.defaultPrevented&&event.button===0&&!event.ctrlKey&&!event.metaKey&&!event.shiftKey&&!event.altKey&&openPhotoViewer(link,gallery))event.preventDefault();
}));
['v2:search-started','v2:search-reset'].forEach(name=>window.addEventListener(name,resetPresentation));
window.Search3HotelDetailsPresentationV1={active,syncHotelPageTitle,normalizeHotelRating,hotelTitleHref,normalizeHotelTitleLink,normalizeHotelPhotoLink,normalizeCard,normalize,version:8};
})();
