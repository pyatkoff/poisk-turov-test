#!/usr/bin/env python3
import copy,json,os,sys,tempfile,unittest
from pathlib import Path
from zipfile import ZipFile
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts/diagnostics'))
import andromeda_saved_bridge as bridge

PENDING=Path(os.environ['BRIDGE_PENDING_ZIP']);ANEX=Path(os.environ['BRIDGE_ANEX_ZIP'])
class BridgeTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.payload=bridge.request(PENDING,ANEX)
        with ZipFile(PENDING) as z: saved=json.loads(z.read('maldives.json'))
        selected={r['external_hotel_id']:r for r in cls.payload['rows']}
        entries=[r for r in saved['rows'] if r['identity']['external_hotel_id'] in selected]
        hotels={r['targets'][0]['id']:r['targets'][0] for r in entries}
        aliases=[{'hotel_id':r['local_hotel_id'],'alias':n} for r in cls.payload['rows'] for n in (r['source']['name'],r['source'].get('lName','')) if n]
        data={'country_id':8,'supplier_country_id':73,'catalog':{'action':'all','params':{'STATEINC':73},'payload':{'HOTELS':[r['source'] for r in cls.payload['rows']],'TOWNTO':saved['towns']}},'local':{'country_id':8,'complete':True,'hotels':list(hotels.values()),'aliases':aliases}}
        # Synthetic DB fixture uses the 61 real saved identities and constructed aliases;
        # it is not a claim that this is the complete application country catalogue.
        fixture={'payload':cls.payload,'data':data,'plan':{'rows':[r['identity'] for r in entries]}}
        Path(os.environ['BRIDGE_FIXTURE']).write_text(json.dumps(fixture,ensure_ascii=False))
    def fake(self):
        return {'status':'accepted','operation_id':bridge.OPERATION_ID,'request_sha256':bridge.REQUEST_SHA,'updated':61,'readback_verified':True,'other_identities_unchanged':True,'supplier_calls':0,'rows':[{'external_hotel_id':r['external_hotel_id'],'local_hotel_id':r['local_hotel_id'],'decision_status':'accepted','evidence_sha256':'a'*64} for r in self.payload['rows']]}
    def test_exact_delta(self):
        self.assertEqual(bridge.digest(self.payload),bridge.REQUEST_SHA)
        self.assertEqual(len(self.payload['rows']),61)
        self.assertEqual(len({r['local_hotel_id'] for r in self.payload['rows']}),61)
        for r in self.payload['rows']:
            for a in r['anex_bridges']:
                self.assertEqual(bridge.norm(a['town']),bridge.norm(r['source']['town']))
                self.assertEqual(a['catalog_hotel_id'],r['local_hotel_id'])
    def test_no_geography_fuzzy_or_direction_removal(self):
        self.assertNotEqual(bridge.norm('Южный Мале Атолл'),bridge.norm('Северный Мале Атолл'))
        self.assertNotEqual(bridge.norm('Мальдивы'),bridge.norm('Мальдивский Архипелаг'))
        self.assertNotEqual(bridge.name('SUN ANNEX'),bridge.name('SUN HOTEL'))
    def test_tampered_archives_rejected(self):
        with tempfile.TemporaryDirectory() as t:
            bad=Path(t)/'bad.zip';bad.write_bytes(PENDING.read_bytes()+b'x')
            with self.assertRaises(ValueError):bridge.request(bad,ANEX)
    def test_reservation_precedes_write_and_finalized_never_replays(self):
        with tempfile.TemporaryDirectory() as t:
            receipt=Path(t)/'receipt.json';calls=[]
            def run(req):
                self.assertEqual(json.loads(receipt.read_bytes())['state'],'reserved');calls.append(1);return self.fake()
            bridge.apply(self.payload,receipt,run)
            self.assertEqual(bridge.apply(self.payload,receipt,run)['status'],'already_finalized')
            self.assertEqual(len(calls),1)
    def test_unknown_preserved_without_retry(self):
        with tempfile.TemporaryDirectory() as t:
            receipt=Path(t)/'receipt.json'
            def lost(req):raise TimeoutError()
            with self.assertRaises(TimeoutError):bridge.apply(self.payload,receipt,lost)
            with self.assertRaisesRegex(ValueError,'do_not_replay'):bridge.apply(self.payload,receipt,lambda _:self.fail('no second write'))
    def test_bad_readback_retained_not_finalized(self):
        with tempfile.TemporaryDirectory() as t:
            receipt=Path(t)/'receipt.json';bad=self.fake();bad['rows'][-1]['local_hotel_id']=99
            with self.assertRaises(ValueError):bridge.apply(self.payload,receipt,lambda _:bad)
            self.assertEqual(json.loads(receipt.read_bytes())['state'],'reserved')
            self.assertTrue(receipt.with_name(receipt.name+'.outcome.json').is_file())
if __name__=='__main__':unittest.main(verbosity=2)
