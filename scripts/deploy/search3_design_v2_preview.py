"""Two-phase, trusted-main provisioning of a separate design v2 preview.

First derive/retain the checked artifact without SSH secrets. The publisher then
rechecks provenance and exact derived bytes before any server write. Only the
reviewed route boundary literals change; the supplier APIs and existing preview stay put.
"""
from __future__ import annotations
import copy
import base64
import gzip
import io
import json
import os
from pathlib import Path
import re
import secrets
import shlex
import shutil
import subprocess
import sys
import tarfile
import zipfile
from search3_design_v2_preview_remote import (ROUTE, INVARIANTS, SOURCE_SHA, SOURCE_TREE, BUILD_RUN,
    ARTIFACT_ID, SOURCE_ZIP_SHA256, digest, inventory, json_bytes, need, verify)

PREFIX = '/create-search3-design-v2-preview '
OLD_ROUTE = '/_preview/search3-site-candidate/'
REPO = 'pyatkoff/poisk-turov-test'
RELEASE = 'release/search3-design-v2'

# Exact changes to the accepted compiled donor. Historical receipts stay intact.
ROUTE_TRANSFORMS = (
    ('site-path-v1.php', b'#^(/_preview/search3-site-candidate)(?:/|$)#',
     b'#^(/_preview/search3-design-v2-candidate)(?:/|$)#'),
    ('visual-search/index.php', b'/_preview/search3-next-candidate/visual-search/',
     b'/_preview/search3-design-v2-candidate/visual-search/'),
    ('prototype-search/config.js', b'search3-(?:next|local)-candidate',
     b'search3-(?:design-v2|local)-candidate'),
    ('prototype-search/data.js', b'/_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php',
     b'/_preview/search3-design-v2-candidate/data/local-tv-catalog-read-v1.php'),
    ('prototype-search/data.js', b"'/_preview/search3-next-candidate/'",
     b"'/_preview/search3-design-v2-candidate/'"),
    ('search3-canonical-profiles-v1.js', b'/_preview/search3-next-candidate/visual-search/',
     b'/_preview/search3-design-v2-candidate/visual-search/'),
    ('data/local-tv-catalog-read-v1.php', b'/_preview/search3-next-candidate/data',
     b'/_preview/search3-design-v2-candidate/data'),
)
LEDGER_PATH = 'visual-search/asset-size.json'
SOURCE_LEDGER_SHA256 = 'e865dc4d8ce6aec92d10c85b981e943bb960ac88dd4e782c9ed44717749f3516'
LEDGER_JS = ('prototype-search/config.js', 'prototype-search/data.js', 'search3-canonical-profiles-v1.js')
CHANGED_PAYLOAD_FILES = tuple(sorted({name for name, _, _ in ROUTE_TRANSFORMS} | {LEDGER_PATH}))



def checked_request(event, env):
    need(env.get('GITHUB_REPOSITORY') == REPO and env.get('GITHUB_REF') == 'refs/heads/main', 'trusted_main_only')
    need(env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff'
         and env.get('GITHUB_ACTOR_ID') == '226193297', 'owner_only')
    need(env.get('GITHUB_RUN_ATTEMPT') == '1', 'no_replay')
    need(event.get('repository', {}).get('full_name') == REPO and event['repository'].get('id') == 1345518271, 'repository_identity')
    need(event.get('sender', {}).get('id') == 226193297 and event['sender'].get('login') == 'pyatkoff', 'sender_identity')
    need(env.get('GITHUB_EVENT_NAME') == 'issue_comment' and event.get('action') == 'created', 'new_command_only')
    issue = event.get('issue', {}); c = event.get('comment', {})
    need(issue.get('number') == 4217 and 'pull_request' not in issue, 'coordination_only')
    need(c.get('user', {}).get('id') == 226193297 and c.get('author_association') == 'OWNER', 'comment_owner')
    body = c.get('body', '')
    need(isinstance(body, str) and body.startswith(PREFIX) and '\n' not in body and '\r' not in body, 'command_syntax')
    parts = body[len(PREFIX):].split(' ')
    need(len(parts) == 4 and all(re.fullmatch('[0-9a-f]{40}', x) for x in parts[:2])
         and all(re.fullmatch('[1-9][0-9]{0,14}', x) for x in parts[2:]), 'exact_pins')
    need(parts[0] == SOURCE_SHA and int(parts[2]) == BUILD_RUN and int(parts[3]) == ARTIFACT_ID, 'accepted_donor_only')
    return {'source_sha': parts[0], 'release_sha': parts[1], 'build_run': int(parts[2]),
            'artifact_id': int(parts[3]), 'deploy_run': int(env['GITHUB_RUN_ID']), 'attempt': 1}


