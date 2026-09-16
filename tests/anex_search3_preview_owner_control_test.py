import importlib.util
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "anex_preview_gate", ROOT / "scripts/diagnostics/anex_search3_preview_owner_gate.py"
)
gate = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(gate)
WORKFLOW = ROOT / ".github/workflows/publish-anex-search3-preview.yml"


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


class OwnerGateTest(unittest.TestCase):
    def test_exact_owner_command_is_authorized(self):
        self.assertEqual(gate.authorize(event(), env()), (True, "authorized"))

    def test_owner_gate_rejects_non_exact_contexts(self):
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

    def test_workflow_is_issue_comment_only_for_publication_and_pins_source(self):
        text = WORKFLOW.read_text(encoding="utf-8")
        self.assertIn("issue_comment:", text)
        self.assertIn("types: [created]", text)
        self.assertIn("pull_request:", text)
        self.assertNotIn("\n  push:", text)
        self.assertIn(gate.COMMAND, text)
        self.assertIn("929a4b3f6206429c41760004b44f1d3004d66129", text)
        self.assertIn("35160429770", text)
        self.assertIn("feature/anex-search-adapter-20260907", text)
        self.assertIn("scripts/diagnostics/anex_search3_preview_deploy_safe.py", text)
        self.assertIn("tests/anex_search3_preview_safe_deploy_test.py", text)
        self.assertIn("ANEX_B2B_TOKEN_READY", text)
        self.assertIn("ANEX_PRIVATE_CONFIG_READY", text)
        self.assertIn("publish-anex-search3-preview-2530-v2", text)
        self.assertIn("/_preview/search3-anex-candidate/", text)
        self.assertEqual(text.count("search3-site-candidate"), 1)
        self.assertIn("! grep -F", text)
        self.assertNotIn("/poisk-turov/?", text)
        self.assertIn("production_entry_changes", text)
        self.assertIn("actions: read", text)
        self.assertIn("contents: read", text)


if __name__ == "__main__":
    unittest.main()
