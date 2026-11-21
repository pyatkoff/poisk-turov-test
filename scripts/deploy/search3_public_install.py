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


# This separately reviewed operation shares the existing transport and lock. It
# neither reopens the consumed public transactions nor changes their policies.
SITE_PREFIX = '/update-site-shared-assets'
SITE_OPERATION = 'site-shared-assets-20261121-v1'
SITE_ROLLBACK_PREFIX = '/rollback-site-shared-assets'
SITE_ROLLBACK_OPERATION = 'site-shared-assets-rollback-20261121-v1'
SITE_SOURCE_PR = 5133
SITE_SOURCE = '53c523c3664d2e9e893942d0eb63b2637e1df978'
SITE_TREE = 'ef15774d5209de96d589104486f6cd957b1ef0aa'
SITE_PUBLISHER_GUARD_SHA = '025f31dd3c4d1e4f6f24578d0d29d975983ed21e1fa6cfef0d8bc54ccabfc851'
SITE_CHECKS = ('guard','seo-core','navigation-contract','contract','predeploy-contract','content-live',
               'publication-manifest','core','bundles','content-ux','tablet-selected-tour')
SITE_PARENT = {
    'operation_id': UPDATE_OPERATION, 'control_source_sha': 'efc380ffca5054cc46e764c56f4e04b251b71831',
    'run_id': '44887902112', 'command_comment_id': 7060338922, 'job_id': 136797108099,
    'artifact_id': 14700542611,
    'artifact_sha256': '48465fa0afcbda66b704ea6d0fd9f182d6169497cfb250f3c0280818216147c7',
    'receipt_sha256': 'ab52afbbb2031c75dea9873cc9fe4004356474fdb10273ff1ef2e993d5cc8274',
    'snapshot_manifest_sha256': '707bb98e6f5be6a4ec74923de659a0b64741e097bde5d303bf5a8bf7839d93f1',
}
SITE_ORDER = ('bundle-manifest-v1.php','assets.php','bundle-v1.php','site-page-shell-v1.php')
# Fixed reviewed source/preimage and dependency maps are deliberately independent
# of any observed server bytes. No caller can supply a path, source or hash.
SITE_TARGETS = {'assets.php': {'after': {'mode': '0644',
                          'sha256': '58bc5a3ac26905e4779bbd7137be2b86f0f67023590523c816263723fc77de6e',
                          'size': 2469,
                          'state': 'present'},
                'before': {'mode': '0644',
                           'sha256': '432bff57cdb360176a9fcc409f2f66bcba97f3571c8d7c06c502b7a5b4f2f076',
                           'size': 2105,
                           'state': 'present'},
                'blob': '5905c963dbdc78e3a936aa6b1dfcf162120b0831'},
 'bundle-manifest-v1.php': {'after': {'mode': '0644',
                                      'sha256': 'd3476c2ffc66625ac1cc03e8ccc94def1354e185d56c44bde89be1136f4129fb',
                                      'size': 3383,
                                      'state': 'present'},
                            'before': {'mode': '0644',
                                       'sha256': '634ed68bb20d0ae93725e591b70901688fecdf5c9c08562025423c50fb0c9a5d',
                                       'size': 2447,
                                       'state': 'present'},
                            'blob': 'f3bab968d4208c589b291e74fcbfd506ad4c8107'},
 'bundle-v1.php': {'after': {'mode': '0644',
                             'sha256': '0a26b803e018a0e9dd8e063894fe10a3cc121aa88dd6220b72aedd5cca6f70ca',
                             'size': 1406,
                             'state': 'present'},
                   'before': {'mode': '0644',
                              'sha256': 'dc0d7a4037f9e13c6cc47c0d8713dae893b79146fbfab338b71ec452da878da7',
                              'size': 1339,
                              'state': 'present'},
                   'blob': '7a55c859d9b47595b6c8fc0445add9b7eb57ea74'},
 'site-page-shell-v1.php': {'after': {'mode': '0644',
                                      'sha256': 'a17162e1baa2364bc1d50fe9d57fb6007e0624b1b757b89652c6f9d1e415ec20',
                                      'size': 5446,
                                      'state': 'present'},
                            'before': {'mode': '0644',
                                       'sha256': 'e5f0009380d1fb35c047510e549bb3f066b0c9bdabad71362f01ab80174cb2b2',
                                       'size': 6407,
                                       'state': 'present'},
                            'blob': '7ea5883d20325ea22cb44614d87a2a763cfb893e'}}
