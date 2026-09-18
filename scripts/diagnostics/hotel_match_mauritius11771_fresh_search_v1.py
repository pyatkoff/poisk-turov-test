#!/usr/bin/env python3
import fcntl, hashlib, json, os, pathlib, re, subprocess, sys, time
from datetime import datetime
from urllib.parse import urlencode, parse_qs, urlparse
from zoneinfo import ZoneInfo

OP='hotel-match-mauritius11771-fresh-search-1971-20260919-v1'
BASE='https://api.tourvisor.ru/search/api/v1'
DAY='2026-09-19'; LIMIT=3000; FLOOR=596; OP_CAP=7
LOCAL_ID=11771; OPERATOR_ID=13
PARAMS={'departureId':1,'countryId':27,'dateFrom':'2026-10-28','dateTo':'2026-10-28','nightsFrom':7,'nightsTo':7,'adults':2,'currency':'RUB','onlyCharter':'false','operatorIds':13,'hotelIds':11771}

def js(x): return json.dumps(x,ensure_ascii=False,sort_keys=True,separators=(',',':'))
def put_bytes(path,raw):
    p=pathlib.Path(path); p.parent.mkdir(parents=True,exist_ok=True); tmp=p.with_name(p.name+'.tmp')
    with open(tmp,'wb') as f: f.write(raw); f.flush(); os.fsync(f.fileno())
    os.replace(tmp,p); fd=os.open(str(p.parent),os.O_DIRECTORY); os.fsync(fd); os.close(fd); return hashlib.sha256(raw).hexdigest()
