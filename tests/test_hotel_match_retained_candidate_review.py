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



class ExpandedReviewTests(unittest.TestCase):
    def fixture(self):
        c = candidate(); c["lanes"]["operator_315"]["native_ids"] = [20]
        e = edge(); e["provenance"] = {"row_path": "/rows/0", "row_canonical_sha256": "d"*64,
                                     "archive_result_sha256": "e"*64}
        return c, e, [{"candidates": [c]}]

    def test_two_natives_for_one_tv_target_are_ambiguous(self):
        c, e, ds = self.fixture()
        second = copy.deepcopy(e); second["positive_native_candidates"] = [21]
        es = [e, second]
        self.assertEqual(M.expanded_proofs(7, c, es, M.expanded_indexes(es, ds)), [])

    def test_one_native_at_two_tv_targets_rejected(self):
        c, e, ds = self.fixture(); other = copy.deepcopy(e); other["tv_hotel_id"] = 8
        es = [e, other]
        self.assertEqual(M.expanded_proofs(7, c, es, M.expanded_indexes(es, ds)), [])

    def test_one_catalog_with_conflicting_native_rows_rejected(self):
        c, e, ds = self.fixture(); other = copy.deepcopy(c)
        other["lanes"]["operator_315"]["native_ids"] = [21]
        ds[0]["candidates"].append(other)
        self.assertEqual(M.expanded_proofs(7, c, [e], M.expanded_indexes([e], ds)), [])

    def test_one_native_at_two_catalogs_rejected(self):
        c, e, ds = self.fixture(); other = copy.deepcopy(c); other["catalog_id"] = "100"
        ds[0]["candidates"].append(other)
        self.assertEqual(M.expanded_proofs(7, c, [e], M.expanded_indexes([e], ds)), [])

    def test_repeated_archive_proof_not_extra_operator(self):
        c, e, ds = self.fixture(); es = [e, copy.deepcopy(e)]
        proof = M.expanded_proofs(7, c, es, M.expanded_indexes(es, ds))
        self.assertEqual(len(proof), 1); self.assertEqual(len(proof[0]["evidence"]), 1)

    def test_same_numeric_id_in_other_namespace_is_not_proof(self):
        c, e, ds = self.fixture(); e["operator_id"] = 13
        self.assertEqual(M.expanded_proofs(7, c, [e], M.expanded_indexes([e], ds)), [])

    def test_wrong_tail_archive_rejected(self):
        with tempfile.TemporaryDirectory() as folder:
            p = Path(folder)/"tail.zip"; p.write_bytes(b"untrusted")
            with self.assertRaisesRegex(ValueError, "zip_hash"):
                M.load_archive(p, "tv_tail")

    def test_unpinned_history_and_symlinks_rejected(self):
        with tempfile.TemporaryDirectory() as folder:
            p = Path(folder)/"history.json"; p.write_text("{}")
            with self.assertRaisesRegex(ValueError, "history_hash"):
                M.load_history(p)
            link = Path(folder)/"link.json"; link.symlink_to(p)
            with self.assertRaisesRegex(ValueError, "history_file"):
                M.load_history(link)

    @unittest.skipUnless(os.environ.get("MATCH_RETAINED_DIR"), "pinned ZIP fixtures")
    def test_real_expanded_provenance_counts_and_old_report_unchanged(self):
        import hashlib
        root = Path(os.environ["MATCH_RETAINED_DIR"])
        tv = M.load_archive(root/"match_v63_secondary.zip", "tv")
        samo = M.load_archive(root/"match_v65_result.zip", "samo")
        bg = M.load_archive(root/"match_bg_dictionary_v5.zip", "bg")
        tail = M.load_archive(root/"match_common4_tail_v15.zip", "tv_tail")
        history = M.load_history(PATH.parents[2]/"reports/hotel-match-retained175-history-mass-20260926.json")
        baseline = M.analyse(tv, samo, bg)
        raw = (json.dumps(baseline, ensure_ascii=False, sort_keys=True, indent=2)+"\n").encode()
        self.assertEqual(hashlib.sha256(raw).hexdigest(), "01ef024f859be9464a12ffcdd196d8b12450d2980d4184c2a056548e578d738d")
        report = M.analyse_expanded(tv, samo, bg, tail, history)
        self.assertEqual((report["tv_input_rows"],report["tv_unique_edges"],report["tv_unique_hotels_with_evidence"]),(812,801,559))
        self.assertEqual((report["tail_overlap_rows"],report["tail_overlap_hotels"]),(59,45))
        self.assertEqual(report["expanded_proven_candidate_hotels"], 13)
        self.assertEqual(len(report["new_candidate_hotels_with_exact_proof"]), 10)
        buckets = report["dossier_ids_by_exact_independent_tv_lane_count"]
        self.assertEqual({k:len(v) for k,v in buckets.items()}, {"0":162,"1":10,"2":3})
        self.assertEqual(buckets["2"], [1540,113617,121109])
        rows = {r["local_hotel_id"]:r for r in report["candidates_with_proven_tv_lanes"]}
        self.assertIn("historical_source_accepted_other_target", rows[1540]["historical_review_reasons"])
        self.assertIn("historical_source_pending", rows[76753]["historical_review_reasons"])
        for key in ("provider_http_calls","database_reads","database_writes","mapping_writes","accepted_mapping_count"):
            self.assertEqual(report[key], 0)
        self.assertFalse(report["current_validation_performed"])
        for row in rows.values():
            self.assertFalse(row["safe_to_write_now"])
            for lane in row["proven_lanes"]:
                self.assertIn(lane["namespace"], M.BRIDGES.values())
                for provenance in lane["evidence"]:
                    document = tv if provenance["archive_result_sha256"] == M.PINS["tv"][1] else tail
                    node = document
                    for part in provenance["row_path"].strip("/").split("/"):
                        node = node[int(part)] if isinstance(node, list) else node[part]
                    self.assertEqual(M.digest(node), provenance["row_canonical_sha256"])
        # Pinned source permits neither silent child substitution nor namespace inference.
        altered = copy.deepcopy(tail); altered["rows"][0]["source_result_sha256"] = "0"*64
        with self.assertRaisesRegex(ValueError, "tail_child_hash"): M.tail_edges(altered)
        altered = copy.deepcopy(tail); altered["rows"][0]["operator_id"] = True
        with self.assertRaisesRegex(ValueError, "tail_namespace"): M.tail_edges(altered)
        altered = copy.deepcopy(tail); altered["children"][1] = altered["children"][0]
        with self.assertRaisesRegex(ValueError, "tail_children"): M.tail_edges(altered)


if __name__ == '__main__':
    unittest.main()
