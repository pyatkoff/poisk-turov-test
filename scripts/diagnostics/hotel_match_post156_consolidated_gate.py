#!/usr/bin/env python3
"""MATCH #1971 saved-CURRENT consolidated hotel identity gate. Read-only/offline."""
from __future__ import annotations
import argparse, collections, hashlib, json, re, unicodedata
from pathlib import Path
from rapidfuzz import fuzz, process
from hotel_match_post156_parent_geo_extension import primary_recheck, numeric_star_label

OP='hotel-match-post156-fuzzy-bridge-review-1971-20260912-v2'
CORE={'egypt','turkey','thailand','uae','vietnam','srilanka','maldives','cuba'}
GEN={'and','ex','former','formerly','hotel','hotels','otel','resort','resorts','spa','the'}
QUAL={'adult','adults','annex','apart','apartments','aqua','bay','beach','beachfront','boutique','central','city','club','east','family','garden','grand','harem','island','marina','north','only','palace','park','royal','south','suite','suites','villa','villas','waterpark','west','wing'}
GROUPS=({'north','south','east','west'},{'beach','garden','marina','city','island','bay','central'},{'annex','wing'},{'adult','adults','family'},{'suite','suites','villa','villas','apart','apartments'})

def norm(v):
    s=unicodedata.normalize('NFKD',str(v or '').strip().lower()); s=''.join(c for c in s if not unicodedata.combining(c)); s=s.replace('&',' and ')
    return re.sub(r'\s+',' ',re.sub(r'[^a-z0-9а-яё]+',' ',re.sub(r"['’`]",'',s),flags=re.I)).strip()
def key(v): return ' '.join(t for t in norm(v).split() if t not in GEN)
def variants(v):
    s=str(v or ''); out={key(s)}
    for x in re.split(r'\(\s*(?:ex\.?|former(?:ly)?)[^)]*?\)',s,maxsplit=1,flags=re.I)[:1]: out.add(key(x))
    for x in re.split(r'\b(?:ex\.?|former(?:ly)?)\b',s,maxsplit=1,flags=re.I)[:1]: out.add(key(x))
    for m in re.finditer(r'\(\s*(?:ex\.?|former(?:ly)?)\s*[:\-]?\s*([^)]{2,})\)',s,re.I): out.add(key(m.group(1)))
    return {x for x in out if x}
def keys(vals):
    out=set()
    for v in vals:
        if v: out.update(variants(v))
    return out
def qok(a,b):
    qa=set().union(*(set(x.split())&QUAL for x in a)) if a else set(); qb=set().union(*(set(x.split())&QUAL for x in b)) if b else set()
    return not any((qa&g)!=(qb&g) and ((qa&g) or (qb&g)) for g in GROUPS)
def best(a,b):
    z=(0.0,None,None)
    for x in sorted(a):
        for y in sorted(b):
            s=float(fuzz.WRatio(x,y))
            if s>z[0] or (s==z[0] and (x,y)<(z[1] or '',z[2] or '')): z=(s,x,y)
    return z
def sha(v): return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()

