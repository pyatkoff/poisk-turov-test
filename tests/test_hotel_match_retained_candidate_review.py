"""Offline negative tests. Set MATCH_RETAINED_DIR to include pinned ZIP fixtures."""
import copy
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest
from collections import defaultdict

PATH = Path(__file__).resolve().parents[1] / 'scripts/diagnostics/hotel_match_retained_candidate_review.py'
SPEC = importlib.util.spec_from_file_location('retained_review', PATH)
M = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(M)


def candidate():
    return {'catalog_id': '99', 'lanes': {ns: {'native_ids': []} for ns in M.NS}}


def edge(operator=25, local=7, native='20'):
    return {'operator_id': operator, 'tv_hotel_id': local, 'positive_native_candidates': [native],
            'operator_link_sha256': 'a'*64, 'tour_id_sha256': 'b'*64, 'source_result_sha256': 'c'*64}


class ReviewTests(unittest.TestCase):
    def test_normalizes_integer_within_namespace(self):
        self.assertEqual(M.ids([20, '20']), ['20'])

    def test_rejects_nonidentifiers(self):
        for value in (True, False, 1.0, '01', '-1', '', None, '1e3'):
            with self.subTest(value=value), self.assertRaises(ValueError):
                M.identifier(value)

    def test_duplicate_json_key_rejected(self):
        with self.assertRaises(ValueError):
            json.loads('{"id":1,"id":2}', object_pairs_hook=M.unique_object)

    def test_direct_anex_is_support_only(self):
        self.assertEqual(M.support(['8510'], [8510]), 'support_equal')
        self.assertEqual(M.support(['8510'], [8509]), 'support_different')
        self.assertEqual(M.support(['8510', '8509'], [8510]), 'support_collision')
        self.assertEqual(M.support(['8510'], []), 'none')

    def test_exact_two_independent_lanes(self):
        c = candidate(); c['lanes']['operator_315']['native_ids'] = [20]
        c['lanes']['operator_342']['native_ids'] = [30]
        proof = M.exact_proofs(7, c, [edge(), edge(43, native='30')],
                              {('operator_315','20'):{'99'}, ('operator_342','30'):{'99'}})
        self.assertEqual(len(proof), 2)

    def test_duplicate_same_operator_is_one_lane(self):
        c = candidate(); c['lanes']['operator_315']['native_ids'] = [20]
        self.assertEqual(len(M.exact_proofs(7, c, [edge(), edge()], {('operator_315','20'):{'99'}})), 1)

    def test_cross_target_collision_has_no_proof(self):
        c = candidate(); c['lanes']['operator_315']['native_ids'] = [20]
        self.assertEqual(M.exact_proofs(7, c, [edge(), edge(local=8)], {('operator_315','20'):{'99'}}), [])

    def test_cross_catalog_collision_has_no_proof(self):
        c = candidate(); c['lanes']['operator_315']['native_ids'] = [20]
        self.assertEqual(M.exact_proofs(7, c, [edge()], {('operator_315','20'):{'99','98'}}), [])

    def test_ambiguous_native_has_no_proof(self):
        c = candidate(); c['lanes']['operator_315']['native_ids'] = [20,21]
        self.assertEqual(M.exact_proofs(7, c, [edge()], {('operator_315','20'):{'99'}}), [])

    def test_bg_prefix_is_not_a_proof(self):
        c = candidate(); c['lanes']['operator_115']['native_ids'] = [625564281]
        e = edge(18, native='102625564281')
        self.assertEqual(M.exact_proofs(7, c, [e], {}), [])
        r = M.bg_evidence(7, c, [e], {'102625564281':[{'name':'A'}]})
        self.assertTrue(r['prefix_pattern_observed_not_authority'])
        self.assertFalse(r['namespace_binding_proven'])

    def test_bg_missing_in_samo_not_id_conflict(self):
        r = M.bg_evidence(7, candidate(), [edge(18, native='102123')], {})
        self.assertEqual(r['samo_original_ids'], [])
        self.assertFalse(r['prefix_pattern_observed_not_authority'])

    def test_wrong_archive_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)/'wrong.zip'; path.write_bytes(b'not an archive')
            with self.assertRaisesRegex(ValueError, 'zip_hash'):
                M.load_archive(path, 'tv')

    @unittest.skipUnless(os.environ.get('MATCH_RETAINED_DIR'), 'pinned local ZIP fixtures not supplied')
    def test_all_real_dossiers_and_outside49_proofs(self):
        root = Path(os.environ['MATCH_RETAINED_DIR'])
        tv = M.load_archive(root/'match_v63_secondary.zip', 'tv')
        samo = M.load_archive(root/'match_v65_result.zip', 'samo')
        bg = M.load_archive(root/'match_bg_dictionary_v5.zip', 'bg')
        report = M.analyse(tv, samo, bg)
        self.assertEqual(report['input_count'], 175)
        counts = report['dossier_ids_by_exact_independent_tv_lane_count']
        self.assertEqual({k:len(v) for k,v in counts.items()}, {'0':172, '1':2, '2':1})
        self.assertEqual(counts['2'], [1540])
        self.assertEqual(counts['1'], [1299, 148230])
        self.assertEqual(report['strict_candidate_catalog_collisions'], [{'catalog_id':'37255','local_hotel_ids':[1772,1773]}])
        bgrows = report['selected_legacy_bg_review']
        self.assertEqual(len(bgrows), 18)
        self.assertEqual(sum(bool(x['samo_original_ids']) for x in bgrows), 4)
        self.assertEqual(sum(bool(x['exact_full_key_dictionary_rows']) for x in bgrows), 1)
        self.assertFalse(report['safe_to_write_now'])
        # Existing immutable child provenance must fail on any tampering.
        altered = copy.deepcopy(tv['single_native_edges']); altered[0]['source_result_sha256'] = '0'*64
        with self.assertRaisesRegex(ValueError, 'child_hash'):
            M.validate_tv(altered)


if __name__ == '__main__':
    unittest.main()
