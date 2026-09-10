from pathlib import Path
import unittest
SOURCE=Path('scripts/diagnostics/hotel_full_catalog_reconcile.php').read_text()
class T(unittest.TestCase):
    def test_fixed_release_scope(self):
        self.assertIn("FC_OPERATION = 'hotel-full-catalog-1759-20260911-v1'",SOURCE)
        self.assertIn("1=>'Египет',2=>'Таиланд',4=>'Турция',8=>'Мальдивы',9=>'ОАЭ',10=>'Куба',12=>'Шри-Ланка',16=>'Вьетнам'",SOURCE)
        for forbidden in ('ANEX_API_TOKEN','Hotels_DETAILS','PRICE','curl_','http://','https://'):
            self.assertNotIn(forbidden,SOURCE)
    def test_current_db_guards(self):
        for required in ('FOR UPDATE','anex_hotel_decisions','anex_review_pair_exclusions',"decision_status='pending'",'candidate_conflict','category_conflict','>5000','readback_verified','operation_already_reserved','supplier_calls'):
            self.assertIn(required,SOURCE)
        self.assertIn("'starKey'",SOURCE)
        self.assertIn("$db->commit()",SOURCE)
        self.assertIn("$db->rollBack()",SOURCE)
if __name__=='__main__':unittest.main(verbosity=2)
