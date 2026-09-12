#!/usr/bin/env python3
import importlib.util, pathlib, unittest
P=pathlib.Path(__file__).resolve().parents[1]/'scripts'/'diagnostics'/'hotel_match_identity_graph_mass_review.py'
s=importlib.util.spec_from_file_location('m',P); m=importlib.util.module_from_spec(s); s.loader.exec_module(m)

def review(rows,gaps):
    return {'operation_id':'x','counts':{},'coverage':{},'third_link_gaps':gaps,
            'buckets':{'needs_extra_evidence':rows}}

def gap(i,name,typ='anex_tv_only',country=4,region='R',sub='S'):
    return {'local_hotel_id':i,'name':name,'type':typ,'country_id':country,'region':region,'subregion':sub}

def row(e,name,provider='andromeda',reason='cross_provider_or_supplier_evidence_needed',country=4,places=None,candidates=None):
    return {'external_id':e,'source_names':[name],'provider':provider,'reason':reason,'country_id':country,
            'source_places':places or [],'candidate_ids':candidates or [],'search_count':7}

class T(unittest.TestCase):
    def test_generic_and_former_alias(self):
        self.assertIn('aperion beach',m.variants('APERION BEACH HOTEL (EX. SEA PARADISE)'))
        self.assertIn('sea paradise',m.variants('APERION BEACH HOTEL (EX. SEA PARADISE)'))
    def test_exact_two_token_bridge(self):
        r=m.build(review([row('a','Sun Beach Hotel')],[gap(10,'SUN BEACH RESORT')]),source_sha256='x')
        self.assertEqual(r['result_counts']['prepared_current_recheck'],1)
    def test_one_token_held(self):
        r=m.build(review([row('a','Prima Hotel')],[gap(10,'PRIMA')]),source_sha256='x')
        self.assertEqual(r['result_counts']['held_for_independent_evidence'],1)
        self.assertIn('low_information_identity',r['held_rows'][0]['hold_reasons'])
    def test_one_token_geo_candidate_allowed(self):
        rr=row('a','Prima Hotel',places=['S'],candidates=[10])
        r=m.build(review([rr],[gap(10,'PRIMA')]),source_sha256='x')
        self.assertEqual(r['result_counts']['prepared_current_recheck'],1)
    def test_provider_direction(self):
        r=m.build(review([row('a','Sun Beach',provider='andromeda')],[gap(10,'SUN BEACH',typ='andromeda_tv_only')]),source_sha256='x')
        self.assertEqual(r['result_counts']['unique_exact_graph_bridges'],0)
    def test_same_provider_duplicate_target_fails_closed(self):
        rows=[row('a','Eden Beach'),row('b','Eden Beach')]
        r=m.build(review(rows,[gap(10,'EDEN BEACH')]),source_sha256='x')
        self.assertEqual(r['result_counts']['prepared_current_recheck'],0)
        self.assertEqual(r['held_reason_counts']['same_provider_duplicate_target'],2)
    def test_anex_reverse_direction(self):
        rr=row('x','Blue Bay Hotel',provider='anex',reason='tourvisor_anex_operator_link_hotelcode_needed')
        r=m.build(review([rr],[gap(22,'BLUE BAY',typ='andromeda_tv_only')]),source_sha256='x')
        self.assertEqual(r['result_counts']['prepared_anex'],1)
    def test_qualifier_not_erased(self):
        r=m.build(review([row('a','Coral Beach')],[gap(10,'CORAL GARDEN')]),source_sha256='x')
        self.assertEqual(r['result_counts']['unique_exact_graph_bridges'],0)
if __name__=='__main__': unittest.main()
