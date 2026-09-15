#!/usr/bin/env python3
"""Offline residual name dossiers only; no network, DB, or acceptance authority."""
import json,zipfile,re,unicodedata,collections,difflib,hashlib,sys,math
from pathlib import Path
raw=Path(sys.argv[1]).read_bytes()
assert hashlib.sha256(raw).hexdigest()=='6da39881ac362b1ad433bc6613baf6f97bdc6c396d89ffa49eec517e5edaa2fc'
z=zipfile.ZipFile(sys.argv[1]);result_raw=z.read('server/result.json');r=json.loads(result_raw);receipt=json.loads(z.read('server/receipt.json'))
assert hashlib.sha256(result_raw).hexdigest()==receipt['result_sha256']=='f135bb42d40b0f3134309b24f5bdffcca8f2e62fa9511d36dfa14c04d96ccd1b'
assert r['state']==receipt['state']=='completed_read_only' and receipt['readback_verified']
assert z.read('reservation.json')==z.read('server/reservation.json')
assert r['db_writes']==r['mapping_writes']==r['supplier_calls']==0
drop={'hotel','hotels','resort','resorts','spa','отель','the','and','by'}
def forms(s):
 s=unicodedata.normalize('NFKC',s).casefold()
 return [' '.join(t for t in re.findall(r'[^\W_]+',x) if t not in drop) for x in re.split(r'\b(?:ex|former|formerly)\b\.?',s)]
formsby={};idx=collections.defaultdict(set)
for k,ls in r['local_alias_forms'].items():
 cid=r['local_hotels'][k]['country_id'];ns=set(n for s in ls for n in forms(s) if n)
 formsby[k]=ns
 for n in ns:
  for t in n.split():idx[cid,t].add(k)
rows=[x for ls in r['routes'].values() for x in ls];found=[]
qual={'annex','beach','garden','gardens','north','south','mountain','palace','deluxe','junior','posh','family'}
for x in rows:
 src=set(n for s in x['names'] for n in forms(s) if len(n.split())>=2);ids=set()
 for s in src:
  for t in s.split():ids.update(idx[x['country_id'],t])
 ranked=[]
 for k in ids:
  best=0; matched=None
  for a in sorted(src):
   for b in sorted(formsby[k]):
    ta=set(a.split());tb=set(b.split())
    if ta&qual!=tb&qual or {t for t in ta if any(c.isdigit() for c in t)}!={t for t in tb if any(c.isdigit() for c in t)}:continue
    score=difflib.SequenceMatcher(None,a,b,autojunk=False).ratio()
    if score>best:best=score;matched=[a,b]
  if best:ranked.append((best,k,matched))
 ranked.sort(reverse=True)
 if not ranked:continue
 best=ranked[0];second=ranked[1][0] if len(ranked)>1 else 0
 if best[0]>=.90 and best[0]-second>=.12:
  found.append({'source':x,'local_id':int(best[1]),'local':r['local_hotels'][best[1]],'matched_forms':best[2],'score':best[0],'margin':best[0]-second,'runner_up_score':second,'auto_accept':False,'state':'name_candidate_only_needs_full_CURRENT_guards'})

# A name rank is an evidence pointer, never acceptance or a provider bridge.
for x in found:
    src=x['source'];h=x['local'];holds=['primary_identity_revalidation_required','manual_exclusion_and_occupancy_not_read','CURRENT_transaction_revalidation_required']
    anchors=src['geo_anchors']
    if not anchors: holds.append('no_independent_subcountry_geography')
    else:
        for a in anchors:
            field='region_id' if a['scope']=='region' else 'subregion_id'
            if int(h.get(field) or 0)!=int(a['scope_id']):holds.append('geo_consensus_target_conflict')
    distances=[]
    for point in src['points']:
        # Preserve unfamiliar point shapes as missing validation, never ignore them.
        if not isinstance(point,dict) or not all(k in point for k in ['latitude','longitude']):
            holds.append('saved_coordinate_shape_requires_guard');continue
        if h.get('latitude') is None or h.get('longitude') is None:
            holds.append('target_coordinate_missing');continue
        lat1,lon1,lat2,lon2=map(math.radians,[float(point['latitude']),float(point['longitude']),float(h['latitude']),float(h['longitude'])])
        v=math.sin((lat2-lat1)/2)**2+math.cos(lat1)*math.cos(lat2)*math.sin((lon2-lon1)/2)**2
        distances.append(6371*2*math.asin(math.sqrt(min(1,max(0,v)))))
    if distances and max(distances)>5:holds.append('coordinate_conflict_gt5km')
    x['coordinate_distances_km']=distances
    x['holds']=sorted(set(holds))
    x['rank_scope']='country-specific exact-token-overlap shortlist; margin is NOT exhaustive country-wide uniqueness proof'
    x['name_comparison']='literal normalized or SequenceMatcher spelling proximity; not production matcher'
assert len(rows)==1399 and len(found)==156
assert not set(r['excluded_claim_ids']).intersection(x['external_hotel_id'] for x in rows)
counts=collections.Counter(h for x in found for h in x['holds'])
report={'schema':'hotel-match-core8-residual-name-dossier/1','state':'prepared_not_safe','auto_accept':False,'safe_mappings':0,'database_writes':0,'supplier_calls':0,'tourvisor_calls':0,'source_operation':r['operation_id'],'source_sha':r['source_sha'],'run_id':34965710062,'artifact_id':10394524643,'artifact_sha256':hashlib.sha256(raw).hexdigest(),'result_sha256':receipt['result_sha256'],'excluded_claim_ids':r['excluded_claim_ids'],'excluded_current_ids':r['excluded_current_ids'],'examined':len(rows),'name_candidates_not_safe':len(found),'exact_name_candidates_not_safe':sum(x['score']==1 for x in found),'candidate_countries':dict(collections.Counter(x['source']['country_id'] for x in found)),'hold_counts_overlapping':dict(counts),'candidates':found,'full_residual':rows,'ranking_limits':['Candidate retrieval requires at least one exact substantive token; not exhaustive fuzzy search.','Comparison against saved aliases/former names may identify a different property; independent primary identity and geography mandatory.','Shortlist margin must not authorize any mapping.','This snapshot is immutable historical evidence at every future apply; revalidate CURRENT in transaction.'],'next_write_boundary':'separate aggregate proven delta claim; CURRENT name/aliases/geography/all coordinates/manual/conflict/exclusion/occupancy revalidation; COMMIT and per-row readback'}
Path(sys.argv[2]).write_text(json.dumps(report,ensure_ascii=False,sort_keys=True,separators=(',',':'))+'\n')
print(json.dumps({k:report[k] for k in ['examined','name_candidates_not_safe','exact_name_candidates_not_safe','candidate_countries','hold_counts_overlapping','safe_mappings']}))
