#!/usr/bin/env python3
"""Resume one <=30-hotel batch from persistent, frequency-ordered preview sightings."""
from collections import Counter
import csv
import hashlib
import json
import os
from pathlib import Path
import sys

import anex_search3_gap_queue as gaps

CHECKPOINT = 'anex-observed-hotel-checkpoint.json'
BOOTSTRAP_ARTIFACT = 10062698820


def legacy(directory):
    raw = (directory / gaps.CHECKPOINT).read_bytes()
    cp = gaps.validate_checkpoint(json.loads(raw), gaps.load_queue())
    if cp['in_flight'] or cp['completed_total'] != 90:
        raise ValueError('legacy handoff is not the reviewed 90 completed IDs')
    return hashlib.sha256(raw).hexdigest(), [
        {'external_id': r['external_id'], 'status': r['status'], 'reason': r['reason'],
         'row_sha256': gaps.digest(r)} for r in cp['rows']]


def validate(cp, history):
    sha, inherited = history
    if (cp.get('schema_version') != 1 or cp.get('scope') != 'preview'
            or cp.get('kind') != 'observed_search_hotels' or cp.get('legacy_sha256') != sha
            or cp.get('inherited') != inherited or cp.get('sources') != gaps.load_queue()['sources']):
        raise ValueError('observed checkpoint provenance mismatch')
    if any(not isinstance(cp.get(k), list) for k in ('rows', 'admissions', 'in_flight', 'import_finalized_ids')):
        raise ValueError('invalid observed checkpoint lists')
    old = [r['external_id'] for r in inherited]
    done = [r.get('external_id') for r in cp['rows']]
    pending = cp['in_flight']
    admitted = [r.get('anex_hotel_id') for r in cp['admissions']]
    if (any(type(i) is not int or not 0 < i < 100000000 for i in old + done + pending + admitted)
            or len(set(old + done + pending)) != len(old + done + pending)
            or len(set(admitted)) != len(admitted) or set(admitted) != set(done + pending)
            or len(pending) > 30 or len(admitted) > 50000
            or cp.get('completed_total') != len(old + done)
            or any(r.get('status') not in gaps.CLASSES for r in cp['rows'])):
        raise ValueError('observed checkpoint identities/counts mismatch')
    finalized = cp['import_finalized_ids']
    strong = {r['external_id'] for r in cp['rows'] if r['status'] == 'strong_candidate'}
    if len(set(finalized)) != len(finalized) or not set(finalized) <= strong:
        raise ValueError('unverified import finalization')
    return cp


def restore(directory):
    history = legacy(directory)
    path = directory / CHECKPOINT
    if path.exists():
        cp = json.loads(path.read_bytes())
    else:
        source = json.loads((directory / 'anex-checkpoint-source.json').read_bytes())
        if source.get('artifact_id') != BOOTSTRAP_ARTIFACT:
            raise ValueError('live checkpoint missing; refusing queue reset')
        cp = {'schema_version': 1, 'scope': 'preview', 'kind': 'observed_search_hotels',
              'legacy_sha256': history[0], 'inherited': history[1], 'sources': gaps.load_queue()['sources'],
              'rows': [], 'admissions': [], 'in_flight': [], 'import_finalized_ids': [],
              'completed_total': len(history[1])}
    return validate(cp, history)


def save(directory, cp):
    validate(cp, legacy(directory))
    target = directory / CHECKPOINT
    temporary = target.with_suffix('.tmp')
    temporary.write_text(json.dumps(cp, ensure_ascii=False, sort_keys=True, indent=2) + '\n')
    temporary.replace(target)


def completed(cp):
    return {r['external_id'] for r in cp['inherited'] + cp['rows']}


def needs_finalization(directory, cp):
    if cp['in_flight']:
        return False
    pending = cp.get('batch_needs_finalization')
    if pending is not None and type(pending) is not bool:
        raise ValueError('invalid finalization marker')
    if pending is None:
        # Older checkpoints have no marker. A verified but unfinished report
        # identifies the batch whose DB readback still needs to be completed.
        path = directory / 'anex-observed-hotel-report.json'
        if not path.exists():
            return False
        report = json.loads(path.read_bytes())
        pending = (report.get('completed_total') == cp['completed_total']
                   and report.get('checkpoint_readback_verified') is True
                   and report.get('previous_evidence_unchanged') is True
                   and 'import_status' not in report)
    if pending:
        previous = cp.get('previous_new_rows')
        if (type(previous) is not int or not 0 <= previous <= len(cp['rows'])
                or not 0 <= len(cp['rows']) - previous <= 30
                or cp.get('previous_completed') != len(cp['inherited']) + previous
                or gaps.digest(cp['rows'][:previous]) != cp.get('previous_rows_sha256')):
            raise ValueError('unverified unfinished batch')
    return pending


