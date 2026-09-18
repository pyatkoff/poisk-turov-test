"""Offline MATCH evidence only: separate proved geography from hotel identity.

Uses the existing census/source queue. Never returns accepted mappings, opens a
network connection, writes a DB, or prepares executable SQL/operation capsules.
Geographic suffixes are removed only when the literal is derived from the row's
own geography fields. Significant building/wing/market qualifiers survive.
"""
from __future__ import annotations
import argparse
from collections import Counter, defaultdict
import hashlib
import json
from pathlib import Path
import re
from rapidfuzz import fuzz, process
from hotel_match_core8_identity_review import (
    Review, COUNTRIES, FORMER, GENERIC, QUALIFIERS, NONDISTINCT,
    country, digest, distance, text, variants,
)

PROTECTED = QUALIFIERS | {'only','gentleman','annexe','annexes','gardens','mountain','pool','sea','building','tower','towers','север','юг','северный','южный','корпус','аннекс'}
PROTECTED = {text(word) for word in PROTECTED}
# Spelling equivalence only, NOT a guessed parent-geography dictionary.
# A spelling has no effect unless its geography occurs in a stored row field.
GEO_SPELLINGS = (
    ('стамбул','istanbul'), ('лалели','laleli'), ('султанахмет','sultanahmet'),
    ('сиркеджи','sirkeci'), ('аксарай','aksaray'), ('фатих','fatih'),
    ('беязыт','beyazit'), ('бейоглу','beyoglu'), ('шишли','sisli'),
    ('таксим','taksim'), ('хургада','hurghada'),
    ('шарм эль шейх','sharm el sheikh','sharm el sheih'),
    ('наама бей','наама бэй','naama bay'), ('набк','набк бей','набк бэй','nabq','nabq bay'),
    ('макади','макади бей','макади бэй','makadi','makadi bay'),
    ('сахль хашиш','сахл хашиш','sahl hasheesh','sahl hashish'),
    ('эль гуна','el gouna'), ('бодрум','bodrum'), ('анталья','antalya'),
    ('хадаба','hadaba'), ('марса алам','marsa alam'),
    ('пхукет','phuket'), ('паттайя','pattaya'), ('самуи','samui'),
    ('нячанг','nha trang'), ('фукуок','phu quoc'), ('дананг','da nang'),
    ('дубай','dubai'), ('шарджа','sharjah'), ('абу даби','abu dhabi'),
)
GROUPS = [tuple(dict.fromkeys(text(s) for s in group)) for group in GEO_SPELLINGS]
LOOKUP = {s: group[0] for group in GROUPS for s in group}
FORM_LOOKUP = {s: group for group in GROUPS for s in group}
TRANSLIT = dict(zip('абвгдежзиклмнопрстуфцыэюя',
                    ['a','b','v','g','d','e','zh','z','i','k','l','m','n','o','p','r','s','t','u','f','ts','y','e','yu','ya']))
TRANSLIT.update({'х':'h','ч':'ch','ш':'sh','щ':'shch','ъ':'','ь':''})
NON_HOTEL = re.compile(r'^(?:(?:roulette|рулетка|тур)\b|(?:fortuna|фортуна)\s+[1-5]\b)',re.I)

def contains_phrase(value: str, phrase: str) -> bool:
    return bool(phrase) and (' '+phrase+' ') in (' '+value+' ')

def geo_key(value: str) -> str:
    raw=text(value)
    return LOOKUP.get(raw,raw)

def name_forms(names: list[str]) -> set[str]:
    # Keep word order here: remove only full prefix/suffix phrases, never a bag of tokens.
    out=set()
    for name in names:
        for item in [name,FORMER.sub(' ',name),*FORMER.findall(name)]:
            value=' '.join(w for w in text(item).split() if w not in GENERIC)
            if value:out.add(value)
    return out

