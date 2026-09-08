import copy
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts' / 'diagnostics'))
import anex_search3_cached_evidence_audit as audit


class CachedEvidenceAuditTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.ns = {}
        exec(audit.gaps.matching_source(), cls.ns)

    def setUp(self):
        self.original = {'name': 'Hotel Example', 'alternate_name': '', 'town_id': 8, 'country': 'Turkey'}
        self.xml = dict(id=12, name='Hotel Example', alternate_name='', town_id=8)
        self.api = dict(self.xml, country='Turkey', latitude=36.5, longitude=30.5, address='Example 1')
        self.cached = {'external_id': 12, 'xml': self.xml, 'api': self.api, 'candidate_limit': 256,
                       'checked_at': '2026-09-08T01:00:00Z', 'candidates': [
                           {'id': 70, 'name': 'Hotel Example', 'country': 'Turkey',
                            'latitude': 36.5, 'longitude': 30.5, 'score': -100,
                            'name_similarity': 0, 'distance_m': 999999}]}
        self.current = {'status': 'source_error', 'reason': 'interrupted_result_unknown'}

    def inspect(self, cached=None, observation=None):
        return audit.inspect(12, observation or {'country_name': 'Турция'}, self.current,
                             self.cached if cached is None else cached, self.original, self.ns)

    def test_unknown_row_is_preserved_and_saved_metrics_are_recomputed(self):
        before = copy.deepcopy((self.current, self.cached))
        result = self.inspect()
        self.assertTrue(result['cached_api_usable'])
        self.assertEqual(result['reproduced_historical_class'], 'strong_candidate')
        self.assertEqual(result['cached_best']['name_similarity'], 1)
        self.assertEqual(result['current_reason'], 'interrupted_result_unknown')
        self.assertEqual(before, (self.current, self.cached))
        self.assertEqual(result['supplier_requests'], 0)

    def test_id_and_country_mismatch_do_not_reuse_details(self):
        bad = copy.deepcopy(self.cached)
        bad['api']['id'] = 13
        self.assertFalse(self.inspect(bad)['cached_api_usable'])
        self.assertFalse(self.inspect(observation={'country_name': 'Египет'})['cached_api_usable'])

    def test_legacy_short_candidate_page_does_not_prove_uniqueness(self):
        self.cached['candidate_limit'] = 1
        result = self.inspect()
        self.assertEqual(result['reproduced_historical_class'], 'review')
        self.assertEqual(result['reproduced_historical_reason'], 'historical_candidate_limit_reached')

    def test_competitor_and_section_gates_remain(self):
        self.cached['candidates'].append(dict(self.cached['candidates'][0], id=71))
        self.assertEqual(self.inspect()['reproduced_historical_reason'], 'competing_candidates')
        self.cached['candidates'] = [dict(self.cached['candidates'][0], name='Hotel Example Beach')]
        self.assertEqual(self.inspect()['reproduced_historical_reason'], 'hotel_section_difference')

    def test_no_saved_details_does_not_infer_a_negative_match(self):
        self.assertEqual(self.inspect({})['cache_reason'], 'no_geo_row')
        self.assertEqual(self.inspect({'external_id': 12})['cache_reason'], 'no_saved_api_details')

    def test_geo_xml_town_is_preserved_when_catalog_projection_omits_it(self):
        del self.original['town_id']
        result = self.inspect()
        self.assertTrue(result['cached_api_usable'])
        self.assertEqual(result['cached_xml']['town_id'], 8)
        self.cached['xml']['town_id'] = 9
        self.assertFalse(self.inspect()['cached_api_usable'])


if __name__ == '__main__':
    unittest.main()
