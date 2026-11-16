"""Prepare only the five admitted consumer files; no network or production calls."""
from __future__ import annotations
import hashlib,json,pathlib,subprocess,sys
BASE='fd8565b2286f8ba253c847e70970fbaea5ffa0e9'
SOURCE='b3d5dca2a7c8cecee88db1404cb2a8c28d26841e'
IDS=[548,2947,4032,43675,48263,82790,24743,66547,60232,78033,43569,78002,2451,63208,2491,2470,64954,2479,2493,76318,53617,12124,76011,65108,123308,65938,80008,2436,2444,2445,218,63191]
F20='v2_published_expansion20_monitor_ids_20261116()'
F32='v2_published_expansion32_monitor_ids_20261116()'
OLD_PRIORITY='array_merge(v2_sites_hotel_monitor_ids_20261111(),'+F20+')'
NEW_PRIORITY='array_merge(v2_sites_hotel_monitor_ids_20261111(),'+F20+','+F32+')'
def replace_exact(text,old,new,count=1):
 if text.count(old)!=count:raise ValueError('exact_anchor_changed:'+old)
 return text.replace(old,new)
def main():
 root,out=map(pathlib.Path,sys.argv[1:]);out.mkdir(parents=True,exist_ok=True)
 assert subprocess.check_output(['git','-C',str(root),'rev-parse','HEAD'],text=True).strip()==BASE
 files={}
 path='v2/data/published-hotels-monitor-v1.php';original=(root/path).read_text()
 function='''/** Thirty-two published pairs: publication43922854551, binding43927951309, routes43928966057, feeds43929964963.
 * Exact Tourvisor namespace. Append after earlier cohorts without regrouping old targets.
 */
function v2_published_expansion32_monitor_ids_20261116(): array
{
    return '''+json.dumps(IDS,separators=(',',':'))+''';
}

'''
 anchor='function v2_all_hotel_monitor_input_ids(?AnyTourAnexSearchMappingRegistry $registry = null): array'
 text=replace_exact(original,anchor,function+anchor)
 text=replace_exact(text,'v2_sites_hotel_monitor_ids_20261111(),'+F20+')));','v2_sites_hotel_monitor_ids_20261111(),'+F20+','+F32+')));')
 cohort="    if($includeSitesRoster)$cohorts['published_expansion20_20261116']="+F20+';'
 text=replace_exact(text,cohort,cohort+"\n    if($includeSitesRoster)$cohorts['published_expansion32_20261116']="+F32+';')
 files[path]=text
 path='v2/data/top500-daily-collector-v1.php';files[path]=replace_exact((root/path).read_text(),OLD_PRIORITY,NEW_PRIORITY)
 path='tests/published-expansion20-monitor-smoke.php';text=(root/path).read_text()
 text=replace_exact(text,'count(array_unique(array_merge($oldInput,$ids)))','count(array_unique(array_merge($oldInput,$ids,'+F32+')))')
 # Both the fixture priority and the actual-caller assertion retain every old check.
 files[path]=replace_exact(text,OLD_PRIORITY,NEW_PRIORITY,2)
 path='.github/workflows/collect-anytour-tours.yml';oldworkflow=(root/path).read_text()
 text=replace_exact(oldworkflow,'ref: db99af43e8ab11b3675fdbf7875da6e8ea6849ee','ref: '+SOURCE,2)
 text=replace_exact(text,"      - 'tests/published-expansion20-monitor-smoke.php'","      - 'tests/published-expansion20-monitor-smoke.php'\n      - 'tests/published-expansion32-monitor-smoke.php'",2)
 text=replace_exact(text,'          php tests/published-expansion20-monitor-smoke.php','          php tests/published-expansion20-monitor-smoke.php\n          php tests/published-expansion32-monitor-smoke.php')
 text=replace_exact(text,'          php hotel-price-publisher/tools/direct_demand/operations/test-monitor-price-expansion20.php','          php hotel-price-publisher/tools/direct_demand/operations/test-monitor-price-expansion20.php\n          php hotel-price-publisher/tools/direct_demand/operations/test-monitor-price-expansion32.php')
 # The producer, credentials, budget, scheduler and deployment steps remain byte-exact apart from the second source pin.
 before=oldworkflow.split('  collect-production:\n',1)[1];after=text.split('  collect-production:\n',1)[1]
 assert after.replace(SOURCE,'db99af43e8ab11b3675fdbf7875da6e8ea6849ee')==before
 assert text.split('  schedule:\n',1)[1].split('\njobs:',1)[0]==oldworkflow.split('  schedule:\n',1)[1].split('\njobs:',1)[0]
 files[path]=text
 files['tests/published-expansion32-monitor-smoke.php']='''<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/published-hotels-monitor-v1.php';
require_once __DIR__.'/../v2/data/top500-monthly-plan-v1.php';
require_once __DIR__.'/../app/integrations/anex-search-mapping-registry.php';
$checks32=0;
function check32(bool $ok,string $reason):void{global $checks32;$checks32++;if(!$ok)throw new RuntimeException($reason);}
$ids='''+json.dumps(IDS,separators=(',',':'))+''';
check32(v2_published_expansion32_monitor_ids_20261116()===$ids,'exact32_scope_changed');
$old20=v2_published_expansion20_monitor_ids_20261116();
check32(count($ids)===32&&count(array_unique($ids))===32&&array_intersect($ids,$old20)===[],'new32_duplicates_or_replay');
check32(!in_array(108165,$ids,true),'held_soneva_secret_admitted');
$fixtureRows=array_map(static fn(int $id):array=>['anex_hotel_id'=>$id,'catalog_hotel_id'=>$id,'existing_catalog_hotel_id'=>$id,'match_class'=>'exact','approval_policy'=>'owner_exact_and_strong_20260908','enabled'=>1,'scope'=>'preview'],v2_top500_anex_ids());
$registry=AnyTourAnexSearchMappingRegistry::fromRows($fixtureRows);
$oldInput=array_values(array_unique(array_merge(v2_collection_priority_hotel_ids($registry),v2_sites_hotel_monitor_ids_20261111(),$old20)));
$all=v2_all_hotel_monitor_input_ids($registry);
check32(array_slice($all,0,count($oldInput))===$oldInput,'old_input_prefix_changed');
check32($all===array_values(array_unique(array_merge($oldInput,$ids))),'all_input_union_changed');
$countries=array_fill_keys($ids,8);$countries[548]=2;$countries[2947]=2;$countries[4032]=16;$countries[43675]=12;$countries[218]=1;$countries[63191]=9;
check32(array_count_values($countries)===[2=>2,16=>1,12=>1,8=>26,1=>1,9=>1],'new32_country_scope_changed');
$oldCountries=[88169=>2,547=>2,15774=>9,12125=>8,57335=>8,2480=>8,2417=>8,2418=>8,2468=>8,12127=>8,27481=>9,23289=>9,50262=>8,72939=>8,2489=>8,85417=>8,2466=>8,2460=>8,99713=>9,43676=>12];
$baseRows=[['id'=>200001,'country_id'=>4,'country_name'=>'Fixture base','is_active'=>1]];
$newRows=array_map(static fn(int $id):array=>['id'=>$id,'country_id'=>$countries[$id],'country_name'=>'Fixture country','is_active'=>1],$ids);
$oldRows=array_map(static fn(int $id):array=>['id'=>$id,'country_id'=>$oldCountries[$id],'country_name'=>'Fixture country','is_active'=>1],$old20);
$departures=array_map(static fn(int $id):array=>['departure_id'=>1,'country_id'=>$id,'is_active'=>1],[4,2,9,8,12,16,1]);
$before=v2_top500_monthly_plan([200001],$baseRows,$departures,'2026-11-16',12,1);
$cohort='published_expansion32_20261116';
$missing=v2_append_published_hotel_monthly_plan($before,$baseRows,$departures,'2026-11-16',12,1,true);
check32($missing['targets']===$before['targets']&&$missing['published_hotels']['cohorts'][$cohort]['searchable']===0,'missing32_blocks_old');
$full=v2_append_published_hotel_monthly_plan($before,array_merge($baseRows,$newRows),$departures,'2026-11-16',12,1,true);
check32($full['published_hotels']['cohorts'][$cohort]['searchable']===32&&$full['hotel_count']===33,'new32_missing');
check32(array_slice($full['targets'],0,count($before['targets']))===$before['targets'],'base_targets_changed');
$oldPlan=v2_append_published_hotel_monthly_plan($before,array_merge($baseRows,$oldRows),$departures,'2026-11-16',12,1,true);
$combined=v2_append_published_hotel_monthly_plan($before,array_merge($baseRows,$oldRows,$newRows),$departures,'2026-11-16',12,1,true);
check32(array_slice($combined['targets'],0,count($oldPlan['targets']))===$oldPlan['targets'],'previous20_targets_regrouped');
check32($combined['published_hotels']['cohorts']['published_expansion20_20261116']['searchable']===20&&$combined['published_hotels']['cohorts'][$cohort]['searchable']===32&&$combined['hotel_count']===53,'old20_or_new32_lost');
$seen=[];$covered=[];$allCountries=$countries+$oldCountries+[200001=>4];
foreach($combined['targets'] as $target){
 check32(count($target['hotel_ids'])<=30,'supplier_batch_cap_changed');$cs=[];
 foreach($target['hotel_ids'] as $id){$key=$target['date_from'].':'.$target['date_to'].':'.$id;check32(!isset($seen[$key]),'duplicate_hotel_window');$seen[$key]=true;$covered[$id]=true;$cs[$allCountries[$id]]=true;}
 check32(count($cs)===1,'countries_mixed');
}
check32(array_diff(array_merge($old20,$ids),array_keys($covered))===[],'cohort_coverage_lost');
$old=$oldPlan['targets'][0];$now=strtotime('2026-11-16 12:00:00 UTC');
$attempt=['id'=>900,'target_key'=>$old['target_key'],'status'=>'started','search_id'=>123456,'started_epoch'=>$now-60,'finished_epoch'=>0];
$priority=array_merge(v2_sites_hotel_monitor_ids_20261111(),$old20,$ids);
$queue=v2_top500_monthly_queue($combined['targets'],[$attempt],$now,24,$priority);
check32($queue['targets'][0]['resume_search_id']===123456&&$queue['targets'][0]['resume_attempt_id']===900,'existing_search_not_resumed_first');
foreach($queue['targets'] as $t)if(array_intersect($t['hotel_ids'],$ids)!==[])check32($t['_published']===true,'new32_not_prioritized');
$partialRows=array_values(array_filter($newRows,static fn(array $r):bool=>$r['id']!==548));
$partial=v2_append_published_hotel_monthly_plan($before,array_merge($baseRows,$partialRows),$departures,'2026-11-16',12,1,true);
check32($partial['published_hotels']['cohorts'][$cohort]['searchable']===31,'missing_one_blocks_peers');
$inactive=$newRows;$inactive[0]['is_active']=0;
$partial=v2_append_published_hotel_monthly_plan($before,array_merge($baseRows,$inactive),$departures,'2026-11-16',12,1,true);
check32($partial['published_hotels']['cohorts'][$cohort]['searchable']===31,'inactive_hotel_counted');
$noMv=array_values(array_filter($departures,static fn(array $r):bool=>$r['country_id']!==8));
$partial=v2_append_published_hotel_monthly_plan($before,array_merge($baseRows,$newRows),$noMv,'2026-11-16',12,1,true);
check32($partial['published_hotels']['cohorts'][$cohort]['searchable']===6,'missing_route_blocks_other_countries');
$without=v2_append_published_hotel_monthly_plan($before,array_merge($baseRows,$newRows),$departures,'2026-11-16',12,1,false);
check32(!isset($without['published_hotels']['cohorts'][$cohort]),'new_cohort_ignores_existing_opt_in');
$caller=file_get_contents(__DIR__.'/../v2/data/top500-daily-collector-v1.php');
check32(str_contains($caller,"'''+NEW_PRIORITY+'''"),'actual_caller_missing32');
$workflow=file_get_contents(__DIR__.'/../.github/workflows/collect-anytour-tours.yml');
check32(substr_count($workflow,'ref: '''+SOURCE+'''')===2,'publisher_source_not_pinned_twice');
check32(str_contains($workflow,"- cron: '12 * * * *'")&&str_contains($workflow,"MONTHLY_BUDGET_VALUE: $"."{{ inputs.monthly_budget || '64' }}"),'schedule_or_budget_changed');
echo 'PUBLISHED_EXPANSION32_OK checks='.$checks32.' exact_native_ids=32 old20_targets=preserved existing_search=resumed supplier_calls=0 writes=0',PHP_EOL;
'''
 # Extract precisely the final canonical validation shell, not a replacement test list.
 workflow=files['.github/workflows/collect-anytour-tours.yml']
 marker='      - name: Validate daily freshness and full-month TOP500 coverage\n        shell: bash\n        run: |\n'
 assert workflow.count(marker)==1
 lines=[]
 for line in workflow.split(marker,1)[1].splitlines():
  if line and not line.startswith('          '):break
  lines.append(line[10:] if line.startswith('          ') else '')
 assert 'echo ANYTOUR_TOP500_MONTHLY_TOOLING_OK' in lines
 (out/'canonical-validate.sh').write_text('\n'.join(lines)+'\n')
 manifest=[]
 for path,text in files.items():
  raw=text.encode();dest=root/path;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(raw)
  copy=out/'files'/path;copy.parent.mkdir(parents=True,exist_ok=True);copy.write_bytes(raw)
  manifest.append({'path':path,'sha':hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest(),'sha256':hashlib.sha256(raw).hexdigest(),'bytes':len(raw)})
 (out/'manifest.json').write_text(json.dumps({'base':BASE,'publisher':SOURCE,'files':manifest,'supplier_calls':0,'production_writes':0},indent=2)+'\n')
 print(json.dumps({'files':len(files),'publisher':SOURCE,'new_cohort':32,'old_cohorts_preserved':True,'production_calls':0}))
if __name__=='__main__':main()
