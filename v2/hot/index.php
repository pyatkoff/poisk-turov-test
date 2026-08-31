<?php
require_once dirname(__DIR__).'/site-page-shell-v1.php';
require_once dirname(__DIR__).'/data/hot-tours-read-v1.php';
$c=sp_context('/hot/','Горящие туры — AnyTour','Горящие туры AnyTour: свежие предложения из накопленной базы и живая проверка конкретного тура перед заявкой.');
$hotFrom=(new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$hotTo=(new DateTimeImmutable('tomorrow +14 days'))->format('Y-m-d');
$hotBase=['dateFrom'=>$hotFrom,'dateTo'=>$hotTo];
$hotSearch='/poisk-turov/?'.http_build_query($hotBase);
$hotScenarios=[
  ['title'=>'На неделю','note'=>'7 ночей · ближайшие две недели','days'=>7],
  ['title'=>'На 10 ночей','note'=>'Чуть больше времени на отдых','days'=>10],
  ['title'=>'На две недели','note'=>'14 ночей · длинный отпуск','days'=>14],
];
$hotOffers=[];
try { $hotOffers=v2_data_hot_tours(['limit'=>9]); } catch(Throwable $e) { error_log('hot page snapshot: '.$e->getMessage()); }
function hot_price($value,$currency='RUB'): string {
  $suffix=strtoupper((string)$currency)==='RUB'?' ₽':' '.strtoupper((string)$currency);
  return number_format((float)$value,0,',',' ').$suffix;
}
function hot_date_label($value): string {
  try { return (new DateTimeImmutable((string)$value))->format('d.m'); } catch(Throwable $e) { return (string)$value; }
}
sp_head($c);sp_header($c);sp_breadcrumbs([['label'=>'Главная','href'=>'/'],['label'=>'Горящие туры','href'=>'']]);sp_hero('AnyTour · горящие туры','Горящие туры из живых поисков','Сохраняем свежие предложения, которые уже видели в поиске AnyTour, и перед заявкой всё равно перепроверяем выбранный вариант в Tourvisor.'); ?>
<main class="sp-main">
<section class="sp-card sp-search-callout">
  <h2>Проверить ближайшие вылеты</h2><p>Откроем тот же живой поиск, которым пользуется основной сценарий AnyTour, уже с диапазоном на ближайшие две недели.</p>
  <div class="sp-actions"><a class="sp-primary" href="<?=sp_e($hotSearch)?>">Искать на ближайшие даты</a><a class="sp-secondary" href="/contacts/">Помощь менеджера</a></div>
</section>
<?php if($hotOffers): ?>
<section aria-labelledby="hot-live-title">
  <div class="sp-section-head"><h2 id="hot-live-title">Свежие предложения из базы AnyTour</h2><p>Это цены, недавно полученные в реальных поисках на 2 взрослых без детей. Они помогают быстро найти интересный вариант, но не заменяют финальную проверку доступности и цены.</p></div>
  <div class="sp-grid sp-grid--balanced-three">
    <?php foreach($hotOffers as $offer):
      $date=(string)$offer['departure_date'];$nights=(int)$offer['nights'];
      $href='/poisk-turov/?'.http_build_query(['from'=>(int)$offer['departure_id'],'country'=>(int)$offer['country_id'],'dateFrom'=>$date,'dateTo'=>$date,'daysFrom'=>$nights,'daysTill'=>$nights,'count_people'=>2]);
      $where=array_values(array_filter([(string)($offer['country_name']??''),(string)($offer['region_name']??'')]));
      $departure=trim((string)($offer['departure_name']??''));
      $meta=implode(' · ',array_filter([implode(', ',$where),$departure!==''?'из '.$departure:'',hot_date_label($date),$nights.' ноч.'])); ?>
      <section class="sp-card sp-hot-offer-card">
        <h3><?=sp_e((string)$offer['hotel_name'])?><?php if((int)($offer['hotel_category']??0)>0): ?> <?=str_repeat('★',(int)$offer['hotel_category'])?><?php endif; ?></h3>
        <p><?=sp_e($meta)?></p>
        <p><strong><?=sp_e(hot_price($offer['price'],$offer['currency']??'RUB'))?></strong> · за двоих</p>
        <div class="sp-actions"><a class="sp-primary" href="<?=sp_e($href)?>">Проверить варианты</a></div>
      </section>
    <?php endforeach; ?>
  </div>
  <div class="sp-note">Витрина строится из накопленных свежих результатов AnyTour. Если предложение устарело или изменилось, живой поиск покажет уже текущую доступность и цену.</div>
</section>
<?php endif; ?>
<section>
  <div class="sp-section-head"><h2>Быстрый старт по длительности</h2><p>Выберите привычный формат отдыха — откроем общий поиск на ближайшие даты с уже заданным количеством ночей.</p></div>
  <div class="sp-grid sp-grid--balanced-three">
    <?php foreach($hotScenarios as $scenario): $href='/poisk-turov/?'.http_build_query($hotBase+['daysFrom'=>$scenario['days'],'daysTill'=>$scenario['days']]); ?>
      <a class="sp-country" href="<?=sp_e($href)?>"><strong><?=sp_e($scenario['title'])?></strong><small><?=sp_e($scenario['note'])?></small></a>
    <?php endforeach; ?>
  </div>
</section>
<section>
  <div class="sp-section-head"><h2>Как искать горящий тур</h2><p>Главное — гибкость и проверка конкретного доступного варианта, а не рекламной цены «от».</p></div>
  <div class="sp-grid sp-grid--balanced-three">
    <section class="sp-card"><h3>Ближайшие даты</h3><p>Для горящих предложений важнее всего гибкость по датам. Поиск откроется на ближайшие две недели — диапазон можно сразу изменить под себя.</p></section>
    <section class="sp-card"><h3>Смотрите итоговый вариант</h3><p>Низкая цена имеет смысл только вместе с конкретным рейсом, питанием, размещением и доступностью.</p></section>
    <section class="sp-card"><h3>Проверяем перед заявкой</h3><p>Откройте конкретный тур — покажем детали и итоговую стоимость до передачи менеджеру.</p></section>
  </div>
</section>
<div class="sp-note">Горящие предложения быстро меняются. Поэтому накопленная база помогает отобрать свежие варианты, а окончательная проверка всегда остаётся за живым поиском.</div>
</main><?php sp_end($c);