def own_geo_spellings(fields: list[str]) -> dict[str,str]:
    result={}
    for field in fields:
        raw=text(field)
        if not raw or country(raw):continue
        candidates={raw}
        # A delimited child-parent label is not flattened to any arbitrary word.
        candidates.update(text(p) for p in re.split(r'[-/,;()]',field) if len(text(p))>=4)
        for known in FORM_LOOKUP:
            if contains_phrase(raw,known):candidates.add(known)
        for value in sorted(candidates):
            forms=set(FORM_LOOKUP.get(value,(value,)))
            latin=''.join(TRANSLIT.get(c,c) for c in value)
            forms.add(latin)
            if 'h' in latin:forms.add(latin.replace('h','kh'))
            for spelling in forms:
                tokens=spelling.split()
                if not tokens or len(spelling)<4 or set(tokens)&PROTECTED:continue
                if any(w.isdigit() for w in tokens):continue
                result[spelling]=field
    return result

def stripped_forms(names: list[str],fields: list[str]) -> dict[str,list[dict]]:
    spellings=own_geo_spellings(fields);out=defaultdict(list)
    for name in sorted(name_forms(names)):
        states={(name,())}
        for _ in range(3):
            expanded=set(states)
            for current,removed in states:
                for spelling,field in sorted(spellings.items()):
                    for prefix in (True,False):
                        part=spelling+' ' if prefix else ' '+spelling
                        if (current.startswith(part) if prefix else current.endswith(part)):
                            new=current[len(part):] if prefix else current[:-len(part)]
                            if new:expanded.add((new,removed+((spelling,field),)))
            if expanded==states:break
            states=expanded
        for residual,removed in sorted(states):
            key=' '.join(sorted(residual.split()))
            proof={'original':name,'removed':[{'spelling':s,'geography_field':g} for s,g in removed]}
            if proof not in out[key]:out[key].append(proof)
    return dict(out)

class GeoIndex:
    """Infers parent candidates only from actual local row region/subregion pairs."""
    def __init__(self,hotels: dict[str,dict]):
        self.by_country=defaultdict(lambda:defaultdict(set))
        for h in hotels.values():
            cc=h['country_class'];root=geo_key(h.get('region_name') or '')
            if not root or country(root):continue
            for raw in [h.get('region_name'),h.get('subregion_name')]:
                key=geo_key(raw or '')
                if key and not country(key):self.by_country[cc][key].add(root)

    def roots(self,cc: str,fields: list[str]) -> set[str]:
        roots=set();index=self.by_country.get(cc,{})
        for field in fields:
            key=geo_key(field)
            if key in index:roots.update(index[key]);continue
            # Longest observed geography wins. Do not turn North/South Male into Male.
            matches=[k for k in index if len(k)>=4 and contains_phrase(key,k)]
            if matches:
                longest=max(len(k) for k in matches)
                for k in matches:
                    if len(k)==longest:roots.update(index[k])
        return roots

    def relation(self,source: dict,target: dict) -> dict:
        cc=source.get('country_class');target_cc=target.get('country_class')
        sg=source.get('geography',[]);sr=self.roots(cc,sg)
        target_root=geo_key(target.get('region_name') or '')
        if cc!=target_cc:return {'status':'country_conflict'}
        if sr and target_root and target_root not in sr:
            return {'status':'region_conflict','source_region_candidates':sorted(sr),'target_region':target_root}
        shared=[]
        for raw in sg:
            sk=geo_key(raw)
            for level in ['subregion_name','region_name']:
                tk=geo_key(target.get(level) or '')
                if not sk or not tk or country(tk):continue
                if sk==tk or (len(tk)>=4 and contains_phrase(sk,tk)):
                    shared.append({'source_field':raw,'target_field':target[level],'target_level':level,'normalized':tk})
        return {'status':'supported' if shared else 'unknown','shared_fields':shared,
                'specific':any(p['target_level']=='subregion_name' for p in shared)}

