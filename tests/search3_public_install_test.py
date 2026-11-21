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
                     patch.object(m, 'download', return_value=b'x'), patch.object(m, 'package', return_value=({},{})), \
                     patch.object(m, 'rollback_source_evidence', return_value=installed_result()), patch.object(m, 'api', return_value=rows), \
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
                for name in ('api', 'package', 'signing_evidence', 'update_parent_evidence', 'update_admission', 'update_final_heads'):
                    stack.enter_context(patch.object(m, name, side_effect=AssertionError('unnecessary recovery dependency: '+name)))
                stack.enter_context(patch.object(m.carrier, 'run', side_effect=[b'fixture known host', json.dumps(restored).encode()]))
                stack.enter_context(patch('builtins.print'))
                m.update(rollback=True)
                download.assert_called_once_with(333, m.sha(raw))
            evidence_path = recovery/'search3-public-install/evidence.json'
            self.assertEqual(json.loads(evidence_path.read_text())['status'], 'update_rolled_back_verified')
            self.assertFalse((evidence_path.parent/'key').exists()); self.assertFalse((evidence_path.parent/'known').exists())


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
        self.assertIn("&& 'continue' ||", block)
        self.assertIn("&& 'rollback' ||", block)
        self.assertNotIn('${{ github.event.comment.body }}', block)
        self.assertNotIn('workflow_dispatch:',value)


if __name__ == '__main__': unittest.main(verbosity=2)
