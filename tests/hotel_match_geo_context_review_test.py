"""Synthetic guard fixtures; not proof of live DB acceptance."""
import copy
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/'scripts'/'diagnostics'))
from hotel_match_geo_context_review import GeoContextReview, stripped_forms, PROTECTED, NON_HOTEL

def data():
    h={'id':10,'name':'Unique Blossom Hotel Laleli','country_id':4,'country_name':'Турция','country_class':'turkey',
       'region_name':'Стамбул','subregion_name':'Лалели','category':5,'latitude':41.0,'longitude':28.95,'is_active':1}
    d={'status':'completed','operation_id':'fixture','generated_at_utc':'fixture','database_writes':0,
       'country_ids':{'4':'turkey','1':'egypt'},'local':[h],'aliases':[],'anex':[],'anex_mappings':[],
       'anex_decisions':[],'anex_exclusions':[],'anex_observations':[],'andromeda':[]}
    b={'matches':[{'external_id':1,'name':'Unique Blossom Resort','alternate_name':'','country':'Турция','town':'Лалели-Фатих'}]}
    return d,b

def candidate(d,b):
    rows=GeoContextReview(d,b).run()['rows']
    return next(r for r in rows if r['source']['provider']=='anex' and r['source']['external_id']=='1')

def andromeda(sid=20,lid=None,status='pending',name='Unique Blossom',town='Лалели',state='Турция',star='5',star_key=99):
    return {'supplier_namespace':'andromeda_catalog','external_hotel_id':str(sid),'local_hotel_id':lid,
            'decision_status':status,'sources':[{'id':sid,'name':name,'lName':name,'state':state,'town':town,'star':star,'starKey':star_key}],
            'evidence_sha256':'fixture','actual_evidence_sha256':'fixture','catalog_sha256':'fixture'}

