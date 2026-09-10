#!/usr/bin/env python3
"""Receipt and boundary tests without supplier or application access."""
import copy
import json
from pathlib import Path
import sys
import tempfile
import unittest
sys.path.insert(0, str(Path(__file__).resolve().parents[1]/'scripts/diagnostics'))
import andromeda_country_expansion as expansion


def capture(country):
    return {'status':'captured_not_applied','operation_id':expansion.OPERATION_ID,'country':country,
            'country_id':77,'counts':{'accepted':1,'pending':1,'conflict':0},'source_hotels':2,
            'pairs':[{'external_hotel_id':'123','local_hotel_id':456}],
            'capture_sha256':'a'*64,'plan_sha256':'b'*64,'supplier_calls':3}


def outcome(country):
    return {'status':'imported','operation_id':expansion.OPERATION_ID,'country':country,'country_name':country,
            'country_id':77,'capture_sha256':'a'*64,'plan_sha256':'b'*64,'supplier_calls':0,
            'readback_verified':True,'previous_identities_unchanged':True,'inserted':2,
            'new_counts':{'accepted':1,'pending':1,'conflict':0},'current_andromeda_counts':{'accepted':1,'pending':1},
            'live_coverage':{'all_three':0},'rows':[
                {'external_hotel_id':'123','local_hotel_id':456,'decision_status':'accepted'},
                {'external_hotel_id':'124','local_hotel_id':None,'decision_status':'pending'}]}


class CountryExpansionTests(unittest.TestCase):
    def test_fixed_country_scope(self):
        self.assertEqual(expansion.COUNTRIES,('uae','thailand','vietnam','sri-lanka','maldives','cuba'))
        self.assertNotIn('russia',expansion.COUNTRIES)
        self.assertNotIn('abkhazia',expansion.COUNTRIES)

    def test_capture_all_once_and_durable_before_transport(self):
        with tempfile.TemporaryDirectory() as t:
            root=Path(t); calls=[]
            def transport(req):
                self.assertTrue((root/(req['country']+'.capture-reservation.json')).exists())
                self.assertEqual(req['phase'],'capture');calls.append(req['country']);return capture(req['country'])
            result=expansion.capture_all(root,transport)
            self.assertEqual(result['status'],'captured');self.assertEqual(tuple(calls),expansion.COUNTRIES)
            with self.assertRaises(FileExistsError):expansion.capture_all(root,transport)
            self.assertEqual(len(calls),6)

    def test_unknown_capture_is_not_retried_or_applied(self):
        with tempfile.TemporaryDirectory() as t:
            root=Path(t)
            def transport(req):
                if req['country']=='uae':raise TimeoutError()
                return capture(req['country'])
            expansion.capture_all(root,transport);applied=[]
            result=expansion.apply_all(root,lambda r:(applied.append(r['country']) or outcome(r['country'])))
            self.assertNotIn('uae',applied);self.assertEqual(len(applied),5)
            self.assertEqual(result['accepted_confirmed'],5)

    def test_apply_receipt_before_write_and_no_repeat(self):
        with tempfile.TemporaryDirectory() as t:
            root=Path(t);expansion.capture_all(root,lambda r:capture(r['country']));calls=[]
            def transport(req):
                self.assertTrue((root/(req['country']+'.apply-reservation.json')).exists())
                calls.append(req['country']);return outcome(req['country'])
            result=expansion.apply_all(root,transport)
            self.assertEqual(result['status'],'completed');self.assertEqual(result['accepted_confirmed'],6)
            for country in expansion.COUNTRIES:
                receipt=json.loads((root/f'{country}.receipt.json').read_bytes())
                self.assertEqual(receipt['state'],'finalized')
                self.assertEqual(receipt['result_sha256'],expansion.digest(receipt['result']))
            with self.assertRaises(FileExistsError):expansion.apply_all(root,transport)
            self.assertEqual(len(calls),6)

    def test_unknown_apply_preserves_reservation_not_success(self):
        with tempfile.TemporaryDirectory() as t:
            root=Path(t);expansion.capture_all(root,lambda r:capture(r['country']))
            def transport(req):raise TimeoutError()
            result=expansion.apply_all(root,transport)
            self.assertEqual(result['accepted_confirmed'],0)
            self.assertFalse((root/'uae.receipt.json').exists())
            self.assertTrue((root/'uae.apply-reservation.json').exists())

    def test_changed_capture_fails_before_any_transport(self):
        with tempfile.TemporaryDirectory() as t:
            root=Path(t);expansion.capture_all(root,lambda r:capture(r['country']))
            p=root/'uae.capture.json';v=json.loads(p.read_bytes());v['pairs'][0]['local_hotel_id']=999;p.write_text(json.dumps(v))
            with self.assertRaisesRegex(ValueError,'changed_after_reservation'):
                expansion.apply_all(root,lambda _:self.fail('transport not permitted'))

    def test_foreign_target_and_unresolved_target_rejected(self):
        for kind in ('target','unresolved','duplicate','counts','unconfirmed'):
            result=outcome('uae')
            if kind=='target':result['rows'][0]['local_hotel_id']=999
            elif kind=='unresolved':result['rows'][1]['local_hotel_id']=777
            elif kind=='duplicate':result['rows'][1]['external_hotel_id']='123'
            elif kind=='counts':result['new_counts']['accepted']=5
            else:result['readback_verified']=False
            with self.subTest(kind=kind),self.assertRaises(ValueError):expansion.validate_result(capture('uae'),result)

    def test_capture_requires_digest_and_request_budget(self):
        for key,value in (('capture_sha256','x'),('supplier_calls',4),('status','imported'),('country','russia')):
            bad=capture('uae');bad[key]=value
            with self.subTest(key=key),self.assertRaises(ValueError):expansion.validate_capture('uae',bad)

if __name__=='__main__':unittest.main(verbosity=2)
