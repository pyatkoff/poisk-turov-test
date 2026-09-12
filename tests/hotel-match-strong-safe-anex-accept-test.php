<?php
declare(strict_types=1);
define('HMSA_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_strong_safe_anex_accept.php';
$src=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_strong_safe_anex_accept.php');
$manifest=json_decode((string)file_get_contents(__DIR__.'/../reports/hotel-match-strong-safe-anex-accept-1971.json'),true,512,JSON_THROW_ON_ERROR);
if(count($manifest['pairs'])!==11)exit(10);
if(count(array_unique(array_column($manifest['pairs'],'external_hotel_id')))!==11)exit(11);
if(!str_contains($src,'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'))exit(12);
if(!str_contains($src,'FOR UPDATE'))exit(13);
if(!str_contains($src,'post_commit_registry_readback_failed'))exit(14);
if(!str_contains($src,'source_digest_drift')||!str_contains($src,'target_digest_drift'))exit(15);
if(!str_contains($src,'existing_mapping_any_policy'))exit(16);
if(str_contains(strtolower($src),'curl_exec')||str_contains(strtolower($src),'http://')||str_contains(strtolower($src),'https://'))exit(17);
foreach($manifest['pairs'] as$r){if(!preg_match('/^[0-9a-f]{64}$/',$r['source_digest'])||!preg_match('/^[0-9a-f]{64}$/',$r['target_digest']))exit(18);}
echo "MATCH strong-safe ANEX writer static test PASS\n";