class Tests(unittest.TestCase):
    def test_proved_geographic_suffix(self):
        d,b=data();r=candidate(d,b)
        self.assertEqual(r['status'],'prepared_geography_evidence');self.assertEqual(r['local_id'],10)
        self.assertFalse(r['accepted']);self.assertTrue(r['current_revalidation_required'])
        self.assertEqual(r['name_proof'][0]['target'][0]['removed'][0]['geography_field'],'Лалели')

    def test_hali_short_distinct_name_in_specific_district(self):
        d,b=data();d['local'][0]['name']='HALI HOTEL SULTANAHMET';d['local'][0]['subregion_name']='Султанахмет'
        b['matches'][0].update(name='Hali Hotel',town='Султанахмет-Фатих')
        self.assertEqual(candidate(d,b)['status'],'prepared_geography_evidence')

    def test_no_geography_not_an_alias(self):
        self.assertNotIn('blossom unique',stripped_forms(['Unique Blossom Hotel Laleli'],[]))

    def test_never_remove_significant_qualifiers(self):
        for qualifier in ['BEACH','GARDEN','ANNEX','NORTH','SOUTH','MOUNTAIN','POOL','СЕВЕРНЫЙ']:
            forms=stripped_forms(['Unique Blossom '+qualifier],[qualifier])
            self.assertNotIn('blossom unique',forms,qualifier)

    def test_only_edge_phrases_not_inside_identity(self):
        self.assertNotIn('blossom unique',stripped_forms(['Unique Laleli Blossom'],['Лалели']))

    def test_unrelated_suffix_not_stripped(self):
        d,b=data();d['local'][0]['name']='Unique Blossom Hotel Sirkeci'
        self.assertEqual(candidate(d,b)['status'],'no_geographic_name_proof')

    def test_full_countrywide_competition(self):
        d,b=data();h=copy.deepcopy(d['local'][0]);h.update(id=11,name='Unique Blossom Hotel Taksim',subregion_name='Таксим');d['local'].append(h)
        self.assertEqual(candidate(d,b)['status'],'ambiguous_countrywide_identity')

    def test_exact_lane_never_repeated(self):
        d,b=data();d['local'][0]['name']='Unique Blossom Hotel'
        self.assertEqual(candidate(d,b)['status'],'existing_exact_lane_not_repeated')

    def test_protected_ids_excluded(self):
        for table in ['anex_mappings','anex_decisions','anex_exclusions']:
            d,b=data();d[table]=[{'anex_hotel_id':1,'catalog_hotel_id':10,'enabled':1}]
            self.assertEqual(GeoContextReview(d,b).run()['examined'],0)

    def test_observed_missing_staging_survives(self):
        d,b=data();d['anex_observations']=[{'anex_hotel_id':1,'hotel_name':'Unique Blossom','country_id':4,'search_count':50}]
        result=GeoContextReview(d,b).run();self.assertEqual(result['live_prepared'],1);self.assertEqual(result['live_observation_weight'],50)

    def test_known_country_conflict(self):
        d,b=data();d['anex']=[{'anex_hotel_id':1,'api_country':'Египет','api_name':'Unique Blossom'}]
        self.assertEqual(GeoContextReview(d,b).run()['examined'],0)

    def test_country_id_namespaces_do_not_substitute(self):
        d,b=data();d['local'][0].update(country_id=1,country_name='Египет',country_class='egypt')
        self.assertEqual(candidate(d,b)['status'],'no_geographic_name_proof')

    def test_coordinate_conflict_blocks_geo_match(self):
        d,b=data();d['anex']=[{'anex_hotel_id':1,'api_country':'Турция','api_name':'Unique Blossom','latitude':42,'longitude':28.95}]
        self.assertEqual(candidate(d,b)['status'],'coordinate_conflict')

    def test_no_global_subregion_removal_byblos_regression(self):
        d,b=data();d['country_ids']={'9':'uae'}
        d['local'][0].update(name='MARINA BYBLOS HOTEL',country_id=9,country_name='ОАЭ',country_class='uae',region_name='Дубай',subregion_name='Марина')
        b['matches'][0].update(name='Social Hotels Tecom (EX. Byblos Hotel Dubai)',country='ОАЭ',town='Дубай')
        self.assertEqual(candidate(d,b)['status'],'unconfirmed_removed_geography')

    def test_country_is_not_geography_proof(self):
        d,b=data();b['matches'][0]['town']='Турция'
        self.assertNotEqual(candidate(d,b)['status'],'prepared_geography_evidence')

    def test_fortuna_placeholders_not_real_hotels(self):
        self.assertTrue(NON_HOTEL.search('fortuna 3 ai marmaris'))
        self.assertTrue(NON_HOTEL.search('roulette 4 sharm'))
        self.assertFalse(NON_HOTEL.search('fortuna hotel'))

    def test_star_key_not_number_of_stars(self):
        d,b=data();d['andromeda']=[andromeda(star='5',star_key=99)]
        r=next(x for x in GeoContextReview(d,b).run()['rows'] if x['source']['provider']=='andromeda')
        self.assertEqual(r['status'],'prepared_geography_evidence')
        self.assertEqual(r['source']['category_key_not_rating'],99)

    def test_real_star_mismatch_deferred(self):
        d,b=data();d['andromeda']=[andromeda(star='4',star_key=5)]
        r=next(x for x in GeoContextReview(d,b).run()['rows'] if x['source']['provider']=='andromeda')
        self.assertEqual(r['status'],'category_needs_independent_evidence')

    def test_alias_cannot_erase_current_beach_garden_difference(self):
        d,b=data();d['local'][0]['name']='Unique Blossom Beach Laleli (EX. Unique Blossom Hotel Laleli)'
        self.assertEqual(candidate(d,b)['status'],'significant_qualifier_difference')

    def test_accepted_conflicting_bridge_is_not_propagated(self):
        d,b=data();h=copy.deepcopy(d['local'][0]);h.update(id=11,name='Other Hotel',region_name='Кемер',subregion_name=None);d['local'].append(h)
        d['andromeda']=[andromeda(lid=10,status='accepted',town='Кемер')]
        result=GeoContextReview(d,b).run();self.assertEqual(len(result['bridge_conflicts']),1)
        self.assertEqual(candidate(d,b)['status'],'accepted_bridge_conflict')
        self.assertEqual(d['andromeda'][0]['decision_status'],'accepted')

    def test_fuzzy_margin_unique_local_not_alias_topk(self):
        d,b=data();h=copy.deepcopy(d['local'][0]);h.update(id=11,name='Unique Blossomm Laleli');d['local'].append(h)
        self.assertEqual(candidate(d,b)['status'],'small_name_margin')

    def test_digest_required_before_parsing_inputs(self):
        with tempfile.TemporaryDirectory() as tmp:
            p=Path(tmp);(p/'c').write_text('{}');(p/'b').write_text('{}')
            script=Path(__file__).resolve().parents[1]/'scripts/diagnostics/hotel_match_geo_context_review.py'
            run=subprocess.run([sys.executable,str(script),str(p/'c'),str(p/'b'),str(p/'out'),'--census-sha256','0'*64,'--baseline-sha256','0'*64],capture_output=True,text=True)
            self.assertNotEqual(run.returncode,0);self.assertFalse((p/'out').exists())
            self.assertIn('input_digest_mismatch',run.stderr)

if __name__=='__main__':unittest.main()
