import copy
import hashlib
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
import os
ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'scripts/diagnostics'))
import hotel_match_name_evidence_recovery as m

HERE = Path(__file__).resolve().parent
DATA = Path(os.environ['MATCH_RECOVERY_INPUT_DIR'])
SHA = 'a' * 64


def fixture(count=1):
    hotel = {'id': 10, 'name': 'NOVEL AMBER HOTEL EXTRA', 'country_id': 4, 'country_name': 'Турция',
             'region_id': 20, 'region_name': 'Город', 'subregion_id': 30, 'subregion_name': 'Район',
             'latitude': '36', 'longitude': '31'}
    current = {'frontier_count': count, 'current_local_hotels': {'10': hotel}, 'current_aliases': {},
               'current_frontier': {}, 'current_occupancy': {}, 'read_at_utc': '2026-09-16T15:34:08Z'}
    review = {'safe_to_write_now': False, 'frontier_holds_preserved': [], 'dossiers': []}
    old = {'routes': {'held': []}, 'created_at': '2026-09-15T11:54:00Z'}
    for i in range(count):
        eid = str(i + 1)
        d = {'external_hotel_id': eid, 'supplier_namespace': 'andromeda_catalog', 'safe_to_write_now': False,
             'frontier': {'andromeda_hotel_id': eid, 'supplier_namespace': 'andromeda_catalog',
                          'country_id': 4, 'country_name': 'Турция', 'state_key': 5,
                          'names': ['Novel Amber Hotel'], 'evidence_sha256': SHA, 'frequency': None},
             'retained_evidence': [], 'evidence_flags': [], 'geography_evidence': {'holds': []},
             'retained_search_observed': False, 'retained_search_documents_lower_bound': None,
             'hotel_identity_review': {'route': 'held', 'holds': ['no_exact_or_bounded_spelling'],
                 'candidate_ids': [], 'target': None, 'effective_geo_anchors': [
                     {'country_id': 4, 'scope': 'region', 'scope_id': 20}], 'safe_to_write_now': False}}
        review['dossiers'].append(d)
        current['current_frontier'][eid] = {'external_hotel_id': eid, 'holds': [],
            'evidence_sha256': SHA, 'decision_status': 'pending', 'local_hotel_id': None}
        old['routes']['held'].append({'external_hotel_id': eid, 'evidence_sha256': SHA,
                                    'country_id': 4, 'points': []})
    return current, review, old


def candidate(out):
    return out['dossiers'][0]['retrieved_candidates'][0]


