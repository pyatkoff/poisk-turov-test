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
