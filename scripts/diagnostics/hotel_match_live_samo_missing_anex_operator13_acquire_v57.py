#!/usr/bin/env python3
import base64, collections, datetime as dt, fcntl, hashlib, json, os, pathlib, re, subprocess, sys, time, urllib.error, urllib.parse, urllib.request
from zoneinfo import ZoneInfo

OP='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v57'
V45_OP='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260925-v45'
V49_OP='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v49'
V49_RESULT_SHA='ee1f6765456940c6b665a3111cdb8b7f2e3dba2ec70e0d7981fe601e759c203a'
V49_RECEIPT_SHA='2a7dee34887519bd75eaededbe4df3114228ac6efddd168b550faf5e015aafbe'
V53_OP='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v53'
V53_RESULT_SHA='ef37e3380401bacdfe6ff16edeb445a103ec248594ed5cacafc0071b0fca5ec4'
V53_RECEIPT_SHA='ae64c83c79ff0c4e4002500fc668cc666ba1e5e1b9cc3af665ad575935846701'
V53_CONSUMED_BATCHES=31
V41B_OP='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260925-v41b'
V41B_RESULT_SHA='a2b3538998c2742096b8655a80af2c5f3f7ea4a8cf3a7c7d81b2d9ec4d5c533e'
V41B_RECEIPT_SHA='d27cf7686650c1c93fca01cd120cdc51e60c311fc314224bfc1a4b959a28a5f4'
V41B_CONSUMED_BATCHES=40
ACCOUNT='TOURVISOR_ANEX_JWT'
ACCOUNT_LEDGER='tourvisor-anex'
DAILY_LIMIT=3000
CALL_CAP=1000
MAX_BATCHES=90
BASE='https://api.tourvisor.ru/search/api/v1'
BODY_LIMIT=16*1024*1024
POLICY='owner_exact_and_strong_20260908'
EXPECTED_DAY='2026-09-26'

def enc(v): return (json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':'))+'\n').encode()
def save(path,v):
    p=pathlib.Path(path);p.parent.mkdir(parents=True,exist_ok=True)
    b=enc(v)
    with open(p,'xb') as f:
        if f.write(b)!=len(b): raise RuntimeError('short_write')
        f.flush();os.fsync(f.fileno())
    return hashlib.sha256(b).hexdigest()
def load(path):
    v=json.loads(pathlib.Path(path).read_text())
    if not isinstance(v,dict): raise RuntimeError('json_shape')
    return v
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
    if token and token in raw:return False
    return not re.search(r'"(?:access_token|oauth_token|refresh_token|password|authorization|secret)"\s*:',raw,re.I)
def link_projection(d):
    raw=d.get('operatorLink') if isinstance(d,dict) else None
    if not isinstance(raw,str) or not raw.strip():
        return {'link_state':'missing','positive_native_candidates':[]}
    raw=raw.strip()
    try:p=urllib.parse.urlsplit(raw)
    except ValueError:
        return {'link_state':'invalid','operator_link_sha256':hashlib.sha256(raw.encode()).hexdigest(),'positive_native_candidates':[]}
    host=(p.hostname or '').lower()
    out={'operator_link_sha256':hashlib.sha256(raw.encode()).hexdigest(),'operator_link_host':host}
    if p.scheme.lower()!='https' or not host or p.username or p.password or p.fragment or not(host=='anextour.ru' or host.endswith('.anextour.ru')):
        out.update(link_state='invalid_origin',positive_native_candidates=[]);return out
    pairs=urllib.parse.parse_qsl(p.query,keep_blank_values=True)
    if any(re.search(r'(?:token|jwt|secret|password|auth|access[_-]?token|api[_-]?key|signature|session)',k or '',re.I) for k,_ in pairs):
        out.update(link_state='secret_bearing_link',positive_native_candidates=[]);return out
    vals=[v.strip() for k,v in pairs if urllib.parse.unquote(k).upper()=='HOTELLIST']
    toks=[x.strip() for v in vals for x in re.split(r'[,;]',v) if x.strip()]
    ids=sorted({int(x) for x in toks if re.fullmatch(r'[1-9][0-9]{0,8}',x)})
    out['query_keys']=sorted({urllib.parse.unquote(k) for k,_ in pairs})[:40]
    out['positive_native_candidates']=ids
    out['link_state']='captured_single_native' if len(toks)==len(ids)==1 else ('captured_ambiguous_native' if toks else 'missing_native')
    return out

def context_key(r):
    return '|'.join(str(r[k]) for k in ('departure_id','country_id','departure_date','nights','adults','children_count'))+'|'+str(r.get('child_ages_signature',''))

def batch_signature(context_key,hotel_ids):
    ids=sorted({int(x) for x in hotel_ids if int(x)>0})
    return str(context_key)+'|'+','.join(str(x) for x in ids)

