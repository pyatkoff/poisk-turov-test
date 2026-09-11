import hashlib
import importlib.util
import json
from pathlib import Path
import unittest
import unittest.mock

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('owner_publish_tests', ROOT / 'tests/anex-review-owner-publish-test.py')
base = importlib.util.module_from_spec(spec)
assert spec and spec.loader
spec.loader.exec_module(base)


class OwnerWriteHtaccessDriftTest(base.PublicationTest):
    def test_owner_write_rebinds_only_safe_preview_htaccess_drift(self):
        private, preserved, _ = self.install_links_for_write()
        ht = self.target / '.htaccess'
        drifted = ht.read_text() + '# regenerated preview metadata\n'
        ht.write_text(drifted)
        drift_hash = hashlib.sha256(ht.read_bytes()).hexdigest()

        done = self.run_payload()
        self.assertEqual(done['status'], 'published')
        self.assertTrue(done['write_enabled'])
        self.assertTrue(done['owner_activated'])
        self.assertTrue(done['htaccess_reconciled'])
        self.assertEqual(done['after']['.htaccess'], drift_hash)
        self.assertEqual(ht.read_text(), drifted)
        public = json.loads((self.target / 'anex-owner-panel-manifest.json').read_text())
        self.assertEqual(public['files']['.htaccess'], drift_hash)
        self.assertTrue(public['write_enabled'])
        for name, raw in preserved.items():
            self.assertEqual((private / name).read_bytes(), raw)

    def test_owner_write_rejects_htaccess_drift_without_exact_owner_grants(self):
        private, _, _ = self.install_links_for_write()
        ht = self.target / '.htaccess'
        raw = ht.read_text().replace(
            '<Files "anex-hotel-review.php">\n  Require all granted\n</Files>',
            '<Files "anex-hotel-review.php">\n  Require all denied\n</Files>',
        )
        ht.write_text(raw)
        result = self.run_payload()
        self.assertEqual(result['reason'], 'owner_write_htaccess_drift')
        self.assertFalse((private / 'write-state.json').exists())
        self.assertFalse((private / ('runtime-' + ('d' * 40))).exists())

    def test_owner_write_rejects_public_manifest_drift(self):
        private, _, _ = self.install_links_for_write()
        ht = self.target / '.htaccess'
        ht.write_text(ht.read_text() + '# regenerated preview metadata\n')
        public_path = self.target / 'anex-owner-panel-manifest.json'
        public = json.loads(public_path.read_text())
        public['source_sha'] = 'f' * 40
        public_path.write_text(json.dumps(public))
        result = self.run_payload()
        self.assertEqual(result['reason'], 'owner_write_public_manifest_drift')
        self.assertFalse((private / 'write-state.json').exists())
        self.assertFalse((private / ('runtime-' + ('d' * 40))).exists())


if __name__ == '__main__':
    unittest.main()
