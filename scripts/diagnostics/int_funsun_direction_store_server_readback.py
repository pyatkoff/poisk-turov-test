#!/usr/bin/env python3
from __future__ import annotations

import json
import os
from pathlib import Path
import re
import subprocess
import urllib.request

REPO = "pyatkoff/poisk-turov-test"
FEATURE = "feature/anex-search-adapter-20260907"
OWNER_ID = 226193297
ISSUE = 3419
PREFIX = "/run-int-direction-store-readback-v1 "
SHA_RE = re.compile(r"\A[a-f0-9]{40}\Z")
OP_RE = re.compile(r"\Aint-andromeda-funsun-direction-store-readback-[a-z0-9-]{8,96}-v[1-9][0-9]*\Z")

REMOTE_PHP = r"""<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors','0');
ini_set('log_errors','0');

function idsr_fail(string $reason): never { throw new RuntimeException($reason); }
function idsr_digest(mixed $value): ?string {
    return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1 ? $value : null;
}
function idsr_money(mixed $value): ?string {
    return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $value) === 1 ? $value : null;
}
function idsr_rate(mixed $value): ?string {
    return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,8})?\z/D', $value) === 1 ? $value : null;
}
function idsr_reason(Throwable $error): string {
    $reason = $error->getMessage();
    return is_string($reason) && preg_match('/\A[A-Za-z0-9_.:-]{1,96}\z/D', $reason) === 1
        ? $reason : 'preflight_failed';
}
function idsr_probe(string $opsRoot, string $operation, int $sampleIndex): array {
    $path = rtrim($opsRoot, '/') . '/' . $operation . '/result.json';
    if (!is_file($path) || is_link($path)) return ['status'=>'failed','reason'=>'probe_receipt_missing'];
    $size = filesize($path);
    if (!is_int($size) || $size < 2 || $size > 1048576) return ['status'=>'failed','reason'=>'probe_receipt_missing'];
    try {
        $outer = json_decode((string)file_get_contents($path), true, 96, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        return ['status'=>'failed','reason'=>'probe_receipt_invalid'];
    }
    if (!is_array($outer) || ($outer['schema_version']??null)!==1 || ($outer['status']??null)!=='complete'
        || ($outer['mode']??null)!=='program-fuel-probe' || ($outer['operation_id']??null)!==$operation
        || ($outer['database_writes']??null)!==0 || ($outer['production_unchanged']??null)!==true) {
        return ['status'=>'failed','reason'=>'outer_contract'];
    }
    $probe = $outer['program_fuel_probe'] ?? null;
    if (!is_array($probe) || ($probe['schema_version']??null)!==1
        || ($probe['source']??null)!=='int-andromeda-program-getflights-probe-v1'
        || ($probe['status']??null)!=='complete' || ($probe['final_price_verified']??null)!==false
        || ($probe['database_writes']??null)!==0 || ($probe['mapping_writes']??null)!==0) {
        return ['status'=>'failed','reason'=>'probe_contract'];
    }
    $calls = $probe['supplier_calls'] ?? null;
    if (!is_array($calls) || ($calls['login_attempted']??null)!==true || ($calls['package']??null)!==1
        || ($calls['get_flights']??null)!==1 || ($calls['changeservice']??null)!==0
        || ($calls['calc']??null)!==0 || ($calls['booking']??null)!==0) {
        return ['status'=>'failed','reason'=>'probe_authority'];
    }
    $target = $probe['target'] ?? null;
    if (!is_array($target) || ($target['operator_family']??null)!=='funsun' || ($target['operator']??null)!=='Fun&Sun'
        || ($target['program_key']??null)!=='114' || ($target['tour_key']??null)!=='78'
        || ($target['tour_label']??null)!=='Turkey Antalya MOW'
        || ($target['target_operation']??null)!=='int-andromeda-flight-observe-20260922-v2'
        || ($target['retained_group_offer_count']??null)!==354 || ($target['retained_distinct_spo_count']??null)!==193
        || ($target['sample_distinct_spo_index']??null)!==$sampleIndex
        || ($target['mapped_local_hotel']??null)!==true || ($target['retained_freight_external']??null)!==false
        || !is_string($target['spo_key']??null) || preg_match('/\A[1-9][0-9]{0,18}\z/D',$target['spo_key'])!==1
        || idsr_digest($target['selected_offer_ref_sha256']??null)===null) {
        return ['status'=>'failed','reason'=>'target_contract'];
    }
    $fuel = $probe['fuel_surcharges_reported'] ?? null;
    if (!is_array($fuel) || count($fuel)!==2) return ['status'=>'failed','reason'=>'fuel_shape'];
    usort($fuel, static fn(mixed $a,mixed $b): int => strcmp((string)(is_array($a)?($a['route_index']??''):''),(string)(is_array($b)?($b['route_index']??''):'')));
    foreach ([0,1] as $i) {
        $row = $fuel[$i] ?? null;
        if (!is_array($row) || ($row['route_index']??null)!==(string)$i || ($row['amount']??null)!=='140'
            || ($row['currency']??null)!=='EUR' || ($row['required_reported']??null)!==true
            || ($row['packet_reported']??null)!==false || ($row['service_type']??null)!=='9'
            || ($row['source']??null)!=='andromeda_claim_service') {
            return ['status'=>'failed','reason'=>'fuel_mismatch'];
        }
    }
    $selection = $probe['cheapest_selection'] ?? null;
    if (!is_array($selection) || ($selection['candidate_counts']??null)!==[35,35]
        || ($selection['target_currency']??null)!=='RUB') {
        return ['status'=>'failed','reason'=>'selection_contract'];
    }
    $flights = $selection['selected_flights'] ?? null;
    if (!is_array($flights) || count($flights)!==2) return ['status'=>'failed','reason'=>'selection_flights'];
    usort($flights, static fn(mixed $a,mixed $b): int => strcmp((string)(is_array($a)?($a['direction']??''):''),(string)(is_array($b)?($b['direction']??''):'')));
    $expectedFlights = ['U6 3555','ZF 3004'];
    $expectedDates = ['2026-10-11','2026-10-18'];
    foreach ([0,1] as $i) {
        $flight = $flights[$i] ?? null;
        $markup = is_array($flight) ? ($flight['markup']??null) : null;
        if (!is_array($flight) || ($flight['direction']??null)!==(string)$i
            || ($flight['flight_numbers']??null)!==[$expectedFlights[$i]] || !is_array($markup)
            || ($markup['amount']??null)!=='280.00' || ($markup['currency']??null)!=='EUR') {
            return ['status'=>'failed','reason'=>'selection_mismatch'];
        }
        $departures = $flight['departure_datetimes'] ?? null;
        if (!is_array($departures) || count($departures)!==1
            || substr((string)$departures[0],0,10)!==$expectedDates[$i]) {
            return ['status'=>'failed','reason'=>'date_mismatch'];
        }
    }
    $rates = $probe['search_surcharge_estimate']['operator_currency_rates_reported'] ?? null;
    if (!is_array($rates) || !array_is_list($rates)) return ['status'=>'failed','reason'=>'fx_missing'];
    $eur = null; $rub = null;
    foreach ($rates as $row) {
        if (!is_array($row) || ($row['source']??null)!=='andromeda_claim_money') continue;
        if (($row['currency']??null)==='EUR' && ($row['is_claim_currency']??null)===true && ($row['rate']??null)==='1') $eur='1';
        if (($row['currency']??null)==='RUB' && ($row['is_claim_currency']??null)===false && is_string($row['rate']??null)) $rub=$row['rate'];
    }
    if ($eur!=='1' || $rub!=='102.7') return ['status'=>'failed','reason'=>'fx_mismatch'];
    $mtime = filemtime($path);
    if (!is_int($mtime) || $mtime < 1 || $mtime > time()+60) return ['status'=>'failed','reason'=>'receipt_time'];
    return [
        'status'=>'valid','reason'=>null,'probe'=>$probe,'target'=>$target,'rate'=>$rub,
        'observed_at'=>$mtime,'receipt_sha256'=>hash_file('sha256',$path),
        'outbound_flight'=>$expectedFlights[0],'return_flight'=>$expectedFlights[1],
        'valid_from'=>$expectedDates[0],'valid_to'=>$expectedDates[1],
    ];
}
function idsr_seed_preflight(string $root, string $directory, string $home, int $now): array {
    $opsRoot = rtrim($home,'/') . '/.anytoour-int-executor';
    $expected = [
        ['int-andromeda-funsun-antalya-fuel-probe-20260923-v1',0],
        ['int-andromeda-funsun-antalya-fuel-probe-20260923-v2',1],
    ];
    $samples = [];
    $receiptDigests = [];
    foreach ($expected as [$operation,$index]) {
        $sample = idsr_probe($opsRoot,$operation,$index);
        if (isset($sample['receipt_sha256']) && idsr_digest($sample['receipt_sha256'])!==null) $receiptDigests[]=$sample['receipt_sha256'];
        $samples[] = $sample;
        if (($sample['status']??null)!=='valid') {
            return [
                'status'=>'failed','reason'=>(string)($sample['reason']??'probe_preflight_failed'),
                'valid_probe_count'=>count(array_filter($samples,static fn(array $r):bool=>($r['status']??null)==='valid')),
                'normalized_probe_count'=>0,'independent_spo'=>false,'independent_offer'=>false,
                'receipt_sha256s'=>$receiptDigests,'evidence_pair_sha256'=>null,
                'runtime_evidence_sha256'=>null,'store_dir_writable'=>is_writable($directory),
                'fresh_exchange_count'=>0,
            ];
        }
    }
    $independentSpo = $samples[0]['target']['spo_key'] !== $samples[1]['target']['spo_key'];
    $independentOffer = $samples[0]['target']['selected_offer_ref_sha256'] !== $samples[1]['target']['selected_offer_ref_sha256'];
    $pairDigest = hash('sha256',
        $samples[0]['target']['spo_key']."\n".$samples[1]['target']['spo_key']."\n".
        $samples[0]['target']['selected_offer_ref_sha256']."\n".$samples[1]['target']['selected_offer_ref_sha256']
    );
    if (!$independentSpo || !$independentOffer) {
        return [
            'status'=>'failed','reason'=>'independent_evidence','valid_probe_count'=>2,
            'normalized_probe_count'=>0,'independent_spo'=>$independentSpo,'independent_offer'=>$independentOffer,
            'receipt_sha256s'=>$receiptDigests,'evidence_pair_sha256'=>$pairDigest,
            'runtime_evidence_sha256'=>null,'store_dir_writable'=>is_writable($directory),
            'fresh_exchange_count'=>0,
        ];
    }
    $evidencePath = $root . '/_preview/search3-anex-candidate/app/integrations/operator-fuel-rule-evidence.php';
    if (!is_file($evidencePath) || is_link($evidencePath) || filesize($evidencePath) < 2 || filesize($evidencePath) > 1048576) {
        return [
            'status'=>'failed','reason'=>'evidence_runtime_missing','valid_probe_count'=>2,
            'normalized_probe_count'=>0,'independent_spo'=>true,'independent_offer'=>true,
            'receipt_sha256s'=>$receiptDigests,'evidence_pair_sha256'=>$pairDigest,
            'runtime_evidence_sha256'=>null,'store_dir_writable'=>is_writable($directory),
            'fresh_exchange_count'=>0,
        ];
    }
    require_once $evidencePath;
    if (!class_exists('AnyTourOperatorFuelRuleEvidenceV1')) {
        return [
            'status'=>'failed','reason'=>'evidence_runtime_class_missing','valid_probe_count'=>2,
            'normalized_probe_count'=>0,'independent_spo'=>true,'independent_offer'=>true,
            'receipt_sha256s'=>$receiptDigests,'evidence_pair_sha256'=>$pairDigest,
            'runtime_evidence_sha256'=>hash_file('sha256',$evidencePath),'store_dir_writable'=>is_writable($directory),
            'fresh_exchange_count'=>0,
        ];
    }
    $normalized = 0;
    $freshExchange = 0;
    foreach ($samples as $sample) {
        $sourceResponse = AnyTourOperatorFuelRuleEvidenceV1::hash([
            'operation'=>'retained_probe','program_fuel_probe'=>$sample['probe'],
        ]);
        $exchange = [
            'from'=>'EUR','to'=>'RUB','rate'=>$sample['rate'],'source'=>'andromeda_claim_money',
            'observed_at'=>$sample['observed_at'],'expires_at'=>$sample['observed_at']+86400,
            'evidence_sha256'=>AnyTourOperatorFuelRuleEvidenceV1::hash([
                'source_response_sha256'=>$sourceResponse,'from'=>'EUR','to'=>'RUB','rate'=>$sample['rate'],
            ]),
        ];
        $raw = [
            'provider'=>'andromeda','operator'=>'Fun&Sun',
            'direction'=>['market'=>'departure:1','destination'=>'country:4'],
            'scope'=>[
                'market'=>'andromeda:departure:1',
                'outbound'=>['origin'=>'departure:1','destination'=>'country:4','carrier'=>'not_exposed_by_probe','flight'=>$sample['outbound_flight']],
                'return'=>['origin'=>'country:4','destination'=>'departure:1','carrier'=>'not_exposed_by_probe','flight'=>$sample['return_flight']],
                'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
            ],
            'unit'=>'per_person_one_way','base_relation'=>'excluded','amount'=>'140.00','currency'=>'EUR',
            'observed_at'=>$sample['observed_at'],'expires_at'=>$sample['observed_at']+2592000,
            'base_includes_other_required_charges'=>true,
            'valid_from'=>$sample['valid_from'],'valid_to'=>$sample['valid_to'],
            'offer_ref_digest'=>$sample['target']['selected_offer_ref_sha256'],
            'source_response_sha256'=>$sourceResponse,'source'=>'andromeda_claim_service','exchange'=>$exchange,
        ];
        $raw['evidence_sha256'] = AnyTourOperatorFuelRuleEvidenceV1::hash([
            'source_response_sha256'=>$sourceResponse,
            'derivation'=>'two_distinct_spo_required_fuel_equals_cheapest_party2_markup',
            'amount'=>'140.00','currency'=>'EUR','unit'=>'per_person_one_way',
            'direction'=>['market'=>'departure:1','destination'=>'country:4'],
            'flight_pair'=>[$sample['outbound_flight'],$sample['return_flight']],
            'exchange_rate'=>$sample['rate'],
        ]);
        try {
            $observation = AnyTourOperatorFuelRuleEvidenceV1::observation($raw);
        } catch (Throwable $error) {
            return [
                'status'=>'failed','reason'=>idsr_reason($error),'valid_probe_count'=>2,
                'normalized_probe_count'=>$normalized,'independent_spo'=>true,'independent_offer'=>true,
                'receipt_sha256s'=>$receiptDigests,'evidence_pair_sha256'=>$pairDigest,
                'runtime_evidence_sha256'=>hash_file('sha256',$evidencePath),'store_dir_writable'=>is_writable($directory),
                'fresh_exchange_count'=>$freshExchange,
            ];
        }
        if (($observation['operator_family']??null)!=='fun_and_sun'
            || ($observation['direction']??null)!==['operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:4']
            || ($observation['amount']??null)!=='140.00' || ($observation['currency']??null)!=='EUR'
            || ($observation['unit']??null)!=='per_person_one_way' || ($observation['base_relation']??null)!=='excluded') {
            return [
                'status'=>'failed','reason'=>'normalized_contract','valid_probe_count'=>2,
                'normalized_probe_count'=>$normalized,'independent_spo'=>true,'independent_offer'=>true,
                'receipt_sha256s'=>$receiptDigests,'evidence_pair_sha256'=>$pairDigest,
                'runtime_evidence_sha256'=>hash_file('sha256',$evidencePath),'store_dir_writable'=>is_writable($directory),
                'fresh_exchange_count'=>$freshExchange,
            ];
        }
        ++$normalized;
        if (($observation['exchange']['observed_at']??0) <= $now && ($observation['exchange']['expires_at']??0) > $now) ++$freshExchange;
    }
    return [
        'status'=>'ready','reason'=>null,'valid_probe_count'=>2,'normalized_probe_count'=>$normalized,
        'independent_spo'=>true,'independent_offer'=>true,'receipt_sha256s'=>$receiptDigests,
        'evidence_pair_sha256'=>$pairDigest,'runtime_evidence_sha256'=>hash_file('sha256',$evidencePath),
        'store_dir_writable'=>is_writable($directory),'fresh_exchange_count'=>$freshExchange,
    ];
}
function idsr_target_path_state(string $directory, array $expectedDirection): array {
    $digest = 'd4569fff8f4a74d2098dd2d1f31374863070ccea7ed9efd25eb3477c50758111';
    if (class_exists('AnyTourOperatorFuelRuleEvidenceV1')) {
        try {
            if (AnyTourOperatorFuelRuleEvidenceV1::directionDigest($expectedDirection) !== $digest) idsr_fail('target_digest_contract');
        } catch (Throwable $error) {
            idsr_fail('target_digest_contract');
        }
    }
    $basename = 'operator-fuel-rule-v2-' . $digest . '.json';
    $path = rtrim($directory,'/') . '/' . $basename;
    $stat = @lstat($path);
    $lstatExists = is_array($stat);
    $isLink = is_link($path);
    $isFile = is_file($path);
    $exists = file_exists($path);
    $size = $lstatExists && isset($stat['size']) && is_int($stat['size']) ? $stat['size'] : null;
    $readable = $isFile && is_readable($path);
    $envelopeStatus = 'absent';
    $observationCount = null;
    $storeSha = null;
    if ($isLink) {
        $envelopeStatus = 'symlink';
    } elseif ($lstatExists && !$isFile) {
        $envelopeStatus = 'not_file';
    } elseif (!$lstatExists) {
        $envelopeStatus = 'absent';
    } elseif (!is_int($size) || $size < 2 || $size > 262144) {
        $envelopeStatus = 'size_invalid';
    } elseif (!$readable) {
        $envelopeStatus = 'unreadable';
    } else {
        try {
            $value = json_decode((string)file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($value)) {
                $envelopeStatus = 'envelope_invalid';
            } else {
                $keys = array_keys($value); sort($keys);
                if ($keys !== ['direction','direction_sha256','observations','version'] || ($value['version']??null)!==2
                    || ($value['direction_sha256']??null)!==$digest || ($value['direction']??null)!==$expectedDirection
                    || !is_array($value['observations']??null) || !array_is_list($value['observations'])
                    || count($value['observations'])>128) {
                    $envelopeStatus = 'envelope_invalid';
                } else {
                    $envelopeStatus = 'valid';
                    $observationCount = count($value['observations']);
                    $storeSha = hash_file('sha256',$path);
                }
            }
        } catch (Throwable $error) {
            $envelopeStatus = 'json_invalid';
        }
    }
    return [
        'basename'=>$basename,'direction_sha256'=>$digest,'lstat_exists'=>$lstatExists,'exists'=>$exists,
        'is_file'=>$isFile,'is_link'=>$isLink,'size_bytes'=>$size,'readable'=>$readable,
        'envelope_status'=>$envelopeStatus,'observation_count'=>$observationCount,'store_sha256'=>$storeSha,
    ];
}
function idsr_writer_prerequisites(string $directory, array $target): array {
    $real = realpath($directory);
    $temps = glob(rtrim($directory,'/') . '/.direction-fuel-seed.*', GLOB_NOSORT);
    if ($temps === false || count($temps) > 128) idsr_fail('temp_inventory_invalid');
    $tempFiles = 0; $tempLinks = 0; $tempOther = 0;
    foreach ($temps as $path) {
        if (is_link($path)) { ++$tempLinks; continue; }
        if (is_file($path)) { ++$tempFiles; continue; }
        ++$tempOther;
    }
    $targetReplaceable = ($target['is_link']??true)===false
        && ((($target['lstat_exists']??true)===false) || (($target['is_file']??false)===true));
    return [
        'directory_exists'=>is_dir($directory),'directory_is_link'=>is_link($directory),
        'directory_writable'=>is_writable($directory),
        'directory_realpath_ok'=>is_string($real) && basename($real)==='searches',
        'target_absent'=>($target['lstat_exists']??true)===false,
        'target_replaceable'=>$targetReplaceable,
        'stale_temp_count'=>count($temps),'stale_temp_file_count'=>$tempFiles,
        'stale_temp_link_count'=>$tempLinks,'stale_temp_other_count'=>$tempOther,
    ];
}
try {
    $home = getenv('HOME');
    if (!is_string($home) || $home === '') idsr_fail('home_missing');
    $root = rtrim($home, '/') . '/www/anytoour.ru';
    $configPath = $root . '/_preview/search3-anex-candidate/.andromeda-private.php';
    if (!is_file($configPath) || is_link($configPath) || filesize($configPath) > 65536) idsr_fail('private_config_missing');
    $config = require $configPath;
    $catalogPath = is_array($config) ? ($config['catalog_path'] ?? null) : null;
    if (!is_string($catalogPath) || $catalogPath === '') idsr_fail('catalog_path_missing');
    $directory = dirname($catalogPath) . '/searches';
    if (!is_dir($directory) || is_link($directory) || basename($directory) !== 'searches') idsr_fail('store_root_invalid');
    $now = time();
    $seedPreflight = idsr_seed_preflight($root,$directory,$home,$now);

    $expectedDirection = ['operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:4'];
    $targetPathState = idsr_target_path_state($directory,$expectedDirection);
    $writerPrerequisites = idsr_writer_prerequisites($directory,$targetPathState);
    $files = glob($directory . '/operator-fuel-rule-v2-*.json', GLOB_NOSORT);
    if ($files === false || count($files) > 512) idsr_fail('store_inventory_invalid');
    sort($files, SORT_STRING);
    $targetFiles = [];
    foreach ($files as $path) {
        if (!is_file($path) || is_link($path)) continue;
        $size = filesize($path);
        if (!is_int($size) || $size < 2 || $size > 262144) continue;
        $base = basename($path);
        if (preg_match('/\Aoperator-fuel-rule-v2-([a-f0-9]{64})\.json\z/D', $base, $match) !== 1) continue;
        try { $value = json_decode((string)file_get_contents($path), true, 96, JSON_THROW_ON_ERROR); }
        catch (Throwable $ignored) { continue; }
        if (!is_array($value) || ($value['direction'] ?? null) !== $expectedDirection) continue;
        if (($value['version'] ?? null) !== 2 || ($value['direction_sha256'] ?? null) !== $match[1]
            || !is_array($value['observations'] ?? null) || !array_is_list($value['observations'])) idsr_fail('target_envelope_invalid');
        $offers=[]; $evidence=[]; $facts=[]; $rates=[]; $freshRates=[]; $freshExchangeCount=0;
        $latestExchangeObservedAt=0; $latestExchangeExpiresAt=0;
        foreach ($value['observations'] as $row) {
            if (!is_array($row) || ($row['schema_version']??null)!==2 || ($row['operator_family']??null)!=='fun_and_sun'
                || ($row['direction']??null)!==$expectedDirection || !in_array($row['provider']??null,['andromeda','tourvisor'],true)
                || ($row['kind']??null)!=='fuel') idsr_fail('target_observation_invalid');
            $amount=idsr_money($row['amount']??null); $currency=$row['currency']??null; $unit=$row['unit']??null;
            $relation=$row['base_relation']??null; $offer=idsr_digest($row['offer_ref_digest']??null);
            $ev=idsr_digest($row['evidence_sha256']??null); $sourceResponse=idsr_digest($row['source_response_sha256']??null);
            $observed=$row['observed_at']??null; $expires=$row['expires_at']??null;
            if ($amount===null || !is_string($currency) || preg_match('/\A[A-Z]{3}\z/D',$currency)!==1
                || !in_array($unit,['party_roundtrip','per_person_one_way'],true) || !in_array($relation,['included','excluded'],true)
                || $offer===null || $ev===null || $sourceResponse===null || !is_int($observed) || !is_int($expires)
                || $observed<1 || $expires<=$observed) idsr_fail('target_observation_shape');
            $offers[$offer]=true; $evidence[$ev]=true; $facts[$amount.'|'.$currency.'|'.$unit.'|'.$relation]=true;
            $exchange=$row['exchange']??null;
            if (is_array($exchange)) {
                $rate=idsr_rate($exchange['rate']??null); $exchangeEvidence=idsr_digest($exchange['evidence_sha256']??null);
                $exchangeObserved=$exchange['observed_at']??null; $exchangeExpires=$exchange['expires_at']??null;
                if (($exchange['from']??null)!==$currency || ($exchange['to']??null)!=='RUB' || $rate===null || $exchangeEvidence===null
                    || !is_int($exchangeObserved) || !is_int($exchangeExpires) || $exchangeObserved<$observed
                    || $exchangeExpires>$expires || $exchangeExpires<=$exchangeObserved) idsr_fail('target_exchange_invalid');
                $rates[$rate]=true;
                if ($exchangeObserved<=$now && $exchangeExpires>$now) {
                    $freshRates[$rate]=true; ++$freshExchangeCount;
                    if ($exchangeObserved >= $latestExchangeObservedAt) {
                        $latestExchangeObservedAt=$exchangeObserved; $latestExchangeExpiresAt=$exchangeExpires;
                    }
                }
            }
        }
        ksort($offers,SORT_STRING); ksort($evidence,SORT_STRING); ksort($facts,SORT_STRING); ksort($rates,SORT_STRING); ksort($freshRates,SORT_STRING);
        $targetFiles[] = [
            'direction_sha256'=>$match[1],'store_sha256'=>hash_file('sha256',$path),
            'observation_count'=>count($value['observations']),'independent_offer_count'=>count($offers),
            'independent_evidence_count'=>count($evidence),'offer_set_sha256'=>hash('sha256',implode("\n",array_keys($offers))),
            'evidence_set_sha256'=>hash('sha256',implode("\n",array_keys($evidence))),'facts'=>array_keys($facts),
            'exchange_rates'=>array_keys($rates),'fresh_exchange_rates'=>array_keys($freshRates),
            'fresh_exchange_count'=>$freshExchangeCount,'latest_exchange_observed_at'=>$latestExchangeObservedAt?:null,
            'latest_exchange_expires_at'=>$latestExchangeExpiresAt?:null,
        ];
    }
    $status='absent';
    if (count($targetFiles)===1) {
        $row=$targetFiles[0];
        $confirmed=$row['observation_count']>=2 && $row['independent_offer_count']>=2 && $row['independent_evidence_count']>=2
            && $row['facts']===['140.00|EUR|per_person_one_way|excluded'] && $row['fresh_exchange_rates']===['102.7']
            && $row['fresh_exchange_count']>=2;
        $status=$confirmed?'confirmed':'insufficient';
    } elseif (count($targetFiles)>1) $status='ambiguous';
    echo json_encode([
        'schema_version'=>1,'source'=>'int-funsun-direction-store-server-readback-v1','status'=>$status,
        'direction'=>$expectedDirection,'target_store_count'=>count($targetFiles),'target_stores'=>$targetFiles,
        'target_path_state'=>$targetPathState,'writer_prerequisites'=>$writerPrerequisites,
        'seed_preflight'=>$seedPreflight,'supplier_calls'=>0,'database_reads'=>0,'database_writes'=>0,
        'store_writes'=>0,'runtime_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'final_price_verified'=>false,'server_time'=>$now,
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $error) {
    $reason=idsr_reason($error);
    echo json_encode([
        'schema_version'=>1,'source'=>'int-funsun-direction-store-server-readback-v1','status'=>'failed','reason'=>$reason,
        'supplier_calls'=>0,'database_reads'=>0,'database_writes'=>0,'store_writes'=>0,'runtime_writes'=>0,
        'booking_calls'=>0,'lead_calls'=>0,'final_price_verified'=>false,
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), "\n";
    exit(0);
}
"""


