"""No SSH/HTTP/supplier calls: authorization, artifact and rollback regression."""
import copy
import io
import json
import os
from pathlib import Path
import re
import sys
import tarfile
import tempfile
import unittest
from unittest.mock import patch
import zipfile

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/deploy'))
import search3_preview_publish as publish
import search3_preview_remote as remote
import search3_lead_route_repair as repair

SOURCE = 'a' * 40
TREE = 'b' * 40
RELEASE = 'c' * 40


def fixture():
    files = {'.htaccess': b'Deny dangerous PHP', 'preview-lead-disabled.php': b'PREVIEW_LEAD_DISABLED',
             'poisk-turov-old/index.php': b'old', 'search-page-v2.php': b'metrikaCounter=0',
             'index.php': b'new preview'}
    entries = [{'path': p, 'sha256': remote.digest(b), 'size': len(b)} for p,b in sorted(files.items())]
    manifest = {'schema_version': 1, 'target': 'search3-whole-site-preview', 'route': remote.ROUTE,
                'source_sha': SOURCE, 'source_tree_sha': TREE, 'file_count': len(files),
                'invariants': remote.INVARIANTS, 'files': entries}
    m = remote.json_bytes(manifest)
    checksums = ''.join(f'{e["sha256"]}  ./{e["path"]}\n' for e in entries).encode()
    contents = {'control/manifest.json': m, 'control/payload.sha256': checksums,
                **{'payload/' + k:v for k,v in files.items()}}
    buffer = io.BytesIO()
    with tarfile.open(fileobj=buffer, mode='w:gz') as t:
        for name, data in contents.items():
            entry = tarfile.TarInfo(name); entry.size = len(data); t.addfile(entry, io.BytesIO(data))
    archive = buffer.getvalue()
    q = dict(source_sha=SOURCE, source_tree=TREE, release_sha=RELEASE, artifact_id=123,
             build_run=456, deploy_run=789, attempt=1, file_count=len(files),
             archive_sha256=remote.digest(archive), manifest_sha256=remote.digest(m),
             payload_sha256=remote.digest(checksums))
    receipt = dict(source_sha=SOURCE, source_tree_sha=TREE, file_count=len(files),
                   manifest_sha256=q['manifest_sha256'], payload_checksums_sha256=q['payload_sha256'],
                   archive_sha256=q['archive_sha256'])
    z = io.BytesIO()
    with zipfile.ZipFile(z,'w') as out:
        out.writestr('search3-whole-site-preview.tar.gz', archive)
        out.writestr('search3-whole-site-preview.tar.gz.sha256', q['archive_sha256'] + '  search3-whole-site-preview.tar.gz\n')
        out.writestr('search3-site-release.txt', ''.join(f'{k}={v}\n' for k,v in receipt.items()))
        out.writestr('search3-site-release/control/manifest.json', m)
        out.writestr('search3-site-release/control/payload.sha256', checksums)
    return q, files, archive, z.getvalue()


