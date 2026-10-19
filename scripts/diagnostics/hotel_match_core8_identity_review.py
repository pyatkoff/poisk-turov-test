"""MATCH saved-data bulk evidence review. This module never accesses a DB or supplier."""
from __future__ import annotations
import argparse, collections, hashlib, json, math, re, unicodedata
from pathlib import Path
from typing import Any
from rapidfuzz import fuzz, process

GENERIC = {'hotel','hotels','resort','resorts','spa','the','and','by','otel','отель','отели','гостиница'}
QUALIFIERS = {'annex','beach','garden','north','south','east','west','wing','main','adults','family','palace','park','villa','villas','suite','suites','apartments','club','residence'}
NONDISTINCT = GENERIC | QUALIFIERS | set('grand royal plaza sea view ocean island city central new old boutique luxury inn guest house guesthouse home retreat pearl sunrise sunset golden green blue white red black star paradise paradise international'.split())
COUNTRIES = {'egypt':['египет','egypt'],'turkey':['турция','turkey','turkiye'],'thailand':['таиланд','thailand'],
    'uae':['оаэ','united arab emirates','uae'],'vietnam':['вьетнам','vietnam','viet nam'],
    'srilanka':['шри ланка','sri lanka'],'maldives':['мальдивы','maldives'],'cuba':['куба','cuba']}
FORMER = re.compile(r'\(\s*(?:ex|ех|formerly|быв)\s*[.:\-]?\s*([^)]*)\)',re.I)

def text(value: Any) -> str:
    s=unicodedata.normalize('NFKD',str(value or '').casefold()).replace('ı','i').replace('&',' and ')
    return ' '.join(re.findall(r'[^\W_]+',''.join(c for c in s if not unicodedata.combining(c))))

def norm(value: Any) -> str:
    return ' '.join(sorted(x for x in text(value).split() if x not in GENERIC))

def variants(value: Any) -> set[str]:
    s=str(value or '')
    return {n for v in [s,FORMER.sub(' ',s),*FORMER.findall(s)] if (n:=norm(v))}

def country(value: Any) -> str | None:
    v=text(value)
    return next((k for k,values in COUNTRIES.items() if v in values),None)

def geo(value: Any) -> str:
    words=text(value).split()
    return ' '.join(w for w in words if w not in {'о','остров','island','город','г'})

def qualifiers(value: str) -> set[str]:
    words=set(value.split())
    return (words & QUALIFIERS) | {w for w in words if w.isdigit() or w in {'ii','iii','iv'}}

def point(row: dict) -> tuple[float,float] | None:
    lat=next((row[k] for k in ['latitude','lat'] if row.get(k) not in [None,'']),None)
    lon=next((row[k] for k in ['longitude','lon','lng'] if row.get(k) not in [None,'']),None)
    try:
        lat,lon=float(lat),float(lon)
        if not math.isfinite(lat+lon) or not -90<=lat<=90 or not -180<=lon<=180 or (lat==0 and lon==0):return None
        return lat,lon
    except (TypeError,ValueError):return None

def distance(a: dict,b: dict) -> float | None:
    x,y=point(a),point(b)
    if x is None or y is None:return None
    p,q=map(math.radians,[x[0],y[0]]);dp=q-p;dl=math.radians(y[1]-x[1])
    return 12742*math.asin(min(1,math.sqrt(math.sin(dp/2)**2+math.cos(p)*math.cos(q)*math.sin(dl/2)**2)))

