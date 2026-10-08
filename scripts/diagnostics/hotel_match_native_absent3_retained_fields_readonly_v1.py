#!/usr/bin/env python3
"""Fixed omitted fields of 13 normalized snapshots; never an identity intake."""
import collections
import datetime as dt
import hashlib
import json
import os
import pathlib
import re
import stat
import sys

import hotel_match_operator115_only2_retained_fields_readonly_v1 as retained

n = retained.n
OP = 'int-andromeda-match-native-absent3-retained-fields-20261008-v1'
BATCH = 'native-absent3-retained-fields-20261008'
MODE = 'match-native-absent3-retained-fields-readonly'
MANIFEST_SHA = 'f43c431165137f817a6d04b4e925b7d382a3c2ff36ec139f3303d86998958e71'
FIXTURE = pathlib.Path(__file__).resolve().with_name('fixtures') / 'hotel_match_native_absent3_retained_fields_readonly_v1.json'
FIELDS = ('offer.hotel', 'offer.operator', 'offer.operator_ref', 'offer.hotel_content.hotel_url',
          'offer.hotel_content.region', 'offer.hotel_content.category', 'offer.hotel_content.image_url',
          'offer.hotel_content.source')
ZERO = retained.NO_EFFECTS
FALSE = retained.FALSE_FLAGS
STATES = ('completed_read_only_native_absent3_fields', 'completed_read_only_native_absent3_fields_incomplete')
RECEIPT_KEYS = ('operation', 'batch', 'source_sha', 'state', 'private_input_sha256', *ZERO, *FALSE, 'no_replay')
BASE_HOLDS = ('normalized_snapshot_not_original_supplier_response', 'independent_local_and_tv_ids_not_established',
              'current_registry_checks_not_performed')
ROW_FAILURES = ('retained_source_unavailable_or_digest', 'retained_schema_or_identity_invalid')
SOURCE_IDS = ('2000049502', '7660', '2000108645')


def manifest(path=FIXTURE):
    value = n.read_pinned(path, MANIFEST_SHA, 65536)
    n.require_fields(value, {'schema': 'match-native-absent3-retained-fields-fixture/1',
        'operation': OP, 'batch': BATCH, 'mode': MODE, 'requested_rows': 13, 'requested_catalog_candidates': 3,
        'source_namespace': 'andromeda_catalog', 'target_namespace': 'not_established',
        'current_readiness': 'not_evaluated', 'normalized_snapshot_only': True,
        'original_supplier_response_proven': False, 'projection_fields': list(FIELDS),
        **dict.fromkeys(('provider_http_calls', 'physical_http_attempts', 'database_reads', 'database_writes',
                         'mapping_writes', 'maximum_writes'), 0), **dict.fromkeys(FALSE, False)})
    rows = value['rows']
    if len(rows) != 13 or collections.Counter(r['catalog_id'] for r in rows) != {'2000049502': 6, '7660': 6, '2000108645': 1}:
        raise ValueError('fixture_roster')
    if len({r['source_file'] for r in rows}) != 13 or any(
        not re.fullmatch(r'searches/[a-f0-9]{64}-[1-9][0-9]*-[1-9][0-9]*\.json', r['source_file'])
        or not re.fullmatch(r'[a-f0-9]{64}', r['sha256'])
        or not re.fullmatch(r'/store/snapshot/offers/[0-9]+', r['json_pointer'])
        or r['independent_tv_hotel_id'] is not None for r in rows):
        raise ValueError('fixture_references')
    n.require_fields(value['limits'], {'raw_file_bytes': 2097152, 'total_raw_bytes': 27262976,
                                     'private_input_bytes': 4194304, 'public_result_bytes': 1048576})
    return value


def pointer(data, path):
    if not re.fullmatch(r'/store/snapshot/offers/[0-9]+', path):
        raise ValueError('snapshot_pointer')
    for key in path[1:].split('/'):
        data = data[int(key)] if isinstance(data, list) else data[key]
    if not isinstance(data, dict):
        raise ValueError('snapshot_row')
    return data


