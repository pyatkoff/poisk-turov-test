#!/usr/bin/env python3
import base64
import json
import os
import pathlib
import re
import subprocess
import urllib.request

ISSUE = 4217
SEARCH_ID = '13905446138'
INCIDENT_UTC = '2026-10-14T15:00:58Z'
COMMAND_PREFIX = f'/diagnose-tourvisor-results-500 {SEARCH_ID} {INCIDENT_UTC}'
COMMAND_NONCE = 'diag-20261016-7f2c9a4e1b63'
MAX_MATCHES = 120
MAX_LINE = 1200
MAX_SOURCES = 20
MAX_BYTES_PER_SOURCE = 2 * 1024 * 1024
MAX_COMMENT_PAGES = 30


def need(value, message):
    if not value:
        raise RuntimeError(message)


def sanitize_line(line):
    line = re.sub(
        r'(?i)\b(proxy-authorization|authorization|cookie|set-cookie)\s*[:=]\s*[^\r\n]+',
        r'\1: [REDACTED]',
        line,
    )
    line = re.sub(r'(?i)(bearer\s+)[A-Za-z0-9._~+/-]{12,}', r'\1[REDACTED]', line)
    line = re.sub(
        r'(?i)([?&](?:access_token|refresh_token|id_token|token|jwt|password|passwd|secret|cookie|key|api_key|apikey|session|sessionid)=)[^&\s]+',
        r'\1[REDACTED]',
        line,
    )
    line = re.sub(
        r'''(?ix)(["']?(?:access_token|refresh_token|id_token|token|jwt|password|passwd|secret|cookie|api_key|apikey|session|sessionid|db_password)["']?\s*[:=]\s*["']?)[^"'\s,;&]+''',
        r'\1[REDACTED]',
        line,
    )
    line = re.sub(r'(?i)([a-z][a-z0-9+.-]*://[^\s:/@]+:)[^\s/@]+(@)', r'\1[REDACTED]\2', line)
    return line.replace('\x00', '')[:MAX_LINE]


REMOTE_READER = r'''import collections,glob,json,pathlib,subprocess
search_id='13905446138'
times=('2026/10/14 15:00','2026/10/14 15:01','2026-10-14 15:00','2026-10-14 15:01','14-Oct-2026 15:00','14-Oct-2026 15:01','Oct 14 15:00','Oct 14 15:01','14/Oct/2026:17:00','14/Oct/2026:17:01','2026/10/14 17:00','2026/10/14 17:01')
errors=('api-v2.php','search_results','PHP Fatal','Allowed memory size','upstream prematurely closed','upstream sent too big','Maximum execution time')
def relevant(line): return search_id in line or (any(t in line for t in times) and any(n in line for n in errors))
paths=['/var/log/nginx/error.log','/var/log/php8.3-fpm.log','/var/log/php8.3-fpm/error.log','/var/log/php8.3-fpm/www-error.log']
home=str(pathlib.Path.home())
paths += glob.glob(home+'/logs/*.log') + glob.glob(home+'/www/anytoour.ru/*error*.log') + glob.glob(home+'/www/anytoour.ru/.logs/*.log')
paths=sorted(set(paths))[:20]
seen=[];matches=collections.deque(maxlen=120)
for raw in paths:
 p=pathlib.Path(raw)
 try:
  if not p.is_file() or p.is_symlink(): continue
  with p.open('rb') as f:
   f.seek(0,2); size=f.tell(); f.seek(max(0,size-2*1024*1024))
   text=f.read(2*1024*1024).decode('utf-8','replace')
  seen.append(raw)
  for line in text.splitlines():
   if relevant(line): matches.append({'source':raw,'line':line[:2400]})
 except (OSError,ValueError): pass
try:
 q=subprocess.run(['journalctl','--no-pager','--lines=2000','--since','2026-10-14 15:00:30 UTC','--until','2026-10-14 15:01:30 UTC','-u','nginx','-u','php8.3-fpm'],capture_output=True,text=True,timeout=20,check=False)
 if q.stdout:
  seen.append('journalctl:nginx,php8.3-fpm')
  for line in q.stdout[:2*1024*1024].splitlines():
   if relevant(line): matches.append({'source':'journalctl:nginx,php8.3-fpm','line':line[:2400]})
except (OSError,subprocess.SubprocessError): pass
print(json.dumps({'sources_read':sorted(set(seen)),'matches':list(matches)},ensure_ascii=False))
'''


