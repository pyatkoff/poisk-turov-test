#!/usr/bin/env python3
import collections,datetime as dt,fcntl,hashlib,json,os,pathlib,re,subprocess,time,urllib.error,urllib.parse,urllib.request
from zoneinfo import ZoneInfo
OP='hotel-match-remaining-tv-search30-1971-20260921-v2';DAY='2026-09-21';LIMIT=3000;BODY=32*1024*1024
BASE='https://api.tourvisor.ru/search/api/v1';OPS={13:'anex',18:'biblio',25:'funsun',43:'intourist'}
TARIFF={'search_start','search_continue','tour_flights'};UNMETERED={'search_status','search_results','tour_detail'}
def enc(x):return (json.dumps(x,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
def save(p,x):
 p=pathlib.Path(p);p.parent.mkdir(parents=True,exist_ok=True);b=enc(x)
 with open(p,'xb') as f:
  if f.write(b)!=len(b):raise RuntimeError('short_write')
  f.flush();os.fsync(f.fileno())
 return hashlib.sha256(b).hexdigest()
def rawsave(p,b):
 with open(p,'xb') as f:
  if f.write(b)!=len(b):raise RuntimeError('raw_short_write')
  f.flush();os.fsync(f.fileno())
 return hashlib.sha256(b).hexdigest()
def readj(p):x=json.loads(pathlib.Path(p).read_text());return x if isinstance(x,dict) else (_ for _ in ()).throw(RuntimeError('json_shape'))
def num(v):
 s=str(v);return int(s) if re.fullmatch(r'[1-9][0-9]{0,21}',s) else None
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
def ident(x,k):
 if not isinstance(x,dict):return None
 v=x.get(k);return num(v.get('id')) if isinstance(v,dict) else num(x.get(k+'Id'))
def link_state(op,d):
 u=d.get('operatorLink') if isinstance(d,dict) else None
 if not isinstance(u,str) or not u:return {'link_state':'missing'}
 try:p=urllib.parse.urlsplit(u)
 except ValueError:return {'link_state':'invalid'}
 if p.scheme!='https' or p.username or p.password or p.port or p.fragment:return {'link_state':'invalid'}
 out={'operator_link':u,'operator_link_sha256':hashlib.sha256(u.encode()).hexdigest()};pairs=urllib.parse.parse_qsl(p.query,keep_blank_values=True)
 if any(re.search(r'token|password|auth|secret|session',k,re.I) for k,_ in pairs):return {'link_state':'invalid_sensitive','operator_link_sha256':out['operator_link_sha256']}
 if op==13:
  if p.hostname!='agent.anextour.ru':return {'link_state':'invalid_origin','operator_link_sha256':out['operator_link_sha256']}
  vals=[v for k,v in pairs if k.upper()=='HOTELLIST'];tok=[z.strip() for v in vals for z in re.split('[,;]',v) if z.strip()];pos=[int(z) for z in tok if re.fullmatch(r'[1-9][0-9]{0,8}',z)]
  out.update(raw_identity_key='HOTELLIST',raw_identity_tokens=tok,positive_native_candidates=pos,link_state=('captured_single_native' if len(tok)==len(pos)==1 else 'captured_ambiguous_native'))
 elif op in (25,43):
  allowed={25:{'b2b.fstravel.com','newb2b.fstravel.com'},43:{'searchtour.intourist.ru'}}[op]
  if p.hostname not in allowed:return {'link_state':'invalid_origin','operator_link_sha256':out['operator_link_sha256']}
  vals=[v for k,v in pairs if k.upper()=='HOTELS'];tok=[z.strip() for v in vals for z in re.split('[,;]',v) if z.strip()];pos=[int(z) for z in tok if re.fullmatch(r'[1-9][0-9]{0,12}',z)]
  out.update(raw_identity_key='HOTELS',raw_identity_tokens=tok,positive_native_candidates=pos,link_state=('captured_single_native' if len(tok)==len(pos)==1 else 'captured_ambiguous_native'))
 else:
  if p.hostname not in {'www.bgoperator.ru','bgoperator.ru'}:return {'link_state':'invalid_origin','operator_link_sha256':out['operator_link_sha256']}
  out.update(positive_native_candidates=[],link_state='captured_biblio_unproven')
 return out
class NR(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,headers,newurl):return None
def scan_tariff(home):
 q=home/'.anytour-match/provider-quotas';opsdir=home/'.anytoour-match/operations';total=physical=0;breakdown=collections.Counter();seen=[]
 day_path=q/('tourvisor-test-'+DAY+'.json')
 if not day_path.is_file():raise RuntimeError('existing_physical_day_ledger_missing')
 day=readj(day_path)
 if str(day.get('provider_day',''))!=DAY or int(day.get('owner_daily_limit',0))!=LIMIT:raise RuntimeError('physical_day_ledger_identity')
 accounted=max(int(day.get('accounted_requests',0)),int(day.get('known_prior_attempt_floor',0))+int(day.get('match_new_attempts',0)))
 for p in sorted(q.glob('tourvisor-*.json')):
  if p.name.startswith('tourvisor-test-') or p.name.startswith('tourvisor-tariff-'):continue
  o=readj(p)
  if str(o.get('provider_day',''))!=DAY or int(o.get('used',0) or 0)<=0:continue
  used=int(o['used']);name=str(o.get('operation') or p.stem[len('tourvisor-'):]);req=sorted((opsdir/name).glob('request-*.json'))
  if len(req)!=used:raise RuntimeError('operation_request_count_mismatch:'+name)
  c=collections.Counter()
  for f in req:
   a=str(readj(f).get('action',''))
   if a not in TARIFF|UNMETERED:raise RuntimeError('unknown_accounting_action:'+a)
   c[a]+=1;physical+=1
  total+=sum(c[a] for a in TARIFF);breakdown.update(c);seen.append({'operation':name,'used':used,'actions':dict(c)})
 if physical!=accounted:raise RuntimeError('unreconciled_physical_attempts:'+str(accounted)+':'+str(physical))
 if total<97:raise RuntimeError('missing_pinned_reverse110_tariff_floor')
 return total,physical,dict(breakdown),seen
class Provider:
 def __init__(self,root,opdir):
  self.home=pathlib.Path(os.environ['HOME']);self.dir=opdir;self.physical=0;self.actions=collections.Counter();self.last=time.monotonic()-1
  if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY:raise RuntimeError('provider_day_mismatch')
  floor,physical,breakdown,ops=scan_tariff(self.home);self.tariff_path=self.home/'.anytour-match/provider-quotas'/('tourvisor-tariff-'+DAY+'.json')
  if self.tariff_path.exists():
   t=readj(self.tariff_path);cur=int(t.get('tariff_search_units',-1))
   if str(t.get('provider_day'))!=DAY or cur<floor:raise RuntimeError('tariff_ledger_drift')
   self.tariff=cur
  else:
   save(self.tariff_path,{'provider_day':DAY,'owner_daily_limit':LIMIT,'tariff_search_units':floor,'reconciled_floor':floor,'reconciled_physical_attempts':physical,'reconciled_actions':breakdown,'source_operations':ops,'accounting_definition':'start+continue+flights only'});self.tariff=floor
  save(opdir/'tariff-reconciliation.json',{'provider_day':DAY,'reconciled_floor':floor,'ledger_opening':self.tariff,'physical_attempts_seen':physical,'actions':breakdown,'operations':ops})
  cp=subprocess.run(['php','-r','require $argv[1]; fwrite(STDOUT,(string)(defined("TOURVISOR_JWT")?TOURVISOR_JWT:getenv("TOURVISOR_JWT")));','--',str(root/'config.php')],capture_output=True,check=True);self.token=cp.stdout.decode().strip()
  if not self.token:raise RuntimeError('token_invalid')
  self.open=urllib.request.build_opener(NR())
 def charge_tariff(self,action):
  if action not in TARIFF:return
  with open(self.tariff_path,'r+b') as f:
   fcntl.flock(f,fcntl.LOCK_EX);x=json.load(f);cur=int(x['tariff_search_units'])
   if cur>=LIMIT:raise RuntimeError('tariff_daily_limit')
   x['tariff_search_units']=cur+1;x.setdefault('operations',{}).setdefault(OP,{})[action]=int(x.get('operations',{}).get(OP,{}).get(action,0))+1
   b=enc(x);f.seek(0);f.truncate(0);f.write(b);f.flush();os.fsync(f.fileno());self.tariff=cur+1
 def call(self,action,path,params):
  if action not in TARIFF|UNMETERED:raise RuntimeError('action_guard')
  if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()!=DAY:raise RuntimeError('provider_day_changed')
  wait=.25-(time.monotonic()-self.last)
  if wait>0:time.sleep(wait)
  self.charge_tariff(action);self.physical+=1;self.actions[action]+=1
  pairs=[(k,str(z).lower() if isinstance(z,bool) else str(z)) for k,v in params.items() for z in (v if isinstance(v,list) else [v])]
  save(self.dir/f'request-{self.physical:04d}.json',{'seq':self.physical,'action':action,'path':path,'params':params,'tariff_unit':action in TARIFF,'tariff_after':self.tariff})
  req=urllib.request.Request(BASE+path+('?' + urllib.parse.urlencode(pairs) if pairs else ''),headers={'Authorization':'Bearer '+self.token,'Accept':'application/json'});self.last=time.monotonic()
  try:r=self.open.open(req,timeout=65)
  except urllib.error.HTTPError as e:r=e
  with r:code=r.code;raw=r.read(BODY+1)
  if len(raw)>BODY:raise RuntimeError('body_limit')
  sha=rawsave(self.dir/f'raw-response-{self.physical:04d}.bin',raw);save(self.dir/f'response-{self.physical:04d}-meta.json',{'seq':self.physical,'http_status':code,'raw_sha256':sha,'bytes':len(raw)})
  if code in (401,403,429) or code>=500:raise RuntimeError('http_'+str(code))
  if code not in (200,404):raise RuntimeError('http_'+str(code))
  try:return code,json.loads(raw)
  except Exception:raise RuntimeError('invalid_json')
