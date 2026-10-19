#!/usr/bin/env python3
"""Offline candidate-evidence recovery. Never authorizes searches or mapping writes.

Read two immutable, completed MATCH artifacts; preserve every frontier identity.
Candidate discovery is deliberately separate from the existing acceptance review.
Only complete token-boundary containment and explicit EX/former lists are used.
"""
from __future__ import annotations
import argparse
import collections
import hashlib
import json
import math
import re
import unicodedata
import zipfile
from pathlib import Path

PINS = {
    'current': {
        'artifact_id': 10455217564,
        'zip_sha256': '7fc52b9f312b1ea10bc46b0a4c3a2cb628b691a2de0a7fee3d758e79c142f80a',
        'result_sha256': 'cbd3d25c9d1e75ae0871dbeb74f0b97f7b546021a1be2b5b8f4a8b80afe80183',
        'review_sha256': '554d38008fd9f20009f7b82c4cd57219d793f9c79b3de3bafb7e9bbeddf9e30d',
        'operation_id': 'hotel-match-hierarchy-current-snapshot-1971-20260916-v1',
        'source_sha': '7526c6a47ad540178b0d08bfc146c86ec5f6e1bb',
    },
    'census': {
        'artifact_id': 10394524643,
        'zip_sha256': '6da39881ac362b1ad433bc6613baf6f97bdc6c396d89ffa49eec517e5edaa2fc',
        'result_sha256': 'f135bb42d40b0f3134309b24f5bdffcca8f2e62fa9511d36dfa14c04d96ccd1b',
        'operation_id': 'hotel-match-core8-residual-current-1971-20260915-v1',
        'source_sha': '3e7d975c12d146da276e76e45ea702898db9f92a',
    },
}
GENERIC = frozenset('hotel hotels resort resorts spa отель'.split())
QUALIFIERS = frozenset('annex annexe beach garden gardens north south east west mountain posh family junior deluxe aqua park palace royal grand premium select bay island village pool sea adult adults sun moon main'.split())
# This is a retrieval limit, never an acceptance threshold. No fuzzy score is used.
MIN_SHARED_CHARACTERS = 6


def require(condition: bool, reason: str) -> None:
    if not condition:
        raise ValueError(reason)


def canonical(value: object) -> bytes:
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2, allow_nan=False) + '\n').encode()