def gzip_lengths(data):
    # Match the accepted build's Node zlib level9 accounting, not Python gzip.
    script = ('const fs=require("node:fs"),z=require("node:zlib");'
              'const a=JSON.parse(fs.readFileSync(0,"utf8"));'
              'process.stdout.write(JSON.stringify(a.map(s=>z.gzipSync(Buffer.from(s,"base64"),{level:9}).length)));')
    r = subprocess.run(['node', '-e', script], input=json_bytes([base64.b64encode(b).decode() for b in data]),
                       capture_output=True, timeout=30)
    need(r.returncode == 0, 'node_gzip_accounting_failed')
    values = json.loads(r.stdout)
    need(isinstance(values,list) and len(values) == len(data)
         and all(type(n) is int and n > 0 for n in values), 'node_gzip_accounting_invalid')
    return values


def update_served_ledger(source, output):
    p = source/'payload'/LEDGER_PATH; ledger = json.loads(p.read_bytes())
    need(digest(p.read_bytes()) == SOURCE_LEDGER_SHA256, 'source_ledger_drift')
    original = copy.deepcopy(ledger)
    entries = {f['target']: f for f in ledger['files']}
    need(len(entries) == len(ledger['files']) and all(n in entries for n in LEDGER_JS), 'served_ledger_inventory')
    before = [(source/'payload'/n).read_bytes() for n in LEDGER_JS]
    after = [(output/'payload'/n).read_bytes() for n in LEDGER_JS]
    lengths = gzip_lengths(before+after)
    for i,name in enumerate(LEDGER_JS):
        f = entries[name]
        need(f['sha256'] == digest(before[i]) and f['served'] == len(before[i])
             and f['gzip_after'] == lengths[i], 'source_ledger_accounting:' + name)
        f.update(sha256=digest(after[i]), served=len(after[i]), gzip_after=lengths[i+len(LEDGER_JS)])
    graphs = {**ledger['graphs'], 'complete_live': ledger['graphs']['live']+ledger['graphs']['ondemand'],
              'complete_offline': ledger['graphs']['offline']+ledger['graphs']['ondemand']}
    for name,graph in graphs.items():
        for field in ('served','gzip_after'):
            ledger['totals'][name][field] = sum(f[field] for f in ledger['files'] if f['src'] in graph)
    # All source/provenance/binding/raw/CSS/offline statistics stay byte-for-byte values.
    permitted_rows = set(LEDGER_JS)
    check = copy.deepcopy(ledger)
    for i,f in enumerate(check['files']):
        if f['target'] in permitted_rows:
            for field in ('sha256','served','gzip_after'): f[field] = original['files'][i][field]
    for name in ('live','complete_live'):
        for field in ('served','gzip_after'): check['totals'][name][field] = original['totals'][name][field]
    need(check == original, 'unexpected_ledger_delta')
    (output/'payload'/LEDGER_PATH).write_bytes((json.dumps(ledger,ensure_ascii=False,indent=2)+'\n').encode())


