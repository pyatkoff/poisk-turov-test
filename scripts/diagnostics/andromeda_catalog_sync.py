"""Compare the complete saved Egypt catalog and persist explicit source identities."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import unicodedata
from andromeda_hotel_candidates import save

CATALOG_SHA = '01030bb9e23e0c87f8bed7c50628c8f56243b89f3e55a24151430766c1576641'


def names(value):
    # Parenthesized EX names are alternate names, not an excuse to remove BEACH/PALACE.
    value = unicodedata.normalize('NFKC', value).casefold().replace('ё','е').replace('&',' and ')
    parts = re.split(r'\(\s*(?:ex[.\s:-]*|быв[.\s:-]*)', value)
    result = set()
    for part in parts:
        words = re.findall(r'[^\W_]+', part.split(')')[0], re.UNICODE)
        words = [w for w in words if w not in {'hotel','hotels','resort','resorts','spa','the','and','отель'}]
        if len(''.join(words)) >= 5:
            result.add(' '.join(words))
    return result


def match(catalog, local, reviewed):
    if catalog['params'] != {'TOWNFROMINC':1,'STATEINC':3} or len(catalog['payload']['HOTELS']) != 905:
        raise ValueError('unexpected source catalog')
    if local.get('complete') is not True or local.get('country_id') != 1:
        raise ValueError('incomplete local catalog')
    hotels={int(h['id']):h for h in local['hotels']}
    aliases={i:set(names(h['name'])) for i,h in hotels.items()}
    for a in local['aliases']:
        aliases[int(a['hotel_id'])].update(names(a['alias']))
    index={}
    for identifier,values in aliases.items():
        for name in values:index.setdefault(name,set()).add(identifier)
    approved={r['external_hotel_id']:r['proposed_catalog_hotel_id'] for r in reviewed['rows'] if r['decision_status']=='proposed'}
    rows=[]
    for source in catalog['payload']['HOTELS']:
        external=str(source['id']); status='pending'; target=None; reason='no_unique_name'
        candidates=set()
        for name in (source['name'],source.get('lName','')):
            for key in names(name):candidates.update(index.get(key,set()))
        if str(source['stateKey'])!='3' or external=='2000073714':
            status='conflict';reason='country_or_known_catalog_offer_conflict'
        elif external in approved:
            target=approved[external]
            if target not in hotels:raise ValueError('reviewed local hotel disappeared')
            status='accepted';reason='owner_authorized_reviewed_identity'
        elif len(candidates)==1:
            target=next(iter(candidates));status='accepted';reason='unique_country_name_or_former_name'
        evidence={'source':source,'candidate_ids':sorted(candidates),'target_name':hotels[target]['name'] if target else None,'reason':reason}
        rows.append({'external_hotel_id':external,'local_hotel_id':target,'decision_status':status,
                     'evidence_json':json.dumps(evidence,ensure_ascii=False,sort_keys=True,separators=(',',':'))})
    return {'operation':'import','catalog_sha256':CATALOG_SHA,'rows':rows}


def main():
    parser=argparse.ArgumentParser();parser.add_argument('phase',choices=['prepare','apply'])
    parser.add_argument('--directory',type=Path,required=True);args=parser.parse_args();root=args.directory
    import anex_search3_owner_decisions as owner
    php=Path(__file__).with_suffix('.php').read_text().removeprefix('<?php')
    state_path=root/'sync-state.json'
    if args.phase=='prepare':
        if state_path.exists():raise ValueError('existing sync state; no replay')
        save(state_path,{'state':'export_inflight'},exclusive=True)
        local=owner.ssh_php(php,{'operation':'export'},maximum_bytes=4000000)
        if local.get('status')!='ok':raise ValueError('local export failed')
        save(root/'local-catalog.json',local,exclusive=True)
        raw=(root/'all.json').read_bytes()
        if hashlib.sha256(raw).hexdigest()!=CATALOG_SHA:raise ValueError('source digest changed')
        review_path=Path(__file__).resolve().parents[2]/'reports/andromeda-hotel-shortlist-20260909.json'
        request=match(json.loads(raw),local,json.loads(review_path.read_bytes()))
        save(root/'import-request.json',request,exclusive=True)
        save(state_path,{'state':'prepared','request_sha256':hashlib.sha256((root/'import-request.json').read_bytes()).hexdigest()})
        print(json.dumps({'local_hotels':len(local['hotels']),'source_hotels':905,'counts':{s:sum(r['decision_status']==s for r in request['rows']) for s in ['accepted','pending','conflict']}}))
    else:
        state=json.loads(state_path.read_bytes());raw=(root/'import-request.json').read_bytes()
        if state['state']!='prepared' or hashlib.sha256(raw).hexdigest()!=state['request_sha256']:raise ValueError('sync not prepared')
        save(state_path,{**state,'state':'import_inflight'})
        request=json.loads(raw);result=owner.ssh_php(php,request,maximum_bytes=4000000)
        save(root/'import-result.json',result,exclusive=True)
        actual={r['external_hotel_id']:(str(r['local_hotel_id']),r['decision_status'],r['evidence_sha256']) for r in result.get('rows',[])}
        expected={r['external_hotel_id']:(str(r['local_hotel_id']),r['decision_status'],hashlib.sha256(r['evidence_json'].encode()).hexdigest()) for r in request['rows']}
        # PDO numeric IDs may arrive as strings; normalize null identically above.
        if result.get('status')!='imported' or actual!=expected:raise ValueError('import readback mismatch; preserve state')
        save(state_path,{**state,'state':'completed','counts':result['counts'],'readback_verified':True})
        print(json.dumps({'state':'completed','counts':result['counts'],'inserted':result['inserted'],'readback_verified':True}))


if __name__=='__main__':main()
