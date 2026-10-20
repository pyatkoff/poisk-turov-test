#!/usr/bin/env python3
"""Bounded MATCH hotel-identity acquisition v3. Never drains a search or writes mappings."""
import collections, datetime as dt, fcntl, hashlib, json, os, pathlib, re, subprocess, sys, time
import urllib.error, urllib.parse, urllib.request
from zoneinfo import ZoneInfo

OP='hotel-match-anex-rolling30-1971-20260919-v3'
DAY='2026-09-19'
LIMIT=3000
KNOWN_FLOOR=689
CALL_CAP=180
MAX_BATCHES=6
BASE='https://api.tourvisor.ru/search/api/v1'
BODY_LIMIT=16*1024*1024

def encode(x): return (json.dumps(x,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
def save(path,obj):
    raw=encode(obj); path=pathlib.Path(path); path.parent.mkdir(parents=True,exist_ok=True)
    with open(path,'xb') as f:
        if f.write(raw)!=len(raw): raise RuntimeError('short_write')
        f.flush(); os.fsync(f.fileno())
    fd=os.open(str(path.parent),os.O_DIRECTORY)
    try: os.fsync(fd)
    finally: os.close(fd)
    return hashlib.sha256(raw).hexdigest()
def read(f):
    f.seek(0); x=json.loads(f.read())
    if not isinstance(x,dict): raise RuntimeError('ledger_shape')
    return x
def write(f,obj):
    raw=encode(obj); f.seek(0); f.truncate(0)
    if f.write(raw)!=len(raw): raise RuntimeError('short_ledger_write')
    f.flush(); os.fsync(f.fileno())
def numeric(v): return int(v) if re.fullmatch(r'[1-9][0-9]{0,21}',str(v)) else None
def identity(obj,key):
    if not isinstance(obj,dict): return None
    child=obj.get(key)
    return numeric(child.get('id')) if isinstance(child,dict) else numeric(obj.get(key+'Id'))

def batches_next(pool,attempts,found,carry):
    pending=[i for i in carry if i not in found and attempts[i]==1]
    new=[i for i in pool if attempts[i]==0 and i not in found]
    if not new: return [],[]
    ids=(pending[:29]+new)[:30]
    return ids,[i for i in ids if attempts[i]>0]

def strict_link(detail):
    u=detail.get('operatorLink') if isinstance(detail,dict) else None
    if not isinstance(u,str) or len(u)>4096: return {'reason':'operator_link_missing'}
    try: p=urllib.parse.urlsplit(u); port=p.port
    except ValueError: return {'reason':'operator_link_malformed'}
    if p.scheme!='https' or p.hostname!='agent.anextour.ru' or p.path!='/search/tour' or p.username or p.password or port or p.fragment:
        return {'reason':'operator_link_origin','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
    pairs=urllib.parse.parse_qsl(p.query,keep_blank_values=True)
    if any(re.search(r'token|password|auth|secret|session',k,re.I) for k,_ in pairs):
        return {'reason':'sensitive_operator_link','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
    vals=[v for k,v in pairs if k.upper()=='HOTELLIST']
    tokens=[x.strip() for val in vals for x in re.split('[,;]',val)]
    out={'operator_link':u,'raw_hotellist_values':vals,'raw_signed_tokens':tokens}
    if len(vals)!=1 or len(tokens)!=1 or not re.fullmatch(r'[1-9][0-9]{0,7}',tokens[0]):
        out['reason']='ambiguous_or_missing_hotellist'
    else: out['native_anex_id']=int(tokens[0])
    return out

def safe_body(data,token):
    raw=json.dumps(data,ensure_ascii=False)
    if token and token in raw: return False
    if re.search(r'"(?:access_token|oauth_token|refresh_token|password|authorization|secret)"\s*:',raw,re.I): return False
    if re.search(r'[?&](?:access_token|oauth_token|token|password|auth|secret|session)=',raw,re.I): return False
    return True

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl): return None

class Provider:
    def __init__(self,root,opdir):
        self.home=pathlib.Path(os.environ['HOME']); self.dir=opdir; self.used=0; self.last_accounted=None; self.counts=collections.Counter()
        self.dayfile=self.home/'.anytour-match/provider-quotas'/('tourvisor-test-'+DAY+'.json')
        self.opfile=self.dayfile.parent/('tourvisor-'+OP+'.json')
        if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY: raise RuntimeError('provider_day_mismatch')
        with open(self.dayfile,'r+b') as f:
            fcntl.flock(f,fcntl.LOCK_EX); d=read(f)
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
            d,o=read(df),read(of)
            if o['operation']!=OP or o['status'] not in ('reserved_before_provider_access','provider_accessed'): raise RuntimeError('terminal_operation')
            used=int(o['used']); prior=int(d.get('known_prior_attempt_floor',0)); match=int(d.get('match_new_attempts',0))
            charged=max(int(d['accounted_requests']),prior+match)
            if charged>=min(LIMIT,int(d['owner_daily_limit'])) or used>=CALL_CAP: raise RuntimeError('quota_exhausted')
            d['match_new_attempts']=match+1; d['accounted_requests']=charged+1; d.setdefault('operations',{})[OP]=used+1
            o.update(status='provider_accessed',used=used+1,last_action=action)
            write(df,d); write(of,o); self.used=used+1; self.last_accounted=charged+1
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
        digest=save(self.dir/('response-%03d.json'%self.used),{'http_status':code,'raw_sha256':hashlib.sha256(raw).hexdigest(),'raw_body_utf8':raw.decode('utf-8'),'data':data})
        if code in (401,403,429): raise RuntimeError('http_'+str(code))
        if code!=200 and not (action=='tour_detail' and code==404): raise RuntimeError('http_'+str(code))
        return code,data,digest
    def finish(self,status,result_sha):
        with open(self.opfile,'r+b') as f:
            fcntl.flock(f,fcntl.LOCK_EX); o=read(f); o.update(status=status,result_sha256=result_sha); write(f,o)

def ready(x):
    if not isinstance(x,dict): return False
    return (isinstance(x.get('progress'),(int,float)) and x['progress']>=100) or str(x.get('status','')).lower() in ('complete','completed','done','ready') or any(ready(v) for v in x.values() if isinstance(v,dict))
def result_rows(x):
    if isinstance(x,list): return x
    if isinstance(x,dict):
        for k in ('hotels','results','items'):
            if isinstance(x.get(k),list): return x[k]
    raise RuntimeError('results_shape')

def run_batch(provider,opdir,number,ctx,ids):
    params={'departureId':ctx['departure_id'],'countryId':ctx['country_id'],'dateFrom':ctx['date_from'],'dateTo':ctx['date_to'],'nightsFrom':7,'nightsTo':10,'adults':2,'currency':'RUB','onlyCharter':False,'operatorIds':[13],'hotelIds':ids}
    _,start,_=provider.call('search_start','/tours/search',params)
    sid=numeric(start.get('searchId') or start.get('id')) if isinstance(start,dict) else None
    if not sid: raise RuntimeError('search_id_missing')
    for delay in (5,8,12,15):
        time.sleep(delay); _,status,_=provider.call('search_status','/tours/search/%d/status'%sid,{'operatorStatus':False})
        if ready(status): break
    else: raise RuntimeError('initial_search_timeout')
    _,results,response_sha=provider.call('search_results','/tours/search/%d'%sid,{'limit':10000})
    rows=result_rows(results); wanted=set(ids); found={}
    for h in rows:
        hid=numeric(h.get('id')) if isinstance(h,dict) else None
        if hid not in wanted: raise RuntimeError('unexpected_hotel_in_filtered_search')
        if hid in found: raise RuntimeError('duplicate_hotel_result')
        if identity(h,'country')!=ctx['country_id']: raise RuntimeError('result_country_mismatch')
        tours=h.get('tours') or []
        if any(identity(t,'operator')!=13 for t in tours): raise RuntimeError('foreign_or_missing_operator')
        usable=[t for t in tours if numeric(t.get('id') or t.get('tourId'))]
        ev={'hotel_id':hid,'hotel_name':h.get('name'),'batch':number,'search_id':sid,'country_id':ctx['country_id'],'results_response_sha256':response_sha,'safe_to_write_now':False}
        if not usable: ev['reason']='no_usable_tour'
        else:
            tour=usable[0]; tid=str(tour.get('id') or tour.get('tourId'))
            ev.update(tour_id=tid,operator_id=13,raw_room_name=tour.get('roomType'),raw_room_id=tour.get('roomId'))
            code,detail,digest=provider.call('tour_detail','/tours/'+tid,{'currency':'RUB'})
            ev.update(detail_response_sha256=digest,detail_http_status=code)
            if code==404: ev['reason']='detail_404'
            elif not isinstance(detail,dict): ev['reason']='detail_shape'
            elif identity(detail,'hotel')!=hid or identity(detail,'operator')!=13 or str(detail.get('id') or detail.get('tourId'))!=tid: ev['reason']='detail_identity_mismatch'
            else: ev.update(strict_link(detail))
        save(opdir/('hotel-%d.json'%hid),ev); found[hid]=ev
    return found,sid

def execute(opdir,root):
    reservation=json.loads((opdir/'reservation.json').read_bytes())
    if reservation['operation']!=OP or reservation['state']!='reserved_before_db_provider': raise RuntimeError('reservation')
    manifest=json.loads((opdir/'payload/source-hashes.json').read_bytes())
    for name,digest in manifest.items():
        if pathlib.PurePath(name).name!=name or hashlib.sha256((opdir/'payload'/name).read_bytes()).hexdigest()!=digest: raise RuntimeError('source_digest')
    cp=subprocess.run(['php',str(opdir/'payload/hotel_match_anex_rolling30_current_v3.php'),'--execute'],capture_output=True,check=False)
    if cp.returncode: raise RuntimeError('current_plan_failed')
    plan=json.loads(cp.stdout)
    if plan['operation']!=OP or plan['state']!='current_read_only_complete': raise RuntimeError('current_plan_state')
    plan_sha=save(opdir/'current-plan.json',plan)
    attempts=collections.Counter({int(i):1 for i in plan.get('prior_residual_ids',[])})
    found={}; batches=[]; provider=None
    try:
        provider=Provider(root,opdir)
        for ctx in plan['contexts']:
            pool=[h['hotel_id'] for h in ctx['hotels']]
            if len(pool)!=len(set(pool)): raise RuntimeError('duplicate_plan_id')
            for h in ctx['hotels']:
                if h['operator_id']!=13 or h['source']!='user_search' or h['country_id']!=ctx['country_id'] or h['departure_id']!=ctx['departure_id'] or not (ctx['date_from']<=h['departure_date']<=ctx['date_to']): raise RuntimeError('plan_observation_binding')
                if int(h.get('prior_attempts',0)) not in (0,1): raise RuntimeError('prior_attempt_shape')
            carry=[i for i in pool if attempts[i]==1 and i not in found]
            while len(batches)<MAX_BATCHES:
                ids,carried=batches_next(pool,attempts,found,carry)
                if not ids or provider.used+len(ids)+6>CALL_CAP: break
                number=len(batches)+1
                save(opdir/('batch-%d-reservation.json'%number),{'operation':OP,'batch':number,'current_plan_sha256':plan_sha,'hotel_ids':ids,'carried_ids':carried,'new_ids':[i for i in ids if i not in carried],'context':{k:v for k,v in ctx.items() if k!='hotels'},'state':'reserved_before_batch_http'})
                before=provider.used; attempts.update(ids)
                captured,sid=run_batch(provider,opdir,number,ctx,ids)
                found.update(captured); carry=[i for i in ids if i not in captured and attempts[i]<2]
                b={'batch':number,'country_name':ctx['country_name'],'search_id':sid,'sent':len(ids),'hotel_ids':ids,'carried_ids':carried,'new_count':len(ids)-len(carried),'found_ids':list(captured),'missing_ids':[i for i in ids if i not in captured],'calls':provider.used-before,'anchors':sum('native_anex_id' in e and 'reason' not in e for e in captured.values()),'detail_holds':sum('reason' in e for e in captured.values())}
                save(opdir/('batch-%d-result.json'%number),b); batches.append(b)
            if len(batches)>=MAX_BATCHES: break
        state='completed_read_only'; reason=None
    except Exception as exc:
        state='terminal_failed_no_replay'; reason=type(exc).__name__+':'+str(exc) if isinstance(exc,RuntimeError) else type(exc).__name__
    evidence=[json.loads(p.read_bytes()) for p in sorted(opdir.glob('hotel-*.json'))]
    outcome={'operation':OP,'state':state,'reason':reason,'batches':batches,'frontier_before':plan['frontier_unique'],'current_plan_sha256':plan_sha,'found_hotels':len(evidence),'anchors':[e for e in evidence if 'native_anex_id' in e and 'reason' not in e],'holds':[e for e in evidence if 'reason' in e],'not_found_ids':sorted(set(i for i,c in attempts.items() if c>0)-{e['hotel_id'] for e in evidence}),'attempts':dict(attempts),'provider_calls':provider.used if provider else 0,'call_counts':dict(provider.counts) if provider else {},'daily_accounted_after':provider.last_accounted if provider else None,'continue_calls':0,'database_writes':0,'mapping_writes':0,'room_correspondences':0,'no_replay':True}
    sha=save(opdir/'result.json',outcome)
    if provider: provider.finish(state,sha)
    save(opdir/'receipt.json',{'operation':OP,'state':state,'result_sha256':sha,'provider_calls':outcome['provider_calls'],'no_replay':True})
    print(json.dumps({'state':state,'batches':len(batches),'found':len(evidence),'anchors':len(outcome['anchors']),'calls':outcome['provider_calls']}))
    return 0 if state=='completed_read_only' else 2

def selftest():
    attempts=collections.Counter({21:1,22:1,23:1}); found={}; pool=list(range(1,81))
    first,carry=batches_next(pool,attempts,found,[21,22,23])
    assert first[:3]==[21,22,23] and first[3:]==list(range(1,21))+list(range(24,31)) and carry==[21,22,23]
    attempts.update(first); found.update({i:{} for i in first if i not in (21,22,23)})
    second,carry=batches_next(pool,attempts,found,[21,22,23])
    assert 21 not in second and 22 not in second and 23 not in second
    assert batches_next([21],collections.Counter({21:1}),{},[21])==([],[])
    link='https://agent.anextour.ru/search/tour?HOTELLIST='
    assert strict_link({'operatorLink':link+'7717'})['native_anex_id']==7717
    for value in ('7717,-1025133','7717,7717','7717,','','x','-2','1&HOTELLIST=1'):
        assert 'native_anex_id' not in strict_link({'operatorLink':link+value})
    assert 'native_anex_id' not in strict_link({'operatorLink':'https://foreign.test/?HOTELLIST=1'})
    assert identity({'hotel':{'id':1}},'hotel')==1 and identity({},'operator') is None
    import tempfile
    with tempfile.TemporaryDirectory() as tmp:
        p=pathlib.Path(tmp)/'e.json'; save(p,{'raw':'FAMILY SEA VIEW ROOM'})
        try: save(p,{})
        except FileExistsError: pass
        else: raise AssertionError('no_replay_output')
    print('ROLLING30_V3_SELFTEST_OK: prior_residual_once, refill, no_continue, signed-token-hold, durable-no-replay')

if __name__=='__main__':
    if '--self-test' in sys.argv: selftest()
    elif '--execute' in sys.argv: sys.exit(execute(pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['ANYTOUR_ROOT'])))
    else: raise SystemExit('disabled')
