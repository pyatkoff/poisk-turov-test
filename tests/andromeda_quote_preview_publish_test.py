import base64
import importlib.util
import json
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path

MODULE = Path(__file__).resolve().parents[1] / 'scripts' / 'diagnostics' / 'andromeda_quote_preview_publish.py'
spec = importlib.util.spec_from_file_location('publisher', MODULE)
publisher = importlib.util.module_from_spec(spec); spec.loader.exec_module(publisher)

# Independent contract: deriving this from FILES hid the #4018 client omission.
EXPECTED = {
    'app/integrations/andromeda-client.php': 'app/integrations/andromeda-client.php',
    'app/integrations/andromeda-claim-actions.php': 'app/integrations/andromeda-claim-actions.php',
    'app/integrations/andromeda-selected-quote.php': 'app/integrations/andromeda-selected-quote.php',
    'api-andromeda-search3-preview.php': 'v2/api-andromeda-search3-preview.php',
    'api-andromeda-quote-preview.php': 'v2/api-andromeda-quote-preview.php',
}
CLIENT = 'app/integrations/andromeda-client.php'
SOURCE = 'a' * 40


class QuoteFixture(unittest.TestCase):
    def setUp(self):
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        self.root = Path(temporary.name)
        self.source = self.root / 'source'
        for target, source in EXPECTED.items():
            path = self.source / source
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('<?php // new fixture: ' + target + '\n')


class QuotePublisherTest(QuoteFixture):
    def test_exact_payload_and_no_supplier_action(self):
        self.assertEqual(publisher.FILES, EXPECTED)
        self.assertEqual(publisher.ORDER, list(EXPECTED))
        data = publisher.payload(self.source, SOURCE)
        self.assertEqual(list(data['files']), list(EXPECTED))
        self.assertEqual(data['source'], SOURCE)
        for target, row in data['files'].items():
            content = (self.source / EXPECTED[target]).read_bytes()
            self.assertEqual(base64.b64decode(row['content'], validate=True), content)
            self.assertEqual(row['sha256'], publisher.sha(content))
        self.assertNotIn('gateway.samo.ru', publisher.PHP)
        self.assertIn("supplier_calls'=>0", publisher.PHP)
        self.assertIn("booking_calls'=>0", publisher.PHP)
        self.assertIn("/_preview/search3-anex-candidate", publisher.PHP)

    def test_bad_source_rejected(self):
        with self.assertRaises(ValueError):
            publisher.payload(self.source, 'main')

    def test_missing_client_source_rejected(self):
        (self.source / CLIENT).unlink()
        with self.assertRaises(FileNotFoundError):
            publisher.payload(self.source, SOURCE)


