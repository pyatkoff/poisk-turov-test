#!/usr/bin/env python3
"""Build corrected immutable v3 writer from the verified CURRENT parent result."""
from __future__ import annotations

import hashlib
import json
import sys
from pathlib import Path

import hotel_match_received_nonanex_apply_bundle as base

OPERATION_ID = "hotel-match-received-nonanex-apply-1971-20260916-v3"
TRIPLES = {
    ("625557015", "46462", 3406),
    ("625746958", "2000044235", 65881),
    ("610167368", "2000084551", 445),
    ("610175991", "135480", 295),
    ("610181893", "2000033706", 364),
    ("610108009", "2000038699", 5536),
    ("625076472", "203605", 1389),
    ("651351140", "1509", 434),
    ("675591202", "98", 1963),
    ("616084480", "732", 9226),
    ("610182727", "2000069328", 306),
    ("610187541", "2000043229", 76320),
    ("610133381", "2000061191", 65659),
    ("610226529", "2000081109", 38552),
    ("616120383", "2000023262", 30637),
    ("616574495", "2000062517", 28507),
    ("617029970", "10064", 1090),
    ("625380715", "145106", 1319),
    ("632834906", "2000041086", 476),
    ("640012445", "9299", 380),
}


def build() -> str:
    if len(TRIPLES) != 20 or len({t[2] for t in TRIPLES}) != 20:
        raise SystemExit("v3_triple_shape")
    base.OPERATION_ID = OPERATION_ID
    base.TRIPLES = TRIPLES
    s = base.build()
    if OPERATION_ID not in s:
        raise SystemExit("v3_operation_missing")
    if "hotel-match-received-nonanex-apply-1971-20260916-v2" in s:
        raise SystemExit("v2_operation_leaked")
    if s.count("UPDATE andromeda_hotel_identities SET") != 1:
        raise SystemExit("v3_writer_count")
    return s


if __name__ == "__main__":
    s = build()
    Path(sys.argv[1]).write_text(s)
    print(json.dumps({
        "operation_id": OPERATION_ID,
        "parent_operation_id": base.PARENT_OPERATION_ID,
        "parent_run_id": base.PARENT_RUN_ID,
        "parent_result_sha256": base.PARENT_RESULT_SHA256,
        "planned_count": 20,
        "planned_frequency": 167,
        "bundle_sha256": hashlib.sha256(s.encode()).hexdigest(),
        "maximum_database_writes": 20,
        "supplier_calls": 0,
        "tourvisor_calls": 0,
    }, sort_keys=True))