def load_consumed(v41b_dir):
    d=pathlib.Path(v41b_dir)
    if not d.is_dir() or d.is_symlink(): raise RuntimeError('v41b_dir')
    rp=d/'result.json';qp=d/'receipt.json'
    if not rp.is_file() or not qp.is_file() or rp.is_symlink() or qp.is_symlink(): raise RuntimeError('v41b_terminal_files')
    raw=rp.read_bytes();qraw=qp.read_bytes()
    if hashlib.sha256(raw).hexdigest()!=V41B_RESULT_SHA: raise RuntimeError('v41b_result_sha')
    if hashlib.sha256(qraw).hexdigest()!=V41B_RECEIPT_SHA: raise RuntimeError('v41b_receipt_sha')
    r=json.loads(raw);q=json.loads(qraw)
    if r.get('operation')!=V41B_OP or r.get('state')!='completed_read_only': raise RuntimeError('v41b_state')
    if r.get('selected_batch_count')!=40 or r.get('selected_hotel_count')!=300 or r.get('provider_calls')!=177 or r.get('captured_single_native_count')!=16 or r.get('operation_tariff_units')!=40: raise RuntimeError('v41b_counts')
    if q.get('operation')!=V41B_OP or q.get('state')!='completed_read_only' or q.get('result_sha256')!=V41B_RESULT_SHA or q.get('no_replay') is not True: raise RuntimeError('v41b_receipt')
    files=sorted(d.glob('batch-*-reservation.json'))
    if len(files)!=V41B_CONSUMED_BATCHES: raise RuntimeError('v41b_batch_reservation_count')
    consumed=set()
    for p in files:
        if not p.is_file() or p.is_symlink(): raise RuntimeError('v41b_batch_reservation_file')
        x=load(p)
        if x.get('operation')!=V41B_OP or x.get('state')!='reserved_before_batch_http': raise RuntimeError('v41b_batch_reservation_state')
        k=str(x.get('context_key',''));ids=x.get('hotel_ids')
        if not k or not isinstance(ids,list) or not ids: raise RuntimeError('v41b_batch_reservation_shape')
        sig=batch_signature(k,ids)
        if sig in consumed: raise RuntimeError('v41b_batch_reservation_duplicate')
        consumed.add(sig)
    if len(consumed)!=V41B_CONSUMED_BATCHES: raise RuntimeError('v41b_consumed_count')
    return consumed


def load_consumed_v45(v45_dir, expected_result_sha, expected_receipt_sha):
    d=pathlib.Path(v45_dir)
    if not d.is_dir() or d.is_symlink(): raise RuntimeError('v45_dir')
    rp=d/'result.json';qp=d/'receipt.json'
    if not rp.is_file() or not qp.is_file() or rp.is_symlink() or qp.is_symlink(): raise RuntimeError('v45_terminal_files')
    raw=rp.read_bytes();qraw=qp.read_bytes()
    if hashlib.sha256(raw).hexdigest()!=expected_result_sha: raise RuntimeError('v45_result_sha')
    if hashlib.sha256(qraw).hexdigest()!=expected_receipt_sha: raise RuntimeError('v45_receipt_sha')
    r=json.loads(raw);q=json.loads(qraw)
    if r.get('operation')!=V45_OP or r.get('state')!='completed_read_only': raise RuntimeError('v45_state')
    if r.get('selected_batch_count')!=50 or r.get('selected_hotel_count')!=160 or r.get('provider_calls')!=192 or r.get('captured_single_native_count')!=17 or r.get('operation_tariff_units')!=50: raise RuntimeError('v45_counts')
    if q.get('operation')!=V45_OP or q.get('state')!='completed_read_only' or q.get('result_sha256')!=expected_result_sha or q.get('no_replay') is not True: raise RuntimeError('v45_receipt')
    files=sorted(d.glob('batch-*-reservation.json'))
    if len(files)!=50: raise RuntimeError('v45_batch_reservation_count')
    consumed=set()
    for p in files:
        if not p.is_file() or p.is_symlink(): raise RuntimeError('v45_batch_reservation_file')
        x=load(p)
        if x.get('operation')!=V45_OP or x.get('state')!='reserved_before_batch_http': raise RuntimeError('v45_batch_reservation_state')
        k=str(x.get('context_key',''));ids=x.get('hotel_ids')
        if not k or not isinstance(ids,list) or not ids: raise RuntimeError('v45_batch_reservation_shape')
        sig=batch_signature(k,ids)
        if sig in consumed: raise RuntimeError('v45_batch_reservation_duplicate')
        consumed.add(sig)
    if len(consumed)!=50: raise RuntimeError('v45_consumed_count')
    return consumed

