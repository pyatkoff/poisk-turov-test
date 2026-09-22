#!/usr/bin/env python3
"""Offline BG/local/canonical evidence join. Never authorizes a database write.

Input is a verified bg-retained-place-evidence/1 artifact ZIP. No network, DB,
provider IDs conversion, arbitrary alias lookup or fuzzy name matching exists.
Raw rows remain embedded in the output; annotations do not mutate source holds.
"""
from __future__ import annotations
import argparse
import collections
import hashlib
import json
from pathlib import Path
import re
import unicodedata
from urllib.parse import parse_qsl, urlsplit
import zipfile

EX = re.compile(r'\(\s*ex(?:\.|\s|:|-)+([^()]*)\)', re.I)
COMMON4 = {'operator_5', 'operator_315', 'operator_342', 'operator_115'}

def sha(b: bytes) -> str:
    return hashlib.sha256(b).hexdigest()

def label(value: object) -> str:
    s = unicodedata.normalize('NFKC', str(value or '')).casefold().replace('ё', 'е').replace('&', ' and ')
    return ' '.join(re.findall(r'[^\W_]+', s, flags=re.UNICODE))

def name(value: object) -> str:
    # HOTEL is the only ignored word. RESORT/APART/SUITE/PREMIUM/AQUA etc remain.
    return ' '.join(t for t in label(value).split() if t != 'hotel')

def names(value: object) -> dict:
    raw = str(value or '')
    matches = list(EX.finditer(raw))
    return {'raw': raw, 'current': name(EX.sub(' ', raw)),
            'declared_former': [name(m.group(1)) for m in matches]}

def stars(value: object) -> int | None:
    m = re.fullmatch(r'\s*([1-5])\s*\*?\s*', str(value or ''))
    return int(m.group(1)) if m else None

def name_proofs(local: str, official: str, records: list[dict]) -> list[dict]:
    l, b = names(local), names(official)
    out = []
    if l['current'] and l['current'] == b['current']:
        out.append({'rule': 'current_title_exact_hotel_only', 'local': l, 'official': b})
    for index, rec in enumerate(records):
        variants = [(field, names(rec.get(field))) for field in ('name', 'lName')]
        # Current BG title must match a current named field, NOT a common old alias.
        bg_fields = [field for field, n in variants if b['current'] and b['current'] == n['current']]
        local_fields = [field for field, n in variants if l['current'] and (l['current'] == n['current'] or l['current'] in n['declared_former'])]
        if bg_fields and local_fields:
            out.append({'rule': 'same_canonical_record_declares_both_titles', 'record_index': index,
                        'official_current_fields': bg_fields, 'local_title_fields': local_fields,
                        'canonical_record': rec, 'local': l, 'official': b})
    return out

def geography_proof(row: dict, bg: dict, rules: dict) -> dict | None:
    """Recheck the retained rule, not the superseded pre-reconciliation boolean."""
    local, city, country, hotel = row['catalog_hotel'], bg['official_city'], bg['official_country'], bg['hotel']
    if (bg.get('native_namespace') != 'bgoperator'
        or str(hotel.get('key')) != bg.get('native_id')
        or str(hotel.get('countryKey')) != str(country.get('id'))
        or str(hotel.get('cityKey')) != str(city.get('id'))
        or str(city.get('country')) != str(country.get('id'))
        or not label(country.get('title_ru'))
        or label(country.get('title_ru')) != label(local.get('country_name'))):
        return None
    for geo in row.get('retained_geography', []):
        if not (geo.get('native_namespace') == 'bgoperator' and geo.get('native_id') == bg.get('native_id')
                and geo.get('supported') is True and str(geo.get('official_city_id')) == str(city.get('id'))
                and str(geo.get('official_country_id')) == str(country.get('id'))):
            continue
        rule = geo.get('rule')
        if rule == 'exact_official_place_label':
            source = {label(city.get(k)) for k in ('title_ru', 'title_en')} - {''}
            target = {label(local.get(k)) for k in ('region_name', 'subregion_name')} - {''}
            if source & target:
                return {'rule': rule, 'retained': geo, 'matched_place_labels': sorted(source & target)}
        elif rule == 'explicit_compound_place_rule:' + str(city.get('id')):
            spec = rules.get(str(city.get('id')))
            if not isinstance(spec, list) or len(spec) != 4:
                continue
            code, official_name, region, subregions = spec
            if (country.get('code') == code and city.get('title_ru') == official_name
                and local.get('region_name') == region
                and (subregions is None or (isinstance(subregions, list) and local.get('subregion_name') in subregions))):
                return {'rule': rule, 'retained': geo, 'retained_rule_specification': spec}
    return None

