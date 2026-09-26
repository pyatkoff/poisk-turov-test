<?php
declare(strict_types=1);
define('OPL_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_operator_pending_local_bridge.php';
$n=0;
function test(bool $value,string $name): void {global $n;if(!$value)throw new RuntimeException('TEST_'.$name);$n++;}
function row(array $e,array $meta=[]):array{$raw=opb_json($e);return $meta+['evidence_json'=>$raw,'evidence_sha256'=>hash('sha256',$raw)];}
function fixture(string $op='315'):array {
    $f=['operator_key'=>$op,'native_hotel_id'=>'224757','andromeda_hotel_id'=>'133121','country_id'=>4,'hotel_name'=>'Example Hill Hotel','original_name'=>'Example Hill','action'=>'price','is_operator_hotel_key'=>false,'request_sha256'=>str_repeat('a',64),'response_sha256'=>str_repeat('b',64)];
    $e=['schema'=>'operator-original-price-bridge/1','source_result_sha256'=>OPL_CAPTURE_SHA,'country_id'=>4,'source'=>['id'=>'224757','operator_key'=>$op],'decision'=>['state'=>'pending','local_hotel_id'=>null],'provider_bridges'=>[$f]];
    $r=row($e,['supplier_namespace'=>'operator_'.$op,'external_hotel_id'=>'224757','decision_status'=>'pending','local_hotel_id'=>null,'catalog_sha256'=>OPL_CAPTURE_SHA]);$s=array_diff_key($r,['evidence_json'=>1,'decision_status'=>1,'local_hotel_id'=>1]);
    $ae=['prior_evidence'=>['source'=>['latitude'=>36.8,'longitude'=>28.8]],'promotion'=>['operation_id'=>OPB_OP,'rule'=>'price_original_exact_anex_native_current_authority','native_anex_hotel_id'=>'12345','target_local_hotel_id'=>17369]];
    $ar=row($ae,['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'133121','decision_status'=>'accepted','local_hotel_id'=>17369]);$proof=['andromeda_hotel_id'=>'133121','local_hotel_id'=>17369,'native_anex_hotel_id'=>'12345','evidence_sha256'=>$ar['evidence_sha256']];
    return [$r,$s,['133121'=>$proof],$ar,['id'=>17369,'country_id'=>4,'name'=>'EXAMPLE HILL FAMILY RESORT','latitude'=>36.8,'longitude'=>28.8,'is_active'=>1]];
}
[$r,$s,$a,$ar,$hotel]=fixture();$b=opl_binding($r,$s,$a);
test($b['ok'],'direct_binding');test(opl_anchor($ar,$a['133121'],$hotel,$b)['ok'],'current_anchor');
foreach(['accepted','conflict','rejected','manual'] as $state){$x=$r;$x['decision_status']=$state;test(!opl_binding($x,$s,$a)['ok'],'preserve_'.$state);}
$x=$r;$x['local_hotel_id']=999;test(!opl_binding($x,$s,$a)['ok'],'existing_local');
$x=$r;$x['evidence_sha256']=str_repeat('c',64);test(!opl_binding($x,$s,$a)['ok'],'changed_snapshot');
$x=$r;$x['catalog_sha256']=str_repeat('c',64);test(!opl_binding($x,$s,$a)['ok'],'changed_catalog');
$x=$r;$x['supplier_namespace']='operator_5';test(!opl_binding($x,$s,$a)['ok'],'anex_not_owned');
test(!opl_binding($r,$s,[])['ok'],'no_anchor');
$e=json_decode($r['evidence_json'],true);$e['provider_bridges'][0]['is_operator_hotel_key']=true;$x=row($e,array_diff_key($r,['evidence_json'=>1,'evidence_sha256'=>1]));$ss=$s;$ss['evidence_sha256']=$x['evidence_sha256'];test(!opl_binding($x,$ss,$a)['ok'],'operator_scoped');
$e=json_decode($r['evidence_json'],true);$e['provider_bridges'][0]['operator_key']='342';$x=row($e,array_diff_key($r,['evidence_json'=>1,'evidence_sha256'=>1]));$ss=$s;$ss['evidence_sha256']=$x['evidence_sha256'];test(!opl_binding($x,$ss,$a)['ok'],'native_namespace');
$e=json_decode($r['evidence_json'],true);$e['provider_bridges'][]=$e['provider_bridges'][0];$e['provider_bridges'][1]['andromeda_hotel_id']='999';$x=row($e,array_diff_key($r,['evidence_json'=>1,'evidence_sha256'=>1]));$ss=$s;$ss['evidence_sha256']=$x['evidence_sha256'];test(!opl_binding($x,$ss,$a)['ok'],'ambiguous_bridge');
$x=$ar;$x['decision_status']='pending';test(!opl_anchor($x,$a['133121'],$hotel,$b)['ok'],'pending_anchor');
$x=$ar;$x['local_hotel_id']=123;test(!opl_anchor($x,$a['133121'],$hotel,$b)['ok'],'changed_anchor_local');
$x=$hotel;$x['country_id']=1;test(!opl_anchor($ar,$a['133121'],$x,$b)['ok'],'country_conflict');
$x=$hotel;$x['is_active']=0;test(!opl_anchor($ar,$a['133121'],$x,$b)['ok'],'inactive');
$x=$hotel;$x['latitude']=37.5;test(!opl_anchor($ar,$a['133121'],$x,$b)['ok'],'coordinate_gt5km');
test(!opl_compatible(['Perdikia Beach Hotel'],'PERDIKIA HILL FAMILY RESORT'),'beach_hill');
test(!opl_compatible(['Example North'],'Example South'),'north_south');
test(!opl_compatible(['Grand Apple'],'Grand Banana'),'brand_only');
test(opl_compatible(['Example Hotel'],'EXAMPLE RESORT SPA'),'generic_words');
[$rr,$ss,$aa]=fixture('342');test(opl_binding($rr,$ss,$aa)['ok'],'intourist_namespace');
$thrown=false;try{$x=$r;$x['evidence_json'].=' ';opl_evidence($x);}catch(RuntimeException $e){$thrown=true;}test($thrown,'evidence_tamper');
echo "$n pending-local focused tests PASS; database_calls=0\n";