def load_consumed_v49(v49_dir, expected_result_sha, expected_receipt_sha):
    d=pathlib.Path(v49_dir)
    if not d.is_dir() or d.is_symlink(): raise RuntimeError('v49_dir')
    rp=d/'result.json';qp=d/'receipt.json'
    if not rp.is_file() or not qp.is_file() or rp.is_symlink() or qp.is_symlink(): raise RuntimeError('v49_terminal_files')
    raw=rp.read_bytes();qraw=qp.read_bytes()
    if hashlib.sha256(raw).hexdigest()!=expected_result_sha: raise RuntimeError('v49_result_sha')
    if hashlib.sha256(qraw).hexdigest()!=expected_receipt_sha: raise RuntimeError('v49_receipt_sha')
    r=json.loads(raw);q=json.loads(qraw)
    if r.get('operation')!=V49_OP or r.get('state')!='completed_read_only': raise RuntimeError('v49_state')
    if r.get('selected_batch_count')!=50 or r.get('selected_hotel_count')!=78 or r.get('provider_calls')!=179 or r.get('captured_single_native_count')!=12 or r.get('operation_tariff_units')!=50: raise RuntimeError('v49_counts')
    if q.get('operation')!=V49_OP or q.get('state')!='completed_read_only' or q.get('result_sha256')!=expected_result_sha or q.get('no_replay') is not True: raise RuntimeError('v49_receipt')
    files=sorted(d.glob('batch-*-reservation.json'))
    if len(files)!=50: raise RuntimeError('v49_batch_reservation_count')
    consumed=set()
    for p in files:
        if not p.is_file() or p.is_symlink(): raise RuntimeError('v49_batch_reservation_file')
        x=load(p)
        if x.get('operation')!=V49_OP or x.get('state')!='reserved_before_batch_http': raise RuntimeError('v49_batch_reservation_state')
        k=str(x.get('context_key',''));ids=x.get('hotel_ids')
        if not k or not isinstance(ids,list) or not ids: raise RuntimeError('v49_batch_reservation_shape')
        sig=batch_signature(k,ids)
        if sig in consumed: raise RuntimeError('v49_batch_reservation_duplicate')
        consumed.add(sig)
    if len(consumed)!=50: raise RuntimeError('v49_consumed_count')
    return consumed

def load_consumed_v53(v53_dir, expected_result_sha, expected_receipt_sha):
    d=pathlib.Path(v53_dir)
    if not d.is_dir() or d.is_symlink(): raise RuntimeError('v53_dir')
    rp=d/'result.json';qp=d/'receipt.json'
    if not rp.is_file() or not qp.is_file() or rp.is_symlink() or qp.is_symlink(): raise RuntimeError('v53_terminal_files')
    raw=rp.read_bytes();qraw=qp.read_bytes()
    if hashlib.sha256(raw).hexdigest()!=expected_result_sha: raise RuntimeError('v53_result_sha')
    if hashlib.sha256(qraw).hexdigest()!=expected_receipt_sha: raise RuntimeError('v53_receipt_sha')
    r=json.loads(raw);q=json.loads(qraw)
    if r.get('operation')!=V53_OP or r.get('state')!='terminal_failed_no_replay': raise RuntimeError('v53_state')
    if r.get('selected_batch_count')!=121 or r.get('selected_hotel_count')!=121 or r.get('provider_calls')!=98 or r.get('captured_single_native_count')!=2 or r.get('operation_tariff_units')!=31 or r.get('completed_batches')!=30: raise RuntimeError('v53_counts')
    if r.get('reason')!='RuntimeError:http_400' or r.get('returned_edge_count')!=3: raise RuntimeError('v53_reason_counts')
    if q.get('operation')!=V53_OP or q.get('state')!='terminal_failed_no_replay' or q.get('result_sha256')!=expected_result_sha or q.get('no_replay') is not True: raise RuntimeError('v53_receipt')
    files=sorted(d.glob('batch-*-reservation.json'))
    if len(files)!=V53_CONSUMED_BATCHES: raise RuntimeError('v53_batch_reservation_count')
    consumed=set()
    for p in files:
        if not p.is_file() or p.is_symlink(): raise RuntimeError('v53_batch_reservation_file')
        x=load(p)
        if x.get('operation')!=V53_OP or x.get('state')!='reserved_before_batch_http': raise RuntimeError('v53_batch_reservation_state')
        k=str(x.get('context_key',''));ids=x.get('hotel_ids')
        if not k or not isinstance(ids,list) or not ids: raise RuntimeError('v53_batch_reservation_shape')
        sig=batch_signature(k,ids)
        if sig in consumed: raise RuntimeError('v53_batch_reservation_duplicate')
        consumed.add(sig)
    if len(consumed)!=V53_CONSUMED_BATCHES: raise RuntimeError('v53_consumed_count')
    return consumed

