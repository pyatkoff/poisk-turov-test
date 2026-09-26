"""Offline historical contradiction screen. It cannot approve a mapping or access a server.

The original three-candidate mode is retained. --samo additionally screens every
candidate in the exact sealed v65 cohort, including review/HOLD and empty dossiers.
Historical absence is unknown, never evidence of current safety.
"""
import argparse
import hashlib
import json
import re
import zipfile
from collections import Counter, defaultdict
from pathlib import Path

REPORT_SHA = '01ef024f859be9464a12ffcdd196d8b12450d2980d4184c2a056548e578d738d'
PINS = {
    'direct27': ('c55fe548c7d223462f5c251137559108765eb4b877ff47a26371e90aac5aaf5f', 'result.json', '7f5855aac069868f7b5e06264de8454cac2d571f65fd89e86e3d695d57695e8a', 'receipt.json', 'cdb0325ea1a3299f06fc54174c09b686e5073df1437e8491c2621ab10a4c70d9'),
    'canonical': ('35e79211da22b9d8f9a417d6ee4c27954d03e83ef89e471b3762fef8969e7aea', 'server/result.json', 'ce3876480bd8dd1899192c0ff1c432f5925c802492aff61d5caada1483090d94', 'server/receipt.json', 'a353decdc04e120391cc0a33abe781ea963a977c62446bac85f5890052c1e269'),
    'anex': ('b847b82f3a6ce6feb4110d42d12396631179dd0e5e4ce7bf9d48da55f0df651b', 'server/result.json', 'd84dccf6d242088683f1b81f6bcc43b933fe2d85c1dda047578514fe9c1e3501', 'server/receipt.json', 'a0f9f7e83603e06b2dccbf40128ae6622cd829eadf41656f175fef74c3551aa1'),
    'samo': ('033d372e5aeb4d495b9fe542c148c2d4eb080889b5eb5ca08a5642837e2dbaf8', 'result.json', '42829f8f7a7988f3f033bfd8e377758b537ccb95c3ace9a09d953191bfe17810', 'receipt.json', '46792a26c166c597602814afc217e08e07709d2ed3aa6941ab291a418e28c626'),
}
HISTORY_KINDS = ('direct27', 'canonical', 'anex')
NS = ('operator_5', 'operator_115', 'operator_315', 'operator_342')


def need(value, reason):
    if not value:
        raise ValueError(reason)


def sh(raw):
    return hashlib.sha256(raw).hexdigest()


def digest(value):
    return sh(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode())


def identifier(value):
    need(type(value) in (int, str), 'id_type')
    text = str(value)
    need(re.fullmatch(r'[1-9][0-9]{0,21}', text) is not None, 'id_shape')
    return text


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        need(key not in result, 'duplicate_json_key')
        result[key] = value
    return result


def json_value(raw):
    return json.loads(raw, object_pairs_hook=unique_object)


def read_pinned(path, expected):
    path = Path(path)
    need(path.is_file() and not path.is_symlink(), 'input_file')
    need(path.stat().st_size <= 8 * 1024 * 1024, 'input_cap')
    raw = path.read_bytes()
    need(sh(raw) == expected, 'input_hash')
    return raw


def load_report(path):
    result = json_value(read_pinned(path, REPORT_SHA))
    need(result.get('input_count') == 175 and result.get('safe_to_write_now') is False, 'report_shape')
    return result


def load_zip(path, kind):
    pin = PINS[kind]
    read_pinned(path, pin[0])
    with zipfile.ZipFile(path) as archive:
        values = []
        for member, expected in ((pin[1], pin[2]), (pin[3], pin[4])):
            need(archive.namelist().count(member) == 1, 'member_count')
            need(archive.getinfo(member).file_size <= 8 * 1024 * 1024, 'member_cap')
            raw = archive.read(member)
            need(sh(raw) == expected, 'member_hash')
            values.append(json_value(raw))
    result, receipt = values
    need(receipt.get('result_sha256') == pin[2] and receipt.get('operation') == result.get('operation'), 'receipt')
    need(result.get('state', result.get('status')) == receipt.get('state', receipt.get('status')) == 'completed_read_only', 'source_state')
    need(result.get('database_writes') == result.get('mapping_writes') == 0, 'writes')
    return result


