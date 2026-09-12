#!/usr/bin/env python3
import importlib.util, pathlib
P=pathlib.Path(__file__).resolve().parents[1]/'scripts'/'diagnostics'/'hotel_match_tourvisor_seed_overlap.py'
s=importlib.util.spec_from_file_location('m',P); m=importlib.util.module_from_spec(s); s.loader.exec_module(m)

def fixture():
    seeds=[{'anex_hotel_id':i,'search_count':1,'reason':'tourvisor_anex_operator_link_hotelcode_needed'} for i in range(1,432)]
    # Patch fixture constants only; production constants remain immutable.
    m.BULK_SEED_SHA256=m.sha(seeds); m.CURRENT_QUEUE_SHA256='q'; m.CURRENT_LIVE_IDS=3; m.CURRENT_OPERATOR_IDS=2; m.CURRENT_PRODUCT_IDS=1; m.CURRENT_OCCURRENCES=15; m.CURRENT_OPERATOR_OCCURRENCES=10
    current={'source_operation_id':m.CURRENT_OPERATION,'status':'prepared_only','queue_sha256':'q','live_unresolved_ids':3,'operator_evidence_queue_ids':2,'product_review_ids':1,'observed_occurrences':15,
             'priority_columns':['anex_hotel_id','country_id','search_count','staging_present','product_identity_review'],
             'priority_rows':[[1,4,9,0,0],[999,4,1,1,0],[2,4,5,0,1]]}
    norm=m.normalize_priority(current); m.CURRENT_PRIORITY_SHA256=m.sha(norm)
    bulk={'operation_id':m.BULK_OPERATION,'status':'completed','tourvisor_anex_seeds':seeds}
    return bulk,current

def test_overlap():
    b,c=fixture(); r=m.build(b,c); assert r['counts']['already_in_bulk_tourvisor_seed_ids']==1; assert r['counts']['new_tail_ids']==1; assert r['counts']['current_product_identity_ids']==1; assert r['counts']['coverage_pct_by_occurrences']==90.0; assert r['overlap'][0]['anex_hotel_id']==1; assert r['new_tail'][0]['anex_hotel_id']==999

def test_fail_closed_counts():
    b,c=fixture(); c['operator_evidence_queue_ids']=3
    try:m.build(b,c)
    except ValueError as e: assert str(e)=='current_counts_changed'
    else: raise AssertionError('expected fail')

def test_fail_closed_duplicate():
    b,c=fixture(); c['priority_rows'][1][0]=1; m.CURRENT_PRIORITY_SHA256=m.sha([{'anex_hotel_id':1,'country_id':4,'search_count':9,'staging_present':False,'product_identity_review':False},{'anex_hotel_id':1,'country_id':4,'search_count':1,'staging_present':True,'product_identity_review':False},{'anex_hotel_id':2,'country_id':4,'search_count':5,'staging_present':False,'product_identity_review':True}])
    try:m.build(b,c)
    except ValueError as e: assert str(e)=='current_priority_duplicate'
    else: raise AssertionError('expected fail')

if __name__=='__main__':
    test_overlap(); test_fail_closed_counts(); test_fail_closed_duplicate(); print('hotel_match_tourvisor_seed_overlap_test: 3 PASS')
