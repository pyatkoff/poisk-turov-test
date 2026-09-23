#!/usr/bin/env python3
from __future__ import annotations

import argparse
import base64
import hashlib
import io
import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import tarfile
import time
import urllib.request

REPO = 'pyatkoff/poisk-turov-test'
FEATURE = 'feature/anex-search-adapter-20260907'
OWNER_ID = 226193297
ISSUE = 3419
PREFIX = '/run-int-server-v1 '
OP_RE = re.compile(r'\Aint-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*\Z')
SHA_RE = re.compile(r'\A[a-f0-9]{40}\Z')

FIXED = [
    'scripts/ops/anex_local_offer_collect.php',
    'scripts/ops/anex_local_offer_demand_fill.php',
    'scripts/ops/anex_local_offer_demand_queue.php',
    'scripts/ops/andromeda_local_offer_collect.php',
    'v2/api-anex-search3-preview.php',
    'v2/api-andromeda-search3-preview.php',
    'v2/data/hotel-details-v1.php',
    'scripts/diagnostics/hotel_match_anex_effective_coverage.php',
    'scripts/diagnostics/hotel_match_live942_frontier_plan_v1.php',
    'scripts/diagnostics/hotel_match_live942_tv_anex_refresh_v1.py',
    'scripts/diagnostics/hotel_match_live942_samo_anex_refresh_v1.php',
    'scripts/diagnostics/hotel_match_live_anex_samo_missing_secondary_audit_v1.php',
    'scripts/diagnostics/hotel_match_live942_tv_candidate_reconcile_v1.php',
    'scripts/diagnostics/hotel_match_live942_tv_writer_v1.php',
    'scripts/diagnostics/hotel_match_tv_samo_anex_coverage_v1.php',
    'scripts/diagnostics/hotel_match_current_coverage_wrapper_v1.php',
]

# Persistent installation is intentionally narrower than the source bundle:
# only provider/runtime PHP and the existing Andromeda collector entrypoint.
# Public endpoints, UI, LOCAL readers and configuration are never copied.
INSTALL_PREFIX = 'app/integrations/'
INSTALL_FIXED = []

FUNSUN_DIRECTION_FUEL_SEED_PHP = r'''
<?php
declare(strict_types=1);

function fsdf_fail(string $reason): never { throw new RuntimeException($reason); }
function fsdf_json(string $path): array {
    if (!is_file($path) || is_link($path) || filesize($path) < 2 || filesize($path) > 1048576) fsdf_fail('probe_receipt_missing');
    $value=json_decode((string)file_get_contents($path),true,96,JSON_THROW_ON_ERROR);
    if (!is_array($value)) fsdf_fail('probe_receipt_invalid');
    return $value;
}
function fsdf_money_units(mixed $value): int {
    if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D',$value)!==1) fsdf_fail('money_invalid');
    [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
    return ((int)$whole)*100+(int)str_pad($fraction,2,'0');
}
function fsdf_money(int $units): string {
    return intdiv($units,100).'.'.str_pad((string)($units%100),2,'0',STR_PAD_LEFT);
}
function fsdf_rate(array $probe): string {
    $rows=$probe['search_surcharge_estimate']['operator_currency_rates_reported']??null;
    if (!is_array($rows) || !array_is_list($rows)) fsdf_fail('fx_missing');
    $eur=null;$rub=null;
    foreach($rows as $row){
        if(!is_array($row)||($row['source']??null)!=='andromeda_claim_money') continue;
        if(($row['currency']??null)==='EUR'&&($row['is_claim_currency']??null)===true&&($row['rate']??null)==='1') $eur='1';
        if(($row['currency']??null)==='RUB'&&($row['is_claim_currency']??null)===false&&is_string($row['rate']??null)) $rub=$row['rate'];
    }
    if($eur!=='1'||$rub!=='102.7') fsdf_fail('fx_mismatch');
    return $rub;
}
function fsdf_probe(array $outer,string $operation,int $sampleIndex): array {
    if(($outer['schema_version']??null)!==1||($outer['status']??null)!=='complete'
        ||($outer['mode']??null)!=='program-fuel-probe'||($outer['operation_id']??null)!==$operation
        ||($outer['database_writes']??null)!==0||($outer['production_unchanged']??null)!==true) fsdf_fail('outer_contract');
    $probe=$outer['program_fuel_probe']??null;
    if(!is_array($probe)||($probe['schema_version']??null)!==1
        ||($probe['source']??null)!=='int-andromeda-program-getflights-probe-v1'
        ||($probe['status']??null)!=='complete'||($probe['final_price_verified']??null)!==false
        ||($probe['database_writes']??null)!==0||($probe['mapping_writes']??null)!==0) fsdf_fail('probe_contract');
    $calls=$probe['supplier_calls']??null;
    if(!is_array($calls)||($calls['login_attempted']??null)!==true||($calls['package']??null)!==1
        ||($calls['get_flights']??null)!==1||($calls['changeservice']??null)!==0
        ||($calls['calc']??null)!==0||($calls['booking']??null)!==0) fsdf_fail('probe_authority');
    $target=$probe['target']??null;
    if(!is_array($target)||($target['operator_family']??null)!=='funsun'||($target['operator']??null)!=='Fun&Sun'
        ||($target['program_key']??null)!=='114'||($target['tour_key']??null)!=='78'
        ||($target['tour_label']??null)!=='Turkey Antalya MOW'
        ||($target['target_operation']??null)!=='int-andromeda-flight-observe-20260922-v2'
        ||($target['retained_group_offer_count']??null)!==354||($target['retained_distinct_spo_count']??null)!==193
        ||($target['sample_distinct_spo_index']??null)!==$sampleIndex
        ||($target['mapped_local_hotel']??null)!==true||($target['retained_freight_external']??null)!==false
        ||!is_string($target['spo_key']??null)||!preg_match('/\A[1-9][0-9]{0,18}\z/D',$target['spo_key'])
        ||!is_string($target['selected_offer_ref_sha256']??null)
        ||!preg_match('/\A[a-f0-9]{64}\z/D',$target['selected_offer_ref_sha256'])) fsdf_fail('target_contract');

    $fuel=$probe['fuel_surcharges_reported']??null;
    if(!is_array($fuel)||count($fuel)!==2) fsdf_fail('fuel_shape');
    usort($fuel,static fn(array $a,array $b):int=>strcmp((string)($a['route_index']??''),(string)($b['route_index']??'')));
    foreach([0,1] as $i){
        $row=$fuel[$i]??null;
        if(!is_array($row)||($row['route_index']??null)!==(string)$i||($row['amount']??null)!=='140'
            ||($row['currency']??null)!=='EUR'||($row['required_reported']??null)!==true
            ||($row['packet_reported']??null)!==false||($row['service_type']??null)!=='9'
            ||($row['source']??null)!=='andromeda_claim_service') fsdf_fail('fuel_mismatch');
    }

    $selection=$probe['cheapest_selection']??null;
    if(!is_array($selection)||($selection['candidate_counts']??null)!==[35,35]
        ||($selection['target_currency']??null)!=='RUB') fsdf_fail('selection_contract');
    $flights=$selection['selected_flights']??null;
    if(!is_array($flights)||count($flights)!==2) fsdf_fail('selection_flights');
    usort($flights,static fn(array $a,array $b):int=>strcmp((string)($a['direction']??''),(string)($b['direction']??'')));
    $expectedFlights=['U6 3555','ZF 3004'];
    $expectedDates=['2026-10-11','2026-10-18'];
    foreach([0,1] as $i){
        $flight=$flights[$i]??null;$markup=is_array($flight)?($flight['markup']??null):null;
        if(!is_array($flight)||($flight['direction']??null)!==(string)$i
            ||($flight['flight_numbers']??null)!==[$expectedFlights[$i]]
            ||!is_array($markup)||($markup['amount']??null)!=='280.00'||($markup['currency']??null)!=='EUR') fsdf_fail('selection_mismatch');
        if(fsdf_money_units($markup['amount'])!==fsdf_money_units('140')*2) fsdf_fail('unit_corroboration');
        $departures=$flight['departure_datetimes']??null;
        if(!is_array($departures)||count($departures)!==1||substr((string)$departures[0],0,10)!==$expectedDates[$i]) fsdf_fail('date_mismatch');
    }
    return [
        'operation'=>$operation,'probe'=>$probe,'target'=>$target,'rate'=>fsdf_rate($probe),
        'outbound_flight'=>$expectedFlights[0],'return_flight'=>$expectedFlights[1],
        'valid_from'=>$expectedDates[0],'valid_to'=>$expectedDates[1],
    ];
}
function fsdf_write(string $path,array $value): bool {
    $dir=dirname($path);
    if(!is_dir($dir)||is_link($dir)||is_link($path)) return false;
    $tmp=tempnam($dir,'.direction-fuel-seed.');
    if(!is_string($tmp)) return false;
    try{
        chmod($tmp,0600);
        $json=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if(file_put_contents($tmp,$json,LOCK_EX)===false) return false;
        if(!rename($tmp,$path)) return false;
        chmod($path,0600);
        return true;
    } finally {
        if(file_exists($tmp)) @unlink($tmp);
    }
}

$sourceRoot=getenv('INT_DIRECTION_SEED_SOURCE_ROOT');
$storeDir=getenv('INT_DIRECTION_SEED_STORE_DIR');
$opsRoot=getenv('INT_DIRECTION_SEED_OPS_ROOT');
if(!is_string($sourceRoot)||!is_dir($sourceRoot)||!is_string($storeDir)||!is_dir($storeDir)
    ||!is_string($opsRoot)||!is_dir($opsRoot)) fsdf_fail('seed_environment');
require_once rtrim($sourceRoot,'/').'/app/integrations/operator-fuel-rule-evidence.php';
require_once rtrim($sourceRoot,'/').'/app/integrations/operator-fuel-rule-store.php';

$expected=[
    ['int-andromeda-funsun-antalya-fuel-probe-20260923-v1',0],
    ['int-andromeda-funsun-antalya-fuel-probe-20260923-v2',1],
];
$samples=[];
foreach($expected as [$operation,$index]){
    $path=rtrim($opsRoot,'/').'/'.$operation.'/result.json';
    $outer=fsdf_json($path);
    $sample=fsdf_probe($outer,$operation,$index);
    $mtime=filemtime($path);
    if(!is_int($mtime)||$mtime<1||$mtime>time()+60) fsdf_fail('receipt_time');
    $sample['observed_at']=$mtime;
    $samples[]=$sample;
}
if($samples[0]['target']['spo_key']===$samples[1]['target']['spo_key']
    ||$samples[0]['target']['selected_offer_ref_sha256']===$samples[1]['target']['selected_offer_ref_sha256']) fsdf_fail('independent_evidence');

$direction=['market'=>'departure:1','destination'=>'country:4'];
$receipts=[];
foreach($samples as $sample){
    $observed=$sample['observed_at'];
    $sourceResponse=AnyTourOperatorFuelRuleEvidenceV1::hash([
        'operation'=>$sample['operation'],
        'program_fuel_probe'=>$sample['probe'],
    ]);
    $exchange=[
        'from'=>'EUR','to'=>'RUB','rate'=>$sample['rate'],'source'=>'andromeda_claim_money',
        'observed_at'=>$observed,'expires_at'=>$observed+86400,
        'evidence_sha256'=>AnyTourOperatorFuelRuleEvidenceV1::hash([
            'operation'=>$sample['operation'],'source_response_sha256'=>$sourceResponse,
            'from'=>'EUR','to'=>'RUB','rate'=>$sample['rate'],
        ]),
    ];
    $raw=[
        'provider'=>'andromeda','operator'=>'Fun&Sun',
        'direction'=>$direction,
        'scope'=>[
            'market'=>'andromeda:departure:1',
            'outbound'=>['origin'=>'departure:1','destination'=>'country:4','carrier'=>'not_exposed_by_probe','flight'=>$sample['outbound_flight']],
            'return'=>['origin'=>'country:4','destination'=>'departure:1','carrier'=>'not_exposed_by_probe','flight'=>$sample['return_flight']],
            'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
        ],
        'unit'=>'per_person_one_way','base_relation'=>'excluded','amount'=>'140.00','currency'=>'EUR',
        'observed_at'=>$observed,'expires_at'=>$observed+2592000,
        'base_includes_other_required_charges'=>true,
        'valid_from'=>$sample['valid_from'],'valid_to'=>$sample['valid_to'],
        'offer_ref_digest'=>$sample['target']['selected_offer_ref_sha256'],
        'source_response_sha256'=>$sourceResponse,
        'source'=>'andromeda_claim_service',
        'exchange'=>$exchange,
    ];
    $raw['evidence_sha256']=AnyTourOperatorFuelRuleEvidenceV1::hash([
        'operation'=>$sample['operation'],'source_response_sha256'=>$sourceResponse,
        'derivation'=>'two_distinct_spo_required_fuel_equals_cheapest_party2_markup',
        'amount'=>'140.00','currency'=>'EUR','unit'=>'per_person_one_way',
        'direction'=>$direction,'flight_pair'=>[$sample['outbound_flight'],$sample['return_flight']],
        'exchange_rate'=>$sample['rate'],
    ]);
    $receipts[]=AnyTourOperatorFuelRuleStoreV1::append($storeDir,$raw,'fsdf_write');
}
$now=time();
$input=AnyTourOperatorFuelRuleStoreV1::inputForTarget($storeDir,[
    'operator'=>'FUN&SUN','search_params'=>['departureId'=>1,'countryId'=>4],
    'party'=>['adults'=>2,'children'=>0,'child_ages'=>[]],
    'offer_ref_digest'=>$samples[0]['target']['selected_offer_ref_sha256'],
],$now);
if(!is_array($input)||($input['direction']??null)!==[
        'operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:4'
    ]||count($input['observations']??[])<2||($input['exchange']['rate']??null)!=='102.7') fsdf_fail('store_readback');
$facts=[];
foreach($input['observations'] as $row){
    $facts[]=($row['amount']??null).'|'.($row['currency']??null).'|'.($row['unit']??null).'|'.($row['base_relation']??null);
}
if(array_values(array_unique($facts))!==['140.00|EUR|per_person_one_way|excluded']) fsdf_fail('confirmed_rule_mismatch');
$paths=array_values(array_unique(array_map(static fn(array $r):string=>basename((string)($r['path']??'')),$receipts)));
if(count($paths)!==1||preg_match('/\Aoperator-fuel-rule-v2-[a-f0-9]{64}\.json\z/D',$paths[0])!==1) fsdf_fail('store_path');
echo json_encode([
    'schema_version'=>1,'source'=>'int-funsun-direction-fuel-seed-v1','status'=>'complete',
    'direction'=>$input['direction'],'amount'=>'140.00','currency'=>'EUR','unit'=>'per_person_one_way',
    'base_relation'=>'excluded','independent_offer_count'=>2,'evidence_count'=>2,
    'exchange'=>array_intersect_key($input['exchange'],array_flip(['from','to','rate','observed_at','expires_at','evidence_sha256'])),
    'store_file'=>$paths[0],
    'append_statuses'=>array_map(static fn(array $r):string=>(string)($r['status']??''),$receipts),
    'supplier_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0,
    'final_price_verified'=>false,
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";

'''

