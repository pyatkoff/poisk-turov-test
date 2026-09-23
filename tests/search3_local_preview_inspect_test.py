"""Offline guards for the read-only Search3 LOCAL predecessor inspector."""
import copy
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/deploy'))
import search3_local_preview_inspect as inspect


class InspectContracts(unittest.TestCase):
    def setUp(self):
        self.event = {
            'repository': {'full_name': inspect.REPO, 'id': 1345518271},
            'sender': {'login': 'pyatkoff', 'id': inspect.OWNER_ID},
            'action': 'created',
            'issue': {'number': inspect.COORDINATION_ISSUE},
            'comment': {
                'id': 123,
                'user': {'id': inspect.OWNER_ID},
                'author_association': 'OWNER',
                'body': inspect.PREFIX,
            },
        }
        self.env = {
            'GITHUB_REPOSITORY': inspect.REPO,
            'GITHUB_REF': 'refs/heads/main',
            'GITHUB_ACTOR': 'pyatkoff',
            'GITHUB_TRIGGERING_ACTOR': 'pyatkoff',
            'GITHUB_ACTOR_ID': str(inspect.OWNER_ID),
            'GITHUB_RUN_ATTEMPT': '1',
            'GITHUB_EVENT_NAME': 'issue_comment',
            'GITHUB_RUN_ID': '456',
        }

    def test_exact_owner_command(self):
        self.assertEqual(inspect.checked_request(self.event, self.env), {'run': 456})

    def test_old_or_pr_coordination_denied(self):
        for issue in (2530, 996):
            event = copy.deepcopy(self.event)
            event['issue']['number'] = issue
            with self.subTest(issue=issue), self.assertRaises(ValueError):
                inspect.checked_request(event, self.env)
        event = copy.deepcopy(self.event)
        event['issue']['pull_request'] = {'url': 'x'}
        with self.assertRaises(ValueError):
            inspect.checked_request(event, self.env)

    def test_command_and_replay_guards(self):
        for body in (inspect.PREFIX + ' x', inspect.PREFIX + '\n', '/update-search3-local-preview'):
            event = copy.deepcopy(self.event)
            event['comment']['body'] = body
            with self.subTest(body=repr(body)), self.assertRaises(ValueError):
                inspect.checked_request(event, self.env)
        with self.assertRaises(ValueError):
            inspect.checked_request(self.event, {**self.env, 'GITHUB_RUN_ATTEMPT': '2'})

    def test_identity_guards(self):
        for key, value in (
            ('GITHUB_REF', 'refs/heads/work'),
            ('GITHUB_ACTOR', 'other'),
            ('GITHUB_TRIGGERING_ACTOR', 'other'),
            ('GITHUB_EVENT_NAME', 'workflow_dispatch'),
        ):
            with self.subTest(key=key), self.assertRaises(ValueError):
                inspect.checked_request(self.event, {**self.env, key: value})

    def test_sanitized_usable_predecessor(self):
        target = 'a' * 64
        result = inspect.sanitize_snapshot({
            'target': target,
            'owner': {'status': 'published', 'source': 'b' * 40, 'run': 789, 'digest': target},
            'protected': {'config.php': 'SECRETISH_HASH'},
        })
        self.assertTrue(result['predecessor_usable'])
        self.assertTrue(result['owner_digest_matches_target'])
        self.assertEqual(result['owner_source'], 'b' * 40)
        self.assertNotIn('protected', result)
        self.assertNotIn('SECRETISH_HASH', repr(result))
        self.assertEqual(result['target_writes'], 0)
        self.assertEqual(result['publisher_metadata_writes'], 0)

    def test_mismatch_is_reported_not_invented(self):
        result = inspect.sanitize_snapshot({
            'target': 'a' * 64,
            'owner': {'status': 'published', 'source': 'b' * 40, 'run': 789, 'digest': 'c' * 64},
        })
        self.assertFalse(result['predecessor_usable'])
        self.assertFalse(result['owner_digest_matches_target'])
        self.assertEqual(result['owner_source'], 'b' * 40)

    def test_malformed_owner_fails_closed(self):
        result = inspect.sanitize_snapshot({'target': 'x', 'owner': {'source': 'main', 'run': True, 'status': 'foreign'}})
        self.assertIsNone(result['target_digest'])
        self.assertIsNone(result['owner_source'])
        self.assertIsNone(result['owner_run'])
        self.assertIsNone(result['owner_status'])
        self.assertFalse(result['predecessor_usable'])


if __name__ == '__main__':
    unittest.main()
