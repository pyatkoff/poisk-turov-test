<?php
require_once __DIR__ . '/phone-value.php';

/** Reusable V2/public-page footer shell. External destinations below are explicit verified AnyTour properties. */
function v2_render_site_footer(string $phone, string $phoneHref): void
{
    $phone = v2_phone_scalar($phone);
    if ($phone === '') $phone = '8 (800) 100-61-50';
    $phoneHref = v2_phone_href($phone);
    ?>
    <footer class="v2-site-footer" aria-label="Информация AnyTour">
      <div class="v2-site-footer__inner">
        <div class="v2-site-footer__brand">
          <a class="v2-site-footer__logo" href="/" aria-label="AnyTour — на главную"><img src="/images/logo.svg" alt="AnyTour"></a>
          <p>Поиск и бронирование туров с поддержкой менеджера до вылета и во время отдыха.</p>
          <a class="v2-site-footer__phone" href="tel:<?=htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($phone, ENT_QUOTES, 'UTF-8')?></a>
        </div>
        <nav class="v2-site-footer__nav" aria-label="Разделы AnyTour">
          <div><strong>Выбрать отдых</strong><a href="/country/">Туры по странам</a><a href="/poisk-turov/">Поиск туров</a><a href="/hot/">Горящие туры</a><a href="/rb/">Раннее бронирование</a></div>
          <div><strong>Покупка и помощь</strong><a href="/how-to-buy/">Как купить тур онлайн</a><a href="/contacts/">Контакты и офисы</a><a href="/personal/" target="_blank" rel="noopener">Личный кабинет</a></div>
          <div class="v2-site-footer__community">
            <strong>AnyTour всегда рядом</strong>
            <div class="v2-site-footer__socials" aria-label="Социальные сети AnyTour">
              <a href="https://max.ru/anytour" target="_blank" rel="noopener noreferrer" aria-label="AnyTour в MAX"><span>MAX</span></a>
              <a href="https://t.me/+gGloLUt4d8s3NDcy" target="_blank" rel="noopener noreferrer" aria-label="AnyTour в Telegram"><span>Telegram</span></a>
              <a href="https://vk.com/anytour_online" target="_blank" rel="noopener noreferrer" aria-label="AnyTour во ВКонтакте"><span>VK</span></a>
            </div>
            <div class="v2-site-footer__apps" aria-label="Приложение AnyTour">
              <a class="v2-site-footer__app" href="https://apps.apple.com/ru/app/anytour-%D0%B3%D0%BE%D1%80%D1%8F%D1%89%D0%B8%D0%B5-%D1%82%D1%83%D1%80%D1%8B/id6753017465" target="_blank" rel="noopener noreferrer" aria-label="Скачать ANYTOUR в App Store"><small>Скачать в</small><b>App Store</b></a>
              <a class="v2-site-footer__app" href="https://play.google.com/store/apps/details?id=online.anytour" target="_blank" rel="noopener noreferrer" aria-label="Скачать ANYTOUR в Google Play"><small>Скачать в</small><b>Google Play</b></a>
            </div>
          </div>
        </nav>
      </div>
      <div class="v2-site-footer__bottom"><span>AnyTour</span><span>Актуальность цены и условий тура подтверждает менеджер перед оплатой.</span></div>
    </footer>
    <?php
}
