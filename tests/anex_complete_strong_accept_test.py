#!/usr/bin/env python3
import json, os, sys, tempfile, unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts/diagnostics'))
import anex_complete_strong_accept as batch
class CompleteStrongTests(unittest.TestCase):
    @unittest.skipUnless(os.environ.get('ANEX_COMPLETE187_ARTIFACT'),'retained complete187 artifact not mounted')
    def test_exact_live_read_artifact_becomes_106_rows(self):
        payload=batch.request(Path(os.environ['ANEX_COMPLETE187_ARTIFACT']))
        self.assertEqual(batch.digest(payload),batch.REQUEST_SHA);self.assertEqual(len(payload['rows']),106);self.assertEqual(len({r['catalog_hotel_id'] for r in payload['rows']}),91)
        countries={}
        for r in payload['rows']:countries[r['country_id']]=countries.get(r['country_id'],0)+1
        self.assertEqual(countries,{2:1,4:97,9:3,16:5});self.assertTrue(all(r['query']['key']==r['anex_hotel_id'] for r in payload['rows']))
    def test_receipt_is_reserved_before_transport_and_never_replayed_unknown(self):
        payload={'operation_id':batch.OPERATION_ID};original=batch.request;batch.request=lambda _:payload
        try:
            with tempfile.TemporaryDirectory() as t:
                receipt=Path(t)/'receipt.json';calls=[]
                def transport(value):self.assertTrue(receipt.exists());calls.append(value);raise TimeoutError()
                with self.assertRaises(TimeoutError):batch.apply(Path('unused'),receipt,transport)
                self.assertEqual(json.loads(receipt.read_text())['state'],'reserved')
                with self.assertRaises(ValueError):batch.apply(Path('unused'),receipt,lambda _:self.fail('must not replay'))
                self.assertEqual(len(calls),1)
        finally:batch.request=original
    def test_validate_result_rejects_partial_or_wrong_pairs(self):
        rows=[{'anex_hotel_id':i+1,'catalog_hotel_id':1000+i} for i in range(batch.COUNT)];payload={'rows':rows}
        good={'status':'imported','operation_id':batch.OPERATION_ID,'request_sha256':batch.digest(payload),'input_count':106,'inserted':106,'updated':0,'readback_verified':True,'previous_rows_unchanged':True,'supplier_calls':0,'rows':[{'anex_hotel_id':r['anex_hotel_id'],'catalog_hotel_id':r['catalog_hotel_id'],'match_class':'strong_candidate'} for r in rows]}
        batch.validate_result(good,payload)
        for field,value in [('inserted',105),('readback_verified',False),('previous_rows_unchanged',False),('supplier_calls',1)]:
            bad=dict(good);bad[field]=value
            with self.subTest(field=field),self.assertRaises(ValueError):batch.validate_result(bad,payload)
        bad=json.loads(json.dumps(good));bad['rows'][0]['catalog_hotel_id']=999999
        with self.assertRaises(ValueError):batch.validate_result(bad,payload)
if __name__=='__main__':unittest.main(verbosity=2)
