import sys
from pathlib import Path
import unittest
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts/diagnostics'))
from andromeda_catalog_sync import names,match

class CatalogTest(unittest.TestCase):
    def test_former_names_and_formatting(self):
        self.assertIn('aperion beach',names('APERION BEACH HOTEL (EX. SEA PARADISE)'))
        self.assertIn('sea paradise',names('APERION BEACH HOTEL (EX. SEA PARADISE)'))
        self.assertEqual(names('Lemon & Soul Hotel'),names('Lemon and Soul'))

    def test_cyrillic_former_name_marker(self):
        current='VERGINIA SHARM RESORT & AQUA PARK'
        for marker in ['EX.', 'ЕХ.', 'EХ.', 'ЕX.']:
            self.assertIn('verginia sharm aqua park',names(current+' ('+marker+' VERGINIA SHARM, SOL VERGINIA)'))

    def test_distinctive_qualifiers_preserved(self):
        self.assertNotEqual(names('Empire Beach Hotel'),names('Empire Hotel'))
        self.assertNotEqual(names('Sharm Holiday Rentals'),names('Sharm Holiday'))
        self.assertNotEqual(names('Sea Sun'),names('Sun Sea'))

    def test_catalog_conflict_and_ambiguous_target(self):
        sources=[{'id':i+1,'name':'Unique '+str(i),'stateKey':3} for i in range(905)]
        sources[0]={'id':2000073714,'name':'Empire Beach','stateKey':3}
        sources[1]={'id':2,'name':'Wrong Country','stateKey':4}
        sources[2]['name']='Duplicate'
        local={'complete':True,'country_id':1,'aliases':[], 'hotels':[
            {'id':1,'name':'Empire Beach'},{'id':2,'name':'Wrong Country'},
            {'id':3,'name':'Duplicate'},{'id':4,'name':'Duplicate'},{'id':5,'name':'Unique 3'}]}
        result=match({'params':{'TOWNFROMINC':1,'STATEINC':3},'payload':{'HOTELS':sources}},local,{'rows':[]})
        self.assertEqual([r['decision_status'] for r in result['rows'][:4]],['conflict','conflict','pending','accepted'])
        self.assertEqual(result['rows'][3]['local_hotel_id'],5)

if __name__=='__main__':unittest.main()