def build_plan(router,consumed):
    if router.get('operation_id')!='hotel-match-live-samo-missing-direct-anex-router-1971-20260925-v35' or router.get('state')!='completed_read_only':
        raise RuntimeError('router_state')
    if router.get('input_count')!=777 or router.get('routed_future_context_count')!=659 or router.get('no_future_context_count')!=118 or router.get('batch_count')!=261:
        raise RuntimeError('router_counts')
    routed=router.get('routed');bp=router.get('batch_plan')
    if not isinstance(routed,list) or not isinstance(bp,list):raise RuntimeError('router_shape')
    contexts={}
    valid_ids=set()
    for row in routed:
        if not isinstance(row,dict):continue
        hid=int(row.get('tv_hotel_id',0))
        if hid<1:continue
        k=context_key(row);contexts.setdefault(k,row);valid_ids.add(hid)
    if len(valid_ids)!=659:raise RuntimeError('router_membership')
    all_candidates=[];seen=set()
    for b in bp:
        if not isinstance(b,dict):continue
        k=str(b.get('context_key',''));ids=sorted({int(x) for x in b.get('hotel_ids',[]) if int(x)>0})
        if not k or k not in contexts or not ids or len(ids)>30 or not set(ids).issubset(valid_ids):raise RuntimeError('batch_shape')
        if seen.intersection(ids):raise RuntimeError('batch_overlap')
        seen.update(ids)
        all_candidates.append({'context_key':k,'batch_index':int(b.get('batch_index',0)),'hotel_ids':ids,'hotel_count':len(ids),'context':contexts[k]})
    if seen!=valid_ids:raise RuntimeError('batch_membership')
    all_sigs={batch_signature(b['context_key'],b['hotel_ids']) for b in all_candidates}
    if len(all_sigs)!=261:raise RuntimeError('router_batch_signature_count')
    if len(consumed)!=171 or not consumed.issubset(all_sigs):raise RuntimeError('consumed_not_router_subset')
    candidates=[b for b in all_candidates if batch_signature(b['context_key'],b['hotel_ids']) not in consumed]
    if len(candidates)!=90:raise RuntimeError('remaining_router_batch_count')
    candidates.sort(key=lambda x:(-x['hotel_count'],x['context_key'],x['batch_index']))
    selected=[];budget=0
    for b in candidates:
        worst=5+b['hotel_count']
        if len(selected)>=MAX_BATCHES or budget+worst>CALL_CAP:continue
        selected.append(b);budget+=worst
    if not selected or budget>CALL_CAP:raise RuntimeError('selection_empty')
    ids=sorted({h for b in selected for h in b['hotel_ids']})
    return {'selected_batches':selected,'selected_batch_count':len(selected),'selected_hotel_count':len(ids),'selected_hotel_ids':ids,'worst_case_http':budget,'all_routeable':659,'all_batches':261,'consumed_batch_count':len(consumed),'remaining_router_batch_count':len(candidates)}

def current_scope(root,ids):
    payload=base64.b64encode(json.dumps(ids,separators=(',',':')).encode()).decode()
    helper=os.environ.get('MATCH_ANEX_COVERAGE_HELPER','')
    if not helper: raise RuntimeError('current_scope_helper_missing')
    code=r'''
$root=$argv[1];$ids=json_decode(base64_decode($argv[2]),true);$helper=$argv[3];
if(!is_array($ids)||!$ids||!is_file($helper)){fwrite(STDERR,"ids_or_helper\n");exit(2);}
require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
require_once $helper;
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
try{
 $ph=implode(',',array_fill(0,count($ids),'?'));$active=[];$q=$db->prepare("SELECT id,is_active,country_name FROM catalog_hotels WHERE id IN ($ph)");$q->execute($ids);
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$active[(int)$r['id']]=['is_active'=>(int)$r['is_active'],'country_name'=>(string)$r['country_name']];
 $samo=[];$q=$db->prepare("SELECT local_hotel_id,external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($ph)");$q->execute($ids);
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$samo[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];
 $cov=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anex=[];
 foreach($ids as $id){$id=(int)$id;if(isset($cov['by_local'][$id]))$anex[$id]=array_map('strval',array_keys($cov['by_local'][$id]));}
 $db->rollBack();echo json_encode(['active'=>$active,'samo'=>$samo,'anex'=>$anex,'effective_native_count'=>$cov['native_count'],'effective_local_count'=>$cov['local_count']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,get_class($e).':'.$e->getMessage());exit(2);}
'''
    p=subprocess.run(['php','-r',code,'--',str(root),payload,helper],capture_output=True,text=True,timeout=90)
    if p.returncode!=0:raise RuntimeError('current_scope_failed')
    v=json.loads(p.stdout)
    if not isinstance(v,dict):raise RuntimeError('current_scope_shape')
    return v

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):return None