SITE_DEPENDENCIES = {'accessibility.js': '24be740204c542579c1a3d3d3623b8907c03b117686bcb43dd53a0fc718bb99a',
 'analytics-config.php': '1cdb2d53eda3e52c5ba1480f3df5d184c3b51ecf707952cb49560c395e463a82',
 'analytics-v4.js': '9f91622ad81d1812753083008d8225e1619dd7fd7fb7ba8f0fdea0e7c65e8602',
 'anytour-brand.css': '12895260ab4e4a7fb944c7f90e6a5d1d915d344a245749e12b4282d5f2270b3f',
 'app.css': '01278d107734c5cefc147fc3dfb35bf61ade556c160f9cf27a56d1262200b091',
 'asset-version-v1.php': 'f39fc578b10180a992a4614dc880a0c1da6aaa52b0c1e851d5869096fd5b7950',
 'br3-control-consistency-v1.css': 'c4e7a6e55df39f942046cf3bebe1442a5e6dd68588bd139d96e91c2ee45ec0f1',
 'catalog-local-routing-v1.js': 'ed38a6450364b0d532d9975e86ee03aaede5cc568a68442946d71c9e70b20cd9',
 'catalogs-v2.js': 'fbad31a91e3503fafe654197a14ab85add6b81239e2168df1f8f91ee91213049',
 'checkout-experience-v1.css': '517957e6252681a29396828f069ddaf958419c19c7f7c389fd1f0a25d89b6ad0',
 'checkout-experience-v1.js': '9357649dde654687667a96da3a45d29ea6224a5d869e204d864c3b26e6930faf',
 'compare-refresh-guard-v1.js': 'a76cdd73b1f75d10ec1b3604c41520745551a49a8be21f31ac247b0cbcfe8801',
 'contacts/index.php': '39261fd38988102c93742409034781e60c0b5dd01083566e4fcb4c9053cc4d2c',
 'conversion-confidence-v1.css': '7c19ae3e4cd2f2ddacd7a70f57cd9e41f71395e305509127a5194cd3731563f5',
 'conversion-confidence-v1.js': '1b12a0b983b3b89e9f0a2cbb3bd92660ffed3ecc6c2d550061cf63e5a16de391',
 'country-matrix-routing-v1.js': '1c541f607386a8dd354c10137b904e183a014c2105b330cb03a4a6b67ddd5cb5',
 'country-page-v1.php': '7a1c91a899a3bac1daa285a07ad6b586604fb08008063b6263a91e6abd51017b',
 'country/index.php': '47e83c67fe7b3898ad913a4a76cac3955d303bc0f2b3934c6241ddcc8199f8df',
 'country/turkey/index.php': 'a67d6626ad396a9878037dc28cc903eaae334ebf595acd028c816d7a108fb956',
 'current-price-calendar-v1.css': 'adb40ad9e99a23cb598e6e1756a5518f85a7602845bdebc1db0d2e1a12d04fdb',
 'current-price-calendar-v1.js': '2645f215c244df674f686b939b6bb45557d76b18cba3ead7f365779b48f9b58f',
 'data/db-v1.php': 'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13',
 'data/hot-tours-read-v1.php': 'a57b170901b445911a97728479ca5bc6f654ef62af0681dc38b97d9db1d79616',
 'data/price-calendar-core-v1.php': '92f0e9df57555425ae1a861255a9346dbb13510dbfe81818486c1a0069659123',
 'data/price-confidence-v1.php': 'c8f607ab65f99d2991732dd539b00c4c34ec8cd7e9ac36077232335908e47759',
 'data/price-intelligence-v1.php': 'b49f7acf88b06bf3d204b845580f1b93a29e1ec70f4b27d0e1416551d1f3a174',
 'data/price-segment-v1.php': '6db65d0a8259d447c2fc1f3acf2f8cf9b5a758a660ca4ee3fd41db6420133b6f',
 'design-system-v2.css': '9d7cd0c1c330b7aa04bd87e5e0a9f0756c594f27803a67302330ab33a604aa70',
 'design-v1.css': '1a7884cb20b9209447edb00f867238686396dcd5ef734d2446553ecb89f902be',
 'ds2-results-filters.js': '6fdb1d4fbb177b2cead3a2f4b74adc5f5c7d26bb854f9516b38ff50c4d5325a5',
 'ds2-search-intro-v1.css': '4507a7b58e06ffdc5dc00d573f2ad08ba22692f2c7946387f418d0b7253ce7ae',
 'ds2-search-tablet-filters-v1.css': '6c098179c01ee7dfec3a22ee8d2a68995c86dd0cc1f4b075467729dbc291a2b3',
 'ds2-search.css': '170bab9840142b979a3b528a96c5605e7066099d8b977ac698954817d96bbfd7',
 'ds2-selected-tour-convergence-v1.css': 'b3d13752268dd23116fa4c250d479f75a74f663133114786972734eb94182d56',
 'editorial-ds2-convergence-v1.css': '81712d596910b1f3c8293c121b6af7d03424cb0882ab312771afe951a5528c4e',
 'editorial-rhythm-v1.css': '42824f1232b5c7be7f949612668feae91df81fb51b7948791db569eea1a4f137',
 'enhancements.css': '2242fd5b76f68a366cf6f60eeea81b545178da9b35256bab60fc670731253f89',
 'flight-empty-recovery-v1.js': 'def6cb029479cb7bb90bc44cbef82bb66a9e7ec240e8787c0c0562d7abdc73a9',
 'flight-price-sync-v1.js': '263a5795c61da3a7cc4018f04c513f7e59b7f9e0fb9c8918fb46298564fc42e8',
 'form-defaults.php': '88c3c09b899c7a4fe873575303f0d7b2e042c5725e44c04a1d032f56e926cff0',
 'header-current-site.css': '54c4cfffb2a26f50b2e2a839f3e7409a9d2b801d8a36aba87226dc7ee45f766a',
 'header-current-site.js': '40502bffcd32af366ba92fb271a16091b94d70febf0b555b2b56a144fd467dd8',
 'hot/index.php': 'a990d757ab8abb4efc0ed8108167c923a4a5f3b5d5a15f5e317495ab5c339225',
 'hotel-actions-v3.js': 'dcf931b02869bbec6f5dbe1768bcb8360b672e244f27f82c4d8862ce4160873d',
 'hotel-autocomplete-v1.css': '888743db25a2bb6a23850260c69d2947625e5c252fc131253b9b3da079804e6b',
 'hotel-autocomplete-v1.js': '32fba3272f2c7495210cbab7b0e439d880c44f5d36aa9e9a150ab963884fa64a',
 'hotel-details-design.css': 'e255c6393f64dbddd60fef95b390f9e79124f7645d621d91d80ca71b37143037',
 'how-to-buy/index.php': '11b3503979cba98cce9775525fa13070678bf2144496c8a841d192ee4d7c932d',
 'index.php': '80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f',
 'lead-form-guard-v1.js': '42427863281ca0bdcb7a84693f57687cd10747196dc85fc5daffdd477fc7c48a',
 'lead-search-context.js': '8d6eafbdf9fbb23aa5984edbe2c585b65c5647517492f7799c1dd732e710335f',
 'lead-ui-race-guard-v1.js': '75461d02a2416e5d1c8d53147603155c3868347d05a5cbed95b56803de3c3953',
 'mobile-results-filters-v1.css': '0843a90d2cd2c066749520cd70ee5cf791c243572362e65d8d24d7754a4e8431',
 'mobile-results-filters-v1.js': 'fe2a57b03e9fafca3cbea49edd0913470da8ee5ec9660f5c94a4f911f6da6821',
 'mobile-search-summary-v1.css': 'ed8cd7fdf24e1493154c825f4ae750164657eea4675560e39443151f7a62a979',
 'mobile-search-summary-v1.js': '65492407f049af1f04c930927f204ff7c29f5e37e47d79bbac79e3d226561fc2',
 'passive-price-observer-v1.js': 'edb52ffef902da3567a2fb8f6c4905cc0053cdb72b6123294baf963a19383260',
 'phone-value.php': 'cc94788cda8ae7fabd6b3d3964dff2f5b56d9e33cf83bd6289dfda3c60774a40',
 'price-confidence-v1.js': 'b02d9f5bc0c665c437d50055ad991b0e70cd50bc2ae1508b79891882b0238e0d',
 'primary-meal-ux-v1.css': '3b4eecd670545fb516d043d24164002cedaf60f382904c16a7c1f2875e726f9d',
 'primary-meal-ux-v1.js': '434a642dfdfbc477eec51cd2d0ad43baded834a3436e616a3f97dfe37b595d81',
 'privacy-config.php': '56fd870bdfdeba1bc663549f64cbbb456bd1c4601c583e7bd0dce28cdf4f61ed',
 'product-shell-v1.css': 'ed0e3035ae67f1fa6098c9ffd7f5661578491a0f15869b92dffd5cb3a000d102',
 'rb/index.php': '6c2dfa34f8c461b0b8d2efd6c80000ae5e298b7f41fb36b3ea9cad1ab9d4100e',
 'results-depth-v1.js': '3d7d7b8ac59fffb689521e48269403d86175c6ad5f848379bed4399f6873da1f',
 'results-experience-v1.css': '843cc5ac46ce722b05d90fad8b6af2c90ff99de2a9c7d80a418cc1856f0df42e',
 'results-filter-autorefresh-v1.js': '2266aafdbec74fcd4e44c624735a7498fc85c5029074e09c3d5d7841226d17ea',
 'results-layout-guard-v1.css': '45fa7e593a6503d8d614cf4d40415bb069d2b0e6f244992e42d43cba64e54b3d',
 'results-local-filters-v1.js': '2e8079d2be692c40fe25c97185f194434df2a93bd8b5ea97fa80517d2b324232',
 'results-renderer-v5.js': 'bc183434804b97e2bd6a46ace224d4a7d039b84b684cdb8f5bd093102a5d6972',
 'room-details-v3.js': '2aa8198d125d11ffda4f8a6d51faf73bf0dbd6640983bd3d913431cbfa5ec676',
 'room-details.css': 'bef58a245f388db3b434111d86e497619b4811eb66899e1f0ff28b2e49a86f5d',
 'runtime-retry-policy.js': '0fbb53a814e5aa18514ff97aa16ecbe7faaa247bb2cecbf2f9650db1bd29f140',
 'runtime-v3.js': '67f014910a5344fab7ed7e70d405f6e3256578eb84e8fbe634eed2a2c8baef92',
 'sales-leader-ui-v1.css': 'eb85aa0ae7e7226c81bbbe342af631b7ac4c43bbb97a7c70c36e7a9691a1e6d6',
 'sales-leader-ui-v1.js': 'd85f369522b95e39c317c5b60704a550729ba38fb6040bd0560be62da76c6bfa',
 'search-complete-recovery-v1.js': 'f7ba7a75c1b55ee89a4128d550425821920d794593bf3c8ac8184a0a05cf1296',
 'search-continue-v6.js': 'ee90fc5eccca3cd63cb7c0159c06501ae1c2c94a195246095719f27a5676e38a',
 'search-dirty-ux-v1.css': '9df679a8593e79bf8580719668922416a28de86964f6f98dc28ea498dd9a2566',
 'search-dirty-ux-v1.js': '009437ebff77c0ea95978e78de0130279827209ad842c32e8cbbb31242a11059',
 'search-filters-ux-v1.css': 'a0cdb817c86aea37d1c276d6f479bbe0d371db8bf11c1d706fceeca98dc0fc53',
 'search-filters-ux-v1.js': '0dd1243ff20ac0852c9c716f6da61a8b48fcc5133f12d50dd17deee9523e0cc6',
 'search-footer-rhythm-v1.css': '86415ffa6a17545e6d8076265cfcdf7c91741fe15af81579a2aecae531e15307',
 'search-header-layout-guard-v1.css': '9026f76aaa794ea3371fcc4926a8a363ad414998c9ac408095e7ec0334ec4b06',
 'search-header-shared-shell-v1.css': 'f4171a0746316767a354b8266c7f52dcc498c318dec6e7a672e2e846d00be389',
 'search-lifecycle-v6.js': '3cc27d84a13ed010daa0cdf850c73e47e58aa7b9edff6a0155b4456d1e914e61',
 'search-page-v2.php': '80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f',
 'search-params-filter-rail-v1.css': '23b2d4801218f03fc34851e0eb909333585275e86680a55ff83752b440bb98ea',
 'search-params-filter-rail-v1.js': '9088c377cd67f820edf963eef185f6886b8ca3650b6688a357d40f84b8239a88',
 'search-progress-ux-v1.css': '8e9b0ee5b2bfe7916d97bdc1b58a64a39a6982d18986dd614e7e68d4d3d0671b',
 'search-progress-ux-v1.js': 'fae8898f03f86e278bed256e784efb3fea5dc65167d801053af5de963ff6aa3d',
 'search-redesign-v2.js': '39fc08a80007b2f06bf0a9156115bcd05d10095dcb29d35a00bf7ef5dbc1aed2',
 'search-shell-grid-v1.css': '9e877a4fbda2a87602ee1d99821cd1d636f7db6005ba4db6d40629777548f004',
 'search-states-design.css': '42294f4db86d4c69b318a67a4bedb7989335a04ca072e915dba4d5ead5c0d8bd',
 'selected-tour-description-v1.js': '0e081b613de51c1d665d8c869863a82c8cd8636aa0f9b7f957c463bfe031703f',
 'selected-tour-layout-guard-v1.css': '3fd0ec404c8be1a2a96249a4026f0532da68b4375a6b38fdefbabcdf53a8da37',
 'selected-tour-return-v1.js': '1c128bf379a64064bf54db24f010613c898a0a7d3db28d847824907191e4dc39',
 'selected-tour-ux.css': '18488cd90ef10d4d920abf347636f61a3e80d1a4b8a406005c917392f733b61a',
 'seo-config.php': 'efb78a7837f762b4992856903aafa0dad00449637a99bc668165e20bf2de9482',
 'seo-content-catalog-v1.php': 'a599fae60fcc66c17212ebbb8b3bcf96590daa89cb65fe67e1d49b1362ea1c6a',
 'seo-content-pilot-alanya-v1.php': 'ed59774abe650395b7b967bbdd716233a53abef7a2b6569e7dee27115e513dcf',
 'seo-content-pilot-antalya-v1.php': '705b5160af29eb06d6164c0b1eea5214bec28079c393b08776e78083980d9f96',
 'seo-content-pilot-belek-v1.php': '3633b7c29e8e3e034c6c7f5c1eeb4973d1ea393fcbc2125d1cb62aff7787d9c5',
 'seo-content-pilot-egypt-hotels-v1.php': 'b40b4b5b4bd5bb70ea9a30c423e136c49b07856f371ca29ca4e1530c88f90fd8',
 'seo-content-pilot-egypt-v1.php': 'cb692984dcbeb5fa30309098bfcfb2f2ca4700ec27c1c0387e087ad14882dd7c',
 'seo-content-pilot-kemer-v1.php': '70ddce168151614a31f497db481192e3a36dc6eac8497253ea811ee857f30315',
 'seo-content-pilot-maldives-hotels-v1.php': '2363046ca2c2133baf4806019caf922365066fe587ec9bcfe3f87a35abb0848b',
 'seo-content-pilot-maldives-v1.php': '5407ef0ac3b6650467d33c561a2eaf894e13eba1ed7ca8bc36c3a5e41a6610e8',
 'seo-content-pilot-side-v1.php': '0c8a6bb3f613206a574050677bf7799fb23382e3f330ee83610dd5c8a26d326d',
 'seo-content-pilot-turkey-v1.php': '86275866eb8427bfdb753208340d137a2b23fd1540ffbe4b7ead0bbf73d5ad0b',
 'seo-core-month-matrix-v1.php': 'a9876806a7ead25f4420d737129381b2c24cfe0eed0a2d24c6bc9b2c3f68c53a',
 'seo-core-month-navigation-v1.php': '36383bf64d418b70c51f3036ce0dabc230ba4268df9c152a0abe49e284c21212',
 'seo-core-resort-launch-state-v1.php': 'b636eb8d76072c6afe710e93228f78f82a0cb6fbac8ef125365a42ddadb94960',
 'seo-editorial-reference-v1.css': 'f3737ccf5a54ba15dd974f060971974027b2b31ba6252f2290dca0cb4a2ab8b0',
 'seo-hotel-launch-readiness-v1.php': 'c1bf44d35e5758f1cdf91d178d29bc6fb1300eabaaf07efd5597f34e87058302',
 'seo-hotel-tour-page-v1.php': '3d32fe1a1ab21a4a1ba470e1f0422ad125066035ea35a885199515690a266d1e',
 'seo-internal-links-v1.php': '14ce420034764af0b68d64e222a32007bf6042d329ef783173fb7772ebafaa0c',
 'seo-launch-slice-v1.php': '03238f3e98a10092384416e6585b088362c36efab1f99b4f0c259dce1f8ee071',
 'seo-offer-snapshot-v1.php': '673a177ec6da6e75501951d1e1819214eff3a2b7d924a7915c75f66ca090fd24',
 'seo-page-contract-v1.php': 'c553d42231d70eb2fb14a2f5c64d60e258db03277e2c548dd4c54ed7499015f6',
 'seo-page-graph-v1.php': '33293bc717f329e67c57fef5b11954e43e9d1f2ac9f4c41c80387847cca1c62b',
 'seo-page-launch-readiness-v1.php': '5a0172a28f304b28d050e4277e5af2a4ad32359b932d41c1a390999cd29aea52',
 'seo-page-primitives-v1.php': '91fd195d006c6579922280d37496e4d9ae523d962936cea088b02a7875574e0a',
 'seo-page-registry-v1.php': 'a59a4b178e160d019d18de487e2acd5c9f3b0ae6c506206f4569718bf31eb7e7',
 'seo-page-types-v1.php': '28d3250b7f35762960ec8fcf400c0451ba6d658c05e4aed5462baa177686a7c4',
 'seo-price-calendar-v1.css': 'ceaac69fd59f33752c17615fcb77439598921c4504be2e954e00922adb4d66a6',
 'seo-price-calendar-v1.php': 'e0d2c0c1ed66cae411950b15a739fbb01ea944224787bc01b6c242f36562fc5f',
 'seo-price-promo-v1.css': 'b2847f22869b4942ed57db02a447cdf24352709edd1d94d555ed9797e02c96fa',
 'seo-publication-manifest-v1.php': 'd18280435b6766474bd29d887c45909541067e14b25d41541bc4789016260b37',
 'seo-publishability-v1.php': '75fa938a9af159201db964fc358fef034ec65f5f3a71cf5b1d82612025beb597',
 'seo-resort-family-integrity-v1.php': '99d0701a5dc51ab9d050f4ef9e0ccfdd94b4ad2b015b9aa658e6add124e6c673',
 'seo-resort-page-v1.php': 'e8b5ee7fd6d38929787b1889506ee7e67be252c6182ab9c3ef68cca89602f2e4',
 'seo-seasonal-content-evidence-v1.php': '38dc6501b1638ee86398f3d176f55d22ac71b028e8affb1b107a39d2f84196df',
 'seo-seasonal-family-registry-v1.php': 'ce8b3486b2568af1130e9cce2fa24ba129d1f5675d3a0797d30c254529ac6597',
 'seo-seasonal-offer-snapshot-v1.php': 'db06d599fa30f462e0898dc83dc93395aefb4c4a829632741f9e3f7069c1e4a9',
 'seo-seasonal-page-v1.php': 'c4dda323f33f80b7b7e969826fed1eb00fdfb9ec33eeedeb20e6d3349a69c067',
 'seo-seasonal-review-content-v1.php': '7ee0e31b35ae43170ab5628679a955a6fd375c499aeca3e020c51315756899f5',
 'seo-seasonal-review-page-v1.php': '770e9e49b7d0e31cd315e435c60dcb2c34d0c98fc3a27fc19170c979b9492732',
 'seo-seasonal-source-registry-v1.php': '53a14324e881877267e317df3419f0c1d35a618c6bd50a4650d43b274c0ed213',
 'seo-seasonal-verified-content-evidence-v1.php': 'f6dbfa30a9dd77182c89ce2e94df07df66c2ead0dcd3a207ee97574413a20b19',
 'seo-sitemap-candidates-v1.php': 'f27ca5deb6cdb2ab7d1774ebf1e59b1f90b65f1423ec9b3a9e7e714ae3ead99d',
 'seo-structured-data-v1.php': '2976fe0b44e64c3552f1ba929a071408e29b5ff9fb7d902649e9ea591c9d3ec3',
 'shared-content-primitives-v1.css': '99ec994272255f59891c51506f7ae4eec7c68e6dbb366fdfe4d41ad356798d8e',
 'site-coherence-v1.css': '4ff92f81974c2945b05db9c5b44ef1893a27da7ff1cfc11032afa1bea9778e65',
 'site-footer-v1.css': 'a93168eb578ff26105fd467c5261ccdfe9c12a40323707dbdd75310d3ca44bab',
 'site-footer-v1.php': 'bf21442d0012437ef35c6a014a232e0e262f958cc238960c3a16b473ff3ba71d',
 'site-header-v2.css': '87654b6903e148306ff27ddf9453d608f8401089240192269cefe79c2607d777',
 'site-header-v2.php': '682a3fb907c8e6ac350a42822ba943097f4e42a277e6c489195724ea50619dd8',
 'site-page-v1.css': '579e8a261e4ae3dbf7c3b07bbf7f92883504555a1519d6483bcb15b2ed598ea2',
 'site-shell-alignment-v1.css': '65906a6f1243bdc0b55cef2bf70dd753509382e0614eb0b0357b7819b486a170',
 'tour-controller-v4.js': 'ad2e5e1e3e7c4d021d291cbb8a60748d8d9a54a7c1c1e3d4aefb7296d427c0ae',
 'tour-design-v1.css': 'b0305cd4f85e030b75de91d1e01d682a81e29648593beb791323c40411693799',
 'unpriced-flight-price-reset-v1.js': 'b81373bc8283bacef64e456fcc3001f838795f4ff7a229eb3c96d1ec2bc876a5',
 'url-primary-catalog-sync-v1.js': '00fdfc7c042d304c9b5758c9cfa86e86dfbfb109f4137f97da6d3a9208ee9cef'}
