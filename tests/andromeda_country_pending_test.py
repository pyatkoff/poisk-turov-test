#!/usr/bin/env python3
"""Validate exact request and durable receipts; transport is synthetic, SQL has a separate actual test."""
import json,os,sys,tempfile,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts/diagnostics'))
import andromeda_country_pending as pending
PAYLOAD=json.loads(Path(os.environ['ANDROMEDA_PENDING_REQUEST']).read_bytes())['request']
def outcome():
 return {'status':'accepted','operation_id':pending.OPERATION_ID,'request_sha256':pending.REQUEST_SHA,'updated':403,'readback_verified':True,'other_identities_unchanged':True,'supplier_calls':0,'rows':[dict(external_hotel_id=r['external_hotel_id'],local_hotel_id=r['local_hotel_id'],country_id=c['country_id'],decision_status='accepted',evidence_sha256='a'*64) for c in PAYLOAD['countries'].values() for r in c['rows']]}
class PendingTests(unittest.TestCase):
 def test_exact_whole_delta(self):
  self.assertEqual(pending.digest(PAYLOAD),pending.REQUEST_SHA)
  self.assertEqual(PAYLOAD['count'],403)
  self.assertEqual({k:len(v['rows']) for k,v in PAYLOAD['countries'].items()},{'uae':113,'thailand':123,'vietnam':113,'sri-lanka':28,'maldives':0,'cuba':26})
 def test_receipt_before_write_and_finalized_never_replayed(self):
  with tempfile.TemporaryDirectory() as d:
   path=Path(d)/'receipt.json';calls=[]
   def transport(value):
    self.assertEqual(json.loads(path.read_bytes())['state'],'reserved');calls.append(value);return outcome()
   pending.apply(PAYLOAD,path,transport);r=pending.apply(PAYLOAD,path,lambda _:self.fail('repeat'))
   self.assertEqual(r['new_writes'],0);self.assertEqual(len(calls),1)
 def test_unknown_never_replayed(self):
  with tempfile.TemporaryDirectory() as d:
   path=Path(d)/'receipt.json'
   def transport(_):raise TimeoutError()
   with self.assertRaises(TimeoutError):pending.apply(PAYLOAD,path,transport)
   with self.assertRaisesRegex(ValueError,'do_not_replay'):pending.apply(PAYLOAD,path,lambda _:self.fail('unknown replay'))
 def test_bad_result_preserved_not_success(self):
  with tempfile.TemporaryDirectory() as d:
   path=Path(d)/'receipt.json';value=outcome();value['rows'][0]['local_hotel_id']=99999
   with self.assertRaises(ValueError):pending.apply(PAYLOAD,path,lambda _:value)
   self.assertTrue(Path(str(path)+'.outcome.json').exists());self.assertEqual(json.loads(path.read_bytes())['state'],'reserved')
 def test_changed_request_cannot_contact_database(self):
  with tempfile.TemporaryDirectory() as d:
   changed=dict(PAYLOAD,count=404)
   with self.assertRaises(ValueError):pending.apply(changed,Path(d)/'r',lambda _:self.fail('tampered transport'))
 def test_duplicate_or_wrong_country_readback_rejected(self):
  value=outcome();value['rows'][1]=value['rows'][0]
  with self.assertRaises(ValueError):pending.validate_result(value,PAYLOAD)
  value=outcome();value['rows'][0]['country_id']=999
  with self.assertRaises(ValueError):pending.validate_result(value,PAYLOAD)
if __name__=='__main__':unittest.main(verbosity=2)
