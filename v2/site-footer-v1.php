<?php
require_once __DIR__ . '/phone-value.php';

if (isset($params) && is_array($params) && array_key_exists('PHONE', $params)) {
    $params['PHONE'] = v2_site_phone($params, '8 (800) 100 - 61 - 50');
}

/**
 * Canonical AnyTour DS2 footer for the search experience.
 * Visual presentation lives only in ds2-search.css.
 */
function v2_render_site_footer(string $phone, string $phoneHref): void
{
    ?>
    <footer class="ds2-site-footer">
      <div class="ds2-site-footer__inner">
        <div class="ds2-site-footer__brand">
          <a class="ds2-site-footer__logo" href="/" aria-label="AnyTour — главная">
            <img src="/images/logo.svg" alt="AnyTour">
          </a>
          <p>Поиск туров по всем туроператорам.<br>Подберём лучший вариант и поможем с отдыхом.</p>
          <div class="ds2-site-footer__socials" aria-label="Социальные сети AnyTour">
            <a href="https://t.me/+gGloLUt4d8s3NDcy" target="_blank" rel="noopener noreferrer">Telegram</a>
            <a href="https://vk.com/anytour_online" target="_blank" rel="noopener noreferrer">VK</a>
            <a href="https://max.ru/anytour" target="_blank" rel="noopener noreferrer">MAX</a>
          </div>
        </div>

        <nav class="ds2-site-footer__column" aria-label="Туры">
          <strong>Туры</strong>
          <a href="/poisk-turov/">Поиск туров</a>
          <a href="/hot/">Горящие туры <span>HOT</span></a>
          <a href="/country/">Страны</a>
          <a href="/hotels/">Отели</a>
        </nav>

        <nav class="ds2-site-footer__column" aria-label="Информация">
          <strong>Информация</strong>
          <a href="/how-to-buy/">Как купить тур</a>
          <a href="/about/">О нас</a>
          <a href="/contacts/">Контакты</a>
        </nav>

        <div class="ds2-site-footer__column ds2-site-footer__support">
          <strong>Поддержка</strong>
          <div><b>Поддержка 24/7</b><small>Мы всегда на связи</small></div>
          <div><b>Гарантия лучшей цены</b><small>Проверим предложение перед заявкой</small></div>
          <div><b>Моментальное подтверждение</b><small>Покажем доступные варианты</small></div>
        </div>

        <div class="ds2-site-footer__column ds2-site-footer__contacts">
          <strong>Контакты</strong>
          <a class="ds2-site-footer__phone" href="tel:<?=htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($phone, ENT_QUOTES, 'UTF-8')?></a>
          <small>Ежедневно 09:00–21:00</small>
          <a href="mailto:info@anytour.ru">info@anytour.ru</a>
        </div>
      </div>

      <div class="ds2-site-footer__meta">
        <div class="ds2-site-footer__payments" aria-label="Платёжные системы">
          <strong>Безопасная оплата</strong>
          <span>МИР</span><span>VISA</span><span>Mastercard</span><span>Apple Pay</span>
        </div>
        <div class="ds2-site-footer__apps">
          <div><strong>Скачайте приложение AnyTour</strong><small>Туры всегда под рукой</small></div>
          <a href="https://apps.apple.com/ru/app/anytour-%D0%B3%D0%BE%D1%80%D1%8F%D1%89%D0%B8%D0%B5-%D1%82%D1%83%D1%80%D1%8B/id6753017465" target="_blank" rel="noopener noreferrer">App Store</a>
          <a href="https://play.google.com/store/apps/details?id=online.anytour" target="_blank" rel="noopener noreferrer">Google Play</a>
        </div>
      </div>

      <div class="ds2-site-footer__bottom">
        <span>© 2026 AnyTour.ru — Все права защищены</span>
        <a href="/contacts/">Контакты</a>
      </div>
    </footer>
    <?php
    v2_render_web_consultant_widget();
}

function v2_render_web_consultant_widget(): void
{
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_replace('/:\d+$/', '', $host) ?: $host;
    if (!in_array($host, ['anytoour.ru', 'www.anytoour.ru'], true)) return;

    $base = 'https://anytour.online/max-search/web-consultant/';
    ?>
    <script src="<?=$base?>widget.js" defer data-anytour-webchat="1"></script>
    <script src="<?=$base?>widget-a11y.js" defer data-anytour-webchat-a11y="1"></script>
    <script src="<?=$base?>widget-context.js" defer data-anytour-webchat-context="1"></script>
    <?php
}

function v2_render_standalone_canonical_footer(): void
{
    $legacy = 'https://anytour.online';
    ?>
    <footer class="at-site-footer">
      <nav class="at-site-footer-menu" aria-label="Служебные ссылки">
        <ul>
          <li><a href="<?=$legacy?>/payment/">Оплата туров</a></li>
          <li><a href="<?=$legacy?>/personal-data/">Согласие на обработку персональных данных</a></li>
          <li><a href="<?=$legacy?>/politika-konfidentsialnosti/">Политика конфиденциальности</a></li>
        </ul>
      </nav>
      <p class="at-site-footer-copy">© 2026 «ТУРАГЕНТСТВО ANYTour» Москва | Все права защищены.</p>
      <span class="at-site-pay-icons" aria-label="Платёжные системы"><i>MasterCard</i><i>Visa</i><i>Мир</i></span>
    </footer>
    <?php
}