def run(archive: Path, expected_digest: str) -> dict:
    raw_zip = archive.read_bytes()
    if sha(raw_zip) != expected_digest.removeprefix('sha256:'):
        raise ValueError('artifact_digest_mismatch')
    with zipfile.ZipFile(archive) as z:
        rb, qb = z.read('result.json'), z.read('receipt.json')
    data, receipt = json.loads(rb), json.loads(qb)
    if receipt.get('result_sha256') != sha(rb):
        raise ValueError('result_receipt_digest_mismatch')
    if data.get('schema') != 'bg-retained-place-evidence/1' or data.get('mapping_writes') != 0 or data.get('ids_promoted_to_operator115') is not False:
        raise ValueError('unexpected_evidence_authority')
    rows = data['rows']
    ids = [r['tv_hotel_id'] for r in rows]
    if len(ids) != len(set(ids)) or len(ids) != 816:
        raise ValueError('unexpected_membership')
    f4_owners, cat_owners = collections.defaultdict(set), collections.defaultdict(set)
    for row in rows:
        for native in row['f4_candidates']:
            f4_owners[str(native)].add(row['tv_hotel_id'])
        for cat in row['accepted_catalog_ids']:
            cat_owners[str(cat)].add(row['tv_hotel_id'])
    output = []
    for row in rows:
        holds, proof, geo_proofs = [], [], []
        tv, local = row['tv_hotel_id'], row['catalog_hotel']
        f4, cats = row['f4_candidates'], row['accepted_catalog_ids']
        if local.get('id') != tv or local.get('is_active') != 1:
            holds.append('local_identity_inactive_or_mismatched')
        if row.get('manual'):
            holds.append('saved_manual_hold')
        if len(f4) != 1:
            holds.append('multiple_native_ids_preserved')
        if len(cats) != 1:
            holds.append('missing_or_multiple_canonical_preserved')
        if any(len(f4_owners[str(v)]) != 1 for v in f4) or any(len(cat_owners[str(v)]) != 1 for v in cats):
            holds.append('competing_target_in_saved_membership')
        link = row.get('operator_link', '')
        url = urlsplit(link)
        query_f4 = [v for k, v in parse_qsl(url.query, keep_blank_values=True) if k.casefold() == 'f4']
        if sha(link.encode()) != row.get('operator_link_sha256') or url.hostname not in ('bgoperator.ru', 'www.bgoperator.ru') or (len(f4) == 1 and query_f4 != f4):
            holds.append('operator_link_provenance_not_exact')
        canonical = [c for c in row['canonical_evidence'] if c['supplier_namespace'] == 'andromeda_catalog']
        if len(canonical) != 1:
            holds.append('nonunique_canonical_evidence')
        good_records = []
        for c in canonical:
            if c.get('local_hotel_id') != tv or c.get('decision_status') != 'accepted' or c.get('external_hotel_id') not in cats or not c.get('recorded_evidence_hash_matches') or c.get('evidence_sha256') != c.get('evidence_json_sha256'):
                holds.append('canonical_evidence_identity_or_hash_failed')
                continue
            for rec in c.get('source_records', []):
                if str(rec.get('id', '')) != str(c['external_hotel_id']):
                    continue
                if label(rec.get('state')) == label(local.get('country_name')) and stars(rec.get('star')) is not None and stars(rec['star']) == stars(local.get('category')):
                    good_records.append(rec)
        if not good_records:
            holds.append('canonical_category_or_country_not_confirmed')
        if not holds:
            for bg in row['official_evidence']:
                if bg['native_id'] != f4[0] or bg.get('native_namespace') != 'bgoperator':
                    continue
                h, city, country = bg['hotel'], bg['official_city'], bg['official_country']
                geo = geography_proof(row, bg, data['rule_table'])
                checked = (bg.get('category_exact') and bg.get('country_matches_local') and bg.get('city_country_consistent')
                    and str(h.get('key')) == f4[0] and str(h.get('countryKey')) == str(country.get('id'))
                    and str(h.get('cityKey')) == str(city.get('id')) and str(city.get('country')) == str(country.get('id'))
                    and stars(h.get('stars')) == stars(local.get('category'))
                    and geo is not None)
                if checked:
                    geo_proofs.append(geo)
                    proof.extend(name_proofs(local['name'], h['name'], good_records))
            if not proof:
                holds.append('current_title_or_saved_geography_not_confirmed')
        supported = bool(proof) and not holds
        baseline = row.get('compound_evidence_candidate') is True
        output.append({'tv_hotel_id': tv, 'baseline_compound_evidence': baseline,
                       'new_alias_evidence_candidate': supported and not baseline,
                       'alias_evidence_supported': supported, 'evidence_holds': sorted(set(holds)),
                       'proofs': proof, 'geography_proofs': geo_proofs, 'safe_to_write_now': False,
                       'pending': ['prove_supplier_native_namespace_separately', 'fresh_manual_exclusion_conflict_occupancy_geography_native_owner', 'writer_authorization_reservation_capture_plan', 'post_commit_readback_and_effective_resolver'],
                       'input_row': row})
    new = [r for r in output if r['new_alias_evidence_candidate']]
    old = {r['tv_hotel_id'] for r in output if r['baseline_compound_evidence']}
    return {'schema': 'match-bg-saved-alias-evidence/1', 'input_zip_sha256': sha(raw_zip),
            'input_result_sha256': sha(rb), 'input_receipt': receipt,
            'state': 'completed_offline_evidence_join_not_accepted',
            'supplier_http_requests': 0, 'provider_calls': 0, 'database_reads': 0,
            'mapping_writes': 0, 'accepted_links_added': 0, 'visibility_verified': False,
            'safe_to_write_now': False, 'ids_promoted_to_operator115': False,
            'counts': {'hotels_examined': len(rows), 'official_rows_examined': sum(len(r['official_evidence']) for r in rows),
                       'baseline_compound_candidates': len(old), 'alias_evidence_supported': sum(r['alias_evidence_supported'] for r in output),
                       'new_evidence_candidates': len(new), 'candidate_union_not_accepted': len(old | {r['tv_hotel_id'] for r in new}),
                       'new_by_country': dict(collections.Counter(r['input_row']['catalog_hotel']['country_name'] for r in new)),
                       'f4_collisions_in_input': sum(len(v)>1 for v in f4_owners.values()),
                       'canonical_collisions_in_input': sum(len(v)>1 for v in cat_owners.values())},
            'new_candidate_ids': [r['tv_hotel_id'] for r in new], 'rows': output}

def main() -> None:
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('archive', type=Path)
    p.add_argument('--sha256', required=True)
    p.add_argument('--out', required=True, type=Path)
    args = p.parse_args()
    result = run(args.archive, args.sha256)
    encoded = (json.dumps(result, ensure_ascii=False, sort_keys=True, indent=2)+'\n').encode()
    with args.out.open('xb') as f:
        f.write(encoded)
    print(json.dumps({'counts': result['counts'], 'result_sha256': sha(encoded)}, ensure_ascii=False))
if __name__ == '__main__':
    main()
