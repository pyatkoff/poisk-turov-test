#!/usr/bin/env python3
"""Offline missing-provider discovery packet. No HTTP, credentials, DB or executor.

Consumes hash-pinned completed artifacts; it never refreshes or replays them.
Optional --latest/--detail/--context enrich discovery without executing any operation.
An output row is a discovery proposal, NOT acceptance evidence or a current DB row.
Run with --queue/--photo/--egypt/--turkey/--selection archive paths and a NEW --output path.
"""
from __future__ import annotations

import argparse
import hashlib
import difflib
import json
import os
import re
import zipfile
import unicodedata
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any

CORE8 = {1, 2, 4, 8, 9, 10, 12, 16}
PINS = {
    'selection': (10361146401, '3f39a11548ddb74fde68dbe55ad9080875d13419e38cb4d4b107fc9adfdaf6db',
                  'hotel-match-old-tv-hotellist-mass-1971-20260914-v4-egypt', '4d125c0e3d1ef3da14b7f4afe75089686963efe2'),
    'queue': (10339548603, '78433a2dcaa4acc4a2089372d1d5c81fa27bc09bd55c19dec8c6bc1d1f989a20',
              'hotel-match-manual-live-queue-1971-20260914-v1', '8c086cd4a448f93b035a603958533bf99b473554'),
    'photo': (10351108943, '4a5c52f99ab5fe1abe9c3f11ad9fbc9809d9578478cc7cb0efbd8ba3591b21b7',
              'hotel-match-manual-live-photo-enriched-1971-20260914-v2', '4cf635673c4004cce54a7fdcf1dea3fddca460fe'),
    'egypt': (10355619797, 'e60d8be4e380400e28134f4d02e4baa052f677f67e671a934dc8f7d3383d6f88',
              'hotel-match-old-tv-hotelids-1971-20260914-v1-egypt-2026-10-31', 'c194b327b97aa6d890b22126d629d052e8f17afc'),
    'turkey': (10356597699, '8a9cd6b0ce785e409ede0b11e1755a1db5c60f8c02d6646cddddbe38d4f1c3d0',
               'hotel-match-old-tv-hotelids-1971-20260914-v1-turkey-2026-11-01', 'c194b327b97aa6d890b22126d629d052e8f17afc'),
}
PINS.update({
    'latest': (10364040179, 'a9fdf43e45e6da5fb6433f34270d56ff370b35c8ad017b89d1d771e12aa50003',
               'hotel-match-manual-live-queue-1971-20260914-v7', 'c8d3aaf27a6c030dbd180fe66745ad4fa84a5a7d'),
    'detail': (10364322730, 'a740032d12381a2841c141dd0dfea269ad8cdd152e4e9f1e9a811d7702b2809f',
               'hotel-match-current-saved-tour-detail-1971-20260914-v1-egypt', '4e7b64a3fdc30be2d740ea22dc0fb8018a573064'),
})
REQUESTED = {
    'egypt': '91404,131347,14140,97122,125,522,37412,293,21636,482,464,111423,132075,190,157782,83106,129,159,191,159159,182,196,73342,488,183,338,483,128,121626,130621',
    'turkey': '42591,28426,1600,37404,153743,17443,129511,59085,54633,81810,99257,115500,77557,70291,21838,85422,82811,68169,111046,69822,17586,17390,82420,163543,37547,104168,43550,104021,132803,53531',
}


def require(ok: bool, reason: str) -> None:
    if not ok:
        raise ValueError(reason)


