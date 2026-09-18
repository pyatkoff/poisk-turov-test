#!/usr/bin/env python3
import fcntl, hashlib, json, os, pathlib, re, subprocess, sys
from datetime import datetime
from urllib.parse import parse_qs, urlparse
from zoneinfo import ZoneInfo

OP='hotel-match-mauritius-retained-tour-detail-1971-20260919-v1'
BASE='https://api.tourvisor.ru/search/api/v1'
DAY='2026-09-19'
DAILY_LIMIT=3000
KNOWN_ACCOUNTED_FLOOR=396
OP_CAP=26
BODY_LIMIT=16*1024*1024
EXPECTED_LOCALS={11745,11751,11754,11756,11758,11761,11769,11770,11775,11788,11789,11792,11802,11805,15788,15794,26841,26842,28452,35164,35170,44413,60234,64582,68977,106703}
EXPECTED_MISSING_LOCAL=11771


def jdump(obj):
    return json.dumps(obj,ensure_ascii=False,sort_keys=True,separators=(',',':'))


def durable_bytes(path, raw):
    p=pathlib.Path(path); p.parent.mkdir(parents=True,exist_ok=True)
    tmp=p.with_name(p.name+'.tmp')
    with open(tmp,'wb') as f:
        f.write(raw); f.flush(); os.fsync(f.fileno())
    os.replace(tmp,p)
    dfd=os.open(str(p.parent),os.O_DIRECTORY)
    try: os.fsync(dfd)
    finally: os.close(dfd)
    return hashlib.sha256(raw).hexdigest()


