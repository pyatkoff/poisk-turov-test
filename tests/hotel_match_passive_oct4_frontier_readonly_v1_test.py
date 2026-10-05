#!/usr/bin/env python3
"""Sealed passive observation capture tests.

Capture and no-replay tests invoke the actual Python CLI. CI additionally runs
its actual PHP DB subprocess against PDO SQLite using the byte-pinned existing
DB entrypoint. Scratch without PHP uses a reported local subprocess stand-in.
"""
from __future__ import annotations

import copy
import hashlib
import importlib.util
import io
import json
import os
import pathlib
import shutil
import sqlite3
import stat
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

ROOT = pathlib.Path(__file__).resolve().parents[1]
SOURCE = ROOT / "scripts/diagnostics/hotel_match_passive_oct4_frontier_readonly_v1.py"
FIXTURE = ROOT / "scripts/diagnostics/fixtures/hotel_match_passive_oct4_frontier_readonly_v1.json"
PRODUCER_PATH = "app/integrations/andromeda-hotel-observations.php"
PRODUCER_BLOB = "5fdb35ed22821ce6ccbc24ad86e12e546e76e799"
DB_ENTRYPOINT_BLOB = "4ac8258ee4933c74ac20c2c9b771dfc88ab7537c"
TABLE = "andromeda_search_hotel_observations"
COLUMNS = ("observation_sha256", "search_evidence_sha256", "supplier_namespace",
           "external_hotel_id", "hotel_name", "operator_refs_json", "operator_names_json",
           "country_id", "country_name", "region_name", "category", "description_text",
           "image_url", "hotel_url", "content_sha256", "observed_at_utc")
spec = importlib.util.spec_from_file_location("passive_oct4", SOURCE)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
PHP = shutil.which("php")
if (os.environ.get("MATCH_REQUIRE_REAL_PHP") == "1" or os.environ.get("CI") == "true") and PHP is None:
    raise RuntimeError("mandatory_real_php_missing")


def php_content_bytes(row):
    """Independent representation of the producer's ordered content array."""
    value = [row[k] for k in ("hotel_name", "country_id", "country_name", "region_name",
                             "category", "description_text", "image_url", "hotel_url")]
    value += [json.loads(row["operator_refs_json"]), json.loads(row["operator_names_json"])]
    raw = json.dumps(value, ensure_ascii=False, separators=(",", ":"), allow_nan=False)
    return raw.replace("\u2028", "\\u2028").replace("\u2029", "\\u2029").encode()


def bind_hashes(row):
    row["observation_sha256"] = hashlib.sha256((row["search_evidence_sha256"] + "\0" +
        row["supplier_namespace"] + "\0" + row["external_hotel_id"]).encode()).hexdigest()
    row["content_sha256"] = hashlib.sha256(php_content_bytes(row)).hexdigest()
    return row


def valid_row(index=1, **changes):
    row = dict(zip(COLUMNS, [None] * len(COLUMNS)))
    row.update({"search_evidence_sha256": hashlib.sha256(("search-" + str(index)).encode()).hexdigest(),
        "supplier_namespace": "andromeda_catalog", "external_hotel_id": str(9000 + index),
        "hotel_name": "Отель / Hotel " + str(index), "operator_refs_json": '["315","342","5"]',
        "operator_names_json": '["ANEX","FUN&SUN","Интурист"]', "country_id": 4,
        "country_name": "Турция", "region_name": "Кемер", "category": 5,
        "description_text": "Строка один\nСтрока два / сохранена", "image_url": "https://operator.com/image.jpg",
        "hotel_url": "https://operator.com/hotel?HOTELLIST=804&HOTELLIST=44562",
        "observed_at_utc": "2026-10-04 14:51:23"})
    row.update(changes)
    return bind_hashes(row)


def producer_bytes():
    path = ROOT / PRODUCER_PATH
    if not path.exists():
        path = ROOT.parent / "app-integrations-andromeda-hotel-observations.php"
    raw = path.read_bytes()
    actual = hashlib.sha1(b"blob " + str(len(raw)).encode() + b"\0" + raw).hexdigest()
    if actual != PRODUCER_BLOB:
        raise RuntimeError("test_producer_byte_binding")
    return raw


def db_entrypoint_bytes():
    path = ROOT / "v2/data/db-v1.php"
    if not path.exists(): path = ROOT.parent / "v2-data-db-v1.php"
    raw = path.read_bytes()
    actual = hashlib.sha1(b"blob " + str(len(raw)).encode() + b"\0" + raw).hexdigest()
    if actual != DB_ENTRYPOINT_BLOB: raise RuntimeError("test_db_entrypoint_byte_binding")
    return raw