def need(condition: bool, reason: str) -> None:
    if not condition:
        raise RuntimeError(reason)


def parse_command(body: str) -> dict[str, str]:
    need(isinstance(body, str) and body.startswith(PREFIX), "command_prefix")
    parts = body.strip().split()
    need(len(parts) == 3 and parts[0] == PREFIX.strip(), "command_shape")
    source_sha, operation_id = parts[1], parts[2]
    need(SHA_RE.fullmatch(source_sha) is not None, "source_sha")
    need(OP_RE.fullmatch(operation_id) is not None, "operation_id")
    return {"source_sha": source_sha, "operation_id": operation_id}


def api_get(path: str, token: str) -> dict:
    request = urllib.request.Request(
        "https://api.github.com/repos/" + REPO + path,
        headers={
            "Authorization": "Bearer " + token,
            "Accept": "application/vnd.github+json",
            "X-GitHub-Api-Version": "2022-11-28",
        },
    )
    with urllib.request.urlopen(request, timeout=30) as response:
        value = json.load(response)
    need(isinstance(value, dict), "github_shape")
    return value


def checked_command() -> dict[str, str]:
    token = os.environ.get("GH_TOKEN", "")
    event_path = Path(os.environ.get("GITHUB_EVENT_PATH", ""))
    need(bool(token) and event_path.is_file(), "github_context")
    event = json.loads(event_path.read_text())
    need(isinstance(event, dict) and event.get("issue", {}).get("number") == ISSUE and not event.get("issue", {}).get("pull_request"), "issue")
    comment = event.get("comment", {})
    need(isinstance(comment, dict) and comment.get("user", {}).get("id") == OWNER_ID and comment.get("author_association") == "OWNER", "owner")
    comment_id = comment.get("id")
    need(isinstance(comment_id, int) and comment_id > 0, "comment_id")
    fresh = api_get("/issues/comments/" + str(comment_id), token)
    body = comment.get("body", "")
    need(fresh.get("body") == body and fresh.get("user", {}).get("id") == OWNER_ID and fresh.get("author_association") == "OWNER", "comment_changed")
    command = parse_command(body)
    main_sha = api_get("/git/ref/heads/main", token).get("object", {}).get("sha")
    feature_sha = api_get("/git/ref/heads/" + FEATURE, token).get("object", {}).get("sha")
    need(main_sha == os.environ.get("GITHUB_SHA"), "main_changed")
    need(feature_sha == command["source_sha"], "feature_changed")
    return command


