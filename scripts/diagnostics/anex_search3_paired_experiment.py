#!/usr/bin/env python3
"""One owner-requested, resumable date-width comparison; never accepts hotel links."""
import argparse
import csv
import hashlib
import json
import os
from pathlib import Path

import anex_search3_gap_queue as gaps
import anex_search3_observed_queue as observed
from anex_search3_owner_decisions import save, ssh_php

EXPERIMENT = 'one_day_anex_20260908_v2'
BOOTSTRAP_ARTIFACT = 10076461153
CHECKPOINT = 'anex-paired-search-v2-checkpoint.json'
REPORT = 'anex-paired-search-v2-report.json'
CASES = ['tv_day', 'tv_week', 'anex_day']
SPEC = {'experiment_id': EXPERIMENT, 'date': '2026-09-16', 'nights': 7,
        'adults': 2, 'currency': 'RUB'}
PROTECTED = ['anex-observed-hotel-checkpoint.json', 'anex-initial-search-checkpoint.json',
             'anex-hotel-geo-enrichment.json', 'anex-owner-hotel-decisions.json',
             'anex-complete-candidate-review-checkpoint.json',
             'anex-complete-candidate-review-acceptance.json',
             'anex-paired-search-checkpoint.json']


def fingerprints(directory):
    return {name: hashlib.sha256((directory / name).read_bytes()).hexdigest() for name in PROTECTED}


def load_checkpoint(directory):
    cp = json.loads((directory / CHECKPOINT).read_bytes())
    if cp.get('schema_version') != 1 or cp.get('spec') != SPEC or cp.get('case_order') != CASES:
        raise ValueError('paired experiment provenance mismatch')
    if cp.get('protected_files') != fingerprints(directory):
        raise ValueError('historical hotel evidence changed')
    if set(cp.get('cases', {})) != set(CASES):
        raise ValueError('paired case identity mismatch')
    for case_id, row in cp['cases'].items():
        if row.get('state') not in ('reserved', 'completed', 'interrupted_result_unknown'):
            raise ValueError('invalid paired case state')
        if row['state'] == 'completed':
            result = row.get('result', {})
            if (result.get('case_id') != case_id or result.get('experiment_id') != EXPERIMENT
                    or row.get('result_sha256') != gaps.digest(result)):
                raise ValueError('paired result digest mismatch')
    return cp


def prepare(directory):
    live = observed.restore(directory)
    if live['in_flight'] or observed.needs_finalization(directory, live):
        raise ValueError('unfinished live hotel batch')
    path = directory / CHECKPOINT
    if path.exists():
        cp = load_checkpoint(directory)
        # A reservation from another job has unknown execution, even if no output survived.
        # Never replay its supplier calls as a side effect of re-running a job.
        job = [os.environ.get('GITHUB_RUN_ID'), os.environ.get('GITHUB_RUN_ATTEMPT')]
        if cp['reserved_by'] != job:
            for row in cp['cases'].values():
                if row['state'] == 'reserved':
                    row.update(state='interrupted_result_unknown', reason='previous_job_result_unconfirmed')
            save(path, cp)
        return {'status': 'restored', 'states': {k: v['state'] for k, v in cp['cases'].items()}}
    source = json.loads((directory / 'anex-checkpoint-source.json').read_bytes())
    if source.get('artifact_id') != BOOTSTRAP_ARTIFACT:
        raise ValueError('paired checkpoint absent outside its single approved bootstrap')
    cp = {'schema_version': 1, 'spec': SPEC, 'case_order': CASES,
          'restored_source': source, 'protected_files': fingerprints(directory),
          'supersedes_incomplete_experiment': {'id': 'one_day_anex_20260908',
              'artifact_id': 10076461153, 'reason': 'local_status_path_regex_error_after_search_start',
              'previous_tourvisor_searches_started': 1, 'previous_anex_requests': 0,
              'previous_results_rewritten': False},
          'reserved_by': [os.environ.get('GITHUB_RUN_ID'), os.environ.get('GITHUB_RUN_ATTEMPT')],
          'cases': {case_id: {'state': 'reserved'} for case_id in CASES}}
    save(path, cp)
    load_checkpoint(directory)
    return {'status': 'reserved', 'cases': CASES, 'historical_completed': live['completed_total']}


