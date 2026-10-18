#!/usr/bin/env python3
import base64
import json
import os
import pathlib
import re
import subprocess
import sys
import urllib.request

REPO = 'pyatkoff/poisk-turov-test'
WORKFLOW = '.github/workflows/search3-root-gateway-install.yml'
ISSUE = 4217
COMMAND_PREFIX = '/preflight-search3-root-gateway'
OPERATION = 'preflight-20261018-fcfe503e-v1'
SOURCE = 'fcfe503e33f902ec19a9eff8cfc7985b1c242f04'
RELEASE = '6bb834a586c231c3f06a6d6e918f8280d2b3f587'
BLOB = '6fb963e68a99945118d04f8e46845ecd6bcb6bc9'
TARGET = 'https://anytoour.ru/_preview/search3-next-candidate/visual-search/'
MAX_COMMENT_PAGES = 30
MAX_API_BYTES = 4 * 1024 * 1024
MAX_FILE_BYTES = 2 * 1024 * 1024
MAX_EVIDENCE_BYTES = 8192
EXPECTED = {
    'api-v2.php': '445c421238c649b2dfb344f59b20c52f8be6418ded8de19b2d1c356bc710ccce',
    'data/collect-hotel-details-v1.php': 'b653b3d174b5afbd22ba73398819fabd76d6a833783323b1845d64a2eb185113',
    'data/db-v1.php': 'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4',
    'data/hotel-details-v1.php': '137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f',
    'data/local-tv-catalog-enabled.json': '3c11725b4b9507427654398aac403177f338d7618b77c96d695582b4e49d3440',
    'data/local-tv-catalog-v1.php': 'b3f4f79d1bb54d723c5e9cbaf4b6e59375e66b9551d4e602a2f9b01c44886a46',
    'data/price-observer-v1.php': '7e057905b1218815fc828c1c558f681eca67d079ad220ee3487b315661357843',
    'data/tourvisor-client-v1.php': 'f669434c2b0bf6af0a68684d02eea964173f2d3c0408e49b94db9606e8273a17',
}


def need(value, message):
    if not value:
        raise RuntimeError(message)


def legacy_inspect():
    e=json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text())
    need(e['issue']['number']==3419 and e['comment']['body'].strip()=='/inspect-search3-root-gateway','command')
    need(e['comment']['user']['id']==226193297 and e['comment']['author_association']=='OWNER','owner')
    work=pathlib.Path(os.environ['RUNNER_TEMP'])/'search3-root-gateway-inspect';work.mkdir()
    key=work/'key';known=work/'known';raw=os.environ['PREVIEW_KEY'].replace('\r','')
    if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
    key.write_text(raw.rstrip()+'\n');key.chmod(0o600)
    host=os.environ['PREVIEW_HOST'];user=os.environ['PREVIEW_USER']
    need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
    known.write_bytes(subprocess.run(['ssh-keyscan','-T','15','-t','ed25519',host],capture_output=True,check=True,timeout=30).stdout);known.chmod(0o600)
    opts=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','ConnectTimeout=15']
    q=subprocess.run(['ssh',*opts,user+'@'+host,'set -e; f="$HOME/www/anytoour.ru/api-v2.php"; test -f "$f" && ! test -L "$f"; php -l "$f" >/dev/null; sha256sum "$f" | cut -d" " -f1'],capture_output=True,text=True,check=True,timeout=45)
    value=q.stdout.strip();need(re.fullmatch(r'[a-f0-9]{64}',value),'live_hash')
    out={'status':'inspected_read_only','sha256':value,'supplier_calls':0,'server_writes':0}
    (work/'evidence.json').write_text(json.dumps(out,sort_keys=True));print(json.dumps(out))