def validate_snapshot(data, row, spec):
    store = data.get('store')
    if not isinstance(store, dict):
        raise ValueError('snapshot_store')
    n.require_fields(store, {'created_at': spec['created_at']})
    n.require_fields(store.get('criteria'), spec['expected_criteria'])
    n.require_fields(data.get('criteria'), spec['expected_criteria'])
    n.require_fields(row, {'provider': 'andromeda', 'supplier_namespace': 'andromeda_catalog',
                          'external_hotel_id': spec['catalog_id'], **spec['expected_offer']})


def capture(cache_root, opdir, fixture):
    rows, originals = [], []
    stats = {'raw_files_attempted': 0, 'raw_files_read': 0, 'raw_bytes_read': 0}
    for i, spec in enumerate(fixture['rows']):
        entry = {'spec': spec, 'raw_verified': False, 'failure': None, 'original_row': None}
        stats['raw_files_attempted'] += 1
        try:
            raw = n.file_bytes(cache_root / spec['source_file'], fixture['limits']['raw_file_bytes'])
            stats['raw_files_read'] += 1
            stats['raw_bytes_read'] += len(raw)
            if hashlib.sha256(raw).hexdigest() != spec['sha256']:
                raise ValueError('source_digest')
        except Exception:
            entry['failure'] = ROW_FAILURES[0]
            rows.append(entry)
            continue
        # Exact permitted bytes, including unrelated/private values, survive before parsing.
        saved = f'retained-{i:02d}.json'
        retained.durable_bytes(opdir / saved, raw)
        originals.append({'row_index': i, 'file': saved, 'sha256': spec['sha256'], 'bytes': len(raw)})
        try:
            data = n.parsed(raw)
            entry['original_row'] = pointer(data, spec['json_pointer'])
            validate_snapshot(data, entry['original_row'], spec)
            entry['raw_verified'] = True
        except Exception:
            entry['failure'] = ROW_FAILURES[1]
        rows.append(entry)
    return {'schema': 'match-native-absent3-retained-private-input/1', 'operation': OP, 'batch': BATCH,
            'rows': rows, 'originals': originals, 'stats': stats}


def field(row, name):
    obj = row
    parts = name.split('.')[1:]
    for part in parts[:-1]:
        obj = obj.get(part) if isinstance(obj, dict) else None
    present = isinstance(obj, dict) and parts[-1] in obj
    value = obj.get(parts[-1]) if isinstance(obj, dict) else None
    # Reuse reviewed redaction for URLs, retaining their original private values.
    alias = 'row.hotelUrl' if name.endswith(('.hotel_url', '.image_url')) else 'row.hotel'
    result = retained.optional_projection(alias, present, value)
    result['field_name'] = name
    return result


def project(private, fixture, private_sha):
    n.require_fields(private, {'schema': 'match-native-absent3-retained-private-input/1', 'operation': OP, 'batch': BATCH})
    if len(private['rows']) != 13:
        raise ValueError('private_roster')
    out = []
    for i, (entry, spec) in enumerate(zip(private['rows'], fixture['rows'])):
        if not n.equal_typed(entry['spec'], spec):
            raise ValueError('private_spec')
        fields, holds = [], list(BASE_HOLDS)
        if entry['raw_verified'] is True and entry['failure'] is None:
            n.require_fields(entry['original_row'], {'provider': 'andromeda', 'supplier_namespace': 'andromeda_catalog',
                'external_hotel_id': spec['catalog_id'], **spec['expected_offer']})
            fields = [field(entry['original_row'], name) for name in FIELDS]
            holds += [f['hold'] for f in fields if f['hold']]
        elif entry['raw_verified'] is False and entry['failure'] in ROW_FAILURES:
            holds.append(entry['failure'])
        else:
            raise ValueError('private_identity_status')
        out.append({'catalog_id': spec['catalog_id'], 'historical_local_hotel_id': spec['historical_local_hotel_id'],
            'independent_tv_hotel_id': None, 'source_namespace': 'andromeda_catalog', 'target_namespace': 'not_established',
            'source_file': spec['source_file'], 'source_sha256': spec['sha256'], 'json_pointer': spec['json_pointer'],
            'raw_verified': entry['raw_verified'], 'fields': fields, 'holds': list(dict.fromkeys(holds)),
            'private_input_pointer': {'sha256': private_sha, 'json_pointer': f'/rows/{i}'}, **dict.fromkeys(FALSE, False)})
    return out


