"""Offline contradiction screen for the three retained175 candidates with exact TV proof."""
import argparse, hashlib, json, zipfile
from pathlib import Path

REPORT_SHA='01ef024f859be9464a12ffcdd196d8b12450d2980d4184c2a056548e578d738d'
PINS={
'direct27':('c55fe548c7d223462f5c251137559108765eb4b877ff47a26371e90aac5aaf5f','result.json','7f5855aac069868f7b5e06264de8454cac2d571f65fd89e86e3d695d57695e8a','receipt.json','cdb0325ea1a3299f06fc54174c09b686e5073df1437e8491c2621ab10a4c70d9'),
'canonical':('35e79211da22b9d8f9a417d6ee4c27954d03e83ef89e471b3762fef8969e7aea','server/result.json','ce3876480bd8dd1899192c0ff1c432f5925c802492aff61d5caada1483090d94','server/receipt.json','a353decdc04e120391cc0a33abe781ea963a977c62446bac85f5890052c1e269'),
'anex':('b847b82f3a6ce6feb4110d42d12396631179dd0e5e4ce7bf9d48da55f0df651b','server/result.json','d84dccf6d242088683f1b81f6bcc43b933fe2d85c1dda047578514fe9c1e3501','server/receipt.json','a0f9f7e83603e06b2dccbf40128ae6622cd829eadf41656f175fef74c3551aa1')}

def need(v,m):
    if not v: raise ValueError(m)
def sh(b): return hashlib.sha256(b).hexdigest()
def load_report(p):
    b=Path(p).read_bytes(); need(sh(b)==REPORT_SHA,'report_hash'); x=json.loads(b)
    need(x.get('input_count')==175 and x.get('safe_to_write_now') is False,'report_shape'); return x
def load_zip(p,k):
    pin=PINS[k]; b=Path(p).read_bytes(); need(sh(b)==pin[0],'zip_hash')
    with zipfile.ZipFile(p) as z:
        rb=z.read(pin[1]); qb=z.read(pin[3]); need(sh(rb)==pin[2] and sh(qb)==pin[4],'member_hash')
        r=json.loads(rb); q=json.loads(qb)
    need(q.get('result_sha256')==pin[2] and q.get('operation')==r.get('operation'),'receipt')
    need(r.get('database_writes',0)==0 and r.get('mapping_writes',0)==0,'writes'); return r
def indexes(d,c,a):
    return ({(int(x['tv_hotel_id']),str(x['samo_hotel_id'])):x for x in d.get('rows',[])},
            {(int(x['tv_hotel_id']),str(x['external_hotel_id'])):x for x in c.get('new_candidate_plans',[])},
            {int(x['local_hotel_id']):x for x in a.get('holds',[]) if x.get('reason')=='coordinate_conflict_gt5km'})
def assess(x,idx):
    local=int(x['local_hotel_id']); cat=str(x['andromeda_catalog_id']); direct,canon,anex=idx; reasons=[]; evidence=[]
    d=direct.get((local,cat))
    if d:
        evidence.append({'kind':'historical_direct27_current','classification':d.get('classification'),'current_samo_identity':d.get('current_samo_identity',[])})
        if d.get('classification') not in ('current_candidate_pending_or_unassigned','samo_identity_missing_current'): reasons.append('historical_current_'+str(d.get('classification')))
    c=canon.get((local,cat))
    if c:
        evidence.append({'kind':'historical_canonical_current','reasons':c.get('reasons',[]),'source_star':(c.get('source') or {}).get('star'),'target_category':(c.get('target') or {}).get('category')})
        reasons += ['historical_canonical_'+str(v) for v in c.get('reasons',[])]
    a=anex.get(local)
    if a and x.get('direct_anex_support_not_authority')=='support_equal':
        evidence.append({'kind':'historical_direct_anex_detail_hold','native_anex_hotel_id':str(a.get('native_anex_hotel_id')),'reason':a.get('reason'),'distance_m':a.get('distance_m')})
        reasons.append('historical_direct_anex_'+str(a.get('reason')))
    reasons=sorted(set(reasons))
    return {'local_hotel_id':local,'hotel_name':x.get('hotel_name'),'andromeda_catalog_id':cat,'candidate_names':x.get('candidate_names',[]),'independent_tv_lane_count':x.get('independent_tv_lane_count'),'historical_status':'hold' if reasons else 'no_historical_veto_found','historical_hold_reasons':reasons,'historical_evidence':evidence,'next_gate':'fresh_CURRENT_manual_occupancy_evidence_review','safe_to_write_now':False}
def analyse(report,d,c,a):
    src=report.get('candidates_with_proven_tv_lanes',[]); need(len(src)==3,'candidate_count'); idx=indexes(d,c,a); rows=sorted((assess(x,idx) for x in src),key=lambda x:x['local_hotel_id']); holds=sum(x['historical_status']=='hold' for x in rows)
    return {'schema':'match_retained175_history_crosscheck_v1','mode':'offline_historical_contradiction_screen_only','source_report_sha256':REPORT_SHA,'historical_input_zip_pins':{k:v[0] for k,v in PINS.items()},'proven_candidates_input':3,'historical_hold_count':holds,'historical_no_veto_count':3-holds,'rows':rows,'fresh_current_validation_performed':False,'provider_http_calls':0,'database_reads':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False}
def main():
    p=argparse.ArgumentParser();
    for n in ('report','direct27','canonical','anex','output'): p.add_argument('--'+n,required=True)
    a=p.parse_args(); out=analyse(load_report(a.report),load_zip(a.direct27,'direct27'),load_zip(a.canonical,'canonical'),load_zip(a.anex,'anex'))
    raw=(json.dumps(out,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode(); Path(a.output).write_bytes(raw); print(json.dumps({'candidates':3,'holds':out['historical_hold_count'],'report_sha256':sh(raw),'mapping_writes':0},sort_keys=True))
if __name__=='__main__': main()
