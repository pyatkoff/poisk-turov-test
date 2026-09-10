#!/usr/bin/env python3
"""One pinned 92-identity promotion; no suppliers or automatic retries."""
from __future__ import annotations
import argparse
import hashlib
import json
from pathlib import Path

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
        detail = item['detail']
        source, local, geo = detail['source'], detail['local_hotel'], detail['geography']
        if (item.get('live_guards_checked') is not False or geo.get('status') != 'supported'
                or item['local_hotel_id'] != local['id'] or item['country_id'] != local['country_id']
                or str(item['external_id']) != str(source['id'])
                or detail['expected_decision_status'] != 'pending'
                or item['expected_evidence_sha256'] != detail['expected_evidence_sha256']
                or digest(source) != item['source_row_sha256']):
            raise ValueError('proposal_evidence_incomplete')
        rows.append({'external_hotel_id': str(item['external_id']), 'local_hotel_id': local['id'],
                     'country_id': local['country_id'], 'expected_evidence_sha256': item['expected_evidence_sha256'],
                     'expected_catalog_sha256': detail['expected_catalog_sha256'],
                     'source_row_sha256': item['source_row_sha256'], 'source': source,
                     'expected_local': local, 'geography': geo,
                     'anex_bridges': detail['anex_bridges'],
                     'category_difference': bool(detail['category_difference'])})
    rows.sort(key=lambda row: int(row['external_hotel_id']))
    if len(rows) != 92 or len({r['external_hotel_id'] for r in rows}) != 92 or len({r['local_hotel_id'] for r in rows}) != 92:
        raise ValueError('validated_92_set_changed')
    if {r['country_id'] for r in rows} != {1, 4}:
        raise ValueError('unexpected_country_set')
    return rows


def request(path: Path):
    return {'schema_version': 1, 'operation_id': OPERATION_ID,
            'source_report_sha256': REPORT_CANONICAL_SHA256, 'append_only_decisions': True,
            'supplier_calls': 0, 'rows': load_proposals(path)}


def validate_result(result, payload):
    if (result.get('status') != 'accepted' or result.get('operation_id') != OPERATION_ID
            or result.get('request_sha256') != digest(payload) or result.get('input_count') != 92
            or result.get('updated') != 92 or result.get('readback_verified') is not True
            or result.get('other_identities_unchanged') is not True or result.get('supplier_calls') != 0
            or len(result.get('rows', [])) != 92):
        raise ValueError('batch_acceptance_not_confirmed')
    expected = {r['external_hotel_id']: r['local_hotel_id'] for r in payload['rows']}
    actual = {str(r['external_hotel_id']): int(r['local_hotel_id']) for r in result['rows']}
    if actual != expected or any(r.get('decision_status') != 'accepted' for r in result['rows']):
        raise ValueError('batch_readback_identity_mismatch')


def apply(report_path: Path, receipt: Path):
    from anex_tourvisor_link_import import save
    import anex_search3_owner_decisions as owner
    payload = request(report_path)
    request_hash = digest(payload)
    if receipt.is_symlink():
        raise ValueError('receipt_symlink')
    if receipt.exists():
        previous = json.loads(receipt.read_bytes())
        if (previous.get('state') != 'finalized' or previous.get('request_sha256') != request_hash
                or previous.get('result_sha256') != digest(previous.get('result'))):
            raise ValueError('reserved_or_unknown_operation_do_not_replay')
        validate_result(previous['result'], payload)
        return {'status': 'already_finalized', 'new_database_writes': 0, 'saved_result': previous['result']}
    reservation = {'state': 'reserved', 'operation_id': OPERATION_ID, 'request_sha256': request_hash}
    save(receipt, reservation, exclusive=True)
    root = Path(__file__).resolve().parents[2]
    source = (root/'app/integrations/anex-search-mapping-registry.php').read_text().removeprefix('<?php')
    source += '\n' + Path(__file__).with_suffix('.php').read_text().removeprefix('<?php')
    result = owner.ssh_php(source, payload, maximum_bytes=65536)
    save(receipt.with_name(receipt.name + '.outcome.json'), result, exclusive=True)
    validate_result(result, payload)
    save(receipt, dict(reservation, state='finalized', result=result, result_sha256=digest(result)))
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--report', required=True, type=Path)
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--receipt', type=Path)
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    if args.apply and args.receipt is None:
        parser.error('--apply requires a durable restored --receipt')
    result = apply(args.report, args.receipt) if args.apply else {'status': 'prepared_not_applied', 'request': request(args.report)}
    if args.output:
        args.output.write_text(json.dumps(result, ensure_ascii=False, indent=2, sort_keys=True) + '\n')
    print(json.dumps(result if args.apply else {'status': result['status'], 'count': len(result['request']['rows']), 'request_sha256': digest(result['request'])}, ensure_ascii=False, sort_keys=True))


if __name__ == '__main__':
    main()
