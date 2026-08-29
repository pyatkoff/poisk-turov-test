<?php
require_once __DIR__ . '/phone-value.php';
if (isset($params) && is_array($params) && array_key_exists('PHONE', $params)) { $params['PHONE'] = v2_site_phone($params, '8 (800) 100 - 61 - 50'); }
function v2_render_site_footer(string $phone, string $phoneHref): void { ?>
<section class="v2-site-community" aria-labelledby="v2-site-community-title"><div class="v2-site-community__inner">
<div class="v2-site-community__copy"><span class="v2-site-community__eyebrow">AnyTour всегда рядом</span><h2 id="v2-site-community-title">Подписывайтесь и берите поиск туров с собой</h2><p>Каналы AnyTour — для идей и выгодных предложений. Приложение — чтобы искать туры с телефона.</p></div>
<div class="v2-site-community__actions"><div class="v2-site-community__socials" aria-label="Социальные сети AnyTour">
<a href="https://max.ru/anytour" target="_blank" rel="noopener noreferrer" aria-label="AnyTour в MAX"><span class="v2-site-community__icon" aria-hidden="true">M</span><span>MAX</span></a>
<a href="https://t.me/+gGloLUt4d8s3NDcy" target="_blank" rel="noopener noreferrer" aria-label="AnyTour в Telegram"><span class="v2-site-community__icon" aria-hidden="true">➤</span><span>Telegram</span></a>
<a href="https://vk.com/anytour_online" target="_blank" rel="noopener noreferrer" aria-label="AnyTour во ВКонтакте"><span class="v2-site-community__icon" aria-hidden="true">VK</span><span>ВКонтакте</span></a>
</div><div class="v2-site-community__apps" aria-label="Приложение AnyTour">
<a class="v2-site-community__app" href="https://apps.apple.com/ru/app/anytour-%D0%B3%D0%BE%D1%80%D1%8F%D1%89%D0%B8%D0%B5-%D1%82%D1%83%D1%80%D1%8B/id6753017465" target="_blank" rel="noopener noreferrer" aria-label="Скачать ANYTOUR в App Store"><span class="v2-site-community__store-icon" aria-hidden="true">●</span><span><small>Скачать в</small><b>App Store</b></span></a>
<a class="v2-site-community__app" href="https://play.google.com/store/apps/details?id=online.anytour" target="_blank" rel="noopener noreferrer" aria-label="Скачать ANYTOUR в Google Play"><span class="v2-site-community__store-icon" aria-hidden="true">▶</span><span><small>Доступно в</small><b>Google Play</b></span></a>
</div></div></div></section><?php }
function v2_render_standalone_canonical_footer(): void { $legacy='https://anytour.online'; ?>
<footer class="at-site-footer"><nav class="at-site-footer-menu" aria-label="Служебные ссылки"><ul><li><a href="<?=$legacy?>/payment/">Оплата туров</a></li><li><a href="<?=$legacy?>/personal-data/">Согласие на обработку персональных данных</a></li><li><a href="<?=$legacy?>/politika-konfidentsialnosti/">Политика конфиденциальности</a></li></ul></nav><p class="at-site-footer-copy">© 2026 «ТУРАГЕНТСТВО ANYTour» Москва | Все права защищены.</p><span class="at-site-pay-icons" aria-label="Платёжные системы"><i>MasterCard</i><i>Visa</i><i>Мир</i></span></footer><?php }