SITE_OPTIONAL_DEPENDENCIES = {'seo-content-pilot-egypt-coral-hills-v1.php': 'fdbe4a1750df9e0d0685ac0986f5339766df6211130cbee1c2a105812e792a51',
 'seo-content-pilot-egypt-dexon-roma-v1.php': 'abc97e880a434203fef550f3abd4011555c8e593e2f9569c5393c6bf7fa61fdb',
 'seo-content-pilot-egypt-ecotel-dahab-v1.php': '31d39cfb822f0e7ae1956f19608d70a39f8de658103b82677940572fd2116063',
 'seo-content-pilot-egypt-eden-rock-v1.php': 'd9a3d246053b63ee2fe1da89597e0869798224eb86ce4c6f495e25c453805a5b',
 'seo-content-pilot-egypt-el-khan-v1.php': '2ced15070842a91d9aa0be5a039cc690492c6ad00b61d507718dc2e53f48d34f',
 'seo-content-pilot-egypt-empire-aqua-v1.php': 'b97274c753e11e6b7710c6a724f0f2e3928d08f8a0c556444ed1f773bd296cd7',
 'seo-content-pilot-egypt-falcon-naama-v1.php': 'd1464f6662593ed28cd8eea28f7cf7c698ac7dd97ee34e2e9b627ec770747e7e',
 'seo-content-pilot-egypt-faraana-v1.php': 'cc057c18f48b612c76b9ada305e59c8f5e07702d9cebba926b71122ef45598d6',
 'seo-content-pilot-egypt-geisum-v1.php': '78e488d20f0c0654824e9138ff115ebd1d2276dd79092758d81068fac96be8dd',
 'seo-content-pilot-egypt-il-mercato-v1.php': '53ef6abd34ca68ebe584a6f5245d89d27695e7ac8418af62ee31bbd52ce9d729',
 'seo-content-pilot-egypt-kiroseiz-v1.php': '81b827ec19842595a6eb9ee0b3491e92e99e80046a842e1186e347410199e9a7',
 'seo-content-pilot-egypt-mazar-v1.php': '2bc500ea19f5f1705cdc5a93a96b41d7b488df2ed7da7157acccd89d42b795d5',
 'seo-content-pilot-egypt-mirage-bay-v1.php': '855e08a82969831ea5435442febd1592fc2759cc1d3735adfeea566618554084',
 'seo-content-pilot-egypt-new-badawia-v1.php': '2cc499876e4a7ea8216259df6b70e78d424e73d0b20e2b865039ab6767da87e8',
 'seo-content-pilot-egypt-old-vic-v1.php': 'fb95e3a28fe1258c89c3e0c6bb5f2ef32fc7c07c01f99f3f4b8457f7c98c0516',
 'seo-content-pilot-egypt-onatti-v1.php': 'f4f6f2dc6f018f533b33ac2bbaba4254c04e2a720aeb09a33cb4fc0327a50945',
 'seo-content-pilot-egypt-panorama-naama-v1.php': '56b0c6e063a1ed6f014e89b0dfbd0b8fa104262ef9978034e6d153c77c9e8522',
 'seo-content-pilot-egypt-parrotel-aqua-v1.php': 'ca5a8087b15e45206b7e44d4c2e8bffacf62509da049bfb0488097153a9cd2fb',
 'seo-content-pilot-egypt-parrotel-beach-v1.php': 'f40fda80976efbfc9645ed1042038ac282fd072860cb82ee45de46a8ce71750b',
 'seo-content-pilot-egypt-parrotel-lagoon-v1.php': '7b219da8ef05ed2f22d894f358eb537a76538f9b9285ba466a09d3234d04c306',
 'seo-content-pilot-egypt-rehana-royal-v1.php': '382eb5a8e30ba3d3b11f3586ebbb1d15d4007e73ab9f99d71ec3fd0fdaeaeee7',
 'seo-content-pilot-egypt-rehana-sharm-v1.php': '3b705357a0a808dffc3c134399e25d68047b701564c8b377e991d6ef90389299',
 'seo-content-pilot-egypt-retac-qunay-v1.php': '9fa46d9a7820e8bd828f8f0d1470d1a959bf9ff3543444debdd6b4d5d8104e04',
 'seo-content-pilot-egypt-sand-beach-v1.php': '01636aabf32e4fe0a26b3cebc965fbc03697650049f35b1c042116354dd5c2e1',
 'seo-content-pilot-egypt-sun-sea-v1.php': '1e594ae7aaf0cf7a650c78d8221fa8c2a5a1d0f453b500fafe2dc235ebc5e7f7',
 'seo-content-pilot-egypt-swiss-heaven-v1.php': 'f676a565d071600c1c306062231d3c9dd530766ad6349b3b60f2f81aeb46a45c',
 'seo-content-pilot-egypt-tivoli-v1.php': 'f829cbca40d9d495ab03e34903772b4450c943942f44429ca90d6366973cd8a6',
 'seo-content-pilot-egypt-tropitel-dahab-v1.php': '47e8b551f2e2a0884221b9fe824781087b72ca9e92443dfa4ac110f441d07b59',
 'seo-content-pilot-egypt-uni-sharm-v1.php': '238dea1e0cd8bca848ec3cd823f0f318e616d0829639124d5e4808fd6639ed28',
 'seo-content-pilot-egypt-verginia-v1.php': 'a596848023fda14df93b1b59bbb30553c7406cc4ca2fae674f0b5b0857201f42',
 'seo-content-pilot-egypt-viking-v1.php': '5d8d4650011f2eaaebb983d15ab1d5a82b6d0ba5c8299a0158de52b4a4e10d47',
 'seo-content-pilot-egypt-viva-blue-v1.php': '97e272120decdaae62762dfeae5f67ce7d59daab2ec545627bf81334fa597868',
 'seo-content-pilot-egypt-viva-sharm-v1.php': '274fadb1bde7591432b097c30c3f4689fcda6389913c3c1529181adf9d7fd8e3',
 'seo-content-pilot-maldives-angsana-velavaru-v1.php': '4509f860090132fd10eed8f2fae9d03e843a87173d69fae343f7beff552c53ce',
 'seo-content-pilot-maldives-avani-fares-v1.php': '271fdd2227d152e21241de1324cb3d7c45fbf05ad3e434db4cce01dc1b33510e',
 'seo-content-pilot-maldives-ayada-v1.php': 'ce071fcdb749685e02892f3a4c341f5180c9d11c04302747be3552ce9e87dd3e',
 'seo-content-pilot-maldives-banyan-vabbinfaru-v1.php': '35ee28b5d6e35b66143648cc3c928ec050d9b6e59ac74a985499971afbeed196',
 'seo-content-pilot-maldives-barcelo-nasandhura-v1.php': 'ef36aef49a473e5f5988a39efd794970ee96408fb091baefc9889f2d2a846fdb',
 'seo-content-pilot-maldives-brennia-kottefaru-v1.php': '464956f8f4995ff8157346871ddf4b23e84d6a58d4498314f84e3cd6b6519887',
 'seo-content-pilot-maldives-cinnamon-hakuraa-v1.php': 'd1dd847773ae16856462622e406399a3f24e8ab158a630b5e3eff09ee1ce88ab',
 'seo-content-pilot-maldives-coco-bodu-hithi-v1.php': 'e9f2a3756077a4322ba57bb74d331f959e4b4869e39d1218aab6966768a1f925',
 'seo-content-pilot-maldives-dhawa-ihuru-v1.php': '9fde4025040a80fe50d3969a12f8311fddc61aaaa1736ae1c93a29c5557741f0',
 'seo-content-pilot-maldives-furaveri-v1.php': '53046d6c5b3c4bebe69d6e965954171c9a65ad6b3f5eb9774f766b15e6a72d27',
 'seo-content-pilot-maldives-grand-park-kodhipparu-v1.php': 'f167c6b59a46e21e887fab3683f8dc0d9510bad7bcf2ea681bf81fdfddb2f742',
 'seo-content-pilot-maldives-hard-rock-v1.php': '786607d3bf48ea485ebaebe9127baf7adc81a7ff3b5105cb1f0f643bed38e8c3',
 'seo-content-pilot-maldives-ifuru-v1.php': '95699a3ac2daa3c40602bfc6dc67ed26e9adc71009d1b3419c7bcf8cf553e008',
 'seo-content-pilot-maldives-kagi-v1.php': '889121502b6e05034fc8fe1bfe91067a8f396eecef701b2dd5da07407bacd9e6',
 'seo-content-pilot-maldives-kandima-v1.php': 'e52f45b4f1ff48191bb1ca120233ec9116f8e1c67a2b5198946540f20e6f990e',
 'seo-content-pilot-maldives-kurumba-v1.php': 'e60ae59e462318a3f9044c812f859dfbd5d26caab27402e78bc3af98966f9502',
 'seo-content-pilot-maldives-lux-south-ari-v1.php': '15c6ac3863b029ca99f681b3f7a03ed106093b33c8a266421c4952f7cccb4800',
 'seo-content-pilot-maldives-machchafushi-v1.php': 'b44cca93cc9303cd08bcddc657d4eea8ed44d96eecc5b9af64cd20e738a27d03',
 'seo-content-pilot-maldives-nh-reethi-v1.php': '2fafcc238b8aad54c14e306c36099e5be637e134bb9dfa8489fdd3c59918ada4',
 'seo-content-pilot-maldives-nika-v1.php': '75bf019fe5c94ad7584c6bde854632719c2236d7956446cdb00f5cd21595e704',
 'seo-content-pilot-maldives-nooe-kunaavashi-v1.php': '1884b02da84f9c9e79e797a366a0f09366246982c8ba40d50d34d7c53224bc24',
 'seo-content-pilot-maldives-royal-island-v1.php': '4b4533ca01b3ce9c55057fc33707482abaf432d4a81006ca0294faef295dbe47',
 'seo-content-pilot-maldives-saii-lagoon-v1.php': '2e9d46513eb5ad92f2841c3e6a0b320dd1d439aa379973fd0cde7f8942658740',
 'seo-content-pilot-maldives-sheraton-v1.php': '9154335fde115027cb81843fe4197353b1ed89d76051527361254f1fbb33acb0',
 'seo-content-pilot-maldives-taj-coral-reef-v1.php': '703ea81ebe09dc3e9232fd0c40b965660952eab79527fabafb07e628472db7e9',
 'seo-content-pilot-maldives-velassaru-v1.php': '569fccde913cb76e5288fc5aad99c57d4bbd317ef20db86192c095029919bd11',
 'seo-content-pilot-maldives-villa-nautica-v1.php': 'ba331dc9f68b6d63a8600ad52e6d42a538d641bc45f02b0aedcfaf5e6aadab21',
 'seo-content-pilot-maldives-villa-park-v1.php': 'bb925aca2e2dcfc1ec83c5a992fa0143d7ce20e1a5f7fbbf8f9bed9cbcf3c5d9',
 'seo-content-pilot-maldives-westin-v1.php': 'd477c8c9547bd89f30d903b1cfe601fd45abeed9a5f7d0466faa697395195b45'}


def site_parent_identity():
    return {k: SITE_PARENT[k] for k in ('control_source_sha','run_id','command_comment_id')}


def site_policy():
    policy = {'operation': SITE_OPERATION, 'source': SITE_SOURCE, 'tree': SITE_TREE,
              'order': SITE_ORDER, 'targets': SITE_TARGETS, 'dependencies': SITE_DEPENDENCIES,
              'optional_dependencies': SITE_OPTIONAL_DEPENDENCIES, 'parent': SITE_PARENT}
    return policy | {'manifest': sha(json.dumps(policy, sort_keys=True, separators=(',', ':')).encode())}


