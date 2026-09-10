#!/usr/bin/env python3
"""Saved ANEX Online proof -> exact append-only batch; no supplier or queue calls."""
from __future__ import annotations
import argparse
from collections import Counter, defaultdict
import hashlib
import json
from pathlib import Path
from zipfile import ZipFile
from anex_tourvisor_link_review import accepted_snapshot, current_name, policy_namespace
from anex_tourvisor_link_import import canonical, digest, save
from andromeda_bridge_review import ARCHIVES, name_key

OPERATION_ID = 'anex-1759-saved-strong25-20260910-v1'
FULL_SHA = '7f2fc5c5f83ddda924d546b003ba03c9dad738ed95d54ff0a53d7b14337d6b8f'
REPORT_SHA = '5a04ff6b3ed11f0477ab8c06f4779d901f335af06cca652e8fd87864f29737f7'
GUARDS_SHA = 'a5bf066578531b1486ce977e26b3d90b91b7d277ce39f269ffdb1f25f8d0453f'
COMPLETED_FOUR = {8121:6319,16275:1326,23775:17568,26688:55648}


def load_archive(path, expected):
    if hashlib.sha256(Path(path).read_bytes()).hexdigest() != expected:
        raise ValueError('unreviewed_archive')
    return ZipFile(path)


def catalogue_fingerprint(country, local):
    hotels = sorted([[int(h['id']),h['name']] for h in local['hotels']])
    aliases = sorted([[int(a['hotel_id']),a['alias']] for a in local['aliases']])
    return digest({'country_id':int(country),'hotels':hotels,'aliases':aliases})


def review(paths):
    archives = {}
    try:
        for key in ('anex','egypt','turkey'):
            archives[key] = load_archive(paths[key], ARCHIVES[key][0])
        archives['full'] = load_archive(paths['full'], FULL_SHA)
        read = lambda key, member: json.loads(archives[key].read(member))
        accepted, manual = accepted_snapshot(archives['anex'])
        accepted.update(COMPLETED_FOUR)
        catalog = {r['external_id']:r for r in read('anex','anex-hotel-catalog-match.json')['matches']}
        locals_ = {1:read('egypt','local-catalog.json'),4:read('turkey','capture.json')['local']}
        for slug in ('uae','thailand','vietnam','sri-lanka','maldives','cuba'):
            item=read('full',slug+'-local.json')
            if digest(item['data']) != item['data_sha256']:
                raise ValueError('saved_local_digest')
            locals_[item['country_id']]=item['data']
        contexts={};country_names={}
        for country,local in locals_.items():
            if local['complete'] is not True or int(local['country_id'])!=country:
                raise ValueError('incomplete_local_catalogue')
            hotels={int(h['id']):h for h in local['hotels']};index=defaultdict(set)
            if len(hotels)!=len(local['hotels']) or not hotels:
                raise ValueError('invalid_catalogue_identities')
            for h in hotels.values():
                if int(h['country_id'])!=country:
                    raise ValueError('foreign_country')
                country_names[h['country_name']]=country
                if name_key(h['name']):index[name_key(h['name'])].add(int(h['id']))
            for alias in local['aliases']:
                if int(alias['hotel_id']) not in hotels:
                    raise ValueError('orphan_alias')
                if name_key(alias['alias']):index[name_key(alias['alias'])].add(int(alias['hotel_id']))
            contexts[country]=(hotels,index)
        history={}
        for member in ('anex-hotel-geo-enrichment.json','anex-initial-search-checkpoint.json','anex-observed-hotel-checkpoint.json'):
            for r in read('anex',member)['rows']:
                if r.get('api'):history[r['external_id']]=(member,r)
        policy=policy_namespace();rows=[];decisions=[]
        for a,(member,evidence) in sorted(history.items()):
            original=catalog.get(a)
            if a in accepted or a in manual or not original or original['country'] not in country_names:
                continue
            country=country_names[original['country']];api=dict(evidence['api']);xml=dict(evidence['xml'])
            decision={'anex_hotel_id':a,'country_id':country,'status':'review','reason':'supplier_identity_unverified'}
            decisions.append(decision)
            if (api.get('id')!=a or xml.get('id')!=a or policy['xml_relation'](xml,api)!='same_record'
                    or policy['country_match'](original['country'],api.get('country')) is not True):
                continue
            api['name']=current_name(api.get('name',''))
            xml.update({k:current_name(xml.get(k) or '') for k in ('name','alternate_name')})
            ranked=[]
            for c in evidence.get('candidates',[]):
                candidate=dict(c,country_name=c.get('country'),region_name=c.get('region'),subregion_name=c.get('town'),name=current_name(c['name']))
                ranked.append(policy['candidate_rank'](api,xml,candidate))
            if len({c['id'] for c in ranked})!=len(ranked):
                raise ValueError('duplicate_evidence_candidates')
            ranked.sort(key=lambda c:(-c['score'],c['id']))
            status,reason=policy['geo_decision'](api,ranked,'same_record')
            decision['reason']=reason
            if status!='strong_candidate':continue
            target=ranked[0]['id'];hotels,index=contexts[country];exact=set()
            for value in (evidence['api']['name'],original['name'],original['alternate_name']):
                if name_key(value):exact.update(index.get(name_key(value),set()))
            if exact!={target}:
                decision['reason']='full_country_name_competition';decision['candidate_ids']=sorted(exact);continue
            saved_target=next(c for c in evidence['candidates'] if c['id']==target)
            if saved_target['name']!=hotels[target]['name']:
                decision['reason']='saved_target_name_changed';continue
            decision['status']='eligible_not_applied'
            rows.append({'anex_hotel_id':a,'catalog_hotel_id':target,'country_id':country,
                'name':original['name'],'target_name':hotels[target]['name'],'target_latitude':saved_target['latitude'],
                'target_longitude':saved_target['longitude'],'distance_m':ranked[0]['distance_m'],
                'name_similarity':ranked[0]['name_similarity'],'score_margin':round(ranked[0]['score']-ranked[1]['score'],4) if len(ranked)>1 else None,
                'candidate_count':len(ranked),'source_member':member,'source_evidence_sha256':digest(evidence),
                'original_catalog_row_sha256':digest(original),'ranked_candidates_sha256':digest(ranked)})
        countries={str(c):catalogue_fingerprint(c,locals_[c]) for c in sorted({r['country_id'] for r in rows})}
        return {'operation_id':OPERATION_ID,'sources':{k:v[0] for k,v in ARCHIVES.items()}|{'full':FULL_SHA},
            'rows':rows,'country_catalogue_fingerprints':countries,'examined':len(decisions),
            'decisions':decisions,'counts':dict(Counter(d['reason'] for d in decisions)),
            'supplier_calls':0,'database_writes':0}
    finally:
        for archive in archives.values():archive.close()