@unittest.skipUnless(shutil.which('php'), 'PHP CLI required for offline publication tests')
class QuotePublicationTransactionTest(QuoteFixture):
    def setUp(self):
        super().setUp()
        self.site = self.root / 'projects' / 'anytoour.ru'
        self.target = self.site / '_preview' / 'search3-anex-candidate'
        self.private = self.root / '.anytoour-andromeda'
        self.private.mkdir()
        for target in EXPECTED:
            path = self.target / target
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('<?php // old fixture: ' + target + '\n')
        (self.site / 'index.php').write_text('production fixture stays unchanged')
        next_file = self.site / '_preview' / 'search3-next-candidate' / 'index.php'
        next_file.parent.mkdir()
        next_file.write_text('NEXT fixture stays unchanged')
        self.before = self.snapshot(self.site)
        self.data = publisher.payload(self.source, SOURCE)
        self.release = self.private / ('quote-preview-' + SOURCE)

    @staticmethod
    def snapshot(root):
        return {str(p.relative_to(root)): p.read_bytes() for p in root.rglob('*') if p.is_file()}

    def run_php(self, data=None, code=None):
        run = subprocess.run(
            [shutil.which('php'), '-r', publisher.PHP if code is None else code],
            input=json.dumps(self.data if data is None else data),
            text=True, capture_output=True, cwd=self.site, timeout=10, check=True,
        )
        result = json.loads(run.stdout)
        for field in ('supplier_calls', 'booking_calls', 'database_writes'):
            self.assertEqual(result[field], 0)
        return result

    def test_complete_publication_includes_client_and_retains_backups(self):
        result = self.run_php()
        self.assertEqual(result['status'], 'published')
        self.assertEqual(result['files'], 5)
        self.assertEqual(result['source'], SOURCE)
        self.assertEqual(list(result['sha256']), list(EXPECTED))
        for index, (target, source) in enumerate(EXPECTED.items()):
            self.assertEqual((self.target / target).read_bytes(), (self.source / source).read_bytes())
            old = self.before['_preview/search3-anex-candidate/' + target]
            self.assertEqual((self.release / 'backup' / (str(index) + '.php')).read_bytes(), old)
        self.assertEqual(json.loads((self.release / 'completed.json').read_text()), result)
        for path in ('index.php', '_preview/search3-next-candidate/index.php'):
            self.assertEqual((self.site / path).read_bytes(), self.before[path])

    def test_old_four_file_payload_rejected_before_installation(self):
        self.data['files'].pop(CLIENT, None)
        result = self.run_php()
        self.assertEqual((result['status'], result['reason']), ('blocked', 'file_set'))
        self.assertEqual(result['installed_count'], 0)
        self.assertEqual(self.snapshot(self.site), self.before)
        self.assertFalse(self.release.exists())

    def test_corrupt_client_hash_rejected_before_installation(self):
        self.data['files'][CLIENT]['sha256'] = '0' * 64
        result = self.run_php()
        self.assertEqual((result['status'], result['reason']), ('blocked', 'file_hash'))
        self.assertEqual(self.snapshot(self.site), self.before)
        self.assertFalse(self.release.exists())

    def test_completed_publication_is_readback_only(self):
        self.assertEqual(self.run_php()['status'], 'published')
        before = self.snapshot(self.root)
        self.assertEqual(self.run_php()['status'], 'already_published')
        self.assertEqual(self.snapshot(self.root), before)

    def test_changed_installed_client_is_not_overwritten_by_replay(self):
        self.assertEqual(self.run_php()['status'], 'published')
        client = self.target / CLIENT
        client.write_text('<?php // concurrent client fixture')
        before = self.snapshot(self.root)
        result = self.run_php()
        self.assertEqual((result['status'], result['reason']), ('blocked', 'published_target_changed'))
        self.assertEqual(self.snapshot(self.root), before)

    def test_old_four_file_receipt_cannot_reauthorize_same_source(self):
        self.release.mkdir()
        old_hashes = {key: row['sha256'] for key, row in self.data['files'].items() if key != CLIENT}
        (self.release / 'completed.json').write_text(json.dumps({
            'status': 'published', 'source': SOURCE, 'sha256': old_hashes,
        }))
        before = self.snapshot(self.root)
        result = self.run_php()
        self.assertEqual((result['status'], result['reason']), ('blocked', 'previous_outcome_unknown'))
        self.assertEqual(self.snapshot(self.site), self.before)
        self.assertEqual(self.snapshot(self.release), {
            'completed.json': before[str((self.release / 'completed.json').relative_to(self.root))],
        })

    def test_unknown_reservation_is_not_replayed(self):
        self.release.mkdir()
        result = self.run_php()
        self.assertEqual((result['status'], result['reason']), ('blocked', 'previous_outcome_unknown'))
        self.assertEqual(self.snapshot(self.site), self.before)

    def test_mid_install_failure_restores_client_and_other_files(self):
        needle = '$installed[]=$i;'
        self.assertEqual(publisher.PHP.count(needle), 1)
        injected = publisher.PHP.replace(needle, needle + "if($i===2)throw new RuntimeException('fixture_failure');")
        result = self.run_php(code=injected)
        self.assertEqual((result['status'], result['reason']), ('blocked', 'fixture_failure'))
        self.assertEqual(result['installed_count'], 3)
        self.assertEqual(self.snapshot(self.site), self.before)
        self.assertFalse((self.release / 'completed.json').exists())
        retry = self.run_php()
        self.assertEqual(retry['reason'], 'previous_outcome_unknown')
        self.assertEqual(self.snapshot(self.site), self.before)


if __name__ == '__main__':
    unittest.main()
