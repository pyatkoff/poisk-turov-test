"""Inspect one failed LOCAL operation via the existing executor; never replay it."""
from __future__ import annotations

import ast

MODE = 'local-profile-plan-4191'
OPERATION = 'int-andromeda-local-phase3-inspection-4191-20261007-v1'
BATCH = 'local4191-phase3-inspection-20261007'


def need(value: bool, reason: str) -> None:
    if not value:
        raise ValueError(reason)


def register_parser(core) -> None:
    original = core.parse_command

    def parse(body: str) -> dict:
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 3 or parts[1:3] != [MODE, OPERATION]:
            return original(body)
        need(len(parts) == 4 and parts[3] == BATCH, 'phase3_inspection_scope')
        need(core.SHA_RE.fullmatch(parts[0]) is not None, 'source_sha')
        return {'source_sha': parts[0], 'mode': MODE, 'operation_id': OPERATION,
                'batch': BATCH, 'maximum_writes': 0, 'provider_http_calls': 0}

    core.parse_command = parse


# Executed only in the stock executor, with its authenticated/current-head guards.
# Keeping the implementation inline avoids invoking PHP or loading a new runner.
REMOTE_HANDLER = r'''
def inspect_local_phase3(stage):
    import os, stat, json, hashlib, re
    from pathlib import Path

    current_operation = 'int-andromeda-local-phase3-inspection-4191-20261007-v1'
    current_batch = 'local4191-phase3-inspection-20261007'
    target_operation = 'int-andromeda-local-profile-mass-plan3-4191-20261002-v1'
    target_source = '1dcda2f59b757d30bd062ab5808d0b46eac09019'
    target_control = '0155ebad6ccbd9fd284f888bbc8d4ceb8026e64d'
    target_batch = 'local4191-mass-retained3-20261002'
    target_result_sha = '60496365be1a7d5a39db8a90a3f8fccdff2c82138ea6bd995a5045ca67cab93d'

    def check(value, reason):
        if not value:
            raise ValueError('phase3_inspection_' + reason)

    def encode(value):
        return json.dumps(value, sort_keys=True, separators=(',', ':'),
                          ensure_ascii=True, allow_nan=False).encode('utf-8')

    def sha(data):
        return hashlib.sha256(data).hexdigest()

    def pairs(items):
        value = {}
        for key, item in items:
            check(key not in value, 'duplicate_json_key')
            value[key] = item
        return value

    def invalid_constant(_):
        raise ValueError('phase3_inspection_nonfinite_json')

    def decode(data):
        value = json.loads(data.decode('utf-8'), object_pairs_hook=pairs,
                           parse_constant=invalid_constant)
        check(type(value) is dict, 'json_object')
        return value

    check(operation == current_operation and payload.get('mode') == 'local-profile-plan-4191'
          and payload.get('operation_id') == current_operation and payload.get('batch') == current_batch
          and type(payload.get('maximum_writes')) is int and payload['maximum_writes'] == 0
          and type(payload.get('provider_http_calls')) is int and payload['provider_http_calls'] == 0
          and isinstance(source, str) and re.fullmatch(r'[a-f0-9]{40}', source)
          and isinstance(payload.get('local_profile_control_sha'), str)
          and re.fullmatch(r'[a-f0-9]{40}', payload['local_profile_control_sha']), 'scope')
    home = Path(os.environ['HOME'])
    root = home / '.anytoour-int-executor'
    destination = root / current_operation
    old = root / target_operation
    check(home.is_absolute() and home.resolve() == home and Path(project) == home/'www/anytoour.ru'
          and Path(op) == destination and root.resolve() == root
          and old.resolve() == old and destination.resolve() == destination, 'root')
    flags = os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW
    old_fd = os.open(old, flags)
    try:
        out_fd = os.open(destination, flags)
    except BaseException:
        os.close(old_fd)
        raise
    try:
        for fd in (old_fd, out_fd):
            st = os.fstat(fd)
            check(stat.S_ISDIR(st.st_mode) and st.st_uid == os.geteuid(), 'directory')
        check(stat.S_IMODE(os.fstat(out_fd).st_mode) == 0o700, 'output_directory_mode')

        def token(st):
            return (st.st_dev, st.st_ino, st.st_size, st.st_mtime_ns, st.st_ctime_ns, st.st_mode, st.st_nlink, st.st_uid)

        def read(name, maximum, optional=False):
            try:
                fd = os.open(name, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK, dir_fd=old_fd)
            except FileNotFoundError:
                check(optional, 'required_file_missing')
                return None, None
            try:
                before = os.fstat(fd)
                check(stat.S_ISREG(before.st_mode) and before.st_nlink == 1
                      and before.st_uid == os.geteuid() and 0 < before.st_size <= maximum, 'input_file')
                parts = []
                remaining = maximum + 1
                while remaining:
                    part = os.read(fd, min(65536, remaining))
                    if not part:
                        break
                    parts.append(part)
                    remaining -= len(part)
                data = b''.join(parts)
                check(len(data) == before.st_size and token(os.fstat(fd)) == token(before), 'input_drift')
                return data, token(before)
            finally:
                os.close(fd)

        def save(name, data):
            fd = os.open(name, os.O_RDWR | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600, dir_fd=out_fd)
            try:
                os.fchmod(fd, 0o600)
                with os.fdopen(fd, 'w+b', closefd=False) as stream:
                    stream.write(data)
                    stream.flush()
                    os.fsync(fd)
                    stream.seek(0)
                    check(stream.read() == data, 'output_readback')
                st = os.fstat(fd)
                check(stat.S_ISREG(st.st_mode) and st.st_nlink == 1 and stat.S_IMODE(st.st_mode) == 0o600, 'output_mode')
                os.fsync(out_fd)
            finally:
                os.close(fd)
            return sha(data)

        identity = {'schema_version': 1, 'operation_id': current_operation, 'batch': current_batch,
                    'source_sha': source, 'control_source_sha': payload['local_profile_control_sha'],
                    'target_operation_id': target_operation, 'target_source_sha': target_source}
        save('phase3-inspection-started.json', encode(identity))
        # No glob/listdir or caller-controlled input path. Copies precede parsing.
        inputs = (
            ('reservation.json', 65536, False),
            ('installed-source.json', 131072, False),
            ('result.json', 65536, False),
            ('local-mass3-started.json', 65536, True),
            ('local-mass3-plan.json', 32 * 1024 * 1024, True),
            ('local-mass3-receipt.json', 65536, True),
        )
        originals, states, inventory = {}, {}, []
        for name, maximum, optional in inputs:
            data, previous = read(name, maximum, optional)
            originals[name], states[name] = data, previous
            digest = save('phase3-original-' + name, data) if data is not None else None
            inventory.append({'file': name, 'present': data is not None,
                              'bytes': len(data) if data is not None else 0, 'sha256': digest})
        input_sha = save('phase3-inspection-input.json', encode(identity | {'files': inventory}))
        reservation = decode(originals['reservation.json'])
        check(reservation.get('operation_id') == target_operation
              and reservation.get('source_sha') == target_source
              and reservation.get('mode') == 'local-profile-plan-4191', 'reservation')
        installed = decode(originals['installed-source.json'])
        check(installed.get('source_sha') == target_source and type(installed.get('files')) is dict
              and 'scripts/diagnostics/local_profile_mass_plan3_4191.php' in installed['files']
              and all(isinstance(k, str) and isinstance(v, str) and re.fullmatch(r'[a-f0-9]{64}', v)
                      for k, v in installed['files'].items()), 'installed_source')
        terminal = decode(originals['result.json'])
        check(sha(encode(terminal)) == target_result_sha, 'original_terminal_changed')
        started = originals['local-mass3-started.json']
        if started is not None:
            start = decode(started)
            check(start.get('operation_id') == target_operation and start.get('source_sha') == target_source, 'started_identity')
        receipt_data = originals['local-mass3-receipt.json']
        receipt = decode(receipt_data) if receipt_data is not None else None
        plan_data = originals['local-mass3-plan.json']
        candidate_count = candidate_sha = None
        scope_state = 'not_recorded'
        if plan_data is not None:
            plan = decode(plan_data)
            check(plan.get('operation_id') == target_operation and plan.get('source_sha') == target_source
                  and plan.get('control_source_sha') == target_control and plan.get('batch') == target_batch
                  and plan.get('safe_to_apply') is False and type(plan.get('rows')) is list
                  and type(plan.get('active_profiles')) is int and len(plan['rows']) == plan['active_profiles']
                  and 0 <= len(plan['rows']) <= 30000, 'plan_identity')
            selected_states = {'RETAINED_DELTA_PREPARED', 'SOURCE_MISSING', 'SOURCE_PROVENANCE_HELD',
                               'NO_DELTA_NOT_PROVEN_COMPLETE', 'OWNER_PLAN_HELD'}
            other_states = {'ALIAS_HELD', 'PROFILE_INTEGRITY_HELD', 'PRIOR_OR_EDITORIAL_HELD',
                            'SCREENED_FIELDS_PRESENT', 'HISTORICAL_366_HELD', 'D1_MANIFEST_UNKNOWN_HELD',
                            'D1_OVERLAP_HELD', 'HISTORY_UNKNOWN_HELD', 'PREDECESSOR_2000_HELD',
                            'PREDECESSOR_UNKNOWN_HELD', 'PREDECESSOR2_2000_HELD',
                            'PREDECESSOR2_UNKNOWN_HELD', 'PLAN_BOUND_DEFERRED'}
            seen, local_seen, counts, candidates = set(), set(), {}, []
            for row in plan['rows']:
                check(type(row) is dict, 'row')
                own, state = row.get('anytourHotelId'), row.get('state')
                check(type(own) is int and 0 < own <= 9007199254740991 and own not in seen
                      and isinstance(state, str) and state in selected_states | other_states, 'row_identity')
                seen.add(own)
                counts[state] = counts.get(state, 0) + 1
                if state in selected_states:
                    local = row.get('localHotelId')
                    check(type(local) is int and 0 < local <= 9007199254740991 and local not in local_seen, 'row_alias')
                    local_seen.add(local)
                    candidates.append({'anytourHotelId': own, 'localHotelId': local, 'recordedState': state})
            check(counts == plan.get('classification_counts') and len(candidates) <= 2000, 'plan_counts')
            prepared = len(candidates) - counts.get('OWNER_PLAN_HELD', 0)
            check(type(plan.get('source_plans_prepared')) is int and plan['source_plans_prepared'] == prepared, 'prepared_count')
            candidates.sort(key=lambda row: row['anytourHotelId'])
            candidate_count = len(candidates)
            candidate_sha = save('phase3-recorded-scope.json', encode(identity | {
                'plan_sha256': sha(plan_data), 'rows': candidates, 'current_identity_verified': False,
                'safe_to_apply': False, 'replay_allowed': False}))
            scope_state = 'recorded_candidates_not_current_validated'
        if receipt is not None:
            check(plan_data is not None and receipt.get('operation_id') == target_operation
                  and receipt.get('source_sha') == target_source and receipt.get('control_source_sha') == target_control
                  and receipt.get('batch') == target_batch and receipt.get('private_plan_sha256') == sha(plan_data)
                  and receipt.get('safe_to_apply') is False, 'receipt_identity')
        # Stat checks detect changes across the bounded snapshot without replaying reads.
        for name, _, _ in inputs:
            try:
                now = token(os.stat(name, dir_fd=old_fd, follow_symlinks=False))
            except FileNotFoundError:
                now = None
            check(now == states[name], 'snapshot_drift')
        public = identity | {
            'state': 'inspected_read_only', 'files': inventory, 'input_sha256': input_sha,
            'recorded_scope_state': scope_state, 'recorded_candidate_profiles': candidate_count,
            'recorded_scope_sha256': candidate_sha, 'original_files_unchanged': True,
            'original_status': 'unknown_no_replay', 'original_supplier_calls': 'unknown',
            'original_database_writes': 'unknown', 'safe_to_apply': False, 'replay_allowed': False,
            'automatic_successor_allowed': False, 'supplier_calls': 0, 'provider_http_calls': 0,
            'database_reads': 0, 'database_writes': 0, 'profile_writes': 0, 'mapping_writes': 0,
            'schema_writes': 0, 'lead_calls': 0, 'booking_calls': 0,
        }
        save('phase3-inspection-receipt.json', encode(public))
        return public
    finally:
        os.close(out_fd)
        os.close(old_fd)
'''


def activate(core, command: dict, plan) -> None:
    need(command.get('operation_id') == OPERATION, 'phase3_inspection_operation')
    expected = core.parse_command(core.PREFIX + ' '.join([
        str(command.get('source_sha', '')), MODE, OPERATION, BATCH]))
    need(command == expected, 'phase3_inspection_authorized_shape')
    remote = plan.remote_with_plan(core)
    anchor = "result['local_profile_plan']=run_local_profile_plan_4191(stage)"
    need(remote.count(anchor) == 1, 'phase3_inspection_dispatch_drift')
    remote = REMOTE_HANDLER + '\n' + remote.replace(anchor,
        "result['local_profile_plan']=inspect_local_phase3(stage)", 1)
    ast.parse(remote)
    compile(remote, "<local-phase3-stock-remote>", "exec")
    core.REMOTE = remote
    core.bundle_source = plan.bundle_source
