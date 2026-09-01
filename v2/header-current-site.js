(function(){'use strict';
var cfg=window.V2_CONFIG||{},apiPath=String(cfg.api||''),runtimeBase=apiPath.replace(/\/api-v2\.php(?:\?.*)?$/,'').replace(/\/$/,'');
function runtimePath(file){return (runtimeBase||'')+'/'+String(file||'').replace(/^\//,'');}
var standalone=String(window.location.hostname||'').toLowerCase()==='anytoour.ru',legacyOrigin=standalone?'https://anytour.online':'';
var standalonePaths=['/poisk-turov/','/country/','/hot/','/rb/','/contacts/','/how-to-buy/'];
function siteHref(path){if(standalone&&standalonePaths.indexOf(path)!==-1)return path;return legacyOrigin+path;}
function addHeaderStyles(){if(document.querySelector('link[data-at-header-v2-css]'))return;var css=document.createElement('link');css.rel='stylesheet';css.href=runtimePath('site-header-v2.css');css.setAttribute('data-at-header-v2-css','1');document.head.appendChild(css);}
function isActive(path){var current=String(window.location.pathname||'/');return current===path||(path!=='/'&&current.indexOf(path)===0);}
function makeLink(path,label,className){var a=document.createElement('a');a.href=siteHref(path);a.textContent=label;if(className)a.className=className;if(isActive(path))a.setAttribute('aria-current','page');return a;}
function upgradeHeader(){
  if(document.querySelector('.at-global-header'))return;
  var old=document.querySelector('.at-site-header');if(!old)return;
  addHeaderStyles();
  var oldLogo=old.querySelector('.at-site-logo img'),oldPhone=old.querySelector('.at-site-phone');
  var phoneText=oldPhone&&oldPhone.textContent?oldPhone.textContent.trim():'8 (800) 100-61-50';
  var phoneHref=oldPhone&&oldPhone.getAttribute('href')?oldPhone.getAttribute('href'):'tel:88001006150';
  var nav=[['/poisk-turov/','Поиск туров'],['/country/','Страны'],['/hot/','Горящие туры'],['/rb/','Раннее бронирование'],['/how-to-buy/','Как купить'],['/contacts/','Контакты']];
  var header=document.createElement('header');header.className='at-global-header';
  var inner=document.createElement('div');inner.className='at-global-header__inner';header.appendChild(inner);
  var logo=document.createElement('a');logo.className='at-global-header__logo';logo.href='/';logo.setAttribute('aria-label','AnyTour — на главную');
  var img=document.createElement('img');img.src=oldLogo&&oldLogo.getAttribute('src')?oldLogo.getAttribute('src'):'/images/logo.svg';img.alt='AnyTour';logo.appendChild(img);inner.appendChild(logo);
  var desktop=document.createElement('nav');desktop.className='at-global-header__nav';desktop.setAttribute('aria-label','Основное меню');nav.forEach(function(item){desktop.appendChild(makeLink(item[0],item[1],''));});inner.appendChild(desktop);
  var actions=document.createElement('div');actions.className='at-global-header__actions';
  var phone=document.createElement('a');phone.className='at-global-header__phone';phone.href=phoneHref;phone.textContent=phoneText;actions.appendChild(phone);actions.appendChild(makeLink('/poisk-turov/','Найти тур','at-global-header__cta'));inner.appendChild(actions);
  var mobile=document.createElement('details');mobile.className='at-global-header__mobile';
  var summary=document.createElement('summary');summary.setAttribute('aria-label','Открыть меню');for(var i=0;i<3;i++)summary.appendChild(document.createElement('span'));mobile.appendChild(summary);
  var panel=document.createElement('div');panel.className='at-global-header__mobile-panel';var mobilePhone=document.createElement('a');mobilePhone.className='at-global-header__mobile-phone';mobilePhone.href=phoneHref;mobilePhone.textContent=phoneText;panel.appendChild(mobilePhone);nav.forEach(function(item){panel.appendChild(makeLink(item[0],item[1],''));});mobile.appendChild(panel);inner.appendChild(mobile);
  old.replaceWith(header);
}
upgradeHeader();
if(standalone){Array.prototype.forEach.call(document.querySelectorAll('.at-global-header a[href^="/"]'),function(link){var href=link.getAttribute('href')||'';if(href&&href!=='/')link.setAttribute('href',siteHref(href));});}
var phones=document.querySelectorAll('.at-global-header__phone,.at-global-header__mobile-phone,.at-site-phone');
if(phones.length&&(!phones[0].textContent||phones[0].textContent.trim()==='Array')){
  fetch(runtimePath('phone-config.php'),{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.ok?r.json():null;}).then(function(d){if(!d||!d.phone)return;Array.prototype.forEach.call(phones,function(phone){phone.textContent=d.phone;if(d.href)phone.setAttribute('href','tel:'+d.href);});}).catch(function(){});
}
function groundBrandProof(){
  var hero=document.querySelector('.v2-product-hero');if(!hero)return;
  var eyebrow=hero.querySelector('.v2-product-hero__eyebrow'),copy=hero.querySelector('.v2-product-hero__content>p'),trust=hero.querySelector('.v2-product-hero__trust');
  if(eyebrow)eyebrow.textContent='AnyTour · онлайн и в 4 офисах';
  if(copy)copy.textContent='Сравнивайте актуальные предложения туроператоров по датам, отелям, питанию и перелёту. После выбора менеджер проверит цену, рейс и детали тура до оплаты.';
  if(trust){var labels=['4 офиса + онлайн','Договор до оплаты','Проверим цену и рейс'];trust.setAttribute('aria-label','Почему AnyTour');Array.prototype.forEach.call(trust.querySelectorAll('span'),function(node,index){if(labels[index])node.textContent=labels[index];});}
}
groundBrandProof();
if(!document.querySelector('link[data-at-footer-css]')){var css=document.createElement('link');css.rel='stylesheet';css.href=runtimePath('footer-current-site.css');css.setAttribute('data-at-footer-css','1');document.head.appendChild(css);}
if(!document.querySelector('.at-site-footer')){var footer=document.createElement('footer');footer.className='at-site-footer';footer.innerHTML='<nav class="at-site-footer-menu" aria-label="Служебные ссылки"><ul><li><a href="'+siteHref('/payment/')+'">Оплата туров</a></li><li><a href="'+siteHref('/personal-data/')+'">Согласие на обработку персональных данных</a></li><li><a href="'+siteHref('/politika-konfidentsialnosti/')+'">Политика конфиденциальности</a></li></ul></nav><p class="at-site-footer-copy">© 2026 «ТУРАГЕНТСТВО ANYTour» Москва | Все права защищены.</p><span class="at-site-pay-icons" aria-label="Платёжные системы"><i class="mastercard" title="MasterCard"></i><i class="visa" title="Visa"></i><i class="mir" title="Мир"></i></span>';document.body.appendChild(footer);}
window.V2BrandProof={apply:groundBrandProof,version:2};
var b=document.querySelector('.at-mobile-menu-button'),m=document.querySelector('.at-mobile-menu');if(!b||!m)return;function close(){m.classList.remove('is-open');b.setAttribute('aria-expanded','false');}b.addEventListener('click',function(e){e.preventDefault();var open=m.classList.toggle('is-open');b.setAttribute('aria-expanded',open?'true':'false');});document.addEventListener('click',function(e){if(!m.classList.contains('is-open'))return;if(e.target.closest('.at-mobile-menu-wrap'))return;close();});document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});m.addEventListener('click',function(e){if(e.target.closest('a'))close();});})();