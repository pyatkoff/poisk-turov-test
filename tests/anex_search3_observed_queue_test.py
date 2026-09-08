import copy
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts/diagnostics'))
import anex_search3_observed_queue as live
import anex_search3_gap_queue as gaps


class ObservedQueueTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.directory = Path(self.temp.name)
        queue = gaps.load_queue()
        old = {'schema_version': 1, 'scope': 'preview', 'queue_sha256': gaps.QUEUE_SHA,
               'rows': [{'external_id': r['anex_hotel_id'], 'status': 'source_error',
                         'reason': 'interrupted_result_unknown', 'candidates': []} for r in queue['rows'][:90]],
               'in_flight': [], 'completed_total': 90, 'remaining': 22}
        (self.directory / gaps.CHECKPOINT).write_text(json.dumps(old))
        (self.directory / 'anex-checkpoint-source.json').write_text(json.dumps({'artifact_id': live.BOOTSTRAP_ARTIFACT}))
        self.cp = live.restore(self.directory)

    def observed(self, ids):
        return {'scope': 'preview', 'status': 'ok', 'truncated': False,
                'effective_mapped_count': 12872,
                'counts': {'observed': len(ids), 'pending': len(ids), 'mapped': 0, 'manual_review': 0},
                'pending': [{'anex_hotel_id': i, 'country_id': 4 if n % 2 else 1, 'anex_country_id': 11,
                             'country_name': 'Egypt', 'hotel_name': 'Example', 'search_count': 500 - n,
                             'last_seen_utc': '2026-09-08 12:00:00'} for n, i in enumerate(ids)],
                'manual_review': []}

    def test_missing_checkpoint_after_bootstrap_refuses_reset(self):
        self.assertEqual(self.cp['completed_total'], 90)
        (self.directory / 'anex-checkpoint-source.json').write_text('{"artifact_id":10062698821}')
        with self.assertRaises(ValueError):
            live.restore(self.directory)
        live.save(self.directory, self.cp)
        self.assertEqual(live.restore(self.directory), self.cp)

    def test_prior_unknown_and_completed_ids_cannot_starve_new_arrivals(self):
        old = [r['external_id'] for r in self.cp['inherited']]
        self.assertEqual([r['anex_hotel_id'] for r in live.eligible(self.cp, self.observed(old + [90000001]))], [90000001])
        cp = copy.deepcopy(self.cp)
        cp['rows'] = [{'external_id': 90000000 + i, 'status': 'review'} for i in range(1100)]
        cp['completed_total'] += 1100
        ids = [r['external_id'] for r in cp['rows']] + [99999999]
        self.assertEqual([r['anex_hotel_id'] for r in live.eligible(cp, self.observed(ids))], [99999999])

    def test_prepare_has_no_supplier_calls_and_reserves_only_30_across_countries(self):
        observed = self.observed(list(range(90000000, 90000040)))
        env = {'ANEX_CATALOG_ARTIFACT_DIR': str(self.directory), 'GITHUB_RUN_ID': '1', 'GITHUB_RUN_ATTEMPT': '1'}
        with patch.dict(os.environ, env), patch.object(sys, 'argv', ['queue', '--prepare']), \
                patch.object(live, 'snapshot', return_value=observed), patch.object(gaps, 'ssh_batch') as api:
            live.main()
        api.assert_not_called()
        cp = live.restore(self.directory)
        self.assertEqual(cp['in_flight'], list(range(90000000, 90000030)))
        self.assertEqual({r['country_id'] for r in cp['admissions']}, {1, 4})
        self.assertEqual(cp['inherited'], self.cp['inherited'])
        self.assertEqual(cp['completed_total'], 90)

    def test_interrupted_reservation_is_retained_without_a_second_batch(self):
        cp = copy.deepcopy(self.cp)
        cp['admissions'] = self.observed([90000000])['pending']
        cp['in_flight'] = [90000000]
        live.save(self.directory, cp)
        env = {'ANEX_CATALOG_ARTIFACT_DIR': str(self.directory), 'GITHUB_RUN_ID': '2', 'GITHUB_RUN_ATTEMPT': '1'}
        with patch.dict(os.environ, env), patch.object(sys, 'argv', ['queue', '--prepare']), \
                patch.object(live, 'snapshot', return_value=self.observed([90000000, 90000001])):
            live.main()
        after = live.restore(self.directory)
        self.assertEqual(after['completed_total'], 91)
        self.assertEqual(after['in_flight'], [])
        self.assertEqual(after['rows'][0]['reason'], 'interrupted_result_unknown')
        self.assertEqual(after['rows'][0]['status'], 'source_error')
        self.assertEqual(after['admissions'], cp['admissions'])

    def test_merge_rejects_repeat_foreign_id_and_partial_response(self):
        cp = copy.deepcopy(self.cp)
        cp['admissions'] = self.observed([90000000, 90000001])['pending']
        cp['in_flight'] = [90000000, 90000001]
        row = {'external_id': 90000000, 'status': 'review', 'reason': 'competing_candidates'}
        for rows in ([row], [row, row], [row, dict(row, external_id=90000002)]):
            with self.assertRaises(ValueError):
                live.merge(cp, rows)
        after = live.merge(cp, [row, dict(row, external_id=90000001)])
        live.save(self.directory, after)
        self.assertEqual(live.restore(self.directory)['completed_total'], 92)
        self.assertEqual(after['inherited'], cp['inherited'])

    def test_unfinished_readback_resumes_without_reserving_or_replaying_hotels(self):
        cp = copy.deepcopy(self.cp)
        ids = list(range(90000000, 90000120))
        cp.update(rows=[{'external_id': i, 'status': 'source_error',
                         'reason': 'interrupted_result_unknown', 'candidates': []} for i in ids],
                  admissions=self.observed(ids)['pending'], completed_total=210,
                  previous_completed=180, previous_new_rows=90,
                  mappings_before=12872, coverage_before=self.observed(ids)['counts'])
        cp['previous_rows_sha256'] = gaps.digest(cp['rows'][:90])
        live.save(self.directory, cp)
        # Backward-compatible recovery of the artifact written before the marker existed.
        (self.directory / 'anex-observed-hotel-report.json').write_text(json.dumps({
            'completed_total': 210, 'new_completed': 30,
            'checkpoint_readback_verified': True, 'previous_evidence_unchanged': True}))
        (self.directory / 'anex-observed-hotel-mapping-import.json').write_text(json.dumps({
            'status': 'no_new_strong_candidates', 'inserted': 0}))
        observed = self.observed(ids + list(range(90000120, 90000170)))
        env = {'ANEX_CATALOG_ARTIFACT_DIR': str(self.directory), 'GITHUB_RUN_ID': '3', 'GITHUB_RUN_ATTEMPT': '1'}
        with patch.dict(os.environ, env), patch.object(live, 'snapshot', return_value=observed), \
                patch.object(gaps, 'ssh_batch') as api:
            for args in (['queue', '--prepare'], ['queue']):
                with patch.object(sys, 'argv', args):
                    live.main()
            with patch.object(sys, 'argv', ['queue', '--finalize']), \
                    patch.object(live, 'snapshot', side_effect=ValueError('remote_batch_exit_255')), \
                    self.assertRaises(ValueError):
                live.main()
            pending = live.restore(self.directory)
            self.assertTrue(pending['batch_needs_finalization'])
            self.assertEqual(pending['rows'], cp['rows'])
            # Another readback interruption must still never consume the next 50 IDs.
            with patch.dict(os.environ, {'GITHUB_RUN_ATTEMPT': '2'}):
                for args in (['queue', '--prepare'], ['queue'], ['queue', '--finalize']):
                    with patch.object(sys, 'argv', args):
                        live.main()
            api.assert_not_called()
        after = live.restore(self.directory)
        self.assertEqual(after['admissions'], cp['admissions'])
        self.assertEqual(after['rows'], cp['rows'])
        self.assertEqual(after['completed_total'], 210)
        self.assertEqual(after['in_flight'], [])
        self.assertFalse(after['batch_needs_finalization'])
        report = json.loads((self.directory / 'anex-observed-hotel-report.json').read_bytes())
        self.assertEqual((report['new_completed'], report['added_links'], report['remaining_new_ids']), (30, 0, 50))

    def test_unfinished_report_cannot_override_prior_evidence(self):
        cp = dict(self.cp, batch_needs_finalization=True, previous_new_rows=0,
                  previous_completed=90, previous_rows_sha256='changed')
        with self.assertRaisesRegex(ValueError, 'unverified unfinished batch'):
            live.needs_finalization(self.directory, cp)

    def test_unadmitted_rows_or_legacy_evidence_changes_fail_closed(self):
        for key, value in [('inherited', []), ('completed_total', 0), ('in_flight', [90000000]),
                           ('import_finalized_ids', [90000000])]:
            with self.assertRaises(ValueError):
                live.validate(dict(self.cp, **{key: value}), live.legacy(self.directory))

    def test_no_import_repetition_after_confirmed_finalize(self):
        cp = copy.deepcopy(self.cp)
        row = {'external_id': 90000000, 'status': 'strong_candidate', 'reason': 'name_country_coordinates'}
        cp.update(rows=[row], admissions=self.observed([90000000])['pending'], completed_total=91,
                  import_finalized_ids=[90000000])
        live.save(self.directory, cp)
        with patch.object(gaps, 'verified_delta', return_value={'rows': []}) as verify:
            self.assertEqual(live.approved_delta(self.directory / live.CHECKPOINT), {'rows': []})
        self.assertEqual(verify.call_args.args[1]['rows'], [])
        self.assertEqual(verify.call_args.args[-1], 'observed_initial_search:')

    def test_snapshot_rejects_truncation_and_duplicate_ids(self):
        for value in [dict(self.observed([1]), truncated=True), self.observed([1, 1])]:
            with patch.object(gaps, 'ssh_batch', return_value=value), self.assertRaises(ValueError):
                live.snapshot()


if __name__ == '__main__':
    unittest.main()
