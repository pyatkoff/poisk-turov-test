import importlib.util, os
from pathlib import Path
import unittest
P=Path(__file__).resolve().parents[1]/'scripts/diagnostics/hotel_match_retained_history_crosscheck.py'; S=importlib.util.spec_from_file_location('m',P); M=importlib.util.module_from_spec(S); S.loader.exec_module(M)
def c(local,cat,s='none'): return {'local_hotel_id':local,'hotel_name':'H','andromeda_catalog_id':str(cat),'candidate_names':['C'],'independent_tv_lane_count':1,'direct_anex_support_not_authority':s}
class T(unittest.TestCase):
 def test_conflict(self):
  idx=M.indexes({'rows':[{'tv_hotel_id':1540,'samo_hotel_id':'5257','classification':'accepted_other_target_conflict','current_samo_identity':[{'local_hotel_id':1541}]}]},{'new_candidate_plans':[]},{'holds':[]}); self.assertEqual(M.assess(c(1540,5257,'support_equal'),idx)['historical_status'],'hold')
 def test_category(self):
  idx=M.indexes({'rows':[]},{'new_candidate_plans':[{'tv_hotel_id':1299,'external_hotel_id':'2000123210','reasons':['category_discrepancy'],'source':{'star':'3'},'target':{'category':4}}]},{'holds':[]}); self.assertIn('historical_canonical_category_discrepancy',M.assess(c(1299,2000123210),idx)['historical_hold_reasons'])
 def test_coordinate(self):
  idx=M.indexes({'rows':[]},{'new_candidate_plans':[]},{'holds':[{'local_hotel_id':148230,'native_anex_hotel_id':44866,'reason':'coordinate_conflict_gt5km','distance_m':11523423}]}); self.assertIn('historical_direct_anex_coordinate_conflict_gt5km',M.assess(c(148230,2000113715,'support_equal'),idx)['historical_hold_reasons'])
 @unittest.skipUnless(os.environ.get('MATCH_HISTORY_DIR'),'fixtures')
 def test_real(self):
  r=Path(os.environ['MATCH_HISTORY_DIR']); out=M.analyse(M.load_report(r/'retained.json'),M.load_zip(r/'samo_direct27_current.zip','direct27'),M.load_zip(r/'samo34_canonical_current.zip','canonical'),M.load_zip(r/'anex_details_v1.zip','anex')); self.assertEqual(out['historical_hold_count'],3); self.assertEqual({x['local_hotel_id'] for x in out['rows']},{1299,1540,148230})
if __name__=='__main__': unittest.main()
