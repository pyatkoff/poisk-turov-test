#!/usr/bin/env python3
"""One owner-budgeted root-TV search through the existing configured account."""
import datetime as dt
import hashlib
import json
import os
import pathlib
import re
import shlex
import sys
from zoneinfo import ZoneInfo

from search3_root_gateway_install import checked_api, need, run
from search3_root_gateway_incident import ssh_options

REPO = 'pyatkoff/poisk-turov-test'
WORKFLOW = '.github/workflows/search3-root-gateway-install.yml'
PREFIX = '/check-search3-root-tv'
OPERATION = 'tv-20261019-anex13-v1'
APPROVAL_HASH = 'b15e80fd871c243569840ba88f45a4a25194bd3a0b5052113fcb1fba54aefe35'
RELEASE = '34bb747dcd1ed625590a60de17112cfd8ab0b28b'
FRONTEND = '05c292249d6e1d027ada3a69c1bc78af9c3ae8a9'
API_BLOB = '6fb963e68a99945118d04f8e46845ecd6bcb6bc9'
API_HASH = 'ef60da2d948c42b7741771ef1e1a091280c14cd79eafea10d737cb7067e55463'
APP_HASH = '1772a1216ca111c1a1a9a2a5cbed0c14dc589674fe5d2bd0016171c2eb2a2f4b'
PARAMS = {'departureId': 1, 'countryId': 4, 'dateFrom': '2026-11-02',
          'dateTo': '2026-11-08', 'nightsFrom': 7, 'nightsTo': 7,
          'adults': 2, 'operatorIds': 13, 'currency': 'RUB'}
ANEX_PROFILE = {'prefix': '/check-search3-tv-anex-key', 'operation': 'tv-anex-key-20261019-v1',
                'account_env': 'TOURVISOR_ANEX_JWT', 'approval_reference': 6262286069,
                'approval_hash': '55c4cdfbab2a3c345684fff863a8f9d0d9e10a6eb2a1fced34aa129f92b0a6c7',
                'budget_prefix': 'SEARCH3_TV_ANEX_KEY_BUDGET_V1\n', 'direct': True}


