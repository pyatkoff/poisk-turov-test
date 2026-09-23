<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_common4_tail_writer_v15.php';
function twok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function row(int $i):array{
  $tv=1000+$i;$ns=$i%3===1?'bgoperator':($i%3===2?'operator_315':'operator_342');$op=HMC14_ALLOWED[$ns];
  $a=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)(900000+$i),'local_hotel_id'=>$tv,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>str_repeat('b',64)];
  return ['kind'=>'identity','writer_ready'=>true,'status'=>'current_missing_edge','anchor_state'=>'canonical_anchor_ok','safe_to_write_now'=>false,
    'supplier_namespace'=>$ns,'external_hotel_id'=>(string)(800000+$i),'tv_hotel_id'=>$tv,'operator_id'=>$op,'source_operation'=>'x',
    'source_result_sha256'=>str_repeat('c',64),'batch'=>1,'search_id_sha256'=>str_repeat('d',64),'tour_id_sha256'=>str_repeat('e',64),'operator_link_sha256'=>str_repeat('f',64),
    'catalog_hotel'=>['id'=>$tv,'name'=>'H','country_id'=>4,'country_name'=>'Turkey','region_id'=>1,'region_name'=>'R','subregion_id'=>null,'subregion_name'=>'','category'=>'5','is_active'=>1],
    'unanimous_catalog_sha256'=>str_repeat('a',64),'anchors'=>[$a]];
}
$rows=[];for($i=1;$i<=367;$i++)$rows[]=row($i);
$a=['operation'=>HMC14_AUDIT_OP,'state'=>'completed_read_only_mass_current','writer_ready_counts'=>['identity'=>367,'anex'=>41],'rows'=>$rows];
$m1=hmc14_manifest($a,0,300);$m2=hmc14_manifest($a,300,67);
twok(count($m1)===300&&count($m2)===67,'counts');
$k1=array_map(fn($x)=>$x['supplier_namespace'].'|'.$x['external_hotel_id'],$m1);$k2=array_map(fn($x)=>$x['supplier_namespace'].'|'.$x['external_hotel_id'],$m2);
twok(count(array_intersect($k1,$k2))===0,'disjoint');
echo "MATCH_COMMON4_TAIL_WRITER_V15_TEST_OK\n";
