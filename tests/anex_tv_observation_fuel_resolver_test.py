from pathlib import Path
import importlib.util
import sys
import unittest

ROOT=Path(__file__).resolve().parents[1]
DIAG=ROOT/'scripts'/'diagnostics'
SCRIPT=DIAG/'anex_tv_observation_fuel_resolver.py'
TEXT=SCRIPT.read_text()

def load():
    sys.path.insert(0,str(DIAG))
    try:
        spec=importlib.util.spec_from_file_location('resolver',SCRIPT);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
    finally:
        sys.path.pop(0)

class FuelResolverTest(unittest.TestCase):
    def test_boundaries(self):
        self.assertIn('supplier_requests'=>0 if False else 'supplier_requests',TEXT)
        self.assertIn('db_writes',TEXT)
        self.assertNotIn('INSERT ',TEXT.upper())
        self.assertNotIn('UPDATE ',TEXT.upper())
        self.assertNotIn('DELETE ',TEXT.upper())
        self.assertIn('TV price - TV fuel == direct ANEX base',TEXT)
    def test_unique_and_ambiguous(self):
        m=load();base={'schema_version':1,'experiment_id':m.EXPERIMENT,'status':'completed','db_writes':0,'supplier_requests':0,'rows':[
            {'price':'140294','fuel_charge':'20846','base_price':'119448','search_id':1,'tour_id':'a','operator_id':1,'room_id':1,'room_type':'Standard','meal_id':7,'observed_at':'x'},
            {'price':'143212','fuel_charge':'20846','base_price':'122366','search_id':1,'tour_id':'b','operator_id':1,'room_id':1,'room_type':'Standard','meal_id':7,'observed_at':'x'},
            {'price':'162494','fuel_charge':'29184','base_price':'133310','search_id':2,'tour_id':'c','operator_id':1,'room_id':1,'room_type':'Standard','meal_id':7,'observed_at':'x'}]}
        r=m.analyze(base);self.assertTrue(r['matches']['119448']['resolved']);self.assertEqual(r['matches']['119448']['fuel_charge'],'20846');self.assertEqual(r['matches']['133310']['fuel_charge'],'29184')
        base['rows'].append({'price':'150000','fuel_charge':'30552','base_price':'119448','search_id':3,'tour_id':'d','operator_id':1,'room_id':1,'room_type':'Standard','meal_id':7,'observed_at':'x'})
        r=m.analyze(base);self.assertFalse(r['matches']['119448']['resolved']);self.assertIsNone(r['matches']['119448']['fuel_charge'])

if __name__=='__main__':unittest.main()
