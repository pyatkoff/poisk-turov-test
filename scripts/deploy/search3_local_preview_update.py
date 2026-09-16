"""Owner-authenticated update of the existing isolated local-catalog Search3 preview.

This is deliberately separate from the create-only publisher. It reuses the checked
artifact derivation but requires an exact published predecessor and retains its bytes
for rollback. The route is fixed; no production path can be supplied by the command.
"""
from __future__ import annotations
import json
import os
from pathlib import Path
import re
import secrets
import shlex
import subprocess
import sys
import tempfile
import zipfile

from search3_local_preview import derive, render_checks, live_checks, REPO, RELEASE
from search3_local_preview_update_remote import inventory, json_bytes, need

PREFIX = '/update-search3-local-preview '


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
    need(len(parts) == 5 and all(re.fullmatch('[0-9a-f]{40}', x) for x in (parts[0], parts[1], parts[4]))
         and all(re.fullmatch('[1-9][0-9]{0,14}', x) for x in parts[2:4]), 'exact_pins')
    need(parts[0] != parts[4], 'same_source_no_update')
    return {'source_sha': parts[0], 'release_sha': parts[1], 'build_run': int(parts[2]),
            'artifact_id': int(parts[3]), 'previous_source_sha': parts[4],
            'deploy_run': int(env['GITHUB_RUN_ID']), 'attempt': 1, 'operation': 'update'}


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
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-local-update-prepare'; work.mkdir(mode=0o700)
    original = work / 'original'
    prepare_zip(api.download(q['artifact_id']), q, tree, zip_hash, original)
    ready, archive = derive(original / 'release', work / 'derived', q, zip_hash)
    ready['previous_source_sha'] = q['previous_source_sha']; ready['operation'] = 'update'
    render_checks(work / 'derived')
    retained = work / 'retained'; retained.mkdir()
    (retained / 'local-preview.tar.gz').write_bytes(archive)
    (retained / 'request.json').write_bytes(json_bytes(ready))
    print(json.dumps({'status': 'prepared_update_not_published', 'previous_source_sha': q['previous_source_sha'],
                      'source_sha': q['source_sha'], 'archive_sha256': ready['archive_sha256']}))



def canonical_live_checks():
    from search3_preview_publish import http
    route = '/_preview/search3-local-candidate/'
    new_status, new_html = http(route + 'poisk-turov/')
    old_status, old_html = http(route + 'poisk-turov-old/')
    need(new_status == 200 and old_status == 200, 'live_search_routes')
    need('search3-canonical-profiles-v1.js' in new_html, 'canonical_module_missing_new_route')
    need('search3-canonical-profiles-v1.js' not in old_html, 'canonical_module_leaked_old_route')
    status, text = http(route + 'data/hotel-details-read-v1.php?catalog=anytour&legacyHotelIds%5B%5D=102')
    data = json.loads(text)
    need(status == 200 and data.get('ok') is True and data.get('source') == 'anytour-canonical-catalog'
         and data.get('catalog') == 'anytour', 'canonical_live_read_failed')
    links = data.get('links') if isinstance(data.get('links'), list) else []
    items = data.get('items') if isinstance(data.get('items'), list) else []
    need(any(x.get('legacyHotelId') == 102 and x.get('anytourHotelId') == 1 for x in links if isinstance(x, dict)), 'canonical_bridge_102_1_missing')
    profile = next((x for x in items if isinstance(x, dict) and x.get('id') == 1), None)
    need(profile and isinstance(profile.get('name'), str) and profile['name'].strip()
         and isinstance(profile.get('description'), str) and profile['description'].strip()
         and isinstance(profile.get('images'), list) and len(profile['images']) > 0, 'canonical_profile_1_incomplete')
    return {'canonical_HTTP': 200, 'canonical_legacy_102_anytour': 1,
            'canonical_profile_name': profile['name'], 'canonical_images': len(profile['images']),
            'legacy_route_catalog_module': False, 'new_route_catalog_module': True}

