#!/usr/bin/env python3
import importlib.util
from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[1]
PATH=ROOT/'scripts/diagnostics/search3_quote_ui_single_asset_publish.py'
spec=importlib.util.spec_from_file_location('publisher',PATH); mod=importlib.util.module_from_spec(spec); spec.loader.exec_module(mod)

class PublisherTest(unittest.TestCase):
    def test_scope_is_one_generated_ui_asset(self):
        self.assertEqual(mod.SOURCE_FILE,'v2/search3-results-cards-v2.js')
        self.assertEqual(mod.TARGET_FILE,'search3-results-cards-v2.js')
        self.assertIn("$preview.'/search3-results-cards-v2.js'",mod.PHP)
        self.assertIn('/_preview/search3-site-candidate/search3-results-cards-v2.js',mod.PHP)
        self.assertIn("'files'=>1",mod.PHP)
    def test_fail_closed_and_no_supplier_or_booking_actions(self):
        for marker in ('previous_outcome_unknown','published_target_changed','target_readback','supplier_calls','booking_calls','database_writes'):
            self.assertIn(marker,mod.PHP)
        for forbidden in ('broninit','get_flights','changeservice','action=bron','lead-adapter','api-v2.php'):
            self.assertNotIn(forbidden,mod.PHP)
    def test_source_asset_contains_verified_quote_module(self):
        data=(ROOT/mod.SOURCE_FILE).read_text()
        self.assertIn('search3-selected-flow-v2',data)
        self.assertIn('api-andromeda-quote-preview.php',data)
        self.assertIn('Проверить цену и рейсы',data)

if __name__=='__main__': unittest.main()
