"""Local-only review of pending MATCH identities and the full unmapped SAMO queue.

Consumes a hash-pinned dual-live30 report and its original immutable artifacts.
No network, database access, SQL writer, workflow or server operation is provided.
A pending-transition dossier is NOT an accepted mapping or writer-ready manifest.
"""
from __future__ import annotations

import argparse
from collections import Counter, defaultdict
from copy import deepcopy
from hashlib import sha256
import json
from pathlib import Path
import re
import unicodedata
from typing import Any
import zipfile

REPORT_SHA = '51e215bff5a8dfa54925a6a4d4a8f3607100a1411071a3314efb181d0fc979fa'
DIRECT_OPERATORS = {'operator_315': (25, 315), 'operator_342': (43, 342)}
AUTOMATIC_PENDING_REASONS = frozenset({'no_unique_name', 'no_unique_country_name'})
PENDING_HOLD_REASONS = frozenset({
    'source_catalog_occupied_or_protected',
    'historical:source_catalog_occupied_or_protected',
    'historical:retained_history:historical_source_pending',
})
MAX_INPUT_BYTES = 120 * 1024 * 1024


def need(condition: bool, reason: str) -> None:
    if not condition:
        raise ValueError(reason)


def canonical(value: Any) -> bytes:
    return json.dumps(value, ensure_ascii=False, sort_keys=True,
                      separators=(',', ':'), allow_nan=False).encode('utf-8')


def digest(value: Any) -> str:
    return sha256(canonical(value)).hexdigest()


def unique_object(pairs: list[tuple[str, Any]]) -> dict:
    out = {}
    for key, value in pairs:
        need(key not in out, 'duplicate_json_key')
        out[key] = value
    return out


def decode(raw: bytes) -> Any:
    return json.loads(raw, object_pairs_hook=unique_object,
                      parse_constant=lambda value: (_ for _ in ()).throw(ValueError('nonfinite_json')))


def identifier(value: Any) -> str:
    need(type(value) in (str, int), 'invalid_identity_type')
    result = str(value)
    need(re.fullmatch(r'[1-9][0-9]{0,21}', result) is not None, 'invalid_positive_identity')
    return result


def norm(value: Any) -> str:
    # Diagnostic comparison only. This does not turn similar names into proof.
    return ' '.join(unicodedata.normalize('NFKC', str(value or '')).casefold().replace('ё', 'е').split())


def checked_file(path: Path, maximum: int = MAX_INPUT_BYTES) -> bytes:
    need(path.is_file() and not path.is_symlink(), 'input_not_regular_file')
    need(path.stat().st_size <= maximum, 'input_too_large')
    return path.read_bytes()


def pointer(document: Any, path: str) -> Any:
    need(isinstance(path, str) and path.startswith('/'), 'invalid_json_pointer')
    node = document
    for encoded in path[1:].split('/'):
        token = encoded.replace('~1', '/').replace('~0', '~')
        if isinstance(node, list):
            need(re.fullmatch(r'0|[1-9][0-9]*', token) is not None, 'invalid_pointer_index')
            node = node[int(token)]
        else:
            node = node[token]
    return node


