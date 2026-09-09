#!/usr/bin/env python3
"""Resolve pinned review rows using cached supplier details and complete local alias competition."""
import csv
from collections import Counter
import hashlib
import json
import os
from pathlib import Path
import sys

import anex_search3_alias_review as alias
import anex_search3_cached_evidence_audit as audit
import anex_search3_gap_queue as gaps
import anex_search3_observed_queue as live
import anex_search3_owner_decisions as owner

CHECKPOINT = 'anex-cached-detail-alias-review-checkpoint.json'
REPORT = 'anex-cached-detail-alias-review-report.json'
PINNED_AUDIT_SHA = 'f82d2391b98f817ebface0331a8ef3691ffcca2fd55213288827f0f20aff9afc'
BOOTSTRAP_ARTIFACT = 10082284529
EXPECTED_IDS = (16193, 23136, 24940, 29272, 31706, 32282, 32577, 32585, 32611,
                32661, 32712, 32724, 32752, 32808, 32955, 32967, 36112)
FINAL_STATES = {'completed', 'interrupted_result_unknown', 'not_started'}
CHECKED_CHECKPOINT_SHA = '6676c6c1a13cb64a92fdb11543d03b6463583abbbf64679c3b431abee7b9da86'
ACCEPTANCE = 'anex-cached-detail-alias-review-acceptance.json'


def job():
    return [os.environ.get('GITHUB_RUN_ID', ''), os.environ.get('GITHUB_RUN_ATTEMPT', '')]


