#!/usr/bin/env python3
"""Offline actual-controller admission and native filesystem transaction tests."""
import base64
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


def event():
    return {'repository': {'full_name': m.REPO}, 'action': 'created', 'issue': {'number': 4709},
            'comment': {'id': 9001, 'body': f'{m.PREFIX} {CONTROL} {m.OPERATION}', 'user': {'id': 226193297}, 'author_association': 'OWNER'}}


def environment():
    return {'GITHUB_REPOSITORY': m.REPO, 'GITHUB_REF': 'refs/heads/main', 'GITHUB_WORKFLOW_REF': f'{m.REPO}/{m.WORKFLOW}@refs/heads/main',
            'GITHUB_ACTOR': 'pyatkoff', 'GITHUB_TRIGGERING_ACTOR': 'pyatkoff', 'GITHUB_EVENT_NAME': 'issue_comment',
            'GITHUB_RUN_ATTEMPT': '1', 'GITHUB_RUN_ID': IDENTITY['run_id'], 'GITHUB_SHA': CONTROL}


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


class Admission(unittest.TestCase):
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
        if path == '/pulls/5118':
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
            return [event()['comment'],{'id':m.SIGNING_RECEIPT_COMMENT,'user':{'id':226193297},'author_association':'OWNER','body':str(m.SIGNING_RUN)+' '+m.SIGNING_RESULT_SHA}]
        raise AssertionError(path)

    def call_admission(self, mutate=None):
        def api(path):
            value=self.fake_api(path)
            return mutate(path,value) if mutate else value
        with patch.object(m,'RELEASE',RELEASE),patch.object(m,'PUBLISHER_GUARD_SHA',m.sha(b'guarded publisher')),patch.object(m,'reviewed_control'),patch.object(m,'api',side_effect=api):
            m.admission(IDENTITY)

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
            if path == '/pulls/5118': value['merged']=False
            return value
        with self.assertRaisesRegex(RuntimeError,'candidate_moved'): self.call_admission(unmerged)

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

    def tearDown(self):
        self.base.chmod(0o700);self.home.chmod(0o700);self.temp.cleanup()

    def put(self,path,raw):
        target=self.root/path;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(raw);target.chmod(0o644)

    def execute(self, change=None, demote=False, inspect=False):
        policy=self.policy|{'operation':m.INSPECT_OPERATION,'read_only':True} if inspect else self.policy
        program=m.remote_program(policy,self.files,IDENTITY)
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
        value=json.loads(run.stdout);(m.valid_inspection_result if inspect else m.valid_result)(IDENTITY,policy,value)
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


class Receipt(unittest.TestCase):
    def test_workflow_stays_existing_locked_carrier(self):
        value=(ROOT/m.WORKFLOW).read_text()
        self.assertIn("format('/install-search3-public {0} public-search3-20261120-v1', github.sha)",value)
        block=value.split('  public-install:\n',1)[1].split('  production-readiness-contracts:',1)[0]
        for text in ['deploy-anytoour-production','cancel-in-progress: false','github.run_attempt == 1',"github.event.issue.number == 4709",'ANYTOOUR_DEPLOY_SSH_KEY','tests/anytoour_production_deploy_guard_test.py']: self.assertIn(text,block)
        self.assertNotIn('workflow_dispatch:',value)


if __name__ == '__main__': unittest.main(verbosity=2)
