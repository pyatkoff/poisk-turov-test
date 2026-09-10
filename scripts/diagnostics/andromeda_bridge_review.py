#!/usr/bin/env python3
"""Offline #1759 shortlist or full inventory. Pinned archives; no SQL, network or apply."""
from __future__ import annotations

import argparse
from collections import Counter, defaultdict
import hashlib
import json
from pathlib import Path
import re
import unicodedata
from zipfile import ZipFile

from anex_tourvisor_link_review import accepted_snapshot, canonical, current_name, digest

ARCHIVES = {
    'anex': ('770d183c60ccff1537f1ce33879825f8fae6d40c68e882010d9bde8c49001021', 10097668551),
    'egypt': ('8d82b5b2e4db23c8af691e98d63e0c2c40df99a5822cb27494971879ef92cb63', 10113498830),
    'turkey': ('b03e15604fb3a3035efeab97a7099aaafca76ee6ae9a70513cbf176bc70dc49a', 10118184869),
}
# Completed historical promotions, not new actions or replay requests.
PROMOTIONS = {'416247': (9365, 34382905510), '2000042763': (447, 34382905510),
              '5354': (1280, 34388366170)}
COUNTRIES = {1: (3, 'Египет'), 4: (5, 'Турция')}


def normalized(value):
    text = unicodedata.normalize('NFKD', str(value or '')).casefold()
    text = ''.join(c for c in text if not unicodedata.combining(c))
    return ' '.join(re.findall(r'[^\W_]+', text))


def name_key(value):
    # Retain every distinguishing word and repeated token, including resort/the/adults.
    # Reordered whole words may match; joined words (Pasa Bey/Pasabey) do not.
    return ' '.join(sorted(w for w in normalized(current_name(value or '')).split()
                           if w != 'hotel'))


def catalogue_index(local):
    if local.get('complete') is not True or local.get('country_id') not in COUNTRIES:
        raise ValueError('incomplete country catalogue')
    hotels, index = {}, defaultdict(set)
    for hotel in local['hotels']:
        identifier = hotel['id']
        if (type(identifier) is not int or identifier <= 0 or identifier in hotels
                or hotel['country_id'] != local['country_id']):
            raise ValueError('invalid local catalogue identity')
        hotels[identifier] = hotel
        key = name_key(hotel['name'])
        if key:
            index[key].add(identifier)
    for alias in local['aliases']:
        if alias['hotel_id'] not in hotels:
            raise ValueError('orphan alias')
        key = name_key(alias['alias'])
        if key:
            index[key].add(alias['hotel_id'])
    return hotels, index


def geography(source, target, towns):
    """Use the actual supplier town/parent table, never name-derived city guesses."""
    town = towns.get(source['townKey'])
    if (not town or town['state'] != source['stateKey']
            or normalized(town['name']) != normalized(source['town'])):
        return {'status': 'unknown', 'reason': 'supplier_town_not_verified'}
    local = {normalized(target.get(k)) for k in ('region_name', 'subregion_name')} - {''}
    source_places = {normalized(town.get(k)) for k in ('name', 'RegionName')} - {''}
    detail = {'supplier_town_id': town['id'], 'supplier_town': town['name'],
              'supplier_parent_id': town.get('Region'), 'supplier_parent': town.get('RegionName'),
              'local_region': target.get('region_name'), 'local_subregion': target.get('subregion_name')}
    # A known local resort's parent can establish an explicit cross-region conflict.
    parents = {t.get('Region') for t in towns.values()
               if t['state'] == source['stateKey']
               and normalized(t['name']) == normalized(target.get('region_name'))} - {None}
    parents.update(t.get('Region') for t in towns.values()
                   if t['state'] == source['stateKey']
                   and normalized(t.get('RegionName')) == normalized(target.get('region_name'))
                   and t.get('Region') is not None)
    if len(parents) == 1 and town.get('Region') is not None and town['Region'] not in parents:
        return dict(detail, status='conflict', reason='different_supplier_parent_regions',
                    local_region_supplier_parent_ids=sorted(parents))
    if local & source_places:
        return dict(detail, status='supported', reason='supplier_town_or_parent_matches_local')
    return dict(detail, status='unknown', reason='geography_not_established')


