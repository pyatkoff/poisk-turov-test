from pathlib import Path
import unittest

SOURCE = Path('scripts/diagnostics/hotel_observed_bulk_reconcile_v2.php').read_text()

class T(unittest.TestCase):
    def test_fixed_operation_and_no_supplier_transport(self):
        self.assertIn("HB2_OPERATION = 'hotel-observed-bulk-1759-20260911-v2'", SOURCE)
        for forbidden in ('curl_', 'file_get_contents(\'http', 'ANEX_API_TOKEN', 'PRICE', 'Hotels_DETAILS'):
            self.assertNotIn(forbidden, SOURCE)
    def test_release_guards(self):
        self.assertIn("['russia','россия','abkhazia','абхазия']", SOURCE)
        self.assertIn("distance>5000", SOURCE.replace(' ', ''))
        self.assertIn("decision_status='pending'", SOURCE)
        self.assertIn('anex_review_pair_exclusions', SOURCE)
        self.assertIn('readback_verified', SOURCE)

if __name__ == '__main__': unittest.main(verbosity=2)
