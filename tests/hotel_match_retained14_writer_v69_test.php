<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_retained14_writer_v69.php';
$n=0;
function check69(bool $ok,string $name):void {global $n;if(!$ok)throw new RuntimeException('TEST_'.$name);++$n;}
function reject69(callable $fn,string $reason):void {try{$fn();}catch(RuntimeException $e){check69($e->getMessage()===$reason,'reason_'.$reason);return;}throw new RuntimeException('MISSING_REJECTION_'.$reason);}
v69_need(count($argv)>=5,'four_input_paths_required');
$raw=array_map('file_get_contents',array_slice($argv,1,4));$b=v69_prepare(...$raw);
check69(array_keys($b['ready'])===array_keys(V69_PAIRS),'exact_14');check69(count($b['hold'])===15,'hold_15');check69(array_intersect_key($b['ready'],$b['hold'])===[],'disjoint');check69(!$b['execution_authorized']&&!$b['safe_to_write_now'],'not_acceptance');
foreach(range(0,3) as $i){$x=$raw;$x[$i].="\n";reject69(fn()=>v69_prepare(...$x),'input_hash');}
$planned=[];foreach(V69_PAIRS as $id=>$external){$ej=v69_json(['test_only'=>true,'id'=>$id]);$planned[]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$external,'local_hotel_id'=>$id,'decision_status'=>'accepted','catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$ej),'evidence_json'=>$ej];}
$preserved=['operator_115|777'=>['supplier_namespace'=>'operator_115','external_hotel_id'=>'777','local_hotel_id'=>null,'decision_status'=>'rejected','catalog_sha256'=>str_repeat('b',64),'evidence_sha256'=>hash('sha256','{}'),'evidence_json'=>'{}']];
$after=[...array_values($preserved),...$planned];$read=v69_verify($preserved,$planned,$after);check69(count($read)===14,'verified_rows');
$x=$after;$x[0]['decision_status']='accepted';reject69(fn()=>v69_verify($preserved,$planned,$x),'preexisting_identity_changed');
$x=$after;array_pop($x);reject69(fn()=>v69_verify($preserved,$planned,$x),'identity_count_delta');
$x=$after;$x[]=$x[0];reject69(fn()=>v69_verify($preserved,$planned,$x),'duplicate_identity');
foreach(V69_FIELDS as $field){$x=$after;$x[1][$field]=$field==='local_hotel_id'?999:(string)$x[1][$field].'changed';$expected=in_array($field,['supplier_namespace','external_hotel_id'],true)?'written_identity_missing':'written_identity_mismatch';reject69(fn()=>v69_verify($preserved,$planned,$x),$expected);}
$x=$planned;$x[0]['evidence_sha256']=str_repeat('f',64);$a=[...array_values($preserved),...$x];reject69(fn()=>v69_verify($preserved,$x,$a),'written_evidence_hash');
$dir=sys_get_temp_dir().'/match-v69-test-'.bin2hex(random_bytes(8));mkdir($dir,0700);$path=$dir.'/receipt.json';
try{$sha=v69_save($path,['unit_test'=>true]);check69(hash_file('sha256',$path)===$sha,'durable_receipt');reject69(fn()=>v69_save($path,['unit_test'=>false]),'exclusive_record');symlink($path,$dir.'/link');reject69(fn()=>v69_read($dir.'/link'),'input_file');}finally{@unlink($dir.'/link');@unlink($path);rmdir($dir);}
if(!in_array('--pure',$argv,true)){
    require_once __DIR__.'/../scripts/diagnostics/hotel_match_live234_v65_current_package_v67.php';v67_package_check();
    $current=['hotels'=>[],'live'=>[],'manual'=>[],'anex'=>[],'source'=>[],'target'=>[],'registry'=>[]];
    foreach($b['ready'] as $id=>$entry){$current['hotels'][$id]=['id'=>$id,'name'=>$entry['dossier']['hotel_name'],'country_name'=>'fixture_country','is_active'=>1];$current['live'][$id]=true;$current['anex'][$id]=array_map('strval',$entry['dossier']['direct_anex_ids']);}
    check69(count(v69_assess($b,$current))===14,'real_retained_proof_all14');
    foreach(V69_PAIRS as $id=>$external){
        $mutations=[];
        $x=$current;$x['manual'][$id]=true;$mutations[]=$x;
        $x=$current;unset($x['live'][$id]);$mutations[]=$x;
        $x=$current;$x['hotels'][$id]['is_active']=0;$mutations[]=$x;
        $x=$current;$x['hotels'][$id]['country_name']='Россия';$mutations[]=$x;
        $x=$current;$x['anex'][$id]=[];$mutations[]=$x;
        $x=$current;$x['anex'][$id]=['99999999'];$mutations[]=$x;
        foreach(['pending','rejected','accepted'] as $status){$x=$current;$x['source'][$external]=[['decision_status'=>$status,'local_hotel_id'=>999,'evidence_valid'=>true]];$mutations[]=$x;}
        $x=$current;$x['target'][$id]=[['external_hotel_id'=>'999999999','decision_status'=>'accepted','evidence_valid'=>true]];$mutations[]=$x;
        $entry=$b['ready'][$id];foreach($entry['candidate']['lanes'] as $ns=>$lane){if(count($lane['native_ids'])!==1)continue;$key=$ns.'|'.$lane['native_ids'][0];
            foreach([['accepted',false,$id],['rejected',true,$id],['accepted',true,999]] as [$status,$valid,$target]){$x=$current;$x['registry'][$key]=[['decision_status'=>$status,'local_hotel_id'=>$target,'evidence_valid'=>$valid,'catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>str_repeat('b',64)]];$mutations[]=$x;}
        }
        foreach($mutations as $x)reject69(fn()=>v69_assess($b,$x),'current_guard_'.$id);
        $x=$current;$same=['external_hotel_id'=>$external,'decision_status'=>'accepted','local_hotel_id'=>$id,'evidence_valid'=>true];$x['source'][$external]=[$same];$x['target'][$id]=[$same];reject69(fn()=>v69_assess($b,$x),'current_guard_'.$id);
    }
    $x=$b;unset($x['ready'][11742]);reject69(fn()=>v69_assess($x,$current),'bundle_ready_set');
    $x=$b;$x['ready'][11742]['candidate']['catalog_id']='999';reject69(fn()=>v69_assess($x,$current),'bundle_evidence_drift');
}
echo v69_json(['test'=>'retained14_writer_v69','assertions'=>$n,'mode'=>in_array('--pure',$argv,true)?'pure_seal_and_readback_only':'real_inputs_plus_current_classifier','database_writes'=>0,'provider_http_calls'=>0])."\n";