def load_inputs(report_path: Path, input_dir: Path) -> tuple[dict, dict]:
    raw = checked_file(report_path)
    need(sha256(raw).hexdigest() == REPORT_SHA, 'report_digest')
    report = decode(raw)
    documents = {}
    for pin in report['input_manifest']:
        name = pin['name']
        need(Path(name).name == name and name.endswith('.zip'), 'archive_name')
        archive = checked_file(input_dir / name)
        need(sha256(archive).hexdigest() == pin['zip_sha256'], 'archive_digest')
        with zipfile.ZipFile(input_dir / name) as z:
            members = {}
            for basename in ('result.json', 'receipt.json'):
                found = [i for i in z.infolist() if Path(i.filename).name == basename]
                need(len(found) == 1 and found[0].file_size <= MAX_INPUT_BYTES,
                     'ambiguous_or_large_archive_member')
                members[basename] = z.read(found[0])
        need(sha256(members['result.json']).hexdigest() == pin['result_sha256'], 'result_digest')
        need(sha256(members['receipt.json']).hexdigest() == pin['receipt_sha256'], 'receipt_digest')
        result, receipt = decode(members['result.json']), decode(members['receipt.json'])
        need(result['operation'] == receipt['operation'] == pin['operation'], 'operation_binding')
        need(receipt['result_sha256'] == pin['result_sha256'], 'receipt_binding')
        need(result.get('database_writes') == 0 and result.get('mapping_writes') == 0,
             'unexpected_write_input')
        need(pin['key'] not in documents, 'duplicate_archive_key')
        documents[pin['key']] = result
    return report, documents


def model(report: dict, documents: dict) -> dict:
    ctx = documents['context']['context']
    source, target, evidence = defaultdict(list), defaultdict(list), defaultdict(list)
    for row in ctx['identities']:
        source[row['supplier_namespace'], str(row['external_hotel_id'])].append(row)
        if row['local_hotel_id'] is not None:
            target[str(row['local_hotel_id']), row['supplier_namespace']].append(row)
    for row in ctx['selected_and_unaccepted_hotel_evidence']:
        ident = row['identity']
        evidence[ident['supplier_namespace'], str(ident['external_hotel_id'])].append(row)
    by_native, by_hotel = defaultdict(set), defaultdict(set)
    for side, edges in report['identity_edges'].items():
        need(side in ('tv', 'samo'), 'unknown_identity_side')
        for e in edges:
            need(e['side'] == side, 'identity_side_mismatch')
            by_native[side, e['namespace'], e['native_id']].add(e['hotel_id'])
            by_hotel[side, e['hotel_id'], e['namespace']].add(e['native_id'])
    pins = {row['key']: row for row in report['input_manifest']}
    return {'context': ctx, 'documents': documents, 'source': source, 'target': target,
            'evidence': evidence, 'by_native': by_native, 'by_hotel': by_hotel,
            'hotels': {str(r['tv_hotel_id']): r for r in ctx['rows']}, 'pins': pins}


def verify_proof(ref: dict, side: str, namespace: str, native: str,
                 hotel: str, m: dict) -> dict:
    need(namespace in DIRECT_OPERATORS, 'not_same_operator_proof')
    need(ref['side'] == side and ref['namespace'] == namespace and
         ref['native_id'] == native and ref['hotel_id'] == hotel, 'proof_identity_binding')
    key, path = ref['archive_key'], ref['json_pointer']
    pin = m['pins'][key]
    need(ref['artifact_id'] == pin['artifact_id'] and
         ref['result_sha256'] == pin['result_sha256'], 'proof_archive_binding')
    node = pointer(m['documents'][key], path)
    need(digest(node) == ref['row_sha256'], 'proof_row_digest')
    tv_operator, samo_operator = DIRECT_OPERATORS[namespace]
    if side == 'tv':
        need(key in ('supplement', 'first450', 'c0c30', 'v63'), 'unsupported_tv_provenance')
        need(node.get('supplier_namespace', node.get('namespace')) == namespace and
             node['operator_id'] == tv_operator and str(node['tv_hotel_id']) == hotel,
             'tv_operator_or_hotel_binding')
        natives = node.get('positive_native_candidates', [node.get('external_hotel_id')])
        need([str(x) for x in natives] == [native], 'tv_native_binding')
    elif key == 'v65':
        match = re.fullmatch(r'/dossiers/(\d+)/candidates/(\d+)/lanes/(operator_315|operator_342)', path)
        need(match is not None and match[3] == namespace, 'samo_lane_pointer')
        candidate = m['documents']['v65']['dossiers'][int(match[1])]['candidates'][int(match[2])]
        need(str(candidate['catalog_id']) == hotel, 'samo_catalog_binding')
        need(node['state'] == 'single_native' and [str(x) for x in node['native_ids']] == [native],
             'samo_native_binding')
    elif key in ('wave1', 'wave2', 'wave4', 'wave5', 'broad'):
        need(node['namespace'] == namespace and node['operator_id'] == samo_operator and
             str(node['catalog_id']) == hotel, 'samo_operator_or_hotel_binding')
        need(node['state'] == 'captured_single_native' and
             [str(x) for x in node['positive_native_candidates']] == [native], 'samo_native_binding')
    else:
        raise ValueError('unsupported_direct_samo_provenance')
    return deepcopy(ref)


