"""Regression for the old TV25/43 versus SAMO315/342 comparison bug."""
import sys
from pathlib import Path
import unittest
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts' / 'diagnostics'))
from hotel_match_operator_namespace import operator_binding_matches as matches, identifier, source_identifier

class OperatorNamespaceTests(unittest.TestCase):
    def test_tv_numbers(self):
        self.assertTrue(matches('tv','operator_315',25));self.assertTrue(matches('tv','operator_342',43))
    def test_samo_numbers(self):
        self.assertTrue(matches('samo','operator_315',315));self.assertTrue(matches('samo','operator_342',342))
    def test_old_v37_samo_comparison_rejected(self):
        self.assertFalse(matches('samo','operator_315',25));self.assertFalse(matches('samo','operator_342',43))
    def test_inverse_comparison_rejected(self):
        self.assertFalse(matches('tv','operator_315',315));self.assertFalse(matches('tv','operator_342',342))
    def test_unknown_source_and_invalid_number_rejected(self):
        self.assertFalse(matches('other','operator_315',315));self.assertFalse(matches('samo','operator_315','315'));self.assertFalse(matches('samo','operator_315',True))
    def test_separate_bg_and_anex_namespaces(self):
        self.assertTrue(matches('tv','bgoperator',18));self.assertFalse(matches('samo','bgoperator',18))
        self.assertTrue(matches('samo','operator_115',115));self.assertFalse(matches('tv','operator_115',115))
        self.assertFalse(matches('samo','anex',13));self.assertFalse(matches('tv','operator_5',5))
    def test_signed_observation_is_retained_but_not_native_authority(self):
        self.assertEqual(source_identifier('-859893'),'-859893')
        with self.assertRaises(ValueError):identifier('-859893')
    def test_full_id_is_not_stripped(self):self.assertEqual(identifier('1020000123'),'1020000123')

if __name__=='__main__':unittest.main()