def verify_originals(opdir, private, fixture):
    originals = private['originals']
    if (len({o['row_index'] for o in originals}) != len(originals)
        or any(type(o['row_index']) is not int or not 0 <= o['row_index'] < 13 for o in originals)):
        raise ValueError('original_roster')
    by_index = {o['row_index']: o for o in originals}
    for i, (entry, spec) in enumerate(zip(private['rows'], fixture['rows'])):
        if entry['failure'] == ROW_FAILURES[0]:
            if i in by_index or entry['original_row'] is not None or entry['raw_verified'] is not False:
                raise ValueError('original_unavailable_binding')
            continue
        o = by_index.get(i)
        if o is None or o['file'] != f'retained-{i:02d}.json' or o['sha256'] != spec['sha256']:
            raise ValueError('original_binding')
        raw = n.file_bytes(opdir / o['file'], fixture['limits']['raw_file_bytes'])
        if type(o['bytes']) is not int or len(raw) != o['bytes'] or hashlib.sha256(raw).hexdigest() != spec['sha256']:
            raise ValueError('original_digest')
        try:
            data = n.parsed(raw)
            expected_row = pointer(data, spec['json_pointer'])
        except Exception:
            if entry['raw_verified'] is not False or entry['failure'] != ROW_FAILURES[1]:
                raise ValueError('original_schema_binding')
            expected_row = None
        if not n.equal_typed(entry['original_row'], expected_row):
            raise ValueError('original_row_binding')
        if entry['raw_verified'] is True:
            validate_snapshot(data, expected_row, spec)
    if set(p.name for p in opdir.glob('retained-*.json')) != {o['file'] for o in originals}:
        raise ValueError('original_files_binding')


def validate_result(data, receipt=None, expected_source=None, private_input=None):
    fixture = manifest()
    n.require_fields(data, {'schema': 'match-native-absent3-retained-fields-result/1', 'operation': OP, 'batch': BATCH,
        'requested_rows': 13, 'requested_catalog_candidates': 3, 'source_namespace': 'andromeda_catalog',
        'target_namespace': 'not_established', 'current_readiness': 'not_evaluated', 'normalized_snapshot_only': True,
        'original_supplier_response_proven': False, 'no_replay': True, **dict.fromkeys(ZERO, 0), **dict.fromkeys(FALSE, False)})
    if not re.fullmatch(r'[a-f0-9]{40}', data['source_sha']) or (expected_source is not None and data['source_sha'] != expected_source):
        raise ValueError('result_source')
    if not re.fullmatch(r'[a-f0-9]{64}', data['private_input_sha256']) or private_input is None:
        raise ValueError('result_private')
    if hashlib.sha256(retained.private_bytes(private_input)).hexdigest() != data['private_input_sha256']:
        raise ValueError('result_private_digest')
    rows = project(private_input, fixture, data['private_input_sha256'])
    expected = {'rows': rows, 'rows_examined': 13, 'raw_references_verified': sum(r['raw_verified'] for r in rows),
                'originals': private_input['originals'], **private_input['stats'],
                'hold_counts': dict(collections.Counter(h for r in rows for h in r['holds']))}
    n.require_fields(data, expected)
    state = STATES[1] if any(not r['raw_verified'] or any(f['hold'] for f in r['fields']) for r in rows) else STATES[0]
    if data['state'] != state or len(n.enc(data)) > fixture['limits']['public_result_bytes']:
        raise ValueError('result_state_or_cap')
    if receipt is not None:
        n.require_fields(receipt, {k: data[k] for k in RECEIPT_KEYS})
        if set(receipt) != set(RECEIPT_KEYS) | {'result_sha256'} or receipt['result_sha256'] != hashlib.sha256(n.enc(data)).hexdigest():
            raise ValueError('receipt_binding')


