#!/usr/bin/env python3
import collections,datetime as dt,fcntl,hashlib,json,os,pathlib,re,subprocess,sys,urllib.error,urllib.parse,urllib.request
from zoneinfo import ZoneInfo
OP='hotel-match-saved159-live-detail-1971-20260920-v1';DAY='2026-09-20';LIMIT=3000;CALL_CAP=159
BASE='https://api.tourvisor.ru/search/api/v1';BODY_LIMIT=16*1024*1024
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
 if isinstance(x,dict) and isinstance(x.get('data'),dict):return x['data']
 return x
def safe_body(x,token):
 raw=json.dumps(x,ensure_ascii=False)
 return not(token and token in raw) and not re.search(r'"(?:access_token|oauth_token|refresh_token|password|authorization|secret)"\s*:',raw,re.I) and not re.search(r'[?&](?:access_token|oauth_token|token|password|auth|secret|session)=',raw,re.I)
def anex_link(d):
 u=d.get('operatorLink') if isinstance(d,dict) else None
 if not isinstance(u,str) or not u or len(u)>8192:return {'link_state':'missing','reason':'operator_link_missing'}
 try:p=urllib.parse.urlsplit(u);port=p.port
 except ValueError:return {'link_state':'invalid','reason':'operator_link_malformed'}
 if p.scheme!='https' or p.hostname!='agent.anextour.ru' or p.username or p.password or port or p.fragment:return {'link_state':'invalid','reason':'operator_link_origin','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
 pairs=urllib.parse.parse_qsl(p.query,keep_blank_values=True)
 if any(re.search(r'token|password|auth|secret|session',k,re.I) for k,_ in pairs):return {'link_state':'invalid','reason':'sensitive_operator_link','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
 vals=[v for k,v in pairs if k.upper()=='HOTELLIST'];tokens=[x.strip() for v in vals for x in re.split('[,;]',v) if x.strip()];pos=[int(x) for x in tokens if re.fullmatch(r'[1-9][0-9]{0,8}',x)]
 return {'operator_link':u,'operator_link_sha256':hashlib.sha256(u.encode()).hexdigest(),'raw_hotellist_values':vals,'raw_signed_tokens':tokens,'positive_native_candidates':pos,'link_state':('captured_single_native' if len(pos)==len(tokens)==1 else 'captured_ambiguous_native')}
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,headers,newurl):return None
class Provider:
 def __init__(self,root,opdir):
  self.home=pathlib.Path(os.environ['HOME']);self.dir=opdir;self.used=0;self.last=None;self.counts=collections.Counter()
  q=self.home/'.anytour-match/provider-quotas';q.mkdir(parents=True,exist_ok=True)
  self.dayfile=q/('tourvisor-test-'+DAY+'.json');self.opfile=q/('tourvisor-'+OP+'.json')
  if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY:raise RuntimeError('provider_day_mismatch')
  if not self.dayfile.exists():
   initial={'provider':'tourvisor-test','provider_day':DAY,'owner_daily_limit':LIMIT,'accounted_requests':0,'known_prior_attempt_floor':0,'match_new_attempts':0,'baseline_status':'owner_authorized_unknown_prior_usage','unknown_prior_usage':True,'owner_authorization_comment':5752518346,'operations':{}}
   try:save(self.dayfile,initial)
   except FileExistsError:pass
  with open(self.dayfile,'r+b') as f:
   fcntl.flock(f,fcntl.LOCK_EX);d=read_locked(f)
   if str(d.get('provider_day',DAY))!=DAY or int(d.get('owner_daily_limit',LIMIT))!=LIMIT:raise RuntimeError('day_ledger_contract')
   accounted=max(int(d.get('accounted_requests',0)),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
   if accounted>=LIMIT:raise RuntimeError('daily_budget_guard')
   self.last=accounted
  save(self.opfile,{'operation':OP,'status':'reserved_before_provider_access','operation_cap':CALL_CAP,'used':0,'provider_day':DAY,'owner_authorized_unknown_prior_usage':True})
  save(self.dir/'quota-reservation.json',{'operation':OP,'accounted_before_local_ledger':self.last,'cap':CALL_CAP,'limit':LIMIT,'provider_day':DAY,'unknown_prior_usage':True,'does_not_claim_external_remaining':True})
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
   o.update(status='provider_accessed',used=used+1,last_action='tour_detail')
   write_locked(df,d);write_locked(of,o);self.used=used+1;self.last=charged+1
  self.counts['tour_detail']+=1;save(self.dir/f'request-{self.used:03d}.json',{'call':self.used,'accounted_local_ledger':self.last,'action':'tour_detail','path':path,'params':params})
 def detail(self,tid):
  path='/tours/'+urllib.parse.quote(str(tid),safe='');params={'currency':'RUB'};self.charge(path,params)
  req=urllib.request.Request(BASE+path+'?currency=RUB',headers={'Authorization':'Bearer '+self.token,'Accept':'application/json'})
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
  return code,unwrap(data)
 def finish(self,status,digest):
  with open(self.opfile,'r+b') as f:fcntl.flock(f,fcntl.LOCK_EX);o=read_locked(f);o.update(status=status,result_sha256=digest);write_locked(f,o)
def execute(root,opdir,inputp):
 res=json.loads((opdir/'reservation.json').read_text());inp=json.loads(inputp.read_text())
 if res.get('operation')!=OP or inp.get('state')!='completed_read_only' or inp.get('old_ready181_remaining')!=159:raise RuntimeError('input_guard')
 rows=[x for x in inp['remaining_retained_context_frontier'] if x.get('route')=='saved_tour_detail_ready']
 if len(rows)!=159:raise RuntimeError('row_count')
 seen=set()
 for x in rows:
  hid=int(x['local_hotel_id']);ctx=x.get('latest_future_anex_context') or {};tid=str(ctx.get('tour_id') or '')
  if hid in seen or not tid or int(ctx.get('departure_id') or 0)<1 or int(ctx.get('country_id') or 0)<1:raise RuntimeError('row_guard')
  seen.add(hid)
 provider=None;edges=[];state='failed_before_provider_access';reason=None
 try:
  provider=Provider(root,opdir)
  for i,x in enumerate(rows,1):
   hid=int(x['local_hotel_id']);ctx=x['latest_future_anex_context'];tid=str(ctx['tour_id']);code,d=provider.detail(tid)
   e={'index':i,'tv_hotel_id':hid,'hotel_name':x['name'],'country':x['country'],'region':x['region'],'subregion':x['subregion'],'operator_id':13,'operator':'ANEX','target_supplier_namespace':'operator_5','tour_id':tid,'retained_search_id':ctx['search_id'],'retained_date':ctx['departure_date'],'retained_nights':ctx['nights'],'safe_to_write_now':False}
   if code==404:e['state']='stale_detail_404'
   elif not isinstance(d,dict):e['state']='detail_shape'
   else:
    rh=identity(d,'hotel');ro=identity(d,'operator');rt=str(d.get('id') or d.get('tourId') or '')
    e.update(returned_tv_hotel_id=rh,returned_operator_id=ro,returned_tour_id=rt)
    if rh!=hid or ro!=13 or rt!=tid:e['state']='detail_identity_mismatch'
    else:e['state']='detail_identity_verified';e.update(anex_link(d))
   save(opdir/f'edge-{i:03d}.json',e);edges.append(e)
  state='completed_read_only'
 except Exception as ex:
  reason=type(ex).__name__+':'+str(ex) if isinstance(ex,RuntimeError) else type(ex).__name__;state='terminal_failed_no_replay' if provider and provider.used else 'failed_before_provider_access'
 counts=collections.Counter(e['state'] for e in edges);links=collections.Counter(e.get('link_state','none') for e in edges)
 out={'operation':OP,'state':state,'reason':reason,'input_artifact':10603089800,'input_result_sha256':'2b09cbda05446ca5210901fcd8f5d9498bdbab2f30e0cab4c6b0192f68c6ed66','input_count':159,'attempted':len(edges),'provider_calls':provider.used if provider else 0,'daily_accounted_after_local_ledger':provider.last if provider else None,'unknown_prior_usage':True,'does_not_claim_external_remaining':True,'state_counts':dict(counts),'link_state_counts':dict(links),'edges':edges,'search_calls':0,'status_calls':0,'results_calls':0,'dates_calls':0,'continue_calls':0,'samo_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':state!='failed_before_provider_access'}
 dig=save(opdir/'result.json',out);save(opdir/'receipt.json',{'operation':OP,'state':state,'result_sha256':dig,'provider_calls':out['provider_calls'],'database_writes':0,'mapping_writes':0,'no_replay':out['no_replay']})
 if provider:provider.finish(state,dig)
 print(json.dumps({'state':state,'attempted':len(edges),'calls':out['provider_calls'],'local_ledger':out['daily_accounted_after_local_ledger'],'states':dict(counts),'links':dict(links),'result_sha256':dig},ensure_ascii=False))
 return 0 if state=='completed_read_only' else 2
def selftest():
 assert unwrap({'data':{'id':1}})=={'id':1} and unwrap({'id':1})=={'id':1}
 assert anex_link({'operatorLink':'https://agent.anextour.ru/search/tour?HOTELLIST=11241'})['positive_native_candidates']==[11241]
 assert anex_link({'operatorLink':'https://agent.anextour.ru/search/tour?HOTELLIST=1,-2'})['link_state']=='captured_ambiguous_native'
 print('SAVED159_LIVE_DETAIL_SELFTEST_OK')
if __name__=='__main__':
 if '--self-test' in sys.argv:selftest()
 elif '--execute' in sys.argv:sys.exit(execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_INPUT_PATH'])))
 else:raise SystemExit('disabled')
