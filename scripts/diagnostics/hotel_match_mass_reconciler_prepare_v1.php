<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_retained_mass47_v94.php';
const MR1_NATIVE_OP='hotel-match-live30-retained-native-union-1971-20260927-v77';
const MR1_NATIVE_SHA='d40fbe2e0240a5df194ac838376a3425a8f4e80f2426a757560fe369011107f7';
const MR1_ACQS=[
 ['hotel-match-newcontext-samoonly200-acquire-1971-20260928-v97','25dbbb4d2c693e52c3dd367300b762da5e8379195649aaf84edb087b29fb3bb6'],
 ['hotel-match-newcontext-samoonly-next200-acquire-1971-20260928-v100','855b296f91da8ea5fc7dfe492a4a972b1a3f7f74891a32e715e2571e8bdf16dc'],
 ['hotel-match-newcontext-samoonly-third200-acquire-1971-20260928-v102','4a7e4343e3fa4ffffc030213b1165402b44ae0c8b8d8b155a2dd24c371ee95c2'],
 ['hotel-match-newcontext-samoonly-final-acquire-1971-20260928-v104','f21434ef4d886bf994e78702c38d823e4e0eeeaed84d21af1d7d019d96f9cc8e'],
 ['hotel-match-anexonly200-acquire-1971-20260928-v109','aa0365674e2f57775cbc7c5f0ab7a2d0781fc2826febc0c55214a726ea0b75a4'],
 ['hotel-match-anexonly-tail-acquire-1971-20260928-v112','ecbba8c5c9dc03f9d4729aabefd0afa1dbfdc67828a66ff429ccb452aab678fd'],
];
function mr1_need(bool $b,string $m):void{if(!$b)throw new RuntimeException($m);}
function mr1_prepare(string $ops):array{
 $v=w84_read($ops.'/'.MR1_NATIVE_OP.'/result.json',MR1_NATIVE_SHA,67108864);
 mr1_need(($v['state']??'')==='completed_retained_native_scan','native_input');
 $native=[];foreach($v['native_facts'] as $f){$ns=(string)$f['supplier_namespace'];if(in_array($ns,['operator_315','operator_342'],true))$native[$ns][(string)$f['native_id']][(string)$f['catalog_id']]=$f;}
 $edges=[];$byNative=[];$byHotelOp=[];$sourceEdges=0;
 foreach(MR1_ACQS as [$op,$sha]){
  $d=w84_read($ops.'/'.$op.'/result.json',$sha,33554432);
  mr1_need(($d['state']??'')==='completed_read_only'&&($d['database_writes']??-1)===0&&($d['mapping_writes']??-1)===0&&($d['provider_calls']??0)>0,'acquisition_input');
  foreach($d['edges']??[] as $i=>$e){
   if(w84_edge_reasons($e)!==[]||count($e['positive_native_candidates']??[])!==1)continue;
   $oid=(int)($e['operator_id']??0);if(!in_array($oid,[13,25,43],true))continue;
   $id=(int)$e['tv_hotel_id'];$n=(string)$e['positive_native_candidates'][0];
   $byNative[$oid][$n][$id]=true;$byHotelOp[$id][$oid][$n]=true;
   $e['source_operation']=$op;$e['source_result_sha256']=$sha;$e['source_json_pointer']='/edges/'.$i;
   $edges[$id][$oid][$n][]=$e;$sourceEdges++;
  }
 }
 $out=['source_edge_count'=>$sourceEdges,'candidates'=>[],'direct'=>[],'held'=>[]];
 foreach($edges as $id=>$opsBy)foreach($opsBy as $oid=>$natives)foreach($natives as $n=>$proofs){
  $why=[];if(count($byNative[$oid][$n]??[])!==1)$why[]='tv_native_collision';if(count($byHotelOp[$id][$oid]??[])!==1)$why[]='tv_operator_multiple_native_ids';
  if($why){$out['held'][]=['local_hotel_id'=>$id,'operator_id'=>$oid,'native_id'=>$n,'reasons'=>$why];continue;}
  if($oid===13){$out['direct'][]=['local_hotel_id'=>$id,'anex_hotel_id'=>$n,'proof'=>$proofs[0]];continue;}
  $ns=$oid===25?'operator_315':'operator_342';$cats=array_keys($native[$ns][$n]??[]);
  if(count($cats)!==1){$out['held'][]=['local_hotel_id'=>$id,'operator_id'=>$oid,'native_id'=>$n,'reasons'=>['samo_native_ambiguous_or_absent']];continue;}
  $cat=(string)$cats[0];try{$raw=w84_raw($ops,$native[$ns][$n][$cat],$cat,$ns,$n);}catch(Throwable){$out['held'][]=['local_hotel_id'=>$id,'catalog_id'=>$cat,'reasons'=>['raw_samo_proof_missing']];continue;}
  $out['candidates'][]=['id'=>$id,'catalog_id'=>$cat,'namespace'=>$ns,'native_id'=>$n,'tv_proof'=>$proofs[0],'samo_proof'=>$raw];
 }
 return$out;
}
