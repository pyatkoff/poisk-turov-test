#!/usr/bin/env python3
from __future__ import annotations

import argparse
import base64
import hashlib
import io
import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import tarfile
import urllib.request

REPO = 'pyatkoff/poisk-turov-test'
FEATURE = 'feature/anex-search-adapter-20260907'
OWNER_ID = 226193297
ISSUE = 2530
PREFIX = '/run-int-server-v1 '
OP_RE = re.compile(r'\Aint-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*\Z')
SHA_RE = re.compile(r'\A[a-f0-9]{40}\Z')

FIXED = [
    'scripts/ops/anex_local_offer_collect.php',
    'scripts/ops/anex_local_offer_demand_fill.php',
    'scripts/ops/anex_local_offer_demand_queue.php',
    'scripts/ops/andromeda_local_offer_collect.php',
    'v2/api-anex-search3-preview.php',
    'v2/api-andromeda-search3-preview.php',
    'v2/data/hotel-details-v1.php',
]

def need(condition: bool, reason: str) -> None:
    if not condition:
        raise ValueError(reason)

def integer(value: str, lo: int, hi: int, reason: str) -> int:
    need(re.fullmatch(r'(?:0|[1-9][0-9]{0,9})', value) is not None, reason)
    n = int(value)
    need(lo <= n <= hi, reason)
    return n

def date(value: str) -> str:
    import datetime as dt
    need(re.fullmatch(r'\d{4}-\d{2}-\d{2}', value) is not None, 'date')
    try:
        dt.date.fromisoformat(value)
    except ValueError as exc:
        raise ValueError('date') from exc
    return value

def parse_command(body: str) -> dict:
    need(body.startswith(PREFIX), 'command_prefix')
    parts = body[len(PREFIX):].split()
    need(len(parts) >= 3, 'command_shape')
    source, mode, operation = parts[:3]
    need(SHA_RE.fullmatch(source) is not None, 'source_sha')
    need(OP_RE.fullmatch(operation) is not None, 'operation_id')
    if mode == 'anex-demand':
        need(len(parts) == 4, 'command_shape')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'limit': integer(parts[3], 1, 20, 'anex_limit')}
    if mode == 'andromeda-scope':
        need(len(parts) == 12, 'command_shape')
        departure = integer(parts[3], 1, 999999999, 'departure')
        country = integer(parts[4], 1, 999999999, 'country')
        date_from, date_to = date(parts[5]), date(parts[6])
        need(date_to >= date_from, 'date_range')
        nights = integer(parts[7], 1, 28, 'nights')
        adults = integer(parts[8], 1, 6, 'adults')
        meal = parts[9]
        need(re.fullmatch(r'(?:-|[A-Za-z0-9_,&]{1,32})', meal) is not None, 'meal')
        region = integer(parts[10], 0, 999999999, 'region')
        captures = integer(parts[11], 0, 0, 'captures')
        return {
            'source_sha': source, 'mode': mode, 'operation_id': operation,
            'departure': departure, 'country': country, 'date_from': date_from,
            'date_to': date_to, 'nights': nights, 'adults': adults,
            'meal': '' if meal == '-' else meal, 'region': region,
            'max_captures': captures,
        }
    raise ValueError('mode')

def api_get(path: str, token: str) -> dict:
    request = urllib.request.Request(
        'https://api.github.com/repos/' + REPO + path,
        headers={
            'Authorization': 'Bearer ' + token,
            'Accept': 'application/vnd.github+json',
            'X-GitHub-Api-Version': '2022-11-28',
        },
    )
    with urllib.request.urlopen(request, timeout=30) as response:
        return json.load(response)

