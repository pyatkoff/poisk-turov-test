#!/usr/bin/env python3
import json, os, sys, tempfile, unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts/diagnostics'))
import anex_saved_complete_review as review

class CompleteReviewTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.ns={};exec(review.gaps.matching_source(),cls.ns)

    def source(self):
        return {'external_id':1,'api':{'id':1,'name':'SUN HOTEL','country':'Турция','latitude':36.7,'longitude':31.5,'town_id':7},
                'xml':{'id':1,'name':'SUN HOTEL','alternate_name':'SUN HOTEL','town_id':7},
                'status':'review','reason':'candidate_limit_reached','candidates':[]}
    def seed(self):
        return {'anex_hotel_id':1,'country_id':4,'source_member':'x','source_row_sha256':'a'*64}
    def item(self, complete=True, count=300):
        rows=[]
        for i in range(count):
            rows.append({'id':1000+i,'name':'OTHER PROPERTY '+str(i),'country_name':'Турция','region_name':'Сиде','subregion_name':'Сиде','latitude':40+i/10000,'longitude':35})
        rows[0]={'id':1000,'name':'SUN HOTEL','country_name':'Турция','region_name':'Сиде','subregion_name':'Сиде','latitude':36.7,'longitude':31.5}
        return {'key':1,'candidates':rows,'candidate_set_complete':complete,'fetch_limit':4097,'query_scope':'active_country_name_or_geobox'}

    def test_complete_large_set_can_be_strong(self):
        out=review.analyze(self.seed(),self.source(),self.item(),self.ns)
        self.assertTrue(out['eligible_not_applied']);self.assertEqual(out['best']['id'],1000)
        self.assertEqual(out['reason'],'name_country_coordinates');self.assertEqual(out['candidate_count'],300)

    def test_incomplete_set_never_accepted(self):
        with self.assertRaisesRegex(ValueError,'complete_candidate_proof_invalid'):
            review.analyze(self.seed(),self.source(),self.item(False),self.ns)

    def test_4097_sentinel_never_complete(self):
        item=self.item(True,4097)
        item['candidate_set_complete']=False
        with self.assertRaises(ValueError):review.analyze(self.seed(),self.source(),item,self.ns)

    def test_competing_candidate_stays_review(self):
        item=self.item();item['candidates'][1]=dict(item['candidates'][0],id=1001)
        out=review.analyze(self.seed(),self.source(),item,self.ns)
        self.assertFalse(out['eligible_not_applied']);self.assertEqual(out['reason'],'competing_candidates')

    def test_coordinate_conflict_stays_review(self):
        item=self.item();item['candidates'][0]['latitude']=50
        out=review.analyze(self.seed(),self.source(),item,self.ns)
        self.assertFalse(out['eligible_not_applied']);self.assertEqual(out['reason'],'coordinate_conflict')

    def test_former_name_suffix_does_not_create_section_conflict(self):
        item=self.item();item['candidates'][0]['name']='SUN HOTEL (EX. MOON GARDEN)'
        out=review.analyze(self.seed(),self.source(),item,self.ns)
        self.assertTrue(out['eligible_not_applied'])

    def test_wrong_key_duplicate_or_scope_refused(self):
        for mutate in ('key','dup','scope'):
            item=self.item()
            if mutate=='key':item['key']=2
            elif mutate=='dup':item['candidates'][1]['id']=item['candidates'][0]['id']
            else:item['query_scope']='other'
            with self.subTest(mutate=mutate),self.assertRaises(ValueError):review.analyze(self.seed(),self.source(),item,self.ns)

    @unittest.skipUnless(os.environ.get('ANEX_SAVED_DIR'),'retained archives not mounted')
    def test_seed_reproduces_exact_187_from_retained_archives(self):
        base=Path(os.environ['ANEX_SAVED_DIR'])
        paths={'anex':base/'archive.zip','egypt':base/'egypt.zip',
               'turkey':base/'turkey.zip','full':base/'complete-catalogues.zip'}
        value=review.seed(paths)
        self.assertEqual(value['count'],187);self.assertEqual(review.digest(value),review.SEED_SHA)
        self.assertEqual(len({r['anex_hotel_id'] for r in value['rows']}),187)
        self.assertTrue(all(r['query']['country_id']==r['country_id'] for r in value['rows']))

if __name__=='__main__':unittest.main(verbosity=2)
