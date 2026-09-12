#!/usr/bin/env python3
"""MATCH #1971: join saved CURRENT Tourvisor→ANEX seeds to the later live unresolved queue.

Offline/read-only only. This tool never calls Tourvisor/ANEX, DB, workflows, or writes mappings.
It proves which current live-unresolved ANEX identities were already selected by a completed
server-current bulk review as needing the Tourvisor operator-link -> ANEX hotelCode ladder.
"""
from __future__ import annotations
import argparse, hashlib, json
from pathlib import Path

BULK_OPERATION = 'hotel-match-current-bulk-review-1971-20260911-v1'
BULK_REVIEW_SHA256 = 'a6f6f965fa5eb2d9c436d87972cdab9ab6cb4dccae44cdf68c1048f44acb59f9'
BULK_SEED_SHA256 = '20188af61b5e6adb60bae21ac79e6a081d8cdf7a42a09eb12cbb92372bd2001d'
BULK_SEED_COUNT = 431
CURRENT_OPERATION = 'hotel-match-post156-fuzzy-bridge-review-1971-20260912-v2'
CURRENT_QUEUE_SHA256 = 'd0a5daf1b3057b9bbbd90da51c92934510c1afe58a513cae3a42313defe85984'
CURRENT_PRIORITY_SHA256 = '2c60a5dadc84a6c3da9d773b0a1e9d985920cc8f5b7abba9c8987d77e70c07f4'
CURRENT_LIVE_IDS = 67
CURRENT_OPERATOR_IDS = 61
CURRENT_PRODUCT_IDS = 6
CURRENT_OCCURRENCES = 1671
CURRENT_OPERATOR_OCCURRENCES = 1445


