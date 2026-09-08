#!/usr/bin/env python3
"""Read complete candidate competition for two saved records; never accept links."""
import csv
import hashlib
import json
import os
from pathlib import Path

import anex_search3_gap_queue as gaps
import anex_search3_observed_queue as live
import anex_search3_owner_decisions as owner

IDS = {32832, 32875}
SOURCE_DIGESTS = {32832: 'b37760c9866cf13a47ab3c814d351fcbd616b3dc53dd19bc9db5f2aa5df9c235',
                  32875: 'f0754d028b244ca9066a2b4d5636d771b0239ffd09d677d51102e1c76c074bff'}
CHECKPOINT = 'anex-complete-candidate-review-checkpoint.json'
REPORT = 'anex-complete-candidate-review-report.json'
CHECKED_CHECKPOINT_SHA = '2d45340a3643f09b7f3b0b7cd5c9d91549a14ebbc024fa33e98b69cf2219704e'
ACCEPTANCE = 'anex-complete-candidate-review-acceptance.json'


def analyze(row, item):
    candidates = item.get('candidates')
    if (item.get('key') != row['external_id'] or not isinstance(candidates, list)
            or len(candidates) > 4097 or item.get('fetch_limit') != 4097
            or item.get('query_scope') != 'active_country_name_or_geobox'
            or item.get('candidate_set_complete') is not (len(candidates) < 4097)
            or any(type(c.get('id')) is not int or c['id'] <= 0 for c in candidates)
            or len({c['id'] for c in candidates}) != len(candidates)):
        raise ValueError('complete candidate proof invalid')
    ns = {}
    exec(gaps.matching_source(), ns)
    ranked = [ns['candidate_rank'](row['api'], row['xml'], c) for c in candidates]
    ranked.sort(key=lambda c: (-c['score'], c['id']))
    best = ranked[0] if ranked else None
    margin = round(best['score'] - ranked[1]['score'], 4) if len(ranked) > 1 else None
    return {'anex_hotel_id': row['external_id'], 'source_row_sha256': gaps.digest(row),
            'candidate_set_complete': item['candidate_set_complete'], 'candidate_count': len(candidates),
            'fetch_limit': 4097, 'query_scope': item['query_scope'], 'best': best, 'score_margin': margin,
            'original_best_id': row['candidates'][0]['id'], 'ranked_candidates': ranked,
            'raw_candidates': candidates, 'raw_candidates_sha256': gaps.digest(candidates),
            'automatic_acceptance': False, 'status': 'read_only_review'}