def build(c,p,pr):
    loc={int(x['id']):x for x in c['local']}; alias=collections.defaultdict(list)
    for x in c['aliases']: alias[int(x['hotel_id'])].append(x['alias'])
    aro={str(x['external_hotel_id']):x for x in c['andromeda']}; accepted=collections.defaultdict(list)
    for x in c['andromeda']:
        if x.get('decision_status')=='accepted' and x.get('local_hotel_id'): accepted[int(x['local_hotel_id'])].append(str(x['external_hotel_id']))
    anx={str(x['anex_hotel_id']):x for x in c['anex']}; obs=collections.defaultdict(list)
    for x in c.get('anex_observations',[]): obs[str(x['anex_hotel_id'])].append(x)
    amap=collections.defaultdict(list)
    for x in c['anex_mappings']:
        if int(x.get('enabled') or 0)==1: amap[int(x['catalog_hotel_id'])].append(str(x['anex_hotel_id']))
    def lnames(i): return [v for v in [loc[i].get('name'),*alias.get(i,[])] if v]
    def anames(e): return [v for s in aro[e].get('sources') or [] for v in (s.get('name'),s.get('lName')) if v]
    def xnames(i):
        out=[]
        for aid in amap.get(i,[]):
            a=anx.get(aid,{})
            out += [v for v in (a.get('api_name'),a.get('xml_name'),a.get('xml_alternate_name')) if v]
            out += [o.get('hotel_name') for o in obs.get(aid,[]) if o.get('hotel_name')]
        return out
    town=collections.defaultdict(collections.Counter)
    for x in c['andromeda']:
        if x.get('decision_status')=='accepted' and x.get('local_hotel_id') and int(x['local_hotel_id']) in loc:
            for s in x.get('sources') or []:
                if s.get('townKey') is not None: town[(x.get('country_class'),str(s['townKey']))][loc[int(x['local_hotel_id'])].get('region_id')]+=1
    def occ(e,i,sk):
        sib=keys(v for ae in accepted.get(i,[]) if ae!=e for v in anames(ae)); ak=keys(xnames(i)); ss=best(sk,sib)[0] if sib else 0; xs=best(sk,ak)[0] if ak else 0
        return i not in accepted or ss>=95 or xs>=95,ss,xs
    high=[]; rc=collections.Counter(); prior_ids=set(); prior_t=set()
    for x in p['rows']:
        e=str(x['external_hotel_id']); i=int(x['proposed_local_id']); prior_ids.add(e); prior_t.add(i); sk=keys(x.get('source_names') or []); ms,mt=x['matched_name_pair']; qo=qok({ms},{mt}); oo,ss,xs=occ(e,i,sk)
        rel='exact' if ms==mt else ('reordered' if sorted(ms.split())==sorted(mt.split()) else ('near' if float(x['best_score'])>=98 else 'fuzzy'))
        ev=(rel in {'exact','reordered'} and float(x.get('geo_score') or 0)>=90) or (float(x['best_score'])>=98 and float(x.get('margin') or 0)>=8 and float(x.get('geo_score') or 0)>=90) or (float(x['best_score'])>=95 and float(x.get('margin') or 0)>=8 and float(x.get('geo_score') or 0)>=95) or (xs>=95 and float(x.get('geo_score') or 0)>=90)
        labels=[numeric_star_label(z.get('star')) for z in aro.get(e,{}).get('sources') or []]
        target_star=numeric_star_label(loc.get(i,{}).get('category'))
        st=bool(labels) and target_star is not None and all(z is not None and abs(z-target_star)<=1 for z in labels)
        if qo and oo and ev and st: high.append([e,i,'prior_101'])
        else:
            if not qo: rc['prior_meaningful_qualifier_guard']+=1
            if not oo: rc['prior_occupied_target_without_corroboration']+=1
            if not ev: rc['prior_insufficient_name_geo_margin']+=1
            if not st: rc['prior_star_difference_gt1']+=1
    pp=[(str(e),int(i)) for e,i in pr['andromeda_extension_pairs']]; parent_ids={e for e,_ in pp}; parent_t={i for _,i in pp}
    ph=0
    for e,i in pp:
        x=aro.get(e); l=loc.get(i)
        if not x or not l: rc['parent_missing_saved_row']+=1; continue
        sk=keys(anames(e)); lk=keys(lnames(i)); ls,a,b=best(sk,lk); qo=qok({a or ''},{b or ''}); oo,_,_=occ(e,i,sk); s=(x.get('sources') or [{}])[0]; cnt=town.get((x.get('country_class'),str(s.get('townKey'))),collections.Counter()); n=sum(cnt.values()); ratio=cnt.get(l.get('region_id'),0)/n if n else 0
        source_star=numeric_star_label(s.get('star')); target_star=numeric_star_label(l.get('category'))
        sd=abs(source_star-target_star) if source_star is not None and target_star is not None else None
        ok=x.get('decision_status')=='pending' and ls>=95 and n>=5 and ratio>=.9 and (sd is None or sd<=1) and qo and oo
        if ok: high.append([e,i,'parent_73']); ph+=1
        else:
            if not qo: rc['parent_meaningful_qualifier_guard']+=1
            if not oo: rc['parent_occupied_target_without_corroboration']+=1
            if ls<95: rc['parent_local_name_score_lt95']+=1
            if n<5 or ratio<.9: rc['parent_geo_support']+=1
            if sd is not None and sd>1: rc['parent_star_difference_gt1']+=1
    index={}; choices={}
    for country in CORE:
        d=collections.defaultdict(set)
        for i in amap:
            if i in loc and loc[i].get('country_class')==country:
                for n in xnames(i):
                    for k in variants(n):
                        if len(k.split())>=2: d[k].add(i)
        index[country]=d; choices[country]=sorted(d)
    prov=[]; exclude_t=prior_t|parent_t
    for x in c['andromeda']:
        if x.get('decision_status')!='pending' or x.get('country_class') not in CORE: continue
        e=str(x['external_hotel_id'])
        if e in prior_ids or e in parent_ids: continue
        s=(x.get('sources') or [{}])[0]; sk=keys([s.get('name'),s.get('lName')])
        if not sk: continue
        scores=collections.defaultdict(float); pairs={}
        for a in sorted(sk):
            for b,sc,_ in process.extract(a,choices[x['country_class']],scorer=fuzz.WRatio,limit=100,score_cutoff=85):
                for i in index[x['country_class']][b]:
                    if sc>scores[i]: scores[i]=float(sc); pairs[i]=(a,b)
        if not scores: continue
        ranked=sorted(scores.items(),key=lambda z:(-z[1],z[0])); i,bs=ranked[0]; sec=ranked[1][1] if len(ranked)>1 else 0; margin=bs-sec
        if i in exclude_t: continue
        l=loc[i]; ls,a,b=best(sk,keys(lnames(i))); qo=qok({a or ''},{b or ''}); oo,ss,_=occ(e,i,sk); cnt=town.get((x['country_class'],str(s.get('townKey'))),collections.Counter()); n=sum(cnt.values()); sup=cnt.get(l.get('region_id'),0); ratio=sup/n if n else 0
        source_star=numeric_star_label(s.get('star')); target_star=numeric_star_label(l.get('category'))
        sd=abs(source_star-target_star) if source_star is not None and target_star is not None else None
        if bs<90: continue
        ok=bs>=95 and margin>=8 and ls>=90 and n>=5 and ratio>=.9 and (sd is None or sd<=1) and qo and oo
        prov.append({'external_hotel_id':e,'country':x['country_class'],'proposed_local_id':i,'source_name':s.get('name'),'source_town':s.get('town'),'target_name':l.get('name'),'active_anex_bridge_score':round(bs,3),'active_anex_bridge_margin':round(margin,3),'active_anex_name_pair':list(pairs[i]),'local_name_score':round(ls,3),'matched_local_name_pair':[a,b],'town_accepted_support':n,'target_region_support':sup,'target_region_ratio':round(ratio,6),'star_difference':sd,'occupied_by_accepted_andromeda':i in accepted,'accepted_sibling_name_score':round(ss,3),'strict_qualifier_ok':qo,'occupancy_ok':oo,'ok':ok})
    cnt=collections.Counter(z['proposed_local_id'] for z in prov if z['ok']); new=[]
    for z in prov:
        if z['ok'] and cnt[z['proposed_local_id']]==1: new.append({k:v for k,v in z.items() if k!='ok'})
        elif z['ok']: rc['new_duplicate_target']+=1
    for z in new: high.append([z['external_hotel_id'],z['proposed_local_id'],'new_cross_provider_bridge'])
    high=sorted(high,key=lambda z:(next((aro[z[0]].get('country_class') for _ in [0] if z[0] in aro),''),z[2],z[0])); new=sorted(new,key=lambda z:(z['country'],z['external_hotel_id']))
    candidates = [dict(z) for z in p['rows']]
    for e, i in pp:
        source_row = aro.get(e, {})
        _, a, b = best(keys(anames(e)) if e in aro else set(), keys(lnames(i)) if i in loc else set())
        candidates.append({'external_hotel_id': e, 'proposed_local_id': i,
                           'country': source_row.get('country_class'), 'matched_name_pair': [a, b]})
    candidates.extend(dict(z, matched_name_pair=z['matched_local_name_pair']) for z in new)
    if len({str(z['external_hotel_id']) for z in candidates}) != len(candidates):
        raise ValueError('DUPLICATE_CONSOLIDATED_SOURCE')
    checked = primary_recheck(c, candidates, 'andromeda')
    checks = {z['external_hotel_id']: z for z in checked}
    pre_primary_high = list(high)
    high = [z for z in high if not checks[z[0]]['reason_codes']]
    for e, _, _ in pre_primary_high:
        for reason in checks[e]['reason_codes']:
            rc['primary_' + reason] += 1
    high_ids = {z[0] for z in high}
    for row in new:
        row['primary_recheck_status'] = checks[row['external_hotel_id']]['status']
        row['primary_recheck_reason_codes'] = checks[row['external_hotel_id']]['reason_codes']
        row['high_current_recheck'] = row['external_hotel_id'] in high_ids
    ph = sum(z[2] == 'parent_73' for z in high)
    nh = sum(z[2] == 'new_cross_provider_bridge' for z in high)
    countries=collections.Counter(aro[e].get('country_class') for e,_,_ in high if e in aro)
    report = {'schema':'hotel-match-post156-consolidated-semantic-gate/2','status':'prepared_only','not_write_authority':True,'no_replay':True,'source_census_operation':c['operation_id'],'source_census_sha256':p['source_census_sha256'],'prior_101_candidate_sha256':p['candidate_sha256'],'parent_73_candidate_sha256':pr.get('andromeda_extension_sha256'),'input_counts':{'andromeda_pending':sum(x.get('decision_status')=='pending' and x.get('country_class') in CORE for x in c['andromeda']),'prior_101':len(p['rows']),'parent_73':len(pp),'remaining_pending_scanned':sum(x.get('decision_status')=='pending' and x.get('country_class') in CORE and str(x['external_hotel_id']) not in prior_ids|parent_ids for x in c['andromeda'])},'result_counts':{'prior_101_high_current_recheck':sum(z[2]=='prior_101' for z in high),'prior_101_needs_extra':len(p['rows'])-sum(z[2]=='prior_101' for z in high),'parent_73_high_current_recheck':ph,'parent_73_needs_extra':len(pp)-ph,'new_cross_provider_high_current_recheck':nh,'new_cross_provider_reviewed_score_ge_90':len(prov),'high_current_recheck_total':len(high),'andromeda_evidence_queue_total_after_extension':len(p['rows'])+len(pp)+len(new),'live_anex_hotelcode_required':int(pr.get('live_anex_bridge_count') or 0),'consolidated_priority_dossier_total':len(p['rows'])+len(pp)+len(new)+int(pr.get('live_anex_bridge_count') or 0)},'needs_extra_reason_counts_nonexclusive':dict(sorted(rc.items())),'high_country_counts':dict(sorted(countries.items())),'new_cross_provider_country_counts':dict(sorted(collections.Counter(z['country'] for z in new).items())),'high_current_recheck_sha256':sha(high),'new_cross_provider_sha256':sha([[z['external_hotel_id'],z['proposed_local_id']] for z in new]),'high_current_recheck_pairs':high,'new_cross_provider_rows':new,'live_anex_bridge_pairs':pr.get('live_anex_bridge_pairs',[]),'policy':{'generic_tokens':sorted(GEN),'meaningful_qualifier_groups':[sorted(g) for g in GROUPS],'qualifier_asymmetry':'fail_closed','same_provider_occupied_target':'accepted sibling or active ANEX source-name >=95','new_bridge_score_min':95,'new_margin_min':8,'new_local_name_score_min':90,'new_parent_support_min':5,'new_parent_ratio_min':.9,'star_difference_max_when_numeric':1,'duplicate_target':'fail_closed','coordinates':'no saved supplier coordinates => no coordinate override','future_acceptance':'server-current same-transaction recompute/preservation guards + post-COMMIT per-row readback'},'database_writes':0,'mapping_writes':0,'supplier_calls':0,'tourvisor_calls':0,'write_authority':'none; CURRENT recompute required'}
    report['pre_primary_high_current_recheck_pairs'] = pre_primary_high
    report['primary_recheck_rows'] = checked
    report['primary_recheck_sha256'] = sha(checked)
    report['result_counts']['primary_recheck_examined'] = len(checked)
    report['result_counts']['primary_recheck_held'] = sum(bool(z['reason_codes']) for z in checked)
    report['result_counts']['pre_primary_high_current_recheck_total'] = len(pre_primary_high)
    report['result_counts']['new_cross_provider_needs_extra'] = len(new) - nh
    report['policy']['primary_names'] = 'shared full-primary/subset predicate from existing parent reviewer; held is not no-match'
    report['policy']['star_semantics'] = 'explicit numeric label only; dictionary starKey is never a star count'
    return report

