#!/usr/bin/env python3
"""Read two bounded complete candidate batches for four pinned saved reviews."""
import csv
import hashlib
import json
import os
from pathlib import Path
import sys

import anex_search3_complete_review as complete
import anex_search3_gap_queue as gaps
import anex_search3_observed_queue as live
import anex_search3_owner_decisions as owner

SOURCE_DIGESTS = {
    28978: 'e5f8e1e9a715b1c21c758cc4c9adb96561e0749c41e055e688fd918eab4d3241',
    32640: 'ef87d1bcde09b5a84a63e648d56d906eff0b5a44ecffa451fa9d8d1448234d77',
    32683: '6e8eb1619111ae0b938ee9475c67c362d604ec3b0a4facd13779bb34b88b461a',
    32722: '755ca5043de86a02158b8d2580197de8b593985c16ed38c8e90b7c034db46756',
}
CHECKPOINT = 'anex-saved-review-checkpoint.json'
REPORT = 'anex-saved-review-report.json'
BOOTSTRAP_ARTIFACT = 10078089699
BATCHES = [[28978, 32640], [32683, 32722]]


def job():
    return [os.environ.get('GITHUB_RUN_ID', ''), os.environ.get('GITHUB_RUN_ATTEMPT', '')]


def file_sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def source_rows(directory):
    checkpoint = live.restore(directory)
    if checkpoint['in_flight'] or checkpoint.get('batch_needs_finalization'):
        raise ValueError('live queue must finish before saved review')
    history = live.evidence_history(directory, checkpoint)
    expected = gaps.load_queue()['sources']
    documents, sources = {}, {}
    for key, filename in [('catalog_sha256', 'anex-hotel-catalog-match.json'),
                          ('geo_sha256', 'anex-hotel-geo-enrichment.json')]:
        path = directory / filename
        sources[key] = file_sha(path)
        if sources[key] != expected[key]:
            raise ValueError('saved review baseline changed')
        documents[key] = json.loads(path.read_bytes())
    originals = {r['external_id']: r for r in documents['catalog_sha256']['matches']}
    accepted = {r['external_id'] for r in originals.values() if r['status'] == 'verified_auto'}
    accepted |= {r['external_id'] for r in documents['geo_sha256']['rows'] if r['status'] == 'strong_candidate'}
    ns = {}
    exec(gaps.matching_source(), ns)
    rows = {}
    for identifier, digest in SOURCE_DIGESTS.items():
        row = history[identifier][0]
        original = originals[identifier]
        xml = {'id': identifier, 'name': original['name'], 'alternate_name': original['alternate_name'],
               'town_id': original.get('town_id')}
        if (identifier in accepted or gaps.digest(row) != digest or row['status'] != 'review'
                or row['reason'] != 'candidate_limit_reached' or row['api']['id'] != identifier
                or row['xml'] != xml or ns['xml_relation'](xml, row['api']) != 'same_record'
                or ns['country_match'](original['country'], row['api']['country']) is not True):
            raise ValueError('saved review identity changed')
        rows[identifier] = row
    return rows, sources


def analyze(row, item):
    result = complete.analyze(row, item)
    ns = {}
    exec(gaps.matching_source(), ns)
    status, reason = ns['geo_decision'](row['api'], result['ranked_candidates'], 'same_record',
                                      candidate_set_complete=result['candidate_set_complete'])
    return dict(result, proposal_status=status, proposal_reason=reason, inserted=0)


