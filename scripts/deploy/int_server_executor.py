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
import zlib

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
    'v2/api-andromeda-quote-preview.php',
    'v2/data/hotel-details-v1.php',
    'scripts/diagnostics/hotel_match_anex_effective_coverage.php',
    'scripts/diagnostics/hotel_match_live942_frontier_plan_v1.php',
    'scripts/diagnostics/hotel_match_live942_tv_anex_refresh_v1.py',
    'scripts/diagnostics/hotel_match_live942_samo_anex_refresh_v1.php',
    'scripts/diagnostics/hotel_match_live_anex_samo_missing_secondary_audit_v1.php',
    'scripts/diagnostics/hotel_match_live942_tv_candidate_reconcile_v1.php',
    'scripts/diagnostics/hotel_match_live942_tv_writer_v1.php',
    'scripts/diagnostics/hotel_match_live234_frontier_plan_v1.php',
    'scripts/diagnostics/hotel_match_live234_tv_secondary_refresh_v1.py',
    'scripts/diagnostics/hotel_match_tv_samo_anex_coverage_v1.php',
    'scripts/diagnostics/hotel_match_current_coverage_wrapper_v1.php',
    'scripts/diagnostics/hotel_match_live30_common4_gap_matrix_v1.php',
    'scripts/diagnostics/hotel_match_live30_common4_plan_v1.php',
    'scripts/diagnostics/hotel_match_live30_common4_acquire_v1.py',
    'scripts/diagnostics/hotel_match_live30_common4_continuation_acquire_v10.py',
    'scripts/diagnostics/hotel_match_live30_common4_remainder_v1.py',
    'scripts/diagnostics/hotel_match_common4_resume_salvage_v1.py',
    'scripts/diagnostics/hotel_match_common4_mass_current_v14.php',
    'scripts/diagnostics/hotel_match_live30_common4_current_v2.php',
    'scripts/diagnostics/int_funsun_direction_fx_seed_v1.php',
    'scripts/diagnostics/int_operator_direction_fuel_mass_readback_v1.php',
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
    if mode == 'install-anex-preview':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-anex-'), 'anex_preview_install_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'install-andromeda-preview':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'preview_install_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'install-andromeda-quote-preview':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'quote_preview_install_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'program-fuel-readback':
        # Supplier-free exact DB/retained-cohort acceptance through the permanent SSH lane.
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'program_fuel_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'funsun-direction-fuel-seed':
        # Historical supplier-derived 140-EUR seed mode; retained for no-replay compatibility only.
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-funsun-antalya-direction-fuel-seed-'),
             'direction_fuel_seed_operation')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'funsun-direction-fx-seed':
        # Supplier-free FX-only persistence from two explicitly named terminal probe receipts.
        need(len(parts) == 5, 'command_shape')
        first, second = parts[3], parts[4]
        for target in (first, second):
            need(OP_RE.fullmatch(target) is not None and target.startswith('int-andromeda-')
                 and target != operation, 'direction_fx_seed_target')
        need(first != second, 'direction_fx_seed_targets_distinct')
        need(operation.startswith('int-andromeda-funsun-') and '-fx-' in operation,
             'direction_fx_seed_operation')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'probe_operation_a': first, 'probe_operation_b': second}
    if mode == 'operator-direction-fuel-readback':
        # Supplier-free DB/retained acceptance for one exact terminal collector cohort.
        need(len(parts) == 7, 'command_shape')
        target, family = parts[3], parts[4]
        need(OP_RE.fullmatch(target) is not None and target.startswith('int-andromeda-')
             and target != operation, 'direction_readback_target')
        need(operation.startswith('int-andromeda-'), 'direction_readback_operation')
        need(family in ('fun_and_sun','intourist','biblio_globus'), 'direction_readback_family')
        departure = integer(parts[5], 1, 999999999, 'departure')
        country = integer(parts[6], 1, 999999999, 'country')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'target_operation_id': target, 'operator_family': family,
                'departure': departure, 'country': country}
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
    if mode == 'anex-range':
        # Exact owner-authorized direct-ANEX search/autosave over one user-visible range.
        need(len(parts) == 12, 'command_shape')
        need(operation.startswith('int-anex-'), 'anex_range_operation_namespace')
        departure = integer(parts[3], 1, 999999999, 'departure')
        country = integer(parts[4], 1, 999999999, 'country')
        date_from, date_to = date(parts[5]), date(parts[6])
        import datetime as dt
        inclusive_days = (dt.date.fromisoformat(date_to) - dt.date.fromisoformat(date_from)).days + 1
        need(1 <= inclusive_days <= 21, 'anex_date_range')
        nights = integer(parts[7], 1, 28, 'nights')
        adults = integer(parts[8], 1, 6, 'adults')
        child_raw = parts[9]
        need(re.fullmatch(r'(?:-|(?:[0-9]|1[0-7])(?:,(?:[0-9]|1[0-7])){0,2})', child_raw) is not None,
             'child_ages')
        child_ages = [] if child_raw == '-' else sorted(int(x) for x in child_raw.split(','))
        meal = parts[10]
        need(re.fullmatch(r'(?:-|[A-Za-z0-9_,&]{1,32})', meal) is not None, 'meal')
        region = integer(parts[11], 0, 999999999, 'region')
        return {
            'source_sha': source, 'mode': mode, 'operation_id': operation,
            'departure': departure, 'country': country, 'date_from': date_from,
            'date_to': date_to, 'nights': nights, 'adults': adults,
            'child_ages': child_ages, 'meal': '' if meal == '-' else meal, 'region': region,
        }
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
    if mode == 'match-common4-acquire':
        need(len(parts) == 5, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        offset = integer(parts[3], 0, 1798, 'match_offset')
        limit = integer(parts[4], 1, 100, 'match_limit')
        need(offset + limit <= 1799, 'match_scope')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'offset': offset, 'limit': limit}
    if mode == 'match-common4-continuation-acquire':
        need(len(parts) == 5, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        offset = integer(parts[3], 0, 1348, 'match_offset')
        limit = integer(parts[4], 1, 1349, 'match_limit')
        need(offset + limit <= 1349, 'match_scope')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'offset': offset, 'limit': limit}
    if mode == 'match-common4-continuation-resume-day':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'match-common4-continuation-resume-readback':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'match-common4-resume-readback':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'match-common4-continuation-remainder':
        need(len(parts) == 4, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'limit': integer(parts[3], 1, 300, 'match_limit')}
    if mode == 'match-common4-resume-salvage':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'match-common4-mass-current':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'match-common4-readback':
        need(len(parts) == 5, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        offset = integer(parts[3], 0, 1798, 'match_offset')
        limit = integer(parts[4], 1, 100, 'match_limit')
        need(offset + limit <= 1799, 'match_scope')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'offset': offset, 'limit': limit}
    if mode == 'match-common4-current-v2':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'match-tv234-secondary':
        need(len(parts) == 5, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        offset = integer(parts[3], 0, 233, 'match_offset')
        limit = integer(parts[4], 1, 78, 'match_limit')
        need(offset + limit <= 234, 'match_scope')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'offset': offset, 'limit': limit}
    if mode == 'match-tv234-readback':
        need(len(parts) == 5, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        offset = integer(parts[3], 0, 233, 'match_offset')
        limit = integer(parts[4], 1, 78, 'match_limit')
        need(offset + limit <= 234, 'match_scope')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'offset': offset, 'limit': limit}
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
    if mode == 'match-coverage-v2':
        need(len(parts) == 3, 'command_shape')
        need(operation.startswith('int-andromeda-'), 'match_operation_namespace')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}
    if mode == 'match-coverage-v2-readback':
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
                or not (
                    (target.get('sample_basis')=='distinct_spo'
                     and target.get('sample_distinct_spo_index')==payload['sample_index']
                     and isinstance(target.get('spo_key'),str))
                    or
                    (payload['operator_family']=='intourist'
                     and target.get('sample_basis')=='distinct_mapped_hotel'
                     and target.get('sample_distinct_spo_index') is None
                     and target.get('spo_key') is None
                     and target.get('sample_distinct_mapped_hotel_index')==payload['sample_index']
                     and target.get('retained_distinct_spo_count')==0
                     and isinstance(target.get('retained_distinct_mapped_hotel_count'),int)
                     and target.get('retained_distinct_mapped_hotel_count')>=2
                     and target.get('mapped_local_hotel') is True
                     and target.get('retained_freight_external') is False)
                )
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
def funsun_direction_fx_seed():
    script=stage/'scripts/diagnostics/int_funsun_direction_fx_seed_v1.php'
    if not safe_file(script,512*1024): fail('direction_fx_seed_source_missing')
    run=subprocess.run(
        ['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
         str(script),payload['probe_operation_a'],payload['probe_operation_b']],
        cwd=stage,capture_output=True,text=True,timeout=90
    )
    if run.returncode!=0 or run.stderr.strip(): fail('direction_fx_seed_failed')
    try: data=json.loads(run.stdout.strip())
    except Exception: fail('direction_fx_seed_unparseable')
    if (not isinstance(data,dict) or data.get('schema_version')!=1
            or data.get('source')!='int-funsun-direction-fx-seed-v1'
            or data.get('status')!='seeded_verified'
            or data.get('direction')!={'operator_family':'fun_and_sun','market':'departure:1','destination':'country:4'}
            or data.get('independent_probe_count')!=2
            or data.get('supplier_calls')!=0 or data.get('database_reads')!=0
            or data.get('database_writes')!=0 or data.get('fuel_rule_writes')!=0
            or data.get('final_price_verified') is not False
            or data.get('write_state') not in ('created','already_present')
            or not isinstance(data.get('rate'),str)
            or not re.fullmatch(r'(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,8})?',data['rate'])
            or not isinstance(data.get('evidence_sha256'),str)
            or not re.fullmatch(r'[a-f0-9]{64}',data['evidence_sha256'])):
        fail('direction_fx_seed_acceptance')
    return data
def operator_direction_fuel_readback():
    script=stage/'scripts/diagnostics/int_operator_direction_fuel_mass_readback_v1.php'
    if not safe_file(script,1024*1024): fail('direction_fuel_readback_source_missing')
    env=dict(os.environ)
    env.update({
        'INT_DIRECTION_FUEL_READBACK_OPERATION':str(payload['target_operation_id']),
        'INT_DIRECTION_FUEL_OPERATOR_FAMILY':str(payload['operator_family']),
        'INT_DIRECTION_FUEL_DEPARTURE_ID':str(payload['departure']),
        'INT_DIRECTION_FUEL_COUNTRY_ID':str(payload['country']),
    })
    run=subprocess.run(
        ['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',str(script)],
        cwd=stage,env=env,capture_output=True,text=True,timeout=90
    )
    if run.returncode!=0 or run.stderr.strip(): fail('direction_fuel_readback_failed')
    try: data=json.loads(run.stdout.strip())
    except Exception: fail('direction_fuel_readback_unparseable')
    expected={'operator_family':payload['operator_family'],
              'market':'departure:'+str(payload['departure']),
              'destination':'country:'+str(payload['country'])}
    if (not isinstance(data,dict)
            or data.get('source')!='int-operator-direction-fuel-mass-readback-v1'
            or data.get('operation_id')!=payload['target_operation_id']
            or data.get('direction')!=expected
            or data.get('supplier_calls')!=0 or data.get('db_writes')!=0
            or not isinstance(data.get('stored_scope_count'),int)
            or not isinstance(data.get('stored_target_count'),int)
            or not isinstance(data.get('ready_target_count'),int)
            or not isinstance(data.get('verified_target_count'),int)):
        fail('direction_fuel_readback_acceptance')
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

