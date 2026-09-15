#!/usr/bin/env python3
"""MATCH receiving dossier: immutable evidence + newer CURRENT, never an acceptor.

No network client, DB connection, supplier call, mapping command or apply output.
The original recovery remains immutable. Its six primary-identity holds follow
the Andromeda/local anchor across operator namespaces in this receiving report.
"""
import argparse
from collections import Counter
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
import unicodedata
import zipfile


def require(ok, message):
    if not ok:
        raise ValueError(message)


def digest(raw):
    return hashlib.sha256(raw).hexdigest()


def unique_object(pairs):
    out = {}
    for k, v in pairs:
        require(k not in out, 'duplicate_json_key')
        out[k] = v
    return out


def parse(raw):
    return json.loads(raw, object_pairs_hook=unique_object)


def read_archive(path, expected):
    raw = Path(path).read_bytes()
    require(digest(raw) == expected, 'archive_digest')
    with zipfile.ZipFile(path) as archive:
        require(len(archive.namelist()) == len(set(archive.namelist())), 'duplicate_zip_member')
        result = {}
        for member in archive.infolist():
            name = PurePosixPath(member.filename)
            require(not name.is_absolute() and '..' not in name.parts and '\\' not in member.filename,
                    'unsafe_zip_path')
            require(member.file_size <= 50_000_000, 'oversize_member')
            if not member.is_dir():
                result[member.filename] = archive.read(member)
        return result


def verify(files, prefix=''):
    raw = files[prefix + 'result.json']
    result, receipt = parse(raw), parse(files[prefix + 'receipt.json'])
    require(receipt.get('result_sha256') == digest(raw), 'receipt_digest')
    for key in ('operation_id', 'source_sha', 'state'):
        require(receipt.get(key) == result.get(key), 'receipt_identity')
    require(receipt.get('readback_verified') is True, 'unverified_receipt')
    require(result.get('no_replay') is True and receipt.get('no_replay') is True, 'no_replay')
    require(result.get('database_writes') == receipt.get('database_writes') == 0, 'not_read_only')
    return result


def key(fact):
    values = tuple(fact.get(k) for k in ('operator_key', 'native_hotel_id', 'andromeda_hotel_id'))
    require(all(isinstance(v, str) and re.fullmatch(r'[1-9][0-9]{0,19}', v) for v in values), 'typed_identity')
    require(values[0] in ('5', '115', '315', '342'), 'operator_namespace')
    require(fact.get('action') == 'price' and fact.get('is_operator_hotel_key') is False, 'original_semantics')
    require(fact.get('country_id') in (1, 4), 'retained_core_countries')
    require(all(re.fullmatch(r'[0-9a-f]{64}', str(fact.get(k, ''))) for k in
                ('request_sha256', 'response_sha256')), 'missing_provenance')
    require(all(isinstance(fact.get(k), str) and fact[k].strip() for k in
                ('hotel_name', 'original_name')), 'missing_name')
    return values


def primary_tokens(name):
    # Former names remain in raw dossier, never used to hide current qualifiers.
    primary = re.split(r'\(\s*(?:ex\.?|former|бывш\.?)\s+', name, flags=re.I)[0]
    folded = ''.join(c for c in unicodedata.normalize('NFKD', primary.casefold())
                     if not unicodedata.combining(c))
    tokens = set(re.findall(r'[^\W_]+', folded))
    return tokens - {'hotel', 'hotels', 'resort', 'resorts', 'spa', 'the', 'and', 'by', 'отель', 'спа'}


