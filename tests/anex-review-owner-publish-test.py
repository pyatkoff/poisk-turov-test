import hashlib
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
import sys
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts/diagnostics'))
import anex_review_owner_publish as publisher

class PublicationTest(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.home=Path(self.temp.name)/'home'
        self.root=self.home/'www/anytoour.ru';self.target=self.root/'_preview/search3-anex-candidate'
        self.target.mkdir(parents=True);(self.home/'.anytoour-anex').mkdir(mode=0o700)
        (self.root/'data').mkdir();(self.root/'data/db-v1.php').write_text('<?php throw new RuntimeException("DB_MUST_NOT_LOAD");')
        (self.target/'.htaccess').write_text('Header always set X-Robots-Tag "noindex, nofollow"\n<FilesMatch "\\.php$">\nRequire all denied\n</FilesMatch>\n')
        self.payload={'action':'inspect','source_sha':'a'*40,'files':{n:{'content':(ROOT/n).read_text(),'sha256':hashlib.sha256((ROOT/n).read_bytes()).hexdigest()} for n in publisher.FILES}}
    def tearDown(self):self.temp.cleanup()
    def run_payload(self):
        result=subprocess.run(['php',str(ROOT/'scripts/diagnostics/anex_review_owner_publish_runner.php')],cwd=self.root,env={**os.environ,'HOME':str(self.home)},input=json.dumps(self.payload),text=True,capture_output=True,check=True)
        return json.loads(result.stdout)
    def test_inspect_apply_and_refuse_replay(self):
        before=self.run_payload();self.assertEqual(before['status'],'ready');self.assertFalse((self.home/'.anytoour-anex/review-owner').exists())
        self.payload.update(action='apply',setup_hash=hashlib.sha256(b'1'*64).hexdigest(),expected_before=dict(sorted(before['before'].items())))
        done=self.run_payload();self.assertEqual(done['status'],'published');self.assertFalse(done['write_enabled']);self.assertEqual(done['database_calls'],0)
        private=self.home/'.anytoour-anex/review-owner';self.assertEqual((private/'config.php').stat().st_mode&0o777,0o600)
        self.assertEqual(json.loads((private/'publication-state.json').read_text())['state'],'completed')
        for name,digest in done['after'].items():self.assertEqual(hashlib.sha256((self.target/name).read_bytes()).hexdigest(),digest)
        self.assertNotIn(self.payload['setup_hash'],(self.target/'anex-owner-panel-manifest.json').read_text())
        stage=self.root/'_preview'/('.search3-anex-'+'b'*40+'-'+'c'*12);stage.mkdir()
        (stage/'.htaccess').write_text('Header always set X-Robots-Tag "noindex, nofollow"\nRequire all denied\n')
        kept=subprocess.run(['php',str(ROOT/'scripts/diagnostics/anex_review_owner_preserve.php'),str(self.root),str(stage)],env={**os.environ,'HOME':str(self.home)},text=True,capture_output=True,check=True)
        self.assertEqual(json.loads(kept.stdout)['owner_panel'],'preserved')
        for name in ['anex-owner-login.php','anex-hotel-review.php']:self.assertEqual((stage/name).read_bytes(),(self.target/name).read_bytes())
        (self.target/'anex-owner-login.php').write_text('<?php echo "drift";')
        rejected=subprocess.run(['php',str(ROOT/'scripts/diagnostics/anex_review_owner_preserve.php'),str(self.root),str(stage)],env={**os.environ,'HOME':str(self.home)},capture_output=True)
        self.assertNotEqual(rejected.returncode,0)
        self.assertEqual(self.run_payload()['reason'],'publish_owner_already_exists')
    def test_policy_repair_preserves_account_and_refuses_replay(self):
        # Install the published old policy fixture, then repair only its response header.
        name='v2/anex-owner-login.php'
        self.payload['files'][name]['content']=self.payload['files'][name]['content'].replace('Referrer-Policy: same-origin','Referrer-Policy: no-referrer')
        self.payload['files'][name]['sha256']=hashlib.sha256(self.payload['files'][name]['content'].encode()).hexdigest()
        before=self.run_payload();self.payload.update(action='apply',setup_hash='a'*64,expected_before=before['before'])
        old=self.run_payload();self.assertEqual(old['status'],'published')
        private=self.home/'.anytoour-anex/review-owner';account=(private/'owner.json').read_bytes()
        self.payload.update(action='repair',source_sha='b'*40,expected_source='a'*40,expected_runtime=old['runtime_files'])
        self.payload['files'][name]={'content':(ROOT/name).read_text(),'sha256':hashlib.sha256((ROOT/name).read_bytes()).hexdigest()}
        repaired=self.run_payload();self.assertEqual(repaired['status'],'published');self.assertTrue(repaired['account_preserved'])
        self.assertEqual((private/'owner.json').read_bytes(),account);self.assertFalse(repaired['write_enabled'])
        self.assertEqual(repaired['database_calls'],0);self.assertIn('runtime-'+('b'*40),(private/'entry-login.php').read_text())
        self.assertEqual(self.run_payload()['status'],'failed')

    def install_repaired(self):
        name='v2/anex-owner-login.php'
        current=self.payload['files'][name]['content']
        self.payload['files'][name]['content']=current.replace('Referrer-Policy: same-origin','Referrer-Policy: no-referrer')
        self.payload['files'][name]['sha256']=hashlib.sha256(self.payload['files'][name]['content'].encode()).hexdigest()
        before=self.run_payload();self.payload.update(action='apply',setup_hash='a'*64,expected_before=before['before'])
        old=self.run_payload();self.assertEqual(old['status'],'published')
        self.payload.update(action='repair',source_sha='b'*40,expected_source='a'*40,expected_runtime=old['runtime_files'])
        self.payload['files'][name]={'content':current,'sha256':hashlib.sha256(current.encode()).hexdigest()}
        repaired=self.run_payload();self.assertEqual(repaired['status'],'published')
        self.payload.update(action='panel_links',source_sha='c'*40,expected_source='b'*40,expected_runtime=repaired['runtime_files'])
        self.payload['allowed_delta']={}
        for path in publisher.LINK_PATHS:
            raw=self.payload['files'][path]['content']+'\n// bounded panel-links test fixture\n'
            digest=hashlib.sha256(raw.encode()).hexdigest()
            self.payload['files'][path]={'content':raw,'sha256':digest};self.payload['allowed_delta'][path]=digest
        return self.home/'.anytoour-anex/review-owner'

    def activate_owner(self,private):
        account=json.loads((private/'owner.json').read_text());account['password_hash']='synthetic-existing-password-hash';account['setup_hash']=None
        (private/'owner.json').write_text(json.dumps(account));(private/'owner.json').chmod(0o600)
        session=private/'sessions/sess_fixture';session.write_text('synthetic-active-session');session.chmod(0o600)

    def install_links_for_write(self):
        private=self.install_repaired();self.activate_owner(private)
        preserved={name:(private/name).read_bytes() for name in ['owner.json','config.php','sessions/sess_fixture','repair-state.json']}
        links=self.run_payload();self.assertEqual(links['status'],'published');self.assertTrue(links['owner_activated']);self.assertFalse(links['write_enabled'])
        preserved['links-state.json']=(private/'links-state.json').read_bytes()
        self.payload.update(action='owner_write',source_sha='d'*40,expected_source='c'*40,expected_runtime=links['runtime_files'])
        path=publisher.OWNER_WRITE_PATHS[0]
        raw=self.payload['files'][path]['content']+'\n// bounded owner-write test fixture\n'
        digest=hashlib.sha256(raw.encode()).hexdigest();self.payload['files'][path]={'content':raw,'sha256':digest}
        self.payload['allowed_delta']={path:digest}
        return private,preserved,links

    def test_panel_links_preserves_activated_account_session_and_repair_receipt(self):
        private=self.install_repaired();self.activate_owner(private)
        preserved={name:(private/name).read_bytes() for name in ['owner.json','config.php','sessions/sess_fixture','repair-state.json']}
        done=self.run_payload();self.assertEqual(done['status'],'published');self.assertTrue(done['owner_activated'])
        self.assertTrue(done['account_preserved']);self.assertTrue(done['config_preserved']);self.assertFalse(done['write_enabled'])
        self.assertEqual(done['database_calls'],0);self.assertEqual(done['supplier_requests'],0)
        for name,raw in preserved.items():self.assertEqual((private/name).read_bytes(),raw)
        self.assertEqual(json.loads((private/'links-state.json').read_text())['state'],'completed')
        self.assertIn('runtime-'+('c'*40),(private/'entry-panel.php').read_text())
        self.assertEqual(self.run_payload()['status'],'failed')

    def test_panel_links_rejects_auth_change_before_write(self):
        private=self.install_repaired();name='app/admin/anex-review/owner-auth.php'
        raw=self.payload['files'][name]['content']+'\n// disallowed auth edit\n'
        self.payload['files'][name]={'content':raw,'sha256':hashlib.sha256(raw.encode()).hexdigest()}
        account=(private/'owner.json').read_bytes()
        self.assertEqual(self.run_payload()['reason'],'links_delta_not_allowed')
        self.assertFalse((private/'links-state.json').exists());self.assertFalse((private/('runtime-'+('c'*40))).exists())
        self.assertEqual((private/'owner.json').read_bytes(),account)

    def test_panel_links_rejects_unpinned_or_expanded_delta(self):
        private=self.install_repaired()
        self.payload['allowed_delta'][publisher.LINK_PATHS[0]]='f'*64
        self.assertEqual(self.run_payload()['reason'],'links_delta_digest')
        self.payload['allowed_delta']['app/admin/anex-review/owner-auth.php']='f'*64
        self.assertEqual(self.run_payload()['reason'],'links_delta_paths')
        self.assertFalse((private/'links-state.json').exists())

    def test_panel_links_checkpoint_uses_only_completed_repair_lineage(self):
        artifact=Path(self.temp.name)/'artifact';artifact.mkdir();(artifact/'anex-checkpoint-source.json').write_text('{"artifact_id":9}')
        runtime={name:file['sha256'] for name,file in self.payload['files'].items()}
        for path in publisher.LINK_PATHS:runtime[path]='d'*64
        report={'source_sha':'b'*40,'runtime_files':runtime}
        cp={'state':'completed','report':report,'report_sha256':publisher.canonical(report)}
        plan={'action':'panel_links','published_report_sha256':publisher.canonical(report),'allowed_delta':{p:self.payload['files'][p]['sha256'] for p in publisher.LINK_PATHS}}
        (artifact/'anex-owner-repair-checkpoint.json').write_text(json.dumps(cp))
        with unittest.mock.patch.dict(os.environ,{'GITHUB_RUN_ID':'1','GITHUB_RUN_ATTEMPT':'1'}):
            reserved,payload=publisher.prepare(artifact,'c'*40,plan)
            self.assertEqual(payload['expected_source'],'b'*40)
            self.assertTrue((artifact/'anex-owner-links-checkpoint.json').exists())
            self.assertEqual(json.loads((artifact/'anex-owner-repair-checkpoint.json').read_text()),cp)
            self.assertFalse((artifact/publisher.CHECKPOINT).exists())
            done={**reserved,'state':'completed','report':report,'report_sha256':publisher.canonical(report)}
            (artifact/'anex-owner-links-checkpoint.json').write_text(json.dumps(done))
            self.assertIsNone(publisher.prepare(artifact,'c'*40,plan)[1])
            (artifact/'anex-owner-links-checkpoint.json').unlink()
            broken={**cp,'state':'executing'};(artifact/'anex-owner-repair-checkpoint.json').write_text(json.dumps(broken))
            with self.assertRaisesRegex(ValueError,'owner_repair_lineage'):publisher.prepare(artifact,'c'*40,plan)

    def test_owner_write_preserves_owner_session_and_enables_only_decisions(self):
        private,preserved,_=self.install_links_for_write()
        done=self.run_payload();self.assertEqual(done['status'],'published');self.assertTrue(done['write_enabled']);self.assertTrue(done['owner_activated'])
        self.assertEqual(done['upgrade_action'],'owner_write');self.assertTrue(done['account_preserved']);self.assertTrue(done['config_preserved'])
        self.assertEqual(done['database_calls'],0);self.assertEqual(done['supplier_requests'],0)
        for name,raw in preserved.items():self.assertEqual((private/name).read_bytes(),raw)
        self.assertEqual(json.loads((private/'write-state.json').read_text())['state'],'completed')
        public=json.loads((self.target/'anex-owner-panel-manifest.json').read_text());self.assertTrue(public['write_enabled'])
        self.assertIn('runtime-'+('d'*40),(private/'entry-panel.php').read_text());self.assertIn('runtime-'+('d'*40),(private/'entry-login.php').read_text())
        self.assertEqual(self.run_payload()['status'],'failed')

    def test_owner_write_rejects_any_second_runtime_delta(self):
        private,_,_=self.install_links_for_write();name='app/admin/anex-review/owner-auth.php'
        raw=self.payload['files'][name]['content']+'\n// forbidden second owner-write delta\n'
        self.payload['files'][name]={'content':raw,'sha256':hashlib.sha256(raw.encode()).hexdigest()}
        self.assertEqual(self.run_payload()['reason'],'owner_write_delta_not_allowed')
        self.assertFalse((private/'write-state.json').exists());self.assertFalse((private/('runtime-'+('d'*40))).exists())

    def test_owner_write_requires_activated_owner_before_any_write(self):
        private=self.install_repaired();links=self.run_payload();self.assertFalse(links['owner_activated'])
        self.payload.update(action='owner_write',source_sha='d'*40,expected_source='c'*40,expected_runtime=links['runtime_files'])
        path=publisher.OWNER_WRITE_PATHS[0];raw=self.payload['files'][path]['content']+'\n// owner write fixture\n';digest=hashlib.sha256(raw.encode()).hexdigest()
        self.payload['files'][path]={'content':raw,'sha256':digest};self.payload['allowed_delta']={path:digest}
        self.assertEqual(self.run_payload()['reason'],'owner_write_not_activated')
        self.assertFalse((private/'write-state.json').exists());self.assertFalse((private/('runtime-'+('d'*40))).exists())

    def test_owner_write_checkpoint_requires_latest_completed_links_lineage(self):
        artifact=Path(self.temp.name)/'artifact';artifact.mkdir();(artifact/'anex-checkpoint-source.json').write_text('{"artifact_id":11}')
        runtime={name:file['sha256'] for name,file in self.payload['files'].items()};path=publisher.OWNER_WRITE_PATHS[0];runtime[path]='d'*64
        report={'source_sha':'c'*40,'runtime_files':runtime,'write_enabled':False,'owner_activated':True}
        cp={'state':'completed','report':report,'report_sha256':publisher.canonical(report)}
        plan={'action':'owner_write','published_report_sha256':publisher.canonical(report),'expected_previous_source':'c'*40,'allowed_delta_paths':list(publisher.OWNER_WRITE_PATHS)}
        (artifact/'anex-owner-links-checkpoint.json').write_text(json.dumps(cp))
        with unittest.mock.patch.dict(os.environ,{'GITHUB_RUN_ID':'2','GITHUB_RUN_ATTEMPT':'1'}):
            reserved,payload=publisher.prepare(artifact,'e'*40,plan)
            self.assertEqual(payload['expected_source'],'c'*40);self.assertEqual(set(payload['allowed_delta']),set(publisher.OWNER_WRITE_PATHS))
            self.assertTrue((artifact/'anex-owner-write-checkpoint.json').exists())
            done_report={'source_sha':'e'*40,'runtime_files':reserved['manifest'],'write_enabled':True,'owner_activated':True}
            done={**reserved,'state':'completed','report':done_report,'report_sha256':publisher.canonical(done_report)}
            (artifact/'anex-owner-write-checkpoint.json').write_text(json.dumps(done))
            self.assertIsNone(publisher.prepare(artifact,'e'*40,plan)[1])
        bad=dict(report,owner_activated=False);badcp={'state':'completed','report':bad,'report_sha256':publisher.canonical(bad)}
        (artifact/'anex-owner-write-checkpoint.json').unlink();(artifact/'anex-owner-links-checkpoint.json').write_text(json.dumps(badcp))
        badplan={**plan,'published_report_sha256':publisher.canonical(bad)}
        with unittest.mock.patch.dict(os.environ,{'GITHUB_RUN_ID':'3','GITHUB_RUN_ATTEMPT':'1'}):
            with self.assertRaisesRegex(ValueError,'owner_write_lineage'):publisher.prepare(artifact,'e'*40,badplan)

    def test_drift_rejected_before_write(self):
        before=self.run_payload();self.payload.update(action='apply',setup_hash='a'*64,expected_before=before['before'])
        with (self.target/'.htaccess').open('a') as stream:stream.write('# changed\n')
        self.assertEqual(self.run_payload()['reason'],'publish_pinned_readiness');self.assertFalse((self.home/'.anytoour-anex/review-owner').exists())
    def test_manifest_injection_rejected(self):
        before=self.run_payload();self.payload.update(action='apply',setup_hash='a'*64,expected_before=before['before'])
        self.payload['files']['../../main.php']={'content':'<?php','sha256':hashlib.sha256(b'<?php').hexdigest()}
        self.assertEqual(self.run_payload()['reason'],'publish_manifest_paths');self.assertFalse((self.home/'.anytoour-anex/review-owner').exists())
    def test_missing_checkpoint_lineage(self):
        artifact=Path(self.temp.name)/'artifact';artifact.mkdir();(artifact/'anex-checkpoint-source.json').write_text('{"artifact_id":1}')
        with unittest.mock.patch.dict(os.environ,{'GITHUB_RUN_ID':'1','GITHUB_RUN_ATTEMPT':'1'}):
            with self.assertRaisesRegex(ValueError,'owner_bootstrap_lineage'):publisher.prepare(artifact,'a'*40,{'action':'apply','setup_hash':'a'*64,'bootstrap_artifact_id':2})

if __name__=='__main__':
    import unittest.mock
    unittest.main()
