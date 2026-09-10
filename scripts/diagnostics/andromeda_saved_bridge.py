#!/usr/bin/env python3
"""Promote only the reviewed Maldives delta using accepted ANEX identity and exact atoll evidence."""
from __future__ import annotations
import argparse
import hashlib
import json
from pathlib import Path
import re
import unicodedata
from zipfile import ZipFile

OPERATION_ID = 'andromeda-1759-maldives-bridge61-20260910-v1'
PENDING_ZIP_SHA = 'ac0a474ede6a56bc93c7f96c0283b26206e6e6f6f2cb652f90351d47fda25457'
ANEX_ZIP_SHA = '770d183c60ccff1537f1ce33879825f8fae6d40c68e882010d9bde8c49001021'
REQUEST_SHA = '941ad3dccbdd4d4c374bcf572016cc1234a535bfe56a5bcacbf1df99e8eac2b4'

def canonical(value):
    return json.dumps(value,ensure_ascii=False,sort_keys=True,separators=(',',':'),allow_nan=False).encode()

def digest(value):
    return hashlib.sha256(canonical(value)).hexdigest()

def norm(value):
    value=unicodedata.normalize('NFKD',str(value or '')).casefold().replace('ё','е').replace('ı','i')
    value=''.join(c for c in value if not unicodedata.combining(c))
    return ' '.join(re.findall(r'[^\W_]+',value,re.UNICODE))

def name(value):
    value=re.sub(r'\s*\(\s*(?:ex|ех)\s*\.?\s+[^()]+\)\s*$','',str(value or ''),flags=re.I)
    return ' '.join(sorted(w for w in norm(value).split() if w!='hotel'))

def load_zip(path,expected):
    if hashlib.sha256(path.read_bytes()).hexdigest()!=expected:
        raise ValueError('unreviewed_input_archive')
    return ZipFile(path)

def request(pending_zip:Path,anex_zip:Path):
    with load_zip(pending_zip,PENDING_ZIP_SHA) as z:
        saved=json.loads(z.read('maldives.json'))
    with load_zip(anex_zip,ANEX_ZIP_SHA) as z:
        catalog=json.loads(z.read('anex-hotel-catalog-match.json'))['matches']
        accepted={r['anex_hotel_id']:r['catalog_hotel_id'] for r in json.loads(z.read('anex-search-mappings.json'))['rows']}
    if saved['country_id']!=8 or saved['supplier_country_id']!=73 or len(saved['rows'])!=327:
        raise ValueError('saved_country_scope_changed')
    towns={str(t['id']):t for t in saved['towns']}
    index={}
    for r in catalog:
        if r['external_id'] not in accepted or norm(r['country'])!=norm('Мальдивы'):
            continue
        for key in {name(r['name']),name(r['alternate_name'])}-{''}:
            index.setdefault(key,{})[r['external_id']]=r
    rows=[]
    for item in saved['rows']:
        source=item['evidence']['source'];targets=item['targets']
        if item['evidence']['reason']!='geography_unknown' or len(targets)!=1:
            continue
        target=targets[0]
        if norm(target['region_name'])!=norm('Мальдивы') or norm(target['subregion_name']):
            continue
        candidates={}
        for key in {name(source.get('name')),name(source.get('lName'))}-{''}:
            candidates.update(index.get(key,{}))
        if {accepted[a] for a in candidates}!={int(target['id'])}:
            continue
        town=towns.get(str(source.get('townKey')))
        if not town or str(town.get('state'))!='73' or norm(town['name'])!=norm(source.get('town')):
            continue
        bridges=[r for r in candidates.values() if norm(r['town'])==norm(source.get('town'))]
        if not bridges:
            continue
        if hashlib.sha256(item['identity']['evidence_json'].encode()).hexdigest()!=item['evidence_sha256']:
            raise ValueError('original_evidence_mismatch')
        rows.append({'external_hotel_id':str(source['id']),'local_hotel_id':int(target['id']),
            'expected_evidence_sha256':item['evidence_sha256'],'target_name':target['name'],
            'source':source,'official_town':town,
            'anex_bridges':[{'id':r['external_id'],'name':r['name'],'alternate_name':r['alternate_name'],
                'town':r['town'],'country':r['country'],'catalog_hotel_id':accepted[r['external_id']],
                'source_record_sha256':digest(r)} for r in sorted(bridges,key=lambda r:r['external_id'])]})
    rows.sort(key=lambda r:int(r['external_hotel_id']))
    if len(rows)!=61 or len({r['external_hotel_id'] for r in rows})!=61 or len({r['local_hotel_id'] for r in rows})!=61:
        raise ValueError('reviewed_delta_changed')
    payload={'operation_id':OPERATION_ID,'country_id':8,'supplier_country_id':73,
        'saved_capture_sha256':saved['capture_sha256'],'saved_plan_sha256':saved['plan_sha256'],
        'catalog_sha256':saved['catalog_sha256'],'source_pending_sha256':digest(saved),
        'anex_archive_sha256':ANEX_ZIP_SHA,'supplier_calls':0,'rows':rows}
    if digest(payload)!=REQUEST_SHA:
        raise ValueError('reviewed_request_changed')
    return payload

