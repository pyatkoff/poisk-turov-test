import copy, hashlib, importlib.util, json, os, pathlib, tempfile, unittest, zipfile
HERE = pathlib.Path(__file__).resolve().parent
s=importlib.util.spec_from_file_location('corroboration', HERE/'match_bg_saved_common4_corroboration.py')
m=importlib.util.module_from_spec(s);s.loader.exec_module(m)

class SavedCommon4Corroboration(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.bg=pathlib.Path(os.environ.get('MATCH_BG_RETAINED_ZIP', str(HERE.parent/'match-bg-retained816.zip')))
        cls.recovery=pathlib.Path(os.environ.get('MATCH_BG_CATALOG_RECOVERY_ZIP', str(HERE.parent/'match-bg-source-catalog-recovery.zip')))
        if not cls.bg.exists() or not cls.recovery.exists():raise unittest.SkipTest('owner artifacts absent')
        cls.result=m.run(cls.bg,m.recovery.BG_ZIP_SHA,cls.recovery,m.recovery.RECOVERY_ZIP_SHA)
        with zipfile.ZipFile(cls.bg) as z:cls.source=json.loads(z.read('result.json'));cls.receipt=json.loads(z.read('receipt.json'))
    def altered(self,fn):
        result=copy.deepcopy(self.source);fn(result)
        rb=json.dumps(result,ensure_ascii=False).encode();receipt=copy.deepcopy(self.receipt);receipt['result_sha256']=hashlib.sha256(rb).hexdigest()
        with tempfile.TemporaryDirectory() as d:
            p=pathlib.Path(d)/'bg.zip'
            with zipfile.ZipFile(p,'w') as z:z.writestr('result.json',rb);z.writestr('receipt.json',json.dumps(receipt))
            return m.run(p,hashlib.sha256(p.read_bytes()).hexdigest(),self.recovery,m.recovery.RECOVERY_ZIP_SHA)
    def test_exact_counts_and_membership(self):
        self.assertEqual(self.result['counts'],{'input_unresolved_after_recovery':96,'corroboration_supported':16,'remaining_unresolved':80,'evidence_supported_after':403,'candidate_union_after':414,'unique_uncommitted_after_accept297':106})
        ids=[r['tv_hotel_id'] for r in self.result['rows'] if r['corroboration_supported']]
        self.assertEqual(ids,[240,428,997,1080,1302,1326,2265,23766,32575,43533,63693,64670,64913,66640,69442,108166])
    def test_zero_authority_and_no_operator115_corroboration(self):
        for k in ['provider_calls','supplier_http_requests','database_reads','mapping_writes','accepted_links_added']:self.assertEqual(self.result[k],0)
        self.assertFalse(self.result['safe_to_write_now']);self.assertFalse(self.result['visibility_verified'])
        self.assertTrue(all(a['supplier_namespace'] in {'operator_5','operator_315','operator_342'} for r in self.result['rows'] for a in r['independent_common4_anchors']))
    def test_provider_hash_drift_holds(self):
        row=next(r for r in self.source['rows'] if r['tv_hotel_id']==240)
        inp=copy.deepcopy(row);anchor=next(a for a in inp['canonical_evidence'] if a['supplier_namespace']=='andromeda_catalog')
        next(a for a in inp['canonical_evidence'] if a['supplier_namespace']=='operator_342')['catalog_sha256']='0'*64
        self.assertEqual(m.independent_corroborators(inp,240,anchor),[])
    def test_operator115_cannot_replace_independent_anchor(self):
        row=next(r for r in self.source['rows'] if r['tv_hotel_id']==240)
        inp=copy.deepcopy(row);anchor=next(a for a in inp['canonical_evidence'] if a['supplier_namespace']=='andromeda_catalog')
        next(a for a in inp['canonical_evidence'] if a['supplier_namespace']=='operator_342')['supplier_namespace']='operator_115'
        self.assertEqual(m.independent_corroborators(inp,240,anchor),[])
    def test_significant_qualifier_drift_holds(self):
        geography=[('official_city.title_en','Nha Trang'),('official_city.title_ru','Нячанг')]
        self.assertEqual(m.title_proofs('ORBIT RESORT & SPA','ORBIT NHA TRANG',geography),[])
    def test_only_exact_saved_geography_affix_is_ignored(self):
        row=next(r for r in self.result['rows'] if r['tv_hotel_id']==240)
        self.assertEqual(row['title_proofs'][0]['normalized_title'],'happy life village')
        self.assertEqual(row['title_proofs'][0]['official_removed_affix'][0]['value'],'Dahab')
    def test_manifest_exact_output(self):
        fixture=json.loads((HERE/'fixtures'/'match_bg_saved_common4_corroboration_20260922.json').read_text())
        self.assertEqual(fixture['counts'],self.result['counts'])
        tuples=[[r['tv_hotel_id'],r['bgoperator_raw_f4'],r['accepted_andromeda_catalog_id']] for r in self.result['rows'] if r['corroboration_supported']]
        self.assertEqual(fixture['corroborated_tuples'],tuples)
        self.assertEqual(fixture['full_result_sha256'],hashlib.sha256(m.encoded(self.result)).hexdigest())

if __name__=='__main__':unittest.main()
