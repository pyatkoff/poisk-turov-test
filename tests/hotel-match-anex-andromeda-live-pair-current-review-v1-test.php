<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY',true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anex_andromeda_live_pair_current_review_v1.php';

function t(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }

t(aalp_norm('APERION BEACH HOTEL',true)==='aperion beach','generic tokens must drop but BEACH must remain');
t(aalp_norm('NORTH RESORT SPA',true)==='north','NORTH is significant');
t(aalp_norm('SEA GARDEN HOTEL',true)==='sea garden','GARDEN is significant');
t(aalp_alias_ok(['aperion beach'],['APERION BEACH HOTEL'],['APERION BEACH RESORT']),'manifest alias must corroborate source and target');
t(!aalp_alias_ok(['aperion beach'],['APERION ANNEX'],['APERION BEACH HOTEL']),'alias cannot be target-only');

$accepted=['state'=>'accepted','local_id'=>6319];$unresolved=['state'=>'unresolved'];$protected=['state'=>'protected'];
$r=aalp_decide($unresolved,$accepted,true,true,false,false);t($r['bucket']==='safe'&&$r['provider']==='anex'&&$r['target_local_hotel_id']===6319,'ANEX unresolved bridge should be safe with alias');
$r=aalp_decide($accepted,$unresolved,true,false,true,false);t($r['bucket']==='safe'&&$r['provider']==='andromeda','Andromeda unresolved bridge should be safe with direct geo');
t(aalp_decide($unresolved,$accepted,true,true,true,true)['bucket']==='hard_conflict','>5km coordinate conflict must block');
t(aalp_decide($unresolved,$accepted,false,true,true,false)['bucket']==='hard_conflict','country mismatch must block');
t(aalp_decide($protected,$unresolved,true,true,true,false)['bucket']==='protected','manual/protected state must block');
t(aalp_decide(['state'=>'accepted','local_id'=>1],['state'=>'accepted','local_id'=>2],true,true,true,false)['bucket']==='hard_conflict','different current provider mappings must block');
t(aalp_decide(['state'=>'accepted','local_id'=>1],['state'=>'accepted','local_id'=>1],true,true,true,false)['bucket']==='already_resolved','same current bridge must not be re-written');
t(aalp_decide($unresolved,$accepted,true,false,false,false)['bucket']==='deferred','bridge without current alias/geo corroboration must defer');
t(aalp_decide($unresolved,$unresolved,true,true,true,false)['bucket']==='deferred','both unresolved cannot auto-accept');

$manifest=json_decode((string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_anex_andromeda_live_pair_current_review_v1.json'),true,64,JSON_THROW_ON_ERROR);
t(($manifest['source_operation']??'')==='hotel-match-anex-andromeda-core8-pair-scale-1971-20260911-v1','manifest source operation changed');
t((int)($manifest['country_id']??0)===1,'manifest must remain Egypt-only');
t(count($manifest['rows']??[])===75,'immutable manifest must contain exactly 75 live pairs');
foreach($manifest['rows'] as $row){t((int)($row['a']??0)>0&&ctype_digit((string)($row['d']??''))&&(int)($row['l']??0)>0&&!empty($row['k']),'manifest row schema');}

$source=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_anex_andromeda_live_pair_current_review_v1.php');
t(!preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE)\s+/i',$source),'review source must stay read-only');
t(str_contains($source,"coordinate_conflict_gt_5km"),'coordinate block missing');
t(str_contains($source,"anex_review_pair_exclusions"),'ANEX pair exclusion protection missing');
t(str_contains($source,"decision_status"),'decision protection missing');
t(str_contains($source,"historical_operations_replayed'=>false"),'no-replay receipt flag missing');
echo "hotel-match-anex-andromeda-live-pair-current-review-v1-test: OK\n";
