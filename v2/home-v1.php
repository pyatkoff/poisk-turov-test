<?php
require_once __DIR__ . '/site-header-v2.php';
require_once __DIR__ . '/site-path-v1.php';
$homeSiteParams = is_array($params ?? null) ? $params : [];
$homeForm = v2_form_defaults($_GET, $homeSiteParams);
$homePhone = v2_site_phone($homeSiteParams, '8 (800) 100 - 61 - 50');
$homePhoneHref = v2_phone_href($homePhone);
$homeDescription = 'AnyTour — удобный поиск туров с актуальными ценами, перелётами и помощью менеджера. Начните с короткого поиска и сравните предложения туроператоров.';
$homeCanonical = 'https://anytoour.ru/';
$homeRobots = v2_seo_robots_content(v2_seo_indexable($homeSiteParams));
$homeSchema = v2_seo_schema($homePhone, $homeDescription);
$homeLegacyBase = v2_site_base_path();
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
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="AnyTour">
  <meta property="og:title" content="AnyTour — поиск и подбор туров онлайн">
  <meta property="og:description" content="<?=home_e($homeDescription)?>">
  <meta property="og:url" content="<?=$homeCanonical?>">
  <meta property="og:locale" content="ru_RU">
  <script type="application/ld+json"><?=json_encode($homeSchema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?></script>
  <link rel="stylesheet" href="<?=home_e(v2_asset('design-system-v2.css'))?>">
  <link rel="stylesheet" href="<?=home_e(v2_asset('site-header-v2.css'))?>">
  <link rel="stylesheet" href="<?=home_e(v2_asset('home-v1.css'))?>">
  <link rel="stylesheet" href="<?=home_e(v2_asset('home-journey-v1.css'))?>">
  <link rel="stylesheet" href="<?=home_e(v2_asset('home-design-system-alignment-v1.css'))?>">
  <link rel="stylesheet" href="<?=home_e(v2_asset('site-coherence-v1.css'))?>">
  <link rel="stylesheet" href="<?=home_e(v2_asset('site-footer-v1.css'))?>">
</head>
<body>
<?php v2_render_site_header($homePhone, $homePhoneHref, '/'); ?>
<main class="at-home-main">
  <section class="at-home-hero">
    <div class="at-home-hero__inner">
      <div class="at-home-hero__copy">
        <span class="at-home-kicker">AnyTour · путешествия без лишней сложности</span>
        <h1>Путешествия, которые делают вас счастливее</h1>
        <p>Сравнивайте актуальные туры и выбирайте отдых по своим условиям. AnyTour помогает быстро найти подходящий вариант и проверить детали до заявки.</p>
      </div>
      <div class="at-home-journey" aria-hidden="true">
        <div class="at-home-journey__eyebrow">Путь к подходящему туру</div>
        <div class="at-home-journey__step"><span>01</span><b>Задайте параметры</b></div>
        <div class="at-home-journey__step"><span>02</span><b>Сравните варианты</b></div>
        <div class="at-home-journey__step"><span>03</span><b>Проверьте детали</b></div>
      </div>
    </div>
  </section>

  <form class="at-home-search" action="<?=home_e(v2_site_href('/poisk-turov/'))?>" method="get" autocomplete="off" data-home-search data-countries-busy="true" aria-busy="true">
    <div class="at-home-search__grid">
      <label class="at-home-field"><span>Вылет из</span><select name="from" data-home-departures required><option value="<?=home_e($homeForm['from'])?>">Загружаем города…</option></select></label>
      <label class="at-home-field"><span>Страна</span><select name="country" data-home-countries required disabled><option value="<?=home_e($homeForm['country'])?>">Загружаем страны…</option></select></label>
      <label class="at-home-field"><span>Вылет с</span><input type="date" name="dateFrom" value="<?=home_e($homeForm['date_from'])?>" required></label>
      <label class="at-home-field"><span>Вылет до</span><input type="date" name="dateTo" aria-describedby="home-range-feedback" value="<?=home_e($homeForm['date_till'])?>" required></label>
      <label class="at-home-field"><span>Ночей от</span><select name="daysFrom" required><?php for($i=1;$i<=28;$i++): ?><option value="<?=$i?>" <?=$i===(int)$homeForm['nights_from']?'selected':''?>><?=$i?></option><?php endfor; ?></select></label>
      <label class="at-home-field"><span>Ночей до</span><select name="daysTill" aria-describedby="home-range-feedback" required><?php for($i=1;$i<=28;$i++): ?><option value="<?=$i?>" <?=$i===(int)$homeForm['nights_till']?'selected':''?>><?=$i?></option><?php endfor; ?></select></label>
      <label class="at-home-field"><span>Взрослых</span><select name="count_people"><?php for($i=1;$i<=6;$i++): ?><option value="<?=$i?>" <?=$i===(int)$homeForm['count_people']?'selected':''?>><?=$i?></option><?php endfor; ?></select></label>
      <label class="at-home-field"><span>Детей</span><select data-home-children><?php for($i=0;$i<=3;$i++): ?><option value="<?=$i?>" <?=$i===count($homeForm['child_ages'])?'selected':''?>><?=$i===0?'Без детей':$i?></option><?php endfor; ?></select></label>
      <div class="at-home-child-ages" data-home-child-ages></div>
      <button type="submit" disabled>Найти туры</button>
    </div>
    <p id="home-range-feedback" data-home-range-feedback role="status" aria-atomic="true" hidden></p>
    <p data-home-catalog-error role="alert" hidden></p>
    <button type="button" data-home-catalog-retry hidden>Повторить загрузку</button>
    <a class="at-home-search__more" href="<?=home_e(v2_site_href('/poisk-turov/'))?>" aria-disabled="true" tabindex="-1">Расширенный поиск и все фильтры →</a>
  </form>

  <section class="at-home-section at-home-section--discovery">
    <div class="at-home-section__head"><h2>Выберите, с чего начать</h2><p>Можно сразу искать по параметрам, открыть направление или перейти к сценарию поездки — горящему туру или раннему бронированию.</p></div>
    <div class="at-home-direction-grid">
      <a class="at-home-direction at-home-direction--primary" href="<?=home_e($homeLegacyBase)?>/country/"><strong>Страны и курорты</strong><span>Выберите направление и перейдите к актуальным турам</span></a>
      <a class="at-home-direction" href="<?=home_e($homeLegacyBase)?>/hot/"><strong>Горящие туры</strong><span>Поиск вариантов на ближайшие даты</span></a>
      <a class="at-home-direction" href="<?=home_e($homeLegacyBase)?>/rb/"><strong>Раннее бронирование</strong><span>Сравните варианты заранее без спешки</span></a>
      <a class="at-home-direction" href="<?=home_e(v2_site_href('/poisk-turov/'))?>"><strong>Полный поиск</strong><span>Все фильтры, отели, питание и актуальные предложения</span></a>
      <a class="at-home-direction" href="<?=home_e($homeLegacyBase)?>/how-to-buy/"><strong>Как купить тур</strong><span>Понятный путь от выбора до бронирования</span></a>
    </div>
  </section>

  <section class="at-home-section">
    <div class="at-home-section__head"><h2>Поиск без сюрпризов</h2><p>Полный поисковик показывает не только цену отеля, но и конкретные варианты тура, перелёт, питание и итоговую стоимость перед заявкой.</p></div>
    <div class="at-home-benefits">
      <article class="at-home-benefit"><b>Актуальные предложения</b><p>Поиск получает доступные варианты напрямую и помогает сравнивать условия, а не только рекламную цену.</p></article>
      <article class="at-home-benefit"><b>Проверка конкретного тура</b><p>Перед заявкой можно открыть выбранный вариант и проверить детали рейса, багажа и размещения.</p></article>
      <article class="at-home-benefit"><b>Цена до заявки</b><p>Итоговая стоимость выбранного варианта видна до передачи контактов менеджеру.</p></article>
      <article class="at-home-benefit"><b>Менеджер рядом</b><p>Если нужен совет, выберите конкретный тур и отправьте заявку — менеджер получит параметры выбранного предложения и сможет проверить условия.</p></article>
    </div>
  </section>
</main>
<?php v2_render_site_footer($homePhone, $homePhoneHref); ?>
<script>
(function(){
  const dep=document.querySelector('[data-home-departures]'),country=document.querySelector('[data-home-countries]');
  const form=document.querySelector('[data-home-search]');
  const childCount=form.querySelector('[data-home-children]'),childBox=form.querySelector('[data-home-child-ages]');
  const initialAges=<?=json_encode($homeForm['child_ages'])?>;
  function renderAges(){const previous=[...childBox.querySelectorAll('select')].map(el=>el.value);childBox.replaceChildren();childBox.hidden=Number(childCount.value)===0;
    for(let i=0;i<Number(childCount.value);i++){const label=document.createElement('label');label.className='at-home-field';const title=document.createElement('span');title.textContent='Возраст ребёнка '+(i+1);const select=document.createElement('select');select.name='child_age[]';select.required=true;select.setAttribute('aria-label',title.textContent);
      const placeholder=new Option('Выберите возраст','');select.add(placeholder);for(let age=0;age<=17;age++)select.add(new Option(age+' лет',String(age)));select.value=previous[i]??(initialAges[i]===undefined?'':String(initialAges[i]));label.append(title,select);childBox.append(label);
    }
  }
  childCount.addEventListener('change',renderAges);renderAges();
  const more=form.querySelector('.at-home-search__more');
  const submit=form.querySelector('button[type="submit"]');
  const catalogError=form.querySelector('[data-home-catalog-error]'),retry=form.querySelector('[data-home-catalog-retry]');
  let countriesBusy=true,countryRevision=0,failedCatalog='',retryCountry='';
  function setCountriesBusy(busy){countriesBusy=busy;form.dataset.countriesBusy=busy?'true':'false';form.setAttribute('aria-busy',busy?'true':'false');country.disabled=busy;submit.disabled=busy;retry.disabled=busy;more.setAttribute('aria-disabled',busy?'true':'false');if(busy)more.setAttribute('tabindex','-1');else more.removeAttribute('tabindex');}
  function showCatalogError(kind){failedCatalog=kind;catalogError.hidden=!kind;catalogError.textContent=kind?'Не удалось загрузить '+(kind==='departures'?'города вылета':'страны')+'. Повторите загрузку — параметры поездки сохранятся.':'';retry.hidden=!kind;}
  const syncMore=()=>{more.href=form.action+'?'+new URLSearchParams(new FormData(form)).toString();};
  const rangeFeedback=form.querySelector('[data-home-range-feedback]');
  const validateRange=()=>{
    const {dateFrom,dateTo,daysFrom,daysTill}=form.elements;
    dateTo.min=dateFrom.value;
    const dateError=dateTo.valueAsNumber<dateFrom.valueAsNumber?'«Вылет до» не может быть раньше «Вылет с».':'';
    const nightError=daysFrom.value&&daysTill.value&&Number(daysTill.value)<Number(daysFrom.value)?'Максимум ночей должен быть не меньше минимума.':'';
    for(const [field,message] of [[dateTo,dateError],[daysTill,nightError]]){
      field.setCustomValidity(message);
      if(message)field.setAttribute('aria-invalid','true');else field.removeAttribute('aria-invalid');
    }
    const message=[dateError,nightError].filter(Boolean).join(' ');
    if(rangeFeedback.textContent!==message)rangeFeedback.textContent=message;
    rangeFeedback.hidden=!message;
  };
  const syncForm=()=>{validateRange();syncMore();};
  form.addEventListener('input',syncForm);form.addEventListener('change',syncForm);
  form.addEventListener('submit',event=>{validateRange();if(countriesBusy||!form.reportValidity())event.preventDefault();});
  more.addEventListener('click',event=>{validateRange();if(countriesBusy||!form.reportValidity()){event.preventDefault();return;}syncMore();});
  validateRange();
  if(!dep||!country)return;
  const initialDeparture=String(dep.value||'1'),initialCountry=String(country.value||'4');
  async function get(action,params){const u=new URL('/api-v2.php',location.origin);u.searchParams.set('action',action);Object.entries(params||{}).forEach(([k,v])=>u.searchParams.set(k,v));const r=await fetch(u,{credentials:'same-origin'});if(!r.ok)throw new Error('HTTP '+r.status);return r.json();}
  function options(select,items,wanted,placeholder){select.innerHTML='';(Array.isArray(items)?items:[]).forEach(item=>{const o=document.createElement('option');o.value=String(item.id);o.textContent=String(item.name||item.title||('ID '+item.id));select.appendChild(o);});if(wanted&&Array.from(select.options).some(o=>o.value===String(wanted)))select.value=String(wanted);if(!select.options.length){const o=document.createElement('option');o.value='';o.textContent=placeholder;select.appendChild(o);}}
  async function loadCountries(wanted){const revision=++countryRevision;setCountriesBusy(true);try{const list=await get('countries',{departureId:dep.value||1});if(revision===countryRevision){options(country,list,wanted,'Страны не найдены');showCatalogError('');}}catch(e){if(revision===countryRevision){retryCountry=wanted;options(country,[],null,'Не удалось загрузить страны');showCatalogError('countries');}}finally{if(revision===countryRevision){setCountriesBusy(false);syncMore();}}}
  async function loadDepartures(){setCountriesBusy(true);dep.disabled=true;try{const list=await get('departures');options(dep,list,initialDeparture,'Города не найдены');dep.disabled=false;await loadCountries(initialCountry);}catch(e){options(dep,[],null,'Не удалось загрузить города');options(country,[],null,'Не удалось загрузить страны');dep.disabled=false;showCatalogError('departures');setCountriesBusy(false);syncMore();}}
  retry.addEventListener('click',()=>{if(countriesBusy)return;if(failedCatalog==='departures')loadDepartures();else if(failedCatalog==='countries')loadCountries(retryCountry);});
  loadDepartures();
  dep.addEventListener('change',()=>loadCountries(''));
})();
</script>
</body>
</html>