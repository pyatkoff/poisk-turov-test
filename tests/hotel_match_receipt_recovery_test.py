#!/usr/bin/env python3
"""Supplier-free mutation tests for offline receipt recovery, not an acceptor."""
import copy
import importlib.util
import json
import tempfile
import unittest
import warnings
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("recovery", ROOT / "scripts/diagnostics/hotel_match_receipt_recovery.py")
m = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(m)


def seal(files, result, prefix=""):
    raw = m.encoded(result)
    files[prefix + "result.json"] = raw
    files[prefix + "receipt.json"] = m.encoded({
        **{k: result[k] for k in ("operation_id", "source_sha", "state")},
        "result_sha256": m.sha(raw), "readback_verified": True,
        "database_writes": 0, "no_replay": True})


def fixture():
    c = {"operation_id": "current", "source_sha": "b" * 40,
         "state": "completed_read_only", "database_writes": 0, "supplier_calls": 0,
         "no_replay": True, "target_count": 2,
         "targets": [{"andromeda_hotel_id": aid, "country_id": 1, "local_hotel_id": lid,
                      "decision_status": "accepted", "frequency": freq,
                      "local": {"name": "BEACH HOTEL"}}
                     for aid, lid, freq in (("100", 10, 3), ("200", 20, 8))],
         "existing_operator_rows": [{"supplier_namespace": "operator_5", "external_hotel_id": "50",
                                     "decision_status": "accepted", "local_hotel_id": 10}]}
    cf = {}; seal(cf, c, "server/")
    saved = {"operator_key": "5", "native_hotel_id": "50", "andromeda_hotel_id": "100",
             "country_id": 1, "hotel_name": "OLD NAME"}
    params = {"OPERATORS": "115", "HOTELS": "200", "PAGE": 1}
    request_hash = m.sha(m.encoded(params))
    new = {"operator_key": "115", "native_hotel_id": "60", "andromeda_hotel_id": "200",
           "country_id": 1, "hotel_name": "PALM HOTEL", "is_operator_hotel_key": False,
           "request_sha256": request_hash, "response_sha256": "e" * 64}
    checkpoint = {"query_index": 0, "page": 1, "operator_key": "115", "country_id": 1,
                  "hotel_ids": ["200"], "params": params, "request_sha256": request_hash,
                  "response_sha256": "e" * 64, "valid_rows": 1, "facts": [new],
                  "observations": [{"operatorKey": 115, "hotelKey": 200, "isOperatorHotelKey": 0,
                                    "original": {"hotelKey": 60}}]}
    plan = {"saved_facts": [saved], "saved_covered_count": 1,
            "current_result_sha256": m.sha(cf["server/result.json"])}
    p = m.encoded(plan)
    ef = {"plan.json": p, "reserved-plan.json": p,
          "reservation.json": m.encoded({"operation_id": "evidence", "source_sha": "a" * 40,
                                          "plan_sha256": m.sha(p)}),
          "request-000.json": m.encoded({"operation_id": "evidence", "params": params,
                                         "request_sha256": request_hash, "state": "reserved_before_supplier_access"}),
          "response-000.json": m.encoded(checkpoint)}
    result = {"operation_id": "evidence", "source_sha": "a" * 40, "state": "completed",
              "database_writes": 0, "mapping_writes": 0, "tourvisor_calls": 0, "all_calls": 0,
              "booking_calls": 0, "no_replay": True, "supplier_calls": 2, "price_calls": 1,
              "plan_summary": {"current_result_sha256": plan["current_result_sha256"]},
              "facts": [saved, new], "unique_pair_count": 2,
              "pages": [{k: v for k, v in checkpoint.items() if k not in ("facts", "observations")}]}
    seal(ef, result)
    return ef, cf


