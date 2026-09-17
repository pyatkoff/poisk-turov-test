import importlib.util
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "anex_preview_gate_v4", ROOT / "scripts/diagnostics/anex_search3_preview_owner_gate_v4.py"
)
gate = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(gate)
WORKFLOW = ROOT / ".github/workflows/publish-anex-search3-preview-v4.yml"
SOURCE_SHA = "8ccf1174c1277e6128258de33d403b52faddf7e2"
SOURCE_TREE = "9859b0044769a9660c03f6cd98d6805d1f771981"
FIX_SHA = "df839684e66ed0a00734d1d6c555e4d6941b4c3a"
FIX_RUN = "35262444268"
ENDPOINT_BLOB = "df3b835fc8497dda4404b1e1301fb4f884336563"
OPERATION = "publish-anex-search3-preview-2530-v4-page2-checkpoint"
CANARY = "andromeda-page2-canary-2530-v4-country4-2026-10-20-2a-7n"


def event(body=gate.COMMAND, *, issue=gate.ISSUE_NUMBER, login=gate.OWNER_LOGIN, user_id=gate.OWNER_ID,
          action="created", pull_request=False):
    issue_value = {"number": issue}
    if pull_request:
        issue_value["pull_request"] = {"url": "https://example.invalid/pr"}
    return {
        "action": action,
        "issue": issue_value,
        "comment": {"body": body, "user": {"login": login, "id": user_id}},
    }


def env(**overrides):
    value = {
        "GITHUB_EVENT_NAME": "issue_comment",
        "GITHUB_REPOSITORY": gate.REPOSITORY,
        "GITHUB_REF": gate.MAIN_REF,
        "GITHUB_RUN_ATTEMPT": "1",
        "GITHUB_ACTOR": gate.OWNER_LOGIN,
        "GITHUB_TRIGGERING_ACTOR": gate.OWNER_LOGIN,
    }
    value.update(overrides)
    return value


class OwnerGateV4Test(unittest.TestCase):
    def test_exact_owner_command_is_authorized(self):
        self.assertEqual(gate.authorize(event(), env()), (True, "authorized"))

    def test_non_exact_contexts_are_rejected(self):
        cases = [
            (event(body=gate.COMMAND + " "), env()),
            (event(issue=996), env()),
            (event(login="other"), env()),
            (event(user_id=1), env()),
            (event(action="edited"), env()),
            (event(pull_request=True), env()),
            (event(), env(GITHUB_EVENT_NAME="pull_request")),
            (event(), env(GITHUB_REPOSITORY="pyatkoff/other")),
            (event(), env(GITHUB_REF="refs/heads/release/search3-production-ready-v1")),
            (event(), env(GITHUB_RUN_ATTEMPT="2")),
            (event(), env(GITHUB_ACTOR="github-actions")),
            (event(), env(GITHUB_TRIGGERING_ACTOR="github-actions")),
        ]
        for payload, environment in cases:
            with self.subTest(payload=payload, environment=environment):
                self.assertFalse(gate.authorize(payload, environment)[0])

    def test_workflow_pins_exact_fix_and_is_no_replay(self):
        text = WORKFLOW.read_text(encoding="utf-8")
        for value in (gate.COMMAND, SOURCE_SHA, SOURCE_TREE, FIX_SHA, FIX_RUN, ENDPOINT_BLOB, OPERATION, CANARY):
            self.assertIn(value, text)
        self.assertIn("issue_comment:", text)
        self.assertIn("types: [created]", text)
        self.assertNotIn("workflow_dispatch:", text)
        self.assertNotIn("\n  push:", text)
        self.assertIn("duplicate_or_stale_owner_command", text)
        self.assertIn("replay_allowed':False", text)
        self.assertIn("/_preview/search3-anex-candidate/", text)
        self.assertIn("production_entry_changes", text)
        self.assertIn("secrets.ANEX_API_TOKEN", text)
        self.assertIn("secrets.ANEX_B2B_TOKEN", text)
        self.assertIn("andromeda-page-session-checkpoint-test.py", text)
        self.assertNotIn("/publish-anex-search3-preview-2530-v3", text)

    def test_live_canary_is_bounded_to_page_one_then_two(self):
        text = WORKFLOW.read_text(encoding="utf-8")
        live = text.split("<?php", 1)[1].split("PHP\n", 1)[0]
        self.assertEqual(live.count("anytour_andromeda_search3_run("), 2)
        self.assertIn("$request['page']=2;", live)
        self.assertIn("page2_not_advertised", live)
        self.assertIn("START TRANSACTION READ ONLY", live)
        self.assertIn("LOCK_SH|LOCK_NB", live)
        self.assertIn("auth_checkpoint_created_at_mismatch", live)
        self.assertIn("page2_checkpoint_missing", live)
        self.assertNotIn("getFlights(", live)
        self.assertNotIn("->calc(", live)
        self.assertNotIn("quote_resolve", live)
        self.assertNotIn("bron_ticket", live)
        self.assertNotIn("$request['page']=3", live)
        self.assertIn("'page_calls'=>0,'max_page'=>0,'database_writes'=>0,'get_flights_calls'=>0,'quote_calls'=>0,'booking_calls'=>0", live)


if __name__ == "__main__":
    unittest.main()
