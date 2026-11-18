"""CLOSED preparation: read-only, fail-closed admission for one original 4294 run.
No install, dispatch, database or native command is executed by this module.
Binding receipts is a separate reviewed action; config never updates itself.
"""
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import time

APP_REPO = 'pyatkoff/max-search-bot'
CARRIER_REPO = 'pyatkoff/poisk-turov-test'
CARRIER = 'audit/price4294-evidence-20261118'
BEFORE = '848e74736431d7be1ce2a4a55229a4d2be8d793b'
FIX_RUNTIME = '92bd28bea3f8e00bffb154b267104640fd53ce17'
RUNTIME = 'bd8e248f7138ea75cb9fda2dcf44beb631c633a5'
CURRENT_PR_HEAD = '69856883ce0b4b511e0b08d178eac7cefbe43dfe'
RUNTIME_DIFF_PATHS = ('docs/PROJECT_FUNNEL.md', 'services/ConversationRecorder.php', 'services/ProjectFunnelStatusReader.php', 'services/RepairMaxWebhookService.php', 'tests/run_project_funnel_conversions_regression.php', 'tests/run_repair_max_webhook_regression.php')
HOOK_RUNTIME_PIN_PATHS = ('integrations/ChannelPostApiTransport.php', 'migrations/033_channel_post_context.sql', 'migrations/036_channel_post_member_delivery.sql', 'migrations/040_channel_post_reconciliation.sql', 'services/ChannelPostAdminService.php', 'services/ChannelPostAutomationService.php', 'services/ChannelPostButtonTransport.php', 'services/ChannelPostEntryService.php', 'services/ChannelPostMaxAlbumRecovery.php', 'services/ChannelPostMediaService.php', 'services/ChannelPostMemberDeliveryService.php', 'services/ChannelPostReconciliationService.php', 'services/ChannelPostReconciliationState.php', 'services/ChannelPostRegistryService.php', 'services/ChannelPostRuntime.php', 'services/ChannelPostTelegramAlbum.php', 'services/ChannelPostWebhookConfigService.php', 'services/ConversationDb.php', 'services/MaxTlsConfig.php', 'services/MaxTransport.php', 'services/ProjectConfig.php', 'services/ConversationRecordClock.php', 'services/ProjectConfiguration.php')
PR_HEAD = 'e7d90c7b8b91926fe7b150d58cb71b53f10776dc'
DEPLOY = 44329290180
OP = 'commercial-monitor-price-h4294-f72863a3310b60862a907f040008fd46c82e7e044485488e9eece8adb1237e24'
STATUSES = ('in_progress', 'queued', 'waiting', 'pending', 'requested')
REQUIRED_EXECUTOR_FILES = frozenset({'admit.py', 'pure.php', 'recover.php', 'safe-result.php',
    'editor-pure-proof.php', 'verify-release.js', 'release_priority.js',
    'deployment_release.js', 'release_slot_close.js', 'queue_guard.py', 'allowlist.json',
    'MAX-classification.json', 'POISK-classification.json', 'subprocess.php', 'probe-worker.php', 'probe-core.php'})

def need(value, error):
    if not value:
        raise RuntimeError(error)

def digest(data):
    return hashlib.sha256(data).hexdigest()

def canon(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':'), ensure_ascii=False).encode()

def sha(value, length=64):
    return isinstance(value, str) and re.fullmatch('[a-f0-9]{%d}' % length, value) is not None

def fresh(value, now):
    return type(value) is int and 0 <= now - value <= 300

def api(repo, path):
    # Explicit GET only. Never invoke shell, gh workflow run, install or PHP.
    return json.loads(subprocess.check_output(
        ['gh', 'api', '--method', 'GET', 'repos/' + repo + '/' + path], timeout=40))

def local(base, name):
    need(isinstance(name, str) and Path(name).name == name and name not in ('.', '..'), 'unsafe_path')
    p = base / name
    need(not p.is_symlink() and p.is_file(), 'file_missing_or_symlink')
    return p.read_bytes()

def receipt(base, spec):
    need(isinstance(spec, dict) and sha(spec.get('sha256')), 'receipt_unbound')
    raw = local(base, spec['file'])
    need(digest(raw) == spec['sha256'], 'receipt_changed')
    value = json.loads(raw)
    need(isinstance(value, dict), 'receipt_shape')
    return value

def terminal(get, repo, rid, head, path):
    need(type(rid) is int and rid > 0 and sha(head, 40) and bool(path), 'run_unbound')
    r = get(repo, 'actions/runs/' + str(rid))
    need(r.get('id') == rid and r.get('head_sha') == head and r.get('run_attempt') == 1
         and r.get('status') == 'completed' and r.get('conclusion') == 'success'
         and r.get('path') == path, 'original_terminal_not_accepted')
    return r

def pinned_comment(get, repo, cid, expected, author):
    need(type(cid) is int and cid > 0 and sha(expected) and type(author) is int and author > 0, 'comment_unbound')
    r = get(repo, 'issues/comments/' + str(cid))
    need(r.get('id') == cid and r.get('user', {}).get('id') == author
         and digest(r.get('body', '').encode()) == expected, 'coordination_changed')

