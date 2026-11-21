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
CONTINUATION_PREFIX = '/continue-search3-public'
CONTINUATION_OPERATION = 'public-search3-20261121-after-44840666320-v1'
# This one child can only continue the exact terminal pre-write failure below.
# The consumed install/inspection commands and their private namespaces stay closed.
CONTINUATION_PARENT = {
    'operation_id': OPERATION,
    'control_source_sha': '251585b63dab95988d9f635a2b1f1da6d61a4650',
    'run_id': '44840666320', 'command_comment_id': 7053664048, 'job_id': 136647935738,
    'artifact_id': 14680527168,
    'artifact_sha256': '476cbe8ee8fd566b6abe35cd7d2697700f8888955022749a66e7bb2dd690b358',
    'receipt_sha256': '99ea76313a2c18f50bf449f6f4396f9368f17ffe6245d334123df0b1e2687866',
    'terminal_comment_id': 7053751668,
}
INSPECT_PREFIX = '/inspect-search3-public-install'
INSPECT_OPERATION = 'public-search3-readiness-20261120-v1'
ROLLBACK_PREFIX = '/rollback-search3-public'
ROLLBACK_OPERATION = 'public-search3-rollback-20261120-v1'
SOURCE_PR = 5125
SOURCE = '1a22691517b53e23ea2fa98d02f857b11a118392'
TREE = 'bcfb06414164bf3cad454055fb3acdd2b6454dd1'
RELEASE = '71fd65ae80e2b8c4b00b80f3bafe36dfa9bb2426'  # Reviewed PR5125 release merge; SOURCE tree is identical.
BUILD_MAIN = 'c5513aaee18af701f1253678e79f41e5725b71a2'
# Exact LIVE build proof; admission separately requires every final source check
# and the normal release merge before any server connection.
BUILD_RUN = 44813818406
ARTIFACT = 14667623451
ARTIFACT_SHA = '9cec7ecb27f1582ad181ab1dd3785d2ee1566aef94d3a62e0c212d0f7c166697'
MANIFEST_SHA = 'c89415a6bfeb6dd49be42d1dfc911310330c69cda2dd56737e524a2b1859d390'
# Filled only from the final accepted original metadata, never from caller input.
ARTIFACT_NAME = 'search3-public-live-1a22691517b53e23ea2fa98d02f857b11a118392'
SOURCE_CHECK_NAMES = ('visual-migration','focused-presentation','integration-contracts','browser-matrix','lead-source',
                      'build-preview-artifact','guard')
SIGNING_ARTIFACT = 14651207961
SIGNING_ARTIFACT_SHA = 'd9f756a73a6e6513b5f858426936b510f400eb9ad9a41678aa4f3ef7a553dab9'
SIGNING_RESULT_SHA = '26a2a0c9fffe620746d2c2b9d73b695ec92a3bd5f36b7d551b3ce321855d049d'
SIGNING_RUN = 44781856520
SIGNING_RECEIPT_COMMENT = 7046090497
# This consumed inspection records its original candidate identity. A later
# reviewed candidate with the same predecessor/dependency scope cannot relabel it.
PREFLIGHT_SOURCE = 'f4d14cb7a66be040798693e9a47a2d023c48de7e'
PREFLIGHT_MANIFEST_SHA = '3ef8fadb6db83cff9709fdc06d0dcaf41673456c92b1e77f40cdc8444ae8da5f'
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
PREFLIGHT_ACCEPTED_PROTECTED = {**PROTECTED, **{path: row['sha256'] for path, row in INSTALL_REVIEWED.items()}}
# Accepted NEXT update44830908434 changed this dependency after the consumed
# inspection. Keep its reviewed provenance separate; never relabel the old
# observed rows. Install and rollback still require the exact current bytes.
CURRENT_NEXT_REVIEWED = {
    '_preview/search3-next-candidate/visual-search/index.php': {
        'source_sha': '1a22691517b53e23ea2fa98d02f857b11a118392',
        'release_sha': '71fd65ae80e2b8c4b00b80f3bafe36dfa9bb2426',
        'run_id': 44830908434, 'artifact_id': 14675809225,
        'artifact_sha256': '7d3012a431231236e26beec267abca82b5bd714b9d142c050da2e87f8c1e3cde',
        'receipt_sha256': 'c1fe72833d1bc81cbfc6530a322947a1c20c4eaa607136c141016b65e7ea7065',
        'sha256': 'bfda6c091544c13d157537879adaa7037e3bff3dface2fb5b3a77067808da339', 'size': 860},
}
INSTALL_PROTECTED = {**PREFLIGHT_ACCEPTED_PROTECTED,
                     **{path: row['sha256'] for path, row in CURRENT_NEXT_REVIEWED.items()}}
