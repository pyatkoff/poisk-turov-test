from pathlib import Path
import unittest

SOURCE = Path('scripts/diagnostics/hotel_observed_bulk_reconcile_v3.php').read_text()

class SourceGuards(unittest.TestCase):
    def test_fixed_operation_and_no_supplier_transport(self):
        self.assertIn("HB3_OPERATION = 'hotel-observed-bulk-1759-20260911-v3'", SOURCE)
        for forbidden in ('ANEX_API_TOKEN', 'Hotels_DETAILS', 'PRICE', 'curl_', "file_get_contents('http"):
            self.assertNotIn(forbidden, SOURCE)

    def test_release_guards(self):
        self.assertIn("['russia','россия','abkhazia','абхазия']", SOURCE)
        self.assertIn("count($tokens) < 2", SOURCE)
        self.assertIn("count($targets) !== 1", SOURCE)
        self.assertIn('anex_review_pair_exclusions', SOURCE)
        self.assertIn("decision_status='pending'", SOURCE)
        self.assertIn("$best[0] < 0.80", SOURCE)
        self.assertIn("$best[0] - $second < 0.15", SOURCE)
        self.assertIn('readback_verified', SOURCE)
        self.assertIn("'supplier_calls'=>0", SOURCE)

if __name__ == '__main__':
    unittest.main(verbosity=2)
