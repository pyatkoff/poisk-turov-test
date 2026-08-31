(function(){'use strict';
if(window.V2HotelPhotoFallbackV1)return;
function fallback(img){if(!img||img.dataset.v2PhotoFallback==='1')return false;const box=img.closest&&img.closest('.hotel-photo');if(!box)return false;img.dataset.v2PhotoFallback='1';if(!box.querySelector('.photo-placeholder')){const p=document.createElement('div');p.className='photo-placeholder';p.textContent='ANYTOUR';box.insertBefore(p,box.firstChild);}img.remove();return true;}
function scan(root){(root||document).querySelectorAll('.hotel-photo img').forEach(img=>{if(img.complete&&Number(img.naturalWidth||0)===0)fallback(img);});}
document.addEventListener('error',e=>{const target=e.target;if(target&&target.matches&&target.matches('.hotel-photo img'))fallback(target);},true);
window.addEventListener('v2:results-rendered',e=>scan(e.detail&&e.detail.results||document));
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>scan(document),{once:true});else scan(document);
window.V2HotelPhotoFallbackV1={fallback,scan,version:1};
})();