def validate_event(value, env):
    need(value['issue']['number'] == ISSUE, 'issue')
    need(value['comment']['user']['id'] == 226193297, 'owner_id')
    need(value['comment']['author_association'] == 'OWNER', 'owner_association')
    need(env.get('GITHUB_REPOSITORY') == 'pyatkoff/poisk-turov-test', 'repository')
    need(env.get('GITHUB_RUN_ATTEMPT') == '1', 'run_attempt')
    source = env.get('GITHUB_SHA', '')
    need(re.fullmatch(r'[0-9a-f]{40}', source), 'source_sha')
    command = value['comment']['body'].strip()
    need(command == f'{COMMAND_PREFIX} {source} {COMMAND_NONCE}', 'command')
    return command, value['comment']['id']


def unique_command_comment(command, comment_id, token):
    matched = []
    complete = False
    for page in range(1, MAX_COMMENT_PAGES + 1):
        request = urllib.request.Request(
            f'https://api.github.com/repos/pyatkoff/poisk-turov-test/issues/{ISSUE}/comments?per_page=100&page={page}',
            headers={
                'Accept': 'application/vnd.github+json',
                'Authorization': 'Bearer ' + token,
                'X-GitHub-Api-Version': '2022-11-28',
            },
        )
        with urllib.request.urlopen(request, timeout=20) as response:
            values = json.load(response)
        need(isinstance(values, list), 'comments_shape')
        matched.extend(item.get('id') for item in values if str(item.get('body', '')).strip() == command)
        if len(values) < 100:
            complete = True
            break
    need(complete, 'comments_unbounded')
    need(matched == [comment_id], 'command_replay')


def event():
    value = json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text())
    command, comment_id = validate_event(value, os.environ)
    unique_command_comment(command, comment_id, os.environ['GH_TOKEN'])


def ssh_options(work):
    key = work / 'key'
    known = work / 'known'
    raw = os.environ['PREVIEW_KEY'].replace('\r', '')
    if 'PRIVATE KEY' not in raw:
        raw = base64.b64decode(raw, validate=True).decode()
    key.write_text(raw.rstrip() + '\n')
    key.chmod(0o600)
    host = os.environ['PREVIEW_HOST']
    user = os.environ['PREVIEW_USER']
    need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*', host), 'ssh_host')
    need(re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}', user), 'ssh_user')
    scan = subprocess.run(
        ['ssh-keyscan', '-T', '15', '-t', 'ed25519', host],
        capture_output=True, check=True, timeout=30,
    )
    known.write_bytes(scan.stdout)
    known.chmod(0o600)
    opts = [
        '-i', str(key), '-o', 'StrictHostKeyChecking=yes',
        '-o', 'UserKnownHostsFile=' + str(known),
        '-o', 'GlobalKnownHostsFile=/dev/null', '-o', 'BatchMode=yes',
        '-o', 'ConnectTimeout=15',
    ]
    return key, known, opts, user + '@' + host


def main():
    event()
    work = pathlib.Path(os.environ['RUNNER_TEMP']) / 'search3-root-gateway-incident'
    work.mkdir()
    key = work / 'key'
    known = work / 'known'
    evidence = {
        'status': 'checked_no_matching_log',
        'issue': ISSUE,
        'search_id': SEARCH_ID,
        'incident_utc': INCIDENT_UTC,
        'supplier_calls': 0,
        'server_writes': 0,
        'db_writes': 0,
        'real_leads': 0,
        'sources_read': [],
        'matches': [],
    }
    failed = False
    try:
        key, known, opts, remote = ssh_options(work)
        result = subprocess.run(
            ['ssh', *opts, remote, 'python3 -'], input=REMOTE_READER,
            capture_output=True, text=True, check=True, timeout=60,
        )
        payload = json.loads(result.stdout)
        sources = payload.get('sources_read', [])
        matches = payload.get('matches', [])
        need(isinstance(sources, list) and isinstance(matches, list), 'remote_shape')
        evidence['sources_read'] = [sanitize_line(str(value)) for value in sources[:40]]
        evidence['matches'] = [
            {
                'source': sanitize_line(str(item.get('source', ''))),
                'line': sanitize_line(str(item.get('line', ''))),
            }
            for item in matches[-MAX_MATCHES:] if isinstance(item, dict)
        ]
        if evidence['matches']:
            evidence['status'] = 'matching_logs_retained_read_only'
    except Exception as exc:
        evidence['status'] = 'diagnostic_error'
        evidence['error_type'] = type(exc).__name__
        failed = True
    finally:
        key.unlink(missing_ok=True)
        known.unlink(missing_ok=True)
        (work / 'evidence.json').write_text(
            json.dumps(evidence, ensure_ascii=False, sort_keys=True, indent=2) + '\n'
        )
    if failed:
        raise RuntimeError('diagnostic_failed') from None
    print(json.dumps(evidence, ensure_ascii=False, sort_keys=True))


if __name__ == '__main__':
    main()
