#!/usr/bin/env python3
"""Pinned real data and receipt tests; no suppliers or application DB access."""
import copy,json,os,sys,tempfile,unittest,zipfile,hashlib
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts/diagnostics'))
import anex_saved_strong_batch as batch
from anex_search_mapping_import import write_protocol

class BatchTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.root=Path(os.environ['ANEX_SAVED_DIR'])
        paths={k:cls.root/f for k,f in {'anex':'archive.zip','egypt':'egypt.zip','turkey':'turkey.zip','full':'complete-catalogues.zip'}.items()}
        cls.report=batch.review(paths);cls.delta=batch.delta(cls.report)
        batch.save(cls.root/'strong-report.json',cls.report,exclusive=True)
        d=cls.delta;meta={k:v for k,v in d.items() if k!='rows'}
        meta.update(type='meta',protocol_version=1,mapping_digest=hashlib.sha256(batch.canonical(d)+b'\n').hexdigest(),rows_digest=hashlib.sha256(b''.join(batch.canonical(r)+b'\n' for r in d['rows'])).hexdigest())
        write_protocol(cls.root/'strong-protocol.ndjson',meta,d['rows'])
        with zipfile.ZipFile(paths['egypt']) as z:locals_={1:json.loads(z.read('local-catalog.json'))}
        with zipfile.ZipFile(paths['turkey']) as z:locals_[4]=json.loads(z.read('capture.json'))['local']
        with zipfile.ZipFile(paths['full']) as z:
            for slug in ('uae','thailand','vietnam','sri-lanka','maldives','cuba'):
                item=json.loads(z.read(slug+'-local.json'));locals_[item['country_id']]=item['data']
        batch.save(cls.root/'strong-fixture.json',{'locals':locals_,'delta':d},exclusive=True)

    def outcome(self):
        return {'status':'imported','inserted':25,'updated':0,'readback_verified':True,'operation_id':batch.OPERATION_ID,'skipped_manual':0,'skipped_pair_excluded':0,'link_readback':[dict(anex_hotel_id=r['anex_hotel_id'],catalog_hotel_id=r['catalog_hotel_id'],status='verified_policy_mapping') for r in self.delta['rows']]}

    def test_exact_new_set_and_gates(self):
        self.assertEqual(len(self.report['rows']),25);self.assertEqual(self.report['examined'],973)
        self.assertEqual(batch.digest(self.report),batch.REPORT_SHA)
        self.assertFalse(set(batch.COMPLETED_FOUR)&{r['anex_hotel_id'] for r in self.report['rows']})
        for r in self.report['rows']:
            self.assertLessEqual(r['distance_m'],200);self.assertGreaterEqual(r['name_similarity'],.9);self.assertLess(r['candidate_count'],256)
            if r['score_margin'] is not None:self.assertGreaterEqual(r['score_margin'],.1)

    def test_changed_evidence_rejected(self):
        value=copy.deepcopy(self.report);value['rows'][0]['catalog_hotel_id']+=1
        with self.assertRaises(ValueError):batch.delta(value)

    def test_unknown_never_replayed(self):
        with tempfile.TemporaryDirectory() as t:
            receipt=Path(t)/'receipt.json'
            def fail(_):raise TimeoutError()
            with self.assertRaises(TimeoutError):batch.apply(self.root/'strong-report.json',receipt,fail)
            with self.assertRaisesRegex(ValueError,'do_not_replay'):batch.apply(self.root/'strong-report.json',receipt,lambda _:self.fail('replayed'))

    def test_finalized_result_read_without_transport(self):
        with tempfile.TemporaryDirectory() as t:
            receipt=Path(t)/'receipt.json'
            def transport(_):
                self.assertEqual(json.loads(receipt.read_bytes())['state'],'reserved');return self.outcome()
            batch.apply(self.root/'strong-report.json',receipt,transport)
            self.assertEqual(batch.apply(self.root/'strong-report.json',receipt,lambda _:self.fail('replayed'))['new_writes'],0)

    def test_bad_readback_not_finalized(self):
        value=self.outcome();value['link_readback'][0]['catalog_hotel_id']+=1
        with tempfile.TemporaryDirectory() as t:
            receipt=Path(t)/'receipt.json'
            with self.assertRaises(ValueError):batch.apply(self.root/'strong-report.json',receipt,lambda _:value)
            self.assertEqual(json.loads(receipt.read_bytes())['state'],'reserved')
            self.assertTrue(receipt.with_name('receipt.json.outcome.json').is_file())

    def test_full_catalogue_fingerprint_observes_new_alias(self):
        a={'hotels':[{'id':1,'name':'A'}],'aliases':[]};b=copy.deepcopy(a);b['aliases'].append({'hotel_id':1,'alias':'B'})
        self.assertNotEqual(batch.catalogue_fingerprint(4,a),batch.catalogue_fingerprint(4,b))

if __name__=='__main__':unittest.main(verbosity=2)
