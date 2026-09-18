#!/usr/bin/env python3
import fcntl, hashlib, json, os, pathlib, re, subprocess, sys, time
from datetime import datetime
from zoneinfo import ZoneInfo
from urllib.parse import urlencode, urlparse, parse_qs

OP='hotel-match-anex-user-seen-uae12-direct-1971-20260919-v1'
BASE='https://api.tourvisor.ru/search/api/v1'
TARGET_IDS=[44669,74083,2590,32230,2522,73509,73055,2565,2582,133061,88323,54603]
OPERATOR_ID=13
DAY='2026-09-19'
DAILY_LIMIT=3000
KNOWN_FLOOR=102
OP_CAP=180
BODY_LIMIT=16*1024*1024


def durable_write(path, obj):
    p=pathlib.Path(path); p.parent.mkdir(parents=True, exist_ok=True)
    raw=(json.dumps(obj,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
    tmp=p.with_name(p.name+'.tmp')
    with open(tmp,'wb') as f:
        f.write(raw); f.flush(); os.fsync(f.fileno())
    os.replace(tmp,p)
    dfd=os.open(str(p.parent),os.O_DIRECTORY)
    try: os.fsync(dfd)
    finally: os.close(dfd)
    return hashlib.sha256(raw).hexdigest()


def append_jsonl(path,obj):
    p=pathlib.Path(path); p.parent.mkdir(parents=True,exist_ok=True)
    raw=(json.dumps(obj,ensure_ascii=False,sort_keys=True)+'\n').encode()
    with open(p,'ab') as f:
        fcntl.flock(f,fcntl.LOCK_EX); f.write(raw); f.flush(); os.fsync(f.fileno()); fcntl.flock(f,fcntl.LOCK_UN)


def load_json_file(f):
    f.seek(0); raw=f.read()
    if not raw.strip(): return {}
    x=json.loads(raw); return x if isinstance(x,dict) else {}


def write_locked(f,obj):
    raw=(json.dumps(obj,ensure_ascii=False,sort_keys=True)+'\n').encode()
    f.seek(0); f.truncate(0); f.write(raw); f.flush(); os.fsync(f.fileno())


def provider_day(): return datetime.now(ZoneInfo('Europe/Moscow')).strftime('%Y-%m-%d')


def quota_paths(home):
    q=pathlib.Path(home)/'.anytour-match/provider-quotas'; q.mkdir(parents=True,exist_ok=True,mode=0o700)
    return q/f'tourvisor-test-{DAY}.json', q/f'tourvisor-{OP}.json'


def reserve(home,reservation_sha):
    if provider_day()!=DAY: raise RuntimeError('provider_day_mismatch')
    daily_p,op_p=quota_paths(home)
    with open(daily_p,'a+b') as df:
        fcntl.flock(df,fcntl.LOCK_EX); daily=load_json_file(df)
        configured=int(daily.get('owner_daily_limit') or DAILY_LIMIT)
        if configured<=0: raise RuntimeError('daily_limit_invalid')
        limit=min(DAILY_LIMIT,configured)
        prior=max(KNOWN_FLOOR,int(daily.get('known_prior_attempt_floor') or 0))
        match=max(0,int(daily.get('match_new_attempts') or 0))
        accounted=max(int(daily.get('accounted_requests') or 0),prior+match)
        if accounted>=limit: raise RuntimeError('daily_cap_before_reservation')
        daily.update(owner_daily_limit=limit,known_prior_attempt_floor=prior,match_new_attempts=match,accounted_requests=accounted,provider_timezone='Europe/Moscow',updated_at=datetime.now(ZoneInfo('UTC')).isoformat())
        write_locked(df,daily); fcntl.flock(df,fcntl.LOCK_UN)
    flags=os.O_RDWR|os.O_CREAT|os.O_EXCL
    try: fd=os.open(op_p,flags,0o600)
    except FileExistsError: raise RuntimeError('operation_already_reserved')
    with os.fdopen(fd,'w+') as of:
        obj={'operation':OP,'status':'reserved_before_provider_access','operation_cap':OP_CAP,'used':0,'reservation_sha256':reservation_sha,'provider_day':DAY,'created_at':datetime.now(ZoneInfo('UTC')).isoformat()}
        write_locked(of,obj)
    return {'daily_path':str(daily_p),'operation_path':str(op_p),'accounted_before':accounted,'daily_limit':limit}


def charge(home,lane,action):
    daily_p,op_p=quota_paths(home)
    with open(daily_p,'r+b') as df:
        fcntl.flock(df,fcntl.LOCK_EX); daily=load_json_file(df)
        with open(op_p,'r+b') as of:
            fcntl.flock(of,fcntl.LOCK_EX); op=load_json_file(of)
            if op.get('operation')!=OP or op.get('status') not in ('reserved_before_provider_access','provider_accessed'): raise RuntimeError('operation_reservation_invalid')
            used=max(0,int(op.get('used') or 0)); limit=min(DAILY_LIMIT,int(daily.get('owner_daily_limit') or DAILY_LIMIT))
            prior=max(KNOWN_FLOOR,int(daily.get('known_prior_attempt_floor') or 0)); match=max(0,int(daily.get('match_new_attempts') or 0)); accounted=max(int(daily.get('accounted_requests') or 0),prior+match)
            if used>=OP_CAP: raise RuntimeError('operation_cap')
            if accounted>=limit: raise RuntimeError('daily_cap')
            used+=1; match+=1; accounted=max(accounted+1,prior+match)
            lanes=op.get('lanes') if isinstance(op.get('lanes'),dict) else {}; lanes[lane]=int(lanes.get(lane) or 0)+1
            op.update(status='provider_accessed',used=used,lanes=lanes,last_action=action,updated_at=datetime.now(ZoneInfo('UTC')).isoformat())
            ops=daily.get('operations') if isinstance(daily.get('operations'),dict) else {}; ops[OP]=int(ops.get(OP) or 0)+1
            daily.update(known_prior_attempt_floor=prior,match_new_attempts=match,accounted_requests=accounted,operations=ops,updated_at=datetime.now(ZoneInfo('UTC')).isoformat())
            write_locked(df,daily); write_locked(of,op); fcntl.flock(of,fcntl.LOCK_UN); fcntl.flock(df,fcntl.LOCK_UN)
            return used,accounted


def seal_operation(home,status,result_sha):
    _,op_p=quota_paths(home)
    with open(op_p,'r+b') as f:
        fcntl.flock(f,fcntl.LOCK_EX); op=load_json_file(f); op.update(status=status,result_sha256=result_sha,sealed_at=datetime.now(ZoneInfo('UTC')).isoformat()); write_locked(f,op); fcntl.flock(f,fcntl.LOCK_UN)


def qs(params):
    pairs=[]
    for k,v in params.items():
        if v is None or v=='': continue
        vals=v if isinstance(v,list) else [v]
        for x in vals:
            if x is None or x=='': continue
            if isinstance(x,bool): x='true' if x else 'false'
            pairs.append((k,str(x)))
    return urlencode(pairs)


def http_call(token,home,lane,action,path,params=None,allow404=False,allow400=False,progress=None):
    used,accounted=charge(home,lane,action)
    url=BASE+path; q=qs(params or {})
    if q: url+='?'+q
    append_jsonl(progress,{'call':used,'accounted':accounted,'action':action,'lane':lane,'path':path,'params':params or {}})
    cp=subprocess.run(['curl','--silent','--show-error','--http1.1','--compressed','--max-time','65','-H','Authorization: Bearer '+token,'-H','Accept: application/json','-w','\n%{http_code}',url],capture_output=True,text=True)
    if '\n' not in cp.stdout: raise RuntimeError('transport_'+str(cp.returncode))
    body,code_s=cp.stdout.rsplit('\n',1); code=int(code_s)
    if len(body.encode())>BODY_LIMIT: raise RuntimeError('body_limit')
    if code==404 and allow404: return code,{}
    if code==400 and allow400: return code,body
    if code==429: raise RuntimeError('http_429')
    if code<200 or code>=300: raise RuntimeError('http_'+str(code))
    try: data=json.loads(body)
    except Exception: raise RuntimeError('json_shape')
    if not isinstance(data,(dict,list)): raise RuntimeError('json_shape')
    return code,data


def search_id(x):
    if isinstance(x,dict):
        for k in ('searchId','id'):
            v=x.get(k)
            if str(v).isdigit() and int(v)>0: return int(v)
        for v in x.values():
            z=search_id(v)
            if z:return z
    elif isinstance(x,list):
        for v in x:
            z=search_id(v)
            if z:return z
    return None


def complete(x):
    if isinstance(x,dict):
        if isinstance(x.get('progress'),(int,float)) and x['progress']>=100:return True
        if str(x.get('status','')).lower() in ('complete','completed','done','ready'):return True
        return any(complete(v) for v in x.values() if isinstance(v,(dict,list)))
    if isinstance(x,list): return any(complete(v) for v in x)
    return False


def request_count(x):
    if isinstance(x,dict):
        for k in ('requestCount','requestsCount','request_count'):
            v=x.get(k)
            if str(v).isdigit(): return int(v)
        for v in x.values():
            if isinstance(v,(dict,list)):
                z=request_count(v)
                if z is not None:return z
    elif isinstance(x,list):
        for v in x:
            z=request_count(v)
            if z is not None:return z
    return None


def rows(x):
    if isinstance(x,list): return x
    if isinstance(x,dict):
        for k in ('hotels','results','items'):
            if isinstance(x.get(k),list):return x[k]
        for v in x.values():
            if isinstance(v,dict):
                z=rows(v)
                if z:return z
    return []


def tid(t):
    if not isinstance(t,dict):return ''
    return str(t.get('id') or t.get('tourId') or '')


def merge(acc,new):
    by={}
    for h in acc+new:
        if not isinstance(h,dict) or not str(h.get('id','')).isdigit():continue
        hid=int(h['id'])
        if hid not in by:
            by[hid]=dict(h); by[hid]['tours']=list(h.get('tours') or []); continue
        base=by[hid]; old=list(base.get('tours') or []); seen={tid(t) for t in old if tid(t)}
        for t in h.get('tours') or []:
            k=tid(t)
            if k and k not in seen: seen.add(k); old.append(t)
        base['tours']=old; by[hid]=base
    return [by[k] for k in sorted(by)]


def signature(rr): return (len(rr),sum(len(h.get('tours') or []) for h in rr if isinstance(h,dict)))


def explicit_operator_id(t):
    if not isinstance(t,dict):return None
    o=t.get('operator')
    if isinstance(o,dict) and str(o.get('id','')).isdigit():return int(o['id'])
    if str(t.get('operatorId','')).isdigit():return int(t['operatorId'])
    return None


def room_raw(x):
    out=set()
    def walk(v,key=''):
        if isinstance(v,dict):
            for k,z in v.items(): walk(z,str(k))
        elif isinstance(v,list):
            for z in v: walk(z,key)
        elif isinstance(v,(str,int,float)) and 'room' in key.lower():
            s=str(v).strip()
            if s and not s.isdigit():out.add(s[:500])
    walk(x); return sorted(out)


def link_evidence(detail):
    urls=[]; ids=set()
    def walk(v,key=''):
        if isinstance(v,dict):
            for k,z in v.items():walk(z,str(k))
        elif isinstance(v,list):
            for z in v:walk(z,key)
        elif isinstance(v,str) and ('link' in key.lower() or 'url' in key.lower()) and '://' in v:
            urls.append({'key':key,'url':v[:2500]})
            try:q=parse_qs(urlparse(v).query)
            except Exception:return
            for k,vals in q.items():
                if k.lower() not in ('hotellist','hotelcode','hotel_code','hotelid','hotel_id','hotelinc','hotel'):continue
                for val in vals:
                    for part in re.split('[,;]',val):
                        part=part.strip()
                        if part.isdigit() and int(part)>0:ids.add(int(part))
    walk(detail); return urls[:20],sorted(ids)


def wait_ready(token,home,sid,progress,lane,max_polls=15):
    last=None
    for _ in range(max_polls):
        time.sleep(2)
        _,last=http_call(token,home,lane,'search_status',f'/tours/search/{sid}/status',{'operatorStatus':False},progress=progress)
        if complete(last):return last
    raise RuntimeError('search_timeout')


def fetch_results(token,home,sid,progress,lane):
    _,d=http_call(token,home,lane,'search_results',f'/tours/search/{sid}',{'limit':10000},progress=progress)
    return rows(d)


def main():
    if '--self-test' in sys.argv:
        assert len(TARGET_IDS)==12 and len(set(TARGET_IDS))==12 and OPERATOR_ID==13 and DAILY_LIMIT==3000 and OP_CAP==180
        assert room_raw({'roomName':'FAMILY DELUXE SEA VIEW ROOM'})==['FAMILY DELUXE SEA VIEW ROOM']
        u,i=link_evidence({'operatorLink':'https://x.test/?HOTELLIST=5844'}); assert i==[5844] and len(u)==1
        print('MATCH_ANEX_UAE12_DIRECT_V1_SELFTEST_OK'); return 0
    if '--execute' not in sys.argv: raise RuntimeError('disabled')
    opdir=pathlib.Path(os.environ['MATCH_OPERATION_DIR']); home=os.environ['HOME']; token=sys.stdin.read().strip()
    if not token: raise RuntimeError('token_missing')
    res_path=opdir/'payload/reservation.json'; res=json.loads(res_path.read_text()); raw=res_path.read_bytes(); res_sha=hashlib.sha256(raw).hexdigest()
    if res.get('operation')!=OP or res.get('state')!='reserved_before_provider_access' or res.get('target',{}).get('hotel_ids')!=TARGET_IDS: raise RuntimeError('reservation_invalid')
    if res.get('daily_accounting',{}).get('known_prior_attempt_floor')!=KNOWN_FLOOR or res.get('daily_accounting',{}).get('provider_day')!=DAY: raise RuntimeError('reservation_quota_invalid')
    progress=opdir/'provider-ledger.ndjson'; provider=False; result={'operation':OP,'state':'starting','database_writes':0,'mapping_writes':0}
    try:
        q=reserve(home,res_sha); durable_write(opdir/'quota-reservation.json',q)
        provider=True
        params={'departureId':1,'countryId':9,'dateFrom':'2026-12-10','dateTo':'2026-12-16','nightsFrom':7,'nightsTo':10,'adults':2,'currency':'RUB','onlyCharter':False,'operatorIds':[13],'hotelIds':TARGET_IDS}
        _,start=http_call(token,home,'UAE12','search_start','/tours/search',params,progress=progress); sid=search_id(start)
        if not sid: raise RuntimeError('search_id_missing')
        wait_ready(token,home,sid,progress,'UAE12')
        union=merge([],fetch_results(token,home,sid,progress,'UAE12'))
        durable_write(opdir/'partial-0.json',union)
        foreign=[]
        for h in union:
            for t in h.get('tours') or []:
                oid=explicit_operator_id(t)
                if oid is not None and oid!=13: foreign.append({'hotel_id':h.get('id'),'tour_id':tid(t),'operator_id':oid})
        if foreign: raise RuntimeError('foreign_operator_in_filtered_search')
        details={}; anchors=[]; holds=[]
        target=set(TARGET_IDS)
        def capture_details(rr):
            for h in rr:
                if not isinstance(h,dict) or not str(h.get('id','')).isdigit():continue
                hid=int(h['id'])
                if hid not in target or hid in details:continue
                tours=[t for t in (h.get('tours') or []) if isinstance(t,dict) and tid(t)]
                if not tours:continue
                tour=tours[0]; tourid=tid(tour)
                code,d=http_call(token,home,'UAE12','tour_detail','/tours/'+tourid,{'currency':'RUB'},allow404=True,progress=progress)
                ev={'local_id':hid,'local_name':h.get('name'),'fresh_tour_id':tourid,'tv_room_raw':room_raw(h),'detail_room_raw':room_raw(d) if code!=404 else []}
                if code==404:
                    ev['reason']='fresh_detail_404'; holds.append(ev); details[hid]=ev; continue
                urls,native=link_evidence(d); ev['operator_link_fields']=urls; ev['native_ids']=native
                details[hid]=ev
                if len(native)==1: anchors.append(ev)
                else: ev['reason']='no_native_id' if not native else 'multiple_native_ids'; holds.append(ev)
        capture_details(union)
        rounds=[{'round':0,'hotels':signature(union)[0],'tours':signature(union)[1]}]; drained=False; drain_reason=None
        for r in range(1,21):
            before=signature(union)
            code,cont=http_call(token,home,'UAE12','search_continue',f'/tours/search/{sid}/continue',{},allow400=True,progress=progress)
            if code==400:
                durable_write(opdir/f'continue-{r}-http400.json',{'status':400,'body':str(cont)[:12000]})
                newer=merge(union,fetch_results(token,home,sid,progress,'UAE12')); after=signature(newer); added=(after[0]-before[0])+(after[1]-before[1]); union=newer
                durable_write(opdir/f'partial-{r}.json',union); capture_details(union)
                rounds.append({'round':r,'http_status':400,'added':added,'hotels':after[0],'tours':after[1]})
                if added==0: drained=True; drain_reason='continue_http_400_zero_growth'; break
                raise RuntimeError('continue_http400_with_growth')
            req=request_count(cont)
            if req not in (None,0):wait_ready(token,home,sid,progress,'UAE12')
            newer=merge(union,fetch_results(token,home,sid,progress,'UAE12')); after=signature(newer); added=(after[0]-before[0])+(after[1]-before[1]); union=newer
            durable_write(opdir/f'partial-{r}.json',union); capture_details(union)
            rounds.append({'round':r,'http_status':code,'request_count':req,'added':added,'hotels':after[0],'tours':after[1]})
            if req==0 or added==0:drained=True; drain_reason='request_count_zero' if req==0 else 'zero_growth'; break
        if not drained: raise RuntimeError('continue_cap')
        returned=sorted(int(h['id']) for h in union if isinstance(h,dict) and str(h.get('id','')).isdigit() and int(h['id']) in target)
        for hid in TARGET_IDS:
            if hid not in returned:holds.append({'local_id':hid,'reason':'not_returned'})
            elif hid not in details:holds.append({'local_id':hid,'reason':'no_fresh_tour'})
        _,op_p=quota_paths(home); opj=json.loads(op_p.read_text())
        result={'operation':OP,'state':'completed_read_only','provider_source':'direct_tourvisor','operator_filter':[13],'search_id':sid,'full_drain':True,'drain_reason':drain_reason,'rounds':rounds,'returned_target_hotels':len(returned),'anchors':anchors,'holds':holds,'details':list(details.values()),'provider_calls':int(opj.get('used') or 0),'database_writes':0,'mapping_writes':0,'no_replay':True}
        sha=durable_write(opdir/'result.json',result); seal_operation(home,'completed_read_only',sha); durable_write(opdir/'receipt.json',{'operation':OP,'state':result['state'],'provider_calls':result['provider_calls'],'anchors':len(anchors),'holds':len(holds),'result_sha256':sha,'database_writes':0,'mapping_writes':0,'no_replay':True}); print(json.dumps({'state':result['state'],'calls':result['provider_calls'],'anchors':len(anchors),'holds':len(holds),'drain_reason':drain_reason})); return 0
    except Exception as e:
        try:
            _,op_p=quota_paths(home); used=int(json.loads(op_p.read_text()).get('used') or 0) if op_p.exists() else 0
        except Exception: used=0
        state='terminal_failed_no_replay' if provider or used>0 else 'failed_before_provider_access'
        result={'operation':OP,'state':state,'reason':re.sub(r'[^A-Za-z0-9_.:-]','_',str(e))[:160],'provider_calls':used,'database_writes':0,'mapping_writes':0,'no_replay':state.startswith('terminal_')}
        sha=durable_write(opdir/'result.json',result)
        try: seal_operation(home,state,sha)
        except Exception: pass
        durable_write(opdir/'receipt.json',{'operation':OP,'state':state,'provider_calls':used,'reason':result['reason'],'result_sha256':sha,'database_writes':0,'mapping_writes':0,'no_replay':result['no_replay']}); print(json.dumps(result)); return 3

if __name__=='__main__': sys.exit(main())