def run_case(directory, case_id):
    cp = load_checkpoint(directory)
    prior = cp['cases'][case_id]
    if prior['state'] != 'reserved':
        return {'case_id': case_id, 'status': 'already_finalized', 'supplier_calls': 0}
    if cp['reserved_by'] != [os.environ.get('GITHUB_RUN_ID'), os.environ.get('GITHUB_RUN_ATTEMPT')]:
        raise ValueError('reservation belongs to a previous job')
    request = dict(SPEC, case_id=case_id)
    source = Path(__file__).with_name('anex_search3_paired_runner.php').read_text().removeprefix('<?php')
    try:
        result = ssh_php(source, request, maximum_bytes=4000000)
        if (result.get('schema_version') != 1 or result.get('experiment_id') != EXPERIMENT
                or result.get('case_id') != case_id or result.get('status') not in ('ok', 'probe_unavailable')
                or not isinstance(result.get('offers'), list) or len(result['offers']) > 2000):
            raise ValueError('paired result unconfirmed')
        if result.get('preservation_verified') is not True:
            raise ValueError('paired database preservation unconfirmed')
        if fingerprints(directory) != cp['protected_files']:
            raise ValueError('historical evidence changed during paired query')
    except Exception as error:
        cp['cases'][case_id] = {'state': 'interrupted_result_unknown',
                               'reason': 'result_unconfirmed', 'diagnostic': gaps.failure_report(error, 'paired_search')}
        save(directory / CHECKPOINT, cp)
        return {'case_id': case_id, 'status': 'interrupted_result_unknown', 'requests_replayed': 0}
    cp['cases'][case_id] = {'state': 'completed', 'result_sha256': gaps.digest(result), 'result': result}
    save(directory / CHECKPOINT, cp)
    load_checkpoint(directory)
    return {'case_id': case_id, 'status': result['status'], 'offers': len(result['offers']),
            'summary': result.get('summary'), 'coverage_limits': result.get('coverage_limits'),
            'error_code': result.get('error_code'), 'requests': result.get('requests'),
            'result_sha256': gaps.digest(result)}


def identities(offers, key):
    return {str(r[key]) for r in offers if r.get(key) is not None and str(r[key]).isdigit() and int(r[key]) > 0}


def compare(results):
    tv_day = results.get('tv_day', {}).get('offers', [])
    tv_week = results.get('tv_week', {}).get('offers', [])
    anex = results.get('anex_day', {}).get('offers', [])
    day_ids, week_ids = identities(tv_day, 'hotel_id'), identities(tv_week, 'hotel_id')
    accepted = identities(anex, 'local_hotel_id')
    both = sorted(day_ids & accepted, key=int)
    paired = []
    for a in anex:
        if str(a.get('local_hotel_id')) not in both:
            continue
        for t in tv_day:
            if str(t.get('hotel_id')) != str(a.get('local_hotel_id')):
                continue
            keys = ('date', 'nights', 'adults', 'children', 'currency')
            if all(a.get(k) is not None and a.get(k) == t.get(k) for k in keys):
                paired.append({'local_hotel_id': a['local_hotel_id'], 'anex_hotel_id': a['hotel_id'],
                               'date': a['date'], 'nights': a['nights'],
                               'anex_price': a.get('price'), 'tourvisor_price': t.get('price'),
                               'anex_meal': a.get('meal'), 'tourvisor_meal': t.get('meal'),
                               'anex_room': a.get('room'), 'tourvisor_room': t.get('room'),
                               'same_hotel_basis': 'previously_accepted_mapping',
                               'identical_package_verified': False, 'fuel_inclusion_verified': False})
                break
        if len(paired) >= 30:
            break
    return {'tv_day_unique_hotels': len(day_ids), 'tv_week_unique_hotels': len(week_ids),
            'date_width_cases_successful': all(results.get(k, {}).get('status') == 'ok' for k in CASES[:2]),
            'date_width_searches_completed': all(results.get(k, {}).get('coverage_limits', {}).get('search_complete') is True for k in CASES[:2]),
            'date_width_output_uncapped': all(results.get(k, {}).get('coverage_limits', {}).get('hotel_limit_reached') is False
                                             and results.get(k, {}).get('coverage_limits', {}).get('output_limit_reached') is False for k in CASES[:2]),
            'tv_day_only_hotels': sorted(day_ids - week_ids, key=int),
            'tv_week_only_hotels': sorted(week_ids - day_ids, key=int),
            'anex_unique_hotels': len(identities(anex, 'hotel_id')),
            'anex_mapped_local_hotels': len(accepted), 'accepted_overlap_local_ids': both,
            'accepted_overlap_count': len(both), 'new_mappings_accepted': 0,
            'aligned_date_party_examples': paired,
            'interpretation': 'bounded_search_samples_only; hotel/name/price overlap does not accept a mapping',
            'final_price_verified': False}


