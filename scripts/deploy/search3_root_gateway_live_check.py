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


def validate_event(event, env):
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
    match = re.fullmatch(re.escape(f'{PREFIX} {control} {OPERATION} ') + r'([1-9][0-9]{0,19})', comment.get('body', ''))
    need(match, 'command')
    return {'control_source_sha': control, 'run_id': run_id,
            'command_comment_id': comment['id'], 'budget_comment_id': int(match[1])}


def budget_note(comment, now):
    """The owner's single-operation allocation is not a global balance assertion."""
    need(comment.get('user', {}).get('id') == 226193297 and comment.get('author_association') == 'OWNER', 'budget_owner')
    prefix = 'SEARCH3_ROOT_TV_BUDGET_V1\n'
    need(isinstance(comment.get('body'), str) and comment['body'].startswith(prefix), 'root_account_budget_unproven')
    value = json.loads(comment['body'][len(prefix):])
    fields = {'schema_version', 'operation_id', 'account_env', 'provider_day',
              'source', 'approval_reference', 'reserved_search_units', 'max_http_attempts'}
    need(isinstance(value, dict) and set(value) == fields, 'budget_fields')
    need(type(value['schema_version']) is int and value['schema_version'] == 1, 'budget_schema')
    need(value['operation_id'] == OPERATION and value['account_env'] == 'TOURVISOR_JWT', 'budget_account')
    need(value['source'] == 'owner_explicit_operation_budget', 'budget_source')
    need(type(value['approval_reference']) is int and value['approval_reference'] == 6261576536, 'budget_approval')
    need(value['provider_day'] == now.astimezone(ZoneInfo('Europe/Moscow')).date().isoformat() == '2026-10-19', 'budget_day')
    need(type(value['reserved_search_units']) is int and value['reserved_search_units'] == 1, 'budget_reservation')
    need(type(value['max_http_attempts']) is int and value['max_http_attempts'] == 3, 'budget_http')
    return value


