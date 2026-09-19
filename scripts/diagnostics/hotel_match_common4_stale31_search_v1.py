#!/usr/bin/env python3
"""COMMON4 exact-TV-hotel identity acquisition for 31 stale saved-tour details.
One new current tour detail first when available; otherwise bounded exact hotel-ID search.
No /continue, no /dates, no mapping writes.
"""
import collections, datetime as dt, fcntl, hashlib, json, os, pathlib, re, subprocess, sys, time
import urllib.error, urllib.parse, urllib.request
from zoneinfo import ZoneInfo

OP='hotel-match-common4-stale31-search-1971-20260919-v1'
DAY='2026-09-19'
LIMIT=3000
KNOWN_FLOOR=980
CALL_CAP=180
BASE='https://api.tourvisor.ru/search/api/v1'
BODY_LIMIT=16*1024*1024
ROUTES={
  13:('anex','operator_5','agent.anextour.ru'),
  18:('biblio_globus','operator_115','www.bgoperator.ru'),
  25:('funsun','operator_315','b2b.fstravel.com'),
  43:('intourist','operator_342','searchtour.intourist.ru'),
}

def encode(x): return (json.dumps(x,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
def save(path,obj):
    raw=encode(obj); path=pathlib.Path(path); path.parent.mkdir(parents=True,exist_ok=True)
    with open(path,'xb') as f:
        if f.write(raw)!=len(raw): raise RuntimeError('short_write')
        f.flush(); os.fsync(f.fileno())
        if hasattr(os,'fsync'): os.fsync(f.fileno())
    fd=os.open(str(path.parent),os.O_DIRECTORY)
    try: os.fsync(fd)
    finally: os.close(fd)
    return hashlib.sha256(raw).hexdigest()
def read_locked(f):
    f.seek(0); x=json.loads(f.read())
    if not isinstance(x,dict): raise RuntimeError('ledger_shape')
    return x
def write_locked(f,obj):
    raw=encode(obj); f.seek(0); f.truncate(0)
    if f.write(raw)!=len(raw): raise RuntimeError('short_ledger_write')
    f.flush(); os.fsync(f.fileno())
def numeric(v):
    s=str(v)
    return int(s) if re.fullmatch(r'[1-9][0-9]{0,21}',s) else None
def identity(obj,key):
    if not isinstance(obj,dict): return None
    child=obj.get(key)
    return numeric(child.get('id')) if isinstance(child,dict) else numeric(obj.get(key+'Id'))
def safe_body(data,token):
    raw=json.dumps(data,ensure_ascii=False)
    if token and token in raw: return False
    if re.search(r'"(?:access_token|oauth_token|refresh_token|password|authorization|secret)"\s*:',raw,re.I): return False
    if re.search(r'[?&](?:access_token|oauth_token|token|password|auth|secret|session)=',raw,re.I): return False
    return True
def link_evidence(operator_id,detail):
    u=detail.get('operatorLink') if isinstance(detail,dict) else None
    if not isinstance(u,str) or not u or len(u)>8192: return {'link_state':'missing','reason':'operator_link_missing'}
    try: p=urllib.parse.urlsplit(u); port=p.port
    except ValueError: return {'link_state':'invalid','reason':'operator_link_malformed'}
    expected=ROUTES[operator_id][2]
    if p.scheme!='https' or p.hostname!=expected or p.username or p.password or port or p.fragment:
        return {'link_state':'invalid','reason':'operator_link_origin','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
    pairs=urllib.parse.parse_qsl(p.query,keep_blank_values=True)
    if any(re.search(r'token|password|auth|secret|session',k,re.I) for k,_ in pairs):
        return {'link_state':'invalid','reason':'sensitive_operator_link','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
    out={'link_state':'captured','operator_link':u,'operator_link_sha256':hashlib.sha256(u.encode()).hexdigest(),
         'query_keys':[k for k,_ in pairs]}
    if operator_id==13:
        vals=[v for k,v in pairs if k.upper()=='HOTELLIST']; tokens=[x.strip() for v in vals for x in re.split('[,;]',v) if x.strip()!='']
        out.update(raw_identity_key='HOTELLIST',raw_identity_values=vals,raw_identity_tokens=tokens,
                   positive_native_candidates=[int(x) for x in tokens if re.fullmatch(r'[1-9][0-9]{0,8}',x)])
        out['link_state']='captured_single_native' if len(out['positive_native_candidates'])==1 and len(tokens)==1 else 'captured_ambiguous_native'
    elif operator_id in (25,43):
        vals=[v for k,v in pairs if k.upper()=='HOTELS']; tokens=[x.strip() for v in vals for x in re.split('[,;]',v) if x.strip()!='']
        out.update(raw_identity_key='HOTELS',raw_identity_values=vals,raw_identity_tokens=tokens,
                   positive_native_candidates=[int(x) for x in tokens if re.fullmatch(r'[1-9][0-9]{0,12}',x)])
        out['link_state']='captured_single_native' if len(out['positive_native_candidates'])==1 and len(tokens)==1 else 'captured_ambiguous_native'
    else:
        numeric_fields=[]
        for k,v in pairs:
            if re.fullmatch(r'[0-9]+',v or ''): numeric_fields.append({'key':k,'raw_value':v})
        out.update(raw_numeric_query_fields=numeric_fields,positive_native_candidates=[])
        out['link_state']='captured_biblio_unproven'
    return out

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl): return None

class Provider:
    def __init__(self,root,opdir):
        self.home=pathlib.Path(os.environ['HOME']); self.dir=opdir; self.used=0; self.last_accounted=None; self.counts=collections.Counter()
        self.dayfile=self.home/'.anytour-match/provider-quotas'/('tourvisor-test-'+DAY+'.json')
        self.opfile=self.dayfile.parent/('tourvisor-'+OP+'.json')
        if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY: raise RuntimeError('provider_day_mismatch')
        with open(self.dayfile,'r+b') as f:
            fcntl.flock(f,fcntl.LOCK_EX); d=read_locked(f)
            accounted=max(int(d['accounted_requests']),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
            if accounted<KNOWN_FLOOR or accounted>=min(LIMIT,int(d['owner_daily_limit'])): raise RuntimeError('daily_budget_guard')
            self.last_accounted=accounted
        save(self.opfile,{'operation':OP,'status':'reserved_before_provider_access','operation_cap':CALL_CAP,'used':0,'provider_day':DAY})
        save(self.dir/'quota-reservation.json',{'operation':OP,'accounted_before':accounted,'cap':CALL_CAP,'limit':LIMIT,'provider_day':DAY})
        code='require $argv[1]; fwrite(STDOUT,(string)(defined("TOURVISOR_JWT")?TOURVISOR_JWT:getenv("TOURVISOR_JWT")));'
        cp=subprocess.run(['php','-r',code,'--',str(root/'config.php')],capture_output=True,check=True)
        self.token=cp.stdout.decode().strip()
        if not self.token or '\n' in self.token: raise RuntimeError('token_invalid')
        self.opener=urllib.request.build_opener(NoRedirect())
    def charge(self,action,path,params):
        if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY: raise RuntimeError('provider_day_changed')
        with open(self.dayfile,'r+b') as df, open(self.opfile,'r+b') as of:
            fcntl.flock(df,fcntl.LOCK_EX); fcntl.flock(of,fcntl.LOCK_EX)
            d,o=read_locked(df),read_locked(of)
            if o['operation']!=OP or o['status'] not in ('reserved_before_provider_access','provider_accessed'): raise RuntimeError('terminal_operation')
            used=int(o['used']); prior=int(d.get('known_prior_attempt_floor',0)); match=int(d.get('match_new_attempts',0))
            charged=max(int(d['accounted_requests']),prior+match)
            if charged>=min(LIMIT,int(d['owner_daily_limit'])) or used>=CALL_CAP: raise RuntimeError('quota_exhausted')
            d['match_new_attempts']=match+1; d['accounted_requests']=charged+1; d.setdefault('operations',{})[OP]=used+1
            o.update(status='provider_accessed',used=used+1,last_action=action)
            write_locked(df,d); write_locked(of,o); self.used=used+1; self.last_accounted=charged+1
        self.counts[action]+=1
        save(self.dir/('request-%03d.json'%self.used),{'call':self.used,'accounted':self.last_accounted,'action':action,'path':path,'params':params})
    def call(self,action,path,params=None):
        params=params or {}
        if action not in ('search_start','search_status','search_results','tour_detail') or '/continue' in path or '/dates' in path:
            raise RuntimeError('forbidden_provider_action')
        self.charge(action,path,params)
        pairs=[(k,str(x).lower() if isinstance(x,bool) else str(x)) for k,v in params.items() for x in (v if isinstance(v,list) else [v])]
        url=BASE+path+('?' + urllib.parse.urlencode(pairs) if pairs else '')
        req=urllib.request.Request(url,headers={'Authorization':'Bearer '+self.token,'Accept':'application/json'})
        try: response=self.opener.open(req,timeout=65)
        except urllib.error.HTTPError as exc: response=exc
        with response: code=response.code; raw=response.read(BODY_LIMIT+1)
        if len(raw)>BODY_LIMIT: raise RuntimeError('body_limit')
        try: data=json.loads(raw)
        except (ValueError,UnicodeError): data={'unparsed_body_sha256':hashlib.sha256(raw).hexdigest()}
        if not safe_body(data,self.token):
            save(self.dir/('response-%03d.json'%self.used),{'http_status':code,'withheld_sensitive_body_sha256':hashlib.sha256(raw).hexdigest()}); raise RuntimeError('sensitive_response')
        save(self.dir/('response-%03d.json'%self.used),{'http_status':code,'raw_sha256':hashlib.sha256(raw).hexdigest(),'data':data})
        if code in (401,403,429): raise RuntimeError('http_'+str(code))
        if code!=200 and not (action=='tour_detail' and code==404): raise RuntimeError('http_'+str(code))
        return code,data
    def finish(self,status,result_sha):
        with open(self.opfile,'r+b') as f:
            fcntl.flock(f,fcntl.LOCK_EX); o=read_locked(f); o.update(status=status,result_sha256=result_sha); write_locked(f,o)

def ready(x):
    if not isinstance(x,dict): return False
    return (isinstance(x.get('progress'),(int,float)) and x['progress']>=100) or str(x.get('status','')).lower() in ('complete','completed','done','ready') or any(ready(v) for v in x.values() if isinstance(v,dict))
def result_rows(x):
    if isinstance(x,list): return x
    if isinstance(x,dict):
        for k in ('hotels','results','items'):
            if isinstance(x.get(k),list): return x[k]
    raise RuntimeError('results_shape')

def verify_detail(detail,hid,opid,tid):
    if not isinstance(detail,dict): raise RuntimeError('detail_shape')
    if identity(detail,'hotel')!=hid or identity(detail,'operator')!=opid or str(detail.get('id') or detail.get('tourId'))!=str(tid):
        raise RuntimeError('detail_identity_mismatch')
    ev={'tv_hotel_id':hid,'operator_id':opid,'operator':ROUTES[opid][0],'target_supplier_namespace':ROUTES[opid][1],'tour_id':str(tid),'safe_to_write_now':False}
    ev.update(link_evidence(opid,detail)); return ev

def detail_first(provider,edge):
    tid=edge.get('detail_first_tour_id')
    if not tid: return None
    code,detail=provider.call('tour_detail','/tours/'+str(tid),{'currency':'RUB'})
    if code==404:
        return {'tv_hotel_id':edge['tv_hotel_id'],'operator_id':edge['operator_id'],'tour_id':str(tid),'state':'fresh_detail_404','safe_to_write_now':False}
    ev=verify_detail(detail,edge['tv_hotel_id'],edge['operator_id'],tid); ev['state']='fresh_detail_verified'
    return ev

def run_search(provider,group,edges_by_key,search_no):
    ids=list(group['hotel_ids']); opid=int(group['operator_id'])
    if not (1<=len(ids)<=30): raise RuntimeError('search_batch_size')
    params={'departureId':group['departure_id'],'countryId':group['country_id'],'dateFrom':group['departure_date'],'dateTo':group['departure_date'],
            'nightsFrom':group['nights'],'nightsTo':group['nights'],'adults':group['adults'],'childs':group['childs'],
            'currency':'RUB','onlyCharter':False,'operatorIds':[opid],'hotelIds':ids}
    _,start=provider.call('search_start','/tours/search',params)
    sid=numeric(start.get('searchId') or start.get('id')) if isinstance(start,dict) else None
    if not sid: raise RuntimeError('search_id_missing')
    for delay in (4,7,10,14):
        time.sleep(delay); _,status=provider.call('search_status','/tours/search/%d/status'%sid,{'operatorStatus':False})
        if ready(status): break
    else: raise RuntimeError('initial_search_timeout')
    _,results=provider.call('search_results','/tours/search/%d'%sid,{'limit':10000})
    rows=result_rows(results); wanted=set(ids); found={}
    for h in rows:
        hid=numeric(h.get('id')) if isinstance(h,dict) else None
        if hid not in wanted: raise RuntimeError('unexpected_hotel_in_filtered_search')
        if hid in found: raise RuntimeError('duplicate_hotel_result')
        tours=h.get('tours') or []
        if any(identity(t,'operator')!=opid for t in tours): raise RuntimeError('foreign_or_missing_operator')
        usable=[t for t in tours if numeric(t.get('id') or t.get('tourId'))]
        if not usable:
            found[hid]={'tv_hotel_id':hid,'operator_id':opid,'state':'no_usable_tour','search_id':sid,'safe_to_write_now':False}; continue
        tour=usable[0]; tid=str(tour.get('id') or tour.get('tourId'))
        edge=edges_by_key.get((opid,hid))
        if not edge: raise RuntimeError('search_edge_missing')
        if tid==str(edge['stale_tour_id']):
            ev={'tv_hotel_id':hid,'operator_id':opid,'tour_id':tid,'state':'returned_stale_tour_id_not_retried','search_id':sid,'safe_to_write_now':False}
        else:
            code,detail=provider.call('tour_detail','/tours/'+tid,{'currency':'RUB'})
            if code==404:
                ev={'tv_hotel_id':hid,'operator_id':opid,'tour_id':tid,'state':'search_detail_404','search_id':sid,'safe_to_write_now':False}
            else:
                ev=verify_detail(detail,hid,opid,tid); ev.update(state='search_detail_verified',search_id=sid)
        found[hid]=ev
        save(provider.dir/('hotel-%d-op%d-search%d.json'%(hid,opid,search_no)),ev)
    return sid,found

def execute(opdir,root):
    provider=None; state='terminal_failed_no_replay'; reason=None; plan=None; evidence={}; searches=[]; fresh_detail_attempts=0
    try:
        reservation=json.loads((opdir/'reservation.json').read_bytes())
        if reservation.get('operation')!=OP or reservation.get('state')!='reserved_before_db_provider': raise RuntimeError('reservation')
        manifest=json.loads((opdir/'payload/source-hashes.json').read_bytes())
        for name,digest in manifest.items():
            if pathlib.PurePath(name).name!=name or hashlib.sha256((opdir/'payload'/name).read_bytes()).hexdigest()!=digest: raise RuntimeError('source_digest')
        cp=subprocess.run(['php',str(opdir/'payload/hotel_match_common4_stale31_current_v1.php')],env={**os.environ,'MATCH_OPERATION_ID':OP,'MATCH_SOURCE_SHA':reservation['source_sha'],'MATCH_OPERATION_DIR':str(opdir)},capture_output=True,check=False)
        if cp.returncode: raise RuntimeError('current_plan_failed')
        plan=json.loads(cp.stdout)
        if plan.get('state')!='current_read_only_complete' or plan.get('operation')!=OP: raise RuntimeError('current_plan_state')
        plan_sha=save(opdir/'current-plan.json',plan)
        provider=Provider(root,opdir)
        edges={(int(e['operator_id']),int(e['tv_hotel_id'])):e for e in plan['eligible_edges']}
        fallback=set(edges)
        # Owner order: a different current saved tour detail first; captured links leave search acquisition.
        for key,edge in edges.items():
            if not edge.get('detail_first_tour_id'): continue
            fresh_detail_attempts+=1
            ev=detail_first(provider,edge); save(opdir/('hotel-%d-op%d-fresh-detail.json'%(edge['tv_hotel_id'],edge['operator_id'])),ev)
            if ev.get('state')=='fresh_detail_verified' and ev.get('link_state','').startswith('captured'):
                evidence[key]=ev; fallback.discard(key)
        # Exact-ID searches only for remaining edges, grouped by exact current compatible context.
        search_no=0
        for group in plan['context_groups']:
            ids=[int(h) for h in group['hotel_ids'] if (int(group['operator_id']),int(h)) in fallback]
            if not ids: continue
            for off in range(0,len(ids),30):
                g=dict(group); g['hotel_ids']=ids[off:off+30]; search_no+=1
                before=provider.used; sid,found=run_search(provider,g,edges,search_no)
                for hid,ev in found.items(): evidence[(int(g['operator_id']),int(hid))]=ev; fallback.discard((int(g['operator_id']),int(hid)))
                searches.append({'search_no':search_no,'operator_id':int(g['operator_id']),'search_id':sid,'hotel_ids':g['hotel_ids'],'found_ids':sorted(found),'missing_ids':[h for h in g['hotel_ids'] if h not in found],'calls':provider.used-before,'context':{k:v for k,v in g.items() if k!='hotel_ids'}})
                save(opdir/('search-%02d-result.json'%search_no),searches[-1])
        state='completed_read_only'
    except Exception as exc:
        reason=type(exc).__name__+':'+str(exc) if isinstance(exc,RuntimeError) else type(exc).__name__
    out={'operation':OP,'state':state,'reason':reason,'no_replay':True,'source_sha':(reservation.get('source_sha') if 'reservation' in locals() else None),
         'current_plan_sha256':(hashlib.sha256((opdir/'current-plan.json').read_bytes()).hexdigest() if (opdir/'current-plan.json').exists() else None),
         'input_count':31,'eligible_edges':(len(plan['eligible_edges']) if plan else None),'current_skipped':(plan.get('skipped') if plan else None),
         'fresh_detail_attempts':fresh_detail_attempts,'searches':searches,'captured_edges':sorted(evidence.values(),key=lambda e:(e.get('operator_id',0),e.get('tv_hotel_id',0))),
         'remaining_not_returned':[{'operator_id':k[0],'tv_hotel_id':k[1]} for k in sorted(fallback)],
         'provider_calls':provider.used if provider else 0,'call_counts':dict(provider.counts) if provider else {},'daily_accounted_after':provider.last_accounted if provider else None,
         'search_calls':int(provider.counts.get('search_start',0)) if provider else 0,'status_calls':int(provider.counts.get('search_status',0)) if provider else 0,
         'results_calls':int(provider.counts.get('search_results',0)) if provider else 0,'detail_calls':int(provider.counts.get('tour_detail',0)) if provider else 0,
         'continue_calls':0,'dates_calls':0,'samo_calls':0,'database_writes':0,'mapping_writes':0}
    sha=save(opdir/'result.json',out)
    if provider: provider.finish(state,sha)
    save(opdir/'receipt.json',{'operation':OP,'state':state,'result_sha256':sha,'provider_calls':out['provider_calls'],'mapping_writes':0,'database_writes':0,'no_replay':True})
    print(json.dumps({'state':state,'reason':reason,'eligible':out['eligible_edges'],'captured':len(out['captured_edges']),'remaining':len(out['remaining_not_returned']),'calls':out['provider_calls'],'daily':out['daily_accounted_after']}))
    return 0 if state=='completed_read_only' else 2

def selftest():
    assert set(ROUTES)=={13,18,25,43}
    assert link_evidence(13,{'operatorLink':'https://agent.anextour.ru/search/tour?HOTELLIST=123'})['link_state']=='captured_single_native'
    assert link_evidence(25,{'operatorLink':'https://b2b.fstravel.com/x?HOTELS=12,13'})['link_state']=='captured_ambiguous_native'
    assert link_evidence(43,{'operatorLink':'https://searchtour.intourist.ru/x?HOTELS=99'})['positive_native_candidates']==[99]
    b=link_evidence(18,{'operatorLink':'https://www.bgoperator.ru/x?F4=0012&id_price=33'})
    assert b['link_state']=='captured_biblio_unproven' and b['positive_native_candidates']==[] and len(b['raw_numeric_query_fields'])==2
    assert link_evidence(25,{'operatorLink':'https://evil.example/x?HOTELS=1'})['link_state']=='invalid'
    assert identity({'hotel':{'id':7}},'hotel')==7 and identity({},'operator') is None
    print('hotel-match-common4-stale31-search-v1: PASS')

if __name__=='__main__':
    if '--self-test' in sys.argv: selftest()
    elif '--execute' in sys.argv: sys.exit(execute(pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['ANYTOOUR_ROOT'])))
    else: raise SystemExit('disabled')
