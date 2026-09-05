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
            <a href="https://max.ru/anytour" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>"><span class="ds2-site-footer__social-icon ds2-site-footer__social-icon--max" aria-hidden="true"></span><span>MAX</span></a>
            <a href="https://t.me/+gGloLUt4d8s3NDcy" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>"><span class="ds2-site-footer__social-icon ds2-site-footer__social-icon--telegram" aria-hidden="true"><svg viewBox="0 0 32 32" focusable="false"><circle cx="16" cy="16" r="13" fill="currentColor"/><path d="m8.6 15.7 15-5.8c.7-.3 1.3.2 1 1l-2.6 12.3c-.2.9-.8 1.1-1.5.7l-4-3-1.9 1.9c-.2.2-.4.4-.8.4l.3-4.1 7.5-6.8c.3-.3-.1-.4-.5-.1l-9.3 5.8-4-1.3c-.9-.3-.9-.9.2-1.3Z" fill="#fff"/></svg></span><span>Telegram</span></a>
            <a href="https://vk.com/anytour_online" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>"><span class="ds2-site-footer__social-icon ds2-site-footer__social-icon--vk" aria-hidden="true"><svg viewBox="0 0 32 32" focusable="false"><rect x="3" y="3" width="26" height="26" rx="8" fill="currentColor"/><path d="M8.5 11h3.3c.3 4.2 2 6 3.3 6.4V11h3.1v3.7c1.3-.1 2.7-1.8 3.2-3.7h3.1c-.4 2.2-2.1 4-3.3 4.8 1.2.7 3.2 2.3 4 5.2h-3.4c-.6-1.8-2-3.1-3.6-3.3V21h-.4c-5.4 0-8.5-3.7-9.3-10Z" fill="#fff"/></svg></span><span>VK</span></a>
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
          <a href="https://apps.apple.com/ru/app/anytour-%D0%B3%D0%BE%D1%80%D1%8F%D1%89%D0%B8%D0%B5-%D1%82%D1%83%D1%80%D1%8B/id6753017465" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>"><span class="ds2-site-footer__store-icon" aria-hidden="true"><svg viewBox="0 0 32 32" focusable="false"><path d="M19.5 7.1c1.1-1.3 1-2.6 1-3.1-1.7.1-3.6 1.2-4.7 2.5-.9 1-1.6 2.3-1.5 3.6 1.8.1 3.5-.8 5.2-3Zm4.4 9.4c0-3.2 2.7-4.8 2.8-4.9-1.5-2.2-3.9-2.5-4.7-2.5-2-.2-3.9 1.2-4.9 1.2-1 0-2.6-1.1-4.3-1-2.2 0-4.3 1.3-5.4 3.3-2.3 4-.6 9.8 1.6 13 .9 1.5 2 3.2 3.5 3.1 1.4-.1 1.9-.9 3.7-.9 1.7 0 2.2.9 3.7.9 1.5 0 2.5-1.5 3.4-3 1-1.6 1.4-3.1 1.4-3.2-.1 0-2.8-1.1-2.8-5Z" fill="currentColor"/></svg></span><span class="ds2-site-footer__store-label"><small>Скачать в</small><b>App Store</b></span></a>
          <a href="https://play.google.com/store/apps/details?id=online.anytour" target="_blank" rel="noopener noreferrer" style="<?=$mobileActionStyle?>"><span class="ds2-site-footer__store-icon" aria-hidden="true"><svg viewBox="0 0 32 32" focusable="false"><path d="M7 5.8v20.4l11.8-10.1L7 5.8Z" fill="#fff"/><path d="m18.8 16.1 4-3.4-4.8-2.8-3.1 2.7 3.9 3.5Z" fill="#fff" opacity=".75"/><path d="m18.8 16.1-3.9 3.4 3.2 2.7 4.8-2.8-4.1-3.3Z" fill="#fff" opacity=".9"/><path d="m22.8 12.7 2.4 1.4c1 .6 1 1.4 0 2l-2.3 1.3-4.1-1.3 4-3.4Z" fill="#fff" opacity=".6"/></svg></span><span class="ds2-site-footer__store-label"><small>Доступно в</small><b>Google Play</b></span></a>
        </div>
      </div>

      <div class="ds2-site-footer__bottom">
        <span>© 2026 «ТУРАГЕНТСТВО ANYTour» Москва | Все права защищены.</span>
        <div class="ds2-site-footer__payments" aria-label="Платёжные системы">
          <span>MasterCard</span><span>Visa</span><span>Мир</span>
        </div>
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
