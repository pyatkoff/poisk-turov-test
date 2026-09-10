#!/usr/bin/env python3
"""Complete current catalogue competition for every saved ANEX candidate-limit row.

Read-only operation. It reuses the checked 973-row saved Online evidence review,
then asks the existing AnyTour catalogue reader for an exhausted active-country
candidate set. It never calls ANEX/Andromeda suppliers and never writes mappings.
"""
from __future__ import annotations

import argparse
from collections import Counter
import hashlib
import json
from pathlib import Path
from zipfile import ZipFile

import anex_saved_strong_batch as strong
import anex_search3_gap_queue as gaps
import anex_search3_owner_decisions as owner
from anex_tourvisor_link_import import digest, save
from anex_tourvisor_link_review import current_name

OPERATION_ID = 'anex-1759-complete187-review-20260910-v1'
STRONG_REPORT_SHA = '5a04ff6b3ed11f0477ab8c06f4779d901f335af06cca652e8fd87864f29737f7'
SEED_COUNT = 187
SEED_SHA = 'ba9c9e068fb9240cef81a87129e4a31ee91f9ecd79d259e5d54f2e7c016a7cd8'


def load_json(archive: ZipFile, member: str):
    return json.loads(archive.read(member))


def seed(paths):
    report = strong.review(paths)
    if digest(report) != STRONG_REPORT_SHA or report.get('examined') != 973:
        raise ValueError('saved_online_review_changed')
    ids = sorted(d['anex_hotel_id'] for d in report['decisions'] if d['reason'] == 'candidate_limit_reached')
    if len(ids) != SEED_COUNT or len(set(ids)) != SEED_COUNT:
        raise ValueError('candidate_limit_cohort_changed')
    archives = {}
    try:
        for key in ('anex','egypt','turkey'):
            archives[key] = strong.load_archive(paths[key], strong.ARCHIVES[key][0])
        archives['full'] = strong.load_archive(paths['full'], strong.FULL_SHA)
        history = {}
        for member in ('anex-hotel-geo-enrichment.json','anex-initial-search-checkpoint.json','anex-observed-hotel-checkpoint.json'):
            for row in load_json(archives['anex'], member)['rows']:
                if row.get('api'):
                    history[row['external_id']] = (member, row)
        catalog = {r['external_id']:r for r in load_json(archives['anex'],'anex-hotel-catalog-match.json')['matches']}
        locals_ = {1:load_json(archives['egypt'],'local-catalog.json'),4:load_json(archives['turkey'],'capture.json')['local']}
        for slug in ('uae','thailand','vietnam','sri-lanka','maldives','cuba'):
            item=load_json(archives['full'],slug+'-local.json')
            if digest(item['data']) != item['data_sha256']:
                raise ValueError('saved_local_digest')
            locals_[int(item['country_id'])]=item['data']
        country_names={}
        for country,local in locals_.items():
            if local.get('complete') is not True or int(local['country_id']) != country:
                raise ValueError('incomplete_local_catalogue')
            for hotel in local['hotels']:
                if int(hotel['country_id']) != country:
                    raise ValueError('foreign_local_hotel')
                country_names[hotel['country_name']]=country
        rows=[]
        for identifier in ids:
            member,evidence=history[identifier]
            original=catalog[identifier]
            country=country_names.get(original['country'])
            if country is None or evidence.get('reason') != 'candidate_limit_reached' or len(evidence.get('candidates',[])) != 256:
                raise ValueError('saved_candidate_limit_source_changed')
            if evidence['api'].get('id') != identifier or evidence['xml'].get('id') != identifier:
                raise ValueError('supplier_identity_changed')
            rows.append({'anex_hotel_id':identifier,'country_id':country,'source_member':member,
                'source_row_sha256':digest(evidence),'original_catalog_row_sha256':digest(original),
                'query':{'key':identifier,'names':[evidence['api'].get('name',''),evidence['xml'].get('name',''),evidence['xml'].get('alternate_name','')],
                    'country_id':country,'latitude':evidence['api'].get('latitude'),'longitude':evidence['api'].get('longitude')}})
        result={'operation_id':OPERATION_ID,'source_report_sha256':STRONG_REPORT_SHA,
            'source_archives':report['sources'],'count':len(rows),'rows':rows,'supplier_calls':0,'database_writes':0}
        if SEED_SHA and digest(result) != SEED_SHA:
            raise ValueError('complete_review_seed_changed')
        return result
    finally:
        for archive in archives.values(): archive.close()


