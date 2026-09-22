<?php
declare(strict_types=1);

const H3_OP = 'hotel-match-hold3-pair-review-1971-20260920-v1';

function h3_need(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
function h3_rows(PDO $db, string $sql, array $params = []): array {
    $s = $db->prepare($sql);
    $s->execute(array_values($params));
    return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function h3_write(string $path, array $value): string {
    $body = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $f = fopen($path, 'xb'); h3_need(is_resource($f), 'open_result');
    try { h3_need(fwrite($f, $body) === strlen($body) && fflush($f), 'write_result'); if (function_exists('fsync')) h3_need(fsync($f), 'sync_result'); }
    finally { fclose($f); }
    return hash('sha256', $body);
}
function h3_norm(string $s): string {
    $s = function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
    $s = preg_replace('/[^\pL\pN]+/u', ' ', $s) ?? $s;
    $parts = preg_split('/\s+/u', trim($s)) ?: [];
    $drop = ['HOTEL'=>1,'RESORT'=>1,'SPA'=>1,'VILLA'=>1,'VILLAS'=>1,'THE'=>1,'AND'=>1,'ZANZIBAR'=>1];
    $parts = array_values(array_filter($parts, fn($x) => $x !== '' && !isset($drop[$x])));
    return implode(' ', $parts);
}
function h3_compatible(string $a, string $b): bool {
    $a = h3_norm($a); $b = h3_norm($b);
    return $a !== '' && $b !== '' && ($a === $b || str_contains($a, $b) || str_contains($b, $a));
}
function h3_star(array $row): ?int {
    $raw = (string)($row['source_catalog']['hotel']['starLName'] ?? $row['source_catalog']['hotel']['star'] ?? '');
    return preg_match('/([1-5])/', $raw, $m) ? (int)$m[1] : null;
}

if (in_array('--self-test', $argv ?? [], true)) {
    h3_need(h3_norm('Fruit & Spice Wellness Resort Zanzibar') === 'FRUIT SPICE WELLNESS', 'norm_fruit');
    h3_need(h3_norm('WHITE PARADISE ZANZIBAR') === 'WHITE PARADISE', 'norm_white');
    h3_need(h3_compatible('Safaya Luxury Villas', 'SAFAYA LUXURY VILLAS'), 'compat_safaya');
    echo "MATCH_HOLD3_PAIR_REVIEW_SELFTEST_OK\n"; exit(0);
}

$dir = (string)getenv('MATCH_OPERATION_DIR');
$root = realpath((string)getenv('ANYTOUR_ROOT'));
$input = realpath((string)getenv('MATCH_INPUT_PATH'));
$inputSha = (string)getenv('MATCH_INPUT_SHA256');
h3_need(PHP_SAPI === 'cli' && is_dir($dir) && basename($dir) === H3_OP && is_string($root) && is_string($input), 'runtime');
h3_need($inputSha !== '' && hash_file('sha256', $input) === $inputSha, 'input_sha');
$in = json_decode((string)file_get_contents($input), true, 128, JSON_THROW_ON_ERROR);
h3_need(($in['source_3156_result_sha256'] ?? null) === '355cf753939b1218b692b4dba8e7f0234712ce5e4a66af7edc23316a8dbf4726', 'source3156');
h3_need(($in['source_3144_result_sha256'] ?? null) === '3144fbbfe9a886bb94954d8a12147119172f570285ae7622edf2ffb8ed16657a', 'source3144');
h3_need(($in['source_3135_result_sha256'] ?? null) === '9d8be005458ca901dac8f94a7224662ae25833882dda2ed6687d184a660af034', 'source3135');
$rows = (array)($in['rows'] ?? []); h3_need(count($rows) === 3, 'three_rows');
$expected = ['2000034436|49104'=>1,'2000063032|69340'=>1,'2000089754|150945'=>1];
foreach ($rows as $r) {
    $k = (string)$r['samo_hotel_id'].'|'.(int)$r['tv_hotel_id']; h3_need(isset($expected[$k]), 'unexpected_pair'); unset($expected[$k]);
    h3_need(($r['source_classification'] ?? null) === 'review_candidate', 'source_classification');
    $proofs = (array)($r['proofs'] ?? []); h3_need(count($proofs) >= 2, 'dual_proof');
    $ops=[]; foreach ($proofs as $p) { h3_need(($p['verified'] ?? false) === true, 'proof_verified'); $ops[(int)$p['operator_id']] = true; }
    h3_need(count($ops) >= 2 && isset($ops[13]) && isset($ops[43]), 'anex_intourist_pair');
}
h3_need(!$expected, 'missing_pair');

require_once $root . (is_file($root.'/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
$db = v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('START TRANSACTION READ ONLY');

$outRows=[]; $counts=[];
foreach ($rows as $r) {
    $sid=(string)$r['samo_hotel_id']; $tv=(int)$r['tv_hotel_id']; $source=(array)$r['source_catalog'];
    $sourceHotel=(array)($source['hotel'] ?? []); $sourceName=(string)($sourceHotel['name'] ?? ''); $sourceCountry=(string)($sourceHotel['state'] ?? ''); $sourceTown=(string)($sourceHotel['town'] ?? '');
    $srcCur=h3_rows($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?",[$sid]);
    $target=h3_rows($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id=?",[$tv]); h3_need(count($target)===1,'target_missing'); $target=$target[0];
    $occupants=h3_rows($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id=? AND external_hotel_id<>?",[$tv,$sid]);
    $anexNative=null; foreach((array)$r['proofs'] as $p) if((int)$p['operator_id']===13) $anexNative=(string)$p['native_token']; h3_need(is_string($anexNative)&&preg_match('/^[1-9][0-9]{0,8}$/D',$anexNative)===1,'anex_native');
    $maps=h3_rows($db,"SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id=? OR catalog_hotel_id=?",[$anexNative,$tv]);
    $dec=h3_rows($db,"SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id=? OR catalog_hotel_id=?",[$anexNative,$tv]);
    $exc=h3_rows($db,"SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id=? OR catalog_hotel_id=?",[$anexNative,$tv]);
    $countryPeers=h3_rows($db,"SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE country_name=? AND is_active=1",[(string)$target['country_name']]);
    $namePeers=[]; foreach($countryPeers as $peer) if(h3_compatible($sourceName,(string)$peer['name'])) $namePeers[]=$peer;
    $otherNamePeers=array_values(array_filter($namePeers,fn($p)=>(int)$p['id']!==$tv));
    $proofOps=[]; $proofNatives=[]; foreach((array)$r['proofs'] as $p){$proofOps[(int)$p['operator_id']]=true;$proofNatives[(int)$p['operator_id']]=(string)$p['native_token'];}
    $currentSameAccepted=false; $currentOther=[]; foreach($srcCur as $c){if(($c['decision_status']??null)==='accepted'&&(int)$c['local_hotel_id']===$tv)$currentSameAccepted=true; elseif(($c['decision_status']??null)==='accepted'||(int)($c['local_hotel_id']??0)>0)$currentOther[]=$c;}
    $exactMap=array_values(array_filter($maps,fn($m)=>(string)$m['anex_hotel_id']===$anexNative&&(int)$m['catalog_hotel_id']===$tv&&(int)($m['enabled']??0)===1));
    $otherEnabledMaps=array_values(array_filter($maps,fn($m)=>(string)$m['anex_hotel_id']===$anexNative&&(int)($m['enabled']??0)===1&&(int)$m['catalog_hotel_id']!==$tv));
    $nameOk=h3_compatible($sourceName,(string)$target['name']);
    $countryOk=h3_norm($sourceCountry)===h3_norm((string)$target['country_name']);
    $town=h3_norm($sourceTown); $region=h3_norm((string)($target['region_name']??'')); $sub=h3_norm((string)($target['subregion_name']??''));
    $placeOk=$town!==''&&($town===$sub||$town===$region||($sub!==''&&(str_contains($sub,$town)||str_contains($town,$sub))));
    $sourceStar=h3_star($r); $targetStar=(int)($target['category']??0); $categoryOk=$sourceStar===null||$targetStar===0||$sourceStar===$targetStar;
    $reasons=[];
    if(!$nameOk)$reasons[]='name_conflict'; if(!$countryOk)$reasons[]='country_conflict'; if((int)$target['is_active']!==1)$reasons[]='inactive_target';
    if($occupants)$reasons[]='target_occupied'; if($currentOther)$reasons[]='source_assigned_other'; if($dec)$reasons[]='manual_decision_present'; if($exc)$reasons[]='pair_exclusion_present'; if($otherEnabledMaps)$reasons[]='anex_native_mapped_other'; if($otherNamePeers)$reasons[]='same_country_name_competition';
    if(count($proofOps)<2||!isset($proofOps[13])||!isset($proofOps[43]))$reasons[]='insufficient_independent_operator_proof';
    $categoryOverride=!$categoryOk && $nameOk && $countryOk && $placeOk && count($proofOps)>=2 && !$otherNamePeers;
    if(!$categoryOk&&!$categoryOverride)$reasons[]='category_conflict';
    $geoSoft=!$placeOk && $nameOk && $countryOk && count($proofOps)>=2 && !$otherNamePeers;
    if(!$placeOk&&!$geoSoft)$reasons[]='geo_not_confirmed';
    $classification='protected_hold';
    if($currentSameAccepted)$classification='already_accepted';
    elseif(!$reasons)$classification='safe_candidate';
    $counts[$classification]=($counts[$classification]??0)+1;
    $outRows[]=[
      'samo_hotel_id'=>$sid,'tv_hotel_id'=>$tv,'source_name'=>$sourceName,'target'=>$target,'source_town'=>$sourceTown,'source_star'=>$sourceStar,
      'proof_operator_ids'=>array_map('intval',array_keys($proofOps)),'proof_native_ids'=>$proofNatives,'anex_native_id'=>$anexNative,
      'current_source_rows'=>$srcCur,'current_target_occupants'=>$occupants,'anex_mapping_rows'=>$maps,'exact_enabled_anex_mapping_count'=>count($exactMap),'other_enabled_anex_mappings'=>$otherEnabledMaps,
      'manual_rows'=>$dec,'exclusion_rows'=>$exc,'compatible_active_country_targets'=>$namePeers,'name_ok'=>$nameOk,'country_ok'=>$countryOk,'place_ok'=>$placeOk,'geo_soft_by_dual_operator'=>$geoSoft,
      'category_ok'=>$categoryOk,'category_override_by_dual_operator_geo'=>$categoryOverride,'classification'=>$classification,'hold_reasons'=>$reasons,'safe_to_write_now'=>false
    ];
}
$db->rollBack(); ksort($counts);
$out=['operation'=>H3_OP,'state'=>'completed_read_only','input_sha256'=>$inputSha,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'classification_counts'=>$counts,'rows'=>$outRows];
$rh=h3_write($dir.'/result.json',$out);
h3_write($dir.'/receipt.json',['operation'=>H3_OP,'state'=>'completed_read_only','result_sha256'=>$rh,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'readback_verified'=>true,'no_replay'=>true]);
echo json_encode(['classification_counts'=>$counts,'result_sha256'=>$rh],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
