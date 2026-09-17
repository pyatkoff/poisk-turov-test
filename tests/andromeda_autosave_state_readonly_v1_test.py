import importlib.util
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]
GATE_PATH = ROOT / 'scripts/diagnostics/andromeda_autosave_state_owner_gate_v1.py'
AUDIT_PATH = ROOT / 'scripts/diagnostics/andromeda_autosave_state_readonly_v1.php'
WORKFLOW_PATH = ROOT / '.github/workflows/andromeda-autosave-state-readonly-v1.yml'
SPEC = importlib.util.spec_from_file_location('gate', GATE_PATH)
gate = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(gate)


def event(body=gate.COMMAND, *, issue=2530, login='pyatkoff', user_id=226193297, action='created', pr=False):
    issue_value = {'number': issue}
    if pr:
        issue_value['pull_request'] = {'url': 'https://example.invalid/pr'}
    return {'action': action, 'issue': issue_value, 'comment': {'body': body, 'user': {'login': login, 'id': user_id}}}


def env(**extra):
    value = {
        'GITHUB_EVENT_NAME': 'issue_comment',
        'GITHUB_REPOSITORY': gate.REPOSITORY,
        'GITHUB_REF': gate.MAIN_REF,
        'GITHUB_RUN_ATTEMPT': '1',
        'GITHUB_ACTOR': gate.OWNER_LOGIN,
        'GITHUB_TRIGGERING_ACTOR': gate.OWNER_LOGIN,
    }
    value.update(extra)
    return value


class ReadonlyAutosaveStateTest(unittest.TestCase):
    def test_gate_accepts_only_exact_owner_command(self):
        self.assertEqual(gate.authorize(event(), env()), (True, 'authorized'))
        cases = [
            (event(body=gate.COMMAND + ' '), env()),
            (event(issue=996), env()),
            (event(login='other'), env()),
            (event(user_id=1), env()),
            (event(action='edited'), env()),
            (event(pr=True), env()),
            (event(), env(GITHUB_RUN_ATTEMPT='2')),
            (event(), env(GITHUB_REF='refs/heads/other')),
            (event(), env(GITHUB_ACTOR='github-actions')),
        ]
        for payload, environment in cases:
            self.assertFalse(gate.authorize(payload, environment)[0])

    def test_php_is_strictly_read_only_and_supplier_free(self):
        text = AUDIT_PATH.read_text(encoding='utf-8')
        for token in [
            'INSERT INTO', 'UPDATE anytour_', 'DELETE FROM', 'REPLACE INTO',
            'CREATE TABLE', 'ALTER TABLE', 'DROP TABLE', 'FOR UPDATE',
            'AnyTourAndromedaTransport', 'ensureLogin', 'broninit', 'getFlights',
            'gateway.samo.ru', 'curl_init', 'curl_exec',
        ]:
            self.assertNotIn(token, text)
        self.assertNotRegex(text, re.compile(r"file_get_contents\s*\(\s*['\"]https?://", re.I))
        self.assertIn("SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda'", text)
        self.assertIn("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider='andromeda'", text)
        self.assertIn('anytour-offer-autosave-v1', text)
        self.assertIn('surcharge-v1', text)
        self.assertIn('runtime_hashes_verified', text)
        self.assertIn("'filesystem_writes' => 0", text)
        self.assertIn("'supplier_calls' => 0", text)
        self.assertIn("'db_writes' => 0", text)

    def test_workflow_has_no_remote_mutation_or_replay(self):
        text = WORKFLOW_PATH.read_text(encoding='utf-8')
        self.assertIn('issue_comment:', text)
        self.assertIn('types: [created]', text)
        self.assertNotIn('workflow_dispatch:', text)
        self.assertNotIn('\n  push:', text)
        self.assertIn(gate.COMMAND, text)
        self.assertIn('35238884947', text)
        self.assertIn('ebc508e31e5294d0e0aa8733dbe271e68efa9502', text)
        self.assertIn('php -d display_errors=0 -d log_errors=0', text)
        self.assertNotIn('mkdir "$HOME', text)
        self.assertNotIn('mv "$HOME', text)
        self.assertNotIn('rm -rf "$HOME', text)
        self.assertIn('run_attempt', text)
        self.assertIn('supplier_calls', text)


if __name__ == '__main__':
    unittest.main()