def load(directory):
    rows, sources = source_rows(directory)
    cp = json.loads((directory / CHECKPOINT).read_bytes())
    if (cp.get('schema_version') != 1 or cp.get('scope') != 'preview'
            or cp.get('kind') != 'four_saved_complete_reviews' or cp.get('sources') != sources
            or cp.get('source_digests') != {str(i): d for i, d in SOURCE_DIGESTS.items()}
            or not isinstance(cp.get('reserved_by'), list) or len(cp['reserved_by']) != 2
            or not all(isinstance(value, str) for value in cp['reserved_by'])
            or len(cp.get('batches', [])) != 2):
        raise ValueError('saved review checkpoint provenance changed')
    for batch, identifiers in zip(cp['batches'], BATCHES):
        if (batch.get('ids') != identifiers or batch.get('state') not in
                {'reserved', 'in_flight', 'completed', 'interrupted_result_unknown'}):
            raise ValueError('saved review batch identity changed')
        request = batch.get('request', {})
        queries = request.get('queries', [])
        keys = [q.get('key') for q in queries]
        if (request.get('mode') != 'complete_review' or len(keys) > 2 or len(set(keys)) != len(keys)
                or set(keys) | set(batch.get('protected_ids', [])) != set(identifiers)
                or set(keys) & set(batch.get('protected_ids', []))
                or batch.get('request_sha256') != gaps.digest(request)):
            raise ValueError('saved review request changed')
        for query in queries:
            row = rows[query['key']]
            expected = query_for(row, query.get('country_id'))
            if query != expected:
                raise ValueError('saved review query source changed')
        if batch['state'] == 'completed':
            results = batch.get('results', [])
            if (batch.get('results_sha256') != gaps.digest(results) or len(results) != len(keys)
                    or {r['anex_hotel_id'] for r in results} != set(keys)):
                raise ValueError('saved review results changed')
            for result in results:
                item = {'key': result['anex_hotel_id'], 'candidates': result['raw_candidates'],
                        'candidate_set_complete': result['candidate_set_complete'],
                        'query_scope': result['query_scope'], 'fetch_limit': result['fetch_limit']}
                if analyze(rows[item['key']], item) != result:
                    raise ValueError('saved review scores changed')
    return cp, rows


def query_for(row, country_id):
    if type(country_id) is not int or not 0 < country_id <= 2147483647:
        raise ValueError('invalid saved review country')
    return {'key': row['external_id'], 'names': [row['api']['name'], row['xml']['name'],
            row['xml'].get('alternate_name', '')], 'country_id': country_id,
            'latitude': row['api'].get('latitude'), 'longitude': row['api'].get('longitude')}


def save(directory, cp):
    owner.save(directory / CHECKPOINT, cp)
    if load(directory)[0] != cp:
        raise ValueError('saved review checkpoint readback failed')


def prepare(directory):
    if (directory / CHECKPOINT).exists():
        cp, _ = load(directory)
        recovered = []
        for batch in cp['batches']:
            if (batch['state'] == 'in_flight'
                    or (cp['reserved_by'] != job() and batch['state'] == 'reserved')):
                batch['state'] = 'interrupted_result_unknown'
                recovered.extend(q['key'] for q in batch['request']['queries'])
        save(directory, cp)
        return {'status': 'restored', 'recovered_unknown_ids': recovered, 'supplier_requests': 0}
    source = json.loads((directory / 'anex-checkpoint-source.json').read_bytes())
    if source.get('artifact_id') != BOOTSTRAP_ARTIFACT:
        raise ValueError('saved review checkpoint missing; refusing reset')
    rows, sources = source_rows(directory)
    snapshot = live.snapshot()
    pending = {r['anex_hotel_id']: r for r in snapshot['pending']}
    batches = []
    for identifiers in BATCHES:
        queries = [query_for(rows[i], pending[i]['country_id']) for i in identifiers if i in pending]
        request = {'mode': 'complete_review', 'queries': queries}
        batches.append({'ids': identifiers, 'state': 'reserved', 'request': request,
                        'request_sha256': gaps.digest(request),
                        'protected_ids': [i for i in identifiers if i not in pending]})
    cp = {'schema_version': 1, 'scope': 'preview', 'kind': 'four_saved_complete_reviews',
          'sources': sources, 'source_digests': {str(i): d for i, d in SOURCE_DIGESTS.items()},
          'source_artifact_id': source['artifact_id'], 'batches': batches,
          'reserved_by': job(),
          'source_live_checkpoint_sha256': gaps.digest(live.restore(directory))}
    save(directory, cp)
    return {'status': 'prepared', 'reserved_ids': [q['key'] for b in batches for q in b['request']['queries']],
            'supplier_requests': 0, 'inserted': 0}


def protected(directory):
    names = [live.CHECKPOINT, gaps.CHECKPOINT, 'anex-hotel-catalog-match.json',
             'anex-hotel-geo-enrichment.json', complete.CHECKPOINT, complete.ACCEPTANCE,
             'anex-owner-hotel-decisions.json']
    return {name: file_sha(directory / name) for name in names}