class GeoContextReview:
    def __init__(self,census: dict,baseline: dict):
        self.base=Review(census,baseline);self.geo=GeoIndex(self.base.h)
        self.native={lid:[h['name']] for lid,h in self.base.h.items()}
        for r in census['aliases']:
            if str(r['hotel_id']) in self.native:self.native[str(r['hotel_id'])].append(r['alias'])
        self.forms={};self.index=defaultdict(lambda:defaultdict(set))
        self.exact=defaultdict(lambda:defaultdict(set))
        for lid,h in self.base.h.items():
            fields=[h.get('region_name') or '',h.get('subregion_name') or '']
            forms=stripped_forms(self.native[lid],fields);self.forms[lid]=forms
            for key in forms:self.index[h['country_class']][key].add(lid)
            for name in self.native[lid]:
                for key in variants(name):self.exact[h['country_class']][key].add(lid)
        self.choices={cc:sorted(keys) for cc,keys in self.index.items()}
        self.bridge_conflicts=[];self.tainted_targets=set()
        for row in census['andromeda']:
            lid=str(row.get('local_hotel_id'))
            if row['supplier_namespace']!='andromeda_catalog' or row['decision_status']!='accepted' or lid not in self.base.h:continue
            s=self.base.andromeda_source(row);h=self.base.h[lid]
            rel=self.geo.relation(s,h);dist=distance(s,h)
            reason='coordinate_conflict' if dist is not None and dist>5 else rel['status']
            if reason in ['region_conflict','country_conflict','coordinate_conflict']:
                self.tainted_targets.add(lid)
                self.bridge_conflicts.append({'provider':'andromeda','external_id':s['external_id'],'local_id':int(lid),
                    'source_names':s['names'],'source_geography':s['geography'],'target_name':h['name'],
                    'target_region':h.get('region_name'),'target_subregion':h.get('subregion_name'),
                    'reason':reason,'geography_proof':rel,'distance_km':dist,'evidence_sha256':s['evidence_sha256'],
                    'action':'independent_current_evidence_required_do_not_overwrite'})

    def removal_supported(self,fields: list[str],removed_field: str,cc: str) -> bool:
        key=geo_key(removed_field)
        if any(geo_key(f)==key or contains_phrase(geo_key(f),key) for f in fields):return True
        # A source district may establish its actual stored local parent, not a sibling district.
        roots=self.geo.roots(cc,fields)
        return roots=={key}

    def pair_proof(self,source: dict,lid: str,keys: list[str],forms: dict) -> list[dict]:
        h=self.base.h[lid];target_fields=[h.get('region_name') or '',h.get('subregion_name') or '']
        result=[]
        for key in keys:
            sp=[p for p in forms[key] if all(self.removal_supported(target_fields,x['geography_field'],source['country_class']) for x in p['removed'])]
            tp=[p for p in self.forms[lid][key] if all(self.removal_supported(source['geography'],x['geography_field'],source['country_class']) for x in p['removed'])]
            if sp and tp:result.append({'key':key,'source':sp,'target':tp})
        return result

    def evaluate(self,s: dict) -> dict:
        cc=s.get('country_class');out={'source':s,'status':'no_geographic_name_proof','accepted':False}
        if cc not in COUNTRIES or s.get('country_conflict') or s.get('source_invalid'):
            out['status']='source_guard';return out
        if any(NON_HOTEL.search(text(n)) for n in s['names']):out['status']='non_specific_accommodation';return out
        forms=stripped_forms(s['names'],s.get('geography',[]))
        exact_ids=set()
        for name in s['names']:
            for key in variants(name):exact_ids.update(self.exact[cc].get(key,set()))
        # Separate from completed exact-match passes and the blocked write package.
        if exact_ids:out['status']='existing_exact_lane_not_repeated';return out
        matches=defaultdict(set)
        for key in forms:
            for lid in self.index[cc].get(key,set()):matches[lid].add(key)
        out['candidate_ids']=sorted(map(int,matches))
        if not matches:return out
        if len(matches)!=1:out['status']='ambiguous_countrywide_identity';return out
        lid=next(iter(matches));h=self.base.h[lid];keys=sorted(matches[lid]);relation=self.geo.relation(s,h)
        proof=self.pair_proof(s,lid,keys,forms)
        out.update(local_id=int(lid),target_name=h['name'],target_region=h.get('region_name'),target_subregion=h.get('subregion_name'),
            geography_proof=relation,name_proof=proof)
        if relation['status']!='supported':out['status']=relation['status'];return out
        if not proof:out['status']='unconfirmed_removed_geography';return out
        keys=[p['key'] for p in proof]
        dist=distance(s,h);out['distance_km']=dist
        if dist is not None and dist>5:out['status']='coordinate_conflict';return out
        if lid in self.tainted_targets:out['status']='accepted_bridge_conflict';return out
        # Preserve current-name qualifiers even when an old alias happens to match.
        def current_qualifiers(name):
            tokens=set(text(FORMER.sub(' ',name)).split())
            return (tokens & PROTECTED) | {w for w in tokens if w.isdigit() or w in {'ii','iii','iv'}}
        sq=current_qualifiers(s['names'][0]);tq=current_qualifiers(h['name'])
        if sq!=tq:out['status']='significant_qualifier_difference';return out
        bridges=[]
        for bs in self.base.bridge.get(lid,[]):
            if bs['provider']==s['provider'] or bs.get('source_invalid') or bs.get('country_conflict'):continue
            if bs['provider']=='andromeda' and bs.get('supplier_namespace')!='andromeda_catalog':continue
            if self.geo.relation(bs,h)['status']!='supported':continue
            bd=distance(bs,h)
            if bd is not None and bd>5:continue
            bkeys=set(stripped_forms(bs['names'],bs['geography']))
            if bkeys & set(keys):bridges.append({'provider':bs['provider'],'external_id':bs['external_id'],'name_keys':sorted(bkeys&set(keys))})
        out['independent_bridge_proofs']=bridges
        distinct={w for key in keys for w in key.split() if len(w)>=4 and w not in NONDISTINCT and w not in PROTECTED}
        if not distinct or (len(distinct)==1 and not relation['specific'] and not bridges):
            out['status']='low_information_name';return out
        # Full fuzzy competition across unique local IDs, never top-k alias truncation.
        competitors={}
        for key in keys:
            for candidate,score,_ in process.extract(key,self.choices[cc],scorer=fuzz.ratio,score_cutoff=88,limit=None):
                for rival in self.index[cc][candidate]:
                    if rival!=lid:competitors[rival]=max(score,competitors.get(rival,0))
        margin=100-max(competitors.values(),default=88)
        out['name_margin']=round(margin,4)
        if margin<8:out['status']='small_name_margin';return out
        label=str(s.get('category_label') or '')
        if re.fullmatch('[1-5]',label) and int(label)!=int(h.get('category') or 0) and not bridges:
            out['status']='category_needs_independent_evidence';return out
        out['status']='prepared_geography_evidence'
        out['current_revalidation_required']=True
        return out

    def run(self) -> dict:
        queue=self.base.source_queue();rows=[self.evaluate(s) for s in queue]
        ready=[r for r in rows if r['status']=='prepared_geography_evidence']
        live=[r for r in rows if r['source'].get('observed')]
        return {'schema_version':1,'scope':'offline_geographic_name_evidence_not_mapping_acceptance',
            'census_operation_id':self.base.d['operation_id'],'census_generated_at':self.base.d['generated_at_utc'],
            'examined':len(rows),'counts':dict(Counter(r['status'] for r in rows)),
            'prepared_count':len(ready),'prepared_by_provider':dict(Counter(r['source']['provider'] for r in ready)),
            'live_examined':len(live),'live_prepared':sum(r['status']=='prepared_geography_evidence' for r in live),
            'live_observation_weight':sum(r['source'].get('search_count') or 0 for r in ready if r['source'].get('observed')),
            'bridge_conflicts':self.bridge_conflicts,'tainted_local_ids':sorted(map(int,self.tainted_targets)),
            'rows':rows,'database_writes':0,'mapping_writes':0,'supplier_calls':0,'tourvisor_calls':0,
            'live_apply_authorized':False,'snapshot_is_not_write_authority':True}

def main():
    p=argparse.ArgumentParser();p.add_argument('census',type=Path);p.add_argument('baseline',type=Path);p.add_argument('output',type=Path)
    p.add_argument('--census-sha256',required=True);p.add_argument('--baseline-sha256',required=True)
    args=p.parse_args();cb=args.census.read_bytes();bb=args.baseline.read_bytes()
    if hashlib.sha256(cb).hexdigest()!=args.census_sha256 or hashlib.sha256(bb).hexdigest()!=args.baseline_sha256:raise ValueError('input_digest_mismatch')
    result=GeoContextReview(json.loads(cb),json.loads(bb)).run()
    result.update(census_sha256=args.census_sha256,baseline_sha256=args.baseline_sha256)
    args.output.write_text(json.dumps(result,ensure_ascii=False,sort_keys=True,indent=2)+'\n')
    print(json.dumps({k:v for k,v in result.items() if k not in ['rows','bridge_conflicts']},ensure_ascii=False,sort_keys=True,indent=2))
if __name__=='__main__':main()
