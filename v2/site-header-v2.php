<?php
require_once __DIR__ . '/phone-value.php';

function v2_header_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function v2_header_path_is_active(string $activePath, string $href): bool {
    return $activePath === $href || ($href !== '/' && str_starts_with($activePath, $href));
}

function v2_render_site_header(string $phone, string $phoneHref, string $activePath = ''): void {
    $phone = trim($phone);
    if ($phone === '' || $phone === 'Array') $phone = '8 (800) 100-61-50';
    $phoneHref = trim($phoneHref);
    if ($phoneHref === '') $phoneHref = v2_phone_href($phone);
    $activePath = '/' . trim($activePath, '/') . ($activePath === '/' ? '' : '/');
    if ($activePath === '//') $activePath = '/';

    $nav = [
        ['/poisk-turov/', 'Поиск туров'],
        ['/country/', 'Страны'],
        ['/hot/', 'Горящие туры'],
        ['/rb/', 'Раннее бронирование'],
        ['/how-to-buy/', 'Как купить'],
        ['/contacts/', 'Контакты'],
    ];
    ?>
<header class="at-global-header">
  <div class="at-global-header__inner">
    <a class="at-global-header__logo" href="/" aria-label="AnyTour — на главную"><img src="/images/logo.svg" alt="AnyTour"></a>
    <nav class="at-global-header__nav" aria-label="Основное меню">
      <?php foreach ($nav as [$href, $label]): $isActive = v2_header_path_is_active($activePath, $href); ?>
        <a href="<?=v2_header_e($href)?>"<?=$isActive?' aria-current="page"':''?>><?=v2_header_e($label)?></a>
      <?php endforeach; ?>
    </nav>
    <div class="at-global-header__actions">
      <a class="at-global-header__phone" href="tel:<?=v2_header_e($phoneHref)?>"><?=v2_header_e($phone)?></a>
      <a class="at-global-header__cta" href="/poisk-turov/">Найти тур</a>
    </div>
    <details class="at-global-header__mobile">
      <summary aria-label="Открыть меню"><span></span><span></span><span></span></summary>
      <div class="at-global-header__mobile-panel">
        <a class="at-global-header__mobile-phone" href="tel:<?=v2_header_e($phoneHref)?>"><?=v2_header_e($phone)?></a>
        <?php foreach ($nav as [$href, $label]): $isActive = v2_header_path_is_active($activePath, $href); ?>
          <a href="<?=v2_header_e($href)?>"<?=$isActive?' aria-current="page"':''?>><?=v2_header_e($label)?></a>
        <?php endforeach; ?>
      </div>
    </details>
  </div>
</header>
<?php }