def canonical_release(base):
    return json.loads(subprocess.check_output(['node', str(base / 'verify-release.js')], timeout=240, cwd=base))

def verify_current_runtime(get, c):
    # The original fix proof above is retained. This is a separate current-release gate.
    pr = get(APP_REPO, 'pulls/1598')
    need(pr.get('merged') is True and pr.get('head', {}).get('sha') == CURRENT_PR_HEAD
         and pr.get('merge_commit_sha') == RUNTIME, 'current_PR_not_accepted')
    cis = c.get('current_ci_runs', [])
    need(isinstance(cis, list) and len(cis) >= 2 and len({x['run_id'] for x in cis}) == len(cis), 'current_ci_unbound')
    for ci in cis:
        terminal(get, APP_REPO, ci['run_id'], CURRENT_PR_HEAD, ci['path'])
    diff = get(APP_REPO, 'compare/' + FIX_RUNTIME + '...' + RUNTIME)
    files = diff.get('files')
    need(diff.get('status') == 'ahead' and diff.get('merge_base_commit', {}).get('sha') == FIX_RUNTIME
         and isinstance(files, list) and len(files) == len(RUNTIME_DIFF_PATHS)
         and len({f.get('filename') for f in files}) == len(files)
         and {f.get('filename') for f in files} == set(RUNTIME_DIFF_PATHS)
         and all(f.get('status') == 'modified' and 'previous_filename' not in f for f in files)
         and len(HOOK_RUNTIME_PIN_PATHS) == 23
         and not set(RUNTIME_DIFF_PATHS).intersection(HOOK_RUNTIME_PIN_PATHS), 'runtime_diff_out_of_scope')

