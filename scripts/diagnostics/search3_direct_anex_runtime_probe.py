"""Owner-gated one-shot diagnosis of the fixed installed ANEX search core."""
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import urllib.request

COMMAND = '/search3-direct-anex-runtime-probe-v1'
REPO = 'pyatkoff/poisk-turov-test'
OPERATION = 'search3-direct-anex-runtime-probe-20260926-v1'
SOURCE = '93ce2190a444a7b4aa2920a013f14c8b2f64e79c'

def authorize(event, env):
    issue, comment = event.get('issue', {}), event.get('comment', {})
    return (env.get('GITHUB_EVENT_NAME') == 'issue_comment' and event.get('action') == 'created'
        and env.get('GITHUB_REPOSITORY') == REPO and env.get('GITHUB_REF') == 'refs/heads/main'
        and env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff'
        and env.get('GITHUB_RUN_ATTEMPT') == '1' and issue.get('number') == 3419
        and 'pull_request' not in issue and comment.get('body') == COMMAND
        and comment.get('author_association') == 'OWNER'
        and comment.get('user', {}).get('id') == 226193297
        and comment.get('user', {}).get('login') == 'pyatkoff')

def sanitize(value):
    if not isinstance(value, dict) or value.get('operation') != OPERATION or value.get('source') != SOURCE:
        raise ValueError('invalid_receipt_identity')
    clean = {'operation': OPERATION, 'source': SOURCE}
    for key, allowed in {'status': {'blocked','failed','complete','unknown'},
                         'stage': {'preflight','runtime_include','database','search_core'}}.items():
        if value.get(key) not in allowed: raise ValueError('invalid_receipt_enum')
        clean[key] = value[key]
    for key, cap in {'client_requests':12,'database_writes':0,'quote_calls':0,'hotels':4800,'offers':4800}.items():
        if key not in value and key in {'hotels','offers'}: continue
        if type(value.get(key)) is not int or not 0 <= value[key] <= cap: raise ValueError('invalid_receipt_count')
        clean[key] = value[key]
    if value.get('replay_allowed') is not False: raise ValueError('invalid_receipt_replay')
    clean['replay_allowed'] = False
    failure = value.get('failure')
    if isinstance(failure, dict):
        out = {}
        for key, allowed in {
            'exception': {'PDOException','RuntimeException','InvalidArgumentException','Error','TypeError','other'},
            'action': {'SearchTour_TOWNFROMS','SearchTour_STATES','SearchTour_CURRENCIES','SearchTour_PRICES'},
            'reason': {'ANEX_TOKEN_REQUIRED','ANEX_REQUEST_LIMIT','ANEX_TRANSPORT_ERROR','ANEX_INVALID_RESPONSE',
                'ANEX_RESPONSE_TOO_LARGE','ANEX_HTTP_ERROR','ANEX_SUPPLIER_ERROR','ANEX_INVALID_PARAMS',
                'ANEX_INVALID_PRICES','ANEX_SEARCH_PAGINATION_LIMIT','ANEX_RATE_LIMIT','ANEX_FILTER_UNSUPPORTED',
                'ANEX_DESTINATION_UNSUPPORTED','ANEX_INVALID_SEARCH','project_invalid','runtime_missing',
                'runtime_drift','config_unavailable','operation_exists_no_replay','reservation_failed',
                'scope_expired','request_budget_exceeded','internal_error'},
        }.items():
            if isinstance(failure.get(key), str) and failure[key] in allowed: out[key] = failure[key]
        for key, cap in {'http_status':599,'curl_errno':999,'supplier_code':99999,'response_bytes':2097153}.items():
            if type(failure.get(key)) is int and 0 <= failure[key] <= cap: out[key] = failure[key]
        state = failure.get('sql_state')
        if isinstance(state, str) and re.fullmatch('[A-Z0-9]{5}', state): out['sql_state'] = state
        clean['failure'] = out
    return clean

def main():
    event=json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
    if not authorize(event, os.environ): raise ValueError('authorization_denied')
    if sys.argv[1:] == ['--authorize']:
        req=urllib.request.Request(f"https://api.github.com/repos/{REPO}/issues/comments/{event['comment']['id']}",
            headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json'})
        with urllib.request.urlopen(req,timeout=20) as response: fresh=json.load(response)
        if not authorize({**event,'comment':fresh},os.environ): raise ValueError('command_revoked')
        print('SEARCH3_ANEX_RUNTIME_AUTHORIZED')
        return
    if sys.argv[1:] != ['--run']: raise ValueError('invalid_mode')
    host, user = os.environ['INT_SSH_HOST'], os.environ['INT_SSH_USER']
    if not re.fullmatch('[A-Za-z0-9][A-Za-z0-9.-]*',host) or not re.fullmatch('[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user):
        raise ValueError('invalid_ssh_identity')
    code=Path(__file__).with_suffix('.php').read_text()+'\nexit(search3_direct_anex_runtime_main());\n'
    with tempfile.TemporaryDirectory(dir=os.environ['RUNNER_TEMP']) as work:
        key=Path(work)/'key'; key.write_text(os.environ['INT_SSH_KEY'].rstrip()+'\n');key.chmod(0o600)
        args=['ssh','-T','-i',str(key),'-o','IdentitiesOnly=yes','-o','BatchMode=yes',
            '-o','StrictHostKeyChecking=accept-new','-o','UserKnownHostsFile='+str(Path(work)/'known_hosts'),
            '-o','ConnectTimeout=15','-o','ServerAliveInterval=15','-o','ServerAliveCountMax=2','-o','LogLevel=ERROR',
            '-l',user,host,'cd "$HOME/www/anytoour.ru" && php']
        child_env={k:v for k,v in os.environ.items() if k not in {'INT_SSH_KEY','GH_TOKEN'}}
        completed=subprocess.run(args,input=code,text=True,capture_output=True,timeout=300,env=child_env)
        if len(completed.stdout) > 8192: raise ValueError('invalid_remote_output')
        result=sanitize(json.loads(completed.stdout))
        out=Path('search3-direct-anex-runtime-probe');out.mkdir(exist_ok=True)
        (out/'result.json').write_text(json.dumps(result,indent=2)+'\n')
        print(json.dumps(result))
        if completed.returncode or result['status']=='unknown': raise ValueError('operation_unknown_no_replay')

if __name__ == '__main__':
    try: main()
    except Exception:
        # Never print subprocess, HTTP, PHP or token-bearing exception text.
        sys.exit('SEARCH3_ANEX_RUNTIME_UNCONFIRMED_NO_REPLAY')
