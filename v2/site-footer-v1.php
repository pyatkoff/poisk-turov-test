<?php
require_once __DIR__ . '/phone-value.php';
require_once __DIR__ . '/site-path-v1.php';

if (isset($params) && is_array($params) && array_key_exists('PHONE', $params)) {
    $params['PHONE'] = v2_site_phone($params, '8 (800) 100 - 61 - 50');
}

/** Canonical factual AnyTour DS2 footer; primary mobile community/app actions keep a 44px tap target. */
function v2_render_site_footer(string $phone, string $phoneHref): void
{
    $mobileActionStyle = 'min-height:44px;display:inline-flex;align-items:center;';
    ?>
    <footer class="ds2-site-footer" data-site-footer="shared" data-search3-footer="1">
      <div class="ds2-site-footer__inner">
        <div class="ds2-site-footer__brand">
          <a class="ds2-site-footer__logo" href="<?=htmlspecialchars(v2_site_href('/'), ENT_QUOTES, 'UTF-8')?>" aria-label="AnyTour — главная">
            <img src="/images/logo.svg" alt="AnyTour">
          </a>
          <strong>AnyTour всегда рядом</strong>
          <p>Каналы AnyTour — для идей и выгодных предложений. Приложение — чтобы искать туры с телефона.</p>
          <div class="ds2-site-footer__socials" aria-label="Социальные сети AnyTour">
            <a href="https://max.ru/anytour" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>">MAX</a>
            <a href="https://t.me/+gGloLUt4d8s3NDcy" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>">Telegram</a>
            <a href="https://vk.com/anytour_online" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>">VK</a>
          </div>
        </div>

        <nav class="ds2-site-footer__navigation" aria-label="Навигация в подвале">
          <div><strong>Туры</strong>
            <a href="<?=htmlspecialchars(v2_site_href('/poisk-turov/'), ENT_QUOTES, 'UTF-8')?>">Поиск туров</a>
            <a href="<?=htmlspecialchars(v2_site_href('/country/'), ENT_QUOTES, 'UTF-8')?>">Страны</a>
            <a href="<?=htmlspecialchars(v2_site_href('/hot/'), ENT_QUOTES, 'UTF-8')?>">Горящие туры</a>
            <a href="<?=htmlspecialchars(v2_site_href('/rb/'), ENT_QUOTES, 'UTF-8')?>">Раннее бронирование</a>
          </div>
          <div><strong>О компании</strong>
            <a href="<?=htmlspecialchars(v2_site_href('/how-to-buy/'), ENT_QUOTES, 'UTF-8')?>">Как купить</a>
            <a href="<?=htmlspecialchars(v2_site_href('/contacts/'), ENT_QUOTES, 'UTF-8')?>">Контакты</a>
          </div>
        </nav>
        <div class="ds2-site-footer__support">
          <strong>Свяжитесь с нами</strong>
          <a href="tel:<?=htmlspecialchars($phoneHref, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($phone, ENT_QUOTES, 'UTF-8')?></a>
        </div>
        <div class="ds2-site-footer__apps" aria-label="Приложение AnyTour">
          <div><strong>Приложение AnyTour</strong><small>Поиск туров с телефона</small></div>
          <a href="https://apps.apple.com/ru/app/anytour-%D0%B3%D0%BE%D1%80%D1%8F%D1%89%D0%B8%D0%B5-%D1%82%D1%83%D1%80%D1%8B/id6753017465" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>">App Store</a>
          <a href="https://play.google.com/store/apps/details?id=online.anytour" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>">Google Play</a>
        </div>
      </div>

      <div class="ds2-site-footer__meta">
        <div class="ds2-site-footer__payments" aria-label="Платёжные системы">
          <span>MasterCard</span><span>Visa</span><span>Мир</span>
        </div>
      </div>

      <div class="ds2-site-footer__bottom">
        <span>© 2026 «ТУРАГЕНТСТВО ANYTour» Москва | Все права защищены.</span>
      </div>
    </footer>
    <?php
}

function v2_render_standalone_canonical_footer(): void
{
    ?>
    <footer class="at-site-footer">
      <p class="at-site-footer-copy">© 2026 «ТУРАГЕНТСТВО ANYTour» Москва | Все права защищены.</p>
      <span class="at-site-pay-icons" aria-label="Платёжные системы"><i>MasterCard</i><i>Visa</i><i>Мир</i></span>
    </footer>
    <?php
}
