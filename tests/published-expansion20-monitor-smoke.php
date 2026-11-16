<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/published-hotels-monitor-v1.php';
require_once __DIR__.'/../v2/data/top500-monthly-plan-v1.php';
$checks=0;
function expansion_check(bool $ok,string $why):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
$ids=[88169,547,15774,12125,57335,2480,2417,2418,2468,12127,27481,23289,50262,72939,2489,85417,2466,2460,99713,43676];
expansion_check(v2_published_expansion20_monitor_ids_20261116()===$ids,'exact_published_twenty_changed');
$fixtureRows=array_map(static fn(int $id):array=>['anex_hotel_id'=>$id,'catalog_hotel_id'=>$id,'existing_catalog_hotel_id'=>$id,'match_class'=>'exact','approval_policy'=>'owner_exact_and_strong_20260908','enabled'=>1,'scope'=>'preview'],v2_top500_anex_ids());
$registry=AnyTourAnexSearchMappingRegistry::fromRows($fixtureRows);
$historical=v2_collection_priority_hotel_ids($registry);
$oldInput=array_values(array_unique(array_merge($historical,v2_sites_hotel_monitor_ids_20261111())));
$input=v2_all_hotel_monitor_input_ids($registry);
expansion_check(array_slice($input,0,count($oldInput))===$oldInput&&array_diff($ids,$input)===[],'old_input_order_or_new_ids_lost');
expansion_check(count($input)===count(array_unique($input))&&count($input)===count(array_unique(array_merge($oldInput,$ids))),'duplicate_input_ids');
// One base target and four actual country groups; all catalogue rows here are fixtures.
$countries=[88169=>2,547=>2,15774=>9,12125=>8,57335=>8,2480=>8,2417=>8,2418=>8,2468=>8,12127=>8,27481=>9,23289=>9,50262=>8,72939=>8,2489=>8,85417=>8,2466=>8,2460=>8,99713=>9,43676=>12];
$baseRows=[['id'=>200001,'country_id'=>4,'country_name'=>'Fixture base','is_active'=>1]];
$newRows=array_map(static fn(int $id):array=>['id'=>$id,'country_id'=>$countries[$id],'country_name'=>'Fixture country','is_active'=>1],$ids);
$departures=array_map(static fn(int $id):array=>['departure_id'=>1,'country_id'=>$id,'is_active'=>1],[4,2,9,8,12]);
$before=v2_top500_monthly_plan([200001],$baseRows,$departures,'2026-11-16',12,1);
$held=v2_append_published_hotel_monthly_plan($before,$baseRows,$departures,'2026-11-16',12,1,true);
$full=v2_append_published_hotel_monthly_plan($before,array_merge($baseRows,$newRows),$departures,'2026-11-16',12,1,true);
$cohort='published_expansion20_20261116';
expansion_check($held['targets']===$before['targets']&&$held['published_hotels']['cohorts'][$cohort]['searchable']===0,'missing_rows_credit_or_block_old_targets');
expansion_check(array_slice($full['targets'],0,count($before['targets']))===$before['targets'],'old_target_keys_changed');
expansion_check($full['published_hotels']['cohorts'][$cohort]['searchable']===20&&$full['hotel_count']===21,'new20_not_searchable');
$seen=[];$covered=[];
foreach($full['targets'] as $target){
 expansion_check(count($target['hotel_ids'])<=30,'supplier_batch_limit_increased');
 $groups=[];
 foreach($target['hotel_ids'] as $id){$key=$target['date_from'].':'.$target['date_to'].':'.$id;expansion_check(!isset($seen[$key]),'duplicate_logical_hotel_window');$seen[$key]=true;$covered[$id]=true;$groups[$id===200001?4:$countries[$id]]=true;}
 expansion_check(count($groups)===1,'countries_mixed_in_search');
}
expansion_check(array_diff($ids,array_keys($covered))===[],'new_hotel_missing_from_plan');
$old=$before['targets'][0];$now=strtotime('2026-11-16 12:00:00 UTC');
$attempt=['id'=>900,'target_key'=>$old['target_key'],'status'=>'started','search_id'=>123456,'started_epoch'=>$now-60,'finished_epoch'=>0];
$queue=v2_top500_monthly_queue($full['targets'],[$attempt],$now,24);
$matched=array_values(array_filter($queue['targets'],static fn(array $t):bool=>$t['target_key']===$old['target_key']));
expansion_check(count($matched)===1&&$matched[0]['resume_search_id']===123456&&$matched[0]['resume_attempt_id']===900,'old_search_restarted');
$missing=array_values(array_filter($newRows,static fn(array $r):bool=>$r['id']!==88169));
$partial=v2_append_published_hotel_monthly_plan($before,array_merge($baseRows,$missing),$departures,'2026-11-16',12,1,true);
expansion_check($partial['published_hotels']['cohorts'][$cohort]['searchable']===19&&array_slice($partial['targets'],0,count($before['targets']))===$before['targets'],'one_missing_blocks_peers');
$limited=array_values(array_filter($departures,static fn(array $r):bool=>$r['country_id']!==8));
$withoutMaldives=v2_append_published_hotel_monthly_plan($before,array_merge($baseRows,$newRows),$limited,'2026-11-16',12,1,true);
expansion_check($withoutMaldives['published_hotels']['cohorts'][$cohort]['searchable']===7,'missing_country_route_blocks_others');
$inactive=$newRows;$inactive[0]['is_active']=0;
$part=v2_append_published_hotel_monthly_plan($before,array_merge($baseRows,$inactive),$departures,'2026-11-16',12,1,true);
expansion_check($part['published_hotels']['cohorts'][$cohort]['searchable']===19,'inactive_hotel_counted');
echo 'PUBLISHED_EXPANSION20_OK checks='.$checks.' exact_native_ids=20 old_targets=preserved searches=resumed supplier_calls=0 writes=0',PHP_EOL;