def execute(project_root, opdir, cache_root, fixture_path):
    project_root, opdir, cache_root = map(pathlib.Path, (project_root, opdir, cache_root))
    home = project_root.parent.parent
    if (project_root != home / 'www/anytoour.ru' or opdir != home / '.anytoour-match/operations' / OP
        or fixture_path != FIXTURE or any(p.resolve() != p or p.is_symlink() or not p.is_dir() for p in (project_root, opdir, cache_root))
        or not cache_root.is_relative_to(home) or cache_root.is_relative_to(home / 'www') or opdir.stat().st_mode & 0o077):
        raise ValueError('execution_paths')
    head = os.environ.get('MATCH_SOURCE_SHA', '')
    if not re.fullmatch(r'[a-f0-9]{40}', head):
        raise ValueError('execution_source')
    fixture = manifest(fixture_path)
    reservation = n.parsed(n.file_bytes(opdir / 'reservation.json', 65536))
    n.require_fields(reservation, {'operation': OP, 'source_sha': head, 'batch': BATCH, 'provider_http_calls': 0,
                                 'maximum_writes': 0, 'state': 'reserved_before_retained_read'})
    if any((opdir / name).exists() or (opdir / name).is_symlink() for name in ('execution-started.json', 'current-input.json', 'result.json', 'receipt.json')) or list(opdir.glob('retained-*.json')):
        raise ValueError('operation_consumed_no_replay')
    n.save(opdir / 'execution-started.json', {'operation': OP, 'source_sha': head, 'no_replay': True})
    private = capture(cache_root, opdir, fixture)
    if len(retained.private_bytes(private)) > fixture['limits']['private_input_bytes']:
        raise ValueError('private_cap')
    private_sha = retained.durable_bytes(opdir / 'current-input.json', retained.private_bytes(private))
    verify_originals(opdir, private, fixture)
    rows = project(private, fixture, private_sha)
    result = {'schema': 'match-native-absent3-retained-fields-result/1', 'operation': OP, 'batch': BATCH,
        'source_sha': head, 'state': STATES[1] if any(not r['raw_verified'] or any(f['hold'] for f in r['fields']) for r in rows) else STATES[0],
        'captured_at_utc': dt.datetime.now(dt.timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'),
        'private_input_sha256': private_sha, 'requested_rows': 13, 'requested_catalog_candidates': 3,
        'source_namespace': 'andromeda_catalog', 'target_namespace': 'not_established', 'current_readiness': 'not_evaluated',
        'normalized_snapshot_only': True, 'original_supplier_response_proven': False,
        'rows': rows, 'rows_examined': 13, 'raw_references_verified': sum(r['raw_verified'] for r in rows),
        'originals': private['originals'], **private['stats'],
        'hold_counts': dict(collections.Counter(h for r in rows for h in r['holds'])),
        'no_replay': True, **dict.fromkeys(ZERO, 0), **dict.fromkeys(FALSE, False)}
    validate_result(result, expected_source=head, private_input=private)
    digest = n.save(opdir / 'result.json', result)
    receipt = {k: result[k] for k in RECEIPT_KEYS} | {'result_sha256': digest}
    validate_result(result, receipt, head, private)
    n.save(opdir / 'receipt.json', receipt)
    print(json.dumps({k: result[k] for k in ('state', 'rows_examined', 'accepted', 'written')}))
    return 0


URL_MODE = 'match-native-absent3-saved-urls-readonly'
URL_OP = 'int-andromeda-match-native-absent3-saved-urls-20261008-v1'
URL_BATCH = 'native-absent3-saved-urls-20261008'
PARENT_SOURCE = '0218e3400a8733e1e3356e21a02946a9d0c91cc0'
PARENT_PRIVATE_SHA = '913f382d46a9c303b4b4db9f509cbdb7aeea82b98612e737ee6b0248ace1e484'
PARENT_RESULT_SHA = 'd9a386659fce8dfa2499c2af7101418b4353302402b062bd7968787142d9fe3d'
PARENT_RESULT_BYTES = 64969
SUN_URL_SHA = 'c7bb70268683d848abb595f7ae010a8e3621aa0e0de3f6f136aca3f0ed76f0c0'
URL_FIELDS = ('offer.hotel_content.hotel_url', 'offer.hotel_content.image_url')
URL_STATES = ('completed_read_only_native_absent3_saved_urls',
              'completed_read_only_native_absent3_saved_urls_incomplete')
URL_RECEIPT_KEYS = ('operation', 'batch', 'source_sha', 'state', 'private_input_sha256',
                    'parent_source_sha', 'parent_private_input_sha256', 'parent_result_sha256',
                    *ZERO, *FALSE, 'no_replay')
PARENT_FILES = (('current-input.json', 4194304), ('result.json', PARENT_RESULT_BYTES),
                ('receipt.json', 65536))


def saved_parent_bytes(directory, copied=False):
    """Only the three terminal metadata files; no cache/original-body access."""
    raw, descriptors = {}, []
    for name, cap in PARENT_FILES:
        path = directory / ('parent-' + name if copied else name)
        before = path.lstat()
        if not stat.S_ISREG(before.st_mode) or stat.S_IMODE(before.st_mode) != 0o600:
            raise ValueError('saved_parent_file_security')
        body = n.file_bytes(path, cap)
        after = path.lstat()
        identity = lambda s: (s.st_dev, s.st_ino, s.st_size, s.st_mtime_ns, s.st_ctime_ns, s.st_mode)
        if identity(before) != identity(after):
            raise ValueError('saved_parent_file_drift')
        raw[name] = body
        descriptors.append({'file': name, 'sha256': hashlib.sha256(body).hexdigest(), 'bytes': len(body)})
    if (hashlib.sha256(raw['current-input.json']).hexdigest() != PARENT_PRIVATE_SHA
        or hashlib.sha256(raw['result.json']).hexdigest() != PARENT_RESULT_SHA
        or len(raw['result.json']) != PARENT_RESULT_BYTES):
        raise ValueError('saved_parent_digest')
    return raw, descriptors


def validate_saved_parent(raw):
    private, result, receipt = (n.parsed(raw[name]) for name, _ in PARENT_FILES)
    if raw['receipt.json'] != n.enc(receipt):
        raise ValueError('saved_parent_receipt_bytes')
    validate_result(result, receipt, PARENT_SOURCE, private)
    n.require_fields(result, {'state': STATES[1], 'raw_references_verified': 13,
        'raw_files_attempted': 13, 'raw_files_read': 13, 'requested_catalog_candidates': 3})
    if (len(private['rows']) != 13 or any(e['raw_verified'] is not True or e['failure'] is not None
                                         for e in private['rows'])):
        raise ValueError('saved_parent_verified_roster')
    values = []
    for i, entry in enumerate(private['rows']):
        original = entry['original_row']
        content = original.get('hotel_content')
        if not isinstance(content, dict) or any(k not in content for k in ('hotel_url', 'image_url')):
            raise ValueError('saved_parent_url_slots')
        for name in URL_FIELDS:
            value = content[name.rsplit('.', 1)[1]]
            old = next(f for f in result['rows'][i]['fields'] if f['field_name'] == name)
            if (hashlib.sha256(retained.private_bytes(value)).hexdigest() != old['value_sha256']
                or len(retained.private_bytes(value)) != old['value_bytes'] or old['present'] is not True):
                raise ValueError('saved_parent_field_binding')
            values.append(value)
        if i < 12 and content['hotel_url'] is not None:
            raise ValueError('saved_parent_null_roster')
    if (sum(type(v) is str for v in values) != 14
        or hashlib.sha256(retained.private_bytes(private['rows'][12]['original_row']['hotel_content']['hotel_url'])).hexdigest() != SUN_URL_SHA):
        raise ValueError('saved_parent_string_roster')
    return private, result, receipt


def saved_url_field(row, name):
    """Operator context controls redaction only, never establishes identity."""
    value = row['hotel_content'][name.rsplit('.', 1)[1]]
    out = retained.optional_projection('row.hotelUrl', True, value)
    out['field_name'] = name
    out['path_projection'] = None
    operator = row.get('operator_ref')
    out['projection_operator_namespace'] = {'115': 'operator_115', '315': 'operator_315'}.get(operator)
    if operator not in ('115', '315'):
        raise ValueError('saved_url_operator_context')
    if operator == '315' and out['hold'] is None and type(value) is str:
        projection = n.field_projection(name, {'present': True, 'value': value}, None, 'operator_315')
        out['projection'] = projection
        if projection['projection_hold'] is not None:
            out['hold'] = projection['projection_hold']
        elif projection['origin_state'] == 'absolute_operator_host_candidate':
            # Reuse the existing strict path projector; no old URL5 execution/capture.
            import hotel_match_nonbg5_retained_url_paths_readonly_v1 as paths
            out['path_projection'] = paths.path_projection(value, projection['operator_host'])
    return out


def saved_url_rows(private, parent_result):
    rows = []
    for i, entry in enumerate(private['rows']):
        original, spec = entry['original_row'], entry['spec']
        fields = []
        for name in URL_FIELDS:
            projected = saved_url_field(original, name)
            old_index = next(j for j, f in enumerate(parent_result['rows'][i]['fields']) if f['field_name'] == name)
            projected['prior_private_value_pointer'] = {'sha256': PARENT_PRIVATE_SHA,
                'json_pointer': '/rows/' + str(i) + '/original_row/hotel_content/' + name.rsplit('.', 1)[1]}
            projected['prior_result_field_pointer'] = {'sha256': PARENT_RESULT_SHA,
                'json_pointer': f'/rows/{i}/fields/{old_index}'}
            fields.append(projected)
        holds = list(BASE_HOLDS) + [f['hold'] for f in fields if f['hold']]
        rows.append({'catalog_id': spec['catalog_id'], 'historical_local_hotel_id': spec['historical_local_hotel_id'],
            'independent_tv_hotel_id': None, 'source_namespace': 'andromeda_catalog',
            'target_namespace': 'not_established', 'operator_ref': original['operator_ref'],
            'source_file': spec['source_file'], 'source_sha256': spec['sha256'], 'json_pointer': spec['json_pointer'],
            'fields': fields, 'holds': list(dict.fromkeys(holds)), **dict.fromkeys(FALSE, False)})
    return rows


def saved_url_result(private, parent_result, descriptors, source):
    rows = saved_url_rows(private, parent_result)
    return {'schema': 'match-native-absent3-saved-urls-result/1', 'operation': URL_OP, 'batch': URL_BATCH,
        'source_sha': source, 'state': URL_STATES[1] if any(f['hold'] for r in rows for f in r['fields']) else URL_STATES[0],
        'private_input_sha256': PARENT_PRIVATE_SHA, 'parent_operation': OP, 'parent_batch': BATCH,
        'parent_source_sha': PARENT_SOURCE, 'parent_private_input_sha256': PARENT_PRIVATE_SHA,
        'parent_result_sha256': PARENT_RESULT_SHA, 'parent_metadata': descriptors, 'parent_unchanged': True,
        'requested_catalog_candidates': 3, 'rows_examined': 13, 'url_slots_examined': 26,
        'string_url_slots': 14, 'parent_files_bound': 3, 'parent_read_passes': 2,
        'parent_bytes_bound': sum(d['bytes'] for d in descriptors), 'original_raw_files_read': 0, 'cache_files_read': 0,
        'source_namespace': 'andromeda_catalog', 'target_namespace': 'not_established',
        'current_readiness': 'not_evaluated', 'normalized_snapshot_only': True,
        'original_supplier_response_proven': False, 'old_operation_replayed': False,
        'rows': rows, 'hold_counts': dict(collections.Counter(h for r in rows for h in r['holds'])),
        'no_replay': True, **dict.fromkeys(ZERO, 0), **dict.fromkeys(FALSE, False)}


def validate_saved_urls(data, receipt=None, expected_source=None, private_input=None, opdir=None):
    if opdir is None or private_input is None or not re.fullmatch(r'[a-f0-9]{40}', data.get('source_sha', '')):
        raise ValueError('saved_urls_validation_inputs')
    if expected_source is not None and data['source_sha'] != expected_source:
        raise ValueError('saved_urls_source')
    raw, descriptors = saved_parent_bytes(pathlib.Path(opdir), copied=True)
    private, parent_result, _ = validate_saved_parent(raw)
    if (n.file_bytes(pathlib.Path(opdir) / 'current-input.json', 4194304) != raw['current-input.json']
        or not n.equal_typed(private_input, private)
        or hashlib.sha256(retained.private_bytes(private_input)).hexdigest() != PARENT_PRIVATE_SHA):
        raise ValueError('saved_urls_private_binding')
    expected = saved_url_result(private, parent_result, descriptors, data['source_sha'])
    if not n.equal_typed(data, expected) or len(n.enc(data)) > 1048576:
        raise ValueError('saved_urls_result_binding')
    if receipt is not None:
        expected_receipt = {k: data[k] for k in URL_RECEIPT_KEYS} | {'result_sha256': hashlib.sha256(n.enc(data)).hexdigest()}
        if not n.equal_typed(receipt, expected_receipt):
            raise ValueError('saved_urls_receipt_binding')


def project_saved_urls(project_root, opdir):
    project_root, opdir = map(pathlib.Path, (project_root, opdir))
    home = project_root.parent.parent
    parent = home / '.anytoour-match/operations' / OP
    if (project_root != home / 'www/anytoour.ru' or opdir != home / '.anytoour-match/operations' / URL_OP
        or any(p.resolve() != p or p.is_symlink() or not p.is_dir() for p in (project_root, opdir, parent))
        or any(stat.S_IMODE(p.stat().st_mode) != 0o700 for p in (opdir, parent))):
        raise ValueError('saved_urls_execution_paths')
    source = os.environ.get('MATCH_SOURCE_SHA', '')
    if not re.fullmatch(r'[a-f0-9]{40}', source):
        raise ValueError('saved_urls_execution_source')
    reservation = n.parsed(n.file_bytes(opdir / 'reservation.json', 65536))
    expected_reservation = {'operation': URL_OP, 'source_sha': source, 'batch': URL_BATCH,
        'provider_http_calls': 0, 'maximum_writes': 0, 'state': 'reserved_before_saved_url_projection'}
    if not n.equal_typed(reservation, expected_reservation):
        raise ValueError('saved_urls_reservation')
    outputs = ('execution-started.json', 'current-input.json', 'result.json', 'receipt.json',
               *('parent-' + name for name, _ in PARENT_FILES))
    if any((opdir / name).exists() or (opdir / name).is_symlink() for name in outputs):
        raise ValueError('saved_urls_consumed_no_replay')
    n.save(opdir / 'execution-started.json', {'operation': URL_OP, 'source_sha': source, 'batch': URL_BATCH, 'no_replay': True})
    raw, before = saved_parent_bytes(parent)
    # Preserve complete three-file evidence before parsing or URL projection.
    for name, body in raw.items():
        retained.durable_bytes(opdir / ('parent-' + name), body)
    retained.durable_bytes(opdir / 'current-input.json', raw['current-input.json'])
    private, parent_result, _ = validate_saved_parent(raw)
    data = saved_url_result(private, parent_result, before, source)
    after_raw, after = saved_parent_bytes(parent)
    if not n.equal_typed(before, after) or any(raw[k] != after_raw[k] for k in raw):
        raise ValueError('saved_urls_parent_drift')
    validate_saved_urls(data, expected_source=source, private_input=private, opdir=opdir)
    digest = n.save(opdir / 'result.json', data)
    receipt = {k: data[k] for k in URL_RECEIPT_KEYS} | {'result_sha256': digest}
    validate_saved_urls(data, receipt, source, private, opdir)
    n.save(opdir / 'receipt.json', receipt)
    print(json.dumps({k: data[k] for k in ('state', 'rows_examined', 'accepted', 'written')}))
    return 0


if __name__ == '__main__':
    if sys.argv[1:] == ['--self-test']:
        manifest()
        print('MATCH_NATIVE_ABSENT3_RETAINED_FIELDS_READONLY_V1_SELFTEST_OK')
    elif sys.argv[1:] == ['--execute']:
        sys.exit(execute(pathlib.Path(os.environ['ANYTOUR_ROOT']), pathlib.Path(os.environ['MATCH_OPERATION_DIR']),
            pathlib.Path(os.environ['MATCH_CACHE_ROOT']), pathlib.Path(os.environ['MATCH_MANIFEST_PATH'])))
    elif sys.argv[1:] == ['--project-saved-urls']:
        sys.exit(project_saved_urls(pathlib.Path(os.environ['ANYTOUR_ROOT']), pathlib.Path(os.environ['MATCH_OPERATION_DIR'])))
    else:
        raise SystemExit('exact --self-test or --execute required')
