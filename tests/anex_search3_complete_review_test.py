import copy
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts/diagnostics'))
import anex_search3_complete_review as review
import anex_search3_gap_queue as gaps
import anex_search3_observed_queue as live
import anex_search3_owner_decisions as owner


class CompleteReviewTests(unittest.TestCase):
    def setUp(self):
        self.row = {'external_id': 32832, 'status': 'review', 'reason': 'candidate_limit_reached',
                    'api': {'id': 32832, 'name': 'Example Hotel', 'country': 'Турция', 'latitude': 41, 'longitude': 29},
                    'xml': {'id': 32832, 'name': 'Example Hotel', 'alternate_name': ''},
                    'candidates': [{'id': 1}]}

    def item(self, count):
        return {'key': 32832, 'candidate_set_complete': count < 4097, 'fetch_limit': 4097,
                'query_scope': 'active_country_name_or_geobox',
                'candidates': [{'id': i + 1, 'name': 'Example Hotel' if i == 0 else 'Different Hotel',
                    'country_name': 'Турция', 'latitude': 41, 'longitude': 29} for i in range(count)]}

    def test_complete_large_set_retains_every_competitor_and_never_accepts(self):
        result = review.analyze(self.row, self.item(300))
        self.assertTrue(result['candidate_set_complete'])
        self.assertEqual(len(result['raw_candidates']), 300)
        self.assertEqual(len(result['ranked_candidates']), 300)
        self.assertFalse(result['automatic_acceptance'])
        self.assertEqual(result['status'], 'read_only_review')

    def test_sentinel_cannot_claim_complete_and_duplicate_ids_are_rejected(self):
        item = self.item(4097)
        item['candidate_set_complete'] = True
        with self.assertRaisesRegex(ValueError, 'proof invalid'):
            review.analyze(self.row, item)
        item = self.item(2)
        item['candidates'][1]['id'] = 1
        with self.assertRaisesRegex(ValueError, 'proof invalid'):
            review.analyze(self.row, item)
        item = self.item(2)
        del item['query_scope']
        with self.assertRaisesRegex(ValueError, 'proof invalid'):
            review.analyze(self.row, item)

    def test_completed_read_does_not_repeat_database_or_supplier_requests(self):
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            history = {i: (dict(copy.deepcopy(self.row), external_id=i,
                              api=dict(self.row['api'], id=i)), 'live_checkpoint') for i in review.IDS}
            digests = {i: gaps.digest(history[i][0]) for i in review.IDS}
            owner.save(directory / review.CHECKPOINT, {'state': 'completed',
                'source_digests': {str(i): d for i, d in digests.items()}, 'results': [],
                'results_sha256': gaps.digest([])})
            with patch.object(review, 'SOURCE_DIGESTS', digests), \
                    patch.object(live, 'restore', return_value={'in_flight': []}), \
                    patch.object(live, 'evidence_history', return_value=history), \
                    patch.object(live, 'snapshot') as snapshot, patch.object(owner, 'ssh_php') as sql:
                self.assertEqual(review.run(directory)['new_catalog_reads'], 0)
                snapshot.assert_not_called()
                sql.assert_not_called()
                data = json.loads((directory / review.CHECKPOINT).read_bytes())
                data['results_sha256'] = 'changed'
                owner.save(directory / review.CHECKPOINT, data)
                with self.assertRaisesRegex(ValueError, 'result changed'):
                    review.run(directory)


if __name__ == '__main__':
    unittest.main()
