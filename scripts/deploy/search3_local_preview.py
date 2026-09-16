"""Two-phase, trusted-main provisioning of the separate local-catalog preview.

First derive/retain the checked artifact without SSH secrets. The publisher then
rechecks provenance and exact derived bytes before any server write. Only the
route-discovery literal changes; the supplier APIs and existing preview stay put.
"""
from __future__ import annotations
import copy
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
from search3_local_preview_remote import ROUTE, INVARIANTS, digest, inventory, json_bytes, need, verify

PREFIX = '/create-search3-local-preview '
OLD_ROUTE = '/_preview/search3-site-candidate/'
REPO = 'pyatkoff/poisk-turov-test'
RELEASE = 'release/search3-production-ready-v1'


def checked_request(event, env):
    need(env.get('GITHUB_REPOSITORY') == REPO and env.get('GITHUB_REF') == 'refs/heads/main', 'trusted_main_only')
    need(env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff'
         and env.get('GITHUB_ACTOR_ID') == '226193297', 'owner_only')
    need(env.get('GITHUB_RUN_ATTEMPT') == '1', 'no_replay')
    need(event.get('repository', {}).get('full_name') == REPO and event['repository'].get('id') == 1345518271, 'repository_identity')
    need(event.get('sender', {}).get('id') == 226193297 and event['sender'].get('login') == 'pyatkoff', 'sender_identity')
    need(env.get('GITHUB_EVENT_NAME') == 'issue_comment' and event.get('action') == 'created', 'new_command_only')
    issue = event.get('issue', {}); c = event.get('comment', {})
    need(issue.get('number') == 2530 and 'pull_request' not in issue, 'coordination_only')
    need(c.get('user', {}).get('id') == 226193297 and c.get('author_association') == 'OWNER', 'comment_owner')
    body = c.get('body', '')
    need(isinstance(body, str) and body.startswith(PREFIX) and '\n' not in body and '\r' not in body, 'command_syntax')
    parts = body[len(PREFIX):].split(' ')
    need(len(parts) == 4 and all(re.fullmatch('[0-9a-f]{40}', x) for x in parts[:2])
         and all(re.fullmatch('[1-9][0-9]{0,14}', x) for x in parts[2:]), 'exact_pins')
    return {'source_sha': parts[0], 'release_sha': parts[1], 'build_run': int(parts[2]),
            'artifact_id': int(parts[3]), 'deploy_run': int(env['GITHUB_RUN_ID']), 'attempt': 1}


def derive(source, output, request, zip_digest):
    """Input is the original artifact already verified by prepare_zip()."""
    need(not output.exists(), 'derived_output_exists')
    shutil.copytree(source, output)
    p = output / 'payload/site-path-v1.php'; before = p.read_text()
    old = '#^(/_preview/search3-site-candidate)(?:/|$)#'
    new = '#^(/_preview/search3-local-candidate)(?:/|$)#'
    need(before.count(old) == 1, 'route_source_drift')
    p.write_text(before.replace(old, new))
    for f in (output / 'payload').rglob('*'):
        if f.is_file():
            need(OLD_ROUTE.rstrip('/').encode() not in f.read_bytes(), 'residual_old_preview_route')
    previous = inventory(source / 'payload'); files = inventory(output / 'payload')
    need(previous.keys() == files.keys() and {n for n in files if files[n] != previous[n]} == {'site-path-v1.php'}, 'unexpected_payload_delta')
    m = json.loads((source / 'control/manifest.json').read_bytes())
    m.update(target='search3-local-preview', route=ROUTE, invariants=INVARIANTS,
        derived_from={'artifact_id': request['artifact_id'], 'build_run': request['build_run'],
                      'ZIP_sha256': zip_digest, 'transform': 'local-route-v1'})
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
    empty.rmdir()


def authorized():
    from search3_preview_publish import Github, verify_provenance
    env = os.environ; event = json.loads(Path(env['GITHUB_EVENT_PATH']).read_text())
    q = checked_request(event, env); api = Github(env['GH_TOKEN'])
    fresh = api.get('/issues/comments/' + str(event['comment']['id']))
    need(fresh.get('body') == event['comment']['body'] and fresh.get('user', {}).get('id') == 226193297, 'command_revoked')
    tree, zip_hash = verify_provenance(api, q, env['GITHUB_SHA'])
    return q, api, tree, zip_hash