def review_one(identity, target_id, local, catalogue, bridges, require_bridge=True):
    if identity['decision_status'] != 'pending' or identity['local_hotel_id'] is not None:
        raise ValueError('only unresolved identities may be proposed')
    if (require_bridge and not bridges) or any(b['local_id'] != target_id or type(b['anex_id']) is not int or b['anex_id'] <= 0 for b in bridges):
        raise ValueError('accepted bridge target mismatch')
    evidence = json.loads(identity['evidence_json'])
    source = evidence['source']
    country = local['country_id']
    supplier_country, country_name = COUNTRIES[country]
    if (str(source['id']) != identity['external_hotel_id'] or source['stateKey'] != supplier_country
            or catalogue['params']['STATEINC'] != supplier_country
            or normalized(source['state']) != normalized(country_name)):
        raise ValueError('supplier country or identity conflict')
    upstream = {h['id']: h for h in catalogue['payload']['HOTELS']}
    if upstream.get(source['id']) != source:
        raise ValueError('saved source differs from supplier catalogue')
    hotels, index = catalogue_index(local)
    if target_id not in hotels:
        raise ValueError('local target missing')
    candidates = set()
    for key in (name_key(source['name']), name_key(source.get('lName'))):
        if key:
            candidates.update(index.get(key, set()))
    towns = {t['id']: t for t in catalogue['payload']['TOWNTO']}
    target = hotels[target_id]
    geo = geography(source, target, towns)
    result = {'andromeda_id': identity['external_hotel_id'], 'supplier_namespace': 'andromeda_catalog',
              'source': source, 'local_hotel_id': target_id, 'local_hotel': target,
              'original_candidate_ids': evidence['candidate_ids'],
              'full_country_candidate_ids': sorted(candidates), 'anex_bridges': bridges,
              'expected_decision_status': 'pending',
              'expected_catalog_sha256': identity.get('catalog_sha256'),
              'expected_evidence_sha256': hashlib.sha256(identity['evidence_json'].encode()).hexdigest(),
              'source_row_sha256': digest(source), 'local_row_sha256': digest(target),
              'geography': geo, 'coordinates_verified': False, 'live_guards_checked': False,
              'category_difference': str(source.get('star')) != str(target.get('category'))}
    if geo['status'] == 'conflict':
        status = 'blocked_geography_conflict'
    elif candidates != {target_id}:
        status = 'review_name_or_ambiguity'
    elif evidence['candidate_ids'] and set(evidence['candidate_ids']) != {target_id}:
        status = 'review_original_candidate_conflict'
    elif geo['status'] != 'supported':
        status = 'review_geography_unknown'
    else:
        status = 'validated_proposal_not_accepted'
    return dict(result, status=status)


