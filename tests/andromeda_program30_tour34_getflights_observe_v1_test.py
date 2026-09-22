#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "scripts/diagnostics/andromeda_program30_tour34_getflights_observe_v1.php"
WF = ROOT / ".github/workflows/andromeda-program30-tour34-getflights-observe-v1.yml"

def test_source_contract():
    text = SRC.read_text()
    required = [
        "INT_GF_PROGRAM = '30'",
        "INT_GF_TOUR = '34'",
        "INT_GF_EXPECTED_GROUP = 12",
        "int-andromeda-program30-tour34-getflights-20260922-v1",
        "AnyTourAndromedaSearchSurcharge::diagnostic",
        "AnyTourAndromedaSearchSurcharge::estimate",
        "AnyTourAndromedaSearchSurcharge::cheapestRequiredFlightSelection",
        "AnyTourAndromedaSelectedQuote::reportedFuelSurcharges",
        "operation_exists_no_replay",
        "'changeservice'=>0",
        "'calc'=>0",
        "'booking'=>0",
        "'database_writes'=>0",
    ]
    for item in required:
        assert item in text, item
    assert text.count("->getFlights(") == 1
    for forbidden in ("->changeService(", "->calc(", "bron_ticket", "action=bron", "action=bron_ticket"):
        assert forbidden not in text
    for forbidden_output in ("supplier_id'=>$", "'uid'=>", "'groupId'=>", "claimDocument"):
        assert forbidden_output not in re.sub(r"\$doc = \$package\['claimDocument'\]\[0\] \?\? null;", "", text)

def test_workflow_contract():
    text = WF.read_text()
    for item in [
        "issue.number == 3419",
        "/observe-andromeda-program30-tour34-getflights-3419-v1",
        "226193297",
        "andromeda_program30_tour34_getflights_observe_v1.php",
        "andromeda-program30-tour34-getflights-v1-",
        "supplier_calls",
    ]:
        assert item in text, item
    assert "ANYTOOUR_DEPLOY_SSH_KEY" in text
    assert "actions/upload-artifact@v4" in text
    assert "workflow_dispatch" not in text