def digest(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()


def decode(raw: bytes) -> dict:
    def unique(pairs):
        result = {}
        for key, value in pairs:
            require(key not in result, 'duplicate_json_key')
            result[key] = value
        return result
    def invalid(_):
        raise ValueError('nonfinite_json')
    value = json.loads(raw, object_pairs_hook=unique, parse_constant=invalid)
    require(isinstance(value, dict), 'json_object_required')
    return value


def read_artifact(path: Path, kind: str) -> tuple[dict, dict | None]:
    pin = PINS[kind]
    raw = path.read_bytes()
    require(len(raw) <= 16000000 and digest(raw) == pin['zip_sha256'], 'archive_pin')
    with zipfile.ZipFile(path) as archive:
        infos = archive.infolist()
        require(len(infos) <= 40 and len(infos) == len({i.filename for i in infos}), 'archive_members')
        require(sum(i.file_size for i in infos) < 64000000, 'expanded_size')
        result_raw = archive.read('server/result.json')
        result = decode(result_raw)
        receipt = decode(archive.read('server/receipt.json'))
        reservation = decode(archive.read('server/reservation.json'))
        require(archive.read('reservation.json') == archive.read('server/reservation.json'), 'reservation_bytes')
        for document in (result, receipt, reservation):
            require(document.get('operation_id') == pin['operation_id'], 'operation_binding')
            require(document.get('source_sha') == pin['source_sha'], 'source_binding')
            require(document.get('no_replay') is True, 'no_replay')
        require(digest(result_raw) == receipt.get('result_sha256') == pin['result_sha256'], 'result_pin')
        require(result.get('state') == receipt.get('state') == 'completed_read_only', 'not_completed_read')
        require(receipt.get('readback_verified') is True, 'receipt_readback')
        for key in ('mapping_writes', 'supplier_calls', 'tourvisor_calls'):
            require(type(result.get(key)) is int and result[key] == 0, 'input_not_read_only')
        review = None
        if kind == 'current':
            review_raw = archive.read('current-review.json')
            require(digest(review_raw) == pin['review_sha256'], 'review_pin')
            review = decode(review_raw)
            require(review['current_snapshot']['result_sha256'] == pin['result_sha256'], 'review_result_binding')
    return result, review


def name_forms(name: str) -> list[dict]:
    require(isinstance(name, str) and len(name) <= 4096, 'invalid_name')
    segments = re.split(r'\b(?:ex|former|formerly)\b\.?', name, flags=re.I)
    pieces = [(segments[0], 'primary')]
    for old in segments[1:]:
        # Preserve full old text as well. Fragments are marked, not accepted aliases.
        pieces.append((old, 'explicit_former_full'))
        if ',' in old or ';' in old:
            pieces.extend((part, 'explicit_former_list_item') for part in re.split('[,;]', old) if part.strip(' ()[]'))
    out = []
    for part, role in pieces:
        text = unicodedata.normalize('NFKC', part).casefold().replace("'", '').replace('’', '')
        text = re.sub(r'\baquapark\b', 'aqua park', text)
        tokens = tuple(t for t in re.findall(r'[^\W_]+', text) if t not in GENERIC)
        require(len(tokens) <= 128, 'name_token_budget')
        if tokens:
            out.append({'compact': ''.join(tokens), 'tokens': tokens, 'role': role, 'raw_fragment': part.strip(),
                        'original_name': name, 'qualifiers': tuple(sorted(t for t in tokens if t in QUALIFIERS)),
                        'numbers': tuple(re.findall(r'\d+', ' '.join(tokens)))})
    return out


def windows(tokens):
    for start in range(len(tokens)):
        key = ''
        for end in range(start, len(tokens)):
            key += tokens[end]
            if len(key) >= MIN_SHARED_CHARACTERS:
                yield key, start, end + 1


def build_index(hotels: dict, aliases: dict):
    full, spans, forms, frequency = collections.defaultdict(list), collections.defaultdict(list), [], collections.defaultdict(set)
    for lid in sorted(hotels, key=int):
        h = hotels[lid]
        require(type(h['country_id']) is int and h['country_id'] > 0, 'country_type')
        for name in sorted(set(aliases.get(lid, [])) | {h['name']}):
            for form in name_forms(name):
                index = len(forms)
                forms.append((lid, form))
                full[(h['country_id'], form['compact'])].append(index)
                for token in set(form['tokens']):
                    frequency[(h['country_id'], token)].add(lid)
                for key, lo, hi in windows(form['tokens']):
                    spans[(h['country_id'], key)].append((index, lo, hi))
    return full, spans, forms, frequency


def identifying_span(tokens, country, frequency):
    # Evidence retrieval, NOT acceptance: require an informative country-wide anchor.
    # Function words/qualifiers stay in comparison/provenance but cannot identify a hotel.
    weak = {'the', 'and', 'by', 'of', 'at', 'a', 'an', 'in', 'on', 'for', 'le', 'la', 'les', 'de', 'des', 'el', 'al'}
    anchor = [t for t in tokens if t not in QUALIFIERS and t not in weak and not t.isdigit() and len(t) >= 3]
    if not anchor:
        return False
    rare = [t for t in anchor if len(t) >= 6 and len(frequency.get((country, t), ())) <= 3]
    common = None
    for token in anchor:
        observed = set(frequency.get((country, token), ()))
        common = observed if common is None else common.intersection(observed)
    # Empty corpus support can happen for a joined source spelling; the opposite
    # local form must also pass this function, without deleting a competitor.
    return bool(rare or len(anchor) >= 2 and sum(map(len, anchor)) >= 8 and common and len(common) <= 3)


def discover(names, country, index):
    full, spans, forms, frequency = index
    targets = collections.defaultdict(dict)
    for name in names:
        for source in name_forms(name):
            hits = [(i, 0, len(source['tokens']), lo, hi) for i, lo, hi in spans.get((country, source['compact']), [])]
            for key, lo, hi in windows(source['tokens']):
                hits.extend((i, lo, hi, 0, len(forms[i][1]['tokens'])) for i in full.get((country, key), []))
            for i, slo, shi, llo, lhi in hits:
                lid, local = forms[i]
                shared_source, shared_local = source['tokens'][slo:shi], local['tokens'][llo:lhi]
                require(''.join(shared_source) == ''.join(shared_local), 'nonexact_window')
                if not identifying_span(shared_source, country, frequency) or not identifying_span(shared_local, country, frequency):
                    continue
                whole = slo == llo == 0 and shi == len(source['tokens']) and lhi == len(local['tokens'])
                proof = {'source': source, 'local': local, 'source_span': [slo, shi], 'local_span': [llo, lhi],
                         'shared_compact': ''.join(shared_source), 'full_form_equal': whole,
                         'source_unmatched_tokens': source['tokens'][:slo] + source['tokens'][shi:],
                         'local_unmatched_tokens': local['tokens'][:llo] + local['tokens'][lhi:],
                         'kind': 'full_exact_form' if whole else 'complete_token_boundary_containment'}
                targets[lid][digest(canonical(proof))] = proof
    return {lid: sorted(values.values(), key=lambda p: (not p['full_form_equal'], canonical(p)))
            for lid, values in sorted(targets.items(), key=lambda item: int(item[0]))}


def point(value):
    try:
        a, b = float(value.get('latitude', value.get('lat'))), float(value.get('longitude', value.get('lon', value.get('lng'))))
        if math.isfinite(a) and math.isfinite(b) and abs(a) <= 90 and abs(b) <= 180 and (a or b):
            return a, b
    except (TypeError, ValueError):
        pass
    return None


def distance(a, b):
    x, y, u, v = map(math.radians, (*a, *b))
    h = math.sin((u-x)/2)**2 + math.cos(x)*math.cos(u)*math.sin((v-y)/2)**2
    return 6371.0088 * 2 * math.asin(math.sqrt(min(1, max(0, h))))


def recover(current: dict, review: dict, old: dict) -> dict:
    hotels, aliases = current['current_local_hotels'], current['current_aliases']
    index = build_index(hotels, aliases)
    old_rows = {row['external_hotel_id']: row for group in old['routes'].values() for row in group}
    excluded = {row['andromeda_hotel_id'] for row in review['frontier_holds_preserved']}
    ids = [d['external_hotel_id'] for d in review['dossiers']]
    require(len(ids) == len(set(ids)) == current['frontier_count'], 'frontier_coverage')
    require(not excluded.intersection(ids), 'excluded_reintroduced')
    rows, stats, by_country = [], collections.Counter(), collections.Counter()
    for dossier in review['dossiers']:
        eid, source = dossier['external_hotel_id'], dossier['frontier']
        require(dossier['supplier_namespace'] == source['supplier_namespace'] == 'andromeda_catalog', 'namespace')
        require(eid == source['andromeda_hotel_id'], 'source_identity')
        require(dossier['safe_to_write_now'] is False and review['safe_to_write_now'] is False, 'acceptance_input')
        prior = dossier['hotel_identity_review']
        snapshot = current['current_frontier'].get(eid)
        source_holds = set(dossier['evidence_flags']) | set(dossier['geography_evidence']['holds'])
        if 'inherited_hard_conflict' in prior['holds']:
            source_holds.add('inherited_hard_conflict')
        if not snapshot:
            source_holds.add('source_missing_in_snapshot')
        else:
            source_holds.update(snapshot['holds'])
            if snapshot['evidence_sha256'] != source['evidence_sha256']:
                source_holds.add('source_digest_changed')
            if snapshot['decision_status'] != 'pending' or snapshot['local_hotel_id'] is not None:
                source_holds.add('not_pending_null_in_snapshot')
        points = []
        earlier = old_rows.get(eid)
        if earlier and earlier.get('evidence_sha256') == source['evidence_sha256'] and earlier.get('country_id') == source['country_id']:
            points.extend(p for item in earlier.get('points', []) if (p := point(item)) is not None)
        for item in dossier['retained_evidence']:
            p = point(item.get('hotel_fields', {}))
            if p is not None:
                points.append(p)
        already_proven_in_prior_review = prior['route'] == 'prepared_for_current_review'
        proposals = {} if already_proven_in_prior_review else discover(source['names'], source['country_id'], index)
        candidates = []
        known_targets = {str(x) for x in prior.get('candidate_ids', [])}
        if prior.get('target') is not None:
            known_targets.add(str(prior['target']))
        for lid, proofs in proposals.items():
            target = hotels[lid]
            holds = set(source_holds)
            anchors = prior['effective_geo_anchors']
            geography = 'unknown'
            if anchors:
                geography = 'consistent'
                for anchor in anchors:
                    scope, value = anchor.get('scope'), anchor.get('scope_id')
                    if scope not in {'region', 'subregion'} or anchor.get('country_id', source['country_id']) != source['country_id']:
                        holds.add('geography_context_invalid'); geography = 'conflict'
                    elif target.get(scope + '_id') != value:
                        holds.add('candidate_geography_conflict'); geography = 'conflict'
            # A single guard-compatible spelling form suffices only for evidence priority,
            # not for acceptance. Every contradictory form/provenance is still retained.
            compatible = [p for p in proofs if p['source']['qualifiers'] == p['local']['qualifiers'] and p['source']['numbers'] == p['local']['numbers']]
            if not compatible:
                holds.add('qualifier_or_number_difference_requires_independent_proof')
            occupied = current['current_occupancy'].get(lid, [])
            if any(x != eid for x in occupied):
                holds.add('occupied_target_in_snapshot')
            tp = point(target)
            distances = [distance(p, tp) for p in points] if tp else []
            if points and not tp:
                holds.add('target_coordinate_missing')
            if any(d > 5 for d in distances):
                holds.add('coordinate_conflict_gt5km')
            if not holds and geography == 'consistent':
                stage = 'name_evidence_needed_with_geography_support'
            elif not holds:
                stage = 'name_and_geography_evidence_needed'
            else:
                stage = 'held_for_conflicting_or_protected_context'
            candidates.append({'proposed_local_hotel_id': int(lid), 'local_name': target['name'],
                'local_country_id': target['country_id'], 'local_region_id': target['region_id'],
                'local_subregion_id': target['subregion_id'], 'local_row_sha256': digest(canonical(target)),
                'candidate_stage': stage, 'new_vs_prior_candidate_list': lid not in known_targets,
                'snapshot_holds': sorted(holds), 'geography_status': geography,
                'snapshot_occupancy': occupied, 'distances_km': distances, 'name_evidence': proofs,
                'safe_to_write_now': False, 'safe_to_query_supplier_now': False})
        new = [x for x in candidates if x['new_vs_prior_candidate_list']]
        promising = [x for x in new if x['candidate_stage'] == 'name_evidence_needed_with_geography_support']
        stats['frontier_rows'] += 1
        stats['previously_prepared_not_researched'] += already_proven_in_prior_review
        stats['with_retrieved_candidates'] += bool(candidates)
        stats['with_new_candidate_targets'] += bool(new)
        stats['with_new_geographically_supported_candidate'] += bool(promising)
        stats['candidate_pairs'] += len(candidates)
        stats['new_candidate_pairs'] += len(new)
        stats['new_geographically_supported_pairs'] += len(promising)
        stats['unknown_current_frequency_preserved'] += source['frequency'] is None
        stats['no_candidate_in_this_retrieval_tier'] += not candidates and not already_proven_in_prior_review
        if new:
            by_country[source['country_name']] += 1
        rows.append({'external_hotel_id': eid, 'supplier_namespace': 'andromeda_catalog',
            'source_evidence_sha256': source['evidence_sha256'], 'source_names': source['names'],
            'country_id': source['country_id'], 'country_name': source['country_name'],
            'frequency': source['frequency'], 'retained_search_observed': dossier['retained_search_observed'],
            'retained_search_documents_lower_bound': dossier['retained_search_documents_lower_bound'],
            'prior_review': prior, 'previously_prepared_not_researched': already_proven_in_prior_review, 'inherited_evidence_flags': dossier['evidence_flags'],
            'retrieved_candidates': candidates, 'safe_to_write_now': False, 'safe_to_query_supplier_now': False})
    return {'schema': 'match-name-evidence-recovery/1', 'state': 'offline_candidate_evidence_only',
        'input_pins': PINS, 'local_snapshot_at_utc': current['read_at_utc'],
        'historical_source_geo_snapshot_at_utc': old['created_at'],
        'summary': dict(sorted(stats.items())), 'new_candidate_dossiers_by_country': dict(sorted(by_country.items())),
        'all_original_frontier_ids_preserved': ids, 'excluded_frontier_ids_preserved': sorted(excluded),
        'dossiers': rows, 'supplier_calls': 0, 'database_writes': 0, 'mapping_writes': 0,
        'safe_to_write_now': False, 'safe_to_query_supplier_now': False,
        'limitations': [
            'Candidate discovery is NOT hotel identity confirmation and never clears original holds.',
            'Unique substring or token containment is NOT proof of a physical hotel.',
            'A former-name list item is retained source text, not an independently accepted alias.',
            'Snapshot geography and occupancy are historical; all current protections must be revalidated.',
            'Missing candidates here do not mean absent, unsold or final-manual hotels.',
            'No denied collector/apply is run, reproduced, renamed or dispatched by this offline analysis.',
        ]}



def audit_recovery(report: dict) -> dict:
    """Validate all recovered pairs and expose evidence gaps, never clear holds."""
    require(report.get('schema') == 'match-name-evidence-recovery/1', 'recovery_schema')
    require(report.get('input_pins') == PINS, 'recovery_input_pins')
    require(report.get('safe_to_write_now') is False and report.get('safe_to_query_supplier_now') is False, 'recovery_authority')
    rows = report['dossiers']
    ids = [row['external_hotel_id'] for row in rows]
    require(ids == report['all_original_frontier_ids_preserved'] and len(ids) == len(set(ids)), 'audit_frontier_coverage')
    require(not set(ids).intersection(report['excluded_frontier_ids_preserved']), 'audit_exclusions')
    pairs, tiers, extra_tiers, evidence_types = [], collections.Counter(), collections.Counter(), collections.Counter()
    seen_pairs = set()
    for row in rows:
        eid = row['external_hotel_id']
        require(isinstance(eid, str) and re.fullmatch(r'[1-9][0-9]{0,19}', eid) is not None, 'audit_typed_id')
        require(row['supplier_namespace'] == 'andromeda_catalog', 'audit_namespace')
        require(row['safe_to_write_now'] is False and row['safe_to_query_supplier_now'] is False, 'row_authority')
        require(re.fullmatch(r'[a-f0-9]{64}', row['source_evidence_sha256']) is not None, 'audit_source_digest')
        candidates = row['retrieved_candidates']
        competing_ids = sorted(c['proposed_local_hotel_id'] for c in candidates)
        require(len(competing_ids) == len(set(competing_ids)), 'duplicate_candidate_target')
        if row['previously_prepared_not_researched']:
            require(not candidates, 'prepared_researched')
        for candidate in candidates:
            lid = candidate['proposed_local_hotel_id']
            require(type(lid) is int and lid > 0, 'audit_local_id')
            require(candidate['local_country_id'] == row['country_id'], 'audit_country')
            require(candidate['safe_to_write_now'] is False and candidate['safe_to_query_supplier_now'] is False, 'candidate_authority')
            require((eid, lid) not in seen_pairs, 'duplicate_pair')
            seen_pairs.add((eid, lid))
            require(re.fullmatch(r'[a-f0-9]{64}', candidate['local_row_sha256']) is not None, 'audit_local_digest')
            full = []
            proof_digests = []
            for proof in candidate['name_evidence']:
                for side in ('source', 'local'):
                    form = proof[side]
                    require(any(canonical(form) == canonical(x) for x in name_forms(form['original_name'])), 'unbound_name_form')
                    if side == 'source':
                        require(form['original_name'] in row['source_names'], 'source_name_binding')
                sf, lf = proof['source'], proof['local']
                slo, shi = proof['source_span']; llo, lhi = proof['local_span']
                require(all(type(x) is int for x in (slo, shi, llo, lhi)), 'span_types')
                require(0 <= slo < shi <= len(sf['tokens']) and 0 <= llo < lhi <= len(lf['tokens']), 'span_bounds')
                shared = ''.join(sf['tokens'][slo:shi])
                require(shared == ''.join(lf['tokens'][llo:lhi]) == proof['shared_compact'], 'span_equality')
                require(list(sf['tokens'][:slo] + sf['tokens'][shi:]) == list(proof['source_unmatched_tokens']), 'source_unmatched')
                require(list(lf['tokens'][:llo] + lf['tokens'][lhi:]) == list(proof['local_unmatched_tokens']), 'local_unmatched')
                whole = slo == llo == 0 and shi == len(sf['tokens']) and lhi == len(lf['tokens'])
                require(type(proof['full_form_equal']) is bool and proof['full_form_equal'] == whole, 'whole_form_flag')
                require(proof['kind'] == ('full_exact_form' if whole else 'complete_token_boundary_containment'), 'proof_kind')
                compatible = sf['qualifiers'] == lf['qualifiers'] and sf['numbers'] == lf['numbers']
                if whole and compatible:
                    full.append({'source_role': sf['role'], 'local_role': lf['role'],
                                 'source_text': sf['raw_fragment'], 'local_text': lf['raw_fragment']})
                proof_digests.append(digest(canonical(proof)))
            require(bool(proof_digests), 'missing_name_proof')
            holds = list(candidate['snapshot_holds'])
            multiple = len(competing_ids) > 1
            geo = candidate['geography_status']
            require(geo in {'consistent', 'unknown', 'conflict'}, 'geography_status')
            if holds or geo == 'conflict':
                tier, stage = 4, 'held_context_requires_independent_resolution'
            elif multiple:
                tier, stage = 3, 'multiple_retrieved_targets_not_unique'
            elif geo != 'consistent':
                tier, stage = 2, 'name_and_geography_evidence_required'
            elif full:
                tier, stage = 0, 'exact_retained_form_requires_current_and_provenance_review'
            else:
                tier, stage = 1, 'unique_retrieved_target_requires_name_difference_proof'
            kind = 'full_retained_name_form' if full else 'token_boundary_containment_only'
            pair = {'external_hotel_id': eid, 'supplier_namespace': row['supplier_namespace'],
                'proposed_local_hotel_id': lid, 'country_id': row['country_id'], 'country_name': row['country_name'],
                'source_names': row['source_names'], 'local_name': candidate['local_name'],
                'source_evidence_sha256': row['source_evidence_sha256'], 'local_row_sha256': candidate['local_row_sha256'],
                'new_vs_prior_candidate_list': candidate['new_vs_prior_candidate_list'],
                'evidence_kind': kind, 'exact_retained_forms': full, 'name_proof_sha256': proof_digests,
                'priority_tier': tier, 'stage': stage, 'all_retrieved_target_ids': competing_ids,
                'multiple_retrieved_targets': multiple, 'geography_status': geo,
                'snapshot_holds': holds, 'prior_review_holds': row['prior_review']['holds'],
                'inherited_evidence_flags': row['inherited_evidence_flags'],
                'frequency': row['frequency'], 'retained_search_observed': row['retained_search_observed'],
                'historical_document_count_lower_bound': row['retained_search_documents_lower_bound'],
                'direct_provider_identity_proven': False, 'current_validation_required': True,
                'safe_to_write_now': False, 'safe_to_query_supplier_now': False}
            pairs.append(pair); tiers[stage] += 1; evidence_types[kind] += 1
            if pair['new_vs_prior_candidate_list']:
                extra_tiers[stage] += 1
    target_sources = collections.defaultdict(set)
    for p in pairs:
        target_sources[p['proposed_local_hotel_id']].add(p['external_hotel_id'])
    tiers.clear(); extra_tiers.clear()
    for p in pairs:
        p['retrieved_source_count_at_target'] = len(target_sources[p['proposed_local_hotel_id']])
        if p['retrieved_source_count_at_target'] > 1 and p['priority_tier'] < 4:
            p['priority_tier'] = 3
            p['stage'] = 'multiple_retrieved_candidates_not_unique'
        tiers[p['stage']] += 1
        if p['new_vs_prior_candidate_list']:
            extra_tiers[p['stage']] += 1
    require(len(rows) == report['summary']['frontier_rows'] and len(pairs) == report['summary']['candidate_pairs'], 'audit_counts')
    require(sum(p['new_vs_prior_candidate_list'] for p in pairs) == report['summary']['new_candidate_pairs'], 'audit_new_count')
    pairs.sort(key=lambda p: (p['priority_tier'], not p['retained_search_observed'],
                             -(p['historical_document_count_lower_bound'] or 0), int(p['external_hotel_id']), p['proposed_local_hotel_id']))
    return {'schema': 'match-recovered-pair-audit/1', 'state': 'offline_evidence_gaps_only',
        'recovery_report_sha256': digest(canonical(report)), 'input_pins': PINS,
        'local_snapshot_at_utc': report['local_snapshot_at_utc'],
        'summary': {'frontier_rows': len(rows), 'all_pairs': len(pairs), 'additional_pairs': report['summary']['new_candidate_pairs'],
                    'all_stages': dict(sorted(tiers.items())), 'additional_stages': dict(sorted(extra_tiers.items())),
                    'evidence_types': dict(sorted(evidence_types.items())),
                    'geography_supported_additional_pairs_with_multiple_targets': sum(p['new_vs_prior_candidate_list'] and p['geography_status'] == 'consistent' and not p['snapshot_holds'] and p['multiple_retrieved_targets'] for p in pairs)},
        'all_original_frontier_ids_preserved': ids, 'excluded_frontier_ids_preserved': report['excluded_frontier_ids_preserved'],
        'target_source_groups': {str(lid): sorted(values, key=int) for lid, values in sorted(target_sources.items())},
        'pairs': pairs, 'supplier_calls': 0, 'database_writes': 0, 'mapping_writes': 0,
        'safe_to_write_now': False, 'safe_to_query_supplier_now': False,
        'limitations': ['Exact retained name text is not a new direct provider-ID proof or a DB decision.',
                       'Every retrieved competitor is retained before geography filtering; unique here means retrieved, not proven physical identity.',
                       'Historical observations remain separate from unknown current demand.',
                       'Original reviews/holds remain unchanged; this view does not authorize a supplier call or acceptance.']}



def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('current_zip', type=Path)
    ap.add_argument('census_zip', type=Path)
    ap.add_argument('output', type=Path)
    ap.add_argument('--audit-output', type=Path, help='Optional complete evidence-gap view; no live operations')
    args = ap.parse_args()
    require(args.audit_output is None or args.audit_output.resolve() != args.output.resolve(), 'separate_output_paths')
    current, review = read_artifact(args.current_zip, 'current')
    old, _ = read_artifact(args.census_zip, 'census')
    report = recover(current, review, old)
    raw = canonical(report)
    with args.output.open('xb') as handle:
        require(handle.write(raw) == len(raw), 'output_write')
    require(args.output.read_bytes() == raw, 'output_readback')
    if args.audit_output is not None:
        audited = canonical(audit_recovery(report))
        with args.audit_output.open('xb') as handle:
            require(handle.write(audited) == len(audited), 'audit_output_write')
        require(args.audit_output.read_bytes() == audited, 'audit_output_readback')
    print(json.dumps({'summary': report['summary'], 'report_sha256': digest(raw)}, ensure_ascii=False))


if __name__ == '__main__':
    main()
