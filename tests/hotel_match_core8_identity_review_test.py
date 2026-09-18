import sys,unittest,copy
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'/'diagnostics'))
from hotel_match_core8_identity_review import Review,variants,norm,country,point

def data():
 h={'id':10,'name':'Unique Blossom Hotel','country_id':4,'country_name':'Турция','country_class':'turkey','region_name':'Анталья','subregion_name':'Лара','category':5,'latitude':36.85,'longitude':30.80,'is_active':1}
 d={'status':'completed','operation_id':'fixture','generated_at_utc':'fixture','database_writes':0,'country_ids':{'4':'turkey','1':'egypt'},'local':[h],'aliases':[], 'anex':[], 'anex_mappings':[], 'anex_decisions':[], 'anex_exclusions':[], 'anex_observations':[], 'andromeda':[]}
 b={'matches':[{'external_id':1,'name':'Unique Blossom Resort','alternate_name':'','country':'Турция','town':'Анталья'}]}
 return d,b

class Tests(unittest.TestCase):
 def test_generic_words_but_not_qualifiers(self):
  self.assertEqual(norm('Unique Blossom Hotel & Spa'),norm('Unique Blossom Resort'))
  self.assertNotEqual(norm('Unique Blossom Beach'),norm('Unique Blossom Garden'))
  self.assertNotEqual(norm('Unique Blossom North'),norm('Unique Blossom South'))
 def test_former_alias(self):
  self.assertIn(norm('Unique Blossom'),variants('New Name (EX. Unique Blossom Hotel)'))
  self.assertIn(norm('Unique Blossom'),variants('New Name (ex: Unique Blossom Resort)'))
 def test_country_exact(self):
  self.assertEqual(country('Турция'),'turkey');self.assertIsNone(country('not egypt'));self.assertIsNone(country('Абхазия'))
 def test_coordinates(self):
  self.assertIsNone(point({'latitude':99,'longitude':1}));self.assertIsNone(point({'latitude':0,'longitude':0}))
 def test_native_unique_geo(self):
  d,b=data();r=Review(d,b).run()['rows'][0];self.assertEqual(r['status'],'prepared_for_current_revalidation');self.assertEqual(r['proposed_local_id'],10)
 def test_existing_and_manual_and_exclusion(self):
  for table in ['anex_mappings','anex_decisions','anex_exclusions']:
   d,b=data();d[table]=[{'anex_hotel_id':1,'catalog_hotel_id':10,'enabled':1}]
   self.assertEqual(Review(d,b).run()['queue_count'],0)
 def test_observed_absent_from_staging_is_included(self):
  d,b=data();d['anex_observations']=[{'anex_hotel_id':1,'hotel_name':'Unique Blossom Hotel','country_id':4,'search_count':25}]
  r=Review(d,b).run();self.assertEqual(r['live_anex_absent_from_staging'],1);self.assertEqual(r['rows'][0]['source']['search_count'],25)
 def test_country_conflict_rejected(self):
  d,b=data();d['anex']=[{'anex_hotel_id':1,'api_country':'Египет','api_name':'Unique Blossom'}]
  self.assertEqual(Review(d,b).run()['queue_count'],0)
 def test_coordinate_conflict_blocks_exact_geo(self):
  d,b=data();d['anex']=[{'anex_hotel_id':1,'api_country':'Турция','api_name':'Unique Blossom','latitude':38,'longitude':30.8}]
  self.assertEqual(Review(d,b).run()['rows'][0]['status'],'coordinate_conflict')
 def test_duplicate_local_identity_is_ambiguous(self):
  d,b=data();h=copy.deepcopy(d['local'][0]);h['id']=11;d['local'].append(h)
  self.assertNotEqual(Review(d,b).run()['rows'][0]['status'],'prepared_for_current_revalidation')
 def test_cross_provider_alias_cannot_be_sole_native_proof(self):
  d,b=data();d['local'][0]['name']='Entirely Different Main Building'
  d['andromeda']=[{'supplier_namespace':'andromeda_catalog','external_hotel_id':'20','local_hotel_id':10,'decision_status':'accepted','sources':[{'id':20,'name':'Unique Blossom','state':'Турция','town':'Анталья','star':'5'}],'evidence_sha256':'same','actual_evidence_sha256':'same','catalog_sha256':'test'}]
  self.assertNotEqual(Review(d,b).run()['rows'][0]['status'],'prepared_for_current_revalidation')
 def test_score_only_is_not_acceptance(self):
  d,b=data();b['matches'][0]['town']='Unknown'
  self.assertNotEqual(Review(d,b).run()['rows'][0]['status'],'prepared_for_current_revalidation')
 def test_star_key_is_not_category(self):
  d,b=data();d['andromeda']=[{'supplier_namespace':'andromeda_catalog','external_hotel_id':'20','local_hotel_id':None,'decision_status':'pending','sources':[{'id':20,'name':'Unique Blossom','state':'Турция','town':'Анталья','star':'5','starKey':77}],'evidence_sha256':'same','actual_evidence_sha256':'same','catalog_sha256':'test'}]
  row=[r for r in Review(d,b).run()['rows'] if r['source']['provider']=='andromeda'][0]
  self.assertFalse(row['candidates'][0]['category_discrepancy']);self.assertEqual(row['source']['category_key_not_rating'],77)
if __name__=='__main__':unittest.main()
