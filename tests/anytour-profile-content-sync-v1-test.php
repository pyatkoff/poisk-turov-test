<?php
/** The existing disposable MySQL fixture plus opt-in retained-content synchronization. */
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/anytour-profile-enrichment-v1.php';
$checks = 0;
function sync_need(bool $ok, string $label): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException('SYNC_TEST:'.$label); }
function sync_private(string $name, mixed ...$args): mixed { return (new ReflectionMethod(AnyTourProfileEnrichmentV1::class, $name))->invoke(null, ...$args); }
function sync_throws(callable $fn, string $contains): void {
    try { $fn(); } catch (Throwable $e) { sync_need(str_contains($e->getMessage(), $contains), 'expected-'.$contains); return; }
    throw new RuntimeException('SYNC_TEST:no_throw:'.$contains);
}
$long = array_map(static fn($n)=>'https://img.example/'.$n.'.jpg', range(1,240));
sync_need(count(v2_hotel_detail_images(['images'=>$long])) === 100, 'legacy-image-default');
sync_need(count(v2_hotel_detail_images(['images'=>array_merge($long,[$long[0],'javascript:alert(1)'])],null)) === 240,'complete-validated-gallery');
sync_throws(fn()=>v2_hotel_detail_images([],0),'Invalid image limit');
sync_need(sync_private('syncPresent','<br> &nbsp; ') === false,'empty-html');
sync_need(sync_private('syncPresent',0) === true && sync_private('syncPresent',false) === true,'real-zero-false');
sync_need(sync_private('syncMerge',['child'=>'keep','free'=>'old'],['child'=>'','free'=>'new']) === ['child'=>'keep','free'=>'new'],'no-empty-leaf-delete');
sync_need(sync_private('syncMerge',[1,2],[3]) === [3],'atomic-list-replace');
sync_need(sync_private('syncMerge',null,['beach'=>'<p>Beach</p>']) === ['beach'=>'<p>Beach</p>'],'object-accepted');
sync_need(sync_private('syncEqual',4,4.0),'numeric-equivalence');
sync_throws(fn()=>sync_private('contentScope',null,1,true),'SYNC_REQUIRES_SCOPE');
sync_throws(fn()=>sync_private('contentScope',[],1,true),'CONTENT_SCOPE');
$one=[['anytourHotelId'=>1,'localHotelId'=>101,'fields'=>['hotelInformation.services']]];
sync_throws(fn()=>sync_private('contentScope',$one,1,false),'CONTENT_SCOPE_FIELD');
sync_need(count(sync_private('contentScope',$one,1,true))===1,'explicit-new-content-scope');
$bad=$one;$bad[0]['fields']=['price'];sync_throws(fn()=>sync_private('contentScope',$bad,1,true),'CONTENT_SCOPE_FIELD');
sync_throws(fn()=>sync_private('contentScope',array_merge($one,$one),2,true),'DUPLICATE');
sync_throws(fn()=>sync_private('syncTime','2026-02-30 12:00:00'),'SOURCE_TIME');
sync_need(sync_private('syncTime','2026-09-25T10:00:00Z')==='2026-09-25 10:00:00','utc');
if (in_array('--self-test',$argv,true)) { echo "SYNC_PURE_OK checks=$checks\n"; exit(0); }

