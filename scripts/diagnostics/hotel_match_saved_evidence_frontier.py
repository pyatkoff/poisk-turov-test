#!/usr/bin/env python3
import argparse, hashlib, json, re, unicodedata
from collections import defaultdict
try:
    from rapidfuzz import fuzz
except ImportError:
    fuzz = None

GENERIC = {'hotel','hotels','resort','resorts','spa','otel','the'}
PRODUCT_RE = re.compile(r'roulette|fortuna|рулет|тур\s*["«]', re.I)

def sha256_file(path):
    h=hashlib.sha256()
    with open(path,'rb') as f:
        for chunk in iter(lambda:f.read(1<<20),b''): h.update(chunk)
    return h.hexdigest()

def norm_tokens(text):
    s=unicodedata.normalize('NFKD',str(text).lower())
    s=''.join(ch for ch in s if not unicodedata.combining(ch)).replace('&',' and ')
    return [t for t in re.findall(r'[a-z0-9]+',s) if t not in GENERIC]

def current_unresolved(census):
    core8={int(x) for x in census['country_ids']}
    mapped={str(r['anex_hotel_id']) for r in census['anex_mappings'] if int(r.get('enabled',1))==1}
    decisions={str(r['anex_hotel_id']) for r in census['anex_decisions']}
    rows=[]
    for r in census['anex_observations']:
        eid=str(r['anex_hotel_id'])
        if eid in mapped or eid in decisions or int(r['country_id']) not in core8: continue
        rows.append({'external_id':eid,'country_id':int(r['country_id']),'name':r['hotel_name'],
                     'search_count':int(r['search_count']),'last_seen_utc':r.get('last_seen_utc')})
    rows.sort(key=lambda x:(-x['search_count'],x['external_id']))
    return rows

def pair_metric(source_name, tv):
    st=norm_tokens(source_name)
    best=(-1,-1,-1.0,None)
    for variant in [tv['name']]+list(tv.get('aliases') or []):
        tt=norm_tokens(variant)
        if not st or not tt: continue
        exact=sum(1 for x in st if x in tt)
        sims=[]
        for x in st:
            if fuzz is not None: sims.append(max(fuzz.ratio(x,y) for y in tt))
            else: sims.append(100.0 if x in tt else 0.0)
        cov75=sum(v>=75 for v in sims)
        avg=sum(sims)/len(sims)
        metric=(exact,cov75,avg,variant)
        if metric[:3]>best[:3]: best=metric
    return best

def build(census, bulk, tv_only, tv_anex):
    unresolved=current_unresolved(census)
    seed={str(r['anex_hotel_id']):r for r in bulk['tourvisor_anex_seeds']}
    products=[r for r in unresolved if PRODUCT_RE.search(r['name'])]
    physical=[r for r in unresolved if not PRODUCT_RE.search(r['name'])]
    seeded=[r for r in physical if r['external_id'] in seed]
    new=[r for r in physical if r['external_id'] not in seed]
    direct={str(h['id']):h for h in tv_anex['providers']['anex']['hotels']}
    tv=tv_only['providers']['tourvisor']['hotels']
    same_day=[]
    for r in physical:
        a=direct.get(r['external_id'])
        if not a: continue
        scored=[]
        for h in tv:
            ex,cov,avg,var=pair_metric(a['name'],h)
            scored.append((ex,cov,avg,h,var))
        scored.sort(key=lambda x:(x[0],x[1],x[2]), reverse=True)
        if not scored: continue
        best=scored[0]; second=scored[1] if len(scored)>1 else (-1,-1,-1,None,None)
        st=norm_tokens(a['name'])
        full_cov=best[1]==len(st) and len(st)>0
        distinct=(best[0]>second[0]) or (best[1]>second[1]) or (best[2]-second[2]>=15)
        if full_cov and distinct:
            same_day.append({'anex_id':r['external_id'],'anex_name':a['name'],'search_count':r['search_count'],
                'tourvisor_hotel_id':str(best[3]['id']),'tourvisor_name':best[3]['name'],
                'matched_variant':best[4],'exact_token_matches':best[0],
                'fuzzy_token_coverage_75':best[1],'source_token_count':len(st),
                'mean_token_similarity':round(best[2],3), 'status':'saved_same_day_name_corroboration_only'})
    target_sources=defaultdict(list)
    for row in same_day: target_sources[row['tourvisor_hotel_id']].append(row['anex_id'])
    for row in same_day:
        if len(target_sources[row['tourvisor_hotel_id']])>1:
            row['status']='duplicate_provider_ids_same_tv_target_hold'
            row['duplicate_anex_ids']=sorted(target_sources[row['tourvisor_hotel_id']])
    return {
      'schema':'hotel-match-saved-evidence-frontier/1','status':'prepared_only_not_write_authority',
      'counts':{
        'current_unresolved_anex_ids':len(unresolved),'current_unresolved_occurrences':sum(r['search_count'] for r in unresolved),
        'product_identity_review_ids':len(products),'product_identity_review_occurrences':sum(r['search_count'] for r in products),
        'physical_hotel_ids':len(physical),'physical_hotel_occurrences':sum(r['search_count'] for r in physical),
        'seeded_operator_link_hotelcode_needed_ids':len(seeded),'seeded_occurrences':sum(r['search_count'] for r in seeded),
        'new_tourvisor_acquisition_scope_ids':len(new),'new_scope_occurrences':sum(r['search_count'] for r in new),
        'saved_direct_anex_overlap_ids':sum(1 for r in physical if r['external_id'] in direct),
        'saved_same_day_name_candidates':len(same_day),
        'saved_same_day_unique_target_candidates':sum(1 for r in same_day if r['status']=='saved_same_day_name_corroboration_only')},
      'coverage':{'seeded_physical_id_pct':round(100*len(seeded)/len(physical),3) if physical else 0,
                  'seeded_physical_occurrence_pct':round(100*sum(r['search_count'] for r in seeded)/sum(r['search_count'] for r in physical),3) if physical else 0},
      'product_identity_review':products,
      'new_tourvisor_acquisition_scope':new,
      'seeded_operator_link_hotelcode_needed':[dict(r, old_seed_search_count=int(seed[r['external_id']].get('search_count',0))) for r in seeded],
      'saved_same_day_name_corroboration':same_day,
      'policy':{'saved_data_only':True,'network_calls':0,'database_writes':0,'mapping_writes':0,
        'same_day_name_is_not_acceptance':True,'seed_rows_do_not_contain_operator_link_or_hotelcode':True,
        'acceptance_requires':'independent operator/card hotelCode or equivalent provider identity plus NEW CURRENT guarded transaction/readback',
        'duplicate_provider_ids_same_target':'hold, never collapse automatically','product_labels':'quarantine from physical-hotel matching'}}

def main():
    ap=argparse.ArgumentParser()
    ap.add_argument('census'); ap.add_argument('bulk_review'); ap.add_argument('tv_only'); ap.add_argument('tv_anex'); ap.add_argument('output')
    args=ap.parse_args()
    labeled=[('current_census',args.census),('bulk_review',args.bulk_review),('tv_only',args.tv_only),('tv_anex',args.tv_anex)]
    docs=[json.load(open(p,encoding='utf-8')) for _,p in labeled]; out=build(*docs)
    out['inputs']={label:sha256_file(path) for label,path in labeled}
    with open(args.output,'w',encoding='utf-8') as f: json.dump(out,f,ensure_ascii=False,sort_keys=True,indent=2); f.write('\n')
if __name__=='__main__': main()