def snapshot():
    value = gaps.ssh_batch([], {}, 1, observations=True)
    if value.get('scope') != 'preview' or value.get('truncated') is not False:
        raise ValueError('incomplete live observations')
    all_ids = []
    for status in ('pending', 'manual_review'):
        rows = value[status]
        if len(rows) != value['counts'][status]:
            raise ValueError('observation counts disagree')
        for row in rows:
            for key in ('anex_hotel_id', 'country_id', 'anex_country_id', 'search_count'):
                text = str(row.get(key, ''))
                if not text.isdecimal() or not 0 < int(text) <= 2147483647:
                    raise ValueError('invalid observed identity/context')
                row[key] = int(text)
            all_ids.append(row['anex_hotel_id'])
    if (len(set(all_ids)) != len(all_ids)
            or value['counts']['observed'] != len(all_ids) + value['counts']['mapped']):
        raise ValueError('duplicate/incomplete observed set')
    return value


def eligible(cp, observed):
    excluded = completed(cp) | set(cp['in_flight'])
    return [r for r in observed['pending'] if r['anex_hotel_id'] not in excluded]


def merge(cp, rows):
    if (not isinstance(rows, list) or len(rows) != len(cp['in_flight'])
            or {r.get('external_id') for r in rows} != set(cp['in_flight'])
            or any(r.get('status') not in gaps.CLASSES for r in rows)):
        raise ValueError('batch does not match live reservation')
    return dict(cp, rows=cp['rows'] + rows, in_flight=[],
                completed_total=cp['completed_total'] + len(rows))


