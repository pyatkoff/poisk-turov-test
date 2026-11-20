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


# This separate read-only operation does not repin or reopen any older operation.
READINESS_ISSUE = 4709
READINESS_PREFIX = '/inspect-search3-production-readiness'
READINESS_OPERATION = 'readiness-20261120-v1'
READINESS_RELEASE = '63844d4093e82dbe2810294514fe2a881df981ed'
READINESS_SOURCE = 'dd216ed705bc79cb2eb8c17968fb114cd5ca4cd7'
READINESS_TREE = 'd910750caccbc9dfb259d543aeb66ed0690bdaa9'
READINESS_FILE_BYTES = 2 * 1024 * 1024
READINESS_EVIDENCE_BYTES = 64 * 1024
READINESS_INT_BASE = '_preview/search3-anex-candidate/'
READINESS_NEXT_BASE = '_preview/search3-next-candidate/'
READINESS_INT_FILES = (
    'api-anex-search3-preview.php',
    'app/integrations/anex-additional-prices-client.php',
    'app/integrations/anex-initial-week-gate.php',
    'app/integrations/anex-package-quote-client.php',
    'app/integrations/anex-package-quote-handoff.php',
    'app/integrations/anex-package-quote-runtime.php',
    'app/integrations/operator-fuel-rule-evidence.php',
    'app/integrations/operator-fuel-rule-store.php',
    'app/integrations/search3-messenger-handoff.php',
    'app/integrations/tourvisor-interactive-client.php',
    'app/integrations/tourvisor-operator-fuel-passive-intake.php',
    'app/integrations/tourvisor-operator-fuel-retained-intake.php',
    'app/integrations/tourvisor-preview-gateway.php',
    'app/integrations/tourvisor-selected-tour-handoff.php',
)
READINESS_TARGETS = {
    'public': {
        'root': ('www', 'anytoour.ru'), 'env': 'PREVIEW',
        'files': (
            'index.php', 'search-page-v2.php', 'poisk-turov/index.php',
            'poisk-turov-old/index.php', 'api-v2.php', 'lead-adapter-v2.php',
            'lead-bridge-v1.php',
            READINESS_NEXT_BASE + 'visual-search/index.php',
            READINESS_NEXT_BASE + 'prototype-search/config.js',
        ) + tuple(READINESS_INT_BASE + path for path in READINESS_INT_FILES),
    },
    'legacy': {
        'root': ('www', 'anytour.online', 'poisk-turov-test', 'v2'), 'env': 'LEGACY',
        'files': ('lead-receiver-v1.php', 'lead-adapter-v2.php',
                  'lead-price-v1.php', 'lead-idempotency-v1.php'),
    },
}
READINESS_OPTIONAL = {('public', 'poisk-turov-old/index.php')}