def checked_event(token: str, event: dict, control_sha: str) -> dict:
    if event.get('comment'):
        body = event['comment'].get('body', '')
        need(event.get('issue', {}).get('number') == ISSUE
             and not event.get('issue', {}).get('pull_request'), 'issue')
        need(event.get('comment', {}).get('user', {}).get('id') == OWNER_ID, 'owner')
        need(event.get('comment', {}).get('author_association') == 'OWNER',
             'owner_association')
        fresh = api_get('/issues/comments/' + str(event['comment']['id']), token)
        need(fresh.get('body') == body and fresh.get('user', {}).get('id') == OWNER_ID,
             'comment_changed')
    else:
        body = os.environ.get('INT_OWNER_COMMAND', '')
        need(os.environ.get('GITHUB_ACTOR') == 'pyatkoff'
             and os.environ.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff',
             'dispatch_owner')
    command = parse_command(body)
    main = api_get('/git/ref/heads/main', token)['object']['sha']
    need(main == control_sha, 'main_changed')
    feature = api_get('/git/ref/heads/' + FEATURE, token)['object']['sha']
    need(feature == command['source_sha'], 'feature_changed')
    return command

def bundle_source(source_root: Path) -> tuple[bytes, dict[str, str]]:
    source_root = source_root.resolve()
    app = source_root / 'app/integrations'
    need(app.is_dir() and not app.is_symlink(), 'source_app')
    paths = []
    for path in sorted(app.rglob('*.php')):
        need(path.is_file() and not path.is_symlink(), 'source_link')
        paths.append(path)
    for relative in FIXED:
        path = source_root / relative
        need(path.is_file() and not path.is_symlink(), 'source_missing:' + relative)
        paths.append(path)
    files = {}
    for path in paths:
        relative = path.relative_to(source_root).as_posix()
        need(path.resolve() == source_root / relative, 'source_escape')
        files[relative] = path
    need(len(files) >= 20, 'source_inventory_small')
    hashes = {}
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative in sorted(files):
            data = files[relative].read_bytes()
            need(0 < len(data) <= 2 * 1024 * 1024, 'source_size:' + relative)
            hashes[relative] = hashlib.sha256(data).hexdigest()
            info = tarfile.TarInfo(relative)
            info.size = len(data); info.mode = 0o600; info.mtime = 0
            info.uid = info.gid = 0; info.uname = info.gname = ''
            archive.addfile(info, io.BytesIO(data))
        manifest = json.dumps(
            {'schema_version': 1, 'files': hashes},
            sort_keys=True, separators=(',', ':')
        ).encode()
        info = tarfile.TarInfo('manifest.json')
        info.size = len(manifest); info.mode = 0o600; info.mtime = 0
        archive.addfile(info, io.BytesIO(manifest))
    return output.getvalue(), hashes

