#!/usr/bin/env python3
"""Bounded Tourvisor segmentation compared with the saved ANEX one-day response."""
import argparse
import csv
import hashlib
import json
import os
from pathlib import Path

import anex_search3_gap_queue as gaps
import anex_search3_observed_queue as observed
from anex_search3_owner_decisions import save, ssh_php
from anex_search3_paired_experiment import compare, identities

EXPERIMENT = 'one_day_anex_segments_20260908'
CASES = ['tv_alanya', 'tv_5star', 'tv_alanya_5star']
SPEC = {'experiment_id': EXPERIMENT, 'date': '2026-09-16', 'nights': 7, 'adults': 2, 'currency': 'RUB'}
CHECKPOINT = 'anex-segment-search-checkpoint.json'
BASELINE = 'anex-paired-search-v2-checkpoint.json'
BASELINE_SHA = '013a231497da38399159167c25ebaa0aeb01079d776d7370d163a61bdc90603a'
BOOTSTRAP = 10076716759
PROTECTED = [BASELINE, 'anex-paired-search-checkpoint.json', 'anex-hotel-geo-enrichment.json',
             'anex-initial-search-checkpoint.json', 'anex-owner-hotel-decisions.json',
             'anex-complete-candidate-review-checkpoint.json', 'anex-complete-candidate-review-acceptance.json']


def baseline(directory):
    raw = (directory / BASELINE).read_bytes()
    if hashlib.sha256(raw).hexdigest() != BASELINE_SHA:
        raise ValueError('saved paired comparison changed')
    cp = json.loads(raw)
    results = {}
    for key in ('tv_day', 'tv_week', 'anex_day'):
        row = cp['cases'][key]
        if (row['state'] != 'completed' or row['result']['status'] != 'ok'
                or row['result_sha256'] != gaps.digest(row['result'])):
            raise ValueError('baseline result incomplete')
        results[key] = row['result']
    return results


def protected_files(directory):
    return {name: hashlib.sha256((directory / name).read_bytes()).hexdigest() for name in PROTECTED}


def job():
    return [os.environ.get('GITHUB_RUN_ID'), os.environ.get('GITHUB_RUN_ATTEMPT')]


def load_checkpoint(directory):
    cp = json.loads((directory / CHECKPOINT).read_bytes())
    if (cp.get('schema_version') != 1 or cp.get('spec') != SPEC or cp.get('case_order') != CASES
            or set(cp.get('cases', {})) != set(CASES) or cp.get('protected_files') != protected_files(directory)):
        raise ValueError('segment checkpoint provenance changed')
    live = observed.restore(directory)
    previous = cp['observed_history']
    count = previous['live_rows']
    if (live['completed_total'] < previous['completed_total']
            or gaps.digest(live['inherited']) != previous['inherited_sha256']
            or gaps.digest(live['rows'][:count]) != previous['rows_sha256']):
        raise ValueError('historical observed results changed')
    for key, row in cp['cases'].items():
        if row.get('state') not in ('reserved', 'completed', 'interrupted_result_unknown'):
            raise ValueError('invalid segment state')
        if row['state'] == 'completed' and (row['result'].get('case_id') != key
                or row['result'].get('experiment_id') != EXPERIMENT
                or row.get('result_sha256') != gaps.digest(row['result'])):
            raise ValueError('segment result digest mismatch')
    return cp


def prepare(directory):
    baseline(directory)
    live = observed.restore(directory)
    if live['in_flight'] or observed.needs_finalization(directory, live):
        raise ValueError('unfinished observed batch')
    path = directory / CHECKPOINT
    if path.exists():
        cp = load_checkpoint(directory)
        if cp['reserved_by'] != job():
            for row in cp['cases'].values():
                if row['state'] == 'reserved':
                    row.update(state='interrupted_result_unknown', reason='previous_job_result_unconfirmed')
            save(path, cp)
        return {'status': 'restored', 'states': {k: v['state'] for k, v in cp['cases'].items()}}
    source = json.loads((directory / 'anex-checkpoint-source.json').read_bytes())
    if source.get('artifact_id') != BOOTSTRAP:
        raise ValueError('missing segment checkpoint outside approved bootstrap')
    cp = {'schema_version': 1, 'spec': SPEC, 'case_order': CASES, 'reserved_by': job(),
          'restored_source': source, 'protected_files': protected_files(directory),
          'observed_history': {'completed_total': live['completed_total'], 'live_rows': len(live['rows']),
              'inherited_sha256': gaps.digest(live['inherited']), 'rows_sha256': gaps.digest(live['rows'])},
          'cases': {key: {'state': 'reserved'} for key in CASES}}
    save(path, cp)
    load_checkpoint(directory)
    return {'status': 'reserved', 'case_ids': CASES, 'baseline_sha256': BASELINE_SHA,
            'observed_completed_preserved': live['completed_total'], 'anex_requests': 0}