# Legacy three-example mode, retained for reproducing the earlier report.
def indexes(direct, canonical, anex):
    return ({(int(x['tv_hotel_id']), str(x['samo_hotel_id'])): x for x in direct.get('rows', [])},
            {(int(x['tv_hotel_id']), str(x['external_hotel_id'])): x for x in canonical.get('new_candidate_plans', [])},
            {int(x['local_hotel_id']): x for x in anex.get('holds', []) if x.get('reason') == 'coordinate_conflict_gt5km'})


def assess(candidate, idx):
    local = int(candidate['local_hotel_id']); catalog = str(candidate['andromeda_catalog_id'])
    direct, canonical, anex = idx; reasons = []; evidence = []
    row = direct.get((local, catalog))
    if row:
        evidence.append({'kind': 'historical_direct27_current', 'classification': row.get('classification'), 'current_samo_identity': row.get('current_samo_identity', [])})
        if row.get('classification') not in ('current_candidate_pending_or_unassigned', 'samo_identity_missing_current'):
            reasons.append('historical_current_' + str(row.get('classification')))
    row = canonical.get((local, catalog))
    if row:
        evidence.append({'kind': 'historical_canonical_current', 'reasons': row.get('reasons', []), 'source_star': (row.get('source') or {}).get('star'), 'target_category': (row.get('target') or {}).get('category')})
        reasons += ['historical_canonical_' + str(value) for value in row.get('reasons', [])]
    row = anex.get(local)
    if row and candidate.get('direct_anex_support_not_authority') == 'support_equal':
        evidence.append({'kind': 'historical_direct_anex_detail_hold', 'native_anex_hotel_id': str(row.get('native_anex_hotel_id')), 'reason': row.get('reason'), 'distance_m': row.get('distance_m')})
        reasons.append('historical_direct_anex_' + str(row.get('reason')))
    reasons = sorted(set(reasons))
    return {'local_hotel_id': local, 'hotel_name': candidate.get('hotel_name'), 'andromeda_catalog_id': catalog,
            'candidate_names': candidate.get('candidate_names', []), 'independent_tv_lane_count': candidate.get('independent_tv_lane_count'),
            'historical_status': 'hold' if reasons else 'no_historical_veto_found', 'historical_hold_reasons': reasons,
            'historical_evidence': evidence, 'next_gate': 'fresh_CURRENT_manual_occupancy_evidence_review', 'safe_to_write_now': False}


def analyse(report, direct, canonical, anex):
    source = report.get('candidates_with_proven_tv_lanes', [])
    need(len(source) == 3, 'candidate_count')
    idx = indexes(direct, canonical, anex)
    rows = sorted((assess(row, idx) for row in source), key=lambda row: row['local_hotel_id'])
    holds = sum(row['historical_status'] == 'hold' for row in rows)
    return {'schema': 'match_retained175_history_crosscheck_v1', 'mode': 'offline_historical_contradiction_screen_only',
            'source_report_sha256': REPORT_SHA, 'historical_input_zip_pins': {key: PINS[key][0] for key in HISTORY_KINDS},
            'proven_candidates_input': 3, 'historical_hold_count': holds, 'historical_no_veto_count': 3 - holds, 'rows': rows,
            'fresh_current_validation_performed': False, 'provider_http_calls': 0, 'database_reads': 0,
            'database_writes': 0, 'mapping_writes': 0, 'safe_to_write_now': False}


