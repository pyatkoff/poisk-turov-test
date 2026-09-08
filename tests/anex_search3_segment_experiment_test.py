#!/usr/bin/env python3
"""One-shot segment requests, preserved history, and bounded coverage evidence."""
import copy
import csv
import hashlib
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/diagnostics'))
import anex_search3_segment_experiment as s


def offer(hotel_id, local_hotel_id=None):
    return {'hotel_id': hotel_id, 'local_hotel_id': local_hotel_id,
            'date': '2026-09-16', 'nights': 7, 'adults': 2, 'children': 0,
            'price': '50000', 'currency': 'RUB', 'hotel_name': 'Example Hotel'}


class SegmentExperimentTest(unittest.TestCase):
    def setUp(self):
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        self.directory = Path(temporary.name)
        self.live = {'completed_total': 3, 'in_flight': [],
                     'inherited': [{'anex_hotel_id': 1, 'status': 'review'}],
                     'rows': [{'anex_hotel_id': 2, 'status': 'review'},
                              {'anex_hotel_id': 3, 'status': 'source_error'}]}
        self.saved = {'tv_day': {'status': 'ok', 'offers': [offer(10)]},
                      'tv_week': {'status': 'ok', 'offers': [offer(10), offer(11)]},
                      'anex_day': {'status': 'ok', 'offers': [offer(500, 20), offer(501, 22), offer(502)]}}
        for name in s.PROTECTED:
            (self.directory / name).write_text('{}')
        baseline_cp = {'cases': {key: {'state': 'completed', 'result': result,
                                      'result_sha256': s.gaps.digest(result)}
                                 for key, result in self.saved.items()}}
        baseline_bytes = json.dumps(baseline_cp).encode()
        (self.directory / s.BASELINE).write_bytes(baseline_bytes)
        self.write_source(s.BOOTSTRAP)
        for context in (
                patch.object(s, 'BASELINE_SHA', hashlib.sha256(baseline_bytes).hexdigest()),
                patch.object(s.observed, 'restore', side_effect=lambda _: copy.deepcopy(self.live)),
                patch.object(s.observed, 'needs_finalization', return_value=False),
                patch.dict(os.environ, {'GITHUB_RUN_ID': '10', 'GITHUB_RUN_ATTEMPT': '1'})):
            context.start()
            self.addCleanup(context.stop)

    def write_source(self, artifact_id):
        (self.directory / 'anex-checkpoint-source.json').write_text(json.dumps({'artifact_id': artifact_id}))

    def result(self, case_id='tv_alanya', offers=None, status='ok'):
        return {'schema_version': 1, 'experiment_id': s.EXPERIMENT, 'case_id': case_id,
                'status': status, 'offers': offers or [], 'preservation_verified': True,
                'requests': {'anex': 0, 'tourvisor': 5, 'total': 5}}

    def test_only_pinned_baseline_and_approved_bootstrap_can_reserve(self):
        self.write_source(s.BOOTSTRAP + 1)
        with self.assertRaisesRegex(ValueError, 'approved bootstrap'):
            s.prepare(self.directory)
        self.assertFalse((self.directory / s.CHECKPOINT).exists())
        self.write_source(s.BOOTSTRAP)
        self.assertEqual(s.prepare(self.directory)['status'], 'reserved')
        self.assertEqual(s.load_checkpoint(self.directory)['observed_history']['completed_total'], 3)
        # A later artifact missing the segment checkpoint must never restart all cases.
        (self.directory / s.CHECKPOINT).unlink()
        self.write_source(s.BOOTSTRAP + 1)
        with self.assertRaisesRegex(ValueError, 'approved bootstrap'):
            s.prepare(self.directory)

    def test_changed_baseline_or_unfinished_observed_batch_stops_before_reservation(self):
        baseline_path = self.directory / s.BASELINE
        original = baseline_path.read_bytes()
        baseline_path.write_bytes(original + b' ')
        with self.assertRaisesRegex(ValueError, 'saved paired comparison changed'):
            s.prepare(self.directory)
        baseline_path.write_bytes(original)
        self.live['in_flight'] = [100]
        with self.assertRaisesRegex(ValueError, 'unfinished observed batch'):
            s.prepare(self.directory)
        self.live['in_flight'] = []
        with patch.object(s.observed, 'needs_finalization', return_value=True):
            with self.assertRaisesRegex(ValueError, 'unfinished observed batch'):
                s.prepare(self.directory)
        self.assertFalse((self.directory / s.CHECKPOINT).exists())

    def test_completed_result_is_read_back_and_never_replayed(self):
        s.prepare(self.directory)
        with patch.object(s, 'ssh_php', return_value=self.result()) as ssh:
            first = s.run_case(self.directory, 'tv_alanya')
            self.assertEqual(first['status'], 'ok')
            self.assertEqual(s.run_case(self.directory, 'tv_alanya')['status'], 'already_finalized')
            self.assertEqual(ssh.call_count, 1)
        checkpoint = s.load_checkpoint(self.directory)
        self.assertEqual(checkpoint['cases']['tv_alanya']['result_sha256'], s.gaps.digest(self.result()))
        checkpoint['cases']['tv_alanya']['result']['offers'].append(offer(999))
        (self.directory / s.CHECKPOINT).write_text(json.dumps(checkpoint))
        with self.assertRaisesRegex(ValueError, 'digest mismatch'):
            s.load_checkpoint(self.directory)

    def test_new_job_marks_only_unconfirmed_reservations_unknown_without_requests(self):
        s.prepare(self.directory)
        with patch.object(s, 'ssh_php', return_value=self.result()):
            s.run_case(self.directory, 'tv_alanya')
        with patch.dict(os.environ, {'GITHUB_RUN_ATTEMPT': '2'}), patch.object(s, 'ssh_php') as ssh:
            states = s.prepare(self.directory)['states']
            self.assertEqual(states, {'tv_alanya': 'completed', 'tv_5star': 'interrupted_result_unknown',
                                      'tv_alanya_5star': 'interrupted_result_unknown'})
            for case_id in s.CASES:
                self.assertEqual(s.run_case(self.directory, case_id)['status'], 'already_finalized')
            ssh.assert_not_called()

    def test_lost_response_and_anex_requests_are_unknown_and_not_replayed(self):
        s.prepare(self.directory)
        with patch.object(s, 'ssh_php', side_effect=ValueError('SSH response lost')) as ssh:
            self.assertEqual(s.run_case(self.directory, 'tv_alanya')['status'], 'interrupted_result_unknown')
            self.assertEqual(s.run_case(self.directory, 'tv_alanya')['status'], 'already_finalized')
            self.assertEqual(ssh.call_count, 1)
        forbidden = self.result('tv_5star')
        forbidden['requests']['anex'] = 1
        with patch.object(s, 'ssh_php', return_value=forbidden) as ssh:
            self.assertEqual(s.run_case(self.directory, 'tv_5star')['status'], 'interrupted_result_unknown')
            s.run_case(self.directory, 'tv_5star')
            self.assertEqual(ssh.call_count, 1)

    def test_observed_append_is_allowed_but_old_results_and_totals_cannot_regress(self):
        s.prepare(self.directory)
        history = copy.deepcopy(self.live)
        self.live['rows'].append({'anex_hotel_id': 4, 'status': 'review'})
        self.live['completed_total'] = 4
        self.assertEqual(s.load_checkpoint(self.directory)['observed_history']['completed_total'], 3)
        mutations = [
            lambda live: live['rows'][0].update(status='strong_candidate'),
            lambda live: live['inherited'][0].update(status='source_error'),
            lambda live: live.update(completed_total=2),
            lambda live: live['rows'].pop(),
        ]
        for mutate in mutations:
            self.live = copy.deepcopy(history)
            mutate(self.live)
            with self.subTest(live=self.live), self.assertRaisesRegex(ValueError, 'historical observed results changed'):
                s.load_checkpoint(self.directory)

    def test_protected_file_mutation_stops_resume(self):
        s.prepare(self.directory)
        (self.directory / 'anex-owner-hotel-decisions.json').write_text('{"altered":true}')
        with self.assertRaisesRegex(ValueError, 'provenance changed'):
            s.load_checkpoint(self.directory)

    def test_analysis_separates_raw_from_validated_and_deduplicates_across_segments(self):
        first = self.result(offers=[offer(20), offer(20)])
        first['raw_response'] = {'hotel_ids': [20, 20, 21, 99]}
        first['filter_verification'] = {'response_respects_requested_filters': False}
        second = self.result('tv_5star', [offer(20), offer(22)])
        second['raw_response'] = {'hotel_ids': [20, 22]}
        failed = self.result('tv_alanya_5star', [offer(30)], status='probe_unavailable')
        result = s.analysis(self.saved, {'tv_alanya': first, 'tv_5star': second, 'tv_alanya_5star': failed})
        self.assertEqual(result['baseline_tv_unique_hotels'], 2)
        self.assertEqual(result['cases']['tv_alanya']['raw_unique_hotels'], 3)
        self.assertEqual(result['cases']['tv_alanya']['validated_unique_hotels'], 1)
        self.assertFalse(result['cases']['tv_alanya']['filter_verification']['response_respects_requested_filters'])
        self.assertEqual(result['cases']['tv_5star']['additional_vs_previous_segments'], ['22'])
        self.assertEqual(result['combined_tv_unique_hotels'], 4)
        self.assertEqual(result['new_tv_hotel_ids'], ['20', '22'])
        self.assertEqual(result['combined_accepted_anex_overlap'], ['20', '22'])
        self.assertEqual(result['new_mappings_accepted'], 0)
        self.assertEqual(result['anex_requests'], 0)
        self.assertTrue(result['anex_baseline_reused'])
        self.assertIn('not a synchronous price comparison', result['interpretation'])

    def test_finalize_saves_json_and_csv_without_supplier_or_mapping_acceptance(self):
        s.prepare(self.directory)
        result = self.result(offers=[dict(offer(20), hotel_name='=HYPERLINK("bad")')])
        with patch.object(s, 'ssh_php', return_value=result):
            s.run_case(self.directory, 'tv_alanya')
        with patch.object(s, 'ssh_php') as ssh, patch.object(s.observed, 'snapshot') as snapshot:
            final = s.finalize(self.directory, refresh=False)
            ssh.assert_not_called()
            snapshot.assert_not_called()
        saved = json.loads((self.directory / 'anex-segment-search-report.json').read_text())
        self.assertEqual(saved['analysis'], final['analysis'])
        self.assertTrue(saved['historical_files_unchanged'])
        self.assertEqual(saved['analysis']['new_mappings_accepted'], 0)
        with (self.directory / 'anex-segment-search-offers.csv').open() as handle:
            rows = list(csv.DictReader(handle))
        self.assertEqual(len(rows), 1)
        self.assertEqual(rows[0]['hotel_name'], "'=HYPERLINK(\"bad\")")


if __name__ == '__main__':
    unittest.main()
