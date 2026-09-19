#!/usr/bin/env python3
from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
import andromeda_retained_full_claim_fuel_read as target  # noqa: E402


def check(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


def test_current_and_alternative_are_separate() -> None:
    payload = {
        "claimId": "FULL-claim-secret-123",
        "services": [
            {
                "type": "fuel_surcharge",
                "name": "Fuel surcharge",
                "amount": "14265",
                "currency": "RUB",
                "unit": "package",
                "required": True,
                "dependency": "transport",
                "packet": True,
                "clients": ["Alice", "Bob"],
                "common": True,
                "includedInPrice": True,
                "authorization": "Bearer must-never-leak",
            },
            {"type": "insurance", "amount": 100},
        ],
        "alternativeServices": [
            {
                "code": "FUEL_ALT",
                "title": "Топливный сбор alternative",
                "price": {"amount": 7000, "currency": "RUB", "included": False},
                "clients": {"adult-1": {"name": "Sensitive"}},
            }
        ],
    }
    out = target.sanitize_payload(payload)
    claim = out["claims"][0]
    check(claim["current_fuel_count"] == 1, "current fuel count")
    check(claim["alternative_fuel_count"] == 1, "alternative fuel count")
    current = claim["current_fuel_services"][0]
    alternative = claim["alternative_fuel_services"][0]
    check(current["money"]["amount"] == "14265", "current amount")
    check(current["price_inclusion_state"] == "included", "current inclusion")
    check(alternative["money"]["amount"] == 7000, "alternative amount")
    check(alternative["price_inclusion_state"] == "not_included", "alternative inclusion")
    check(current["clients"] == {"source": "clients", "present": True, "count": 2}, "client PII leaked")
    check(alternative["clients"]["count"] == 1, "alternative client count")
    rendered = json.dumps(out, ensure_ascii=False)
    check("FULL-claim-secret-123" not in rendered, "raw claim id leaked")
    check("Bearer must-never-leak" not in rendered, "secret leaked")
    check("Alice" not in rendered and "Sensitive" not in rendered, "PII leaked")
    check(len(claim["claim_ref_sha256"]) == 20, "claim hash missing")


def test_missing_inclusion_stays_unknown() -> None:
    out = target.sanitize_payload({"services": [{"name": "Fuel surcharge", "amount": 123}]})
    service = out["claims"][0]["current_fuel_services"][0]
    check(service["price_inclusion_state"] == "unknown", "missing inclusion was inferred")
    check("price_inclusion_evidence" not in service, "fabricated inclusion evidence")


def test_transport_and_flight_markup_are_not_promoted() -> None:
    payload = {
        "services": [
            {"type": "party_transport_surcharge", "amount": 14265},
            {"name": "Minimum flight markup", "amount": 2000},
            {"title": "Flight surcharge", "amount": 3000},
            {"name": "YQ", "amount": 4000},
            {"name": "Airport fuel surcharge", "amount": 5000},
        ]
    }
    out = target.sanitize_payload(payload)
    services = out["claims"][0]["current_fuel_services"]
    check(len(services) == 1, "non-fuel transport evidence promoted")
    check(services[0]["money"]["amount"] == 5000, "real fuel descriptor missed")


def test_explicit_fuel_flag_does_not_override_excluded_markup() -> None:
    out = target.sanitize_payload({"services": [{"type": "party_transport_surcharge", "isFuel": True, "amount": 1}]})
    check(out["claims"][0]["current_fuel_count"] == 0, "excluded markup upgraded by flag")


def test_envelopes_cap_and_determinism() -> None:
    claims = [
        {"id": f"claim-{i}", "package": {"services": [{"serviceType": "fuel", "amount": i}]}}
        for i in range(5)
    ]
    first = target.sanitize_payload({"claims": claims})
    second = target.sanitize_payload({"claims": claims})
    check(first == second, "output is not deterministic")
    check(first["claims_processed"] == 3 and first["claims_truncated"] is True, "claim cap failed")
    check(first["evidence_sha256"] == second["evidence_sha256"], "evidence hash unstable")


def test_alternative_container_shape() -> None:
    payload = {
        "claim": {
            "services": [{"name": "fuel surcharge", "amount": 10}],
            "alternative_services": [
                {"group": "one", "services": [{"name": "Fuel alternative", "amount": 20}]}
            ],
        }
    }
    out = target.sanitize_payload(payload)
    claim = out["claims"][0]
    check(claim["current_fuel_count"] == 1, "nested current missing")
    check(claim["alternative_fuel_count"] == 1, "nested alternative container missing")


def test_cli_invalid_json_is_safe() -> None:
    proc = subprocess.run(
        [sys.executable, str(HERE / "andromeda_retained_full_claim_fuel_read.py")],
        input="{bad-json",
        text=True,
        capture_output=True,
        check=False,
    )
    check(proc.returncode == 2, "invalid JSON exit code")
    check("invalid_json" in proc.stderr, "safe invalid JSON marker missing")
    check("bad-json" not in proc.stderr, "raw invalid input leaked")


def main() -> int:
    tests = [name for name, value in globals().items() if name.startswith("test_") and callable(value)]
    for name in sorted(tests):
        globals()[name]()
    print(f"PASS {len(tests)} tests")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