class Authorization(unittest.TestCase):
    def setUp(self):
        self.env = dict(GITHUB_REPOSITORY=publish.REPO,GITHUB_REF='refs/heads/main',GITHUB_ACTOR='pyatkoff',
                        GITHUB_TRIGGERING_ACTOR='pyatkoff',GITHUB_ACTOR_ID='226193297',GITHUB_RUN_ATTEMPT='1',GITHUB_EVENT_NAME='issue_comment')
        self.event = dict(repository={'id':1345518271,'full_name':publish.REPO},sender={'id':226193297,'login':'pyatkoff'},
                          action='created',issue={'number':2530},comment={'user':{'id':226193297},'author_association':'OWNER',
                          'body':f'/publish-search3-preview {SOURCE} {RELEASE} 456 123'})

    def test_owner_command(self):
        self.assertEqual(publish.checked_command(self.event,self.env)['artifact_id'],123)

    def test_publisher_and_recovery_accept_only_current_coordination_issue(self):
        repair_env = {**self.env, 'GITHUB_SHA': 'd' * 40}
        for number in (2530, 996, 997, 1646):
            event = copy.deepcopy(self.event)
            event['issue']['number'] = number
            with self.subTest(issue=number, command='publish'):
                if number == 2530:
                    self.assertEqual(publish.checked_command(event, self.env)['artifact_id'], 123)
                else:
                    with self.assertRaisesRegex(ValueError, 'coordinator_command_only'):
                        publish.checked_command(event, self.env)
            event['comment']['body'] = repair.PREFIX + repair_env['GITHUB_SHA']
            with self.subTest(issue=number, command='recovery'):
                if number == 2530:
                    self.assertEqual(repair.checked_repair(event, repair_env), repair_env['GITHUB_SHA'])
                else:
                    with self.assertRaisesRegex(ValueError, 'coordinator_only'):
                        repair.checked_repair(event, repair_env)

    def test_both_commands_still_reject_edits_PRs_and_identity_changes(self):
        variants = [('action', 'edited'), ('action', 'deleted'),
                    ('repository.id', 1), ('repository.full_name', 'other/repo'),
                    ('sender.id', 1), ('sender.login', 'other'),
                    ('issue.pull_request', {'url': 'x'}),
                    ('comment.user.id', 1), ('comment.author_association', 'COLLABORATOR')]
        env = {**self.env, 'GITHUB_SHA': 'd' * 40}
        for name, value in variants:
            for command in ('publish', 'recovery'):
                event = copy.deepcopy(self.event)
                if command == 'recovery':
                    event['comment']['body'] = repair.PREFIX + env['GITHUB_SHA']
                parent = event
                parts = name.split('.')
                for part in parts[:-1]:
                    parent = parent[part]
                parent[parts[-1]] = value
                with self.subTest(field=name, command=command), self.assertRaises(ValueError):
                    if command == 'publish':
                        publish.checked_command(event, env)
                    else:
                        repair.checked_repair(event, env)

    def test_workflow_coordination_guards_match_shared_authorization(self):
        workflow = (Path(__file__).resolve().parents[1] / '.github/workflows/deploy-search3-whole-site-preview.yml').read_text()
        guards = re.findall(r'github\.event\.issue\.number == (\d+)', workflow)
        self.assertEqual(guards, [str(publish.COORDINATION_ISSUE)] * 2)
        self.assertEqual(publish.COORDINATION_ISSUE, 2530)

    def test_manual(self):
        self.env['GITHUB_EVENT_NAME']='workflow_dispatch'
        self.event['inputs']=dict(source_sha=SOURCE,release_sha=RELEASE,build_run='456',artifact_id='123')
        self.assertEqual(publish.checked_command(self.event,self.env)['source_sha'],SOURCE)

    def test_rejects_other_actor_branch_trigger_and_replay(self):
        for key,value in [('GITHUB_ACTOR','attacker'),('GITHUB_ACTOR_ID','1'),('GITHUB_TRIGGERING_ACTOR','other'),
                          ('GITHUB_REF','refs/heads/feature'),('GITHUB_EVENT_NAME','push'),('GITHUB_RUN_ATTEMPT','2')]:
            env=dict(self.env);env[key]=value
            with self.subTest(key=key),self.assertRaises(ValueError):publish.checked_command(self.event,env)

    def test_rejects_non_owner_edits_other_issue_and_PR(self):
        variants=[('comment','author_association','COLLABORATOR'),('issue','number',997),('issue','pull_request',{'url':'x'})]
        for parent,key,value in variants:
            event=copy.deepcopy(self.event);event[parent][key]=value
            with self.subTest(key=key),self.assertRaises(ValueError):publish.checked_command(event,self.env)
        self.event['action']='edited'
        with self.assertRaises(ValueError):publish.checked_command(self.event,self.env)

    def test_rejects_shell_newline_and_partial_SHA(self):
        for body in [f'/publish-search3-preview {SOURCE[:8]} {RELEASE} 456 123',self.event['comment']['body']+'; echo x',
                     self.event['comment']['body']+'\nsecond',self.event['comment']['body']+' extra']:
            self.event['comment']['body']=body
            with self.subTest(body=body),self.assertRaises(ValueError):publish.checked_command(self.event,self.env)