def admission(identity):
    need(checked_api('/git/ref/heads/main')['object']['sha'] == identity['control_source_sha'], 'main_moved')
    need(checked_api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha'] == RELEASE, 'release_moved')
    need(checked_api('/contents/v2/api-v2.php?ref=' + RELEASE)['sha'] == API_BLOB, 'api_source_changed')
    approval = checked_api('/issues/comments/6261576536')
    need(approval.get('user', {}).get('id') == 226193297 and approval.get('author_association') == 'OWNER'
         and hashlib.sha256(approval.get('body', '').encode()).hexdigest() == APPROVAL_HASH, 'approval_changed')
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
        matched.extend(c['id'] for c in rows if c.get('body', '').strip().startswith(PREFIX + ' '))
        if len(rows) < 100:
            need(matched == [identity['command_comment_id']], 'operation_no_replay')
            break
    else:
        raise RuntimeError('history_incomplete')
    fresh = checked_api('/issues/comments/' + str(identity['command_comment_id']))
    need(fresh.get('body') == f"{PREFIX} {identity['control_source_sha']} {OPERATION} {identity['budget_comment_id']}", 'command_changed')
    note = checked_api('/issues/comments/' + str(identity['budget_comment_id']))
    need(note.get('issue_url', '').endswith('/issues/4217'), 'budget_issue')
    return budget_note(note, dt.datetime.now(dt.timezone.utc))


REMOTE = r'''
import datetime as dt,hashlib,json,os,pathlib,re,stat,subprocess,sys,time,urllib.error,urllib.parse,urllib.request
from zoneinfo import ZoneInfo
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
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None
def request(action,params):
 need(action in ('search_start','search_status','search_results') and len(result['requests'])<3,'request_cap')
 entry={'action':action,'http':None,'bytes':0,'json_kind':'not_measured'}
 result['requests'].append(entry) # reserve the physical attempt BEFORE sending
 query=urllib.parse.urlencode({'action':action,**params})
 req=urllib.request.Request('https://anytoour.ru/api-v2.php?'+query,headers={'Accept':'application/json','User-Agent':'AnyTour-owner-bounded-root-check'})
 try:
  response=urllib.request.build_opener(NoRedirect).open(req,timeout=55)
 except urllib.error.HTTPError as e:
  entry['http']=e.code;raise RuntimeError('http_failed') from None
 with response as response:
  entry['http']=response.status
  body=response.read(8*1024*1024+1)
 entry['bytes']=len(body);entry['sha256']=hashlib.sha256(body).hexdigest()
 need(entry['http']==200 and len(body)<=8*1024*1024,'http_or_body_limit')
 value=json.loads(body);entry['json_kind']=type(value).__name__
 return value
result={'operation_id':POLICY['operation'],'status':'blocked_no_replay','live_operation_started':False,
        'requests':[],'tariff_searches_reserved':0,'real_leads':0,'application_file_writes':0,
        'normal_autosave_enabled':True,'database_write_count':'not_measured',
        'global_daily_usage':'not_measured','budget_basis':'owner_operation_allocation'}
op=None;reserved=False
try:
 raw=sys.stdin.buffer.read(16385);need(len(raw)<=16384,'payload_limit');payload=json.loads(raw)
 need(isinstance(payload,dict) and set(payload)=={'control_source_sha','run_id','command_comment_id','budget_comment_id','budget'},'payload_fields')
 need(re.fullmatch(r'[a-f0-9]{40}',str(payload['control_source_sha'])) and re.fullmatch(r'[1-9][0-9]{0,19}',str(payload['run_id'])),'payload_identity')
 need(all(type(payload[k]) is int and payload[k]>0 for k in ('command_comment_id','budget_comment_id')),'payload_identity')
 for k in ('control_source_sha','run_id','command_comment_id','budget_comment_id'):result[k]=payload[k]
 b=payload['budget'];now=dt.datetime.now(dt.timezone.utc)
 need(isinstance(b,dict) and set(b)=={'schema_version','operation_id','account_env','provider_day','source','approval_reference','reserved_search_units','max_http_attempts'} and type(b['schema_version']) is int and b['schema_version']==1,'budget_fields')
 need(b['account_env']=='TOURVISOR_JWT' and b['operation_id']==POLICY['operation'] and b['source']=='owner_explicit_operation_budget','root_account_budget_unproven')
 need(type(b['approval_reference']) is int and b['approval_reference']==6261576536,'budget_approval')
 need(b['provider_day']==now.astimezone(ZoneInfo('Europe/Moscow')).date().isoformat()=='2026-10-19','budget_day')
 need(type(b['reserved_search_units']) is int and b['reserved_search_units']==1 and type(b['max_http_attempts']) is int and b['max_http_attempts']==3,'budget_reservation')
 home=pathlib.Path.home();project=home/'www/anytoour.ru'
 need(home.resolve()==home and project.resolve()==project,'unsafe_parent')
 api_before=read_file(project/'api-v2.php');need(api_before==POLICY['api_hash'],'api_drift')
 # Compiled frontend remains bound to the exact already-published artifact.
 app=project/'_preview/search3-next-candidate/visual-search/app.js'
 app_before=read_file(app);need(app_before==POLICY['app_hash'],'frontend_drift')
 result['before_files']={'api-v2.php':api_before,'frontend_app':app_before}
 # Reuse the established two configured credentials internally. No token leaves PHP.
 # Equal known subjects also stop: different JWT strings can be rotations of one account.
 config=project/'config.php';need(config.resolve()==config and stat.S_ISREG(config.lstat().st_mode),'unsafe_parent')
 code='require $argv[1];function keyvalue($k){$v=trim((string)getenv($k));return $v!==""?$v:(defined($k)?trim((string)constant($k)):"");}function subject($v){$p=explode(".",$v);if(count($p)!==3)return null;$b=strtr($p[1],"-_","+/");$a=json_decode(base64_decode($b),true);return is_array($a)&&isset($a["sub"])?json_encode([$a["iss"]??"",$a["sub"]]):null;}$a=keyvalue("TOURVISOR_JWT");$b=keyvalue("TOURVISOR_ANEX_JWT");$sa=subject($a);$sb=subject($b);fwrite(STDOUT,json_encode(["root_configured"=>$a!=="","anex_configured"=>$b!=="","same_token"=>$a!==""&&hash_equals($a,$b),"same_subject"=>$sa!==null&&$sb!==null&&hash_equals($sa,$sb)]));'
 cp=subprocess.run(['php','-r',code,'--',str(config)],capture_output=True,timeout=15,check=False)
 need(cp.returncode==0 and len(cp.stdout)<=256,'root_account_binding_unproven')
 account=json.loads(cp.stdout)
 need(isinstance(account,dict) and set(account)=={'root_configured','anex_configured','same_token','same_subject'} and all(type(v) is bool for v in account.values()),'root_account_binding_unproven')
 need(account['root_configured'] and account['anex_configured'],'root_account_binding_unproven')
 need(not account['same_token'] and not account['same_subject'],'shared_account_budget_unproven')
 result['configured_account_guard']='distinct_configured_tokens_no_known_shared_subject'
 base=home/'.anytoour-search3-root-gateway'
 info=base.lstat();need(stat.S_ISDIR(info.st_mode) and stat.S_IMODE(info.st_mode)==0o700 and info.st_uid==os.getuid(),'unsafe_private_parent')
 op=base/POLICY['operation'];op.mkdir(mode=0o700);reserved=True
 private_write(op/'reservation.json',payload)
 result['tariff_searches_reserved']=1;result['budget']=b
 result['live_operation_started']=True;result['status']='unknown_no_replay'
 start=request('search_start',POLICY['params'])
 need(isinstance(start,dict),'start_shape')
 data=start.get('data',start);need(isinstance(data,dict),'start_shape')
 sid=data.get('searchId',data.get('id'))
 need(type(sid) is int and 0<sid<10**19 or isinstance(sid,str) and re.fullmatch(r'[1-9][0-9]{0,18}',sid),'search_identity')
 sid=str(sid);need(sid!='13905446138','historical_search_identity')
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
 result['status']='fresh_populated_results_verified' if count else 'fresh_empty_results_verified'
except Exception as e:
 reasons={'unsafe_file','file_changed','request_cap','http_failed','http_or_body_limit','payload_limit','payload_fields','payload_identity','budget_fields','root_account_budget_unproven','budget_approval','budget_day','budget_reservation','root_account_binding_unproven','shared_account_budget_unproven','unsafe_parent','api_drift','frontend_drift','unsafe_private_parent','start_shape','search_identity','historical_search_identity','status_shape','results_shape','result_scope','hotel_limit','installed_drift'}
 result['reason']=str(e) if type(e) is RuntimeError and str(e) in reasons else 'operation_failed'
 result['error_type']=type(e).__name__
finally:
 if reserved:
  try:private_write(op/'result.json',result)
  except Exception:result.update(status='unknown_no_replay',reason='receipt_write_failed')
 print(json.dumps(result,sort_keys=True))
'''


def main():
    work = pathlib.Path(os.environ['RUNNER_TEMP']) / 'search3-root-tv-check'
    work.mkdir()
    key, known = work / 'key', work / 'known'
    evidence = {'operation_id': OPERATION, 'status': 'blocked_no_replay', 'live_operation_started': False,
                'release_sha': RELEASE, 'frontend_source_sha': FRONTEND, 'api_sha256': API_HASH,
                'frontend_sha256': APP_HASH}
    try:
        identity = validate_event(json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text()), os.environ)
        evidence.update(identity)
        budget = admission(identity)
        payload = {**identity, 'budget': budget}
        (work / 'request.json').write_text(json.dumps(payload, sort_keys=True))
        policy = {'operation': OPERATION, 'api_hash': API_HASH, 'app_hash': APP_HASH, 'params': PARAMS}
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
        need(isinstance(result, dict) and set(result) <= allowed and result.get('operation_id') == OPERATION, 'receipt_shape')
        need(all(result.get(k) == v for k, v in identity.items()), 'receipt_identity')
        need(result.get('real_leads') == 0 and result.get('application_file_writes') == 0, 'receipt_scope')
        requests = result.get('requests', [])
        need(isinstance(requests, list) and len(requests) <= 3, 'receipt_requests')
        for i, request in enumerate(requests):
            need(set(request) <= {'action', 'http', 'bytes', 'json_kind', 'sha256'}
                 and request.get('action') == ('search_start', 'search_status', 'search_results')[i], 'receipt_requests')
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
    need(evidence['status'] in ('fresh_populated_results_verified', 'fresh_empty_results_verified'), 'live_check_not_verified')


if __name__ == '__main__':
    main()
