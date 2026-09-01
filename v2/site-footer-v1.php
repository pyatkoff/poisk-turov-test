<?php
require_once __DIR__ . '/phone-value.php';

if (isset($params) && is_array($params) && array_key_exists('PHONE', $params)) {
    $params['PHONE'] = v2_site_phone($params, '8 (800) 100 - 61 - 50');
}

/** Canonical factual AnyTour DS2 footer; primary mobile community/app actions keep a 44px tap target. */
function v2_render_site_footer(string $phone, string $phoneHref): void
{
    $legacy = 'https://anytour.online';
    $mobileActionStyle = 'min-height:44px;display:inline-flex;align-items:center;';
    ?>
    <footer class="ds2-site-footer">
      <div class="ds2-site-footer__inner">
        <div class="ds2-site-footer__brand">
          <a class="ds2-site-footer__logo" href="/" aria-label="AnyTour — главная">
            <img src="/images/logo.svg" alt="AnyTour">
          </a>
          <span class="ds2-site-footer__eyebrow">AnyTour всегда рядом</span>
          <strong>Подписывайтесь и берите поиск туров с собой</strong>
          <p>Каналы AnyTour — для идей и выгодных предложений. Приложение — чтобы искать туры с телефона.</p>
          <div class="ds2-site-footer__socials" aria-label="Социальные сети AnyTour">
            <a href="https://max.ru/anytour" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>">MAX</a>
            <a href="https://t.me/+gGloLUt4d8s3NDcy" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>">Telegram</a>
            <a href="https://vk.com/anytour_online" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>">VK</a>
          </div>
        </div>

        <div class="ds2-site-footer__apps" aria-label="Приложение AnyTour">
          <div><strong>Приложение AnyTour</strong><small>Поиск туров с телефона</small></div>
          <a href="https://apps.apple.com/ru/app/anytour-%D0%B3%D0%BE%D1%80%D1%8F%D1%89%D0%B8%D0%B5-%D1%82%D1%83%D1%80%D1%8B/id6753017465" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>">App Store</a>
          <a href="https://play.google.com/store/apps/details?id=online.anytour" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>">Google Play</a>
        </div>
      </div>

      <div class="ds2-site-footer__meta">
        <nav class="ds2-site-footer__legal" aria-label="Служебные ссылки">
          <a href="<?=$legacy?>/payment/">Оплата туров</a>
          <a href="<?=$legacy?>/personal-data/">Согласие на обработку персональных данных</a>
          <a href="<?=$legacy?>/politika-konfidentsialnosti/">Политика конфиденциальности</a>
        </nav>
        <div class="ds2-site-footer__payments" aria-label="Платёжные системы">
          <span>MasterCard</span><span>Visa</span><span>Мир</span>
        </div>
      </div>

      <div class="ds2-site-footer__bottom">
        <span>© 2026 «ТУРАГЕНТСТВО ANYTour» Москва | Все права защищены.</span>
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