class Artifacts(unittest.TestCase):
    def test_valid_and_bad_digest(self):
        q,files,archive,z=fixture()
        with tempfile.TemporaryDirectory() as d:
            actual=publish.prepare_zip(z,q,TREE,remote.digest(z),Path(d)/'good')
            self.assertEqual(actual,{k:remote.digest(v) for k,v in files.items()})
            with self.assertRaises(ValueError):publish.prepare_zip(z,q,TREE,'0'*64,Path(d)/'bad')

    def test_unsafe_tar_members(self):
        for name,kind in [('../escape',tarfile.REGTYPE),('/absolute',tarfile.REGTYPE),('payload/link',tarfile.SYMTYPE),
                          ('payload/link',tarfile.LNKTYPE),('payload/fifo',tarfile.FIFOTYPE),('payload//bad',tarfile.REGTYPE)]:
            with self.subTest(name=name,kind=kind),tempfile.TemporaryDirectory() as d:
                archive=Path(d)/'input.tar.gz'
                with tarfile.open(archive,'w:gz') as t:
                    m=tarfile.TarInfo(name);m.type=kind;m.linkname='/etc/passwd';t.addfile(m)
                with self.assertRaises(ValueError):remote.safe_extract(archive,Path(d)/'out')
                self.assertFalse((Path(d)/'out').exists())

    def test_invariants_and_corrupt_payload(self):
        q,files,archive,z=fixture()
        with tempfile.TemporaryDirectory() as d:
            root=Path(d);a=root/'a.tar.gz';a.write_bytes(archive);remote.safe_extract(a,root/'out')
            (root/'out/payload/index.php').write_bytes(b'corrupted')
            with self.assertRaises(ValueError):remote.verify_payload(root/'out',q)

    def test_wrong_artifact_source(self):
        q,files,archive,z=fixture();q['source_sha']='d'*40
        with tempfile.TemporaryDirectory() as d,self.assertRaises(ValueError):
            publish.prepare_zip(z,q,TREE,remote.digest(z),Path(d)/'bad')


