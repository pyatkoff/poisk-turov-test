#!/usr/bin/env python3
"""Offline review of the two pinned #1759 link batches. No SQL/network/apply path."""
from __future__ import annotations

import argparse
from collections import Counter
import hashlib
import json
from pathlib import Path
import re
from urllib.parse import parse_qsl, urlsplit
from zipfile import ZipFile

ARCHIVE_SHA256 = '770d183c60ccff1537f1ce33879825f8fae6d40c68e882010d9bde8c49001021'
TRIAGE_SHA256 = 'a80ec41ec29da2896330f7b1f1504c780022ad5ae9f55b0ed479fe532c07690a'
BATCH_SHA256 = (
    '69de67f3cd6d0601d67c68fbef03f0003282b1313f7d668a5c182ac456aba354',
    '846f5fd374c0cc7a822389b5c4c89c92d782ba0522a56211a3385609a85f6293',
)
FORMER_SUFFIX = re.compile(r'\s*\(\s*(?:ex|ех)\s*\.?\s+[^()]+\)\s*$', re.IGNORECASE)


def canonical(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True,
                      separators=(',', ':'), allow_nan=False).encode('utf-8')


def digest(value):
    return hashlib.sha256(canonical(value)).hexdigest()


def current_name(value):
    """Remove only an explicitly labelled, parenthesized former-name suffix."""
    if not isinstance(value, str):
        raise ValueError('name must be a string')
    current = FORMER_SUFFIX.sub('', value).strip()
    if value.strip() and not current:
        raise ValueError('former name without current name')
    return current


def policy_namespace():
    # Existing pure ranking and acceptance functions, never a queue runner.
    import anex_search3_gap_queue as gaps
    namespace = {}
    exec(gaps.matching_source(), namespace)
    return namespace


def positive_id(value):
    if type(value) is not int or not 0 < value <= 2147483647:
        raise ValueError('invalid hotel identity')
    return value


def validate_link(entry):
    identifier = positive_id(entry['anex_hotel_id'])
    positive_id(entry['local_hotel_id'])
    link = entry['operator_link']
    url = urlsplit(link['safe_url'])
    if (url.scheme != 'https' or url.netloc != 'agent.anextour.ru'
            or url.path != '/search/tour' or url.fragment
            or parse_qsl(url.query, keep_blank_values=True) != [('HOTELLIST', str(identifier))]
            or link.get('origin') != 'https://agent.anextour.ru'
            or link.get('path') != '/search/tour' or link.get('field') != 'HOTELLIST'
            or link.get('value') != str(identifier)):
        raise ValueError('operator link identity mismatch')


def accepted_snapshot(archive):
    """Reconstruct only receipted rows in this hash-pinned historical archive."""
    def document(name):
        return json.loads(archive.read(name))

    accepted = {}

    def add(rows):
        for row in rows:
            a, t = positive_id(row['anex_hotel_id']), positive_id(row['catalog_hotel_id'])
            if a in accepted and accepted[a] != t:
                raise ValueError('historical registry conflict')
            accepted[a] = t

    add(document('anex-search-mappings.json')['rows'])
    receipt = document('anex-initial-search-mapping-import.json')
    if receipt.get('status') not in ('imported', 'already_imported'):
        raise ValueError('initial delta not receipted')
    add(document('anex-initial-search-mappings.json')['rows'])
    observed = document('anex-observed-hotel-checkpoint.json')
    if observed['in_flight'] or observed['batch_needs_finalization']:
        raise ValueError('observed checkpoint incomplete')
    finalized = set(observed['import_finalized_ids'])
    source_rows = {r['external_id']: r for r in observed['rows']}
    for a in sorted(finalized):
        row = source_rows[a]
        if row['status'] != 'strong_candidate':
            raise ValueError('unapproved observed row')
        add([{'anex_hotel_id': a, 'catalog_hotel_id': row['candidates'][0]['id']}])
    for prefix in ('anex-complete-candidate-review', 'anex-saved-review', 'anex-cached-review'):
        receipt = document(prefix + '-acceptance.json')
        if receipt.get('state') != 'finalized':
            raise ValueError('review delta not finalized')
        add(document(prefix + '-mappings.json')['rows'])
    manual = document('anex-owner-hotel-decisions.json')
    if manual.get('state') != 'applied':
        raise ValueError('manual decisions not applied')
    manual_rows = manual['request']['rows']
    if len(accepted) != 12890 or len(manual_rows) != 9:
        raise ValueError('historical totals differ from readback')
    # All manual IDs are protected, including any future non-accepted decisions.
    manual_ids = {positive_id(r['anex_hotel_id']) for r in manual_rows}
    add(manual_rows)
    if len(accepted) != 12899 or len(set(accepted.values())) != 11072:
        raise ValueError('effective historical totals mismatch')
    return accepted, manual_ids


