(function(){'use strict';
if(window.V2DirectOfferRetargeting)return;
window.dataLayer=Array.isArray(window.dataLayer)?window.dataLayer:[];
const seen=new Map();
function cleanId(v){return String(v==null?'':v).trim().slice(0,100);}
function offerId(hotelId){const id=cleanId(hotelId);return id?'hotel_'+id:'';}
function text(v,n){return String(v==null?'':v).trim().slice(0,n||160);}
function number(v){const n=Number(v||0);return Number.isFinite(n)&&n>0?n:undefined;}
function product(input){const p=input||{},id=offerId(p.hotelId||p.id);if(!id)return null;const out={id:id};const name=text(p.name||p.hotel,160);if(name)out.name=name;const price=number(p.price);if(price!==undefined)out.price=price;const country=text(p.country,80),region=text(p.region,80);if(country||region)out.category=[country,region].filter(Boolean).join('/');out.brand='AnyTour';return out;}
function pushDetail(input){const p=product(input);if(!p)return false;const now=Date.now(),last=seen.get(p.id)||0;if(now-last<1500)return false;seen.set(p.id,now);window.dataLayer.push({ecommerce:{currencyCode:'RUB',detail:{products:[p]}}});return true;}
window.addEventListener('v2:tour-selected',function(e){const t=e.detail&&e.detail.tour||{},h=t.hotel||{};pushDetail({hotelId:h.id,name:h.name||t.name,price:t.price,country:h.country&&h.country.name,region:h.region&&h.region.name});});
window.addEventListener('v2:analytics',function(e){const d=e.detail||{};if(d.name!=='hotel_open')return;const p=d.params||{};pushDetail({hotelId:p.hotelId,hotel:p.hotel,price:p.price,country:p.country,region:p.region});});
window.V2DirectOfferRetargeting={offerId:offerId,pushDetail:pushDetail,version:1};
})();
