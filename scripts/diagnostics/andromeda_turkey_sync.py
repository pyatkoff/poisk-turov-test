"""One-shot Turkey catalog capture and append-only country identity import."""
import argparse, hashlib, json
from pathlib import Path
from andromeda_catalog_sync import names
from andromeda_hotel_candidates import save

def match(data):
    local=data['local'];catalog=data['catalog'];country=int(data['country_id']);supplier=int(data['supplier_country_id'])
    if local.get('complete') is not True or int(local['country_id'])!=country or int(catalog['params']['STATEINC'])!=supplier:
        raise ValueError('country mismatch')
    hotels={int(h['id']):h for h in local['hotels']}
    if any(int(h['country_id'])!=country for h in hotels.values()):raise ValueError('foreign local hotel')
    index={}
    for identifier,h in hotels.items():
        for key in names(h['name']):index.setdefault(key,set()).add(identifier)
    for alias in local['aliases']:
        identifier=int(alias['hotel_id'])
        if identifier not in hotels:raise ValueError('foreign alias')
        for key in names(alias['alias']):index.setdefault(key,set()).add(identifier)
    rows=[];seen=set()
    for source in catalog['payload']['HOTELS']:
        external=str(source['id'])
        if not external.isdigit() or external in seen:raise ValueError('invalid source key')
        seen.add(external);candidates=set()
        for value in [source['name'],source.get('lName','')]:
            for key in names(value):candidates.update(index.get(key,set()))
        target=None;status='pending';reason='no_unique_country_name'
        if str(source['stateKey'])!=str(supplier):status='conflict';reason='supplier_country_conflict'
        elif len(candidates)==1:target=next(iter(candidates));status='accepted';reason='unique_country_name_or_former_name'
        evidence={'source':source,'candidate_ids':sorted(candidates),'target_name':hotels[target]['name'] if target else None,'reason':reason}
        rows.append({'external_hotel_id':external,'local_hotel_id':target,'decision_status':status,
            'evidence_json':json.dumps(evidence,ensure_ascii=False,sort_keys=True,separators=(',',':'))})
    if not rows:raise ValueError('empty source catalog')
    return {'operation':'import','catalog_sha256':data['catalog_sha256'],'rows':rows}

def main():
    parser=argparse.ArgumentParser();parser.add_argument('phase',choices=['prepare','apply'])
    parser.add_argument('--directory',type=Path,required=True);args=parser.parse_args()
    root=args.directory;root.mkdir(exist_ok=True)
    import anex_search3_owner_decisions as owner
    php=Path(__file__).with_suffix('.php').read_text().removeprefix('<?php')
    if args.phase=='prepare':
        save(root/'capture-reservation.json',{'state':'inflight'},exclusive=True)
        data=owner.ssh_php(php,{'operation':'capture'},maximum_bytes=8000000)
        save(root/'capture.json',data,exclusive=True)
        if data.get('status')!='captured':raise ValueError('capture stopped; inspect without replay')
        request=match(data);save(root/'import-request.json',request,exclusive=True)
        print(json.dumps({'status':'prepared','country_id':data['country_id'],'supplier_country_id':data['supplier_country_id'],
            'source_hotels':len(request['rows']),'local_hotels':len(data['local']['hotels']),
            'counts':{s:sum(r['decision_status']==s for r in request['rows']) for s in ['accepted','pending','conflict']}}))
    else:
        request=json.loads((root/'import-request.json').read_bytes())
        save(root/'import-reservation.json',{'state':'inflight','request_sha256':hashlib.sha256((root/'import-request.json').read_bytes()).hexdigest()},exclusive=True)
        result=owner.ssh_php(php,request,maximum_bytes=8000000)
        save(root/'result.json',result,exclusive=True);print(json.dumps(result))
        if result.get('status')!='imported' or result.get('readback_verified') is not True:raise ValueError('import not confirmed; no replay')
if __name__=='__main__':main()
