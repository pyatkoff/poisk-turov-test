<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/scripts/diagnostics/hotel_match_live_operator_evidence.php';

$checks = 0;
function same(mixed $actual, mixed $expected, string $label): void {
    global $checks; $checks++;
    if ($actual !== $expected) throw new RuntimeException($label . ': ' . hmlo_json([$actual, $expected]));
}
function fixture(): array {
    return ['status'=>'completed','no_replay'=>true,'operation_id'=>'completed-fixture',
        'anex_mappings'=>[],'anex_decisions'=>[],'anex_exclusions'=>[],'anex'=>[],
        'local'=>[['id'=>21477,'country_id'=>1,'is_active'=>1]],
        'anex_observations'=>[['anex_hotel_id'=>5200,'country_id'=>1,'hotel_name'=>'MOVENPICK SOMA BAY','search_count'=>40,'last_seen_utc'=>'2026-09-12 00:00:00']]];
}
function capture(): array {
    $card = 'https://www.anextour.ru/hotel/egypt/movenpick-soma-bay';
    return ['anex_hotel_id'=>5200,'local_hotel_id'=>21477,'country_id'=>1,'operator'=>'ANEX','operator_filter'=>'ANEX',
        'date_from'=>'2026-10-12','date_to'=>'2026-10-12',
        'operator_url'=>'https://agent.anextour.ru/search/tour?HOTELLIST=5200&NIGHTS=7',
        'card_url'=>$card,'operator_html'=>'<a href="'.$card.'">Hotel</a>',
        'card_html'=>'<img src="https://files.anextour.ru/hotel/egypt/pic/o123?x=1&amp;hotelCode=5200">'];
}
function build(array $census, array $captures = []): array { return hmlo_build($census, str_repeat('a',64), $captures); }
function status(array $capture, ?array $census = null): string { return build($census ?? fixture(), [$capture])['rows'][0]['captures'][0]['status']; }

