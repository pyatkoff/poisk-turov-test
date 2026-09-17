import importlib.util
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "anex_preview_gate_v3", ROOT / "scripts/diagnostics/anex_search3_preview_owner_gate_v3.py"
)
gate = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(gate)
WORKFLOW = ROOT / ".github/workflows/publish-anex-search3-preview-v3.yml"
SOURCE_SHA = "ebc508e31e5294d0e0aa8733dbe271e68efa9502"
SOURCE_BRANCH = "feature/int-andromeda-offer-autosave-2506-20260917"
INT_SHA = "22a6fee1d2e6c4c6249322304524d8fcebd543e6"
INT_BRANCH = "feature/anex-search-adapter-20260907"
OPERATION = "publish-anex-search3-preview-2530-v3"
REQUIRED_RUNS = [
    "35238158503",  # Andromeda AnyTour offer autosave
    "35238158361",  # Three-provider money facts
    "35238158481",  # final-price readiness
    "35238158251",  # INT release readiness
    "35238158241",  # Security
    "35238158617",  # whole-site build
]


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


class OwnerGateV3Test(unittest.TestCase):
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

    def test_workflow_pins_exact_merged_green_tree_and_no_replay_operation(self):
        text = WORKFLOW.read_text(encoding="utf-8")
        self.assertIn("issue_comment:", text)
        self.assertIn("types: [created]", text)
        self.assertNotIn("\n  push:", text)
        self.assertNotIn("workflow_dispatch:", text)
        self.assertIn(gate.COMMAND, text)
        self.assertIn(SOURCE_SHA, text)
        self.assertIn(SOURCE_BRANCH, text)
        self.assertIn(INT_SHA, text)
        self.assertIn(INT_BRANCH, text)
        self.assertIn(OPERATION, text)
        for run_id in REQUIRED_RUNS:
            self.assertIn(run_id, text)
        self.assertIn("source_tree != int_tree", text)
        self.assertIn("/_preview/search3-anex-candidate/", text)
        self.assertIn("secrets.ANEX_API_TOKEN", text)
        self.assertIn("secrets.ANEX_B2B_TOKEN", text)
        self.assertIn("production_entry_changes", text)
        self.assertIn("replay_allowed':False", text)
        self.assertIn("andromeda-anytour-offer-autosave.php", text)
        self.assertIn("three-provider-money-facts.php", text)


if __name__ == "__main__":
    unittest.main()
