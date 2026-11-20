#!/usr/bin/env python3
"""Offline admission, real filesystem reader and redaction tests; no server calls."""
import ast
import contextlib
import copy
import hashlib
import importlib.util
import io
import json
import os
import re
from pathlib import Path
import socket
import stat
import subprocess
import sys
import tempfile
import unittest
import urllib.request
from unittest import mock

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / 'scripts/deploy/search3_root_gateway_inspect.py'
SPEC = importlib.util.spec_from_file_location('readiness_inspector', SCRIPT)
M = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(M)


def fixture():
    control = 'a' * 40
    event = {'repository': {'full_name': M.REPO}, 'action': 'created', 'issue': {'number': 4709},
             'comment': {'id': 987, 'body': f'{M.READINESS_PREFIX} {control} {M.READINESS_OPERATION}',
                         'user': {'id': 226193297}, 'author_association': 'OWNER'}}
    env = {'GITHUB_REPOSITORY': M.REPO, 'GITHUB_REF': 'refs/heads/main', 'GITHUB_SHA': control,
           'GITHUB_WORKFLOW_REF': f'{M.REPO}/{M.WORKFLOW}@refs/heads/main',
           'GITHUB_ACTOR': 'pyatkoff', 'GITHUB_TRIGGERING_ACTOR': 'pyatkoff',
           'GITHUB_EVENT_NAME': 'issue_comment', 'GITHUB_RUN_ATTEMPT': '1', 'GITHUB_RUN_ID': '123456'}
    return event, env


def api_fixture(path):
    event, env = fixture()
    if path == '/git/ref/heads/main':
        return {'object': {'sha': env['GITHUB_SHA']}}
    if path == '/git/ref/heads/release/search3-production-ready-v1':
        return {'object': {'sha': M.READINESS_RELEASE}}
    if path == '/pulls/5118':
        return {'number': 5118, 'state': 'open', 'merged': False,
                'head': {'sha': M.READINESS_SOURCE, 'repo': {'full_name': M.REPO}},
                'base': {'ref': 'release/search3-production-ready-v1', 'repo': {'full_name': M.REPO}}}
    if path == '/git/commits/' + M.READINESS_SOURCE:
        return {'sha': M.READINESS_SOURCE, 'tree': {'sha': M.READINESS_TREE}}
    if path == '/issues/4709/comments?per_page=100&page=1':
        return [copy.deepcopy(event['comment'])]
    raise AssertionError('unexpected GitHub path')


def good_result(target):
    rows = [{'path': path, 'state': 'present', 'sha256': '0' * 64, 'size': 20, 'mode': '0644'}
            for path in M.READINESS_TARGETS[target]['files']]
    return {'schema_version': 1, 'status': 'inventory_observed',
            'before': rows, 'after': copy.deepcopy(rows)}


def make_files(home, target):
    root = home.joinpath(*M.READINESS_TARGETS[target]['root'])
    for path in M.READINESS_TARGETS[target]['files']:
        file = root / path
        file.parent.mkdir(parents=True, exist_ok=True)
        # If this PHP were executed, it would create a forbidden marker.
        file.write_bytes(b'<?php file_put_contents("MUST_NOT_EXIST", "PRIVATE_FILE_BODY"); ?>\n')
        file.chmod(0o640)
    (root / 'config.php').write_text('PRIVATE_CONFIG_MUST_NOT_BE_READ')
    return root


def native_reader(home, target):
    answer = subprocess.run([sys.executable, '-I', '-S', '-B', '-'],
                            input=M.readiness_remote_program(target).encode(),
                            stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True, timeout=10,
                            env={'HOME': str(home), 'PATH': '/usr/bin:/bin'})
    if answer.stderr:
        raise AssertionError('unexpected reader diagnostic')
    return json.loads(answer.stdout)


def in_process_reader(home, target):
    with mock.patch.dict(os.environ, {'HOME': str(home)}), contextlib.redirect_stdout(io.StringIO()) as output:
        exec(compile(M.readiness_remote_program(target), '<fixed-readonly-program>', 'exec'), {})
    return json.loads(output.getvalue())


