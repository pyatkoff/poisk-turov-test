import copy
import hashlib
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

    def test_complete_evidence_only_replaces_the_truncation_guard(self):
        ns = {}
        exec(gaps.matching_source(), ns)
        result = review.analyze(self.row, self.item(300))
        ranked = result['ranked_candidates']
        decide = ns['geo_decision']
        self.assertEqual(decide(self.row['api'], ranked, 'same_record')[1], 'candidate_limit_reached')
        self.assertEqual(decide(self.row['api'], ranked, 'same_record', True)[0], 'strong_candidate')
        self.assertEqual(decide(self.row['api'], ranked, 'same_record', 'true')[0], 'review')
        bad = copy.deepcopy(ranked)
        bad[1]['score'] = bad[0]['score']
        self.assertEqual(decide(self.row['api'], bad, 'same_record', True)[1], 'competing_candidates')
        bad = copy.deepcopy(ranked)
        bad[0]['country_match'] = False
        self.assertEqual(decide(self.row['api'], bad, 'same_record', True)[1], 'country_conflict')

    def test_complete_import_reproduces_raw_evidence_and_baseline_identity(self):
        with tempfile.TemporaryDirectory() as temp:
            directory = Path(temp)
            history = {}
            originals, results = [], []
            for i in sorted(review.IDS):
                row = dict(copy.deepcopy(self.row), external_id=i, api=dict(self.row['api'], id=i),
                           xml=dict(self.row['xml'], id=i, town_id=None))
                history[i] = (row, 'live_checkpoint')
                item = self.item(300)
                item['key'] = i
                results.append(review.analyze(row, item))
                originals.append({'external_id': i, 'name': 'Example Hotel', 'alternate_name': '',
                                  'country': 'Турция', 'status': 'review'})
            sources = {}
            for key, name, value in [('catalog_sha256', 'anex-hotel-catalog-match.json', {'matches': originals}),
                                     ('geo_sha256', 'anex-hotel-geo-enrichment.json', {'rows': []})]:
                owner.save(directory / name, value)
                sources[key] = hashlib.sha256((directory / name).read_bytes()).hexdigest()
            path = directory / review.CHECKPOINT
            owner.save(path, {'state': 'completed', 'results': results, 'results_sha256': gaps.digest(results)})
            digests = {i: gaps.digest(history[i][0]) for i in review.IDS}
            with patch.object(review, 'SOURCE_DIGESTS', digests), \
                    patch.object(live, 'restore', return_value={'in_flight': []}), \
                    patch.object(live, 'evidence_history', return_value=history), \
                    patch.object(gaps, 'load_queue', return_value={'sources': sources}), \
                    patch.object(review, 'CHECKED_CHECKPOINT_SHA', hashlib.sha256(path.read_bytes()).hexdigest()):
                self.assertEqual(review.approved_delta(path)['counts']['strong'], 2)
                changed = json.loads(path.read_bytes())
                changed['results'][0]['best']['id'] = 900
                changed['results_sha256'] = gaps.digest(changed['results'])
                owner.save(path, changed)
                with self.assertRaisesRegex(ValueError, 'unchecked complete-review checkpoint'):
                    review.approved_delta(path)
                with patch.object(review, 'CHECKED_CHECKPOINT_SHA', hashlib.sha256(path.read_bytes()).hexdigest()):
                    with self.assertRaisesRegex(ValueError, 'not reproduced'):
                        review.approved_delta(path)
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