// This existing test owns/validates the exact dedicated fixture DSN and runs unchanged.
require __DIR__ . '/anytour-profile-enrichment-v1-test.php';
$db->exec('ALTER TABLE catalog_hotel_details ADD raw_json LONGTEXT NULL, ADD source_hash CHAR(64) NULL');
$through='2026-09-25T20:00:00Z';
$rawFor=static function(int $local): array {
    return ['id'=>$local,'name'=>'Sync '.$local,'country'=>['id'=>4,'name'=>'Египет'],
        'region'=>['id'=>40,'name'=>'Шарм-эль-Шейх'],'subRegion'=>['id'=>401,'name'=>'Наама-Бей'],
        'category'=>5,'rating'=>4.7,'type'=>1,
        'images'=>['https://img.example/'.$local.'.jpg'],
        'common'=>['description'=>'Old description','address'=>'Адрес','place'=>'Наама-Бей',
            'build'=>'2005','repair'=>'2024','square'=>'10000 м²','latitude'=>27.9,'longitude'=>34.3],
        'infrastructure'=>['beach'=>'Old beach','territory'=>'Pool'],
        'services'=>['child'=>'Old kids club','free'=>'Wi-Fi','servicesPay'=>'Keep paid info'],
        'meals'=>['description'=>'Old hotel meal description'],'roomTypes'=>'Old hotel room descriptions'];
};
$saveRaw=static function(array $raw,string $stamp) use($db): void {
    $n=v2_hotel_detail_normalized($raw);$j=json_encode($raw,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $q=$db->prepare("UPDATE catalog_hotel_details SET status='success',description=?,address=?,place=?,build_info=?,repair_info=?,square_info=?,images_json=?,infrastructure_json=?,meals_json=?,services_json=?,room_types=?,raw_json=?,source_hash=?,fetched_at=? WHERE hotel_id=?");
    $q->execute([$n['description'],$n['address'],$n['place'],$n['build'],$n['repair'],$n['square'],$n['images_json'],$n['infrastructure_json'],$n['meals_json'],$n['services_json'],$n['room_types'],$j,hash('sha256',$j),$stamp,$n['hotel_id']]);
};
$make=static function(int $local,?array $custom=null) use($db,$insertCatalog,$insertHotel,$insertAlias,$rawFor,$saveRaw): array {
    $raw=$custom??$rawFor($local);
    $insertCatalog($db,$local,$raw['name'],'Old description',$raw['images'][0],[],[],'','');
    $saveRaw($raw,'2026-09-20 10:00:00');
    $saved=hotel_presentation_read_many($db,[$local])['items'][0];
    $own=$insertHotel($db,AnyTourCanonicalCatalog::initialProfile($saved));$insertAlias($db,$local,$own);
    $j=AnyTourCanonicalCatalog::json($saved);
    $db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('legacy_catalog',?,?,'saved_catalog',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([(string)$local,$own,$j,hash('sha256',$j)]);
    return [$own,$raw];
};
$scopeFor=static fn(int $own,int $local,array $fields=AnyTourProfileEnrichmentV1::SYNC_FIELDS): array => [['anytourHotelId'=>$own,'localHotelId'=>$local,'fields'=>$fields]];
$engine=new AnyTourProfileEnrichmentV1($db);
[$own,$raw]=$make(9001);$scope=$scopeFor($own,9001);
$before=$readProfile($db,$own);
$raw['common']['description']='New source description';
$raw['infrastructure']['beach']='New beach';$raw['services']['child']='New kids club';
$raw['services']['servicesPay']=''; // Incomplete source must not erase a good existing leaf.
$raw['meals']['description']='New hotel meal description';$raw['roomTypes']='New room descriptions';
$raw['images']=$long;$raw['images'][]=$long[0];$raw['images'][]='javascript:alert(1)';
$saveRaw($raw,'2026-09-25 10:00:00');
$plan=$engine->plan(1,$through,$scope,true);$item=$plan['selected'][0];
sync_need(count($plan['selected'])===1 && $plan['writes']===0,'read-only-exact-plan');
sync_need($item['patch']['description']==='New source description','stale-nonempty-description');
sync_need(count($item['patch']['images'])===240,'all-240-images');
sync_need($item['patch']['hotelInformation.services']['child']==='New kids club','object-children');
sync_need($item['patch']['hotelInformation.services']['servicesPay']==='Keep paid info','empty-source-leaf-preserved');
sync_need($item['patch']['hotelInformation.meals']['description']==='New hotel meal description','hotel-not-offer-meals');
sync_need($item['patch']['hotelInformation.roomTypes']==='New room descriptions','hotel-not-offer-rooms');
sync_need($item['beforeProfileJson']!=='' && $item['importProof']['seedSha256']!=='' ,'private-before-and-origin');
$changedScope=$scope;$changedScope[0]['fields']=['description'];
sync_throws(fn()=>$engine->apply('sync-scope-drift',1,$through,$plan['planSha256'],$changedScope,true),'PLAN_DRIFT');
$oldStamp=$raw;$raw['common']['description']='Changed after plan';$saveRaw($raw,'2026-09-25 10:00:01');
sync_throws(fn()=>$engine->apply('sync-source-drift',1,$through,$plan['planSha256'],$scope,true),'PLAN_DRIFT');
sync_need($readProfile($db,$own)===$before,'drift-wrote-nothing');
$raw=$oldStamp;$saveRaw($raw,'2026-09-25 10:00:00');
$plan=$engine->plan(1,$through,$scope,true);
$result=$engine->apply('sync-correct',1,$through,$plan['planSha256'],$scope,true);
sync_need($result['status']==='committed_verified' && $result['profilesUpdated']===1,'committed');
$now=$readProfile($db,$own);sync_need($now['revision']===2 && count($now['profile']['images'])===240,'exact-result');
sync_need($now['profile']['traits']===[] && $now['profile']['category']===5,'other-fields-preserved');
sync_need((int)$db->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='profile_sync:retained_tv_v1'")->fetchColumn()===1,'honest-sync-provenance');
$repeat=$engine->plan(1,$through,$scope,true);sync_need($repeat['selected']===[],'repeat-noop-provenance-chain');
$noOp=$engine->apply('sync-noop',1,$through,$repeat['planSha256'],$scope,true);sync_need($noOp['profilesUpdated']===0,'noop-no-writes');
// One actual subsequent source update proves sync is not a one-off empty-field repair.
$raw['common']['description']='Third source version';$saveRaw($raw,'2026-09-25 11:00:00');
$p=$engine->plan(1,$through,$scope,true);sync_need($p['selected'][0]['patch']['description']==='Third source version','later-source-update');
$engine->apply('sync-third',1,$through,$p['planSha256'],$scope,true);
sync_need($readProfile($db,$own)['revision']===3,'second-sync-revision');

[$manual,$manualRaw]=$make(9002);$manualBefore=$readProfile($db,$manual);$p=$manualBefore['profile'];$p['description']='Real manual change';$j=AnyTourProfileEnrichmentV1::json($p);
$db->prepare('UPDATE anytour_hotels SET profile_json=?,profile_sha256=?,revision=2 WHERE id=?')->execute([$j,hash('sha256',$j),$manual]);
$manualRaw['common']['description']='Conflicting TV';$saveRaw($manualRaw,'2026-09-25 10:00:00');
$m=$engine->plan(1,$through,$scopeFor($manual,9002),true);
sync_need($m['selected']===[] && str_contains($m['held'][$manual]['profile'],'UNPROVEN_OR_MANUAL'),'manual-protected');
sync_need($readProfile($db,$manual)['profile']['description']==='Real manual change','manual-not-written');

[$older,$olderRaw]=$make(9003);$olderRaw['common']['description']='Older source';$saveRaw($olderRaw,'2026-09-19 10:00:00');
$o=$engine->plan(1,$through,$scopeFor($older,9003),true);sync_need($o['selected']===[] && str_contains($o['held'][$older]['profile'],'OLDER_OR_UNKNOWN'),'older-source-held');

[$meta,$metaRaw]=$make(9004);$metaRaw['category']=3;$metaRaw['common']['description']='Metadata canary';$saveRaw($metaRaw,'2026-09-25 10:00:00');
$p=$engine->plan(1,$through,$scopeFor($meta,9004),true);
sync_need(!isset($p['selected'][0]['patch']['category']) && $p['held'][$meta]['category']==='raw_catalog_disagreement','metadata-conflict-held');
$db->exec('UPDATE catalog_hotels SET category=3 WHERE id=9004');
$p=$engine->plan(1,$through,$scopeFor($meta,9004),true);sync_need($p['selected'][0]['patch']['category']===3,'agreed-metadata-updates');
$metaRaw['common']['latitude']=1.0;$saveRaw($metaRaw,'2026-09-25 11:00:00');
$p=$engine->plan(1,$through,$scopeFor($meta,9004),true);sync_need(!isset($p['selected'][0]['patch']['coordinates']),'coordinate-conflict-held');

// Legacy fill-missing receipt may be followed only when its chain reaches the original seed.
$legacyRaw=$rawFor(9005);$legacyRaw['common']['description']=null;[$legacy,$legacyRaw]=$make(9005,$legacyRaw);
$legacyRaw['common']['description']='Filled using old policy';$saveRaw($legacyRaw,'2026-09-24 10:00:00');
$ls=$scopeFor($legacy,9005,['description']);$lp=$engine->plan(1,$through,$ls);
$engine->apply('legacy-before-sync',1,$through,$lp['planSha256'],$ls);
$legacyRaw['common']['description']='Newer synchronized description';$saveRaw($legacyRaw,'2026-09-25 10:00:00');
$lp=$engine->plan(1,$through,$ls,true);sync_need($lp['selected'][0]['patch']['description']==='Newer synchronized description','legacy-chain-proven');
// Unknown raw ID is never used merely because it came from a detail row of the requested ID.
$db->exec("UPDATE catalog_hotel_details SET source_hash=REPEAT('0',64) WHERE hotel_id=9005");
$lp=$engine->plan(1,$through,$ls,true);sync_need($lp['selected']===[] && $lp['held'][$legacy]['profile']==='SYNC_SOURCE_INTEGRITY','invalid-source-held');

echo "ANYTOUR_CONTENT_SYNC_OK checks=$checks legacy_default_preserved=1 object_content=1 imported_updates=1 manual_protected=1 full_gallery=240 supplier_calls=0\n";