class Provider:
    def __init__(self,root,opdir):
        day=dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()
        if day!=EXPECTED_DAY:raise RuntimeError('provider_day_changed')
        q=pathlib.Path(os.environ['HOME'])/'.anytour-match/provider-quotas';q.mkdir(parents=True,exist_ok=True)
        self.day=q/f'{ACCOUNT_LEDGER}-{day}.json';self.op=q/f'{ACCOUNT_LEDGER}-{OP}.json';self.dir=opdir;self.day_value=day
        self.used=0;self.tariff_used=0;self.last=0;self.counts=collections.Counter()
        if not self.day.exists():
            try:save(self.day,{'provider':'tourvisor-anex','provider_day':day,'owner_daily_limit':DAILY_LIMIT,'tariff_search_units':0,'physical_http_attempts':0,'baseline_status':'separate_match_account','operations':{}})
            except FileExistsError:pass
        with open(self.day,'r+b') as f:
            fcntl.flock(f,fcntl.LOCK_EX);d=json.load(f)
            if d.get('provider_day')!=day:raise RuntimeError('provider_day_ledger_mismatch')
            self.last=int(d.get('tariff_search_units',0))
            if self.last>=DAILY_LIMIT:raise RuntimeError('daily_budget_guard')
        save(self.op,{'operation':OP,'status':'reserved_before_provider_access','used':0,'tariff_used':0,'operation_cap':CALL_CAP,'provider_day':day,'account':ACCOUNT})
        save(opdir/'quota-reservation.json',{'operation':OP,'tariff_units_before_local_ledger':self.last,'cap':CALL_CAP,'limit':DAILY_LIMIT,'provider_day':day,'account':ACCOUNT})
        php='require $argv[1];$a=defined("TOURVISOR_ANEX_JWT")?TOURVISOR_ANEX_JWT:getenv("TOURVISOR_ANEX_JWT");$b=defined("TOURVISOR_JWT")?TOURVISOR_JWT:getenv("TOURVISOR_JWT");if(!$a||$a===$b){fwrite(STDERR,"tourvisor_account_guard");exit(42);}fwrite(STDOUT,(string)$a);'
        cp=subprocess.run(['php','-r',php,'--',str(root/'config.php')],capture_output=True,timeout=20)
        if cp.returncode!=0:raise RuntimeError('tourvisor_anex_account_guard')
        self.token=cp.stdout.decode().strip()
        if not self.token or '\n' in self.token:raise RuntimeError('tourvisor_anex_token_invalid')
        self.open=urllib.request.build_opener(NoRedirect())
    def _read(self,f):f.seek(0);v=json.load(f);return v
    def _write(self,f,v):
        b=enc(v);f.seek(0);f.truncate(0);f.write(b);f.flush();os.fsync(f.fileno())
    def call(self,action,path,params):
        day=dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()
        if day!=self.day_value:raise RuntimeError('provider_day_changed')
        with open(self.day,'r+b') as df,open(self.op,'r+b') as of:
            fcntl.flock(df,fcntl.LOCK_EX);fcntl.flock(of,fcntl.LOCK_EX)
            d,o=self._read(df),self._read(of);used=int(o.get('used',0));tariff_used=int(o.get('tariff_used',0))
            charged=int(d.get('tariff_search_units',0));tariff_charge=1 if action in ('search_start','search_continue','flights_actualization') else 0
            if charged+tariff_charge>DAILY_LIMIT or used>=CALL_CAP:raise RuntimeError('quota_exhausted')
            d['physical_http_attempts']=int(d.get('physical_http_attempts',0))+1;d['tariff_search_units']=charged+tariff_charge
            d.setdefault('operations',{})[OP]={'physical_http_attempts':used+1,'tariff_search_units':tariff_used+tariff_charge}
            o.update(status='provider_accessed',used=used+1,tariff_used=tariff_used+tariff_charge,last_action=action)
            self._write(df,d);self._write(of,o);self.used=used+1;self.tariff_used=tariff_used+tariff_charge;self.last=charged+tariff_charge
        self.counts[action]+=1
        save(self.dir/f'request-{self.used:04d}.json',{'call':self.used,'action':action,'path':path,'params':params,'tariff_units_local_ledger':self.last})
        pairs=[(k,str(z).lower() if isinstance(z,bool) else str(z)) for k,v in params.items() for z in (v if isinstance(v,list) else [v])]
        url=BASE+path+('?' + urllib.parse.urlencode(pairs) if pairs else '')
        req=urllib.request.Request(url,headers={'Authorization':'Bearer '+self.token,'Accept':'application/json'})
        try:r=self.open.open(req,timeout=55)
        except urllib.error.HTTPError as e:r=e
        with r:code=r.code;raw=r.read(BODY_LIMIT+1)
        if len(raw)>BODY_LIMIT:raise RuntimeError('body_limit')
        try:data=json.loads(raw)
        except Exception:data={'unparsed_body_sha256':hashlib.sha256(raw).hexdigest()}
        if not safe_payload(data,self.token):
            save(self.dir/f'response-{self.used:04d}.json',{'http_status':code,'withheld_sensitive_body_sha256':hashlib.sha256(raw).hexdigest()});raise RuntimeError('sensitive_response')
        save(self.dir/f'response-{self.used:04d}.json',{'http_status':code,'raw_sha256':hashlib.sha256(raw).hexdigest(),'data':data})
        if code==429:raise RuntimeError('quota_http_429')
        if code in (401,403):raise RuntimeError('auth_http_'+str(code))
        if code!=200 and not(action=='tour_detail' and code==404):raise RuntimeError('http_'+str(code))
        return code,data
    def finish(self,state,digest):
        with open(self.op,'r+b') as f:
            fcntl.flock(f,fcntl.LOCK_EX);o=self._read(f);o.update(status=state,result_sha256=digest);self._write(f,o)