def mass_indexes(direct, canonical, anex):
    """Keep all conflicting historical rows; never overwrite duplicates in a dict."""
    sources = defaultdict(list); plans = defaultdict(list); details = defaultdict(list)

    def source_row(row, kind, path, original=None):
        if row.get('supplier_namespace') != 'andromeda_catalog':
            return
        catalog = identifier(row['external_hotel_id'])
        local = row.get('local_hotel_id')
        sources[catalog].append({'local_hotel_id': None if local is None else int(identifier(local)),
            'decision_status': str(row.get('decision_status', 'unknown')), 'kind': kind,
            'result_sha256': PINS[kind][2], 'row_path': path, 'row_sha256': digest(row if original is None else original)})

    for index, row in enumerate(direct.get('rows', [])):
        for field in ('current_samo_identity', 'accepted_samo_target_occupants'):
            for offset, item in enumerate(row.get(field, [])):
                source_row(item, 'direct27', f'rows/{index}/{field}/{offset}')
    for index, edge in enumerate(canonical.get('edges', [])):
        for offset, fact in enumerate(edge.get('facts', [])):
            if fact.get('current_status') == 'accepted' and fact.get('effective_current_local_id') is not None:
                source_row({'supplier_namespace': fact['supplier_namespace'], 'external_hotel_id': fact['external_hotel_id'],
                    'local_hotel_id': fact['effective_current_local_id'], 'decision_status': 'accepted'}, 'canonical', f'edges/{index}/facts/{offset}', original=fact)
    for index, row in enumerate(canonical.get('new_candidate_plans', [])):
        plans[int(identifier(row['tv_hotel_id'])), identifier(row['external_hotel_id'])].append((index, row))
    for index, row in enumerate(anex.get('holds', [])):
        # A hold for native A must not taint native B merely because the local ID agrees.
        key = int(identifier(row['local_hotel_id'])), identifier(row['native_anex_hotel_id'])
        details[key].append((index, row))
    return sources, plans, details


def mass_assess(dossier, candidate, idx, strict_targets):
    local = int(identifier(dossier['local_hotel_id'])); catalog = identifier(candidate['catalog_id'])
    sources, plans, details = idx; reasons = []; evidence = []
    for row in sources.get(catalog, []):
        if row['decision_status'] == 'accepted' and row['local_hotel_id'] != local:
            reasons.append('historical_source_accepted_other_target'); evidence.append(row)
        elif row['decision_status'] != 'accepted':
            reasons.append('historical_source_' + row['decision_status']); evidence.append(row)
    for index, row in plans.get((local, catalog), []):
        if row.get('reasons'):
            reasons.extend('historical_canonical_' + value for value in row['reasons'])
            evidence.append({'kind': 'canonical', 'result_sha256': PINS['canonical'][2], 'row_path': f'new_candidate_plans/{index}',
                'row_sha256': digest(row), 'reasons': row['reasons'], 'source_star': row.get('source', {}).get('star'),
                'target_category': row.get('target', {}).get('category')})
    direct = {identifier(value) for value in dossier['direct_anex_ids']}
    observed = {identifier(value) for value in candidate['lanes']['operator_5']['native_ids']}
    # Numeric equality is consulted ONLY to veto an auxiliary support signal; never to create proof.
    for native in sorted(direct & observed):
        for index, row in details.get((local, native), []):
            reasons.append('historical_direct_anex_' + row['reason'])
            evidence.append({'kind': 'anex', 'result_sha256': PINS['anex'][2], 'row_path': f'holds/{index}',
                'row_sha256': digest(row), 'native_anex_hotel_id': native, 'reason': row['reason'],
                'distance_m': row.get('distance_m'), 'distance_inputs_independently_verified': False})
    if len(strict_targets.get(catalog, set())) > 1:
        reasons.append('v65_selected_catalog_multiple_targets')
    reasons = sorted(set(reasons))
    evidence = list({digest(item): item for item in evidence}.values())
    evidence.sort(key=digest)
    return {'local_hotel_id': local, 'hotel_name': dossier['hotel_name'], 'andromeda_catalog_id': catalog,
        'candidate_names': candidate['names'], 'original_status': dossier['status'],
        'original_selected': dossier.get('strict_catalog_id') == catalog,
        'observed_supplier_lane_count_not_cross_source_proof': candidate['lane_count'],
        'historical_hold_reasons': reasons, 'historical_evidence': evidence,
        'dossier_sha256': digest(dossier), 'candidate_sha256': digest(candidate),
        'safe_to_write_now': False}