# Comparisons describe their provenance. They are not installed-version admission.
READINESS_REFERENCES = {
    'main_source': {
        'source': 'e68fe088764521c7ff6b605403ca7f2ebb7e4dd6',
        'kind': 'source_mapping', 'target': 'public',
        'hashes': {
            'index.php': 'bef590b35ec45d2ac21a5c41c1d6d49bd36da25798e720aa2d23dca152d3688c',
            'search-page-v2.php': '80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f',
            'poisk-turov/index.php': '9d00c5f952e0c94cbe850e9fd5c95aa9b4e2cf8bd1ff7732faa3f1e10c1a1438',
            'api-v2.php': '828354f69bfc2fafa7e2afb2a2955e0e67858920ba967be196301b7a01baca98',
            # Existing publisher maps the public adapter URL to the bridge owner.
            'lead-adapter-v2.php': '68f6c42bb9cf18dfeaf303b1b615c7d9db650cd64d4cb942c833441686ac8a8c',
            'lead-bridge-v1.php': '68f6c42bb9cf18dfeaf303b1b615c7d9db650cd64d4cb942c833441686ac8a8c',
        },
    },
    'next_payload': {
        'source': 'd6e0563d598b32d59d6219e68eed97340f2e68a1',
        'kind': 'retained_compiled_payload', 'target': 'public',
        'artifact': 14605214194,
        'manifest_sha256': '454c2050e7385abffdaaadd08293281a1723ed59a4b7867ffe88fefa5aedf919',
        'hashes': {
            READINESS_NEXT_BASE + 'visual-search/index.php': '159060195fa7a41e6a95f5e8e0fb302f04c6c94c9e49b5c1845eee9210626454',
            READINESS_NEXT_BASE + 'prototype-search/config.js': '2d2fc5c4f67e1b13d99ae7fa2c7adf3407518077aed8f11d40da28c4158a3b4d',
        },
    },
    'int_retained_receipt': {
        'source': '6dcfb5053793caba4f353facfa7a899a30df2d17',
        'kind': 'historical_installed_pin_map', 'target': 'public', 'receipt': 6528951578,
        'hashes': dict(zip((READINESS_INT_BASE + path for path in READINESS_INT_FILES), (
            '0beda5c137c3153ec7c80da382f63ca3f989634130c508af69f6c6f0ce2162f4',
            'e905ce4f12a920201b1b2faeff78623a26023dd8ad79e0c3d6378d1dc75478f7',
            '145eff807355b50bcd381c6b3d732eb5c8ac4350f2945fb77304c9fd50673ce6',
            '010900cb382af985c0da097a440fc49dc6d85e311ea380d4c1a93e0df656ce0a',
            'd3d134a43b6f19676e7d046de525fc7ac206d7781aaaaf9eee375334e237bb7d',
            'bdc4b6737974935cf195f5db1477013ab95110108e2137aea684571bbd22af01',
            '5205c275da1f71aa756aab6852664902a8ea0453a4a457673f0896737f11a6a4',
            'a84468efc7953f50ee6fc25aaa77a40b97bd527ad9fbbe923d547dbe9db60fb9',
            '5e0fe33e6adf3ce30451b9ffe1742cc77f791114e241fb41d92236c1d70ea85c',
            '72056e5c4642daa9515d2d5dccece0804b6e3dd9c935b6250e0e29ca2e39daf9',
            '09a40aa5c6c4b6d98d378e353707412a4a3b83631452585a3fff27c6392d7008',
            '6c233c9fc0fe64c1647941ccb22756e1cad4c17b9692998dbd1eb6b7eb04a3d0',
            'c3008d765b9279c7be347e9fa92861053b5dc661d7ea2dbf65450e83f488d8d1',
            '9dafb88cab92e19376e120c80dd5dfecc975730cca7e7039edc419bf1ffa57f3',
        ))),
    },
    'legacy_main_source': {
        'source': 'e68fe088764521c7ff6b605403ca7f2ebb7e4dd6',
        'kind': 'source_mapping', 'target': 'legacy',
        'hashes': {
            'lead-receiver-v1.php': '67f4402cbf482a3274bb56a82e94217e3789b8907e27dafb30bde9e14d229575',
            'lead-adapter-v2.php': '8eb0c75a5ccad219190fddc31f9d254545f9c7566e459f169231481cebe4e069',
            'lead-price-v1.php': 'e723470a45f910ebfd93bd04debeb1c13bbf87f897e5c63a4735d606034d09ff',
            'lead-idempotency-v1.php': '076d74f05d3c32f67828c992c73c3ea09d8676af58fc1769e521ac6df819f238',
        },
    },
    'legacy_release_source': {
        'source': READINESS_RELEASE, 'kind': 'source_mapping', 'target': 'legacy',
        'hashes': {
            'lead-adapter-v2.php': '3160b72685f0a7e430528f62aa3b114ffaec567e9942d969beddcb47f5cbdbeb',
            'lead-idempotency-v1.php': '60f571695d135a827160d9f095a23f11a0e6c61d12dfd7b36ab4dd1f33b27793',
        },
    },
}