def site_event_identity(event, env, rollback=False):
    # Apply exactly the existing carrier's owner/event/attempt gate, then parse
    # only these two fixed commands. The event body is never passed to a shell.
    prefix, operation = (SITE_ROLLBACK_PREFIX, SITE_ROLLBACK_OPERATION) if rollback else (SITE_PREFIX, SITE_OPERATION)
    control = env.get('GITHUB_SHA', '')
    body = event.get('comment', {}).get('body', '')
    original_run = None
    if rollback:
        match = re.fullmatch(re.escape(prefix+' '+control+' ') + r'([1-9][0-9]{0,19}) ' + re.escape(operation), body)
        need(match is not None, 'command'); original_run = match[1]
    else: need(body == f'{prefix} {control} {operation}', 'command')
    synthetic = dict(event); synthetic['comment'] = dict(event.get('comment', {}))
    synthetic['comment']['body'] = f'{UPDATE_PREFIX} {control} {UPDATE_OPERATION}'
    identity = update_event_identity(synthetic, env)
    need(identity['run_id'] != SITE_PARENT['run_id'], 'site_runtime_identity')
    if rollback:
        need(original_run not in (identity['run_id'], SITE_PARENT['run_id'], UPDATE_PARENT['run_id'], CONTINUATION_PARENT['run_id']), 'command')
        return identity, original_run
    return identity


def site_final_heads(identity):
    rollback_main(identity)
    pr = api('/pulls/' + str(SITE_SOURCE_PR))
    need(pr.get('state') == 'closed' and pr.get('merged') is True
         and pr.get('merge_commit_sha') == identity['control_source_sha']
         and pr.get('head', {}).get('sha') == SITE_SOURCE and pr.get('head', {}).get('repo', {}).get('full_name') == REPO
         and pr.get('base', {}).get('ref') == 'main' and pr.get('base', {}).get('repo', {}).get('full_name') == REPO, 'site_candidate_moved')
    for path in SITE_ORDER:
        row = api('/contents/v2/' + path + '?ref=' + identity['control_source_sha'])
        need(row.get('type') == 'file' and row.get('sha') == SITE_TARGETS[path]['blob'], 'site_merged_blob')


def site_parent_admission():
    parent = SITE_PARENT
    run = api('/actions/runs/' + parent['run_id'])
    need(run.get('id') == int(parent['run_id']) and run.get('head_sha') == parent['control_source_sha']
         and run.get('head_branch') == 'main' and run.get('path') == WORKFLOW and run.get('event') == 'issue_comment'
         and type(run.get('run_attempt')) is int and run['run_attempt'] == 1
         and run.get('status') == 'completed' and run.get('conclusion') == 'success'
         and run.get('actor', {}).get('login') == run.get('triggering_actor', {}).get('login') == 'pyatkoff', 'site_parent_run')
    jobs = [j for j in pages('/actions/runs/' + parent['run_id'] + '/attempts/1/jobs', 'jobs') if j.get('name') == 'public-install']
    need(len(jobs) == 1 and jobs[0].get('id') == parent['job_id'] and jobs[0].get('run_id') == int(parent['run_id'])
         and jobs[0].get('status') == 'completed' and jobs[0].get('conclusion') == 'success', 'site_parent_job')
    steps = [s for s in jobs[0].get('steps', []) if s.get('name', '').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps) == 1 and steps[0].get('status') == 'completed' and steps[0].get('conclusion') == 'success', 'site_parent_step')
    artifact = api('/actions/artifacts/' + str(parent['artifact_id']))
    need(artifact.get('id') == parent['artifact_id'] and artifact.get('name') == 'search3-public-install-'+parent['run_id']+'-1'
         and artifact.get('expired') is False and artifact.get('workflow_run', {}).get('id') == int(parent['run_id'])
         and artifact.get('digest') == 'sha256:' + parent['artifact_sha256'], 'site_parent_artifact')
    _unique_update_command(pages('/issues/4709/comments'), UPDATE_PREFIX, UPDATE_OPERATION, site_parent_identity())


def site_admission(identity):
    site_final_heads(identity); reviewed_control(identity['control_source_sha'])
    need(api('/git/commits/' + SITE_SOURCE).get('tree', {}).get('sha') == SITE_TREE, 'site_source_tree')
    gate = api('/contents/.github/workflows/deploy-anytoour.yml?ref=' + identity['control_source_sha'])
    need(gate.get('type') == 'file' and gate.get('encoding') == 'base64'
         and sha(base64.b64decode(gate['content'])) == SITE_PUBLISHER_GUARD_SHA, 'site_publisher_guard')
    checks = pages('/commits/' + SITE_SOURCE + '/check-runs', 'check_runs')
    for name in (*SITE_CHECKS, 'production-evidence'):
        latest = max((c for c in checks if c.get('name') == name and c.get('head_sha') == SITE_SOURCE), key=lambda c: c['id'], default={})
        need(latest.get('status') == 'completed' and latest.get('app', {}).get('slug') == 'github-actions'
             and latest.get('conclusion') == ('skipped' if name == 'production-evidence' else 'success'), 'site_source_ci')
    for path in SITE_ORDER:
        row = api('/contents/v2/' + path + '?ref=' + SITE_SOURCE)
        need(row.get('type') == 'file' and row.get('sha') == SITE_TARGETS[path]['blob'], 'site_source_blob')
    site_parent_admission()
    _unique_update_command(pages('/issues/4709/comments'), SITE_PREFIX, SITE_OPERATION, identity)
    site_final_heads(identity)


def site_parent_value(value):
    # Preserve the accepted parent's exact JSON proof in the SITE receipt. A
    # later rollback needs no old artifact, source CI, release or signer fetch.
    data = (json.dumps(value, sort_keys=True) + '\n').encode()
    need(sha(data) == SITE_PARENT['receipt_sha256'], 'site_parent_digest')
    packet = io.BytesIO()
    with zipfile.ZipFile(packet, 'w') as archive: archive.writestr('evidence.json', data)
    policy, accepted, original = update_source_evidence(packet.getvalue(), site_parent_identity())
    need(accepted['snapshot_manifest_sha256'] == SITE_PARENT['snapshot_manifest_sha256'], 'site_parent_snapshot')
    return policy, accepted, original


def site_parent_evidence(raw):
    need(sha(raw) == SITE_PARENT['artifact_sha256'], 'site_parent_artifact_digest')
    with zip_files(raw) as archive:
        need(archive.namelist() == ['evidence.json'], 'site_parent_artifact')
        data = archive.read('evidence.json'); need(sha(data) == SITE_PARENT['receipt_sha256'], 'site_parent_digest')
    value = json.loads(data); site_parent_value(value)
    return value


def site_package():
    files = {}
    for path in SITE_ORDER:
        expected = SITE_TARGETS[path]; blob = api('/git/blobs/' + expected['blob'])
        need(blob.get('sha') == expected['blob'] and blob.get('encoding') == 'base64'
             and blob.get('size') == expected['after']['size'], 'site_payload_blob')
        raw = base64.b64decode(blob['content'].replace('\n', ''), validate=True)
        need(0 < len(raw) <= MAX_BYTES and raw.startswith(b'<?php')
             and expected['after'] == {'state':'present','sha256':sha(raw),'size':len(raw),'mode':'0644'}
             and hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest() == expected['blob'], 'site_payload_bytes')
        files[path] = base64.b64encode(raw).decode()
    return files


SITE_COMMON_REMOTE = r'''
UPDATE_RECORD_PATHS=('reservation.json','result.json','snapshot/manifest.json','snapshot/app','intent.json')
SITE_PREPARED_PATHS=('reservation.json','snapshot/manifest.json')+tuple(p+str(i) for i in range(4) for p in ('snapshot/','drill/','intent-'))
def site_read_records(store,paths):
 rows={}
 for path in paths:
  row,identity,raw=store.read(path,True)
  mode='0644' if path.startswith(('snapshot/','drill/')) and path!='snapshot/manifest.json' else '0600'
  require(row.get('state')=='present' and row.get('mode')==mode and identity[6]==os.getuid(),'site_private_record')
  rows[path]=(row,identity,raw)
 return rows
def site_parent_records():
 retained=parent_records();candidate=parent_candidate(retained,True);protected=parent_protected(retained)
 rows=read_records(updated,UPDATE_RECORD_PATHS);record=lambda p:json.loads(rows[p][2]);path=UPDATE_POLICY['path']
 require(record('reservation.json')=={'operation':UPDATE_POLICY['operation'],**UPDATE_ORIGINAL,
         'source':UPDATE_POLICY['source'],'manifest':UPDATE_POLICY['manifest'],'parent_install':ORIGINAL}
         and record('result.json')==UPDATE_ACCEPTED,'update_original_identity')
 snapshot=record('snapshot/manifest.json')
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_install','path','before','protected','previous_identity','parent_records'}
         and snapshot['source']==UPDATE_POLICY['source'] and snapshot['manifest']==UPDATE_POLICY['manifest']
         and snapshot['parent_install']==ORIGINAL and snapshot['path']==path and snapshot['before']==POLICY['expected']
         and snapshot['protected']=={p:r[0] for p,r in protected.items()} and snapshot['parent_records']==parent_hashes(retained)
         and digest(rows['snapshot/manifest.json'][2])==UPDATE_ACCEPTED['snapshot_manifest_sha256']
         and rows['snapshot/app'][0]==UPDATE_POLICY['before'],'update_snapshot_conflict')
 old=checked_stamp(snapshot['previous_identity'],UPDATE_POLICY['before'])
 require(old[:-1]==parent_intent(path,retained)[:-1],'update_parent_inode')
 prior=updated.read('stage/app')[:2]
 require(prior[0]==UPDATE_POLICY['before'] and prior[1][:-1]==old[:-1],'update_snapshot_conflict')
 intent=record('intent.json')
 require(isinstance(intent,dict) and set(intent)=={'path','before','after','previous_identity','new_identity'}
         and intent['path']==path and intent['before']==UPDATE_POLICY['before'] and intent['after']==UPDATE_POLICY['after']
         and intent['previous_identity']==snapshot['previous_identity'],'update_private_record')
 new=checked_stamp(intent['new_identity'],UPDATE_POLICY['after'])
 require(candidate[path][1][:-1]==new[:-1],'rollback_candidate_changed')
 dependencies=public.inventory(tuple(SITE_POLICY['dependencies'])+tuple(SITE_POLICY['optional_dependencies']))
 for p,(row,_) in dependencies.items():
  expected=SITE_POLICY['dependencies'].get(p,SITE_POLICY['optional_dependencies'].get(p))
  require((p in SITE_POLICY['optional_dependencies'] and row=={'state':'absent'})
          or row.get('state')=='present' and row.get('sha256')==expected and row.get('mode')=='0644','site_dependency_drift')
 return retained,rows,prior,{**candidate,**protected,**dependencies}
def site_check_parents():
 require(parent_records()==retained and read_records(updated,UPDATE_RECORD_PATHS)==update_records
         and updated.read('stage/app')[:2]==update_prior,'site_parent_changed')
 require(public.inventory(guarded)==guarded,'protected_changed')
def site_check_targets(expected):
 require(public.inventory(SITE_POLICY['order'])==expected,'site_target_changed')
def site_restore_one(store,path,index,current,prior,capture):
 require(public.read(path)[:2]==current and store.read('stage/'+str(index))[:2]==prior,'rollback_preflight_race')
 capture_owned(public,private,path,capture,current[0],current[1])
 restore_capture(public,store,path,'stage/'+str(index))
 restored=public.read(path)[:2]
 require(restored[0]==prior[0] and restored[1][:-1]==prior[1][:-1],'rollback_incomplete')
 return restored
'''