def assess_pair(pair: dict, m: dict) -> dict:
    """Separate automated pending-review from real conflicts; never authorize a write."""
    local, catalog = identifier(pair['local_hotel_id']), identifier(pair['andromeda_catalog_id'])
    out = {'local_hotel_id': int(local), 'andromeda_catalog_id': catalog,
           'name': pair.get('name'), 'original_status': pair['status'],
           'original_reasons': list(pair['reasons']), 'status': 'hold', 'reasons': [],
           'safe_to_write_now': False, 'existing_hold_cleared': False,
           'fresh_current_validation_performed': False}
    if pair['status'] != 'hold':
        out['status'] = 'unchanged_' + pair['status']
        return out
    reasons = []
    residual = sorted(set(pair['reasons']) - PENDING_HOLD_REASONS)
    rows = m['source']['andromeda_catalog', catalog]
    if len(rows) != 1:
        reasons.append('requires_one_existing_pending_row')
    elif not (rows[0]['decision_status'] == 'pending' and rows[0]['local_hotel_id'] is None
              and rows[0]['evidence_valid'] is True):
        reasons.append('accepted_conflicting_invalid_or_nonpending_source')
    if residual:
        reasons.append('independent_historical_or_current_hold')
    out['residual_hold_reasons'] = residual
    if reasons:
        out['reasons'] = reasons
        return out
    old = rows[0]
    evs = m['evidence']['andromeda_catalog', catalog]
    if len(evs) != 1:
        out['reasons'] = ['missing_unique_saved_prior_evidence']
        return out
    ev, current = evs[0], m['hotels'].get(local)
    prior = ev['hotel_evidence']
    source = prior.get('source')
    if not (ev['identity'] == old and ev['raw_evidence_sha256'] == old['evidence_sha256']):
        reasons.append('prior_identity_digest_binding')
    if prior.get('reason') not in AUTOMATIC_PENDING_REASONS:
        reasons.append('pending_reason_requires_manual_review')
    if any(k not in {'source', 'reason', 'operation_id'} for k in prior):
        reasons.append('extra_prior_evidence_requires_review')
    if not isinstance(source, dict) or str(source.get('id')) != catalog:
        reasons.append('source_catalog_binding')
    if not current or current['hotel']['is_active'] != 1 or current['invalid_selected_evidence']:
        reasons.append('missing_active_valid_current_target')
    if m['target'][local, 'andromeda_catalog']:
        reasons.append('target_catalog_occupied')
    if any(str(r.get('catalog_hotel_id')) == local for r in m['context']['manual_anex']):
        reasons.append('manual_target_protected')
    if current and isinstance(source, dict):
        if not norm(source.get('state')) or not norm(current['hotel'].get('country_name')):
            reasons.append('missing_country_binding')
        elif norm(source.get('state')) != norm(current['hotel']['country_name']):
            reasons.append('country_conflict')
    verified, seen = [], set()
    for proof in pair['proofs']:
        ns, native = proof['namespace'], identifier(proof['native_id'])
        if ns not in DIRECT_OPERATORS:
            reasons.append('cross_namespace_not_authority')
            continue
        if (ns, native) in seen:
            continue
        seen.add((ns, native))
        if any(m['by_native'][side, ns, native] != {hotel} or
               m['by_hotel'][side, hotel, ns] != {native}
               for side, hotel in (('tv', local), ('samo', catalog))):
            reasons.append('global_native_or_hotel_collision')
        for row in m['source'][ns, native]:
            if not (row['decision_status'] == 'accepted' and row['evidence_valid'] is True and
                    str(row['local_hotel_id']) == local):
                reasons.append('operator_identity_occupied_or_protected')
        for row in m['target'][local, ns]:
            if not (str(row['external_hotel_id']) == native and row['decision_status'] == 'accepted'
                    and row['evidence_valid'] is True):
                reasons.append('target_operator_conflict')
        if not proof['tv'] or not proof['samo']:
            reasons.append('missing_either_source_proof')
            continue
        verified.append({'namespace': ns, 'native_id': native,
                         'tv': [verify_proof(x, 'tv', ns, native, local, m) for x in proof['tv']],
                         'samo': [verify_proof(x, 'samo', ns, native, catalog, m) for x in proof['samo']]})
    if not verified:
        reasons.append('no_exact_same_operator_proof')
    if reasons:
        out['reasons'] = sorted(set(reasons))
        return out
    anex = []
    for native in current['current_anex_ids']:
        targets = {str(x) for x in m['context']['effective_anex_native_targets'].get(str(native), [])}
        if targets == {local}:
            anex.append(str(native))
    target = current['hotel']
    geo = sorted({norm(target.get(k)) for k in ('region_name', 'subregion_name') if target.get(k)})
    out.update({'status': 'pending_transition_review_not_writer_ready',
                'old_row_expectations': deepcopy(old), 'saved_prior_evidence': deepcopy(prior),
                'saved_prior_evidence_projection_sha256': digest(prior),
                'current_target': deepcopy(target), 'exact_same_operator_proofs': verified,
                'exact_operator_count': len({p['namespace'] for p in verified}),
                'effective_direct_anex_ids_in_snapshot': sorted(anex, key=int),
                'would_complete_tv_triple_if_accepted_and_unchanged': bool(anex),
                'geography': {'supplier_town': source.get('town'), 'supplier_town_id': source.get('townKey'),
                              'local_region': target.get('region_name'), 'local_subregion': target.get('subregion_name'),
                              'exact_town_label_agrees': norm(source.get('town')) in geo,
                              'additional_geographic_review_required': norm(source.get('town')) not in geo},
                'required_before_acceptance': [
                    'permitted_fresh_current_read_and_explicit_pending_transition_scope',
                    'unredacted_prior_evidence_manual_origin_and_exact_digest_recheck',
                    'transaction_time_source_target_operator_manual_and_geography_guards',
                    'conditional_pending_null_target_update_with_prior_evidence_retained',
                    'post_commit_exact_readback_and_unchanged_other_identities'],
                'automatic_reason_is_not_a_manual_rejection': True})
    return out


