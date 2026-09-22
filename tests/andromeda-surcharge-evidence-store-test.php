<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/andromeda-surcharge-evidence-store.php';

function estore_ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function estore_offer(string $id,string $amount):array{return [
    'provider'=>'andromeda','offer_ref'=>'offer_'.hash('sha256',$id),'operator_ref'=>'7','local_hotel_id'=>1,
    'check_in'=>'2026-10-30','nights'=>7,'adults'=>2,'children'=>0,'price'=>['amount'=>$amount,'currency'=>'RUB'],
    'transport_context'=>['freight_external'=>true,'program_ref'=>'101','tour_ref'=>'202','spo_ref'=>'spo-'.$id],
];}
function estore_fact(array $offer,string $surcharge):array{
    $base=$offer['price']['amount'];$sum=(string)((int)$base+(int)$surcharge);
    return ['schema_version'=>1,'provider'=>'andromeda','state'=>'estimated','search_price'=>['amount'=>$base,'currency'=>'RUB'],
        'party_surcharge'=>['amount'=>$surcharge,'currency'=>'RUB','source'=>'andromeda_get_flights_transport'],
        'search_price_with_surcharge'=>['amount'=>$sum,'currency'=>'RUB','source'=>'derived_search_estimate'],
        'surcharge_scope'=>'party','arithmetic_applied'=>true,'final_price_verified'=>false];
}
$root=sys_get_temp_dir().'/andromeda-evidence-store-'.bin2hex(random_bytes(5));$dir=$root.'/searches';mkdir($dir,0700,true);
$write=static function(string $path,array $value):bool{
    $tmp=$path.'.tmp';file_put_contents($tmp,json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));chmod($tmp,0600);return rename($tmp,$path);
};
$request=['params'=>['departureId'=>'1','countryId'=>'4']];
$source=estore_offer('source','100000');
$provenance=['source_sha'=>str_repeat('a',40),'source_search_ref'=>str_repeat('b',64),'source_offer_ref'=>$source['offer_ref']];
try{
    $evidence=AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,estore_fact($source,'5000'),1000,1300);
    estore_ok(is_array($evidence),'evidence fixture');
    $created=AnyTourAndromedaSurchargeEvidenceStoreV1::save($dir,$evidence,$provenance,$write);
    estore_ok($created['status']==='created'&&$created['written']===true,'cache create');
    estore_ok(is_file($created['path'])&&basename($created['path'])==='andromeda-surcharge-group-v2-'.substr($evidence['group_key'],strlen('andromeda-surcharge-v3:')).'.json','cache path');

    $same=AnyTourAndromedaSurchargeEvidenceStoreV1::save($dir,$evidence,$provenance,$write);
    estore_ok($same['status']==='unchanged'&&$same['written']===false,'idempotent save');

    $target=estore_offer('target','110000');$target['local_hotel_id']=99;$target['transport_context']['spo_ref']='other-spo';
    $applied=AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($dir,$target,$request,1100);
    estore_ok(is_array($applied)&&$applied['search_price_with_surcharge']['amount']==='115000','cache rebase');
    estore_ok($applied['final_price_verified']===false,'cache must stay estimate');

    foreach([10,14] as $nights){
        $nightTarget=$target;$nightTarget['nights']=$nights;$nightTarget['price']['amount']=(string)(110000+$nights);
        $nightApplied=AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($dir,$nightTarget,$request,1100);
        estore_ok(is_array($nightApplied),'cross-night cache reuse '.$nights);
        estore_ok($nightApplied['search_price']['amount']===$nightTarget['price']['amount'],'own base '.$nights);
    }

    $older=AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,estore_fact($source,'4000'),900,1200);
    $kept=AnyTourAndromedaSurchargeEvidenceStoreV1::save($dir,$older,$provenance,$write);
    estore_ok($kept['status']==='kept_newer'&&$kept['written']===false,'older evidence cannot roll back');

    $conflict=AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,estore_fact($source,'6000'),1000,1300);
    $conflictThrown=false;try{AnyTourAndromedaSurchargeEvidenceStoreV1::save($dir,$conflict,$provenance,$write);}catch(DomainException $e){$conflictThrown=$e->getMessage()==='ANDROMEDA_SURCHARGE_EVIDENCE_STORE_CONFLICT';}
    estore_ok($conflictThrown,'equal timestamp conflict fail closed');

    $newer=AnyTourAndromedaSurchargeEvidenceV1::capture($source,$request,estore_fact($source,'5500'),1100,1400);
    $replaced=AnyTourAndromedaSurchargeEvidenceStoreV1::save($dir,$newer,$provenance,$write);
    estore_ok($replaced['status']==='replaced'&&$replaced['written']===true,'newer evidence replaces');
    $applied=AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($dir,$target,$request,1200);
    estore_ok($applied['search_price_with_surcharge']['amount']==='115500','newer evidence served');
    estore_ok(AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($dir,$target,$request,1400)===null,'expired evidence rejected');

    $mismatch=$target;$mismatch['check_in']='2026-10-31';
    estore_ok(AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($dir,$mismatch,$request,1200)===null,'strict group mismatch rejected');

    // Old v1/v2 cache namespace must never be reinterpreted after key semantics change.
    $legacyPath=$dir.'/andromeda-surcharge-group-v1-'.str_repeat('c',64).'.json';
    file_put_contents($legacyPath,json_encode(['version'=>1,'provider'=>'andromeda','evidence'=>[
        'schema_version'=>1,'provider'=>'andromeda','state'=>'estimated','group_key'=>'andromeda-surcharge-v2:'.str_repeat('c',64),
        'party_surcharge'=>['amount'=>'9999','currency'=>'RUB','source'=>'andromeda_get_flights_transport'],'observed_at'=>1000,'expires_at'=>1300
    ],'provenance'=>$provenance],JSON_THROW_ON_ERROR));
    estore_ok(AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($dir,$target,$request,1200)!==null,'legacy file does not shadow v3 cache');

    $badProv=$provenance;$badProv['source_sha']='bad';$badProvThrown=false;
    try{AnyTourAndromedaSurchargeEvidenceStoreV1::save($dir,$newer,$badProv,$write);}catch(InvalidArgumentException $e){$badProvThrown=true;}
    estore_ok($badProvThrown,'bad provenance rejected');

    file_put_contents($replaced['path'],'{"version":1,"provider":"andromeda"}');
    estore_ok(AnyTourAndromedaSurchargeEvidenceStoreV1::readApplied($dir,$target,$request,1200)===null,'malformed cache fail closed');
}finally{
    foreach(glob($dir.'/*')?:[] as $path)@unlink($path);@rmdir($dir);@rmdir($root);
}

echo "ANDROMEDA_SURCHARGE_EVIDENCE_STORE_OK create=1 cross_night=2 rebase=1 monotonic=2 conflict=1 expiry=1 legacy_isolation=1 malformed=1 provenance=1\n";