def install_anex_preview(stage,files,op):
    global install_started
    selected=[
        ('app/integrations/anex-initial-week-gate.php','app/integrations/anex-initial-week-gate.php'),
        ('v2/api-anex-search3-preview.php','api-anex-search3-preview.php'),
    ]
    backup_root=op/'backup';backup_root.mkdir(mode=0o700)
    changed=[]
    endpoint_expected=None
    for source_relative,target_relative in selected:
        source_path=stage/source_relative
        expected=files.get(source_relative)
        if (not safe_file(source_path,2*1024*1024)
                or not isinstance(expected,str)
                or hashlib.sha256(source_path.read_bytes()).hexdigest()!=expected):
            fail('anex_preview_install_source_hash')
        lint=subprocess.run(['php','-l',str(source_path)],capture_output=True,text=True,timeout=20)
        if lint.returncode!=0: fail('anex_preview_install_source_lint')
        target=runtime/target_relative
        if not target.parent.is_dir() or target.parent.is_symlink() or target.parent.resolve()!=target.parent:
            fail('anex_preview_install_target_parent')
        prior={'exists':False,'sha256':None,'mode':0o644}
        if target.exists() or target.is_symlink():
            if not safe_file(target,2*1024*1024): fail('anex_preview_install_target_invalid')
            data=target.read_bytes()
            prior={'exists':True,'sha256':hashlib.sha256(data).hexdigest(),
                   'mode':target.stat().st_mode&0o777}
            backup=backup_root/target_relative;backup.parent.mkdir(parents=True,exist_ok=True)
            backup.write_bytes(data);os.chmod(backup,0o600)
            if hashlib.sha256(backup.read_bytes()).hexdigest()!=prior['sha256']:
                fail('anex_preview_install_backup_hash')
        install_previous[target_relative]=prior
        install_expected[target_relative]=expected
        if source_relative=='v2/api-anex-search3-preview.php':
            endpoint_expected=expected
        if prior['sha256']!=expected:
            changed.append(target_relative)
            install_temps[target_relative]=stage_target_bytes(target,source_path.read_bytes(),prior['mode'])
    write_private_json(op/'anex-preview-install-plan.json',{
        'schema_version':1,'source_sha':source,
        'files':[{'source':a,'target':b} for a,b in selected],
        'changed_files':changed,'previous':install_previous,'expected':install_expected,'status':'prepared'})
    install_started=True
    for target_relative in changed:
        target=runtime/target_relative
        os.replace(install_temps[target_relative],target)
        install_applied.append(target_relative)
        os.chmod(target,install_previous[target_relative]['mode'])
        write_private_json(op/'install-state.json',
            {'status':'applying-anex-preview','source_sha':source,'applied':install_applied})
    for target_relative,expected in install_expected.items():
        target=runtime/target_relative
        if (not safe_file(target,2*1024*1024)
                or hashlib.sha256(target.read_bytes()).hexdigest()!=expected):
            fail('anex_preview_install_readback_hash')
        lint=subprocess.run(['php','-l',str(target)],capture_output=True,text=True,timeout=20)
        if lint.returncode!=0: fail('anex_preview_install_readback_lint')
    complete={'status':'installed','source_sha':source,'files':len(selected),
              'changed_files':len(changed),'created_files':sum(
                  1 for relative in changed if not install_previous[relative]['exists']),
              'manifest_sha256':payload['manifest_sha256'],
              'endpoint':{'source':'v2/api-anex-search3-preview.php',
                          'target':'api-anex-search3-preview.php',
                          'sha256':endpoint_expected,
                          'changed':'api-anex-search3-preview.php' in changed},
              'dependency':{'source':'app/integrations/anex-initial-week-gate.php',
                            'target':'app/integrations/anex-initial-week-gate.php',
                            'sha256':install_expected['app/integrations/anex-initial-week-gate.php'],
                            'changed':'app/integrations/anex-initial-week-gate.php' in changed}}
    write_private_json(op/'install-state.json',complete)
    return complete

def install_andromeda_preview(stage,files,op):
    integration=install_runtime(stage,files,op)
    source_relative='v2/api-andromeda-search3-preview.php'
    target_relative='api-andromeda-search3-preview.php'
    source_path=stage/source_relative
    expected=files.get(source_relative)
    if (not safe_file(source_path,2*1024*1024)
            or not isinstance(expected,str)
            or hashlib.sha256(source_path.read_bytes()).hexdigest()!=expected):
        fail('preview_install_source_hash')
    lint=subprocess.run(['php','-l',str(source_path)],capture_output=True,text=True,timeout=20)
    if lint.returncode!=0: fail('preview_install_source_lint')
    target=runtime/target_relative
    if not target.parent.is_dir() or target.parent.is_symlink() or target.parent.resolve()!=target.parent:
        fail('preview_install_target_parent')
    prior={'exists':False,'sha256':None,'mode':0o644}
    if target.exists() or target.is_symlink():
        if not safe_file(target,2*1024*1024): fail('preview_install_target_invalid')
        data=target.read_bytes()
        prior={'exists':True,'sha256':hashlib.sha256(data).hexdigest(),
               'mode':target.stat().st_mode&0o777}
        backup=op/'backup'/target_relative
        backup.write_bytes(data);os.chmod(backup,0o600)
        if hashlib.sha256(backup.read_bytes()).hexdigest()!=prior['sha256']:
            fail('preview_install_backup_hash')
    install_previous[target_relative]=prior
    install_expected[target_relative]=expected
    changed=prior['sha256']!=expected
    write_private_json(op/'preview-install-plan.json',{
        'schema_version':1,'source_sha':source,'source':source_relative,'target':target_relative,
        'previous':prior,'expected_sha256':expected,'changed':changed,'status':'prepared'})
    if changed:
        install_temps[target_relative]=stage_target_bytes(target,source_path.read_bytes(),prior['mode'])
        os.replace(install_temps[target_relative],target)
        install_applied.append(target_relative)
        os.chmod(target,prior['mode'])
        write_private_json(op/'install-state.json',
            {'status':'applying-preview','source_sha':source,'applied':install_applied})
    if (not safe_file(target,2*1024*1024)
            or hashlib.sha256(target.read_bytes()).hexdigest()!=expected):
        fail('preview_install_readback_hash')
    lint=subprocess.run(['php','-l',str(target)],capture_output=True,text=True,timeout=20)
    if lint.returncode!=0: fail('preview_install_readback_lint')
    complete={'status':'installed','source_sha':source,'files':integration['files']+1,
              'changed_files':integration['changed_files']+(1 if changed else 0),
              'created_files':integration['created_files']+(1 if changed and not prior['exists'] else 0),
              'manifest_sha256':payload['manifest_sha256'],
              'endpoint':{'source':source_relative,'target':target_relative,'sha256':expected,'changed':changed}}
    write_private_json(op/'install-state.json',complete)
    return complete

def install_andromeda_quote_preview(stage,files,op):
    integration=install_runtime(stage,files,op)
    source_relative='v2/api-andromeda-quote-preview.php'
    target_relative='api-andromeda-quote-preview.php'
    source_path=stage/source_relative
    expected=files.get(source_relative)
    if (not safe_file(source_path,2*1024*1024)
            or not isinstance(expected,str)
            or hashlib.sha256(source_path.read_bytes()).hexdigest()!=expected):
        fail('quote_preview_install_source_hash')
    lint=subprocess.run(['php','-l',str(source_path)],capture_output=True,text=True,timeout=20)
    if lint.returncode!=0: fail('quote_preview_install_source_lint')
    target=runtime/target_relative
    if not target.parent.is_dir() or target.parent.is_symlink() or target.parent.resolve()!=target.parent:
        fail('quote_preview_install_target_parent')
    prior={'exists':False,'sha256':None,'mode':0o644}
    if target.exists() or target.is_symlink():
        if not safe_file(target,2*1024*1024): fail('quote_preview_install_target_invalid')
        data=target.read_bytes()
        prior={'exists':True,'sha256':hashlib.sha256(data).hexdigest(),
               'mode':target.stat().st_mode&0o777}
        backup=op/'backup'/target_relative
        backup.write_bytes(data);os.chmod(backup,0o600)
        if hashlib.sha256(backup.read_bytes()).hexdigest()!=prior['sha256']:
            fail('quote_preview_install_backup_hash')
    install_previous[target_relative]=prior
    install_expected[target_relative]=expected
    changed=prior['sha256']!=expected
    write_private_json(op/'quote-preview-install-plan.json',{
        'schema_version':1,'source_sha':source,'source':source_relative,'target':target_relative,
        'previous':prior,'expected_sha256':expected,'changed':changed,'status':'prepared'})
    if changed:
        install_temps[target_relative]=stage_target_bytes(target,source_path.read_bytes(),prior['mode'])
        os.replace(install_temps[target_relative],target)
        install_applied.append(target_relative)
        os.chmod(target,prior['mode'])
        write_private_json(op/'install-state.json',
            {'status':'applying-preview','source_sha':source,'applied':install_applied})
    if (not safe_file(target,2*1024*1024)
            or hashlib.sha256(target.read_bytes()).hexdigest()!=expected):
        fail('quote_preview_install_readback_hash')
    lint=subprocess.run(['php','-l',str(target)],capture_output=True,text=True,timeout=20)
    if lint.returncode!=0: fail('quote_preview_install_readback_lint')
    complete={'status':'installed','source_sha':source,'files':integration['files']+1,
              'changed_files':integration['changed_files']+(1 if changed else 0),
              'created_files':integration['created_files']+(1 if changed and not prior['exists'] else 0),
              'manifest_sha256':payload['manifest_sha256'],
              'endpoint':{'source':source_relative,'target':target_relative,'sha256':expected,'changed':changed}}
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


