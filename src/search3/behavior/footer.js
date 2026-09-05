/* donor:search3-footer-preview.js @ e5baf32f455cdb0aa1a704964f28e5efbebf57ff */
(function(){'use strict';
/* Donor CSS is bundled into the isolated candidate asset. */
var footer=document.querySelector('.ds2-site-footer');if(!footer||footer.dataset.search3Footer==='1')return;footer.dataset.search3Footer='1';
footer.style.setProperty('background','#0b1324','important');footer.style.setProperty('background-color','#0b1324','important');footer.style.setProperty('color','#fff','important');
function esc(v){return String(v==null?'':v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function href(selector){var a=document.querySelector(selector);return a&&a.href?a.href:'';}
function headerLink(pattern){var list=Array.from(document.querySelectorAll('.at-global-header__nav a'));var a=list.find(function(x){return pattern.test(String(x.textContent||''));});return a&&a.href?a.href:'';}
var logo=footer.querySelector('.ds2-site-footer__logo'),logoImage=logo&&logo.querySelector('img'),logoHref=logo&&logo.href?logo.href:'/',logoSrc=logoImage&&logoImage.getAttribute('src')?logoImage.getAttribute('src'):'/images/logo.svg',logoAlt=logoImage&&logoImage.alt?logoImage.alt:'AnyTour';
var phone=document.querySelector('.at-global-header__phone'),phoneText=phone?String(phone.textContent||'').trim():'',phoneHref=phone&&phone.href?phone.href:'';
var socials={max:href('.ds2-site-footer__socials a:nth-child(1)'),tg:href('.ds2-site-footer__socials a:nth-child(2)'),vk:href('.ds2-site-footer__socials a:nth-child(3)')};
var apps={ios:href('.ds2-site-footer__apps>a:nth-of-type(1)'),android:href('.ds2-site-footer__apps>a:nth-of-type(2)')};
var legal=Array.from(document.querySelectorAll('.ds2-site-footer__legal a')).map(function(a){return{href:a.href,text:String(a.textContent||'').trim()};});
function legalHref(rx){var x=legal.find(function(a){return rx.test(a.text);});return x?x.href:'';}
function link(hrefValue,label){return hrefValue?'<a href="'+esc(hrefValue)+'">'+esc(label)+'</a>':'';}
function externalLink(hrefValue,label,icon){return hrefValue?'<a href="'+esc(hrefValue)+'" target="_blank" rel="noopener noreferrer"><b>'+esc(icon)+'</b><span>'+esc(label)+'</span></a>':'';}
function appLink(hrefValue,label,icon){return hrefValue?'<a href="'+esc(hrefValue)+'" target="_blank" rel="noopener noreferrer">'+esc(icon)+' <b>'+esc(label)+'</b></a>':'';}
function group(title,links,extraClass){var items=links.filter(Boolean);return items.length?'<details class="search3-footer-group'+(extraClass?' '+extraClass:'')+'" open><summary>'+esc(title)+'</summary><div>'+items.join('')+'</div></details>':'';}
var tours=[link(headerLink(/^Поиск туров/i),'Поиск туров'),link(headerLink(/Горящие туры/i),'Горящие туры'),link(headerLink(/^Страны/i),'Страны')];
var useful=[link(headerLink(/Как купить/i),'Как это работает'),link(legalHref(/Политика конфиденциальности/i),'Политика конфиденциальности')];
var company=[link(headerLink(/Контакты/i),'Контакты')];
var phoneLink=link(phoneHref,phoneText),mobileSupport=[phoneLink,''];
var socialLinks=[externalLink(socials.max,'MAX','◎'),externalLink(socials.tg,'Telegram','➤'),externalLink(socials.vk,'VK','VK')].filter(Boolean).join('');
var appLinks=[appLink(apps.ios,'App Store',''),appLink(apps.android,'Google Play','▶')].filter(Boolean).join('');
footer.innerHTML='<div class="search3-footer-main"><div class="search3-footer-brand"><a class="search3-footer-logo" href="'+esc(logoHref)+'"><img src="'+esc(logoSrc)+'" alt="'+esc(logoAlt)+'"></a><strong>AnyTour всегда рядом</strong><p>Каналы AnyTour — для идей и выгодных предложений. Приложение — чтобы искать туры с телефона.</p><div class="search3-footer-socials">'+socialLinks+'</div></div><div class="search3-footer-nav">'+group('Туры',tours)+group('Полезная информация',useful)+group('О компании',company)+group('Поддержка',mobileSupport,'search3-footer-support-mobile')+'</div><div class="search3-footer-support search3-footer-support-desktop"><strong>Поддержка</strong><span>Свяжитесь с нами</span>'+phoneLink+'</div></div><div class="search3-footer-benefits"><div class="search3-footer-apps"><strong>Мобильные приложения</strong><span>Установите и ищите туры ещё удобнее</span><div>'+appLinks+'</div></div><div class="search3-footer-benefit"><div><strong>Актуальные предложения</strong><span>Сравнивайте доступные варианты и условия тура.</span></div></div><div class="search3-footer-benefit"><div><strong>Проверка конкретного тура</strong><span>Проверьте детали рейса, багажа и размещения.</span></div></div><div class="search3-footer-benefit"><div><strong>Цена до заявки</strong><span>Стоимость выбранного варианта видна до передачи контактов.</span></div></div><div class="search3-footer-benefit"><div><strong>Менеджер рядом</strong><span>Помощь с учётом параметров поиска и выбранного предложения.</span></div></div></div><div class="search3-footer-bottom"><span>© 2026 AnyTour — Все права защищены</span></div>';
function syncGroups(){var mobile=matchMedia('(max-width:640px)').matches;footer.querySelectorAll('.search3-footer-group').forEach(function(d){d.open=!mobile;});var mobileSupportEl=footer.querySelector('.search3-footer-support-mobile');var desktopSupportEl=footer.querySelector('.search3-footer-support-desktop');if(mobileSupportEl)mobileSupportEl.style.setProperty('display',mobile?'block':'none','important');if(desktopSupportEl)desktopSupportEl.style.setProperty('display',mobile?'none':'grid','important');}
syncGroups();window.addEventListener('resize',syncGroups);
})();


