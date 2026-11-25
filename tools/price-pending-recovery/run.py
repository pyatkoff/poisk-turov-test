"""Authorize the exact original PRICE handoff before the pinned server call."""
import base64
import concurrent.futures
import hashlib
import json
import os
import pathlib
import re
import subprocess

ROOT = pathlib.Path(__file__).parent
SITE = 'poisk-turov-test'
BOT = 'max-search-bot'
BRANCH = 'ops/price-hook-reviewed-pass-20261117'


def api(repo, path):
    return json.loads(subprocess.check_output(
        ['gh', 'api', '--method', 'GET', f'repos/pyatkoff/{repo}/{path}'], timeout=20))


def gate():
    event = json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text())
    assert os.environ['GITHUB_REPOSITORY'] == f'pyatkoff/{SITE}'
    assert os.environ['GITHUB_ACTOR'] == 'pyatkoff' and os.environ['GITHUB_RUN_ATTEMPT'] == '1'
    assert os.environ['GITHUB_EVENT_NAME'] == 'push' and event['before'] == '955f87fcd707670603b2607ed1177e260285a559'
    with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
        calls = {
            'head': (SITE, f'git/ref/heads/{BRANCH}'),
            'original': (SITE, 'actions/runs/45720745186'),
            'readback': (SITE, 'actions/runs/45723506613'),
            'release': (BOT, 'actions/runs/45722339567'),
            'publisher': (BOT, 'issues/comments/7077907800'),
            'content': (BOT, 'issues/comments/6818332618'),
            'feed': (BOT, 'issues/comments/7029866773'),
            'handoff': (BOT, 'issues/comments/6997426347'),
            'active': (BOT, 'actions/runs?branch=main&status=in_progress&per_page=50'),
            'coordination': (BOT, 'issues/1566'),
        }
        futures = {key: pool.submit(api, *args) for key, args in calls.items()}
        data = {key: result.result() for key, result in futures.items()}
    assert data['head']['object']['sha'] == os.environ['GITHUB_SHA'], 'branch_lease_changed'
    for key, head, conclusion in (
        ('original', '492ed802f0927c120953616113af4b162bc600d5', 'failure'),
        ('readback', '955f87fcd707670603b2607ed1177e260285a559', 'success'),
        ('release', 'c1a445f48fd514b9a4f883faecd20e5ecf1bc4fb', 'success'),
    ):
        run = data[key]
        assert (run['head_sha'], run['status'], run['conclusion']) == (head, 'completed', conclusion), key
    expected = {
        'publisher': '11eb5fb36e9e8ebd7162a2f2075e176fc703fee2451482f6dcc1aca0f7195e70',
        'content': 'ac36ecc4807b9d35485265615ce16c724e6af3aab2c6eb6ed4bb1c9a6f5d6bab',
        'feed': '195cc7dbf8501d8579e77f55343e1c032cd18f56e5175c5628099d4f3705faf2',
        'handoff': '132f256b9eab4c493554f7d783add1d011ef345c888127f86566e4e4cf9da26d',
    }
    for key, sha in expected.items():
        assert hashlib.sha256(data[key]['body'].encode()).hexdigest() == sha, key
    assert data['coordination']['comments'] == 233, 'coordination_changed'
    assert not data['active']['workflow_runs'], 'shared_main_release_active'


def main():
    gate()
    if os.getenv('CHECK_ONLY') == '1':
        return
    tmp = pathlib.Path(os.environ['RUNNER_TEMP'])
    host, user = os.environ['DEPLOY_HOST'], os.environ['DEPLOY_USER']
    assert re.fullmatch(r'[A-Za-z0-9_.:-]+', host) and not host.startswith('-')
    assert re.fullmatch(r'[A-Za-z0-9_-]+', user)
    finish = (ROOT / 'finish.php').read_bytes()
    prefix = 'import base64\nFINISH=base64.b64decode(' + repr(base64.b64encode(finish).decode()) + ',validate=True)\n'
    prefix += 'FINISH_SHA=' + repr(hashlib.sha256(finish).hexdigest()) + '\n'
    opts = ['-i', str(tmp / 'channel_key'), '-o', 'BatchMode=yes', '-o', 'IdentitiesOnly=yes',
            '-o', 'StrictHostKeyChecking=yes', '-o', 'GlobalKnownHostsFile=/dev/null',
            '-o', f'UserKnownHostsFile={tmp}/channel_known_hosts', '-o', 'ConnectTimeout=15']
    print('ORIGINAL_PRICE_ADMITTED 45720745186 45723506613', flush=True)
    result = subprocess.run(['ssh', *opts, user + '@' + host, 'python3 -'],
                            input=prefix.encode() + (ROOT / 'launch.py').read_bytes(), capture_output=True, timeout=165)
    print(result.stdout.decode(), end='', flush=True)
    raise SystemExit(result.returncode)


if __name__ == '__main__':
    main()