def write_db(case, rows, with_table=True):
    path = case["dbfixture"]
    if path.exists(): path.unlink()
    with sqlite3.connect(path) as db:
        if with_table:
            types = ["INTEGER" if key in ("country_id", "category") else "TEXT" for key in COLUMNS]
            db.execute("CREATE TABLE " + TABLE + " (" + ",".join(k + " " + t for k, t in zip(COLUMNS, types)) + ")")
            db.executemany("INSERT INTO " + TABLE + " VALUES (" + ",".join("?" for _ in COLUMNS) + ")",
                           [[row[k] for k in COLUMNS] for row in rows])
    case["rows"] = copy.deepcopy(sorted(rows, key=lambda row: (row["observed_at_utc"], row["observation_sha256"])))


def build_cli_case(base):
    home = pathlib.Path(base) / "home"
    project = home / "www/anytoour.ru"
    runtime = project / "_preview/search3-anex-candidate"
    app = runtime / "app/integrations"
    app.mkdir(parents=True)
    (app / "andromeda-hotel-observations.php").write_bytes(producer_bytes())
    data = project / "data"
    data.mkdir()
    dbfixture = data / "fixture.sqlite"
    entry = data / "db-v1.php"
    entry.write_bytes(db_entrypoint_bytes())
    config = project / "config.php"
    config.write_text("<?php define('ANYTOUR_DATA_DSN','sqlite:' . __DIR__ . '/data/fixture.sqlite'); "
                      "define('ANYTOUR_DATA_DB_USER','fixture'); define('ANYTOUR_DATA_DB_PASSWORD','');\n")
    private = home / ".anytoour-match"
    opdir = private / "operations" / m.OP
    opdir.mkdir(parents=True)
    reservation = {"operation": m.OP, "batch": m.BATCH, "source_sha": "a" * 40,
                   "provider_http_calls": 0, "maximum_writes": 0, "state": "reserved_before_database_read"}
    (opdir / "reservation.json").write_bytes(m.enc(reservation))
    marker = private / "passive-oct4-before175942-batch.json"
    marker.write_bytes(m.enc(reservation))
    env = {key: os.environ[key] for key in ("PATH", "HOME", "LANG", "LC_ALL") if key in os.environ}
    env.update({"ANYTOUR_ROOT": str(project), "MATCH_SOURCE_ROOT": str(ROOT),
                "MATCH_PRIVATE_DIRECTORY": str(opdir), "MATCH_CURRENT_MANIFEST_PATH": str(FIXTURE),
                "MATCH_RESULT_PATH": str(opdir / "result.json"), "MATCH_SOURCE_SHA": "a" * 40})
    if PHP is None:
        bindir = home / "stub-bin"
        bindir.mkdir()
        stub = bindir / "php"
        # Only the data subprocess is substituted. Python execution, path
        # guards, immutable files, row validation and receipt stay real.
        stub.write_text("#!" + sys.executable + "\n" +
            "import json,os,sqlite3,sys\nfrom pathlib import Path\n" +
            "a=sys.argv\nassert '-n' not in a\nassert 'allow_url_fopen=0' in a\n" +
            "assert any(x.startswith('disable_functions=') for x in a)\n" +
            "assert 'START TRANSACTION READ ONLY' in a[-1] and 'PRAGMA query_only=ON' in a[-1]\n" +
            "db=sqlite3.connect(str(Path(os.environ['ANYTOUR_ROOT'])/'data/fixture.sqlite'))\n" +
            "db.row_factory=sqlite3.Row\ndb.execute('PRAGMA query_only=ON')\ndb.execute('BEGIN')\n" +
            "try:\n rows=[dict(x) for x in db.execute(" + repr("SELECT " + ",".join(COLUMNS) +
             " FROM " + TABLE + " WHERE observed_at_utc>='2026-10-04 00:00:00' AND observed_at_utc<'2026-10-04 17:59:42' ORDER BY observed_at_utc,observation_sha256 LIMIT 5001") + ")]\n" +
            " out={'schema':'match-passive-oct4-db-projection/1','rows':rows,'database_reads':1,'database_read_attempts':1,'read_transaction_rolled_back':True,'failure_stage':None}\n" +
            "except sqlite3.OperationalError:\n out={'schema':'match-passive-oct4-db-projection/1','rows':None,'database_reads':0,'database_read_attempts':1,'read_transaction_rolled_back':True,'failure_stage':'db_table_missing'}\n" +
            "finally:\n db.rollback();db.close()\nprint(json.dumps(out,ensure_ascii=False,separators=(',',':')))\n")
        stub.chmod(0o700)
        env["PATH"] = str(bindir) + os.pathsep + env.get("PATH", "")
    case = {"home": home, "project": project, "runtime": runtime, "producerapp": app,
            "dbfixture": dbfixture, "dbentry": entry, "config": config, "opdir": opdir, "marker": marker,
            "env": env, "reservation": reservation}
    write_db(case, [valid_row()])
    return case


def cli(case):
    return subprocess.run([sys.executable, str(SOURCE), "--execute"], env=case["env"],
                          capture_output=True, text=True, timeout=45)