SITE_UPDATE_REMOTE = r'''
result={'schema_version':1,'operation_id':SITE_POLICY['operation'],'source_sha':SITE_POLICY['source'],
        'manifest_sha256':SITE_POLICY['manifest'],**IDENTITY,'parent_update':UPDATE_ORIGINAL,
        'parent_receipt_sha256':SITE_POLICY['parent']['receipt_sha256'],'status':'blocked_no_replay','phase':'preflight',
        'supplier_calls':0,'database_reads':0,'database_writes':0,'lead_calls':0,'booking_calls':0,'application_calls':0,
        'written_files':[],'rollback_files':[],'reservation_created':False,'snapshot_verified':False,
        'restore_drill_verified':False,'protected_unchanged':False,'unchanged_files_verified':False,
        'parent_records_unchanged':False,'original_inodes_verified':False,'snapshot_manifest_sha256':None}
public=base=original=updated=private=None;reserved=False;switched=[];owned={};prior={};prepared=None
try:
 require(hasattr(os,'O_PATH') and hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY')
         and hasattr(ctypes.CDLL(None),'renameat2'),'unsupported_platform')
 order=SITE_POLICY['order'];targets=SITE_POLICY['targets']
 require(len(order)==4 and set(PAYLOAD)==set(targets)==set(order)
         and not set(order)&(set(POLICY['expected'])|set(checked_protected_paths())|set(SITE_POLICY['dependencies'])|set(SITE_POLICY['optional_dependencies'])),'payload_scope')
 payload={p:base64.b64decode(PAYLOAD[p],validate=True) for p in order}
 require(all(targets[p]['after']=={'state':'present','sha256':digest(raw),'size':len(raw),'mode':'0644'}
             and raw.startswith(b'<?php') and 0<len(raw)<=MAX_BYTES for p,raw in payload.items()),'payload_bytes')
 home=os.environ.get('HOME','');public=Files(home+'/www/anytoour.ru')
 base=Files(home+'/.anytoour-search3-root-gateway');private_root(base)
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation']);private_root(original)
 updated=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_POLICY['operation']);private_root(updated)
 require(os.fstat(base.fd).st_dev==os.fstat(public.fd).st_dev,'different_filesystem')
 retained,update_records,update_prior,guarded=site_parent_records()
 before=public.inventory(order)
 require(all(row[0]==targets[p]['before'] and row[1][6]==os.getuid() for p,row in before.items()),'predecessor_drift')
 site_check_parents();site_check_targets(before)
 try: os.mkdir(SITE_POLICY['operation'],0o700,dir_fd=base.fd)
 except FileExistsError: raise RuntimeError('operation_exists_no_replay') from None
 reserved=True;result['reservation_created']=True;sync(base.fd)
 private=Files(home+'/.anytoour-search3-root-gateway/'+SITE_POLICY['operation'],('snapshot','drill','stage','rollback'),0o700);private_root(private)
 reservation={'operation':SITE_POLICY['operation'],**IDENTITY,'source':SITE_POLICY['source'],
              'manifest':SITE_POLICY['manifest'],'parent_update':UPDATE_ORIGINAL}
 private.create('reservation.json',canonical(reservation));result['phase']='snapshot'
 snapshot={'source':SITE_POLICY['source'],'manifest':SITE_POLICY['manifest'],'parent_update':UPDATE_ORIGINAL,
           'before':{p:[row,list(identity)] for p,(row,identity) in before.items()},
           'guarded':{p:row[0] for p,row in guarded.items()},'parent_records':parent_hashes(retained),
           'update_records':parent_hashes(update_records)}
 for index,path in enumerate(order):
  row,identity,raw=public.read(path,True);require((row,identity)==before[path],'predecessor_drift')
  private.create('snapshot/'+str(index),raw,0o644)
  require(private.read('snapshot/'+str(index))[0]==targets[path]['before'],'snapshot_invalid')
 private.create('snapshot/manifest.json',canonical(snapshot))
 require(private.read('snapshot/manifest.json',True)[2]==canonical(snapshot),'snapshot_invalid')
 result['snapshot_manifest_sha256']=digest(canonical(snapshot));result['snapshot_verified']=True
 for index,path in enumerate(order):
  private.create('drill/'+str(index),private.read('snapshot/'+str(index),True)[2],0o644)
  require(private.read('drill/'+str(index))[0]==targets[path]['before'],'restore_drill_failed')
 result['restore_drill_verified']=True
 for index,path in enumerate(order):
  private.create('stage/'+str(index),payload[path],0o644);owned[path]=private.read('stage/'+str(index))[:2]
  require(owned[path][0]==targets[path]['after'],'payload_bytes')
  private.create('intent-'+str(index),canonical({'path':path,'before':targets[path]['before'],'after':targets[path]['after'],
                 'previous_identity':list(before[path][1]),'new_identity':list(owned[path][1])}))
 prepared=site_read_records(private,SITE_PREPARED_PATHS)
 require(prepared['reservation.json'][2]==canonical(reservation) and prepared['snapshot/manifest.json'][2]==canonical(snapshot)
         and all(prepared['snapshot/'+str(i)][0]==prepared['drill/'+str(i)][0]==targets[p]['before']
                 and json.loads(prepared['intent-'+str(i)][2])=={'path':p,'before':targets[p]['before'],'after':targets[p]['after'],
                      'previous_identity':list(before[p][1]),'new_identity':list(owned[p][1])} for i,p in enumerate(order)),'snapshot_invalid')
 current=dict(before);result['phase']='assets'
 for index,path in enumerate(order):
  site_check_parents();site_check_targets(current)
  require(site_read_records(private,SITE_PREPARED_PATHS)==prepared
          and private.read('stage/'+str(index))[:2]==owned[path],'prewrite_race')
  with private.parent('stage/'+str(index)) as (src,name),public.parent(path) as (dst,target):
   require(public.read(path)[:2]==before[path],'entry_prewrite_race')
   exchange(src,name,dst,target);switched.append(path);result['written_files'].append(path);sync(src);sync(dst)
  observed=public.read(path)[:2]
  require(observed[0]==targets[path]['after'] and observed[1][:-1]==owned[path][1][:-1],'site_readback')
  owned[path]=observed;current[path]=observed
  try:
   prior[path]=private.read('stage/'+str(index))[:2]
   require(prior[path][0]==targets[path]['before'] and prior[path][1][:-1]==before[path][1][:-1],'site_exchange_conflict')
  except Exception:
   # An unexpected object raced the exchange. Return that exact captured object
   # without overwriting a newer public writer, then unwind earlier own writes.
   capture_owned(public,private,path,'rollback/conflict-'+str(index),observed[0],observed[1])
   restore_capture(public,private,path,'stage/'+str(index));switched.remove(path)
   raise RuntimeError('site_exchange_conflict')
  site_check_targets(current);site_check_parents()
 require(site_read_records(private,SITE_PREPARED_PATHS)==prepared
         and all(private.read('stage/'+str(i))[:2]==prior[p] for i,p in enumerate(order)),'site_snapshot_conflict')
 result.update(status='site_updated_verified',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
               parent_records_unchanged=True,original_inodes_verified=True)
except Exception as error:
 result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
 if reserved:
  result['status']='unknown_no_replay';recovery_ok=True
  for path in reversed(switched):
   try:
    index=order.index(path);current_row=public.read(path)[:2];saved=private.read('stage/'+str(index))[:2]
    require(current_row[0]==targets[path]['after'] and current_row[1][:-1]==owned[path][1][:-1]
            and saved[0]==targets[path]['before'] and saved[1][:-1]==before[path][1][:-1],'rollback_entry_conflict')
    site_restore_one(private,path,index,current_row,saved,'rollback/'+str(index));result['rollback_files'].append(path)
   except Exception: recovery_ok=False
  try:
   require(recovery_ok,'rollback_incomplete');site_check_parents()
   restored=public.inventory(order)
   require(all(row[0]==before[p][0] and row[1][:-1]==before[p][1][:-1] for p,row in restored.items())
           and result['rollback_files']==list(reversed(result['written_files'])),'rollback_incomplete')
   result.update(status='rolled_back_no_replay',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
                 parent_records_unchanged=True,original_inodes_verified=True)
  except Exception: result['status']='unknown_no_replay'
finally:
 if reserved and private is not None:
  try: private.create('result.json',canonical(result))
  except Exception: result.update(status='unknown_no_replay',reason='receipt_failed')
 for store in (private,updated,original,base,public):
  if store is not None: store.close()
print(json.dumps(result,sort_keys=True))
'''


SITE_ROLLBACK_REMOTE = r'''
result={'schema_version':1,'operation_id':SITE_ROLLBACK_OPERATION,'source_sha':SITE_POLICY['source'],
        'manifest_sha256':SITE_POLICY['manifest'],**IDENTITY,'parent_update':UPDATE_ORIGINAL,
        'parent_receipt_sha256':SITE_POLICY['parent']['receipt_sha256'],'original_site':SITE_ORIGINAL,
        'status':'blocked_no_replay','phase':'preflight','supplier_calls':0,'database_reads':0,'database_writes':0,
        'lead_calls':0,'booking_calls':0,'application_calls':0,'written_files':[],'rollback_files':[],
        'reservation_created':False,'snapshot_verified':False,'restore_drill_verified':False,
        'protected_unchanged':False,'unchanged_files_verified':False,'parent_records_unchanged':False,
        'original_inodes_verified':False,'snapshot_manifest_sha256':SITE_ACCEPTED['snapshot_manifest_sha256']}
public=base=original=updated=published=private=None;reserved=False
try:
 require(hasattr(os,'O_PATH') and hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY')
         and hasattr(ctypes.CDLL(None),'renameat2'),'unsupported_platform')
 order=SITE_POLICY['order'];targets=SITE_POLICY['targets'];home=os.environ.get('HOME','')
 public=Files(home+'/www/anytoour.ru');base=Files(home+'/.anytoour-search3-root-gateway');private_root(base)
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation']);private_root(original)
 updated=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_POLICY['operation']);private_root(updated)
 published=Files(home+'/.anytoour-search3-root-gateway/'+SITE_POLICY['operation']);private_root(published)
 require(os.fstat(base.fd).st_dev==os.fstat(public.fd).st_dev,'different_filesystem')
 retained,update_records,update_prior,guarded=site_parent_records()
 record_paths=SITE_PREPARED_PATHS+('result.json',);records=site_read_records(published,record_paths)
 record=lambda p:json.loads(records[p][2])
 require(record('reservation.json')=={'operation':SITE_POLICY['operation'],**SITE_ORIGINAL,
         'source':SITE_POLICY['source'],'manifest':SITE_POLICY['manifest'],'parent_update':UPDATE_ORIGINAL}
         and record('result.json')==SITE_ACCEPTED,'site_original_identity')
 snapshot=record('snapshot/manifest.json')
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_update','before','guarded','parent_records','update_records'}
         and snapshot['source']==SITE_POLICY['source'] and snapshot['manifest']==SITE_POLICY['manifest']
         and snapshot['parent_update']==UPDATE_ORIGINAL and set(snapshot['before'])==set(order)
         and snapshot['guarded']=={p:row[0] for p,row in guarded.items()}
         and snapshot['parent_records']==parent_hashes(retained) and snapshot['update_records']==parent_hashes(update_records)
         and digest(records['snapshot/manifest.json'][2])==SITE_ACCEPTED['snapshot_manifest_sha256'],'site_snapshot_conflict')
 current=public.inventory(order);prior={};old_identities={}
 for index,path in enumerate(order):
  before=snapshot['before'][path]
  require(isinstance(before,list) and len(before)==2 and before[0]==targets[path]['before'],'site_snapshot_conflict')
  old_identities[path]=checked_stamp(before[1],targets[path]['before'])
  require(records['snapshot/'+str(index)][0]==records['drill/'+str(index)][0]==targets[path]['before'],'site_snapshot_conflict')
  intent=record('intent-'+str(index))
  require(isinstance(intent,dict) and set(intent)=={'path','before','after','previous_identity','new_identity'}
          and intent['path']==path and intent['before']==targets[path]['before'] and intent['after']==targets[path]['after']
          and intent['previous_identity']==before[1],'site_private_record')
  new_identity=checked_stamp(intent['new_identity'],targets[path]['after'])
  require(current[path][0]==targets[path]['after'] and current[path][1][:-1]==new_identity[:-1],'rollback_candidate_changed')
  prior[path]=published.read('stage/'+str(index))[:2]
  require(prior[path][0]==targets[path]['before'] and prior[path][1][:-1]==old_identities[path][:-1],'site_snapshot_conflict')
 site_check_parents();site_check_targets(current)
 require(site_read_records(published,record_paths)==records,'rollback_preflight_race')
 result.update(snapshot_verified=True,restore_drill_verified=True)
 try: os.mkdir(SITE_ROLLBACK_OPERATION,0o700,dir_fd=base.fd)
 except FileExistsError: raise RuntimeError('operation_exists_no_replay') from None
 reserved=True;result['reservation_created']=True;sync(base.fd)
 private=Files(home+'/.anytoour-search3-root-gateway/'+SITE_ROLLBACK_OPERATION,('quarantine',),0o700);private_root(private)
 private.create('reservation.json',canonical({'operation':SITE_ROLLBACK_OPERATION,**IDENTITY,'original_site':SITE_ORIGINAL,
                'source':SITE_POLICY['source'],'manifest':SITE_POLICY['manifest'],'parent_update':UPDATE_ORIGINAL}))
 result['phase']='assets'
 for index in reversed(range(4)):
  path=order[index];site_check_parents();site_check_targets(current)
  require(site_read_records(published,record_paths)==records,'site_snapshot_conflict')
  current[path]=site_restore_one(published,path,index,current[path],prior[path],'quarantine/'+str(index))
  result['rollback_files'].append(path)
  site_check_targets(current);site_check_parents()
 require(all(current[p][0]==targets[p]['before'] and current[p][1][:-1]==old_identities[p][:-1] for p in order)
         and site_read_records(published,record_paths)==records,'rollback_incomplete')
 result.update(status='site_rolled_back_verified',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
               parent_records_unchanged=True,original_inodes_verified=True)
except Exception as error:
 result['status']='unknown_no_replay' if reserved else 'blocked_no_replay'
 result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
finally:
 if reserved and private is not None:
  try: private.create('result.json',canonical(result))
  except Exception: result.update(status='unknown_no_replay',reason='receipt_failed')
 for store in (private,published,updated,original,base,public):
  if store is not None: store.close()
print(json.dumps(result,sort_keys=True))
'''


