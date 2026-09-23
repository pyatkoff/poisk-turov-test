#!/usr/bin/env python3
import collections,datetime as dt,fcntl,hashlib,json,os,pathlib,re,subprocess,sys,time,urllib.error,urllib.parse,urllib.request
from zoneinfo import ZoneInfo

OP=os.environ.get('MATCH_CHILD_OPERATION','hotel-match-live30-common4-continuation-acquire-1971-20260923-c0-n30-v1')
DAILY_LIMIT=3000
CALL_CAP=int(os.environ.get('MATCH_CALL_CAP','900'))
BASE='https://api.tourvisor.ru/search/api/v1'
BODY_LIMIT=16*1024*1024
OPS=(13,18,25,43)
NAMESPACES={13:'anex',18:'bgoperator',25:'operator_315',43:'operator_342'}
SECRET=re.compile(r'(?:token|jwt|auth|pass|password|secret|session|sid|cookie|signature|api[_-]?key)',re.I)
HOTEL_KEYS={'hotel','hotels','hotelid','hotel_id','hotelcode','hotel_code','hotellist','hotelkey','hotel_key'}

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
    return num(x.get(key)) or num(x.get(key+'Id')) or num(x.get(key+'_id'))
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
def anex_host(host):
    host=(host or '').lower()
    return host=='anextour.ru' or host.endswith('.anextour.ru')

def native_projection(operator_id,url):
    ns=NAMESPACES.get(operator_id)
    if ns is None:return {'namespace':None,'link_state':'unsupported_operator','positive_native_candidates':[]}
    if not isinstance(url,str) or not url.strip():return {'namespace':ns,'link_state':'missing','positive_native_candidates':[]}
    raw=url.strip()
    try:p=urllib.parse.urlsplit(raw)
    except ValueError:return {'namespace':ns,'link_state':'invalid','operator_link_sha256':hashlib.sha256(raw.encode()).hexdigest(),'positive_native_candidates':[]}
    out={'namespace':ns,'operator_link_sha256':hashlib.sha256(raw.encode()).hexdigest(),'operator_link_host':p.hostname or ''}
    if p.scheme.lower()!='https' or not p.hostname or p.username or p.password or p.fragment:
        out.update(link_state='invalid_origin',positive_native_candidates=[]);return out
    host=(p.hostname or '').lower()
    if operator_id==13 and not anex_host(host):
        out.update(link_state='unexpected_anex_host',positive_native_candidates=[]);return out
    if operator_id==18 and host not in ('bgoperator.ru','www.bgoperator.ru'):
        out.update(link_state='unexpected_bg_host',positive_native_candidates=[]);return out
    pairs=urllib.parse.parse_qsl(p.query,keep_blank_values=True)
    if any(SECRET.search(k or '') for k,_ in pairs):
        out.update(link_state='secret_bearing_link',positive_native_candidates=[]);return out
    vals=[]
    if operator_id==18:
        vals=[v.strip() for k,v in pairs if k.upper()=='F4' and re.fullmatch(r'[1-9][0-9]{0,19}',v.strip())]
    else:
        vals=[v.strip() for k,v in pairs if urllib.parse.unquote(k).lower() in HOTEL_KEYS and re.fullmatch(r'[1-9][0-9]{0,19}',v.strip())]
    ids=sorted({int(v) for v in vals})
    out['positive_native_candidates']=ids
    if len(ids)==1 and len(vals)==1: out['link_state']='captured_single_native'
    elif vals: out['link_state']='captured_ambiguous_native'
    else: out['link_state']='missing_native'
    out['query_keys']=sorted({urllib.parse.unquote(k).lower() for k,_ in pairs})[:40]
    return out

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl): return None