REMOTE = r"""
import hashlib,json,os,pathlib,re,subprocess,sys,tarfile,time
home=pathlib.Path.home()
project=home/'www/anytoour.ru'
private=home/'.anytoour-int-executor'
payload=json.loads(sys.stdin.read())
operation=payload['operation_id']; mode=payload['mode']; source=payload['source_sha']
result={'schema_version':1,'operation_id':operation,'source_sha':source,'mode':mode,
        'status':'blocked','supplier_calls':'unknown','database_writes':'unknown',
        'booking_calls':0,'lead_calls':0}
def fail(reason): raise RuntimeError(reason)
def safe_file(path,max_size=4*1024*1024):
    return path.is_file() and not path.is_symlink() and path.stat().st_size<=max_size
def fingerprints():
    out={}
    for rel in ['index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php']:
        path=project/rel
        out[rel]=hashlib.sha256(path.read_bytes()).hexdigest() if safe_file(path) else None
    return out
def db_summary(provider):
    php=r'''declare(strict_types=1);error_reporting(0);ini_set('display_errors','0');
$root=getenv('HOME').'/www/anytoour.ru';
require_once is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
$db=v2_data_db();$p=$argv[1];
$q=function(string $sql)use($db,$p){$s=$db->prepare($sql);$s->execute([$p]);return $s->fetchColumn();};
$r=['schema_version'=>(int)$db->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn(),
'rows_total'=>(int)$q('SELECT COUNT(*) FROM anytour_offers WHERE provider=?'),
'active_rows'=>(int)$q('SELECT COUNT(*) FROM anytour_offers WHERE provider=? AND is_active=1'),
'current_ready_rows'=>(int)$q('SELECT COUNT(*) FROM anytour_offers WHERE provider=? AND is_active=1 AND final_price_ready=1 AND expires_at>UTC_TIMESTAMP()'),
'current_confirmation_rows'=>(int)$q('SELECT COUNT(*) FROM anytour_offers WHERE provider=? AND is_active=1 AND final_price_ready=0 AND expires_at>UTC_TIMESTAMP()'),
'current_ready_hotels'=>(int)$q('SELECT COUNT(DISTINCT anytour_hotel_id) FROM anytour_offers WHERE provider=? AND is_active=1 AND final_price_ready=1 AND expires_at>UTC_TIMESTAMP()'),
'completed_refreshes'=>(int)$q("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider=? AND status='completed'"),
'latest_completed_at'=>$q("SELECT MAX(completed_at) FROM anytour_offer_refreshes WHERE provider=? AND status='completed'")];
echo json_encode($r,JSON_THROW_ON_ERROR);'''
    run=subprocess.run(['php','-r',php,provider],cwd=project,capture_output=True,text=True,timeout=30)
    if run.returncode or run.stderr: fail('db_readback_failed')
    return json.loads(run.stdout)
def local_read(scopes):
    rows=[]
    for scope in scopes[:20]:
        php=r'''declare(strict_types=1);error_reporting(0);ini_set('display_errors','0');
$root=getenv('HOME').'/www/anytoour.ru';
$f=$root.'/_preview/search3-local-candidate/data/search3-local-results-read-v1.php';
if(!is_file($f)||is_link($f))throw new RuntimeException('local_reader_missing');
require_once $f;$p=json_decode($argv[1],true,32,JSON_THROW_ON_ERROR);
$r=search3_local_results_build(v2_data_db(),$p,new DateTimeImmutable('now',new DateTimeZone('UTC')));
echo json_encode(['scopeDigest'=>$r['scopeDigest'],'hotelCount'=>$r['hotelCount'],
'offerCount'=>$r['offerCount'],'storedOfferCount'=>$r['storedOfferCount'],
'providerOfferCounts'=>(array)$r['providerOfferCounts'],
'withheldOfferCount'=>$r['withheldOfferCount'],'matchMode'=>$r['matchMode']],JSON_THROW_ON_ERROR);'''
        params={'departureId':str(scope['departureId']),'countryId':str(scope['countryId']),
          'dateFrom':scope['dateFrom'],'dateTo':scope['dateTo'],
          'nightsFrom':scope['nights'],'nightsTo':scope['nights'],
          'adults':scope['adults'],'childs':scope.get('childAges',[]),'meal':'',
          'hotelCategory':'','hotelRating':'','hotelTypes':[],'hotelIds':[],
          'hotelServices':[],'arrivalId':'',
          'regionIds':[] if scope.get('regionId') is None else [str(scope['regionId'])],
          'subregionIds':[],'operatorIds':[],'priceFrom':'','priceTo':'',
          'currency':'RUB','onlyCharter':False,'onlyDirect':False}
        run=subprocess.run(['php','-r',php,json.dumps(params,separators=(',',':'))],
                           cwd=project,capture_output=True,text=True,timeout=30)
        if run.returncode or run.stderr: fail('local_readback_failed')
        rows.append(json.loads(run.stdout))
    return rows
try:
    if not re.fullmatch(r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*',operation):
        fail('operation_invalid')
    if not re.fullmatch(r'[a-f0-9]{40}',source): fail('source_invalid')
    if project.resolve()!=project or project.name!='anytoour.ru': fail('project_invalid')
    private.mkdir(mode=0o700,exist_ok=True)
    op=private/operation
    if op.exists() or op.is_symlink(): fail('operation_exists_no_replay')
    op.mkdir(mode=0o700)
    reservation={'operation_id':operation,'source_sha':source,'mode':mode,'reserved_at':int(time.time())}
    (op/'reservation.json').write_text(json.dumps(reservation,sort_keys=True))
    os.chmod(op/'reservation.json',0o600)
    result['status']='reserved'
    before=fingerprints(); result['production_before']=before
    archive=pathlib.Path(payload['archive']); stage=op/'source'; stage.mkdir(mode=0o700)
    with tarfile.open(archive,'r:gz') as package:
        members=package.getmembers()
        for member in members:
            pure=pathlib.PurePosixPath(member.name)
            if (not member.isfile() or member.issym() or member.islnk()
                    or pure.is_absolute() or '..' in pure.parts):
                fail('archive_entry')
        package.extractall(stage,filter='data')
    manifest=json.loads((stage/'manifest.json').read_text())
    files=manifest.get('files',{})
    if not isinstance(files,dict) or len(files)<20: fail('manifest')
    for relative,sha in files.items():
        path=stage/relative
        if (not safe_file(path,2*1024*1024)
                or hashlib.sha256(path.read_bytes()).hexdigest()!=sha):
            fail('source_hash')
    (op/'installed-source.json').write_text(json.dumps({'source_sha':source,'files':files},sort_keys=True))
    os.chmod(op/'installed-source.json',0o600)
    provider='anex' if mode=='anex-demand' else 'andromeda'
    result['before_db']=db_summary(provider)
    env={k:v for k,v in os.environ.items() if k not in ('ANEX_API_TOKEN','ANEX_B2B_TOKEN')}
    env['ANYTOUR_PROJECT_ROOT']=str(project)
    generation=str(2100000000-(int(hashlib.sha256(operation.encode()).hexdigest()[:6],16)%1000000))
    if mode=='anex-demand':
        command=['php',str(stage/'scripts/ops/anex_local_offer_demand_fill.php'),
          '--limit='+str(payload['limit']),'--lookback-hours=168','--horizon-days=21',
          '--max-expands=600','--max-apd=600','--generation-base='+generation]
    else:
        config=project/'_preview/search3-anex-candidate/.andromeda-private.php'
        if not safe_file(config,65536): fail('andromeda_private_config_missing')
        command=['php',str(stage/'scripts/ops/andromeda_local_offer_collect.php'),
          '--site-root='+str(project),'--private-config='+str(config),'--source-sha='+source,
          '--departure='+str(payload['departure']),'--country='+str(payload['country']),
          '--date-from='+payload['date_from'],'--date-to='+payload['date_to'],
          '--nights='+str(payload['nights']),'--adults='+str(payload['adults']),
          '--meal='+payload['meal'],'--generation='+generation,
          '--max-captures='+str(payload['max_captures']),'--max-capture-seconds=0',
          '--capture-mode=non_external_only']
        if payload['region']: command.append('--region='+str(payload['region']))
    run=subprocess.run(command,cwd=stage,env=env,capture_output=True,text=True,timeout=900)
    result['collector_exit']=run.returncode
    try: collector=json.loads(run.stdout.strip())
    except Exception: collector={'status':'unparseable'}
    result['collector']=collector
    result['collector_stderr_nonempty']=bool(run.stderr.strip())
    result['after_db']=db_summary(provider)
    if mode=='anex-demand':
        scopes=[x.get('scope',{}) for x in collector.get('results',[])
                if isinstance(x,dict) and isinstance(x.get('scope'),dict)]
    else:
        scopes=[{'departureId':payload['departure'],'countryId':payload['country'],
                 'regionId':payload['region'] or None,'dateFrom':payload['date_from'],
                 'dateTo':payload['date_to'],'nights':payload['nights'],
                 'adults':payload['adults'],'childAges':[]}]
    result['local_readback']=local_read(scopes) if scopes else []
    result['production_after']=fingerprints()
    if result['production_after']!=before: fail('production_drift')
    result['status']='complete' if run.returncode==0 else 'terminal_nonzero_no_replay'
    result['supplier_calls']='bounded_by_collector'
    result['database_writes']='collector_owned'
    result['production_unchanged']=True
except Exception as exc:
    if result.get('status')=='reserved':
        result['status']='unknown_no_replay';result['reason']=str(exc)
    elif result.get('status')=='blocked':
        result['reason']=str(exc)
    elif result.get('status') not in ('complete','terminal_nonzero_no_replay'):
        result['status']='unknown_no_replay';result['reason']=str(exc)
finally:
    try:
        if 'op' in globals() and op.exists():
            (op/'result.json').write_text(json.dumps(result,sort_keys=True,separators=(',',':')))
            os.chmod(op/'result.json',0o600)
    except Exception:
        pass
print(json.dumps(result,separators=(',',':')))
"""

