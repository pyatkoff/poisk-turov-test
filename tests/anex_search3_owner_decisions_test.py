import hashlib
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts/diagnostics'))
import anex_search3_owner_decisions as owner
import anex_search3_observed_queue as live
import anex_search3_gap_queue as gaps


class OwnerDecisionsTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.directory = Path(self.temp.name)
        self.history = {identifier: ({'status': 'review', 'reason': 'candidate_limit_reached',
            'api': {'id': identifier}, 'candidates': [{'id': target}]}, 'live_checkpoint')
            for identifier, target in owner.PAIRS.items()}
        self.manifest = {'rows': [{'anex_hotel_id': identifier, 'catalog_hotel_id': target,
            'country': 'Турция', 'evidence_row_sha256': gaps.digest(self.history[identifier][0])}
            for identifier, target in owner.PAIRS.items()]}
        self.path = self.directory / 'approval.json'
        self.path.write_text(json.dumps(self.manifest))
        self.patches = [patch.object(owner, 'MANIFEST', self.path),
                        patch.object(owner, 'MANIFEST_SHA256', hashlib.sha256(self.path.read_bytes()).hexdigest()),
                        patch.object(live, 'restore', return_value={'in_flight': []}),
                        patch.object(live, 'evidence_history', return_value=self.history)]
        for item in self.patches:
            item.start()
            self.addCleanup(item.stop)

    def result(self, inserted=9):
        return {'status': 'imported' if inserted else 'already_imported', 'inserted': inserted,
                'approval_id': owner.APPROVAL_ID, 'approved_count': 9, 'readback_verified': True,
                'rows': [{'anex_hotel_id': i, 'catalog_hotel_id': t} for i, t in owner.PAIRS.items()],
                'preservation_before': {'prior': 'unchanged'}, 'preservation_after': {'prior': 'unchanged'}}

    def test_approval_is_bound_to_exact_saved_evidence(self):
        self.assertEqual(len(owner.build_request(self.directory)['rows']), 9)
        identifier = next(iter(self.history))
        self.history[identifier][0]['candidates'][0]['id'] += 1
        with self.assertRaisesRegex(ValueError, 'source evidence changed'):
            owner.build_request(self.directory)

    def test_changed_manifest_and_unfinished_batch_refuse_before_ssh(self):
        with patch.object(live, 'restore', return_value={'in_flight': [1]}):
            with self.assertRaisesRegex(ValueError, 'unfinished live batch'):
                owner.build_request(self.directory)
        self.path.write_text(self.path.read_text() + ' ')
        with self.assertRaisesRegex(ValueError, 'manifest changed'):
            owner.build_request(self.directory)

    def test_applied_decisions_are_not_reimported(self):
        with patch.object(owner, 'ssh_apply', return_value=self.result()) as apply:
            self.assertEqual(owner.apply(self.directory)['inserted'], 9)
            self.assertEqual(owner.apply(self.directory)['inserted'], 0)
        apply.assert_called_once()
        self.assertEqual(json.loads((self.directory / owner.REPORT).read_bytes())['state'], 'applied')
        self.assertEqual(json.loads((self.directory / owner.RUN_REPORT).read_bytes())['inserted'], 0)

    def test_lost_commit_response_keeps_request_for_idempotent_readback(self):
        with patch.object(owner, 'ssh_apply', side_effect=RuntimeError('unconfirmed')):
            with self.assertRaises(RuntimeError):
                owner.apply(self.directory)
        before = json.loads((self.directory / owner.REPORT).read_bytes())
        self.assertEqual(before['state'], 'prepared')
        with patch.object(owner, 'ssh_apply', return_value=self.result(0)) as apply:
            self.assertEqual(owner.apply(self.directory)['inserted'], 0)
        self.assertEqual(apply.call_args.args[0], before['request'])

    def test_wrong_readback_or_preservation_cannot_finalize(self):
        for kind in ('rows', 'preservation_after'):
            result = self.result()
            result[kind] = [] if kind == 'rows' else {'prior': 'changed'}
            with patch.object(owner, 'ssh_apply', return_value=result):
                with self.assertRaisesRegex(ValueError, 'not confirmed'):
                    owner.apply(self.directory)
            self.assertEqual(json.loads((self.directory / owner.REPORT).read_bytes())['state'], 'prepared')


if __name__ == '__main__':
    unittest.main()
