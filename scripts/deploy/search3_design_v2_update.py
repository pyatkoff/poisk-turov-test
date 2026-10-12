"""Retain then independently rederive a bounded update of NEW design preview.

No source runs with SSH credentials. All non-visual donor bytes must still match
the original accepted artifact, including supplier, price, lead, logo and fonts.
"""
from __future__ import annotations
import copy
import io
import json
import os
from pathlib import Path
import re
import secrets
import shlex
import shutil
import sys
import tarfile
import zipfile
import gzip
import base64
from search3_design_v2_preview import (REPO, RELEASE, OLD_ROUTE, ROUTE_TRANSFORMS,
    LEDGER_PATH, LEDGER_JS, gzip_lengths, render_checks, live_checks)
from search3_design_v2_update_remote import (ROUTE, INVARIANTS, ORIGINAL_SOURCE, PREDECESSOR_SOURCE, COMPLETED_SOURCES,
    ORIGINAL_BUILD, ORIGINAL_ARTIFACT, ORIGINAL_ZIP, SOURCE_CHANGES, ROUTE_CHANGES,
    digest, inventory, json_bytes, need, verify, predecessor)
from search3_design_v2_preview_remote import SOURCE_TREE as ORIGINAL_TREE

PREFIX = '/update-search3-design-v2-preview '
REQUIRED_CHECKS = {'guard', 'build-preview-artifact', 'focused-presentation', 'visual-migration', 'lead-source'}


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
    need(parts[0] not in COMPLETED_SOURCES, 'new_source_required')
    return {'source_sha': parts[0], 'release_sha': parts[1], 'build_run': int(parts[2]),
        'artifact_id': int(parts[3]), 'deploy_run': int(env['GITHUB_RUN_ID']), 'attempt': 1}


def strict_checks(api, source):
    body = api.get('/commits/' + source + '/check-runs?per_page=100')
    need(body.get('total_count', 0) <= 100, 'check_pagination_required')
    latest = {}
    for c in body['check_runs']:
        if c['name'] not in latest or (c.get('started_at') or '') > (latest[c['name']].get('started_at') or ''):
            latest[c['name']] = c
    need(REQUIRED_CHECKS <= latest.keys(), 'required_visual_checks_missing')
    for name in REQUIRED_CHECKS:
        c = latest[name]
        need(c.get('head_sha') == source and c.get('status') == 'completed'
             and c.get('conclusion') == 'success' and c.get('app', {}).get('slug') == 'github-actions', 'required_visual_check_not_success:' + name)


def authorized():
    from search3_preview_publish import Github, verify_provenance
    env = os.environ; event = json.loads(Path(env['GITHUB_EVENT_PATH']).read_text())
    q = checked_request(event, env); api = Github(env['GH_TOKEN'])
    fresh = api.get('/issues/comments/' + str(event['comment']['id']))
    need(fresh.get('body') == event['comment']['body'] and fresh.get('user', {}).get('id') == 226193297, 'command_revoked')
    tree, zip_hash = verify_provenance(api, q, env['GITHUB_SHA'], release_branch=RELEASE)
    strict_checks(api, q['source_sha'])
    return q, api, tree, zip_hash


