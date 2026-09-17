#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
import json
import pathlib
import tempfile

ROOT = pathlib.Path(__file__).resolve().parents[1]
PATH = ROOT / "scripts/catalog/anytour_canonical_seed_batch_manifest_v1.py"
spec = importlib.util.spec_from_file_location("batch_manifest", PATH)
assert spec and spec.loader
mod = importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)


def manifest() -> dict:
    return {
        "schema_version": 1,
        "operation_id": "anytour-canonical-provider-only-seed-2690-20260917-b001-v1",
        "selection": mod.SELECTION,
        "source_operation": mod.SOURCE_OPERATION,
        "source_run": mod.SOURCE_RUN,
        "source_payload_sha256": mod.SOURCE_PAYLOAD_SHA256,
        "base_release": "0" * 40,
        "claim_comment_id": 123456,
        "batch_index": 1,
        "batch_total": 12,
        "expected_count": 3,
        "tourvisor_hotel_ids": [10, 20, 30],
    }


def raw(value: dict) -> bytes:
    return (json.dumps(value, sort_keys=True, indent=2) + "\n").encode()


base = manifest()
out = mod.validate_bytes(raw(base), "anytour_provider_only_seed_manifest_v1_b001.json")
assert out["expected_count"] == 3 and out["tourvisor_hotel_ids"] == [10, 20, 30]
assert len(out["manifest_sha256"]) == 64

for mutate, code in [
    (lambda x: x.update(expected_count=1001, tourvisor_hotel_ids=list(range(1, 1002))), "expected_count"),
    (lambda x: x.update(tourvisor_hotel_ids=[10, 10, 30]), "ids_duplicate"),
    (lambda x: x.update(source_run=1), "source_run"),
    (lambda x: x.update(selection="all"), "selection"),
    (lambda x: x.update(base_release="main"), "base_release"),
    (lambda x: x.update(operation_id="anytour-canonical-provider-only-seed-2690-20260917-b002-v1"), "operation_batch_mismatch"),
]:
    value = manifest(); mutate(value)
    try:
        mod.validate_bytes(raw(value), "anytour_provider_only_seed_manifest_v1_b001.json")
    except ValueError as exc:
        assert str(exc) == code, (code, str(exc))
    else:
        raise AssertionError(code)

value = manifest(); value["extra"] = True
try:
    mod.validate_bytes(raw(value), "anytour_provider_only_seed_manifest_v1_b001.json")
except ValueError as exc:
    assert str(exc) == "manifest_keys"
else:
    raise AssertionError("manifest_keys")

try:
    mod.validate_bytes(raw(manifest()), "wrong.json")
except ValueError as exc:
    assert str(exc) == "manifest_filename"
else:
    raise AssertionError("manifest_filename")

with tempfile.TemporaryDirectory() as tmp:
    p = pathlib.Path(tmp) / "anytour_provider_only_seed_manifest_v1_b001.json"
    p.write_bytes(raw(manifest()))
    assert mod.validate_path(p)["operation_id"].endswith("-b001-v1")

print("anytour canonical seed batch manifest v1 PASS")
