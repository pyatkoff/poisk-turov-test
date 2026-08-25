<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/site_conf.php');
require_once(dirname(__DIR__).'/tv_api/tvapi.php');
$dataFilter=['COUNTRY'=>4];if(!empty($params['TV_CITY']))$dataFilter['FROM']=$params['TV_CITY'];if(isset($_GET['from']))$dataFilter['FROM']=(int)$_GET['from'];if(isset($_GET['country']))$dataFilter['COUNTRY']=(int)$_GET['country'];$form=TvApi::prepareForm($dataFilter);function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}$from=(int)($form['from']??1);$country=(int)($dataFilter['COUNTRY']??4);
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Поиск туров — V2</title><link rel="stylesheet" href="/poisk-turov-test/v2/app.css?v=6"><link rel="stylesheet" href="/poisk-turov-test/v2/enhancements.css?v=3"></head><body>
<main class="v2-shell">
<header class="v2-head"><div><span class="eyebrow">ANYTOUR · V2</span><h1>Найдём тур под ваши планы</h1><p>Сравниваем предложения туроператоров и показываем рейсы до отправки заявки.</p></div><div class="head-badge">Прямой Tourvisor API</div></header>
<form id="tourSearch" class="search-card" autocomplete="off"><?=bitrix_sessid_post()?>
<div class="search-section-title"><span>Куда и когда</span><small>Основные параметры</small></div>
<div class="main-fields">
<label class="field field-wide"><span>Вылет из</span><select name="from" data-v2-catalog="departures"><?php foreach(($form['fromListFull']??[]) as $id=>$item):?><option value="<?=e($id)?>" <?=$id==$from?'selected':''?>><?=e($item['NAME2']?:$item['NAME'])?></option><?php endforeach;?></select></label>
<label class="field field-wide"><span>Страна</span><select name="country" data-v2-catalog="countries"><?php foreach(($form['countryList']??[]) as $id=>$name):?><option value="<?=e($id)?>" <?=$id==$country?'selected':''?>><?=e(is_array($name)?($name['NAME']??''):$name)?></option><?php endforeach;?></select></label>
<label class="field"><span>Вылет с</span><input type="date" name="dateFrom" value="<?=e($form['date_from']??'')?>"></label>
<label class="field"><span>Вылет до</span><input type="date" name="dateTo" value="<?=e($form['date_till']??'')?>"></label>
<label class="field"><span>Ночей от</span><input type="number" min="1" max="28" name="daysFrom" value="<?=e($form['nights_from']??5)?>"></label>
<label class="field"><span>Ночей до</span><input type="number" min="1" max="28" name="daysTill" value="<?=e($form['nights_till']??14)?>"></label>
<label class="field"><span>Взрослых</span><select name="count_people"><option>1</option><option selected>2</option><option>3</option><option>4</option><option>5</option><option>6</option></select></label>
<label class="field"><span>Детей</span><select name="child_count" id="childCount"><option value="0" selected>Без детей</option><option value="1">1 ребёнок</option><option value="2">2 ребёнка</option><option value="3">3 ребёнка</option></select></label>
</div>
<div id="childAges" class="child-ages" hidden></div>
<details class="extras" open><summary>Фильтры отдыха <span>курорт, отель, питание и перелёт</span></summary><div class="extra-grid">
<label class="field"><span>Аэропорт прилёта</span><select name="arrival"><option value="">Любой аэропорт</option></select></label>
<label class="field"><span>Курорт / регион</span><select name="region"><option value="">Все курорты</option></select></label>
<label class="field"><span>Район / субкурорт</span><select name="subregion"><option value="">Все районы</option></select></label>
<label class="field"><span>Конкретный отель</span><select name="hotel"><option value="">Любой отель</option></select></label>
<label class="field"><span>Туроператор</span><select name="operator"><option value="">Все операторы</option></select></label>
<label class="field"><span>Тип отеля</span><select name="hotel_type"><option value="">Любой тип</option></select></label>
<label class="field"><span>Категория отеля</span><select name="stars"><option value="">Любая</option><option value="2">2★ и выше</option><option value="3">3★ и выше</option><option value="4">4★ и выше</option><option value="5">5★</option></select></label>
<label class="field"><span>Рейтинг отеля</span><select name="rating"><option value="">Любой</option><option value="2">от 3.0</option><option value="3">от 3.5</option><option value="4">от 4.0</option><option value="5">от 4.5</option></select></label>
<label class="field"><span>Питание</span><select name="food"><option value="">Любое</option></select></label>
<label class="field"><span>Цена от</span><input type="number" min="0" step="1000" name="price_from" placeholder="Например 80000"></label>
<label class="field"><span>Цена до</span><input type="number" min="0" step="1000" name="price_till" placeholder="Например 180000"></label>
<div class="field"><span>Перелёт</span><div class="toggle-row"><label class="toggle"><input type="checkbox" name="onlyDirect" value="1"><span>Прямой</span></label><label class="toggle"><input type="checkbox" name="onlyCharter" value="1"><span>Чартер</span></label></div></div>
</div>
<details class="service-picker"><summary>Услуги отеля <span id="serviceCount">не выбраны</span></summary><div id="hotelServices" class="service-groups"><div class="service-loading">Загружаем доступные услуги…</div></div></details>
</details>
<button class="primary search-submit" type="submit"><span>Найти туры</span><small>показать актуальные предложения</small></button>
</form>
<section id="status" class="status" hidden></section>
<section id="resultsTools" class="results-tools" hidden><div><strong>Предложения</strong><span id="resultSummary">Актуальные варианты</span></div><label>Сортировка <select id="sortResults"><option value="price">Сначала дешевле</option><option value="rating">По рейтингу</option><option value="stars">По звёздам</option></select></label></section>
<section id="results" class="results"></section><section id="selectedTour" class="selected-tour" hidden></section>
</main>
<script>window.V2_CONFIG={api:'/poisk-turov-test/v2/api.php'};</script><script src="/poisk-turov-test/v2/search-continue.js?v=2"></script><script src="/poisk-turov-test/v2/hotel-actions.js?v=1"></script><script src="/poisk-turov-test/v2/direct-search.js?v=10"></script></body></html>