#!/usr/bin/env python3
"""Offline exact-artifact proof assembly; no network or database capabilities."""
import argparse
import hashlib
import json
import os
import re
import zipfile
from pathlib import Path
from urllib.parse import parse_qs, urlsplit

OP = 'hotel-match-fruit-white-canonical-accept-1971-20260920-v1'
ARCHIVES = {
    'review': ('9135d5848c8657a9dca6903a4e440b1b615c8033ee84fd1e4e19f8390bedc10b', 10599469743),
    'tv': ('bc9157f7ec36e2e3f556a90077613a3cb33682263845d4e3b6f68f8bf283a7c3', 10591695073),
    'samo': ('5a0a7cec348f1239c01ca8ce286a4f93212edc62039477985bd63d01a160a478', 10591406713),
}
REVIEW = '0d9011b74d06c986bb55cae28b3152d20a0355b7a14a18a5edd11d0b8bc62bcd'
CATALOG = 'b9b27238c1c980bbf5015ecf6f5c46bacada55251fcd0d92d373fa11bcd5e056'
SPECS = [
    (49104, '2000034436', 28626, '41074', '11077', ['-1575330','41074'], [31,32]),
    (69340, '2000063032', 29125, '41080', '46562', ['-1574845','41080'], [55,57]),
]


def require(value, reason):
    if not value:
        raise ValueError(reason)


def digest(value):
    return hashlib.sha256(value).hexdigest()


def encode(value):
    return (json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':'))+'\n').encode()


def read(z, name):
    info=z.getinfo(name)
    require(info.file_size < 8_000_000 and not info.is_dir(), 'archive_entry_bound')
    return z.read(name)


def load(z, name):
    return json.loads(read(z, name))


def tv_proof(z, index, tv, op, tokens):
    edge_name=f'server/edge-{index:03d}.json'
    response_name=f'server/response-{index:03d}.json'
    request_name=f'server/request-{index:03d}.json'
    edge=load(z,edge_name); response=load(z,response_name); request=load(z,request_name)
    r=response['data']; link=r['operatorLink']; query=parse_qs(urlsplit(link).query,keep_blank_values=True)
    require(response['http_status']==200 and edge['state']=='detail_identity_verified', 'tv_response_state')
    require(edge['tv_hotel_id']==edge['returned_tv_hotel_id']==r['hotel']['id']==tv, 'tv_hotel_binding')
    require(edge['operator_id']==edge['returned_operator_id']==r['operator']['id']==op, 'tv_operator_binding')
    require(edge['tour_id']==edge['returned_tour_id']==r['id'] and request['path']=='/tours/'+r['id'], 'tv_tour_binding')
    require(edge['operator_link']==link and digest(link.encode())==edge['operator_link_sha256'], 'tv_link_binding')
    require(edge['raw_identity_tokens']==tokens and edge['raw_identity_values']==[','.join(tokens)], 'all_tokens_retained')
    require(r['date']=='01.10.2026' and r['nights']==6 and r['adults']==2 and r['childs']==0 and r['departure']['id']==1, 'tv_context')
    require(r['hotel']['country']=={'id':41,'name':'Танзания'} and r['hotel']['region']['name']=='Занзибар', 'tv_country')
    if op==13:
        require(urlsplit(link).hostname=='agent.anextour.ru' and query.get('HOTELLIST')==[','.join(tokens)] and r['operator']['name']=='Anex', 'anex_full_signed_link')
    else:
        require(urlsplit(link).hostname=='searchtour.intourist.ru' and query.get('HOTELS')==tokens and len(tokens)==1 and r['operator']['name']=='Интурист', 'intourist_single_link')
    return {'edge':edge,'response':response,'request':request,'response_file':response_name,
            'response_file_sha256':digest(read(z,response_name)),
            'role':'corroboration_signed_list_semantics_unresolved' if op==13 else 'unambiguous_operator_native_anchor'}


