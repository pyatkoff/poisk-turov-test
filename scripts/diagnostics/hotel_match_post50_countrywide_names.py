import json,zipfile,re,unicodedata,collections,difflib,time,numpy as np
from pathlib import Path
import hashlib,sys
drop={'hotel','hotels','resort','resorts','spa','отель'}
qual={'annex','annexe','beach','garden','gardens','north','south','east','west','mountain','posh','family','junior','deluxe','aqua','park','palace','royal','grand','premium','select','bay','island','village'}
def upper_bounds(matrix,vector,lengths,source_length):
 return 2*np.minimum(matrix,vector).sum(axis=1)/(lengths+source_length)
def forms(s):
 s=unicodedata.normalize('NFKC',s).casefold().replace("'",'').replace('’','');s=re.sub(r'\baquapark\b','aqua park',s)
 return sorted(set(' '.join(t for t in re.findall(r'[^\W_]+',x) if t not in drop) for x in re.split(r'\b(?:ex|former|formerly)\b\.?',s))-{''})
def read_result(path,digest):
 z=zipfile.ZipFile(path);raw=z.read('server/result.json');d=json.loads(raw);receipt=json.loads(z.read('server/receipt.json'))
 assert hashlib.sha256(raw).hexdigest()==digest==receipt['result_sha256']
 assert receipt['readback_verified'] and receipt['no_replay']
 assert z.read('reservation.json')==z.read('server/reservation.json')
 return d

