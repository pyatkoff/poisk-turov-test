"""Owner-authorized exact-artifact publisher for the shared isolated preview.

No rebuild, source execution, production deployment, scheduling or dynamic host
selection. All event data is parsed as data, never interpolated into shell code.
"""
from __future__ import annotations
import io
import json
import os
from pathlib import Path
import re
import secrets
import shlex
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
import zipfile

from search3_preview_remote import (ROUTE, digest, inventory, json_bytes, need,
    safe_extract, verify_payload, validate_request)

REPO = 'pyatkoff/poisk-turov-test'
OWNER_ID = 226193297
RELEASE = 'release/search3-production-ready-v1'
API = 'https://api.github.com/repos/' + REPO
ORIGIN = 'https://anytoour.ru'
BUILD = '.github/workflows/build-search3-whole-site-preview.yml'
PREFIX = '/publish-search3-preview '


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def checked_command(event, env):
    need(env.get('GITHUB_REPOSITORY') == REPO and env.get('GITHUB_REF') == 'refs/heads/main', 'trusted_main_only')
    need(env.get('GITHUB_ACTOR') == 'pyatkoff' and env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff'
         and env.get('GITHUB_ACTOR_ID') == str(OWNER_ID), 'owner_only')
    need(env.get('GITHUB_RUN_ATTEMPT') == '1', 'replay_requires_new_authorization')
    need(event.get('repository', {}).get('full_name') == REPO and event['repository'].get('id') == 1345518271, 'repository_identity')
    sender = event.get('sender', {})
    need(sender.get('id') == OWNER_ID and sender.get('login') == 'pyatkoff', 'sender_identity')
    if env.get('GITHUB_EVENT_NAME') == 'issue_comment':
        issue = event.get('issue', {}); comment = event.get('comment', {})
        need(event.get('action') == 'created' and issue.get('number') == 996 and not issue.get('pull_request'), 'coordinator_command_only')
        need(comment.get('user', {}).get('id') == OWNER_ID and comment.get('author_association') == 'OWNER', 'comment_owner')
        body = comment.get('body', '')
        need(isinstance(body, str) and body.startswith(PREFIX) and '\n' not in body and '\r' not in body, 'command_syntax')
        parts = body[len(PREFIX):].split(' ')
    elif env.get('GITHUB_EVENT_NAME') == 'workflow_dispatch':
        values = event.get('inputs', {})
        parts = [values.get(k, '') for k in ('source_sha', 'release_sha', 'build_run', 'artifact_id')]
    else:
        raise ValueError('manual_or_owner_command_only')
    need(len(parts) == 4 and all(isinstance(x, str) for x in parts), 'command_fields')
    need(all(re.fullmatch('[0-9a-f]{40}', x) for x in parts[:2]), 'exact_SHA_required')
    need(all(re.fullmatch('[1-9][0-9]{0,14}', x) for x in parts[2:]), 'numeric_artifact_required')
    return dict(source_sha=parts[0], release_sha=parts[1], build_run=int(parts[2]), artifact_id=int(parts[3]))


class Github:
    def __init__(self, token):
        need(bool(token), 'missing_GitHub_token')
        self.headers = {'Accept': 'application/vnd.github+json', 'Authorization': 'Bearer ' + token,
                        'X-GitHub-Api-Version': '2022-11-28', 'User-Agent': 'AnyTour-preview-publisher'}
        self.opener = urllib.request.build_opener(NoRedirect)

    def get(self, path):
        need(path.startswith('/') and not path.startswith('//'), 'API_path')
        req = urllib.request.Request(API + path, headers=self.headers)
        with self.opener.open(req, timeout=30) as response:
            return json.load(response)

    def download(self, artifact_id):
        req = urllib.request.Request(API + f'/actions/artifacts/{artifact_id}/zip', headers=self.headers)
        try:
            self.opener.open(req, timeout=30)
        except urllib.error.HTTPError as exc:
            need(exc.code == 302, 'artifact_download_redirect')
            url = exc.headers.get('Location', '')
        else:
            raise ValueError('artifact_download_redirect_missing')
        parsed = urllib.parse.urlsplit(url)
        need(parsed.scheme == 'https' and parsed.hostname and not parsed.username and not parsed.password
             and any(parsed.hostname.endswith(s) for s in ('.blob.core.windows.net', '.actions.githubusercontent.com', '.githubusercontent.com')), 'untrusted_artifact_host')
        # Deliberately no Authorization header on the signed storage request.
        with self.opener.open(urllib.request.Request(url), timeout=60) as response:
            content = response.read(64 * 1024 * 1024 + 1)
        need(len(content) <= 64 * 1024 * 1024, 'artifact_download_limit')
        return content


