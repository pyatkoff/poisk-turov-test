"""Correlate saved ANEX-only Andromeda hotel evidence without supplier requests."""
import hashlib,json,os
from pathlib import Path
import anex_search3_owner_decisions as owner
from andromeda_catalog_sync import names
from andromeda_hotel_candidates import save

def analyse(data):
    hotels={int(h['id']):h for h in data['hotels']}
    aliases={i:set(names(h['name'])) for i,h in hotels.items()}
    for a in data['aliases']:aliases[int(a['hotel_id'])].update(names(a['alias']))
    index={}
    for i,ns in aliases.items():
        for n in ns:index.setdefault(n,set()).add(i)
    rows=[]
    for row in data['rows']:
        ids=set()
        for n in names(row['hotel']):ids.update(index.get(n,set()))
        identity=row['identity'] or {}; existing=identity.get('decision_status')
        accepted=row['anex_accepted_local_id']; proposed=None;reason='no_unique_identity'
        if existing=='accepted':reason='already_accepted'
        elif existing=='conflict':reason='preserve_conflict'
        elif row['supplier_namespace']!='andromeda_catalog':reason='operator_namespace_needs_specific_evidence'
        elif len(ids)==1:
            candidate=next(iter(ids))
            if accepted is not None and int(accepted)!=candidate:reason='anex_name_conflict'
            else:proposed=candidate;reason='unique_egypt_current_or_former_name'+('_and_accepted_anex_id' if accepted else '')
        rows.append({**row,'name_candidates':sorted(ids),'proposed_local_id':proposed,'reason':reason,'target':hotels.get(proposed)})
    return {'operator_filter':'5','supplier_calls':0,'database_writes':0,'saved_pages_read':data['saved_pages_read'],'anex_offer_rows':data['anex_offer_rows'],'counts':{s:sum(r['reason']==s for r in rows) for s in sorted(set(r['reason'] for r in rows))},'rows':rows,'csp_source_lines':data['csp_source_lines']}

def main():
    dest=Path(os.environ['RUNNER_TEMP'])/'andromeda-anex-saved';dest.mkdir(exist_ok=True)
    save(dest/'reservation.json',{'state':'inflight','operator_filter':'5','supplier_calls':0},exclusive=True)
    source=Path(__file__).with_suffix('.php').read_text().removeprefix('<?php')
    data=owner.ssh_php(source,{},maximum_bytes=4000000)
    if data.get('status')!='ok':raise ValueError('saved identity export failed')
    save(dest/'saved-hotel-evidence.json',data,exclusive=True)
    report=analyse(data);save(dest/'anex-only-correlation.json',report,exclusive=True)
    print(json.dumps(report,ensure_ascii=False))
    print('EVIDENCE_SHA256='+hashlib.sha256((dest/'saved-hotel-evidence.json').read_bytes()).hexdigest())
if __name__=='__main__':main()
