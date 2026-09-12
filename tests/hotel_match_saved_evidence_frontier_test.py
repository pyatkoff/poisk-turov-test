import importlib.util, pathlib, unittest
P=pathlib.Path(__file__).parents[1]/'scripts'/'diagnostics'/'hotel_match_saved_evidence_frontier.py'
spec=importlib.util.spec_from_file_location('m',P); m=importlib.util.module_from_spec(spec); spec.loader.exec_module(m)
class T(unittest.TestCase):
 def test_product(self):
  self.assertTrue(m.PRODUCT_RE.search('Fortuna 3 AI')); self.assertTrue(m.PRODUCT_RE.search('Тур "Стамбул"'))
 def test_tokens(self): self.assertEqual(m.norm_tokens('The Rose Hotel & Spa'),['rose','and'])
 def test_current_unresolved(self):
  c={'country_ids':{'4':'turkey'},'anex_mappings':[{'anex_hotel_id':1,'enabled':1}],'anex_decisions':[],
     'anex_observations':[{'anex_hotel_id':1,'country_id':4,'hotel_name':'A','search_count':2},{'anex_hotel_id':2,'country_id':4,'hotel_name':'B','search_count':3}]}
  self.assertEqual([x['external_id'] for x in m.current_unresolved(c)],['2'])
 def test_duplicate_target_held(self):
  census={'country_ids':{'4':'turkey'},'anex_mappings':[],'anex_decisions':[],
   'anex_observations':[{'anex_hotel_id':10,'country_id':4,'hotel_name':'Malkoc Hotel','search_count':2},{'anex_hotel_id':11,'country_id':4,'hotel_name':'Malkoc Hotel','search_count':1}]}
  bulk={'tourvisor_anex_seeds':[{'anex_hotel_id':10,'search_count':2},{'anex_hotel_id':11,'search_count':1}]}
  tv={'providers':{'tourvisor':{'hotels':[{'id':'5','name':'MALKOC HOTEL LALELI','aliases':['malkoc laleli']}]}}}
  ta={'providers':{'anex':{'hotels':[{'id':'10','name':'Malkoc Hotel'},{'id':'11','name':'Malkoc Hotel'}]}}}
  o=m.build(census,bulk,tv,ta); self.assertEqual(o['counts']['saved_same_day_name_candidates'],2)
  self.assertTrue(all(x['status'].startswith('duplicate_') for x in o['saved_same_day_name_corroboration']))
if __name__=='__main__': unittest.main()
