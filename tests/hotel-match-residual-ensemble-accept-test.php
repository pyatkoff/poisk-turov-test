<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_residual_ensemble_accept.php';

function hmrea_assert(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }

hmrea_assert(hmrea_latin('Бодрум')==='bodrum','Cyrillic geography transliteration changed');
hmrea_assert(hmrea_latin('Сахл Хашиш')==='sakhl khashish','Sahl Hasheesh transliteration guard changed');

$geoOnly=['source_tokens'=>['beach','bodrum'],'target_tokens'=>['beach','bodrum']];
$geoRow=['source_region'=>'Бодрум','source_town'=>'Бодрум','target_region'=>'Бодрум','target_subregion'=>''];
hmrea_assert(!hmrea_identity_anchor($geoOnly,$geoRow),'geography + weak BEACH must not be a hotel identity anchor');

$sahlOnly=['source_tokens'=>['sahl','hasheesh'],'target_tokens'=>['sahl','hasheesh']];
$sahlRow=['source_region'=>'Сахл Хашиш','source_town'=>'Сахл Хашиш','target_region'=>'Сахл Хашиш','target_subregion'=>''];
// Russian supplier geography transliterates to sakhl/khashish rather than the common English sahl/hasheesh,
// so this synthetic case is not sufficient alone. A true hotel token must still be required by the writer's
// source/target identity guard when available.
hmrea_assert(!hmrea_identity_anchor(['source_tokens'=>['beach'],'target_tokens'=>['beach']],$sahlRow),'weak structural token must not anchor identity');

$costa=['source_tokens'=>['costa','bodrum'],'target_tokens'=>['costa','bodrum']];
hmrea_assert(hmrea_identity_anchor($costa,$geoRow),'distinctive COSTA token should survive geography removal');

$signature=['source_tokens'=>['signature','blue'],'target_tokens'=>['signature','blue']];
hmrea_assert(hmrea_identity_anchor($signature,['source_region'=>'Кушадасы','source_town'=>'Кушадасы','target_region'=>'Кушадасы','target_subregion'=>'']),'distinctive SIGNATURE token should survive weak BLUE');

$mass=['status'=>'completed','operation_id'=>HMREA_SOURCE_OPERATION,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'counts'=>['auto_accept'=>40,'auto_anex'=>37,'auto_andromeda'=>3],'buckets'=>['auto_accept'=>[]]];
for($i=1;$i<=37;$i++)$mass['buckets']['auto_accept'][]=['provider'=>'anex','external_id'=>(string)$i,'target_local_hotel_id'=>1000+$i,'evidence_sources'=>['direct_details_coordinate']];
for($i=1;$i<=3;$i++)$mass['buckets']['auto_accept'][]=['provider'=>'andromeda','external_id'=>'a'.$i,'target_local_hotel_id'=>2000+$i,'evidence_sources'=>['current_strict']];
$rows=hmrea_source_rows($mass);hmrea_assert(count($rows)===40,'mass allowlist count changed');hmrea_assert(isset($rows['anex:1'])&&isset($rows['andromeda:a1']),'provider allowlist keys changed');

$bad=$mass;$bad['database_writes']=1;$thrown=false;try{hmrea_source_rows($bad);}catch(RuntimeException $e){$thrown=$e->getMessage()==='mass_source_invalid';}hmrea_assert($thrown,'writer must reject a source artifact that already wrote DB');

$sold=['status'=>'completed','operation_id'=>HMRE_SOLD_OPERATION,'database_writes'=>0,'prepared'=>[
 ['anex_hotel_id'=>1,'target_local_hotel_id'=>11],['anex_hotel_id'=>2,'target_local_hotel_id'=>12],['anex_hotel_id'=>3,'target_local_hotel_id'=>13],
]];hmrea_assert(count(hmrea_sold_rows($sold))===3,'sold-date immutable source validation changed');

echo "hotel-match-residual-ensemble-accept-test: OK\n";
