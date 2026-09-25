#!/usr/bin/env python3
import collections,datetime as dt,fcntl,hashlib,json,os,pathlib,re,subprocess,sys,urllib.error,urllib.parse,urllib.request
from zoneinfo import ZoneInfo
OP='hotel-match-refresh82-detail-only-1971-20260919-v1';DAY='2026-09-19';LIMIT=3000;KNOWN_FLOOR=1180;CALL_CAP=82
BASE='https://api.tourvisor.ru/search/api/v1';BODY_LIMIT=16*1024*1024
ROUTES={13:('anex','operator_5','agent.anextour.ru'),18:('biblio_globus','operator_115','www.bgoperator.ru'),25:('funsun','operator_315','b2b.fstravel.com'),43:('intourist','operator_342','searchtour.intourist.ru')}
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
def safe_body(x,token):
 raw=json.dumps(x,ensure_ascii=False)
 return not(token and token in raw) and not re.search(r'"(?:access_token|oauth_token|refresh_token|password|authorization|secret)"\s*:',raw,re.I) and not re.search(r'[?&](?:access_token|oauth_token|token|password|auth|secret|session)=',raw,re.I)
def link(op,d):
 u=d.get('operatorLink') if isinstance(d,dict) else None
 if not isinstance(u,str) or not u or len(u)>8192:return {'link_state':'missing','reason':'operator_link_missing'}
 try:p=urllib.parse.urlsplit(u);port=p.port
 except ValueError:return {'link_state':'invalid','reason':'operator_link_malformed'}
 if p.scheme!='https' or p.hostname!=ROUTES[op][2] or p.username or p.password or port or p.fragment:return {'link_state':'invalid','reason':'operator_link_origin','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
 pairs=urllib.parse.parse_qsl(p.query,keep_blank_values=True)
 if any(re.search(r'token|password|auth|secret|session',k,re.I) for k,_ in pairs):return {'link_state':'invalid','reason':'sensitive_operator_link','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
 out={'operator_link':u,'operator_link_sha256':hashlib.sha256(u.encode()).hexdigest(),'query_keys':[k for k,_ in pairs]}
 if op==13:
  vals=[v for k,v in pairs if k.upper()=='HOTELLIST'];t=[x.strip() for v in vals for x in re.split('[,;]',v) if x.strip()]
  pos=[int(x) for x in t if re.fullmatch(r'[1-9][0-9]{0,8}',x)]
  out.update(raw_identity_key='HOTELLIST',raw_identity_values=vals,raw_identity_tokens=t,positive_native_candidates=pos,link_state=('captured_single_native' if len(pos)==len(t)==1 else 'captured_ambiguous_native'))
 elif op in (25,43):
  vals=[v for k,v in pairs if k.upper()=='HOTELS'];t=[x.strip() for v in vals for x in re.split('[,;]',v) if x.strip()]
  pos=[int(x) for x in t if re.fullmatch(r'[1-9][0-9]{0,12}',x)]
  out.update(raw_identity_key='HOTELS',raw_identity_values=vals,raw_identity_tokens=t,positive_native_candidates=pos,link_state=('captured_single_native' if len(pos)==len(t)==1 else 'captured_ambiguous_native'))
 else:
  nf=[{'key':k,'raw_value':v} for k,v in pairs if re.fullmatch(r'[0-9]+',v or '')]
  out.update(raw_numeric_query_fields=nf,positive_native_candidates=[],link_state='captured_biblio_unproven')
 return out
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,headers,newurl):return None
class Provider:
 def __init__(self,root,opdir):
  self.home=pathlib.Path(os.environ['HOME']);self.dir=opdir;self.used=0;self.last=None;self.counts=collections.Counter()
  self.dayfile=self.home/'.anytour-match/provider-quotas'/('tourvisor-test-'+DAY+'.json');self.opfile=self.dayfile.parent/('tourvisor-'+OP+'.json')
  if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY:raise RuntimeError('provider_day_mismatch')
  with open(self.dayfile,'r+b') as f:
   fcntl.flock(f,fcntl.LOCK_EX);d=read_locked(f);accounted=max(int(d['accounted_requests']),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
   if accounted<KNOWN_FLOOR or accounted>=min(LIMIT,int(d['owner_daily_limit'])):raise RuntimeError('daily_budget_guard')
   self.last=accounted
  save(self.opfile,{'operation':OP,'status':'reserved_before_provider_access','operation_cap':CALL_CAP,'used':0,'provider_day':DAY})
  save(self.dir/'quota-reservation.json',{'operation':OP,'accounted_before':accounted,'cap':CALL_CAP,'limit':LIMIT,'provider_day':DAY})
  cp=subprocess.run(['php','-r','require $argv[1]; fwrite(STDOUT,(string)(defined("TOURVISOR_JWT")?TOURVISOR_JWT:getenv("TOURVISOR_JWT")));','--',str(root/'config.php')],capture_output=True,check=True)
  self.token=cp.stdout.decode().strip()
  if not self.token or '\n' in self.token:raise RuntimeError('token_invalid')
  self.opener=urllib.request.build_opener(NoRedirect())
 def charge(self,path,params):
  with open(self.dayfile,'r+b') as df,open(self.opfile,'r+b') as of:
   fcntl.flock(df,fcntl.LOCK_EX);fcntl.flock(of,fcntl.LOCK_EX);d,o=read_locked(df),read_locked(of)
   used=int(o['used']);charged=max(int(d['accounted_requests']),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
   if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY or charged>=min(LIMIT,int(d['owner_daily_limit'])) or used>=CALL_CAP:raise RuntimeError('quota_exhausted')
   d['match_new_attempts']=int(d.get('match_new_attempts',0))+1;d['accounted_requests']=charged+1;d.setdefault('operations',{})[OP]=used+1;o.update(status='provider_accessed',used=used+1,last_action='tour_detail')
   write_locked(df,d);write_locked(of,o);self.used=used+1;self.last=charged+1
  self.counts['tour_detail']+=1;save(self.dir/f'request-{self.used:03d}.json',{'call':self.used,'accounted':self.last,'action':'tour_detail','path':path,'params':params})
 def detail(self,tid):
  path='/tours/'+urllib.parse.quote(str(tid),safe='');params={'currency':'RUB'};self.charge(path,params);url=BASE+path+'?currency=RUB';req=urllib.request.Request(url,headers={'Authorization':'Bearer '+self.token,'Accept':'application/json'})
  try:r=self.opener.open(req,timeout=65)
  except urllib.error.HTTPError as e:r=e
  with r:code=r.code;raw=r.read(BODY_LIMIT+1)
  if len(raw)>BODY_LIMIT:raise RuntimeError('body_limit')
  try:data=json.loads(raw)
  except Exception:data={'unparsed_body_sha256':hashlib.sha256(raw).hexdigest()}
  if not safe_body(data,self.token):save(self.dir/f'response-{self.used:03d}.json',{'http_status':code,'withheld_sensitive_body_sha256':hashlib.sha256(raw).hexdigest()});raise RuntimeError('sensitive_response')
  save(self.dir/f'response-{self.used:03d}.json',{'http_status':code,'raw_sha256':hashlib.sha256(raw).hexdigest(),'data':data})
  if code in (401,403,429):raise RuntimeError('http_'+str(code))
  if code not in (200,404):raise RuntimeError('http_'+str(code))
  return code,data
 def finish(self,status,digest):
  with open(self.opfile,'r+b') as f:fcntl.flock(f,fcntl.LOCK_EX);o=read_locked(f);o.update(status=status,result_sha256=digest);write_locked(f,o)
def execute(root,opdir,inputp):
 res=json.loads((opdir/'reservation.json').read_text());inp=json.loads(inputp.read_text())
 if res.get('operation')!=OP or inp.get('detail_needed_count')!=82:raise RuntimeError('input_guard')
 rows=inp['detail_needed'];seen=set()
 for x in rows:
  k=(int(x['tv_hotel_id']),int(x['tv_operator_id']))
  if k in seen or int(x['tv_operator_id']) not in ROUTES or x.get('state')!='detail_needed':raise RuntimeError('row_guard')
  seen.add(k)
 provider=None;edges=[];state='failed_before_provider_access';reason=None
 try:
  provider=Provider(root,opdir)
  for i,x in enumerate(rows,1):
   hid=int(x['tv_hotel_id']);op=int(x['tv_operator_id']);tid=str(x['tour_id']);code,d=provider.detail(tid)
   e={'index':i,'tv_hotel_id':hid,'hotel_name':x['hotel_name'],'operator_id':op,'operator':ROUTES[op][0],'target_supplier_namespace':ROUTES[op][1],'tour_id':tid,'retained_search_id':x['search_id'],'retained_date':x['departure_date'],'retained_nights':x['nights'],'safe_to_write_now':False}
   if code==404:e['state']='stale_detail_404'
   elif not isinstance(d,dict):e['state']='detail_shape'
   else:
    rh=identity(d,'hotel');ro=identity(d,'operator');rt=str(d.get('id') or d.get('tourId') or '')
    e.update(returned_tv_hotel_id=rh,returned_operator_id=ro,returned_tour_id=rt)
    if rh!=hid or ro!=op or rt!=tid:e['state']='detail_identity_mismatch'
    else:e['state']='detail_identity_verified';e.update(link(op,d))
   save(opdir/f'edge-{i:03d}.json',e);edges.append(e)
  state='completed_read_only'
 except Exception as ex:
  reason=type(ex).__name__+':'+str(ex) if isinstance(ex,RuntimeError) else type(ex).__name__;state='terminal_failed_no_replay' if provider and provider.used else 'failed_before_provider_access'
 counts=collections.Counter(e['state'] for e in edges);links=collections.Counter(e.get('link_state','none') for e in edges)
 out={'operation':OP,'state':state,'reason':reason,'input_router_artifact':10590289815,'input_router_result_sha256':'3b189d1b7313eb85743a6d22be48aa7323725fe6fb57e8d47b6e5ea220b1cb8f','input_count':82,'attempted':len(edges),'provider_calls':provider.used if provider else 0,'daily_accounted_after':provider.last if provider else None,'call_counts':dict(provider.counts) if provider else {},'state_counts':dict(counts),'link_state_counts':dict(links),'edges':edges,'search_calls':0,'status_calls':0,'results_calls':0,'dates_calls':0,'continue_calls':0,'samo_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':state!='failed_before_provider_access'}
 dig=save(opdir/'result.json',out);save(opdir/'receipt.json',{'operation':OP,'state':state,'result_sha256':dig,'provider_calls':out['provider_calls'],'daily_accounted_after':out['daily_accounted_after'],'database_writes':0,'mapping_writes':0,'no_replay':out['no_replay']})
 if provider:provider.finish(state,dig)
 print(json.dumps({'state':state,'attempted':len(edges),'calls':out['provider_calls'],'daily':out['daily_accounted_after'],'states':dict(counts),'links':dict(links),'result_sha256':dig},ensure_ascii=False))
 return 0 if state=='completed_read_only' else 2
def selftest():
 assert link(13,{'operatorLink':'https://agent.anextour.ru/x?HOTELLIST=35371'})['positive_native_candidates']==[35371]
 assert link(25,{'operatorLink':'https://b2b.fstravel.com/x?HOTELS=12,13'})['link_state']=='captured_ambiguous_native'
 assert link(18,{'operatorLink':'https://www.bgoperator.ru/x?F4=12&id_price=33'})['link_state']=='captured_biblio_unproven'
 print('REFRESH82_DETAIL_ONLY_SELFTEST_OK')
if __name__=='__main__':
 if '--self-test' in sys.argv:selftest()
 elif '--execute' in sys.argv:sys.exit(execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_INPUT_PATH'])))
 else:raise SystemExit('disabled')
