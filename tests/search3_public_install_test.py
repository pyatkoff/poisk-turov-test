#!/usr/bin/env python3
"""Offline actual-controller admission and native filesystem transaction tests."""
import base64
import contextlib
import copy
import io
import json
import os
from pathlib import Path
import stat
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch
import zipfile

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts/deploy'))
import search3_public_install as m

CONTROL = 'a' * 40
RELEASE = 'b' * 40
IDENTITY = {'control_source_sha': CONTROL, 'run_id': '123456', 'command_comment_id': 9001}
ROLLBACK_IDENTITY = {'control_source_sha': 'd'*40, 'run_id': '234567', 'command_comment_id': 9002}
CONTINUATION_IDENTITY = {'control_source_sha': CONTROL, 'run_id': '345678', 'command_comment_id': 9003}
UPDATE_IDENTITY = {'control_source_sha': CONTROL, 'run_id': '456789', 'command_comment_id': 9004}
UPDATE_ROLLBACK_IDENTITY = {'control_source_sha': 'd'*40, 'run_id': '567890', 'command_comment_id': 9005}
PARENT_IDENTITY = {'control_source_sha': '251585b63dab95988d9f635a2b1f1da6d61a4650',
                   'run_id': '44840666320', 'command_comment_id': 7053664048}
NEXT_ENTRY = '_preview/search3-next-candidate/visual-search/index.php'


def event():
    return {'repository': {'full_name': m.REPO}, 'action': 'created', 'issue': {'number': 4709},
            'comment': {'id': 9001, 'body': f'{m.PREFIX} {CONTROL} {m.OPERATION}', 'user': {'id': 226193297}, 'author_association': 'OWNER'}}


def environment():
    return {'GITHUB_REPOSITORY': m.REPO, 'GITHUB_REF': 'refs/heads/main', 'GITHUB_WORKFLOW_REF': f'{m.REPO}/{m.WORKFLOW}@refs/heads/main',
            'GITHUB_ACTOR': 'pyatkoff', 'GITHUB_TRIGGERING_ACTOR': 'pyatkoff', 'GITHUB_EVENT_NAME': 'issue_comment',
            'GITHUB_RUN_ATTEMPT': '1', 'GITHUB_RUN_ID': IDENTITY['run_id'], 'GITHUB_SHA': CONTROL}


def rollback_event():
    value = event(); value['comment']['id'] = ROLLBACK_IDENTITY['command_comment_id']
    value['comment']['body'] = f'{m.ROLLBACK_PREFIX} {ROLLBACK_IDENTITY["control_source_sha"]} {IDENTITY["run_id"]} {m.ROLLBACK_OPERATION}'
    return value


def continuation_event():
    value = event(); value['comment']['id'] = CONTINUATION_IDENTITY['command_comment_id']
    value['comment']['body'] = f'{m.CONTINUATION_PREFIX} {CONTROL} {m.CONTINUATION_OPERATION}'
    return value


def update_event():
    value = event(); value['comment']['id'] = UPDATE_IDENTITY['command_comment_id']
    value['comment']['body'] = f'{m.UPDATE_PREFIX} {CONTROL} {m.UPDATE_OPERATION}'
    return value


def update_rollback_event():
    value = event(); value['comment']['id'] = UPDATE_ROLLBACK_IDENTITY['command_comment_id']
    value['comment']['body'] = (f'{m.UPDATE_ROLLBACK_PREFIX} {UPDATE_ROLLBACK_IDENTITY["control_source_sha"]} '
                              f'{UPDATE_IDENTITY["run_id"]} {m.UPDATE_ROLLBACK_OPERATION}')
    return value


def update_parent_identity():
    return {key: m.UPDATE_PARENT[key] for key in ('control_source_sha', 'run_id', 'command_comment_id', 'operation_id')}


def update_parent_result():
    return installed_result() | update_parent_identity() | {
        'snapshot_manifest_sha256': m.UPDATE_PARENT['snapshot_manifest_sha256'],
        'parent_failure': copy.deepcopy(m.CONTINUATION_PARENT),
        'dependency_policy_sha256': m.CONTINUATION_POLICY_SHA,
        'parent_namespace_absent': True, 'public_mismatches': [],
        'private_checks': {label: True for label in ('anex_private_config', 'public_config', 'site_config', 'local_config', 'next_config')}}


def update_parent_archive(change=None, extra=False):
    value = {**update_parent_identity(), 'read_only': False, 'phase': 'complete', 'ssh_started': True,
             'status': 'installed_verified', 'parent_failure': copy.deepcopy(m.CONTINUATION_PARENT),
             'dependency_policy_sha256': m.CONTINUATION_POLICY_SHA, 'remote': update_parent_result()}
    if change: change(value)
    data = (json.dumps(value, sort_keys=True) + '\n').encode(); stream = io.BytesIO()
    with zipfile.ZipFile(stream, 'w') as archive:
        archive.writestr('evidence.json', data)
        if extra: archive.writestr('unreviewed.json', b'{}')
    return stream.getvalue(), m.sha(data)


def continuation_comments_fixture():
    parent = m.CONTINUATION_PARENT
    owner = {'user': {'id': 226193297}, 'author_association': 'OWNER'}
    return [owner | {'id': 7053664048,
                     'body': f'{m.PREFIX} {PARENT_IDENTITY["control_source_sha"]} public-search3-20261120-v1'},
            owner | {'id': 7053751668, 'body': ' '.join(str(value) for value in (
                parent['run_id'], parent['control_source_sha'], parent['command_comment_id'], parent['job_id'],
                parent['artifact_id'], parent['artifact_sha256'], parent['receipt_sha256'],
                'blocked_no_replay', 'dependency_drift', 'NO_REPLAY'))}, continuation_event()['comment']]


def parent_failure_fixture(change=None, extra=False):
    # The retained zero-write failure is historical, independent of current policy.
    remote = {'schema_version': 1, 'operation_id': 'public-search3-20261120-v1',
              'source_sha': '1a22691517b53e23ea2fa98d02f857b11a118392',
              'manifest_sha256': 'c89415a6bfeb6dd49be42d1dfc911310330c69cda2dd56737e524a2b1859d390',
              **PARENT_IDENTITY, 'status': 'blocked_no_replay', 'phase': 'preflight',
              'supplier_calls': 0, 'database_reads': 0, 'database_writes': 0, 'lead_calls': 0,
              'booking_calls': 0, 'application_calls': 0, 'written_files': [], 'rollback_files': [],
              'snapshot_verified': False, 'restore_drill_verified': False, 'protected_unchanged': False,
              'snapshot_manifest_sha256': None, 'reason': 'dependency_drift'}
    value = {**PARENT_IDENTITY, 'operation_id': 'public-search3-20261120-v1', 'phase': 'complete',
             'read_only': False, 'ssh_started': True, 'status': 'blocked_no_replay', 'remote': remote}
    if change: change(value)
    data = (json.dumps(value, sort_keys=True) + '\n').encode(); stream = io.BytesIO()
    with zipfile.ZipFile(stream, 'w') as archive:
        archive.writestr('evidence.json', data)
        if extra: archive.writestr('unreviewed.json', b'{}')
    return stream.getvalue(), m.sha(data)


def installed_result():
    return {'schema_version': 1, 'operation_id': m.OPERATION, 'source_sha': m.SOURCE, 'manifest_sha256': m.MANIFEST_SHA,
            **IDENTITY, 'status': 'installed_verified', 'phase': 'complete', 'supplier_calls': 0, 'database_reads': 0,
            'database_writes': 0, 'lead_calls': 0, 'booking_calls': 0, 'application_calls': 0,
            'written_files': [*m.ADDITIONS, m.ENTRY], 'rollback_files': [], 'snapshot_verified': True,
            'restore_drill_verified': True, 'protected_unchanged': True, 'snapshot_manifest_sha256': '7'*64}


def archive_fixture(change=None):
    files = {path: ('compiled:' + path).encode() for path in (*m.ADDITIONS, m.ENTRY)}
    rows = [{'path': path, 'served': {'size': len(data), 'mode': '0644', 'sha256': m.sha(data)}} for path, data in files.items()]
    rows[-1]['before'] = {k: v for k, v in m.ENTRY_BEFORE.items() if k != 'state'}
    manifest = {'schema_version': 1, 'purpose': 'local_public_live_install_candidate', 'deployable': False,
                'profile': {'schemaVersion': 1, 'profile': 'live', 'scenario': 'family'}, 'candidate': {'sha': m.SOURCE, 'tree_sha': m.TREE},
                'main': {'sha': m.BUILD_MAIN}, 'files': {'added': rows[:-1], 'replaced': rows[-1:]}}
    if change: change(manifest, files)
    raw = json.dumps(manifest).encode(); stream = io.BytesIO()
    with zipfile.ZipFile(stream, 'w') as archive:
        archive.writestr('manifest.json', raw)
        for path, data in files.items(): archive.writestr('overlay/' + path, data)
    return stream.getvalue(), m.sha(raw)


def update_archive_fixture(change=None):
    predecessor, predecessor_digest = archive_fixture()
    with patch.object(m, 'MANIFEST_SHA', predecessor_digest):
        policy, encoded = m.package(predecessor, continuation=True)
    with zipfile.ZipFile(io.BytesIO(predecessor)) as archive:
        manifest = json.loads(archive.read('manifest.json'))
        files = {name.removeprefix('overlay/'): archive.read(name)
                 for name in archive.namelist() if name.startswith('overlay/')}
    manifest['candidate'] = {'sha': m.UPDATE_SOURCE, 'tree_sha': m.UPDATE_TREE}
    files[m.UPDATE_PATH] = b'compiled: applied contact flight and family price retained'
    after = {'state': 'present', 'sha256': m.sha(files[m.UPDATE_PATH]),
             'size': len(files[m.UPDATE_PATH]), 'mode': '0644'}
    row = next(row for row in manifest['files']['added'] if row['path'] == m.UPDATE_PATH)
    row['served'] = {key: value for key, value in after.items() if key != 'state'}
    if change: change(manifest, files)
    manifest_raw = json.dumps(manifest).encode(); stream = io.BytesIO()
    with zipfile.ZipFile(stream, 'w') as archive:
        archive.writestr('manifest.json', manifest_raw)
        for path, data in files.items(): archive.writestr('overlay/'+path, data)
    return stream.getvalue(), m.sha(manifest_raw), policy, encoded, after


def preflight_comments():
    owner = {'user': {'id': 226193297}, 'author_association': 'OWNER'}
    return [owner | {'id': m.PREFLIGHT_COMMAND, 'body': f'{m.INSPECT_PREFIX} {m.PREFLIGHT_CONTROL} {m.INSPECT_OPERATION}'},
            owner | {'id': m.PREFLIGHT_RECEIPT_COMMENT,
                     'body': f'{m.PREFLIGHT_RUN} {m.PREFLIGHT_RESULT_SHA} preflight_mismatch CONSUMED_NO_REPLAY'}]


def preflight_fixture(change=None, extra=False):
    observed = {path: {'state': 'absent'} for path in m.ADDITIONS}
    observed[m.ENTRY] = dict(m.ENTRY_BEFORE)
    for path, digest in m.PREFLIGHT_ACCEPTED_PROTECTED.items():
        if path != m.PRIVATE_CONFIG:
            observed[path] = {'state': 'present', 'sha256': digest, 'mode': '0644',
                              'size': m.INSTALL_REVIEWED.get(path, {}).get('size', 100)}
    observed['data/hotel-popularity-v1.php'] = {'state': 'absent'}
    identity = {'control_source_sha': m.PREFLIGHT_CONTROL, 'run_id': str(m.PREFLIGHT_RUN), 'command_comment_id': m.PREFLIGHT_COMMAND}
    remote = {'schema_version': 1, 'operation_id': m.INSPECT_OPERATION, 'source_sha': m.PREFLIGHT_SOURCE,
              'manifest_sha256': m.PREFLIGHT_MANIFEST_SHA, **identity, 'read_only': True, 'server_writes': 0,
              'supplier_calls': 0, 'database_reads': 0, 'database_writes': 0, 'lead_calls': 0, 'booking_calls': 0, 'application_calls': 0,
              'status': 'preflight_mismatch', 'observed': observed,
              'private_checks': {label: {'presence_known': True, 'present': present, 'matches_expected': True, 'unchanged': True}
                                 for label, present in {'anex_private_config': True, 'public_config': True, 'site_config': False,
                                                        'local_config': False, 'next_config': False}.items()},
              'mismatches': [{'path': path, 'reason': 'dependency_drift'} for path in m.INSTALL_REVIEWED]}
    value = {**identity, 'operation_id': m.INSPECT_OPERATION, 'phase': 'complete', 'read_only': True,
             'server_writes': 0, 'ssh_started': True, 'status': 'preflight_mismatch', 'remote': remote}
    if change: change(value)
    data = json.dumps(value, sort_keys=True).encode(); stream = io.BytesIO()
    with zipfile.ZipFile(stream, 'w') as archive:
        archive.writestr('evidence.json', data)
        if extra: archive.writestr('unreviewed.json', b'{}')
    return stream.getvalue(), m.sha(data)


class Admission(unittest.TestCase):
    def test_current_approved_public_packet_is_distinct_from_next_and_old_source(self):
        self.assertEqual((m.SOURCE_PR, m.SOURCE, m.TREE, m.RELEASE), (
            5125, '1a22691517b53e23ea2fa98d02f857b11a118392',
            'bcfb06414164bf3cad454055fb3acdd2b6454dd1', '71fd65ae80e2b8c4b00b80f3bafe36dfa9bb2426'))
        self.assertEqual((m.BUILD_RUN, m.ARTIFACT, m.ARTIFACT_NAME), (
            44813818406, 14667623451, 'search3-public-live-1a22691517b53e23ea2fa98d02f857b11a118392'))
        self.assertEqual(m.ARTIFACT_SHA, '9cec7ecb27f1582ad181ab1dd3785d2ee1566aef94d3a62e0c212d0f7c166697')
        self.assertEqual(m.MANIFEST_SHA, 'c89415a6bfeb6dd49be42d1dfc911310330c69cda2dd56737e524a2b1859d390')
        self.assertEqual(m.BUILD_MAIN, 'c5513aaee18af701f1253678e79f41e5725b71a2')

    def test_continuation_pins_one_parent_and_changes_only_current_root_db(self):
        self.assertEqual(m.CONTINUATION_PREFIX, '/continue-search3-public')
        self.assertEqual(m.CONTINUATION_OPERATION, 'public-search3-20261121-after-44840666320-v1')
        self.assertNotEqual(m.CONTINUATION_OPERATION, m.OPERATION)
        self.assertEqual(m.CONTINUATION_PARENT, {
            'operation_id': 'public-search3-20261120-v1', **PARENT_IDENTITY,
            'job_id': 136647935738, 'artifact_id': 14680527168,
            'artifact_sha256': '476cbe8ee8fd566b6abe35cd7d2697700f8888955022749a66e7bb2dd690b358',
            'receipt_sha256': '99ea76313a2c18f50bf449f6f4396f9368f17ffe6245d334123df0b1e2687866',
            'terminal_comment_id': 7053751668})
        _, digest = parent_failure_fixture()
        self.assertEqual(digest, m.CONTINUATION_PARENT['receipt_sha256'])
        old_db = 'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4'
        current_db = 'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13'
        self.assertEqual(m.PREFLIGHT_ACCEPTED_PROTECTED['data/db-v1.php'], old_db)
        self.assertEqual(m.INSTALL_PROTECTED['data/db-v1.php'], old_db)
        self.assertEqual(m.CONTINUATION_PROTECTED,
                         m.INSTALL_PROTECTED | {'data/db-v1.php': current_db})
        self.assertEqual(m.CONTINUATION_PROTECTED[NEXT_ENTRY],
                         'bfda6c091544c13d157537879adaa7037e3bff3dface2fb5b3a77067808da339')
        raw, digest = archive_fixture()
        with patch.object(m, 'MANIFEST_SHA', digest):
            original, files = m.package(raw)
            continued, child_files = m.package(raw, continuation=True)
        self.assertEqual(files, child_files)
        self.assertEqual(continued['protected'], m.CONTINUATION_PROTECTED)
        self.assertEqual(original['protected'], m.INSTALL_PROTECTED)
        self.assertEqual(set(files), set(m.ADDITIONS) | {m.ENTRY})
        self.assertEqual(len(files), 42)
        self.assertFalse(any(path.startswith(('data/', '_preview/')) for path in files))
        for key in ('expected', 'before', 'opaque_settings', 'opaque_presence', 'absent_dependencies'):
            self.assertEqual(continued[key], original[key])
        self.assertEqual(continued['parent_failure'], m.CONTINUATION_PARENT)

    def test_continuation_event_does_not_reopen_install_or_inspect(self):
        env = environment() | {'GITHUB_RUN_ID': CONTINUATION_IDENTITY['run_id']}
        self.assertEqual(m.event_identity(continuation_event(), env, continuation=True), CONTINUATION_IDENTITY)
        self.assertEqual(m.command_mode(['continue']), 'continue')
        for args in (['continue', PARENT_IDENTITY['run_id']], ['continue', '/tmp/target'], ['--continue']):
            with self.assertRaisesRegex(RuntimeError, 'usage'): m.command_mode(args)
        with self.assertRaises(RuntimeError): m.event_identity(continuation_event(), env)
        with self.assertRaises(RuntimeError): m.event_identity(continuation_event(), env, inspect=True)
        with self.assertRaises(RuntimeError): m.event_identity(event(), environment(), continuation=True)
        with self.assertRaises(RuntimeError): m.event_identity(continuation_event(), env, inspect=True, continuation=True)
        for field, value in [('GITHUB_RUN_ATTEMPT', '2'), ('GITHUB_ACTOR', 'other'),
                             ('GITHUB_TRIGGERING_ACTOR', 'other'), ('GITHUB_REF', 'refs/heads/feature'),
                             ('GITHUB_WORKFLOW_REF', 'other')]:
            with self.subTest(field=field), self.assertRaises(RuntimeError):
                m.event_identity(continuation_event(), env | {field: value}, continuation=True)
        for change in [lambda e: e['comment'].update(body=e['comment']['body']+' extra'),
                       lambda e: e['comment'].update(body=e['comment']['body'].replace('44840666320', '44840666321')),
                       lambda e: e['comment'].update(author_association='MEMBER'),
                       lambda e: e['comment']['user'].update(id=True),
                       lambda e: e['issue'].update(pull_request={}), lambda e: e.update(action='edited')]:
            value = continuation_event(); change(value)
            with self.assertRaises(RuntimeError): m.event_identity(value, env, continuation=True)

    def test_owner_main_workflow_attempt_and_exact_command(self):
        self.assertEqual(m.event_identity(event(), environment()), IDENTITY)
        for key, value in [('GITHUB_REPOSITORY','evil/repo'),('GITHUB_REF','refs/heads/feature'),('GITHUB_WORKFLOW_REF','other'),
                           ('GITHUB_ACTOR','someone'),('GITHUB_TRIGGERING_ACTOR','someone'),('GITHUB_EVENT_NAME','workflow_dispatch'),
                           ('GITHUB_RUN_ATTEMPT','2'),('GITHUB_RUN_ID','0'),('GITHUB_SHA','x'*40)]:
            with self.subTest(key=key), self.assertRaises(RuntimeError): m.event_identity(event(), environment() | {key: value})
        for alter in [lambda e:e['issue'].update(number=4217), lambda e:e['issue'].update(pull_request={}),
                      lambda e:e.update(action='edited'), lambda e:e['comment']['user'].update(id=True),
                      lambda e:e['comment'].update(author_association='MEMBER'), lambda e:e['comment'].update(body=e['comment']['body']+' extra'),
                      lambda e:e['comment'].update(body=e['comment']['body'].replace(m.OPERATION,'readiness-public-int-20261120-v2'))]:
            value=event();alter(value)
            with self.assertRaises(RuntimeError): m.event_identity(value,environment())

    def test_unresolved_release_cannot_activate(self):
        with patch.object(m,'RELEASE',None),patch.object(m,'api') as api,self.assertRaisesRegex(RuntimeError,'pending_final_evidence'):
            m.admission(IDENTITY)
        api.assert_not_called()

    def test_fixed_inspect_mode_cannot_cross_over_into_install(self):
        inspected=event();inspected['comment']['body']=f'{m.INSPECT_PREFIX} {CONTROL} {m.INSPECT_OPERATION}'
        self.assertEqual(m.event_identity(inspected,environment(),True),IDENTITY)
        with self.assertRaisesRegex(RuntimeError,'command'): m.event_identity(inspected,environment())
        with self.assertRaisesRegex(RuntimeError,'command'): m.event_identity(event(),environment(),True)
        self.assertFalse(m.command_mode([]));self.assertTrue(m.command_mode(['inspect']))
        for arguments in (['install'],['inspect','other'],['--inspect'],['/tmp/target']):
            with self.assertRaisesRegex(RuntimeError,'usage'): m.command_mode(arguments)
        for policy in ({'operation':m.OPERATION,'read_only':True},{'operation':m.INSPECT_OPERATION,'read_only':False},{'operation':m.OPERATION,'read_only':1}):
            with self.assertRaisesRegex(RuntimeError,'mode'): m.remote_program(policy,{},IDENTITY)

    def fake_api(self, path):
        if path == '/git/ref/heads/main': return {'object': {'sha': CONTROL}}
        if path == '/git/ref/heads/release/search3-production-ready-v1': return {'object': {'sha': RELEASE}}
        if path == '/pulls/' + str(m.SOURCE_PR):
            return {'state':'closed','merged':True,'merge_commit_sha':RELEASE,'head':{'sha':m.SOURCE,'repo':{'full_name':m.REPO}},'base':{'ref':'release/search3-production-ready-v1','repo':{'full_name':m.REPO}}}
        if path.startswith('/git/commits/'): return {'tree':{'sha':m.TREE}}
        if path.startswith('/contents/.github/workflows/deploy-anytoour.yml'):
            return {'type':'file','encoding':'base64','content':base64.b64encode(b'guarded publisher').decode()}
        if '/check-runs?' in path:
            return {'total_count':len(m.SOURCE_CHECK_NAMES),'check_runs':[{'id':i+1,'name':name,'head_sha':m.SOURCE,'status':'completed','conclusion':'success','app':{'slug':'github-actions'}} for i,name in enumerate(m.SOURCE_CHECK_NAMES)]}
        if path == '/actions/runs/'+str(m.BUILD_RUN):
            return {'head_sha':m.SOURCE,'path':'.github/workflows/search3-visual-migration.yml','event':'pull_request','run_attempt':1,'status':'completed','conclusion':'success'}
        if path == '/actions/artifacts/'+str(m.ARTIFACT):
            return {'id':m.ARTIFACT,'name':m.ARTIFACT_NAME,'expired':False,'digest':'sha256:'+m.ARTIFACT_SHA,'workflow_run':{'id':m.BUILD_RUN}}
        if path.startswith('/issues/4709/comments?'):
            return [event()['comment'],{'id':m.SIGNING_RECEIPT_COMMENT,'user':{'id':226193297},'author_association':'OWNER','body':str(m.SIGNING_RUN)+' '+m.SIGNING_RESULT_SHA}, *preflight_comments()]
        raise AssertionError(path)

    def call_admission(self, mutate=None, continuation=False):
        def api(path):
            value=self.fake_api(path)
            if continuation and '/comments?' in path:
                value = value[1:] + continuation_comments_fixture()
            return mutate(path,value) if mutate else value
        with patch.object(m,'RELEASE',RELEASE),patch.object(m,'PUBLISHER_GUARD_SHA',m.sha(b'guarded publisher')),patch.object(m,'reviewed_control'),patch.object(m,'api',side_effect=api):
            m.admission(CONTINUATION_IDENTITY if continuation else IDENTITY, continuation=continuation)

    def test_continuation_requires_unique_child_parent_and_owner_terminal(self):
        self.call_admission(continuation=True)
        for target, change in [(9003, lambda row: row.update(body=row['body']+' edited')),
                               (9003, lambda row: row.update(author_association='MEMBER')),
                               (7053664048, lambda row: row.update(body=row['body'].replace(PARENT_IDENTITY['control_source_sha'], CONTROL))),
                               (7053664048, lambda row: row['user'].update(id=2)),
                               (7053751668, lambda row: row.update(body=row['body'].replace('NO_REPLAY', 'unaccepted'))),
                               (7053751668, lambda row: row.update(author_association='MEMBER')),
                               (7053751668, lambda row: row.update(body=row['body'].replace('44840666320', '44840666321')))]:
            def mutate(path, rows):
                if '/comments?' in path: change(next(row for row in rows if row['id'] == target))
                return rows
            with self.subTest(target=target, change=change), self.assertRaises(RuntimeError):
                self.call_admission(mutate, continuation=True)
        for target in (9003, 7053664048):
            def duplicate(path, rows):
                if '/comments?' in path:
                    rows.append(copy.deepcopy(next(row for row in rows if row['id'] == target)) | {'id':9004})
                return rows
            with self.subTest(duplicate=target), self.assertRaises(RuntimeError):
                self.call_admission(duplicate, continuation=True)

    def test_full_ci_and_guarded_main_and_unique_comment(self):
        self.call_admission()
        def changed(path,value):
            if '/check-runs?' in path: value['check_runs'][0]['conclusion']='failure'
            return value
        with self.assertRaisesRegex(RuntimeError,'source_ci'): self.call_admission(changed)
        def duplicate(path,value):
            if '/comments?' in path: value.append(event()['comment'] | {'id':9002})
            return value
        with self.assertRaisesRegex(RuntimeError,'operation_already_reserved'): self.call_admission(duplicate)
        def unguarded(path,value):
            if path.startswith('/contents/'): value['content']=base64.b64encode(b'unguarded publisher').decode()
            return value
        with self.assertRaisesRegex(RuntimeError,'publisher_guard'): self.call_admission(unguarded)
        def running(path,value):
            if path == '/actions/runs/'+str(m.BUILD_RUN): value['status']='in_progress';value['conclusion']=None
            return value
        with self.assertRaisesRegex(RuntimeError,'build_run'): self.call_admission(running)
        def unmerged(path,value):
            if path == '/pulls/' + str(m.SOURCE_PR): value['merged']=False
            return value
        with self.assertRaisesRegex(RuntimeError,'candidate_moved'): self.call_admission(unmerged)

    def test_source_ci_requires_exact_checks_for_the_current_candidate(self):
        for name in m.SOURCE_CHECK_NAMES:
            for field, value in [('head_sha', '0' * 40), ('status', 'in_progress'),
                                 ('conclusion', 'skipped'), ('app', {'slug': 'untrusted'})]:
                def changed(path, response):
                    if '/check-runs?' in path:
                        next(row for row in response['check_runs'] if row['name'] == name)[field] = value
                    return response
                with self.subTest(check=name, field=field), self.assertRaisesRegex(RuntimeError, 'source_ci'):
                    self.call_admission(changed)
        for label, alter in [
            ('missing', lambda rows: rows.pop()),
            ('extra', lambda rows: rows.append(rows[0] | {'id': 999, 'name': 'unreviewed'})),
            ('substitute', lambda rows: rows[-1].update(name='unreviewed')),
            ('duplicate', lambda rows: rows[-1].update(name=rows[0]['name'])),
        ]:
            def changed(path, response):
                if '/check-runs?' in path:
                    alter(response['check_runs'])
                    response['total_count'] = len(response['check_runs'])
                return response
            with self.subTest(check_set=label), self.assertRaisesRegex(RuntimeError, 'source_ci'):
                self.call_admission(changed)

    def test_install_requires_original_consumed_inspection_and_owner_receipt(self):
        for field, value in [('body', 'unaccepted'), ('user', {'id': 2}), ('author_association', 'MEMBER')]:
            def changed(path, rows):
                if '/comments?' in path:
                    next(row for row in rows if row['id'] == m.PREFLIGHT_RECEIPT_COMMENT)[field] = value
                return rows
            with self.subTest(field=field), self.assertRaisesRegex(RuntimeError, 'preflight_acceptance'):
                self.call_admission(changed)
        for alter in [lambda rows: rows.append(preflight_comments()[0] | {'id': 99}),
                      lambda rows: next(row for row in rows if row['id'] == m.PREFLIGHT_COMMAND).update(body='different command')]:
            def changed(path, rows):
                if '/comments?' in path: alter(rows)
                return rows
            with self.assertRaisesRegex(RuntimeError, 'preflight_command'): self.call_admission(changed)

    def test_complete_pagination_not_only_first_hundred(self):
        first=[{'id':i+1} for i in range(100)]
        with patch.object(m,'api',side_effect=[first,[{'id':101}]]): self.assertEqual(len(m.pages('/issues/4709/comments')),101)
        with patch.object(m,'api',side_effect=[first,[{'id':1}]]),self.assertRaisesRegex(RuntimeError,'pagination_duplicate'): m.pages('/issues/4709/comments')
        with patch.object(m,'api',return_value={'total_count':2,'check_runs':[{'id':1}]}),self.assertRaisesRegex(RuntimeError,'pagination_incomplete'): m.pages('/check-runs','check_runs')

    def test_control_review_requires_every_existing_contract_and_identical_tree(self):
        reviewed,previous='c'*40,'d'*40
        def api(path):
            if path.startswith('/contents/'):
                return {'type':'file','sha':'e'*40}
            if path=='/git/commits/'+CONTROL:
                return {'sha':CONTROL,'tree':{'sha':m.TREE},'parents':[{'sha':previous},{'sha':reviewed}]}
            if path=='/git/commits/'+reviewed:
                return {'sha':reviewed,'tree':{'sha':m.TREE},'parents':[{'sha':previous}]}
            if path=='/git/commits/'+previous:
                return {'sha':previous,'tree':{'sha':'f'*40},'parents':[]}
            if '/check-runs?' in path:
                return {'total_count':len(m.CONTROL_CHECKS),'check_runs':[{'id':i+1,'name':name,'head_sha':reviewed,'status':'completed','conclusion':'success','app':{'slug':'github-actions'}} for i,name in enumerate(m.CONTROL_CHECKS)]}
            raise AssertionError(path)
        with patch.object(m,'api',side_effect=api): m.reviewed_control(CONTROL)
        for failed in m.CONTROL_CHECKS:
            def failing(path):
                value=api(path)
                if '/check-runs?' in path:
                    next(c for c in value['check_runs'] if c['name']==failed)['conclusion']='failure'
                return value
            with self.subTest(failed=failed),patch.object(m,'api',side_effect=failing),self.assertRaisesRegex(RuntimeError,'reviewed_control_missing'):
                m.reviewed_control(CONTROL)
        def changed_tree(path):
            value=api(path)
            if path=='/git/commits/'+reviewed: value['tree']['sha']='0'*40
            return value
        with patch.object(m,'api',side_effect=changed_tree),self.assertRaisesRegex(RuntimeError,'reviewed_control_missing'): m.reviewed_control(CONTROL)
        def imported_carrier_changed(path):
            value=api(path)
            if path=='/contents/scripts/deploy/search3_root_gateway_install.py?ref='+reviewed: value['sha']='0'*40
            return value
        with patch.object(m,'api',side_effect=imported_carrier_changed),self.assertRaisesRegex(RuntimeError,'reviewed_control_missing'): m.reviewed_control(CONTROL)

    def test_artifact_scope_review_and_traversal_are_rejected(self):
        raw,digest=archive_fixture()
        with patch.object(m,'MANIFEST_SHA',digest):
            policy,files=m.package(raw)
        self.assertEqual(len(files),42);self.assertNotIn('index.php',files);self.assertNotIn('api-v2.php',files)
        for change in [lambda v,f:v['profile'].update(profile='review'),lambda v,f:v['files']['added'].pop(),
                       lambda v,f:v['files']['replaced'][0]['before'].update(sha256='0'*64),
                       lambda v,f:f.update({'api-v2.php':b'never copy'}),lambda v,f:v['files']['added'][0]['served'].update(mode='0777')]:
            raw,digest=archive_fixture(change)
            with patch.object(m,'MANIFEST_SHA',digest),self.assertRaises(RuntimeError): m.package(raw)
        stream=io.BytesIO()
        with zipfile.ZipFile(stream,'w') as archive: archive.writestr('../escape',b'x')
        with self.assertRaisesRegex(RuntimeError,'archive_entry'): m.zip_files(stream.getvalue())


class UpdateAdmission(unittest.TestCase):
    def metadata(self, path):
        if path == '/git/ref/heads/main': return {'object': {'sha': CONTROL}}
        if path == '/git/ref/heads/release/search3-production-ready-v1': return {'object': {'sha': RELEASE}}
        if path == '/pulls/' + str(m.UPDATE_SOURCE_PR):
            return {'state': 'closed', 'merged': True, 'merge_commit_sha': RELEASE,
                    'head': {'sha': m.UPDATE_SOURCE, 'repo': {'full_name': m.REPO}},
                    'base': {'ref': 'release/search3-production-ready-v1', 'repo': {'full_name': m.REPO}}}
        if path.startswith('/git/commits/'): return {'tree': {'sha': m.UPDATE_TREE}}
        if path.startswith('/contents/.github/workflows/deploy-anytoour.yml'):
            return {'type': 'file', 'encoding': 'base64', 'content': base64.b64encode(b'guarded publisher').decode()}
        if '/check-runs?' in path:
            return {'total_count': len(m.SOURCE_CHECK_NAMES), 'check_runs': [
                {'id': index+1, 'name': name, 'head_sha': m.UPDATE_SOURCE, 'status': 'completed',
                 'conclusion': 'success', 'app': {'slug': 'github-actions'}}
                for index, name in enumerate(m.SOURCE_CHECK_NAMES)]}
        if path == '/actions/runs/' + str(m.UPDATE_BUILD_RUN):
            return {'id': m.UPDATE_BUILD_RUN, 'head_sha': m.UPDATE_SOURCE, 'path': '.github/workflows/search3-visual-migration.yml',
                    'event': 'pull_request', 'run_attempt': 1, 'status': 'completed', 'conclusion': 'success'}
        if path == '/actions/artifacts/' + str(m.UPDATE_ARTIFACT):
            return {'id': m.UPDATE_ARTIFACT, 'name': m.UPDATE_ARTIFACT_NAME, 'expired': False,
                    'digest': 'sha256:' + m.UPDATE_ARTIFACT_SHA, 'workflow_run': {'id': m.UPDATE_BUILD_RUN}}
        if path.startswith('/issues/4709/comments?'): return [update_event()['comment']]
        raise AssertionError('Unexpected update dependency: '+path)

    def admission(self, mutation=None):
        def api(path):
            value = self.metadata(path)
            return mutation(path, value) if mutation else value
        with patch.object(m, 'UPDATE_RELEASE', RELEASE), patch.object(m, 'PUBLISHER_GUARD_SHA', m.sha(b'guarded publisher')), \
             patch.object(m, 'reviewed_control') as review, patch.object(m, 'api', side_effect=api):
            m.update_admission(UPDATE_IDENTITY)
            review.assert_called_once_with(CONTROL)

    def test_successor_source_does_not_repin_the_consumed_install(self):
        self.assertEqual((m.SOURCE_PR, m.SOURCE, m.RELEASE), (
            5125, '1a22691517b53e23ea2fa98d02f857b11a118392',
            '71fd65ae80e2b8c4b00b80f3bafe36dfa9bb2426'))
        self.assertEqual((m.UPDATE_SOURCE_PR, m.UPDATE_SOURCE, m.UPDATE_TREE), (
            5130, 'f10e51a49ea9d4b3e4b6356f98d6d898484d9fc6',
            '7251ab6712a437ef46a590a3f2b8fb8bd1037b82'))
        self.assertEqual(m.UPDATE_RELEASE, '67c9e34876a1b52e1e7ed919c5b70a2f28a7220b')
        self.assertEqual((m.UPDATE_BUILD_RUN, m.UPDATE_ARTIFACT), (44847945914, 14684470494))
        self.assertEqual(m.UPDATE_ARTIFACT_SHA, '4bced061fe5ffc224c196fa400c65ee726d775df4fe4830c9f4b03a19b926a3a')
        self.assertEqual(m.UPDATE_MANIFEST_SHA, '19a2ecc6db293d4994c70c9203d52e24cc577d9bfa57f90273820c01e955121c')
        self.assertEqual(m.UPDATE_PATH, 'visual-search/app.js')
        self.assertEqual(update_parent_identity(), {
            'operation_id': m.CONTINUATION_OPERATION,
            'control_source_sha': '6335775a2f3f4b628dbc405b5d4edd7a9c43b6ec',
            'run_id': '44843395448', 'command_comment_id': 7054034774})
        self.assertEqual(m.UPDATE_PARENT['artifact_id'], 14680714567)
        self.assertEqual(m.UPDATE_PARENT['receipt_sha256'], 'bcf70929bc32228d6a2e7676af9addde80dfda1b9675413866eef31c9caca3bf')

    def test_fixed_update_command_preserves_owner_main_and_original_attempt(self):
        env = environment() | {'GITHUB_RUN_ID': UPDATE_IDENTITY['run_id']}
        self.assertEqual(m.update_event_identity(update_event(), env), UPDATE_IDENTITY)
        self.assertEqual(m.command_mode(['update']), 'update')
        self.assertEqual(m.command_mode(['update-rollback']), 'update-rollback')
        for field, value in [('GITHUB_REPOSITORY', 'other/project'), ('GITHUB_REF', 'refs/heads/feature'),
                             ('GITHUB_WORKFLOW_REF', 'other'), ('GITHUB_ACTOR', 'other'),
                             ('GITHUB_TRIGGERING_ACTOR', 'other'), ('GITHUB_EVENT_NAME', 'workflow_dispatch'),
                             ('GITHUB_RUN_ATTEMPT', '2'), ('GITHUB_RUN_ID', '0')]:
            with self.subTest(field=field), self.assertRaises(RuntimeError):
                m.update_event_identity(update_event(), env | {field: value})
        for mutate in [lambda e: e.update(action='edited'), lambda e: e['issue'].update(number=4217),
                       lambda e: e['issue'].update(pull_request={}),
                       lambda e: e['comment']['user'].update(id=True),
                       lambda e: e['comment'].update(author_association='MEMBER'),
                       lambda e: e['comment'].update(body=e['comment']['body']+' extra'),
                       lambda e: e['comment'].update(body=e['comment']['body'].replace(m.UPDATE_OPERATION, m.CONTINUATION_OPERATION))]:
            value = update_event(); mutate(value)
            with self.subTest(mutate=mutate), self.assertRaises(RuntimeError): m.update_event_identity(value, env)
        for value in (event(), continuation_event(), rollback_event()):
            with self.assertRaises(RuntimeError): m.update_event_identity(value, env)
        with self.assertRaises(RuntimeError): m.event_identity(update_event(), env)
        for args in (['update', '/tmp/root'], ['update-rollback', '123456'], ['--update']):
            with self.assertRaises(RuntimeError): m.command_mode(args)

    def test_fixed_update_rollback_cannot_target_the_initial_install(self):
        env = environment() | {'GITHUB_RUN_ID': UPDATE_ROLLBACK_IDENTITY['run_id'],
                               'GITHUB_SHA': UPDATE_ROLLBACK_IDENTITY['control_source_sha']}
        self.assertEqual(m.update_rollback_event_identity(update_rollback_event(), env),
                         (UPDATE_ROLLBACK_IDENTITY, UPDATE_IDENTITY['run_id']))
        for run in (m.UPDATE_PARENT['run_id'], m.CONTINUATION_PARENT['run_id'],
                    UPDATE_ROLLBACK_IDENTITY['run_id'], '0', '/tmp'):
            value = update_rollback_event()
            value['comment']['body'] = value['comment']['body'].replace(UPDATE_IDENTITY['run_id'], run)
            with self.subTest(run=run), self.assertRaises(RuntimeError): m.update_rollback_event_identity(value, env)
        with self.assertRaises(RuntimeError): m.update_rollback_event_identity(update_event(), env)
        with self.assertRaises(RuntimeError): m.update_event_identity(update_rollback_event(), env)

    def test_unresolved_successor_release_fails_before_any_api_or_ssh(self):
        with patch.object(m, 'UPDATE_RELEASE', None), patch.object(m, 'api') as api, \
             patch.object(m.carrier, 'run') as run, self.assertRaises(RuntimeError):
            m.update_admission(UPDATE_IDENTITY)
        api.assert_not_called(); run.assert_not_called()

    def test_update_requires_the_final_source_checks_and_artifact(self):
        self.admission()
        for name in m.SOURCE_CHECK_NAMES:
            for field, value in [('head_sha', m.SOURCE), ('status', 'in_progress'),
                                 ('conclusion', 'skipped'), ('app', {'slug': 'untrusted'})]:
                def mutation(path, response):
                    if '/check-runs?' in path:
                        next(row for row in response['check_runs'] if row['name'] == name)[field] = value
                    return response
                with self.subTest(check=name, field=field), self.assertRaises(RuntimeError): self.admission(mutation)
        for endpoint, change in [('/git/ref/heads/release/search3-production-ready-v1', lambda v: v['object'].update(sha=m.RELEASE)),
                                 ('/pulls/'+str(m.UPDATE_SOURCE_PR), lambda v: v.update(merged=False)),
                                 ('/git/commits/'+m.UPDATE_SOURCE, lambda v: v['tree'].update(sha=m.TREE)),
                                 ('/actions/runs/'+str(m.UPDATE_BUILD_RUN), lambda v: v.update(id=m.BUILD_RUN)),
                                 ('/actions/runs/'+str(m.UPDATE_BUILD_RUN), lambda v: v.update(head_sha=m.SOURCE)),
                                 ('/actions/runs/'+str(m.UPDATE_BUILD_RUN), lambda v: v.update(run_attempt=True)),
                                 ('/actions/runs/'+str(m.UPDATE_BUILD_RUN), lambda v: v.update(conclusion='failure')),
                                 ('/actions/artifacts/'+str(m.UPDATE_ARTIFACT), lambda v: v.update(expired=True)),
                                 ('/actions/artifacts/'+str(m.UPDATE_ARTIFACT), lambda v: v.update(digest='sha256:'+m.ARTIFACT_SHA)),
                                 ('/actions/artifacts/'+str(m.UPDATE_ARTIFACT), lambda v: v['workflow_run'].update(id=m.BUILD_RUN))]:
            def mutation(path, response):
                if path == endpoint: change(response)
                return response
            with self.subTest(endpoint=endpoint, change=change), self.assertRaises(RuntimeError): self.admission(mutation)

    def test_update_command_cannot_be_edited_or_reserved_twice(self):
        for change in [lambda rows: rows.append(copy.deepcopy(rows[0]) | {'id': 9999}),
                       lambda rows: rows[0].update(body=rows[0]['body']+' edited'),
                       lambda rows: rows[0]['user'].update(id=2),
                       lambda rows: rows[0].update(author_association='MEMBER')]:
            def mutation(path, response):
                if '/comments?' in path: change(response)
                return response
            with self.subTest(change=change), self.assertRaises(RuntimeError): self.admission(mutation)


class UpdateRollbackAdmission(unittest.TestCase):
    def metadata(self, path):
        if path == '/git/ref/heads/main': return {'object': {'sha': UPDATE_ROLLBACK_IDENTITY['control_source_sha']}}
        if path.startswith('/issues/4709/comments?'): return [update_event()['comment'], update_rollback_event()['comment']]
        if path == '/actions/runs/' + UPDATE_IDENTITY['run_id']:
            return {'id': int(UPDATE_IDENTITY['run_id']), 'head_sha': CONTROL, 'head_branch': 'main',
                    'event': 'issue_comment', 'path': m.WORKFLOW, 'run_attempt': 1,
                    'status': 'completed', 'conclusion': 'success', 'actor': {'login': 'pyatkoff'},
                    'triggering_actor': {'login': 'pyatkoff'}}
        if path.startswith('/actions/runs/' + UPDATE_IDENTITY['run_id'] + '/attempts/1/jobs?'):
            return {'total_count': 1, 'jobs': [{'id': 222, 'run_id': int(UPDATE_IDENTITY['run_id']),
                    'name': 'public-install', 'status': 'completed', 'conclusion': 'success', 'steps': [
                        {'name': 'Run python3 scripts/deploy/search3_public_install.py update',
                         'status': 'completed', 'conclusion': 'success'}]}]}
        if path.startswith('/actions/runs/' + UPDATE_IDENTITY['run_id'] + '/artifacts?'):
            return {'total_count': 1, 'artifacts': [{'id': 333, 'name': 'search3-public-install-'+UPDATE_IDENTITY['run_id']+'-1',
                    'expired': False, 'workflow_run': {'id': int(UPDATE_IDENTITY['run_id'])}, 'digest': 'sha256:'+'5'*64}]}
        raise AssertionError('Recovery fetched an unrelated or historic dependency: '+path)

    def admission(self, change=None):
        def api(path):
            value = self.metadata(path)
            return change(path, value) if change else value
        with patch.object(m, 'api', side_effect=api), patch.object(m, 'reviewed_control') as control, \
             patch.object(m, 'UPDATE_RELEASE', None), patch.object(m, 'RELEASE', None), \
             patch.object(m, 'update_final_heads', side_effect=AssertionError('source release gate')), \
             patch.object(m, 'signing_evidence', side_effect=AssertionError('historic signer gate')):
            original, artifact = m.update_rollback_admission(UPDATE_ROLLBACK_IDENTITY, UPDATE_IDENTITY['run_id'])
            control.assert_called_once_with(UPDATE_ROLLBACK_IDENTITY['control_source_sha'])
            self.assertEqual(original, UPDATE_IDENTITY); self.assertEqual(artifact['id'], 333)

    def test_successful_update_recovery_is_independent_of_source_and_historic_artifacts(self):
        self.admission()
        for run in (m.UPDATE_PARENT['run_id'], m.CONTINUATION_PARENT['run_id'], UPDATE_ROLLBACK_IDENTITY['run_id']):
            with patch.object(m, 'api') as api, self.assertRaises(RuntimeError):
                m.update_rollback_commands(UPDATE_ROLLBACK_IDENTITY, run)
            api.assert_not_called()

    def test_only_the_unique_owned_successful_update_attempt_authorizes_rollback(self):
        for part, mutate in [('/comments?', lambda v: v.append(update_event()['comment'] | {'id': 9006})),
                             ('/comments?', lambda v: v.append(update_rollback_event()['comment'] | {'id': 9006})),
                             ('/comments?', lambda v: v[0].update(author_association='MEMBER')),
                             ('/comments?', lambda v: v[1].update(body=v[1]['body']+' edited')),
                             ('/actions/runs/456789', lambda v: v.update(head_sha='f'*40)),
                             ('/actions/runs/456789', lambda v: v.update(run_attempt=2)),
                             ('/actions/runs/456789', lambda v: v.update(conclusion='failure')),
                             ('/actions/runs/456789', lambda v: v.update(status='in_progress')),
                             ('/attempts/1/jobs?', lambda v: v['jobs'][0].update(conclusion='failure')),
                             ('/attempts/1/jobs?', lambda v: v['jobs'][0]['steps'][0].update(conclusion='failure')),
                             ('/artifacts?', lambda v: v['artifacts'][0].update(expired=True)),
                             ('/artifacts?', lambda v: v['artifacts'][0].update(workflow_run={'id': 1})),
                             ('/artifacts?', lambda v: v['artifacts'][0].update(digest='unreviewed')),
                             ('/artifacts?', lambda v: (v['artifacts'].append(v['artifacts'][0] | {'id': 334}), v.update(total_count=2)))]:
            def change(path, value):
                if part == path or '?' in part and part in path: mutate(value)
                return value
            with self.subTest(part=part, mutate=mutate), self.assertRaises(RuntimeError): self.admission(change)


class UpdatePayload(unittest.TestCase):
    def package(self, change=None, previous_change=None):
        raw, digest, previous, files, after = update_archive_fixture(change)
        before = copy.deepcopy(previous['expected'][m.UPDATE_PATH])
        if previous_change: previous_change(previous, files)
        with patch.object(m, 'MANIFEST_SHA', previous['manifest']), patch.object(m, 'UPDATE_MANIFEST_SHA', digest), patch.object(m, 'UPDATE_BEFORE', before), \
             patch.object(m, 'UPDATE_AFTER', after), patch.object(m, 'UPDATE_RELEASE', RELEASE):
            result, output = m.update_package(raw, previous, files)
        return result, output, previous, after

    def test_exact_successor_packet_yields_only_the_reviewed_app(self):
        policy, files, previous, after = self.package()
        self.assertEqual(list(files), [m.UPDATE_PATH])
        self.assertEqual(policy['previous'], previous)
        self.assertEqual(policy['path'], 'visual-search/app.js')
        self.assertEqual(policy['before'], previous['expected'][m.UPDATE_PATH])
        self.assertEqual(policy['after'], after)
        self.assertEqual((policy['source'], policy['tree'], policy['release']),
                         (m.UPDATE_SOURCE, m.UPDATE_TREE, RELEASE))
        self.assertEqual(policy['operation'], m.UPDATE_OPERATION)
        self.assertEqual(m.sha(base64.b64decode(files[m.UPDATE_PATH])), after['sha256'])
        self.assertEqual(set(previous['expected']), set(m.ADDITIONS) | {m.ENTRY})
        self.assertNotIn(m.ENTRY, files)
        self.assertNotIn('poisk-turov-old/index.php', files)

    def test_another_served_change_missing_path_or_wrong_graph_is_rejected(self):
        def foreign_asset(manifest, files):
            path = 'poisk-turov-old/index.php'; files[path] = b'unreviewed fallback rewrite'
            row = next(row for row in manifest['files']['added'] if row['path'] == path)
            row['served'] = {'size': len(files[path]), 'mode': '0644', 'sha256': m.sha(files[path])}
        def missing_asset(manifest, files):
            row = manifest['files']['added'].pop(0); del files[row['path']]
        def changed_entry(manifest, files):
            files[m.ENTRY] = b'unreviewed active entry rewrite'
            manifest['files']['replaced'][0]['served'] = {
                'size': len(files[m.ENTRY]), 'mode': '0644', 'sha256': m.sha(files[m.ENTRY])}
        for change in [foreign_asset, missing_asset, changed_entry,
                       lambda value, files: value['candidate'].update(sha=m.SOURCE),
                       lambda value, files: value['candidate'].update(tree_sha='0'*40),
                       lambda value, files: value['profile'].update(profile='review'),
                       lambda value, files: files.update({'api-v2.php': b'not authorized'})]:
            with self.subTest(change=change), self.assertRaises(RuntimeError): self.package(change)

    def test_unreviewed_app_bytes_mode_or_predecessor_are_rejected(self):
        def changed_app(manifest, files):
            files[m.UPDATE_PATH] = b'other compiled app'
            row = next(row for row in manifest['files']['added'] if row['path'] == m.UPDATE_PATH)
            row['served'] = {'size': len(files[m.UPDATE_PATH]), 'mode': '0644', 'sha256': m.sha(files[m.UPDATE_PATH])}
        def wrong_mode(manifest, files):
            next(row for row in manifest['files']['added'] if row['path'] == m.UPDATE_PATH)['served']['mode'] = '0666'
        for change in (changed_app, wrong_mode):
            with self.subTest(change=change), self.assertRaises(RuntimeError): self.package(change)
        for change in [lambda policy, files: policy.update(source='0'*40),
                       lambda policy, files: policy.update(operation=m.OPERATION),
                       lambda policy, files: policy['expected'][m.UPDATE_PATH].update(sha256='0'*64)]:
            with self.subTest(previous_change=change), self.assertRaises(RuntimeError): self.package(previous_change=change)


class UpdateParentEvidence(unittest.TestCase):
    def metadata(self, path):
        parent = m.UPDATE_PARENT
        if path == '/actions/runs/'+parent['run_id']:
            return {'id': int(parent['run_id']), 'head_sha': parent['control_source_sha'], 'head_branch': 'main',
                    'path': m.WORKFLOW, 'event': 'issue_comment', 'run_attempt': 1, 'status': 'completed',
                    'conclusion': 'success', 'actor': {'login': 'pyatkoff'}, 'triggering_actor': {'login': 'pyatkoff'}}
        if '/attempts/1/jobs?' in path:
            return {'total_count': 1, 'jobs': [{'id': parent['job_id'], 'run_id': int(parent['run_id']),
                    'name': 'public-install', 'status': 'completed', 'conclusion': 'success', 'steps': [
                        {'name': 'Run python3 scripts/deploy/search3_public_install.py continue',
                         'status': 'completed', 'conclusion': 'success'}]}]}
        if path == '/actions/artifacts/'+str(parent['artifact_id']):
            return {'id': parent['artifact_id'], 'name': 'search3-public-install-'+parent['run_id']+'-1',
                    'expired': False, 'workflow_run': {'id': int(parent['run_id'])}, 'digest': 'sha256:'+parent['artifact_sha256']}
        if '/issues/4709/comments?' in path:
            owner = {'user': {'id': 226193297}, 'author_association': 'OWNER'}
            tokens = [str(parent[key]) for key in ('run_id', 'control_source_sha', 'command_comment_id', 'job_id',
                      'artifact_id', 'artifact_sha256', 'receipt_sha256', 'snapshot_manifest_sha256')]
            return [owner | {'id': parent['command_comment_id'],
                             'body': f'{m.CONTINUATION_PREFIX} {parent["control_source_sha"]} {m.CONTINUATION_OPERATION}'},
                    owner | {'id': parent['terminal_comment_id'], 'body': ' '.join(tokens+['installed_verified', 'NO_REPLAY'])}]
        raise AssertionError('Unexpected predecessor dependency: '+path)

    def verify(self, change=None, metadata=None, extra=False):
        raw, digest = update_parent_archive(change, extra)
        parent = m.UPDATE_PARENT | {'artifact_sha256': m.sha(raw), 'receipt_sha256': digest}
        old_raw, old_digest = archive_fixture()
        with patch.object(m, 'MANIFEST_SHA', old_digest): previous, _ = m.package(old_raw, continuation=True)
        def api(path):
            value = self.metadata(path)
            return metadata(path, value) if metadata else value
        with patch.object(m, 'UPDATE_PARENT', parent), patch.object(m, 'api', side_effect=api):
            return m.update_parent_evidence(raw, previous)

    def test_known_successful_predecessor_receipt_is_accepted(self):
        self.assertEqual(self.verify(), update_parent_result())

    def test_failed_unknown_or_mutated_predecessor_never_authorizes_update(self):
        for change in [lambda v: v.update(status='unknown_no_replay'), lambda v: v.update(phase='remote'),
                       lambda v: v.update(ssh_started=False), lambda v: v.update(read_only=True),
                       lambda v: v.update(run_id=m.CONTINUATION_PARENT['run_id']),
                       lambda v: v.update(extra='unreviewed'),
                       lambda v: v['remote'].update(status='blocked_no_replay'),
                       lambda v: v['remote'].update(phase='entry'),
                       lambda v: v['remote'].update(source_sha=m.UPDATE_SOURCE),
                       lambda v: v['remote'].update(manifest_sha256=m.UPDATE_MANIFEST_SHA),
                       lambda v: v['remote'].update(written_files=[m.ENTRY]),
                       lambda v: v['remote'].update(rollback_files=[m.ENTRY]),
                       lambda v: v['remote'].update(snapshot_verified=False),
                       lambda v: v['remote'].update(restore_drill_verified=False),
                       lambda v: v['remote'].update(snapshot_manifest_sha256='0'*64),
                       lambda v: v['remote'].update(parent_namespace_absent=False),
                       lambda v: v['remote']['private_checks'].update(public_config=False),
                       lambda v: v['remote'].update(supplier_calls=1),
                       lambda v: v['remote'].update(database_writes='unknown'),
                       lambda v: v['remote'].update(lead_calls=False)]:
            with self.subTest(change=change), self.assertRaises(RuntimeError): self.verify(change)
        with self.assertRaises(RuntimeError): self.verify(extra=True)
        with patch.object(m, 'api') as api, self.assertRaises(RuntimeError): m.update_parent_evidence(b'wrong ZIP', {})
        api.assert_not_called()

    def test_parent_run_job_artifact_and_owner_acceptance_are_indivisible(self):
        run = '/actions/runs/'+m.UPDATE_PARENT['run_id']
        for part, change in [(run, lambda v: v.update(head_sha='f'*40)),
                             (run, lambda v: v.update(run_attempt=2)),
                             (run, lambda v: v.update(run_attempt=True)),
                             (run, lambda v: v.update(conclusion='failure')),
                             (run, lambda v: v.update(status='in_progress')),
                             (run, lambda v: v.update(triggering_actor={'login': 'other'})),
                             ('/jobs?', lambda v: v['jobs'][0].update(id=2)),
                             ('/jobs?', lambda v: v['jobs'][0].update(run_id=2)),
                             ('/jobs?', lambda v: v['jobs'][0]['steps'][0].update(conclusion='skipped')),
                             ('/actions/artifacts/', lambda v: v.update(expired=True)),
                             ('/actions/artifacts/', lambda v: v.update(digest='sha256:'+'0'*64)),
                             ('/actions/artifacts/', lambda v: v['workflow_run'].update(id=2)),
                             ('/comments?', lambda v: v[-1].update(author_association='MEMBER')),
                             ('/comments?', lambda v: v[-1].update(body='unaccepted')),
                             ('/comments?', lambda v: v.append(copy.deepcopy(v[0]) | {'id': 999}))]:
            def mutation(path, value):
                if path == part if part == run else part in path: change(value)
                return value
            with self.subTest(part=part, change=change), self.assertRaises(RuntimeError): self.verify(metadata=mutation)


class PreflightEvidence(unittest.TestCase):
    def setUp(self):
        raw, digest = archive_fixture()
        with patch.object(m, 'MANIFEST_SHA', digest): self.policy, _ = m.package(raw)

    def metadata(self, path):
        if path == '/actions/runs/' + str(m.PREFLIGHT_RUN):
            return {'id': m.PREFLIGHT_RUN, 'head_sha': m.PREFLIGHT_CONTROL, 'head_branch': 'main', 'event': 'issue_comment',
                    'path': m.WORKFLOW, 'run_attempt': 1, 'status': 'completed', 'conclusion': 'success',
                    'actor': {'login': 'pyatkoff'}, 'triggering_actor': {'login': 'pyatkoff'}}
        if path == '/actions/artifacts/' + str(m.PREFLIGHT_ARTIFACT):
            return {'id': m.PREFLIGHT_ARTIFACT, 'name': m.PREFLIGHT_ARTIFACT_NAME, 'expired': False,
                    'workflow_run': {'id': m.PREFLIGHT_RUN}, 'digest': 'sha256:' + m.PREFLIGHT_ARTIFACT_SHA}
        raise AssertionError(path)

    def verify(self, change=None, metadata=None, extra=False):
        raw, digest = preflight_fixture(change, extra)
        def api(path):
            value = self.metadata(path)
            return metadata(path, value) if metadata else value
        with patch.object(m, 'PREFLIGHT_ARTIFACT_SHA', m.sha(raw)), patch.object(m, 'PREFLIGHT_RESULT_SHA', digest), \
             patch.object(m, 'api', side_effect=api):
            m.preflight_evidence(raw, self.policy)

    def test_exact_reviewed_install_map_preserves_historical_inspection(self):
        raw, digest = archive_fixture()
        with patch.object(m, 'MANIFEST_SHA', digest):
            inspection, _ = m.package(raw, True); installation, _ = m.package(raw)
        self.assertEqual(len(m.PROTECTED), 49)
        self.assertEqual(set(m.PROTECTED), set(m.INSTALL_PROTECTED))
        self.assertEqual(set(m.INSTALL_REVIEWED), {
            'data/db-v1.php', '_preview/search3-local-candidate/data/anytour-canonical-catalog-v1.php',
            '_preview/search3-local-candidate/data/hotel-details-v1.php'})
        self.assertEqual({p for p in m.PROTECTED if m.PROTECTED[p] != m.PREFLIGHT_ACCEPTED_PROTECTED[p]}, set(m.INSTALL_REVIEWED))
        self.assertEqual({p for p in m.PREFLIGHT_ACCEPTED_PROTECTED if m.PREFLIGHT_ACCEPTED_PROTECTED[p] != m.INSTALL_PROTECTED[p]}, {NEXT_ENTRY})
        self.assertEqual(inspection['protected'], m.PROTECTED); self.assertEqual(installation['protected'], m.INSTALL_PROTECTED)
        self.assertNotIn('opaque_presence', inspection)
        self.assertEqual(installation['opaque_presence'], {'config.php': True, 'site_conf.php': False})
        self.assertEqual(m.PROTECTED['data/db-v1.php'], 'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13')
        self.assertEqual(m.PROTECTED['_preview/search3-local-candidate/data/anytour-canonical-catalog-v1.php'],
                         '06e737f7b00bfe17c1f9410c19f880d65b0bfad3345f0d79e62727282601041a')
        self.assertEqual(m.PROTECTED['_preview/search3-local-candidate/data/hotel-details-v1.php'],
                         '137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f')
        self.assertEqual(len(m.ADDITIONS), 41)
        self.assertEqual(set(installation['expected']), set(m.ADDITIONS) | {m.ENTRY})
        self.assertFalse(set(m.INSTALL_PROTECTED) & set(installation['expected']))
        self.assertFalse(any(path.startswith(('data/', '_preview/')) for path in installation['expected']))
        self.verify()

    def test_accepted_next_update_does_not_relabel_consumed_preflight(self):
        legacy = '159060195fa7a41e6a95f5e8e0fb302f04c6c94c9e49b5c1845eee9210626454'
        current = 'bfda6c091544c13d157537879adaa7037e3bff3dface2fb5b3a77067808da339'
        self.assertEqual(m.CURRENT_NEXT_REVIEWED, {NEXT_ENTRY: {
            'source_sha': '1a22691517b53e23ea2fa98d02f857b11a118392',
            'release_sha': '71fd65ae80e2b8c4b00b80f3bafe36dfa9bb2426',
            'run_id': 44830908434, 'artifact_id': 14675809225,
            'artifact_sha256': '7d3012a431231236e26beec267abca82b5bd714b9d142c050da2e87f8c1e3cde',
            'receipt_sha256': 'c1fe72833d1bc81cbfc6530a322947a1c20c4eaa607136c141016b65e7ea7065',
            'sha256': current, 'size': 860}})
        self.assertEqual(m.PROTECTED[NEXT_ENTRY], legacy)
        self.assertEqual(m.PREFLIGHT_ACCEPTED_PROTECTED[NEXT_ENTRY], legacy)
        self.assertEqual(self.policy['protected'][NEXT_ENTRY], current)
        policy = copy.deepcopy(self.policy)
        self.verify()
        self.assertEqual(self.policy, policy, 'consumed evidence cannot replace current dependency expectations')
        for digest in (current, '0' * 64):
            with self.subTest(digest=digest), self.assertRaisesRegex(RuntimeError, 'preflight_observed'):
                self.verify(lambda v: v['remote']['observed'][NEXT_ENTRY].update(sha256=digest))
        with self.assertRaisesRegex(RuntimeError, 'preflight_deviations'):
            self.verify(lambda v: v['remote']['mismatches'].append({'path': NEXT_ENTRY, 'reason': 'dependency_drift'}))
        for digest in (legacy, '0' * 64):
            self.policy['protected'] = {**policy['protected'], NEXT_ENTRY: digest}
            with self.subTest(policy_digest=digest), self.assertRaisesRegex(RuntimeError, 'preflight_policy'):
                self.verify()

    def test_archive_and_json_tampering_are_rejected(self):
        raw, digest = preflight_fixture()
        with patch.object(m, 'api') as api:
            with self.assertRaisesRegex(RuntimeError, 'preflight_artifact_digest'): m.preflight_evidence(raw, self.policy)
            with patch.object(m, 'PREFLIGHT_ARTIFACT_SHA', m.sha(raw)), self.assertRaisesRegex(RuntimeError, 'preflight_digest'):
                m.preflight_evidence(raw, self.policy)
            api.assert_not_called()
        with self.assertRaisesRegex(RuntimeError, 'preflight_artifact'): self.verify(extra=True)

    def test_historical_identity_is_preserved_across_candidate_changes(self):
        self.assertEqual(m.PREFLIGHT_SOURCE, 'f4d14cb7a66be040798693e9a47a2d023c48de7e')
        self.assertEqual(m.PREFLIGHT_MANIFEST_SHA, '3ef8fadb6db83cff9709fdc06d0dcaf41673456c92b1e77f40cdc8444ae8da5f')
        self.assertEqual((m.PREFLIGHT_RUN, m.PREFLIGHT_CONTROL, m.PREFLIGHT_COMMAND, m.PREFLIGHT_RECEIPT_COMMENT), (
            44790620377, '927d3f213eadad98dcf4771c0c21612af7bfd0ed', 7047112039, 7047276834))
        self.assertEqual(m.PREFLIGHT_RESULT_SHA, 'dafc322bd6c6a0c8922eccfa4f45694cefdec190ee24a454c30e7fd2afaa298b')
        self.assertEqual(m.PREFLIGHT_ARTIFACT_SHA, 'a1edf23fd86e24bea82dc2d97de89e47b3fb6d75f469d374683b6c205dd21cf1')
        next_source, next_manifest = 'e' * 40, 'd' * 64
        original_policy = copy.deepcopy(self.policy)
        self.policy |= {'source': next_source, 'manifest': next_manifest}
        with patch.object(m, 'SOURCE', next_source), patch.object(m, 'MANIFEST_SHA', next_manifest):
            self.verify()
            for field, value in [('source_sha', next_source), ('manifest_sha256', next_manifest)]:
                with self.subTest(field=field), self.assertRaisesRegex(RuntimeError, 'inspection_identity'):
                    self.verify(lambda result: result['remote'].update({field: value}))
        self.assertEqual(self.policy, original_policy | {'source': next_source, 'manifest': next_manifest},
                         'historical validation cannot mutate the current install policy')

    def test_foreign_incomplete_or_mutating_receipts_are_rejected(self):
        for change in [lambda v: v.update(control_source_sha='f'*40), lambda v: v.update(run_id='2'),
                       lambda v: v.update(command_comment_id=2), lambda v: v.update(status='preflight_match'),
                       lambda v: v.update(phase='remote'), lambda v: v.update(read_only=False), lambda v: v.update(server_writes=1),
                       lambda v: v.update(extra=True), lambda v: v['remote'].update(source_sha='f'*40),
                       lambda v: v['remote'].update(manifest_sha256='f'*64),
                       lambda v: v['remote'].update(supplier_calls=1), lambda v: v['remote'].update(database_reads=1),
                       lambda v: v['remote']['observed'].pop(m.ENTRY),
                       lambda v: v['remote']['observed'].update({'unknown.php': {'state': 'absent'}}),
                       lambda v: v['remote']['private_checks']['site_config'].update(present=True),
                       lambda v: v['remote']['private_checks']['public_config'].update(matches_expected=False),
                       lambda v: v['remote']['private_checks']['anex_private_config'].update(unchanged=False),
                       lambda v: v['remote']['mismatches'].pop(),
                       lambda v: v['remote']['mismatches'][0].update(reason='predecessor_drift')]:
            with self.subTest(change=change), self.assertRaises(RuntimeError): self.verify(change)

    def test_only_reviewed_bytes_modes_and_absences_are_accepted(self):
        for path in m.INSTALL_REVIEWED:
            for field, value in [('sha256', m.PROTECTED[path]), ('size', 0), ('mode', '0600')]:
                with self.subTest(path=path, field=field), self.assertRaisesRegex(RuntimeError, 'preflight_observed'):
                    self.verify(lambda v: v['remote']['observed'][path].update({field: value}))
        for path in (m.ENTRY, 'data/departures-v1.php'):
            with self.subTest(path=path), self.assertRaisesRegex(RuntimeError, 'preflight_observed'):
                self.verify(lambda v: v['remote']['observed'][path].update(sha256='0'*64))
        with self.assertRaisesRegex(RuntimeError, 'preflight_observed'):
            self.verify(lambda v: v['remote']['observed'].update({m.ADDITIONS[0]: dict(m.ENTRY_BEFORE)}))

    def test_original_run_and_artifact_metadata_are_required(self):
        for kind, field, value in [('runs', 'id', 2), ('runs', 'head_sha', 'f'*40), ('runs', 'head_branch', 'other'),
                                   ('runs', 'event', 'workflow_dispatch'), ('runs', 'run_attempt', 2), ('runs', 'path', 'other'),
                                   ('runs', 'status', 'in_progress'), ('runs', 'conclusion', 'failure'),
                                   ('runs', 'triggering_actor', {'login': 'other'}), ('artifacts', 'id', 2),
                                   ('artifacts', 'name', 'other'), ('artifacts', 'expired', True),
                                   ('artifacts', 'workflow_run', {'id': 2}), ('artifacts', 'digest', 'sha256:'+'0'*64)]:
            def changed(path, row):
                if '/' + kind + '/' in path: row[field] = value
                return row
            with self.subTest(kind=kind, field=field), self.assertRaises(RuntimeError): self.verify(metadata=changed)

    def test_rejected_preflight_never_reaches_ssh(self):
        with tempfile.TemporaryDirectory() as directory:
            event_path = Path(directory) / 'event.json'; event_path.write_text(json.dumps(event()))
            env = environment() | {'RUNNER_TEMP': directory, 'GITHUB_EVENT_PATH': str(event_path)}
            with patch.dict(os.environ, env), patch.object(m, 'admission'), patch.object(m, 'download', return_value=b'x'), \
                 patch.object(m, 'signing_evidence'), patch.object(m, 'package', return_value=(self.policy, {})), \
                 patch.object(m, 'carrier') as carrier, self.assertRaisesRegex(RuntimeError, 'public_install_not_verified'):
                m.install()
            carrier.run.assert_not_called()
            evidence = json.loads((Path(directory) / 'search3-public-install/evidence.json').read_text())
            self.assertEqual(evidence['phase'], 'artifact'); self.assertIs(evidence['ssh_started'], False)


class ContinuationEvidence(unittest.TestCase):
    def metadata(self, path):
        parent = m.CONTINUATION_PARENT
        if path == '/actions/runs/' + PARENT_IDENTITY['run_id']:
            return {'id': 44840666320, 'head_sha': PARENT_IDENTITY['control_source_sha'], 'head_branch': 'main',
                    'event': 'issue_comment', 'path': m.WORKFLOW, 'run_attempt': 1,
                    'status': 'completed', 'conclusion': 'failure',
                    'actor': {'login': 'pyatkoff'}, 'triggering_actor': {'login': 'pyatkoff'}}
        if '/attempts/1/jobs?' in path:
            return {'total_count': 1, 'jobs': [{'id': 136647935738, 'run_id': 44840666320, 'name': 'public-install',
                    'status': 'completed', 'conclusion': 'failure', 'steps': [
                        {'name': 'Run python3 scripts/deploy/search3_public_install.py ',
                         'status': 'completed', 'conclusion': 'failure'}]}]}
        if path == '/actions/artifacts/14680527168':
            return {'id': 14680527168, 'name': 'search3-public-install-44840666320-1', 'expired': False,
                    'workflow_run': {'id': 44840666320}, 'digest': 'sha256:' + parent['artifact_sha256']}
        raise AssertionError('Unexpected dependency: ' + path)

    def verify(self, change=None, metadata=None, extra=False):
        raw, digest = parent_failure_fixture(change, extra)
        parent = m.CONTINUATION_PARENT | {'artifact_sha256': m.sha(raw), 'receipt_sha256': digest}
        def api(path):
            value = self.metadata(path)
            return metadata(path, value) if metadata else value
        with patch.object(m, 'CONTINUATION_PARENT', parent), patch.object(m, 'api', side_effect=api):
            return m.continuation_parent_evidence(raw)

    def test_exact_terminal_parent_failure_is_admitted(self):
        self.verify()

    def test_unknown_mutating_or_other_parent_receipt_is_never_continued(self):
        changes = [lambda v: v.update(status='unknown_no_replay'), lambda v: v.update(phase='remote'),
                   lambda v: v.update(run_id='44840666321'), lambda v: v.update(command_comment_id=2),
                   lambda v: v.update(control_source_sha='f'*40), lambda v: v.update(read_only=True),
                   lambda v: v.update(ssh_started=False), lambda v: v.update(extra='unreviewed'),
                   lambda v: v['remote'].update(status='unknown_no_replay'),
                   lambda v: v['remote'].update(status='rolled_back_no_replay'),
                   lambda v: v['remote'].update(status='installed_verified'),
                   lambda v: v['remote'].update(phase='additions'),
                   lambda v: v['remote'].update(reason='predecessor_drift'),
                   lambda v: v['remote'].update(source_sha='f'*40),
                   lambda v: v['remote'].update(manifest_sha256='0'*64),
                   lambda v: v['remote'].update(operation_id=m.CONTINUATION_OPERATION),
                   lambda v: v['remote'].update(run_id='44840666321'),
                   lambda v: v['remote'].update(written_files=[m.ENTRY]),
                   lambda v: v['remote'].update(rollback_files=[m.ENTRY]),
                   lambda v: v['remote'].update(snapshot_verified=True),
                   lambda v: v['remote'].update(restore_drill_verified=True),
                   lambda v: v['remote'].update(snapshot_manifest_sha256='7'*64),
                   lambda v: v['remote'].update(reserved=True),
                   lambda v: v['remote'].update(supplier_calls=1),
                   lambda v: v['remote'].update(database_writes='unknown'),
                   lambda v: v['remote'].update(lead_calls=False)]
        for change in changes:
            with self.subTest(change=change), self.assertRaises(RuntimeError): self.verify(change)
        with self.assertRaises(RuntimeError): self.verify(extra=True)
        with patch.object(m, 'api') as api, self.assertRaises(RuntimeError):
            m.continuation_parent_evidence(b'not the retained artifact')
        api.assert_not_called()

    def test_parent_run_job_and_artifact_metadata_cannot_be_substituted(self):
        for part, change in [('/actions/runs/44840666320', lambda v: v.update(head_sha='f'*40)),
                             ('/actions/runs/44840666320', lambda v: v.update(id=2)),
                             ('/actions/runs/44840666320', lambda v: v.update(path='other.yml')),
                             ('/actions/runs/44840666320', lambda v: v.update(event='workflow_dispatch')),
                             ('/actions/runs/44840666320', lambda v: v.update(head_branch='other')),
                             ('/actions/runs/44840666320', lambda v: v.update(run_attempt=2)),
                             ('/actions/runs/44840666320', lambda v: v.update(status='in_progress')),
                             ('/actions/runs/44840666320', lambda v: v.update(conclusion='success')),
                             ('/actions/runs/44840666320', lambda v: v.update(triggering_actor={'login':'other'})),
                             ('/jobs?', lambda v: v['jobs'][0].update(id=2)),
                             ('/jobs?', lambda v: v['jobs'][0].update(run_id=2)),
                             ('/jobs?', lambda v: v['jobs'][0].update(conclusion='success')),
                             ('/jobs?', lambda v: v['jobs'][0]['steps'][0].update(conclusion='skipped')),
                             ('/jobs?', lambda v: (v.update(total_count=2), v['jobs'].append(copy.deepcopy(v['jobs'][0])))),
                             ('/actions/artifacts/', lambda v: v.update(id=2)),
                             ('/actions/artifacts/', lambda v: v.update(expired=True)),
                             ('/actions/artifacts/', lambda v: v.update(workflow_run={'id':2})),
                             ('/actions/artifacts/', lambda v: v.update(digest='sha256:'+'0'*64))]:
            def mutate(path, value):
                if (path == part if part == '/actions/runs/44840666320' else part in path): change(value)
                return value
            with self.subTest(part=part, change=change), self.assertRaises(RuntimeError): self.verify(metadata=mutate)

    def test_rejected_parent_never_reaches_ssh(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)/'event.json'; path.write_text(json.dumps(continuation_event()))
            env = environment() | {'RUNNER_TEMP':directory, 'GITHUB_EVENT_PATH':str(path),
                                    'GITHUB_RUN_ID':CONTINUATION_IDENTITY['run_id']}
            with patch.dict(os.environ, env), patch.object(m, 'admission'), patch.object(m, 'download', return_value=b'x'), \
                 patch.object(m, 'package', return_value=({}, {})), patch.object(m, 'signing_evidence'), \
                 patch.object(m, 'preflight_evidence'), patch.object(m, 'continuation_parent_evidence', wraps=m.continuation_parent_evidence) as parent, \
                 patch.object(m, 'carrier') as carrier, patch('builtins.print'), self.assertRaisesRegex(RuntimeError, 'public_install_not_verified'):
                m.install(continuation=True)
            parent.assert_called_once_with(b'x'); carrier.run.assert_not_called()
            evidence = json.loads((Path(directory)/'search3-public-install/evidence.json').read_text())
            self.assertEqual(evidence['phase'], 'artifact'); self.assertIs(evidence['ssh_started'], False)

    def test_continuation_admission_is_rechecked_before_ssh(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)/'event.json'; path.write_text(json.dumps(continuation_event()))
            env = environment() | {'RUNNER_TEMP':directory, 'GITHUB_EVENT_PATH':str(path),
                'GITHUB_RUN_ID':CONTINUATION_IDENTITY['run_id'], 'PREVIEW_HOST':'example.invalid',
                'PREVIEW_USER':'fixture', 'PREVIEW_KEY':'PRIVATE KEY fixture'}
            with patch.dict(os.environ, env), patch.object(m, 'admission', side_effect=[None, RuntimeError('operation_already_reserved')]) as admission, \
                 patch.object(m, 'download', return_value=b'x'), patch.object(m, 'package', return_value=({}, {})), \
                 patch.object(m, 'signing_evidence'), patch.object(m, 'preflight_evidence'), patch.object(m, 'continuation_parent_evidence'), \
                 patch.object(m.carrier, 'run', return_value=b'fixture known host') as run, patch('builtins.print'), \
                 self.assertRaisesRegex(RuntimeError, 'public_install_not_verified'):
                m.install(continuation=True)
            self.assertEqual(admission.call_count, 2)
            self.assertEqual([call.args[0][0] for call in run.call_args_list], ['ssh-keyscan'])
            evidence = json.loads((Path(directory)/'search3-public-install/evidence.json').read_text())
            self.assertIs(evidence['ssh_started'], False)


class RollbackAdmission(unittest.TestCase):
    def metadata(self, path):
        if path == '/git/ref/heads/main': return {'object': {'sha': ROLLBACK_IDENTITY['control_source_sha']}}
        if path.startswith('/issues/4709/comments?'): return [event()['comment'], rollback_event()['comment']]
        if path == '/actions/runs/' + IDENTITY['run_id']:
            return {'id': int(IDENTITY['run_id']), 'head_sha': CONTROL, 'head_branch': 'main', 'event': 'issue_comment',
                    'path': m.WORKFLOW, 'run_attempt': 1, 'status': 'completed', 'conclusion': 'success',
                    'actor': {'login': 'pyatkoff'}, 'triggering_actor': {'login': 'pyatkoff'}}
        if '/attempts/1/jobs?' in path:
            return {'total_count': 1, 'jobs': [{'id': 222, 'run_id': int(IDENTITY['run_id']), 'name': 'public-install',
                    'status': 'completed', 'conclusion': 'success', 'steps': [
                        {'name': 'Run python3 scripts/deploy/search3_public_install.py ', 'status': 'completed', 'conclusion': 'success'}]}]}
        if '/artifacts?' in path:
            return {'total_count': 1, 'artifacts': [{'id': 333, 'name': 'search3-public-install-'+IDENTITY['run_id']+'-1',
                    'expired': False, 'workflow_run': {'id': int(IDENTITY['run_id'])}, 'digest': 'sha256:'+'5'*64}]}
        raise AssertionError('Unexpected dependency: '+path)

    def admission(self, change=None):
        def api(path):
            value = self.metadata(path)
            return change(path, value) if change else value
        with patch.object(m, 'api', side_effect=api), patch.object(m, 'reviewed_control') as control, \
             patch.object(m, 'RELEASE', None), patch.object(m, 'final_heads', side_effect=AssertionError('release gate')), \
             patch.object(m, 'signing_evidence', side_effect=AssertionError('signer gate')):
            original, artifact = m.rollback_admission(ROLLBACK_IDENTITY, IDENTITY['run_id'])
            control.assert_called_once_with(ROLLBACK_IDENTITY['control_source_sha'])
            self.assertEqual(original, IDENTITY); self.assertEqual(artifact['id'], 333)

    def test_fixed_rollback_command_and_same_owner_boundary(self):
        env = environment() | {'GITHUB_SHA': ROLLBACK_IDENTITY['control_source_sha'], 'GITHUB_RUN_ID': ROLLBACK_IDENTITY['run_id']}
        self.assertEqual(m.rollback_event_identity(rollback_event(), env), (ROLLBACK_IDENTITY, IDENTITY['run_id']))
        self.assertEqual(m.command_mode(['rollback']), 'rollback')
        with self.assertRaisesRegex(RuntimeError, 'command'): m.event_identity(rollback_event(), env)
        with self.assertRaisesRegex(RuntimeError, 'command'): m.rollback_event_identity(event(), environment())
        for field, value in [('GITHUB_ACTOR', 'other'), ('GITHUB_TRIGGERING_ACTOR', 'other'), ('GITHUB_RUN_ATTEMPT', '2'),
                             ('GITHUB_REF', 'refs/heads/feature'), ('GITHUB_WORKFLOW_REF', 'other')]:
            with self.subTest(field=field), self.assertRaises(RuntimeError): m.rollback_event_identity(rollback_event(), env | {field: value})
        for change in [lambda e: e['comment'].update(body=e['comment']['body']+' extra'),
                       lambda e: e['comment'].update(body=e['comment']['body'].replace(IDENTITY['run_id'], '../target')),
                       lambda e: e['comment'].update(body=e['comment']['body'].replace(IDENTITY['run_id'], '0')),
                       lambda e: e['comment']['user'].update(id=True), lambda e: e['comment'].update(author_association='MEMBER'),
                       lambda e: e['issue'].update(pull_request={}), lambda e: e.update(action='edited')]:
            value = rollback_event(); change(value)
            with self.assertRaises(RuntimeError): m.rollback_event_identity(value, env)
        for arguments in (['rollback','123456'], ['rollback','/tmp/root'], ['--rollback']):
            with self.assertRaisesRegex(RuntimeError, 'usage'): m.command_mode(arguments)

    def test_recovery_uses_original_success_without_signer_or_release(self):
        self.admission()

    def test_continuation_rollback_selects_actual_successful_child_not_failed_parent(self):
        rollback = rollback_event()['comment']
        rollback['body'] = rollback['body'].replace(IDENTITY['run_id'], CONTINUATION_IDENTITY['run_id'])
        def api(path):
            # Successful child recovery does not depend on mutable parent comments.
            if '/comments?' in path: return [continuation_event()['comment'], rollback]
            value = self.metadata(path.replace(CONTINUATION_IDENTITY['run_id'], IDENTITY['run_id']))
            if path == '/actions/runs/' + CONTINUATION_IDENTITY['run_id']: value['id'] = int(CONTINUATION_IDENTITY['run_id'])
            if '/jobs?' in path: value['jobs'][0]['run_id'] = int(CONTINUATION_IDENTITY['run_id'])
            if '/artifacts?' in path:
                value['artifacts'][0].update(name='search3-public-install-'+CONTINUATION_IDENTITY['run_id']+'-1',
                                             workflow_run={'id':int(CONTINUATION_IDENTITY['run_id'])})
            return value
        with patch.object(m, 'api', side_effect=api), patch.object(m, 'reviewed_control'), \
             patch.object(m, 'final_heads', side_effect=AssertionError('release gate')):
            original, _ = m.rollback_admission(ROLLBACK_IDENTITY, CONTINUATION_IDENTITY['run_id'])
        self.assertEqual(original, CONTINUATION_IDENTITY | {'operation_id':m.CONTINUATION_OPERATION})
        with patch.object(m, 'api') as calls, self.assertRaises(RuntimeError):
            m.rollback_commands(ROLLBACK_IDENTITY, PARENT_IDENTITY['run_id'])
        calls.assert_not_called()
        def stale_parent_head(path):
            value = api(path)
            if path == '/actions/runs/' + CONTINUATION_IDENTITY['run_id']:
                value['head_sha'] = PARENT_IDENTITY['control_source_sha']
            return value
        with patch.object(m, 'api', side_effect=stale_parent_head), patch.object(m, 'reviewed_control'), self.assertRaises(RuntimeError):
            m.rollback_admission(ROLLBACK_IDENTITY, CONTINUATION_IDENTITY['run_id'])

    def test_run_job_artifact_and_unique_commands_cannot_be_substituted(self):
        for part, change in [('/comments?', lambda v: v.append(rollback_event()['comment'] | {'id': 9003})),
                             ('/comments?', lambda v: v.append(event()['comment'] | {'id': 9003})),
                             ('/comments?', lambda v: v[0].update(author_association='MEMBER')),
                             ('/comments?', lambda v: v[1].update(body=v[1]['body']+' extra')),
                             ('/actions/runs/123456', lambda v: v.update(head_sha='f'*40)),
                             ('/actions/runs/123456', lambda v: v.update(run_attempt=2)),
                             ('/actions/runs/123456', lambda v: v.update(conclusion='failure')),
                             ('/actions/runs/123456', lambda v: v.update(triggering_actor={'login':'other'})),
                             ('/jobs?', lambda v: v['jobs'][0].update(conclusion='skipped')),
                             ('/jobs?', lambda v: v['jobs'][0]['steps'][0].update(conclusion='skipped')),
                             ('/artifacts?', lambda v: v['artifacts'][0].update(expired=True)),
                             ('/artifacts?', lambda v: v['artifacts'][0].update(digest='sha256:invalid')),
                             ('/artifacts?', lambda v: v['artifacts'][0].update(workflow_run={'id':1})),
                             ('/artifacts?', lambda v: (v.update(total_count=2),v['artifacts'].append(v['artifacts'][0] | {'id':334})))]:
            def mutate(path, value):
                if (path == part if part == '/actions/runs/123456' else part in path): change(value)
                return value
            with self.subTest(part=part, change=change), self.assertRaises(RuntimeError): self.admission(mutate)

    def test_original_install_artifact_requires_complete_verified_receipt(self):
        raw, digest = archive_fixture()
        with patch.object(m, 'MANIFEST_SHA', digest): policy, _ = m.package(raw)
        def fixture(change=None):
            value = {**IDENTITY, 'operation_id': m.OPERATION, 'read_only': False, 'phase': 'complete',
                     'ssh_started': True, 'status': 'installed_verified', 'remote': installed_result()}
            if change: change(value)
            stream = io.BytesIO()
            with zipfile.ZipFile(stream, 'w') as archive: archive.writestr('evidence.json', json.dumps(value))
            return stream.getvalue()
        self.assertEqual(m.rollback_source_evidence(fixture(), IDENTITY, policy), installed_result())
        for change in [lambda v: v.update(status='unknown_no_replay'), lambda v: v.update(read_only=True),
                       lambda v: v.update(run_id='2'), lambda v: v.update(command_comment_id=2),
                       lambda v: v['remote'].update(status='rolled_back_no_replay'),
                       lambda v: v['remote'].update(supplier_calls=1), lambda v: v['remote']['written_files'].pop(),
                       lambda v: v['remote'].update(restore_drill_verified=False)]:
            with self.assertRaises(RuntimeError): m.rollback_source_evidence(fixture(change), IDENTITY, policy)

    def test_changed_or_duplicate_command_is_checked_again_before_ssh(self):
        raw, digest = archive_fixture()
        with patch.object(m, 'MANIFEST_SHA', digest): policy, _ = m.package(raw)
        for mutate in [lambda rows: rows[1].update(body=rows[1]['body']+' edited'),
                       lambda rows: rows.append(rollback_event()['comment'] | {'id': 9003}),
                       lambda rows: rows[0].update(body=rows[0]['body'].replace(CONTROL, 'f'*40))]:
            with self.subTest(mutate=mutate), tempfile.TemporaryDirectory() as directory:
                event_path = Path(directory)/'event.json'; event_path.write_text(json.dumps(rollback_event()))
                env = environment() | {'GITHUB_SHA': ROLLBACK_IDENTITY['control_source_sha'], 'GITHUB_RUN_ID': ROLLBACK_IDENTITY['run_id'],
                    'RUNNER_TEMP': directory, 'GITHUB_EVENT_PATH': str(event_path), 'PREVIEW_HOST': 'example.invalid',
                    'PREVIEW_USER': 'fixture', 'PREVIEW_KEY': 'PRIVATE KEY fixture'}
                rows = self.metadata('/issues/4709/comments?per_page=100&page=1'); mutate(rows)
                with patch.dict(os.environ, env), patch.object(m, 'rollback_admission', return_value=(IDENTITY, {'id':333,'digest':'sha256:'+'5'*64})), \
                     patch.object(m, 'download', return_value=b'x'), patch.object(m, 'package', return_value=(policy,{})), \
                     patch.object(m, 'rollback_source_evidence', return_value=installed_result()), patch.object(m, 'api', return_value=rows), \
                     patch.object(m, 'tv_successor_evidence', return_value={'status':'absent'}), \
                     patch.object(m, 'rollback_main'), patch.object(m.carrier, 'run', return_value=b'fixture known host') as run, \
                     patch('builtins.print'), self.assertRaisesRegex(RuntimeError, 'public_rollback_not_verified'):
                    m.rollback()
                self.assertEqual([call.args[0][0] for call in run.call_args_list], ['ssh-keyscan'])
                evidence = json.loads((Path(directory)/'search3-public-install/evidence.json').read_text())
                self.assertIs(evidence['ssh_started'], False)


class Transaction(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.base=Path(self.temp.name);self.home=self.base/'home';self.root=self.home/'www/anytoour.ru'
        self.root.mkdir(parents=True);self.old=b'original public route\n';self.put(m.ENTRY,self.old)
        self.secret=b'PRIVATE_CONFIG_SENTINEL never exported\n'
        hashes={}
        for path in m.PROTECTED:
            raw=self.secret if path.endswith('.anex-private.php') else ('protected:'+path).encode()
            self.put(path,raw);hashes[path]=m.sha(raw)
        (self.root/m.PRIVATE_CONFIG).chmod(0o600)
        self.put('config.php',self.secret);(self.root/'config.php').chmod(0o600)
        self.put('site_conf.php',b'existing site settings')
        self.files={p:base64.b64encode(('candidate:'+p).encode()).decode() for p in (*m.ADDITIONS,m.ENTRY)}
        expected={p:{'state':'present','sha256':m.sha(base64.b64decode(v)),'size':len(base64.b64decode(v)),'mode':'0644'} for p,v in self.files.items()}
        self.policy={'operation':m.OPERATION,'read_only':False,'source':m.SOURCE,'tree':m.TREE,'release':RELEASE,'manifest':m.MANIFEST_SHA,
                     'additions':m.ADDITIONS,'entry':m.ENTRY,'before':{'state':'present','sha256':m.sha(self.old),'size':len(self.old),'mode':'0644'},
                     'expected':expected,'protected':hashes,'private_config':m.PRIVATE_CONFIG,
                     'opaque_settings':m.OPAQUE_SETTINGS,'absent_dependencies':m.ABSENT_DEPENDENCIES,'signing_result':m.SIGNING_RESULT_SHA}
        self.private=self.home/'.anytoour-search3-root-gateway'/m.OPERATION
        self.identity = IDENTITY

    def use_continuation(self):
        self.policy.update(operation=m.CONTINUATION_OPERATION, parent_failure=dict(m.CONTINUATION_PARENT),
                           dependency_policy_sha256=m.CONTINUATION_POLICY_SHA,
                           opaque_presence=dict(m.INSTALL_OPAQUE_PRESENCE))
        (self.root/'site_conf.php').unlink()
        self.private = self.home/'.anytoour-search3-root-gateway'/m.CONTINUATION_OPERATION
        self.identity = CONTINUATION_IDENTITY

    def tearDown(self):
        self.base.chmod(0o700);self.home.chmod(0o700);self.temp.cleanup()

    def put(self,path,raw):
        target=self.root/path;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(raw);target.chmod(0o644)

    def execute(self, change=None, demote=False, inspect=False):
        policy=self.policy|{'operation':m.INSPECT_OPERATION,'read_only':True} if inspect else self.policy
        program=m.remote_program(policy,self.files,self.identity)
        # Only the inert native fixture selects a temporary root. Leave the
        # process HOME unchanged; production reads its existing HOME normally.
        program=program.replace("home=os.environ.get('HOME','');root=home+'/www/anytoour.ru'",
                                "home=os.environ['SEARCH3_FIXTURE_HOME'];root=home+'/www/anytoour.ru'",1)
        program=program.replace("os.environ.get('HOME','')+'/www/anytoour.ru'","os.environ['SEARCH3_FIXTURE_HOME']+'/www/anytoour.ru'",1)
        if change: program=change(program)
        preexec=None
        if demote and os.geteuid()==0:
            for path in [self.base,*self.base.rglob('*')]: os.chown(path,65534,65534)
            self.base.chmod(0o111)
            def preexec(): os.setgid(65534);os.setuid(65534)
        run=subprocess.run([sys.executable,'-I','-S','-B','-'],input=program.encode(),capture_output=True,env=os.environ|{'SEARCH3_FIXTURE_HOME':str(self.home)},cwd=self.home,preexec_fn=preexec,timeout=30)
        self.assertEqual(run.returncode,0,run.stderr.decode())
        self.assertNotIn(self.secret,run.stdout);self.assertNotIn(str(self.home).encode(),run.stdout)
        value=json.loads(run.stdout);(m.valid_inspection_result if inspect else m.valid_result)(self.identity,policy,value)
        return value

    def filesystem_snapshot(self):
        result={}
        for path in (self.base,*self.base.rglob('*')):
            value=path.lstat()
            result[str(path.relative_to(self.base))]=(value.st_mode,value.st_ino,value.st_nlink,value.st_size,value.st_mtime_ns,value.st_ctime_ns,
                m.sha(path.read_bytes()) if path.is_file() else None)
        return result

    def test_inspect_match_is_read_only_and_cannot_reach_install(self):
        before=self.filesystem_snapshot();value=self.execute(inspect=True)
        self.assertEqual(value['status'],'preflight_match');self.assertIs(value['read_only'],True);self.assertEqual(value['server_writes'],0)
        self.assertEqual(value['mismatches'],[]);self.assertEqual(self.filesystem_snapshot(),before);self.assertFalse(self.private.parent.exists())
        self.assertNotIn('written_files',value);self.restored()

    def test_reviewed_dependencies_are_rechecked_before_any_reservation(self):
        for path in (*m.INSTALL_REVIEWED, *m.CURRENT_NEXT_REVIEWED):
            target = self.root/path; original = target.read_bytes(); target.write_bytes(b'changed after inspection')
            value = self.execute()
            self.assertEqual(value['reason'], 'dependency_drift'); self.assertEqual(value['written_files'], [])
            self.assertFalse(self.private.exists()); self.assertEqual(target.read_bytes(), b'changed after inspection')
            target.write_bytes(original)

    def test_observed_private_presence_is_rechecked_before_any_reservation(self):
        self.policy['opaque_presence'] = m.INSTALL_OPAQUE_PRESENCE
        original = (self.root/'config.php').read_bytes()
        (self.root/'site_conf.php').unlink()
        for path, present in [('config.php', False), ('site_conf.php', True)]:
            with self.subTest(path=path):
                if present: self.put(path, b'new private setting owner')
                else: (self.root/path).unlink()
                before = self.filesystem_snapshot()
                value = self.execute()
                self.assertEqual(value['reason'], 'dependency_drift')
                self.assertEqual(value['written_files'], [])
                self.assertFalse(self.private.exists())
                self.assertEqual(self.filesystem_snapshot(), before)
                if present: (self.root/path).unlink()
                else:
                    self.put(path, original); (self.root/path).chmod(0o600)

    def test_inspect_mismatch_is_diagnosable_and_still_read_only(self):
        self.put('data/db-v1.php',b'installed differing public helper');before=self.filesystem_snapshot()
        value=self.execute(inspect=True);self.assertEqual(value['status'],'preflight_mismatch')
        self.assertIn({'path':'data/db-v1.php','reason':'dependency_drift'},value['mismatches'])
        self.assertEqual(value['observed']['data/db-v1.php']['sha256'],m.sha(b'installed differing public helper'))
        self.assertEqual(self.filesystem_snapshot(),before);self.assertFalse(self.private.parent.exists());self.restored()

    def test_inspect_private_config_and_settings_export_only_flags(self):
        (self.root/m.PRIVATE_CONFIG).write_bytes(b'other private credential');(self.root/'config.php').chmod(0o660)
        before=self.filesystem_snapshot();value=self.execute(inspect=True);encoded=json.dumps(value)
        self.assertEqual(value['status'],'preflight_mismatch')
        self.assertEqual({row['path'] for row in value['mismatches']},{'anex_private_config','public_config'})
        for forbidden in (m.PRIVATE_CONFIG,m.sha(b'other private credential'),m.sha(self.secret),'other private credential',str(self.home)):
            self.assertNotIn(forbidden,encoded)
        for row in value['private_checks'].values(): self.assertTrue(all(type(flag) is bool for flag in row.values()))
        self.assertEqual(self.filesystem_snapshot(),before);self.assertFalse(self.private.parent.exists())
        leaking=copy.deepcopy(value);leaking['private_checks']['anex_private_config']['sha256']=m.sha(b'other private credential')
        policy=self.policy|{'operation':m.INSPECT_OPERATION,'read_only':True}
        with self.assertRaisesRegex(RuntimeError,'inspection_private_fields'): m.valid_inspection_result(IDENTITY,policy,leaking)

    def test_inspect_unexpected_local_next_settings_never_export_private_metadata(self):
        paths=('_preview/search3-local-candidate/config.php','_preview/search3-next-candidate/config.php')
        for path in paths: self.put(path,b'PRIVATE SHADOW SETTINGS')
        before=self.filesystem_snapshot();value=self.execute(inspect=True);encoded=json.dumps(value)
        self.assertEqual(value['status'],'preflight_mismatch')
        self.assertEqual({row['path'] for row in value['mismatches']},{'local_config','next_config'})
        for forbidden in (*paths,'PRIVATE SHADOW SETTINGS',m.sha(b'PRIVATE SHADOW SETTINGS')): self.assertNotIn(forbidden,encoded)
        for label in ('local_config','next_config'):
            self.assertTrue(value['private_checks'][label]['present']);self.assertFalse(value['private_checks'][label]['matches_expected'])
        self.assertEqual(self.filesystem_snapshot(),before);self.assertFalse(self.private.parent.exists())

    def restored(self):
        self.assertEqual((self.root/m.ENTRY).read_bytes(),self.old)
        for path in m.ADDITIONS: self.assertFalse((self.root/path).exists(),path)
        self.assertEqual((self.root/'_preview/search3-anex-candidate/.anex-private.php').read_bytes(),self.secret)
        self.assertEqual(stat.S_IMODE((self.root/m.PRIVATE_CONFIG).stat().st_mode),0o600)
        self.assertEqual((self.root/'config.php').read_bytes(),self.secret)

    def test_success_exact_overlay_and_snapshot_no_replay(self):
        value=self.execute();self.assertEqual(value['status'],'installed_verified');self.assertEqual(value['written_files'],list(m.ADDITIONS)+[m.ENTRY])
        self.assertTrue(value['snapshot_verified'] and value['restore_drill_verified'] and value['protected_unchanged'])
        self.assertEqual((self.private/'snapshot/entry').read_bytes(),self.old)
        for path in [self.private,self.private/'snapshot',self.private/'drill',self.private/'stage']: self.assertEqual(stat.S_IMODE(path.stat().st_mode),0o700)
        receipt=(self.private/'result.json').read_bytes()
        second=self.execute();self.assertEqual(second['status'],'blocked_no_replay');self.assertEqual((self.private/'result.json').read_bytes(),receipt)

    def test_existing_unknown_reservation_cannot_be_replayed_on_unchanged_site(self):
        self.private.mkdir(parents=True,mode=0o700);self.private.parent.chmod(0o700)
        (self.private/'reservation.json').write_text('unknown operation; do not overwrite')
        value=self.execute();self.assertEqual(value['status'],'blocked_no_replay');self.assertEqual(value['reason'],'operation_exists_no_replay')
        self.assertEqual((self.private/'reservation.json').read_text(),'unknown operation; do not overwrite');self.restored()

    def test_continuation_preserves_any_existing_parent_namespace(self):
        self.use_continuation(); parent = self.private.parent/m.OPERATION
        parent.parent.mkdir(mode=0o700)
        for kind in ('directory', 'file', 'symlink'):
            with self.subTest(kind=kind):
                if kind == 'directory': parent.mkdir(mode=0o700)
                elif kind == 'file': parent.write_bytes(b'original reservation sentinel')
                else: parent.symlink_to(self.root, target_is_directory=True)
                before = self.filesystem_snapshot(); value = self.execute()
                self.assertEqual(value['status'], 'blocked_no_replay')
                self.assertEqual(value['written_files'], []); self.assertFalse(self.private.exists())
                self.assertFalse(value['parent_namespace_absent'])
                self.assertEqual(self.filesystem_snapshot(), before)
                parent.rmdir() if kind == 'directory' else parent.unlink()

    def test_continuation_dependency_drift_is_granular_and_redacted_before_write(self):
        self.use_continuation()
        self.put('data/db-v1.php', b'unreviewed root db')
        self.put(m.PRIVATE_CONFIG, b'PRIVATE_CONFIG_SENTINEL changed private bytes')
        (self.root/m.PRIVATE_CONFIG).chmod(0o600)
        self.put('_preview/search3-next-candidate/config.php', b'unreviewed private override')
        before = self.filesystem_snapshot(); value = self.execute()
        self.assertEqual(value['reason'], 'dependency_drift'); self.assertEqual(value['written_files'], [])
        self.assertFalse(self.private.exists()); self.assertEqual(self.filesystem_snapshot(), before)
        self.assertEqual({row['path'] for row in value['public_mismatches']}, {'data/db-v1.php'})
        row = value['public_mismatches'][0]
        self.assertEqual(row['observed'], {'state':'present', 'sha256':m.sha(b'unreviewed root db'),
                                         'size':len(b'unreviewed root db'), 'mode':'0644'})
        self.assertFalse(value['private_checks']['anex_private_config'])
        self.assertFalse(value['private_checks']['next_config'])
        self.assertTrue(all(type(flag) is bool for flag in value['private_checks'].values()))
        encoded = json.dumps(value)
        for forbidden in (m.PRIVATE_CONFIG, 'PRIVATE_CONFIG_SENTINEL', m.sha(b'PRIVATE_CONFIG_SENTINEL changed private bytes'),
                          m.sha(b'unreviewed private override'), '_preview/search3-next-candidate/config.php'):
            self.assertNotIn(forbidden, encoded)
        for mutate in [lambda v: v['public_mismatches'][0].update(path='../unreviewed'),
                       lambda v: v['public_mismatches'][0].update(path=m.PRIVATE_CONFIG),
                       lambda v: v['public_mismatches'][0]['observed'].update(content='never export file bodies'),
                       lambda v: v['private_checks'].update(anex_private_config={'sha256':'0'*64})]:
            invalid = copy.deepcopy(value); mutate(invalid)
            with self.assertRaises(RuntimeError): m.valid_result(self.identity, self.policy, invalid)

    def test_continuation_rechecks_predecessor_additions_and_other_dependencies(self):
        self.use_continuation()
        for path in (m.ENTRY, m.ADDITIONS[-1], NEXT_ENTRY):
            with self.subTest(path=path):
                target = self.root/path; prior = target.read_bytes() if target.exists() else None
                self.put(path, b'concurrent unreviewed update'); before = self.filesystem_snapshot()
                value = self.execute()
                self.assertEqual(value['status'], 'blocked_no_replay'); self.assertEqual(value['written_files'], [])
                self.assertFalse(self.private.exists()); self.assertEqual(self.filesystem_snapshot(), before)
                self.put(path, prior) if prior is not None else target.unlink()

    def test_continuation_unreadable_parent_namespace_fails_before_reservation(self):
        self.use_continuation(); self.private.parent.mkdir(mode=0o700); self.private.parent.chmod(0)
        try: value = self.execute(demote=True)
        finally: self.private.parent.chmod(0o700)
        self.assertEqual(value['status'], 'blocked_no_replay'); self.assertEqual(value['written_files'], [])
        self.assertFalse(value['parent_namespace_absent']); self.assertFalse(self.private.exists()); self.restored()

    def test_continuation_parent_race_is_rechecked_immediately_before_reservation(self):
        self.use_continuation(); self.private.parent.mkdir(mode=0o700)
        marker = "  before=continuation_inventory(public,POLICY['expected']);protected=continuation_inventory(public,protected_paths)"
        def race(program):
            self.assertEqual(program.count(marker), 1)
            return program.replace(marker, "  os.mkdir(home+'/.anytoour-search3-root-gateway/'+POLICY['parent_failure']['operation_id'],0o700)\n"+marker, 1)
        value = self.execute(change=race)
        self.assertEqual(value['reason'], 'parent_operation_exists'); self.assertEqual(value['written_files'], [])
        self.assertFalse(value['parent_namespace_absent']); self.assertFalse(self.private.exists())
        self.assertTrue((self.private.parent/m.OPERATION).is_dir()); self.restored()

    def test_continuation_existing_child_reservation_is_never_reused(self):
        self.use_continuation(); self.private.mkdir(parents=True, mode=0o700); self.private.parent.chmod(0o700)
        (self.private/'reservation.json').write_bytes(b'unknown child; no replay')
        before = self.filesystem_snapshot(); value = self.execute()
        self.assertEqual(value['status'], 'blocked_no_replay'); self.assertEqual(value['written_files'], [])
        self.assertEqual(self.filesystem_snapshot(), before)

    def test_missing_local_or_private_drift_is_before_any_write(self):
        for key in ['_preview/search3-local-candidate/data/search3-local-results-read-v1.php',m.PRIVATE_CONFIG,'analytics-config.php','seo-config.php']:
            target=self.root/key;old=target.read_bytes();target.write_bytes(b'drift')
            value=self.execute();self.assertEqual(value['reason'],'dependency_drift');self.assertFalse(self.private.exists());self.assertFalse((self.root/'visual-search').exists())
            target.write_bytes(old)
        self.restored()

    def test_missing_transitive_local_helper_stops_before_any_write(self):
        (self.root/'_preview/search3-local-candidate/data/anytour-offer-store-read-v2.php').unlink()
        value=self.execute();self.assertEqual(value['reason'],'dependency_drift')
        self.assertEqual(value['written_files'],[]);self.assertFalse(self.private.exists());self.restored()

    def test_root_db_cannot_be_silently_replaced_by_local_db(self):
        self.put('data/db-v1.php',(self.root/'_preview/search3-local-candidate/data/db-v1.php').read_bytes())
        value=self.execute();self.assertEqual(value['reason'],'dependency_drift')
        self.assertEqual(value['written_files'],[]);self.assertFalse(self.private.exists());self.restored()

    def test_unreviewed_local_or_next_config_shadow_stops_before_any_write(self):
        for path in ('_preview/search3-local-candidate/config.php','_preview/search3-next-candidate/config.php'):
            with self.subTest(path=path):
                self.put(path,b'<?php /* unreviewed settings; never execute */')
                value=self.execute();self.assertEqual(value['reason'],'dependency_drift')
                self.assertEqual(value['written_files'],[]);self.assertFalse(self.private.exists());self.restored()
                (self.root/path).unlink()

    def test_symlink_hardlink_and_wrong_mode_fail_before_reservation(self):
        path=self.root/m.ENTRY;path.unlink();path.symlink_to(self.root/'index.php')
        self.assertEqual(self.execute()['reason'],'unsafe_file');self.assertFalse(self.private.exists())
        path.unlink();os.link(self.root/'index.php',path)
        self.assertEqual(self.execute()['reason'],'unsafe_file');self.assertFalse(self.private.exists())
        path.unlink();self.put(m.ENTRY,self.old);path.chmod(0o600)
        self.assertEqual(self.execute()['reason'],'predecessor_drift');self.assertFalse(self.private.exists())

    def test_execute_only_ancestor(self):
        value=self.execute(demote=True);self.assertEqual(value['status'],'installed_verified')

    def test_unreadable_home_with_existing_private_root(self):
        self.private.parent.mkdir(mode=0o700);self.home.chmod(0o300)
        value=self.execute(demote=True);self.assertEqual(value['status'],'installed_verified')

    def test_unreviewed_optional_include_and_writable_private_config_stop_before_write(self):
        self.put(m.ABSENT_DEPENDENCIES[0],b'<?php /* must never run */')
        self.assertEqual(self.execute()['reason'],'dependency_drift');self.assertFalse(self.private.exists())
        (self.root/m.ABSENT_DEPENDENCIES[0]).unlink();(self.root/m.PRIVATE_CONFIG).chmod(0o660)
        self.assertEqual(self.execute()['reason'],'dependency_drift');self.assertFalse(self.private.exists())
        (self.root/m.PRIVATE_CONFIG).chmod(0o600);self.restored()

    def test_opaque_settings_change_aborts_without_overwriting_them(self):
        def fail(program): return program.replace(" result['phase']='entry'", " with public.parent('config.php') as (fd,name):\n  f=os.open(name,os.O_WRONLY|os.O_TRUNC,dir_fd=fd);os.write(f,b'foreign settings');os.close(f)\n raise RuntimeError('operation_failed')\n result['phase']='entry'",1)
        value=self.execute(fail);self.assertEqual(value['status'],'unknown_no_replay')
        self.assertEqual((self.root/'config.php').read_bytes(),b'foreign settings');self.assertEqual((self.root/m.ENTRY).read_bytes(),self.old)

    def test_partial_addition_failure_restores_only_ours(self):
        def fail(program): return program.replace("  result['written_files']=list(written)\n", "  result['written_files']=list(written)\n  if len(written)==5: raise RuntimeError('operation_failed')\n",1)
        value=self.execute(fail);self.assertEqual(value['status'],'rolled_back_no_replay');self.assertEqual(len(value['rollback_files']),5);self.restored()

    def test_entry_failure_rolls_back_entry_first(self):
        def fail(program): return program.replace(" written.append(POLICY['entry']);result['written_files']=list(written)\n", " written.append(POLICY['entry']);result['written_files']=list(written)\n raise RuntimeError('operation_failed')\n",1)
        value=self.execute(fail);self.assertEqual(value['status'],'rolled_back_no_replay');self.assertEqual(value['rollback_files'][0],m.ENTRY);self.restored()

    def inject_rollback_race(self,program,entry=False,foreign='file',newer=False):
        if entry:
            program=program.replace(" written.append(POLICY['entry']);result['written_files']=list(written)\n", " written.append(POLICY['entry']);result['written_files']=list(written)\n raise RuntimeError('operation_failed')\n",1)
        else:
            program=program.replace("  result['written_files']=list(written)\n", "  result['written_files']=list(written)\n  if len(written)==5: raise RuntimeError('operation_failed')\n",1)
        if foreign=='symlink':
            inject="   os.symlink('foreign-target',name+'.race',dir_fd=src);os.rename(name+'.race',name,src_dir_fd=src,dst_dir_fd=src)\n"
        else:
            raw="b'FOREIGN UPDATE'" if foreign=='file' else "b'X'*(MAX_BYTES+1)"
            inject="   f=os.open(name+'.race',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o644,dir_fd=src);os.write(f,"+raw+");os.close(f);os.rename(name+'.race',name,src_dir_fd=src,dst_dir_fd=src)\n"
        program=program.replace('\n   move_noreplace(src,name,dst,target);moved=True;sync(src);sync(dst)','\n'+inject+'   move_noreplace(src,name,dst,target);moved=True;sync(src);sync(dst)',1)
        if newer:
            program=program.replace('  observed=private.read(capture)',"  with public.parent(relative) as (fd,name):\n   f=os.open(name,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o644,dir_fd=fd);os.write(f,b'NEWER UPDATE');os.close(f)\n  observed=private.read(capture)",1)
        return program

    def test_addition_rollback_capture_gap_preserves_foreign_update(self):
        value=self.execute(lambda p:self.inject_rollback_race(p))
        self.assertEqual(value['status'],'unknown_no_replay');self.assertEqual((self.root/m.ADDITIONS[4]).read_bytes(),b'FOREIGN UPDATE')
        self.assertEqual((self.root/m.ENTRY).read_bytes(),self.old)

    def test_entry_rollback_capture_gap_preserves_foreign_update(self):
        value=self.execute(lambda p:self.inject_rollback_race(p,entry=True))
        self.assertEqual(value['status'],'unknown_no_replay');self.assertEqual((self.root/m.ENTRY).read_bytes(),b'FOREIGN UPDATE')

    def test_foreign_symlink_is_restored_after_capture(self):
        value=self.execute(lambda p:self.inject_rollback_race(p,foreign='symlink'))
        target=self.root/m.ADDITIONS[4];self.assertEqual(value['status'],'unknown_no_replay')
        self.assertTrue(target.is_symlink());self.assertEqual(os.readlink(target),'foreign-target')

    def test_foreign_large_file_is_restored_after_capture(self):
        value=self.execute(lambda p:self.inject_rollback_race(p,foreign='large'))
        self.assertEqual(value['status'],'unknown_no_replay');self.assertEqual((self.root/m.ADDITIONS[4]).stat().st_size,m.MAX_BYTES+1)

    def test_newer_foreign_path_wins_while_captured_foreign_is_preserved(self):
        value=self.execute(lambda p:self.inject_rollback_race(p,newer=True))
        self.assertEqual(value['status'],'unknown_no_replay');self.assertEqual((self.root/m.ADDITIONS[4]).read_bytes(),b'NEWER UPDATE')
        self.assertEqual((self.private/'rollback/4').read_bytes(),b'FOREIGN UPDATE')

    def test_foreign_empty_directory_survives_rollback_capture_gap(self):
        def race(program):
            program=program.replace("  result['written_files']=list(written)\n", "  result['written_files']=list(written)\n  if len(written)==5: raise RuntimeError('operation_failed')\n",1)
            return program.replace('     move_noreplace(src,name,dst,target);moved=True;sync(src);sync(dst)',
                                   "     os.rename(name,name+'.held',src_dir_fd=src,dst_dir_fd=src);os.mkdir(name,0o755,dir_fd=src)\n     move_noreplace(src,name,dst,target);moved=True;sync(src);sync(dst)",1)
        value=self.execute(race);self.assertEqual(value['status'],'unknown_no_replay')
        self.assertTrue((self.root/'prototype-search').is_dir());self.assertEqual(list((self.root/'prototype-search').iterdir()),[])

    def test_forward_exchange_race_restores_unreadable_foreign_entry(self):
        def race(program):
            return program.replace('  exchange(src,name,dst,target);switched=True;sync(src);sync(dst)',
                                   "  os.symlink('foreign-entry',target+'.race',dir_fd=dst);os.rename(target+'.race',target,src_dir_fd=dst,dst_dir_fd=dst)\n  exchange(src,name,dst,target);switched=True;sync(src);sync(dst)",1)
        value=self.execute(race);self.assertEqual(value['status'],'unknown_no_replay')
        self.assertTrue((self.root/m.ENTRY).is_symlink());self.assertEqual(os.readlink(self.root/m.ENTRY),'foreign-entry')

    def test_foreign_file_racing_addition_is_not_overwritten(self):
        first=m.ADDITIONS[0]
        def race(program): return program.replace("   os.link(name,target,src_dir_fd=src,dst_dir_fd=dst,follow_symlinks=False);written.append(p)","   f=os.open(target,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o644,dir_fd=dst);os.write(f,b'foreign');os.close(f)\n   os.link(name,target,src_dir_fd=src,dst_dir_fd=dst,follow_symlinks=False);written.append(p)",1)
        value=self.execute(race);self.assertEqual(value['status'],'unknown_no_replay');self.assertEqual((self.root/first).read_bytes(),b'foreign');self.assertEqual((self.root/m.ENTRY).read_bytes(),self.old)

    def test_foreign_equal_bytes_inode_survives_rollback(self):
        def race(program): return program.replace(" result['phase']='entry'", " p=POLICY['additions'][0]\n with public.parent(p) as (fd,name):\n  os.unlink(name,dir_fd=fd);f=os.open(name,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o644,dir_fd=fd);os.write(f,data[p]);os.close(f)\n raise RuntimeError('operation_failed')\n result['phase']='entry'",1)
        value=self.execute(race);self.assertEqual(value['status'],'unknown_no_replay');self.assertTrue((self.root/m.ADDITIONS[0]).exists());self.assertEqual((self.root/m.ENTRY).read_bytes(),self.old)

    def test_snapshot_corruption_stops_before_public_write(self):
        def corrupt(program): return program.replace(" require(private.read('snapshot/entry')[0]==POLICY['before'],'snapshot_invalid')", " with private.parent('snapshot/entry') as (fd,name):\n  f=os.open(name,os.O_WRONLY|os.O_TRUNC,dir_fd=fd);os.write(f,b'corrupt');os.close(f)\n require(private.read('snapshot/entry')[0]==POLICY['before'],'snapshot_invalid')",1)
        value=self.execute(corrupt);self.assertEqual(value['written_files'],[]);self.assertFalse(value['restore_drill_verified']);self.restored()

    def test_parent_symlink_is_never_followed(self):
        other=self.home/'other';other.mkdir();(self.root/'visual-search').symlink_to(other,target_is_directory=True)
        self.assertEqual(self.execute()['reason'],'unsafe_parent');self.assertFalse(list(other.iterdir()));self.assertFalse(self.private.exists())


class RollbackTransaction(unittest.TestCase):
    @contextlib.contextmanager
    def installed(self, continuation=False):
        fixture = Transaction(); fixture.setUp()
        try:
            if continuation: fixture.use_continuation()
            fixture.accepted = fixture.execute()
            self.assertEqual(fixture.accepted['status'], 'installed_verified')
            yield fixture
        finally: fixture.tearDown()

    def original_records(self, fixture):
        return {str(p.relative_to(fixture.private)): (p.read_bytes(), p.stat().st_mode, p.stat().st_ino, p.stat().st_mtime_ns)
                for p in fixture.private.rglob('*') if p.is_file()}

    def execute(self, fixture, change=None):
        original = fixture.identity | ({'operation_id': m.CONTINUATION_OPERATION}
                                       if fixture.policy['operation'] == m.CONTINUATION_OPERATION else {})
        program = m.rollback_remote_program(fixture.policy, ROLLBACK_IDENTITY, original, fixture.accepted)
        program = program.replace("home=os.environ.get('HOME','');root=home+'/www/anytoour.ru'",
                                  "home=os.environ['SEARCH3_FIXTURE_HOME'];root=home+'/www/anytoour.ru'", 1)
        if change: program = change(program)
        run = subprocess.run([sys.executable, '-I', '-S', '-B', '-'], input=program.encode(), capture_output=True,
                             cwd=fixture.home, env=os.environ | {'SEARCH3_FIXTURE_HOME': str(fixture.home)}, timeout=30)
        self.assertEqual(run.returncode, 0, run.stderr.decode())
        self.assertNotIn(fixture.secret, run.stdout); self.assertNotIn(str(fixture.home).encode(), run.stdout)
        value = json.loads(run.stdout); m.valid_rollback_result(ROLLBACK_IDENTITY, original, fixture.accepted, value)
        return value

    def test_continuation_success_rolls_back_its_actual_identity_and_namespace(self):
        with self.installed(continuation=True) as f:
            self.assertTrue(f.accepted['parent_namespace_absent'])
            self.assertEqual(f.accepted['parent_failure'], m.CONTINUATION_PARENT)
            self.assertEqual(f.accepted['run_id'], CONTINUATION_IDENTITY['run_id'])
            self.assertEqual(f.accepted['written_files'], [*m.ADDITIONS, m.ENTRY])
            for mutate in [lambda v: v['parent_failure'].update(run_id='44840666321'),
                           lambda v: v['parent_failure'].update(receipt_sha256='0'*64),
                           lambda v: v.update(dependency_policy_sha256='0'*64),
                           lambda v: v.update(parent_namespace_absent=False)]:
                invalid = copy.deepcopy(f.accepted); mutate(invalid)
                with self.assertRaises(RuntimeError): m.valid_result(f.identity, f.policy, invalid)
            original = CONTINUATION_IDENTITY | {'operation_id':m.CONTINUATION_OPERATION}
            outer = {**original, 'read_only':False, 'phase':'complete', 'ssh_started':True, 'status':'installed_verified',
                     'parent_failure':m.CONTINUATION_PARENT, 'dependency_policy_sha256':m.CONTINUATION_POLICY_SHA, 'remote':f.accepted}
            stream = io.BytesIO()
            with zipfile.ZipFile(stream, 'w') as archive: archive.writestr('evidence.json', json.dumps(outer))
            self.assertEqual(m.rollback_source_evidence(stream.getvalue(), original, f.policy), f.accepted)
            with self.assertRaises(RuntimeError): m.rollback_source_evidence(parent_failure_fixture()[0], PARENT_IDENTITY, f.policy)
            parent = f.private.parent/m.OPERATION; self.assertFalse(parent.exists())
            records = self.original_records(f); root_db = (f.root/'data/db-v1.php').read_bytes()
            value = self.execute(f)
            self.assertEqual(value['status'], 'rolled_back_verified'); self.assertEqual(len(value['rollback_files']), 42)
            self.assertEqual(value['original_install'], CONTINUATION_IDENTITY | {'operation_id':m.CONTINUATION_OPERATION})
            # Recovery consumes the successful child's proof, even if the parent's
            # historic artifact or signer can no longer be fetched.
            runner = f.base/'runner'; runner.mkdir(); event_path = runner/'event.json'
            event_value = rollback_event()
            event_value['comment']['body'] = event_value['comment']['body'].replace(IDENTITY['run_id'], CONTINUATION_IDENTITY['run_id'])
            event_path.write_text(json.dumps(event_value))
            env = environment() | {'GITHUB_SHA':ROLLBACK_IDENTITY['control_source_sha'], 'GITHUB_RUN_ID':ROLLBACK_IDENTITY['run_id'],
                'RUNNER_TEMP':str(runner), 'GITHUB_EVENT_PATH':str(event_path), 'PREVIEW_HOST':'example.invalid',
                'PREVIEW_USER':'fixture', 'PREVIEW_KEY':'PRIVATE KEY fixture'}
            with patch.dict(os.environ, env), patch.object(m, 'rollback_admission', return_value=(original, {'id':333,'digest':'sha256:'+'5'*64})), \
                 patch.object(m, 'download', side_effect=[b'compiled payload', stream.getvalue()]) as download, \
                 patch.object(m, 'package', return_value=(f.policy, {})), patch.object(m, 'rollback_commands', return_value=original), \
                 patch.object(m, 'rollback_main'), patch.object(m, 'continuation_parent_evidence', side_effect=AssertionError('expired parent fetch')) as parent_proof, \
                 patch.object(m, 'tv_successor_evidence', return_value={'status':'absent'}), \
                 patch.object(m, 'signing_evidence', side_effect=AssertionError('signer gate')) as signer, \
                 patch.object(m.carrier, 'run', side_effect=[b'fixture known host', json.dumps(value).encode()]), patch('builtins.print'):
                m.rollback()
            self.assertEqual([call.args[0] for call in download.call_args_list], [m.ARTIFACT, 333])
            parent_proof.assert_not_called(); signer.assert_not_called()
            f.restored(); self.assertFalse(parent.exists()); self.assertEqual(self.original_records(f), records)
            self.assertEqual((f.root/'data/db-v1.php').read_bytes(), root_db)
            self.assertEqual(self.execute(f)['status'], 'blocked_no_replay')
            self.assertEqual(self.original_records(f), records)

    def test_success_then_replay_preserves_original_records_and_directories(self):
        with self.installed() as f:
            before = self.original_records(f); foreign = f.root/'visual-search/assets/foreign.txt'; foreign.write_bytes(b'keep')
            value = self.execute(f)
            self.assertEqual(value['status'], 'rolled_back_verified'); self.assertEqual(len(value['rollback_files']), 42)
            f.restored(); self.assertEqual(foreign.read_bytes(), b'keep')
            self.assertTrue((f.root/'poisk-turov-old').is_dir()); self.assertTrue((f.root/'visual-search/assets').is_dir())
            self.assertEqual(self.original_records(f), before)
            receipt = f.private.parent/m.ROLLBACK_OPERATION/'result.json'; saved = receipt.read_bytes()
            self.assertEqual(self.execute(f)['status'], 'blocked_no_replay')
            self.assertEqual(receipt.read_bytes(), saved); self.assertEqual(self.original_records(f), before)

    def test_changed_candidate_or_private_proof_blocks_before_reservation(self):
        for kind in ('inode', 'mode', 'mtime', 'hardlink', 'intent', 'snapshot', 'private_result', 'protected', 'reserved'):
            with self.subTest(kind=kind), self.installed() as f:
                target = f.root/m.ADDITIONS[-1]
                if kind == 'inode':
                    previous = target.stat(); foreign = target.with_name(target.name+'.foreign')
                    foreign.write_bytes(target.read_bytes()); foreign.chmod(0o644)
                    os.utime(foreign, ns=(previous.st_atime_ns, previous.st_mtime_ns)); os.replace(foreign, target)
                    self.assertNotEqual(target.stat().st_ino, previous.st_ino)
                elif kind == 'mode': target.chmod(0o600)
                elif kind == 'mtime':
                    previous = target.stat(); os.utime(target, ns=(previous.st_atime_ns, previous.st_mtime_ns+1000000))
                elif kind == 'hardlink': os.link(target, f.home/'foreign-link')
                elif kind == 'intent': (f.private/'intent-0.json').write_text('{}')
                elif kind == 'snapshot': (f.private/'snapshot/entry').write_bytes(b'wrong backup')
                elif kind == 'private_result': (f.private/'result.json').write_text('{}')
                elif kind == 'protected': (f.root/'config.php').write_bytes(b'changed settings')
                elif kind == 'reserved':
                    reserved = f.private.parent/m.ROLLBACK_OPERATION; reserved.mkdir(mode=0o700)
                    (reserved/'reservation.json').write_bytes(b'unknown prior attempt')
                before = self.original_records(f); current = target.read_bytes(); value = self.execute(f)
                self.assertEqual(value['status'], 'blocked_no_replay'); self.assertEqual(value['rollback_files'], [])
                self.assertEqual(target.read_bytes(), current); self.assertEqual(self.original_records(f), before)
                if kind == 'reserved': self.assertEqual((reserved/'reservation.json').read_bytes(), b'unknown prior attempt')
                else: self.assertFalse((f.private.parent/m.ROLLBACK_OPERATION).exists())

    def test_foreign_entry_or_addition_at_capture_gap_is_preserved_unknown(self):
        for entry in (True, False):
            with self.subTest(entry=entry), self.installed() as f:
                before = self.original_records(f); path = m.ENTRY if entry else m.ADDITIONS[-1]
                def race(program):
                    if entry:
                        line = " capture_owned(public,private,entry,'quarantine/entry',POLICY['expected'][entry],candidate[entry][1])"
                        injection = " foreign=root+'/'+entry+'.foreign'\n with open(foreign,'wb') as stream: stream.write(b'FOREIGN')\n os.chmod(foreign,0o644);os.replace(foreign,root+'/'+entry)\n"
                    else:
                        line = "  capture_owned(public,private,path,'quarantine/'+str(POLICY['additions'].index(path)),POLICY['expected'][path],candidate[path][1])"
                        injection = "  if path==POLICY['additions'][-1]:\n   foreign=root+'/'+path+'.foreign'\n   with open(foreign,'wb') as stream: stream.write(b'FOREIGN')\n   os.chmod(foreign,0o644);os.replace(foreign,root+'/'+path)\n"
                    self.assertIn(line, program); return program.replace(line, injection+line, 1)
                value = self.execute(f, race)
                self.assertEqual(value['status'], 'unknown_no_replay'); self.assertEqual((f.root/path).read_bytes(), b'FOREIGN')
                self.assertEqual(value['rollback_files'], [] if entry else [m.ENTRY])
                if not entry: self.assertEqual((f.root/m.ENTRY).read_bytes(), f.old)
                self.assertEqual(self.original_records(f), before)

    def test_newer_entry_between_capture_and_restore_is_never_overwritten(self):
        with self.installed() as f:
            before = self.original_records(f)
            def race(program):
                line = " restore_capture(public,private,entry,'stage/entry')"
                self.assertIn(line, program)
                return program.replace(line, " public.create(entry,b'NEWER FOREIGN ENTRY',0o644)\n"+line, 1)
            value = self.execute(f, race)
            self.assertEqual(value['status'], 'unknown_no_replay'); self.assertEqual(value['rollback_files'], [])
            self.assertEqual((f.root/m.ENTRY).read_bytes(), b'NEWER FOREIGN ENTRY')
            self.assertTrue(all((f.root/p).is_file() for p in m.ADDITIONS)); self.assertEqual(self.original_records(f), before)


class UpdateTransaction(unittest.TestCase):
    @contextlib.contextmanager
    def installed(self):
        fixture = Transaction(); fixture.setUp(); fixture.use_continuation()
        fixture.parent_identity = update_parent_identity()
        fixture.identity = {key: value for key, value in fixture.parent_identity.items() if key != 'operation_id'}
        try:
            fixture.accepted = fixture.execute()
            self.assertEqual(fixture.accepted['status'], 'installed_verified')
            before = copy.deepcopy(fixture.policy['expected'][m.UPDATE_PATH])
            data = b'compiled: contact Apply Back resume keeps the selected flight'
            after = {'state': 'present', 'sha256': m.sha(data), 'size': len(data), 'mode': '0644'}
            _, parent_digest = update_parent_archive(lambda value: value.update(remote=fixture.accepted))
            parent = m.UPDATE_PARENT | {'snapshot_manifest_sha256': fixture.accepted['snapshot_manifest_sha256'],
                                        'receipt_sha256': parent_digest}
            fixture.update_policy = {
                'operation': m.UPDATE_OPERATION, 'source': m.UPDATE_SOURCE, 'tree': m.UPDATE_TREE,
                'release': RELEASE, 'manifest': m.UPDATE_MANIFEST_SHA, 'path': m.UPDATE_PATH,
                'before': before, 'after': after, 'parent': parent, 'previous': fixture.policy}
            fixture.update_files = {m.UPDATE_PATH: base64.b64encode(data).decode()}
            fixture.update_private = fixture.private.parent/m.UPDATE_OPERATION
            with patch.object(m, 'UPDATE_PARENT', parent), patch.object(m, 'UPDATE_BEFORE', before), \
                 patch.object(m, 'UPDATE_AFTER', after), patch.object(m, 'UPDATE_RELEASE', RELEASE):
                yield fixture
        finally: fixture.tearDown()

    def stamp(self, path):
        value = path.stat()
        return (value.st_dev, value.st_ino, value.st_mode, value.st_nlink, value.st_size,
                value.st_mtime_ns, value.st_uid, value.st_gid)

    def public_files(self, fixture):
        paths = set(fixture.policy['expected']) | set(fixture.policy['protected']) | {'config.php'}
        return {path: (self.stamp(fixture.root/path), (fixture.root/path).read_bytes()) for path in paths}

    def run_program(self, fixture, program, change=None):
        self.assertIn("home=os.environ.get('HOME','')", program)
        program = program.replace("home=os.environ.get('HOME','')", "home=os.environ['SEARCH3_FIXTURE_HOME']")
        program = program.replace("os.environ.get('HOME','')+'/www/anytoour.ru'",
                                  "os.environ['SEARCH3_FIXTURE_HOME']+'/www/anytoour.ru'", 1)
        if change: program = change(program)
        run = subprocess.run([sys.executable, '-I', '-S', '-B', '-'], input=program.encode(), capture_output=True,
                             cwd=fixture.home, env=os.environ | {'SEARCH3_FIXTURE_HOME': str(fixture.home)}, timeout=30)
        self.assertEqual(run.returncode, 0, run.stderr.decode())
        self.assertNotIn(fixture.secret, run.stdout); self.assertNotIn(str(fixture.home).encode(), run.stdout)
        return json.loads(run.stdout)

    def execute(self, fixture, change=None):
        program = m.update_remote_program(fixture.update_policy, fixture.update_files, UPDATE_IDENTITY,
                                          fixture.parent_identity, fixture.accepted)
        value = self.run_program(fixture, program, change)
        m.valid_update_result(UPDATE_IDENTITY, fixture.update_policy, value)
        return value

    def rollback(self, fixture, change=None):
        original = UPDATE_IDENTITY
        program = m.update_rollback_remote_program(fixture.update_policy, UPDATE_ROLLBACK_IDENTITY,
            original, fixture.update_accepted, fixture.parent_identity, fixture.accepted)
        value = self.run_program(fixture, program, change)
        m.valid_update_rollback_result(UPDATE_ROLLBACK_IDENTITY, original, fixture.update_accepted,
                                       fixture.update_policy, value)
        return value

    def test_one_asset_roundtrip_preserves_original_inode_and_full_legacy_rollback(self):
        with self.installed() as f:
            original_files = self.public_files(f)
            parent_records = RollbackTransaction().original_records(f)
            f.update_accepted = self.execute(f)
            self.assertEqual(f.update_accepted['status'], 'updated_verified')
            self.assertEqual(f.update_accepted['written_files'], [m.UPDATE_PATH])
            self.assertEqual(f.update_accepted['rollback_files'], [])
            self.assertTrue(f.update_accepted['snapshot_verified'])
            self.assertTrue(f.update_accepted['restore_drill_verified'])
            current = self.public_files(f)
            self.assertEqual({key for key in current if current[key] != original_files[key]}, {m.UPDATE_PATH})
            self.assertEqual(self.stamp(f.update_private/'stage/app'), original_files[m.UPDATE_PATH][0])
            self.assertEqual((f.update_private/'stage/app').read_bytes(), original_files[m.UPDATE_PATH][1])
            self.assertEqual(RollbackTransaction().original_records(f), parent_records)
            for key in ('supplier_calls', 'database_reads', 'database_writes', 'lead_calls', 'booking_calls', 'application_calls'):
                self.assertIs(type(f.update_accepted[key]), int); self.assertEqual(f.update_accepted[key], 0)
            update_records = {str(p.relative_to(f.update_private)): p.read_bytes()
                              for p in f.update_private.rglob('*.json')}
            restored = self.rollback(f)
            self.assertEqual(restored['status'], 'update_rolled_back_verified')
            self.assertEqual(restored['rollback_files'], [m.UPDATE_PATH])
            self.assertEqual(self.public_files(f), original_files)
            self.assertEqual(RollbackTransaction().original_records(f), parent_records)
            self.assertEqual({str(p.relative_to(f.update_private)): p.read_bytes()
                              for p in f.update_private.rglob('*.json')}, update_records)
            # Equal copied bytes are insufficient: the unchanged old installer
            # must accept its original inode/mode/links/mtime/owner intent again.
            self.assertEqual(RollbackTransaction().execute(f)['status'], 'rolled_back_verified')
            f.restored()
            self.assertEqual(RollbackTransaction().original_records(f), parent_records)

    def test_update_replay_and_unknown_reservation_preserve_all_existing_records(self):
        for unknown in (False, True):
            with self.subTest(unknown=unknown), self.installed() as f:
                if unknown:
                    f.update_private.mkdir(mode=0o700)
                    (f.update_private/'reservation.json').write_bytes(b'unknown prior update; do not reuse')
                else:
                    self.assertEqual(self.execute(f)['status'], 'updated_verified')
                before = f.filesystem_snapshot()
                value = self.execute(f)
                self.assertEqual(value['status'], 'blocked_no_replay')
                self.assertEqual(value['written_files'], [])
                self.assertEqual(f.filesystem_snapshot(), before)

    def test_public_private_and_parent_record_drift_blocks_before_update_reservation(self):
        for kind in ('bytes', 'inode', 'mode', 'mtime', 'hardlink', 'fallback', 'entry', 'dependency', 'private', 'parent_intent', 'parent_result'):
            with self.subTest(kind=kind), self.installed() as f:
                target = f.root/m.UPDATE_PATH
                if kind == 'bytes': target.write_bytes(b'foreign app')
                elif kind == 'inode':
                    previous = target.stat(); foreign = target.with_name(target.name+'.foreign')
                    foreign.write_bytes(target.read_bytes()); foreign.chmod(0o644)
                    os.utime(foreign, ns=(previous.st_atime_ns, previous.st_mtime_ns)); os.replace(foreign, target)
                    self.assertNotEqual(target.stat().st_ino, previous.st_ino)
                elif kind == 'mode': target.chmod(0o600)
                elif kind == 'mtime':
                    previous = target.stat(); os.utime(target, ns=(previous.st_atime_ns, previous.st_mtime_ns+1000000))
                elif kind == 'hardlink': os.link(target, f.home/'foreign-app-link')
                elif kind == 'fallback': (f.root/'poisk-turov-old/index.php').write_bytes(b'foreign old search')
                elif kind == 'entry': (f.root/m.ENTRY).write_bytes(b'foreign public entry')
                elif kind == 'dependency': (f.root/'data/db-v1.php').write_bytes(b'foreign dependency')
                elif kind == 'private': (f.root/'config.php').write_bytes(b'foreign private settings')
                elif kind == 'parent_intent': (f.private/f'intent-{m.ADDITIONS.index(m.UPDATE_PATH)}.json').write_bytes(b'{}')
                elif kind == 'parent_result': (f.private/'result.json').write_bytes(b'{}')
                before = f.filesystem_snapshot(); value = self.execute(f)
                self.assertEqual(value['status'], 'blocked_no_replay')
                self.assertEqual(value['written_files'], [])
                self.assertFalse(f.update_private.exists())
                self.assertEqual(f.filesystem_snapshot(), before)

    def test_update_rollback_replay_is_not_a_second_restoration(self):
        with self.installed() as f:
            f.update_accepted = self.execute(f)
            self.assertEqual(self.rollback(f)['status'], 'update_rolled_back_verified')
            before = f.filesystem_snapshot()
            repeated = self.rollback(f)
            self.assertEqual(repeated['status'], 'blocked_no_replay')
            self.assertEqual(repeated['rollback_files'], [])
            self.assertEqual(f.filesystem_snapshot(), before)

    def test_changed_updated_asset_or_private_proof_blocks_rollback_before_reservation(self):
        for kind in ('bytes', 'inode', 'mtime', 'private_result', 'original_app', 'dependency', 'reserved'):
            with self.subTest(kind=kind), self.installed() as f:
                f.update_accepted = self.execute(f)
                self.assertEqual(f.update_accepted['status'], 'updated_verified')
                target = f.root/m.UPDATE_PATH
                if kind == 'bytes': target.write_bytes(b'newer foreign application')
                elif kind == 'inode':
                    previous = target.stat(); foreign = target.with_name(target.name+'.foreign')
                    foreign.write_bytes(target.read_bytes()); foreign.chmod(0o644)
                    os.utime(foreign, ns=(previous.st_atime_ns, previous.st_mtime_ns)); os.replace(foreign, target)
                elif kind == 'mtime':
                    previous = target.stat(); os.utime(target, ns=(previous.st_atime_ns, previous.st_mtime_ns+1000000))
                elif kind == 'private_result': (f.update_private/'result.json').write_bytes(b'{}')
                elif kind == 'original_app': (f.update_private/'stage/app').write_bytes(b'wrong predecessor app')
                elif kind == 'dependency': (f.root/'config.php').write_bytes(b'new private settings')
                elif kind == 'reserved':
                    reserved = f.private.parent/m.UPDATE_ROLLBACK_OPERATION; reserved.mkdir(mode=0o700)
                    (reserved/'reservation.json').write_bytes(b'unknown rollback attempt')
                before = f.filesystem_snapshot(); value = self.rollback(f)
                self.assertEqual(value['status'], 'blocked_no_replay')
                self.assertEqual(value['rollback_files'], [])
                self.assertEqual(f.filesystem_snapshot(), before)
                if kind != 'reserved': self.assertFalse((f.private.parent/m.UPDATE_ROLLBACK_OPERATION).exists())

    def test_snapshot_and_restore_drill_fail_before_any_public_write(self):
        for path, reason in (('snapshot/app', 'snapshot_invalid'), ('drill/app', 'restore_drill_failed')):
            with self.subTest(path=path), self.installed() as f:
                before = self.public_files(f); records = RollbackTransaction().original_records(f)
                def corrupt(program):
                    line = f" require(private.read('{path}')[0]==UPDATE_POLICY['before']"
                    self.assertIn(line, program)
                    injection = f" with private.parent('{path}') as (fd,name):\n  f=os.open(name,os.O_WRONLY|os.O_TRUNC,dir_fd=fd);os.write(f,b'corrupt');os.close(f)\n"
                    return program.replace(line, injection+line, 1)
                value = self.execute(f, corrupt)
                self.assertEqual(value['status'], 'rolled_back_no_replay')
                self.assertEqual(value['reason'], reason)
                self.assertEqual(value['written_files'], []); self.assertEqual(value['rollback_files'], [])
                self.assertFalse(value['restore_drill_verified'])
                self.assertEqual(self.public_files(f), before)
                self.assertEqual(RollbackTransaction().original_records(f), records)
                consumed = f.filesystem_snapshot()
                self.assertEqual(self.execute(f)['status'], 'blocked_no_replay')
                self.assertEqual(f.filesystem_snapshot(), consumed)

    def fail_after_exchange(self, program):
        line = ' candidate=parent_candidate(retained,True)'
        self.assertIn(line, program)
        return program.replace(line, " raise RuntimeError('injected_after_exchange')\n"+line, 1)

    def test_failure_after_activation_returns_the_original_inode_and_legacy_rollback(self):
        with self.installed() as f:
            before = self.public_files(f); records = RollbackTransaction().original_records(f)
            value = self.execute(f, self.fail_after_exchange)
            self.assertEqual(value['status'], 'rolled_back_no_replay')
            self.assertEqual(value['written_files'], [m.UPDATE_PATH])
            self.assertEqual(value['rollback_files'], [m.UPDATE_PATH])
            self.assertEqual(self.public_files(f), before)
            self.assertEqual(RollbackTransaction().original_records(f), records)
            self.assertEqual(RollbackTransaction().execute(f)['status'], 'rolled_back_verified')
            f.restored()

    def test_foreign_app_at_exchange_is_preserved_and_never_reported_as_verified(self):
        for kind in ('file', 'same_bytes_inode', 'symlink'):
            with self.subTest(kind=kind), self.installed() as f:
                before = self.public_files(f); records = RollbackTransaction().original_records(f)
                def race(program):
                    line = "  exchange(src,name,dst,target);switched=True;result['written_files']=[path];sync(src);sync(dst)"
                    self.assertIn(line, program)
                    if kind == 'symlink':
                        injection = "  os.symlink('foreign-app',target+'.race',dir_fd=dst)\n"
                    else:
                        raw = "public.read(path,True)[2]" if kind == 'same_bytes_inode' else "b'FOREIGN APP'"
                        injection = "  race_stat=os.stat(target,dir_fd=dst,follow_symlinks=False)\n  race_bytes="+raw+"\n  fd=os.open(target+'.race',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o644,dir_fd=dst);os.write(fd,race_bytes);os.close(fd)\n  os.utime(target+'.race',ns=(race_stat.st_atime_ns,race_stat.st_mtime_ns),dir_fd=dst,follow_symlinks=False)\n"
                    injection += "  os.rename(target+'.race',target,src_dir_fd=dst,dst_dir_fd=dst)\n"
                    return program.replace(line, injection+line, 1)
                value = self.execute(f, race)
                self.assertEqual(value['status'], 'unknown_no_replay')
                self.assertEqual(value['rollback_files'], [])
                target = f.root/m.UPDATE_PATH
                if kind == 'symlink':
                    self.assertTrue(target.is_symlink()); self.assertEqual(os.readlink(target), 'foreign-app')
                else:
                    self.assertEqual(target.read_bytes(), before[m.UPDATE_PATH][1] if kind == 'same_bytes_inode' else b'FOREIGN APP')
                    if kind == 'same_bytes_inode': self.assertNotEqual(target.stat().st_ino, before[m.UPDATE_PATH][0][1])
                for path, original in before.items():
                    if path != m.UPDATE_PATH: self.assertEqual((self.stamp(f.root/path), (f.root/path).read_bytes()), original)
                self.assertEqual(RollbackTransaction().original_records(f), records)

    def test_foreign_app_during_automatic_or_requested_capture_survives(self):
        for automatic in (True, False):
            for kind in ('file', 'symlink'):
                with self.subTest(automatic=automatic, kind=kind), self.installed() as f:
                    records = RollbackTransaction().original_records(f)
                    original_app = (self.stamp(f.root/m.UPDATE_PATH), (f.root/m.UPDATE_PATH).read_bytes())
                    if not automatic:
                        f.update_accepted = self.execute(f)
                        self.assertEqual(f.update_accepted['status'], 'updated_verified')
                    def race(program):
                        if automatic: program = self.fail_after_exchange(program)
                        line = '\n   move_noreplace(src,name,dst,target);moved=True;sync(src);sync(dst)'
                        self.assertIn(line, program)
                        if kind == 'symlink':
                            injection = "   os.symlink('foreign-app',name+'.race',dir_fd=src)\n"
                        else:
                            injection = "   fd=os.open(name+'.race',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o644,dir_fd=src);os.write(fd,b'FOREIGN APP');os.close(fd)\n"
                        injection += "   os.rename(name+'.race',name,src_dir_fd=src,dst_dir_fd=src)\n"
                        return program.replace(line, '\n'+injection+line[1:], 1)
                    value = self.execute(f, race) if automatic else self.rollback(f, race)
                    self.assertEqual(value['status'], 'unknown_no_replay')
                    self.assertEqual(value['rollback_files'], [])
                    target = f.root/m.UPDATE_PATH
                    if kind == 'symlink':
                        self.assertTrue(target.is_symlink()); self.assertEqual(os.readlink(target), 'foreign-app')
                    else: self.assertEqual(target.read_bytes(), b'FOREIGN APP')
                    self.assertEqual(RollbackTransaction().original_records(f), records)
                    self.assertEqual((self.stamp(f.update_private/'stage/app'), (f.update_private/'stage/app').read_bytes()), original_app)

    def test_newer_app_between_capture_and_restore_is_not_overwritten(self):
        with self.installed() as f:
            original = (self.stamp(f.root/m.UPDATE_PATH), (f.root/m.UPDATE_PATH).read_bytes())
            records = RollbackTransaction().original_records(f)
            f.update_accepted = self.execute(f)
            def race(program):
                line = " restore_capture(public,updated,path,'stage/app');result['rollback_files']=[path]"
                self.assertIn(line, program)
                return program.replace(line, " public.create(path,b'NEWER FOREIGN APP',0o644)\n"+line, 1)
            value = self.rollback(f, race)
            self.assertEqual(value['status'], 'unknown_no_replay'); self.assertEqual(value['rollback_files'], [])
            self.assertEqual((f.root/m.UPDATE_PATH).read_bytes(), b'NEWER FOREIGN APP')
            self.assertEqual((self.stamp(f.update_private/'stage/app'), (f.update_private/'stage/app').read_bytes()), original)
            self.assertEqual(RollbackTransaction().original_records(f), records)

    def test_unreviewed_generator_inputs_are_rejected_before_remote_execution(self):
        with self.installed() as f:
            before = f.filesystem_snapshot()
            for key, value in (('operation', m.CONTINUATION_OPERATION), ('source', m.SOURCE),
                               ('tree', '0'*40), ('release', None), ('manifest', '0'*64),
                               ('path', m.ENTRY), ('parent', m.UPDATE_PARENT | {'run_id': '1'})):
                policy = copy.deepcopy(f.update_policy); policy[key] = value
                with self.subTest(key=key), self.assertRaises(RuntimeError):
                    m.update_remote_program(policy, f.update_files, UPDATE_IDENTITY, f.parent_identity, f.accepted)
            for files in ({}, f.update_files | {m.ENTRY: base64.b64encode(b'changed').decode()},
                          {m.UPDATE_PATH: base64.b64encode(b'unreviewed app').decode()}):
                with self.subTest(files=tuple(files)), self.assertRaises(RuntimeError):
                    m.update_remote_program(f.update_policy, files, UPDATE_IDENTITY, f.parent_identity, f.accepted)
            for original, accepted in (
                (f.parent_identity | {'run_id': m.CONTINUATION_PARENT['run_id']}, f.accepted),
                (f.parent_identity, f.accepted | {'status': 'unknown_no_replay'}),
                (f.parent_identity, f.accepted | {'snapshot_manifest_sha256': '0'*64})):
                with self.assertRaises(RuntimeError):
                    m.update_remote_program(f.update_policy, f.update_files, UPDATE_IDENTITY, original, accepted)
            self.assertEqual(f.filesystem_snapshot(), before)
            self.assertFalse(f.update_private.exists())

    def test_success_receipts_require_exact_identity_proofs_and_single_asset_scope(self):
        with self.installed() as f:
            f.update_accepted = self.execute(f)
            restored = self.rollback(f)
            for rollback, good in ((False, f.update_accepted), (True, restored)):
                verify = (lambda value: m.valid_update_rollback_result(UPDATE_ROLLBACK_IDENTITY, UPDATE_IDENTITY,
                          f.update_accepted, f.update_policy, value)) if rollback else \
                         (lambda value: m.valid_update_result(UPDATE_IDENTITY, f.update_policy, value))
                self.assertTrue(verify(good))
                changes = [('operation_id', m.CONTINUATION_OPERATION), ('source_sha', m.SOURCE),
                           ('manifest_sha256', '0'*64), ('control_source_sha', '0'*40),
                           ('command_comment_id', True), ('phase', 'preflight'), ('snapshot_manifest_sha256', None),
                           ('parent_install', f.parent_identity | {'run_id': '1'}),
                           ('rollback_files', [m.ENTRY]), ('unreviewed_field', 'not an allowed diagnostic')]
                changes += [(key, value) for key in ('supplier_calls', 'database_reads', 'database_writes',
                              'lead_calls', 'booking_calls', 'application_calls') for value in (1, False)]
                changes += [(key, False) for key, value in good.items() if type(value) is bool]
                changes += [('original_update', UPDATE_IDENTITY | {'run_id': '1'})] if rollback else [
                    ('written_files', [m.UPDATE_PATH, m.ENTRY]), ('parent_receipt_sha256', '0'*64)]
                for key, value in changes:
                    invalid = copy.deepcopy(good); invalid[key] = value
                    with self.subTest(rollback=rollback, key=key, value=value), self.assertRaises(RuntimeError): verify(invalid)
                invalid = copy.deepcopy(good); del invalid['snapshot_verified']
                with self.assertRaises(RuntimeError): verify(invalid)

    def test_client_receipt_restores_without_current_source_gates_or_parent_downloads(self):
        with self.installed() as f:
            f.update_accepted = self.execute(f)
            runner = f.base/'forward-runner'; runner.mkdir(); event_path = runner/'event.json'
            event_path.write_text(json.dumps(update_event()))
            env = environment() | {'GITHUB_RUN_ID': UPDATE_IDENTITY['run_id'], 'RUNNER_TEMP': str(runner),
                'GITHUB_EVENT_PATH': str(event_path), 'PREVIEW_HOST': 'example.invalid',
                'PREVIEW_USER': 'fixture', 'PREVIEW_KEY': 'PRIVATE KEY fixture'}
            with patch.dict(os.environ, env), patch.object(m, 'update_admission') as admission, \
                 patch.object(m, 'update_final_heads'), patch.object(m, 'download', side_effect=[b'prior', b'parent', b'new']) as download, \
                 patch.object(m, 'package', return_value=(f.policy, f.files)), \
                 patch.object(m, 'update_parent_evidence', return_value=f.accepted), \
                 patch.object(m, 'update_package', return_value=(f.update_policy, f.update_files)), \
                 patch.object(m, 'tv_successor_evidence', return_value={'status':'absent'}), \
                 patch.object(m.carrier, 'run', side_effect=[b'fixture known host', json.dumps(f.update_accepted).encode()]), \
                 patch('builtins.print'):
                m.update()
            self.assertEqual(admission.call_count, 2)
            self.assertEqual([call.args[0] for call in download.call_args_list],
                             [m.ARTIFACT, m.UPDATE_PARENT['artifact_id'], m.UPDATE_ARTIFACT])
            evidence_path = runner/'search3-public-install/evidence.json'
            good = json.loads(evidence_path.read_text())
            self.assertEqual(good['status'], 'updated_verified')
            self.assertEqual(good['previous_public'], f.policy['expected'])
            self.assertEqual(good['parent_result'], f.accepted)
            self.assertFalse((evidence_path.parent/'key').exists()); self.assertFalse((evidence_path.parent/'known').exists())
            def archive(value, extra=False):
                stream = io.BytesIO()
                with zipfile.ZipFile(stream, 'w') as packet:
                    packet.writestr('evidence.json', json.dumps(value, sort_keys=True)+'\n')
                    if extra: packet.writestr('unreviewed.json', '{}')
                return stream.getvalue()
            raw = archive(good)
            # Recovery retains the actual installed parent proof and all public
            # rows; no earlier artifact, source check, signer, or release is used.
            with patch.object(m, 'UPDATE_RELEASE', None), patch.object(m, 'RELEASE', None), \
                 patch.object(m, 'api', side_effect=AssertionError('unrelated API dependency')):
                policy, accepted, parent = m.update_source_evidence(raw, UPDATE_IDENTITY)
                self.assertEqual(policy['release'], RELEASE)
                self.assertEqual(policy['previous']['expected'], f.policy['expected'])
                self.assertEqual(accepted, f.update_accepted); self.assertEqual(parent, f.accepted)
                for change in [lambda v: v.update(status='unknown_no_replay'),
                               lambda v: v.update(run_id=m.UPDATE_PARENT['run_id']),
                               lambda v: v.update(release_sha=None),
                               lambda v: v.update(unreviewed_field=True),
                               lambda v: v['parent_result'].update(snapshot_manifest_sha256='0'*64),
                               lambda v: v['parent_result'].update(private_checks={}),
                               lambda v: v['previous_public'].pop(m.ENTRY),
                               lambda v: v['previous_public'][m.UPDATE_PATH].update(sha256='0'*64),
                               lambda v: v['remote'].update(status='unknown_no_replay'),
                               lambda v: v['remote'].update(parent_receipt_sha256='0'*64)]:
                    invalid = copy.deepcopy(good); change(invalid)
                    with self.subTest(change=change), self.assertRaises(RuntimeError):
                        m.update_source_evidence(archive(invalid), UPDATE_IDENTITY)
                with self.assertRaises(RuntimeError): m.update_source_evidence(archive(good, extra=True), UPDATE_IDENTITY)
            restored = self.rollback(f)
            recovery = f.base/'rollback-runner'; recovery.mkdir(); event_path = recovery/'event.json'
            event_path.write_text(json.dumps(update_rollback_event()))
            env.update(GITHUB_SHA=UPDATE_ROLLBACK_IDENTITY['control_source_sha'],
                       GITHUB_RUN_ID=UPDATE_ROLLBACK_IDENTITY['run_id'], RUNNER_TEMP=str(recovery), GITHUB_EVENT_PATH=str(event_path))
            with contextlib.ExitStack() as stack:
                stack.enter_context(patch.dict(os.environ, env))
                stack.enter_context(patch.object(m, 'UPDATE_RELEASE', None))
                stack.enter_context(patch.object(m, 'RELEASE', None))
                stack.enter_context(patch.object(m, 'update_rollback_admission', return_value=(UPDATE_IDENTITY, {'id': 333, 'digest': 'sha256:'+m.sha(raw)})))
                download = stack.enter_context(patch.object(m, 'download', return_value=raw))
                stack.enter_context(patch.object(m, 'update_rollback_commands', return_value=UPDATE_IDENTITY))
                stack.enter_context(patch.object(m, 'rollback_main'))
                stack.enter_context(patch.object(m, 'tv_successor_evidence', return_value={'status':'absent'}))
                for name in ('api', 'package', 'signing_evidence', 'update_parent_evidence', 'update_admission', 'update_final_heads'):
                    stack.enter_context(patch.object(m, name, side_effect=AssertionError('unnecessary recovery dependency: '+name)))
                stack.enter_context(patch.object(m.carrier, 'run', side_effect=[b'fixture known host', json.dumps(restored).encode()]))
                stack.enter_context(patch('builtins.print'))
                m.update(rollback=True)
                download.assert_called_once_with(333, m.sha(raw))
            evidence_path = recovery/'search3-public-install/evidence.json'
            self.assertEqual(json.loads(evidence_path.read_text())['status'], 'update_rolled_back_verified')
            self.assertFalse((evidence_path.parent/'key').exists()); self.assertFalse((evidence_path.parent/'known').exists())


SITE_IDENTITY = {'control_source_sha': CONTROL, 'run_id': '678901', 'command_comment_id': 9006}
SITE_ROLLBACK_IDENTITY = {'control_source_sha': 'd'*40, 'run_id': '789012', 'command_comment_id': 9007}


def site_event(rollback=False):
    value=event(); identity=SITE_ROLLBACK_IDENTITY if rollback else SITE_IDENTITY
    value['comment']['id']=identity['command_comment_id']
    value['comment']['body']=(f'{m.SITE_ROLLBACK_PREFIX} {identity["control_source_sha"]} {SITE_IDENTITY["run_id"]} {m.SITE_ROLLBACK_OPERATION}'
                              if rollback else f'{m.SITE_PREFIX} {identity["control_source_sha"]} {m.SITE_OPERATION}')
    return value


class SiteAdmission(unittest.TestCase):
    def metadata(self,path):
        if path=='/git/ref/heads/main': return {'object':{'sha':CONTROL}}
        if path=='/pulls/'+str(m.SITE_SOURCE_PR):
            return {'state':'closed','merged':True,'merge_commit_sha':CONTROL,
                    'head':{'sha':m.SITE_SOURCE,'repo':{'full_name':m.REPO}},'base':{'ref':'main','sha':'c'*40,'repo':{'full_name':m.REPO}}}
        if path=='/git/commits/'+m.SITE_SOURCE: return {'tree':{'sha':m.SITE_TREE}}
        if path.startswith('/contents/v2/'):
            relative=path.split('/contents/v2/',1)[1].split('?')[0]
            return {'type':'file','sha':m.SITE_TARGETS[relative]['blob']}
        if path.startswith('/contents/.github/workflows/deploy-anytoour.yml'):
            return {'type':'file','encoding':'base64','content':base64.b64encode(b'manual-only guarded publisher').decode()}
        if '/check-runs?' in path:
            checks=[{'id':i+1,'name':name,'head_sha':m.SITE_SOURCE,'status':'completed',
                     'conclusion':'skipped' if name=='production-evidence' else 'success','app':{'slug':'github-actions'}}
                    for i,name in enumerate((*m.SITE_CHECKS,'production-evidence'))]
            return {'total_count':len(checks),'check_runs':checks}
        if path=='/actions/runs/'+m.SITE_PARENT['run_id']:
            return {'id':int(m.SITE_PARENT['run_id']),'head_sha':m.SITE_PARENT['control_source_sha'],'head_branch':'main',
                    'path':m.WORKFLOW,'event':'issue_comment','run_attempt':1,'status':'completed','conclusion':'success',
                    'actor':{'login':'pyatkoff'},'triggering_actor':{'login':'pyatkoff'}}
        if path.startswith('/actions/runs/'+m.SITE_PARENT['run_id']+'/attempts/1/jobs?'):
            return {'total_count':1,'jobs':[{'id':m.SITE_PARENT['job_id'],'run_id':int(m.SITE_PARENT['run_id']),
                    'name':'public-install','status':'completed','conclusion':'success','steps':[
                        {'name':'Run python3 scripts/deploy/search3_public_install.py update','status':'completed','conclusion':'success'}]}]}
        if path=='/actions/artifacts/'+str(m.SITE_PARENT['artifact_id']):
            return {'id':m.SITE_PARENT['artifact_id'],'name':'search3-public-install-'+m.SITE_PARENT['run_id']+'-1',
                    'expired':False,'workflow_run':{'id':int(m.SITE_PARENT['run_id'])},'digest':'sha256:'+m.SITE_PARENT['artifact_sha256']}
        if path.startswith('/issues/4709/comments?'):
            parent=update_event()['comment'];parent.update(id=m.SITE_PARENT['command_comment_id'],
                    body=f'{m.UPDATE_PREFIX} {m.SITE_PARENT["control_source_sha"]} {m.UPDATE_OPERATION}')
            return [parent,site_event()['comment']]
        raise AssertionError('Unexpected SITE dependency: '+path)

    def admit(self,change=None):
        def api(path):
            value=self.metadata(path)
            return change(path,value) if change else value
        with patch.object(m,'api',side_effect=api),patch.object(m,'reviewed_control') as reviewed, \
             patch.object(m,'SITE_PUBLISHER_GUARD_SHA',m.sha(b'manual-only guarded publisher')):
            m.site_admission(SITE_IDENTITY);reviewed.assert_called_once_with(CONTROL)

    def test_fixed_source_targets_and_untouched_historical_pins(self):
        self.assertEqual(m.SITE_ORDER,('bundle-manifest-v1.php','assets.php','bundle-v1.php','site-page-shell-v1.php'))
        self.assertEqual((m.SITE_SOURCE_PR,m.SITE_SOURCE,m.SITE_TREE),(5133,'53c523c3664d2e9e893942d0eb63b2637e1df978','ef15774d5209de96d589104486f6cd957b1ef0aa'))
        self.assertEqual(m.SITE_PARENT['run_id'],'44887902112')
        self.assertEqual(m.SITE_PARENT['receipt_sha256'],'ab52afbbb2031c75dea9873cc9fe4004356474fdb10273ff1ef2e993d5cc8274')
        self.assertEqual(m.PUBLISHER_GUARD_SHA,'9b81eac1a94263fcfb9f49948495c45d143ffbb1bcd91d96921ee3257423da40')
        self.assertEqual(m.SITE_PUBLISHER_GUARD_SHA,m.sha((ROOT/'.github/workflows/deploy-anytoour.yml').read_bytes()))
        self.assertEqual(set(m.SITE_TARGETS),set(m.SITE_ORDER))
        self.assertEqual((len(m.SITE_DEPENDENCIES),len(m.SITE_OPTIONAL_DEPENDENCIES)),(154,62))
        for path,row in m.SITE_TARGETS.items():
            self.assertIn(m.sha((ROOT/'v2'/path).read_bytes()),(row['before']['sha256'],row['after']['sha256']))
            self.assertEqual(row['before']['mode'],'0644')
        # Every default native bundle input and each standalone stylesheet is
        # independently pinned, including the retained root/native route alias.
        import re
        manifest=(ROOT/'v2/bundle-manifest-v1.php').read_text().split('function v2_bundle_files(',1)[0]
        native=re.findall(r"'([^']+\.(?:css|js))'",manifest)
        self.assertEqual(len(native),82)
        self.assertTrue(set(native)<=set(m.SITE_DEPENDENCIES))
        for path in ('site-page-v1.css','site-coherence-v1.css','shared-content-primitives-v1.css','asset-version-v1.php',
                     'contacts/index.php','country-page-v1.php','site-header-v2.php','site-footer-v1.php','search-page-v2.php'):
            self.assertIn(path,m.SITE_DEPENDENCIES)
        for path in set(m.SITE_DEPENDENCIES)&set(m.CONTINUATION_PROTECTED):
            self.assertEqual(m.SITE_DEPENDENCIES[path],m.CONTINUATION_PROTECTED[path])
        self.assertFalse(set(m.SITE_ORDER)&(set(m.ADDITIONS)|set(m.CONTINUATION_PROTECTED)|set(m.SITE_DEPENDENCIES)))
        UpdateAdmission().test_successor_source_does_not_repin_the_consumed_install()

    def test_fixed_site_commands_share_strict_owner_main_attempt_boundary(self):
        for rollback,identity in ((False,SITE_IDENTITY),(True,SITE_ROLLBACK_IDENTITY)):
            env=environment()|{'GITHUB_SHA':identity['control_source_sha'],'GITHUB_RUN_ID':identity['run_id']}
            expected=(identity,SITE_IDENTITY['run_id']) if rollback else identity
            self.assertEqual(m.site_event_identity(site_event(rollback),env,rollback),expected)
            for key,value in (('GITHUB_ACTOR','other'),('GITHUB_TRIGGERING_ACTOR','other'),('GITHUB_REF','refs/heads/branch'),
                              ('GITHUB_WORKFLOW_REF','other'),('GITHUB_RUN_ATTEMPT','2'),('GITHUB_EVENT_NAME','workflow_dispatch')):
                with self.subTest(rollback=rollback,key=key),self.assertRaises(RuntimeError):
                    m.site_event_identity(site_event(rollback),env|{key:value},rollback)
            for mutate in (lambda e:e.update(action='edited'),lambda e:e['issue'].update(number=4217),
                           lambda e:e['issue'].update(pull_request={}),lambda e:e['comment']['user'].update(id=True),
                           lambda e:e['comment'].update(author_association='MEMBER'),
                           lambda e:e['comment'].update(body=e['comment']['body']+' edited')):
                value=site_event(rollback);mutate(value)
                with self.subTest(rollback=rollback,mutate=mutate),self.assertRaises(RuntimeError):m.site_event_identity(value,env,rollback)
        for mode in ('site-update','site-rollback'):self.assertEqual(m.command_mode([mode]),mode)
        for args in (['site-update','/tmp'],['site-rollback','123'],['--site-update']):
            with self.assertRaises(RuntimeError):m.command_mode(args)
        for old in (event(),update_event(),continuation_event(),rollback_event()):
            with self.assertRaises(RuntimeError):m.site_event_identity(old,environment())

    def test_source_is_merged_with_exact_four_blobs_and_latest_reviewed_checks(self):
        self.admit()
        for name in (*m.SITE_CHECKS,'production-evidence'):
            for change in ('pending','wronghead','untrusted','latest-failed'):
                def mutate(path,value):
                    if '/check-runs?' in path:
                        row=next(c for c in value['check_runs'] if c['name']==name)
                        if change=='pending':row.update(status='in_progress')
                        elif change=='wronghead':row.update(head_sha=m.UPDATE_SOURCE)
                        elif change=='untrusted':row.update(app={'slug':'untrusted'})
                        else:value['check_runs'].append(row|{'id':1000,'conclusion':'failure'});value['total_count']+=1
                    return value
                with self.subTest(name=name,change=change),self.assertRaises(RuntimeError):self.admit(mutate)
        def additional(path,value):
            if '/check-runs?' in path:
                value['check_runs'].append({'id':1000,'name':'unrelated','head_sha':m.SITE_SOURCE,'status':'queued'});value['total_count']+=1
            return value
        self.admit(additional)
        for endpoint,mutate in (('/pulls/5133',lambda v:v.update(merged=False)),('/pulls/5133',lambda v:v.update(merge_commit_sha='f'*40)),
                                 ('/pulls/5133',lambda v:v['head'].update(sha=m.UPDATE_SOURCE)),('/pulls/5133',lambda v:v['base'].update(ref='release/other')),
                                 ('/git/commits/'+m.SITE_SOURCE,lambda v:v['tree'].update(sha=m.UPDATE_TREE)),
                                 ('/contents/.github/workflows/deploy-anytoour.yml?ref='+CONTROL,lambda v:v.update(content=base64.b64encode(b'broad automatic writer').decode()))):
            def mutation(path,value):
                if path==endpoint:mutate(value)
                return value
            with self.subTest(endpoint=endpoint),self.assertRaises(RuntimeError):self.admit(mutation)
        for target in m.SITE_ORDER:
            for ref in (CONTROL,m.SITE_SOURCE):
                def mutation(path,value):
                    if path=='/contents/v2/'+target+'?ref='+ref:value['sha']='0'*40
                    return value
                with self.subTest(target=target,ref=ref),self.assertRaises(RuntimeError):self.admit(mutation)

    def test_parent_success_and_unique_commands_are_required(self):
        for fragment,mutate in (('/actions/runs/'+m.SITE_PARENT['run_id'],lambda v:v.update(run_attempt=2)),
                                ('/attempts/1/jobs?',lambda v:v['jobs'][0].update(id=1)),
                                ('/attempts/1/jobs?',lambda v:v['jobs'][0]['steps'][0].update(conclusion='failure')),
                                ('/actions/artifacts/',lambda v:v.update(expired=True)),
                                ('/comments?',lambda v:v.append(v[-1]|{'id':10000})),
                                ('/comments?',lambda v:v[0].update(author_association='MEMBER'))):
            def mutation(path,value):
                if path==fragment or (fragment.endswith('?') or fragment=='/actions/artifacts/') and fragment in path:mutate(value)
                return value
            with self.subTest(fragment=fragment),self.assertRaises(RuntimeError):self.admit(mutation)

    def test_old_public_rollback_stays_independent_of_new_publisher_and_source(self):
        with patch.object(m,'SITE_SOURCE',None),patch.object(m,'SITE_PUBLISHER_GUARD_SHA',None), \
             patch.object(m,'site_admission',side_effect=AssertionError('SITE gate must not affect public recovery')):
            UpdateRollbackAdmission().admission()


class SiteRollbackAdmission(unittest.TestCase):
    def metadata(self,path):
        if path=='/git/ref/heads/main':return {'object':{'sha':SITE_ROLLBACK_IDENTITY['control_source_sha']}}
        if path.startswith('/issues/4709/comments?'):return [site_event()['comment'],site_event(True)['comment']]
        if path=='/actions/runs/'+SITE_IDENTITY['run_id']:
            return {'id':int(SITE_IDENTITY['run_id']),'head_sha':CONTROL,'head_branch':'main','event':'issue_comment',
                    'path':m.WORKFLOW,'run_attempt':1,'status':'completed','conclusion':'success',
                    'actor':{'login':'pyatkoff'},'triggering_actor':{'login':'pyatkoff'}}
        if path.startswith('/actions/runs/'+SITE_IDENTITY['run_id']+'/attempts/1/jobs?'):
            return {'total_count':1,'jobs':[{'id':222,'run_id':int(SITE_IDENTITY['run_id']),'name':'public-install',
                    'status':'completed','conclusion':'success','steps':[{'name':'Run python3 scripts/deploy/search3_public_install.py site-update',
                                                                       'status':'completed','conclusion':'success'}]}]}
        if path.startswith('/actions/runs/'+SITE_IDENTITY['run_id']+'/artifacts?'):
            return {'total_count':1,'artifacts':[{'id':444,'name':'search3-public-install-'+SITE_IDENTITY['run_id']+'-1',
                    'expired':False,'workflow_run':{'id':int(SITE_IDENTITY['run_id'])},'digest':'sha256:'+'5'*64}]}
        raise AssertionError('SITE rollback fetched unrelated source proof: '+path)

    def admit(self,change=None):
        def api(path):
            value=self.metadata(path);return change(path,value) if change else value
        with patch.object(m,'api',side_effect=api),patch.object(m,'reviewed_control') as reviewed, \
             patch.object(m,'site_admission',side_effect=AssertionError('source gate during recovery')), \
             patch.object(m,'site_parent_admission',side_effect=AssertionError('old parent fetch during recovery')), \
             patch.object(m,'SITE_PUBLISHER_GUARD_SHA',None),patch.object(m,'UPDATE_RELEASE',None),patch.object(m,'RELEASE',None):
            original,artifact=m.site_rollback_admission(SITE_ROLLBACK_IDENTITY,SITE_IDENTITY['run_id'])
            self.assertEqual(original,SITE_IDENTITY);self.assertEqual(artifact['id'],444)
            reviewed.assert_called_once_with(SITE_ROLLBACK_IDENTITY['control_source_sha'])

    def test_owned_success_receipt_is_independent_of_source_and_old_publish_guard(self):
        self.admit()
        for original in (SITE_ROLLBACK_IDENTITY['run_id'],m.SITE_PARENT['run_id'],m.UPDATE_PARENT['run_id'],m.CONTINUATION_PARENT['run_id']):
            with patch.object(m,'api') as api,self.assertRaises(RuntimeError):m.site_rollback_commands(SITE_ROLLBACK_IDENTITY,original)
            api.assert_not_called()

    def test_duplicate_command_wrong_owner_failed_attempt_or_foreign_artifact_is_rejected(self):
        for part,mutate in (('/comments?',lambda v:v.append(v[0]|{'id':9008})),('/comments?',lambda v:v.append(v[1]|{'id':9008})),
                            ('/comments?',lambda v:v[0].update(author_association='MEMBER')),('/comments?',lambda v:v[1].update(body=v[1]['body']+' edited')),
                            ('/actions/runs/678901',lambda v:v.update(run_attempt=2)),('/actions/runs/678901',lambda v:v.update(conclusion='failure')),
                            ('/actions/runs/678901',lambda v:v.update(head_sha='f'*40)),('/attempts/1/jobs?',lambda v:v['jobs'][0]['steps'][0].update(conclusion='failure')),
                            ('/artifacts?',lambda v:v['artifacts'][0].update(expired=True)),('/artifacts?',lambda v:v['artifacts'][0].update(workflow_run={'id':1})),
                            ('/artifacts?',lambda v:v['artifacts'][0].update(digest='untrusted'))):
            def change(path,value):
                if path==part or part.endswith('?') and part in path:mutate(value)
                return value
            with self.subTest(part=part),self.assertRaises(RuntimeError):self.admit(change)


class SiteTransaction(unittest.TestCase):
    @contextlib.contextmanager
    def installed(self):
        helper=UpdateTransaction()
        with helper.installed() as f:
            f.update_accepted=helper.execute(f);self.assertEqual(f.update_accepted['status'],'updated_verified')
            parent_value={**UPDATE_IDENTITY,'operation_id':m.UPDATE_OPERATION,'source_sha':m.UPDATE_SOURCE,
                          'manifest_sha256':m.UPDATE_MANIFEST_SHA,'read_only':False,'phase':'complete','ssh_started':True,
                          'status':'updated_verified','parent_install':f.parent_identity,'parent_result':f.accepted,
                          'previous_public':f.policy['expected'],'release_sha':RELEASE,'remote':f.update_accepted}
            parent_data=(json.dumps(parent_value,sort_keys=True)+'\n').encode();packet=io.BytesIO()
            with zipfile.ZipFile(packet,'w') as archive:archive.writestr('evidence.json',parent_data)
            f.site_parent_raw=packet.getvalue()
            parent=m.SITE_PARENT|UPDATE_IDENTITY|{'receipt_sha256':m.sha(parent_data),'artifact_sha256':m.sha(f.site_parent_raw),
                                                 'snapshot_manifest_sha256':f.update_accepted['snapshot_manifest_sha256']}
            targets={};files={}
            for path in m.SITE_ORDER:
                old=('<?php /* before '+path+' */\n').encode();new=('<?php /* approved '+path+' */\n').encode();f.put(path,old)
                row=lambda raw:{'state':'present','sha256':m.sha(raw),'size':len(raw),'mode':'0644'}
                targets[path]={'before':row(old),'after':row(new),'blob':m.hashlib.sha1(b'blob '+str(len(new)).encode()+b'\0'+new).hexdigest()}
                files[path]=base64.b64encode(new).decode()
            dependencies={};optional={}
            for path in m.SITE_DEPENDENCIES:
                if path not in f.policy['protected']:f.put(path,('site protected '+path).encode())
                dependencies[path]=m.sha((f.root/path).read_bytes())
            for index,path in enumerate(m.SITE_OPTIONAL_DEPENDENCIES):
                raw=('fixed optional '+path).encode();optional[path]=m.sha(raw)
                if index%2==0:f.put(path,raw)
            f.site_files=files;f.site_parent_value=parent_value;f.site_private=f.private.parent/m.SITE_OPERATION
            with patch.object(m,'SITE_PARENT',parent),patch.object(m,'SITE_TARGETS',targets), \
                 patch.object(m,'SITE_DEPENDENCIES',dependencies),patch.object(m,'SITE_OPTIONAL_DEPENDENCIES',optional), \
                 patch.object(m,'CONTINUATION_PROTECTED',f.policy['protected']),patch.object(m,'ENTRY_BEFORE',f.policy['before']):
                f.site_policy=m.site_policy();yield f

    def execute(self,f,change=None):
        program=m.site_remote_program(f.site_policy,f.site_files,SITE_IDENTITY,f.site_parent_value)
        value=UpdateTransaction().run_program(f,program,change);m.valid_site_result(SITE_IDENTITY,value)
        return value

    def rollback(self,f,change=None):
        program=m.site_rollback_remote_program(f.site_policy,SITE_ROLLBACK_IDENTITY,SITE_IDENTITY,f.site_accepted,f.site_parent_value)
        value=UpdateTransaction().run_program(f,program,change)
        m.valid_site_result(SITE_ROLLBACK_IDENTITY,value,SITE_IDENTITY,f.site_accepted)
        return value

    def public_files(self,f):
        paths=set(f.policy['expected'])|set(f.policy['protected'])|set(m.SITE_ORDER)|set(m.SITE_DEPENDENCIES)|set(m.SITE_OPTIONAL_DEPENDENCIES)|{'config.php'}
        return {p:(UpdateTransaction().stamp(f.root/p),(f.root/p).read_bytes()) if (f.root/p).exists() else None for p in paths}

    def parent_records(self,f):
        return {str(p.relative_to(f.private.parent)):(UpdateTransaction().stamp(p),p.read_bytes())
                for root in (f.private,f.update_private) for p in root.rglob('*') if p.is_file()}

    def fail_after_exchange(self,index):
        def change(program):
            line="   exchange(src,name,dst,target);switched.append(path);result['written_files'].append(path);sync(src);sync(dst)"
            self.assertIn(line,program)
            return program.replace(line,line+f"\n   if index=={index}: raise RuntimeError('operation_failed')",1)
        return change

    def test_four_ordered_owners_roundtrip_restores_inodes_and_parent_public_rollback(self):
        with self.installed() as f:
            before=self.public_files(f);parent=self.parent_records(f)
            f.site_accepted=self.execute(f);self.assertEqual(f.site_accepted['status'],'site_updated_verified')
            self.assertEqual(f.site_accepted['written_files'],list(m.SITE_ORDER))
            after=self.public_files(f);self.assertEqual({p for p in before if before[p]!=after[p]},set(m.SITE_ORDER))
            self.assertEqual(self.parent_records(f),parent)
            for index,path in enumerate(m.SITE_ORDER):
                saved=f.site_private/'stage'/str(index)
                self.assertEqual((UpdateTransaction().stamp(saved),saved.read_bytes()),before[path])
            records={str(p.relative_to(f.site_private)):p.read_bytes() for p in f.site_private.rglob('*') if p.is_file() and 'stage' not in p.parts}
            result=self.rollback(f);self.assertEqual(result['status'],'site_rolled_back_verified')
            self.assertEqual(result['rollback_files'],list(reversed(m.SITE_ORDER)))
            self.assertEqual(self.public_files(f),before);self.assertEqual(self.parent_records(f),parent)
            self.assertEqual({str(p.relative_to(f.site_private)):p.read_bytes() for p in f.site_private.rglob('*') if p.is_file() and 'stage' not in p.parts},records)
            self.assertEqual(UpdateTransaction().rollback(f)['status'],'update_rolled_back_verified')
            self.assertEqual(RollbackTransaction().execute(f)['status'],'rolled_back_verified');f.restored()

    def test_active_site_does_not_break_existing_public_update_recovery(self):
        with self.installed() as f:
            f.site_accepted=self.execute(f);self.assertEqual(f.site_accepted['status'],'site_updated_verified')
            targets={p:(f.root/p).read_bytes() for p in m.SITE_ORDER}
            self.assertEqual(UpdateTransaction().rollback(f)['status'],'update_rolled_back_verified')
            self.assertEqual(RollbackTransaction().execute(f)['status'],'rolled_back_verified');f.restored()
            self.assertEqual({p:(f.root/p).read_bytes() for p in m.SITE_ORDER},targets)

    def test_failure_after_each_exchange_restores_exact_original_inodes(self):
        for index in range(4):
            with self.subTest(index=index),self.installed() as f:
                before=self.public_files(f);parent=self.parent_records(f)
                result=self.execute(f,self.fail_after_exchange(index))
                self.assertEqual(result['status'],'rolled_back_no_replay')
                self.assertEqual(result['written_files'],list(m.SITE_ORDER[:index+1]))
                self.assertEqual(result['rollback_files'],list(reversed(m.SITE_ORDER[:index+1])))
                self.assertEqual(self.public_files(f),before);self.assertEqual(self.parent_records(f),parent)
                consumed=f.filesystem_snapshot();again=self.execute(f)
                self.assertEqual(again['status'],'blocked_no_replay');self.assertEqual(f.filesystem_snapshot(),consumed)

    def test_target_preimages_links_and_modes_fail_before_any_reservation(self):
        for path in m.SITE_ORDER:
            for kind in ('bytes','mode','symlink','hardlink'):
                with self.subTest(path=path,kind=kind),self.installed() as f:
                    target=f.root/path
                    if kind=='bytes':target.write_bytes(b'foreign PHP')
                    elif kind=='mode':target.chmod(0o600)
                    elif kind=='symlink':target.unlink();target.symlink_to(f.root/'config.php')
                    else:os.link(target,f.home/'foreign-link')
                    before=f.filesystem_snapshot();result=self.execute(f)
                    self.assertEqual(result['status'],'blocked_no_replay');self.assertEqual(result['written_files'],[])
                    self.assertFalse(f.site_private.exists());self.assertEqual(f.filesystem_snapshot(),before)

    def test_every_guard_category_and_both_private_parent_lineages_are_checked(self):
        cases=('app','fallback','next','native','css','route','private','original-result','update-result','update-intent','retained-app','optional')
        for kind in cases:
            with self.subTest(kind=kind),self.installed() as f:
                paths={'app':f.root/m.UPDATE_PATH,'fallback':f.root/'poisk-turov-old/index.php',
                       'next':f.root/'_preview/search3-next-candidate/visual-search/index.php','native':f.root/'accessibility.js',
                       'css':f.root/'site-page-v1.css','route':f.root/'contacts/index.php','private':f.root/'config.php',
                       'original-result':f.private/'result.json','update-result':f.update_private/'result.json',
                       'update-intent':f.update_private/'intent.json','retained-app':f.update_private/'stage/app',
                       'optional':f.root/next(iter(m.SITE_OPTIONAL_DEPENDENCIES))}
                paths[kind].write_bytes(b'foreign');before=f.filesystem_snapshot();result=self.execute(f)
                self.assertEqual(result['status'],'blocked_no_replay');self.assertFalse(f.site_private.exists())
                self.assertEqual(f.filesystem_snapshot(),before)

    def test_snapshot_and_drill_are_proven_before_first_exchange(self):
        for kind in ('snapshot','drill'):
            for index in range(4):
                with self.subTest(kind=kind,index=index),self.installed() as f:
                    before=self.public_files(f);parent=self.parent_records(f)
                    def change(program):
                        line=f"  require(private.read('{kind}/'+str(index))[0]==targets[path]['before']"
                        injection=(f"  if index=={index}:\n   with private.parent('{kind}/'+str(index)) as (fd,name):\n"
                                   "    leaf=os.open(name,os.O_WRONLY|os.O_TRUNC,dir_fd=fd);os.write(leaf,b'corrupt');os.close(leaf)\n")
                        self.assertIn(line,program);return program.replace(line,injection+line,1)
                    value=self.execute(f,change)
                    self.assertEqual(value['status'],'rolled_back_no_replay');self.assertEqual(value['written_files'],[])
                    self.assertEqual(value['reason'],'snapshot_invalid' if kind=='snapshot' else 'restore_drill_failed')
                    self.assertEqual(self.public_files(f),before);self.assertEqual(self.parent_records(f),parent)

    def test_foreign_target_at_each_exchange_is_returned_without_overwrite(self):
        for selected in range(4):
            for kind in ('file','same-bytes','symlink','hardlink'):
                with self.subTest(selected=selected,kind=kind),self.installed() as f:
                    before=self.public_files(f);parent=self.parent_records(f)
                    def change(program):
                        line="   exchange(src,name,dst,target);switched.append(path);result['written_files'].append(path);sync(src);sync(dst)"
                        injection=f"   if index=={selected}:\n"
                        if kind=='symlink':injection+="    os.symlink('foreign-owner',target+'.race',dir_fd=dst)\n"
                        else:
                            raw="public.read(path,True)[2]" if kind=='same-bytes' else "b'FOREIGN OWNER'"
                            injection+=f"    raw={raw};leaf=os.open(target+'.race',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o644,dir_fd=dst);os.write(leaf,raw);os.close(leaf)\n"
                            if kind=='hardlink':injection+="    os.link(target+'.race',target+'.foreign-link',src_dir_fd=dst,dst_dir_fd=dst)\n"
                        injection+="    os.rename(target+'.race',target,src_dir_fd=dst,dst_dir_fd=dst)\n"
                        return program.replace(line,injection+line,1)
                    value=self.execute(f,change);self.assertEqual(value['status'],'unknown_no_replay')
                    path=m.SITE_ORDER[selected];target=f.root/path
                    if kind=='symlink':self.assertTrue(target.is_symlink());self.assertEqual(os.readlink(target),'foreign-owner')
                    else:
                        self.assertEqual(target.read_bytes(),before[path][1] if kind=='same-bytes' else b'FOREIGN OWNER')
                        self.assertNotEqual(target.stat().st_ino,before[path][0][1])
                        if kind=='hardlink':self.assertEqual(target.stat().st_nlink,2)
                    for other,row in before.items():
                        if other!=path and row is not None:self.assertEqual((UpdateTransaction().stamp(f.root/other),(f.root/other).read_bytes()),row)
                    self.assertEqual(self.parent_records(f),parent)

    def test_parent_directory_race_fails_closed_before_public_write(self):
        with self.installed() as f:
            before=self.public_files(f);parent=self.parent_records(f)
            def change(program):
                line=" current=dict(before);result['phase']='assets'"
                injection=" os.rename(home+'/www/anytoour.ru',home+'/www/retained-root');os.mkdir(home+'/www/anytoour.ru');open(home+'/www/anytoour.ru/foreign','wb').write(b'NEW OWNER')\n"
                self.assertIn(line,program);return program.replace(line,injection+line,1)
            result=self.execute(f,change)
            self.assertEqual(result['status'],'unknown_no_replay');self.assertEqual(result['written_files'],[])
            self.assertEqual((f.root/'foreign').read_bytes(),b'NEW OWNER')
            old=f.root;f.root=f.home/'www/retained-root'
            self.assertEqual(self.public_files(f),before);f.root=old;self.assertEqual(self.parent_records(f),parent)

    def test_unknown_and_successful_site_operations_never_replay(self):
        for unknown in (True,False):
            with self.subTest(unknown=unknown),self.installed() as f:
                if unknown:
                    f.site_private.mkdir(mode=0o700);(f.site_private/'reservation.json').write_bytes(b'unknown consumed operation')
                else:
                    f.site_accepted=self.execute(f);self.assertEqual(f.site_accepted['status'],'site_updated_verified')
                before=f.filesystem_snapshot();value=self.execute(f)
                self.assertEqual(value['status'],'blocked_no_replay');self.assertEqual(f.filesystem_snapshot(),before)
                if not unknown:
                    self.assertEqual(self.rollback(f)['status'],'site_rolled_back_verified');before=f.filesystem_snapshot()
                    self.assertEqual(self.rollback(f)['status'],'blocked_no_replay');self.assertEqual(f.filesystem_snapshot(),before)

    def test_rollback_rejects_changed_targets_and_retained_proof(self):
        for kind in ('bytes','inode','symlink','hardlink','snapshot','intent','result','retained','parent','protected'):
            with self.subTest(kind=kind),self.installed() as f:
                f.site_accepted=self.execute(f);self.assertEqual(f.site_accepted['status'],'site_updated_verified')
                target=f.root/m.SITE_ORDER[-1]
                if kind=='bytes':target.write_bytes(b'newer site owner')
                elif kind=='inode':raw=target.read_bytes();target.unlink();f.put(m.SITE_ORDER[-1],raw)
                elif kind=='symlink':target.unlink();target.symlink_to('foreign-site-owner')
                elif kind=='hardlink':os.link(target,f.home/'new-owner-link')
                elif kind=='snapshot':(f.site_private/'snapshot/manifest.json').write_bytes(b'{}')
                elif kind=='intent':(f.site_private/'intent-3').write_bytes(b'{}')
                elif kind=='result':(f.site_private/'result.json').write_bytes(b'{}')
                elif kind=='retained':(f.site_private/'stage/3').write_bytes(b'corrupt predecessor')
                elif kind=='parent':(f.update_private/'result.json').write_bytes(b'{}')
                else:(f.root/'site-page-v1.css').write_bytes(b'new stylesheet')
                before=f.filesystem_snapshot();value=self.rollback(f)
                self.assertEqual(value['status'],'blocked_no_replay');self.assertEqual(value['rollback_files'],[])
                self.assertFalse((f.private.parent/m.SITE_ROLLBACK_OPERATION).exists());self.assertEqual(f.filesystem_snapshot(),before)

    def test_rollback_fault_after_each_restoration_is_sealed_and_never_overclaims(self):
        for selected in reversed(range(4)):
            with self.subTest(selected=selected),self.installed() as f:
                before=self.public_files(f);f.site_accepted=self.execute(f)
                def change(program):
                    line="  result['rollback_files'].append(path)"
                    self.assertIn(line,program);return program.replace(line,line+f"\n  if index=={selected}: raise RuntimeError('operation_failed')",1)
                value=self.rollback(f,change);self.assertEqual(value['status'],'unknown_no_replay')
                self.assertEqual(value['rollback_files'],list(reversed(m.SITE_ORDER[selected:])))
                for path in m.SITE_ORDER[selected:]:self.assertEqual((UpdateTransaction().stamp(f.root/path),(f.root/path).read_bytes()),before[path])
                consumed=f.filesystem_snapshot();self.assertEqual(self.rollback(f)['status'],'blocked_no_replay')
                self.assertEqual(f.filesystem_snapshot(),consumed)

    def test_foreign_writer_during_requested_restore_survives(self):
        for stage in ('capture','restore'):
            with self.subTest(stage=stage),self.installed() as f:
                f.site_accepted=self.execute(f);parent=self.parent_records(f)
                def change(program):
                    if stage=='capture':
                        line='\n   move_noreplace(src,name,dst,target);moved=True;sync(src);sync(dst)'
                        inject="\n   leaf=os.open(name+'.race',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o644,dir_fd=src);os.write(leaf,b'FOREIGN OWNER');os.close(leaf)\n   os.rename(name+'.race',name,src_dir_fd=src,dst_dir_fd=src)"
                    else:
                        line=" restore_capture(public,store,path,'stage/'+str(index))"
                        inject=" public.create(path,b'FOREIGN OWNER',0o644)\n"
                    self.assertIn(line,program);return program.replace(line,inject+line,1)
                value=self.rollback(f,change);self.assertEqual(value['status'],'unknown_no_replay')
                self.assertEqual((f.root/m.SITE_ORDER[-1]).read_bytes(),b'FOREIGN OWNER')
                self.assertEqual(self.parent_records(f),parent)
                self.assertEqual((f.site_private/'stage/3').read_bytes(),b'<?php /* before site-page-shell-v1.php */\n')

    def test_protected_or_parent_change_during_activation_unwinds_only_owned_targets(self):
        for target in ('site-page-v1.css','visual-search/app.js','original-result','updated-result'):
            with self.subTest(target=target),self.installed() as f:
                before={p:(UpdateTransaction().stamp(f.root/p),(f.root/p).read_bytes()) for p in m.SITE_ORDER}
                def change(program):
                    line='  site_check_targets(current);site_check_parents()'
                    store="original" if target=='original-result' else "updated" if target=='updated-result' else "public"
                    path='result.json' if target.endswith('-result') else target
                    inject=("  if index==1:\n"+f"   with {store}.parent({path!r}) as (fd,name):\n"
                            "    leaf=os.open(name,os.O_WRONLY|os.O_TRUNC,dir_fd=fd);os.write(leaf,b'FOREIGN OWNER');os.close(leaf)\n")
                    self.assertIn(line,program);return program.replace(line,inject+line,1)
                value=self.execute(f,change);self.assertEqual(value['status'],'unknown_no_replay')
                self.assertEqual(value['rollback_files'],list(reversed(m.SITE_ORDER[:2])))
                for p,row in before.items():self.assertEqual((UpdateTransaction().stamp(f.root/p),(f.root/p).read_bytes()),row)
                changed=f.private/'result.json' if target=='original-result' else f.update_private/'result.json' if target=='updated-result' else f.root/target
                self.assertEqual(changed.read_bytes(),b'FOREIGN OWNER')

    def test_payload_and_parent_receipt_are_exact_and_no_new_path_can_be_injected(self):
        with self.installed() as f:
            self.assertEqual(m.site_parent_evidence(f.site_parent_raw),f.site_parent_value)
            with self.assertRaises(RuntimeError):m.site_parent_evidence(f.site_parent_raw+b'changed')
            def blob(path):
                name=next(p for p in m.SITE_ORDER if path=='/git/blobs/'+m.SITE_TARGETS[p]['blob'])
                raw=base64.b64decode(f.site_files[name]);return {'sha':m.SITE_TARGETS[name]['blob'],'size':len(raw),
                                                              'encoding':'base64','content':f.site_files[name]}
            with patch.object(m,'api',side_effect=blob):self.assertEqual(m.site_package(),f.site_files)
            for kind in ('bytes','blob','size'):
                def forged(path):
                    value=blob(path)
                    if kind=='bytes':value['content']=base64.b64encode(b'<?php unreviewed').decode()
                    elif kind=='blob':value['sha']='0'*40
                    else:value['size']+=1
                    return value
                with self.subTest(kind=kind),patch.object(m,'api',side_effect=forged),self.assertRaises(RuntimeError):m.site_package()
            for changed in (f.site_policy|{'operation':m.UPDATE_OPERATION},f.site_policy|{'source':m.UPDATE_SOURCE},
                            f.site_policy|{'order':m.SITE_ORDER+(m.ENTRY,)},f.site_policy|{'dependencies':{}}):
                with self.assertRaises(RuntimeError):m.site_remote_program(changed,f.site_files,SITE_IDENTITY,f.site_parent_value)
            for files in ({},f.site_files|{m.ENTRY:base64.b64encode(b'<?php bad').decode()},
                          f.site_files|{m.SITE_ORDER[0]:base64.b64encode(b'<?php bad').decode()}):
                with self.assertRaises(RuntimeError):m.site_remote_program(f.site_policy,files,SITE_IDENTITY,f.site_parent_value)

    def test_client_receipt_is_self_contained_for_recovery_after_control_change(self):
        with self.installed() as f:
            f.site_accepted=self.execute(f);self.assertEqual(f.site_accepted['status'],'site_updated_verified')
            runner=f.base/'site-runner';runner.mkdir();event_path=runner/'event.json';event_path.write_text(json.dumps(site_event()))
            env=environment()|{'GITHUB_RUN_ID':SITE_IDENTITY['run_id'],'RUNNER_TEMP':str(runner),'GITHUB_EVENT_PATH':str(event_path),
                               'PREVIEW_HOST':'example.invalid','PREVIEW_USER':'fixture','PREVIEW_KEY':'PRIVATE KEY fixture'}
            with patch.dict(os.environ,env),patch.object(m,'site_admission') as admission,patch.object(m,'site_final_heads'), \
                 patch.object(m,'download',return_value=f.site_parent_raw) as download,patch.object(m,'site_package',return_value=f.site_files), \
                 patch.object(m,'tv_successor_evidence',return_value={'status':'absent'}), \
                 patch.object(m.carrier,'run',side_effect=[b'fixture known host',json.dumps(f.site_accepted).encode()]),patch('builtins.print'):
                m.site_update()
            self.assertEqual(admission.call_count,2);download.assert_called_once_with(m.SITE_PARENT['artifact_id'],m.SITE_PARENT['artifact_sha256'])
            evidence_path=runner/'search3-public-install/evidence.json';good=json.loads(evidence_path.read_text())
            self.assertEqual(good['status'],'site_updated_verified');self.assertEqual(good['parent_evidence'],f.site_parent_value)
            self.assertFalse((evidence_path.parent/'key').exists());self.assertFalse((evidence_path.parent/'known').exists())
            def archive(value,extra=False):
                packet=io.BytesIO()
                with zipfile.ZipFile(packet,'w') as zipped:
                    zipped.writestr('evidence.json',json.dumps(value,sort_keys=True)+'\n')
                    if extra:zipped.writestr('other.json','{}')
                return packet.getvalue()
            raw=archive(good)
            with patch.object(m,'UPDATE_RELEASE',None),patch.object(m,'RELEASE',None),patch.object(m,'SITE_PUBLISHER_GUARD_SHA',None), \
                 patch.object(m,'api',side_effect=AssertionError('unrelated API dependency')):
                policy,accepted,parent=m.site_source_evidence(raw,SITE_IDENTITY)
                self.assertEqual(policy,f.site_policy);self.assertEqual(accepted,f.site_accepted);self.assertEqual(parent,f.site_parent_value)
                for change in (lambda v:v.update(status='unknown_no_replay'),lambda v:v.update(source_sha=m.UPDATE_SOURCE),
                               lambda v:v.update(unreviewed=True),lambda v:v['parent_evidence'].update(run_id='1'),
                               lambda v:v['remote'].update(status='unknown_no_replay'),lambda v:v['remote'].update(written_files=[m.ENTRY])):
                    invalid=copy.deepcopy(good);change(invalid)
                    with self.subTest(change=change),self.assertRaises(RuntimeError):m.site_source_evidence(archive(invalid),SITE_IDENTITY)
                with self.assertRaises(RuntimeError):m.site_source_evidence(archive(good,True),SITE_IDENTITY)
            restored=self.rollback(f);self.assertEqual(restored['status'],'site_rolled_back_verified')
            recovery=f.base/'site-recovery';recovery.mkdir();event_path=recovery/'event.json';event_path.write_text(json.dumps(site_event(True)))
            env.update(GITHUB_SHA=SITE_ROLLBACK_IDENTITY['control_source_sha'],GITHUB_RUN_ID=SITE_ROLLBACK_IDENTITY['run_id'],
                       RUNNER_TEMP=str(recovery),GITHUB_EVENT_PATH=str(event_path))
            with contextlib.ExitStack() as stack:
                stack.enter_context(patch.dict(os.environ,env))
                stack.enter_context(patch.object(m,'UPDATE_RELEASE',None));stack.enter_context(patch.object(m,'RELEASE',None))
                stack.enter_context(patch.object(m,'SITE_PUBLISHER_GUARD_SHA',None))
                stack.enter_context(patch.object(m,'site_rollback_admission',return_value=(SITE_IDENTITY,{'id':444,'digest':'sha256:'+m.sha(raw)})))
                stack.enter_context(patch.object(m,'site_rollback_commands',return_value=SITE_IDENTITY));stack.enter_context(patch.object(m,'rollback_main'))
                stack.enter_context(patch.object(m,'tv_successor_evidence',return_value={'status':'absent'}))
                download=stack.enter_context(patch.object(m,'download',return_value=raw))
                for name in ('api','site_admission','site_final_heads','site_package','site_parent_admission','site_parent_evidence','signing_evidence'):
                    stack.enter_context(patch.object(m,name,side_effect=AssertionError('unnecessary recovery dependency: '+name)))
                stack.enter_context(patch.object(m.carrier,'run',side_effect=[b'fixture known host',json.dumps(restored).encode()]))
                stack.enter_context(patch('builtins.print'));m.site_update(rollback=True)
                download.assert_called_once_with(444,m.sha(raw))
            result=json.loads((recovery/'search3-public-install/evidence.json').read_text())
            self.assertEqual(result['status'],'site_rolled_back_verified')

    def test_success_receipt_requires_all_proofs_and_exact_ordered_scope(self):
        with self.installed() as f:
            f.site_accepted=self.execute(f);restored=self.rollback(f)
            for rollback,good in ((False,f.site_accepted),(True,restored)):
                verify=(lambda v:m.valid_site_result(SITE_ROLLBACK_IDENTITY,v,SITE_IDENTITY,f.site_accepted)) if rollback else (lambda v:m.valid_site_result(SITE_IDENTITY,v))
                self.assertTrue(verify(good))
                changes=[('operation_id',m.UPDATE_OPERATION),('source_sha',m.UPDATE_SOURCE),('manifest_sha256','0'*64),
                         ('parent_update',UPDATE_IDENTITY|{'run_id':'1'}),('parent_receipt_sha256','0'*64),('command_comment_id',True),
                         ('phase','preflight'),('snapshot_manifest_sha256',None),('unreviewed',True),('rollback_files',[m.ENTRY])]
                changes += [(key,False) for key,value in good.items() if type(value) is bool]
                changes += [(key,value) for key in ('supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls') for value in (1,False)]
                changes += [('rollback_files',list(m.SITE_ORDER))] if rollback else [('written_files',list(reversed(m.SITE_ORDER)))]
                for key,value in changes:
                    invalid=copy.deepcopy(good);invalid[key]=value
                    with self.subTest(rollback=rollback,key=key),self.assertRaises(RuntimeError):verify(invalid)


SITE_INSPECTION_IDENTITY = {'control_source_sha':'e'*40,'run_id':'890123','command_comment_id':9008}


def site_inspection_event():
    value=event();value['comment'].update(id=SITE_INSPECTION_IDENTITY['command_comment_id'],
        body=f'{m.SITE_INSPECT_PREFIX} {SITE_INSPECTION_IDENTITY["control_source_sha"]} {m.SITE_INSPECT_OPERATION}')
    return value


class SiteInspectionAdmission(unittest.TestCase):
    def metadata(self,path):
        failed=m.SITE_INSPECT_FAILED
        if path=='/git/ref/heads/main':return {'object':{'sha':SITE_INSPECTION_IDENTITY['control_source_sha']}}
        if path=='/pulls/'+str(m.SITE_SOURCE_PR):
            return {'state':'closed','merged':True,'merge_commit_sha':failed['control_source_sha'],
                    'head':{'sha':m.SITE_SOURCE,'repo':{'full_name':m.REPO}},'base':{'ref':'main','repo':{'full_name':m.REPO}}}
        if path.startswith('/contents/v2/'):
            relative=path.split('/contents/v2/',1)[1].split('?')[0]
            return {'type':'file','sha':m.SITE_TARGETS[relative]['blob']}
        if path=='/actions/runs/'+failed['run_id']:
            return {'id':int(failed['run_id']),'head_sha':failed['control_source_sha'],'head_branch':'main','path':m.WORKFLOW,
                    'event':'issue_comment','run_attempt':1,'status':'completed','conclusion':'failure',
                    'actor':{'login':'pyatkoff'},'triggering_actor':{'login':'pyatkoff'}}
        if path.startswith('/actions/runs/'+failed['run_id']+'/attempts/1/jobs?'):
            return {'total_count':1,'jobs':[{'id':failed['job_id'],'run_id':int(failed['run_id']),'name':'public-install',
                    'status':'completed','conclusion':'failure','steps':[{'name':'Run python3 scripts/deploy/search3_public_install.py site-update',
                                                                       'status':'completed','conclusion':'failure'}]}]}
        if path=='/actions/artifacts/'+str(failed['artifact_id']):
            return {'id':failed['artifact_id'],'name':'search3-public-install-'+failed['run_id']+'-1','expired':False,
                    'workflow_run':{'id':int(failed['run_id'])},'digest':'sha256:'+failed['artifact_sha256']}
        if path.startswith('/issues/4709/comments?'):
            owner={'user':{'id':226193297},'author_association':'OWNER'}
            return [owner|{'id':failed['command_comment_id'],'body':f'{m.SITE_PREFIX} {failed["control_source_sha"]} {m.SITE_OPERATION}'},
                    owner|{'id':failed['terminal_comment_id'],'body':' '.join(str(failed[k]) for k in
                       ('run_id','control_source_sha','command_comment_id','job_id','artifact_id','artifact_sha256','receipt_sha256'))+' blocked_no_replay dependency_drift NO_REPLAY'},
                    site_inspection_event()['comment']]
        raise AssertionError('Unrelated inspection dependency: '+path)

    def admit(self,change=None):
        def api(path):
            value=self.metadata(path);return change(path,value) if change else value
        with patch.object(m,'api',side_effect=api),patch.object(m,'reviewed_control') as reviewed, \
             patch.object(m,'site_admission',side_effect=AssertionError('write admission during inspection')):
            m.site_inspect_admission(SITE_INSPECTION_IDENTITY)
            reviewed.assert_called_once_with(SITE_INSPECTION_IDENTITY['control_source_sha'])

    def test_fixed_readonly_command_and_current_reviewed_control_can_advance(self):
        env=environment()|{'GITHUB_SHA':SITE_INSPECTION_IDENTITY['control_source_sha'],'GITHUB_RUN_ID':SITE_INSPECTION_IDENTITY['run_id']}
        self.assertEqual(m.site_inspect_event_identity(site_inspection_event(),env),SITE_INSPECTION_IDENTITY)
        self.assertEqual(m.command_mode(['site-inspect']),'site-inspect');self.admit()
        self.assertNotEqual(SITE_INSPECTION_IDENTITY['control_source_sha'],m.SITE_INSPECT_FAILED['control_source_sha'])
        for key,value in (('GITHUB_ACTOR','other'),('GITHUB_REF','refs/heads/topic'),('GITHUB_WORKFLOW_REF','other'),
                          ('GITHUB_RUN_ATTEMPT','2'),('GITHUB_EVENT_NAME','workflow_dispatch')):
            with self.subTest(key=key),self.assertRaises(RuntimeError):m.site_inspect_event_identity(site_inspection_event(),env|{key:value})
        for value in (site_event(),site_event(True),update_event(),event()):
            with self.assertRaises(RuntimeError):m.site_inspect_event_identity(value,env)
        for args in (['site-inspect','/tmp/root'],['site-inspect','continue'],['--site-inspect']):
            with self.assertRaises(RuntimeError):m.command_mode(args)

    def test_failed_identity_artifact_terminal_unique_commands_and_current_blobs_are_required(self):
        cases=(('/git/ref/heads/main',lambda v:v['object'].update(sha='f'*40)),
               ('/pulls/5133',lambda v:v.update(merge_commit_sha='f'*40)),('/pulls/5133',lambda v:v['head'].update(sha=m.UPDATE_SOURCE)),
               ('/contents/v2/',lambda v:v.update(sha='0'*40)),('/actions/runs/'+m.SITE_INSPECT_FAILED['run_id'],lambda v:v.update(conclusion='success')),
               ('/actions/runs/'+m.SITE_INSPECT_FAILED['run_id'],lambda v:v.update(run_attempt=2)),
               ('/attempts/1/jobs?',lambda v:v['jobs'][0].update(id=1)),('/attempts/1/jobs?',lambda v:v['jobs'][0]['steps'][0].update(conclusion='success')),
               ('/actions/artifacts/',lambda v:v.update(expired=True)),('/actions/artifacts/',lambda v:v.update(digest='sha256:'+'0'*64)),
               ('/comments?',lambda v:v.append(v[-1]|{'id':9999})),('/comments?',lambda v:v.append(v[0]|{'id':9999})),
               ('/comments?',lambda v:v[0].update(body=v[0]['body']+' edited')),('/comments?',lambda v:v[1].update(body='unknown outcome')),
               ('/comments?',lambda v:v[1].update(author_association='MEMBER')))
        for fragment,mutate in cases:
            def change(path,value):
                if path==fragment or (fragment.endswith('/') or fragment.endswith('?')) and fragment in path:mutate(value)
                return value
            with self.subTest(fragment=fragment),self.assertRaises(RuntimeError):self.admit(change)


class SiteInspectionTransaction(unittest.TestCase):
    @contextlib.contextmanager
    def failed_attempt(self):
        helper=SiteTransaction()
        with helper.installed() as f:
            target=f.root/'data/db-v1.php';saved=target.read_bytes();target.write_bytes(b'foreign root helper')
            value=helper.execute(f);self.assertEqual(value['status'],'blocked_no_replay');self.assertEqual(value['reason'],'dependency_drift')
            target.write_bytes(saved)
            failed={**SITE_IDENTITY,'operation_id':m.SITE_OPERATION,'source_sha':m.SITE_SOURCE,'manifest_sha256':m.site_policy()['manifest'],
                    'read_only':False,'phase':'complete','ssh_started':True,'status':'blocked_no_replay',
                    'parent_update':m.site_parent_identity(),'parent_evidence':f.site_parent_value,'remote':value}
            data=(json.dumps(failed,sort_keys=True)+'\n').encode();packet=io.BytesIO()
            with zipfile.ZipFile(packet,'w') as archive:archive.writestr('evidence.json',data)
            f.inspection_failed=failed;f.inspection_raw=packet.getvalue()
            pin=m.SITE_INSPECT_FAILED|SITE_IDENTITY|{'receipt_sha256':m.sha(data),'artifact_sha256':m.sha(f.inspection_raw)}
            with patch.object(m,'SITE_INSPECT_FAILED',pin):yield f

    def execute(self,f,change=None):
        program=m.site_inspect_remote_program(SITE_INSPECTION_IDENTITY,f.inspection_failed)
        value=UpdateTransaction().run_program(f,program,change);m.valid_site_inspection_result(SITE_INSPECTION_IDENTITY,value)
        return value

    def test_actual_filesystem_match_is_readonly_with_complete_fixed_scope(self):
        with self.failed_attempt() as f:
            before=f.filesystem_snapshot();value=self.execute(f)
            self.assertEqual(value['status'],'inspection_match');self.assertTrue(value['read_only'])
            self.assertEqual(value['server_writes'],0);self.assertEqual(f.filesystem_snapshot(),before)
            self.assertFalse(f.site_private.exists())
            self.assertEqual(len(value['protected_comparisons'])+len(value['private_checks']),54)
            self.assertEqual(len(value['private_checks']),5);self.assertEqual(set(value['target_comparisons']),set(m.SITE_ORDER))
            self.assertEqual(value['checked_paths'],{'overlay':sorted((*m.ADDITIONS,m.ENTRY)),
                'site_required':sorted(m.SITE_DEPENDENCIES),'site_optional':sorted(m.SITE_OPTIONAL_DEPENDENCIES)})
            self.assertTrue(value['parent_records_stable'] and value['inventory_stable'] and value['failed_namespace_absent'])

    def test_root_helper_drift_is_observed_without_repinning_or_repair(self):
        with self.failed_attempt() as f:
            target=f.root/'data/db-v1.php';target.write_bytes(b'candidate collector helper');before=f.filesystem_snapshot()
            value=self.execute(f);self.assertEqual(value['status'],'inspection_mismatch')
            row=value['protected_comparisons']['data/db-v1.php']
            self.assertFalse(row['matches_parent_snapshot']);self.assertFalse(row['matches_compiled_policy'])
            self.assertEqual(row['observed']['sha256'],m.sha(b'candidate collector helper'))
            self.assertIn('data/db-v1.php',[r['path'] for r in value['site_mismatches']])
            self.assertTrue(all(r['matches_predecessor'] for r in value['target_comparisons'].values()))
            self.assertEqual(f.filesystem_snapshot(),before)

    def test_private_comparisons_are_flags_only_even_for_content_mode_and_presence_drift(self):
        with self.failed_attempt() as f:
            secret=b'PRIVATE NEW SECRET DO NOT EXPORT';(f.root/m.PRIVATE_CONFIG).write_bytes(secret)
            (f.root/'config.php').chmod(0o644);f.put('site_conf.php',b'private extra config')
            f.put('_preview/search3-local-candidate/config.php',b'private shadow')
            before=f.filesystem_snapshot();value=self.execute(f);encoded=json.dumps(value)
            self.assertEqual(value['status'],'inspection_mismatch');self.assertEqual(f.filesystem_snapshot(),before)
            for row in value['private_checks'].values():
                self.assertEqual(set(row),{'readable','present','matches_parent_snapshot','matches_compiled_policy'})
                self.assertTrue(all(type(flag) is bool for flag in row.values()))
            self.assertFalse(value['private_checks']['public_config']['matches_parent_snapshot'])
            self.assertTrue(value['private_checks']['public_config']['matches_compiled_policy'])
            for text in (secret.decode(),m.sha(secret),m.PRIVATE_CONFIG,'_preview/search3-local-candidate/config.php',str(f.home)):
                self.assertNotIn(text,encoded)

    def test_full_worst_case_public_metadata_fits_only_the_explicit_readonly_bound(self):
        with self.failed_attempt() as f:
            private={m.PRIVATE_CONFIG,'config.php','site_conf.php','_preview/search3-local-candidate/config.php','_preview/search3-next-candidate/config.php'}
            paths=(set(f.policy['expected'])|set(f.policy['protected'])|set(m.ABSENT_DEPENDENCIES)|set(m.SITE_ORDER)
                   |set(m.SITE_DEPENDENCIES)|set(m.SITE_OPTIONAL_DEPENDENCIES))-private
            for path in paths:f.put(path,('FOREIGN '+path).encode())
            before=f.filesystem_snapshot();value=self.execute(f)
            self.assertEqual(value['status'],'inspection_mismatch');self.assertEqual(f.filesystem_snapshot(),before)
            self.assertEqual(len(value['overlay_mismatches']),42);self.assertEqual(len(value['site_mismatches']),216)
            rows=[r['observed'] for name in ('protected_comparisons','target_comparisons') for r in value[name].values()]
            rows += [r['observed'] for name in ('overlay_mismatches','site_mismatches') for r in value[name]]
            for row in rows:row.update(size=m.MAX_BYTES,mode='7777')
            self.assertTrue(m.valid_site_inspection_result(SITE_INSPECTION_IDENTITY,value))
            length=len(json.dumps(value,sort_keys=True).encode())
            self.assertGreater(length,32768);self.assertLess(length,m.SITE_INSPECT_RECEIPT_LIMIT)
            self.assertEqual(m.SITE_INSPECT_RECEIPT_LIMIT,131072)

    def test_unsafe_public_file_is_reported_without_following_it(self):
        with self.failed_attempt() as f:
            target=f.root/'data/db-v1.php';target.unlink();target.symlink_to(f.root/'config.php')
            before=f.filesystem_snapshot();value=self.execute(f)
            self.assertEqual(value['status'],'inspection_mismatch')
            self.assertEqual(value['protected_comparisons']['data/db-v1.php']['observed'],{'state':'unreadable','reason':'unsafe_file'})
            self.assertEqual(f.filesystem_snapshot(),before)

    def test_existing_failed_namespace_or_changed_parent_proof_blocks_without_writes(self):
        for kind in ('namespace','namespace-link','parent','update'):
            with self.subTest(kind=kind),self.failed_attempt() as f:
                if kind=='namespace':f.site_private.mkdir(mode=0o700)
                elif kind=='namespace-link':f.site_private.symlink_to(f.home)
                elif kind=='parent':(f.private/'result.json').write_bytes(b'{}')
                else:(f.update_private/'result.json').write_bytes(b'{}')
                before=f.filesystem_snapshot();value=self.execute(f)
                self.assertEqual(value['status'],'inspection_blocked');self.assertEqual(f.filesystem_snapshot(),before)

    def test_public_or_parent_race_is_unknown_and_never_repaired(self):
        for kind in ('public','parent','namespace'):
            with self.subTest(kind=kind),self.failed_attempt() as f:
                def change(program):
                    line=" result['inventory_stable']=continuation_inventory(public,scope)==rows"
                    if kind=='namespace':inject=" os.mkdir(SITE_POLICY['operation'],0o700,dir_fd=base.fd)\n"
                    else:
                        store='public' if kind=='public' else 'original';path='data/db-v1.php' if kind=='public' else 'result.json'
                        inject=f" with {store}.parent({path!r}) as (fd,name):\n  leaf=os.open(name,os.O_WRONLY|os.O_TRUNC,dir_fd=fd);os.write(leaf,b'FOREIGN RACE');os.close(leaf)\n"
                    self.assertIn(line,program);return program.replace(line,inject+line,1)
                value=self.execute(f,change);self.assertEqual(value['status'],'inspection_unknown')
                if kind=='namespace':self.assertTrue(f.site_private.exists())
                else:self.assertEqual(((f.root/'data/db-v1.php') if kind=='public' else (f.private/'result.json')).read_bytes(),b'FOREIGN RACE')

    def test_mutated_failed_receipts_never_become_read_or_write_admission(self):
        with self.failed_attempt() as f:
            self.assertEqual(m.site_inspect_failed_evidence(f.inspection_raw),f.inspection_failed)
            with self.assertRaises(RuntimeError):m.site_inspect_failed_evidence(f.inspection_raw+b'changed')
            changes=(lambda v:v.update(read_only=True),lambda v:v.update(status='unknown_no_replay'),lambda v:v.update(run_id='1'),
                     lambda v:v['remote'].update(reason='site_dependency_drift'),lambda v:v['remote'].update(reservation_created=True),
                     lambda v:v['remote'].update(written_files=list(m.SITE_ORDER)),lambda v:v['remote'].update(database_reads=1),
                     lambda v:v['parent_evidence'].update(status='unknown_no_replay'))
            for change in changes:
                bad=copy.deepcopy(f.inspection_failed);change(bad)
                pin=m.SITE_INSPECT_FAILED|{'receipt_sha256':m.sha((json.dumps(bad,sort_keys=True)+'\n').encode())}
                with self.subTest(change=change),patch.object(m,'SITE_INSPECT_FAILED',pin),self.assertRaises(RuntimeError):m.site_inspect_failed_value(bad)

    def test_result_schema_rejects_missing_scope_private_metadata_and_write_claims(self):
        with self.failed_attempt() as f:
            good=self.execute(f)
            changes=(lambda v:v.update(read_only=False),lambda v:v.update(server_writes=1),lambda v:v.update(database_reads=True),
                     lambda v:v['private_checks']['public_config'].update(sha256='0'*64),
                     lambda v:v['private_checks']['public_config'].update(present='yes'),
                     lambda v:v['checked_paths']['site_required'].pop(),lambda v:v['protected_comparisons'].pop('data/db-v1.php'),
                     lambda v:v['target_comparisons'].pop(m.SITE_ORDER[0]),lambda v:v['protected_comparisons'].update({'config.php':{}}),
                     lambda v:v.update(inventory_stable=False),lambda v:v.update(scope_matches=False),
                     lambda v:v['overlay_mismatches'].append({'path':'unreviewed.php','observed':{'state':'absent'},'matches_bytes':False,'matches_inode':False}))
            for change in changes:
                value=copy.deepcopy(good);change(value)
                with self.subTest(change=change),self.assertRaises(RuntimeError):m.valid_site_inspection_result(SITE_INSPECTION_IDENTITY,value)

    def test_client_rechecks_admission_preserves_readonly_evidence_and_never_calls_write_modes(self):
        with self.failed_attempt() as f:
            (f.root/'data/db-v1.php').write_bytes(b'drift');result=self.execute(f)
            runner=f.base/'inspection-runner';runner.mkdir();event_path=runner/'event.json';event_path.write_text(json.dumps(site_inspection_event()))
            env=environment()|{'GITHUB_SHA':SITE_INSPECTION_IDENTITY['control_source_sha'],'GITHUB_RUN_ID':SITE_INSPECTION_IDENTITY['run_id'],
                 'RUNNER_TEMP':str(runner),'GITHUB_EVENT_PATH':str(event_path),'PREVIEW_HOST':'example.invalid','PREVIEW_USER':'fixture','PREVIEW_KEY':'PRIVATE KEY fixture'}
            with patch.dict(os.environ,env),patch.object(m,'site_inspect_admission') as admission,patch.object(m,'site_inspect_heads'), \
                 patch.object(m,'download',return_value=f.inspection_raw) as download, \
                 patch.object(m,'site_update',side_effect=AssertionError('mutation during inspection')), \
                 patch.object(m.carrier,'run',side_effect=[b'fixture known host',json.dumps(result).encode()]),patch('builtins.print'):
                m.site_inspect()
            self.assertEqual(admission.call_count,2);download.assert_called_once_with(m.SITE_INSPECT_FAILED['artifact_id'],m.SITE_INSPECT_FAILED['artifact_sha256'])
            evidence=runner/'search3-public-install/evidence.json';value=json.loads(evidence.read_text())
            self.assertEqual(value['status'],'inspection_mismatch');self.assertTrue(value['read_only'])
            self.assertEqual(value['failed_evidence'],f.inspection_failed);self.assertEqual(value['remote']['server_writes'],0)
            self.assertFalse((evidence.parent/'key').exists());self.assertFalse((evidence.parent/'known').exists())


class TvSuccessorTransaction(unittest.TestCase):
    """Exercise the accepted INT maintenance against real retained public proofs."""

    @contextlib.contextmanager
    def successor(self, f, installed=True):
        signing=m.tv_signing; contract=copy.deepcopy(m.TV_SUCCESSOR)
        relative=contract['relative']; target=contract['path']
        old=(f.root/target).read_bytes(); new=b'<?php /* fixed Tourvisor response and filters */\n'
        dependency='app/integrations/anex-initial-week-gate.php'
        previous=signing.tv_invalid_response_previous()
        original_files={}
        for source in [*previous,dependency]:
            path=source if source.startswith('app/') else 'api-anex-search3-preview.php'
            full='_preview/search3-anex-candidate/'+path
            if not (f.root/full).exists():f.put(full,('retained INT '+path).encode())
            original_files[path]=(f.root/full).read_bytes()
        previous={source:m.sha(original_files[source if source.startswith('app/') else 'api-anex-search3-preview.php'])
                  for source in previous}
        dependencies=signing.TV_DEPENDENCIES|{dependency:m.sha(original_files[dependency])}
        contract.update(before_sha256=m.sha(old),after_sha256=m.sha(new),after_size=len(new))
        pins=previous|{relative:m.sha(new)}
        command={key:contract[key] for key in ('source_sha','mode','operation_id')}
        basis={'parent_operation':signing.TV_RECOVERY_OPERATION,'parent_canonical_sha256':signing.TV_ENABLED_CANONICAL,
               'configuration_sha256':signing.TV_ENABLED_CONFIG_SHA,'configuration_mode':0o644,
               'receipt_and_configuration_unchanged':True}
        public={p:v.get('sha256') for p,v in signing.TV_PUBLIC.items() if p!='api-v2.php'}
        result={'schema_version':1,**command,'status':'installed','supplier_calls':0,'database_writes':0,
                'booking_calls':0,'lead_calls':0,'runtime_changed':True,'public_ui_entrypoints_unchanged':True,
                'tv_gateway':{'source_sha':contract['source_sha'],'files':pins,'activation_performed':False,
                              'configuration_changed':False,'signing_custody_changed':False},
                'diagnostic_basis_before':basis,'diagnostic_basis_after':copy.deepcopy(basis),
                'tv_gateway_public_before':signing.TV_PUBLIC,'tv_gateway_public_after':copy.deepcopy(signing.TV_PUBLIC),
                'production_before':public,'production_after':dict(public),
                'install':{'status':'installed','source_sha':contract['source_sha'],'files':12,
                           'changed_files':1,'created_files':0,'manifest_sha256':'8'*64,
                           'endpoint':{'source':'v2/api-anex-search3-preview.php','target':'api-anex-search3-preview.php',
                                       'sha256':pins['v2/api-anex-search3-preview.php'],'changed':False},
                           'dependency':{'source':dependency,'target':dependency,'sha256':dependencies[dependency],'changed':False},
                           'tv_client':{'source':relative,'target':relative,'sha256':m.sha(new),'changed':True}}}
        evidence={'status':'installed','result':result,'run_id':456780001,'command_comment_id':9601,
                  'control_source_sha':'c'*40,'job_id':456780002,'artifact_id':456780003,
                  'artifact_sha256':'9'*64,'created_at':1795266000,'started_at':1795266001,'completed_at':1795266100}
        namespace=f.home/'.anytoour-int-executor'/contract['operation_id']
        selected=[{'source':p,'target':p} for p in pins if p.startswith('app/integrations/')]
        selected += [{'source':dependency,'target':dependency},
                     {'source':'v2/api-anex-search3-preview.php','target':'api-anex-search3-preview.php'}]
        expected={row['target']:pins[row['source']] if row['source'] in pins else dependencies[row['source']] for row in selected}
        plan={'schema_version':1,'source_sha':contract['source_sha'],'files':selected,
              'changed_files':[relative],'previous':{path:{'exists':True,'sha256':m.sha(raw),'mode':0o644}
                                                   for path,raw in original_files.items()},
              'expected':expected,'status':'prepared'}
        records={'reservation.json':command|{'reserved_at':1795266002},'result.json':result,
                 'anex-preview-install-plan.json':plan,'install-state.json':result['install']}
        if installed:
            namespace.parent.mkdir(mode=0o700);namespace.mkdir(mode=0o700)
            for path,value in records.items():
                # The frozen INT installer writes the reservation with spaces;
                # result, prepared plan and final install-state are compact.
                raw=json.dumps(value,sort_keys=True) if path=='reservation.json' else json.dumps(value,sort_keys=True,separators=(',',':'))
                record=namespace/path;record.write_text(raw);record.chmod(0o600)
            (namespace/'backup').mkdir(mode=0o700)
            for path,raw in original_files.items():
                backup=namespace/'backup'/path;backup.parent.mkdir(parents=True,exist_ok=True)
                backup.write_bytes(raw);backup.chmod(0o600)
            f.put(target,new)
        f.tv_evidence=evidence if installed else {'status':'absent'}
        f.tv_namespace=namespace;f.tv_target=target;f.tv_old=old;f.tv_new=new;f.tv_plan=plan
        with contextlib.ExitStack() as stack:
            stack.enter_context(patch.dict(os.environ,{'HOME':str(f.home)}))
            stack.enter_context(patch.object(m,'TV_SUCCESSOR',contract))
            stack.enter_context(patch.object(signing,'TV_INVALID_RESPONSE_SHA',m.sha(new)))
            stack.enter_context(patch.object(signing,'tv_invalid_response_previous',return_value=previous))
            stack.enter_context(patch.object(signing,'TV_DEPENDENCIES',dependencies))
            self.assertTrue(m.tv_successor_result_valid(result))
            for name in ('rollback_remote_program','update_remote_program','update_rollback_remote_program',
                         'site_remote_program','site_rollback_remote_program'):
                original=getattr(m,name)
                def builder(*args,_original=original,**kwargs):
                    return _original(*args,**(kwargs|{'tv_successor':copy.deepcopy(f.tv_evidence)}))
                stack.enter_context(patch.object(m,name,side_effect=builder))
            yield f

    def proof_records(self,f):
        if not f.tv_namespace.exists():return {}
        return {str(path.relative_to(f.tv_namespace)):(UpdateTransaction().stamp(path),path.read_bytes())
                for path in f.tv_namespace.rglob('*') if path.is_file()}

    def assert_no_actions(self,value):
        for key in ('supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls'):
            self.assertIs(type(value[key]),int);self.assertEqual(value[key],0)

    def replace_same_bytes(self,path,indent=' '):
        # Preserve every content/mode/mtime assertion while replacing the inode.
        # This catches a resolver that only compares parsed JSON or file hashes.
        return '\n'.join(indent+line for line in (
            'race_path='+repr(str(path)),
            "race_stat=os.stat(race_path,follow_symlinks=False);race_raw=open(race_path,'rb').read()",
            "race_fd=os.open(race_path+'.race',os.O_WRONLY|os.O_CREAT|os.O_EXCL,stat.S_IMODE(race_stat.st_mode))",
            'os.write(race_fd,race_raw);os.close(race_fd)',
            "os.utime(race_path+'.race',ns=(race_stat.st_atime_ns,race_stat.st_mtime_ns))",
            "os.replace(race_path+'.race',race_path)"))+'\n'

    def restored_maintenance(self,f,count=1):
        value=copy.deepcopy(f.tv_evidence['result'])
        for key in ('install','diagnostic_basis_after','tv_gateway_public_after','production_after'):value.pop(key)
        value.update(status='rolled_back',runtime_changed=False,
                     rollback={'status':'complete','restored_files':count},
                     install_failure_class='anex_preview_install_readback_hash')
        self.assertTrue(m.tv_successor_rollback_valid(value))
        f.tv_evidence.update(status='rolled_back',result=value)
        (f.tv_namespace/'result.json').write_text(json.dumps(value,sort_keys=True,separators=(',',':')))
        state=f.tv_namespace/'install-state.json'
        if count==0:state.unlink()
        else:state.write_text(json.dumps({'status':'applying-anex-preview','source_sha':m.TV_SUCCESSOR['source_sha'],
                                         'applied':[m.TV_SUCCESSOR['relative']]},sort_keys=True,separators=(',',':')))
        f.put(f.tv_target,f.tv_old)

    def test_fixed_production_transition_does_not_repin_historical_policy(self):
        c=m.TV_SUCCESSOR
        self.assertEqual(c['path'],'_preview/search3-anex-candidate/app/integrations/tourvisor-interactive-client.php')
        self.assertEqual(c['relative'],'app/integrations/tourvisor-interactive-client.php')
        self.assertEqual(c['operation_id'],'int-anex-tv-invalid-response-install-20261119-v1')
        self.assertEqual(c['mode'],'install-tv-invalid-response')
        self.assertEqual(c['source_sha'],'3207266e17ed4e8244aaf28d05b962747fd4cd3c')
        self.assertEqual(c['before_sha256'],'72056e5c4642daa9515d2d5dccece0804b6e3dd9c935b6250e0e29ca2e39daf9')
        self.assertEqual(c['after_sha256'],'529257a04d7526a758b49368959179e697279d223306d06a640e169c35148841')
        self.assertEqual(c['after_size'],9741)
        self.assertEqual(set(c['control_blobs']),{'.github/workflows/int-server-executor.yml'}|
            {'scripts/deploy/int_server_executor'+suffix+'.py' for suffix in (
                '', '_anex_secret_transport', '_firstpage_readback', '_local_profile_acquire',
                '_local_profile_apply', '_local_profile_apply_source320', '_local_profile_metadata',
                '_local_profile_phase3_inspection', '_local_profile_plan', '_local_profile_read',
                '_local_profile_recovery_plan', '_local_stay_read', '_local_tv_capture', '_local_tv_content',
                '_local_tv_schema', '_local_tv_seed', '_match_primary', '_match_retained',
                '_passive_fuel_readiness', '_search3_signing')}|
            {'tests/int_server_executor'+suffix+'_test.py' for suffix in (
                '', '_anex_secret_transport', '_firstpage_readback', '_local_profile_acquire',
                '_local_profile_apply_source320', '_local_profile_apply', '_local_profile_mass_apply2',
                '_local_profile_mass_apply', '_local_profile_mass_plan2', '_local_profile_mass_plan3',
                '_local_profile_mass_plan', '_local_profile_metadata', '_local_profile_phase3_inspection',
                '_local_profile_plan', '_local_profile_recovery_plan', '_match_primary', '_search3_signing')}|
            {'scripts/diagnostics/int_funsun_direction_store_server_readback.py'}|
            {'scripts/diagnostics/local_profile_'+name+'_4191.php' for name in (
                'acquire','apply','apply_source320','mass_apply2','mass_apply','mass_plan2',
                'mass_plan3','mass_plan','metadata','plan')})
        for policy in (m.PROTECTED,m.PREFLIGHT_ACCEPTED_PROTECTED,m.INSTALL_PROTECTED,m.CONTINUATION_PROTECTED):
            self.assertEqual(policy[c['path']],c['before_sha256'])

    def test_absent_maintenance_accepts_only_the_existing_old_dependency(self):
        helper=UpdateTransaction()
        with helper.installed() as f,self.successor(f,False):
            proof=self.proof_records(f);records=RollbackTransaction().original_records(f)
            value=helper.execute(f);self.assertEqual(value['status'],'updated_verified')
            self.assert_no_actions(value);self.assertEqual(self.proof_records(f),proof)
            self.assertEqual(RollbackTransaction().original_records(f),records)
        for kind in ('unproved-new','third-hash','pending','wrong-mode'):
            with self.subTest(kind=kind),helper.installed() as f,self.successor(f,False):
                if kind=='pending':
                    f.tv_namespace.parent.mkdir(mode=0o700);f.tv_namespace.mkdir(mode=0o700)
                    (f.tv_namespace/'reservation.json').write_text('{}');(f.tv_namespace/'reservation.json').chmod(0o600)
                elif kind=='wrong-mode':(f.root/f.tv_target).chmod(0o600)
                else:f.put(f.tv_target,f.tv_new if kind=='unproved-new' else b'not an admitted client')
                before=f.filesystem_snapshot();value=helper.execute(f)
                self.assertEqual(value['status'],'blocked_no_replay');self.assertEqual(value['written_files'],[])
                self.assertFalse(f.update_private.exists());self.assertEqual(f.filesystem_snapshot(),before)

    def test_update_and_both_rollback_levels_keep_new_client_and_immutable_original_records(self):
        helper=UpdateTransaction()
        with helper.installed() as f,self.successor(f):
            records=RollbackTransaction().original_records(f);proof=self.proof_records(f)
            f.update_accepted=helper.execute(f)
            self.assertEqual(f.update_accepted['status'],'updated_verified');self.assert_no_actions(f.update_accepted)
            self.assertEqual(RollbackTransaction().original_records(f),records);self.assertEqual(self.proof_records(f),proof)
            updated_records={str(p.relative_to(f.update_private)):(UpdateTransaction().stamp(p),p.read_bytes())
                             for p in f.update_private.rglob('*.json')}
            value=helper.rollback(f);self.assertEqual(value['status'],'update_rolled_back_verified');self.assert_no_actions(value)
            self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new)
            self.assertEqual({str(p.relative_to(f.update_private)):(UpdateTransaction().stamp(p),p.read_bytes())
                              for p in f.update_private.rglob('*.json')},updated_records)
            value=RollbackTransaction().execute(f);self.assertEqual(value['status'],'rolled_back_verified');self.assert_no_actions(value)
            self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new);f.restored()
            self.assertEqual(RollbackTransaction().original_records(f),records);self.assertEqual(self.proof_records(f),proof)

    def test_update_created_before_maintenance_rolls_back_with_exact_successor(self):
        helper=UpdateTransaction()
        with helper.installed() as f:
            f.update_accepted=helper.execute(f);self.assertEqual(f.update_accepted['status'],'updated_verified')
            with self.successor(f):
                records=RollbackTransaction().original_records(f);proof=self.proof_records(f)
                value=helper.rollback(f);self.assertEqual(value['status'],'update_rolled_back_verified')
                self.assertEqual(RollbackTransaction().execute(f)['status'],'rolled_back_verified')
                self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new)
                self.assertEqual(RollbackTransaction().original_records(f),records);self.assertEqual(self.proof_records(f),proof)

    def test_authentic_completed_int_rollback_preserves_public_recovery_with_old_client_only(self):
        helper=UpdateTransaction()
        for count in (0,1):
            with self.subTest(restored_files=count),helper.installed() as f,self.successor(f):
                self.restored_maintenance(f,count);records=RollbackTransaction().original_records(f);proof=self.proof_records(f)
                value=RollbackTransaction().execute(f)
                self.assertEqual(value['status'],'rolled_back_verified');self.assert_no_actions(value);f.restored()
                self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_old)
                self.assertEqual(RollbackTransaction().original_records(f),records);self.assertEqual(self.proof_records(f),proof)
        for kind in ('new-client','third-hash','unknown-local-result','wrong-unchanged-helper'):
            with self.subTest(kind=kind),helper.installed() as f,self.successor(f):
                self.restored_maintenance(f)
                if kind in ('new-client','third-hash'):f.put(f.tv_target,f.tv_new if kind=='new-client' else b'foreign client')
                elif kind=='wrong-unchanged-helper':
                    f.put('_preview/search3-anex-candidate/app/integrations/anex-initial-week-gate.php',b'foreign dependency')
                else:
                    path=f.tv_namespace/'result.json';value=json.loads(path.read_text());value['status']='unknown_no_replay'
                    path.write_text(json.dumps(value,sort_keys=True,separators=(',',':')))
                before=f.filesystem_snapshot();value=RollbackTransaction().execute(f)
                self.assertEqual(value['status'],'blocked_no_replay');self.assertEqual(value['rollback_files'],[])
                self.assertEqual(f.filesystem_snapshot(),before)

    def test_site_guarded_snapshot_from_before_maintenance_accepts_only_proven_transition(self):
        helper=SiteTransaction()
        with helper.installed() as f:
            f.site_accepted=helper.execute(f);self.assertEqual(f.site_accepted['status'],'site_updated_verified')
            with self.successor(f):
                parents=helper.parent_records(f);proof=self.proof_records(f)
                records={str(p.relative_to(f.site_private)):(UpdateTransaction().stamp(p),p.read_bytes())
                         for p in f.site_private.rglob('*.json')}
                value=helper.rollback(f);self.assertEqual(value['status'],'site_rolled_back_verified');self.assert_no_actions(value)
                self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new)
                self.assertEqual(helper.parent_records(f),parents);self.assertEqual(self.proof_records(f),proof)
                self.assertEqual({str(p.relative_to(f.site_private)):(UpdateTransaction().stamp(p),p.read_bytes())
                                  for p in f.site_private.rglob('*.json')},records)
                self.assertEqual(UpdateTransaction().rollback(f)['status'],'update_rolled_back_verified')
                self.assertEqual(RollbackTransaction().execute(f)['status'],'rolled_back_verified')
                self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new)

    def test_site_update_after_maintenance_roundtrips_without_changing_int_proof(self):
        helper=SiteTransaction()
        with helper.installed() as f,self.successor(f):
            parents=helper.parent_records(f);proof=self.proof_records(f)
            f.site_accepted=helper.execute(f);self.assertEqual(f.site_accepted['status'],'site_updated_verified')
            self.assertEqual(helper.rollback(f)['status'],'site_rolled_back_verified')
            self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new)
            self.assertEqual(helper.parent_records(f),parents);self.assertEqual(self.proof_records(f),proof)

    def test_site_child_snapshot_before_maintenance_rolls_back_with_new_client_and_original_history(self):
        child=SiteContinuationTransaction();site=SiteTransaction()
        with child.inspected() as f:
            f.continuation_accepted=child.execute(f);self.assertEqual(f.continuation_accepted['status'],'site_updated_verified')
            with self.successor(f):
                parents=site.parent_records(f);proof=self.proof_records(f)
                records={str(p.relative_to(f.continuation_private)):(UpdateTransaction().stamp(p),p.read_bytes())
                         for p in f.continuation_private.rglob('*.json')}
                value=child.rollback(f);self.assertEqual(value['status'],'site_rolled_back_verified');self.assert_no_actions(value)
                self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new);self.assertFalse(f.site_private.exists())
                self.assertEqual(site.parent_records(f),parents);self.assertEqual(self.proof_records(f),proof)
                self.assertEqual({str(p.relative_to(f.continuation_private)):(UpdateTransaction().stamp(p),p.read_bytes())
                                  for p in f.continuation_private.rglob('*.json')},records)
                for value in (UpdateTransaction().rollback(f),RollbackTransaction().execute(f)):
                    self.assertIn(value['status'],('update_rolled_back_verified','rolled_back_verified'));self.assert_no_actions(value)
                self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new)
                self.assertEqual(self.proof_records(f),proof)

    def test_site_child_after_maintenance_roundtrips_and_retains_new_client_through_public_recovery(self):
        child=SiteContinuationTransaction();site=SiteTransaction()
        with child.inspected() as f,self.successor(f):
            before=site.public_files(f);parents=site.parent_records(f);proof=self.proof_records(f)
            inspected=copy.deepcopy(f.continuation_evidence);archived=f.continuation_raw
            f.continuation_accepted=child.execute(f)
            self.assertEqual(f.continuation_accepted['status'],'site_updated_verified');self.assert_no_actions(f.continuation_accepted)
            self.assertTrue(f.continuation_accepted['failed_namespace_absent'])
            self.assertEqual(site.parent_records(f),parents);self.assertEqual(self.proof_records(f),proof)
            value=child.rollback(f);self.assertEqual(value['status'],'site_rolled_back_verified');self.assert_no_actions(value)
            self.assertEqual(site.public_files(f),before);self.assertEqual(site.parent_records(f),parents)
            self.assertEqual(f.continuation_evidence,inspected);self.assertEqual(f.continuation_raw,archived)
            self.assertFalse(f.site_private.exists());self.assertEqual(self.proof_records(f),proof)
            self.assertEqual(UpdateTransaction().rollback(f)['status'],'update_rolled_back_verified')
            self.assertEqual(RollbackTransaction().execute(f)['status'],'rolled_back_verified');f.restored()
            self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new);self.assertEqual(self.proof_records(f),proof)

    def test_site_child_rechecks_tv_proof_after_activation_and_unwinds_only_owned_asset(self):
        child=SiteContinuationTransaction();site=SiteTransaction()
        with child.inspected() as f,self.successor(f):
            before=site.public_files(f);parents=site.parent_records(f);proof=self.proof_records(f)
            def change(program):
                line='  site_check_targets(current);site_check_parents()'
                injection='  if index==0:\n'+self.replace_same_bytes(f.tv_namespace/'result.json','   ')
                self.assertIn(line,program);return program.replace(line,injection+line,1)
            value=child.execute(f,change)
            self.assertEqual(value['status'],'unknown_no_replay');self.assertFalse(value['protected_unchanged'])
            self.assertEqual(value['written_files'],[m.SITE_ORDER[0]]);self.assertEqual(value['rollback_files'],[m.SITE_ORDER[0]])
            self.assert_no_actions(value);self.assertEqual(site.public_files(f),before);self.assertEqual(site.parent_records(f),parents)
            self.assertFalse(f.site_private.exists());current=self.proof_records(f)
            self.assertNotEqual(current['result.json'][0],proof['result.json'][0]);self.assertEqual(current['result.json'][1],proof['result.json'][1])
            for path,row in proof.items():
                if path!='result.json':self.assertEqual(current[path],row)
            consumed=f.filesystem_snapshot();self.assertEqual(child.execute(f)['status'],'blocked_no_replay')
            self.assertEqual(f.filesystem_snapshot(),consumed)

    def test_partial_tampered_or_downgraded_maintenance_never_reserves_public_write(self):
        helper=UpdateTransaction()
        kinds=('missing-result','unknown-result','different-result','wrong-reservation','wrong-plan','wrong-state',
               'missing-backup','wrong-backup','record-mode','record-symlink','record-hardlink','namespace-mode',
               'target-mode','target-symlink','third-hash','downgrade')
        for kind in kinds:
            with self.subTest(kind=kind),helper.installed() as f,self.successor(f):
                ns=f.tv_namespace;target=f.root/f.tv_target;result=ns/'result.json';backup=ns/'backup'/m.TV_SUCCESSOR['relative']
                if kind=='missing-result':result.unlink()
                elif kind in ('unknown-result','different-result'):
                    value=json.loads(result.read_text());value['status']='unknown_no_replay' if kind=='unknown-result' else 'rolled_back'
                    result.write_text(json.dumps(value))
                elif kind=='wrong-reservation':
                    path=ns/'reservation.json';value=json.loads(path.read_text());value['operation_id']+='-alias';path.write_text(json.dumps(value))
                elif kind=='wrong-plan':
                    path=ns/'anex-preview-install-plan.json';value=json.loads(path.read_text());value['changed_files'].append('api-anex-search3-preview.php');path.write_text(json.dumps(value))
                elif kind=='wrong-state':(ns/'install-state.json').write_text('{}')
                elif kind=='missing-backup':backup.unlink()
                elif kind=='wrong-backup':backup.write_bytes(b'not the retained predecessor')
                elif kind=='record-mode':result.chmod(0o644)
                elif kind=='record-symlink':result.rename(ns/'retained-result');result.symlink_to('retained-result')
                elif kind=='record-hardlink':os.link(result,ns/'foreign-link')
                elif kind=='namespace-mode':ns.chmod(0o755)
                elif kind=='target-mode':target.chmod(0o600)
                elif kind=='target-symlink':target.rename(target.with_suffix('.saved'));target.symlink_to(target.with_suffix('.saved').name)
                elif kind=='third-hash':target.write_bytes(b'foreign client')
                else:target.write_bytes(f.tv_old)
                before=f.filesystem_snapshot();value=helper.execute(f)
                self.assertEqual(value['status'],'blocked_no_replay');self.assertEqual(value['written_files'],[])
                self.assertFalse(f.update_private.exists());self.assertEqual(f.filesystem_snapshot(),before)

    def test_proof_or_client_inode_race_before_reservation_blocks_without_public_write(self):
        helper=UpdateTransaction()
        paths=('reservation.json','result.json','anex-preview-install-plan.json','install-state.json',
               'backup/app/integrations/tourvisor-interactive-client.php','client')
        for relative in paths:
            with self.subTest(relative=relative),helper.installed() as f,self.successor(f):
                target=f.root/f.tv_target if relative=='client' else f.tv_namespace/relative
                public=helper.public_files(f);records=RollbackTransaction().original_records(f);proof=self.proof_records(f)
                previous=target.stat();raw=target.read_bytes()
                def change(program):
                    line=" require(parent_records()==retained and public.inventory(POLICY['expected'])==before\n"
                    self.assertIn(line,program);return program.replace(line,self.replace_same_bytes(target)+line,1)
                value=helper.execute(f,change)
                self.assertEqual(value['status'],'blocked_no_replay');self.assertEqual(value['written_files'],[])
                self.assertFalse(value['reservation_created']);self.assertFalse(f.update_private.exists());self.assert_no_actions(value)
                self.assertEqual(target.read_bytes(),raw);self.assertNotEqual(target.stat().st_ino,previous.st_ino)
                self.assertEqual(target.stat().st_mtime_ns,previous.st_mtime_ns)
                current=helper.public_files(f)
                for path,row in public.items():
                    if relative!='client' or path!=f.tv_target:self.assertEqual(current[path],row)
                self.assertEqual(RollbackTransaction().original_records(f),records)
                for path,row in proof.items():
                    if path!=relative:self.assertEqual(self.proof_records(f)[path],row)

    def test_postwrite_proof_race_unwinds_only_public_asset_and_never_claims_verified(self):
        helper=UpdateTransaction()
        for relative in ('result.json','client'):
            with self.subTest(relative=relative),helper.installed() as f,self.successor(f):
                target=f.root/f.tv_target if relative=='client' else f.tv_namespace/relative
                public=helper.public_files(f);records=RollbackTransaction().original_records(f)
                def change(program):
                    line=' candidate=parent_candidate(retained,True)'
                    self.assertIn(line,program);return program.replace(line,self.replace_same_bytes(target)+line,1)
                value=helper.execute(f,change)
                self.assertEqual(value['status'],'unknown_no_replay');self.assertFalse(value['protected_unchanged'])
                self.assertEqual(value['written_files'],[m.UPDATE_PATH]);self.assertEqual(value['rollback_files'],[m.UPDATE_PATH])
                self.assert_no_actions(value)
                current=helper.public_files(f)
                for path,row in public.items():
                    if relative!='client' or path!=f.tv_target:self.assertEqual(current[path],row)
                self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new)
                self.assertEqual(RollbackTransaction().original_records(f),records)
                consumed=f.filesystem_snapshot();self.assertEqual(helper.execute(f)['status'],'blocked_no_replay')
                self.assertEqual(f.filesystem_snapshot(),consumed)

    def test_site_proof_race_after_second_exchange_restores_owned_assets_and_preserves_dependency(self):
        helper=SiteTransaction()
        with helper.installed() as f,self.successor(f):
            public=helper.public_files(f);records=helper.parent_records(f);proof=self.proof_records(f)
            def change(program):
                line='  site_check_targets(current);site_check_parents()'
                injection='  if index==1:\n'+self.replace_same_bytes(f.tv_namespace/'install-state.json','   ')
                self.assertIn(line,program);return program.replace(line,injection+line,1)
            value=helper.execute(f,change)
            self.assertEqual(value['status'],'unknown_no_replay');self.assertFalse(value['protected_unchanged'])
            self.assertEqual(value['written_files'],list(m.SITE_ORDER[:2]))
            self.assertEqual(value['rollback_files'],list(reversed(m.SITE_ORDER[:2])))
            self.assertEqual(helper.public_files(f),public);self.assertEqual(helper.parent_records(f),records)
            self.assert_no_actions(value)
            current=self.proof_records(f)
            self.assertNotEqual(current['install-state.json'][0],proof['install-state.json'][0])
            self.assertEqual(current['install-state.json'][1],proof['install-state.json'][1])
            for path,row in proof.items():
                if path!='install-state.json':self.assertEqual(current[path],row)

    def test_both_public_rollback_levels_recheck_proof_after_restoration(self):
        helper=UpdateTransaction()
        for level in ('update','original'):
            with self.subTest(level=level),helper.installed() as f,self.successor(f):
                if level=='update':
                    f.update_accepted=helper.execute(f);self.assertEqual(f.update_accepted['status'],'updated_verified')
                records=RollbackTransaction().original_records(f);proof=self.proof_records(f)
                def change(program):
                    if level=='update':
                        line=" restore_capture(public,updated,path,'stage/app');result['rollback_files']=[path]\n"
                    else:line=" result['rollback_files'].append(entry);result['phase']='additions'\n"
                    self.assertIn(line,program);return program.replace(line,line+self.replace_same_bytes(f.tv_namespace/'result.json'),1)
                value=helper.rollback(f,change) if level=='update' else RollbackTransaction().execute(f,change)
                self.assertEqual(value['status'],'unknown_no_replay');self.assertFalse(value['protected_unchanged'])
                self.assertEqual(value['rollback_files'],[m.UPDATE_PATH if level=='update' else m.ENTRY]);self.assert_no_actions(value)
                self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new)
                self.assertEqual(RollbackTransaction().original_records(f),records)
                current=self.proof_records(f)
                self.assertEqual(current['result.json'][1],proof['result.json'][1])
                self.assertNotEqual(current['result.json'][0],proof['result.json'][0])

    def test_site_rollback_rechecks_current_dependency_before_restoring_next_asset(self):
        helper=SiteTransaction()
        with helper.installed() as f,self.successor(f):
            f.site_accepted=helper.execute(f);self.assertEqual(f.site_accepted['status'],'site_updated_verified')
            records=helper.parent_records(f);proof=self.proof_records(f)
            def change(program):
                line="  result['rollback_files'].append(path)\n"
                self.assertIn(line,program);return program.replace(line,line+self.replace_same_bytes(f.root/f.tv_target,'  '),1)
            value=helper.rollback(f,change)
            self.assertEqual(value['status'],'unknown_no_replay');self.assertFalse(value['protected_unchanged'])
            self.assertEqual(value['rollback_files'],[m.SITE_ORDER[-1]]);self.assert_no_actions(value)
            for index,path in enumerate(m.SITE_ORDER):
                row=m.SITE_TARGETS[path]['before' if index==3 else 'after']
                self.assertEqual(m.sha((f.root/path).read_bytes()),row['sha256'])
            self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new)
            self.assertEqual(helper.parent_records(f),records);self.assertEqual(self.proof_records(f),proof)


class TvSuccessorAdmission(unittest.TestCase):
    @contextlib.contextmanager
    def admitted(self, mutate=None):
        with UpdateTransaction().installed() as f,TvSuccessorTransaction().successor(f):
            command={'id':9601,'user':{'id':226193297},'author_association':'OWNER',
                     'body':'/run-int-server-v1 '+' '.join(m.TV_SUCCESSOR[k] for k in ('source_sha','mode','operation_id')),
                     'created_at':'2026-11-21T13:00:00Z','updated_at':'2026-11-21T13:00:00Z'}
            run={'id':456780001,'path':'.github/workflows/int-server-executor.yml','head_branch':'main',
                 'event':'issue_comment','head_sha':'c'*40,'run_attempt':1,'status':'completed','conclusion':'success',
                 'actor':{'login':'pyatkoff'},'triggering_actor':{'login':'pyatkoff'},
                 'created_at':'2026-11-21T13:00:01Z','run_started_at':'2026-11-21T13:00:02Z','updated_at':'2026-11-21T13:01:00Z'}
            job={'id':456780002,'run_id':run['id'],'name':'execute','status':'completed','conclusion':'success',
                 'steps':[{'name':'Execute bounded private INT operation, install, or DB/LOCAL readback',
                           'status':'completed','conclusion':'success','started_at':'2026-11-21T13:00:03Z',
                           'completed_at':'2026-11-21T13:00:50Z'}]}
            value=copy.deepcopy(f.tv_evidence['result'])
            packet=io.BytesIO()
            with zipfile.ZipFile(packet,'w') as archive:archive.writestr('result.json',json.dumps(value,sort_keys=True,indent=2)+'\n')
            raw=packet.getvalue()
            artifact={'id':456780003,'name':'int-server-executor-'+str(run['id'])+'-1','expired':False,
                      'workflow_run':{'id':run['id']},'digest':'sha256:'+m.sha(raw)}
            data={'commands':[command],'runs':[run],'artifacts':[artifact],'jobs':[job],
                  'blobs':copy.deepcopy(m.TV_SUCCESSOR['control_blobs']),'raw':raw,'value':value}
            if mutate:mutate(data)
            def pages(path,key=None):
                if path=='/issues/4709/comments':return copy.deepcopy(data['commands'])
                if path.startswith('/actions/workflows/int-server-executor.yml/runs?'):
                    self.assertIn('event=issue_comment',path);self.assertIn('branch=main',path)
                    self.assertIn('2026-11-21T13%3A00%3A00Z..2026-11-21T13%3A05%3A00Z',path)
                    return copy.deepcopy(data['runs'])
                if path=='/actions/runs/'+str(run['id'])+'/artifacts':return copy.deepcopy(data['artifacts'])
                if path=='/actions/runs/'+str(run['id'])+'/attempts/1/jobs':return copy.deepcopy(data['jobs'])
                raise AssertionError('Unrelated successor lookup: '+path)
            def api(path):
                if path=='/contents/scripts/deploy?ref='+run['head_sha']:
                    return [{'type':'file','path':source,'sha':blob} for source,blob in data['blobs'].items()
                            if source.startswith('scripts/deploy/')]
                for source,blob in data['blobs'].items():
                    if path=='/contents/'+source+'?ref='+run['head_sha']:return {'type':'file','sha':blob}
                raise AssertionError('Unrelated successor provenance lookup: '+path)
            def download(identity,digest):
                m.need(identity==artifact['id'] and digest==m.sha(data['raw']),'tv_successor_test_artifact_digest')
                return data['raw']
            with patch.object(m,'TV_SUCCESSOR_RETAINED',None), \
                 patch.object(m,'pages',side_effect=pages),patch.object(m,'api',side_effect=api), \
                 patch.object(m,'download',side_effect=download), \
                 patch.object(m.carrier,'run',side_effect=AssertionError('admission must not contact the server')):
                yield data

    def test_absent_command_is_explicit_old_state_without_source_or_artifact_scan(self):
        with patch.object(m,'TV_SUCCESSOR_RETAINED',None),patch.object(m,'pages',return_value=[]) as pages, \
             patch.object(m,'api',side_effect=AssertionError('unnecessary source lookup')), \
             patch.object(m,'download',side_effect=AssertionError('unnecessary artifact lookup')):
            self.assertEqual(m.tv_successor_evidence(),{'status':'absent'})
            pages.assert_called_once_with('/issues/4709/comments')

    def test_unique_successful_original_operation_yields_bound_runtime_proof(self):
        with self.admitted() as data:
            value=m.tv_successor_evidence()
            self.assertEqual(value,{'status':'installed','result':data['value'],'run_id':456780001,
                                   'command_comment_id':9601,'control_source_sha':'c'*40,'job_id':456780002,
                                   'artifact_id':456780003,'artifact_sha256':m.sha(data['raw']),
                                   'created_at':m._tv_time('2026-11-21T13:00:00Z'),
                                   'started_at':m._tv_time('2026-11-21T13:00:03Z'),
                                   'completed_at':m._tv_time('2026-11-21T13:00:50Z')})
            self.assertIn('TV_SUCCESSOR_PROOF=',m.tv_successor_prefix(value))

    def test_every_loaded_control_blob_and_local_validator_must_be_authentic(self):
        for source in m.TV_SUCCESSOR['control_blobs']:
            with self.subTest(source=source),self.admitted(lambda d:d['blobs'].update({source:'0'*40})),self.assertRaises(RuntimeError):
                m.tv_successor_evidence()
        with tempfile.TemporaryDirectory() as directory:
            changed=Path(directory)/'signing.py';changed.write_bytes(Path(m.tv_signing.__file__).read_bytes()+b'\n# unreviewed source\n')
            with patch.object(m.tv_signing,'__file__',str(changed)), \
                 patch.object(m,'pages',side_effect=AssertionError('must reject local validator before metadata')):
                with self.assertRaisesRegex(RuntimeError,'tv_successor_validator_source'):m.tv_successor_evidence()
                with self.assertRaisesRegex(RuntimeError,'tv_successor_validator_source'):m.tv_successor_prefix({'status':'absent'})

    def test_authentic_failed_original_can_prove_complete_restoration_but_unknown_never_can(self):
        def restored(data,change=None):
            value=copy.deepcopy(data['value'])
            for key in ('install','diagnostic_basis_after','tv_gateway_public_after','production_after'):value.pop(key)
            value.update(status='rolled_back',runtime_changed=False,rollback={'status':'complete','restored_files':1},
                         install_failure_class='anex_preview_install_readback_hash')
            if change:change(value)
            data['value']=value;packet=io.BytesIO()
            with zipfile.ZipFile(packet,'w') as archive:archive.writestr('result.json',json.dumps(value,sort_keys=True,indent=2)+'\n')
            data['raw']=packet.getvalue();data['artifacts'][0]['digest']='sha256:'+m.sha(data['raw'])
            data['runs'][0]['conclusion']=data['jobs'][0]['conclusion']=data['jobs'][0]['steps'][0]['conclusion']='failure'
        with self.admitted(restored) as data:
            proof=m.tv_successor_evidence();self.assertEqual(proof['status'],'rolled_back')
            self.assertEqual(proof['result'],data['value']);self.assertIn('TV_SUCCESSOR_PROOF=',m.tv_successor_prefix(proof))
        for mutate in (lambda v:v.update(status='unknown_no_replay'),lambda v:v.update(runtime_changed=True),
                       lambda v:v.update(supplier_calls=1),lambda v:v.update(reason='unknown'),
                       lambda v:v['rollback'].update(status='failed'),lambda v:v['rollback'].update(restored_files=2),
                       lambda v:v['rollback'].update(restored_files=True),lambda v:v.update(production_after={})):
            with self.subTest(mutate=mutate),self.admitted(lambda d:restored(d,mutate)),self.assertRaises(RuntimeError):
                m.tv_successor_evidence()
        for item in ('run','job','step'):
            with self.subTest(item=item),self.admitted(restored) as data:
                row=data['runs'][0] if item=='run' else data['jobs'][0] if item=='job' else data['jobs'][0]['steps'][0]
                row['conclusion']='success'
                with self.assertRaises(RuntimeError):m.tv_successor_evidence()

    def test_artifact_digest_duplicate_json_and_caller_supplied_proof_cannot_bypass_identity(self):
        for kind in ('digest','duplicate-json'):
            with self.subTest(kind=kind),self.admitted() as data:
                if kind=='digest':data['artifacts'][0]['digest']='sha256:'+'0'*64
                else:
                    packet=io.BytesIO();raw=json.dumps(data['value'])
                    with zipfile.ZipFile(packet,'w') as archive:
                        archive.writestr('result.json','{"operation_id":"foreign",'+raw[1:])
                    data['raw']=packet.getvalue();data['artifacts'][0]['digest']='sha256:'+m.sha(data['raw'])
                with self.assertRaises(RuntimeError):m.tv_successor_evidence()
        with self.admitted():
            proof=m.tv_successor_evidence()
            for change in (lambda v:v.update(run_id=True),lambda v:v.update(command_comment_id=0),
                           lambda v:v.update(artifact_id=-1),lambda v:v.update(artifact_sha256='0'*63),
                           lambda v:v.update(control_source_sha='main'),lambda v:v.update(started_at=v['completed_at']+1),
                           lambda v:v.update(unreviewed=True),lambda v:v['result'].update(source_sha='0'*40)):
                value=copy.deepcopy(proof);change(value)
                with self.subTest(change=change),self.assertRaises(RuntimeError):m.tv_successor_prefix(value)
            with self.assertRaises(RuntimeError):m.tv_successor_prefix({'status':'absent','operation_id':m.TV_SUCCESSOR['operation_id']})

    def test_duplicate_edited_or_unowned_command_cannot_authenticate_maintenance(self):
        changes=(lambda d:d['commands'].append(copy.deepcopy(d['commands'][0])|{'id':9602}),
                 lambda d:d['commands'][0]['user'].update(id=1),
                 lambda d:d['commands'][0].update(author_association='MEMBER'),
                 lambda d:d['commands'][0].update(updated_at='2026-11-21T13:00:01Z'),
                 lambda d:d['commands'][0].update(body=d['commands'][0]['body']+'\n'),
                 lambda d:d['commands'][0].update(body=d['commands'][0]['body'].replace(m.TV_SUCCESSOR['source_sha'],'0'*40)))
        for index,change in enumerate(changes):
            with self.subTest(index=index),self.admitted(change),self.assertRaises(RuntimeError):m.tv_successor_evidence()

    def test_pending_retry_foreign_control_or_failed_original_is_never_old_ready(self):
        changes=(lambda d:d.update(runs=[]),
                 lambda d:d.update(artifacts=[]),
                 lambda d:d['runs'][0].update(status='in_progress',conclusion=None),
                 lambda d:d['runs'][0].update(run_attempt=2),
                 lambda d:d['runs'][0].update(run_attempt=True),
                 lambda d:d['runs'][0].update(conclusion='failure'),
                 lambda d:d['runs'][0]['actor'].update(login='unrelated'),
                 lambda d:d['runs'][0]['triggering_actor'].update(login='unrelated'),
                 lambda d:d['runs'][0].update(event='workflow_dispatch'),
                 lambda d:d['runs'][0].update(head_branch='branch'),
                 lambda d:d['blobs'].update({'scripts/deploy/int_server_executor_search3_signing.py':'0'*40}),
                 lambda d:d['artifacts'][0].update(expired=True),
                 lambda d:d['artifacts'][0].update(name='int-server-executor-456780001-2'),
                 lambda d:d['artifacts'][0]['workflow_run'].update(id=1),
                 lambda d:d['jobs'][0].update(conclusion='failure'),
                 lambda d:d['jobs'][0].update(run_id=1),
                 lambda d:d['jobs'][0]['steps'][0].update(conclusion='failure'),
                 lambda d:d['jobs'][0]['steps'][0].update(started_at='2026-11-21T12:59:59Z'),
                 lambda d:d['runs'][0].update(created_at='2026-11-21T12:59:59Z'),
                 lambda d:d['jobs'][0]['steps'][0].update(completed_at='2026-11-21T13:01:01Z'),
                 lambda d:d['runs'].append(copy.deepcopy(d['runs'][0])))
        for index,change in enumerate(changes):
            with self.subTest(index=index),self.admitted(change),self.assertRaises(RuntimeError):m.tv_successor_evidence()

    def test_original_artifact_must_be_single_successful_exact_tuple(self):
        def archive_change(change,extra=False):
            def mutate(data):
                value=copy.deepcopy(data['value']);change(value);stream=io.BytesIO()
                with zipfile.ZipFile(stream,'w') as archive:
                    archive.writestr('result.json',json.dumps(value))
                    if extra:archive.writestr('other.json','{}')
                data['raw']=stream.getvalue();data['artifacts'][0]['digest']='sha256:'+m.sha(data['raw'])
            return mutate
        changes=(lambda v:v.update(operation_id='another-operation'),lambda v:v.update(source_sha='0'*40),
                 lambda v:v.update(status='unknown_no_replay'),lambda v:v.update(schema_version=True),
                 lambda v:v.update(supplier_calls=True),lambda v:v.update(reason='unknown'),
                 lambda v:v.update(rollback={'status':'complete'}),
                 lambda v:v['install'].update(changed_files=2),lambda v:v['install'].update(created_files=1),
                 lambda v:v['install']['tv_client'].update(sha256='0'*64),
                 lambda v:v['install']['endpoint'].update(changed=True),
                 lambda v:v['diagnostic_basis_after'].update(receipt_and_configuration_unchanged=False))
        for index,change in enumerate(changes):
            with self.subTest(index=index),self.admitted(archive_change(change)),self.assertRaises(RuntimeError):m.tv_successor_evidence()
        with self.admitted(archive_change(lambda v:None,True)),self.assertRaises(RuntimeError):m.tv_successor_evidence()

    def test_command_change_during_proof_collection_invalidates_admission(self):
        with self.admitted() as data:
            original=m._tv_commands;calls=[]
            def commands():
                rows=original();calls.append(1)
                if len(calls)>1:rows[0]['id']+=1
                return rows
            with patch.object(m,'_tv_commands',side_effect=commands),self.assertRaises(RuntimeError):m.tv_successor_evidence()
            self.assertEqual(len(calls),2)

    def test_every_live_public_maintenance_path_requires_resolver_before_ssh_and_rechecks_it(self):
        cases=((m.update,{},update_event(),UPDATE_IDENTITY,'update_remote_program'),
               (m.update,{'rollback':True},update_rollback_event(),UPDATE_ROLLBACK_IDENTITY,'update_rollback_remote_program'),
               (m.site_update,{},site_event(),SITE_IDENTITY,'site_remote_program'),
               (m.site_update,{'rollback':True},site_event(True),SITE_ROLLBACK_IDENTITY,'site_rollback_remote_program'),
               (m.site_update,{'continuation':True},site_continuation_event(),SITE_CONTINUATION_IDENTITY,'site_remote_program'),
               (m.site_update,{'rollback':True,'continuation':True},site_continuation_event(True),SITE_CONTINUATION_ROLLBACK_IDENTITY,'site_rollback_remote_program'),
               (m.rollback,{},rollback_event(),ROLLBACK_IDENTITY,'rollback_remote_program'))
        artifact={'id':777,'digest':'sha256:'+'7'*64}
        for call,options,event_value,identity,builder in cases:
            for stage in ('initial','recheck'):
                with self.subTest(path=builder,options=options,stage=stage),tempfile.TemporaryDirectory() as directory,contextlib.ExitStack() as stack:
                    event_path=Path(directory)/'event.json';event_path.write_text(json.dumps(event_value))
                    env=environment()|{'GITHUB_SHA':identity['control_source_sha'],'GITHUB_RUN_ID':identity['run_id'],
                        'RUNNER_TEMP':directory,'GITHUB_EVENT_PATH':str(event_path),'PREVIEW_HOST':'example.invalid',
                        'PREVIEW_USER':'fixture','PREVIEW_KEY':'PRIVATE KEY fixture'}
                    stack.enter_context(patch.dict(os.environ,env));stack.enter_context(patch('builtins.print'))
                    values={'rollback_admission':(IDENTITY,artifact),'update_rollback_admission':(UPDATE_IDENTITY,artifact),
                            'site_rollback_admission':(SITE_IDENTITY,artifact),'download':b'inert source artifact',
                            'package':({'expected':{},'release':RELEASE},{}),'rollback_source_evidence':{},
                            'update_package':({'release':RELEASE},{}),'update_parent_evidence':{},
                            'update_source_evidence':({'release':RELEASE},{},{}),'site_package':{},
                            'site_parent_evidence':{},'site_source_evidence':(m.site_policy(),{},{}),
                            'site_continuation_inspection_evidence':{},'site_continuation_inspection_value':{},
                            'site_continuation_collector_log':{}}
                    for name,value in values.items():stack.enter_context(patch.object(m,name,return_value=value))
                    for name in ('rollback_main','rollback_commands','update_admission','update_rollback_commands',
                                 'site_admission','site_rollback_commands'):
                        stack.enter_context(patch.object(m,name))
                    stack.enter_context(patch.object(m,'api',side_effect=AssertionError('unrelated network lookup')))
                    program=stack.enter_context(patch.object(m,builder,return_value='# inert generated program'))
                    sequence=([{'status':'absent'}] if stage=='recheck' else [])+[RuntimeError('tv_successor_original_missing')]
                    resolver=stack.enter_context(patch.object(m,'tv_successor_evidence',side_effect=sequence))
                    carrier=stack.enter_context(patch.object(m.carrier,'run',return_value=b'fixture known host'))
                    with self.assertRaises(RuntimeError):call(**options)
                    self.assertEqual(resolver.call_count,1 if stage=='initial' else 2)
                    self.assertEqual([c.args[0][0] for c in carrier.call_args_list],[] if stage=='initial' else ['ssh-keyscan'])
                    if stage=='initial':program.assert_not_called()
                    else:
                        self.assertEqual(program.call_count,1)
                        self.assertEqual(program.call_args.kwargs['tv_successor'],{'status':'absent'})
                    evidence=json.loads((Path(directory)/'search3-public-install/evidence.json').read_text())
                    self.assertIs(evidence['ssh_started'],False);self.assertEqual(evidence['status'],'blocked_no_replay')
                    self.assertFalse((Path(directory)/'search3-public-install/key').exists())
                    self.assertFalse((Path(directory)/'search3-public-install/known').exists())


SITE_CONTINUATION_IDENTITY={'control_source_sha':CONTROL,'run_id':'901234','command_comment_id':9009}
SITE_CONTINUATION_ROLLBACK_IDENTITY={'control_source_sha':'d'*40,'run_id':'912345','command_comment_id':9010}


def site_continuation_event(rollback=False):
    value=event();identity=SITE_CONTINUATION_ROLLBACK_IDENTITY if rollback else SITE_CONTINUATION_IDENTITY
    body=(f'{m.SITE_CONTINUATION_ROLLBACK_PREFIX} {identity["control_source_sha"]} {SITE_CONTINUATION_IDENTITY["run_id"]} {m.SITE_CONTINUATION_ROLLBACK_OPERATION}'
          if rollback else f'{m.SITE_CONTINUATION_PREFIX} {identity["control_source_sha"]} {m.SITE_CONTINUATION_OPERATION}')
    value['comment'].update(id=identity['command_comment_id'],body=body)
    return value


def site_continuation_output():
    pin=m.SITE_CONTINUATION_COLLECTOR
    return ('2026-11-21T13:10:20.1234567Z ANYTOUR_LOCAL_PRICE_READBACK_OK db='+pin['db_sha256']+' observer='+pin['observer_sha256']+'\n').encode()


class SiteContinuationAdmission(unittest.TestCase):
    def metadata(self,path):
        if path=='/pulls/'+str(m.SITE_SOURCE_PR):
            value=SiteAdmission().metadata(path);value['merge_commit_sha']=m.SITE_INSPECT_FAILED['control_source_sha'];return value
        for pin,outcome,mode in ((m.SITE_INSPECT_FAILED,'failure','site-update'),(m.SITE_CONTINUATION_INSPECTION,'success','site-inspect')):
            if path=='/actions/runs/'+pin['run_id']:
                return {'id':int(pin['run_id']),'head_sha':pin['control_source_sha'],'head_branch':'main','path':m.WORKFLOW,
                        'event':'issue_comment','run_attempt':1,'status':'completed','conclusion':outcome,
                        'actor':{'login':'pyatkoff'},'triggering_actor':{'login':'pyatkoff'}}
            if path.startswith('/actions/runs/'+pin['run_id']+'/attempts/1/jobs?'):
                return {'total_count':1,'jobs':[{'id':pin['job_id'],'run_id':int(pin['run_id']),'name':'public-install',
                    'status':'completed','conclusion':outcome,'steps':[{'name':'Run python3 scripts/deploy/search3_public_install.py '+mode,
                                                                     'status':'completed','conclusion':outcome}]}]}
            if path=='/actions/artifacts/'+str(pin['artifact_id']):
                return {'id':pin['artifact_id'],'name':'search3-public-install-'+pin['run_id']+'-1','expired':False,
                        'workflow_run':{'id':int(pin['run_id'])},'digest':'sha256:'+pin['artifact_sha256']}
        pin=m.SITE_CONTINUATION_COLLECTOR
        if path=='/actions/runs/'+pin['run_id']:
            return {'id':int(pin['run_id']),'head_sha':pin['source_sha'],'head_branch':'main','path':pin['workflow'],
                    'event':'workflow_dispatch','run_attempt':1,'status':'completed','conclusion':'success',
                    'actor':{'login':'pyatkoff'},'triggering_actor':{'login':'pyatkoff'}}
        if path.startswith('/actions/runs/'+pin['run_id']+'/attempts/1/jobs?'):
            return {'total_count':1,'jobs':[{'id':pin['job_id'],'run_id':int(pin['run_id']),'name':'collect-production',
                'status':'completed','conclusion':'success','steps':[{'name':'Collect due TOP500 months and refresh production aggregates',
                                                                   'status':'completed','conclusion':'success'}]}]}
        if path.startswith('/issues/4709/comments?'):
            rows=SiteAdmission().metadata(path)[:1];owner={'user':{'id':226193297},'author_association':'OWNER'}
            for pin,prefix,operation,tokens in (
                (m.SITE_INSPECT_FAILED,m.SITE_PREFIX,m.SITE_OPERATION,'blocked_no_replay dependency_drift NO_REPLAY'),
                (m.SITE_CONTINUATION_INSPECTION,m.SITE_INSPECT_PREFIX,m.SITE_INSPECT_OPERATION,'inspection_mismatch data/db-v1.php')):
                rows.extend([owner|{'id':pin['command_comment_id'],'body':f'{prefix} {pin["control_source_sha"]} {operation}'},
                    owner|{'id':pin['terminal_comment_id'],'body':' '.join(str(pin[k]) for k in
                        ('run_id','control_source_sha','command_comment_id','job_id','artifact_id','artifact_sha256','receipt_sha256'))+' '+tokens}])
            return rows+[site_continuation_event()['comment']]
        return SiteAdmission().metadata(path)

    def admit(self,change=None):
        def api(path):
            value=self.metadata(path);return change(path,value) if change else value
        pin=m.SITE_CONTINUATION_COLLECTOR|{'log_sha256':m.sha(site_continuation_output())}
        with patch.object(m,'api',side_effect=api),patch.object(m,'reviewed_control') as reviewed, \
             patch.object(m,'SITE_CONTINUATION_COLLECTOR',pin),patch.object(m,'SITE_PUBLISHER_GUARD_SHA',m.sha(b'manual-only guarded publisher')):
            m.site_admission(SITE_CONTINUATION_IDENTITY,continuation=True)
            reviewed.assert_called_once_with(CONTROL)

    def test_fixed_child_commands_keep_original_owner_event_and_attempt_gate(self):
        for rollback,identity in ((False,SITE_CONTINUATION_IDENTITY),(True,SITE_CONTINUATION_ROLLBACK_IDENTITY)):
            env=environment()|{'GITHUB_SHA':identity['control_source_sha'],'GITHUB_RUN_ID':identity['run_id']}
            expected=(identity,SITE_CONTINUATION_IDENTITY['run_id']) if rollback else identity
            self.assertEqual(m.site_event_identity(site_continuation_event(rollback),env,rollback,True),expected)
            for key,value in (('GITHUB_ACTOR','other'),('GITHUB_REF','refs/heads/topic'),('GITHUB_RUN_ATTEMPT','2'),('GITHUB_EVENT_NAME','workflow_dispatch')):
                with self.subTest(rollback=rollback,key=key),self.assertRaises(RuntimeError):
                    m.site_event_identity(site_continuation_event(rollback),env|{key:value},rollback,True)
            for old in (site_event(),site_event(True),site_inspection_event(),update_event(),continuation_event()):
                with self.assertRaises(RuntimeError):m.site_event_identity(old,env,rollback,True)
            for mutate in (lambda e:e.update(action='edited'),lambda e:e['comment'].update(body=e['comment']['body']+' extra'),
                           lambda e:e['comment'].update(author_association='MEMBER'),lambda e:e['issue'].update(pull_request={})):
                value=site_continuation_event(rollback);mutate(value)
                with self.assertRaises(RuntimeError):m.site_event_identity(value,env,rollback,True)
        for mode in ('site-continue','site-continue-rollback'):
            self.assertEqual(m.command_mode([mode]),mode)
            with self.assertRaises(RuntimeError):m.command_mode([mode,'arbitrary'])

    def test_exact_failed_inspection_collector_and_unique_command_required(self):
        self.admit()
        pins=(m.SITE_INSPECT_FAILED,m.SITE_CONTINUATION_INSPECTION,m.SITE_CONTINUATION_COLLECTOR)
        for pin in pins:
            endpoint='/actions/runs/'+pin['run_id']
            for mutation in (lambda v:v.update(status='in_progress'),lambda v:v.update(run_attempt=2),
                             lambda v:v.update(head_sha='f'*40),lambda v:v['triggering_actor'].update(login='other')):
                def change(path,value):
                    if path==endpoint:mutation(value)
                    return value
                with self.subTest(run=pin['run_id'],mutation=mutation),self.assertRaises(RuntimeError):self.admit(change)
            for mutation in (lambda v:v['jobs'][0].update(id=1),lambda v:v['jobs'][0]['steps'][0].update(status='in_progress')):
                def change(path,value):
                    if path.startswith(endpoint+'/attempts/1/jobs?'):mutation(value)
                    return value
                with self.subTest(run=pin['run_id'],jobmutation=mutation),self.assertRaises(RuntimeError):self.admit(change)
        for index in (1,3,5):
            def duplicate(path,value):
                if path.startswith('/issues/4709/comments?'):value.append(value[index]|{'id':99999})
                return value
            with self.subTest(index=index),self.assertRaises(RuntimeError):self.admit(duplicate)
        def missing_terminal(path,value):
            if path.startswith('/issues/4709/comments?'):value[4]['body']='another inspection'
            return value
        with self.assertRaises(RuntimeError):self.admit(missing_terminal)
        for endpoint,mutation in (('/pulls/5133',lambda v:v.update(merge_commit_sha=CONTROL)),
            ('/actions/runs/'+m.SITE_CONTINUATION_COLLECTOR['run_id'],lambda v:v.update(conclusion='failure')),
            ('/actions/artifacts/'+str(m.SITE_CONTINUATION_INSPECTION['artifact_id']),lambda v:v.update(digest='sha256:'+'0'*64))):
            def change(path,value):
                if path==endpoint:mutation(value)
                return value
            with self.subTest(endpoint=endpoint),self.assertRaises(RuntimeError):self.admit(change)

    def test_unaccepted_collector_never_opens_admission(self):
        with patch.object(m,'SITE_CONTINUATION_COLLECTOR',m.SITE_CONTINUATION_COLLECTOR|{'log_sha256':None}), \
             patch.object(m,'api') as api,self.assertRaisesRegex(RuntimeError,'site_collector_unaccepted'):
            m.site_continuation_collector_admission()
        api.assert_not_called()

    def test_archived_output_rejects_echoes_duplicates_drift_and_wrong_digest(self):
        good=site_continuation_output()
        raw=b'\xef\xbb\xbf2026-11-21T13:00:00Z starting\n'+good+b'2026-11-21T13:11:00Z finished\n'
        with patch.object(m,'SITE_CONTINUATION_COLLECTOR',m.SITE_CONTINUATION_COLLECTOR|{'log_sha256':m.sha(raw)}):
            self.assertTrue(m.site_continuation_collector_evidence(raw)['installed_output_verified'])
            with self.assertRaises(RuntimeError):m.site_continuation_collector_evidence(raw+b'changed')
        echoed=good.replace(b'Z ANYTOUR_',b'Z printf "%s\\n" "ANYTOUR_')
        for bad in (echoed,good+good,good+good.replace(b' db=',b' db=bad'),good.replace(b'READBACK_OK',b'READBACK_FAILED'),
                    good.replace(b'Z ANYTOUR_',b'Z prefix ANYTOUR_'),good.replace(m.SITE_CONTINUATION_COLLECTOR['db_sha256'].encode(),b'0'*64)):
            with self.subTest(bad=bad[:90]),patch.object(m,'SITE_CONTINUATION_COLLECTOR',m.SITE_CONTINUATION_COLLECTOR|{'log_sha256':m.sha(bad)}),self.assertRaises(RuntimeError):
                m.site_continuation_collector_evidence(bad)

    def test_fixed_log_download_keeps_authorization_on_github_only(self):
        raw=site_continuation_output();pin=m.SITE_CONTINUATION_COLLECTOR|{'log_sha256':m.sha(raw)}
        endpoint=f'https://api.github.com/repos/{m.REPO}/actions/jobs/{pin["job_id"]}/logs'
        for location,allowed in (
            ('https://logs.blob.core.windows.net/signed-log?fixture=1',True),
            ('https://logs.actions.githubusercontent.com/signed-log',True),
            ('https://evil.invalid/log',False),('http://logs.blob.core.windows.net/log',False),
            ('https://logs.blob.core.windows.net.evil.invalid/log',False),
            ('https://fixture@logs.blob.core.windows.net/log',False),
            ('https://logs.blob.core.windows.net:444/log',False),
            ('https://logs.blob.core.windows.net/log#fragment',False)):
            requests=[]
            class Opener:
                def open(self,request,timeout):
                    requests.append(request)
                    if len(requests)==1:raise m.urllib.error.HTTPError(endpoint,302,'redirect',{'Location':location},None)
                    return io.BytesIO(raw)
            with self.subTest(location=location),patch.dict(os.environ,{'GH_TOKEN':'fixture-token'}), \
                 patch.object(m,'SITE_CONTINUATION_COLLECTOR',pin),patch.object(m.urllib.request,'build_opener',return_value=Opener()) as build:
                if allowed:self.assertEqual(m.site_continuation_collector_log(),m.site_continuation_collector_value())
                else:
                    with self.assertRaisesRegex(RuntimeError,'site_collector_redirect'):m.site_continuation_collector_log()
                self.assertEqual(requests[0].full_url,endpoint)
                self.assertEqual(requests[0].get_header('Authorization'),'Bearer fixture-token')
                self.assertIsNone(build.call_args.args[0].redirect_request(None,None,None,None,None,None))
                self.assertEqual(len(requests),2 if allowed else 1)
                if allowed:self.assertEqual(requests[1].headers,{})


class SiteContinuationRollbackAdmission(unittest.TestCase):
    def metadata(self,path):
        if path=='/git/ref/heads/main':return {'object':{'sha':SITE_CONTINUATION_ROLLBACK_IDENTITY['control_source_sha']}}
        if path.startswith('/issues/4709/comments?'):
            return [site_continuation_event()['comment'],site_continuation_event(True)['comment']]
        original=SITE_CONTINUATION_IDENTITY['run_id'];historical=SITE_IDENTITY['run_id']
        value=SiteRollbackAdmission().metadata(path.replace(original,historical))
        if path=='/actions/runs/'+original:
            value.update(id=int(original),head_sha=SITE_CONTINUATION_IDENTITY['control_source_sha'])
        elif '/attempts/1/jobs?' in path:
            value['jobs'][0].update(run_id=int(original))
            value['jobs'][0]['steps'][0]['name']='Run python3 scripts/deploy/search3_public_install.py site-continue'
        elif '/artifacts?' in path:
            value['artifacts'][0].update(name='search3-public-install-'+original+'-1',workflow_run={'id':int(original)})
        return value

    def admit(self,change=None):
        def api(path):
            value=self.metadata(path);return change(path,value) if change else value
        with contextlib.ExitStack() as stack:
            stack.enter_context(patch.object(m,'api',side_effect=api));stack.enter_context(patch.object(m,'reviewed_control'))
            for name in ('site_admission','site_parent_admission','site_continuation_admission','site_continuation_collector_admission','site_continuation_collector_log'):
                stack.enter_context(patch.object(m,name,side_effect=AssertionError('unrelated recovery proof: '+name)))
            original,artifact=m.site_rollback_admission(SITE_CONTINUATION_ROLLBACK_IDENTITY,SITE_CONTINUATION_IDENTITY['run_id'],True)
            self.assertEqual(original,SITE_CONTINUATION_IDENTITY);self.assertEqual(artifact['id'],444)

    def test_child_recovery_uses_only_its_successful_command_and_receipt(self):
        self.admit()
        for pin in (m.SITE_INSPECT_FAILED,m.SITE_CONTINUATION_INSPECTION,m.SITE_CONTINUATION_COLLECTOR):
            with patch.object(m,'api') as api,self.assertRaises(RuntimeError):
                m.site_rollback_commands(SITE_CONTINUATION_ROLLBACK_IDENTITY,pin['run_id'],continuation=True)
            api.assert_not_called()

    def test_old_mode_duplicate_unknown_and_foreign_child_proofs_fail(self):
        for part,mutate in (('/comments?',lambda v:v.append(v[0]|{'id':99999})),
            ('/comments?',lambda v:v[0].update(body=site_event()['comment']['body'])),
            ('/comments?',lambda v:v[1].update(body=site_event(True)['comment']['body'])),
            ('/actions/runs/'+SITE_CONTINUATION_IDENTITY['run_id'],lambda v:v.update(conclusion='failure')),
            ('/actions/runs/'+SITE_CONTINUATION_IDENTITY['run_id'],lambda v:v.update(run_attempt=2)),
            ('/attempts/1/jobs?',lambda v:v['jobs'][0]['steps'][0].update(name='Run python3 scripts/deploy/search3_public_install.py site-update')),
            ('/artifacts?',lambda v:v['artifacts'][0].update(workflow_run={'id':int(m.SITE_INSPECT_FAILED['run_id'])}))):
            def change(path,value):
                if path==part or part.endswith('?') and part in path:mutate(value)
                return value
            with self.subTest(part=part),self.assertRaises(RuntimeError):self.admit(change)


class SiteContinuationTransaction(unittest.TestCase):
    @contextlib.contextmanager
    def inspected(self):
        helper=SiteInspectionTransaction()
        with helper.failed_attempt() as f:
            path=f.root/'data/db-v1.php';expected=path.read_bytes();observed=b'previous collector root helper';path.write_bytes(observed)
            result=helper.execute(f);path.write_bytes(expected)
            value={**SITE_INSPECTION_IDENTITY,'operation_id':m.SITE_INSPECT_OPERATION,'source_sha':m.SITE_SOURCE,
                'manifest_sha256':m.site_policy()['manifest'],'read_only':True,'phase':'complete','ssh_started':True,
                'status':'inspection_mismatch','failed_attempt':m.SITE_INSPECT_FAILED,'remote':result,'failed_evidence':f.inspection_failed}
            data=(json.dumps(value,sort_keys=True)+'\n').encode();packet=io.BytesIO()
            with zipfile.ZipFile(packet,'w') as archive:archive.writestr('evidence.json',data)
            f.continuation_evidence=value;f.continuation_raw=packet.getvalue()
            pin=m.SITE_CONTINUATION_INSPECTION|SITE_INSPECTION_IDENTITY|{'receipt_sha256':m.sha(data),'artifact_sha256':m.sha(f.continuation_raw)}
            db={'state':'present','mode':'0644','size':len(observed),'sha256':m.sha(observed)}
            collector=m.SITE_CONTINUATION_COLLECTOR|{'log_sha256':m.sha(site_continuation_output())}
            with patch.object(m,'SITE_CONTINUATION_INSPECTION',pin),patch.object(m,'SITE_CONTINUATION_DB_OBSERVED',db), \
                 patch.object(m,'SITE_CONTINUATION_COLLECTOR',collector):
                f.continuation_policy=m.site_policy(True);f.continuation_private=f.private.parent/m.SITE_CONTINUATION_OPERATION
                yield f

    def execute(self,f,change=None):
        parent=m.site_continuation_inspection_value(f.continuation_evidence)
        program=m.site_remote_program(f.continuation_policy,f.site_files,SITE_CONTINUATION_IDENTITY,parent,True)
        value=UpdateTransaction().run_program(f,program,change)
        m.valid_site_result(SITE_CONTINUATION_IDENTITY,value,continuation=True)
        return value

    def rollback(self,f,change=None):
        program=m.site_rollback_remote_program(f.continuation_policy,SITE_CONTINUATION_ROLLBACK_IDENTITY,SITE_CONTINUATION_IDENTITY,
                                              f.continuation_accepted,f.site_parent_value,True)
        value=UpdateTransaction().run_program(f,program,change)
        m.valid_site_result(SITE_CONTINUATION_ROLLBACK_IDENTITY,value,SITE_CONTINUATION_IDENTITY,f.continuation_accepted,True)
        return value

    def test_successful_child_and_rollback_preserve_failed_namespace_and_parent_history(self):
        helper=SiteTransaction()
        with self.inspected() as f:
            before=helper.public_files(f);parents=helper.parent_records(f)
            f.continuation_accepted=self.execute(f)
            self.assertEqual(f.continuation_accepted['status'],'site_updated_verified')
            self.assertEqual(f.continuation_accepted['written_files'],list(m.SITE_ORDER))
            self.assertTrue(f.continuation_accepted['failed_namespace_absent'])
            self.assertFalse(f.site_private.exists());self.assertEqual(helper.parent_records(f),parents)
            self.assertEqual({p for p,v in before.items() if helper.public_files(f)[p]!=v},set(m.SITE_ORDER))
            self.assertEqual(self.rollback(f)['status'],'site_rolled_back_verified')
            self.assertEqual(helper.public_files(f),before);self.assertEqual(helper.parent_records(f),parents)
            self.assertFalse(f.site_private.exists())
            self.assertEqual(UpdateTransaction().rollback(f)['status'],'update_rolled_back_verified')
            self.assertEqual(RollbackTransaction().execute(f)['status'],'rolled_back_verified');f.restored()

    def test_old_namespace_wrong_db_provider_or_target_blocks_before_child_reservation(self):
        for kind in ('namespace','namespace-link','db-old','db-third','provider-third','target','parent'):
            with self.subTest(kind=kind),self.inspected() as f:
                if kind=='namespace':f.site_private.mkdir(mode=0o700)
                elif kind=='namespace-link':f.site_private.symlink_to(f.home)
                elif kind=='db-old':(f.root/'data/db-v1.php').write_bytes(b'previous collector root helper')
                elif kind=='db-third':(f.root/'data/db-v1.php').write_bytes(b'unreviewed third helper')
                elif kind=='provider-third':
                    path=next(p for p in m.CONTINUATION_PROTECTED if p.endswith('/tourvisor-interactive-client.php'))
                    (f.root/path).write_bytes(b'future provider version not admitted')
                elif kind=='target':(f.root/m.SITE_ORDER[0]).write_bytes(b'foreign target')
                else:(f.private/'result.json').write_bytes(b'{}')
                before=f.filesystem_snapshot();value=self.execute(f)
                self.assertEqual(value['status'],'blocked_no_replay');self.assertFalse(value['reservation_created'])
                self.assertFalse(f.continuation_private.exists());self.assertEqual(f.filesystem_snapshot(),before)

    def test_failed_namespace_race_is_checked_before_reservation(self):
        with self.inspected() as f:
            def change(program):
                line=' before=public.inventory(order)';self.assertIn(line,program)
                return program.replace(line," os.mkdir(SITE_POLICY['continuation']['failed']['operation_id'],0o700,dir_fd=base.fd)\n"+line,1)
            value=self.execute(f,change)
            self.assertEqual(value['status'],'blocked_no_replay');self.assertEqual(value['reason'],'site_failed_operation_exists')
            self.assertTrue(f.site_private.exists());self.assertFalse(f.continuation_private.exists())

    def test_failed_namespace_race_stops_later_writes_and_automatic_restore(self):
        with self.inspected() as f:
            before=SiteTransaction().public_files(f);parents=SiteTransaction().parent_records(f)
            def change(program):
                line="   exchange(src,name,dst,target);switched.append(path);result['written_files'].append(path);sync(src);sync(dst)"
                self.assertIn(line,program)
                return program.replace(line,line+"\n   if index==0: os.mkdir(SITE_POLICY['continuation']['failed']['operation_id'],0o700,dir_fd=base.fd)",1)
            value=self.execute(f,change)
            self.assertEqual(value['status'],'unknown_no_replay');self.assertFalse(value['failed_namespace_absent'])
            self.assertEqual(value['written_files'],[m.SITE_ORDER[0]]);self.assertEqual(value['rollback_files'],[])
            self.assertEqual({p for p,v in before.items() if SiteTransaction().public_files(f)[p]!=v},{m.SITE_ORDER[0]})
            self.assertEqual(SiteTransaction().parent_records(f),parents)
            snapshot=f.filesystem_snapshot();again=self.execute(f)
            self.assertEqual(again['status'],'blocked_no_replay');self.assertEqual(f.filesystem_snapshot(),snapshot)

    def test_each_failed_exchange_restores_own_files_and_child_cannot_repeat(self):
        helper=SiteTransaction()
        for index in range(4):
            with self.subTest(index=index),self.inspected() as f:
                before=helper.public_files(f);parents=helper.parent_records(f)
                value=self.execute(f,helper.fail_after_exchange(index))
                self.assertEqual(value['status'],'rolled_back_no_replay')
                self.assertEqual(value['rollback_files'],list(reversed(m.SITE_ORDER[:index+1])))
                self.assertEqual(helper.public_files(f),before);self.assertEqual(helper.parent_records(f),parents)
                snapshot=f.filesystem_snapshot();again=self.execute(f)
                self.assertEqual(again['status'],'blocked_no_replay');self.assertEqual(f.filesystem_snapshot(),snapshot)

    def test_inspection_and_failed_proof_tampering_cannot_admit_child(self):
        with self.inspected() as f:
            self.assertEqual(m.site_continuation_inspection_evidence(f.continuation_raw),f.continuation_evidence)
            with self.assertRaises(RuntimeError):m.site_continuation_inspection_evidence(f.continuation_raw+b'changed')
            changes=(lambda v:v.update(read_only=False),lambda v:v['remote'].update(server_writes=1),
                lambda v:v['remote'].update(inventory_stable=False),lambda v:v['remote'].update(failed_namespace_absent=False),
                lambda v:v['failed_evidence'].update(status='unknown_no_replay'),
                lambda v:v['failed_evidence']['remote'].update(reservation_created=True),
                lambda v:v['remote']['private_checks']['public_config'].update(matches_parent_snapshot=False),
                lambda v:v['remote']['target_comparisons'][m.SITE_ORDER[0]].update(matches_predecessor=False))
            for change in changes:
                value=copy.deepcopy(f.continuation_evidence);change(value)
                pin=m.SITE_CONTINUATION_INSPECTION|{'receipt_sha256':m.sha((json.dumps(value,sort_keys=True)+'\n').encode())}
                with self.subTest(change=change),patch.object(m,'SITE_CONTINUATION_INSPECTION',pin),self.assertRaises(RuntimeError):
                    m.site_continuation_inspection_value(value)

    def test_saved_child_receipt_alone_supports_recovery_and_rejects_original_alias(self):
        with self.inspected() as f:
            f.continuation_accepted=self.execute(f)
            value={**SITE_CONTINUATION_IDENTITY,'operation_id':m.SITE_CONTINUATION_OPERATION,'source_sha':m.SITE_SOURCE,
                'manifest_sha256':f.continuation_policy['manifest'],'read_only':False,'phase':'complete','ssh_started':True,
                'status':'site_updated_verified','parent_update':m.site_parent_identity(),'parent_evidence':f.site_parent_value,
                'continuation_evidence':f.continuation_evidence,'collector_evidence':m.site_continuation_collector_value(),'remote':f.continuation_accepted}
            def archive(v):
                stream=io.BytesIO()
                with zipfile.ZipFile(stream,'w') as z:z.writestr('evidence.json',(json.dumps(v,sort_keys=True)+'\n').encode())
                return stream.getvalue()
            with patch.object(m,'api',side_effect=AssertionError('network during saved recovery')):
                policy,accepted,parent=m.site_source_evidence(archive(value),SITE_CONTINUATION_IDENTITY,True)
            self.assertEqual(policy,f.continuation_policy);self.assertEqual(accepted,f.continuation_accepted);self.assertEqual(parent,f.site_parent_value)
            for mutate in (lambda v:v.update(operation_id=m.SITE_OPERATION),lambda v:v.update(status='unknown_no_replay'),
                           lambda v:v['remote'].update(operation_id=m.SITE_OPERATION),lambda v:v['collector_evidence'].update(log_sha256='0'*64)):
                bad=copy.deepcopy(value);mutate(bad)
                with self.assertRaises(RuntimeError):m.site_source_evidence(archive(bad),SITE_CONTINUATION_IDENTITY,True)
            with self.assertRaises(RuntimeError):m.site_source_evidence(archive(value),SITE_CONTINUATION_IDENTITY)

    def test_client_preserves_child_proofs_and_rolls_back_without_refetching_ancestors(self):
        with self.inspected() as f:
            f.continuation_accepted=self.execute(f)
            def runner_env(name,rollback=False):
                runner=f.base/name;runner.mkdir();event_path=runner/'event.json';event_path.write_text(json.dumps(site_continuation_event(rollback)))
                identity=SITE_CONTINUATION_ROLLBACK_IDENTITY if rollback else SITE_CONTINUATION_IDENTITY
                return runner,environment()|{'GITHUB_RUN_ID':identity['run_id'],'GITHUB_SHA':identity['control_source_sha'],
                    'RUNNER_TEMP':str(runner),'GITHUB_EVENT_PATH':str(event_path),'PREVIEW_HOST':'example.invalid',
                    'PREVIEW_USER':'fixture','PREVIEW_KEY':'PRIVATE KEY fixture'}
            runner,env=runner_env('child-runner')
            with patch.dict(os.environ,env),patch.object(m,'site_admission') as admission,patch.object(m,'site_inspect_heads'), \
                 patch.object(m,'download',return_value=f.continuation_raw) as download,patch.object(m,'site_package',return_value=f.site_files), \
                 patch.object(m,'site_continuation_collector_log',return_value=m.site_continuation_collector_value()) as collector, \
                 patch.object(m,'tv_successor_evidence',return_value={'status':'absent'}), \
                 patch.object(m.carrier,'run',side_effect=[b'fixture known host',json.dumps(f.continuation_accepted).encode()]),patch('builtins.print'):
                m.site_update(continuation=True)
            self.assertEqual(admission.call_count,2);collector.assert_called_once_with()
            download.assert_called_once_with(m.SITE_CONTINUATION_INSPECTION['artifact_id'],m.SITE_CONTINUATION_INSPECTION['artifact_sha256'])
            evidence=runner/'search3-public-install/evidence.json';value=json.loads(evidence.read_text())
            self.assertEqual(value['status'],'site_updated_verified');self.assertEqual(value['operation_id'],m.SITE_CONTINUATION_OPERATION)
            self.assertEqual(value['continuation_evidence'],f.continuation_evidence)
            self.assertEqual(value['collector_evidence'],m.site_continuation_collector_value())
            self.assertFalse((evidence.parent/'key').exists());self.assertFalse((evidence.parent/'known').exists())
            packet=io.BytesIO()
            with zipfile.ZipFile(packet,'w') as archive:archive.writestr('evidence.json',evidence.read_bytes())
            raw=packet.getvalue();restored=self.rollback(f);recovery,env=runner_env('child-recovery',True)
            with contextlib.ExitStack() as stack:
                stack.enter_context(patch.dict(os.environ,env))
                stack.enter_context(patch.object(m,'site_rollback_admission',return_value=(SITE_CONTINUATION_IDENTITY,{'id':444,'digest':'sha256:'+m.sha(raw)})))
                stack.enter_context(patch.object(m,'site_rollback_commands',return_value=SITE_CONTINUATION_IDENTITY));stack.enter_context(patch.object(m,'rollback_main'))
                stack.enter_context(patch.object(m,'tv_successor_evidence',return_value={'status':'absent'}))
                download=stack.enter_context(patch.object(m,'download',return_value=raw))
                for name in ('api','site_admission','site_inspect_heads','site_package','site_parent_admission','site_continuation_collector_log','site_continuation_collector_admission'):
                    stack.enter_context(patch.object(m,name,side_effect=AssertionError('unnecessary recovery dependency: '+name)))
                stack.enter_context(patch.object(m.carrier,'run',side_effect=[b'fixture known host',json.dumps(restored).encode()]))
                stack.enter_context(patch('builtins.print'));m.site_update(rollback=True,continuation=True)
                download.assert_called_once_with(444,m.sha(raw))
            value=json.loads((recovery/'search3-public-install/evidence.json').read_text())
            self.assertEqual(value['status'],'site_rolled_back_verified');self.assertEqual(value['operation_id'],m.SITE_CONTINUATION_ROLLBACK_OPERATION)
            for name,bad_archive in (('bad-inspection',True),('bad-collector',False)):
                runner,env=runner_env(name)
                with patch.dict(os.environ,env),patch.object(m,'site_admission'), \
                     patch.object(m,'download',return_value=b'invalid' if bad_archive else f.continuation_raw), \
                     patch.object(m,'site_continuation_collector_log',side_effect=RuntimeError('site_collector_log_digest')), \
                     patch.object(m.carrier,'run') as remote,patch('builtins.print'),self.assertRaises(RuntimeError):
                    m.site_update(continuation=True)
                remote.assert_not_called();value=json.loads((runner/'search3-public-install/evidence.json').read_text())
                self.assertEqual(value['status'],'blocked_no_replay');self.assertFalse(value['ssh_started'])


class TvSuccessorRetainedAdmission(unittest.TestCase):
    @contextlib.contextmanager
    def retained(self,change=None,result=None):
        pin=copy.deepcopy(m.TV_SUCCESSOR_RETAINED);original=copy.deepcopy(pin['proof'])
        if result is not None:
            # Only the inert filesystem fixture substitutes bytes. Production
            # receipt authenticity is checked independently against its actual hash.
            pin['result_canonical_json']=json.dumps(result,sort_keys=True,separators=(',',':'))
            pin['result_canonical_sha256']=m.sha(pin['result_canonical_json'].encode())
        command={'id':7063787927,'user':{'id':226193297},'author_association':'OWNER',
                 'body':'/run-int-server-v1 '+' '.join(m.TV_SUCCESSOR[k] for k in ('source_sha','mode','operation_id')),
                 'created_at':'2026-11-21T14:03:31Z','updated_at':'2026-11-21T14:03:31Z'}
        run={'id':44911949356,'path':'.github/workflows/int-server-executor.yml','head_branch':'main',
             'event':'issue_comment','head_sha':'79ae813b41c933542dbb05577bd12ebcdfe3bc2b',
             'run_attempt':1,'status':'completed','conclusion':'success',
             'actor':{'login':'pyatkoff'},'triggering_actor':{'login':'pyatkoff'},
             'created_at':'2026-11-21T14:03:34Z','run_started_at':'2026-11-21T14:03:34Z',
             'updated_at':'2026-11-21T14:06:12Z'}
        job={'id':136869432183,'run_id':44911949356,'name':'execute','status':'completed','conclusion':'success',
             'started_at':'2026-11-21T14:03:37Z','completed_at':'2026-11-21T14:06:11Z',
             'steps':[{'name':'Execute bounded private INT operation, install, or DB/LOCAL readback',
                       'status':'completed','conclusion':'success','started_at':'2026-11-21T14:05:54Z',
                       'completed_at':'2026-11-21T14:06:06Z'}]}
        data={'pin':pin,'commands':[command],'run':run,'jobs':[job],
              'blobs':copy.deepcopy(m.TV_SUCCESSOR['control_blobs']),'calls':[]}
        if change:change(data)
        def pages(path,key=None):
            data['calls'].append(('pages',path))
            if path=='/issues/4709/comments':return copy.deepcopy(data['commands'])
            if path=='/actions/runs/'+str(original['run_id'])+'/attempts/1/jobs':return copy.deepcopy(data['jobs'])
            raise AssertionError('retained proof must not discover runs or artifacts: '+path)
        def api(path):
            data['calls'].append(('api',path))
            if path=='/actions/runs/'+str(original['run_id']):return copy.deepcopy(data['run'])
            if path=='/contents/scripts/deploy?ref='+original['control_source_sha']:
                return [{'type':'file','path':source,'sha':blob} for source,blob in data['blobs'].items()
                        if source.startswith('scripts/deploy/')]
            for source,blob in data['blobs'].items():
                if path=='/contents/'+source+'?ref='+original['control_source_sha']:return {'type':'file','sha':blob}
            raise AssertionError('retained proof must not fetch artifacts or unrelated metadata: '+path)
        with patch.object(m,'TV_SUCCESSOR_RETAINED',pin),patch.object(m,'pages',side_effect=pages), \
             patch.object(m,'api',side_effect=api), \
             patch.object(m,'download',side_effect=AssertionError('retained INT artifact download')) as download, \
             patch.object(m.carrier,'run',side_effect=AssertionError('retained admission must not contact server')):
            data['download']=download
            yield data

    def change_result(self,data,change,repin=False):
        value=json.loads(data['pin']['result_canonical_json']);change(value)
        raw=json.dumps(value,sort_keys=True,separators=(',',':'))
        data['pin']['result_canonical_json']=raw
        if repin:data['pin']['result_canonical_sha256']=m.sha(raw.encode())

    def test_actual_terminal_receipt_and_provenance_are_frozen_exactly(self):
        pin=m.TV_SUCCESSOR_RETAINED
        self.assertEqual(set(pin),{'proof','run_attempt','run_created_at','run_started_at',
                                  'result_canonical_json','result_canonical_sha256'})
        self.assertEqual(pin['proof'],{'status':'installed','run_id':44911949356,'job_id':136869432183,
            'command_comment_id':7063787927,'control_source_sha':'79ae813b41c933542dbb05577bd12ebcdfe3bc2b',
            'artifact_id':14710576060,'artifact_sha256':'3e6a106c3f8529422a68e01cab27baca8b9037936a769301119a92a0f5fd3dd0',
            'created_at':1795269811,'started_at':1795269954,'completed_at':1795269966})
        self.assertIs(type(pin['run_attempt']),int);self.assertEqual(pin['run_attempt'],1)
        self.assertEqual(pin['run_created_at'],'2026-11-21T14:03:34Z')
        self.assertEqual(pin['run_started_at'],'2026-11-21T14:03:34Z')
        raw=pin['result_canonical_json'].encode('ascii');value=json.loads(raw)
        self.assertEqual(len(raw),4418)
        self.assertEqual(m.sha(raw),'4bbfd3c2aad2d534c043c0efcbedb349c2374331c0be78d783d546d897a759d2')
        self.assertEqual(pin['result_canonical_sha256'],m.sha(raw))
        self.assertEqual(raw,json.dumps(value,sort_keys=True,separators=(',',':')).encode())
        self.assertTrue(m.tv_successor_result_valid(value))
        self.assertEqual(value['install']['manifest_sha256'],'c591ec8340d231221c20199e02fda954a51f8dcf25c2b3b9ebdb48942709e766')
        self.assertEqual((value['install']['files'],value['install']['changed_files'],value['install']['created_files']),(12,1,0))

    def test_retained_original_outlives_expired_or_deleted_artifact_without_artifact_lookups(self):
        # Both lifecycle states deliberately have no artifact route: any list,
        # metadata or ZIP lookup fails even when all remaining evidence is sound.
        for lifecycle in ('expired','deleted'):
            with self.subTest(lifecycle=lifecycle),self.retained() as data:
                expected=data['pin']['proof']|{'result':json.loads(data['pin']['result_canonical_json'])}
                value=m.tv_successor_evidence();self.assertEqual(value,expected)
                self.assertEqual(set(value),set(data['pin']['proof'])|{'result'})
                self.assertIn('TV_SUCCESSOR_PROOF=',m.tv_successor_prefix(value))
                value['result']['install']['manifest_sha256']='0'*64
                again=m.tv_successor_evidence();self.assertEqual(again,expected)
                self.assertEqual(m.sha(data['pin']['result_canonical_json'].encode()),data['pin']['result_canonical_sha256'])
                data['download'].assert_not_called()
                self.assertEqual(sum(path=='/actions/runs/44911949356' for _,path in data['calls']),2)
                self.assertFalse(any('/artifacts' in path or '/workflows/' in path and not path.startswith('/contents/')
                                     for _,path in data['calls']))

    def test_retained_tamper_and_invalid_result_never_fall_back_to_an_artifact(self):
        changes=(lambda d:d['pin'].update(result_canonical_sha256='0'*64),
                 lambda d:d['pin'].update(result_canonical_json=d['pin']['result_canonical_json']+'\n'),
                 lambda d:d['pin'].update(run_attempt=True),lambda d:d['pin'].update(run_attempt=2),
                 lambda d:d['pin']['proof'].update(run_id=True),lambda d:d['pin']['proof'].update(command_comment_id=0),
                 lambda d:d['pin']['proof'].update(artifact_id=-1),lambda d:d['pin']['proof'].update(artifact_sha256='0'*63),
                 lambda d:d['pin']['proof'].update(unreviewed=True),lambda d:d['pin'].update(unreviewed=True),
                 lambda d:self.change_result(d,lambda v:v['install'].update(manifest_sha256='0'*64)),
                 lambda d:self.change_result(d,lambda v:v.update(status='unknown_no_replay'),True),
                 lambda d:self.change_result(d,lambda v:v.update(supplier_calls=1),True),
                 lambda d:self.change_result(d,lambda v:v.update(schema_version=True),True),
                 lambda d:self.change_result(d,lambda v:v.update(source_sha='0'*40),True),
                 lambda d:self.change_result(d,lambda v:v['install']['tv_client'].update(sha256='0'*64),True))
        for index,change in enumerate(changes):
            with self.subTest(index=index),self.retained(change) as data,self.assertRaises(RuntimeError):m.tv_successor_evidence()
            data['download'].assert_not_called()
        for encoding in ('pretty','duplicate-key'):
            with self.subTest(encoding=encoding),self.retained() as data:
                raw=(json.dumps(json.loads(data['pin']['result_canonical_json']),indent=2) if encoding=='pretty'
                     else '{"status":"unknown_no_replay",'+data['pin']['result_canonical_json'][1:])
                data['pin'].update(result_canonical_json=raw,result_canonical_sha256=m.sha(raw.encode()))
                with self.assertRaises(RuntimeError):m.tv_successor_evidence()
                data['download'].assert_not_called()

    def test_retained_original_requires_live_unique_command_original_run_job_and_full_control(self):
        changes=(lambda d:d.update(commands=[]),
                 lambda d:d['commands'].append(copy.deepcopy(d['commands'][0])|{'id':7063787928}),
                 lambda d:d['commands'][0].update(id=7063787928),
                 lambda d:d['commands'][0].update(body=d['commands'][0]['body']+' edited'),
                 lambda d:d['commands'][0]['user'].update(id=1),lambda d:d['commands'][0].update(author_association='MEMBER'),
                 lambda d:d['commands'][0].update(updated_at='2026-11-21T14:03:32Z'),
                 lambda d:d['commands'][0].update(created_at='2026-11-21T14:03:32Z',updated_at='2026-11-21T14:03:32Z'),
                 lambda d:d['run'].update(id=44911949357),lambda d:d['run'].update(head_sha='c'*40),
                 lambda d:d['run'].update(run_attempt=2),lambda d:d['run'].update(run_attempt=True),
                 lambda d:d['run'].update(status='in_progress',conclusion=None),lambda d:d['run'].update(conclusion='failure'),
                 lambda d:d['run'].update(event='workflow_dispatch'),lambda d:d['run'].update(head_branch='branch'),
                 lambda d:d['run'].update(path='.github/workflows/other.yml'),
                 lambda d:d['run']['actor'].update(login='other'),lambda d:d['run']['triggering_actor'].update(login='other'),
                 lambda d:d['run'].update(created_at='2026-11-21T14:03:35Z'),
                 lambda d:d['run'].update(run_started_at='2026-11-21T14:03:35Z'),
                 lambda d:d['run'].update(updated_at='2026-11-21T14:06:05Z'),
                 lambda d:d.update(jobs=[]),lambda d:d['jobs'].append(copy.deepcopy(d['jobs'][0])),
                 lambda d:d['jobs'][0].update(id=136869432184),lambda d:d['jobs'][0].update(run_id=44911949357),
                 lambda d:d['jobs'][0].update(status='in_progress'),lambda d:d['jobs'][0].update(conclusion='failure'),
                 lambda d:d['jobs'][0].update(steps=[]),
                 lambda d:d['jobs'][0]['steps'].append(copy.deepcopy(d['jobs'][0]['steps'][0])),
                 lambda d:d['jobs'][0]['steps'][0].update(name='another execute step'),
                 lambda d:d['jobs'][0]['steps'][0].update(conclusion='failure'),
                 lambda d:d['jobs'][0]['steps'][0].update(started_at='2026-11-21T14:05:55Z'),
                 lambda d:d['jobs'][0]['steps'][0].update(completed_at='2026-11-21T14:06:05Z'))
        for index,change in enumerate(changes):
            with self.subTest(index=index),self.retained(change),self.assertRaises(RuntimeError):m.tv_successor_evidence()
        for source in m.TV_SUCCESSOR['control_blobs']:
            with self.subTest(source=source),self.retained(lambda d:d['blobs'].update({source:'0'*40})),self.assertRaises(RuntimeError):
                m.tv_successor_evidence()
        with self.retained() as data:
            original=m._tv_commands;calls=[]
            def changing_command():
                calls.append(1)
                if len(calls)==2:data['commands'][0]['id']+=1
                return original()
            with patch.object(m,'_tv_commands',side_effect=changing_command),self.assertRaises(RuntimeError):m.tv_successor_evidence()
            self.assertEqual(len(calls),2)
        with self.retained() as data,tempfile.TemporaryDirectory() as directory:
            signer=Path(directory)/'signing.py';signer.write_bytes(Path(m.tv_signing.__file__).read_bytes()+b'\n# unreviewed\n')
            with patch.object(m.tv_signing,'__file__',str(signer)),self.assertRaisesRegex(RuntimeError,'tv_successor_validator_source'):
                m.tv_successor_evidence()
            self.assertEqual(data['calls'],[])

    def test_all_seven_live_paths_recheck_actual_retained_provenance_before_ssh(self):
        cases=((m.update,{},update_event(),UPDATE_IDENTITY,'update_remote_program'),
               (m.update,{'rollback':True},update_rollback_event(),UPDATE_ROLLBACK_IDENTITY,'update_rollback_remote_program'),
               (m.site_update,{},site_event(),SITE_IDENTITY,'site_remote_program'),
               (m.site_update,{'rollback':True},site_event(True),SITE_ROLLBACK_IDENTITY,'site_rollback_remote_program'),
               (m.site_update,{'continuation':True},site_continuation_event(),SITE_CONTINUATION_IDENTITY,'site_remote_program'),
               (m.site_update,{'rollback':True,'continuation':True},site_continuation_event(True),SITE_CONTINUATION_ROLLBACK_IDENTITY,'site_rollback_remote_program'),
               (m.rollback,{},rollback_event(),ROLLBACK_IDENTITY,'rollback_remote_program'))
        artifact={'id':777,'digest':'sha256:'+'7'*64}
        for call,options,event_value,identity,builder in cases:
            with self.subTest(path=builder,options=options),self.retained() as data, \
                 tempfile.TemporaryDirectory() as directory,contextlib.ExitStack() as stack:
                event_path=Path(directory)/'event.json';event_path.write_text(json.dumps(event_value))
                env=environment()|{'GITHUB_SHA':identity['control_source_sha'],'GITHUB_RUN_ID':identity['run_id'],
                    'RUNNER_TEMP':directory,'GITHUB_EVENT_PATH':str(event_path),'PREVIEW_HOST':'example.invalid',
                    'PREVIEW_USER':'fixture','PREVIEW_KEY':'PRIVATE KEY fixture'}
                stack.enter_context(patch.dict(os.environ,env));stack.enter_context(patch('builtins.print'))
                values={'rollback_admission':(IDENTITY,artifact),'update_rollback_admission':(UPDATE_IDENTITY,artifact),
                        'site_rollback_admission':(SITE_IDENTITY,artifact),'package':({'expected':{},'release':RELEASE},{}),
                        'rollback_source_evidence':{},'update_package':({'release':RELEASE},{}),'update_parent_evidence':{},
                        'update_source_evidence':({'release':RELEASE},{},{}),'site_package':{},'site_parent_evidence':{},
                        'site_source_evidence':(m.site_policy(),{},{}),'site_continuation_inspection_evidence':{},
                        'site_continuation_inspection_value':{},'site_continuation_collector_log':{}}
                for name,value in values.items():stack.enter_context(patch.object(m,name,return_value=value))
                for name in ('rollback_main','rollback_commands','update_admission','update_rollback_commands',
                             'site_admission','site_rollback_commands'):stack.enter_context(patch.object(m,name))
                def public_download(artifact_id,digest):
                    self.assertNotEqual(artifact_id,14710576060,'expired INT artifact must not be requested')
                    return b'inert public artifact'
                stack.enter_context(patch.object(m,'download',side_effect=public_download))
                program=stack.enter_context(patch.object(m,builder,return_value='# inert generated program'))
                original=m.tv_successor_evidence;calls=[]
                def rechecked():
                    calls.append(1)
                    if len(calls)==2:data['run'].update(status='in_progress',conclusion=None)
                    return original()
                stack.enter_context(patch.object(m,'tv_successor_evidence',side_effect=rechecked))
                carrier=stack.enter_context(patch.object(m.carrier,'run',return_value=b'fixture known host'))
                with self.assertRaises(RuntimeError):call(**options)
                self.assertEqual(len(calls),2);self.assertEqual(program.call_count,1)
                self.assertEqual(program.call_args.kwargs['tv_successor'],data['pin']['proof']|
                                 {'result':json.loads(data['pin']['result_canonical_json'])})
                self.assertEqual([row.args[0][0] for row in carrier.call_args_list],['ssh-keyscan'])
                evidence=json.loads((Path(directory)/'search3-public-install/evidence.json').read_text())
                self.assertEqual(evidence['status'],'blocked_no_replay');self.assertFalse(evidence['ssh_started'])
                self.assertFalse((Path(directory)/'search3-public-install/key').exists())
                self.assertFalse((Path(directory)/'search3-public-install/known').exists())

    def test_retained_resolution_keeps_remote_guards_and_full_public_recovery(self):
        child=SiteContinuationTransaction();site=SiteTransaction();transition=TvSuccessorTransaction()
        for damage in (None,'result','plan','state','backup','target'):
            with self.subTest(damage=damage),child.inspected() as f,transition.successor(f), \
                 self.retained(result=f.tv_evidence['result']) as data:
                f.tv_evidence=m.tv_successor_evidence()
                reservation=f.tv_namespace/'reservation.json';value=json.loads(reservation.read_text())
                value['reserved_at']=f.tv_evidence['started_at']+1;reservation.write_text(json.dumps(value,sort_keys=True))
                if damage is not None:
                    path=({'result':f.tv_namespace/'result.json','plan':f.tv_namespace/'anex-preview-install-plan.json',
                           'state':f.tv_namespace/'install-state.json',
                           'backup':f.tv_namespace/'backup'/m.TV_SUCCESSOR['relative'],'target':f.root/f.tv_target})[damage]
                    path.write_bytes(b'foreign replacement');before=f.filesystem_snapshot();value=child.execute(f)
                    self.assertEqual(value['status'],'blocked_no_replay');self.assertEqual(value['written_files'],[])
                    self.assertFalse(f.continuation_private.exists());self.assertEqual(f.filesystem_snapshot(),before)
                    continue
                parents=site.parent_records(f);proof=transition.proof_records(f)
                f.continuation_accepted=child.execute(f);self.assertEqual(f.continuation_accepted['status'],'site_updated_verified')
                transition.assert_no_actions(f.continuation_accepted);self.assertEqual(site.parent_records(f),parents)
                records={str(path):(UpdateTransaction().stamp(path),path.read_bytes())
                         for store in (f.private,f.update_private,f.continuation_private) for path in store.rglob('*.json')}
                value=child.rollback(f);self.assertEqual(value['status'],'site_rolled_back_verified');transition.assert_no_actions(value)
                self.assertEqual(site.parent_records(f),parents)
                value=UpdateTransaction().rollback(f);self.assertEqual(value['status'],'update_rolled_back_verified');transition.assert_no_actions(value)
                value=RollbackTransaction().execute(f);self.assertEqual(value['status'],'rolled_back_verified');transition.assert_no_actions(value)
                f.restored();self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new)
                self.assertEqual(transition.proof_records(f),proof);self.assertFalse(f.site_private.exists())
                for path,expected in records.items():self.assertEqual((UpdateTransaction().stamp(Path(path)),Path(path).read_bytes()),expected)
                data['download'].assert_not_called()


class Receipt(unittest.TestCase):
    def test_workflow_stays_existing_locked_carrier(self):
        value=(ROOT/m.WORKFLOW).read_text()
        self.assertIn("format('/install-search3-public {0} public-search3-20261120-v1', github.sha)",value)
        block=value.split('  public-install:\n',1)[1].split('  production-readiness-contracts:',1)[0]
        for text in ['deploy-anytoour-production','cancel-in-progress: false','github.run_attempt == 1',"github.event.issue.number == 4709",'ANYTOOUR_DEPLOY_SSH_KEY','tests/anytoour_production_deploy_guard_test.py']: self.assertIn(text,block)
        self.assertIn("format('/rollback-search3-public {0} ', github.sha)", block)
        self.assertIn("format('/continue-search3-public {0} public-search3-20261121-after-44840666320-v1', github.sha)", block)
        self.assertIn("format('/update-search3-public {0} public-search3-flight-return-20261121-v1', github.sha)", block)
        self.assertIn("format('/rollback-search3-public-update {0} ', github.sha)", block)
        self.assertIn("' public-search3-flight-return-rollback-20261121-v1'", block)
        self.assertIn("&& 'update-rollback' ||", block)
        self.assertIn("&& 'update' ||", block)
        self.assertIn("format('/update-site-shared-assets {0} site-shared-assets-20261121-v1', github.sha)",block)
        self.assertIn("format('/rollback-site-shared-assets {0} ', github.sha)",block)
        self.assertIn("' site-shared-assets-rollback-20261121-v1'",block)
        self.assertIn("&& 'site-update' ||",block)
        self.assertIn("&& 'site-rollback' ||",block)
        self.assertIn("format('/inspect-site-shared-assets {0} site-shared-assets-drift-44897684408-v1', github.sha)",block)
        self.assertIn("&& 'site-inspect' ||",block)
        self.assertIn("format('/continue-site-shared-assets {0} site-shared-assets-after-44897684408-20261121-v1', github.sha)",block)
        self.assertIn("format('/rollback-site-shared-assets-continuation {0} ', github.sha)",block)
        self.assertIn("' site-shared-assets-after-44897684408-rollback-20261121-v1'",block)
        self.assertIn("&& 'site-continue' ||",block)
        for mode in ('refactor','refactor-rollback'):
            self.assertEqual(m.command_mode([mode]),mode)
            with self.assertRaises(RuntimeError):m.command_mode([mode,'arbitrary'])
        self.assertIn("format('/update-search3-refactor {0} public-search3-refactor-20261121-v1', github.sha)",block)
        self.assertIn("format('/rollback-search3-refactor {0} ', github.sha)",block)
        self.assertIn("' public-search3-refactor-rollback-20261121-v1'",block)
        self.assertIn("&& 'refactor' ||",block)
        self.assertIn("&& 'refactor-rollback' ||",block)
        self.assertIn("&& 'site-continue-rollback' ||",block)
        self.assertIn("&& 'continue' ||", block)
        self.assertIn("&& 'rollback' ||", block)
        self.assertNotIn('${{ github.event.comment.body }}', block)
        self.assertNotIn('workflow_dispatch:',value)


"""Append these new tests to the existing publisher tests after owned integration.

The separate local harness supplies immutable existing fixtures and draft paths;
the production test proposal uses the existing unittest and native filesystem
fixtures. It never downloads code, contacts production, or changes shared files.
"""

REFACTOR_IDENTITY={'control_source_sha':CONTROL,'run_id':'923456','command_comment_id':9011}
REFACTOR_ROLLBACK_IDENTITY={'control_source_sha':'d'*40,'run_id':'934567','command_comment_id':9012}


def refactor_test_event(rollback=False):
    value=event();identity=REFACTOR_ROLLBACK_IDENTITY if rollback else REFACTOR_IDENTITY
    body=(f'{m.REFACTOR_ROLLBACK_PREFIX} {identity["control_source_sha"]} {REFACTOR_IDENTITY["run_id"]} {m.REFACTOR_ROLLBACK_OPERATION}'
          if rollback else f'{m.REFACTOR_PREFIX} {identity["control_source_sha"]} {m.REFACTOR_OPERATION}')
    value['comment'].update(id=identity['command_comment_id'],body=body)
    return value


class RefactorTransaction(unittest.TestCase):
    @contextlib.contextmanager
    def installed(self,tv=False):
        site=SiteContinuationTransaction()
        with site.inspected() as f:
            f.continuation_accepted=site.execute(f)
            self.assertEqual(f.continuation_accepted['status'],'site_updated_verified')
            value={**SITE_CONTINUATION_IDENTITY,'operation_id':m.SITE_CONTINUATION_OPERATION,'source_sha':m.SITE_SOURCE,
                'manifest_sha256':f.continuation_policy['manifest'],'read_only':False,'phase':'complete','ssh_started':True,
                'status':'site_updated_verified','parent_update':m.site_parent_identity(),'parent_evidence':f.site_parent_value,
                'continuation_evidence':f.continuation_evidence,'collector_evidence':m.site_continuation_collector_value(),
                'remote':f.continuation_accepted}
            data=(json.dumps(value,sort_keys=True)+'\n').encode();stream=io.BytesIO()
            with zipfile.ZipFile(stream,'w') as z:z.writestr('evidence.json',data)
            f.refactor_parent=value;f.refactor_parent_raw=stream.getvalue()
            pin=m.REFACTOR_PARENT|SITE_CONTINUATION_IDENTITY|{'receipt_sha256':m.sha(data),'artifact_sha256':m.sha(f.refactor_parent_raw),
                'snapshot_manifest_sha256':f.continuation_accepted['snapshot_manifest_sha256'],'policy_sha256':f.continuation_policy['manifest']}
            targets={};files={}
            for path in m.REFACTOR_ORDER:
                before=(f.root/path).read_bytes();after=('// reviewed refactor '+path+'\n').encode()
                row=lambda raw:{'state':'present','mode':'0644','size':len(raw),'sha256':m.sha(raw)}
                targets[path]={'before':row(before),'after':row(after)};files[path]=base64.b64encode(after).decode()
            with patch.object(m,'REFACTOR_PARENT',pin),patch.object(m,'REFACTOR_TARGETS',targets):
                f.refactor_policy=m.refactor_policy();f.refactor_files=files
                f.refactor_private=f.private.parent/m.REFACTOR_OPERATION
                f.refactor_rollback_private=f.private.parent/m.REFACTOR_ROLLBACK_OPERATION
                if tv:
                    with TvSuccessorTransaction().successor(f):yield f
                else:yield f

    def execute(self,f,change=None):
        kwargs={'tv_successor':copy.deepcopy(f.tv_evidence)} if hasattr(f,'tv_evidence') else {}
        program=m.refactor_remote_program(f.refactor_policy,f.refactor_files,REFACTOR_IDENTITY,f.refactor_parent,**kwargs)
        result=UpdateTransaction().run_program(f,program,change);m.valid_refactor_result(REFACTOR_IDENTITY,result)
        return result

    def rollback(self,f,change=None):
        kwargs={'tv_successor':copy.deepcopy(f.tv_evidence)} if hasattr(f,'tv_evidence') else {}
        program=m.refactor_rollback_remote_program(f.refactor_policy,REFACTOR_ROLLBACK_IDENTITY,REFACTOR_IDENTITY,
                    f.refactor_accepted,f.refactor_parent,**kwargs)
        result=UpdateTransaction().run_program(f,program,change)
        m.valid_refactor_result(REFACTOR_ROLLBACK_IDENTITY,result,REFACTOR_IDENTITY,f.refactor_accepted)
        return result

    def public(self,f):return {p:UpdateTransaction().stamp(f.root/p) for p in (*f.policy['expected'],*m.SITE_ORDER)}

    def test_exact_two_file_roundtrip_preserves_all_original_inodes_and_full_recovery_chain(self):
        for tv in (False,True):
            with self.subTest(tv=tv),self.installed(tv) as f:
                before=self.public(f);parents=SiteTransaction().parent_records(f)
                site_proof={str(p.relative_to(f.continuation_private)):(UpdateTransaction().stamp(p),p.read_bytes())
                            for p in f.continuation_private.rglob('*') if p.is_file()}
                tv_proof=TvSuccessorTransaction().proof_records(f) if tv else None
                f.refactor_accepted=self.execute(f)
                self.assertEqual(f.refactor_accepted['status'],'refactor_updated_verified')
                self.assertEqual(f.refactor_accepted['written_files'],list(m.REFACTOR_ORDER))
                self.assertEqual({p for p in before if self.public(f)[p]!=before[p]},set(m.REFACTOR_ORDER))
                restored=self.rollback(f);self.assertEqual(restored['status'],'refactor_rolled_back_verified')
                self.assertEqual(restored['rollback_files'],list(reversed(m.REFACTOR_ORDER)))
                self.assertEqual(self.public(f),before);self.assertEqual(SiteTransaction().parent_records(f),parents)
                self.assertEqual({str(p.relative_to(f.continuation_private)):(UpdateTransaction().stamp(p),p.read_bytes())
                                  for p in f.continuation_private.rglob('*') if p.is_file()},site_proof)
                self.assertEqual(SiteContinuationTransaction().rollback(f)['status'],'site_rolled_back_verified')
                self.assertEqual(UpdateTransaction().rollback(f)['status'],'update_rolled_back_verified')
                self.assertEqual(RollbackTransaction().execute(f)['status'],'rolled_back_verified');f.restored()
                if tv:
                    self.assertEqual((f.root/f.tv_target).read_bytes(),f.tv_new)
                    self.assertEqual(TvSuccessorTransaction().proof_records(f),tv_proof)


    def test_preflight_drift_and_existing_namespace_are_zero_write(self):
        for kind in ('target-bytes','target-inode','site-inode','site-stage-inode','parent','protected','failed-namespace','own-namespace'):
            with self.subTest(kind=kind),self.installed() as f:
                if kind=='target-bytes':(f.root/m.REFACTOR_ORDER[0]).write_bytes(b'foreign target')
                elif kind in ('target-inode','site-inode','site-stage-inode'):
                    path=(f.root/m.REFACTOR_ORDER[0] if kind=='target-inode' else
                          f.root/m.SITE_ORDER[0] if kind=='site-inode' else f.continuation_private/'stage/0')
                    previous=path.stat();tmp=path.with_name(path.name+'.foreign');tmp.write_bytes(path.read_bytes());tmp.chmod(stat.S_IMODE(previous.st_mode))
                    os.utime(tmp,ns=(previous.st_atime_ns,previous.st_mtime_ns));tmp.replace(path)
                elif kind=='parent':(f.continuation_private/'result.json').write_text('{}')
                elif kind=='protected':(f.root/'data/db-v1.php').write_bytes(b'not the corrected DB helper')
                elif kind=='failed-namespace':f.site_private.mkdir(mode=0o700)
                else:f.refactor_private.mkdir(mode=0o700)
                before=f.filesystem_snapshot();value=self.execute(f)
                self.assertEqual(value['status'],'blocked_no_replay');self.assertEqual(value['written_files'],[])
                self.assertFalse(value['reservation_created']);self.assertEqual(f.filesystem_snapshot(),before)

    def test_each_failed_exchange_unwinds_only_own_two_files_and_cannot_repeat(self):
        for index in range(2):
            with self.subTest(index=index),self.installed(True) as f:
                before=self.public(f);parents=SiteTransaction().parent_records(f)
                def fail(program):
                    line="   exchange(src,name,dst,target);switched.append(path);result['written_files'].append(path);sync(src);sync(dst)"
                    self.assertIn(line,program)
                    return program.replace(line,line+f"\n   if index=={index}: raise RuntimeError('operation_failed')",1)
                value=self.execute(f,fail)
                self.assertEqual(value['status'],'rolled_back_no_replay')
                self.assertEqual(value['rollback_files'],list(reversed(m.REFACTOR_ORDER[:index+1])))
                self.assertEqual(self.public(f),before);self.assertEqual(SiteTransaction().parent_records(f),parents)
                snapshot=f.filesystem_snapshot();again=self.execute(f)
                self.assertEqual(again['status'],'blocked_no_replay');self.assertEqual(f.filesystem_snapshot(),snapshot)

    def test_after_parent_validation_never_adopts_foreign_target_inodes_as_preimages(self):
        for tv in (False,True):
            for target in m.REFACTOR_ORDER:
                with self.subTest(tv=tv,target=target),self.installed(tv) as f:
                    path=f.root/target;before=self.public(f);parents=SiteTransaction().parent_records(f)
                    def race(program):
                        self.assertIn('def refactor_site_parent():',program)
                        program=program.replace('def refactor_site_parent():','def fixture_checked_refactor_site_parent():',1)
                        wrapper=('fixture_parent_checked=False\ndef refactor_site_parent():\n'
                            ' global fixture_parent_checked\n value=fixture_checked_refactor_site_parent()\n'
                            ' if not fixture_parent_checked:\n  fixture_parent_checked=True\n'
                            +TvSuccessorTransaction().replace_same_bytes(path,indent='  ')+' return value\n')
                        return program.replace('def refactor_check_ancestors():',wrapper+'def refactor_check_ancestors():',1)
                    value=self.execute(f,race)
                    self.assertEqual(value['status'],'blocked_no_replay')
                    self.assertEqual(value['written_files'],[]);self.assertEqual(value['rollback_files'],[])
                    self.assertFalse(value['reservation_created']);self.assertFalse(f.refactor_private.exists())
                    self.assertEqual(m.sha(path.read_bytes()),m.REFACTOR_TARGETS[target]['before']['sha256'])
                    self.assertNotEqual(self.public(f)[target],before[target])
                    self.assertEqual({p:v for p,v in self.public(f).items() if p!=target},{p:v for p,v in before.items() if p!=target})
                    self.assertEqual(SiteTransaction().parent_records(f),parents)

    def test_foreign_same_bytes_inode_before_or_after_exchange_is_preserved(self):
        for timing in ('before','after'):
            with self.subTest(timing=timing),self.installed() as f:
                path=f.root/m.REFACTOR_ORDER[1];before=self.public(f)
                def race(program):
                    if timing=='before':
                        line="   exchange(src,name,dst,target);switched.append(path);result['written_files'].append(path);sync(src);sync(dst)"
                        injection="   if index==1:\n"+TvSuccessorTransaction().replace_same_bytes(path,indent='    ')
                        return program.replace(line,injection+line,1)
                    line='  observed=public.read(path)[:2]'
                    injection="  if index==1:\n"+TvSuccessorTransaction().replace_same_bytes(path,indent='   ')
                    return program.replace(line,injection+line,1)
                value=self.execute(f,race)
                self.assertEqual(value['status'],'unknown_no_replay')
                self.assertNotEqual(UpdateTransaction().stamp(path),before[m.REFACTOR_ORDER[1]])
                desired=m.REFACTOR_TARGETS[m.REFACTOR_ORDER[1]]['before' if timing=='before' else 'after']['sha256']
                self.assertEqual(m.sha(path.read_bytes()),desired)
                self.assertEqual(self.public(f)[m.REFACTOR_ORDER[0]],before[m.REFACTOR_ORDER[0]])

    def test_ancestor_protected_and_int_same_bytes_races_stop_all_further_writes(self):
        for kind in ('site-record','protected','int-record','failed-namespace'):
            with self.subTest(kind=kind),self.installed(True) as f:
                def race(program):
                    line="   exchange(src,name,dst,target);switched.append(path);result['written_files'].append(path);sync(src);sync(dst)"
                    if kind=='failed-namespace':
                        injection="   if index==0: os.mkdir(SITE_POLICY['continuation']['failed']['operation_id'],0o700,dir_fd=base.fd)\n"
                    else:
                        path=(f.continuation_private/'result.json' if kind=='site-record' else
                              f.root/'data/db-v1.php' if kind=='protected' else f.tv_namespace/'result.json')
                        injection="   if index==0:\n"+TvSuccessorTransaction().replace_same_bytes(path,indent='    ')
                    return program.replace(line,line+'\n'+injection.rstrip('\n'),1)
                value=self.execute(f,race)
                self.assertEqual(value['status'],'unknown_no_replay');self.assertEqual(value['written_files'],[m.REFACTOR_ORDER[0]])
                self.assertEqual(value['rollback_files'],[])
                before=f.filesystem_snapshot();again=self.execute(f)
                self.assertEqual(again['status'],'blocked_no_replay');self.assertEqual(f.filesystem_snapshot(),before)

    def test_own_rollback_rejects_target_or_retained_proof_replacement_without_reservation(self):
        for kind in ('target-inode','stage-inode','site-record-inode','own-result','ancestor-record'):
            with self.subTest(kind=kind),self.installed(True) as f:
                f.refactor_accepted=self.execute(f);self.assertEqual(f.refactor_accepted['status'],'refactor_updated_verified')
                if kind=='own-result':(f.refactor_private/'result.json').write_text('{}')
                elif kind=='ancestor-record':(f.update_private/'intent.json').write_text('{}')
                else:
                    path=(f.root/m.REFACTOR_ORDER[0] if kind=='target-inode' else
                          f.refactor_private/'stage/0' if kind=='stage-inode' else f.continuation_private/'result.json')
                    old=path.stat();tmp=path.with_name(path.name+'.foreign');tmp.write_bytes(path.read_bytes());tmp.chmod(stat.S_IMODE(old.st_mode))
                    os.utime(tmp,ns=(old.st_atime_ns,old.st_mtime_ns));tmp.replace(path)
                before=f.filesystem_snapshot();result=self.rollback(f)
                self.assertEqual(result['status'],'blocked_no_replay');self.assertFalse(f.refactor_rollback_private.exists())
                self.assertEqual(f.filesystem_snapshot(),before)

    def test_rollback_each_interruption_is_sealed_unknown_and_cannot_repeat(self):
        for index in (1,0):
            with self.subTest(index=index),self.installed(True) as f:
                f.refactor_accepted=self.execute(f)
                def fail(program):
                    line="  result['rollback_files'].append(path)"
                    self.assertIn(line,program)
                    return program.replace(line,line+f"\n  if index=={index}: raise RuntimeError('operation_failed')",1)
                value=self.rollback(f,fail)
                self.assertEqual(value['status'],'unknown_no_replay')
                self.assertEqual(value['rollback_files'],list(reversed(m.REFACTOR_ORDER[index:])))
                before=f.filesystem_snapshot();again=self.rollback(f)
                self.assertEqual(again['status'],'blocked_no_replay');self.assertEqual(f.filesystem_snapshot(),before)

    def test_successful_own_artifact_supports_recovery_without_historical_network_and_rejects_aliases(self):
        with self.installed(True) as f:
            f.refactor_accepted=self.execute(f)
            value={**REFACTOR_IDENTITY,'operation_id':m.REFACTOR_OPERATION,'source_sha':m.REFACTOR_SOURCE,
                'manifest_sha256':m.REFACTOR_MANIFEST_SHA,'read_only':False,'phase':'complete','ssh_started':True,
                'status':'refactor_updated_verified','parent_site':m.refactor_parent_identity(),
                'parent_evidence':f.refactor_parent,'remote':f.refactor_accepted}
            def archive(v):
                stream=io.BytesIO()
                with zipfile.ZipFile(stream,'w') as z:z.writestr('evidence.json',(json.dumps(v,sort_keys=True)+'\n').encode())
                return stream.getvalue()
            with patch.object(m,'api',side_effect=AssertionError('historical network during recovery')):
                policy,accepted,parent=m.refactor_source_evidence(archive(value),REFACTOR_IDENTITY)
            self.assertEqual((policy,accepted,parent),(f.refactor_policy,f.refactor_accepted,f.refactor_parent))
            for mutate in (lambda v:v.update(operation_id=m.SITE_CONTINUATION_OPERATION),
                lambda v:v.update(status='unknown_no_replay'),lambda v:v['remote'].update(operation_id=m.UPDATE_OPERATION),
                lambda v:v['remote'].update(written_files=[m.REFACTOR_ORDER[1]]),
                lambda v:v['remote'].update(parent_receipt_sha256='0'*64),
                lambda v:v['remote'].update(protected_unchanged=False),lambda v:v['parent_evidence'].update(run_id=m.SITE_INSPECT_FAILED['run_id'])):
                bad=copy.deepcopy(value);mutate(bad)
                with self.subTest(mutate=mutate),self.assertRaises(RuntimeError):m.refactor_source_evidence(archive(bad),REFACTOR_IDENTITY)
            for mutate in (lambda v:v.update(lead_calls=1),lambda v:v.update(database_reads=True),
                lambda v:v.update(failed_namespace_absent=False),lambda v:v.update(rollback_files=list(m.REFACTOR_ORDER)),
                lambda v:v.update(snapshot_manifest_sha256=None),lambda v:v.update(unreviewed=True)):
                bad=copy.deepcopy(f.refactor_accepted);mutate(bad)
                with self.subTest(mutate=mutate),self.assertRaises(RuntimeError):m.valid_refactor_result(REFACTOR_IDENTITY,bad)


class RefactorPayload(unittest.TestCase):
    def archive(self,f,change=None):
        files={p:(f.root/p).read_bytes() for p in (*m.ADDITIONS,m.ENTRY)}
        files.update({p:base64.b64decode(v) for p,v in f.refactor_files.items()})
        rows=[{'path':p,'served':{'size':len(v),'mode':'0644','sha256':m.sha(v)}} for p,v in files.items()]
        rows[-1]['before']={k:v for k,v in m.ENTRY_BEFORE.items() if k!='state'}
        manifest={k:None for k in m.REFACTOR_CONTRACT_KEYS}
        manifest.update(schema_version=1,purpose='local_public_live_install_candidate',deployable=False,
            profile={'schemaVersion':1,'profile':'live','scenario':'family'},main={'sha':m.BUILD_MAIN},
            candidate={'sha':m.REFACTOR_SOURCE,'tree_sha':m.REFACTOR_TREE},
            files={'added':rows[:-1],'replaced':rows[-1:],'protected':[{'path':'fixed/'+str(i),'sha256':'a'*64} for i in range(733)]})
        contract={k:manifest[k] for k in m.REFACTOR_CONTRACT_KEYS};contract['protected']=manifest['files']['protected']
        contract_sha=m.sha(json.dumps(contract,sort_keys=True,separators=(',',':')).encode())
        if change:change(manifest,files)
        manifest_raw=json.dumps(manifest,sort_keys=True).encode();stream=io.BytesIO()
        with zipfile.ZipFile(stream,'w') as archive:
            archive.writestr('manifest.json',manifest_raw)
            for path,data in files.items():archive.writestr('overlay/'+path,data)
        return stream.getvalue(),m.sha(manifest_raw),contract_sha

    def test_full_fixed_overlay_yields_exactly_two_payloads_and_rejects_scope_or_contract_drift(self):
        with RefactorTransaction().installed() as f:
            raw,digest,contract=self.archive(f)
            with patch.object(m,'REFACTOR_ARTIFACT_SHA',m.sha(raw)),patch.object(m,'REFACTOR_MANIFEST_SHA',digest),patch.object(m,'REFACTOR_CONTRACT_SHA',contract):
                policy,files=m.refactor_package(raw,f.refactor_parent)
                self.assertEqual(set(files),set(m.REFACTOR_ORDER));self.assertEqual(files,f.refactor_files)
                with self.assertRaises(RuntimeError):m.refactor_package(raw+b'tamper',f.refactor_parent)
            def changed_unaffected(manifest,files):
                path=next(p for p in m.ADDITIONS if p not in m.REFACTOR_ORDER);files[path]=b'foreign unaffected file'
                row=next(v for v in manifest['files']['added'] if v['path']==path)
                row['served']={'size':len(files[path]),'mode':'0644','sha256':m.sha(files[path])}
            changes=(lambda v,b:v.update(candidate={'sha':m.UPDATE_SOURCE,'tree_sha':m.UPDATE_TREE}),
                lambda v,b:v.update(graph={'unreviewed':'entry'}),lambda v,b:v['files']['protected'].pop(),
                lambda v,b:v['files']['protected'][0].update(sha256='0'*64),changed_unaffected,
                lambda v,b:b.pop(m.REFACTOR_ORDER[0]),lambda v,b:b.update({'foreign.js':b'extra'}),
                lambda v,b:b.update({m.REFACTOR_ORDER[1]:b'wrong candidate bytes'}),
                lambda v,b:v['files']['added'][0]['served'].update(mode='0755'))
            for change in changes:
                raw,digest,contract=self.archive(f,change)
                with self.subTest(change=change),patch.object(m,'REFACTOR_ARTIFACT_SHA',m.sha(raw)), \
                     patch.object(m,'REFACTOR_MANIFEST_SHA',digest),patch.object(m,'REFACTOR_CONTRACT_SHA',contract),self.assertRaises((RuntimeError,KeyError)):
                    m.refactor_package(raw,f.refactor_parent)
            for files in ({},f.refactor_files|{m.ENTRY:base64.b64encode(b'foreign entry').decode()}):
                with self.assertRaises(RuntimeError):m.refactor_remote_program(f.refactor_policy,files,REFACTOR_IDENTITY,f.refactor_parent)
            for changed in (f.refactor_policy|{'order':m.SITE_ORDER},f.refactor_policy|{'operation':m.SITE_CONTINUATION_OPERATION}):
                with self.assertRaises(RuntimeError):m.refactor_remote_program(changed,f.refactor_files,REFACTOR_IDENTITY,f.refactor_parent)


class RefactorAdmission(unittest.TestCase):
    def metadata(self,path):
        owner={'user':{'id':226193297},'author_association':'OWNER'}
        repo={'full_name':m.REPO}
        if path=='/git/ref/heads/main':return {'object':{'sha':CONTROL}}
        if path=='/git/ref/heads/release/search3-production-ready-v1':return {'object':{'sha':m.REFACTOR_RELEASE}}
        if path=='/pulls/'+str(m.REFACTOR_SOURCE_PR):
            return {'state':'closed','merged':True,'merge_commit_sha':m.REFACTOR_SOURCE_MERGE,
                'head':{'sha':m.REFACTOR_SOURCE,'repo':repo},'base':{'ref':'release/search3-production-ready-v1','repo':repo}}
        if path=='/pulls/5140':
            return {'state':'closed','merged':True,'merge_commit_sha':m.REFACTOR_RELEASE,
                'head':{'sha':m.REFACTOR_COMPILER_HEAD,'repo':repo},
                'base':{'sha':m.REFACTOR_SOURCE_MERGE,'ref':'release/search3-production-ready-v1','repo':repo}}
        for commit,tree in ((m.REFACTOR_SOURCE,m.REFACTOR_TREE),(m.REFACTOR_SOURCE_MERGE,m.REFACTOR_TREE),(m.REFACTOR_RELEASE,m.REFACTOR_RELEASE_TREE)):
            if path=='/git/commits/'+commit:return {'tree':{'sha':tree},'parents':[{'sha':m.REFACTOR_SOURCE_MERGE},{'sha':m.REFACTOR_COMPILER_HEAD}]}
        if path.startswith('/contents/.github/workflows/deploy-anytoour.yml?'):
            return {'type':'file','encoding':'base64','content':base64.b64encode(b'guarded manual publisher').decode()}
        if path.startswith('/commits/'+m.REFACTOR_SOURCE+'/check-runs?'):
            return {'total_count':len(m.SOURCE_CHECK_NAMES),'check_runs':[{'id':i+1,'name':n,'head_sha':m.REFACTOR_SOURCE,
                    'status':'completed','conclusion':'success','app':{'slug':'github-actions'},'check_suite':{'id':i}}
                    for i,n in enumerate(m.SOURCE_CHECK_NAMES)]}
        parent=m.REFACTOR_PARENT
        if path in ('/actions/runs/'+str(m.REFACTOR_BUILD_RUN),'/actions/runs/'+parent['run_id']):
            build=path.endswith(str(m.REFACTOR_BUILD_RUN))
            return {'id':m.REFACTOR_BUILD_RUN if build else int(parent['run_id']),
                'head_sha':m.REFACTOR_SOURCE if build else parent['control_source_sha'],
                'head_branch':'refactor/search3-passive-card-selection-20261121' if build else 'main',
                'path':'.github/workflows/search3-visual-migration.yml' if build else m.WORKFLOW,
                'event':'pull_request' if build else 'issue_comment','run_attempt':1,'status':'completed','conclusion':'success',
                'actor':{'login':'pyatkoff'},'triggering_actor':{'login':'pyatkoff'},'repository':repo,'head_repository':repo,'pull_requests':[]}
        if path.startswith('/actions/runs/'+parent['run_id']+'/attempts/1/jobs?'):
            return {'total_count':1,'jobs':[{'id':parent['job_id'],'run_id':int(parent['run_id']),'name':'public-install',
                'status':'completed','conclusion':'success','steps':[{'name':'Run python3 scripts/deploy/search3_public_install.py site-continue',
                'status':'completed','conclusion':'success'}]}]}
        if path in ('/actions/artifacts/'+str(m.REFACTOR_ARTIFACT),'/actions/artifacts/'+str(parent['artifact_id'])):
            build=path.endswith(str(m.REFACTOR_ARTIFACT))
            return {'id':m.REFACTOR_ARTIFACT if build else parent['artifact_id'],
                'name':'search3-public-live-'+m.REFACTOR_SOURCE if build else 'search3-public-install-'+parent['run_id']+'-1',
                'expired':False,'digest':'sha256:'+(m.REFACTOR_ARTIFACT_SHA if build else parent['artifact_sha256']),
                'workflow_run':{'id':m.REFACTOR_BUILD_RUN if build else int(parent['run_id']),'head_sha':m.REFACTOR_SOURCE if build else parent['control_source_sha']}}
        if path.startswith('/issues/4709/comments?'):
            return [refactor_test_event()['comment'],
                owner|{'id':parent['command_comment_id'],'body':f'{m.SITE_CONTINUATION_PREFIX} {parent["control_source_sha"]} {m.SITE_CONTINUATION_OPERATION}'},
                owner|{'id':parent['terminal_comment_id'],'body':'site_updated_verified '+' '.join(str(parent[k]) for k in
                    ('run_id','artifact_id','artifact_sha256','receipt_sha256','snapshot_manifest_sha256'))}]
        raise AssertionError('unexpected refactor source request: '+path)

    def admit(self,change=None):
        def api(path):
            value=self.metadata(path);return change(path,value) if change else value
        with patch.object(m,'api',side_effect=api),patch.object(m,'reviewed_control') as reviewed, \
             patch.object(m,'SITE_PUBLISHER_GUARD_SHA',m.sha(b'guarded manual publisher')), \
             patch.object(m.carrier,'run',side_effect=AssertionError('server during admission')):
            m.refactor_admission(REFACTOR_IDENTITY)
            reviewed.assert_called_once_with(CONTROL)

    def test_fixed_owner_created_event_current_main_and_original_attempt_only(self):
        for rollback,identity in ((False,REFACTOR_IDENTITY),(True,REFACTOR_ROLLBACK_IDENTITY)):
            env=environment()|{'GITHUB_SHA':identity['control_source_sha'],'GITHUB_RUN_ID':identity['run_id']}
            self.assertEqual(m.refactor_event_identity(refactor_test_event(rollback),env,rollback),
                             (identity,REFACTOR_IDENTITY['run_id']) if rollback else identity)
            for key,value in (('GITHUB_ACTOR','other'),('GITHUB_REF','refs/heads/topic'),('GITHUB_RUN_ATTEMPT','2'),('GITHUB_EVENT_NAME','workflow_dispatch')):
                with self.subTest(key=key),self.assertRaises(RuntimeError):m.refactor_event_identity(refactor_test_event(rollback),env|{key:value},rollback)
            for change in (lambda e:e.update(action='edited'),lambda e:e['comment'].update(author_association='MEMBER'),
                lambda e:e['comment'].update(body=e['comment']['body']+' arbitrary.js hash'),lambda e:e['issue'].update(pull_request={}),
                lambda e:e['comment'].update(body=site_continuation_event(rollback)['comment']['body'])):
                value=refactor_test_event(rollback);change(value)
                with self.assertRaises(RuntimeError):m.refactor_event_identity(value,env,rollback)

    def test_fixed_source_release_artifact_parent_and_unique_command_are_required(self):
        self.admit()
        mutations=[
            ('/git/ref/heads/main',lambda v:v['object'].update(sha='f'*40)),
            ('/git/ref/heads/release/search3-production-ready-v1',lambda v:v['object'].update(sha=m.REFACTOR_SOURCE_MERGE)),
            ('/pulls/'+str(m.REFACTOR_SOURCE_PR),lambda v:v.update(merge_commit_sha=m.REFACTOR_RELEASE)),
            ('/pulls/5140',lambda v:v['base'].update(sha='f'*40)),
            ('/git/commits/'+m.REFACTOR_RELEASE,lambda v:v.update(tree={'sha':m.REFACTOR_TREE})),
            ('/git/commits/'+m.REFACTOR_RELEASE,lambda v:v.update(parents=[{'sha':m.REFACTOR_SOURCE_MERGE}])),
            ('/check-runs?',lambda v:v['check_runs'][0].update(conclusion='failure')),
            ('/check-runs?',lambda v:v['check_runs'].append(copy.deepcopy(v['check_runs'][0]))),
            ('/actions/runs/'+str(m.REFACTOR_BUILD_RUN),lambda v:v.update(run_attempt=2)),
            ('/actions/runs/'+str(m.REFACTOR_BUILD_RUN),lambda v:v.update(head_sha=m.UPDATE_SOURCE)),
            ('/actions/runs/'+str(m.REFACTOR_BUILD_RUN),lambda v:v.update(path='.github/workflows/build-preview.yml')),
            ('/actions/runs/'+m.REFACTOR_PARENT['run_id'],lambda v:v.update(status='in_progress')),
            ('/actions/runs/'+m.REFACTOR_PARENT['run_id'],lambda v:v.update(conclusion='failure')),
            ('/actions/runs/'+m.REFACTOR_PARENT['run_id'],lambda v:v.update(run_attempt=True)),
            ('/attempts/1/jobs?',lambda v:v['jobs'][0]['steps'][0].update(name='Run python3 scripts/deploy/search3_public_install.py site-update')),
            ('/actions/artifacts/'+str(m.REFACTOR_ARTIFACT),lambda v:v.update(digest='sha256:'+'0'*64)),
            ('/actions/artifacts/'+str(m.REFACTOR_PARENT['artifact_id']),lambda v:v.update(expired=True)),
            ('/comments?',lambda v:v.append(v[0]|{'id':99999})),
            ('/comments?',lambda v:v.append(v[1]|{'id':99999})),
            ('/comments?',lambda v:v[2].update(body='unrelated terminal'))]
        for part,mutate in mutations:
            def change(path,value):
                if path==part or part.endswith('?') and part in path:mutate(value)
                return value
            with self.subTest(part=part,mutate=mutate),self.assertRaises(RuntimeError):self.admit(change)


class RefactorRollbackAdmission(unittest.TestCase):
    def metadata(self,path):
        if path=='/git/ref/heads/main':return {'object':{'sha':REFACTOR_ROLLBACK_IDENTITY['control_source_sha']}}
        if path.startswith('/issues/4709/comments?'):return [refactor_test_event()['comment'],refactor_test_event(True)['comment']]
        run=REFACTOR_IDENTITY['run_id']
        if path=='/actions/runs/'+run:
            return {'id':int(run),'head_sha':CONTROL,'head_branch':'main','path':m.WORKFLOW,'event':'issue_comment',
                'run_attempt':1,'status':'completed','conclusion':'success','actor':{'login':'pyatkoff'},'triggering_actor':{'login':'pyatkoff'}}
        if path.startswith('/actions/runs/'+run+'/attempts/1/jobs?'):
            return {'total_count':1,'jobs':[{'id':556,'run_id':int(run),'name':'public-install','status':'completed','conclusion':'success',
                'steps':[{'name':'Run python3 scripts/deploy/search3_public_install.py refactor','status':'completed','conclusion':'success'}]}]}
        if path.startswith('/actions/runs/'+run+'/artifacts?'):
            return {'total_count':1,'artifacts':[{'id':555,'name':'search3-public-install-'+run+'-1','expired':False,
                'workflow_run':{'id':int(run)},'digest':'sha256:'+'5'*64}]}
        raise AssertionError('historical source dependency during refactor recovery: '+path)

    def admit(self,change=None):
        def api(path):
            value=self.metadata(path);return change(path,value) if change else value
        with patch.object(m,'api',side_effect=api),patch.object(m,'reviewed_control') as reviewed, \
             patch.object(m,'refactor_admission',side_effect=AssertionError('source during recovery')), \
             patch.object(m,'refactor_parent_admission',side_effect=AssertionError('parent API during recovery')):
            original,artifact=m.refactor_rollback_admission(REFACTOR_ROLLBACK_IDENTITY,REFACTOR_IDENTITY['run_id'])
            self.assertEqual(original,REFACTOR_IDENTITY);self.assertEqual(artifact['id'],555)
            reviewed.assert_called_once_with(REFACTOR_ROLLBACK_IDENTITY['control_source_sha'])

    def test_only_successful_own_refactor_can_recover_independently_of_release_and_old_artifacts(self):
        self.admit()
        for run in (m.REFACTOR_PARENT['run_id'],m.SITE_INSPECT_FAILED['run_id'],m.SITE_PARENT['run_id'],REFACTOR_ROLLBACK_IDENTITY['run_id']):
            with patch.object(m,'api') as api,self.assertRaises(RuntimeError):m.refactor_rollback_commands(REFACTOR_ROLLBACK_IDENTITY,run)
            api.assert_not_called()
        for part,mutate in (('/comments?',lambda v:v.append(v[0]|{'id':99999})),
            ('/comments?',lambda v:v[0].update(body=site_continuation_event()['comment']['body'])),
            ('/actions/runs/'+REFACTOR_IDENTITY['run_id'],lambda v:v.update(conclusion='failure')),
            ('/actions/runs/'+REFACTOR_IDENTITY['run_id'],lambda v:v.update(status='in_progress')),
            ('/actions/runs/'+REFACTOR_IDENTITY['run_id'],lambda v:v.update(run_attempt=2)),
            ('/attempts/1/jobs?',lambda v:v['jobs'][0]['steps'][0].update(name='Run python3 scripts/deploy/search3_public_install.py update')),
            ('/artifacts?',lambda v:v['artifacts'][0].update(workflow_run={'id':int(m.REFACTOR_PARENT['run_id'])}))):
            def change(path,value):
                if path==part or part.endswith('?') and part in path:mutate(value)
                return value
            with self.subTest(part=part),self.assertRaises(RuntimeError):self.admit(change)


class RefactorClient(unittest.TestCase):
    def env(self,f,name,rollback=False):
        directory=f.base/name;directory.mkdir();event_path=directory/'event.json';event_path.write_text(json.dumps(refactor_test_event(rollback)))
        identity=REFACTOR_ROLLBACK_IDENTITY if rollback else REFACTOR_IDENTITY
        return directory,environment()|{'GITHUB_RUN_ID':identity['run_id'],'GITHUB_SHA':identity['control_source_sha'],
            'RUNNER_TEMP':str(directory),'GITHUB_EVENT_PATH':str(event_path),'PREVIEW_HOST':'example.invalid',
            'PREVIEW_USER':'fixture','PREVIEW_KEY':'PRIVATE KEY fixture'}

    def test_live_client_saves_complete_parent_and_own_recovery_avoids_historical_downloads(self):
        helper=RefactorTransaction()
        with helper.installed(True) as f:
            f.refactor_accepted=helper.execute(f);runner,env=self.env(f,'refactor-client')
            with patch.dict(os.environ,env),patch.object(m,'refactor_admission') as admission,patch.object(m,'refactor_final_heads'), \
                 patch.object(m,'download',side_effect=[f.refactor_parent_raw,b'package covered separately']) as download, \
                 patch.object(m,'refactor_package',return_value=(f.refactor_policy,f.refactor_files)), \
                 patch.object(m,'tv_successor_evidence',return_value=copy.deepcopy(f.tv_evidence)) as dependency, \
                 patch.object(m.carrier,'run',side_effect=[b'fixture known host',json.dumps(f.refactor_accepted).encode()]),patch('builtins.print'):
                m.refactor()
            self.assertEqual(admission.call_count,2);self.assertEqual(dependency.call_count,2)
            self.assertEqual([c.args for c in download.call_args_list],[(m.REFACTOR_PARENT['artifact_id'],m.REFACTOR_PARENT['artifact_sha256']),
                (m.REFACTOR_ARTIFACT,m.REFACTOR_ARTIFACT_SHA)])
            path=runner/'search3-public-install/evidence.json';value=json.loads(path.read_text())
            self.assertEqual(value['status'],'refactor_updated_verified');self.assertEqual(value['parent_evidence'],f.refactor_parent)
            self.assertFalse((path.parent/'key').exists());self.assertFalse((path.parent/'known').exists())
            packet=io.BytesIO()
            with zipfile.ZipFile(packet,'w') as archive:archive.writestr('evidence.json',path.read_bytes())
            raw=packet.getvalue();restored=helper.rollback(f);runner,env=self.env(f,'refactor-client-recovery',True)
            with contextlib.ExitStack() as stack:
                stack.enter_context(patch.dict(os.environ,env));stack.enter_context(patch('builtins.print'))
                stack.enter_context(patch.object(m,'refactor_rollback_admission',return_value=(REFACTOR_IDENTITY,{'id':555,'digest':'sha256:'+m.sha(raw)})))
                stack.enter_context(patch.object(m,'refactor_rollback_commands',return_value=REFACTOR_IDENTITY));stack.enter_context(patch.object(m,'rollback_main'))
                dependency=stack.enter_context(patch.object(m,'tv_successor_evidence',return_value=copy.deepcopy(f.tv_evidence)))
                download=stack.enter_context(patch.object(m,'download',return_value=raw))
                for name in ('api','refactor_admission','refactor_final_heads','refactor_parent_admission','refactor_parent_evidence',
                             'refactor_package','site_continuation_collector_log','signing_evidence'):
                    stack.enter_context(patch.object(m,name,side_effect=AssertionError('historical recovery dependency: '+name)))
                stack.enter_context(patch.object(m.carrier,'run',side_effect=[b'fixture known host',json.dumps(restored).encode()]))
                m.refactor(rollback=True)
                self.assertEqual(dependency.call_count,2);download.assert_called_once_with(555,m.sha(raw))
            path=runner/'search3-public-install/evidence.json';value=json.loads(path.read_text())
            self.assertEqual(value['status'],'refactor_rolled_back_verified')
            self.assertFalse((path.parent/'key').exists());self.assertFalse((path.parent/'known').exists())

    def test_unresolved_or_changed_int_proof_never_starts_ssh_in_either_mode(self):
        helper=RefactorTransaction()
        with helper.installed(True) as f:
            for rollback in (False,True):
                for stage in ('initial','recheck'):
                    with self.subTest(rollback=rollback,stage=stage),contextlib.ExitStack() as stack:
                        runner,env=self.env(f,'proof-'+str(rollback)+'-'+stage,rollback)
                        stack.enter_context(patch.dict(os.environ,env));stack.enter_context(patch('builtins.print'))
                        for name in ('refactor_admission','refactor_rollback_commands','rollback_main'):
                            stack.enter_context(patch.object(m,name))
                        stack.enter_context(patch.object(m,'refactor_rollback_admission',return_value=(REFACTOR_IDENTITY,{'id':555,'digest':'sha256:'+'5'*64})))
                        stack.enter_context(patch.object(m,'download',return_value=f.refactor_parent_raw))
                        stack.enter_context(patch.object(m,'refactor_source_evidence',return_value=(f.refactor_policy,{},f.refactor_parent)))
                        stack.enter_context(patch.object(m,'refactor_package',return_value=(f.refactor_policy,f.refactor_files)))
                        builder=stack.enter_context(patch.object(m,'refactor_rollback_remote_program' if rollback else 'refactor_remote_program',
                                                                return_value='# inert native program'))
                        sequence=([copy.deepcopy(f.tv_evidence)] if stage=='recheck' else [])+[RuntimeError('tv_successor_original_missing')]
                        resolver=stack.enter_context(patch.object(m,'tv_successor_evidence',side_effect=sequence))
                        remote=stack.enter_context(patch.object(m.carrier,'run',return_value=b'fixture known host'))
                        with self.assertRaises(RuntimeError):m.refactor(rollback)
                        self.assertEqual(resolver.call_count,1 if stage=='initial' else 2)
                        self.assertEqual([c.args[0][0] for c in remote.call_args_list],[] if stage=='initial' else ['ssh-keyscan'])
                        if stage=='initial':builder.assert_not_called()
                        else:self.assertEqual(builder.call_args.kwargs['tv_successor'],f.tv_evidence)
                        value=json.loads((runner/'search3-public-install/evidence.json').read_text())
                        self.assertEqual(value['status'],'blocked_no_replay');self.assertFalse(value['ssh_started'])




if __name__ == '__main__': unittest.main(verbosity=2)