class PassiveOct4Tests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.c = build_cli_case(self.temp.name)
        self.fixture, _ = m.manifest(FIXTURE)

    def tearDown(self): self.temp.cleanup()

    def result(self): return json.loads((self.c["opdir"] / "result.json").read_text())

    def execute_ok(self, expected_state="completed_read_only"):
        before = self.c["dbfixture"].read_bytes()
        run = cli(self.c)
        self.assertEqual(run.returncode, 0, run.stderr)
        self.assertEqual(run.stderr, "")
        self.assertEqual(self.c["dbfixture"].read_bytes(), before)
        result = self.result()
        self.assertEqual(result["state"], expected_state)
        receipt = json.loads((self.c["opdir"] / "receipt.json").read_text())
        self.assertTrue(m.validate_result(result, receipt, "a" * 40))
        self.assertEqual(json.loads(run.stdout), {"state": expected_state,
            "rows_examined": result["rows_examined"], "accepted": 0, "written": 0})
        return result

    def test_real_cli_success_full_private_rows_and_digest_bound(self):
        result = self.execute_ok()
        self.assertEqual((result["rows_captured"], result["rows_examined"], result["rows_retained"], result["rows_held"]), (1, 1, 1, 0))
        self.assertTrue(result["read_transaction_rolled_back"])
        self.assertEqual(result["database_reads"], 1)
        private = json.loads((self.c["opdir"] / "current-input.json").read_text())
        projection = json.loads(private["db_projection_utf8"])
        self.assertEqual(private["db_projection"], projection)
        self.assertEqual(projection["rows"], self.c["rows"])
        self.assertEqual(hashlib.sha256(m.enc(private)).hexdigest(), result["private_input_sha256"])
        self.assertEqual(result["rows"][0]["raw_row_sha256"], hashlib.sha256(m.enc(projection["rows"][0])).hexdigest())
        for flag in m.FALSE_FLAGS: self.assertIs(result[flag], False)
        for counter in ("provider_http_calls", "physical_http_attempts", "database_writes", "mapping_writes", "booking_calls", "lead_calls", "accepted", "written"):
            self.assertEqual(result[counter], 0)

    def test_complete_raw_capture_is_sealed_before_any_row_sanitizer(self):
        secret = "https://operator.com/hotel?token=PRIVATE-BEFORE-PROJECTION"
        write_db(self.c, [valid_row(1), valid_row(2, hotel_url=secret)])
        original = m.project_row
        examined = []
        def checked(row, index):
            private_path = self.c["opdir"] / "current-input.json"
            self.assertTrue(private_path.is_file())
            private = json.loads(private_path.read_text())
            raw_rows = private["db_projection"]["rows"]
            self.assertEqual(raw_rows, self.c["rows"])
            self.assertEqual(raw_rows, json.loads(private["db_projection_utf8"])["rows"])
            self.assertTrue(all(set(r) == set(COLUMNS) for r in raw_rows))
            self.assertIn(secret, [r["hotel_url"] for r in raw_rows])
            examined.append(index)
            return original(row, index)
        with patch.object(m, "project_row", side_effect=checked), patch.dict(os.environ, self.c["env"], clear=True), patch("sys.stdout", new=io.StringIO()):
            code = m.execute(self.c["project"], self.c["opdir"], FIXTURE, ROOT,
                             self.c["opdir"] / "result.json", "a" * 40)
        self.assertEqual(code, 0)
        self.assertEqual(len(examined), 2)

    def test_operator_ref_and_name_sets_are_independent_not_zipped(self):
        row = valid_row(operator_refs_json='["315","342","5"]', operator_names_json='["ANEX"]')
        write_db(self.c, [row])
        result = self.execute_ok()
        self.assertEqual(result["rows"][0]["operator_refs"], ["315", "342", "5"])
        self.assertEqual(result["rows"][0]["operator_names"], ["ANEX"])

    def test_literal_newlines_unicode_and_optional_empty_fields(self):
        row = valid_row(hotel_name="Отель\nДве строки / Unicode", region_name="", description_text=None, image_url=None, hotel_url=None, category=None)
        write_db(self.c, [row])
        result = self.execute_ok()
        self.assertTrue(result["rows"][0]["content_hash_verified"])
        self.assertEqual(result["rows"][0]["region_name"], "")
        self.assertEqual(result["rows"][0]["hotel_name"], row["hotel_name"])

    def test_unicode_slashes_and_line_separator_php_hash_contract(self):
        row = valid_row(hotel_name="Жемчужина / Hotel\u2028Вторая\u2029Третья", description_text="Описание / café")
        projected = m.project_row(row, 1)
        self.assertEqual(projected["state"], "retained_normalized_identity_candidate")
        self.assertTrue(projected["content_hash_verified"])
        if PHP is not None:
            content = [row[k] for k in ("hotel_name", "country_id", "country_name", "region_name", "category", "description_text", "image_url", "hotel_url")]
            content += [json.loads(row["operator_refs_json"]), json.loads(row["operator_names_json"])]
            run = subprocess.run([PHP, "-r", "echo json_encode(json_decode($argv[1],true,512,JSON_THROW_ON_ERROR),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);", json.dumps(content, ensure_ascii=False)], capture_output=True, timeout=10)
            self.assertEqual(run.returncode, 0)
            self.assertEqual(run.stdout, php_content_bytes(row))

    def test_real_php_pinned_producer_generates_valid_projection(self):
        if PHP is None: self.skipTest("real PHP producer parity is mandatory in CI")
        offer = {"provider": "andromeda", "local_hotel_id": None, "supplier_namespace": "operator_5", "external_hotel_id": "804", "hotel": "Новый / Отель", "operator_ref": "5", "operator": "ANEX", "hotel_content": {"region": "Кемер", "category": 5, "description": "Текст\nДва / café", "hotel_url": "https://operator.com/hotel/804"}}
        page = {"provider": "andromeda", "search_ref": "fixture-search", "generation": 1, "page": 1, "offers": [offer]}
        code = "require $argv[1];$r=AnyTourAndromedaHotelObservations::rows(json_decode($argv[2],true),['local_country_id'=>4,'local_country_name'=>'Турция'])[0];$r['operator_refs_json']=json_encode($r['operator_refs']);$r['operator_names_json']=json_encode($r['operator_names'],JSON_UNESCAPED_UNICODE);unset($r['operator_refs'],$r['operator_names']);$r['observed_at_utc']='2026-10-04 14:51:23';echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);"
        run = subprocess.run([PHP, "-r", code, str(self.c["producerapp"] / "andromeda-hotel-observations.php"), json.dumps(page, ensure_ascii=False)], capture_output=True, text=True, timeout=10)
        self.assertEqual(run.returncode, 0, run.stderr)
        projected = m.project_row(json.loads(run.stdout), 1)
        self.assertEqual(projected["state"], "retained_normalized_identity_candidate")
        self.assertTrue(projected["content_hash_verified"])
        self.assertTrue(projected["observation_sha256_verified"])

    def test_wrong_namespace_one_hold_does_not_drop_valid_neighbor(self):
        write_db(self.c, [valid_row(1), valid_row(2, supplier_namespace="tourvisor")])
        result = self.execute_ok("completed_with_holds")
        self.assertEqual((result["rows_retained"], result["rows_held"]), (1, 1))
        self.assertEqual({r["state"] for r in result["rows"]}, {"row_hold", "retained_normalized_identity_candidate"})

    def test_content_hash_error_is_per_row_hold_private_raw_preserved(self):
        bad = valid_row(2); bad["content_sha256"] = "0" * 64
        write_db(self.c, [valid_row(1), bad])
        result = self.execute_ok("completed_with_holds")
        self.assertEqual(result["rows_retained"], 1)
        held = [r for r in result["rows"] if r["state"] == "row_hold"][0]
        self.assertFalse(held["content_hash_verified"])
        self.assertIn(bad, json.loads((self.c["opdir"] / "current-input.json").read_text())["db_projection"]["rows"])

    def test_observation_hash_mismatch_is_per_row_hold(self):
        bad = valid_row(); bad["observation_sha256"] = "0" * 64
        row = m.project_row(bad, 1)
        self.assertEqual(row["state"], "row_hold")
        self.assertFalse(row["observation_sha256_verified"])

    def test_invalid_typed_country_category_and_operator_json_are_per_row_holds(self):
        for field, value in (("country_id", True), ("category", 23), ("operator_refs_json", '{}'),
                             ("operator_names_json", '["ANEX","ANEX"]')):
            row = valid_row(); row[field] = value; bind_hashes(row)
            with self.subTest(field=field):
                self.assertEqual(m.project_row(row, 1)["state"], "row_hold")

    def test_wrong_window_is_per_row_hold_at_both_edges(self):
        for observed in ("2026-10-03 23:59:59", "2026-10-04 17:59:42", "2026-10-04 17:59:45"):
            with self.subTest(observed=observed):
                row = m.project_row(valid_row(observed_at_utc=observed), 1)
                self.assertEqual(row["state"], "row_hold")
                self.assertTrue(row["holds"])

    def test_actual_sql_scope_excludes_consumed_page1_and_previous_day(self):
        write_db(self.c, [valid_row(1, observed_at_utc="2026-10-04 00:00:00"), valid_row(2, observed_at_utc="2026-10-04 17:59:41"), valid_row(3, observed_at_utc="2026-10-04 17:59:42"), valid_row(4, observed_at_utc="2026-10-03 23:59:59")])
        result = self.execute_ok()
        self.assertEqual(result["rows_captured"], 2)
        self.assertEqual({r["external_hotel_id"] for r in result["rows"]}, {"9001", "9002"})

    def test_empty_capture_is_sealed_and_not_claimed_as_matching_gain(self):
        write_db(self.c, [])
        result = self.execute_ok()
        self.assertEqual((result["rows_captured"], result["rows_examined"], result["rows_retained"], result["rows_held"]), (0, 0, 0, 0))
        self.assertEqual(result["rows"], [])
        self.assertTrue((self.c["opdir"] / "current-input.json").exists())

    def test_overflow_all_5001_rows_sealed_without_truncation_or_projection(self):
        write_db(self.c, [valid_row(i) for i in range(5001)])
        run = cli(self.c)
        self.assertEqual(run.returncode, 2, run.stderr)
        result = self.result()
        self.assertEqual(result["state"], "held_overflow_no_replay")
        self.assertEqual(result["rows_captured"], 5001)
        self.assertEqual(result["rows_examined"], 0)
        self.assertTrue(result["overflow"])
        self.assertEqual(result["rows"], [])
        private = json.loads((self.c["opdir"] / "current-input.json").read_text())
        self.assertEqual(len(private["db_projection"]["rows"]), 5001)
        self.assertTrue(m.validate_result(result))

    def test_table_missing_failure_is_terminal_no_replay(self):
        write_db(self.c, [], with_table=False)
        run = cli(self.c)
        self.assertEqual(run.returncode, 2, run.stderr)
        result = self.result()
        self.assertEqual(result["state"], "terminal_failed_no_replay")
        self.assertEqual(result["failure_stage"], "db_table_missing")
        self.assertEqual(result["database_reads"], 0)
        self.assertEqual(result["database_read_attempts"], 1)
        self.assertTrue(result["read_transaction_rolled_back"])
        before = {p.name: p.read_bytes() for p in self.c["opdir"].iterdir()}
        write_db(self.c, [valid_row()])
        self.assertNotEqual(cli(self.c).returncode, 0)
        self.assertEqual(before, {p.name: p.read_bytes() for p in self.c["opdir"].iterdir()})

    def test_success_no_replay_preserves_every_private_byte(self):
        self.execute_ok()
        before = {p.name: p.read_bytes() for p in self.c["opdir"].iterdir()}
        self.assertNotEqual(cli(self.c).returncode, 0)
        self.assertEqual(before, {p.name: p.read_bytes() for p in self.c["opdir"].iterdir()})

    def test_secret_url_redacted_only_publicly_entire_original_stays_private(self):
        secret = "https://operator.com/hotel?HOTELLIST=804&token=PRIVATE-SECRET"
        write_db(self.c, [valid_row(hotel_url=secret)])
        result = self.execute_ok("completed_with_holds")
        self.assertNotIn("PRIVATE-SECRET", m.enc(result).decode())
        self.assertIsNone(result["rows"][0]["hotel_url"])
        self.assertEqual(result["rows"][0]["redacted_hotel_url_sha256"], hashlib.sha256(secret.encode()).hexdigest())
        private = json.loads((self.c["opdir"] / "current-input.json").read_text())
        self.assertEqual(private["db_projection"]["rows"][0]["hotel_url"], secret)

    def test_unsafe_image_url_redacted_private_same_row_complete(self):
        url = "https://127.0.0.1/private"
        write_db(self.c, [valid_row(image_url=url)])
        result = self.execute_ok("completed_with_holds")
        self.assertIsNone(result["rows"][0]["image_url"])
        self.assertEqual(result["rows"][0]["redacted_image_url_sha256"], hashlib.sha256(url.encode()).hexdigest())

    def test_opaque_auth_cookie_offer_ref_and_double_encoded_path_stay_private(self):
        urls = ["https://operator.com/hotel?auth=opaqueXYZ",
                "https://operator.com/hotel?cookie=opaqueXYZ",
                "https://operator.com/hotel?offer_id=offer_opaqueXYZ",
                "https://operator.com/%2574oken%253DopaqueXYZ"]
        safe = "https://operator.com/hotel?F4=610210401&code=hotel_24&HOTELLIST=804&HOTELLIST=44562"
        write_db(self.c, [valid_row(1, hotel_url=safe)] +
                        [valid_row(index, hotel_url=url) for index, url in enumerate(urls, 2)])
        result = self.execute_ok("completed_with_holds")
        self.assertEqual((result["rows_captured"], result["rows_retained"], result["rows_held"]), (5, 1, 4))
        self.assertNotIn("opaqueXYZ", m.enc(result).decode())
        rows = {row["external_hotel_id"]: row for row in result["rows"]}
        self.assertEqual(rows["9001"]["hotel_url"], safe)
        self.assertTrue(rows["9001"]["content_hash_verified"])
        for index, url in enumerate(urls, 2):
            projected = rows[str(9000 + index)]
            self.assertIsNone(projected["hotel_url"])
            self.assertEqual(projected["state"], "row_hold")
            self.assertTrue(projected["content_hash_verified"])
            self.assertEqual(projected["redacted_hotel_url_sha256"], hashlib.sha256(url.encode()).hexdigest())
        private = json.loads((self.c["opdir"] / "current-input.json").read_text())
        self.assertEqual({row["hotel_url"] for row in private["db_projection"]["rows"]}, set(urls) | {safe})
        self.assertEqual(private["db_projection"], json.loads(private["db_projection_utf8"]))

    def test_opaque_cookie_image_url_redacts_without_dropping_other_rows(self):
        url = "https://operator.com/image?cookie=opaqueXYZ"
        write_db(self.c, [valid_row(1), valid_row(2, image_url=url)])
        result = self.execute_ok("completed_with_holds")
        self.assertEqual(result["rows_retained"], 1)
        held = [row for row in result["rows"] if row["external_hotel_id"] == "9002"][0]
        self.assertIsNone(held["image_url"])
        self.assertEqual(held["redacted_image_url_sha256"], hashlib.sha256(url.encode()).hexdigest())
        self.assertNotIn("opaqueXYZ", m.enc(result).decode())

    def test_opaque_auth_cookie_and_offer_markers_in_text_are_private_per_row(self):
        names = ["auth=opaqueXYZ", "cookie=opaqueXYZ", "offer_opaqueXYZ"]
        write_db(self.c, [valid_row(1)] + [valid_row(index, hotel_name=name) for index, name in enumerate(names, 2)])
        result = self.execute_ok("completed_with_holds")
        self.assertEqual((result["rows_captured"], result["rows_retained"], result["rows_held"]), (4, 1, 3))
        self.assertNotIn("opaqueXYZ", m.enc(result).decode())
        for row in result["rows"]:
            if row["external_hotel_id"] != "9001":
                self.assertIsNone(row["hotel_name"])
                self.assertTrue(row["content_hash_verified"])
        private = json.loads((self.c["opdir"] / "current-input.json").read_text())
        self.assertEqual({row["hotel_name"] for row in private["db_projection"]["rows"]}, set(names) | {"Отель / Hotel 1"})

    def test_opaque_operator_name_marker_does_not_leak_or_drop_valid_neighbor(self):
        write_db(self.c, [valid_row(1), valid_row(2, operator_names_json='["ANEX","cookie=opaqueXYZ"]')])
        result = self.execute_ok("completed_with_holds")
        self.assertEqual(result["rows_retained"], 1)
        held = [row for row in result["rows"] if row["external_hotel_id"] == "9002"][0]
        self.assertEqual(held["operator_names"], ["ANEX"])
        self.assertNotIn("opaqueXYZ", m.enc(result).decode())

    def test_json_objects_hold_and_genuine_empty_arrays_remain_missing_independently(self):
        rows = [valid_row(1), valid_row(2, operator_refs_json='{}'),
                valid_row(3, operator_names_json='{}'), valid_row(4, operator_refs_json='[]'),
                valid_row(5, operator_names_json='[]')]
        write_db(self.c, rows)
        result = self.execute_ok("completed_with_holds")
        self.assertEqual((result["rows_captured"], result["rows_examined"]), (5, 5))
        by_id = {row["external_hotel_id"]: row for row in result["rows"]}
        self.assertEqual(by_id["9001"]["state"], "retained_normalized_identity_candidate")
        for external in ("9002", "9003"):
            self.assertEqual(by_id[external]["state"], "row_hold")
            self.assertFalse(by_id[external]["content_hash_verified"])
            self.assertIn("content_hash_unverifiable", by_id[external]["holds"])
        self.assertEqual(by_id["9004"]["operator_refs"], [])
        self.assertEqual(by_id["9004"]["state"], "row_hold")
        self.assertTrue(by_id["9004"]["content_hash_verified"])
        self.assertEqual(by_id["9005"]["operator_names"], [])
        self.assertTrue(by_id["9005"]["content_hash_verified"])
        for flag in m.FALSE_FLAGS: self.assertIs(result[flag], False)
        private = json.loads((self.c["opdir"] / "current-input.json").read_text())
        self.assertEqual(private["db_projection"]["rows"], self.c["rows"])

    def test_full_projection_over_16_mib_is_sealed_and_description_only_hashed_publicly(self):
        description = "Длинный текст / line\n" * 3000
        self.assertLessEqual(len(description), 65535)
        rows = [valid_row(i, description_text=description) for i in range(220)]
        write_db(self.c, rows)
        result = self.execute_ok()
        self.assertEqual((result["rows_captured"], result["rows_retained"]), (220, 220))
        path = self.c["opdir"] / "current-input.json"
        self.assertGreater(path.stat().st_size, 16 * 1024 * 1024)
        self.assertLess(path.stat().st_size, 136 * 1024 * 1024)
        private = json.loads(path.read_text())
        self.assertEqual(private["db_projection"]["rows"], self.c["rows"])
        self.assertEqual(private["db_projection"], json.loads(private["db_projection_utf8"]))
        self.assertEqual(hashlib.sha256(path.read_bytes()).hexdigest(), result["private_input_sha256"])
        self.assertLess(len(m.enc(result)), 1024 * 1024)
        self.assertNotIn(description, m.enc(result).decode())
        self.assertTrue(all(row["description_sha256"] == hashlib.sha256(description.encode()).hexdigest() for row in result["rows"]))

    def test_invalid_operator_json_one_hold_and_no_secret_leak(self):
        bad = valid_row(2); bad["operator_refs_json"] = '["5", "PRIVATE token=SECRET"]'
        write_db(self.c, [valid_row(1), bad])
        result = self.execute_ok("completed_with_holds")
        self.assertEqual(result["rows_retained"], 1)
        self.assertNotIn("SECRET", m.enc(result).decode())

    def test_pinned_producer_byte_change_fails_without_database_read(self):
        with (self.c["producerapp"] / "andromeda-hotel-observations.php").open("ab") as stream: stream.write(b"\n")
        run = cli(self.c)
        self.assertEqual(run.returncode, 2)
        result = self.result()
        self.assertEqual(result["failure_stage"], "producer_source_changed")
        self.assertEqual(result["database_read_attempts"], 0)

    def test_pinned_db_entrypoint_byte_change_fails_before_php_or_db(self):
        with self.c["dbentry"].open("ab") as stream: stream.write(b"\n")
        run = cli(self.c)
        self.assertEqual(run.returncode, 2)
        result = self.result()
        self.assertEqual(result["failure_stage"], "db_entrypoint_changed")
        self.assertEqual(result["php_invocations"], 0)
        self.assertEqual(result["database_read_attempts"], 0)

    def test_unclassified_php_exit_preserves_unknown_reads_and_no_replay(self):
        if PHP is None:
            stub = self.c["home"] / "stub-bin/php"
            stub.write_text("#!" + sys.executable + "\nraise SystemExit(77)\n")
            stub.chmod(0o700)
        else:
            self.c["config"].write_text("<?php exit(77);\n")
        run = cli(self.c)
        self.assertEqual(run.returncode, 2)
        result = self.result()
        self.assertEqual(result["failure_stage"], "db_subprocess_unclassified")
        self.assertEqual(result["php_invocations"], 1)
        self.assertIsNone(result["database_reads"])
        self.assertIsNone(result["database_read_attempts"])
        self.assertIsNone(result["read_transaction_rolled_back"])
        self.assertEqual(result["accepted"], 0)
        self.assertEqual(result["written"], 0)
        for key in ("database_reads", "database_read_attempts", "read_transaction_rolled_back"):
            altered = copy.deepcopy(result); altered[key] = 0 if key != "read_transaction_rolled_back" else False
            with self.subTest(key=key), self.assertRaises(ValueError): m.validate_result(altered)
        before = {p.name: p.read_bytes() for p in self.c["opdir"].iterdir()}
        self.assertNotEqual(cli(self.c).returncode, 0)
        self.assertEqual(before, {p.name: p.read_bytes() for p in self.c["opdir"].iterdir()})

    def test_missing_reservation_refused_before_started(self):
        (self.c["opdir"] / "reservation.json").unlink()
        self.assertNotEqual(cli(self.c).returncode, 0)
        self.assertFalse((self.c["opdir"] / "execution-started.json").exists())

    def test_distinct_batch_marker_missing_refused_before_started(self):
        self.c["marker"].unlink()
        self.assertNotEqual(cli(self.c).returncode, 0)
        self.assertFalse((self.c["opdir"] / "execution-started.json").exists())

    def test_wrong_reservation_write_budget_refused_before_started(self):
        reservation = copy.deepcopy(self.c["reservation"]); reservation["maximum_writes"] = 1
        (self.c["opdir"] / "reservation.json").write_bytes(m.enc(reservation))
        self.assertNotEqual(cli(self.c).returncode, 0)
        self.assertFalse((self.c["opdir"] / "execution-started.json").exists())

    def test_result_output_symlink_blocks_before_started_and_does_not_write_outside(self):
        outside = self.c["home"] / "outside-result"
        (self.c["opdir"] / "result.json").symlink_to(outside)
        run = cli(self.c)
        self.assertNotEqual(run.returncode, 0)
        self.assertNotIn(str(self.c["home"]), run.stderr)
        self.assertFalse(outside.exists())
        self.assertFalse((self.c["opdir"] / "execution-started.json").exists())

    def test_project_symlink_or_traversal_refused_before_started(self):
        alias = self.c["home"] / "www/alias"; alias.symlink_to(self.c["project"], target_is_directory=True)
        self.c["env"]["ANYTOUR_ROOT"] = str(alias)
        self.assertNotEqual(cli(self.c).returncode, 0)
        self.assertFalse((self.c["opdir"] / "execution-started.json").exists())
        self.c["env"]["ANYTOUR_ROOT"] = str(self.c["project"]) + "/../anytoour.ru"
        self.assertNotEqual(cli(self.c).returncode, 0)
        self.assertFalse((self.c["opdir"] / "execution-started.json").exists())

    def test_db_entrypoint_symlink_not_loaded(self):
        entry = self.c["dbentry"]; raw = entry.read_bytes(); entry.unlink()
        outside = self.c["home"] / "outside-db.php"; outside.write_bytes(raw); entry.symlink_to(outside)
        run = cli(self.c)
        self.assertEqual(run.returncode, 2)
        self.assertEqual(self.result()["database_read_attempts"], 0)

    def test_db_config_symlink_refused_before_php_without_loading_contents(self):
        config = self.c["config"]; raw = config.read_bytes(); config.unlink()
        outside = self.c["home"] / "outside-config.php"; outside.write_bytes(raw)
        config.symlink_to(outside)
        run = cli(self.c)
        self.assertEqual(run.returncode, 2)
        result = self.result()
        self.assertEqual(result["failure_stage"], "db_config_path")
        self.assertEqual(result["php_invocations"], 0)
        self.assertEqual(result["database_read_attempts"], 0)
        self.assertEqual(outside.read_bytes(), raw)

    def test_private_files_are_restrictive_and_exclusive(self):
        self.execute_ok()
        for name in ("current-input.json", "result.json", "receipt.json", "execution-started.json"):
            self.assertEqual(stat.S_IMODE((self.c["opdir"] / name).stat().st_mode), 0o600)
        with self.assertRaises(FileExistsError): m.save(self.c["opdir"] / "current-input.json", {})

    def test_save_fsyncs_file_and_directory_and_reads_exact_written_bytes(self):
        target = self.c["opdir"] / "test-fsync.json"
        original = os.fsync
        calls = []
        def synced(fd): calls.append(stat.S_ISDIR(os.fstat(fd).st_mode)); return original(fd)
        with patch.object(m.os, "fsync", side_effect=synced):
            digest = m.save(target, {"value": "неизменяемый / текст"})
        self.assertEqual(calls, [False, True])
        self.assertEqual(digest, hashlib.sha256(target.read_bytes()).hexdigest())

    def test_result_tampered_authority_private_keys_counters_or_digest_rejected(self):
        result = self.execute_ok()
        for key, value in (("raw_samo_evidence_verified", True), ("safe_to_write_now", True),
                           ("accepted", 1), ("database_writes", 1), ("rows_captured", True),
                           ("search_evidence_sha256", "b" * 64)):
            altered = copy.deepcopy(result); altered[key] = value
            with self.subTest(key=key), self.assertRaises(ValueError): m.validate_result(altered)
        receipt = json.loads((self.c["opdir"] / "receipt.json").read_text())
        receipt["private_input_sha256"] = "0" * 64
        with self.assertRaises(ValueError): m.validate_result(result, receipt, "a" * 40)

    def test_public_candidate_flags_ids_and_namespace_cannot_be_tampered(self):
        result = self.execute_ok()
        for key, value in (("content_hash_verified", False), ("content_hash_verified", 1),
                           ("observation_sha256_verified", False), ("observation_sha256_verified", 1),
                           ("country_id", True), ("country_id", "4"), ("category", True),
                           ("supplier_namespace", "operator_5"), ("hotel_name", None),
                           ("country_name", None), ("region_name", None), ("operator_refs", [])):
            altered = copy.deepcopy(result); altered["rows"][0][key] = value
            with self.subTest(key=key, value=value), self.assertRaises(ValueError): m.validate_result(altered)

    def test_completed_database_counters_require_strict_types_and_complete_relationship(self):
        result = self.execute_ok()
        for key, value in (("database_reads", True), ("database_read_attempts", True),
                           ("php_invocations", True), ("read_transaction_rolled_back", 1),
                           ("database_reads", None), ("database_read_attempts", 0),
                           ("php_invocations", 0), ("read_transaction_rolled_back", False)):
            altered = copy.deepcopy(result); altered[key] = value
            with self.subTest(key=key, value=value), self.assertRaises(ValueError): m.validate_result(altered)

    def test_table_missing_known_database_counters_cannot_become_unknown_or_pre_php(self):
        write_db(self.c, [], with_table=False)
        self.assertEqual(cli(self.c).returncode, 2)
        result = self.result()
        for key, value in (("database_reads", None), ("database_read_attempts", None),
                           ("database_reads", 1), ("database_read_attempts", 0),
                           ("php_invocations", 0), ("read_transaction_rolled_back", False)):
            altered = copy.deepcopy(result); altered[key] = value
            with self.subTest(key=key, value=value), self.assertRaises(ValueError): m.validate_result(altered)

    def test_invalid_fixture_window_or_row_cap_does_not_start_operation(self):
        for key, value in (("row_cap", 5001), ("window_utc", {"lower_inclusive": "2026-10-04 00:00:00", "upper_exclusive": "2026-10-04 18:00:00"}), ("maximum_writes", True)):
            fixture = copy.deepcopy(self.fixture); fixture[key] = value
            path = self.c["home"] / "invalid-fixture.json"; path.write_bytes(m.enc(fixture))
            with self.subTest(key=key), self.assertRaises(ValueError): m.manifest(path)

    def test_concurrent_cli_only_one_capture_wins(self):
        args = [sys.executable, str(SOURCE), "--execute"]
        processes = [subprocess.Popen(args, env=self.c["env"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True) for _ in range(2)]
        for process in processes: process.communicate(timeout=45)
        self.assertEqual(sorted(process.returncode for process in processes), [0, 1])
        self.assertEqual(self.result()["state"], "completed_read_only")


if __name__ == "__main__":
    print("database_dependency=real_php_pdo_sqlite" if PHP else "database_dependency=explicit_local_php_db_stub; mandatory_CI_requires_real_php_pdo_sqlite")
    unittest.main()
