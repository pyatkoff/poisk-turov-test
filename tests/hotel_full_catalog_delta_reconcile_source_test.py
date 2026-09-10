from pathlib import Path
import unittest
SOURCE=Path('scripts/diagnostics/hotel_full_catalog_delta_reconcile.php').read_text()
class T(unittest.TestCase):
    def test_operation_and_no_supplier_surface(self):
        self.assertIn("FD_OPERATION = 'hotel-full-catalog-delta-1759-20260911-v2'",SOURCE)
        for forbidden in ('ANEX_API_TOKEN','Hotels_DETAILS','PRICE','curl_','http://','https://'):
            self.assertNotIn(forbidden,SOURCE)
    def test_delta_guards(self):
        for required in ('<=200','>=0.75','>=0.82','>=0.15','>5000','anex_review_pair_exclusions',"decision_status='pending'",'category_conflict','candidate_conflict','readback_verified','operation_already_reserved','supplier_calls'):
            self.assertIn(required,SOURCE)
        self.assertIn("observed_accepted_andromeda_bridge",SOURCE)
        self.assertIn("fuzzy_saved_geo_margin",SOURCE)
        self.assertIn("$db->commit()",SOURCE)
        self.assertIn("$db->rollBack()",SOURCE)
if __name__=='__main__':unittest.main(verbosity=2)
