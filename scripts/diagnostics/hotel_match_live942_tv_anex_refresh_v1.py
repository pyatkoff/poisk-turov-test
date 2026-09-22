#!/usr/bin/env python3
import collections,datetime as dt,fcntl,hashlib,json,os,pathlib,re,subprocess,sys,time,urllib.error,urllib.parse,urllib.request
from zoneinfo import ZoneInfo

OP='hotel-match-live942-tv-anex-refresh-1971-20260923-v2'
DAY='2026-09-23'
LIMIT=3000
CALL_CAP=2700
BASE='https://api.tourvisor.ru/search/api/v1'
BODY_LIMIT=16*1024*1024

def enc(x): return (json.dumps(x,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
def save(path,obj):
    p=pathlib.Path(path);p.parent.mkdir(parents=True,exist_ok=True);b=enc(obj)
    with open(p,'xb') as f:
        if f.write(b)!=len(b): raise RuntimeError('short_write')
        f.flush();os.fsync(f.fileno())
    return hashlib.sha256(b).hexdigest()
def readj(path):
    x=json.loads(pathlib.Path(path).read_text())
    if not isinstance(x,dict): raise RuntimeError('json_shape')
    return x
def read_locked(f):
    f.seek(0);x=json.loads(f.read())
    if not isinstance(x,dict): raise RuntimeError('ledger_shape')
    return x
def write_locked(f,x):
    b=enc(x);f.seek(0);f.truncate(0);f.write(b);f.flush();os.fsync(f.fileno())
def num(v):
    if isinstance(v,dict): v=v.get('id')
    s=str(v)
    return int(s) if re.fullmatch(r'[1-9][0-9]{0,21}',s) else None
def ident(x,key):
    if not isinstance(x,dict): return None
    return num(x.get(key)) or num(x.get(key+'Id'))
def unwrap(x): return x.get('data') if isinstance(x,dict) and isinstance(x.get('data'),(dict,list)) else x
def hotel_rows(x):
    x=unwrap(x)
    if isinstance(x,list): return x
    if isinstance(x,dict):
        for k in ('hotels','results','items'):
            if isinstance(x.get(k),list): return x[k]
    raise RuntimeError('results_shape')
def ready(x):
    x=unwrap(x)
    if not isinstance(x,dict): return False
    if isinstance(x.get('progress'),(int,float)) and x['progress']>=100:return True
    if str(x.get('status','')).lower() in ('complete','completed','done','ready'):return True
    return any(ready(v) for v in x.values() if isinstance(v,dict))
def safe_payload(x,token):
    raw=json.dumps(x,ensure_ascii=False)
    return not(token and token in raw) and not re.search(r'"(?:access_token|oauth_token|refresh_token|password|authorization|secret)"\s*:',raw,re.I)
def link_projection(detail):
    u=detail.get('operatorLink') if isinstance(detail,dict) else None
    if not isinstance(u,str) or not u:return {'link_state':'missing'}
    try:p=urllib.parse.urlsplit(u)
    except ValueError:return {'link_state':'invalid','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
    out={'operator_link_sha256':hashlib.sha256(u.encode()).hexdigest(),'operator_link_host':p.hostname or ''}
    if p.scheme!='https' or p.hostname!='agent.anextour.ru' or p.username or p.password or p.port or p.fragment:
        out['link_state']='invalid_origin';return out
    vals=[v for k,v in urllib.parse.parse_qsl(p.query,keep_blank_values=True) if k.upper()=='HOTELLIST']
    toks=[z.strip() for v in vals for z in re.split(r'[,;]',v) if z.strip()]
    pos=sorted({int(z) for z in toks if re.fullmatch(r'[1-9][0-9]{0,11}',z)})
    out['positive_native_candidates']=pos
    out['link_state']='captured_single_native' if len(pos)==1 and len(toks)==1 else ('captured_ambiguous_native' if toks else 'missing_native')
    return out

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl): return None

class Provider:
    def __init__(self,root,opdir):
        q=pathlib.Path(os.environ['HOME'])/'.anytoour-match/provider-quotas';q.mkdir(parents=True,exist_ok=True)
        self.day=q/f'tourvisor-test-{DAY}.json';self.op=q/f'tourvisor-{OP}.json';self.dir=opdir
        self.used=0;self.last=0;self.counts=collections.Counter()
        if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY:raise RuntimeError('provider_day_mismatch')
        if not self.day.exists():
            try:save(self.day,{'provider':'tourvisor-test','provider_day':DAY,'owner_daily_limit':LIMIT,'accounted_requests':0,'known_prior_attempt_floor':0,'match_new_attempts':0,'baseline_status':'new_day_first_match_operation','operations':{}})
            except FileExistsError:pass
        with open(self.day,'r+b') as f:
            fcntl.flock(f,fcntl.LOCK_EX);d=read_locked(f)
            self.last=max(int(d.get('accounted_requests',0)),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
            if self.last>=LIMIT:raise RuntimeError('daily_budget_guard')
        save(self.op,{'operation':OP,'status':'reserved_before_provider_access','used':0,'operation_cap':CALL_CAP,'provider_day':DAY})
        save(opdir/'quota-reservation.json',{'operation':OP,'accounted_before_local_ledger':self.last,'cap':CALL_CAP,'limit':LIMIT,'provider_day':DAY})
        cp=subprocess.run(['php','-r','require $argv[1];fwrite(STDOUT,(string)(defined("TOURVISOR_JWT")?TOURVISOR_JWT:getenv("TOURVISOR_JWT")));','--',str(root/'config.php')],capture_output=True,check=True)
        self.token=cp.stdout.decode().strip()
        if not self.token:raise RuntimeError('token_invalid')
        self.open=urllib.request.build_opener(NoRedirect())
    def call(self,action,path,params):
        if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY:raise RuntimeError('provider_day_changed')
        with open(self.day,'r+b') as df,open(self.op,'r+b') as of:
            fcntl.flock(df,fcntl.LOCK_EX);fcntl.flock(of,fcntl.LOCK_EX)
            d,o=read_locked(df),read_locked(of);used=int(o['used'])
            charged=max(int(d.get('accounted_requests',0)),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
            if charged>=LIMIT or used>=CALL_CAP:raise RuntimeError('quota_exhausted')
            d['match_new_attempts']=int(d.get('match_new_attempts',0))+1;d['accounted_requests']=charged+1;d.setdefault('operations',{})[OP]=used+1
            o.update(status='provider_accessed',used=used+1,last_action=action);write_locked(df,d);write_locked(of,o)
            self.used=used+1;self.last=charged+1
        self.counts[action]+=1
        save(self.dir/f'tv-request-{self.used:04d}.json',{'call':self.used,'action':action,'path':path,'params':params,'accounted_local_ledger':self.last})
        pairs=[(k,str(z).lower() if isinstance(z,bool) else str(z)) for k,v in params.items() for z in (v if isinstance(v,list) else [v])]
        url=BASE+path+('?' + urllib.parse.urlencode(pairs) if pairs else '')
        req=urllib.request.Request(url,headers={'Authorization':'Bearer '+self.token,'Accept':'application/json'})
        try:r=self.open.open(req,timeout=55)
        except urllib.error.HTTPError as e:r=e
        with r: code=r.code;raw=r.read(BODY_LIMIT+1)
        try:data=json.loads(raw)
        except Exception:data={'unparsed_body_sha256':hashlib.sha256(raw).hexdigest()}
        if not safe_payload(data,self.token):
            save(self.dir/f'tv-response-{self.used:04d}.json',{'http_status':code,'withheld_sensitive_body_sha256':hashlib.sha256(raw).hexdigest()})
            raise RuntimeError('sensitive_response')
        save(self.dir/f'tv-response-{self.used:04d}.json',{'http_status':code,'raw_sha256':hashlib.sha256(raw).hexdigest(),'data':data})
        if code==429:raise RuntimeError('quota_http_429')
        if code in (401,403):raise RuntimeError('auth_http_'+str(code))
        if code!=200 and not(action=='tour_detail' and code==404):raise RuntimeError('http_'+str(code))
        return code,data
    def finish(self,state,digest):
        with open(self.op,'r+b') as f:
            fcntl.flock(f,fcntl.LOCK_EX);o=read_locked(f);o.update(status=state,result_sha256=digest);write_locked(f,o)

def chunks(rows):
    grouped={}
    for x in rows:
        k=(int(x['departure_id']),int(x['country_id']),str(x['departure_date']),int(x['nights']),int(x['adults']),str(x.get('child_ages_signature','')))
        grouped.setdefault(k,[]).append(int(x['tv_hotel_id']))
    out=[]
    for k,ids in grouped.items():
        ids=sorted(set(ids))
        for i in range(0,len(ids),30):
            out.append({'departure_id':k[0],'country_id':k[1],'departure_date':k[2],'nights':k[3],'adults':k[4],'child_ages_signature':k[5],'hotel_ids':ids[i:i+30]})
    out.sort(key=lambda g:(-len(g['hotel_ids']),g['country_id'],g['departure_date'],g['nights']))
    return out

def run_batch(p,opdir,index,g):
    ages=[int(z) for z in g['child_ages_signature'].split(',') if re.fullmatch(r'[0-9]{1,2}',z)]
    params={'departureId':g['departure_id'],'countryId':g['country_id'],'dateFrom':g['departure_date'],'dateTo':g['departure_date'],'nightsFrom':g['nights'],'nightsTo':g['nights'],'adults':g['adults'],'childs':ages,'currency':'RUB','onlyCharter':False,'operatorIds':[13],'hotelIds':g['hotel_ids']}
    _,st=p.call('search_start','/tours/search',params);st=unwrap(st);sid=num((st or {}).get('searchId') if isinstance(st,dict) else None) or num((st or {}).get('id') if isinstance(st,dict) else None)
    if not sid:raise RuntimeError('search_id_missing')
    ok=False
    for delay in (2,4,7):
        time.sleep(delay);_,s=p.call('search_status',f'/tours/search/{sid}/status',{'operatorStatus':False})
        if ready(s):ok=True;break
    if not ok:raise RuntimeError('search_timeout')
    _,res=p.call('search_results',f'/tours/search/{sid}',{'limit':10000})
    wanted=set(g['hotel_ids']);edges=[]
    for h in hotel_rows(res):
        if not isinstance(h,dict):continue
        hid=num(h.get('id'))
        if hid not in wanted:continue
        ts=[t for t in (h.get('tours') or []) if isinstance(t,dict) and ident(t,'operator')==13 and num(t.get('id') or t.get('tourId'))]
        edge={'tv_hotel_id':hid,'search_id':sid,'batch':index,'anex_tour_count':len(ts),'state':'returned_no_anex_tour' if not ts else 'anex_tour_returned','safe_to_write_now':False}
        if ts:
            tid=str(ts[0].get('id') or ts[0].get('tourId'));code,d=p.call('tour_detail','/tours/'+tid,{'currency':'RUB'});d=unwrap(d)
            edge['tour_detail_http']=code;edge['tour_id_sha256']=hashlib.sha256(tid.encode()).hexdigest()
            if code==404:edge['state']='detail_404'
            elif not isinstance(d,dict) or ident(d,'hotel')!=hid or ident(d,'operator')!=13:edge['state']='detail_identity_mismatch'
            else:edge['state']='detail_identity_verified';edge.update(link_projection(d))
        save(opdir/f'tv-hotel-{hid}.json',edge);edges.append(edge)
    return sid,edges

def execute(root,opdir,plan_path):
    plan=readj(plan_path);reservation=readj(opdir/'reservation.json')
    if reservation.get('operation')!=OP or plan.get('frontier_count')!=942 or len(plan.get('rows',[]))!=942:raise RuntimeError('input_guard')
    groups=chunks(plan['rows']);save(opdir/'tv-plan.json',{'operation':OP,'frontier_count':942,'groups':groups,'group_count':len(groups)})
    p=None;batches=[];state='failed_before_provider_access';reason=None
    try:
        p=Provider(root,opdir)
        for i,g in enumerate(groups,1):
            save(opdir/f'tv-batch-{i:04d}-reservation.json',{'operation':OP,'batch':i,'state':'reserved_before_batch_http','hotel_count':len(g['hotel_ids'])})
            before=p.used;sid,edges=run_batch(p,opdir,i,g)
            b={'batch':i,'search_id':sid,'sent':len(g['hotel_ids']),'returned_targets':len(edges),'calls':p.used-before}
            save(opdir/f'tv-batch-{i:04d}-result.json',b);batches.append(b)
        state='completed_read_only'
    except Exception as e:
        reason=(type(e).__name__+':'+str(e))[:180]
        if 'provider_day_changed' in reason:state='terminal_day_changed_no_replay'
        elif 'quota' in reason or 'daily_budget' in reason:state='terminal_quota_stop_no_replay'
        else:state='terminal_failed_no_replay' if p and p.used else 'failed_before_provider_access'
    edges=[readj(x) for x in sorted(opdir.glob('tv-hotel-*.json'))]
    links=collections.Counter(x.get('link_state','none') for x in edges);states=collections.Counter(x.get('state','unknown') for x in edges)
    unique_native={}
    for x in edges:
        ids=x.get('positive_native_candidates') or []
        if x.get('link_state')=='captured_single_native' and len(ids)==1:unique_native.setdefault(str(ids[0]),[]).append(x['tv_hotel_id'])
    unique_pairs=sum(1 for a,t in unique_native.items() if len(set(t))==1)
    out={'operation':OP,'state':state,'reason':reason,'frontier_count':942,'planned_groups':len(groups),'completed_batches':len(batches),'searched_hotels':sum(b['sent'] for b in batches),'returned_targets':len(edges),'provider_calls':p.used if p else 0,'daily_accounted_after_local_ledger':p.last if p else None,'call_counts':dict(p.counts) if p else {},'edge_state_counts':dict(states),'link_state_counts':dict(links),'single_native_source_unique_count':unique_pairs,'edges':edges,'batches':batches,'operator_ids':[13],'continue_calls':0,'dates_calls':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False}
    digest=save(opdir/'result.json',out);save(opdir/'receipt.json',{'operation':OP,'state':state,'result_sha256':digest,'provider_calls':out['provider_calls'],'searched_hotels':out['searched_hotels'],'no_replay':bool(p and p.used),'database_writes':0,'mapping_writes':0})
    if p:p.finish(state,digest)
    print(json.dumps({k:out[k] for k in ['state','reason','planned_groups','completed_batches','searched_hotels','returned_targets','provider_calls','daily_accounted_after_local_ledger','edge_state_counts','link_state_counts','single_native_source_unique_count']},ensure_ascii=False))
    return 0 if state in ('completed_read_only','terminal_day_changed_no_replay','terminal_quota_stop_no_replay') else 2

if __name__=='__main__':
    if '--self-test' in sys.argv:
        x=chunks([{'departure_id':1,'country_id':4,'departure_date':'2026-10-01','nights':7,'adults':2,'child_ages_signature':'','tv_hotel_id':i} for i in range(1,66)])
        assert [len(z['hotel_ids']) for z in x]==[30,30,5]
        print('MATCH_LIVE942_TV_ANEX_REFRESH_V2_SELFTEST_OK')
    elif '--execute' in sys.argv:
        sys.exit(execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_PLAN_PATH'])))
    else:raise SystemExit('disabled')
