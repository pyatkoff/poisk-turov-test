<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_live942_samo_anex_refresh_v1.php';
require_once __DIR__.'/../scripts/diagnostics/hotel_match_live942_frontier_plan_v1.php';
function t_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
$rows=[
  ['id'=>1,'name'=>'Moscow','lName'=>'Москва'],
  ['id'=>2,'name'=>'Kazan','lName'=>'Казань'],
  ['id'=>3,'name'=>'Saint Petersburg','lName'=>'Санкт-Петербург'],
];
t_need(s942_ids($rows,['Москва'])===[1],'russian_town');
t_need(s942_ids($rows,['Kazan'])===[2],'english_town');
t_need(s942_ids($rows,['Санкт-Петербург'])===[3],'local_town');
t_need((s942_departure_binding($rows,'')['state']??'')==='departure_name_missing','empty_departure_local_hold');
t_need((s942_departure_binding($rows,'Москва')['id']??null)===1,'departure_ready');
t_need(str_ends_with(s942_source_integrations(),'/app/integrations'),'exact_source_integrations');
$missingCatalogDir=sys_get_temp_dir().'/s942-missing-'.bin2hex(random_bytes(4));mkdir($missingCatalogDir,0700,true);
$missingCatalog=s942_catalog_or_hold($missingCatalogDir.'/catalog.json',2);
t_need(($missingCatalog['state']??'')==='catalog_missing'&&($missingCatalog['saved']??null)===null,'missing_catalog_local_hold');
rmdir($missingCatalogDir);
t_need(s942_date_ymd('2026-10-11')==='20261011','date_valid');
t_need(s942_date_ymd('2026-02-31')===null,'date_invalid');
t_need(s942_child_ages(0,'')===[],'zero_children');
t_need(s942_child_ages(2,'5,12')===[5,12],'ages');
t_need(s942_child_ages(2,'5')===null,'age_count');
t_need(s942_child_ages(1,'18')===null,'age_range');
$tmp=sys_get_temp_dir().'/s942-test-'.bin2hex(random_bytes(4));
mkdir($tmp.'/_preview/search3-anex-candidate',0700,true);
file_put_contents($tmp.'/_preview/search3-anex-candidate/.andromeda-private.php',"<?php return ['enabled'=>true,'catalog_path'=>'/tmp/catalog.json'];");
t_need((s942_private_config($tmp)['catalog_path']??'')==='/tmp/catalog.json','private_proxy');
unlink($tmp.'/_preview/search3-anex-candidate/.andromeda-private.php');
rmdir($tmp.'/_preview/search3-anex-candidate');rmdir($tmp.'/_preview');rmdir($tmp);

$oldHome=(string)getenv('HOME');$planHome=sys_get_temp_dir().'/m942-plan-'.bin2hex(random_bytes(4));
$ops=$planHome.'/.anytoour-match/operations';mkdir($ops,0700,true);
$planRows=[];for($i=1;$i<=942;$i++)$planRows[]=['tv_hotel_id'=>$i,'samo_hotel_ids'=>[$i+100000],'country_id'=>4,'departure_id'=>1,'departure_date'=>'2026-10-01','nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>''];
$plan=['state'=>'original_live942_ready','frontier_count'=>942,'current_missing_count'=>927,'control_written_count'=>15,'rows'=>$planRows];
foreach(['hotel-match-live942-tv-anex-refresh-1971-20260923-o0-n350-v2','hotel-match-live942-tv-anex-refresh-1971-20260923-o350-n350-v2','hotel-match-live942-tv-anex-refresh-1971-20260923-o700-n242-v2'] as $name){mkdir($ops.'/'.$name,0700,true);file_put_contents($ops.'/'.$name.'/plan.json',json_encode($plan));}
putenv('HOME='.$planHome);$ret=m942_retained_original942();
t_need(($ret['cohort_source']??'')==='retained_terminal_tv_plan'&&count($ret['rows']??[])===942,'retained_original942');
putenv('HOME='.$oldHome);
foreach(array_reverse(glob($ops.'/*')?:[]) as $d){@unlink($d.'/plan.json');@rmdir($d);}@rmdir($ops);@rmdir(dirname($ops));@rmdir($planHome);

$runnerSource=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_live942_samo_anex_refresh_v1.php');
t_need(str_contains($runnerSource,'$lastHttpStarted=0.0'),'shared_rate_clock');
t_need(str_contains($runnerSource,'$transport=new AnyTourAndromedaTransport(true);$lastHttpStarted=microtime(true);'),'transport_per_http');
echo "MATCH_LIVE942_SAMO_RETAINED_CONTEXT_V1_TEST_OK\n";