def validate_result(value,payload):
    if (value.get('status')!='accepted' or value.get('operation_id')!=OPERATION_ID
        or value.get('request_sha256')!=digest(payload) or value.get('updated')!=61
        or value.get('readback_verified') is not True or value.get('other_identities_unchanged') is not True
        or value.get('supplier_calls')!=0 or len(value.get('rows',[]))!=61):
        raise ValueError('live_result_unconfirmed')
    expected={r['external_hotel_id']:r['local_hotel_id'] for r in payload['rows']}
    if {str(r['external_hotel_id']):int(r['local_hotel_id']) for r in value['rows']}!=expected:
        raise ValueError('live_pair_mismatch')
    if any(r.get('decision_status')!='accepted' or not re.fullmatch('[0-9a-f]{64}',r.get('evidence_sha256','')) for r in value['rows']):
        raise ValueError('live_evidence_unconfirmed')

def apply(payload,receipt:Path,transport=None):
    from anex_tourvisor_link_import import save
    if receipt.is_symlink():
        raise ValueError('receipt_symlink')
    h=digest(payload)
    if receipt.exists():
        prior=json.loads(receipt.read_bytes())
        if prior.get('state')!='finalized' or prior.get('request_sha256')!=h or prior.get('result_sha256')!=digest(prior.get('result')):
            raise ValueError('reserved_unknown_do_not_replay')
        validate_result(prior['result'],payload)
        return {'status':'already_finalized','new_writes':0,'saved_result':prior['result']}
    save(receipt,{'state':'reserved','operation_id':OPERATION_ID,'request_sha256':h},exclusive=True)
    if transport is None:
        from anex_search3_owner_decisions import ssh_php
        root=Path(__file__).resolve().parents[2]
        source=(root/'app/integrations/anex-search-mapping-registry.php').read_text().removeprefix('<?php')
        source+="\ndefine('CE_LIBRARY_ONLY',true);\n"+Path(__file__).with_name('andromeda_country_expansion.php').read_text().removeprefix('<?php')
        source+='\n'+Path(__file__).with_suffix('.php').read_text().removeprefix('<?php')
        result=ssh_php(source,payload,maximum_bytes=65536)
    else:
        result=transport(payload)
    save(receipt.with_name(receipt.name+'.outcome.json'),result,exclusive=True)
    validate_result(result,payload)
    save(receipt,{'state':'finalized','operation_id':OPERATION_ID,'request_sha256':h,'result_sha256':digest(result),'result':result})
    return result

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--pending',required=True,type=Path);p.add_argument('--anex',required=True,type=Path)
    p.add_argument('--output',required=True,type=Path);p.add_argument('--apply',action='store_true');p.add_argument('--receipt',type=Path)
    args=p.parse_args()
    if args.apply and args.receipt is None:p.error('--apply requires a durable --receipt')
    payload=request(args.pending,args.anex)
    result=apply(payload,args.receipt) if args.apply else {'status':'prepared_not_applied','request':payload}
    args.output.write_text(json.dumps(result,ensure_ascii=False,sort_keys=True,indent=2)+'\n')
    print(json.dumps(result if args.apply else {'status':result['status'],'rows':len(payload['rows']),'request_sha256':digest(payload)},ensure_ascii=False))

if __name__=='__main__':main()
