#!/usr/bin/env python3
"""Resolve a pinned review subset with complete canonical and historical-alias competition."""
import csv
from collections import Counter
import hashlib
import json
import os
from pathlib import Path
import re
import sys

import anex_search3_complete_review as complete
import anex_search3_gap_queue as gaps
import anex_search3_observed_queue as live
import anex_search3_owner_decisions as owner

CHECKPOINT = 'anex-alias-review-checkpoint.json'
REPORT = 'anex-alias-review-report.json'
PRIORITY = Path('docs/integrations/reports/anex-observed-evidence-recovery-20260908.json')
PRIORITY_SHA = 'f62617fd453de990bca805cc7c181d9a96942f5154992b37d7134baa58ab12d1'
BOOTSTRAP_ARTIFACT = 10079280403
MAX_IDS = 12
FINAL_STATES = {'completed', 'interrupted_result_unknown', 'not_started'}
QUALIFIERS = {'beach', 'garden', 'palace', 'park', 'annex', 'adults', 'family', 'harem'}


def job():
    return [os.environ.get('GITHUB_RUN_ID', ''), os.environ.get('GITHUB_RUN_ATTEMPT', '')]


def file_sha(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def v2_normalize(value):
    value = str(value or '').strip().lower().replace('ё', 'е')
    return ' '.join(re.findall(r'[^\W_]+', value, re.UNICODE))


def qualifier_set(ns, value):
    return set(ns['norm'](value).split()) & QUALIFIERS


def source_rows(directory):
    raw = PRIORITY.read_bytes()
    if hashlib.sha256(raw).hexdigest() != PRIORITY_SHA:
        raise ValueError('unchecked alias priority manifest')
    manifest = json.loads(raw)
    priority = manifest.get('next_read_only_alias_priorities', {})
    selected = priority.get('rows', [])
    if (priority.get('max_ids') != MAX_IDS or len(selected) != MAX_IDS
            or len({r.get('anex_hotel_id') for r in selected}) != MAX_IDS
            or any(r.get('automatic_acceptance') is not False for r in selected)):
        raise ValueError('alias priority manifest changed')
    cp = live.restore(directory)
    if cp['in_flight'] or cp.get('batch_needs_finalization'):
        raise ValueError('live queue must be finalized before alias review')
    history = live.evidence_history(directory, cp)
    snapshot = live.snapshot()
    pending = {r['anex_hotel_id']: r for r in snapshot['pending']}
    rows, protected = {}, []
    for item in selected:
        identifier = item['anex_hotel_id']
        row = history[identifier][0]
        if (gaps.digest(row) != item['source_row_sha256'] or row.get('status') != 'review'
                or row.get('reason') != 'candidate_limit_reached' or row.get('api', {}).get('id') != identifier
                or row.get('api', {}).get('country') != item['country']
                or row.get('api', {}).get('name') != item['hotel_name']):
            raise ValueError('alias source evidence changed')
        if identifier in pending:
            if pending[identifier].get('country_name') != item['country']:
                raise ValueError('alias review country changed')
            rows[identifier] = row
        else:
            protected.append(identifier)
    sources = {'priority_sha256': PRIORITY_SHA,
               'live_checkpoint_sha256': gaps.digest(cp),
               'catalog_sha256': cp['sources']['catalog_sha256'],
               'geo_sha256': cp['sources']['geo_sha256']}
    return rows, sources, protected


def query_for(identifier, row, pending):
    country_id = pending[identifier].get('country_id')
    if type(country_id) is not int or not 0 < country_id <= 2147483647:
        raise ValueError('invalid alias review country')
    return {'key': identifier, 'country_id': country_id,
            'names': [row['api']['name'], row['xml']['name'], row['xml'].get('alternate_name', '')],
            'latitude': row['api'].get('latitude'), 'longitude': row['api'].get('longitude')}


def validate_alias(alias):
    return (isinstance(alias, dict) and isinstance(alias.get('alias'), str)
            and 0 < len(alias['alias']) <= 500 and isinstance(alias.get('normalized_alias'), str)
            and 0 < len(alias['normalized_alias']) <= 500 and isinstance(alias.get('source'), str)
            and 0 < len(alias['source']) <= 100)


def analyze(row, item):
    candidates = item.get('candidates')
    if (item.get('key') != row['external_id'] or not isinstance(candidates, list)
            or len(candidates) > 4097 or item.get('fetch_limit') != 4097
            or item.get('query_scope') != 'active_country_canonical_alias_or_geobox'
            or item.get('candidate_set_complete') is not (len(candidates) < 4097)
            or item.get('alias_set_complete') is not True or item.get('alias_fetch_limit') != 8193
            or type(item.get('alias_rows')) is not int or item['alias_rows'] < 0
            or any(not isinstance(c, dict) or type(c.get('id')) is not int or c['id'] <= 0
                   or not isinstance(c.get('aliases'), list) or any(not validate_alias(a) for a in c['aliases'])
                   for c in candidates)
            or len({c['id'] for c in candidates}) != len(candidates)
            or sum(len(c['aliases']) for c in candidates) != item['alias_rows']):
        raise ValueError('alias candidate completeness unverified')
    ns = {}
    exec(gaps.matching_source(), ns)
    names = [row['api'].get('name'), row['xml'].get('name'), row['xml'].get('alternate_name')]
    ranked, unsafe_aliases = [], []
    for candidate in candidates:
        aliases = candidate['aliases']
        if len({(a['alias'], a['normalized_alias'], a['source']) for a in aliases}) != len(aliases):
            raise ValueError('duplicate alias evidence')
        canonical = ns['candidate_rank'](row['api'], row['xml'], candidate)
        best = dict(canonical, canonical_name=canonical['name'], canonical_name_similarity=canonical['name_similarity'],
                    matched_name=canonical['name'], matched_name_source='canonical')
        for alias in aliases:
            valid_generated = alias['source'] == 'generated' and v2_normalize(alias['alias']) == alias['normalized_alias']
            alias_similarity = max(ns['name_score'](name, alias['alias']) for name in names)
            near = canonical['distance_m'] is not None and canonical['distance_m'] <= 200
            if not valid_generated and alias_similarity >= 0.9 and canonical['country_match'] is True and near:
                unsafe_aliases.append({'catalog_hotel_id': candidate['id'],
                                       'alias_sha256': gaps.digest(alias),
                                       'name_similarity': alias_similarity,
                                       'distance_m': canonical['distance_m']})
            if valid_generated:
                alias_candidate = dict(candidate, name=alias['alias'])
                value = ns['candidate_rank'](row['api'], row['xml'], alias_candidate)
                if value['score'] > best['score'] or (value['score'] == best['score']
                        and value['name_similarity'] > best['name_similarity']):
                    best = dict(value, canonical_name=canonical['name'],
                                canonical_name_similarity=canonical['name_similarity'],
                                matched_name=alias['alias'], matched_name_source='generated')
        best['aliases_sha256'] = gaps.digest(aliases)
        best['alias_count'] = len(aliases)
        ranked.append(best)
    ranked.sort(key=lambda c: (-c['score'], c['id']))
    status, reason = ns['geo_decision'](row['api'], ranked, 'same_record',
                                      candidate_set_complete=item['candidate_set_complete'])
    if unsafe_aliases:
        status, reason = 'review', 'unverified_alias_provenance'
    if status == 'strong_candidate':
        best = ranked[0]
        supplier = qualifier_set(ns, row['api'].get('name'))
        if (qualifier_set(ns, best['matched_name']) != supplier
                or qualifier_set(ns, best['canonical_name']) != supplier):
            status, reason = 'review', 'hotel_section_difference'
    return {'anex_hotel_id': row['external_id'], 'source_row_sha256': gaps.digest(row),
            'candidate_set_complete': item['candidate_set_complete'], 'fetch_limit': 4097,
            'alias_set_complete': item['alias_set_complete'], 'alias_fetch_limit': 8193,
            'alias_rows': item['alias_rows'], 'query_scope': item['query_scope'],
            'candidate_count': len(candidates), 'raw_candidates': candidates,
            'raw_candidates_sha256': gaps.digest(candidates), 'ranked_candidates': ranked,
            'best': ranked[0] if ranked else None,
            'score_margin': round(ranked[0]['score'] - ranked[1]['score'], 4) if len(ranked) > 1 else None,
            'unsafe_aliases': unsafe_aliases, 'proposal_status': status, 'proposal_reason': reason,
            'inserted': 0, 'automatic_acceptance': False}


def load(directory):
    cp = json.loads((Path(directory) / CHECKPOINT).read_bytes())
    rows, sources, protected = source_rows(directory)
    ids = sorted(set(rows) | set(protected))
    if (cp.get('schema_version') != 1 or cp.get('scope') != 'preview'
            or cp.get('kind') != 'complete_alias_reviews' or cp.get('sources') != sources
            or cp.get('source_artifact_id') != BOOTSTRAP_ARTIFACT
            or cp.get('source_digests') != {str(i): gaps.digest(rows[i]) for i in sorted(rows)}
            or cp.get('manifest_ids') != ids or cp.get('protected_ids') != sorted(protected)
            or not isinstance(cp.get('reserved_by'), list) or len(cp['reserved_by']) != 2
            or len(cp.get('batches', [])) != (len(rows) + 1) // 2):
        raise ValueError('alias review checkpoint provenance changed')
    pending = {r['anex_hotel_id']: r for r in live.snapshot()['pending']}
    active_ids = sorted(rows)
    for n, batch in enumerate(cp['batches']):
        wanted = active_ids[n * 2:n * 2 + 2]
        expected_queries = [query_for(i, rows[i], pending) for i in wanted if i in pending]
        request = {'mode': 'alias_review', 'queries': expected_queries}
        if (batch.get('ids') != wanted or batch.get('request') != request
                or batch.get('request_sha256') != gaps.digest(request)
                or batch.get('state') not in FINAL_STATES | {'reserved', 'in_flight'}):
            raise ValueError('alias review reservation changed')
        if batch['state'] == 'completed':
            results = batch.get('results', [])
            if (len(results) != len(expected_queries)
                    or {r['anex_hotel_id'] for r in results} != {q['key'] for q in expected_queries}
                    or batch.get('results_sha256') != gaps.digest(results)):
                raise ValueError('alias review results changed')
            for result in results:
                item = {'key': result['anex_hotel_id'], 'candidates': result['raw_candidates'],
                        'candidate_set_complete': result['candidate_set_complete'],
                        'fetch_limit': result['fetch_limit'], 'alias_set_complete': result['alias_set_complete'],
                        'alias_fetch_limit': result['alias_fetch_limit'], 'alias_rows': result['alias_rows'],
                        'query_scope': result['query_scope']}
                if analyze(rows[result['anex_hotel_id']], item) != result:
                    raise ValueError('alias review score reproduction failed')
    return cp, rows


def save(directory, cp):
    owner.save(Path(directory) / CHECKPOINT, cp)
    if load(directory)[0] != cp:
        raise ValueError('alias review checkpoint readback failed')


def protected(directory):
    names = [live.CHECKPOINT, gaps.CHECKPOINT, 'anex-hotel-geo-enrichment.json',
             'anex-hotel-catalog-match.json', 'anex-owner-hotel-decisions.json',
             complete.CHECKPOINT, complete.ACCEPTANCE, 'anex-saved-review-checkpoint.json',
             'anex-saved-review-acceptance.json', 'anex-cached-review-checkpoint.json',
             'anex-cached-review-acceptance.json']
    return {name: file_sha(Path(directory) / name) for name in names}


def prepare(directory):
    directory = Path(directory)
    if (directory / CHECKPOINT).exists():
        cp, _ = load(directory)
        recovered = []
        for batch in cp['batches']:
            if batch['state'] == 'in_flight' or (batch['state'] == 'reserved' and cp['reserved_by'] != job()):
                batch.update(state='interrupted_result_unknown', reason='previous_job_reservation_unconfirmed')
                recovered.extend(q['key'] for q in batch['request']['queries'])
        save(directory, cp)
        return {'status': 'restored', 'recovered_unknown_ids': recovered,
                'supplier_requests': 0, 'inserted': 0}
    source = json.loads((directory / 'anex-checkpoint-source.json').read_bytes())
    if source.get('artifact_id') != BOOTSTRAP_ARTIFACT:
        raise ValueError('alias checkpoint missing; refusing reset')
    rows, sources, protected_ids = source_rows(directory)
    pending = {r['anex_hotel_id']: r for r in live.snapshot()['pending']}
    active_ids = sorted(rows)
    batches = []
    for n in range(0, len(active_ids), 2):
        wanted = active_ids[n:n + 2]
        request = {'mode': 'alias_review', 'queries': [query_for(i, rows[i], pending) for i in wanted]}
        batches.append({'ids': wanted, 'state': 'reserved', 'request': request,
                        'request_sha256': gaps.digest(request)})
    cp = {'schema_version': 1, 'scope': 'preview', 'kind': 'complete_alias_reviews',
          'sources': sources, 'source_artifact_id': BOOTSTRAP_ARTIFACT,
          'manifest_ids': sorted(set(active_ids) | set(protected_ids)),
          'source_digests': {str(i): gaps.digest(rows[i]) for i in active_ids},
          'protected_ids': sorted(protected_ids), 'reserved_by': job(), 'batches': batches}
    save(directory, cp)
    return {'status': 'prepared', 'reserved_ids': active_ids, 'protected_ids': sorted(protected_ids),
            'supplier_requests': 0, 'inserted': 0}


def run(directory):
    directory = Path(directory)
    cp, rows = load(directory)
    if all(batch['state'] in FINAL_STATES for batch in cp['batches']):
        return {'status': 'already_completed', 'new_catalog_reads': 0,
                'supplier_requests': 0, 'inserted': 0}
    if cp['reserved_by'] != job() or any(batch['state'] == 'in_flight' for batch in cp['batches']):
        raise ValueError('alias review requires current prepared reservation')
    before = protected(directory)
    source = Path(__file__).with_name('anex_alias_catalog_reader.php').read_text().removeprefix('<?php')
    reads, checked = 0, []
    for batch in cp['batches']:
        if batch['state'] != 'reserved':
            continue
        batch['state'] = 'in_flight'
        save(directory, cp)
        try:
            response = owner.ssh_php(source, batch['request'], maximum_bytes=4000000)
            items = response.get('items', [])
            queries = batch['request']['queries']
            if (response.get('status') != 'ok' or len(items) != len(queries)
                    or {item.get('key') for item in items} != {q['key'] for q in queries}):
                raise ValueError('alias catalog response unconfirmed')
            results = [analyze(rows[item['key']], item) for item in items]
            batch.update(state='completed', results=results, results_sha256=gaps.digest(results))
            reads += len(results)
            checked.extend(r['anex_hotel_id'] for r in results)
        except Exception as error:
            batch.update(state='interrupted_result_unknown', reason='alias_catalog_read_unconfirmed',
                         diagnostic=gaps.failure_report(error, 'alias_review'))
            save(directory, cp)
            if protected(directory) != before:
                raise ValueError('alias review changed protected evidence')
            return {'status': 'catalog_read_unconfirmed', 'new_catalog_reads_confirmed': reads,
                    'checked_ids': checked, 'supplier_requests': 0, 'inserted': 0}
        save(directory, cp)
    if protected(directory) != before:
        raise ValueError('alias review changed protected evidence')
    return {'status': 'complete_alias_candidates_saved', 'new_catalog_reads': reads,
            'checked_ids': checked, 'supplier_requests': 0, 'inserted': 0,
            'protected_evidence_unchanged': True}


def finalize(directory):
    directory = Path(directory)
    cp, _ = load(directory)
    for batch in cp['batches']:
        if batch['state'] == 'reserved':
            batch.update(state='not_started', reason='read_not_started_before_finalization')
        elif batch['state'] == 'in_flight':
            batch.update(state='interrupted_result_unknown', reason='alias_catalog_read_unconfirmed')
    save(directory, cp)
    results = [r for b in cp['batches'] if b['state'] == 'completed' for r in b['results']]
    strong = [r for r in results if r['proposal_status'] == 'strong_candidate']
    summary = {'selected_ids': len(cp['manifest_ids']), 'checked_ids': len(results),
               'proposal_counts': dict(Counter(r['proposal_status'] for r in results)),
               'proposal_reasons': dict(Counter(r['proposal_reason'] for r in results)),
               'deferred_ids': [q['key'] for b in cp['batches'] if b['state'] != 'completed'
                                for q in b['request']['queries']],
               'protected_ids': cp['protected_ids'],
               'strict_proposal_pairs': [{'anex_hotel_id': r['anex_hotel_id'],
                                          'catalog_hotel_id': r['best']['id'],
                                          'canonical_name': r['best']['canonical_name'],
                                          'matched_name': r['best']['matched_name'],
                                          'matched_name_source': r['best']['matched_name_source'],
                                          'name_similarity': r['best']['name_similarity'],
                                          'distance_m': r['best']['distance_m'],
                                          'score_margin': r['score_margin']} for r in strong],
               'supplier_requests': 0, 'inserted': 0, 'historical_evidence_unchanged': True}
    report = {'schema_version': 1, 'scope': 'preview', 'kind': 'alias_complete_read_only_review',
              'checkpoint_sha256': file_sha(directory / CHECKPOINT), 'summary': summary,
              'results': results, 'results_sha256': gaps.digest(results),
              'policy': 'Proposals only. Full canonical/alias competition; separate pinned acceptance required.'}
    owner.save(directory / REPORT, report)
    cells = [['anex_hotel_id','catalog_hotel_id','canonical_name','matched_name','matched_name_source',
              'candidate_count','alias_rows','proposal_status','proposal_reason',
              'name_similarity','distance_m','score_margin']]
    for r in results:
        best = r['best'] or {}
        cells.append([str(v) for v in [r['anex_hotel_id'], best.get('id',''), best.get('canonical_name',''),
            best.get('matched_name',''), best.get('matched_name_source',''), r['candidate_count'],
            r['alias_rows'], r['proposal_status'], r['proposal_reason'], best.get('name_similarity',''),
            best.get('distance_m',''), r['score_margin']]])
    path = directory / REPORT.replace('.json', '.csv')
    with path.open('w', newline='') as handle:
        csv.writer(handle).writerows(cells)
    with path.open(newline='') as handle:
        if list(csv.reader(handle)) != cells:
            raise ValueError('alias review CSV readback mismatch')
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
