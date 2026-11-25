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



@unittest.skipUnless(os.environ.get("MATCH_RETAINED_DIR"), "pinned ZIP fixtures")
class EarlyReviewTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        root = Path(os.environ["MATCH_RETAINED_DIR"])
        cls.tv = M.load_archive(root/"match_v63_secondary.zip", "tv")
        cls.samo = M.load_archive(root/"match_v65_result.zip", "samo")
        cls.bg = M.load_archive(root/"match_bg_dictionary_v5.zip", "bg")
        cls.tail = M.load_archive(root/"match_common4_tail_v15.zip", "tv_tail")
        cls.early = M.load_archive(root/"match_tv_c35_c135.zip", "tv_early")
        cls.history = M.load_history(PATH.parents[2]/"reports/hotel-match-retained175-history-mass-20260926.json")
        cls.report = M.analyse_expanded(cls.tv, cls.samo, cls.bg, cls.tail, cls.history, cls.early)

    def test_full_scope_and_increment(self):
        r = self.report
        self.assertEqual((r["input_count"], r["candidate_pairs_reviewed"]), (175, 161))
        self.assertEqual((r["tv_input_rows"], r["tv_unique_edges"], r["tv_unique_hotels_with_evidence"]), (1171, 1153, 800))
        self.assertEqual((r["early_input_rows"], r["early_overlap_rows"], r["early_overlap_hotels"]), (359, 31, 20))
        self.assertEqual((r["before_early_proven_candidate_hotels"], r["expanded_proven_candidate_hotels"]), (13, 26))
        self.assertEqual(r["new_candidate_hotels_from_early"], [11742,11748,11771,11773,57552,60000,63524,64351,64355,64722,71376,117800,119844])
        self.assertEqual(r["lost_candidate_hotels_after_early_collision_check"], [])
        buckets = r["dossier_ids_by_exact_independent_tv_lane_count"]
        self.assertEqual({k: len(v) for k,v in buckets.items()}, {"0":149,"1":20,"2":6})
        flat = [v for values in buckets.values() for v in values]
        self.assertEqual(len(flat), len(set(flat)))
        self.assertEqual(set(flat), {d["local_hotel_id"] for d in self.samo["dossiers"]})

    def test_three_new_two_operator_candidates(self):
        self.assertEqual(self.report["new_two_lane_candidate_hotels"], [11742,11748,11773])
        rows = {r["local_hotel_id"]: r for r in self.report["candidates_with_proven_tv_lanes"]}
        for local, catalog, natives in [(11742,"183505",("303225","2532")),
                                        (11748,"191231",("306944","2541")),
                                        (11773,"364934",("306998","2535"))]:
            r = rows[local]
            self.assertEqual(r["andromeda_catalog_id"], catalog)
            self.assertEqual({x["namespace"]:x["native_id"] for x in r["proven_lanes"]}, dict(zip(("operator_315","operator_342"), natives)))
            self.assertFalse(r["safe_to_write_now"])

    def test_tail_only_output_remains_byte_identical(self):
        import hashlib
        r = M.analyse_expanded(self.tv, self.samo, self.bg, self.tail, self.history)
        raw = (json.dumps(r, ensure_ascii=False, sort_keys=True, indent=2)+"\n").encode()
        self.assertEqual(hashlib.sha256(raw).hexdigest(), "d04df3f221f39dd50b4f2d67fff25684e55b8323f4d1034eb70539fd2fa737da")

    def test_all_proofs_dereference_to_exact_retained_rows(self):
        docs = {M.PINS["tv"][1]: self.tv, M.TV_TAIL_PIN[1]: self.tail, M.TV_EARLY_PIN[1]: self.early}
        for row in self.report["candidates_with_proven_tv_lanes"]:
            for lane in row["proven_lanes"]:
                for evidence in lane["evidence"]:
                    node = docs[evidence["archive_result_sha256"]]
                    for part in evidence["row_path"].strip("/").split("/"):
                        node = node[int(part)] if isinstance(node, list) else node[part]
                    self.assertEqual(M.digest(node), evidence["row_canonical_sha256"])
                    self.assertEqual(node["tv_hotel_id"], row["local_hotel_id"])
                    self.assertEqual(M.BRIDGES.get(node["operator_id"]), lane["namespace"])
                    self.assertEqual(node["operator_link_sha256"], evidence["tv_link_sha256"])
                    self.assertEqual(node["tour_id_sha256"], evidence["tv_tour_sha256"])
                    self.assertEqual(node["source_result_sha256"], evidence["tv_child_result_sha256"])
                    native = (node["positive_native_candidates"][0] if "positive_native_candidates" in node else node["external_hotel_id"])
                    self.assertEqual(str(native), lane["native_id"])

    def test_no_earlier_risk_or_current_gate_is_cleared(self):
        before = M.analyse_expanded(self.tv, self.samo, self.bg, self.tail, self.history)
        after = {(r["local_hotel_id"], r["andromeda_catalog_id"]):r for r in self.report["candidates_with_proven_tv_lanes"]}
        for old in before["candidates_with_proven_tv_lanes"]:
            new = after[(old["local_hotel_id"], old["andromeda_catalog_id"])]
            self.assertTrue(set(old["historical_review_reasons"]) <= set(new["historical_review_reasons"]))
        for field in ("provider_http_calls","database_reads","database_writes","mapping_writes","accepted_mapping_count"):
            self.assertEqual(self.report[field], 0)
        self.assertFalse(self.report["current_validation_performed"])
        self.assertFalse(self.report["safe_to_write_now"])

    def test_wrong_early_archive_and_symlink_fail_closed(self):
        with tempfile.TemporaryDirectory() as folder:
            p = Path(folder)/"early.zip"; p.write_bytes(b"wrong")
            with self.assertRaisesRegex(ValueError, "zip_hash"): M.load_archive(p, "tv_early")
            link = Path(folder)/"symlink.zip"; link.symlink_to(p)
            with self.assertRaisesRegex(ValueError, "input_file"): M.load_archive(link, "tv_early")

    def test_early_operation_shape_and_zero_boundaries(self):
        for field, value, code in [("operation","wrong","tail_operation"), ("source_sha","0"*40,"tail_source"),
                                    ("input_single_native_edges",358,"tail_count"), ("searched_hotels",400,"tail_scope"),
                                    ("provider_http_calls",1,"tail_zero"), ("mapping_writes",1,"tail_zero"),
                                    ("safe_to_write_now",True,"tail_safety")]:
            with self.subTest(field=field):
                changed = copy.deepcopy(self.early); changed[field] = value
                with self.assertRaisesRegex(ValueError, code): M.tail_edges(changed, early=True)

    def test_early_child_and_namespace_integrity(self):
        changed = copy.deepcopy(self.early); changed["children"][1] = changed["children"][0]
        with self.assertRaisesRegex(ValueError,"tail_children"): M.tail_edges(changed, early=True)
        for field, value, code in [("source_result_sha256","0"*64,"tail_child_hash"),
                                  ("operator_id",True,"tail_namespace"), ("external_hotel_id",True,"id_type"),
                                  ("operator_link_sha256","invalid","tail_edge_hash"), ("safe_to_write_now",True,"tail_edge_safety")]:
            with self.subTest(field=field):
                changed = copy.deepcopy(self.early); changed["rows"][0][field] = value
                with self.assertRaisesRegex(ValueError,code): M.tail_edges(changed, early=True)

    def test_new_data_can_remove_a_false_proof_on_collision(self):
        es = M.tail_edges(self.early, early=True) + M.tail_edges(self.tail)
        dossier = next(d for d in self.samo["dossiers"] if d["local_hotel_id"] == 11742)
        ca = next(c for c in dossier["candidates"] if c["catalog_id"] == "183505")
        edge = next(e for e in es if e["tv_hotel_id"] == 11742 and e["operator_id"] == 25)
        other = copy.deepcopy(edge); other["tv_hotel_id"] = 999999
        out = M.expanded_proofs(11742,ca,es+[other],M.expanded_indexes(es+[other],self.samo["dossiers"]))
        self.assertNotIn("operator_315", {x["namespace"] for x in out})

    def test_repeated_record_is_not_a_new_lane(self):
        es = M.tail_edges(self.early, early=True)
        dossier = next(d for d in self.samo["dossiers"] if d["local_hotel_id"] == 11742)
        ca = next(c for c in dossier["candidates"] if c["catalog_id"] == "183505")
        a = M.expanded_proofs(11742,ca,es,M.expanded_indexes(es,self.samo["dossiers"]))
        b = M.expanded_proofs(11742,ca,es+copy.deepcopy(es),M.expanded_indexes(es+es,self.samo["dossiers"]))
        self.assertEqual(a,b)

    def test_early_historical_other_owner_veto_is_carried(self):
        changed = copy.deepcopy(self.early)
        changed["rows"][0]["anchors"] = [{"supplier_namespace":"andromeda_catalog",
            "decision_status":"accepted", "external_hotel_id":"183505", "local_hotel_id":999999}]
        # Pure-function negative fixture, never a pinned-file validation bypass.
        out = M.analyse_expanded(self.tv,self.samo,self.bg,self.tail,self.history,changed)
        row = next(r for r in out["candidates_with_proven_tv_lanes"] if r["local_hotel_id"]==11742)
        self.assertIn("early_historical_source_other_target",row["historical_review_reasons"])
        self.assertFalse(row["safe_to_write_now"])


if __name__ == '__main__':
    unittest.main()