def validate_remote(data: dict) -> dict:
    need(isinstance(data, dict), "remote_shape")
    need(data.get("schema_version") == 1, "remote_schema")
    need(data.get("source") == "int-funsun-direction-store-server-readback-v1", "remote_source")
    status = data.get("status")
    need(status in {"confirmed", "absent", "insufficient", "ambiguous", "failed"}, "remote_status")
    for key in ("supplier_calls", "database_reads", "database_writes", "store_writes", "runtime_writes", "booking_calls", "lead_calls"):
        need(data.get(key) == 0, "remote_authority_" + key)
    need(data.get("final_price_verified") is False, "remote_final_verified")
    if status == "failed":
        need(isinstance(data.get("reason"), str) and len(data["reason"]) <= 96, "remote_reason")
        return data
    expected_direction = {"operator_family": "fun_and_sun", "market": "departure:1", "destination": "country:4"}
    expected_digest = "d4569fff8f4a74d2098dd2d1f31374863070ccea7ed9efd25eb3477c50758111"
    expected_basename = "operator-fuel-rule-v2-" + expected_digest + ".json"
    need(data.get("direction") == expected_direction, "remote_direction")
    preflight = data.get("seed_preflight")
    need(isinstance(preflight, dict), "remote_seed_preflight")
    need(preflight.get("status") in {"ready", "failed"}, "remote_seed_preflight_status")
    reason = preflight.get("reason")
    need(reason is None or (isinstance(reason, str) and 1 <= len(reason) <= 96), "remote_seed_preflight_reason")
    for key in ("valid_probe_count", "normalized_probe_count", "fresh_exchange_count"):
        need(isinstance(preflight.get(key), int) and 0 <= preflight[key] <= 2, "remote_seed_preflight_count_" + key)
    for key in ("independent_spo", "independent_offer", "store_dir_writable"):
        need(isinstance(preflight.get(key), bool), "remote_seed_preflight_bool_" + key)
    receipts = preflight.get("receipt_sha256s")
    need(isinstance(receipts, list) and len(receipts) <= 2 and all(isinstance(x, str) and re.fullmatch(r"[a-f0-9]{64}", x) for x in receipts), "remote_seed_preflight_receipts")
    for key in ("evidence_pair_sha256", "runtime_evidence_sha256"):
        value = preflight.get(key)
        need(value is None or (isinstance(value, str) and re.fullmatch(r"[a-f0-9]{64}", value)), "remote_seed_preflight_digest_" + key)
    if preflight["status"] == "ready":
        need(reason is None, "remote_seed_preflight_ready_reason")
        need(preflight["valid_probe_count"] == 2 and preflight["normalized_probe_count"] == 2, "remote_seed_preflight_ready_counts")
        need(preflight["independent_spo"] and preflight["independent_offer"], "remote_seed_preflight_ready_independence")
        need(len(receipts) == 2, "remote_seed_preflight_ready_receipts")
    else:
        need(isinstance(reason, str), "remote_seed_preflight_failed_reason")
    target = data.get("target_path_state")
    need(isinstance(target, dict), "remote_target_path")
    need(target.get("basename") == expected_basename and target.get("direction_sha256") == expected_digest, "remote_target_identity")
    for key in ("lstat_exists", "exists", "is_file", "is_link", "readable"):
        need(isinstance(target.get(key), bool), "remote_target_bool_" + key)
    size = target.get("size_bytes")
    need(size is None or (isinstance(size, int) and 0 <= size <= 1048576), "remote_target_size")
    need(target.get("envelope_status") in {"absent", "symlink", "not_file", "size_invalid", "unreadable", "json_invalid", "envelope_invalid", "valid"}, "remote_target_envelope")
    observation_count = target.get("observation_count")
    need(observation_count is None or (isinstance(observation_count, int) and 0 <= observation_count <= 128), "remote_target_observations")
    store_sha = target.get("store_sha256")
    need(store_sha is None or (isinstance(store_sha, str) and re.fullmatch(r"[a-f0-9]{64}", store_sha)), "remote_target_store_sha")
    if target["envelope_status"] == "absent":
        need(not target["lstat_exists"] and not target["exists"] and not target["is_file"] and not target["is_link"], "remote_target_absent_shape")
    if target["envelope_status"] == "valid":
        need(target["lstat_exists"] and target["exists"] and target["is_file"] and not target["is_link"] and target["readable"], "remote_target_valid_shape")
        need(isinstance(observation_count, int) and isinstance(store_sha, str), "remote_target_valid_details")
    writer = data.get("writer_prerequisites")
    need(isinstance(writer, dict), "remote_writer_prerequisites")
    for key in ("directory_exists", "directory_is_link", "directory_writable", "directory_realpath_ok", "target_absent", "target_replaceable"):
        need(isinstance(writer.get(key), bool), "remote_writer_bool_" + key)
    for key in ("stale_temp_count", "stale_temp_file_count", "stale_temp_link_count", "stale_temp_other_count"):
        need(isinstance(writer.get(key), int) and 0 <= writer[key] <= 128, "remote_writer_count_" + key)
    need(writer["stale_temp_count"] == writer["stale_temp_file_count"] + writer["stale_temp_link_count"] + writer["stale_temp_other_count"], "remote_writer_temp_sum")
    need(writer["target_absent"] == (not target["lstat_exists"]), "remote_writer_target_absent")
    stores = data.get("target_stores")
    need(isinstance(stores, list) and len(stores) <= 1, "remote_store_count")
    need(data.get("target_store_count") == len(stores), "remote_store_count")
    for row in stores:
        need(isinstance(row, dict), "remote_store_shape")
        for digest_key in ("direction_sha256", "store_sha256", "offer_set_sha256", "evidence_set_sha256"):
            need(isinstance(row.get(digest_key), str) and re.fullmatch(r"[a-f0-9]{64}", row[digest_key]), "remote_digest_" + digest_key)
        for count_key in ("observation_count", "independent_offer_count", "independent_evidence_count", "fresh_exchange_count"):
            need(isinstance(row.get(count_key), int) and 0 <= row[count_key] <= 128, "remote_count_" + count_key)
        need(isinstance(row.get("facts"), list) and all(isinstance(x, str) and len(x) <= 96 for x in row["facts"]), "remote_facts")
        need(isinstance(row.get("exchange_rates"), list) and isinstance(row.get("fresh_exchange_rates"), list)
             and all(isinstance(x, str) and len(x) <= 32 for x in row["exchange_rates"] + row["fresh_exchange_rates"]), "remote_rates")
    if status == "confirmed":
        need(len(stores) == 1, "remote_confirmed_store")
        row = stores[0]
        need(row["observation_count"] >= 2, "remote_confirmed_observations")
        need(row["independent_offer_count"] >= 2, "remote_confirmed_offers")
        need(row["independent_evidence_count"] >= 2, "remote_confirmed_evidence")
        need(row["facts"] == ["140.00|EUR|per_person_one_way|excluded"], "remote_confirmed_fact")
        need(row["fresh_exchange_rates"] == ["102.7"], "remote_confirmed_fx")
        need(row["fresh_exchange_count"] >= 2, "remote_confirmed_fx_count")
    return data


