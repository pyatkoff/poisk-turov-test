#!/usr/bin/env python3
"""Read local competitors for pinned cached source errors, without supplier calls/imports."""
import csv
from collections import Counter
import hashlib
import json
import os
from pathlib import Path
import sys

import anex_search3_cached_evidence_audit as audit
import anex_search3_gap_queue as gaps
import anex_search3_observed_queue as live
import anex_search3_owner_decisions as owner

CHECKPOINT = 'anex-cached-review-checkpoint.json'
REPORT = 'anex-cached-review-report.json'
BOOTSTRAP_ARTIFACT = 10079050364
PINNED_AUDIT_SHA = 'caacb640360fb3867c3faef622f39a3fead776911ec94af9de8982c462694254'
MAX_IDS = 14
FINAL_STATES = {'completed', 'interrupted_result_unknown', 'not_started'}


def job():
    return [os.environ.get('GITHUB_RUN_ID', ''), os.environ.get('GITHUB_RUN_ATTEMPT', '')]


def file_sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def source_rows(directory, raw_audit):
    if not isinstance(raw_audit, str) or hashlib.sha256(raw_audit.encode()).hexdigest() != PINNED_AUDIT_SHA:
        raise ValueError('unchecked cached evidence audit')
    report = json.loads(raw_audit)
    if report.get('kind') != 'historical_cached_evidence_audit' or report.get('scope') != 'preview':
        raise ValueError('unexpected cached audit scope')
    cp = live.restore(directory)
    if cp['in_flight'] or cp.get('batch_needs_finalization'):
        raise ValueError('live queue must be finalized before cached review')
    history = live.evidence_history(directory, cp)
    expected = gaps.load_queue()['sources']
    documents, sources = {}, {}
    for key, name in [('geo_sha256', 'anex-hotel-geo-enrichment.json'),
                      ('catalog_sha256', 'anex-hotel-catalog-match.json')]:
        path = directory / name
        sources[key] = file_sha(path)
        if sources[key] != expected[key] or report['protected_source_sha256'][name] != sources[key]:
            raise ValueError('cached review source digest changed')
        documents[key] = json.loads(path.read_bytes())
    geo = {r['external_id']: r for r in documents['geo_sha256']['rows']}
    originals = {r['external_id']: r for r in documents['catalog_sha256']['matches']}
    selected = [r for r in report['rows']
                if r.get('current_status') == 'source_error' and r.get('cached_api_usable') is True]
    if (not 1 <= len(selected) <= MAX_IDS
            or len({r['anex_hotel_id'] for r in selected}) != len(selected)
            or len(selected) != report['summary']['source_error_with_usable_cached_api']):
        raise ValueError('cached review selection exceeds checked source-error set')
    ns = {}
    exec(gaps.matching_source(), ns)
    rows = {}
    for item in selected:
        identifier = item['anex_hotel_id']
        current = history[identifier][0]
        cached = geo[identifier]
        # Reproduce the pinned audit's raw API/XML/country checks and scores.
        reproduced = audit.inspect(identifier, {'country_name': cached.get('api', {}).get('country')},
                                   current, cached, originals.get(identifier), ns)
        if (current['status'] != 'source_error' or reproduced != item
                or gaps.digest(cached) != item['cached_row_sha256']):
            raise ValueError('cached source-error evidence changed')
        rows[identifier] = {'external_id': identifier, 'api': cached['api'], 'xml': cached['xml'],
                            'live_row_sha256': gaps.digest(current),
                            'geo_row_sha256': gaps.digest(cached), 'audit_row_sha256': gaps.digest(item)}
    return rows, sources


def query_for(row, observation):
    country_id = observation.get('country_id')
    if type(country_id) is not int or not 0 < country_id <= 2147483647:
        raise ValueError('invalid cached review country')
    ns = {}
    exec(gaps.matching_source(), ns)
    if (observation.get('anex_hotel_id') != row['external_id']
            or ns['country_match'](observation.get('country_name'), row['api'].get('country')) is not True):
        raise ValueError('cached review current observation country changed')
    return {'key': row['external_id'], 'names': [row['api']['name'], row['xml']['name'],
            row['xml'].get('alternate_name', '')], 'country_id': country_id,
            'latitude': row['api'].get('latitude'), 'longitude': row['api'].get('longitude')}


