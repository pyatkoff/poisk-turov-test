"""Promote one reviewed pending identities with original evidence retained."""
import argparse,hashlib,json,os
from pathlib import Path
import anex_search3_owner_decisions as owner
from andromeda_anex_saved_audit import analyse
from andromeda_hotel_candidates import save
DIGEST='03a34edf75f895b6ccc4dacd819e75ff213bb50ecbc99fa680751de06f4d6bee'

def main():
    p=argparse.ArgumentParser();p.add_argument('phase',choices=['prepare','apply']);a=p.parse_args()
    dest=Path(os.environ['RUNNER_TEMP'])/'andromeda-identity-accept';dest.mkdir(exist_ok=True)
    if a.phase=='prepare':
        raw=(Path(os.environ['RUNNER_TEMP'])/'saved'/'saved-hotel-evidence.json').read_bytes()
        if hashlib.sha256(raw).hexdigest()!=DIGEST:raise ValueError('saved evidence changed')
        proposals=[r for r in analyse(json.loads(raw))['rows'] if r['proposed_local_id']]
        if {r['external_hotel_id']:r['proposed_local_id'] for r in proposals}!={'5354':1280}:raise ValueError('reviewed pair set changed')
        rows=[]
        for r in proposals:
            old=r['identity'];evidence={'prior_evidence':json.loads(old['evidence_json']),'source':'owner_authorized_saved_anex_correlation_20260909','saved_evidence_sha256':DIGEST,'observed_hotel':r['hotel'],'image_url':r['image_url'],'hotel_url':r['hotel_url'],'anex_candidate_id':r['anex_candidate_id'],'anex_accepted_local_id':r['anex_accepted_local_id'],'target':r['target'],'reason':r['reason']}
            rows.append({'external_hotel_id':r['external_hotel_id'],'local_hotel_id':r['proposed_local_id'],'previous_evidence_sha256':old['evidence_sha256'],'evidence_json':json.dumps(evidence,ensure_ascii=False,sort_keys=True,separators=(',',':'))})
        save(dest/'request.json',{'rows':rows},exclusive=True)
        save(dest/'state.json',{'state':'prepared','request_sha256':hashlib.sha256((dest/'request.json').read_bytes()).hexdigest()},exclusive=True)
        print(json.dumps({'prepared_pairs':[[r['external_hotel_id'],r['local_hotel_id']] for r in rows]}))
    else:
        state=json.loads((dest/'state.json').read_bytes());raw=(dest/'request.json').read_bytes()
        if state['state']!='prepared' or hashlib.sha256(raw).hexdigest()!=state['request_sha256']:raise ValueError('unprepared import')
        save(dest/'state.json',{**state,'state':'inflight'})
        result=owner.ssh_php(Path(__file__).with_suffix('.php').read_text().removeprefix('<?php'),json.loads(raw))
        save(dest/'result.json',result,exclusive=True)
        if result.get('status')!='accepted':raise ValueError('identity promotion not confirmed')
        expected={r['external_hotel_id']:(r['local_hotel_id'],hashlib.sha256(r['evidence_json'].encode()).hexdigest()) for r in json.loads(raw)['rows']}
        if {str(r['external_hotel_id']):(int(r['local_hotel_id']),r['evidence_sha256']) for r in result['rows']}!=expected:raise ValueError('readback mismatch')
        save(dest/'state.json',{**state,'state':'complete','readback_verified':True})
        print(json.dumps(result,ensure_ascii=False))
if __name__=='__main__':main()