def validate_event(event, env, profile=None):
    profile = profile or {}
    prefix, operation = profile.get('prefix', PREFIX), profile.get('operation', OPERATION)
    need(env.get('GITHUB_REPOSITORY') == REPO and event.get('repository', {}).get('full_name') == REPO, 'repository')
    need(env.get('GITHUB_REF') == 'refs/heads/main', 'main_only')
    need(env.get('GITHUB_WORKFLOW_REF') == f'{REPO}/{WORKFLOW}@refs/heads/main', 'workflow')
    need(env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff', 'actor')
    need(env.get('GITHUB_EVENT_NAME') == 'issue_comment' and event.get('action') == 'created', 'event')
    need(env.get('GITHUB_RUN_ATTEMPT') == '1', 'run_attempt')
    issue, comment = event.get('issue', {}), event.get('comment', {})
    need(issue.get('number') == 4217 and 'pull_request' not in issue, 'issue')
    need(comment.get('user', {}).get('id') == 226193297 and comment.get('author_association') == 'OWNER', 'owner')
    control, run_id = env.get('GITHUB_SHA', ''), env.get('GITHUB_RUN_ID', '')
    need(re.fullmatch(r'[a-f0-9]{40}', control) and re.fullmatch(r'[1-9][0-9]{0,19}', run_id), 'identity')
    need(type(comment.get('id')) is int and comment['id'] > 0, 'comment_id')
    match = re.fullmatch(re.escape(f'{prefix} {control} {operation} ') + r'([1-9][0-9]{0,19})', comment.get('body', ''))
    need(match, 'command')
    return {'control_source_sha': control, 'run_id': run_id,
            'command_comment_id': comment['id'], 'budget_comment_id': int(match[1])}


def budget_note(comment, now, profile=None):
    """The owner's single-operation allocation is not a global balance assertion."""
    need(comment.get('user', {}).get('id') == 226193297 and comment.get('author_association') == 'OWNER', 'budget_owner')
    profile = profile or {}
    prefix = profile.get('budget_prefix', 'SEARCH3_ROOT_TV_BUDGET_V1\n')
    need(isinstance(comment.get('body'), str) and comment['body'].startswith(prefix), 'root_account_budget_unproven')
    value = json.loads(comment['body'][len(prefix):])
    fields = {'schema_version', 'operation_id', 'account_env', 'provider_day',
              'source', 'approval_reference', 'reserved_search_units', 'max_http_attempts'}
    need(isinstance(value, dict) and set(value) == fields, 'budget_fields')
    need(type(value['schema_version']) is int and value['schema_version'] == 1, 'budget_schema')
    need(value['operation_id'] == profile.get('operation', OPERATION)
         and value['account_env'] == profile.get('account_env', 'TOURVISOR_JWT'), 'budget_account')
    need(value['source'] == 'owner_explicit_operation_budget', 'budget_source')
    need(type(value['approval_reference']) is int and value['approval_reference'] == profile.get('approval_reference', 6261576536), 'budget_approval')
    need(value['provider_day'] == now.astimezone(ZoneInfo('Europe/Moscow')).date().isoformat() == '2026-10-19', 'budget_day')
    need(type(value['reserved_search_units']) is int and value['reserved_search_units'] == 1, 'budget_reservation')
    need(type(value['max_http_attempts']) is int and value['max_http_attempts'] == 3, 'budget_http')
    return value


def admission(identity, profile=None):
    profile = profile or {}
    prefix, operation = profile.get('prefix', PREFIX), profile.get('operation', OPERATION)
    need(checked_api('/git/ref/heads/main')['object']['sha'] == identity['control_source_sha'], 'main_moved')
    need(checked_api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha'] == RELEASE, 'release_moved')
    need(checked_api('/contents/v2/api-v2.php?ref=' + RELEASE)['sha'] == API_BLOB, 'api_source_changed')
    approval = checked_api('/issues/comments/' + str(profile.get('approval_reference', 6261576536)))
    need(approval.get('user', {}).get('id') == 226193297 and approval.get('author_association') == 'OWNER'
         and hashlib.sha256(approval.get('body', '').encode()).hexdigest() == profile.get('approval_hash', APPROVAL_HASH), 'approval_changed')
    control = checked_api('/git/commits/' + identity['control_source_sha'])
    need(len(control['parents']) == 2, 'reviewed_merge_required')
    source = checked_api('/git/commits/' + control['parents'][1]['sha'])
    need(source['tree']['sha'] == control['tree']['sha'], 'reviewed_tree')
    checks = checked_api('/commits/' + source['sha'] + '/check-runs?per_page=100')
    for name in ('guard', 'contracts', 'live-check-contracts'):
        matches = [c for c in checks.get('check_runs', []) if c.get('name') == name]
        need(len(matches) == 1 and matches[0].get('status') == 'completed'
             and matches[0].get('conclusion') == 'success'
             and matches[0].get('app', {}).get('slug') == 'github-actions', 'source_ci')
    matched = []
    for page in range(1, 31):
        rows = checked_api(f'/issues/4217/comments?per_page=100&page={page}')
        need(isinstance(rows, list), 'history_shape')
        matched.extend(c['id'] for c in rows if c.get('body', '').strip().startswith(prefix + ' '))
        if len(rows) < 100:
            need(matched == [identity['command_comment_id']], 'operation_no_replay')
            break
    else:
        raise RuntimeError('history_incomplete')
    fresh = checked_api('/issues/comments/' + str(identity['command_comment_id']))
    need(fresh.get('body') == f"{prefix} {identity['control_source_sha']} {operation} {identity['budget_comment_id']}", 'command_changed')
    note = checked_api('/issues/comments/' + str(identity['budget_comment_id']))
    need(note.get('issue_url', '').endswith('/issues/4217'), 'budget_issue')
    return budget_note(note, dt.datetime.now(dt.timezone.utc), profile) if profile else budget_note(note, dt.datetime.now(dt.timezone.utc))


REMOTE = r'''
import datetime as dt,fcntl,hashlib,json,os,pathlib,re,stat,subprocess,sys,time,urllib.error,urllib.parse,urllib.request
from zoneinfo import ZoneInfo
direct=POLICY.get('direct') is True
token=None;quota_day=None;quota_op=None;owns_quota_op=False
def need(ok,why):
 if not ok:raise RuntimeError(why)
def read_file(path):
 need(path.resolve()==path,'unsafe_parent')
 before=path.lstat();need(stat.S_ISREG(before.st_mode) and before.st_nlink==1,'unsafe_file')
 fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW)
 with os.fdopen(fd,'rb') as f:
  opened=os.fstat(f.fileno());need((opened.st_dev,opened.st_ino)==(before.st_dev,before.st_ino),'file_changed')
  body=f.read(2*1024*1024+1);after=os.fstat(f.fileno())
 need(len(body)<=2*1024*1024 and (before.st_dev,before.st_ino,before.st_size,before.st_mtime_ns,before.st_ctime_ns)==(after.st_dev,after.st_ino,after.st_size,after.st_mtime_ns,after.st_ctime_ns),'file_changed')
 return hashlib.sha256(body).hexdigest()
def private_write(path,value):
 fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
 with os.fdopen(fd,'w') as f:os.fchmod(f.fileno(),0o600);json.dump(value,f,sort_keys=True);f.flush();os.fsync(f.fileno())
def quota_open(path):
 need(path.resolve()==path,'unsafe_quota')
 before=path.lstat();need(stat.S_ISREG(before.st_mode) and before.st_nlink==1 and before.st_uid==os.getuid(),'unsafe_quota')
 fd=os.open(path,os.O_RDWR|os.O_NOFOLLOW);f=os.fdopen(fd,'r+b')
 opened=os.fstat(f.fileno())
 if (opened.st_dev,opened.st_ino)!=(before.st_dev,before.st_ino):f.close();raise RuntimeError('unsafe_quota')
 return f
def quota_read(f):
 f.seek(0);raw=f.read(2*1024*1024+1);need(len(raw)<=2*1024*1024,'quota_shape')
 value=json.loads(raw);need(isinstance(value,dict),'quota_shape');return value
def quota_write(f,value):
 body=json.dumps(value,sort_keys=True).encode();need(len(body)<=2*1024*1024,'quota_shape')
 f.seek(0);f.write(body);f.truncate();f.flush();os.fsync(f.fileno())
def quota_validate(value):
 need(value.get('provider')=='tourvisor-anex' and value.get('provider_day')=='2026-10-19'
      and type(value.get('owner_daily_limit')) is int and value['owner_daily_limit']==3000,'quota_shape')
 for k in ('tariff_search_units','physical_http_attempts'):
  need(type(value.get(k)) is int and value[k]>=0,'quota_shape')
 need(isinstance(value.get('operations'),dict),'quota_shape')
def quota_prepare(home):
 global quota_day,quota_op,owns_quota_op
 base=home/'.anytoour-match'
 for p in (base,base/'provider-quotas'):
  need(p.parent.resolve()==p.parent,'unsafe_quota')
  try:p.mkdir(mode=0o700)
  except FileExistsError:pass
  s=p.lstat();need(p.resolve()==p and stat.S_ISDIR(s.st_mode) and s.st_uid==os.getuid() and not(s.st_mode&0o022),'unsafe_quota')
 q=base/'provider-quotas';quota_day=q/'tourvisor-anex-2026-10-19.json';quota_op=q/('tourvisor-anex-'+POLICY['operation']+'.json')
 try:private_write(quota_day,{'provider':'tourvisor-anex','provider_day':'2026-10-19','owner_daily_limit':3000,
       'tariff_search_units':0,'physical_http_attempts':0,'baseline_status':'local_ledger_only_global_usage_not_measured','operations':{}})
 except FileExistsError:pass
 with quota_open(quota_day) as f:
  fcntl.flock(f,fcntl.LOCK_EX);d=quota_read(f);quota_validate(d)
  need(d['tariff_search_units']<3000,'quota_exhausted')
  need(POLICY['operation'] not in d['operations'],'quota_operation_exists')
  private_write(quota_op,{'operation':POLICY['operation'],'status':'reserved_before_provider_access','used':0,'tariff_used':0,
                'operation_cap':3,'provider_day':'2026-10-19','account':'TOURVISOR_ANEX_JWT'})
  owns_quota_op=True
  result['known_accounted_units_before']=d['tariff_search_units']
def quota_charge(action):
 need(dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat()=='2026-10-19','budget_day')
 with quota_open(quota_day) as df,quota_open(quota_op) as of:
  fcntl.flock(df,fcntl.LOCK_EX);fcntl.flock(of,fcntl.LOCK_EX);d,o=quota_read(df),quota_read(of);quota_validate(d)
  need(o.get('operation')==POLICY['operation'] and o.get('account')=='TOURVISOR_ANEX_JWT' and o.get('provider_day')=='2026-10-19'
       and type(o.get('used')) is int and type(o.get('tariff_used')) is int,'quota_shape')
  used,charged=o['used'],o['tariff_used'];need(0<=used<3 and 0<=charged<=1,'quota_exhausted')
  need(action==('search_start','search_status','search_results')[used],'request_cap')
  tariff=1 if action=='search_start' else 0
  need(d['tariff_search_units']+tariff<=3000 and charged+tariff<=1,'quota_exhausted')
  previous=d['operations'].get(POLICY['operation'])
  need(previous==({'physical_http_attempts':used,'tariff_search_units':charged} if used else None),'quota_shape')
  d['physical_http_attempts']+=1;d['tariff_search_units']+=tariff
  d['operations'][POLICY['operation']]={'physical_http_attempts':used+1,'tariff_search_units':charged+tariff}
  o.update(status='provider_accessed',used=used+1,tariff_used=charged+tariff,last_action=action)
  quota_write(df,d);quota_write(of,o)
  result.update(known_accounted_units_after=d['tariff_search_units'],operation_physical_attempts=used+1,operation_tariff_units=charged+tariff,live_operation_started=True)
def quota_finish():
 if not owns_quota_op:return
 with quota_open(quota_op) as f:
  fcntl.flock(f,fcntl.LOCK_EX);o=quota_read(f)
  need(o.get('operation')==POLICY['operation'],'quota_shape')
  o.update(status=result['status'],result_sha256=hashlib.sha256(json.dumps(result,sort_keys=True).encode()).hexdigest());quota_write(f,o)
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
def request(action,params):
 need(action in ('search_start','search_status','search_results') and len(result['requests'])<3,'request_cap')
 entry={'action':action,'http':None,'bytes':0,'json_kind':'not_measured'}
 result['requests'].append(entry) # reserve the physical attempt BEFORE sending
 if direct:
  quota_charge(action)
  if action=='search_start':path='/tours/search';query=urllib.parse.urlencode({**params,'onlyCharter':'false','onlyDirect':'false'})
  else:
   sid=str(params['searchId']);need(re.fullmatch(r'[1-9][0-9]{0,18}',sid) and sid not in ('13905446138','13943172077'),'historical_search_identity')
   path='/tours/search/'+sid+('/status' if action=='search_status' else '')
   query=urllib.parse.urlencode({'operatorStatus':'false'} if action=='search_status' else {'limit':25})
  url='https://api.tourvisor.ru/search/api/v1'+path+'?'+query
  req=urllib.request.Request(url,headers={'Authorization':'Bearer '+token,'Accept':'application/json'})
 else:
  query=urllib.parse.urlencode({'action':action,**params})
  req=urllib.request.Request('https://anytoour.ru/api-v2.php?'+query,headers={'Accept':'application/json','User-Agent':'AnyTour-owner-bounded-root-check'})
 try:
  response=urllib.request.build_opener(NoRedirect).open(req,timeout=55)
 except urllib.error.HTTPError as e:
  if not direct:entry['http']=e.code;raise RuntimeError('http_failed') from None
  response=e
 with response as response:
  entry['http']=response.status
  maximum=65536 if direct and entry['http']!=200 else 8*1024*1024
  body=response.read(maximum+1)
 entry['bytes']=len(body);entry['sha256']=hashlib.sha256(body).hexdigest()
 if direct:
  entry['body_truncated']=len(body)>maximum
  entry['hash_scope']='bounded_prefix' if len(body)>maximum else 'complete_body'
  try:entry['json_kind']=type(json.loads(body)).__name__ if len(body)<=maximum else 'not_measured'
  except (ValueError,UnicodeError):entry['json_kind']='invalid_json'
  if entry['http']!=200:
   entry['error_category']='auth_denied' if entry['http'] in (401,403) else 'rate_limited' if entry['http']==429 else 'upstream_server_error' if 500<=entry['http']<=599 else 'unexpected_http'
   raise RuntimeError('http_failed')
 need(entry['http']==200 and len(body)<=8*1024*1024,'http_or_body_limit')
 value=json.loads(body);entry['json_kind']=type(value).__name__
 return value
result={'operation_id':POLICY['operation'],'status':'blocked_no_replay','live_operation_started':False,
        'requests':[],'tariff_searches_reserved':0,'real_leads':0,'application_file_writes':0,
        'normal_autosave_enabled':True,'database_write_count':'not_measured',
        'global_daily_usage':'not_measured','budget_basis':'owner_operation_allocation'}
if direct:result.update(account_env='TOURVISOR_ANEX_JWT',route='direct_tourvisor_api',normal_autosave_enabled=False,database_write_count=0)
op=None;reserved=False
try:
 raw=sys.stdin.buffer.read(16385);need(len(raw)<=16384,'payload_limit');payload=json.loads(raw)
 need(isinstance(payload,dict) and set(payload)=={'control_source_sha','run_id','command_comment_id','budget_comment_id','budget'},'payload_fields')
 need(re.fullmatch(r'[a-f0-9]{40}',str(payload['control_source_sha'])) and re.fullmatch(r'[1-9][0-9]{0,19}',str(payload['run_id'])),'payload_identity')
 need(all(type(payload[k]) is int and payload[k]>0 for k in ('command_comment_id','budget_comment_id')),'payload_identity')
 for k in ('control_source_sha','run_id','command_comment_id','budget_comment_id'):result[k]=payload[k]
 b=payload['budget'];now=dt.datetime.now(dt.timezone.utc)
 need(isinstance(b,dict) and set(b)=={'schema_version','operation_id','account_env','provider_day','source','approval_reference','reserved_search_units','max_http_attempts'} and type(b['schema_version']) is int and b['schema_version']==1,'budget_fields')
 need(b['account_env']==POLICY.get('account_env','TOURVISOR_JWT') and b['operation_id']==POLICY['operation'] and b['source']=='owner_explicit_operation_budget','root_account_budget_unproven')
 need(type(b['approval_reference']) is int and b['approval_reference']==POLICY.get('approval_reference',6261576536),'budget_approval')
 need(b['provider_day']==now.astimezone(ZoneInfo('Europe/Moscow')).date().isoformat()=='2026-10-19','budget_day')
 need(type(b['reserved_search_units']) is int and b['reserved_search_units']==1 and type(b['max_http_attempts']) is int and b['max_http_attempts']==3,'budget_reservation')
 home=pathlib.Path.home();project=home/'www/anytoour.ru'
 need(home.resolve()==home and project.resolve()==project,'unsafe_parent')
 api_before=read_file(project/'api-v2.php');need(api_before==POLICY['api_hash'],'api_drift')
 # Compiled frontend remains bound to the exact already-published artifact.
 app=project/'_preview/search3-next-candidate/visual-search/app.js'
 app_before=read_file(app);need(app_before==POLICY['app_hash'],'frontend_drift')
 result['before_files']={'api-v2.php':api_before,'frontend_app':app_before}
 # Credentials stay inside the remote process; no token enters a retained receipt.
 # Equal known subjects also stop: different JWT strings can be rotations of one account.
 config=project/'config.php';need(config.resolve()==config and stat.S_ISREG(config.lstat().st_mode),'unsafe_parent')
 code='require $argv[1];function keyvalue($k){$v=trim((string)getenv($k));return $v!==""?$v:(defined($k)?trim((string)constant($k)):"");}function subject($v){$p=explode(".",$v);if(count($p)!==3)return null;$b=strtr($p[1],"-_","+/");$a=json_decode(base64_decode($b),true);return is_array($a)&&isset($a["sub"])?json_encode([$a["iss"]??"",$a["sub"]]):null;}$a=keyvalue("TOURVISOR_JWT");$b=keyvalue("TOURVISOR_ANEX_JWT");$sa=subject($a);$sb=subject($b);fwrite(STDOUT,json_encode(["root_configured"=>$a!=="","anex_configured"=>$b!=="","same_token"=>$a!==""&&hash_equals($a,$b),"same_subject"=>$sa!==null&&$sb!==null&&hash_equals($sa,$sb)]));'
 if direct:
  # Match the existing second-key owner's constant-first credential precedence.
  code=code.replace('$b=keyvalue("TOURVISOR_ANEX_JWT");','$b=defined("TOURVISOR_ANEX_JWT")?trim((string)constant("TOURVISOR_ANEX_JWT")):trim((string)getenv("TOURVISOR_ANEX_JWT"));')
  code=code.replace('fwrite(STDOUT,json_encode(["root_configured"=>$a!=="","anex_configured"=>$b!=="","same_token"=>$a!==""&&hash_equals($a,$b),"same_subject"=>$sa!==null&&$sb!==null&&hash_equals($sa,$sb)]));','fwrite(STDOUT,json_encode(["token"=>$b,"root_equal"=>$a!==""&&hash_equals($a,$b),"same_subject"=>$sa!==null&&$sb!==null&&hash_equals($sa,$sb)]));')
 cp=subprocess.run(['php','-r',code,'--',str(config)],capture_output=True,timeout=15,check=False)
 need(cp.returncode==0 and len(cp.stdout)<=(16384 if direct else 256),'root_account_binding_unproven')
 account=json.loads(cp.stdout)
 if direct:
  need(isinstance(account,dict) and set(account)=={'token','root_equal','same_subject'}
       and type(account['root_equal']) is bool and type(account['same_subject']) is bool,'root_account_binding_unproven')
  token=account['token'];need(isinstance(token,str) and 0<len(token)<=8192 and token.isascii() and not any(c.isspace() or ord(c)<33 or ord(c)==127 for c in token),'root_account_binding_unproven')
  need(not account['root_equal'] and not account['same_subject'],'shared_account_budget_unproven')
  result.update(account_env='TOURVISOR_ANEX_JWT',route='direct_tourvisor_api',normal_autosave_enabled=False,database_write_count=0)
 else:
  need(isinstance(account,dict) and set(account)=={'root_configured','anex_configured','same_token','same_subject'} and all(type(v) is bool for v in account.values()),'root_account_binding_unproven')
  need(account['root_configured'] and account['anex_configured'],'root_account_binding_unproven')
  need(not account['same_token'] and not account['same_subject'],'shared_account_budget_unproven')
 result['configured_account_guard']='distinct_configured_tokens_no_known_shared_subject'
 base=home/'.anytoour-search3-root-gateway'
 info=base.lstat();need(stat.S_ISDIR(info.st_mode) and stat.S_IMODE(info.st_mode)==0o700 and info.st_uid==os.getuid(),'unsafe_private_parent')
 op=base/POLICY['operation'];op.mkdir(mode=0o700);reserved=True
 private_write(op/'reservation.json',payload)
 result['tariff_searches_reserved']=1;result['budget']=b
 if direct:quota_prepare(home)
 else:result['live_operation_started']=True
 result['status']='unknown_no_replay'
 start=request('search_start',POLICY['params'])
 need(isinstance(start,dict),'start_shape')
 data=start.get('data',start);need(isinstance(data,dict),'start_shape')
 sid=data.get('searchId',data.get('id'))
 need(type(sid) is int and 0<sid<10**19 or isinstance(sid,str) and re.fullmatch(r'[1-9][0-9]{0,18}',sid),'search_identity')
 sid=str(sid);need(sid not in (('13905446138','13943172077') if direct else ('13905446138',)),'historical_search_identity')
 result['search_id']=sid;result['generation_id']=POLICY['operation']
 time.sleep(20)
 status=request('search_status',{'searchId':sid})
 need(isinstance(status,dict) and str(status.get('searchId'))==sid and not status.get('error'),'status_shape')
 progress=status.get('progress')
 need(type(progress) in (int,float) and 0<=progress<=100,'status_shape')
 result['progress']=progress
 values=request('search_results',{'searchId':sid,'limit':25});need(isinstance(values,list),'results_shape')
 need(len(values)<=25,'hotel_limit')
 count=0;samples=[]
 for h in values:
  need(isinstance(h,dict) and type(h.get('id')) is int and h['id']>0 and isinstance(h.get('tours'),list),'results_shape')
  for t in h['tours']:
   need(isinstance(t,dict) and isinstance(t.get('operator'),dict) and t['operator'].get('id')==13,'result_scope')
   need(t.get('currency')=='RUB' and t.get('adults')==2 and type(t.get('childs')) is int and t['childs']==0 and t.get('nights')==7,'result_scope')
   date=t.get('date');need(isinstance(date,str) and POLICY['params']['dateFrom']<=date[:10]<=POLICY['params']['dateTo'],'result_scope')
   need(isinstance(t.get('id'),(str,int)) and not isinstance(t['id'],bool) and 0<len(str(t['id']))<=200,'result_scope')
   price=t.get('price');need(type(price) in (int,float) and 0<price<10**10,'result_scope')
   count+=1
   if len(samples)<3:samples.append({'hotel_id':h['id'],'tour_id_sha256':hashlib.sha256(str(t['id']).encode()).hexdigest(),'date':date[:10],'nights':7,'adults':2,'children':0,'operator_id':13,'currency':'RUB','reported_price':price})
 result['hotel_groups']=len(values);result['tour_count']=count;result['samples']=samples
 result['after_files']={'api-v2.php':read_file(project/'api-v2.php'),'frontend_app':read_file(app)}
 need(result['after_files']==result['before_files'],'installed_drift')
 result['status']='fresh_partial_results_verified' if direct and progress<100 else 'fresh_populated_results_verified' if count else 'fresh_empty_results_verified'
except Exception as e:
 reasons={'unsafe_file','file_changed','request_cap','http_failed','http_or_body_limit','payload_limit','payload_fields','payload_identity','budget_fields','root_account_budget_unproven','budget_approval','budget_day','budget_reservation','root_account_binding_unproven','shared_account_budget_unproven','unsafe_parent','api_drift','frontend_drift','unsafe_private_parent','start_shape','search_identity','historical_search_identity','status_shape','results_shape','result_scope','hotel_limit','installed_drift','unsafe_quota','quota_shape','quota_exhausted','quota_operation_exists'}
 result['reason']=str(e) if type(e) is RuntimeError and str(e) in reasons else 'operation_failed'
 result['error_type']=type(e).__name__
finally:
 if direct and 'before_files' in result and 'after_files' not in result:
  try:
   result['after_files']={'api-v2.php':read_file(project/'api-v2.php'),'frontend_app':read_file(app)}
   if result['after_files']!=result['before_files']:result.update(status='unknown_no_replay',reason='installed_drift')
  except Exception:result['after_read_error']='not_verified'
 if direct:
  try:quota_finish()
  except Exception:result.update(status='unknown_no_replay',reason='quota_receipt_failed')
 if reserved:
  try:private_write(op/'result.json',result)
  except Exception:result.update(status='unknown_no_replay',reason='receipt_write_failed')
 print(json.dumps(result,sort_keys=True))
'''


def main(profile=None):
    profile = profile or {}
    operation = profile.get('operation', OPERATION)
    work = pathlib.Path(os.environ['RUNNER_TEMP']) / ('search3-tv-anex-key-check' if profile else 'search3-root-tv-check')
    work.mkdir()
    key, known = work / 'key', work / 'known'
    evidence = {'operation_id': operation, 'status': 'blocked_no_replay', 'live_operation_started': False,
                'release_sha': RELEASE, 'frontend_source_sha': FRONTEND, 'api_sha256': API_HASH,
                'frontend_sha256': APP_HASH}
    try:
        event = json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text())
        identity = validate_event(event, os.environ, profile) if profile else validate_event(event, os.environ)
        evidence.update(identity)
        budget = admission(identity, profile) if profile else admission(identity)
        payload = {**identity, 'budget': budget}
        (work / 'request.json').write_text(json.dumps(payload, sort_keys=True))
        policy = {'operation': operation, 'api_hash': API_HASH, 'app_hash': APP_HASH, 'params': PARAMS}
        if profile:
            policy.update(direct=True, account_env=profile['account_env'], approval_reference=profile['approval_reference'])
            evidence.update(account_env=profile['account_env'], route='direct_tourvisor_api')
        program = 'POLICY=' + repr(policy) + '\n' + REMOTE
        key, known, opts, remote = ssh_options(work)
        evidence['status'] = 'unknown_no_replay'
        raw = run(['ssh', *opts, remote, 'python3 -c ' + shlex.quote(program)],
                  input=json.dumps(payload).encode(), timeout=220)
        need(len(raw) <= 16384, 'receipt_limit')
        result = json.loads(raw)
        allowed = {'operation_id', 'status', 'live_operation_started', 'requests', 'tariff_searches_reserved',
                   'real_leads', 'application_file_writes', 'normal_autosave_enabled', 'database_write_count',
                   'global_daily_usage', 'budget_basis', 'configured_account_guard',
                   *identity, 'budget', 'search_id', 'generation_id', 'hotel_groups', 'tour_count', 'progress', 'samples', 'before_files', 'after_files', 'reason', 'error_type'}
        if profile:
            allowed.update(('account_env','route','known_accounted_units_before','known_accounted_units_after',
                            'operation_physical_attempts','operation_tariff_units','after_read_error'))
        need(isinstance(result, dict) and set(result) <= allowed and result.get('operation_id') == operation, 'receipt_shape')
        need(all(result.get(k) == v for k, v in identity.items()), 'receipt_identity')
        need(result.get('real_leads') == 0 and result.get('application_file_writes') == 0, 'receipt_scope')
        requests = result.get('requests', [])
        need(isinstance(requests, list) and len(requests) <= 3, 'receipt_requests')
        for i, request in enumerate(requests):
            fields = {'action', 'http', 'bytes', 'json_kind', 'sha256'}
            if profile:
                fields.update(('body_truncated','hash_scope','error_category'))
            need(set(request) <= fields
                 and request.get('action') == ('search_start', 'search_status', 'search_results')[i], 'receipt_requests')
            if profile:
                need(type(request.get('bytes')) is int and 0<=request['bytes']<=8*1024*1024+1, 'receipt_requests')
                need(request.get('http') is None or type(request['http']) is int and 100<=request['http']<=599,'receipt_requests')
                need(request.get('json_kind') in ('not_measured','dict','list','str','int','float','bool','NoneType','invalid_json'),'receipt_requests')
                if 'sha256' in request:need(re.fullmatch(r'[a-f0-9]{64}',request['sha256']) is not None,'receipt_requests')
                if 'body_truncated' in request:need(type(request['body_truncated']) is bool,'receipt_requests')
                if 'hash_scope' in request:need(request['hash_scope'] in ('bounded_prefix','complete_body'),'receipt_requests')
                if 'error_category' in request:need(request['error_category'] in ('auth_denied','rate_limited','upstream_server_error','unexpected_http'),'receipt_requests')
        if profile:
            need(result.get('account_env') in (None,'TOURVISOR_ANEX_JWT') and result.get('route') in (None,'direct_tourvisor_api'),'receipt_scope')
            need(result.get('normal_autosave_enabled') is False and type(result.get('database_write_count')) is int and result['database_write_count']==0,'receipt_scope')
            for field, maximum in (('known_accounted_units_before',3000),('known_accounted_units_after',3000),('operation_physical_attempts',3),('operation_tariff_units',1)):
                if field in result:need(type(result[field]) is int and 0<=result[field]<=maximum,'receipt_scope')
        evidence.update(status=result.get('status'), live_operation_started=result.get('live_operation_started'), remote=result)
    except Exception as exc:
        evidence['error_type'] = type(exc).__name__
        if type(exc) is RuntimeError and str(exc) in ('root_account_budget_unproven', 'budget_stale', 'budget_day', 'budget_account', 'budget_exhausted'):
            evidence['reason'] = str(exc)
    finally:
        key.unlink(missing_ok=True)
        known.unlink(missing_ok=True)
        (work / 'evidence.json').write_text(json.dumps(evidence, sort_keys=True))
    print(json.dumps(evidence, sort_keys=True))
    accepted = ('fresh_populated_results_verified', 'fresh_empty_results_verified')
    if profile:accepted += ('fresh_partial_results_verified',)
    need(evidence['status'] in accepted, 'live_check_not_verified')


if __name__ == '__main__':
    need(sys.argv[1:] in ([], ['anex-key']), 'usage')
    main(ANEX_PROFILE if sys.argv[1:] else None)