def verify_provenance(api, q, control_sha):
    need(api.get('/git/ref/heads/main')['object']['sha'] == control_sha, 'control_main_changed')
    need(api.get('/git/ref/heads/' + RELEASE)['object']['sha'] == q['release_sha'], 'release_pin_changed')
    source = api.get('/git/commits/' + q['source_sha']); release = api.get('/git/commits/' + q['release_sha'])
    source_tree = source['tree']['sha']; release_tree = release['tree']['sha']
    if source_tree != release_tree:
        def projection(tree):
            body = api.get('/git/trees/' + tree + '?recursive=1')
            need(not body.get('truncated'), 'truncated_source_tree')
            return {x['path']: (x['sha'], x['mode'], x['type']) for x in body['tree']
                    if x['type'] != 'tree' and x['path'] != 'AUTOPILOT_STATE.json' and not x['path'].startswith('docs/')}
        need(projection(source_tree) == projection(release_tree), 'release_runtime_differs')
    run = api.get('/actions/runs/' + str(q['build_run']))
    need(run.get('head_sha') == q['source_sha'] and run.get('path') == BUILD and run.get('event') == 'pull_request'
         and run.get('status') == 'completed' and run.get('conclusion') == 'success' and run.get('run_attempt') == 1
         and run.get('repository', {}).get('full_name') == REPO
         and run.get('head_repository', {}).get('full_name') == REPO, 'build_not_successful_exact_source')
    checks = api.get('/commits/' + q['source_sha'] + '/check-runs?per_page=100')
    need(checks.get('total_count', 0) <= 100, 'check_pagination_required')
    latest = {}
    for c in checks['check_runs']:
        latest.setdefault(c['name'], c)
    need({'guard', 'build-preview-artifact'} <= latest.keys(), 'required_checks_missing')
    for name, c in latest.items():
        need(c['head_sha'] == q['source_sha'] and c['status'] == 'completed'
             and c['conclusion'] in ('success', 'skipped', 'neutral'), 'source_check_not_green')
        if name in ('guard', 'build-preview-artifact'):
            need(c['conclusion'] == 'success' and c.get('app', {}).get('slug') == 'github-actions', 'required_check_not_success')
    artifact = api.get('/actions/artifacts/' + str(q['artifact_id']))
    need(not artifact.get('expired') and artifact.get('workflow_run', {}).get('id') == q['build_run']
         and artifact['workflow_run'].get('head_sha') == q['source_sha']
         and artifact.get('name') == f"search3-site-preview-{q['source_sha']}-{q['build_run']}-1"
         and re.fullmatch('sha256:[0-9a-f]{64}', artifact.get('digest', '')), 'artifact_identity')
    return source_tree, artifact['digest'][7:]


def prepare_zip(content, q, source_tree, expected_digest, directory):
    need(digest(content) == expected_digest, 'artifact_ZIP_digest')
    names = {'search3-whole-site-preview.tar.gz', 'search3-whole-site-preview.tar.gz.sha256',
             'search3-site-release.txt', 'search3-site-release/control/manifest.json', 'search3-site-release/control/payload.sha256'}
    with zipfile.ZipFile(io.BytesIO(content)) as z:
        entries = z.infolist()
        need(len(entries) == 5 and {x.filename for x in entries} == names, 'artifact_ZIP_inventory')
        need(sum(x.file_size for x in entries) <= 64 * 1024 * 1024, 'artifact_ZIP_total_limit')
        need(all(x.file_size <= 64 * 1024 * 1024 and (x.external_attr >> 16) & 0o170000 in (0, 0o100000) for x in entries), 'artifact_ZIP_type_limit')
        values = {}
        for line in z.read('search3-site-release.txt').decode().splitlines():
            key, value = line.split('=', 1)
            need(key not in values, 'duplicate_release_receipt'); values[key] = value
        need(set(values) == {'source_sha','source_tree_sha','file_count','manifest_sha256','payload_checksums_sha256','archive_sha256'}, 'release_receipt_fields')
        need(values['source_sha'] == q['source_sha'] and values['source_tree_sha'] == source_tree, 'release_receipt_identity')
        q.update(source_tree=source_tree, manifest_sha256=values['manifest_sha256'], payload_sha256=values['payload_checksums_sha256'],
                 archive_sha256=values['archive_sha256'], file_count=int(values['file_count']))
        validate_request(q)
        archive = z.read('search3-whole-site-preview.tar.gz')
        need(digest(archive) == q['archive_sha256'], 'archive_digest')
        need(z.read('search3-whole-site-preview.tar.gz.sha256').decode().split()[0] == q['archive_sha256'], 'archive_receipt_digest')
        need(digest(z.read('search3-site-release/control/manifest.json')) == q['manifest_sha256'], 'ZIP_manifest_digest')
        need(digest(z.read('search3-site-release/control/payload.sha256')) == q['payload_sha256'], 'ZIP_checksums_digest')
        directory.mkdir(mode=0o700)
        (directory / 'payload.tar.gz').write_bytes(archive)
    safe_extract(directory / 'payload.tar.gz', directory / 'release')
    return verify_payload(directory / 'release', q)


