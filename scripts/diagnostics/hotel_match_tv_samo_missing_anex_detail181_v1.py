#!/usr/bin/env python3
import collections,datetime as dt,fcntl,hashlib,json,os,pathlib,re,subprocess,sys,urllib.error,urllib.parse,urllib.request
from zoneinfo import ZoneInfo

OP='hotel-match-tv-samo-missing-anex-detail181-1971-20260920-v1'
DAY='2026-09-20';LIMIT=3000;CALL_CAP=181
BASE='https://api.tourvisor.ru/search/api/v1';BODY_LIMIT=16*1024*1024
ANEX_HOST='agent.anextour.ru';TARGET_NS='operator_5'

def enc(x): return (json.dumps(x,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
def save(path,obj):
 p=pathlib.Path(path);p.parent.mkdir(parents=True,exist_ok=True);b=enc(obj)
 with open(p,'xb') as f:
  if f.write(b)!=len(b): raise RuntimeError('short_write')
  f.flush();os.fsync(f.fileno())
 return hashlib.sha256(b).hexdigest()
def read_locked(f):
 f.seek(0);x=json.loads(f.read())
 if not isinstance(x,dict): raise RuntimeError('ledger_shape')
 return x
def write_locked(f,x):
 b=enc(x);f.seek(0);f.truncate(0)
 if f.write(b)!=len(b): raise RuntimeError('short_ledger_write')
 f.flush();os.fsync(f.fileno())
def num(v):
 s=str(v);return int(s) if re.fullmatch(r'[1-9][0-9]{0,21}',s) else None
def identity(x,key):
 if not isinstance(x,dict): return None
 c=x.get(key)
 return num(c.get('id')) if isinstance(c,dict) else num(x.get(key+'Id'))
def safe_body(x,token):
 raw=json.dumps(x,ensure_ascii=False)
 return not(token and token in raw) and not re.search(r'"(?:access_token|oauth_token|refresh_token|password|authorization|secret)"\s*:',raw,re.I) and not re.search(r'[?&](?:access_token|oauth_token|token|password|auth|secret|session)=',raw,re.I)
def parse_anex_link(d):
 u=d.get('operatorLink') if isinstance(d,dict) else None
 if not isinstance(u,str) or not u or len(u)>8192:return {'link_state':'missing','reason':'operator_link_missing'}
 try:p=urllib.parse.urlsplit(u);port=p.port
 except ValueError:return {'link_state':'invalid','reason':'operator_link_malformed'}
 if p.scheme!='https' or p.hostname!=ANEX_HOST or p.username or p.password or port or p.fragment:
  return {'link_state':'invalid','reason':'operator_link_origin','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
 pairs=urllib.parse.parse_qsl(p.query,keep_blank_values=True)
 if any(re.search(r'token|password|auth|secret|session',k,re.I) for k,_ in pairs):
  return {'link_state':'invalid','reason':'sensitive_operator_link','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
 vals=[v for k,v in pairs if k.upper()=='HOTELLIST'];tokens=[x.strip() for v in vals for x in re.split('[,;]',v) if x.strip()]
 pos=[int(x) for x in tokens if re.fullmatch(r'[1-9][0-9]{0,8}',x)]
 return {'operator_link':u,'operator_link_sha256':hashlib.sha256(u.encode()).hexdigest(),'query_keys':[k for k,_ in pairs],'raw_identity_key':'HOTELLIST','raw_identity_values':vals,'raw_identity_tokens':tokens,'positive_native_candidates':pos,'link_state':('captured_single_native' if len(pos)==len(tokens)==1 else 'captured_ambiguous_native')}

class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,headers,newurl): return None

class Provider:
 def __init__(self,root,opdir):
  self.home=pathlib.Path(os.environ['HOME']);self.dir=opdir;self.used=0;self.last=None;self.counts=collections.Counter()
  self.dayfile=self.home/'.anytour-match/provider-quotas'/('tourvisor-test-'+DAY+'.json')
  self.opfile=self.dayfile.parent/('tourvisor-'+OP+'.json')
  if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY: raise RuntimeError('provider_day_mismatch')
  if not self.dayfile.is_file(): raise RuntimeError('day_ledger_missing')
  with open(self.dayfile,'r+b') as f:
   fcntl.flock(f,fcntl.LOCK_EX);d=read_locked(f)
   owner_limit=int(d.get('owner_daily_limit',0));
   if owner_limit<=0 or owner_limit>LIMIT: raise RuntimeError('owner_limit_invalid')
   accounted=max(int(d.get('accounted_requests',0)),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
   if accounted<0 or accounted>=owner_limit: raise RuntimeError('daily_budget_guard')
   self.last=accounted;self.owner_limit=owner_limit;self.allowed=min(CALL_CAP,owner_limit-accounted)
  if self.opfile.exists(): raise RuntimeError('operation_ledger_exists_no_replay')
  save(self.opfile,{'operation':OP,'status':'reserved_before_provider_access','operation_cap':self.allowed,'requested_input_cap':CALL_CAP,'used':0,'provider_day':DAY})
  save(self.dir/'quota-reservation.json',{'operation':OP,'accounted_before':self.last,'operation_cap':self.allowed,'requested_input_cap':CALL_CAP,'owner_daily_limit':self.owner_limit,'provider_day':DAY})
  cp=subprocess.run(['php','-r','require $argv[1]; fwrite(STDOUT,(string)(defined("TOURVISOR_JWT")?TOURVISOR_JWT:getenv("TOURVISOR_JWT")));','--',str(root/'config.php')],capture_output=True,check=True)
  self.token=cp.stdout.decode().strip()
  if not self.token or '\n' in self.token: raise RuntimeError('token_invalid')
  self.opener=urllib.request.build_opener(NoRedirect())
 def charge(self,path,params):
  with open(self.dayfile,'r+b') as df,open(self.opfile,'r+b') as of:
   fcntl.flock(df,fcntl.LOCK_EX);fcntl.flock(of,fcntl.LOCK_EX);d,o=read_locked(df),read_locked(of)
   used=int(o['used']);owner_limit=min(LIMIT,int(d.get('owner_daily_limit',0)))
   charged=max(int(d.get('accounted_requests',0)),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
   if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY or charged>=owner_limit or used>=int(o['operation_cap']): raise RuntimeError('quota_exhausted')
   d['match_new_attempts']=int(d.get('match_new_attempts',0))+1;d['accounted_requests']=charged+1;d.setdefault('operations',{})[OP]=used+1
   o.update(status='provider_accessed',used=used+1,last_action='tour_detail')
   write_locked(df,d);write_locked(of,o);self.used=used+1;self.last=charged+1
  self.counts['tour_detail']+=1;save(self.dir/f'request-{self.used:03d}.json',{'call':self.used,'accounted':self.last,'action':'tour_detail','path':path,'params':params})
 def detail(self,tid):
  path='/tours/'+urllib.parse.quote(str(tid),safe='');params={'currency':'RUB'};self.charge(path,params)
  req=urllib.request.Request(BASE+path+'?currency=RUB',headers={'Authorization':'Bearer '+self.token,'Accept':'application/json'})
  try:r=self.opener.open(req,timeout=65)
  except urllib.error.HTTPError as e:r=e
  with r:code=r.code;raw=r.read(BODY_LIMIT+1)
  if len(raw)>BODY_LIMIT: raise RuntimeError('body_limit')
  try:data=json.loads(raw)
  except Exception:data={'unparsed_body_sha256':hashlib.sha256(raw).hexdigest()}
  if not safe_body(data,self.token):
   save(self.dir/f'response-{self.used:03d}.json',{'http_status':code,'withheld_sensitive_body_sha256':hashlib.sha256(raw).hexdigest()});raise RuntimeError('sensitive_response')
  save(self.dir/f'response-{self.used:03d}.json',{'http_status':code,'raw_sha256':hashlib.sha256(raw).hexdigest(),'data':data})
  if code in (401,403,429): raise RuntimeError('http_'+str(code))
  if code not in (200,404): raise RuntimeError('http_'+str(code))
  return code,data
 def finish(self,status,digest):
  with open(self.opfile,'r+b') as f:
   fcntl.flock(f,fcntl.LOCK_EX);o=read_locked(f);o.update(status=status,result_sha256=digest);write_locked(f,o)

def execute(root,opdir,inputp):
 res=json.loads((opdir/'reservation.json').read_text());inp=json.loads(inputp.read_text())
 if res.get('operation')!=OP or inp.get('input_count')!=181 or inp.get('source_result_sha256')!='8644e09a8301b3a98aced075365c0ce89488d2e09adab3d43796f3b4ced0c968': raise RuntimeError('input_guard')
 rows=inp.get('rows');
 if not isinstance(rows,list) or len(rows)!=181: raise RuntimeError('rows_guard')
 seen=set()
 for x in rows:
  hid=int(x['tv_hotel_id']);tid=str(x['tour_id']);k=(hid,tid)
  if hid<=0 or not tid or k in seen or int(x.get('operator_id',0))!=13: raise RuntimeError('row_guard')
  if not x.get('samo_external_ids'): raise RuntimeError('samo_identity_guard')
  seen.add(k)
 provider=None;edges=[];state='failed_before_provider_access';reason=None
 try:
  provider=Provider(root,opdir);allowed=provider.allowed
  for i,x in enumerate(rows,1):
   if i>allowed:
    edges.append({'index':i,'tv_hotel_id':int(x['tv_hotel_id']),'hotel_name':x['hotel_name'],'operator_id':13,'operator':'anex','target_supplier_namespace':TARGET_NS,'tour_id':str(x['tour_id']),'state':'not_attempted_quota','safe_to_write_now':False});continue
   hid=int(x['tv_hotel_id']);tid=str(x['tour_id'])
   e={'index':i,'tv_hotel_id':hid,'hotel_name':x['hotel_name'],'operator_id':13,'operator':'anex','target_supplier_namespace':TARGET_NS,'tour_id':tid,'retained_search_id':x.get('search_id'),'retained_date':x.get('departure_date'),'retained_nights':x.get('nights'),'samo_external_ids':x.get('samo_external_ids'),'safe_to_write_now':False}
   try:code,d=provider.detail(tid)
   except Exception as ex:
    e['state']='provider_error';e['reason']=type(ex).__name__+':'+str(ex) if isinstance(ex,RuntimeError) else type(ex).__name__;save(opdir/f'edge-{i:03d}.json',e);edges.append(e);reason=e['reason'];state='terminal_failed_no_replay';break
   if code==404:e['state']='stale_detail_404'
   elif not isinstance(d,dict):e['state']='detail_shape'
   else:
    rh=identity(d,'hotel');ro=identity(d,'operator');rt=str(d.get('id') or d.get('tourId') or '')
    e.update(returned_tv_hotel_id=rh,returned_operator_id=ro,returned_tour_id=rt)
    if rh!=hid or ro!=13 or rt!=tid:e['state']='detail_identity_mismatch'
    else:e['state']='detail_identity_verified';e.update(parse_anex_link(d))
   save(opdir/f'edge-{i:03d}.json',e);edges.append(e)
  if state!='terminal_failed_no_replay': state='completed_read_only'
 except Exception as ex:
  reason=type(ex).__name__+':'+str(ex) if isinstance(ex,RuntimeError) else type(ex).__name__
  state='terminal_failed_no_replay' if provider and provider.used else 'failed_before_provider_access'
 if len(edges)<len(rows):
  for j,x in enumerate(rows[len(edges):],len(edges)+1):edges.append({'index':j,'tv_hotel_id':int(x['tv_hotel_id']),'hotel_name':x['hotel_name'],'operator_id':13,'operator':'anex','target_supplier_namespace':TARGET_NS,'tour_id':str(x['tour_id']),'state':'not_attempted_after_failure','safe_to_write_now':False})
 counts=collections.Counter(e['state'] for e in edges);links=collections.Counter(e.get('link_state','none') for e in edges)
 out={'operation':OP,'state':state,'reason':reason,'source_result_sha256':inp['source_result_sha256'],'input_count':181,'attempted':provider.used if provider else 0,'provider_calls':provider.used if provider else 0,'daily_accounted_after':provider.last if provider else None,'operation_cap':provider.allowed if provider else 0,'call_counts':dict(provider.counts) if provider else {},'state_counts':dict(counts),'link_state_counts':dict(links),'edges':edges,'search_calls':0,'status_calls':0,'results_calls':0,'dates_calls':0,'continue_calls':0,'samo_calls':0,'andromeda_calls':0,'direct_anex_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':bool(provider and provider.used)}
 dig=save(opdir/'result.json',out);save(opdir/'receipt.json',{'operation':OP,'state':state,'result_sha256':dig,'provider_calls':out['provider_calls'],'daily_accounted_after':out['daily_accounted_after'],'database_writes':0,'mapping_writes':0,'no_replay':out['no_replay']})
 if provider: provider.finish(state,dig)
 print(json.dumps({'state':state,'reason':reason,'attempted':out['attempted'],'calls':out['provider_calls'],'daily':out['daily_accounted_after'],'cap':out['operation_cap'],'states':dict(counts),'links':dict(links),'result_sha256':dig},ensure_ascii=False))
 return 0 if state=='completed_read_only' else 2

def selftest():
 x=parse_anex_link({'operatorLink':'https://agent.anextour.ru/x?HOTELLIST=35371'});assert x['link_state']=='captured_single_native' and x['positive_native_candidates']==[35371]
 y=parse_anex_link({'operatorLink':'https://agent.anextour.ru/x?HOTELLIST=12,13'});assert y['link_state']=='captured_ambiguous_native'
 assert parse_anex_link({'operatorLink':'https://example.com/x?HOTELLIST=12'})['link_state']=='invalid'
 print('TV_SAMO_MISSING_ANEX_DETAIL181_SELFTEST_OK')

if __name__=='__main__':
 if '--self-test' in sys.argv:selftest()
 elif '--execute' in sys.argv:sys.exit(execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_INPUT_PATH'])))
 else:raise SystemExit('disabled')