def need(condition: bool, reason: str) -> None:
    if not condition:
        raise ValueError(reason)

def integer(value: str, lo: int, hi: int, reason: str) -> int:
    need(re.fullmatch(r'(?:0|[1-9][0-9]{0,9})', value) is not None, reason)
    n = int(value)
    need(lo <= n <= hi, reason)
    return n

def date(value: str) -> str:
    import datetime as dt
    need(re.fullmatch(r'\d{4}-\d{2}-\d{2}', value) is not None, 'date')
    try:
        dt.date.fromisoformat(value)
    except ValueError as exc:
        raise ValueError('date') from exc
    return value

def parse_command(body: str) -> dict:
    need(body.startswith(PREFIX), 'command_prefix')
    parts = body[len(PREFIX):].split()
    need(len(parts) >= 3, 'command_shape')
    source, mode, operation = parts[:3]
    need(SHA_RE.fullmatch(source) is not None, 'source_sha')
    need(OP_RE.fullmatch(operation) is not None, 'operation_id')
    if mode == 'install-runtime':
        need(len(parts) == 3, 'command_shape')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'program-fuel-readback':
        # Supplier-free exact DB/retained-cohort acceptance through the permanent SSH lane.
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'program_fuel_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'funsun-direction-fuel-seed':
        # Supplier-free one-shot persistence from the two already terminal FUN&SUN 114/78 probes.
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-funsun-antalya-direction-fuel-seed-'),
             'direction_fuel_seed_operation')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'program-fuel-probe':
        # One exact retained operator/program/tour + one distinct-SPO sample.
        need(len(parts) == 8, 'command_shape')
        target = parts[3]
        family = parts[4]
        need(OP_RE.fullmatch(target) is not None and target.startswith('int-andromeda-')
             and target != operation, 'target_operation_id')
        need(family in ('funsun','intourist'), 'program_fuel_operator_family')
        program_key = integer(parts[5], 1, 999999999, 'program_key')
        tour_key = integer(parts[6], 1, 999999999, 'tour_key')
        sample_index = integer(parts[7], 0, 1000, 'sample_index')
        need(operation.startswith('int-andromeda-'), 'program_fuel_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'target_operation_id': target, 'operator_family': family,
                'program_key': program_key, 'tour_key': tour_key,
                'sample_index': sample_index}
    if mode == 'anex-demand':
        need(len(parts) == 4, 'command_shape')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'limit': integer(parts[3], 1, 20, 'anex_limit')}
    if mode == 'reconcile':
        need(len(parts) == 4, 'command_shape')
        target = parts[3]
        need(OP_RE.fullmatch(target) is not None and target != operation, 'target_operation_id')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'target_operation_id': target}
    if mode == 'local-readback':
        need(len(parts) == 11, 'command_shape')
        departure = integer(parts[3], 1, 999999999, 'departure')
        country = integer(parts[4], 1, 999999999, 'country')
        date_from, date_to = date(parts[5]), date(parts[6])
        need(date_to >= date_from, 'date_range')
        nights = integer(parts[7], 1, 28, 'nights')
        adults = integer(parts[8], 1, 6, 'adults')
        meal = parts[9]
        need(re.fullmatch(r'(?:-|[A-Za-z0-9_,&]{1,32})', meal) is not None, 'meal')
        region = integer(parts[10], 0, 999999999, 'region')
        return {
            'source_sha': source, 'mode': mode, 'operation_id': operation,
            'departure': departure, 'country': country, 'date_from': date_from,
            'date_to': date_to, 'nights': nights, 'adults': adults,
            'meal': '' if meal == '-' else meal, 'region': region,
        }
    if mode == 'andromeda-external-group':
        # Owner-authorized one-group evidence probe. The caller cannot widen the
        # supplier budget or change the capture mode through the command text.
        need(len(parts) == 11, 'command_shape')
        departure = integer(parts[3], 1, 999999999, 'departure')
        country = integer(parts[4], 1, 999999999, 'country')
        date_from, date_to = date(parts[5]), date(parts[6])
        need(date_to >= date_from, 'date_range')
        nights = integer(parts[7], 1, 28, 'nights')
        adults = integer(parts[8], 1, 6, 'adults')
        meal = parts[9]
        need(re.fullmatch(r'(?:-|[A-Za-z0-9_,&]{1,32})', meal) is not None, 'meal')
        region = integer(parts[10], 0, 999999999, 'region')
        return {
            'source_sha': source, 'mode': mode, 'operation_id': operation,
            'departure': departure, 'country': country, 'date_from': date_from,
            'date_to': date_to, 'nights': nights, 'adults': adults,
            'meal': '' if meal == '-' else meal, 'region': region,
            'max_captures': 1,
        }
    if mode in ('match-tv942', 'match-samo942'):
        need(len(parts) == 5, 'command_shape')
        offset = integer(parts[3], 0, 941, 'match_offset')
        limit = integer(parts[4], 1, 350, 'match_limit')
        need(offset + limit <= 942, 'match_scope')
        if mode == 'match-tv942':
            need(operation.startswith('int-anex-'), 'match_operation_namespace')
        else:
            need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'offset': offset, 'limit': limit}
    if mode == 'match-secondary-audit':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'match-coverage':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'match-coverage-readback':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'match-tv942-reconcile':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-anex-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'match-tv942-write':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-anex-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'match-readback':
        need(len(parts) == 6, 'command_shape')
        lane = parts[3]
        need(lane in ('tv','samo'), 'match_lane')
        offset = integer(parts[4], 0, 941, 'match_offset')
        limit = integer(parts[5], 1, 350, 'match_limit')
        need(offset + limit <= 942, 'match_scope')
        if lane == 'tv':
            need(operation.startswith('int-anex-'), 'match_operation_namespace')
        else:
            need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'lane': lane, 'offset': offset, 'limit': limit}
    if mode == 'andromeda-operator-preflight':
        # Supplier-free proof that one Search3/Tourvisor operator ID resolves through
        # current local identity evidence into the saved Andromeda operator dictionary.
        need(len(parts) == 12, 'command_shape')
        departure = integer(parts[3], 1, 999999999, 'departure')
        country = integer(parts[4], 1, 999999999, 'country')
        date_from, date_to = date(parts[5]), date(parts[6])
        need(date_to >= date_from, 'date_range')
        nights = integer(parts[7], 1, 28, 'nights')
        adults = integer(parts[8], 1, 6, 'adults')
        meal = parts[9]
        need(re.fullmatch(r'(?:-|[A-Za-z0-9_,&]{1,32})', meal) is not None, 'meal')
        region = integer(parts[10], 0, 999999999, 'region')
        operator_id = integer(parts[11], 1, 999999999, 'operator_id')
        return {
            'source_sha': source, 'mode': mode, 'operation_id': operation,
            'departure': departure, 'country': country, 'date_from': date_from,
            'date_to': date_to, 'nights': nights, 'adults': adults,
            'meal': '' if meal == '-' else meal, 'region': region,
            'operator_id': operator_id,
        }
    if mode == 'andromeda-operator-scope':
        # One provider-neutral Search3/Tourvisor operator filter. This mode is
        # search+autosave only: no package/getFlights capture budget is accepted.
        need(len(parts) == 12, 'command_shape')
        departure = integer(parts[3], 1, 999999999, 'departure')
        country = integer(parts[4], 1, 999999999, 'country')
        date_from, date_to = date(parts[5]), date(parts[6])
        need(date_to >= date_from, 'date_range')
        nights = integer(parts[7], 1, 28, 'nights')
        adults = integer(parts[8], 1, 6, 'adults')
        meal = parts[9]
        need(re.fullmatch(r'(?:-|[A-Za-z0-9_,&]{1,32})', meal) is not None, 'meal')
        region = integer(parts[10], 0, 999999999, 'region')
        operator_id = integer(parts[11], 1, 999999999, 'operator_id')
        return {
            'source_sha': source, 'mode': mode, 'operation_id': operation,
            'departure': departure, 'country': country, 'date_from': date_from,
            'date_to': date_to, 'nights': nights, 'adults': adults,
            'meal': '' if meal == '-' else meal, 'region': region,
            'operator_id': operator_id, 'max_captures': 0,
        }
    if mode == 'andromeda-scope':
        need(len(parts) == 12, 'command_shape')
        departure = integer(parts[3], 1, 999999999, 'departure')
        country = integer(parts[4], 1, 999999999, 'country')
        date_from, date_to = date(parts[5]), date(parts[6])
        need(date_to >= date_from, 'date_range')
        nights = integer(parts[7], 1, 28, 'nights')
        adults = integer(parts[8], 1, 6, 'adults')
        meal = parts[9]
        need(re.fullmatch(r'(?:-|[A-Za-z0-9_,&]{1,32})', meal) is not None, 'meal')
        region = integer(parts[10], 0, 999999999, 'region')
        captures = integer(parts[11], 0, 30, 'captures')
        return {
            'source_sha': source, 'mode': mode, 'operation_id': operation,
            'departure': departure, 'country': country, 'date_from': date_from,
            'date_to': date_to, 'nights': nights, 'adults': adults,
            'meal': '' if meal == '-' else meal, 'region': region,
            'max_captures': captures,
        }
    raise ValueError('mode')