def digest(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()


def canonical(value: Any) -> bytes:
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')) + '\n').encode()


def identity(row: dict[str, Any]) -> str:
    provider, ext = row.get('provider'), str(row.get('external_hotel_id', ''))
    require(provider in {'anex', 'andromeda'} and bool(re.fullmatch(r'[1-9][0-9]{0,14}', ext)), 'invalid_provider_identity')
    return f'{provider}:{ext}'


def load_archive(path: Path, kind: str) -> dict[str, Any]:
    """Validate exact GitHub artifact bytes, then its internal receipt/data binding."""
    artifact, expected, operation, source = PINS[kind]
    require(path.stat().st_size <= 20_000_000, 'archive_too_large')
    require(digest(path.read_bytes()) == expected, 'archive_pin_mismatch')
    with zipfile.ZipFile(path) as archive:
        names = archive.namelist()
        require(len(names) == len(set(names)), 'duplicate_archive_member')
        def read(name: str) -> bytes:
            require(archive.getinfo(name).file_size <= 5_000_000, 'member_too_large')
            return archive.read(name)
        def document(name: str) -> dict[str, Any]:
            value = json.loads(read(name))
            require(isinstance(value, dict), 'invalid_document')
            return value
        prefix = 'enriched/' if kind == 'photo' else ''
        result_raw = read(prefix + 'result.json')
        result = document(prefix + 'result.json')
        receipt, reservation = document('receipt.json'), document('reservation.json')
        for value in (result, receipt, reservation):
            require(value.get('operation_id') == operation and value.get('no_replay') is True, 'operation_identity_mismatch')
            require(value.get('database_writes') == 0, 'source_not_read_only')
        require(reservation.get('source_sha') == source, 'source_sha_mismatch')
        require(receipt.get('result_sha256') == digest(result_raw), 'receipt_hash_mismatch')
        require(receipt.get('state') == result.get('status'), 'receipt_status_mismatch')
        require(result.get('mapping_writes') == 0, 'source_mapping_write')
        if kind != 'photo':
            require(result.get('source_sha') == source and receipt.get('readback_verified') is True, 'source_readback_mismatch')
        else:
            require(receipt.get('source_sha') == source, 'source_sha_mismatch')
        output = {'artifact_id': artifact, 'archive_sha256': expected, 'result_sha256': digest(result_raw),
                  'operation_id': operation, 'source_sha': source, 'result': result, 'reservation': reservation}
        if kind in {'queue', 'photo', 'latest'}:
            name = 'enriched/queue-photo-enriched.json' if kind == 'photo' else 'queue.json'
            raw = read(name)
            require(digest(raw) == result.get('queue_json_sha256'), 'queue_hash_mismatch')
            queue = document(name)
            require(isinstance(queue.get('rows'), list) and len(queue['rows']) == result.get('queue_count'), 'queue_count_mismatch')
            output.update(rows=queue['rows'], queue_sha256=digest(raw))
        elif kind == 'detail':
            require(result.get('status') == 'completed' and result.get('credential_identifier') == 'TOURVISOR_JWT'
                    and result.get('search_calls') == 0, 'invalid_detail_source')
            rows = result.get('rows', [])
            require(len(rows) == result.get('detail_rows'), 'detail_rows_truncated')
            require(len({r['expected_anex_hotel_id'] for r in rows}) == len(rows), 'duplicate_detail_identity')
            output['rows'] = rows
        elif kind == 'selection':
            require(result.get('status') == 'blocked' and result.get('reason') == 'no_unique_tours_selected'
                    and result.get('credential_identifier') == 'TOURVISOR_JWT'
                    and result.get('search_id') == 13615248686, 'selection_receipt_mismatch')
        else:
            country = 1 if kind == 'egypt' else 4
            require(result.get('status') == 'completed' and result.get('credential_identifier') == 'TOURVISOR_JWT', 'source_not_completed_old_tv')
            require(result.get('country', {}).get('id') == country and result.get('operator', {}).get('id') == 13, 'source_country_operator_mismatch')
            requested = REQUESTED[kind]
            require(reservation.get('hotel_ids_sha256') == digest(requested.encode()), 'requested_targets_mismatch')
            require(result.get('date') == reservation.get('date'), 'date_binding_mismatch')
            allowed = {int(h) for h in requested.split(',')}
            rows = result.get('rows', [])
            require(len(rows) == result.get('anex_rows'), 'source_rows_truncated')
            for row in rows:
                hid, refs = row.get('tourvisor_hotel_id'), row.get('refs', {})
                require(hid in allowed and str(refs.get('hotel.id')) == str(hid), 'out_of_target_offer')
                require(str(refs.get('hotel.country.id')) == str(country) and str(refs.get('operator.id')) == '13', 'offer_country_operator_mismatch')
                require(bool(re.fullmatch(r'[1-9][0-9]{0,14}', str(refs.get('id', '')))), 'invalid_tour_id')
            output.update(rows=rows, country_id=country, date=result['date'])
        return output


def nonphysical(name: str) -> bool:
    # Do not blacklist named physical Fortuna hotels; only explicit roulette products.
    return bool(re.match(r'^(?:тур\s+[\"«]|roulette\s+[1-5]\s*\*|fortuna\s+[1-5]\s*\*?\s+(?:ai|bb|hb|fb|ro)\b)', name.strip(), re.I))


# A request-quality veto, NOT a name matcher or permission to accept an identity.
# Qualifiers remain intact in the dossier; by themselves they cannot identify a brand.
GENERIC = set('hotel hotels resort resorts spa the and by of in at for only otel hotell отель отели курорт спа'.split())
QUALIFIERS = set('beach garden north south east west club palace royal grand premium select family adults adult pool sea luxury deluxe suites suite island inn boutique'.split())
GEO_TOKENS = set('turkey turkiye egypt istanbul bodrum marmaris antalya alanya belek kemer side sultanahmet fatih laleli hurghada sharm el sheikh makadi bay quseir marsa alam nabq gumbet arnavutkoy египет турция стамбул бодрум мармарис анталья аланья белек кемер сиде хургадa шарм эль шейх'.split())
# Coarse compatibility buckets avoid mistaking Belek/Antalya parent geography for conflict.
GEO_GROUPS = {
    'istanbul': {'istanbul', 'стамбул'}, 'bursa': {'bursa', 'бурса'},
    'bodrum': {'bodrum', 'бодрум'}, 'marmaris': {'marmaris', 'мармарис'},
    'antalya_coast': {'antalya', 'анталья', 'анталия', 'belek', 'белек', 'side', 'сиде', 'kemer', 'кемер', 'alanya', 'аланья', 'manavgat', 'манавгат'},
    'hurghada': {'hurghada', 'хургада'}, 'sharm': {'sharm', 'шарм'},
    'marsa_alam': {'marsa', 'марса'},
}
CONTEXT_SHA256 = 'd8bf81539ac72dcb7c7db6bf7364fe7e0e6321875c48e3881459bfb8f097e2b3'


def name_tokens(text: str) -> set[str]:
    text = unicodedata.normalize('NFKD', text).casefold()
    text = ''.join(c for c in text if not unicodedata.combining(c))
    text = re.sub(r"(?<=\w)['’]s\b", 's', text)
    return {'adult' if t == 'adults' else t for t in re.findall(r'[^\W\d_]+', text, re.U)}


def request_quality(row: dict[str, Any], context: list[dict[str, Any]]) -> dict[str, Any]:
    source = name_tokens(row.get('source_name') or '')
    target = name_tokens(row.get('candidate_name') or '')
    # Remove only saved place tokens and their known equivalents from NAME anchors.
    place = name_tokens(' '.join(str(row.get(k) or '') for k in ('candidate_region', 'candidate_subregion', 'candidate_country')))
    stop = GENERIC | QUALIFIERS | GEO_TOKENS | place
    left, right = source - stop, target - stop
    anchors = sorted(t for t in left & right if len(t) >= 3)
    # Exact word segmentation (Darkhill / Dark Hill) is useful for discovery, not acceptance.
    joined = bool(left and right and ''.join(sorted(left)) == ''.join(sorted(right)) and len(''.join(sorted(left))) >= 4)
    exact = source - GENERIC == target - GENERIC and bool(left or len(source - GENERIC - GEO_TOKENS) >= 2)
    # Preserve a strong saved fuzzy winner for discovery only (e.g. SWISSOTEL/SWISSTEL).
    typo = (float(row.get('name_score') or 0) >= .75 and float(row.get('margin') or 0) >= .15
            and any(min(len(a), len(b)) >= 7 and difflib.SequenceMatcher(None, a, b, autojunk=False).ratio() >= .88
                    for a in left for b in right))
    reasons = [] if anchors or joined or exact or typo else ['candidate_name_anchor_missing']
    candidate_geo = {g for g, terms in GEO_GROUPS.items() if terms & place}
    observations = []
    for group in context:
        evidence_geo = {g for g, terms in GEO_GROUPS.items() if terms & name_tokens(group['geography'])}
        if evidence_geo and candidate_geo and not (evidence_geo & candidate_geo):
            reasons.append('candidate_disagrees_with_saved_geography')
            observations.append(group)
    return {'name_anchors': anchors, 'exact_word_segmentation': joined, 'strong_saved_typo_candidate': typo, 'reasons': sorted(set(reasons)),
            'saved_context_disagreements': observations, 'is_acceptance_evidence': False}


def load_context(path: Path) -> dict[str, Any]:
    raw = path.read_bytes()
    require(digest(raw) == CONTEXT_SHA256, 'context_hash_mismatch')
    doc = json.loads(raw)
    require(doc.get('guards', {}).get('not_write_authority') is True, 'context_authority_mismatch')
    return doc


def detail_route(row: dict[str, Any], observation: dict[str, Any]) -> str:
    if row['country_id'] != 1 or observation.get('expected_tourvisor_hotel_id') != row['candidate_local_id']:
        return 'captured_evidence_hold'
    aid = int(row['external_hotel_id'])
    if observation.get('operator_identity', {}).get('anex_hotel_id') != aid:
        return 'captured_identity_contradiction'
    if (observation.get('tier') == 'DIRECT' and observation.get('reason') == 'direct_identity_confirmed'
            and observation.get('detail_tourvisor_hotel_id') == row['candidate_local_id']
            and observation.get('source_name') == row['source_name']
            and observation.get('semantic', {}).get('state') == 'corroborated'
            and observation.get('qualifier_conflict') is False and observation.get('numeric_conflict') is False
            and row.get('qualifier_conflict') is False and row.get('numeric_conflict') is False
            and (observation.get('distance_km') is None or observation['distance_km'] <= 5)):
        return 'captured_direct_identity_pending_current_acceptance'
    return 'captured_evidence_hold'


def build_packet(queue: dict[str, Any], photo: dict[str, Any], offers: list[dict[str, Any]], selection: dict[str, Any] | None = None, *, latest: dict[str, Any] | None = None, detail: dict[str, Any] | None = None, context: dict[str, Any] | None = None) -> dict[str, Any]:
    originals = {identity(r): r for r in queue['rows']}
    enriched = {identity(r): r for r in photo['rows']}
    require(len(originals) == len(queue['rows']) and len(enriched) == len(photo['rows']), 'duplicate_source_identity')
    require(originals.keys() == enriched.keys(), 'enriched_identity_set_mismatch')
    require(photo['result'].get('manual_source_sha256') == queue['queue_sha256'], 'enrichment_source_mismatch')
    for key, row in originals.items():
        require(all(enriched[key].get(k) == v for k, v in row.items()), 'enrichment_changed_original_dossier')
    old_originals = originals
    if latest is not None:
        originals = {identity(r): r for r in latest['rows']}
        require(len(originals) == len(latest['rows']), 'duplicate_current_identity')
        require(latest['result']['generated_at_utc'] > queue['result']['generated_at_utc'], 'latest_snapshot_not_newer')
    contexts: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for group in (context or {}).get('public_groups', []):
        for aid in group['anex_ids']:
            contexts[f'anex:{aid}'].append({k: group[k] for k in ('kind', 'geography', 'url')})
    details = {f"anex:{r['expected_anex_hotel_id']}": r for r in (detail or {}).get('rows', [])}
    saved: dict[tuple[int, int], list[dict[str, Any]]] = defaultdict(list)
    for bundle in offers:
        for row in bundle['rows']:
            saved[(bundle['country_id'], row['tourvisor_hotel_id'])].append({
                'tour_id': str(row['refs']['id']), 'date': bundle['date'],
                'artifact_id': bundle['artifact_id'], 'result_sha256': bundle['result_sha256']})
    targets, excluded = [], []
    for key, row in sorted(originals.items(), key=lambda item: (-int(item[1]['search_count']), item[0])):
        require(type(row.get('search_count')) is int and row['search_count'] > 0, 'invalid_live_frequency')
        provider, ext = row['provider'], str(row['external_hotel_id'])
        for bridge in row.get('existing_provider_bridges', []):
            bp, ids = bridge.split(':', 1)
            require(not (bp == provider and ext in ids.split(',')), 'source_already_mapped_in_snapshot')
        country, local = row.get('country_id'), row.get('candidate_local_id')
        if country not in CORE8 or nonphysical(row['source_name']):
            excluded.append({'key': key, 'reason': 'outside_core8' if country not in CORE8 else ('nonphysical_excursion_product' if re.match(r'^тур\s', row['source_name'], re.I) else 'nonphysical_roulette_product')})
            continue
        flags = [row['auto_block_reason']]
        if row.get('qualifier_conflict'): flags.append('meaningful_qualifier_conflict')
        if row.get('numeric_conflict'): flags.append('numeric_conflict')
        distance = row.get('distance_km')
        if distance is not None and float(distance) > 5: flags.append('coordinate_conflict_gt5km')
        if row.get('pair_excluded'): flags.append('pair_excluded')
        blocked = row.get('pair_excluded') or 'coordinate_conflict_gt5km' in flags
        native = (enriched.get(key, {}).get('native_anex_hotelcode_confirmed') is True
                  and old_originals.get(key, {}).get('country_id') == country
                  and old_originals.get(key, {}).get('source_name') == row['source_name'])
        record = {'key': key, 'country_id': country, 'external_hotel_id': ext, 'candidate_local_id': local,
                  'search_count': row['search_count'], 'source_name': row['source_name'],
                  'dossier_sha256': digest(canonical(row)), 'acceptance_holds': sorted(set(flags)),
                  'native_anex_hotelcode': native, 'existing_provider_bridges': row.get('existing_provider_bridges', [])}
        quality = request_quality(row, contexts[key])
        record['candidate_quality'] = quality
        if key in details:
            record['captured_detail'] = {'artifact_id': detail['artifact_id'], 'result_sha256': detail['result_sha256'], 'row': details[key]}
        if blocked:
            record['route'] = 'protected_hold'
        elif not isinstance(local, int) or isinstance(local, bool) or local < 1:
            record['route'] = 'needs_local_candidate'
        elif provider == 'andromeda':
            record['route'] = 'missing_andromeda_identity'
            record['supplier_namespace'] = 'andromeda_catalog'
        elif key in details:
            record['route'] = detail_route(row, details[key])
        elif quality['reasons']:
            record['route'] = 'needs_candidate_evidence_before_search'
        elif (country, local) in saved:
            record['route'] = 'saved_targeted_tour_detail'
            record['saved_tours'] = saved[(country, local)]
        else:
            record['route'] = 'hotel_filtered_anex_discovery'
        targets.append(record)
    batches = []
    grouped: dict[int, dict[int, list[str]]] = defaultdict(dict)
    for row in targets:
        if row['route'] == 'hotel_filtered_anex_discovery':
            grouped[row['country_id']].setdefault(row['candidate_local_id'], []).append(row['key'])
    for country in sorted(grouped):
        items = list(grouped[country].items())
        for start in range(0, len(items), 30):
            chunk = items[start:start+30]
            batches.append({'country_id': country, 'hotelIds': [h for h, _ in chunk],
                            'target_keys': [key for _, keys in chunk for key in keys]})
    require(all(b['hotelIds'] and len(b['hotelIds']) <= 30 for b in batches), 'empty_or_oversized_batch')
    route_counts = dict(sorted(Counter(r['route'] for r in targets).items()))
    sources = [{k: v for k, v in s.items() if k in {'artifact_id', 'archive_sha256', 'result_sha256', 'operation_id', 'source_sha', 'queue_sha256'}}
               for s in [queue, photo, *offers, *([selection] if selection else []), *([latest] if latest else []), *([detail] if detail else [])]]
    return {'schema': 'hotel-match-unresolved-target-plan/1', 'status': 'offline_prepared_not_authorized_to_execute',
            'snapshot_at_utc': (latest or queue)['result']['generated_at_utc'],
            'superseded_snapshot_at_utc': queue['result']['generated_at_utc'] if latest else None,
            'retired_source_keys': sorted(old_originals.keys() - originals.keys()),
            'new_source_keys': sorted(originals.keys() - old_originals.keys()),
            'context_sha256': CONTEXT_SHA256 if context else None, 'current_db_verified': False,
            'acceptance_authorized': False, 'supplier_calls': 0, 'database_reads': 0, 'mapping_writes': 0,
            'sources': sources, 'blocked_saved_search': None if selection is None else {
                'search_id': selection['result']['search_id'], 'operation_id': selection['operation_id'],
                'result_sha256': selection['result_sha256'], 'reason': 'no_unique_tours_selected',
                'is_evidence_of_no_tours': False, 'readback_execution_permitted': False},
            'input_identities': len(originals), 'physical_identity_targets': len(targets),
            'route_counts': route_counts, 'excluded': excluded, 'targets': targets, 'anex_discovery_batches': batches,
            'execution_requirements': [
                'CURRENT missing source link, manual decisions, conflicts and pair exclusions must be checked before each provider request.',
                'Candidate IDs are proposals, never identity evidence; preserve each external ID when deduplicating request targets.',
                'Andromeda groups address only missing Andromeda external IDs; do not re-query existing ANEX/Tourvisor bridges.',
                'Saved tour IDs are historical; no current availability or permitted executor is implied.',
                'No broad fallback, no source operation replay and no retry of the platform-blocked saved-search-v5 action.',
                'A future acceptance requires independent country/name/geo proof, current transaction and post-COMMIT per-row readback.']}


def compact_packet(packet: dict[str, Any]) -> dict[str, Any]:
    """A reviewable index into the hash-pinned full dossiers, not a second resolver."""
    result = {k: v for k, v in packet.items() if k != 'targets'}
    result['full_packet_sha256'] = digest(canonical(packet))
    result['target_columns'] = ['key', 'country_id', 'candidate_local_id', 'search_count']
    routes: dict[str, list[list[Any]]] = defaultdict(list)
    for row in packet['targets']:
        routes[row['route']].append([row[k] for k in result['target_columns']])
    result['targets_by_route'] = dict(sorted(routes.items()))
    result['saved_tour_bindings'] = {r['key']: r['saved_tours'] for r in packet['targets'] if 'saved_tours' in r}
    result['native_anex_hotelcode_keys'] = [r['key'] for r in packet['targets'] if r['native_anex_hotelcode']]
    result['candidate_quality_holds'] = {r['key']: r['candidate_quality'] for r in packet['targets'] if r['candidate_quality']['reasons']}
    result['captured_details'] = {r['key']: r['captured_detail'] for r in packet['targets'] if 'captured_detail' in r}
    result['dossier_policy'] = 'All original names, aliases, coordinates, qualifier/numeric conflicts and exclusions remain in the pinned queue; this index never overrides them.'
    return result


def write_new(path: Path, packet: dict[str, Any]) -> str:
    raw = canonical(packet)
    with path.open('xb') as output:
        output.write(raw)
        output.flush()
        os.fsync(output.fileno())
    require(path.read_bytes() == raw, 'output_readback_failed')
    return digest(raw)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    for kind in PINS:
        parser.add_argument('--' + kind, required=kind not in {'latest', 'detail'}, type=Path)
    parser.add_argument('--context', type=Path, help='Pinned saved public geography supplement, not live geography authority')
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--compact', action='store_true', help='Write the review index instead of full dossiers')
    args = parser.parse_args()
    bundles = {kind: load_archive(getattr(args, kind), kind) for kind in PINS if getattr(args, kind)}
    packet = build_packet(bundles['queue'], bundles['photo'], [bundles['egypt'], bundles['turkey']], bundles['selection'],
                          latest=bundles.get('latest'), detail=bundles.get('detail'),
                          context=load_context(args.context) if args.context else None)
    result_hash = write_new(args.output, compact_packet(packet) if args.compact else packet)
    print(json.dumps({'sha256': result_hash, 'targets': len(packet['targets']), 'routes': packet['route_counts'],
                      'supplier_calls': 0, 'mapping_writes': 0}, sort_keys=True))


if __name__ == '__main__':
    main()