def review_signals(fact, anchor):
    local = (anchor or {}).get('local') or {}
    local_tokens = primary_tokens(local.get('name', ''))
    signals = []
    meaningful = {'annex', 'annexe', 'beach', 'garden', 'gardens', 'north', 'south',
                  'east', 'west', 'posh', 'palm', 'palms', 'mountain', 'family', 'junior', 'deluxe'}
    for field in ('hotel_name', 'original_name'):
        source = primary_tokens(fact[field])
        sq, lq = sorted(source & meaningful), sorted(local_tokens & meaningful)
        if sq != lq:
            signals.append({'reason': 'primary_qualifiers_require_independent_evidence',
                            'source_field': field, 'source_qualifiers': sq, 'local_qualifiers': lq})
        substantive = (source & local_tokens) - meaningful
        if not substantive:
            signals.append({'reason': 'no_shared_primary_name_anchor', 'source_field': field})
    return signals


def reconcile(facts, current, prior_holds):
    require(current.get('state') == 'completed_read_only' and current.get('supplier_calls') == 0,
            'current_contract')
    targets = {str(t['andromeda_hotel_id']): t for t in current['targets']}
    require(len(targets) == len(current['targets']) == current['target_count'], 'duplicate_current_target')
    existing = {(r['supplier_namespace'], str(r['external_hotel_id'])): r
                for r in current['existing_operator_rows']}
    require(len(existing) == len(current['existing_operator_rows']), 'duplicate_current_native')
    holds = {}
    for h in prior_holds:
        holds.setdefault((str(h['andromeda_hotel_id']), h['snapshot_local_id']), []).append(h)
    counts, operators, signals = Counter(), Counter(), Counter()
    seen, index, candidates, unresolved = set(), [], [], []
    for fact in facts:
        op, native, andromeda = k = key(fact)
        require(k not in seen, 'duplicate_evidence_pair')
        seen.add(k)
        a, n = targets.get(andromeda), existing.get(('operator_' + op, native))
        if not a:
            route = 'missing_current_anchor'
        elif a['country_id'] != fact['country_id']:
            route = 'country_conflict'
        elif a['decision_status'] != 'accepted' or a['local_hotel_id'] is None:
            route = 'anchor_not_accepted'
        elif n and n['decision_status'] == 'accepted':
            route = 'already_linked_same_target' if n['local_hotel_id'] == a['local_hotel_id'] else 'accepted_target_conflict'
        elif n and (n['decision_status'] != 'pending' or n['local_hotel_id'] is not None):
            route = 'protected_nonaccepted_native'
        elif n:
            route = 'existing_pending_requires_current_guards'
        else:
            route = 'missing_native_requires_current_guards'
        counts[route] += 1
        inherited = holds.get((andromeda, (a or {}).get('local_hotel_id')), [])
        identity_signals = review_signals(fact, a) if a and a.get('local') else []
        index.append([op, native, andromeda, route, (a or {}).get('local_hotel_id')])
        if route in ('existing_pending_requires_current_guards', 'missing_native_requires_current_guards'):
            reasons = sorted(set(s['reason'] for s in identity_signals))
            if inherited:
                reasons.append('prior_primary_identity_hold_on_same_anchor')
            if op == '5':
                reasons.append('anex_authority_and_active_lane_2412_required')
            for reason in reasons:
                signals[reason] += 1
            operators[op] += 1
            candidates.append({'fact': fact, 'current_anchor': a, 'current_native': n,
                               'route': route, 'auto_accept': False,
                               'frequency': int(a.get('frequency', 0)),
                               'review_signals': identity_signals, 'dependency_reasons': reasons,
                               'inherited_identity_holds': inherited})
        elif route == 'anchor_not_accepted':
            unresolved.append({'fact': fact, 'current_anchor': a, 'current_native': n,
                               'auto_accept': False})
    candidates.sort(key=lambda r: (-r['frequency'], key(r['fact'])))
    index.sort()
    return {'state': 'prepared_current_dossier_not_apply_manifest', 'auto_accept': False,
            'apply_manifest': False, 'safe_mappings': 0, 'database_writes': 0,
            'mapping_writes': 0, 'supplier_calls': 0, 'tourvisor_calls': 0,
            'pair_count': len(index), 'relation_counts': dict(sorted(counts.items())),
            'candidate_count_not_safe': len(candidates), 'candidate_by_operator': dict(sorted(operators.items())),
            'candidate_signal_counts': dict(sorted(signals.items())),
            'full_pair_index_columns': ['operator_key', 'native_hotel_id', 'andromeda_hotel_id', 'route', 'current_local_id'],
            'full_pair_index': index, 'candidates': candidates, 'unresolved_anchors': unresolved,
            'current_operation_id': current['operation_id'], 'current_source_sha': current['source_sha'],
            'current_target_count': current['target_count'], 'current_operator_row_count': len(existing),
            'not_current_at_future_apply': True,
            'mandatory_before_write': ['fresh_reserved_operation', 'source_request_provenance',
                'current_primary_names_and_aliases', 'current_country_and_parent_geography',
                'all_coordinate_conflicts_gt5km', 'manual_exclusions_and_conflicts',
                'same_provider_target_occupancy', 'transaction_current_revalidation',
                'commit', 'per_row_readback', 'durable_receipt']}


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    for name in ('evidence', 'current', 'prior_report'):
        ap.add_argument('--' + name.replace('_', '-'), required=True)
        ap.add_argument('--' + name.replace('_', '-') + '-sha256', required=True)
    ap.add_argument('--output', required=True)
    args = ap.parse_args()
    evidence = read_archive(args.evidence, args.evidence_sha256)
    current_files = read_archive(args.current, args.current_sha256)
    er, cr = verify(evidence), verify(current_files, 'server/')
    require(er['state'] in ('completed', 'stopped_no_retry'), 'unknown_evidence')
    require(all(er.get(k) == 0 for k in ('mapping_writes', 'tourvisor_calls', 'all_calls', 'booking_calls')),
            'source_side_effects')
    require(current_files['reservation.json'] == current_files['server/reservation.json'], 'reservation_changed')
    reservation = parse(current_files['reservation.json'])
    require(reservation.get('operation_id') == cr['operation_id'] and
            reservation.get('source_sha') == cr['source_sha'] and
            reservation.get('state') == 'reserved_before_db_access', 'reservation_contract')
    prior_raw = Path(args.prior_report).read_bytes()
    require(digest(prior_raw) == args.prior_report_sha256, 'prior_report_digest')
    prior = parse(prior_raw)
    require(prior['source_result_sha256'] == digest(evidence['result.json']), 'prior_evidence_binding')
    require(prior['pair_count'] == er['unique_pair_count'] == len(er['facts']), 'retained_pair_count')
    require(prior.get('apply_manifest') is False and prior.get('auto_accept') is False, 'prior_report_authority')
    report = reconcile(er['facts'], cr, prior['illustrative_identity_holds_not_manual_decisions'])
    report['provenance'] = {'evidence_archive_sha256': args.evidence_sha256,
                            'current_archive_sha256': args.current_sha256,
                            'prior_report_sha256': args.prior_report_sha256,
                            'evidence_result_sha256': digest(evidence['result.json']),
                            'current_result_sha256': digest(current_files['server/result.json']),
                            'source_operation_id': er['operation_id'],
                            'source_state': er['state'], 'source_no_replay': True,
                            'current_no_replay': True,
                            'verification_scope': 'Receipt/archive hashes and exact retained PR2528 evidence binding; no raw supplier response replay or rehash claim.'}
    raw = (json.dumps(report, ensure_ascii=False, sort_keys=True, separators=(',', ':')) + '\n').encode()
    with Path(args.output).open('xb') as output:
        output.write(raw)
        output.flush()
    require(Path(args.output).read_bytes() == raw, 'output_readback')
    print(json.dumps({k: report[k] for k in ('pair_count', 'relation_counts', 'candidate_count_not_safe',
                                          'candidate_by_operator', 'candidate_signal_counts')}))
    print('report_sha256=' + digest(raw))


if __name__ == '__main__':
    main()