def durable_json(path,obj):
    return durable_bytes(path,(json.dumps(obj,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode())


def append_jsonl(path,obj):
    p=pathlib.Path(path); p.parent.mkdir(parents=True,exist_ok=True)
    raw=(jdump(obj)+'\n').encode()
    with open(p,'ab') as f:
        fcntl.flock(f,fcntl.LOCK_EX); f.write(raw); f.flush(); os.fsync(f.fileno()); fcntl.flock(f,fcntl.LOCK_UN)


def read_locked(f):
    f.seek(0); raw=f.read()
    if isinstance(raw,bytes): raw=raw.decode()
    if not raw.strip(): return {}
    x=json.loads(raw); return x if isinstance(x,dict) else {}


def write_locked(f,obj):
    raw=(jdump(obj)+'\n').encode()
    f.seek(0); f.truncate(0)
    n=f.write(raw)
    if n!=len(raw): raise RuntimeError('locked_write_short')
    f.flush(); os.fsync(f.fileno())


def provider_day():
    return datetime.now(ZoneInfo('Europe/Moscow')).strftime('%Y-%m-%d')


def quota_paths(home):
    q=pathlib.Path(home)/'.anytour-match/provider-quotas'; q.mkdir(parents=True,exist_ok=True,mode=0o700)
    return q/f'tourvisor-test-{DAY}.json', q/f'tourvisor-{OP}.json'


def reserve(home,reservation_sha):
    if provider_day()!=DAY: raise RuntimeError('provider_day_mismatch')
    daily_p,op_p=quota_paths(home)
    with open(daily_p,'a+b') as df:
        fcntl.flock(df,fcntl.LOCK_EX); daily=read_locked(df)
        configured=int(daily.get('owner_daily_limit') or DAILY_LIMIT)
        if configured<=0: raise RuntimeError('daily_limit_invalid')
        limit=min(DAILY_LIMIT,configured)
        prior=max(0,int(daily.get('known_prior_attempt_floor') or 0))
        match=max(0,int(daily.get('match_new_attempts') or 0))
        accounted=max(KNOWN_ACCOUNTED_FLOOR,int(daily.get('accounted_requests') or 0),prior+match)
        if accounted>=limit: raise RuntimeError('daily_cap_before_reservation')
        daily.update(owner_daily_limit=limit,accounted_requests=accounted,provider_timezone='Europe/Moscow',updated_at=datetime.now(ZoneInfo('UTC')).isoformat())
        write_locked(df,daily); fcntl.flock(df,fcntl.LOCK_UN)
    try: fd=os.open(op_p,os.O_RDWR|os.O_CREAT|os.O_EXCL,0o600)
    except FileExistsError: raise RuntimeError('operation_already_reserved')
    with os.fdopen(fd,'r+b',buffering=0) as of:
        write_locked(of,{'operation':OP,'status':'reserved_before_provider_access','operation_cap':OP_CAP,'used':0,'reservation_sha256':reservation_sha,'provider_day':DAY,'created_at':datetime.now(ZoneInfo('UTC')).isoformat()})
    return {'daily_path':str(daily_p),'operation_path':str(op_p),'accounted_before':accounted,'daily_limit':limit}


def charge(home,local_id,tour_id):
    daily_p,op_p=quota_paths(home)
    with open(daily_p,'r+b',buffering=0) as df:
        fcntl.flock(df,fcntl.LOCK_EX); daily=read_locked(df)
        with open(op_p,'r+b',buffering=0) as of:
            fcntl.flock(of,fcntl.LOCK_EX); op=read_locked(of)
            if op.get('operation')!=OP or op.get('status') not in ('reserved_before_provider_access','provider_accessed'): raise RuntimeError('operation_reservation_invalid')
            used=max(0,int(op.get('used') or 0)); limit=min(DAILY_LIMIT,int(daily.get('owner_daily_limit') or DAILY_LIMIT))
            prior=max(0,int(daily.get('known_prior_attempt_floor') or 0)); match=max(0,int(daily.get('match_new_attempts') or 0))
            accounted=max(KNOWN_ACCOUNTED_FLOOR,int(daily.get('accounted_requests') or 0),prior+match)
            if used>=OP_CAP: raise RuntimeError('operation_cap')
            if accounted>=limit: raise RuntimeError('daily_cap')
            used+=1; match+=1; accounted=max(accounted+1,prior+match)
            op.update(status='provider_accessed',used=used,last_action='retained_tour_detail',last_local_id=local_id,last_tour_id=str(tour_id),updated_at=datetime.now(ZoneInfo('UTC')).isoformat())
            ops=daily.get('operations') if isinstance(daily.get('operations'),dict) else {}; ops[OP]=int(ops.get(OP) or 0)+1
            daily.update(match_new_attempts=match,accounted_requests=accounted,operations=ops,updated_at=datetime.now(ZoneInfo('UTC')).isoformat())
            write_locked(df,daily); write_locked(of,op); fcntl.flock(of,fcntl.LOCK_UN); fcntl.flock(df,fcntl.LOCK_UN)
            return used,accounted


def seal(home,status,result_sha):
    _,op_p=quota_paths(home)
    if not op_p.exists(): return
    with open(op_p,'r+b',buffering=0) as f:
        fcntl.flock(f,fcntl.LOCK_EX); op=read_locked(f); op.update(status=status,result_sha256=result_sha,sealed_at=datetime.now(ZoneInfo('UTC')).isoformat()); write_locked(f,op); fcntl.flock(f,fcntl.LOCK_UN)


def get_token(root):
    code='require $argv[1]; $v=defined("TOURVISOR_JWT") ? TOURVISOR_JWT : getenv("TOURVISOR_JWT"); fwrite(STDOUT,(string)$v);'
    cp=subprocess.run(['php','-r',code,'--',str(pathlib.Path(root)/'config.php')],capture_output=True,text=True,check=True)
    token=cp.stdout.strip()
    if not token: raise RuntimeError('token_missing')
    return token


def http_detail(token,home,local_id,tour_id,progress):
    used,accounted=charge(home,local_id,tour_id)
    path=f'/tours/{tour_id}'
    append_jsonl(progress,{'call':used,'accounted':accounted,'action':'retained_tour_detail','local_id':local_id,'tour_id':str(tour_id),'path':path})
    url=BASE+path+'?currency=RUB'
    cp=subprocess.run(['curl','--silent','--show-error','--http1.1','--compressed','--max-time','65','-H','Authorization: Bearer '+token,'-H','Accept: application/json','-w','\n%{http_code}',url],capture_output=True,text=True)
    if '\n' not in cp.stdout: raise RuntimeError('transport_'+str(cp.returncode))
    body,code_s=cp.stdout.rsplit('\n',1); code=int(code_s)
    if len(body.encode())>BODY_LIMIT: raise RuntimeError('body_limit')
    return code,body


def explicit_operator_id(detail):
    if not isinstance(detail,dict): return None
    o=detail.get('operator')
    if isinstance(o,dict) and str(o.get('id','')).isdigit(): return int(o['id'])
    if str(detail.get('operatorId','')).isdigit(): return int(detail['operatorId'])
    return None


def hotel_id(detail):
    if not isinstance(detail,dict): return None
    h=detail.get('hotel')
    if isinstance(h,dict) and str(h.get('id','')).isdigit(): return int(h['id'])
    if str(detail.get('hotelId','')).isdigit(): return int(detail['hotelId'])
    return None


def room_raw(x):
    out=[]; seen=set()
    def walk(v,key=''):
        if isinstance(v,dict):
            for k,z in v.items(): walk(z,str(k))
        elif isinstance(v,list):
            for z in v: walk(z,key)
        elif isinstance(v,(str,int,float)) and 'room' in key.lower():
            s=str(v).strip()
            if s and not s.isdigit() and s not in seen:
                seen.add(s); out.append(s[:500])
    walk(x); return out


def link_evidence(detail):
    links=[]; signed=[]; sensitive=re.compile(r'(?:token|password|auth|secret|session)',re.I)
    def walk(v,key=''):
        if isinstance(v,dict):
            for k,z in v.items(): walk(z,str(k))
        elif isinstance(v,list):
            for z in v: walk(z,key)
        elif isinstance(v,str) and ('link' in key.lower() or 'url' in key.lower()) and '://' in v:
            try:
                p=urlparse(v); q=parse_qs(p.query,keep_blank_values=True)
            except Exception: return
            if any(sensitive.search(str(k)) for k in q): return
            links.append({'key':key,'url':v[:2500]})
            for k,vals in q.items():
                if k.lower() not in ('hotellist','hotelcode','hotel_code','hotelid','hotel_id','hotelinc','hotel'): continue
                for val in vals:
                    for part in re.split('[,;]',val):
                        part=part.strip()
                        if re.fullmatch(r'-?[1-9][0-9]{0,12}',part): signed.append(int(part))
    walk(detail)
    uniq=[]
    for n in signed:
        if n not in uniq: uniq.append(n)
    eligible=len(uniq)==1 and uniq[0]>0
    return links[:30],uniq,(uniq[0] if eligible else None)


def self_test():
    links,signed,native=link_evidence({'operatorLink':'https://agent.anextour.ru/search/tour?HOTELLIST=5844'})
    assert native==5844 and signed==[5844] and len(links)==1
    _,signed,native=link_evidence({'operatorLink':'https://agent.anextour.ru/search/tour?HOTELLIST=7717,-1025133'})
    assert signed==[7717,-1025133] and native is None
    _,signed,native=link_evidence({'operatorLink':'https://agent.anextour.ru/search/tour?HOTELLIST=1&token=x'})
    assert signed==[] and native is None
    assert len(EXPECTED_LOCALS)==26 and EXPECTED_MISSING_LOCAL not in EXPECTED_LOCALS and OP_CAP==26
    print('MATCH_MAURITIUS_RETAINED_TOUR_DETAIL_V1_SELFTEST_OK')


def main():
    if '--self-test' in sys.argv: self_test(); return 0
    if '--execute' not in sys.argv: raise RuntimeError('disabled')
    opdir=pathlib.Path(os.environ['MATCH_OPERATION_DIR']); root=os.environ['ANYTOUR_ROOT']; home=os.environ['HOME']
    plan_path=opdir/'payload/plan.json'; reservation_path=opdir/'payload/reservation.json'
    plan=json.loads(plan_path.read_text()); reservation=json.loads(reservation_path.read_text()); res_sha=hashlib.sha256(reservation_path.read_bytes()).hexdigest()
    tuples=plan.get('tuples') if isinstance(plan.get('tuples'),list) else []
    if plan.get('operation')!=OP or reservation.get('operation')!=OP or reservation.get('state')!='reserved_before_provider_access': raise RuntimeError('reservation_invalid')
    if plan.get('source_result_sha256')!='a561a8efe62659a6ed556a8c3d8a06341d669f47714c0f1dd0be0a5ee09d67d8' or plan.get('source_partial20_sha256')!='5876f3c7c5f095f945719363f287dd454c941425eefe77d917c12f1829087b7d': raise RuntimeError('source_digest_invalid')
    if len(tuples)!=26 or {int(x['local_id']) for x in tuples}!=EXPECTED_LOCALS or len({str(x['tour_id']) for x in tuples})!=26: raise RuntimeError('plan_invalid')
    if reservation.get('daily_accounting')!={'provider_day':DAY,'daily_limit':3000,'known_accounted_floor':KNOWN_ACCOUNTED_FLOOR,'operation_cap':OP_CAP}: raise RuntimeError('quota_reservation_invalid')
    token=get_token(root); progress=opdir/'provider-ledger.ndjson'; provider=False; anchors=[]; holds=[]; attempted=[]
    try:
        q=reserve(home,res_sha); durable_json(opdir/'quota-reservation.json',q); provider=True
        for item in tuples:
            local_id=int(item['local_id']); tour_id=str(item['tour_id'])
            code,body=http_detail(token,home,local_id,tour_id,progress)
            raw_path=opdir/f'detail-{local_id}.json'
            if code==200:
                raw=body.encode(); detail_sha=durable_bytes(raw_path,raw)
                try: detail=json.loads(body)
                except Exception:
                    ev={'local_id':local_id,'tour_id':tour_id,'http_status':code,'detail_sha256':detail_sha,'reason':'detail_json_shape'}; durable_json(opdir/f'evidence-{local_id}.json',ev); holds.append(ev); attempted.append(local_id); continue
                if not isinstance(detail,dict):
                    ev={'local_id':local_id,'tour_id':tour_id,'http_status':code,'detail_sha256':detail_sha,'reason':'detail_json_shape'}; durable_json(opdir/f'evidence-{local_id}.json',ev); holds.append(ev); attempted.append(local_id); continue
                oid=explicit_operator_id(detail); hid=hotel_id(detail); links,signed,native=link_evidence(detail)
                ev={'local_id':local_id,'tour_id':tour_id,'http_status':code,'detail_sha256':detail_sha,'returned_hotel_id':hid,'operator_id':oid,'operator_link_fields':links,'signed_native_tokens':signed,'native_anex_id':native,'detail_room_raw':room_raw(detail)}
                reason=None
                if hid is not None and hid!=local_id: reason='detail_hotel_mismatch'
                elif oid is not None and oid!=13: reason='foreign_operator_detail'
                elif native is None: reason='no_native_id' if not signed else 'ambiguous_signed_hotellist'
                if reason:
                    ev['reason']=reason; holds.append(ev)
                else: anchors.append(ev)
                durable_json(opdir/f'evidence-{local_id}.json',ev); attempted.append(local_id)
            elif code==404:
                ev={'local_id':local_id,'tour_id':tour_id,'http_status':404,'reason':'retained_tour_detail_404'}; durable_json(opdir/f'evidence-{local_id}.json',ev); holds.append(ev); attempted.append(local_id)
            else:
                durable_bytes(raw_path,body.encode())
                ev={'local_id':local_id,'tour_id':tour_id,'http_status':code,'reason':'retained_tour_detail_http_'+str(code)}; durable_json(opdir/f'evidence-{local_id}.json',ev); holds.append(ev); attempted.append(local_id)
        _,op_p=quota_paths(home); opj=json.loads(op_p.read_text())
        result={'operation':OP,'state':'completed_read_only','provider_source':'direct_tourvisor_retained_tour_detail','source_run_id':35403637042,'source_artifact_id':10571338567,'planned_hotels':26,'attempted_hotels':len(attempted),'anchors':anchors,'holds':holds,'deferred_missing_local_ids':[EXPECTED_MISSING_LOCAL],'provider_calls':int(opj.get('used') or 0),'database_writes':0,'mapping_writes':0,'no_search_start':True,'no_continue':True,'no_dates':True,'no_replay':True}
        sha=durable_json(opdir/'result.json',result); seal(home,'completed_read_only',sha); durable_json(opdir/'receipt.json',{'operation':OP,'state':result['state'],'provider_calls':result['provider_calls'],'attempted_hotels':len(attempted),'anchors':len(anchors),'holds':len(holds),'result_sha256':sha,'database_writes':0,'mapping_writes':0,'no_replay':True})
        print(json.dumps({'state':result['state'],'calls':result['provider_calls'],'anchors':len(anchors),'holds':len(holds),'deferred':[EXPECTED_MISSING_LOCAL]},ensure_ascii=False)); return 0
    except Exception as e:
        try:
            _,op_p=quota_paths(home); used=int(json.loads(op_p.read_text()).get('used') or 0) if op_p.exists() else 0
        except Exception: used=0
        state='terminal_failed_no_replay' if provider or used>0 else 'failed_before_provider_access'
        result={'operation':OP,'state':state,'reason':re.sub(r'[^A-Za-z0-9_.:-]','_',str(e))[:180],'provider_calls':used,'attempted_hotels':len(attempted),'anchors':anchors,'holds':holds,'deferred_missing_local_ids':[EXPECTED_MISSING_LOCAL],'database_writes':0,'mapping_writes':0,'no_replay':state.startswith('terminal_')}
        sha=durable_json(opdir/'result.json',result)
        try: seal(home,state,sha)
        except Exception: pass
        durable_json(opdir/'receipt.json',{'operation':OP,'state':state,'provider_calls':used,'attempted_hotels':len(attempted),'anchors':len(anchors),'holds':len(holds),'reason':result['reason'],'result_sha256':sha,'database_writes':0,'mapping_writes':0,'no_replay':result['no_replay']}); print(json.dumps(result,ensure_ascii=False)); return 3

if __name__=='__main__': sys.exit(main())
