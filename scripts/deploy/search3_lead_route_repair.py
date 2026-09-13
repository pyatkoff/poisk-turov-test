"""Owner-approved recovery of the canonical public lead route, never a release.

Only restores a known internal-adapter copy to the existing trusted bridge.
No PHP payload, secret, destination, source ref or server path is accepted as input.
"""
import json
import os
from pathlib import Path
import re
import secrets
import shlex

from search3_preview_publish import Github, OWNER_ID, REPO, command, http
from search3_preview_remote import ROUTE, digest, json_bytes, need

PREFIX = '/restore-search3-lead-route '


def checked_repair(event, env):
    need(env.get('GITHUB_REPOSITORY') == REPO and env.get('GITHUB_REF') == 'refs/heads/main', 'trusted_main_only')
    need(env.get('GITHUB_ACTOR') == 'pyatkoff' and env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff'
         and env.get('GITHUB_ACTOR_ID') == str(OWNER_ID), 'owner_only')
    need(env.get('GITHUB_EVENT_NAME') == 'issue_comment' and env.get('GITHUB_RUN_ATTEMPT') == '1', 'owner_command_no_replay')
    need(event.get('repository', {}).get('full_name') == REPO
         and event['repository'].get('id') == 1345518271, 'repository_identity')
    need(event.get('sender', {}).get('id') == OWNER_ID and event['sender'].get('login') == 'pyatkoff', 'sender_identity')
    issue = event.get('issue', {}); comment = event.get('comment', {})
    need(event.get('action') == 'created' and issue.get('number') == 996 and not issue.get('pull_request'), 'coordinator_only')
    need(comment.get('user', {}).get('id') == OWNER_ID and comment.get('author_association') == 'OWNER', 'comment_owner')
    control = env.get('GITHUB_SHA', '')
    need(re.fullmatch('[0-9a-f]{40}', control) and comment.get('body') == PREFIX + control, 'exact_control_command')
    return control


def main():
    env = os.environ; event = json.loads(Path(env['GITHUB_EVENT_PATH']).read_text())
    control = checked_repair(event, env); api = Github(env['GH_TOKEN'])
    fresh = api.get('/issues/comments/' + str(event['comment']['id']))
    need(fresh['body'] == event['comment']['body'] and fresh['user']['id'] == OWNER_ID, 'command_changed_or_revoked')
    need(api.get('/git/ref/heads/main')['object']['sha'] == control, 'control_main_changed')
    root = Path(__file__).resolve().parents[2]
    q = dict(deploy_run=int(env['GITHUB_RUN_ID']), attempt=1,
             bridge_sha256=digest((root / 'v2/lead-bridge-v1.php').read_bytes()),
             direct_sha256=digest((root / 'v2/lead-adapter-v2.php').read_bytes()))
    work = Path(env['RUNNER_TEMP']) / 'search3-lead-route-repair'; work.mkdir(mode=0o700)
    evidence = {'control_sha': control, 'deploy_run': q['deploy_run'], 'status': 'not_started',
                'real_leads': 0, 'supplier_searches': 0, 'production_release': False}
    host = env['PREVIEW_HOST']; user = env['PREVIEW_USER']
    need(re.fullmatch('[A-Za-z0-9][A-Za-z0-9.-]*', host)
         and re.fullmatch('[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}', user), 'SSH_identity')
    key = work / 'key'; known = work / 'known_hosts'
    try:
        raw = env['PREVIEW_KEY'].replace('\r', '')
        if 'PRIVATE KEY' not in raw:
            import base64
            raw = base64.b64decode(raw, validate=True).decode()
        key.write_text(raw.rstrip() + '\n'); key.chmod(0o600)
        command(['ssh-keygen', '-y', '-f', str(key)])
        known.write_bytes(command(['ssh-keyscan', '-T', '15', '-t', 'ed25519', host])); known.chmod(0o600)
        fingerprints = command(['ssh-keygen', '-lf', str(known), '-E', 'sha256']).decode().splitlines()
        need(len({x.split()[1] for x in fingerprints}) == 1, 'SSH_fingerprint_count')
        options = ['-i', str(key), '-o', 'StrictHostKeyChecking=yes', '-o', 'UserKnownHostsFile=' + str(known),
                   '-o', 'GlobalKnownHostsFile=/dev/null', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=15',
                   '-o', 'ServerAliveInterval=15', '-o', 'ServerAliveCountMax=3']
        source = Path(__file__).with_name('search3_preview_remote.py').read_bytes()
        def remote(action, request):
            cmd = 'python3 - ' + shlex.quote(action) + ' ' + shlex.quote(json.dumps(request, separators=(',', ':')))
            return json.loads(command(['ssh', *options, user + '@' + host, cmd], source, 120))
        binding = dict(name=f"site-binding-{q['deploy_run']}-1-{secrets.token_hex(12)}.txt", nonce=secrets.token_hex(32))
        remote('bind', binding)
        try:
            status, body = http(ROUTE + binding['name'] + '?bind=1')
            need(status == 200 and body == binding['nonce'], 'SSH_HTTPS_binding_failed')
        finally:
            remote('unbind', binding)
        need(http(ROUTE + binding['name'] + '?removed=1')[0] in (404, 410), 'binding_not_removed')
        q['before'] = remote('lead-inspect', {})
        evidence['diagnosis'] = {name: q['before']['protected'][name]
                                 for name in ('lead-adapter-v2.php', 'lead-bridge-v1.php')}
        evidence['bridge_configured'] = q['before']['bridge_configured']
        print(json.dumps({'diagnosis': evidence['diagnosis'], 'bridge_configured': evidence['bridge_configured']}), flush=True)
        need(api.get('/git/ref/heads/main')['object']['sha'] == control, 'control_main_changed_before_repair')
        evidence['recovery'] = remote('lead-restore', q)
        after = remote('lead-inspect', {})
        expected = {**q['before']['protected'], 'lead-adapter-v2.php': q['bridge_sha256']}
        need(after['protected'] == expected and after['target_digest'] == q['before']['target_digest']
             and after['owner'] == q['before']['owner'] and after['bridge_configured'], 'final_repair_readback')
        evidence.update(status=evidence['recovery']['status'], canonical_file_verified=True,
                        other_protected_unchanged=True, preview_unchanged=True)
        # Public HTTP/preview acceptance remains in the unchanged canonical publisher.
    except Exception:
        evidence['status'] = 'failed_stop_no_replay'
        raise
    finally:
        key.unlink(missing_ok=True); known.unlink(missing_ok=True)
        (work / 'evidence.json').write_bytes(json_bytes(evidence))
        print(json.dumps(evidence, indent=2))


if __name__ == '__main__':
    main()