def review_pair(entry, dossier, accepted, manual_ids, excluded_pairs, policy):
    """Eligibility is a proposal, never DB acceptance or live exclusion authority."""
    validate_link(entry)
    a, t = entry['anex_hotel_id'], entry['local_hotel_id']
    result = {'anex_hotel_id': a, 'catalog_hotel_id': t,
              'anex_name': entry['anex_name'], 'local_name': entry['local_name'],
              'dossier_sha256': entry['dossier_digest'], 'status': 'review'}
    if a in manual_ids or (a, t) in excluded_pairs:
        return dict(result, status='protected', reason='manual_or_pair_exclusion')
    if a in accepted:
        return dict(result, status='protected', reason=(
            'already_accepted' if accepted[a] == t else 'existing_mapping_conflict'))
    evidence = dossier['evidence']
    if (dossier['anex_hotel_id'] != a or evidence['external_id'] != a
            or digest(evidence) != entry['dossier_digest']
            or dossier['evidence_row_sha256'] != entry['dossier_digest']
            or evidence['api']['id'] != a or evidence['xml']['id'] != a):
        raise ValueError('dossier identity or digest mismatch')
    candidates = evidence['candidates']
    identifiers = [positive_id(c['id']) for c in candidates]
    if len(set(identifiers)) != len(identifiers) or t not in identifiers:
        raise ValueError('candidate identity set mismatch')
    if len(candidates) >= 256:
        return dict(result, reason='candidate_limit_reached')
    selected = next(c for c in candidates if c['id'] == t)
    if selected['name'] != entry['local_name']:
        raise ValueError('operator target name differs from dossier')
    if (policy['country_match'](dossier['observation']['country_name'], evidence['api']['country'])
            is not True):
        return dict(result, reason='source_country_unverified')
    relation = policy['xml_relation'](evidence['xml'], evidence['api'])
    if relation != 'same_record' or evidence['api_xml_relation'] != relation:
        return dict(result, reason='supplier_identity_unverified')

    def ranked(use_current):
        api, xml = dict(evidence['api']), dict(evidence['xml'])
        if use_current:
            api['name'] = current_name(api['name'])
            for key in ('name', 'alternate_name'):
                xml[key] = current_name(xml.get(key) or '')
        rows = []
        for saved in candidates:
            candidate = dict(saved, country_name=saved.get('country'),
                             region_name=saved.get('region'), subregion_name=saved.get('town'))
            if use_current:
                candidate['name'] = current_name(saved['name'])
            rows.append(policy['candidate_rank'](api, xml, candidate))
        rows.sort(key=lambda c: (-c['score'], c['id']))
        return api, rows

    old_api, old = ranked(False)
    api, rows = ranked(True)
    old_status, old_reason = policy['geo_decision'](old_api, old, relation)
    status, reason = policy['geo_decision'](api, rows, relation)
    best = rows[0]
    if best['id'] != t:
        status, reason = 'review', 'operator_target_not_best_candidate'
    result.update(reason=reason, previous_status=old_status, previous_reason=old_reason,
                  current_source_name=api['name'], current_target_name=current_name(selected['name']),
                  candidate_count=len(rows), name_similarity=best['name_similarity'],
                  distance_m=best['distance_m'], score_margin=(
                      round(best['score'] - rows[1]['score'], 4) if len(rows) > 1 else None),
                  ranked_candidates_sha256=digest(rows), best=best,
                  runner_up=rows[1] if len(rows) > 1 else None)
    if status == 'strong_candidate':
        result['status'] = 'eligible_for_guarded_import'
    return result


