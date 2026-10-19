"""Typed, sanitized retained evidence. Candidates never grant write authority."""
from __future__ import annotations
import collections
import hashlib
import json
from pathlib import Path
import re
import sys
from urllib.parse import parse_qsl, urlsplit

NAMESPACES = ('operator_5', 'operator_315', 'bgoperator', 'operator_115', 'operator_342')


def positive(value):
    return str(value) if re.fullmatch(r'[1-9][0-9]{0,19}', str(value)) else None


def operator_family(name):
    name = str(name).casefold().replace(' ', '')
    if 'anex' in name or 'анекс' in name:
        return 'ANEX'
    if 'fun&sun' in name or 'funsun' in name or 'фансан' in name:
        return 'FUN&SUN'
    if 'библио' in name or 'biblio' in name:
        return 'Библио-Глобус'
    if 'интурист' in name or 'intourist' in name:
        return 'Интурист'
    return None


def native_link(value):
    """Preserve URL namespace; a Tourvisor URL ID is not a SAMO namespace ID."""
    if not isinstance(value, str) or len(value) > 2048:
        return []
    try:
        url = urlsplit(value)
        if (url.scheme != 'https' or url.username or url.password or url.fragment
                or url.port not in (None, 443)):
            return []
        host = (url.hostname or '').lower()
        keys = {'anextour.ru': ('hotellist', 'hotelcode'), 'bgoperator.ru': ('f4',),
                'fun-sun.ru': ('hotelid', 'hotel_id'), 'intourist.ru': ('hotelid', 'hotel_id')}
        base = host[4:] if host.startswith('www.') else host
        if base not in keys:
            return []
        pairs = parse_qsl(url.query, keep_blank_values=True)
        if any(re.search(r'token|jwt|auth|secret|pass|cookie|session|signature|api.?key', k, re.I) for k, _ in pairs):
            return []
        found = [(k.lower(), positive(v)) for k, v in pairs if k.lower() in keys[base]]
        if base in ('fun-sun.ru', 'intourist.ru'):
            match = re.fullmatch(r'/hotel/([1-9][0-9]{0,19})/?', url.path)
            if match:
                found.append(('hotel-path', match[1]))
        if not found or any(v is None for _, v in found) or len({v for _, v in found}) != 1:
            return []
        return [{'namespace': 'operator_url:' + base + ':' + kind, 'external_id': value}
                for kind, value in sorted(set(found))]
    except (ValueError, TypeError):
        return []