def main():
    a=argparse.ArgumentParser(); a.add_argument('--census',required=True); a.add_argument('--previous-review',required=True); a.add_argument('--parent-report',required=True); a.add_argument('--output',required=True); x=a.parse_args(); cp=Path(x.census); c=json.loads(cp.read_text()); p=json.loads(Path(x.previous_review).read_text()); pr=json.loads(Path(x.parent_report).read_text()); actual=hashlib.sha256(cp.read_bytes()).hexdigest()
    if c.get('operation_id')!=OP or p.get('operation_id')!=OP or p.get('source_census_sha256')!=actual: raise SystemExit('source contract mismatch')
    pp=pr.get('andromeda_extension_pairs') or []
    if p.get('selected_count')!=101 or len(p.get('rows') or [])!=101 or pr.get('andromeda_extension_count')!=73 or len(pp)!=73 or len({str(z[0]) for z in pp})!=73 or len({int(z[1]) for z in pp})!=73 or pr.get('live_anex_bridge_count')!=6: raise SystemExit('evidence count contract mismatch')
    if pr.get('source_census_sha256') not in (None,actual): raise SystemExit('parent census mismatch')
    with Path(x.output).open('x', encoding='utf-8') as output:
        output.write(json.dumps(build(c,p,pr),ensure_ascii=False,sort_keys=True,indent=2)+'\n')
if __name__=='__main__': main()
