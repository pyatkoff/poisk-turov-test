#!/usr/bin/env python3
"""Audit historical geo evidence for unresolved observed IDs, without source/DB calls."""
from collections import Counter
import hashlib
import json
import os
from pathlib import Path

import anex_search3_gap_queue as gaps
import anex_search3_observed_queue as live

REPORT = 'anex-observed-cached-evidence-audit.json'


def digest_bytes(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def inspect(identifier, observation, current, cached, original, ns):
    result = {'anex_hotel_id': identifier, 'current_status': current.get('status', 'pending'),
              'current_reason': current.get('reason', ''), 'cached_api_usable': False,
              'supplier_requests': 0, 'required_next_step': 'independent_evidence_needed'}
    if not cached:
        return dict(result, cache_reason='no_geo_row')
    result['cached_row_sha256'] = gaps.digest(cached)
    api = cached.get('api')
    if not isinstance(api, dict) or not api:
        return dict(result, cache_reason='no_saved_api_details')
    if (api.get('id') != identifier or type(api.get('id')) is not int
            or not ns['hotel_text'](api.get('name')) or not original):
        return dict(result, cache_reason='cached_identity_unverified')
    # The final catalog projection omits town_id; the pinned geo row retains
    # the original XML town. Do not discard that evidence or fabricate null.
    xml = cached.get('xml', {})
    expected = {'id': identifier, 'name': original['name'],
                'alternate_name': original['alternate_name']}
    if (not isinstance(xml, dict) or any(xml.get(k) != v for k, v in expected.items())
            or (original.get('town_id') is not None and original['town_id'] != xml.get('town_id'))
            or ns['xml_relation'](xml, api) != 'same_record'
            or ns['country_match'](original.get('country'), api.get('country')) is not True
            or ns['country_match'](observation.get('country_name'), api.get('country')) is not True):
        return dict(result, cache_reason='cached_xml_country_unverified')
    result.update(cached_api_usable=True, cache_reason='historical_id_xml_country_verified',
                  cached_checked_at=cached.get('checked_at'), cached_api=api,
                  cached_xml=xml, required_next_step='fresh_read_only_local_candidates')
    # Historic candidates are normalized projections, not raw SQL rows. Rebuild
    # the pure rank inputs and retain the original row digest as provenance.
    candidates = cached.get('candidates', [])
    if not isinstance(candidates, list) or any(not isinstance(c, dict) for c in candidates):
        raise ValueError('invalid cached candidate list')
    if (any(type(c.get('id')) is not int or c['id'] <= 0 for c in candidates)
            or len({c['id'] for c in candidates}) != len(candidates)):
        raise ValueError('invalid cached candidate identities')
    ranked = [ns['candidate_rank'](api, xml, dict(c, country_name=c.get('country'),
              region_name=c.get('region'), subregion_name=c.get('town'))) for c in candidates]
    ranked.sort(key=lambda c: (-c['score'], c['id']))
    status, reason = ns['geo_decision'](api, ranked, 'same_record')
    limit = cached.get('candidate_limit', 64)
    if type(limit) is not int or not 1 <= limit <= 256:
        raise ValueError('invalid historical candidate limit')
    if len(ranked) >= limit:
        status, reason = 'review', 'historical_candidate_limit_reached'
    result.update(cached_candidate_count=len(ranked), cached_candidate_limit=limit,
                  reproduced_historical_class=status, reproduced_historical_reason=reason,
                  cached_best=ranked[0] if ranked else None,
                  cached_score_margin=round(ranked[0]['score'] - ranked[1]['score'], 4)
                  if len(ranked) > 1 else None)
    return result


def run(directory):
    directory = Path(directory)
    cp = live.restore(directory)
    if cp['in_flight'] or cp.get('batch_needs_finalization'):
        raise ValueError('live evidence must be finalized before cached audit')
    history = live.evidence_history(directory, cp)
    names = [live.CHECKPOINT, gaps.CHECKPOINT, 'anex-hotel-geo-enrichment.json',
             'anex-hotel-catalog-match.json', 'anex-observed-hotel-queue.json',
             'anex-observed-hotel-triage.json']
    protected = {name: digest_bytes(directory / name) for name in names}
    for name, key in [('anex-hotel-geo-enrichment.json', 'geo_sha256'),
                      ('anex-hotel-catalog-match.json', 'catalog_sha256')]:
        if protected[name] != cp['sources'][key]:
            raise ValueError('cached evidence source digest mismatch')
    queue = json.loads((directory / 'anex-observed-hotel-queue.json').read_bytes())
    triage = json.loads((directory / 'anex-observed-hotel-triage.json').read_bytes())
    if (queue['completed_total'] != cp['completed_total']
            or triage['checkpoint_sha256'] != gaps.digest(cp)
            or queue['triage']['files']['anex-observed-hotel-triage.json'] != protected['anex-observed-hotel-triage.json']):
        raise ValueError('latest unresolved snapshot provenance mismatch')
    targets = []
    for row in triage['rows']:
        identifier = row['anex_hotel_id']
        evidence = history[identifier][0]
        if (gaps.digest(evidence) != row['evidence_row_sha256'] or row['evidence'] != evidence
                or row['status'] != evidence['status'] or row['reason'] != evidence.get('reason', '')):
            raise ValueError('historical unresolved evidence changed')
        targets.append((identifier, row['observation'], evidence))
    targets.extend((r['anex_hotel_id'], r, {}) for r in queue['pending'])
    if (len({r[0] for r in targets}) != len(targets)
            or len(targets) != queue['counts']['pending']):
        raise ValueError('incomplete or duplicate unresolved snapshot')
    geo = json.loads((directory / 'anex-hotel-geo-enrichment.json').read_bytes())['rows']
    originals = json.loads((directory / 'anex-hotel-catalog-match.json').read_bytes())['matches']
    if len({r['external_id'] for r in geo}) != len(geo):
        raise ValueError('duplicate geo cache identities')
    cache = {r['external_id']: r for r in geo}
    catalog = {r['external_id']: r for r in originals}
    ns = {}
    exec(gaps.matching_source(), ns)
    rows = [inspect(i, observation, current, cache.get(i), catalog.get(i), ns)
            for i, observation, current in targets]
    usable = [r for r in rows if r['cached_api_usable']]
    summary = {'unresolved_ids': len(rows), 'usable_cached_api_ids': len(usable),
               'source_error_with_usable_cached_api': sum(r['current_status'] == 'source_error' for r in usable),
               'cache_reasons': dict(Counter(r['cache_reason'] for r in rows)),
               'usable_cached_api_id_list': [r['anex_hotel_id'] for r in usable],
               'cached_strict_pair_ids': [r['anex_hotel_id'] for r in usable
                                         if r.get('reproduced_historical_class') == 'strong_candidate'],
               'supplier_requests': 0, 'new_catalog_reads': 0, 'inserted': 0}
    payload = {'schema_version': 1, 'scope': 'preview', 'kind': 'historical_cached_evidence_audit',
               'source_sha': os.environ.get('GITHUB_SHA'), 'protected_source_sha256': protected,
               'summary': summary, 'rows': rows,
               'policy': 'Historical evidence only. No supplier requests, retries, database reads or writes. '
                         'Cached pair signals require current registry and local-candidate validation before acceptance.'}
    path = directory / REPORT
    path.write_text(json.dumps(payload, ensure_ascii=False, sort_keys=True, indent=2) + '\n')
    if (json.loads(path.read_bytes()) != payload
            or any(digest_bytes(directory / name) != sha for name, sha in protected.items())):
        raise ValueError('cached audit readback or preservation mismatch')
    return dict(summary, report=REPORT, report_sha256=digest_bytes(path),
                protected_evidence_unchanged=True, report_readback_verified=True)


if __name__ == '__main__':
    print(json.dumps(run(Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])), ensure_ascii=False))
