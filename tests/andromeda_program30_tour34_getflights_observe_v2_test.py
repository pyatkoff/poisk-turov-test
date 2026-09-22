#!/usr/bin/env python3
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "scripts/diagnostics/andromeda_program30_tour34_getflights_observe_v2.php"
WF = ROOT / ".github/workflows/andromeda-program30-tour34-getflights-observe-v2.yml"

class TargetedGetFlightsContractTest(unittest.TestCase):
    def test_source_contract(self):
        text = SRC.read_text()
        required = [
            "INT_GF_PROGRAM = '30'",
            "INT_GF_TOUR = '34'",
            "INT_GF_EXPECTED_GROUP = 12",
            "INT_GF_SAMPLE_INDEX = 1",
            "INT_GF_V1_OFFER_SHA256",
            "int-andromeda-program30-tour34-getflights-20260922-v2",
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
            self.assertIn(item, text)
        self.assertEqual(1, text.count("->getFlights("))
        for forbidden in ("->changeService(", "->calc(", "bron_ticket", "action=bron", "action=bron_ticket"):
            self.assertNotIn(forbidden, text)
        public_text = re.sub(r"\$doc = \$package\['claimDocument'\]\[0\] \?\? null;", "", text)
        for forbidden_output in ("'supplier_offer_id'=>", "'uid'=>", "'groupId'=>", "claimDocument"):
            self.assertNotIn(forbidden_output, public_text)

    def test_workflow_contract(self):
        text = WF.read_text()
        for item in [
            "issue.number == 3419",
            "/observe-andromeda-program30-tour34-getflights-3419-v2",
            "226193297",
            "andromeda_program30_tour34_getflights_observe_v2.php",
            "andromeda-program30-tour34-getflights-v2-",
            "supplier_calls",
        ]:
            self.assertIn(item, text)
        self.assertIn("ANYTOOUR_DEPLOY_SSH_KEY", text)
        self.assertIn("actions/upload-artifact@v4", text)
        self.assertNotIn("workflow_dispatch", text)

if __name__ == "__main__":
    unittest.main()