def project(path: Path):
    counts = collections.Counter()
    by_operator = {}
    source_namespaces = collections.Counter()
    registry = collections.defaultdict(list)
    targets = {}
    cohort = set()
    edges = {}
    scope = None
    raw_hash = hashlib.sha256()
    raw_bytes = 0
    with path.open('rb') as handle:
        for raw in handle:
            raw_bytes += len(raw)
            if len(raw) > 4*1024*1024 or raw_bytes > 128*1024*1024:
                raise ValueError('projection_size_bound')
            raw_hash.update(raw)
            row = json.loads(raw)
            kind = row['kind']
            counts[kind] += 1
            if kind == 'scope':
                if scope is not None or counts.total() != 1 or row.get('cohort') != 'common4-retained':
                    raise ValueError('projection_scope')
                scope = row
            elif scope is None:
                raise ValueError('projection_scope_missing')
            elif kind == 'identity':
                r = row['row']
                registry[(str(r['supplier_namespace']), str(r['external_hotel_id']))].append(r)
            elif kind == 'target':
                r = row['row']
                targets[str(r['id'])] = {k: r.get(k) for k in ('id', 'name', 'country_id', 'country_name',
                    'region_name', 'subregion_name', 'latitude', 'longitude', 'is_active')}
            elif kind == 'observation':
                r = row['row']
                family = operator_family(r.get('operator_name'))
                if family is None:
                    continue
                tv = positive(r['hotel_id'])
                if tv is None:
                    raise ValueError('observation_target_id')
                cohort.add(tv)
                key = str(r['operator_id'])
                bucket = by_operator.setdefault(key, {'operator_id': int(key), 'operator_name': str(r['operator_name']),
                    'family': family, 'observations': 0, 'with_native_url': 0, 'without_native_url': 0})
                bucket['observations'] += 1
                native = native_link(r.get('operator_link'))
                bucket['with_native_url' if native else 'without_native_url'] += 1
                if int(r.get('native_id_conflict') or 0):
                    native = []
                    bucket['conflicted_observations'] = bucket.get('conflicted_observations', 0) + 1
                for item in native:
                    ek = (item['namespace'], item['external_id'], tv)
                    edge = edges.setdefault(ek, {**item, 'tv_hotel_id': int(tv), 'family': family,
                        'proof_kind': 'retained_operator_url', 'evidence': [],
                        'namespace_bridge': 'not_proven', 'safe_to_write_now': False})
                    edge['evidence'].append({'observation_id': int(r['id']), 'operator_id': int(key),
                        'operator_link_sha256': hashlib.sha256(r['operator_link'].encode()).hexdigest(),
                        'first_seen_at': r.get('first_seen_at'), 'last_seen_at': r.get('last_seen_at')})
            elif kind == 'legacy_source':
                s = row['source']
                if hashlib.sha256(s['source_json'].encode()).hexdigest() != s['source_sha256']:
                    raise ValueError('projection_source_integrity')
                ns = str(s['namespace'])
                source_namespaces[ns] += 1
                tv = positive(row['tv_hotel_id'])
                if tv is None:
                    raise ValueError('legacy_target_id')
                cohort.add(tv)
                # Exact namespaces only. Prefixes and equal integers do not
                # authorize a provider/URL/SAMO namespace bridge.
                if ns not in NAMESPACES or positive(s['external_key']) is None:
                    continue
                key = (ns, str(s['external_key']), tv)
                edge = edges.setdefault(key, {'namespace': ns, 'external_id': str(s['external_key']),
                    'tv_hotel_id': int(tv), 'proof_kind': 'retained_legacy_source', 'evidence': [],
                    'namespace_bridge': 'exact_source_namespace', 'safe_to_write_now': False})
                edge['evidence'].append({'old_local_id': row['old_local_id'],
                    'snapshot_sha256': row['snapshot_sha256'], 'source_sha256': s['source_sha256']})
            else:
                raise ValueError('projection_unknown_kind')
    if scope is None:
        raise ValueError('projection_empty_capture')
    registry_available = {'supplier_namespace', 'external_hotel_id', 'local_hotel_id', 'decision_status'} <= set(
        scope.get('schema_columns', {}).get('andromeda_hotel_identities', []))
    ownership = collections.defaultdict(set)
    for ns, native, tv in edges:
        ownership[(ns, native)].add(tv)
    states = collections.Counter()
    for (ns, native, tv), edge in edges.items():
        current = registry.get((ns, native), [])
        accepted = {str(r['local_hotel_id']) for r in current if r['decision_status'] == 'accepted'}
        if not registry_available:
            state = 'needs_current_registry_read'
        elif len(ownership[(ns, native)]) > 1:
            state = 'hold_multiple_targets'
        elif accepted == {tv}:
            state = 'already_accepted'
        elif accepted:
            state = 'hold_accepted_ownership'
        elif ns.startswith('operator_url:'):
            state = 'needs_independent_namespace_bridge'
        else:
            state = 'needs_independent_source_proof_and_current_guards'
        edge['state'] = state
        edge['target'] = targets.get(tv)
        edge['current_registry_rows'] = len(current)
        states[state] += 1
    coverage = []
    for ns in NAMESPACES:
        rows = [r for (n, _), rr in registry.items() if n == ns for r in rr]
        accepted = [r for r in rows if r['decision_status'] == 'accepted']
        local = {str(r['local_hotel_id']) for r in accepted}
        coverage.append({'namespace': ns, 'registry_available': registry_available,
            'registry_rows': len(rows) if registry_available else None,
            'accepted_rows': len(accepted) if registry_available else None,
            'unique_registry_local_hotels': len(local) if registry_available else None,
            'accepted_on_retained_cohort': len(local & cohort) if registry_available else None,
            'without_accepted_on_retained_cohort': len(cohort-local) if registry_available else None,
            'nonaccepted_rows': len(rows)-len(accepted) if registry_available else None,
            'decision_status_counts': dict(collections.Counter(str(r['decision_status']) for r in rows)) if registry_available else None})
    bg_local = {str(r['local_hotel_id']) for (ns,_),rr in registry.items()
                if ns in ('bgoperator','operator_115') for r in rr if r['decision_status']=='accepted'}
    return {'schema_version': 1, 'state': 'projected_read_only', 'cohort': 'common4-retained',
        'captured_at_utc': scope['captured_at_utc'], 'database_snapshot': scope['database_snapshot'],
        'target_scope': scope.get('target_scope','not_declared'),
        'cohort_tv_hotel_ids': sorted(map(int, cohort)), 'cohort_hotels': len(cohort),
        'raw_sha256': raw_hash.hexdigest(), 'raw_bytes': raw_bytes, 'capture_rows': dict(counts),
        'observed_operators': list(by_operator.values()), 'legacy_source_namespaces': dict(source_namespaces),
        'registry_coverage': coverage, 'candidate_states': dict(states),
        'biblio_union_unique_registry_local_hotels': len(bg_local) if registry_available else None,
        'biblio_union_on_retained_cohort': len(bg_local & cohort) if registry_available else None,
        'candidate_edges': list(edges.values()), 'new_accepted_mappings': 0,
        'supplier_calls': 0, 'database_writes': 0, 'mapping_writes': 0, 'safe_to_write_now': False,
        'limitations': ['retained cohort; not full registry or live30 coverage',
            'direct ANEX decisions are not the SAMO operator_5 registry',
            'URL-native namespaces need independent bridges before SAMO/direct acceptance',
            'CURRENT manual/exclusion/occupancy/geo guards remain mandatory']}


if __name__ == '__main__':
    if len(sys.argv) != 2:
        raise SystemExit('projection_input_required')
    print(json.dumps(project(Path(sys.argv[1])), ensure_ascii=False, sort_keys=True))
