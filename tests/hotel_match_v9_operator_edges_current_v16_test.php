<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_v9_operator_edges_current_v16.php';
function t16(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
t16(hm16_f4('https://bgoperator.ru/price.shtml?F4=100&x=1&F4=200')===['100','200'],'repeated_f4');
$bad=false;try{hm16_f4('https://evil.example/?F4=100');}catch(RuntimeException){$bad=true;}t16($bad,'bad_origin');
$edge=['supplier_namespace'=>'bgoperator','external_hotel_id'=>'100','tv_hotel_id'=>5,'safe_to_write_now'=>false];
$cat=[5=>['id'=>5,'is_active'=>1,'country_name'=>'Турция','region_name'=>'Side','subregion_name'=>'']];
$saved=['source'=>['bgoperator|100'=>[5=>true]],'target'=>['bgoperator|5'=>['100'=>true]]];
$x=hm16_classify($edge,$cat,[],[],[5=>[['decision_status'=>'accepted']]],[],$saved);
t16($x['status']==='current_missing_edge'&&$x['writer_ready']===true,'ready');
$x=hm16_classify($edge,$cat,['bgoperator|100'=>[['decision_status'=>'accepted','local_hotel_id'=>5]]],[],[5=>[['decision_status'=>'accepted']]],[],$saved);
t16($x['status']==='resolved_same'&&!$x['writer_ready'],'resolved_same');
$x=hm16_classify($edge,$cat,[],['bgoperator|5'=>[['external_hotel_id'=>'999','decision_status'=>'accepted','local_hotel_id'=>5]]],[5=>[['decision_status'=>'accepted']]],[],$saved);
t16($x['status']==='target_namespace_occupied_other','target_occupied');
$x=hm16_classify($edge,$cat,[],[],[],[],$saved);
t16($x['status']==='current_missing_edge'&&$x['writer_ready']===false,'missing_anchor_not_ready');
$src=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_v9_operator_edges_current_v16.php');
t16(!str_contains($src,'curl_')&&!str_contains($src,'file_get_contents(\'http'),'no_provider_http');
echo "MATCH_V9_OPERATOR_EDGES_CURRENT_V16_TEST_OK\n";
