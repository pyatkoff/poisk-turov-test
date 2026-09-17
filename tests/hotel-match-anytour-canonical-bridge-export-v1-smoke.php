<?php
declare(strict_types=1);

define('MACB_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_anytour_canonical_bridge_export_v1.php';

function cb_assert(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

function cb_row(int $legacy, int $own, bool $active = true, string $name = 'Example Hotel'): array
{
    $sourceJson = macb_json(['id'=>$legacy,'name'=>$name]);
    $profileJson = macb_json(['name'=>$name,'traits'=>[]]);
    return [
        'namespace'=>'legacy_catalog','external_key'=>(string)$legacy,'anytour_hotel_id'=>(string)$own,
        'acquired_via'=>'saved_catalog','source_json'=>$sourceJson,'source_sha256'=>hash('sha256',$sourceJson),
        'first_seen_at'=>'2026-09-17 10:00:00','last_seen_at'=>'2026-09-17 11:00:00',
        'profile_json'=>$profileJson,'profile_sha256'=>hash('sha256',$profileJson),'revision'=>'1','is_active'=>$active?'1':'0',
    ];
}

$rows = [cb_row(102,1), cb_row(203,2), cb_row(7,7), cb_row(304,4,false)];
$export = macb_export_rows($rows);
cb_assert(($export['census']['source_rows'] ?? 0) === 4, 'source row count');
cb_assert(($export['census']['accepted_edges'] ?? 0) === 3, 'accepted edge count');
cb_assert(($export['census']['inactive_held'] ?? 0) === 1, 'inactive held');
cb_assert(count($export['records']) === 3, 'record count');
cb_assert($export['records'][0]['source']['id'] === '7', 'numeric ordering');
cb_assert($export['records'][1]['source']['id'] === '102', 'numeric ordering 102');
cb_assert($export['records'][1]['target']['id'] === '1', 'canonical target');
cb_assert($export['records'][1]['authority'] === 'accepted', 'accepted authority');
cb_assert($export['records'][1]['evidence_type'] === 'legacy_catalog_canonical_bridge', 'evidence type');
cb_assert($export['records'][0]['source']['id'] === $export['records'][0]['target']['id'], 'explicit equal-id fixture retained');

$badSource = cb_row(400,5); $badSource['source_sha256'] = str_repeat('0',64);
try { macb_export_rows([$badSource]); throw new RuntimeException('bad source hash accepted'); }
catch (DomainException $e) { cb_assert($e->getMessage()==='source_hash_mismatch','source hash failure'); }

$badProfile = cb_row(401,6); $badProfile['profile_sha256'] = str_repeat('f',64);
try { macb_export_rows([$badProfile]); throw new RuntimeException('bad profile hash accepted'); }
catch (DomainException $e) { cb_assert($e->getMessage()==='profile_hash_mismatch','profile hash failure'); }

$badIdentity = cb_row(402,7); $badIdentity['source_json']=macb_json(['id'=>999,'name'=>'Wrong']);
$badIdentity['source_sha256']=hash('sha256',$badIdentity['source_json']);
try { macb_export_rows([$badIdentity]); throw new RuntimeException('source identity mismatch accepted'); }
catch (DomainException $e) { cb_assert($e->getMessage()==='source_identity_mismatch','source identity failure'); }

$duplicate = cb_row(102,99);
try { macb_export_rows([cb_row(102,1),$duplicate]); throw new RuntimeException('duplicate legacy accepted'); }
catch (DomainException $e) { cb_assert($e->getMessage()==='duplicate_legacy_target','duplicate legacy failure'); }

if (in_array('--emit-jsonl', $argv ?? [], true)) {
    echo macb_jsonl($export['records']);
    exit(0);
}

echo "anytour canonical bridge export v1 smoke PASS edges=" . count($export['records']) . "\n";