SITE_REASONS = UPDATE_REASONS + ('site_private_record','site_parent_changed','site_target_changed',
    'site_dependency_drift','site_readback','site_exchange_conflict','site_snapshot_conflict','site_original_identity')


def _site_program_prefix(policy, identity, parent_value):
    need(policy == site_policy(), 'site_policy')
    update_runtime_identity(identity); need(identity['run_id'] != SITE_PARENT['run_id'], 'site_runtime_identity')
    previous, accepted, original = site_parent_value(parent_value)
    need(not set(SITE_ORDER) & (set(previous['previous']['expected']) | set(previous['previous']['protected'])
              | set(SITE_DEPENDENCIES) | set(SITE_OPTIONAL_DEPENDENCIES))
         and not set(SITE_DEPENDENCIES) & set(SITE_OPTIONAL_DEPENDENCIES), 'site_scope')
    return (_update_program_prefix(previous, identity, update_parent_identity(), original)
            + '\nSITE_POLICY=' + repr(policy) + '\nUPDATE_ORIGINAL=' + repr(site_parent_identity())
            + '\nUPDATE_ACCEPTED=' + repr(accepted)
            + '\ndef re_safe(value): return value in ' + repr(SITE_REASONS) + '\n' + SITE_COMMON_REMOTE)


def site_remote_program(policy, files, identity, parent_value):
    prefix = _site_program_prefix(policy, identity, parent_value)
    need(isinstance(files, dict) and set(files) == set(SITE_ORDER), 'site_payload')
    for path in SITE_ORDER:
        raw = base64.b64decode(files[path], validate=True)
        need(raw.startswith(b'<?php') and SITE_TARGETS[path]['after'] ==
             {'state':'present','sha256':sha(raw),'size':len(raw),'mode':'0644'}, 'site_payload_bytes')
    program = prefix + '\nPAYLOAD=' + repr(files) + '\n' + SITE_UPDATE_REMOTE
    ast.parse(program)
    return program


def site_rollback_remote_program(policy, identity, original, accepted, parent_value):
    prefix = _site_program_prefix(policy, identity, parent_value)
    update_runtime_identity(original)
    need(identity['run_id'] != original['run_id'] and identity['command_comment_id'] != original['command_comment_id']
         and valid_site_result(original, accepted), 'site_rollback_evidence')
    program = (prefix + '\nSITE_ORIGINAL=' + repr(original) + '\nSITE_ACCEPTED=' + repr(accepted)
               + '\nSITE_ROLLBACK_OPERATION=' + repr(SITE_ROLLBACK_OPERATION) + '\n' + SITE_ROLLBACK_REMOTE)
    ast.parse(program)
    return program


def valid_site_result(identity, result, original=None, accepted=None):
    rollback = original is not None
    fixed = {'schema_version':1,'operation_id':SITE_ROLLBACK_OPERATION if rollback else SITE_OPERATION,
             'source_sha':SITE_SOURCE,'manifest_sha256':site_policy()['manifest'],**identity,
             'parent_update':site_parent_identity(),'parent_receipt_sha256':SITE_PARENT['receipt_sha256']}
    if rollback: fixed.update(original_site=original,snapshot_manifest_sha256=accepted['snapshot_manifest_sha256'])
    counters = ('supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
    flags = ('reservation_created','snapshot_verified','restore_drill_verified','protected_unchanged',
             'unchanged_files_verified','parent_records_unchanged','original_inodes_verified')
    required = set(fixed) | set(counters) | set(flags) | {'status','phase','written_files','rollback_files','snapshot_manifest_sha256'}
    need(isinstance(result, dict) and required <= set(result) <= required | {'reason'}, 'site_receipt_shape')
    need(all(type(result.get(k)) is type(v) and result[k] == v for k,v in fixed.items()), 'site_receipt_identity')
    need(all(type(result.get(k)) is int and result[k] == 0 for k in counters), 'site_receipt_counters')
    need(all(type(result[k]) is bool for k in flags), 'site_receipt_flags')
    success = 'site_rolled_back_verified' if rollback else 'site_updated_verified'
    states = ('blocked_no_replay','unknown_no_replay',success) if rollback else ('blocked_no_replay','unknown_no_replay','rolled_back_no_replay',success)
    need(result['status'] in states and result['phase'] in ('preflight','snapshot','assets','complete')
         and ('reason' not in result or result['reason'] in SITE_REASONS), 'site_receipt_status')
    written, restored = result['written_files'], result['rollback_files']
    need(isinstance(written,list) and written in [list(SITE_ORDER[:i]) for i in range(5)]
         and isinstance(restored,list) and all(isinstance(p,str) for p in restored)
         and len(set(restored))==len(restored), 'site_receipt_paths')
    order = list(reversed(SITE_ORDER if rollback else written))
    need(all(p in order for p in restored) and restored == [p for p in order if p in restored]
         and (not rollback or not written), 'site_receipt_paths')
    snapshot = result['snapshot_manifest_sha256']
    need(snapshot is None or isinstance(snapshot,str) and re.fullmatch('[a-f0-9]{64}',snapshot), 'site_receipt_snapshot')
    if result['status'] == 'blocked_no_replay':
        need(not result['reservation_created'] and result['phase']=='preflight' and not written and not restored, 'site_receipt_incomplete')
    if result['status'] in (success,'rolled_back_no_replay'):
        need(result['phase']=='complete' and result['reservation_created'] and result['protected_unchanged']
             and result['unchanged_files_verified'] and result['parent_records_unchanged']
             and result['original_inodes_verified'], 'site_receipt_incomplete')
    if result['status']=='rolled_back_no_replay':
        need(restored==list(reversed(written)), 'site_receipt_incomplete')
    if result['status']==success:
        need(all(result[k] for k in flags) and snapshot is not None and 'reason' not in result
             and (restored==list(reversed(SITE_ORDER)) if rollback else written==list(SITE_ORDER) and not restored), 'site_receipt_incomplete')
    return result['status']==success


def site_rollback_commands(identity, original_run, expected_original=None):
    need(isinstance(original_run,str) and re.fullmatch('[1-9][0-9]{0,19}',original_run)
         and original_run not in (identity['run_id'],SITE_PARENT['run_id'],UPDATE_PARENT['run_id'],CONTINUATION_PARENT['run_id']), 'site_rollback_run')
    comments = pages('/issues/4709/comments')
    _unique_update_command(comments,SITE_ROLLBACK_PREFIX,SITE_ROLLBACK_OPERATION,identity,original_run)
    installed = [c for c in comments if c.get('body','').strip().split()[:1]==[SITE_PREFIX]
                 and SITE_OPERATION in c.get('body','').split()[1:]]
    need(len(installed)==1 and installed[0].get('user',{}).get('id')==226193297
         and installed[0].get('author_association')=='OWNER', 'site_rollback_command')
    match = re.fullmatch(re.escape(SITE_PREFIX)+r' ([a-f0-9]{40}) '+re.escape(SITE_OPERATION),installed[0].get('body',''))
    need(match is not None, 'site_rollback_command')
    original = {'control_source_sha':match[1],'run_id':original_run,'command_comment_id':installed[0]['id']}
    update_runtime_identity(original)
    need(expected_original is None or original==expected_original,'site_rollback_command')
    return original


def site_rollback_admission(identity, original_run):
    rollback_main(identity);reviewed_control(identity['control_source_sha'])
    original=site_rollback_commands(identity,original_run)
    run=api('/actions/runs/'+original_run)
    need(run.get('id')==int(original_run) and run.get('head_sha')==original['control_source_sha']
         and run.get('head_branch')=='main' and run.get('path')==WORKFLOW and run.get('event')=='issue_comment'
         and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')=='success'
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff','site_rollback_run')
    jobs=[j for j in pages('/actions/runs/'+original_run+'/attempts/1/jobs','jobs') if j.get('name')=='public-install']
    need(len(jobs)==1 and jobs[0].get('run_id')==int(original_run) and jobs[0].get('status')=='completed'
         and jobs[0].get('conclusion')=='success','site_rollback_job')
    steps=[s for s in jobs[0].get('steps',[]) if s.get('name','').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps)==1 and steps[0].get('status')=='completed' and steps[0].get('conclusion')=='success','site_rollback_step')
    name='search3-public-install-'+original_run+'-1'
    artifacts=[a for a in pages('/actions/runs/'+original_run+'/artifacts','artifacts') if a.get('name')==name]
    need(len(artifacts)==1 and artifacts[0].get('expired') is False
         and artifacts[0].get('workflow_run',{}).get('id')==int(original_run)
         and re.fullmatch(r'sha256:[a-f0-9]{64}',artifacts[0].get('digest','')),'site_rollback_artifact')
    rollback_main(identity)
    return original,artifacts[0]


def site_source_evidence(raw, original):
    update_runtime_identity(original)
    with zip_files(raw) as archive:
        need(archive.namelist()==['evidence.json'],'site_rollback_artifact')
        value=json.loads(archive.read('evidence.json'))
    fixed={**original,'operation_id':SITE_OPERATION,'source_sha':SITE_SOURCE,'manifest_sha256':site_policy()['manifest'],
           'read_only':False,'phase':'complete','ssh_started':True,'status':'site_updated_verified',
           'parent_update':site_parent_identity()}
    need(isinstance(value,dict) and set(value)==set(fixed)|{'remote','parent_evidence'}
         and all(type(value.get(k)) is type(v) and value[k]==v for k,v in fixed.items()),'site_rollback_evidence')
    site_parent_value(value['parent_evidence'])
    need(valid_site_result(original,value['remote']),'site_rollback_evidence')
    return site_policy(),value['remote'],value['parent_evidence']


def site_update(rollback=False):
    need(type(rollback) is bool,'mode')
    policy=site_policy();operation=SITE_ROLLBACK_OPERATION if rollback else SITE_OPERATION
    work=Path(os.environ['RUNNER_TEMP'])/'search3-public-install';work.mkdir(mode=0o700)
    key,known=work/'key',work/'known'
    evidence={'status':'blocked_no_replay','operation_id':operation,'source_sha':SITE_SOURCE,
              'manifest_sha256':policy['manifest'],'read_only':False,'phase':'identity','ssh_started':False}
    complete=False
    try:
        event=json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
        if rollback:
            identity,original_run=site_event_identity(event,os.environ,True)
            evidence.update(identity,original_site_run_id=original_run,phase='admission')
            original,artifact=site_rollback_admission(identity,original_run);evidence['phase']='artifact'
            policy,accepted,parent_value=site_source_evidence(download(artifact['id'],artifact['digest'].removeprefix('sha256:')),original)
            program=site_rollback_remote_program(policy,identity,original,accepted,parent_value)
            evidence.update(parent_update=site_parent_identity(),original_site=original)
        else:
            identity=site_event_identity(event,os.environ);evidence.update(identity,phase='admission');site_admission(identity)
            evidence['phase']='artifact'
            parent_value=site_parent_evidence(download(SITE_PARENT['artifact_id'],SITE_PARENT['artifact_sha256']))
            program=site_remote_program(policy,site_package(),identity,parent_value)
            evidence.update(parent_update=site_parent_identity(),parent_evidence=parent_value)
        # Same fixed carrier, credentials and bounded invocation as the original
        # public updater. No endpoint/application execution is added here.
        host,user=os.environ['PREVIEW_HOST'],os.environ['PREVIEW_USER']
        need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]{0,252}',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
        raw=os.environ['PREVIEW_KEY'].replace('\r','');need(0<len(raw)<=32768,'ssh_key')
        if 'PRIVATE KEY' not in raw: raw=base64.b64decode(raw,validate=True).decode()
        with key.open('x') as stream: stream.write(raw.rstrip()+'\n')
        key.chmod(0o600)
        scan=carrier.run(['ssh-keyscan','-T','15','-t','ed25519',host]);need(0<len(scan)<=8192,'host_key')
        known.write_bytes(scan);known.chmod(0o600)
        options=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','ConnectTimeout=15']
        if rollback: site_rollback_commands(identity,original_run,original);rollback_main(identity)
        else: site_admission(identity)
        evidence.update(status='unknown_no_replay',phase='remote',ssh_started=True)
        response=carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'],input=program.encode(),timeout=180)
        need(len(response)<=32768,'receipt_limit');result=json.loads(response)
        complete=valid_site_result(identity,result,original,accepted) if rollback else valid_site_result(identity,result)
        evidence['remote']=result
        if rollback: rollback_main(identity)
        else: site_final_heads(identity)
        evidence.update(status=result['status'],phase='complete')
    except Exception as error:
        complete=False
        evidence['error_type']=type(error).__name__ if type(error) in (RuntimeError,ValueError,KeyError,OSError,subprocess.CalledProcessError,subprocess.TimeoutExpired) else 'SiteUpdateError'
    finally:
        key.unlink(missing_ok=True);known.unlink(missing_ok=True)
        (work/'evidence.json').write_text(json.dumps(evidence,sort_keys=True)+'\n')
    print(json.dumps(evidence,sort_keys=True));need(complete,'site_rollback_not_verified' if rollback else 'site_update_not_verified')