def run_match_coverage_v2(stage):
    coverage_child='hotel-match-current-coverage-1971-20260923-v2'
    matrix_child='hotel-match-live30-common4-gap-matrix-1971-20260923-v1'
    match_root=home/'.anytoour-match/operations'
    match_root.mkdir(mode=0o700,parents=True,exist_ok=True)
    coverage_dir=match_root/coverage_child
    matrix_dir=match_root/matrix_child
    if coverage_dir.exists() or coverage_dir.is_symlink(): fail('match_coverage_v2_child_exists_no_replay')
    if matrix_dir.exists() or matrix_dir.is_symlink(): fail('match_common4_matrix_child_exists_no_replay')
    coverage_dir.mkdir(mode=0o700);matrix_dir.mkdir(mode=0o700)
    for child,directory in ((coverage_child,coverage_dir),(matrix_child,matrix_dir)):
        reservation={'operation':child,'state':'reserved_before_db_read','source_sha':source,
                     'parent_operation':operation,'provider_http_calls':0,'database_writes':0,'mapping_writes':0}
        (directory/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
        os.chmod(directory/'reservation.json',0o600)

    coverage_runner=stage/'scripts/diagnostics/hotel_match_current_coverage_wrapper_v1.php'
    coverage_diag=stage/'scripts/diagnostics/hotel_match_tv_samo_anex_coverage_v1.php'
    matrix_runner=stage/'scripts/diagnostics/hotel_match_live30_common4_gap_matrix_v1.php'
    helper=stage/'scripts/diagnostics/hotel_match_anex_effective_coverage.php'
    for path in (coverage_runner,coverage_diag,matrix_runner,helper):
        if not safe_file(path): fail('match_coverage_v2_source_missing')

    env={**os.environ,'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(coverage_dir),
         'MATCH_SOURCE_SHA':source,'MATCH_CHILD_OPERATION':coverage_child}
    coverage_call=subprocess.run(['php',str(coverage_runner),'--execute'],cwd=project,env=env,
                                 capture_output=True,text=True,timeout=240)
    coverage_result_path=coverage_dir/'result.json';coverage_receipt_path=coverage_dir/'receipt.json'
    if not safe_file(coverage_result_path,128*1024*1024) or not safe_file(coverage_receipt_path,1024*1024):
        fail('match_coverage_v2_terminal_missing')
    coverage_result=safe_json(coverage_result_path,128*1024*1024)
    coverage_receipt=safe_json(coverage_receipt_path,1024*1024)
    coverage_digest=hashlib.sha256(coverage_result_path.read_bytes()).hexdigest()
    if coverage_receipt.get('result_sha256')!=coverage_digest: fail('match_coverage_v2_terminal_hash')
    for key in ('provider_http_calls','tourvisor_calls','samo_calls','anex_calls','database_writes','mapping_writes'):
        if coverage_result.get(key)!=0: fail('match_coverage_v2_nonzero_'+key)
    if coverage_call.returncode!=0 or coverage_result.get('state')!='completed_read_only_coverage':
        fail('match_coverage_v2_terminal_guard')

    env={**os.environ,'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(matrix_dir),
         'MATCH_SOURCE_SHA':source}
    matrix_call=subprocess.run(['php',str(matrix_runner),'--execute'],cwd=project,env=env,
                               capture_output=True,text=True,timeout=240)
    matrix_result_path=matrix_dir/'result.json';matrix_receipt_path=matrix_dir/'receipt.json'
    if not safe_file(matrix_result_path,64*1024*1024) or not safe_file(matrix_receipt_path,1024*1024):
        fail('match_common4_matrix_terminal_missing')
    matrix_result=safe_json(matrix_result_path,64*1024*1024)
    matrix_receipt=safe_json(matrix_receipt_path,1024*1024)
    matrix_digest=hashlib.sha256(matrix_result_path.read_bytes()).hexdigest()
    if matrix_receipt.get('result_sha256')!=matrix_digest: fail('match_common4_matrix_terminal_hash')
    for key in ('provider_http_calls','tourvisor_calls','samo_calls','anex_calls','andromeda_calls','database_writes','mapping_writes'):
        if matrix_result.get(key)!=0: fail('match_common4_matrix_nonzero_'+key)
    if matrix_call.returncode!=0 or matrix_result.get('state')!='completed_read_only_common4_gap_matrix':
        fail('match_common4_matrix_terminal_guard')

    live30=coverage_result.get('live_30d') if isinstance(coverage_result.get('live_30d'),dict) else {}
    active=coverage_result.get('active_tv') if isinstance(coverage_result.get('active_tv'),dict) else {}
    samo_live30=coverage_result.get('samo_live_30d') if isinstance(coverage_result.get('samo_live_30d'),dict) else {}
    matrix_summary={k:v for k,v in matrix_result.items() if k not in ('rows','definitions','source_sha')}
    return {
        'coverage':{
            'child_operation':coverage_child,'state':coverage_result.get('state'),
            'result_sha256':coverage_digest,'result_bytes':coverage_result_path.stat().st_size,
            'summary':{
                'generated_at_utc':coverage_result.get('generated_at_utc'),
                'edge_counts':coverage_result.get('edge_counts'),
                'active_counts':active.get('counts'),
                'live30_counts':live30.get('counts'),
                'live30_top_missing_geographies':live30.get('top_missing_geographies'),
                'samo_live30':samo_live30,
            },
            'stdout_sha256':hashlib.sha256(coverage_call.stdout.encode()).hexdigest(),
            'stderr_sha256':hashlib.sha256(coverage_call.stderr.encode()).hexdigest() if coverage_call.stderr else None,
        },
        'common4_gap_matrix':{
            'child_operation':matrix_child,'state':matrix_result.get('state'),
            'result_sha256':matrix_digest,'result_bytes':matrix_result_path.stat().st_size,
            'summary':matrix_summary,
            'stdout_sha256':hashlib.sha256(matrix_call.stdout.encode()).hexdigest(),
            'stderr_sha256':hashlib.sha256(matrix_call.stderr.encode()).hexdigest() if matrix_call.stderr else None,
        },
        'provider_http_calls':0,'database_writes':0,'mapping_writes':0,
    }


def read_match_coverage_v2():
    parent_name='int-andromeda-match-coverage-v2-20260923-v2'
    coverage_child='hotel-match-current-coverage-1971-20260923-v2'
    matrix_child='hotel-match-live30-common4-gap-matrix-1971-20260923-v1'
    parent_dir=private/parent_name
    parent={'operation_id':parent_name,'present':parent_dir.is_dir() and not parent_dir.is_symlink()}
    if parent['present']:
        reservation_path=parent_dir/'reservation.json';result_path=parent_dir/'result.json'
        if safe_file(reservation_path,1024*1024):
            reservation=safe_json(reservation_path,1024*1024)
            parent['reservation']={k:reservation.get(k) for k in ('operation_id','source_sha','mode','reserved_at')}
        if safe_file(result_path,8*1024*1024):
            value=safe_json(result_path,8*1024*1024)
            parent['result']={k:value.get(k) for k in ('status','reason','source_sha','mode','supplier_calls','database_writes','production_unchanged')}
            parent['result_sha256']=hashlib.sha256(result_path.read_bytes()).hexdigest()

    def read_child(name,kind):
        directory=home/'.anytoour-match/operations'/name
        out={'child_operation':name,'present':directory.is_dir() and not directory.is_symlink()}
        if not out['present']: return out
        result_path=directory/'result.json';receipt_path=directory/'receipt.json'
        limit=128*1024*1024 if kind=='coverage' else 64*1024*1024
        if not safe_file(result_path,limit) or not safe_file(receipt_path,1024*1024):
            out['terminal']='missing_or_oversized';return out
        value=safe_json(result_path,limit);receipt=safe_json(receipt_path,1024*1024)
        digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
        if receipt.get('result_sha256')!=digest: fail('match_coverage_v2_readback_hash_'+kind)
        common=('provider_http_calls','tourvisor_calls','samo_calls','anex_calls','database_writes','mapping_writes')
        for key in common:
            if value.get(key)!=0: fail('match_coverage_v2_readback_nonzero_'+kind+'_'+key)
        out.update({'terminal':'verified','state':value.get('state'),'result_sha256':digest,
                    'result_bytes':result_path.stat().st_size,'receipt_state':receipt.get('state'),
                    'receipt_no_replay':receipt.get('no_replay')})
        if kind=='coverage':
            if value.get('state')!='completed_read_only_coverage': fail('match_coverage_v2_readback_state_coverage')
            live30=value.get('live_30d') if isinstance(value.get('live_30d'),dict) else {}
            active=value.get('active_tv') if isinstance(value.get('active_tv'),dict) else {}
            samo=value.get('samo_live_30d') if isinstance(value.get('samo_live_30d'),dict) else {}
            out['summary']={'generated_at_utc':value.get('generated_at_utc'),'edge_counts':value.get('edge_counts'),
                            'active_counts':active.get('counts'),'live30_counts':live30.get('counts'),
                            'live30_top_missing_geographies':live30.get('top_missing_geographies'),
                            'samo_live30':samo}
        else:
            if value.get('andromeda_calls')!=0: fail('match_coverage_v2_readback_nonzero_matrix_andromeda_calls')
            if value.get('state')!='completed_read_only_common4_gap_matrix': fail('match_coverage_v2_readback_state_matrix')
            out['summary']={k:v for k,v in value.items() if k not in ('rows','definitions','source_sha')}
        return out

    coverage=read_child(coverage_child,'coverage')
    matrix=read_child(matrix_child,'matrix')
    return {'target_parent':parent,'coverage':coverage,'common4_gap_matrix':matrix,
            'provider_http_calls':0,'database_writes':0,'mapping_writes':0}

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

def read_match_tv234_secondary(offset, limit):
    child='hotel-match-live234-tv-secondary-1971-20260923-o'+str(offset)+'-n'+str(limit)+'-v1'
    child_dir=home/'.anytoour-match/operations'/child
    if not child_dir.is_dir() or child_dir.is_symlink(): fail('match_tv234_child_missing')
    result_path=child_dir/'result.json';receipt_path=child_dir/'receipt.json'
    if not safe_file(result_path,16*1024*1024) or not safe_file(receipt_path,1024*1024):
        fail('match_tv234_readback_missing')
    child_result=safe_json(result_path,16*1024*1024);receipt=safe_json(receipt_path,1024*1024)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if receipt.get('result_sha256')!=digest: fail('match_tv234_readback_hash')
    if (child_result.get('frontier_count')!=234 or child_result.get('scope_offset')!=offset
            or child_result.get('scope_count')!=limit or child_result.get('database_writes')!=0
            or child_result.get('mapping_writes')!=0 or child_result.get('operator_ids')!=[18,25,43]):
        fail('match_tv234_readback_guard')
    keys=('state','reason','planned_groups','completed_batches','searched_hotels','returned_targets',
          'returned_operator_pairs','provider_calls','daily_accounted_after_local_ledger','call_counts',
          'edge_state_counts','link_state_counts','single_native_chunk_unique_count')
    summary={k:child_result.get(k) for k in keys}
    return {'child_operation':child,'result_sha256':digest,'result_bytes':result_path.stat().st_size,
            'receipt_state':receipt.get('state'),'receipt_no_replay':receipt.get('no_replay'),
            'summary':summary}



def read_match_common4(offset, limit):
    child='hotel-match-live30-common4-acquire-1971-20260923-o'+str(offset)+'-n'+str(limit)+'-v1'
    child_dir=home/'.anytoour-match/operations'/child
    if not child_dir.is_dir() or child_dir.is_symlink(): fail('match_common4_readback_child_missing')
    result_path=child_dir/'result.json';receipt_path=child_dir/'receipt.json'
    if not safe_file(result_path,32*1024*1024) or not safe_file(receipt_path,1024*1024):
        fail('match_common4_readback_missing_or_oversized')
    child_result=safe_json(result_path,32*1024*1024);receipt=safe_json(receipt_path,1024*1024)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if receipt.get('result_sha256')!=digest: fail('match_common4_readback_hash')
    if (child_result.get('frontier_count')!=1799 or child_result.get('scope_offset')!=offset
            or child_result.get('scope_count')!=limit or child_result.get('database_writes')!=0
            or child_result.get('mapping_writes')!=0 or child_result.get('operator_ids')!=[13,18,25,43]
            or child_result.get('continue_calls')!=0 or child_result.get('dates_calls')!=0):
        fail('match_common4_readback_guard')
    calls=child_result.get('provider_calls')
    if not isinstance(calls,int) or calls<0 or calls>900: fail('match_common4_readback_call_cap')
    keys=('state','reason','planned_groups','completed_batches','searched_hotels',
          'incomplete_status_batches','returned_targets','returned_missing_operator_pairs',
          'returned_operator_pairs','provider_calls','daily_accounted_after_local_ledger',
          'provider_day','call_counts','edge_state_counts','link_state_counts',
          'single_native_chunk_unique_count','single_native_by_operator')
    summary={k:child_result.get(k) for k in keys}
    return {'child_operation':child,'result_sha256':digest,'result_bytes':result_path.stat().st_size,
            'receipt_state':receipt.get('state'),'receipt_no_replay':receipt.get('no_replay'),
            'summary':summary}

def run_match_common4_current_v2(stage):
    child='hotel-match-live30-common4-current-1971-20260923-v2'
    match_root=home/'.anytoour-match/operations'
    match_root.mkdir(mode=0o700,parents=True,exist_ok=True)
    child_dir=match_root/child
    if child_dir.exists() or child_dir.is_symlink(): fail('match_common4_current_v2_child_exists_no_replay')
    child_dir.mkdir(mode=0o700)
    reservation={'operation':child,'state':'reserved_before_db_read','source_sha':source,
                 'parent_operation':operation,'provider_http_calls':0,'supplier_calls':0,
                 'database_writes':0,'mapping_writes':0,'reserved_at':int(time.time())}
    (child_dir/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
    os.chmod(child_dir/'reservation.json',0o600)

    runner=stage/'scripts/diagnostics/hotel_match_live30_common4_current_v2.php'
    if not safe_file(runner): fail('match_common4_current_v2_source_missing')
    env={**os.environ,'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child_dir),
         'MATCH_SOURCE_SHA':source}
    call=subprocess.run(['php',str(runner),'--execute'],cwd=project,env=env,
                        capture_output=True,text=True,timeout=240)
    result_path=child_dir/'result.json';receipt_path=child_dir/'receipt.json'
    if not safe_file(result_path,32*1024*1024) or not safe_file(receipt_path,1024*1024):
        fail('match_common4_current_v2_terminal_missing')
    child_result=safe_json(result_path,32*1024*1024);receipt=safe_json(receipt_path,1024*1024)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if receipt.get('result_sha256')!=digest: fail('match_common4_current_v2_terminal_hash')
    if call.returncode!=0 or child_result.get('state')!='completed_read_only_current_audit':
        fail('match_common4_current_v2_terminal_guard')
    if (child_result.get('input_single_native_count')!=283
            or child_result.get('input_namespace_counts')!={'bgoperator':205,'operator_315':50,'operator_342':28}
            or child_result.get('provider_http_calls')!=0 or child_result.get('supplier_calls')!=0
            or child_result.get('database_writes')!=0 or child_result.get('mapping_writes')!=0
            or child_result.get('safe_to_write_now') is not False):
        fail('match_common4_current_v2_authority_guard')
    if receipt.get('provider_http_calls')!=0 or receipt.get('database_writes')!=0 or receipt.get('mapping_writes')!=0:
        fail('match_common4_current_v2_receipt_guard')
    keys=('input_single_native_count','input_namespace_counts','unique_targets','status_counts',
          'namespace_status_counts','anchor_state_counts','writer_ready_count')
    summary={k:child_result.get(k) for k in keys}
    return {'child_operation':child,'result_sha256':digest,'result_bytes':result_path.stat().st_size,
            'receipt_state':receipt.get('state'),'summary':summary,
            'stdout_sha256':hashlib.sha256(call.stdout.encode()).hexdigest(),
            'stderr_sha256':hashlib.sha256(call.stderr.encode()).hexdigest() if call.stderr else None}


def run_match_common4_continuation_acquire(stage, offset, limit):
    plan_operation='hotel-match-live30-common4-continuation-plan-1971-20260923-v9'
    expected_plan_source='cfa5049a6f5731eaef08ae7d2ad31adff73aa1b1'
    expected_frontier=1349
    expected_frontier_hash='ce464a7b71dc72cf425197c73c1b8a4770adaf586ee05d169f4c67f8fc43ccca'
    match_root=home/'.anytoour-match/operations'
    match_root.mkdir(mode=0o700,parents=True,exist_ok=True)
    plan_dir=match_root/plan_operation
    plan_result_path=plan_dir/'result.json';plan_receipt_path=plan_dir/'receipt.json'
    if not safe_file(plan_result_path,32*1024*1024) or not safe_file(plan_receipt_path,1024*1024):
        fail('match_common4_continuation_plan_missing')
    plan_result=safe_json(plan_result_path,32*1024*1024);plan_receipt=safe_json(plan_receipt_path,1024*1024)
    plan_digest=hashlib.sha256(plan_result_path.read_bytes()).hexdigest()
    if plan_receipt.get('result_sha256')!=plan_digest: fail('match_common4_continuation_plan_hash')
    if (plan_result.get('operation')!=plan_operation
            or plan_result.get('state')!='live30_common4_continuation_ready'
            or plan_result.get('source_sha')!=expected_plan_source
            or plan_result.get('acquisition_target_count')!=expected_frontier
            or plan_result.get('acquisition_target_id_sha256')!=expected_frontier_hash
            or not isinstance(plan_result.get('rows'),list)
            or len(plan_result['rows'])!=expected_frontier
            or plan_result.get('provider_http_calls')!=0
            or plan_result.get('database_writes')!=0
            or plan_result.get('mapping_writes')!=0
            or plan_result.get('safe_to_write_now') is not False):
        fail('match_common4_continuation_plan_guard')
    if offset<0 or limit<1 or limit>1349 or offset+limit>expected_frontier:
        fail('match_common4_continuation_scope_guard')
    scope_rows=plan_result['rows'][offset:offset+limit]
    scope_ids=[]
    for row in scope_rows:
        if not isinstance(row,dict): fail('match_common4_continuation_scope_row')
        try: tv=int(row.get('tv_hotel_id'))
        except Exception: fail('match_common4_continuation_scope_id')
        if tv<1: fail('match_common4_continuation_scope_id')
        scope_ids.append(tv)
    expected_scope_hash=hashlib.sha256(
        json.dumps(sorted(scope_ids),ensure_ascii=False,separators=(',',':')).encode()
    ).hexdigest()

    child='hotel-match-live30-common4-continuation-acquire-1971-20260923-c'+str(offset)+'-n'+str(limit)+'-v1'
    child_dir=match_root/child
    if child_dir.exists() or child_dir.is_symlink(): fail('match_common4_continuation_child_exists_no_replay')
    child_dir.mkdir(mode=0o700)
    reservation={'operation':child,'state':'reserved_before_provider','source_sha':source,
                 'parent_operation':operation,'continuation_plan_operation':plan_operation,
                 'continuation_plan_sha256':plan_digest,'frontier_expected':expected_frontier,
                 'frontier_id_sha256':expected_frontier_hash,'offset':offset,'limit':limit,
                 'call_cap':5000,'database_writes':0,'mapping_writes':0,'reserved_at':int(time.time())}
    (child_dir/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
    os.chmod(child_dir/'reservation.json',0o600)

    runner=stage/'scripts/diagnostics/hotel_match_live30_common4_continuation_acquire_v10.py'
    if not safe_file(runner): fail('match_common4_continuation_source_missing')
    plan_path=child_dir/'plan.json'
    plan_path.write_bytes(plan_result_path.read_bytes());os.chmod(plan_path,0o600)

    run_env={**os.environ,'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child_dir),
             'MATCH_PLAN_PATH':str(plan_path),'MATCH_CHILD_OPERATION':child,
             'MATCH_OFFSET':str(offset),'MATCH_LIMIT':str(limit),'MATCH_CALL_CAP':'5000',
             'MATCH_SOURCE_SHA':source}
    call=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=run_env,
                        capture_output=True,text=True,timeout=1200)
    result_path=child_dir/'result.json';receipt_path=child_dir/'receipt.json'
    if not safe_file(result_path,32*1024*1024) or not safe_file(receipt_path,1024*1024):
        fail('match_common4_continuation_terminal_missing')
    child_result=safe_json(result_path,32*1024*1024);receipt=safe_json(receipt_path,1024*1024)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if receipt.get('result_sha256')!=digest: fail('match_common4_continuation_terminal_hash')
    if (child_result.get('continuation_plan_sha256')!=plan_digest
            or receipt.get('continuation_plan_sha256')!=plan_digest
            or child_result.get('frontier_count')!=expected_frontier
            or child_result.get('frontier_id_sha256')!=expected_frontier_hash
            or child_result.get('scope_offset')!=offset
            or child_result.get('scope_count')!=limit
            or child_result.get('scope_target_id_sha256')!=expected_scope_hash
            or child_result.get('database_writes')!=0
            or child_result.get('mapping_writes')!=0
            or child_result.get('operator_ids')!=[13,18,25,43]
            or child_result.get('continue_calls')!=0
            or child_result.get('dates_calls')!=0):
        fail('match_common4_continuation_terminal_guard')
    calls=child_result.get('provider_calls')
    if not isinstance(calls,int) or calls<0 or calls>5000: fail('match_common4_continuation_call_cap_guard')
    allowed={'completed_read_only','terminal_quota_stop_no_replay','terminal_day_changed_no_replay'}
    if call.returncode!=0 or child_result.get('state') not in allowed:
        fail('match_common4_continuation_terminal_nonzero_no_replay')
    summary={k:v for k,v in child_result.items() if k not in ('edges','batches')}
    return {'child_operation':child,'scope_offset':offset,'scope_count':limit,
            'state':child_result.get('state'),'result_sha256':digest,
            'continuation_plan_sha256':plan_digest,'scope_target_id_sha256':expected_scope_hash,
            'summary':summary,
            'provider_stdout_sha256':hashlib.sha256(call.stdout.encode()).hexdigest(),
            'provider_stderr_sha256':hashlib.sha256(call.stderr.encode()).hexdigest() if call.stderr else None}


def read_match_common4_resume_day():
    root=home/'.anytoour-match/operations'
    prefix='hotel-match-live30-common4-continuation-resume-1971-20260924-r1-n'
    matches=[p for p in root.iterdir() if p.is_dir() and not p.is_symlink() and p.name.startswith(prefix) and p.name.endswith('-v1')]
    if len(matches)!=1: fail('match_common4_resume_readback_directory_count')
    directory=matches[0]
    reservation_path=directory/'reservation.json'
    if not safe_file(reservation_path,1024*1024): fail('match_common4_resume_readback_reservation_missing')
    reservation=safe_json(reservation_path,1024*1024)
    out={'child_operation':directory.name,'reservation':{k:reservation.get(k) for k in (
        'state','source_sha','resume_from_operation','resume_previous_result_sha256','resume_plan_sha256',
        'attempted_search_groups','attempted_hotel_count','remaining_count','call_cap')}}
    requests=sorted(directory.glob('tv-request-*.json'))
    responses=sorted(directory.glob('tv-response-*.json'))
    batches=sorted(directory.glob('tv-batch-*-result.json'))
    action_counts={}
    for path in requests:
        if not safe_file(path,1024*1024): continue
        value=safe_json(path,1024*1024);action=str(value.get('action') or 'unknown')
        action_counts[action]=action_counts.get(action,0)+1
    out['progress']={'request_files':len(requests),'response_files':len(responses),'completed_batch_files':len(batches),'action_counts':action_counts}
    result_path=directory/'result.json';receipt_path=directory/'receipt.json'
    if safe_file(result_path,32*1024*1024) and safe_file(receipt_path,1024*1024):
        raw=result_path.read_bytes();digest=hashlib.sha256(raw).hexdigest()
        result=safe_json(result_path,32*1024*1024);receipt=safe_json(receipt_path,1024*1024)
        if receipt.get('result_sha256')!=digest: fail('match_common4_resume_readback_hash')
        out['terminal']=True;out['result_sha256']=digest;out['receipt_state']=receipt.get('state')
        out['result_summary']={k:result.get(k) for k in (
            'state','reason','frontier_count','scope_count','searched_hotels','planned_groups','completed_batches',
            'returned_targets','returned_missing_operator_pairs','returned_operator_pairs','provider_calls',
            'operation_tariff_units','daily_tariff_units_after_local_ledger','physical_http_attempts',
            'tourvisor_account','single_native_chunk_unique_count','single_native_by_operator',
            'incomplete_status_batches','database_writes','mapping_writes')}
    else:
        out['terminal']=False
    return out


def read_match_common4_continuation_resume():
    match_root=home/'.anytoour-match/operations'
    candidates=sorted([
        p for p in match_root.glob('hotel-match-live30-common4-continuation-resume-1971-20260924-r1-n*-v1')
        if p.is_dir() and not p.is_symlink()
    ])
    if len(candidates)!=1: fail('match_common4_resume_readback_child_count')
    child_dir=candidates[0];child=child_dir.name
    reservation_path=child_dir/'reservation.json';plan_path=child_dir/'plan.json'
    if not safe_file(reservation_path,1024*1024) or not safe_file(plan_path,32*1024*1024):
        fail('match_common4_resume_readback_inputs')
    reservation=safe_json(reservation_path,1024*1024);plan=safe_json(plan_path,32*1024*1024)
    remaining_expected=int(reservation.get('remaining_count',0) or 0)
    rows=plan.get('rows')
    if (reservation.get('operation')!=child or remaining_expected<1
            or not isinstance(rows,list) or len(rows)!=remaining_expected):
        fail('match_common4_resume_readback_reservation')
    plan_ids=[]
    for row in rows:
        if not isinstance(row,dict): fail('match_common4_resume_readback_plan_row')
        try: tv=int(row.get('tv_hotel_id'))
        except Exception: fail('match_common4_resume_readback_plan_id')
        if tv<1: fail('match_common4_resume_readback_plan_id')
        plan_ids.append(tv)
    if len(set(plan_ids))!=len(plan_ids): fail('match_common4_resume_readback_plan_duplicate')
    plan_set=set(plan_ids)

    search_files=sorted(child_dir.glob('tv-request-*.json'))
    starts=[];started=set();physical=0;actions={}
    for path in search_files:
        if not safe_file(path,2*1024*1024): fail('match_common4_resume_readback_request_file')
        q=safe_json(path,2*1024*1024);physical+=1
        action=str(q.get('action',''));actions[action]=actions.get(action,0)+1
        if action!='search_start': continue
        params=q.get('params')
        hotel_ids=params.get('hotelIds') if isinstance(params,dict) else None
        if not isinstance(hotel_ids,list) or not hotel_ids: fail('match_common4_resume_readback_start_shape')
        one=[]
        for raw in hotel_ids:
            try: tv=int(raw)
            except Exception: fail('match_common4_resume_readback_start_id')
            if tv<1 or tv not in plan_set: fail('match_common4_resume_readback_start_membership')
            one.append(tv)
        if len(one)!=len(set(one)): fail('match_common4_resume_readback_start_duplicate')
        overlap=started.intersection(one)
        if overlap: fail('match_common4_resume_readback_cross_start_duplicate')
        started.update(one)
        starts.append(one)
    started_ids=sorted(started)
    remaining_ids=sorted(plan_set-started)
    completed_batches=len(list(child_dir.glob('tv-batch-*-result.json')))
    reserved_batches=len(list(child_dir.glob('tv-batch-*-reservation.json')))

    terminal=None;result_sha=None;receipt_state=None
    result_path=child_dir/'result.json';receipt_path=child_dir/'receipt.json'
    if result_path.exists() or receipt_path.exists():
        if not safe_file(result_path,32*1024*1024) or not safe_file(receipt_path,1024*1024):
            fail('match_common4_resume_readback_terminal_pair')
        raw=result_path.read_bytes();result_sha=hashlib.sha256(raw).hexdigest()
        terminal=safe_json(result_path,32*1024*1024);receipt=safe_json(receipt_path,1024*1024)
        if receipt.get('result_sha256')!=result_sha: fail('match_common4_resume_readback_terminal_hash')
        receipt_state=receipt.get('state')

    running_pids=0
    marker=('MATCH_CHILD_OPERATION='+child).encode()
    for proc in pathlib.Path('/proc').iterdir():
        if not proc.name.isdigit() or int(proc.name)==os.getpid(): continue
        try:
            env=(proc/'environ').read_bytes()
        except Exception:
            continue
        if marker in env: running_pids+=1

    return {
        'child_operation':child,
        'reservation':{
            'resume_from_operation':reservation.get('resume_from_operation'),
            'attempted_search_groups_before_resume':reservation.get('attempted_search_groups'),
            'attempted_hotel_count_before_resume':reservation.get('attempted_hotel_count'),
            'remaining_count_at_resume_start':remaining_expected,
        },
        'server_process_running':running_pids>0,
        'server_process_count':running_pids,
        'physical_request_files':physical,
        'request_action_counts':actions,
        'search_start_count':len(starts),
        'started_hotel_count':len(started_ids),
        'started_hotel_ids_sha256':hashlib.sha256(json.dumps(started_ids,separators=(',',':')).encode()).hexdigest(),
        'remaining_unstarted_hotel_count':len(remaining_ids),
        'remaining_unstarted_hotel_ids_sha256':hashlib.sha256(json.dumps(remaining_ids,separators=(',',':')).encode()).hexdigest(),
        'completed_batch_results':completed_batches,
        'reserved_batches':reserved_batches,
        'terminal_result_present':terminal is not None,
        'terminal_state':terminal.get('state') if isinstance(terminal,dict) else None,
        'terminal_result_sha256':result_sha,
        'receipt_state':receipt_state,
        'terminal_summary':(
            {k:terminal.get(k) for k in (
                'state','reason','searched_hotels','provider_calls','physical_http_attempts',
                'operation_tariff_units','daily_tariff_units_after_local_ledger',
                'returned_targets','returned_operator_pairs','single_native_chunk_unique_count',
                'single_native_by_operator','tourvisor_account','provider_day'
            )} if isinstance(terminal,dict) else None
        ),
        'supplier_calls':0,'database_writes':0,'mapping_writes':0,
    }


def run_match_common4_continuation_resume_day(stage):
    previous='hotel-match-live30-common4-continuation-acquire-1971-20260923-c135-n1214-v1'
    match_root=home/'.anytoour-match/operations'
    prev_dir=match_root/previous
    result_path=prev_dir/'result.json';receipt_path=prev_dir/'receipt.json';tv_plan_path=prev_dir/'tv-plan.json'
    if not safe_file(result_path,32*1024*1024) or not safe_file(receipt_path,1024*1024) or not safe_file(tv_plan_path,32*1024*1024):
        fail('match_common4_resume_previous_missing')
    prior=safe_json(result_path,32*1024*1024);receipt=safe_json(receipt_path,1024*1024);tv_plan=safe_json(tv_plan_path,32*1024*1024)
    prior_digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if receipt.get('result_sha256')!=prior_digest: fail('match_common4_resume_previous_hash')
    if (prior.get('operation')!=previous or prior.get('state')!='terminal_day_changed_no_replay'
            or prior.get('tourvisor_account')!='TOURVISOR_ANEX_JWT'
            or prior.get('scope_offset')!=135 or prior.get('scope_count')!=1214
            or prior.get('database_writes')!=0 or prior.get('mapping_writes')!=0):
        fail('match_common4_resume_previous_guard')
    starts=(prior.get('call_counts') or {}).get('search_start')
    groups=tv_plan.get('groups')
    if not isinstance(starts,int) or starts<1 or not isinstance(groups,list) or starts>len(groups):
        fail('match_common4_resume_group_count')
    attempted=[]
    for group in groups[:starts]:
        if not isinstance(group,dict) or not isinstance(group.get('hotel_ids'),list) or not group['hotel_ids']:
            fail('match_common4_resume_group_shape')
        for raw in group['hotel_ids']:
            try: tv=int(raw)
            except Exception: fail('match_common4_resume_group_id')
            if tv<1: fail('match_common4_resume_group_id')
            attempted.append(tv)
    attempted_set=set(attempted)
    if len(attempted_set)!=len(attempted): fail('match_common4_resume_group_overlap')

    plan_operation='hotel-match-live30-common4-continuation-plan-1971-20260923-v9'
    plan_dir=match_root/plan_operation
    plan_result_path=plan_dir/'result.json';plan_receipt_path=plan_dir/'receipt.json'
    if not safe_file(plan_result_path,32*1024*1024) or not safe_file(plan_receipt_path,1024*1024):
        fail('match_common4_resume_plan_missing')
    plan=safe_json(plan_result_path,32*1024*1024);plan_receipt=safe_json(plan_receipt_path,1024*1024)
    plan_digest=hashlib.sha256(plan_result_path.read_bytes()).hexdigest()
    if plan_receipt.get('result_sha256')!=plan_digest: fail('match_common4_resume_plan_hash')
    rows=plan.get('rows')
    if (plan.get('state')!='live30_common4_continuation_ready' or plan.get('acquisition_target_count')!=1349
            or not isinstance(rows,list) or len(rows)!=1349 or plan.get('provider_http_calls')!=0
            or plan.get('database_writes')!=0 or plan.get('mapping_writes')!=0):
        fail('match_common4_resume_plan_guard')
    prior_scope=rows[135:1349]
    prior_scope_ids=set()
    for row in prior_scope:
        if not isinstance(row,dict): fail('match_common4_resume_plan_row')
        try: tv=int(row.get('tv_hotel_id'))
        except Exception: fail('match_common4_resume_plan_id')
        prior_scope_ids.add(tv)
    if len(prior_scope_ids)!=1214 or not attempted_set.issubset(prior_scope_ids):
        fail('match_common4_resume_membership')
    remaining_rows=[row for row in prior_scope if int(row.get('tv_hotel_id')) not in attempted_set]
    remaining_count=len(remaining_rows)
    if remaining_count!=1214-len(attempted_set) or remaining_count<1:
        fail('match_common4_resume_remaining_count')
    remaining_ids=sorted(int(row['tv_hotel_id']) for row in remaining_rows)
    remaining_digest=hashlib.sha256(json.dumps(remaining_ids,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
    reduced=dict(plan)
    reduced['operation']='hotel-match-live30-common4-continuation-resume-plan-1971-20260924-v1'
    reduced['source_sha']=source
    reduced['acquisition_target_count']=remaining_count
    reduced['acquisition_target_id_sha256']=remaining_digest
    reduced['rows']=remaining_rows
    reduced['resume_from_operation']=previous
    reduced['resume_attempted_search_groups']=starts
    reduced['resume_attempted_hotel_count']=len(attempted_set)
    reduced['resume_attempted_hotel_id_sha256']=hashlib.sha256(json.dumps(sorted(attempted_set),separators=(',',':')).encode()).hexdigest()

    child='hotel-match-live30-common4-continuation-resume-1971-20260924-r1-n'+str(remaining_count)+'-v1'
    child_dir=match_root/child
    if child_dir.exists() or child_dir.is_symlink(): fail('match_common4_resume_child_exists_no_replay')
    child_dir.mkdir(mode=0o700)
    plan_path=child_dir/'plan.json'
    plan_path.write_text(json.dumps(reduced,ensure_ascii=False,separators=(',',':')))
    os.chmod(plan_path,0o600)
    reduced_digest=hashlib.sha256(plan_path.read_bytes()).hexdigest()
    reservation={'operation':child,'state':'reserved_before_provider','source_sha':source,
                 'parent_operation':operation,'resume_from_operation':previous,
                 'resume_previous_result_sha256':prior_digest,'resume_plan_sha256':reduced_digest,
                 'attempted_search_groups':starts,'attempted_hotel_count':len(attempted_set),
                 'remaining_count':remaining_count,'call_cap':5000,'database_writes':0,'mapping_writes':0,
                 'reserved_at':int(time.time())}
    (child_dir/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
    os.chmod(child_dir/'reservation.json',0o600)

    runner=stage/'scripts/diagnostics/hotel_match_live30_common4_continuation_acquire_v10.py'
    if not safe_file(runner): fail('match_common4_resume_source_missing')
    run_env={**os.environ,'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child_dir),
             'MATCH_PLAN_PATH':str(plan_path),'MATCH_CHILD_OPERATION':child,
             'MATCH_OFFSET':'0','MATCH_LIMIT':str(remaining_count),'MATCH_CALL_CAP':'5000',
             'MATCH_SOURCE_SHA':source}
    call=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=run_env,
                        capture_output=True,text=True,timeout=1200)
    child_result_path=child_dir/'result.json';child_receipt_path=child_dir/'receipt.json'
    if not safe_file(child_result_path,32*1024*1024) or not safe_file(child_receipt_path,1024*1024):
        fail('match_common4_resume_terminal_missing')
    child_result=safe_json(child_result_path,32*1024*1024);child_receipt=safe_json(child_receipt_path,1024*1024)
    digest=hashlib.sha256(child_result_path.read_bytes()).hexdigest()
    if child_receipt.get('result_sha256')!=digest: fail('match_common4_resume_terminal_hash')
    if (child_result.get('continuation_plan_sha256')!=reduced_digest
            or child_result.get('frontier_count')!=remaining_count
            or child_result.get('frontier_id_sha256')!=remaining_digest
            or child_result.get('scope_offset')!=0 or child_result.get('scope_count')!=remaining_count
            or child_result.get('tourvisor_account')!='TOURVISOR_ANEX_JWT'
            or child_result.get('database_writes')!=0 or child_result.get('mapping_writes')!=0
            or child_result.get('operator_ids')!=[13,18,25,43]
            or child_result.get('continue_calls')!=0 or child_result.get('dates_calls')!=0):
        fail('match_common4_resume_terminal_guard')
    calls=child_result.get('provider_calls')
    if not isinstance(calls,int) or calls<0 or calls>5000: fail('match_common4_resume_call_cap_guard')
    allowed={'completed_read_only','terminal_quota_stop_no_replay','terminal_day_changed_no_replay'}
    if call.returncode!=0 or child_result.get('state') not in allowed:
        fail('match_common4_resume_terminal_nonzero_no_replay')
    summary={k:v for k,v in child_result.items() if k not in ('edges','batches')}
    summary['resume_attempted_search_groups']=starts
    summary['resume_attempted_hotel_count']=len(attempted_set)
    summary['resume_remaining_count']=remaining_count
    return {'child_operation':child,'state':child_result.get('state'),'result_sha256':digest,
            'resume_from_operation':previous,'resume_previous_result_sha256':prior_digest,
            'attempted_search_groups':starts,'attempted_hotel_count':len(attempted_set),
            'remaining_count':remaining_count,'remaining_target_id_sha256':remaining_digest,
            'summary':summary,
            'provider_stdout_sha256':hashlib.sha256(call.stdout.encode()).hexdigest(),
            'provider_stderr_sha256':hashlib.sha256(call.stderr.encode()).hexdigest() if call.stderr else None}


def run_match_common4_acquire(stage, offset, limit):
    child='hotel-match-live30-common4-acquire-1971-20260923-o'+str(offset)+'-n'+str(limit)+'-v1'
    match_root=home/'.anytoour-match/operations'
    match_root.mkdir(mode=0o700,parents=True,exist_ok=True)
    child_dir=match_root/child
    if child_dir.exists() or child_dir.is_symlink(): fail('match_common4_child_exists_no_replay')
    child_dir.mkdir(mode=0o700)
    reservation={'operation':child,'state':'reserved_before_db_and_provider','source_sha':source,
                 'parent_operation':operation,'frontier_expected':1799,'offset':offset,'limit':limit,
                 'call_cap':900,'database_writes':0,'mapping_writes':0,'reserved_at':int(time.time())}
    (child_dir/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
    os.chmod(child_dir/'reservation.json',0o600)

    planner=stage/'scripts/diagnostics/hotel_match_live30_common4_plan_v1.php'
    runner=stage/'scripts/diagnostics/hotel_match_live30_common4_acquire_v1.py'
    matrix=stage/'scripts/diagnostics/hotel_match_live30_common4_gap_matrix_v1.php'
    helper=stage/'scripts/diagnostics/hotel_match_anex_effective_coverage.php'
    for path in (planner,runner,matrix,helper):
        if not safe_file(path): fail('match_common4_source_missing')

    env={**os.environ,'ANYTOUR_ROOT':str(project)}
    planned=subprocess.run(['php',str(planner),'--execute'],cwd=project,env=env,
                           capture_output=True,text=True,timeout=120)
    if planned.returncode or planned.stderr.strip(): fail('match_common4_plan_failed')
    try: plan=json.loads(planned.stdout)
    except Exception: fail('match_common4_plan_unparseable')
    if (plan.get('state')!='live30_common4_ready' or plan.get('frontier_count')!=1799
            or plan.get('expected_frontier')!=1799
            or not isinstance(plan.get('rows'),list) or len(plan['rows'])!=1799
            or plan.get('operator_ids')!=[13,18,25,43]
            or plan.get('provider_http_calls')!=0 or plan.get('database_writes')!=0
            or plan.get('mapping_writes')!=0):
        fail('match_common4_plan_guard')

    plan_path=child_dir/'plan.json'
    plan_path.write_text(json.dumps(plan,ensure_ascii=False,separators=(',',':')))
    os.chmod(plan_path,0o600)
    run_env={**os.environ,'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child_dir),
             'MATCH_PLAN_PATH':str(plan_path),'MATCH_CHILD_OPERATION':child,
             'MATCH_OFFSET':str(offset),'MATCH_LIMIT':str(limit),'MATCH_CALL_CAP':'900',
             'MATCH_SOURCE_SHA':source}
    call=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=run_env,
                        capture_output=True,text=True,timeout=1200)
    result_path=child_dir/'result.json';receipt_path=child_dir/'receipt.json'
    if not safe_file(result_path,32*1024*1024) or not safe_file(receipt_path,1024*1024):
        fail('match_common4_terminal_missing')
    child_result=safe_json(result_path,32*1024*1024);receipt=safe_json(receipt_path,1024*1024)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if receipt.get('result_sha256')!=digest: fail('match_common4_terminal_hash')
    if (child_result.get('frontier_count')!=1799 or child_result.get('scope_offset')!=offset
            or child_result.get('scope_count')!=limit or child_result.get('database_writes')!=0
            or child_result.get('mapping_writes')!=0 or child_result.get('operator_ids')!=[13,18,25,43]
            or child_result.get('continue_calls')!=0 or child_result.get('dates_calls')!=0):
        fail('match_common4_terminal_guard')
    calls=child_result.get('provider_calls')
    if not isinstance(calls,int) or calls<0 or calls>900: fail('match_common4_call_cap_guard')
    allowed={'completed_read_only','terminal_quota_stop_no_replay','terminal_day_changed_no_replay'}
    if call.returncode!=0 or child_result.get('state') not in allowed:
        fail('match_common4_terminal_nonzero_no_replay')
    summary={k:v for k,v in child_result.items() if k not in ('edges','batches')}
    return {'child_operation':child,'scope_offset':offset,'scope_count':limit,
            'state':child_result.get('state'),'result_sha256':digest,'summary':summary,
            'provider_stdout_sha256':hashlib.sha256(call.stdout.encode()).hexdigest(),
            'provider_stderr_sha256':hashlib.sha256(call.stderr.encode()).hexdigest() if call.stderr else None}

def run_match_tv234_secondary(stage, offset, limit):
    child='hotel-match-live234-tv-secondary-1971-20260923-o'+str(offset)+'-n'+str(limit)+'-v1'
    match_root=home/'.anytoour-match/operations'
    match_root.mkdir(mode=0o700,parents=True,exist_ok=True)
    child_dir=match_root/child
    if child_dir.exists() or child_dir.is_symlink(): fail('match_tv234_child_exists_no_replay')
    child_dir.mkdir(mode=0o700)
    reservation={'operation':child,'state':'reserved_before_db_and_provider','source_sha':source,
                 'parent_operation':operation,'frontier_expected':234,'offset':offset,'limit':limit,
                 'database_writes':0,'mapping_writes':0,'reserved_at':int(time.time())}
    (child_dir/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
    os.chmod(child_dir/'reservation.json',0o600)
    planner=stage/'scripts/diagnostics/hotel_match_live234_frontier_plan_v1.php'
    runner=stage/'scripts/diagnostics/hotel_match_live234_tv_secondary_refresh_v1.py'
    helper=stage/'scripts/diagnostics/hotel_match_anex_effective_coverage.php'
    if not safe_file(planner) or not safe_file(runner) or not safe_file(helper):
        fail('match_tv234_source_missing')
    env={**os.environ,'ANYTOUR_ROOT':str(project)}
    planned=subprocess.run(['php',str(planner),'--execute'],cwd=project,env=env,
                           capture_output=True,text=True,timeout=90)
    if planned.returncode or planned.stderr.strip(): fail('match_tv234_plan_failed')
    try: plan=json.loads(planned.stdout)
    except Exception: fail('match_tv234_plan_unparseable')
    if (plan.get('state')!='live234_ready' or plan.get('frontier_count')!=234
            or not isinstance(plan.get('rows'),list) or len(plan['rows'])!=234
            or plan.get('operator_ids')!=[18,25,43]):
        fail('match_tv234_plan_guard')
    plan_path=child_dir/'plan.json'
    plan_path.write_text(json.dumps(plan,ensure_ascii=False,separators=(',',':')))
    os.chmod(plan_path,0o600)
    run_env={**os.environ,'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child_dir),
             'MATCH_PLAN_PATH':str(plan_path),'MATCH_CHILD_OPERATION':child,
             'MATCH_OFFSET':str(offset),'MATCH_LIMIT':str(limit),'MATCH_SOURCE_SHA':source}
    call=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=run_env,
                        capture_output=True,text=True,timeout=900)
    result_path=child_dir/'result.json';receipt_path=child_dir/'receipt.json'
    if not safe_file(result_path,16*1024*1024) or not safe_file(receipt_path,1024*1024):
        fail('match_tv234_terminal_missing')
    child_result=safe_json(result_path,16*1024*1024);receipt=safe_json(receipt_path,1024*1024)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if receipt.get('result_sha256')!=digest: fail('match_tv234_terminal_hash')
    if (child_result.get('frontier_count')!=234 or child_result.get('scope_offset')!=offset
            or child_result.get('scope_count')!=limit or child_result.get('database_writes')!=0
            or child_result.get('mapping_writes')!=0 or child_result.get('operator_ids')!=[18,25,43]):
        fail('match_tv234_terminal_guard')
    allowed={'completed_read_only','terminal_quota_stop_no_replay','terminal_day_changed_no_replay'}
    if call.returncode!=0 or child_result.get('state') not in allowed:
        fail('match_tv234_terminal_nonzero_no_replay')
    summary={k:v for k,v in child_result.items() if k not in ('edges','batches')}
    return {'child_operation':child,'scope_offset':offset,'scope_count':limit,
            'state':child_result.get('state'),'result_sha256':digest,'summary':summary,
            'provider_stdout_sha256':hashlib.sha256(call.stdout.encode()).hexdigest(),
            'provider_stderr_sha256':hashlib.sha256(call.stderr.encode()).hexdigest() if call.stderr else None}

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
    if mode in ('install-runtime','install-anex-preview','install-andromeda-preview','install-andromeda-quote-preview'):
        if mode=='install-anex-preview':
            result['install']=install_anex_preview(stage,files,op)
        elif mode=='install-andromeda-preview':
            result['install']=install_andromeda_preview(stage,files,op)
        elif mode=='install-andromeda-quote-preview':
            result['install']=install_andromeda_quote_preview(stage,files,op)
        else:
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
    if mode=='funsun-direction-fx-seed':
        result['before_db']=db_summary('andromeda')
        result['direction_fx_seed']=funsun_direction_fx_seed()
        result['after_db']=db_summary('andromeda')
        if result['after_db']!=result['before_db']: fail('direction_fx_seed_db_drift')
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=0
        result['database_writes']=0
        result['private_evidence_writes']=1 if result['direction_fx_seed']['write_state']=='created' else 0
        result['production_unchanged']=True
    if mode=='operator-direction-fuel-readback':
        result['before_db']=db_summary('andromeda')
        result['operator_direction_fuel_readback']=operator_direction_fuel_readback()
        result['after_db']=db_summary('andromeda')
        if result['after_db']!=result['before_db']: fail('direction_fuel_readback_db_drift')
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
    if mode=='match-coverage-v2':
        result['match_coverage_v2']=run_match_coverage_v2(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='match-coverage-v2-readback':
        result['match_coverage_v2_readback']=read_match_coverage_v2()
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='reconciled_read_only'
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
    if mode=='match-tv234-readback':
        result['match_tv234_readback']=read_match_tv234_secondary(int(payload['offset']),int(payload['limit']))
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='reconciled_read_only'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='match-common4-readback':
        result['match_common4_readback']=read_match_common4(int(payload['offset']),int(payload['limit']))
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='reconciled_read_only'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='match-common4-current-v2':
        result['match_common4_current_v2']=run_match_common4_current_v2(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='match-common4-acquire':
        result['match_common4_acquire']=run_match_common4_acquire(stage,int(payload['offset']),int(payload['limit']))
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=result['match_common4_acquire']['summary'].get('provider_calls','bounded')
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='match-common4-continuation-acquire':
        result['match_common4_continuation_acquire']=run_match_common4_continuation_acquire(stage,int(payload['offset']),int(payload['limit']))
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=result['match_common4_continuation_acquire']['summary'].get('provider_calls','bounded')
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='match-common4-continuation-resume-day':
        result['match_common4_continuation_resume_day']=run_match_common4_continuation_resume_day(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=result['match_common4_continuation_resume_day']['summary'].get('provider_calls','bounded')
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='match-common4-continuation-resume-readback':
        result['match_common4_continuation_resume_readback']=read_match_common4_continuation_resume()
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='reconciled_read_only'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='match-common4-resume-readback':
        result['match_common4_resume_readback']=read_match_common4_resume_day()
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='reconciled_read_only'
        result['supplier_calls']=0
        result['database_writes']=0
        result['production_unchanged']=True
    if mode=='match-tv234-secondary':
        result['match_tv234_secondary']=run_match_tv234_secondary(stage,int(payload['offset']),int(payload['limit']))
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['status']='complete'
        result['supplier_calls']=result['match_tv234_secondary']['summary'].get('provider_calls','bounded')
        result['database_writes']=0
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
    if mode not in ('reconcile','local-readback','program-fuel-readback','program-fuel-probe','funsun-direction-fuel-seed','funsun-direction-fx-seed','operator-direction-fuel-readback','install-runtime','install-anex-preview','install-andromeda-preview','install-andromeda-quote-preview','match-coverage','match-coverage-v2','match-coverage-v2-readback','match-coverage-readback','match-tv234-readback','match-tv234-secondary','match-common4-acquire','match-common4-continuation-acquire','match-common4-continuation-resume-day','match-common4-resume-readback','match-common4-readback','match-common4-current-v2','match-readback','match-tv942-reconcile','match-tv942-write','match-tv942','match-samo942','andromeda-operator-preflight'):
        provider='anex' if mode in ('anex-demand','anex-range') else 'andromeda'
        result['before_db']=db_summary(provider)
        env={k:v for k,v in os.environ.items() if k not in ('ANEX_API_TOKEN','ANEX_B2B_TOKEN')}
        env['ANYTOUR_PROJECT_ROOT']=str(project)
        generation=str(2100000000-(int(hashlib.sha256(operation.encode()).hexdigest()[:6],16)%1000000))
    if mode=='anex-demand':
        command=['php',str(stage/'scripts/ops/anex_local_offer_demand_fill.php'),
          '--limit='+str(payload['limit']),'--lookback-hours=168','--horizon-days=21',
          '--max-expands=600','--max-apd=600','--generation-base='+generation]
    elif mode=='anex-range':
        command=['php',str(stage/'scripts/ops/anex_local_offer_collect.php'),
          '--departure='+str(payload['departure']),'--country='+str(payload['country']),
          '--date-from='+payload['date_from'],'--date-to='+payload['date_to'],
          '--nights='+str(payload['nights']),'--adults='+str(payload['adults']),
          '--child-ages='+','.join(str(x) for x in payload['child_ages']),
          '--meal='+payload['meal'],'--generation='+generation,
          '--max-expands=600','--max-apd=600']
        if payload['region']: command.append('--region='+str(payload['region']))
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
    if mode not in ('reconcile','local-readback','program-fuel-readback','program-fuel-probe','funsun-direction-fuel-seed','funsun-direction-fx-seed','operator-direction-fuel-readback','install-runtime','install-anex-preview','install-andromeda-preview','install-andromeda-quote-preview','match-coverage','match-coverage-v2','match-coverage-v2-readback','match-coverage-readback','match-tv234-readback','match-tv234-secondary','match-common4-acquire','match-common4-continuation-acquire','match-common4-continuation-resume-day','match-common4-resume-readback','match-common4-readback','match-common4-current-v2','match-readback','match-tv942-reconcile','match-tv942-write','match-tv942','match-samo942','andromeda-operator-preflight'):
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
        collector_incomplete_safe=(
            mode in ('andromeda-scope','andromeda-external-group','andromeda-operator-scope')
            and run.returncode!=0 and parseable
            and collector.get('source')=='andromeda-local-offer-collector-v1'
            and collector.get('status')=='incomplete'
            and collector.get('incomplete_reason')=='search_partial'
            and isinstance(collector.get('pages'),int) and collector['pages']>=1
            and isinstance(collector.get('advertised_pages'),int)
            and collector['advertised_pages']>collector['pages']
            and isinstance(collector.get('received_offers'),int) and collector['received_offers']>=0
            and isinstance(collector.get('mapped_offers'),int) and collector['mapped_offers']>=0
            and collector.get('autosave_published') is False
            and collector.get('autosave_reason')=='cohort_incomplete'
            and collector.get('autosave')=={'published':False,'reason':'cohort_incomplete'}
            and collector.get('selection_authority') is False
            and collector.get('booking_calls')==0
            and result['after_db']==result['before_db']
        )
        if run.returncode==0 and parseable:
            if mode=='anex-demand':
                scopes=[x.get('scope',{}) for x in collector.get('results',[])
                        if isinstance(x,dict) and isinstance(x.get('scope'),dict)]
            elif mode=='anex-range':
                scopes=[{'departureId':payload['departure'],'countryId':payload['country'],
                         'regionId':payload['region'] or None,'dateFrom':payload['date_from'],
                         'dateTo':payload['date_to'],'nights':payload['nights'],
                         'adults':payload['adults'],'childAges':payload['child_ages']}]
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
    if mode in ('install-runtime','install-anex-preview','install-andromeda-preview') and install_started:
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
    if command['mode'] == 'match-common4-continuation-remainder':
        stage='/tmp/' + command['operation_id'] + '-source'
        q=shlex.quote
        remote_script=(
            'set -eu; umask 077; '
            'stage='+q(stage)+'; rm -rf "$stage"; mkdir -p "$stage"; '
            'tar -xzf '+q(remote_archive)+' -C "$stage"; '
            'root="$HOME/www/anytoour.ru"; '
            'before="$(sha256sum "$root/index.php" | awk \'{print $1}\')"; '
            'cd "$root"; '
            'ANYTOUR_ROOT="$root" MATCH_OPERATIONS_ROOT="$HOME/.anytoour-match/operations" '
            'MATCH_PARENT_OPERATION='+q(command['operation_id'])+' '
            'MATCH_SOURCE_SHA='+q(command['source_sha'])+' '
            'MATCH_LIMIT='+q(str(command['limit']))+' '
            'python3 "$stage/scripts/diagnostics/hotel_match_live30_common4_remainder_v1.py" --execute >"$stage/result.out"; '
            'after="$(sha256sum "$root/index.php" | awk \'{print $1}\')"; test "$before" = "$after"; '
            'cat "$stage/result.out"'
        )
        try:
            run=subprocess.run(['ssh',*options,'-l',user,host,remote_script],
                               capture_output=True,text=True,timeout=1350)
            need(run.returncode==0,'match_common4_remainder_remote_exit')
            out=json.loads(run.stdout.strip())
            need(isinstance(out,dict),'match_common4_remainder_output_shape')
            state=out.get('state')
            if state=='nothing_remaining':
                need(out.get('provider_calls')==0 and out.get('database_writes')==0
                     and out.get('mapping_writes')==0 and out.get('remaining_before')==0,
                     'match_common4_remainder_empty_guard')
                supplier_calls=0
            else:
                need(state in ('completed_read_only','terminal_quota_stop_no_replay','terminal_day_changed_no_replay'),
                     'match_common4_remainder_state')
                need(isinstance(out.get('selected_count'),int) and 1<=out['selected_count']<=command['limit'],
                     'match_common4_remainder_selected_count')
                summary=out.get('summary')
                need(isinstance(summary,dict) and summary.get('tourvisor_account')=='TOURVISOR_ANEX_JWT'
                     and summary.get('database_writes')==0 and summary.get('mapping_writes')==0
                     and summary.get('operator_ids')==[13,18,25,43]
                     and summary.get('continue_calls')==0 and summary.get('dates_calls')==0,
                     'match_common4_remainder_summary_guard')
                supplier_calls=summary.get('provider_calls',0)
            result={'schema_version':1,'operation_id':command['operation_id'],'source_sha':command['source_sha'],
                    'mode':command['mode'],'status':'complete','match_common4_continuation_remainder':out,
                    'supplier_calls':supplier_calls,'database_writes':0,'booking_calls':0,'lead_calls':0,
                    'production_unchanged':True}
            (output/'result.json').write_text(json.dumps(result,sort_keys=True,indent=2)+'\n')
            return result
        finally:
            subprocess.run(['ssh',*options,'-l',user,host,'rm -rf -- '+q(stage)+'; rm -f -- '+q(remote_archive)],
                           stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,timeout=30)
            key.unlink(missing_ok=True);known.unlink(missing_ok=True)

    if command['mode'] == 'match-common4-resume-salvage':
        stage='/tmp/' + command['operation_id'] + '-source'
        q=shlex.quote
        remote_script=(
            'set -eu; umask 077; '
            'stage='+q(stage)+'; rm -rf "$stage"; mkdir -p "$stage"; '
            'tar -xzf '+q(remote_archive)+' -C "$stage"; '
            'root="$HOME/www/anytoour.ru"; before="$(sha256sum "$root/index.php" | awk \'{print $1}\')"; '
            'MATCH_OPERATIONS_ROOT="$HOME/.anytoour-match/operations" MATCH_SOURCE_SHA='+q(command['source_sha'])+' '
            'python3 "$stage/scripts/diagnostics/hotel_match_common4_resume_salvage_v1.py" --execute >"$stage/salvage.out"; '
            'after="$(sha256sum "$root/index.php" | awk \'{print $1}\')"; test "$before" = "$after"; cat "$stage/salvage.out"'
        )
        try:
            run=subprocess.run(['ssh',*options,'-l',user,host,remote_script],
                               capture_output=True,text=True,timeout=180)
            need(run.returncode==0,'match_common4_salvage_remote_exit')
            out=json.loads(run.stdout.strip());need(isinstance(out,dict),'match_common4_salvage_output')
            need(out.get('state')=='terminal_wrapper_timeout_salvaged_no_replay'
                 and out.get('searched_hotels')==761 and out.get('unstarted_hotel_count')==138,
                 'match_common4_salvage_guard')
            result={'schema_version':1,'operation_id':command['operation_id'],'source_sha':command['source_sha'],
                    'mode':command['mode'],'status':'complete','match_common4_resume_salvage':out,
                    'supplier_calls':0,'database_writes':0,'booking_calls':0,'lead_calls':0,'production_unchanged':True}
            (output/'result.json').write_text(json.dumps(result,sort_keys=True,indent=2)+'\n')
            return result
        finally:
            subprocess.run(['ssh',*options,'-l',user,host,'rm -rf -- '+q(stage)+'; rm -f -- '+q(remote_archive)],
                           stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,timeout=30)
            key.unlink(missing_ok=True);known.unlink(missing_ok=True)

    if command['mode'] == 'match-common4-mass-current':
        stage='/tmp/' + command['operation_id'] + '-source'
        q=shlex.quote
        audit='hotel-match-common4-mass-current-1971-20260924-v14'
        children=[
          'hotel-match-live30-common4-continuation-acquire-1971-20260923-c35-n100-v1',
          'hotel-match-live30-common4-continuation-acquire-1971-20260923-c135-n1214-v1',
          'hotel-match-live30-common4-continuation-resume-1971-20260924-r1-n899-v1',
          'hotel-match-live30-common4-continuation-resume-1971-20260924-r2-n138-v1',
        ]
        manifest_code=(
          "import hashlib,json,pathlib,sys;"
          "root=pathlib.Path.home()/'.anytoour-match/operations';"
          "names=json.loads(sys.argv[1]);rows=[];"
          "\nfor n in names:"
          "\n p=root/n/'result.json';q=root/n/'receipt.json';"
          "\n assert p.is_file() and q.is_file();raw=p.read_bytes();sha=hashlib.sha256(raw).hexdigest();"
          "\n receipt=json.loads(q.read_text());assert receipt.get('result_sha256')==sha;"
          "\n rows.append({'operation':n,'result_sha256':sha});"
          "\nprint(json.dumps({'children':rows},separators=(',',':')))"
        )
        names_json=json.dumps(children,separators=(',',':'))
        remote_script=(
            'set -eu; umask 077; stage='+q(stage)+'; rm -rf "$stage"; mkdir -p "$stage"; '
            'tar -xzf '+q(remote_archive)+' -C "$stage"; root="$HOME/www/anytoour.ru"; '
            'ops="$HOME/.anytoour-match/operations"; audit="$ops/'+audit+'"; test ! -e "$audit"; mkdir -m 700 "$audit"; '
            'printf "%s\\n" '+q(json.dumps({'operation':audit,'state':'reserved_before_db_read'},separators=(',',':')))+' >"$audit/reservation.json"; chmod 600 "$audit/reservation.json"; '
            'python3 -c '+q(manifest_code)+' '+q(names_json)+' >"$audit/manifest.json"; chmod 600 "$audit/manifest.json"; '
            'before="$(sha256sum "$root/index.php" | awk \'{print $1}\')"; '
            'ANYTOUR_ROOT="$root" MATCH_OPERATION_DIR="$audit" MATCH_OPERATIONS_ROOT="$ops" MATCH_CHILD_MANIFEST="$audit/manifest.json" MATCH_SOURCE_SHA='+q(command['source_sha'])+' '
            'php "$stage/scripts/diagnostics/hotel_match_common4_mass_current_v14.php" --execute >"$audit/stdout.txt"; '
            'after="$(sha256sum "$root/index.php" | awk \'{print $1}\')"; test "$before" = "$after"; '
            'python3 -c '+q(
              "import json,pathlib;d=pathlib.Path.home()/'.anytoour-match/operations'/"+repr(audit)+
              ";r=json.loads((d/'result.json').read_text());"
              "print(json.dumps({k:r.get(k) for k in ('operation','state','searched_hotels','input_single_native_edges','status_counts','anchor_state_counts','writer_ready_counts','supplier_calls','provider_http_calls','database_writes','mapping_writes')},separators=(',',':')))"
            )
        )
        try:
            run=subprocess.run(['ssh',*options,'-l',user,host,remote_script],
                               capture_output=True,text=True,timeout=300)
            need(run.returncode==0,'match_common4_mass_current_remote_exit')
            out=json.loads(run.stdout.strip());need(isinstance(out,dict),'match_common4_mass_current_output')
            need(out.get('state')=='completed_read_only_mass_current'
                 and out.get('supplier_calls')==0 and out.get('provider_http_calls')==0
                 and out.get('database_writes')==0 and out.get('mapping_writes')==0,
                 'match_common4_mass_current_guard')
            result={'schema_version':1,'operation_id':command['operation_id'],'source_sha':command['source_sha'],
                    'mode':command['mode'],'status':'complete','match_common4_mass_current':out,
                    'supplier_calls':0,'database_writes':0,'booking_calls':0,'lead_calls':0,'production_unchanged':True}
            (output/'result.json').write_text(json.dumps(result,sort_keys=True,indent=2)+'\n')
            return result
        finally:
            subprocess.run(['ssh',*options,'-l',user,host,'rm -rf -- '+q(stage)+'; rm -f -- '+q(remote_archive)],
                           stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,timeout=30)
            key.unlink(missing_ok=True);known.unlink(missing_ok=True)

    compressed = zlib.compress(REMOTE.encode(), 9)
    encoded = base64.b64encode(compressed).decode()
    remote_command = (
        "python3 -c 'import base64,zlib;exec(zlib.decompress(base64.b64decode(\"" + encoded + "\")))'"
    )
    need(len(remote_command.encode()) <= 65536, 'remote_command_size')
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
    if command['mode'] in ('anex-range','andromeda-scope','andromeda-external-group','andromeda-operator-scope','match-tv942','match-samo942','match-tv234-secondary','match-common4-acquire','match-common4-continuation-acquire','match-common4-continuation-resume-day','match-common4-continuation-remainder','program-fuel-probe'):
        ensure_supplier_slot(token)
    result = execute(command, Path(args.source_root))
    print(json.dumps(result,sort_keys=True))
    if result.get('status') not in ('complete','reconciled_read_only','installed'):
        raise SystemExit(1)

if __name__ == '__main__':
    main()
