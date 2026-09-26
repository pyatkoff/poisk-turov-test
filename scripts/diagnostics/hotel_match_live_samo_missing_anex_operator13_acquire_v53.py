#!/usr/bin/env python3
import base64, collections, datetime as dt, fcntl, hashlib, json, os, pathlib, re, subprocess, sys, time, urllib.error, urllib.parse, urllib.request
from zoneinfo import ZoneInfo

OP='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v53'
V45_OP='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260925-v45'\nV49_OP='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260926-v49'\nV49_RESULT_SHA='ee1f6765456940c6b665a3111cdb8b7f2e3dba2ec70e0d7981fe601e759c203a'\nV49_RECEIPT_SHA='2a7dee34887519bd75eaededbe4df3114228ac6efddd168b550faf5e015aafbe'
V41B_OP='hotel-match-live-samo-missing-anex-operator13-acquire-1971-20260925-v41b'
V41B_RESULT_SHA='a2b3538998c2742096b8655a80af2c5f3f7ea4a8cf3a7c7d81b2d9ec4d5c533e'
V41B_RECEIPT_SHA='d27cf7686650c1c93fca01cd120cdc51e60c311fc314224bfc1a4b959a28a5f4'
V41B_CONSUMED_BATCHES=40
ACCOUNT='TOURVISOR_ANEX_JWT'
ACCOUNT_LEDGER='tourvisor-anex'
DAILY_LIMIT=3000
CALL_CAP=1000
MAX_BATCHES=121
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
    if len(consumed)!=140 or not consumed.issubset(all_sigs):raise RuntimeError('consumed_not_router_subset')
    candidates=[b for b in all_candidates if batch_signature(b['context_key'],b['hotel_ids']) not in consumed]
    if len(candidates)!=121:raise RuntimeError('remaining_router_batch_count')
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
require_once $root.(is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');
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

