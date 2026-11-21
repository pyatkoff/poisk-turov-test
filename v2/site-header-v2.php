<?php
require_once __DIR__ . '/phone-value.php';
require_once __DIR__ . '/asset-version-v1.php';
require_once __DIR__ . '/site-path-v1.php';

/** Emit the unchanged owner artwork before the browser parses the page body. */
function v2_render_site_favicon(): void {
    $version = rawurlencode(v2_asset_content_version(__DIR__ . '/favicon.svg'));
    echo '<link rel="icon" href="/favicon.ico?v='.$version.'" type="image/x-icon" sizes="64x64">';
    echo '<link rel="icon" href="/favicon.png?v='.$version.'" type="image/png" sizes="64x64">';
}

function v2_header_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function v2_header_path_is_active(string $activePath, string $href): bool {
    return $activePath === $href || ($href !== '/' && str_starts_with($activePath, $href));
}

function v2_render_site_header(string $phone, string $phoneHref, string $activePath = '', bool $search3 = false): void {
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
<?php if ($activePath !== '/' && $activePath !== '/poisk-turov/'): ?>
<script data-anytour-favicon-bootstrap>
(function(){
  var head=document.head;if(!head)return;
  var href='/favicon.php?v=20260903';
  head.querySelectorAll('link[rel~="icon"]').forEach(function(node){node.remove();});
  [['icon','image/png'],['shortcut icon','image/png']].forEach(function(spec){
    var link=document.createElement('link');link.rel=spec[0];link.type=spec[1];link.href=href;head.appendChild(link);
  });
})();
</script>
<?php endif; ?>
<header class="at-global-header<?=$search3?' header':''?>">
  <div class="at-global-header__inner">
    <a class="at-global-header__logo" href="<?=v2_header_e(v2_site_href('/'))?>" aria-label="AnyTour — на главную"><img src="/images/logo.svg" alt="AnyTour" width="224" height="61"></a>
    <nav class="at-global-header__nav" aria-label="Основное меню">
      <?php foreach ($nav as [$href, $label]): $isActive = v2_header_path_is_active($activePath, $href); ?>
        <?php if ($search3 && $href === '/poisk-turov/'): ?>
          <button type="button" data-action="top" aria-current="page"><?=v2_header_e($label)?></button>
        <?php else: ?>
          <a href="<?=v2_header_e(v2_site_href($href))?>"<?=$isActive?' aria-current="page"':''?>><?=v2_header_e($label)?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="at-global-header__actions">
      <a class="at-global-header__phone" href="tel:<?=v2_header_e($phoneHref)?>"><?=v2_header_e($phone)?></a>
      <?php if (!$search3): ?><a class="at-global-header__cta" href="<?=v2_header_e(v2_site_href('/poisk-turov/'))?>">Найти тур</a><?php endif; ?>
    </div>
    <details class="at-global-header__mobile">
      <summary aria-label="Открыть меню"><span>Меню</span><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></summary>
      <div class="at-global-header__mobile-panel">
        <?php foreach ($nav as [$href, $label]): $isActive = v2_header_path_is_active($activePath, $href); ?>
          <?php if ($search3 && $href === '/poisk-turov/'): ?>
            <button type="button" data-action="top" aria-current="page"><?=v2_header_e($label)?></button>
          <?php else: ?>
            <a href="<?=v2_header_e(v2_site_href($href))?>"<?=$isActive?' aria-current="page"':''?>><?=v2_header_e($label)?></a>
          <?php endif; ?>
        <?php endforeach; ?>
        <a class="at-global-header__mobile-phone" href="tel:<?=v2_header_e($phoneHref)?>"><?=v2_header_e($phone)?></a>
      </div>
    </details>
  </div>
</header>
<?php }
