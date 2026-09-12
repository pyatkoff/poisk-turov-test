<?php
declare(strict_types=1);
$root=dirname(__DIR__);$src=(string)file_get_contents($root.'/scripts/diagnostics/hotel_match_baseline_gap_prewrite.php');$m=json_decode((string)file_get_contents($root.'/reports/hotel-match-baseline-gap-prewrite-1971.json'),true,512,JSON_THROW_ON_ERROR);
if(($m['schema']??'')!=='hotel-match-baseline-gap-prewrite/1'||count($m['pairs']??[])!==168||($m['not_write_authority']??false)!==true)exit(10);
$ids=[];$bridge=0;$specific=0;foreach($m['pairs'] as$r){if(isset($ids[(int)$r['e']])||(int)$r['e']<1||(int)$r['l']<1||(float)$r['m']<8||!($r['k']??[])||!($r['g']??[]))exit(11);$ids[(int)$r['e']]=true;if($r['b']??[])$bridge++;if($r['s']??false)$specific++;}
if($bridge!==93||$specific!==73)exit(12);
foreach(['START TRANSACTION READ ONLY','broad_geo_without_current_bridge','countrywide_key_not_unique','primary_qualifier_drift','manual_protected','mapping_target_drift','andromeda_hotel_identities'] as$needle)if(!str_contains($src,$needle))exit(13);
$bad=['INSERT ','UPDATE ','DELETE ',' COMMIT','curl_exec','file_get_contents(\'http','file_get_contents("http'];foreach($bad as$needle)if(str_contains($src,$needle))exit(14);
if(!str_contains($src,"'database_writes'=>0")||!str_contains($src,"'mapping_writes'=>0"))exit(15);
echo "MATCH baseline-gap prewrite static test PASS; rows=168 bridge=93 specific=73\n";
