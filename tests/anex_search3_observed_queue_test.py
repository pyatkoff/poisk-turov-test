import copy
import csv
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

    def test_triage_recovers_legacy_evidence_and_orders_by_current_search_demand(self):
        legacy_path = self.directory / gaps.CHECKPOINT
        legacy_cp = json.loads(legacy_path.read_bytes())
        row = legacy_cp['rows'][0]
        identifier = row['external_id']
        row.update(status='review', reason='competing_candidates', api={'name': 'Saved supplier name'},
                   candidates=[{'id': 11, 'name': 'Candidate one', 'distance_m': 120, 'country_match': True},
                               {'id': 12, 'name': 'Candidate two', 'distance_m': None}])
        legacy_path.write_text(json.dumps(legacy_cp))
        cp = live.restore(self.directory)
        cp.update(rows=[{'external_id': 90000001, 'status': 'review', 'reason': 'insufficient_independent_evidence',
                         'candidates': []}], admissions=self.observed([90000001])['pending'], completed_total=91)
        live.save(self.directory, cp)
        observed = self.observed([identifier, 90000001])
        observed['pending'][0].update(hotel_name='Current observed name', search_count=3)
        observed['pending'][1]['search_count'] = 100
        before = (self.directory / live.CHECKPOINT).read_bytes()
        with patch.object(gaps, 'ssh_batch') as api:
            report = live.export(self.directory, cp, observed)
        api.assert_not_called()
        self.assertEqual((self.directory / live.CHECKPOINT).read_bytes(), before)
        self.assertEqual(live.restore(self.directory), cp)
        data = json.loads((self.directory / 'anex-observed-hotel-triage.json').read_bytes())
        self.assertEqual([r['anex_hotel_id'] for r in data['rows']], [90000001, identifier])
        saved = data['rows'][1]
        self.assertEqual(saved['evidence'], row)
        self.assertEqual(saved['evidence_row_sha256'], cp['inherited'][0]['row_sha256'])
        self.assertEqual(saved['observation']['hotel_name'], 'Current observed name')
        self.assertFalse(saved['automatic_acceptance'])
        self.assertEqual(report['triage']['candidate_rows'], 2)
        self.assertTrue(report['triage']['readback_verified'])
        with (self.directory / 'anex-observed-hotel-review.csv').open() as f:
            by_id = {int(r['anex_hotel_id']): r for r in csv.DictReader(f)}
        self.assertEqual(by_id[identifier]['hotel_name'], 'Current observed name')
        self.assertEqual(by_id[identifier]['search_count'], '3')
        with (self.directory / 'anex-observed-hotel-candidates.csv').open() as f:
            candidates = list(csv.DictReader(f))
        self.assertEqual([r['catalog_hotel_id'] for r in candidates], ['11', '12'])
        self.assertEqual(candidates[1]['distance_m'], '')

    def test_triage_excludes_mapped_manual_new_and_reserved_ids_and_does_not_replay_unknown(self):
        old_ids = [r['external_id'] for r in self.cp['inherited'][:3]]
        cp = copy.deepcopy(self.cp)
        cp.update(admissions=self.observed([90000001])['pending'], in_flight=[90000001])
        observed = self.observed([old_ids[0], 90000001, 90000002])
        observed['manual_review'] = self.observed([old_ids[1]])['pending']
        observed['pending'][0]['hotel_name'] = ' =HYPERLINK("unsafe")'
        with patch.object(gaps, 'ssh_batch') as api:
            summary = live.export_triage(self.directory, cp, observed)
        api.assert_not_called()
        data = json.loads((self.directory / 'anex-observed-hotel-triage.json').read_bytes())
        self.assertEqual([r['anex_hotel_id'] for r in data['rows']], [old_ids[0]])
        row = data['rows'][0]
        self.assertEqual(row['status'], 'source_error')
        self.assertEqual(row['next_action'], 'investigate_interrupted_result_without_replay')
        self.assertFalse(row['automatic_retry'])
        self.assertEqual(row['evidence']['candidates'], [])
        self.assertEqual(row['prior_fixed_queue_hints'], gaps.load_queue()['rows'][0]['candidates'])
        self.assertEqual(summary['unknown_results_not_replayed'], 1)
        with (self.directory / 'anex-observed-hotel-triage.csv').open() as f:
            self.assertTrue(next(csv.DictReader(f))['hotel_name'].startswith("' ="))

    def test_triage_refuses_changed_legacy_evidence(self):
        old = self.directory / gaps.CHECKPOINT
        value = json.loads(old.read_bytes())
        value['rows'][0]['reason'] = 'tampered'
        old.write_text(json.dumps(value))
        with self.assertRaises(ValueError):
            live.export_triage(self.directory, self.cp, self.observed([value['rows'][0]['external_id']]))

    def test_ceiling_audit_keeps_full_set_and_never_promotes_a_good_pair(self):
        best = {'id': 10, 'name': 'Example Resort', 'score': 1.05,
                'name_similarity': 1.0, 'distance_m': 10, 'country_match': True}
        candidates = [best] + [dict(best, id=20 + i, score=0.2, name_similarity=0.2,
                                    distance_m=900) for i in range(255)]
        evidence = {'status': 'review', 'reason': 'candidate_limit_reached', 'candidates': candidates,
                    'api': {'name': 'Example Resort', 'latitude': 28.0, 'longitude': 33.0}}
        item = {'anex_hotel_id': 1, 'reason': evidence['reason'], 'evidence': evidence,
                'evidence_row_sha256': gaps.digest(evidence), 'observation': {'country_name': 'Egypt'}}
        before = copy.deepcopy(item)
        with patch.object(gaps, 'ssh_batch') as api:
            audit = live.candidate_ceiling_audit([item])
        api.assert_not_called()
        row = audit['rows'][0]
        self.assertEqual(row['candidate_count'], 256)
        self.assertEqual(row['name_country_near_count'], 1)
        self.assertEqual(row['signals'], [])
        self.assertEqual(row['status'], 'review')
        self.assertFalse(row['candidate_set_complete'])
        self.assertFalse(row['automatic_acceptance'])
        self.assertEqual(item, before)
        # Missing coordinates and close alternatives remain distinct from no candidates.
        evidence['api']['latitude'] = None
        candidates[0]['distance_m'] = None
        candidates[1]['score'] = 1.02
        row = live.candidate_ceiling_audit([item])['rows'][0]
        self.assertEqual(set(row['signals']), {'supplier_coordinates_missing',
                                             'best_not_verified_within_200m', 'close_scoring_alternatives'})


if __name__ == '__main__':
    unittest.main()
