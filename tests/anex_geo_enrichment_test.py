#!/usr/bin/env python3
import unittest
from unittest.mock import patch
from anex_hotel_match_probe_test import probe, ROOT

exec((ROOT / 'scripts/diagnostics/anex_geo_enrichment.py').read_text(), probe.__dict__)

class GeoTest(unittest.TestCase):
    def setUp(self):
        probe.SENSITIVE_VALUES = ()
        self.api = {'id': 12, 'name': 'Blue Garden', 'state': 'Turkey', 'latitude': 36, 'longitude': 30, 'townKey': 3}
        self.match = {'external_id': 12, 'name': 'Blue Garden', 'alternate_name': '', 'country': 'Turkey', 'status': 'review'}
        self.candidate = {'id': 51, 'name': 'Blue Garden', 'country_name': 'Turkey', 'latitude': 36.0001, 'longitude': 30}
    def run_sample(self, candidates, api=None):
        with patch.object(probe, 'api_data', return_value=api or self.api), patch.object(probe, 'read_catalog', return_value={'status':'ok','items':[{'key':12,'candidates':candidates}]}):
            return probe.enrich_geo_sample({'ANEX_API_TOKEN':'test'}, [self.match], [{'inc':'12','town':'3'}])['rows'][0]
    def test_strong_evidence_without_applying_identity(self):
        row = self.run_sample([self.candidate])
        self.assertEqual(row['status'], 'strong_candidate')
        self.assertNotIn('catalog_hotel_id', row)
        self.assertEqual(self.match['status'], 'review')
    def test_nearby_duplicate_stays_review(self):
        row = self.run_sample([self.candidate, dict(self.candidate,id=52)])
        self.assertEqual(row['reason'], 'competing_candidates')
    def test_supplier_namespace_conflict(self):
        row = self.run_sample([self.candidate],dict(self.api,id=99))
        self.assertEqual(row['reason'], 'supplier_identity_unverified')
    def test_missing_coordinates_or_country_never_strong(self):
        for field in ('latitude','country_name'):
            row = self.run_sample([dict(self.candidate, **{field:None})])
            self.assertEqual(row['status'], 'review')
    def test_section_difference(self):
        row = self.run_sample([dict(self.candidate,name='Blue Beach')])
        self.assertEqual(row['reason'], 'hotel_section_difference')
    def test_request_budget(self):
        matches = [dict(self.match,external_id=i) for i in range(1,41)]
        with patch.object(probe,'api_data',side_effect=probe.StopProbe) as fetch:
            report = probe.enrich_geo_sample({'ANEX_API_TOKEN':'test'},matches,[])
        self.assertEqual(fetch.call_count,10)
        self.assertEqual(report['selected'],10)

if __name__ == '__main__':
    unittest.main()