def api_get(path: str, token: str) -> dict:
    request = urllib.request.Request(
        'https://api.github.com/repos/' + REPO + path,
        headers={
            'Authorization': 'Bearer ' + token,
            'Accept': 'application/vnd.github+json',
            'X-GitHub-Api-Version': '2022-11-28',
        },
    )
    with urllib.request.urlopen(request, timeout=30) as response:
        return json.load(response)

def checked_event(token: str, event: dict, control_sha: str) -> dict:
    if event.get('comment'):
        body = event['comment'].get('body', '')
        need(event.get('issue', {}).get('number') == ISSUE
             and not event.get('issue', {}).get('pull_request'), 'issue')
        need(event.get('comment', {}).get('user', {}).get('id') == OWNER_ID, 'owner')
        need(event.get('comment', {}).get('author_association') == 'OWNER',
             'owner_association')
        fresh = api_get('/issues/comments/' + str(event['comment']['id']), token)
        need(fresh.get('body') == body and fresh.get('user', {}).get('id') == OWNER_ID,
             'comment_changed')
    else:
        body = os.environ.get('INT_OWNER_COMMAND', '')
        need(os.environ.get('GITHUB_ACTOR') == 'pyatkoff'
             and os.environ.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff',
             'dispatch_owner')
    command = parse_command(body)
    main = api_get('/git/ref/heads/main', token)['object']['sha']
    need(main == control_sha, 'main_changed')
    feature = api_get('/git/ref/heads/' + FEATURE, token)['object']['sha']
    need(feature == command['source_sha'], 'feature_changed')
    return command

def ensure_supplier_slot(token: str) -> None:
    own = int(os.environ.get('GITHUB_RUN_ID', '0') or '0')
    words = ('match','andromeda','anex','tourvisor','search3','seasonal live evidence')
    import datetime as dt
    for _ in range(36):
        busy = []
        now = dt.datetime.now(dt.timezone.utc)
        for status in ('in_progress','queued'):
            payload = api_get('/actions/runs?status=' + status + '&per_page=100', token)
            for run in payload.get('workflow_runs', []):
                if int(run.get('id', 0)) == own or run.get('name') == 'Security guard':
                    continue
                created = run.get('created_at')
                if not isinstance(created, str):
                    continue
                try:
                    stamp = dt.datetime.fromisoformat(created.replace('Z', '+00:00'))
                except ValueError:
                    continue
                if (now - stamp).total_seconds() > 21600:
                    continue
                name = str(run.get('name', '')).lower()
                if any(word in name for word in words):
                    busy.append((run.get('id'), run.get('name'), status))
        if not busy:
            return
        time.sleep(5)
    raise ValueError('supplier_slot_busy')

def bundle_source(source_root: Path) -> tuple[bytes, dict[str, str]]:
    source_root = source_root.resolve()
    app = source_root / 'app/integrations'
    need(app.is_dir() and not app.is_symlink(), 'source_app')
    paths = []
    for path in sorted(app.rglob('*.php')):
        need(path.is_file() and not path.is_symlink(), 'source_link')
        paths.append(path)
    for relative in FIXED:
        path = source_root / relative
        need(path.is_file() and not path.is_symlink(), 'source_missing:' + relative)
        paths.append(path)
    files = {}
    for path in paths:
        relative = path.relative_to(source_root).as_posix()
        need(path.resolve() == source_root / relative, 'source_escape')
        files[relative] = path
    need(len(files) >= 20, 'source_inventory_small')
    hashes = {}
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative in sorted(files):
            data = files[relative].read_bytes()
            need(0 < len(data) <= 2 * 1024 * 1024, 'source_size:' + relative)
            hashes[relative] = hashlib.sha256(data).hexdigest()
            info = tarfile.TarInfo(relative)
            info.size = len(data); info.mode = 0o600; info.mtime = 0
            info.uid = info.gid = 0; info.uname = info.gname = ''
            archive.addfile(info, io.BytesIO(data))
        manifest = json.dumps(
            {'schema_version': 1, 'files': hashes},
            sort_keys=True, separators=(',', ':')
        ).encode()
        info = tarfile.TarInfo('manifest.json')
        info.size = len(manifest); info.mode = 0o600; info.mtime = 0
        archive.addfile(info, io.BytesIO(manifest))
    return output.getvalue(), hashes