SITE_INSPECT_PREFIX = '/inspect-site-shared-assets'
SITE_INSPECT_OPERATION = 'site-shared-assets-drift-44897684408-v1'
SITE_INSPECT_RECEIPT_LIMIT = 131072  # Fixed diagnostic scope; mutating receipts remain capped at 32768.
SITE_INSPECT_FAILED = {
    'operation_id': SITE_OPERATION, 'control_source_sha': '40f279501c3594925a815219d5bd0905c238c492',
    'run_id': '44897684408', 'command_comment_id': 7061696501, 'job_id': 136826736802,
    'artifact_id': 14704249355,
    'artifact_sha256': '26e40baa43c70bca5fdce3112c7b3b0c6aa06093c51849f14cd8f855fb906fab',
    'receipt_sha256': 'e800d005b4ff0c8687060cc14d07804bb086b2970c8caed6c283cc87e622498b',
    'terminal_comment_id': 7061760440,
}


def site_inspect_event_identity(event, env):
    control = env.get('GITHUB_SHA', '')
    need(event.get('comment', {}).get('body') == f'{SITE_INSPECT_PREFIX} {control} {SITE_INSPECT_OPERATION}', 'command')
    synthetic = dict(event); synthetic['comment'] = dict(event.get('comment', {}))
    synthetic['comment']['body'] = f'{UPDATE_PREFIX} {control} {UPDATE_OPERATION}'
    identity = update_event_identity(synthetic, env)
    need(identity['run_id'] not in (SITE_INSPECT_FAILED['run_id'], SITE_PARENT['run_id']), 'site_inspection_identity')
    return identity


def site_inspect_heads(identity):
    rollback_main(identity)
    pr = api('/pulls/' + str(SITE_SOURCE_PR))
    need(pr.get('state') == 'closed' and pr.get('merged') is True
         and pr.get('merge_commit_sha') == SITE_INSPECT_FAILED['control_source_sha']
         and pr.get('head', {}).get('sha') == SITE_SOURCE and pr.get('head', {}).get('repo', {}).get('full_name') == REPO
         and pr.get('base', {}).get('ref') == 'main' and pr.get('base', {}).get('repo', {}).get('full_name') == REPO, 'site_candidate_moved')
    for path in SITE_ORDER:
        row = api('/contents/v2/' + path + '?ref=' + identity['control_source_sha'])
        need(row.get('type') == 'file' and row.get('sha') == SITE_TARGETS[path]['blob'], 'site_merged_blob')


def site_inspect_admission(identity):
    site_inspect_heads(identity); reviewed_control(identity['control_source_sha'])
    failed = SITE_INSPECT_FAILED
    run = api('/actions/runs/' + failed['run_id'])
    need(run.get('id') == int(failed['run_id']) and run.get('head_sha') == failed['control_source_sha']
         and run.get('head_branch') == 'main' and run.get('path') == WORKFLOW and run.get('event') == 'issue_comment'
         and type(run.get('run_attempt')) is int and run['run_attempt'] == 1
         and run.get('status') == 'completed' and run.get('conclusion') == 'failure'
         and run.get('actor', {}).get('login') == run.get('triggering_actor', {}).get('login') == 'pyatkoff', 'site_inspection_failed_run')
    jobs = [j for j in pages('/actions/runs/' + failed['run_id'] + '/attempts/1/jobs', 'jobs') if j.get('name') == 'public-install']
    need(len(jobs) == 1 and jobs[0].get('id') == failed['job_id'] and jobs[0].get('run_id') == int(failed['run_id'])
         and jobs[0].get('status') == 'completed' and jobs[0].get('conclusion') == 'failure', 'site_inspection_failed_job')
    steps = [s for s in jobs[0].get('steps', []) if s.get('name', '').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps) == 1 and steps[0].get('status') == 'completed' and steps[0].get('conclusion') == 'failure', 'site_inspection_failed_step')
    artifact = api('/actions/artifacts/' + str(failed['artifact_id']))
    need(artifact.get('id') == failed['artifact_id'] and artifact.get('name') == 'search3-public-install-'+failed['run_id']+'-1'
         and artifact.get('expired') is False and artifact.get('workflow_run', {}).get('id') == int(failed['run_id'])
         and artifact.get('digest') == 'sha256:' + failed['artifact_sha256'], 'site_inspection_failed_artifact')
    comments = pages('/issues/4709/comments')
    original = {k: failed[k] for k in ('control_source_sha','run_id','command_comment_id')}
    _unique_update_command(comments, SITE_PREFIX, SITE_OPERATION, original)
    _unique_update_command(comments, SITE_INSPECT_PREFIX, SITE_INSPECT_OPERATION, identity)
    terminal = next((c for c in comments if c['id'] == failed['terminal_comment_id']), {})
    tokens = [str(failed[k]) for k in ('run_id','control_source_sha','command_comment_id','job_id','artifact_id','artifact_sha256','receipt_sha256')]
    need(terminal.get('user', {}).get('id') == 226193297 and terminal.get('author_association') == 'OWNER'
         and all(t in terminal.get('body', '') for t in tokens + ['blocked_no_replay','dependency_drift','NO_REPLAY']), 'site_inspection_failed_terminal')
    site_inspect_heads(identity)


def site_inspect_failed_value(value):
    failed = SITE_INSPECT_FAILED
    need(sha((json.dumps(value, sort_keys=True)+'\n').encode()) == failed['receipt_sha256'], 'site_inspection_failed_digest')
    original = {k: failed[k] for k in ('control_source_sha','run_id','command_comment_id')}
    fixed = {**original, 'operation_id':SITE_OPERATION, 'source_sha':SITE_SOURCE, 'manifest_sha256':site_policy()['manifest'],
             'read_only':False, 'phase':'complete', 'ssh_started':True, 'status':'blocked_no_replay', 'parent_update':site_parent_identity()}
    need(isinstance(value, dict) and set(value) == set(fixed) | {'remote','parent_evidence'}
         and all(type(value.get(k)) is type(v) and value[k] == v for k,v in fixed.items()), 'site_inspection_failed_evidence')
    remote = value['remote']; valid_site_result(original, remote)
    flags = ('reservation_created','snapshot_verified','restore_drill_verified','protected_unchanged',
             'unchanged_files_verified','parent_records_unchanged','original_inodes_verified')
    need(remote['status'] == 'blocked_no_replay' and remote['phase'] == 'preflight' and remote.get('reason') == 'dependency_drift'
         and remote['snapshot_manifest_sha256'] is None and not remote['written_files'] and not remote['rollback_files']
         and all(remote[k] is False for k in flags), 'site_inspection_failed_evidence')
    site_parent_value(value['parent_evidence'])
    return value['parent_evidence']


def site_inspect_failed_evidence(raw):
    need(sha(raw) == SITE_INSPECT_FAILED['artifact_sha256'], 'site_inspection_failed_artifact_digest')
    with zip_files(raw) as archive:
        need(archive.namelist() == ['evidence.json'], 'site_inspection_failed_artifact')
        data = archive.read('evidence.json')
        need(sha(data) == SITE_INSPECT_FAILED['receipt_sha256'], 'site_inspection_failed_digest')
    value = json.loads(data); site_inspect_failed_value(value)
    return value


SITE_INSPECT_REMOTE = r'''
def inspection_namespace_absent():
 with base.parent(SITE_POLICY['operation']) as (fd,name):
  try: os.stat(name,dir_fd=fd,follow_symlinks=False)
  except FileNotFoundError: return True
  return False
def inspection_parent_proof():
 # Validate retained proofs independently of the current protected state. The
 # latter is what this diagnostic measures, never an admission to change it.
 retained=parent_records();original_snapshot=json.loads(retained['snapshot/manifest.json'][2])
 rows=read_records(updated,UPDATE_RECORD_PATHS);record=lambda p:json.loads(rows[p][2]);path=UPDATE_POLICY['path']
 require(record('reservation.json')=={'operation':UPDATE_POLICY['operation'],**UPDATE_ORIGINAL,
         'source':UPDATE_POLICY['source'],'manifest':UPDATE_POLICY['manifest'],'parent_install':ORIGINAL}
         and record('result.json')==UPDATE_ACCEPTED,'update_original_identity')
 snapshot=record('snapshot/manifest.json')
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_install','path','before','protected','previous_identity','parent_records'}
         and snapshot['source']==UPDATE_POLICY['source'] and snapshot['manifest']==UPDATE_POLICY['manifest']
         and snapshot['parent_install']==ORIGINAL and snapshot['path']==path and snapshot['before']==POLICY['expected']
         and snapshot['protected']==original_snapshot['protected'] and snapshot['parent_records']==parent_hashes(retained)
         and digest(rows['snapshot/manifest.json'][2])==UPDATE_ACCEPTED['snapshot_manifest_sha256']
         and rows['snapshot/app'][0]==UPDATE_POLICY['before'],'update_snapshot_conflict')
 identities={p:parent_intent(p,retained) for p in POLICY['expected']}
 old=checked_stamp(snapshot['previous_identity'],UPDATE_POLICY['before'])
 require(old[:-1]==identities[path][:-1],'update_parent_inode')
 prior=updated.read('stage/app')[:2]
 require(prior[0]==UPDATE_POLICY['before'] and prior[1][:-1]==old[:-1],'update_snapshot_conflict')
 intent=record('intent.json')
 require(isinstance(intent,dict) and set(intent)=={'path','before','after','previous_identity','new_identity'}
         and intent['path']==path and intent['before']==UPDATE_POLICY['before'] and intent['after']==UPDATE_POLICY['after']
         and intent['previous_identity']==snapshot['previous_identity'],'update_private_record')
 identities[path]=checked_stamp(intent['new_identity'],UPDATE_POLICY['after'])
 return retained,rows,prior,original_snapshot['protected'],identities
result={'schema_version':1,'operation_id':INSPECTION_OPERATION,'source_sha':SITE_POLICY['source'],
        'manifest_sha256':SITE_POLICY['manifest'],**IDENTITY,'failed_attempt':FAILED,'parent_update':UPDATE_ORIGINAL,
        'read_only':True,'server_writes':0,'supplier_calls':0,'database_reads':0,'database_writes':0,
        'lead_calls':0,'booking_calls':0,'application_calls':0,'status':'inspection_blocked','phase':'preflight',
        'parent_records_verified':False,'parent_records_stable':False,'inventory_stable':False,
        'failed_namespace_absent':False,'scope_matches':False,
        'checked_paths':{'overlay':[],'site_required':[],'site_optional':[]},
        'protected_comparisons':{},'private_checks':{},'target_comparisons':{},'overlay_mismatches':[],'site_mismatches':[]}
public=base=original=updated=None
try:
 home=os.environ.get('HOME','');public=Files(home+'/www/anytoour.ru')
 base=Files(home+'/.anytoour-search3-root-gateway');private_root(base)
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation']);private_root(original)
 updated=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_POLICY['operation']);private_root(updated)
 result['failed_namespace_absent']=inspection_namespace_absent()
 require(result['failed_namespace_absent'],'site_failed_operation_exists')
 retained,update_records,update_prior,snapshot,identities=inspection_parent_proof()
 result['parent_records_verified']=True
 protected=checked_protected_paths();overlay=sorted(POLICY['expected'])
 required=sorted(SITE_POLICY['dependencies']);optional=sorted(SITE_POLICY['optional_dependencies'])
 scope=tuple(dict.fromkeys((*protected,*overlay,*SITE_POLICY['order'],*required,*optional)))
 rows=continuation_inventory(public,scope);labels=private_labels()
 for path in protected:
  row=rows[path][0]
  matches={'matches_parent_snapshot':row==snapshot[path],'matches_compiled_policy':dependency_matches(path,row)}
  if path in labels:
   result['private_checks'][labels[path]]={**matches,'readable':row.get('state')!='unreadable','present':row.get('state')=='present'}
  else: result['protected_comparisons'][path]={**matches,'observed':row}
 for path in SITE_POLICY['order']:
  row=rows[path][0];target=SITE_POLICY['targets'][path]
  result['target_comparisons'][path]={'matches_predecessor':row==target['before'],'matches_source':row==target['after'],'observed':row}
 for path in overlay:
  row,identity=rows[path];expected=UPDATE_POLICY['after'] if path==UPDATE_POLICY['path'] else POLICY['expected'][path]
  matches_bytes=row==expected;matches_inode=identity is not None and identity[:-1]==identities[path][:-1]
  if not(matches_bytes and matches_inode):
   result['overlay_mismatches'].append({'path':path,'matches_bytes':matches_bytes,'matches_inode':matches_inode,'observed':row})
 for path in (*required,*optional):
  row=rows[path][0];expected=SITE_POLICY['dependencies'].get(path,SITE_POLICY['optional_dependencies'].get(path))
  match=(path in optional and row=={'state':'absent'}) or row.get('state')=='present' and row.get('sha256')==expected and row.get('mode')=='0644'
  if not match: result['site_mismatches'].append({'path':path,'optional':path in optional,'observed':row})
 result['checked_paths']={'overlay':overlay,'site_required':required,'site_optional':optional}
 result['inventory_stable']=continuation_inventory(public,scope)==rows
 result['parent_records_stable']=(parent_records()==retained and read_records(updated,UPDATE_RECORD_PATHS)==update_records
                                 and updated.read('stage/app')[:2]==update_prior)
 result['failed_namespace_absent']=inspection_namespace_absent()
 require(result['inventory_stable'] and result['parent_records_stable'] and result['failed_namespace_absent'],'site_inspection_race')
 result['scope_matches']=(all(r['matches_parent_snapshot'] and r['matches_compiled_policy'] for r in result['protected_comparisons'].values())
                          and all(r['readable'] and r['matches_parent_snapshot'] and r['matches_compiled_policy'] for r in result['private_checks'].values())
                          and all(r['matches_predecessor'] for r in result['target_comparisons'].values())
                          and not result['overlay_mismatches'] and not result['site_mismatches'])
 result.update(status='inspection_match' if result['scope_matches'] else 'inspection_mismatch',phase='complete')
except Exception as error:
 result['status']='inspection_unknown' if result['parent_records_verified'] else 'inspection_blocked'
 result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
finally:
 for store in (updated,original,base,public):
  if store is not None: store.close()
print(json.dumps(result,sort_keys=True))
'''


