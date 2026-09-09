import copy
import hashlib
import importlib.util
import json
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('pack', Path(__file__).resolve().parents[1] / 'scripts/diagnostics/anex-review-dossier-pack.py')
p = importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)

def fixture():
    evidence = {'external_id': 30, 'status': 'source_error', 'reason': 'interrupted_result_unknown', 'candidates': []}
    row = {'anex_hotel_id': 30, 'status': 'source_error', 'reason': 'interrupted_result_unknown',
           'automatic_acceptance': False, 'automatic_retry': False, 'evidence_origin': 'live_checkpoint',
           'observation': {'anex_hotel_id':30,'search_count':4,'hotel_name':'Длинное название'},
           'evidence':evidence,'evidence_row_sha256':p.digest(p.canonical(evidence).encode()),
           'prior_fixed_queue_hints':[{'id':101,'name':'Hint is not verified'}]}
    return {'schema_version':1,'scope':'preview','kind':'observed_review_dossiers','source_sha':'a'*40,
            'checkpoint_sha256':'b'*64,'summary':{'count':1},'rows':[row]}

def packed(data=None):
    raw=p.canonical(data or fixture()).encode()
    return p.pack(raw,p.digest(raw),10093538360)

class Packing(unittest.TestCase):
    def test_saved_row_is_lossless(self):
        data=fixture(); before=copy.deepcopy(data); result=packed(data)
        self.assertEqual(json.loads(result['rows'][0]['row_json']),data['rows'][0])
        self.assertEqual(data,before)
        self.assertEqual(json.loads(result['rows'][0]['evidence_json'])['candidates'],[])
    def test_rejects_tamper(self):
        data=fixture();data['rows'][0]['evidence']['reason']='new'
        with self.assertRaisesRegex(ValueError,'evidence_digest'): packed(data)
    def test_rejects_duplicate_id(self):
        data=fixture();data['rows']*=2;data['summary']['count']=2
        with self.assertRaisesRegex(ValueError,'row_contract'):packed(data)
    def test_never_enables_acceptance_or_retry(self):
        for key in ['automatic_acceptance','automatic_retry']:
            data=fixture();data['rows'][0][key]=True
            with self.assertRaises(ValueError):packed(data)
    def test_rejects_id_mismatch(self):
        data=fixture();data['rows'][0]['observation']['anex_hotel_id']=31
        with self.assertRaises(ValueError):packed(data)
    def test_source_digest_and_bound(self):
        with self.assertRaises(ValueError):p.pack(b'{}','a'*64,1)
        with self.assertRaises(ValueError):p.pack(b'x'*(p.MAX_BYTES+1),'a'*64,1)
    def test_rejects_duplicate_json_keys(self):
        raw=b'{"schema_version":1,"schema_version":1}'
        with self.assertRaisesRegex(ValueError,'duplicate_json_key'):p.pack(raw,p.digest(raw),1)
    def test_invalid_count_and_origin(self):
        data=fixture();data['summary']['count']=2
        with self.assertRaises(ValueError):packed(data)
        data=fixture();data['rows'][0]['evidence_origin']='guessed'
        with self.assertRaises(ValueError):packed(data)

if __name__=='__main__':unittest.main()
