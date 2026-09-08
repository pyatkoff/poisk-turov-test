#!/usr/bin/env python3
"""Protect one-shot searches, historical checkpoints, and honest comparison counts."""
import copy
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/diagnostics'))
import anex_search3_paired_experiment as p


class PairedExperimentTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.directory = Path(self.temp.name)
        for name in p.PROTECTED:
            (self.directory / name).write_text('{}')
        (self.directory / 'anex-checkpoint-source.json').write_text(json.dumps({'artifact_id': p.BOOTSTRAP_ARTIFACT}))
        self.env = patch.dict(os.environ, {'GITHUB_RUN_ID': '10', 'GITHUB_RUN_ATTEMPT': '1'})
        self.env.start()
        self.addCleanup(self.env.stop)
        with patch.object(p.observed, 'restore', return_value={'in_flight': [], 'completed_total': 260}), patch.object(p.observed, 'needs_finalization', return_value=False):
            p.prepare(self.directory)

    def test_unknown_reservation_never_replays(self):
        with patch.dict(os.environ, {'GITHUB_RUN_ATTEMPT': '2'}), patch.object(p.observed, 'restore', return_value={'in_flight': []}), patch.object(p.observed, 'needs_finalization', return_value=False):
            p.prepare(self.directory)
            with patch.object(p, 'ssh_php') as ssh:
                for case_id in p.CASES:
                    self.assertEqual(p.run_case(self.directory, case_id)['status'], 'already_finalized')
                ssh.assert_not_called()

    def test_completed_readback_and_no_second_search(self):
        result = {'schema_version': 1, 'experiment_id': p.EXPERIMENT, 'case_id': 'tv_day',
                  'status': 'ok', 'offers': [], 'preservation_verified': True}
        with patch.object(p, 'ssh_php', return_value=result) as ssh:
            p.run_case(self.directory, 'tv_day')
            p.run_case(self.directory, 'tv_day')
            self.assertEqual(ssh.call_count, 1)
        cp = p.load_checkpoint(self.directory)
        cp['cases']['tv_day']['result']['offers'].append({'hotel_id': 7})
        (self.directory / p.CHECKPOINT).write_text(json.dumps(cp))
        with self.assertRaisesRegex(ValueError, 'digest'):
            p.load_checkpoint(self.directory)

    def test_partial_response_is_unknown_and_history_is_protected(self):
        with patch.object(p, 'ssh_php', side_effect=ValueError('response lost')):
            self.assertEqual(p.run_case(self.directory, 'tv_day')['status'], 'interrupted_result_unknown')
        (self.directory / p.PROTECTED[0]).write_text('{"changed":true}')
        with self.assertRaisesRegex(ValueError, 'historical'):
            p.load_checkpoint(self.directory)

    def test_no_checkpoint_reset_from_later_artifact(self):
        (self.directory / p.CHECKPOINT).unlink()
        (self.directory / 'anex-checkpoint-source.json').write_text('{"artifact_id":10099999999}')
        with patch.object(p.observed, 'restore', return_value={'in_flight': []}), patch.object(p.observed, 'needs_finalization', return_value=False):
            with self.assertRaisesRegex(ValueError, 'single approved bootstrap'):
                p.prepare(self.directory)

    def test_duplicate_tours_not_hotels_and_price_never_accepts_link(self):
        criteria = {'date': '2026-09-16', 'nights': 7, 'adults': 2, 'children': 0, 'currency': 'RUB'}
        tv = dict(criteria, hotel_id=10, price=50000)
        anex = dict(criteria, hotel_id=123, local_hotel_id=10, price=50000)
        other = dict(anex, hotel_id=124, local_hotel_id=None)
        results = {'tv_day': {'status': 'ok', 'offers': [tv, tv]},
                   'tv_week': {'status': 'ok', 'offers': [dict(tv, hotel_id=11)]},
                   'anex_day': {'status': 'ok', 'offers': [anex, other]}}
        comparison = p.compare(results)
        self.assertEqual(comparison['tv_day_unique_hotels'], 1)
        self.assertEqual(comparison['accepted_overlap_count'], 1)
        self.assertEqual(comparison['new_mappings_accepted'], 0)
        self.assertEqual(len(comparison['aligned_date_party_examples']), 1)
        self.assertFalse(comparison['date_width_searches_completed'])
        self.assertFalse(comparison['date_width_output_uncapped'])
        self.assertFalse(comparison['aligned_date_party_examples'][0]['identical_package_verified'])
        incomplete = copy.deepcopy(results)
        del incomplete['tv_week']
        self.assertFalse(p.compare(incomplete)['date_width_cases_successful'])


if __name__ == '__main__':
    unittest.main()