def analyze(row, item):
    candidates = item.get('candidates')
    if (item.get('key') != row['external_id'] or not isinstance(candidates, list)
            or len(candidates) > 4097 or item.get('fetch_limit') != 4097
            or item.get('query_scope') != 'active_country_name_or_geobox'
            or item.get('candidate_set_complete') is not (len(candidates) < 4097)
            or any(not isinstance(c, dict) or type(c.get('id')) is not int or c['id'] <= 0 for c in candidates)
            or len({c['id'] for c in candidates}) != len(candidates)):
        raise ValueError('cached review candidate completeness unverified')
    ns = {}
    exec(gaps.matching_source(), ns)
    ranked = [ns['candidate_rank'](row['api'], row['xml'], c) for c in candidates]
    ranked.sort(key=lambda c: (-c['score'], c['id']))
    status, reason = ns['geo_decision'](row['api'], ranked, 'same_record',
                                      candidate_set_complete=item['candidate_set_complete'])
    return {'anex_hotel_id': row['external_id'], 'source_row_sha256': gaps.digest(row),
            'candidate_set_complete': item['candidate_set_complete'], 'fetch_limit': 4097,
            'query_scope': item['query_scope'], 'candidate_count': len(candidates),
            'raw_candidates': candidates, 'raw_candidates_sha256': gaps.digest(candidates),
            'ranked_candidates': ranked, 'best': ranked[0] if ranked else None,
            'score_margin': round(ranked[0]['score'] - ranked[1]['score'], 4) if len(ranked) > 1 else None,
            'proposal_status': status, 'proposal_reason': reason, 'inserted': 0,
            'policy': 'Historical supplier details plus fresh local competitors; proposal only.'}


