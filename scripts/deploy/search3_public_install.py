#!/usr/bin/env python3
"""One reviewed Search3 overlay transaction in the existing root-gateway carrier.

No application execution, endpoint probe, configuration repair or generic target.
The LIVE builder's deployable:false is preserved; only this separately admitted,
fixed operation can consume its exact overlay after all dependency checks.
"""
import ast
import base64
import hashlib
import io
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import zipfile

import search3_root_gateway_install as carrier
import search3_root_gateway_inspect as inspector

REPO = 'pyatkoff/poisk-turov-test'
WORKFLOW = '.github/workflows/search3-root-gateway-install.yml'
PREFIX = '/install-search3-public'
OPERATION = 'public-search3-20261120-v1'
INSPECT_PREFIX = '/inspect-search3-public-install'
INSPECT_OPERATION = 'public-search3-readiness-20261120-v1'
ROLLBACK_PREFIX = '/rollback-search3-public'
ROLLBACK_OPERATION = 'public-search3-rollback-20261120-v1'
SOURCE = 'f4d14cb7a66be040798693e9a47a2d023c48de7e'
TREE = '80b89f7e605594288f72e9cab885a6c60cc19a71'
RELEASE = '9040bb83011a18ec5f001c57f92218e53f0b255c'  # Reviewed PR5118 release merge; SOURCE tree is identical.
BUILD_MAIN = 'c5513aaee18af701f1253678e79f41e5725b71a2'
# Exact LIVE build proof; admission separately requires every final source check
# and the normal release merge before any server connection.
BUILD_RUN = 44785537969
ARTIFACT = 14653981913
ARTIFACT_SHA = '37e7c65f1c9f8c2b88df76c8b42191a6695ba8c8ac44a63a5ac4681b60f3bc7f'
MANIFEST_SHA = '3ef8fadb6db83cff9709fdc06d0dcaf41673456c92b1e77f40cdc8444ae8da5f'
# Filled only from the final accepted original metadata, never from caller input.
ARTIFACT_NAME = 'search3-public-live-f4d14cb7a66be040798693e9a47a2d023c48de7e'
SOURCE_CHECK_NAMES = ('visual-migration','focused-presentation','integration-contracts','browser-matrix','lead-source','focused',
                      'destination-identities','contract','build-preview-artifact','guard','source-receipt','fixture-drill',
                      'canonical-cards','canonical-meal-client','renderer-date-display')
SIGNING_ARTIFACT = 14651207961
SIGNING_ARTIFACT_SHA = 'd9f756a73a6e6513b5f858426936b510f400eb9ad9a41678aa4f3ef7a553dab9'
SIGNING_RESULT_SHA = '26a2a0c9fffe620746d2c2b9d73b695ec92a3bd5f36b7d551b3ce321855d049d'
SIGNING_RUN = 44781856520
SIGNING_RECEIPT_COMMENT = 7046090497
PREFLIGHT_RUN = 44790620377
PREFLIGHT_CONTROL = '927d3f213eadad98dcf4771c0c21612af7bfd0ed'
PREFLIGHT_COMMAND = 7047112039
PREFLIGHT_RECEIPT_COMMENT = 7047276834
PREFLIGHT_ARTIFACT = 14656147196
PREFLIGHT_ARTIFACT_NAME = 'search3-public-install-44790620377-1'
PREFLIGHT_ARTIFACT_SHA = 'a1edf23fd86e24bea82dc2d97de89e47b3fb6d75f469d374683b6c205dd21cf1'
PREFLIGHT_RESULT_SHA = 'dafc322bd6c6a0c8922eccfa4f45694cefdec190ee24a454c30e7fd2afaa298b'
PUBLISHER_GUARD_SHA = '9b81eac1a94263fcfb9f49948495c45d143ffbb1bcd91d96921ee3257423da40'
ENTRY = 'poisk-turov/index.php'
ENTRY_BEFORE = {'state': 'present', 'sha256': '9d00c5f952e0c94cbe850e9fd5c95aa9b4e2cf8bd1ff7732faa3f1e10c1a1438', 'size': 862, 'mode': '0644'}
ADDITIONS = (
    'poisk-turov-old/index.php',
    'prototype-search/calendar-exact-hotel-v1.js',
    'prototype-search/data.js',
    'prototype-search/hotel-popularity-v1.js',
    'prototype-search/lead.js',
    'prototype-search/messenger-context-v1.js',
    'prototype-search/messenger-flow-v1.js',
    'prototype-search/search-lifecycle-v1.js',
    'prototype-search/source-receipt-v1.js',
    'search3-canonical-profiles-v1.js',
    'search3-local-db-provider-v1.js',
    'visual-search/app.js',
    'visual-search/assets/anytour-logo.svg',
    'visual-search/assets/favicon.png',
    'visual-search/assets/inter-LICENSE.txt',
    'visual-search/assets/inter-cyrillic-ext.woff2',
    'visual-search/assets/inter-cyrillic.woff2',
    'visual-search/assets/inter-latin.woff2',
    'visual-search/assets/operators/anex.svg',
    'visual-search/assets/operators/biblio-globus.svg',
    'visual-search/assets/operators/coral-travel.png',
    'visual-search/assets/operators/fun-sun.svg',
    'visual-search/assets/operators/loti.png',
    'visual-search/assets/photo-unavailable.svg',
    'visual-search/entry-v1.php',
    'visual-search/filter-panel-v1.js',
    'visual-search/flight-picker-ui-v1.js',
    'visual-search/flight-picker-v18.js',
    'visual-search/hotel-details-v1.js',
    'visual-search/index.html',
    'visual-search/index.php',
    'visual-search/lead-runtime-v1.js',
    'visual-search/live-bridge.js',
    'visual-search/mobile-controls-v1.css',
    'visual-search/offer-list-v1.js',
    'visual-search/public-analytics-v1.js',
    'visual-search/public-bootstrap-v1.js',
    'visual-search/public-build.json',
    'visual-search/public-config-v1.js',
    'visual-search/public-url-v1.js',
    'visual-search/styles.css',
)
# Public hashes observed in readiness7045700047, main JS from retained main,
# INT14 from accepted6528951578 and fresh readiness; these files are never copied.
PROTECTED = {
    'index.php': '80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f',
    'api-v2.php': '90ddd43020aff509430b6ff85887a267ea4f0b30acc2213c82d7dcce0eb862f2',
    'search-page-v2.php': '80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f',
    'runtime-v3.js': '67f014910a5344fab7ed7e70d405f6e3256578eb84e8fbe634eed2a2c8baef92',
    'analytics-v4.js': '9f91622ad81d1812753083008d8225e1619dd7fd7fb7ba8f0fdea0e7c65e8602',
    'lead-search-context.js': '8d6eafbdf9fbb23aa5984edbe2c585b65c5647517492f7799c1dd732e710335f',
    'tour-controller-v4.js': 'ad2e5e1e3e7c4d021d291cbb8a60748d8d9a54a7c1c1e3d4aefb7296d427c0ae',
    'lead-form-guard-v1.js': '42427863281ca0bdcb7a84693f57687cd10747196dc85fc5daffdd477fc7c48a',
    # Required PHP owners from the exact LIVE manifest; never execute them here.
    'analytics-config.php': '1cdb2d53eda3e52c5ba1480f3df5d184c3b51ecf707952cb49560c395e463a82',
    'seo-config.php': 'efb78a7837f762b4992856903aafa0dad00449637a99bc668165e20bf2de9482',
    # LIVE data.js uses these root readers. Retain exact main c551 bytes,
    # including its own DB helper; do not substitute the release DB helper.
    'data/departures-v1.php': 'ec5ccf224dc9684a95d2b52feadb12cbd88357e4c511f16e54ff58ee7dafbf6b',
    'data/hotel-search-v1.php': 'c7ca2e7e405fde5a30b9f758b0c349be75961fe9d3f8d7ab052eb5f70ba08dba',
    'data/db-v1.php': 'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13',
    **inspector.READINESS_REFERENCES['int_retained_receipt']['hashes'],
    **inspector.READINESS_REFERENCES['next_payload']['hashes'],
    # NEXT artifact14605214194/manifest454c2050; no registry flag needed by reader.
    '_preview/search3-next-candidate/data/local-tv-catalog-read-v1.php': '32d1c3a5694c857363d1a2889e7be123ac3fa7d17647bc39fe64fc11b9d7063e',
    '_preview/search3-next-candidate/data/local-tv-catalog-v1.php': 'abbed95e0343ce402cdcfb11ef7819da0a12e5bafb7e5bf7f22cbb9260167b11',
    '_preview/search3-next-candidate/data/db-v1.php': 'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4',
    # Exact release63844d source pins, NOT asserted installed until preflight matches.
    '_preview/search3-local-candidate/data/search3-destination-read-v1.php': '783e6753f3b107b3642c53d9c6e37e12c45756c13ef8e00c4423ad489c807833',
    '_preview/search3-local-candidate/data/search3-local-results-read-v1.php': '9490965bc81dedf0374f85c50bcb515ed865313c713f5326144eb124db98d329',
    '_preview/search3-local-candidate/data/hotel-details-read-v1.php': '87e5a11285e54144aa9417ef601bb99e2ef99edcf947d59d37d455d9c531b9a3',
    # Fixed require closure of those LOCAL endpoints in release63844d. These
    # are source expectations only; any installed mismatch stops before writes.
    '_preview/search3-local-candidate/data/anytour-canonical-catalog-v1.php': '06e737f7b00bfe17c1f9410c19f880d65b0bfad3345f0d79e62727282601041a',
    '_preview/search3-local-candidate/data/anytour-destination-catalog-v1.php': '34f8c351d1ffeb4f45a8b4ad83c38f9d4eb5e539cb660dd338b3ad0497276253',
    '_preview/search3-local-candidate/data/anytour-hotel-stay-catalog-v2.php': '7a0b9081bc1dd3033d1110441e0bc918e1c6cc6c5657cda0f3d3c50e39882a5a',
    '_preview/search3-local-candidate/data/anytour-offer-scope-index-v1.php': 'da85fc001440777198a47b045f3cae2d362086cd952000f281291d7a179cb542',
    '_preview/search3-local-candidate/data/anytour-offer-store-read-v2.php': 'c2e699a1f4e41f265cbf7527754e380724f254f29460fe32b285eb197f8b3c80',
    '_preview/search3-local-candidate/data/anytour-provider-identity-bridge-v1.php': '641e92a97c91a40166a4271662044ac54ba68aaa6ab74628315e20eca6f076ad',
    '_preview/search3-local-candidate/data/anytour-search-meal-catalog-v1.php': '41f93859397539bdf2a46d29bcf7a7c51e45cf8bd82364b0366abb9720f5ed63',
    '_preview/search3-local-candidate/data/anytour-search-scope-v1.php': '671a8cd19c8e2ac61fb03949a8803751c74917f99b21e0f523608067e2ae5cf6',
    '_preview/search3-local-candidate/data/anytour-stay-catalog-v1.php': '17ec69cf71fb46c60f6db62069ee6eb28a94bd57a214fec1837a051d4375fa5f',
    '_preview/search3-local-candidate/data/db-v1.php': 'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4',
    '_preview/search3-local-candidate/data/hotel-details-v1.php': '137bbf53ef87fab50505ecd41a075cb5f571193480da7f0e3face51190c0f77f',
    '_preview/search3-local-candidate/data/hotel-presentation-read-v1.php': '17c9c1ea19de0d86bf6d2d4f13426a8a0cbf232686abd63ec1db5414ccc3d9a3',
    '_preview/search3-local-candidate/data/price-calendar-core-v1.php': 'ab0151520328a24989724af6dae7b9cdc3ce4c0dd7b74630a62bede9c6b9ecbe',
    # Accepted TV enable6392874420, retained6528951578. Hash only; never copied.
    '_preview/search3-anex-candidate/.anex-private.php': 'faea72b846d201013a6426485996c0a2c022148ec897ae3092015c0946fb3d27',
}
# Historical inspection expectations above remain immutable. Claim7047321410
# reviewed these exact installed bytes against known sources; they are retained,
# never copied by this operation. This is not an observed-value wildcard or a
# retry of the completed preflight_mismatch inspection7047276834.
INSTALL_REVIEWED = {
    'data/db-v1.php': {
        'source_sha': '4907a2ed8bab30085c91d755e9b4e587ca7624ef',
        'sha256': 'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4', 'size': 3861},
    '_preview/search3-local-candidate/data/anytour-canonical-catalog-v1.php': {
        'source_sha': '5e275c1bc0e91247ea2968bb9ce78b569889060b',
        'sha256': '9a346940e84fa1eac3e20e9cee024dc4741448c9c2453421dc04bc38946c5129', 'size': 20648},
    '_preview/search3-local-candidate/data/hotel-details-v1.php': {
        'source_sha': 'e1285138be62a5881043dab842c15d6fa89ae3a4',
        'sha256': '401990a74e46d3294e6370f5da6ce134200f9a69f9287f15383074b782c62411', 'size': 6295},
}
INSTALL_PROTECTED = {**PROTECTED, **{path: row['sha256'] for path, row in INSTALL_REVIEWED.items()}}
PRIVATE_CONFIG = '_preview/search3-anex-candidate/.anex-private.php'
# Existing public PHP bridges may read these installed settings. Keep opaque
# hash/mode/presence snapshots locally; never copy, execute or export their bytes.
OPAQUE_SETTINGS = ('config.php', 'site_conf.php')
# The accepted readback observed these presences. Keep the compatibility
# prerequisite fresh without exporting or pinning private configuration bytes.
INSTALL_OPAQUE_PRESENCE = {'config.php': True, 'site_conf.php': False}
# The baseline has no optional popularity include; an unreviewed installed include
# must stop this operation before writes rather than start its transitive DB code.
# Existing LOCAL/NEXT publishers exclude config.php; require that absence so their
# reviewed DB helpers cannot acquire an unreviewed sibling configuration shadow.
ABSENT_DEPENDENCIES = ('data/hotel-popularity-v1.php',
                       '_preview/search3-local-candidate/config.php', '_preview/search3-next-candidate/config.php')
