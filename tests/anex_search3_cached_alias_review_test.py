import copy
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts' / 'diagnostics'))
import anex_search3_cached_alias_review as review


class CachedAliasReviewTests(unittest.TestCase):
    def setUp(self):
        self.row = {
            'external_id': 12,
            'api': {'id': 12, 'name': 'Grand Pamir Hotel', 'country': 'Turkey',
                    'latitude': 41.0118, 'longitude': 28.9542},
            'xml': {'id': 12, 'name': 'Grand Pamir Hotel', 'alternate_name': ''},
            'live_row_sha256': 'live', 'geo_row_sha256': 'geo', 'audit_row_sha256': 'audit',
        }
        self.candidates = [
            {'id': 70, 'name': 'GRAND PAMIR HOTEL LALELI', 'country_name': 'Turkey',
             'region_name': 'Istanbul', 'subregion_name': 'Laleli', 'category': '4',
             'latitude': 41.0118, 'longitude': 28.9542, 'address': ''},
            {'id': 71, 'name': 'OTHER HOTEL', 'country_name': 'Turkey',
             'region_name': 'Istanbul', 'subregion_name': 'Laleli', 'category': '4',
             'latitude': 41.0130, 'longitude': 28.9550, 'address': ''},
        ]
        self.source = {
            'anex_hotel_id': 12, 'candidate_set_complete': True, 'fetch_limit': 4097,
            'candidate_count': 2, 'raw_candidates': copy.deepcopy(self.candidates),
            'raw_candidates_sha256': 'raw', 'proposal_status': 'review',
        }

    def item(self, aliases):
        return {'key': 12, 'aliases': aliases, 'alias_set_complete': True,
                'alias_fetch_limit': 8193, 'alias_rows': len(aliases),
                'candidate_count': 2,
                'query_scope': 'pinned_complete_candidate_ids_aliases'}

    def test_generated_alias_enriches_pinned_candidates_without_canonical_reread(self):
        result = review.merge_aliases(self.row, self.source, self.item([{
            'hotel_id': 70, 'alias': 'Grand Pamir Hotel',
            'normalized_alias': 'grand pamir hotel', 'source': 'generated'}]))
        self.assertEqual(result['proposal_status'], 'strong_candidate')
        self.assertEqual(result['best']['id'], 70)
        self.assertEqual(result['best']['matched_name_source'], 'generated')
        self.assertIn('canonical_result_sha256', result)

    def test_unverified_competing_alias_blocks_proposal(self):
        result = review.merge_aliases(self.row, self.source, self.item([{
            'hotel_id': 70, 'alias': 'Grand Pamir Hotel',
            'normalized_alias': 'grand pamir hotel', 'source': 'unknown'}]))
        self.assertEqual(result['proposal_status'], 'review')
        self.assertEqual(result['proposal_reason'], 'unverified_alias_provenance')

    def test_alias_response_must_be_complete_and_bound_to_candidate_ids(self):
        item = self.item([])
        item['alias_set_complete'] = False
        with self.assertRaisesRegex(ValueError, 'incomplete'):
            review.merge_aliases(self.row, self.source, item)
        with self.assertRaisesRegex(ValueError, 'incomplete'):
            review.merge_aliases(self.row, self.source, self.item([{
                'hotel_id': 999, 'alias': 'Grand Pamir Hotel',
                'normalized_alias': 'grand pamir hotel', 'source': 'generated'}]))

    def test_query_reuses_only_pinned_candidate_ids(self):
        self.assertEqual(review.query_for(12, self.source), {
            'key': 12, 'candidate_ids': [70, 71]})


if __name__ == '__main__':
    unittest.main()