def publish():
    from search3_preview_publish import prepare_zip, command, http
    q, api, tree, zip_hash = authorized()
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-local-update-publish'; work.mkdir(mode=0o700)
    original = work / 'original'
    prepare_zip(api.download(q['artifact_id']), q, tree, zip_hash, original)
    expected_q, archive = derive(original / 'release', work / 'expected', q, zip_hash)
    expected_q['previous_source_sha'] = q['previous_source_sha']; expected_q['operation'] = 'update'

    artifact_id = os.environ['READY_ARTIFACT_ID']
    need(re.fullmatch('[1-9][0-9]{0,14}', artifact_id), 'ready_artifact_id')
    meta = api.get('/actions/artifacts/' + artifact_id)
    need(not meta.get('expired') and meta.get('workflow_run', {}).get('id') == q['deploy_run']
         and meta['workflow_run'].get('head_sha') == os.environ['GITHUB_SHA']
         and meta.get('name') == 'search3-local-update-ready-' + str(q['deploy_run']), 'ready_artifact_identity')
    packed = api.download(int(artifact_id))
    need('sha256:' + __import__('hashlib').sha256(packed).hexdigest() == meta.get('digest'), 'ready_ZIP_hash')
    with zipfile.ZipFile(__import__('io').BytesIO(packed)) as z:
        need(len(z.infolist()) == 2 and set(z.namelist()) == {'local-preview.tar.gz', 'request.json'}, 'ready_ZIP_inventory')
        need(sum(x.file_size for x in z.infolist()) <= 64 * 1024 * 1024, 'ready_ZIP_limit')
        need(z.read('local-preview.tar.gz') == archive and json.loads(z.read('request.json')) == expected_q, 'derived_artifact_drift')

    q = expected_q; (work / 'local-preview.tar.gz').write_bytes(archive)
    files = inventory(work / 'expected/payload')
    evidence = {'route': '/_preview/search3-local-candidate/', 'source_sha': q['source_sha'],
                'previous_source_sha': q['previous_source_sha'], 'release_sha': q['release_sha'],
                'source_artifact': q['artifact_id'], 'derived_artifact': int(artifact_id),
                'status': 'checked_update_not_published'}
    host = os.environ['PREVIEW_HOST']; user = os.environ['PREVIEW_USER']
    need(re.fullmatch('[A-Za-z0-9][A-Za-z0-9.-]*', host) and re.fullmatch('[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}', user), 'SSH_identity')
    key = work / 'key'; known = work / 'known_hosts'
    raw = os.environ['PREVIEW_KEY'].replace('\r', '')
    if 'PRIVATE KEY' not in raw:
        import base64
        raw = base64.b64decode(raw, validate=True).decode()
    key.write_text(raw.rstrip() + '\n'); key.chmod(0o600)
    attempted = False
    try:
        command(['ssh-keygen', '-y', '-f', str(key)])
        known.write_bytes(command(['ssh-keyscan', '-T', '15', '-t', 'ed25519', host])); known.chmod(0o600)
        fingerprints = command(['ssh-keygen', '-lf', str(known), '-E', 'sha256']).decode().splitlines()
        need(len({x.split()[1] for x in fingerprints}) == 1, 'SSH_fingerprint_count')
        opts = ['-i', str(key), '-o', 'StrictHostKeyChecking=yes', '-o', 'UserKnownHostsFile=' + str(known),
                '-o', 'GlobalKnownHostsFile=/dev/null', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=15']
        source = Path(__file__).with_name('search3_local_preview_update_remote.py').read_bytes()
        def remote(action, request):
            shell = 'python3 - ' + shlex.quote(action) + ' ' + shlex.quote(json.dumps(request, separators=(',', ':')))
            return json.loads(command(['ssh', *opts, user + '@' + host, shell], source, 180))
        binding = {'name': f"search3-local-update-bind-{q['deploy_run']}-{secrets.token_hex(12)}.txt", 'nonce': secrets.token_hex(32)}
        remote('bind', binding)
        try:
            status, text = http('/_preview/' + binding['name'])
            need(status == 200 and text == binding['nonce'], 'SSH_HTTPS_binding')
        finally:
            remote('unbind', binding)
        before = remote('snapshot', {}); q['before'] = before
        owner = before.get('owner') if isinstance(before, dict) else None
        need(isinstance(owner, dict) and owner.get('status') == 'published'
             and owner.get('source') == q['previous_source_sha'] and owner.get('digest') == before.get('target'),
             'published_predecessor_mismatch')
        need(api.get('/git/ref/heads/' + RELEASE)['object']['sha'] == q['release_sha'], 'release_changed_before_activation')
        need(api.get('/git/ref/heads/main')['object']['sha'] == os.environ['GITHUB_SHA'], 'main_changed_before_activation')
        q['archive'] = remote('upload', {})['archive']
        need(re.fullmatch(r'/tmp/search3-local-update\.[A-Za-z0-9_-]+\.tar\.gz', q['archive']), 'remote_upload_path')
        command(['scp', *opts, str(work / 'local-preview.tar.gz'), user + '@' + host + ':' + q['archive']])
        attempted = True; evidence['activation'] = remote('activate-update', q)
        evidence.update(live_checks(files)); evidence.update(canonical_live_checks())
        evidence['completion'] = remote('complete-update', q)
        evidence.update(status='published_update', production_unchanged=True, existing_preview_unchanged=True,
                        predecessor_retained=True)
    except Exception as exc:
        evidence.update(status='failed_update_not_accepted', reason=str(exc))
        if attempted:
            try:
                evidence['rollback'] = remote('rollback-update', q)
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