def ssh_options(key: Path, known: Path) -> list[str]:
    return ["-T", "-i", str(key), "-o", "IdentitiesOnly=yes", "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes",
            "-o", "UserKnownHostsFile=" + str(known), "-o", "GlobalKnownHostsFile=/dev/null", "-o", "ConnectTimeout=15",
            "-o", "ServerAliveInterval=15", "-o", "ServerAliveCountMax=3", "-o", "LogLevel=ERROR"]


def execute(command: dict[str, str]) -> dict:
    host = os.environ.get("INT_SSH_HOST", "").strip()
    user = os.environ.get("INT_SSH_USER", "").strip()
    raw_key = os.environ.get("INT_SSH_KEY", "").strip()
    need(bool(host and user and raw_key), "ssh_config")
    need(re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9.-]*", host) is not None and re.fullmatch(r"[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}", user) is not None, "ssh_identity")
    output = Path(os.environ["RUNNER_TEMP"]) / "int-direction-store-readback"
    output.mkdir(mode=0o700, exist_ok=True)
    key = output / "key"; known = output / "known_hosts"
    key.write_text(raw_key.rstrip() + "\n"); key.chmod(0o600)
    subprocess.run(["ssh-keygen", "-y", "-f", str(key)], stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, check=True, timeout=10)
    scan = subprocess.run(["ssh-keyscan", "-T", "15", "-t", "ed25519", host], capture_output=True, check=True, timeout=20).stdout
    need(bool(scan), "ssh_hostkey"); known.write_bytes(scan); known.chmod(0o600)
    run = subprocess.run(["ssh", *ssh_options(key, known), user + "@" + host, "php -d display_errors=0 -d log_errors=0"],
                         input=REMOTE_PHP.encode(), capture_output=True, timeout=90)
    need(run.returncode == 0, "remote_exit"); need(not run.stderr.strip(), "remote_stderr")
    try: remote = json.loads(run.stdout.decode().strip())
    except Exception as exc: raise RuntimeError("remote_json") from exc
    remote = validate_remote(remote)
    result = {
        "schema_version": 1, "source": "int-funsun-direction-store-control-readback-v1",
        "operation_id": command["operation_id"], "source_sha": command["source_sha"], "control_sha": os.environ.get("GITHUB_SHA"),
        "readback": remote, "supplier_calls": 0, "database_reads": 0, "database_writes": 0, "store_writes": 0,
        "runtime_writes": 0, "booking_calls": 0, "lead_calls": 0, "production_unchanged": True,
    }
    path = output / "result.json"; path.write_text(json.dumps(result, sort_keys=True, separators=(",", ":"))); path.chmod(0o600)
    return result


def main() -> int:
    command = checked_command()
    result = execute(command)
    print(json.dumps(result, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())