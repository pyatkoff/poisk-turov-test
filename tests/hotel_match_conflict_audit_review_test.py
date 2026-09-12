"""Offline regression tests; no network or DB access."""
import importlib.util
from pathlib import Path
import unittest

PATH = Path(__file__).resolve().parents[1] / 'scripts/diagnostics/hotel_match_conflict_audit_review.py'
SPEC = importlib.util.spec_from_file_location('match_conflict_review', PATH)
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class ConflictReviewTest(unittest.TestCase):
    def test_posh_primary_cannot_use_unqualified_alternate(self):
        self.assertEqual(MODULE.primary_name_guard('Posh Club by Sunrise Diamond Beach Resort Select', 'SUNRISE DIAMOND BEACH RESORT')['status'], 'blocked')

    def test_posh_specific_target_still_requires_other_guards(self):
        result = MODULE.primary_name_guard('Posh Club by Sunrise Diamond Beach Resort Select', 'POSH CLUB SUNRISE DIAMOND BEACH RESORT')
        self.assertEqual(result['status'], 'requires_other_identity_guards')
        self.assertFalse(result['auto_accept'])

    def test_nova_city_is_not_nova_hotel(self):
        self.assertIn('city', MODULE.primary_name_guard('Nova City Hotel Istanbul', 'NOVA HOTEL ISTANBUL')['differing_qualifiers'])

    def test_nova_current_name_wins_over_former_name(self):
        self.assertEqual(MODULE.primary_name_guard('Nova City Hotel Istanbul', 'NOVA CITY HOTEL (EX. GRAND INOVA, AMBER SUITES)')['status'], 'requires_other_identity_guards')

    def test_former_name_cannot_satisfy_current_qualifier(self):
        self.assertEqual(MODULE.primary_name_guard('Posh Club Sunrise Diamond Beach', 'Sunrise Diamond Beach (ex. Posh Club)')['status'], 'blocked')

    def test_meaningful_qualifiers_preserved(self):
        for left, right in [('North Garden Hotel', 'South Beach Hotel'), ('ABC Annex', 'ABC'), ('ABC Adults', 'ABC')]:
            with self.subTest(left=left):
                self.assertEqual(MODULE.primary_name_guard(left, right)['status'], 'blocked')

    def test_missing_primary_blocks(self):
        for value in ('', ' ', None):
            self.assertEqual(MODULE.primary_name_guard(value, 'ABC')['status'], 'blocked')

    def test_no_substring_qualifier(self):
        self.assertEqual(MODULE.primary_name_guard('Cityscape Hotel', 'Cityscape')['status'], 'requires_other_identity_guards')

    def test_receipt_full_match(self):
        expected = {'local_id': 10, 'mapping_digest': 'a', 'source_row_digest': 'b'}
        current = [{'catalog_hotel_id': 10, 'enabled': 1, 'mapping_digest': 'a', 'source_row_digest': 'b'}]
        self.assertEqual(MODULE.receipt_row_state(expected, current), 'full_match')

    def test_legacy_missing_digest_is_not_db_change(self):
        expected = {'local_id': 10, 'mapping_digest': 'a'}
        current = [{'catalog_hotel_id': 10, 'enabled': 1, 'mapping_digest': 'a', 'source_row_digest': 'b'}]
        self.assertEqual(MODULE.receipt_row_state(expected, current), 'matching_available_fields_incomplete_legacy_provenance')

    def test_known_digest_change_is_not_hidden_by_missing_other_digest(self):
        self.assertEqual(MODULE.receipt_row_state({'local_id': 10, 'mapping_digest': 'a'}, [{'catalog_hotel_id': 10, 'enabled': 1, 'mapping_digest': 'changed'}]), 'digest_changed')

    def test_target_disabled_missing_duplicate_rejected(self):
        for current in ([], [{'catalog_hotel_id': 11, 'enabled': 1}], [{'catalog_hotel_id': 10, 'enabled': 0}], [{}, {}]):
            with self.subTest(current=current):
                self.assertEqual(MODULE.receipt_row_state({'local_id': 10}, current), 'target_or_cardinality_changed')


if __name__ == '__main__':
    unittest.main()