def derive(source, output, request, zip_digest):
    """Input is the original artifact already verified by prepare_zip()."""
    need(not output.exists(), 'derived_output_exists')
    shutil.copytree(source, output)
    need(request['source_sha'] == SOURCE_SHA and request.get('source_tree') == SOURCE_TREE
         and request['build_run'] == BUILD_RUN and request['artifact_id'] == ARTIFACT_ID
         and zip_digest == SOURCE_ZIP_SHA256, 'accepted_donor_only')
    for name, old, new in ROUTE_TRANSFORMS:
        p = output / 'payload' / name; before = p.read_bytes()
        need(before.count(old) == 1 and new not in before, 'route_source_drift:' + name)
        p.write_bytes(before.replace(old, new))
    need(b'/_preview/search3-next-candidate/visual-search/' not in
         (output / 'payload/visual-search/index.php').read_bytes(), 'residual_old_visual_route')
    update_served_ledger(source,output)
    for f in (output / 'payload').rglob('*'):
        if f.is_file():
            need(OLD_ROUTE.rstrip('/').encode() not in f.read_bytes(), 'residual_old_preview_route')
    previous = inventory(source / 'payload'); need(len(previous) == 845, 'source_file_count'); files = inventory(output / 'payload')
    need(previous.keys() == files.keys() and {n for n in files if files[n] != previous[n]} == set(CHANGED_PAYLOAD_FILES), 'unexpected_payload_delta')
    m = json.loads((source / 'control/manifest.json').read_bytes())
    m.update(target='search3-design-v2-preview', route=ROUTE, invariants=INVARIANTS,
        derived_from={'artifact_id': request['artifact_id'], 'build_run': request['build_run'],
                      'ZIP_sha256': zip_digest, 'transform': 'design-v2-route-and-ledger-v1',
                      'changed_payload_files': list(CHANGED_PAYLOAD_FILES)})
    m['files'] = [{'path': n, 'sha256': h, 'size': (output / 'payload' / n).stat().st_size} for n, h in files.items()]
    m['file_count'] = len(files)
    (output / 'control/manifest.json').write_bytes(json_bytes(m))
    (output / 'control/payload.sha256').write_text(''.join(f'{files[n]}  ./{n}\n' for n in sorted(files)))
    raw = io.BytesIO()
    with tarfile.open(fileobj=raw, mode='w', format=tarfile.USTAR_FORMAT) as t:
        for name in ('control/manifest.json', 'control/payload.sha256', *('payload/' + n for n in files)):
            data = (output / name).read_bytes(); info = tarfile.TarInfo(name)
            info.size = len(data); info.mode = 0o644; info.mtime = 0
            t.addfile(info, io.BytesIO(data))
    archive = gzip.compress(raw.getvalue(), mtime=0)
    q = {**request, 'source_ZIP_sha256': zip_digest, 'archive_sha256': digest(archive),
         'manifest_sha256': digest((output / 'control/manifest.json').read_bytes()),
         'payload_sha256': digest((output / 'control/payload.sha256').read_bytes()), 'file_count': len(files)}
    verify(output, q)
    return q, archive


def render_checks(root):
    # Build job only, before SSH secrets. The source is already isolated.
    php = "$_SERVER['DOCUMENT_ROOT']=$argv[1]; $_SERVER['HTTP_HOST']='anytoour.ru'; $_SERVER['SCRIPT_NAME']=$argv[2]; include $argv[3];"
    empty = root / 'empty-document-root'; empty.mkdir()
    for route in ('', 'poisk-turov/', 'poisk-turov-old/', 'country/', 'country/turkey/', 'hot/', 'contacts/', 'rb/', 'how-to-buy/'):
        path = root / 'payload' / route / 'index.php'
        r = subprocess.run(['php', '-d', 'display_errors=1', '-r', php, str(empty), ROUTE + route + 'index.php', str(path)],
                           capture_output=True, text=True, timeout=30)
        need(r.returncode == 0 and 'Fatal error' not in r.stdout, 'local_render:' + route)
        need('<meta name="robots" content="noindex,follow,max-image-preview:large' in r.stdout, 'local_noindex:' + route)
        if route in ('poisk-turov/', 'poisk-turov-old/'):
            for marker in ('id="tourSearch"', 'metrikaCounter:0', 'leadApi:"' + ROUTE + 'preview-lead-disabled.php"'):
                need(marker in r.stdout, 'local_search_guard')
            need('web-consultant/widget.js' not in r.stdout and OLD_ROUTE not in r.stdout, 'local_wrong_preview')
    path = root / 'payload/visual-search/index.php'
    r = subprocess.run(['php', '-d', 'display_errors=1', '-r', php, str(empty),
                        ROUTE + 'visual-search/index.php', str(path)],
                       capture_output=True, text=True, timeout=30)
    need(r.returncode == 0 and 'Fatal error' not in r.stdout
         and '<meta name="robots" content="noindex, nofollow, noarchive"' in r.stdout
         and '../prototype-search/data.js?v=' in r.stdout and './live-bridge.js?v=' in r.stdout
         and re.search(r'app.js\?v=[0-9a-f]{12}', r.stdout), 'visual_render_or_boundary')
    need('live-data.js' not in r.stdout and 'src="./data.js"' not in r.stdout
         and './fixture-data.js' not in r.stdout and 'Живой поиск ещё не подключён' not in r.stdout,
         'visual_must_use_reviewed_owner')
    for wrong in ('/poisk-turov/index.php', '/_preview/search3-next-candidate/visual-search/index.php',
                  '/_preview/search3-local-candidate/visual-search/index.php',
                  '/_preview/search3-site-candidate/visual-search/index.php'):
        denied = subprocess.run(['php', '-d', 'display_errors=1', '-r', php, str(empty), wrong, str(path)],
                                capture_output=True, text=True, timeout=30)
        need(denied.returncode == 0 and denied.stdout == 'Visual migration is only available in its separate preview.',
             'visual_old_route_fallback:' + wrong)
    empty.rmdir()