def update_ledger(source, output):
    ledger = json.loads((source / 'payload' / LEDGER_PATH).read_bytes()); original = copy.deepcopy(ledger)
    entries = {f['target']: f for f in ledger['files']}
    need(len(entries) == len(ledger['files']) and all(n in entries for n in LEDGER_JS), 'served_ledger_inventory')
    before = [(source / 'payload' / n).read_bytes() for n in LEDGER_JS]
    after = [(output / 'payload' / n).read_bytes() for n in LEDGER_JS]
    lengths = gzip_lengths(before + after)
    for i, name in enumerate(LEDGER_JS):
        f = entries[name]
        need(f['sha256'] == digest(before[i]) and f['served'] == len(before[i])
             and f['gzip_after'] == lengths[i], 'source_ledger_accounting:' + name)
        f.update(sha256=digest(after[i]), served=len(after[i]), gzip_after=lengths[i + len(LEDGER_JS)])
    graphs = {**ledger['graphs'], 'complete_live': ledger['graphs']['live'] + ledger['graphs']['ondemand'],
        'complete_offline': ledger['graphs']['offline'] + ledger['graphs']['ondemand']}
    for name, graph in graphs.items():
        for field in ('served', 'gzip_after'):
            ledger['totals'][name][field] = sum(f[field] for f in ledger['files'] if f['src'] in graph)
    check = copy.deepcopy(ledger)
    for i, f in enumerate(check['files']):
        if f['target'] in LEDGER_JS:
            for field in ('sha256', 'served', 'gzip_after'): f[field] = original['files'][i][field]
    for name in ('live', 'complete_live'):
        for field in ('served', 'gzip_after'): check['totals'][name][field] = original['totals'][name][field]
    need(check == original, 'unexpected_ledger_delta')
    (output / 'payload' / LEDGER_PATH).write_bytes((json.dumps(ledger, ensure_ascii=False, indent=2) + '\n').encode())


def derive(source, baseline, output, request, zip_digest):
    need(not output.exists(), 'derived_output_exists')
    original = inventory(baseline / 'payload'); current = inventory(source / 'payload')
    need(len(original) == len(current) == 845 and original.keys() == current.keys(), 'source_inventory_drift')
    changes = sorted(n for n in current if current[n] != original[n])
    need(bool(changes) and set(changes) <= set(SOURCE_CHANGES), 'unreviewed_source_delta')
    shutil.copytree(source, output)
    for name, old, new in ROUTE_TRANSFORMS:
        p = output / 'payload' / name; before = p.read_bytes()
        need(before.count(old) == 1 and new not in before, 'route_source_drift:' + name)
        p.write_bytes(before.replace(old, new))
    update_ledger(source, output)
    for p in (output / 'payload').rglob('*'):
        if p.is_file(): need(OLD_ROUTE.rstrip('/').encode() not in p.read_bytes(), 'residual_old_preview_route')
    files = inventory(output / 'payload')
    need(current.keys() == files.keys() and {n for n in files if files[n] != current[n]} == set(ROUTE_CHANGES), 'unexpected_payload_delta')
    m = json.loads((source / 'control/manifest.json').read_bytes())
    m.update(target='search3-design-v2-preview', route=ROUTE, invariants=INVARIANTS,
        derived_from={'artifact_id': request['artifact_id'], 'build_run': request['build_run'],
            'ZIP_sha256': zip_digest, 'transform': 'design-v2-route-and-ledger-v1',
            'changed_payload_files': list(ROUTE_CHANGES), 'changed_source_payload_files': changes,
            'baseline': {'source_sha': ORIGINAL_SOURCE, 'build_run': ORIGINAL_BUILD,
                'artifact_id': ORIGINAL_ARTIFACT, 'ZIP_sha256': ORIGINAL_ZIP}})
    m['files'] = [{'path': n, 'sha256': h, 'size': (output / 'payload' / n).stat().st_size} for n, h in files.items()]
    m['file_count'] = len(files)
    (output / 'control/manifest.json').write_bytes(json_bytes(m))
    (output / 'control/payload.sha256').write_bytes(''.join(f'{files[n]}  ./{n}\n' for n in sorted(files)).encode())
    raw = io.BytesIO()
    with tarfile.open(fileobj=raw, mode='w', format=tarfile.USTAR_FORMAT) as t:
        for name in ('control/manifest.json', 'control/payload.sha256', *('payload/' + n for n in files)):
            data = (output / name).read_bytes(); info = tarfile.TarInfo(name)
            info.size = len(data); info.mode = 0o644; info.mtime = 0
            t.addfile(info, io.BytesIO(data))
    archive = gzip.compress(raw.getvalue(), mtime=0)
    q = {**request, 'source_ZIP_sha256': zip_digest, 'archive_sha256': digest(archive),
        'manifest_sha256': digest((output / 'control/manifest.json').read_bytes()),
        'payload_sha256': digest((output / 'control/payload.sha256').read_bytes()),
        'target_digest': digest(json_bytes(files)), 'file_count': len(files)}
    verify(output, q)
    return q, archive