def main(base,current,applied,guarded,out):
 r=read_result(base,'f135bb42d40b0f3134309b24f5bdffcca8f2e62fa9511d36dfa14c04d96ccd1b')
 cur=read_result(current,'d742a12dee5d5289abfd11dc6929407ccbe4ee40d4d2ca32dd3c52158c3087ed')
 written=read_result(applied,'2adfcb28a54e17f3efc1ee44582d6abe5e2d7bb888e27e1adc3ee3f2357f3ff2')
 previous=read_result(guarded,'0a7f816a521267c708009b3722b58cb651421bb5ba9f3e8d841b04e185cab337')
 keep={x['external_hotel_id'] for rows in cur['routes'].values() for x in rows if x['supplier_namespace']=='andromeda_catalog'}
 committed={x['external_hotel_id'] for x in written['post_commit_readback']}
 assert not keep.intersection(committed) and len(keep)==1410 and len(committed)==50
 prior_holds={x['external_hotel_id']:x['holds'] for x in previous['routes'].get('held',[])}
 newly_occupied={x['local_hotel_id'] for x in written['post_commit_readback']}
 bycountry=collections.defaultdict(lambda:collections.defaultdict(set))
 for k,ls in r['local_alias_forms'].items():
  for raw in ls:
   for f in forms(raw):bycountry[r['local_hotels'][k]['country_id']][f].add(int(k))
 rows=[x for ls in r['routes'].values() for x in ls if x['external_hotel_id'] in keep];indexes={}
 for cid,d in bycountry.items():
  fs=sorted(d);vocab=sorted(set(''.join(fs)));vi={c:i for i,c in enumerate(vocab)};m=np.zeros((len(fs),len(vocab)),dtype=np.int16)
  for j,f in enumerate(fs):
   for c,n in collections.Counter(f).items():m[j,vi[c]]=n
  indexes[cid]=(fs,vi,m,np.array([len(f) for f in fs]))
 found=[];st=time.time()
 for no,x in enumerate(rows):
  cid=x['country_id']
  if cid not in indexes:continue
  fs,vi,m,lens=indexes[cid];scores={};matches={}
  for raw in x['names']:
   for f in forms(raw):
    if len(f.split())<2 or len(f.replace(' ',''))<8:continue
    v=np.zeros(len(vi),dtype=np.int16)
    for c,n in collections.Counter(f).items():
     if c in vi:v[vi[c]]=n
    upper=upper_bounds(m,v,lens,len(f))
    for i in np.flatnonzero(upper>=.8):
     lf=fs[i];score=difflib.SequenceMatcher(None,f,lf,autojunk=False).ratio()
     if score<.8:continue
     for lid in bycountry[cid][lf]:
      if score>scores.get(lid,0):scores[lid]=score;matches[lid]=(f,lf)
  rank=sorted(scores,key=lambda k:(-scores[k],k))
  if not rank:continue
  lid=rank[0];score=scores[lid];second=max(.8,scores[rank[1]] if len(rank)>1 else 0)
  if score<.92 or score-second<.12-1e-12:continue
  f,lf=matches[lid];h=r['local_hotels'][str(lid)];holds=[]
  if set(f.split())&qual!=set(lf.split())&qual:holds.append('qualifier_mismatch')
  if re.findall(r'\d+',f)!=re.findall(r'\d+',lf):holds.append('numeric_mismatch')
  if not x['geo_anchors']:holds.append('geography_missing')
  for a in x['geo_anchors']:
   if int(h.get(a['scope']+'_id') or 0)!=int(a['scope_id']):holds.append('geography_conflict')
  found.append({'source':x,'local_id':lid,'local':h,'score':score,'runner_up_upper_bound':second,'margin_lower_bound':score-second,'matched_forms':[f,lf],'holds':holds,'prepared_only':True})
 target_counts=collections.Counter(x['local_id'] for x in found)
 for x in found:
  x['prior_guard_holds']=prior_holds.get(x['source']['external_hotel_id'],[])
  x['holds'].extend(h for h in x['prior_guard_holds'] if h!='countrywide_exact_alias_not_unique_at_proposed_target')
  if x['local_id'] in newly_occupied:x['holds'].append('same_provider_target_newly_occupied')
  if target_counts[x['local_id']]>1:x['holds'].append('duplicate_planned_target')
  if len(x['matched_forms'][1].split())<2:x['holds'].append('local_name_less_than_two_tokens')
  x['holds']=sorted(set(x['holds']))
  x['required_before_write']=['new_CURRENT_transaction','primary_identity_semantics','manual_and_pair_exclusions','unchanged_full_country_names_aliases','unanimous_geography','same_provider_occupancy','all_coordinates_gt5km_veto']
  x['safe_to_write_now']=False
 report={'schema':'post50-countrywide-name-evidence/1','state':'prepared_not_safe','examined':len(rows),'pending_snapshot_count':len(keep),'committed_subtracted':len(committed),'excluded_claim_ids':r['excluded_claim_ids'],'source_result_sha256':{'census':'f135bb42d40b0f3134309b24f5bdffcca8f2e62fa9511d36dfa14c04d96ccd1b','post50':'d742a12dee5d5289abfd11dc6929407ccbe4ee40d4d2ca32dd3c52158c3087ed','apply50':'2adfcb28a54e17f3efc1ee44582d6abe5e2d7bb888e27e1adc3ee3f2357f3ff2','prior_guards':'0a7f816a521267c708009b3722b58cb651421bb5ba9f3e8d841b04e185cab337'},'rank_scope':'all same-country saved active local hotel names and aliases; no token-overlap shortlist','rank_certificate':'For omitted forms, SequenceMatcher ratio <= character-multiset upper bound <0.8; runner-up bound is max(0.8, every evaluated other-local score). Qualifier/geo mismatches never remove runner-up competition.','candidate_count':len(found),'no_preliminary_holds':sum(not x['holds'] for x in found),'holds_overlapping':dict(sorted(collections.Counter(k for x in found for k in x['holds']).items())),'safe_mappings':0,'db_writes':0,'supplier_calls':0,'tourvisor_calls':0,'candidates':found}
 assert len(rows)==1349 and len(found)==104
 Path(out).write_text(json.dumps(report,ensure_ascii=False,sort_keys=True,indent=2)+'\n')
 print(json.dumps({k:report[k] for k in ['examined','candidate_count','no_preliminary_holds','holds_overlapping','safe_mappings']}))

if __name__=='__main__':main(*sys.argv[1:])
