<?php require_once dirname(__DIR__).'/site-page-shell-v1.php';
$c=sp_context('/hot/','Горящие туры — AnyTour','Горящие туры AnyTour: быстрый поиск актуальных предложений и проверка конкретного тура перед заявкой.');
$hotFrom=(new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$hotTo=(new DateTimeImmutable('tomorrow +14 days'))->format('Y-m-d');
$hotBase=['dateFrom'=>$hotFrom,'dateTo'=>$hotTo];
$hotSearch='/poisk-turov/?'.http_build_query($hotBase);
$hotScenarios=[
  ['title'=>'На неделю','note'=>'7 ночей · ближайшие две недели','days'=>7],
  ['title'=>'На 10 ночей','note'=>'Чуть больше времени на отдых','days'=>10],
  ['title'=>'На две недели','note'=>'14 ночей · длинный отпуск','days'=>14],
];
sp_head($c);sp_header($c);sp_breadcrumbs([['label'=>'Главная','href'=>'/'],['label'=>'Горящие туры','href'=>'']]);sp_hero('AnyTour · горящие туры','Горящие туры без рекламной магии','Цена на ближайшие даты меняется быстро, поэтому начинайте с живого поиска и проверяйте конкретный вариант перед заявкой.'); ?>
<main class="sp-main">
<section>
  <div class="sp-section-head"><h2>Как искать горящий тур</h2><p>Главное — гибкость и проверка конкретного доступного варианта, а не рекламной цены «от».</p></div>
  <div class="sp-grid sp-grid--balanced-three">
    <section class="sp-card"><h3>Ближайшие даты</h3><p>Для горящих предложений важнее всего гибкость по датам. Поиск откроется на ближайшие две недели — диапазон можно сразу изменить под себя.</p></section>
    <section class="sp-card"><h3>Смотрите итоговый вариант</h3><p>Низкая цена имеет смысл только вместе с конкретным рейсом, питанием, размещением и доступностью.</p></section>
    <section class="sp-card"><h3>Проверяем перед заявкой</h3><p>Откройте конкретный тур — покажем детали и итоговую стоимость до передачи менеджеру.</p></section>
  </div>
</section>
<section>
  <div class="sp-section-head"><h2>Быстрый старт по длительности</h2><p>Выберите привычный формат отдыха — откроем общий поиск на ближайшие даты с уже заданным количеством ночей.</p></div>
  <div class="sp-grid sp-grid--balanced-three">
    <?php foreach($hotScenarios as $scenario): $href='/poisk-turov/?'.http_build_query($hotBase+['daysFrom'=>$scenario['days'],'daysTill'=>$scenario['days']]); ?>
      <a class="sp-country" href="<?=sp_e($href)?>"><strong><?=sp_e($scenario['title'])?></strong><small><?=sp_e($scenario['note'])?></small></a>
    <?php endforeach; ?>
  </div>
</section>
<section class="sp-card sp-search-callout">
  <h2>Проверить ближайшие вылеты</h2><p>Откроем тот же живой поиск, которым пользуется основной сценарий AnyTour, уже с диапазоном на ближайшие две недели.</p>
  <div class="sp-actions"><a class="sp-primary" href="<?=sp_e($hotSearch)?>">Искать на ближайшие даты</a><a class="sp-secondary" href="/contacts/">Помощь менеджера</a></div>
</section>
<div class="sp-note">Горящие предложения быстро меняются. Поэтому показываем актуальные варианты через тот же поиск, где можно сразу сравнить отель, питание, перелёт и итоговую стоимость.</div>
</main><?php sp_end($c);