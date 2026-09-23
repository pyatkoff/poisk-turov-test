"""Read-only reconciliation of the fixed Search3 LOCAL preview predecessor.

The command cannot upload, activate, complete or roll back a preview. It only
reads the existing fixed-target snapshot through the same trusted SSH lane used
by the guarded updater and emits sanitized predecessor metadata.
"""
from __future__ import annotations

import json
import os
from pathlib import Path
import re
import secrets
import shlex

from search3_local_preview_update_remote import need, json_bytes
from search3_preview_publish import Github, command, http

REPO = 'pyatkoff/poisk-turov-test'
OWNER_ID = 226193297
COORDINATION_ISSUE = 3419
PREFIX = '/inspect-search3-local-preview'
ROUTE = '/_preview/search3-local-candidate/'


def checked_request(event, env):
    need(env.get('GITHUB_REPOSITORY') == REPO and env.get('GITHUB_REF') == 'refs/heads/main', 'trusted_main_only')
    need(env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff'
         and env.get('GITHUB_ACTOR_ID') == str(OWNER_ID), 'owner_only')
    need(env.get('GITHUB_RUN_ATTEMPT') == '1', 'no_replay')
    need(event.get('repository', {}).get('full_name') == REPO
         and event['repository'].get('id') == 1345518271, 'repository_identity')
    need(event.get('sender', {}).get('id') == OWNER_ID
         and event['sender'].get('login') == 'pyatkoff', 'sender_identity')
    need(env.get('GITHUB_EVENT_NAME') == 'issue_comment'
         and event.get('action') == 'created', 'new_command_only')
    issue = event.get('issue', {})
    comment = event.get('comment', {})
    need(issue.get('number') == COORDINATION_ISSUE
         and 'pull_request' not in issue, 'coordination_only')
    need(comment.get('user', {}).get('id') == OWNER_ID
         and comment.get('author_association') == 'OWNER', 'comment_owner')
    need(comment.get('body') == PREFIX, 'command_syntax')
    run_id = env.get('GITHUB_RUN_ID', '')
    need(re.fullmatch('[1-9][0-9]{0,14}', run_id or ''), 'run_identity')
    return {'run': int(run_id)}


def _hex(value, length):
    return value if isinstance(value, str) and re.fullmatch('[0-9a-f]{%d}' % length, value) else None


def sanitize_snapshot(snapshot):
    need(isinstance(snapshot, dict), 'snapshot_shape')
    target = _hex(snapshot.get('target'), 64)
    owner = snapshot.get('owner') if isinstance(snapshot.get('owner'), dict) else {}
    source = _hex(owner.get('source'), 40)
    owner_digest = _hex(owner.get('digest'), 64)
    owner_run = owner.get('run')
    if type(owner_run) is not int or owner_run <= 0:
        owner_run = None
    owner_status = owner.get('status') if owner.get('status') in ('published', 'activated') else None
    match = bool(target and owner_digest and target == owner_digest)
    return {
        'schema_version': 1,
        'status': 'inspected_read_only',
        'route': ROUTE,
        'target_digest': target,
        'owner_status': owner_status,
        'owner_source': source,
        'owner_run': owner_run,
        'owner_digest': owner_digest,
        'owner_digest_matches_target': match,
        'predecessor_usable': bool(owner_status == 'published' and source and owner_run and match),
        'target_writes': 0,
        'publisher_metadata_writes': 0,
        'supplier_calls': 0,
        'real_leads': 0,
    }


def inspect():
    env = os.environ
    event = json.loads(Path(env['GITHUB_EVENT_PATH']).read_text())
    request = checked_request(event, env)
    api = Github(env['GH_TOKEN'])
    fresh = api.get('/issues/comments/' + str(event['comment']['id']))
    need(fresh.get('body') == PREFIX
         and fresh.get('user', {}).get('id') == OWNER_ID, 'command_revoked')

    work = Path(env['RUNNER_TEMP']) / 'search3-local-inspect'
    work.mkdir(mode=0o700)
    key = work / 'key'
    known = work / 'known_hosts'
    host = env['PREVIEW_HOST']
    user = env['PREVIEW_USER']
    need(re.fullmatch('[A-Za-z0-9][A-Za-z0-9.-]*', host)
         and re.fullmatch('[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}', user), 'SSH_identity')
    raw = env['PREVIEW_KEY'].replace('\r', '')
    if 'PRIVATE KEY' not in raw:
        import base64
        raw = base64.b64decode(raw, validate=True).decode()
    key.write_text(raw.rstrip() + '\n')
    key.chmod(0o600)

    source = Path(__file__).with_name('search3_local_preview_update_remote.py').read_bytes()
    try:
        command(['ssh-keygen', '-y', '-f', str(key)])
        known.write_bytes(command(['ssh-keyscan', '-T', '15', '-t', 'ed25519', host]))
        known.chmod(0o600)
        fingerprints = command(['ssh-keygen', '-lf', str(known), '-E', 'sha256']).decode().splitlines()
        need(len({line.split()[1] for line in fingerprints}) == 1, 'SSH_fingerprint_count')
        opts = [
            '-i', str(key),
            '-o', 'StrictHostKeyChecking=yes',
            '-o', 'UserKnownHostsFile=' + str(known),
            '-o', 'GlobalKnownHostsFile=/dev/null',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=15',
        ]

        def remote(action, payload):
            shell = 'python3 - ' + shlex.quote(action) + ' ' + shlex.quote(json.dumps(payload, separators=(',', ':')))
            return json.loads(command(['ssh', *opts, user + '@' + host, shell], source, 180))

        binding = {
            'name': f"search3-local-update-bind-{request['run']}-{secrets.token_hex(12)}.txt",
            'nonce': secrets.token_hex(32),
        }
        remote('bind', binding)
        try:
            code, body = http('/_preview/' + binding['name'])
            need(code == 200 and body == binding['nonce'], 'SSH_HTTPS_binding')
        finally:
            remote('unbind', binding)

        result = sanitize_snapshot(remote('snapshot', {}))
        result['binding_verified'] = True
        (work / 'evidence.json').write_bytes(json_bytes(result))
        print(json.dumps(result, indent=2, sort_keys=True))
    finally:
        key.unlink(missing_ok=True)
        known.unlink(missing_ok=True)


if __name__ == '__main__':
    inspect()