class Transaction(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.addCleanup(self.temp.cleanup)
        root=Path(self.temp.name)/'anytoour.ru';root.mkdir()
        for name in remote.REQUIRED_PROTECTED:
            p=root/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(('production:'+name).encode())
        target=root/'_preview/search3-site-candidate';target.mkdir(parents=True)
        (target/'index.php').write_bytes(b'previous preview')
        self.site=remote.Site(root);self.q,self.files,archive,z=fixture()
        fd,name=tempfile.mkstemp(prefix='search3-site.',suffix='.tar.gz',dir='/tmp')
        with os.fdopen(fd,'wb') as f:f.write(archive)
        self.addCleanup(lambda:Path(name).unlink(missing_ok=True))
        self.q['archive']=name;self.q['before']=self.site.snapshot()

    def test_publish_readback_backup_production_unchanged(self):
        before=self.site.protected();self.site.activate(self.q);done=self.site.complete(self.q)
        self.assertEqual(done['status'],'published');self.assertTrue(done['rollback_retained'])
        self.assertEqual(self.site.protected(),before)
        self.assertEqual((self.site.paths(self.q)['backup']/'index.php').read_bytes(),b'previous preview')
        self.assertEqual(self.site.snapshot()['owner']['status'],'published')

    def test_failed_live_check_rolls_back_own_target(self):
        self.site.activate(self.q)
        self.assertEqual(self.site.rollback(self.q)['status'],'rolled_back')
        self.assertEqual(self.site.snapshot(),self.q['before'])

    def test_refuses_predecessor_drift_before_activation(self):
        (self.site.target/'index.php').write_bytes(b'newer writer')
        with self.assertRaises(ValueError):self.site.activate(self.q)
        self.assertEqual((self.site.target/'index.php').read_bytes(),b'newer writer')

    def test_refuses_rollback_over_later_writer(self):
        self.site.activate(self.q);(self.site.target/'index.php').write_bytes(b'newer writer')
        with self.assertRaises(ValueError):self.site.rollback(self.q)
        self.assertEqual((self.site.target/'index.php').read_bytes(),b'newer writer')

    def test_noop_rollback_without_activation(self):
        self.assertEqual(self.site.rollback(self.q)['status'],'not_activated')

    def test_existing_lock_not_stolen(self):
        lock=self.site.parent/'.search3-site-lock';lock.mkdir()
        with self.assertRaises(FileExistsError):self.site.activate(self.q)
        self.assertTrue(lock.is_dir())

    def test_binding_only_own_nonce(self):
        q=dict(name='site-binding-789-1-'+'e'*24+'.txt',nonce='f'*64)
        self.site.binding(q)
        with self.assertRaises(ValueError):self.site.binding({**q,'nonce':'0'*64},True)
        self.site.binding(q,True);self.assertFalse((self.site.target/q['name']).exists())


class LeadRouteRecovery(unittest.TestCase):
    def setUp(self):
        Transaction.setUp(self)
        self.bridge = b'<?php /* canonical bridge fixture */'
        self.direct = b'<?php /* internal adapter fixture */'
        self.target = self.site.root / 'lead-adapter-v2.php'
        self.target.write_bytes(self.direct)
        (self.site.root / 'lead-bridge-v1.php').write_bytes(self.bridge)
        (self.site.root / '.anytoour-bridge-secret').write_bytes(b'e' * 64)
        self.request = dict(deploy_run=999, attempt=1, direct_sha256=remote.digest(self.direct),
                            bridge_sha256=remote.digest(self.bridge), before=self.site.lead_inspect())
        self.process = patch.object(remote.subprocess, 'run', return_value=type('Result', (), {'returncode': 0, 'stdout': b'curl\n'})())
        self.process.start(); self.addCleanup(self.process.stop)
        # Keep retained-backup fixtures in the test's own temporary directory.
        original = tempfile.mkdtemp
        temp = patch.object(remote.tempfile, 'mkdtemp', side_effect=lambda **kw: original(prefix=kw['prefix'], dir=self.temp.name))
        temp.start(); self.addCleanup(temp.stop)

    def test_restore_exact_drift_preserves_other_files_and_preview(self):
        before = self.site.snapshot()
        result = self.site.lead_restore(self.request)
        self.assertEqual(result['status'], 'restored_canonical')
        self.assertEqual(self.target.read_bytes(), self.bridge)
        self.assertEqual(Path(result['backup']).read_bytes(), self.direct)
        self.assertEqual(self.site.protected(), {**before['protected'], 'lead-adapter-v2.php': remote.digest(self.bridge)})
        self.assertEqual(self.site.snapshot()['target_digest'], before['target_digest'])
        self.assertTrue(result['other_protected_unchanged'])
        self.request['before'] = self.site.lead_inspect()
        with self.assertRaisesRegex(ValueError, 'lead_repair_no_replay'):
            self.site.lead_restore(self.request)

    def test_already_canonical_has_no_replacement(self):
        self.target.write_bytes(self.bridge); self.request['before'] = self.site.lead_inspect()
        result = self.site.lead_restore(self.request)
        self.assertEqual(result['status'], 'already_canonical')
        self.assertEqual(result['writes'], 0)
        self.assertFalse(list(self.site.parent.glob('.search3-lead-repair-*')))

    def test_unknown_adapter_or_bridge_and_missing_secret_do_not_write(self):
        for mode in ('adapter', 'bridge', 'secret'):
            with self.subTest(mode=mode):
                self.target.write_bytes(self.direct)
                (self.site.root / 'lead-bridge-v1.php').write_bytes(self.bridge)
                (self.site.root / '.anytoour-bridge-secret').write_bytes(b'e' * 64)
                name = {'adapter': 'lead-adapter-v2.php', 'bridge': 'lead-bridge-v1.php', 'secret': '.anytoour-bridge-secret'}[mode]
                (self.site.root / name).write_bytes(b'unknown')
                self.request['before'] = self.site.lead_inspect(); before = self.site.snapshot()
                with self.assertRaises(ValueError): self.site.lead_restore(self.request)
                self.assertEqual(self.site.snapshot(), before)
                self.assertFalse(list(self.site.parent.glob('.search3-lead-repair-*')))

    def test_drift_and_failed_php_preflight_prevent_replacement(self):
        self.target.write_bytes(b'other writer')
        with self.assertRaisesRegex(ValueError, 'lead_repair_predecessor_changed'):
            self.site.lead_restore(self.request)
        self.target.write_bytes(self.direct)
        with patch.object(remote.subprocess, 'run', return_value=type('Result', (), {'returncode': 1, 'stdout': b''})()):
            with self.assertRaisesRegex(ValueError, 'lead_bridge_syntax'):
                self.site.lead_restore(self.request)
        self.assertEqual(self.target.read_bytes(), self.direct)

    def test_failed_readback_restores_only_own_change(self):
        original = self.site.lead_inspect
        calls = 0
        def inspect():
            nonlocal calls
            calls += 1
            state = original()
            if calls == 3: state['target_digest'] = 'simulated failed acceptance'
            return state
        with patch.object(self.site, 'lead_inspect', side_effect=inspect):
            with self.assertRaisesRegex(ValueError, 'lead_repair_readback'):
                self.site.lead_restore(self.request)
        self.assertEqual(self.target.read_bytes(), self.direct)
        receipt = json.loads((self.site.parent / '.search3-lead-repair-999.json').read_text())
        self.assertEqual(receipt['status'], 'rolled_back')

    def test_repair_requires_exact_fresh_owner_command(self):
        fixture_auth = Authorization(); fixture_auth.setUp()
        env = {**fixture_auth.env, 'GITHUB_SHA': 'd' * 40}
        event = copy.deepcopy(fixture_auth.event)
        event['comment']['body'] = repair.PREFIX + env['GITHUB_SHA']
        self.assertEqual(repair.checked_repair(event, env), env['GITHUB_SHA'])
        for key, value in [('GITHUB_SHA', 'e' * 40), ('GITHUB_RUN_ATTEMPT', '2'),
                           ('GITHUB_ACTOR', 'other'), ('GITHUB_REF', 'refs/heads/feature'),
                           ('GITHUB_EVENT_NAME', 'push')]:
            with self.subTest(key=key), self.assertRaises(ValueError):
                repair.checked_repair(event, {**env, key: value})
        for body in [repair.PREFIX + 'd' * 8, event['comment']['body'] + '\n', event['comment']['body'] + ' extra']:
            bad = copy.deepcopy(event); bad['comment']['body'] = body
            with self.subTest(body=body), self.assertRaises(ValueError): repair.checked_repair(bad, env)


class Provenance(unittest.TestCase):
    def api(self):
        check = lambda name: dict(name=name,head_sha=SOURCE,status='completed',conclusion='success',app={'slug':'github-actions'})
        self.data={
            '/git/ref/heads/main': {'object':{'sha':'d'*40}},
            '/git/ref/heads/'+publish.RELEASE: {'object':{'sha':RELEASE}},
            '/git/commits/'+SOURCE: {'tree':{'sha':TREE}},
            '/git/commits/'+RELEASE: {'tree':{'sha':TREE}},
            '/actions/runs/456': dict(head_sha=SOURCE,path=publish.BUILD,event='pull_request',status='completed',conclusion='success',run_attempt=1,repository={'full_name':publish.REPO},head_repository={'full_name':publish.REPO}),
            '/commits/'+SOURCE+'/check-runs?per_page=100': {'total_count':2,'check_runs':[check('guard'),check('build-preview-artifact')]},
            '/actions/artifacts/123': dict(expired=False,workflow_run={'id':456,'head_sha':SOURCE},name=f'search3-site-preview-{SOURCE}-456-1',digest='sha256:'+'e'*64)}
        class API:
            def __init__(self,data): self.data=data
            def get(self,path): return self.data[path]
        return API(self.data)

    def test_exact_success(self):
        api=self.api();q=fixture()[0]
        self.assertEqual(publish.verify_provenance(api,q,'d'*40),(TREE,'e'*64))

    def test_stale_release_rejected(self):
        api=self.api();self.data['/git/ref/heads/'+publish.RELEASE]['object']['sha']='f'*40
        with self.assertRaises(ValueError): publish.verify_provenance(api,fixture()[0],'d'*40)

    def test_failed_build_and_expired_artifact_rejected(self):
        for key,field,value in [('/actions/runs/456','conclusion','failure'),('/actions/artifacts/123','expired',True),('/actions/runs/456','path','other.yml')]:
            api=self.api();self.data[key][field]=value
            with self.subTest(field=field),self.assertRaises(ValueError):publish.verify_provenance(api,fixture()[0],'d'*40)

    def test_missing_or_red_required_guard_rejected(self):
        for mode in ('missing','red'):
            api=self.api();checks=self.data['/commits/'+SOURCE+'/check-runs?per_page=100']['check_runs']
            if mode=='missing': checks.pop(0)
            else: checks[0]['conclusion']='failure'
            with self.subTest(mode=mode),self.assertRaises(ValueError):publish.verify_provenance(api,fixture()[0],'d'*40)


if __name__=='__main__':unittest.main()