class Admission(unittest.TestCase):
    def test_all_event_guards_and_old_command_separation(self):
        event, env = fixture()
        self.assertEqual(M.readiness_event(event, env), ('a' * 40, 987))
        changes = {'GITHUB_REPOSITORY': 'other/repo', 'GITHUB_REF': 'refs/heads/feature',
                   'GITHUB_SHA': 'b' * 40, 'GITHUB_WORKFLOW_REF': M.WORKFLOW,
                   'GITHUB_ACTOR': 'other', 'GITHUB_TRIGGERING_ACTOR': 'other',
                   'GITHUB_EVENT_NAME': 'workflow_dispatch', 'GITHUB_RUN_ATTEMPT': '2',
                   'GITHUB_RUN_ID': 'private-or-missing'}
        for key, value in changes.items():
            with self.subTest(key=key), self.assertRaises(RuntimeError):
                M.readiness_event(event, {**env, key: value})
        variants = []
        for key, value in [('action', 'edited'), ('repository', {'full_name': 'other/repo'}),
                           ('issue', {'number': 4217}), ('issue', {'number': 4709, 'pull_request': {'url': 'x'}})]:
            changed = copy.deepcopy(event); changed[key] = value; variants.append(changed)
        for key, value in [('id', True), ('user', {'id': 1}), ('author_association', 'MEMBER'),
                           ('body', event['comment']['body'] + ' '),
                           ('body', f'{M.COMMAND_PREFIX} {env["GITHUB_SHA"]} {M.OPERATION}'),
                           ('body', f'{M.READINESS_PREFIX} {env["GITHUB_SHA"]} readiness-20261120-v1'),
                           ('body', event['comment']['body'].replace(M.READINESS_OPERATION, 'another-operation'))]:
            changed = copy.deepcopy(event); changed['comment'][key] = value; variants.append(changed)
        for changed in variants:
            with self.subTest(changed=changed), self.assertRaises(RuntimeError):
                M.readiness_event(changed, env)

    def test_pins_paginated_unique_operation_and_final_main_recheck(self):
        with mock.patch.object(M, 'api', side_effect=api_fixture) as api:
            M.readiness_admission('a' * 40, 987)
        self.assertEqual(api.call_args_list[-2].args[0], '/git/ref/heads/main')
        for variant in ('main', 'release', 'pr', 'source_tree', 'duplicate', 'current_edit',
                        'current_owner', 'missing', 'shape', 'main_moves_during_history', 'incomplete'):
            calls = []
            def bad(path):
                calls.append(path)
                if variant == 'main' and path == '/git/ref/heads/main':
                    return {'object': {'sha': 'b' * 40}}
                if variant == 'release' and 'heads/release/' in path:
                    return {'object': {'sha': 'b' * 40}}
                if variant == 'pr' and path == '/pulls/5118':
                    value = api_fixture(path); value['head']['sha'] = 'b' * 40; return value
                if variant == 'source_tree' and path.startswith('/git/commits/'):
                    return {'sha': M.READINESS_SOURCE, 'tree': {'sha': 'b' * 40}}
                if variant == 'main_moves_during_history' and path == '/git/ref/heads/main' and calls.count(path) > 1:
                    return {'object': {'sha': 'b' * 40}}
                if path.startswith('/issues/'):
                    if variant == 'shape': return {'body': 'not a page'}
                    if variant == 'missing': return []
                    if variant == 'incomplete':
                        page = int(path.split('page=')[-1])
                        return [{'id': page * 1000 + i, 'body': 'ordinary'} for i in range(100)]
                    value = api_fixture(path)
                    if variant == 'duplicate':
                        other = copy.deepcopy(value[0]); other['id'] = 986
                        other['body'] = other['body'].replace('a' * 40, 'b' * 40)
                        return [other, *value]
                    if variant == 'current_edit': value[0]['body'] += ' '
                    if variant == 'current_owner': value[0]['user']['id'] = 1
                    return value
                return api_fixture(path)
            with self.subTest(variant=variant), mock.patch.object(M, 'api', side_effect=bad), self.assertRaises(RuntimeError):
                M.readiness_admission('a' * 40, 987)
        def paginated(path):
            if path == '/issues/4709/comments?per_page=100&page=1':
                return [{'id': i + 1, 'body': '/preflight-search3-root-gateway old sealed operation'} for i in range(100)]
            if path == '/issues/4709/comments?per_page=100&page=2':
                return [fixture()[0]['comment']]
            return api_fixture(path)
        with mock.patch.object(M, 'api', side_effect=paginated):
            M.readiness_admission('a' * 40, 987)
        def old_operation_history(path):
            if path == '/issues/4709/comments?per_page=100&page=1':
                old = copy.deepcopy(fixture()[0]['comment']); old['id'] = 986
                old['body'] = f'{M.READINESS_PREFIX} {"b" * 40} readiness-20261120-v1'
                return [old, fixture()[0]['comment']]
            return api_fixture(path)
        with mock.patch.object(M, 'api', side_effect=old_operation_history):
            M.readiness_admission('a' * 40, 987)

    def test_consumed_v1_is_rejected_before_admission_keys_or_connection(self):
        event, env = fixture()
        event['comment']['body'] = f'{M.READINESS_PREFIX} {env["GITHUB_SHA"]} readiness-20261120-v1'
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'event.json'; path.write_text(json.dumps(event))
            with mock.patch.dict(os.environ, {**env, 'RUNNER_TEMP': directory, 'GITHUB_EVENT_PATH': str(path)}), \
                 mock.patch.object(M, 'readiness_admission') as admission, \
                 mock.patch.object(M, 'read_readiness_remote') as remote, \
                 mock.patch.object(M.subprocess, 'run') as network, \
                 contextlib.redirect_stdout(io.StringIO()), self.assertRaisesRegex(RuntimeError, 'readiness_not_observed'):
                M.production_readiness()
            admission.assert_not_called(); remote.assert_not_called(); network.assert_not_called()
            result = json.loads((Path(directory) / 'search3-production-readiness/evidence.json').read_text())
            self.assertEqual(result['failure_phase'], 'admission')
            self.assertEqual(result['targets'], {})
            self.assertFalse(result['installation_ready'])

    def test_admission_failure_never_loads_keys_or_connects_and_is_redacted(self):
        event, env = fixture()
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'event.json'; path.write_text(json.dumps(event))
            with mock.patch.dict(os.environ, {**env, 'RUNNER_TEMP': directory, 'GITHUB_EVENT_PATH': str(path)}), \
                 mock.patch.object(M, 'readiness_admission', side_effect=RuntimeError('PRIVATE_ADMISSION_DETAIL')), \
                 mock.patch.object(M, 'read_readiness_remote') as remote, \
                 mock.patch.object(M.subprocess, 'run') as network, \
                 contextlib.redirect_stdout(io.StringIO()) as output, self.assertRaisesRegex(RuntimeError, 'readiness_not_observed'):
                M.production_readiness()
            remote.assert_not_called(); network.assert_not_called()
            result = json.loads((Path(directory) / 'search3-production-readiness/evidence.json').read_text())
            self.assertEqual(result['failure_phase'], 'admission')
            self.assertEqual(result['run_id'], 123456)
            self.assertEqual(result['command_comment_id'], 987)
            self.assertNotIn('PRIVATE_ADMISSION_DETAIL', output.getvalue())
            self.assertFalse(result['installation_ready'])


