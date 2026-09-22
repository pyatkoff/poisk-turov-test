#!/usr/bin/env python3
"""Join terminal BG evidence with terminal CURRENT source-catalog recovery.

This is an offline evidence classifier. It never reads a database, calls a
provider, accepts a mapping or converts a BG F4 to another namespace.
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
except ModuleNotFoundError:
    spec = importlib.util.spec_from_file_location('match_bg_saved_aliases', Path(__file__).with_name('match_bg_saved_aliases.py'))
    base = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(base)

BG_ZIP_SHA = 'a52b4dbdf4a9eafdd49746a5a1aae461374f8d558e088096165ff82964e2b515'
BG_OUTPUT_SHA = 'a959d4078639169aaf7bd857140e74e6fe62ccc4dcf8ae8610d480f3e26ccf00'
RECOVERY_ZIP_SHA = 'ebf8bab41614367ece952b4604f85a2167c4d65c0d2060b961f318be12b50010'
RECOVERY_RESULT_SHA = '47d223b633fb7a05770f3efe08ccf1b206918ea665bf55f5079e5547b4d6f0fc'

def sha(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()

def encoded(value: object) -> bytes:
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode()

def recovery_rows(archive: Path, expected_digest: str) -> dict[tuple[int, str], dict]:
    raw = archive.read_bytes()
    if sha(raw) != expected_digest.removeprefix('sha256:'):
        raise ValueError('recovery_artifact_digest_mismatch')
    with zipfile.ZipFile(archive) as z:
        rb, qb = z.read('result.json'), z.read('receipt.json')
    result, receipt = json.loads(rb), json.loads(qb)
    if sha(rb) != receipt.get('result_sha256'):
        raise ValueError('recovery_result_receipt_digest_mismatch')
    if (result.get('state') != 'completed_source_catalog_recovery'
        or result.get('operation') != 'hotel-match-bg-source-catalog-recovery-1971-20260922-v4'
        or result.get('mapping_writes') != 0 or result.get('database_writes') != 0
        or result.get('provider_calls') != 0 or result.get('supplier_http_requests') != 0
        or result.get('counts') != {
            'membership_hotels': 816, 'accepted_andromeda_catalog_rows': 869,
            'rows_with_source_catalog_blocks': 41, 'source_catalog_blocks': 41,
            'evidence_hash_mismatches': 0,
        }):
        raise ValueError('unexpected_recovery_authority')
    out = {}
    for row in result['rows']:
        key = (int(row['local_hotel_id']), str(row['andromeda_catalog_id']))
        if key in out:
            raise ValueError('duplicate_recovery_anchor')
        out[key] = row
    if len(out) != 869:
        raise ValueError('unexpected_recovery_membership')
    return out

def run(bg_archive: Path, bg_digest: str, recovery_archive: Path, recovery_digest: str) -> dict:
    evidence = base.run(bg_archive, bg_digest)
    if sha(encoded(evidence)) != BG_OUTPUT_SHA:
        raise ValueError('unexpected_base_output')
    recovery = recovery_rows(recovery_archive, recovery_digest)
    residual = [r for r in evidence['rows']
                if r['alias_evidence_supported'] is False
                and r['evidence_holds'] == ['canonical_category_or_country_not_confirmed']]
    if len(residual) != 114:
        raise ValueError('unexpected_base_residual')
    source = json.loads(zipfile.ZipFile(bg_archive).read('result.json'))
    rows = []
    for row in residual:
        inp, holds = row['input_row'], []
        tv = int(row['tv_hotel_id'])
        selected = str(inp['accepted_catalog_ids'][0]) if len(inp.get('accepted_catalog_ids', [])) == 1 else ''
        anchors = [a for a in inp.get('canonical_evidence', [])
                   if a.get('supplier_namespace') == 'andromeda_catalog'
                   and str(a.get('external_hotel_id')) == selected]
        if len(anchors) != 1:
            holds.append('selected_anchor_not_unique')
            anchor = None
        else:
            anchor = anchors[0]
        saved = recovery.get((tv, selected))
        if saved is None:
            holds.append('saved_source_catalog_missing')
        projection = None
        if saved is not None and anchor is not None:
            same = (saved.get('recorded_evidence_hash_matches') is True
                    and saved.get('catalog_sha256') == anchor.get('catalog_sha256')
                    and saved.get('evidence_sha256') == anchor.get('evidence_sha256')
                    and saved.get('evidence_json_sha256') == anchor.get('evidence_sha256'))
            if not same:
                holds.append('saved_anchor_hash_mismatch')
            blocks = saved.get('source_catalog_blocks')
            if not isinstance(blocks, list):
                holds.append('saved_source_catalog_shape_invalid')
            elif len(blocks) == 0:
                holds.append('saved_source_catalog_missing')
            elif len(blocks) != 1 or blocks[0].get('path') != 'source_catalog':
                holds.append('saved_source_catalog_not_unique')
            elif not isinstance(blocks[0].get('projection'), dict):
                holds.append('saved_source_catalog_projection_invalid')
            else:
                projection = blocks[0]['projection']
        local = inp['catalog_hotel']
        if projection is not None:
            if not base.name(projection.get('name')) or not base.label(projection.get('town')):
                holds.append('saved_source_name_or_town_missing')
            if base.stars(projection.get('star')) != base.stars(local.get('category')):
                holds.append('saved_source_category_mismatch')
            stated = projection.get('country', projection.get('state'))
            if stated is not None and base.label(stated) != base.label(local.get('country_name')):
                holds.append('saved_source_country_mismatch')
        official = inp.get('official_evidence', [])
        if len(official) != 1:
            holds.append('official_evidence_not_unique')
            official_proofs, geo = [], None
        else:
            bg = official[0]
            geo = base.geography_proof(inp, bg, source['rule_table'])
            official_proofs = base.name_proofs(local['name'], bg['hotel']['name'], [projection] if projection else [])
            checked = (bg.get('native_namespace') == 'bgoperator'
                       and str(bg.get('native_id')) == str(inp['f4_candidates'][0])
                       and bg.get('category_exact') is True
                       and bg.get('city_country_consistent') is True
                       and base.country_matches(local.get('country_name'), bg['official_country'])
                       and geo is not None and bool(official_proofs))
            if not checked:
                holds.append('official_target_proof_failed')
        supported = not holds
        rows.append({
            'tv_hotel_id': tv,
            'bgoperator_raw_f4': str(inp['f4_candidates'][0]),
            'accepted_andromeda_catalog_id': selected,
            'recovery_supported': supported,
            'holds': sorted(set(holds)),
            'official_name_proofs': official_proofs,
            'geography_proof': geo,
            'saved_source_catalog': projection,
            'saved_anchor': ({k: saved[k] for k in ('local_hotel_id','andromeda_catalog_id','catalog_sha256','evidence_sha256')}
                             if saved is not None else None),
            'safe_to_write_now': False,
        })
    rows.sort(key=lambda r: r['tv_hotel_id'])
    recovered = [r for r in rows if r['recovery_supported']]
    base_union = {r['tv_hotel_id'] for r in evidence['rows']
                  if r['baseline_compound_evidence'] or r['new_alias_evidence_candidate']}
    union_after = base_union | {r['tv_hotel_id'] for r in recovered}
    return {
        'schema': 'match-bg-saved-catalog-recovery/2',
        'state': 'completed_offline_saved_recovery_not_accepted',
        'inputs': {
            'bg_zip_sha256': bg_digest.removeprefix('sha256:'),
            'bg_matcher_output_sha256': BG_OUTPUT_SHA,
            'catalog_recovery_zip_sha256': recovery_digest.removeprefix('sha256:'),
            'catalog_recovery_result_sha256': RECOVERY_RESULT_SHA,
        },
        'counts': {
            'input_residual': len(rows),
            'recovery_supported': len(recovered),
            'remaining_residual': len(rows) - len(recovered),
            'evidence_supported_after': evidence['counts']['alias_evidence_supported'] + len(recovered),
            'candidate_union_after': len(union_after),
        },
        'rows': rows,
        'provider_calls': 0, 'supplier_http_requests': 0, 'database_reads': 0,
        'mapping_writes': 0, 'accepted_links_added': 0,
        'safe_to_write_now': False, 'visibility_verified': False,
    }

def main() -> None:
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('bg_archive', type=Path)
    p.add_argument('--bg-sha256', default=BG_ZIP_SHA)
    p.add_argument('--catalog-recovery', required=True, type=Path)
    p.add_argument('--catalog-recovery-sha256', default=RECOVERY_ZIP_SHA)
    p.add_argument('--out', required=True, type=Path)
    args = p.parse_args()
    result = run(args.bg_archive, args.bg_sha256, args.catalog_recovery, args.catalog_recovery_sha256)
    raw = encoded(result)
    with args.out.open('xb') as f:
        f.write(raw)
    print(json.dumps({'counts': result['counts'], 'result_sha256': sha(raw)}, ensure_ascii=False))

if __name__ == '__main__':
    main()