def put_json(path,obj): return put_bytes(path,(json.dumps(obj,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode())
def read_locked(f):
    f.seek(0); r=f.read(); r=r.decode() if isinstance(r,bytes) else r; return json.loads(r) if r.strip() else {}
def write_locked(f,obj):
    raw=(js(obj)+'\n').encode(); f.seek(0); f.truncate(0); n=f.write(raw)
    if n!=len(raw): raise RuntimeError('quota_write_short')
    f.flush(); os.fsync(f.fileno())
def qpaths(home):
    q=pathlib.Path(home)/'.anytour-match/provider-quotas'; q.mkdir(parents=True,exist_ok=True,mode=0o700); return q/f'tourvisor-test-{DAY}.json',q/f'tourvisor-{OP}.json'
def reserve(home,res_sha):
    if datetime.now(ZoneInfo('Europe/Moscow')).strftime('%Y-%m-%d')!=DAY: raise RuntimeError('provider_day_mismatch')
    dp,op=qpaths(home)
    with open(dp,'a+b') as f:
        fcntl.flock(f,fcntl.LOCK_EX); d=read_locked(f); lim=min(LIMIT,int(d.get('owner_daily_limit') or LIMIT)); prior=max(0,int(d.get('known_prior_attempt_floor') or 0)); match=max(0,int(d.get('match_new_attempts') or 0)); accounted=max(FLOOR,int(d.get('accounted_requests') or 0),prior+match)
        if accounted>=lim: raise RuntimeError('daily_cap')
        d.update(owner_daily_limit=lim,accounted_requests=accounted,provider_timezone='Europe/Moscow',updated_at=datetime.now(ZoneInfo('UTC')).isoformat()); write_locked(f,d); fcntl.flock(f,fcntl.LOCK_UN)
    fd=os.open(op,os.O_RDWR|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'r+b',buffering=0) as f: write_locked(f,{'operation':OP,'status':'reserved_before_provider_access','used':0,'operation_cap':OP_CAP,'reservation_sha256':res_sha,'provider_day':DAY})
    return accounted
def charge(home,action):
    dp,op=qpaths(home)
    with open(dp,'r+b',buffering=0) as df:
        fcntl.flock(df,fcntl.LOCK_EX); d=read_locked(df)
        with open(op,'r+b',buffering=0) as of:
            fcntl.flock(of,fcntl.LOCK_EX); o=read_locked(of); used=int(o.get('used') or 0); lim=min(LIMIT,int(d.get('owner_daily_limit') or LIMIT)); prior=max(0,int(d.get('known_prior_attempt_floor') or 0)); match=max(0,int(d.get('match_new_attempts') or 0)); accounted=max(FLOOR,int(d.get('accounted_requests') or 0),prior+match)
            if used>=OP_CAP or accounted>=lim: raise RuntimeError('quota_cap')
            used+=1; match+=1; accounted=max(accounted+1,prior+match); o.update(status='provider_accessed',used=used,last_action=action,updated_at=datetime.now(ZoneInfo('UTC')).isoformat()); ops=d.get('operations') if isinstance(d.get('operations'),dict) else {}; ops[OP]=int(ops.get(OP) or 0)+1; d.update(match_new_attempts=match,accounted_requests=accounted,operations=ops,updated_at=datetime.now(ZoneInfo('UTC')).isoformat()); write_locked(df,d); write_locked(of,o); fcntl.flock(of,fcntl.LOCK_UN); fcntl.flock(df,fcntl.LOCK_UN); return used,accounted
def seal(home,status,sha):
    _,op=qpaths(home)
    with open(op,'r+b',buffering=0) as f: fcntl.flock(f,fcntl.LOCK_EX); o=read_locked(f); o.update(status=status,result_sha256=sha,sealed_at=datetime.now(ZoneInfo('UTC')).isoformat()); write_locked(f,o); fcntl.flock(f,fcntl.LOCK_UN)
def current_preflight(root,registry):
    code=r'''$root=$argv[1];$reg=$argv[2];$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbf;require_once $reg;$d=v2_data_db();$d->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$d->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$d->exec('START TRANSACTION READ ONLY');$r=AnyTourAnexSearchMappingRegistry::fromPdo($d);$ids=$r->previewHotelIds([11771]);$s=$d->prepare('SELECT id,country_id,name,is_active FROM catalog_hotels WHERE id=?');$s->execute([11771]);$h=$s->fetch(PDO::FETCH_ASSOC);$q=$d->prepare('SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE catalog_hotel_id=? LIMIT 20');$q->execute([11771]);$m=$q->fetchAll(PDO::FETCH_ASSOC);$d->rollBack();echo json_encode(['accepted_ids'=>$ids,'hotel'=>$h,'manual'=>$m],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);'''
    cp=subprocess.run(['php','-r',code,'--',root,registry],capture_output=True,text=True,check=True); d=json.loads(cp.stdout)
    h=d.get('hotel') or {}; reasons=[]
    if d.get('accepted_ids'): reasons.append('already_accepted_anex')
    if d.get('manual'): reasons.append('target_manual_decision')
    if int(h.get('id') or 0)!=LOCAL_ID or int(h.get('is_active') or 0)!=1 or int(h.get('country_id') or 0)!=27: reasons.append('target_not_active_country27')
    return d,reasons
def get_token(root):
    code='require $argv[1]; $v=defined("TOURVISOR_JWT") ? TOURVISOR_JWT : getenv("TOURVISOR_JWT"); fwrite(STDOUT,(string)$v);'; cp=subprocess.run(['php','-r',code,'--',str(pathlib.Path(root)/'config.php')],capture_output=True,text=True,check=True); t=cp.stdout.strip()
    if not t: raise RuntimeError('token_missing')
    return t
def http(token,home,action,path,params,out):
    call,accounted=charge(home,action); url=BASE+path+('?' + urlencode(params,doseq=True) if params else ''); cp=subprocess.run(['curl','--silent','--show-error','--http1.1','--compressed','--max-time','65','-H','Authorization: Bearer '+token,'-H','Accept: application/json','-w','\n%{http_code}',url],capture_output=True,text=True)
    if cp.returncode!=0 or '\n' not in cp.stdout: raise RuntimeError('transport_'+action)
    body,cs=cp.stdout.rsplit('\n',1); code=int(cs); put_bytes(out,body.encode())
    if code==429: raise RuntimeError('http_429')
    if code<200 or code>=300: raise RuntimeError('http_'+str(code)+'_'+action)
    try:d=json.loads(body)
    except: raise RuntimeError('json_'+action)
    return d,call,accounted
def search_id(x):
    if isinstance(x,dict):
        for k in ('searchId','id'):
            v=x.get(k)
            if str(v).isdigit() and int(v)>0:return int(v)
        for v in x.values():
            r=search_id(v)
            if r:return r
    elif isinstance(x,list):
        for v in x:
            r=search_id(v)
            if r:return r
    return None
def complete(x):
    if isinstance(x,dict):
        try:
            if int(x.get('progress') or 0)>=100:return True
        except:pass
        if str(x.get('status') or '').lower() in ('complete','completed','done','ready'):return True
        return any(complete(v) for v in x.values())
    if isinstance(x,list): return any(complete(v) for v in x)
    return False
def rows(x):
    if isinstance(x,list): return x
    if isinstance(x,dict):
        for k in ('hotels','results','items'):
            if isinstance(x.get(k),list): return x[k]
    return []
def find_tour(result):
    for h in rows(result):
        if not isinstance(h,dict) or int(h.get('id') or 0)!=LOCAL_ID:continue
        for t in h.get('tours') or []:
            if not isinstance(t,dict):continue
            o=t.get('operator'); oid=(o or {}).get('id') if isinstance(o,dict) else t.get('operatorId'); tid=str(t.get('id') or t.get('tourId') or '')
            if str(oid)=='13' and tid.isdigit():return tid,h
    return None,None
def detail_ids(d):
    h=d.get('hotel'); hid=(h or {}).get('id') if isinstance(h,dict) else d.get('hotelId'); o=d.get('operator'); oid=(o or {}).get('id') if isinstance(o,dict) else d.get('operatorId'); return (int(hid) if str(hid).isdigit() else None,int(oid) if str(oid).isdigit() else None)
def links(d):
    out=[]; signed=[]
    def walk(v,k=''):
        if isinstance(v,dict):
            for a,b in v.items():walk(b,str(a))
        elif isinstance(v,list):
            for b in v:walk(b,k)
        elif isinstance(v,str) and ('link' in k.lower() or 'url' in k.lower()) and '://' in v:
            try:q=parse_qs(urlparse(v).query,keep_blank_values=True)
            except:return
            if any(re.search(r'token|password|auth|secret|session',a,re.I) for a in q):return
            out.append({'key':k,'url':v[:2500]})
            for a,vals in q.items():
                if a.lower() not in ('hotellist','hotelcode','hotel_code','hotelid','hotel_id','hotelinc','hotel'):continue
                for val in vals:
                    for part in re.split('[,;]',val):
                        part=part.strip()
                        if re.fullmatch(r'-?[1-9][0-9]{0,12}',part):signed.append(int(part))
    walk(d); u=[]
    for n in signed:
        if n not in u:u.append(n)
    return out,u,(u[0] if len(u)==1 and u[0]>0 else None)
def main():
    if '--self-test' in sys.argv:
        assert OP_CAP==7 and PARAMS['hotelIds']==11771 and PARAMS['operatorIds']==13 and PARAMS['dateFrom']==PARAMS['dateTo']=='2026-10-28' and PARAMS['nightsFrom']==PARAMS['nightsTo']==7; print('M11771_FRESH_SELFTEST_OK');return 0
    if '--execute' not in sys.argv:raise RuntimeError('disabled')
    opdir=pathlib.Path(os.environ['MATCH_OPERATION_DIR']); root=os.environ['ANYTOUR_ROOT']; home=os.environ['HOME']; reg=str(opdir/'payload/anex-search-mapping-registry.php'); res=opdir/'payload/reservation.json'; rv=json.loads(res.read_text())
    if rv.get('operation')!=OP or rv.get('daily_floor')!=FLOOR:raise RuntimeError('reservation_invalid')
    pf,reasons=current_preflight(root,reg); put_json(opdir/'current-preflight.json',pf)
    if reasons:
        result={'operation':OP,'state':'completed_current_hold','current_holds':reasons,'provider_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':True}; sha=put_json(opdir/'result.json',result); put_json(opdir/'receipt.json',{'operation':OP,'state':result['state'],'provider_calls':0,'result_sha256':sha,'no_replay':True}); print(json.dumps(result));return 0
    provider=False
    try:
        before=reserve(home,hashlib.sha256(res.read_bytes()).hexdigest()); provider=True; tok=get_token(root)
        start,_,_=http(tok,home,'search_start','/tours/search',PARAMS,opdir/'search-start.json'); sid=search_id(start)
        if not sid:raise RuntimeError('search_id')
        time.sleep(5); found_tid=None; found_hotel=None; attempts=[]; accounted=None
        for i,wait_s in enumerate((0,5,8),start=1):
            if wait_s:time.sleep(wait_s)
            rr,_,accounted=http(tok,home,'results_'+str(i),f'/tours/search/{sid}',{'limit':10000},opdir/f'results-{i}.json'); tid,h=find_tour(rr); attempts.append({'results_attempt':i,'found':bool(tid)})
            if tid:found_tid,found_hotel=tid,h;break
            if i<3:
                st,_,accounted=http(tok,home,'status_'+str(i),f'/tours/search/{sid}/status',{'operatorStatus':'false'},opdir/f'status-{i}.json'); done=complete(st); attempts[-1]['complete']=done
                if done:break
        if not found_tid:
            _,opp=qpaths(home); used=int(read_locked(open(opp,'rb')).get('used') or 0); result={'operation':OP,'state':'completed_search_miss','search_id':sid,'attempts':attempts,'provider_calls':used,'accounted_before':before,'accounted_after':accounted,'database_writes':0,'mapping_writes':0,'no_continue':True,'no_dates':True,'no_replay':True}; sha=put_json(opdir/'result.json',result);seal(home,result['state'],sha);put_json(opdir/'receipt.json',{'operation':OP,'state':result['state'],'provider_calls':used,'result_sha256':sha,'no_replay':True});print(json.dumps(result));return 0
        det,_,accounted=http(tok,home,'detail',f'/tours/{found_tid}',{'currency':'RUB'},opdir/'detail.json'); hid,oid=detail_ids(det); link_fields,signed,native=links(det); ev={'local_id':LOCAL_ID,'tour_id':found_tid,'returned_hotel_id':hid,'operator_id':oid,'operator_link_fields':link_fields,'signed_native_tokens':signed,'native_anex_id':native,'detail_sha256':hashlib.sha256((opdir/'detail.json').read_bytes()).hexdigest()}; reason=None
        if hid not in (None,LOCAL_ID):reason='detail_hotel_mismatch'
        elif oid not in (None,13):reason='foreign_operator'
        elif native is None:reason='no_native_id' if not signed else 'ambiguous_signed_hotellist'
        if reason:ev['reason']=reason; state='completed_hold'
        else:state='completed_anchor'
        put_json(opdir/'evidence.json',ev); _,opp=qpaths(home); used=int(read_locked(open(opp,'rb')).get('used') or 0); result={'operation':OP,'state':state,'search_id':sid,'attempts':attempts,'evidence':ev,'provider_calls':used,'accounted_before':before,'accounted_after':accounted,'database_writes':0,'mapping_writes':0,'no_continue':True,'no_dates':True,'no_replay':True};sha=put_json(opdir/'result.json',result);seal(home,state,sha);put_json(opdir/'receipt.json',{'operation':OP,'state':state,'provider_calls':used,'result_sha256':sha,'no_replay':True});print(json.dumps({'state':state,'calls':used,'native':native,'reason':reason,'accounted_after':accounted}));return 0
    except Exception as e:
        try:_,opp=qpaths(home); used=int(read_locked(open(opp,'rb')).get('used') or 0) if opp.exists() else 0
        except:used=0
        state='terminal_failed_no_replay' if provider or used else 'failed_before_provider_access'; result={'operation':OP,'state':state,'reason':re.sub(r'[^A-Za-z0-9_.:-]','_',str(e))[:180],'provider_calls':used,'database_writes':0,'mapping_writes':0,'no_replay':bool(provider or used)};sha=put_json(opdir/'result.json',result);put_json(opdir/'receipt.json',{'operation':OP,'state':state,'provider_calls':used,'result_sha256':sha,'no_replay':result['no_replay']});print(json.dumps(result));return 3
if __name__=='__main__':sys.exit(main())
