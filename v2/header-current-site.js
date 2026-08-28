(function(){'use strict';
var phone=document.querySelector('.at-site-phone');
if(phone&&(!phone.textContent||phone.textContent.trim()==='Array')){
  fetch('/poisk-turov-test/v2/phone-config.php',{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.ok?r.json():null;}).then(function(d){if(!d||!d.phone)return;phone.textContent=d.phone;if(d.href)phone.setAttribute('href','tel:'+d.href);}).catch(function(){});
}
function groundBrandProof(){
  var hero=document.querySelector('.v2-product-hero');if(!hero)return;
  var eyebrow=hero.querySelector('.v2-product-hero__eyebrow'),copy=hero.querySelector('.v2-product-hero__content>p'),trust=hero.querySelector('.v2-product-hero__trust');
  if(eyebrow)eyebrow.textContent='AnyTour · онлайн и в 4 офисах';
  if(copy)copy.textContent='Сравнивайте актуальные предложения туроператоров по датам, отелям, питанию и перелёту. После выбора менеджер проверит цену, рейс и детали тура до оплаты.';
  if(trust){var labels=['4 офиса + онлайн','Договор до оплаты','Проверим цену и рейс'];trust.setAttribute('aria-label','Почему AnyTour');Array.prototype.forEach.call(trust.querySelectorAll('span'),function(node,index){if(labels[index])node.textContent=labels[index];});}
}
groundBrandProof();
if(!document.querySelector('link[data-at-footer-css]')){var css=document.createElement('link');css.rel='stylesheet';css.href='/poisk-turov-test/v2/footer-current-site.css';css.setAttribute('data-at-footer-css','1');document.head.appendChild(css);}
if(!document.querySelector('.at-site-footer')){var footer=document.createElement('footer');footer.className='at-site-footer';footer.innerHTML='<nav class="at-site-footer-menu" aria-label="Служебные ссылки"><ul><li><a href="/payment/">Оплата туров</a></li><li><a href="/personal-data/">Согласие на обработку персональных данных</a></li><li><a href="/politika-konfidentsialnosti/">Политика конфиденциальности</a></li></ul></nav><p class="at-site-footer-copy">© 2026 «ТУРАГЕНТСТВО ANYTour» Москва | Все права защищены.</p><span class="at-site-pay-icons" aria-label="Платёжные системы"><i class="mastercard" title="MasterCard"></i><i class="visa" title="Visa"></i><i class="mir" title="Мир"></i></span>';document.body.appendChild(footer);}
window.V2BrandProof={apply:groundBrandProof,version:1};
var b=document.querySelector('.at-mobile-menu-button'),m=document.querySelector('.at-mobile-menu');if(!b||!m)return;function close(){m.classList.remove('is-open');b.setAttribute('aria-expanded','false');}b.addEventListener('click',function(e){e.preventDefault();var open=m.classList.toggle('is-open');b.setAttribute('aria-expanded',open?'true':'false');});document.addEventListener('click',function(e){if(!m.classList.contains('is-open'))return;if(e.target.closest('.at-mobile-menu-wrap'))return;close();});document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});m.addEventListener('click',function(e){if(e.target.closest('a'))close();});})();