<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/published-hotels-monitor-v1.php';
require_once __DIR__.'/../v2/data/top500-monthly-plan-v1.php';
require_once __DIR__.'/../app/integrations/anex-search-mapping-registry.php';
$checks217=0;
function check217(bool $v,string $m):void{global $checks217;$checks217++;if(!$v)throw new RuntimeException($m);}
$ids=[12120,64957,76029,66198,49750,123247,48184,32322,28272,7458,2490,2458,113544,73477,2588,2611,2612,2610,2931,78456,7831,2948,57536,61241,17424,17423,23718,22940,97001,74509,4283,52337,64648,78669,60788,2435,2481,24744,50261,78006,26043,2922,64130,42647,2457,65341,49084,75538,82649,37376,1148,75100,80478,27646,1150,40222,306,83106,113,108,133,123365,76125,76121,1028,1071,1149,1563,28460,112391,127009,17603,17682,66450,37470,99608,102321,37165,72910,82106,103273,1980,266,159,344,125550,143790,76208,104626,2554,65170,80397,82023,2549,2553,8917,8999,56236,110597,2519,2591,2936,8945,13837,80431,78845,8918,37172,17683,28662,83059,75658,60787,2592,2681,49850,65031,4522,8916,13840,99319,110940,102527,103272,32226,69179,112603,77942,46724,13996,63901,13845,45287,44984,23290,38640,128487,68939,77515,69774,23283,76032,2478,132068,158417,22740,78200,538,792,2984,4202,7898,66860,598,669,3022,4294,4309,4343,7856,8171,9395,12170,526,599,685,689,697,761,791,3021,22836,33310,57466,70051,542,596,649,706,765,768,825,4270,9404,35036,77358,60433,61611,126119,104035,126601,9230,32341,32412,41498,57025,61623,73257,4966,61615,65164,132294,7443,43689,54914,57672,61583,14130,46747,71241,108234,4919,82212,7449,69260,47460,64775];
$countries=json_decode('{"12120":8,"64957":8,"76029":8,"66198":8,"49750":8,"123247":9,"48184":9,"32322":9,"28272":2,"7458":16,"2490":8,"2458":8,"113544":9,"73477":8,"2588":9,"2611":9,"2612":9,"2610":9,"2931":9,"78456":64,"7831":2,"2948":2,"57536":64,"61241":64,"17424":4,"17423":4,"23718":16,"22940":9,"97001":1,"74509":1,"4283":2,"52337":9,"64648":9,"78669":2,"60788":1,"2435":8,"2481":8,"24744":8,"50261":8,"78006":8,"26043":8,"2922":8,"64130":8,"42647":8,"2457":8,"65341":4,"49084":4,"75538":4,"82649":4,"37376":4,"1148":4,"75100":4,"80478":4,"27646":4,"1150":4,"40222":1,"306":1,"83106":1,"113":1,"108":1,"133":1,"123365":1,"76125":1,"76121":1,"1028":4,"1071":4,"1149":4,"1563":4,"28460":4,"112391":4,"127009":4,"17603":4,"17682":4,"66450":4,"37470":4,"99608":4,"102321":4,"37165":4,"72910":4,"82106":1,"103273":1,"1980":1,"266":1,"159":1,"344":1,"125550":1,"143790":1,"76208":1,"104626":1,"2554":9,"65170":9,"80397":9,"82023":9,"2549":9,"2553":9,"8917":9,"8999":9,"56236":9,"110597":9,"2519":9,"2591":9,"2936":9,"8945":9,"13837":9,"80431":9,"78845":9,"8918":9,"37172":4,"17683":4,"28662":4,"83059":4,"75658":1,"60787":1,"2592":9,"2681":9,"49850":9,"65031":9,"4522":9,"8916":9,"13840":9,"99319":4,"110940":4,"102527":4,"103272":1,"32226":9,"69179":9,"112603":9,"77942":9,"46724":9,"13996":9,"63901":9,"13845":9,"45287":9,"44984":9,"23290":9,"38640":9,"128487":9,"68939":9,"77515":9,"69774":9,"23283":9,"76032":8,"2478":8,"132068":8,"158417":8,"22740":2,"78200":2,"538":2,"792":2,"2984":2,"4202":2,"7898":2,"66860":2,"598":2,"669":2,"3022":2,"4294":2,"4309":2,"4343":2,"7856":2,"8171":2,"9395":2,"12170":2,"526":2,"599":2,"685":2,"689":2,"697":2,"761":2,"791":2,"3021":2,"22836":2,"33310":2,"57466":2,"70051":2,"542":2,"596":2,"649":2,"706":2,"765":2,"768":2,"825":2,"4270":2,"9404":2,"35036":2,"77358":2,"60433":16,"61611":16,"126119":16,"104035":16,"126601":16,"9230":16,"32341":16,"32412":16,"41498":16,"57025":16,"61623":16,"73257":16,"4966":16,"61615":16,"65164":16,"132294":16,"7443":16,"43689":16,"54914":16,"57672":16,"61583":16,"14130":16,"46747":16,"71241":16,"108234":16,"4919":16,"82212":16,"7449":16,"69260":16,"47460":16,"64775":16}',true,512,JSON_THROW_ON_ERROR);
check217(v2_published_expansion217_monitor_ids_20261117()===$ids&&count(array_unique($ids))===217,'exact217');
$fixture=array_map(static fn(int $id):array=>['anex_hotel_id'=>$id,'catalog_hotel_id'=>$id,'existing_catalog_hotel_id'=>$id,'match_class'=>'exact','approval_policy'=>'owner_exact_and_strong_20260908','enabled'=>1,'scope'=>'preview'],v2_top500_anex_ids());
$registry=AnyTourAnexSearchMappingRegistry::fromRows($fixture);
$old=array_values(array_unique(array_merge(v2_collection_priority_hotel_ids($registry),v2_sites_hotel_monitor_ids_20261111(),v2_published_expansion20_monitor_ids_20261116(),v2_published_expansion32_monitor_ids_20261116())));
$all=v2_all_hotel_monitor_input_ids($registry);check217(array_slice($all,0,count($old))===$old,'old_input_prefix');check217($all===array_values(array_unique(array_merge($old,$ids))),'exact_union');
$base=[['id'=>200001,'country_id'=>4,'country_name'=>'Fixture','is_active'=>1]];
$rows=array_map(static fn(int $id):array=>['id'=>$id,'country_id'=>$countries[$id],'country_name'=>'Fixture','is_active'=>1],$ids);
$departures=array_map(static fn(int $id):array=>['departure_id'=>1,'country_id'=>$id,'is_active'=>1],array_values(array_unique(array_merge([4],array_values($countries)))));
$before=v2_top500_monthly_plan([200001],$base,$departures,'2026-11-17',12,1);$cohort='published_expansion217_20261117';
$full=v2_append_published_hotel_monthly_plan($before,array_merge($base,$rows),$departures,'2026-11-17',12,1,true);
check217($full['published_hotels']['cohorts'][$cohort]['searchable']===217,'searchable217');check217(array_slice($full['targets'],0,count($before['targets']))===$before['targets'],'base_unchanged');
$seen=[];$covered=[];
foreach($full['targets'] as $t){check217(count($t['hotel_ids'])<=30,'batch_limit');foreach($t['hotel_ids'] as $id){$key=$t['date_from'].':'.$t['date_to'].':'.$id;check217(!isset($seen[$key]),'duplicate_window');$seen[$key]=true;$covered[$id]=true;}}
check217(array_diff($ids,array_keys($covered))===[],'coverage217');
$without=v2_append_published_hotel_monthly_plan($before,array_merge($base,$rows),$departures,'2026-11-17',12,1,false);check217(!isset($without['published_hotels']['cohorts'][$cohort]),'opt_in');
array_pop($rows);$partial=v2_append_published_hotel_monthly_plan($before,array_merge($base,$rows),$departures,'2026-11-17',12,1,true);check217($partial['published_hotels']['cohorts'][$cohort]['searchable']===216,'missing_one_blocks_peers');
$queue=v2_top500_monthly_queue($full['targets'],[],strtotime('2026-11-17 12:00:00 UTC'),24,array_merge($old,$ids));foreach($queue['targets'] as $t)if(array_intersect($t['hotel_ids'],$ids)!==[])check217($t['_published']===true,'new217_not_prioritized');
$wf=file_get_contents(__DIR__.'/../.github/workflows/collect-anytour-tours.yml');check217(str_contains($wf,"- cron: '12 * * * *'")&&str_contains($wf,"inputs.monthly_budget || '64'"),'schedule_or_budget');
// Existing old20/32 targets retain their exact order and keys after the append.
$oldPublished=array_merge(v2_published_expansion20_monitor_ids_20261116(),v2_published_expansion32_monitor_ids_20261116());
$oldRows=array_map(static fn(int $id):array=>['id'=>$id,'country_id'=>4,'country_name'=>'Fixture old','is_active'=>1],$oldPublished);
$allRows=array_map(static fn(int $id):array=>['id'=>$id,'country_id'=>$countries[$id],'country_name'=>'Fixture new','is_active'=>1],$ids);
$oldPlan=v2_append_published_hotel_monthly_plan($before,array_merge($base,$oldRows),$departures,'2026-11-17',12,1,true);
$combined=v2_append_published_hotel_monthly_plan($before,array_merge($base,$oldRows,$allRows),$departures,'2026-11-17',12,1,true);
check217(array_slice($combined['targets'],0,count($oldPlan['targets']))===$oldPlan['targets'],'old20_32_targets_changed');
// Synthetic ANEX identity fixture can overlap numerically; union must deduplicate, never infer a native binding.
check217(count($all)===count(array_unique(array_merge($old,$ids))),'input_union_duplicates');
$inactive=$allRows;$inactive[0]['is_active']=0;
$held=v2_append_published_hotel_monthly_plan($before,array_merge($base,$inactive),$departures,'2026-11-17',12,1,true);
check217($held['published_hotels']['cohorts'][$cohort]['searchable']===216,'inactive_admitted');
$noMv=array_values(array_filter($departures,static fn(array $r):bool=>$r['country_id']!==8));
$held=v2_append_published_hotel_monthly_plan($before,array_merge($base,$allRows),$noMv,'2026-11-17',12,1,true);
check217($held['published_hotels']['cohorts'][$cohort]['searchable']===217-count(array_filter($countries,static fn(int $c):bool=>$c===8)),'route_eligibility_changed');
$now=strtotime('2026-11-17 12:00:00 UTC');$first=$before['targets'][0];
$attempt=['id'=>900,'target_key'=>$first['target_key'],'status'=>'started','search_id'=>123456,'started_epoch'=>$now-60,'finished_epoch'=>0];
$priority=array_merge(v2_sites_hotel_monitor_ids_20261111(),v2_published_expansion20_monitor_ids_20261116(),v2_published_expansion32_monitor_ids_20261116(),$ids);
$resume=v2_top500_monthly_queue($combined['targets'],[$attempt],$now,24,$priority);
check217($resume['targets'][0]['resume_search_id']===123456&&$resume['targets'][0]['resume_attempt_id']===900,'new217_displaced_retained_search');
foreach($resume['targets'] as $t)if(array_intersect($t['hotel_ids'],$ids)!==[])check217($t['_published']===true,'published_priority_missing');
$caller=file_get_contents(__DIR__.'/../v2/data/top500-daily-collector-v1.php');
check217(str_contains($caller,'array_merge(v2_sites_hotel_monitor_ids_20261111(),v2_published_expansion20_monitor_ids_20261116(),v2_published_expansion32_monitor_ids_20261116(),v2_published_expansion217_monitor_ids_20261117())'),'actual_caller_missing217');
check217(substr_count($wf,"- 'tests/published-expansion217-monitor-smoke.php'")===2&&str_contains($wf,'php tests/published-expansion217-monitor-smoke.php'),'ci_missing217');
check217(str_contains($wf,'            v2/data/published-hotels-monitor-v1.php '.chr(92).chr(10))&&str_contains($wf,'            v2/data/top500-daily-collector-v1.php '.chr(92).chr(10)),'runtime_delivery_missing217');
echo "PUBLISHED_EXPANSION217_OK checks=$checks217 exact217 old_prefix_preserved supplier_calls=0 writes=0\n";
