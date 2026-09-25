<?php
declare(strict_types=1);
const R26_OP='hotel-match-common4-retained-context-recovery-1971-20260925-v26r';
const R26_OLD='hotel-match-common4-retained-context-acquire-1971-20260925-v26';
const R26_OLD_SHA='036b64868f17ca2b9f087e998c1064f3f6b1d312c7683bef4768e5f6338844c4';
function r26_need(bool $v,string $w):void{if(!$v)throw new RuntimeException($w);}
function r26_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,256,JSON_THROW_ON_ERROR);r26_need(is_array($v),'json');return$v;}
function r26_json($v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function r26_save(string $p,array $v):string{$raw=r26_json($v)."\n";r26_need(file_put_contents($p,$raw,LOCK_EX)===strlen($raw),'save');return hash('sha256',$raw);}
function r26_id($v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)?$s:null;}
function r26_targets(array $post):array{
  r26_need(($post['operation']??'')==='hotel-match-samo-business-live30-common4-postwrite-1971-20260925-v24','post_op');
  $out=[];foreach(($post['rows']??[]) as $r){
    if(!is_array($r)||($r['mapping_state']??'')!=='mapped_unique'||($r['saved_catalog_state']??'')!=='saved_catalog_ready'||(int)($r['saved_stateinc']??0)!==5)continue;
    if(($r['operator_lanes']['operator_315']['status']??'')!=='missing')continue;
    $cid=r26_id($r['andromeda_catalog_id']??null);$local=(int)($r['local_hotel_id']??0);if($cid!==null&&$local>0)$out[$cid]=$local;
  } return$out;
}
function r26_run(string $old,array $post):array{
  r26_need(is_dir($old)&&basename($old)===R26_OLD,'old_dir');
  $or=r26_load($old.'/result.json');$oq=r26_load($old.'/receipt.json');
  r26_need(hash_file('sha256',$old.'/result.json')===R26_OLD_SHA&&($or['state']??'')==='terminal_failed_no_replay'&&($or['reason']??'')==='exclusive_create','old_result');
  r26_need(($oq['result_sha256']??'')===R26_OLD_SHA&&($oq['no_replay']??false)===true,'old_receipt');
  $targets=r26_targets($post);r26_need(count($targets)>0,'targets');
  $reservations=glob($old.'/context-5-315-7-page-*-reserved.json')?:[];$evidence=glob($old.'/evidence-private/context-5-315-7-page-*.json')?:[];
  $http=glob($old.'/http-*-reserved.json')?:[];sort($reservations,SORT_NATURAL);sort($evidence,SORT_NATURAL);sort($http,SORT_NATURAL);
  r26_need($reservations!==[]&&count($reservations)===count($evidence),'page_membership');
  $native=[];$returned=[];$rows=0;$pcs=[];$pages=[];
  foreach($evidence as $p){
    r26_need(preg_match('/page-([0-9]+)\.json$/D',$p,$m)===1,'page_name');$page=(int)$m[1];$pages[]=$page;
    $v=r26_load($p);$pc=(int)($v['PAGES_COUNT']??-1);r26_need($pc>=0&&$pc<=1000,'pages_count');$pcs[$pc]=true;
    foreach(($v['PRICES']??[]) as $row){if(!is_array($row)||(int)($row['operatorKey']??0)!==315)continue;$rows++;
      $cid=r26_id($row['hotelKey']??null);if($cid===null||!isset($targets[$cid])||(string)($row['isOperatorHotelKey']??'')!=='0')continue;
      $returned[$cid]=true;$orig=is_array($row['original']??null)?$row['original']:[];$nid=r26_id($orig['hotelKey']??null);if($nid!==null)$native[$cid][$nid]=true;
    }
  }
  r26_need(count($pcs)===1,'pages_count_consistency');$pc=(int)array_key_first($pcs);sort($pages,SORT_NUMERIC);r26_need($pages===range(1,$pc),'not_fully_drained');
  $edges=[];$counts=[];$unique=[];$collisions=0;
  foreach($targets as $cid=>$local){$ids=array_keys($native[$cid]??[]);sort($ids,SORT_NATURAL);$state=count($ids)===1?'captured_single_native':(count($ids)>1?'captured_ambiguous_native':(isset($returned[$cid])?'catalog_only':'not_returned_in_context'));
    $edges[]=['catalog_id'=>$cid,'local_hotel_id'=>$local,'stateinc'=>5,'operator_id'=>315,'namespace'=>'operator_315','state'=>$state,'positive_native_candidates'=>$ids,'safe_to_write_now'=>false];
    $counts[$state]=($counts[$state]??0)+1;if($state==='captured_single_native')$unique['operator_315|'.$ids[0]][$local]=true;
  }
  foreach($unique as $locals)if(count($locals)>1)$collisions++;ksort($counts);
  return ['operation'=>R26_OP,'state'=>'completed_supplier_free_v26_salvage','source_operation'=>R26_OLD,'source_result_sha256'=>R26_OLD_SHA,'consumed_context'=>['operator_id'=>315,'stateinc'=>5,'checkin_beg'=>'20260929','checkin_end'=>'20261005','nights'=>7,'adults'=>2,'meal'=>null],
    'target_edge_count'=>count($targets),'pages_count'=>$pc,'pages_drained'=>count($pages),'price_rows_seen'=>$rows,'provider_http_reservations_observed'=>count($http),'edge_state_counts'=>$counts,'single_native_source_collision_count'=>$collisions,'edges'=>$edges,
    'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'raw_payload_exported'=>false,'safe_to_write_now'=>false];
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
 if(in_array('--self-test',$argv??[],true)){echo"MATCH_V26R_SELFTEST_OK\n";exit;}
 $dir=(string)getenv('MATCH_OPERATION_DIR');$old=(string)getenv('MATCH_OLD_DIR');$post=(string)getenv('MATCH_POST_RESULT');r26_need(is_dir($dir)&&basename($dir)===R26_OP&&is_file($post),'scope');
 try{$out=r26_run($old,r26_load($post));$h=r26_save($dir.'/result.json',$out);r26_save($dir.'/receipt.json',['operation'=>R26_OP,'state'=>$out['state'],'result_sha256'=>$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);echo r26_json(['state'=>$out['state'],'target_edge_count'=>$out['target_edge_count'],'pages_count'=>$out['pages_count'],'pages_drained'=>$out['pages_drained'],'price_rows_seen'=>$out['price_rows_seen'],'provider_http_reservations_observed'=>$out['provider_http_reservations_observed'],'edge_state_counts'=>$out['edge_state_counts'],'single_native_source_collision_count'=>$out['single_native_source_collision_count']])."\n";}
 catch(Throwable$e){fwrite(STDERR,$e->getMessage()."\n");exit(2);}
}