def run_batch(provider,opdir,index,b):
    c=b['context'];ages=[int(x) for x in str(c.get('child_ages_signature','')).split(',') if re.fullmatch(r'[0-9]{1,2}',x or '')]
    params={'departureId':int(c['departure_id']),'countryId':int(c['country_id']),'dateFrom':str(c['departure_date']),'dateTo':str(c['departure_date']),
            'nightsFrom':int(c['nights']),'nightsTo':int(c['nights']),'adults':int(c['adults']),'childs':ages,'currency':'RUB','onlyCharter':False,
            'operatorIds':[13],'hotelIds':b['hotel_ids']}
    _,st=provider.call('search_start','/tours/search',params);st=unwrap(st)
    sid=num((st or {}).get('searchId') if isinstance(st,dict) else None) or num((st or {}).get('id') if isinstance(st,dict) else None)
    if not sid:raise RuntimeError('search_id_missing')
    complete=False
    for delay in (1,2,4):
        time.sleep(delay);_,status=provider.call('search_status',f'/tours/search/{sid}/status',{'operatorStatus':False})
        if ready(status):complete=True;break
    _,res=provider.call('search_results',f'/tours/search/{sid}',{'limit':10000})
    wanted=set(b['hotel_ids']);edges=[];returned=set()
    for h in hotel_rows(res):
        if not isinstance(h,dict):continue
        hid=num(h.get('id'))
        if hid not in wanted or hid in returned:continue
        returned.add(hid)
        tours=[t for t in (h.get('tours') or []) if isinstance(t,dict) and ident(t,'operator')==13 and num(t.get('id') or t.get('tourId'))]
        edge={'tv_hotel_id':hid,'operator_id':13,'namespace':'anex','batch':index,'search_id_sha256':hashlib.sha256(str(sid).encode()).hexdigest(),'safe_to_write_now':False}
        if not tours:
            edge['state']='returned_no_anex_tour'
        else:
            tid=str(tours[0].get('id') or tours[0].get('tourId'));code,d=provider.call('tour_detail','/tours/'+urllib.parse.quote(tid,safe=''),{'currency':'RUB'});d=unwrap(d)
            edge['tour_id_sha256']=hashlib.sha256(tid.encode()).hexdigest();edge['tour_detail_http']=code
            if code==404:edge['state']='detail_404'
            elif not isinstance(d,dict) or ident(d,'hotel')!=hid or ident(d,'operator')!=13 or str(d.get('id') or d.get('tourId') or '')!=tid:edge['state']='detail_identity_mismatch'
            else:
                edge['state']='detail_identity_verified';edge.update(link_projection(d))
        save(opdir/f'edge-{hid}.json',edge);edges.append(edge)
    return sid,complete,edges

