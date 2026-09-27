<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_tv_samo_anex_coverage_v2.php';
const C73_OP='hotel-match-post-samo23-census-1971-20260927-v73';
if(($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
hmtsac_need(is_dir($root)&&is_dir($dir)&&basename($dir)===C73_OP,'scope');
$bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');require_once $bootstrap;
$c=hmtsac_execute(v2_data_db());hmtsac_need($c['state']==='completed_read_only_coverage','coverage');
$missing=$c['live_30d']['missing'];$map=[];
foreach($missing['missing_samo'] as $x){$i=(int)$x['tv_hotel_id'];$map[$i]=$x+['needs_samo'=>true,'needs_anex'=>false];}
foreach($missing['missing_anex'] as $x){$i=(int)$x['tv_hotel_id'];if(!isset($map[$i]))$map[$i]=$x+['needs_samo'=>false,'needs_anex'=>false];$map[$i]['needs_anex']=true;}
ksort($map,SORT_NUMERIC);$counts=$c['live_30d']['counts'];hmtsac_need(count($map)===$counts['tv_total']-$counts['full_triple'],'queue');
$out=['operation'=>C73_OP,'state'=>'completed_read_only_post_samo23_census','source_sha'=>$head,'generated_at_utc'=>gmdate('c'),'counts'=>$counts,'remaining_count'=>count($map),'rows'=>array_values($map),'database_writes'=>0,'mapping_writes'=>0,'provider_http_calls'=>0];
$sha=hmtsac_save($dir.'/result.json',$out);hmtsac_save($dir.'/receipt.json',['operation'=>C73_OP,'state'=>$out['state'],'source_sha'=>$head,'result_sha256'=>$sha,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$sha,'database_writes'=>0,'mapping_writes'=>0,'provider_http_calls'=>0]);
echo hmtsac_json(['state'=>$out['state'],'counts'=>$counts,'remaining'=>count($map)])."\n";
