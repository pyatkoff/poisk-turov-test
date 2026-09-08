import copy
from contextlib import ExitStack
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts/diagnostics'))
import anex_search3_saved_review as review
import anex_search3_complete_review as complete
import anex_search3_gap_queue as gaps
import anex_search3_observed_queue as live
import anex_search3_owner_decisions as owner


class SavedReviewTests(unittest.TestCase):
    def setUp(self):
        self.stack = ExitStack()
        self.addCleanup(self.stack.close)
        self.directory = Path(self.stack.enter_context(tempfile.TemporaryDirectory()))
        self.ids = sorted(review.SOURCE_DIGESTS)
        self.rows = {identifier: {
            'external_id': identifier, 'status': 'review', 'reason': 'candidate_limit_reached',
            'api': {'id': identifier, 'name': 'Example Orchid Hotel', 'country': 'Турция',
                    'latitude': 41, 'longitude': 29},
            'xml': {'id': identifier, 'name': 'Example Orchid Hotel', 'alternate_name': '', 'town_id': None},
            'candidates': [{'id': 1}],
        } for identifier in self.ids}
        originals = [{'external_id': identifier, 'status': 'review', 'name': 'Example Orchid Hotel',
                      'alternate_name': '', 'country': 'Турция'} for identifier in self.ids]
        owner.save(self.directory / 'anex-hotel-catalog-match.json', {'matches': originals})
        owner.save(self.directory / 'anex-hotel-geo-enrichment.json', {'rows': []})
        for name in [live.CHECKPOINT, gaps.CHECKPOINT, complete.CHECKPOINT, complete.ACCEPTANCE,
                     'anex-owner-hotel-decisions.json']:
            owner.save(self.directory / name, {'historical': name})
        owner.save(self.directory / 'anex-checkpoint-source.json', {'artifact_id': review.BOOTSTRAP_ARTIFACT})
        sources = {'catalog_sha256': review.file_sha(self.directory / 'anex-hotel-catalog-match.json'),
                   'geo_sha256': review.file_sha(self.directory / 'anex-hotel-geo-enrichment.json')}
        self.stack.enter_context(patch.object(review, 'SOURCE_DIGESTS', {i: gaps.digest(r) for i, r in self.rows.items()}))
        self.stack.enter_context(patch.object(gaps, 'load_queue', return_value={'sources': sources}))
        self.stack.enter_context(patch.object(live, 'restore', return_value={'in_flight': [], 'rows': []}))
        self.stack.enter_context(patch.object(live, 'evidence_history', return_value={i: (r, 'live_checkpoint') for i, r in self.rows.items()}))
        self.snapshot = self.stack.enter_context(patch.object(live, 'snapshot', return_value={
            'pending': [{'anex_hotel_id': i, 'country_id': 1} for i in self.ids], 'manual_review': []}))
        self.sql = self.stack.enter_context(patch.object(owner, 'ssh_php', side_effect=self.respond))

    def item(self, identifier, count=300):
        return {'key': identifier, 'candidate_set_complete': count < 4097, 'fetch_limit': 4097,
                'query_scope': 'active_country_name_or_geobox', 'candidates': [
                    {'id': i + 1, 'name': 'Example Orchid Hotel' if i == 0 else 'Different Hotel',
                     'country_name': 'Турция', 'latitude': 41, 'longitude': 29} for i in range(count)]}

    def respond(self, source, request, maximum_bytes):
        self.assertEqual(request['mode'], 'complete_review')
        self.assertLessEqual(len(request['queries']), 2)
        self.assertEqual(maximum_bytes, 4000000)
        return {'status': 'ok', 'items': [self.item(q['key']) for q in request['queries']]}

    def test_four_reads_are_two_bounded_batches_preserving_full_evidence(self):
        before = review.protected(self.directory)
        self.assertEqual(review.prepare(self.directory)['reserved_ids'], self.ids)
        result = review.run(self.directory)
        self.assertEqual(result['new_catalog_reads'], 4)
        self.assertEqual(self.sql.call_count, 2)
        self.assertEqual(result['completed_ids'], self.ids)
        self.assertEqual(result['inserted'], 0)
        self.assertEqual(result['supplier_requests'], 0)
        cp, _ = review.load(self.directory)
        for batch in cp['batches']:
            for row in batch['results']:
                self.assertEqual(len(row['raw_candidates']), 300)
                self.assertEqual(len(row['ranked_candidates']), 300)
                self.assertEqual(row['proposal_status'], 'strong_candidate')
                self.assertFalse(row['automatic_acceptance'])
        self.assertEqual(review.protected(self.directory), before)
        self.snapshot.reset_mock()
        self.sql.reset_mock()
        self.assertEqual(review.run(self.directory)['new_catalog_reads'], 0)
        self.snapshot.assert_not_called()
        self.sql.assert_not_called()

    def test_sentinel_and_forged_complete_proof_remain_blocked(self):
        item = self.item(self.ids[0], 4097)
        item['candidate_set_complete'] = True
        with self.assertRaisesRegex(ValueError, 'proof invalid'):
            review.analyze(self.rows[self.ids[0]], item)
        item['candidate_set_complete'] = False
        result = review.analyze(self.rows[self.ids[0]], item)
        self.assertEqual(result['proposal_status'], 'review')
        self.assertEqual(result['proposal_reason'], 'candidate_limit_reached')

    def test_result_mutation_rejected_even_with_recomputed_digest(self):
        review.prepare(self.directory)
        review.run(self.directory)
        cp, _ = review.load(self.directory)
        batch = cp['batches'][0]
        batch['results'][0]['best']['id'] = 9999
        batch['results_sha256'] = gaps.digest(batch['results'])
        owner.save(self.directory / review.CHECKPOINT, cp)
        with self.assertRaisesRegex(ValueError, 'scores changed'):
            review.load(self.directory)

    def test_unknown_batch_never_replayed_and_remaining_batch_continues(self):
        review.prepare(self.directory)
        self.sql.side_effect = ValueError('unconfirmed response')
        result = review.run(self.directory)
        self.assertEqual(result['unknown_ids'], self.ids[:2])
        self.assertEqual(self.sql.call_count, 1)
        self.sql.reset_mock()
        self.sql.side_effect = self.respond
        result = review.run(self.directory)
        self.assertEqual(result['completed_ids'], self.ids[2:])
        self.assertEqual(result['unknown_ids'], self.ids[:2])
        self.assertEqual(self.sql.call_count, 1)
        self.assertEqual([q['key'] for q in self.sql.call_args.args[1]['queries']], self.ids[2:])

    def test_in_flight_recovery_marks_unknown_without_reading(self):
        review.prepare(self.directory)
        cp, _ = review.load(self.directory)
        cp['batches'][0]['state'] = 'in_flight'
        review.save(self.directory, cp)
        self.snapshot.reset_mock()
        result = review.prepare(self.directory)
        self.assertEqual(result['recovered_unknown_ids'], self.ids[:2])
        self.sql.assert_not_called()
        self.snapshot.assert_not_called()

    def test_previous_job_durable_reservation_cannot_replay_unknown_reads(self):
        with patch.dict(os.environ, {'GITHUB_RUN_ID': '1', 'GITHUB_RUN_ATTEMPT': '1'}):
            review.prepare(self.directory)
        self.snapshot.reset_mock()
        with patch.dict(os.environ, {'GITHUB_RUN_ID': '1', 'GITHUB_RUN_ATTEMPT': '2'}):
            with self.assertRaisesRegex(ValueError, 'without replay'):
                review.run(self.directory)
            result = review.prepare(self.directory)
            self.assertEqual(result['recovered_unknown_ids'], self.ids)
            self.assertEqual(review.run(self.directory)['new_catalog_reads'], 0)
        self.sql.assert_not_called()
        self.snapshot.assert_not_called()

    def test_current_accepted_and_manual_ids_are_not_queried(self):
        review.prepare(self.directory)
        self.snapshot.return_value = {'pending': [{'anex_hotel_id': i, 'country_id': 1} for i in self.ids[2:]],
                                      'manual_review': [{'anex_hotel_id': self.ids[0]}]}
        result = review.run(self.directory)
        self.assertEqual(result['protected_ids'], self.ids[:2])
        self.assertEqual(result['new_catalog_reads'], 2)
        self.assertEqual(self.sql.call_count, 1)

    def test_missing_checkpoint_cannot_bootstrap_again_and_source_identity_is_pinned(self):
        owner.save(self.directory / 'anex-checkpoint-source.json', {'artifact_id': review.BOOTSTRAP_ARTIFACT + 1})
        with self.assertRaisesRegex(ValueError, 'refusing reset'):
            review.prepare(self.directory)
        owner.save(self.directory / 'anex-checkpoint-source.json', {'artifact_id': review.BOOTSTRAP_ARTIFACT})
        self.rows[self.ids[0]]['api']['id'] += 1
        with self.assertRaisesRegex(ValueError, 'identity changed'):
            review.prepare(self.directory)
        self.sql.assert_not_called()
        self.snapshot.assert_not_called()


if __name__ == '__main__':
    unittest.main()
