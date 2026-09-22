#!/usr/bin/env python3
"""Corroborate BG identities with already accepted independent COMMON4 links.

Offline evidence only. ANEX/FUN&SUN/Intourist may corroborate an exact local
target and catalog snapshot; BG/SAMO operator_115 is deliberately excluded.
No fuzzy title matching, provider access, database access or mapping write.
"""
from __future__ import annotations
import argparse
import hashlib
import importlib.util
import json
from pathlib import Path
import zipfile

try:
    import match_bg_saved_aliases as base
    import match_bg_saved_catalog_recovery as recovery
except ModuleNotFoundError:
    def load(name: str):
        spec = importlib.util.spec_from_file_location(name, Path(__file__).with_name(name + '.py'))
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        return module
    base = load('match_bg_saved_aliases')
    recovery = load('match_bg_saved_catalog_recovery')

INDEPENDENT_NAMESPACES = {'operator_5', 'operator_315', 'operator_342'}
RECOVERY_JOIN_OUTPUT_SHA = '64b2b77e2e57daf142e266836dbbe94fb93149fa6e473baa29cc6dae762b722d'

def sha(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()

def encoded(value: object) -> bytes:
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode()

def title_forms(value: object, geography: list[tuple[str, object]]) -> dict[str, list[dict]]:
    current = base.names(value)['current']
    forms: dict[str, list[dict]] = {current: []} if current else {}
    for source, raw in geography:
        geo = base.name(raw)
        if not geo or current == geo:
            continue
        if current.startswith(geo + ' '):
            forms[current[len(geo) + 1:].strip()] = [{'source': source, 'value': raw, 'position': 'prefix'}]
        if current.endswith(' ' + geo):
            forms[current[:-(len(geo) + 1)].strip()] = [{'source': source, 'value': raw, 'position': 'suffix'}]
    return {k: v for k, v in forms.items() if k}

def title_proofs(local: object, official: object, geography: list[tuple[str, object]]) -> list[dict]:
    left, right = title_forms(local, geography), title_forms(official, geography)
    out = []
    for common in sorted(set(left) & set(right)):
        out.append({'rule': 'exact_after_hotel_and_optional_saved_geography_affix',
                    'normalized_title': common,
                    'local_removed_affix': left[common],
                    'official_removed_affix': right[common]})
    return out

def independent_corroborators(inp: dict, tv: int, anchor: dict | None) -> list[dict]:
    out = []
    for candidate in inp.get('canonical_evidence', []):
        records = candidate.get('source_records')
        valid_detail = (isinstance(records, list) and len(records) == 1
                        and records[0] == {'state': 'detail_identity_verified'})
        if (anchor is not None
            and candidate.get('supplier_namespace') in INDEPENDENT_NAMESPACES
            and candidate.get('local_hotel_id') == tv
            and candidate.get('decision_status') == 'accepted'
            and candidate.get('recorded_evidence_hash_matches') is True
            and candidate.get('evidence_sha256') == candidate.get('evidence_json_sha256')
            and candidate.get('catalog_sha256') == anchor.get('catalog_sha256')
            and valid_detail):
            out.append(candidate)
    return out

def run(bg_archive: Path, bg_digest: str, recovery_archive: Path, recovery_digest: str) -> dict:
    evidence = base.run(bg_archive, bg_digest)
    recovered = recovery.run(bg_archive, bg_digest, recovery_archive, recovery_digest)
    recovery_raw = encoded(recovered)
    if RECOVERY_JOIN_OUTPUT_SHA and sha(recovery_raw) != RECOVERY_JOIN_OUTPUT_SHA:
        raise ValueError('unexpected_recovery_join_output')
    recovered_ids = {r['tv_hotel_id'] for r in recovered['rows'] if r['recovery_supported']}
    with zipfile.ZipFile(bg_archive) as z:
        source = json.loads(z.read('result.json'))
    rows = []
    for row in evidence['rows']:
        if (row['alias_evidence_supported']
            or row['evidence_holds'] != ['canonical_category_or_country_not_confirmed']
            or row['tv_hotel_id'] in recovered_ids):
            continue
        inp, holds = row['input_row'], []
        tv = int(row['tv_hotel_id'])
        f4 = inp.get('f4_candidates', [])
        selected_ids = inp.get('accepted_catalog_ids', [])
        selected = str(selected_ids[0]) if len(selected_ids) == 1 else ''
        canonical = [a for a in inp.get('canonical_evidence', [])
                     if a.get('supplier_namespace') == 'andromeda_catalog'
                     and str(a.get('external_hotel_id')) == selected]
        if len(canonical) != 1:
            holds.append('selected_canonical_anchor_not_unique')
            anchor = None
        else:
            anchor = canonical[0]
            if not (anchor.get('local_hotel_id') == tv
                    and anchor.get('decision_status') == 'accepted'
                    and anchor.get('recorded_evidence_hash_matches') is True
                    and anchor.get('evidence_sha256') == anchor.get('evidence_json_sha256')):
                holds.append('selected_canonical_anchor_invalid')
        corroborators = independent_corroborators(inp, tv, anchor)
        if not corroborators:
            holds.append('independent_common4_detail_anchor_missing')
        official = inp.get('official_evidence', [])
        local = inp['catalog_hotel']
        proof, geo = [], None
        if len(official) != 1 or len(f4) != 1:
            holds.append('official_or_f4_not_unique')
        else:
            bg = official[0]
            hotel, city, country = bg['hotel'], bg['official_city'], bg['official_country']
            geo = base.geography_proof(inp, bg, source['rule_table'])
            geography = [('official_city.title_en', city.get('title_en')),
                         ('official_city.title_ru', city.get('title_ru')),
                         ('local.region_name', local.get('region_name')),
                         ('local.subregion_name', local.get('subregion_name'))]
            proof = title_proofs(local.get('name'), hotel.get('name'), geography)
            checked = (bg.get('native_namespace') == 'bgoperator'
                       and str(bg.get('native_id')) == str(f4[0])
                       and str(hotel.get('key')) == str(f4[0])
                       and str(hotel.get('countryKey')) == str(country.get('id'))
                       and str(hotel.get('cityKey')) == str(city.get('id'))
                       and str(city.get('country')) == str(country.get('id'))
                       and bg.get('category_exact') is True
                       and base.stars(hotel.get('stars')) == base.stars(local.get('category'))
                       and bg.get('city_country_consistent') is True
                       and base.country_matches(local.get('country_name'), country)
                       and geo is not None and bool(proof))
            if not checked:
                holds.append('official_target_proof_failed')
        supported = not holds
        rows.append({
            'tv_hotel_id': tv,
            'bgoperator_raw_f4': str(f4[0]) if len(f4) == 1 else None,
            'accepted_andromeda_catalog_id': selected,
            'corroboration_supported': supported,
            'holds': sorted(set(holds)),
            'title_proofs': proof,
            'geography_proof': geo,
            'independent_common4_anchors': [{k: a[k] for k in ('supplier_namespace','external_hotel_id','catalog_sha256','evidence_sha256')}
                                             for a in corroborators],
            'safe_to_write_now': False,
        })
    rows.sort(key=lambda r: r['tv_hotel_id'])
    supported = [r for r in rows if r['corroboration_supported']]
    base_union = {r['tv_hotel_id'] for r in evidence['rows']
                  if r['baseline_compound_evidence'] or r['new_alias_evidence_candidate']}
    union_after = base_union | recovered_ids | {r['tv_hotel_id'] for r in supported}
    return {
        'schema': 'match-bg-saved-common4-corroboration/1',
        'state': 'completed_offline_saved_corroboration_not_accepted',
        'inputs': {
            'bg_zip_sha256': bg_digest.removeprefix('sha256:'),
            'catalog_recovery_zip_sha256': recovery_digest.removeprefix('sha256:'),
            'catalog_recovery_join_sha256': sha(recovery_raw),
        },
        'counts': {
            'input_unresolved_after_recovery': len(rows),
            'corroboration_supported': len(supported),
            'remaining_unresolved': len(rows) - len(supported),
            'evidence_supported_after': recovered['counts']['evidence_supported_after'] + len(supported),
            'candidate_union_after': len(union_after),
            'unique_uncommitted_after_accept297': (evidence['counts']['alias_evidence_supported'] - 297)
                                                    + recovered['counts']['recovery_supported'] + len(supported),
        },
        'rows': rows,
        'provider_calls': 0, 'supplier_http_requests': 0, 'database_reads': 0,
        'mapping_writes': 0, 'accepted_links_added': 0,
        'safe_to_write_now': False, 'visibility_verified': False,
    }

def main() -> None:
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('bg_archive', type=Path)
    p.add_argument('--bg-sha256', default=recovery.BG_ZIP_SHA)
    p.add_argument('--catalog-recovery', required=True, type=Path)
    p.add_argument('--catalog-recovery-sha256', default=recovery.RECOVERY_ZIP_SHA)
    p.add_argument('--out', required=True, type=Path)
    args = p.parse_args()
    result = run(args.bg_archive, args.bg_sha256, args.catalog_recovery, args.catalog_recovery_sha256)
    raw = encoded(result)
    with args.out.open('xb') as f:
        f.write(raw)
    print(json.dumps({'counts': result['counts'], 'result_sha256': sha(raw)}, ensure_ascii=False))

if __name__ == '__main__':
    main()