same(hmlo_operator_code('https://agent.anextour.ru/search/tour?HOTELLIST=5200'),5200,'operator exact');
foreach ([
 'http://agent.anextour.ru/search/tour?HOTELLIST=5200',
 'https://agent.anextour.ru.evil.example/search/tour?HOTELLIST=5200',
 'https://u:p@agent.anextour.ru/search/tour?HOTELLIST=5200',
 'https://agent.anextour.ru/search/tour?HOTELLIST=5200#x',
 'https://agent.anextour.ru/search/tour?HOTELLIST=5200&HOTELLIST=5200',
 'https://agent.anextour.ru/search/tour?HOTELLIST=5200%2C5201',
 'https://agent.anextour.ru/search/tour?HOTELLIST[]=5200',
 'https://agent.anextour.ru/search/tour?HOTELLIST=0',
 'https://agent.anextour.ru/search/tour?HOTELLIST=5200&token=private',
 'https://agent.anextour.ru/redirect?HOTELLIST=5200',
] as $url) same(hmlo_operator_code($url),null,'operator guard');
same(status(capture()),'consistent_code_requires_identity_recheck','captured chain');
foreach ([
 ['operator','ANEX-compatible','not_anex_only'], ['operator_filter','ALL','not_anex_only'],
 ['date_to','2026-10-13','not_one_day'], ['date_from','2026-02-30','not_one_day'],
 ['operator_url','https://agent.anextour.ru/search/tour?HOTELLIST=5201','operator_code_mismatch'],
 ['operator_html','<p>No card link</p>','card_not_linked_from_operator'],
 ['card_url','https://www.anextour.ru.evil.example/hotel/test','invalid_card_url'],
 ['card_html','<img src="https://files.anextour.ru/hotel/e/x?hotelCode=5201">','card_code_mismatch'],
 ['card_html','<img src="https://files.anextour.ru/hotel/e/x?hotelCode=5200"><img src="https://files.anextour.ru/hotel/e/y?hotelCode=5201">','conflicting_codes'],
 ['card_html','<p>hotelCode=5200</p>','insufficient_evidence'],
 ['card_html','<img src="https://files.anextour.ru/hotel/e/x?hotelCode=5200&hotelCode=5200">','invalid_evidence'],
 ['card_html','<img src="https://files.anextour.ru/hotel/e/x?hotelCode=5200&session=private">','invalid_evidence'],
 ['country_id',4,'country_conflict'], ['local_hotel_id',99999,'invalid_capture'],
] as [$key,$value,$want]) { $c=capture(); $c[$key]=$value; same(status($c),$want,$key); }
$c=capture();$c['media_urls']=['https://files.anextour.ru/hotel/e/x?hotelCode=5200'];same(status($c),'media_not_in_card','cannot inject media');
$c=capture();$c['card_html']='{"image":"https:\/\/files.anextour.ru\/hotel\/e\/x?x=1\\u0026hotelCode=5200"}';same(status($c),'consistent_code_requires_identity_recheck','JSON escaped media');
$c=capture();$c['card_html']='https://files.anextour.ru/hotel/e/x?hotelCode=5200 https://foreign.example/?hotelCode=5200';same(status($c),'invalid_evidence','mixed hosts rejected');
$c=capture();$c['card_html']=str_repeat('x',2*1024*1024+1);try { status($c); throw new RuntimeException('size guard absent'); } catch (InvalidArgumentException $e) { same($e->getMessage(),'DOCUMENT_TOO_LARGE','document cap'); }
$x=fixture();$x['anex_exclusions']=[['anex_hotel_id'=>5200,'catalog_hotel_id'=>21477]];same(status(capture(),$x),'pair_excluded','exclusion');
$x=fixture();$x['anex_exclusions']=[['anex_hotel_id'=>5200,'catalog_hotel_id'=>999]];same(status(capture(),$x),'consistent_code_requires_identity_recheck','other pair not blanket blocked');
$x=fixture();$x['local'][0]['is_active']=0;same(status(capture(),$x),'invalid_capture','inactive local');
$x=fixture();$x['anex_mappings']=[['anex_hotel_id'=>5200,'enabled'=>0]];same(build($x,[capture()])['live_unresolved_ids'],0,'disabled existing mapping preserved');
$x=fixture();$x['anex_decisions']=[['anex_hotel_id'=>5200,'decision_status'=>'rejected']];same(build($x,[capture()])['live_unresolved_ids'],0,'manual reject preserved');
$x=fixture();$x['anex_observations'][0]['country_id']=99;same(build($x)['live_unresolved_ids'],0,'unsold country excluded');
foreach (['Roulette 3* Sharm El Sheikh','Fortuna 3 Ai Marmaris','Тур "Золотое Кольцо"'] as $name) {
 $x=fixture();$x['anex_observations'][0]['hotel_name']=$name;$r=build($x,[capture()]);same($r['live_unresolved_ids'],1,'product remains unresolved');same($r['product_review_ids'],1,'product flag');same($r['rows'][0]['captures'][0]['status'],'non_single_hotel_label','product no hotel promotion');
}
$x=fixture();$x['anex_observations'][0]['hotel_name']='Beach Garden Annex North South Hotel Resort Spa';same(build($x)['rows'][0]['names'][0],$x['anex_observations'][0]['hotel_name'],'qualifiers not stripped');
$x=fixture();$x['local'][]=['id'=>21478,'country_id'=>1,'is_active'=>1];$c=capture();$c['local_hotel_id']=21478;same(build($x,[capture(),$c])['rows'][0]['competing_local_targets'],true,'no arbitrary winner');
$x=fixture();$r=$x['anex_observations'][0];$r['country_id']=4;$x['anex_observations'][]=$r;same(status(capture(),$x),'country_conflict','conflicting source countries');
same(build(fixture(),[capture()])['not_write_authority'],true,'no acceptance authority');
same(build(fixture(),[capture()])['mapping_writes'],0,'no database writes');

// Thousands are processed as one input, not truncated into tiny batches.
$x=fixture();$x['anex_observations']=[];
for($i=1;$i<=3000;$i++)$x['anex_observations'][]=['anex_hotel_id'=>$i,'country_id'=>1,'hotel_name'=>'Hotel '.$i,'search_count'=>$i,'last_seen_utc'=>'2026-09-12 00:00:00'];
$r=build($x);same($r['live_unresolved_ids'],3000,'mass count');same($r['rows'][0]['anex_hotel_id'],3000,'frequency priority');same($r['rows'][2999]['anex_hotel_id'],1,'mass tail retained');
$x['anex_observations']=array_reverse($x['anex_observations']);same(build($x)['queue_sha256'],$r['queue_sha256'],'permutation deterministic');
echo 'PASS ', $checks, " offline checks; mass fixture 3000 rows; live writes/calls=0\n";
