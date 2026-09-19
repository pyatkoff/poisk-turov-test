#!/usr/bin/env python3
"""One authorized #3072 helper installation; no supplier or DB mutation.
This operations-only branch must close unmerged. Historical operations are not replayed.
"""
import hashlib
import json
import os
from pathlib import Path
import re
import shlex
import stat
import subprocess
import sys
import tempfile
import time
import urllib.request

REPO = 'pyatkoff/poisk-turov-test'
BASE = '25d03f7317350652129231223eccbd865b7a912b'
SOURCE = 'b8f1607c46333dc8931f246a19428ec15afddcdb'
OP = 'search3-autosave-helper-3072-20260919-v2'
BRANCH = 'ops/' + OP
CLAIM = 5743176393
HELPER = 'app/integrations/tourvisor-anytour-offer-autosave.php'
OLD = '563642d1191377d34eba4c282f882084dc33fc71'
NEW = '9fb728249b65dc685001057c246d589251a31728'
API_SHA = '0aee743972d3ac9eebeb5a54f899355375f4e5891369a2e873ceeb71097f68e2'
PATHS = ['.github/workflows/search3-autosave-helper-activate-v2.yml',
         'scripts/ops/search3_autosave_helper_activate_v2.py']
RUNTIME_RUNS = {35451587690: 'Tourvisor AnyTour offer autosave',
                35451587710: 'Tourvisor autosave persistent state',
                35451587641: 'Security guard'}


def need(ok, code):
    if not ok:
        raise RuntimeError(code)


def sha(data):
    return hashlib.sha256(data).hexdigest()