def readiness_event(event, env):
    need(env.get('GITHUB_REPOSITORY') == REPO, 'repository')
    need(event.get('repository', {}).get('full_name') == REPO, 'event_repository')
    need(env.get('GITHUB_REF') == 'refs/heads/main', 'main_only')
    need(env.get('GITHUB_WORKFLOW_REF') == f'{REPO}/{WORKFLOW}@refs/heads/main', 'workflow')
    need(env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff', 'actor')
    need(env.get('GITHUB_EVENT_NAME') == 'issue_comment' and event.get('action') == 'created', 'event')
    need(env.get('GITHUB_RUN_ATTEMPT') == '1', 'run_attempt')
    need(isinstance(env.get('GITHUB_RUN_ID'), str) and
         re.fullmatch(r'[1-9][0-9]{0,19}', env['GITHUB_RUN_ID']), 'run_id')
    issue, comment = event.get('issue', {}), event.get('comment', {})
    need(issue.get('number') == READINESS_ISSUE and not issue.get('pull_request'), 'issue')
    need(type(comment.get('user', {}).get('id')) is int and
         comment['user']['id'] == 226193297 and comment.get('author_association') == 'OWNER', 'owner')
    control = env.get('GITHUB_SHA', '')
    need(isinstance(control, str) and re.fullmatch(r'[a-f0-9]{40}', control), 'control_sha')
    need(comment.get('body') == f'{READINESS_PREFIX} {control} {READINESS_OPERATION}', 'command')
    need(type(comment.get('id')) is int and comment['id'] > 0, 'comment_id')
    return control, comment['id']


def readiness_admission(control, comment_id):
    need(api('/git/ref/heads/main')['object']['sha'] == control, 'main_moved')
    need(api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha'] == READINESS_RELEASE,
         'readiness_release_moved')
    pr = api('/pulls/5118')
    need(pr.get('number') == 5118 and pr.get('state') == 'open' and pr.get('merged') is False and
         pr.get('head', {}).get('sha') == READINESS_SOURCE and
         pr.get('head', {}).get('repo', {}).get('full_name') == REPO and
         pr.get('base', {}).get('ref') == 'release/search3-production-ready-v1' and
         pr.get('base', {}).get('repo', {}).get('full_name') == REPO, 'readiness_candidate')
    commit = api('/git/commits/' + READINESS_SOURCE)
    need(commit.get('sha') == READINESS_SOURCE and commit.get('tree', {}).get('sha') == READINESS_TREE,
         'readiness_source_tree')
    matched, seen = [], set()
    command = f'{READINESS_PREFIX} {control} {READINESS_OPERATION}'
    for page in range(1, MAX_COMMENT_PAGES + 1):
        comments = api(f'/issues/{READINESS_ISSUE}/comments?per_page=100&page={page}')
        need(isinstance(comments, list) and len(comments) <= 100, 'readiness_comments_shape')
        for item in comments:
            need(isinstance(item, dict) and type(item.get('id')) is int and item['id'] > 0 and
                 isinstance(item.get('body'), str) and item['id'] not in seen, 'readiness_comments_shape')
            seen.add(item['id'])
            tokens = item['body'].strip().split()
            if tokens and tokens[0] == READINESS_PREFIX and READINESS_OPERATION in tokens[1:]:
                matched.append(item['id'])
                if item['id'] == comment_id:
                    need(item['body'] == command and item.get('user', {}).get('id') == 226193297 and
                         item.get('author_association') == 'OWNER', 'readiness_current_comment')
        if len(comments) < 100:
            need(matched == [comment_id], 'readiness_operation_already_reserved')
            # Pagination may take time; do not connect after the trusted control moved.
            need(api('/git/ref/heads/main')['object']['sha'] == control, 'main_moved')
            need(api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha'] == READINESS_RELEASE,
                 'readiness_release_moved')
            return
    raise RuntimeError('readiness_comment_history_incomplete')


# This stdin program imports only standard-library hashing/JSON/filesystem readers.
# It never executes application code, opens a network connection or writes remotely.
READINESS_REMOTE_BODY = r'''import hashlib,json,os,stat
def require(value,reason):
 if not value: raise RuntimeError(reason)
def stamp(value):
 return (value.st_dev,value.st_ino,value.st_mode,value.st_nlink,value.st_size,value.st_mtime_ns,value.st_ctime_ns)
def directory_stamp(value):
 # Sibling traffic may change directory timestamps; identity/mode must not change.
 return (value.st_dev,value.st_ino,value.st_mode)
def parts(value):
 result=value.split('/')
 require(bool(result) and all(p and p not in ('.','..') and '\x00' not in p for p in result),'invalid_fixed_inventory')
 return result
def snapshot(relative):
 home=os.environ.get('HOME','')
 require(home.startswith('/') and home!='/' and os.path.normpath(home)==home,'unsafe_home')
 ancestors=parts(home[1:])+list(ROOT_PARTS)
 components=ancestors+parts(relative)
 links=[]; fd=None; row=None; absent=False; leaf_stamp=None
 try:
  fd=os.open('/',os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
  initial=os.fstat(fd);require(stat.S_ISDIR(initial.st_mode),'unsafe_parent')
  links.append((None,None,fd,directory_stamp(initial)))
  for index,component in enumerate(components[:-1]):
   try: before=os.stat(component,dir_fd=fd,follow_symlinks=False)
   except FileNotFoundError:
    if index<len(ancestors): raise
    absent=True;break
   require(stat.S_ISDIR(before.st_mode),'unsafe_parent')
   child=os.open(component,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd)
   opened=os.fstat(child)
   if directory_stamp(before)!=directory_stamp(opened): os.close(child);raise RuntimeError('file_changed')
   links.append((fd,component,child,directory_stamp(before)));fd=child
  if not absent:
   try: before=os.stat(components[-1],dir_fd=fd,follow_symlinks=False)
   except FileNotFoundError: absent=True
  if absent:
   row={'path':relative,'state':'absent'}
  else:
   require(stat.S_ISREG(before.st_mode) and before.st_nlink==1,'unsafe_file')
   require(0<=before.st_size<=MAX_BYTES,'file_too_large')
   leaf=os.open(components[-1],os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=fd)
   try:
    opened=os.fstat(leaf)
    require(stamp(opened)==stamp(before) and stat.S_ISREG(opened.st_mode) and opened.st_nlink==1,'file_changed')
    value=hashlib.sha256();total=0
    while True:
     chunk=os.read(leaf,min(65536,MAX_BYTES-total+1))
     if not chunk: break
     total+=len(chunk);require(total<=MAX_BYTES,'file_too_large');value.update(chunk)
    require(stamp(before)==stamp(os.fstat(leaf)) and total==before.st_size,'file_changed')
   finally: os.close(leaf)
   require(stamp(before)==stamp(os.stat(components[-1],dir_fd=fd,follow_symlinks=False)),'file_changed')
   leaf_stamp=stamp(before)
   row={'path':relative,'state':'present','sha256':value.hexdigest(),'size':total,'mode':format(stat.S_IMODE(before.st_mode),'04o')}
  for parent,name,handle,original in links:
   require(directory_stamp(os.fstat(handle))==original,'file_changed')
   if parent is not None: require(directory_stamp(os.stat(name,dir_fd=parent,follow_symlinks=False))==original,'file_changed')
  return row,([original for parent,name,handle,original in links],leaf_stamp)
 finally:
  for parent,name,handle,original in reversed(links): os.close(handle)
result={'schema_version':1,'status':'read_error','before':[],'after':[]}
try:
 require(hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY'),'unsupported_platform')
 require(len(FILES)==len(set(FILES)) and all(isinstance(p,str) for p in FILES),'invalid_fixed_inventory')
 before_stamps=[];after_stamps=[]
 for relative in FILES:
  row,identity=snapshot(relative);result['before'].append(row);before_stamps.append(identity)
 for relative in FILES:
  row,identity=snapshot(relative);result['after'].append(row);after_stamps.append(identity)
 require(result['before']==result['after'] and before_stamps==after_stamps,'file_changed')
 result['status']='inventory_observed'
except Exception as error:
 allowed=('unsafe_home','unsafe_parent','unsafe_file','file_too_large','file_changed','invalid_fixed_inventory','unsupported_platform')
 result['reason']=str(error) if type(error) is RuntimeError and str(error) in allowed else 'read_failed'
 result['error_type']=type(error).__name__ if type(error) in (RuntimeError,FileNotFoundError,PermissionError,OSError,ValueError) else 'ReadError'
print(json.dumps(result,sort_keys=True,separators=(',',':')))
'''


def readiness_remote_program(target):
    need(target in READINESS_TARGETS, 'readiness_target')
    selected = READINESS_TARGETS[target]
    return (f'ROOT_PARTS={selected["root"]!r}\nFILES={selected["files"]!r}\n'
            f'MAX_BYTES={READINESS_FILE_BYTES}\n' + READINESS_REMOTE_BODY)


def validate_readiness_result(result, target):
    need(target in READINESS_TARGETS and isinstance(result, dict), 'readiness_result_shape')
    fields = {'schema_version', 'status', 'before', 'after'}
    failed = result.get('status') == 'read_error'
    need(set(result) == fields | ({'error_type', 'reason'} if failed else set()), 'readiness_result_fields')
    need(type(result.get('schema_version')) is int and result['schema_version'] == 1, 'readiness_result_schema')
    need(result.get('status') in ('inventory_observed', 'read_error'), 'readiness_result_status')
    if failed:
        need(result['error_type'] in ('RuntimeError', 'FileNotFoundError', 'PermissionError', 'OSError',
                                     'ValueError', 'ReadError'), 'readiness_result_error_type')
        need(result['reason'] in ('unsafe_home', 'unsafe_parent', 'unsafe_file', 'file_too_large',
                                 'file_changed', 'invalid_fixed_inventory', 'unsupported_platform',
                                 'read_failed'), 'readiness_result_reason')
    paths = READINESS_TARGETS[target]['files']
    for phase in ('before', 'after'):
        values = result.get(phase)
        need(isinstance(values, list) and len(values) <= len(paths), 'readiness_result_inventory')
        for index, item in enumerate(values):
            need(isinstance(item, dict) and item.get('path') == paths[index], 'readiness_result_path')
            need(item.get('state') in ('present', 'absent'), 'readiness_result_presence')
            if item['state'] == 'absent':
                need(set(item) == {'path', 'state'}, 'readiness_result_absent')
            else:
                need(set(item) == {'path', 'state', 'sha256', 'size', 'mode'}, 'readiness_result_file_fields')
                need(isinstance(item['sha256'], str) and re.fullmatch(r'[a-f0-9]{64}', item['sha256']), 'readiness_result_hash')
                need(type(item['size']) is int and 0 <= item['size'] <= READINESS_FILE_BYTES, 'readiness_result_size')
                need(isinstance(item['mode'], str) and re.fullmatch(r'[0-7]{4}', item['mode']), 'readiness_result_mode')
    if not failed:
        need(len(result['before']) == len(paths) and result['before'] == result['after'], 'readiness_result_changed')
    elif result['after']:
        need(len(result['before']) == len(paths), 'readiness_result_phase')
    return result


def readiness_comparisons(result, target):
    observations = {row['path']: row for row in result['before']}
    comparisons = {}
    mapped = set()
    for name, reference in READINESS_REFERENCES.items():
        if reference['target'] != target:
            continue
        rows = []
        for path, expected in reference['hashes'].items():
            observed = observations[path]
            state = ('absent' if observed['state'] == 'absent' else
                     'matches_reference' if observed['sha256'] == expected else 'differs_from_reference')
            rows.append({'path': path, 'expected_sha256': expected, 'comparison': state})
            mapped.add(path)
        comparisons[name] = {**{k: v for k, v in reference.items() if k != 'hashes'}, 'files': rows}
    return {
        'required_present': all(row['state'] == 'present' or (target, path) in READINESS_OPTIONAL
                                for path, row in observations.items()),
        'reference_results': comparisons,
        'unmapped_reference_paths': [path for path in observations if path not in mapped],
    }


class ReadinessTransportError(RuntimeError):
    def __init__(self, reason):
        need(reason in ('auth_failed', 'connection_failed', 'host_key_failed', 'timeout',
                        'invalid_credentials', 'read_failed'), 'readiness_transport_reason')
        self.reason = reason
        super().__init__(reason)


def readiness_ssh_failure(error):
    if isinstance(error, subprocess.TimeoutExpired):
        return 'timeout'
    # Inspect only for fixed categories in memory. Never retain the diagnostic.
    message = error.stderr if isinstance(error, subprocess.CalledProcessError) else b''
    message = message.decode('utf-8', errors='replace') if isinstance(message, bytes) else str(message or '')
    message = message.lower()
    if 'host key verification failed' in message or 'remote host identification has changed' in message:
        return 'host_key_failed'
    if (re.search(r'permission denied \((?:publickey|password|keyboard-interactive|gssapi|hostbased)[a-z0-9_,@.-]*\)', message)
            or 'permission denied, please try again' in message
            or 'authentication failed' in message or 'authentication failures' in message):
        return 'auth_failed'
    if any(term in message for term in ('invalid format', 'error in libcrypto', 'load key')):
        return 'invalid_credentials'
    if any(term in message for term in ('could not resolve hostname', 'connection refused', 'connection timed out',
                                        'no route to host', 'network is unreachable', 'connection reset')):
        return 'connection_failed'
    return 'read_failed'


def read_readiness_remote(work, target):
    need(target in READINESS_TARGETS, 'readiness_target')
    prefix = READINESS_TARGETS[target]['env']
    key, known = work / (target + '-key'), work / (target + '-known')
    try:
        try:
            host, user = os.environ[prefix + '_HOST'], os.environ[prefix + '_USER']
            need(len(host) <= 253 and re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*', host) and
                 re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}', user), 'readiness_ssh_identity')
            raw = os.environ[prefix + '_KEY'].replace('\r', '')
            need(0 < len(raw) <= 32768, 'readiness_key_limit')
            if 'PRIVATE KEY' not in raw:
                raw = base64.b64decode(raw, validate=True).decode()
        except (KeyError, ValueError, RuntimeError, UnicodeError):
            raise ReadinessTransportError('invalid_credentials') from None
        with os.fdopen(os.open(key, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600), 'w') as stream:
            stream.write(raw.rstrip() + '\n')
        try:
            scanned = subprocess.run(['ssh-keyscan', '-T', '15', '-t', 'ed25519', host],
                                     stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, check=True, timeout=30)
        except subprocess.TimeoutExpired:
            raise ReadinessTransportError('timeout') from None
        except (subprocess.CalledProcessError, OSError):
            raise ReadinessTransportError('connection_failed') from None
        need(0 < len(scanned.stdout) <= 8192, 'readiness_hostkey_limit')
        with os.fdopen(os.open(known, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600), 'wb') as stream:
            stream.write(scanned.stdout)
        opts = ['-i', str(key), '-o', 'StrictHostKeyChecking=yes',
                '-o', 'UserKnownHostsFile=' + str(known), '-o', 'GlobalKnownHostsFile=/dev/null',
                '-o', 'BatchMode=yes', '-o', 'IdentitiesOnly=yes', '-o', 'ConnectTimeout=15']
        # Isolated/no-site/no-bytecode: no user bootstrap or import cache writes.
        try:
            answer = subprocess.run(['ssh', *opts, user + '@' + host, 'python3 -I -S -B -'],
                                    input=readiness_remote_program(target).encode(), stdout=subprocess.PIPE,
                                    stderr=subprocess.PIPE, check=True, timeout=60)
        except (subprocess.CalledProcessError, subprocess.TimeoutExpired, OSError) as error:
            raise ReadinessTransportError(readiness_ssh_failure(error)) from None
        need(len(answer.stdout) <= READINESS_EVIDENCE_BYTES, 'readiness_evidence_limit')
        return json.loads(answer.stdout)
    finally:
        key.unlink(missing_ok=True)
        known.unlink(missing_ok=True)


def readiness_error_type(error):
    if isinstance(error, ReadinessTransportError):
        return 'TransportError'
    return type(error).__name__ if type(error) in (
        RuntimeError, KeyError, ValueError, TypeError, OSError, FileNotFoundError, PermissionError,
        subprocess.CalledProcessError, subprocess.TimeoutExpired, json.JSONDecodeError,
    ) else 'ReadError'


def production_readiness():
    work = pathlib.Path(os.environ['RUNNER_TEMP']) / 'search3-production-readiness'
    work.mkdir(mode=0o700)
    evidence = {
        'schema_version': 1, 'status': 'readiness_error', 'operation': READINESS_OPERATION,
        'source': READINESS_SOURCE, 'source_tree': READINESS_TREE, 'release': READINESS_RELEASE,
        'supplier_calls': 0, 'server_writes': 0, 'db_reads': 0, 'db_writes': 0, 'real_leads': 0,
        'installation_ready': False, 'targets': {}, 'failure_phase': 'admission',
    }
    try:
        event = json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text())
        control, comment_id = readiness_event(event, os.environ)
        evidence.update(control_source=control, command_comment_id=comment_id,
                        run_id=int(os.environ['GITHUB_RUN_ID']), run_attempt=1)
        readiness_admission(control, comment_id)
        evidence['failure_phase'] = 'readback'
        for target in READINESS_TARGETS:
            try:
                result = validate_readiness_result(read_readiness_remote(work, target), target)
                if result['status'] == 'inventory_observed':
                    result = {**result, **readiness_comparisons(result, target)}
                evidence['targets'][target] = result
            except Exception as error:
                evidence['targets'][target] = {'status': 'transport_or_result_error',
                                               'error_type': readiness_error_type(error),
                                               'reason': error.reason if isinstance(error, ReadinessTransportError) else 'read_failed'}
        observed = [value['status'] == 'inventory_observed' for value in evidence['targets'].values()]
        evidence['status'] = ('inventory_observed' if all(observed) else
                              'partial_inventory_observed' if any(observed) else 'readiness_error')
        evidence['required_present'] = all(value.get('required_present') is True
                                           for value in evidence['targets'].values())
        if all(observed):
            evidence.pop('failure_phase')
    except Exception as error:
        evidence['error_type'] = readiness_error_type(error)
    # Retain only validated fixed-path metadata; never exception messages or SSH output.
    encoded = json.dumps(evidence, sort_keys=True)
    need(len(encoded.encode()) <= READINESS_EVIDENCE_BYTES, 'readiness_evidence_limit')
    (work / 'evidence.json').write_text(encoded)
    print(encoded)
    need(evidence['status'] == 'inventory_observed', 'readiness_not_observed')


if __name__ == '__main__':
    if sys.argv[1:] == ['production-readiness']:
        production_readiness()
    elif len(sys.argv) == 1:
        legacy_inspect()
    else:
        need(sys.argv[1:] == ['preflight'], 'usage')
        preflight()

