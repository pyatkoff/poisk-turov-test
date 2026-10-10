#!/usr/bin/env python3
"""Offline regression of the actual demand SELECT; no collector entrypoint or HTTP.

PHP renders the two isolated query functions into a capture-only PDO double.
SQLite executes their portable demand query against synthetic in-memory tables.
This tests selection semantics, not a production MySQL connection or catalogue.
"""
from __future__ import annotations

import json
from pathlib import Path
import re
import sqlite3
import subprocess
import sys
import unittest

ROOT = Path(__file__).resolve().parents[1]
PHP = sys.argv[1] if len(sys.argv) > 1 else "php"


def captured_queries() -> dict:
    source = (ROOT / "v2/data/collect-hotel-details-v1.php").read_text()
    functions = []
    for name in ("hotel_details_demand_source_sql", "hotel_details_pending_rows"):
        matches = re.findall(r"^function " + name + r"\([^\n]*\)[^\n]*\n\{\n.*?^\}", source, re.M | re.S)
        if len(matches) != 1:
            raise RuntimeError("Cannot isolate query function: " + name)
        functions.append(matches[0])
    # Never require/eval the collector file: its top level connects and writes.
    program = "<?php\ndeclare(strict_types=1);\n" + "\n".join(functions) + r'''
class DemandQueryStatement extends PDOStatement {
    public function __construct() {}
    public function execute(?array $params = null): bool { return true; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return []; }
}
class DemandQueryPDO extends PDO {
    public string $sql = '';
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->sql = $query;
        return new DemandQueryStatement();
    }
}
$out = [];
foreach ([1,2,100] as $limit) {
    $pdo = new DemandQueryPDO();
    hotel_details_pending_rows($pdo, '2026-09-10 00:00:00', '2026-10-09 00:00:00', $limit, 'demand');
    $out[$limit] = $pdo->sql;
}
echo json_encode($out, JSON_THROW_ON_ERROR);
'''
    result = subprocess.run([PHP], input=program, text=True, capture_output=True, check=True, timeout=10)
    return json.loads(result.stdout)


class DemandPriority(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.queries = captured_queries()

    def setUp(self):
        self.db = sqlite3.connect(":memory:")
        self.db.row_factory = sqlite3.Row
        self.db.executescript("""
            CREATE TABLE tour_price_observations(hotel_id INTEGER, source TEXT, observed_at TEXT);
            CREATE TABLE hot_tours_current(hotel_id INTEGER, fetched_at TEXT);
            CREATE TABLE catalog_hotels(id INTEGER PRIMARY KEY, name TEXT, last_seen_at TEXT);
            CREATE TABLE catalog_hotel_details(hotel_id INTEGER PRIMARY KEY, status TEXT, fetched_at TEXT);
        """)

    def tearDown(self):
        self.db.close()

    def observe(self, hotel, source="user_search", when="2026-10-01 00:00:00", count=1):
        self.db.executemany("INSERT INTO tour_price_observations VALUES (?,?,?)", [(hotel, source, when)] * count)

    def rows(self, limit=100):
        return self.db.execute(self.queries[str(limit)], {
            "cutoff": "2026-09-10 00:00:00", "retry_cutoff": "2026-10-09 00:00:00",
        }).fetchall()

    def test_old_user_search_precedes_frequent_recent_monitor(self):
        self.observe(101, when="2025-01-01 00:00:00")
        self.observe(102, "scheduled_monitor", "2026-10-10 00:00:00", 50)
        self.db.execute("INSERT INTO hot_tours_current VALUES (103,'2026-10-10 00:00:00')")
        self.assertEqual([r["hotel_id"] for r in self.rows()], [101, 102, 103])

    def test_only_user_observations_contribute_to_user_priority(self):
        self.observe(101, count=2)
        self.observe(102)
        self.observe(102, "scheduled_monitor", count=20)
        rows = self.rows()
        self.assertEqual([r["hotel_id"] for r in rows], [101, 102])
        self.assertEqual([r["user_search_count"] for r in rows], [2, 1])

    def test_hot_duplicates_do_not_inflate_user_count_or_duplicate_hotel(self):
        self.observe(101, count=2)
        self.db.executemany("INSERT INTO hot_tours_current VALUES (?,?)", [(101, "2026-10-09 00:00:00")] * 3)
        rows = self.rows()
        self.assertEqual(len(rows), 1)
        self.assertEqual(rows[0]["user_search_count"], 2)
        self.assertEqual(rows[0]["observation_count"], 5)
        self.assertEqual(rows[0]["last_seen_at"], "2026-10-09 00:00:00")

    def test_membership_stays_observed_positive_ids_not_full_catalogue(self):
        for hotel in [-1, 0, 101]:
            self.observe(hotel, when="2020-01-01 00:00:00")
        self.db.execute("INSERT INTO catalog_hotels VALUES (999,'Unseen fixture','2026-10-10')")
        self.assertEqual([r["hotel_id"] for r in self.rows()], [101])

    def test_freshness_retry_and_missing_rules_unchanged(self):
        cases = [(101,"success","2026-10-09 00:00:00"), (102,"success","2026-09-10 00:00:00"),
                 (103,"success","2026-09-09 23:59:59"), (104,"failure","2026-10-09 00:00:00"),
                 (105,"failure","2026-10-08 23:59:59"), (106,"not_found","2026-09-01 00:00:00")]
        for hotel, _, _ in cases:
            self.observe(hotel)
        self.observe(107)
        self.db.executemany("INSERT INTO catalog_hotel_details VALUES (?,?,?)", cases)
        self.assertEqual([r["hotel_id"] for r in self.rows()], [103, 105, 106, 107])

    def test_monitor_recency_and_id_tiebreak_remain_deterministic(self):
        self.observe(103, "scheduled_monitor", "2026-10-02 00:00:00")
        self.observe(102, "scheduled_monitor", "2026-10-02 00:00:00")
        self.observe(101, "scheduled_monitor", "2026-10-01 00:00:00")
        self.assertEqual([r["hotel_id"] for r in self.rows()], [102, 103, 101])

    def test_scan_limit_preserved_and_user_is_in_first_slot(self):
        for hotel in range(101, 121):
            self.observe(hotel, "scheduled_monitor", count=5)
        self.observe(999)
        rows = self.rows(1)
        self.assertEqual(len(rows), 4)  # Existing four-times scan before generic filtering.
        self.assertEqual(rows[0]["hotel_id"], 999)
        self.assertEqual(len(self.rows(2)), 8)

    def test_empty_and_unknown_source_are_not_user_searches(self):
        self.assertEqual(self.rows(), [])
        self.observe(101, None)
        self.observe(102, "other_source")
        self.db.execute("INSERT INTO hot_tours_current VALUES (103,'2026-10-01 00:00:00')")
        self.assertEqual([r["user_search_count"] for r in self.rows()], [0, 0, 0])


if __name__ == "__main__":
    unittest.main(argv=[sys.argv[0]], verbosity=2)