def finalize(directory, refresh=True):
    cp = load_checkpoint(directory)
    results = {key: row['result'] for key, row in cp['cases'].items() if row['state'] == 'completed'}
    report = {'schema_version': 1, 'experiment_id': EXPERIMENT, 'source_sha': os.environ.get('GITHUB_SHA'),
              'spec': SPEC, 'restored_source': cp['restored_source'],
              'supersedes_incomplete_experiment': cp.get('supersedes_incomplete_experiment'),
              'states': {k: v['state'] for k, v in cp['cases'].items()},
              'comparison': compare(results), 'case_results': results,
              'historical_files_unchanged': fingerprints(directory) == cp['protected_files'],
              'checkpoint_sha256': hashlib.sha256((directory / CHECKPOINT).read_bytes()).hexdigest()}
    if refresh:
        live = observed.restore(directory)
        snapshot = observed.snapshot()
        report['live_queue'] = observed.export(directory, live, snapshot)
    save(directory / REPORT, report)
    fields = ['case_id', 'hotel_id', 'local_hotel_id', 'hotel_name', 'date', 'nights',
              'adults', 'children', 'meal', 'room', 'price', 'currency', 'operator_id', 'operator_name']
    path = directory / 'anex-paired-search-v2-offers.csv'
    with path.open('w', newline='') as handle:
        writer = csv.writer(handle)
        writer.writerow(fields)
        for case_id, result in results.items():
            for row in result['offers']:
                writer.writerow([observed.csv_cell(dict(row, case_id=case_id).get(k, '')) for k in fields])
    # Read back the durable JSON and CSV before declaring the experiment saved.
    with path.open(newline='') as handle:
        if len(list(csv.reader(handle))) != 1 + sum(len(r['offers']) for r in results.values()):
            raise ValueError('paired CSV readback mismatch')
    concise = {k: v for k, v in report.items() if k != 'case_results'}
    if 'live_queue' in concise:
        concise['live_queue'] = {k: v for k, v in concise['live_queue'].items() if k != 'triage'}
    return concise


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument('--prepare', action='store_true')
    mode.add_argument('--case', choices=CASES)
    mode.add_argument('--finalize', action='store_true')
    args = parser.parse_args()
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    try:
        result = prepare(directory) if args.prepare else run_case(directory, args.case) if args.case else finalize(directory)
        print(json.dumps(result, ensure_ascii=False, sort_keys=True))
        # A controlled supplier failure also ends this experiment's requests, including 429.
        # Its exact report survives; never follow it with another query during Retry-After.
        if result.get('status') in ('interrupted_result_unknown', 'probe_unavailable'):
            raise SystemExit(1)
    except Exception as error:
        print(json.dumps(gaps.failure_report(error, 'paired_experiment')))
        raise SystemExit(1) from None