def file_sha(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def source_rows(directory):
    directory = Path(directory)
    report = json.loads((directory / audit.REPORT).read_bytes())
    cp = live.restore(directory)
    if cp['in_flight'] or cp.get('batch_needs_finalization'):
        raise ValueError('live queue must be finalized before cached detail review')
    history = live.evidence_history(directory, cp)
    expected = gaps.load_queue()['sources']
    documents, sources = {}, {}
    for key, name in [('geo_sha256', 'anex-hotel-geo-enrichment.json'),
                      ('catalog_sha256', 'anex-hotel-catalog-match.json')]:
        path = directory / name
        sources[key] = file_sha(path)
        if (sources[key] != expected[key]
                or report['protected_source_sha256'][name] != sources[key]):
            raise ValueError('cached detail source digest changed')
        documents[key] = json.loads(path.read_bytes())
    geo = {row['external_id']: row for row in documents['geo_sha256']['rows']}
    originals = {row['external_id']: row for row in documents['catalog_sha256']['matches']}
    audit_rows = {row['anex_hotel_id']: row for row in report['rows']}
    if (report.get('kind') != 'historical_cached_evidence_audit'
            or report.get('scope') != 'preview'
            or set(EXPECTED_IDS) - set(audit_rows)
            or len(set(EXPECTED_IDS)) != len(EXPECTED_IDS)):
        raise ValueError('cached detail audit scope changed')
    ns = {}
    exec(gaps.matching_source(), ns)
    rows = {}
    for identifier in EXPECTED_IDS:
        item = audit_rows[identifier]
        current = history[identifier][0]
        cached = geo[identifier]
        reproduced = audit.inspect(
            identifier, {'country_name': cached.get('api', {}).get('country')},
            current, cached, originals.get(identifier), ns)
        if (item.get('current_status') != 'review' or item.get('cached_api_usable') is not True
                or current.get('status') != 'review' or reproduced != item
                or gaps.digest(cached) != item['cached_row_sha256']):
            raise ValueError('cached detail evidence changed')
        rows[identifier] = {
            'external_id': identifier, 'api': cached['api'], 'xml': cached['xml'],
            'live_row_sha256': gaps.digest(current),
            'geo_row_sha256': gaps.digest(cached),
            'audit_row_sha256': gaps.digest(item),
        }
    return rows, sources


def load(directory):
    directory = Path(directory)
    cp = json.loads((directory / CHECKPOINT).read_bytes())
    rows, sources = source_rows(directory)
    active = sorted(cp.get('active_ids', []))
    protected = sorted(cp.get('protected_ids', []))
    admissions = {row['anex_hotel_id']: row for row in cp.get('admissions', [])}
    manifest = sorted(EXPECTED_IDS)
    cp_sources = cp.get('sources', {})
    if (cp.get('schema_version') != 1 or cp.get('scope') != 'preview'
            or cp.get('kind') != 'cached_detail_complete_alias_reviews'
            or {key: cp_sources.get(key) for key in sources} != sources
            or cp.get('source_artifact_id') != BOOTSTRAP_ARTIFACT
            or cp.get('manifest_ids') != manifest or sorted(active + protected) != manifest
            or set(active) & set(protected) or set(admissions) != set(active)
            or cp.get('source_digests') != {
                str(identifier): gaps.digest(rows[identifier]) for identifier in active}
            or not isinstance(cp.get('reserved_by'), list) or len(cp['reserved_by']) != 2
            or len(cp.get('batches', [])) != (len(active) + 1) // 2):
        raise ValueError('cached detail checkpoint provenance changed')
    for index, batch in enumerate(cp['batches']):
        wanted = active[index * 2:index * 2 + 2]
        request = {'mode': 'alias_review',
                   'queries': [alias.query_for(identifier, rows[identifier], admissions)
                               for identifier in wanted]}
        if (batch.get('ids') != wanted or batch.get('request') != request
                or batch.get('request_sha256') != gaps.digest(request)
                or batch.get('state') not in FINAL_STATES | {'reserved', 'in_flight'}):
            raise ValueError('cached detail reservation changed')
        if batch['state'] == 'completed':
            results = batch.get('results', [])
            if (len(results) != len(wanted)
                    or {result['anex_hotel_id'] for result in results} != set(wanted)
                    or batch.get('results_sha256') != gaps.digest(results)):
                raise ValueError('cached detail result digest changed')
            items = batch.get('alias_response', [])
            if (len(items) != len(wanted)
                    or [alias.analyze(rows[item['key']], item) for item in items] != results):
                raise ValueError('cached detail score reproduction failed')
    return cp, rows


def save(directory, cp):
    owner.save(Path(directory) / CHECKPOINT, cp)
    if load(directory)[0] != cp:
        raise ValueError('cached detail checkpoint readback failed')


def protected(directory):
    names = [live.CHECKPOINT, gaps.CHECKPOINT, 'anex-hotel-geo-enrichment.json',
             'anex-hotel-catalog-match.json', 'anex-owner-hotel-decisions.json',
             audit.REPORT, alias.CHECKPOINT, alias.REPORT,
             'anex-cached-alias-review-checkpoint.json',
             'anex-cached-alias-review-report.json']
    return {name: file_sha(Path(directory) / name) for name in names}


def prepare(directory):
    directory = Path(directory)
    if (directory / CHECKPOINT).exists():
        cp, _ = load(directory)
        recovered = []
        for batch in cp['batches']:
            if (batch['state'] == 'in_flight'
                    or (batch['state'] == 'reserved' and cp['reserved_by'] != job())):
                batch.update(state='interrupted_result_unknown',
                             reason='previous_job_reservation_unconfirmed')
                recovered.extend(batch['ids'])
        save(directory, cp)
        return {'status': 'restored', 'recovered_unknown_ids': recovered,
                'supplier_requests': 0, 'inserted': 0}
    source = json.loads((directory / 'anex-checkpoint-source.json').read_bytes())
    if source.get('artifact_id') != BOOTSTRAP_ARTIFACT:
        raise ValueError('cached detail checkpoint missing; refusing reset')
    rows, sources = source_rows(directory)
    pending = {row['anex_hotel_id']: row for row in live.snapshot()['pending']}
    active = sorted(set(rows) & set(pending))
    protected_ids = sorted(set(rows) - set(active))
    admissions = {identifier: pending[identifier] for identifier in active}
    batches = []
    for offset in range(0, len(active), 2):
        wanted = active[offset:offset + 2]
        request = {'mode': 'alias_review',
                   'queries': [alias.query_for(identifier, rows[identifier], admissions)
                               for identifier in wanted]}
        batches.append({'ids': wanted, 'state': 'reserved', 'request': request,
                        'request_sha256': gaps.digest(request)})
    cp = {'schema_version': 1, 'scope': 'preview',
          'kind': 'cached_detail_complete_alias_reviews',
          'sources': sources, 'source_artifact_id': BOOTSTRAP_ARTIFACT,
          'manifest_ids': sorted(rows), 'active_ids': active, 'protected_ids': protected_ids,
          'source_digests': {str(identifier): gaps.digest(rows[identifier])
                             for identifier in active},
          'admissions': [admissions[identifier] for identifier in active],
          'reserved_by': job(), 'batches': batches}
    save(directory, cp)
    return {'status': 'prepared', 'reserved_ids': active, 'protected_ids': protected_ids,
            'supplier_requests': 0, 'inserted': 0}


def run(directory):
    directory = Path(directory)
    cp, rows = load(directory)
    if all(batch['state'] in FINAL_STATES for batch in cp['batches']):
        return {'status': 'already_completed', 'new_catalog_reads': 0,
                'supplier_requests': 0, 'inserted': 0}
    if cp['reserved_by'] != job() or any(batch['state'] == 'in_flight' for batch in cp['batches']):
        raise ValueError('cached detail review requires current prepared reservation')
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
            if (response.get('status') != 'ok' or len(items) != len(batch['ids'])
                    or {item.get('key') for item in items} != set(batch['ids'])):
                raise ValueError('cached detail catalog response unconfirmed')
            results = [alias.analyze(rows[item['key']], item) for item in items]
            batch.update(state='completed', alias_response=items, results=results,
                         results_sha256=gaps.digest(results))
            reads += len(results)
            checked.extend(result['anex_hotel_id'] for result in results)
        except Exception as error:
            batch.update(state='interrupted_result_unknown',
                         reason='cached_detail_catalog_read_unconfirmed',
                         diagnostic=gaps.failure_report(error, 'cached_detail_alias_review'))
            save(directory, cp)
            if protected(directory) != before:
                raise ValueError('cached detail review changed protected evidence')
            return {'status': 'catalog_read_unconfirmed',
                    'new_catalog_reads_confirmed': reads, 'checked_ids': checked,
                    'supplier_requests': 0, 'inserted': 0}
        save(directory, cp)
    if protected(directory) != before:
        raise ValueError('cached detail review changed protected evidence')
    return {'status': 'cached_detail_alias_evidence_saved', 'new_catalog_reads': reads,
            'checked_ids': checked, 'supplier_requests': 0, 'inserted': 0}


def finalize(directory):
    directory = Path(directory)
    if not (directory / CHECKPOINT).exists():
        return {'status': 'not_prepared', 'checked_ids': 0, 'supplier_requests': 0}
    cp, _ = load(directory)
    for batch in cp['batches']:
        if batch['state'] == 'reserved':
            batch.update(state='not_started', reason='read_not_started_before_finalization')
        elif batch['state'] == 'in_flight':
            batch.update(state='interrupted_result_unknown',
                         reason='cached_detail_catalog_read_unconfirmed')
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
               'supplier_requests': 0, 'inserted': 0,
               'historical_evidence_unchanged': True}
    report = {'schema_version': 1, 'scope': 'preview',
              'kind': 'cached_detail_complete_alias_read_only_review',
              'checkpoint_sha256': file_sha(directory / CHECKPOINT),
              'summary': summary, 'results': results,
              'results_sha256': gaps.digest(results),
              'policy': 'Proposals only; separate pinned acceptance required.'}
    owner.save(directory / REPORT, report)
    cells = [['anex_hotel_id','catalog_hotel_id','canonical_name','matched_name',
              'matched_name_source','candidate_count','alias_rows','proposal_status',
              'proposal_reason','name_similarity','distance_m','score_margin']]
    for result in results:
        best = result['best'] or {}
        cells.append([str(value) for value in [
            result['anex_hotel_id'], best.get('id',''), best.get('canonical_name',''),
            best.get('matched_name',''), best.get('matched_name_source',''),
            result['candidate_count'], result['alias_rows'], result['proposal_status'],
            result['proposal_reason'], best.get('name_similarity',''),
            best.get('distance_m',''), result['score_margin']]])
    csv_path = directory / REPORT.replace('.json', '.csv')
    with csv_path.open('w', newline='') as handle:
        csv.writer(handle).writerows(cells)
    with csv_path.open(newline='') as handle:
        if list(csv.reader(handle)) != cells:
            raise ValueError('cached detail CSV readback mismatch')
    return dict(summary, checkpoint_sha256=file_sha(directory / CHECKPOINT),
                report=REPORT, report_sha256=file_sha(directory / REPORT),
                report_readback_verified=True)


def approved_delta(path):
    path = Path(path)
    if path.name != CHECKPOINT or file_sha(path) != CHECKED_CHECKPOINT_SHA:
        raise ValueError('unchecked cached-detail alias checkpoint')
    cp, _ = load(path.parent)
    if any(batch['state'] != 'completed' for batch in cp['batches']):
        raise ValueError('cached-detail alias results are not all confirmed')
    rows = []
    for batch in cp['batches']:
        for result in batch['results']:
            if (result['candidate_set_complete'] is not True
                    or result['alias_set_complete'] is not True
                    or result['proposal_status'] != 'strong_candidate'
                    or result['proposal_reason'] != 'name_country_coordinates'):
                continue
            rows.append({
                'anex_hotel_id': result['anex_hotel_id'],
                'catalog_hotel_id': result['best']['id'],
                'match_class': 'strong_candidate',
                'reason': 'observed_cached_detail_alias_review:name_country_coordinates',
                'source_row_digest': gaps.digest(result),
            })
    sources = dict(cp['sources'], cached_detail_alias_sha256=CHECKED_CHECKPOINT_SHA)
    return {'schema_version': 1, 'scope': 'preview',
            'approval_policy': 'owner_exact_and_strong_20260908',
            'append_only': True, 'sources': sources,
            'rows': sorted(rows, key=lambda row: row['anex_hotel_id']),
            'counts': {'exact': 0, 'strong': len(rows), 'total': len(rows),
                       'unique_catalog_hotels': len({row['catalog_hotel_id'] for row in rows})}}


def accept(directory):
    from anex_search_mapping_import import ssh_import
    directory = Path(directory)
    delta = approved_delta(directory / CHECKPOINT)
    path = directory / ACCEPTANCE
    previous = json.loads(path.read_bytes()) if path.exists() else None
    if previous is not None and previous.get('delta_sha256') != gaps.digest(delta):
        raise ValueError('cached-detail alias acceptance changed')
    if previous is not None and previous.get('state') == 'finalized':
        return {'status': 'already_finalized', 'inserted': 0,
                'supplier_requests': 0, 'new_catalog_reads': 0}
    if not delta['rows']:
        return {'status': 'no_new_strong_candidates', 'inserted': 0,
                'supplier_requests': 0, 'new_catalog_reads': 0}
    before_history = protected(directory)
    before = live.snapshot()
    checkpoint = {'state': 'prepared', 'delta_sha256': gaps.digest(delta),
                  'checked_checkpoint_sha256': CHECKED_CHECKPOINT_SHA,
                  'coverage_before': before['counts']}
    owner.save(path, checkpoint)
    imported = ssh_import(
        directory / 'anex-cached-detail-alias-review-mappings.json',
        cached_detail_alias_checkpoint=directory / CHECKPOINT)
    after = live.snapshot()
    if (after['effective_mapped_count'] < before['effective_mapped_count']
            or protected(directory) != before_history):
        raise ValueError('cached-detail alias import changed protected evidence')
    preservation = gaps.ssh_batch([], {}, 1)['preservation']
    report = dict(
        live.export(directory, live.restore(directory), after),
        coverage_before=before['counts'], coverage_after=after['counts'],
        import_result=imported, supplier_requests=0, new_completed=0,
        historical_evidence_unchanged=True,
        checked_checkpoint_sha256=CHECKED_CHECKPOINT_SHA,
        database_readback=preservation)
    checkpoint.update(state='finalized', import_result=imported, report=report)
    owner.save(path, checkpoint)
    owner.save(directory / 'anex-cached-detail-alias-review-acceptance-report.json', report)
    return {key: value for key, value in report.items() if key != 'triage'}


if __name__ == '__main__':
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    action = {'--prepare': prepare, '--run': run, '--finalize': finalize, '--accept': accept}
    if len(sys.argv) != 2 or sys.argv[1] not in action:
        raise SystemExit('expected --prepare, --run, --finalize or --accept')
    result = action[sys.argv[1]](directory)
    print(json.dumps(result, ensure_ascii=False))
    if result.get('status') == 'catalog_read_unconfirmed':
        raise SystemExit(1)