def authorized():
    from search3_preview_publish import Github, verify_provenance
    env = os.environ; event = json.loads(Path(env['GITHUB_EVENT_PATH']).read_text())
    q = checked_request(event, env); api = Github(env['GH_TOKEN'])
    fresh = api.get('/issues/comments/' + str(event['comment']['id']))
    need(fresh.get('body') == event['comment']['body'] and fresh.get('user', {}).get('id') == 226193297, 'command_revoked')
    tree, zip_hash = verify_provenance(api, q, env['GITHUB_SHA'], release_branch=RELEASE)
    return q, api, tree, zip_hash


def prepare():
    from search3_preview_publish import prepare_zip
    q, api, tree, zip_hash = authorized()
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-design-v2-prepare'; work.mkdir(mode=0o700)
    original = work / 'original'
    prepare_zip(api.download(q['artifact_id']), q, tree, zip_hash, original)
    ready, archive = derive(original / 'release', work / 'derived', q, zip_hash)
    render_checks(work / 'derived')
    retained = work / 'retained'; retained.mkdir()
    (retained / 'design-v2-preview.tar.gz').write_bytes(archive)
    (retained / 'request.json').write_bytes(json_bytes(ready))
    print(json.dumps({'status': 'prepared_not_published', 'route': ROUTE, 'archive_sha256': ready['archive_sha256'], 'changed_payload_files': list(CHANGED_PAYLOAD_FILES)}))