def analyze(seed_row, source_row, item, namespace=None):
    candidates=item.get('candidates')
    if (item.get('key') != seed_row['anex_hotel_id'] or not isinstance(candidates,list)
            or item.get('fetch_limit') != 4097 or item.get('query_scope') != 'active_country_name_or_geobox'
            or item.get('candidate_set_complete') is not (len(candidates) < 4097)
            or item.get('candidate_set_complete') is not True or len(candidates) > 4096
            or len({c.get('id') for c in candidates}) != len(candidates)
            or any(type(c.get('id')) is not int or c['id'] <= 0 for c in candidates)):
        raise ValueError('complete_candidate_proof_invalid')
    ns=namespace or {}
    if not ns:
        exec(gaps.matching_source(),ns)
    api=dict(source_row['api']);xml=dict(source_row['xml'])
    api['name']=current_name(api.get('name',''))
    xml.update({k:current_name(xml.get(k) or '') for k in ('name','alternate_name')})
    ranked=[]
    for raw in candidates:
        candidate=dict(raw,name=current_name(raw.get('name','')))
        ranked.append(ns['candidate_rank'](api,xml,candidate))
    ranked.sort(key=lambda c:(-c['score'],c['id']))
    status,reason=ns['geo_decision'](api,ranked,ns['xml_relation'](xml,api),candidate_set_complete=True)
    best=ranked[0] if ranked else None
    return {'anex_hotel_id':seed_row['anex_hotel_id'],'country_id':seed_row['country_id'],
        'source_member':seed_row['source_member'],'source_row_sha256':seed_row['source_row_sha256'],
        'candidate_set_complete':True,'candidate_count':len(candidates),'raw_candidates_sha256':digest(candidates),
        'ranked_candidates_sha256':digest(ranked),'best':best,
        'score_margin':round(best['score']-ranked[1]['score'],4) if len(ranked)>1 else None,
        'status':status,'reason':reason,'eligible_not_applied':status=='strong_candidate' and reason=='name_country_coordinates'}


def review(paths, seed_doc, complete_items):
    if digest(seed_doc) != SEED_SHA:
        raise ValueError('unchecked_complete_review_seed')
    by_key={item.get('key'):item for item in complete_items}
    if len(by_key)!=SEED_COUNT or set(by_key)!=set(r['anex_hotel_id'] for r in seed_doc['rows']):
        raise ValueError('complete_review_item_set_changed')
    with strong.load_archive(paths['anex'], strong.ARCHIVES['anex'][0]) as archive:
        history={}
        for member in ('anex-hotel-geo-enrichment.json','anex-initial-search-checkpoint.json','anex-observed-hotel-checkpoint.json'):
            for row in load_json(archive,member)['rows']:
                if row.get('api'): history[row['external_id']]=(member,row)
    ns={};exec(gaps.matching_source(),ns)
    rows=[]
    for seed_row in seed_doc['rows']:
        member,source=history[seed_row['anex_hotel_id']]
        if member!=seed_row['source_member'] or digest(source)!=seed_row['source_row_sha256']:
            raise ValueError('saved_source_row_changed')
        rows.append(analyze(seed_row,source,by_key[seed_row['anex_hotel_id']],ns))
    counts=Counter(r['reason'] for r in rows)
    return {'operation_id':OPERATION_ID,'seed_sha256':SEED_SHA,'count':len(rows),'rows':rows,
        'eligible_count':sum(r['eligible_not_applied'] for r in rows),'reason_counts':dict(sorted(counts.items())),
        'supplier_calls':0,'database_writes':0}


def remote_capture(paths,directory:Path):
    directory.mkdir(mode=0o700,parents=True,exist_ok=True)
    seed_doc=seed(paths)
    save(directory/'seed.json',seed_doc,exclusive=True)
    save(directory/'reservation.json',{'state':'reserved_before_catalog_reads','operation_id':OPERATION_ID,
        'seed_sha256':digest(seed_doc),'count':SEED_COUNT,'supplier_calls':0,'database_writes':0},exclusive=True)
    source=Path(__file__).with_name('anex_catalog_reader.php').read_text(encoding='utf-8').removeprefix('<?php')
    items=[]
    for offset in range(0,SEED_COUNT,2):
        batch=seed_doc['rows'][offset:offset+2]
        request={'mode':'complete_review','queries':[r['query'] for r in batch]}
        result=owner.ssh_php(source,request,maximum_bytes=4000000)
        expected={r['anex_hotel_id'] for r in batch}
        got=result.get('items',[]) if isinstance(result,dict) else []
        if result.get('status')!='ok' or {r.get('key') for r in got}!=expected or len(got)!=len(batch):
            raise ValueError('complete_catalog_read_unconfirmed')
        for item in got:
            # Validate proof shape immediately before persisting it.
            row=next(r for r in batch if r['anex_hotel_id']==item['key'])
            if item.get('candidate_set_complete') is not (len(item.get('candidates',[]))<4097):
                raise ValueError('complete_set_flag_invalid')
        part={'offset':offset,'request_sha256':digest(request),'items':got}
        save(directory/f'part-{offset:03d}.json',part,exclusive=True)
        items.extend(got)
    report=review(paths,seed_doc,items)
    save(directory/'complete-review.json',report,exclusive=True)
    save(directory/'result.json',{'status':'completed_read_only','operation_id':OPERATION_ID,
        'seed_sha256':SEED_SHA,'review_sha256':digest(report),'eligible_count':report['eligible_count'],
        'reason_counts':report['reason_counts'],'catalog_reads':SEED_COUNT,'supplier_calls':0,'database_writes':0},exclusive=True)
    return json.loads((directory/'result.json').read_bytes())


def main():
    p=argparse.ArgumentParser(description=__doc__)
    for key in ('anex','egypt','turkey','full'):p.add_argument('--'+key,required=True,type=Path)
    p.add_argument('--seed',action='store_true');p.add_argument('--directory',type=Path)
    args=p.parse_args();paths={k:getattr(args,k) for k in ('anex','egypt','turkey','full')}
    if args.seed:
        print(json.dumps(seed(paths),ensure_ascii=False,sort_keys=True));return
    if args.directory is None:p.error('--directory required unless --seed')
    print(json.dumps(remote_capture(paths,args.directory),ensure_ascii=False,sort_keys=True))

if __name__=='__main__':main()
