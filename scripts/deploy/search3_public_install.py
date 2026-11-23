#!/usr/bin/env python3
"""One reviewed Search3 overlay transaction in the existing root-gateway carrier.

No application execution, endpoint probe, configuration repair or generic target.
The LIVE builder's deployable:false is preserved; only this separately admitted,
fixed operation can consume its exact overlay after all dependency checks.
"""
import ast
import base64
import datetime
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
import int_server_executor_search3_signing as tv_signing

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

# A current dependency transition, not a replacement of any accepted policy or
# snapshot. Only the original INT5090 maintenance can supply
# the successor. The original protected hashes and their policy digest stay fixed.
TV_SUCCESSOR = {
    'path': '_preview/search3-anex-candidate/app/integrations/tourvisor-interactive-client.php',
    'relative': 'app/integrations/tourvisor-interactive-client.php',
    'operation_id': 'int-anex-tv-invalid-response-install-20261119-v1',
    'mode': 'install-tv-invalid-response',
    'source_sha': '3207266e17ed4e8244aaf28d05b962747fd4cd3c',
    'before_sha256': '72056e5c4642daa9515d2d5dccece0804b6e3dd9c935b6250e0e29ca2e39daf9',
    'after_sha256': '529257a04d7526a758b49368959179e697279d223306d06a640e169c35148841',
    'after_size': 9741,
    # The original execute job runs these tests before dispatch. Pin their
    # executed Python/PHP closure as well as the transport's companion modules;
    # passive fixtures and files inspected only by php -l are not executable inputs.
    'control_blobs': {
        '.github/workflows/int-server-executor.yml': 'd5f530e5257052d32b91e5cc82e2dfb00247f5c0',
        'scripts/deploy/int_server_executor.py': 'beaccacebd7864c3e41e8fb4bcf7fa9ab9357268',
        'scripts/deploy/int_server_executor_anex_secret_transport.py': 'c548e445ae426934540ade3433f3eb6ddfd44a75',
        'scripts/deploy/int_server_executor_firstpage_readback.py': '2fed14a625f56bfea42f1a7e3cc17e7408a5b4d1',
        'scripts/deploy/int_server_executor_local_profile_acquire.py': '5610c13a42ba9f9eeeba18e3961b8375342971d4',
        'scripts/deploy/int_server_executor_local_profile_apply.py': 'c840c47e1404b1eb8776957598c7947178d973ed',
        'scripts/deploy/int_server_executor_local_profile_apply_source320.py': '416fbfa517298907d77feec5a36a45fcd76400f8',
        'scripts/deploy/int_server_executor_local_profile_metadata.py': '63d0a543e7ac9b25c751270d0836d10ef6dd6e03',
        'scripts/deploy/int_server_executor_local_profile_phase3_inspection.py': '3aa01e8ec679290d7d953507f3e684403e361803',
        'scripts/deploy/int_server_executor_local_profile_plan.py': '85cb0494e9d7ba4ba1ddd94c4f1c11f3a2b01cc8',
        'scripts/deploy/int_server_executor_local_profile_read.py': 'edd932d18ddbf224fb00e2825b2d1e76e2f8b70b',
        'scripts/deploy/int_server_executor_local_profile_recovery_plan.py': 'e853b2ffd2933048d36b4b2354d6d94e528f3b57',
        'scripts/deploy/int_server_executor_local_stay_read.py': '2ddfd0606a2110ea3e75cdfc3d85b3287f6207f7',
        'scripts/deploy/int_server_executor_local_tv_capture.py': '8dac180c0f48490e39ebfb7a416520351a24e770',
        'scripts/deploy/int_server_executor_local_tv_content.py': 'a352848a313bb25a81a2e83e1829c2c4ca05e86a',
        'scripts/deploy/int_server_executor_local_tv_schema.py': '9d454798261e62e4ae9902485ad8ee2d2b4fb78d',
        'scripts/deploy/int_server_executor_local_tv_seed.py': 'a89ea16920cb1495b2bfa28b83856933da7d3b6c',
        'scripts/deploy/int_server_executor_match_primary.py': '3fe7781316c88bfd61573700b6916c866d36d480',
        'scripts/deploy/int_server_executor_match_retained.py': 'affbf2d848f6a787bd36d5785cbdbc695ef7ed7d',
        'scripts/deploy/int_server_executor_passive_fuel_readiness.py': '0cc93dc43f1a38c82633474557c51a5f2fc00ad2',
        'scripts/deploy/int_server_executor_search3_signing.py': 'eb64ac13f9f27d32c02e2eaafe5e9362dd4a0aa5',
        'scripts/diagnostics/int_funsun_direction_store_server_readback.py': '96bff671035241c869f772d14af5c45871aad0a6',
        'scripts/diagnostics/local_profile_acquire_4191.php': '1ac2a0158efc21f1bb07a5b10af85835a5187ec3',
        'scripts/diagnostics/local_profile_apply_4191.php': '5b8fbecb106ac266ed4c9b602f577a199e349628',
        'scripts/diagnostics/local_profile_apply_source320_4191.php': 'e135ee29510b54b37260f1873a6aa7d3ad28ce85',
        'scripts/diagnostics/local_profile_mass_apply2_4191.php': '9bdc84aea6c72bee01b3fc4ee5061d5104c0b83a',
        'scripts/diagnostics/local_profile_mass_apply_4191.php': '19d3fa0c1683f35acfc63e1780300b66cec8bc42',
        'scripts/diagnostics/local_profile_mass_plan2_4191.php': 'b9795f3baa07b13d1cdcb21f3323ac6fc0c87ec7',
        'scripts/diagnostics/local_profile_mass_plan3_4191.php': '8c1179a6ccdcc37932212037d123db00cfe45987',
        'scripts/diagnostics/local_profile_mass_plan_4191.php': '8a09058cb0253dd811990a547ad1740b84b2f79f',
        'scripts/diagnostics/local_profile_metadata_4191.php': 'c1c1233e297529a2e45ae3f0b6529ee9a171ecd4',
        'scripts/diagnostics/local_profile_plan_4191.php': '2e600b7a472cd4d278eca809a5b6129e68b7a991',
        'tests/int_server_executor_anex_secret_transport_test.py': '0ecb910e4b395a44b4eccea765366568eba93d3a',
        'tests/int_server_executor_firstpage_readback_test.py': '1e645f4b33e64f2feac743a1b17691451478a36d',
        'tests/int_server_executor_local_profile_acquire_test.py': '88c7fa34faf26979b08df0c0e61dd18d59da965a',
        'tests/int_server_executor_local_profile_apply_source320_test.py': '21d7a293cc5f83d62a7e43086bc9a0d4b0a8b7e4',
        'tests/int_server_executor_local_profile_apply_test.py': '95573cc1adfd640de28a52f0fd4ad910ca482e77',
        'tests/int_server_executor_local_profile_mass_apply2_test.py': 'c4788dfb2939014cd6c7cd7887ea578c0170a22f',
        'tests/int_server_executor_local_profile_mass_apply_test.py': '2897e68199975ba24dcd79f54c5a5e041b7114b2',
        'tests/int_server_executor_local_profile_mass_plan2_test.py': '1eb0509ea4b838f33686257f7de881f6d820703a',
        'tests/int_server_executor_local_profile_mass_plan3_test.py': '914b948deda08b0d5e600bd0e557e4f4930dce74',
        'tests/int_server_executor_local_profile_mass_plan_test.py': 'dfb5cc1c7d82f6fad60a9a5111329d1df8e4d6ec',
        'tests/int_server_executor_local_profile_metadata_test.py': '5872eb60be0fec9cfd4423b759d849d6794452dc',
        'tests/int_server_executor_local_profile_phase3_inspection_test.py': '7c3d7bbcdb40ed4d943b42c2ab977df30a971c3d',
        'tests/int_server_executor_local_profile_plan_test.py': 'b147bffaeb7459fa7d38cf822328b9d465ac900c',
        'tests/int_server_executor_local_profile_recovery_plan_test.py': '3cd6ce634d878dadbcaf7a1f1f3407ad295410fe',
        'tests/int_server_executor_match_primary_test.py': 'be5ff3b1d9ccc3fd5f8df70a6833ece01e0d81eb',
        'tests/int_server_executor_search3_signing_test.py': '92f74b938171b6753a22e404fad9df0be9ad6207',
        'tests/int_server_executor_test.py': '19f14d8ca182e5891e737172ea1e118e1d378dbb',
    },
}
# Accepted original INT attempt 44911949356. Its reviewed canonical receipt
# survives the source artifact's 30-day retention; metadata and private records
# are still checked on every use. These are observed facts, never a template.
TV_SUCCESSOR_RETAINED = {'proof': {'artifact_id': 14710576060,
           'artifact_sha256': '3e6a106c3f8529422a68e01cab27baca8b9037936a769301119a92a0f5fd3dd0',
           'command_comment_id': 7063787927,
           'completed_at': 1795269966,
           'control_source_sha': '79ae813b41c933542dbb05577bd12ebcdfe3bc2b',
           'created_at': 1795269811,
           'job_id': 136869432183,
           'run_id': 44911949356,
           'started_at': 1795269954,
           'status': 'installed'},
 'run_attempt': 1,
 'run_created_at': '2026-11-21T14:03:34Z',
 'run_started_at': '2026-11-21T14:03:34Z',
 'result_canonical_json': '{"booking_calls":0,"database_writes":0,"diagnostic_basis_after":{"configuration_mode":420,"configuration_sha256":"faea72b846d201013a6426485996c0a2c022148ec897ae3092015c0946fb3d27","parent_canonical_sha256":"16049bccb2a9908c54dd25c998ddc2c6c0b6f0edecb07e55bca88faeb1c62d03","parent_operation":"int-anex-tv-session-gateway-recovery-20261025-v1","receipt_and_configuration_unchanged":true},"diagnostic_basis_before":{"configuration_mode":420,"configuration_sha256":"faea72b846d201013a6426485996c0a2c022148ec897ae3092015c0946fb3d27","parent_canonical_sha256":"16049bccb2a9908c54dd25c998ddc2c6c0b6f0edecb07e55bca88faeb1c62d03","parent_operation":"int-anex-tv-session-gateway-recovery-20261025-v1","receipt_and_configuration_unchanged":true},"install":{"changed_files":1,"created_files":0,"dependency":{"changed":false,"sha256":"145eff807355b50bcd381c6b3d732eb5c8ac4350f2945fb77304c9fd50673ce6","source":"app/integrations/anex-initial-week-gate.php","target":"app/integrations/anex-initial-week-gate.php"},"endpoint":{"changed":false,"sha256":"0beda5c137c3153ec7c80da382f63ca3f989634130c508af69f6c6f0ce2162f4","source":"v2/api-anex-search3-preview.php","target":"api-anex-search3-preview.php"},"files":12,"manifest_sha256":"c591ec8340d231221c20199e02fda954a51f8dcf25c2b3b9ebdb48942709e766","source_sha":"3207266e17ed4e8244aaf28d05b962747fd4cd3c","status":"installed","tv_client":{"changed":true,"sha256":"529257a04d7526a758b49368959179e697279d223306d06a640e169c35148841","source":"app/integrations/tourvisor-interactive-client.php","target":"app/integrations/tourvisor-interactive-client.php"}},"lead_calls":0,"mode":"install-tv-invalid-response","operation_id":"int-anex-tv-invalid-response-install-20261119-v1","production_after":{"index.php":"80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f","v2/api-v2.php":null,"v2/index.php":null,"v2/lead-adapter-v2.php":null},"production_before":{"index.php":"80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f","v2/api-v2.php":null,"v2/index.php":null,"v2/lead-adapter-v2.php":null},"public_ui_entrypoints_unchanged":true,"runtime_changed":true,"schema_version":1,"source_sha":"3207266e17ed4e8244aaf28d05b962747fd4cd3c","status":"installed","supplier_calls":0,"tv_gateway":{"activation_performed":false,"configuration_changed":false,"files":{"app/integrations/anex-additional-prices-client.php":"e905ce4f12a920201b1b2faeff78623a26023dd8ad79e0c3d6378d1dc75478f7","app/integrations/anex-package-quote-client.php":"010900cb382af985c0da097a440fc49dc6d85e311ea380d4c1a93e0df656ce0a","app/integrations/operator-fuel-rule-evidence.php":"5205c275da1f71aa756aab6852664902a8ea0453a4a457673f0896737f11a6a4","app/integrations/operator-fuel-rule-store.php":"a84468efc7953f50ee6fc25aaa77a40b97bd527ad9fbbe923d547dbe9db60fb9","app/integrations/search3-messenger-handoff.php":"5e0fe33e6adf3ce30451b9ffe1742cc77f791114e241fb41d92236c1d70ea85c","app/integrations/tourvisor-interactive-client.php":"529257a04d7526a758b49368959179e697279d223306d06a640e169c35148841","app/integrations/tourvisor-operator-fuel-passive-intake.php":"09a40aa5c6c4b6d98d378e353707412a4a3b83631452585a3fff27c6392d7008","app/integrations/tourvisor-operator-fuel-retained-intake.php":"6c233c9fc0fe64c1647941ccb22756e1cad4c17b9692998dbd1eb6b7eb04a3d0","app/integrations/tourvisor-preview-gateway.php":"c3008d765b9279c7be347e9fa92861053b5dc661d7ea2dbf65450e83f488d8d1","app/integrations/tourvisor-selected-tour-handoff.php":"9dafb88cab92e19376e120c80dd5dfecc975730cca7e7039edc419bf1ffa57f3","v2/api-anex-search3-preview.php":"0beda5c137c3153ec7c80da382f63ca3f989634130c508af69f6c6f0ce2162f4"},"signing_custody_changed":false,"source_sha":"3207266e17ed4e8244aaf28d05b962747fd4cd3c"},"tv_gateway_public_after":{"api-v2.php":{"sha256":"90ddd43020aff509430b6ff85887a267ea4f0b30acc2213c82d7dcce0eb862f2","state":"regular"},"index.php":{"sha256":"80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f","state":"regular"},"v2/api-v2.php":{"state":"absent"},"v2/index.php":{"state":"absent"},"v2/lead-adapter-v2.php":{"state":"absent"}},"tv_gateway_public_before":{"api-v2.php":{"sha256":"90ddd43020aff509430b6ff85887a267ea4f0b30acc2213c82d7dcce0eb862f2","state":"regular"},"index.php":{"sha256":"80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f","state":"regular"},"v2/api-v2.php":{"state":"absent"},"v2/index.php":{"state":"absent"},"v2/lead-adapter-v2.php":{"state":"absent"}}}',
 'result_canonical_sha256': '4bbfd3c2aad2d534c043c0efcbedb349c2374331c0be78d783d546d897a759d2'}

TV_SUCCESSOR_REASONS = ('tv_successor_identity', 'tv_successor_namespace', 'tv_successor_record',
                        'tv_successor_plan', 'tv_successor_backup', 'tv_successor_state',
                        'tv_successor_time', 'tv_successor_target', 'tv_successor_snapshot',
                        'tv_successor_changed')


def tv_successor_result_valid(value):
    command = {key: TV_SUCCESSOR[key] for key in ('source_sha', 'mode', 'operation_id')}
    return (isinstance(value, dict)
            and not set(value) & {'reason', 'rollback', 'install_failure_class'}
            and tv_signing.completed_tv_invalid_response(command, value))


def tv_successor_rollback_valid(value):
    """Admit only the original installer's complete automatic restoration."""
    if not isinstance(value, dict): return False
    fixed = {'schema_version': 1, 'status': 'rolled_back', 'runtime_changed': False,
             'public_ui_entrypoints_unchanged': True,
             **{k: TV_SUCCESSOR[k] for k in ('source_sha', 'mode', 'operation_id')}}
    if not all(type(value.get(k)) is type(v) and value[k] == v for k,v in fixed.items()): return False
    if not all(type(value.get(k)) is int and value[k] == 0 for k in
               ('supplier_calls', 'database_writes', 'booking_calls', 'lead_calls')): return False
    rollback = value.get('rollback')
    if (not isinstance(rollback, dict) or set(rollback) != {'status', 'restored_files'}
            or rollback['status'] != 'complete' or type(rollback['restored_files']) is not int
            or rollback['restored_files'] not in (0, 1)
            or not isinstance(value.get('install_failure_class'), str)
            or not re.fullmatch(r'[A-Za-z0-9_:-]{1,96}', value['install_failure_class'])
            or 'reason' in value): return False
    basis = {'parent_operation': tv_signing.TV_RECOVERY_OPERATION,
             'parent_canonical_sha256': tv_signing.TV_ENABLED_CANONICAL,
             'configuration_sha256': tv_signing.TV_ENABLED_CONFIG_SHA, 'configuration_mode': 0o644,
             'receipt_and_configuration_unchanged': True}
    public = {p: v.get('sha256') for p,v in tv_signing.TV_PUBLIC.items() if p != 'api-v2.php'}
    if (value.get('production_before') != public or value.get('tv_gateway_public_before') != tv_signing.TV_PUBLIC
            or value.get('diagnostic_basis_before') != basis
            or value['diagnostic_basis_before'].get('receipt_and_configuration_unchanged') is not True): return False
    for key, expected in (('production_after', public), ('tv_gateway_public_after', tv_signing.TV_PUBLIC),
                          ('diagnostic_basis_after', basis)):
        if key in value and value[key] != expected: return False
    if ('diagnostic_basis_after' in value
            and value['diagnostic_basis_after'].get('receipt_and_configuration_unchanged') is not True): return False
    gateway = {'source_sha': TV_SUCCESSOR['source_sha'],
               'files': tv_signing.tv_invalid_response_previous() |
                        {TV_SUCCESSOR['relative']: TV_SUCCESSOR['after_sha256']},
               'activation_performed': False, 'configuration_changed': False, 'signing_custody_changed': False}
    if ('tv_gateway' in value and (value['tv_gateway'] != gateway or
            any(value['tv_gateway'].get(k) is not False for k in
                ('activation_performed', 'configuration_changed', 'signing_custody_changed')))): return False
    if 'install' in value:
        # Reuse the exact install-shape validator; filling known expected facts
        # here validates the optional nested record, not a claimed runtime state.
        trial = dict(value, status='installed', runtime_changed=True, production_after=public,
                     tv_gateway_public_after=tv_signing.TV_PUBLIC, diagnostic_basis_after=basis,
                     tv_gateway=gateway)
        for key in ('rollback', 'install_failure_class'): trial.pop(key)
        if not tv_successor_result_valid(trial): return False
    return True


def _tv_signer_source():
    raw = Path(tv_signing.__file__).read_bytes()
    blob = hashlib.sha1(b'blob ' + str(len(raw)).encode() + b'\0' + raw).hexdigest()
    need(blob == TV_SUCCESSOR['control_blobs']['scripts/deploy/int_server_executor_search3_signing.py'],
         'tv_successor_validator_source')


def _tv_json(raw):
    def pairs(items):
        value = {}
        for key, item in items:
            need(key not in value, 'tv_successor_json')
            value[key] = item
        return value
    return json.loads(raw, object_pairs_hook=pairs)


def _tv_time(value):
    need(isinstance(value, str) and value.endswith('Z'), 'tv_successor_time')
    parsed = datetime.datetime.fromisoformat(value[:-1] + '+00:00')
    return int(parsed.timestamp())


def _tv_commands():
    command = '/run-int-server-v1 ' + ' '.join(TV_SUCCESSOR[k] for k in ('source_sha', 'mode', 'operation_id'))
    rows = [row for row in pages('/issues/4709/comments')
            if row.get('body', '').startswith('/run-int-server-v1 ')
            and TV_SUCCESSOR['operation_id'] in row['body'].split()]
    need(len(rows) <= 1, 'tv_successor_commands')
    if rows:
        row = rows[0]
        need(row.get('body') == command and row.get('user', {}).get('id') == 226193297
             and row.get('author_association') == 'OWNER'
             and row.get('created_at') == row.get('updated_at'), 'tv_successor_command')
        _tv_time(row.get('created_at'))
    return rows


def _tv_original_proof(command, run, value, artifact_id, artifact_sha256):
    """The same original command/control/job checks for live or retained bytes."""
    created = _tv_time(command['created_at'])
    restored = tv_successor_rollback_valid(value)
    need(tv_successor_result_valid(value) or restored, 'tv_successor_result')
    conclusion = 'failure' if restored else 'success'
    need(run.get('path') == '.github/workflows/int-server-executor.yml'
         and run.get('head_branch') == 'main' and run.get('event') == 'issue_comment'
         and created <= _tv_time(run.get('created_at')) <= created + 300
         and type(run.get('run_attempt')) is int and run['run_attempt'] == 1
         and run.get('status') == 'completed' and run.get('conclusion') == conclusion
         and run.get('actor', {}).get('login') == run.get('triggering_actor', {}).get('login') == 'pyatkoff', 'tv_successor_run')
    control = run.get('head_sha', '')
    need(isinstance(control, str) and re.fullmatch('[a-f0-9]{40}', control), 'tv_successor_control')
    entries = api('/contents/scripts/deploy?ref=' + control)
    need(isinstance(entries, list), 'tv_successor_control')
    selected = {row.get('path'): row for row in entries}
    need(len(selected) == len(entries), 'tv_successor_control')
    for path, expected in TV_SUCCESSOR['control_blobs'].items():
        # The tests directory can exceed GitHub Contents' 1000-entry listing
        # limit. Read each other fixed path explicitly.
        blob = selected.get(path, {}) if path.startswith('scripts/deploy/') else api('/contents/' + path + '?ref=' + control)
        need(blob.get('type') == 'file' and blob.get('sha') == expected, 'tv_successor_control')
    jobs = [job for job in pages('/actions/runs/' + str(run['id']) + '/attempts/1/jobs', 'jobs')
            if job.get('name') == 'execute']
    need(len(jobs) == 1 and jobs[0].get('run_id') == run['id']
         and jobs[0].get('status') == 'completed' and jobs[0].get('conclusion') == conclusion, 'tv_successor_job')
    steps = [step for step in jobs[0].get('steps', [])
             if step.get('name') == 'Execute bounded private INT operation, install, or DB/LOCAL readback']
    need(len(steps) == 1 and steps[0].get('status') == 'completed'
         and steps[0].get('conclusion') == conclusion, 'tv_successor_step')
    started, completed = _tv_time(steps[0].get('started_at')), _tv_time(steps[0].get('completed_at'))
    need(created <= _tv_time(run.get('created_at')) <= _tv_time(run.get('run_started_at'))
         <= started <= completed <= _tv_time(run.get('updated_at')), 'tv_successor_time')
    return {'status': 'rolled_back' if restored else 'installed', 'result': value, 'run_id': run['id'],
            'command_comment_id': command['id'], 'control_source_sha': control,
            'job_id': jobs[0]['id'], 'artifact_id': artifact_id, 'artifact_sha256': artifact_sha256,
            'created_at': created, 'started_at': started, 'completed_at': completed}


def _tv_retained_proof(commands):
    retained = TV_SUCCESSOR_RETAINED
    need(isinstance(retained, dict) and set(retained) == {'proof', 'run_attempt', 'run_created_at', 'run_started_at',
         'result_canonical_json', 'result_canonical_sha256'}, 'tv_successor_retained')
    expected = retained['proof']
    keys = {'status', 'run_id', 'command_comment_id', 'control_source_sha', 'job_id', 'artifact_id',
            'artifact_sha256', 'created_at', 'started_at', 'completed_at'}
    need(isinstance(expected, dict) and set(expected) == keys and expected['status'] in ('installed', 'rolled_back')
         and all(type(expected[k]) is int and expected[k] > 0 for k in
                 ('run_id', 'command_comment_id', 'job_id', 'artifact_id', 'created_at', 'started_at', 'completed_at'))
         and isinstance(expected['control_source_sha'], str) and re.fullmatch('[a-f0-9]{40}', expected['control_source_sha'])
         and isinstance(expected['artifact_sha256'], str) and re.fullmatch('[a-f0-9]{64}', expected['artifact_sha256'])
         and type(retained['run_attempt']) is int and retained['run_attempt'] == 1, 'tv_successor_retained')
    need(len(commands) == 1 and commands[0]['id'] == expected['command_comment_id']
         and _tv_time(commands[0]['created_at']) == expected['created_at'], 'tv_successor_command')
    text = retained['result_canonical_json']
    need(isinstance(text, str) and text.isascii() and 0 < len(text) <= 65536
         and isinstance(retained['result_canonical_sha256'], str)
         and re.fullmatch('[a-f0-9]{64}', retained['result_canonical_sha256']), 'tv_successor_retained')
    raw = text.encode('ascii')
    need(sha(raw) == retained['result_canonical_sha256'], 'tv_successor_retained')
    value = _tv_json(raw)
    need(raw == json.dumps(value, sort_keys=True, separators=(',', ':')).encode(), 'tv_successor_retained')
    run = api('/actions/runs/' + str(expected['run_id']))
    need(run.get('id') == expected['run_id'] and run.get('head_sha') == expected['control_source_sha']
         and type(run.get('run_attempt')) is int and run['run_attempt'] == retained['run_attempt']
         and run.get('created_at') == retained['run_created_at']
         and run.get('run_started_at') == retained['run_started_at'], 'tv_successor_run')
    proof = _tv_original_proof(commands[0], run, value, expected['artifact_id'], expected['artifact_sha256'])
    need(proof == dict(expected, result=value) and _tv_commands() == commands, 'tv_successor_retained')
    return proof


def tv_successor_evidence():
    """Resolve the accepted original INT result and recheck its live provenance.

    INT's local receipt has no command/run IDs. Association is established here
    by the unique exact owner command, original trusted-control attempt, exact
    artifact tuple and its execution window; remote also checks reserved_at.
    An existing command without a complete original receipt is never old-ready.
    """
    _tv_signer_source()
    commands = _tv_commands()
    if TV_SUCCESSOR_RETAINED is not None:
        # The production pin consumes the original transition. A missing or
        # altered proof never falls back to absent or to another candidate run.
        return _tv_retained_proof(commands)
    if not commands:
        return {'status': 'absent'}
    command = commands[0]; created = _tv_time(command['created_at'])
    # GitHub creates an issue_comment run at the event, even if its job then
    # waits on concurrency. Bound creation discovery; unrelated later runs and
    # their expiring artifacts must not become this fixed dependency's inputs.
    end = datetime.datetime.fromtimestamp(created + 300, datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
    query = urllib.parse.quote(command['created_at'] + '..' + end, safe='')
    runs = pages('/actions/workflows/int-server-executor.yml/runs?event=issue_comment&branch=main&created=' + query,
                 'workflow_runs')
    need(len(runs) <= 100, 'tv_successor_run_limit')
    matches = []
    for run in runs:
        need(run.get('path') == '.github/workflows/int-server-executor.yml'
             and run.get('head_branch') == 'main' and run.get('event') == 'issue_comment'
             and created <= _tv_time(run.get('created_at')) <= created + 300, 'tv_successor_run')
        artifacts = pages('/actions/runs/' + str(run['id']) + '/artifacts', 'artifacts')
        candidates = [row for row in artifacts if row.get('name', '').startswith('int-server-executor-')]
        for item in candidates:
            digest = item.get('digest', '')
            if (item.get('expired') is not False or not re.fullmatch(r'sha256:[a-f0-9]{64}', digest)
                    or item.get('workflow_run', {}).get('id') != run['id']):
                continue
            # Unsupported artifacts cannot prove this operation. With no exact
            # original proof below, a pending/malformed own attempt still blocks.
            try:
                with zip_files(download(item['id'], digest.removeprefix('sha256:'))) as archive:
                    if archive.namelist() != ['result.json']: continue
                    raw = archive.read('result.json')
                    if len(raw) > 65536: continue
                value = _tv_json(raw)
            except (ValueError, RuntimeError, zipfile.BadZipFile):
                continue
            if not isinstance(value, dict) or value.get('operation_id') != TV_SUCCESSOR['operation_id']:
                continue
            need(item.get('name') == 'int-server-executor-' + str(run['id']) + '-1', 'tv_successor_run')
            matches.append(_tv_original_proof(command, run, value, item['id'], digest.removeprefix('sha256:')))
    need(len(matches) == 1 and _tv_commands() == commands, 'tv_successor_original_missing')
    return matches[0]


def tv_successor_prefix(value):
    # Pure builders remain usable for historical fixtures. Every live successor
    # entry point passes an explicitly resolved value, including the absent case.
    if value is not None:
        _tv_signer_source()
        need(isinstance(value, dict), 'tv_successor_identity')
        if value.get('status') == 'absent':
            need(value == {'status': 'absent'}, 'tv_successor_identity')
        else:
            keys = {'status', 'result', 'run_id', 'command_comment_id', 'control_source_sha', 'job_id',
                    'artifact_id', 'artifact_sha256', 'created_at', 'started_at', 'completed_at'}
            need(set(value) == keys and value['status'] in ('installed', 'rolled_back')
                 and (tv_successor_result_valid(value['result']) if value['status'] == 'installed'
                      else tv_successor_rollback_valid(value['result']))
                 and all(type(value[k]) is int and value[k] > 0 for k in
                         ('run_id', 'command_comment_id', 'job_id', 'artifact_id', 'created_at', 'started_at', 'completed_at'))
                 and value['created_at'] <= value['started_at'] <= value['completed_at']
                 and re.fullmatch('[a-f0-9]{40}', value['control_source_sha'])
                 and re.fullmatch('[a-f0-9]{64}', value['artifact_sha256']), 'tv_successor_identity')
    previous = tv_signing.tv_invalid_response_previous()
    expected = previous | {TV_SUCCESSOR['relative']: TV_SUCCESSOR['after_sha256']}
    pairs = [(path, path) for path in expected if path.startswith('app/integrations/')]
    pairs += [('app/integrations/anex-initial-week-gate.php', 'app/integrations/anex-initial-week-gate.php'),
              ('v2/api-anex-search3-preview.php', 'api-anex-search3-preview.php')]
    # The dependency is selected separately by the original installer.
    pins = dict(expected) | {'app/integrations/anex-initial-week-gate.php': tv_signing.TV_DEPENDENCIES['app/integrations/anex-initial-week-gate.php']}
    old = dict(previous) | {'app/integrations/anex-initial-week-gate.php': tv_signing.TV_DEPENDENCIES['app/integrations/anex-initial-week-gate.php']}
    dependency = 'app/integrations/anex-initial-week-gate.php'
    install = {'status': 'installed', 'source_sha': TV_SUCCESSOR['source_sha'],
               'files': 12, 'changed_files': 1, 'created_files': 0,
               'endpoint': {'source': 'v2/api-anex-search3-preview.php', 'target': 'api-anex-search3-preview.php',
                            'sha256': pins['v2/api-anex-search3-preview.php'], 'changed': False},
               'dependency': {'source': dependency, 'target': dependency, 'sha256': pins[dependency], 'changed': False},
               'tv_client': {'source': TV_SUCCESSOR['relative'], 'target': TV_SUCCESSOR['relative'],
                             'sha256': TV_SUCCESSOR['after_sha256'], 'changed': True}}
    constants = dict(TV_SUCCESSOR, files=[{'source': a, 'target': b} for a,b in pairs],
                     previous={b: old[a] for a,b in pairs}, expected={b: pins[a] for a,b in pairs}, install=install)
    return '\nTV_SUCCESSOR=' + repr(constants) + '\nTV_SUCCESSOR_PROOF=' + repr(value) + '\n'


# These definitions run only inside the existing PUBLIC SSH program. They read
# the fixed INT namespace; they never create a record, copy a dependency, execute
# PHP or restore an INT file. Historical snapshots are inputs, never write targets.
TV_SUCCESSOR_REMOTE = r'''
_tv_successor_bound=None
def tv_successor_namespace():
 home=os.environ.get('HOME','');store=Files(home)
 try:
  with store.parent('.anytoour-int-executor/'+TV_SUCCESSOR['operation_id']) as (fd,name):
   if fd is None:return False
   root=os.fstat(fd)
   require(stat.S_IMODE(root.st_mode)==0o700 and root.st_uid==os.getuid(),'tv_successor_namespace')
   try:info=os.stat(name,dir_fd=fd,follow_symlinks=False)
   except FileNotFoundError:return False
   require(stat.S_ISDIR(info.st_mode) and stat.S_IMODE(info.st_mode)==0o700
           and info.st_uid==os.getuid(),'tv_successor_namespace')
   return True
 finally:store.close()
def tv_successor_read():
 proof=TV_SUCCESSOR_PROOF;exists=tv_successor_namespace()
 require((proof['status']!='absent')==exists,'tv_successor_namespace')
 current=public.read(TV_SUCCESSOR['path'])[:2]
 row,identity=current
 require(row.get('state')=='present' and row.get('mode')=='0644'
         and identity[6]==os.getuid(),'tv_successor_target')
 if not exists:
  require(row['sha256']==TV_SUCCESSOR['before_sha256'],'tv_successor_target')
  return {'target':current,'records':None,'backup':row}
 installed=proof['status']=='installed'
 require((row['sha256']==TV_SUCCESSOR['after_sha256'] and row['size']==TV_SUCCESSOR['after_size']) if installed
         else row['sha256']==TV_SUCCESSOR['before_sha256'],'tv_successor_target')
 store=Files(os.environ['HOME']+'/.anytoour-int-executor/'+TV_SUCCESSOR['operation_id'])
 try:
  rows={};accepted=proof['result']
  directories={'ancestors':tuple((name,expected) for _,name,_,expected in store.links)}
  for relative in sorted({'/'.join(('backup/'+path).split('/')[:i])
                          for path in TV_SUCCESSOR['previous'] for i in range(1,len(('backup/'+path).split('/')))}):
   with store.parent(relative) as (fd,name):
    require(fd is not None,'tv_successor_namespace')
    info=os.stat(name,dir_fd=fd,follow_symlinks=False)
    require(stat.S_ISDIR(info.st_mode) and info.st_uid==os.getuid(),'tv_successor_namespace')
    directories[relative]=directory(info)
  paths=('reservation.json','result.json','anex-preview-install-plan.json','install-state.json')
  for path in (*paths,*('backup/'+path for path in TV_SUCCESSOR['previous'])):
   record=store.read(path,True);meta,ident,raw=record
   if path=='install-state.json' and not installed and meta=={'state':'absent'}:
    rows[path]=record;continue
   require(meta.get('state')=='present' and meta.get('mode')=='0600'
           and ident[6]==os.getuid(),'tv_successor_record')
   require(path.startswith('backup/') or len(raw)<=65536,'tv_successor_record')
   rows[path]=record
  require(rows['result.json'][2]==canonical(accepted),'tv_successor_state')
  if installed:
   require(rows['install-state.json'][2]==canonical(accepted['install']),'tv_successor_state')
  else:
   count=accepted['rollback']['restored_files'];state=rows['install-state.json']
   if state[0]=={'state':'absent'}:
    # replace may have succeeded before chmod or the first state write failed.
    require('install' not in accepted,'tv_successor_state')
   elif state[2]==canonical({'status':'applying-anex-preview','source_sha':TV_SUCCESSOR['source_sha'],
                            'applied':[TV_SUCCESSOR['relative']]}):
    require(count==1 and 'install' not in accepted,'tv_successor_state')
   else:
    value=json.loads(state[2]);manifest=value.get('manifest_sha256') if isinstance(value,dict) else None
    require(count==1 and isinstance(manifest,str) and len(manifest)==64
            and all(c in '0123456789abcdef' for c in manifest)
            and state[2]==canonical(dict(TV_SUCCESSOR['install'],manifest_sha256=manifest))
            and ('install' not in accepted or state[2]==canonical(accepted['install'])),'tv_successor_state')
  reservation=json.loads(rows['reservation.json'][2])
  fixed={k:TV_SUCCESSOR[k] for k in ('operation_id','source_sha','mode')}
  require(isinstance(reservation,dict) and set(reservation)==set(fixed)|{'reserved_at'}
          and all(type(reservation[k]) is type(v) and reservation[k]==v for k,v in fixed.items())
          and type(reservation['reserved_at']) is int
          and rows['reservation.json'][2]==json.dumps(reservation,sort_keys=True).encode(),'tv_successor_identity')
  require(proof['started_at']<=reservation['reserved_at']<=proof['completed_at'],'tv_successor_time')
  plan={'schema_version':1,'source_sha':TV_SUCCESSOR['source_sha'],'files':TV_SUCCESSOR['files'],
        'changed_files':[TV_SUCCESSOR['relative']],
        'previous':{p:{'exists':True,'sha256':h,'mode':0o644} for p,h in TV_SUCCESSOR['previous'].items()},
        'expected':TV_SUCCESSOR['expected'],'status':'prepared'}
  require(rows['anex-preview-install-plan.json'][2]==canonical(plan),'tv_successor_plan')
  require(len(plan['files'])==len(plan['previous'])==len(plan['expected'])==12,'tv_successor_plan')
  for path,expected in TV_SUCCESSOR['previous'].items():
   require(rows['backup/'+path][0]['sha256']==expected,'tv_successor_backup')
  prior=dict(rows['backup/'+TV_SUCCESSOR['relative']][0],mode='0644')
  require(prior['sha256']==TV_SUCCESSOR['before_sha256'],'tv_successor_backup')
  runtime={}
  runtime_root=TV_SUCCESSOR['path'][:-len(TV_SUCCESSOR['relative'])]
  for path,expected in (TV_SUCCESSOR['expected'] if installed else TV_SUCCESSOR['previous']).items():
   item=public.read(runtime_root+path)[:2]
   require(item[0].get('state')=='present' and item[0].get('sha256')==expected
           and item[0].get('mode')=='0644' and item[1][6]==os.getuid(),'tv_successor_state')
   runtime[path]=item
  # File stamps include device/inode/mode/nlink/uid and both modification times.
  # Retain raw records as well so a same-content replacement is still detected.
  return {'target':current,'records':rows,'backup':prior,'directories':directories,'runtime':runtime}
 finally:store.close()
def tv_successor_stable():
 if TV_SUCCESSOR_PROOF is not None and _tv_successor_bound is not None:
  require(tv_successor_read()==_tv_successor_bound,'tv_successor_changed')
def tv_successor_expected(snapshot):
 global _tv_successor_bound
 if TV_SUCCESSOR_PROOF is None:return snapshot
 path=TV_SUCCESSOR['path']
 require(isinstance(snapshot,dict) and path in snapshot,'tv_successor_snapshot')
 observed=tv_successor_read()
 if _tv_successor_bound is None:_tv_successor_bound=observed
 else:require(observed==_tv_successor_bound,'tv_successor_changed')
 # A later SITE snapshot may already contain the admitted successor. The old
 # public snapshots continue to contain its independently checked backup row.
 require(snapshot[path]==observed['backup'] or
         TV_SUCCESSOR_PROOF['status']=='installed' and snapshot[path]==observed['target'][0],
         'tv_successor_snapshot')
 return dict(snapshot,**{path:observed['target'][0]})
'''


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
  check=globals().get('tv_successor_stable')
  if check is not None:check()
  rows={path:self.read(path)[:2] for path in paths}
  if check is not None:check()
  return rows
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
 if globals().get('TV_SUCCESSOR_PROOF') is not None and path==TV_SUCCESSOR['path']:
  return _tv_successor_bound is not None and row==_tv_successor_bound['target'][0]
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
 require({p:r[0] for p,r in protected.items()}==tv_successor_expected(snapshot['protected'])
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
ROLLBACK_REASONS = REASONS + TV_SUCCESSOR_REASONS + ('rollback_private_record','rollback_original_identity','rollback_snapshot_conflict',
                             'rollback_candidate_changed','rollback_preflight_race','rollback_entry_readback',
                             'rollback_addition_conflict','rollback_incomplete')


def rollback_remote_program(policy, identity, original, accepted, tv_successor=None):
    need(policy.get('operation') in (OPERATION, CONTINUATION_OPERATION) and policy.get('read_only') is False, 'mode')
    need(original['run_id'] != CONTINUATION_PARENT['run_id']
         and (original.get('operation_id') == CONTINUATION_OPERATION) == (policy['operation'] == CONTINUATION_OPERATION), 'rollback_install_identity')
    need(valid_result(original, policy, accepted), 'rollback_install_evidence')
    prefix = ('MAX_BYTES=' + repr(MAX_BYTES) + '\nPOLICY=' + repr(policy) + '\nIDENTITY=' + repr(identity)
              + '\nORIGINAL=' + repr(original) + '\nACCEPTED=' + repr(accepted)
              + '\nROLLBACK_OPERATION=' + repr(ROLLBACK_OPERATION) + '\n')
    prefix += 'def re_safe(value): return value in ' + repr(ROLLBACK_REASONS) + '\n'
    # Share only filesystem definitions, never the inspect/install branches.
    program = (prefix + tv_successor_prefix(tv_successor) + REMOTE.split('\ndef inspect_only():', 1)[0]
               + TV_SUCCESSOR_REMOTE + ROLLBACK_REMOTE)
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
 require({p:r[0] for p,r in protected.items()}==tv_successor_expected(snapshot['protected'])
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
         and tv_successor_expected(snapshot['protected'])=={p:r[0] for p,r in protected.items()} and snapshot['parent_records']==parent_hashes(retained)
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


def _update_program_prefix(policy, identity, original, accepted, tv_successor=None):
    return ('MAX_BYTES=' + repr(MAX_BYTES) + '\nPOLICY=' + repr(policy['previous'])
            + '\nUPDATE_POLICY=' + repr(policy) + '\nIDENTITY=' + repr(identity)
            + '\nORIGINAL=' + repr(original) + '\nPARENT_ACCEPTED=' + repr(accepted)
            + '\ndef re_safe(value): return value in ' + repr(UPDATE_REASONS) + '\n'
            + tv_successor_prefix(tv_successor) + REMOTE.split('\ndef inspect_only():', 1)[0]
            + TV_SUCCESSOR_REMOTE + UPDATE_COMMON_REMOTE)


def update_remote_program(policy, files, identity, original, accepted, tv_successor=None):
    update_policy_shape(policy); update_runtime_identity(identity); update_parent_result(policy, original, accepted)
    need(policy['release'] == UPDATE_RELEASE and isinstance(files, dict) and set(files) == {UPDATE_PATH}, 'update_payload')
    raw = base64.b64decode(files[UPDATE_PATH], validate=True)
    need(UPDATE_AFTER == {'state': 'present', 'sha256': sha(raw), 'size': len(raw), 'mode': '0644'}, 'update_asset_bytes')
    program = _update_program_prefix(policy, identity, original, accepted, tv_successor) + '\nPAYLOAD=' + repr(files) + '\n' + UPDATE_REMOTE
    ast.parse(program)
    return program


def update_rollback_remote_program(policy, identity, original, accepted, parent, parent_accepted, tv_successor=None):
    update_policy_shape(policy); update_runtime_identity(identity); update_runtime_identity(original)
    need(identity['run_id'] != original['run_id'] and identity['command_comment_id'] != original['command_comment_id'], 'update_rollback_identity')
    update_parent_result(policy, parent, parent_accepted)
    need(valid_update_result(original, policy, accepted), 'update_rollback_evidence')
    program = (_update_program_prefix(policy, identity, parent, parent_accepted, tv_successor)
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
            dependency = tv_successor_evidence()
            program = update_rollback_remote_program(policy, identity, original, accepted, update_parent_identity(), parent_accepted,
                                                     tv_successor=dependency)
            evidence.update(parent_install=update_parent_identity(), original_update=original)
        else:
            identity = update_event_identity(event, os.environ)
            evidence.update(identity, phase='admission'); update_admission(identity)
            evidence['phase'] = 'artifact'
            previous, previous_files = package(download(ARTIFACT, ARTIFACT_SHA), continuation=True)
            parent_accepted = update_parent_evidence(download(UPDATE_PARENT['artifact_id'], UPDATE_PARENT['artifact_sha256']), previous)
            policy, files = update_package(download(UPDATE_ARTIFACT, UPDATE_ARTIFACT_SHA), previous, previous_files)
            dependency = tv_successor_evidence()
            program = update_remote_program(policy, files, identity, update_parent_identity(), parent_accepted, tv_successor=dependency)
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
        need(tv_successor_evidence() == dependency, 'tv_successor_changed')
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


def site_policy(continuation=False):
    need(type(continuation) is bool, 'mode')
    policy = {'operation': SITE_CONTINUATION_OPERATION if continuation else SITE_OPERATION, 'source': SITE_SOURCE, 'tree': SITE_TREE,
              'order': SITE_ORDER, 'targets': SITE_TARGETS, 'dependencies': SITE_DEPENDENCIES,
              'optional_dependencies': SITE_OPTIONAL_DEPENDENCIES, 'parent': SITE_PARENT}
    if continuation: policy['continuation'] = site_continuation_provenance()
    return policy | {'manifest': sha(json.dumps(policy, sort_keys=True, separators=(',', ':')).encode())}


def site_event_identity(event, env, rollback=False, continuation=False):
    # Apply exactly the existing carrier's owner/event/attempt gate, then parse
    # only these two fixed commands. The event body is never passed to a shell.
    need(type(rollback) is type(continuation) is bool, 'mode')
    if continuation:
        prefix, operation = ((SITE_CONTINUATION_ROLLBACK_PREFIX, SITE_CONTINUATION_ROLLBACK_OPERATION) if rollback
                             else (SITE_CONTINUATION_PREFIX, SITE_CONTINUATION_OPERATION))
    else:
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
    if continuation:
        excluded = (SITE_INSPECT_FAILED['run_id'], SITE_CONTINUATION_INSPECTION['run_id'], SITE_CONTINUATION_COLLECTOR['run_id'])
        need(identity['run_id'] not in excluded and original_run not in excluded, 'site_runtime_identity')
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


def site_admission(identity, continuation=False):
    need(type(continuation) is bool, 'mode')
    heads = site_inspect_heads if continuation else site_final_heads
    heads(identity); reviewed_control(identity['control_source_sha'])
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
    if continuation: site_continuation_admission(identity)
    else: _unique_update_command(pages('/issues/4709/comments'), SITE_PREFIX, SITE_OPERATION, identity)
    heads(identity)


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
 site_continuation_namespace_absent()
 retained=parent_records();candidate=parent_candidate(retained,True);protected=parent_protected(retained)
 rows=read_records(updated,UPDATE_RECORD_PATHS);record=lambda p:json.loads(rows[p][2]);path=UPDATE_POLICY['path']
 require(record('reservation.json')=={'operation':UPDATE_POLICY['operation'],**UPDATE_ORIGINAL,
         'source':UPDATE_POLICY['source'],'manifest':UPDATE_POLICY['manifest'],'parent_install':ORIGINAL}
         and record('result.json')==UPDATE_ACCEPTED,'update_original_identity')
 snapshot=record('snapshot/manifest.json')
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_install','path','before','protected','previous_identity','parent_records'}
         and snapshot['source']==UPDATE_POLICY['source'] and snapshot['manifest']==UPDATE_POLICY['manifest']
         and snapshot['parent_install']==ORIGINAL and snapshot['path']==path and snapshot['before']==POLICY['expected']
         and tv_successor_expected(snapshot['protected'])=={p:r[0] for p,r in protected.items()} and snapshot['parent_records']==parent_hashes(retained)
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
 site_continuation_namespace_absent()
 require(parent_records()==retained and read_records(updated,UPDATE_RECORD_PATHS)==update_records
         and updated.read('stage/app')[:2]==update_prior,'site_parent_changed')
 require(public.inventory(guarded)==guarded,'protected_changed')
def site_check_targets(expected):
 require(public.inventory(SITE_POLICY['order'])==expected,'site_target_changed')
def site_continuation_namespace_absent():
 if 'continuation' not in SITE_POLICY: return
 with base.parent(SITE_POLICY['continuation']['failed']['operation_id']) as (fd,name):
  try: os.stat(name,dir_fd=fd,follow_symlinks=False)
  except FileNotFoundError:
   result['failed_namespace_absent']=True
   return
  result['failed_namespace_absent']=False
  raise RuntimeError('site_failed_operation_exists')
def site_restore_one(store,path,index,current,prior,capture):
 site_continuation_namespace_absent()
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
if 'continuation' in SITE_POLICY: result.update(continuation=SITE_POLICY['continuation'],failed_namespace_absent=False)
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
   site_continuation_namespace_absent()
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
if 'continuation' in SITE_POLICY: result.update(continuation=SITE_POLICY['continuation'],failed_namespace_absent=False)
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
         and tv_successor_expected(snapshot['guarded'])=={p:row[0] for p,row in guarded.items()}
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


def _site_program_prefix(policy, identity, parent_value, continuation=False, *, tv_successor=None):
    need(policy == site_policy(continuation), 'site_policy')
    update_runtime_identity(identity); need(identity['run_id'] != SITE_PARENT['run_id'], 'site_runtime_identity')
    previous, accepted, original = site_parent_value(parent_value)
    need(not set(SITE_ORDER) & (set(previous['previous']['expected']) | set(previous['previous']['protected'])
              | set(SITE_DEPENDENCIES) | set(SITE_OPTIONAL_DEPENDENCIES))
         and not set(SITE_DEPENDENCIES) & set(SITE_OPTIONAL_DEPENDENCIES), 'site_scope')
    return (_update_program_prefix(previous, identity, update_parent_identity(), original, tv_successor=tv_successor)
            + '\nSITE_POLICY=' + repr(policy) + '\nUPDATE_ORIGINAL=' + repr(site_parent_identity())
            + '\nUPDATE_ACCEPTED=' + repr(accepted)
            + '\ndef re_safe(value): return value in ' + repr(SITE_REASONS + ('site_failed_operation_exists',) if continuation else SITE_REASONS) + '\n' + SITE_COMMON_REMOTE)


def site_remote_program(policy, files, identity, parent_value, continuation=False, *, tv_successor=None):
    prefix = _site_program_prefix(policy, identity, parent_value, continuation, tv_successor=tv_successor)
    need(isinstance(files, dict) and set(files) == set(SITE_ORDER), 'site_payload')
    for path in SITE_ORDER:
        raw = base64.b64decode(files[path], validate=True)
        need(raw.startswith(b'<?php') and SITE_TARGETS[path]['after'] ==
             {'state':'present','sha256':sha(raw),'size':len(raw),'mode':'0644'}, 'site_payload_bytes')
    program = prefix + '\nPAYLOAD=' + repr(files) + '\n' + SITE_UPDATE_REMOTE
    ast.parse(program)
    return program


def site_rollback_remote_program(policy, identity, original, accepted, parent_value, continuation=False, *, tv_successor=None):
    prefix = _site_program_prefix(policy, identity, parent_value, continuation, tv_successor=tv_successor)
    update_runtime_identity(original)
    need(identity['run_id'] != original['run_id'] and identity['command_comment_id'] != original['command_comment_id']
         and valid_site_result(original, accepted, continuation=continuation), 'site_rollback_evidence')
    program = (prefix + '\nSITE_ORIGINAL=' + repr(original) + '\nSITE_ACCEPTED=' + repr(accepted)
               + '\nSITE_ROLLBACK_OPERATION=' + repr(SITE_CONTINUATION_ROLLBACK_OPERATION if continuation else SITE_ROLLBACK_OPERATION) + '\n' + SITE_ROLLBACK_REMOTE)
    ast.parse(program)
    return program


def valid_site_result(identity, result, original=None, accepted=None, continuation=False):
    rollback = original is not None
    policy = site_policy(continuation)
    rollback_operation = SITE_CONTINUATION_ROLLBACK_OPERATION if continuation else SITE_ROLLBACK_OPERATION
    fixed = {'schema_version':1,'operation_id':rollback_operation if rollback else policy['operation'],
             'source_sha':SITE_SOURCE,'manifest_sha256':policy['manifest'],**identity,
             'parent_update':site_parent_identity(),'parent_receipt_sha256':SITE_PARENT['receipt_sha256']}
    if continuation: fixed['continuation'] = policy['continuation']
    if rollback: fixed.update(original_site=original,snapshot_manifest_sha256=accepted['snapshot_manifest_sha256'])
    counters = ('supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
    flags = ('reservation_created','snapshot_verified','restore_drill_verified','protected_unchanged',
             'unchanged_files_verified','parent_records_unchanged','original_inodes_verified')
    if continuation: flags += ('failed_namespace_absent',)
    required = set(fixed) | set(counters) | set(flags) | {'status','phase','written_files','rollback_files','snapshot_manifest_sha256'}
    need(isinstance(result, dict) and required <= set(result) <= required | {'reason'}, 'site_receipt_shape')
    need(all(type(result.get(k)) is type(v) and result[k] == v for k,v in fixed.items()), 'site_receipt_identity')
    need(all(type(result.get(k)) is int and result[k] == 0 for k in counters), 'site_receipt_counters')
    need(all(type(result[k]) is bool for k in flags), 'site_receipt_flags')
    success = 'site_rolled_back_verified' if rollback else 'site_updated_verified'
    states = ('blocked_no_replay','unknown_no_replay',success) if rollback else ('blocked_no_replay','unknown_no_replay','rolled_back_no_replay',success)
    need(result['status'] in states and result['phase'] in ('preflight','snapshot','assets','complete')
         and ('reason' not in result or result['reason'] in SITE_REASONS + (('site_failed_operation_exists',) if continuation else ())), 'site_receipt_status')
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


def site_rollback_commands(identity, original_run, expected_original=None, continuation=False):
    need(type(continuation) is bool, 'mode')
    prefix, operation = (SITE_CONTINUATION_PREFIX, SITE_CONTINUATION_OPERATION) if continuation else (SITE_PREFIX, SITE_OPERATION)
    rollback_prefix, rollback_operation = ((SITE_CONTINUATION_ROLLBACK_PREFIX, SITE_CONTINUATION_ROLLBACK_OPERATION) if continuation
                                           else (SITE_ROLLBACK_PREFIX, SITE_ROLLBACK_OPERATION))
    need(isinstance(original_run,str) and re.fullmatch('[1-9][0-9]{0,19}',original_run)
         and original_run not in (identity['run_id'],SITE_PARENT['run_id'],UPDATE_PARENT['run_id'],CONTINUATION_PARENT['run_id']), 'site_rollback_run')
    if continuation:
        need(original_run not in (SITE_INSPECT_FAILED['run_id'],SITE_CONTINUATION_INSPECTION['run_id'],SITE_CONTINUATION_COLLECTOR['run_id']), 'site_rollback_run')
    comments = pages('/issues/4709/comments')
    _unique_update_command(comments,rollback_prefix,rollback_operation,identity,original_run)
    installed = [c for c in comments if c.get('body','').strip().split()[:1]==[prefix]
                 and operation in c.get('body','').split()[1:]]
    need(len(installed)==1 and installed[0].get('user',{}).get('id')==226193297
         and installed[0].get('author_association')=='OWNER', 'site_rollback_command')
    match = re.fullmatch(re.escape(prefix)+r' ([a-f0-9]{40}) '+re.escape(operation),installed[0].get('body',''))
    need(match is not None, 'site_rollback_command')
    original = {'control_source_sha':match[1],'run_id':original_run,'command_comment_id':installed[0]['id']}
    update_runtime_identity(original)
    need(expected_original is None or original==expected_original,'site_rollback_command')
    return original


def site_rollback_admission(identity, original_run, continuation=False):
    rollback_main(identity);reviewed_control(identity['control_source_sha'])
    original=site_rollback_commands(identity,original_run,continuation=continuation)
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
    if continuation: need(steps[0].get('name')=='Run python3 scripts/deploy/search3_public_install.py site-continue','site_rollback_step')
    name='search3-public-install-'+original_run+'-1'
    artifacts=[a for a in pages('/actions/runs/'+original_run+'/artifacts','artifacts') if a.get('name')==name]
    need(len(artifacts)==1 and artifacts[0].get('expired') is False
         and artifacts[0].get('workflow_run',{}).get('id')==int(original_run)
         and re.fullmatch(r'sha256:[a-f0-9]{64}',artifacts[0].get('digest','')),'site_rollback_artifact')
    rollback_main(identity)
    return original,artifacts[0]


def site_source_evidence(raw, original, continuation=False):
    update_runtime_identity(original)
    with zip_files(raw) as archive:
        need(archive.namelist()==['evidence.json'],'site_rollback_artifact')
        value=json.loads(archive.read('evidence.json'))
    policy=site_policy(continuation)
    fixed={**original,'operation_id':policy['operation'],'source_sha':SITE_SOURCE,'manifest_sha256':policy['manifest'],
           'read_only':False,'phase':'complete','ssh_started':True,'status':'site_updated_verified',
           'parent_update':site_parent_identity()}
    extra={'continuation_evidence','collector_evidence'} if continuation else set()
    need(isinstance(value,dict) and set(value)==set(fixed)|{'remote','parent_evidence'}|extra
         and all(type(value.get(k)) is type(v) and value[k]==v for k,v in fixed.items()),'site_rollback_evidence')
    site_parent_value(value['parent_evidence'])
    if continuation:
        parent=site_continuation_inspection_value(value['continuation_evidence'])
        need(parent==value['parent_evidence'],'site_continuation_parent')
        need(value['collector_evidence']==site_continuation_collector_value(),'site_collector_evidence')
    need(valid_site_result(original,value['remote'],continuation=continuation),'site_rollback_evidence')
    return policy,value['remote'],value['parent_evidence']


def site_update(rollback=False, continuation=False):
    need(type(rollback) is type(continuation) is bool,'mode')
    policy=site_policy(continuation)
    operation=(SITE_CONTINUATION_ROLLBACK_OPERATION if continuation else SITE_ROLLBACK_OPERATION) if rollback else policy['operation']
    work=Path(os.environ['RUNNER_TEMP'])/'search3-public-install';work.mkdir(mode=0o700)
    key,known=work/'key',work/'known'
    evidence={'status':'blocked_no_replay','operation_id':operation,'source_sha':SITE_SOURCE,
              'manifest_sha256':policy['manifest'],'read_only':False,'phase':'identity','ssh_started':False}
    complete=False
    try:
        event=json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
        if rollback:
            identity,original_run=site_event_identity(event,os.environ,True,continuation)
            evidence.update(identity,original_site_run_id=original_run,phase='admission')
            original,artifact=site_rollback_admission(identity,original_run,continuation);evidence['phase']='artifact'
            policy,accepted,parent_value=site_source_evidence(download(artifact['id'],artifact['digest'].removeprefix('sha256:')),original,continuation)
            dependency=tv_successor_evidence()
            program=site_rollback_remote_program(policy,identity,original,accepted,parent_value,continuation,tv_successor=dependency)
            evidence.update(parent_update=site_parent_identity(),original_site=original)
        else:
            identity=site_event_identity(event,os.environ,continuation=continuation);evidence.update(identity,phase='admission');site_admission(identity,continuation)
            evidence['phase']='artifact'
            if continuation:
                inspection=site_continuation_inspection_evidence(download(SITE_CONTINUATION_INSPECTION['artifact_id'],SITE_CONTINUATION_INSPECTION['artifact_sha256']))
                parent_value=site_continuation_inspection_value(inspection)
                evidence.update(continuation_evidence=inspection,collector_evidence=site_continuation_collector_log())
            else: parent_value=site_parent_evidence(download(SITE_PARENT['artifact_id'],SITE_PARENT['artifact_sha256']))
            dependency=tv_successor_evidence()
            program=site_remote_program(policy,site_package(),identity,parent_value,continuation,tv_successor=dependency)
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
        if rollback: site_rollback_commands(identity,original_run,original,continuation);rollback_main(identity)
        else: site_admission(identity,continuation)
        need(tv_successor_evidence()==dependency,'tv_successor_changed')
        evidence.update(status='unknown_no_replay',phase='remote',ssh_started=True)
        response=carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'],input=program.encode(),timeout=180)
        need(len(response)<=32768,'receipt_limit');result=json.loads(response)
        complete=valid_site_result(identity,result,original,accepted,continuation) if rollback else valid_site_result(identity,result,continuation=continuation)
        evidence['remote']=result
        if rollback: rollback_main(identity)
        else: (site_inspect_heads if continuation else site_final_heads)(identity)
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


# One child of the exact inspected, zero-write SITE refusal. Historical SITE
# commands, policies and private records above stay immutable and consumed.
SITE_CONTINUATION_PREFIX = '/continue-site-shared-assets'
SITE_CONTINUATION_OPERATION = 'site-shared-assets-after-44897684408-20261121-v1'
SITE_CONTINUATION_ROLLBACK_PREFIX = '/rollback-site-shared-assets-continuation'
SITE_CONTINUATION_ROLLBACK_OPERATION = 'site-shared-assets-after-44897684408-rollback-20261121-v1'
SITE_CONTINUATION_INSPECTION = {
    'operation_id': SITE_INSPECT_OPERATION, 'control_source_sha': '0116055dce2c01eb4fe8ec00589f71ce910f6382',
    'run_id': '44899600338', 'command_comment_id': 7061962251, 'job_id': 136832515983,
    'artifact_id': 14705151965,
    'artifact_sha256': '6b95b95821147023c95feaad01287b732c339bc9f69d9587b22a1d0eed3cd32e',
    'receipt_sha256': '9aa06a15a61eab4f5e6f0f5fe33154b105d899563c2193d886dfcf7d2e3f30a9',
    'terminal_comment_id': 7062069463,
}
SITE_CONTINUATION_DB_OBSERVED = {'state':'present', 'mode':'0644', 'size':3861,
    'sha256':'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4'}
SITE_CONTINUATION_COLLECTOR = {
    'run_id':'44907231885', 'job_id':136855488291,
    'source_sha':'e3b452094949a0941f014c8cdf27b7c044a8ae84',
    'workflow':'.github/workflows/collect-anytour-tours.yml',
    'db_sha256':'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13',
    'observer_sha256':'7112b84b7de1ddc901663f48bacc120c6f870b41ea7b39ba2b472e94a8564ae5',
    # Exact archived bytes, including GitHub's UTF-8 BOM; original attempt1
    # completed successfully. A checkpoint or echoed shell text is not it.
    'log_sha256':'5025e500f92893a9b5e76cba8e305af97a946ba84c91f0c65f4d695766c9fd6b',
}


def site_continuation_provenance():
    return {'failed':dict(SITE_INSPECT_FAILED), 'inspection':dict(SITE_CONTINUATION_INSPECTION),
            'collector':dict(SITE_CONTINUATION_COLLECTOR)}


def site_continuation_fixed_run(pin, conclusion, mode):
    # Internal callers pass only the two literal historical proofs above.
    need(pin in (SITE_INSPECT_FAILED, SITE_CONTINUATION_INSPECTION), 'site_continuation_pin')
    run=api('/actions/runs/'+pin['run_id'])
    need(run.get('id')==int(pin['run_id']) and run.get('head_sha')==pin['control_source_sha']
         and run.get('head_branch')=='main' and run.get('path')==WORKFLOW and run.get('event')=='issue_comment'
         and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')==conclusion
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff', 'site_continuation_run')
    jobs=[j for j in pages('/actions/runs/'+pin['run_id']+'/attempts/1/jobs','jobs') if j.get('name')=='public-install']
    need(len(jobs)==1 and jobs[0].get('id')==pin['job_id'] and jobs[0].get('run_id')==int(pin['run_id'])
         and jobs[0].get('status')=='completed' and jobs[0].get('conclusion')==conclusion, 'site_continuation_job')
    steps=[s for s in jobs[0].get('steps',[]) if s.get('name','').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps)==1 and steps[0].get('name')=='Run python3 scripts/deploy/search3_public_install.py '+mode
         and steps[0].get('status')=='completed' and steps[0].get('conclusion')==conclusion, 'site_continuation_step')
    artifact=api('/actions/artifacts/'+str(pin['artifact_id']))
    need(artifact.get('id')==pin['artifact_id'] and artifact.get('name')=='search3-public-install-'+pin['run_id']+'-1'
         and artifact.get('expired') is False and artifact.get('workflow_run',{}).get('id')==int(pin['run_id'])
         and artifact.get('digest')=='sha256:'+pin['artifact_sha256'], 'site_continuation_artifact')


def site_continuation_collector_admission():
    pin=SITE_CONTINUATION_COLLECTOR
    need(isinstance(pin['log_sha256'],str) and re.fullmatch('[a-f0-9]{64}',pin['log_sha256']), 'site_collector_unaccepted')
    run=api('/actions/runs/'+pin['run_id'])
    need(run.get('id')==int(pin['run_id']) and run.get('head_sha')==pin['source_sha']
         and run.get('head_branch')=='main' and run.get('path')==pin['workflow'] and run.get('event')=='workflow_dispatch'
         and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')=='success'
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff', 'site_collector_run')
    jobs=[j for j in pages('/actions/runs/'+pin['run_id']+'/attempts/1/jobs','jobs') if j.get('name')=='collect-production']
    need(len(jobs)==1 and jobs[0].get('id')==pin['job_id'] and jobs[0].get('run_id')==int(pin['run_id'])
         and jobs[0].get('status')=='completed' and jobs[0].get('conclusion')=='success', 'site_collector_job')
    steps=[s for s in jobs[0].get('steps',[]) if s.get('name')=='Collect due TOP500 months and refresh production aggregates']
    need(len(steps)==1 and steps[0].get('status')=='completed' and steps[0].get('conclusion')=='success', 'site_collector_step')


def site_continuation_admission(identity):
    site_continuation_fixed_run(SITE_INSPECT_FAILED,'failure','site-update')
    site_continuation_fixed_run(SITE_CONTINUATION_INSPECTION,'success','site-inspect')
    comments=pages('/issues/4709/comments')
    for pin,prefix,operation,tokens in (
        (SITE_INSPECT_FAILED,SITE_PREFIX,SITE_OPERATION,['blocked_no_replay','dependency_drift','NO_REPLAY']),
        (SITE_CONTINUATION_INSPECTION,SITE_INSPECT_PREFIX,SITE_INSPECT_OPERATION,['inspection_mismatch','data/db-v1.php'])):
        original={k:pin[k] for k in ('control_source_sha','run_id','command_comment_id')}
        _unique_update_command(comments,prefix,operation,original)
        terminal=next((c for c in comments if c['id']==pin['terminal_comment_id']),{})
        required=[str(pin[k]) for k in ('run_id','control_source_sha','command_comment_id','job_id','artifact_id','artifact_sha256','receipt_sha256')]
        need(terminal.get('user',{}).get('id')==226193297 and terminal.get('author_association')=='OWNER'
             and all(token in terminal.get('body','') for token in required+tokens), 'site_continuation_terminal')
    _unique_update_command(comments,SITE_CONTINUATION_PREFIX,SITE_CONTINUATION_OPERATION,identity)
    site_continuation_collector_admission()


def site_continuation_inspection_value(value):
    pin=SITE_CONTINUATION_INSPECTION
    need(isinstance(value,dict) and sha((json.dumps(value,sort_keys=True)+'\n').encode())==pin['receipt_sha256'], 'site_continuation_inspection_digest')
    identity={k:pin[k] for k in ('control_source_sha','run_id','command_comment_id')}
    fixed={**identity,'operation_id':SITE_INSPECT_OPERATION,'source_sha':SITE_SOURCE,'manifest_sha256':site_policy()['manifest'],
           'read_only':True,'phase':'complete','ssh_started':True,'status':'inspection_mismatch','failed_attempt':SITE_INSPECT_FAILED}
    need(set(value)==set(fixed)|{'remote','failed_evidence'}
         and all(type(value.get(k)) is type(v) and value[k]==v for k,v in fixed.items()), 'site_continuation_inspection')
    parent=site_inspect_failed_value(value['failed_evidence'])
    result=value['remote'];need(valid_site_inspection_result(identity,result),'site_continuation_inspection')
    mismatches=[p for p,row in result['protected_comparisons'].items() if not(row['matches_parent_snapshot'] and row['matches_compiled_policy'])]
    row=result['protected_comparisons'].get('data/db-v1.php',{})
    need(mismatches==['data/db-v1.php'] and row=={'matches_parent_snapshot':False,'matches_compiled_policy':False,'observed':SITE_CONTINUATION_DB_OBSERVED}
         and result['site_mismatches']==[{'path':'data/db-v1.php','optional':False,'observed':SITE_CONTINUATION_DB_OBSERVED}]
         and not result['overlay_mismatches'] and all(r['matches_predecessor'] and not r['matches_source'] for r in result['target_comparisons'].values())
         and all(r['readable'] and r['matches_parent_snapshot'] and r['matches_compiled_policy'] for r in result['private_checks'].values()), 'site_continuation_inspection_scope')
    return parent


def site_continuation_inspection_evidence(raw):
    pin=SITE_CONTINUATION_INSPECTION
    need(sha(raw)==pin['artifact_sha256'],'site_continuation_inspection_artifact')
    with zip_files(raw) as archive:
        need(archive.namelist()==['evidence.json'],'site_continuation_inspection_artifact')
        data=archive.read('evidence.json');need(sha(data)==pin['receipt_sha256'],'site_continuation_inspection_digest')
    value=json.loads(data);site_continuation_inspection_value(value)
    return value


def site_continuation_collector_value():
    pin=SITE_CONTINUATION_COLLECTOR
    need(isinstance(pin['log_sha256'],str) and re.fullmatch('[a-f0-9]{64}',pin['log_sha256']), 'site_collector_unaccepted')
    return {**pin,'status':'completed','conclusion':'success','run_attempt':1,'installed_output_verified':True}


def site_continuation_collector_evidence(raw):
    pin=SITE_CONTINUATION_COLLECTOR
    need(isinstance(raw,bytes) and 0<len(raw)<=MAX_ZIP and sha(raw)==pin['log_sha256'],'site_collector_log_digest')
    # GitHub's timestamped output is distinct from its echoed run-script lines.
    # Duplicate or contradictory native markers are not an accepted correction.
    pattern=r'\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z (ANYTOUR_LOCAL_PRICE_READBACK_[A-Z_]+(?: .*)?)'
    observed=[match.group(1) for line in raw.decode('utf-8').splitlines() if (match:=re.fullmatch(pattern,line))]
    expected='ANYTOUR_LOCAL_PRICE_READBACK_OK db='+pin['db_sha256']+' observer='+pin['observer_sha256']
    need(observed==[expected], 'site_collector_installed_output')
    return site_continuation_collector_value()


def site_continuation_collector_log():
    # Same authenticated GitHub / unauthenticated allowlisted HTTPS redirect
    # boundary as download(); the one fixed job is never supplied by a caller.
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self,*args,**kwargs): return None
    pin=SITE_CONTINUATION_COLLECTOR;site_continuation_collector_value()
    opener=urllib.request.build_opener(NoRedirect())
    request=urllib.request.Request(f'https://api.github.com/repos/{REPO}/actions/jobs/{pin["job_id"]}/logs',
        headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json'})
    try: response=opener.open(request,timeout=30)
    except urllib.error.HTTPError as error:
        need(error.code==302,'site_collector_redirect')
        location=error.headers.get('Location','');url=urllib.parse.urlsplit(location)
        need(url.scheme=='https' and url.hostname and url.port in (None,443) and not url.username and not url.password and not url.fragment
             and (url.hostname.endswith('.blob.core.windows.net') or url.hostname.endswith('.actions.githubusercontent.com')),'site_collector_redirect')
        response=opener.open(urllib.request.Request(location),timeout=30)
    with response: raw=response.read(MAX_ZIP+1)
    return site_continuation_collector_evidence(raw)


"""Unpublished fixed Search3 two-file successor fragment.

Insert before command_mode in the reviewed publisher only after the INT owner
releases its source claim. This file has no CLI or runnable default.
Live integration requires the already reviewed tv_successor helpers from PR5142.
"""

REFACTOR_PREFIX = '/update-search3-refactor'
REFACTOR_OPERATION = 'public-search3-refactor-20261121-v1'
REFACTOR_ROLLBACK_PREFIX = '/rollback-search3-refactor'
REFACTOR_ROLLBACK_OPERATION = 'public-search3-refactor-rollback-20261121-v1'
REFACTOR_SOURCE_PR = 5139
REFACTOR_SOURCE = 'cb1a11dffa9b63a0811d0339483a22fcbbcd5a91'
REFACTOR_TREE = 'dc9623b7d4bb69e3d168b6ebe144f2ceb2292883'
REFACTOR_SOURCE_MERGE = '73268818dd13c162f543ec3318b890548184edb9'
REFACTOR_RELEASE = 'aa4258b0fce33da56452e8dbfffd27273e67ba73'
REFACTOR_RELEASE_TREE = 'f48d000820f424dac523bda7920cf0aebd75f743'
REFACTOR_COMPILER_HEAD = 'bf586771b563fd42dcce524f0ca46217a17ba4f9'
# One reviewed later release; the immutable compiler basis and refactor policy
# above remain fixed. These four LOCAL source paths are outside the LIVE payload.
REFACTOR_RELEASE_SUCCESSOR = {
    'pr':5144,'head_sha':'de82f20ec26435add5f627567a035ee96ad18811',
    'head_ref':'local/official11-source-20261121',
    'merge_sha':'8b804957501d5b4715bd6f400fb794f02792d3de',
    'tree_sha':'a6352ca4d34f6a2dd4fe07b9eba5391bd017cc18',
    'files':{'.github/workflows/search3-anytour-profile-enrichment-v1.yml':'modified',
             'tests/anytour-profile-official-review-v1-test.php':'added',
             'v2/data/anytour-profile-enrichment-v1.php':'modified',
             'v2/data/anytour-profile-official-review-v1.php':'added'},
}
REFACTOR_BUILD_RUN = 44906779297
REFACTOR_ARTIFACT = 14708066643
REFACTOR_ARTIFACT_SHA = 'ea7a0213646150537cf05f0a13f9f6b5b1d2c804552133d131e81f6345f64c8d'
REFACTOR_MANIFEST_SHA = '0d65a8cb7c119a97b2fda3bc3bafb8f5075d55c48c5a83812bab5e702126f751'
REFACTOR_CONTRACT_KEYS = ('schema_version','purpose','deployable','profile','network_used','checkout_changed',
    'published','main','source_scope','served_root','overlay_root','overlay_scope','resource_base','public_path',
    'aliases','live_graph','graph','rollback','boundaries','server_dependencies')
REFACTOR_CONTRACT_SHA = '0c8619ef530967c27ab5c3f7d17d0e02971dcecb2d666d740e27503aec7a0ddc'  # Unchanged graph, dependencies, and all 733 protected manifest rows.
REFACTOR_PARENT = {
    'operation_id':'site-shared-assets-after-44897684408-20261121-v1',
    'control_source_sha':'ac061f7afa94004d0077ea59ed733875b5ae22f9',
    'run_id':'44910720726','command_comment_id':7063600308,'job_id':136865752676,
    'artifact_id':14708929961,
    'artifact_sha256':'1ffb6381ce3d819771abc04d4304224e219eb6476f2ee57cc415078492b9cfb3',
    'receipt_sha256':'59744d56f8c46e008b24150ad6919e892276305a13bc2c5b15d1fbaaf1418ca8',
    'policy_sha256':'fb855bd2c9818148f22505b85020dcc8aaa887ea25426bd89f79c928af2bfe30',
    'snapshot_manifest_sha256':'3206cac517fca84dbba3bc6877521f21452c9457e75ff7392c2a7dd45a906044',
    'terminal_comment_id':7063678029,
}
REFACTOR_ORDER = ('visual-search/offer-list-v1.js','visual-search/app.js')
REFACTOR_TARGETS = {
    'visual-search/offer-list-v1.js':{
        'before':{'state':'present','mode':'0644','size':10662,'sha256':'c721c5ff832219f1e0ee64b738f263bc0fc6f94f3b506020782a46f4ccccbfe8'},
        'after':{'state':'present','mode':'0644','size':10523,'sha256':'d2fda746f777a43826db7c5ccec1015439b04f63279ef3a738f509cf155091e4'}},
    'visual-search/app.js':{
        'before':{'state':'present','mode':'0644','size':311547,'sha256':'c8d6922231350409490e833e429792d1d5a974e02b499e740264d148324d196f'},
        'after':{'state':'present','mode':'0644','size':310844,'sha256':'efb3870d23c3e0d3c3fbb53fff4967a6ee3d901bebdf5502b7d78c6e2d2bbe92'}},
}


def refactor_parent_identity():
    return {key:REFACTOR_PARENT[key] for key in ('control_source_sha','run_id','command_comment_id')}


def refactor_policy():
    return {'operation':REFACTOR_OPERATION,'source':REFACTOR_SOURCE,'tree':REFACTOR_TREE,
            'release':REFACTOR_RELEASE,'manifest':REFACTOR_MANIFEST_SHA,
            'order':REFACTOR_ORDER,'targets':REFACTOR_TARGETS,'parent':REFACTOR_PARENT}


def refactor_event_identity(event, env, rollback=False):
    need(type(rollback) is bool,'mode')
    prefix,operation=(REFACTOR_ROLLBACK_PREFIX,REFACTOR_ROLLBACK_OPERATION) if rollback else (REFACTOR_PREFIX,REFACTOR_OPERATION)
    control=env.get('GITHUB_SHA','');body=event.get('comment',{}).get('body','')
    original_run=None
    if rollback:
        match=re.fullmatch(re.escape(prefix+' '+control+' ')+r'([1-9][0-9]{0,19}) '+re.escape(operation),body)
        need(match is not None,'command');original_run=match[1]
    else:need(body==f'{prefix} {control} {operation}','command')
    synthetic=json.loads(json.dumps(event));synthetic['comment']['body']=f'{UPDATE_PREFIX} {control} {UPDATE_OPERATION}'
    identity=update_event_identity(synthetic,env)
    excluded=(REFACTOR_PARENT['run_id'],SITE_PARENT['run_id'],UPDATE_PARENT['run_id'],CONTINUATION_PARENT['run_id'],
              SITE_INSPECT_FAILED['run_id'],SITE_CONTINUATION_INSPECTION['run_id'],SITE_CONTINUATION_COLLECTOR['run_id'])
    need(identity['run_id'] not in excluded and original_run not in excluded,'refactor_runtime_identity')
    if rollback:need(original_run!=identity['run_id'],'command')
    return (identity,original_run) if rollback else identity


def refactor_final_heads(identity):
    rollback_main(identity)
    successor=REFACTOR_RELEASE_SUCCESSOR
    need(api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha']==successor['merge_sha'],'release_moved')
    pr=api('/pulls/'+str(REFACTOR_SOURCE_PR))
    need(pr.get('state')=='closed' and pr.get('merged') is True and pr.get('merge_commit_sha')==REFACTOR_SOURCE_MERGE
         and pr.get('head',{}).get('sha')==REFACTOR_SOURCE and pr.get('head',{}).get('repo',{}).get('full_name')==REPO
         and pr.get('base',{}).get('ref')=='release/search3-production-ready-v1'
         and pr.get('base',{}).get('repo',{}).get('full_name')==REPO,'candidate_moved')
    for commit,tree in ((REFACTOR_SOURCE,REFACTOR_TREE),(REFACTOR_SOURCE_MERGE,REFACTOR_TREE),(REFACTOR_RELEASE,REFACTOR_RELEASE_TREE)):
        value=api('/git/commits/'+commit)
        need(value.get('tree',{}).get('sha')==tree,'refactor_source_tree')
        if commit==REFACTOR_RELEASE:
            need([p.get('sha') for p in value.get('parents',[])]==[REFACTOR_SOURCE_MERGE,REFACTOR_COMPILER_HEAD],'refactor_release_relation')
    compiler=api('/pulls/5140')
    need(compiler.get('state')=='closed' and compiler.get('merged') is True
         and compiler.get('merge_commit_sha')==REFACTOR_RELEASE and compiler.get('head',{}).get('sha')==REFACTOR_COMPILER_HEAD
         and compiler.get('head',{}).get('repo',{}).get('full_name')==REPO
         and compiler.get('base',{}).get('sha')==REFACTOR_SOURCE_MERGE
         and compiler.get('base',{}).get('ref')=='release/search3-production-ready-v1'
         and compiler.get('base',{}).get('repo',{}).get('full_name')==REPO,'refactor_release_relation')
    local=api('/pulls/'+str(successor['pr']))
    need(local.get('state')=='closed' and local.get('merged') is True
         and local.get('merge_commit_sha')==successor['merge_sha']
         and local.get('head',{}).get('sha')==successor['head_sha']
         and local.get('head',{}).get('ref')==successor['head_ref']
         and local.get('head',{}).get('repo',{}).get('full_name')==REPO
         and local.get('base',{}).get('sha')==REFACTOR_RELEASE
         and local.get('base',{}).get('ref')=='release/search3-production-ready-v1'
         and local.get('base',{}).get('repo',{}).get('full_name')==REPO,'refactor_release_successor')
    for commit,parents in ((successor['head_sha'],[REFACTOR_RELEASE]),
                           (successor['merge_sha'],[REFACTOR_RELEASE,successor['head_sha']])):
        value=api('/git/commits/'+commit)
        need(value.get('tree',{}).get('sha')==successor['tree_sha']
             and [p.get('sha') for p in value.get('parents',[])]==parents,'refactor_release_successor')
    delta=api('/compare/'+REFACTOR_RELEASE+'...'+successor['merge_sha']);files=delta.get('files',[])
    need(delta.get('status')=='ahead' and delta.get('ahead_by')==2 and delta.get('behind_by')==0
         and delta.get('total_commits')==2 and delta.get('base_commit',{}).get('sha')==REFACTOR_RELEASE
         and delta.get('merge_base_commit',{}).get('sha')==REFACTOR_RELEASE
         and isinstance(files,list) and len(files)==len(successor['files'])
         and {row.get('filename'):row.get('status') for row in files}==successor['files']
         and all(row.get('previous_filename') is None for row in files),'refactor_release_scope')


def refactor_parent_admission():
    pin=REFACTOR_PARENT;run=api('/actions/runs/'+pin['run_id'])
    need(run.get('id')==int(pin['run_id']) and run.get('head_sha')==pin['control_source_sha']
         and run.get('head_branch')=='main' and run.get('path')==WORKFLOW and run.get('event')=='issue_comment'
         and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')=='success'
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff','refactor_parent_run')
    jobs=[j for j in pages('/actions/runs/'+pin['run_id']+'/attempts/1/jobs','jobs') if j.get('name')=='public-install']
    need(len(jobs)==1 and jobs[0].get('id')==pin['job_id'] and jobs[0].get('run_id')==int(pin['run_id'])
         and jobs[0].get('status')=='completed' and jobs[0].get('conclusion')=='success','refactor_parent_job')
    steps=[s for s in jobs[0].get('steps',[]) if s.get('name','').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps)==1 and steps[0].get('name')=='Run python3 scripts/deploy/search3_public_install.py site-continue'
         and steps[0].get('status')=='completed' and steps[0].get('conclusion')=='success','refactor_parent_step')
    artifact=api('/actions/artifacts/'+str(pin['artifact_id']))
    need(artifact.get('id')==pin['artifact_id'] and artifact.get('name')=='search3-public-install-'+pin['run_id']+'-1'
         and artifact.get('expired') is False and artifact.get('workflow_run',{}).get('id')==int(pin['run_id'])
         and artifact.get('digest')=='sha256:'+pin['artifact_sha256'],'refactor_parent_artifact')
    comments=pages('/issues/4709/comments')
    _unique_update_command(comments,SITE_CONTINUATION_PREFIX,SITE_CONTINUATION_OPERATION,refactor_parent_identity())
    terminal=next((c for c in comments if c.get('id')==pin['terminal_comment_id']),{})
    tokens=[str(pin[k]) for k in ('run_id','artifact_id','artifact_sha256','receipt_sha256','snapshot_manifest_sha256')]
    need(terminal.get('user',{}).get('id')==226193297 and terminal.get('author_association')=='OWNER'
         and all(t in terminal.get('body','') for t in tokens+['site_updated_verified']),'refactor_parent_terminal')


def refactor_admission(identity):
    refactor_final_heads(identity);reviewed_control(identity['control_source_sha'])
    gate=api('/contents/.github/workflows/deploy-anytoour.yml?ref='+identity['control_source_sha'])
    need(gate.get('type')=='file' and gate.get('encoding')=='base64'
         and sha(base64.b64decode(gate['content']))==SITE_PUBLISHER_GUARD_SHA,'publisher_guard')
    checks=pages('/commits/'+REFACTOR_SOURCE+'/check-runs','check_runs')
    need(len(checks)==len(SOURCE_CHECK_NAMES) and {c.get('name') for c in checks}==set(SOURCE_CHECK_NAMES)
         and all(c.get('head_sha')==REFACTOR_SOURCE and green(c) for c in checks),'source_ci')
    run=api('/actions/runs/'+str(REFACTOR_BUILD_RUN))
    need(run.get('id')==REFACTOR_BUILD_RUN and run.get('head_sha')==REFACTOR_SOURCE
         and run.get('head_branch')=='refactor/search3-passive-card-selection-20261121'
         and run.get('path')=='.github/workflows/search3-visual-migration.yml'
         and run.get('event')=='pull_request' and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')=='success'
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff'
         and run.get('repository',{}).get('full_name')==run.get('head_repository',{}).get('full_name')==REPO,'refactor_build')
    artifact=api('/actions/artifacts/'+str(REFACTOR_ARTIFACT))
    need(artifact.get('id')==REFACTOR_ARTIFACT and artifact.get('name')=='search3-public-live-'+REFACTOR_SOURCE
         and artifact.get('expired') is False and artifact.get('digest')=='sha256:'+REFACTOR_ARTIFACT_SHA
         and artifact.get('workflow_run',{}).get('id')==REFACTOR_BUILD_RUN
         and artifact.get('workflow_run',{}).get('head_sha')==REFACTOR_SOURCE,'refactor_artifact')
    refactor_parent_admission()
    _unique_update_command(pages('/issues/4709/comments'),REFACTOR_PREFIX,REFACTOR_OPERATION,identity)
    refactor_final_heads(identity)


def refactor_parent_value(value):
    # The fixed digest preserves the actual parent identity and complete nested
    # proof. Reuse the existing pure successful-child validator without network.
    raw=(json.dumps(value,sort_keys=True)+'\n').encode()
    need(sha(raw)==REFACTOR_PARENT['receipt_sha256'],'refactor_parent_digest')
    packet=io.BytesIO()
    with zipfile.ZipFile(packet,'w') as archive:archive.writestr('evidence.json',raw)
    policy,accepted,previous=site_source_evidence(packet.getvalue(),refactor_parent_identity(),True)
    need(policy['manifest']==REFACTOR_PARENT['policy_sha256']
         and accepted['snapshot_manifest_sha256']==REFACTOR_PARENT['snapshot_manifest_sha256'],'refactor_parent_receipt')
    return policy,accepted,previous


def refactor_parent_evidence(raw):
    need(sha(raw)==REFACTOR_PARENT['artifact_sha256'],'refactor_parent_artifact')
    with zip_files(raw) as archive:
        need(archive.namelist()==['evidence.json'],'refactor_parent_artifact')
        data=archive.read('evidence.json');need(sha(data)==REFACTOR_PARENT['receipt_sha256'],'refactor_parent_digest')
    value=json.loads(data);refactor_parent_value(value)
    return value


def refactor_package(raw, parent_value):
    need(sha(raw)==REFACTOR_ARTIFACT_SHA,'refactor_artifact')
    _,_,update_value=refactor_parent_value(parent_value)
    previous,_,_=site_parent_value(update_value)
    expected=dict(previous['previous']['expected'],**{UPDATE_PATH:UPDATE_AFTER})
    need(set(REFACTOR_ORDER)<=set(expected) and len(expected)==42
         and all(expected[p]==REFACTOR_TARGETS[p]['before'] for p in REFACTOR_ORDER),'refactor_predecessor')
    with zip_files(raw) as archive:
        manifest_raw=archive.read('manifest.json');need(sha(manifest_raw)==REFACTOR_MANIFEST_SHA,'manifest_digest')
        manifest=json.loads(manifest_raw)
        contract={k:manifest[k] for k in REFACTOR_CONTRACT_KEYS};contract['protected']=manifest['files']['protected']
        need(sha(json.dumps(contract,sort_keys=True,separators=(',',':')).encode())==REFACTOR_CONTRACT_SHA
             and len(manifest['files']['protected'])==733,'refactor_manifest_contract')
        need(manifest.get('candidate')=={'sha':REFACTOR_SOURCE,'tree_sha':REFACTOR_TREE},'manifest_identity')
        added,replaced=manifest['files']['added'],manifest['files']['replaced']
        need(tuple(x['path'] for x in added)==ADDITIONS and len(replaced)==1 and replaced[0]['path']==ENTRY
             and replaced[0]['before']=={k:v for k,v in ENTRY_BEFORE.items() if k!='state'},'manifest_scope')
        rows=added+replaced
        need({x for x in archive.namelist() if x.startswith('overlay/') and not x.endswith('/')}
             =={'overlay/'+p for p in expected},'overlay_scope')
        files={}
        for item in rows:
            path=item['path'];data=archive.read('overlay/'+path);record={'state':'present',**item['served']}
            need(item['served']=={'size':len(data),'mode':'0644','sha256':sha(data)} and 0<len(data)<=MAX_BYTES,'overlay_bytes')
            if path in REFACTOR_ORDER:
                need(record==REFACTOR_TARGETS[path]['after'],'refactor_payload_bytes');files[path]=base64.b64encode(data).decode()
            else:need(record==expected[path],'refactor_unexpected_delta')
    need(set(files)==set(REFACTOR_ORDER),'refactor_payload')
    return refactor_policy(),files


REFACTOR_COMMON_REMOTE = r'''
REFACTOR_PREPARED_PATHS=('reservation.json','snapshot/manifest.json')+tuple(p+str(i) for i in range(2) for p in ('snapshot/','drill/','intent-'))
def refactor_stamp_rows(rows):
 return {p:[row,list(identity) if identity is not None else None] for p,(row,identity,*_) in rows.items()}
def refactor_ancestor_inventory():
 return {'public':refactor_stamp_rows(parent_records()),
         'update':refactor_stamp_rows(read_records(updated,UPDATE_RECORD_PATHS)),
         'update_stage':refactor_stamp_rows({'app':updated.read('stage/app')[:2]}),
         'site':refactor_stamp_rows(site_read_records(published,SITE_PREPARED_PATHS+('result.json',))),
         'site_stage':refactor_stamp_rows({p:published.read('stage/'+str(i))[:2] for i,p in enumerate(SITE_POLICY['order'])})}
def refactor_site_parent():
 retained,updates,update_prior,previous=site_parent_records()
 rows=site_read_records(published,SITE_PREPARED_PATHS+('result.json',));record=lambda p:json.loads(rows[p][2])
 require(record('reservation.json')=={'operation':SITE_POLICY['operation'],**SITE_ORIGINAL,
         'source':SITE_POLICY['source'],'manifest':SITE_POLICY['manifest'],'parent_update':UPDATE_ORIGINAL}
         and record('result.json')==SITE_ACCEPTED,'refactor_site_parent')
 snapshot=record('snapshot/manifest.json')
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_update','before','guarded','parent_records','update_records'}
         and snapshot['source']==SITE_POLICY['source'] and snapshot['manifest']==SITE_POLICY['manifest']
         and snapshot['parent_update']==UPDATE_ORIGINAL and set(snapshot['before'])==set(SITE_POLICY['order'])
         and refactor_expected(snapshot['guarded'])=={p:r[0] for p,r in previous.items()}
         and snapshot['parent_records']==parent_hashes(retained) and snapshot['update_records']==parent_hashes(updates)
         and digest(rows['snapshot/manifest.json'][2])==SITE_ACCEPTED['snapshot_manifest_sha256'],'refactor_site_snapshot')
 current=public.inventory(SITE_POLICY['order']);site_prior={}
 for index,path in enumerate(SITE_POLICY['order']):
  target=SITE_POLICY['targets'][path];before=snapshot['before'][path]
  require(isinstance(before,list) and len(before)==2 and before[0]==target['before'],'refactor_site_snapshot')
  old=checked_stamp(before[1],target['before']);intent=record('intent-'+str(index))
  require(intent=={'path':path,'before':target['before'],'after':target['after'],
          'previous_identity':before[1],'new_identity':intent.get('new_identity')},'refactor_site_parent')
  new=checked_stamp(intent['new_identity'],target['after']);prior=published.read('stage/'+str(index))[:2]
  require(rows['snapshot/'+str(index)][0]==rows['drill/'+str(index)][0]==target['before']
          and prior[0]==target['before'] and prior[1][:-1]==old[:-1]
          and current[path][0]==target['after'] and current[path][1][:-1]==new[:-1],'refactor_site_snapshot')
  site_prior[path]=prior
 ancestors={'public':refactor_stamp_rows(retained),'update':refactor_stamp_rows(updates),
            'update_stage':refactor_stamp_rows({'app':update_prior}),'site':refactor_stamp_rows(rows),
            'site_stage':refactor_stamp_rows(site_prior)}
 # Carry the exact validated preimages and ancestor records into the transaction.
 # A later read may only confirm this view, never replace its inode authority.
 return ({p:r for p,r in previous.items() if p not in REFACTOR_POLICY['order']}|current,
         {p:previous[p] for p in REFACTOR_POLICY['order']},ancestors)
def refactor_check_ancestors():
 site_continuation_namespace_absent()
 require(refactor_ancestor_inventory()==ancestors,'refactor_parent_changed')
 require(public.inventory(guarded)==guarded,'protected_changed')
def refactor_check_targets(expected):
 require(public.inventory(REFACTOR_POLICY['order'])==expected,'refactor_target_changed')
def refactor_guard_from_snapshot(saved):
 expected=refactor_expected({p:row[0] for p,row in saved.items()});observed=public.inventory(saved)
 for path,current in observed.items():
  require(current[0]==expected[path],'protected_changed')
  if globals().get('TV_SUCCESSOR_PROOF') is not None and path==TV_SUCCESSOR['path']:
   require(current==_tv_successor_bound['target'],'protected_changed')
  else:
   require(current==(saved[path][0],tuple(saved[path][1]) if saved[path][1] is not None else None),'protected_changed')
 return observed
def refactor_restore_one(store,path,index,current,prior,capture):
 refactor_check_ancestors()
 require(public.read(path)[:2]==current and store.read('stage/'+str(index))[:2]==prior,'rollback_preflight_race')
 capture_owned(public,private,path,capture,current[0],current[1])
 restore_capture(public,store,path,'stage/'+str(index))
 restored=public.read(path)[:2]
 require(restored[0]==prior[0] and restored[1][:-1]==prior[1][:-1],'rollback_incomplete')
 return restored
'''

REFACTOR_REMOTE = r'''
result={'schema_version':1,'operation_id':REFACTOR_POLICY['operation'],'source_sha':REFACTOR_POLICY['source'],
        'manifest_sha256':REFACTOR_POLICY['manifest'],**IDENTITY,'parent_site':SITE_ORIGINAL,
        'parent_receipt_sha256':REFACTOR_POLICY['parent']['receipt_sha256'],
        'status':'blocked_no_replay','phase':'preflight','supplier_calls':0,'database_reads':0,'database_writes':0,
        'lead_calls':0,'booking_calls':0,'application_calls':0,'written_files':[],'rollback_files':[],
        'reservation_created':False,'snapshot_verified':False,'restore_drill_verified':False,
        'protected_unchanged':False,'unchanged_files_verified':False,'parent_records_unchanged':False,
        'original_inodes_verified':False,'failed_namespace_absent':False,'snapshot_manifest_sha256':None}
public=base=original=updated=published=private=None;reserved=False;switched=[];owned={};prior={};prepared=None
try:
 require(hasattr(os,'O_PATH') and hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY')
         and hasattr(ctypes.CDLL(None),'renameat2'),'unsupported_platform')
 order=REFACTOR_POLICY['order'];targets=REFACTOR_POLICY['targets'];home=os.environ.get('HOME','')
 require(len(order)==2 and set(PAYLOAD)==set(targets)==set(order)
         and set(order)<=set(POLICY['expected']) and not set(order)&set(SITE_POLICY['order']),'refactor_scope')
 payload={p:base64.b64decode(PAYLOAD[p],validate=True) for p in order}
 require(all(targets[p]['after']=={'state':'present','sha256':digest(raw),'size':len(raw),'mode':'0644'}
             and 0<len(raw)<=MAX_BYTES for p,raw in payload.items()),'payload_bytes')
 public=Files(home+'/www/anytoour.ru');base=Files(home+'/.anytoour-search3-root-gateway');private_root(base)
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation']);private_root(original)
 updated=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_POLICY['operation']);private_root(updated)
 published=Files(home+'/.anytoour-search3-root-gateway/'+SITE_POLICY['operation']);private_root(published)
 require(os.fstat(base.fd).st_dev==os.fstat(public.fd).st_dev,'different_filesystem')
 guarded,before,ancestors=refactor_site_parent()
 require(all(row[0]==targets[p]['before'] and row[1][6]==os.getuid() for p,row in before.items()),'predecessor_drift')
 refactor_check_ancestors();refactor_check_targets(before)
 try:os.mkdir(REFACTOR_POLICY['operation'],0o700,dir_fd=base.fd)
 except FileExistsError:raise RuntimeError('operation_exists_no_replay') from None
 reserved=True;result['reservation_created']=True;sync(base.fd)
 private=Files(home+'/.anytoour-search3-root-gateway/'+REFACTOR_POLICY['operation'],('snapshot','drill','stage','rollback'),0o700);private_root(private)
 reservation={'operation':REFACTOR_POLICY['operation'],**IDENTITY,'source':REFACTOR_POLICY['source'],
              'manifest':REFACTOR_POLICY['manifest'],'parent_site':SITE_ORIGINAL}
 private.create('reservation.json',canonical(reservation));result['phase']='snapshot'
 snapshot={'source':REFACTOR_POLICY['source'],'manifest':REFACTOR_POLICY['manifest'],'parent_site':SITE_ORIGINAL,
           'before':refactor_stamp_rows(before),'guarded':refactor_stamp_rows(guarded),'ancestors':ancestors}
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
 prepared=site_read_records(private,REFACTOR_PREPARED_PATHS)
 require(prepared['reservation.json'][2]==canonical(reservation) and prepared['snapshot/manifest.json'][2]==canonical(snapshot)
         and all(prepared['snapshot/'+str(i)][0]==prepared['drill/'+str(i)][0]==targets[p]['before']
                 and json.loads(prepared['intent-'+str(i)][2])=={'path':p,'before':targets[p]['before'],'after':targets[p]['after'],
                      'previous_identity':list(before[p][1]),'new_identity':list(owned[p][1])} for i,p in enumerate(order)),'snapshot_invalid')
 current=dict(before);result['phase']='assets'
 for index,path in enumerate(order):
  refactor_check_ancestors();refactor_check_targets(current)
  require(site_read_records(private,REFACTOR_PREPARED_PATHS)==prepared
          and private.read('stage/'+str(index))[:2]==owned[path],'prewrite_race')
  with private.parent('stage/'+str(index)) as (src,name),public.parent(path) as (dst,target):
   require(public.read(path)[:2]==before[path],'entry_prewrite_race')
   exchange(src,name,dst,target);switched.append(path);result['written_files'].append(path);sync(src);sync(dst)
  observed=public.read(path)[:2]
  require(observed[0]==targets[path]['after'] and observed[1][:-1]==owned[path][1][:-1],'refactor_readback')
  owned[path]=observed;current[path]=observed
  try:
   prior[path]=private.read('stage/'+str(index))[:2]
   require(prior[path][0]==targets[path]['before'] and prior[path][1][:-1]==before[path][1][:-1],'refactor_exchange_conflict')
  except Exception:
   refactor_check_ancestors()
   capture_owned(public,private,path,'rollback/conflict-'+str(index),observed[0],observed[1])
   restore_capture(public,private,path,'stage/'+str(index));switched.remove(path)
   raise RuntimeError('refactor_exchange_conflict')
  refactor_check_targets(current);refactor_check_ancestors()
 require(site_read_records(private,REFACTOR_PREPARED_PATHS)==prepared
         and all(private.read('stage/'+str(i))[:2]==prior[p] for i,p in enumerate(order)),'refactor_snapshot_conflict')
 result.update(status='refactor_updated_verified',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
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
    refactor_restore_one(private,path,index,current_row,saved,'rollback/'+str(index));result['rollback_files'].append(path)
   except Exception:recovery_ok=False
  try:
   require(recovery_ok,'rollback_incomplete');refactor_check_ancestors()
   restored=public.inventory(order)
   require(all(row[0]==before[p][0] and row[1][:-1]==before[p][1][:-1] for p,row in restored.items())
           and result['rollback_files']==list(reversed(result['written_files'])),'rollback_incomplete')
   # Restored JS once again satisfies the unchanged historical full checker.
   refactor_site_parent()
   result.update(status='rolled_back_no_replay',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
                 parent_records_unchanged=True,original_inodes_verified=True)
  except Exception:result['status']='unknown_no_replay'
finally:
 if reserved and private is not None:
  try:private.create('result.json',canonical(result))
  except Exception:result.update(status='unknown_no_replay',reason='receipt_failed')
 for store in (private,published,updated,original,base,public):
  if store is not None:store.close()
print(json.dumps(result,sort_keys=True))
'''


REFACTOR_ROLLBACK_REMOTE = r'''
result={'schema_version':1,'operation_id':REFACTOR_ROLLBACK_OPERATION,'source_sha':REFACTOR_POLICY['source'],
        'manifest_sha256':REFACTOR_POLICY['manifest'],**IDENTITY,'parent_site':SITE_ORIGINAL,
        'parent_receipt_sha256':REFACTOR_POLICY['parent']['receipt_sha256'],'original_refactor':REFACTOR_ORIGINAL,
        'status':'blocked_no_replay','phase':'preflight','supplier_calls':0,'database_reads':0,'database_writes':0,
        'lead_calls':0,'booking_calls':0,'application_calls':0,'written_files':[],'rollback_files':[],
        'reservation_created':False,'snapshot_verified':False,'restore_drill_verified':False,
        'protected_unchanged':False,'unchanged_files_verified':False,'parent_records_unchanged':False,
        'original_inodes_verified':False,'failed_namespace_absent':False,
        'snapshot_manifest_sha256':REFACTOR_ACCEPTED['snapshot_manifest_sha256']}
public=base=original=updated=published=refactored=private=None;reserved=False
try:
 require(hasattr(os,'O_PATH') and hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY')
         and hasattr(ctypes.CDLL(None),'renameat2'),'unsupported_platform')
 order=REFACTOR_POLICY['order'];targets=REFACTOR_POLICY['targets'];home=os.environ.get('HOME','')
 public=Files(home+'/www/anytoour.ru');base=Files(home+'/.anytoour-search3-root-gateway');private_root(base)
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation']);private_root(original)
 updated=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_POLICY['operation']);private_root(updated)
 published=Files(home+'/.anytoour-search3-root-gateway/'+SITE_POLICY['operation']);private_root(published)
 refactored=Files(home+'/.anytoour-search3-root-gateway/'+REFACTOR_POLICY['operation']);private_root(refactored)
 require(os.fstat(base.fd).st_dev==os.fstat(public.fd).st_dev,'different_filesystem')
 site_continuation_namespace_absent()
 paths=REFACTOR_PREPARED_PATHS+('result.json',);records=site_read_records(refactored,paths)
 record=lambda p:json.loads(records[p][2])
 require(record('reservation.json')=={'operation':REFACTOR_POLICY['operation'],**REFACTOR_ORIGINAL,
         'source':REFACTOR_POLICY['source'],'manifest':REFACTOR_POLICY['manifest'],'parent_site':SITE_ORIGINAL}
         and record('result.json')==REFACTOR_ACCEPTED,'refactor_original_identity')
 snapshot=record('snapshot/manifest.json')
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_site','before','guarded','ancestors'}
         and snapshot['source']==REFACTOR_POLICY['source'] and snapshot['manifest']==REFACTOR_POLICY['manifest']
         and snapshot['parent_site']==SITE_ORIGINAL and set(snapshot['before'])==set(order)
         and set(snapshot['guarded'])==(set(POLICY['expected'])-set(order))|set(checked_protected_paths())
             |set(SITE_POLICY['dependencies'])|set(SITE_POLICY['optional_dependencies'])|set(SITE_POLICY['order'])
         and digest(records['snapshot/manifest.json'][2])==REFACTOR_ACCEPTED['snapshot_manifest_sha256'],'refactor_snapshot_conflict')
 ancestors=snapshot['ancestors'];guarded=refactor_guard_from_snapshot(snapshot['guarded'])
 require(refactor_ancestor_inventory()==ancestors,'refactor_parent_changed')
 current=public.inventory(order);prior={};old_identities={}
 for index,path in enumerate(order):
  before=snapshot['before'][path]
  require(isinstance(before,list) and len(before)==2 and before[0]==targets[path]['before'],'refactor_snapshot_conflict')
  old_identities[path]=checked_stamp(before[1],targets[path]['before'])
  require(records['snapshot/'+str(index)][0]==records['drill/'+str(index)][0]==targets[path]['before'],'refactor_snapshot_conflict')
  intent=record('intent-'+str(index))
  require(isinstance(intent,dict) and set(intent)=={'path','before','after','previous_identity','new_identity'}
          and intent['path']==path and intent['before']==targets[path]['before'] and intent['after']==targets[path]['after']
          and intent['previous_identity']==before[1],'refactor_original_identity')
  new=checked_stamp(intent['new_identity'],targets[path]['after']);prior[path]=refactored.read('stage/'+str(index))[:2]
  require(current[path][0]==targets[path]['after'] and current[path][1][:-1]==new[:-1]
          and prior[path][0]==targets[path]['before'] and prior[path][1][:-1]==old_identities[path][:-1],'refactor_snapshot_conflict')
 refactor_check_ancestors();refactor_check_targets(current)
 require(site_read_records(refactored,paths)==records,'rollback_preflight_race')
 result.update(snapshot_verified=True,restore_drill_verified=True)
 try:os.mkdir(REFACTOR_ROLLBACK_OPERATION,0o700,dir_fd=base.fd)
 except FileExistsError:raise RuntimeError('operation_exists_no_replay') from None
 reserved=True;result['reservation_created']=True;sync(base.fd)
 private=Files(home+'/.anytoour-search3-root-gateway/'+REFACTOR_ROLLBACK_OPERATION,('quarantine',),0o700);private_root(private)
 private.create('reservation.json',canonical({'operation':REFACTOR_ROLLBACK_OPERATION,**IDENTITY,'original_refactor':REFACTOR_ORIGINAL,
                'source':REFACTOR_POLICY['source'],'manifest':REFACTOR_POLICY['manifest'],'parent_site':SITE_ORIGINAL}))
 result['phase']='assets'
 for index in reversed(range(2)):
  path=order[index];refactor_check_ancestors();refactor_check_targets(current)
  require(site_read_records(refactored,paths)==records,'refactor_snapshot_conflict')
  current[path]=refactor_restore_one(refactored,path,index,current[path],prior[path],'quarantine/'+str(index))
  result['rollback_files'].append(path)
  refactor_check_targets(current);refactor_check_ancestors()
 require(all(current[p][0]==targets[p]['before'] and current[p][1][:-1]==old_identities[p][:-1] for p in order)
         and site_read_records(refactored,paths)==records,'rollback_incomplete')
 refactor_site_parent()
 result.update(status='refactor_rolled_back_verified',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
               parent_records_unchanged=True,original_inodes_verified=True)
except Exception as error:
 result['status']='unknown_no_replay' if reserved else 'blocked_no_replay'
 result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
finally:
 if reserved and private is not None:
  try:private.create('result.json',canonical(result))
  except Exception:result.update(status='unknown_no_replay',reason='receipt_failed')
 for store in (private,refactored,published,updated,original,base,public):
  if store is not None:store.close()
print(json.dumps(result,sort_keys=True))
'''


REFACTOR_REASONS = SITE_REASONS + ('site_failed_operation_exists','refactor_scope','refactor_site_parent',
    'refactor_site_snapshot','refactor_parent_changed','refactor_target_changed','refactor_readback',
    'refactor_exchange_conflict','refactor_snapshot_conflict','refactor_original_identity')


def _refactor_program_prefix(policy, identity, parent_value, tv_successor=None):
    need(policy==refactor_policy(),'refactor_policy');update_runtime_identity(identity)
    need(identity['run_id']!=REFACTOR_PARENT['run_id'],'refactor_runtime_identity')
    site_policy_value,accepted,previous=refactor_parent_value(parent_value)
    # None remains a pure old-state fixture only. Every live entry resolves and
    # supplies explicit current INT evidence, as the approved publisher does.
    prefix=(_site_program_prefix(site_policy_value,identity,previous,True) if tv_successor is None else
            _site_program_prefix(site_policy_value,identity,previous,True,tv_successor=tv_successor))
    expected='snapshot' if tv_successor is None else 'tv_successor_expected(snapshot)'
    return (prefix+'\nREFACTOR_POLICY='+repr(policy)+'\nSITE_ORIGINAL='+repr(refactor_parent_identity())
            +'\nSITE_ACCEPTED='+repr(accepted)+'\ndef refactor_expected(snapshot): return '+expected+'\n'
            +'\ndef re_safe(value): return value in '+repr(REFACTOR_REASONS)+'\n'+REFACTOR_COMMON_REMOTE)


def refactor_remote_program(policy, files, identity, parent_value, *, tv_successor=None):
    prefix=_refactor_program_prefix(policy,identity,parent_value,tv_successor)
    need(isinstance(files,dict) and set(files)==set(REFACTOR_ORDER),'refactor_payload')
    for path in REFACTOR_ORDER:
        raw=base64.b64decode(files[path],validate=True)
        need(0<len(raw)<=MAX_BYTES and REFACTOR_TARGETS[path]['after']==
             {'state':'present','mode':'0644','size':len(raw),'sha256':sha(raw)},'refactor_payload_bytes')
    program=prefix+'\nPAYLOAD='+repr(files)+'\n'+REFACTOR_REMOTE;ast.parse(program)
    return program


def refactor_rollback_remote_program(policy, identity, original, accepted, parent_value, *, tv_successor=None, handoff_restoration=None, home_restoration=None):
    prefix=_refactor_program_prefix(policy,identity,parent_value,tv_successor);update_runtime_identity(original)
    need(identity['run_id']!=original['run_id'] and identity['command_comment_id']!=original['command_comment_id']
         and valid_refactor_result(original,accepted),'refactor_rollback_evidence')
    if handoff_restoration is not None and handoff_restoration.get('status')=='restored':
        need(original==handoff_parent_identity(),'handoff_restoration_parent')
    if home_restoration is not None:
        need(handoff_restoration is not None,'home_restoration_parent')
        if home_restoration.get('status')=='restored':need(original==handoff_parent_identity(),'home_restoration_parent')
    program=(prefix+'\nREFACTOR_ORIGINAL='+repr(original)+'\nREFACTOR_ACCEPTED='+repr(accepted)
             +'\nREFACTOR_ROLLBACK_OPERATION='+repr(REFACTOR_ROLLBACK_OPERATION)+'\n'
             +handoff_restoration_prefix(handoff_restoration)+home_restoration_prefix(home_restoration,'refactor')+REFACTOR_ROLLBACK_REMOTE)
    ast.parse(program);return program


def valid_refactor_result(identity, result, original=None, accepted=None):
    rollback=original is not None
    fixed={'schema_version':1,'operation_id':REFACTOR_ROLLBACK_OPERATION if rollback else REFACTOR_OPERATION,
           'source_sha':REFACTOR_SOURCE,'manifest_sha256':REFACTOR_MANIFEST_SHA,**identity,
           'parent_site':refactor_parent_identity(),'parent_receipt_sha256':REFACTOR_PARENT['receipt_sha256']}
    if rollback:fixed.update(original_refactor=original,snapshot_manifest_sha256=accepted['snapshot_manifest_sha256'])
    counters=('supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
    flags=('reservation_created','snapshot_verified','restore_drill_verified','protected_unchanged',
           'unchanged_files_verified','parent_records_unchanged','original_inodes_verified','failed_namespace_absent')
    required=set(fixed)|set(counters)|set(flags)|{'status','phase','written_files','rollback_files','snapshot_manifest_sha256'}
    need(isinstance(result,dict) and required<=set(result)<=required|{'reason'},'refactor_receipt_shape')
    need(all(type(result.get(k)) is type(v) and result[k]==v for k,v in fixed.items()),'refactor_receipt_identity')
    need(all(type(result[k]) is int and result[k]==0 for k in counters)
         and all(type(result[k]) is bool for k in flags),'refactor_receipt_counters')
    success='refactor_rolled_back_verified' if rollback else 'refactor_updated_verified'
    states=('blocked_no_replay','unknown_no_replay',success) if rollback else ('blocked_no_replay','unknown_no_replay','rolled_back_no_replay',success)
    need(result['status'] in states and result['phase'] in ('preflight','snapshot','assets','complete')
         and ('reason' not in result or result['reason'] in REFACTOR_REASONS),'refactor_receipt_status')
    written,restored=result['written_files'],result['rollback_files']
    need(isinstance(written,list) and written in [list(REFACTOR_ORDER[:i]) for i in range(3)]
         and isinstance(restored,list) and all(isinstance(p,str) for p in restored) and len(set(restored))==len(restored),'refactor_receipt_paths')
    order=list(reversed(REFACTOR_ORDER if rollback else written))
    need(all(p in order for p in restored) and restored==[p for p in order if p in restored]
         and (not rollback or not written),'refactor_receipt_paths')
    snapshot=result['snapshot_manifest_sha256']
    need(snapshot is None or isinstance(snapshot,str) and re.fullmatch('[a-f0-9]{64}',snapshot),'refactor_receipt_snapshot')
    if result['status']=='blocked_no_replay':
        need(not result['reservation_created'] and result['phase']=='preflight' and not written and not restored,'refactor_receipt_incomplete')
    if result['status'] in (success,'rolled_back_no_replay'):
        need(result['phase']=='complete' and all(result[k] for k in ('reservation_created','protected_unchanged',
             'unchanged_files_verified','parent_records_unchanged','original_inodes_verified','failed_namespace_absent')),'refactor_receipt_incomplete')
    if result['status']=='rolled_back_no_replay':need(restored==list(reversed(written)),'refactor_receipt_incomplete')
    if result['status']==success:
        need(all(result[k] for k in flags) and snapshot is not None and 'reason' not in result
             and (restored==list(reversed(REFACTOR_ORDER)) if rollback else written==list(REFACTOR_ORDER) and not restored),'refactor_receipt_incomplete')
    return result['status']==success


def refactor_rollback_commands(identity, original_run, expected_original=None):
    excluded=(identity['run_id'],REFACTOR_PARENT['run_id'],SITE_PARENT['run_id'],UPDATE_PARENT['run_id'],
              CONTINUATION_PARENT['run_id'],SITE_INSPECT_FAILED['run_id'],
              SITE_CONTINUATION_INSPECTION['run_id'],SITE_CONTINUATION_COLLECTOR['run_id'])
    need(isinstance(original_run,str) and re.fullmatch('[1-9][0-9]{0,19}',original_run)
         and original_run not in excluded,'refactor_rollback_run')
    comments=pages('/issues/4709/comments')
    _unique_update_command(comments,REFACTOR_ROLLBACK_PREFIX,REFACTOR_ROLLBACK_OPERATION,identity,original_run)
    installed=[c for c in comments if c.get('body','').strip().split()[:1]==[REFACTOR_PREFIX]
               and REFACTOR_OPERATION in c.get('body','').split()[1:]]
    need(len(installed)==1 and installed[0].get('user',{}).get('id')==226193297
         and installed[0].get('author_association')=='OWNER','refactor_rollback_command')
    match=re.fullmatch(re.escape(REFACTOR_PREFIX)+r' ([a-f0-9]{40}) '+re.escape(REFACTOR_OPERATION),installed[0].get('body',''))
    need(match is not None,'refactor_rollback_command')
    original={'control_source_sha':match[1],'run_id':original_run,'command_comment_id':installed[0]['id']}
    update_runtime_identity(original)
    need(expected_original is None or original==expected_original,'refactor_rollback_command')
    return original


def refactor_rollback_admission(identity, original_run):
    rollback_main(identity);reviewed_control(identity['control_source_sha'])
    original=refactor_rollback_commands(identity,original_run);run=api('/actions/runs/'+original_run)
    need(run.get('id')==int(original_run) and run.get('head_sha')==original['control_source_sha']
         and run.get('head_branch')=='main' and run.get('path')==WORKFLOW and run.get('event')=='issue_comment'
         and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')=='success'
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff','refactor_rollback_run')
    jobs=[j for j in pages('/actions/runs/'+original_run+'/attempts/1/jobs','jobs') if j.get('name')=='public-install']
    need(len(jobs)==1 and jobs[0].get('run_id')==int(original_run) and jobs[0].get('status')=='completed'
         and jobs[0].get('conclusion')=='success','refactor_rollback_job')
    steps=[s for s in jobs[0].get('steps',[]) if s.get('name','').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps)==1 and steps[0].get('name')=='Run python3 scripts/deploy/search3_public_install.py refactor'
         and steps[0].get('status')=='completed' and steps[0].get('conclusion')=='success','refactor_rollback_step')
    artifacts=[a for a in pages('/actions/runs/'+original_run+'/artifacts','artifacts')
               if a.get('name')=='search3-public-install-'+original_run+'-1']
    need(len(artifacts)==1 and artifacts[0].get('expired') is False
         and artifacts[0].get('workflow_run',{}).get('id')==int(original_run)
         and re.fullmatch(r'sha256:[a-f0-9]{64}',artifacts[0].get('digest','')),'refactor_rollback_artifact')
    rollback_main(identity);return original,artifacts[0]


def refactor_source_evidence(raw, original):
    update_runtime_identity(original)
    with zip_files(raw) as archive:
        need(archive.namelist()==['evidence.json'],'refactor_rollback_artifact')
        value=json.loads(archive.read('evidence.json'))
    fixed={**original,'operation_id':REFACTOR_OPERATION,'source_sha':REFACTOR_SOURCE,'manifest_sha256':REFACTOR_MANIFEST_SHA,
           'read_only':False,'phase':'complete','ssh_started':True,'status':'refactor_updated_verified',
           'parent_site':refactor_parent_identity()}
    need(isinstance(value,dict) and set(value)==set(fixed)|{'parent_evidence','remote'}
         and all(type(value.get(k)) is type(v) and value[k]==v for k,v in fixed.items()),'refactor_rollback_evidence')
    refactor_parent_value(value['parent_evidence'])
    need(valid_refactor_result(original,value['remote']),'refactor_rollback_evidence')
    return refactor_policy(),value['remote'],value['parent_evidence']


def refactor(rollback=False):
    need(type(rollback) is bool,'mode')
    operation=REFACTOR_ROLLBACK_OPERATION if rollback else REFACTOR_OPERATION
    work=Path(os.environ['RUNNER_TEMP'])/'search3-public-install';work.mkdir(mode=0o700)
    key,known=work/'key',work/'known'
    evidence={'status':'blocked_no_replay','operation_id':operation,'source_sha':REFACTOR_SOURCE,
              'manifest_sha256':REFACTOR_MANIFEST_SHA,'read_only':False,'phase':'identity','ssh_started':False}
    complete=False
    try:
        event=json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
        if rollback:
            identity,original_run=refactor_event_identity(event,os.environ,True)
            evidence.update(identity,original_refactor_run_id=original_run,phase='admission')
            original,artifact=refactor_rollback_admission(identity,original_run);evidence['phase']='artifact'
            policy,accepted,parent_value=refactor_source_evidence(download(artifact['id'],artifact['digest'].removeprefix('sha256:')),original)
            dependency=tv_successor_evidence()
            restoration=handoff_restoration_evidence()
            home_restoration=home_restoration_evidence()
            program=refactor_rollback_remote_program(policy,identity,original,accepted,parent_value,tv_successor=dependency,
                                                    handoff_restoration=restoration,home_restoration=home_restoration)
            evidence.update(parent_site=refactor_parent_identity(),original_refactor=original,handoff_restoration_evidence=restoration,
                            home_restoration_evidence=home_restoration)
        else:
            identity=refactor_event_identity(event,os.environ);evidence.update(identity,phase='admission');refactor_admission(identity)
            evidence['phase']='artifact'
            parent_value=refactor_parent_evidence(download(REFACTOR_PARENT['artifact_id'],REFACTOR_PARENT['artifact_sha256']))
            policy,files=refactor_package(download(REFACTOR_ARTIFACT,REFACTOR_ARTIFACT_SHA),parent_value)
            dependency=tv_successor_evidence()
            program=refactor_remote_program(policy,files,identity,parent_value,tv_successor=dependency)
            evidence.update(parent_site=refactor_parent_identity(),parent_evidence=parent_value)
        # Exact existing carrier, key handling, options, output bound and timeout.
        host,user=os.environ['PREVIEW_HOST'],os.environ['PREVIEW_USER']
        need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]{0,252}',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
        raw=os.environ['PREVIEW_KEY'].replace('\r','');need(0<len(raw)<=32768,'ssh_key')
        if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
        with key.open('x') as stream:stream.write(raw.rstrip()+'\n')
        key.chmod(0o600)
        scan=carrier.run(['ssh-keyscan','-T','15','-t','ed25519',host]);need(0<len(scan)<=8192,'host_key')
        known.write_bytes(scan);known.chmod(0o600)
        options=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','ConnectTimeout=15']
        if rollback:refactor_rollback_commands(identity,original_run,original);rollback_main(identity)
        else:refactor_admission(identity)
        need(tv_successor_evidence()==dependency,'tv_successor_changed')
        if rollback:
            need(handoff_restoration_evidence()==restoration,'handoff_restoration_changed')
            need(home_restoration_evidence()==home_restoration,'home_restoration_changed')
        evidence.update(status='unknown_no_replay',phase='remote',ssh_started=True)
        response=carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'],input=program.encode(),timeout=180)
        need(len(response)<=32768,'receipt_limit');result=json.loads(response)
        complete=valid_refactor_result(identity,result,original,accepted) if rollback else valid_refactor_result(identity,result)
        evidence['remote']=result
        if rollback:rollback_main(identity)
        else:refactor_final_heads(identity)
        evidence.update(status=result['status'],phase='complete')
    except Exception as error:
        complete=False
        evidence['error_type']=type(error).__name__ if type(error) in (RuntimeError,ValueError,KeyError,OSError,subprocess.CalledProcessError,subprocess.TimeoutExpired) else 'RefactorError'
    finally:
        key.unlink(missing_ok=True);known.unlink(missing_ok=True)
        (work/'evidence.json').write_text(json.dumps(evidence,sort_keys=True)+'\n')
    print(json.dumps(evidence,sort_keys=True));need(complete,'refactor_rollback_not_verified' if rollback else 'refactor_not_verified')


# Integration only, after source ownership is released:
# command_mode(['refactor']) -> 'refactor'
# command_mode(['refactor-rollback']) -> 'refactor-rollback'
# dispatcher: refactor() / refactor(rollback=True)
# Existing workflow exact-command job filter and mode expression gain these two
# fixed commands. No new job, lock, permissions, triggers, secrets, or transport.




HANDOFF_PREFIX = '/update-site-country-handoff'
HANDOFF_OPERATION = 'site-country-offer-handoff-20261121-v1'
HANDOFF_ROLLBACK_PREFIX = '/rollback-site-country-handoff'
HANDOFF_ROLLBACK_OPERATION = 'site-country-offer-handoff-rollback-20261121-v1'
HANDOFF_SOURCE_PR = 5146
HANDOFF_SOURCE = '1ebbd422f2d82f5e64e92d6f7584d46ebb14eff2'
HANDOFF_TREE = 'f50a49a52ccc648f53b11c768c216e494d281a9a'
# The reviewed source head predates SEARCH/LOCAL integration; its merge tree is
# pinned separately and must preserve the exact two accepted PHP blobs.
HANDOFF_SOURCE_MERGE = '2ac85c2ed4cdfba2c6c30029a8e9d6439d04aa93'
HANDOFF_SOURCE_MERGE_TREE = '6876a477ec5d6e58c07aff87fc165af6b90d513f'
HANDOFF_SOURCE_BASE = '458602aa945afec1c3d4a20434bae1945e2a6844'
HANDOFF_CHECKS = ('seo-core','predeploy-contract','seo-structure','contract','content-ux','guard',
                  'publishability','country-handoff','bundles','content-live','seo-primitives')
HANDOFF_PARENT = {
    'control_source_sha':'1e6a8ae4ec059d27c82e6d005425bb4abbfe82a1',
    'run_id':'44915366099','command_comment_id':7064306296,'job_id':136879707131,
    'artifact_id':14711761796,
    'artifact_sha256':'efbe359d0f93579947ba7768116015f31f27df58c479dbf89eaf0aa2b3da2a8d',
    'receipt_sha256':'975580c0bedae4c6df7b0a70052ddcf0fd54150461691a9394aeebdf62f7171a',
    'policy_sha256':'0d65a8cb7c119a97b2fda3bc3bafb8f5075d55c48c5a83812bab5e702126f751',
    'snapshot_manifest_sha256':'73dab12ea0f14fad99a2315314c647739f4f7912b4bac84fe11e0700b9e18d90',
    'terminal_comment_id':7064432337,
}
HANDOFF_ORDER = ('seo-page-primitives-v1.php','country-page-v1.php')
HANDOFF_TARGETS = {
    'seo-page-primitives-v1.php':{
        'blob':'7e43600b8fde14f351d18cfa18a35043d5c3ef13',
        'before':{'state':'present','mode':'0644','size':4086,'sha256':'91fd195d006c6579922280d37496e4d9ae523d962936cea088b02a7875574e0a'},
        'after':{'state':'present','mode':'0644','size':4820,'sha256':'731c706582990fd541b2bec6f15b641835fe6aeeebefcdc6ee3758dba5077da6'}},
    'country-page-v1.php':{
        'blob':'2e0cfbea9e3b71ac09ece9669f24ec730116378b',
        'before':{'state':'present','mode':'0644','size':12151,'sha256':'7a1c91a899a3bac1daa285a07ad6b586604fb08008063b6263a91e6abd51017b'},
        'after':{'state':'present','mode':'0644','size':12185,'sha256':'96e3897da97defd11186df1e751f8d167a08d2bd9b7825dfc93471daf888de6c'}},
}


def handoff_parent_identity():
    return {k:HANDOFF_PARENT[k] for k in ('control_source_sha','run_id','command_comment_id')}


def handoff_policy():
    policy={'operation':HANDOFF_OPERATION,'source':HANDOFF_SOURCE,'tree':HANDOFF_TREE,
            'order':HANDOFF_ORDER,'targets':HANDOFF_TARGETS,'parent':HANDOFF_PARENT}
    return policy|{'manifest':sha(json.dumps(policy,sort_keys=True,separators=(',',':')).encode())}


def handoff_parent_value(value):
    raw=(json.dumps(value,sort_keys=True)+'\n').encode()
    need(sha(raw)==HANDOFF_PARENT['receipt_sha256'],'handoff_parent_digest')
    packet=io.BytesIO()
    with zipfile.ZipFile(packet,'w') as archive:archive.writestr('evidence.json',raw)
    policy,accepted,previous=refactor_source_evidence(packet.getvalue(),handoff_parent_identity())
    need(policy['manifest']==HANDOFF_PARENT['policy_sha256']
         and accepted['snapshot_manifest_sha256']==HANDOFF_PARENT['snapshot_manifest_sha256'],'handoff_parent_receipt')
    return policy,accepted,previous


def handoff_parent_evidence(raw):
    need(sha(raw)==HANDOFF_PARENT['artifact_sha256'],'handoff_parent_artifact')
    with zip_files(raw) as archive:
        need(archive.namelist()==['evidence.json'],'handoff_parent_artifact')
        data=archive.read('evidence.json');need(sha(data)==HANDOFF_PARENT['receipt_sha256'],'handoff_parent_digest')
    value=json.loads(data);handoff_parent_value(value)
    return value


def handoff_package():
    files={}
    for path in HANDOFF_ORDER:
        target=HANDOFF_TARGETS[path];blob=api('/git/blobs/'+target['blob'])
        need(blob.get('sha')==target['blob'] and blob.get('encoding')=='base64'
             and blob.get('size')==target['after']['size'],'handoff_payload_blob')
        raw=base64.b64decode(blob['content'].replace('\n',''),validate=True)
        need(0<len(raw)<=MAX_BYTES and raw.startswith(b'<?php')
             and target['after']=={'state':'present','sha256':sha(raw),'size':len(raw),'mode':'0644'}
             and hashlib.sha1(b'blob '+str(len(raw)).encode()+b'\0'+raw).hexdigest()==target['blob'],'handoff_payload_bytes')
        files[path]=base64.b64encode(raw).decode()
    return files


HANDOFF_COMMON_REMOTE = r'''
HANDOFF_PREPARED_PATHS=('reservation.json','snapshot/manifest.json')+tuple(p+str(i) for i in range(2) for p in ('snapshot/','drill/','intent-'))
_handoff_local_restored=None
_handoff_local_before=None
def handoff_stamp_rows(rows):return refactor_stamp_rows(rows)
def handoff_absent(operation):
 with base.parent(operation) as (fd,name):
  try:os.stat(name,dir_fd=fd,follow_symlinks=False)
  except FileNotFoundError:return
  raise RuntimeError('handoff_namespace')
def handoff_projection(saved,before,restored):
 require(isinstance(before,dict) and isinstance(restored,dict)
         and set(before)==set(restored)==set(HANDOFF_POLICY['order']),'handoff_restoration_scope')
 projected=dict(saved)
 for path in HANDOFF_POLICY['order']:
  require(saved.get(path)==before[path] and before[path][0]==HANDOFF_POLICY['targets'][path]['before'],
          'handoff_restoration_before')
  old=checked_stamp(before[path][1],before[path][0]);row=restored[path]
  require(isinstance(row,list) and len(row)==2 and row[0]==before[path][0],'handoff_restoration_target')
  current=checked_stamp(row[1],row[0])
  require(current[:-1]==old[:-1],'handoff_restoration_target')
  projected[path]=row
 return projected
def handoff_ancestor_inventory():
 value=refactor_ancestor_inventory()
 value['refactor']=refactor_stamp_rows(site_read_records(refactored,REFACTOR_PREPARED_PATHS+('result.json',)))
 value['refactor_stage']=refactor_stamp_rows({p:refactored.read('stage/'+str(i))[:2] for i,p in enumerate(REFACTOR_POLICY['order'])})
 return value
def handoff_refactor_parent():
 site_continuation_namespace_absent();handoff_absent(REFACTOR_ROLLBACK_OPERATION)
 records=site_read_records(refactored,REFACTOR_PREPARED_PATHS+('result.json',));record=lambda p:json.loads(records[p][2])
 require(record('reservation.json')=={'operation':REFACTOR_POLICY['operation'],**REFACTOR_ORIGINAL,
         'source':REFACTOR_POLICY['source'],'manifest':REFACTOR_POLICY['manifest'],'parent_site':SITE_ORIGINAL}
         and record('result.json')==REFACTOR_ACCEPTED,'handoff_parent_identity')
 snapshot=record('snapshot/manifest.json');order=REFACTOR_POLICY['order'];targets=REFACTOR_POLICY['targets']
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_site','before','guarded','ancestors'}
         and snapshot['source']==REFACTOR_POLICY['source'] and snapshot['manifest']==REFACTOR_POLICY['manifest']
         and snapshot['parent_site']==SITE_ORIGINAL and set(snapshot['before'])==set(order)
         and set(snapshot['guarded'])==(set(POLICY['expected'])-set(order))|set(checked_protected_paths())
             |set(SITE_POLICY['dependencies'])|set(SITE_POLICY['optional_dependencies'])|set(SITE_POLICY['order'])
         and digest(records['snapshot/manifest.json'][2])==REFACTOR_ACCEPTED['snapshot_manifest_sha256'],
         'handoff_parent_snapshot')
 saved=snapshot['guarded']
 if _handoff_local_restored is not None:saved=handoff_projection(saved,_handoff_local_before,_handoff_local_restored)
 previous=refactor_guard_from_snapshot(saved)
 require(refactor_ancestor_inventory()==snapshot['ancestors'],'handoff_parent_changed')
 current=public.inventory(order);stages={}
 for index,path in enumerate(order):
  before=snapshot['before'][path]
  require(isinstance(before,list) and len(before)==2 and before[0]==targets[path]['before'],'handoff_parent_snapshot')
  old=checked_stamp(before[1],targets[path]['before']);intent=record('intent-'+str(index))
  require(isinstance(intent,dict) and set(intent)=={'path','before','after','previous_identity','new_identity'}
          and intent['path']==path and intent['before']==targets[path]['before'] and intent['after']==targets[path]['after']
          and intent['previous_identity']==before[1],'handoff_parent_identity')
  new=checked_stamp(intent['new_identity'],targets[path]['after']);prior=refactored.read('stage/'+str(index))[:2];stages[path]=prior
  require(records['snapshot/'+str(index)][0]==records['drill/'+str(index)][0]==targets[path]['before']
          and current[path][0]==targets[path]['after'] and current[path][1][:-1]==new[:-1]
          and prior[0]==targets[path]['before'] and prior[1][:-1]==old[:-1],'handoff_parent_snapshot')
 ancestors={**snapshot['ancestors'],'refactor':refactor_stamp_rows(records),'refactor_stage':refactor_stamp_rows(stages)}
 # The caller carries these validated preimages into the transaction. No later
 # inventory may replace their authority before reservation or exchange.
 return ({p:r for p,r in previous.items() if p not in HANDOFF_POLICY['order']}|current,
         {p:previous[p] for p in HANDOFF_POLICY['order']},ancestors)
def handoff_check_ancestors():
 site_continuation_namespace_absent();handoff_absent(REFACTOR_ROLLBACK_OPERATION)
 require(handoff_ancestor_inventory()==ancestors,'handoff_parent_changed')
 require(public.inventory(guarded)==guarded,'protected_changed')
def handoff_check_targets(expected):
 require(public.inventory(HANDOFF_POLICY['order'])==expected,'handoff_target_changed')
def handoff_restore_one(store,path,index,current,prior,capture):
 handoff_check_ancestors()
 require(public.read(path)[:2]==current and store.read('stage/'+str(index))[:2]==prior,'rollback_preflight_race')
 capture_owned(public,private,path,capture,current[0],current[1]);restore_capture(public,store,path,'stage/'+str(index))
 restored=public.read(path)[:2]
 require(restored[0]==prior[0] and restored[1][:-1]==prior[1][:-1],'rollback_incomplete')
 return restored
def handoff_bind_restoration(before):
 global _handoff_local_before,_handoff_local_restored
 handoff_check_ancestors();restored=public.inventory(HANDOFF_POLICY['order'])
 _handoff_local_before=refactor_stamp_rows(before);_handoff_local_restored=refactor_stamp_rows(restored)
 # This local projection is justified by the validated before/stage inodes.
 # A later historical rollback requires the published native terminal proof.
 handoff_refactor_parent();handoff_check_targets(restored);handoff_check_ancestors()
 return _handoff_local_restored
'''


HANDOFF_REMOTE = r'''
result={'schema_version':1,'operation_id':HANDOFF_POLICY['operation'],'source_sha':HANDOFF_POLICY['source'],
        'manifest_sha256':HANDOFF_POLICY['manifest'],**IDENTITY,'parent_refactor':REFACTOR_ORIGINAL,
        'parent_receipt_sha256':HANDOFF_POLICY['parent']['receipt_sha256'],
        'status':'blocked_no_replay','phase':'preflight','supplier_calls':0,'database_reads':0,'database_writes':0,
        'lead_calls':0,'booking_calls':0,'application_calls':0,'written_files':[],'rollback_files':[],
        'reservation_created':False,'snapshot_verified':False,'restore_drill_verified':False,
        'protected_unchanged':False,'unchanged_files_verified':False,'parent_records_unchanged':False,
        'original_inodes_verified':False,'failed_namespace_absent':False,'snapshot_manifest_sha256':None,'restored_guard':None}
public=base=original=updated=published=refactored=private=None;reserved=False;switched=[];owned={};prior={};prepared=None
try:
 require(hasattr(os,'O_PATH') and hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY')
         and hasattr(ctypes.CDLL(None),'renameat2'),'unsupported_platform')
 order=HANDOFF_POLICY['order'];targets=HANDOFF_POLICY['targets'];home=os.environ.get('HOME','')
 require(len(order)==2 and set(PAYLOAD)==set(targets)==set(order)
         and set(order)<=set(SITE_POLICY['dependencies']) and not set(order)&set(SITE_POLICY['order']),'handoff_scope')
 payload={p:base64.b64decode(PAYLOAD[p],validate=True) for p in order}
 require(all(targets[p]['after']=={'state':'present','sha256':digest(raw),'size':len(raw),'mode':'0644'}
             and 0<len(raw)<=MAX_BYTES for p,raw in payload.items()),'payload_bytes')
 public=Files(home+'/www/anytoour.ru');base=Files(home+'/.anytoour-search3-root-gateway');private_root(base)
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation']);private_root(original)
 updated=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_POLICY['operation']);private_root(updated)
 published=Files(home+'/.anytoour-search3-root-gateway/'+SITE_POLICY['operation']);private_root(published)
 refactored=Files(home+'/.anytoour-search3-root-gateway/'+REFACTOR_POLICY['operation']);private_root(refactored)
 handoff_absent(HANDOFF_ROLLBACK_OPERATION)
 require(os.fstat(base.fd).st_dev==os.fstat(public.fd).st_dev,'different_filesystem')
 guarded,before,ancestors=handoff_refactor_parent()
 require(all(row[0]==targets[p]['before'] and row[1][6]==os.getuid() for p,row in before.items()),'predecessor_drift')
 handoff_check_ancestors();handoff_check_targets(before)
 try:os.mkdir(HANDOFF_POLICY['operation'],0o700,dir_fd=base.fd)
 except FileExistsError:raise RuntimeError('operation_exists_no_replay') from None
 reserved=True;result['reservation_created']=True;sync(base.fd)
 private=Files(home+'/.anytoour-search3-root-gateway/'+HANDOFF_POLICY['operation'],('snapshot','drill','stage','rollback'),0o700);private_root(private)
 reservation={'operation':HANDOFF_POLICY['operation'],**IDENTITY,'source':HANDOFF_POLICY['source'],
              'manifest':HANDOFF_POLICY['manifest'],'parent_refactor':REFACTOR_ORIGINAL}
 private.create('reservation.json',canonical(reservation));result['phase']='snapshot'
 snapshot={'source':HANDOFF_POLICY['source'],'manifest':HANDOFF_POLICY['manifest'],'parent_refactor':REFACTOR_ORIGINAL,
           'before':handoff_stamp_rows(before),'guarded':handoff_stamp_rows(guarded),'ancestors':ancestors}
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
 prepared=site_read_records(private,HANDOFF_PREPARED_PATHS)
 require(prepared['reservation.json'][2]==canonical(reservation) and prepared['snapshot/manifest.json'][2]==canonical(snapshot)
         and all(prepared['snapshot/'+str(i)][0]==prepared['drill/'+str(i)][0]==targets[p]['before']
                 and json.loads(prepared['intent-'+str(i)][2])=={'path':p,'before':targets[p]['before'],'after':targets[p]['after'],
                      'previous_identity':list(before[p][1]),'new_identity':list(owned[p][1])} for i,p in enumerate(order)),'snapshot_invalid')
 current=dict(before);result['phase']='assets'
 for index,path in enumerate(order):
  handoff_check_ancestors();handoff_check_targets(current)
  require(site_read_records(private,HANDOFF_PREPARED_PATHS)==prepared
          and private.read('stage/'+str(index))[:2]==owned[path],'prewrite_race')
  with private.parent('stage/'+str(index)) as (src,name),public.parent(path) as (dst,target):
   require(public.read(path)[:2]==before[path],'entry_prewrite_race')
   exchange(src,name,dst,target);switched.append(path);result['written_files'].append(path);sync(src);sync(dst)
  observed=public.read(path)[:2]
  require(observed[0]==targets[path]['after'] and observed[1][:-1]==owned[path][1][:-1],'handoff_readback')
  owned[path]=observed;current[path]=observed
  try:
   prior[path]=private.read('stage/'+str(index))[:2]
   require(prior[path][0]==targets[path]['before'] and prior[path][1][:-1]==before[path][1][:-1],'handoff_exchange_conflict')
  except Exception:
   handoff_check_ancestors()
   capture_owned(public,private,path,'rollback/conflict-'+str(index),observed[0],observed[1])
   restore_capture(public,private,path,'stage/'+str(index));switched.remove(path)
   raise RuntimeError('handoff_exchange_conflict')
  handoff_check_targets(current);handoff_check_ancestors()
 require(site_read_records(private,HANDOFF_PREPARED_PATHS)==prepared
         and all(private.read('stage/'+str(i))[:2]==prior[p] for i,p in enumerate(order)),'handoff_snapshot_conflict')
 result.update(status='country_handoff_updated_verified',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
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
    handoff_restore_one(private,path,index,current_row,saved,'rollback/'+str(index));result['rollback_files'].append(path)
   except Exception:recovery_ok=False
  try:
   require(recovery_ok,'rollback_incomplete');handoff_check_ancestors()
   restored=public.inventory(order)
   require(all(row[0]==before[p][0] and row[1][:-1]==before[p][1][:-1] for p,row in restored.items())
           and result['rollback_files']==list(reversed(result['written_files'])),'rollback_incomplete')
   result['restored_guard']=handoff_bind_restoration(before)
   result.update(status='rolled_back_no_replay',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
                 parent_records_unchanged=True,original_inodes_verified=True)
  except Exception:result['status']='unknown_no_replay'
finally:
 if reserved and private is not None:
  try:private.create('result.json',canonical(result))
  except Exception:result.update(status='unknown_no_replay',reason='receipt_failed')
 for store in (private,refactored,published,updated,original,base,public):
  if store is not None:store.close()
print(json.dumps(result,sort_keys=True))
'''


HANDOFF_ROLLBACK_REMOTE = r'''
result={'schema_version':1,'operation_id':HANDOFF_ROLLBACK_OPERATION,'source_sha':HANDOFF_POLICY['source'],
        'manifest_sha256':HANDOFF_POLICY['manifest'],**IDENTITY,'parent_refactor':REFACTOR_ORIGINAL,
        'parent_receipt_sha256':HANDOFF_POLICY['parent']['receipt_sha256'],'original_handoff':HANDOFF_ORIGINAL,
        'status':'blocked_no_replay','phase':'preflight','supplier_calls':0,'database_reads':0,'database_writes':0,
        'lead_calls':0,'booking_calls':0,'application_calls':0,'written_files':[],'rollback_files':[],
        'reservation_created':False,'snapshot_verified':False,'restore_drill_verified':False,
        'protected_unchanged':False,'unchanged_files_verified':False,'parent_records_unchanged':False,
        'original_inodes_verified':False,'failed_namespace_absent':False,
        'snapshot_manifest_sha256':HANDOFF_ACCEPTED['snapshot_manifest_sha256'],'restored_guard':None}
public=base=original=updated=published=refactored=handed=private=None;reserved=False
try:
 require(hasattr(os,'O_PATH') and hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY')
         and hasattr(ctypes.CDLL(None),'renameat2'),'unsupported_platform')
 order=HANDOFF_POLICY['order'];targets=HANDOFF_POLICY['targets'];home=os.environ.get('HOME','')
 public=Files(home+'/www/anytoour.ru');base=Files(home+'/.anytoour-search3-root-gateway');private_root(base)
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation']);private_root(original)
 updated=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_POLICY['operation']);private_root(updated)
 published=Files(home+'/.anytoour-search3-root-gateway/'+SITE_POLICY['operation']);private_root(published)
 refactored=Files(home+'/.anytoour-search3-root-gateway/'+REFACTOR_POLICY['operation']);private_root(refactored)
 handed=Files(home+'/.anytoour-search3-root-gateway/'+HANDOFF_POLICY['operation']);private_root(handed)
 require(os.fstat(base.fd).st_dev==os.fstat(public.fd).st_dev,'different_filesystem')
 site_continuation_namespace_absent()
 paths=HANDOFF_PREPARED_PATHS+('result.json',);records=site_read_records(handed,paths)
 record=lambda p:json.loads(records[p][2])
 require(record('reservation.json')=={'operation':HANDOFF_POLICY['operation'],**HANDOFF_ORIGINAL,
         'source':HANDOFF_POLICY['source'],'manifest':HANDOFF_POLICY['manifest'],'parent_refactor':REFACTOR_ORIGINAL}
         and record('result.json')==HANDOFF_ACCEPTED,'handoff_original_identity')
 snapshot=record('snapshot/manifest.json')
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_refactor','before','guarded','ancestors'}
         and snapshot['source']==HANDOFF_POLICY['source'] and snapshot['manifest']==HANDOFF_POLICY['manifest']
         and snapshot['parent_refactor']==REFACTOR_ORIGINAL and set(snapshot['before'])==set(order)
         and set(snapshot['guarded'])==((set(POLICY['expected'])|set(checked_protected_paths())
             |set(SITE_POLICY['dependencies'])|set(SITE_POLICY['optional_dependencies'])|set(SITE_POLICY['order']))-set(order))
         and digest(records['snapshot/manifest.json'][2])==HANDOFF_ACCEPTED['snapshot_manifest_sha256'],'handoff_snapshot_conflict')
 ancestors=snapshot['ancestors'];guarded=refactor_guard_from_snapshot(snapshot['guarded'])
 require(handoff_ancestor_inventory()==ancestors,'handoff_parent_changed')
 current=public.inventory(order);prior={};old_identities={}
 for index,path in enumerate(order):
  before=snapshot['before'][path]
  require(isinstance(before,list) and len(before)==2 and before[0]==targets[path]['before'],'handoff_snapshot_conflict')
  old_identities[path]=checked_stamp(before[1],targets[path]['before'])
  require(records['snapshot/'+str(index)][0]==records['drill/'+str(index)][0]==targets[path]['before'],'handoff_snapshot_conflict')
  intent=record('intent-'+str(index))
  require(isinstance(intent,dict) and set(intent)=={'path','before','after','previous_identity','new_identity'}
          and intent['path']==path and intent['before']==targets[path]['before'] and intent['after']==targets[path]['after']
          and intent['previous_identity']==before[1],'handoff_original_identity')
  new=checked_stamp(intent['new_identity'],targets[path]['after']);prior[path]=handed.read('stage/'+str(index))[:2]
  require(current[path][0]==targets[path]['after'] and current[path][1][:-1]==new[:-1]
          and prior[path][0]==targets[path]['before'] and prior[path][1][:-1]==old_identities[path][:-1],'handoff_snapshot_conflict')
 handoff_check_ancestors();handoff_check_targets(current)
 require(site_read_records(handed,paths)==records,'rollback_preflight_race')
 result.update(snapshot_verified=True,restore_drill_verified=True)
 try:os.mkdir(HANDOFF_ROLLBACK_OPERATION,0o700,dir_fd=base.fd)
 except FileExistsError:raise RuntimeError('operation_exists_no_replay') from None
 reserved=True;result['reservation_created']=True;sync(base.fd)
 private=Files(home+'/.anytoour-search3-root-gateway/'+HANDOFF_ROLLBACK_OPERATION,('quarantine',),0o700);private_root(private)
 private.create('reservation.json',canonical({'operation':HANDOFF_ROLLBACK_OPERATION,**IDENTITY,'original_handoff':HANDOFF_ORIGINAL,
                'source':HANDOFF_POLICY['source'],'manifest':HANDOFF_POLICY['manifest'],'parent_refactor':REFACTOR_ORIGINAL}))
 result['phase']='assets'
 for index in reversed(range(2)):
  path=order[index];handoff_check_ancestors();handoff_check_targets(current)
  require(site_read_records(handed,paths)==records,'handoff_snapshot_conflict')
  current[path]=handoff_restore_one(handed,path,index,current[path],prior[path],'quarantine/'+str(index))
  result['rollback_files'].append(path)
  handoff_check_targets(current);handoff_check_ancestors()
 require(all(current[p][0]==targets[p]['before'] and current[p][1][:-1]==old_identities[p][:-1] for p in order)
         and site_read_records(handed,paths)==records,'rollback_incomplete')
 result['restored_guard']=handoff_bind_restoration({p:(snapshot['before'][p][0],tuple(snapshot['before'][p][1])) for p in order})
 result.update(status='country_handoff_rolled_back_verified',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
               parent_records_unchanged=True,original_inodes_verified=True)
except Exception as error:
 result['status']='unknown_no_replay' if reserved else 'blocked_no_replay'
 result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
finally:
 if reserved and private is not None:
  try:private.create('result.json',canonical(result))
  except Exception:result.update(status='unknown_no_replay',reason='receipt_failed')
 for store in (private,handed,refactored,published,updated,original,base,public):
  if store is not None:store.close()
print(json.dumps(result,sort_keys=True))
'''


HANDOFF_REASONS = REFACTOR_REASONS + ('handoff_namespace','handoff_restoration_scope','handoff_restoration_before',
    'handoff_restoration_target','handoff_restoration_record','handoff_restoration_changed','handoff_parent_identity',
    'handoff_parent_snapshot','handoff_parent_changed','handoff_target_changed','handoff_scope','handoff_readback',
    'handoff_exchange_conflict','handoff_snapshot_conflict','handoff_original_identity')


def _handoff_program_prefix(policy,identity,parent_value,tv_successor=None):
    need(policy==handoff_policy(),'handoff_policy');update_runtime_identity(identity)
    need(identity['run_id']!=HANDOFF_PARENT['run_id'],'handoff_runtime_identity')
    parent,accepted,previous=handoff_parent_value(parent_value)
    return (_refactor_program_prefix(parent,identity,previous,tv_successor)
            +'\nHANDOFF_POLICY='+repr(policy)+'\nREFACTOR_ORIGINAL='+repr(handoff_parent_identity())
            +'\nREFACTOR_ACCEPTED='+repr(accepted)+'\nREFACTOR_ROLLBACK_OPERATION='+repr(REFACTOR_ROLLBACK_OPERATION)
            +'\nHANDOFF_ROLLBACK_OPERATION='+repr(HANDOFF_ROLLBACK_OPERATION)
            +'\ndef re_safe(value): return value in '+repr(HANDOFF_REASONS)+'\n'+HANDOFF_COMMON_REMOTE)


def handoff_remote_program(policy,files,identity,parent_value,*,tv_successor=None):
    prefix=_handoff_program_prefix(policy,identity,parent_value,tv_successor)
    need(isinstance(files,dict) and set(files)==set(HANDOFF_ORDER),'handoff_payload')
    for path in HANDOFF_ORDER:
        raw=base64.b64decode(files[path],validate=True)
        need(0<len(raw)<=MAX_BYTES and raw.startswith(b'<?php') and HANDOFF_TARGETS[path]['after']==
             {'state':'present','mode':'0644','size':len(raw),'sha256':sha(raw)},'handoff_payload_bytes')
    program=prefix+'\nPAYLOAD='+repr(files)+'\n'+HANDOFF_REMOTE;ast.parse(program)
    return program


def handoff_rollback_remote_program(policy,identity,original,accepted,parent_value,*,tv_successor=None,home_restoration=None):
    prefix=_handoff_program_prefix(policy,identity,parent_value,tv_successor);update_runtime_identity(original)
    need(identity['run_id']!=original['run_id'] and identity['command_comment_id']!=original['command_comment_id']
         and valid_handoff_result(original,accepted),'handoff_rollback_evidence')
    if home_restoration is not None and home_restoration.get('status')=='restored':
        need(original==home_parent_identity(),'home_restoration_parent')
    program=(prefix+'\nHANDOFF_ORIGINAL='+repr(original)+'\nHANDOFF_ACCEPTED='+repr(accepted)+'\n'
             +home_restoration_prefix(home_restoration,'handoff')+HANDOFF_ROLLBACK_REMOTE)
    ast.parse(program);return program


def _handoff_restored_guard(value):
    need(isinstance(value,dict) and set(value)==set(HANDOFF_ORDER),'handoff_restoration_scope')
    for path,row in value.items():
        need(isinstance(row,list) and len(row)==2 and row[0]==HANDOFF_TARGETS[path]['before'],'handoff_restoration_target')
        ident=row[1]
        need(isinstance(ident,list) and len(ident)==9 and all(type(v) is int for v in ident)
             and ident[0]>=0 and ident[1]>0 and ident[2]==(stat.S_IFREG|0o644) and ident[3]==1
             and ident[4]==row[0]['size'] and all(ident[i]>=0 for i in (5,6,7,8)),'handoff_restoration_target')


def valid_handoff_result(identity,result,original=None,accepted=None):
    rollback=original is not None;policy=handoff_policy()
    fixed={'schema_version':1,'operation_id':HANDOFF_ROLLBACK_OPERATION if rollback else HANDOFF_OPERATION,
           'source_sha':HANDOFF_SOURCE,'manifest_sha256':policy['manifest'],**identity,
           'parent_refactor':handoff_parent_identity(),'parent_receipt_sha256':HANDOFF_PARENT['receipt_sha256']}
    if rollback:fixed.update(original_handoff=original,snapshot_manifest_sha256=accepted['snapshot_manifest_sha256'])
    counters=('supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
    flags=('reservation_created','snapshot_verified','restore_drill_verified','protected_unchanged',
           'unchanged_files_verified','parent_records_unchanged','original_inodes_verified','failed_namespace_absent')
    required=set(fixed)|set(counters)|set(flags)|{'status','phase','written_files','rollback_files','snapshot_manifest_sha256','restored_guard'}
    need(isinstance(result,dict) and required<=set(result)<=required|{'reason'},'handoff_receipt_shape')
    need(all(type(result.get(k)) is type(v) and result[k]==v for k,v in fixed.items()),'handoff_receipt_identity')
    need(all(type(result[k]) is int and result[k]==0 for k in counters)
         and all(type(result[k]) is bool for k in flags),'handoff_receipt_counters')
    success='country_handoff_rolled_back_verified' if rollback else 'country_handoff_updated_verified'
    states=('blocked_no_replay','unknown_no_replay',success) if rollback else ('blocked_no_replay','unknown_no_replay','rolled_back_no_replay',success)
    need(result['status'] in states and result['phase'] in ('preflight','snapshot','assets','complete')
         and ('reason' not in result or result['reason'] in HANDOFF_REASONS),'handoff_receipt_status')
    written,restored=result['written_files'],result['rollback_files']
    need(isinstance(written,list) and written in [list(HANDOFF_ORDER[:i]) for i in range(3)]
         and isinstance(restored,list) and all(isinstance(p,str) for p in restored) and len(set(restored))==len(restored),'handoff_receipt_paths')
    order=list(reversed(HANDOFF_ORDER if rollback else written))
    need(all(p in order for p in restored) and restored==[p for p in order if p in restored]
         and (not rollback or not written),'handoff_receipt_paths')
    snapshot=result['snapshot_manifest_sha256']
    need(snapshot is None or isinstance(snapshot,str) and re.fullmatch('[a-f0-9]{64}',snapshot),'handoff_receipt_snapshot')
    if result['status']=='blocked_no_replay':
        need(not result['reservation_created'] and result['phase']=='preflight' and not written and not restored,'handoff_receipt_incomplete')
    if result['status'] in (success,'rolled_back_no_replay'):
        need(result['phase']=='complete' and all(result[k] for k in ('reservation_created','protected_unchanged',
             'unchanged_files_verified','parent_records_unchanged','original_inodes_verified','failed_namespace_absent')),'handoff_receipt_incomplete')
    if result['status']=='rolled_back_no_replay':need(restored==list(reversed(written)),'handoff_receipt_incomplete')
    if result['status']==success:
        need(all(result[k] for k in flags) and snapshot is not None and 'reason' not in result
             and (restored==list(reversed(HANDOFF_ORDER)) if rollback else written==list(HANDOFF_ORDER) and not restored),'handoff_receipt_incomplete')
    if result['status'] in ('rolled_back_no_replay','country_handoff_rolled_back_verified'):_handoff_restored_guard(result['restored_guard'])
    elif result['status']!='unknown_no_replay':need(result['restored_guard'] is None,'handoff_receipt_incomplete')
    return result['status']==success


def _handoff_source_value(value,original,*,recovery=False,rollback_original=None,accepted=None):
    update_runtime_identity(original)
    rollback=rollback_original is not None
    status='country_handoff_rolled_back_verified' if rollback else 'rolled_back_no_replay' if recovery else 'country_handoff_updated_verified'
    fixed={**original,'operation_id':HANDOFF_ROLLBACK_OPERATION if rollback else HANDOFF_OPERATION,
           'source_sha':HANDOFF_SOURCE,'manifest_sha256':handoff_policy()['manifest'],'read_only':False,
           'phase':'complete','ssh_started':True,'status':status,'parent_refactor':handoff_parent_identity()}
    if rollback:fixed.update(original_handoff=rollback_original,original_handoff_run_id=rollback_original['run_id'])
    optional={'home_restoration_evidence'} if rollback and isinstance(value,dict) and 'home_restoration_evidence' in value else set()
    need(isinstance(value,dict) and set(value)==set(fixed)|{'parent_evidence','remote'}|optional
         and all(type(value.get(k)) is type(v) and value[k]==v for k,v in fixed.items()),'handoff_rollback_evidence')
    if optional:home_restoration_value(value['home_restoration_evidence'])
    handoff_parent_value(value['parent_evidence'])
    success=valid_handoff_result(original,value['remote'],rollback_original,accepted)
    need(success or recovery and value['remote']['status']=='rolled_back_no_replay','handoff_rollback_evidence')
    return handoff_policy(),value['remote'],value['parent_evidence']


def handoff_source_evidence(raw,original):
    with zip_files(raw) as archive:
        need(archive.namelist()==['evidence.json'],'handoff_rollback_artifact')
        value=json.loads(archive.read('evidence.json'))
    return _handoff_source_value(value,original)


def handoff_restoration_value(value):
    need(isinstance(value,dict),'handoff_restoration_proof')
    if value=={'status':'absent'}:return value
    if value.get('status')=='zero_write':
        need(set(value)=={'status','original','install_evidence'},'handoff_restoration_proof')
        _handoff_zero_write_value(value['install_evidence'],value['original'])
        return value
    need(set(value)=={'status','original','install_evidence','restoration_evidence'} and value['status']=='restored',
         'handoff_restoration_proof')
    original=value['original'];installed=value['install_evidence'];restored=value['restoration_evidence']
    need(isinstance(installed,dict) and isinstance(restored,dict),'handoff_restoration_proof')
    recovery=installed.get('status')=='rolled_back_no_replay'
    _,accepted,_=_handoff_source_value(installed,original,recovery=recovery)
    if recovery:need(restored==installed,'handoff_restoration_proof')
    else:
        identity={k:restored.get(k) for k in ('control_source_sha','run_id','command_comment_id')}
        need(identity['run_id']!=original['run_id'] and identity['command_comment_id']!=original['command_comment_id'],
             'handoff_restoration_proof')
        _handoff_source_value(restored,identity,rollback_original=original,accepted=accepted)
    return value


def _handoff_zero_write_value(value,identity):
    update_runtime_identity(identity)
    fixed={**identity,'operation_id':HANDOFF_OPERATION,'source_sha':HANDOFF_SOURCE,
           'manifest_sha256':handoff_policy()['manifest'],'read_only':False,'status':'blocked_no_replay'}
    need(isinstance(value,dict) and all(type(value.get(k)) is type(v) and value[k]==v for k,v in fixed.items()),
         'handoff_zero_write')
    allowed=set(fixed)|{'phase','ssh_started','parent_refactor','parent_evidence','remote','error_type'}
    need(set(value)<=allowed and type(value.get('ssh_started')) is bool,'handoff_zero_write')
    if value['ssh_started']:
        need(value.get('phase')=='complete' and 'remote' in value and 'error_type' not in value,'handoff_zero_write')
        valid_handoff_result(identity,value['remote'])
        need(value['remote']['status']=='blocked_no_replay','handoff_zero_write')
        handoff_parent_value(value.get('parent_evidence'))
    else:
        need(value.get('phase') in ('admission','artifact') and 'remote' not in value
             and value.get('error_type') in ('RuntimeError','ValueError','KeyError','OSError','CalledProcessError'),
             'handoff_zero_write')
        if 'parent_evidence' in value:handoff_parent_value(value['parent_evidence'])
    if 'parent_refactor' in value:need(value['parent_refactor']==handoff_parent_identity(),'handoff_zero_write')


HANDOFF_RESTORATION_REMOTE = r'''
_handoff_restoration_bound=None
_handoff_restoration_saved=None
_handoff_original_guard=refactor_guard_from_snapshot
_handoff_original_check=refactor_check_ancestors
def handoff_restoration_read(saved):
 proof=HANDOFF_RESTORATION_PROOF
 if proof['status'] in ('absent','zero_write'):
  handoff_absent(HANDOFF_POLICY['operation']);handoff_absent(HANDOFF_ROLLBACK_OPERATION)
  return {'status':proof['status']}
 install=proof['install_evidence']['remote'];terminal=proof['restoration_evidence']['remote'];identity=proof['original']
 recovery=install['status']=='rolled_back_no_replay';written=install['written_files'];stores=[]
 try:
  child=Files(home+'/.anytoour-search3-root-gateway/'+HANDOFF_POLICY['operation']);stores.append(child);private_root(child)
  rows={}
  for path in HANDOFF_PREPARED_PATHS+('result.json',):
   row,ident,raw=child.read(path,True)
   if row=={'state':'absent'}:
    require(recovery and not written and path not in ('reservation.json','result.json'),'handoff_restoration_record')
   else:
    mode='0644' if path.startswith(('snapshot/','drill/')) and path!='snapshot/manifest.json' else '0600'
    require(row.get('mode')==mode and ident[6]==os.getuid(),'handoff_restoration_record')
   rows[path]=(row,ident,raw)
  require(rows['reservation.json'][2]==canonical({'operation':HANDOFF_POLICY['operation'],**identity,
          'source':HANDOFF_POLICY['source'],'manifest':HANDOFF_POLICY['manifest'],'parent_refactor':REFACTOR_ORIGINAL})
          and rows['result.json'][2]==canonical(install),'handoff_restoration_record')
  before={p:saved[p] for p in HANDOFF_POLICY['order']}
  if recovery and not written:
   # Preparation can fail between creating a stage and writing its intent.
   # No public exchange is admitted here: both complete stamps must still be
   # exactly the historical originals, and every observed private row is bound.
   handoff_absent(HANDOFF_ROLLBACK_OPERATION)
   require(not terminal['rollback_files'] and terminal['restored_guard']==before,'handoff_restoration_target')
   for index,path in enumerate(HANDOFF_POLICY['order']):
    stage=child.read('stage/'+str(index))[:2];capture=child.read('rollback/'+str(index))[:2]
    require(capture==({'state':'absent'},None),'handoff_restoration_record')
    if stage[0]!={'state':'absent'}:
     require(stage[0].get('mode')=='0644' and stage[1][6]==os.getuid(),'handoff_restoration_record')
    rows['stage/'+str(index)]=stage;rows['rollback/'+str(index)]=capture
   require(public.inventory(HANDOFF_POLICY['order'])=={p:(r[0],tuple(r[1])) for p,r in before.items()},
           'handoff_restoration_target')
   return {'status':'restored','before':before,'restored':before,'records':rows,'restore_records':{},
           'directories':[[name,expected] for _,name,_,expected in child.links]}
  if rows['snapshot/manifest.json'][0]!={'state':'absent'}:
   raw=rows['snapshot/manifest.json'][2];snapshot=json.loads(raw)
   require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_refactor','before','guarded','ancestors'}
           and snapshot['source']==HANDOFF_POLICY['source'] and snapshot['manifest']==HANDOFF_POLICY['manifest']
           and snapshot['parent_refactor']==REFACTOR_ORIGINAL and snapshot['before']==before
           and raw==canonical(snapshot) and digest(raw)==install['snapshot_manifest_sha256'],'handoff_restoration_record')
  else:require(recovery and not written and install['snapshot_manifest_sha256'] is None,'handoff_restoration_record')
  if recovery:
   handoff_absent(HANDOFF_ROLLBACK_OPERATION);restore=child;restore_rows={};capture_prefix='rollback/'
  else:
   restore=Files(home+'/.anytoour-search3-root-gateway/'+HANDOFF_ROLLBACK_OPERATION);stores.append(restore);private_root(restore)
   restore_rows=site_read_records(restore,('reservation.json','result.json'));capture_prefix='quarantine/'
   restore_identity={k:terminal[k] for k in ('control_source_sha','run_id','command_comment_id')}
   require(restore_rows['reservation.json'][2]==canonical({'operation':HANDOFF_ROLLBACK_OPERATION,**restore_identity,
           'original_handoff':identity,'source':HANDOFF_POLICY['source'],'manifest':HANDOFF_POLICY['manifest'],
           'parent_refactor':REFACTOR_ORIGINAL}) and restore_rows['result.json'][2]==canonical(terminal),
           'handoff_restoration_record')
  for index,path in enumerate(HANDOFF_POLICY['order']):
   target=HANDOFF_POLICY['targets'][path];intent_row=rows['intent-'+str(index)]
   intent=None
   if intent_row[0]!={'state':'absent'}:
    intent=json.loads(intent_row[2])
    require(isinstance(intent,dict) and set(intent)=={'path','before','after','previous_identity','new_identity'}
            and intent['path']==path and intent['before']==target['before'] and intent['after']==target['after']
            and intent['previous_identity']==before[path][1] and intent_row[2]==canonical(intent),'handoff_restoration_record')
    new=checked_stamp(intent['new_identity'],target['after'])
   for prefix in ('snapshot/','drill/'):
    if rows[prefix+str(index)][0]!={'state':'absent'}:
     require(rows[prefix+str(index)][0]==target['before'],'handoff_restoration_record')
   stage=child.read('stage/'+str(index))[:2];capture=restore.read(capture_prefix+str(index))[:2]
   rows['stage/'+str(index)]=stage;restore_rows[capture_prefix+str(index)]=capture
   if path in written:
    require(intent is not None and stage==({'state':'absent'},None) and capture[0]==target['after']
            and capture[1][:-1]==new[:-1],'handoff_restoration_record')
   else:
    require(recovery and capture==({'state':'absent'},None),'handoff_restoration_record')
    if stage[0]!={'state':'absent'}:
     require(intent is not None and stage[0]==target['after'] and stage[1][:-1]==new[:-1],'handoff_restoration_record')
    require(terminal['restored_guard'][path]==before[path],'handoff_restoration_target')
  projected=handoff_projection(saved,before,terminal['restored_guard'])
  require(public.inventory(HANDOFF_POLICY['order'])=={p:(projected[p][0],tuple(projected[p][1])) for p in HANDOFF_POLICY['order']},
          'handoff_restoration_target')
  return {'status':'restored','before':before,'restored':terminal['restored_guard'],'records':rows,'restore_records':restore_rows,
          'directories':[[name,expected] for store in stores for _,name,_,expected in store.links]}
 finally:
  for store in reversed(stores):store.close()
def handoff_restoration_stable():
 if _handoff_restoration_bound is not None:
  require(handoff_restoration_read(_handoff_restoration_saved)==_handoff_restoration_bound,'handoff_restoration_changed')
def refactor_guard_from_snapshot(saved):
 global _handoff_restoration_bound,_handoff_restoration_saved
 observed=handoff_restoration_read(saved)
 if _handoff_restoration_bound is None:
  _handoff_restoration_bound=observed;_handoff_restoration_saved=saved
 else:require(observed==_handoff_restoration_bound,'handoff_restoration_changed')
 projected=handoff_projection(saved,observed['before'],observed['restored']) if observed['status']=='restored' else saved
 result=_handoff_original_guard(projected);handoff_restoration_stable();return result
def refactor_check_ancestors():
 handoff_restoration_stable();_handoff_original_check();handoff_restoration_stable()
'''


def handoff_restoration_prefix(value):
    if value is None:return ''
    handoff_restoration_value(value)
    return ('\nHANDOFF_POLICY='+repr(handoff_policy())+'\nHANDOFF_ROLLBACK_OPERATION='+repr(HANDOFF_ROLLBACK_OPERATION)
            +'\nHANDOFF_RESTORATION_PROOF='+repr(value)+'\n'
            +HANDOFF_COMMON_REMOTE+HANDOFF_RESTORATION_REMOTE)


def handoff_event_identity(event,env,rollback=False):
    need(type(rollback) is bool,'mode')
    prefix,operation=(HANDOFF_ROLLBACK_PREFIX,HANDOFF_ROLLBACK_OPERATION) if rollback else (HANDOFF_PREFIX,HANDOFF_OPERATION)
    control=env.get('GITHUB_SHA','');body=event.get('comment',{}).get('body','');original_run=None
    if rollback:
        match=re.fullmatch(re.escape(prefix+' '+control+' ')+r'([1-9][0-9]{0,19}) '+re.escape(operation),body)
        need(match is not None,'command');original_run=match[1]
    else:need(body==f'{prefix} {control} {operation}','command')
    synthetic=json.loads(json.dumps(event));synthetic['comment']['body']=f'{UPDATE_PREFIX} {control} {UPDATE_OPERATION}'
    identity=update_event_identity(synthetic,env)
    excluded=(HANDOFF_PARENT['run_id'],REFACTOR_PARENT['run_id'],SITE_PARENT['run_id'],UPDATE_PARENT['run_id'],
              CONTINUATION_PARENT['run_id'],SITE_INSPECT_FAILED['run_id'],SITE_CONTINUATION_INSPECTION['run_id'],
              SITE_CONTINUATION_COLLECTOR['run_id'])
    need(identity['run_id'] not in excluded and original_run not in excluded,'handoff_runtime_identity')
    if rollback:need(original_run!=identity['run_id'],'command')
    return (identity,original_run) if rollback else identity


def handoff_final_heads(identity):
    rollback_main(identity)
    need(all(isinstance(v,str) and re.fullmatch('[a-f0-9]{40}',v) for v in
             (HANDOFF_SOURCE_MERGE,HANDOFF_SOURCE_MERGE_TREE,HANDOFF_SOURCE_BASE)),'handoff_source_merge')
    pr=api('/pulls/'+str(HANDOFF_SOURCE_PR))
    need(pr.get('state')=='closed' and pr.get('merged') is True and pr.get('merge_commit_sha')==HANDOFF_SOURCE_MERGE
         and pr.get('head',{}).get('sha')==HANDOFF_SOURCE and pr.get('head',{}).get('repo',{}).get('full_name')==REPO
         and pr.get('base',{}).get('ref')=='main' and pr.get('base',{}).get('repo',{}).get('full_name')==REPO,
         'handoff_candidate_moved')
    need(api('/git/commits/'+HANDOFF_SOURCE).get('tree',{}).get('sha')==HANDOFF_TREE,'handoff_source_tree')
    merged=api('/git/commits/'+HANDOFF_SOURCE_MERGE)
    need(merged.get('tree',{}).get('sha')==HANDOFF_SOURCE_MERGE_TREE
         and [p.get('sha') for p in merged.get('parents',[])]==[HANDOFF_SOURCE_BASE,HANDOFF_SOURCE],
         'handoff_source_merge')
    relation=api('/compare/'+HANDOFF_SOURCE_MERGE+'...'+identity['control_source_sha'])
    need(relation.get('status') in ('identical','ahead') and relation.get('behind_by')==0
         and relation.get('merge_base_commit',{}).get('sha')==HANDOFF_SOURCE_MERGE,'handoff_source_ancestry')
    for commit in (HANDOFF_SOURCE,HANDOFF_SOURCE_MERGE,identity['control_source_sha']):
        for path in HANDOFF_ORDER:
            row=api('/contents/v2/'+path+'?ref='+commit)
            need(row.get('type')=='file' and row.get('sha')==HANDOFF_TARGETS[path]['blob'],'handoff_source_blob')


def handoff_parent_admission():
    pin=HANDOFF_PARENT;run=api('/actions/runs/'+pin['run_id'])
    need(run.get('id')==int(pin['run_id']) and run.get('head_sha')==pin['control_source_sha']
         and run.get('head_branch')=='main' and run.get('path')==WORKFLOW and run.get('event')=='issue_comment'
         and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')=='success'
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff','handoff_parent_run')
    jobs=[j for j in pages('/actions/runs/'+pin['run_id']+'/attempts/1/jobs','jobs') if j.get('name')=='public-install']
    need(len(jobs)==1 and jobs[0].get('id')==pin['job_id'] and jobs[0].get('run_id')==int(pin['run_id'])
         and jobs[0].get('status')=='completed' and jobs[0].get('conclusion')=='success','handoff_parent_job')
    steps=[s for s in jobs[0].get('steps',[]) if s.get('name','').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps)==1 and steps[0].get('name')=='Run python3 scripts/deploy/search3_public_install.py refactor'
         and steps[0].get('status')=='completed' and steps[0].get('conclusion')=='success','handoff_parent_step')
    artifact=api('/actions/artifacts/'+str(pin['artifact_id']))
    need(artifact.get('id')==pin['artifact_id'] and artifact.get('name')=='search3-public-install-'+pin['run_id']+'-1'
         and artifact.get('expired') is False and artifact.get('workflow_run',{}).get('id')==int(pin['run_id'])
         and artifact.get('digest')=='sha256:'+pin['artifact_sha256'],'handoff_parent_artifact')
    comments=pages('/issues/4709/comments');_unique_update_command(comments,REFACTOR_PREFIX,REFACTOR_OPERATION,handoff_parent_identity())
    terminal=next((c for c in comments if c.get('id')==pin['terminal_comment_id']),{})
    tokens=[str(pin[k]) for k in ('run_id','artifact_id','artifact_sha256','receipt_sha256','snapshot_manifest_sha256')]
    need(terminal.get('user',{}).get('id')==226193297 and terminal.get('author_association')=='OWNER'
         and all(t in terminal.get('body','') for t in tokens+['refactor_updated_verified']),'handoff_parent_terminal')


def handoff_admission(identity):
    handoff_final_heads(identity);reviewed_control(identity['control_source_sha'])
    gate=api('/contents/.github/workflows/deploy-anytoour.yml?ref='+identity['control_source_sha'])
    need(gate.get('type')=='file' and gate.get('encoding')=='base64'
         and sha(base64.b64decode(gate['content']))==SITE_PUBLISHER_GUARD_SHA,'publisher_guard')
    checks=pages('/commits/'+HANDOFF_SOURCE+'/check-runs','check_runs')
    for name in (*HANDOFF_CHECKS,'production-evidence'):
        latest=max((c for c in checks if c.get('name')==name and c.get('head_sha')==HANDOFF_SOURCE),key=lambda c:c['id'],default={})
        need(latest.get('status')=='completed' and latest.get('app',{}).get('slug')=='github-actions'
             and latest.get('conclusion')==('skipped' if name=='production-evidence' else 'success'),'handoff_source_ci')
    handoff_parent_admission()
    _unique_update_command(pages('/issues/4709/comments'),HANDOFF_PREFIX,HANDOFF_OPERATION,identity)
    handoff_final_heads(identity)


def _handoff_commands():
    comments=pages('/issues/4709/comments');selected={}
    for prefix,operation in ((HANDOFF_PREFIX,HANDOFF_OPERATION),(HANDOFF_ROLLBACK_PREFIX,HANDOFF_ROLLBACK_OPERATION)):
        rows=[c for c in comments if c.get('body','').strip().split()[:1]==[prefix]
              and operation in c.get('body','').split()[1:]]
        need(len(rows)<=1,'handoff_commands')
        if not rows:continue
        row=rows[0];rollback=prefix==HANDOFF_ROLLBACK_PREFIX
        pattern=re.escape(prefix)+r' ([a-f0-9]{40}) '+(r'([1-9][0-9]{0,19}) ' if rollback else '')+re.escape(operation)
        match=re.fullmatch(pattern,row.get('body',''))
        need(match is not None and row.get('user',{}).get('id')==226193297 and row.get('author_association')=='OWNER'
             and row.get('created_at')==row.get('updated_at'),'handoff_command')
        _tv_time(row.get('created_at'));selected['rollback' if rollback else 'install']=row
    need('rollback' not in selected or 'install' in selected,'handoff_commands')
    return selected


def handoff_rollback_commands(identity,original_run,expected_original=None):
    need(isinstance(original_run,str) and re.fullmatch('[1-9][0-9]{0,19}',original_run)
         and original_run not in (identity['run_id'],HANDOFF_PARENT['run_id'],REFACTOR_PARENT['run_id'],SITE_PARENT['run_id'],
                                  UPDATE_PARENT['run_id'],CONTINUATION_PARENT['run_id']),'handoff_rollback_run')
    comments=pages('/issues/4709/comments')
    _unique_update_command(comments,HANDOFF_ROLLBACK_PREFIX,HANDOFF_ROLLBACK_OPERATION,identity,original_run)
    commands=_handoff_commands();need('install' in commands,'handoff_rollback_command')
    installed=commands['install'];control=installed['body'].split()[1]
    original={'control_source_sha':control,'run_id':original_run,'command_comment_id':installed['id']}
    update_runtime_identity(original)
    need(expected_original is None or original==expected_original,'handoff_rollback_command')
    return original


def _handoff_native_proof(command,run,value,artifact,*,rollback=False,original=None,accepted=None):
    created=_tv_time(command['created_at']);tokens=command['body'].split();control=tokens[1]
    identity={'control_source_sha':control,'run_id':str(run.get('id')),'command_comment_id':command['id']}
    update_runtime_identity(identity)
    if rollback:
        need(tokens[2]==original['run_id'],'handoff_native_command')
        _handoff_source_value(value,identity,rollback_original=original,accepted=accepted);conclusion='success'
    elif value.get('status')=='blocked_no_replay':
        _handoff_zero_write_value(value,identity);conclusion='failure'
    else:
        recovery=value.get('status')=='rolled_back_no_replay'
        _handoff_source_value(value,identity,recovery=recovery);conclusion='failure' if recovery else 'success'
    need(run.get('head_sha')==control and run.get('head_branch')=='main' and run.get('path')==WORKFLOW
         and run.get('event')=='issue_comment' and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')==conclusion
         and created<=_tv_time(run.get('created_at'))<=created+300
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff','handoff_native_run')
    # Restoration introduces a new full-stamp authority. Bind the controller
    # actually executed by this historical run to its reviewed tree and CI;
    # a later controller revision is neither a substitute nor a blocker.
    reviewed_control(control)
    jobs=[j for j in pages('/actions/runs/'+identity['run_id']+'/attempts/1/jobs','jobs') if j.get('name')=='public-install']
    need(len(jobs)==1 and jobs[0].get('run_id')==run['id'] and jobs[0].get('status')=='completed'
         and jobs[0].get('conclusion')==conclusion,'handoff_native_job')
    steps=[s for s in jobs[0].get('steps',[]) if s.get('name','').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    mode='handoff-rollback' if rollback else 'handoff'
    need(len(steps)==1 and steps[0].get('name')=='Run python3 scripts/deploy/search3_public_install.py '+mode
         and steps[0].get('status')=='completed' and steps[0].get('conclusion')==conclusion,'handoff_native_step')
    need(created<=_tv_time(run.get('created_at'))<=_tv_time(run.get('run_started_at'))
         <=_tv_time(steps[0].get('started_at'))<=_tv_time(steps[0].get('completed_at'))<=_tv_time(run.get('updated_at')),
         'handoff_native_time')
    need(artifact.get('name')=='search3-public-install-'+identity['run_id']+'-1' and artifact.get('expired') is False
         and artifact.get('workflow_run',{}).get('id')==run['id']
         and re.fullmatch(r'sha256:[a-f0-9]{64}',artifact.get('digest','')),'handoff_native_artifact')
    return identity,value


def _handoff_command_evidence(command,*,rollback=False,original=None,accepted=None):
    created=_tv_time(command['created_at'])
    end=datetime.datetime.fromtimestamp(created+300,datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
    query=urllib.parse.quote(command['created_at']+'..'+end,safe='')
    runs=pages('/actions/workflows/search3-root-gateway-install.yml/runs?event=issue_comment&branch=main&created='+query,'workflow_runs')
    need(len(runs)<=100,'handoff_native_run_limit');matches=[]
    for run in runs:
        if run.get('head_sha')!=command['body'].split()[1]:continue
        for artifact in pages('/actions/runs/'+str(run['id'])+'/artifacts','artifacts'):
            if artifact.get('name')!='search3-public-install-'+str(run['id'])+'-1':continue
            digest=artifact.get('digest','')
            if artifact.get('expired') is not False or not re.fullmatch(r'sha256:[a-f0-9]{64}',digest):continue
            with zip_files(download(artifact['id'],digest.removeprefix('sha256:'))) as archive:
                need(archive.namelist()==['evidence.json'],'handoff_native_artifact')
                raw=archive.read('evidence.json');need(len(raw)<=262144,'handoff_native_artifact')
                value=_tv_json(raw)
            if not isinstance(value,dict) or value.get('command_comment_id')!=command['id']:continue
            matches.append(_handoff_native_proof(command,run,value,artifact,rollback=rollback,original=original,accepted=accepted))
    need(len(matches)==1,'handoff_original_missing')
    return matches[0]


def handoff_restoration_evidence():
    commands=_handoff_commands()
    if not commands:return {'status':'absent'}
    original,installed=_handoff_command_evidence(commands['install'])
    if installed['status']=='blocked_no_replay':
        need('rollback' not in commands,'handoff_restoration_pending')
        value={'status':'zero_write','original':original,'install_evidence':installed}
    elif installed['status']=='rolled_back_no_replay':
        need('rollback' not in commands,'handoff_restoration_pending')
        value={'status':'restored','original':original,'install_evidence':installed,'restoration_evidence':installed}
    else:
        need('rollback' in commands,'handoff_restoration_pending')
        _,restored=_handoff_command_evidence(commands['rollback'],rollback=True,original=original,accepted=installed['remote'])
        value={'status':'restored','original':original,'install_evidence':installed,'restoration_evidence':restored}
    handoff_restoration_value(value);need(_handoff_commands()==commands,'handoff_commands_changed')
    return value


def handoff_rollback_admission(identity,original_run):
    rollback_main(identity);reviewed_control(identity['control_source_sha'])
    original=handoff_rollback_commands(identity,original_run);run=api('/actions/runs/'+original_run)
    artifacts=[a for a in pages('/actions/runs/'+original_run+'/artifacts','artifacts')
               if a.get('name')=='search3-public-install-'+original_run+'-1']
    need(len(artifacts)==1 and artifacts[0].get('expired') is False
         and re.fullmatch(r'sha256:[a-f0-9]{64}',artifacts[0].get('digest','')),'handoff_rollback_artifact')
    artifact=artifacts[0]
    with zip_files(download(artifact['id'],artifact['digest'].removeprefix('sha256:'))) as archive:
        need(archive.namelist()==['evidence.json'],'handoff_rollback_artifact');value=_tv_json(archive.read('evidence.json'))
    command=_handoff_commands()['install'];checked,_=_handoff_native_proof(command,run,value,artifact)
    need(checked==original and value['status']=='country_handoff_updated_verified','handoff_rollback_evidence')
    rollback_main(identity);return original,artifact


def handoff(rollback=False):
    need(type(rollback) is bool,'mode')
    operation=HANDOFF_ROLLBACK_OPERATION if rollback else HANDOFF_OPERATION
    work=Path(os.environ['RUNNER_TEMP'])/'search3-public-install';work.mkdir(mode=0o700)
    key,known=work/'key',work/'known'
    evidence={'status':'blocked_no_replay','operation_id':operation,'source_sha':HANDOFF_SOURCE,
              'manifest_sha256':handoff_policy()['manifest'],'read_only':False,'phase':'identity','ssh_started':False}
    complete=False
    try:
        event=json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
        if rollback:
            identity,original_run=handoff_event_identity(event,os.environ,True)
            evidence.update(identity,original_handoff_run_id=original_run,phase='admission')
            original,artifact=handoff_rollback_admission(identity,original_run);evidence['phase']='artifact'
            policy,accepted,parent_value=handoff_source_evidence(download(artifact['id'],artifact['digest'].removeprefix('sha256:')),original)
            dependency=tv_successor_evidence()
            restoration=home_restoration_evidence()
            program=handoff_rollback_remote_program(policy,identity,original,accepted,parent_value,tv_successor=dependency,
                                                    home_restoration=restoration)
            evidence.update(parent_refactor=handoff_parent_identity(),parent_evidence=parent_value,original_handoff=original,
                            home_restoration_evidence=restoration)
        else:
            identity=handoff_event_identity(event,os.environ);evidence.update(identity,phase='admission');handoff_admission(identity)
            evidence['phase']='artifact'
            parent_value=handoff_parent_evidence(download(HANDOFF_PARENT['artifact_id'],HANDOFF_PARENT['artifact_sha256']))
            policy=handoff_policy();files=handoff_package()
            dependency=tv_successor_evidence()
            program=handoff_remote_program(policy,files,identity,parent_value,tv_successor=dependency)
            evidence.update(parent_refactor=handoff_parent_identity(),parent_evidence=parent_value)
        # Exact existing carrier, key handling, options, output bound and timeout.
        host,user=os.environ['PREVIEW_HOST'],os.environ['PREVIEW_USER']
        need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]{0,252}',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
        raw=os.environ['PREVIEW_KEY'].replace('\r','');need(0<len(raw)<=32768,'ssh_key')
        if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
        with key.open('x') as stream:stream.write(raw.rstrip()+'\n')
        key.chmod(0o600)
        scan=carrier.run(['ssh-keyscan','-T','15','-t','ed25519',host]);need(0<len(scan)<=8192,'host_key')
        known.write_bytes(scan);known.chmod(0o600)
        options=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','ConnectTimeout=15']
        if rollback:
            checked,latest_artifact=handoff_rollback_admission(identity,original_run)
            need(checked==original and latest_artifact==artifact,'handoff_rollback_changed')
        else:handoff_admission(identity)
        need(tv_successor_evidence()==dependency,'tv_successor_changed')
        if rollback:need(home_restoration_evidence()==restoration,'home_restoration_changed')
        evidence.update(status='unknown_no_replay',phase='remote',ssh_started=True)
        response=carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'],input=program.encode(),timeout=180)
        need(len(response)<=32768,'receipt_limit');result=json.loads(response)
        complete=valid_handoff_result(identity,result,original,accepted) if rollback else valid_handoff_result(identity,result)
        evidence['remote']=result
        if rollback:rollback_main(identity)
        else:handoff_final_heads(identity)
        evidence.update(status=result['status'],phase='complete')
    except Exception as error:
        complete=False
        evidence['error_type']=type(error).__name__ if type(error) in (RuntimeError,ValueError,KeyError,OSError,subprocess.CalledProcessError,subprocess.TimeoutExpired) else 'HandoffError'
    finally:
        key.unlink(missing_ok=True);known.unlink(missing_ok=True)
        (work/'evidence.json').write_text(json.dumps(evidence,sort_keys=True)+'\n')
    print(json.dumps(evidence,sort_keys=True));need(complete,'handoff_rollback_not_verified' if rollback else 'handoff_not_verified')


HOME_PREFIX = '/update-site-home-navigation'
HOME_OPERATION = 'site-home-navigation-20261121-v1'
HOME_ROLLBACK_PREFIX = '/rollback-site-home-navigation'
HOME_ROLLBACK_OPERATION = 'site-home-navigation-rollback-20261121-v1'
# Draft admission is deliberately closed until the approved SOURCE and its exact
# modern LIVE artifact have frozen. No caller supplies paths, sources or hashes.
HOME_SOURCE_PR = 5157
HOME_SOURCE = 'be5d04ea6580a51e046eeec5d0a2da3b846725c2'
HOME_TREE = '2088eb9a22c1a7c16e6626ba6c71722cea6fe0d9'
HOME_SOURCE_MERGE = 'ee3e7c9bfc70e46db59720ba06e58946c7349a9b'
HOME_SOURCE_MERGE_TREE = '28324059d18ccc057227c504e38a13ec3871ae93'
HOME_RELEASE_BASE = '44515801b01f536e571fc3444276e1dd4bbdcd0b'
HOME_MAIN_BASE = 'f6b7513445f599f332c5d9d4021b2537e2d3d609'
HOME_ARTIFACT = 14752234533
HOME_ARTIFACT_SHA = '3d8afde87847b83da4d3a01e0b6a9edc4474df3a5ee9a723aef02e06095e11c7'
HOME_MANIFEST_SHA = 'bcb3d8ec1b181c29e86b6964b410e8eb0ab74afabf9e9492482f9e344e71bb46'
HOME_BUILD_RUN = 45019050793
HOME_SOURCE_BRANCH = 'feat/site234-home-search3-form-20261122'
HOME_CHECKS = ('browser-matrix','build-preview-artifact','content-ux','focused-presentation','guard',
               'integration-contracts','lead-source','navigation-contract','preview-boundary','visual-migration')
HOME_APPROVAL_COMMENT = 7065453587
HOME_PARENT = {
    'control_source_sha':HOME_MAIN_BASE,'run_id':'44919131549','command_comment_id':7064864145,
    'job_id':136891096904,'artifact_id':14712672447,
    'artifact_sha256':'eff55d658065fc0722e6eb443528e042c18402f54dd401c4be4c06fae5b9da0b',
    'receipt_sha256':'901477edeee5ceb162559520b0f73b7509e26709b8a49fba184dde8c621ba523',
    'policy_sha256':'d2f80312b7dfb70261641b1ac5f5ca779d804d1dfcffb439d7c92c029efbc271',
    'snapshot_manifest_sha256':'5a5158c8d409ade5477c528105cf2750a238a55e356ff9ea758663586079ea85',
    'terminal_comment_id':7064974626,
}
HOME_SHARED_ORDER = ('site-path-v1.php','home-hero-v1.jpg','home-v1.css','site-header-v2.css',
                     'home-v1.php','site-header-v2.php')
HOME_SEARCH_ORDER = ('visual-search/styles.css',
 'visual-search/mobile-controls-v1.css',
 'visual-search/app.js',
 'visual-search/index.html',
 'visual-search/entry-v1.php',
 'visual-search/public-config-v1.js',
 'visual-search/public-bootstrap-v1.js',
 'search3-canonical-profiles-v1.js',
 'prototype-search/data.js')
HOME_ORDER = HOME_SHARED_ORDER + HOME_SEARCH_ORDER + ('index.php',)
HOME_ENTRY = {'source_ref':HOME_MAIN_BASE,'source_path':'v2/home-entry-v1.php',
              'blob':'2e066c7fb83832d7115e698d2079e5661f45e413',
              'before':{'state':'present','mode':'0644','size':12305,
                        'sha256':'80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f'},
              'after':{'state':'present','mode':'0644','size':433,
                       'sha256':'bef590b35ec45d2ac21a5c41c1d6d49bd36da25798e720aa2d23dca152d3688c'}}
HOME_TARGETS = {'site-path-v1.php': {'before': {'state': 'absent'},
                      'after': {'state': 'present',
                                'size': 2205,
                                'mode': '0644',
                                'sha256': '031f3ff136c0adcbcd4895a2e29e1997200f437280e0e206e6950e353c225101'},
                      'source_path': 'v2/site-path-v1.php',
                      'source': {'mode': '100644',
                                 'blob_sha': 'f472f245da352a0c69749869c55809f903432e9b',
                                 'size': 2205,
                                 'sha256': '031f3ff136c0adcbcd4895a2e29e1997200f437280e0e206e6950e353c225101'}},
 'home-hero-v1.jpg': {'before': {'state': 'absent'},
                      'after': {'state': 'present',
                                'size': 109085,
                                'mode': '0644',
                                'sha256': '1968129469a1f348df4a7246f4d45cf9d4404e8e30f27806fda95b69a80ed82e'},
                      'source_path': 'v2/home-hero-v1.jpg',
                      'source': {'mode': '100644',
                                 'blob_sha': '5bf213f51829c89cb884db031477a016a0990006',
                                 'size': 109085,
                                 'sha256': '1968129469a1f348df4a7246f4d45cf9d4404e8e30f27806fda95b69a80ed82e'}},
 'home-v1.css': {'before': {'state': 'present',
            'mode': '0644',
            'size': 9914,
            'sha256': '844e106d1ba42f92bf5bce807cee88e53247e8acd807c76cc67fa11293beb08e'},
 'after': {'state': 'present',
           'size': 9529,
           'mode': '0644',
           'sha256': '76ad044d1ec5aa0e3121314986bbab504c1de642220a77ec2b2f6484a3e8bbb5'},
 'source_path': 'v2/home-v1.css',
 'source': {'mode': '100644',
            'blob_sha': 'ba26b284aa9c09c30ee41437283f44615fcfbbf5',
            'size': 9529,
            'sha256': '76ad044d1ec5aa0e3121314986bbab504c1de642220a77ec2b2f6484a3e8bbb5'}},
 'site-header-v2.css': {'before': {'state': 'present',
                                   'mode': '0644',
                                   'size': 5198,
                                   'sha256': '87654b6903e148306ff27ddf9453d608f8401089240192269cefe79c2607d777'},
                        'after': {'state': 'present',
                                  'size': 4683,
                                  'mode': '0644',
                                  'sha256': '63ce25e14ec66ed24dba597e6ca94d0821f51889eaa22b778dfd791bce6e07ba'},
                        'source_path': 'v2/site-header-v2.css',
                        'source': {'mode': '100644',
                                   'blob_sha': 'f5b9998fc1eae3133167b47698909af84f9cb457',
                                   'size': 4802,
                                   'sha256': 'f0009423c54e0077a4aa5209c34482761175bbd93c96d9fd1418e3c9bb1d683e'}},
 'home-v1.php': {'before': {'state': 'present',
            'mode': '0644',
            'size': 10663,
            'sha256': '081389eb4980e41a5801943a752ccbc16fb1326682a199c0e29f31525d4f270d'},
 'after': {'state': 'present',
           'size': 8695,
           'mode': '0644',
           'sha256': '86c634204db376421188d33cf26c7cca7283dacc9c0584cb2e1f25118178dad2'},
 'source_path': 'v2/home-v1.php',
 'source': {'mode': '100644',
            'blob_sha': '060e273199b858d96d9913a014d4984569c6df79',
            'size': 8695,
            'sha256': '86c634204db376421188d33cf26c7cca7283dacc9c0584cb2e1f25118178dad2'}},
 'site-header-v2.php': {'before': {'state': 'present',
                                   'mode': '0644',
                                   'size': 3472,
                                   'sha256': '682a3fb907c8e6ac350a42822ba943097f4e42a277e6c489195724ea50619dd8'},
                        'after': {'state': 'present',
                                  'size': 4326,
                                  'mode': '0644',
                                  'sha256': 'a31026a8fb723ac71df72e282dc8e7b716c488aeecb4b8d97fc430f9b117fcd3'},
                        'source_path': 'v2/site-header-v2.php',
                        'source': {'mode': '100644',
                                   'blob_sha': '18527d0bb9cea79425d478f038036417d4ddf47b',
                                   'size': 4326,
                                   'sha256': 'a31026a8fb723ac71df72e282dc8e7b716c488aeecb4b8d97fc430f9b117fcd3'}},
 'visual-search/styles.css': {'before': {'mode': '0644',
                                         'sha256': 'f2f6585cc128d5ff122d8ea4486424de234f5561461f0aaca3bbe785f5444af3',
                                         'size': 270030,
                                         'state': 'present'},
                              'after': {'state': 'present',
                                        'size': 268471,
                                        'mode': '0644',
                                        'sha256': '8d137710f2e52c6666096b533c62e88d75e185e096067189e9235be06fe0cfea'},
                              'source_path': 'v2/visual-search/styles.css',
                              'source': {'mode': '100644',
                                         'blob_sha': '965edcea9eb4a5b750d405bd6d057ef8e88a627c',
                                         'size': 280924,
                                         'sha256': '459c7d84e6642d5f274526e236f9397ffdc9d085e0ecf499bb6fba375374b525'}},
 'visual-search/mobile-controls-v1.css': {'before': {'mode': '0644',
                                                     'sha256': '362df43e79697ae6ae2932758e5fe671b634fb2d8aa2d0a34d1bd6009e2b7784',
                                                     'size': 15665,
                                                     'state': 'present'},
                                          'after': {'state': 'present',
                                                    'size': 15615,
                                                    'mode': '0644',
                                                    'sha256': 'f3321da94bf770e243be441f302b81707bac5a5c5e3aebfd14f104e12128771a'},
                                          'source_path': 'v2/visual-search/mobile-controls-v1.css',
                                          'source': {'mode': '100644',
                                                     'blob_sha': '1ddd76b5cbbe63ac4937e3886d92d4a06d22c11b',
                                                     'size': 18888,
                                                     'sha256': 'de1dad08ac1f22ede59e84c4df7b47bf7b3e95a86e90f5f8c95b190859e66781'}},
 'visual-search/app.js': {'before': {'state': 'present',
            'mode': '0644',
            'size': 310844,
            'sha256': 'efb3870d23c3e0d3c3fbb53fff4967a6ee3d901bebdf5502b7d78c6e2d2bbe92'},
 'after': {'state': 'present',
           'size': 312104,
           'mode': '0644',
           'sha256': 'e1c5735e41affcac3cf2853bacb110e4a0ce7924118801bc1d542ef20d623fb7'},
 'source_path': 'v2/visual-search/app.js',
 'source': {'mode': '100644',
            'blob_sha': 'cd74f089663bef114c5cbc396b793b3a7813c830',
            'size': 408874,
            'sha256': 'bbce130c7be0c982fdcc092ed3af28050ec391877bff990bb6168133e0ff0efc'}},
 'visual-search/index.html': {'before': {'mode': '0644',
                                         'sha256': '8ff5bf5fc2d4340b2e7e98c7662dc581cb33fafe31cb152f3cd2e36fbcfe1e5f',
                                         'size': 15715,
                                         'state': 'present'},
                              'after': {'state': 'present',
                                        'size': 15293,
                                        'mode': '0644',
                                        'sha256': '96091fb0fed9e6c097fb4f91855e840249b91461e7a4eb791b7efed6111cc996'},
                              'source_path': 'v2/visual-search/index.html',
                              'source': {'mode': '100644',
                                         'blob_sha': 'ad32640d9c068e5c36e690d775642c775d8f2e9b',
                                         'size': 15293,
                                         'sha256': '96091fb0fed9e6c097fb4f91855e840249b91461e7a4eb791b7efed6111cc996'}},
 'visual-search/entry-v1.php': {'before': {'mode': '0644',
            'sha256': '48898849b5ebdb9c2961087e7ad6c4dbd9ec0546675eaa9240870f4d59b03ac9',
            'size': 10376,
            'state': 'present'},
 'after': {'state': 'present',
           'size': 13704,
           'mode': '0644',
           'sha256': 'a5f685876b0b7b66dc89732984f4bcebf0a13d1a1a2e6fbb34a024a0922e0fa8'},
 'source_path': 'v2/visual-search/entry-v1.php',
 'source': {'mode': '100644',
            'blob_sha': '002eb613189ae799cdf202171f283c50be4c8ff9',
            'size': 13704,
            'sha256': 'a5f685876b0b7b66dc89732984f4bcebf0a13d1a1a2e6fbb34a024a0922e0fa8'}},
 'visual-search/public-config-v1.js': {'before': {'mode': '0644',
            'sha256': '5753a21bbee45bfd925a7e4b5815b43f143f5c4b37ae02c7cef416e3808fdc3e',
            'size': 1678,
            'state': 'present'},
 'after': {'state': 'present',
           'size': 1848,
           'mode': '0644',
           'sha256': '28f2762809add82b16f79f564634d686c63a6e10dd9970b82c53e6732a11f922'},
 'source_path': 'v2/visual-search/public-config-v1.js',
 'source': {'mode': '100644',
            'blob_sha': '24ac0fe0b7e0bd371d9b6e60f276b3d8c4e41609',
            'size': 2423,
            'sha256': '3495f1ed45d1472c178ef06aa575ccc2484c45d93658b463daf2d641441496f2'}},
 'visual-search/public-bootstrap-v1.js': {'before': {'mode': '0644',
            'sha256': 'eb9c9c0b1545a5ac6d7ecd931c762d21407574370cadb60a95b35800ba3429b4',
            'size': 2277,
            'state': 'present'},
 'after': {'state': 'present',
           'size': 2399,
           'mode': '0644',
           'sha256': '9772352181baa938da93d5462859bddba05dd08983a9a4a39f7e7222b02c478d'},
 'source_path': 'v2/visual-search/public-bootstrap-v1.js',
 'source': {'mode': '100644',
            'blob_sha': 'af4b0ee0409037428608cec2de0b37bb7e1745e9',
            'size': 3391,
            'sha256': 'fec0de624189de029e1c3c5a1311f8ef99734ff070e7d5167d1117512a46e922'}},
 'search3-canonical-profiles-v1.js': {'before': {'mode': '0644',
            'sha256': '73006ce55ff61d4ea01338d92e80518d545eb741183b3ba9d29bcf355ce7e3ef',
            'size': 11572,
            'state': 'present'},
 'after': {'state': 'present',
           'size': 11773,
           'mode': '0644',
           'sha256': '3e1cc4b9cd985bad0bec86e7ed1c85ebbd57f511870ead69161dc1fcf30dc915'},
 'source_path': 'v2/search3-canonical-profiles-v1.js',
 'source': {'mode': '100644',
            'blob_sha': '8e8fe70612e9210d7f42ea5af1b2165aaf3cd0ca',
            'size': 17256,
            'sha256': '00ea5b9fe8775fa1df24a2d7022dd66cd5851e4d6813251849aa88fbeec601ed'}},
 'prototype-search/data.js': {'before': {'mode': '0644',
            'sha256': '5752c94377e5f506788bcda293fcfa072434ebc915c5d5e3e28a29d5a69f0e80',
            'size': 134455,
            'state': 'present'},
 'after': {'state': 'present',
           'size': 134732,
           'mode': '0644',
           'sha256': 'd52436403c6a6adaf5bf05307acd151bb56081b46d10c97ca5269c0dc54149f7'},
 'source_path': 'v2/prototype-search/data.js',
 'source': {'mode': '100644',
            'blob_sha': '0666e444119cd8d3ba55890ab5e2e4991021e647',
            'size': 195720,
            'sha256': 'f3a70925b74e037ff237785e7d1fa3e87422818867d92891d4016c80309d637c'}}}
HOME_TARGETS['index.php'] = HOME_ENTRY

HOME_DEPENDENCIES = {'images/logo.svg':{'state':'present','mode':'0644','size':11649,
    'sha256':'3c033c7697dbb8879f6a867f659a7964efc180518f92655391c289b74ce2e049'}}


def home_parent_identity():
    return {k:HOME_PARENT[k] for k in ('control_source_sha','run_id','command_comment_id')}


def home_policy():
    value={'operation':HOME_OPERATION,'source':HOME_SOURCE,'tree':HOME_TREE,'manifest_source':HOME_MANIFEST_SHA,
           'order':HOME_ORDER,'targets':HOME_TARGETS,'dependencies':HOME_DEPENDENCIES,'parent':HOME_PARENT,
           'main_base':HOME_MAIN_BASE,'entry':HOME_ENTRY}
    return value|{'manifest':sha(json.dumps(value,sort_keys=True,separators=(',',':')).encode())}


def home_parent_value(value):
    raw=(json.dumps(value,sort_keys=True)+'\n').encode()
    need(sha(raw)==HOME_PARENT['receipt_sha256'],'home_parent_digest')
    packet=io.BytesIO()
    with zipfile.ZipFile(packet,'w') as archive:archive.writestr('evidence.json',raw)
    policy,accepted,previous=handoff_source_evidence(packet.getvalue(),home_parent_identity())
    need(policy['manifest']==HOME_PARENT['policy_sha256']
         and accepted['snapshot_manifest_sha256']==HOME_PARENT['snapshot_manifest_sha256'],'home_parent_receipt')
    return policy,accepted,previous


def home_parent_evidence(raw):
    need(sha(raw)==HOME_PARENT['artifact_sha256'],'home_parent_artifact')
    with zip_files(raw) as archive:
        need(archive.namelist()==['evidence.json'],'home_parent_artifact')
        data=archive.read('evidence.json');need(sha(data)==HOME_PARENT['receipt_sha256'],'home_parent_digest')
    value=json.loads(data);home_parent_value(value)
    return value


def home_overlay_predecessor(parent_value):
    # Project only the accepted overlays. The historical SITE/PUBLIC policies
    # retain their original maps and still validate their original receipts.
    _,_,refactor_value=home_parent_value(parent_value)
    _,_,site_value=handoff_parent_value(refactor_value)
    _,_,update_value=refactor_parent_value(site_value)
    update_policy_value,_,_=site_parent_value(update_value)
    overlay=dict(update_policy_value['previous']['expected'])
    overlay[UPDATE_PATH]=UPDATE_AFTER
    overlay.update({p:r['after'] for p,r in REFACTOR_TARGETS.items()})
    need(len(overlay)==42 and set(overlay)==set(ADDITIONS)|{ENTRY},'home_parent_scope')
    return overlay


HOME_COMMON_REMOTE = r'''
HOME_PREPARED_PATHS=('reservation.json','snapshot/manifest.json')+tuple(p+str(i) for i in range(len(HOME_POLICY['order'])) for p in ('snapshot/','drill/','intent-'))
HOME_EMPTY={'state':'present','mode':'0644','size':0,'sha256':digest(b'')}
_home_local_before=None
_home_local_restored=None
def home_ident(value):return list(value) if value is not None else None
def home_backup_row(path):
 before=HOME_POLICY['targets'][path]['before']
 return HOME_EMPTY if before=={'state':'absent'} else before
def home_before_identity(value,row):
 if row=={'state':'absent'}:
  require(value is None,'home_snapshot_conflict');return None
 return checked_stamp(value,row)
def home_same_original(current,prior):
 return current==prior if prior[1] is None else current[0]==prior[0] and current[1] is not None and current[1][:-1]==prior[1][:-1]
def home_projection(saved,before,restored):
 require(isinstance(before,dict) and isinstance(restored,dict)
         and set(before)==set(restored)==set(HOME_POLICY['order']),'home_restoration_scope')
 projected=dict(saved)
 for path in HOME_POLICY['order']:
  target=HOME_POLICY['targets'][path];old=before[path];current=restored[path]
  require(isinstance(old,list) and len(old)==2 and old[0]==target['before']
          and isinstance(current,list) and len(current)==2 and current[0]==old[0],'home_restoration_before')
  prior=home_before_identity(old[1],old[0]);now=home_before_identity(current[1],current[0])
  require((now is None if prior is None else now is not None and now[:-1]==prior[:-1]),'home_restoration_target')
  if path in saved:
   require(saved[path]==old,'home_restoration_before');projected[path]=current
 return projected
def home_ancestor_inventory():
 value=handoff_ancestor_inventory()
 value['handoff']=refactor_stamp_rows(site_read_records(handed,HANDOFF_PREPARED_PATHS+('result.json',)))
 value['handoff_stage']=refactor_stamp_rows({p:handed.read('stage/'+str(i))[:2] for i,p in enumerate(HANDOFF_POLICY['order'])})
 return value
def home_handoff_parent():
 site_continuation_namespace_absent();handoff_absent(REFACTOR_ROLLBACK_OPERATION);handoff_absent(HANDOFF_ROLLBACK_OPERATION)
 records=site_read_records(handed,HANDOFF_PREPARED_PATHS+('result.json',));record=lambda p:json.loads(records[p][2])
 require(record('reservation.json')=={'operation':HANDOFF_POLICY['operation'],**HANDOFF_ORIGINAL,
         'source':HANDOFF_POLICY['source'],'manifest':HANDOFF_POLICY['manifest'],'parent_refactor':REFACTOR_ORIGINAL}
         and record('result.json')==HANDOFF_ACCEPTED,'home_parent_identity')
 snapshot=record('snapshot/manifest.json');order=HANDOFF_POLICY['order'];targets=HANDOFF_POLICY['targets']
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_refactor','before','guarded','ancestors'}
         and snapshot['source']==HANDOFF_POLICY['source'] and snapshot['manifest']==HANDOFF_POLICY['manifest']
         and snapshot['parent_refactor']==REFACTOR_ORIGINAL and set(snapshot['before'])==set(order)
         and set(snapshot['guarded'])==((set(POLICY['expected'])|set(checked_protected_paths())
             |set(SITE_POLICY['dependencies'])|set(SITE_POLICY['optional_dependencies'])|set(SITE_POLICY['order']))-set(order))
         and digest(records['snapshot/manifest.json'][2])==HANDOFF_ACCEPTED['snapshot_manifest_sha256'],'home_parent_snapshot')
 saved=snapshot['guarded']
 if _home_local_restored is not None:saved=home_projection(saved,_home_local_before,_home_local_restored)
 previous=refactor_guard_from_snapshot(saved)
 require(handoff_ancestor_inventory()==snapshot['ancestors'],'home_parent_changed')
 current=public.inventory(order);stages={}
 for index,path in enumerate(order):
  before=snapshot['before'][path]
  require(isinstance(before,list) and len(before)==2 and before[0]==targets[path]['before'],'home_parent_snapshot')
  old=checked_stamp(before[1],targets[path]['before']);intent=record('intent-'+str(index))
  require(isinstance(intent,dict) and set(intent)=={'path','before','after','previous_identity','new_identity'}
          and intent['path']==path and intent['before']==targets[path]['before'] and intent['after']==targets[path]['after']
          and intent['previous_identity']==before[1],'home_parent_identity')
  new=checked_stamp(intent['new_identity'],targets[path]['after']);prior=handed.read('stage/'+str(index))[:2];stages[path]=prior
  require(records['snapshot/'+str(index)][0]==records['drill/'+str(index)][0]==targets[path]['before']
          and current[path][0]==targets[path]['after'] and current[path][1][:-1]==new[:-1]
          and prior[0]==targets[path]['before'] and prior[1][:-1]==old[:-1],'home_parent_snapshot')
 inherited=previous|current
 extra=(set(HOME_POLICY['order'])|set(HOME_POLICY['dependencies']))-set(inherited)
 complete=inherited|public.inventory(extra)
 require(all(complete[p][0]==r for p,r in HOME_POLICY['dependencies'].items()),'home_dependency_changed')
 before={p:complete[p] for p in HOME_POLICY['order']}
 require(all(row[0]==HOME_POLICY['targets'][p]['before']
             and (row[1] is None if row[0]=={'state':'absent'} else row[1][6]==os.getuid()) for p,row in before.items()),
         'predecessor_drift')
 ancestors={**snapshot['ancestors'],'handoff':refactor_stamp_rows(records),'handoff_stage':refactor_stamp_rows(stages)}
 return ({p:r for p,r in complete.items() if p not in HOME_POLICY['order']},before,ancestors)
def home_check_ancestors():
 site_continuation_namespace_absent();handoff_absent(REFACTOR_ROLLBACK_OPERATION);handoff_absent(HANDOFF_ROLLBACK_OPERATION)
 require(home_ancestor_inventory()==ancestors,'home_parent_changed')
 require(public.inventory(guarded)==guarded,'protected_changed')
def home_check_targets(expected):
 require(public.inventory(HOME_POLICY['order'])==expected,'home_target_changed')
def home_restore_one(store,path,index,current,prior,capture):
 home_check_ancestors()
 require(public.read(path)[:2]==current and store.read('stage/'+str(index))[:2]==prior,'rollback_preflight_race')
 capture_owned(public,private,path,capture,current[0],current[1])
 if prior[0]!={'state':'absent'}:restore_capture(public,store,path,'stage/'+str(index))
 restored=public.read(path)[:2];require(home_same_original(restored,prior),'rollback_incomplete')
 return restored
def home_bind_restoration(before):
 global _home_local_before,_home_local_restored
 home_check_ancestors();restored=public.inventory(HOME_POLICY['order'])
 require(all(home_same_original(row,before[p]) for p,row in restored.items()),'rollback_incomplete')
 _home_local_before=refactor_stamp_rows(before);_home_local_restored=refactor_stamp_rows(restored)
 home_handoff_parent();home_check_targets(restored);home_check_ancestors()
 return _home_local_restored
def home_original_records():
 records=site_read_records(homed,HOME_PREPARED_PATHS+('result.json',));record=lambda p:json.loads(records[p][2])
 require(record('reservation.json')=={'operation':HOME_POLICY['operation'],**HOME_ORIGINAL,
         'source':HOME_POLICY['source'],'manifest':HOME_POLICY['manifest'],'parent_handoff':HANDOFF_ORIGINAL}
         and record('result.json')==HOME_ACCEPTED,'home_original_identity')
 snapshot=record('snapshot/manifest.json');order=HOME_POLICY['order'];targets=HOME_POLICY['targets']
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_handoff','before','guarded','ancestors'}
         and snapshot['source']==HOME_POLICY['source'] and snapshot['manifest']==HOME_POLICY['manifest']
         and snapshot['parent_handoff']==HANDOFF_ORIGINAL and set(snapshot['before'])==set(order)
         and set(snapshot['guarded'])==((set(POLICY['expected'])|set(checked_protected_paths())
             |set(SITE_POLICY['dependencies'])|set(SITE_POLICY['optional_dependencies'])|set(SITE_POLICY['order'])
             |set(HOME_POLICY['dependencies']))-set(order))
         and digest(records['snapshot/manifest.json'][2])==HOME_ACCEPTED['snapshot_manifest_sha256'],'home_snapshot_conflict')
 guarded=refactor_guard_from_snapshot(snapshot['guarded'])
 require(home_ancestor_inventory()==snapshot['ancestors'],'home_parent_changed')
 current=public.inventory(order);prior={};old_identities={}
 for index,path in enumerate(order):
  before=snapshot['before'][path]
  require(isinstance(before,list) and len(before)==2 and before[0]==targets[path]['before'],'home_snapshot_conflict')
  old_identities[path]=home_before_identity(before[1],targets[path]['before'])
  require(records['snapshot/'+str(index)][0]==records['drill/'+str(index)][0]==home_backup_row(path),'home_snapshot_conflict')
  intent=record('intent-'+str(index))
  require(isinstance(intent,dict) and set(intent)=={'path','before','after','previous_identity','new_identity'}
          and intent['path']==path and intent['before']==targets[path]['before'] and intent['after']==targets[path]['after']
          and intent['previous_identity']==before[1],'home_original_identity')
  new=checked_stamp(intent['new_identity'],targets[path]['after']);prior[path]=homed.read('stage/'+str(index))[:2]
  require(current[path][0]==targets[path]['after'] and current[path][1][:-1]==new[:-1]
          and home_same_original(prior[path],(before[0],old_identities[path])),'home_snapshot_conflict')
 return snapshot,records,guarded,current,prior,old_identities
'''


HOME_REMOTE = r'''
result={'schema_version':1,'operation_id':HOME_POLICY['operation'],'source_sha':HOME_POLICY['source'],
        'manifest_sha256':HOME_POLICY['manifest'],**IDENTITY,'parent_handoff':HANDOFF_ORIGINAL,
        'parent_receipt_sha256':HOME_POLICY['parent']['receipt_sha256'],
        'status':'blocked_no_replay','phase':'preflight','supplier_calls':0,'database_reads':0,'database_writes':0,
        'lead_calls':0,'booking_calls':0,'application_calls':0,'written_files':[],'rollback_files':[],
        'reservation_created':False,'snapshot_verified':False,'restore_drill_verified':False,
        'protected_unchanged':False,'unchanged_files_verified':False,'parent_records_unchanged':False,
        'original_inodes_verified':False,'failed_namespace_absent':False,'snapshot_manifest_sha256':None,'restored_guard':None}
public=base=original=updated=published=refactored=handed=private=None;reserved=False;switched=[];owned={};prior={};prepared=None;reservation=None
try:
 require(hasattr(os,'O_PATH') and hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY')
         and hasattr(ctypes.CDLL(None),'renameat2'),'unsupported_platform')
 order=HOME_POLICY['order'];targets=HOME_POLICY['targets'];home=os.environ.get('HOME','')
 require(len(order)==len(set(order))==16 and set(PAYLOAD)==set(targets)==set(order)
         and not set(order)&(set(HANDOFF_POLICY['order'])|set(SITE_POLICY['order'])),'home_scope')
 payload={p:base64.b64decode(PAYLOAD[p],validate=True) for p in order}
 require(all(targets[p]['after']=={'state':'present','sha256':digest(raw),'size':len(raw),'mode':'0644'}
             and 0<len(raw)<=MAX_BYTES for p,raw in payload.items()),'payload_bytes')
 public=Files(home+'/www/anytoour.ru');base=Files(home+'/.anytoour-search3-root-gateway');private_root(base)
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation']);private_root(original)
 updated=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_POLICY['operation']);private_root(updated)
 published=Files(home+'/.anytoour-search3-root-gateway/'+SITE_POLICY['operation']);private_root(published)
 refactored=Files(home+'/.anytoour-search3-root-gateway/'+REFACTOR_POLICY['operation']);private_root(refactored)
 handed=Files(home+'/.anytoour-search3-root-gateway/'+HANDOFF_POLICY['operation']);private_root(handed)
 handoff_absent(HOME_ROLLBACK_OPERATION)
 require(os.fstat(base.fd).st_dev==os.fstat(public.fd).st_dev,'different_filesystem')
 guarded,before,ancestors=home_handoff_parent()
 require(all(row[0]==targets[p]['before'] and (row[1] is None or row[1][6]==os.getuid()) for p,row in before.items()),'predecessor_drift')
 home_check_ancestors();home_check_targets(before)
 try:os.mkdir(HOME_POLICY['operation'],0o700,dir_fd=base.fd)
 except FileExistsError:raise RuntimeError('operation_exists_no_replay') from None
 reserved=True;result['reservation_created']=True;sync(base.fd)
 private=Files(home+'/.anytoour-search3-root-gateway/'+HOME_POLICY['operation'],('snapshot','drill','stage','rollback'),0o700);private_root(private)
 reservation={'operation':HOME_POLICY['operation'],**IDENTITY,'source':HOME_POLICY['source'],
              'manifest':HOME_POLICY['manifest'],'parent_handoff':HANDOFF_ORIGINAL}
 private.create('reservation.json',canonical(reservation));result['phase']='snapshot'
 snapshot={'source':HOME_POLICY['source'],'manifest':HOME_POLICY['manifest'],'parent_handoff':HANDOFF_ORIGINAL,
           'before':refactor_stamp_rows(before),'guarded':refactor_stamp_rows(guarded),'ancestors':ancestors}
 for index,path in enumerate(order):
  row,identity,raw=public.read(path,True);require((row,identity)==before[path],'predecessor_drift')
  private.create('snapshot/'+str(index),raw if raw is not None else b'',0o644)
  require(private.read('snapshot/'+str(index))[0]==home_backup_row(path),'snapshot_invalid')
 private.create('snapshot/manifest.json',canonical(snapshot))
 require(private.read('snapshot/manifest.json',True)[2]==canonical(snapshot),'snapshot_invalid')
 result['snapshot_manifest_sha256']=digest(canonical(snapshot));result['snapshot_verified']=True
 for index,path in enumerate(order):
  private.create('drill/'+str(index),private.read('snapshot/'+str(index),True)[2],0o644)
  require(private.read('drill/'+str(index))[0]==home_backup_row(path),'restore_drill_failed')
 result['restore_drill_verified']=True
 for index,path in enumerate(order):
  private.create('stage/'+str(index),payload[path],0o644);owned[path]=private.read('stage/'+str(index))[:2]
  require(owned[path][0]==targets[path]['after'],'payload_bytes')
  private.create('intent-'+str(index),canonical({'path':path,'before':targets[path]['before'],'after':targets[path]['after'],
                 'previous_identity':home_ident(before[path][1]),'new_identity':list(owned[path][1])}))
 prepared=site_read_records(private,HOME_PREPARED_PATHS)
 require(prepared['reservation.json'][2]==canonical(reservation) and prepared['snapshot/manifest.json'][2]==canonical(snapshot)
         and all(prepared['snapshot/'+str(i)][0]==prepared['drill/'+str(i)][0]==home_backup_row(p)
                 and json.loads(prepared['intent-'+str(i)][2])=={'path':p,'before':targets[p]['before'],'after':targets[p]['after'],
                      'previous_identity':home_ident(before[p][1]),'new_identity':list(owned[p][1])} for i,p in enumerate(order)),'snapshot_invalid')
 current=dict(before);result['phase']='assets'
 for index,path in enumerate(order):
  home_check_ancestors();home_check_targets(current)
  require(site_read_records(private,HOME_PREPARED_PATHS)==prepared
          and private.read('stage/'+str(index))[:2]==owned[path],'prewrite_race')
  with private.parent('stage/'+str(index)) as (src,name),public.parent(path) as (dst,target):
   require(public.read(path)[:2]==before[path],'entry_prewrite_race')
   if before[path][0]=={'state':'absent'}:move_noreplace(src,name,dst,target)
   else:exchange(src,name,dst,target)
   switched.append(path);result['written_files'].append(path);sync(src);sync(dst)
  observed=public.read(path)[:2]
  require(observed[0]==targets[path]['after'] and observed[1][:-1]==owned[path][1][:-1],'home_readback')
  owned[path]=observed;current[path]=observed
  try:
   prior[path]=private.read('stage/'+str(index))[:2]
   require(home_same_original(prior[path],before[path]),'home_exchange_conflict')
  except Exception:
   home_check_ancestors()
   capture_owned(public,private,path,'rollback/conflict-'+str(index),observed[0],observed[1])
   if before[path][0]!={'state':'absent'}:restore_capture(public,private,path,'stage/'+str(index))
   switched.remove(path)
   raise RuntimeError('home_exchange_conflict')
  home_check_targets(current);home_check_ancestors()
 require(site_read_records(private,HOME_PREPARED_PATHS)==prepared
         and all(private.read('stage/'+str(i))[:2]==prior[p] for i,p in enumerate(order)),'home_snapshot_conflict')
 result.update(status='home_navigation_updated_verified',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
               parent_records_unchanged=True,original_inodes_verified=True)
except Exception as error:
 result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
 if reserved:
  result['status']='unknown_no_replay';recovery_ok=True
  for path in reversed(switched):
   try:
    index=order.index(path);current_row=public.read(path)[:2];saved=private.read('stage/'+str(index))[:2]
    require(current_row[0]==targets[path]['after'] and current_row[1][:-1]==owned[path][1][:-1]
            and home_same_original(saved,before[path]),'rollback_entry_conflict')
    home_restore_one(private,path,index,current_row,saved,'rollback/'+str(index));result['rollback_files'].append(path)
   except Exception:recovery_ok=False
  try:
   require(recovery_ok,'rollback_incomplete');home_check_ancestors()
   restored=public.inventory(order)
   require(all(home_same_original(row,before[p]) for p,row in restored.items())
           and result['rollback_files']==list(reversed(result['written_files'])),'rollback_incomplete')
   require(private is not None and isinstance(reservation,dict),'home_restoration_record')
   proof_record=site_read_records(private,('reservation.json',))
   require(proof_record['reservation.json'][2]==canonical(reservation),'home_restoration_record')
   result['restored_guard']=home_bind_restoration(before)
   result.update(status='rolled_back_no_replay',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
                 parent_records_unchanged=True,original_inodes_verified=True)
  except Exception:result['status']='unknown_no_replay'
finally:
 if reserved and private is not None:
  try:private.create('result.json',canonical(result))
  except Exception:result.update(status='unknown_no_replay',reason='receipt_failed')
 for store in (private,handed,refactored,published,updated,original,base,public):
  if store is not None:store.close()
print(json.dumps(result,sort_keys=True))
'''


HOME_ROLLBACK_REMOTE = r'''
result={'schema_version':1,'operation_id':HOME_ROLLBACK_OPERATION,'source_sha':HOME_POLICY['source'],
        'manifest_sha256':HOME_POLICY['manifest'],**IDENTITY,'parent_handoff':HANDOFF_ORIGINAL,
        'parent_receipt_sha256':HOME_POLICY['parent']['receipt_sha256'],'original_home':HOME_ORIGINAL,
        'status':'blocked_no_replay','phase':'preflight','supplier_calls':0,'database_reads':0,'database_writes':0,
        'lead_calls':0,'booking_calls':0,'application_calls':0,'written_files':[],'rollback_files':[],
        'reservation_created':False,'snapshot_verified':False,'restore_drill_verified':False,
        'protected_unchanged':False,'unchanged_files_verified':False,'parent_records_unchanged':False,
        'original_inodes_verified':False,'failed_namespace_absent':False,
        'snapshot_manifest_sha256':HOME_ACCEPTED['snapshot_manifest_sha256'],'restored_guard':None}
public=base=original=updated=published=refactored=handed=homed=private=None;reserved=False
try:
 require(hasattr(os,'O_PATH') and hasattr(os,'O_NOFOLLOW') and hasattr(os,'O_DIRECTORY')
         and hasattr(ctypes.CDLL(None),'renameat2'),'unsupported_platform')
 order=HOME_POLICY['order'];targets=HOME_POLICY['targets'];home=os.environ.get('HOME','')
 public=Files(home+'/www/anytoour.ru');base=Files(home+'/.anytoour-search3-root-gateway');private_root(base)
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation']);private_root(original)
 updated=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_POLICY['operation']);private_root(updated)
 published=Files(home+'/.anytoour-search3-root-gateway/'+SITE_POLICY['operation']);private_root(published)
 refactored=Files(home+'/.anytoour-search3-root-gateway/'+REFACTOR_POLICY['operation']);private_root(refactored)
 handed=Files(home+'/.anytoour-search3-root-gateway/'+HANDOFF_POLICY['operation']);private_root(handed)
 homed=Files(home+'/.anytoour-search3-root-gateway/'+HOME_POLICY['operation']);private_root(homed)
 require(os.fstat(base.fd).st_dev==os.fstat(public.fd).st_dev,'different_filesystem')
 site_continuation_namespace_absent()
 paths=HOME_PREPARED_PATHS+('result.json',)
 snapshot,records,guarded,current,prior,old_identities=home_original_records();ancestors=snapshot['ancestors']
 home_check_ancestors();home_check_targets(current)
 require(site_read_records(homed,paths)==records,'rollback_preflight_race')
 result.update(snapshot_verified=True,restore_drill_verified=True)
 try:os.mkdir(HOME_ROLLBACK_OPERATION,0o700,dir_fd=base.fd)
 except FileExistsError:raise RuntimeError('operation_exists_no_replay') from None
 reserved=True;result['reservation_created']=True;sync(base.fd)
 private=Files(home+'/.anytoour-search3-root-gateway/'+HOME_ROLLBACK_OPERATION,('quarantine',),0o700);private_root(private)
 private.create('reservation.json',canonical({'operation':HOME_ROLLBACK_OPERATION,**IDENTITY,'original_home':HOME_ORIGINAL,
                'source':HOME_POLICY['source'],'manifest':HOME_POLICY['manifest'],'parent_handoff':HANDOFF_ORIGINAL}))
 result['phase']='assets'
 for index in reversed(range(len(order))):
  path=order[index];home_check_ancestors();home_check_targets(current)
  require(site_read_records(homed,paths)==records,'home_snapshot_conflict')
  current[path]=home_restore_one(homed,path,index,current[path],prior[path],'quarantine/'+str(index))
  result['rollback_files'].append(path)
  home_check_targets(current);home_check_ancestors()
 require(all(home_same_original(current[p],(targets[p]['before'],old_identities[p])) for p in order)
         and site_read_records(homed,paths)==records,'rollback_incomplete')
 result['restored_guard']=home_bind_restoration({p:(snapshot['before'][p][0],tuple(snapshot['before'][p][1]) if snapshot['before'][p][1] is not None else None) for p in order})
 result.update(status='home_navigation_rolled_back_verified',phase='complete',protected_unchanged=True,unchanged_files_verified=True,
               parent_records_unchanged=True,original_inodes_verified=True)
except Exception as error:
 result['status']='unknown_no_replay' if reserved else 'blocked_no_replay'
 result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
finally:
 if reserved and private is not None:
  try:private.create('result.json',canonical(result))
  except Exception:result.update(status='unknown_no_replay',reason='receipt_failed')
 for store in (private,homed,handed,refactored,published,updated,original,base,public):
  if store is not None:store.close()
print(json.dumps(result,sort_keys=True))
'''


HOME_REASONS = HANDOFF_REASONS + ('home_scope','home_parent_identity','home_parent_snapshot','home_parent_changed',
    'home_dependency_changed','home_target_changed','home_readback','home_exchange_conflict','home_snapshot_conflict',
    'home_original_identity','home_restoration_scope','home_restoration_before','home_restoration_target',
    'home_restoration_record','home_restoration_changed')


def _home_program_prefix(policy,identity,parent_value,tv_successor=None):
    need(policy==home_policy(),'home_policy');update_runtime_identity(identity)
    need(identity['run_id']!=HOME_PARENT['run_id'],'home_runtime_identity')
    parent,accepted,previous=home_parent_value(parent_value)
    return (_handoff_program_prefix(parent,identity,previous,tv_successor)
            +'\nHOME_POLICY='+repr(policy)+'\nHANDOFF_ORIGINAL='+repr(home_parent_identity())
            +'\nHANDOFF_ACCEPTED='+repr(accepted)+'\nHOME_ROLLBACK_OPERATION='+repr(HOME_ROLLBACK_OPERATION)
            +'\ndef re_safe(value): return value in '+repr(HOME_REASONS)+'\n'+HOME_COMMON_REMOTE)


def home_remote_program(policy,files,identity,parent_value,*,tv_successor=None):
    prefix=_home_program_prefix(policy,identity,parent_value,tv_successor)
    need(isinstance(files,dict) and set(files)==set(HOME_ORDER),'home_payload')
    for path in HOME_ORDER:
        raw=base64.b64decode(files[path],validate=True)
        need(0<len(raw)<=MAX_BYTES and HOME_TARGETS[path]['after']==
             {'state':'present','mode':'0644','size':len(raw),'sha256':sha(raw)},'home_payload_bytes')
    program=prefix+'\nPAYLOAD='+repr(files)+'\n'+HOME_REMOTE;ast.parse(program)
    return program


def home_rollback_remote_program(policy,identity,original,accepted,parent_value,*,tv_successor=None):
    prefix=_home_program_prefix(policy,identity,parent_value,tv_successor);update_runtime_identity(original)
    need(identity['run_id']!=original['run_id'] and identity['command_comment_id']!=original['command_comment_id']
         and valid_home_result(original,accepted),'home_rollback_evidence')
    program=(prefix+'\nHOME_ORIGINAL='+repr(original)+'\nHOME_ACCEPTED='+repr(accepted)+'\n'+HOME_ROLLBACK_REMOTE)
    ast.parse(program);return program


def _home_restored_guard(value):
    need(isinstance(value,dict) and set(value)==set(HOME_ORDER),'home_restoration_scope')
    for path,row in value.items():
        need(isinstance(row,list) and len(row)==2 and row[0]==HOME_TARGETS[path]['before'],'home_restoration_target')
        ident=row[1]
        if row[0]=={'state':'absent'}:
            need(ident is None,'home_restoration_target');continue
        need(isinstance(ident,list) and len(ident)==9 and all(type(v) is int for v in ident)
             and ident[0]>=0 and ident[1]>0 and ident[2]==(stat.S_IFREG|0o644) and ident[3]==1
             and ident[4]==row[0]['size'] and all(ident[i]>=0 for i in (5,6,7,8)),'home_restoration_target')


def valid_home_result(identity,result,original=None,accepted=None):
    rollback=original is not None;policy=home_policy()
    fixed={'schema_version':1,'operation_id':HOME_ROLLBACK_OPERATION if rollback else HOME_OPERATION,
           'source_sha':HOME_SOURCE,'manifest_sha256':policy['manifest'],**identity,
           'parent_handoff':home_parent_identity(),'parent_receipt_sha256':HOME_PARENT['receipt_sha256']}
    if rollback:fixed.update(original_home=original,snapshot_manifest_sha256=accepted['snapshot_manifest_sha256'])
    counters=('supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
    flags=('reservation_created','snapshot_verified','restore_drill_verified','protected_unchanged',
           'unchanged_files_verified','parent_records_unchanged','original_inodes_verified','failed_namespace_absent')
    required=set(fixed)|set(counters)|set(flags)|{'status','phase','written_files','rollback_files','snapshot_manifest_sha256','restored_guard'}
    need(isinstance(result,dict) and required<=set(result)<=required|{'reason'},'home_receipt_shape')
    need(all(type(result.get(k)) is type(v) and result[k]==v for k,v in fixed.items()),'home_receipt_identity')
    need(all(type(result[k]) is int and result[k]==0 for k in counters)
         and all(type(result[k]) is bool for k in flags),'home_receipt_counters')
    success='home_navigation_rolled_back_verified' if rollback else 'home_navigation_updated_verified'
    states=('blocked_no_replay','unknown_no_replay',success) if rollback else ('blocked_no_replay','unknown_no_replay','rolled_back_no_replay',success)
    need(result['status'] in states and result['phase'] in ('preflight','snapshot','assets','complete')
         and ('reason' not in result or result['reason'] in HOME_REASONS),'home_receipt_status')
    written,restored=result['written_files'],result['rollback_files']
    need(isinstance(written,list) and written in [list(HOME_ORDER[:i]) for i in range(len(HOME_ORDER)+1)]
         and isinstance(restored,list) and all(isinstance(p,str) for p in restored) and len(set(restored))==len(restored),'home_receipt_paths')
    order=list(reversed(HOME_ORDER if rollback else written))
    need(all(p in order for p in restored) and restored==[p for p in order if p in restored]
         and (not rollback or not written),'home_receipt_paths')
    snapshot=result['snapshot_manifest_sha256']
    need(snapshot is None or isinstance(snapshot,str) and re.fullmatch('[a-f0-9]{64}',snapshot),'home_receipt_snapshot')
    if result['status']=='blocked_no_replay':
        need(not result['reservation_created'] and result['phase']=='preflight' and not written and not restored,'home_receipt_incomplete')
    if result['status'] in (success,'rolled_back_no_replay'):
        need(result['phase']=='complete' and all(result[k] for k in ('reservation_created','protected_unchanged',
             'unchanged_files_verified','parent_records_unchanged','original_inodes_verified','failed_namespace_absent')),'home_receipt_incomplete')
    if result['status']=='rolled_back_no_replay':need(restored==list(reversed(written)),'home_receipt_incomplete')
    if result['status']==success:
        need(all(result[k] for k in flags) and snapshot is not None and 'reason' not in result
             and (restored==list(reversed(HOME_ORDER)) if rollback else written==list(HOME_ORDER) and not restored),'home_receipt_incomplete')
    if result['status'] in ('rolled_back_no_replay','home_navigation_rolled_back_verified'):_home_restored_guard(result['restored_guard'])
    elif result['status']!='unknown_no_replay':need(result['restored_guard'] is None,'home_receipt_incomplete')
    return result['status']==success


def _home_source_value(value,original,*,recovery=False,rollback_original=None,accepted=None):
    update_runtime_identity(original)
    rollback=rollback_original is not None
    status='home_navigation_rolled_back_verified' if rollback else 'rolled_back_no_replay' if recovery else 'home_navigation_updated_verified'
    fixed={**original,'operation_id':HOME_ROLLBACK_OPERATION if rollback else HOME_OPERATION,
           'source_sha':HOME_SOURCE,'manifest_sha256':home_policy()['manifest'],'read_only':False,
           'phase':'complete','ssh_started':True,'status':status,'parent_handoff':home_parent_identity()}
    if rollback:fixed.update(original_home=rollback_original,original_home_run_id=rollback_original['run_id'])
    need(isinstance(value,dict) and set(value)==set(fixed)|{'parent_evidence','remote'}
         and all(type(value.get(k)) is type(v) and value[k]==v for k,v in fixed.items()),'home_rollback_evidence')
    home_parent_value(value['parent_evidence'])
    success=valid_home_result(original,value['remote'],rollback_original,accepted)
    need(success or recovery and value['remote']['status']=='rolled_back_no_replay','home_rollback_evidence')
    return home_policy(),value['remote'],value['parent_evidence']


def home_source_evidence(raw,original):
    with zip_files(raw) as archive:
        need(archive.namelist()==['evidence.json'],'home_rollback_artifact')
        value=json.loads(archive.read('evidence.json'))
    return _home_source_value(value,original)


def home_restoration_value(value):
    need(isinstance(value,dict),'home_restoration_proof')
    if value=={'status':'absent'}:return value
    if value.get('status')=='zero_write':
        need(set(value)=={'status','original','install_evidence'},'home_restoration_proof')
        _home_zero_write_value(value['install_evidence'],value['original'])
        return value
    need(set(value)=={'status','original','install_evidence','restoration_evidence'} and value['status']=='restored',
         'home_restoration_proof')
    original=value['original'];installed=value['install_evidence'];restored=value['restoration_evidence']
    need(isinstance(installed,dict) and isinstance(restored,dict),'home_restoration_proof')
    recovery=installed.get('status')=='rolled_back_no_replay'
    _,accepted,_=_home_source_value(installed,original,recovery=recovery)
    if recovery:need(restored==installed,'home_restoration_proof')
    else:
        identity={k:restored.get(k) for k in ('control_source_sha','run_id','command_comment_id')}
        need(identity['run_id']!=original['run_id'] and identity['command_comment_id']!=original['command_comment_id'],
             'home_restoration_proof')
        _home_source_value(restored,identity,rollback_original=original,accepted=accepted)
    return value


def _home_zero_write_value(value,identity):
    update_runtime_identity(identity)
    fixed={**identity,'operation_id':HOME_OPERATION,'source_sha':HOME_SOURCE,
           'manifest_sha256':home_policy()['manifest'],'read_only':False,'status':'blocked_no_replay'}
    need(isinstance(value,dict) and all(type(value.get(k)) is type(v) and value[k]==v for k,v in fixed.items()),
         'home_zero_write')
    allowed=set(fixed)|{'phase','ssh_started','parent_handoff','parent_evidence','remote','error_type'}
    need(set(value)<=allowed and type(value.get('ssh_started')) is bool,'home_zero_write')
    if value['ssh_started']:
        need(value.get('phase')=='complete' and 'remote' in value and 'error_type' not in value,'home_zero_write')
        valid_home_result(identity,value['remote'])
        need(value['remote']['status']=='blocked_no_replay','home_zero_write')
        home_parent_value(value.get('parent_evidence'))
    else:
        need(value.get('phase') in ('admission','artifact') and 'remote' not in value
             and value.get('error_type') in ('RuntimeError','ValueError','KeyError','OSError','CalledProcessError'),
             'home_zero_write')
        if 'parent_evidence' in value:home_parent_value(value['parent_evidence'])
    if 'parent_handoff' in value:need(value['parent_handoff']==home_parent_identity(),'home_zero_write')



HOME_RESTORATION_REMOTE = r'''
_home_restoration_bound=None
_home_restoration_saved=None
_home_parent_active_bound=False
_home_original_guard=refactor_guard_from_snapshot
_home_original_check=handoff_check_ancestors if HOME_PARENT_CONTEXT=='handoff' else refactor_check_ancestors
_home_original_target_check=refactor_check_targets
def home_restoration_read(saved):
 proof=HOME_RESTORATION_PROOF
 if proof['status'] in ('absent','zero_write'):
  handoff_absent(HOME_POLICY['operation']);handoff_absent(HOME_ROLLBACK_OPERATION)
  return {'status':proof['status']}
 install=proof['install_evidence']['remote'];terminal=proof['restoration_evidence']['remote'];identity=proof['original']
 recovery=install['status']=='rolled_back_no_replay';written=install['written_files'];stores=[]
 try:
  child=Files(home+'/.anytoour-search3-root-gateway/'+HOME_POLICY['operation']);stores.append(child);private_root(child)
  rows={}
  for path in HOME_PREPARED_PATHS+('result.json',):
   row,ident,raw=child.read(path,True)
   if row=={'state':'absent'}:
    require(recovery and not written and path not in ('reservation.json','result.json'),'home_restoration_record')
   else:
    mode='0644' if path.startswith(('snapshot/','drill/')) and path!='snapshot/manifest.json' else '0600'
    require(row.get('mode')==mode and ident[6]==os.getuid(),'home_restoration_record')
   rows[path]=(row,ident,raw)
  require(rows['reservation.json'][2]==canonical({'operation':HOME_POLICY['operation'],**identity,
          'source':HOME_POLICY['source'],'manifest':HOME_POLICY['manifest'],'parent_handoff':HOME_PARENT_IDENTITY})
          and rows['result.json'][2]==canonical(install),'home_restoration_record')
  snapshot_row=rows['snapshot/manifest.json'];snapshot=None
  if snapshot_row[0]!={'state':'absent'}:
   snapshot=json.loads(snapshot_row[2])
   require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_handoff','before','guarded','ancestors'}
           and snapshot['source']==HOME_POLICY['source'] and snapshot['manifest']==HOME_POLICY['manifest']
           and snapshot['parent_handoff']==HOME_PARENT_IDENTITY and set(snapshot['before'])==set(HOME_POLICY['order'])
           and snapshot_row[2]==canonical(snapshot) and digest(snapshot_row[2])==install['snapshot_manifest_sha256'],
           'home_restoration_record')
   before=snapshot['before']
  else:
   require(recovery and not written and install['snapshot_manifest_sha256'] is None,'home_restoration_record')
   before=terminal['restored_guard']
  projected=home_projection(saved,before,terminal['restored_guard'])
  restore_rows={}
  if recovery:
   handoff_absent(HOME_ROLLBACK_OPERATION);restore=child;capture_prefix='rollback/'
  else:
   restore=Files(home+'/.anytoour-search3-root-gateway/'+HOME_ROLLBACK_OPERATION);stores.append(restore);private_root(restore)
   restore_rows=site_read_records(restore,('reservation.json','result.json'));capture_prefix='quarantine/'
   restore_identity={k:terminal[k] for k in ('control_source_sha','run_id','command_comment_id')}
   require(restore_rows['reservation.json'][2]==canonical({'operation':HOME_ROLLBACK_OPERATION,**restore_identity,
           'original_home':identity,'source':HOME_POLICY['source'],'manifest':HOME_POLICY['manifest'],
           'parent_handoff':HOME_PARENT_IDENTITY}) and restore_rows['result.json'][2]==canonical(terminal),
           'home_restoration_record')
  for index,path in enumerate(HOME_POLICY['order']):
   target=HOME_POLICY['targets'][path];intent_row=rows['intent-'+str(index)];intent=None
   if intent_row[0]!={'state':'absent'}:
    intent=json.loads(intent_row[2])
    require(isinstance(intent,dict) and set(intent)=={'path','before','after','previous_identity','new_identity'}
            and intent['path']==path and intent['before']==target['before'] and intent['after']==target['after']
            and intent['previous_identity']==before[path][1] and intent_row[2]==canonical(intent),'home_restoration_record')
    new=checked_stamp(intent['new_identity'],target['after'])
   for prefix in ('snapshot/','drill/'):
    if rows[prefix+str(index)][0]!={'state':'absent'}:
     require(rows[prefix+str(index)][0]==home_backup_row(path),'home_restoration_record')
   stage=child.read('stage/'+str(index))[:2];capture=restore.read(capture_prefix+str(index))[:2]
   rows['stage/'+str(index)]=stage;restore_rows[capture_prefix+str(index)]=capture
   if path in written:
    require(intent is not None and stage==({'state':'absent'},None) and capture[0]==target['after']
            and capture[1][:-1]==new[:-1],'home_restoration_record')
   else:
    require(recovery and capture==({'state':'absent'},None) and terminal['restored_guard'][path]==before[path],
            'home_restoration_record')
    if stage[0]!={'state':'absent'}:
     require(stage[0].get('mode')=='0644' and stage[1][6]==os.getuid(),'home_restoration_record')
     # No exchanges can precede complete preparation. With written=[] the
     # native terminal may bind a stage created just before its missing intent.
     if written or intent is not None:
      require(intent is not None and stage[0]==target['after'] and stage[1][:-1]==new[:-1],'home_restoration_record')
  # Hold every restored full stamp until the parent's first checked current
  # inventory is bound below. Only then may its fixed REFACTOR targets change,
  # under the original intent/inode and per-exchange current-target checks.
  active=set(REFACTOR_POLICY['order']) if HOME_PARENT_CONTEXT=='refactor' else set()
  scope=set(HOME_POLICY['order']) if not _home_parent_active_bound else set(HOME_POLICY['order'])-active
  require(public.inventory(scope)=={p:(terminal['restored_guard'][p][0],
          tuple(terminal['restored_guard'][p][1]) if terminal['restored_guard'][p][1] is not None else None) for p in scope},
          'home_restoration_target')
  return {'status':'restored','before':before,'restored':terminal['restored_guard'],'records':rows,'restore_records':restore_rows,
          'directories':[[name,expected] for store in stores for _,name,_,expected in store.links]}
 finally:
  for store in reversed(stores):store.close()
def home_restoration_stable():
 if _home_restoration_bound is not None:
  require(home_restoration_read(_home_restoration_saved)==_home_restoration_bound,'home_restoration_changed')
def refactor_guard_from_snapshot(saved):
 global _home_restoration_bound,_home_restoration_saved
 observed=home_restoration_read(saved)
 if _home_restoration_bound is None:
  _home_restoration_bound=observed;_home_restoration_saved=saved
 else:require(observed==_home_restoration_bound,'home_restoration_changed')
 projected=home_projection(saved,observed['before'],observed['restored']) if observed['status']=='restored' else saved
 result=_home_original_guard(projected);home_restoration_stable();return result
def home_parent_check():
 home_restoration_stable();_home_original_check();home_restoration_stable()
def home_parent_target_check(expected):
 global _home_parent_active_bound
 require(_home_restoration_bound is not None,'home_restoration_target')
 home_restoration_stable()
 if not _home_parent_active_bound and _home_restoration_bound['status']=='restored':
  for path in set(HOME_POLICY['order'])&set(REFACTOR_POLICY['order']):
   row=_home_restoration_bound['restored'][path]
   require(expected.get(path)==(row[0],tuple(row[1]) if row[1] is not None else None),'home_restoration_target')
 _home_original_target_check(expected)
 _home_parent_active_bound=True
 home_restoration_stable()
if HOME_PARENT_CONTEXT=='handoff':handoff_check_ancestors=home_parent_check
else:
 refactor_check_ancestors=home_parent_check
 refactor_check_targets=home_parent_target_check
'''


def home_restoration_prefix(value,context):
    need(context in ('handoff','refactor'),'home_restoration_parent')
    if value is None:return ''
    home_restoration_value(value)
    need(not set(HOME_ORDER)&set(HANDOFF_ORDER) and set(HOME_ORDER)&set(REFACTOR_ORDER)=={UPDATE_PATH},
         'home_restoration_scope')
    return ('\nHOME_POLICY='+repr(home_policy())+'\nHOME_ROLLBACK_OPERATION='+repr(HOME_ROLLBACK_OPERATION)
            +'\nHOME_PARENT_IDENTITY='+repr(home_parent_identity())+'\nHOME_PARENT_CONTEXT='+repr(context)
            +'\nHOME_RESTORATION_PROOF='+repr(value)+'\n'+HOME_COMMON_REMOTE+HOME_RESTORATION_REMOTE)


def home_pending_pins():
    need(type(HOME_SOURCE_PR) is int and HOME_SOURCE_PR>0 and type(HOME_ARTIFACT) is int and HOME_ARTIFACT>0
         and type(HOME_BUILD_RUN) is int and HOME_BUILD_RUN>0
         and isinstance(HOME_SOURCE_BRANCH,str) and HOME_SOURCE_BRANCH
         and HOME_CHECKS and len(set(HOME_CHECKS))==len(HOME_CHECKS)
         and all(isinstance(v,str) and re.fullmatch('[a-f0-9]{40}',v)
                 for v in (HOME_SOURCE,HOME_TREE,HOME_SOURCE_MERGE,HOME_SOURCE_MERGE_TREE,HOME_RELEASE_BASE,HOME_MAIN_BASE))
         and all(isinstance(v,str) and re.fullmatch('[a-f0-9]{64}',v) for v in (HOME_ARTIFACT_SHA,HOME_MANIFEST_SHA)),
         'home_pending_final_evidence')


def home_package(raw,parent_value):
    need(sha(raw)==HOME_ARTIFACT_SHA,'home_artifact')
    expected=home_overlay_predecessor(parent_value)
    need(len(HOME_ORDER)==16 and set(HOME_ORDER)==set(HOME_TARGETS)
         and set(HOME_SEARCH_ORDER)<set(expected) and set(HOME_SHARED_ORDER).isdisjoint(expected)
         and all(HOME_TARGETS[p]['before']==expected[p] for p in HOME_SEARCH_ORDER),'home_predecessor')
    with zip_files(raw) as archive:
        manifest_raw=archive.read('manifest.json');need(sha(manifest_raw)==HOME_MANIFEST_SHA,'manifest_digest')
        manifest=json.loads(manifest_raw)
        need(manifest.get('candidate')=={'sha':HOME_SOURCE,'tree_sha':HOME_TREE}
             and manifest.get('main')=={'sha':HOME_MAIN_BASE,'tree_sha':'39d167395c96680250e4bee5ba8c599f236ea5c8'}
             and manifest.get('profile')=={'schemaVersion':1,'profile':'live','scenario':'family'}
             and manifest.get('shared_ui',{}).get('enabled') is True,'home_manifest_identity')
        added,replaced=manifest['files']['added'],manifest['files']['replaced']
        candidate=[r for r in added if r.get('candidate_only') is True]
        need(len(candidate)==1 and candidate[0]['path']=='images/logo.svg'
             and candidate[0]['source_path']=='v2/visual-search/assets/anytour-logo.svg'
             and {'state':'present',**candidate[0]['served']}==HOME_DEPENDENCIES['images/logo.svg']
             and manifest.get('candidate_only_aliases')==candidate,'home_candidate_alias')
        rows=[r for r in added if r not in candidate]+replaced
        scope=set(expected)|set(HOME_SHARED_ORDER)
        need(len(rows)==len(scope)==48 and {r['path'] for r in rows}==scope
             and {r['path'] for r in replaced}=={ENTRY,'home-v1.php','home-v1.css','site-header-v2.php','site-header-v2.css'}
             and {x for x in archive.namelist() if x.startswith('overlay/') and not x.endswith('/')}
                 =={'overlay/'+p for p in scope},'home_overlay_scope')
        files={}
        for item in rows:
            path=item['path'];data=archive.read('overlay/'+path);record={'state':'present',**item['served']}
            need(item['served']=={'size':len(data),'mode':'0644','sha256':sha(data)}
                 and 0<len(data)<=MAX_BYTES,'overlay_bytes')
            if path in HOME_TARGETS:
                target=HOME_TARGETS[path]
                need(record==target['after'] and item['source_path']==target['source_path']
                     and item['source']==target['source'],'home_payload_bytes')
                if path in HOME_SHARED_ORDER and target['before']!={'state':'absent'}:
                    need(item.get('before')=={k:v for k,v in target['before'].items() if k!='state'},'home_predecessor')
                files[path]=base64.b64encode(data).decode()
            else:need(record==expected[path],'home_unexpected_delta')
    # The approved root entry is MAIN's existing 433-byte entry. SOURCE's
    # different entry and the candidate-only logo are never installed.
    blob=api('/git/blobs/'+HOME_ENTRY['blob'])
    need(blob.get('sha')==HOME_ENTRY['blob'] and blob.get('encoding')=='base64'
         and blob.get('size')==HOME_ENTRY['after']['size'],'home_entry_blob')
    data=base64.b64decode(blob['content'].replace('\n',''),validate=True)
    need({'state':'present','mode':'0644','size':len(data),'sha256':sha(data)}==HOME_ENTRY['after']
         and hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()==HOME_ENTRY['blob'],'home_entry_blob')
    files['index.php']=base64.b64encode(data).decode();need(set(files)==set(HOME_ORDER),'home_payload')
    return home_policy(),files


def home_final_heads(identity):
    home_pending_pins();rollback_main(identity)
    need(api('/git/ref/heads/release/search3-production-ready-v1')['object']['sha']==HOME_SOURCE_MERGE,'home_release_moved')
    pr=api('/pulls/'+str(HOME_SOURCE_PR))
    need(pr.get('state')=='closed' and pr.get('merged') is True and pr.get('merge_commit_sha')==HOME_SOURCE_MERGE
         and pr.get('head',{}).get('sha')==HOME_SOURCE and pr.get('head',{}).get('repo',{}).get('full_name')==REPO
         and pr.get('base',{}).get('ref')=='release/search3-production-ready-v1'
         and pr.get('base',{}).get('repo',{}).get('full_name')==REPO,'home_candidate_moved')
    need(api('/git/commits/'+HOME_SOURCE).get('tree',{}).get('sha')==HOME_TREE,'home_source_tree')
    merged=api('/git/commits/'+HOME_SOURCE_MERGE)
    need(merged.get('tree',{}).get('sha')==HOME_SOURCE_MERGE_TREE
         and [p.get('sha') for p in merged.get('parents',[])]==[HOME_RELEASE_BASE,HOME_SOURCE],'home_source_merge')
    relation=api('/compare/'+HOME_MAIN_BASE+'...'+identity['control_source_sha'])
    need(relation.get('status') in ('identical','ahead') and relation.get('behind_by')==0
         and relation.get('merge_base_commit',{}).get('sha')==HOME_MAIN_BASE,'home_control_ancestry')
    for commit in (HOME_SOURCE,HOME_SOURCE_MERGE):
        for path in HOME_SHARED_ORDER+HOME_SEARCH_ORDER:
            target=HOME_TARGETS[path];row=api('/contents/'+target['source_path']+'?ref='+commit)
            need(row.get('type')=='file' and row.get('sha')==target['source']['blob_sha'],'home_source_blob')
    # Shared MAIN owners stay aligned for future bounded operations; their
    # current source bytes never redefine production BEFORE rows.
    for path in HOME_SHARED_ORDER:
        row=api('/contents/'+HOME_TARGETS[path]['source_path']+'?ref='+identity['control_source_sha'])
        need(row.get('type')=='file' and row.get('sha')==HOME_TARGETS[path]['source']['blob_sha'],'home_main_shared_blob')
    for commit in (HOME_MAIN_BASE,identity['control_source_sha']):
        row=api('/contents/'+HOME_ENTRY['source_path']+'?ref='+commit)
        need(row.get('type')=='file' and row.get('sha')==HOME_ENTRY['blob'],'home_entry_blob')


def home_admission(identity):
    home_final_heads(identity);reviewed_control(identity['control_source_sha'])
    gate=api('/contents/.github/workflows/deploy-anytoour.yml?ref='+identity['control_source_sha'])
    need(gate.get('type')=='file' and gate.get('encoding')=='base64'
         and sha(base64.b64decode(gate['content']))==SITE_PUBLISHER_GUARD_SHA,'publisher_guard')
    checks=pages('/commits/'+HOME_SOURCE+'/check-runs','check_runs')
    for name in HOME_CHECKS:
        latest=max((c for c in checks if c.get('name')==name and c.get('head_sha')==HOME_SOURCE),key=lambda c:c['id'],default={})
        need(latest.get('status')=='completed' and latest.get('app',{}).get('slug')=='github-actions'
             and latest.get('conclusion')=='success','home_source_ci')
    run=api('/actions/runs/'+str(HOME_BUILD_RUN))
    need(run.get('id')==HOME_BUILD_RUN and run.get('head_sha')==HOME_SOURCE
         and run.get('head_branch')==HOME_SOURCE_BRANCH and run.get('path')=='.github/workflows/search3-visual-migration.yml'
         and run.get('event')=='pull_request' and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')=='success'
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff'
         and run.get('repository',{}).get('full_name')==run.get('head_repository',{}).get('full_name')==REPO,'home_build')
    artifact=api('/actions/artifacts/'+str(HOME_ARTIFACT))
    need(artifact.get('id')==HOME_ARTIFACT and artifact.get('name')=='search3-public-live-'+HOME_SOURCE
         and artifact.get('expired') is False and artifact.get('digest')=='sha256:'+HOME_ARTIFACT_SHA
         and artifact.get('workflow_run',{}).get('id')==HOME_BUILD_RUN
         and artifact.get('workflow_run',{}).get('head_sha')==HOME_SOURCE,'home_artifact')
    home_parent_admission()
    _unique_update_command(pages('/issues/4709/comments'),HOME_PREFIX,HOME_OPERATION,identity)
    home_final_heads(identity)


def home_event_identity(event,env,rollback=False):
    need(type(rollback) is bool,'mode')
    prefix,operation=(HOME_ROLLBACK_PREFIX,HOME_ROLLBACK_OPERATION) if rollback else (HOME_PREFIX,HOME_OPERATION)
    control=env.get('GITHUB_SHA','');body=event.get('comment',{}).get('body','');original_run=None
    if rollback:
        match=re.fullmatch(re.escape(prefix+' '+control+' ')+r'([1-9][0-9]{0,19}) '+re.escape(operation),body)
        need(match is not None,'command');original_run=match[1]
    else:need(body==f'{prefix} {control} {operation}','command')
    synthetic=json.loads(json.dumps(event));synthetic['comment']['body']=f'{UPDATE_PREFIX} {control} {UPDATE_OPERATION}'
    identity=update_event_identity(synthetic,env)
    excluded=(HOME_PARENT['run_id'],HANDOFF_PARENT['run_id'],REFACTOR_PARENT['run_id'],SITE_PARENT['run_id'],UPDATE_PARENT['run_id'],
              CONTINUATION_PARENT['run_id'],SITE_INSPECT_FAILED['run_id'],SITE_CONTINUATION_INSPECTION['run_id'],
              SITE_CONTINUATION_COLLECTOR['run_id'])
    need(identity['run_id'] not in excluded and original_run not in excluded,'home_runtime_identity')
    if rollback:need(original_run!=identity['run_id'],'command')
    return (identity,original_run) if rollback else identity


def home_parent_admission():
    pin=HOME_PARENT;run=api('/actions/runs/'+pin['run_id'])
    need(run.get('id')==int(pin['run_id']) and run.get('head_sha')==pin['control_source_sha']
         and run.get('head_branch')=='main' and run.get('path')==WORKFLOW and run.get('event')=='issue_comment'
         and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')=='success'
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff','home_parent_run')
    jobs=[j for j in pages('/actions/runs/'+pin['run_id']+'/attempts/1/jobs','jobs') if j.get('name')=='public-install']
    need(len(jobs)==1 and jobs[0].get('id')==pin['job_id'] and jobs[0].get('run_id')==int(pin['run_id'])
         and jobs[0].get('status')=='completed' and jobs[0].get('conclusion')=='success','home_parent_job')
    steps=[s for s in jobs[0].get('steps',[]) if s.get('name','').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps)==1 and steps[0].get('name')=='Run python3 scripts/deploy/search3_public_install.py handoff'
         and steps[0].get('status')=='completed' and steps[0].get('conclusion')=='success','home_parent_step')
    artifact=api('/actions/artifacts/'+str(pin['artifact_id']))
    need(artifact.get('id')==pin['artifact_id'] and artifact.get('name')=='search3-public-install-'+pin['run_id']+'-1'
         and artifact.get('expired') is False and artifact.get('workflow_run',{}).get('id')==int(pin['run_id'])
         and artifact.get('digest')=='sha256:'+pin['artifact_sha256'],'home_parent_artifact')
    comments=pages('/issues/4709/comments');_unique_update_command(comments,HANDOFF_PREFIX,HANDOFF_OPERATION,home_parent_identity())
    terminal=next((c for c in comments if c.get('id')==pin['terminal_comment_id']),{})
    tokens=[str(pin[k]) for k in ('run_id','artifact_id','artifact_sha256','receipt_sha256','snapshot_manifest_sha256')]
    need(terminal.get('user',{}).get('id')==226193297 and terminal.get('author_association')=='OWNER'
         and all(t in terminal.get('body','') for t in tokens+['country_handoff_updated_verified']),'home_parent_terminal')


def _home_commands():
    comments=pages('/issues/4709/comments');selected={}
    for prefix,operation in ((HOME_PREFIX,HOME_OPERATION),(HOME_ROLLBACK_PREFIX,HOME_ROLLBACK_OPERATION)):
        rows=[c for c in comments if c.get('body','').strip().split()[:1]==[prefix]
              and operation in c.get('body','').split()[1:]]
        need(len(rows)<=1,'home_commands')
        if not rows:continue
        row=rows[0];rollback=prefix==HOME_ROLLBACK_PREFIX
        pattern=re.escape(prefix)+r' ([a-f0-9]{40}) '+(r'([1-9][0-9]{0,19}) ' if rollback else '')+re.escape(operation)
        match=re.fullmatch(pattern,row.get('body',''))
        need(match is not None and row.get('user',{}).get('id')==226193297 and row.get('author_association')=='OWNER'
             and row.get('created_at')==row.get('updated_at'),'home_command')
        _tv_time(row.get('created_at'));selected['rollback' if rollback else 'install']=row
    need('rollback' not in selected or 'install' in selected,'home_commands')
    return selected


def home_rollback_commands(identity,original_run,expected_original=None):
    need(isinstance(original_run,str) and re.fullmatch('[1-9][0-9]{0,19}',original_run)
         and original_run not in (identity['run_id'],HOME_PARENT['run_id'],HANDOFF_PARENT['run_id'],REFACTOR_PARENT['run_id'],SITE_PARENT['run_id'],
                                  UPDATE_PARENT['run_id'],CONTINUATION_PARENT['run_id']),'home_rollback_run')
    comments=pages('/issues/4709/comments')
    _unique_update_command(comments,HOME_ROLLBACK_PREFIX,HOME_ROLLBACK_OPERATION,identity,original_run)
    commands=_home_commands();need('install' in commands,'home_rollback_command')
    installed=commands['install'];control=installed['body'].split()[1]
    original={'control_source_sha':control,'run_id':original_run,'command_comment_id':installed['id']}
    update_runtime_identity(original)
    need(expected_original is None or original==expected_original,'home_rollback_command')
    return original


def _home_native_proof(command,run,value,artifact,*,rollback=False,original=None,accepted=None):
    created=_tv_time(command['created_at']);tokens=command['body'].split();control=tokens[1]
    identity={'control_source_sha':control,'run_id':str(run.get('id')),'command_comment_id':command['id']}
    update_runtime_identity(identity)
    if rollback:
        need(tokens[2]==original['run_id'],'home_native_command')
        _home_source_value(value,identity,rollback_original=original,accepted=accepted);conclusion='success'
    elif value.get('status')=='blocked_no_replay':
        _home_zero_write_value(value,identity);conclusion='failure'
    else:
        recovery=value.get('status')=='rolled_back_no_replay'
        _home_source_value(value,identity,recovery=recovery);conclusion='failure' if recovery else 'success'
    need(run.get('head_sha')==control and run.get('head_branch')=='main' and run.get('path')==WORKFLOW
         and run.get('event')=='issue_comment' and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')==conclusion
         and created<=_tv_time(run.get('created_at'))<=created+300
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff','home_native_run')
    # Restoration introduces a new full-stamp authority. Bind the controller
    # actually executed by this historical run to its reviewed tree and CI;
    # a later controller revision is neither a substitute nor a blocker.
    reviewed_control(control)
    jobs=[j for j in pages('/actions/runs/'+identity['run_id']+'/attempts/1/jobs','jobs') if j.get('name')=='public-install']
    need(len(jobs)==1 and jobs[0].get('run_id')==run['id'] and jobs[0].get('status')=='completed'
         and jobs[0].get('conclusion')==conclusion,'home_native_job')
    steps=[s for s in jobs[0].get('steps',[]) if s.get('name','').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    mode='home-rollback' if rollback else 'home'
    need(len(steps)==1 and steps[0].get('name')=='Run python3 scripts/deploy/search3_public_install.py '+mode
         and steps[0].get('status')=='completed' and steps[0].get('conclusion')==conclusion,'home_native_step')
    need(created<=_tv_time(run.get('created_at'))<=_tv_time(run.get('run_started_at'))
         <=_tv_time(steps[0].get('started_at'))<=_tv_time(steps[0].get('completed_at'))<=_tv_time(run.get('updated_at')),
         'home_native_time')
    need(artifact.get('name')=='search3-public-install-'+identity['run_id']+'-1' and artifact.get('expired') is False
         and artifact.get('workflow_run',{}).get('id')==run['id']
         and re.fullmatch(r'sha256:[a-f0-9]{64}',artifact.get('digest','')),'home_native_artifact')
    return identity,value


def _home_command_evidence(command,*,rollback=False,original=None,accepted=None):
    created=_tv_time(command['created_at'])
    end=datetime.datetime.fromtimestamp(created+300,datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
    query=urllib.parse.quote(command['created_at']+'..'+end,safe='')
    runs=pages('/actions/workflows/search3-root-gateway-install.yml/runs?event=issue_comment&branch=main&created='+query,'workflow_runs')
    need(len(runs)<=100,'home_native_run_limit');matches=[]
    for run in runs:
        if run.get('head_sha')!=command['body'].split()[1]:continue
        for artifact in pages('/actions/runs/'+str(run['id'])+'/artifacts','artifacts'):
            if artifact.get('name')!='search3-public-install-'+str(run['id'])+'-1':continue
            digest=artifact.get('digest','')
            if artifact.get('expired') is not False or not re.fullmatch(r'sha256:[a-f0-9]{64}',digest):continue
            with zip_files(download(artifact['id'],digest.removeprefix('sha256:'))) as archive:
                need(archive.namelist()==['evidence.json'],'home_native_artifact')
                raw=archive.read('evidence.json');need(len(raw)<=262144,'home_native_artifact')
                value=_tv_json(raw)
            if not isinstance(value,dict) or value.get('command_comment_id')!=command['id']:continue
            matches.append(_home_native_proof(command,run,value,artifact,rollback=rollback,original=original,accepted=accepted))
    need(len(matches)==1,'home_original_missing')
    return matches[0]


def home_restoration_evidence():
    commands=_home_commands()
    if not commands:return {'status':'absent'}
    original,installed=_home_command_evidence(commands['install'])
    if installed['status']=='blocked_no_replay':
        need('rollback' not in commands,'home_restoration_pending')
        value={'status':'zero_write','original':original,'install_evidence':installed}
    elif installed['status']=='rolled_back_no_replay':
        need('rollback' not in commands,'home_restoration_pending')
        value={'status':'restored','original':original,'install_evidence':installed,'restoration_evidence':installed}
    else:
        need('rollback' in commands,'home_restoration_pending')
        _,restored=_home_command_evidence(commands['rollback'],rollback=True,original=original,accepted=installed['remote'])
        value={'status':'restored','original':original,'install_evidence':installed,'restoration_evidence':restored}
    home_restoration_value(value);need(_home_commands()==commands,'home_commands_changed')
    return value


def home_rollback_admission(identity,original_run):
    rollback_main(identity);reviewed_control(identity['control_source_sha'])
    original=home_rollback_commands(identity,original_run);run=api('/actions/runs/'+original_run)
    artifacts=[a for a in pages('/actions/runs/'+original_run+'/artifacts','artifacts')
               if a.get('name')=='search3-public-install-'+original_run+'-1']
    need(len(artifacts)==1 and artifacts[0].get('expired') is False
         and re.fullmatch(r'sha256:[a-f0-9]{64}',artifacts[0].get('digest','')),'home_rollback_artifact')
    artifact=artifacts[0]
    with zip_files(download(artifact['id'],artifact['digest'].removeprefix('sha256:'))) as archive:
        need(archive.namelist()==['evidence.json'],'home_rollback_artifact');value=_tv_json(archive.read('evidence.json'))
    command=_home_commands()['install'];checked,_=_home_native_proof(command,run,value,artifact)
    need(checked==original and value['status']=='home_navigation_updated_verified','home_rollback_evidence')
    rollback_main(identity);return original,artifact


def home(rollback=False):
    need(type(rollback) is bool,'mode')
    operation=HOME_ROLLBACK_OPERATION if rollback else HOME_OPERATION
    work=Path(os.environ['RUNNER_TEMP'])/'search3-public-install';work.mkdir(mode=0o700)
    key,known=work/'key',work/'known'
    evidence={'status':'blocked_no_replay','operation_id':operation,'source_sha':HOME_SOURCE,
              'manifest_sha256':home_policy()['manifest'],'read_only':False,'phase':'identity','ssh_started':False}
    complete=False
    try:
        event=json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
        if rollback:
            identity,original_run=home_event_identity(event,os.environ,True)
            evidence.update(identity,original_home_run_id=original_run,phase='admission')
            original,artifact=home_rollback_admission(identity,original_run);evidence['phase']='artifact'
            policy,accepted,parent_value=home_source_evidence(download(artifact['id'],artifact['digest'].removeprefix('sha256:')),original)
            dependency=tv_successor_evidence()
            program=home_rollback_remote_program(policy,identity,original,accepted,parent_value,tv_successor=dependency)
            evidence.update(parent_handoff=home_parent_identity(),parent_evidence=parent_value,original_home=original)
        else:
            identity=home_event_identity(event,os.environ);evidence.update(identity,phase='admission');home_admission(identity)
            evidence['phase']='artifact'
            parent_value=home_parent_evidence(download(HOME_PARENT['artifact_id'],HOME_PARENT['artifact_sha256']))
            policy,files=home_package(download(HOME_ARTIFACT,HOME_ARTIFACT_SHA),parent_value)
            dependency=tv_successor_evidence()
            program=home_remote_program(policy,files,identity,parent_value,tv_successor=dependency)
            evidence.update(parent_handoff=home_parent_identity(),parent_evidence=parent_value)
        # Exact existing carrier, key handling, options, output bound and timeout.
        host,user=os.environ['PREVIEW_HOST'],os.environ['PREVIEW_USER']
        need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]{0,252}',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
        raw=os.environ['PREVIEW_KEY'].replace('\r','');need(0<len(raw)<=32768,'ssh_key')
        if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
        with key.open('x') as stream:stream.write(raw.rstrip()+'\n')
        key.chmod(0o600)
        scan=carrier.run(['ssh-keyscan','-T','15','-t','ed25519',host]);need(0<len(scan)<=8192,'host_key')
        known.write_bytes(scan);known.chmod(0o600)
        options=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','ConnectTimeout=15']
        if rollback:
            checked,latest_artifact=home_rollback_admission(identity,original_run)
            need(checked==original and latest_artifact==artifact,'home_rollback_changed')
        else:home_admission(identity)
        need(tv_successor_evidence()==dependency,'tv_successor_changed')
        evidence.update(status='unknown_no_replay',phase='remote',ssh_started=True)
        response=carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'],input=program.encode(),timeout=180)
        need(len(response)<=32768,'receipt_limit');result=json.loads(response)
        complete=valid_home_result(identity,result,original,accepted) if rollback else valid_home_result(identity,result)
        evidence['remote']=result
        if rollback:rollback_main(identity)
        else:home_final_heads(identity)
        evidence.update(status=result['status'],phase='complete')
    except Exception as error:
        complete=False
        evidence['error_type']=type(error).__name__ if type(error) in (RuntimeError,ValueError,KeyError,OSError,subprocess.CalledProcessError,subprocess.TimeoutExpired) else 'HandoffError'
    finally:
        key.unlink(missing_ok=True);known.unlink(missing_ok=True)
        (work/'evidence.json').write_text(json.dumps(evidence,sort_keys=True)+'\n')
    print(json.dumps(evidence,sort_keys=True));need(complete,'home_rollback_not_verified' if rollback else 'home_not_verified')


# One finite, read-only audit of the retained HOME refusal. This profile cannot
# reserve, resume, repair, install or roll back any runtime operation.
HOME_INSPECT_PREFIX = '/inspect-site-home-guard'
HOME_INSPECT_OPERATION = 'site-home-guard-inspect-45078958662-v1'
HOME_INSPECT_RECEIPT_LIMIT = 262144
HOME_INSPECT_FAILED = {
    'operation_id': HOME_OPERATION,
    'control_source_sha': '428a3a5f8aab838daa3413020194dadeeea25bfc',
    'run_id': '45078958662', 'command_comment_id': 7086983423, 'job_id': 137382493638,
    'artifact_id': 14776437137,
    'artifact_sha256': 'b63c34ef5ecdf0582da5d206db326fd9403559281f4ad88987982877e700b877',
    'receipt_sha256': 'e9d378accedcbe5be9f3cd12594359d0ce2997ba614c6e8c62c0dc9189146cb3',
    'terminal_comment_id': 7087105642,
}
HOME_INSPECT_ROW_FIELDS = ('state','sha256','size','mode')
HOME_INSPECT_STAT_FIELDS = ('st_dev','st_ino','st_mode','st_nlink','st_size','st_mtime_ns','st_uid','st_gid','st_ctime_ns')
HOME_INSPECT_ANCESTORS = ('public','update','update_stage','site','site_stage','refactor','refactor_stage')
HOME_INSPECT_REASONS = HOME_REASONS + ('home_inspection_namespace','home_inspection_scope',
    'home_inspection_snapshot','home_inspection_unreadable','home_inspection_race')


def home_inspect_scope():
    private = {PRIVATE_CONFIG:'anex_private_config','config.php':'public_config','site_conf.php':'site_config',
               '_preview/search3-local-candidate/config.php':'local_config',
               '_preview/search3-next-candidate/config.php':'next_config'}
    guarded = (set(ADDITIONS)|{ENTRY}|set(CONTINUATION_PROTECTED)|set(OPAQUE_SETTINGS)|set(ABSENT_DEPENDENCIES)
               |set(SITE_DEPENDENCIES)|set(SITE_OPTIONAL_DEPENDENCIES)|set(SITE_ORDER))-set(HANDOFF_ORDER)
    need(set(private)<=guarded and len(private)==5 and len(guarded)<=512,'home_inspection_scope')
    return tuple(sorted(guarded-set(private))),private


def home_inspect_event_identity(event,env):
    control=env.get('GITHUB_SHA','')
    need(event.get('comment',{}).get('body')==f'{HOME_INSPECT_PREFIX} {control} {HOME_INSPECT_OPERATION}','command')
    synthetic=json.loads(json.dumps(event))
    synthetic['comment']['body']=f'{HOME_PREFIX} {control} {HOME_OPERATION}'
    identity=home_event_identity(synthetic,env)
    need(identity['run_id']!=HOME_INSPECT_FAILED['run_id'],'home_inspection_identity')
    return identity


def home_inspect_failed_value(value):
    need(sha((json.dumps(value,sort_keys=True)+'\n').encode())==HOME_INSPECT_FAILED['receipt_sha256'],
         'home_inspection_failed_digest')
    original={k:HOME_INSPECT_FAILED[k] for k in ('control_source_sha','run_id','command_comment_id')}
    _home_zero_write_value(value,original)
    remote=value.get('remote',{})
    flags=('reservation_created','snapshot_verified','restore_drill_verified','protected_unchanged',
           'unchanged_files_verified','parent_records_unchanged','original_inodes_verified')
    need(value.get('phase')=='complete' and value.get('ssh_started') is True
         and remote.get('status')=='blocked_no_replay' and remote.get('phase')=='preflight'
         and remote.get('reason')=='protected_changed' and remote.get('failed_namespace_absent') is True
         and remote.get('snapshot_manifest_sha256') is None and remote.get('restored_guard') is None
         and all(remote.get(k) is False for k in flags),'home_inspection_failed_evidence')
    return value['parent_evidence']


def home_inspect_failed_evidence(raw):
    need(sha(raw)==HOME_INSPECT_FAILED['artifact_sha256'],'home_inspection_failed_artifact_digest')
    with zip_files(raw) as archive:
        need(archive.namelist()==['evidence.json'],'home_inspection_failed_artifact')
        data=archive.read('evidence.json')
        need(sha(data)==HOME_INSPECT_FAILED['receipt_sha256'],'home_inspection_failed_digest')
    value=_tv_json(data);home_inspect_failed_value(value)
    return value


def home_inspect_heads(identity):
    home_final_heads(identity)
    relation=api('/compare/'+HOME_INSPECT_FAILED['control_source_sha']+'...'+identity['control_source_sha'])
    need(relation.get('status') in ('identical','ahead') and relation.get('behind_by')==0
         and relation.get('merge_base_commit',{}).get('sha')==HOME_INSPECT_FAILED['control_source_sha'],
         'home_inspection_control_ancestry')


def home_inspect_admission(identity):
    home_inspect_heads(identity);reviewed_control(identity['control_source_sha'])
    checks=pages('/commits/'+HOME_SOURCE+'/check-runs','check_runs')
    for name in HOME_CHECKS:
        latest=max((c for c in checks if c.get('name')==name and c.get('head_sha')==HOME_SOURCE),
                   key=lambda c:c['id'],default={})
        need(green(latest),'home_source_ci')
    comments=pages('/issues/4709/comments');failed=HOME_INSPECT_FAILED
    original={k:failed[k] for k in ('control_source_sha','run_id','command_comment_id')}
    _unique_update_command(comments,HOME_PREFIX,HOME_OPERATION,original)
    _unique_update_command(comments,HOME_INSPECT_PREFIX,HOME_INSPECT_OPERATION,identity)
    need(not any(c.get('body','').strip().split()[:1]==[HOME_ROLLBACK_PREFIX]
                 and HOME_ROLLBACK_OPERATION in c.get('body','').split()[1:] for c in comments),
         'home_inspection_original_changed')
    command=next(c for c in comments if c['id']==original['command_comment_id'])
    current=next(c for c in comments if c['id']==identity['command_comment_id'])
    for row in (command,current):
        _tv_time(row.get('created_at'))
        need(row.get('created_at')==row.get('updated_at'),'home_inspection_edited_command')
    terminal=next((c for c in comments if c['id']==failed['terminal_comment_id']),{})
    tokens=[str(failed[k]) for k in ('run_id','control_source_sha','command_comment_id','job_id',
                                    'artifact_id','artifact_sha256','receipt_sha256')]
    need(terminal.get('user',{}).get('id')==226193297 and terminal.get('author_association')=='OWNER'
         and all(t in terminal.get('body','') for t in tokens+['blocked_no_replay','protected_changed','NO REPLAY'])
         and terminal.get('created_at')==terminal.get('updated_at')
         and _tv_time(command['created_at'])<=_tv_time(terminal.get('created_at'))<=_tv_time(current['created_at']),
         'home_inspection_failed_terminal')
    run=api('/actions/runs/'+failed['run_id'])
    artifact=api('/actions/artifacts/'+str(failed['artifact_id']))
    need(artifact.get('id')==failed['artifact_id'] and artifact.get('digest')=='sha256:'+failed['artifact_sha256']
         and artifact.get('workflow_run',{}).get('head_sha')==failed['control_source_sha'],
         'home_inspection_failed_artifact')
    value=home_inspect_failed_evidence(download(failed['artifact_id'],failed['artifact_sha256']))
    checked,_=_home_native_proof(command,run,value,artifact)
    need(checked==original,'home_inspection_failed_identity')
    jobs=[j for j in pages('/actions/runs/'+failed['run_id']+'/attempts/1/jobs','jobs') if j.get('name')=='public-install']
    need(len(jobs)==1 and jobs[0].get('id')==failed['job_id'],'home_inspection_failed_job')
    home_inspect_heads(identity)
    return value


HOME_INSPECT_REMOTE = r'''
def home_inspection_absent(operation):
 with base.parent(operation) as (fd,name):
  try: os.stat(name,dir_fd=fd,follow_symlinks=False)
  except FileNotFoundError:return True
  return False
def home_inspection_namespaces():
 site_continuation_namespace_absent()
 handoff_absent(REFACTOR_ROLLBACK_OPERATION);handoff_absent(HANDOFF_ROLLBACK_OPERATION)
 result['home_namespace_absent']=(home_inspection_absent(HOME_POLICY['operation'])
                                  and home_inspection_absent(HOME_ROLLBACK_OPERATION))
 result['inspection_namespace_absent']=home_inspection_absent(INSPECTION_OPERATION)
 require(result['home_namespace_absent'] and result['inspection_namespace_absent'],'home_inspection_namespace')
def home_inspection_parent_proof():
 # Authenticate the unchanged COUNTRY snapshot before looking at its paths.
 # Current protected values are measurements, never replacement authority.
 records=site_read_records(handed,HANDOFF_PREPARED_PATHS+('result.json',));record=lambda p:json.loads(records[p][2])
 require(record('reservation.json')=={'operation':HANDOFF_POLICY['operation'],**HANDOFF_ORIGINAL,
         'source':HANDOFF_POLICY['source'],'manifest':HANDOFF_POLICY['manifest'],'parent_refactor':REFACTOR_ORIGINAL}
         and record('result.json')==HANDOFF_ACCEPTED,'home_parent_identity')
 snapshot=record('snapshot/manifest.json')
 scope=((set(POLICY['expected'])|set(checked_protected_paths())|set(SITE_POLICY['dependencies'])
         |set(SITE_POLICY['optional_dependencies'])|set(SITE_POLICY['order']))-set(HANDOFF_POLICY['order']))
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_refactor','before','guarded','ancestors'}
         and snapshot['source']==HANDOFF_POLICY['source'] and snapshot['manifest']==HANDOFF_POLICY['manifest']
         and snapshot['parent_refactor']==REFACTOR_ORIGINAL and set(snapshot['before'])==set(HANDOFF_POLICY['order'])
         and set(snapshot['guarded'])==scope and scope==set(INSPECTION_PUBLIC_PATHS)|set(private_labels())
         and set(snapshot['ancestors'])==set(INSPECTION_ANCESTORS)
         and digest(records['snapshot/manifest.json'][2])==HANDOFF_ACCEPTED['snapshot_manifest_sha256'],
         'home_inspection_snapshot')
 return records,snapshot
def home_inspection_stat_changes(expected,current):
 if expected is None or current is None:
  return [] if expected is None and current is None else list(INSPECTION_STAT_FIELDS)
 require(isinstance(expected,(list,tuple)) and isinstance(current,(list,tuple)) and len(expected)==len(current)==9,
         'home_inspection_snapshot')
 return [name for i,name in enumerate(INSPECTION_STAT_FIELDS) if expected[i]!=current[i]]
def home_inspection_ancestor_check(expected,current,confirmed):
 same_scope=set(expected)==set(current)
 return {'matches_scope':same_scope,
         'matches_rows':same_scope and all(expected[p][0]==current[p][0] for p in expected),
         'matches_identity':same_scope and all(expected[p][1]==current[p][1] for p in expected),
         'stable':current==confirmed}
result={'schema_version':1,'operation_id':INSPECTION_OPERATION,'source_sha':HOME_POLICY['source'],
        'manifest_sha256':HOME_POLICY['manifest'],**IDENTITY,'failed_attempt':FAILED,'parent_handoff':HANDOFF_ORIGINAL,
        'parent_receipt_sha256':HOME_POLICY['parent']['receipt_sha256'],
        'parent_snapshot_manifest_sha256':HOME_POLICY['parent']['snapshot_manifest_sha256'],
        'read_only':True,'server_writes':0,'supplier_calls':0,'database_reads':0,'database_writes':0,
        'lead_calls':0,'booking_calls':0,'application_calls':0,'status':'inspection_blocked','phase':'preflight',
        'parent_records_verified':False,'parent_records_stable':False,'inventory_stable':False,
        'failed_namespace_absent':False,'home_namespace_absent':False,'inspection_namespace_absent':False,'scope_matches':False,
        'checked_public_paths':[],'public_mismatches':[],'private_checks':{},'ancestor_checks':{}}
public=base=original=updated=published=refactored=handed=None
try:
 home=os.environ.get('HOME','');public=Files(home+'/www/anytoour.ru')
 base=Files(home+'/.anytoour-search3-root-gateway');private_root(base)
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation']);private_root(original)
 updated=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_POLICY['operation']);private_root(updated)
 published=Files(home+'/.anytoour-search3-root-gateway/'+SITE_POLICY['operation']);private_root(published)
 refactored=Files(home+'/.anytoour-search3-root-gateway/'+REFACTOR_POLICY['operation']);private_root(refactored)
 handed=Files(home+'/.anytoour-search3-root-gateway/'+HANDOFF_POLICY['operation']);private_root(handed)
 home_inspection_namespaces()
 records,snapshot=home_inspection_parent_proof();saved=snapshot['guarded']
 ancestors=home_ancestor_inventory()
 require(set(ancestors)==set(INSPECTION_ANCESTORS)|{'handoff','handoff_stage'},'home_inspection_scope')
 result['parent_records_verified']=True
 expected=refactor_expected({p:r[0] for p,r in saved.items()})
 labels=private_labels();scope=tuple(sorted(saved))
 rows=continuation_inventory(public,scope)
 for path in scope:
  row,identity=rows[path];previous=saved[path][1]
  if TV_SUCCESSOR_PROOF is not None and path==TV_SUCCESSOR['path']:previous=_tv_successor_bound['target'][1]
  row_changes=[name for name in INSPECTION_ROW_FIELDS if row.get(name)!=expected[path].get(name)]
  stat_changes=home_inspection_stat_changes(previous,identity)
  if path in labels:
   result['private_checks'][labels[path]]={'readable':row.get('state')!='unreadable','present':row.get('state')=='present',
       'expected_present':expected[path].get('state')=='present','matches_row':row==expected[path],
       'matches_identity':not stat_changes}
  elif row!=expected[path] or stat_changes:
   result['public_mismatches'].append({'path':path,'expected':expected[path],'observed':row,
       'changed_row_fields':row_changes,'changed_stat_fields':stat_changes})
 result['checked_public_paths']=list(INSPECTION_PUBLIC_PATHS)
 confirmed=continuation_inventory(public,scope)
 confirmed_ancestors=home_ancestor_inventory()
 for group in INSPECTION_ANCESTORS:
  result['ancestor_checks'][group]=home_inspection_ancestor_check(snapshot['ancestors'][group],
      ancestors[group],confirmed_ancestors[group])
 result['inventory_stable']=confirmed==rows
 result['parent_records_stable']=(confirmed_ancestors==ancestors
     and site_read_records(handed,HANDOFF_PREPARED_PATHS+('result.json',))==records)
 home_inspection_namespaces()
 tv_successor_stable()
 require(result['inventory_stable'] and result['parent_records_stable'],'home_inspection_race')
 require(all(row[0].get('state')!='unreadable' for row in rows.values()),'home_inspection_unreadable')
 result['scope_matches']=(not result['public_mismatches']
     and all(r['readable'] and r['matches_row'] and r['matches_identity'] for r in result['private_checks'].values())
     and all(r['matches_scope'] and r['matches_rows'] and r['matches_identity'] for r in result['ancestor_checks'].values()))
 result.update(status='inspection_match' if result['scope_matches'] else 'inspection_mismatch',phase='complete')
except Exception as error:
 result['status']='inspection_unknown' if result['parent_records_verified'] else 'inspection_blocked'
 result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
finally:
 for store in (handed,refactored,published,updated,original,base,public):
  if store is not None:store.close()
print(json.dumps(result,sort_keys=True))
'''


def home_inspect_remote_program(identity,failed_value,*,tv_successor=None):
    parent=home_inspect_failed_value(failed_value)
    need(identity['run_id']!=HOME_INSPECT_FAILED['run_id'],'home_inspection_identity')
    public,_=home_inspect_scope()
    program=(_home_program_prefix(home_policy(),identity,parent,tv_successor)
        +'\nFAILED='+repr(HOME_INSPECT_FAILED)+'\nINSPECTION_OPERATION='+repr(HOME_INSPECT_OPERATION)
        +'\nINSPECTION_PUBLIC_PATHS='+repr(public)+'\nINSPECTION_ANCESTORS='+repr(HOME_INSPECT_ANCESTORS)
        +'\nINSPECTION_ROW_FIELDS='+repr(HOME_INSPECT_ROW_FIELDS)+'\nINSPECTION_STAT_FIELDS='+repr(HOME_INSPECT_STAT_FIELDS)
        +'\ndef re_safe(value): return value in '+repr(HOME_INSPECT_REASONS)+'\n'+HOME_INSPECT_REMOTE)
    ast.parse(program)
    return program


def valid_home_inspection_result(identity,result):
    fixed={'schema_version':1,'operation_id':HOME_INSPECT_OPERATION,'source_sha':HOME_SOURCE,
        'manifest_sha256':home_policy()['manifest'],**identity,'failed_attempt':HOME_INSPECT_FAILED,
        'parent_handoff':home_parent_identity(),'parent_receipt_sha256':HOME_PARENT['receipt_sha256'],
        'parent_snapshot_manifest_sha256':HOME_PARENT['snapshot_manifest_sha256'],'read_only':True}
    counters=('server_writes','supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
    flags=('parent_records_verified','parent_records_stable','inventory_stable','failed_namespace_absent',
           'home_namespace_absent','inspection_namespace_absent','scope_matches')
    required=set(fixed)|set(counters)|set(flags)|{'status','phase','checked_public_paths','public_mismatches','private_checks','ancestor_checks'}
    need(isinstance(result,dict) and required<=set(result)<=required|{'reason'},'home_inspection_receipt_shape')
    need(all(type(result.get(k)) is type(v) and result[k]==v for k,v in fixed.items()),'home_inspection_receipt_identity')
    need(all(type(result[k]) is int and result[k]==0 for k in counters),'home_inspection_receipt_counters')
    need(all(type(result[k]) is bool for k in flags),'home_inspection_receipt_flags')
    need(result['status'] in ('inspection_match','inspection_mismatch','inspection_blocked','inspection_unknown')
         and result['phase'] in ('preflight','complete')
         and ('reason' not in result or result['reason'] in HOME_INSPECT_REASONS),'home_inspection_receipt_status')
    public,private=home_inspect_scope()
    need(result['checked_public_paths'] in ([],list(public)),'home_inspection_checked_scope')
    def observed(row,unreadable=False):
        need(isinstance(row,dict),'home_inspection_observed')
        if row.get('state')=='present':
            need(set(row)=={'state','sha256','size','mode'} and isinstance(row['sha256'],str)
                 and re.fullmatch('[a-f0-9]{64}',row['sha256']) and type(row['size']) is int and 0<=row['size']<=MAX_BYTES
                 and isinstance(row['mode'],str) and re.fullmatch('[0-7]{4}',row['mode']),'home_inspection_observed')
        else:
            need(row=={'state':'absent'} or unreadable and set(row)=={'state','reason'}
                 and row['state']=='unreadable' and row['reason'] in HOME_INSPECT_REASONS,'home_inspection_observed')
    def booleans(row,keys):
        need(isinstance(row,dict) and set(row)==set(keys) and all(type(row[k]) is bool for k in keys),
             'home_inspection_boolean_fields')
    mismatches=result['public_mismatches'];seen=set()
    need(isinstance(mismatches,list) and len(mismatches)<=len(public),'home_inspection_mismatch_scope')
    for row in mismatches:
        need(isinstance(row,dict) and set(row)=={'path','expected','observed','changed_row_fields','changed_stat_fields'}
             and isinstance(row['path'],str) and row['path'] in public and row['path'] not in seen,
             'home_inspection_mismatch_scope')
        seen.add(row['path']);observed(row['expected']);observed(row['observed'],True)
        expected_changes=[k for k in HOME_INSPECT_ROW_FIELDS if row['expected'].get(k)!=row['observed'].get(k)]
        need(row['changed_row_fields']==expected_changes and isinstance(row['changed_stat_fields'],list)
             and all(isinstance(k,str) for k in row['changed_stat_fields'])
             and row['changed_stat_fields']==[k for k in HOME_INSPECT_STAT_FIELDS if k in row['changed_stat_fields']]
             and (row['expected']!=row['observed'] or row['changed_stat_fields']),'home_inspection_comparison')
    private_checks=result['private_checks'];ancestors=result['ancestor_checks']
    need(isinstance(private_checks,dict) and set(private_checks)<=set(private.values())
         and isinstance(ancestors,dict) and set(ancestors)<=set(HOME_INSPECT_ANCESTORS),'home_inspection_comparison_scope')
    for row in private_checks.values():
        booleans(row,('readable','present','expected_present','matches_row','matches_identity'))
        need(not row['matches_row'] or row['readable'] and row['present']==row['expected_present'],
             'home_inspection_private_comparison')
    for row in ancestors.values():
        booleans(row,('matches_scope','matches_rows','matches_identity','stable'))
        need(row['matches_scope'] or not(row['matches_rows'] or row['matches_identity']),'home_inspection_ancestor_comparison')
    complete=result['status'] in ('inspection_match','inspection_mismatch')
    if complete:
        need(result['phase']=='complete' and 'reason' not in result and result['checked_public_paths']==list(public)
             and set(private_checks)==set(private.values()) and set(ancestors)==set(HOME_INSPECT_ANCESTORS)
             and all(result[k] for k in flags if k!='scope_matches')
             and all(row['readable'] for row in private_checks.values()) and all(row['stable'] for row in ancestors.values())
             and all(row['observed'].get('state')!='unreadable' for row in mismatches),'home_inspection_receipt_incomplete')
        match=(not mismatches and all(r['matches_row'] and r['matches_identity'] for r in private_checks.values())
               and all(r['matches_scope'] and r['matches_rows'] and r['matches_identity'] for r in ancestors.values()))
        need(result['scope_matches']==match and (result['status']=='inspection_match')==match,'home_inspection_receipt_incomplete')
    else:
        need(result['phase']=='preflight' and not result['scope_matches'] and 'reason' in result
             and result['parent_records_verified']==(result['status']=='inspection_unknown'),'home_inspection_receipt_incomplete')
    need(len(json.dumps(result,sort_keys=True).encode())<=HOME_INSPECT_RECEIPT_LIMIT,'home_inspection_receipt_limit')
    return complete


def home_inspect():
    work=Path(os.environ['RUNNER_TEMP'])/'search3-public-install';work.mkdir(mode=0o700)
    key,known=work/'key',work/'known'
    evidence={'status':'inspection_blocked','operation_id':HOME_INSPECT_OPERATION,'source_sha':HOME_SOURCE,
        'manifest_sha256':home_policy()['manifest'],'read_only':True,'phase':'identity','ssh_started':False,
        'failed_attempt':HOME_INSPECT_FAILED}
    complete=False
    try:
        identity=home_inspect_event_identity(json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text()),os.environ)
        evidence.update(identity,phase='admission');failed=home_inspect_admission(identity);evidence['phase']='artifact'
        dependency=tv_successor_evidence()
        program=home_inspect_remote_program(identity,failed,tv_successor=dependency);evidence['failed_evidence']=failed
        host,user=os.environ['PREVIEW_HOST'],os.environ['PREVIEW_USER']
        need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]{0,252}',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
        raw=os.environ['PREVIEW_KEY'].replace('\r','');need(0<len(raw)<=32768,'ssh_key')
        if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
        with key.open('x') as stream:stream.write(raw.rstrip()+'\n')
        key.chmod(0o600)
        scan=carrier.run(['ssh-keyscan','-T','15','-t','ed25519',host]);need(0<len(scan)<=8192,'host_key')
        known.write_bytes(scan);known.chmod(0o600)
        options=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','ConnectTimeout=15']
        need(home_inspect_admission(identity)==failed,'home_inspection_failed_changed')
        need(tv_successor_evidence()==dependency,'tv_successor_changed')
        evidence.update(status='inspection_unknown',phase='remote',ssh_started=True)
        response=carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'],input=program.encode(),timeout=180)
        need(len(response)<=HOME_INSPECT_RECEIPT_LIMIT,'home_inspection_receipt_limit');result=_tv_json(response)
        complete=valid_home_inspection_result(identity,result);evidence['remote']=result
        home_inspect_heads(identity);evidence.update(status=result['status'],phase='complete')
    except Exception as error:
        complete=False
        evidence['error_type']=type(error).__name__ if type(error) in (RuntimeError,ValueError,KeyError,OSError,subprocess.CalledProcessError,subprocess.TimeoutExpired) else 'HomeInspectionError'
    finally:
        key.unlink(missing_ok=True);known.unlink(missing_ok=True)
        (work/'evidence.json').write_text(json.dumps(evidence,sort_keys=True)+'\n')
    print(json.dumps(evidence,sort_keys=True));need(complete,'home_inspection_not_verified')


# A separately reviewed child of the retained HOME refusal and finite inspection.
# The original HOME/SITE policies, entry points and receipts remain immutable.
HOME_CHILD_PREFIX = '/continue-site-home-navigation'
HOME_CHILD_OPERATION = 'site-home-after-45078958662-20261123-v1'
HOME_CHILD_ROLLBACK_PREFIX = '/rollback-site-home-continuation'
HOME_CHILD_ROLLBACK_OPERATION = 'site-home-after-45078958662-rollback-20261123-v1'
HOME_CHILD_INSPECTION = {
    'operation_id': HOME_INSPECT_OPERATION,
    'control_source_sha': '4d5c1fabad2eac5cd7c2019291f006b2e3711247',
    'run_id': '45083269171', 'command_comment_id': 7087575127, 'job_id': 137396005183,
    'artifact_id': 14778199068,
    'artifact_sha256': 'c58906571eccc6ff7af50145db31c5f04afc086e97eaaf257f0e09d9e73d0fbf',
    'receipt_sha256': '5604b5727c082aee44035597e2e88ae6090ecfb32397a830a7daf1e76a935f30',
    'terminal_comment_id': 7087746947,
}
HOME_CHILD_PUBLIC_DRIFT = {
    'data/db-v1.php': {'state':'present','mode':'0644','size':3282,
        'sha256':'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13'},
    'data/price-confidence-v1.php': {'state':'present','mode':'0644','size':681,
        'sha256':'c8f607ab65f99d2991732dd539b00c4c34ec8cd7e9ac36077232335908e47759'},
    'data/price-intelligence-v1.php': {'state':'present','mode':'0644','size':5997,
        'sha256':'b49f7acf88b06bf3d204b845580f1b93a29e1ec70f4b27d0e1416551d1f3a174'},
    'data/price-segment-v1.php': {'state':'present','mode':'0644','size':1932,
        'sha256':'6db65d0a8259d447c2fc1f3acf2f8cf9b5a758a660ca4ee3fd41db6420133b6f'},
}
HOME_CHILD_REPAIR_PATHS = (
    '.github/workflows/collect-anytour-tours.yml',
    '.github/workflows/collect-hotel-details.yml',
    '.github/workflows/sync-anytour-catalog.yml',
    '.github/workflows/build-anytour-hot.yml',
    '.github/workflows/rollup-anytour-prices.yml',
    'tests/local_tv_capture_workflow_test.py',
    'tests/monitor_price_hook_workflow_test.py',
) + tuple('v2/'+p for p in HOME_CHILD_PUBLIC_DRIFT)
HOME_CHILD_REPAIR_WORKFLOWS = tuple('.github/workflows/'+p+'.yml' for p in (
    'security-guard','collect-anytour-tours','collect-hotel-details','sync-anytour-catalog',
    'build-anytour-hot','rollup-anytour-prices','int-server-executor','collect-seo-seasonal-live-evidence'))
HOME_CHILD_WRITER_WORKFLOWS = HOME_CHILD_REPAIR_PATHS[:5] + (
    '.github/workflows/bootstrap-anytour-data.yml',
    '.github/workflows/sync-top500-priority-hotels.yml',
    '.github/workflows/deploy-anytoour.yml',
)
# Exact original native CI and normal MAIN merge of the independently reviewed
# repair. Missing or malformed pins fail closed; no runtime argument supplies them.
HOME_CHILD_REPAIR = {'base_sha': 'cba860aaeefab5b6304da1fe8cf9d3b0896f7338',
 'blobs': {'.github/workflows/build-anytour-hot.yml': 'c5efaf7e334bfcd54d1ece0d02fd1de2ec32b1ca',
           '.github/workflows/collect-anytour-tours.yml': '170966037d30c40062bc17b622aa297dcd93e459',
           '.github/workflows/collect-hotel-details.yml': 'abb0f66c0655b899f143eaad8cde797cc76a6001',
           '.github/workflows/rollup-anytour-prices.yml': '0ab30a4345273e2c035298490ed4edaad3b29e19',
           '.github/workflows/sync-anytour-catalog.yml': 'f615211c6d35287c8414a452dd22a1a973d7d2eb',
           'tests/local_tv_capture_workflow_test.py': 'a346df7d48ae2db69e924605d8a43e81b772785f',
           'tests/monitor_price_hook_workflow_test.py': 'ad67c4a0006e02d1c478f307a5e2a03ad9c443c5',
           'v2/data/db-v1.php': '4ac8258ee4933c74ac20c2c9b771dfc88ab7537c',
           'v2/data/price-confidence-v1.php': '06f8eaacaaf21b3f99506548bd10ae3c30a42313',
           'v2/data/price-intelligence-v1.php': 'd15caf413a9f532fae026a0dcd54a07a17bb947e',
           'v2/data/price-segment-v1.php': '778c75808a302fcb9af7f25ab5b1bceebb604f74'},
 'checks': [{'job_id': 137407653749,
             'name': 'validate',
             'run_id': 45086952860,
             'workflow': '.github/workflows/build-anytour-hot.yml'},
            {'job_id': 137407653877,
             'name': 'validate',
             'run_id': 45086952901,
             'workflow': '.github/workflows/collect-anytour-tours.yml'},
            {'job_id': 137407653892,
             'name': 'validate',
             'run_id': 45086952898,
             'workflow': '.github/workflows/collect-hotel-details.yml'},
            {'job_id': 137407653315,
             'name': 'contract',
             'run_id': 45086952802,
             'workflow': '.github/workflows/collect-seo-seasonal-live-evidence.yml'},
            {'job_id': 137407653885,
             'name': 'contract',
             'run_id': 45086952851,
             'workflow': '.github/workflows/int-server-executor.yml'},
            {'job_id': 137407654555,
             'name': 'validate',
             'run_id': 45086953173,
             'workflow': '.github/workflows/rollup-anytour-prices.yml'},
            {'job_id': 137407653596,
             'name': 'guard',
             'run_id': 45086952798,
             'workflow': '.github/workflows/security-guard.yml'},
            {'job_id': 137407653735,
             'name': 'validate',
             'run_id': 45086952829,
             'workflow': '.github/workflows/sync-anytour-catalog.yml'}],
 'merge_sha': '2f1e50daec0b6f9249bc76c2ec368ca52b92d21e',
 'pr': 5161,
 'source_sha': 'b9173dfb763e20a4ea3dcc6f16d460eae797c69d',
 'tree_sha': '4feaac9449c89b5605d7e54c7e4664603b68ee56'}
HOME_CHILD_REASONS = HOME_REASONS + (
    'home_child_scope','home_child_namespace','home_child_reconciliation',
    'home_child_projection','home_child_private_identity','home_child_reconciliation_changed',
    'home_child_reconciliation_record','home_child_reconciliation_missing',
)


def home_child_pending_pins():
    value=HOME_CHILD_REPAIR
    need(isinstance(value,dict) and set(value)=={'pr','base_sha','source_sha','merge_sha','tree_sha','blobs','checks'},
         'home_child_repair_not_pinned')
    need(type(value['pr']) is int and value['pr']>0
         and all(isinstance(value[k],str) and re.fullmatch('[a-f0-9]{40}',value[k]) for k in
                 ('base_sha','source_sha','merge_sha','tree_sha')),'home_child_repair_pins')
    need(isinstance(value['blobs'],dict) and set(value['blobs'])==set(HOME_CHILD_REPAIR_PATHS)
         and all(isinstance(v,str) and re.fullmatch('[a-f0-9]{40}',v) for v in value['blobs'].values()),
         'home_child_repair_blobs')
    checks=value['checks']
    need(isinstance(checks,list) and len(checks)==len(HOME_CHILD_REPAIR_WORKFLOWS)
         and all(isinstance(c,dict) and isinstance(c.get('workflow'),str) for c in checks)
         and {c['workflow'] for c in checks}==set(HOME_CHILD_REPAIR_WORKFLOWS),
         'home_child_repair_checks')
    need(all(isinstance(c,dict) and set(c)=={'workflow','run_id','job_id','name'}
             and type(c['run_id']) is int and c['run_id']>0 and type(c['job_id']) is int and c['job_id']>0
             and isinstance(c['name'],str) and 0<len(c['name'])<=120 for c in checks)
         and len({c['run_id'] for c in checks})==len(checks)
         and len({c['job_id'] for c in checks})==len(checks),'home_child_repair_checks')


def home_child_provenance():
    home_child_pending_pins()
    return {'failed':dict(HOME_INSPECT_FAILED),'inspection':dict(HOME_CHILD_INSPECTION),
            'repair':json.loads(json.dumps(HOME_CHILD_REPAIR)),
            'original_home_policy_sha256':home_policy()['manifest'],
            'public_drift':dict(HOME_CHILD_PUBLIC_DRIFT),
            'public_allowed_stat_fields':['st_mtime_ns','st_ctime_ns'],
            'private_checks':{'public_config':{'allowed_stat_fields':['st_ctime_ns']}}}


def home_child_policy():
    original=home_policy()
    value={k:v for k,v in original.items() if k!='manifest'}
    value.update(operation=HOME_CHILD_OPERATION,continuation=home_child_provenance())
    return value|{'manifest':sha(json.dumps(value,sort_keys=True,separators=(',',':')).encode())}


def home_child_inspection_value(value):
    pin=HOME_CHILD_INSPECTION
    need(isinstance(value,dict) and sha((json.dumps(value,sort_keys=True)+'\n').encode())==pin['receipt_sha256'],
         'home_child_inspection_digest')
    identity={k:pin[k] for k in ('control_source_sha','run_id','command_comment_id')}
    fixed={**identity,'status':'inspection_mismatch','operation_id':HOME_INSPECT_OPERATION,
           'source_sha':HOME_SOURCE,'manifest_sha256':home_policy()['manifest'],'read_only':True,
           'phase':'complete','ssh_started':True,'failed_attempt':HOME_INSPECT_FAILED}
    need(set(value)==set(fixed)|{'failed_evidence','remote'}
         and all(type(value.get(k)) is type(v) and value[k]==v for k,v in fixed.items()),'home_child_inspection')
    parent=home_inspect_failed_value(value['failed_evidence'])
    result=value['remote'];need(valid_home_inspection_result(identity,result),'home_child_inspection')
    expected=[{'path':p,'expected':row,'observed':row,'changed_row_fields':[],
               'changed_stat_fields':['st_mtime_ns','st_ctime_ns']} for p,row in HOME_CHILD_PUBLIC_DRIFT.items()]
    private={label:{'readable':True,'present':label in ('anex_private_config','public_config'),
                    'expected_present':label in ('anex_private_config','public_config'),
                    'matches_row':True,'matches_identity':label!='public_config'}
             for label in ('anex_private_config','public_config','site_config','local_config','next_config')}
    need(result['public_mismatches']==expected and result['private_checks']==private
         and all(all(row.values()) for row in result['ancestor_checks'].values()),'home_child_inspection_scope')
    return parent


def home_child_inspection_evidence(raw):
    pin=HOME_CHILD_INSPECTION
    need(sha(raw)==pin['artifact_sha256'],'home_child_inspection_artifact')
    with zip_files(raw) as archive:
        need(archive.namelist()==['evidence.json'],'home_child_inspection_artifact')
        data=archive.read('evidence.json');need(sha(data)==pin['receipt_sha256'],'home_child_inspection_digest')
    value=_tv_json(data);home_child_inspection_value(value);return value


def home_child_event_identity(event,env,rollback=False):
    need(type(rollback) is bool,'mode');control=env.get('GITHUB_SHA','')
    body=event.get('comment',{}).get('body','');synthetic=json.loads(json.dumps(event))
    if rollback:
        match=re.fullmatch(re.escape(HOME_CHILD_ROLLBACK_PREFIX+' '+control+' ')
                           +r'([1-9][0-9]{0,19}) '+re.escape(HOME_CHILD_ROLLBACK_OPERATION),body)
        need(match is not None,'command');original_run=match[1]
        synthetic['comment']['body']=f'{HOME_ROLLBACK_PREFIX} {control} {original_run} {HOME_ROLLBACK_OPERATION}'
        identity,original_run=home_event_identity(synthetic,env,True)
        need(original_run not in (HOME_INSPECT_FAILED['run_id'],HOME_CHILD_INSPECTION['run_id']),
             'home_child_original_identity')
    else:
        need(body==f'{HOME_CHILD_PREFIX} {control} {HOME_CHILD_OPERATION}','command')
        synthetic['comment']['body']=f'{HOME_PREFIX} {control} {HOME_OPERATION}'
        identity=home_event_identity(synthetic,env)
    need(identity['run_id'] not in (HOME_INSPECT_FAILED['run_id'],HOME_CHILD_INSPECTION['run_id']),
         'home_child_identity')
    return (identity,original_run) if rollback else identity


def home_child_fixed_proof(pin,command,conclusion,mode):
    need(pin in (HOME_INSPECT_FAILED,HOME_CHILD_INSPECTION),'home_child_fixed_pin')
    run=api('/actions/runs/'+pin['run_id']);created=_tv_time(command.get('created_at'))
    need(type(run.get('id')) is int and run.get('id')==int(pin['run_id']) and run.get('head_sha')==pin['control_source_sha']
         and run.get('head_branch')=='main' and run.get('path')==WORKFLOW and run.get('event')=='issue_comment'
         and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')==conclusion
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff'
         and created<=_tv_time(run.get('created_at'))<=created+300,'home_child_fixed_run')
    jobs=[j for j in pages('/actions/runs/'+pin['run_id']+'/attempts/1/jobs','jobs') if j.get('name')=='public-install']
    need(len(jobs)==1 and type(jobs[0].get('id')) is int and jobs[0].get('id')==pin['job_id']
         and type(jobs[0].get('run_id')) is int and jobs[0].get('run_id')==int(pin['run_id'])
         and jobs[0].get('status')=='completed' and jobs[0].get('conclusion')==conclusion,'home_child_fixed_job')
    steps=[s for s in jobs[0].get('steps',[]) if s.get('name','').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps)==1 and steps[0].get('name')=='Run python3 scripts/deploy/search3_public_install.py '+mode
         and steps[0].get('status')=='completed' and steps[0].get('conclusion')==conclusion
         and created<=_tv_time(run.get('created_at'))<=_tv_time(run.get('run_started_at'))
         <=_tv_time(steps[0].get('started_at'))<=_tv_time(steps[0].get('completed_at'))<=_tv_time(run.get('updated_at')),
         'home_child_fixed_step')
    artifact=api('/actions/artifacts/'+str(pin['artifact_id']))
    need(type(artifact.get('id')) is int and artifact.get('id')==pin['artifact_id']
         and artifact.get('name')=='search3-public-install-'+pin['run_id']+'-1'
         and artifact.get('expired') is False and artifact.get('digest')=='sha256:'+pin['artifact_sha256']
         and artifact.get('workflow_run',{}).get('id')==int(pin['run_id'])
         and artifact.get('workflow_run',{}).get('head_sha')==pin['control_source_sha'],'home_child_fixed_artifact')
    reviewed_control(pin['control_source_sha'])


def home_child_writer_quiescence():
    # This finite read does not acquire a new lock or poll until work completes.
    # Existing writer groups differ: any active original writer is a blocker.
    seen=set()
    for status in ('queued','in_progress','waiting','requested','pending'):
        runs=pages('/actions/runs?status='+status,'workflow_runs')
        need(len(runs)<=1000,'home_child_writer_history_limit')
        for run in runs:
            if run.get('path') not in HOME_CHILD_WRITER_WORKFLOWS:continue
            need(type(run.get('id')) is int and run['id']>0,'home_child_writer_run')
            seen.add(run['id'])
    need(not seen,'home_child_writer_active')


def home_child_repair_admission(identity):
    home_child_pending_pins();pin=HOME_CHILD_REPAIR
    pr=api('/pulls/'+str(pin['pr']))
    need(pr.get('state')=='closed' and pr.get('merged') is True and pr.get('merge_commit_sha')==pin['merge_sha']
         and pr.get('head',{}).get('sha')==pin['source_sha'] and pr.get('base',{}).get('ref')=='main'
         and pr.get('head',{}).get('repo',{}).get('full_name')==pr.get('base',{}).get('repo',{}).get('full_name')==REPO,
         'home_child_repair_pr')
    source=api('/git/commits/'+pin['source_sha']);merged=api('/git/commits/'+pin['merge_sha'])
    need(source.get('tree',{}).get('sha')==merged.get('tree',{}).get('sha')==pin['tree_sha']
         and [p.get('sha') for p in merged.get('parents',[])]==[pin['base_sha'],pin['source_sha']],
         'home_child_repair_tree')
    relation=api('/compare/'+pin['merge_sha']+'...'+identity['control_source_sha'])
    need(relation.get('status') in ('identical','ahead') and relation.get('behind_by')==0
         and relation.get('merge_base_commit',{}).get('sha')==pin['merge_sha'],'home_child_repair_ancestry')
    for commit in (pin['source_sha'],identity['control_source_sha']):
        for path,blob in pin['blobs'].items():
            row=api('/contents/'+path+'?ref='+commit)
            need(row.get('type')=='file' and row.get('sha')==blob,'home_child_repair_blob')
            if path.startswith('v2/data/'):
                need(row.get('encoding')=='base64','home_child_repair_helper')
                raw=base64.b64decode(row.get('content','').replace('\n',''),validate=True)
                expected=HOME_CHILD_PUBLIC_DRIFT[path[3:]]
                need(len(raw)==expected['size'] and sha(raw)==expected['sha256'],'home_child_repair_helper')
    for check in pin['checks']:
        run=api('/actions/runs/'+str(check['run_id']))
        need(type(run.get('id')) is int and run.get('id')==check['run_id'] and run.get('head_sha')==pin['source_sha']
             and run.get('path')==check['workflow'] and run.get('event')=='pull_request'
             and type(run.get('run_attempt')) is int and run['run_attempt']==1
             and run.get('status')=='completed' and run.get('conclusion')=='success'
             and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff'
             and run.get('repository',{}).get('full_name')==run.get('head_repository',{}).get('full_name')==REPO,
             'home_child_repair_run')
        jobs=pages('/actions/runs/'+str(check['run_id'])+'/attempts/1/jobs','jobs')
        matches=[j for j in jobs if j.get('id')==check['job_id'] and j.get('name')==check['name']]
        need(len(matches)==1 and type(matches[0].get('id')) is int and type(matches[0].get('run_id')) is int
             and matches[0].get('run_id')==check['run_id']
             and matches[0].get('status')=='completed' and matches[0].get('conclusion')=='success',
             'home_child_repair_job')
    home_child_writer_quiescence()


def home_child_admission(identity):
    home_child_pending_pins();home_inspect_heads(identity);reviewed_control(identity['control_source_sha'])
    home_child_repair_admission(identity)
    gate=api('/contents/.github/workflows/deploy-anytoour.yml?ref='+identity['control_source_sha'])
    need(gate.get('type')=='file' and gate.get('encoding')=='base64'
         and sha(base64.b64decode(gate['content']))==SITE_PUBLISHER_GUARD_SHA,'publisher_guard')
    checks=pages('/commits/'+HOME_SOURCE+'/check-runs','check_runs')
    for name in HOME_CHECKS:
        latest=max((c for c in checks if c.get('name')==name and c.get('head_sha')==HOME_SOURCE),
                   key=lambda c:c['id'],default={})
        need(green(latest),'home_source_ci')
    run=api('/actions/runs/'+str(HOME_BUILD_RUN));artifact=api('/actions/artifacts/'+str(HOME_ARTIFACT))
    need(run.get('id')==HOME_BUILD_RUN and run.get('head_sha')==HOME_SOURCE and run.get('head_branch')==HOME_SOURCE_BRANCH
         and run.get('path')=='.github/workflows/search3-visual-migration.yml' and run.get('event')=='pull_request'
         and type(run.get('run_attempt')) is int and run['run_attempt']==1 and run.get('status')=='completed'
         and run.get('conclusion')=='success' and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff'
         and run.get('repository',{}).get('full_name')==run.get('head_repository',{}).get('full_name')==REPO,'home_build')
    need(artifact.get('id')==HOME_ARTIFACT and artifact.get('name')=='search3-public-live-'+HOME_SOURCE
         and artifact.get('expired') is False and artifact.get('digest')=='sha256:'+HOME_ARTIFACT_SHA
         and artifact.get('workflow_run',{}).get('id')==HOME_BUILD_RUN
         and artifact.get('workflow_run',{}).get('head_sha')==HOME_SOURCE,'home_artifact')
    home_parent_admission();comments=pages('/issues/4709/comments')
    for pin,prefix,operation,conclusion,mode,status in (
        (HOME_INSPECT_FAILED,HOME_PREFIX,HOME_OPERATION,'failure','home','blocked_no_replay'),
        (HOME_CHILD_INSPECTION,HOME_INSPECT_PREFIX,HOME_INSPECT_OPERATION,'success','home-inspect','inspection_mismatch')):
        old={k:pin[k] for k in ('control_source_sha','run_id','command_comment_id')}
        _unique_update_command(comments,prefix,operation,old)
        command=next(c for c in comments if c['id']==old['command_comment_id'])
        terminal=next((c for c in comments if c['id']==pin['terminal_comment_id']),{})
        required=[str(pin[k]) for k in ('run_id','control_source_sha','command_comment_id','job_id',
                                      'artifact_id','artifact_sha256','receipt_sha256')]+[status,'NO REPLAY']
        need(command.get('created_at')==command.get('updated_at')
             and terminal.get('created_at')==terminal.get('updated_at')
             and terminal.get('user',{}).get('id')==226193297 and terminal.get('author_association')=='OWNER'
             and all(t in terminal.get('body','') for t in required)
             and _tv_time(command.get('created_at'))<=_tv_time(terminal.get('created_at')),'home_child_fixed_terminal')
        home_child_fixed_proof(pin,command,conclusion,mode)
    _unique_update_command(comments,HOME_CHILD_PREFIX,HOME_CHILD_OPERATION,identity)
    command=next(c for c in comments if c['id']==identity['command_comment_id'])
    need(command.get('created_at')==command.get('updated_at')
         and _tv_time(command.get('created_at'))>=max(_tv_time(c['created_at']) for c in comments
               if c['id'] in (HOME_INSPECT_FAILED['terminal_comment_id'],HOME_CHILD_INSPECTION['terminal_comment_id'])),
         'home_child_command_time')
    need(not any(c.get('body','').strip().split()[:1] in ([HOME_ROLLBACK_PREFIX],[HOME_CHILD_ROLLBACK_PREFIX])
                 and any(op in c.get('body','').split()[1:] for op in (HOME_ROLLBACK_OPERATION,HOME_CHILD_ROLLBACK_OPERATION))
                 for c in comments),'home_child_rollback_present')
    inspection=home_child_inspection_evidence(download(HOME_CHILD_INSPECTION['artifact_id'],HOME_CHILD_INSPECTION['artifact_sha256']))
    failed=home_inspect_failed_evidence(download(HOME_INSPECT_FAILED['artifact_id'],HOME_INSPECT_FAILED['artifact_sha256']))
    need(inspection['failed_evidence']==failed,'home_child_failure_changed')
    home_inspect_heads(identity)
    return inspection


HOME_CHILD_COMMON_REMOTE = r'''
# These bindings exist only in this child's generated program. Historical
# entry points continue to execute their byte-identical original definitions.
_home_child_parent_impl=home_handoff_parent
_home_child_original_impl=home_original_records
_home_child_ancestor_impl=home_check_ancestors
_home_child_strict_guard=refactor_guard_from_snapshot
_home_child_parent_context=False
_home_child_binding=None
_home_child_record_store=None
_home_child_record_bound=None
HOME_PREPARED_PATHS=HOME_PREPARED_PATHS+('reconciliation.json',)

def home_child_namespaces():
 for operation in (CHILD_ORIGINAL_OPERATION,CHILD_ORIGINAL_ROLLBACK,CHILD_INSPECTION_OPERATION):
  with base.parent(operation) as (fd,name):
   try:os.stat(name,dir_fd=fd,follow_symlinks=False)
   except FileNotFoundError:continue
   raise RuntimeError('home_child_namespace')
 if not CHILD_ROLLBACK:handoff_absent(HOME_ROLLBACK_OPERATION)

def home_child_origin():
 rows=site_read_records(handed,('snapshot/manifest.json',))
 raw=rows['snapshot/manifest.json'][2]
 require(digest(raw)==HOME_POLICY['parent']['snapshot_manifest_sha256'],'home_child_reconciliation')
 snapshot=json.loads(raw)
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_refactor','before','guarded','ancestors'}
         and snapshot['source']==HANDOFF_POLICY['source'] and snapshot['manifest']==HANDOFF_POLICY['manifest']
         and snapshot['parent_refactor']==REFACTOR_ORIGINAL,'home_child_reconciliation')
 private={label:path for path,label in private_labels().items()}
 require(set(CHILD_PUBLIC_DRIFT)<=set(snapshot['guarded']) and 'public_config' in private
         and private['public_config'] in snapshot['guarded'],'home_child_scope')
 paths=tuple(CHILD_PUBLIC_DRIFT)+(private['public_config'],)
 require(len(set(paths))==5 and not set(paths)&set(HOME_POLICY['order']),'home_child_scope')
 return {p:snapshot['guarded'][p] for p in paths},private['public_config']

def home_child_validate_binding(value,accepted_scope=None):
 origin,private_path=home_child_origin()
 require(isinstance(value,dict) and set(value)=={'schema_version','policy_sha256','parent_snapshot_manifest_sha256',
         'provenance','original','accepted'} and type(value['schema_version']) is int and value['schema_version']==1
         and value['policy_sha256']==HOME_POLICY['manifest'] and value['provenance']==CHILD_PROVENANCE
         and value['parent_snapshot_manifest_sha256']==HOME_POLICY['parent']['snapshot_manifest_sha256']
         and value['original']==origin and isinstance(value['accepted'],dict)
         and set(value['accepted'])==set(origin),'home_child_reconciliation')
 for path,old in origin.items():
  current=value['accepted'][path]
  require(isinstance(old,list) and len(old)==2 and isinstance(old[0],dict) and old[0].get('state')=='present'
          and isinstance(current,list) and len(current)==2 and current[0]==old[0],'home_child_projection')
  prior=checked_stamp(old[1],old[0]);now=checked_stamp(current[1],current[0])
  allowed=(8,) if path==private_path else (5,8)
  require(all(prior[i]==now[i] for i in range(9) if i not in allowed),
          'home_child_private_identity' if path==private_path else 'home_child_projection')
  if path!=private_path:
   require(old[0]==CHILD_PUBLIC_DRIFT[path] and all(prior[i]!=now[i] for i in allowed),'home_child_projection')
  if accepted_scope is not None:
   require(path in accepted_scope and accepted_scope[path]==current,'home_child_reconciliation_record')
 return private_path

def home_child_bind(saved):
 global _home_child_binding
 origin,private_path=home_child_origin()
 require(all(saved.get(p)==row for p,row in origin.items()),'home_child_projection')
 if _home_child_binding is None:
  require(not CHILD_ROLLBACK,'home_child_reconciliation_missing')
  observed={p:public.read(p)[:2] for p in origin}
  binding={'schema_version':1,'policy_sha256':HOME_POLICY['manifest'],
           'parent_snapshot_manifest_sha256':HOME_POLICY['parent']['snapshot_manifest_sha256'],
           'provenance':CHILD_PROVENANCE,'original':origin,'accepted':refactor_stamp_rows(observed)}
  home_child_validate_binding(binding)
  _home_child_binding=binding
 else:home_child_validate_binding(_home_child_binding)
 result['reconciliation_sha256']=digest(canonical(_home_child_binding))
 result['reconciliation_verified']=True
 result['private_checks']={'public_config':True}
 projected=dict(saved)
 projected.update(_home_child_binding['accepted'])
 return projected

def refactor_guard_from_snapshot(saved):
 # No repeated acceptance of newer metadata: only the first preflight can bind
 # the five finite transitions. Every subsequent call uses that saved tuple.
 projected=home_child_bind(saved) if _home_child_parent_context else saved
 return _home_child_strict_guard(projected)

def home_child_assert_record():
 if _home_child_record_store is not None:
  require(site_read_records(_home_child_record_store,('reconciliation.json',))['reconciliation.json']==_home_child_record_bound,
          'home_child_reconciliation_changed')

def home_handoff_parent():
 global _home_child_parent_context
 home_child_namespaces();_home_child_parent_context=True
 try:value=_home_child_parent_impl()
 finally:_home_child_parent_context=False
 home_child_assert_record()
 return value

def home_check_ancestors():
 home_child_namespaces();home_child_assert_record()
 _home_child_ancestor_impl()
 home_child_assert_record()

def home_child_persist_reconciliation(store):
 global _home_child_record_store,_home_child_record_bound
 require(_home_child_binding is not None and result['reconciliation_verified'],'home_child_reconciliation_missing')
 home_check_ancestors();home_child_validate_binding(_home_child_binding)
 raw=canonical(_home_child_binding)
 store.create('reconciliation.json',raw)
 bound=site_read_records(store,('reconciliation.json',))['reconciliation.json']
 require(bound[2]==raw and digest(raw)==result['reconciliation_sha256'],'home_child_reconciliation_record')
 _home_child_record_store=store;_home_child_record_bound=bound
 result['lineage_persisted']=True

def home_original_records():
 global _home_child_binding,_home_child_record_store,_home_child_record_bound
 records=site_read_records(homed,('reconciliation.json','snapshot/manifest.json'))
 raw=records['reconciliation.json'][2];value=json.loads(raw)
 snapshot_raw=records['snapshot/manifest.json'][2];snapshot=json.loads(snapshot_raw)
 require(raw==canonical(value) and digest(raw)==HOME_ACCEPTED['reconciliation_sha256']
         and digest(snapshot_raw)==HOME_ACCEPTED['snapshot_manifest_sha256'],'home_child_reconciliation_record')
 home_child_validate_binding(value,snapshot.get('guarded'))
 _home_child_binding=value;_home_child_record_store=homed
 _home_child_record_bound=records['reconciliation.json']
 result.update(reconciliation_sha256=digest(raw),reconciliation_verified=True,lineage_persisted=True,
               private_checks={'public_config':True})
 # The unchanged original reader compares every current protected stamp to the
 # child's already sealed snapshot. It never applies a new projection here.
 checked=_home_child_original_impl();home_child_assert_record()
 return checked
'''


def home_child_runtime_identity(identity):
    update_runtime_identity(identity)
    need(identity['run_id'] not in (HOME_INSPECT_FAILED['run_id'],HOME_CHILD_INSPECTION['run_id'])
         and identity['command_comment_id'] not in (HOME_INSPECT_FAILED['command_comment_id'],HOME_CHILD_INSPECTION['command_comment_id']),
         'home_child_identity')


def _home_child_program_prefix(policy,identity,parent_value,inspection_value,tv_successor,rollback=False):
    need(policy==home_child_policy(),'home_child_policy');home_child_runtime_identity(identity)
    need(parent_value==home_child_inspection_value(inspection_value),'home_child_parent_evidence')
    prefix=_home_program_prefix(home_policy(),identity,parent_value,tv_successor)
    return (prefix+'\nHOME_POLICY='+repr(policy)+'\nHOME_ROLLBACK_OPERATION='+repr(HOME_CHILD_ROLLBACK_OPERATION)
        +'\nCHILD_PROVENANCE='+repr(home_child_provenance())+'\nCHILD_PUBLIC_DRIFT='+repr(HOME_CHILD_PUBLIC_DRIFT)
        +'\nCHILD_ORIGINAL_OPERATION='+repr(HOME_OPERATION)+'\nCHILD_ORIGINAL_ROLLBACK='+repr(HOME_ROLLBACK_OPERATION)
        +'\nCHILD_INSPECTION_OPERATION='+repr(HOME_INSPECT_OPERATION)+'\nCHILD_ROLLBACK='+repr(rollback)
        +'\ndef re_safe(value): return value in '+repr(HOME_CHILD_REASONS)+'\n'+HOME_CHILD_COMMON_REMOTE)


def _home_child_remote_body(rollback=False):
    # Count-checked fixed custody hooks in the unchanged, exhaustively exercised
    # HOME transaction. No expression, path or replacement comes from a caller.
    need(type(rollback) is bool,'mode')
    body=HOME_ROLLBACK_REMOTE if rollback else HOME_REMOTE
    anchor=('public=base=original=updated=published=refactored=handed=homed=private=None;reserved=False' if rollback else
            'public=base=original=updated=published=refactored=handed=private=None;reserved=False;switched=[];owned={};prior={};prepared=None;reservation=None')
    need(body.count(anchor)==1,'home_child_template')
    initial="result.update(continuation=CHILD_PROVENANCE,reconciliation_sha256=None,reconciliation_verified=False,lineage_persisted=False,private_checks={'public_config':False})\n"
    body=body.replace(anchor,initial+anchor,1)
    if not rollback:
        anchor=" private.create('reservation.json',canonical(reservation));result['phase']='snapshot'\n"
        need(body.count(anchor)==1,'home_child_template')
        body=body.replace(anchor,anchor+' home_child_persist_reconciliation(private)\n',1)
    ast.parse(body);return body


def home_child_remote_program(policy,files,identity,parent_value,inspection_value,*,tv_successor=None):
    prefix=_home_child_program_prefix(policy,identity,parent_value,inspection_value,tv_successor)
    need(isinstance(files,dict) and set(files)==set(HOME_ORDER),'home_payload')
    for path in HOME_ORDER:
        raw=base64.b64decode(files[path],validate=True)
        need(0<len(raw)<=MAX_BYTES and HOME_TARGETS[path]['after']==
             {'state':'present','mode':'0644','size':len(raw),'sha256':sha(raw)},'home_payload_bytes')
    program=prefix+'\nPAYLOAD='+repr(files)+'\n'+_home_child_remote_body()
    ast.parse(program);return program


def home_child_rollback_remote_program(policy,identity,original,accepted,parent_value,inspection_value,*,tv_successor=None):
    prefix=_home_child_program_prefix(policy,identity,parent_value,inspection_value,tv_successor,True)
    home_child_runtime_identity(original)
    need(identity['run_id']!=original['run_id'] and identity['command_comment_id']!=original['command_comment_id']
         and valid_home_child_result(original,accepted),'home_child_rollback_evidence')
    program=(prefix+'\nHOME_ORIGINAL='+repr(original)+'\nHOME_ACCEPTED='+repr(accepted)
             +'\n'+_home_child_remote_body(True))
    ast.parse(program);return program


def _home_child_base_result(value,rollback=False):
    extra={'continuation','reconciliation_sha256','reconciliation_verified','lineage_persisted','private_checks'}
    result={k:v for k,v in value.items() if k not in extra}
    result.update(operation_id=HOME_ROLLBACK_OPERATION if rollback else HOME_OPERATION,
                  manifest_sha256=home_policy()['manifest'])
    if result.get('reason') in HOME_CHILD_REASONS and result.get('reason') not in HOME_REASONS:
        result['reason']='operation_failed'
    return result


def valid_home_child_result(identity,result,original=None,accepted=None):
    home_child_runtime_identity(identity);rollback=original is not None
    policy=home_child_policy()
    need(isinstance(result,dict) and result.get('operation_id')==
         (HOME_CHILD_ROLLBACK_OPERATION if rollback else HOME_CHILD_OPERATION)
         and result.get('manifest_sha256')==policy['manifest']
         and result.get('continuation')==home_child_provenance(),'home_child_receipt_identity')
    additions={'continuation','reconciliation_sha256','reconciliation_verified','lineage_persisted','private_checks'}
    need(additions<=set(result) and type(result['reconciliation_verified']) is bool
         and type(result['lineage_persisted']) is bool
         and isinstance(result['private_checks'],dict) and set(result['private_checks'])=={'public_config'}
         and type(result['private_checks']['public_config']) is bool,'home_child_receipt_shape')
    digest=result['reconciliation_sha256'];verified=result['reconciliation_verified']
    need((isinstance(digest,str) and re.fullmatch('[a-f0-9]{64}',digest) if verified else digest is None)
         and result['private_checks']['public_config']==verified
         and (not result['lineage_persisted'] or verified),'home_child_receipt_reconciliation')
    need('reason' not in result or result['reason'] in HOME_CHILD_REASONS,'home_child_receipt_reason')
    if rollback:
        home_child_runtime_identity(original)
        need(identity['run_id']!=original['run_id'] and identity['command_comment_id']!=original['command_comment_id']
             and valid_home_child_result(original,accepted),'home_child_rollback_evidence')
    base=_home_child_base_result(result,rollback)
    complete=valid_home_result(identity,base,original,_home_child_base_result(accepted) if rollback else None)
    if complete or result['written_files']:
        need(verified and result['lineage_persisted'],'home_child_receipt_incomplete')
    if rollback and verified:
        need(digest==accepted['reconciliation_sha256'],'home_child_receipt_reconciliation')
    need(len(json.dumps(result,sort_keys=True).encode())<=32768,'receipt_limit')
    return complete


def _home_child_source_value(value,original,*,recovery=False,rollback_original=None,accepted=None):
    home_child_runtime_identity(original);rollback=rollback_original is not None
    status=('home_navigation_rolled_back_verified' if rollback else
            'rolled_back_no_replay' if recovery else 'home_navigation_updated_verified')
    fixed={**original,'operation_id':HOME_CHILD_ROLLBACK_OPERATION if rollback else HOME_CHILD_OPERATION,
           'source_sha':HOME_SOURCE,'manifest_sha256':home_child_policy()['manifest'],
           'read_only':False,'phase':'complete','ssh_started':True,'status':status,
           'parent_handoff':home_parent_identity(),'continuation':home_child_provenance()}
    if rollback:fixed.update(original_home=rollback_original,original_home_run_id=rollback_original['run_id'])
    need(isinstance(value,dict) and set(value)==set(fixed)|{'parent_evidence','inspection_evidence','remote'}
         and all(type(value.get(k)) is type(v) and value[k]==v for k,v in fixed.items()),'home_child_source_evidence')
    parent=home_child_inspection_value(value['inspection_evidence'])
    need(parent==value['parent_evidence'],'home_child_parent_evidence');home_parent_value(parent)
    success=valid_home_child_result(original,value['remote'],rollback_original,accepted)
    need(success or recovery and value['remote']['status']=='rolled_back_no_replay','home_child_source_evidence')
    return home_child_policy(),value['remote'],parent,value['inspection_evidence']


def home_child_source_evidence(raw,original):
    with zip_files(raw) as archive:
        need(archive.namelist()==['evidence.json'],'home_child_source_artifact')
        data=archive.read('evidence.json');need(len(data)<=262144,'home_child_source_artifact')
        value=_tv_json(data)
    return _home_child_source_value(value,original)


def home_child_commands(comments):
    result={}
    for name,prefix,operation in (('install',HOME_CHILD_PREFIX,HOME_CHILD_OPERATION),
                                   ('rollback',HOME_CHILD_ROLLBACK_PREFIX,HOME_CHILD_ROLLBACK_OPERATION)):
        rows=[c for c in comments if c.get('body','').strip().split()[:1]==[prefix]
              and operation in c.get('body','').split()[1:]]
        need(len(rows)<=1,'home_child_duplicate_command')
        if not rows:continue
        row=rows[0]
        expression=re.escape(prefix)+r' ([a-f0-9]{40}) '+(r'([1-9][0-9]{0,19}) ' if name=='rollback' else '')+re.escape(operation)
        need(re.fullmatch(expression,row.get('body','')) is not None and row.get('user',{}).get('id')==226193297
             and row.get('author_association')=='OWNER' and type(row.get('id')) is int and row['id']>0
             and row.get('created_at')==row.get('updated_at'),'home_child_command')
        _tv_time(row.get('created_at'));result[name]=row
    need('rollback' not in result or 'install' in result,'home_child_original_missing')
    return result


def _home_child_native_proof(command,run,value,artifact):
    control=command['body'].split()[1];created=_tv_time(command['created_at'])
    identity={'control_source_sha':control,'run_id':str(run.get('id')),'command_comment_id':command['id']}
    _home_child_source_value(value,identity)
    need(type(run.get('id')) is int and run['id']>0
         and run.get('head_sha')==control and run.get('head_branch')=='main' and run.get('path')==WORKFLOW
         and run.get('event')=='issue_comment' and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')=='success'
         and created<=_tv_time(run.get('created_at'))<=created+300
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff',
         'home_child_native_run')
    reviewed_control(control)
    jobs=[j for j in pages('/actions/runs/'+identity['run_id']+'/attempts/1/jobs','jobs') if j.get('name')=='public-install']
    need(len(jobs)==1 and type(jobs[0].get('id')) is int and jobs[0]['id']>0
         and type(jobs[0].get('run_id')) is int and jobs[0].get('run_id')==run['id'] and jobs[0].get('status')=='completed'
         and jobs[0].get('conclusion')=='success','home_child_native_job')
    steps=[s for s in jobs[0].get('steps',[]) if s.get('name','').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps)==1 and steps[0].get('name')=='Run python3 scripts/deploy/search3_public_install.py home-continue'
         and steps[0].get('status')=='completed' and steps[0].get('conclusion')=='success'
         and created<=_tv_time(run.get('created_at'))<=_tv_time(run.get('run_started_at'))
         <=_tv_time(steps[0].get('started_at'))<=_tv_time(steps[0].get('completed_at'))<=_tv_time(run.get('updated_at')),
         'home_child_native_step')
    need(type(artifact.get('id')) is int and artifact['id']>0
         and type(artifact.get('workflow_run',{}).get('id')) is int
         and artifact.get('name')=='search3-public-install-'+identity['run_id']+'-1' and artifact.get('expired') is False
         and artifact.get('workflow_run',{}).get('id')==run['id'] and artifact.get('workflow_run',{}).get('head_sha')==control
         and re.fullmatch(r'sha256:[a-f0-9]{64}',artifact.get('digest','')),'home_child_native_artifact')
    return identity


def home_child_rollback_admission(identity,original_run):
    home_child_pending_pins();rollback_main(identity);reviewed_control(identity['control_source_sha'])
    need(isinstance(original_run,str) and re.fullmatch('[1-9][0-9]{0,19}',original_run)
         and original_run not in (identity['run_id'],HOME_INSPECT_FAILED['run_id'],HOME_CHILD_INSPECTION['run_id']),
         'home_child_original_identity')
    comments=pages('/issues/4709/comments');commands=home_child_commands(comments)
    need(set(commands)=={'install','rollback'},'home_child_original_missing')
    _unique_update_command(comments,HOME_CHILD_ROLLBACK_PREFIX,HOME_CHILD_ROLLBACK_OPERATION,identity,original_run)
    command=commands['install'];original={'control_source_sha':command['body'].split()[1],
                                        'run_id':original_run,'command_comment_id':command['id']}
    home_child_runtime_identity(original)
    need(_tv_time(commands['rollback']['created_at'])>=_tv_time(command['created_at']),'home_child_command_time')
    run=api('/actions/runs/'+original_run)
    artifacts=[a for a in pages('/actions/runs/'+original_run+'/artifacts','artifacts')
               if a.get('name')=='search3-public-install-'+original_run+'-1']
    need(len(artifacts)==1 and artifacts[0].get('expired') is False
         and re.fullmatch(r'sha256:[a-f0-9]{64}',artifacts[0].get('digest','')),'home_child_source_artifact')
    artifact=artifacts[0]
    with zip_files(download(artifact['id'],artifact['digest'].removeprefix('sha256:'))) as archive:
        need(archive.namelist()==['evidence.json'],'home_child_source_artifact')
        raw=archive.read('evidence.json');need(len(raw)<=262144,'home_child_source_artifact');value=_tv_json(raw)
    need(_home_child_native_proof(command,run,value,artifact)==original,'home_child_original_identity')
    # Recovery uses this child's self-contained accepted proof. It does not
    # reopen the expired failed/inspection artifacts or current SOURCE gates.
    home_child_writer_quiescence();rollback_main(identity)
    return original,artifact


def home_child(rollback=False):
    need(type(rollback) is bool,'mode');home_child_pending_pins()
    operation=HOME_CHILD_ROLLBACK_OPERATION if rollback else HOME_CHILD_OPERATION
    work=Path(os.environ['RUNNER_TEMP'])/'search3-public-install';work.mkdir(mode=0o700)
    key,known=work/'key',work/'known'
    evidence={'status':'blocked_no_replay','operation_id':operation,'source_sha':HOME_SOURCE,
              'manifest_sha256':home_child_policy()['manifest'],'read_only':False,'phase':'identity','ssh_started':False,
              'continuation':home_child_provenance()}
    complete=False
    try:
        event=json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
        if rollback:
            identity,original_run=home_child_event_identity(event,os.environ,True)
            evidence.update(identity,original_home_run_id=original_run,phase='admission')
            original,artifact=home_child_rollback_admission(identity,original_run);evidence['phase']='artifact'
            policy,accepted,parent_value,inspection=home_child_source_evidence(
                download(artifact['id'],artifact['digest'].removeprefix('sha256:')),original)
            dependency=tv_successor_evidence()
            program=home_child_rollback_remote_program(policy,identity,original,accepted,parent_value,inspection,
                                                       tv_successor=dependency)
            evidence['original_home']=original
        else:
            identity=home_child_event_identity(event,os.environ);evidence.update(identity,phase='admission')
            inspection=home_child_admission(identity);evidence['phase']='artifact'
            parent_value=home_child_inspection_value(inspection)
            previous,files=home_package(download(HOME_ARTIFACT,HOME_ARTIFACT_SHA),parent_value)
            need(previous==home_policy(),'home_child_payload_policy');policy=home_child_policy()
            dependency=tv_successor_evidence()
            program=home_child_remote_program(policy,files,identity,parent_value,inspection,tv_successor=dependency)
        evidence.update(parent_handoff=home_parent_identity(),parent_evidence=parent_value,inspection_evidence=inspection)
        host,user=os.environ['PREVIEW_HOST'],os.environ['PREVIEW_USER']
        need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]{0,252}',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
        raw=os.environ['PREVIEW_KEY'].replace('\r','');need(0<len(raw)<=32768,'ssh_key')
        if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
        with key.open('x') as stream:stream.write(raw.rstrip()+'\n')
        key.chmod(0o600)
        scan=carrier.run(['ssh-keyscan','-T','15','-t','ed25519',host]);need(0<len(scan)<=8192,'host_key')
        known.write_bytes(scan);known.chmod(0o600)
        options=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),
                 '-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','ConnectTimeout=15']
        if rollback:
            checked,latest=home_child_rollback_admission(identity,original_run)
            need(checked==original and latest==artifact,'home_child_rollback_changed')
        else:need(home_child_admission(identity)==inspection,'home_child_inspection_changed')
        need(tv_successor_evidence()==dependency,'tv_successor_changed')
        evidence.update(status='unknown_no_replay',phase='remote',ssh_started=True)
        response=carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'],input=program.encode(),timeout=180)
        need(len(response)<=32768,'receipt_limit');result=_tv_json(response)
        complete=valid_home_child_result(identity,result,original,accepted) if rollback else valid_home_child_result(identity,result)
        evidence['remote']=result
        if rollback:rollback_main(identity)
        else:home_inspect_heads(identity)
        evidence.update(status=result['status'],phase='complete')
    except Exception as error:
        complete=False
        evidence['error_type']=type(error).__name__ if type(error) in (RuntimeError,ValueError,KeyError,OSError,subprocess.CalledProcessError,subprocess.TimeoutExpired) else 'HomeChildError'
    finally:
        key.unlink(missing_ok=True);known.unlink(missing_ok=True)
        (work/'evidence.json').write_text(json.dumps(evidence,sort_keys=True)+'\n')
    print(json.dumps(evidence,sort_keys=True));need(complete,'home_child_rollback_not_verified' if rollback else 'home_child_not_verified')


# Exact early dispatch leaves the original command parser and original main
# dispatch AST unchanged. No other argument list can enter the child modes.
if __name__ == '__main__':
    import sys as _home_child_sys
    if _home_child_sys.argv[1:] in (['home-continue'],['home-continue-rollback']):
        home_child(_home_child_sys.argv[1:]==['home-continue-rollback'])
        raise SystemExit(0)


HOME_CHILD_INSPECT_PREFIX = '/inspect-site-home-continuation'
HOME_CHILD_INSPECT_OPERATION = 'site-home-inspect-45089823646-v1'
HOME_CHILD_INSPECT_ORIGINAL = {
    'control_source_sha': 'bd851620fa4a0d932bcdbea1b9ebeff527bb1020',
    'run_id': '45089823646',
    'command_comment_id': 7088495946,
    'job_id': 137416621468,
    'terminal_comment_id': 7088724592,
}
HOME_CHILD_INSPECT_RECEIPT_LIMIT = 32768
HOME_CHILD_INSPECT_CLASSIFICATIONS = ('current_before_namespace_absent','installed_sealed_current',
    'rolled_back_sealed_current','partial_unknown','unknown')
HOME_CHILD_INSPECT_CUSTODY = ('namespace_present','reservation_present','reservation_valid',
    'reconciliation_present','reconciliation_valid','snapshot_present','snapshot_valid',
    'receipt_present','receipt_valid','intents_complete','backups_complete','stages_consistent',
    'rollback_captures_consistent')
HOME_CHILD_INSPECT_FLAGS = ('parent_records_verified','records_stable','inventory_stable',
    'namespaces_stable','failed_namespace_absent','protected_matches','ancestors_match')
HOME_CHILD_INSPECT_REASONS = HOME_CHILD_REASONS + ('home_child_inspect_namespace','home_child_inspect_parent',
    'home_child_inspect_custody','home_child_inspect_receipt','home_child_inspect_snapshot',
    'home_child_inspect_intent','home_child_inspect_stage','home_child_inspect_restored',
    'home_child_inspect_race','home_child_inspect_unreadable')


def home_child_inspect_scope():
    public,private=home_inspect_scope()
    guarded=(set(public)|set(private)|set(HANDOFF_ORDER)|set(HOME_DEPENDENCIES))-set(HOME_ORDER)
    need(set(private)<=guarded and len(guarded)<=512,'home_child_inspect_scope')
    return tuple(sorted(guarded-set(private))),private


def home_child_inspect_event_identity(event,env):
    control=env.get('GITHUB_SHA','')
    need(event.get('comment',{}).get('body')==
         f'{HOME_CHILD_INSPECT_PREFIX} {control} {HOME_CHILD_INSPECT_OPERATION}','command')
    synthetic=json.loads(json.dumps(event));synthetic['comment']['body']=f'{HOME_CHILD_PREFIX} {control} {HOME_CHILD_OPERATION}'
    identity=home_child_event_identity(synthetic,env)
    need(identity['run_id']!=HOME_CHILD_INSPECT_ORIGINAL['run_id']
         and identity['command_comment_id']!=HOME_CHILD_INSPECT_ORIGINAL['command_comment_id'],
         'home_child_inspect_identity')
    return identity


def home_child_inspect_heads(identity):
    home_inspect_heads(identity)
    relation=api('/compare/'+HOME_CHILD_INSPECT_ORIGINAL['control_source_sha']+'...'+identity['control_source_sha'])
    need(relation.get('status') in ('identical','ahead') and relation.get('behind_by')==0
         and relation.get('merge_base_commit',{}).get('sha')==HOME_CHILD_INSPECT_ORIGINAL['control_source_sha'],
         'home_child_inspect_ancestry')


def home_child_inspect_admission(identity):
    home_child_inspect_heads(identity);reviewed_control(identity['control_source_sha'])
    pin=HOME_CHILD_INSPECT_ORIGINAL
    original={k:pin[k] for k in ('control_source_sha','run_id','command_comment_id')}
    comments=pages('/issues/4709/comments')
    for fixed,prefix,operation in ((HOME_INSPECT_FAILED,HOME_PREFIX,HOME_OPERATION),
            (HOME_CHILD_INSPECTION,HOME_INSPECT_PREFIX,HOME_INSPECT_OPERATION),
            (pin,HOME_CHILD_PREFIX,HOME_CHILD_OPERATION)):
        _unique_update_command(comments,prefix,operation,
            {k:fixed[k] for k in ('control_source_sha','run_id','command_comment_id')})
    _unique_update_command(comments,HOME_CHILD_INSPECT_PREFIX,HOME_CHILD_INSPECT_OPERATION,identity)
    need(not any(c.get('body','').strip().split()[:1] in ([HOME_ROLLBACK_PREFIX],[HOME_CHILD_ROLLBACK_PREFIX])
                 and any(op in c.get('body','').split()[1:] for op in (HOME_ROLLBACK_OPERATION,HOME_CHILD_ROLLBACK_OPERATION))
                 for c in comments),'home_child_inspect_rollback_present')
    command=next(c for c in comments if c['id']==original['command_comment_id'])
    current=next(c for c in comments if c['id']==identity['command_comment_id'])
    terminal=next((c for c in comments if c['id']==pin['terminal_comment_id']),{})
    for value in (command,current,terminal):
        need(value.get('created_at')==value.get('updated_at'),'home_child_inspect_edited_command')
        _tv_time(value.get('created_at'))
    tokens=[str(pin[k]) for k in ('run_id','control_source_sha','command_comment_id','job_id')]
    need(terminal.get('user',{}).get('id')==226193297 and terminal.get('author_association')=='OWNER'
         and all(t in terminal.get('body','') for t in tokens+['CANCELLED','UNKNOWN','CONSUMED','NO REPLAY'])
         and _tv_time(command['created_at'])<=_tv_time(terminal['created_at'])<=_tv_time(current['created_at']),
         'home_child_inspect_terminal')
    run=api('/actions/runs/'+pin['run_id'])
    need(type(run.get('id')) is int and run['id']==int(pin['run_id'])
         and run.get('head_sha')==pin['control_source_sha'] and run.get('head_branch')=='main'
         and run.get('path')==WORKFLOW and run.get('event')=='issue_comment'
         and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')=='cancelled'
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff'
         and run.get('repository',{}).get('full_name')==run.get('head_repository',{}).get('full_name')==REPO
         and _tv_time(command['created_at'])<=_tv_time(run.get('created_at'))<=_tv_time(command['created_at'])+300,
         'home_child_inspect_run')
    jobs=[j for j in pages('/actions/runs/'+pin['run_id']+'/attempts/1/jobs','jobs') if j.get('name')=='public-install']
    need(len(jobs)==1 and type(jobs[0].get('id')) is int and jobs[0]['id']==pin['job_id']
         and type(jobs[0].get('run_id')) is int and jobs[0]['run_id']==int(pin['run_id'])
         and jobs[0].get('status')=='completed' and jobs[0].get('conclusion')=='cancelled','home_child_inspect_job')
    steps=[s for s in jobs[0].get('steps',[]) if s.get('name','').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps)==1 and steps[0].get('name')=='Run python3 scripts/deploy/search3_public_install.py home-continue'
         and steps[0].get('status')=='completed' and steps[0].get('conclusion')=='cancelled'
         and _tv_time(run.get('created_at'))<=_tv_time(run.get('run_started_at'))
         <=_tv_time(steps[0].get('started_at'))<=_tv_time(steps[0].get('completed_at'))
         <=_tv_time(run.get('updated_at'))<=_tv_time(terminal['created_at']),'home_child_inspect_step')
    need(not pages('/actions/runs/'+pin['run_id']+'/artifacts','artifacts'),'home_child_inspect_artifact_present')
    home_child_writer_quiescence()
    # The previous reader's immutable artifact supplies the already accepted
    # COUNTRY lineage. It is read, never rerun; this cancelled child has no artifact.
    inspection=home_child_inspection_evidence(download(HOME_CHILD_INSPECTION['artifact_id'],HOME_CHILD_INSPECTION['artifact_sha256']))
    home_child_inspect_heads(identity)
    return inspection


HOME_CHILD_INSPECT_REMOTE = r'''
def home_child_inspect_json(raw):
 value=json.loads(raw)
 require(raw==canonical(value),'home_child_inspect_custody')
 return value
def home_child_inspect_namespaces():
 site_continuation_namespace_absent()
 absent=(REFACTOR_ROLLBACK_OPERATION,HANDOFF_ROLLBACK_OPERATION,OBSERVE_OLD_HOME,OBSERVE_OLD_ROLLBACK,
         OBSERVE_OLD_READER,HOME_ROLLBACK_OPERATION,OBSERVE_OPERATION)
 state={}
 for operation in absent+(HOME_POLICY['operation'],):
  with base.parent(operation) as (fd,name):
   try:s=os.stat(name,dir_fd=fd,follow_symlinks=False)
   except FileNotFoundError:state[operation]=None;continue
   require(operation==HOME_POLICY['operation'] and stat.S_ISDIR(s.st_mode)
           and stat.S_IMODE(s.st_mode)==0o700 and s.st_uid==os.getuid(),'home_child_inspect_namespace')
   state[operation]=stamp(s)
 return state
def home_child_inspect_parent():
 records=site_read_records(handed,HANDOFF_PREPARED_PATHS+('result.json',))
 value=lambda p:home_child_inspect_json(records[p][2])
 require(value('reservation.json')=={'operation':HANDOFF_POLICY['operation'],**HANDOFF_ORIGINAL,
         'source':HANDOFF_POLICY['source'],'manifest':HANDOFF_POLICY['manifest'],'parent_refactor':REFACTOR_ORIGINAL}
         and value('result.json')==HANDOFF_ACCEPTED,'home_child_inspect_parent')
 snapshot=value('snapshot/manifest.json')
 scope=((set(POLICY['expected'])|set(checked_protected_paths())|set(SITE_POLICY['dependencies'])
         |set(SITE_POLICY['optional_dependencies'])|set(SITE_POLICY['order']))-set(HANDOFF_POLICY['order']))
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_refactor','before','guarded','ancestors'}
         and snapshot['source']==HANDOFF_POLICY['source'] and snapshot['manifest']==HANDOFF_POLICY['manifest']
         and snapshot['parent_refactor']==REFACTOR_ORIGINAL and set(snapshot['before'])==set(HANDOFF_POLICY['order'])
         and set(snapshot['guarded'])==scope and set(snapshot['ancestors'])==set(OBSERVE_ANCESTORS[:7])
         and digest(records['snapshot/manifest.json'][2])==HOME_POLICY['parent']['snapshot_manifest_sha256'],
         'home_child_inspect_parent')
 return records,snapshot
def home_child_inspect_records():
 if homed is None:return {}
 rows={}
 for path in OBSERVE_RECORD_PATHS:
  content=path in ('reservation.json','reconciliation.json','snapshot/manifest.json','result.json') or path.startswith('intent-')
  value=homed.read(path,content);row,identity,_=value
  mode='0600' if content else '0644'
  require(row=={'state':'absent'} or row.get('mode')==mode and identity[6]==os.getuid(),'home_child_inspect_custody')
  rows[path]=value
 return rows
def home_child_inspect_pass():
 namespaces=home_child_inspect_namespaces()
 records,parent=home_child_inspect_parent()
 refactor_expected({p:r[0] for p,r in parent['guarded'].items()})
 custody=home_child_inspect_records()
 scope=tuple(sorted(set(OBSERVE_PUBLIC)|set(private_labels())|set(HOME_POLICY['order'])))
 observed=continuation_inventory(public,scope)
 ancestors=home_ancestor_inventory()
 require(set(ancestors)==set(OBSERVE_ANCESTORS),'home_child_inspect_parent')
 tv_successor_stable()
 return {'namespaces':namespaces,'parent_records':records,'parent':parent,'custody':custody,
         'observed':observed,'ancestors':ancestors}
def home_child_inspect_receipt(value):
 fixed={'schema_version':1,'operation_id':HOME_POLICY['operation'],'source_sha':HOME_POLICY['source'],
        'manifest_sha256':HOME_POLICY['manifest'],**OBSERVE_IDENTITY,'parent_handoff':HANDOFF_ORIGINAL,
        'parent_receipt_sha256':HOME_POLICY['parent']['receipt_sha256'],'continuation':CHILD_PROVENANCE}
 counters=('supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
 flags=('reservation_created','snapshot_verified','restore_drill_verified','protected_unchanged',
        'unchanged_files_verified','parent_records_unchanged','original_inodes_verified','failed_namespace_absent',
        'reconciliation_verified','lineage_persisted')
 required=set(fixed)|set(counters)|set(flags)|{'status','phase','written_files','rollback_files',
          'snapshot_manifest_sha256','restored_guard','reconciliation_sha256','private_checks'}
 require(isinstance(value,dict) and required<=set(value)<=required|{'reason'}
         and all(type(value.get(k)) is type(v) and value[k]==v for k,v in fixed.items())
         and all(type(value[k]) is int and value[k]==0 for k in counters)
         and all(type(value[k]) is bool for k in flags),'home_child_inspect_receipt')
 require(value['status'] in ('blocked_no_replay','unknown_no_replay','rolled_back_no_replay','home_navigation_updated_verified')
         and value['phase'] in ('preflight','snapshot','assets','complete')
         and ('reason' not in value or value['reason'] in OBSERVE_CHILD_REASONS),'home_child_inspect_receipt')
 written=value['written_files'];restored=value['rollback_files'];order=HOME_POLICY['order']
 require(isinstance(written,list) and written in [list(order[:i]) for i in range(len(order)+1)]
         and isinstance(restored,list) and all(isinstance(p,str) for p in restored)
         and len(restored)==len(set(restored)) and all(p in written for p in restored)
         and restored==[p for p in reversed(written) if p in restored],'home_child_inspect_receipt')
 for key in ('snapshot_manifest_sha256','reconciliation_sha256'):
  v=value[key];require(v is None or isinstance(v,str) and len(v)==64 and all(c in '0123456789abcdef' for c in v),
                      'home_child_inspect_receipt')
 verified=value['reconciliation_verified']
 require(isinstance(value['private_checks'],dict) and set(value['private_checks'])=={'public_config'}
         and type(value['private_checks']['public_config']) is bool and value['private_checks']['public_config']==verified
         and verified==(value['reconciliation_sha256'] is not None)
         and (not value['lineage_persisted'] or verified),'home_child_inspect_receipt')
 if value['status']=='blocked_no_replay':
  require(not value['reservation_created'] and value['phase']=='preflight' and not written and not restored,'home_child_inspect_receipt')
 if value['status'] in ('home_navigation_updated_verified','rolled_back_no_replay'):
  require(value['phase']=='complete' and all(value[k] for k in ('reservation_created','protected_unchanged',
          'unchanged_files_verified','parent_records_unchanged','original_inodes_verified','failed_namespace_absent')),
          'home_child_inspect_receipt')
 if written or value['status']=='home_navigation_updated_verified':
  require(verified and value['lineage_persisted'],'home_child_inspect_receipt')
 if value['status']=='home_navigation_updated_verified':
  require(all(value[k] for k in flags) and value['snapshot_manifest_sha256'] is not None and 'reason' not in value
          and written==list(order) and not restored and value['restored_guard'] is None,'home_child_inspect_receipt')
 elif value['status']=='rolled_back_no_replay':
  require(restored==list(reversed(written)) and isinstance(value['restored_guard'],dict)
          and set(value['restored_guard'])==set(order),'home_child_inspect_receipt')
  for path,row in value['restored_guard'].items():
   require(isinstance(row,list) and len(row)==2 and row[0]==HOME_POLICY['targets'][path]['before'],'home_child_inspect_receipt')
   home_before_identity(row[1],row[0])
 elif value['status']!='unknown_no_replay':require(value['restored_guard'] is None,'home_child_inspect_receipt')
 return value
def home_child_inspect_custody(view):
 rows=view['custody'];flags=result['custody'];flags['namespace_present']=bool(rows)
 for key,path in (('reservation','reservation.json'),('reconciliation','reconciliation.json'),
                  ('snapshot','snapshot/manifest.json'),('receipt','result.json')):
  flags[key+'_present']=bool(rows) and rows[path][0]!={'state':'absent'}
 if not rows:return None,None
 decoded={}
 for key,path in (('reservation','reservation.json'),('reconciliation','reconciliation.json'),
                  ('snapshot','snapshot/manifest.json'),('receipt','result.json')):
  if flags[key+'_present']:decoded[key]=home_child_inspect_json(rows[path][2])
 if 'reservation' in decoded:
  require(decoded['reservation']=={'operation':HOME_POLICY['operation'],**OBSERVE_IDENTITY,
          'source':HOME_POLICY['source'],'manifest':HOME_POLICY['manifest'],'parent_handoff':HANDOFF_ORIGINAL},
          'home_child_inspect_custody');flags['reservation_valid']=True
 if 'receipt' in decoded:
  receipt=home_child_inspect_receipt(decoded['receipt']);flags['receipt_valid']=True
  result.update(retained_status=receipt['status'],retained_written_files=receipt['written_files'],
                retained_rollback_files=receipt['rollback_files'],retained_result_sha256=digest(rows['result.json'][2]))
 else:receipt=None
 if 'reconciliation' in decoded:
  home_child_validate_binding(decoded['reconciliation']);flags['reconciliation_valid']=True
  result['reconciliation_sha256']=digest(rows['reconciliation.json'][2])
 if 'snapshot' not in decoded:return None,receipt
 snapshot=decoded['snapshot'];order=HOME_POLICY['order'];targets=HOME_POLICY['targets']
 require(isinstance(snapshot,dict) and set(snapshot)=={'source','manifest','parent_handoff','before','guarded','ancestors'}
         and snapshot['source']==HOME_POLICY['source'] and snapshot['manifest']==HOME_POLICY['manifest']
         and snapshot['parent_handoff']==HANDOFF_ORIGINAL and set(snapshot['before'])==set(order)
         and set(snapshot['guarded'])==set(OBSERVE_PUBLIC)|set(private_labels())
         and set(snapshot['ancestors'])==set(OBSERVE_ANCESTORS),'home_child_inspect_snapshot')
 require(flags['reconciliation_valid'],'home_child_inspect_snapshot')
 home_child_validate_binding(decoded['reconciliation'],snapshot['guarded'])
 # An internally rehashed record must still descend from the independently
 # pinned COUNTRY snapshot. Only the already persisted five-row transition
 # and the separately authenticated TV successor may differ from that origin.
 accepted=decoded['reconciliation']['accepted'];parent=view['parent']
 for path,old in parent['guarded'].items():
  if path in order:
   require(snapshot['before'][path]==old,'home_child_inspect_snapshot');continue
  expected=accepted.get(path,old)
  if TV_SUCCESSOR_PROOF is not None and path==TV_SUCCESSOR['path']:
   expected=[_tv_successor_bound['target'][0],list(_tv_successor_bound['target'][1])]
  require(snapshot['guarded'].get(path)==expected,'home_child_inspect_snapshot')
 require(all(snapshot['ancestors'][g]==parent['ancestors'][g] for g in OBSERVE_ANCESTORS[:7])
         and snapshot['ancestors']['handoff']==refactor_stamp_rows(view['parent_records']),
         'home_child_inspect_snapshot')
 for index,path in enumerate(HANDOFF_POLICY['order']):
  target=HANDOFF_POLICY['targets'][path];before=parent['before'][path]
  intent=home_child_inspect_json(view['parent_records']['intent-'+str(index)][2])
  require(isinstance(intent,dict) and set(intent)=={'path','before','after','previous_identity','new_identity'}
          and intent['path']==path and intent['before']==target['before'] and intent['after']==target['after']
          and intent['previous_identity']==before[1],'home_child_inspect_snapshot')
  newer=checked_stamp(intent['new_identity'],target['after']);prior=snapshot['ancestors']['handoff_stage'].get(path)
  guarded=snapshot['guarded'].get(path)
  require(isinstance(prior,list) and len(prior)==2 and prior[0]==before[0]
          and prior[1] is not None and prior[1][:-1]==before[1][:-1]
          and isinstance(guarded,list) and len(guarded)==2 and guarded[0]==target['after']
          and checked_stamp(guarded[1],guarded[0])[:-1]==newer[:-1],'home_child_inspect_snapshot')
 for path,row in HOME_POLICY['dependencies'].items():
  require(path in snapshot['guarded'] and snapshot['guarded'][path][0]==row,'home_child_inspect_snapshot')
 for path,before in snapshot['before'].items():
  require(isinstance(before,list) and len(before)==2 and before[0]==targets[path]['before'],'home_child_inspect_snapshot')
  home_before_identity(before[1],before[0])
 flags['snapshot_valid']=True;result['snapshot_manifest_sha256']=digest(rows['snapshot/manifest.json'][2])
 if receipt is not None:
  require(receipt['snapshot_manifest_sha256']==result['snapshot_manifest_sha256']
          and receipt['reconciliation_sha256']==result['reconciliation_sha256']
          and receipt['lineage_persisted'] and flags['reservation_valid'],'home_child_inspect_custody')
 intents={};flags['backups_complete']=True
 for index,path in enumerate(order):
  flags['backups_complete'] &= all(rows[p+str(index)][0]==home_backup_row(path) for p in ('snapshot/','drill/'))
  raw=rows['intent-'+str(index)]
  if raw[0]=={'state':'absent'}:continue
  intent=home_child_inspect_json(raw[2])
  require(isinstance(intent,dict) and set(intent)=={'path','before','after','previous_identity','new_identity'}
          and intent['path']==path and intent['before']==targets[path]['before'] and intent['after']==targets[path]['after']
          and intent['previous_identity']==snapshot['before'][path][1],'home_child_inspect_intent')
  checked_stamp(intent['new_identity'],targets[path]['after']);intents[path]=intent
 flags['intents_complete']=len(intents)==len(order)
 if receipt is None or receipt['status'] not in ('home_navigation_updated_verified','rolled_back_no_replay'):
  return snapshot,receipt
 # A sealed classification requires complete preparation. An early recovered
 # attempt with incomplete records remains partial_unknown, without inventing custody.
 if not flags['backups_complete'] or not flags['intents_complete']:return snapshot,receipt
 rolled=receipt['status']=='rolled_back_no_replay';written=receipt['written_files']
 flags['stages_consistent']=True;flags['rollback_captures_consistent']=True
 for index,path in enumerate(order):
  target=targets[path];before=snapshot['before'][path];old=home_before_identity(before[1],before[0])
  new=checked_stamp(intents[path]['new_identity'],target['after']);current=view['observed'][path]
  stage=rows['stage/'+str(index)][:2];capture=rows['rollback/'+str(index)][:2]
  require(rows['rollback/conflict-'+str(index)][0]=={'state':'absent'},'home_child_inspect_stage')
  if not rolled:
   flags['stages_consistent'] &= (current[0]==target['after'] and current[1] is not None
        and current[1][:-1]==new[:-1] and home_same_original(stage,(before[0],old)))
   flags['rollback_captures_consistent'] &= capture==({'state':'absent'},None)
  else:
   restored=receipt['restored_guard'][path]
   require(current==(restored[0],tuple(restored[1]) if restored[1] is not None else None)
           and home_same_original(current,(before[0],old)),'home_child_inspect_restored')
   if path in written:
    flags['stages_consistent'] &= stage==({'state':'absent'},None)
    flags['rollback_captures_consistent'] &= (capture[0]==target['after'] and capture[1] is not None and capture[1][:-1]==new[:-1])
   else:
    flags['stages_consistent'] &= (stage[0]==target['after'] and stage[1] is not None and stage[1][:-1]==new[:-1]
                                  and restored==before)
    flags['rollback_captures_consistent'] &= capture==({'state':'absent'},None)
 return snapshot,receipt
def home_child_inspect_compare(view,snapshot):
 labels=private_labels();observed=view['observed'];parent=view['parent']
 if snapshot is None:
  saved={p:r for p,r in parent['guarded'].items() if p not in HOME_POLICY['order']}
  result['protected_reference']='parent'
 else:saved=snapshot['guarded'];result['protected_reference']='child_snapshot'
 expected=refactor_expected({p:r[0] for p,r in saved.items()})
 for path in tuple(OBSERVE_PUBLIC)+tuple(labels):
  current=observed[path];row=current[0];readable=row.get('state')!='unreadable'
  wanted=expected.get(path,HOME_POLICY['dependencies'].get(path,HANDOFF_POLICY['targets'].get(path,{}).get('after')))
  previous=saved.get(path)
  if TV_SUCCESSOR_PROOF is not None and path==TV_SUCCESSOR['path']:previous=_tv_successor_bound['target']
  same=previous is not None and current==(previous[0],tuple(previous[1]) if previous[1] is not None else None)
  check={'readable':readable,'matches_row':readable and row==wanted,'matches_identity':bool(same)}
  if path in labels:result['private_checks'][labels[path]]=check
  elif not all(check.values()):result['protected_mismatches'].append({'path':path,**check})
 result['protected_matches']=not result['protected_mismatches'] and all(all(r.values()) for r in result['private_checks'].values())
 for group in OBSERVE_ANCESTORS:
  if snapshot is not None:matches=view['ancestors'][group]==snapshot['ancestors'][group]
  elif group in parent['ancestors']:matches=view['ancestors'][group]==parent['ancestors'][group]
  elif group=='handoff':matches=view['ancestors'][group]==refactor_stamp_rows(view['parent_records'])
  else:
   matches=True
   for index,path in enumerate(HANDOFF_POLICY['order']):
    prior=view['ancestors'][group].get(path);before=parent['before'][path]
    matches &= (isinstance(prior,list) and len(prior)==2 and prior[0]==before[0]
                and prior[1] is not None and prior[1][:-1]==before[1][:-1])
  result['ancestor_checks'][group]={'matches':bool(matches),'stable':False}
 result['ancestors_match']=all(r['matches'] for r in result['ancestor_checks'].values())
result={'schema_version':1,'operation_id':OBSERVE_OPERATION,'source_sha':HOME_POLICY['source'],
        'manifest_sha256':HOME_POLICY['manifest'],**IDENTITY,'original_cancelled':OBSERVE_ORIGINAL,
        'parent_handoff':HANDOFF_ORIGINAL,'parent_receipt_sha256':HOME_POLICY['parent']['receipt_sha256'],
        'read_only':True,'counter_scope':'observer_only','original_process_liveness':'unknown',
        'historical_zero_write_proven':False,'future_writer_authorized':False,
        'server_writes':0,'supplier_calls':0,'database_reads':0,'database_writes':0,'lead_calls':0,'booking_calls':0,'application_calls':0,
        'status':'inspection_blocked','phase':'preflight','classification':'unknown',
        **{k:False for k in OBSERVE_FLAGS},'custody':{k:False for k in OBSERVE_CUSTODY},'custody_reason':None,
        'target_checks':[],'checked_public_paths':[],'protected_reference':'parent','protected_mismatches':[],
        'private_checks':{},'ancestor_checks':{},'retained_status':None,'retained_written_files':[],
        'retained_rollback_files':[],'retained_result_sha256':None,'snapshot_manifest_sha256':None,'reconciliation_sha256':None}
public=base=original=updated=published=refactored=handed=homed=None
try:
 home=os.environ.get('HOME','');public=Files(home+'/www/anytoour.ru')
 base=Files(home+'/.anytoour-search3-root-gateway');private_root(base)
 original=Files(home+'/.anytoour-search3-root-gateway/'+POLICY['operation']);private_root(original)
 updated=Files(home+'/.anytoour-search3-root-gateway/'+UPDATE_POLICY['operation']);private_root(updated)
 published=Files(home+'/.anytoour-search3-root-gateway/'+SITE_POLICY['operation']);private_root(published)
 refactored=Files(home+'/.anytoour-search3-root-gateway/'+REFACTOR_POLICY['operation']);private_root(refactored)
 handed=Files(home+'/.anytoour-search3-root-gateway/'+HANDOFF_POLICY['operation']);private_root(handed)
 initial_namespaces=home_child_inspect_namespaces()
 if initial_namespaces[HOME_POLICY['operation']] is not None:
  homed=Files(home+'/.anytoour-search3-root-gateway/'+HOME_POLICY['operation']);private_root(homed)
 first=home_child_inspect_pass()
 result['parent_records_verified']=True
 require(all(r[0].get('state')!='unreadable' for r in first['observed'].values()),'home_child_inspect_unreadable')
 for path in HOME_POLICY['order']:
  row=first['observed'][path][0];target=HOME_POLICY['targets'][path]
  result['target_checks'].append({'path':path,'observed':row,'matches_before':row==target['before'],'matches_after':row==target['after']})
 result['checked_public_paths']=list(OBSERVE_PUBLIC)
 snapshot=receipt=None
 try:snapshot,receipt=home_child_inspect_custody(first)
 except Exception as error:
  result['custody_reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'home_child_inspect_custody'
 home_child_inspect_compare(first,snapshot)
 # Finish semantic validation before the confirming pass. No first observation
 # becomes authority if any record, namespace, target or protected stamp moved.
 second=home_child_inspect_pass()
 result['records_stable']=(first['parent_records']==second['parent_records'] and first['custody']==second['custody']
                           and first['ancestors']==second['ancestors'])
 result['inventory_stable']=first['observed']==second['observed']
 result['namespaces_stable']=initial_namespaces==first['namespaces']==second['namespaces']
 for group,row in result['ancestor_checks'].items():row['stable']=first['ancestors'][group]==second['ancestors'][group]
 require(result['records_stable'] and result['inventory_stable'] and result['namespaces_stable'],'home_child_inspect_race')
 if not result['custody']['namespace_present']:
  if all(r['matches_before'] for r in result['target_checks']):result['classification']='current_before_namespace_absent'
 else:
  result['classification']='partial_unknown'
  sealed=(result['custody_reason'] is None and all(result['custody'].values()) and result['protected_matches']
          and result['ancestors_match'] and receipt is not None)
  if sealed and receipt['status']=='home_navigation_updated_verified':result['classification']='installed_sealed_current'
  elif sealed and receipt['status']=='rolled_back_no_replay':result['classification']='rolled_back_sealed_current'
 tv_successor_stable()
 result.update(status='inspection_complete',phase='complete')
except Exception as error:
 result['status']='inspection_unknown' if result['parent_records_verified'] else 'inspection_blocked'
 result['classification']='unknown'
 result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'
finally:
 for store in (homed,handed,refactored,published,updated,original,base,public):
  if store is not None:store.close()
print(json.dumps(result,sort_keys=True))
'''


def home_child_inspect_remote_program(identity,inspection_value,*,tv_successor=None):
    home_child_runtime_identity(identity)
    need(identity['run_id']!=HOME_CHILD_INSPECT_ORIGINAL['run_id']
         and identity['command_comment_id']!=HOME_CHILD_INSPECT_ORIGINAL['command_comment_id'],'home_child_inspect_identity')
    parent=home_child_inspection_value(inspection_value)
    public,_=home_child_inspect_scope()
    original={k:HOME_CHILD_INSPECT_ORIGINAL[k] for k in ('control_source_sha','run_id','command_comment_id')}
    # Only these two existing functions read an already persisted reconciliation.
    # The first-time binder and all child install/recovery bodies are excluded.
    selected={'home_child_origin','home_child_validate_binding'}
    nodes=[n for n in ast.parse(HOME_CHILD_COMMON_REMOTE).body if isinstance(n,ast.FunctionDef) and n.name in selected]
    need({n.name for n in nodes}==selected,'home_child_inspect_template')
    binding='\n'.join(ast.get_source_segment(HOME_CHILD_COMMON_REMOTE,n) for n in nodes)
    paths=('reservation.json','reconciliation.json','snapshot/manifest.json','result.json')+tuple(
        prefix+str(i) for i in range(len(HOME_ORDER)) for prefix in ('snapshot/','drill/','intent-','stage/','rollback/','rollback/conflict-'))
    program=(_home_program_prefix(home_policy(),identity,parent,tv_successor)
        +'\nHOME_POLICY='+repr(home_child_policy())+'\nHOME_ROLLBACK_OPERATION='+repr(HOME_CHILD_ROLLBACK_OPERATION)
        +'\nCHILD_PROVENANCE='+repr(home_child_provenance())+'\nCHILD_PUBLIC_DRIFT='+repr(HOME_CHILD_PUBLIC_DRIFT)
        +'\nOBSERVE_ORIGINAL='+repr(HOME_CHILD_INSPECT_ORIGINAL)+'\nOBSERVE_IDENTITY='+repr(original)
        +'\nOBSERVE_OPERATION='+repr(HOME_CHILD_INSPECT_OPERATION)+'\nOBSERVE_PUBLIC='+repr(public)
        +'\nOBSERVE_ANCESTORS='+repr(HOME_INSPECT_ANCESTORS+('handoff','handoff_stage'))
        +'\nOBSERVE_RECORD_PATHS='+repr(paths)+'\nOBSERVE_FLAGS='+repr(HOME_CHILD_INSPECT_FLAGS)
        +'\nOBSERVE_CUSTODY='+repr(HOME_CHILD_INSPECT_CUSTODY)+'\nOBSERVE_CHILD_REASONS='+repr(HOME_CHILD_REASONS)
        +'\nOBSERVE_OLD_HOME='+repr(HOME_OPERATION)+'\nOBSERVE_OLD_ROLLBACK='+repr(HOME_ROLLBACK_OPERATION)
        +'\nOBSERVE_OLD_READER='+repr(HOME_INSPECT_OPERATION)
        +'\ndef re_safe(value): return value in '+repr(HOME_CHILD_INSPECT_REASONS)+'\n'+binding+'\n'+HOME_CHILD_INSPECT_REMOTE)
    ast.parse(program);return program


def valid_home_child_inspect_result(identity,result):
    home_child_runtime_identity(identity)
    need(identity['run_id']!=HOME_CHILD_INSPECT_ORIGINAL['run_id']
         and identity['command_comment_id']!=HOME_CHILD_INSPECT_ORIGINAL['command_comment_id'],'home_child_inspect_identity')
    fixed={'schema_version':1,'operation_id':HOME_CHILD_INSPECT_OPERATION,'source_sha':HOME_SOURCE,
        'manifest_sha256':home_child_policy()['manifest'],**identity,'original_cancelled':HOME_CHILD_INSPECT_ORIGINAL,
        'parent_handoff':home_parent_identity(),'parent_receipt_sha256':HOME_PARENT['receipt_sha256'],
        'read_only':True,'counter_scope':'observer_only','original_process_liveness':'unknown',
        'historical_zero_write_proven':False,'future_writer_authorized':False}
    counters=('server_writes','supplier_calls','database_reads','database_writes','lead_calls','booking_calls','application_calls')
    fields={'status','phase','classification','custody','custody_reason','target_checks','checked_public_paths',
        'protected_reference','protected_mismatches','private_checks','ancestor_checks','retained_status',
        'retained_written_files','retained_rollback_files','retained_result_sha256','snapshot_manifest_sha256','reconciliation_sha256'}
    required=set(fixed)|set(counters)|set(HOME_CHILD_INSPECT_FLAGS)|fields
    need(isinstance(result,dict) and required<=set(result)<=required|{'reason'},'home_child_inspect_receipt_shape')
    need(all(type(result.get(k)) is type(v) and result[k]==v for k,v in fixed.items()),'home_child_inspect_receipt_identity')
    need(all(type(result[k]) is int and result[k]==0 for k in counters),'home_child_inspect_receipt_counters')
    need(all(type(result[k]) is bool for k in HOME_CHILD_INSPECT_FLAGS),'home_child_inspect_receipt_flags')
    need(result['status'] in ('inspection_complete','inspection_blocked','inspection_unknown')
         and result['phase'] in ('preflight','complete') and result['classification'] in HOME_CHILD_INSPECT_CLASSIFICATIONS
         and result['protected_reference'] in ('parent','child_snapshot')
         and ('reason' not in result or result['reason'] in HOME_CHILD_INSPECT_REASONS)
         and (result['custody_reason'] is None or result['custody_reason'] in HOME_CHILD_INSPECT_REASONS),
         'home_child_inspect_receipt_status')
    def booleans(value,keys):
        need(isinstance(value,dict) and set(value)==set(keys) and all(type(value[k]) is bool for k in keys),
             'home_child_inspect_boolean_fields')
    booleans(result['custody'],HOME_CHILD_INSPECT_CUSTODY);custody=result['custody']
    for name in ('reservation','reconciliation','snapshot','receipt'):
        need(not custody[name+'_valid'] or custody[name+'_present'],'home_child_inspect_custody')
    need(custody['namespace_present'] or not any(custody.values()),'home_child_inspect_custody')
    public,private=home_child_inspect_scope();ancestors=HOME_INSPECT_ANCESTORS+('handoff','handoff_stage')
    need(result['checked_public_paths'] in ([],list(public)),'home_child_inspect_scope')
    targets=result['target_checks'];need(isinstance(targets,list) and len(targets) in (0,len(HOME_ORDER)),'home_child_inspect_targets')
    for index,row in enumerate(targets):
        need(isinstance(row,dict) and set(row)=={'path','observed','matches_before','matches_after'}
             and row['path']==HOME_ORDER[index] and type(row['matches_before']) is bool and type(row['matches_after']) is bool,
             'home_child_inspect_targets')
        observed=row['observed'];need(isinstance(observed,dict),'home_child_inspect_targets')
        if observed!={'state':'absent'}:
            need(set(observed)=={'state','sha256','size','mode'} and observed['state']=='present'
                 and isinstance(observed['sha256'],str) and re.fullmatch('[a-f0-9]{64}',observed['sha256'])
                 and type(observed['size']) is int and 0<=observed['size']<=MAX_BYTES
                 and isinstance(observed['mode'],str) and re.fullmatch('[0-7]{4}',observed['mode']),'home_child_inspect_targets')
        need(row['matches_before']==(observed==HOME_TARGETS[row['path']]['before'])
             and row['matches_after']==(observed==HOME_TARGETS[row['path']]['after']),'home_child_inspect_targets')
    checks=('readable','matches_row','matches_identity');seen=set()
    need(isinstance(result['protected_mismatches'],list) and len(result['protected_mismatches'])<=len(public),
         'home_child_inspect_public_scope')
    for value in result['protected_mismatches']:
        need(isinstance(value,dict) and set(value)=={'path',*checks} and value['path'] in public and value['path'] not in seen,
             'home_child_inspect_public_scope');seen.add(value['path'])
        booleans({k:value[k] for k in checks},checks)
        need(not all(value[k] for k in checks),'home_child_inspect_comparison')
    need(isinstance(result['private_checks'],dict) and set(result['private_checks'])<=set(private.values())
         and isinstance(result['ancestor_checks'],dict) and set(result['ancestor_checks'])<=set(ancestors),
         'home_child_inspect_comparison_scope')
    for value in result['private_checks'].values():booleans(value,checks)
    for value in result['ancestor_checks'].values():booleans(value,('matches','stable'))
    for key,flag in (('retained_result_sha256','receipt_valid'),('snapshot_manifest_sha256','snapshot_valid'),
                     ('reconciliation_sha256','reconciliation_valid')):
        value=result[key]
        need((isinstance(value,str) and re.fullmatch('[a-f0-9]{64}',value)) if custody[flag] else value is None,
             'home_child_inspect_digest')
    written=result['retained_written_files'];restored=result['retained_rollback_files']
    need(isinstance(written,list) and written in [list(HOME_ORDER[:i]) for i in range(len(HOME_ORDER)+1)]
         and isinstance(restored,list) and all(isinstance(p,str) for p in restored)
         and len(restored)==len(set(restored)) and all(p in written for p in restored)
         and restored==[p for p in reversed(written) if p in restored],'home_child_inspect_retained_paths')
    need(result['retained_status'] in ('blocked_no_replay','unknown_no_replay','rolled_back_no_replay','home_navigation_updated_verified')
         if custody['receipt_valid'] else result['retained_status'] is None and not written and not restored,
         'home_child_inspect_retained_status')
    complete=result['status']=='inspection_complete';classification=result['classification']
    if complete:
        need(result['phase']=='complete' and 'reason' not in result and len(targets)==len(HOME_ORDER)
             and result['checked_public_paths']==list(public) and set(result['private_checks'])==set(private.values())
             and set(result['ancestor_checks'])==set(ancestors)
             and all(result[k] for k in ('parent_records_verified','records_stable','inventory_stable','namespaces_stable','failed_namespace_absent'))
             and all(r['readable'] for r in result['private_checks'].values())
             and all(r['readable'] for r in result['protected_mismatches'])
             and all(r['stable'] for r in result['ancestor_checks'].values()),'home_child_inspect_incomplete')
        need(result['protected_matches']==(not result['protected_mismatches'] and all(all(r.values()) for r in result['private_checks'].values()))
             and result['ancestors_match']==all(r['matches'] for r in result['ancestor_checks'].values()),'home_child_inspect_comparison')
        if classification in ('installed_sealed_current','rolled_back_sealed_current'):
            rolled=classification=='rolled_back_sealed_current'
            need(all(custody.values()) and result['custody_reason'] is None and result['protected_reference']=='child_snapshot'
                 and result['protected_matches'] and result['ancestors_match']
                 and result['retained_status']==('rolled_back_no_replay' if rolled else 'home_navigation_updated_verified')
                 and all(r['matches_before' if rolled else 'matches_after'] for r in targets)
                 and (restored==list(reversed(written)) if rolled else written==list(HOME_ORDER) and not restored),
                 'home_child_inspect_sealed')
        elif classification=='partial_unknown':need(custody['namespace_present'],'home_child_inspect_classification')
        else:
            need(not custody['namespace_present'] and classification==
                 ('current_before_namespace_absent' if all(r['matches_before'] for r in targets) else 'unknown'),
                 'home_child_inspect_classification')
    else:
        need(result['phase']=='preflight' and classification=='unknown' and 'reason' in result
             and result['parent_records_verified']==(result['status']=='inspection_unknown'),'home_child_inspect_incomplete')
    need(len(json.dumps(result,sort_keys=True).encode())<=HOME_CHILD_INSPECT_RECEIPT_LIMIT,'home_child_inspect_receipt_limit')
    return complete


def home_child_inspect():
    work=Path(os.environ['RUNNER_TEMP'])/'search3-public-install';work.mkdir(mode=0o700)
    key,known=work/'key',work/'known'
    evidence={'status':'inspection_blocked','operation_id':HOME_CHILD_INSPECT_OPERATION,'source_sha':HOME_SOURCE,
        'manifest_sha256':home_child_policy()['manifest'],'read_only':True,'phase':'identity','ssh_started':False,
        'original_cancelled':HOME_CHILD_INSPECT_ORIGINAL,'original_process_liveness':'unknown',
        'historical_zero_write_proven':False,'future_writer_authorized':False}
    complete=False
    try:
        identity=home_child_inspect_event_identity(json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text()),os.environ)
        evidence.update(identity,phase='admission');inspection=home_child_inspect_admission(identity);evidence['phase']='artifact'
        dependency=tv_successor_evidence()
        program=home_child_inspect_remote_program(identity,inspection,tv_successor=dependency)
        evidence['inspection_evidence']=inspection
        host,user=os.environ['PREVIEW_HOST'],os.environ['PREVIEW_USER']
        need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]{0,252}',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
        raw=os.environ['PREVIEW_KEY'].replace('\r','');need(0<len(raw)<=32768,'ssh_key')
        if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
        with key.open('x') as stream:stream.write(raw.rstrip()+'\n')
        key.chmod(0o600)
        scan=carrier.run(['ssh-keyscan','-T','15','-t','ed25519',host]);need(0<len(scan)<=8192,'host_key')
        known.write_bytes(scan);known.chmod(0o600)
        options=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),
                 '-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','ConnectTimeout=15']
        need(home_child_inspect_admission(identity)==inspection,'home_child_inspect_evidence_changed')
        need(tv_successor_evidence()==dependency,'tv_successor_changed')
        evidence.update(status='inspection_unknown',phase='remote',ssh_started=True)
        response=carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'],input=program.encode(),timeout=180)
        need(len(response)<=HOME_CHILD_INSPECT_RECEIPT_LIMIT,'home_child_inspect_receipt_limit')
        result=_tv_json(response);complete=valid_home_child_inspect_result(identity,result);evidence['remote']=result
        home_child_inspect_heads(identity);evidence.update(status=result['status'],phase='complete')
    except Exception as error:
        complete=False
        evidence['error_type']=type(error).__name__ if type(error) in (RuntimeError,ValueError,KeyError,OSError,subprocess.CalledProcessError,subprocess.TimeoutExpired) else 'HomeChildInspectionError'
    finally:
        key.unlink(missing_ok=True);known.unlink(missing_ok=True)
        (work/'evidence.json').write_text(json.dumps(evidence,sort_keys=True)+'\n')
    print(json.dumps(evidence,sort_keys=True));need(complete,'home_child_inspection_not_complete')


# A separate exact dispatcher preserves every consumed controller node.
if __name__ == '__main__':
    import sys as _home_child_inspect_sys
    if _home_child_inspect_sys.argv[1:]==['home-continue-inspect']:
        home_child_inspect()
        raise SystemExit(0)


HOME_FINISH_PREFIX = '/finish-site-home-navigation'
HOME_FINISH_OPERATION = 'site-home-after-45089823646-20261123-v1'
HOME_FINISH_OBSERVATION = {
    'control_source_sha': '8ea0642e647f95bf63a53621e21537a16028c3e2',
    'run_id': '45095031856',
    'command_comment_id': 7089204570,
    'job_id': 137432849414,
    'artifact_id': 14783780758,
    'artifact_sha256': 'b6021c233687375b977652409e36b97253cccdd7293406b201b846fc0fe0bbba',
    'receipt_sha256': '3728040f8c42d7e1ba777eb09a3ad08183854235bf47d93ac871bc2dde926b5f',
    'terminal_comment_id': 7089458075,
}
HOME_FINISH_RECEIPT_LIMIT = 32768
HOME_FINISH_CLOSED_PATH = 'closed.json'
HOME_FINISH_CUSTODY_PATH = 'closure-custody.json'
HOME_FINISH_CLOSURE_FLAGS = ('namespace_created','record_write_attempted','record_verified','durable_verified',
    'guards_rechecked','custody_persisted','protected_unchanged')
HOME_FINISH_REASONS = HOME_CHILD_REASONS + ('home_finish_predecessor_exists','home_finish_namespace',
    'home_finish_closure_incomplete','home_finish_closed_record','home_finish_closed_changed',
    'home_finish_closed_custody','home_finish_scope')


def home_finish_provenance():
    return {'predecessor':dict(HOME_CHILD_INSPECT_ORIGINAL),'observation':dict(HOME_FINISH_OBSERVATION),
        'predecessor_operation':HOME_CHILD_OPERATION,'predecessor_policy_sha256':home_child_policy()['manifest'],
        'accepted_home_continuation':home_child_provenance()}


def home_finish_policy():
    old=home_child_policy();value={k:v for k,v in old.items() if k!='manifest'}
    value.update(operation=HOME_FINISH_OPERATION,continuation=home_finish_provenance())
    return value|{'manifest':sha(json.dumps(value,sort_keys=True,separators=(',',':')).encode())}


def home_finish_runtime_identity(identity):
    home_child_runtime_identity(identity)
    need(identity['run_id'] not in (HOME_CHILD_INSPECT_ORIGINAL['run_id'],HOME_FINISH_OBSERVATION['run_id'])
         and identity['command_comment_id'] not in (HOME_CHILD_INSPECT_ORIGINAL['command_comment_id'],
                                                  HOME_FINISH_OBSERVATION['command_comment_id']),
         'home_finish_identity')


def home_finish_event_identity(event,env):
    control=env.get('GITHUB_SHA','')
    need(event.get('comment',{}).get('body')==f'{HOME_FINISH_PREFIX} {control} {HOME_FINISH_OPERATION}','command')
    synthetic=json.loads(json.dumps(event));synthetic['comment']['body']=f'{HOME_CHILD_PREFIX} {control} {HOME_CHILD_OPERATION}'
    identity=home_child_event_identity(synthetic,env);home_finish_runtime_identity(identity)
    return identity


def home_finish_observation_value(value):
    pin=HOME_FINISH_OBSERVATION
    need(sha((json.dumps(value,sort_keys=True)+'\n').encode())==pin['receipt_sha256'],'home_finish_observation_digest')
    identity={k:pin[k] for k in ('control_source_sha','run_id','command_comment_id')}
    fixed={**identity,'operation_id':HOME_CHILD_INSPECT_OPERATION,'source_sha':HOME_SOURCE,
        'manifest_sha256':home_child_policy()['manifest'],'read_only':True,'phase':'complete','ssh_started':True,
        'status':'inspection_complete','original_cancelled':HOME_CHILD_INSPECT_ORIGINAL,
        'original_process_liveness':'unknown','historical_zero_write_proven':False,'future_writer_authorized':False}
    need(isinstance(value,dict) and set(value)==set(fixed)|{'inspection_evidence','remote'}
         and all(type(value.get(k)) is type(v) and value[k]==v for k,v in fixed.items()),'home_finish_observation')
    remote=value['remote'];need(valid_home_child_inspect_result(identity,remote),'home_finish_observation')
    need(remote['classification']=='current_before_namespace_absent' and not any(remote['custody'].values())
         and all(row['matches_before'] for row in remote['target_checks'])
         and remote['protected_reference']=='parent' and all(all(row.values()) for row in remote['ancestor_checks'].values()),
         'home_finish_observation')
    # These three paths have no COUNTRY guarded tuple. They are still checked
    # by the unchanged HANDOFF intents and pinned HOME dependency in preflight;
    # this comparison set does not extend the five-row reconciliation scope.
    comparisons=set(HOME_CHILD_PUBLIC_DRIFT)|set(HANDOFF_ORDER)|set(HOME_DEPENDENCIES)
    need({r['path'] for r in remote['protected_mismatches']}==comparisons
         and all(r=={'path':r['path'],'readable':True,'matches_row':True,'matches_identity':False}
                 for r in remote['protected_mismatches'])
         and all(r=={'readable':True,'matches_row':True,'matches_identity':label!='public_config'}
                 for label,r in remote['private_checks'].items()),'home_finish_observation_scope')
    inspection=value['inspection_evidence'];parent=home_child_inspection_value(inspection)
    return parent,inspection


def home_finish_observation_evidence(raw):
    pin=HOME_FINISH_OBSERVATION;need(sha(raw)==pin['artifact_sha256'],'home_finish_observation_artifact')
    with zip_files(raw) as archive:
        need(archive.namelist()==['evidence.json'],'home_finish_observation_artifact')
        data=archive.read('evidence.json')
        need(len(data)<=262144 and sha(data)==pin['receipt_sha256'],'home_finish_observation_digest')
    value=_tv_json(data);home_finish_observation_value(value);return value


def home_finish_heads(identity):
    home_child_inspect_heads(identity)
    relation=api('/compare/'+HOME_FINISH_OBSERVATION['control_source_sha']+'...'+identity['control_source_sha'])
    need(relation.get('status') in ('identical','ahead') and relation.get('behind_by')==0
         and relation.get('merge_base_commit',{}).get('sha')==HOME_FINISH_OBSERVATION['control_source_sha'],
         'home_finish_control_ancestry')


def _home_finish_native_proof(pin,command,conclusion,mode):
    need(pin in (HOME_CHILD_INSPECT_ORIGINAL,HOME_FINISH_OBSERVATION),'home_finish_fixed_pin')
    run=api('/actions/runs/'+pin['run_id']);created=_tv_time(command.get('created_at'))
    need(type(run.get('id')) is int and run['id']==int(pin['run_id']) and run.get('head_sha')==pin['control_source_sha']
         and run.get('head_branch')=='main' and run.get('path')==WORKFLOW and run.get('event')=='issue_comment'
         and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')==conclusion
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff'
         and run.get('repository',{}).get('full_name')==run.get('head_repository',{}).get('full_name')==REPO
         and created<=_tv_time(run.get('created_at'))<=created+300,'home_finish_fixed_run')
    jobs=[j for j in pages('/actions/runs/'+pin['run_id']+'/attempts/1/jobs','jobs') if j.get('name')=='public-install']
    need(len(jobs)==1 and type(jobs[0].get('id')) is int and jobs[0]['id']==pin['job_id']
         and type(jobs[0].get('run_id')) is int and jobs[0]['run_id']==int(pin['run_id'])
         and jobs[0].get('status')=='completed' and jobs[0].get('conclusion')==conclusion,'home_finish_fixed_job')
    steps=[s for s in jobs[0].get('steps',[]) if s.get('name','').startswith('Run python3 scripts/deploy/search3_public_install.py')]
    need(len(steps)==1 and steps[0].get('name')=='Run python3 scripts/deploy/search3_public_install.py '+mode
         and steps[0].get('status')=='completed' and steps[0].get('conclusion')==conclusion
         and created<=_tv_time(run.get('created_at'))<=_tv_time(run.get('run_started_at'))
         <=_tv_time(steps[0].get('started_at'))<=_tv_time(steps[0].get('completed_at'))<=_tv_time(run.get('updated_at')),
         'home_finish_fixed_step')
    artifacts=pages('/actions/runs/'+pin['run_id']+'/artifacts','artifacts')
    if pin==HOME_CHILD_INSPECT_ORIGINAL:
        need(conclusion=='cancelled' and mode=='home-continue' and not artifacts,'home_finish_cancelled_artifacts')
    else:
        need(conclusion=='success' and mode=='home-continue-inspect' and len(artifacts)==1,'home_finish_reader_artifact')
        artifact=artifacts[0]
        need(type(artifact.get('id')) is int and artifact['id']==pin['artifact_id']
             and artifact.get('name')=='search3-public-install-'+pin['run_id']+'-1'
             and artifact.get('expired') is False and artifact.get('digest')=='sha256:'+pin['artifact_sha256']
             and type(artifact.get('workflow_run',{}).get('id')) is int
             and artifact['workflow_run']['id']==int(pin['run_id'])
             and artifact['workflow_run'].get('head_sha')==pin['control_source_sha'],
             'home_finish_reader_artifact')
    reviewed_control(pin['control_source_sha'])
    return run


def home_finish_admission(identity):
    home_finish_runtime_identity(identity);home_finish_heads(identity);reviewed_control(identity['control_source_sha'])
    home_child_repair_admission(identity)
    gate=api('/contents/.github/workflows/deploy-anytoour.yml?ref='+identity['control_source_sha'])
    need(gate.get('type')=='file' and gate.get('encoding')=='base64'
         and sha(base64.b64decode(gate['content']))==SITE_PUBLISHER_GUARD_SHA,'publisher_guard')
    checks=pages('/commits/'+HOME_SOURCE+'/check-runs','check_runs')
    for name in HOME_CHECKS:
        latest=max((c for c in checks if c.get('name')==name and c.get('head_sha')==HOME_SOURCE),
                   key=lambda c:c['id'],default={})
        need(green(latest),'home_source_ci')
    run=api('/actions/runs/'+str(HOME_BUILD_RUN));artifact=api('/actions/artifacts/'+str(HOME_ARTIFACT))
    need(type(run.get('id')) is int and run['id']==HOME_BUILD_RUN and run.get('head_sha')==HOME_SOURCE
         and run.get('head_branch')==HOME_SOURCE_BRANCH and run.get('path')=='.github/workflows/search3-visual-migration.yml'
         and run.get('event')=='pull_request' and type(run.get('run_attempt')) is int and run['run_attempt']==1
         and run.get('status')=='completed' and run.get('conclusion')=='success'
         and run.get('actor',{}).get('login')==run.get('triggering_actor',{}).get('login')=='pyatkoff'
         and run.get('repository',{}).get('full_name')==run.get('head_repository',{}).get('full_name')==REPO,'home_build')
    need(type(artifact.get('id')) is int and artifact['id']==HOME_ARTIFACT
         and artifact.get('name')=='search3-public-live-'+HOME_SOURCE and artifact.get('expired') is False
         and artifact.get('digest')=='sha256:'+HOME_ARTIFACT_SHA
         and type(artifact.get('workflow_run',{}).get('id')) is int and artifact['workflow_run']['id']==HOME_BUILD_RUN
         and artifact['workflow_run'].get('head_sha')==HOME_SOURCE,'home_artifact')
    home_parent_admission();comments=pages('/issues/4709/comments');terminal_times=[]
    for pin,prefix,operation,conclusion,mode,tokens in (
        (HOME_INSPECT_FAILED,HOME_PREFIX,HOME_OPERATION,'failure','home',['blocked_no_replay','NO REPLAY']),
        (HOME_CHILD_INSPECTION,HOME_INSPECT_PREFIX,HOME_INSPECT_OPERATION,'success','home-inspect',['inspection_mismatch','NO REPLAY']),
        (HOME_CHILD_INSPECT_ORIGINAL,HOME_CHILD_PREFIX,HOME_CHILD_OPERATION,'cancelled','home-continue',['CANCELLED','UNKNOWN','CONSUMED','NO REPLAY']),
        (HOME_FINISH_OBSERVATION,HOME_CHILD_INSPECT_PREFIX,HOME_CHILD_INSPECT_OPERATION,'success','home-continue-inspect',
            ['inspection_complete','current_before_namespace_absent','CONSUMED','NO REPLAY'])):
        prior={k:pin[k] for k in ('control_source_sha','run_id','command_comment_id')}
        _unique_update_command(comments,prefix,operation,prior)
        command=next(c for c in comments if c['id']==prior['command_comment_id'])
        terminal=next((c for c in comments if c['id']==pin['terminal_comment_id']),{})
        required=[str(pin[k]) for k in ('run_id','control_source_sha','command_comment_id','job_id')]
        if 'artifact_id' in pin:required += [str(pin[k]) for k in ('artifact_id','artifact_sha256','receipt_sha256')]
        need(command.get('created_at')==command.get('updated_at')
             and terminal.get('created_at')==terminal.get('updated_at')
             and terminal.get('user',{}).get('id')==226193297 and terminal.get('author_association')=='OWNER'
             and all(t in terminal.get('body','') for t in required+tokens)
             and _tv_time(command.get('created_at'))<=_tv_time(terminal.get('created_at')),
             'home_finish_fixed_terminal')
        if pin in (HOME_INSPECT_FAILED,HOME_CHILD_INSPECTION):home_child_fixed_proof(pin,command,conclusion,mode)
        else:
            native=_home_finish_native_proof(pin,command,conclusion,mode)
            need(_tv_time(native.get('updated_at'))<=_tv_time(terminal['created_at']),'home_finish_terminal_time')
        terminal_times.append(_tv_time(terminal['created_at']))
    _unique_update_command(comments,HOME_FINISH_PREFIX,HOME_FINISH_OPERATION,identity)
    command=next(c for c in comments if c['id']==identity['command_comment_id'])
    need(command.get('created_at')==command.get('updated_at') and _tv_time(command['created_at'])>=max(terminal_times),
         'home_finish_command_time')
    need(not any(c.get('body','').strip().split()[:1] in ([HOME_ROLLBACK_PREFIX],[HOME_CHILD_ROLLBACK_PREFIX])
                 and any(op in c.get('body','').split()[1:] for op in (HOME_ROLLBACK_OPERATION,HOME_CHILD_ROLLBACK_OPERATION))
                 for c in comments),'home_finish_rollback_present')
    observation=home_finish_observation_evidence(download(HOME_FINISH_OBSERVATION['artifact_id'],
                                                         HOME_FINISH_OBSERVATION['artifact_sha256']))
    home_finish_heads(identity);return observation


def home_finish_closed_record(identity):
    home_finish_runtime_identity(identity)
    return {'schema_version':1,'record_kind':'home_predecessor_closed_before_successor_reservation',
        'predecessor_operation':HOME_CHILD_OPERATION,'predecessor':dict(HOME_CHILD_INSPECT_ORIGINAL),
        'observation':dict(HOME_FINISH_OBSERVATION),'successor':{'operation_id':HOME_FINISH_OPERATION,
            'source_sha':HOME_SOURCE,'manifest_sha256':home_finish_policy()['manifest'],**identity},
        'predecessor_policy_sha256':home_child_policy()['manifest']}


HOME_FINISH_REMOTE = r'''
_finish_parent_impl=home_handoff_parent
_finish_ancestor_impl=home_check_ancestors
_finish_persist_impl=home_child_persist_reconciliation
_finish_store=None
_finish_directory=None
_finish_record=None
_finish_custody_store=None
_finish_custody_record=None
HOME_PREPARED_PATHS=HOME_PREPARED_PATHS+(FINISH_CUSTODY_PATH,)
def home_finish_namespace_absent(operation):
 with base.parent(operation) as (fd,name):
  try:os.stat(name,dir_fd=fd,follow_symlinks=False)
  except FileNotFoundError:return
  raise RuntimeError('home_finish_namespace')
def home_finish_assert_closure():
 closure=result['closure']
 if not closure['namespace_created']:
  home_finish_namespace_absent(FINISH_PREDECESSOR_OPERATION)
  home_finish_namespace_absent(HOME_POLICY['operation'])
  return
 try:
  require(_finish_store is not None and _finish_record is not None and _finish_directory is not None
          and closure['record_verified'] and closure['durable_verified'],'home_finish_closure_incomplete')
  _finish_store.verify(_finish_store.links)
  require(stamp(os.fstat(_finish_store.fd))==_finish_directory,'home_finish_closed_changed')
  with base.parent(FINISH_PREDECESSOR_OPERATION) as (fd,name):
   require(stamp(os.stat(name,dir_fd=fd,follow_symlinks=False))==_finish_directory,'home_finish_closed_changed')
  require(site_read_records(_finish_store,(FINISH_CLOSED_PATH,))[FINISH_CLOSED_PATH]==_finish_record,
          'home_finish_closed_changed')
  if _finish_custody_store is not None:
   require(site_read_records(_finish_custody_store,(FINISH_CUSTODY_PATH,))[FINISH_CUSTODY_PATH]==_finish_custody_record,
           'home_finish_closed_custody')
  closure['protected_unchanged']=True
 except Exception:
  closure['protected_unchanged']=False
  raise
def home_handoff_parent():
 home_finish_assert_closure()
 value=_finish_parent_impl()
 home_finish_assert_closure()
 return value
def home_check_ancestors():
 home_finish_assert_closure()
 _finish_ancestor_impl()
 home_finish_assert_closure()
def home_finish_close_predecessor(before):
 global _finish_store,_finish_directory,_finish_record
 closure=result['closure']
 require(not closure['namespace_created'] and _finish_store is None,'home_finish_scope')
 home_finish_assert_closure()
 # The old program's first mutating gate uses this exact name. A loser never
 # opens or writes another process's namespace, even if it appears empty.
 try:os.mkdir(FINISH_PREDECESSOR_OPERATION,0o700,dir_fd=base.fd)
 except FileExistsError:raise RuntimeError('home_finish_predecessor_exists') from None
 closure['namespace_created']=True
 sync(base.fd)
 with base.parent(FINISH_PREDECESSOR_OPERATION) as (fd,name):
  info=os.stat(name,dir_fd=fd,follow_symlinks=False)
  require(stat.S_ISDIR(info.st_mode) and stat.S_IMODE(info.st_mode)==0o700 and info.st_uid==os.getuid(),
          'home_finish_closed_record')
  created=directory(info)
 _finish_store=Files(os.environ['HOME']+'/.anytoour-search3-root-gateway/'+FINISH_PREDECESSOR_OPERATION)
 private_root(_finish_store)
 require(directory(os.fstat(_finish_store.fd))==created,'home_finish_closed_record')
 raw=canonical(FINISH_CLOSED_RECORD);closure['record_write_attempted']=True
 _finish_store.create(FINISH_CLOSED_PATH,raw)
 retained=site_read_records(_finish_store,(FINISH_CLOSED_PATH,))[FINISH_CLOSED_PATH]
 require(retained[2]==raw and retained[0]['sha256']==FINISH_CLOSED_SHA,'home_finish_closed_record')
 closure.update(record_verified=True,record_sha256=FINISH_CLOSED_SHA)
 sync(_finish_store.fd);sync(base.fd)
 _finish_store.verify(_finish_store.links)
 _finish_directory=stamp(os.fstat(_finish_store.fd));_finish_record=retained
 closure['durable_verified']=True
 home_finish_assert_closure()
 # Never recapture protected values after the closure. Compare to the exact
 # validated tuples from this invocation's original finite preflight.
 home_check_ancestors();home_check_targets(before);home_finish_assert_closure()
 closure['guards_rechecked']=True
def home_child_persist_reconciliation(store):
 global _finish_custody_store,_finish_custody_record
 require(result['closure']['guards_rechecked'],'home_finish_closure_incomplete')
 home_finish_assert_closure();_finish_persist_impl(store);home_finish_assert_closure()
 custody={'schema_version':1,'operation_id':HOME_POLICY['operation'],'manifest_sha256':HOME_POLICY['manifest'],
          **IDENTITY,'predecessor_operation':FINISH_PREDECESSOR_OPERATION,'closed_record':FINISH_CLOSED_RECORD,
          'closed_sha256':FINISH_CLOSED_SHA,'directory_identity':list(_finish_directory),
          'record_identity':[dict(_finish_record[0]),list(_finish_record[1])]}
 raw=canonical(custody);store.create(FINISH_CUSTODY_PATH,raw)
 retained=site_read_records(store,(FINISH_CUSTODY_PATH,))[FINISH_CUSTODY_PATH]
 require(retained[2]==raw,'home_finish_closed_custody')
 _finish_custody_store=store;_finish_custody_record=retained
 result['closure'].update(custody_persisted=True,custody_sha256=digest(raw))
 home_finish_assert_closure()
'''


def _home_finish_remote_body():
    body=_home_child_remote_body(False)
    initial="public=base=original=updated=published=refactored=handed=private=None;reserved=False;switched=[];owned={};prior={};prepared=None;reservation=None"
    need(body.count(initial)==1,'home_finish_template')
    closure={'predecessor_operation':HOME_CHILD_OPERATION,**{k:False for k in HOME_FINISH_CLOSURE_FLAGS},
             'record_sha256':None,'custody_sha256':None,'retained_on_failure':True}
    body=body.replace(initial,"result.update(closure="+repr(closure)+",predecessor_liveness='unknown',historical_predecessor_zero_writes_proven=False)\n"+initial,1)
    anchor=" home_check_ancestors();home_check_targets(before)\n try:os.mkdir(HOME_POLICY['operation'],0o700,dir_fd=base.fd)"
    need(body.count(anchor)==1,'home_finish_template')
    body=body.replace(anchor," home_check_ancestors();home_check_targets(before)\n home_finish_close_predecessor(before)\n try:os.mkdir(HOME_POLICY['operation'],0o700,dir_fd=base.fd)",1)
    anchor="except Exception as error:\n result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'\n if reserved:"
    need(body.count(anchor)==1,'home_finish_template')
    body=body.replace(anchor,"except Exception as error:\n result['reason']=str(error) if type(error) is RuntimeError and re_safe(str(error)) else 'operation_failed'\n if result['closure']['namespace_created']:result['status']='unknown_no_replay'\n if reserved:",1)
    anchor=" for store in (private,handed,refactored,published,updated,original,base,public):"
    need(body.count(anchor)==1,'home_finish_template')
    body=body.replace(anchor," for store in (_finish_store,private,handed,refactored,published,updated,original,base,public):",1)
    ast.parse(body);return body


def home_finish_remote_program(policy,files,identity,observation_value,*,tv_successor=None):
    need(policy==home_finish_policy(),'home_finish_policy');home_finish_runtime_identity(identity)
    parent,inspection=home_finish_observation_value(observation_value)
    need(isinstance(files,dict) and set(files)==set(HOME_ORDER),'home_payload')
    for path in HOME_ORDER:
        raw=base64.b64decode(files[path],validate=True)
        need(0<len(raw)<=MAX_BYTES and HOME_TARGETS[path]['after']==
             {'state':'present','mode':'0644','size':len(raw),'sha256':sha(raw)},'home_payload_bytes')
    closed=home_finish_closed_record(identity)
    prefix=_home_child_program_prefix(home_child_policy(),identity,parent,inspection,tv_successor)
    program=(prefix+'\nHOME_POLICY='+repr(policy)+'\nCHILD_PROVENANCE='+repr(home_finish_provenance())
        +'\nFINISH_PREDECESSOR_OPERATION='+repr(HOME_CHILD_OPERATION)+'\nFINISH_CLOSED_PATH='+repr(HOME_FINISH_CLOSED_PATH)
        +'\nFINISH_CUSTODY_PATH='+repr(HOME_FINISH_CUSTODY_PATH)+'\nFINISH_CLOSED_RECORD='+repr(closed)
        +'\nFINISH_CLOSED_SHA='+repr(sha(json.dumps(closed,sort_keys=True,separators=(',',':')).encode()))
        +'\ndef re_safe(value): return value in '+repr(HOME_FINISH_REASONS)+'\n'+HOME_FINISH_REMOTE
        +'\nPAYLOAD='+repr(files)+'\n'+_home_finish_remote_body())
    ast.parse(program);return program


def valid_home_finish_result(identity,result):
    home_finish_runtime_identity(identity);policy=home_finish_policy()
    need(isinstance(result,dict) and result.get('operation_id')==HOME_FINISH_OPERATION
         and result.get('manifest_sha256')==policy['manifest']
         and result.get('continuation')==home_finish_provenance()
         and result.get('predecessor_liveness')=='unknown'
         and type(result.get('historical_predecessor_zero_writes_proven')) is bool
         and result['historical_predecessor_zero_writes_proven'] is False,'home_finish_receipt_identity')
    closure=result.get('closure');keys=set(HOME_FINISH_CLOSURE_FLAGS)|{
        'predecessor_operation','record_sha256','custody_sha256','retained_on_failure'}
    need(isinstance(closure,dict) and set(closure)==keys
         and closure['predecessor_operation']==HOME_CHILD_OPERATION and closure['retained_on_failure'] is True
         and all(type(closure[k]) is bool for k in HOME_FINISH_CLOSURE_FLAGS),'home_finish_receipt_closure')
    created=closure['namespace_created'];verified=closure['record_verified'];custody=closure['custody_persisted']
    expected=sha(json.dumps(home_finish_closed_record(identity),sort_keys=True,separators=(',',':')).encode())
    need((closure['record_sha256']==expected if verified else closure['record_sha256'] is None)
         and ((isinstance(closure['custody_sha256'],str) and re.fullmatch('[a-f0-9]{64}',closure['custody_sha256']))
              if custody else closure['custody_sha256'] is None),'home_finish_receipt_digest')
    need((created or not any(closure[k] for k in HOME_FINISH_CLOSURE_FLAGS))
         and (not closure['record_write_attempted'] or created)
         and (not verified or closure['record_write_attempted'])
         and (not closure['durable_verified'] or verified)
         and (not closure['guards_rechecked'] or closure['durable_verified'])
         and (not custody or closure['guards_rechecked'] and result.get('reservation_created') is True
              and result.get('lineage_persisted') is True)
         and (not closure['protected_unchanged'] or closure['durable_verified']),
         'home_finish_receipt_closure')
    need('reason' not in result or result['reason'] in HOME_FINISH_REASONS,'home_finish_receipt_reason')
    base={k:v for k,v in result.items() if k not in ('closure','predecessor_liveness','historical_predecessor_zero_writes_proven')}
    base.update(operation_id=HOME_CHILD_OPERATION,manifest_sha256=home_child_policy()['manifest'],continuation=home_child_provenance())
    if base.get('reason') in HOME_FINISH_REASONS and base.get('reason') not in HOME_CHILD_REASONS:base['reason']='operation_failed'
    complete=valid_home_child_result(identity,base)
    if not created:
        need(result['status']=='blocked_no_replay' and not result['reservation_created']
             and not result['written_files'] and not result['rollback_files'],'home_finish_receipt_incomplete')
    elif not result['reservation_created']:
        need(result['status']=='unknown_no_replay' and result['phase']=='preflight'
             and not result['written_files'] and not result['rollback_files'],'home_finish_receipt_incomplete')
    else:
        need(closure['guards_rechecked'] and closure['durable_verified'],'home_finish_receipt_incomplete')
    if complete or result['written_files']:
        need(custody and closure['record_sha256']==expected,'home_finish_receipt_incomplete')
    if complete or result['status']=='rolled_back_no_replay':
        need(created and closure['protected_unchanged'] and closure['durable_verified'],'home_finish_receipt_incomplete')
    if complete:need(all(closure[k] for k in HOME_FINISH_CLOSURE_FLAGS),'home_finish_receipt_incomplete')
    need(len(json.dumps(result,sort_keys=True).encode())<=HOME_FINISH_RECEIPT_LIMIT,'home_finish_receipt_limit')
    return complete


def home_finish_checkpoint(work,evidence):
    # This is runner-local custody, never a server write. A cancellation leaves
    # the previous complete JSON rather than a partial replacement. Checkpoints
    # contain no SSH program/key and never stand in for a remote receipt.
    raw=(json.dumps(evidence,sort_keys=True)+'\n').encode()
    need(len(raw)<=524288,'home_finish_checkpoint_limit')
    pending=work/'evidence.pending';created=None;handle=None
    try:
        handle=os.open(pending,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
        created=os.fstat(handle)
        with os.fdopen(handle,'wb') as stream:
            handle=None;stream.write(raw);stream.flush();os.fsync(stream.fileno())
        now=pending.lstat()
        need((now.st_dev,now.st_ino)==(created.st_dev,created.st_ino)
             and stat.S_ISREG(now.st_mode) and now.st_nlink==1,'home_finish_checkpoint_changed')
        os.replace(pending,work/'evidence.json')
        directory_fd=os.open(work,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
        try:os.fsync(directory_fd)
        finally:os.close(directory_fd)
    finally:
        if handle is not None:os.close(handle)
        if created is not None:
            try:
                now=pending.lstat()
                if (now.st_dev,now.st_ino)==(created.st_dev,created.st_ino):pending.unlink()
            except FileNotFoundError:pass


def home_finish():
    work=Path(os.environ['RUNNER_TEMP'])/'search3-public-install';work.mkdir(mode=0o700)
    key,known=work/'key',work/'known'
    evidence={'status':'blocked_no_replay','operation_id':HOME_FINISH_OPERATION,'source_sha':HOME_SOURCE,
        'manifest_sha256':home_finish_policy()['manifest'],'read_only':False,'phase':'identity',
        'ssh_requested':False,'ssh_started':'unknown','remote_outcome':'unknown',
        'continuation':home_finish_provenance(),'predecessor_liveness':'unknown',
        'historical_predecessor_zero_writes_proven':False}
    complete=False
    try:
        identity=home_finish_event_identity(json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text()),os.environ)
        evidence.update(identity,phase='admission');home_finish_checkpoint(work,evidence)
        observation=home_finish_admission(identity);evidence['phase']='artifact'
        parent,_=home_finish_observation_value(observation)
        previous,files=home_package(download(HOME_ARTIFACT,HOME_ARTIFACT_SHA),parent)
        need(previous==home_policy(),'home_finish_payload_policy')
        dependency=tv_successor_evidence()
        program=home_finish_remote_program(home_finish_policy(),files,identity,observation,tv_successor=dependency)
        evidence.update(parent_handoff=home_parent_identity(),observation_evidence=observation,phase='connection_preparation')
        home_finish_checkpoint(work,evidence)
        host,user=os.environ['PREVIEW_HOST'],os.environ['PREVIEW_USER']
        need(re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]{0,252}',host) and re.fullmatch(r'[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}',user),'ssh_identity')
        raw=os.environ['PREVIEW_KEY'].replace('\r','');need(0<len(raw)<=32768,'ssh_key')
        if 'PRIVATE KEY' not in raw:raw=base64.b64decode(raw,validate=True).decode()
        with key.open('x') as stream:stream.write(raw.rstrip()+'\n')
        key.chmod(0o600)
        scan=carrier.run(['ssh-keyscan','-T','15','-t','ed25519',host]);need(0<len(scan)<=8192,'host_key')
        known.write_bytes(scan);known.chmod(0o600)
        options=['-i',str(key),'-o','StrictHostKeyChecking=yes','-o','UserKnownHostsFile='+str(known),
                 '-o','GlobalKnownHostsFile=/dev/null','-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','ConnectTimeout=15']
        need(home_finish_admission(identity)==observation,'home_finish_observation_changed')
        need(tv_successor_evidence()==dependency,'tv_successor_changed')
        evidence.update(status='unknown_no_replay',phase='remote_requested',ssh_requested=True)
        home_finish_checkpoint(work,evidence)
        response=carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'],input=program.encode(),timeout=180)
        need(len(response)<=HOME_FINISH_RECEIPT_LIMIT,'home_finish_receipt_limit')
        result=_tv_json(response);complete=valid_home_finish_result(identity,result)
        evidence.update(remote=result,ssh_started=True,remote_outcome=result['status'])
        home_finish_heads(identity);evidence.update(status=result['status'],phase='complete')
    except Exception as error:
        complete=False
        if evidence['ssh_requested']:evidence['status']='unknown_no_replay'
        evidence['error_type']=type(error).__name__ if type(error) in (RuntimeError,ValueError,KeyError,OSError,subprocess.CalledProcessError,subprocess.TimeoutExpired) else 'HomeFinishError'
    finally:
        key.unlink(missing_ok=True);known.unlink(missing_ok=True)
        home_finish_checkpoint(work,evidence)
    print(json.dumps(evidence,sort_keys=True));need(complete,'home_finish_not_verified')


if __name__ == '__main__':
    import sys as _home_finish_sys
    if _home_finish_sys.argv[1:]==['home-finish']:
        home_finish()
        raise SystemExit(0)


def command_mode(arguments):
    if arguments == ['home-inspect']: return 'home-inspect'
    if arguments == ['home']: return 'home'
    if arguments == ['home-rollback']: return 'home-rollback'
    if arguments == ['handoff']: return 'handoff'
    if arguments == ['handoff-rollback']: return 'handoff-rollback'
    if arguments == ['refactor']: return 'refactor'
    if arguments == ['refactor-rollback']: return 'refactor-rollback'
    if arguments == ['site-continue']: return 'site-continue'
    if arguments == ['site-continue-rollback']: return 'site-continue-rollback'
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
        dependency = tv_successor_evidence()
        program = rollback_remote_program(policy, identity, original, accepted, tv_successor=dependency)
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
        need(tv_successor_evidence() == dependency, 'tv_successor_changed')
        evidence.update(status='unknown_no_replay', phase='remote', ssh_started=True)
        response = carrier.run(['ssh',*options,user+'@'+host,'python3 -I -S -B -'],
                               input=program.encode(), timeout=180)
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
    if mode == 'home-inspect': home_inspect()
    elif mode == 'home': home()
    elif mode == 'home-rollback': home(rollback=True)
    elif mode == 'handoff': handoff()
    elif mode == 'handoff-rollback': handoff(rollback=True)
    elif mode == 'refactor': refactor()
    elif mode == 'refactor-rollback': refactor(rollback=True)
    elif mode == 'site-continue': site_update(continuation=True)
    elif mode == 'site-continue-rollback': site_update(rollback=True,continuation=True)
    elif mode == 'site-inspect': site_inspect()
    elif mode == 'site-update': site_update()
    elif mode == 'site-rollback': site_update(rollback=True)
    elif mode == 'update': update()
    elif mode == 'update-rollback': update(rollback=True)
    elif mode == 'rollback': rollback()
    elif mode == 'continue': install(continuation=True)
    else: install(mode)
