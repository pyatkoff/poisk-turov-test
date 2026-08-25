<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/site_conf.php');
require_once(dirname(__DIR__).'/tv_api/tvapi.php');

$dataFilter = ['COUNTRY' => 4];
if (!empty($params['TV_CITY'])) $dataFilter['FROM'] = $params['TV_CITY'];
if (isset($_GET['from'])) $dataFilter['FROM'] = (int)$_GET['from'];
if (isset($_GET['country'])) $dataFilter['COUNTRY'] = (int)$_GET['country'];
$form = TvApi::prepareForm($dataFilter);

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$from = (int)($form['from'] ?? 1);
$country = (int)($dataFilter['COUNTRY'] ?? 4);
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Поиск туров — V2</title>
<link rel="stylesheet" href="/poisk-turov-test/v2/app.css?v=1">
</head>
<body>
<main class="v2-shell">
  <header class="v2-head"><div><span class="eyebrow">ANYTOUR</span><h1>Найти тур</h1><p>Новый тестовый интерфейс поиска</p></div></header>

  <form id="tourSearch" class="search-card" autocomplete="off">
    <?=bitrix_sessid_post()?>
    <div class="main-fields">
      <label class="field"><span>Вылет из</span><select name="from" id="from">
        <?php foreach (($form['fromListFull'] ?? []) as $id=>$item): ?>
          <option value="<?=e($id)?>" <?=$id==$from?'selected':''?>><?=e($item['NAME2'] ?: $item['NAME'])?></option>
        <?php endforeach; ?>
      </select></label>
      <label class="field"><span>Страна</span><select name="country" id="country">
        <?php foreach (($form['countryList'] ?? []) as $id=>$name): ?>
          <option value="<?=e($id)?>" <?=$id==$country?'selected':''?>><?=e(is_array($name)?($name['NAME']??''):$name)?></option>
        <?php endforeach; ?>
      </select></label>
      <label class="field"><span>Вылет</span><input type="date" name="dateFrom" value="<?=e($form['date_from'] ?? '')?>"></label>
      <label class="field"><span>До</span><input type="date" name="dateTo" value="<?=e($form['date_till'] ?? '')?>"></label>
      <label class="field"><span>Ночей</span><div class="split"><input type="number" min="1" max="28" name="daysFrom" value="<?=e($form['nights_from'] ?? 5)?>"><input type="number" min="1" max="28" name="daysTill" value="<?=e($form['nights_till'] ?? 14)?>"></div></label>
      <label class="field"><span>Взрослых</span><select name="count_people"><option>1</option><option selected>2</option><option>3</option><option>4</option><option>5</option><option>6</option></select></label>
    </div>

    <details class="extras"><summary>Дополнительные фильтры</summary><div class="extra-grid">
      <label class="field"><span>Цена от</span><input type="number" name="price_from" inputmode="numeric"></label>
      <label class="field"><span>Цена до</span><input type="number" name="price_till" inputmode="numeric"></label>
      <label class="field"><span>Категория отеля</span><select name="stars"><option value="">Любая</option><option value="2">2★ и выше</option><option value="3">3★ и выше</option><option value="4">4★ и выше</option><option value="5">5★</option></select></label>
      <label class="field"><span>Питание</span><select name="food"><option value="">Любое</option><?php foreach (($form['foodList'] ?? []) as $id=>$name): ?><option value="<?=e($id)?>"><?=e($name)?></option><?php endforeach; ?></select></label>
    </div></details>

    <button class="primary" type="submit">Искать туры</button>
  </form>

  <section id="status" class="status" hidden></section>
  <section id="results" class="results"></section>
</main>
<script>window.V2_CONFIG={ajax:'/poisk-turov-test/template/search/ajax.php'};</script>
<script src="/poisk-turov-test/v2/app.js?v=1"></script>
</body></html>
