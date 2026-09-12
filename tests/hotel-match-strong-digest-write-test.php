<?php
declare(strict_types=1);
define('HMSDW_LIBRARY_ONLY',true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_strong_digest_write.php';
$m=hmsdw_manifest(__DIR__.'/../reports/hotel-match-strong-digest-write-1971.json');
if(count($m['pairs'])!==11) exit(2);
$ids=array_map(static fn($x)=>(int)$x['external_hotel_id'],$m['pairs']);
if(count(array_unique($ids))!==11) exit(3);
foreach($m['pairs'] as $p) if(($p['provider']??'')!=='anex'||($p['catalog_hotel_id']??0)<=0) exit(4);
$s=(string)file_get_contents(__DIR__.'/../scripts/diagnostics/hotel_match_strong_digest_write.php');
foreach(['SERIALIZABLE','hmpw_run($pdo,$auditManifest)','FOR UPDATE','post_commit_readback_failed','resolver_readback_failed','supplier_calls'=>0] as $needle) if(strpos($s,(string)$needle)===false) exit(5);
if(substr_count($s,'INSERT INTO anex_hotel_search_mappings')!==1) exit(6);
if(strpos($s,'UPDATE andromeda_hotel_identities')!==false||strpos($s,'DELETE FROM')!==false) exit(7);
echo "MATCH strong digest writer source guards PASS; pairs=11; supplier=0; andromeda=0\n";
