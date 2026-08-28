<?php
/** Reusable V2/public-page footer shell. Keep destinations to verified first-party routes only. */
function v2_render_site_footer(string $phone, string $phoneHref): void
{
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
        </nav>
      </div>
      <div class="v2-site-footer__bottom"><span>AnyTour</span><span>Актуальность цены и условий тура подтверждает менеджер перед оплатой.</span></div>
    </footer>
    <?php
}