def prepare():
    from search3_preview_publish import prepare_zip
    q, api, tree, zip_hash = authorized()
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-local-prepare'; work.mkdir(mode=0o700)
    original = work / 'original'
    prepare_zip(api.download(q['artifact_id']), q, tree, zip_hash, original)
    ready, archive = derive(original / 'release', work / 'derived', q, zip_hash)
    render_checks(work / 'derived')
    retained = work / 'retained'; retained.mkdir()
    (retained / 'local-preview.tar.gz').write_bytes(archive)
    (retained / 'request.json').write_bytes(json_bytes(ready))
    print(json.dumps({'status': 'prepared_not_published', 'route': ROUTE, 'archive_sha256': ready['archive_sha256'], 'changed_payload_files': ['site-path-v1.php']}))


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
    status, text = http(ROUTE + 'data/hotel-details-read-v1.php?hotelIds%5B%5D=6319&hotelIds%5B%5D=65108')
    data = json.loads(text)
    need(status == 200 and data.get('ok') is True and data.get('source') == 'anytour-local-hotel'
         and isinstance(data.get('items'), list) and isinstance(data.get('missingIds'), list), 'local_batch_read_failed')
    for name in ('search3-results-filters-v1.css', 'search3-results-filters-v1.js', 'site-header-v2.css'):
        status, body = http(ROUTE + name + '?sha=' + files[name], binary=True)
        need(status == 200 and digest(body) == files[name], 'served_asset_hash')
    need(http('/')[0] == 200 and http('/poisk-turov/')[0] == 200, 'production_pages_unhealthy')
    return {'routes_200': 9, 'noindex': True, 'counter': 0, 'lead_HTTP': 403,
            'local_batch_HTTP': 200, 'local_profiles_returned': len(data['items']), 'supplier_searches': 0, 'real_leads': 0}


def publish():
    from search3_preview_publish import prepare_zip, command, http
    q, api, tree, zip_hash = authorized()
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-local-publish'; work.mkdir(mode=0o700)
    original = work / 'original'
    prepare_zip(api.download(q['artifact_id']), q, tree, zip_hash, original)
    expected_q, archive = derive(original / 'release', work / 'expected', q, zip_hash)
    # Retained artifact belongs to this same trusted-main run; compare independently re-derived bytes.
    artifact_id = os.environ['READY_ARTIFACT_ID']
    need(re.fullmatch('[1-9][0-9]{0,14}', artifact_id), 'ready_artifact_id')
    meta = api.get('/actions/artifacts/' + artifact_id)
    need(not meta.get('expired') and meta.get('workflow_run', {}).get('id') == q['deploy_run']
         and meta['workflow_run'].get('head_sha') == os.environ['GITHUB_SHA']
         and meta.get('name') == 'search3-local-ready-' + str(q['deploy_run']), 'ready_artifact_identity')
    packed = api.download(int(artifact_id)); need('sha256:' + digest(packed) == meta.get('digest'), 'ready_ZIP_hash')
    with zipfile.ZipFile(io.BytesIO(packed)) as z:
        need(len(z.infolist()) == 2 and set(z.namelist()) == {'local-preview.tar.gz', 'request.json'}, 'ready_ZIP_inventory')
        need(sum(x.file_size for x in z.infolist()) <= 64 * 1024 * 1024, 'ready_ZIP_limit')
        need(z.read('local-preview.tar.gz') == archive and json.loads(z.read('request.json')) == expected_q, 'derived_artifact_drift')
    q = expected_q; (work / 'local-preview.tar.gz').write_bytes(archive)
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
        source = Path(__file__).with_name('search3_local_preview_remote.py').read_bytes()
        def remote(action, request):
            shell = 'python3 - ' + shlex.quote(action) + ' ' + shlex.quote(json.dumps(request, separators=(',', ':')))
            return json.loads(command(['ssh', *opts, user + '@' + host, shell], source, 180))
        binding = {'name': f"search3-local-bind-{q['deploy_run']}-{secrets.token_hex(12)}.txt", 'nonce': secrets.token_hex(32)}
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
        need(re.fullmatch(r'/tmp/search3-local\.[A-Za-z0-9_-]+\.tar\.gz', q['archive']), 'remote_upload_path')
        command(['scp', *opts, str(work / 'local-preview.tar.gz'), user + '@' + host + ':' + q['archive']])
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