def prepare_inputs(api, q, tree, zip_hash, work):
    from search3_preview_publish import prepare_zip
    prepare_zip(api.download(q['artifact_id']), q, tree, zip_hash, work / 'original')
    baseline = {'source_sha': ORIGINAL_SOURCE, 'release_sha': ORIGINAL_SOURCE,
        'artifact_id': ORIGINAL_ARTIFACT, 'build_run': ORIGINAL_BUILD,
        'deploy_run': q['deploy_run'], 'attempt': 1}
    prepare_zip(api.download(ORIGINAL_ARTIFACT), baseline, ORIGINAL_TREE, ORIGINAL_ZIP, work / 'baseline')
    return derive(work / 'original/release', work / 'baseline/release', work / 'derived', q, zip_hash)


def prepare():
    q, api, tree, zip_hash = authorized()
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-design-v2-update-prepare'; work.mkdir(mode=0o700)
    ready, archive = prepare_inputs(api, q, tree, zip_hash, work)
    render_checks(work / 'derived')
    retained = work / 'retained'; retained.mkdir()
    (retained / 'design-v2-update.tar.gz').write_bytes(archive)
    (retained / 'request.json').write_bytes(json_bytes(ready))
    print(json.dumps({'status': 'prepared_not_published', 'route': ROUTE, 'archive_sha256': ready['archive_sha256']}))


def remote_source():
    # Trusted-main modules only; payload Python is never imported or executed.
    root = Path(__file__).parent
    base = (root / 'search3_design_v2_preview_remote.py').read_text()
    updater = (root / 'search3_design_v2_update_remote.py').read_text()
    return ('import sys, types\n'
        'm=types.ModuleType("search3_design_v2_preview_remote")\n'
        'sys.modules[m.__name__]=m\n'
        'exec(compile(' + repr(base) + ',"trusted-creator-remote","exec"),m.__dict__)\n'
        'exec(compile(' + repr(updater) + ',"trusted-update-remote","exec"))\n').encode()