def build(paths, scope='shortlist'):
    if scope not in ('shortlist', 'all'):
        raise ValueError('unsupported scope')
    archives, provenance = {}, {}
    try:
        for key, (expected, artifact) in ARCHIVES.items():
            raw = Path(paths[key]).read_bytes()
            if hashlib.sha256(raw).hexdigest() != expected:
                raise ValueError('unreviewed archive: ' + key)
            archives[key] = ZipFile(paths[key])
            provenance[key] = {'artifact_id': artifact, 'zip_sha256': expected}
        def read(key, member):
            raw = archives[key].read(member)
            provenance[key].setdefault('members', {})[member] = hashlib.sha256(raw).hexdigest()
            return json.loads(raw)
        accepted, manual = accepted_snapshot(archives['anex'])
        anex_rows = read('anex', 'anex-hotel-catalog-match.json')['matches']
        turkey = read('turkey', 'capture.json')
        locals_ = {1: read('egypt', 'local-catalog.json'), 4: turkey['local']}
        catalogues = {1: read('egypt', 'all.json'), 4: turkey['catalog']}
        identities = {}
        for key in ('egypt', 'turkey'):
            request = read(key, 'import-request.json')
            for row in request['rows']:
                # Turkey ID150867 already existed; keep the original Egypt row.
                identities.setdefault(row['external_hotel_id'], dict(row, catalog_sha256=request['catalog_sha256']))
        for external, (target, _) in PROMOTIONS.items():
            row = identities[external]
            if row['decision_status'] != 'pending':
                raise ValueError('historical promotion baseline changed')
            row.update(decision_status='accepted', local_hotel_id=target)
        statuses = Counter(r['decision_status'] for r in identities.values())
        if statuses != {'accepted': 2846, 'pending': 1310, 'conflict': 7}:
            raise ValueError('historical import totals changed')
        index = defaultdict(list)
        for row in anex_rows:
            a = row['external_id']
            if a not in accepted:
                continue
            for key in {name_key(row['name']), name_key(row.get('alternate_name'))} - {''}:
                index[(normalized(row['country']), key)].append({
                    'anex_id': a, 'local_id': accepted[a], 'name': row['name'],
                    'town': row.get('town'), 'source_row_sha256': digest(row),
                    'manual_accepted': a in manual})
        rows, counts = [], Counter()
        for external, identity in sorted(identities.items(), key=lambda item: int(item[0])):
            if identity['decision_status'] != 'pending':
                continue
            source = json.loads(identity['evidence_json'])['source']
            bridges = {}
            for key in {name_key(source['name']), name_key(source.get('lName'))} - {''}:
                for b in index.get((normalized(source['state']), key), []):
                    bridges[b['anex_id']] = b
            targets = {b['local_id'] for b in bridges.values()}
            original = set(json.loads(identity['evidence_json'])['candidate_ids'])
            if not targets:
                counts['no_name_bridge'] += 1
                continue
            if len(targets) != 1 or original and original != targets:
                counts['ambiguous_bridge_or_original_conflict'] += 1
                continue
            country = next((c for c, (s, _) in COUNTRIES.items() if s == source['stateKey']), None)
            if country is None:
                raise ValueError('unsupported source country')
            rows.append(review_one(identity, next(iter(targets)), locals_[country],
                                   catalogues[country], [bridges[a] for a in sorted(bridges)]))
        if len(rows) != 40 or counts != {'no_name_bridge': 1241, 'ambiguous_bridge_or_original_conflict': 29}:
            raise ValueError('bounded saved candidate totals changed')
        counts.update(r['status'] for r in rows)
        current_local = {r['local_hotel_id'] for r in identities.values() if r['decision_status'] == 'accepted'}
        valid_local = {r['local_hotel_id'] for r in rows if r['status'] == 'validated_proposal_not_accepted'}
        triple = set(accepted.values()) & current_local
        report = {'schema_version': 1, 'date': '2026-09-10', 'scope': 'offline_saved_catalogue_review',
                'issue': 1759, 'sources': provenance, 'counts': dict(counts), 'rows': rows,
                'previous_shortlist_count': 39, 'additional_word_order_candidate_ids': ['2000068096'],
                'historical_promotions': PROMOTIONS,
                'catalogue_coverage': {str(c): {'hotels': len(v['hotels']), 'aliases': len(v['aliases']),
                                               'complete': v['complete']} for c, v in locals_.items()},
                'effect_if_all_validated_proposals_are_accepted': {
                    'conditional_not_live': True, 'additional_provider_ids': sum(r['status'] == 'validated_proposal_not_accepted' for r in rows),
                    'new_unique_local_hotels': len(valid_local - current_local),
                    'already_has_other_accepted_andromeda_id': len(valid_local & current_local),
                    'triple_before': len(triple),
                    'triple_after': len(set(accepted.values()) & (current_local | valid_local))},
                'database_writes': 0, 'supplier_calls': 0, 'new_accepted_mappings': 0,
                'limitations': ['Saved 2026-09-09 data, not a fresh database read.',
                                'Proposal is not an import authorization or a live mapping.',
                                'Coordinate evidence is absent from these two local exports.',
                                'Before writes recheck active target/country, current pending row digest, accepted ANEX bridge and exclusions.',
                                'No existing accepted mapping was changed; conflict is a proposal-review status.']}
        if scope == 'all':
            # Freeze legacy provenance before adding inventory-only evidence members.
            report['sources'] = json.loads(json.dumps(provenance))
            scaled = scale_report(identities, anex_rows, accepted, manual, locals_, catalogues, report, read)
            scaled['sources'] = provenance
            return scaled
        return report
    finally:
        for archive in archives.values():
            archive.close()