CONTROL_PATHS = (
    WORKFLOW, 'scripts/deploy/search3_public_install.py', 'tests/search3_public_install_test.py',
    '.github/workflows/deploy-anytoour.yml', 'tests/anytoour_production_deploy_guard_test.py',
    'scripts/deploy/search3_root_gateway_install.py', 'scripts/deploy/search3_root_gateway_inspect.py',
)
CONTROL_CHECKS = ('guard', 'core', 'public-install-contracts', 'production-readiness-contracts', 'contracts',
                  'live-check-contracts', 'tv-diagnostic-contracts', 'context-sender-contracts')
MAX_BYTES = 2 * 1024 * 1024
MAX_ZIP = 16 * 1024 * 1024
api = carrier.checked_api
need = carrier.need
sha = carrier.sha


def fixed_command(inspect=False):
    need(type(inspect) is bool, 'mode')
    return (INSPECT_PREFIX, INSPECT_OPERATION) if inspect else (PREFIX, OPERATION)


def event_identity(event, env, inspect=False):
    prefix, operation = fixed_command(inspect)
    need(env.get('GITHUB_REPOSITORY') == event.get('repository', {}).get('full_name') == REPO, 'repository')
    need(env.get('GITHUB_REF') == 'refs/heads/main' and env.get('GITHUB_WORKFLOW_REF') == f'{REPO}/{WORKFLOW}@refs/heads/main', 'trusted_workflow')
    need(env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff', 'actor')
    need(env.get('GITHUB_EVENT_NAME') == 'issue_comment' and event.get('action') == 'created' and env.get('GITHUB_RUN_ATTEMPT') == '1', 'event_attempt')
    issue, comment = event.get('issue', {}), event.get('comment', {})
    need(issue.get('number') == 4709 and 'pull_request' not in issue, 'issue')
    need(type(comment.get('user', {}).get('id')) is int and comment['user']['id'] == 226193297 and comment.get('author_association') == 'OWNER', 'owner')
    control, run = env.get('GITHUB_SHA', ''), env.get('GITHUB_RUN_ID', '')
    need(re.fullmatch('[a-f0-9]{40}', control) and re.fullmatch('[1-9][0-9]{0,19}', run), 'identity')
    need(type(comment.get('id')) is int and comment['id'] > 0 and comment.get('body') == f'{prefix} {control} {operation}', 'command')
    return {'control_source_sha': control, 'run_id': run, 'command_comment_id': comment['id']}


def rollback_event_identity(event, env):
    need(env.get('GITHUB_REPOSITORY') == event.get('repository', {}).get('full_name') == REPO, 'repository')
    need(env.get('GITHUB_REF') == 'refs/heads/main' and env.get('GITHUB_WORKFLOW_REF') == f'{REPO}/{WORKFLOW}@refs/heads/main', 'trusted_workflow')
    need(env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff', 'actor')
    need(env.get('GITHUB_EVENT_NAME') == 'issue_comment' and event.get('action') == 'created' and env.get('GITHUB_RUN_ATTEMPT') == '1', 'event_attempt')
    issue, comment = event.get('issue', {}), event.get('comment', {})
    need(issue.get('number') == 4709 and 'pull_request' not in issue, 'issue')
    need(type(comment.get('user', {}).get('id')) is int and comment['user']['id'] == 226193297 and comment.get('author_association') == 'OWNER', 'owner')
    control, run = env.get('GITHUB_SHA', ''), env.get('GITHUB_RUN_ID', '')
    need(re.fullmatch('[a-f0-9]{40}', control) and re.fullmatch('[1-9][0-9]{0,19}', run), 'identity')
    match = re.fullmatch(re.escape(f'{ROLLBACK_PREFIX} {control} ') + r'([1-9][0-9]{0,19}) ' + re.escape(ROLLBACK_OPERATION), comment.get('body', ''))
    need(type(comment.get('id')) is int and comment['id'] > 0 and match is not None and match[1] != run, 'command')
    return {'control_source_sha': control, 'run_id': run, 'command_comment_id': comment['id']}, match[1]


def pages(path, key=None):
    result, seen, expected = [], set(), None
    for page in range(1, 101):
        value = api(path + ('&' if '?' in path else '?') + f'per_page=100&page={page}')
        rows = value.get(key) if key else value
        need(isinstance(rows, list) and len(rows) <= 100, 'pagination_shape')
        if key:
            count = value.get('total_count')
            need(type(count) is int and count >= 0 and (expected is None or expected == count), 'pagination_total')
            expected = count
        for row in rows:
            need(isinstance(row, dict) and type(row.get('id')) is int and row['id'] > 0 and row['id'] not in seen, 'pagination_duplicate')
            seen.add(row['id']); result.append(row)
        if len(rows) < 100:
            need(expected is None or expected == len(result), 'pagination_incomplete')
            return result
    raise RuntimeError('pagination_incomplete')


def green(check):
    return check.get('status') == 'completed' and check.get('conclusion') == 'success' and check.get('app', {}).get('slug') == 'github-actions'


def reviewed_control(control):
    def blobs(head):
        values = [api('/contents/' + path + '?ref=' + head) for path in CONTROL_PATHS]
        need(all(v.get('type') == 'file' and re.fullmatch('[a-f0-9]{40}', v.get('sha', '')) for v in values), 'control_files')
        return [v['sha'] for v in values]
    current, head, seen = blobs(control), control, set()
    for _ in range(24):
        need(head not in seen, 'control_ancestry'); seen.add(head)
        commit = api('/git/commits/' + head); parents = commit.get('parents', [])
        need(commit.get('sha') == head and isinstance(parents, list) and len(parents) <= 2, 'control_ancestry')
        if len(parents) == 2:
            reviewed = parents[1]['sha']; other = api('/git/commits/' + reviewed)
            if other.get('tree', {}).get('sha') == commit.get('tree', {}).get('sha') and blobs(reviewed) == current:
                checks = pages('/commits/' + reviewed + '/check-runs', 'check_runs')
                if all(green(max((c for c in checks if c.get('name') == name and c.get('head_sha') == reviewed), key=lambda c: c['id'], default={})) for name in CONTROL_CHECKS):
                    return
        need(parents, 'reviewed_control_missing'); head = parents[0]['sha']
    raise RuntimeError('reviewed_control_missing')


def final_heads(identity):
    need(api('/git/ref/heads/main')['object']['sha'] == identity['control_source_sha'], 'main_moved')
    need(api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha'] == RELEASE, 'release_moved')
    pr = api('/pulls/5118')
    need(pr.get('state') == 'closed' and pr.get('merged') is True and pr.get('merge_commit_sha') == RELEASE and pr.get('head', {}).get('sha') == SOURCE
         and pr.get('head', {}).get('repo', {}).get('full_name') == REPO
         and pr.get('base', {}).get('ref') == 'release/search3-production-ready-v1'
         and pr.get('base', {}).get('repo', {}).get('full_name') == REPO, 'candidate_moved')


def admission(identity, inspect=False):
    prefix, operation = fixed_command(inspect)
    need(isinstance(RELEASE, str) and re.fullmatch('[a-f0-9]{40}', RELEASE)
         and isinstance(ARTIFACT_NAME, str) and ARTIFACT_NAME and type(SIGNING_RECEIPT_COMMENT) is int, 'pending_final_evidence')
    final_heads(identity)
    need(api('/git/commits/' + SOURCE).get('tree', {}).get('sha') == TREE, 'source_tree')
    need(api('/git/commits/' + RELEASE).get('tree', {}).get('sha') == TREE, 'release_tree')
    gate = api('/contents/.github/workflows/deploy-anytoour.yml?ref=' + identity['control_source_sha'])
    need(gate.get('type') == 'file' and gate.get('encoding') == 'base64'
         and sha(base64.b64decode(gate['content'])) == PUBLISHER_GUARD_SHA, 'publisher_guard')
    reviewed_control(identity['control_source_sha'])
    checks = pages('/commits/' + SOURCE + '/check-runs', 'check_runs')
    need(len(checks) == len(SOURCE_CHECK_NAMES) and {c.get('name') for c in checks} == set(SOURCE_CHECK_NAMES)
         and all(c.get('head_sha') == SOURCE and green(c) for c in checks), 'source_ci')
    run = api('/actions/runs/' + str(BUILD_RUN))
    need(run.get('head_sha') == SOURCE and run.get('path') == '.github/workflows/search3-visual-migration.yml'
         and run.get('event') == 'pull_request' and run.get('run_attempt') == 1
         and run.get('status') == 'completed' and run.get('conclusion') == 'success', 'build_run')
    artifact = api('/actions/artifacts/' + str(ARTIFACT))
    need(artifact.get('id') == ARTIFACT and artifact.get('name') == ARTIFACT_NAME and artifact.get('expired') is False
         and artifact.get('digest') == 'sha256:' + ARTIFACT_SHA
         and artifact.get('workflow_run', {}).get('id') == BUILD_RUN, 'artifact_identity')
    command = f'{prefix} {identity["control_source_sha"]} {operation}'
    comments = pages('/issues/4709/comments')
    matches = [c for c in comments if c.get('body', '').strip().split()[:1] == [prefix] and operation in c.get('body', '').split()[1:]]
    need(len(matches) == 1 and matches[0]['id'] == identity['command_comment_id'] and matches[0].get('body') == command
         and matches[0].get('user', {}).get('id') == 226193297 and matches[0].get('author_association') == 'OWNER', 'operation_already_reserved')
    accepted = next((c for c in comments if c['id'] == SIGNING_RECEIPT_COMMENT), {})
    need(accepted.get('user', {}).get('id') == 226193297 and accepted.get('author_association') == 'OWNER'
         and str(SIGNING_RUN) in accepted.get('body', '') and SIGNING_RESULT_SHA in accepted.get('body', ''), 'signing_acceptance')
    if not inspect:
        inspected = [c for c in comments if c.get('body', '').strip().split()[:1] == [INSPECT_PREFIX]
                     and INSPECT_OPERATION in c.get('body', '').split()[1:]]
        need(len(inspected) == 1 and inspected[0]['id'] == PREFLIGHT_COMMAND
             and inspected[0].get('body') == f'{INSPECT_PREFIX} {PREFLIGHT_CONTROL} {INSPECT_OPERATION}'
             and inspected[0].get('user', {}).get('id') == 226193297
             and inspected[0].get('author_association') == 'OWNER', 'preflight_command')
        accepted = next((c for c in comments if c['id'] == PREFLIGHT_RECEIPT_COMMENT), {})
        need(accepted.get('user', {}).get('id') == 226193297 and accepted.get('author_association') == 'OWNER'
             and all(token in accepted.get('body', '') for token in
                     (str(PREFLIGHT_RUN), PREFLIGHT_RESULT_SHA, 'preflight_mismatch', 'CONSUMED_NO_REPLAY')), 'preflight_acceptance')
    final_heads(identity)


def rollback_main(identity):
    need(api('/git/ref/heads/main')['object']['sha'] == identity['control_source_sha'], 'main_moved')


def rollback_commands(identity, original_run, expected_original=None):
    need(isinstance(original_run, str) and re.fullmatch('[1-9][0-9]{0,19}', original_run)
         and original_run != identity['run_id'], 'rollback_install_run')
    comments = pages('/issues/4709/comments')
    matches = [c for c in comments if c.get('body', '').strip().split()[:1] == [ROLLBACK_PREFIX]
               and ROLLBACK_OPERATION in c.get('body', '').split()[1:]]
    need(len(matches) == 1 and matches[0]['id'] == identity['command_comment_id']
         and matches[0].get('body') == f'{ROLLBACK_PREFIX} {identity["control_source_sha"]} {original_run} {ROLLBACK_OPERATION}'
         and matches[0].get('user', {}).get('id') == 226193297 and matches[0].get('author_association') == 'OWNER', 'operation_already_reserved')
    installed = [c for c in comments if c.get('body', '').strip().split()[:1] == [PREFIX]
                 and OPERATION in c.get('body', '').split()[1:]]
    need(len(installed) == 1 and installed[0].get('user', {}).get('id') == 226193297
         and installed[0].get('author_association') == 'OWNER', 'rollback_install_command')
    match = re.fullmatch(re.escape(PREFIX) + r' ([a-f0-9]{40}) ' + re.escape(OPERATION), installed[0].get('body', ''))
    need(match is not None, 'rollback_install_command')
    original = {'control_source_sha': match[1], 'run_id': original_run, 'command_comment_id': installed[0]['id']}
    need(expected_original is None or original == expected_original, 'rollback_install_command')
    return original


def rollback_admission(identity, original_run):
    # Recovery must not depend on signer validity, a newer release or source CI.
    rollback_main(identity); reviewed_control(identity['control_source_sha'])
    original = rollback_commands(identity, original_run)
    run = api('/actions/runs/' + original_run)
    need(run.get('id') == int(original_run) and run.get('head_sha') == original['control_source_sha'] and run.get('head_branch') == 'main'
         and run.get('path') == WORKFLOW and run.get('event') == 'issue_comment' and run.get('run_attempt') == 1
         and run.get('status') == 'completed' and run.get('conclusion') == 'success'
         and run.get('actor', {}).get('login') == run.get('triggering_actor', {}).get('login') == 'pyatkoff', 'rollback_install_run')
    jobs = [j for j in pages('/actions/runs/' + original_run + '/attempts/1/jobs', 'jobs') if j.get('name') == 'public-install']
    need(len(jobs) == 1 and jobs[0].get('run_id') == int(original_run) and jobs[0].get('status') == 'completed'
         and jobs[0].get('conclusion') == 'success', 'rollback_install_job')
    steps = [s for s in jobs[0].get('steps', []) if s.get('name', '').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps) == 1 and steps[0].get('status') == 'completed' and steps[0].get('conclusion') == 'success', 'rollback_install_step')
    name = 'search3-public-install-' + original_run + '-1'
    artifacts = [a for a in pages('/actions/runs/' + original_run + '/artifacts', 'artifacts') if a.get('name') == name]
    need(len(artifacts) == 1 and artifacts[0].get('expired') is False
         and artifacts[0].get('workflow_run', {}).get('id') == int(original_run)
         and re.fullmatch(r'sha256:[a-f0-9]{64}', artifacts[0].get('digest', '')), 'rollback_install_artifact')
    rollback_main(identity)
    return original, artifacts[0]


def rollback_source_evidence(raw, original, policy):
    with zip_files(raw) as archive:
        need(archive.namelist() == ['evidence.json'], 'rollback_install_artifact')
        value = json.loads(archive.read('evidence.json'))
    expected = {**original, 'operation_id': OPERATION, 'read_only': False, 'phase': 'complete',
                'ssh_started': True, 'status': 'installed_verified'}
    need(isinstance(value, dict) and set(value) == set(expected) | {'remote'}
         and all(type(value.get(k)) is type(v) and value[k] == v for k, v in expected.items()), 'rollback_install_evidence')
    need(valid_result(original, policy, value['remote']), 'rollback_install_evidence')
    return value['remote']


def download(artifact, expected):
    # Existing GitHub artifact transport: authenticated API, unauthenticated HTTPS redirect.
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *args, **kwargs):
            return None
    opener = urllib.request.build_opener(NoRedirect())
    request = urllib.request.Request(f'https://api.github.com/repos/{REPO}/actions/artifacts/{artifact}/zip', headers={'Authorization': 'Bearer ' + os.environ['GH_TOKEN'], 'Accept': 'application/vnd.github+json'})
    try:
        response = opener.open(request, timeout=30)
    except urllib.error.HTTPError as error:
        need(error.code == 302, 'artifact_redirect')
        location = error.headers.get('Location', ''); url = urllib.parse.urlsplit(location)
        need(url.scheme == 'https' and url.hostname and url.port in (None, 443) and not url.username and not url.password and not url.fragment
             and (url.hostname.endswith('.blob.core.windows.net') or url.hostname.endswith('.actions.githubusercontent.com')), 'artifact_redirect')
        response = opener.open(urllib.request.Request(location), timeout=30)
    with response:
        raw = response.read(MAX_ZIP + 1)
    need(len(raw) <= MAX_ZIP and sha(raw) == expected, 'artifact_digest')
    return raw


def zip_files(raw):
    archive = zipfile.ZipFile(io.BytesIO(raw)); entries = archive.infolist()
    need(0 < len(entries) <= 2000 and sum(x.file_size for x in entries) <= 32 * 1024 * 1024, 'archive_limit')
    names = set()
    for entry in entries:
        name = entry.filename
        need(name and not name.startswith('/') and '\\' not in name and all(p not in ('', '.', '..') for p in name.rstrip('/').split('/'))
             and name not in names and not entry.flag_bits & 1 and not stat.S_ISLNK(entry.external_attr >> 16), 'archive_entry')
        names.add(name)
    return archive


def signing_evidence(raw):
    with zip_files(raw) as archive:
        need(archive.namelist() == ['result.json'], 'signing_artifact')
        data = archive.read('result.json'); need(sha(data) == SIGNING_RESULT_SHA, 'signing_digest')
    value = json.loads(data)
    need(value.get('operation_id') == 'int-anex-search3-signing-readback-20261120-v1'
         and value.get('source_sha') == '3207266e17ed4e8244aaf28d05b962747fd4cd3c'
         and value.get('mode') == 'search3-signing-readback' and value.get('status') == 'complete', 'signing_identity')
    expected = {'ok': True, 'state': 'ready', 'created': False, 'pointer_configured': False, 'key_id': 'search3-anytour-v1', 'producer': 'search3', 'project': 'anytour',
                'public_key': 'vreerv0j3wsOQqcz40lcYby3VhEX+lirikoeNETdx7c=', 'not_before': 1792792045, 'not_after': 1800568105}
    need(value.get('signing') == expected and expected['not_before'] <= int(time.time()) < expected['not_after'], 'signing_material')
    need(value.get('private_key_exported') is False and value.get('public_ui_entrypoints_unchanged') is True
         and value.get('production_before') == value.get('production_after') and bool(value.get('production_before'))
         and all(type(value.get(k)) is int and value[k] == 0 for k in ('supplier_calls', 'database_writes', 'booking_calls', 'lead_calls')), 'signing_boundary')
    run = api('/actions/runs/' + str(SIGNING_RUN))
    need(run.get('status') == 'completed' and run.get('conclusion') == 'success' and run.get('run_attempt') == 1
         and run.get('path') == '.github/workflows/int-server-executor.yml', 'signing_run')
    meta = api('/actions/artifacts/' + str(SIGNING_ARTIFACT))
    need(meta.get('workflow_run', {}).get('id') == SIGNING_RUN and meta.get('digest') == 'sha256:' + SIGNING_ARTIFACT_SHA and meta.get('expired') is False, 'signing_artifact')


def package(raw, inspect=False):
    _, operation = fixed_command(inspect)
    with zip_files(raw) as archive:
        manifest_raw = archive.read('manifest.json'); need(sha(manifest_raw) == MANIFEST_SHA, 'manifest_digest')
        manifest = json.loads(manifest_raw)
        need(manifest.get('schema_version') == 1 and manifest.get('purpose') == 'local_public_live_install_candidate'
             and manifest.get('deployable') is False and manifest.get('profile') == {'schemaVersion': 1, 'profile': 'live', 'scenario': 'family'}
             and manifest.get('candidate') == {'sha': SOURCE, 'tree_sha': TREE} and manifest.get('main', {}).get('sha') == BUILD_MAIN, 'manifest_identity')
        added, replaced = manifest['files']['added'], manifest['files']['replaced']
        need(tuple(x['path'] for x in added) == ADDITIONS and len(replaced) == 1 and replaced[0]['path'] == ENTRY
             and replaced[0]['before'] == {k: v for k, v in ENTRY_BEFORE.items() if k != 'state'}, 'manifest_scope')
        rows = added + replaced; files, expected = {}, {}
        need({x for x in archive.namelist() if x.startswith('overlay/') and not x.endswith('/')} == {'overlay/' + x['path'] for x in rows}, 'overlay_scope')
        for item in rows:
            path, record = item['path'], item['served']; data = archive.read('overlay/' + path)
            need(record == {'size': len(data), 'mode': '0644', 'sha256': sha(data)} and 0 < len(data) <= MAX_BYTES, 'overlay_bytes')
            files[path] = base64.b64encode(data).decode(); expected[path] = {'state': 'present', **record}
    policy = {'operation': operation, 'read_only': inspect, 'source': SOURCE, 'tree': TREE, 'release': RELEASE, 'manifest': MANIFEST_SHA,
              'additions': ADDITIONS, 'entry': ENTRY, 'before': ENTRY_BEFORE, 'expected': expected,
              'protected': PROTECTED if inspect else INSTALL_PROTECTED,
              'private_config': PRIVATE_CONFIG, 'opaque_settings': OPAQUE_SETTINGS, 'absent_dependencies': ABSENT_DEPENDENCIES,
              'signing_result': SIGNING_RESULT_SHA,
              **({} if inspect else {'opaque_presence': INSTALL_OPAQUE_PRESENCE})}
    return policy, files


REMOTE = r'''
import base64,contextlib,ctypes,errno,hashlib,json,os,stat
def require(ok, code):
 if not ok: raise RuntimeError(code)
def digest(raw): return hashlib.sha256(raw).hexdigest()
def canonical(value): return json.dumps(value,sort_keys=True,separators=(',',':')).encode()
def inode(s): return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink,s.st_size,s.st_mtime_ns,s.st_uid,s.st_gid)
def stamp(s): return inode(s)+(s.st_ctime_ns,)
def directory(s): return (s.st_dev,s.st_ino,s.st_mode,s.st_uid,s.st_gid)
def components(path):
 parts=path.split('/')
 require(parts and all(p and p not in ('.','..') and '\x00' not in p for p in parts),'fixed_path')
 return parts
def sync(fd):
 handle=os.open('.',os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd)
 try: os.fsync(handle)
 finally: os.close(handle)
class Files:
 def __init__(self, absolute, allowed_dirs=(), directory_mode=0o755):
  require(absolute.startswith('/') and absolute!='/' and os.path.normpath(absolute)==absolute,'fixed_root')
  self.links=[];self.created=[];self.allowed=set(allowed_dirs);self.directory_mode=directory_mode
  fd=os.open('/',os.O_PATH|os.O_DIRECTORY|os.O_NOFOLLOW)
  self.links.append((None,None,fd,directory(os.fstat(fd))))
  for name in components(absolute[1:]):
   before=os.stat(name,dir_fd=fd,follow_symlinks=False)
   require(stat.S_ISDIR(before.st_mode),'unsafe_parent')
   child=os.open(name,os.O_PATH|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd)
   require(directory(before)==directory(os.fstat(child)),'parent_changed')
   self.links.append((fd,name,child,directory(before)));fd=child
  self.fd=fd;self.verify(self.links)
 def verify(self, links):
  for parent,name,fd,expected in links:
   require(directory(os.fstat(fd))==expected,'parent_changed')
   if parent is not None: require(directory(os.stat(name,dir_fd=parent,follow_symlinks=False))==expected,'parent_changed')
 def close(self):
  for _,_,fd,_ in reversed(self.links): os.close(fd)
 @contextlib.contextmanager
 def parent(self, relative, create=False):
  parts=components(relative);fd=self.fd;links=[];prefix=[];missing=False
  try:
   self.verify(self.links)
   for name in parts[:-1]:
    prefix.append(name);path='/'.join(prefix)
    try: before=os.stat(name,dir_fd=fd,follow_symlinks=False)
    except FileNotFoundError:
     if not create: missing=True;break
     require(path in self.allowed,'directory_scope')
     os.mkdir(name,self.directory_mode,dir_fd=fd)
     made=os.open(name,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd)
     try: os.fchmod(made,self.directory_mode)
     finally: os.close(made)
     before=os.stat(name,dir_fd=fd,follow_symlinks=False)
     self.created.append((path,directory(before)));sync(fd)
    require(stat.S_ISDIR(before.st_mode),'unsafe_parent')
    child=os.open(name,os.O_PATH|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd)
    require(directory(before)==directory(os.fstat(child)),'parent_changed')
    links.append((fd,name,child,directory(before)));fd=child
   self.verify(self.links+links)
   yield None if missing else fd,parts[-1]
   self.verify(self.links+links)
  finally:
   for _,_,child,_ in reversed(links): os.close(child)
 def read(self, relative, content=False):
  with self.parent(relative) as (fd,name):
   if fd is None: return {'state':'absent'},None,None
   try: before=os.stat(name,dir_fd=fd,follow_symlinks=False)
   except FileNotFoundError: return {'state':'absent'},None,None
   require(stat.S_ISREG(before.st_mode) and before.st_nlink==1 and 0<=before.st_size<=MAX_BYTES,'unsafe_file')
   leaf=os.open(name,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=fd)
   try:
    require(stamp(os.fstat(leaf))==stamp(before),'file_changed')
    value=hashlib.sha256();size=0;chunks=[]
    while True:
     chunk=os.read(leaf,min(65536,MAX_BYTES-size+1))
     if not chunk: break
     size+=len(chunk);require(size<=MAX_BYTES,'file_limit');value.update(chunk)
     if content: chunks.append(chunk)
    require(size==before.st_size and stamp(os.fstat(leaf))==stamp(before),'file_changed')
   finally: os.close(leaf)
   require(stamp(os.stat(name,dir_fd=fd,follow_symlinks=False))==stamp(before),'file_changed')
   return {'state':'present','sha256':value.hexdigest(),'size':size,'mode':format(stat.S_IMODE(before.st_mode),'04o')},stamp(before),b''.join(chunks) if content else None
 def inventory(self, paths):
  return {path:self.read(path)[:2] for path in paths}
 def create(self, relative, data, mode=0o600):
  with self.parent(relative,True) as (fd,name):
   leaf=os.open(name,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,mode,dir_fd=fd)
   try:
    os.fchmod(leaf,mode)
    with os.fdopen(leaf,'wb',closefd=False) as stream: stream.write(data);stream.flush();os.fsync(leaf)
   finally: os.close(leaf)
   sync(fd)
 def remove_dirs(self,private):
  for index,(path,expected) in enumerate(reversed(self.created)):
   capture='rollback/directory-'+str(index);moved=False
   try:
    with self.parent(path) as (src,name),private.parent(capture,True) as (dst,target):
     require(src is not None,'rollback_directory_conflict')
     move_noreplace(src,name,dst,target);moved=True;sync(src);sync(dst)
     require(directory(os.stat(target,dir_fd=dst,follow_symlinks=False))==expected,'rollback_directory_conflict')
     # Only this captured own directory may be removed. ENOTEMPTY preserves it;
     # never enumerate or recursively remove anything another writer added.
     os.rmdir(target,dir_fd=dst);moved=False;sync(dst)
   except Exception:
    if moved: restore_capture(self,private,path,capture)
    raise
def rename_fixed(left_fd,left,right_fd,right,flag):
 libc=ctypes.CDLL(None,use_errno=True);fn=getattr(libc,'renameat2',None)
 require(fn is not None,'unsupported_platform')
 fn.argtypes=[ctypes.c_int,ctypes.c_char_p,ctypes.c_int,ctypes.c_char_p,ctypes.c_uint];fn.restype=ctypes.c_int
 if fn(left_fd,left.encode(),right_fd,right.encode(),flag)!=0: raise OSError(ctypes.get_errno(),'rename_failed')
def exchange(left_fd,left,right_fd,right): rename_fixed(left_fd,left,right_fd,right,2)
def move_noreplace(left_fd,left,right_fd,right): rename_fixed(left_fd,left,right_fd,right,1)
def restore_capture(public,private,relative,capture):
 # No overwrite even when another writer appeared after the capture.
 with private.parent(capture) as (src,name),public.parent(relative) as (dst,target):
  move_noreplace(src,name,dst,target);sync(src);sync(dst)
def capture_owned(public,private,relative,capture,expected,identity):
 moved=False
 try:
  with public.parent(relative) as (src,name),private.parent(capture,True) as (dst,target):
   move_noreplace(src,name,dst,target);moved=True;sync(src);sync(dst)
  observed=private.read(capture)
  # Rename changes ctime. The inode, mode, links, owner, size, mtime and bytes
  # must still identify exactly what this operation installed.
  require(observed[0]==expected and observed[1][:-1]==identity[:-1],'rollback_entry_conflict')
 except Exception:
  # Restore an unexpected object even if it cannot be read (e.g. symlink or
  # oversized foreign file). A newer public object always wins NOREPLACE.
  if moved: restore_capture(public,private,relative,capture)
  raise

def checked_protected_paths():
 paths=tuple(POLICY['protected'])+tuple(POLICY['opaque_settings'])+tuple(POLICY['absent_dependencies'])
 require(len(set(paths))==len(paths) and not set(paths)&set(POLICY['expected']),'payload_scope')
 return paths
def dependency_matches(path,row):
 private_modes=('0400','0440','0444','0600','0640','0644')
 if path in POLICY['protected']:
  return row.get('state')=='present' and row.get('sha256')==POLICY['protected'][path] and row.get('mode') in (private_modes if path==POLICY['private_config'] else ('0644',))
 if path in POLICY['opaque_settings']:
  presence=POLICY.get('opaque_presence',{})
  if path in presence and (row.get('state')=='present') is not presence[path]: return False
  return row=={'state':'absent'} or row.get('mode') in private_modes
 return path in POLICY['absent_dependencies'] and row=={'state':'absent'}
def inspect_only():
 result={'schema_version':1,'operation_id':POLICY['operation'],'source_sha':POLICY['source'],'manifest_sha256':POLICY['manifest'],**IDENTITY,
         'status':'preflight_blocked','read_only':True,'server_writes':0,'supplier_calls':0,'database_reads':0,'database_writes':0,
         'lead_calls':0,'booking_calls':0,'application_calls':0,'observed':{},'private_checks':{},'mismatches':[]}
 public=None
 try:
  require(POLICY['operation']=='public-search3-readiness-20261120-v1' and len(POLICY['expected'])==42 and len(POLICY['additions'])==41
          and set(PAYLOAD)==set(POLICY['expected'])==set(POLICY['additions'])|{POLICY['entry']},'payload_scope')
  protected_paths=checked_protected_paths();paths=tuple(POLICY['expected'])+protected_paths
  private_labels={POLICY['private_config']:'anex_private_config','config.php':'public_config','site_conf.php':'site_config',
                  '_preview/search3-local-candidate/config.php':'local_config','_preview/search3-next-candidate/config.php':'next_config'}
  require(tuple(POLICY['opaque_settings'])==('config.php','site_conf.php'),'payload_scope')
  public=Files(os.environ.get('HOME','')+'/www/anytoour.ru')
  def inventory():
   rows={}
   for path in paths:
    try: rows[path]=public.read(path)[:2]
    except Exception as error:
     reason=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
     rows[path]=({'state':'unreadable','reason':reason},None)
   return rows
  first,second=inventory(),inventory()
  require(first==second,'preflight_race')
  for path,(row,_) in first.items():
   match=(row==POLICY['before'] if path==POLICY['entry'] else row=={'state':'absent'}) if path in POLICY['expected'] else dependency_matches(path,row)
   if path in private_labels:
    result['private_checks'][private_labels[path]]={'presence_known':row.get('state') in ('present','absent'),
        'present':row.get('state')=='present','matches_expected':match,'unchanged':True}
   else: result['observed'][path]=row
   if not match:
    reason=row.get('reason','predecessor_drift' if path in POLICY['expected'] else 'dependency_drift')
    result['mismatches'].append({'path':private_labels.get(path,path),'reason':reason})
  result['status']='preflight_mismatch' if result['mismatches'] else 'preflight_match'
 except Exception as error:
  result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
 finally:
  if public is not None: public.close()
 print(json.dumps(result,sort_keys=True))

# This branch exits the interpreter before the mutation transaction is reached.
# It never constructs Files for private storage and has no create/mkdir call.
if POLICY['read_only']:
 inspect_only()
 raise SystemExit(0)

result={'schema_version':1,'operation_id':POLICY['operation'],'source_sha':POLICY['source'],
        'manifest_sha256':POLICY['manifest'],**IDENTITY,'status':'blocked_no_replay','phase':'preflight',
        'supplier_calls':0,'database_reads':0,'database_writes':0,'lead_calls':0,'booking_calls':0,
        'application_calls':0,'written_files':[],'rollback_files':[],'snapshot_verified':False,
        'restore_drill_verified':False,'protected_unchanged':False,'snapshot_manifest_sha256':None}
public=private=None;owned={};staged_ids={};written=[];switched=False;reserved=False;entry_conflict=False;exchanged_old=None
try:
 require(hasattr(os,'O_PATH') and hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY'),'unsupported_platform')
 require(hasattr(ctypes.CDLL(None),'renameat2'),'unsupported_platform')
 require(set(PAYLOAD)==set(POLICY['expected'])==set(POLICY['additions'])|{POLICY['entry']}
         and len(POLICY['additions'])==41 and len(POLICY['expected'])==42,'payload_scope')
 data={p:base64.b64decode(v,validate=True) for p,v in PAYLOAD.items()}
 require(all(POLICY['expected'][p]=={'state':'present','sha256':digest(v),'size':len(v),'mode':'0644'}
             and 0<len(v)<=MAX_BYTES for p,v in data.items()),'payload_bytes')
 home=os.environ.get('HOME','');root=home+'/www/anytoour.ru'
 allowed=sorted({'/'.join(components(p)[:i]) for p in POLICY['expected'] for i in range(1,len(components(p)))})
 public=Files(root,allowed)
 protected_paths=checked_protected_paths()
 before=public.inventory(POLICY['expected']);protected=public.inventory(protected_paths)
 require(before[POLICY['entry']][0]==POLICY['before']
         and all(before[p][0]=={'state':'absent'} for p in POLICY['additions']),'predecessor_drift')
 require(all(dependency_matches(p,row[0]) for p,row in protected.items()),'dependency_drift')
 require(public.inventory(POLICY['expected'])==before and public.inventory(protected_paths)==protected,'preflight_race')
 # Private state is reserved once, after dependencies pass, before any served mkdir/write.
 home_files=Files(home)
 try:
  with home_files.parent('.anytoour-search3-root-gateway') as (fd,name):
   try: os.mkdir(name,0o700,dir_fd=fd);sync(fd)
   except FileExistsError: pass
   s=os.stat(name,dir_fd=fd,follow_symlinks=False)
   require(stat.S_ISDIR(s.st_mode) and stat.S_IMODE(s.st_mode)==0o700 and s.st_uid==os.getuid(),'private_root')
 finally: home_files.close()
 base=Files(home+'/.anytoour-search3-root-gateway')
 try:
  require(os.fstat(base.fd).st_dev==os.fstat(public.fd).st_dev,'different_filesystem')
  try: os.mkdir(POLICY['operation'],0o700,dir_fd=base.fd)
  except FileExistsError: raise RuntimeError('operation_exists_no_replay') from None
  reserved=True;sync(base.fd)
 finally: base.close()
 private=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation'],('stage','snapshot','drill','rollback'),0o700)
 private.create('reservation.json',canonical({'operation':POLICY['operation'],**IDENTITY,'manifest':POLICY['manifest']}))
 old_row,old_stamp,old=public.read(POLICY['entry'],True)
 require((old_row,old_stamp)==before[POLICY['entry']],'predecessor_drift')
 private.create('snapshot/entry',old,0o644)
 snapshot={'before':{p:r[0] for p,r in before.items()},'protected':{p:r[0] for p,r in protected.items()},'source':POLICY['source'],'manifest':POLICY['manifest']}
 private.create('snapshot/manifest.json',canonical(snapshot))
 require(private.read('snapshot/entry')[0]==POLICY['before'],'snapshot_invalid')
 result['snapshot_manifest_sha256']=digest(canonical(snapshot));result['snapshot_verified']=True
 # Empty isolated restore: no helper ever restores directly over production.
 private.create('drill/entry',private.read('snapshot/entry',True)[2],0o644)
 require(private.read('drill/entry')[0]==POLICY['before']
         and all(public.read(p)[0]=={'state':'absent'} for p in POLICY['additions']),'restore_drill_failed')
 result['restore_drill_verified']=True
 for index,p in enumerate(POLICY['expected']): private.create('stage/'+str(index),data[p],0o644)
 stages={p:'stage/'+str(i) for i,p in enumerate(POLICY['expected'])}
 require(public.inventory(POLICY['expected'])==before and public.inventory(protected_paths)==protected,'prewrite_race')
 result['phase']='additions'
 for p in POLICY['additions']:
  require(public.read(p)[0]=={'state':'absent'},'addition_conflict')
  stage=stages[p];stage_identity=private.read(stage)[1];staged_ids[p]=stage_identity
  private.create('intent-'+str(len(written))+'.json',canonical({'path':p,'identity':stage_identity,'sha256':POLICY['expected'][p]['sha256']}))
  with private.parent(stage) as (src,name),public.parent(p,True) as (dst,target):
   # Atomic no-overwrite; never replace a newly appearing foreign file.
   os.link(name,target,src_dir_fd=src,dst_dir_fd=dst,follow_symlinks=False);written.append(p)
   os.unlink(name,dir_fd=src);sync(src);sync(dst)
  row,identity,_=public.read(p)
  require(row==POLICY['expected'][p] and identity[:2]==stage_identity[:2],'addition_readback')
  owned[p]=identity
  result['written_files']=list(written)
 require(public.inventory(protected_paths)==protected and public.read(POLICY['entry'])[:2]==before[POLICY['entry']],'entry_prewrite_race')
 require(all(public.read(p)[:2]==(POLICY['expected'][p],owned[p]) for p in written),'addition_race')
 result['phase']='entry'
 stage=stages[POLICY['entry']];stage_identity=private.read(stage)[1];staged_ids[POLICY['entry']]=stage_identity
 private.create('entry-intent.json',canonical({'before':before[POLICY['entry']],'new_identity':stage_identity}))
 with private.parent(stage) as (src,name),public.parent(POLICY['entry']) as (dst,target):
  require(public.read(POLICY['entry'])[:2]==before[POLICY['entry']],'entry_prewrite_race')
  exchange(src,name,dst,target);switched=True;sync(src);sync(dst)
 row,identity,_=public.read(POLICY['entry']);owned[POLICY['entry']]=identity
 require(row==POLICY['expected'][POLICY['entry']] and identity[:2]==stage_identity[:2],'entry_readback')
 # Exchange keeps a racing preimage intact. Refuse to call it our approved backup.
 try:
  actual_old=private.read(stage);exchanged_old=actual_old[:2]
  require(actual_old[0]==POLICY['before'] and actual_old[1][:-1]==before[POLICY['entry']][1][:-1],'entry_exchange_conflict')
 except Exception:
  entry_conflict=True
  capture_owned(public,private,POLICY['entry'],'rollback/conflicted-entry',row,identity)
  restore_capture(public,private,POLICY['entry'],stage);switched=False
  raise RuntimeError('entry_exchange_conflict')
 written.append(POLICY['entry']);result['written_files']=list(written)
 require(all(public.read(p)[:2]==(POLICY['expected'][p],owned[p]) for p in written),'installed_race')
 require(public.inventory(protected_paths)==protected,'protected_changed')
 result.update(status='installed_verified',phase='complete',protected_unchanged=True)
except Exception as error:
 result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
 result['written_files']=list(written)
 if reserved:
  result['status']='unknown_no_replay'
  try:
   require(not entry_conflict,'rollback_entry_conflict')
   if switched:
    entry=POLICY['entry'];stage=stages[entry]
    if entry not in owned:
     observed=public.read(entry)
     require(observed[0]==POLICY['expected'][entry] and observed[1][:2]==staged_ids[entry][:2],'rollback_entry_conflict')
     owned[entry]=observed[1]
    require(public.read(entry)[:2]==(POLICY['expected'][entry],owned[entry]),'rollback_entry_conflict')
    prior=private.read(stage)
    require(prior[0]==POLICY['before'] and prior[1][:-1]==before[entry][1][:-1]
            and (exchanged_old is None or prior[:2]==exchanged_old),'rollback_snapshot_conflict')
    capture_owned(public,private,entry,'rollback/entry',POLICY['expected'][entry],owned[entry])
    restore_capture(public,private,entry,stage)
    require(public.read(entry)[0]==POLICY['before'],'rollback_entry_readback')
    result['rollback_files'].append(entry)
   for p in reversed([p for p in written if p!=POLICY['entry']]):
    if p not in owned:
     observed=public.read(p)
     require(observed[0]==POLICY['expected'][p] and observed[1][:2]==staged_ids[p][:2],'rollback_addition_conflict')
     owned[p]=observed[1]
    require(p in owned and public.read(p)[:2]==(POLICY['expected'][p],owned[p]),'rollback_addition_conflict')
    capture_owned(public,private,p,'rollback/'+str(POLICY['additions'].index(p)),POLICY['expected'][p],owned[p])
    require(public.read(p)[0]=={'state':'absent'},'rollback_addition_conflict')
    result['rollback_files'].append(p)
   public.remove_dirs(private)
   require(public.read(POLICY['entry'])[0]==POLICY['before'] and all(public.read(p)[0]=={'state':'absent'} for p in POLICY['additions'])
           and public.inventory(protected_paths)==protected,'rollback_incomplete')
   result.update(status='rolled_back_no_replay',protected_unchanged=True)
  except Exception: result['status']='unknown_no_replay'
finally:
 if reserved and private is not None:
  try: private.create('result.json',canonical(result))
  except Exception: result.update(status='unknown_no_replay',reason='receipt_failed')
 if private is not None: private.close()
 if public is not None: public.close()
print(json.dumps(result,sort_keys=True))
'''


ROLLBACK_REMOTE = r'''
result={'schema_version':1,'operation_id':ROLLBACK_OPERATION,'source_sha':POLICY['source'],
        'manifest_sha256':POLICY['manifest'],**IDENTITY,'original_install':ORIGINAL,
        'status':'blocked_no_replay','phase':'preflight','supplier_calls':0,'database_reads':0,
        'database_writes':0,'lead_calls':0,'booking_calls':0,'application_calls':0,
        'rollback_files':[],'snapshot_verified':False,'protected_unchanged':False,
        'original_records_unchanged':False,'snapshot_manifest_sha256':ACCEPTED['snapshot_manifest_sha256']}
public=original=base=private=None;reserved=False
try:
 require(hasattr(os,'O_PATH') and hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY')
         and hasattr(ctypes.CDLL(None),'renameat2'),'unsupported_platform')
 require(POLICY['operation']=='public-search3-20261120-v1' and POLICY['read_only'] is False
         and len(POLICY['additions'])==41 and len(POLICY['expected'])==42
         and set(POLICY['expected'])==set(POLICY['additions'])|{POLICY['entry']},'payload_scope')
 home=os.environ.get('HOME','');root=home+'/www/anytoour.ru'
 public=Files(root);base=Files(home+'/.anytoour-search3-root-gateway')
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation'])
 for fd in (base.fd,original.fd):
  s=os.fstat(fd);require(stat.S_IMODE(s.st_mode)==0o700 and s.st_uid==os.getuid(),'private_root')
 s=os.stat('snapshot',dir_fd=original.fd,follow_symlinks=False)
 require(stat.S_ISDIR(s.st_mode) and stat.S_IMODE(s.st_mode)==0o700 and s.st_uid==os.getuid(),'private_root')
 require(os.fstat(base.fd).st_dev==os.fstat(public.fd).st_dev,'different_filesystem')
 record_paths=('reservation.json','result.json','snapshot/manifest.json','snapshot/entry','entry-intent.json')+tuple('intent-'+str(i)+'.json' for i in range(41))
 def records():
  rows={}
  for path in record_paths:
   row,identity,raw=original.read(path,True)
   require(row.get('state')=='present' and row.get('mode')==('0644' if path=='snapshot/entry' else '0600')
           and identity[6]==os.getuid(),'rollback_private_record')
   rows[path]=(row,identity,raw)
  return rows
 retained=records()
 def record(path): return json.loads(retained[path][2])
 require(record('reservation.json')=={'operation':POLICY['operation'],**ORIGINAL,'manifest':POLICY['manifest']},'rollback_original_identity')
 require(record('result.json')==ACCEPTED,'rollback_original_identity')
 snapshot=record('snapshot/manifest.json')
 expected_before={p:POLICY['before'] if p==POLICY['entry'] else {'state':'absent'} for p in POLICY['expected']}
 protected_paths=checked_protected_paths()
 require(isinstance(snapshot,dict) and set(snapshot)=={'before','protected','source','manifest'}
         and snapshot['source']==POLICY['source'] and snapshot['manifest']==POLICY['manifest']
         and snapshot['before']==expected_before and set(snapshot['protected'])==set(protected_paths)
         and digest(retained['snapshot/manifest.json'][2])==ACCEPTED['snapshot_manifest_sha256']
         and retained['snapshot/entry'][0]==POLICY['before'],'rollback_snapshot_conflict')
 candidate=public.inventory(POLICY['expected']);protected=public.inventory(protected_paths)
 require({p:r[0] for p,r in protected.items()}==snapshot['protected']
         and all(dependency_matches(p,r[0]) for p,r in protected.items()),'protected_changed')
 def checked_stamp(value,row,owned=True):
  require(isinstance(value,list) and len(value)==9 and all(type(v) is int for v in value)
          and value[0]>=0 and value[1]>0 and value[2]==(stat.S_IFREG|int(row['mode'],8))
          and value[3]==1 and value[4]==row['size'] and (not owned or value[6]==os.getuid()),'rollback_private_record')
  return tuple(value)
 for index,path in enumerate(POLICY['additions']):
  intent=record('intent-'+str(index)+'.json')
  require(isinstance(intent,dict) and set(intent)=={'path','identity','sha256'}
          and intent['path']==path and intent['sha256']==POLICY['expected'][path]['sha256'],'rollback_private_record')
  identity=checked_stamp(intent['identity'],POLICY['expected'][path])
  require(candidate[path][0]==POLICY['expected'][path] and candidate[path][1][:-1]==identity[:-1],'rollback_candidate_changed')
 intent=record('entry-intent.json');entry=POLICY['entry']
 require(isinstance(intent,dict) and set(intent)=={'before','new_identity'} and isinstance(intent['before'],list)
         and len(intent['before'])==2 and intent['before'][0]==POLICY['before'],'rollback_private_record')
 checked_stamp(intent['before'][1],POLICY['before'],False)
 identity=checked_stamp(intent['new_identity'],POLICY['expected'][entry])
 require(candidate[entry][0]==POLICY['expected'][entry] and candidate[entry][1][:-1]==identity[:-1],'rollback_candidate_changed')
 require(records()==retained and public.inventory(POLICY['expected'])==candidate
         and public.inventory(protected_paths)==protected,'rollback_preflight_race')
 result['snapshot_verified']=True
 # This separate reservation is never reused. The original successful operation
 # and every original private record stay read-only, including its snapshot.
 try: os.mkdir(ROLLBACK_OPERATION,0o700,dir_fd=base.fd)
 except FileExistsError: raise RuntimeError('operation_exists_no_replay') from None
 reserved=True;sync(base.fd)
 private=Files(home+'/.anytoour-search3-root-gateway/'+ROLLBACK_OPERATION,('stage','quarantine'),0o700)
 private.create('reservation.json',canonical({'operation':ROLLBACK_OPERATION,**IDENTITY,'original_install':ORIGINAL,
                                            'manifest':POLICY['manifest'],'snapshot_manifest_sha256':ACCEPTED['snapshot_manifest_sha256']}))
 private.create('stage/entry',retained['snapshot/entry'][2],0o644)
 require(private.read('stage/entry')[0]==POLICY['before'],'rollback_snapshot_conflict')
 require(records()==retained and public.inventory(POLICY['expected'])==candidate
         and public.inventory(protected_paths)==protected,'rollback_preflight_race')
 result['phase']='entry'
 capture_owned(public,private,entry,'quarantine/entry',POLICY['expected'][entry],candidate[entry][1])
 restore_capture(public,private,entry,'stage/entry')
 restored_entry=public.read(entry)[:2]
 require(restored_entry[0]==POLICY['before'],'rollback_entry_readback')
 result['rollback_files'].append(entry);result['phase']='additions'
 for path in reversed(POLICY['additions']):
  require(public.read(entry)[:2]==restored_entry and public.inventory(protected_paths)==protected,'protected_changed')
  require(public.read(path)[:2]==candidate[path],'rollback_addition_conflict')
  capture_owned(public,private,path,'quarantine/'+str(POLICY['additions'].index(path)),POLICY['expected'][path],candidate[path][1])
  require(public.read(path)[0]=={'state':'absent'},'rollback_addition_conflict')
  result['rollback_files'].append(path)
 # Directory ownership was not persisted by the original installer. Leave every
 # directory and unrelated object in place; never enumerate or remove them.
 require(public.read(entry)[:2]==restored_entry
         and all(public.read(p)[0]=={'state':'absent'} for p in POLICY['additions'])
         and public.inventory(protected_paths)==protected and records()==retained,'rollback_incomplete')
 result.update(status='rolled_back_verified',phase='complete',protected_unchanged=True,original_records_unchanged=True)
except Exception as error:
 result['status']='unknown_no_replay' if reserved else 'blocked_no_replay'
 result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
finally:
 if reserved and private is not None:
  try: private.create('result.json',canonical(result))
  except Exception: result.update(status='unknown_no_replay',reason='receipt_failed')
 for files in (private,original,base,public):
  if files is not None: files.close()
print(json.dumps(result,sort_keys=True))
'''


def remote_program(policy, files, identity):
    # Safe bounded reason codes only, no exception text/private paths in the result.
    need(type(policy.get('read_only')) is bool and policy.get('operation') == fixed_command(policy['read_only'])[1], 'mode')
    prefix = 'MAX_BYTES=' + repr(MAX_BYTES) + '\nPOLICY=' + repr(policy) + '\nPAYLOAD=' + repr(files) + '\nIDENTITY=' + repr(identity) + '\n'
    prefix += 'def re_safe(value): return value in ' + repr(REASONS) + '\n'
    program = prefix + REMOTE
    ast.parse(program)
    return program


REASONS = ('fixed_path','fixed_root','unsafe_parent','parent_changed','directory_scope','unsafe_file','file_changed','file_limit',
           'unsupported_platform','payload_scope','payload_bytes','predecessor_drift','dependency_drift','preflight_race','private_root',
           'different_filesystem','operation_exists_no_replay','snapshot_invalid','restore_drill_failed','prewrite_race','addition_conflict',
           'addition_readback','entry_prewrite_race','addition_race','entry_readback','entry_exchange_conflict','installed_race','protected_changed',
           'operation_failed','receipt_failed','rollback_entry_conflict')
ROLLBACK_REASONS = REASONS + ('rollback_private_record','rollback_original_identity','rollback_snapshot_conflict',
                             'rollback_candidate_changed','rollback_preflight_race','rollback_entry_readback',
                             'rollback_addition_conflict','rollback_incomplete')


def rollback_remote_program(policy, identity, original, accepted):
    need(policy.get('operation') == OPERATION and policy.get('read_only') is False, 'mode')
    need(valid_result(original, policy, accepted), 'rollback_install_evidence')
    prefix = ('MAX_BYTES=' + repr(MAX_BYTES) + '\nPOLICY=' + repr(policy) + '\nIDENTITY=' + repr(identity)
              + '\nORIGINAL=' + repr(original) + '\nACCEPTED=' + repr(accepted)
              + '\nROLLBACK_OPERATION=' + repr(ROLLBACK_OPERATION) + '\n')
    prefix += 'def re_safe(value): return value in ' + repr(ROLLBACK_REASONS) + '\n'
    # Share only the stable filesystem definitions, never the inspect/install
    # execution branches. Historical REMOTE bytes remain unchanged.
    program = prefix + REMOTE.split('\ndef inspect_only():', 1)[0] + ROLLBACK_REMOTE
    ast.parse(program)
    return program


def valid_rollback_result(identity, original, accepted, result):
    fixed = {'schema_version': 1, 'operation_id': ROLLBACK_OPERATION, 'source_sha': SOURCE,
             'manifest_sha256': MANIFEST_SHA, **identity, 'original_install': original,
             'snapshot_manifest_sha256': accepted['snapshot_manifest_sha256']}
    counters = ('supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
    required = set(fixed) | set(counters) | {'status','phase','rollback_files','snapshot_verified','protected_unchanged','original_records_unchanged'}
    need(isinstance(result, dict) and required <= set(result) <= required | {'reason'}, 'rollback_receipt_shape')
    need(all(type(result.get(k)) is type(v) and result[k] == v for k, v in fixed.items()), 'rollback_receipt_identity')
    need(all(type(result.get(k)) is int and result[k] == 0 for k in counters), 'rollback_receipt_counters')
    need(result['status'] in ('blocked_no_replay','unknown_no_replay','rolled_back_verified')
         and result['phase'] in ('preflight','entry','additions','complete')
         and ('reason' not in result or result['reason'] in ROLLBACK_REASONS), 'rollback_receipt_status')
    need(isinstance(result['rollback_files'], list) and len(result['rollback_files']) == len(set(result['rollback_files']))
         and set(result['rollback_files']) <= set(ADDITIONS) | {ENTRY}, 'rollback_receipt_paths')
    need(all(type(result[k]) is bool for k in ('snapshot_verified','protected_unchanged','original_records_unchanged')), 'rollback_receipt_flags')
    if result['status'] == 'rolled_back_verified':
        need(result['phase'] == 'complete' and result['rollback_files'] == [ENTRY, *reversed(ADDITIONS)]
             and all(result[k] for k in ('snapshot_verified','protected_unchanged','original_records_unchanged')) and 'reason' not in result,
             'rollback_receipt_incomplete')
    return result['status'] == 'rolled_back_verified'


def valid_result(identity, policy, result):
    fixed = {'schema_version': 1, 'operation_id': OPERATION, 'source_sha': SOURCE, 'manifest_sha256': MANIFEST_SHA, **identity}
    counters = ('supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
    required = set(fixed) | set(counters) | {'status','phase','written_files','rollback_files','snapshot_verified','restore_drill_verified','protected_unchanged','snapshot_manifest_sha256'}
    need(isinstance(result, dict) and required <= set(result) <= required | {'reason'}, 'receipt_shape')
    need(all(type(result.get(k)) is type(v) and result[k] == v for k, v in fixed.items()), 'receipt_identity')
    need(all(type(result.get(k)) is int and result[k] == 0 for k in counters), 'receipt_counters')
    need(result['status'] in ('blocked_no_replay','unknown_no_replay','rolled_back_no_replay','installed_verified') and result['phase'] in ('preflight','additions','entry','complete'), 'receipt_status')
    need('reason' not in result or result['reason'] in REASONS, 'receipt_reason')
    for key in ('written_files','rollback_files'):
        need(isinstance(result[key], list) and len(result[key]) == len(set(result[key])) and set(result[key]) <= set(policy['expected']), 'receipt_paths')
    need(all(type(result[k]) is bool for k in ('snapshot_verified','restore_drill_verified','protected_unchanged')), 'receipt_flags')
    need(result['snapshot_manifest_sha256'] is None or re.fullmatch('[a-f0-9]{64}', result['snapshot_manifest_sha256']), 'receipt_snapshot')
    if result['status'] == 'installed_verified':
        need(result['phase'] == 'complete' and result['written_files'] == list(ADDITIONS) + [ENTRY] and not result['rollback_files']
             and all(result[k] for k in ('snapshot_verified','restore_drill_verified','protected_unchanged')) and result['snapshot_manifest_sha256'] is not None and 'reason' not in result, 'receipt_incomplete')
    return result['status'] == 'installed_verified'


def valid_inspection_result(identity, policy, result):
    fixed = {'schema_version': 1, 'operation_id': INSPECT_OPERATION, 'source_sha': SOURCE,
             'manifest_sha256': MANIFEST_SHA, **identity, 'read_only': True, 'server_writes': 0,
             'supplier_calls': 0, 'database_reads': 0, 'database_writes': 0, 'lead_calls': 0, 'booking_calls': 0, 'application_calls': 0}
    need(all(type(result.get(k)) is type(v) and result[k] == v for k, v in fixed.items()), 'inspection_identity')
    need(set(result) == set(fixed) | {'status', 'observed', 'private_checks', 'mismatches'} | ({'reason'} if 'reason' in result else set()), 'inspection_fields')
    need(result['status'] in ('preflight_match', 'preflight_mismatch', 'preflight_blocked')
         and ('reason' not in result or result['reason'] in REASONS), 'inspection_status')
    private_paths = {policy['private_config'], *policy['opaque_settings'],
                     '_preview/search3-local-candidate/config.php', '_preview/search3-next-candidate/config.php'}
    public_paths = (set(policy['expected']) | set(policy['protected']) | set(policy['absent_dependencies'])) - private_paths
    private_labels = {'anex_private_config', 'public_config', 'site_config', 'local_config', 'next_config'}
    observed, checks, mismatches = result['observed'], result['private_checks'], result['mismatches']
    need(isinstance(observed, dict) and set(observed) <= public_paths and isinstance(checks, dict) and set(checks) <= private_labels, 'inspection_paths')
    for row in observed.values():
        need(isinstance(row, dict), 'inspection_record')
        if row.get('state') == 'present':
            need(set(row) == {'state','sha256','size','mode'} and re.fullmatch('[a-f0-9]{64}', row.get('sha256', ''))
                 and type(row.get('size')) is int and 0 <= row['size'] <= MAX_BYTES and re.fullmatch('[0-7]{4}', row.get('mode', '')), 'inspection_record')
        else:
            need(row == {'state':'absent'} or set(row) == {'state','reason'} and row.get('state') == 'unreadable' and row.get('reason') in REASONS, 'inspection_record')
    for row in checks.values():
        need(isinstance(row, dict) and set(row) == {'presence_known','present','matches_expected','unchanged'}
             and all(type(v) is bool for v in row.values()) and (row['presence_known'] or not row['present']), 'inspection_private_fields')
    need(isinstance(mismatches, list) and all(isinstance(row, dict) and set(row) == {'path','reason'}
         and row['path'] in public_paths | private_labels and row['reason'] in REASONS for row in mismatches)
         and len({row['path'] for row in mismatches}) == len(mismatches), 'inspection_mismatches')
    if result['status'] != 'preflight_blocked':
        need(set(observed) == public_paths and set(checks) == private_labels and 'reason' not in result
             and all(row['unchanged'] for row in checks.values())
             and bool(mismatches) == (result['status'] == 'preflight_mismatch'), 'inspection_incomplete')
        if result['status'] == 'preflight_match': need(all(row['matches_expected'] for row in checks.values()), 'inspection_incomplete')
    return result['status'] in ('preflight_match', 'preflight_mismatch')


def preflight_evidence(raw, policy):
    """Admit only the reviewed historical mismatch; the server rechecks freshness."""
    need(policy['read_only'] is False and policy['operation'] == OPERATION
         and policy['protected'] == INSTALL_PROTECTED, 'preflight_policy')
    need(sha(raw) == PREFLIGHT_ARTIFACT_SHA, 'preflight_artifact_digest')
    with zip_files(raw) as archive:
        need(archive.namelist() == ['evidence.json'], 'preflight_artifact')
        data = archive.read('evidence.json'); need(sha(data) == PREFLIGHT_RESULT_SHA, 'preflight_digest')
    value = json.loads(data)
    identity = {'control_source_sha': PREFLIGHT_CONTROL, 'run_id': str(PREFLIGHT_RUN), 'command_comment_id': PREFLIGHT_COMMAND}
    outer = {**identity, 'operation_id': INSPECT_OPERATION, 'phase': 'complete', 'read_only': True,
             'server_writes': 0, 'ssh_started': True, 'status': 'preflight_mismatch'}
    need(isinstance(value, dict) and set(value) == set(outer) | {'remote'}
         and all(type(value.get(k)) is type(v) and value[k] == v for k, v in outer.items()), 'preflight_identity')
    original_policy = policy | {'read_only': True, 'operation': INSPECT_OPERATION, 'protected': PROTECTED}
    result = value['remote']; need(isinstance(result, dict), 'preflight_result')
    valid_inspection_result(identity, original_policy, result)
    need(result['status'] == 'preflight_mismatch'
         and result['mismatches'] == [{'path': path, 'reason': 'dependency_drift'} for path in INSTALL_REVIEWED], 'preflight_deviations')
    for path, row in result['observed'].items():
        if path == ENTRY:
            match = row == ENTRY_BEFORE
        elif path in ADDITIONS or path in ABSENT_DEPENDENCIES:
            match = row == {'state': 'absent'}
        else:
            match = (row.get('state') == 'present' and row.get('sha256') == INSTALL_PROTECTED.get(path)
                     and row.get('mode') == '0644')
            if path in INSTALL_REVIEWED: match = match and row.get('size') == INSTALL_REVIEWED[path]['size']
        need(match, 'preflight_observed')
    presence = {'anex_private_config': True, 'public_config': True, 'site_config': False, 'local_config': False, 'next_config': False}
    need(result['private_checks'] == {label: {'presence_known': True, 'present': present, 'matches_expected': True, 'unchanged': True}
                                      for label, present in presence.items()}, 'preflight_private')
    run = api('/actions/runs/' + str(PREFLIGHT_RUN))
    need(run.get('id') == PREFLIGHT_RUN and run.get('head_sha') == PREFLIGHT_CONTROL and run.get('head_branch') == 'main'
         and run.get('event') == 'issue_comment' and run.get('path') == WORKFLOW and run.get('run_attempt') == 1
         and run.get('status') == 'completed' and run.get('conclusion') == 'success'
         and run.get('actor', {}).get('login') == run.get('triggering_actor', {}).get('login') == 'pyatkoff', 'preflight_run')
    meta = api('/actions/artifacts/' + str(PREFLIGHT_ARTIFACT))
    need(meta.get('id') == PREFLIGHT_ARTIFACT and meta.get('name') == PREFLIGHT_ARTIFACT_NAME and meta.get('expired') is False
         and meta.get('workflow_run', {}).get('id') == PREFLIGHT_RUN
         and meta.get('digest') == 'sha256:' + PREFLIGHT_ARTIFACT_SHA, 'preflight_artifact')


def install(inspect=False):
    _, operation = fixed_command(inspect)
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-public-install'; work.mkdir(mode=0o700)
    key, known = work / 'key', work / 'known'
    evidence = {'status': 'preflight_blocked' if inspect else 'blocked_no_replay', 'operation_id': operation,
                'read_only': inspect, 'phase': 'identity', 'ssh_started': False}
    if inspect: evidence['server_writes'] = 0
    complete = False
    try:
        identity = event_identity(json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text()), os.environ, inspect)
        evidence.update(identity); evidence['phase'] = 'admission'; admission(identity, inspect)
        evidence['phase'] = 'artifact'
        signing_evidence(download(SIGNING_ARTIFACT, SIGNING_ARTIFACT_SHA))
        policy, files = package(download(ARTIFACT, ARTIFACT_SHA), inspect)
        if not inspect: preflight_evidence(download(PREFLIGHT_ARTIFACT, PREFLIGHT_ARTIFACT_SHA), policy)
        host, user = os.environ['PREVIEW_HOST'], os.environ['PREVIEW_USER']
        need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]{0,252}', host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}', user), 'ssh_identity')
        raw = os.environ['PREVIEW_KEY'].replace('\r', '')
        need(0 < len(raw) <= 32768, 'ssh_key')
        if 'PRIVATE KEY' not in raw: raw = base64.b64decode(raw, validate=True).decode()
        with key.open('x') as stream: stream.write(raw.rstrip() + '\n')
        key.chmod(0o600)
        scan = carrier.run(['ssh-keyscan','-T','15','-t','ed25519',host]); need(0 < len(scan) <= 8192, 'host_key')
        known.write_bytes(scan); known.chmod(0o600)
        options = ['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','ConnectTimeout=15']
        final_heads(identity)
        evidence.update(status='preflight_unknown' if inspect else 'unknown_no_replay', phase='remote', ssh_started=True)
        response = carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'], input=remote_program(policy,files,identity).encode(), timeout=180)
        need(len(response) <= 32768, 'receipt_limit'); result = json.loads(response)
        complete = (valid_inspection_result if inspect else valid_result)(identity,policy,result); evidence['remote'] = result
        final_heads(identity)
        evidence.update(status=result['status'], phase='complete')
    except Exception as error:
        complete = False
        evidence['error_type'] = type(error).__name__ if type(error) in (RuntimeError,ValueError,KeyError,OSError,subprocess.CalledProcessError,subprocess.TimeoutExpired) else 'InstallError'
    finally:
        key.unlink(missing_ok=True); known.unlink(missing_ok=True)
        (work / 'evidence.json').write_text(json.dumps(evidence,sort_keys=True)+'\n')
    print(json.dumps(evidence,sort_keys=True)); need(complete,'public_inspection_not_verified' if inspect else 'public_install_not_verified')