def export(directory, cp, observed):
    remaining = eligible(cp, observed)
    history = {r['external_id']: r for r in cp['inherited'] + cp['rows']}
    unresolved = {r['anex_hotel_id'] for r in observed['pending']}
    queues = {'pending': remaining, 'manual_decisions': observed['manual_review']}
    for status in ('review', 'source_error', 'unmatched', 'protected'):
        queues[status] = [r for i, r in history.items() if i in unresolved and r['status'] == status]
    for name, rows in queues.items():
        base = directory / ('anex-observed-hotel-' + name)
        base.with_suffix('.json').write_text(json.dumps({'count': len(rows), 'rows': rows}, ensure_ascii=False, indent=2) + '\n')
        with base.with_suffix('.csv').open('w', newline='') as handle:
            writer = csv.writer(handle)
            writer.writerow(['anex_hotel_id', 'hotel_name', 'country_id', 'search_count', 'status', 'reason'])
            for r in rows:
                cells = [r.get('external_id', r.get('anex_hotel_id')), r.get('hotel_name', ''),
                         r.get('country_id', ''), r.get('search_count', ''), r.get('status', name), r.get('reason', '')]
                writer.writerow(["'" + c if isinstance(c, str) and c.startswith(('=', '+', '-', '@', '\t', '\r')) else c for c in cells])
    result = {'schema_version': 1, 'scope': 'preview', 'source_sha': os.environ.get('GITHUB_SHA'),
              'counts': observed['counts'], 'remaining_new_ids': len(remaining),
              'completed_total': cp['completed_total'], 'inherited_completed': len(cp['inherited']),
              'live_completed': len(cp['rows']), 'in_flight': len(cp['in_flight']),
              'effective_mapped_count': observed['effective_mapped_count'],
              'queues': {k: len(v) for k, v in queues.items()}, 'pending': remaining}
    (directory / 'anex-observed-hotel-queue.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
    return {k: v for k, v in result.items() if k != 'pending'}


def approved_delta(path):
    path = Path(path)
    cp = restore(path.parent)
    if path.name != CHECKPOINT or cp['in_flight']:
        raise ValueError('unconfirmed live evidence')
    ready = [r for r in cp['rows'] if r['external_id'] not in cp['import_finalized_ids']]
    return gaps.verified_delta(path.parent, dict(cp, rows=ready), path.read_bytes(),
                               gaps.load_queue(), 'observed_initial_search:')


def process(directory, cp):
    wanted = set(cp['in_flight'])
    selected = [r for r in cp['admissions'] if r['anex_hotel_id'] in wanted]
    raw = (directory / 'anex-hotel-catalog-match.json').read_bytes()
    if hashlib.sha256(raw).hexdigest() != cp['sources']['catalog_sha256']:
        raise ValueError('catalog provenance changed')
    originals = {str(r['external_id']): r for r in json.loads(raw)['matches'] if r['external_id'] in wanted}
    ns = {}
    exec(gaps.matching_source(), ns)
    safe, rows = [], []
    for item in selected:
        identifier = item['anex_hotel_id']
        original = originals.get(str(identifier))
        if original is None:
            rows.append({'external_id': identifier, 'status': 'source_error', 'reason': 'original_catalog_missing', 'candidates': []})
        elif ns['country_match'](original['country'], item.get('country_name', '')) is not True:
            rows.append({'external_id': identifier, 'status': 'review', 'reason': 'observed_country_unverified', 'candidates': []})
        else:
            safe.append(item)
    result = gaps.ssh_batch(safe, originals, {str(r['anex_hotel_id']): r['country_id'] for r in safe})
    rows += result['rows']
    order = {i: n for n, i in enumerate(cp['in_flight'])}
    rows.sort(key=lambda r: order[r['external_id']])
    return merge(cp, rows), result['preservation']


def main():
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    cp = restore(directory)
    owner = os.environ['GITHUB_RUN_ID'] + ':' + os.environ['GITHUB_RUN_ATTEMPT']
    if '--prepare' in sys.argv:
        if needs_finalization(directory, cp):
            observed = snapshot()
            cp.update(reservation_owner=owner, batch_needs_finalization=True)
            save(directory, cp)
            report = export(directory, cp, observed)
            print(json.dumps(dict(report, reserved=0, recovered_unknown=0, resumed_finalization=True)))
            return
        previous_total = cp['completed_total']
        previous_rows = len(cp['rows'])
        recovered = bool(cp['in_flight'])
        if recovered:
            cp = merge(cp, [{'external_id': i, 'status': 'source_error',
                            'reason': 'interrupted_result_unknown', 'candidates': []} for i in cp['in_flight']])
        observed = snapshot()
        selected = [] if recovered else eligible(cp, observed)[:30]
        cp.update(in_flight=[r['anex_hotel_id'] for r in selected], admissions=cp['admissions'] + selected,
                  reservation_owner=owner, previous_completed=previous_total, previous_new_rows=previous_rows,
                  previous_rows_sha256=gaps.digest(cp['rows'][:previous_rows]), coverage_before=observed['counts'],
                  mappings_before=observed['effective_mapped_count'], batch_needs_finalization=recovered)
        save(directory, cp)
        report = export(directory, cp, observed)
        print(json.dumps(dict(report, reserved=len(selected), recovered_unknown=cp['completed_total'] - previous_total)))
        return
    if cp.get('reservation_owner') != owner:
        raise ValueError('current run reservation required')
    if '--finalize' in sys.argv:
        report = json.loads((directory / 'anex-observed-hotel-report.json').read_bytes())
        imported = json.loads((directory / 'anex-observed-hotel-mapping-import.json').read_bytes())
        if imported.get('status') not in ('imported', 'already_imported', 'no_new_strong_candidates'):
            raise ValueError('mapping import not confirmed')
        observed = snapshot()
        if observed['effective_mapped_count'] < cp['mappings_before']:
            raise ValueError('accepted mappings decreased')
        cp['import_finalized_ids'] = [r['external_id'] for r in cp['rows'] if r['status'] == 'strong_candidate']
        cp['batch_needs_finalization'] = False
        save(directory, cp)
        if restore(directory) != cp:
            raise ValueError('finalized checkpoint readback mismatch')
        report.update(export(directory, cp, observed), added_links=imported['inserted'],
                      coverage_before=cp['coverage_before'], coverage_after=observed['counts'],
                      import_status=imported['status'])
    else:
        preservation = None
        if cp['in_flight']:
            try:
                cp, preservation = process(directory, cp)
            except Exception as error:
                failure = dict(gaps.failure_report(error, 'observed_batch'), reserved_ids=cp['in_flight'],
                               completed_total=cp['completed_total'], source_sha=os.environ.get('GITHUB_SHA'))
                (directory / 'anex-observed-hotel-failure.json').write_text(json.dumps(failure, indent=2) + '\n')
                print(json.dumps(failure))
                raise RuntimeError('OBSERVED_BATCH_UNCONFIRMED: reservation preserved') from None
        if gaps.digest(cp['rows'][:cp['previous_new_rows']]) != cp['previous_rows_sha256']:
            raise ValueError('old completed evidence changed')
        cp['batch_needs_finalization'] = True
        save(directory, cp)
        # Re-read the file we will upload, so integrity is checked on persisted bytes as well.
        if restore(directory) != cp:
            raise ValueError('checkpoint readback mismatch')
        new_rows = cp['rows'][cp['previous_new_rows']:]
        report = {'source_sha': os.environ.get('GITHUB_SHA'), 'new_completed': len(new_rows), 'repeated_ids': 0,
                  'completed_total': cp['completed_total'], 'inherited_completed': len(cp['inherited']),
                  'new_counts': dict(Counter(r['status'] for r in new_rows)), 'preservation': preservation,
                  'checkpoint_readback_verified': True, 'previous_evidence_unchanged': True}
    (directory / 'anex-observed-hotel-report.json').write_text(json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True) + '\n')
    print(json.dumps(report, sort_keys=True))


if __name__ == '__main__':
    main()