def execute(root,opdir,router_path,v41b_dir,v45_dir,v45_result_sha,v45_receipt_sha,v49_dir,v49_result_sha,v49_receipt_sha,v53_dir,v53_result_sha,v53_receipt_sha,source_sha):
    router=load(router_path)
    consumed=load_consumed(v41b_dir) | load_consumed_v45(v45_dir,v45_result_sha,v45_receipt_sha) | load_consumed_v49(v49_dir,v49_result_sha,v49_receipt_sha) | load_consumed_v53(v53_dir,v53_result_sha,v53_receipt_sha)
    plan=build_plan(router,consumed)
    current=current_scope(root,plan['selected_hotel_ids'])
    active=[];skipped_anex=[];skipped_samo=[];skipped_inactive=[]
    for hid in plan['selected_hotel_ids']:
        h=(current.get('active') or {}).get(str(hid)) or (current.get('active') or {}).get(hid)
        country=str((h or {}).get('country_name','')).strip().lower()
        if not h or int((h or {}).get('is_active',0))!=1 or country in ('россия','абхазия','russia','russian federation','abkhazia'):
            skipped_inactive.append(hid);continue
        if str(hid) in (current.get('anex') or {}) or hid in (current.get('anex') or {}):
            skipped_anex.append(hid);continue
        if not ((current.get('samo') or {}).get(str(hid)) or (current.get('samo') or {}).get(hid)):
            skipped_samo.append(hid);continue
        active.append(hid)
    active_set=set(active)
    batches=[]
    for b in plan['selected_batches']:
        ids=[x for x in b['hotel_ids'] if x in active_set]
        if ids:batches.append(dict(b,hotel_ids=ids,hotel_count=len(ids)))
    scope={'operation':OP,'source_sha':source_sha,'router_operation':router['operation_id'],'selected_batch_count':len(batches),'selected_hotel_count':len(active),
           'selected_hotel_ids_sha256':hashlib.sha256(json.dumps(sorted(active),separators=(',',':')).encode()).hexdigest(),
           'worst_case_http':sum(5+len(b['hotel_ids']) for b in batches),'skipped_current_anex':skipped_anex,'skipped_current_samo':skipped_samo,'skipped_inactive':skipped_inactive,
           'tourvisor_account':ACCOUNT,'consumed_batch_count':plan['consumed_batch_count'],'remaining_router_batch_count':plan['remaining_router_batch_count'],'safe_to_write_now':False}
    save(opdir/'current-scope.json',scope)
    if not batches:
        out={'operation':OP,'state':'nothing_current_missing','source_sha':source_sha,'tourvisor_account':ACCOUNT,'provider_calls':0,'selected_hotel_count':0,
             'skipped_current_anex_count':len(skipped_anex),'skipped_current_samo_count':len(skipped_samo),'skipped_inactive_count':len(skipped_inactive),
             'database_writes':0,'mapping_writes':0,'continue_calls':0,'dates_calls':0,'safe_to_write_now':False}
        dig=save(opdir/'result.json',out);save(opdir/'receipt.json',{'operation':OP,'state':out['state'],'result_sha256':dig,'provider_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':False});return out
    provider=None;done=[];state='failed_before_provider_access';reason=None
    try:
        provider=Provider(root,opdir)
        for i,b in enumerate(batches,1):
            save(opdir/f'batch-{i:03d}-reservation.json',{'operation':OP,'batch':i,'context_key':b['context_key'],'hotel_ids':b['hotel_ids'],'state':'reserved_before_batch_http'})
            before=provider.used;sid,complete,edges=run_batch(provider,opdir,i,b)
            rec={'batch':i,'context_key':b['context_key'],'sent':len(b['hotel_ids']),'returned_targets':len(edges),'search_complete':complete,'search_id_sha256':hashlib.sha256(str(sid).encode()).hexdigest(),'calls':provider.used-before}
            save(opdir/f'batch-{i:03d}-result.json',rec);done.append(rec)
        state='completed_read_only'
    except Exception as e:
        reason=(type(e).__name__+':'+str(e))[:180]
        if 'provider_day_changed' in reason:state='terminal_day_changed_no_replay'
        elif 'quota' in reason or 'daily_budget' in reason:state='terminal_quota_stop_no_replay'
        else:state='terminal_failed_no_replay' if provider and provider.used else 'failed_before_provider_access'
    edges=[load(p) for p in sorted(opdir.glob('edge-*.json'))]
    states=collections.Counter(x.get('state','unknown') for x in edges);links=collections.Counter(x.get('link_state','none') for x in edges)
    captured=sum(1 for x in edges if x.get('state')=='detail_identity_verified' and x.get('link_state')=='captured_single_native' and len(x.get('positive_native_candidates') or [])==1)
    out={'operation':OP,'state':state,'reason':reason,'source_sha':source_sha,'router_operation':router['operation_id'],'tourvisor_account':ACCOUNT,
         'router_input_count':777,'router_routeable_count':659,'consumed_batch_count':plan['consumed_batch_count'],'remaining_router_batch_count':plan['remaining_router_batch_count'],'selected_batch_count':len(batches),'selected_hotel_count':len(active),'selected_worst_case_http':scope['worst_case_http'],
         'completed_batches':len(done),'returned_edge_count':len(edges),'captured_single_native_count':captured,
         'provider_calls':provider.used if provider else 0,'physical_http_attempts':provider.used if provider else 0,'daily_tariff_units_after_local_ledger':provider.last if provider else None,'operation_tariff_units':provider.tariff_used if provider else 0,
         'call_counts':dict(provider.counts) if provider else {},'edge_state_counts':dict(states),'link_state_counts':dict(links),'edges':edges,'batches':done,
         'skipped_current_anex_count':len(skipped_anex),'skipped_current_samo_count':len(skipped_samo),'skipped_inactive_count':len(skipped_inactive),
         'continue_calls':0,'dates_calls':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False}
    dig=save(opdir/'result.json',out);save(opdir/'receipt.json',{'operation':OP,'state':state,'result_sha256':dig,'provider_calls':out['provider_calls'],'tourvisor_account':ACCOUNT,'database_writes':0,'mapping_writes':0,'no_replay':bool(provider and provider.used)})
    if provider:provider.finish(state,dig)
    return out

