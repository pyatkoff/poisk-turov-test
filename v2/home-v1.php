<?php
$homeSiteParams = is_array($params ?? null) ? $params : [];
$homeForm = v2_form_defaults($_GET, $homeSiteParams);
$homePhone = trim((string)($homeSiteParams['PHONE'] ?? '8 (800) 100 - 61 - 50'));
$homePhoneHref = preg_replace('/[^0-9+]/', '', $homePhone);
$homeDescription = 'AnyTour — удобный поиск туров с актуальными ценами, перелётами и помощью менеджера. Начните с короткого поиска и сравните предложения туроператоров.';
$homeCanonical = 'https://anytoour.ru/';
$homeRobots = v2_seo_robots_content(v2_seo_indexable($homeSiteParams));
$homeSchema = v2_seo_schema($homePhone, $homeDescription);
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
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="AnyTour">
  <meta property="og:title" content="AnyTour — поиск и подбор туров онлайн">
  <meta property="og:description" content="<?=home_e($homeDescription)?>">
  <meta property="og:url" content="<?=$homeCanonical?>">
  <meta property="og:locale" content="ru_RU">
  <script type="application/ld+json"><?=json_encode($homeSchema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?></script>
  <link rel="stylesheet" href="<?=home_e(v2_asset('home-v1.css'))?>">
  <link rel="stylesheet" href="<?=home_e(v2_asset('site-footer-v1.css'))?>">
</head>
<body>
<header class="at-home-header">
  <div class="at-home-header__inner">
    <a class="at-home-logo" href="/" aria-label="AnyTour"><img src="/images/logo.svg" alt="AnyTour"></a>
    <nav class="at-home-nav" aria-label="Основное меню">
      <a href="/poisk-turov/">Поиск туров</a>
      <a href="/country/">Страны</a>
      <a href="/hot/">Горящие туры</a>
      <a href="/contacts/">Контакты</a>
    </nav>
    <a class="at-home-phone" href="tel:<?=home_e($homePhoneHref)?>"><?=home_e($homePhone)?></a>
    <a class="at-home-cta" href="/poisk-turov/">Найти тур</a>
  </div>
</header>
<main class="at-home-main">
  <section class="at-home-hero">
    <div class="at-home-hero__inner">
      <span class="at-home-kicker">AnyTour · путешествия без лишней сложности</span>
      <h1>Найдите отдых, который подходит именно вам</h1>
      <p>Сравниваем актуальные туры по датам, отелям, питанию и перелёту. Начните с нескольких параметров — полный поиск откроется уже с вашими условиями.</p>
    </div>
  </section>

  <form class="at-home-search" action="/poisk-turov/" method="get" autocomplete="off" data-home-search>
    <div class="at-home-search__grid">
      <label class="at-home-field"><span>Вылет из</span><select name="from" data-home-departures required><option value="<?=home_e($homeForm['from'])?>">Загружаем города…</option></select></label>
      <label class="at-home-field"><span>Страна</span><select name="country" data-home-countries required><option value="<?=home_e($homeForm['country'])?>">Загружаем страны…</option></select></label>
      <label class="at-home-field"><span>Вылет с</span><input type="date" name="dateFrom" value="<?=home_e($homeForm['date_from'])?>" required></label>
      <label class="at-home-field"><span>Вылет до</span><input type="date" name="dateTo" value="<?=home_e($homeForm['date_till'])?>" required></label>
      <label class="at-home-field"><span>Ночей</span><input type="number" name="daysFrom" min="1" max="28" value="<?=home_e($homeForm['nights_from'])?>" required><input type="hidden" name="daysTill" value="<?=home_e($homeForm['nights_till'])?>"></label>
      <label class="at-home-field"><span>Взрослых</span><select name="count_people"><?php for($i=1;$i<=6;$i++): ?><option value="<?=$i?>" <?=$i===(int)$homeForm['count_people']?'selected':''?>><?=$i?></option><?php endfor; ?></select></label>
      <button type="submit">Найти туры</button>
    </div>
    <a class="at-home-search__more" href="/poisk-turov/">Расширенный поиск и все фильтры →</a>
  </form>

  <section class="at-home-section">
    <div class="at-home-section__head"><h2>Поиск без сюрпризов</h2><p>Полный поисковик показывает не только цену отеля, но и конкретные варианты тура, перелёт, питание и итоговую стоимость перед заявкой.</p></div>
    <div class="at-home-benefits">
      <article class="at-home-benefit"><b>Актуальные предложения</b><p>Поиск получает доступные варианты напрямую и помогает сравнивать условия, а не только рекламную цену.</p></article>
      <article class="at-home-benefit"><b>Проверка конкретного тура</b><p>Перед заявкой можно открыть выбранный вариант и проверить детали рейса, багажа и размещения.</p></article>
      <article class="at-home-benefit"><b>Менеджер рядом</b><p>Если нужен совет, менеджер подключится уже с параметрами вашего поиска и выбранного предложения.</p></article>
    </div>
  </section>

  <section class="at-home-section">
    <div class="at-home-section__head"><h2>Разделы нового AnyTour</h2><p>Мы переносим текущие разделы сайта в единый новый дизайн. Поиск уже работает; страны, горящие туры и полезные страницы переносятся без потери старых маршрутов.</p></div>
    <div class="at-home-direction-grid">
      <a class="at-home-direction" href="/poisk-turov/"><strong>Поиск туров</strong><span>Полная форма, фильтры и актуальные предложения</span></a>
      <a class="at-home-direction" href="/country/"><strong>Страны и курорты</strong><span>Направления и будущие SEO-подборки</span></a>
      <a class="at-home-direction" href="/hot/"><strong>Горящие туры</strong><span>Раздел текущего сайта будет перенесён сюда</span></a>
      <a class="at-home-direction" href="/how-to-buy/"><strong>Как купить тур</strong><span>Понятный путь от выбора до бронирования</span></a>
    </div>
  </section>
</main>
<?php v2_render_site_footer($homePhone, $homePhoneHref); ?>
<script>
(function(){
  const dep=document.querySelector('[data-home-departures]'),country=document.querySelector('[data-home-countries]');
  if(!dep||!country)return;
  const initialDeparture=String(dep.value||'1'),initialCountry=String(country.value||'4');
  async function get(action,params){const u=new URL('/api-v2.php',location.origin);u.searchParams.set('action',action);Object.entries(params||{}).forEach(([k,v])=>u.searchParams.set(k,v));const r=await fetch(u,{credentials:'same-origin'});if(!r.ok)throw new Error('HTTP '+r.status);return r.json();}
  function options(select,items,wanted,placeholder){select.innerHTML='';(Array.isArray(items)?items:[]).forEach(item=>{const o=document.createElement('option');o.value=String(item.id);o.textContent=String(item.name||item.title||('ID '+item.id));select.appendChild(o);});if(wanted&&Array.from(select.options).some(o=>o.value===String(wanted)))select.value=String(wanted);if(!select.options.length){const o=document.createElement('option');o.value='';o.textContent=placeholder;select.appendChild(o);}}
  async function loadCountries(wanted){country.disabled=true;try{const list=await get('countries',{departureId:dep.value||1});options(country,list,wanted,'Страны не найдены');}catch(e){options(country,[],null,'Не удалось загрузить страны');}finally{country.disabled=false;}}
  get('departures').then(list=>{options(dep,list,initialDeparture,'Города не найдены');return loadCountries(initialCountry);}).catch(()=>{options(dep,[],null,'Не удалось загрузить города');});
  dep.addEventListener('change',()=>loadCountries(''));
})();
</script>
</body>
</html>