# Bootstrap44836099209 copied this exact main source to root data/. Its DB helper
# uses the same required root config; NEXT/LOCAL retain their separate fallback
# helpers. Do not relabel the historical inspection or the failed install policy.
CURRENT_ROOT_DB_REVIEWED = {
    'data/db-v1.php': {
        'source_sha': 'f7d1be4d9eb5a1dfa1f3286c130973535e085363', 'run_id': 44836099209,
        'sha256': 'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13', 'size': 3282},
}
CONTINUATION_PROTECTED = {**INSTALL_PROTECTED,
                          **{path: row['sha256'] for path, row in CURRENT_ROOT_DB_REVIEWED.items()}}
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
CONTINUATION_POLICY_SHA = hashlib.sha256(json.dumps({
    'protected': CONTINUATION_PROTECTED, 'opaque_presence': INSTALL_OPAQUE_PRESENCE,
    'absent_dependencies': ABSENT_DEPENDENCIES,
}, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
CONTROL_PATHS = (
    WORKFLOW, 'scripts/deploy/search3_public_install.py', 'tests/search3_public_install_test.py',
    '.github/workflows/deploy-anytoour.yml', 'tests/anytoour_production_deploy_guard_test.py',
    'scripts/deploy/search3_root_gateway_install.py', 'scripts/deploy/search3_root_gateway_inspect.py',
)
CONTROL_CHECKS = ('guard', 'core', 'public-install-contracts', 'production-readiness-contracts', 'contracts',
                  'live-check-contracts', 'tv-diagnostic-contracts', 'context-sender-contracts')
MAX_BYTES = 2 * 1024 * 1024
MAX_ZIP = 16 * 1024 * 1024

# Separate successor-source update. None of the consumed installation pins,
# records or rollback policies above are repurposed by this operation.
UPDATE_PREFIX = '/update-search3-public'
UPDATE_OPERATION = 'public-search3-flight-return-20261121-v1'
UPDATE_ROLLBACK_PREFIX = '/rollback-search3-public-update'
UPDATE_ROLLBACK_OPERATION = 'public-search3-flight-return-rollback-20261121-v1'
UPDATE_SOURCE_PR = 5130
UPDATE_SOURCE = 'f10e51a49ea9d4b3e4b6356f98d6d898484d9fc6'
UPDATE_TREE = '7251ab6712a437ef46a590a3f2b8fb8bd1037b82'
UPDATE_RELEASE = '67c9e34876a1b52e1e7ed919c5b70a2f28a7220b'  # Reviewed PR5130 merge; exact source tree.
UPDATE_BUILD_RUN = 44847945914
UPDATE_ARTIFACT = 14684470494
UPDATE_ARTIFACT_SHA = '4bced061fe5ffc224c196fa400c65ee726d775df4fe4830c9f4b03a19b926a3a'
UPDATE_MANIFEST_SHA = '19a2ecc6db293d4994c70c9203d52e24cc577d9bfa57f90273820c01e955121c'
UPDATE_ARTIFACT_NAME = 'search3-public-live-' + UPDATE_SOURCE
UPDATE_PATH = 'visual-search/app.js'
UPDATE_BEFORE = {'state': 'present', 'sha256': '7a61242a0028cf51f6058efce33c4d3308158cda4e8c81e530a7be71618ddfc9', 'size': 311206, 'mode': '0644'}
UPDATE_AFTER = {'state': 'present', 'sha256': 'c8d6922231350409490e833e429792d1d5a974e02b499e740264d148324d196f', 'size': 311547, 'mode': '0644'}
UPDATE_PARENT = {
    'operation_id': CONTINUATION_OPERATION,
    'control_source_sha': '6335775a2f3f4b628dbc405b5d4edd7a9c43b6ec',
    'run_id': '44843395448', 'command_comment_id': 7054034774,
    'job_id': 136657106645, 'artifact_id': 14680714567,
    'artifact_sha256': 'c9b1c0e962c05d8765de66c96ee655dd3c107c08d5f964e34fc87a722f2024f1',
    'receipt_sha256': 'bcf70929bc32228d6a2e7676af9addde80dfda1b9675413866eef31c9caca3bf',
    'terminal_comment_id': 7054093708,
    'snapshot_manifest_sha256': 'd1f9fa654cc5a67d7e9d632838606c1343701a70810b3c46254be3054d496592',
}
api = carrier.checked_api
need = carrier.need
sha = carrier.sha


def fixed_command(inspect=False, continuation=False):
    need(type(inspect) is bool and type(continuation) is bool and not (inspect and continuation), 'mode')
    if continuation: return CONTINUATION_PREFIX, CONTINUATION_OPERATION
    return (INSPECT_PREFIX, INSPECT_OPERATION) if inspect else (PREFIX, OPERATION)


def event_identity(event, env, inspect=False, continuation=False):
    prefix, operation = fixed_command(inspect, continuation)
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
    pr = api('/pulls/' + str(SOURCE_PR))
    need(pr.get('state') == 'closed' and pr.get('merged') is True and pr.get('merge_commit_sha') == RELEASE and pr.get('head', {}).get('sha') == SOURCE
         and pr.get('head', {}).get('repo', {}).get('full_name') == REPO
         and pr.get('base', {}).get('ref') == 'release/search3-production-ready-v1'
         and pr.get('base', {}).get('repo', {}).get('full_name') == REPO, 'candidate_moved')


def continuation_comments(comments):
    parent = CONTINUATION_PARENT
    commands = [c for c in comments if c.get('body', '').strip().split()[:1] == [PREFIX]
                and OPERATION in c.get('body', '').split()[1:]]
    need(len(commands) == 1 and commands[0]['id'] == parent['command_comment_id']
         and commands[0].get('body') == f'{PREFIX} {parent["control_source_sha"]} {OPERATION}'
         and commands[0].get('user', {}).get('id') == 226193297
         and commands[0].get('author_association') == 'OWNER', 'continuation_parent_command')
    terminal = next((c for c in comments if c['id'] == parent['terminal_comment_id']), {})
    tokens = [str(parent[k]) for k in ('run_id', 'control_source_sha', 'command_comment_id', 'job_id',
                                      'artifact_id', 'artifact_sha256', 'receipt_sha256')]
    need(terminal.get('user', {}).get('id') == 226193297 and terminal.get('author_association') == 'OWNER'
         and all(t in terminal.get('body', '') for t in tokens + ['blocked_no_replay', 'dependency_drift', 'NO_REPLAY']),
         'continuation_parent_terminal')


def admission(identity, inspect=False, continuation=False):
    prefix, operation = fixed_command(inspect, continuation)
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
    if continuation:
        need(identity['run_id'] != CONTINUATION_PARENT['run_id']
             and identity['control_source_sha'] != CONTINUATION_PARENT['control_source_sha'], 'continuation_identity')
        continuation_comments(comments)
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
         and original_run not in (identity['run_id'], CONTINUATION_PARENT['run_id']), 'rollback_install_run')
    comments = pages('/issues/4709/comments')
    matches = [c for c in comments if c.get('body', '').strip().split()[:1] == [ROLLBACK_PREFIX]
               and ROLLBACK_OPERATION in c.get('body', '').split()[1:]]
    need(len(matches) == 1 and matches[0]['id'] == identity['command_comment_id']
         and matches[0].get('body') == f'{ROLLBACK_PREFIX} {identity["control_source_sha"]} {original_run} {ROLLBACK_OPERATION}'
         and matches[0].get('user', {}).get('id') == 226193297 and matches[0].get('author_association') == 'OWNER', 'operation_already_reserved')
    continued = [c for c in comments if c.get('body', '').strip().split()[:1] == [CONTINUATION_PREFIX]
                 and CONTINUATION_OPERATION in c.get('body', '').split()[1:]]
    prefix, operation = (CONTINUATION_PREFIX, CONTINUATION_OPERATION) if continued else (PREFIX, OPERATION)
    installed = continued or [c for c in comments if c.get('body', '').strip().split()[:1] == [PREFIX]
                              and OPERATION in c.get('body', '').split()[1:]]
    need(len(installed) == 1 and installed[0].get('user', {}).get('id') == 226193297
         and installed[0].get('author_association') == 'OWNER', 'rollback_install_command')
    match = re.fullmatch(re.escape(prefix) + r' ([a-f0-9]{40}) ' + re.escape(operation), installed[0].get('body', ''))
    need(match is not None, 'rollback_install_command')
    original = {'control_source_sha': match[1], 'run_id': original_run, 'command_comment_id': installed[0]['id']}
    if continued:
        need(match[1] != CONTINUATION_PARENT['control_source_sha'], 'rollback_install_command')
        original['operation_id'] = CONTINUATION_OPERATION
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
    need(original['run_id'] != CONTINUATION_PARENT['run_id']
         and (original.get('operation_id') == CONTINUATION_OPERATION) == (policy['operation'] == CONTINUATION_OPERATION), 'rollback_install_identity')
    with zip_files(raw) as archive:
        need(archive.namelist() == ['evidence.json'], 'rollback_install_artifact')
        value = json.loads(archive.read('evidence.json'))
    expected = {**original, 'operation_id': policy['operation'], 'read_only': False, 'phase': 'complete',
                'ssh_started': True, 'status': 'installed_verified'}
    if policy['operation'] == CONTINUATION_OPERATION:
        expected.update(parent_failure=CONTINUATION_PARENT, dependency_policy_sha256=CONTINUATION_POLICY_SHA)
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


def continuation_parent_evidence(raw):
    """Only this immutable, complete pre-reservation failure admits the child."""
    parent = CONTINUATION_PARENT
    need(sha(raw) == parent['artifact_sha256'], 'continuation_parent_artifact_digest')
    with zip_files(raw) as archive:
        need(archive.namelist() == ['evidence.json'], 'continuation_parent_artifact')
        data = archive.read('evidence.json')
        need(sha(data) == parent['receipt_sha256'], 'continuation_parent_digest')
    value = json.loads(data)
    identity = {k: parent[k] for k in ('control_source_sha', 'run_id', 'command_comment_id')}
    outer = {**identity, 'operation_id': OPERATION, 'phase': 'complete', 'read_only': False,
             'ssh_started': True, 'status': 'blocked_no_replay'}
    remote = {'schema_version': 1, **identity, 'operation_id': OPERATION, 'source_sha': SOURCE,
              'manifest_sha256': MANIFEST_SHA, 'status': 'blocked_no_replay', 'phase': 'preflight',
              'reason': 'dependency_drift', 'supplier_calls': 0, 'database_reads': 0, 'database_writes': 0,
              'lead_calls': 0, 'booking_calls': 0, 'application_calls': 0, 'written_files': [], 'rollback_files': [],
              'snapshot_verified': False, 'restore_drill_verified': False, 'protected_unchanged': False,
              'snapshot_manifest_sha256': None}
    need(isinstance(value, dict) and set(value) == set(outer) | {'remote'}
         and all(type(value.get(k)) is type(v) and value[k] == v for k, v in outer.items()), 'continuation_parent_identity')
    observed = value['remote']
    need(isinstance(observed, dict) and set(observed) == set(remote)
         and all(type(observed.get(k)) is type(v) and observed[k] == v for k, v in remote.items()), 'continuation_parent_result')
    run = api('/actions/runs/' + parent['run_id'])
    need(run.get('id') == int(parent['run_id']) and run.get('head_sha') == parent['control_source_sha']
         and run.get('head_branch') == 'main' and run.get('event') == 'issue_comment' and run.get('path') == WORKFLOW
         and type(run.get('run_attempt')) is int and run['run_attempt'] == 1
         and run.get('status') == 'completed' and run.get('conclusion') == 'failure'
         and run.get('actor', {}).get('login') == run.get('triggering_actor', {}).get('login') == 'pyatkoff', 'continuation_parent_run')
    jobs = [j for j in pages('/actions/runs/' + parent['run_id'] + '/attempts/1/jobs', 'jobs') if j.get('name') == 'public-install']
    need(len(jobs) == 1 and jobs[0]['id'] == parent['job_id'] and jobs[0].get('run_id') == int(parent['run_id'])
         and jobs[0].get('status') == 'completed' and jobs[0].get('conclusion') == 'failure', 'continuation_parent_job')
    steps = [s for s in jobs[0].get('steps', []) if s.get('name', '').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps) == 1 and steps[0].get('status') == 'completed' and steps[0].get('conclusion') == 'failure', 'continuation_parent_step')
    meta = api('/actions/artifacts/' + str(parent['artifact_id']))
    need(meta.get('id') == parent['artifact_id'] and meta.get('name') == 'search3-public-install-' + parent['run_id'] + '-1'
         and meta.get('expired') is False and meta.get('workflow_run', {}).get('id') == int(parent['run_id'])
         and meta.get('digest') == 'sha256:' + parent['artifact_sha256'], 'continuation_parent_artifact')
    return value


def package(raw, inspect=False, continuation=False):
    _, operation = fixed_command(inspect, continuation)
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
              'protected': PROTECTED if inspect else CONTINUATION_PROTECTED if continuation else INSTALL_PROTECTED,
              'private_config': PRIVATE_CONFIG, 'opaque_settings': OPAQUE_SETTINGS, 'absent_dependencies': ABSENT_DEPENDENCIES,
              'signing_result': SIGNING_RESULT_SHA,
              **({} if inspect else {'opaque_presence': INSTALL_OPAQUE_PRESENCE})}
    if continuation:
        policy.update(parent_failure=CONTINUATION_PARENT, dependency_policy_sha256=CONTINUATION_POLICY_SHA)
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
def private_labels():
 return {POLICY['private_config']:'anex_private_config','config.php':'public_config','site_conf.php':'site_config',
         '_preview/search3-local-candidate/config.php':'local_config','_preview/search3-next-candidate/config.php':'next_config'}
def continuation_inventory(public,paths):
 # The same bounded preflight reads, with public-only diagnostics on failure.
 rows={}
 for path in paths:
  try: rows[path]=public.read(path)[:2]
  except Exception as error:
   reason=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
   rows[path]=({'state':'unreadable','reason':reason},None)
 return rows
def continuation_mismatches(before,protected):
 labels=private_labels()
 for path,(row,_) in {**before,**protected}.items():
  match=(row==POLICY['before'] if path==POLICY['entry'] else row=={'state':'absent'}) if path in before else dependency_matches(path,row)
  if path in labels: result['private_checks'][labels[path]]=match
  elif not match:
   result['public_mismatches'].append({'path':path,'reason':row.get('reason','predecessor_drift' if path in before else 'dependency_drift'),'observed':row})
def require_parent_namespace_absent(home):
 # Never open/create a record inside the consumed parent's namespace. An existing
 # object of any kind, symlink, inaccessible parent, or racing parent fails closed.
 result['parent_namespace_absent']=False
 home_files=Files(home)
 try:
  with home_files.parent('.anytoour-search3-root-gateway/'+POLICY['parent_failure']['operation_id']) as (fd,name):
   if fd is not None:
    try: os.stat(name,dir_fd=fd,follow_symlinks=False)
    except FileNotFoundError: pass
    else: raise RuntimeError('parent_operation_exists')
 finally: home_files.close()
 result['parent_namespace_absent']=True
