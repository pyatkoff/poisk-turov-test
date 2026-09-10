#!/usr/bin/env python3
"""Six explicit new country catalogues; separate capture/apply and durable receipts.

Reuses the project SSH helper, supplier client, monthly budget and identity table.
No price/booking methods, active country publication, or old operation replay.
"""
from __future__ import annotations
import argparse
import json
from pathlib import Path
from anex_tourvisor_link_import import save, digest

OPERATION_ID = 'andromeda-1759-country6-20260910-v1'
COUNTRIES = ('uae', 'thailand', 'vietnam', 'sri-lanka', 'maldives', 'cuba')


def remote(request):
    from anex_search3_owner_decisions import ssh_php
    source = Path(__file__).with_suffix('.php').read_text(encoding='utf-8').removeprefix('<?php')
    return ssh_php(source, request, maximum_bytes=4000000)


def validate_capture(country, value):
    if (value.get('status') != 'captured_not_applied' or value.get('operation_id') != OPERATION_ID
            or value.get('country') != country or type(value.get('country_id')) is not int
            or not 0 <= value.get('supplier_calls', -1) <= 3
            or set(value.get('counts', {})) != {'accepted', 'pending', 'conflict'}
            or sum(value['counts'].values()) != value.get('source_hotels')
            or len(value.get('pairs', [])) != value['counts']['accepted']):
        raise ValueError('country_capture_not_verified')
    for key in ('capture_sha256', 'plan_sha256'):
        v = value.get(key, '')
        if len(v) != 64 or any(c not in '0123456789abcdef' for c in v):
            raise ValueError('country_capture_digest_missing')


def validate_result(capture, result):
    if (result.get('status') != 'imported' or result.get('operation_id') != OPERATION_ID
            or result.get('country') != capture['country']
            or result.get('country_id') != capture['country_id']
            or result.get('capture_sha256') != capture['capture_sha256']
            or result.get('plan_sha256') != capture['plan_sha256']
            or result.get('readback_verified') is not True
            or result.get('previous_identities_unchanged') is not True
            or result.get('supplier_calls') != 0):
        raise ValueError('country_import_not_verified')
    rows = result.get('rows', [])
    if (len(rows) != result.get('inserted') or len({r['external_hotel_id'] for r in rows}) != len(rows)
            or sum(result.get('new_counts', {}).values()) != len(rows)):
        raise ValueError('country_import_count_mismatch')
    expected = {r['external_hotel_id']: r['local_hotel_id'] for r in capture['pairs']}
    counts = {'accepted': 0, 'pending': 0, 'conflict': 0}
    for row in rows:
        status = row['decision_status']
        if status not in counts:
            raise ValueError('unexpected_identity_status')
        counts[status] += 1
        if status == 'accepted':
            if expected.get(row['external_hotel_id']) != row['local_hotel_id']:
                raise ValueError('unprepared_identity')
        elif row['local_hotel_id'] is not None:
            raise ValueError('unresolved_identity_has_target')
    if counts != result['new_counts']:
        raise ValueError('new_status_count_mismatch')


def capture_all(directory: Path, transport=remote):
    directory.mkdir(mode=0o700, parents=True, exist_ok=True)
    save(directory/'capture-reservation.json', {'state': 'reserved', 'operation_id': OPERATION_ID,
         'countries': COUNTRIES, 'max_supplier_calls': 18}, exclusive=True)
    results = []
    for country in COUNTRIES:
        save(directory/f'{country}.capture-reservation.json', {'state': 'reserved'}, exclusive=True)
        request = {'operation_id': OPERATION_ID, 'phase': 'capture', 'country': country}
        try:
            value = transport(request)
            save(directory/f'{country}.capture.json', value, exclusive=True)
            validate_capture(country, value)
            results.append({'country': country, 'status': 'captured', 'sha256': digest(value),
                            'counts': value['counts'], 'supplier_calls': value['supplier_calls']})
        except Exception:
            results.append({'country': country, 'status': 'failed_or_unknown', 'retry': False})
    summary = {'operation_id': OPERATION_ID, 'countries': results,
               'status': 'captured' if all(r['status'] == 'captured' for r in results) else 'partial_or_failed',
               'database_writes': 0}
    save(directory/'capture-summary.json', summary, exclusive=True)
    return summary


def apply_all(directory: Path, transport=remote):
    summary = json.loads((directory/'capture-summary.json').read_bytes())
    if summary.get('operation_id') != OPERATION_ID or tuple(r['country'] for r in summary['countries']) != COUNTRIES:
        raise ValueError('unprepared_country_set')
    # The external operation reservation must already be durable before this call.
    save(directory/'apply-reservation.json', {'state': 'reserved', 'operation_id': OPERATION_ID,
         'capture_summary_sha256': digest(summary)}, exclusive=True)
    results = []
    for item in summary['countries']:
        country = item['country']
        if item['status'] != 'captured':
            results.append({'country': country, 'status': 'not_applied_capture_unconfirmed'})
            continue
        value = json.loads((directory/f'{country}.capture.json').read_bytes())
        validate_capture(country, value)
        if digest(value) != item['sha256']:
            raise ValueError('capture_changed_after_reservation')
        save(directory/f'{country}.apply-reservation.json', {'state': 'reserved', 'capture_sha256': item['sha256']}, exclusive=True)
        request = {k: value[k] for k in ('capture_sha256', 'plan_sha256')}
        request.update(operation_id=OPERATION_ID, phase='apply', country=country)
        try:
            result = transport(request)
            save(directory/f'{country}.outcome.json', result, exclusive=True)
            validate_result(value, result)
            save(directory/f'{country}.receipt.json', {'state': 'finalized', 'result_sha256': digest(result), 'result': result}, exclusive=True)
            results.append({k: result[k] for k in ('country', 'country_name', 'status', 'inserted', 'new_counts', 'readback_verified', 'live_coverage', 'current_andromeda_counts')})
        except Exception:
            results.append({'country': country, 'status': 'failed_or_unknown', 'retry': False})
    report = {'operation_id': OPERATION_ID, 'countries': results,
              'status': 'completed' if all(r['status'] == 'imported' for r in results) else 'partial_or_failed',
              'accepted_confirmed': sum(r.get('new_counts', {}).get('accepted', 0) for r in results),
              'inserted_confirmed': sum(r.get('inserted', 0) for r in results), 'supplier_calls_during_apply': 0}
    save(directory/'result-summary.json', report, exclusive=True)
    return report


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('phase', choices=('describe', 'capture', 'apply'))
    p.add_argument('--directory', type=Path)
    p.add_argument('--execute', action='store_true')
    args = p.parse_args()
    if args.phase == 'describe':
        result = {'operation_id': OPERATION_ID, 'countries': COUNTRIES, 'max_supplier_calls': 18, 'database_writes': 0}
    else:
        if not args.execute or args.directory is None:
            p.error('capture/apply requires explicit --execute and durable --directory')
        result = capture_all(args.directory) if args.phase == 'capture' else apply_all(args.directory)
    print(json.dumps(result, ensure_ascii=False, sort_keys=True))


if __name__ == '__main__':
    main()
