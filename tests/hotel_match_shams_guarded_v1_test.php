<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/diagnostics/hotel_match_shams_guarded_v1.php';
$checks=0;
function shgcheck(bool $ok,string $name): void {global $checks;++$checks;if(!$ok)throw new RuntimeException('shams_guarded_test:'.$name);}

$source=['id'=>'9501','name'=>'SHAMS SAFAGA','state'=>'Египет','town'=>'Шамс Сафага'];
$raw=w76_json(['source'=>$source,'reason'=>'native_review']);
$row=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'9501','local_hotel_id'=>null,'decision_status'=>'pending',
    'catalog_sha256'=>str_repeat('a',64),'evidence_sha256'=>hash('sha256',$raw),'evidence_json'=>$raw];
$hotel=['id'=>420,'name'=>'SHAMS SAFAGA','country_id'=>5,'country_name'=>'Египет','region_name'=>'Марса Алам',
    'subregion_name'=>null,'category'=>4,'is_active'=>1,'latitude'=>24.6907006,'longitude'=>35.0835745];
$entry=['id'=>420,'catalog_id'=>'9501','prior'=>array_diff_key($row,['evidence_json'=>true]),'history_sha256'=>w76_hash($source),
    'target'=>$hotel,'operator_facts'=>[['namespace'=>'operator_5','native_id'=>'835'],['namespace'=>'operator_342','native_id'=>'24402']],
    'proofs'=>[['namespace'=>'operator_342','native_id'=>'24402','samo'=>['raw_verified'=>true],'tv'=>[['state'=>'saved_tv_proof_verified']]]],
    'geo_source'=>['town'=>'Марса Алам','townKey'=>'5821','namespace'=>'operator_342','native_id'=>'24402'],
    'geo_receipt_sha256'=>str_repeat('b',64)];
$context=pm1_context([$row]);$context['hotels']=[420=>$hotel];
shgcheck(ng110_classify($entry,$context)['reasons']===['geography_requires_review'],'old_history_still_holds');
shgcheck(shg_classify($entry,$context)===['status'=>'ready','reasons'=>[]],'independent_concrete_geo_admits');

$c=$context;$c['hotels'][420]['region_name']='Хургада';$entryDrift=$entry;$entryDrift['target']=$c['hotels'][420];
shgcheck(shg_classify($entryDrift,$c)['status']==='hold','current_target_geo_drift_holds');
$c=$context;$c['manual'][420]=true;shgcheck(in_array('protected_target',shg_classify($entry,$c)['reasons'],true),'manual_holds');
$c=$context;$c['exclusions'][420]=true;shgcheck(in_array('protected_target',shg_classify($entry,$c)['reasons'],true),'exclusion_holds');
$c=$context;$c['targets'][420][]=['external_hotel_id'=>'other'];shgcheck(in_array('target_catalog_occupied',shg_classify($entry,$c)['reasons'],true),'occupied_holds');
$c=$context;$c['sources']['9501'][0]['decision_status']='accepted';shgcheck(in_array('source_not_pending_null',shg_classify($entry,$c)['reasons'],true),'accepted_not_replaced');
$c=$context;$c['hotels'][420]['is_active']=0;shgcheck(in_array('target_missing_or_inactive',shg_classify($entry,$c)['reasons'],true),'inactive_holds');
$c=$context;$c['hotels'][420]['country_name']='Турция';$entryCountry=$entry;$entryCountry['target']=$c['hotels'][420];
shgcheck(in_array('country_conflict',shg_classify($entryCountry,$c)['reasons'],true),'country_holds');
$c=$context;$h=json_decode($c['sources']['9501'][0]['evidence_json'],true);$h['source']['latitude']=1;$h['source']['longitude']=1;
$r=&$c['sources']['9501'][0];$r['evidence_json']=w76_json($h);$r['evidence_sha256']=hash('sha256',$r['evidence_json']);
$entryCoordinates=$entry;$entryCoordinates['prior']['evidence_sha256']=$r['evidence_sha256'];$entryCoordinates['history_sha256']=w76_hash($h['source']);
shgcheck(in_array('coordinate_conflict_over_5km',shg_classify($entryCoordinates,$c)['reasons'],true),'over_5km_never_removed');
shgcheck(SHG_OP!==(defined('NG110_OP')?NG110_OP:''),'new_operation_not_old_writer');
echo 'SHAMS_GUARDED_PURE_PASS '.$checks."\n";