def inspect_only():
 result={'schema_version':1,'operation_id':POLICY['operation'],'source_sha':POLICY['source'],'manifest_sha256':POLICY['manifest'],**IDENTITY,
         'status':'preflight_blocked','read_only':True,'server_writes':0,'supplier_calls':0,'database_reads':0,'database_writes':0,
         'lead_calls':0,'booking_calls':0,'application_calls':0,'observed':{},'private_checks':{},'mismatches':[]}
 public=None
 try:
  require(POLICY['operation']=='public-search3-readiness-20261120-v1' and len(POLICY['expected'])==42 and len(POLICY['additions'])==41
          and set(PAYLOAD)==set(POLICY['expected'])==set(POLICY['additions'])|{POLICY['entry']},'payload_scope')
  protected_paths=checked_protected_paths();paths=tuple(POLICY['expected'])+protected_paths
  labels=private_labels()
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
   if path in labels:
    result['private_checks'][labels[path]]={'presence_known':row.get('state') in ('present','absent'),
        'present':row.get('state')=='present','matches_expected':match,'unchanged':True}
   else: result['observed'][path]=row
   if not match:
    reason=row.get('reason','predecessor_drift' if path in POLICY['expected'] else 'dependency_drift')
    result['mismatches'].append({'path':labels.get(path,path),'reason':reason})
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
continuation=POLICY['operation']=='public-search3-20261121-after-44840666320-v1'
if continuation:
 result.update(parent_failure=POLICY['parent_failure'],dependency_policy_sha256=POLICY['dependency_policy_sha256'],
               parent_namespace_absent=False,public_mismatches=[],private_checks={})
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
 if continuation:
  require_parent_namespace_absent(home)
  before=continuation_inventory(public,POLICY['expected']);protected=continuation_inventory(public,protected_paths)
  continuation_mismatches(before,protected)
 else:
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
  if continuation:
   require(public.inventory(POLICY['expected'])==before and public.inventory(protected_paths)==protected,'preflight_race')
   require_parent_namespace_absent(home)
   base.verify(base.links)
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
if POLICY['operation']=='public-search3-20261121-after-44840666320-v1':
 result.update(parent_failure=POLICY['parent_failure'],dependency_policy_sha256=POLICY['dependency_policy_sha256'])