def approved_delta(path):
    """Reproduce both complete sets and unchanged strong gates from the checked artifact."""
    path = Path(path)
    raw = path.read_bytes()
    if path.name != CHECKPOINT or hashlib.sha256(raw).hexdigest() != CHECKED_CHECKPOINT_SHA:
        raise ValueError('unchecked complete-review checkpoint')
    cp = json.loads(raw)
    if cp.get('state') != 'completed' or gaps.digest(cp['results']) != cp['results_sha256']:
        raise ValueError('complete-review result not confirmed')
    if {r['anex_hotel_id'] for r in cp['results']} != IDS or len(cp['results']) != 2:
        raise ValueError('complete-review acceptance is limited to two checked records')
    directory = path.parent
    live_cp = live.restore(directory)
    if live_cp['in_flight']:
        raise ValueError('live batch must finish before complete-review import')
    history = live.evidence_history(directory, live_cp)
    sources, documents = {}, {}
    expected_sources = gaps.load_queue()['sources']
    for key, filename in [('catalog_sha256', 'anex-hotel-catalog-match.json'),
                          ('geo_sha256', 'anex-hotel-geo-enrichment.json')]:
        content = (directory / filename).read_bytes()
        sources[key] = hashlib.sha256(content).hexdigest()
        if sources[key] != expected_sources[key]:
            raise ValueError('baseline provenance changed')
        documents[key] = json.loads(content)
    originals = {r['external_id']: r for r in documents['catalog_sha256']['matches']}
    baseline_accepted = {r['external_id'] for r in documents['catalog_sha256']['matches'] if r['status'] == 'verified_auto'}
    baseline_accepted |= {r['external_id'] for r in documents['geo_sha256']['rows'] if r['status'] == 'strong_candidate'}
    ns = {}
    exec(gaps.matching_source(), ns)
    delta = []
    for result in cp['results']:
        identifier = result['anex_hotel_id']
        row = history[identifier][0]
        if identifier in baseline_accepted or gaps.digest(row) != SOURCE_DIGESTS[identifier]:
            raise ValueError('complete-review source identity changed')
        original = originals[identifier]
        xml = {'id': identifier, 'name': original['name'], 'alternate_name': original['alternate_name'],
               'town_id': original.get('town_id')}
        if (row['xml'] != xml or ns['xml_relation'](xml, row['api']) != 'same_record'
                or ns['country_match'](original['country'], row['api']['country']) is not True):
            raise ValueError('supplier identity not verified')
        item = {'key': identifier, 'candidates': result['raw_candidates'],
                'candidate_set_complete': result['candidate_set_complete'],
                'fetch_limit': result['fetch_limit'], 'query_scope': result['query_scope']}
        if analyze(row, item) != result or result['candidate_set_complete'] is not True:
            raise ValueError('full candidate evidence was not reproduced')
        status, reason = ns['geo_decision'](row['api'], result['ranked_candidates'], 'same_record', candidate_set_complete=True)
        if status != 'strong_candidate' or reason != 'name_country_coordinates':
            raise ValueError('complete set does not satisfy strict strong criteria')
        delta.append({'anex_hotel_id': identifier, 'catalog_hotel_id': result['best']['id'],
                      'match_class': status, 'reason': 'observed_complete_review:' + reason,
                      'source_row_digest': gaps.digest(result)})
    sources['gap_sha256'] = CHECKED_CHECKPOINT_SHA
    return {'schema_version': 1, 'scope': 'preview', 'approval_policy': 'owner_exact_and_strong_20260908',
            'append_only': True, 'sources': sources, 'rows': sorted(delta, key=lambda r: r['anex_hotel_id']),
            'counts': {'exact': 0, 'strong': len(delta), 'total': len(delta),
                       'unique_catalog_hotels': len({r['catalog_hotel_id'] for r in delta})}}


def accept(directory):
    from anex_search_mapping_import import ssh_import
    delta = approved_delta(directory / CHECKPOINT)
    path = directory / ACCEPTANCE
    previous = json.loads(path.read_bytes()) if path.exists() else None
    if previous is not None and previous.get('delta_sha256') != gaps.digest(delta):
        raise ValueError('complete-review acceptance changed')
    if previous is not None and previous.get('state') == 'finalized':
        return {'status': 'already_finalized', 'inserted': 0, 'supplier_requests': 0}
    before_live = gaps.digest(live.restore(directory))
    before = live.snapshot()
    checkpoint = {'state': 'prepared', 'delta_sha256': gaps.digest(delta), 'coverage_before': before['counts']}
    owner.save(path, checkpoint)
    imported = ssh_import(directory / 'anex-complete-candidate-review-mappings.json',
                          complete_review_checkpoint=directory / CHECKPOINT)
    after = live.snapshot()
    if after['effective_mapped_count'] < before['effective_mapped_count']:
        raise ValueError('accepted mappings decreased')
    live_cp = live.restore(directory)
    if gaps.digest(live_cp) != before_live:
        raise ValueError('complete-review import changed live checkpoint')
    preservation = gaps.ssh_batch([], {}, 1)['preservation']
    report = dict(live.export(directory, live_cp, after),
                  coverage_before=before['counts'], coverage_after=after['counts'], import_result=imported,
                  supplier_requests=0, new_completed=0, old_live_checkpoint_unchanged=True,
                  database_readback=preservation)
    checkpoint.update(state='finalized', import_result=imported, report=report)
    owner.save(path, checkpoint)
    owner.save(directory / 'anex-complete-candidate-review-acceptance-report.json', report)
    return report


