<?php
require_once __DIR__ . '/phone-value.php';

// site_conf.php may expose PHONE as a nested Bitrix-style structure. Normalize it once
// in request scope before v2/index.php copies $params into its local site parameters.
if (isset($params) && is_array($params) && array_key_exists('PHONE', $params)) {
    $params['PHONE'] = v2_site_phone($params, '8 (800) 100 - 61 - 50');
}

/**
 * V2 owns the social/app pre-footer block. On the legacy host the surrounding site
 * supplies its global footer; standalone anytoour pages explicitly render theirs via
 * v2_render_standalone_canonical_footer() so the two environments never duplicate it.
 */
function v2_render_site_footer(string $phone, string $phoneHref): void
{
    ?>
    <section class="v2-site-community" aria-labelledby="v2-site-community-title">
      <div class="v2-site-community__inner">
        <div class="v2-site-community__copy">
          <span class="v2-site-community__eyebrow">AnyTour всегда рядом</span>
          <h2 id="v2-site-community-title">Подписывайтесь и берите поиск туров с собой</h2>
          <p>Каналы AnyTour — для идей и выгодных предложений. Приложение — чтобы искать туры с телефона.</p>
        </div>
        <div class="v2-site-community__actions">
          <div class="v2-site-community__socials" aria-label="Социальные сети AnyTour">
            <a href="https://max.ru/anytour" target="_blank" rel="noopener noreferrer" aria-label="AnyTour в MAX"><span class="v2-site-community__icon" aria-hidden="true"><svg viewBox="0 0 32 32" focusable="false"><rect x="3" y="3" width="26" height="26" rx="8" fill="currentColor"/><path d="M9 21V11h3.1l3.9 5.1 3.9-5.1H23v10h-3v-5.6L16 20.2l-4-4.8V21H9Z" fill="#fff"/></svg></span><span>MAX</span></a>
            <a href="https://t.me/+gGloLUt4d8s3NDcy" target="_blank" rel="noopener noreferrer" aria-label="AnyTour в Telegram"><span class="v2-site-community__icon" aria-hidden="true"><svg viewBox="0 0 32 32" focusable="false"><circle cx="16" cy="16" r="13" fill="currentColor"/><path d="m8.6 15.7 15-5.8c.7-.3 1.3.2 1 1l-2.6 12.3c-.2.9-.8 1.1-1.5.7l-4-3-1.9 1.9c-.2.2-.4.4-.8.4l.3-4.1 7.5-6.8c.3-.3-.1-.4-.5-.1l-9.3 5.8-4-1.3c-.9-.3-.9-.9.2-1.3Z" fill="#fff"/></svg></span><span>Telegram</span></a>
            <a href="https://vk.com/anytour_online" target="_blank" rel="noopener noreferrer" aria-label="AnyTour во ВКонтакте"><span class="v2-site-community__icon" aria-hidden="true"><svg viewBox="0 0 32 32" focusable="false"><rect x="3" y="3" width="26" height="26" rx="8" fill="currentColor"/><path d="M8.5 11h3.3c.3 4.2 2 6 3.3 6.4V11h3.1v3.7c1.3-.1 2.7-1.8 3.2-3.7h3.1c-.4 2.2-2.1 4-3.3 4.8 1.2.7 3.2 2.3 4 5.2h-3.4c-.6-1.8-2-3.1-3.6-3.3V21h-.4c-5.4 0-8.5-3.7-9.3-10Z" fill="#fff"/></svg></span><span>ВКонтакте</span></a>
          </div>
          <div class="v2-site-community__apps" aria-label="Приложение AnyTour">
            <a class="v2-site-community__app" href="https://apps.apple.com/ru/app/anytour-%D0%B3%D0%BE%D1%80%D1%8F%D1%89%D0%B8%D0%B5-%D1%82%D1%83%D1%80%D1%8B/id6753017465" target="_blank" rel="noopener noreferrer" aria-label="Скачать ANYTOUR в App Store"><span class="v2-site-community__store-icon" aria-hidden="true"><svg viewBox="0 0 32 32" focusable="false"><path d="M19.5 7.1c1.1-1.3 1-2.6 1-3.1-1.7.1-3.6 1.2-4.7 2.5-.9 1-1.6 2.3-1.5 3.6 1.8.1 3.5-.8 5.2-3Zm4.4 9.4c0-3.2 2.7-4.8 2.8-4.9-1.5-2.2-3.9-2.5-4.7-2.5-2-.2-3.9 1.2-4.9 1.2-1 0-2.6-1.1-4.3-1-2.2 0-4.3 1.3-5.4 3.3-2.3 4-.6 9.8 1.6 13 .9 1.5 2 3.2 3.5 3.1 1.4-.1 1.9-.9 3.7-.9 1.7 0 2.2.9 3.7.9 1.5 0 2.5-1.5 3.4-3 1-1.6 1.4-3.1 1.4-3.2-.1 0-2.8-1.1-2.8-5Z" fill="currentColor"/></svg></span><span><small>Скачать в</small><b>App Store</b></span></a>
            <a class="v2-site-community__app" href="https://play.google.com/store/apps/details?id=online.anytour" target="_blank" rel="noopener noreferrer" aria-label="Скачать ANYTOUR в Google Play"><span class="v2-site-community__store-icon" aria-hidden="true"><svg viewBox="0 0 32 32" focusable="false"><path d="M7 5.8v20.4l11.8-10.1L7 5.8Z" fill="currentColor"/><path d="m18.8 16.1 4-3.4-4.8-2.8-3.1 2.7 3.9 3.5Zm0 0-3.9 3.4 3.2 2.7 4.8-2.8-4.1-3.3Z" fill="currentColor" opacity=".78"/></svg></span><span><small>Доступно в</small><b>Google Play</b></span></a>
          </div>
        </div>
      </div>
    </section>
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