REMOTE = r"""
import base64,hashlib,json,os,pathlib,re,subprocess,sys,tarfile,time
home=pathlib.Path.home()
project=home/'www/anytoour.ru'
runtime=project/'_preview/search3-anex-candidate'
private=home/'.anytoour-int-executor'
payload=json.loads(sys.stdin.read())
operation=payload['operation_id']; mode=payload['mode']; source=payload['source_sha']
result={'schema_version':1,'operation_id':operation,'source_sha':source,'mode':mode,
        'status':'blocked','supplier_calls':'unknown','database_writes':'unknown',
        'booking_calls':0,'lead_calls':0}
def fail(reason): raise RuntimeError(reason)
def safe_file(path,max_size=4*1024*1024):
    return path.is_file() and not path.is_symlink() and path.stat().st_size<=max_size
def fingerprints():
    out={}
    for rel in ['index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php']:
        path=project/rel
        out[rel]=hashlib.sha256(path.read_bytes()).hexdigest() if safe_file(path) else None
    return out
def db_summary(provider):
    php=r'''declare(strict_types=1);error_reporting(0);ini_set('display_errors','0');
$root=getenv('HOME').'/www/anytoour.ru';
require_once is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
$db=v2_data_db();$p=$argv[1];
$q=function(string $sql)use($db,$p){$s=$db->prepare($sql);$s->execute([$p]);return $s->fetchColumn();};
$r=['schema_version'=>(int)$db->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn(),
'rows_total'=>(int)$q('SELECT COUNT(*) FROM anytour_offers WHERE provider=?'),
'active_rows'=>(int)$q('SELECT COUNT(*) FROM anytour_offers WHERE provider=? AND is_active=1'),
'current_ready_rows'=>(int)$q('SELECT COUNT(*) FROM anytour_offers WHERE provider=? AND is_active=1 AND final_price_ready=1 AND expires_at>UTC_TIMESTAMP()'),
'current_confirmation_rows'=>(int)$q('SELECT COUNT(*) FROM anytour_offers WHERE provider=? AND is_active=1 AND final_price_ready=0 AND expires_at>UTC_TIMESTAMP()'),
'current_ready_hotels'=>(int)$q('SELECT COUNT(DISTINCT anytour_hotel_id) FROM anytour_offers WHERE provider=? AND is_active=1 AND final_price_ready=1 AND expires_at>UTC_TIMESTAMP()'),
'completed_refreshes'=>(int)$q("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider=? AND status='completed'"),
'latest_completed_at'=>$q("SELECT MAX(completed_at) FROM anytour_offer_refreshes WHERE provider=? AND status='completed'")];
echo json_encode($r,JSON_THROW_ON_ERROR);'''
    run=subprocess.run(['php','-r',php,provider],cwd=project,capture_output=True,text=True,timeout=30)
    if run.returncode or run.stderr: fail('db_readback_failed')
    return json.loads(run.stdout)
def program_fuel_readback():
    encoded=payload.get('program_fuel_readback_php_b64')
    expected=payload.get('program_fuel_readback_php_sha256')
    if not isinstance(encoded,str) or not isinstance(expected,str) or not re.fullmatch(r'[a-f0-9]{64}',expected):
        fail('program_fuel_readback_source_missing')
    try:
        script=base64.b64decode(encoded,validate=True)
    except Exception:
        fail('program_fuel_readback_source_encoding')
    if not script or len(script)>1024*1024 or hashlib.sha256(script).hexdigest()!=expected:
        fail('program_fuel_readback_source_hash')

    marker=b'declare(strict_types=1);'
    if script.count(marker)!=1:
        fail('program_fuel_readback_source_contract')
    # The strict diagnostic intentionally throws on acceptance mismatch. Install a
    # safe uncaught-exception handler before its body so the permanent executor can
    # retain the already-sanitized partial counters instead of collapsing them to a
    # generic nonzero PHP exit. This mode is supplier-free and read-only.
    handler=b'''
$out=null;
set_exception_handler(function(Throwable $__pf_error) use (&$out): void {
    $__pf_reason=$__pf_error->getMessage();
    if(!is_string($__pf_reason)
        || preg_match('/\\A[A-Za-z0-9_.:-]{1,96}\\z/D',$__pf_reason)!==1) {
        $__pf_reason='readback_failed';
    }
    echo json_encode([
        'schema_version'=>1,
        'source'=>'int-program-fuel-readback-wrapper-v1',
        'diagnostic_status'=>'failed',
        'reason'=>$__pf_reason,
        'supplier_calls'=>0,
        'database_reads'=>1,
        'database_writes'=>0,
        'filesystem_writes'=>0,
        'partial'=>isset($out)&&is_array($out)?$out:null,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\\n";
    exit(0);
});
'''
    wrapped=script.replace(marker,marker+handler,1)
    run=subprocess.run(
        ['php','-d','display_errors=0','-d','log_errors=0'],
        input=wrapped,cwd=project,capture_output=True,timeout=90
    )
    if run.returncode!=0 or run.stderr.strip():
        fail('program_fuel_readback_failed')
    try:
        data=json.loads(run.stdout.decode().strip())
    except Exception:
        fail('program_fuel_readback_unparseable')
    if not isinstance(data,dict) or data.get('schema_version')!=1:
        fail('program_fuel_readback_contract')

    if data.get('source')=='int-program-fuel-readback-wrapper-v1':
        if (data.get('diagnostic_status')!='failed' or data.get('supplier_calls')!=0
                or data.get('database_reads')!=1 or data.get('database_writes')!=0
                or data.get('filesystem_writes')!=0
                or not isinstance(data.get('reason'),str)):
            fail('program_fuel_readback_wrapper_contract')
        partial=data.get('partial')
        if partial is not None and (not isinstance(partial,dict)
                or partial.get('source')!='int-program-fuel-cohort-v3-readback'):
            fail('program_fuel_readback_partial_contract')
        return {
            'acceptance_pass':False,
            'failure_reason':data['reason'],
            'partial':partial,
            'supplier_calls':0,'database_reads':1,'database_writes':0,'filesystem_writes':0,
        }

    if data.get('source')!='int-program-fuel-cohort-v3-readback':
        fail('program_fuel_readback_contract')
    if (data.get('supplier_calls')!=0 or data.get('database_reads')!=1
            or data.get('database_writes')!=0 or data.get('filesystem_writes')!=0):
        fail('program_fuel_readback_authority')
    target=data.get('target',{})
    stored=data.get('stored',{})
    passed=(target.get('ready_count')==12 and target.get('ready_valid_rule_count')==12
            and target.get('ready_non_target_count')==0 and target.get('bad_ready_boundary_count')==0
            and stored.get('ready_count')==12 and stored.get('verified_count')==0
            and stored.get('payload_hash_invalid_count')==0 and stored.get('retained_missing_count')==0)
    return {
        'acceptance_pass':passed,
        'failure_reason':None if passed else 'program_fuel_readback_acceptance',
        'data':data,
        'supplier_calls':0,'database_reads':1,'database_writes':0,'filesystem_writes':0,
    }
def program_fuel_probe():
    encoded=payload.get('program_fuel_probe_php_b64')
    expected=payload.get('program_fuel_probe_php_sha256')
    if not isinstance(encoded,str) or not isinstance(expected,str) or not re.fullmatch(r'[a-f0-9]{64}',expected):
        fail('program_fuel_probe_source_missing')
    try:
        script=base64.b64decode(encoded,validate=True)
    except Exception:
        fail('program_fuel_probe_source_encoding')
    if not script or len(script)>1024*1024 or hashlib.sha256(script).hexdigest()!=expected:
        fail('program_fuel_probe_source_hash')
    env=dict(os.environ)
    env.update({
        'INT_PROGRAM_PROBE_TARGET_OPERATION': str(payload['target_operation_id']),
        'INT_PROGRAM_PROBE_OPERATOR_FAMILY': str(payload['operator_family']),
        'INT_PROGRAM_PROBE_PROGRAM_KEY': str(payload['program_key']),
        'INT_PROGRAM_PROBE_TOUR_KEY': str(payload['tour_key']),
        'INT_PROGRAM_PROBE_SAMPLE_INDEX': str(payload['sample_index']),
    })
    run=subprocess.run(
        ['php','-d','display_errors=0','-d','log_errors=0'],
        input=script,cwd=project,env=env,capture_output=True,timeout=120
    )
    if run.returncode!=0 or run.stderr.strip():
        fail('program_fuel_probe_failed')
    try:
        data=json.loads(run.stdout.decode().strip())
    except Exception:
        fail('program_fuel_probe_unparseable')
    if not isinstance(data,dict) or data.get('schema_version')!=1 or data.get('source')!='int-andromeda-program-getflights-probe-v1':
        fail('program_fuel_probe_contract')
    calls=data.get('supplier_calls',{})
    if (not isinstance(calls,dict) or calls.get('changeservice')!=0
            or calls.get('calc')!=0 or calls.get('booking')!=0
            or data.get('database_reads')!=0 or data.get('database_writes')!=0
            or data.get('mapping_writes')!=0 or data.get('final_price_verified') is not False):
        fail('program_fuel_probe_authority')
    target=data.get('target',{})
    if data.get('status')=='complete':
        if (calls.get('login_attempted') is not True or calls.get('package')!=1
                or calls.get('get_flights')!=1
                or target.get('target_operation')!=payload['target_operation_id']
                or target.get('operator_family')!=payload['operator_family']
                or target.get('program_key')!=str(payload['program_key'])
                or target.get('tour_key')!=str(payload['tour_key'])
                or target.get('sample_distinct_spo_index')!=payload['sample_index']
                or not isinstance(target.get('spo_key'),str)
                or not isinstance(target.get('selected_offer_ref_sha256'),str)
                or not re.fullmatch(r'[a-f0-9]{64}',target['selected_offer_ref_sha256'])):
            fail('program_fuel_probe_acceptance')
    elif data.get('status')=='supplier_rejected':
        facts=data.get('supplier_error_facts')
        if not isinstance(facts,dict) or facts.get('source')!='andromeda_claim_error':
            fail('program_fuel_probe_supplier_rejection')
    elif data.get('status') not in ('blocked_before_supplier','unknown_no_replay'):
        fail('program_fuel_probe_status')
    return data
def funsun_direction_fuel_seed():
    encoded=payload.get('funsun_direction_fuel_seed_php_b64')
    expected=payload.get('funsun_direction_fuel_seed_php_sha256')
    if not isinstance(encoded,str) or not isinstance(expected,str) or not re.fullmatch(r'[a-f0-9]{64}',expected):
        fail('direction_fuel_seed_source_missing')
    try:
        script=base64.b64decode(encoded,validate=True)
    except Exception:
        fail('direction_fuel_seed_source_encoding')
    if not script or len(script)>256*1024 or hashlib.sha256(script).hexdigest()!=expected:
        fail('direction_fuel_seed_source_hash')
    config=project/'_preview/search3-anex-candidate/.andromeda-private.php'
    if not safe_file(config,65536): fail('andromeda_private_config_missing')
    q=subprocess.run(
        ['php','-r',"$c=require $argv[1];$p=$c['catalog_path']??null;if(!is_string($p)||$p==='')exit(2);echo dirname($p).'/searches';",str(config)],
        capture_output=True,text=True,timeout=20
    )
    if q.returncode or not q.stdout.strip(): fail('direction_fuel_seed_store_root')
    store=pathlib.Path(q.stdout.strip())
    if not store.is_dir() or store.is_symlink(): fail('direction_fuel_seed_store_root')
    env=dict(os.environ)
    env.update({
        'INT_DIRECTION_SEED_SOURCE_ROOT':str(stage),
        'INT_DIRECTION_SEED_STORE_DIR':str(store),
        'INT_DIRECTION_SEED_OPS_ROOT':str(private),
    })
    run=subprocess.run(
        ['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0'],
        input=script,cwd=stage,env=env,capture_output=True,timeout=90
    )
    if run.returncode!=0 or run.stderr.strip(): fail('direction_fuel_seed_failed')
    try:
        data=json.loads(run.stdout.decode().strip())
    except Exception:
        fail('direction_fuel_seed_unparseable')
    if (not isinstance(data,dict) or data.get('schema_version')!=1
            or data.get('source')!='int-funsun-direction-fuel-seed-v1'
            or data.get('status')!='complete'
            or data.get('direction')!={'operator_family':'fun_and_sun','market':'departure:1','destination':'country:4'}
            or data.get('amount')!='140.00' or data.get('currency')!='EUR'
            or data.get('unit')!='per_person_one_way' or data.get('base_relation')!='excluded'
            or data.get('independent_offer_count')!=2 or data.get('evidence_count')!=2
            or data.get('supplier_calls')!=0 or data.get('database_reads')!=0
            or data.get('database_writes')!=0 or data.get('mapping_writes')!=0
            or data.get('final_price_verified') is not False):
        fail('direction_fuel_seed_acceptance')
    exchange=data.get('exchange')
    if (not isinstance(exchange,dict) or exchange.get('from')!='EUR'
            or exchange.get('to')!='RUB' or exchange.get('rate')!='102.7'
            or not isinstance(exchange.get('evidence_sha256'),str)
            or not re.fullmatch(r'[a-f0-9]{64}',exchange['evidence_sha256'])):
        fail('direction_fuel_seed_exchange')
    return data
def safe_json(path,max_size=1024*1024):
    if not safe_file(path,max_size): fail('safe_json')
    value=json.loads(path.read_text())
    if not isinstance(value,dict): fail('safe_json')
    return value
def reconcile_target(target_name):
    target=private/target_name
    if not target.is_dir() or target.is_symlink(): fail('reconcile_target_missing')
    reservation=safe_json(target/'reservation.json',65536)
    prior=safe_json(target/'result.json',1024*1024)
    if reservation.get('operation_id')!=target_name or prior.get('operation_id')!=target_name: fail('reconcile_target_identity')
    start=reservation.get('reserved_at')
    if not isinstance(start,int) or start<1: fail('reconcile_target_time')
    end=int((target/'result.json').stat().st_mtime)+1
    out={'target_operation_id':target_name,'target_mode':reservation.get('mode'),
         'target_status':prior.get('status'),'reserved_at':start,'result_mtime':end-1,
         'target_source_sha':reservation.get('source_sha')}
    if target_name.startswith('int-andromeda-'):
        config=project/'_preview/search3-anex-candidate/.andromeda-private.php'
        if not safe_file(config,65536): fail('andromeda_private_config_missing')
        php="$c=require $argv[1];$p=$c['catalog_path']??null;if(!is_string($p)||$p==='')exit(2);echo dirname($p);"
        q=subprocess.run(['php','-r',php,str(config)],capture_output=True,text=True,timeout=20)
        if q.returncode or not q.stdout.strip(): fail('andromeda_catalog_root')
        base=pathlib.Path(q.stdout.strip())
        counter=base/'monthly-requests.json'
        if safe_file(counter,65536):
            c=safe_json(counter,65536)
            mtime=int(counter.stat().st_mtime)
            out['andromeda_monthly_counter']={
                'month':c.get('month'),'reserved_requests':c.get('reserved_requests'),
                'monthly_limit':c.get('monthly_limit'),'mtime':mtime,
                'mtime_in_target_window': start-2 <= mtime <= end+2}
        searches=base/'searches'
        observed=[]
        retained=[]
        allowed_keys={'status','state','error','error_code','search_id','searchId','request_id','requestId','page','pages','page_count','pageCount','count','total','total_count','totalCount','created_at','createdAt','updated_at','updatedAt','expires_at','expiresAt'}
        if searches.is_dir() and not searches.is_symlink():
            for p in searches.iterdir():
                try:
                    if p.is_file() and not p.is_symlink():
                        mt=int(p.stat().st_mtime)
                        if start-2 <= mt <= end+2:
                            observed.append(mt)
                            item={'name_sha256':hashlib.sha256(p.name.encode()).hexdigest(),'mtime':mt,'size':p.stat().st_size}
                            if safe_file(p,2*1024*1024):
                                try:
                                    raw=json.loads(p.read_text())
                                    if isinstance(raw,dict):
                                        item['fields']={k:raw.get(k) for k in sorted(allowed_keys) if k in raw and isinstance(raw.get(k),(str,int,float,bool,type(None)))}
                                        state=raw.get('state')
                                        if isinstance(state,dict):
                                            item['attempt_state']={k:state.get(k) for k in ('status','failure_class','attempt') if isinstance(state.get(k),(str,int,type(None)))}
                                        record=raw.get('record')
                                        if isinstance(record,dict):
                                            item['package_record']={k:record.get(k) for k in ('status','diagnostic_code') if isinstance(record.get(k),(str,type(None)))}
                                            facts=record.get('supplier_error_facts')
                                            if isinstance(facts,dict):
                                                item['supplier_error_facts']={k:facts.get(k) for k in ('shape','reason_category','code','code_field','error_sha256') if isinstance(facts.get(k),(str,type(None)))}
                                        actualization=raw.get('actualization')
                                        if isinstance(actualization,dict):
                                            item['actualization']={k:actualization.get(k) for k in ('state','failure_class','actions_used') if isinstance(actualization.get(k),(str,int,type(None)))}
                                        store=raw.get('store')
                                        snapshot=store.get('snapshot') if isinstance(store,dict) else None
                                        rejected=snapshot.get('rejected') if isinstance(snapshot,dict) else None
                                        if isinstance(rejected,list):
                                            classes={}
                                            valid=0
                                            for row in rejected[:2000]:
                                                if not isinstance(row,dict): continue
                                                reason=row.get('reason');missing=row.get('missing_field');ownership=row.get('ownership_class')
                                                if not isinstance(reason,str) or len(reason)>96: continue
                                                if missing is not None and (not isinstance(missing,str) or len(missing)>96): continue
                                                if ownership is not None and (not isinstance(ownership,str) or len(ownership)>96): continue
                                                key=reason+'|'+(missing or '-')+'|'+(ownership or '-')
                                                classes[key]=classes.get(key,0)+1;valid+=1
                                            item['rejection_summary']={'count':len(rejected),'classified':valid,'classes':classes}
                                        item['top_level_keys']=sorted(str(k) for k in raw.keys())[:80]
                                except Exception:
                                    item['json_status']='unparseable'
                            retained.append(item)
                except OSError: pass
        out['andromeda_search_files_in_target_window']={
            'count':len(observed),'first_mtime':min(observed) if observed else None,
            'last_mtime':max(observed) if observed else None,'retained':retained}
    return out
def local_read(scopes):
    rows=[]
    for scope in scopes[:20]:
        php=r'''declare(strict_types=1);error_reporting(0);ini_set('display_errors','0');
try{
$root=getenv('HOME').'/www/anytoour.ru';
$config=$root.'/config.php';
if(!is_file($config)||is_link($config))throw new RuntimeException('site_config_missing');
require_once $config;
$f=$root.'/_preview/search3-local-candidate/data/search3-local-results-read-v1.php';
if(!is_file($f)||is_link($f))throw new RuntimeException('local_reader_missing');
require_once $f;$p=json_decode($argv[1],true,32,JSON_THROW_ON_ERROR);
$r=search3_local_results_build(v2_data_db(),$p,new DateTimeImmutable('now',new DateTimeZone('UTC')));
echo json_encode(['scopeDigest'=>$r['scopeDigest'],'hotelCount'=>$r['hotelCount'],
'offerCount'=>$r['offerCount'],'storedOfferCount'=>$r['storedOfferCount'],
'providerOfferCounts'=>(array)$r['providerOfferCounts'],
'withheldOfferCount'=>$r['withheldOfferCount'],'matchMode'=>$r['matchMode']],JSON_THROW_ON_ERROR);
}catch(Throwable $e){$m=$e->getMessage();$code=match(true){
$m==='local_reader_missing'=>'LOCAL_READER_MISSING',
$m==='site_config_missing'=>'LOCAL_SITE_CONFIG_MISSING',
$m==='AnyTour data database is not configured'=>'LOCAL_DB_NOT_CONFIGURED',
$m==='Dedicated MySQL connection required'=>'LOCAL_DB_CONNECTION',
$m==='Unsupported AnyTour offer-store schema'=>'LOCAL_SCHEMA',
$m==='Offer-store scope mismatch'=>'LOCAL_SCOPE_MISMATCH',
$m==='Stay mapping batch mismatch'=>'LOCAL_STAY_MAPPING',
str_contains($m,'undefined function v2_data_db')=>'LOCAL_DB_FUNCTION_MISSING',
default=>'LOCAL_UNCLASSIFIED'};
echo json_encode(['readbackError'=>$code,'errorClass'=>get_class($e),
'errorSha256'=>hash('sha256',$m)],JSON_THROW_ON_ERROR);}'''
        params={'departureId':str(scope['departureId']),'countryId':str(scope['countryId']),
          'dateFrom':scope['dateFrom'],'dateTo':scope['dateTo'],
          'nightsFrom':scope['nights'],'nightsTo':scope['nights'],
          'adults':scope['adults'],'childs':scope.get('childAges',[]),'meal':'',
          'hotelCategory':'','hotelRating':'','hotelTypes':[],'hotelIds':[],
          'hotelServices':[],'arrivalId':'',
          'regionIds':[] if scope.get('regionId') is None else [str(scope['regionId'])],
          'subregionIds':[],'operatorIds':[] if scope.get('operatorId') is None else [str(scope['operatorId'])],
          'priceFrom':'','priceTo':'',
          'currency':'RUB','onlyCharter':False,'onlyDirect':False}
        run=subprocess.run(['php','-r',php,json.dumps(params,separators=(',',':'))],
                           cwd=project,capture_output=True,text=True,timeout=30)
        stderr=run.stderr.strip()
        meta={'stderr_nonempty':bool(stderr),
              'stderr_sha256':hashlib.sha256(stderr.encode()).hexdigest() if stderr else None}
        if run.returncode:
            rows.append({'status':'failed','reason':'local_readback_exit','exit':run.returncode,**meta})
            continue
        try: parsed=json.loads(run.stdout)
        except Exception:
            rows.append({'status':'failed','reason':'local_readback_json',**meta})
            continue
        if isinstance(parsed,dict) and isinstance(parsed.get('readbackError'),str):
            rows.append({'status':'failed','reason':parsed['readbackError'],
                         'errorClass':parsed.get('errorClass'),'errorSha256':parsed.get('errorSha256'),**meta})
            continue
        required={'scopeDigest','hotelCount','offerCount','storedOfferCount','providerOfferCounts','withheldOfferCount','matchMode'}
        if not isinstance(parsed,dict) or not required.issubset(parsed):
            rows.append({'status':'failed','reason':'local_readback_shape',**meta})
            continue
        parsed['status']='complete';parsed.update(meta);rows.append(parsed)
    return rows

def operator_preflight(stage):
    api=stage/'v2/api-andromeda-search3-preview.php'
    config=project/'_preview/search3-anex-candidate/.andromeda-private.php'
    if not safe_file(api,2*1024*1024): fail('operator_preflight_source_missing')
    if not safe_file(config,65536): fail('andromeda_private_config_missing')
    request={
        'generation':2100000000-(int(hashlib.sha256(operation.encode()).hexdigest()[:6],16)%1000000),
        'page':1,
        'params':{
            'departureId':str(payload['departure']),'countryId':str(payload['country']),
            'dateFrom':payload['date_from'],'dateTo':payload['date_to'],
            'nightsFrom':payload['nights'],'nightsTo':payload['nights'],
            'adults':payload['adults'],'childs':[],'meal':payload['meal'],
            'hotelCategory':'','hotelRating':'','hotelTypes':[],'hotelIds':[],
            'hotelServices':[],'arrivalId':'',
            'regionIds':[] if not payload['region'] else [str(payload['region'])],
            'subregionIds':[],'operatorIds':[str(payload['operator_id'])],
            'priceFrom':'','priceTo':'','currency':'RUB',
            'onlyCharter':False,'onlyDirect':False,
        }
    }
    php=r'''declare(strict_types=1);error_reporting(0);ini_set('display_errors','0');ini_set('log_errors','0');
$allowed=['country_not_loaded','meal_not_supported','meal_dictionary_missing','meal_not_loaded',
'stars_not_supported','stars_dictionary_missing','stars_not_loaded','operator_dictionary_missing',
'operator_not_loaded','destination_dictionary_missing','destination_not_loaded','departure_not_loaded',
'no_operators','operator_not_supported','filter_not_supported'];
$name=null;$dictionaryId=null;
try{
$api=$argv[1];$configPath=$argv[2];$request=json_decode($argv[3],true,32,JSON_THROW_ON_ERROR);
if(!is_file($api)||is_link($api)||!is_file($configPath)||is_link($configPath))throw new RuntimeException('preflight_runtime_missing');
require_once $api;$config=require $configPath;
if(!is_array($config)||($config['enabled']??null)!==true)throw new RuntimeException('preflight_runtime_missing');
$root=getenv('HOME').'/www/anytoour.ru';require_once $root.'/config.php';
$dbPath=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
require_once $dbPath;$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$saved=anytour_andromeda_search3_catalog($config,$request);$saved['excluded_operator_ids']=$config['excluded_operator_ids']??[];
try{$q=$pdo->prepare("SELECT operator_name FROM tour_operator_identity_observations WHERE operator_id=? AND operator_name IS NOT NULL AND operator_name<>'' ORDER BY last_seen_at DESC,id DESC LIMIT 1");$q->execute([(string)$request['params']['operatorIds'][0]]);$v=$q->fetchColumn();if(is_string($v)&&$v!=='')$name=$v;}catch(Throwable $ignored){}
if($name!==null){
    try{$dictionaryId=anytour_andromeda_search3_dictionary_id($saved['all']['payload']['OPERATORS']??[],anytour_andromeda_search3_operator_aliases($name),'operator_not_loaded');}
    catch(Throwable $ignored){}
}
$params=anytour_andromeda_search3_params($request,$pdo,$saved);
echo json_encode(['status'=>'resolved','operator_id'=>(string)$request['params']['operatorIds'][0],
'operator_name'=>$name,'dictionary_operator_id'=>$dictionaryId,'andromeda_operators'=>$params['OPERATORS']??null,
'supplier_calls'=>0,'database_writes'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}catch(Throwable $e){$m=$e->getMessage();$code=in_array($m,$allowed,true)?$m:'preflight_unclassified';
echo json_encode(['status'=>'failed','error_code'=>$code,'error_class'=>get_class($e),
'operator_name'=>$name,'dictionary_operator_id'=>$dictionaryId,
'error_sha256'=>hash('sha256',$m),'supplier_calls'=>0,'database_writes'=>0],
JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}'''
    run=subprocess.run(['php','-r',php,str(api),str(config),json.dumps(request,separators=(',',':'))],
                       cwd=project,capture_output=True,text=True,timeout=30)
    if run.returncode or run.stderr.strip(): fail('operator_preflight_process')
    try: parsed=json.loads(run.stdout)
    except Exception: fail('operator_preflight_json')
    if not isinstance(parsed,dict) or parsed.get('status') not in ('resolved','failed'):
        fail('operator_preflight_shape')
    if parsed.get('supplier_calls')!=0 or parsed.get('database_writes')!=0:
        fail('operator_preflight_authority')
    return parsed

install_started=False
install_previous={}
install_applied=[]
install_expected={}
install_temps={}

def write_private_json(path,value):
    encoded=json.dumps(value,sort_keys=True,separators=(',',':')).encode()
    tmp=path.with_name('.'+path.name+'.'+operation+'.tmp')
    if tmp.exists() or tmp.is_symlink(): fail('install_private_temp_exists')
    fd=os.open(tmp,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    try:
        os.write(fd,encoded);os.fsync(fd)
    finally:
        os.close(fd)
    os.replace(tmp,path);os.chmod(path,0o600)

def stage_target_bytes(target,data,mode):
    parent=target.parent
    if not parent.is_dir() or parent.is_symlink() or parent.resolve()!=parent:
        fail('install_target_parent')
    tmp=parent/('.'+target.name+'.'+operation+'.tmp')
    if tmp.exists() or tmp.is_symlink(): fail('install_target_temp_exists')
    fd=os.open(tmp,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    try:
        os.write(fd,data);os.fsync(fd)
    finally:
        os.close(fd)
    if hashlib.sha256(tmp.read_bytes()).hexdigest()!=hashlib.sha256(data).hexdigest():
        fail('install_target_temp_hash')
    os.chmod(tmp,mode)
    return tmp

def rollback_install(op):
    restored=[]
    for relative in reversed(install_applied):
        target=runtime/relative
        prior=install_previous[relative]
        if prior['exists']:
            backup=op/'backup'/relative
            if not safe_file(backup,2*1024*1024): fail('rollback_backup_missing')
            temp=stage_target_bytes(target,backup.read_bytes(),prior['mode'])
            os.replace(temp,target)
        else:
            if target.is_symlink() or (target.exists() and not target.is_file()):
                fail('rollback_target_invalid')
            target.unlink(missing_ok=True)
        restored.append(relative)
    for relative,prior in install_previous.items():
        target=runtime/relative
        if prior['exists']:
            if (not safe_file(target,2*1024*1024)
                    or hashlib.sha256(target.read_bytes()).hexdigest()!=prior['sha256']):
                fail('rollback_hash')
        elif target.exists() or target.is_symlink():
            fail('rollback_absent')
    for temp in install_temps.values():
        try: temp.unlink(missing_ok=True)
        except OSError: pass
    return restored

def install_runtime(stage,files,op):
    global install_started
    selected=sorted(
        relative for relative in files
        if relative.startswith('app/integrations/')
    )
    if len(selected)<20 or not any(x=='app/integrations/three-provider-fuel-evidence.php' for x in selected):
        fail('install_inventory')
    backup_root=op/'backup';backup_root.mkdir(mode=0o700)
    changed=[]
    for relative in selected:
        if (not re.fullmatch(r'[A-Za-z0-9._/-]{1,240}',relative)
                or relative.startswith('/') or '..' in pathlib.PurePosixPath(relative).parts):
            fail('install_relative')
        source_path=stage/relative
        expected=files.get(relative)
        if (not safe_file(source_path,2*1024*1024)
                or not isinstance(expected,str)
                or hashlib.sha256(source_path.read_bytes()).hexdigest()!=expected):
            fail('install_source_hash')
        lint=subprocess.run(['php','-l',str(source_path)],capture_output=True,text=True,timeout=20)
        if lint.returncode!=0: fail('install_source_lint')
        target=runtime/relative
        if not target.parent.is_dir() or target.parent.is_symlink() or target.parent.resolve()!=target.parent:
            fail('install_target_parent')
        prior={'exists':False,'sha256':None,'mode':0o644}
        if target.exists() or target.is_symlink():
            if not safe_file(target,2*1024*1024): fail('install_target_invalid')
            data=target.read_bytes()
            prior={'exists':True,'sha256':hashlib.sha256(data).hexdigest(),
                   'mode':target.stat().st_mode&0o777}
            backup=backup_root/relative;backup.parent.mkdir(parents=True,exist_ok=True)
            backup.write_bytes(data);os.chmod(backup,0o600)
            if hashlib.sha256(backup.read_bytes()).hexdigest()!=prior['sha256']:
                fail('install_backup_hash')
        install_previous[relative]=prior
        install_expected[relative]=expected
        if prior['sha256']!=expected:
            changed.append(relative)
            install_temps[relative]=stage_target_bytes(target,source_path.read_bytes(),prior['mode'])
    plan={'schema_version':1,'source_sha':source,'files':selected,'changed_files':changed,
          'previous':install_previous,'expected':install_expected,'status':'prepared'}
    write_private_json(op/'install-plan.json',plan)
    install_started=True
    for relative in changed:
        target=runtime/relative
        os.replace(install_temps[relative],target)
        install_applied.append(relative)
        os.chmod(target,install_previous[relative]['mode'])
        write_private_json(op/'install-state.json',
            {'status':'applying','source_sha':source,'applied':install_applied})
    for relative,expected in install_expected.items():
        target=runtime/relative
        if (not safe_file(target,2*1024*1024)
                or hashlib.sha256(target.read_bytes()).hexdigest()!=expected):
            fail('install_readback_hash')
        lint=subprocess.run(['php','-l',str(target)],capture_output=True,text=True,timeout=20)
        if lint.returncode!=0: fail('install_readback_lint')
    complete={'status':'installed','source_sha':source,'files':len(selected),
              'changed_files':len(changed),'created_files':sum(
                  1 for relative in changed if not install_previous[relative]['exists']),
              'manifest_sha256':payload['manifest_sha256']}
    write_private_json(op/'install-state.json',complete)
    return complete
def match942_child_name(lane, offset, limit):
    if lane not in ('tv','samo') or offset<0 or limit<1 or offset+limit>942: fail('match_child_scope')
    kind='tv-anex' if lane=='tv' else 'samo-anex'
    return 'hotel-match-live942-'+kind+'-refresh-1971-20260923-o'+str(offset)+'-n'+str(limit)+'-v2'
def read_match942(lane, offset, limit):
    child=match942_child_name(lane,offset,limit)
    child_dir=home/'.anytoour-match/operations'/child
    if not child_dir.is_dir() or child_dir.is_symlink(): fail('match_child_missing')
    reservation=safe_json(child_dir/'reservation.json',65536) if safe_file(child_dir/'reservation.json',65536) else {}
    result_path=child_dir/'result.json'; receipt_path=child_dir/'receipt.json'
    attempt_patterns=('tv-request-*.json','tv-response-*.json','tv-batch-*-reservation.json',
                      'tv-batch-*-result.json','samo-http-*-reserved.json',
                      'samo-target-*-reserved.json','samo-target-*-result.json')
    counts={pattern:len(list(child_dir.glob(pattern))) for pattern in attempt_patterns}
    provider_attempt_files=counts['tv-request-*.json']+counts['samo-http-*-reserved.json']
    out={'child_operation':child,'reservation_present':bool(reservation),
         'reservation_source_sha':reservation.get('source_sha'),
         'reservation_parent_operation':reservation.get('parent_operation'),
         'scope_offset':reservation.get('offset'),'scope_count':reservation.get('limit'),
         'file_counts':counts,'provider_attempt_files':provider_attempt_files,
         'result_present':safe_file(result_path,8*1024*1024),
         'receipt_present':safe_file(receipt_path,1024*1024)}
    if out['result_present'] and out['receipt_present']:
        child_result=safe_json(result_path,8*1024*1024); receipt=safe_json(receipt_path,1024*1024)
        digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
        if receipt.get('result_sha256')!=digest: fail('match_readback_hash')
        summary={k:v for k,v in child_result.items() if k not in ('rows','edges','batches')}
        out.update(state='terminal_receipt',result_sha256=digest,summary=summary,
                   receipt_state=receipt.get('state'),receipt_no_replay=receipt.get('no_replay'))
    elif provider_attempt_files>0:
        out['state']='provider_attempted_without_terminal'
    else:
        out['state']='pre_provider_reservation_only'
    return out
def read_match_coverage():
    child='hotel-match-current-coverage-1971-20260923-v1'
    child_dir=home/'.anytoour-match/operations'/child
    if not child_dir.is_dir() or child_dir.is_symlink(): fail('match_coverage_child_missing')
    result_path=child_dir/'result.json';receipt_path=child_dir/'receipt.json'
    if not safe_file(result_path,128*1024*1024) or not safe_file(receipt_path,1024*1024):
        fail('match_coverage_readback_missing_or_oversized')
    child_result=safe_json(result_path,128*1024*1024);receipt=safe_json(receipt_path,1024*1024)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if receipt.get('result_sha256')!=digest: fail('match_coverage_readback_hash')
    for key in ('provider_http_calls','tourvisor_calls','samo_calls','anex_calls','database_writes','mapping_writes'):
        if child_result.get(key)!=0: fail('match_coverage_readback_nonzero_'+key)
    if child_result.get('state')!='completed_read_only_coverage': fail('match_coverage_readback_state')
    live30=child_result.get('live_30d') if isinstance(child_result.get('live_30d'),dict) else {}
    active=child_result.get('active_tv') if isinstance(child_result.get('active_tv'),dict) else {}
    return {
        'child_operation':child,'state':child_result.get('state'),
        'result_sha256':digest,'result_bytes':result_path.stat().st_size,
        'receipt_no_replay':receipt.get('no_replay'),
        'summary':{
            'generated_at_utc':child_result.get('generated_at_utc'),
            'edge_counts':child_result.get('edge_counts'),
            'active_counts':active.get('counts'),
            'live30_counts':live30.get('counts'),
            'live30_top_missing_geographies':live30.get('top_missing_geographies'),
        },
    }

def run_match_coverage(stage):
    child='hotel-match-current-coverage-1971-20260923-v1'
    match_root=home/'.anytoour-match/operations'
    match_root.mkdir(mode=0o700,parents=True,exist_ok=True)
    child_dir=match_root/child
    if child_dir.exists() or child_dir.is_symlink(): fail('match_coverage_child_exists_no_replay')
    child_dir.mkdir(mode=0o700)
    reservation={'operation':child,'state':'reserved_before_db_read','source_sha':source,
                 'parent_operation':operation,'provider_http_calls':0,'database_writes':0,'mapping_writes':0}
    (child_dir/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
    os.chmod(child_dir/'reservation.json',0o600)
    runner=stage/'scripts/diagnostics/hotel_match_current_coverage_wrapper_v1.php'
    base_diag=stage/'scripts/diagnostics/hotel_match_tv_samo_anex_coverage_v1.php'
    helper=stage/'scripts/diagnostics/hotel_match_anex_effective_coverage.php'
    if not safe_file(runner) or not safe_file(base_diag) or not safe_file(helper):
        fail('match_coverage_source_missing')
    env={**os.environ,'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child_dir),
         'MATCH_SOURCE_SHA':source,'MATCH_CHILD_OPERATION':child}
    call=subprocess.run(['php',str(runner),'--execute'],cwd=project,env=env,
                        capture_output=True,text=True,timeout=240)
    result_path=child_dir/'result.json';receipt_path=child_dir/'receipt.json'
    if not safe_file(result_path,32*1024*1024) or not safe_file(receipt_path,1024*1024):
        fail('match_coverage_terminal_missing')
    child_result=safe_json(result_path,32*1024*1024);receipt=safe_json(receipt_path,1024*1024)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if receipt.get('result_sha256')!=digest: fail('match_coverage_terminal_hash')
    for key in ('provider_http_calls','tourvisor_calls','samo_calls','anex_calls','database_writes','mapping_writes'):
        if child_result.get(key)!=0: fail('match_coverage_nonzero_'+key)
    if call.returncode!=0 or child_result.get('state')!='completed_read_only_coverage':
        fail('match_coverage_terminal_guard')
    live30=child_result.get('live_30d') if isinstance(child_result.get('live_30d'),dict) else {}
    active=child_result.get('active_tv') if isinstance(child_result.get('active_tv'),dict) else {}
    summary={
        'generated_at_utc':child_result.get('generated_at_utc'),
        'edge_counts':child_result.get('edge_counts'),
        'active_counts':active.get('counts'),
        'live30_counts':live30.get('counts'),
        'live30_top_missing_geographies':live30.get('top_missing_geographies'),
    }
    return {'child_operation':child,'state':child_result.get('state'),'result_sha256':digest,
            'summary':summary,'stdout_sha256':hashlib.sha256(call.stdout.encode()).hexdigest(),
            'stderr_sha256':hashlib.sha256(call.stderr.encode()).hexdigest() if call.stderr else None}

def run_match_secondary_audit(stage):
    child='hotel-match-live-anex-samo-missing-secondary-audit-1971-20260923-v1'
    match_root=home/'.anytoour-match/operations'
    match_root.mkdir(mode=0o700,parents=True,exist_ok=True)
    child_dir=match_root/child
    if child_dir.exists() or child_dir.is_symlink(): fail('match_secondary_child_exists_no_replay')
    child_dir.mkdir(mode=0o700)
    reservation={'operation':child,'state':'reserved_before_db_read','source_sha':source,
                 'parent_operation':operation,'provider_http_calls':0,'database_writes':0,'mapping_writes':0}
    (child_dir/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
    os.chmod(child_dir/'reservation.json',0o600)
    runner=stage/'scripts/diagnostics/hotel_match_live_anex_samo_missing_secondary_audit_v1.php'
    helper=stage/'scripts/diagnostics/hotel_match_anex_effective_coverage.php'
    if not safe_file(runner) or not safe_file(helper): fail('match_secondary_source_missing')
    env={**os.environ,'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child_dir),'MATCH_SOURCE_SHA':source}
    call=subprocess.run(['php',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    result_path=child_dir/'result.json';receipt_path=child_dir/'receipt.json'
    if not safe_file(result_path,16*1024*1024) or not safe_file(receipt_path,1024*1024):
        fail('match_secondary_terminal_missing')
    child_result=safe_json(result_path,16*1024*1024);receipt=safe_json(receipt_path,1024*1024)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if receipt.get('result_sha256')!=digest: fail('match_secondary_terminal_hash')
    if (call.returncode!=0 or child_result.get('state')!='completed_read_only_secondary_audit'
            or child_result.get('provider_http_calls')!=0 or child_result.get('database_writes')!=0
            or child_result.get('mapping_writes')!=0):
        fail('match_secondary_terminal_guard')
    summary={k:v for k,v in child_result.items() if k!='rows'}
    return {'child_operation':child,'state':child_result.get('state'),'result_sha256':digest,
            'summary':summary,'stdout_sha256':hashlib.sha256(call.stdout.encode()).hexdigest(),
            'stderr_sha256':hashlib.sha256(call.stderr.encode()).hexdigest() if call.stderr else None}

def run_match_tv942_reconcile(stage):
    child='hotel-match-live942-tv-candidate-reconcile-1971-20260923-v1'
    match_root=home/'.anytoour-match/operations'
    match_root.mkdir(mode=0o700,parents=True,exist_ok=True)
    child_dir=match_root/child
    if child_dir.exists() or child_dir.is_symlink(): fail('match_tv_reconcile_child_exists_no_replay')
    child_dir.mkdir(mode=0o700)
    reservation={'operation':child,'state':'reserved_before_db_read','source_sha':source,
                 'parent_operation':operation,'supplier_calls':0,'database_writes':0,'mapping_writes':0}
    (child_dir/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
    os.chmod(child_dir/'reservation.json',0o600)
    runner=stage/'scripts/diagnostics/hotel_match_live942_tv_candidate_reconcile_v1.php'
    helper=stage/'scripts/diagnostics/hotel_match_anex_effective_coverage.php'
    if not safe_file(runner) or not safe_file(helper): fail('match_tv_reconcile_source_missing')
    env={**os.environ,'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child_dir),'MATCH_SOURCE_SHA':source}
    call=subprocess.run(['php',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    if call.returncode!=0: fail('match_tv_reconcile_nonzero')
    try: child_result=json.loads(call.stdout)
    except Exception: fail('match_tv_reconcile_unparseable')
    if (not isinstance(child_result,dict) or child_result.get('state')!='completed_read_only'
            or child_result.get('supplier_calls')!=0 or child_result.get('database_writes')!=0
            or child_result.get('mapping_writes')!=0):
        fail('match_tv_reconcile_guard')
    full_path=child_dir/'result.json'
    full_path.write_text(json.dumps(child_result,ensure_ascii=False,sort_keys=True,separators=(',',':')))
    os.chmod(full_path,0o600)
    digest=hashlib.sha256(full_path.read_bytes()).hexdigest()
    receipt={'operation':child,'state':'completed_read_only','result_sha256':digest,
             'supplier_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':True}
    (child_dir/'receipt.json').write_text(json.dumps(receipt,sort_keys=True,separators=(',',':')))
    os.chmod(child_dir/'receipt.json',0o600)
    summary={k:v for k,v in child_result.items() if k not in ('rows','child_summaries')}
    writer_rows=[r for r in child_result.get('rows',[]) if isinstance(r,dict) and r.get('state')=='writer_ready']
    return {'child_operation':child,'state':'completed_read_only','result_sha256':digest,
            'summary':summary,'writer_ready_rows':writer_rows,
            'stdout_sha256':hashlib.sha256(call.stdout.encode()).hexdigest(),
            'stderr_sha256':hashlib.sha256(call.stderr.encode()).hexdigest() if call.stderr else None}

def run_match_tv942_write(stage):
    child='hotel-match-live942-tv-writer-1971-20260923-v1'
    reconcile_child='hotel-match-live942-tv-candidate-reconcile-1971-20260923-v1'
    reconcile_sha='57245b9020beefb3760c3bf65696f3fc4a33485c8b4ec74f2058f40b363e1700'
    match_root=home/'.anytoour-match/operations'
    match_root.mkdir(mode=0o700,parents=True,exist_ok=True)
    child_dir=match_root/child
    if child_dir.exists() or child_dir.is_symlink(): fail('match_tv_writer_child_exists_no_replay')
    manifest_path=match_root/reconcile_child/'result.json'
    if not safe_file(manifest_path,16*1024*1024): fail('match_tv_writer_manifest_missing')
    if hashlib.sha256(manifest_path.read_bytes()).hexdigest()!=reconcile_sha:
        fail('match_tv_writer_manifest_hash')
    child_dir.mkdir(mode=0o700)
    reservation={'operation':child,'state':'reserved_before_write','source_sha':source,
                 'parent_operation':operation,'reconcile_result_sha256':reconcile_sha,
                 'supplier_calls':0,'database_writes_reserved':88,'mapping_writes_reserved':88}
    (child_dir/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
    os.chmod(child_dir/'reservation.json',0o600)
    runner=stage/'scripts/diagnostics/hotel_match_live942_tv_writer_v1.php'
    helper=stage/'scripts/diagnostics/hotel_match_anex_effective_coverage.php'
    if not safe_file(runner) or not safe_file(helper): fail('match_tv_writer_source_missing')
    env={**os.environ,'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child_dir),
         'MATCH_SOURCE_SHA':source,'MATCH_MANIFEST_PATH':str(manifest_path)}
    call=subprocess.run(['php',str(runner),'--execute'],cwd=project,env=env,
                        capture_output=True,text=True,timeout=240)
    result_path=child_dir/'result.json';receipt_path=child_dir/'receipt.json'
    if not safe_file(result_path,16*1024*1024) or not safe_file(receipt_path,1024*1024):
        fail('match_tv_writer_terminal_missing')
    child_result=safe_json(result_path,16*1024*1024);receipt=safe_json(receipt_path,1024*1024)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if receipt.get('result_sha256')!=digest: fail('match_tv_writer_terminal_hash')
    inserted=child_result.get('inserted');held=child_result.get('held_count')
    if (call.returncode!=0 or child_result.get('state')!='committed_verified'
            or not isinstance(inserted,int) or not isinstance(held,int) or inserted<0 or held<0
            or inserted+held!=88 or child_result.get('planned')!=88
            or child_result.get('readback_verified') is not True
            or child_result.get('database_writes')!=inserted
            or child_result.get('mapping_writes')!=inserted
            or child_result.get('provider_http_calls')!=0 or child_result.get('supplier_calls')!=0
            or receipt.get('database_writes')!=inserted or receipt.get('mapping_writes')!=inserted
            or receipt.get('readback_verified') is not True or receipt.get('no_replay') is not True):
        fail('match_tv_writer_terminal_guard')
    summary={k:v for k,v in child_result.items() if k not in ('rows',)}
    return {'child_operation':child,'state':'committed_verified','result_sha256':digest,
            'inserted':inserted,'held_count':held,'summary':summary,
            'stdout_sha256':hashlib.sha256(call.stdout.encode()).hexdigest(),
            'stderr_sha256':hashlib.sha256(call.stderr.encode()).hexdigest() if call.stderr else None}

def run_match942(stage, mode, offset, limit):
    match_root=home/'.anytoour-match/operations'
    match_root.mkdir(mode=0o700,parents=True,exist_ok=True)
    lane='tv' if mode=='match-tv942' else 'samo'
    child=match942_child_name(lane,offset,limit)
    child_dir=match_root/child
    if child_dir.exists() or child_dir.is_symlink(): fail('match_child_exists_no_replay')
    child_dir.mkdir(mode=0o700)
    reservation={'operation':child,'state':'reserved_before_db_and_provider','source_sha':source,
                 'parent_operation':operation,'frontier_expected':942,'offset':offset,'limit':limit,
                 'database_writes':0,'mapping_writes':0,'reserved_at':int(time.time())}
    (child_dir/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
    os.chmod(child_dir/'reservation.json',0o600)
    planner=stage/'scripts/diagnostics/hotel_match_live942_frontier_plan_v1.php'
    helper=stage/'scripts/diagnostics/hotel_match_anex_effective_coverage.php'
    if not safe_file(planner) or not safe_file(helper): fail('match_plan_source_missing')
    env={**os.environ,'ANYTOUR_ROOT':str(project)}
    planned=subprocess.run(['php',str(planner),'--execute'],cwd=project,env=env,
                           capture_output=True,text=True,timeout=90)
    if planned.returncode or planned.stderr.strip(): fail('match_plan_failed')
    try: plan=json.loads(planned.stdout)
    except Exception: fail('match_plan_unparseable')
    if (plan.get('state')!='original_live942_ready' or plan.get('frontier_count')!=942
            or plan.get('current_missing_count')!=927 or plan.get('control_written_count')!=15
            or not isinstance(plan.get('rows'),list) or len(plan['rows'])!=942):
        fail('match_plan_guard')
    plan_path=child_dir/'plan.json'
    plan_path.write_text(json.dumps(plan,ensure_ascii=False,separators=(',',':')))
    os.chmod(plan_path,0o600)
    run_env={**os.environ,'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child_dir),
             'MATCH_PLAN_PATH':str(plan_path),'MATCH_CHILD_OPERATION':child,
             'MATCH_OFFSET':str(offset),'MATCH_LIMIT':str(limit),'MATCH_SOURCE_SHA':source}
    if mode=='match-tv942':
        runner=stage/'scripts/diagnostics/hotel_match_live942_tv_anex_refresh_v1.py'
        command=['python3',str(runner),'--execute']
    else:
        runner=stage/'scripts/diagnostics/hotel_match_live942_samo_anex_refresh_v1.php'
        command=['php',str(runner),'--execute']
    if not safe_file(runner): fail('match_runner_missing')
    call=subprocess.run(command,cwd=project,env=run_env,capture_output=True,text=True,timeout=900)
    result_path=child_dir/'result.json';receipt_path=child_dir/'receipt.json'
    if not safe_file(result_path,8*1024*1024) or not safe_file(receipt_path,1024*1024):
        fail('match_terminal_receipt_missing')
    child_result=safe_json(result_path,8*1024*1024);receipt=safe_json(receipt_path,1024*1024)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if receipt.get('result_sha256')!=digest: fail('match_terminal_hash')
    if (child_result.get('frontier_count')!=942 or child_result.get('scope_offset')!=offset
            or child_result.get('scope_count')!=limit or child_result.get('database_writes')!=0
            or child_result.get('mapping_writes')!=0):
        fail('match_terminal_guard')
    allowed={'completed_read_only','terminal_quota_stop_no_replay','terminal_day_changed_no_replay'}
    if call.returncode!=0 or child_result.get('state') not in allowed:
        fail('match_terminal_nonzero_no_replay')
    summary={k:v for k,v in child_result.items() if k not in ('rows','edges','batches')}
    return {'child_operation':child,'scope_offset':offset,'scope_count':limit,
            'state':child_result.get('state'),'result_sha256':digest,'summary':summary,
            'provider_stdout_sha256':hashlib.sha256(call.stdout.encode()).hexdigest(),
            'provider_stderr_sha256':hashlib.sha256(call.stderr.encode()).hexdigest() if call.stderr else None}

try:
    if not re.fullmatch(r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*',operation):
        fail('operation_invalid')
    if not re.fullmatch(r'[a-f0-9]{40}',source): fail('source_invalid')
    if project.resolve()!=project or project.name!='anytoour.ru': fail('project_invalid')
    if runtime.resolve()!=runtime or not runtime.is_dir() or runtime.is_symlink(): fail('runtime_invalid')
    private.mkdir(mode=0o700,exist_ok=True)
    op=private/operation
    if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')
    op.mkdir(mode=0o700)
    reservation={'operation_id':operation,'source_sha':source,'mode':mode,'reserved_at':int(time.time())}
    (op/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
    os.chmod(op/'reservation.json',0o600)
    result['status']='reserved'
    before=fingerprints(); result['production_before']=before
    archive=pathlib.Path(payload['archive']); stage=op/'source'; stage.mkdir(mode=0o700)
    with tarfile.open(archive,'r:gz') as package:
        members=package.getmembers()
        for member in members:
            pure=pathlib.PurePosixPath(member.name)
            if (not member.isfile() or member.issym() or member.islnk()
                    or pure.is_absolute() or '..' in pure.parts):
                fail('archive_entry')
        package.extractall(stage,filter='data')
    manifest=json.loads((stage/'manifest.json').read_text())
    if manifest.get('schema_version')!=1: fail('manifest_schema')
    files=manifest.get('files',{})
    if not isinstance(files,dict) or len(files)<20: fail('manifest')
    calculated_manifest=hashlib.sha256(
        json.dumps(files,sort_keys=True,separators=(',',':')).encode()
    ).hexdigest()
    if calculated_manifest!=payload.get('manifest_sha256'): fail('manifest_digest')
    for relative,sha in files.items():
        path=stage/relative
        if (not safe_file(path,2*1024*1024)
                or hashlib.sha256(path.read_bytes()).hexdigest()!=sha):
            fail('source_hash')
    (op/'installed-source.json').write_text(json.dumps({'source_sha':source,'files':files},sort_keys=True))
    os.chmod(op/'installed-source.json',0o600)
    if mode=='install-runtime':
        result['install']=install_runtime(stage,files,op)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='installed'
        result['supplier_calls']=0
        result['database_writes']=0
        result['runtime_changed']=result['install']['changed_files']>0
        result['public_ui_entrypoints_unchanged']=True
    if mode=='program-fuel-readback':
        result['before_db']=db_summary('andromeda')
        readback=program_fuel_readback()
        result['program_fuel_readback']=readback
        result['after_db']=db_summary('andromeda')
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        if result['before_db']!=result['after_db']: fail('program_fuel_readback_db_drift')
        result['status']='complete' if readback.get('acceptance_pass') is True else 'blocked'
        result['reason']=None if readback.get('acceptance_pass') is True else readback.get('failure_reason','program_fuel_readback_acceptance')
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='funsun-direction-fuel-seed':
        result['before_db']=db_summary('andromeda')
        result['direction_fuel_seed']=funsun_direction_fuel_seed()
        result['after_db']=db_summary('andromeda')
        if result['after_db']!=result['before_db']: fail('direction_fuel_seed_db_drift')
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='program-fuel-probe':
        result['before_db']=db_summary('andromeda')
        result['program_fuel_probe']=program_fuel_probe()
        result['after_db']=db_summary('andromeda')
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        if result['before_db']!=result['after_db']: fail('program_fuel_probe_db_drift')
        probe_status=result['program_fuel_probe'].get('status')
        if probe_status in ('complete','supplier_rejected'):
            result['status']='complete'
        elif probe_status=='blocked_before_supplier':
            result['status']='blocked'
        else:
            result['status']='unknown_no_replay'
        result['supplier_calls']=result['program_fuel_probe'].get('supplier_calls','unknown')
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='local-readback':
        result['before_db']=db_summary('andromeda')
        scopes=[{'departureId':payload['departure'],'countryId':payload['country'],
                 'regionId':payload['region'] or None,'dateFrom':payload['date_from'],
                 'dateTo':payload['date_to'],'nights':payload['nights'],
                 'adults':payload['adults'],'childAges':[]}]
        result['local_readback']=local_read(scopes)
        result['after_db']=db_summary('andromeda')
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
        __LOCAL_READBACK__=True
    if mode=='andromeda-operator-preflight':
        result['before_db']=db_summary('andromeda')
        result['operator_preflight']=operator_preflight(stage)
        result['after_db']=db_summary('andromeda')
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        if result['before_db']!=result['after_db']: fail('operator_preflight_db_drift')
        result['status']='preflight_complete'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='reconcile':
        target_name=payload['target_operation_id']
        provider='anex' if target_name.startswith('int-anex-') else 'andromeda'
        result['before_db']=db_summary(provider)
        result['reconciliation']=reconcile_target(target_name)
        result['after_db']=db_summary(provider)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='reconciled_read_only'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
        __RECONCILED__=True
    if mode=='match-secondary-audit':
        result['match_secondary_audit']=run_match_secondary_audit(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='match-coverage':
        result['match_coverage']=run_match_coverage(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='match-coverage-readback':
        result['match_coverage_readback']=read_match_coverage()
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='reconciled_read_only'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='match-tv942-reconcile':
        result['match_tv942_reconcile']=run_match_tv942_reconcile(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='match-tv942-write':
        result['match_tv942_write']=run_match_tv942_write(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=0
        result['database_writes']=result['match_tv942_write']['inserted']
        result['production_unchanged']=True
    if mode=='match-readback':
        result['match_readback']=read_match942(payload['lane'],int(payload['offset']),int(payload['limit']))
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='reconciled_read_only'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode in ('match-tv942','match-samo942'):
        result['match942']=run_match942(stage,mode,int(payload['offset']),int(payload['limit']))
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=result['match942']['summary'].get('provider_calls',
            result['match942']['summary'].get('samo_http_calls','bounded'))
        result['database_writes']=0
        result['production_unchanged']=True
    if mode not in ('reconcile','local-readback','program-fuel-readback','program-fuel-probe','funsun-direction-fuel-seed','install-runtime','match-coverage','match-coverage-readback','match-readback','match-tv942-reconcile','match-tv942-write','match-tv942','match-samo942','andromeda-operator-preflight'):
        provider='anex' if mode=='anex-demand' else 'andromeda'
        result['before_db']=db_summary(provider)
        env={k:v for k,v in os.environ.items() if k not in ('ANEX_API_TOKEN','ANEX_B2B_TOKEN')}
        env['ANYTOUR_PROJECT_ROOT']=str(project)
        generation=str(2100000000-(int(hashlib.sha256(operation.encode()).hexdigest()[:6],16)%1000000))
    if mode=='anex-demand':
        command=['php',str(stage/'scripts/ops/anex_local_offer_demand_fill.php'),
          '--limit='+str(payload['limit']),'--lookback-hours=168','--horizon-days=21',
          '--max-expands=600','--max-apd=600','--generation-base='+generation]
    elif mode in ('andromeda-scope','andromeda-external-group','andromeda-operator-scope'):
        config=project/'_preview/search3-anex-candidate/.andromeda-private.php'
        if not safe_file(config,65536): fail('andromeda_private_config_missing')
        command=['php',str(stage/'scripts/ops/andromeda_local_offer_collect.php'),
          '--site-root='+str(project),'--private-config='+str(config),'--source-sha='+source,
          '--departure='+str(payload['departure']),'--country='+str(payload['country']),
          '--date-from='+payload['date_from'],'--date-to='+payload['date_to'],
          '--nights='+str(payload['nights']),'--adults='+str(payload['adults']),
          '--meal='+payload['meal'],'--generation='+generation,
          '--max-captures='+str(payload['max_captures']),'--max-capture-seconds='+('240' if payload['max_captures']>0 else '0'),
          '--capture-mode='+('external_group_only' if mode=='andromeda-external-group' else 'non_external_only')]
        if payload['region']: command.append('--region='+str(payload['region']))
        if mode=='andromeda-operator-scope': command.append('--operator-id='+str(payload['operator_id']))
    if mode not in ('reconcile','local-readback','program-fuel-readback','program-fuel-probe','funsun-direction-fuel-seed','install-runtime','match-coverage','match-coverage-readback','match-readback','match-tv942-reconcile','match-tv942-write','match-tv942','match-samo942','andromeda-operator-preflight'):
        run=subprocess.run(command,cwd=stage,env=env,capture_output=True,text=True,timeout=900)
        result['collector_exit']=run.returncode
        stderr=run.stderr.strip()
        result['collector_stderr_nonempty']=bool(stderr)
        result['collector_stderr_sha256']=hashlib.sha256(stderr.encode()).hexdigest() if stderr else None
        code_match=re.search(r'(?:RuntimeException|DomainException|InvalidArgumentException):\s*([A-Z][A-Z0-9_]{2,80})',stderr)
        result['collector_error_code']=code_match.group(1) if code_match else ('PHP_FATAL' if 'PHP Fatal error' in stderr else None)
        try: collector=json.loads(run.stdout.strip())
        except Exception: collector={'status':'unparseable'}
        result['collector']=collector
        result['after_db']=db_summary(provider)
        parseable=collector.get('status')!='unparseable'
        if run.returncode==0 and parseable:
            if mode=='anex-demand':
                scopes=[x.get('scope',{}) for x in collector.get('results',[])
                        if isinstance(x,dict) and isinstance(x.get('scope'),dict)]
            else:
                scopes=[{'departureId':payload['departure'],'countryId':payload['country'],
                         'regionId':payload['region'] or None,'dateFrom':payload['date_from'],
                         'dateTo':payload['date_to'],'nights':payload['nights'],
                         'adults':payload['adults'],'childAges':[],
                         'operatorId':payload.get('operator_id')}]
            try:
                result['local_readback']=local_read(scopes) if scopes else []
            except Exception as exc:
                result['local_readback']={'status':'failed','reason':str(exc)}
        else:
            result['local_readback']={'status':'skipped_after_collector_nonzero'}
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete' if run.returncode==0 and parseable else 'unknown_no_replay'
        result['supplier_calls']='bounded_by_collector' if result['status']=='complete' else 'unknown'
        result['database_writes']='collector_owned' if result['status']=='complete' else 'unknown'
        result['production_unchanged']=True
except Exception as exc:
    if mode=='install-runtime' and install_started:
        failure=str(exc)
        result['install_failure_class']=failure if re.fullmatch(r'[A-Za-z0-9_:-]{1,96}',failure) else type(exc).__name__
        try:
            result['rollback']={'status':'complete','restored_files':len(rollback_install(op))}
            result['status']='rolled_back'
            result['supplier_calls']=0
            result['database_writes']=0
            result['runtime_changed']=False
            result['public_ui_entrypoints_unchanged']=fingerprints()==before
        except Exception as rollback_error:
            value=str(rollback_error)
            result['rollback']={'status':'failed','failure_class':
                value if re.fullmatch(r'[A-Za-z0-9_:-]{1,96}',value) else type(rollback_error).__name__}
            result['status']='rollback_failed_no_replay'
    elif result.get('status')=='reserved':
        result['status']='unknown_no_replay';result['reason']=str(exc)
    elif result.get('status')=='blocked':
        result['reason']=str(exc)
    elif result.get('status') not in ('complete','terminal_nonzero_no_replay'):
        result['status']='unknown_no_replay';result['reason']=str(exc)
finally:
    try:
        if 'op' in globals() and op.exists():
            (op/'result.json').write_text(json.dumps(result,sort_keys=True,separators=(',',':')))
            os.chmod(op/'result.json',0o600)
    except Exception:
        pass
print(json.dumps(result,separators=(',',':')))
"""