class Reader(unittest.TestCase):
    def test_native_program_reads_exact_files_without_execution_or_changed_bytes_modes(self):
        for target in M.READINESS_TARGETS:
            with self.subTest(target=target), tempfile.TemporaryDirectory() as directory:
                home = Path(directory); make_files(home, target)
                def inventory():
                    return {str(p.relative_to(home)): (p.read_bytes(), p.stat().st_mode,
                                                       p.stat().st_mtime_ns, p.stat().st_ctime_ns)
                            for p in home.rglob('*') if p.is_file()}
                before = inventory(); result = native_reader(home, target); after = inventory()
                self.assertEqual(before, after)
                self.assertEqual(result['status'], 'inventory_observed')
                self.assertEqual(result['before'], result['after'])
                self.assertEqual(len(result['before']), len(M.READINESS_TARGETS[target]['files']))
                self.assertEqual(M.validate_readiness_result(result, target), result)
                self.assertNotIn('PRIVATE', json.dumps(result))
                self.assertNotIn(str(home), json.dumps(result))
                self.assertFalse(list(home.rglob('MUST_NOT_EXIST')))
                self.assertFalse(list(home.rglob('__pycache__')))

    @unittest.skipIf(os.geteuid() == 0, 'requires unprivileged filesystem permission enforcement')
    def test_execute_only_ancestor_allows_exact_read_without_permission_changes(self):
        with tempfile.TemporaryDirectory() as directory:
            home = Path(directory) / 'home'; home.mkdir(); root = make_files(home, 'public')
            expected = {path: hashlib.sha256((root / path).read_bytes()).hexdigest()
                        for path in M.READINESS_TARGETS['public']['files']}
            home.chmod(0o111)
            try:
                with self.assertRaises(PermissionError):
                    descriptor = os.open(home, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
                    os.close(descriptor)
                result = native_reader(home, 'public')
                self.assertEqual(result['status'], 'inventory_observed')
                self.assertEqual(result['before'], result['after'])
                self.assertEqual({row['path']: row['sha256'] for row in result['before']}, expected)
                self.assertEqual(stat.S_IMODE(home.stat().st_mode), 0o111)
                self.assertEqual({path: hashlib.sha256((root / path).read_bytes()).hexdigest()
                                  for path in expected}, expected)
            finally:
                home.chmod(0o700)

    @unittest.skipIf(os.geteuid() == 0, 'requires unprivileged filesystem permission enforcement')
    def test_no_search_permission_and_unreadable_leaf_still_fail_closed(self):
        for variant, stage in (('ancestor', 'ancestor_stat'), ('leaf', 'file_open')):
            with self.subTest(variant=variant), tempfile.TemporaryDirectory() as directory:
                home = Path(directory) / 'home'; home.mkdir(); root = make_files(home, 'public')
                guarded = home if variant == 'ancestor' else root / 'index.php'
                mode = stat.S_IMODE(guarded.stat().st_mode); guarded.chmod(0)
                try:
                    result = native_reader(home, 'public')
                    self.assertEqual(stat.S_IMODE(guarded.stat().st_mode), 0)
                finally:
                    guarded.chmod(mode)
                self.assertEqual(result['status'], 'read_error')
                self.assertEqual(result['error_type'], 'PermissionError')
                self.assertEqual(result['reason'], 'read_failed')
                self.assertEqual(result['read_stage'], stage)
                self.assertEqual(result['before'], [])
                M.validate_readiness_result(result, 'public')
                self.assertNotIn(str(home), json.dumps(result))

    def test_missing_path_handle_support_fails_before_filesystem_reads(self):
        with tempfile.TemporaryDirectory() as directory:
            home = Path(directory); make_files(home, 'public')
            with mock.patch.object(os, 'O_PATH'), mock.patch.object(os, 'open') as opened:
                del os.O_PATH
                result = in_process_reader(home, 'public')
            opened.assert_not_called()
            self.assertEqual(result['status'], 'read_error')
            self.assertEqual(result['reason'], 'unsupported_platform')
            self.assertEqual(result['read_stage'], 'validation')
            M.validate_readiness_result(result, 'public')

    def test_actual_reader_has_network_process_and_remote_write_tripwires(self):
        with tempfile.TemporaryDirectory() as directory:
            home = Path(directory); make_files(home, 'public')
            original_open = os.open
            def read_open(path, flags, *args, **kwargs):
                self.assertEqual(flags & os.O_ACCMODE, os.O_RDONLY)
                self.assertFalse(flags & (os.O_CREAT | os.O_TRUNC | os.O_APPEND))
                if flags & os.O_DIRECTORY: self.assertTrue(flags & os.O_PATH)
                else: self.assertFalse(flags & os.O_PATH)
                return original_open(path, flags, *args, **kwargs)
            with contextlib.ExitStack() as stack:
                stack.enter_context(mock.patch.object(os, 'open', side_effect=read_open))
                for module, name in ((socket, 'socket'), (subprocess, 'run'), (os, 'system'),
                                     (urllib.request, 'urlopen'), (os, 'mkdir'), (os, 'unlink'),
                                     (os, 'rename'), (os, 'chmod'), (os, 'write')):
                    stack.enter_context(mock.patch.object(module, name, side_effect=AssertionError('forbidden effect')))
                result = in_process_reader(home, 'public')
            self.assertEqual(result['status'], 'inventory_observed')
        tree = ast.parse(M.READINESS_REMOTE_BODY)
        imports = {name.name for node in ast.walk(tree) if isinstance(node, ast.Import) for name in node.names}
        self.assertEqual(imports, {'hashlib', 'json', 'os', 'stat'})
        self.assertFalse(any(isinstance(node, ast.ImportFrom) for node in ast.walk(tree)))

    def test_absent_files_and_missing_nested_directory_are_explicit_not_readiness(self):
        with tempfile.TemporaryDirectory() as directory:
            home = Path(directory); root = make_files(home, 'public')
            (root / 'poisk-turov-old/index.php').unlink(); (root / 'poisk-turov-old').rmdir()
            result = native_reader(home, 'public')
            self.assertEqual(result['status'], 'inventory_observed')
            summary = M.readiness_comparisons(M.validate_readiness_result(result, 'public'), 'public')
            self.assertTrue(summary['required_present'])
            self.assertEqual(summary['unmapped_reference_paths'], ['poisk-turov-old/index.php'])
            (root / 'api-v2.php').unlink()
            result = native_reader(home, 'public')
            self.assertEqual(result['status'], 'inventory_observed')
            self.assertFalse(M.readiness_comparisons(result, 'public')['required_present'])
            self.assertEqual(next(x for x in result['before'] if x['path'] == 'api-v2.php'),
                             {'path': 'api-v2.php', 'state': 'absent'})

    def test_unsafe_ancestors_leaf_hardlinks_special_and_oversize_fail_closed(self):
        for variant in ('leaf_symlink', 'parent_symlink', 'home_symlink', 'hardlink', 'fifo', 'directory', 'oversize'):
            with self.subTest(variant=variant), tempfile.TemporaryDirectory() as directory:
                home = Path(directory) / 'home'; home.mkdir(); root = make_files(home, 'public')
                entry = root / 'index.php'; observed_home = home
                if variant == 'leaf_symlink':
                    entry.unlink(); entry.symlink_to(root / 'config.php')
                if variant == 'parent_symlink':
                    nested = root / '_preview'; nested.rename(home / 'private-preview')
                    nested.symlink_to(home / 'private-preview', target_is_directory=True)
                if variant == 'home_symlink':
                    observed_home = Path(directory) / 'linked-home'; observed_home.symlink_to(home, target_is_directory=True)
                if variant == 'hardlink': os.link(entry, home / 'private-hardlink')
                if variant == 'fifo': entry.unlink(); os.mkfifo(entry)
                if variant == 'directory': entry.unlink(); entry.mkdir()
                if variant == 'oversize':
                    with entry.open('wb') as stream: stream.truncate(M.READINESS_FILE_BYTES + 1)
                result = native_reader(observed_home, 'public')
                self.assertEqual(result['status'], 'read_error')
                self.assertIn(result['reason'], ('unsafe_parent', 'unsafe_file', 'file_too_large'))
                M.validate_readiness_result(result, 'public')
                self.assertNotIn('PRIVATE', json.dumps(result))
                self.assertNotIn(str(home), json.dumps(result))

    def test_changes_during_read_and_same_bytes_inode_replacement_are_rejected(self):
        for variant in ('changed_bytes', 'replaced_inode', 'between_passes'):
            with self.subTest(variant=variant), tempfile.TemporaryDirectory() as directory:
                home = Path(directory); root = make_files(home, 'public'); file = root / 'index.php'
                original_read = os.read; changed = False; eof_count = 0
                def racing_read(fd, count):
                    nonlocal changed, eof_count
                    value = original_read(fd, count)
                    if not value: eof_count += 1
                    trigger = (eof_count == len(M.READINESS_TARGETS['public']['files'])
                               if variant == 'between_passes' else bool(value))
                    if trigger and not changed:
                        changed = True
                        if variant == 'changed_bytes': file.write_bytes(b'PRIVATE_CHANGED_BYTES')
                        else:
                            data = file.read_bytes(); file.unlink(); file.write_bytes(data)
                    return value
                with mock.patch.object(os, 'read', side_effect=racing_read):
                    result = in_process_reader(home, 'public')
                self.assertEqual(result['status'], 'read_error')
                self.assertEqual(result['reason'], 'file_changed')
                self.assertNotIn('PRIVATE', json.dumps(result))

    def test_sibling_churn_is_ignored_but_ancestor_replacement_is_rejected(self):
        for replace_parent in (False, True):
            with self.subTest(replace_parent=replace_parent), tempfile.TemporaryDirectory() as directory:
                home = Path(directory); root = make_files(home, 'public')
                original_read = os.read; changed = False
                def racing_read(fd, count):
                    nonlocal changed
                    value = original_read(fd, count)
                    if value and not changed:
                        changed = True
                        if replace_parent:
                            root.rename(root.parent / 'moved-runtime'); root.mkdir()
                        else:
                            sibling = root / 'unlisted-sibling.tmp'; sibling.write_bytes(b'PRIVATE_SIBLING'); sibling.unlink()
                    return value
                with mock.patch.object(os, 'read', side_effect=racing_read):
                    result = in_process_reader(home, 'public')
                self.assertEqual(result['status'], 'read_error' if replace_parent else 'inventory_observed')
                if replace_parent: self.assertEqual(result['reason'], 'file_changed')
                self.assertNotIn('PRIVATE', json.dumps(result))

    def test_permission_and_unexpected_reader_errors_return_only_typed_failure(self):
        for error in (PermissionError('PRIVATE_PATH'), OSError('PRIVATE_HOST'), ValueError('PRIVATE_BYTES')):
            with self.subTest(kind=type(error).__name__), tempfile.TemporaryDirectory() as directory:
                home = Path(directory); make_files(home, 'public')
                with mock.patch.object(os, 'open', side_effect=error):
                    result = in_process_reader(home, 'public')
                self.assertEqual(result['reason'], 'read_failed')
                self.assertEqual(result['error_type'], type(error).__name__)
                self.assertEqual(result['read_stage'], 'ancestor_open')
                self.assertNotIn('PRIVATE', json.dumps(result))
                M.validate_readiness_result(result, 'public')


class ResultsAndTransport(unittest.TestCase):
    def test_strict_result_validation_rejects_private_fields_paths_and_forged_success(self):
        good = good_result('public')
        self.assertEqual(M.validate_readiness_result(good, 'public'), good)
        variants = [{**good, 'private': 'PRIVATE_VALUE'}, {**good, 'before': []},
                    {**good, 'schema_version': True}, {**good, 'status': 'install_ready'}]
        for field, value in [('path', '../config.php'), ('sha256', True), ('size', True),
                             ('size', M.READINESS_FILE_BYTES + 1), ('mode', '07777'), ('state', 'unknown')]:
            changed = copy.deepcopy(good); changed['before'][0][field] = value; variants.append(changed)
        changed = copy.deepcopy(good); changed['after'][0]['sha256'] = '1' * 64; variants.append(changed)
        for changed in variants:
            with self.subTest(changed=changed), self.assertRaises(RuntimeError):
                M.validate_readiness_result(changed, 'public')
        failed = {'schema_version': 1, 'status': 'read_error', 'before': [], 'after': [],
                  'error_type': 'PermissionError', 'reason': 'read_failed', 'read_stage': 'ancestor_open'}
        self.assertEqual(M.validate_readiness_result(failed, 'public'), failed)
        for key in ('error_type', 'reason', 'read_stage'):
            with self.assertRaises(RuntimeError):
                M.validate_readiness_result({**failed, key: 'PRIVATE_ERROR'}, 'public')

    def test_reference_difference_does_not_block_observation_and_provenance_is_separate(self):
        result = good_result('public')
        reference = M.READINESS_REFERENCES['next_payload']['hashes']
        for phase in ('before', 'after'):
            for row in result[phase]:
                if row['path'] in reference: row['sha256'] = reference[row['path']]
        summary = M.readiness_comparisons(M.validate_readiness_result(result, 'public'), 'public')
        self.assertTrue(summary['required_present'])
        self.assertTrue(all(x['comparison'] == 'matches_reference'
                            for x in summary['reference_results']['next_payload']['files']))
        self.assertTrue(any(x['comparison'] == 'differs_from_reference'
                            for x in summary['reference_results']['int_retained_receipt']['files']))
        self.assertEqual(summary['reference_results']['int_retained_receipt']['kind'], 'historical_installed_pin_map')

    def test_existing_ssh_tuples_fixed_program_no_retry_and_key_cleanup(self):
        for target in M.READINESS_TARGETS:
            prefix = M.READINESS_TARGETS[target]['env']
            for failure, expected in [(None, None), (b'PRIVATE Permission denied (publickey).', 'auth_failed'),
                                      (b'PRIVATE Host key verification failed.', 'host_key_failed'),
                                      (b'PRIVATE Connection refused', 'connection_failed'),
                                      (b'Load key PRIVATE: invalid format', 'invalid_credentials'),
                                      (b'bash: python3: Permission denied PRIVATE', 'read_failed'),
                                      (b'PRIVATE unknown failure', 'read_failed'), ('timeout', 'timeout')]:
                with self.subTest(target=target, expected=expected), tempfile.TemporaryDirectory() as directory:
                    work = Path(directory)
                    def process(args, **kwargs):
                        self.assertEqual(stat.S_IMODE((work / (target + '-key')).stat().st_mode), 0o600)
                        if args[0] == 'ssh-keyscan': return mock.Mock(stdout=b'synthetic known-host record')
                        self.assertEqual(args[0], 'ssh'); self.assertEqual(args[-1], 'python3 -I -S -B -')
                        self.assertIn('StrictHostKeyChecking=yes', args)
                        self.assertIn('IdentitiesOnly=yes', args)
                        self.assertEqual(kwargs['input'], M.readiness_remote_program(target).encode())
                        if failure == 'timeout': raise subprocess.TimeoutExpired(args, 60, stderr=b'PRIVATE_TIMEOUT')
                        if failure is not None: raise subprocess.CalledProcessError(255, args, stderr=failure)
                        return mock.Mock(stdout=json.dumps(good_result(target)).encode())
                    env = {prefix + '_HOST': 'synthetic.example', prefix + '_USER': 'synthetic', prefix + '_KEY': 'c3ludGhldGlj'}
                    with mock.patch.dict(os.environ, env), mock.patch.object(M.subprocess, 'run', side_effect=process) as calls:
                        if failure is None:
                            self.assertEqual(M.read_readiness_remote(work, target)['status'], 'inventory_observed')
                        else:
                            with self.assertRaises(M.ReadinessTransportError) as caught:
                                M.read_readiness_remote(work, target)
                            self.assertEqual(caught.exception.reason, expected)
                            self.assertNotIn('PRIVATE', str(caught.exception))
                    self.assertEqual(calls.call_count, 2)
                    self.assertEqual(list(work.iterdir()), [])

    def test_invalid_credentials_never_start_network(self):
        with tempfile.TemporaryDirectory() as directory, \
             mock.patch.dict(os.environ, {'PREVIEW_HOST': 'bad; host', 'PREVIEW_USER': 'user', 'PREVIEW_KEY': 'secret'}), \
             mock.patch.object(M.subprocess, 'run') as network, self.assertRaises(M.ReadinessTransportError) as caught:
            M.read_readiness_remote(Path(directory), 'public')
        self.assertEqual(caught.exception.reason, 'invalid_credentials'); network.assert_not_called()

    def test_public_only_receipt_preserves_failure_stage_and_redacts_invalid_reply(self):
        for variant in ('public_auth', 'public_private_reply', 'public_read_error', 'success'):
            with self.subTest(variant=variant), tempfile.TemporaryDirectory() as directory:
                event, env = fixture(); path = Path(directory) / 'event.json'; path.write_text(json.dumps(event))
                def remote(work, target):
                    self.assertEqual(target, 'public')
                    if variant == 'public_auth': raise M.ReadinessTransportError('auth_failed')
                    if variant == 'public_private_reply': return {'PRIVATE': 'PRIVATE_BODY'}
                    if variant == 'public_read_error':
                        return {'schema_version': 1, 'status': 'read_error', 'before': [], 'after': [],
                                'error_type': 'PermissionError', 'reason': 'read_failed', 'read_stage': 'file_open'}
                    return good_result(target)
                with mock.patch.dict(os.environ, {**env, 'RUNNER_TEMP': directory, 'GITHUB_EVENT_PATH': str(path)}), \
                     mock.patch.object(M, 'readiness_admission'), mock.patch.object(M, 'read_readiness_remote', side_effect=remote) as calls, \
                     contextlib.redirect_stdout(io.StringIO()) as output:
                    if variant == 'success': M.production_readiness()
                    else:
                        with self.assertRaisesRegex(RuntimeError, 'readiness_not_observed'): M.production_readiness()
                evidence = json.loads((Path(directory) / 'search3-production-readiness/evidence.json').read_text())
                self.assertEqual([x.args[1] for x in calls.call_args_list], ['public'])
                self.assertEqual(list(evidence['targets']), ['public'])
                self.assertEqual(evidence['status'], 'inventory_observed' if variant == 'success' else 'readiness_error')
                if variant == 'success': self.assertEqual(evidence['targets']['public']['status'], 'inventory_observed')
                if variant == 'public_auth': self.assertEqual(evidence['targets']['public']['reason'], 'auth_failed')
                if variant == 'public_read_error': self.assertEqual(evidence['targets']['public']['read_stage'], 'file_open')
                self.assertNotIn('PRIVATE', output.getvalue()); self.assertFalse(evidence['installation_ready'])
                self.assertLess(len(output.getvalue().encode()), M.READINESS_EVIDENCE_BYTES)
                self.assertEqual([evidence[k] for k in ('server_writes', 'supplier_calls', 'db_reads', 'db_writes', 'real_leads')], [0] * 5)


class FrozenAndWorkflow(unittest.TestCase):
    def test_old_inspector_prefix_and_pins_are_byte_exact(self):
        prefix = SCRIPT.read_bytes().split(b'# This separate read-only operation', 1)[0]
        self.assertEqual(hashlib.sha256(prefix).hexdigest(), '61fc14bdaf2127b1a1450d1e91810d80e1766f49fa4540015bb2887222a6221c')
        self.assertEqual(M.OPERATION, 'preflight-20261018-fcfe503e-v1')
        self.assertEqual(M.ISSUE, 4217)
        self.assertEqual(M.SOURCE, 'fcfe503e33f902ec19a9eff8cfc7985b1c242f04')
        self.assertEqual(M.RELEASE, '6bb834a586c231c3f06a6d6e918f8280d2b3f587')

    def test_fixed_code_inventory_has_exact_scope_and_no_secret_data_paths(self):
        self.assertEqual(list(M.READINESS_TARGETS), ['public'])
        self.assertEqual(len(M.READINESS_TARGETS['public']['files']), 23)
        self.assertEqual(M.READINESS_TARGETS['public']['root'], ('www', 'anytoour.ru'))
        self.assertEqual(M.READINESS_TARGETS['public']['env'], 'PREVIEW')
        # Freeze the prior operation's public allowlist; this operation only removes the legacy host.
        scope = json.dumps(M.READINESS_TARGETS['public'], sort_keys=True, separators=(',', ':')).encode()
        self.assertEqual(hashlib.sha256(scope).hexdigest(), 'ad3f2c10134038fd10d28b2d778c6f7126f9d201143d5b19841f248e2d049820')
        self.assertEqual(len(M.READINESS_INT_FILES), 14)
        for target in M.READINESS_TARGETS.values():
            self.assertEqual(len(target['files']), len(set(target['files'])))
            for path in target['files']:
                self.assertNotIn('..', Path(path).parts)
                self.assertFalse(path.startswith('/'))
                self.assertTrue(path.endswith(('.php', '.js')))
                self.assertNotIn(path.split('/')[-1], ('config.php', '.anex-private.php', 'site_conf.php'))
        for reference in M.READINESS_REFERENCES.values():
            for path, digest in reference['hashes'].items():
                self.assertIn(path, M.READINESS_TARGETS[reference['target']]['files'])
                self.assertRegex(digest, r'^[a-f0-9]{64}$')
        with self.assertRaisesRegex(RuntimeError, 'readiness_target'):
            M.readiness_remote_program('legacy')

    def test_new_operation_pins_exact_reviewed_candidate(self):
        self.assertEqual(M.READINESS_OPERATION, 'readiness-public-int-20261120-v2')
        self.assertEqual(M.READINESS_SOURCE, '8d5fc37fdc7baca67f0a26444a4548f8c7ded38c')
        self.assertEqual(M.READINESS_TREE, 'aa1c25651321433c6f711fdb8f81dc438fb84444')
        self.assertEqual(M.READINESS_RELEASE, '63844d4093e82dbe2810294514fe2a881df981ed')

    def test_workflow_uses_new_bounded_job_and_tests_before_secrets(self):
        workflow = (ROOT / M.WORKFLOW).read_text()
        job = re.split(r'\n  [A-Za-z][A-Za-z0-9_-]*:\n', workflow.split('  readiness-inspect:\n', 1)[1], maxsplit=1)[0]
        for expected in ('group: deploy-anytoour-production', 'cancel-in-progress: false',
                         'github.event.issue.number == 4709', 'github.run_attempt == 1',
                         "github.actor == 'pyatkoff'", "github.triggering_actor == 'pyatkoff'",
                         'github.event.comment.user.id == 226193297', "github.event.comment.author_association == 'OWNER'",
                         'github.workflow_ref', M.READINESS_OPERATION,
                         'scripts/deploy/search3_root_gateway_inspect.py production-readiness',
                         'search3-production-readiness/evidence.json'):
            self.assertIn(expected, job)
        self.assertLess(job.index('tests/search3_production_readiness_inspect_test.py'), job.index('PREVIEW_KEY:'))
        self.assertEqual(workflow.count('scripts/deploy/search3_root_gateway_inspect.py production-readiness'), 1)
        self.assertIn('  production-readiness-contracts:', workflow)
        self.assertNotIn('readiness-20261120-v1', job)
        self.assertNotIn('LEGACY_', job)
        for key in ('ANYTOOUR_DEPLOY_HOST', 'ANYTOOUR_DEPLOY_USER', 'ANYTOOUR_DEPLOY_SSH_KEY'):
            self.assertIn('secrets.' + key, job)
        self.assertNotIn('workflow_dispatch', workflow)
        self.assertNotIn('scp ', job)
        self.assertNotIn('request.json', job)


if __name__ == '__main__':
    unittest.main()
