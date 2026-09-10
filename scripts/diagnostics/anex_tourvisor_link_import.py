#!/usr/bin/env python3
"""Four checked #1759 links through the existing importer; dry-run by default.

The receipt directory must be durable and restored before invocation. A reserved,
failed or missing-after-loss receipt is not permission to repeat a remote write.
No supplier calls. No new SQL/SSH implementation. Never use from an HTTP route.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path

REPORT_SHA256 = '324d25b3d08fd448a54fa2fd7dc2da6882f8858ea874b6820fc24f62b78cfa02'
PAIRS = {8121: 6319, 16275: 1326, 23775: 17568, 26688: 55648}
SOURCES = {
    'catalog_sha256': '8f1ee7bd288fe4f94a7e49a0245faffd4933e654443760d6454f7dfbf76d67f1',
    'geo_sha256': '88943e704bbc45120aa3a3f5b98e7b3975af55a5ca33f5a7ed5ed4b63e6513e5',
    'gap_sha256': REPORT_SHA256,
}


def canonical(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True,
                      separators=(',', ':'), allow_nan=False).encode('utf-8')


def digest(value):
    return hashlib.sha256(canonical(value)).hexdigest()


def approved_delta(checkpoint):
    """The full CI report is the authority, not the human-readable summary."""
    raw = Path(checkpoint).read_bytes()
    if hashlib.sha256(raw).hexdigest() != REPORT_SHA256:
        raise ValueError('unverified_link_review_report')
    report = json.loads(raw)
    rows = []
    for item in report['rows']:
        if item['status'] != 'eligible_for_guarded_import':
            continue
        rows.append({'anex_hotel_id': item['anex_hotel_id'],
                     'catalog_hotel_id': item['catalog_hotel_id'],
                     'match_class': 'strong_candidate',
                     'reason': 'tourvisor_link_review_20260910:name_country_coordinates',
                     'source_row_digest': digest(item)})
    rows.sort(key=lambda row: row['anex_hotel_id'])
    if len(rows) != 4 or {r['anex_hotel_id']: r['catalog_hotel_id'] for r in rows} != PAIRS:
        raise ValueError('checked_pair_set_changed')
    return {'schema_version': 1, 'scope': 'preview',
            'approval_policy': 'owner_exact_and_strong_20260908', 'append_only': True,
            'sources': dict(SOURCES), 'rows': rows,
            'counts': {'exact': 0, 'strong': 4, 'total': 4, 'unique_catalog_hotels': 4}}


def save(path, value, exclusive=False):
    flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, 'O_NOFOLLOW', 0)
    target = path if exclusive else path.with_name(path.name + '.tmp')
    with os.fdopen(os.open(target, flags, 0o600), 'wb') as handle:
        handle.write(canonical(value) + b'\n')
        handle.flush()
        os.fsync(handle.fileno())
    if not exclusive:
        os.replace(target, path)
    directory_fd = os.open(path.parent, os.O_RDONLY)
    try:
        os.fsync(directory_fd)
    finally:
        os.close(directory_fd)


def validate_result(result):
    if (result.get('status') not in ('imported', 'already_imported')
            or result.get('append_only') is not True or result.get('input_count') != 4
            or result.get('updated') != 0 or result.get('readback_verified') is not True
            or result.get('catalog_country_guard') != 4):
        raise ValueError('link_import_readback_unconfirmed')
    rows = result.get('link_readback', [])
    if len(rows) != 4 or {r.get('anex_hotel_id'): r.get('catalog_hotel_id') for r in rows} != PAIRS:
        raise ValueError('link_import_identity_unconfirmed')
    allowed = {'verified_policy_mapping', 'skipped_manual', 'skipped_pair_excluded'}
    if any(r.get('status') not in allowed for r in rows):
        raise ValueError('link_import_outcome_unconfirmed')


def apply(checkpoint, receipt):
    from anex_search_mapping_import import ssh_import
    checkpoint, receipt = Path(checkpoint), Path(receipt)
    delta = approved_delta(checkpoint)
    expected_digest = digest(delta)
    if receipt.is_symlink():
        raise ValueError('receipt_symlink')
    if receipt.exists():
        previous = json.loads(receipt.read_bytes())
        if (previous.get('state') != 'finalized' or previous.get('delta_sha256') != expected_digest
                or previous.get('result_sha256') != digest(previous.get('result'))):
            raise ValueError('reserved_or_unknown_import_do_not_replay')
        validate_result(previous['result'])
        return {'status': 'already_finalized', 'new_database_writes': 0,
                'saved_result': previous['result']}
    reservation = {'state': 'reserved', 'delta_sha256': expected_digest,
                   'checked_report_sha256': REPORT_SHA256}
    save(receipt, reservation, exclusive=True)
    # Any failure, including a lost response after COMMIT, leaves reserved intact.
    result = ssh_import(receipt.with_name(receipt.name + '.mapping.json'),
                        link_review_checkpoint=checkpoint)
    validate_result(result)
    save(receipt, dict(reservation, state='finalized', result=result,
                       result_sha256=digest(result)))
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--checkpoint', required=True, type=Path)
    parser.add_argument('--receipt', type=Path)
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    if args.apply and args.receipt is None:
        parser.error('--apply requires a durable --receipt')
    result = apply(args.checkpoint, args.receipt) if args.apply else {
        'status': 'prepared_not_applied', 'delta': approved_delta(args.checkpoint)}
    print(json.dumps(result, ensure_ascii=False, sort_keys=True))


if __name__ == '__main__':
    main()
