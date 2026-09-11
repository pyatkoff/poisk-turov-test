import importlib.util
import tempfile
import unittest
from pathlib import Path

MODULE = Path(__file__).resolve().parents[1] / 'scripts' / 'diagnostics' / 'andromeda_quote_preview_publish.py'
spec = importlib.util.spec_from_file_location('publisher', MODULE)
publisher = importlib.util.module_from_spec(spec); spec.loader.exec_module(publisher)

class QuotePublisherTest(unittest.TestCase):
    def test_exact_payload_and_no_supplier_action(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            for _, source in publisher.FILES.items():
                path = root / source; path.parent.mkdir(parents=True, exist_ok=True); path.write_text('<?php echo "x";')
            source_sha = 'a' * 40
            data = publisher.payload(root, source_sha)
            self.assertEqual(list(data['files']), publisher.ORDER)
            self.assertEqual(data['source'], source_sha)
            self.assertEqual(set(data['files']), set(publisher.ORDER))
            for row in data['files'].values():
                self.assertRegex(row['sha256'], r'^[0-9a-f]{64}$')
                self.assertTrue(row['content'])
        self.assertNotIn('gateway.samo.ru', publisher.PHP)
        self.assertIn("supplier_calls'=>0", publisher.PHP)
        self.assertIn("booking_calls'=>0", publisher.PHP)
        self.assertIn("/_preview/search3-anex-candidate", publisher.PHP)

    def test_bad_source_rejected(self):
        with tempfile.TemporaryDirectory() as td:
            with self.assertRaises(ValueError):
                publisher.payload(Path(td), 'main')

if __name__ == '__main__': unittest.main()