def ssh_options(key: Path, known: Path) -> list[str]:
    return [
        '-T','-i',str(key),'-o','IdentitiesOnly=yes','-o','BatchMode=yes',
        '-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),
        '-o','GlobalKnownHostsFile=/dev/null','-o','ConnectTimeout=15',
        '-o','ServerAliveInterval=15','-o','ServerAliveCountMax=3','-o','LogLevel=ERROR'
    ]

def execute(command: dict, source_root: Path) -> dict:
    host, user, raw_key = (
        os.environ.get(name, '').strip()
        for name in ('INT_SSH_HOST','INT_SSH_USER','INT_SSH_KEY')
    )
    need(bool(host and user and raw_key), 'ssh_config')
    need(not host.startswith('-') and not user.startswith('-')
         and not any(c.isspace() for c in host + user), 'ssh_identity')
    bundle, manifest = bundle_source(source_root)
    output = Path(os.environ['RUNNER_TEMP']) / 'int-server-executor'
    output.mkdir(mode=0o700, exist_ok=True)
    key, known, archive = output/'key', output/'known_hosts', output/'source.tar.gz'
    key.write_text(raw_key.rstrip() + '\n'); key.chmod(0o600)
    subprocess.run(['ssh-keygen','-y','-f',str(key)], stdout=subprocess.DEVNULL,
                   stderr=subprocess.PIPE, check=True, timeout=10)
    scan = subprocess.run(['ssh-keyscan','-T','15','-t','ed25519',host],
                          capture_output=True, check=True, timeout=20).stdout
    need(bool(scan), 'ssh_hostkey')
    known.write_bytes(scan); known.chmod(0o600)
    archive.write_bytes(bundle); archive.chmod(0o600)
    options = ssh_options(key, known)
    remote_archive = (
        '/tmp/' + command['operation_id'] + '-' +
        hashlib.sha256(bundle).hexdigest()[:16] + '.tar.gz'
    )
    subprocess.run(['scp',*options,str(archive),user+'@'+host+':'+remote_archive],
                   check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE,
                   timeout=60)
    payload = dict(command)
    payload['archive'] = remote_archive
    payload['manifest_sha256'] = hashlib.sha256(
        json.dumps(manifest,sort_keys=True,separators=(',',':')).encode()
    ).hexdigest()
    if command['mode'] == 'program-fuel-readback':
        readback_path = Path(__file__).resolve().parents[2] / 'scripts/diagnostics/int_program_fuel_cohort_v3_readback.php'
        need(readback_path.is_file() and not readback_path.is_symlink(), 'program_fuel_readback_source')
        readback_bytes = readback_path.read_bytes()
        need(0 < len(readback_bytes) <= 1024 * 1024, 'program_fuel_readback_source_size')
        payload['program_fuel_readback_php_b64'] = base64.b64encode(readback_bytes).decode()
        payload['program_fuel_readback_php_sha256'] = hashlib.sha256(readback_bytes).hexdigest()
    if command['mode'] == 'funsun-direction-fuel-seed':
        seed_bytes = FUNSUN_DIRECTION_FUEL_SEED_PHP.encode()
        need(0 < len(seed_bytes) <= 256 * 1024, 'direction_fuel_seed_source_size')
        payload['funsun_direction_fuel_seed_php_b64'] = base64.b64encode(seed_bytes).decode()
        payload['funsun_direction_fuel_seed_php_sha256'] = hashlib.sha256(seed_bytes).hexdigest()
    if command['mode'] == 'program-fuel-probe':
        probe_path = Path(__file__).resolve().parents[2] / 'scripts/diagnostics/int_andromeda_program_getflights_probe_v1.php'
        need(probe_path.is_file() and not probe_path.is_symlink(), 'program_fuel_probe_source')
        probe_bytes = probe_path.read_bytes()
        need(0 < len(probe_bytes) <= 1024 * 1024, 'program_fuel_probe_source_size')
        payload['program_fuel_probe_php_b64'] = base64.b64encode(probe_bytes).decode()
        payload['program_fuel_probe_php_sha256'] = hashlib.sha256(probe_bytes).hexdigest()
    encoded = base64.b64encode(REMOTE.encode()).decode()
    remote_command = (
        "python3 -c 'import base64;exec(base64.b64decode(\"" + encoded + "\"))'"
    )
    try:
        run = subprocess.run(
            ['ssh',*options,'-l',user,host,remote_command],
            input=json.dumps(payload,separators=(',',':')), text=True,
            capture_output=True, timeout=1000
        )
        need(run.returncode == 0, 'ssh_remote_exit')
        result = json.loads(run.stdout.strip())
        need(isinstance(result,dict)
             and result.get('operation_id') == command['operation_id']
             and result.get('source_sha') == command['source_sha'],
             'remote_receipt')
        (output/'result.json').write_text(
            json.dumps(result,sort_keys=True,indent=2) + '\n'
        )
        return result
    finally:
        subprocess.run(
            ['ssh',*options,'-l',user,host,'rm -f -- '+shlex.quote(remote_archive)],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=30
        )
        key.unlink(missing_ok=True); known.unlink(missing_ok=True)

def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('--parse-only', action='store_true')
    parser.add_argument('--source-root', default='source')
    args = parser.parse_args()
    event = json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
    token = os.environ.get('GH_TOKEN','')
    need(bool(token), 'gh_token')
    command = checked_event(token, event, os.environ['GITHUB_SHA'])
    if args.parse_only:
        for key,value in command.items():
            print(f'{key}={value}')
        return
    if command['mode'] in ('match-tv942','match-samo942','program-fuel-probe'):
        ensure_supplier_slot(token)
    result = execute(command, Path(args.source_root))
    print(json.dumps(result,sort_keys=True))
    if result.get('status') not in ('complete','reconciled_read_only','installed'):
        raise SystemExit(1)

if __name__ == '__main__':
    main()