def selftest():
    assert batch_signature('ctx',[3,1,2])=='ctx|1,2,3'
    r={'operation_id':'hotel-match-live-samo-missing-direct-anex-router-1971-20260925-v35','state':'completed_read_only','input_count':777,'routed_future_context_count':659,'no_future_context_count':118,'batch_count':261,
       'routed':[{'tv_hotel_id':1,'departure_id':1,'country_id':4,'departure_date':'2026-10-01','nights':7,'adults':2,'children_count':0,'child_ages_signature':'' }],
       'batch_plan':[{'context_key':'1|4|2026-10-01|7|2|0|','batch_index':1,'hotel_ids':[1],'hotel_count':1}]}
    try:build_plan(r,set())
    except RuntimeError as e:
        assert str(e)=='router_membership'
    assert link_projection({'operatorLink':'https://agent.anextour.ru/x?HOTELLIST=35371'})['positive_native_candidates']==[35371]
    assert link_projection({'operatorLink':'https://online.anextour.ru/x?HOTELLIST=35,36'})['link_state']=='captured_ambiguous_native'
    assert callable(run_batch) and callable(execute) and Provider is not None
    print('MATCH_V57_SELFTEST_OK')

if __name__=='__main__':
    if '--self-test' in sys.argv:selftest();sys.exit(0)
    if '--execute' not in sys.argv:raise SystemExit('disabled')
    root=pathlib.Path(os.environ['ANYTOUR_ROOT']);opdir=pathlib.Path(os.environ['MATCH_OPERATION_DIR']);router=pathlib.Path(os.environ['MATCH_ROUTER_RESULT']);v41b=pathlib.Path(os.environ['MATCH_V41B_DIR']);v45=pathlib.Path(os.environ['MATCH_V45_DIR']);v45_result_sha=os.environ['MATCH_V45_RESULT_SHA'];v45_receipt_sha=os.environ['MATCH_V45_RECEIPT_SHA'];v49=pathlib.Path(os.environ['MATCH_V49_DIR']);v49_result_sha=os.environ['MATCH_V49_RESULT_SHA'];v49_receipt_sha=os.environ['MATCH_V49_RECEIPT_SHA'];v53=pathlib.Path(os.environ['MATCH_V53_DIR']);v53_result_sha=os.environ['MATCH_V53_RESULT_SHA'];v53_receipt_sha=os.environ['MATCH_V53_RECEIPT_SHA'];source=os.environ['MATCH_SOURCE_SHA']
    if opdir.name!=OP or not root.is_dir() or not router.is_file() or not v41b.is_dir() or not v45.is_dir() or not v49.is_dir() or not v53.is_dir() or not re.fullmatch(r'[0-9a-f]{64}',v45_result_sha) or not re.fullmatch(r'[0-9a-f]{64}',v45_receipt_sha) or not re.fullmatch(r'[0-9a-f]{64}',v49_result_sha) or not re.fullmatch(r'[0-9a-f]{64}',v49_receipt_sha) or not re.fullmatch(r'[0-9a-f]{64}',v53_result_sha) or not re.fullmatch(r'[0-9a-f]{64}',v53_receipt_sha) or not re.fullmatch(r'[0-9a-f]{40}',source):raise SystemExit('runtime_scope')
    reservation=load(opdir/'reservation.json')
    if reservation.get('operation')!=OP or reservation.get('state')!='reserved_before_provider_access':raise SystemExit('reservation')
    out=execute(root,opdir,router,v41b,v45,v45_result_sha,v45_receipt_sha,v49,v49_result_sha,v49_receipt_sha,v53,v53_result_sha,v53_receipt_sha,source)
    print(json.dumps({k:out.get(k) for k in ('state','reason','selected_batch_count','selected_hotel_count','completed_batches','returned_edge_count','captured_single_native_count','provider_calls','daily_tariff_units_after_local_ledger','consumed_batch_count','remaining_router_batch_count','call_counts','edge_state_counts','link_state_counts','tourvisor_account')},ensure_ascii=False,sort_keys=True))
    sys.exit(0 if out['state'] in ('completed_read_only','terminal_quota_stop_no_replay','terminal_day_changed_no_replay','terminal_failed_no_replay','nothing_current_missing') else 2)