def run(directory):
    cp, rows = load(directory)
    if all(b['state'] in {'completed', 'interrupted_result_unknown'} for b in cp['batches']):
        return {'status': 'already_completed', 'new_catalog_reads': 0, 'supplier_requests': 0, 'inserted': 0}
    if cp['reserved_by'] != job():
        raise ValueError('previous job reservation must be recovered without replay')
    for batch in cp['batches']:
        if batch['state'] == 'in_flight':
            batch['state'] = 'interrupted_result_unknown'
    save(directory, cp)
    before = protected(directory)
    pending = {r['anex_hotel_id']: r for r in live.snapshot()['pending']}
    reads = 0
    for batch in cp['batches']:
        if batch['state'] != 'reserved':
            continue
        safe = []
        for query in batch['request']['queries']:
            identifier = query['key']
            if identifier not in pending:
                batch['protected_ids'].append(identifier)
            elif pending[identifier]['country_id'] != query['country_id']:
                raise ValueError('saved review country changed after reservation')
            else:
                safe.append(query)
        batch['request']['queries'] = safe
        batch['request_sha256'] = gaps.digest(batch['request'])
        batch['state'] = 'in_flight'
        save(directory, cp)
        try:
            results = []
            if safe:
                source = Path(__file__).with_name('anex_catalog_reader.php').read_text().removeprefix('<?php')
                response = owner.ssh_php(source, batch['request'], maximum_bytes=4000000)
                reads += len(safe)
                items = response.get('items', [])
                if (response.get('status') != 'ok' or len(items) != len(safe)
                        or {item.get('key') for item in items} != {q['key'] for q in safe}):
                    raise ValueError('saved review catalog read unconfirmed')
                results = [analyze(rows[item['key']], item) for item in items]
            batch.update(state='completed', results=results, results_sha256=gaps.digest(results))
            save(directory, cp)
        except Exception as error:
            batch.update(state='interrupted_result_unknown', diagnostic=gaps.failure_report(error, 'saved_review'))
            save(directory, cp)
            return {'status': 'interrupted_result_unknown', 'new_catalog_reads': reads, 'supplier_requests': 0,
                    'unknown_ids': [q['key'] for q in safe], 'inserted': 0}
    if protected(directory) != before:
        raise ValueError('saved review changed historical evidence')
    return dict(finalize(directory), new_catalog_reads=reads)


def finalize(directory):
    cp, _ = load(directory)
    results = [r for batch in cp['batches'] if batch['state'] == 'completed' for r in batch['results']]
    summary = {'status': 'read_only_review_saved', 'supplier_requests': 0, 'inserted': 0,
               'checkpoint_sha256': file_sha(directory / CHECKPOINT), 'checkpoint_readback_verified': True,
               'source_digests': cp['source_digests'], 'sources': cp['sources'],
               'completed_ids': [r['anex_hotel_id'] for r in results],
               'unknown_ids': [q['key'] for b in cp['batches'] if b['state'] in
                               {'in_flight', 'interrupted_result_unknown'} for q in b['request']['queries']],
               'remaining_ids': [q['key'] for b in cp['batches'] if b['state'] == 'reserved' for q in b['request']['queries']],
               'protected_ids': [i for b in cp['batches'] for i in b['protected_ids']],
               'rows': [{k: v for k, v in r.items() if k not in ('raw_candidates', 'ranked_candidates')} for r in results]}
    owner.save(directory / REPORT, dict(summary, results=results))
    cells = [['anex_hotel_id', 'catalog_hotel_id', 'source_row_sha256', 'raw_candidates_sha256',
              'candidate_set_complete', 'proposal_status', 'proposal_reason', 'raw_candidate', 'ranked_candidate']]
    for result in results:
        ranked = {r['id']: r for r in result['ranked_candidates']}
        for raw in result['raw_candidates']:
            values = [result['anex_hotel_id'], raw['id'], result['source_row_sha256'],
                      result['raw_candidates_sha256'], result['candidate_set_complete'], result['proposal_status'],
                      result['proposal_reason'], raw, ranked[raw['id']]]
            cells.append([str(live.csv_cell(value)) for value in values])
    path = directory / REPORT.replace('.json', '.csv')
    with path.open('w', newline='') as handle:
        csv.writer(handle).writerows(cells)
    with path.open(newline='') as handle:
        if list(csv.reader(handle)) != cells:
            raise ValueError('saved review CSV readback failed')
    return summary


if __name__ == '__main__':
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    try:
        action = prepare if '--prepare' in sys.argv else finalize if '--finalize' in sys.argv else run
        result = action(directory)
        print(json.dumps(result, ensure_ascii=False, sort_keys=True))
        if result['status'] == 'interrupted_result_unknown':
            raise SystemExit(1)
    except Exception as error:
        report = gaps.failure_report(error, 'saved_review')
        owner.save(directory / 'anex-saved-review-failure.json', report)
        print(json.dumps(report))
        raise SystemExit(1) from None