SITE_INSPECT_REASONS = SITE_REASONS + ('site_failed_operation_exists','site_inspection_race')


def site_inspect_remote_program(identity, failed_value):
    parent = site_inspect_failed_value(failed_value)
    program = (_site_program_prefix(site_policy(), identity, parent) + '\nFAILED=' + repr(SITE_INSPECT_FAILED)
               + '\nINSPECTION_OPERATION=' + repr(SITE_INSPECT_OPERATION)
               + '\ndef re_safe(value): return value in ' + repr(SITE_INSPECT_REASONS) + '\n' + SITE_INSPECT_REMOTE)
    ast.parse(program)
    return program


def valid_site_inspection_result(identity, result):
    fixed = {'schema_version':1,'operation_id':SITE_INSPECT_OPERATION,'source_sha':SITE_SOURCE,
             'manifest_sha256':site_policy()['manifest'],**identity,'failed_attempt':SITE_INSPECT_FAILED,
             'parent_update':site_parent_identity(),'read_only':True}
    counters = ('server_writes','supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
    flags = ('parent_records_verified','parent_records_stable','inventory_stable','failed_namespace_absent','scope_matches')
    required = set(fixed) | set(counters) | set(flags) | {'status','phase','checked_paths','protected_comparisons',
                                                       'private_checks','target_comparisons','overlay_mismatches','site_mismatches'}
    need(isinstance(result,dict) and required <= set(result) <= required | {'reason'}, 'site_inspection_receipt_shape')
    need(all(type(result.get(k)) is type(v) and result[k]==v for k,v in fixed.items()), 'site_inspection_receipt_identity')
    need(all(type(result.get(k)) is int and result[k]==0 for k in counters), 'site_inspection_receipt_counters')
    need(all(type(result[k]) is bool for k in flags), 'site_inspection_receipt_flags')
    need(result['status'] in ('inspection_match','inspection_mismatch','inspection_blocked','inspection_unknown')
         and result['phase'] in ('preflight','complete')
         and ('reason' not in result or result['reason'] in SITE_INSPECT_REASONS), 'site_inspection_receipt_status')
    private = {PRIVATE_CONFIG:'anex_private_config','config.php':'public_config','site_conf.php':'site_config',
               '_preview/search3-local-candidate/config.php':'local_config','_preview/search3-next-candidate/config.php':'next_config'}
    protected = (set(CONTINUATION_PROTECTED) | set(OPAQUE_SETTINGS) | set(ABSENT_DEPENDENCIES)) - set(private)
    expected_checked = {'overlay':sorted((*ADDITIONS,ENTRY)), 'site_required':sorted(SITE_DEPENDENCIES),
                        'site_optional':sorted(SITE_OPTIONAL_DEPENDENCIES)}
    need(result['checked_paths'] in (expected_checked,{k:[] for k in expected_checked}), 'site_inspection_checked_scope')
    def observed(row):
        need(isinstance(row,dict),'site_inspection_observed')
        if row.get('state')=='present':
            need(set(row)=={'state','sha256','size','mode'} and isinstance(row['sha256'],str) and re.fullmatch('[a-f0-9]{64}',row['sha256'])
                 and type(row['size']) is int and 0 <= row['size'] <= MAX_BYTES
                 and isinstance(row['mode'],str) and re.fullmatch('[0-7]{4}',row['mode']), 'site_inspection_observed')
        else:
            need(row=={'state':'absent'} or set(row)=={'state','reason'} and row['state']=='unreadable'
                 and row['reason'] in SITE_INSPECT_REASONS, 'site_inspection_observed')
    def booleans(row, keys):
        need(isinstance(row,dict) and set(row)==set(keys) and all(type(row[k]) is bool for k in keys), 'site_inspection_private_fields')
    comparisons = result['protected_comparisons']; private_checks = result['private_checks']; targets = result['target_comparisons']
    need(isinstance(comparisons,dict) and set(comparisons) <= protected
         and isinstance(private_checks,dict) and set(private_checks) <= set(private.values())
         and isinstance(targets,dict) and set(targets) <= set(SITE_ORDER), 'site_inspection_observed_scope')
    for path,row in comparisons.items():
        need(isinstance(row,dict) and set(row)=={'observed','matches_parent_snapshot','matches_compiled_policy'}, 'site_inspection_comparison')
        observed(row['observed']); booleans({k:v for k,v in row.items() if k!='observed'}, ('matches_parent_snapshot','matches_compiled_policy'))
        current=row['observed']
        match=(current.get('state')=='present' and current.get('sha256')==CONTINUATION_PROTECTED[path] and current.get('mode')=='0644') if path in CONTINUATION_PROTECTED else current=={'state':'absent'}
        need(row['matches_compiled_policy']==match, 'site_inspection_comparison')
    for row in private_checks.values():booleans(row,('readable','present','matches_parent_snapshot','matches_compiled_policy'))
    for path,row in targets.items():
        need(isinstance(row,dict) and set(row)=={'observed','matches_predecessor','matches_source'}, 'site_inspection_comparison')
        observed(row['observed']);booleans({k:v for k,v in row.items() if k!='observed'},('matches_predecessor','matches_source'))
        need(row['matches_predecessor']==(row['observed']==SITE_TARGETS[path]['before'])
             and row['matches_source']==(row['observed']==SITE_TARGETS[path]['after']), 'site_inspection_comparison')
    for kind,allowed in (('overlay_mismatches',set(expected_checked['overlay'])),('site_mismatches',set(SITE_DEPENDENCIES)|set(SITE_OPTIONAL_DEPENDENCIES))):
        rows=result[kind];need(isinstance(rows,list) and len(rows)<=len(allowed),'site_inspection_mismatch_scope');seen=set()
        for row in rows:
            keys={'path','observed','matches_bytes','matches_inode'} if kind=='overlay_mismatches' else {'path','observed','optional'}
            need(isinstance(row,dict) and set(row)==keys and isinstance(row['path'],str)
                 and row['path'] in allowed and row['path'] not in seen,'site_inspection_mismatch_scope')
            seen.add(row['path']);observed(row['observed'])
            if kind=='overlay_mismatches':
                booleans({k:row[k] for k in ('matches_bytes','matches_inode')},('matches_bytes','matches_inode'))
                need(not(row['matches_bytes'] and row['matches_inode']),'site_inspection_comparison')
            else:
                path=row['path'];current=row['observed'];optional=path in SITE_OPTIONAL_DEPENDENCIES
                expected=SITE_DEPENDENCIES.get(path,SITE_OPTIONAL_DEPENDENCIES.get(path))
                match=(optional and current=={'state':'absent'}) or current.get('state')=='present' and current.get('sha256')==expected and current.get('mode')=='0644'
                need(type(row['optional']) is bool and row['optional']==optional and not match,'site_inspection_comparison')
    complete=result['status'] in ('inspection_match','inspection_mismatch')
    if complete:
        need(result['phase']=='complete' and 'reason' not in result and result['checked_paths']==expected_checked
             and set(comparisons)==protected and set(private_checks)==set(private.values()) and set(targets)==set(SITE_ORDER)
             and all(result[k] for k in flags if k!='scope_matches'), 'site_inspection_receipt_incomplete')
        match=(all(r['matches_parent_snapshot'] and r['matches_compiled_policy'] for r in comparisons.values())
               and all(r['readable'] and r['matches_parent_snapshot'] and r['matches_compiled_policy'] for r in private_checks.values())
               and all(r['matches_predecessor'] for r in targets.values())
               and not result['overlay_mismatches'] and not result['site_mismatches'])
        need(result['scope_matches']==match and (result['status']=='inspection_match')==match, 'site_inspection_receipt_incomplete')
    need(len(json.dumps(result,sort_keys=True).encode()) <= SITE_INSPECT_RECEIPT_LIMIT,'site_inspection_receipt_limit')
    return complete


def site_inspect():
    work=Path(os.environ['RUNNER_TEMP'])/'search3-public-install';work.mkdir(mode=0o700)
    key,known=work/'key',work/'known'
    evidence={'status':'inspection_blocked','operation_id':SITE_INSPECT_OPERATION,'source_sha':SITE_SOURCE,
              'manifest_sha256':site_policy()['manifest'],'read_only':True,'phase':'identity','ssh_started':False,
              'failed_attempt':SITE_INSPECT_FAILED}
    complete=False
    try:
        identity=site_inspect_event_identity(json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text()),os.environ)
        evidence.update(identity,phase='admission');site_inspect_admission(identity);evidence['phase']='artifact'
        failed=site_inspect_failed_evidence(download(SITE_INSPECT_FAILED['artifact_id'],SITE_INSPECT_FAILED['artifact_sha256']))
        program=site_inspect_remote_program(identity,failed);evidence['failed_evidence']=failed
        host,user=os.environ['PREVIEW_HOST'],os.environ['PREVIEW_USER']
        need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]{0,252}',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
        raw=os.environ['PREVIEW_KEY'].replace('\r','');need(0<len(raw)<=32768,'ssh_key')
        if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
        with key.open('x') as stream:stream.write(raw.rstrip()+'\n')
        key.chmod(0o600)
        scan=carrier.run(['ssh-keyscan','-T','15','-t','ed25519',host]);need(0<len(scan)<=8192,'host_key')
        known.write_bytes(scan);known.chmod(0o600)
        options=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','ConnectTimeout=15']
        site_inspect_admission(identity)
        evidence.update(status='inspection_unknown',phase='remote',ssh_started=True)
        response=carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'],input=program.encode(),timeout=180)
        need(len(response)<=SITE_INSPECT_RECEIPT_LIMIT,'site_inspection_receipt_limit');result=json.loads(response)
        complete=valid_site_inspection_result(identity,result);evidence['remote']=result
        site_inspect_heads(identity);evidence.update(status=result['status'],phase='complete')
    except Exception as error:
        complete=False
        evidence['error_type']=type(error).__name__ if type(error) in (RuntimeError,ValueError,KeyError,OSError,subprocess.CalledProcessError,subprocess.TimeoutExpired) else 'SiteInspectionError'
    finally:
        key.unlink(missing_ok=True);known.unlink(missing_ok=True)
        (work/'evidence.json').write_text(json.dumps(evidence,sort_keys=True)+'\n')
    print(json.dumps(evidence,sort_keys=True));need(complete,'site_inspection_not_verified')


def command_mode(arguments):
    if arguments == ['site-inspect']: return 'site-inspect'
    if arguments == ['site-update']: return 'site-update'
    if arguments == ['site-rollback']: return 'site-rollback'
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
    if mode == 'site-inspect': site_inspect()
    elif mode == 'site-update': site_update()
    elif mode == 'site-rollback': site_update(rollback=True)
    elif mode == 'update': update()
    elif mode == 'update-rollback': update(rollback=True)
    elif mode == 'rollback': rollback()
    elif mode == 'continue': install(continuation=True)
    else: install(mode)
