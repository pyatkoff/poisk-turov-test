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
        self.assertEqual(fetch.call_count,30)
        self.assertEqual(report['selected'],30)
        self.assertEqual(report['remaining'],10)

class ResumeTest(unittest.TestCase):
    def test_skips_prior_and_rechecks_changed_record(self):
        matches = [dict(external_id=i, name='Hotel', alternate_name='', country='Turkey', town='Kas', status='review') for i in range(1, 4)]
        completed = {str(r['external_id']): probe.geo_fingerprint(r) for r in matches[:2]}
        matches[0]['town'] = 'Kemer'
        with patch.object(probe,'api_data',side_effect=probe.StopProbe) as fetch:
            batch = probe.enrich_geo_sample({'ANEX_API_TOKEN':'test','geo_completed':completed},matches,[])
        self.assertEqual([r['external_id'] for r in batch['rows']], [1,3])
        self.assertEqual(batch['remaining'],0)
    def test_merge_retains_previous_and_replaces_rechecked(self):
        previous = {'rows':[{'external_id':1,'status':'review'},{'external_id':2,'status':'review'}]}
        batch = {'rows':[{'external_id':1,'status':'strong_candidate'}],'counts':{'strong_candidate':1},'remaining':0}
        merged = probe.merge_geo_checkpoint(previous,batch)
        self.assertEqual(merged['processed_total'],2)
        self.assertEqual(merged['counts']['strong_candidate'],1)
        self.assertEqual(merged['counts']['review'],1)
    def test_checkpoint_migration_and_corruption(self):
        import tempfile,json
        from pathlib import Path
        with tempfile.TemporaryDirectory() as temp:
            p = Path(temp)
            (p/'anex-hotel-geo-enrichment.json').write_text(json.dumps({'schema_version':1,'rows':[{'external_id':1,'status':'review'}]}))
            (p/'anex-hotel-catalog-match.json').write_text(json.dumps({'matches':[{'external_id':1,'name':'Hotel'}]}))
            loaded = probe.load_geo_checkpoint(temp)
            self.assertEqual(loaded['rows'][0]['fingerprint'],probe.geo_fingerprint({'external_id':1,'name':'Hotel'}))
            (p/'anex-hotel-geo-enrichment.json').write_text('{}')
            with self.assertRaises(ValueError): probe.load_geo_checkpoint(temp)
    def test_deadline_preserves_remaining(self):
        match = {'external_id':1,'status':'review'}
        with patch.object(probe.time,'monotonic',side_effect=[0,241]), patch.object(probe,'api_data') as fetch:
            batch = probe.enrich_geo_sample({'ANEX_API_TOKEN':'test'},[match],[])
        fetch.assert_not_called()
        self.assertEqual(batch['remaining'],1)

class CandidateExpansionTest(unittest.TestCase):
    def test_only_old_truncated_rows_are_requeued(self):
        rows = [{'external_id':1,'fingerprint':'a','reason':'candidate_limit_reached'},
                {'external_id':2,'fingerprint':'b','reason':'candidate_limit_reached','candidate_limit':256},
                {'external_id':3,'fingerprint':'c','reason':'name_country_coordinates'}]
        self.assertEqual(probe.geo_completed_map({'rows':rows}),{'2':'b','3':'c'})
    def test_expanded_full_page_still_blocks_confirmation(self):
        best = {'name':'Blue','name_similarity':1,'country_match':True,'distance_m':10,'score':1}
        other = dict(best,name='Other',score=0.1)
        self.assertEqual(probe.geo_decision({'name':'Blue'},[best]+[other]*255,'same_record')[1], 'candidate_limit_reached')
        self.assertEqual(probe.geo_decision({'name':'Blue'},[best]+[other]*64,'same_record')[0], 'strong_candidate')

class MultiBatchTest(unittest.TestCase):
    def test_moves_through_batches_without_requery(self):
        matches = [dict(external_id=i, name='Blue Hotel', alternate_name='', country='Turkey', town='Kas', status='review') for i in range(1, 66)]
        tokens = {'ANEX_API_TOKEN':'test', 'geo_completed':{'1':probe.geo_fingerprint(matches[0])}}
        # Identity conflict is a completed review, without local DB reads.
        with patch.object(probe,'api_data',return_value={'id':999,'name':'Other'}) as fetch:
            run = probe.enrich_geo_run(tokens,matches,[])
        ids = [r['external_id'] for r in run['rows']]
        self.assertEqual(ids,list(range(2,66)))
        self.assertEqual(fetch.call_count,64)
        self.assertEqual(run['batches'],3)
        self.assertEqual(run['stop_reason'],'queue_complete')
        self.assertEqual(len(tokens['geo_completed']),1)
    def test_service_outage_stops_after_one_batch(self):
        matches = [dict(external_id=i, name='Blue', alternate_name='', country='Turkey', status='review') for i in range(1,101)]
        with patch.object(probe,'api_data',side_effect=probe.StopProbe) as fetch:
            run = probe.enrich_geo_run({'ANEX_API_TOKEN':'test'},matches,[])
        self.assertEqual(fetch.call_count,30)
        self.assertEqual(run['remaining'],70)
        self.assertEqual(run['stop_reason'],'batch_without_details')
    def test_run_cap(self):
        matches = [dict(external_id=i, name='Blue', alternate_name='', country='Turkey', status='review') for i in range(1,351)]
        with patch.object(probe,'api_data',return_value={'id':999,'name':'Other'}) as fetch:
            run = probe.enrich_geo_run({'ANEX_API_TOKEN':'test'},matches,[])
        self.assertEqual(fetch.call_count,300)
        self.assertEqual(run['remaining'],50)
        self.assertEqual(run['stop_reason'],'run_limit')

if __name__ == '__main__':
    unittest.main()
