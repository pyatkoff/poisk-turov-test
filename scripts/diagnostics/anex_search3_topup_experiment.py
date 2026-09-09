#!/usr/bin/env python3
"""One durable Tourvisor hotelIds experiment against an already saved ANEX sample."""
import argparse
import hashlib
import json
import os
from pathlib import Path

EXPERIMENT = 'catalog_hotel_topup_20260909'
PREVIEW = '2545982789ada8db8c4be18be0c1159f624633bc'
HOTELS = [445, 9365, 56094]
BOOTSTRAP = 10093538360
CHECKPOINT = 'anex-topup-checkpoint.json'
REPORT = 'anex-topup-report.json'
PLAN = Path(__file__).with_name('anex_search3_topup_plan.json')
PROTECTED = ['anex-hotel-geo-enrichment.json', 'anex-owner-hotel-decisions.json',
             'anex-initial-search-checkpoint.json', 'anex-paired-search-v2-checkpoint.json',
             'anex-segment-search-checkpoint.json']


def digest(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, ensure_ascii=False).encode()).hexdigest()


def save(path, value):
    temporary = path.with_suffix('.tmp')
    temporary.write_text(json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + '\n')
    temporary.replace(path)
    if json.loads(path.read_text()) != value:
        raise ValueError('topup checkpoint readback failed')


def job():
    values = [os.environ.get('GITHUB_RUN_ID'), os.environ.get('GITHUB_RUN_ATTEMPT')]
    if not all(values):
        raise ValueError('topup job identity required')
    return values


def plan():
    value = json.loads(PLAN.read_text())
    if (value.get('experiment_id') != EXPERIMENT or value.get('preview_source_sha') != PREVIEW
            or value.get('hotel_ids') != HOTELS):
        raise ValueError('fixed topup identity changed')
    return value


def protected(directory):
    return {name: hashlib.sha256((directory / name).read_bytes()).hexdigest() for name in PROTECTED}


def load(directory):
    cp = json.loads((directory / CHECKPOINT).read_text())
    if (cp.get('schema_version') != 1 or cp.get('plan_sha256') != digest(plan())
            or cp.get('protected') != protected(directory)
            or cp.get('state') not in ('reserved', 'running', 'completed', 'interrupted_result_unknown')):
        raise ValueError('topup checkpoint provenance mismatch')
    if cp['state'] == 'completed' and cp.get('result_sha256') != digest(cp.get('result')):
        raise ValueError('topup result digest mismatch')
    return cp


def prepare(directory):
    if (directory / CHECKPOINT).exists():
        cp = load(directory)
        if cp['state'] in ('reserved', 'running') and cp['reserved_by'] != job():
            cp.update(state='interrupted_result_unknown', reason='previous_job_execution_unconfirmed')
            save(directory / CHECKPOINT, cp)
        return {'status': cp['state'], 'new_reservations': 0}
    source = json.loads((directory / 'anex-checkpoint-source.json').read_text())
    if source.get('artifact_id') != BOOTSTRAP:
        raise ValueError('topup absent outside approved bootstrap')
    cp = {'schema_version': 1, 'plan_sha256': digest(plan()), 'protected': protected(directory),
          'restored_source': source, 'reserved_by': job(), 'state': 'reserved'}
    save(directory / CHECKPOINT, cp)
    load(directory)
    return {'status': 'reserved', 'new_tourvisor_search_budget': 1, 'hotel_ids': HOTELS, 'anex_requests': 0}


def request_once():
    from anex_search3_owner_decisions import ssh_php
    root = Path(__file__).parent
    source = "define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);\n"
    source += (root / 'anex_search3_paired_runner.php').read_text().removeprefix('<?php')
    source += '\n' + (root / 'anex_search3_topup_runner.php').read_text().removeprefix('<?php')
    request = {'experiment_id': EXPERIMENT, 'preview_source_sha': PREVIEW, 'hotel_ids': HOTELS}
    return ssh_php(source, request, maximum_bytes=4000000)


def run(directory):
    cp = load(directory)
    if cp['state'] != 'reserved':
        return {'status': 'already_finalized', 'state': cp['state'], 'supplier_requests': 0}
    if cp['reserved_by'] != job():
        raise ValueError('topup reservation belongs to another job')
    cp['state'] = 'running'
    save(directory / CHECKPOINT, cp)
    try:
        result = request_once()
        if (result.get('schema_version') != 1 or result.get('experiment_id') != EXPERIMENT
                or result.get('status') not in ('ok', 'probe_unavailable')
                or result.get('requests', {}).get('anex') != 0
                or result.get('preservation_verified') is not True):
            raise ValueError('topup result unconfirmed')
        load(directory)
    except Exception:
        cp.update(state='interrupted_result_unknown', reason='result_unconfirmed')
        save(directory / CHECKPOINT, cp)
        return {'status': 'interrupted_result_unknown', 'requests_replayed': 0}
    cp.update(state='completed', result=result, result_sha256=digest(result))
    save(directory / CHECKPOINT, cp)
    load(directory)
    return {'status': result['status'], 'result_sha256': cp['result_sha256'],
            'summary': result.get('summary'), 'requests': result.get('requests')}


def finalize(directory):
    cp = load(directory)
    report = {'schema_version': 1, 'experiment_id': EXPERIMENT, 'source_sha': os.environ.get('GITHUB_SHA'),
              'plan': plan(), 'state': cp['state'], 'result': cp.get('result'),
              'result_sha256': cp.get('result_sha256'), 'historical_files_unchanged': True,
              'automatic_topup_enabled': False, 'new_mappings_accepted': 0,
              'interpretation': 'Point query compared with saved broad result; not simultaneous availability or identical package evidence'}
    save(directory / REPORT, report)
    return report


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument('--prepare', action='store_true')
    mode.add_argument('--run', action='store_true')
    mode.add_argument('--finalize', action='store_true')
    args = parser.parse_args()
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    try:
        result = prepare(directory) if args.prepare else run(directory) if args.run else finalize(directory)
        print(json.dumps(result, ensure_ascii=False, sort_keys=True))
        if result.get('status') in ('probe_unavailable', 'interrupted_result_unknown'):
            raise SystemExit(1)
    except Exception:
        print(json.dumps({'status': 'blocked', 'error': 'TOPUP_CHECKPOINT_OR_EXECUTION_UNCONFIRMED', 'requests_replayed': 0}))
        raise SystemExit(1) from None
