#!/usr/bin/env python3
import importlib.util, pathlib, unittest
P=pathlib.Path(__file__).resolve().parents[1]/'scripts'/'diagnostics'/'hotel_match_post156_consolidated_gate.py'
P=P if P.exists() else pathlib.Path(__file__).with_name('hotel_match_post156_consolidated_gate.py')
s=importlib.util.spec_from_file_location('gate',P); g=importlib.util.module_from_spec(s); s.loader.exec_module(g)

def L(i,n): return {'id':i,'name':n,'region_id':10,'region_name':'Antalya','subregion_name':'T','country_class':'turkey','country_id':2,'category':5}
def A(e,i,n): return {'external_hotel_id':str(e),'decision_status':'accepted','local_hotel_id':i,'country_class':'turkey','sources':[{'name':n,'lName':n,'town':'T','townKey':1,'starKey':5}]}
def PEND(e,n,star=5): return {'external_hotel_id':str(e),'decision_status':'pending','local_hotel_id':None,'country_class':'turkey','sources':[{'name':n,'lName':n,'town':'T','townKey':1,'starKey':star}]}
def base():
    ls=[L(100,'SUN BEACH')]+[L(100+i,f'ANCHOR {i}') for i in range(1,6)]
    ar=[A('a0',100,'SUN BEACH')]+[A(f'a{i}',100+i,f'ANCHOR {i}') for i in range(1,6)]
    return {'operation_id':g.OP,'local':ls,'aliases':[],'andromeda':ar,'anex':[{'anex_hotel_id':'500','api_name':'SUN BEACH HOTEL','xml_name':None,'xml_alternate_name':None}],'anex_mappings':[{'anex_hotel_id':'500','catalog_hotel_id':100,'enabled':1}],'anex_observations':[]}

class T(unittest.TestCase):
    def test_generic(self): self.assertEqual(g.key('The Palm HOTEL Resort & SPA'),'palm')
    def test_former_alias(self): self.assertTrue({'aperion beach','sea paradise'} <= g.variants('APERION BEACH HOTEL (EX. SEA PARADISE)'))
    def test_qualifier_asymmetry(self): self.assertFalse(g.qok({'sunrise beach'},{'sunrise'}))
    def test_direction_conflict(self): self.assertFalse(g.qok({'royal north'},{'royal south'}))
    def test_same_qualifier(self): self.assertTrue(g.qok({'sunrise beach'},{'sunrise beach'}))
    def test_best_deterministic(self): self.assertEqual(g.best({'beta hotel','alpha hotel'},{'alpha','beta'}),g.best({'alpha hotel','beta hotel'},{'beta','alpha'}))
    def test_duplicate_target_fail_closed(self):
        c=base(); c['andromeda'] += [PEND('p1','SUN BEACH'),PEND('p2','SUN BEACH')]
        p={'rows':[],'selected_count':0,'candidate_sha256':'x','source_census_sha256':'x'}; pr={'andromeda_extension_pairs':[],'live_anex_bridge_count':0,'live_anex_bridge_pairs':[]}
        r=g.build(c,p,pr); self.assertEqual(r['result_counts']['new_cross_provider_high_current_recheck'],0); self.assertEqual(r['needs_extra_reason_counts_nonexclusive']['new_duplicate_target'],2)
    def test_prior_beach_asymmetry_demoted(self):
        c=base(); c['local'][0]['name']='SUN'; c['anex'][0]['api_name']='SUN'; c['andromeda'].append(PEND('old','SUN BEACH'))
        p={'rows':[{'external_hotel_id':'old','country':'turkey','proposed_local_id':100,'source_names':['SUN BEACH'],'matched_name_pair':['sun beach','sun'],'best_score':95.0,'margin':20.0,'geo_score':100.0,'star_difference':0,'target_name':'SUN'}],'selected_count':1,'candidate_sha256':'x','source_census_sha256':'x'}
        pr={'andromeda_extension_pairs':[],'live_anex_bridge_count':0,'live_anex_bridge_pairs':[]}; r=g.build(c,p,pr)
        self.assertEqual(r['result_counts']['prior_101_high_current_recheck'],0); self.assertEqual(r['needs_extra_reason_counts_nonexclusive']['prior_meaningful_qualifier_guard'],1)
    def test_star_is_guard(self):
        c=base(); c['andromeda'].append(PEND('star','SUN BEACH',2)); p={'rows':[],'selected_count':0,'candidate_sha256':'x','source_census_sha256':'x'}; pr={'andromeda_extension_pairs':[],'live_anex_bridge_count':0,'live_anex_bridge_pairs':[]}
        r=g.build(c,p,pr); self.assertEqual(r['result_counts']['new_cross_provider_high_current_recheck'],0)

if __name__=='__main__': unittest.main()