def command(args, data=None, timeout=120):
    result = subprocess.run(args, input=data, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=timeout)
    if result.returncode:
        # Do not print command arguments or SSH credentials. Last error is bounded.
        raise RuntimeError(f'command_failed:{args[0]}:{result.returncode}:' + result.stderr.decode(errors='replace')[-1200:])
    return result.stdout


def http(path, data=None, binary=False):
    need(path.startswith('/') and not path.startswith('//'), 'HTTPS_path')
    request = urllib.request.Request(ORIGIN + path, data=data, headers={'Content-Type':'application/json'} if data else {})
    try:
        response = urllib.request.build_opener(NoRedirect).open(request, timeout=40)
    except urllib.error.HTTPError as exc:
        response = exc
    with response:
        content = response.read(8 * 1024 * 1024)
        return response.code, content if binary else content.decode(errors='replace')


def live_checks(files):
    for route in ('', 'poisk-turov/', 'poisk-turov-old/', 'country/', 'country/turkey/', 'hot/', 'contacts/', 'rb/', 'how-to-buy/'):
        status, html = http(ROUTE + route)
        need(status == 200 and '<meta name="robots" content="noindex,follow,max-image-preview:large' in html, 'live_route_noindex:' + route)
        if route in ('poisk-turov/', 'poisk-turov-old/'):
            for marker in ('id="tourSearch"', 'metrikaCounter:0', 'leadApi:"' + ROUTE + 'preview-lead-disabled.php"'):
                need(marker in html, 'live_search_guard')
            need('web-consultant/widget.js' not in html, 'live_consultant')
            need(('<body class="search3-candidate">' in html) == (route == 'poisk-turov/'), 'live_search_identity')
    status, body = http(ROUTE + 'preview-lead-disabled.php', b'{"synthetic":true}')
    need(status == 403 and 'PREVIEW_LEAD_DISABLED' in body, 'preview_lead_not_disabled')
    for path in ('assets.php', 'search-page-v2.php', 'seo-config.php', 'data/db-v1.php', 'offer-freshness-v1.php'):
        need(http(ROUTE + path)[0] == 403, 'internal_PHP_not_denied')
    need(http(ROUTE + 'config.php')[0] in (403,404), 'config_not_denied')
    need(http('/')[0] == 200 and http('/poisk-turov/')[0] == 200, 'production_pages_unhealthy')
    need('tourvisor-direct' in http('/api-v2.php?action=health')[1], 'production_API_health')
    need('v2-hmac-bridge-bitrix-lead' in http('/lead-adapter-v2.php')[1], 'production_lead_health')
    for name in ('search3-results-filters-v1.css', 'search3-results-filters-v1.js', 'site-header-v2.css', 'andromeda-provider-v1.js'):
        if name in files:
            status, body = http(ROUTE + name + '?sha=' + files[name], binary=True)
            need(status == 200 and digest(body) == files[name], 'served_asset_hash:' + name)
    return {'served_asset_hashes_checked': True, 'routes_200': 9, 'noindex': True, 'counter': 0, 'disabled_lead_HTTP': 403, 'real_leads': 0, 'supplier_searches': 0}


