import sys, unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts/diagnostics'))
from andromeda_turkey_sync import match

class TurkeyMatch(unittest.TestCase):
    def data(self):
        return {'country_id':4,'supplier_country_id':6,'catalog_sha256':'a'*64,
            'catalog':{'params':{'TOWNFROMINC':1,'STATEINC':6},'payload':{'HOTELS':[
                {'id':10,'name':'Aperion Beach Hotel','stateKey':6},
                {'id':11,'name':'Other Palace','stateKey':6},
                {'id':12,'name':'Aperion Beach Hotel','stateKey':3}]}},
            'local':{'complete':True,'country_id':4,'hotels':[{'id':6319,'name':'APERION BEACH (ЕХ. SEA PARADISE)','country_id':4}],
                'aliases':[]}}
    def test_unique_and_country(self):
        rows=match(self.data())['rows']
        self.assertEqual([(r['decision_status'],r['local_hotel_id']) for r in rows],[('accepted',6319),('pending',None),('conflict',None)])
    def test_ambiguity(self):
        data=self.data();data['local']['hotels'].append({'id':7000,'name':'Aperion Beach','country_id':4})
        self.assertEqual(match(data)['rows'][0]['decision_status'],'pending')
    def test_cross_country(self):
        data=self.data();data['local']['hotels'][0]['country_id']=1
        with self.assertRaises(ValueError):match(data)
if __name__=='__main__':unittest.main()