def digest(value: Any) -> str:
    return hashlib.sha256(json.dumps(value,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()

class Review:
    def __init__(self,census: dict,baseline: dict):
        if census.get('status')!='completed' or census.get('database_writes')!=0:raise ValueError('unverified census')
        self.d=census
        self.h={str(h['id']):h for h in census['local']}
        self.b={str(r['external_id']):r for r in baseline['matches']}
        self.a={str(r['anex_hotel_id']):r for r in census['anex']}
        self.obs={str(r['anex_hotel_id']):r for r in census['anex_observations']}
        self.protected={str(r['anex_hotel_id']) for k in ['anex_mappings','anex_decisions','anex_exclusions'] for r in census[k]}
        self.index=collections.defaultdict(lambda:collections.defaultdict(set))
        self.aliases=collections.defaultdict(set)
        self.bridge=collections.defaultdict(list)
        for h in self.h.values():self.add_alias(h['id'],h['name'])
        for a in census['aliases']:self.add_alias(a['hotel_id'],a['alias'])
        self.native_aliases={lid:set(names) for lid,names in self.aliases.items()}
        for m in census['anex_mappings']:
            lid=str(m['catalog_hotel_id']);sid=str(m['anex_hotel_id'])
            if lid not in self.h or m.get('enabled')!=1:continue
            s=self.anex_source(sid)
            if s.get('country_class')!=self.h[lid]['country_class'] or s.get('country_conflict'):continue
            self.bridge[lid].append(s)
        for row in census['andromeda']:
            lid=str(row['local_hotel_id'])
            if row['decision_status']!='accepted' or lid not in self.h:continue
            s=self.andromeda_source(row)
            if s.get('country_class')!=self.h[lid]['country_class'] or s.get('source_invalid'):continue
            self.bridge[lid].append(s)
        # Accepted counterpart aliases are retained separately from native catalog names.
        for lid,sources in self.bridge.items():
            for s in sources:
                for n in s['names']:self.add_alias(lid,n)
        self.choices={cc:sorted(names) for cc,names in self.index.items()}

    def add_alias(self,lid: Any,name: Any) -> None:
        lid=str(lid)
        if lid not in self.h:return
        for n in sorted(variants(name)):
            self.index[self.h[lid]['country_class']][n].add(lid);self.aliases[lid].add(n)

    def anex_source(self,sid: str) -> dict:
        a=self.a.get(sid,{});b=self.b.get(sid,{});o=self.obs.get(sid,{})
        names=[v for v in [a.get('api_name'),a.get('xml_name'),a.get('xml_alternate_name'),b.get('name'),b.get('alternate_name'),o.get('hotel_name')] if v]
        api_cc=country(a.get('api_country'));xml_cc=country(b.get('country'));obs_cc=self.d['country_ids'].get(str(o.get('country_id')))
        known={x for x in [api_cc,xml_cc,obs_cc] if x};conflict=len(known)>1 or bool(a.get('api_country') and not api_cc)
        return {'provider':'anex','external_id':sid,'names':names,'country_class':api_cc or xml_cc or obs_cc,
            'country_conflict':conflict,'geography':[v for v in [a.get('api_town'),a.get('api_region'),b.get('town')] if v],
            'latitude':a.get('latitude'),'longitude':a.get('longitude'),'search_count':o.get('search_count',0),
            'country_evidence':['current_api' if api_cc else None,'saved_xml_catalog' if xml_cc else None,'current_observation' if obs_cc else None],
            'source_exists':bool(a),'observed':bool(o),'baseline_exists':bool(b),'current_source_digest':digest(a),'current_observation_identity_digest':digest({k:v for k,v in o.items() if k in ['anex_hotel_id','hotel_name','country_id','anex_country_id']})}

    def andromeda_source(self,row: dict) -> dict:
        sid=row['external_hotel_id'];ss=[s for s in row['sources'] if str(s.get('id',s.get('hotelKey')))==sid]
        s=ss[0] if len(ss)==1 else {}
        return {'provider':'andromeda','external_id':sid,'supplier_namespace':row['supplier_namespace'],
            'names':[v for v in [s.get('name'),s.get('lName')] if v], 'country_class':country(s.get('state')),
            'geography':[v for v in [s.get('town'),s.get('region')] if v],
            'latitude':s.get('latitude',s.get('lat')),'longitude':s.get('longitude',s.get('lon')),
            'category_label':s.get('star'),'category_key_not_rating':s.get('starKey'),'search_count':None,
            'evidence_sha256':row['evidence_sha256'],'catalog_sha256':row['catalog_sha256'],
            'source_invalid':len(ss)!=1 or row['evidence_sha256']!=row['actual_evidence_sha256']}

    def source_queue(self) -> list[dict]:
        # Include observed IDs that were absent from anex_hotels; never silently drop them.
        ids=(set(self.a)|set(self.obs)|{sid for sid,b in self.b.items() if country(b.get('country')) in COUNTRIES})-self.protected
        queue=[self.anex_source(sid) for sid in sorted(ids,key=int)]
        queue=[s for s in queue if s['country_class'] in COUNTRIES and not s['country_conflict']]
        for row in self.d['andromeda']:
            if row['decision_status']=='pending' and row['local_hotel_id'] is None and row['supplier_namespace']=='andromeda_catalog':queue.append(self.andromeda_source(row))
        return sorted(queue,key=lambda s:(-(s.get('search_count') or 0),s['provider'],int(s['external_id'])))

    def evaluate(self,s: dict) -> dict:
        out={'source':s,'status':'needs_automatic_evidence','candidates':[]}
        cc=s['country_class']
        if s.get('country_conflict') or s.get('source_invalid') or cc not in COUNTRIES:
            out['status']='source_guard';return out
        forms=set().union(*(variants(n) for n in s['names']))
        if not forms:out['status']='missing_name';return out
        # Enumerate every name scoring >=65: alias-heavy hotels must not hide a competing local ID.
        best={}
        for sf in sorted(forms):
            for tf,score,_ in process.extract(sf,self.choices.get(cc,[]),scorer=fuzz.ratio,score_cutoff=65,limit=None):
                for lid in self.index[cc][tf]:
                    old=best.get(lid)
                    if old is None or score>old['name_score']:
                        best[lid]={'local_id':int(lid),'name_score':round(score,4),'source_form':sf,'target_form':tf}
            for lid in self.index[cc].get(sf,set()):best[lid]={'local_id':int(lid),'name_score':100.0,'source_form':sf,'target_form':sf}
        sg={geo(v) for v in s['geography'] if geo(v)}
        broad_countries={geo(v) for vs in COUNTRIES.values() for v in vs}
        sg-=broad_countries
        for lid,c in best.items():
            h=self.h[lid];c['native_exact_keys']=sorted(forms & self.native_aliases[lid]);
            native=max(((fuzz.ratio(sf,tf),sf,tf) for sf in forms for tf in self.native_aliases[lid]),default=(0,'',''))
            c['native_name_score']=round(native[0],4);c['native_qualifier_conflict']=qualifiers(native[1])!=qualifiers(native[2]);tg={geo(v) for v in [h.get('region_name'),h.get('subregion_name')] if geo(v)}-broad_countries
            c.update(target_name=h['name'],target_country=h['country_name'],target_region=h.get('region_name'),target_subregion=h.get('subregion_name'),distance_km=distance(s,h),
                exact_geography=sorted(sg & tg),qualifier_conflict=qualifiers(c['source_form'])!=qualifiers(c['target_form']))
            bridges=[]
            for bs in self.bridge.get(lid,[]):
                if bs['provider']==s['provider']:continue
                names=set().union(*(variants(n) for n in bs['names']))
                geo_shared=sg & {geo(v) for v in bs['geography'] if geo(v)}
                if forms & names and geo_shared:bridges.append({'provider':bs['provider'],'external_id':bs['external_id'],'name_keys':sorted(forms&names),'geography':sorted(geo_shared)})
            c['cross_provider_proofs']=bridges
            cat=str(s.get('category_label') or '')
            c['category_discrepancy']=bool(re.fullmatch('[1-5]',cat) and int(cat)!=int(h.get('category') or 0))
            c['coordinate_conflict']=c['distance_km'] is not None and c['distance_km']>5
        ranked=sorted(best.values(),key=lambda c:(-c['name_score'],c['local_id']))
        out['candidate_count']=len(ranked);out['candidates']=ranked[:5]
        if not ranked:out['status']='no_name_candidate';return out
        winner=ranked[0];margin=winner['name_score']-(ranked[1]['name_score'] if len(ranked)>1 else 65)
        out['margin']=round(margin,4)
        if winner['coordinate_conflict']:out['status']='coordinate_conflict';return out
        if winner['qualifier_conflict']:out['status']='qualifier_conflict';return out
        distinct=[w for w in winner['source_form'].split() if len(w)>=5 and w not in NONDISTINCT]
        near=winner['distance_km'] is not None and winner['distance_km']<=0.5
        geography=bool(winner['exact_geography'])
        bridged=bool(winner['cross_provider_proofs'])
        exact=winner['name_score']==100 and bool(winner['native_exact_keys'])
        # Score alone / an unrelated accepted counterpart can never establish a mapping.
        rule=None
        if exact and margin>=5 and near:rule='exact_alias_same_country_near_coordinates'
        elif exact and margin>=8 and distinct and geography:rule='unique_distinct_exact_alias_same_country_exact_geography'
        elif exact and margin>=8 and distinct and bridged:rule='unique_exact_cross_provider_name_country_geography_bridge'
        elif winner['native_name_score']>=97 and winner['native_name_score']-max([c['native_name_score'] for c in ranked[1:]]+[65])>=12 and not winner['native_qualifier_conflict'] and len(distinct)>=2 and (near or (geography and bridged)) and not winner['category_discrepancy']:
            rule='strong_fuzzy_large_margin_independent_geography'
        if rule:
            out['status']='prepared_for_current_revalidation';out['rule']=rule;out['proposed_local_id']=winner['local_id']
        return out

    def run(self) -> dict:
        queue=self.source_queue();rows=[self.evaluate(s) for s in queue]
        return {'schema_version':1,'census_operation_id':self.d['operation_id'],'census_generated_at':self.d['generated_at_utc'],
            'scope':'current_live_union_full_saved_core8_catalog_plus_andromeda_pending','queue_count':len(queue),
            'status_counts':dict(collections.Counter(r['status'] for r in rows)),
            'provider_counts':dict(collections.Counter(s['provider'] for s in queue)),
            'saved_only_anex_count':sum(s['provider']=='anex' and not s['source_exists'] and not s['observed'] for s in queue),
            'live_anex_count':sum(s['provider']=='anex' and s['observed'] for s in queue),
            'live_anex_absent_from_staging':sum(s['provider']=='anex' and s['observed'] and not s['source_exists'] for s in queue),
            'rows':rows,'accepted_mappings':0,'database_writes':0,'supplier_calls':0,
            'snapshot_is_not_write_authority':True,'current_transaction_required':True}

def make_write_plan(result: dict, census: dict, baseline: dict) -> dict:
    selected=[r for r in result['rows'] if r['status']=='prepared_for_current_revalidation'
        and r['candidates'][0]['cross_provider_proofs'] and r['candidates'][0]['native_exact_keys']
        and not r['candidates'][0]['category_discrepancy']
        and (r['candidates'][0]['distance_km'] is None or r['candidates'][0]['distance_km']<=1)]
    by_baseline={str(r['external_id']):r for r in baseline['matches']}
    by_staging={str(r['anex_hotel_id']):r for r in census['anex']}
    by_and={r['external_hotel_id']:r for r in census['andromeda'] if r['supplier_namespace']=='andromeda_catalog'}
    rows=[];anex_ids=set();and_ids=set()
    for r in selected:
        s=r['source'];proof=min(r['candidates'][0]['cross_provider_proofs'],key=lambda b:int(b['external_id']))
        rows.append({'provider':s['provider'],'external_id':s['external_id'],'local_id':r['proposed_local_id'],'bridge_id':proof['external_id']})
        anex_ids.add(s['external_id'] if s['provider']=='anex' else proof['external_id'])
        and_ids.add(proof['external_id'] if s['provider']=='anex' else s['external_id'])
    rows.sort(key=lambda r:(r['provider'],int(r['external_id'])))
    return {'operation_id':'hotel-match-core8-bridge-accept-1971-20260912-v1','cap':400,'rows':rows,
        'census_sha256':result['census_sha256'],'baseline_sha256':result['baseline_sha256'],
        'baseline_anex':{sid:{k:by_baseline[sid].get(k) for k in ['external_id','name','alternate_name','country','town']} for sid in sorted(anex_ids,key=int)},
        'expected_staging':{sid:by_staging[sid]['source_fingerprint'] if sid in by_staging else None for sid in sorted(anex_ids,key=int)},
        'expected_andromeda':{sid:{k:by_and[sid][k] for k in ['evidence_sha256','catalog_sha256','decision_status','local_hotel_id']} for sid in sorted(and_ids,key=int)},
        'database_writes':0,'requires_current_transaction':True}

def main() -> None:
    parser=argparse.ArgumentParser();parser.add_argument('census',type=Path);parser.add_argument('baseline',type=Path);parser.add_argument('output',type=Path)
    args=parser.parse_args();cb=args.census.read_bytes();bb=args.baseline.read_bytes()
    result=Review(json.loads(cb),json.loads(bb)).run();result['census_sha256']=hashlib.sha256(cb).hexdigest();result['baseline_sha256']=hashlib.sha256(bb).hexdigest()
    args.output.write_text(json.dumps(result,ensure_ascii=False,sort_keys=True,indent=2)+'\n')
    plan=make_write_plan(result,json.loads(cb),json.loads(bb))
    plan_bytes=json.dumps(plan,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()
    args.output.with_suffix('.plan.json').write_bytes(plan_bytes)
    print('WRITE_PLAN',len(plan['rows']),hashlib.sha256(plan_bytes).hexdigest())
    print(json.dumps({k:v for k,v in result.items() if k!='rows'},ensure_ascii=False,indent=2))
if __name__=='__main__':main()
