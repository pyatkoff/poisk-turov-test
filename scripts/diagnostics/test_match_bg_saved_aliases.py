import copy, hashlib, importlib.util, json, os, pathlib, tempfile, unittest, zipfile
HERE = pathlib.Path(__file__).resolve().parent
s=importlib.util.spec_from_file_location('matcher', HERE/'match_bg_saved_aliases.py')
m=importlib.util.module_from_spec(s);s.loader.exec_module(m)

class Names(unittest.TestCase):
    def test_only_hotel_is_generic(self):
        self.assertEqual(m.name('Alpha Hotel & SPA'),m.name('ALPHA AND SPA'))
        for qualifier in ['APART','FAMILY','SEA VIEW','DELUXE','SUITE','PREMIUM','AQUA PARK','OASIS','GARDEN','BEACH','RESORT']:
            self.assertNotEqual(m.name('Alpha '+qualifier),m.name('Alpha'))
    def test_former_names_are_not_current(self):
        n=m.names('SHARM PLAZA (EX. CROWNE PLAZA RESORT)')
        self.assertEqual(n['current'],'sharm plaza');self.assertEqual(n['declared_former'],['crowne plaza resort'])
        self.assertEqual(n['raw'],'SHARM PLAZA (EX. CROWNE PLAZA RESORT)')
    def test_shared_former_does_not_merge_two_current_hotels(self):
        self.assertEqual(m.name_proofs('SHARM PLAZA (EX. CROWNE PLAZA RESORT)','SHARM RESORT (EX. CROWNE PLAZA RESORT)',[]),[])
    def test_no_fuzzy_or_script_transliteration(self):
        self.assertNotEqual(m.name('SPА'),m.name('SPA')) # first A is Cyrillic
        self.assertEqual(m.name_proofs('MIRETTE FAMILY','PALMA',[]),[])
    def test_other_parentheticals_keep_qualifier(self):
        self.assertNotEqual(m.names('ALPHA (ADULTS ONLY)')['current'],m.names('ALPHA')['current'])
    def test_same_canonical_record_explicit_alias(self):
        rows=[{'name':'TTC Hotel Michelia, Nha Trang','lName':'TTC Hotel Premium - Michelia'}]
        p=m.name_proofs('TTC HOTEL PREMIUM - MICHELIA','TTC HOTEL MICHELIA NHA TRANG',rows)
        self.assertEqual([x['rule'] for x in p],['same_canonical_record_declares_both_titles'])
    def test_alias_cannot_cross_two_canonical_records(self):
        self.assertEqual(m.name_proofs('ALPHA PREMIUM','ALPHA', [{'name':'ALPHA PREMIUM'},{'name':'ALPHA'}]),[])
    def test_empty_name_is_not_a_match(self):
        self.assertEqual(m.name_proofs('HOTEL','',[]),[])
    def test_current_title_survives_ex(self):
        self.assertTrue(m.name_proofs('KAILA BEACH HOTEL (EX. KATYA HOTEL)','Kaila Beach Hotel',[]))

class Actual816(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        p=pathlib.Path(os.environ.get('MATCH_BG_RETAINED_ZIP', str(HERE.parent/'match-bg-retained816.zip')))
        if not p.exists():raise unittest.SkipTest('retained owner artifact absent; name regression remains runnable')
        cls.archive=p
        with zipfile.ZipFile(p) as z:cls.source=json.loads(z.read('result.json'));cls.receipt=json.loads(z.read('receipt.json'))
        cls.result=m.run(p,'a52b4dbdf4a9eafdd49746a5a1aae461374f8d558e088096165ff82964e2b515')
    def altered(self,fn):
        data=copy.deepcopy(self.source);fn(data)
        rb=json.dumps(data,ensure_ascii=False).encode();q=copy.deepcopy(self.receipt);q['result_sha256']=hashlib.sha256(rb).hexdigest()
        with tempfile.TemporaryDirectory() as d:
            p=pathlib.Path(d)/'a.zip'
            with zipfile.ZipFile(p,'w') as z:z.writestr('result.json',rb);z.writestr('receipt.json',json.dumps(q))
            return m.run(p,hashlib.sha256(p.read_bytes()).hexdigest())
    def test_mass_counts_and_disjoint_delta(self):
        self.assertEqual(self.result['counts']['new_evidence_candidates'],79)
        self.assertEqual(self.result['counts']['candidate_union_not_accepted'],335)
        self.assertEqual(len(set(self.result['new_candidate_ids'])),79)
        for r in self.result['rows']:
            self.assertFalse(r['safe_to_write_now'])
            self.assertEqual(r['input_row'],next(s for s in self.source['rows'] if s['tv_hotel_id']==r['tv_hotel_id']))
    def test_no_synthetic_acceptance_or_namespace_transform(self):
        self.assertEqual(self.result['accepted_links_added'],0);self.assertFalse(self.result['ids_promoted_to_operator115'])
        self.assertEqual(self.result['mapping_writes'],0)
        self.assertEqual(self.result['database_reads'],0)
    def test_operatorlink_tamper_held(self):
        out=self.altered(lambda d:next(r for r in d['rows'] if r['tv_hotel_id']==244).__setitem__('operator_link_sha256','0'*64))
        r=next(r for r in out['rows'] if r['tv_hotel_id']==244)
        self.assertFalse(r['alias_evidence_supported']);self.assertIn('operator_link_provenance_not_exact',r['evidence_holds'])
    def test_manual_preserved(self):
        out=self.altered(lambda d:next(r for r in d['rows'] if r['tv_hotel_id']==244).__setitem__('manual',[{'decision':'hold'}]))
        self.assertFalse(next(r for r in out['rows'] if r['tv_hotel_id']==244)['alias_evidence_supported'])
    def test_duplicate_membership_rejected(self):
        with self.assertRaisesRegex(ValueError,'unexpected_membership'):
            self.altered(lambda d:d['rows'].__setitem__(0,copy.deepcopy(d['rows'][1])))
    def test_cross_hotel_canonical_ownership_held(self):
        target=next(r for r in self.source['rows'] if r['tv_hotel_id']==244)['accepted_catalog_ids'][0]
        out=self.altered(lambda d:d['rows'][0]['accepted_catalog_ids'].append(target))
        self.assertIn('competing_target_in_saved_membership',next(r for r in out['rows'] if r['tv_hotel_id']==244)['evidence_holds'])
    def test_receipt_digest_fail_closed(self):
        with self.assertRaisesRegex(ValueError,'artifact_digest_mismatch'):m.run(self.archive,'0'*64)
    def test_multiple_canonical_never_new(self):
        for r in self.result['rows']:
            if len(r['input_row']['accepted_catalog_ids'])!=1:self.assertFalse(r['new_alias_evidence_candidate'])

if __name__=='__main__':unittest.main()