def run(directory):
    cp = live.restore(directory)
    original_digest = gaps.digest(cp)
    if cp['in_flight'] or cp.get('batch_needs_finalization'):
        raise ValueError('live queue must be finalized first')
    history = live.evidence_history(directory, cp)
    rows = {}
    for identifier in sorted(IDS):
        row = history[identifier][0]
        if (gaps.digest(row) != SOURCE_DIGESTS[identifier] or row['status'] != 'review'
                or row['reason'] != 'candidate_limit_reached' or row['api']['id'] != identifier):
            raise ValueError('saved review identity changed')
        rows[identifier] = row
    source_digests = {str(i): SOURCE_DIGESTS[i] for i in sorted(IDS)}
    path = directory / CHECKPOINT
    previous = json.loads(path.read_bytes()) if path.exists() else None
    if previous is not None:
        if previous.get('source_digests') != source_digests:
            raise ValueError('complete review checkpoint source changed')
        if previous.get('state') == 'completed':
            if gaps.digest(previous['results']) != previous.get('results_sha256'):
                raise ValueError('complete review checkpoint result changed')
            return {'status': 'already_completed', 'new_catalog_reads': 0, 'supplier_requests': 0}
    observed = live.snapshot()
    eligible = {r['anex_hotel_id']: r for r in observed['pending']}
    selected = [i for i in sorted(IDS) if i in eligible]
    queries = [{'key': i, 'names': [rows[i]['api']['name'], rows[i]['xml']['name'],
                rows[i]['xml'].get('alternate_name', '')], 'country_id': eligible[i]['country_id'],
                'latitude': rows[i]['api'].get('latitude'), 'longitude': rows[i]['api'].get('longitude')}
               for i in selected]
    checkpoint = {'state': 'prepared', 'source_digests': source_digests,
                  'request': {'mode': 'complete_review', 'queries': queries},
                  'protected_ids': sorted(IDS - set(selected)), 'source_live_checkpoint_sha256': original_digest}
    owner.save(path, checkpoint)
    results = []
    if queries:
        source = Path(__file__).with_name('anex_catalog_reader.php').read_text().removeprefix('<?php')
        response = owner.ssh_php(source, checkpoint['request'], maximum_bytes=4000000)
        items = response.get('items', [])
        if (response.get('status') != 'ok' or len(items) != len(selected)
                or {r.get('key') for r in items} != set(selected)):
            raise ValueError('complete catalog read unconfirmed')
        results = [analyze(rows[item['key']], item) for item in items]
    if gaps.digest(live.restore(directory)) != original_digest:
        raise ValueError('complete review changed live evidence')
    checkpoint.update(state='completed', results=results, results_sha256=gaps.digest(results))
    summary = {'status': 'read_only_review_saved', 'new_catalog_reads': len(selected),
               'supplier_requests': 0, 'inserted': 0, 'protected_ids': checkpoint['protected_ids'],
               'previous_live_evidence_unchanged': True, 'checkpoint_readback_verified': True,
               'rows': [{k: v for k, v in r.items() if k not in ('raw_candidates', 'ranked_candidates')} for r in results]}
    csv_path = directory / REPORT.replace('.json', '.csv')
    cells = [['anex_hotel_id', 'catalog_hotel_id', 'candidate_count', 'candidate_set_complete',
              'name_similarity', 'distance_m', 'score_margin', 'automatic_acceptance']]
    for r in results:
        best = r['best'] or {}
        cells.append([str(v) for v in [r['anex_hotel_id'], best.get('id', ''), r['candidate_count'],
            r['candidate_set_complete'], best.get('name_similarity', ''), best.get('distance_m', ''),
            r['score_margin'], False]])
    with csv_path.open('w', newline='') as handle:
        csv.writer(handle).writerows(cells)
    with csv_path.open(newline='') as handle:
        if list(csv.reader(handle)) != cells:
            raise ValueError('complete review CSV readback mismatch')
    owner.save(path, checkpoint)
    summary['checkpoint_sha256'] = hashlib.sha256(path.read_bytes()).hexdigest()
    owner.save(directory / REPORT, summary)
    return summary


if __name__ == '__main__':
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    try:
        action = accept if '--accept' in __import__('sys').argv else run
        print(json.dumps(action(directory), ensure_ascii=False, sort_keys=True))
    except Exception as error:
        report = gaps.failure_report(error, 'complete_candidate_review')
        owner.save(directory / 'anex-complete-candidate-review-failure.json', report)
        print(json.dumps(report))
        raise SystemExit(1) from None
