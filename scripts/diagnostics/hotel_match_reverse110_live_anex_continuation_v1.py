#!/usr/bin/env python3
import collections,datetime as dt,fcntl,hashlib,json,os,pathlib,re,subprocess,sys,time,urllib.error,urllib.parse,urllib.request
from zoneinfo import ZoneInfo
OP='hotel-match-reverse110-live-anex-continuation-1971-20260921-v1';DAY='2026-09-21';LIMIT=3000;CALL_CAP=600;BASE='https://api.tourvisor.ru/search/api/v1';BODY_LIMIT=16*1024*1024
def enc(x):return (json.dumps(x,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
def save(p,x):
 p=pathlib.Path(p);p.parent.mkdir(parents=True,exist_ok=True);b=enc(x)
 with open(p,'xb') as f:
  if f.write(b)!=len(b):raise RuntimeError('short_write')
  f.flush();os.fsync(f.fileno())
 return hashlib.sha256(b).hexdigest()
def readl(f):f.seek(0);x=json.loads(f.read());return x if isinstance(x,dict) else (_ for _ in ()).throw(RuntimeError('ledger_shape'))
def writel(f,x):b=enc(x);f.seek(0);f.truncate(0);f.write(b);f.flush();os.fsync(f.fileno())
def num(v):
 s=str(v);return int(s) if re.fullmatch(r'[1-9][0-9]{0,21}',s) else None
def ident(x,k):
 if not isinstance(x,dict):return None
 c=x.get(k);return num(c.get('id')) if isinstance(c,dict) else num(x.get(k+'Id'))
def unwrap(x):return x['data'] if isinstance(x,dict) and isinstance(x.get('data'),(dict,list)) else x
def rows(x):
 x=unwrap(x)
 if isinstance(x,list):return x
 if isinstance(x,dict):
  for k in ('hotels','results','items'):
   if isinstance(x.get(k),list):return x[k]
 raise RuntimeError('results_shape')
def ready(x):
 x=unwrap(x)
 if not isinstance(x,dict):return False
 return (isinstance(x.get('progress'),(int,float)) and x['progress']>=100) or str(x.get('status','')).lower() in ('complete','completed','done','ready') or any(ready(v) for v in x.values() if isinstance(v,dict))
def safe(x,t):
 raw=json.dumps(x,ensure_ascii=False)
 return not(t and t in raw) and not re.search(r'"(?:access_token|oauth_token|refresh_token|password|authorization|secret)"\s*:',raw,re.I) and not re.search(r'[?&](?:access_token|oauth_token|token|password|auth|secret|session)=',raw,re.I)
def link(d):
 u=d.get('operatorLink') if isinstance(d,dict) else None
 if not isinstance(u,str) or not u:return {'link_state':'missing','reason':'operator_link_missing'}
 try:p=urllib.parse.urlsplit(u)
 except ValueError:return {'link_state':'invalid','reason':'operator_link_malformed'}
 if p.scheme!='https' or p.hostname!='agent.anextour.ru' or p.username or p.password or p.port or p.fragment:return {'link_state':'invalid','reason':'operator_link_origin','operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()}
 pairs=urllib.parse.parse_qsl(p.query,keep_blank_values=True);vals=[v for k,v in pairs if k.upper()=='HOTELLIST'];tok=[x.strip() for v in vals for x in re.split('[,;]',v) if x.strip()];pos=[int(x) for x in tok if re.fullmatch(r'[1-9][0-9]{0,8}',x)]
 return {'operator_link':u,'operator_link_sha256':hashlib.sha256(u.encode()).hexdigest(),'raw_hotellist_values':vals,'raw_signed_tokens':tok,'positive_native_candidates':pos,'link_state':('captured_single_native' if len(pos)==len(tok)==1 else 'captured_ambiguous_native')}
class NR(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,headers,newurl):return None
class P:
 def __init__(self,root,opdir):
  q=pathlib.Path(os.environ['HOME'])/'.anytour-match/provider-quotas';self.day=q/('tourvisor-test-'+DAY+'.json');self.op=q/('tourvisor-'+OP+'.json');self.dir=opdir;self.used=0;self.last=None;self.counts=collections.Counter()
  if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY:raise RuntimeError('provider_day_mismatch')
  with open(self.day,'r+b') as f:
   fcntl.flock(f,fcntl.LOCK_EX);d=readl(f);self.last=max(int(d.get('accounted_requests',0)),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
   if self.last<159 or self.last>=LIMIT:raise RuntimeError('daily_budget_guard')
  save(self.op,{'operation':OP,'status':'reserved_before_provider_access','used':0,'operation_cap':CALL_CAP,'provider_day':DAY})
  save(opdir/'quota-reservation.json',{'operation':OP,'accounted_before_local_ledger':self.last,'cap':CALL_CAP,'limit':LIMIT,'provider_day':DAY,'unknown_prior_usage':True})
  cp=subprocess.run(['php','-r','require $argv[1]; fwrite(STDOUT,(string)(defined("TOURVISOR_JWT")?TOURVISOR_JWT:getenv("TOURVISOR_JWT")));','--',str(root/'config.php')],capture_output=True,check=True);self.token=cp.stdout.decode().strip()
  if not self.token:raise RuntimeError('token_invalid')
  self.open=urllib.request.build_opener(NR())
 def call(self,action,path,params):
  if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY:raise RuntimeError('provider_day_changed')
  with open(self.day,'r+b') as df,open(self.op,'r+b') as of:
   fcntl.flock(df,fcntl.LOCK_EX);fcntl.flock(of,fcntl.LOCK_EX);d,o=readl(df),readl(of);used=int(o['used']);charged=max(int(d.get('accounted_requests',0)),int(d.get('known_prior_attempt_floor',0))+int(d.get('match_new_attempts',0)))
   if charged>=LIMIT or used>=CALL_CAP:raise RuntimeError('quota_exhausted')
   d['match_new_attempts']=int(d.get('match_new_attempts',0))+1;d['accounted_requests']=charged+1;d.setdefault('operations',{})[OP]=used+1;o.update(status='provider_accessed',used=used+1,last_action=action);writel(df,d);writel(of,o);self.used=used+1;self.last=charged+1
  self.counts[action]+=1;save(self.dir/f'request-{self.used:03d}.json',{'call':self.used,'accounted_local_ledger':self.last,'action':action,'path':path,'params':params})
  pairs=[(k,str(x).lower() if isinstance(x,bool) else str(x)) for k,v in params.items() for x in (v if isinstance(v,list) else [v])]
  req=urllib.request.Request(BASE+path+('?' + urllib.parse.urlencode(pairs) if pairs else ''),headers={'Authorization':'Bearer '+self.token,'Accept':'application/json'})
  try:r=self.open.open(req,timeout=55)
  except urllib.error.HTTPError as e:r=e
  with r:code=r.code;raw=r.read(BODY_LIMIT+1)
  try:data=json.loads(raw)
  except Exception:data={'unparsed_body_sha256':hashlib.sha256(raw).hexdigest()}
  if not safe(data,self.token):save(self.dir/f'response-{self.used:03d}.json',{'http_status':code,'withheld_sensitive_body_sha256':hashlib.sha256(raw).hexdigest()});raise RuntimeError('sensitive_response')
  save(self.dir/f'response-{self.used:03d}.json',{'http_status':code,'raw_sha256':hashlib.sha256(raw).hexdigest(),'data':data})
  if code==429:raise RuntimeError('quota_http_429')
  if code in (401,403):raise RuntimeError('auth_http_'+str(code))
  if code!=200 and not(action=='tour_detail' and code==404):raise RuntimeError('http_'+str(code))
  return code,data
 def finish(self,s,dig):
  with open(self.op,'r+b') as f:fcntl.flock(f,fcntl.LOCK_EX);o=readl(f);o.update(status=s,result_sha256=dig);writel(f,o)
def batch(p,opdir,n,g):
 ids=g['hotel_ids'];ages=[int(x) for x in g['child_ages_signature'].split(',') if x!='']
 params={'departureId':g['departure_id'],'countryId':g['country_id'],'dateFrom':g['departure_date'],'dateTo':g['departure_date'],'nightsFrom':g['nights'],'nightsTo':g['nights'],'adults':g['adults'],'childs':ages,'currency':'RUB','onlyCharter':False,'operatorIds':[13],'hotelIds':ids}
 _,st=p.call('search_start','/tours/search',params);st=unwrap(st);sid=num(st.get('searchId') or st.get('id')) if isinstance(st,dict) else None
 if not sid:raise RuntimeError('search_id_missing')
 for delay in (3,5,8):
  time.sleep(delay);_,s=p.call('search_status',f'/tours/search/{sid}/status',{'operatorStatus':False})
  if ready(s):break
 else:raise RuntimeError('search_timeout')
 _,res=p.call('search_results',f'/tours/search/{sid}',{'limit':10000});out=[];wanted=set(ids)
 for h in rows(res):
  hid=num(h.get('id')) if isinstance(h,dict) else None
  if hid not in wanted:continue
  tours=[t for t in (h.get('tours') or []) if ident(t,'operator')==13 and num(t.get('id') or t.get('tourId'))]
  e={'tv_hotel_id':hid,'hotel_name':h.get('name'),'search_id':sid,'batch':n,'operator_id':13,'safe_to_write_now':False}
  if not tours:e['state']='returned_no_anex_tour'
  else:
   t=tours[0];tid=str(t.get('id') or t.get('tourId'));code,d=p.call('tour_detail','/tours/'+tid,{'currency':'RUB'});d=unwrap(d);e['tour_id']=tid
   if code==404:e['state']='detail_404'
   elif not isinstance(d,dict) or ident(d,'hotel')!=hid or ident(d,'operator')!=13 or str(d.get('id') or d.get('tourId') or '')!=tid:e['state']='detail_identity_mismatch'
   else:e['state']='detail_identity_verified';e.update(link(d))
  save(opdir/f'hotel-{hid}.json',e);out.append(e)
 return sid,out
def execute(root,opdir,inputp):
 inp=json.loads(inputp.read_text());res=json.loads((opdir/'reservation.json').read_text())
 if res['operation']!=OP or len(inp.get('rows',[]))!=110:raise RuntimeError('input_guard')
 groups={}
 for x in inp['rows']:
  k=(x['departure_id'],x['country_id'],x['departure_date'],x['nights'],x['adults'],x['children_count'],x['child_ages_signature'])
  groups.setdefault(k,[]).append(x)
 plan=[]
 for k,v in groups.items():
  ids=sorted({int(x['tv_hotel_id']) for x in v});assert len(ids)<=30
  plan.append({'departure_id':k[0],'country_id':k[1],'departure_date':k[2],'nights':k[3],'adults':k[4],'children_count':k[5],'child_ages_signature':k[6],'hotel_ids':ids})
 plan.sort(key=lambda g:(-len(g['hotel_ids']),g['departure_date'],g['country_id']))
 save(opdir/'plan.json',{'operation':OP,'groups':len(plan),'hotels':110,'plan':plan})
 p=None;batches=[];state='failed_before_provider_access';reason=None
 try:
  p=P(root,opdir)
  for i,g in enumerate(plan,1):
   save(opdir/f'batch-{i:03d}-reservation.json',{'operation':OP,'batch':i,'context':g,'state':'reserved_before_batch_http'})
   before=p.used;sid,ev=batch(p,opdir,i,g);b={'batch':i,'search_id':sid,'sent':len(g['hotel_ids']),'found':len(ev),'calls':p.used-before,'hotel_ids':g['hotel_ids']};save(opdir/f'batch-{i:03d}-result.json',b);batches.append(b)
  state='completed_read_only'
 except Exception as e:
  reason=type(e).__name__+':'+str(e) if isinstance(e,RuntimeError) else type(e).__name__
  if 'provider_day_changed' in reason:state='terminal_day_changed_no_replay'
  elif 'quota' in reason:state='terminal_quota_stop_no_replay'
  else:state='terminal_failed_no_replay' if p and p.used else 'failed_before_provider_access'
 ev=[json.loads(x.read_text()) for x in sorted(opdir.glob('hotel-*.json'))];links=collections.Counter(x.get('link_state','none') for x in ev)
 out={'operation':OP,'state':state,'reason':reason,'input_count':110,'planned_groups':len(plan),'completed_batches':len(batches),'found_hotels':len(ev),'provider_calls':p.used if p else 0,'daily_accounted_after_local_ledger':p.last if p else None,'call_counts':dict(p.counts) if p else {},'link_state_counts':dict(links),'batches':batches,'edges':ev,'continue_calls':0,'dates_calls':0,'samo_calls':0,'database_writes':0,'mapping_writes':0,'no_replay':state!='failed_before_provider_access'}
 dig=save(opdir/'result.json',out);save(opdir/'receipt.json',{'operation':OP,'state':state,'result_sha256':dig,'provider_calls':out['provider_calls'],'no_replay':out['no_replay']})
 if p:p.finish(state,dig)
 print(json.dumps({'state':state,'reason':reason,'batches':len(batches),'found':len(ev),'calls':out['provider_calls'],'daily':out['daily_accounted_after_local_ledger'],'links':dict(links),'sha':dig},ensure_ascii=False))
 return 0 if state in ('completed_read_only','terminal_day_changed_no_replay','terminal_quota_stop_no_replay') else 2
def selftest():print('REVERSE110_LIVE_SELFTEST_OK')
if __name__=='__main__':
 if '--self-test' in sys.argv:selftest()
 elif '--execute' in sys.argv:sys.exit(execute(pathlib.Path(os.environ['ANYTOUR_ROOT']),pathlib.Path(os.environ['MATCH_OPERATION_DIR']),pathlib.Path(os.environ['MATCH_INPUT_PATH'])))
 else:raise SystemExit('disabled')