def delta(report):
    if digest(report)!=REPORT_SHA or len(report['rows'])!=25:
        raise ValueError('unreviewed_strong_report')
    rows=[{'anex_hotel_id':r['anex_hotel_id'],'catalog_hotel_id':r['catalog_hotel_id'],
           'match_class':'strong_candidate','reason':'saved_online_complete_country_20260910:name_country_coordinates',
           'source_row_digest':digest(r)} for r in report['rows']]
    guards={'operation_id':OPERATION_ID,'countries':report['country_catalogue_fingerprints'],
        'targets':{str(r['anex_hotel_id']):{k:r[k] for k in ('catalog_hotel_id','country_id','target_name','target_latitude','target_longitude')}|{'source_row_digest':digest(r)} for r in report['rows']}}
    if digest(guards)!=GUARDS_SHA:raise ValueError('unreviewed_current_guards')
    return {'schema_version':1,'scope':'preview','approval_policy':'owner_exact_and_strong_20260908','append_only':True,
        'sources':{'catalog_sha256':'8f1ee7bd288fe4f94a7e49a0245faffd4933e654443760d6454f7dfbf76d67f1','geo_sha256':'88943e704bbc45120aa3a3f5b98e7b3975af55a5ca33f5a7ed5ed4b63e6513e5','gap_sha256':REPORT_SHA},
        'saved_strong_guards':guards,'rows':rows,'counts':{'exact':0,'strong':25,'total':25,'unique_catalog_hotels':len({r['catalog_hotel_id'] for r in rows})}}


def approved_delta(path):
    return delta(json.loads(Path(path).read_bytes()))


def validate_result(value,report):
    if (value.get('status')!='imported' or value.get('inserted')!=25 or value.get('updated')!=0
        or value.get('readback_verified') is not True or value.get('operation_id')!=OPERATION_ID
        or value.get('skipped_manual')!=0 or value.get('skipped_pair_excluded')!=0):
        raise ValueError('saved_strong_import_not_confirmed')
    actual={r['anex_hotel_id']:r['catalog_hotel_id'] for r in value.get('link_readback',[])}
    expected={r['anex_hotel_id']:r['catalog_hotel_id'] for r in report['rows']}
    if actual!=expected or len(value['link_readback'])!=25 or any(r['status']!='verified_policy_mapping' for r in value['link_readback']):
        raise ValueError('post_commit_pair_mismatch')


def apply(path,receipt,transport=None):
    report=json.loads(Path(path).read_bytes());payload=delta(report)
    if receipt.is_symlink():raise ValueError('receipt_symlink')
    if receipt.exists():
        old=json.loads(receipt.read_bytes())
        if old.get('state')!='finalized' or old.get('delta_sha256')!=digest(payload) or old.get('result_sha256')!=digest(old.get('result')):
            raise ValueError('reserved_unknown_do_not_replay')
        validate_result(old['result'],report);return {'status':'already_finalized','new_writes':0,'saved_result':old['result']}
    reservation={'state':'reserved','operation_id':OPERATION_ID,'delta_sha256':digest(payload)}
    save(receipt,reservation,exclusive=True)
    if transport is None:
        from anex_search_mapping_import import ssh_import
        result=ssh_import(receipt.with_suffix('.mapping.json'),saved_strong_checkpoint=path)
    else:result=transport(payload)
    save(receipt.with_name(receipt.name+'.outcome.json'),result,exclusive=True);validate_result(result,report)
    save(receipt,dict(reservation,state='finalized',result=result,result_sha256=digest(result)))
    return result


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    for key in ('anex','egypt','turkey','full'):parser.add_argument('--'+key,type=Path)
    parser.add_argument('--report',type=Path,required=True);parser.add_argument('--apply',action='store_true');parser.add_argument('--receipt',type=Path)
    args=parser.parse_args()
    if args.apply:
        if not args.receipt:parser.error('--apply requires durable --receipt')
        result=apply(args.report,args.receipt)
    else:
        paths={k:getattr(args,k) for k in ('anex','egypt','turkey','full')}
        if not all(paths.values()):parser.error('all four pinned archives required')
        result=review(paths);delta(result);save(args.report,result,exclusive=True)
        result={'status':'prepared_not_applied','eligible':len(result['rows']),'examined':result['examined'],'report_sha256':digest(result)}
    print(json.dumps(result,ensure_ascii=False,sort_keys=True))

if __name__=='__main__':main()
