#!/usr/bin/env python3
import fcntl, hashlib, json, os, pathlib, re, subprocess, sys
from datetime import datetime
from urllib.parse import parse_qs, urlparse
from zoneinfo import ZoneInfo

OP='hotel-match-mauritius11771-retained-detail-1971-20260919-v1'
BASE='https://api.tourvisor.ru/search/api/v1'
DAY='2026-09-19'; DAILY_LIMIT=3000; FLOOR=595; LOCAL_ID=11771; TOUR_ID='13278420158714'; OP_CAP=1

def dump(x): return json.dumps(x,ensure_ascii=False,sort_keys=True,separators=(',',':'))
def durable(path,obj):
    p=pathlib.Path(path); p.parent.mkdir(parents=True,exist_ok=True); raw=(json.dumps(obj,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode(); tmp=p.with_name(p.name+'.tmp')
    with open(tmp,'wb') as f: f.write(raw); f.flush(); os.fsync(f.fileno())
    os.replace(tmp,p); d=os.open(str(p.parent),os.O_DIRECTORY); os.fsync(d); os.close(d); return hashlib.sha256(raw).hexdigest()
def readf(f):
    f.seek(0); x=f.read(); x=x.decode() if isinstance(x,bytes) else x; return json.loads(x) if x.strip() else {}
def writef(f,obj):
    raw=(dump(obj)+'\n').encode(); f.seek(0); f.truncate(0); n=f.write(raw); assert n==len(raw); f.flush(); os.fsync(f.fileno())
def qpaths(home):
    q=pathlib.Path(home)/'.anytour-match/provider-quotas'; q.mkdir(parents=True,exist_ok=True,mode=0o700); return q/f'tourvisor-test-{DAY}.json',q/f'tourvisor-{OP}.json'
def reserve(home,res_sha):
    if datetime.now(ZoneInfo('Europe/Moscow')).strftime('%Y-%m-%d')!=DAY: raise RuntimeError('provider_day_mismatch')
    dp,op=qpaths(home)
    with open(dp,'a+b') as f:
        fcntl.flock(f,fcntl.LOCK_EX); d=readf(f); lim=min(DAILY_LIMIT,int(d.get('owner_daily_limit') or DAILY_LIMIT)); prior=max(0,int(d.get('known_prior_attempt_floor') or 0)); match=max(0,int(d.get('match_new_attempts') or 0)); accounted=max(FLOOR,int(d.get('accounted_requests') or 0),prior+match)
        if accounted>=lim: raise RuntimeError('daily_cap')
        d.update(owner_daily_limit=lim,accounted_requests=accounted,provider_timezone='Europe/Moscow',updated_at=datetime.now(ZoneInfo('UTC')).isoformat()); writef(f,d); fcntl.flock(f,fcntl.LOCK_UN)
    fd=os.open(op,os.O_RDWR|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'r+b',buffering=0) as f: writef(f,{'operation':OP,'status':'reserved_before_provider_access','used':0,'operation_cap':1,'reservation_sha256':res_sha,'provider_day':DAY})
    return accounted
def charge(home):
    dp,op=qpaths(home)
    with open(dp,'r+b',buffering=0) as df:
        fcntl.flock(df,fcntl.LOCK_EX); d=readf(df)
        with open(op,'r+b',buffering=0) as of:
            fcntl.flock(of,fcntl.LOCK_EX); o=readf(of); used=int(o.get('used') or 0); prior=max(0,int(d.get('known_prior_attempt_floor') or 0)); match=max(0,int(d.get('match_new_attempts') or 0)); accounted=max(FLOOR,int(d.get('accounted_requests') or 0),prior+match); lim=min(DAILY_LIMIT,int(d.get('owner_daily_limit') or DAILY_LIMIT))
            if used>=1 or accounted>=lim: raise RuntimeError('quota_cap')
            used+=1; match+=1; accounted=max(accounted+1,prior+match); o.update(status='provider_accessed',used=used,last_action='retained_tour_detail',updated_at=datetime.now(ZoneInfo('UTC')).isoformat()); ops=d.get('operations') if isinstance(d.get('operations'),dict) else {}; ops[OP]=int(ops.get(OP) or 0)+1; d.update(match_new_attempts=match,accounted_requests=accounted,operations=ops,updated_at=datetime.now(ZoneInfo('UTC')).isoformat()); writef(df,d); writef(of,o); fcntl.flock(of,fcntl.LOCK_UN); fcntl.flock(df,fcntl.LOCK_UN); return accounted
def seal(home,status,sha):
    _,op=qpaths(home)
    with open(op,'r+b',buffering=0) as f: fcntl.flock(f,fcntl.LOCK_EX); o=readf(f); o.update(status=status,result_sha256=sha,sealed_at=datetime.now(ZoneInfo('UTC')).isoformat()); writef(f,o); fcntl.flock(f,fcntl.LOCK_UN)
def token(root):
    code='require $argv[1]; $v=defined("TOURVISOR_JWT") ? TOURVISOR_JWT : getenv("TOURVISOR_JWT"); fwrite(STDOUT,(string)$v);'; cp=subprocess.run(['php','-r',code,'--',str(pathlib.Path(root)/'config.php')],capture_output=True,text=True,check=True); t=cp.stdout.strip();
    if not t: raise RuntimeError('token_missing')
    return t
def link(detail):
    links=[]; signed=[]
    def walk(v,k=''):
        if isinstance(v,dict):
            for a,b in v.items(): walk(b,str(a))
        elif isinstance(v,list):
            for b in v: walk(b,k)
        elif isinstance(v,str) and ('link' in k.lower() or 'url' in k.lower()) and '://' in v:
            try:q=parse_qs(urlparse(v).query,keep_blank_values=True)
            except: return
            if any(re.search(r'token|password|auth|secret|session',x,re.I) for x in q): return
            links.append({'key':k,'url':v[:2500]})
            for x,vals in q.items():
                if x.lower() not in ('hotellist','hotelcode','hotel_code','hotelid','hotel_id','hotelinc','hotel'): continue
                for val in vals:
                    for part in re.split('[,;]',val):
                        part=part.strip()
                        if re.fullmatch(r'-?[1-9][0-9]{0,12}',part): signed.append(int(part))
    walk(detail); u=[]
    for n in signed:
        if n not in u:u.append(n)
    return links,u,(u[0] if len(u)==1 and u[0]>0 else None)
def main():
    if '--self-test' in sys.argv:
        _,s,n=link({'operatorLink':'https://agent.anextour.ru/search/tour?HOTELLIST=1,-2'}); assert s==[1,-2] and n is None; print('M11771_SELFTEST_OK'); return 0
    if '--execute' not in sys.argv: raise RuntimeError('disabled')
    opdir=pathlib.Path(os.environ['MATCH_OPERATION_DIR']); home=os.environ['HOME']; root=os.environ['ANYTOUR_ROOT']; res=opdir/'payload/reservation.json'; r=json.loads(res.read_text()); raw=res.read_bytes()
    if r.get('operation')!=OP or r.get('local_id')!=LOCAL_ID or str(r.get('tour_id'))!=TOUR_ID or r.get('daily_floor')!=FLOOR: raise RuntimeError('reservation_invalid')
    provider=False
    try:
        before=reserve(home,hashlib.sha256(raw).hexdigest()); provider=True; accounted=charge(home); t=token(root); url=BASE+f'/tours/{TOUR_ID}?currency=RUB'; cp=subprocess.run(['curl','--silent','--show-error','--http1.1','--compressed','--max-time','65','-H','Authorization: Bearer '+t,'-H','Accept: application/json','-w','\n%{http_code}',url],capture_output=True,text=True)
        if cp.returncode!=0 or '\n' not in cp.stdout: raise RuntimeError('transport')
        body,cs=cp.stdout.rsplit('\n',1); code=int(cs); pathlib.Path(opdir/'detail-11771.raw').write_text(body)
        ev={'local_id':LOCAL_ID,'tour_id':TOUR_ID,'http_status':code,'accounted_before':before,'accounted_after':accounted}
        if code==200:
            d=json.loads(body); hid=(d.get('hotel') or {}).get('id') if isinstance(d.get('hotel'),dict) else d.get('hotelId'); oid=(d.get('operator') or {}).get('id') if isinstance(d.get('operator'),dict) else d.get('operatorId'); links,signed,native=link(d); ev.update(returned_hotel_id=int(hid) if str(hid).isdigit() else None,operator_id=int(oid) if str(oid).isdigit() else None,operator_link_fields=links,signed_native_tokens=signed,native_anex_id=native,detail_sha256=hashlib.sha256(body.encode()).hexdigest()); reason=None
            if ev['returned_hotel_id'] not in (None,LOCAL_ID): reason='detail_hotel_mismatch'
            elif ev['operator_id'] not in (None,13): reason='foreign_operator'
            elif native is None: reason='no_native_id' if not signed else 'ambiguous_signed_hotellist'
            if reason: ev['reason']=reason; state='completed_hold'
            else: state='completed_anchor'
        elif code==404: ev['reason']='retained_tour_detail_404'; state='completed_hold'
        else: ev['reason']='http_'+str(code); state='completed_hold'
        durable(opdir/'evidence.json',ev); result={'operation':OP,'state':state,'evidence':ev,'provider_calls':1,'database_writes':0,'mapping_writes':0,'no_search_start':True,'no_continue':True,'no_replay':True}; sha=durable(opdir/'result.json',result); seal(home,state,sha); durable(opdir/'receipt.json',{'operation':OP,'state':state,'provider_calls':1,'result_sha256':sha,'no_replay':True}); print(json.dumps({'state':state,'native_anex_id':ev.get('native_anex_id'),'reason':ev.get('reason'),'accounted_after':accounted})); return 0
    except Exception as e:
        state='terminal_failed_no_replay' if provider else 'failed_before_provider_access'; result={'operation':OP,'state':state,'reason':re.sub(r'[^A-Za-z0-9_.:-]','_',str(e))[:160],'provider_calls':1 if provider else 0,'database_writes':0,'mapping_writes':0,'no_replay':provider}; sha=durable(opdir/'result.json',result); durable(opdir/'receipt.json',{'operation':OP,'state':state,'result_sha256':sha,'no_replay':provider}); print(json.dumps(result)); return 3
if __name__=='__main__': sys.exit(main())