def assemble(paths):
    zs={}
    for key,(sha,_) in ARCHIVES.items():
        require(digest(paths[key].read_bytes())==sha, 'archive_digest_'+key)
        zs[key]=zipfile.ZipFile(paths[key])
    try:
        review=load(zs['review'],'server/result.json')
        require(digest(read(zs['review'],'server/result.json'))==REVIEW and load(zs['review'],'server/receipt.json')['result_sha256']==REVIEW, 'review_receipt')
        require(review['state']=='completed_read_only' and review['provider_calls']==review['mapping_writes']==0, 'review_state')
        catalog_name='server/payload/v1/raw-catalog-b897da4c1b.json'
        require(digest(read(zs['samo'],catalog_name))==CATALOG, 'catalog_digest')
        catalog=load(zs['samo'],catalog_name)
        raw_pages=[]
        for name in sorted(zs['samo'].namelist()):
            if re.search(r'/raw-price-[^/]+\.json$', name):
                raw_pages.append((name,digest(read(zs['samo'],name)),load(zs['samo'],name)))
        output={'operation':OP,'provenance':{k:{'artifact_id':i,'zip_sha256':s} for k,(s,i) in ARCHIVES.items()},
                'review_result_sha256':REVIEW,'catalog_sha256':CATALOG,'pairs':[],
                'provider_calls':0,'max_writes':2,'mapping_namespace':'andromeda_catalog',
                'anex_mapping_writes':0,'historical_identity_evidence_not_current_price':True,
                'auto_review_snapshot':review['current_auto_review_rows']}
        for tv,samo,old,anex,it,tokens,indices in SPECS:
            dossier=next(x for x in review['dossiers'] if x['tv_hotel_id']==tv)
            source=[x for x in catalog['HOTELS'] if str(x['id'])==samo]
            require(len(source)==1 and source[0]==dossier['saved_support']['source_catalog']['hotel'], 'canonical_catalog_binding')
            target=next(x for x in review['related_local_rows'] if x['id']==tv)
            old_row=next(x for x in review['current_native_catalog_rows'] if x['anex_hotel_id']==old)
            old_map=next(x for x in review['current_mapping_rows'] if x['anex_hotel_id']==old)
            require(review['effective_anex_targets'][str(old)]==tv and review['effective_anex_targets'][anex] is None, 'no_anex_id_equation')
            require(not review['current_identity_rows'] and not review['current_manual_rows'] and not review['current_exclusion_rows'], 'review_guards')
            proofs=[]
            for tv_op,samo_op,native,index,all_tokens in [(13,5,anex,indices[0],tokens),(43,342,it,indices[1],[it])]:
                observed=[]
                for name,sha,page in raw_pages:
                    for row_index,row in enumerate(page['PRICES']):
                        original=row.get('original') or {}
                        # A competing canonical ID for the same qualified native ID is a hold.
                        if str(row.get('operatorKey'))==str(samo_op) and str(original.get('hotelKey'))==native:
                            require(str(row['hotelKey'])==samo and row.get('isOperatorHotelKey')==0, 'native_namespace_conflict')
                        if str(row.get('hotelKey'))==samo and str(row.get('operatorKey'))==str(samo_op):
                            require(row.get('isOperatorHotelKey')==0 and str(original.get('hotelKey'))==native, 'canonical_native_conflict')
                            require(row['checkIn']=='01.10.2026' and str(row['nights'])=='6' and str(row['adult'])=='2' and str(row['child'])=='0', 'samo_context')
                            require(row['town']==source[0]['town'] and str(row['samoTownKey'])==str(source[0]['townKey']), 'samo_concrete_town')
                            require(row['operator']==('Anex Tour' if samo_op==5 else 'Intourist'), 'samo_operator_name')
                            observed.append({'file':name,'file_sha256':sha,'row_index':row_index,'row':row})
                require(observed, 'missing_raw_native_proof')
                proofs.append({'tv_operator_id':tv_op,'samo_operator_id':samo_op,'native_operator_hotel_id':native,
                               'tv':tv_proof(zs['tv'],index,tv,tv_op,all_tokens),'samo':observed[0],
                               'all_saved_matching_row_refs':[{'file':v['file'],'sha256':v['file_sha256'],'row_index':v['row_index']} for v in observed]})
            extra=[]
            for name in zs['tv'].namelist():
                if re.fullmatch(r'server/edge-\d{3}\.json',name):
                    e=load(zs['tv'],name)
                    if e.get('tv_hotel_id')==tv and e.get('operator_id') not in (13,43):
                        extra.append({'edge':e,'state':'saved_additional_missing_operator_edge_not_accepted'})
            fingerprints={}
            for _,_,page in raw_pages:
                for row in page['PRICES']:
                    if str(row.get('hotelKey'))==samo:
                        key=str(row['operatorKey']); value={'operator':row['operator'],'original_hotel_id':str(row.get('original',{}).get('hotelKey')),'isOperatorHotelKey':row.get('isOperatorHotelKey')}
                        require(key not in fingerprints or fingerprints[key]==value,'saved_fingerprint_conflict')
                        fingerprints[key]=value
            output['pairs'].append({'tv_hotel_id':tv,'samo_hotel_id':samo,'source_catalog':source[0],
                'target_snapshot':target,'old_accepted_anex_id':old,'old_anex_catalog_snapshot':old_row,'old_anex_mapping_snapshot':old_map,
                'new_anex_id_not_materialized':anex,'full_operator_proofs':proofs,'all_saved_samo_operator_fingerprints':fingerprints,
                'additional_saved_tv_edges':extra,
                'pair_local_full_raw_names':list(dict.fromkeys([target['name'],source[0]['name'],source[0]['lName']]+[p['samo']['row']['hotel'] for p in proofs])),
                'acceptance_basis':'unambiguous_Intourist_native_plus_explicit_canonical_namespace_with_ANEX_corroboration',
                'anex_signed_tokens_semantics':'unresolved_preserved_not_accepted_as_alias',
                'snapshot_holds':[], 'safe_without_fresh_transaction':False})
        return output
    finally:
        for z in zs.values(): z.close()


def main():
    p=argparse.ArgumentParser();p.add_argument('--input-dir',type=Path,required=True);p.add_argument('--output',type=Path,required=True)
    args=p.parse_args();value=assemble({k:args.input_dir/(k+'.zip') for k in ARCHIVES});body=encode(value)
    with args.output.open('xb') as f:
        f.write(body);f.flush();os.fsync(f.fileno())
    require(args.output.read_bytes()==body,'output_readback')
    print('CANONICAL2_INPUT',digest(body),len(value['pairs']))


if __name__=='__main__':
    main()
