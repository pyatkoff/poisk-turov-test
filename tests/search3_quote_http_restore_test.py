import importlib.util
from pathlib import Path
import tempfile
import unittest

path=Path(__file__).parents[1]/'scripts/diagnostics/search3_quote_http_restore.py'
spec=importlib.util.spec_from_file_location('restore',path);module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)

class RestoreTest(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.addCleanup(self.temp.cleanup)
        root=Path(self.temp.name);self.site=root/'www/anytoour.ru';self.target=self.site/'_preview/search3-anex-candidate'
        self.target.mkdir(parents=True);self.private=root/'.anytoour-andromeda';self.private.mkdir()
        (self.private/'grouped-search-update.lock').write_bytes(b'')
        self.ns={'__name__':'fixture'};exec(module.REMOTE,self.ns)
        self.before=b'Options -Indexes\n<FilesMatch "\\.php$">\nRequire all denied\n</FilesMatch>\n'
        self.access=self.target/'.htaccess';self.access.write_bytes(self.before);self.access.chmod(0o644)
        self.ns['BEFORE']=self.ns['sha'](self.before)
        for name in self.ns['PINS']:
            body=(name+' fixture').encode();(self.target/name).write_bytes(body);self.ns['PINS'][name]=self.ns['sha'](body)
        (self.site/'index.php').write_text('production unchanged')
    def run_action(self,action):return self.ns['repair'](self.site,self.private,action)
    def test_apply_and_rollback_preserve_exact_bytes(self):
        result=self.run_action('apply');self.assertEqual(result['status'],'applied')
        self.assertEqual(self.access.read_bytes(),self.before+self.ns['RULE'])
        self.assertEqual(self.access.stat().st_mode&0o777,0o644)
        with self.assertRaises(AssertionError):self.run_action('apply')
        self.assertEqual(self.run_action('rollback')['status'],'rolled_back')
        self.assertEqual(self.access.read_bytes(),self.before)
        with self.assertRaises(AssertionError):self.run_action('apply')
    def test_changed_predecessor_never_writes(self):
        self.access.write_bytes(self.before+b'# different\n')
        with self.assertRaises(AssertionError):self.run_action('apply')
        self.assertFalse((self.private/self.ns['OP']).exists())
    def test_changed_runtime_never_writes(self):
        (self.target/'api-andromeda-quote-preview.php').write_text('changed')
        with self.assertRaises(AssertionError):self.run_action('apply')
        self.assertEqual(self.access.read_bytes(),self.before)
    def test_ancestor_policy_blocks(self):
        (self.site/'.htaccess').write_text('Require all denied')
        with self.assertRaises(AssertionError):self.run_action('apply')
    def test_rollback_refuses_external_drift(self):
        self.run_action('apply');self.access.write_bytes(self.access.read_bytes()+b'# later edit\n')
        with self.assertRaises(AssertionError):self.run_action('rollback')
    def test_rule_is_only_exact_public_route(self):
        rule=self.ns['RULE'].decode()
        self.assertIn('^api-andromeda-quote-preview\\.php$',rule)
        self.assertIn("%{REQUEST_URI} == '/_preview/search3-anex-candidate/api-andromeda-quote-preview.php'",rule)
        self.assertNotIn('Require all granted',rule)

if __name__=='__main__':unittest.main()