class RecoveryTests(unittest.TestCase):
    def test_containment_keeps_name_difference_and_original_hold(self):
        a,b,c = fixture()
        out = m.recover(a,b,c)
        proof = candidate(out)['name_evidence'][0]
        self.assertEqual(proof['local_unmatched_tokens'], ('extra',))
        self.assertEqual(out['dossiers'][0]['prior_review']['holds'], ['no_exact_or_bounded_spelling'])
        self.assertFalse(candidate(out)['safe_to_write_now'])
        self.assertFalse(candidate(out)['safe_to_query_supplier_now'])

    def test_complete_2000_rows_no_input_mutation(self):
        a,b,c = fixture(2000)
        before = m.canonical([a,b,c])
        out = m.recover(a,b,c)
        self.assertEqual(out['summary']['frontier_rows'], 2000)
        self.assertEqual(len(out['dossiers']), 2000)
        self.assertEqual(out['summary']['with_new_candidate_targets'], 2000)
        self.assertEqual(before, m.canonical([a,b,c]))

    def test_internal_word_substring_is_not_a_boundary(self):
        a,b,c = fixture()
        a['current_local_hotels']['10']['name'] = 'ON HOTEL PHUKET'
        b['dossiers'][0]['frontier']['names'] = ['Plaza Radisson Resort Phuket']
        self.assertEqual(m.recover(a,b,c)['dossiers'][0]['retrieved_candidates'], [])

    def test_former_lists_preserve_explicit_roles(self):
        forms = m.name_forms('New Bright Hotel (EX. Alpha Blossom; Beta Blossom)')
        items = [x for x in forms if x['role'] == 'explicit_former_list_item']
        self.assertEqual({x['compact'] for x in items}, {'alphablossom','betablossom'})
        self.assertTrue(any(x['role'] == 'explicit_former_full' for x in forms))
        self.assertTrue(all('EX.' in x['original_name'] for x in forms))

    def test_unmarked_commas_are_not_accepted_aliases(self):
        forms = m.name_forms('New Bright, Other Blossom Hotel')
        self.assertEqual({x['role'] for x in forms}, {'primary'})

    def test_generic_qualifier_names_are_not_identifiers(self):
        a,b,c = fixture()
        a['current_local_hotels']['10']['name'] = 'ROYAL GARDEN HOTEL EXTRA'
        b['dossiers'][0]['frontier']['names'] = ['Royal Garden Resort Spa']
        self.assertEqual(m.recover(a,b,c)['dossiers'][0]['retrieved_candidates'], [])

    def test_compound_boundary_does_not_lose_all_letters(self):
        a,b,c = fixture()
        a['current_local_hotels']['10']['name'] = 'YAMAN LIFE HOTEL EXTRA'
        b['dossiers'][0]['frontier']['names'] = ['YAMANLIFE HOTEL']
        out = m.recover(a,b,c)
        self.assertEqual(candidate(out)['proposed_local_hotel_id'], 10)
        self.assertEqual(candidate(out)['name_evidence'][0]['shared_compact'], 'yamanlife')

    def test_foreign_country_not_a_candidate(self):
        a,b,c = fixture()
        a['current_local_hotels']['10']['country_id'] = 1
        self.assertEqual(m.recover(a,b,c)['dossiers'][0]['retrieved_candidates'], [])

    def test_numeric_id_equality_not_evidence(self):
        a,b,c = fixture()
        a['current_local_hotels']['10']['name'] = 'Unrelated Different Hotel'
        self.assertEqual(m.recover(a,b,c)['dossiers'][0]['retrieved_candidates'], [])

    def test_numeric_and_significant_qualifier_mismatch_are_held(self):
        for suffix in (' NORTH', ' BEACH', ' GARDEN', ' ANNEX', ' 2', ' SOUTH 3'):
            with self.subTest(suffix=suffix):
                a,b,c = fixture()
                a['current_local_hotels']['10']['name'] += suffix
                p = candidate(m.recover(a,b,c))
                self.assertIn('qualifier_or_number_difference_requires_independent_proof',p['snapshot_holds'])
                self.assertEqual(p['candidate_stage'],'held_for_conflicting_or_protected_context')

    def test_source_protection_flags_are_not_cleared(self):
        a,b,c = fixture()
        b['dossiers'][0]['evidence_flags'] = ['manual_decision', 'multiple_retained_search_primary_names']
        p = candidate(m.recover(a,b,c))
        self.assertIn('manual_decision',p['snapshot_holds'])
        self.assertIn('multiple_retained_search_primary_names',p['snapshot_holds'])

    def test_prior_geography_ambiguity_is_not_ignored(self):
        a,b,c = fixture()
        b['dossiers'][0]['geography_evidence']['holds'] = ['ambiguous_local_geography']
        p = candidate(m.recover(a,b,c))
        self.assertIn('ambiguous_local_geography',p['snapshot_holds'])
        self.assertEqual(p['candidate_stage'],'held_for_conflicting_or_protected_context')

    def test_changed_source_and_pending_state_are_held(self):
        a,b,c = fixture()
        a['current_frontier']['1'].update(evidence_sha256='b'*64, decision_status='accepted', local_hotel_id=10)
        p = candidate(m.recover(a,b,c))
        self.assertIn('source_digest_changed', p['snapshot_holds'])
        self.assertIn('not_pending_null_in_snapshot', p['snapshot_holds'])

    def test_occupancy_is_not_overwritten(self):
        a,b,c = fixture()
        a['current_occupancy']['10'] = ['another_id']
        p = candidate(m.recover(a,b,c))
        self.assertIn('occupied_target_in_snapshot', p['snapshot_holds'])
        self.assertEqual(p['snapshot_occupancy'], ['another_id'])

    def test_candidate_specific_geography_conflict_is_held(self):
        a,b,c = fixture()
        a['current_local_hotels']['10']['region_id'] = 25
        p = candidate(m.recover(a,b,c))
        self.assertIn('candidate_geography_conflict', p['snapshot_holds'])

    def test_missing_geography_stays_unknown(self):
        a,b,c = fixture()
        b['dossiers'][0]['hotel_identity_review']['effective_geo_anchors'] = []
        p = candidate(m.recover(a,b,c))
        self.assertEqual(p['candidate_stage'], 'name_and_geography_evidence_needed')
        self.assertEqual(p['geography_status'], 'unknown')

    def test_coordinate_conflict_over_five_km(self):
        a,b,c = fixture()
        c['routes']['held'][0]['points'] = [{'latitude':37, 'longitude':31}]
        p = candidate(m.recover(a,b,c))
        self.assertIn('coordinate_conflict_gt5km', p['snapshot_holds'])
        self.assertGreater(p['distances_km'][0], 100)

    def test_old_coordinate_digest_mismatch_not_used_as_proof(self):
        a,b,c = fixture()
        c['routes']['held'][0].update(evidence_sha256='b'*64,points=[{'latitude':37,'longitude':31}])
        p = candidate(m.recover(a,b,c))
        self.assertEqual(p['distances_km'], [])

    def test_prepared_survivors_not_researched(self):
        a,b,c = fixture()
        b['dossiers'][0]['hotel_identity_review'].update(route='prepared_for_current_review',target=10,candidate_ids=[10],holds=[])
        out = m.recover(a,b,c)
        self.assertEqual(out['summary']['previously_prepared_not_researched'],1)
        self.assertEqual(out['dossiers'][0]['retrieved_candidates'],[])
        self.assertEqual(out['dossiers'][0]['prior_review']['target'],10)

    def test_unknown_frequency_not_zero(self):
        a,b,c = fixture(2)
        b['dossiers'][1]['frontier']['frequency'] = 0
        out = m.recover(a,b,c)
        self.assertEqual([r['frequency'] for r in out['dossiers']],[None,0])
        self.assertEqual(out['summary']['unknown_current_frequency_preserved'],1)

    def test_duplicate_or_excluded_frontier_fail_closed(self):
        for excluded in (False, True):
            a,b,c = fixture()
            if excluded:
                b['frontier_holds_preserved'] = [{'andromeda_hotel_id':'1'}]
            else:
                b['dossiers'].append(copy.deepcopy(b['dossiers'][0]));a['frontier_count']=2
            with self.assertRaises(ValueError):m.recover(a,b,c)

    def test_json_duplicate_and_nonfinite_are_rejected(self):
        for raw in (b'{"a":1,"a":2}', b'{"a":NaN}', b'{"a":Infinity}', b'[]'):
            with self.assertRaises(ValueError):m.decode(raw)

    def test_real_artifacts_full_coverage_original_reviews_unchanged(self):
        a,b = m.read_artifact(DATA/'match-hierarchy-current-35116182779.zip','current')
        c,_ = m.read_artifact(DATA/'match-census-10394524643.zip','census')
        out = m.recover(a,b,c)
        self.assertEqual(out['summary'], {'candidate_pairs':619,'frontier_rows':1321,'new_candidate_pairs':579,
            'new_geographically_supported_pairs':171,'no_candidate_in_this_retrieval_tier':778,
            'previously_prepared_not_researched':5,'unknown_current_frequency_preserved':1321,
            'with_new_candidate_targets':507,'with_new_geographically_supported_candidate':168,
            'with_retrieved_candidates':538})
        self.assertEqual([d['external_hotel_id'] for d in out['dossiers']],[d['external_hotel_id'] for d in b['dossiers']])
        for new,prior in zip(out['dossiers'],b['dossiers']):
            self.assertEqual(new['prior_review'],prior['hotel_identity_review'])
            self.assertEqual(new['source_evidence_sha256'],prior['frontier']['evidence_sha256'])
            self.assertFalse(new['safe_to_write_now']);self.assertFalse(new['safe_to_query_supplier_now'])
        self.assertEqual(m.digest(m.canonical(out)), '73a6d537ed852e85bcf6601792a226f6a552ed9e7e690f64245297838ebc929c')

    def test_cli_exclusive_output_tampered_input(self):
        with tempfile.TemporaryDirectory() as tmp:
            output=Path(tmp)/'result.json'
            cmd=[sys.executable,str(ROOT/'scripts/diagnostics/hotel_match_name_evidence_recovery.py'),str(DATA/'match-hierarchy-current-35116182779.zip'),
                 str(DATA/'match-census-10394524643.zip'),str(output)]
            subprocess.run(cmd, check=True, capture_output=True)
            before=output.read_bytes()
            self.assertNotEqual(subprocess.run(cmd,capture_output=True).returncode,0)
            self.assertEqual(before,output.read_bytes())
            bad=Path(tmp)/'bad.zip';bad.write_bytes((DATA/'match-hierarchy-current-35116182779.zip').read_bytes()+b'x')
            with self.assertRaisesRegex(ValueError,'archive_pin'):m.read_artifact(bad,'current')


    def test_audit_preserves_every_pair_and_prior_hold(self):
        a,b,c = fixture()
        recovery=m.recover(a,b,c); before=m.canonical(recovery)
        out=m.audit_recovery(recovery)
        self.assertEqual(out['summary']['all_pairs'],1)
        self.assertEqual(out['pairs'][0]['stage'],'unique_retrieved_target_requires_name_difference_proof')
        self.assertEqual(out['pairs'][0]['prior_review_holds'], ['no_exact_or_bounded_spelling'])
        self.assertFalse(out['pairs'][0]['safe_to_write_now'])
        self.assertFalse(out['pairs'][0]['direct_provider_identity_proven'])
        self.assertEqual(before,m.canonical(recovery))

    def test_audit_does_not_turn_alias_text_into_direct_id(self):
        a,b,c=fixture()
        a['current_local_hotels']['10']['name']='New Dawn Hotel (EX. Novel Amber; Prior Garden)'
        recovery=m.recover(a,b,c);out=m.audit_recovery(recovery)
        p=out['pairs'][0]
        self.assertEqual(p['evidence_kind'],'full_retained_name_form')
        self.assertEqual(p['priority_tier'],0)
        self.assertFalse(p['direct_provider_identity_proven'])
        self.assertFalse(p['safe_to_query_supplier_now'])

    def test_audit_keeps_competitors_before_geography_filter(self):
        a,b,c=fixture()
        a['current_local_hotels']['11']=dict(a['current_local_hotels']['10'],id=11,region_id=999,name='Novel Amber Other')
        out=m.audit_recovery(m.recover(a,b,c))
        good=next(p for p in out['pairs'] if p['proposed_local_hotel_id']==10)
        self.assertTrue(good['multiple_retrieved_targets'])
        self.assertEqual(good['priority_tier'],3)
        self.assertEqual(good['all_retrieved_target_ids'],[10,11])

    def test_audit_reversed_source_collision_and_2000_retention(self):
        a,b,c=fixture(2000)
        out=m.audit_recovery(m.recover(a,b,c))
        self.assertEqual(out['summary']['frontier_rows'],2000)
        self.assertEqual(out['summary']['all_pairs'],2000)
        self.assertEqual(len(out['target_source_groups']['10']),2000)
        self.assertTrue(all(p['retrieved_source_count_at_target']==2000 and p['priority_tier']==3 for p in out['pairs']))

    def test_audit_rejects_changed_proof_or_authority(self):
        a,b,c=fixture(); base=m.recover(a,b,c)
        for change in ('authority','span','whole','unmatched','form','source','duplicate'):
            r=copy.deepcopy(base);p=r['dossiers'][0]['retrieved_candidates'][0];proof=p['name_evidence'][0]
            if change=='authority':p['safe_to_write_now']=True
            elif change=='span':proof['source_span']=[-1,2]
            elif change=='whole':proof['full_form_equal']=True
            elif change=='unmatched':proof['local_unmatched_tokens']=[]
            elif change=='form':proof['source']['raw_fragment']='Invented Name'
            elif change=='source':r['dossiers'][0]['source_names']=['Invented Different Name']
            else:r['dossiers'][0]['retrieved_candidates'].append(copy.deepcopy(p))
            with self.subTest(change=change),self.assertRaises(ValueError):m.audit_recovery(r)

    def test_audit_original_holds_outrank_evidence_priority(self):
        a,b,c=fixture()
        a['current_occupancy']['10']=['999']
        out=m.audit_recovery(m.recover(a,b,c))
        self.assertEqual(out['pairs'][0]['priority_tier'],4)
        self.assertIn('occupied_target_in_snapshot',out['pairs'][0]['snapshot_holds'])

    def test_audit_actual_packet_full_coverage_and_kind_counts(self):
        a,b=m.read_artifact(DATA/'match-hierarchy-current-35116182779.zip','current')
        c,_=m.read_artifact(DATA/'match-census-10394524643.zip','census')
        recovery=m.recover(a,b,c);out=m.audit_recovery(recovery)
        self.assertEqual(out['summary']['all_pairs'],619)
        self.assertEqual(out['summary']['additional_pairs'],579)
        self.assertEqual(out['summary']['geography_supported_additional_pairs_with_multiple_targets'],28)
        self.assertEqual(out['all_original_frontier_ids_preserved'],[d['external_hotel_id'] for d in b['dossiers']])
        self.assertEqual(len({(p['external_hotel_id'],p['proposed_local_hotel_id']) for p in out['pairs']}),619)
        self.assertTrue(all(p['safe_to_write_now'] is False and p['safe_to_query_supplier_now'] is False for p in out['pairs']))

    def test_audit_cli_creates_separate_exclusive_outputs(self):
        with tempfile.TemporaryDirectory() as tmp:
            out=Path(tmp)/'result.json';audit=Path(tmp)/'audit.json'
            cmd=[sys.executable,str(ROOT/'scripts/diagnostics/hotel_match_name_evidence_recovery.py'),str(DATA/'match-hierarchy-current-35116182779.zip'),str(DATA/'match-census-10394524643.zip'),str(out),'--audit-output',str(audit)]
            subprocess.run(cmd,check=True,capture_output=True)
            self.assertEqual(json.loads(audit.read_bytes())['summary']['all_pairs'],619)
            before=(out.read_bytes(),audit.read_bytes())
            self.assertNotEqual(subprocess.run(cmd,capture_output=True).returncode,0)
            self.assertEqual((out.read_bytes(),audit.read_bytes()),before)


if __name__ == '__main__':
    unittest.main(verbosity=2)