public=original=base=private=None;reserved=False
try:
 require(hasattr(os,'O_PATH') and hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY')
         and hasattr(ctypes.CDLL(None),'renameat2'),'unsupported_platform')
 require(POLICY['operation'] in ('public-search3-20261120-v1','public-search3-20261121-after-44840666320-v1') and POLICY['read_only'] is False
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
 require(record('reservation.json')=={'operation':POLICY['operation'],**{k:v for k,v in ORIGINAL.items() if k!='operation_id'},'manifest':POLICY['manifest']},'rollback_original_identity')
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
    continuation = policy.get('operation') == CONTINUATION_OPERATION
    need(type(policy.get('read_only')) is bool and policy.get('operation') == fixed_command(policy['read_only'], continuation)[1], 'mode')
    if continuation:
        need(policy.get('parent_failure') == CONTINUATION_PARENT
             and policy.get('dependency_policy_sha256') == CONTINUATION_POLICY_SHA, 'continuation_policy')
    prefix = 'MAX_BYTES=' + repr(MAX_BYTES) + '\nPOLICY=' + repr(policy) + '\nPAYLOAD=' + repr(files) + '\nIDENTITY=' + repr(identity) + '\n'
    prefix += 'def re_safe(value): return value in ' + repr(REASONS) + '\n'
    program = prefix + REMOTE
    ast.parse(program)
    return program


REASONS = ('fixed_path','fixed_root','unsafe_parent','parent_changed','directory_scope','unsafe_file','file_changed','file_limit',
           'unsupported_platform','payload_scope','payload_bytes','predecessor_drift','dependency_drift','preflight_race','private_root',
           'different_filesystem','operation_exists_no_replay','snapshot_invalid','restore_drill_failed','prewrite_race','addition_conflict',
           'addition_readback','entry_prewrite_race','addition_race','entry_readback','entry_exchange_conflict','installed_race','protected_changed',
           'operation_failed','receipt_failed','rollback_entry_conflict','parent_operation_exists')
ROLLBACK_REASONS = REASONS + ('rollback_private_record','rollback_original_identity','rollback_snapshot_conflict',
                             'rollback_candidate_changed','rollback_preflight_race','rollback_entry_readback',
                             'rollback_addition_conflict','rollback_incomplete')


def rollback_remote_program(policy, identity, original, accepted):
    need(policy.get('operation') in (OPERATION, CONTINUATION_OPERATION) and policy.get('read_only') is False, 'mode')
    need(original['run_id'] != CONTINUATION_PARENT['run_id']
         and (original.get('operation_id') == CONTINUATION_OPERATION) == (policy['operation'] == CONTINUATION_OPERATION), 'rollback_install_identity')
    need(valid_result(original, policy, accepted), 'rollback_install_evidence')
    prefix = ('MAX_BYTES=' + repr(MAX_BYTES) + '\nPOLICY=' + repr(policy) + '\nIDENTITY=' + repr(identity)
              + '\nORIGINAL=' + repr(original) + '\nACCEPTED=' + repr(accepted)
              + '\nROLLBACK_OPERATION=' + repr(ROLLBACK_OPERATION) + '\n')
    prefix += 'def re_safe(value): return value in ' + repr(ROLLBACK_REASONS) + '\n'
    # Share only filesystem definitions, never the inspect/install branches.
    program = prefix + REMOTE.split('\ndef inspect_only():', 1)[0] + ROLLBACK_REMOTE
    ast.parse(program)
    return program


def valid_rollback_result(identity, original, accepted, result):
    fixed = {'schema_version': 1, 'operation_id': ROLLBACK_OPERATION, 'source_sha': SOURCE,
             'manifest_sha256': MANIFEST_SHA, **identity, 'original_install': original,
             'snapshot_manifest_sha256': accepted['snapshot_manifest_sha256']}
    if accepted['operation_id'] == CONTINUATION_OPERATION:
        fixed.update(parent_failure=CONTINUATION_PARENT, dependency_policy_sha256=CONTINUATION_POLICY_SHA)
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
    need(policy['operation'] in (OPERATION, CONTINUATION_OPERATION) and policy['read_only'] is False, 'mode')
    continuation = policy['operation'] == CONTINUATION_OPERATION
    fixed = {'schema_version': 1, 'operation_id': policy['operation'], 'source_sha': SOURCE, 'manifest_sha256': MANIFEST_SHA, **identity}
    if continuation:
        need(policy.get('parent_failure') == CONTINUATION_PARENT
             and policy.get('dependency_policy_sha256') == CONTINUATION_POLICY_SHA, 'continuation_policy')
        fixed.update(parent_failure=CONTINUATION_PARENT, dependency_policy_sha256=CONTINUATION_POLICY_SHA)
    counters = ('supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
    required = set(fixed) | set(counters) | {'status','phase','written_files','rollback_files','snapshot_verified','restore_drill_verified','protected_unchanged','snapshot_manifest_sha256'}
    if continuation: required |= {'parent_namespace_absent', 'public_mismatches', 'private_checks'}
    need(isinstance(result, dict) and required <= set(result) <= required | {'reason'}, 'receipt_shape')
    need(all(type(result.get(k)) is type(v) and result[k] == v for k, v in fixed.items()), 'receipt_identity')
    need(all(type(result.get(k)) is int and result[k] == 0 for k in counters), 'receipt_counters')
    need(result['status'] in ('blocked_no_replay','unknown_no_replay','rolled_back_no_replay','installed_verified') and result['phase'] in ('preflight','additions','entry','complete'), 'receipt_status')
    need('reason' not in result or result['reason'] in REASONS, 'receipt_reason')
    for key in ('written_files','rollback_files'):
        need(isinstance(result[key], list) and len(result[key]) == len(set(result[key])) and set(result[key]) <= set(policy['expected']), 'receipt_paths')
    need(all(type(result[k]) is bool for k in ('snapshot_verified','restore_drill_verified','protected_unchanged')), 'receipt_flags')
    need(result['snapshot_manifest_sha256'] is None or re.fullmatch('[a-f0-9]{64}', result['snapshot_manifest_sha256']), 'receipt_snapshot')
    if continuation:
        need(type(result['parent_namespace_absent']) is bool, 'receipt_parent_namespace')
        labels = {'anex_private_config', 'public_config', 'site_config', 'local_config', 'next_config'}
        private_paths = {PRIVATE_CONFIG, *OPAQUE_SETTINGS, '_preview/search3-local-candidate/config.php', '_preview/search3-next-candidate/config.php'}
        public_paths = (set(ADDITIONS) | {ENTRY} | set(CONTINUATION_PROTECTED) | set(ABSENT_DEPENDENCIES)) - private_paths
        mismatches, checks = result['public_mismatches'], result['private_checks']
        need(isinstance(checks, dict) and set(checks) <= labels and all(type(v) is bool for v in checks.values()), 'receipt_private_checks')
        need(isinstance(mismatches, list) and len(mismatches) <= len(public_paths), 'receipt_mismatches')
        seen = set()
        for mismatch in mismatches:
            need(isinstance(mismatch, dict) and set(mismatch) == {'path', 'reason', 'observed'}
                 and mismatch['path'] in public_paths and mismatch['path'] not in seen
                 and mismatch['reason'] in REASONS, 'receipt_mismatches')
            seen.add(mismatch['path']); row = mismatch['observed']
            need(isinstance(row, dict), 'receipt_mismatch_record')
            if row.get('state') == 'present':
                need(set(row) == {'state', 'sha256', 'size', 'mode'} and re.fullmatch('[a-f0-9]{64}', row.get('sha256', ''))
                     and type(row.get('size')) is int and 0 <= row['size'] <= MAX_BYTES
                     and re.fullmatch('[0-7]{4}', row.get('mode', '')), 'receipt_mismatch_record')
            else:
                need(row == {'state': 'absent'} or set(row) == {'state', 'reason'}
                     and row.get('state') == 'unreadable' and row.get('reason') in REASONS, 'receipt_mismatch_record')
        if result['status'] == 'installed_verified':
            need(result['parent_namespace_absent'] is True and not mismatches
                 and set(checks) == labels and all(checks.values()), 'receipt_incomplete')
    if result['status'] == 'installed_verified':
        need(result['phase'] == 'complete' and result['written_files'] == list(ADDITIONS) + [ENTRY] and not result['rollback_files']
             and all(result[k] for k in ('snapshot_verified','restore_drill_verified','protected_unchanged')) and result['snapshot_manifest_sha256'] is not None and 'reason' not in result, 'receipt_incomplete')
    return result['status'] == 'installed_verified'


def valid_inspection_result(identity, policy, result):
    fixed = {'schema_version': 1, 'operation_id': INSPECT_OPERATION, 'source_sha': policy['source'],
             'manifest_sha256': policy['manifest'], **identity, 'read_only': True, 'server_writes': 0,
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
    continuation = policy['operation'] == CONTINUATION_OPERATION
    need(policy['read_only'] is False and policy['operation'] in (OPERATION, CONTINUATION_OPERATION)
         and policy['protected'] == (CONTINUATION_PROTECTED if continuation else INSTALL_PROTECTED), 'preflight_policy')
    if continuation:
        need(policy.get('parent_failure') == CONTINUATION_PARENT
             and policy.get('dependency_policy_sha256') == CONTINUATION_POLICY_SHA, 'continuation_policy')
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
    original_policy = policy | {'read_only': True, 'operation': INSPECT_OPERATION, 'protected': PROTECTED,
                               'source': PREFLIGHT_SOURCE, 'manifest': PREFLIGHT_MANIFEST_SHA}
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
            match = (row.get('state') == 'present' and row.get('sha256') == PREFLIGHT_ACCEPTED_PROTECTED.get(path)
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


def install(inspect=False, continuation=False):
    _, operation = fixed_command(inspect, continuation)
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-public-install'; work.mkdir(mode=0o700)
    key, known = work / 'key', work / 'known'
    evidence = {'status': 'preflight_blocked' if inspect else 'blocked_no_replay', 'operation_id': operation,
                'read_only': inspect, 'phase': 'identity', 'ssh_started': False}
    if inspect: evidence['server_writes'] = 0
    if continuation:
        evidence.update(parent_failure=CONTINUATION_PARENT, dependency_policy_sha256=CONTINUATION_POLICY_SHA)
    complete = False
    try:
        identity = event_identity(json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text()), os.environ, inspect, continuation)
        evidence.update(identity); evidence['phase'] = 'admission'; admission(identity, inspect, continuation)
        evidence['phase'] = 'artifact'
        signing_evidence(download(SIGNING_ARTIFACT, SIGNING_ARTIFACT_SHA))
        policy, files = package(download(ARTIFACT, ARTIFACT_SHA), inspect, continuation)
        if not inspect: preflight_evidence(download(PREFLIGHT_ARTIFACT, PREFLIGHT_ARTIFACT_SHA), policy)
        if continuation:
            continuation_parent_evidence(download(CONTINUATION_PARENT['artifact_id'], CONTINUATION_PARENT['artifact_sha256']))
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
        if continuation: admission(identity, continuation=True)
        else: final_heads(identity)
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


def update_parent_identity():
    return {k: UPDATE_PARENT[k] for k in ('control_source_sha', 'run_id', 'command_comment_id', 'operation_id')}


def _update_identity(event, env, rollback=False):
    need(env.get('GITHUB_REPOSITORY') == event.get('repository', {}).get('full_name') == REPO, 'repository')
    need(env.get('GITHUB_REF') == 'refs/heads/main' and env.get('GITHUB_WORKFLOW_REF') == f'{REPO}/{WORKFLOW}@refs/heads/main', 'trusted_workflow')
    need(env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff', 'actor')
    need(env.get('GITHUB_EVENT_NAME') == 'issue_comment' and event.get('action') == 'created' and env.get('GITHUB_RUN_ATTEMPT') == '1', 'event_attempt')
    issue, comment = event.get('issue', {}), event.get('comment', {})
    need(issue.get('number') == 4709 and 'pull_request' not in issue, 'issue')
    need(type(comment.get('user', {}).get('id')) is int and comment['user']['id'] == 226193297 and comment.get('author_association') == 'OWNER', 'owner')
    control, run = env.get('GITHUB_SHA', ''), env.get('GITHUB_RUN_ID', '')
    need(re.fullmatch('[a-f0-9]{40}', control) and re.fullmatch('[1-9][0-9]{0,19}', run), 'identity')
    identity = {'control_source_sha': control, 'run_id': run, 'command_comment_id': comment.get('id')}
    need(type(identity['command_comment_id']) is int and identity['command_comment_id'] > 0, 'command')
    if rollback:
        match = re.fullmatch(re.escape(f'{UPDATE_ROLLBACK_PREFIX} {control} ') + r'([1-9][0-9]{0,19}) ' + re.escape(UPDATE_ROLLBACK_OPERATION), comment.get('body', ''))
        need(match is not None and match[1] not in (run, UPDATE_PARENT['run_id'], CONTINUATION_PARENT['run_id']), 'command')
        return identity, match[1]
    need(comment.get('body') == f'{UPDATE_PREFIX} {control} {UPDATE_OPERATION}', 'command')
    return identity


def update_event_identity(event, env):
    return _update_identity(event, env)


def update_rollback_event_identity(event, env):
    return _update_identity(event, env, True)


def update_final_heads(identity):
    need(isinstance(UPDATE_RELEASE, str) and re.fullmatch('[a-f0-9]{40}', UPDATE_RELEASE), 'pending_final_evidence')
    need(api('/git/ref/heads/main')['object']['sha'] == identity['control_source_sha'], 'main_moved')
    need(api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha'] == UPDATE_RELEASE, 'release_moved')
    pr = api('/pulls/' + str(UPDATE_SOURCE_PR))
    need(pr.get('state') == 'closed' and pr.get('merged') is True and pr.get('merge_commit_sha') == UPDATE_RELEASE
         and pr.get('head', {}).get('sha') == UPDATE_SOURCE and pr.get('head', {}).get('repo', {}).get('full_name') == REPO
         and pr.get('base', {}).get('ref') == 'release/search3-production-ready-v1'
         and pr.get('base', {}).get('repo', {}).get('full_name') == REPO, 'candidate_moved')


def _unique_update_command(comments, prefix, operation, identity, original_run=None):
    matches = [c for c in comments if c.get('body', '').strip().split()[:1] == [prefix] and operation in c.get('body', '').split()[1:]]
    suffix = (str(original_run) + ' ') if original_run is not None else ''
    need(len(matches) == 1 and matches[0]['id'] == identity['command_comment_id']
         and matches[0].get('body') == f'{prefix} {identity["control_source_sha"]} {suffix}{operation}'
         and matches[0].get('user', {}).get('id') == 226193297 and matches[0].get('author_association') == 'OWNER', 'operation_already_reserved')


def update_admission(identity):
    update_final_heads(identity); reviewed_control(identity['control_source_sha'])
    need(api('/git/commits/' + UPDATE_SOURCE).get('tree', {}).get('sha') == UPDATE_TREE
         and api('/git/commits/' + UPDATE_RELEASE).get('tree', {}).get('sha') == UPDATE_TREE, 'source_tree')
    gate = api('/contents/.github/workflows/deploy-anytoour.yml?ref=' + identity['control_source_sha'])
    need(gate.get('type') == 'file' and gate.get('encoding') == 'base64'
         and sha(base64.b64decode(gate['content'])) == PUBLISHER_GUARD_SHA, 'publisher_guard')
    checks = pages('/commits/' + UPDATE_SOURCE + '/check-runs', 'check_runs')
    need(len(checks) == len(SOURCE_CHECK_NAMES) and {c.get('name') for c in checks} == set(SOURCE_CHECK_NAMES)
         and all(c.get('head_sha') == UPDATE_SOURCE and green(c) for c in checks), 'source_ci')
    run = api('/actions/runs/' + str(UPDATE_BUILD_RUN))
    need(run.get('id') == UPDATE_BUILD_RUN and run.get('head_sha') == UPDATE_SOURCE
         and run.get('path') == '.github/workflows/search3-visual-migration.yml'
         and run.get('event') == 'pull_request' and type(run.get('run_attempt')) is int and run['run_attempt'] == 1
         and run.get('status') == 'completed' and run.get('conclusion') == 'success', 'build_run')
    artifact = api('/actions/artifacts/' + str(UPDATE_ARTIFACT))
    need(artifact.get('id') == UPDATE_ARTIFACT and artifact.get('name') == UPDATE_ARTIFACT_NAME and artifact.get('expired') is False
         and artifact.get('digest') == 'sha256:' + UPDATE_ARTIFACT_SHA
         and artifact.get('workflow_run', {}).get('id') == UPDATE_BUILD_RUN, 'artifact_identity')
    _unique_update_command(pages('/issues/4709/comments'), UPDATE_PREFIX, UPDATE_OPERATION, identity)
    update_final_heads(identity)


def update_parent_evidence(raw, predecessor_policy):
    parent = UPDATE_PARENT
    need(sha(raw) == parent['artifact_sha256'], 'update_parent_artifact_digest')
    with zip_files(raw) as archive:
        need(archive.namelist() == ['evidence.json'], 'update_parent_artifact')
        data = archive.read('evidence.json'); need(sha(data) == parent['receipt_sha256'], 'update_parent_digest')
    value = json.loads(data); original = update_parent_identity()
    expected = {**original, 'read_only': False, 'phase': 'complete', 'ssh_started': True, 'status': 'installed_verified',
                'parent_failure': CONTINUATION_PARENT, 'dependency_policy_sha256': CONTINUATION_POLICY_SHA}
    need(isinstance(value, dict) and set(value) == set(expected) | {'remote'}
         and all(type(value.get(k)) is type(v) and value[k] == v for k, v in expected.items()), 'update_parent_identity')
    need(predecessor_policy['operation'] == CONTINUATION_OPERATION and valid_result(original, predecessor_policy, value['remote'])
         and value['remote']['snapshot_manifest_sha256'] == parent['snapshot_manifest_sha256'], 'update_parent_result')
    run = api('/actions/runs/' + parent['run_id'])
    need(run.get('id') == int(parent['run_id']) and run.get('head_sha') == parent['control_source_sha']
         and run.get('head_branch') == 'main' and run.get('path') == WORKFLOW and run.get('event') == 'issue_comment'
         and type(run.get('run_attempt')) is int and run['run_attempt'] == 1 and run.get('status') == 'completed'
         and run.get('conclusion') == 'success' and run.get('actor', {}).get('login') == run.get('triggering_actor', {}).get('login') == 'pyatkoff', 'update_parent_run')
    jobs = [j for j in pages('/actions/runs/' + parent['run_id'] + '/attempts/1/jobs', 'jobs') if j.get('name') == 'public-install']
    need(len(jobs) == 1 and jobs[0]['id'] == parent['job_id'] and jobs[0].get('run_id') == int(parent['run_id'])
         and jobs[0].get('status') == 'completed' and jobs[0].get('conclusion') == 'success', 'update_parent_job')
    steps = [s for s in jobs[0].get('steps', []) if s.get('name', '').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps) == 1 and steps[0].get('status') == 'completed' and steps[0].get('conclusion') == 'success', 'update_parent_step')
    artifact = api('/actions/artifacts/' + str(parent['artifact_id']))
    need(artifact.get('id') == parent['artifact_id'] and artifact.get('name') == 'search3-public-install-' + parent['run_id'] + '-1'
         and artifact.get('expired') is False and artifact.get('workflow_run', {}).get('id') == int(parent['run_id'])
         and artifact.get('digest') == 'sha256:' + parent['artifact_sha256'], 'update_parent_artifact')
    comments = pages('/issues/4709/comments')
    _unique_update_command(comments, CONTINUATION_PREFIX, CONTINUATION_OPERATION, original)
    terminal = next((c for c in comments if c['id'] == parent['terminal_comment_id']), {})
    tokens = [str(parent[k]) for k in ('run_id', 'control_source_sha', 'command_comment_id', 'job_id', 'artifact_id', 'artifact_sha256', 'receipt_sha256', 'snapshot_manifest_sha256')]
    need(terminal.get('user', {}).get('id') == 226193297 and terminal.get('author_association') == 'OWNER'
         and all(t in terminal.get('body', '') for t in tokens + ['installed_verified', 'NO_REPLAY']), 'update_parent_terminal')
    return value['remote']


def update_package(raw, predecessor_policy, predecessor_files):
    need(predecessor_policy['operation'] == CONTINUATION_OPERATION and predecessor_policy['source'] == SOURCE
         and predecessor_policy['manifest'] == MANIFEST_SHA and predecessor_policy['expected'][UPDATE_PATH] == UPDATE_BEFORE, 'update_predecessor_policy')
    with zip_files(raw) as archive:
        manifest_raw = archive.read('manifest.json'); need(sha(manifest_raw) == UPDATE_MANIFEST_SHA, 'manifest_digest')
        manifest = json.loads(manifest_raw)
        need(manifest.get('schema_version') == 1 and manifest.get('purpose') == 'local_public_live_install_candidate'
             and manifest.get('deployable') is False and manifest.get('profile') == {'schemaVersion': 1, 'profile': 'live', 'scenario': 'family'}
             and manifest.get('candidate') == {'sha': UPDATE_SOURCE, 'tree_sha': UPDATE_TREE}
             and manifest.get('main', {}).get('sha') == BUILD_MAIN, 'manifest_identity')
        added, replaced = manifest['files']['added'], manifest['files']['replaced']
        need(tuple(x['path'] for x in added) == ADDITIONS and len(replaced) == 1 and replaced[0]['path'] == ENTRY
             and replaced[0]['before'] == {k: v for k, v in ENTRY_BEFORE.items() if k != 'state'}, 'manifest_scope')
        rows = added + replaced
        need({x for x in archive.namelist() if x.startswith('overlay/') and not x.endswith('/')} == {'overlay/' + x['path'] for x in rows}, 'overlay_scope')
        files = {}
        for item in rows:
            path, record = item['path'], item['served']; data = archive.read('overlay/' + path)
            need(record == {'size': len(data), 'mode': '0644', 'sha256': sha(data)} and 0 < len(data) <= MAX_BYTES, 'overlay_bytes')
            encoded = base64.b64encode(data).decode()
            if path == UPDATE_PATH:
                need({'state': 'present', **record} == UPDATE_AFTER, 'update_asset_bytes'); files[path] = encoded
            else:
                need(encoded == predecessor_files[path] and {'state': 'present', **record} == predecessor_policy['expected'][path], 'update_unexpected_delta')
    policy = {'operation': UPDATE_OPERATION, 'source': UPDATE_SOURCE, 'tree': UPDATE_TREE, 'release': UPDATE_RELEASE,
              'manifest': UPDATE_MANIFEST_SHA, 'path': UPDATE_PATH, 'before': UPDATE_BEFORE, 'after': UPDATE_AFTER,
              'parent': UPDATE_PARENT, 'previous': predecessor_policy}
    return policy, files


# Reuse the installer's descriptor-relative, no-symlink, nlink==1 helpers.
# The previous inode is retained by EXCHANGE, never by a hardlink or a copy.
UPDATE_COMMON_REMOTE = r'''
def checked_stamp(value,row):
 require(isinstance(value,list) and len(value)==9 and all(type(v) is int for v in value)
         and value[0]>=0 and value[1]>0 and value[2]==(stat.S_IFREG|int(row['mode'],8))
         and value[3]==1 and value[4]==row['size'] and value[6]==os.getuid(),'update_private_record')
 return tuple(value)
def read_records(store,paths):
 rows={}
 for path in paths:
  row,identity,raw=store.read(path,True)
  require(row.get('state')=='present' and row.get('mode')==('0644' if path in ('snapshot/entry','snapshot/app') else '0600')
          and identity[6]==os.getuid(),'update_private_record')
  rows[path]=(row,identity,raw)
 return rows
PARENT_RECORD_PATHS=('reservation.json','result.json','snapshot/manifest.json','snapshot/entry','entry-intent.json')+tuple('intent-'+str(i)+'.json' for i in range(41))
def parent_records():
 rows=read_records(original,PARENT_RECORD_PATHS)
 record=lambda p:json.loads(rows[p][2])
 require(record('reservation.json')=={'operation':POLICY['operation'],**{k:v for k,v in ORIGINAL.items() if k!='operation_id'},'manifest':POLICY['manifest']}
         and record('result.json')==PARENT_ACCEPTED,'update_parent_records')
 snapshot=record('snapshot/manifest.json')
 expected_before={p:POLICY['before'] if p==POLICY['entry'] else {'state':'absent'} for p in POLICY['expected']}
 require(isinstance(snapshot,dict) and set(snapshot)=={'before','protected','source','manifest'}
         and snapshot['source']==POLICY['source'] and snapshot['manifest']==POLICY['manifest']
         and snapshot['before']==expected_before and set(snapshot['protected'])==set(checked_protected_paths())
         and digest(rows['snapshot/manifest.json'][2])==PARENT_ACCEPTED['snapshot_manifest_sha256']
         and rows['snapshot/entry'][0]==POLICY['before'],'update_parent_snapshot')
 return rows
def parent_intent(path,rows):
 if path==POLICY['entry']:
  intent=json.loads(rows['entry-intent.json'][2])
  require(isinstance(intent,dict) and set(intent)=={'before','new_identity'}
          and isinstance(intent['before'],list) and len(intent['before'])==2
          and intent['before'][0]==POLICY['before'],'update_parent_intent')
  return checked_stamp(intent['new_identity'],POLICY['expected'][path])
 index=POLICY['additions'].index(path);intent=json.loads(rows['intent-'+str(index)+'.json'][2])
 require(isinstance(intent,dict) and set(intent)=={'path','identity','sha256'} and intent['path']==path
         and intent['sha256']==POLICY['expected'][path]['sha256'],'update_parent_intent')
 return checked_stamp(intent['identity'],POLICY['expected'][path])
def parent_candidate(rows,updated=False):
 candidate=public.inventory(POLICY['expected'])
 for path,(row,identity) in candidate.items():
  desired=UPDATE_POLICY['after'] if updated and path==UPDATE_POLICY['path'] else POLICY['expected'][path]
  require(row==desired,'predecessor_drift')
  if not(updated and path==UPDATE_POLICY['path']):
   require(identity[:-1]==parent_intent(path,rows)[:-1],'update_parent_inode')
 return candidate
def parent_protected(rows):
 protected=public.inventory(checked_protected_paths())
 snapshot=json.loads(rows['snapshot/manifest.json'][2])
 require({p:r[0] for p,r in protected.items()}==snapshot['protected']
         and all(dependency_matches(p,r[0]) for p,r in protected.items()),'dependency_drift')
 return protected
def parent_hashes(rows): return {p:row[0]['sha256'] for p,row in rows.items()}
def private_root(store):
 s=os.fstat(store.fd)
 require(stat.S_IMODE(s.st_mode)==0o700 and s.st_uid==os.getuid(),'private_root')
def unchanged(candidate,before):
 return all(candidate[p]==before[p] for p in before if p!=UPDATE_POLICY['path'])
'''


UPDATE_REMOTE = r'''
result={'schema_version':1,'operation_id':UPDATE_POLICY['operation'],'source_sha':UPDATE_POLICY['source'],
        'manifest_sha256':UPDATE_POLICY['manifest'],**IDENTITY,'parent_install':ORIGINAL,
        'parent_receipt_sha256':UPDATE_POLICY['parent']['receipt_sha256'],'status':'blocked_no_replay','phase':'preflight',
        'supplier_calls':0,'database_reads':0,'database_writes':0,'lead_calls':0,'booking_calls':0,'application_calls':0,
        'written_files':[],'rollback_files':[],'reservation_created':False,'snapshot_verified':False,
        'restore_drill_verified':False,'protected_unchanged':False,'unchanged_files_verified':False,
        'parent_records_unchanged':False,'original_inode_retained':False,'snapshot_manifest_sha256':None}
public=base=original=private=None;reserved=False;switched=False;conflict=False;owned=None;new_identity=None
try:
 require(hasattr(os,'O_PATH') and hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY')
         and hasattr(ctypes.CDLL(None),'renameat2'),'unsupported_platform')
 path=UPDATE_POLICY['path']
 require(set(PAYLOAD)=={path} and path in POLICY['additions'] and len(POLICY['expected'])==42
         and POLICY['expected'][path]==UPDATE_POLICY['before'],'payload_scope')
 data=base64.b64decode(PAYLOAD[path],validate=True)
 require(UPDATE_POLICY['after']=={'state':'present','sha256':digest(data),'size':len(data),'mode':'0644'}
         and 0<len(data)<=MAX_BYTES,'payload_bytes')
 home=os.environ.get('HOME','');public=Files(home+'/www/anytoour.ru')
 base=Files(home+'/.anytoour-search3-root-gateway');private_root(base)
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation']);private_root(original)
 require(os.fstat(base.fd).st_dev==os.fstat(public.fd).st_dev,'different_filesystem')
 retained=parent_records();before=parent_candidate(retained);protected=parent_protected(retained)
 require(parent_records()==retained and public.inventory(POLICY['expected'])==before
         and public.inventory(checked_protected_paths())==protected,'preflight_race')
 try: os.mkdir(UPDATE_POLICY['operation'],0o700,dir_fd=base.fd)
 except FileExistsError: raise RuntimeError('operation_exists_no_replay') from None
 reserved=True;result['reservation_created']=True;sync(base.fd)
 private=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_POLICY['operation'],('snapshot','drill','stage','rollback'),0o700);private_root(private)
 reservation={'operation':UPDATE_POLICY['operation'],**IDENTITY,
              'source':UPDATE_POLICY['source'],'manifest':UPDATE_POLICY['manifest'],'parent_install':ORIGINAL}
 private.create('reservation.json',canonical(reservation))
 result['phase']='snapshot'
 old_row,old_identity,old=public.read(path,True)
 require((old_row,old_identity)==before[path],'predecessor_drift')
 private.create('snapshot/app',old,0o644)
 snapshot={'source':UPDATE_POLICY['source'],'manifest':UPDATE_POLICY['manifest'],'parent_install':ORIGINAL,
           'path':path,'before':{p:r[0] for p,r in before.items()},'protected':{p:r[0] for p,r in protected.items()},
           'previous_identity':list(old_identity),'parent_records':parent_hashes(retained)}
 private.create('snapshot/manifest.json',canonical(snapshot))
 require(private.read('snapshot/app')[0]==UPDATE_POLICY['before']
         and private.read('snapshot/manifest.json',True)[2]==canonical(snapshot),'snapshot_invalid')
 result['snapshot_manifest_sha256']=digest(canonical(snapshot));result['snapshot_verified']=True
 private.create('drill/app',private.read('snapshot/app',True)[2],0o644)
 require(private.read('drill/app')[0]==UPDATE_POLICY['before'],'restore_drill_failed')
 result['restore_drill_verified']=True
 private.create('stage/app',data,0o644);new_identity=private.read('stage/app')[1]
 intent={'path':path,'before':UPDATE_POLICY['before'],'after':UPDATE_POLICY['after'],
         'previous_identity':list(old_identity),'new_identity':list(new_identity)}
 private.create('intent.json',canonical(intent))
 prepared_paths=('reservation.json','snapshot/manifest.json','snapshot/app','intent.json')
 prepared=read_records(private,prepared_paths)
 require(private.read('stage/app')[:2]==(UPDATE_POLICY['after'],new_identity),'payload_bytes')
 require(prepared['reservation.json'][2]==canonical(reservation) and prepared['intent.json'][2]==canonical(intent)
         and prepared['snapshot/manifest.json'][2]==canonical(snapshot)
         and prepared['snapshot/app'][0]==UPDATE_POLICY['before'],'snapshot_invalid')
 require(parent_records()==retained and public.inventory(POLICY['expected'])==before
         and public.inventory(checked_protected_paths())==protected
         and read_records(private,prepared_paths)==prepared,'prewrite_race')
 result['phase']='asset'
 with private.parent('stage/app') as (src,name),public.parent(path) as (dst,target):
  require(public.read(path)[:2]==before[path],'entry_prewrite_race')
  exchange(src,name,dst,target);switched=True;result['written_files']=[path];sync(src);sync(dst)
 row,owned,_=public.read(path)
 require(row==UPDATE_POLICY['after'] and owned[:-1]==new_identity[:-1],'update_readback')
 try:
  prior=private.read('stage/app')
  require(prior[0]==UPDATE_POLICY['before'] and prior[1][:-1]==old_identity[:-1],'update_exchange_conflict')
 except Exception:
  conflict=True
  capture_owned(public,private,path,'rollback/conflicted-app',row,owned)
  restore_capture(public,private,path,'stage/app');switched=False
  raise RuntimeError('update_exchange_conflict')
 candidate=parent_candidate(retained,True)
 require(candidate[path]==(UPDATE_POLICY['after'],owned) and unchanged(candidate,before),'installed_race')
 require(public.inventory(checked_protected_paths())==protected and parent_records()==retained,'protected_changed')
 require(private.read('stage/app')[:2]==prior[:2] and read_records(private,prepared_paths)==prepared,'update_snapshot_conflict')
 result.update(status='updated_verified',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
               parent_records_unchanged=True,original_inode_retained=True)
except Exception as error:
 result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
 if reserved:
  result['status']='unknown_no_replay'
  try:
   require(not conflict,'update_exchange_conflict')
   if switched:
    current=public.read(path)
    require(current[0]==UPDATE_POLICY['after'] and current[1][:-1]==new_identity[:-1],'rollback_entry_conflict')
    prior=private.read('stage/app')
    require(prior[0]==UPDATE_POLICY['before'] and prior[1][:-1]==before[path][1][:-1],'rollback_snapshot_conflict')
    capture_owned(public,private,path,'rollback/app',UPDATE_POLICY['after'],current[1])
    restore_capture(public,private,path,'stage/app');switched=False;result['rollback_files']=[path]
   restored=parent_candidate(retained)
   require(unchanged(restored,before) and public.inventory(checked_protected_paths())==protected
           and parent_records()==retained,'rollback_incomplete')
   result.update(status='rolled_back_no_replay',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
                 parent_records_unchanged=True,original_inode_retained=True)
  except Exception: result['status']='unknown_no_replay'
finally:
 if reserved and private is not None:
  try: private.create('result.json',canonical(result))
  except Exception: result.update(status='unknown_no_replay',reason='receipt_failed')
 for store in (private,original,base,public):
  if store is not None: store.close()
print(json.dumps(result,sort_keys=True))
'''


UPDATE_ROLLBACK_REMOTE = r'''
result={'schema_version':1,'operation_id':UPDATE_ROLLBACK_OPERATION,'source_sha':UPDATE_POLICY['source'],
        'manifest_sha256':UPDATE_POLICY['manifest'],**IDENTITY,'original_update':UPDATE_ORIGINAL,'parent_install':ORIGINAL,
        'status':'blocked_no_replay','phase':'preflight','supplier_calls':0,'database_reads':0,'database_writes':0,
        'lead_calls':0,'booking_calls':0,'application_calls':0,'rollback_files':[],'reservation_created':False,
        'snapshot_verified':False,'protected_unchanged':False,'unchanged_files_verified':False,
        'parent_records_unchanged':False,'update_records_unchanged':False,'original_inode_restored':False,
        'snapshot_manifest_sha256':UPDATE_ACCEPTED['snapshot_manifest_sha256']}
public=base=original=updated=private=None;reserved=False
try:
 require(hasattr(os,'O_PATH') and hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY')
         and hasattr(ctypes.CDLL(None),'renameat2'),'unsupported_platform')
 path=UPDATE_POLICY['path'];home=os.environ.get('HOME','');public=Files(home+'/www/anytoour.ru')
 base=Files(home+'/.anytoour-search3-root-gateway');private_root(base)
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation']);private_root(original)
 updated=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_POLICY['operation']);private_root(updated)
 require(os.fstat(base.fd).st_dev==os.fstat(public.fd).st_dev,'different_filesystem')
 retained=parent_records();candidate=parent_candidate(retained,True);protected=parent_protected(retained)
 update_paths=('reservation.json','result.json','snapshot/manifest.json','snapshot/app','intent.json')
 update_records=read_records(updated,update_paths);record=lambda p:json.loads(update_records[p][2])
 require(record('reservation.json')=={'operation':UPDATE_POLICY['operation'],**UPDATE_ORIGINAL,
         'source':UPDATE_POLICY['source'],'manifest':UPDATE_POLICY['manifest'],'parent_install':ORIGINAL}
         and record('result.json')==UPDATE_ACCEPTED,'update_original_identity')
 snapshot=record('snapshot/manifest.json')
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_install','path','before','protected','previous_identity','parent_records'}
         and snapshot['source']==UPDATE_POLICY['source'] and snapshot['manifest']==UPDATE_POLICY['manifest']
         and snapshot['parent_install']==ORIGINAL and snapshot['path']==path and snapshot['before']==POLICY['expected']
         and snapshot['protected']=={p:r[0] for p,r in protected.items()} and snapshot['parent_records']==parent_hashes(retained)
         and digest(update_records['snapshot/manifest.json'][2])==UPDATE_ACCEPTED['snapshot_manifest_sha256']
         and update_records['snapshot/app'][0]==UPDATE_POLICY['before'],'update_snapshot_conflict')
 old_identity=checked_stamp(snapshot['previous_identity'],UPDATE_POLICY['before'])
 require(old_identity[:-1]==parent_intent(path,retained)[:-1],'update_parent_inode')
 prior=updated.read('stage/app')
 require(prior[0]==UPDATE_POLICY['before'] and prior[1][:-1]==old_identity[:-1],'update_snapshot_conflict')
 intent=record('intent.json')
 require(isinstance(intent,dict) and set(intent)=={'path','before','after','previous_identity','new_identity'}
         and intent['path']==path and intent['before']==UPDATE_POLICY['before'] and intent['after']==UPDATE_POLICY['after']
         and intent['previous_identity']==snapshot['previous_identity'],'update_private_record')
 new_identity=checked_stamp(intent['new_identity'],UPDATE_POLICY['after'])
 require(candidate[path][1][:-1]==new_identity[:-1],'rollback_candidate_changed')
 require(parent_records()==retained and read_records(updated,update_paths)==update_records
         and public.inventory(POLICY['expected'])==candidate and public.inventory(checked_protected_paths())==protected,'rollback_preflight_race')
 result['snapshot_verified']=True
 try: os.mkdir(UPDATE_ROLLBACK_OPERATION,0o700,dir_fd=base.fd)
 except FileExistsError: raise RuntimeError('operation_exists_no_replay') from None
 reserved=True;result['reservation_created']=True;sync(base.fd)
 private=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_ROLLBACK_OPERATION,('quarantine',),0o700);private_root(private)
 private.create('reservation.json',canonical({'operation':UPDATE_ROLLBACK_OPERATION,**IDENTITY,'original_update':UPDATE_ORIGINAL,
                'source':UPDATE_POLICY['source'],'manifest':UPDATE_POLICY['manifest'],'parent_install':ORIGINAL}))
 require(parent_records()==retained and read_records(updated,update_paths)==update_records
         and public.inventory(POLICY['expected'])==candidate and updated.read('stage/app')[:2]==prior[:2]
         and public.inventory(checked_protected_paths())==protected,'rollback_preflight_race')
 result['phase']='asset'
 capture_owned(public,private,path,'quarantine/app',UPDATE_POLICY['after'],candidate[path][1])
 restore_capture(public,updated,path,'stage/app');result['rollback_files']=[path]
 restored=parent_candidate(retained)
 require(unchanged(restored,candidate) and restored[path][1][:-1]==old_identity[:-1]
         and public.inventory(checked_protected_paths())==protected and parent_records()==retained
         and read_records(updated,update_paths)==update_records,'rollback_incomplete')
 result.update(status='update_rolled_back_verified',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
               parent_records_unchanged=True,update_records_unchanged=True,original_inode_restored=True)
except Exception as error:
 result['status']='unknown_no_replay' if reserved else 'blocked_no_replay'
 result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
finally:
 if reserved and private is not None:
  try: private.create('result.json',canonical(result))
  except Exception: result.update(status='unknown_no_replay',reason='receipt_failed')
 for store in (private,updated,original,base,public):
  if store is not None: store.close()
print(json.dumps(result,sort_keys=True))
'''


UPDATE_REASONS = ROLLBACK_REASONS + ('update_private_record','update_parent_records','update_parent_snapshot',
    'update_parent_intent','update_parent_inode','update_readback','update_exchange_conflict',
    'update_original_identity','update_snapshot_conflict')


def update_policy_shape(policy):
    fixed = {'operation': UPDATE_OPERATION, 'source': UPDATE_SOURCE, 'tree': UPDATE_TREE,
             'manifest': UPDATE_MANIFEST_SHA, 'path': UPDATE_PATH, 'before': UPDATE_BEFORE,
             'after': UPDATE_AFTER, 'parent': UPDATE_PARENT}
    need(isinstance(policy, dict) and set(policy) == set(fixed) | {'release', 'previous'}
         and all(type(policy.get(k)) is type(v) and policy[k] == v for k, v in fixed.items())
         and isinstance(policy['release'], str) and re.fullmatch('[a-f0-9]{40}', policy['release']), 'update_policy')
    previous = policy['previous']
    need(isinstance(previous, dict) and previous.get('operation') == CONTINUATION_OPERATION
         and previous.get('read_only') is False and previous.get('source') == SOURCE and previous.get('tree') == TREE
         and previous.get('manifest') == MANIFEST_SHA and tuple(previous.get('additions', ())) == ADDITIONS
         and previous.get('entry') == ENTRY and isinstance(previous.get('expected'), dict)
         and set(previous['expected']) == set(ADDITIONS) | {ENTRY}
         and previous['expected'][UPDATE_PATH] == UPDATE_BEFORE
         and previous.get('parent_failure') == CONTINUATION_PARENT
         and previous.get('dependency_policy_sha256') == CONTINUATION_POLICY_SHA, 'update_predecessor_policy')
    for row in previous['expected'].values():
        need(isinstance(row, dict) and set(row) == {'state', 'sha256', 'size', 'mode'}
             and row.get('state') == 'present' and isinstance(row.get('sha256'), str)
             and re.fullmatch('[a-f0-9]{64}', row['sha256']) and type(row.get('size')) is int
             and 0 < row['size'] <= MAX_BYTES and row.get('mode') == '0644', 'update_predecessor_policy')


def update_runtime_identity(identity):
    need(isinstance(identity, dict) and set(identity) == {'control_source_sha', 'run_id', 'command_comment_id'}
         and isinstance(identity.get('control_source_sha'), str) and re.fullmatch('[a-f0-9]{40}', identity['control_source_sha'])
         and isinstance(identity.get('run_id'), str) and re.fullmatch('[1-9][0-9]{0,19}', identity['run_id'])
         and type(identity.get('command_comment_id')) is int and identity['command_comment_id'] > 0
         and identity['run_id'] not in (UPDATE_PARENT['run_id'], CONTINUATION_PARENT['run_id']), 'update_runtime_identity')


def update_parent_result(policy, original, accepted):
    need(original == update_parent_identity() and valid_result(original, policy['previous'], accepted)
         and accepted['snapshot_manifest_sha256'] == UPDATE_PARENT['snapshot_manifest_sha256'], 'update_parent_result')


def _update_program_prefix(policy, identity, original, accepted):
    return ('MAX_BYTES=' + repr(MAX_BYTES) + '\nPOLICY=' + repr(policy['previous'])
            + '\nUPDATE_POLICY=' + repr(policy) + '\nIDENTITY=' + repr(identity)
            + '\nORIGINAL=' + repr(original) + '\nPARENT_ACCEPTED=' + repr(accepted)
            + '\ndef re_safe(value): return value in ' + repr(UPDATE_REASONS) + '\n'
            + REMOTE.split('\ndef inspect_only():', 1)[0] + UPDATE_COMMON_REMOTE)


def update_remote_program(policy, files, identity, original, accepted):
    update_policy_shape(policy); update_runtime_identity(identity); update_parent_result(policy, original, accepted)
    need(policy['release'] == UPDATE_RELEASE and isinstance(files, dict) and set(files) == {UPDATE_PATH}, 'update_payload')
    raw = base64.b64decode(files[UPDATE_PATH], validate=True)
    need(UPDATE_AFTER == {'state': 'present', 'sha256': sha(raw), 'size': len(raw), 'mode': '0644'}, 'update_asset_bytes')
    program = _update_program_prefix(policy, identity, original, accepted) + '\nPAYLOAD=' + repr(files) + '\n' + UPDATE_REMOTE
    ast.parse(program)
    return program


def update_rollback_remote_program(policy, identity, original, accepted, parent, parent_accepted):
    update_policy_shape(policy); update_runtime_identity(identity); update_runtime_identity(original)
    need(identity['run_id'] != original['run_id'] and identity['command_comment_id'] != original['command_comment_id'], 'update_rollback_identity')
    update_parent_result(policy, parent, parent_accepted)
    need(valid_update_result(original, policy, accepted), 'update_rollback_evidence')
    program = (_update_program_prefix(policy, identity, parent, parent_accepted)
               + '\nUPDATE_ORIGINAL=' + repr(original) + '\nUPDATE_ACCEPTED=' + repr(accepted)
               + '\nUPDATE_ROLLBACK_OPERATION=' + repr(UPDATE_ROLLBACK_OPERATION) + '\n' + UPDATE_ROLLBACK_REMOTE)
    ast.parse(program)
    return program


def valid_update_result(identity, policy, result):
    fixed = {'schema_version': 1, 'operation_id': UPDATE_OPERATION, 'source_sha': UPDATE_SOURCE,
             'manifest_sha256': UPDATE_MANIFEST_SHA, **identity, 'parent_install': update_parent_identity(),
             'parent_receipt_sha256': UPDATE_PARENT['receipt_sha256']}
    counters = ('supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
    flags = ('reservation_created','snapshot_verified','restore_drill_verified','protected_unchanged',
             'unchanged_files_verified','parent_records_unchanged','original_inode_retained')
    required = set(fixed) | set(counters) | set(flags) | {'status','phase','written_files','rollback_files','snapshot_manifest_sha256'}
    need(isinstance(result, dict) and required <= set(result) <= required | {'reason'}, 'update_receipt_shape')
    need(all(type(result.get(k)) is type(v) and result[k] == v for k, v in fixed.items()), 'update_receipt_identity')
    need(all(type(result.get(k)) is int and result[k] == 0 for k in counters), 'update_receipt_counters')
    need(all(type(result[k]) is bool for k in flags), 'update_receipt_flags')
    need(result['status'] in ('blocked_no_replay','unknown_no_replay','rolled_back_no_replay','updated_verified')
         and result['phase'] in ('preflight','snapshot','asset','complete')
         and ('reason' not in result or result['reason'] in UPDATE_REASONS), 'update_receipt_status')
    need(all(result[k] in ([], [UPDATE_PATH]) for k in ('written_files','rollback_files')), 'update_receipt_paths')
    snapshot = result['snapshot_manifest_sha256']
    need(snapshot is None or isinstance(snapshot, str) and re.fullmatch('[a-f0-9]{64}', snapshot), 'update_receipt_snapshot')
    if result['status'] == 'blocked_no_replay':
        need(not result['reservation_created'] and result['phase'] == 'preflight'
             and not result['written_files'] and not result['rollback_files'], 'update_receipt_incomplete')
    if result['status'] in ('rolled_back_no_replay', 'updated_verified'):
        need(result['phase'] == 'complete' and result['reservation_created'] and result['protected_unchanged']
             and result['unchanged_files_verified'] and result['parent_records_unchanged']
             and result['original_inode_retained'], 'update_receipt_incomplete')
    if result['status'] == 'updated_verified':
        need(all(result[k] for k in flags) and result['written_files'] == [UPDATE_PATH] and not result['rollback_files']
             and snapshot is not None and 'reason' not in result, 'update_receipt_incomplete')
    return result['status'] == 'updated_verified'


def valid_update_rollback_result(identity, original, accepted, policy, result):
    fixed = {'schema_version': 1, 'operation_id': UPDATE_ROLLBACK_OPERATION, 'source_sha': UPDATE_SOURCE,
             'manifest_sha256': UPDATE_MANIFEST_SHA, **identity, 'original_update': original,
             'parent_install': update_parent_identity(), 'snapshot_manifest_sha256': accepted['snapshot_manifest_sha256']}
    counters = ('supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
    flags = ('reservation_created','snapshot_verified','protected_unchanged','unchanged_files_verified',
             'parent_records_unchanged','update_records_unchanged','original_inode_restored')
    required = set(fixed) | set(counters) | set(flags) | {'status','phase','rollback_files'}
    need(isinstance(result, dict) and required <= set(result) <= required | {'reason'}, 'update_rollback_receipt_shape')
    need(all(type(result.get(k)) is type(v) and result[k] == v for k, v in fixed.items()), 'update_rollback_receipt_identity')
    need(all(type(result.get(k)) is int and result[k] == 0 for k in counters), 'update_rollback_receipt_counters')
    need(all(type(result[k]) is bool for k in flags), 'update_rollback_receipt_flags')
    need(result['status'] in ('blocked_no_replay','unknown_no_replay','update_rolled_back_verified')
         and result['phase'] in ('preflight','asset','complete')
         and ('reason' not in result or result['reason'] in UPDATE_REASONS), 'update_rollback_receipt_status')
    need(result['rollback_files'] in ([], [UPDATE_PATH]), 'update_rollback_receipt_paths')
    if result['status'] == 'blocked_no_replay':
        need(not result['reservation_created'] and result['phase'] == 'preflight' and not result['rollback_files'], 'update_rollback_receipt_incomplete')
    if result['status'] == 'update_rolled_back_verified':
        need(result['phase'] == 'complete' and all(result[k] for k in flags)
             and result['rollback_files'] == [UPDATE_PATH] and 'reason' not in result, 'update_rollback_receipt_incomplete')
    return result['status'] == 'update_rolled_back_verified'


def update_rollback_commands(identity, original_run, expected_original=None):
    need(isinstance(original_run, str) and re.fullmatch('[1-9][0-9]{0,19}', original_run)
         and original_run not in (identity['run_id'], UPDATE_PARENT['run_id'], CONTINUATION_PARENT['run_id']), 'update_rollback_run')
    comments = pages('/issues/4709/comments')
    _unique_update_command(comments, UPDATE_ROLLBACK_PREFIX, UPDATE_ROLLBACK_OPERATION, identity, original_run)
    installed = [c for c in comments if c.get('body', '').strip().split()[:1] == [UPDATE_PREFIX]
                 and UPDATE_OPERATION in c.get('body', '').split()[1:]]
    need(len(installed) == 1 and installed[0].get('user', {}).get('id') == 226193297
         and installed[0].get('author_association') == 'OWNER', 'update_rollback_command')
    match = re.fullmatch(re.escape(UPDATE_PREFIX) + r' ([a-f0-9]{40}) ' + re.escape(UPDATE_OPERATION), installed[0].get('body', ''))
    need(match is not None, 'update_rollback_command')
    original = {'control_source_sha': match[1], 'run_id': original_run, 'command_comment_id': installed[0]['id']}
    update_runtime_identity(original)
    need(expected_original is None or original == expected_original, 'update_rollback_command')
    return original


def update_rollback_admission(identity, original_run):
    # A proven successful update is self-contained recovery evidence. No source
    # release, source CI, signer, or initial installation artifact is fetched.
    rollback_main(identity); reviewed_control(identity['control_source_sha'])
    original = update_rollback_commands(identity, original_run)
    run = api('/actions/runs/' + original_run)
    need(run.get('id') == int(original_run) and run.get('head_sha') == original['control_source_sha']
         and run.get('head_branch') == 'main' and run.get('path') == WORKFLOW and run.get('event') == 'issue_comment'
         and type(run.get('run_attempt')) is int and run['run_attempt'] == 1
         and run.get('status') == 'completed' and run.get('conclusion') == 'success'
         and run.get('actor', {}).get('login') == run.get('triggering_actor', {}).get('login') == 'pyatkoff', 'update_rollback_run')
    jobs = [j for j in pages('/actions/runs/' + original_run + '/attempts/1/jobs', 'jobs') if j.get('name') == 'public-install']
    need(len(jobs) == 1 and jobs[0].get('run_id') == int(original_run) and jobs[0].get('status') == 'completed'
         and jobs[0].get('conclusion') == 'success', 'update_rollback_job')
    steps = [s for s in jobs[0].get('steps', []) if s.get('name', '').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps) == 1 and steps[0].get('status') == 'completed' and steps[0].get('conclusion') == 'success', 'update_rollback_step')
    name = 'search3-public-install-' + original_run + '-1'
    artifacts = [a for a in pages('/actions/runs/' + original_run + '/artifacts', 'artifacts') if a.get('name') == name]
    need(len(artifacts) == 1 and artifacts[0].get('expired') is False
         and artifacts[0].get('workflow_run', {}).get('id') == int(original_run)
         and re.fullmatch(r'sha256:[a-f0-9]{64}', artifacts[0].get('digest', '')), 'update_rollback_artifact')
    rollback_main(identity)
    return original, artifacts[0]


def update_previous_policy(expected):
    # These are the original install's public hashes, retained by the successful
    # update receipt. All private/dependency expectations remain compiled here.
    return {'operation': CONTINUATION_OPERATION, 'read_only': False, 'source': SOURCE, 'tree': TREE,
            'release': RELEASE, 'manifest': MANIFEST_SHA, 'additions': ADDITIONS, 'entry': ENTRY,
            'before': ENTRY_BEFORE, 'expected': expected, 'protected': CONTINUATION_PROTECTED,
            'private_config': PRIVATE_CONFIG, 'opaque_settings': OPAQUE_SETTINGS, 'absent_dependencies': ABSENT_DEPENDENCIES,
            'signing_result': SIGNING_RESULT_SHA, 'opaque_presence': INSTALL_OPAQUE_PRESENCE,
            'parent_failure': CONTINUATION_PARENT, 'dependency_policy_sha256': CONTINUATION_POLICY_SHA}


def update_source_evidence(raw, original):
    update_runtime_identity(original)
    with zip_files(raw) as archive:
        need(archive.namelist() == ['evidence.json'], 'update_rollback_artifact')
        value = json.loads(archive.read('evidence.json'))
    fixed = {**original, 'operation_id': UPDATE_OPERATION, 'source_sha': UPDATE_SOURCE,
             'manifest_sha256': UPDATE_MANIFEST_SHA, 'read_only': False, 'phase': 'complete',
             'ssh_started': True, 'status': 'updated_verified', 'parent_install': update_parent_identity()}
    need(isinstance(value, dict) and set(value) == set(fixed) | {'remote','parent_result','previous_public','release_sha'}
         and all(type(value.get(k)) is type(v) and value[k] == v for k, v in fixed.items()), 'update_rollback_evidence')
    policy = {'operation': UPDATE_OPERATION, 'source': UPDATE_SOURCE, 'tree': UPDATE_TREE,
              'release': value['release_sha'], 'manifest': UPDATE_MANIFEST_SHA, 'path': UPDATE_PATH,
              'before': UPDATE_BEFORE, 'after': UPDATE_AFTER, 'parent': UPDATE_PARENT,
              'previous': update_previous_policy(value['previous_public'])}
    update_policy_shape(policy)
    parent = value['parent_result']; update_parent_result(policy, update_parent_identity(), parent)
    parent_outer = {**update_parent_identity(), 'read_only': False, 'phase': 'complete', 'ssh_started': True,
                    'status': 'installed_verified', 'parent_failure': CONTINUATION_PARENT,
                    'dependency_policy_sha256': CONTINUATION_POLICY_SHA, 'remote': parent}
    need(sha((json.dumps(parent_outer, sort_keys=True) + '\n').encode()) == UPDATE_PARENT['receipt_sha256'], 'update_parent_digest')
    need(valid_update_result(original, policy, value['remote']), 'update_rollback_evidence')
    return policy, value['remote'], parent


def update(rollback=False):
    need(type(rollback) is bool, 'mode')
    operation = UPDATE_ROLLBACK_OPERATION if rollback else UPDATE_OPERATION
    work = Path(os.environ['RUNNER_TEMP']) / 'search3-public-install'; work.mkdir(mode=0o700)
    key, known = work / 'key', work / 'known'
    evidence = {'status': 'blocked_no_replay', 'operation_id': operation, 'source_sha': UPDATE_SOURCE,
                'manifest_sha256': UPDATE_MANIFEST_SHA, 'read_only': False, 'phase': 'identity', 'ssh_started': False}
    complete = False
    try:
        event = json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
        if rollback:
            identity, original_run = update_rollback_event_identity(event, os.environ)
            evidence.update(identity, original_update_run_id=original_run, phase='admission')
            original, artifact = update_rollback_admission(identity, original_run)
            evidence['phase'] = 'artifact'
            policy, accepted, parent_accepted = update_source_evidence(download(artifact['id'], artifact['digest'].removeprefix('sha256:')), original)
            program = update_rollback_remote_program(policy, identity, original, accepted, update_parent_identity(), parent_accepted)
            evidence.update(parent_install=update_parent_identity(), original_update=original)
        else:
            identity = update_event_identity(event, os.environ)
            evidence.update(identity, phase='admission'); update_admission(identity)
            evidence['phase'] = 'artifact'
            previous, previous_files = package(download(ARTIFACT, ARTIFACT_SHA), continuation=True)
            parent_accepted = update_parent_evidence(download(UPDATE_PARENT['artifact_id'], UPDATE_PARENT['artifact_sha256']), previous)
            policy, files = update_package(download(UPDATE_ARTIFACT, UPDATE_ARTIFACT_SHA), previous, previous_files)
            program = update_remote_program(policy, files, identity, update_parent_identity(), parent_accepted)
            evidence.update(parent_install=update_parent_identity(), parent_result=parent_accepted,
                            previous_public=previous['expected'], release_sha=policy['release'])
        # The same fixed carrier, secrets and bounded SSH invocation as install.
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
        if rollback:
            update_rollback_commands(identity, original_run, original); rollback_main(identity)
        else: update_admission(identity)
        evidence.update(status='unknown_no_replay', phase='remote', ssh_started=True)
        response = carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'], input=program.encode(), timeout=180)
        need(len(response) <= 32768, 'receipt_limit'); result = json.loads(response)
        complete = valid_update_rollback_result(identity, original, accepted, policy, result) if rollback else valid_update_result(identity, policy, result)
        evidence['remote'] = result
        if rollback: rollback_main(identity)
        else: update_final_heads(identity)
        evidence.update(status=result['status'], phase='complete')
    except Exception as error:
        complete = False
        evidence['error_type'] = type(error).__name__ if type(error) in (RuntimeError,ValueError,KeyError,OSError,subprocess.CalledProcessError,subprocess.TimeoutExpired) else 'PublicUpdateError'
    finally:
        key.unlink(missing_ok=True); known.unlink(missing_ok=True)
        (work / 'evidence.json').write_text(json.dumps(evidence, sort_keys=True) + '\n')
    print(json.dumps(evidence, sort_keys=True)); need(complete, 'public_update_rollback_not_verified' if rollback else 'public_update_not_verified')


def command_mode(arguments):
    if arguments == ['update']: return 'update'
    if arguments == ['update-rollback']: return 'update-rollback'
    if arguments == ['rollback']: return 'rollback'
    if arguments == ['continue']: return 'continue'
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
        continuation = original.get('operation_id') == CONTINUATION_OPERATION
        policy, _ = package(download(ARTIFACT, ARTIFACT_SHA), continuation=continuation)
        if continuation:
            # The successful child's exact artifact/private result retain this
            # proof. Recovery does not depend on the failed parent's availability.
            evidence.update(parent_failure=CONTINUATION_PARENT, dependency_policy_sha256=CONTINUATION_POLICY_SHA)
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
    if mode == 'update': update()
    elif mode == 'update-rollback': update(rollback=True)
    elif mode == 'rollback': rollback()
    elif mode == 'continue': install(continuation=True)
    else: install(mode)
