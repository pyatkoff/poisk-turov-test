#!/usr/bin/env python3
"""Four checked #1759 links through the existing writer; dry-run by default.

Use --local-root only in an authorized terminal on the AnyTour server. It invokes
exactly the same PHP writer without SSH or GitHub Actions. The durable receipt
must be outside webroot. Reserved/unknown results are never automatically replayed.
No supplier calls, new SQL, HTTP endpoint, or activation of historical queues.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import stat
import subprocess
import tempfile

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


def local_context(root, receipt):
    """Resolve only explicit local paths; never discover or read credentials."""
    root, receipt = Path(root), Path(receipt)
    resolved = root.resolve(strict=True)
    parent = receipt.parent.resolve(strict=True)
    if (not root.is_absolute() or root != resolved or not resolved.is_dir()
            or resolved.name != 'anytoour.ru'):
        raise ValueError('explicit_canonical_anytour_root_required')
    if (not receipt.is_absolute() or receipt.parent != parent or receipt.is_symlink()
            or parent == resolved or resolved in parent.parents
            or stat.S_IMODE(parent.stat().st_mode) & 0o077
            or parent.stat().st_uid != os.getuid()):
        raise ValueError('private_durable_receipt_outside_webroot_required')
    if resolved == Path(__file__).resolve().parent or resolved in Path(__file__).resolve().parents:
        raise ValueError('import_tools_must_stay_outside_webroot')
    helpers = [resolved / 'data/db-v1.php', resolved / 'v2/data/db-v1.php']
    if not any(p.is_file() and resolved in p.resolve().parents for p in helpers):
        raise ValueError('existing_anytour_database_helper_required')
    return resolved


def local_import(delta, root, directory):
    """Send the existing validated protocol to the unchanged sole SQL writer."""
    from anex_search_mapping_import import sanitize_row, write_protocol
    rows = [sanitize_row(row) for row in delta['rows']]
    meta = {key: value for key, value in delta.items() if key != 'rows'}
    meta.update(type='meta', protocol_version=1,
                mapping_digest=hashlib.sha256(canonical(delta) + b'\n').hexdigest(),
                rows_digest=hashlib.sha256(b''.join(canonical(r) + b'\n' for r in rows)).hexdigest())
    writer = Path(__file__).with_name('anex_search_mapping_writer.php').resolve(strict=True)
    if root == writer.parent or root in writer.parents:
        raise ValueError('writer_must_stay_outside_webroot')
    with tempfile.TemporaryDirectory(prefix='anex-link-protocol-', dir=directory) as tmp:
        protocol = Path(tmp) / 'protocol.ndjson'
        write_protocol(protocol, meta, rows)
        protocol.chmod(0o600)
        with protocol.open('rb') as handle:
            completed = subprocess.run(['php', '-d', 'display_errors=0', '-d', 'log_errors=0', str(writer)],
                cwd=root, stdin=handle, capture_output=True, timeout=900)
    if completed.returncode != 0 or len(completed.stdout) > 1000000:
        raise RuntimeError('local_writer_failed_receipt_reserved_do_not_replay')
    result = json.loads(completed.stdout)
    if result.get('mapping_digest') != meta['mapping_digest']:
        raise ValueError('local_writer_digest_unconfirmed')
    validate_result(result)
    return result


def apply(checkpoint, receipt, local_root=None):
    from anex_search_mapping_import import ssh_import
    checkpoint, receipt = Path(checkpoint), Path(receipt)
    delta = approved_delta(checkpoint)
    expected_digest = digest(delta)
    root = local_context(local_root, receipt) if local_root is not None else None
    if receipt.is_symlink():
        raise ValueError('receipt_symlink')
    if receipt.exists():
        previous = json.loads(receipt.read_bytes())
        if (previous.get('state') != 'finalized' or previous.get('delta_sha256') != expected_digest
                or previous.get('local_root') != (str(root) if root is not None else None)
                or previous.get('result_sha256') != digest(previous.get('result'))):
            raise ValueError('reserved_or_unknown_import_do_not_replay')
        validate_result(previous['result'])
        return {'status': 'already_finalized', 'new_database_writes': 0,
                'saved_result': previous['result']}
    reservation = {'state': 'reserved', 'delta_sha256': expected_digest,
                   'checked_report_sha256': REPORT_SHA256}
    if root is not None:
        reservation['local_root'] = str(root)
    save(receipt, reservation, exclusive=True)
    # Any failure, including a lost response after COMMIT, leaves reserved intact.
    result = local_import(delta, root, receipt.parent) if root is not None else ssh_import(
        receipt.with_name(receipt.name + '.mapping.json'), link_review_checkpoint=checkpoint)
    validate_result(result)
    save(receipt, dict(reservation, state='finalized', result=result,
                       result_sha256=digest(result)))
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--checkpoint', required=True, type=Path)
    parser.add_argument('--receipt', type=Path)
    parser.add_argument('--local-root', type=Path, help='Explicit canonical AnyTour root on this server; no SSH')
    parser.add_argument('--apply', action='store_true')
    args = parser.parse_args()
    if args.apply and args.receipt is None:
        parser.error('--apply requires a durable --receipt')
    result = apply(args.checkpoint, args.receipt, args.local_root) if args.apply else {
        'status': 'prepared_not_applied', 'delta': approved_delta(args.checkpoint)}
    print(json.dumps(result, ensure_ascii=False, sort_keys=True))


if __name__ == '__main__':
    main()