def exact_candidates(index, values):
    result = set()
    for value in values:
        key = name_key(value)
        if key:
            result.update(index.get(key, set()))
    return result


def planned_batches(rows, size=100):
    """Stable evidence-only batches; neither a SQL payload nor an apply permit."""
    if type(size) is not int or not 1 <= size <= 500:
        raise ValueError('invalid batch size')
    ordered = sorted(rows, key=lambda r: (r['provider'], int(r['external_id'])))
    identities = [(r['provider'], r['external_id']) for r in ordered]
    if len(set(identities)) != len(identities):
        raise ValueError('duplicate batch identity')
    result = []
    for start in range(0, len(ordered), size):
        chunk = ordered[start:start + size]
        members = [{'provider': r['provider'], 'external_id': r['external_id'],
                    'local_hotel_id': r['local_hotel_id'], 'evidence_sha256': digest(r)} for r in chunk]
        result.append({'batch_sha256': digest(members), 'rows': members,
                       'count': len(members), 'state': 'planned_not_applied', 'apply_allowed': False})
    return result


def scale_report(identities, anex_rows, accepted, manual, locals_, catalogues, prior, read):
    """Inventory every unresolved ID in the saved complete countries in one pass."""
    contexts = {c: catalogue_index(local) for c, local in locals_.items()}
    country_by_name = {normalized(label): c for c, (_, label) in COUNTRIES.items()}
    country_by_supplier = {supplier: c for c, (supplier, _) in COUNTRIES.items()}
    bridges_by_local = defaultdict(list)
    for source in anex_rows:
        a = source['external_id']
        country = country_by_name.get(normalized(source['country']))
        if a in accepted and country is not None and accepted[a] in contexts[country][0]:
            bridges_by_local[accepted[a]].append({'anex_id': a, 'local_id': accepted[a],
                'source_row_sha256': digest(source), 'manual_accepted': a in manual})
    previous = {r['andromeda_id'] for r in prior['rows']
                if r['status'] == 'validated_proposal_not_accepted'}
    history = {}
    for member in ('anex-hotel-geo-enrichment.json', 'anex-initial-search-checkpoint.json',
                   'anex-observed-hotel-checkpoint.json'):
        for row in read('anex', member)['rows']:
            if row.get('api'):
                history[row['external_id']] = (member, row)
    observations = {r['anex_hotel_id']: r for r in read('anex', 'anex-observed-hotel-triage.json')['rows']}
    ready_anex = {8121: 6319, 16275: 1326, 23775: 17568, 26688: 55648}
    rows = []
    for external, identity in sorted(identities.items(), key=lambda item: int(item[0])):
        if identity['decision_status'] != 'pending':
            continue
        evidence = json.loads(identity['evidence_json'])
        source = evidence['source']
        country = country_by_supplier.get(source['stateKey'])
        if country is None or str(source['id']) != external:
            raise ValueError('unresolved source identity mismatch')
        hotels, index = contexts[country]
        candidates = exact_candidates(index, (source['name'], source.get('lName')))
        row = {'provider': 'andromeda', 'external_id': external, 'country_id': country,
               'name': source['name'], 'source_row_sha256': digest(source),
               'expected_evidence_sha256': hashlib.sha256(identity['evidence_json'].encode()).hexdigest(),
               'expected_catalog_sha256': identity['catalog_sha256'],
               'candidate_ids': sorted(candidates), 'local_hotel_id': None,
               'previously_validated': external in previous, 'live_guards_checked': False}
        if len(candidates) != 1:
            row['status'] = 'review_ambiguous_name' if candidates else 'no_exact_name_candidate'
            row['original_candidate_ids'] = evidence['candidate_ids']
        else:
            target = next(iter(candidates))
            detail = review_one(identity, target, locals_[country], catalogues[country],
                               sorted(bridges_by_local[target], key=lambda b: b['anex_id']),
                               require_bridge=False)
            row.update(local_hotel_id=target, status=detail['status'], detail=detail)
        rows.append(row)
    outside = Counter()
    for source in sorted(anex_rows, key=lambda r: r['external_id']):
        a = source['external_id']
        if a in accepted or a in manual:
            continue
        country = country_by_name.get(normalized(source['country']))
        if country is None:
            outside[source['country'] or '(unknown country)'] += 1
            continue
        hotels, index = contexts[country]
        candidates = exact_candidates(index, (source['name'], source.get('alternate_name')))
        row = {'provider': 'anex', 'external_id': str(a), 'country_id': country,
               'name': source['name'], 'source_town': source.get('town'),
               'source_row_sha256': digest(source), 'candidate_ids': sorted(candidates),
               'local_hotel_id': None, 'previously_import_ready': a in ready_anex,
               'live_guards_checked': False, 'observation': observations.get(a, {}).get('observation')}
        if len(candidates) != 1:
            row['status'] = 'review_ambiguous_name' if candidates else 'no_exact_name_candidate'
        else:
            target = next(iter(candidates)); hotel = hotels[target]
            member, saved = history.get(a, (None, {})); api = saved.get('api', {})
            online = (api.get('id') == a and saved.get('api_xml_relation') == 'same_record'
                      and normalized(api.get('country')) == normalized(source['country']))
            candidate = next((c for c in saved.get('candidates', []) if c['id'] == target), None)
            distance = candidate.get('distance_m') if candidate else None
            places = {normalized(hotel.get(k)) for k in ('region_name', 'subregion_name')} - {''}
            supplier_places = {normalized(source.get('town'))} - {''}
            if online:
                supplier_places |= {normalized(api.get(k)) for k in ('region', 'town')} - {''}
            status = 'review_online_identity_missing'
            if online:
                status = 'review_saved_online_evidence'
                if distance is not None and distance > 5000:
                    status = 'blocked_saved_coordinate_conflict'
                elif saved.get('reason') == 'candidate_limit_reached' or len(saved.get('candidates', [])) >= 256:
                    status = 'review_candidate_set_truncated'
                elif distance is not None and distance <= 200:
                    status = 'review_saved_close_coordinates'
            row.update(local_hotel_id=target, status=status, local_hotel=hotel,
                       local_row_sha256=digest(hotel), online_identity_recorded=online,
                       geography_text_agrees=bool(places & supplier_places),
                       saved_distance_m=distance, saved_evidence_member=member,
                       saved_evidence_sha256=digest(saved) if saved else None,
                       saved_reason=saved.get('reason'),
                       saved_candidate_count=len(saved.get('candidates', [])))
            if a in ready_anex and ready_anex[a] != target:
                raise ValueError('previous four-pair package changed target')
        rows.append(row)
    counts = {provider: dict(Counter(r['status'] for r in rows if r['provider'] == provider))
              for provider in ('andromeda', 'anex')}
    valid = [r for r in rows if r['provider'] == 'andromeda'
             and r['status'] == 'validated_proposal_not_accepted']
    current_local = {r['local_hotel_id'] for r in identities.values() if r['decision_status'] == 'accepted'}
    proposed_local = {r['local_hotel_id'] for r in valid}
    prior_local = {r['local_hotel_id'] for r in prior['rows'] if r['andromeda_id'] in previous}
    candidate_anex = [r for r in rows if r['provider'] == 'anex' and len(r['candidate_ids']) == 1]
    previous_pairs = {(r['andromeda_id'], r['local_hotel_id']) for r in prior['rows'] if r['andromeda_id'] in previous}
    if not previous_pairs <= {(r['external_id'], r['local_hotel_id']) for r in valid}:
        raise ValueError('previous validated proposals were lost or reassigned')
    queue_anex = [r for r in candidate_anex if not r['previously_import_ready']
                  and not r['status'].startswith('blocked_')]
    return {'schema_version': 1, 'issue': 1759, 'date': '2026-09-10',
            'scope': 'all_saved_unresolved_turkey_egypt', 'sources': prior['sources'],
            'previous_report_canonical_sha256': digest(prior),
            'catalogue_coverage': prior['catalogue_coverage'], 'counts': counts,
            'totals': {'andromeda_pending_examined': sum(counts['andromeda'].values()),
                       'anex_unmapped_examined': sum(counts['anex'].values()),
                       'all_unresolved_examined': len(rows),
                       'andromeda_validated_proposals': len(valid),
                       'previous_andromeda_proposals_preserved': len(previous),
                       'additional_andromeda_proposals': sum(not r['previously_validated'] for r in valid),
                       'anex_unique_name_candidates': len(candidate_anex),
                       'anex_previous_import_ready': sum(r['previously_import_ready'] for r in candidate_anex),
                       'anex_new_review_queue': len(queue_anex)},
            'rows': rows, 'unmapped_anex_outside_loaded_countries': dict(sorted(outside.items())),
            'batches': {'andromeda_validated': planned_batches(valid),
                        'anex_candidates_requiring_review': planned_batches(queue_anex)},
            'effect_if_all_validated_proposals_are_accepted': {
                'conditional_not_live': True,
                'triple_before': len(set(accepted.values()) & current_local),
                'triple_after': len(set(accepted.values()) & (current_local | proposed_local)),
                'additional_triples_beyond_previous_package': len((proposed_local - current_local - prior_local) & set(accepted.values())),
                'new_unique_local_hotels': len(proposed_local - current_local)},
            'database_writes': 0, 'supplier_calls': 0, 'new_accepted_mappings': 0,
            'limitations': prior['limitations'] + [
                'ANEX exact-name candidates are not eligible mappings; saved online evidence still requires strict revalidation.',
                'Batch manifests are evidence only, not import authorization or executable SQL.',
                'No arbitrary new country, API quota or protected production scope is enabled.']}