class RecoveryTest(unittest.TestCase):
    def setUp(self):
        self.e, self.c = fixture()

    def change(self, files, name, transform):
        value = m.parse(files[name]); transform(value); files[name] = m.encoded(value)

    def failure(self, reason):
        with self.assertRaisesRegex(ValueError, reason):
            m.recover(self.e, self.c)

    def test_recovers_evidence_without_accepting_anchor(self):
        r = m.recover(self.e, self.c)
        self.assertEqual((r["pair_count"], r["recovered_new_pair_count"]), (2, 1))
        self.assertEqual(r["relation_only_counts"]["missing_native_link_requires_current_guards"], 1)
        self.assertFalse(r["auto_accept"]); self.assertFalse(r["apply_manifest"])
        self.assertTrue(all(x["auto_accept"] is False for x in r["rows"]))
        self.assertEqual(r["rows"][0]["fact"]["hotel_name"], "PALM HOTEL")
        self.assertEqual(r["rows"][0]["snapshot_anchor"]["local"]["name"], "BEACH HOTEL")

    def test_inputs_are_not_mutated(self):
        before = copy.deepcopy((self.e, self.c)); m.recover(self.e, self.c)
        self.assertEqual((self.e, self.c), before)

    def test_stopped_unknown_request_not_replayed_or_counted_as_evidence(self):
        r = m.parse(self.e["result.json"]); r.update(state="stopped_no_retry", price_calls=2, supplier_calls=3)
        seal(self.e, r); self.e["request-001.json"] = self.e["request-000.json"]
        out = m.recover(self.e, self.c)
        self.assertEqual(out["reserved_without_checkpoint"], ["request-001.json"])
        self.assertEqual(out["source_operation_state"], "stopped_no_retry")
        self.assertEqual(out["recovered_new_pair_count"], 1)
        self.assertEqual(out["supplier_calls"], 0)

    def test_unknown_terminal_state(self):
        r = m.parse(self.e["result.json"]); r["state"] = "unknown"; seal(self.e, r)
        self.failure("unknown_terminal_result")

    def test_tampered_result(self):
        self.e["result.json"] += b"\n"; self.failure("receipt_digest_mismatch")

    def test_unverified_receipt(self):
        self.change(self.e, "receipt.json", lambda r: r.update(readback_verified=False))
        self.failure("unverified_receipt")

    def test_receipt_source_mismatch(self):
        self.change(self.e, "receipt.json", lambda r: r.update(source_sha="bad"))
        self.failure("receipt_identity_mismatch")

    def test_no_replay_required(self):
        self.change(self.e, "receipt.json", lambda r: r.update(no_replay=False))
        self.failure("no_replay_missing")

    def test_non_read_only_source(self):
        r = m.parse(self.e["result.json"]); r["mapping_writes"] = 1; seal(self.e, r)
        self.failure("unexpected_external_side_effect")

    def test_plan_changed(self):
        self.e["plan.json"] += b"\n"; self.failure("plan_changed_after_reservation")

    def test_wrong_snapshot(self):
        r = m.parse(self.c["server/result.json"]); r["target_count"] = 99; seal(self.c, r, "server/")
        self.failure("wrong_current_snapshot")

    def test_checkpoint_requires_prior_reservation(self):
        del self.e["request-000.json"]; self.failure("checkpoint_without_reservation")

    def test_request_digest(self):
        self.change(self.e, "request-000.json", lambda r: r.update(request_sha256="0" * 64))
        self.failure("request_digest_mismatch")

    def test_request_context(self):
        self.change(self.e, "request-000.json", lambda r: r["params"].update(PAGE=2))
        self.failure("request_context_mismatch")

    def test_original_id_observation_required(self):
        self.change(self.e, "response-000.json", lambda r: r.update(observations=[]))
        self.failure("fact_without_original_observation")

    def test_operator_key_semantics_required(self):
        self.change(self.e, "response-000.json", lambda r: r["observations"][0].update(isOperatorHotelKey=1))
        self.failure("fact_without_original_observation")

    def test_invented_terminal_fact(self):
        r = m.parse(self.e["result.json"]); r["facts"][1]["native_hotel_id"] = "invented"; seal(self.e, r)
        self.failure("terminal_union_mismatch")

    def test_completed_cannot_have_unknown_request(self):
        r = m.parse(self.e["result.json"]); r["price_calls"] = 2; seal(self.e, r)
        self.e["request-001.json"] = self.e["request-000.json"]
        self.failure("completed_with_unknown_request")

    def test_duplicate_json_key(self):
        with self.assertRaisesRegex(ValueError, "duplicate_json_key"):
            m.parse(b'{"a":1,"a":2}')

    def test_archive_guards(self):
        for bad_name, reason in (("../result.json", "unsafe_zip_member"), ("/result.json", "unsafe_zip_member")):
            with self.subTest(bad_name=bad_name), tempfile.TemporaryDirectory() as temp:
                p = Path(temp) / "input.zip"
                with zipfile.ZipFile(p, "w") as z: z.writestr(bad_name, "{}")
                with self.assertRaisesRegex(ValueError, reason): m.archive(p, m.sha(p.read_bytes()))

    def test_archive_digest(self):
        with tempfile.TemporaryDirectory() as temp:
            p = Path(temp) / "input.zip"
            with zipfile.ZipFile(p, "w") as z: z.writestr("result.json", "{}")
            with self.assertRaisesRegex(ValueError, "archive_digest_mismatch"): m.archive(p, "0" * 64)

    def test_duplicate_archive_member(self):
        with tempfile.TemporaryDirectory() as temp:
            p = Path(temp) / "input.zip"
            with warnings.catch_warnings(), zipfile.ZipFile(p, "w") as z:
                warnings.simplefilter("ignore", UserWarning)
                z.writestr("result.json", "{}"); z.writestr("result.json", "{}")
            with self.assertRaisesRegex(ValueError, "duplicate_zip_member"): m.archive(p, m.sha(p.read_bytes()))


if __name__ == "__main__": unittest.main()
