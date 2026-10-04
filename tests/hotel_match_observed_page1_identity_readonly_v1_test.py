#!/usr/bin/env python3
"""Real Python collector CLI and retained-file contract regression tests.

CI requires real PHP. Scratch without PHP uses an explicitly reported local
config subprocess stub only; every Python source CLI and file guard stays real.
"""
from __future__ import annotations
import copy
import hashlib
import importlib.util
import json
import os
import pathlib
import shutil
import subprocess
import sys
import tempfile
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[1]
SOURCE = ROOT / "scripts/diagnostics/hotel_match_observed_page1_identity_readonly_v1.py"
FIXTURE = ROOT / "scripts/diagnostics/fixtures/hotel_match_observed_page1_identity_readonly_v1.json"
spec = importlib.util.spec_from_file_location("page1", SOURCE)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
PHP = shutil.which("php")
if (os.environ.get("MATCH_REQUIRE_REAL_PHP") == "1" or os.environ.get("CI") == "true") and PHP is None:
    raise RuntimeError("mandatory_real_php_missing")


def source_bytes(path, role):
    p = ROOT / path
    if p.exists(): raw = p.read_bytes()
    else:
        p = ROOT.parent / path.replace("/", "-")
        raw = p.read_bytes()[:-1]  # Scratch MCP patch copy adds one trailing LF.
    blob = hashlib.sha1(b"blob " + str(len(raw)).encode() + b"\0" + raw).hexdigest()
    if blob != m.PRODUCER_BLOBS[role]: raise RuntimeError("test_producer_byte_binding")
    return raw


def page_fixture():
    fixture, _ = m.manifest(FIXTURE)
    ref = "b" * 64
    criteria = fixture["criteria"] | {"TOWNFROMINC": "2", "STATEINC": 3}
    offers, raw_ids = [], {}
    for i in range(50):
        operator = "5" if i < 25 else ("315" if i % 2 else "342")
        namespace = "operator_5" if i < 25 else "andromeda_catalog"
        external = str(8000 + i % 9)
        oref = "offer_" + hashlib.sha256(str(i).encode()).hexdigest()
        local = i + 1 if i < 42 else None
        offers.append({"provider":"andromeda", "search_ref":ref, "generation":17, "offer_ref":oref, "selection_enabled":False, "supplier_namespace":namespace, "external_hotel_id":external, "local_hotel_id":local, "operator_ref":operator, "operator":{"5":"ANEX", "315":"FUN&SUN", "342":"Интурист"}[operator], "hotel":"Retained Hotel " + external, "hotel_content":{"source":"andromeda", "image_url":"https://hotels.example.com/photo.jpg", "hotel_url":"https://operator.example.com/hotel?HOTELLIST=804&HOTELLIST=44562&F4=123", "region":"Кемер", "category":5}, "price":{"amount":"123456789PRIVATEPRICE", "currency":"RUB"}, "room_raw":"private room", "transport_context":{"tour_ref":"PRIVATE-TOUR"}})
        raw_ids[oref] = "PRIVATE-RAW-OFFER-" + str(i)
    snapshot = {"provider":"andromeda", "search_ref":ref, "generation":17, "page":1, "pages_count":300, "status":"partial", "offers":offers, "rejected":[], "selection_enabled":False}
    return {"version":1, "search_ref":ref, "generation":17, "status":"partial", "criteria":criteria, "store":{"version":1, "search_ref":ref, "generation":17, "created_at":1791136785, "expires_at":1791137685, "criteria":copy.deepcopy(criteria), "snapshot":snapshot, "raw_ids":raw_ids}, "error":None}