def run_case(directory, case_id):
    cp = load_checkpoint(directory)
    if cp['cases'][case_id]['state'] != 'reserved':
        return {'case_id': case_id, 'status': 'already_finalized', 'requests': 0}
    if cp['reserved_by'] != job():
        raise ValueError('reservation belongs to another job')
    root = Path(__file__).parent
    source = "define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);\n"
    source += (root / 'anex_search3_paired_runner.php').read_text().removeprefix('<?php')
    source += '\n' + (root / 'anex_search3_segment_runner.php').read_text().removeprefix('<?php')
    try:
        result = ssh_php(source, dict(SPEC, case_id=case_id), maximum_bytes=4000000)
        if (result.get('schema_version') != 1 or result.get('experiment_id') != EXPERIMENT
                or result.get('case_id') != case_id or result.get('status') not in ('ok', 'probe_unavailable')
                or result.get('preservation_verified') is not True
                or not isinstance(result.get('offers'), list) or len(result['offers']) > 1500
                or result.get('requests', {}).get('anex') != 0):
            raise ValueError('unconfirmed segment response')
        load_checkpoint(directory)
    except Exception as error:
        cp['cases'][case_id] = {'state': 'interrupted_result_unknown', 'reason': 'result_unconfirmed',
                               'diagnostic': gaps.failure_report(error, 'segment_search')}
        save(directory / CHECKPOINT, cp)
        return {'case_id': case_id, 'status': 'interrupted_result_unknown', 'requests_replayed': 0}
    cp['cases'][case_id] = {'state': 'completed', 'result_sha256': gaps.digest(result), 'result': result}
    save(directory / CHECKPOINT, cp)
    load_checkpoint(directory)
    return {k: result.get(k) for k in ('case_id', 'status', 'error_code', 'criteria', 'operator', 'summary',
            'coverage_limits', 'filter_verification', 'requests')} | {'result_sha256': gaps.digest(result)}


def analysis(saved, results):
    original = identities(saved['tv_day']['offers'], 'hotel_id') | identities(saved['tv_week']['offers'], 'hotel_id')
    combined = set(original)
    out = {}
    for key in CASES:
        result = results.get(key)
        if result is None or result.get('status') != 'ok':
            out[key] = {'status': result.get('status') if result else 'not_confirmed',
                        'coverage_confirmed': False}
            continue
        raw_ids = {str(i) for i in result.get('raw_response', {}).get('hotel_ids', [])}
        accepted_ids = identities(result['offers'], 'hotel_id')
        compared = compare({'tv_day': result, 'anex_day': saved['anex_day']})
        out[key] = {'status': result['status'], 'raw_unique_hotels': len(raw_ids),
                    'coverage_confirmed': True,
                    'requested_segment_verified': result.get('filter_verification', {}).get('response_respects_requested_filters') is True,
                    'validated_unique_hotels': len(accepted_ids),
                    'additional_vs_baseline': sorted(accepted_ids - original, key=int),
                    'additional_vs_previous_segments': sorted(accepted_ids - combined, key=int),
                    'accepted_overlap_count': compared['accepted_overlap_count'],
                    'accepted_overlap_local_ids': compared['accepted_overlap_local_ids'],
                    'search_complete': result.get('coverage_limits', {}).get('search_complete'),
                    'filter_verification': result.get('filter_verification'),
                    'full_supplier_inventory': False}
        if result['status'] == 'ok':
            combined |= accepted_ids
    anex_ids = identities(saved['anex_day']['offers'], 'local_hotel_id')
    return {'baseline_tv_unique_hotels': len(original), 'combined_tv_unique_hotels': len(combined),
            'new_tv_hotel_ids': sorted(combined - original, key=int),
            'combined_accepted_anex_overlap': sorted(combined & anex_ids, key=int),
            'cases': out, 'anex_baseline_reused': True, 'anex_requests': 0, 'new_mappings_accepted': 0,
            'interpretation': 'bounded Tourvisor segments compared with saved ANEX day snapshot; not a synchronous price comparison'}


def finalize(directory, refresh=True):
    cp = load_checkpoint(directory)
    saved = baseline(directory)
    results = {key: row['result'] for key, row in cp['cases'].items() if row['state'] == 'completed'}
    report = {'schema_version': 1, 'experiment_id': EXPERIMENT, 'spec': SPEC,
              'source_sha': os.environ.get('GITHUB_SHA'), 'restored_source': cp['restored_source'],
              'checkpoint_sha256': hashlib.sha256((directory / CHECKPOINT).read_bytes()).hexdigest(),
              'baseline_sha256': BASELINE_SHA, 'states': {k: v['state'] for k, v in cp['cases'].items()},
              'historical_files_unchanged': cp['protected_files'] == protected_files(directory),
              'analysis': analysis(saved, results), 'case_results': results}
    if refresh:
        report['live_queue'] = observed.export(directory, observed.restore(directory), observed.snapshot())
    save(directory / 'anex-segment-search-report.json', report)
    fields = ['case_id', 'hotel_id', 'local_hotel_id', 'hotel_name', 'hotel_category', 'region', 'date',
              'nights', 'meal', 'room', 'price', 'currency', 'operator_id', 'operator_name']
    target = directory / 'anex-segment-search-offers.csv'
    with target.open('w', newline='') as handle:
        writer = csv.writer(handle)
        writer.writerow(fields)
        for key, result in results.items():
            for row in result['offers']:
                writer.writerow([observed.csv_cell(dict(row, case_id=key).get(k, '')) for k in fields])
    with target.open(newline='') as handle:
        if len(list(csv.reader(handle))) != 1 + sum(len(r['offers']) for r in results.values()):
            raise ValueError('segment CSV readback mismatch')
    summary = {k: v for k, v in report.items() if k != 'case_results'}
    if refresh:
        summary['live_queue'] = {k: v for k, v in report['live_queue'].items() if k != 'triage'}
    return summary


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    choice = parser.add_mutually_exclusive_group(required=True)
    choice.add_argument('--prepare', action='store_true')
    choice.add_argument('--case', choices=CASES)
    choice.add_argument('--finalize', action='store_true')
    args = parser.parse_args()
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    try:
        result = prepare(directory) if args.prepare else run_case(directory, args.case) if args.case else finalize(directory)
        print(json.dumps(result, ensure_ascii=False, sort_keys=True))
        if result.get('status') in ('probe_unavailable', 'interrupted_result_unknown'):
            raise SystemExit(1)
    except Exception as error:
        print(json.dumps(gaps.failure_report(error, 'segment_experiment')))
        raise SystemExit(1) from None