def analyse_mass(samo, direct, canonical, anex):
    need(samo.get('operation') == 'hotel-match-live234-andromeda-context-acquire-1971-20260926-v65', 'samo_operation')
    need(samo.get('state') == 'completed_read_only' and samo.get('mapping_writes') == samo.get('database_writes') == 0, 'samo_state')
    dossiers = samo['dossiers']; seen = set(); strict_targets = defaultdict(set); pairs = set()
    for dossier in dossiers:
        local = int(identifier(dossier['local_hotel_id']))
        need(local not in seen, 'duplicate_dossier'); seen.add(local)
        for candidate in dossier['candidates']:
            pair = local, identifier(candidate['catalog_id'])
            need(pair not in pairs, 'duplicate_candidate'); pairs.add(pair)
            need(set(candidate['lanes']) == set(NS), 'namespaces')
            for lane in candidate['lanes'].values():
                for native in lane['native_ids']:
                    identifier(native)
        if dossier['status'] == 'strict_multi_lane_candidate':
            strict_targets[identifier(dossier['strict_catalog_id'])].add(local)
    need(len(dossiers) == 175 and len(pairs) == 161, 'mass_scope')
    need(sum(map(len, strict_targets.values())) == 49, 'strict_scope')
    idx = mass_indexes(direct, canonical, anex); risk_rows = []; buckets = defaultdict(list)
    strict_risk = []; strict_unresolved = []; no_candidate = []
    for dossier in sorted(dossiers, key=lambda item: item['local_hotel_id']):
        local = dossier['local_hotel_id']; risky = False; selected_risky = False
        for candidate in dossier['candidates']:
            row = mass_assess(dossier, candidate, idx, strict_targets)
            if row['historical_hold_reasons']:
                risk_rows.append(row); risky = True
                selected_risky |= row['original_selected']
        if not dossier['candidates']:
            status = 'no_candidate_in_retained_response'; no_candidate.append(local)
        elif risky:
            status = 'historical_or_batch_risk_requires_review'
        else:
            status = 'no_risk_detected_in_limited_history_not_validated'
        buckets[status].append(local)
        if dossier['status'] == 'strict_multi_lane_candidate':
            (strict_risk if selected_risky else strict_unresolved).append(local)
    risk_rows.sort(key=lambda item: (item['local_hotel_id'], int(item['andromeda_catalog_id'])))
    reasons = Counter(reason for row in risk_rows for reason in row['historical_hold_reasons'])
    return {'schema': 'match_retained175_history_mass_v1', 'mode': 'offline_historical_risk_screen_not_acceptance',
        'input_hashes_zip_result_receipt': PINS, 'historical_snapshot_dates': ['2026-09-18', '2026-09-19', '2026-09-21'],
        'input_dossiers': 175, 'candidate_pairs_reviewed': 161, 'dossiers_with_candidates': 175 - len(no_candidate),
        'risk_candidate_pairs': len(risk_rows), 'risk_dossiers': len({row['local_hotel_id'] for row in risk_rows}),
        'risk_reason_counts': dict(sorted(reasons.items())), 'dossier_ids_by_disposition': dict(sorted(buckets.items())),
        'original_selected49_risk_ids': strict_risk, 'original_selected49_other_unvalidated_ids': strict_unresolved,
        'rows': risk_rows, 'historical_absence_means': 'unknown_not_safe', 'accepted_mapping_count': 0,
        'fresh_current_validation_performed': False, 'provider_http_calls': 0, 'database_reads': 0,
        'database_writes': 0, 'mapping_writes': 0, 'safe_to_write_now': False}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--report')
    parser.add_argument('--samo', help='Use all175 mode with the exact v65 ZIP; no live access')
    for name in ('direct27', 'canonical', 'anex', 'output'):
        parser.add_argument('--' + name, required=True)
    args = parser.parse_args()
    need(bool(args.report) != bool(args.samo), 'select_exactly_one_mode')
    history = [load_zip(getattr(args, key), key) for key in HISTORY_KINDS]
    result = analyse_mass(load_zip(args.samo, 'samo'), *history) if args.samo else analyse(load_report(args.report), *history)
    raw = (json.dumps(result, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode()
    # No overwrite of prior receipts, and no output symlink following.
    with Path(args.output).open('xb') as output:
        output.write(raw)
    print(json.dumps({'report_sha256': sh(raw), 'mapping_writes': 0, 'mode': result['mode']}, sort_keys=True))


if __name__ == '__main__':
    main()
