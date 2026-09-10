#!/usr/bin/env python3
"""Never repeat a charged search after completion, interruption or a lost result."""
import copy
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/diagnostics'))
import anex_search3_topup_experiment as t


class TopupTest(unittest.TestCase):
    def test_topup_only_test_fix_cannot_start_the_old_observed_queue(self):
        workflow = (Path(__file__).resolve().parents[1] / '.github/workflows/anex-access-probe.yml').read_text()
        private_job = workflow.split('\n  verify-access:\n', 1)[1].split('    steps:\n', 1)[0]
        # A changed path is no longer authority to run even the topup operation.
        self.assertIn('    needs: offline-checks\n', private_job)
        self.assertIn("    if: github.event_name == 'workflow_dispatch' && github.ref == 'refs/heads/feature/anex-search-adapter-20260907'\n", private_job)
        self.assertNotIn('topup =', workflow)
        self.assertNotIn('diff-tree', workflow)

    def setUp(self):
        temp = tempfile.TemporaryDirectory()
        self.addCleanup(temp.cleanup)
        self.directory = Path(temp.name)
        for name in t.PROTECTED:
            (self.directory / name).write_text('{"preserved":true}')
        (self.directory / 'anex-checkpoint-source.json').write_text(json.dumps({'artifact_id': t.BOOTSTRAP}))
        self.environment = patch.dict(os.environ, {'GITHUB_RUN_ID': '100', 'GITHUB_RUN_ATTEMPT': '1'})
        self.environment.start()
        self.addCleanup(self.environment.stop)
        self.result = {'schema_version': 1, 'experiment_id': t.EXPERIMENT, 'status': 'ok',
                       'requests': {'anex': 0, 'tourvisor': 5}, 'preservation_verified': True,
                       'summary': {'returned_hotels': 2}}

    def test_only_original_bootstrap_can_start_a_missing_experiment(self):
        (self.directory / 'anex-checkpoint-source.json').write_text(json.dumps({'artifact_id': t.BOOTSTRAP + 1}))
        with patch.object(t, 'request_once') as call, self.assertRaises(ValueError):
            t.prepare(self.directory)
        call.assert_not_called()
        self.assertFalse((self.directory / t.CHECKPOINT).exists())

    def test_completed_search_is_preserved_across_attempts_and_not_repeated(self):
        t.prepare(self.directory)
        with patch.object(t, 'request_once', return_value=self.result) as call:
            self.assertEqual(t.run(self.directory)['status'], 'ok')
            self.assertEqual(t.run(self.directory)['supplier_requests'], 0)
            with patch.dict(os.environ, {'GITHUB_RUN_ATTEMPT': '2'}):
                self.assertEqual(t.prepare(self.directory)['status'], 'completed')
                self.assertEqual(t.run(self.directory)['supplier_requests'], 0)
            self.assertEqual(call.call_count, 1)
        report = t.finalize(self.directory)
        self.assertEqual(report['result'], self.result)
        self.assertFalse(report['automatic_topup_enabled'])
        self.assertTrue(report['historical_files_unchanged'])

    def test_lost_response_is_never_replayed(self):
        t.prepare(self.directory)
        with patch.object(t, 'request_once', side_effect=ValueError('unknown SSH result')) as call:
            self.assertEqual(t.run(self.directory)['status'], 'interrupted_result_unknown')
            self.assertEqual(t.run(self.directory)['supplier_requests'], 0)
            self.assertEqual(call.call_count, 1)
        self.assertIsNone(t.finalize(self.directory)['result'])

    def test_previous_job_reservation_cannot_start_supplier_request(self):
        t.prepare(self.directory)
        with patch.dict(os.environ, {'GITHUB_RUN_ATTEMPT': '2'}), patch.object(t, 'request_once') as call:
            self.assertEqual(t.prepare(self.directory)['status'], 'interrupted_result_unknown')
            self.assertEqual(t.run(self.directory)['supplier_requests'], 0)
            call.assert_not_called()

    def test_running_marker_stops_reentry_even_in_same_job(self):
        t.prepare(self.directory)
        def check_marker():
            self.assertEqual(t.load(self.directory)['state'], 'running')
            self.assertEqual(t.run(self.directory)['supplier_requests'], 0)
            return self.result
        with patch.object(t, 'request_once', side_effect=check_marker) as call:
            self.assertEqual(t.run(self.directory)['status'], 'ok')
            self.assertEqual(call.call_count, 1)

    def test_changed_evidence_and_unexpected_anex_request_fail_closed(self):
        t.prepare(self.directory)
        path = self.directory / t.PROTECTED[0]
        original = path.read_text()
        path.write_text('{}')
        with patch.object(t, 'request_once') as call, self.assertRaises(ValueError):
            t.run(self.directory)
        call.assert_not_called()
        path.write_text(original)
        result = copy.deepcopy(self.result)
        result['requests']['anex'] = 1
        with patch.object(t, 'request_once', return_value=result) as call:
            self.assertEqual(t.run(self.directory)['status'], 'interrupted_result_unknown')
            self.assertEqual(t.run(self.directory)['supplier_requests'], 0)
            self.assertEqual(call.call_count, 1)


if __name__ == '__main__':
    unittest.main()