def build_cli_case(base):
    home = pathlib.Path(base) / "home"
    project = home / "www/anytoour.ru"
    runtime = project / "_preview/search3-anex-candidate"
    app = runtime / "app/integrations"
    app.mkdir(parents=True)
    for role, path, dest in [("normalizer", "app/integrations/andromeda-normalizer.php", app / "andromeda-normalizer.php"), ("offer_store", "app/integrations/andromeda-offer-store.php", app / "andromeda-offer-store.php"), ("endpoint", "v2/api-andromeda-search3-preview.php", runtime / "api-andromeda-search3-preview.php")]:
        dest.write_bytes(source_bytes(path, role))
    catalog = home / ".andromeda-retained"
    searches = catalog / "searches"
    searches.mkdir(parents=True)
    config = runtime / ".andromeda-private.php"
    config.write_text("<?php return ['enabled'=>true,'catalog_path'=>" + repr(str(catalog / "catalog.json")) + ",'password'=>'PRIVATE-CREDENTIAL'];")
    page = page_fixture()
    p = searches / (page["search_ref"] + "-1.json")
    p.write_bytes(m.enc(page))
    os.utime(p, (1791136803, 1791136803))
    opdir = home / ".anytoour-match/operations" / m.OP
    opdir.mkdir(parents=True)
    (opdir / "reservation.json").write_bytes(m.enc({"operation":m.OP, "batch":m.BATCH, "source_sha":"a"*40, "provider_http_calls":0, "maximum_writes":0, "state":"reserved_before_retained_read"}))
    env = {key:os.environ[key] for key in ("PATH", "HOME", "LANG", "LC_ALL") if key in os.environ}
    env.update({"ANYTOUR_ROOT":str(project), "MATCH_SOURCE_ROOT":str(ROOT), "MATCH_PRIVATE_DIRECTORY":str(opdir), "MATCH_CURRENT_MANIFEST_PATH":str(FIXTURE), "MATCH_RESULT_PATH":str(opdir / "result.json"), "MATCH_SOURCE_SHA":"a"*40})
    if PHP is None:
        bindir = home / "stub-bin"
        bindir.mkdir()
        stub = bindir / "php"
        stub.write_text("#!" + sys.executable + "\nimport json,sys\nfrom pathlib import Path\na=sys.argv\nassert '-n' in a and 'allow_url_fopen=0' in a and any(x.startswith('disable_functions=') for x in a)\nassert Path(a[-1]).name=='.andromeda-private.php'\nprint(json.dumps({'directory':" + repr(str(catalog)) + "}))\n")
        stub.chmod(0o700)
        env["PATH"] = str(bindir) + os.pathsep + env.get("PATH", "")
    return {"home":home, "project":project, "runtime":runtime, "app":app, "searches":searches, "page_path":p, "page":page, "opdir":opdir, "env":env}


def cli(case):
    return subprocess.run([sys.executable, str(SOURCE), "--execute"], env=case["env"], capture_output=True, text=True, timeout=30)