def publish():
    from search3_preview_publish import command, http
    q, api, tree, zip_hash = authorized()
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-design-v2-update-publish'; work.mkdir(mode=0o700)
    expected_q, archive = prepare_inputs(api, q, tree, zip_hash, work)
    artifact_id = os.environ['READY_ARTIFACT_ID']
    need(re.fullmatch('[1-9][0-9]{0,14}', artifact_id), 'ready_artifact_id')
    meta = api.get('/actions/artifacts/' + artifact_id)
    need(not meta.get('expired') and meta.get('workflow_run', {}).get('id') == q['deploy_run']
         and meta['workflow_run'].get('head_sha') == os.environ['GITHUB_SHA']
         and meta.get('name') == 'search3-design-v2-update-ready-' + str(q['deploy_run']), 'ready_artifact_identity')
    packed = api.download(int(artifact_id)); need('sha256:' + digest(packed) == meta.get('digest'), 'ready_ZIP_hash')
    with zipfile.ZipFile(io.BytesIO(packed)) as z:
        need(len(z.infolist()) == 2 and set(z.namelist()) == {'design-v2-update.tar.gz', 'request.json'}, 'ready_ZIP_inventory')
        need(sum(x.file_size for x in z.infolist()) <= 64 * 1024 * 1024, 'ready_ZIP_limit')
        need(z.read('design-v2-update.tar.gz') == archive and json.loads(z.read('request.json')) == expected_q, 'derived_artifact_drift')
    q = expected_q; (work / 'design-v2-update.tar.gz').write_bytes(archive)
    files = inventory(work / 'derived/payload')
    evidence = {'route': ROUTE, 'source_sha': q['source_sha'], 'release_sha': q['release_sha'],
        'source_artifact': q['artifact_id'], 'derived_artifact': int(artifact_id), 'derived_ZIP_sha256': digest(packed),
        'predecessor_source': PREDECESSOR_SOURCE, 'status': 'checked_not_published'}
    host = os.environ['PREVIEW_HOST']; user = os.environ['PREVIEW_USER']
    need(re.fullmatch('[A-Za-z0-9][A-Za-z0-9.-]*', host) and re.fullmatch('[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}', user), 'SSH_identity')
    key = work / 'key'; known = work / 'known_hosts'; raw = os.environ['PREVIEW_KEY'].replace('\r', '')
    if 'PRIVATE KEY' not in raw: raw = base64.b64decode(raw, validate=True).decode()
    key.write_text(raw.rstrip() + '\n'); key.chmod(0o600)
    attempted = False
    try:
        command(['ssh-keygen', '-y', '-f', str(key)])
        known.write_bytes(command(['ssh-keyscan', '-T', '15', '-t', 'ed25519', host])); known.chmod(0o600)
        fingerprints = command(['ssh-keygen', '-lf', str(known), '-E', 'sha256']).decode().splitlines()
        need(len({x.split()[1] for x in fingerprints}) == 1, 'SSH_fingerprint_count')
        opts = ['-i', str(key), '-o', 'StrictHostKeyChecking=yes', '-o', 'UserKnownHostsFile=' + str(known),
            '-o', 'GlobalKnownHostsFile=/dev/null', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=15']
        source = remote_source()
        def remote(action, request):
            shell = 'python3 - ' + shlex.quote(action) + ' ' + shlex.quote(json.dumps(request, separators=(',', ':')))
            return json.loads(command(['ssh', *opts, user + '@' + host, shell], source, 180))
        binding = {'name': f"search3-design-v2-bind-{q['deploy_run']}-{secrets.token_hex(12)}.txt", 'nonce': secrets.token_hex(32)}
        remote('bind', binding)
        try:
            status, text = http('/_preview/' + binding['name'])
            need(status == 200 and text == binding['nonce'], 'SSH_HTTPS_binding')
        finally: remote('unbind', binding)
        q['before'] = remote('snapshot', {}); predecessor(q['before'])
        need(api.get('/git/ref/heads/' + RELEASE)['object']['sha'] == q['release_sha'], 'release_changed_before_activation')
        need(api.get('/git/ref/heads/main')['object']['sha'] == os.environ['GITHUB_SHA'], 'main_changed_before_activation')
        q['archive'] = remote('upload', {})['archive']
        need(re.fullmatch(r'/tmp/search3-design-v2-update\.[A-Za-z0-9_-]+\.tar\.gz', q['archive']), 'remote_upload_path')
        command(['scp', *opts, str(work / 'design-v2-update.tar.gz'), user + '@' + host + ':' + q['archive']])
        attempted = True; evidence['activation'] = remote('activate', q)
        evidence.update(live_checks(files))
        evidence['completion'] = remote('complete', q)
        evidence.update(status='published', production_unchanged=True, existing_preview_unchanged=True)
    except Exception as exc:
        evidence.update(status='failed_not_accepted', reason=str(exc))
        if attempted:
            try: evidence['rollback'] = remote('rollback', q)
            except Exception as error: evidence['rollback'] = {'status': 'unknown_stop_no_replay', 'reason': str(error)}
        raise
    finally:
        key.unlink(missing_ok=True); known.unlink(missing_ok=True)
        (work / 'evidence.json').write_bytes(json_bytes(evidence))
    print(json.dumps(evidence, indent=2))


if __name__ == '__main__':
    need(len(sys.argv) == 2 and sys.argv[1] in ('prepare', 'publish'), 'usage')
    {'prepare': prepare, 'publish': publish}[sys.argv[1]]()