def load(directory):
    cp = json.loads((directory / CHECKPOINT).read_bytes())
    rows, sources = source_rows(directory, cp.get('source_audit_raw'))
    ids = sorted(rows)
    admissions = cp.get('admissions', [])
    if (cp.get('schema_version') != 1 or cp.get('scope') != 'preview'
            or cp.get('kind') != 'cached_source_error_complete_reviews'
            or cp.get('sources') != sources or cp.get('audit_sha256') != PINNED_AUDIT_SHA
            or cp.get('source_artifact_id') != BOOTSTRAP_ARTIFACT
            or cp.get('source_digests') != {str(i): gaps.digest(rows[i]) for i in ids}
            or not isinstance(cp.get('reserved_by'), list) or len(cp['reserved_by']) != 2
            or not all(isinstance(v, str) for v in cp['reserved_by'])
            or not isinstance(admissions, list) or any(not isinstance(r, dict) for r in admissions)
            or len({r.get('anex_hotel_id') for r in admissions}) != len(admissions)
            or len(cp.get('batches', [])) != (len(ids) + 1) // 2):
        raise ValueError('cached review checkpoint provenance changed')
    admitted = {r['anex_hotel_id']: r for r in admissions}
    if not set(admitted) <= set(ids):
        raise ValueError('cached review admission exceeds pinned IDs')
    for n, batch in enumerate(cp['batches']):
        wanted = ids[n * 2:n * 2 + 2]
        request = batch.get('request', {})
        expected = {'mode': 'complete_review', 'queries': [query_for(rows[i], admitted[i])
                                                          for i in wanted if i in admitted]}
        if (batch.get('ids') != wanted or request != expected
                or batch.get('protected_ids') != [i for i in wanted if i not in admitted]
                or batch.get('request_sha256') != gaps.digest(request)
                or batch.get('state') not in FINAL_STATES | {'reserved', 'in_flight'}):
            raise ValueError('cached review reservation changed')
        if batch['state'] == 'completed':
            results = batch.get('results', [])
            keys = [q['key'] for q in request['queries']]
            if (len(results) != len(keys) or {r['anex_hotel_id'] for r in results} != set(keys)
                    or batch.get('results_sha256') != gaps.digest(results)):
                raise ValueError('cached review result digest changed')
            for result in results:
                item = {'key': result['anex_hotel_id'], 'candidates': result['raw_candidates'],
                        'candidate_set_complete': result['candidate_set_complete'],
                        'fetch_limit': result['fetch_limit'], 'query_scope': result['query_scope']}
                if analyze(rows[item['key']], item) != result:
                    raise ValueError('cached review score reproduction failed')
    return cp, rows


def save(directory, cp):
    owner.save(directory / CHECKPOINT, cp)
    if load(directory)[0] != cp:
        raise ValueError('cached review checkpoint readback failed')


def recover(cp, current_job):
    recovered = []
    for batch in cp['batches']:
        if (batch['state'] == 'in_flight'
                or (batch['state'] == 'reserved' and cp['reserved_by'] != current_job)):
            batch.update(state='interrupted_result_unknown', reason='previous_job_reservation_unconfirmed')
            recovered.extend(q['key'] for q in batch['request']['queries'])
    return recovered


def prepare(directory):
    if (directory / CHECKPOINT).exists():
        cp, _ = load(directory)
        recovered = recover(cp, job())
        save(directory, cp)
        return {'status': 'restored', 'recovered_unknown_ids': recovered, 'supplier_requests': 0}
    source = json.loads((directory / 'anex-checkpoint-source.json').read_bytes())
    if source.get('artifact_id') != BOOTSTRAP_ARTIFACT:
        raise ValueError('cached review checkpoint missing; refusing reset')
    raw_audit = (directory / audit.REPORT).read_bytes().decode('utf-8')
    rows, sources = source_rows(directory, raw_audit)
    pending = {r['anex_hotel_id']: r for r in live.snapshot()['pending']}
    ids = sorted(rows)
    batches = []
    for n in range(0, len(ids), 2):
        wanted = ids[n:n + 2]
        request = {'mode': 'complete_review', 'queries': [query_for(rows[i], pending[i])
                                                          for i in wanted if i in pending]}
        batches.append({'ids': wanted, 'state': 'reserved', 'request': request,
                        'request_sha256': gaps.digest(request),
                        'protected_ids': [i for i in wanted if i not in pending]})
    cp = {'schema_version': 1, 'scope': 'preview', 'kind': 'cached_source_error_complete_reviews',
          'sources': sources, 'source_digests': {str(i): gaps.digest(rows[i]) for i in ids},
          'source_artifact_id': source['artifact_id'], 'source_audit_raw': raw_audit,
          'audit_sha256': PINNED_AUDIT_SHA, 'batches': batches, 'reserved_by': job(),
          'admissions': [pending[i] for i in ids if i in pending]}
    save(directory, cp)
    return {'status': 'prepared', 'reserved_ids': [r['anex_hotel_id'] for r in cp['admissions']],
            'protected_ids': [i for b in batches for i in b['protected_ids']],
            'supplier_requests': 0, 'inserted': 0}


def protected(directory):
    names = [live.CHECKPOINT, gaps.CHECKPOINT, 'anex-hotel-geo-enrichment.json',
             'anex-hotel-catalog-match.json', 'anex-owner-hotel-decisions.json',
             'anex-complete-candidate-review-checkpoint.json',
             'anex-complete-candidate-review-acceptance.json']
    return {name: file_sha(directory / name) for name in names}


def run(directory):
    cp, rows = load(directory)
    if all(b['state'] in FINAL_STATES for b in cp['batches']):
        return {'status': 'already_completed', 'new_catalog_reads': 0, 'supplier_requests': 0, 'inserted': 0}
    if cp['reserved_by'] != job() or any(b['state'] == 'in_flight' for b in cp['batches']):
        raise ValueError('cached review requires current prepared reservation')
    before = protected(directory)
    source = Path(__file__).with_name('anex_catalog_reader.php').read_text().removeprefix('<?php')
    reads, checked = 0, []
    for batch in cp['batches']:
        if batch['state'] != 'reserved':
            continue
        batch['state'] = 'in_flight'
        save(directory, cp)
        queries = batch['request']['queries']
        try:
            response = owner.ssh_php(source, batch['request'], maximum_bytes=4000000) if queries else {'status': 'ok', 'items': []}
            items = response.get('items', [])
            if (response.get('status') != 'ok' or len(items) != len(queries)
                    or {r.get('key') for r in items} != {q['key'] for q in queries}):
                raise ValueError('local cached review response unconfirmed')
            results = [analyze(rows[r['key']], r) for r in items]
            batch.update(state='completed', results=results, results_sha256=gaps.digest(results))
            reads += len(queries)
            checked.extend(r['anex_hotel_id'] for r in results)
        except Exception as error:
            batch.update(state='interrupted_result_unknown', reason='catalog_read_unconfirmed',
                         diagnostic=gaps.failure_report(error, 'cached_review'))
            save(directory, cp)
            if protected(directory) != before:
                raise ValueError('cached review changed protected source evidence')
            return {'status': 'catalog_read_unconfirmed', 'new_catalog_reads_confirmed': reads,
                    'checked_ids': checked, 'supplier_requests': 0, 'inserted': 0}
        save(directory, cp)
    if protected(directory) != before:
        raise ValueError('cached review changed protected source evidence')
    return {'status': 'complete_local_candidates_saved', 'new_catalog_reads': reads,
            'checked_ids': checked, 'supplier_requests': 0, 'inserted': 0,
            'protected_evidence_unchanged': True}


def finalize(directory):
    cp, _ = load(directory)
    for batch in cp['batches']:
        if batch['state'] == 'reserved':
            batch.update(state='not_started', reason='read_not_started_before_finalization')
        elif batch['state'] == 'in_flight':
            batch.update(state='interrupted_result_unknown', reason='catalog_read_unconfirmed')
    save(directory, cp)
    results = [r for b in cp['batches'] if b['state'] == 'completed' for r in b['results']]
    strong = [r for r in results if r['proposal_status'] == 'strong_candidate']
    summary = {'selected_ids': len(cp['source_digests']), 'checked_ids': len(results),
               'proposal_counts': dict(Counter(r['proposal_status'] for r in results)),
               'proposal_reasons': dict(Counter(r['proposal_reason'] for r in results)),
               'deferred_ids': [q['key'] for b in cp['batches'] if b['state'] != 'completed' for q in b['request']['queries']],
               'protected_ids': [i for b in cp['batches'] for i in b['protected_ids']],
               'strict_proposal_pairs': [{'anex_hotel_id': r['anex_hotel_id'], 'catalog_hotel_id': r['best']['id'],
                                          'name_similarity': r['best']['name_similarity'],
                                          'distance_m': r['best']['distance_m'], 'score_margin': r['score_margin']}
                                         for r in strong],
               'supplier_requests': 0, 'inserted': 0, 'live_source_errors_unchanged': True}
    report = {'schema_version': 1, 'scope': 'preview', 'kind': 'cached_source_error_read_only_review',
              'audit_sha256': PINNED_AUDIT_SHA, 'source_artifact_id': cp['source_artifact_id'],
              'checkpoint_sha256': file_sha(directory / CHECKPOINT), 'source_digests': cp['source_digests'],
              'summary': summary, 'results': results, 'results_sha256': gaps.digest(results),
              'policy': 'Proposals only. A separate checked result digest and strict importer are required before acceptance.'}
    owner.save(directory / REPORT, report)
    path = directory / REPORT.replace('.json', '.csv')
    cells = [['anex_hotel_id', 'catalog_hotel_id', 'candidate_count', 'candidate_set_complete',
              'proposal_status', 'proposal_reason', 'name_similarity', 'distance_m', 'score_margin']]
    for r in results:
        best = r['best'] or {}
        cells.append([str(v) for v in [r['anex_hotel_id'], best.get('id', ''), r['candidate_count'],
                      r['candidate_set_complete'], r['proposal_status'], r['proposal_reason'],
                      best.get('name_similarity', ''), best.get('distance_m', ''), r['score_margin']]])
    with path.open('w', newline='') as handle:
        csv.writer(handle).writerows(cells)
    with path.open(newline='') as handle:
        if list(csv.reader(handle)) != cells:
            raise ValueError('cached review CSV readback mismatch')
    return dict(summary, checkpoint_sha256=file_sha(directory / CHECKPOINT),
                report=REPORT, report_sha256=file_sha(directory / REPORT), report_readback_verified=True)


if __name__ == '__main__':
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    action = {'--prepare': prepare, '--run': run, '--finalize': finalize}
    if len(sys.argv) != 2 or sys.argv[1] not in action:
        raise SystemExit('expected --prepare, --run or --finalize')
    result = action[sys.argv[1]](directory)
    print(json.dumps(result, ensure_ascii=False))
    if result.get('status') == 'catalog_read_unconfirmed':
        raise SystemExit(1)