def evaluate(c, helper, env, event, base, get=api, now=None, verify_release=None, clock=None, verify_queue=None):
    clock = time.time if clock is None else clock
    now = int(clock()) if now is None else now
    need(c.get('enabled') is True and helper.get('enabled') is True, 'gate_closed')
    need(c.get('schema') == 'price4294-admission-v1' and c.get('binding_reviewed') is True, 'binding_unreviewed')
    need(env.get('GITHUB_REPOSITORY') == CARRIER_REPO and env.get('GITHUB_EVENT_NAME') == 'push'
         and env.get('GITHUB_RUN_ATTEMPT') == '1' and env.get('GITHUB_ACTOR') == 'pyatkoff'
         and event.get('before') == BEFORE and event.get('after') == env.get('GITHUB_SHA')
         and event.get('ref') == 'refs/heads/' + CARRIER, 'original_event_required')
    head = env.get('GITHUB_SHA'); rid = int(env.get('GITHUB_RUN_ID', '0'))
    need(sha(head, 40) and rid > 0, 'original_run_missing')
    own = get(CARRIER_REPO, 'actions/runs/' + str(rid))
    need(own.get('head_sha') == head and own.get('run_attempt') == 1 and own.get('event') == 'push'
         and own.get('head_branch') == CARRIER and own.get('path') == c['workflow_path']
         and own.get('status') == 'in_progress', 'original_run_changed')
    need(get(CARRIER_REPO, 'git/ref/heads/' + CARRIER)['object']['sha'] == head, 'carrier_moved')
    need(get(APP_REPO, 'git/ref/heads/main')['object']['sha'] == RUNTIME, 'main_moved')
    need(helper.get('runtime_sha') == RUNTIME and helper.get('operation') == OP, 'runtime_or_operation_unbound')
    files = c.get('files', {})
    need(files and set(files) == set(c.get('expected_files', [])) and REQUIRED_EXECUTOR_FILES <= set(files), 'manifest_incomplete')
    for name, pin in files.items():
        raw = local(base, name)
        need(sha(pin.get('sha256')) and sha(pin.get('blob_sha'), 40), 'manifest_unbound')
        need(digest(raw) == pin['sha256'] and hashlib.sha1(b'blob ' + str(len(raw)).encode() + b'\0' + raw).hexdigest() == pin['blob_sha'], 'manifest_changed')
    # Helper config digest excludes only the final admission digest to avoid a hash cycle.
    h = dict(helper); h.pop('admission_sha256', None)
    need(digest(canon(h)) == c.get('helper_config_payload_sha256'), 'helper_config_changed')
    need(len(c.get('ci_runs', [])) >= 2, 'ci_unbound')
    for ci in c['ci_runs']:
        terminal(get, APP_REPO, ci['run_id'], PR_HEAD, ci['path'])
    pr = get(APP_REPO, 'pulls/1620')
    need(pr.get('merged') is True and pr.get('head', {}).get('sha') == PR_HEAD
         and pr.get('merge_commit_sha') == FIX_RUNTIME, 'PR_not_accepted')
    verify_current_runtime(get, c)
    release = helper['technical_release_proof']
    need(release.get('application_sha') == RUNTIME and release.get('deploy_run_id') == DEPLOY, 'release_unbound')
    deploy = terminal(get, APP_REPO, DEPLOY, RUNTIME, c['deploy_workflow_path'])
    need(deploy.get('event') == 'push', 'deploy_event_changed')
    # The canonical release verifier owns downstream tail and shared publisher proof.
    # Do not replace eight downstream workflows with steps on the Deploy job.
    release_receipt = (verify_release or (lambda: canonical_release(base)))()
    need(release_receipt == {
        'schema': 'price4294-canonical-release-v1', 'repository': APP_REPO,
        'repository_sha': RUNTIME, 'deploy_run_id': DEPLOY,
        'technical_release_accepted': True, 'technical_publisher_free': True,
        'verifier': 'verifyPreviousRelease', 'publisher_override': False},
        'canonical_release_not_accepted')
    need(digest(canon(release_receipt)) == release.get('receipt_sha256'), 'release_receipt_changed')
    closer = c['closer_comment']
    pinned_comment(get, APP_REPO, closer['id'], closer['sha256'], closer['author_id'])
    claims = helper['price_window_claims']
    need(isinstance(claims, list) and len(claims) > 0 and len(claims) == len(c['claim_bindings']), 'claims_unbound')
    for claim, binding in zip(claims, c['claim_bindings']):
        need(claim == binding['claim'] and binding.get('price_window_owned') is True, 'claim_not_owned')
        for kind in ('claim', 'ack'):
            pinned_comment(get, binding[kind + '_repo'], claim[kind + '_id'], claim[kind + '_body_sha256'], binding[kind + '_author_id'])
    need(set(c['gha_repositories']) == {APP_REPO, CARRIER_REPO}, 'gha_scope_unbound')
    if verify_queue is None:
        queue_spec = importlib.util.spec_from_file_location('finite_queue_guard', base / 'queue_guard.py')
        queue_module = importlib.util.module_from_spec(queue_spec); queue_spec.loader.exec_module(queue_module)
        verify_queue = queue_module.verify
    queue_proof = verify_queue(get, CARRIER_REPO, rid, head, c['queue_expected_mains'], base)
    route = receipt(base, c['route_attestation'])
    need(route == c['expected_route_attestation'] and route.get('repo') == 'pyatkoff/tour-max'
         and route.get('source') == 'private_connector' and sha(route.get('head'), 40)
         and fresh(route.get('observed_at_epoch'), now)
         and route.get('counts') == {s: 0 for s in STATUSES}, 'route_stale_or_busy')
    producer = helper['producer_proof']
    native = receipt(base, c['producer_receipt'])
    need(c['producer_receipt']['sha256'] == producer['receipt_sha256'] and producer['run_id'] == rid
         and fresh(producer.get('observed_at_epoch'), now)
         and native.get('run_id') == rid and native.get('head_sha') == head
         and native.get('runtime_sha') == RUNTIME and native.get('observed_at_epoch') == producer['observed_at_epoch']
         and native.get('native_active_collectors') == 0 and type(native.get('native_active_collectors')) is int
         and native.get('sql_active_collectors') == 0 and type(native.get('sql_active_collectors')) is int
         and native.get('unknown_collectors') is False, 'native_collectors_not_proven_idle')
    for key in ('operation', 'global_pin', 'child_pin', 'feed_shas', 'staged_bundle_sha256', 'cohort_sha256', 'collector_sha256'):
        need(native.get(key) == helper.get(key) and key in native and key in helper, 'producer_pin_changed')
    # Long canonical release/API reads must not extend the original proof TTL.
    completed_at = int(clock())
    need(completed_at >= now and fresh(route.get('observed_at_epoch'), completed_at)
         and fresh(producer.get('observed_at_epoch'), completed_at), 'completion_proofs_stale_or_future')
    return {**{k: helper[k] for k in ('operation', 'runtime_sha', 'staged_bundle_sha256', 'global_pin', 'child_pin', 'feed_shas', 'price_window_claims', 'technical_release_proof', 'producer_proof')},
            'observed_at_epoch': now, 'original_run_id': rid, 'head_sha': head,
            'no_active_collectors': True, 'route_idle': True, 'price_window_owned': True,
            'technical_release_accepted': True, 'technical_publisher_free': True,
            'helper_config_payload_sha256': digest(canon(h)), 'admission_config_sha256': digest(canon(c)),
            'manifest_sha256': digest(canon(files)), 'queue_relevance_proof': queue_proof, 'route_attestation_sha256': c['route_attestation']['sha256'],
            'route_observed_at_epoch': route['observed_at_epoch']}

def main():
    base = Path(__file__).resolve().parent
    c = json.loads(local(base, 'admission-config-CLOSED.json'))
    helper = json.loads(local(base, 'config-CLOSED.json'))
    need(c.get('enabled') is True and helper.get('enabled') is True, 'gate_closed')
    event = json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_bytes())
    print(json.dumps(evaluate(c, helper, os.environ, event, base), sort_keys=True))

if __name__ == '__main__':
    main()
