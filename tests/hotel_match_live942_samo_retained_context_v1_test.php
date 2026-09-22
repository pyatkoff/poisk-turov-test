<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_live942_samo_anex_refresh_v1.php';
function t_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
$rows=[
  ['id'=>1,'name'=>'Moscow','lName'=>'Москва'],
  ['id'=>2,'name'=>'Kazan','lName'=>'Казань'],
  ['id'=>3,'name'=>'Saint Petersburg','lName'=>'Санкт-Петербург'],
];
t_need(s942_ids($rows,['Москва'])===[1],'russian_town');
t_need(s942_ids($rows,['Kazan'])===[2],'english_town');
t_need(s942_ids($rows,['Санкт-Петербург'])===[3],'local_town');
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
echo "MATCH_LIVE942_SAMO_RETAINED_CONTEXT_V1_TEST_OK\n";
