#!/usr/bin/env python3
"""MATCH COMMON4 detail-first acquisition from sealed user_search tour IDs.

Exactly one Tourvisor detail GET per selected edge, no search/status/results/dates/continue,
no DB/mapping writes. Every physical HTTP is charged to the existing shared daily ledger
before access. Operation is terminal/no-replay after its first provider access.
"""
import base64, collections, datetime as dt, fcntl, gzip, hashlib, json, os, pathlib, re, subprocess, sys, time
import urllib.error, urllib.parse, urllib.request
from zoneinfo import ZoneInfo

OP = 'hotel-match-common4-saved-tour-detail180-1971-20260919-v1'
DAY = '2026-09-19'
LIMIT = 3000
KNOWN_FLOOR = 800
BASE = 'https://api.tourvisor.ru/search/api/v1'
BODY_LIMIT = 8 * 1024 * 1024
COMMON4 = {13: ('anex','operator_5'), 25: ('funsun','operator_315'), 18: ('biblio_globus','operator_115'), 43: ('intourist','operator_342')}
SENSITIVE_KEY = re.compile(r'(?:access[_-]?token|oauth|refresh[_-]?token|password|authorization|secret|session|cookie)', re.I)


def encode(x): return (json.dumps(x, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode()
def save_new(path, obj):
    raw = encode(obj); path = pathlib.Path(path); path.parent.mkdir(parents=True, exist_ok=True)
    with open(path, 'xb') as f:
        if f.write(raw) != len(raw): raise RuntimeError('short_write')
        f.flush(); os.fsync(f.fileno())
    fd = os.open(str(path.parent), os.O_DIRECTORY)
    try: os.fsync(fd)
    finally: os.close(fd)
    return hashlib.sha256(raw).hexdigest()
def read_locked(f):
    f.seek(0); x = json.loads(f.read())
    if not isinstance(x, dict): raise RuntimeError('ledger_shape')
    return x
def write_locked(f, obj):
    raw = encode(obj); f.seek(0); f.truncate(0)
    if f.write(raw) != len(raw): raise RuntimeError('ledger_short_write')
    f.flush(); os.fsync(f.fileno())
def numeric(v):
    s = str(v) if v is not None else ''
    return int(s) if re.fullmatch(r'[1-9][0-9]{0,21}', s) else None
def identity(obj, key):
    if not isinstance(obj, dict): return None
    child = obj.get(key)
    if isinstance(child, dict):
        x = numeric(child.get('id'))
        if x: return x
    return numeric(obj.get(key + 'Id'))
def sanitize(x, token):
    if isinstance(x, dict):
        out = {}
        for k,v in x.items():
            if SENSITIVE_KEY.search(str(k)): out[str(k)] = '<redacted>'
            else: out[str(k)] = sanitize(v, token)
        return out
    if isinstance(x, list): return [sanitize(v, token) for v in x]
    if isinstance(x, str):
        if token and token in x: return '<redacted-token-containing-string>'
        if x.startswith(('http://','https://')):
            try:
                p = urllib.parse.urlsplit(x)
                pairs = urllib.parse.parse_qsl(p.query, keep_blank_values=True)
                if any(SENSITIVE_KEY.search(k) for k,_ in pairs):
                    pairs = [(k, '<redacted>') if SENSITIVE_KEY.search(k) else (k,v) for k,v in pairs]
                    return urllib.parse.urlunsplit((p.scheme,p.netloc,p.path,urllib.parse.urlencode(pairs,doseq=True),p.fragment))
            except Exception: pass
        return x
    return x


def safe_operator_link(detail, operator_id):
    u = detail.get('operatorLink') if isinstance(detail, dict) else None
    if not isinstance(u, str) or not u or len(u) > 8192: return {'link_state':'missing'}
    try: p = urllib.parse.urlsplit(u); port = p.port
    except ValueError: return {'link_state':'malformed','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
    if p.scheme != 'https' or not p.hostname or p.username or p.password or port or p.fragment:
        return {'link_state':'unsafe_origin','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
    pairs = urllib.parse.parse_qsl(p.query, keep_blank_values=True)
    if any(SENSITIVE_KEY.search(k) for k,_ in pairs):
        return {'link_state':'sensitive_query','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest(),'host':p.hostname,'path':p.path}
    out = {'link_state':'captured','operator_link':u,'operator_link_sha256':hashlib.sha256(u.encode()).hexdigest(),'host':p.hostname,'path':p.path,'query_keys':sorted({k for k,_ in pairs})}
    if operator_id == 13:
        vals = [v for k,v in pairs if k.upper() == 'HOTELLIST']
        tokens = [z.strip() for v in vals for z in re.split(r'[,;]', v) if z.strip()]
        out['raw_hotellist_values'] = vals
        out['raw_hotellist_tokens'] = tokens
        positives = [int(z) for z in tokens if re.fullmatch(r'[1-9][0-9]{0,8}', z)]
        out['positive_hotellist_ids'] = positives
    return out


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl): return None


class Provider:
    def __init__(self, root, opdir):
        self.home = pathlib.Path(os.environ['HOME']); self.opdir = pathlib.Path(opdir); self.used = 0
        self.dayfile = self.home/'.anytour-match/provider-quotas'/('tourvisor-test-'+DAY+'.json')
        self.opfile = self.dayfile.parent/('tourvisor-'+OP+'.json')
        if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat() != DAY: raise RuntimeError('provider_day_mismatch')
        if not self.dayfile.is_file(): raise RuntimeError('shared_ledger_missing')
        if self.opfile.exists(): raise RuntimeError('operation_ledger_exists_no_replay')
        with open(self.dayfile,'r+b') as f:
            fcntl.flock(f,fcntl.LOCK_EX); d=read_locked(f)
            charged=max(int(d.get('accounted_requests',0)),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
            if charged < KNOWN_FLOOR: raise RuntimeError('ledger_below_known_floor')
            if min(LIMIT,int(d.get('owner_daily_limit',0))) != LIMIT or charged >= LIMIT: raise RuntimeError('daily_budget_guard')
        save_new(self.opfile, {'operation':OP,'provider_day':DAY,'status':'reserved_before_provider_access','used':0,'operation_cap':180})
        code='require $argv[1]; fwrite(STDOUT,(string)(defined("TOURVISOR_JWT")?TOURVISOR_JWT:getenv("TOURVISOR_JWT")));'
        cp=subprocess.run(['php','-r',code,'--',str(pathlib.Path(root)/'config.php')],capture_output=True,check=True)
        self.token=cp.stdout.decode().strip()
        if not self.token or '\n' in self.token or '\r' in self.token: raise RuntimeError('token_invalid')
        self.opener=urllib.request.build_opener(NoRedirect())
    def charge(self, row):
        if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat() != DAY: raise RuntimeError('provider_day_changed')
        with open(self.dayfile,'r+b') as df, open(self.opfile,'r+b') as of:
            fcntl.flock(df,fcntl.LOCK_EX); fcntl.flock(of,fcntl.LOCK_EX)
            d,o=read_locked(df),read_locked(of)
            if o.get('operation') != OP or o.get('status') not in ('reserved_before_provider_access','provider_accessed'): raise RuntimeError('terminal_operation')
            used=int(o.get('used',0)); charged=max(int(d.get('accounted_requests',0)),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
            if min(LIMIT,int(d.get('owner_daily_limit',0))) != LIMIT or charged >= LIMIT or used >= 180: raise RuntimeError('quota_exhausted')
            d['match_new_attempts']=int(d.get('match_new_attempts',0))+1; d['accounted_requests']=charged+1
            d.setdefault('operations',{})[OP]=used+1
            o.update(status='provider_accessed',used=used+1,last_action='tour_detail',last_tour_id=row['t'])
            write_locked(df,d); write_locked(of,o); self.used=used+1
        save_new(self.opdir/f'request-{self.used:03d}.json', {'call':self.used,'accounted_after':charged+1,'action':'tour_detail','tour_id':row['t'],'expected_tv_hotel_id':row['h'],'expected_operator_id':row['o']})
        return charged+1
    def detail(self,row):
        accounted=self.charge(row)
        url=BASE+'/tours/'+urllib.parse.quote(str(row['t']),safe='')+'?currency=RUB'
        req=urllib.request.Request(url,headers={'Authorization':'Bearer '+self.token,'Accept':'application/json'})
        try: resp=self.opener.open(req,timeout=60)
        except urllib.error.HTTPError as exc: resp=exc
        with resp: status=resp.code; raw=resp.read(BODY_LIMIT+1)
        if len(raw)>BODY_LIMIT: raise RuntimeError('body_limit')
        try: data=json.loads(raw)
        except Exception: data=None
        evidence={'http_status':status,'raw_body_sha256':hashlib.sha256(raw).hexdigest(),'accounted_after':accounted}
        if isinstance(data,(dict,list)): evidence['data']=sanitize(data,self.token)
        else: evidence['unparsed_body_sha256']=hashlib.sha256(raw).hexdigest()
        save_new(self.opdir/f'response-{self.used:03d}.json',evidence)
        if status in (401,403,429): raise RuntimeError('http_'+str(status))
        if status not in (200,404): raise RuntimeError('http_'+str(status))
        return status,data,evidence['raw_body_sha256']
    def finish(self,status,result_sha):
        with open(self.opfile,'r+b') as f:
            fcntl.flock(f,fcntl.LOCK_EX); o=read_locked(f); o.update(status=status,result_sha256=result_sha); write_locked(f,o)


def load_cohort(path):
    wrapper=json.loads(pathlib.Path(path).read_text())
    gz=base64.b64decode(wrapper['gzip_base64'],validate=True)
    if hashlib.sha256(gz).hexdigest()!=wrapper['gzip_sha256']: raise RuntimeError('gzip_digest')
    raw=gzip.decompress(gz)
    if hashlib.sha256(raw).hexdigest()!=wrapper['raw_json_sha256']: raise RuntimeError('raw_digest')
    x=json.loads(raw)
    rows=x.get('rows') if isinstance(x,dict) else None
    if not isinstance(rows,list) or len(rows)!=180: raise RuntimeError('cohort_count')
    seen=set(); counts=collections.Counter()
    for r in rows:
        if not isinstance(r,dict): raise RuntimeError('cohort_row')
        if r.get('o') not in COMMON4 or COMMON4[r['o']] != (r.get('n'),r.get('ns')): raise RuntimeError('cohort_operator')
        if numeric(r.get('h')) is None or not re.fullmatch(r'[1-9][0-9]{5,20}',str(r.get('t',''))): raise RuntimeError('cohort_identity')
        if r['t'] in seen: raise RuntimeError('duplicate_tour_id')
        seen.add(r['t']); counts[r['n']]+=1
    if counts != collections.Counter({'biblio_globus':76,'anex':52,'funsun':26,'intourist':26}): raise RuntimeError('cohort_distribution')
    return x,rows


def execute(root,opdir,cohort_path):
    reservation=json.loads((pathlib.Path(opdir)/'reservation.json').read_text())
    if reservation.get('operation')!=OP or reservation.get('state')!='reserved_before_provider_access': raise RuntimeError('reservation_guard')
    cohort,rows=load_cohort(cohort_path)
    provider=None; evidence=[]; state='failed_before_provider_access'; reason=None
    try:
        provider=Provider(root,opdir)
        for idx,row in enumerate(rows,1):
            status,data,response_sha=provider.detail(row)
            ev={'index':idx,'tv_hotel_id':row['h'],'hotel_name':row['name'],'operator_id':row['o'],'operator':row['n'],'target_supplier_namespace':row['ns'],'tour_id':row['t'],'response_sha256':response_sha,'http_status':status,'observation_rows':row['obs']}
            if status==404:
                ev['state']='detail_404'
            elif not isinstance(data,dict):
                ev['state']='detail_shape'
            else:
                hid=identity(data,'hotel'); oid=identity(data,'operator'); tid=str(data.get('id') or data.get('tourId') or '')
                ev.update(returned_tv_hotel_id=hid,returned_operator_id=oid,returned_tour_id=tid)
                if hid!=row['h'] or oid!=row['o'] or tid!=str(row['t']):
                    ev['state']='detail_identity_mismatch'
                else:
                    ev['state']='detail_identity_verified'; ev.update(safe_operator_link(data,row['o']))
            save_new(pathlib.Path(opdir)/f'edge-{idx:03d}.json',ev); evidence.append(ev)
            time.sleep(0.15)
        state='completed_read_only'; reason=None
    except Exception as exc:
        state='terminal_failed_no_replay' if provider is not None and provider.used>0 else 'failed_before_provider_access'
        reason=type(exc).__name__+':'+str(exc) if isinstance(exc,RuntimeError) else type(exc).__name__
    counts=collections.Counter(e.get('state') for e in evidence); link_counts=collections.Counter(e.get('link_state','none') for e in evidence)
    byop={}
    for op in ('anex','funsun','biblio_globus','intourist'):
        es=[e for e in evidence if e['operator']==op]
        byop[op]={'attempted':len(es),'verified':sum(e.get('state')=='detail_identity_verified' for e in es),'captured_links':sum(e.get('link_state')=='captured' for e in es),'not_found':sum(e.get('state')=='detail_404' for e in es),'identity_mismatch':sum(e.get('state')=='detail_identity_mismatch' for e in es)}
    result={'operation':OP,'state':state,'reason':reason,'source_frontier_artifact_id':cohort['source_frontier_artifact_id'],'source_frontier_result_sha256':cohort['source_frontier_result_sha256'],'cohort_rows':len(rows),'attempted':len(evidence),'provider_calls':provider.used if provider else 0,'state_counts':dict(counts),'link_state_counts':dict(link_counts),'by_operator':byop,'verified_edges':[{'tv_hotel_id':e['tv_hotel_id'],'operator_id':e['operator_id'],'operator':e['operator'],'target_supplier_namespace':e['target_supplier_namespace'],'tour_id':e['tour_id'],'link_state':e.get('link_state'),'operator_link':e.get('operator_link'),'positive_hotellist_ids':e.get('positive_hotellist_ids')} for e in evidence if e.get('state')=='detail_identity_verified'],'database_writes':0,'mapping_writes':0,'search_calls':0,'status_calls':0,'results_calls':0,'dates_calls':0,'continue_calls':0,'samo_calls':0,'no_replay':state!='failed_before_provider_access'}
    result_sha=save_new(pathlib.Path(opdir)/'result.json',result)
    save_new(pathlib.Path(opdir)/'receipt.json',{'operation':OP,'state':state,'result_sha256':result_sha,'provider_calls':result['provider_calls'],'database_writes':0,'mapping_writes':0,'no_replay':result['no_replay']})
    if provider: provider.finish(state,result_sha)
    print(json.dumps({'state':state,'reason':reason,'attempted':len(evidence),'provider_calls':result['provider_calls'],'state_counts':dict(counts),'link_state_counts':dict(link_counts),'by_operator':byop,'result_sha256':result_sha},ensure_ascii=False))
    return 0 if state=='completed_read_only' else 2


if __name__=='__main__':
    if '--self-test' in sys.argv:
        assert numeric('13')==13 and numeric('0') is None
        d={'hotel':{'id':123},'operatorId':25}; assert identity(d,'hotel')==123 and identity(d,'operator')==25
        assert safe_operator_link({'operatorLink':'https://example.org/tour?id=1'},25)['link_state']=='captured'
        assert safe_operator_link({'operatorLink':'https://example.org/tour?session=abc'},25)['link_state']=='sensitive_query'
        print('hotel-match-common4-saved-tour-detail180-v1: PASS'); raise SystemExit(0)
    if len(sys.argv)!=4 or sys.argv[1]!='--execute': raise SystemExit('disabled')
    raise SystemExit(execute(sys.argv[2],sys.argv[3],sys.argv[2]+'/'+sys.argv[4] if False else sys.argv[3]+'/payload/cohort.json'))