def validate_event(event, env):
    need(env.get('GITHUB_REPOSITORY') == REPO, 'repository')
    need(event.get('repository', {}).get('full_name') == REPO, 'event_repository')
    need(env.get('GITHUB_REF') == 'refs/heads/main', 'main_only')
    need(env.get('GITHUB_WORKFLOW_REF') == f'{REPO}/{WORKFLOW}@refs/heads/main', 'workflow')
    need(env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff', 'actor')
    need(env.get('GITHUB_EVENT_NAME') == 'issue_comment' and event.get('action') == 'created', 'event')
    need(env.get('GITHUB_RUN_ATTEMPT') == '1', 'run_attempt')
    issue, comment = event.get('issue', {}), event.get('comment', {})
    need(issue.get('number') == ISSUE and not issue.get('pull_request'), 'issue')
    need(comment.get('user', {}).get('id') == 226193297 and comment.get('author_association') == 'OWNER', 'owner')
    control = env.get('GITHUB_SHA', '')
    need(re.fullmatch(r'[a-f0-9]{40}', control), 'control_sha')
    need(comment.get('body') == f'{COMMAND_PREFIX} {control} {OPERATION}', 'command')
    need(type(comment.get('id')) is int and comment['id'] > 0, 'comment_id')
    return control, comment['id']


def api(path):
    request = urllib.request.Request(
        f'https://api.github.com/repos/{REPO}{path}',
        headers={'Authorization': 'Bearer ' + os.environ['GH_TOKEN'],
                 'Accept': 'application/vnd.github+json', 'User-Agent': 'search3-root-readonly-preflight'},
    )
    with urllib.request.urlopen(request, timeout=30) as response:
        data = response.read(MAX_API_BYTES + 1)
    need(len(data) <= MAX_API_BYTES, 'github_response_limit')
    return json.loads(data)


def admission(control, comment_id):
    need(api('/git/ref/heads/main')['object']['sha'] == control, 'main_moved')
    need(api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha'] == RELEASE, 'release_moved')
    for ref in (SOURCE, RELEASE):
        need(api('/contents/v2/api-v2.php?ref=' + ref)['sha'] == BLOB, 'candidate_blob')
    matched = []
    for page in range(1, MAX_COMMENT_PAGES + 1):
        comments = api(f'/issues/{ISSUE}/comments?per_page=100&page={page}')
        need(isinstance(comments, list), 'comments_shape')
        for item in comments:
            if item.get('body', '').strip().startswith(COMMAND_PREFIX + ' '):
                matched.append(item['id'])
        if len(comments) < 100:
            need(matched == [comment_id], 'operation_already_reserved')
            return
    raise RuntimeError('comment_history_incomplete')


# No file body, PHP output, logs, config, DB or HTTP is returned or executed.
# Constants are emitted only by this checked source, never from command arguments.
REMOTE_BODY = r'''import hashlib,json,os,pathlib,stat,subprocess
def need(value,message):
 if not value: raise RuntimeError(message)
root=pathlib.Path.home()/'www'/'anytoour.ru'
def digest(relative):
 path=root/relative
 for parent in (root.parent,root,path.parent):
  need(stat.S_ISDIR(parent.lstat().st_mode),'unsafe_parent')
 before=path.lstat()
 need(stat.S_ISREG(before.st_mode) and before.st_nlink==1,'unsafe_file')
 need(before.st_size<=MAX_FILE_BYTES,'file_too_large')
 fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
 with os.fdopen(fd,'rb') as stream:
  opened=os.fstat(stream.fileno())
  need((opened.st_dev,opened.st_ino)==(before.st_dev,before.st_ino),'file_changed')
  total=0; value=hashlib.sha256()
  while True:
   chunk=stream.read(min(65536,MAX_FILE_BYTES-total+1))
   if not chunk: break
   total+=len(chunk);need(total<=MAX_FILE_BYTES,'file_too_large');value.update(chunk)
  after=os.fstat(stream.fileno())
 need((before.st_dev,before.st_ino,before.st_size,before.st_mtime_ns,before.st_ctime_ns)==
      (after.st_dev,after.st_ino,total,after.st_mtime_ns,after.st_ctime_ns),'file_changed')
 return {'path':relative,'sha256':value.hexdigest(),'size':total,'mode':format(stat.S_IMODE(before.st_mode),'04o')}
result={'status':'preflight_error','syntax':'not_run','before':[],'after':[]}
try:
 for path in EXPECTED: result['before'].append(digest(path))
 need(all(item['sha256']==EXPECTED[item['path']] for item in result['before']),'predecessor_drift')
 checked=subprocess.run(['php','-n','-l',str(root/'api-v2.php')],stdout=subprocess.DEVNULL,
                        stderr=subprocess.DEVNULL,timeout=15,check=False,
                        env={'PATH':'/usr/bin:/bin','HOME':str(pathlib.Path.home())})
 result['syntax']='ok' if checked.returncode==0 else 'failed'
 need(checked.returncode==0,'syntax_failed')
 for path in EXPECTED: result['after'].append(digest(path))
 need(result['before']==result['after'],'file_changed')
 result['status']='preflight_verified_read_only'
except Exception as exc:
 result['error_type']=type(exc).__name__
 result['reason']=str(exc) if type(exc) is RuntimeError and str(exc) in ('unsafe_parent','unsafe_file','file_too_large','file_changed','predecessor_drift','syntax_failed') else 'read_failed'
print(json.dumps(result,sort_keys=True,separators=(',',':')))
'''
REMOTE_PREFLIGHT = f'EXPECTED={EXPECTED!r}\nMAX_FILE_BYTES={MAX_FILE_BYTES}\n' + REMOTE_BODY


def read_remote(work):
    key, known = work / 'key', work / 'known'
    try:
        raw = os.environ['PREVIEW_KEY'].replace('\r', '')
        if 'PRIVATE KEY' not in raw:
            raw = base64.b64decode(raw, validate=True).decode()
        key.write_text(raw.rstrip() + '\n')
        key.chmod(0o600)
        host, user = os.environ['PREVIEW_HOST'], os.environ['PREVIEW_USER']
        need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*', host) and
             re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}', user), 'ssh_identity')
        scanned = subprocess.run(['ssh-keyscan', '-T', '15', '-t', 'ed25519', host],
                                 stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, check=True, timeout=30)
        known.write_bytes(scanned.stdout)
        known.chmod(0o600)
        opts = ['-i', str(key), '-o', 'StrictHostKeyChecking=yes',
                '-o', 'UserKnownHostsFile=' + str(known), '-o', 'GlobalKnownHostsFile=/dev/null',
                '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=15']
        result = subprocess.run(['ssh', *opts, user + '@' + host, 'python3 -'],
                                input=REMOTE_PREFLIGHT.encode(), stdout=subprocess.PIPE,
                                stderr=subprocess.DEVNULL, check=True, timeout=60)
        need(len(result.stdout) <= MAX_EVIDENCE_BYTES, 'evidence_limit')
        return json.loads(result.stdout)
    finally:
        key.unlink(missing_ok=True)
        known.unlink(missing_ok=True)


def validate_result(result):
    need(set(result) <= {'status', 'syntax', 'before', 'after', 'error_type', 'reason'}, 'result_fields')
    need(result.get('status') in ('preflight_verified_read_only', 'preflight_error'), 'result_status')
    need(result.get('syntax') in ('not_run', 'ok', 'failed'), 'result_syntax')
    if 'error_type' in result:
        need(result['error_type'] in ('RuntimeError', 'FileNotFoundError', 'PermissionError',
                                     'OSError', 'TimeoutExpired', 'ValueError'), 'result_error_type')
        need(result.get('reason') in ('unsafe_parent', 'unsafe_file', 'file_too_large', 'file_changed',
                                     'predecessor_drift', 'syntax_failed', 'read_failed'), 'result_reason')
    for phase in ('before', 'after'):
        values = result.get(phase)
        need(isinstance(values, list) and len(values) <= len(EXPECTED), 'result_inventory')
        for index, item in enumerate(values):
            need(set(item) == {'path', 'sha256', 'size', 'mode'}, 'result_file_fields')
            need(item['path'] == list(EXPECTED)[index], 'result_path')
            need(re.fullmatch(r'[a-f0-9]{64}', item['sha256']), 'result_hash')
            need(type(item['size']) is int and 0 <= item['size'] <= MAX_FILE_BYTES, 'result_size')
            need(re.fullmatch(r'[0-7]{4}', item['mode']), 'result_mode')
    if result['status'] == 'preflight_verified_read_only':
        need(result['syntax'] == 'ok' and 'error_type' not in result and 'reason' not in result, 'result_verified')
        need(len(result['before']) == len(EXPECTED) and result['before'] == result['after'], 'result_changed')
        need(all(item['sha256'] == EXPECTED[item['path']] for item in result['before']), 'predecessor_drift')
    return result


def preflight():
    work = pathlib.Path(os.environ['RUNNER_TEMP']) / 'search3-root-gateway-preflight'
    work.mkdir()
    evidence = {'status': 'preflight_error', 'operation': OPERATION, 'target': TARGET,
                'source': SOURCE, 'release': RELEASE, 'blob': BLOB,
                'supplier_calls': 0, 'server_writes': 0, 'db_writes': 0, 'real_leads': 0}
    failed = True
    try:
        event = json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text())
        control, comment_id = validate_event(event, os.environ)
        evidence.update(control_source=control, command_comment_id=comment_id)
        admission(control, comment_id)
        evidence.update(validate_result(read_remote(work)))
        failed = evidence['status'] != 'preflight_verified_read_only'
    except Exception as exc:
        # Do not retain exception messages: SSH, environment or paths may be private.
        evidence.update(status='preflight_error', error_type=type(exc).__name__)
    finally:
        (work / 'evidence.json').write_text(json.dumps(evidence, sort_keys=True))
    print(json.dumps(evidence, sort_keys=True))
    need(not failed, 'preflight_failed')


if __name__ == '__main__':
    if len(sys.argv) == 1:
        legacy_inspect()
    else:
        need(sys.argv[1:] == ['preflight'], 'usage')
        preflight()