def main():
    env = os.environ; event = json.loads(Path(env['GITHUB_EVENT_PATH']).read_text())
    q = checked_command(event, env)
    q.update(deploy_run=int(env['GITHUB_RUN_ID']), attempt=1)
    api = Github(env['GH_TOKEN'])
    if env['GITHUB_EVENT_NAME'] == 'issue_comment':
        fresh = api.get('/issues/comments/' + str(event['comment']['id']))
        need(fresh['body'] == event['comment']['body'] and fresh['user']['id'] == OWNER_ID, 'command_changed_or_revoked')
    tree, zip_digest = verify_provenance(api, q, env['GITHUB_SHA'])
    work = Path(env['RUNNER_TEMP']) / 'search3-preview-publish'
    files = prepare_zip(api.download(q['artifact_id']), q, tree, zip_digest, work)
    evidence = {'source_sha': q['source_sha'], 'release_sha': q['release_sha'], 'artifact_id': q['artifact_id'],
                'build_run': q['build_run'], 'ZIP_sha256': zip_digest, 'route': ROUTE, 'status': 'checked_not_published'}
    host = env['PREVIEW_HOST']; user = env['PREVIEW_USER']
    need(re.fullmatch('[A-Za-z0-9][A-Za-z0-9.-]*', host) and re.fullmatch('[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}', user), 'SSH_identity')
    key = work / 'key'; known = work / 'known_hosts'
    raw = env['PREVIEW_KEY'].replace('\r', '')
    if 'PRIVATE KEY' not in raw:
        import base64
        raw = base64.b64decode(raw, validate=True).decode()
    key.write_text(raw.rstrip() + '\n'); key.chmod(0o600)
    command(['ssh-keygen','-y','-f',str(key)])
    scan = command(['ssh-keyscan','-T','15','-t','ed25519',host]); known.write_bytes(scan); known.chmod(0o600)
    fingerprints = command(['ssh-keygen','-lf',str(known),'-E','sha256']).decode().splitlines()
    need(len({x.split()[1] for x in fingerprints}) == 1, 'SSH_fingerprint_count')
    options = ['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),
               '-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','ConnectTimeout=15',
               '-o','ServerAliveInterval=15','-o','ServerAliveCountMax=3']
    remote_source = Path(__file__).with_name('search3_preview_remote.py').read_bytes()
    def remote(action, request):
        shell_command = 'python3 - ' + shlex.quote(action) + ' ' + shlex.quote(json.dumps(request, separators=(',',':')))
        return json.loads(command(['ssh',*options,user+'@'+host,shell_command], remote_source, 180))
    binding = {'name': f"site-binding-{q['deploy_run']}-1-{secrets.token_hex(12)}.txt", 'nonce': secrets.token_hex(32)}
    attempted = False; before = None
    try:
        remote('bind',binding)
        try:
            status, body = http(ROUTE + binding['name'] + '?bind=1')
            need(status == 200 and body == binding['nonce'], 'SSH_HTTPS_binding_failed')
        finally:
            remote('unbind',binding)
        need(http(ROUTE + binding['name'] + '?removed=1')[0] in (404,410), 'binding_not_removed')
        before = remote('snapshot', {}); q['before'] = before
        owner = before['owner'] or {}
        same = owner.get('source_sha') == q['source_sha'] and owner.get('artifact_id') == q['artifact_id']
        if same:
            need(owner.get('status') == 'published' and before['target_digest'] == digest(json_bytes(files)), 'previous_outcome_unknown')
            evidence.update(live_checks(files), status='already_published', production_unchanged=True)
        else:
            # Refresh the release pin immediately before the first activation write.
            need(api.get('/git/ref/heads/' + RELEASE)['object']['sha'] == q['release_sha'], 'release_pin_changed_before_activation')
            q['archive'] = remote('upload', {})['archive']
            need(re.fullmatch(r'/tmp/search3-site\.[A-Za-z0-9_\-]+\.tar\.gz', q['archive']), 'remote_upload_path')
            command(['scp',*options,str(work/'payload.tar.gz'),user+'@'+host+':'+q['archive']])
            attempted = True
            evidence['activation'] = remote('activate',q)
            evidence.update(live_checks(files))
            evidence['completion'] = remote('complete',q)
            evidence.update(status='published', production_unchanged=True)
    except Exception:
        evidence['status'] = 'failed_not_accepted'
        if attempted:
            try:
                evidence['rollback'] = remote('rollback',q)
            except Exception as rollback_error:
                evidence['rollback'] = {'status':'unknown_stop_no_replay','reason':str(rollback_error)}
        raise
    finally:
        try:
            if before is not None:
                final = remote('snapshot',{})
                evidence['production_unchanged'] = final['protected'] == before['protected']
                need(evidence['production_unchanged'], 'final_production_or_INT_drift')
        finally:
            key.unlink(missing_ok=True); known.unlink(missing_ok=True)
            (work/'evidence.json').write_bytes(json_bytes(evidence))
    print(json.dumps(evidence, indent=2))


if __name__ == '__main__':
    main()