def scale_summary(report):
    keys = ('schema_version', 'issue', 'date', 'scope', 'sources', 'previous_report_canonical_sha256',
            'catalogue_coverage', 'counts', 'totals', 'unmapped_anex_outside_loaded_countries',
            'effect_if_all_validated_proposals_are_accepted', 'database_writes', 'supplier_calls',
            'new_accepted_mappings', 'limitations')
    result = {key: report[key] for key in keys}
    result['full_report_canonical_sha256'] = digest(report)
    result['batch_counts'] = {key: len(value) for key, value in report['batches'].items()}
    result['additional_andromeda_pairs'] = [[r['external_id'], r['local_hotel_id']]
        for r in report['rows'] if r['provider'] == 'andromeda'
        and r['status'] == 'validated_proposal_not_accepted' and not r['previously_validated']]
    return result


def summary(report):
    """Compact checkpoint; full source-bound dossiers are reproduced by this CLI."""
    keys = ('schema_version', 'date', 'issue', 'scope', 'sources', 'counts',
            'catalogue_coverage', 'effect_if_all_validated_proposals_are_accepted',
            'previous_shortlist_count', 'additional_word_order_candidate_ids',
            'database_writes', 'supplier_calls', 'new_accepted_mappings', 'limitations')
    result = {k: report[k] for k in keys}
    result['full_report_canonical_sha256'] = digest(report)
    result['pairs_by_status'] = {status: [[r['andromeda_id'], r['local_hotel_id']]
        for r in report['rows'] if r['status'] == status]
        for status in sorted({r['status'] for r in report['rows']})}
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for key in ARCHIVES:
        parser.add_argument('--' + key, required=True, type=Path)
    parser.add_argument('--scope', choices=('shortlist', 'all'), default='shortlist')
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--summary', type=Path)
    args = parser.parse_args()
    report = build({k: getattr(args, k) for k in ARCHIVES}, scope=args.scope)
    args.output.write_text(json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True) + '\n', encoding='utf-8')
    if args.summary:
        args.summary.write_text(json.dumps(scale_summary(report) if args.scope == 'all' else summary(report), ensure_ascii=False, indent=2, sort_keys=True) + '\n', encoding='utf-8')
    print(json.dumps({'counts': report['counts'], 'effect': report['effect_if_all_validated_proposals_are_accepted']}, ensure_ascii=False))


if __name__ == '__main__':
    main()
