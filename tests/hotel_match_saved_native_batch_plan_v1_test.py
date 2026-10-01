"""Mass native join preserves ambiguity and never grants write authority."""
import copy
import importlib.util
import random
import tempfile
import unittest
from pathlib import Path

SOURCE = Path(__file__).resolve().parents[1] / "scripts/diagnostics/hotel_match_saved_native_batch_plan_v1.py"
spec = importlib.util.spec_from_file_location("saved_native_batch", SOURCE)
batch = importlib.util.module_from_spec(spec)
spec.loader.exec_module(batch)


def source(catalog="1", lane="funsun", native="77"):
    return dict(samo_catalog_id=catalog, source_decision_status_v77="pending",
                source_catalog_sha256="a" * 64, source_evidence_sha256="b" * 64,
                **{f"samo_{op}_native_ids": native if op == lane else "" for op in batch.LANES})


def target(hotel="2", lane="funsun", native="77"):
    return dict(tv_hotel_id=hotel, has_samo_mapping="False", samo_catalog_ids_accepted="",
                **{f"tv_{op}_native_ids": native if op == lane else "" for op in batch.LANES},
                **{f"{op}_known_exact": "True" for op in batch.LANES})


class BatchTest(unittest.TestCase):
    def classify(self, sources, targets):
        result = batch.plan(sources, targets)
        self.assertEqual(sum(result["counts"].values()), len(sources))
        self.assertFalse(result["safe_to_write_now"])
        self.assertTrue(all(not r["safe_to_write_now"] for r in result["rows"]))
        return result

    def test_exact_and_bg_prefix_only_in_their_own_lane(self):
        for lane in batch.LANES:
            expected = "10277" if lane == "bg" else "77"
            row = self.classify([source(lane=lane)], [target(lane=lane, native=expected)])["rows"][0]
            self.assertEqual(row["state"], "candidate_current_checks_required")
            wrong_lane = "bg" if lane != "bg" else "anex"
            row = self.classify([source(lane=lane)], [target(lane=wrong_lane, native=expected)])["rows"][0]
            self.assertEqual(row["state"], "no_target_in_saved_slice")

    def test_names_and_price_never_match(self):
        s, t = source(native="11"), target(native="22")
        s["hotel_names_seen"] = t["hotel_name"] = "Same exact name"
        s["price"] = t["price"] = "123456"
        self.assertEqual(self.classify([s], [t])["rows"][0]["candidate_tv_ids"], [])

    def test_no_double_prefix_or_numeric_coercion(self):
        for native in ["077", "77.0", "7.7e1", "77|", "-77", " 77", "77|signed"]:
            row = self.classify([source(native=native)], [target()])["rows"][0]
            self.assertIn("malformed_samo_native_id", row["reasons"])
        row = self.classify([source(lane="bg", native="10277")], [target(lane="bg", native="10277")])["rows"][0]
        self.assertEqual(row["candidate_tv_ids"], [])

    def test_competing_source_and_target_ids_are_held(self):
        r = self.classify([source("1"), source("3")], [target()])
        self.assertEqual(r["counts"], {"conflict_review": 2})
        r = self.classify([source()], [target("2"), target("3")])
        self.assertEqual(r["counts"], {"conflict_review": 1})

    def test_cross_operator_disagreement(self):
        s = source(); s["samo_intourist_native_ids"] = "88"
        r = self.classify([s], [target(), target("3", "intourist", "88")])["rows"][0]
        self.assertIn("operator_targets_disagree", r["reasons"])
        r = self.classify([s], [dict(target(), tv_intourist_native_ids="88")])["rows"][0]
        self.assertEqual(len(r["native_matches"]), 2)
        self.assertEqual(r["state"], "candidate_current_checks_required")

    def test_occupied_and_protected_do_not_block_other_rows(self):
        s = [source("2000086118"), source("3", native="88"), source("4", native="99")]
        t = [target(), dict(target("5", native="88"), has_samo_mapping="True", samo_catalog_ids_accepted="555.0"), target("6", native="99")]
        r = self.classify(s, t)
        self.assertEqual(r["counts"], {"candidate_current_checks_required": 1, "occupied_review": 1, "protected": 1})

    def test_accepted_list_cannot_be_hidden_by_false_flag(self):
        t = dict(target(), samo_catalog_ids_accepted="999")
        self.assertEqual(self.classify([source()], [t])["rows"][0]["state"], "occupied_review")

    def test_unknown_or_conflict_source_never_silent_pending(self):
        for state in ["", "accepted", "conflict", "manual"]:
            row = self.classify([dict(source(), source_decision_status_v77=state)], [target()])["rows"][0]
            self.assertIn("source_decision_protected_or_unknown", row["reasons"])

    def test_duplicate_rows_and_multinative_preserve_hold(self):
        self.assertIn("duplicate_samo_catalog_rows", self.classify([source(), source()], [target()])["rows"][0]["reasons"])
        self.assertIn("duplicate_tv_hotel_rows", self.classify([source()], [target(), target()])["rows"][0]["reasons"])
        s = dict(source(), samo_funsun_native_ids="77|88")
        self.assertIn("multiple_samo_native_ids_same_operator", self.classify([s], [target()])["rows"][0]["reasons"])

    def test_local_anchor_is_not_relabelled_as_tv_identity(self):
        s = dict(source(lane="anex"), linked_local_ids_via_anex_native="999")
        row = self.classify([s], [target()])["rows"][0]
        self.assertEqual(row["state"], "saved_local_anchor_current_checks_required")
        self.assertEqual(row["candidate_local_ids"], ["999"])
        self.assertEqual(row["candidate_tv_ids"], [])
        s["linked_local_ids_via_intourist_native"] = "111"
        self.assertIn("saved_local_anchors_disagree", self.classify([s], [target()])["rows"][0]["reasons"])

    def test_bom_row_provenance_and_file_guards(self):
        import csv
        with tempfile.TemporaryDirectory() as d:
            path = Path(d) / "source.csv"
            row = dict(source(), source_local_hotel_id_v77="")
            with path.open("w", encoding="utf-8-sig", newline="") as f:
                writer = csv.DictWriter(f, fieldnames=list(row)); writer.writeheader(); writer.writerow(row)
            rows, provenance = batch.load_csv(path, "samo")
            self.assertEqual(rows[0]["_csv_record"], 2)
            self.assertEqual(provenance["row_count"], 1)
            self.assertEqual(len(provenance["sha256"]), 64)
            link = Path(d) / "link.csv"; link.symlink_to(path)
            with self.assertRaisesRegex(ValueError, "input_file_size_or_type"):
                batch.load_csv(link, "samo")
            path.write_text("samo_catalog_id\n1\n")
            with self.assertRaisesRegex(ValueError, "input_columns_missing"):
                batch.load_csv(path, "samo")

    def test_no_candidate_cap_hides_late_collision(self):
        sources = [source(str(i), native=str(100000 + i)) for i in range(1, 2001)]
        targets = [target(str(10000 + i), native=str(100000 + i)) for i in range(1, 2001)]
        sources.append(source("9000", native="100001"))
        r = self.classify(sources, targets)
        self.assertEqual(r["counts"], {"candidate_current_checks_required": 1999, "conflict_review": 2})
        before = copy.deepcopy((sources, targets))
        random.Random(4).shuffle(sources); random.Random(5).shuffle(targets)
        self.assertEqual(self.classify(sources, targets), r)
        self.assertEqual(sorted(sources, key=lambda x: int(x["samo_catalog_id"])), sorted(before[0], key=lambda x: int(x["samo_catalog_id"])))


if __name__ == "__main__":
    unittest.main()