def blob(data):
    return hashlib.sha1(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest()


def save(path, value):
    with path.open('x', encoding='utf-8') as f:
        json.dump(value, f, ensure_ascii=False, sort_keys=True, indent=2)
        f.write('\n')
        f.flush()
        os.fsync(f.fileno())


def github(path):
    req = urllib.request.Request('https://api.github.com/repos/' + REPO + path,
        headers={'Authorization': 'Bearer ' + os.environ['GH_TOKEN'],
                 'Accept': 'application/vnd.github+json', 'User-Agent': OP})
    with urllib.request.urlopen(req, timeout=30) as r:
        return json.load(r)


def authorize(wait_security):
    event = json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
    pr = event.get('pull_request', {})
    head = pr.get('head', {}).get('sha', '')
    need(os.environ.get('GITHUB_REPOSITORY') == REPO and os.environ.get('GITHUB_EVENT_NAME') == 'pull_request', 'repository_event')
    need(os.environ.get('GITHUB_RUN_ATTEMPT') == '1', 'no_replay')
    need(event.get('sender', {}).get('id') == 226193297 and
         os.environ.get('GITHUB_ACTOR') == os.environ.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff', 'owner_only')
    need(pr.get('head', {}).get('ref') == BRANCH and pr.get('head', {}).get('repo', {}).get('full_name') == REPO, 'branch_identity')
    need(pr.get('base', {}).get('ref') == 'release/search3-production-ready-v1', 'base_branch')
    need(subprocess.check_output(['git', 'rev-parse', 'HEAD'], text=True).strip() == head, 'exact_checkout')
    changed = subprocess.check_output(['git', 'diff', '--name-only', BASE, head], text=True).splitlines()
    need(sorted(changed) == PATHS, 'exact_two_operation_paths')
    need(subprocess.check_output(['git', 'merge-base', BASE, head], text=True).strip() == BASE, 'base_ancestry')
    need(blob(Path(HELPER).read_bytes()) == NEW, 'source_blob')
    claim = github('/issues/comments/' + str(CLAIM))
    need(claim.get('user', {}).get('id') == 226193297 and claim.get('author_association') == 'OWNER'
         and claim.get('issue_url', '').endswith('/issues/2530') and OP in claim.get('body', '')
         and 'AUTHORIZED by owner' in claim.get('body', ''), 'fresh_authorization')
    live_pr = github('/pulls/' + str(pr['number']))
    need(live_pr.get('state') == 'open' and live_pr['head']['sha'] == head, 'operation_head_changed')
    need(github('/git/ref/heads/release/search3-production-ready-v1')['object']['sha'] == BASE, 'release_changed')
    source_pr = github('/pulls/3072')
    need(source_pr.get('merged') is True and source_pr['head']['sha'] == SOURCE and source_pr['merge_commit_sha'] == BASE, 'merged_source')
    for run_id, name in RUNTIME_RUNS.items():
        run = github('/actions/runs/' + str(run_id))
        need(run.get('name') == name and run.get('head_sha') == SOURCE and run.get('conclusion') == 'success', 'runtime_gate_' + str(run_id))
    deadline = time.monotonic() + (240 if wait_security else 1)
    while True:
        runs = github('/actions/runs?event=pull_request&head_sha=' + head + '&per_page=100')['workflow_runs']
        security = [r for r in runs if r.get('name') == 'Security guard' and r.get('head_sha') == head]
        if security and security[0]['status'] == 'completed':
            need(security[0]['conclusion'] == 'success', 'security_failed')
            break
        need(time.monotonic() < deadline, 'security_not_confirmed')
        time.sleep(5)
    return {'operation': OP, 'head': head, 'base': BASE, 'claim': CLAIM,
            'main': github('/git/ref/heads/main')['object']['sha'],
            'security_run': security[0]['id'], 'helper_blob': NEW,
            'supplier_searches': 0, 'db_writes': 0}


def regular(path):
    need(path.is_file() and not path.is_symlink() and path.stat().st_nlink == 1, 'not_regular_' + path.name)


def inventory(parent, site, target):
    paths = [site / 'api-v2.php']
    for root in (parent / 'app/integrations', parent / 'v2/data'):
        need(root.is_dir() and not root.is_symlink(), 'runtime_directory')
        paths.extend(p for p in root.rglob('*') if p.is_file() and p != target)
    out = {}
    for p in sorted(set(paths)):
        regular(p)
        out[str(p.relative_to(parent))] = sha(p.read_bytes())
    return out


def replace_if(path, expected, replacement):
    regular(path)
    original = path.read_bytes()
    need(blob(original) == expected, 'predecessor_mismatch')
    metadata = path.stat()
    need(metadata.st_uid == os.geteuid(), 'file_owner_changed')
    fd, temporary = tempfile.mkstemp(prefix='.autosave-3072-', suffix='.php', dir=str(path.parent))
    try:
        with os.fdopen(fd, 'wb') as f:
            f.write(replacement)
            f.flush()
            os.fsync(f.fileno())
            os.fchmod(f.fileno(), stat.S_IMODE(metadata.st_mode))
        # Prevent another writer's newer file from being replaced.
        need(path.stat().st_ino == metadata.st_ino and path.read_bytes() == original, 'concurrent_writer')
        os.replace(temporary, path)
        directory_fd = os.open(path.parent, os.O_RDONLY)
        try:
            os.fsync(directory_fd)
        finally:
            os.close(directory_fd)
        need(path.read_bytes() == replacement, 'postwrite_bytes')
    finally:
        Path(temporary).unlink(missing_ok=True)


PHP_PROBE = r'''
require $argv[1];
$m = new ReflectionMethod(AnyTourTourvisorOfferAutosaveV1::class, 'entryFromTour');
$now = new DateTimeImmutable('2026-09-19T09:00:00Z');
$labels = ['PEGAS Touristik','Coral Travel','Sunmar','ANEX','FUN&SUN','Библио-Глобус','Интурист','Pegasus Holidays','Tez Tour','Русский Экспресс','Space Travel','Другой оператор'];
$n=0;
foreach($labels as $i=>$label){
 $tour=['id'=>'OFFLINE-3072-'.($i+1),'date'=>'2026-10-05','nights'=>7,'price'=>150824,'currency'=>'RUB','meal'=>['id'=>7,'name'=>'Все включено'],'roomType'=>'Standard Room','operator'=>['name'=>$label],'fuelCharge'=>0];
 $e=$m->invoke(null,987654321,3417,77,$tour,2,0,[],'2026-09-19T09:00:00Z',$now);
 if(!is_array($e)||$e['offer']['provider']!=='tourvisor'||$e['offer']['operator']['raw']!==$label)throw new RuntimeException('operator_not_retained');
 $d=AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer($e['offer'],$e['retained'],$e['current'],$now->getTimestamp(),$e['priced_money']);
 if($d['finalPriceReady']!==true||$d['price']!=='150824'||$d['finalPrice']!=='150824')throw new RuntimeException('price_contract');
 $n++;
}
echo json_encode(['installed_operator_cases'=>$n,'supplier_calls'=>0,'db_writes'=>0]);
'''


def probe(path):
    subprocess.run(['php', '-l', str(path)], check=True, stdout=subprocess.DEVNULL)
    output = subprocess.check_output(['php', '-r', PHP_PROBE, str(path)], timeout=30)
    result = json.loads(output)
    need(result.get('installed_operator_cases') == 12, 'installed_probe')
    return result


def remote(upload, run):
    need(re.fullmatch('[1-9][0-9]{5,14}', run) is not None, 'run_id')
    upload = Path(upload)
    need(str(upload) == '/tmp/' + OP + '.' + run + '.php', 'upload_path')
    regular(upload)
    new = upload.read_bytes()
    need(blob(new) == NEW and len(new) < 65536, 'upload_blob')
    home = Path.home().resolve()
    parent = home / 'www'
    site = parent / 'anytoour.ru'
    target = parent / HELPER
    for p in (parent, site, parent / 'app', target.parent):
        need(p.is_dir() and not p.is_symlink(), 'no_symlink_directory')
    regular(target)
    need(sha((site / 'api-v2.php').read_bytes()) == API_SHA, 'active_gateway_changed')
    ledger_parent = home / '.anytour-ops'
    need(ledger_parent.is_dir() and not ledger_parent.is_symlink(), 'ledger_parent')
    ledger = ledger_parent / OP
    ledger.mkdir(mode=0o700)  # Existing operation is terminal, never overwritten.
    save(ledger / 'reservation.json', {'operation': OP, 'run': run, 'source': SOURCE, 'blob': NEW})
    before = inventory(parent, site, target)
    old_bytes = target.read_bytes()
    before_blob = blob(old_bytes)
    receipt = {'operation': OP, 'run': run, 'source': SOURCE, 'release': BASE,
               'before_blob': before_blob, 'after_blob': None, 'supplier_searches': 0,
               'db_writes': 0, 'public_file_writes': [], 'protected_files': len(before),
               'status': 'not_activated'}
    activated = False
    try:
        need(before_blob in (OLD, NEW), 'unknown_installed_helper')
        if before_blob == OLD:
            backup = ledger / 'helper.before.php'
            with backup.open('xb') as f:
                f.write(old_bytes); f.flush(); os.fsync(f.fileno())
            backup.chmod(0o600)
            activated = True  # Includes errors after rename: rollback checks exact bytes.
            replace_if(target, OLD, new)
        receipt.update(probe(target))
        need(inventory(parent, site, target) == before, 'neighbor_files_changed')
        need(blob(target.read_bytes()) == NEW, 'installed_file_drift')
        receipt.update(status='installed' if activated else 'already_installed',
                       after_blob=NEW, after_sha256=sha(target.read_bytes()),
                       runtime_file_writes=[HELPER] if activated else [],
                       neighbors_unchanged=True, backup_retained=activated,
                       live_supplier_search_verified=False)
    except Exception as exc:
        receipt.update(status='failed_not_accepted', reason=str(exc)[:160])
        if activated:
            try:
                if target.read_bytes() == new:
                    replace_if(target, NEW, old_bytes)
                    receipt.update(rollback='restored_exact_predecessor')
                else:
                    need(target.read_bytes() == old_bytes, 'rollback_foreign_bytes')
                    receipt.update(rollback='not_needed_predecessor_unchanged')
            except Exception:
                receipt.update(rollback='unknown_stop_no_replay')
        raise
    finally:
        save(ledger / 'receipt.json', receipt)
        print(json.dumps(receipt, ensure_ascii=False), flush=True)
        upload.unlink(missing_ok=True)


def execute():
    authorized = authorize(False)
    tmp = Path(os.environ['RUNNER_TEMP'])
    save(tmp / 'preflight.json', authorized)
    key = tmp / 'autosave3072.key'
    raw = os.environ['ANYTOOUR_DEPLOY_SSH_KEY'].replace('\r', '')
    if 'PRIVATE KEY' not in raw:
        import base64
        raw = base64.b64decode(raw, validate=True).decode()
    key.write_text(raw.rstrip() + '\n'); key.chmod(0o600)
    host = os.environ['ANYTOOUR_DEPLOY_HOST']; user = os.environ['ANYTOOUR_DEPLOY_USER']
    need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]*', host) is not None and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]*', user) is not None, 'ssh_identity')
    opts = ['-i', str(key), '-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=accept-new', '-o', 'UserKnownHostsFile=' + str(tmp / 'autosave3072.known_hosts'),
            '-o', 'ConnectTimeout=15', '-o', 'ServerAliveInterval=15', '-o', 'ServerAliveCountMax=3']
    destination = user + '@' + host
    run = os.environ['GITHUB_RUN_ID']; remote_upload = '/tmp/' + OP + '.' + run + '.php'
    receipt = {'operation': OP, 'status': 'not_activated', 'supplier_searches': 0, 'db_writes': 0}
    try:
        subprocess.run(['ssh-keygen', '-y', '-f', str(key)], stdout=subprocess.DEVNULL, check=True)
        subprocess.run(['scp', *opts, HELPER, destination + ':' + remote_upload], check=True, timeout=45)
        command = 'python3 - --remote ' + shlex.quote(remote_upload) + ' ' + shlex.quote(run)
        execution = subprocess.run(['ssh', '-T', *opts, destination, command], input=Path(__file__).read_bytes(), stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=150)
        lines = execution.stdout.decode().splitlines()
        if lines:
            receipt = json.loads(lines[-1])
        need(execution.returncode == 0, 'remote_operation_failed')
        readback = subprocess.check_output(['ssh', '-T', *opts, destination,
            'cat "$HOME/.anytour-ops/' + OP + '/receipt.json"'], timeout=30)
        need(json.loads(readback) == receipt, 'independent_receipt_mismatch')
        need(receipt.get('after_blob') == NEW and receipt.get('neighbors_unchanged') is True, 'readback_incomplete')
        receipt['independent_receipt_readback'] = True
        need(github('/git/ref/heads/main')['object']['sha'] == authorized['main'], 'main_changed_during_operation')
    except Exception as exc:
        # Never invent rollback if the connection failed before receiving a receipt.
        receipt['runner_error'] = type(exc).__name__ + (':' + str(exc)[:120] if isinstance(exc, RuntimeError) else '')
        raise
    finally:
        key.unlink(missing_ok=True)
        save(tmp / 'receipt.json', receipt)
        print(json.dumps(receipt, ensure_ascii=False))


def self_test():
    with tempfile.TemporaryDirectory() as d:
        p = Path(d) / 'helper.php'; p.write_bytes(b'old'); p.chmod(0o640)
        replace_if(p, blob(b'old'), b'new')
        need(p.read_bytes() == b'new' and stat.S_IMODE(p.stat().st_mode) == 0o640, 'test_atomic_mode')
        try:
            replace_if(p, blob(b'old'), b'bad')
        except RuntimeError:
            pass
        else:
            raise RuntimeError('test_drift_guard')
        need(p.read_bytes() == b'new', 'test_no_drift_write')
        replace_if(p, blob(b'new'), b'old')
        need(p.read_bytes() == b'old', 'test_rollback')
    print('HELPER_INSTALL_OFFLINE_OK atomic=1 mode=1 predecessor=1 rollback=1')


if __name__ == '__main__':
    if sys.argv[1:] == ['--self-test']:
        self_test()
    elif sys.argv[1:] == ['--authorize']:
        print(json.dumps(authorize(True)))
    elif sys.argv[1:] == ['--execute']:
        execute()
    elif len(sys.argv) == 4 and sys.argv[1] == '--remote':
        remote(sys.argv[2], sys.argv[3])
    else:
        raise SystemExit('usage')
