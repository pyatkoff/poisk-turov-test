#!/usr/bin/env python3
import collections,datetime as dt,fcntl,hashlib,json,os,pathlib,re,subprocess,sys,urllib.error,urllib.parse,urllib.request
from zoneinfo import ZoneInfo
OP='hotel-match-residual2041-common4-nonanex-detail-1971-20260921-v2';DAY='2026-09-21';LIMIT=3000;CALL_CAP=1800
BASE='https://api.tourvisor.ru/search/api/v1';BODY_LIMIT=16*1024*1024
ROUTES={18:('biblio_globus','operator_115',{'www.bgoperator.ru','bgoperator.ru'}),25:('funsun','operator_315',{'b2b.fstravel.com','newb2b.fstravel.com'}),43:('intourist','operator_342',{'searchtour.intourist.ru'})}
def enc(x):return (json.dumps(x,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
def save(p,x):
 p=pathlib.Path(p);p.parent.mkdir(parents=True,exist_ok=True);b=enc(x)
 with open(p,'xb') as f:
  if f.write(b)!=len(b):raise RuntimeError('short_write')
  f.flush();os.fsync(f.fileno())
 return hashlib.sha256(b).hexdigest()
def read_locked(f):
 f.seek(0);x=json.loads(f.read())
 if not isinstance(x,dict):raise RuntimeError('ledger_shape')
 return x
def write_locked(f,x):
 b=enc(x);f.seek(0);f.truncate(0)
 if f.write(b)!=len(b):raise RuntimeError('short_ledger_write')
 f.flush();os.fsync(f.fileno())
def num(v):
 s=str(v);return int(s) if re.fullmatch(r'[1-9][0-9]{0,21}',s) else None
def identity(x,key):
 if not isinstance(x,dict):return None
 c=x.get(key);return num(c.get('id')) if isinstance(c,dict) else num(x.get(key+'Id'))
def unwrap(x):
 return x['data'] if isinstance(x,dict) and isinstance(x.get('data'),dict) else x
def safe_body(x,token):
 raw=json.dumps(x,ensure_ascii=False)
 return not(token and token in raw) and not re.search(r'"(?:access_token|oauth_token|refresh_token|password|authorization|secret)"\s*:',raw,re.I) and not re.search(r'[?&](?:access_token|oauth_token|token|password|auth|secret|session)=',raw,re.I)
def parse_link(op,d):
 u=d.get('operatorLink') if isinstance(d,dict) else None
 if not isinstance(u,str) or not u or len(u)>8192:return {'link_state':'missing','reason':'operator_link_missing'}
 try:p=urllib.parse.urlsplit(u);port=p.port
 except ValueError:return {'link_state':'invalid','reason':'operator_link_malformed'}
 if p.scheme!='https' or p.hostname not in ROUTES[op][2] or p.username or p.password or port or p.fragment:return {'link_state':'invalid','reason':'operator_link_origin','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
 pairs=urllib.parse.parse_qsl(p.query,keep_blank_values=True)
 if any(re.search(r'token|password|auth|secret|session',k,re.I) for k,_ in pairs):return {'link_state':'invalid','reason':'sensitive_operator_link','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
 out={'operator_link':u,'operator_link_sha256':hashlib.sha256(u.encode()).hexdigest(),'query_keys':[k for k,_ in pairs]}
 if op in (25,43):
  vals=[v for k,v in pairs if k.upper()=='HOTELS'];tokens=[x.strip() for v in vals for x in re.split('[,;]',v) if x.strip()];pos=[int(x) for x in tokens if re.fullmatch(r'[1-9][0-9]{0,12}',x)]
  out.update(raw_identity_key='HOTELS',raw_identity_values=vals,raw_identity_tokens=tokens,positive_native_candidates=pos,link_state=('captured_single_native' if len(pos)==len(tokens)==1 else 'captured_ambiguous_native'))
 else:
  fields=[{'key':k,'raw_value':v} for k,v in pairs if re.fullmatch(r'[0-9]+',v or '')]
  out.update(raw_numeric_query_fields=fields,positive_native_candidates=[],link_state='captured_biblio_unproven')
 return out
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,headers,newurl):return None
class Provider:
 def __init__(self,root,opdir):
  self.home=pathlib.Path(os.environ['HOME']);self.dir=opdir;self.used=0;self.last=None;self.counts=collections.Counter()
  q=self.home/'.anytour-match/provider-quotas';self.dayfile=q/('tourvisor-test-'+DAY+'.json');self.opfile=q/('tourvisor-'+OP+'.json')
  if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY:raise RuntimeError('provider_day_mismatch')
  if not self.dayfile.exists():raise RuntimeError('day_ledger_missing_after_reverse110')
  with open(self.dayfile,'r+b') as f:
   fcntl.flock(f,fcntl.LOCK_EX);d=read_locked(f);accounted=max(int(d.get('accounted_requests',0)),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
   if str(d.get('provider_day',DAY))!=DAY or int(d.get('owner_daily_limit',LIMIT))!=LIMIT or accounted>=LIMIT:raise RuntimeError('daily_budget_guard')
   self.last=accounted
  save(self.opfile,{'operation':OP,'status':'reserved_before_provider_access','operation_cap':CALL_CAP,'used':0,'provider_day':DAY})
  save(self.dir/'quota-reservation.json',{'operation':OP,'accounted_before_local_ledger':self.last,'cap':CALL_CAP,'limit':LIMIT,'provider_day':DAY,'does_not_claim_external_remaining':True})
  cp=subprocess.run(['php','-r','require $argv[1]; fwrite(STDOUT,(string)(defined("TOURVISOR_JWT")?TOURVISOR_JWT:getenv("TOURVISOR_JWT")));','--',str(root/'config.php')],capture_output=True,check=True)
  self.token=cp.stdout.decode().strip()
  if not self.token or '\n' in self.token:raise RuntimeError('token_invalid')
  self.opener=urllib.request.build_opener(NoRedirect())
 def charge(self,path,params):
  if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY:raise RuntimeError('provider_day_changed')
  with open(self.dayfile,'r+b') as df,open(self.opfile,'r+b') as of:
   fcntl.flock(df,fcntl.LOCK_EX);fcntl.flock(of,fcntl.LOCK_EX);d,o=read_locked(df),read_locked(of)
   if o.get('operation')!=OP or o.get('status') not in ('reserved_before_provider_access','provider_accessed'):raise RuntimeError('terminal_operation')
   used=int(o['used']);charged=max(int(d.get('accounted_requests',0)),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
   if charged>=LIMIT or used>=CALL_CAP:raise RuntimeError('quota_exhausted')
   d['match_new_attempts']=int(d.get('match_new_attempts',0))+1;d['accounted_requests']=charged+1;d.setdefault('operations',{})[OP]=used+1
   o.update(status='provider_accessed',used=used+1,last_action='tour_detail');write_locked(df,d);write_locked(of,o);self.used=used+1;self.last=charged+1
  self.counts['tour_detail']+=1;save(self.dir/f'request-{self.used:04d}.json',{'call':self.used,'accounted_local_ledger':self.last,'action':'tour_detail','path':path,'params':params})
 def detail(self,tid):
  path='/tours/'+urllib.parse.quote(str(tid),safe='');params={'currency':'RUB'};self.charge(path,params)
  req=urllib.request.Request(BASE+path+'?currency=RUB',headers={'Authorization':'Bearer '+self.token,'Accept':'application/json'})
  try:r=self.opener.open(req,timeout=65)
  except urllib.error.HTTPError as e:r=e
  with r:code=r.code;raw=r.read(BODY_LIMIT+1)
  if len(raw)>BODY_LIMIT:raise RuntimeError('body_limit')
  try:data=json.loads(raw)
  except Exception:data={'unparsed_body_sha256':hashlib.sha256(raw).hexdigest()}
  if not safe_body(data,self.token):save(self.dir/f'response-{self.used:04d}.json',{'http_status':code,'withheld_sensitive_body_sha256':hashlib.sha256(raw).hexdigest()});raise RuntimeError('sensitive_response')
  save(self.dir/f'response-{self.used:04d}.json',{'http_status':code,'raw_sha256':hashlib.sha256(raw).hexdigest(),'data':data})
  if code in (401,403,429):raise RuntimeError('http_'+str(code))
  if code not in (200,404):raise RuntimeError('http_'+str(code))
  return code,unwrap(data)
 def finish(self,status,digest):
  with open(self.opfile,'r+b') as f:fcntl.flock(f,fcntl.LOCK_EX);o=read_locked(f);o.update(status=status,result_sha256=digest);write_locked(f,o)
def execute(root,opdir,planp):
 reservation=json.loads((opdir/'reservation.json').read_text());plan=json.loads(planp.read_text())
 if reservation.get('operation')!=OP or plan.get('operation')!=OP or plan.get('state')!='current_plan_complete':raise RuntimeError('input_guard')
 rows=plan.get('detail_ready') or [];seen=set()
 for x in rows:
  k=(int(x['tv_hotel_id']),int(x['operator_id']))
  if k in seen or int(x['operator_id']) not in ROUTES or str(x.get('supplier_namespace'))!=ROUTES[int(x['operator_id'])][1] or not str(x.get('tour_id') or ''):raise RuntimeError('row_guard')
  seen.add(k)
 provider=None;edges=[];state='failed_before_provider_access';reason=None
 try:
  provider=Provider(root,opdir)
  for i,x in enumerate(rows,1):
   if provider.used>=CALL_CAP:break
   hid=int(x['tv_hotel_id']);op=int(x['operator_id']);tid=str(x['tour_id']);code,d=provider.detail(tid)
   e={'index':i,'tv_hotel_id':hid,'hotel_name':x['name'],'country':x['country_name'],'bucket':x['bucket'],'operator_id':op,'operator':ROUTES[op][0],'target_supplier_namespace':ROUTES[op][1],'tour_id':tid,'retained_search_id':x['search_id'],'retained_date':x['departure_date'],'retained_nights':x['nights'],'safe_to_write_now':False}
   if code==404:e['state']='stale_detail_404'
   elif not isinstance(d,dict):e['state']='detail_shape'
   else:
    rh=identity(d,'hotel');ro=identity(d,'operator');rt=str(d.get('id') or d.get('tourId') or '')
    e.update(returned_tv_hotel_id=rh,returned_operator_id=ro,returned_tour_id=rt)
    if rh!=hid or ro!=op or rt!=tid:e['state']='detail_identity_mismatch'
    else:e['state']='detail_identity_verified';e.update(parse_link(op,d))
   save(opdir/f'edge-{i:04d}.json',e);edges.append(e)
  state='completed_read_only' if len(edges)==len(rows) else 'completed_cap_reached'
 except Exception as ex:
  reason=type(ex).__name__+':'+str(ex) if isinstance(ex,RuntimeError) else type(ex).__name__;state='terminal_failed_no_replay' if provider and provider.used else 'failed_before_provider_access'
 counts=collections.Counter(e['state'] for e in edges);links=collections.Counter(e.get('link_state','none') for e in edges);ops=collections.Counter(str(e['operator_id']) for e in edges)
 out={'operation':OP,'state':state,'reason':reason,'planned':len(rows),'attempted':len(edges),'provider_calls':provider.used if provider else 0,'daily_accounted_after_local_ledger':provider.last if provider else None,'does_not_claim_external_remaining':True,'operator_attempt_counts':dict(ops),'state_counts':dict(counts),'link_state_counts':dict(links),'edges':edges,'search_calls':0,'status_calls':0,'results_calls':0,'dates_calls':0,'continue_calls':0,'samo_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':state!='failed_before_provider_access'}
 dig=save(opdir/'result.json',out);save(opdir/'receipt.json',{'operation':OP,'state':state,'result_sha256':dig,'provider_calls':out['provider_calls'],'database_writes':0,'mapping_writes':0,'no_replay':out['no_replay']})
 if provider:provider.finish(state,dig)
 print(json.dumps({'state':state,'planned':len(rows),'attempted':len(edges),'calls':out['provider_calls'],'local_ledger':out['daily_accounted_after_local_ledger'],'operators':dict(ops),'states':dict(counts),'links':dict(links),'result_sha256':dig},ensure_ascii=False))
 return 0 if state in ('completed_read_only','completed_cap_reached') else 2
def selftest():
 assert parse_link(25,{'operatorLink':'https://b2b.fstravel.com/x?HOTELS=784528'})['positive_native_candidates']==[784528]
 assert parse_link(43,{'operatorLink':'https://searchtour.intourist.ru/x?HOTELS=572'})['positive_native_candidates']==[572]
 assert parse_link(18,{'operatorLink':'https://bgoperator.ru/price.shtml?F4=12&id_price=33'})['link_state']=='captured_biblio_unproven'
 print('RESIDUAL_COMMON4_DETAIL_SELFTEST_OK')
if __name__=='__main__':
 if '--self-test' in sys.argv:selftest()
 elif '--execute' in sys.argv:sys.exit(execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_PLAN_PATH'])))
 else:raise SystemExit('disabled')