def live_checks(files):
    from search3_preview_publish import http
    for route in ('', 'poisk-turov/', 'poisk-turov-old/', 'country/', 'country/turkey/', 'hot/', 'contacts/', 'rb/', 'how-to-buy/'):
        status, html = http(ROUTE + route)
        need(status == 200 and '<meta name="robots" content="noindex,follow,max-image-preview:large' in html, 'live_route:' + route)
        if route in ('poisk-turov/', 'poisk-turov-old/'):
            for marker in ('id="tourSearch"', 'metrikaCounter:0', 'leadApi:"' + ROUTE + 'preview-lead-disabled.php"'):
                need(marker in html, 'live_search_guard')
            need(OLD_ROUTE not in html and 'web-consultant/widget.js' not in html, 'live_wrong_preview')
    status, text = http(ROUTE + 'preview-lead-disabled.php', b'{"synthetic":true}')
    need(status == 403 and 'PREVIEW_LEAD_DISABLED' in text, 'lead_not_disabled')
    for name in ('assets.php', 'search-page-v2.php', 'seo-config.php', 'data/db-v1.php', 'data/hotel-presentation-read-v1.php'):
        need(http(ROUTE + name)[0] == 403, 'internal_PHP_exposed:' + name)
    need(http(ROUTE + 'config.php')[0] in (403, 404), 'config_exposed')
    boundaries = {
        'data/hotel-details-read-v1.php?catalog=anytour&anytourHotelId=1':
            'Canonical catalogue is isolated to local preview',
        'data/search3-local-results-read-v1.php':
            'Local DB results are isolated to local preview',
    }
    for path, message in boundaries.items():
        status, body = http(ROUTE + path)
        need(status == 403 and json.loads(body) == {'ok': False, 'error': message},
             'LOCAL_only_data_boundary:' + path)
    catalog_path = ROUTE + 'data/local-tv-catalog-read-v1.php?oldLocalHotelIds%5B%5D=1'
    status, body = http(catalog_path)
    catalog = json.loads(body)
    need(status == 200 and catalog.get('ok') is True
         and catalog.get('source') == 'anytour-local-tv-catalog-v1' and catalog.get('catalog') == 'local-tv'
         and catalog.get('requestedIds') == [1]
         and all(isinstance(catalog.get(k),list) for k in ('items','links','missingIds')), 'new_catalog_read_boundary')
    for name in ('search3-results-filters-v1.css', 'search3-results-filters-v1.js', 'site-header-v2.css'):
        status, body = http(ROUTE + name + '?sha=' + files[name], binary=True)
        need(status == 200 and digest(body) == files[name], 'served_asset_hash')
    status, html = http(ROUTE + 'visual-search/')
    need(status == 200 and '../prototype-search/data.js?v=' in html and './live-bridge.js?v=' in html
         and './fixture-data.js' not in html and 'Живой поиск ещё не подключён' not in html
         and '<meta name="robots" content="noindex, nofollow, noarchive"' in html
         and re.search(r'app.js\?v=[0-9a-f]{12}', html), 'visual_live_entry')
    visual_assets = [name for name in files if name.startswith('visual-search/')
                     and not name.endswith('.php')]
    need(len(visual_assets) >= 29, 'visual_assets_missing')
    for name in visual_assets:
        status, body = http(ROUTE + name + '?sha=' + files[name], binary=True)
        need(status == 200 and digest(body) == files[name], 'visual_served_hash:' + name)
    # Verify the canonical dependencies actually served to the new visual too.
    for name in re.findall(r'(?:src|href)="\.\./([^"?]+\.(?:js|css))', html):
        need(name in files and '..' not in name.split('/'), 'visual_dependency_not_in_artifact')
        status, body = http(ROUTE + name + '?sha=' + files[name], binary=True)
        need(status == 200 and digest(body) == files[name], 'visual_dependency_hash:' + name)
    need(http('/')[0] == 200 and http('/poisk-turov/')[0] == 200, 'production_pages_unhealthy')
    return {'routes_200': 9, 'noindex': True, 'counter': 0, 'lead_HTTP': 403,
            'LOCAL_only_data_HTTP': 403, 'LOCAL_TV_catalog_HTTP': 200, 'supplier_searches': 0, 'real_leads': 0,
            'visual_route_200': True, 'visual_assets_verified': len(visual_assets),
            'visual_live_connected': '../prototype-search/data.js?v=' in html}


