<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/published-hotels-monitor-v1.php';
require_once __DIR__.'/../v2/data/top500-monthly-plan-v1.php';
require_once __DIR__.'/../app/integrations/anex-search-mapping-registry.php';
$checks123=0;
function check123(bool $v,string $m):void{global $checks123;$checks123++;if(!$v)throw new RuntimeException($m);}
$ids=[797,839,7897,8094,660,2074,2075,4262,4446,7935,8117,12201,45733,57720,74940,630,766,8127,43827,44661,56478,57867,58088,80057,574,595,632,126428,2968,3018,13110,796,42509,66861,45232,47213,77818,82631,102191,112772,116451,128830,103063,46569,68194,126101,68663,69130,82751,23723,66681,80225,80955,98713,104973,111414,4090,22785,22789,23721,82067,4076,4077,131884,70778,126417,52256,65253,15862,41494,22718,541,2006,9385,3006,82422,81140,8020,51931,74110,113553,115812,121699,140013,143372,15852,2986,4203,4403,8105,8169,15857,23264,23738,23755,70050,4235,8170,22777,22845,8019,13935,47185,49176,49287,55570,53706,23756,40895,43494,52090,66666,68971,74114,79323,79954,80233,110848,111489,118713,119052,127988,66375];
$countries=json_decode('{"797":2,"839":2,"7897":2,"8094":2,"660":2,"2074":2,"2075":2,"4262":2,"4446":2,"7935":2,"8117":2,"12201":2,"45733":2,"57720":2,"74940":2,"630":2,"766":2,"8127":2,"43827":2,"44661":2,"56478":2,"57867":2,"58088":2,"80057":2,"574":2,"595":2,"632":2,"126428":2,"2968":2,"3018":2,"13110":2,"796":2,"42509":2,"66861":2,"45232":2,"47213":2,"77818":2,"82631":2,"102191":2,"112772":2,"116451":2,"128830":2,"103063":16,"46569":16,"68194":16,"126101":16,"68663":16,"69130":16,"82751":16,"23723":16,"66681":16,"80225":16,"80955":16,"98713":16,"104973":16,"111414":16,"4090":16,"22785":16,"22789":16,"23721":16,"82067":16,"4076":16,"4077":16,"131884":16,"70778":16,"126417":16,"52256":16,"65253":16,"15862":16,"41494":16,"22718":2,"541":2,"2006":2,"9385":2,"3006":2,"82422":2,"81140":2,"8020":2,"51931":2,"74110":16,"113553":16,"115812":16,"121699":16,"140013":16,"143372":16,"15852":16,"2986":2,"4203":2,"4403":2,"8105":2,"8169":2,"15857":16,"23264":2,"23738":16,"23755":16,"70050":2,"4235":2,"8170":2,"22777":2,"22845":2,"8019":2,"13935":16,"47185":16,"49176":2,"49287":2,"55570":2,"53706":16,"23756":16,"40895":2,"43494":2,"52090":2,"66666":16,"68971":2,"74114":16,"79323":2,"79954":2,"80233":2,"110848":2,"111489":16,"118713":2,"119052":2,"127988":16,"66375":2}',true,512,JSON_THROW_ON_ERROR);
check123(v2_published_expansion123_monitor_ids_20261118()===$ids&&count(array_unique($ids))===123,'exact123');
$fixture=array_map(static fn(int $id):array=>['anex_hotel_id'=>$id,'catalog_hotel_id'=>$id,'existing_catalog_hotel_id'=>$id,'match_class'=>'exact','approval_policy'=>'owner_exact_and_strong_20260908','enabled'=>1,'scope'=>'preview'],v2_top500_anex_ids());
$registry=AnyTourAnexSearchMappingRegistry::fromRows($fixture);
$nativeRegistry=AnyTourAnexSearchMappingRegistry::fromRows([]);
$nativeOld=array_values(array_unique(array_merge(v2_collection_priority_hotel_ids($nativeRegistry),v2_sites_hotel_monitor_ids_20261111(),v2_published_expansion20_monitor_ids_20261116(),v2_published_expansion32_monitor_ids_20261116(),v2_published_expansion217_monitor_ids_20261117())));
check123(array_intersect($nativeOld,$ids)===[],'new123_already_in_native_input');
$old=array_values(array_unique(array_merge(v2_collection_priority_hotel_ids($registry),v2_sites_hotel_monitor_ids_20261111(),v2_published_expansion20_monitor_ids_20261116(),v2_published_expansion32_monitor_ids_20261116(),v2_published_expansion217_monitor_ids_20261117())));
$all=v2_all_hotel_monitor_input_ids($registry);check123(array_slice($all,0,count($old))===$old,'old_input_prefix');check123($all===array_values(array_unique(array_merge($old,$ids))),'exact_union');
$base=[['id'=>200001,'country_id'=>4,'country_name'=>'Fixture','is_active'=>1]];
$rows=array_map(static fn(int $id):array=>['id'=>$id,'country_id'=>$countries[$id],'country_name'=>'Fixture','is_active'=>1],$ids);
$departures=array_map(static fn(int $id):array=>['departure_id'=>1,'country_id'=>$id,'is_active'=>1],array_values(array_unique(array_merge([4],array_values($countries)))));
$before=v2_top500_monthly_plan([200001],$base,$departures,'2026-11-18',12,1);$cohort='published_expansion123_20261118';
$full=v2_append_published_hotel_monthly_plan($before,array_merge($base,$rows),$departures,'2026-11-18',12,1,true);
check123($full['published_hotels']['cohorts'][$cohort]['searchable']===123,'searchable123');check123(array_slice($full['targets'],0,count($before['targets']))===$before['targets'],'base_unchanged');
$seen=[];$covered=[];
foreach($full['targets'] as $t){check123(count($t['hotel_ids'])<=30,'batch_limit');foreach($t['hotel_ids'] as $id){$key=$t['date_from'].':'.$t['date_to'].':'.$id;check123(!isset($seen[$key]),'duplicate_window');$seen[$key]=true;$covered[$id]=true;}}
check123(array_diff($ids,array_keys($covered))===[],'coverage123');
foreach($full['targets'] as $t){$group=[];foreach($t['hotel_ids'] as $id)$group[$id===200001?4:$countries[$id]]=true;check123(count($group)===1,'mixed_country_target');}
$without=v2_append_published_hotel_monthly_plan($before,array_merge($base,$rows),$departures,'2026-11-18',12,1,false);check123(!isset($without['published_hotels']['cohorts'][$cohort]),'opt_in');
array_pop($rows);$partial=v2_append_published_hotel_monthly_plan($before,array_merge($base,$rows),$departures,'2026-11-18',12,1,true);check123($partial['published_hotels']['cohorts'][$cohort]['searchable']===122,'missing_one_blocks_peers');
$queue=v2_top500_monthly_queue($full['targets'],[],strtotime('2026-11-18 12:00:00 UTC'),24,array_merge($old,$ids));foreach($queue['targets'] as $t)if(array_intersect($t['hotel_ids'],$ids)!==[])check123($t['_published']===true,'new123_not_prioritized');
$wf=file_get_contents(__DIR__.'/../.github/workflows/collect-anytour-tours.yml');check123(str_contains($wf,"- cron: '12 * * * *'")&&str_contains($wf,"inputs.monthly_budget || '64'"),'schedule_or_budget');
// Every prior native cohort retains exact target order and keys after the append.
$oldPublished=$nativeOld; // Every prior native input, including sites605 and all expansion cohorts.
$oldRows=array_map(static fn(int $id):array=>['id'=>$id,'country_id'=>4,'country_name'=>'Fixture old','is_active'=>1],$oldPublished);
$allRows=array_map(static fn(int $id):array=>['id'=>$id,'country_id'=>$countries[$id],'country_name'=>'Fixture new','is_active'=>1],$ids);
$oldPlan=v2_append_published_hotel_monthly_plan($before,array_merge($base,$oldRows),$departures,'2026-11-18',12,1,true);
$combined=v2_append_published_hotel_monthly_plan($before,array_merge($base,$oldRows,$allRows),$departures,'2026-11-18',12,1,true);
check123(array_slice($combined['targets'],0,count($oldPlan['targets']))===$oldPlan['targets'],'all_prior_native_targets_changed');
// Synthetic ANEX identity fixture can overlap numerically; union must deduplicate, never infer a native binding.
check123(count($all)===count(array_unique(array_merge($old,$ids))),'input_union_duplicates');
$inactive=$allRows;$inactive[0]['is_active']=0;
$held=v2_append_published_hotel_monthly_plan($before,array_merge($base,$inactive),$departures,'2026-11-18',12,1,true);
check123($held['published_hotels']['cohorts'][$cohort]['searchable']===122,'inactive_admitted');
$noMv=array_values(array_filter($departures,static fn(array $r):bool=>$r['country_id']!==8));
$held=v2_append_published_hotel_monthly_plan($before,array_merge($base,$allRows),$noMv,'2026-11-18',12,1,true);
check123($held['published_hotels']['cohorts'][$cohort]['searchable']===123-count(array_filter($countries,static fn(int $c):bool=>$c===8)),'route_eligibility_changed');
$now=strtotime('2026-11-18 12:00:00 UTC');$first=$before['targets'][0];
$attempt=['id'=>900,'target_key'=>$first['target_key'],'status'=>'started','search_id'=>123456,'started_epoch'=>$now-60,'finished_epoch'=>0];
$priority=array_merge(v2_sites_hotel_monitor_ids_20261111(),v2_published_expansion20_monitor_ids_20261116(),v2_published_expansion32_monitor_ids_20261116(),v2_published_expansion217_monitor_ids_20261117(),$ids);
$resume=v2_top500_monthly_queue($combined['targets'],[$attempt],$now,24,$priority);
check123($resume['targets'][0]['resume_search_id']===123456&&$resume['targets'][0]['resume_attempt_id']===900,'new123_displaced_retained_search');
foreach($resume['targets'] as $t)if(array_intersect($t['hotel_ids'],$ids)!==[])check123($t['_published']===true,'published_priority_missing');
$caller=file_get_contents(__DIR__.'/../v2/data/top500-daily-collector-v1.php');
check123(str_contains($caller,'array_merge(v2_sites_hotel_monitor_ids_20261111(),v2_published_expansion20_monitor_ids_20261116(),v2_published_expansion32_monitor_ids_20261116(),v2_published_expansion217_monitor_ids_20261117(),v2_published_expansion123_monitor_ids_20261118())'),'actual_caller_missing123');
check123(substr_count($wf,"- 'tests/published-expansion123-monitor-smoke.php'")===2&&str_contains($wf,'php tests/published-expansion123-monitor-smoke.php'),'ci_missing123');
check123(str_contains($wf,'            v2/data/published-hotels-monitor-v1.php '.chr(92).chr(10))&&str_contains($wf,'            v2/data/top500-daily-collector-v1.php '.chr(92).chr(10)),'runtime_delivery_missing123');
echo "PUBLISHED_EXPANSION123_OK checks=$checks123 exact123 old_prefix_preserved supplier_calls=0 writes=0\n";
