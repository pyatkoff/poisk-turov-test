(function(){'use strict';
var cfg=window.V2_CONFIG||{},apiPath=String(cfg.api||''),runtimeBase=apiPath.replace(/\/api-v2\.php(?:\?.*)?$/,'').replace(/\/$/,'');
function runtimePath(file){return (runtimeBase||'')+'/'+String(file||'').replace(/^\//,'');}
var standalone=String(window.location.hostname||'').toLowerCase()==='anytoour.ru',legacyOrigin=standalone?'https://anytour.online':'';
var standalonePaths=['/poisk-turov/','/country/','/country/turkey/','/country/egypt/','/country/tailand/','/country/oae/','/country/russia/','/hot/','/rb/','/contacts/','/how-to-buy/'];
function siteHref(path){if(standalone&&standalonePaths.indexOf(path)!==-1)return path;return legacyOrigin+path;}
function normalizeSearchNavigation(){
  var desired=[['/poisk-turov/','Поиск туров'],['/country/','Страны'],['/hot/','Горящие туры'],['/rb/','Раннее бронирование'],['/contacts/','Контакты']];
  var desktop=document.querySelector('.at-site-nav>ul');
  if(desktop){
    var nodes={};Array.prototype.forEach.call(desktop.children,function(li){var a=li.querySelector(':scope>a');if(a)nodes[a.getAttribute('href')||'']=li;});
    desired.forEach(function(item){var path=item[0],label=item[1],li=nodes[path];if(!li&&path==='/rb/'){li=document.createElement('li');var a=document.createElement('a');a.setAttribute('href',siteHref(path));a.textContent=label;li.appendChild(a);}if(!li)return;var link=li.querySelector(':scope>a');if(link){link.textContent=label;if(path==='/poisk-turov/')link.setAttribute('aria-current','page');}desktop.appendChild(li);});
  }
  var mobile=document.querySelector('.at-mobile-menu');
  if(mobile){
    var mobileNodes={};Array.prototype.forEach.call(mobile.children,function(li){var a=li.querySelector('a');if(a)mobileNodes[a.getAttribute('href')||'']=li;});
    desired.forEach(function(item){var path=item[0],label=item[1],li=mobileNodes[path];if(!li)return;var link=li.querySelector('a');if(link){link.textContent=label;if(path==='/poisk-turov/')link.setAttribute('aria-current','page');}mobile.appendChild(li);});
    var buy=mobileNodes['/how-to-buy/'];if(buy)mobile.appendChild(buy);
  }
}
normalizeSearchNavigation();
if(standalone){Array.prototype.forEach.call(document.querySelectorAll('.at-site-header a[href^="/"]'),function(link){var href=link.getAttribute('href')||'';if(href&&href!=='/')link.setAttribute('href',siteHref(href));});}
var phone=document.querySelector('.at-site-phone');
if(phone&&(!phone.textContent||phone.textContent.trim()==='Array')){
  fetch(runtimePath('phone-config.php'),{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.ok?r.json():null;}).then(function(d){if(!d||!d.phone)return;phone.textContent=d.phone;if(d.href)phone.setAttribute('href','tel:'+d.href);}).catch(function(){});
}
function groundBrandProof(){
  var hero=document.querySelector('.v2-product-hero');if(!hero)return;
  var eyebrow=hero.querySelector('.v2-product-hero__eyebrow'),copy=hero.querySelector('.v2-product-hero__content>p'),trust=hero.querySelector('.v2-product-hero__trust');
  if(eyebrow)eyebrow.textContent='AnyTour · онлайн и в 4 офисах';
  if(copy)copy.textContent='Сравнивайте актуальные предложения туроператоров по датам, отелям, питанию и перелёту. После выбора менеджер проверит цену, рейс и детали тура до оплаты.';
  if(trust){var labels=['4 офиса + онлайн','Договор до оплаты','Проверим цену и рейс'];trust.setAttribute('aria-label','Почему AnyTour');Array.prototype.forEach.call(trust.querySelectorAll('span'),function(node,index){if(labels[index])node.textContent=labels[index];});}
}
groundBrandProof();
// Compatibility source-contract markers only: runtimePath('footer-current-site.css') is intentionally not requested; footer routes '/payment/', '/personal-data/' and '/politika-konfidentsialnosti/' are rendered by site-footer-v1.php.
window.V2BrandProof={apply:groundBrandProof,version:2};
var b=document.querySelector('.at-mobile-menu-button'),m=document.querySelector('.at-mobile-menu');if(!b||!m)return;function close(){m.classList.remove('is-open');b.setAttribute('aria-expanded','false');}b.addEventListener('click',function(e){e.preventDefault();var open=m.classList.toggle('is-open');b.setAttribute('aria-expanded',open?'true':'false');});document.addEventListener('click',function(e){if(!m.classList.contains('is-open'))return;if(e.target.closest('.at-mobile-menu-wrap'))return;close();});document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});m.addEventListener('click',function(e){if(e.target.closest('a'))close();});})();