def publish():
    from search3_preview_publish import prepare_zip, command, http
    q, api, tree, zip_hash = authorized()
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-design-v2-publish'; work.mkdir(mode=0o700)
    original = work / 'original'
    prepare_zip(api.download(q['artifact_id']), q, tree, zip_hash, original)
    expected_q, archive = derive(original / 'release', work / 'expected', q, zip_hash)
    # Retained artifact belongs to this same trusted-main run; compare independently re-derived bytes.
    artifact_id = os.environ['READY_ARTIFACT_ID']
    need(re.fullmatch('[1-9][0-9]{0,14}', artifact_id), 'ready_artifact_id')
    meta = api.get('/actions/artifacts/' + artifact_id)
    need(not meta.get('expired') and meta.get('workflow_run', {}).get('id') == q['deploy_run']
         and meta['workflow_run'].get('head_sha') == os.environ['GITHUB_SHA']
         and meta.get('name') == 'search3-design-v2-ready-' + str(q['deploy_run']), 'ready_artifact_identity')
    packed = api.download(int(artifact_id)); need('sha256:' + digest(packed) == meta.get('digest'), 'ready_ZIP_hash')
    with zipfile.ZipFile(io.BytesIO(packed)) as z:
        need(len(z.infolist()) == 2 and set(z.namelist()) == {'design-v2-preview.tar.gz', 'request.json'}, 'ready_ZIP_inventory')
        need(sum(x.file_size for x in z.infolist()) <= 64 * 1024 * 1024, 'ready_ZIP_limit')
        need(z.read('design-v2-preview.tar.gz') == archive and json.loads(z.read('request.json')) == expected_q, 'derived_artifact_drift')
    q = expected_q; (work / 'design-v2-preview.tar.gz').write_bytes(archive)
    files = inventory(work / 'expected/payload')
    evidence = {'route': ROUTE, 'source_sha': q['source_sha'], 'release_sha': q['release_sha'],
                'source_artifact': q['artifact_id'], 'derived_artifact': int(artifact_id), 'derived_ZIP_sha256': digest(packed), 'status': 'checked_not_published'}
    host = os.environ['PREVIEW_HOST']; user = os.environ['PREVIEW_USER']
    need(re.fullmatch('[A-Za-z0-9][A-Za-z0-9.-]*', host) and re.fullmatch('[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}', user), 'SSH_identity')
    key = work / 'key'; known = work / 'known_hosts'
    raw = os.environ['PREVIEW_KEY'].replace('\r', '')
    if 'PRIVATE KEY' not in raw:
        import base64
        raw = base64.b64decode(raw, validate=True).decode()
    key.write_text(raw.rstrip() + '\n'); key.chmod(0o600)
    attempted = False; before = None
    try:
        command(['ssh-keygen', '-y', '-f', str(key)])
        known.write_bytes(command(['ssh-keyscan', '-T', '15', '-t', 'ed25519', host])); known.chmod(0o600)
        fingerprints = command(['ssh-keygen', '-lf', str(known), '-E', 'sha256']).decode().splitlines()
        need(len({x.split()[1] for x in fingerprints}) == 1, 'SSH_fingerprint_count')
        opts = ['-i', str(key), '-o', 'StrictHostKeyChecking=yes', '-o', 'UserKnownHostsFile=' + str(known),
                '-o', 'GlobalKnownHostsFile=/dev/null', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=15']
        source = Path(__file__).with_name('search3_design_v2_preview_remote.py').read_bytes()
        def remote(action, request):
            shell = 'python3 - ' + shlex.quote(action) + ' ' + shlex.quote(json.dumps(request, separators=(',', ':')))
            return json.loads(command(['ssh', *opts, user + '@' + host, shell], source, 180))
        binding = {'name': f"search3-design-v2-bind-{q['deploy_run']}-{secrets.token_hex(12)}.txt", 'nonce': secrets.token_hex(32)}
        remote('bind', binding)
        try:
            status, text = http('/_preview/' + binding['name'])
            need(status == 200 and text == binding['nonce'], 'SSH_HTTPS_binding')
        finally:
            remote('unbind', binding)
        before = remote('snapshot', {}); q['before'] = before
        need(before['target'] is None and before['owner'] is None, 'create_only_target_exists')
        need(api.get('/git/ref/heads/' + RELEASE)['object']['sha'] == q['release_sha'], 'release_changed_before_activation')
        need(api.get('/git/ref/heads/main')['object']['sha'] == os.environ['GITHUB_SHA'], 'main_changed_before_activation')
        q['archive'] = remote('upload', {})['archive']
        need(re.fullmatch(r'/tmp/search3-design-v2\.[A-Za-z0-9_-]+\.tar\.gz', q['archive']), 'remote_upload_path')
        command(['scp', *opts, str(work / 'design-v2-preview.tar.gz'), user + '@' + host + ':' + q['archive']])
        attempted = True; evidence['activation'] = remote('activate', q)
        evidence.update(live_checks(files))
        evidence['completion'] = remote('complete', q)
        evidence.update(status='published', production_unchanged=True, existing_preview_unchanged=True)
    except Exception as exc:
        evidence.update(status='failed_not_accepted', reason=str(exc))
        if attempted:
            try:
                evidence['rollback'] = remote('rollback', q)
            except Exception as rollback_error:
                evidence['rollback'] = {'status': 'unknown_stop_no_replay', 'reason': str(rollback_error)}
        raise
    finally:
        key.unlink(missing_ok=True); known.unlink(missing_ok=True)
        (work / 'evidence.json').write_bytes(json_bytes(evidence))
    print(json.dumps(evidence, indent=2))


if __name__ == '__main__':
    need(len(sys.argv) == 2 and sys.argv[1] in ('prepare', 'publish'), 'usage')
    {'prepare': prepare, 'publish': publish}[sys.argv[1]]()