def build_review(report: dict, documents: dict) -> dict:
    m = model(report, documents)
    reviewed = [assess_pair(p, m) for p in report['catalog_pairs'] if p['status'] == 'hold']
    pending = [p for p in reviewed if p['status'] == 'pending_transition_review_not_writer_ready']
    unresolved = []
    cache = defaultdict(list)
    for entry in documents['supplement']['catalog']['rows']:
        if entry['kind'] == 'HOTELS':
            cache[str(entry['row']['id'])].append(entry)
    for row in report['samo_nontriple']:
        if row['current_local_targets']:
            continue
        catalog = identifier(row['andromeda_catalog_id'])
        identities, evs = m['source']['andromeda_catalog', catalog], m['evidence']['andromeda_catalog', catalog]
        source = deepcopy(evs[0]['hotel_evidence'].get('source', {})) if len(evs) == 1 else {}
        if not source and len(cache[catalog]) == 1:
            source = deepcopy(cache[catalog][0]['row'])
        protected = any(x['decision_status'] not in ('pending',) or x['local_hotel_id'] is not None
                        or x['evidence_valid'] is not True for x in identities)
        pair_indices = row['catalog_pair_indices']
        known_native = any(row['operator_native_ids'].values())
        if protected:
            state = 'protected_conflict_no_automatic_acquisition'
        elif pair_indices:
            state = 'exact_pair_has_specific_review_hold'
        elif known_native:
            state = 'retained_native_needs_tv_or_namespace_proof'
        elif not source:
            state = 'missing_retained_metadata_bind_before_acquisition'
        else:
            state = 'needs_missing_operator_identity_not_local_mapping_prerequisite'
        unresolved.append({**deepcopy(row), 'source_metadata': source,
                           'current_identity_statuses': [x['decision_status'] for x in identities],
                           'next_step': state, 'local_mapping_required_to_plan_native_lookup': False,
                           'provider_execution_authorized': False,
                           'fresh_availability_context_bound': False,
                           'provider_calls_scheduled': 0, 'safe_to_write_now': False})
    need(len(report['tv_nontriple']) == 1674 and len(report['samo_nontriple']) == 487 and
         len(report['samo_operator_only']) == 283 and len(unresolved) == 125, 'full_dual_scope')
    return {'schema': 'match_pending_identity_review_v1', 'mode': 'offline_review_only',
            'source_report_sha256': REPORT_SHA, 'source_registry_at': report['source_registry_at'],
            'scope': {'tv_nontriple': 1674, 'samo_nontriple': 487, 'samo_operator_only': 283,
                      'samo_without_local_identity': 125, 'scope_sets_overlap': True},
            'summary': {'catalog_holds_reviewed': len(reviewed), 'pending_transition_dossiers': len(pending),
                        'other_holds_preserved': len(reviewed) - len(pending),
                        'potential_tv_triple_completions_not_committed': sum(p['would_complete_tv_triple_if_accepted_and_unchanged'] for p in pending),
                        'additional_geographic_review_count': sum(p['geography']['additional_geographic_review_required'] for p in pending),
                        'unmapped_samo_next_steps': dict(sorted(Counter(r['next_step'] for r in unresolved).items())),
                        'newly_accepted_identities': 0, 'provider_http_calls': 0, 'database_writes': 0},
            'pending_transition_dossiers': pending, 'all_catalog_hold_reviews': reviewed,
            'unmapped_samo_queue': unresolved, 'samo_operator_only_queue': deepcopy(report['samo_operator_only']),
            'prior83_status': 'unchanged_proposed_not_accepted',
            'current_validation_performed': False, 'safe_to_write_now': False,
            'execution_blocker': 'prior_workflow_write_denial_not_retried_or_bypassed',
            'limitations': ['Sanitized prior evidence is not a substitute for a fresh full evidence/manual-origin check.',
                            'The historical92 pending updater is an example of existing semantics, not permission to rerun or generalize it.',
                            'No earlier HOLD is cleared and no writer-ready or acceptance authority is emitted.',
                            'Native lookup can be planned for an unmapped SAMO source; supplier context and permission must still be bound.']}


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--report', type=Path, required=True)
    parser.add_argument('--input-dir', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    try:
        report, documents = load_inputs(args.report, args.input_dir)
        result = build_review(report, documents)
        with args.output.open('x', encoding='utf-8') as f:
            json.dump(result, f, ensure_ascii=False, sort_keys=True, indent=2, allow_nan=False)
            f.write('\n')
        print(json.dumps(result['summary'], ensure_ascii=False, sort_keys=True))
    except (OSError, ValueError, KeyError, IndexError, zipfile.BadZipFile) as error:
        parser.exit(2, f'Pending review failed: {type(error).__name__}: {error}\n')


if __name__ == '__main__':
    main()
