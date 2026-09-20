/* Failed photos release their media area; pending lazy images remain intact. */
(function(){'use strict';
if(!document.body?.classList.contains('search3-candidate')||window.Search3PhotoRecoveryV1)return;
const failedPhotos=new WeakMap();
function recover(image){
 if(!image||!image.complete||image.naturalWidth!==0||typeof image.closest!=='function')return;
 const gallery=image.closest('#results .hotel-gallery');if(!gallery)return;
 const failed=failedPhotos.get(gallery)||new Set();failed.add(String(image.getAttribute('src')||''));failedPhotos.set(gallery,failed);
 const main=gallery.querySelector('img.hotel-gallery-main');
 gallery.querySelectorAll('.hotel-gallery-thumb').forEach(button=>{const photo=button.querySelector('img');if(!photo||failed.has(String(photo.getAttribute('src')||'')))button.remove();});
 if(image!==main)return;
 // These URLs came from the renderer's validated gallery for this exact hotel.
 const next=Array.from(gallery.querySelectorAll('.hotel-gallery-thumb img')).find(photo=>photo.getAttribute('src')&&!failed.has(photo.getAttribute('src')));
 if(next){const src=next.getAttribute('src');next.closest('.hotel-gallery-thumb').remove();main.setAttribute('src',src);gallery.querySelector('.search3-hotel-photo-link')?.setAttribute('href',src);return;}
 gallery.querySelectorAll('.hotel-gallery-thumbs,.search3-hotel-photo-link').forEach(node=>node.remove());
 const placeholder=document.createElement('div');placeholder.className='photo-placeholder';placeholder.textContent='Фото недоступно';main.replaceWith(placeholder);gallery.classList.remove('hotel-gallery');
}
function normalize(){document.querySelectorAll('#results .hotel-gallery img').forEach(recover)}
window.addEventListener('error',event=>{if(event.target?.tagName==='IMG')recover(event.target)},true);
window.addEventListener('v2:results-rendered',normalize);
window.addEventListener('v2:hotel-details-rendered',normalize);
window.Search3PhotoRecoveryV1={normalize};normalize();
})();
