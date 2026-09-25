<?php
/** HC-1 opt-in scope. All records are fictional; only the disposable CI DB is allowed. */
declare(strict_types=1);
require_once __DIR__ . '/../v2/data/anytour-profile-enrichment-v1.php';

$checked = 0;
function hc1_need(bool $ok, string $label): void {
    global $checked;
    if (!$ok) throw new RuntimeException('HC1_CHECK_FAILED:' . $label);
    ++$checked;
}
function hc1_reject(callable $call, string $needle): void {
    try { $call(); } catch (Throwable $e) {
        hc1_need(str_contains($e->getMessage(), $needle), 'expected ' . $needle . ', got ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('HC1_EXPECTED_REJECTION:' . $needle);
}
$scopeMethod = new ReflectionMethod(AnyTourProfileEnrichmentV1::class, 'contentScope');
$normalize = static fn(?array $value, int $limit): ?array => $scopeMethod->invoke(null, $value, $limit);
$target = static fn(int $local, int $own, array $fields): array =>
    ['localHotelId'=>$local,'anytourHotelId'=>$own,'fields'=>$fields];
$a=$target(101,1,['images','description','primaryImage']);
$b=$target(202,2,['images']);
hc1_need($normalize(null,250)===null,'null is the only default queue selector');
$normalized=$normalize([$b,$a],2);
hc1_need(array_keys($normalized)===[1,2],'stable ID order');
hc1_need($normalized[1]['fields']===['description','images','primaryImage'],'stable field order');
foreach ([[],[$a,$a],[$a,$target(101,3,['images'])],[$a+['extra'=>1]],
    [$target(101,1,[])],[$target(101,1,['images','images'])],[$target(101,1,['rating'])],
    [$target(101,1,['hotelInformation.meals'])],[$target(101,1,[null])],
    array_fill(0,21,$a),[9=>$a]] as $bad) {
    hc1_reject(fn()=> $normalize($bad,count($bad)), 'ANYTOUR_PROFILE_ENRICH_CONTENT_SCOPE');
}
hc1_reject(fn()=> $normalize([$a],2), 'ANYTOUR_PROFILE_ENRICH_CONTENT_SCOPE');
hc1_reject(fn()=> $normalize([$target(0,1,['images'])],1), 'ANYTOUR_PROFILE_ENRICH_ID');
hc1_reject(fn()=> $normalize([['localHotelId'=>'1 OR 1','anytourHotelId'=>1,'fields'=>['images']]],1), 'ANYTOUR_PROFILE_ENRICH_ID');
if (getenv('HC1_SCOPE_PURE_ONLY')==='1') {
    echo 'HC1_SCOPE_PURE_OK checks=' . $checked . "\n";
    exit(0);
}
// Reuse the existing guarded fixture/schema/factories and execute its legacy tests unchanged.
require __DIR__ . '/anytour-profile-enrichment-v1-test.php';
$through='2026-09-17T21:00:00Z';
$h5=$insertHotel($db,$baseProfile('HC1 scoped hotel')); $insertAlias($db,505,$h5);
$insertCatalog($db,505,'HC1 scoped hotel','Exact saved description','https://img.example/505.jpg',['Pool'],['Spa'],'DO NOT COPY MEAL','DO NOT COPY ROOM');
$p6=$baseProfile('HC1 own content');
$p6['description']='Preserve manual text'; $p6['primaryImage']='https://own.example/606.jpg';
$p6['images']=['https://own.example/gallery.jpg'];
$h6=$insertHotel($db,$p6); $insertAlias($db,606,$h6);
$insertCatalog($db,606,'HC1 own content','Do not overwrite','https://img.example/606.jpg',[],[],'OTHER MEAL','OTHER ROOM');
$scope=[$target(505,$h5,['primaryImage','images','description']),$target(606,$h6,['primaryImage','images','description'])];
$untouched=$readProfile($db,$h3);
$plan=$engine->plan(2,$through,$scope);
hc1_need($plan['writes']===0 && count($plan['selected'])===1,'only exact fillable target, not demand leader');
hc1_need($plan['selected'][0]['anytourHotelId']===$h5,'selected exact own ID');
hc1_need(array_keys($plan['selected'][0]['patch'])===['description','primaryImage','images'],'only three content fields');
hc1_need(hash('sha256',$plan['selected'][0]['beforeProfileJson'])===$plan['selected'][0]['expectedProfileSha256'],'exact private before-image');
hc1_need(strlen($plan['selected'][0]['expectedAliasSha256'])===64,'alias evidence is bound');
$canonicalScope=$plan['contentScope'];
hc1_need($engine->plan(2,$through,array_reverse($scope))['planSha256']===$plan['planSha256'],'order-independent same plan');
// A changed field scope cannot reuse a broader plan hash.
$narrow=[$target(505,$h5,['images']),$target(606,$h6,['images'])];
hc1_reject(fn()=> $engine->apply('hc1-wrong-scope',2,$through,$plan['planSha256'],$narrow),'ANYTOUR_PROFILE_ENRICH_PLAN_DRIFT');
hc1_need($readProfile($db,$h5)['revision']===1,'scope drift writes nothing');
hc1_reject(fn()=> $engine->plan(1,$through,[$target(999,$h5,['images'])]),'ANYTOUR_PROFILE_ENRICH_SCOPE_IDENTITY');
hc1_reject(fn()=> $engine->plan(1,$through,[$target(999,999999,['images'])]),'ANYTOUR_PROFILE_ENRICH_SCOPE_IDENTITY');
// Source changes between plan and apply must also reject without a partial write.
$db->exec("UPDATE catalog_hotel_details SET description='Changed evidence' WHERE hotel_id=505");
hc1_reject(fn()=> $engine->apply('hc1-source-drift',2,$through,$plan['planSha256'],$scope),'ANYTOUR_PROFILE_ENRICH_PLAN_DRIFT');
$db->exec("UPDATE catalog_hotel_details SET description='Exact saved description' WHERE hotel_id=505");
// Even a valid alias with the same two IDs cannot silently change its provenance.
$q=$db->prepare("SELECT source_json,source_sha256 FROM anytour_hotel_sources WHERE namespace='anytour_local_id' AND external_key=?");
$q->execute(['505']); $aliasBefore=$q->fetch();
$alias=json_decode($aliasBefore['source_json'],true,512,JSON_THROW_ON_ERROR);
$alias['derived_from_source_sha256']=str_repeat('b',64);
$j=AnyTourProfileEnrichmentV1::json($alias);
$u=$db->prepare("UPDATE anytour_hotel_sources SET source_json=?,source_sha256=? WHERE namespace='anytour_local_id' AND external_key=?");
$u->execute([$j,hash('sha256',$j),'505']);
hc1_reject(fn()=> $engine->apply('hc1-alias-drift',2,$through,$plan['planSha256'],$scope),'ANYTOUR_PROFILE_ENRICH_PLAN_DRIFT');
$u->execute([$aliasBefore['source_json'],$aliasBefore['source_sha256'],'505']);
$result=$engine->apply('hc1-content-only',2,$through,$plan['planSha256'],$scope);
hc1_need($result['status']==='committed_verified' && $result['profilesUpdated']===1 && $result['fieldsFilled']===3,'scoped commit verified');
hc1_need($result['fieldCounts']===['description'=>1,'images'=>1,'primaryImage'=>1],'three fields only');
$after=$readProfile($db,$h5); $expected=$baseProfile('HC1 scoped hotel');
foreach ($plan['selected'][0]['patch'] as $key=>$value) $expected[$key]=$value;
hc1_need(AnyTourProfileEnrichmentV1::json($after['profile'])===AnyTourProfileEnrichmentV1::json($expected),'all other profile fields byte-semantically preserved');
hc1_need($readProfile($db,$h6)['revision']===1,'manual profile not written');
hc1_need(AnyTourProfileEnrichmentV1::json($readProfile($db,$h6)['profile'])===AnyTourProfileEnrichmentV1::json($p6),'manual content exact');
hc1_need($readProfile($db,$h3)===$untouched,'unscoped demand hotel untouched');
$repeat=$engine->plan(2,$through,$scope);
hc1_need($repeat['selected']===[],'repeat does not advance to another hotel');
$noop=$engine->apply('hc1-noop',2,$through,$repeat['planSha256'],$scope);
hc1_need($noop['profileWrites']===0 && $noop['provenanceWrites']===0,'verified no-op');
hc1_reject(fn()=> $engine->apply('hc1-stale-plan',2,$through,$plan['planSha256'],$scope),'ANYTOUR_PROFILE_ENRICH_PLAN_DRIFT');
hc1_need(!array_key_exists('contentScope',$engine->plan(1,$through)),'legacy default output remains unchanged');
// Per-target masks: a stored description must not be filled when this target allows only images.
$h7=$insertHotel($db,$baseProfile('HC1 partial scope')); $insertAlias($db,707,$h7);
$insertCatalog($db,707,'HC1 partial scope','Must remain unselected','https://img.example/707.jpg',[],[],'M','R');
$only=[$target(707,$h7,['images'])]; $p=$engine->plan(1,$through,$only);
$r=$engine->apply('hc1-gallery-only',1,$through,$p['planSha256'],$only);
$after=$readProfile($db,$h7);
hc1_need($r['fieldCounts']===['images'=>1] && $after['profile']['description']===null && $after['profile']['primaryImage']===null,'per-target mask enforced at commit');
hc1_need($r['mappingWrites']===0 && $r['supplierCalls']===0 && $r['legacyWrites']===0,'protected boundaries unchanged');
echo 'HC1_SCOPE_MYSQL_OK checks=' . $checked . "\n";
