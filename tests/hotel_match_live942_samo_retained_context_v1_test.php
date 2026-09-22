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
echo "MATCH_LIVE942_SAMO_RETAINED_CONTEXT_V1_TEST_OK\n";
