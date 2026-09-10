#!/usr/bin/env python3
"""Accept the pinned 92 Andromeda→catalog proposals through one guarded transaction.

Dry-run/prepare by default. The input is the exact full #1900 scale report. No supplier
calls or matching are performed here; the PHP writer rechecks current DB state before writes.
"""
from __future__ import annotations
import argparse, hashlib, json, os
from pathlib import Path

import anex_search3_owner_decisions as owner

REPORT_CANONICAL_SHA256 = '16158bb5d4e6ff7189512ead8a0d12227f85a8e9a1cb167fbe38b188d0df7857'
OPERATION_ID = 'andromeda-1759-validated-92-v1'


def canonical(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':'), allow_nan=False).encode()


def digest(value):
    return hashlib.sha256(canonical(value)).hexdigest()


def load_proposals(path: Path):
    report = json.loads(path.read_bytes())
    if digest(report) != REPORT_CANONICAL_SHA256:
        raise ValueError('unverified_scale_report')
    if report.get('scope') != 'all_saved_unresolved_turkey_egypt' or report.get('database_writes') != 0:
        raise ValueError('unexpected_scale_report_scope')
    rows = []
    for item in report.get('rows', []):
        if item.get('provider') != 'andromeda' or item.get('status') != 'validated_proposal_not_accepted':
            continue
        detail = item.get('detail') or {}
        geo = detail.get('geography') or {}
        source = detail.get('source') or {}
        local = detail.get('local_hotel') or {}
        if (item.get('live_guards_checked') is not False or geo.get('status') != 'supported'
                or item.get('local_hotel_id') != local.get('id') or item.get('country_id') != local.get('country_id')
                or str(item.get('external_id')) != str(source.get('id'))
                or detail.get('expected_decision_status') != 'pending'
                or item.get('expected_evidence_sha256') != detail.get('expected_evidence_sha256')):
            raise ValueError('proposal_evidence_incomplete')
        rows.append({
            'external_hotel_id': str(item['external_id']),
            'local_hotel_id': int(item['local_hotel_id']),
            'country_id': int(item['country_id']),
            'expected_evidence_sha256': item['expected_evidence_sha256'],
            'source_row_sha256': item['source_row_sha256'],
            'source': source,
            'expected_local': local,
            'geography': geo,
            'category_difference': bool(detail.get('category_difference')),
        })
    rows.sort(key=lambda r: int(r['external_hotel_id']))
    if len(rows) != 92 or len({r['external_hotel_id'] for r in rows}) != 92 or len({r['local_hotel_id'] for r in rows}) != 92:
        raise ValueError('validated_92_set_changed')
    if {r['country_id'] for r in rows} != {1, 4}:
        raise ValueError('unexpected_country_set')
    return rows


def request(path: Path):
    rows = load_proposals(path)
    return {'schema_version': 1, 'operation_id': OPERATION_ID,
            'source_report_sha256': REPORT_CANONICAL_SHA256, 'append_only_decisions': True,
            'supplier_calls': 0, 'rows': rows}


def apply(report_path: Path):
    payload = request(report_path)
    source = Path(__file__).with_suffix('.php').read_text(encoding='utf-8').removeprefix('<?php')
    result = owner.ssh_php(source, payload, maximum_bytes=65536)
    if (result.get('status') != 'accepted' or result.get('operation_id') != OPERATION_ID
            or result.get('input_count') != 92 or result.get('updated') != 92
            or result.get('readback_verified') is not True or result.get('other_identities_unchanged') is not True
            or result.get('supplier_calls') != 0 or len(result.get('rows', [])) != 92):
        raise ValueError('batch_acceptance_not_confirmed')
    expected = {r['external_hotel_id']: r['local_hotel_id'] for r in payload['rows']}
    actual = {str(r['external_hotel_id']): int(r['local_hotel_id']) for r in result['rows']}
    if actual != expected or any(r.get('decision_status') != 'accepted' for r in result['rows']):
        raise ValueError('batch_readback_identity_mismatch')
    return result


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--report', required=True, type=Path)
    p.add_argument('--apply', action='store_true')
    p.add_argument('--output', type=Path)
    a = p.parse_args()
    result = apply(a.report) if a.apply else {'status': 'prepared_not_applied', 'request': request(a.report)}
    raw = json.dumps(result, ensure_ascii=False, indent=2, sort_keys=True) + '\n'
    if a.output: a.output.write_text(raw, encoding='utf-8')
    print(json.dumps(result if a.apply else {'status': result['status'], 'count': len(result['request']['rows']), 'request_sha256': digest(result['request'])}, ensure_ascii=False, sort_keys=True))

if __name__ == '__main__':
    main()
