#!/usr/bin/env python3
"""One-shot v7 HTTP canary for Andromeda quote continuation.

This reuses the checked v6 HTTP consumer and changes only the immutable scenario,
installed runtime pin, and capture of the already-public browser-safe failure_category.
No supplier UID/body/URL is accepted or emitted.
"""
from __future__ import annotations

import importlib.util
import json
import sys
from pathlib import Path
from typing import Any, Callable

HERE = Path(__file__).resolve().parent
CORE_PATH = HERE / "andromeda_http_flight_selection_e2e.py"
spec = importlib.util.spec_from_file_location("andromeda_http_flight_selection_e2e_v6_core", CORE_PATH)
if spec is None or spec.loader is None:
    raise RuntimeError("v6_core_unavailable")
core = importlib.util.module_from_spec(spec)
spec.loader.exec_module(core)

OPERATION = "andromeda-http-flight-selection-e2e-1717-v7-turkey-2026-12-22-2a-9n"
RUNTIME_SOURCE = "3275b3e8b1f9bfe50cc0ce521beaa43602a408bf"
ALLOWED_FAILURE_CATEGORIES = {
    "supplier_transport",
    "supplier_http",
    "supplier_rejected",
    "supplier_response",
    "supplier_auth",
    "quote_state",
    "internal",
}


def scenario_request() -> dict[str, Any]:
    return {
        "generation": 17171222,
        "page": 1,
        "params": {
            "countryId": "4",
            "departureId": "1",
            "dateFrom": "2026-12-22",
            "dateTo": "2026-12-22",
            "nightsFrom": 9,
            "nightsTo": 9,
            "adults": 2,
            "childs": [],
            "meal": "",
            "hotelCategory": "",
            "hotelIds": [],
            "regionIds": [],
            "subregionIds": [],
            "operatorIds": [],
            "currency": "RUB",
        },
        "andromeda_operator_ids": ["5"],
    }


Post = Callable[[str, dict[str, Any]], tuple[int, dict[str, Any]]]


def run_flow(post: Post) -> dict[str, Any]:
    observed_category: str | None = None

    def classified_post(endpoint: str, payload: dict[str, Any]) -> tuple[int, dict[str, Any]]:
        nonlocal observed_category
        status, body = post(endpoint, payload)
        if status == 502 and isinstance(body, dict):
            category = body.get("failure_category")
            if isinstance(category, str):
                observed_category = category if category in ALLOWED_FAILURE_CATEGORIES else "invalid"
        return status, body

    old_operation = core.OPERATION
    old_runtime = core.RUNTIME_SOURCE
    old_scenario = core.scenario_request
    try:
        core.OPERATION = OPERATION
        core.RUNTIME_SOURCE = RUNTIME_SOURCE
        core.scenario_request = scenario_request
        result = core.run_flow(classified_post)
    finally:
        core.OPERATION = old_operation
        core.RUNTIME_SOURCE = old_runtime
        core.scenario_request = old_scenario

    result["scenario"] = {
        "country": "Turkey",
        "date": "2026-12-22",
        "nights": 9,
        "adults": 2,
        "children": [],
        "operator": "ANEX",
    }
    if observed_category in ALLOWED_FAILURE_CATEGORIES:
        result["failure_category"] = observed_category
    elif observed_category == "invalid":
        result["failure_category"] = "invalid_public_category"
    return result


class HttpSession(core.HttpSession):
    pass


def main() -> int:
    session = HttpSession()
    result = run_flow(session.post)
    print(json.dumps(result, ensure_ascii=False, sort_keys=True))
    return 0


if __name__ == "__main__":
    sys.exit(main())