def command_mode(arguments):
    if arguments == ['rollback']: return 'rollback'
    need(arguments in ([], ['inspect']), 'usage')
    return arguments == ['inspect']


def rollback():
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-public-install'; work.mkdir(mode=0o700)
    key, known = work / 'key', work / 'known'
    evidence = {'status': 'blocked_no_replay', 'operation_id': ROLLBACK_OPERATION,
                'read_only': False, 'phase': 'identity', 'ssh_started': False}
    complete = False
    try:
        identity, original_run = rollback_event_identity(json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text()), os.environ)
        evidence.update(identity); evidence['original_install_run_id'] = original_run; evidence['phase'] = 'admission'
        original, artifact = rollback_admission(identity, original_run)
        evidence['phase'] = 'artifact'
        policy, _ = package(download(ARTIFACT, ARTIFACT_SHA))
        accepted = rollback_source_evidence(download(artifact['id'], artifact['digest'].removeprefix('sha256:')), original, policy)
        host, user = os.environ['PREVIEW_HOST'], os.environ['PREVIEW_USER']
        need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]{0,252}', host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}', user), 'ssh_identity')
        raw = os.environ['PREVIEW_KEY'].replace('\r', '')
        need(0 < len(raw) <= 32768, 'ssh_key')
        if 'PRIVATE KEY' not in raw: raw = base64.b64decode(raw, validate=True).decode()
        with key.open('x') as stream: stream.write(raw.rstrip() + '\n')
        key.chmod(0o600)
        scan = carrier.run(['ssh-keyscan','-T','15','-t','ed25519',host]); need(0 < len(scan) <= 8192, 'host_key')
        known.write_bytes(scan); known.chmod(0o600)
        options = ['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','ConnectTimeout=15']
        rollback_commands(identity, original_run, original)
        rollback_main(identity)
        evidence.update(status='unknown_no_replay', phase='remote', ssh_started=True)
        response = carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'],
                               input=rollback_remote_program(policy,identity,original,accepted).encode(), timeout=180)
        need(len(response) <= 32768, 'receipt_limit'); result = json.loads(response)
        complete = valid_rollback_result(identity,original,accepted,result); evidence['remote'] = result
        rollback_main(identity)
        evidence.update(status=result['status'], phase='complete')
    except Exception as error:
        complete = False
        evidence['error_type'] = type(error).__name__ if type(error) in (RuntimeError,ValueError,KeyError,OSError,subprocess.CalledProcessError,subprocess.TimeoutExpired) else 'RollbackError'
    finally:
        key.unlink(missing_ok=True); known.unlink(missing_ok=True)
        (work / 'evidence.json').write_text(json.dumps(evidence,sort_keys=True)+'\n')
    print(json.dumps(evidence,sort_keys=True)); need(complete,'public_rollback_not_verified')


if __name__ == '__main__':
    import sys
    mode = command_mode(sys.argv[1:])
    rollback() if mode == 'rollback' else install(mode)
