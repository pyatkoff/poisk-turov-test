<?php
require_once __DIR__ . '/site-header-v2.php';
require_once __DIR__ . '/site-path-v1.php';
require_once __DIR__ . '/analytics-config.php';
$homeSiteParams = is_array($params ?? null) ? $params : [];
$homePhone = v2_site_phone($homeSiteParams, '8 (800) 100 - 61 - 50');
$homePhoneHref = v2_phone_href($homePhone);
$homeDescription = 'AnyTour — удобный поиск туров с актуальными ценами, перелётами и помощью менеджера. Начните с короткого поиска и сравните предложения туроператоров.';
$homeCanonical = 'https://anytoour.ru/';
$homeRobots = v2_seo_robots_content(v2_seo_indexable($homeSiteParams));
$homeMetrikaCounter = v2_metrika_counter_id();
$homeSchema = v2_seo_schema($homePhone, $homeDescription);
$homePrefix = v2_site_base_path();
if (!in_array((string)($_SERVER['SCRIPT_NAME'] ?? ''), [$homePrefix . '/', $homePrefix . '/index.php'], true)) {
    http_response_code(403);
    exit('The home form requires its exact home route.');
}
if (!defined('V2_VISUAL_ENTRY_ALLOWED')) define('V2_VISUAL_ENTRY_ALLOWED', true);
require_once __DIR__ . '/visual-search/entry-v1.php';
$homeSearch = null;
try {
    $homeBuild = v2_visual_search_public_build(__DIR__ . '/visual-search/public-build.json');
    $homeConfig = v2_visual_search_public_config($homeBuild, $homePrefix, 'home');
    $homeSearch = v2_visual_search_home_parts($homeConfig, $homeSiteParams);
} catch (Throwable $error) {
    http_response_code(503);
}
function home_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title>AnyTour — поиск и подбор туров онлайн</title>
  <meta name="description" content="<?=home_e($homeDescription)?>">
  <meta name="robots" content="<?=home_e($homeRobots)?>">
  <link rel="canonical" href="<?=$homeCanonical?>">
  <?php v2_render_site_favicon(); ?>
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="AnyTour">
  <meta property="og:title" content="AnyTour — поиск и подбор туров онлайн">
  <meta property="og:description" content="<?=home_e($homeDescription)?>">
  <meta property="og:url" content="<?=$homeCanonical?>">
  <meta property="og:locale" content="ru_RU">
  <script type="application/ld+json"><?=json_encode($homeSchema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?></script>
  <?= $homeSearch['head'] ?? '' ?>
  <link rel="stylesheet" href="<?=home_e(v2_asset('design-system-v2.css'))?>">
  <?php if ($homeSearch === null): ?><link rel="stylesheet" href="<?=home_e(v2_asset('site-header-v2.css'))?>"><?php endif; ?>
  <link rel="stylesheet" href="<?=home_e(v2_asset('home-v1.css'))?>">
  <link rel="stylesheet" href="<?=home_e(v2_asset('site-footer-v1.css'))?>">
</head>
<body class="at-home-page">
<a class="home-skip" href="#home-content">Перейти к содержанию</a>
<?php v2_render_site_header($homePhone, $homePhoneHref, '/'); ?>
  <main class="at-home-main home-content" id="home-content">
    <section class="home-hero" aria-labelledby="home-title">
      <div class="home-hero-copy">
        <p class="home-eyebrow">Пакетные туры · AnyTour</p>
        <h1 id="home-title">Ваш следующий<br>отдых — здесь</h1>
        <p class="home-lead">Море, новый город или отпуск всей семьёй. Выберите тур, сравните условия и продолжите с менеджером.</p>
      </div>
      <div class="home-hero-image">
        <picture>
          <source media="(max-width: 760px)" srcset="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7">
          <img src="<?=home_e(v2_asset('home-hero-v1.jpg'))?>" alt="Песчаный берег и море в мягком вечернем свете" width="1100" height="731" fetchpriority="high">
        </picture>
        <span>Пора сменить вид из окна</span>
      </div>
      <div class="home-search-surface">
        <?php if ($homeSearch !== null): ?>
          <?= $homeSearch['form'] ?>
        <?php else: ?>
          <p role="status">Форма сейчас недоступна. Откройте поиск, чтобы выбрать условия поездки.</p>
          <a class="primary home-search-link" href="<?=home_e(v2_site_href('/poisk-turov/'))?>">Открыть поиск туров</a>
        <?php endif; ?>
      </div>
    </section>

    <section class="home-section home-directions" id="countries" aria-labelledby="countries-title">
      <div class="home-section-heading"><div><p class="home-eyebrow">Начните с настроения</p><h2 id="countries-title">Куда хочется поехать?</h2></div><a class="home-text-link" href="<?=home_e(v2_site_href('/country/'))?>">Выбрать страну </a></div>
      <div class="home-direction-grid">
        <a class="home-direction home-direction-sea" href="<?=home_e(v2_site_href('/poisk-turov/?country=4'))?>"><span class="home-direction-number">Направление</span><h3>Турция</h3><p>Выберите курорт, питание и удобный перелёт.</p><span class="home-direction-action">Подобрать тур </span></a>
        <a class="home-direction home-direction-family" href="<?=home_e(v2_site_href('/poisk-turov/'))?>"><span class="home-direction-number">Семья</span><h3>Отдых вместе</h3><p>Укажите возраст детей и сравните варианты для всех.</p><span class="home-direction-action">Подобрать семейный тур </span></a>
        <a class="home-direction home-direction-choice" href="<?=home_e(v2_site_href('/poisk-turov/'))?>"><span class="home-direction-number">Ваш выбор</span><h3>Свои планы</h3><p>Подберите даты, питание и комфортный бюджет.</p><span class="home-direction-action">Перейти к поиску </span></a>
      </div>
    </section>

    <section class="home-season-grid" aria-label="Когда планировать отдых">
      <article class="home-season" id="hot"><span class="home-eyebrow">Скоро в отпуск</span><h2>Готовы уехать<br>в ближайшие дни?</h2><p>Если даты гибкие, сравните соседние вылеты и условия. Стоимость показывается за весь тур и всех туристов.</p><a class="home-text-link" href="<?=home_e(v2_site_href('/hot/'))?>">Проверить даты </a></article>
      <article class="home-season home-season-early" id="early"><span class="home-eyebrow">Планы заранее</span><h2>Время выбрать<br>свой отпуск</h2><p>Начните с удобных дат и состава семьи. Сохраните понравившийся вариант и обсудите детали с менеджером.</p><a class="home-text-link" href="<?=home_e(v2_site_href('/rb/'))?>">Спланировать поездку </a></article>
    </section>

    <section class="home-section" id="how" aria-labelledby="how-title">
      <div class="home-section-heading"><div><p class="home-eyebrow">От идеи к поездке</p><h2 id="how-title">Понятный путь к вашему туру</h2></div></div>
      <ol class="home-steps"><li><span>1</span><h3>Задайте условия</h3><p>Выберите направление, даты и всех туристов. Питание и категория отеля сразу перед вами.</p></li><li><span>2</span><h3>Сравните детали</h3><p>Проверьте номер, питание, перелёт и общую сумму в карточке выбранного тура.</p></li><li><span>3</span><h3>Продолжите в мессенджере</h3><p>Выберите Telegram или MAX. Менеджер поможет уточнить детали поездки.</p></li></ol>
    </section>

    <section class="home-contact" id="contacts" aria-labelledby="contact-title"><div><p class="home-eyebrow">Будем на связи</p><h2 id="contact-title">Сначала выберите тур.<br>Дальше — удобный вам мессенджер.</h2><p>Telegram и MAX доступны на шаге связи с менеджером.</p></div><a class="primary" href="<?=home_e(v2_site_href('/poisk-turov/'))?>">Выбрать тур </a></section>
  </main>
<?php v2_render_site_footer($homePhone, $homePhoneHref); ?>
<?= $homeSearch['support'] ?? '' ?>
</body>
</html>