def ssh_options(key: Path, known: Path) -> list[str]:
    return [
        '-T','-i',str(key),'-o','IdentitiesOnly=yes','-o','BatchMode=yes',
        '-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),
        '-o','GlobalKnownHostsFile=/dev/null','-o','ConnectTimeout=15',
        '-o','ServerAliveInterval=15','-o','ServerAliveCountMax=3','-o','LogLevel=ERROR'
    ]

def execute(command: dict, source_root: Path) -> dict:
    host, user, raw_key = (
        os.environ.get(name, '').strip()
        for name in ('INT_SSH_HOST','INT_SSH_USER','INT_SSH_KEY')
    )
    need(bool(host and user and raw_key), 'ssh_config')
    need(not host.startswith('-') and not user.startswith('-')
         and not any(c.isspace() for c in host + user), 'ssh_identity')
    bundle, manifest = bundle_source(source_root)
    output = Path(os.environ['RUNNER_TEMP']) / 'int-server-executor'
    output.mkdir(mode=0o700, exist_ok=True)
    key, known, archive = output/'key', output/'known_hosts', output/'source.tar.gz'
    key.write_text(raw_key.rstrip() + '\n'); key.chmod(0o600)
    subprocess.run(['ssh-keygen','-y','-f',str(key)], stdout=subprocess.DEVNULL,
                   stderr=subprocess.PIPE, check=True, timeout=10)
    scan = subprocess.run(['ssh-keyscan','-T','15','-t','ed25519',host],
                          capture_output=True, check=True, timeout=20).stdout
    need(bool(scan), 'ssh_hostkey')
    known.write_bytes(scan); known.chmod(0o600)
    archive.write_bytes(bundle); archive.chmod(0o600)
    options = ssh_options(key, known)
    remote_archive = (
        '/tmp/' + command['operation_id'] + '-' +
        hashlib.sha256(bundle).hexdigest()[:16] + '.tar.gz'
    )
    subprocess.run(['scp',*options,str(archive),user+'@'+host+':'+remote_archive],
                   check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE,
                   timeout=60)
    payload = dict(command)
    payload['archive'] = remote_archive
    payload['manifest_sha256'] = hashlib.sha256(
        json.dumps(manifest,sort_keys=True,separators=(',',':')).encode()
    ).hexdigest()
    encoded = base64.b64encode(REMOTE.encode()).decode()
    remote_command = (
        "python3 -c 'import base64;exec(base64.b64decode(\"" + encoded + "\"))'"
    )
    try:
        run = subprocess.run(
            ['ssh',*options,'-l',user,host,remote_command],
            input=json.dumps(payload,separators=(',',':')), text=True,
            capture_output=True, timeout=1000
        )
        need(run.returncode == 0, 'ssh_remote_exit')
        result = json.loads(run.stdout.strip())
        need(isinstance(result,dict)
             and result.get('operation_id') == command['operation_id']
             and result.get('source_sha') == command['source_sha'],
             'remote_receipt')
        (output/'result.json').write_text(
            json.dumps(result,sort_keys=True,indent=2) + '\n'
        )
        return result
    finally:
        subprocess.run(
            ['ssh',*options,'-l',user,host,'rm -f -- '+shlex.quote(remote_archive)],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=30
        )
        key.unlink(missing_ok=True); known.unlink(missing_ok=True)

def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('--parse-only', action='store_true')
    parser.add_argument('--source-root', default='source')
    args = parser.parse_args()
    event = json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
    token = os.environ.get('GH_TOKEN','')
    need(bool(token), 'gh_token')
    command = checked_event(token, event, os.environ['GITHUB_SHA'])
    if args.parse_only:
        for key,value in command.items():
            print(f'{key}={value}')
        return
    result = execute(command, Path(args.source_root))
    print(json.dumps(result,sort_keys=True))
    if result.get('status') not in ('complete','terminal_nonzero_no_replay'):
        raise SystemExit(1)

if __name__ == '__main__':
    main()