def canonical(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode('utf-8')

def sha(value):
    return hashlib.sha256(canonical(value)).hexdigest()

def file_sha(path: Path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def normalize_priority(report):
    cols = report.get('priority_columns')
    if cols != ['anex_hotel_id','country_id','search_count','staging_present','product_identity_review']:
        raise ValueError('current_priority_columns_changed')
    rows=[]
    for raw in report.get('priority_rows') or []:
        if not isinstance(raw, list) or len(raw)!=5: raise ValueError('current_priority_row_invalid')
        aid,country,count,staging,product=raw
        if not all(type(v) is int for v in (aid,country,count,staging,product)):
            raise ValueError('current_priority_type_invalid')
        if aid<=0 or country not in {1,2,4,8,9,10,12,16} or count<0 or staging not in {0,1} or product not in {0,1}:
            raise ValueError('current_priority_value_invalid')
        rows.append({'anex_hotel_id':aid,'country_id':country,'search_count':count,
                     'staging_present':bool(staging),'product_identity_review':bool(product)})
    if len({r['anex_hotel_id'] for r in rows}) != len(rows): raise ValueError('current_priority_duplicate')
    return rows

def build(bulk, current):
    if bulk.get('operation_id') != BULK_OPERATION or bulk.get('status') != 'completed':
        raise ValueError('bulk_operation_changed')
    seeds=bulk.get('tourvisor_anex_seeds') or []
    if len(seeds)!=BULK_SEED_COUNT or sha(seeds)!=BULK_SEED_SHA256:
        raise ValueError('bulk_seed_set_changed')
    if current.get('source_operation_id') != CURRENT_OPERATION or current.get('status')!='prepared_only':
        raise ValueError('current_operation_changed')
    if current.get('queue_sha256') != CURRENT_QUEUE_SHA256:
        raise ValueError('current_queue_changed')
    if [current.get('live_unresolved_ids'),current.get('operator_evidence_queue_ids'),current.get('product_review_ids'),current.get('observed_occurrences')] != [CURRENT_LIVE_IDS,CURRENT_OPERATOR_IDS,CURRENT_PRODUCT_IDS,CURRENT_OCCURRENCES]:
        raise ValueError('current_counts_changed')
    priority=normalize_priority(current)
    if sha(priority)!=CURRENT_PRIORITY_SHA256: raise ValueError('current_priority_changed')
    physical=[r for r in priority if not r['product_identity_review']]
    product=[r for r in priority if r['product_identity_review']]
    if len(physical)!=CURRENT_OPERATOR_IDS or sum(r['search_count'] for r in physical)!=CURRENT_OPERATOR_OCCURRENCES or len(product)!=CURRENT_PRODUCT_IDS:
        raise ValueError('current_priority_semantics_changed')
    seed_map={int(r['anex_hotel_id']):r for r in seeds}
    overlap=[]; tail=[]
    for r in physical:
        old=seed_map.get(r['anex_hotel_id'])
        if old is None:
            tail.append(dict(r))
            continue
        overlap.append({**r,
            'bulk_review_search_count':int(old.get('search_count') or 0),
            'search_count_delta':r['search_count']-int(old.get('search_count') or 0),
            'bulk_reason':old.get('reason'),
            'next_evidence':'saved Tourvisor ANEX-only result -> operatorLink -> ANEX card/media hotelCode -> CURRENT semantic/preservation recheck',
        })
    overlap.sort(key=lambda r:(-r['search_count'],r['anex_hotel_id']))
    tail.sort(key=lambda r:(-r['search_count'],r['anex_hotel_id']))
    product=sorted(product,key=lambda r:(-r['search_count'],r['anex_hotel_id']))
    covered_weight=sum(r['search_count'] for r in overlap); total=sum(r['search_count'] for r in physical)
    return {
        'schema':'hotel-match-tourvisor-seed-overlap/1','status':'prepared_only','not_write_authority':True,
        'historical_operations_replayed':False,'database_writes':0,'mapping_writes':0,'supplier_calls':0,'tourvisor_calls':0,
        'sources':{
            'bulk_operation':BULK_OPERATION,'bulk_review_sha256':BULK_REVIEW_SHA256,'bulk_seed_sha256':BULK_SEED_SHA256,
            'bulk_seed_count':len(seeds),'current_operation':CURRENT_OPERATION,'current_queue_sha256':CURRENT_QUEUE_SHA256,
            'current_priority_sha256':CURRENT_PRIORITY_SHA256,
        },
        'counts':{
            'current_live_unresolved_ids':len(priority),'current_physical_hotel_ids':len(physical),'current_product_identity_ids':len(product),
            'current_physical_occurrences':total,'already_in_bulk_tourvisor_seed_ids':len(overlap),'already_in_bulk_tourvisor_seed_occurrences':covered_weight,
            'new_tail_ids':len(tail),'new_tail_occurrences':sum(r['search_count'] for r in tail),
            'coverage_pct_by_ids':round(100*len(overlap)/len(physical),3),'coverage_pct_by_occurrences':round(100*covered_weight/total,3),
        },
        'overlap_sha256':sha(overlap),'new_tail_sha256':sha(tail),'product_review_sha256':sha(product),
        'overlap':overlap,'new_tail':tail,'product_identity_review':product,
        'interpretation':[
            'Overlap is prioritization evidence only, not a mapping or proof of Tourvisor operatorLink/hotelCode.',
            'The 52 overlap identities should not be rediscovered by another fuzzy/catalog pass; they need the operatorLink/card/hotelCode acquisition ladder.',
            'The 9-row new tail was absent from the older 431-seed snapshot and should be added to future Tourvisor acquisition scope.',
            'Six product labels remain outside physical-hotel auto-mapping.'
        ]
    }

def main():
    p=argparse.ArgumentParser(); p.add_argument('--bulk-review',type=Path,required=True); p.add_argument('--current-report',type=Path,required=True); p.add_argument('--output',type=Path,required=True); a=p.parse_args()
    if file_sha(a.bulk_review)!=BULK_REVIEW_SHA256: raise SystemExit('bulk_review_sha256_mismatch')
    bulk=json.loads(a.bulk_review.read_text(encoding='utf-8')); current=json.loads(a.current_report.read_text(encoding='utf-8'))
    result=build(bulk,current)
    with a.output.open('x',encoding='utf-8') as f: f.write(json.dumps(result,ensure_ascii=False,sort_keys=True,indent=2)+'\n')
    print(json.dumps(result['counts'],sort_keys=True))
if __name__=='__main__': main()
