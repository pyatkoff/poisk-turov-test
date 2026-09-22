import copy, hashlib, importlib.util, json, os, pathlib, tempfile, unittest, zipfile
HERE = pathlib.Path(__file__).resolve().parent
s=importlib.util.spec_from_file_location('recovery', HERE/'match_bg_saved_catalog_recovery.py')
m=importlib.util.module_from_spec(s);s.loader.exec_module(m)

class SavedRecovery(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.bg=pathlib.Path(os.environ.get('MATCH_BG_RETAINED_ZIP', str(HERE.parent/'match-bg-retained816.zip')))
        cls.recovery=pathlib.Path(os.environ.get('MATCH_BG_CATALOG_RECOVERY_ZIP', str(HERE.parent/'match-bg-source-catalog-recovery.zip')))
        if not cls.bg.exists() or not cls.recovery.exists():raise unittest.SkipTest('owner artifacts absent')
        cls.result=m.run(cls.bg,m.BG_ZIP_SHA,cls.recovery,m.RECOVERY_ZIP_SHA)
        with zipfile.ZipFile(cls.recovery) as z:cls.source=json.loads(z.read('result.json'));cls.receipt=json.loads(z.read('receipt.json'))
    def altered(self,fn):
        result=copy.deepcopy(self.source);fn(result)
        rb=json.dumps(result,ensure_ascii=False).encode();receipt=copy.deepcopy(self.receipt);receipt['result_sha256']=hashlib.sha256(rb).hexdigest()
        with tempfile.TemporaryDirectory() as d:
            p=pathlib.Path(d)/'recovery.zip'
            with zipfile.ZipFile(p,'w') as z:z.writestr('result.json',rb);z.writestr('receipt.json',json.dumps(receipt))
            return m.run(self.bg,m.BG_ZIP_SHA,p,hashlib.sha256(p.read_bytes()).hexdigest())
    def test_exact_counts_and_membership(self):
        self.assertEqual(self.result['counts'],{'input_residual':114,'recovery_supported':18,'remaining_residual':96,'evidence_supported_after':387,'candidate_union_after':404})
        ids=[r['tv_hotel_id'] for r in self.result['rows'] if r['recovery_supported']]
        self.assertEqual(ids,[594,2650,3073,4142,4163,8335,15803,15835,41894,46770,51530,52232,68686,112029,124871,129740,143045,156439])
    def test_zero_authority(self):
        for k in ['provider_calls','supplier_http_requests','database_reads','mapping_writes','accepted_links_added']:self.assertEqual(self.result[k],0)
        self.assertFalse(self.result['safe_to_write_now']);self.assertFalse(self.result['visibility_verified'])
        self.assertTrue(all(r['safe_to_write_now'] is False for r in self.result['rows']))
    def test_star_drift_holds(self):
        out=self.altered(lambda d:next(r for r in d['rows'] if r['local_hotel_id']==594)['source_catalog_blocks'][0]['projection'].__setitem__('star',4))
        row=next(r for r in out['rows'] if r['tv_hotel_id']==594)
        self.assertFalse(row['recovery_supported']);self.assertIn('saved_source_category_mismatch',row['holds'])
    def test_explicit_country_conflict_holds(self):
        out=self.altered(lambda d:next(r for r in d['rows'] if r['local_hotel_id']==2650)['source_catalog_blocks'][0]['projection'].__setitem__('country','Турция'))
        self.assertIn('saved_source_country_mismatch',next(r for r in out['rows'] if r['tv_hotel_id']==2650)['holds'])
    def test_anchor_hash_drift_holds(self):
        out=self.altered(lambda d:next(r for r in d['rows'] if r['local_hotel_id']==3073).__setitem__('catalog_sha256','0'*64))
        self.assertIn('saved_anchor_hash_mismatch',next(r for r in out['rows'] if r['tv_hotel_id']==3073)['holds'])
    def test_artifact_digest_fail_closed(self):
        with self.assertRaisesRegex(ValueError,'recovery_artifact_digest_mismatch'):
            m.run(self.bg,m.BG_ZIP_SHA,self.recovery,'0'*64)
    def test_manifest_exact_output(self):
        fixture=json.loads((HERE/'fixtures'/'match_bg_saved_catalog_recovery_20260922.json').read_text())
        self.assertEqual(fixture['counts'],self.result['counts'])
        tuples=[[r['tv_hotel_id'],r['bgoperator_raw_f4'],r['accepted_andromeda_catalog_id']] for r in self.result['rows'] if r['recovery_supported']]
        self.assertEqual(fixture['recovered_tuples'],tuples)
        self.assertEqual(fixture['full_result_sha256'],hashlib.sha256(m.encoded(self.result)).hexdigest())

if __name__=='__main__':unittest.main()
