#!/usr/bin/env python3
"""Add complete alias evidence to eight pinned cached reviews without repeating candidate reads."""
import csv
from collections import Counter
import hashlib
import json
import os
from pathlib import Path
import sys

import anex_search3_alias_review as alias
import anex_search3_cached_review as cached
import anex_search3_gap_queue as gaps
import anex_search3_observed_queue as live
import anex_search3_owner_decisions as owner

CHECKPOINT = 'anex-cached-alias-review-checkpoint.json'
REPORT = 'anex-cached-alias-review-report.json'
SOURCE_CHECKPOINT_SHA = '370b406754bd71d7e74e1ab20f9728163db7727ee56f819e911d9f7b5b33c921'
BOOTSTRAP_ARTIFACT = 10081341737
EXPECTED_IDS = 8
FINAL_STATES = {'completed', 'interrupted_result_unknown', 'not_started'}


def job():
    return [os.environ.get('GITHUB_RUN_ID', ''), os.environ.get('GITHUB_RUN_ATTEMPT', '')]


def file_sha(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def source_data(directory):
    directory = Path(directory)
    if file_sha(directory / cached.CHECKPOINT) != SOURCE_CHECKPOINT_SHA:
        raise ValueError('cached alias source checkpoint changed')
    source_cp, rows = cached.load(directory)
    results = [result for batch in source_cp['batches'] if batch['state'] == 'completed'
               for result in batch['results']]
    selected = {result['anex_hotel_id']: result for result in results
                if result.get('proposal_status') == 'review'}
    if (len(results) != cached.MAX_IDS or len(selected) != EXPECTED_IDS
            or any(result.get('candidate_set_complete') is not True
                   or result.get('fetch_limit') != 4097
                   or len(result.get('raw_candidates', [])) < 1
                   for result in selected.values())
            or set(selected) - set(rows)):
        raise ValueError('cached alias review selection changed')
    sources = {'cached_checkpoint_sha256': SOURCE_CHECKPOINT_SHA,
               'catalog_sha256': source_cp['sources']['catalog_sha256'],
               'geo_sha256': source_cp['sources']['geo_sha256']}
    return rows, selected, sources


def query_for(identifier, result):
    ids = sorted(candidate['id'] for candidate in result['raw_candidates'])
    if (len(ids) != len(set(ids)) or len(ids) != result['candidate_count']
            or any(type(value) is not int or value < 1 for value in ids)):
        raise ValueError('cached candidate IDs changed')
    return {'key': identifier, 'candidate_ids': ids}


def merge_aliases(row, source_result, item):
    candidates = source_result['raw_candidates']
    candidate_ids = {candidate['id'] for candidate in candidates}
    aliases = item.get('aliases')
    if (item.get('key') != row['external_id'] or not isinstance(aliases, list)
            or item.get('query_scope') != 'pinned_complete_candidate_ids_aliases'
            or item.get('candidate_count') != len(candidates)
            or item.get('alias_set_complete') is not True
            or item.get('alias_fetch_limit') != 8193
            or item.get('alias_rows') != len(aliases)
            or len(aliases) > 8192
            or any(not isinstance(value, dict) or type(value.get('hotel_id')) is not int
                   or value['hotel_id'] not in candidate_ids
                   or not alias.validate_alias(value) for value in aliases)
            or len({(value['hotel_id'], value['alias'], value['normalized_alias'], value['source'])
                    for value in aliases}) != len(aliases)):
        raise ValueError('cached alias rows incomplete')
    by_hotel = {identifier: [] for identifier in candidate_ids}
    for value in aliases:
        by_hotel[value['hotel_id']].append({
            'alias': value['alias'], 'normalized_alias': value['normalized_alias'],
            'source': value['source']})
    merged = []
    for candidate in candidates:
        value = dict(candidate)
        value['aliases'] = by_hotel[candidate['id']]
        merged.append(value)
    evidence = {'key': row['external_id'], 'candidates': merged,
                'candidate_set_complete': source_result['candidate_set_complete'],
                'fetch_limit': source_result['fetch_limit'],
                'alias_set_complete': item['alias_set_complete'],
                'alias_fetch_limit': item['alias_fetch_limit'],
                'alias_rows': item['alias_rows'],
                'query_scope': 'pinned_complete_candidates_plus_aliases'}
    result = alias.analyze(row, evidence)
    result['canonical_result_sha256'] = gaps.digest(source_result)
    result['alias_rows_sha256'] = gaps.digest(aliases)
    result['policy'] = ('Proposal only: pinned complete canonical candidates plus complete '
                        'generated-alias evidence; no supplier calls.')
    return result


def load(directory):
    directory = Path(directory)
    cp = json.loads((directory / CHECKPOINT).read_bytes())
    rows, selected, sources = source_data(directory)
    manifest = sorted(selected)
    active = sorted(cp.get('active_ids', []))
    protected = sorted(cp.get('protected_ids', []))
    if (cp.get('schema_version') != 1 or cp.get('scope') != 'preview'
            or cp.get('kind') != 'cached_complete_alias_reviews'
            or cp.get('sources') != sources or cp.get('source_artifact_id') != BOOTSTRAP_ARTIFACT
            or cp.get('manifest_ids') != manifest or sorted(active + protected) != manifest
            or set(active) & set(protected)
            or cp.get('source_digests') != {
                str(identifier): {
                    'row': gaps.digest(rows[identifier]),
                    'canonical_result': gaps.digest(selected[identifier]),
                } for identifier in active}
            or not isinstance(cp.get('reserved_by'), list) or len(cp['reserved_by']) != 2
            or len(cp.get('batches', [])) != len(active)):
        raise ValueError('cached alias checkpoint provenance changed')
    for index, batch in enumerate(cp['batches']):
        identifier = active[index]
        request = {'mode': 'cached_alias_review',
                   'queries': [query_for(identifier, selected[identifier])]}
        if (batch.get('ids') != [identifier] or batch.get('request') != request
                or batch.get('request_sha256') != gaps.digest(request)
                or batch.get('state') not in FINAL_STATES | {'reserved', 'in_flight'}):
            raise ValueError('cached alias reservation changed')
        if batch['state'] == 'completed':
            results = batch.get('results', [])
            if (len(results) != 1 or results[0].get('anex_hotel_id') != identifier
                    or batch.get('results_sha256') != gaps.digest(results)):
                raise ValueError('cached alias result digest changed')
            item = batch.get('alias_response')
            if (not isinstance(item, dict)
                    or merge_aliases(rows[identifier], selected[identifier], item) != results[0]):
                raise ValueError('cached alias score reproduction failed')
    return cp, rows, selected


def save(directory, cp):
    owner.save(Path(directory) / CHECKPOINT, cp)
    if load(directory)[0] != cp:
        raise ValueError('cached alias checkpoint readback failed')


def protected(directory):
    names = [live.CHECKPOINT, gaps.CHECKPOINT, 'anex-hotel-geo-enrichment.json',
             'anex-hotel-catalog-match.json', 'anex-owner-hotel-decisions.json',
             cached.CHECKPOINT, cached.REPORT, cached.ACCEPTANCE,
             alias.CHECKPOINT, alias.REPORT]
    return {name: file_sha(Path(directory) / name) for name in names}


def prepare(directory):
    directory = Path(directory)
    if (directory / CHECKPOINT).exists():
        cp, _, _ = load(directory)
        recovered = []
        for batch in cp['batches']:
            if (batch['state'] == 'in_flight'
                    or (batch['state'] == 'reserved' and cp['reserved_by'] != job())):
                batch.update(state='interrupted_result_unknown',
                             reason='previous_job_reservation_unconfirmed')
                recovered.extend(batch['ids'])
        save(directory, cp)
        return {'status': 'restored', 'recovered_unknown_ids': recovered,
                'supplier_requests': 0, 'new_canonical_reads': 0}
    source = json.loads((directory / 'anex-checkpoint-source.json').read_bytes())
    if source.get('artifact_id') != BOOTSTRAP_ARTIFACT:
        raise ValueError('cached alias checkpoint missing; refusing reset')
    rows, selected, sources = source_data(directory)
    pending = {row['anex_hotel_id']: row for row in live.snapshot()['pending']}
    active = sorted(set(selected) & set(pending))
    protected_ids = sorted(set(selected) - set(active))
    batches = []
    for identifier in active:
        request = {'mode': 'cached_alias_review',
                   'queries': [query_for(identifier, selected[identifier])]}
        batches.append({'ids': [identifier], 'state': 'reserved', 'request': request,
                        'request_sha256': gaps.digest(request)})
    cp = {'schema_version': 1, 'scope': 'preview', 'kind': 'cached_complete_alias_reviews',
          'sources': sources, 'source_artifact_id': BOOTSTRAP_ARTIFACT,
          'manifest_ids': sorted(selected), 'active_ids': active, 'protected_ids': protected_ids,
          'source_digests': {str(identifier): {
              'row': gaps.digest(rows[identifier]),
              'canonical_result': gaps.digest(selected[identifier]),
          } for identifier in active},
          'observation_digests': {str(identifier): gaps.digest(pending[identifier])
                                  for identifier in active},
          'reserved_by': job(), 'batches': batches}
    save(directory, cp)
    return {'status': 'prepared', 'reserved_ids': active, 'protected_ids': protected_ids,
            'supplier_requests': 0, 'new_canonical_reads': 0}


def run(directory):
    directory = Path(directory)
    cp, rows, selected = load(directory)
    if all(batch['state'] in FINAL_STATES for batch in cp['batches']):
        return {'status': 'already_completed', 'new_alias_reads': 0,
                'new_canonical_reads': 0, 'supplier_requests': 0, 'inserted': 0}
    if cp['reserved_by'] != job() or any(batch['state'] == 'in_flight' for batch in cp['batches']):
        raise ValueError('cached alias review requires current prepared reservation')
    before = protected(directory)
    source = Path(__file__).with_name('anex_cached_alias_reader.php').read_text().removeprefix('<?php')
    reads, checked = 0, []
    for batch in cp['batches']:
        if batch['state'] != 'reserved':
            continue
        batch['state'] = 'in_flight'
        save(directory, cp)
        identifier = batch['ids'][0]
        try:
            response = owner.ssh_php(source, batch['request'], maximum_bytes=4000000)
            items = response.get('items', [])
            if (response.get('status') != 'ok' or len(items) != 1
                    or items[0].get('key') != identifier):
                raise ValueError('cached alias response unconfirmed')
            result = merge_aliases(rows[identifier], selected[identifier], items[0])
            batch.update(state='completed', alias_response=items[0], results=[result],
                         results_sha256=gaps.digest([result]))
            reads += 1
            checked.append(identifier)
        except Exception as error:
            batch.update(state='interrupted_result_unknown',
                         reason='cached_alias_read_unconfirmed',
                         diagnostic=gaps.failure_report(error, 'cached_alias_review'))
            save(directory, cp)
            if protected(directory) != before:
                raise ValueError('cached alias review changed protected evidence')
            return {'status': 'alias_read_unconfirmed', 'new_alias_reads_confirmed': reads,
                    'checked_ids': checked, 'new_canonical_reads': 0,
                    'supplier_requests': 0, 'inserted': 0}
        save(directory, cp)
    if protected(directory) != before:
        raise ValueError('cached alias review changed protected evidence')
    return {'status': 'cached_alias_evidence_saved', 'new_alias_reads': reads,
            'checked_ids': checked, 'new_canonical_reads': 0,
            'supplier_requests': 0, 'inserted': 0}


def finalize(directory):
    directory = Path(directory)
    if not (directory / CHECKPOINT).exists():
        return {'status': 'not_prepared', 'checked_ids': 0, 'supplier_requests': 0}
    cp, _, _ = load(directory)
    for batch in cp['batches']:
        if batch['state'] == 'reserved':
            batch.update(state='not_started', reason='alias_read_not_started_before_finalization')
        elif batch['state'] == 'in_flight':
            batch.update(state='interrupted_result_unknown',
                         reason='cached_alias_read_unconfirmed')
    save(directory, cp)
    results = [result for batch in cp['batches'] if batch['state'] == 'completed'
               for result in batch['results']]
    strong = [result for result in results if result['proposal_status'] == 'strong_candidate']
    summary = {'selected_ids': len(cp['manifest_ids']), 'checked_ids': len(results),
               'proposal_counts': dict(Counter(r['proposal_status'] for r in results)),
               'proposal_reasons': dict(Counter(r['proposal_reason'] for r in results)),
               'deferred_ids': [identifier for batch in cp['batches']
                                if batch['state'] != 'completed' for identifier in batch['ids']],
               'protected_ids': cp['protected_ids'],
               'strict_proposal_pairs': [{
                   'anex_hotel_id': result['anex_hotel_id'],
                   'catalog_hotel_id': result['best']['id'],
                   'canonical_name': result['best']['canonical_name'],
                   'matched_name': result['best']['matched_name'],
                   'matched_name_source': result['best']['matched_name_source'],
                   'name_similarity': result['best']['name_similarity'],
                   'distance_m': result['best']['distance_m'],
                   'score_margin': result['score_margin'],
               } for result in strong],
               'new_canonical_reads': 0, 'supplier_requests': 0, 'inserted': 0,
               'historical_evidence_unchanged': True}
    report = {'schema_version': 1, 'scope': 'preview',
              'kind': 'cached_complete_alias_read_only_review',
              'checkpoint_sha256': file_sha(directory / CHECKPOINT),
              'summary': summary, 'results': results,
              'results_sha256': gaps.digest(results),
              'policy': 'Proposals only; separate pinned acceptance required.'}
    owner.save(directory / REPORT, report)
    cells = [['anex_hotel_id','catalog_hotel_id','matched_name','matched_name_source',
              'candidate_count','alias_rows','proposal_status','proposal_reason',
              'name_similarity','distance_m','score_margin']]
    for result in results:
        best = result['best'] or {}
        cells.append([str(value) for value in [
            result['anex_hotel_id'], best.get('id',''), best.get('matched_name',''),
            best.get('matched_name_source',''), result['candidate_count'], result['alias_rows'],
            result['proposal_status'], result['proposal_reason'],
            best.get('name_similarity',''), best.get('distance_m',''), result['score_margin']]])
    csv_path = directory / REPORT.replace('.json', '.csv')
    with csv_path.open('w', newline='') as handle:
        csv.writer(handle).writerows(cells)
    with csv_path.open(newline='') as handle:
        if list(csv.reader(handle)) != cells:
            raise ValueError('cached alias CSV readback mismatch')
    return dict(summary, checkpoint_sha256=file_sha(directory / CHECKPOINT),
                report=REPORT, report_sha256=file_sha(directory / REPORT),
                report_readback_verified=True)


if __name__ == '__main__':
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    action = {'--prepare': prepare, '--run': run, '--finalize': finalize}
    if len(sys.argv) != 2 or sys.argv[1] not in action:
        raise SystemExit('expected --prepare, --run or --finalize')
    result = action[sys.argv[1]](directory)
    print(json.dumps(result, ensure_ascii=False))
    if result.get('status') == 'alias_read_unconfirmed':
        raise SystemExit(1)
