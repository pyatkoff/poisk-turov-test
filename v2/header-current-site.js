(function(){'use strict';
var cfg=window.V2_CONFIG||{},apiPath=String(cfg.api||''),runtimeBase=apiPath.replace(/\/api-v2\.php(?:\?.*)?$/,'').replace(/\/$/,'');
function runtimePath(file){return (runtimeBase||'')+'/'+String(file||'').replace(/^\//,'');}
var standalone=String(window.location.hostname||'').toLowerCase()==='anytoour.ru',legacyOrigin=standalone?'https://anytour.online':'';
function siteHref(path){if(standalone&&path==='/poisk-turov/')return path;return legacyOrigin+path;}
function ensureStylesheet(href,attr){if(document.querySelector('link['+attr+']'))return;var css=document.createElement('link');css.rel='stylesheet';css.href=href;css.setAttribute(attr,'1');document.head.appendChild(css);}
if(standalone){Array.prototype.forEach.call(document.querySelectorAll('.at-site-header a[href^="/"]'),function(link){var href=link.getAttribute('href')||'';if(href&&href!=='/')link.setAttribute('href',siteHref(href));});}
var phone=document.querySelector('.at-site-phone');
if(phone&&(!phone.textContent||phone.textContent.trim()==='Array')){
  fetch(runtimePath('phone-config.php'),{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.ok?r.json():null;}).then(function(d){if(!d||!d.phone)return;phone.textContent=d.phone;if(d.href)phone.setAttribute('href','tel:'+d.href);}).catch(function(){});
}
function headerLink(path,label,active){var href=siteHref(path);return '<a href="'+href+'"'+(active?' aria-current="page"':'')+'>'+label+'</a>';}
function upgradeHeader(){
  var old=document.querySelector('.at-site-header');if(!old||document.querySelector('.at-global-header'))return;
  ensureStylesheet(runtimePath('site-header-v2.css'),'data-at-header-v2-css');
  var logo=old.querySelector('.at-site-logo img'),phoneLink=old.querySelector('.at-site-phone'),personal=old.querySelector('.at-site-personal');
  var logoSrc=logo&&logo.getAttribute('src')?logo.getAttribute('src'):'/images/logo.svg';
  var phoneText=phoneLink&&phoneLink.textContent?phoneLink.textContent.trim():'';
  var phoneHref=phoneLink&&phoneLink.getAttribute('href')?phoneLink.getAttribute('href'):'';
  var hasPhone=phoneText&&phoneText!=='Array'&&/^tel:/.test(phoneHref);
  var activePath=String(window.location.pathname||'/');
  var nav=[['/poisk-turov/','Поиск туров'],['/country/','Страны'],['/hot/','Горящие туры'],['/rb/','Раннее бронирование'],['/how-to-buy/','Как купить'],['/contacts/','Контакты']];
  var navHtml=nav.map(function(item){var active=activePath===item[0]||(item[0]!=='/'&&activePath.indexOf(item[0])===0);return headerLink(item[0],item[1],active);}).join('');
  var personalHtml=personal?'<a class="at-global-header__personal" href="'+personal.getAttribute('href')+'" target="_blank" rel="noopener">Личный кабинет</a>':'';
  var phoneHtml=hasPhone?'<a class="at-global-header__phone" href="'+phoneHref+'">'+phoneText+'</a>':'';
  var mobilePhone=hasPhone?'<a class="at-global-header__mobile-phone" href="'+phoneHref+'">'+phoneText+'</a>':'';
  var header=document.createElement('header');header.className='at-global-header';header.setAttribute('data-at-header-v2','');
  header.innerHTML='<div class="at-global-header__inner"><a class="at-global-header__logo" href="/" aria-label="AnyTour — на главную"><img src="'+logoSrc+'" alt="AnyTour"></a><nav class="at-global-header__nav" aria-label="Основное меню">'+navHtml+'</nav><div class="at-global-header__actions">'+personalHtml+phoneHtml+'</div><details class="at-global-header__mobile"><summary aria-label="Открыть меню"><span></span><span></span><span></span></summary><div class="at-global-header__mobile-panel">'+mobilePhone+personalHtml+navHtml+'</div></details></div>';
  old.replaceWith(header);
}
upgradeHeader();
function groundBrandProof(){
  var hero=document.querySelector('.v2-product-hero');if(!hero)return;
  var eyebrow=hero.querySelector('.v2-product-hero__eyebrow'),copy=hero.querySelector('.v2-product-hero__content>p'),trust=hero.querySelector('.v2-product-hero__trust');
  if(eyebrow)eyebrow.textContent='AnyTour · онлайн и в 4 офисах';
  if(copy)copy.textContent='Сравнивайте актуальные предложения туроператоров по датам, отелям, питанию и перелёту. После выбора менеджер проверит цену, рейс и детали тура до оплаты.';
  if(trust){var labels=['4 офиса + онлайн','Договор до оплаты','Проверим цену и рейс'];trust.setAttribute('aria-label','Почему AnyTour');Array.prototype.forEach.call(trust.querySelectorAll('span'),function(node,index){if(labels[index])node.textContent=labels[index];});}
}
groundBrandProof();
ensureStylesheet(runtimePath('footer-current-site.css'),'data-at-footer-css');
if(!document.querySelector('.at-site-footer')){var footer=document.createElement('footer');footer.className='at-site-footer';footer.innerHTML='<nav class="at-site-footer-menu" aria-label="Служебные ссылки"><ul><li><a href="'+siteHref('/payment/')+'">Оплата туров</a></li><li><a href="'+siteHref('/personal-data/')+'">Согласие на обработку персональных данных</a></li><li><a href="'+siteHref('/politika-konfidentsialnosti/')+'">Политика конфиденциальности</a></li></ul></nav><p class="at-site-footer-copy">© 2026 «ТУРАГЕНТСТВО ANYTour» Москва | Все права защищены.</p><span class="at-site-pay-icons" aria-label="Платёжные системы"><i class="mastercard" title="MasterCard"></i><i class="visa" title="Visa"></i><i class="mir" title="Мир"></i></span>';document.body.appendChild(footer);}
window.V2BrandProof={apply:groundBrandProof,version:2};
})();