def build_report(archive_path, batch_paths, policy=None):
    raw_archive = Path(archive_path).read_bytes()
    if hashlib.sha256(raw_archive).hexdigest() != ARCHIVE_SHA256:
        raise ValueError('unreviewed historical archive')
    if len(batch_paths) != 2:
        raise ValueError('exactly two saved batches required')
    batches = []
    for path, expected in zip(batch_paths, BATCH_SHA256):
        raw = Path(path).read_bytes()
        if hashlib.sha256(raw).hexdigest() != expected:
            raise ValueError('unreviewed link batch')
        batches.append(json.loads(raw))
    policy = policy or policy_namespace()
    with ZipFile(archive_path) as archive:
        raw_triage = archive.read('anex-observed-hotel-triage.json')
        if hashlib.sha256(raw_triage).hexdigest() != TRIAGE_SHA256:
            raise ValueError('triage provenance mismatch')
        triage = json.loads(raw_triage)
        accepted, manual_ids = accepted_snapshot(archive)
    dossier_by_id = {r['anex_hotel_id']: r for r in triage['rows']}
    rows, unknown, seen = [], [], set()
    for batch, entries_key, status_key, positive_status in (
        (batches[0], 'entries', 'status', 'visible_operator_link'),
        (batches[1], 'rows', 'result', 'confirmed'),
    ):
        for entry in batch[entries_key]:
            a = positive_id(entry['anex_hotel_id'])
            if a in seen:
                raise ValueError('repeated hotel in saved batches')
            seen.add(a)
            if entry[status_key] == 'unknown':
                unknown.append({'anex_hotel_id': a, 'catalog_hotel_id': entry['local_hotel_id'],
                                'status': 'unknown_not_no_match', 'automatic_retry': False})
                continue
            if entry[status_key] != positive_status:
                raise ValueError('unexpected saved status')
            rows.append(review_pair(entry, dossier_by_id[a], accepted, manual_ids, set(), policy))
    if len(rows) != 10 or len(unknown) != 6:
        raise ValueError('saved batch totals mismatch')
    counts = Counter(r['status'] for r in rows)
    return {
        'schema_version': 1, 'issue': 1759, 'scope': 'offline_saved_evidence',
        'algorithm': 'explicit_former_suffix_only_plus_existing_strict_gates_v1',
        'sources': {'anex_archive_artifact': 10097668551, 'anex_archive_sha256': ARCHIVE_SHA256,
                    'triage_sha256': TRIAGE_SHA256, 'link_batch_sha256': list(BATCH_SHA256)},
        'historical_registry': {'accepted_links': len(accepted),
                                'unique_local_hotels': len(set(accepted.values())),
                                'manual_protected': len(manual_ids), 'live_readback': False},
        'counts': {'link_confirmed': len(rows), 'unknown_preserved': len(unknown),
                   'eligible_for_guarded_import': counts['eligible_for_guarded_import'],
                   'review': counts['review'], 'protected': counts['protected'],
                   'database_writes': 0, 'supplier_calls': 0, 'new_accepted_mappings': 0},
        'rows': rows, 'unknown': unknown,
        'limitations': ['Eligibility is not a persisted mapping.',
                        'Live manual decisions, active targets and pair exclusions require fresh importer guards.',
                        'This CLI has no network, SQL, apply or resolver-output capability.',
                        'No prior unknown searches or completed imports were replayed.'],
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--archive', required=True, type=Path)
    parser.add_argument('--batch1', required=True, type=Path)
    parser.add_argument('--batch2', required=True, type=Path)
    parser.add_argument('--output', required=True, type=Path)
    args = parser.parse_args()
    report = build_report(args.archive, (args.batch1, args.batch2))
    raw = json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True) + '\n'
    args.output.write_text(raw, encoding='utf-8')
    print(json.dumps(report['counts'], sort_keys=True))


if __name__ == '__main__':
    main()