class Provider:
    def __init__(self,root,opdir):
        day=dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()
        q=pathlib.Path(os.environ['HOME'])/'.anytoour-match/provider-quotas';q.mkdir(parents=True,exist_ok=True)
        self.day=q/f'tourvisor-test-{day}.json';self.op=q/f'tourvisor-{OP}.json';self.dir=opdir;self.day_value=day
        self.used=0;self.last=0;self.counts=collections.Counter()
        if not self.day.exists():
            try:save(self.day,{'provider':'tourvisor-test','provider_day':day,'owner_daily_limit':DAILY_LIMIT,'accounted_requests':0,'known_prior_attempt_floor':0,'match_new_attempts':0,'baseline_status':'new_day_first_match_operation','operations':{}})
            except FileExistsError:pass
        with open(self.day,'r+b') as f:
            fcntl.flock(f,fcntl.LOCK_EX);d=read_locked(f)
            if d.get('provider_day')!=day:raise RuntimeError('provider_day_ledger_mismatch')
            self.last=max(int(d.get('accounted_requests',0)),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
            if self.last>=DAILY_LIMIT:raise RuntimeError('daily_budget_guard')
        save(self.op,{'operation':OP,'status':'reserved_before_provider_access','used':0,'operation_cap':CALL_CAP,'provider_day':day})
        save(opdir/'quota-reservation.json',{'operation':OP,'accounted_before_local_ledger':self.last,'cap':CALL_CAP,'limit':DAILY_LIMIT,'provider_day':day})
        cp=subprocess.run(['php','-r','require $argv[1];fwrite(STDOUT,(string)(defined("TOURVISOR_JWT")?TOURVISOR_JWT:getenv("TOURVISOR_JWT")));','--',str(root/'config.php')],capture_output=True,check=True)
        self.token=cp.stdout.decode().strip()
        if not self.token:raise RuntimeError('token_invalid')
        self.open=urllib.request.build_opener(NoRedirect())
    def call(self,action,path,params):
        day=dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()
        if day!=self.day_value:raise RuntimeError('provider_day_changed')
        with open(self.day,'r+b') as df,open(self.op,'r+b') as of:
            fcntl.flock(df,fcntl.LOCK_EX);fcntl.flock(of,fcntl.LOCK_EX)
            d,o=read_locked(df),read_locked(of);used=int(o['used'])
            charged=max(int(d.get('accounted_requests',0)),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
            if charged>=DAILY_LIMIT or used>=CALL_CAP:raise RuntimeError('quota_exhausted')
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
        if not x.get('missing_operator_ids'):continue
        k=(int(x['departure_id']),int(x['country_id']),str(x['departure_date']),int(x['nights']),int(x['adults']),str(x.get('child_ages_signature','')))
        grouped.setdefault(k,[]).append(int(x['tv_hotel_id']))
    out=[]
    for k,ids in grouped.items():
        ids=sorted(set(ids))
        for i in range(0,len(ids),30):
            out.append({'departure_id':k[0],'country_id':k[1],'departure_date':k[2],'nights':k[3],'adults':k[4],'child_ages_signature':k[5],'hotel_ids':ids[i:i+30]})
    out.sort(key=lambda g:(-len(g['hotel_ids']),g['country_id'],g['departure_date'],g['nights'],g['hotel_ids'][0]))
    return out

def run_batch(p,opdir,index,g,missing_by_hotel):
    ages=[int(z) for z in g['child_ages_signature'].split(',') if re.fullmatch(r'[0-9]{1,2}',z)]
    params={'departureId':g['departure_id'],'countryId':g['country_id'],'dateFrom':g['departure_date'],'dateTo':g['departure_date'],
            'nightsFrom':g['nights'],'nightsTo':g['nights'],'adults':g['adults'],'childs':ages,'currency':'RUB','onlyCharter':False,
            'operatorIds':list(OPS),'hotelIds':g['hotel_ids']}
    _,st=p.call('search_start','/tours/search',params);st=unwrap(st)
    sid=num((st or {}).get('searchId') if isinstance(st,dict) else None) or num((st or {}).get('id') if isinstance(st,dict) else None)
    if not sid:raise RuntimeError('search_id_missing')
    search_complete=False
    for delay in (2,4,7):
        time.sleep(delay);_,status=p.call('search_status',f'/tours/search/{sid}/status',{'operatorStatus':False})
        if ready(status):search_complete=True;break
    _,res=p.call('search_results',f'/tours/search/{sid}',{'limit':10000})
    wanted=set(g['hotel_ids']);edges=[];returned=set();returned_pairs=0
    for h in hotel_rows(res):
        if not isinstance(h,dict):continue
        hid=num(h.get('id'))
        if hid not in wanted:continue
        returned.add(hid);tours=h.get('tours') or []
        for op in sorted(set(missing_by_hotel.get(hid,[]))):
            ts=[t for t in tours if isinstance(t,dict) and ident(t,'operator')==op and num(t.get('id') or t.get('tourId'))]
            if not ts:continue
            returned_pairs+=1;tid=str(ts[0].get('id') or ts[0].get('tourId'))
            edge={'tv_hotel_id':hid,'operator_id':op,'namespace':NAMESPACES[op],
                  'search_id_sha256':hashlib.sha256(str(sid).encode()).hexdigest(),'batch':index,
                  'operator_tour_count':len(ts),'state':'operator_tour_returned','safe_to_write_now':False}
            code,d=p.call('tour_detail','/tours/'+tid,{'currency':'RUB'});d=unwrap(d)
            edge['tour_detail_http']=code;edge['tour_id_sha256']=hashlib.sha256(tid.encode()).hexdigest()
            if code==404:edge['state']='detail_404'
            elif not isinstance(d,dict) or ident(d,'hotel')!=hid or ident(d,'operator')!=op:edge['state']='detail_identity_mismatch'
            else:
                edge['state']='detail_identity_verified';edge.update(native_projection(op,d.get('operatorLink')))
            save(opdir/f'tv-edge-{hid}-{op}.json',edge);edges.append(edge)
    return sid,returned,edges,returned_pairs,search_complete

def plan_id_digest(ids):
    values=sorted(int(x) for x in ids)
    return hashlib.sha256(json.dumps(values,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()

def validate_continuation_plan(plan,plan_sha=None):
    if not isinstance(plan,dict) or plan.get('state')!='live30_common4_continuation_ready':
        raise RuntimeError('continuation_plan_state')
    frontier=plan.get('acquisition_target_count')
    rows=plan.get('rows')
    if not isinstance(frontier,int) or frontier<1 or frontier>50000 or not isinstance(rows,list) or len(rows)!=frontier:
        raise RuntimeError('continuation_plan_count')
    if plan.get('provider_http_calls')!=0 or plan.get('database_writes')!=0 or plan.get('mapping_writes')!=0 or plan.get('safe_to_write_now') is not False:
        raise RuntimeError('continuation_plan_authority')
    ids=[];seen=set()
    for row in rows:
        if not isinstance(row,dict): raise RuntimeError('continuation_plan_row')
        tv=num(row.get('tv_hotel_id'))
        if tv is None or tv in seen: raise RuntimeError('continuation_plan_identity')
        seen.add(tv);ids.append(tv)
        missing=row.get('missing_operator_ids')
        if not isinstance(missing,list) or not missing: raise RuntimeError('continuation_plan_missing_lanes')
        ops=[]
        for value in missing:
            op=num(value)
            if op not in OPS or op in ops: raise RuntimeError('continuation_plan_operator')
            ops.append(op)
        for key in ('departure_id','country_id','nights','adults'):
            if num(row.get(key)) is None: raise RuntimeError('continuation_plan_context')
        date=str(row.get('departure_date',''))
        if not re.fullmatch(r'\d{4}-\d{2}-\d{2}',date): raise RuntimeError('continuation_plan_date')
    digest=plan_id_digest(ids)
    if plan.get('acquisition_target_id_sha256')!=digest:
        raise RuntimeError('continuation_plan_id_hash')
    if plan_sha is not None and (not isinstance(plan_sha,str) or not re.fullmatch(r'[0-9a-f]{64}',plan_sha)):
        raise RuntimeError('continuation_plan_result_hash')
    return frontier,ids,digest

def scope_continuation_plan(plan,offset,limit):
    frontier,ids,digest=validate_continuation_plan(plan)
    if not isinstance(offset,int) or not isinstance(limit,int) or offset<0 or limit<1 or limit>150 or offset>=frontier or offset+limit>frontier:
        raise RuntimeError('scope_guard')
    scope=plan['rows'][offset:offset+limit]
    scope_ids=[int(x['tv_hotel_id']) for x in scope]
    return scope,{'frontier_count':frontier,'frontier_id_sha256':digest,
                  'scope_offset':offset,'scope_count':len(scope),
                  'scope_target_id_sha256':plan_id_digest(scope_ids)}

def execute(root,opdir,plan_path):
    plan_raw=pathlib.Path(plan_path).read_bytes()
    plan_sha=hashlib.sha256(plan_raw).hexdigest()
    plan=json.loads(plan_raw)
    reservation=readj(opdir/'reservation.json')
    frontier,_,frontier_digest=validate_continuation_plan(plan,plan_sha)
    if reservation.get('operation')!=OP:
        raise RuntimeError('reservation_operation')
    expected_plan_sha=reservation.get('continuation_plan_sha256')
    if expected_plan_sha is not None and expected_plan_sha!=plan_sha:
        raise RuntimeError('reservation_plan_hash')
    if reservation.get('database_writes') not in (0,None) or reservation.get('mapping_writes') not in (0,None):
        raise RuntimeError('reservation_authority')
    offset=int(os.environ.get('MATCH_OFFSET','0'));limit=int(os.environ.get('MATCH_LIMIT','30'))
    scope,scope_meta=scope_continuation_plan(plan,offset,limit)
    missing_by_hotel={int(x['tv_hotel_id']):[int(z) for z in x.get('missing_operator_ids',[])] for x in scope}
    groups=chunks(scope)
    save(opdir/'tv-plan.json',{'operation':OP,'continuation_plan_sha256':plan_sha,
                              'frontier_count':frontier,'frontier_id_sha256':frontier_digest,
                              'scope_offset':offset,'scope_count':len(scope),
                              'scope_target_id_sha256':scope_meta['scope_target_id_sha256'],
                              'groups':groups,'operator_ids':list(OPS),
                              'missing_lane_counts':dict(collections.Counter(z for x in scope for z in x.get('missing_operator_ids',[])))})
    p=None;batches=[];state='failed_before_provider_access';reason=None
    try:
        p=Provider(root,opdir)
        for i,g in enumerate(groups,1):
            save(opdir/f'tv-batch-{i:04d}-reservation.json',{'operation':OP,'batch':i,'state':'reserved_before_batch_http','hotel_count':len(g['hotel_ids'])})
            before=p.used;sid,returned,edges,returned_pairs,search_complete=run_batch(p,opdir,i,g,missing_by_hotel)
            b={'batch':i,'search_id_sha256':hashlib.sha256(str(sid).encode()).hexdigest(),'sent':len(g['hotel_ids']),
               'returned_targets':len(returned),'returned_missing_operator_pairs':returned_pairs,'detail_edges':len(edges),
               'search_complete':search_complete,'calls':p.used-before}
            save(opdir/f'tv-batch-{i:04d}-result.json',b);batches.append(b)
        state='completed_read_only'
    except Exception as e:
        reason=(type(e).__name__+':'+str(e))[:180]
        if 'provider_day_changed' in reason:state='terminal_day_changed_no_replay'
        elif 'quota' in reason or 'daily_budget' in reason:state='terminal_quota_stop_no_replay'
        else:state='terminal_failed_no_replay' if p and p.used else 'failed_before_provider_access'
    edges=[readj(x) for x in sorted(opdir.glob('tv-edge-*.json'))]
    state_counts=collections.Counter(x.get('state','unknown') for x in edges)
    link_counts=collections.Counter((x.get('namespace','none')+'|'+x.get('link_state','none')) for x in edges)
    native={};operator_single=collections.Counter()
    for x in edges:
        ids=x.get('positive_native_candidates') or []
        if x.get('link_state')=='captured_single_native' and len(ids)==1:
            native.setdefault((x.get('namespace'),str(ids[0])),set()).add(int(x['tv_hotel_id']))
            operator_single[str(x.get('operator_id'))]+=1
    chunk_unique=sum(1 for targets in native.values() if len(targets)==1)
    out={'operation':OP,'state':state,'reason':reason,
         'continuation_plan_sha256':plan_sha,'frontier_count':frontier,
         'frontier_id_sha256':frontier_digest,'scope_offset':offset,'scope_count':len(scope),
         'scope_target_id_sha256':scope_meta['scope_target_id_sha256'],
         'planned_groups':len(groups),'completed_batches':len(batches),'searched_hotels':sum(b['sent'] for b in batches),
         'incomplete_status_batches':sum(1 for b in batches if not b.get('search_complete',False)),
         'returned_targets':sum(b['returned_targets'] for b in batches),
         'returned_missing_operator_pairs':sum(b['returned_missing_operator_pairs'] for b in batches),
         'returned_operator_pairs':len(edges),'provider_calls':p.used if p else 0,
         'daily_accounted_after_local_ledger':p.last if p else None,'provider_day':p.day_value if p else None,
         'call_counts':dict(p.counts) if p else {},'edge_state_counts':dict(state_counts),'link_state_counts':dict(link_counts),
         'single_native_chunk_unique_count':chunk_unique,'single_native_by_operator':dict(operator_single),
         'edges':edges,'batches':batches,'operator_ids':list(OPS),
         'continue_calls':0,'dates_calls':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False}
    digest=save(opdir/'result.json',out)
    save(opdir/'receipt.json',{'operation':OP,'state':state,'result_sha256':digest,'continuation_plan_sha256':plan_sha,
         'provider_calls':out['provider_calls'],'searched_hotels':out['searched_hotels'],
         'no_replay':bool(p and p.used),'database_writes':0,'mapping_writes':0})
    if p:p.finish(state,digest)
    print(json.dumps({k:out[k] for k in ['state','reason','frontier_count','scope_offset','scope_count','scope_target_id_sha256',
        'planned_groups','completed_batches','searched_hotels','incomplete_status_batches','returned_targets',
        'returned_missing_operator_pairs','returned_operator_pairs','provider_calls','daily_accounted_after_local_ledger',
        'edge_state_counts','link_state_counts','single_native_chunk_unique_count','single_native_by_operator']},ensure_ascii=False))
    return 0 if state in ('completed_read_only','terminal_day_changed_no_replay','terminal_quota_stop_no_replay') else 2

if __name__=='__main__':
    if '--self-test' in sys.argv:
        rows=[{'tv_hotel_id':i,'missing_operator_ids':[13,18,25,43],'departure_id':1,'country_id':4,
               'departure_date':'2026-10-01','nights':7,'adults':2,'children_count':0,'child_ages_signature':''}
              for i in range(1,66)]
        plan={'state':'live30_common4_continuation_ready','acquisition_target_count':65,'rows':rows,
              'acquisition_target_id_sha256':plan_id_digest(range(1,66)),'provider_http_calls':0,
              'database_writes':0,'mapping_writes':0,'safe_to_write_now':False}
        frontier,ids,digest=validate_continuation_plan(plan,'a'*64)
        assert frontier==65 and len(ids)==65 and digest==plan['acquisition_target_id_sha256']
        scope,meta=scope_continuation_plan(plan,30,30)
        assert [x['tv_hotel_id'] for x in scope]==list(range(31,61))
        assert meta['scope_count']==30 and len(meta['scope_target_id_sha256'])==64
        bad=dict(plan);bad['acquisition_target_id_sha256']='0'*64
        try:validate_continuation_plan(bad)
        except RuntimeError as e:assert str(e)=='continuation_plan_id_hash'
        else:raise AssertionError('bad plan hash accepted')
        assert native_projection(13,'https://online.anextour.ru/search?hotelCode=5844')['positive_native_candidates']==[5844]
        x=chunks(rows);assert [len(z['hotel_ids']) for z in x]==[30,30,5]
        print('MATCH_LIVE30_COMMON4_CONTINUATION_ACQUIRE_V10_SELFTEST_OK')
    elif '--execute' in sys.argv:
        sys.exit(execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_PLAN_PATH'])))
    else:raise SystemExit('disabled')