class Page1Tests(unittest.TestCase):
    def setUp(self):
        self.t = tempfile.TemporaryDirectory()
        self.c = build_cli_case(self.t.name)
        self.fixture, _ = m.manifest(FIXTURE)

    def tearDown(self): self.t.cleanup()

    def store(self):
        self.c["page_path"].write_bytes(m.enc(self.c["page"]))
        os.utime(self.c["page_path"], (1791136803, 1791136803))

    def result(self):
        return json.loads((self.c["opdir"] / "result.json").read_text())

    def failed(self, reason):
        run = cli(self.c)
        self.assertEqual(run.returncode, 2, run.stderr)
        self.assertEqual(run.stderr, "")
        result = self.result()
        self.assertEqual(result["state"], "terminal_failed_no_replay")
        self.assertEqual(result["failure_stage"], reason)
        self.assertEqual(result["examined_offers"], 0)
        self.assertTrue(m.validate_result(result))
        return result

    def test_real_python_cli_success_expired_page_keeps_all_operators(self):
        run = cli(self.c)
        self.assertEqual(run.returncode, 0, run.stderr)
        self.assertEqual(run.stderr, "")
        result, receipt = self.result(), json.loads((self.c["opdir"] / "receipt.json").read_text())
        self.assertTrue(m.validate_result(result, receipt, "a"*40))
        self.assertEqual(json.loads(run.stdout), {"state":"completed_read_only", "examined_offers":50, "accepted":0, "written":0})
        self.assertEqual(result["unresolved_offer_count"], 8)
        self.assertEqual(result["resolved_offer_count"], 42)
        self.assertEqual(result["unique_hotel_identities"], 18)
        self.assertEqual(set(o for r in result["hotel_roster"] for o in r["operator_refs"]), {"5","315","342"})
        self.assertIn("HOTELLIST=804&HOTELLIST=44562", result["hotel_roster"][0]["hotel_urls"][0])
        public = m.enc(result).decode()
        for secret in (self.c["page"]["search_ref"], "PRIVATE-CREDENTIAL", "PRIVATE-RAW-OFFER", "PRIVATE-TOUR", "PRIVATEPRICE", str(self.c["home"]), "TOWNFROMINC", self.c["page"]["store"]["snapshot"]["offers"][0]["offer_ref"]):
            self.assertNotIn(secret, public)
        private = json.loads((self.c["opdir"] / "current-input.json").read_text())
        self.assertEqual(hashlib.sha256(private["retained_page_utf8"].encode()).hexdigest(), result["retained_page_sha256"])
        self.assertEqual(private["retained_page_utf8"].encode(), self.c["page_path"].read_bytes())
        for flag in m.FALSE_FLAGS: self.assertIs(result[flag], False)

    def test_missing_page_consumes_terminal_without_retry(self):
        self.c["page_path"].unlink()
        self.failed("page_missing")
        self.store()
        second = cli(self.c)
        self.assertNotEqual(second.returncode, 0)
        self.assertEqual(self.result()["failure_stage"], "page_missing")

    def test_success_replay_refused_preserves_exact_receipt(self):
        self.assertEqual(cli(self.c).returncode, 0)
        before = {p.name:p.read_bytes() for p in self.c["opdir"].iterdir()}
        self.assertNotEqual(cli(self.c).returncode, 0)
        self.assertEqual(before, {p.name:p.read_bytes() for p in self.c["opdir"].iterdir()})

    def test_page_ambiguity_not_arbitrary_first(self):
        p = self.c["searches"] / ("c"*64 + "-1.json")
        p.write_bytes(self.c["page_path"].read_bytes()); os.utime(p, (1791136803, 1791136803))
        self.failed("page_ambiguous")

    def test_symlink_page_refused(self):
        p = self.c["page_path"]; raw = p.read_bytes(); p.unlink()
        target = self.c["home"] / "payload.json"; target.write_bytes(raw); p.symlink_to(target)
        self.failed("page_symlink")

    def test_symlink_project_refused_before_started(self):
        link = self.c["home"] / "www/alias"; link.symlink_to(self.c["project"], target_is_directory=True)
        self.c["env"]["ANYTOUR_ROOT"] = str(link)
        self.assertNotEqual(cli(self.c).returncode, 0)
        self.assertFalse((self.c["opdir"] / "execution-started.json").exists())

    def test_traversal_root_refused(self):
        self.c["env"]["ANYTOUR_ROOT"] += "/../anytoour.ru"
        self.assertNotEqual(cli(self.c).returncode, 0)
        self.assertFalse((self.c["opdir"] / "execution-started.json").exists())

    def test_changed_producer_hash_fails(self):
        with (self.c["app"] / "andromeda-normalizer.php").open("ab") as out: out.write(b"\n")
        self.failed("producer_source_changed")

    def test_filename_ref_mismatch(self):
        self.c["page"]["search_ref"] = "c"*64; self.store(); self.failed("page_header")

    def test_generation_boolean_not_integer(self):
        self.c["page"]["store"]["generation"] = True; self.store(); self.failed("page_store_binding")

    def test_snapshot_page2_refused(self):
        self.c["page"]["store"]["snapshot"]["page"] = 2; self.store(); self.failed("page_snapshot_binding")

    def test_complete_context_stay_party_filters_strict(self):
        for key, value in (("ADULT", 3), ("NIGHTS_FROM", 8), ("CHECKIN_BEG", "20261013"), ("PAGE", True)):
            with self.subTest(key=key):
                p = page_fixture(); p["criteria"][key] = value
                with self.assertRaisesRegex(ValueError, "page_criteria"): m.validate_page(p, p["search_ref"], self.fixture)
        p = page_fixture(); p["criteria"]["HOTELS"] = "123"
        with self.assertRaisesRegex(ValueError, "page_filter_keys"): m.validate_page(p, p["search_ref"], self.fixture)

    def test_fifty_offers_count_bound(self):
        self.c["page"]["store"]["snapshot"]["offers"].pop(); self.store(); self.failed("page_offer_count")

    def test_offer_namespace_not_tv_id(self):
        self.c["page"]["store"]["snapshot"]["offers"][0]["supplier_namespace"] = "andromeda_operator_5"
        self.store(); self.failed("offer_namespace")

    def test_offer_ref_duplicate(self):
        offers = self.c["page"]["store"]["snapshot"]["offers"]
        offers[1]["offer_ref"] = offers[0]["offer_ref"]; self.store(); self.failed("offer_reference")

    def test_identity_text_secret_refused_without_leak(self):
        self.c["page"]["store"]["snapshot"]["offers"][0]["hotel"] = "token=PRIVATE-SECRET"
        self.store(); result = self.failed("identity_secret_like_text")
        self.assertNotIn("PRIVATE-SECRET", m.enc(result).decode())

    def test_unsafe_url_query_stays_private_and_all_safe_tokens_retained(self):
        url = "https://operator.example.com/hotel?token=PRIVATE-SECRET&HOTELLIST=804&HOTELLIST=44562"
        self.c["page"]["store"]["snapshot"]["offers"][0]["hotel_content"]["hotel_url"] = url
        self.store(); self.assertEqual(cli(self.c).returncode, 0)
        result = self.result(); self.assertNotIn("PRIVATE-SECRET", m.enc(result).decode())
        self.assertIn(hashlib.sha256(url.encode()).hexdigest(), [h for r in result["hotel_roster"] for h in r["redacted_hotel_url_sha256"]])
        self.assertIn(url, (self.c["opdir"] / "current-input.json").read_text())

    def test_private_url_targets_redacted(self):
        for url in ("https://127.0.0.1/hotel", "https://host.internal/hotel", "https://user:pass@operator.example.com/hotel", "https://operator.example.com/hotel#PRIVATE", "https://operator.example.com/hotel?sid=PRIVATE"):
            with self.subTest(url=url): self.assertIsNone(m.public_url(url))

    def test_invalid_fixture_float_time_and_changed_context_refused(self):
        for key, value in (("created_at", 1791136785.0), ("observed_normalized_offers", True), ("criteria", {}), ("precursor", {})):
            p = self.c["home"] / "wrongfixture.json"; data = copy.deepcopy(self.fixture); data[key] = value; p.write_bytes(m.enc(data))
            with self.subTest(key=key), self.assertRaises(ValueError): m.manifest(p)

    def test_public_extra_private_key_or_authority_flag_refused(self):
        self.assertEqual(cli(self.c).returncode, 0)
        result = self.result()
        for key, value in (("search_ref", "b"*64), ("raw_samo_evidence_verified", True), ("examined_offers", True), ("written", 1)):
            changed = copy.deepcopy(result); changed[key] = value
            with self.subTest(key=key), self.assertRaises(ValueError): m.validate_result(changed)

    def test_real_saved_page_does_not_read_auth_or_page2(self):
        auth = self.c["searches"] / ("b"*64 + "-auth.json"); auth.symlink_to(self.c["home"] / "missing-auth")
        page2 = self.c["searches"] / ("b"*64 + "-1791136785-2.json"); page2.write_text("not-read")
        self.assertEqual(cli(self.c).returncode, 0)

    def test_concurrent_source_entry_only_one_exclusive_run(self):
        args = [sys.executable, str(SOURCE), "--execute"]
        a = subprocess.Popen(args, env=self.c["env"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        b = subprocess.Popen(args, env=self.c["env"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        a.communicate(timeout=30); b.communicate(timeout=30)
        self.assertEqual(sorted((a.returncode, b.returncode)), [0, 1])
        self.assertEqual(self.result()["state"], "completed_read_only")

    def test_config_symlink_refused_without_loading_it(self):
        config = self.c["runtime"] / ".andromeda-private.php"
        raw = config.read_bytes(); config.unlink()
        target = self.c["home"] / "config.php"; target.write_bytes(raw); config.symlink_to(target)
        self.failed("unsafe_file_path")

    def test_output_symlink_blocks_before_started(self):
        (self.c["opdir"] / "result.json").symlink_to(self.c["home"] / "outside-result")
        run = cli(self.c)
        self.assertNotEqual(run.returncode, 0)
        self.assertNotIn(str(self.c["home"]), run.stderr)
        self.assertFalse((self.c["opdir"] / "execution-started.json").exists())
        self.assertFalse((self.c["home"] / "outside-result").exists())

    def test_page_timestamp_and_session_generation_bound(self):
        p = page_fixture(); p["store"]["created_at"] += 1
        with self.assertRaisesRegex(ValueError, "page_store_binding"): m.validate_page(p, p["search_ref"], self.fixture)
        p = page_fixture(); p["store"]["snapshot"]["offers"][0]["generation"] = 18
        with self.assertRaisesRegex(ValueError, "offer_context"): m.validate_page(p, p["search_ref"], self.fixture)

    def test_inventory_limit_stops_instead_of_unbounded_drain(self):
        from unittest.mock import patch
        for i in range(3): (self.c["searches"] / ("ignored-" + str(i))).write_text("ignored")
        with patch.object(m, "MAX_ENTRIES", 2), patch.object(m, "searches_directory", return_value=(self.c["searches"], self.c["runtime"])):
            with self.assertRaisesRegex(ValueError, "inventory_bound"): m.capture(self.c["project"], self.fixture)

    def test_bytes_and_duplicate_json_resource_guards(self):
        p = self.c["home"] / "bounded.json"; p.write_bytes(b"x"*100)
        with self.assertRaisesRegex(ValueError, "unsafe_file_type_or_size"): m.file_bytes(p, 50)
        with self.assertRaisesRegex(ValueError, "duplicate_json_key"): m.parsed('{"id":1,"id":2}')

    def test_failed_counter_boolean_rejected(self):
        self.c["page_path"].unlink(); result = self.failed("page_missing")
        result["retained_page_bytes"] = False
        with self.assertRaises(ValueError): m.validate_result(result)


if __name__ == "__main__":
    print("config_dependency=real_php" if PHP else "config_dependency=explicit_local_php_stub; mandatory_CI_requires_real_php")
    unittest.main()
